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
            'Square-Version' => '2026-05-20',
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

function simple_hotel_crm_square_create_terminal_checkout( $booking_id, $amount, $skip_receipt = false, $note = '' ) {
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

    if ( '' === $note ) {
        $note = 'Booking #' . $booking_id;
    }

    $body = [
        'idempotency_key' => $idempotency_key,
        'checkout' => [
            'amount_money' => [
                'amount' => $amount_cents,
                'currency' => 'EUR',
            ],
            'reference_id' => (string) $booking_id,
            'note' => $note,
            'location_id' => $location_id,
            'device_options' => [
                'device_id' => $device_id,
                'skip_receipt_screen' => $skip_receipt,
                'tip_settings' => [
                    'allow_tipping' => false,
                ],
            ],
        ],
    ];

    $result = simple_hotel_crm_square_api_request( 'POST', '/v2/terminals/checkouts', $body );

    if ( is_wp_error( $result ) ) {
        error_log( 'LGF: create_terminal_checkout FAILED: ' . $result->get_error_message() );
        return $result;
    }

    $checkout_id = isset( $result['checkout']['id'] ) ? $result['checkout']['id'] : '';
    if ( empty( $checkout_id ) ) {
        error_log( 'LGF: create_terminal_checkout no checkout ID in response' );
        return new WP_Error( 'square_no_checkout_id', 'No checkout ID returned from Square.' );
    }

    update_post_meta( $booking_id, '_square_checkout_id', $checkout_id );
    update_post_meta( $booking_id, '_square_checkout_status', 'pending' );
    error_log( 'LGF: create_terminal_checkout OK id=' . $checkout_id . ' booking=' . $booking_id );

    return $result;
}

function simple_hotel_crm_square_get_checkout_status( $checkout_id ) {
    $result = simple_hotel_crm_square_api_request( 'GET', '/v2/terminals/checkouts/' . $checkout_id );

    if ( is_wp_error( $result ) ) {
        return $result;
    }

    return isset( $result['checkout']['status'] ) ? $result['checkout']['status'] : 'unknown';
}

function simple_hotel_crm_square_create_confirmation_action( $booking_id, $bill_text ) {
    $device_id = simple_hotel_crm_square_get_device_id();
    if ( empty( $device_id ) ) {
        return new WP_Error( 'square_no_device', 'Square Device ID not configured.' );
    }

    $idempotency_key = 'confirm-booking-' . $booking_id . '-' . time();

    $body = [
        'idempotency_key' => $idempotency_key,
        'action' => [
            'type' => 'CONFIRMATION',
            'device_id' => $device_id,
            'await_next_action' => true,
            'confirmation_options' => [
                'title' => 'Booking #' . $booking_id,
                'body' => $bill_text,
                'agree_button_text' => 'OK',
            ],

        ],
    ];

    $result = simple_hotel_crm_square_api_request( 'POST', '/v2/terminals/actions', $body );

    if ( is_wp_error( $result ) ) {
        return $result;
    }

    $action_id = isset( $result['action']['id'] ) ? $result['action']['id'] : '';
    if ( empty( $action_id ) ) {
        return new WP_Error( 'square_no_action_id', 'No action ID returned from Square.' );
    }

    return $result;
}

function simple_hotel_crm_square_get_action_status( $action_id ) {
    $result = simple_hotel_crm_square_api_request( 'GET', '/v2/terminals/actions/' . $action_id );
    if ( is_wp_error( $result ) ) {
        return $result;
    }
    return isset( $result['action']['status'] ) ? $result['action']['status'] : 'UNKNOWN';
}

function simple_hotel_crm_square_cancel_checkout( $checkout_id ) {
    return simple_hotel_crm_square_api_request( 'POST', '/v2/terminals/checkouts/' . $checkout_id . '/cancel' );
}

function simple_hotel_crm_square_handle_payment_complete( $booking_id, $checkout_id, $payment_id = '' ) {
    global $wpdb;
    $bookings_table = simple_hotel_crm_bookings_table();

    if ( empty( $payment_id ) ) {
        $details = simple_hotel_crm_square_api_request( 'GET', '/v2/terminals/checkouts/' . $checkout_id );
        if ( ! is_wp_error( $details ) && isset( $details['checkout']['payment_ids'][0] ) ) {
            $payment_id = $details['checkout']['payment_ids'][0];
        }
    }

    $wpdb->update(
        $bookings_table,
        [ 'payment_status' => 'paid', 'payment_method' => 'square_terminal', 'square_checkout_id' => $checkout_id ],
        [ 'id' => $booking_id ],
        [ '%s', '%s', '%s' ],
        [ '%d' ]
    );

    update_post_meta( $booking_id, '_square_checkout_status', 'completed' );
    if ( ! empty( $payment_id ) ) {
        update_post_meta( $booking_id, '_square_payment_id', $payment_id );
    }

    $invoice = simple_hotel_crm_create_invoice_ninja_invoice( $booking_id );
    if ( is_wp_error( $invoice ) ) {
        return $invoice;
    }

    return true;
}

function simple_hotel_crm_square_refund_payment( $booking_id, $amount = null ) {
    $payment_id = get_post_meta( $booking_id, '_square_payment_id', true );
    if ( empty( $payment_id ) ) {
        return new WP_Error( 'square_no_payment', 'No Square payment found for this booking.' );
    }

    $amount_cents = null;
    if ( null !== $amount && (float) $amount > 0 ) {
        $amount_cents = round( (float) $amount * 100 );
    }

    $body = [
        'idempotency_key' => 'refund-' . $booking_id . '-' . time(),
        'payment_id' => $payment_id,
    ];

    if ( null !== $amount_cents ) {
        $body['amount_money'] = [
            'amount' => $amount_cents,
            'currency' => 'EUR',
        ];
    }

    $result = simple_hotel_crm_square_api_request( 'POST', '/v2/refunds', $body );

    if ( is_wp_error( $result ) ) {
        return $result;
    }

    global $wpdb;
    $wpdb->update(
        simple_hotel_crm_bookings_table(),
        [ 'payment_status' => 'refunded' ],
        [ 'id' => $booking_id ],
        [ '%s' ],
        [ '%d' ]
    );

    update_post_meta( $booking_id, '_square_checkout_status', 'refunded' );

    return $result;
}

function simple_hotel_crm_square_get_payment_status_label( $status ) {
    $labels = [
        'pending' => __( 'Awaiting terminal', 'simple-hotel-crm' ),
        'in_progress' => __( 'Payment in progress', 'simple-hotel-crm' ),
        'completed' => __( 'Paid', 'simple-hotel-crm' ),
        'canceled' => __( 'Cancelled', 'simple-hotel-crm' ),
        'failed' => __( 'Failed', 'simple-hotel-crm' ),
        'refunded' => __( 'Refunded', 'simple-hotel-crm' ),
    ];
    return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
}

function simple_hotel_crm_square_generate_device_code() {
    $location_id = simple_hotel_crm_square_get_location_id();
    if ( empty( $location_id ) ) {
        return new WP_Error( 'square_no_location', 'Location ID not configured.' );
    }

    $idempotency_key = 'lgf-pair-' . time() . '-' . wp_rand( 1000, 9999 );
    $body = [
        'idempotency_key' => $idempotency_key,
        'device_code' => [
            'name' => 'LGF Bookings Terminal',
            'product_type' => 'TERMINAL_API',
            'location_id' => $location_id,
        ],
    ];

    $result = simple_hotel_crm_square_api_request( 'POST', '/v2/devices/codes', $body );
    if ( is_wp_error( $result ) ) {
        return $result;
    }

    return $result;
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
