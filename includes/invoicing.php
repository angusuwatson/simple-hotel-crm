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

    global $wpdb;
    $crm_bookings_table = simple_hotel_crm_bookings_table();
    $booking_rooms_table = simple_hotel_crm_booking_rooms_table();
    $client_id = (string) $wpdb->get_var( $wpdb->prepare( "SELECT invoice_ninja_client_id FROM {$crm_bookings_table} WHERE id = %d LIMIT 1", $booking_id ) );
    if ( '' === $client_id ) {
        return new WP_Error( 'booking_not_found', __( 'Booking not found in WordPress CRM data, or it has no Invoice Ninja client ID.', 'simple-hotel-crm' ), [ 'status' => 404 ] );
    }

    if ( '' === $client_id ) {
        return new WP_Error( 'no_customer', __( 'This booking has no linked Invoice Ninja client ID.', 'simple-hotel-crm' ), [ 'status' => 400 ] );
    }

    $booking_room = $wpdb->get_row(
        $wpdb->prepare( "SELECT room_rate_amount, extras_amount, tourist_tax_amount, commission_amount, total_amount FROM {$booking_rooms_table} WHERE booking_id = %d AND legacy_reserved_room_id = %d LIMIT 1", $booking_id, $reserved_room_id ),
        ARRAY_A
    );

    $invoice_lines = [];
    $total = 0.0;

    $tarif = null;
    if ( is_array( $booking_room ) ) {
        $tarif = (float) $booking_room['room_rate_amount'];
    }
    if ( null !== $tarif && $tarif != 0.0 ) {
        $invoice_lines[] = [ 'product_key' => 'room-charge', 'quantity' => 1, 'rate' => round( $tarif, 2 ) ];
        $total += $tarif;
    }

    $extras = null;
    if ( is_array( $booking_room ) ) {
        $extras = (float) $booking_room['extras_amount'];
    }
    if ( null !== $extras && $extras != 0.0 ) {
        $invoice_lines[] = [ 'product_key' => 'extras-charge', 'quantity' => 1, 'rate' => round( $extras, 2 ) ];
        $total += $extras;
    }

    $tourist_tax = is_array( $booking_room ) ? (float) $booking_room['tourist_tax_amount'] : 0.0;
    if ( $tourist_tax != 0.0 ) {
        $invoice_lines[] = [ 'product_key' => 'tourist-tax', 'quantity' => 1, 'rate' => round( $tourist_tax, 2 ) ];
        $total += $tourist_tax;
    }

    $commission = null;
    if ( is_array( $booking_room ) && isset( $booking_room['commission_amount'] ) ) {
        $commission = (float) $booking_room['commission_amount'];
    } elseif ( null !== $tarif ) {
        $commission = simple_hotel_crm_calculate_channel_commission( (string) $wpdb->get_var( $wpdb->prepare( "SELECT source_channel FROM {$crm_bookings_table} WHERE id = %d LIMIT 1", $booking_id ) ), (float) $tarif );
    }
    if ( null !== $commission && $commission != 0.0 ) {
        $invoice_lines[] = [ 'product_key' => 'booking-commission', 'quantity' => 1, 'rate' => -round( $commission, 2 ) ];
        $total -= $commission;
    }

    if ( empty( $invoice_lines ) ) {
        return new WP_Error( 'empty_invoice', __( 'No invoiceable lines were found.', 'simple-hotel-crm' ), [ 'status' => 400 ] );
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

    return rest_ensure_response( [
        'success' => true,
        'invoice_id' => $data['id'],
        'invoice_number' => $data['invoice_number'] ?? '',
        'amount' => $data['amount'] ?? $total,
    ] );
}
