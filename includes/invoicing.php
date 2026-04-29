<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Create invoice in Invoice Ninja via REST API.
 */
function simple_hotel_crm_rest_create_invoice( WP_REST_Request $request ) {
    $booking_id = absint( $request->get_param( 'booking_id' ) );
    $reserved_room_id = absint( $request->get_param( 'reserved_room_id' ) );
    if ( $booking_id <= 0 || $reserved_room_id <= 0 ) {
        return new WP_Error( 'invalid_booking', __( 'Invalid booking or room reference.', 'simple-hotel-crm' ), [ 'status' => 400 ] );
    }

    $api_url = get_option( 'simple_hotel_crm_invoice_ninja_url', '' );
    $api_token = get_option( 'simple_hotel_crm_invoice_ninja_token', '' );

    if ( empty( $api_url ) || empty( $api_token ) ) {
        return new WP_Error( 'invoice_ninja_not_configured', __( 'Invoice Ninja API not configured. Please set URL and token in settings.', 'simple-hotel-crm' ), [ 'status' => 500 ] );
    }

    $overlay = simple_hotel_crm_get_booking_overlay( $reserved_room_id );
    if ( empty( $overlay ) ) {
        return new WP_Error( 'no_overlay_data', __( 'No overlay data for this room booking yet. Save the tariff/commission/extras first.', 'simple-hotel-crm' ), [ 'status' => 404 ] );
    }

    $client_id = '';
    $source = simple_hotel_crm_get_data_source();
    if ( 'external_pg' === $source ) {
        $connection = simple_hotel_crm_get_external_pg_connection();
        if ( is_wp_error( $connection ) ) {
            return $connection;
        }

        $client_result = pg_query_params( $connection, 'SELECT invoice_ninja_client_id FROM bookings WHERE id = $1 LIMIT 1', [ $booking_id ] );
        if ( ! $client_result || 0 === pg_num_rows( $client_result ) ) {
            return new WP_Error( 'booking_not_found', __( 'Booking not found in the external database.', 'simple-hotel-crm' ), [ 'status' => 404 ] );
        }

        $client_row = pg_fetch_assoc( $client_result );
        $client_id = (string) ( $client_row['invoice_ninja_client_id'] ?? '' );
    } elseif ( 'wp_sync' === $source ) {
        global $wpdb;
        $crm_bookings_table = simple_hotel_crm_bookings_table();
        $client_id = (string) $wpdb->get_var( $wpdb->prepare( "SELECT invoice_ninja_client_id FROM {$crm_bookings_table} WHERE id = %d LIMIT 1", $booking_id ) );
        if ( '' === $client_id ) {
            return new WP_Error( 'booking_not_found', __( 'Booking not found in WordPress CRM data, or it has no Invoice Ninja client ID.', 'simple-hotel-crm' ), [ 'status' => 404 ] );
        }
    } else {
        $booking = get_post( $booking_id );
        if ( ! $booking || 'mphb_booking' !== $booking->post_type ) {
            return new WP_Error( 'booking_not_found', __( 'Booking not found.', 'simple-hotel-crm' ), [ 'status' => 404 ] );
        }

        $client_id = (string) get_post_meta( $booking_id, '_mphb_customer_id', true );
    }

    if ( '' === $client_id ) {
        return new WP_Error( 'no_customer', __( 'This booking has no linked Invoice Ninja client ID.', 'simple-hotel-crm' ), [ 'status' => 400 ] );
    }

    $invoice_lines = [];
    $total = 0.0;

    if ( isset( $overlay['manual_tarif'] ) && '' !== $overlay['manual_tarif'] && null !== $overlay['manual_tarif'] ) {
        $tarif = (float) $overlay['manual_tarif'];
        $invoice_lines[] = [ 'product_key' => 'room-charge', 'quantity' => 1, 'rate' => $tarif ];
        $total += $tarif;
    }

    if ( isset( $overlay['extras_total'] ) && '' !== $overlay['extras_total'] && null !== $overlay['extras_total'] ) {
        $extras = (float) $overlay['extras_total'];
        $invoice_lines[] = [ 'product_key' => 'extras-charge', 'quantity' => 1, 'rate' => $extras ];
        $total += $extras;
    }

    if ( isset( $overlay['manual_commission'] ) && '' !== $overlay['manual_commission'] && null !== $overlay['manual_commission'] ) {
        $commission = (float) $overlay['manual_commission'];
        $invoice_lines[] = [ 'product_key' => 'booking-commission', 'quantity' => 1, 'rate' => -$commission ];
        $total -= $commission;
    }

    if ( empty( $invoice_lines ) ) {
        return new WP_Error( 'empty_invoice', __( 'No invoiceable lines were found in the saved overlay data.', 'simple-hotel-crm' ), [ 'status' => 400 ] );
    }

    $payload = [
        'client_id' => $client_id,
        'lines' => $invoice_lines,
        'amount' => round( $total, 2 ),
        'is_amount_discount' => false,
        'discount' => 0,
    ];

    $response = wp_remote_post( trailingslashit( $api_url ) . 'api/v1/invoices', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ],
        'body' => wp_json_encode( $payload ),
        'timeout' => 15,
    ] );

    if ( is_wp_error( $response ) ) {
        return new WP_Error( 'invoice_ninja_request_failed', $response->get_error_message(), [ 'status' => 500 ] );
    }

    $code = wp_remote_retrieve_response_code( $response );
    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );

    if ( 201 !== $code || empty( $data['id'] ) ) {
        return new WP_Error( 'invoice_ninja_invalid_response', __( 'Failed to create invoice. Response:', 'simple-hotel-crm' ) . ' ' . $code . ' - ' . $body, [ 'status' => $code >= 500 ? 502 : 500 ] );
    }

    if ( 'external_pg' === $source ) {
        $connection = simple_hotel_crm_get_external_pg_connection();
        if ( ! is_wp_error( $connection ) ) {
            pg_query_params( $connection, 'UPDATE bookings SET invoice_ninja_invoice_id = $1, invoiced_at = NOW() WHERE id = $2', [ (string) $data['id'], $booking_id ] );
        }
    } elseif ( 'wp_sync' === $source ) {
        global $wpdb;
        $crm_bookings_table = simple_hotel_crm_bookings_table();
        $sync_bookings_table = simple_hotel_crm_sync_bookings_table();
        $wpdb->update(
            $crm_bookings_table,
            [
                'invoice_ninja_invoice_id' => (string) $data['id'],
                'invoiced_at' => current_time( 'mysql' ),
            ],
            [ 'id' => $booking_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );
        $wpdb->update(
            $sync_bookings_table,
            [ 'invoice_ninja_invoice_id' => (string) $data['id'] ],
            [ 'external_booking_room_id' => $reserved_room_id ],
            [ '%s' ],
            [ '%d' ]
        );
    } else {
        update_post_meta( $booking_id, '_simple_hotel_crm_invoice_ninja_id', $data['id'] );
    }

    return rest_ensure_response( [
        'success' => true,
        'invoice_id' => $data['id'],
        'invoice_number' => $data['invoice_number'] ?? '',
        'amount' => $data['amount'] ?? $total,
    ] );
}
