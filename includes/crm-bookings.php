<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function simple_hotel_crm_get_sync_room_options( $check_in = '', $check_out = '' ) {
    global $wpdb;
    $rooms_table = simple_hotel_crm_rooms_table();
    $bookings_table = simple_hotel_crm_bookings_table();
    $booking_rooms_table = simple_hotel_crm_booking_rooms_table();

    if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $check_in ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $check_out ) && $check_out > $check_in ) {
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.id, r.external_room_id, r.room_code, r.room_name
                 FROM {$rooms_table} r
                 WHERE r.active = 1
                   AND NOT EXISTS (
                       SELECT 1 FROM {$booking_rooms_table} br
                       JOIN {$bookings_table} b ON b.id = br.booking_id
                       WHERE br.room_id = r.id
                         AND b.status_code IN ('pending', 'confirmed', 'checked_in')
                         AND b.check_in_date < %s
                         AND b.check_out_date > %s
                   )
                 ORDER BY r.sort_order ASC, r.room_name ASC",
                $check_out,
                $check_in
            ),
            ARRAY_A
        );
    }

    return $wpdb->get_results( "SELECT id, external_room_id, room_code, room_name FROM {$rooms_table} WHERE active = 1 ORDER BY sort_order ASC, room_name ASC", ARRAY_A );
}

function simple_hotel_crm_get_room_options( $check_in = '', $check_out = '' ) {
    return simple_hotel_crm_get_sync_room_options( $check_in, $check_out );
}

function simple_hotel_crm_get_booking_status_options() {
    return [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'checked_in' => 'Checked in',
        'checked_out' => 'Checked out',
        'cancelled' => 'Cancelled',
        'no_show' => 'No show',
    ];
}

function simple_hotel_crm_get_booking_channel_options() {
    return [
        'direct' => 'Direct',
        'website' => 'Website',
        'booking_com' => 'Booking.com',
    ];
}

function simple_hotel_crm_distribute_amounts( $total, $count ) {
    $count = max( 1, (int) $count );
    $total_cents = (int) round( (float) $total * 100 );
    $base = intdiv( $total_cents, $count );
    $remainder = $total_cents % $count;
    $values = [];
    for ( $i = 0; $i < $count; $i++ ) {
        $values[] = ( $base + ( $i < $remainder ? 1 : 0 ) ) / 100;
    }
    return $values;
}

function simple_hotel_crm_get_room_pricing_for_occupancy( $room_id, $occupancy_adults ) {
    global $wpdb;

    $room_id = absint( $room_id );
    $occupancy_adults = max( 0, (int) $occupancy_adults );
    if ( $room_id <= 0 || $occupancy_adults <= 0 ) {
        return null;
    }

    $pricing_table = simple_hotel_crm_room_pricing_table();
    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id, room_id, occupancy_adults, price_amount
             FROM {$pricing_table}
             WHERE room_id = %d AND occupancy_adults = %d AND active = 1
             LIMIT 1",
            $room_id,
            $occupancy_adults
        ),
        ARRAY_A
    );

    return is_array( $row ) ? $row : null;
}

function simple_hotel_crm_calculate_room_pricing( array $line, int $nights, int $crm_room_id = 0, string $source_channel = 'direct' ) {
    $nights = max( 1, $nights );
    $adults = max( 0, (int) ( $line['adults'] ?? 0 ) );
    $children = max( 0, (int) ( $line['children'] ?? 0 ) );
    $babies = max( 0, (int) ( $line['babies'] ?? 0 ) );
    $guest_count = $adults + $children + $babies;
    $occupancy_adults = $adults + $children;
    $pricing_row = $crm_room_id > 0 ? simple_hotel_crm_get_room_pricing_for_occupancy( $crm_room_id, $occupancy_adults ) : null;

    $manual_room_rate_amount = max( 0, (float) simple_hotel_crm_normalize_decimal( $line['room_rate_amount'] ?? 0 ) );
    $extras_amount = max( 0, (float) simple_hotel_crm_normalize_decimal( $line['extras_amount'] ?? 0 ) );
    $discount_type = sanitize_key( (string) ( $line['discount_type'] ?? 'none' ) );
    if ( ! in_array( $discount_type, [ 'none', 'percent', 'amount' ], true ) ) {
        $discount_type = 'none';
    }
    $discount_value = max( 0, (float) simple_hotel_crm_normalize_decimal( $line['discount_value'] ?? 0 ) );

    $base_price_amount = $pricing_row
        ? round( (float) $pricing_row['price_amount'] * $nights, 2 )
        : $manual_room_rate_amount;

    if ( 'percent' === $discount_type ) {
        $discount_value = min( 100, $discount_value );
        $discount_amount = round( $base_price_amount * ( $discount_value / 100 ), 2 );
    } elseif ( 'amount' === $discount_type ) {
        $discount_amount = min( $base_price_amount, round( $discount_value, 2 ) );
    } else {
        $discount_amount = 0.00;
        $discount_value = 0.00;
    }

    $subtotal_amount = max( 0, round( $base_price_amount - $discount_amount, 2 ) );
    $commission_amount = simple_hotel_crm_calculate_channel_commission( $source_channel, $subtotal_amount );
    $tourist_tax_total = round( $adults * 0.80 * $nights, 2 );
    $room_rate_amount = $subtotal_amount;
    $total_amount = round( $room_rate_amount + $extras_amount + $tourist_tax_total, 2 );

    return [
        'pricing_room_id' => $pricing_row ? (int) $pricing_row['id'] : 0,
        'occupancy_adults' => $occupancy_adults,
        'adults' => $adults,
        'children' => $children,
        'babies' => $babies,
        'guest_count' => $guest_count,
        'base_price_amount' => $base_price_amount,
        'discount_type' => $discount_type,
        'discount_value' => $discount_value,
        'discount_amount' => $discount_amount,
        'subtotal_amount' => $subtotal_amount,
        'commission_amount' => $commission_amount,
        'room_rate_amount' => $room_rate_amount,
        'extras_amount' => $extras_amount,
        'tourist_tax_total' => $tourist_tax_total,
        'total_amount' => $total_amount,
    ];
}

function simple_hotel_crm_validate_booking_form_data( $data ) {
    $guest_name = trim( (string) ( $data['guest_name'] ?? '' ) );
    if ( '' === $guest_name ) {
        return new WP_Error( 'missing_guest_name', __( 'Guest name is required.', 'simple-hotel-crm' ) );
    }

    $source_channel = sanitize_text_field( (string) ( $data['source_channel'] ?? 'direct' ) );
    if ( ! array_key_exists( $source_channel, simple_hotel_crm_get_booking_channel_options() ) ) {
        return new WP_Error( 'invalid_source_channel', __( 'Please select a valid booking channel.', 'simple-hotel-crm' ) );
    }

    $status_code = sanitize_text_field( (string) ( $data['status_code'] ?? 'confirmed' ) );
    if ( ! array_key_exists( $status_code, simple_hotel_crm_get_booking_status_options() ) ) {
        return new WP_Error( 'invalid_status_code', __( 'Please select a valid booking status.', 'simple-hotel-crm' ) );
    }

    $check_in = (string) ( $data['check_in'] ?? '' );
    $check_out = (string) ( $data['check_out'] ?? '' );
    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $check_in ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $check_out ) || $check_out <= $check_in ) {
        return new WP_Error( 'invalid_dates', __( 'Check-in and check-out dates are invalid.', 'simple-hotel-crm' ) );
    }

    $nights = max( 1, (int) round( ( strtotime( $check_out ) - strtotime( $check_in ) ) / DAY_IN_SECONDS ) );
    $contacted_date = isset( $data['contacted_date'] ) ? (string) $data['contacted_date'] : '';
    if ( '' !== $contacted_date && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $contacted_date ) ) {
        return new WP_Error( 'invalid_contacted_date', __( 'Contacted date is invalid.', 'simple-hotel-crm' ) );
    }

    $room_lines = isset( $data['room_lines'] ) && is_array( $data['room_lines'] ) ? $data['room_lines'] : [];
    if ( empty( $room_lines ) ) {
        return new WP_Error( 'missing_rooms', __( 'Add at least one room.', 'simple-hotel-crm' ) );
    }

    $validated_lines = [];
    $selected_rooms = [];
    foreach ( $room_lines as $index => $line ) {
        $room_sync_id = absint( $line['room_sync_id'] ?? 0 );
        if ( $room_sync_id <= 0 ) {
            continue;
        }
        if ( in_array( $room_sync_id, $selected_rooms, true ) ) {
            return new WP_Error( 'duplicate_room', __( 'The same room cannot be selected twice in one booking.', 'simple-hotel-crm' ) );
        }
        $selected_rooms[] = $room_sync_id;
        $adults = max( 0, (int) ( $line['adults'] ?? 0 ) );
        $children = max( 0, (int) ( $line['children'] ?? 0 ) );
        $babies = max( 0, (int) ( $line['babies'] ?? 0 ) );
        $guest_count = $adults + $children + $babies;
        if ( $guest_count <= 0 ) {
            return new WP_Error( 'invalid_guest_count', __( 'Each room line must have at least one guest.', 'simple-hotel-crm' ) );
        }
        $discount_type = sanitize_key( (string) ( $line['discount_type'] ?? 'none' ) );
        if ( ! in_array( $discount_type, [ 'none', 'percent', 'amount' ], true ) ) {
            return new WP_Error( 'invalid_discount_type', __( 'Please select a valid discount type.', 'simple-hotel-crm' ) );
        }
        $discount_value = max( 0, (float) simple_hotel_crm_normalize_decimal( $line['discount_value'] ?? 0 ) );
        if ( 'percent' === $discount_type && $discount_value > 100 ) {
            return new WP_Error( 'invalid_discount_value', __( 'Percent discount cannot exceed 100.', 'simple-hotel-crm' ) );
        }
        $validated_lines[] = [
            'room_sync_id' => $room_sync_id,
            'adults' => $adults,
            'children' => $children,
            'babies' => $babies,
            'guest_count' => $guest_count,
            'room_rate_amount' => max( 0, (float) simple_hotel_crm_normalize_decimal( $line['room_rate_amount'] ?? 0 ) ),
            'extras_amount' => max( 0, (float) simple_hotel_crm_normalize_decimal( $line['extras_amount'] ?? 0 ) ),
            'discount_type' => $discount_type,
            'discount_value' => $discount_value,
        ];
    }
    if ( empty( $validated_lines ) ) {
        return new WP_Error( 'missing_rooms', __( 'Select at least one room.', 'simple-hotel-crm' ) );
    }

    return [
        'guest_name' => $guest_name,
        'check_in' => $check_in,
        'check_out' => $check_out,
        'nights' => $nights,
        'contacted_date' => $contacted_date,
        'room_lines' => $validated_lines,
    ];
}

function simple_hotel_crm_check_wp_sync_room_availability( $room_sync_id, $check_in, $check_out ) {
    global $wpdb;

    $bookings_table = simple_hotel_crm_bookings_table();
    $booking_rooms_table = simple_hotel_crm_booking_rooms_table();
    $crm_room_id = simple_hotel_crm_find_crm_room_id( $room_sync_id );

    if ( $crm_room_id <= 0 ) {
        return true;
    }

    $conflict_count = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$booking_rooms_table} br
             JOIN {$bookings_table} b ON b.id = br.booking_id
             WHERE br.room_id = %d
               AND b.is_deleted = 0
               AND b.status_code IN ('pending', 'confirmed', 'checked_in')
               AND b.check_in_date < %s
               AND b.check_out_date > %s",
            $crm_room_id,
            $check_out,
            $check_in
        )
    );

    if ( $conflict_count > 0 ) {
        return new WP_Error( 'room_unavailable', __( 'That room already has an overlapping booking for the selected dates.', 'simple-hotel-crm' ) );
    }

    return true;
}

function simple_hotel_crm_create_wp_crm_booking( $data ) {
    global $wpdb;

    simple_hotel_crm_seed_rooms_table();

    $bookings_table = simple_hotel_crm_sync_bookings_table();
    $crm_rooms_table = simple_hotel_crm_rooms_table();
    $crm_guests_table = simple_hotel_crm_guests_table();
    $crm_bookings_table = simple_hotel_crm_bookings_table();
    $crm_booking_rooms_table = simple_hotel_crm_booking_rooms_table();
    $crm_booking_nights_table = simple_hotel_crm_booking_room_nights_table();

    $validated = simple_hotel_crm_validate_booking_form_data( $data );
    if ( is_wp_error( $validated ) ) {
        return $validated;
    }

    extract( $validated, EXTR_SKIP );

    $source_channel = sanitize_text_field( (string) ( $data['source_channel'] ?? 'direct' ) );
    $status_code = sanitize_text_field( (string) ( $data['status_code'] ?? 'confirmed' ) );
    $phone = sanitize_text_field( (string) ( $data['phone'] ?? '' ) );
    $email = sanitize_email( (string) ( $data['email'] ?? '' ) );
    $address_line_1 = sanitize_text_field( (string) ( $data['address_line_1'] ?? '' ) );
    $address_line_2 = sanitize_text_field( (string) ( $data['address_line_2'] ?? '' ) );
    $city = sanitize_text_field( (string) ( $data['city'] ?? '' ) );
    $postcode = sanitize_text_field( (string) ( $data['postcode'] ?? '' ) );
    $country = sanitize_text_field( (string) ( $data['country'] ?? '' ) );
    $guest_notes = sanitize_textarea_field( (string) ( $data['guest_notes'] ?? '' ) );
    $booking_note = sanitize_textarea_field( (string) ( $data['booking_note'] ?? '' ) );
    $import_notes = sanitize_textarea_field( (string) ( $data['import_notes'] ?? '' ) );
    $source_created_at = ( '' !== $contacted_date ? $contacted_date : current_time( 'mysql' ) );

    $external_booking_id = (int) $wpdb->get_var( "SELECT COALESCE(MAX(external_booking_id), 0) + 1 FROM {$bookings_table}" );
    $next_booking_room_id = (int) $wpdb->get_var( "SELECT COALESCE(MAX(external_booking_room_id), 0) + 1 FROM {$bookings_table}" );
    $calculated_room_lines = [];

    foreach ( $room_lines as $line ) {
        $availability = simple_hotel_crm_check_wp_sync_room_availability( $line['room_sync_id'], $check_in, $check_out );
        if ( is_wp_error( $availability ) ) {
            return $availability;
        }

        $crm_room_id = simple_hotel_crm_find_crm_room_id( $line['room_sync_id'] ?? 0 );
        $room = $crm_room_id > 0 ? $wpdb->get_row( $wpdb->prepare( "SELECT id, sync_room_id, external_room_id, room_code, room_name FROM {$crm_rooms_table} WHERE id = %d LIMIT 1", $crm_room_id ), ARRAY_A ) : null;
        if ( ! $room || $crm_room_id <= 0 ) {
            return new WP_Error( 'invalid_room', __( 'Please select a valid room.', 'simple-hotel-crm' ) );
        }

        $calculated_room_lines[] = array_merge(
            $line,
            simple_hotel_crm_calculate_room_pricing( $line, $nights, $crm_room_id, $source_channel ),
            [
                'crm_room_id' => $crm_room_id,
                'room' => $room,
            ]
        );
    }

    $booking_adults = array_sum( array_column( $calculated_room_lines, 'adults' ) );
    $booking_children = array_sum( array_column( $calculated_room_lines, 'children' ) );
    $booking_babies = array_sum( array_column( $calculated_room_lines, 'babies' ) );
    $booking_room_rate = round( array_sum( array_column( $calculated_room_lines, 'room_rate_amount' ) ), 2 );
    $booking_extras = round( array_sum( array_column( $calculated_room_lines, 'extras_amount' ) ), 2 );
    $booking_tax = round( array_sum( array_column( $calculated_room_lines, 'tourist_tax_total' ) ), 2 );
    $booking_total = round( array_sum( array_column( $calculated_room_lines, 'total_amount' ) ), 2 );

    list( $first_name, $last_name ) = simple_hotel_crm_split_guest_name( $guest_name );

    $wpdb->query( 'START TRANSACTION' );

    $guest_inserted = $wpdb->insert(
        $crm_guests_table,
        [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'phone' => $phone,
            'address_line_1' => $address_line_1,
            'address_line_2' => $address_line_2,
            'city' => $city,
            'postcode' => $postcode,
            'country' => $country,
            'notes' => $guest_notes,
        ],
        [ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
    );
    if ( false === $guest_inserted ) {
        $wpdb->query( 'ROLLBACK' );
        return new WP_Error( 'guest_insert_failed', __( 'Could not create the guest in WordPress CRM tables.', 'simple-hotel-crm' ) );
    }
    $guest_id = (int) $wpdb->insert_id;

    $booking_inserted = $wpdb->insert(
        $crm_bookings_table,
        [
            'guest_id' => $guest_id,
            'source_channel' => $source_channel,
            'source_booking_id' => '',
            'status_code' => $status_code,
            'contacted_date' => $contacted_date ?: null,
            'check_in_date' => $check_in,
            'check_out_date' => $check_out,
            'adults' => $booking_adults,
            'children' => $booking_children,
            'babies' => $booking_babies,
            'room_rate_amount' => $booking_room_rate,
            'extras_amount' => $booking_extras,
            'tourist_tax_amount' => $booking_tax,
            'total_amount' => $booking_total,
            'currency' => 'EUR',
            'booking_note' => $booking_note,
            'internal_notes' => $import_notes,
            'invoice_ninja_client_id' => '',
            'invoice_ninja_invoice_id' => '',
        ],
        [ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%f', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%s' ]
    );
    if ( false === $booking_inserted ) {
        $wpdb->query( 'ROLLBACK' );
        return new WP_Error( 'booking_insert_failed', __( 'Could not create the booking in WordPress CRM tables.', 'simple-hotel-crm' ) );
    }
    $booking_id = (int) $wpdb->insert_id;

    $last_booking_room_id = 0;
    foreach ( $calculated_room_lines as $line ) {
        $crm_room_id = (int) $line['crm_room_id'];
        $room = $line['room'];
        $external_booking_room_id = $next_booking_room_id++;
        $booking_room_inserted = $wpdb->insert(
            $crm_booking_rooms_table,
            [
                'booking_id' => $booking_id,
                'room_id' => $crm_room_id,
                'legacy_reserved_room_id' => $external_booking_room_id,
                'pricing_room_id' => $line['pricing_room_id'] > 0 ? $line['pricing_room_id'] : null,
                'occupancy_adults' => $line['occupancy_adults'],
                'guest_count' => $line['guest_count'],
                'adults' => $line['adults'],
                'children' => $line['children'],
                'babies' => $line['babies'],
                'base_price_amount' => $line['base_price_amount'],
                'discount_type' => $line['discount_type'],
                'discount_value' => $line['discount_value'],
                'discount_amount' => $line['discount_amount'],
                'subtotal_amount' => $line['subtotal_amount'],
                'commission_amount' => $line['commission_amount'],
                'room_rate_amount' => $line['room_rate_amount'],
                'extras_amount' => $line['extras_amount'],
                'tourist_tax_amount' => $line['tourist_tax_total'],
                'total_amount' => $line['total_amount'],
            ],
            [ '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%f', '%s', '%f', '%f', '%f', '%f', '%f', '%f', '%f', '%f' ]
        );
        if ( false === $booking_room_inserted ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'booking_room_insert_failed', __( 'Could not create a booking room in WordPress CRM tables.', 'simple-hotel-crm' ) );
        }

        $crm_booking_room_id = (int) $wpdb->insert_id;
        $last_booking_room_id = $crm_booking_room_id;
        $base_price_nightly = simple_hotel_crm_distribute_amounts( $line['base_price_amount'], $nights );
        $discount_nightly = simple_hotel_crm_distribute_amounts( $line['discount_amount'], $nights );
        $subtotal_nightly = simple_hotel_crm_distribute_amounts( $line['subtotal_amount'], $nights );
        $commission_nightly = simple_hotel_crm_distribute_amounts( $line['commission_amount'], $nights );
        $room_rate_nightly = simple_hotel_crm_distribute_amounts( $line['room_rate_amount'], $nights );
        $extras_nightly = simple_hotel_crm_distribute_amounts( $line['extras_amount'], $nights );
        $tax_nightly = simple_hotel_crm_distribute_amounts( $line['tourist_tax_total'], $nights );

        for ( $i = 0; $i < $nights; $i++ ) {
            $stay_date = gmdate( 'Y-m-d', strtotime( $check_in . ' +' . $i . ' day' ) );
            $night_total = round( $room_rate_nightly[ $i ] + $extras_nightly[ $i ] + $tax_nightly[ $i ], 2 );

            $night_inserted = $wpdb->insert(
                $crm_booking_nights_table,
                [
                    'booking_room_id' => $crm_booking_room_id,
                    'stay_date' => $stay_date,
                    'guest_count' => $line['guest_count'],
                    'adults' => $line['adults'],
                    'children' => $line['children'],
                    'babies' => $line['babies'],
                    'base_price_amount' => $base_price_nightly[ $i ],
                    'discount_amount' => $discount_nightly[ $i ],
                    'subtotal_amount' => $subtotal_nightly[ $i ],
                    'commission_amount' => $commission_nightly[ $i ],
                    'room_rate_amount' => $room_rate_nightly[ $i ],
                    'extras_amount' => $extras_nightly[ $i ],
                    'tourist_tax_amount' => $tax_nightly[ $i ],
                    'total_amount' => $night_total,
                ],
                [ '%d', '%s', '%d', '%d', '%d', '%d', '%f', '%f', '%f', '%f', '%f', '%f', '%f', '%f' ]
            );
            if ( false === $night_inserted ) {
                $wpdb->query( 'ROLLBACK' );
                return new WP_Error( 'booking_night_insert_failed', __( 'Could not create booking nights in WordPress CRM tables.', 'simple-hotel-crm' ) );
            }

            $sync_inserted = $wpdb->insert(
                $bookings_table,
                [
                    'external_booking_id' => $external_booking_id,
                    'external_booking_room_id' => $external_booking_room_id,
                    'external_room_id' => (int) $room['external_room_id'],
                    'room_sync_id' => (int) ( $room['sync_room_id'] ?? 0 ),
                    'status_code' => $status_code,
                    'check_in' => $check_in,
                    'check_out' => $check_out,
                    'stay_date' => $stay_date,
                    'guest_count' => $line['guest_count'],
                    'adults' => $line['adults'],
                    'children' => $line['children'],
                    'babies' => $line['babies'],
                    'total_amount' => $night_total,
                    'room_amount' => $room_rate_nightly[ $i ],
                    'extras_amount' => $extras_nightly[ $i ] > 0 ? $extras_nightly[ $i ] : null,
                    'tourist_tax_amount' => $tax_nightly[ $i ],
                    'room_count' => count( $room_lines ),
                    'source_channel' => $source_channel,
                    'source_booking_id' => '',
                    'channel_label' => simple_hotel_crm_get_booking_channel_options()[ $source_channel ] ?? $source_channel,
                    'guest_name' => $guest_name,
                    'phone' => $phone,
                    'import_notes' => $import_notes,
                    'invoice_ninja_client_id' => '',
                    'invoice_ninja_invoice_id' => '',
                    'source_created_at' => $source_created_at,
                ],
                [ '%d','%d','%d','%d','%s','%s','%s','%s','%d','%d','%d','%d','%f','%f','%f','%d','%s','%s','%s','%s','%s','%s','%s','%s','%s' ]
            );
            if ( false === $sync_inserted ) {
                $wpdb->query( 'ROLLBACK' );
                return new WP_Error( 'sync_booking_insert_failed', __( 'Could not create the compatibility sync booking rows.', 'simple-hotel-crm' ) );
            }
        }
    }

    $wpdb->query( 'COMMIT' );
    simple_hotel_crm_clear_calendar_cache();
    return [ 'booking_id' => $booking_id, 'booking_room_id' => $last_booking_room_id ];
}

function simple_hotel_crm_split_guest_name( $guest_name ) {
    $guest_name = trim( (string) $guest_name );
    if ( '' === $guest_name ) {
        return [ '', '' ];
    }
    $parts = preg_split( '/\s+/', $guest_name );
    $last_name = array_pop( $parts );
    $first_name = trim( implode( ' ', $parts ) );
    return [ '' !== $first_name ? $first_name : $last_name, '' !== $first_name ? $last_name : '' ];
}

function simple_hotel_crm_create_booking( $data ) {
    return simple_hotel_crm_create_wp_crm_booking( $data );
}

