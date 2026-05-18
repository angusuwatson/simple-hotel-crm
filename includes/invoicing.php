<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function simple_hotel_crm_invoice_ninja_api_request( $method, $endpoint, $body = null ) {
    $api_url = get_option( 'simple_hotel_crm_invoice_ninja_url', '' );
    $api_token = get_option( 'simple_hotel_crm_invoice_ninja_token', '' );
    if ( empty( $api_url ) || empty( $api_token ) ) {
        return new WP_Error( 'invoice_ninja_not_configured', 'Invoice Ninja API not configured.' );
    }
    $args = [
        'method' => strtoupper( $method ),
        'headers' => [
            'X-API-Token' => $api_token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ],
        'timeout' => 15,
    ];
    if ( null !== $body ) {
        $args['body'] = wp_json_encode( $body );
    }
    $url = trailingslashit( $api_url ) . 'api/v1/' . ltrim( $endpoint, '/' );
    $response = wp_remote_request( $url, $args );
    if ( is_wp_error( $response ) ) {
        return $response;
    }
    $code = wp_remote_retrieve_response_code( $response );
    $response_body = wp_remote_retrieve_body( $response );
    $data = json_decode( $response_body, true );
    if ( $code >= 400 ) {
        return new WP_Error( 'invoice_ninja_api_error', sprintf( 'Invoice Ninja API error %d: %s', $code, substr( $response_body, 0, 500 ) ) );
    }
    return $data;
}

function simple_hotel_crm_find_or_create_invoice_ninja_client( $guest ) {
    if ( ! empty( $guest['invoice_ninja_client_id'] ) ) {
        $existing = simple_hotel_crm_invoice_ninja_api_request( 'GET', 'clients/' . rawurlencode( (string) $guest['invoice_ninja_client_id'] ) );
        if ( ! is_wp_error( $existing ) && ! empty( $existing['data'] ) ) {
            return (string) $guest['invoice_ninja_client_id'];
        }
    }
    if ( ! empty( $guest['email'] ) ) {
        $search = simple_hotel_crm_invoice_ninja_api_request( 'GET', 'clients?email=' . rawurlencode( $guest['email'] ) );
        if ( ! is_wp_error( $search ) && ! empty( $search['data'] ) && is_array( $search['data'] ) ) {
            $match = $search['data'][0];
            $client_id = (string) ( $match['id'] ?? '' );
            if ( '' !== $client_id ) {
                global $wpdb;
                $wpdb->update( simple_hotel_crm_guests_table(), [ 'invoice_ninja_client_id' => $client_id ], [ 'id' => $guest['id'] ], [ '%s' ], [ '%d' ] );
                return $client_id;
            }
        }
    }
    $name = trim( ( $guest['first_name'] ?? '' ) . ' ' . ( $guest['last_name'] ?? '' ) );
    if ( '' === $name ) {
        $name = $guest['email'] ?? $guest['phone'] ?? 'Guest #' . $guest['id'];
    }
    $contacts = [
        'first_name' => $guest['first_name'] ?? '',
        'last_name' => $guest['last_name'] ?? '',
    ];
    if ( ! empty( $guest['email'] ) ) {
        $contacts['email'] = $guest['email'];
    }
    if ( ! empty( $guest['phone'] ) ) {
        $contacts['phone'] = $guest['phone'];
    }
    $payload = [
        'name' => $name,
        'contacts' => [ $contacts ],
    ];
    $result = simple_hotel_crm_invoice_ninja_api_request( 'POST', 'clients', $payload );
    if ( is_wp_error( $result ) ) {
        return $result;
    }
    $client_id = (string) ( $result['data']['id'] ?? '' );
    if ( '' === $client_id ) {
        return new WP_Error( 'invoice_ninja_no_client_id', 'Client was created but no ID was returned.' );
    }
    global $wpdb;
    $wpdb->update( simple_hotel_crm_guests_table(), [ 'invoice_ninja_client_id' => $client_id ], [ 'id' => $guest['id'] ], [ '%s' ], [ '%d' ] );
    return $client_id;
}

function simple_hotel_crm_find_invoice_ninja_product_by_key( $product_key ) {
    $result = simple_hotel_crm_invoice_ninja_api_request( 'GET', 'products?product_key=' . rawurlencode( $product_key ) );
    if ( is_wp_error( $result ) || empty( $result['data'] ) || ! is_array( $result['data'] ) ) {
        return null;
    }
    foreach ( $result['data'] as $product ) {
        if ( ( $product['product_key'] ?? '' ) === $product_key && empty( $product['archived_at'] ) && empty( $product['is_deleted'] ) ) {
            return $product;
        }
    }
    return null;
}

function simple_hotel_crm_find_invoice_ninja_product_by_name( $name ) {
    static $cache = null;
    if ( null === $cache ) {
        $cache = [];
        $page = 1;
        while ( true ) {
            $result = simple_hotel_crm_invoice_ninja_api_request( 'GET', 'products?per_page=100&page=' . $page );
            if ( is_wp_error( $result ) || empty( $result['data'] ) || ! is_array( $result['data'] ) ) {
                break;
            }
            foreach ( $result['data'] as $product ) {
                if ( ! empty( $product['product_key'] ) && empty( $product['archived_at'] ) && empty( $product['is_deleted'] ) ) {
                    $cache[ $product['product_key'] ] = $product;
                }
            }
            if ( count( $result['data'] ) < 100 ) {
                break;
            }
            $page++;
        }
    }
    return $cache[ $name ] ?? null;
}

function simple_hotel_crm_create_invoice_ninja_invoice( $booking_id ) {
    global $wpdb;
    $bookings_table = simple_hotel_crm_bookings_table();
    $guests_table = simple_hotel_crm_guests_table();
    $booking_rooms_table = simple_hotel_crm_booking_rooms_table();
    $rooms_table = simple_hotel_crm_rooms_table();
    $booking = $wpdb->get_row( $wpdb->prepare( "SELECT b.*, g.first_name, g.last_name, g.email, g.phone, g.invoice_ninja_client_id AS guest_client_id FROM {$bookings_table} b LEFT JOIN {$guests_table} g ON g.id = b.guest_id WHERE b.id = %d LIMIT 1", $booking_id ), ARRAY_A );
    if ( empty( $booking ) ) {
        return new WP_Error( 'booking_not_found', 'Booking not found.' );
    }
    if ( ! empty( $booking['invoice_ninja_invoice_id'] ) ) {
        return new WP_Error( 'already_invoiced', 'This booking already has an Invoice Ninja invoice.' );
    }
    $guest = [
        'id' => $booking['guest_id'],
        'first_name' => $booking['first_name'],
        'last_name' => $booking['last_name'],
        'email' => $booking['email'],
        'phone' => $booking['phone'],
        'invoice_ninja_client_id' => $booking['guest_client_id'],
    ];
    $client_id = simple_hotel_crm_find_or_create_invoice_ninja_client( $guest );
    if ( is_wp_error( $client_id ) ) {
        return $client_id;
    }
    $booking_rooms = $wpdb->get_results( $wpdb->prepare( "SELECT br.*, r.room_name, r.invoice_ninja_product_key, r.invoice_ninja_product_id FROM {$booking_rooms_table} br LEFT JOIN {$rooms_table} r ON r.id = br.room_id WHERE br.booking_id = %d ORDER BY br.id ASC", $booking_id ), ARRAY_A );
    $line_items = [];
    $product_cache = [];
    foreach ( $booking_rooms as $room ) {
        $product_id = null;
        $product_key_display = 'room-charge';
        if ( ! empty( $room['invoice_ninja_product_key'] ) ) {
            if ( false !== strpos( (string) $room['invoice_ninja_product_key'], '%d' ) ) {
                $resolved_key = sprintf( (string) $room['invoice_ninja_product_key'], (int) $room['occupancy_adults'] );
            } else {
                $resolved_key = (string) $room['invoice_ninja_product_key'];
            }
            if ( ! isset( $product_cache[ $resolved_key ] ) ) {
                $found = simple_hotel_crm_find_invoice_ninja_product_by_key( $resolved_key );
                $product_cache[ $resolved_key ] = $found;
            }
            $found = $product_cache[ $resolved_key ];
            if ( $found ) {
                $product_id = (int) ( $found['id'] ?? 0 );
                $product_key_display = (string) ( $found['product_key'] ?? $resolved_key );
            } else {
                $found = simple_hotel_crm_find_invoice_ninja_product_by_name( (string) $room['room_name'] );
                if ( $found ) {
                    $product_id = (int) ( $found['id'] ?? 0 );
                    $product_key_display = (string) ( $found['product_key'] ?? $room['room_name'] );
                } else {
                    $product_key_display = $resolved_key;
                }
            }
        } elseif ( ! empty( $room['invoice_ninja_product_id'] ) ) {
            $product_id = (int) $room['invoice_ninja_product_id'];
        } elseif ( ! empty( $room['room_name'] ) ) {
            if ( ! isset( $product_cache[ $room['room_name'] ] ) ) {
                $found = simple_hotel_crm_find_invoice_ninja_product_by_name( (string) $room['room_name'] );
                $product_cache[ $room['room_name'] ] = $found;
            }
            $found = $product_cache[ $room['room_name'] ];
            if ( $found ) {
                $product_id = (int) ( $found['id'] ?? 0 );
                $product_key_display = (string) ( $found['product_key'] ?? $room['room_name'] );
            } else {
                $product_key_display = (string) $room['room_name'];
            }
        }
        $rate = (float) $room['room_rate_amount'];
        if ( $rate > 0 ) {
            $item = [
                'quantity' => 1,
                'cost' => round( $rate, 2 ),
            ];
            if ( $product_id ) {
                $item['product_id'] = $product_id;
            }
            $item['product_key'] = $product_key_display;
            $line_items[] = $item;
        }
        $extras = (float) $room['extras_amount'];
        if ( $extras > 0 ) {
            $line_items[] = [
                'product_key' => 'extras-charge',
                'quantity' => 1,
                'cost' => round( $extras, 2 ),
            ];
        }
    }
    if ( empty( $line_items ) ) {
        return new WP_Error( 'empty_invoice', 'No invoiceable line items found for this booking.' );
    }
    $total_amount = array_sum( array_column( $line_items, 'cost' ) );
    $taxe_sejour_rate = (float) get_option( 'simple_hotel_crm_taxe_sejour_rate', 0.80 );
    $check_in = strtotime( (string) $booking['check_in_date'] );
    $check_out = strtotime( (string) $booking['check_out_date'] );
    $nights = 0;
    if ( $check_in && $check_out && $check_out > $check_in ) {
        $nights = (int) round( ( $check_out - $check_in ) / 86400 );
    }
    $stored_tax = (float) ( $booking['tourist_tax_amount'] ?? 0 );
    $recalc_tax = 0.0;
    if ( $nights > 0 && $taxe_sejour_rate > 0 && (int) ( $booking['adults'] ?? 0 ) > 0 ) {
        $recalc_tax = round( (int) $booking['adults'] * $taxe_sejour_rate * $nights, 2 );
    }
    $taxe_sejour = $recalc_tax > 0 ? $recalc_tax : $stored_tax;
    $payload = [
        'client_id' => $client_id,
        'line_items' => $line_items,
        'amount' => round( $total_amount, 2 ),
        'is_amount_discount' => false,
        'discount' => 0,
    ];
    if ( $taxe_sejour > 0 ) {
        $payload['custom_surcharge1'] = round( $taxe_sejour, 2 );
        $payload['custom_surcharge_tax1'] = true;
    }
    $result = simple_hotel_crm_invoice_ninja_api_request( 'POST', 'invoices', $payload );
    if ( is_wp_error( $result ) ) {
        return $result;
    }
    $invoice_id = (string) ( $result['data']['id'] ?? '' );
    if ( '' === $invoice_id ) {
        return new WP_Error( 'invoice_ninja_no_invoice_id', 'Invoice was created but no ID was returned.' );
    }
    $wpdb->update(
        $bookings_table,
        [
            'invoice_ninja_client_id' => $client_id,
            'invoice_ninja_invoice_id' => $invoice_id,
            'invoiced_at' => current_time( 'mysql' ),
        ],
        [ 'id' => $booking_id ],
        [ '%s', '%s', '%s' ],
        [ '%d' ]
    );
    return [
        'success' => true,
        'invoice_id' => $invoice_id,
        'invoice_number' => $result['data']['invoice_number'] ?? '',
        'amount' => $result['data']['amount'] ?? $total_amount,
    ];
}

function simple_hotel_crm_sync_past_bookings_to_invoice_ninja() {
    global $wpdb;
    $bookings_table = simple_hotel_crm_bookings_table();
    $results = [];
    $bookings = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id FROM {$bookings_table} WHERE is_deleted = 0 AND invoice_ninja_invoice_id IS NULL AND status_code IN ( 'checked-in', 'checked-out', 'confirmed' ) ORDER BY check_in_date ASC"
        ),
        ARRAY_A
    );
    if ( empty( $bookings ) ) {
        return [ 'success' => true, 'message' => 'No uninvoiced past bookings found.', 'total' => 0 ];
    }
    $invoiced = 0;
    $errors = [];
    foreach ( $bookings as $row ) {
        $invoice = simple_hotel_crm_create_invoice_ninja_invoice( (int) $row['id'] );
        if ( is_wp_error( $invoice ) ) {
            $errors[] = sprintf( 'Booking #%d: %s', $row['id'], $invoice->get_error_message() );
        } else {
            $invoiced++;
            $results[] = $invoice;
        }
    }
    return [
        'success' => true,
        'total' => count( $bookings ),
        'invoiced' => $invoiced,
        'errors' => $errors,
        'results' => $results,
    ];
}

function simple_hotel_crm_rest_create_invoice( WP_REST_Request $request ) {
    $booking_id = absint( $request->get_param( 'booking_id' ) );
    if ( $booking_id <= 0 ) {
        return new WP_Error( 'invalid_booking', __( 'Invalid booking ID.', 'simple-hotel-crm' ), [ 'status' => 400 ] );
    }
    $result = simple_hotel_crm_create_invoice_ninja_invoice( $booking_id );
    if ( is_wp_error( $result ) ) {
        $code = $result->get_error_code();
        $http_code = 'invoice_ninja_not_configured' === $code ? 500 : ( 'already_invoiced' === $code ? 400 : ( 'booking_not_found' === $code ? 404 : 500 ) );
        return new WP_Error( $code, $result->get_error_message(), [ 'status' => $http_code ] );
    }
    return rest_ensure_response( $result );
}
