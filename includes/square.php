<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'SQUARE_API_BASE', 'https://connect.squareup.com' );

function simple_hotel_crm_square_maybe_add_columns() {
    global $wpdb;
    $table = simple_hotel_crm_bookings_table();

    $columns = $wpdb->get_col( "DESCRIBE {$table}" );
    $add = [];

    if ( ! in_array( 'payment_status', $columns, true ) ) {
        $add[] = "ADD COLUMN payment_status varchar(20) NOT NULL DEFAULT 'pending'";
    }
    if ( ! in_array( 'payment_method', $columns, true ) ) {
        $add[] = "ADD COLUMN payment_method varchar(50) NULL";
    }
    if ( ! in_array( 'square_checkout_id', $columns, true ) ) {
        $add[] = "ADD COLUMN square_checkout_id varchar(191) NULL";
    }

    if ( ! empty( $add ) ) {
        $sql = "ALTER TABLE {$table} " . implode( ', ', $add );
        $wpdb->query( $sql );
    }
}

function simple_hotel_crm_square_get_access_token() {
    return get_option( 'simple_hotel_crm_square_access_token', '' );
}

function simple_hotel_crm_square_get_location_id() {
    return get_option( 'simple_hotel_crm_square_location_id', '' );
}

function simple_hotel_crm_square_get_device_id() {
    return get_option( 'simple_hotel_crm_square_device_id', '' );
}

function simple_hotel_crm_square_is_configured() {
    $token = simple_hotel_crm_square_get_access_token();
    $location = simple_hotel_crm_square_get_location_id();
    $device = simple_hotel_crm_square_get_device_id();
    return ! empty( $token ) && ! empty( $location ) && ! empty( $device );
}

function simple_hotel_crm_square_api_request( $method, $path, $body = null ) {
    $token = simple_hotel_crm_square_get_access_token();
    if ( empty( $token ) ) {
        return new WP_Error( 'square_no_token', 'Square access token not configured.' );
    }

    $url = SQUARE_API_BASE . $path;
    $args = [
        'method' => $method,
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
            'Square-Version' => '2026-05-19',
        ],
        'timeout' => 30,
    ];

    if ( null !== $body ) {
        $args['body'] = wp_json_encode( $body );
    }

    $response = wp_remote_request( $url, $args );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $code = wp_remote_retrieve_response_code( $response );
    $data = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( $code < 200 || $code >= 300 ) {
        $msg = isset( $data['errors'][0]['detail'] ) ? $data['errors'][0]['detail'] : 'Square API error (HTTP ' . $code . ')';
        return new WP_Error( 'square_api_error', $msg );
    }

    return $data;
}

function simple_hotel_crm_square_create_terminal_checkout( $booking_id, $amount ) {
    $location_id = simple_hotel_crm_square_get_location_id();
    if ( empty( $location_id ) ) {
        return new WP_Error( 'square_no_location', 'Square Location ID not configured.' );
    }

    $amount_cents = round( (float) $amount * 100 );
    if ( $amount_cents <= 0 ) {
        return new WP_Error( 'square_invalid_amount', 'Invalid payment amount.' );
    }

    $idempotency_key = 'booking-' . $booking_id . '-' . time();

    $device_id = simple_hotel_crm_square_get_device_id();
    if ( empty( $device_id ) ) {
        return new WP_Error( 'square_no_device', 'Square Device ID not configured.' );
    }

    $body = [
        'idempotency_key' => $idempotency_key,
        'checkout' => [
            'amount_money' => [
                'amount' => $amount_cents,
                'currency' => 'EUR',
            ],
            'reference_id' => (string) $booking_id,
            'note' => 'Booking #' . $booking_id,
            'device_options' => [
                'device_id' => $device_id,
                'skip_receipt_screen' => false,
                'tip_settings' => [
                    'allow_tipping' => true,
                ],
            ],
        ],
    ];

    $result = simple_hotel_crm_square_api_request( 'POST', '/v2/terminals/checkouts', $body );

    if ( is_wp_error( $result ) ) {
        return $result;
    }

    $checkout_id = isset( $result['checkout']['id'] ) ? $result['checkout']['id'] : '';
    if ( empty( $checkout_id ) ) {
        return new WP_Error( 'square_no_checkout_id', 'No checkout ID returned from Square.' );
    }

    update_post_meta( $booking_id, '_square_checkout_id', $checkout_id );
    update_post_meta( $booking_id, '_square_checkout_status', 'pending' );

    return $result;
}

function simple_hotel_crm_square_get_checkout_status( $checkout_id ) {
    $result = simple_hotel_crm_square_api_request( 'GET', '/v2/terminals/checkouts/' . $checkout_id );

    if ( is_wp_error( $result ) ) {
        return $result;
    }

    return isset( $result['checkout']['status'] ) ? $result['checkout']['status'] : 'unknown';
}

function simple_hotel_crm_square_cancel_checkout( $checkout_id ) {
    $idempotency_key = 'cancel-' . $checkout_id . '-' . time();
    $body = [ 'idempotency_key' => $idempotency_key ];

    return simple_hotel_crm_square_api_request( 'POST', '/v2/terminals/checkouts/' . $checkout_id . '/cancel', $body );
}

function simple_hotel_crm_square_handle_payment_complete( $booking_id, $checkout_id ) {
    global $wpdb;
    $bookings_table = simple_hotel_crm_bookings_table();

    $wpdb->update(
        $bookings_table,
        [ 'payment_status' => 'paid', 'payment_method' => 'square_terminal', 'square_checkout_id' => $checkout_id ],
        [ 'id' => $booking_id ],
        [ '%s', '%s', '%s' ],
        [ '%d' ]
    );

    update_post_meta( $booking_id, '_square_checkout_status', 'completed' );

    $invoice = simple_hotel_crm_create_invoice_ninja_invoice( $booking_id );
    if ( is_wp_error( $invoice ) ) {
        return $invoice;
    }

    return true;
}

function simple_hotel_crm_square_get_payment_status_label( $status ) {
    $labels = [
        'pending' => __( 'Awaiting terminal', 'simple-hotel-crm' ),
        'in_progress' => __( 'Payment in progress', 'simple-hotel-crm' ),
        'completed' => __( 'Paid', 'simple-hotel-crm' ),
        'canceled' => __( 'Cancelled', 'simple-hotel-crm' ),
        'failed' => __( 'Failed', 'simple-hotel-crm' ),
    ];
    return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
}

function simple_hotel_crm_square_get_webhook_url() {
    return rest_url( 'simple-hotel-crm/v1/square-webhook' );
}

function simple_hotel_crm_square_get_webhook_signature_key() {
    return get_option( 'simple_hotel_crm_square_webhook_signature_key', '' );
}

function simple_hotel_crm_square_verify_webhook_signature( $body, $signature ) {
    $signature_key = simple_hotel_crm_square_get_webhook_signature_key();
    if ( empty( $signature_key ) || empty( $signature ) ) {
        return false;
    }
    $expected = base64_encode( hash_hmac( 'sha256', $body, $signature_key, true ) );
    return hash_equals( $expected, $signature );
}
