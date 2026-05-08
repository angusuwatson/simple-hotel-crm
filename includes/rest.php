<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'rest_api_init', function() {
    register_rest_route( 'simple-hotel-crm/v1', '/table', [
        'methods'  => 'GET',
        'callback' => 'simple_hotel_crm_rest_table',
        'args'     => [
            'month' => [ 'validate_callback' => function( $param ) { return is_numeric( $param ) && $param >= 1 && $param <= 12; }, 'required' => true ],
            'year'  => [ 'validate_callback' => function( $param ) { return is_numeric( $param ) && $param >= 2000 && $param <= 2100; }, 'required' => true ],
        ],
        'permission_callback' => function() { return simple_hotel_crm_user_can_access(); },
    ] );

    register_rest_route( 'simple-hotel-crm/v1', '/daily-note', [
        'methods'  => 'POST',
        'callback' => 'simple_hotel_crm_rest_save_daily_note',
        'permission_callback' => function() { return simple_hotel_crm_user_can_access(); },
    ] );

    register_rest_route( 'simple-hotel-crm/v1', '/create-invoice', [
        'methods'  => 'POST',
        'callback' => 'simple_hotel_crm_rest_create_invoice',
        'permission_callback' => function() { return simple_hotel_crm_user_can_access(); },
        'args'     => [
            'booking_id' => [
                'validate_callback' => function( $param ) {
                    return is_numeric( $param ) && $param > 0;
                },
                'required' => true,
            ],
            'reserved_room_id' => [
                'validate_callback' => function( $param ) {
                    return is_numeric( $param ) && $param > 0;
                },
                'required' => true,
            ],
        ],
    ] );

    register_rest_route( 'simple-hotel-crm/v1', '/quick-booking', [
        'methods'  => 'GET',
        'callback' => 'simple_hotel_crm_rest_get_quick_booking',
        'permission_callback' => function() { return simple_hotel_crm_user_can_access(); },
        'args'     => [
            'booking_id' => [
                'validate_callback' => function( $param ) {
                    return is_numeric( $param ) && $param > 0;
                },
                'required' => true,
            ],
        ],
    ] );

    register_rest_route( 'simple-hotel-crm/v1', '/quick-booking', [
        'methods'  => 'POST',
        'callback' => 'simple_hotel_crm_rest_save_quick_booking',
        'permission_callback' => function() { return simple_hotel_crm_user_can_access(); },
        'args'     => [
            'booking_id' => [
                'validate_callback' => function( $param ) {
                    return is_numeric( $param ) && $param > 0;
                },
                'required' => true,
            ],
        ],
    ] );

    register_rest_route( 'simple-hotel-crm/v1', '/room-day-note', [
        'methods'  => 'POST',
        'callback' => 'simple_hotel_crm_rest_save_room_day_note',
        'permission_callback' => function() { return simple_hotel_crm_user_can_access(); },
    ] );

    register_rest_route( 'simple-hotel-crm/v1', '/room-day-extras', [
        'methods'  => 'POST',
        'callback' => 'simple_hotel_crm_rest_save_room_day_extras',
        'permission_callback' => function() { return simple_hotel_crm_user_can_access(); },
    ] );
} );

function simple_hotel_crm_rest_table( WP_REST_Request $request ) {
    $month = intval( $request->get_param( 'month' ) );
    $year  = intval( $request->get_param( 'year' ) );
    $context = 'admin' === $request->get_param( 'context' ) ? 'admin' : 'frontend';

    return rest_ensure_response( [ 'html' => simple_hotel_crm_render_calendar( simple_hotel_crm_get_calendar_data( $month, $year ), $context ) ] );
}

function simple_hotel_crm_rest_save_daily_note( WP_REST_Request $request ) {
    global $wpdb;

    $date = sanitize_text_field( (string) $request->get_param( 'date' ) );
    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
        return new WP_Error( 'invalid_date', __( 'Invalid note date.', 'simple-hotel-crm' ), [ 'status' => 400 ] );
    }

    $note = sanitize_textarea_field( (string) $request->get_param( 'note' ) );
    $table = simple_hotel_crm_daily_notes_table();

    if ( '' === trim( $note ) ) {
        $wpdb->delete( $table, [ 'note_date' => $date ], [ '%s' ] );
    } else {
        $wpdb->replace( $table, [ 'note_date' => $date, 'note_text' => $note ], [ '%s', '%s' ] );
    }

    simple_hotel_crm_clear_calendar_cache();

    return rest_ensure_response( [ 'success' => true, 'date' => $date, 'note' => $note ] );
}

function simple_hotel_crm_rest_save_room_day_note( WP_REST_Request $request ) {
    $booking_id = absint( $request->get_param( 'booking_id' ) );
    $booking_room_id = absint( $request->get_param( 'booking_room_id' ) );
    $stay_date = sanitize_text_field( (string) $request->get_param( 'stay_date' ) );
    $note = sanitize_textarea_field( (string) $request->get_param( 'note' ) );

    if ( $booking_id <= 0 || $booking_room_id <= 0 || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $stay_date ) ) {
        return new WP_Error( 'invalid_room_day_note', __( 'Invalid room-day note payload.', 'simple-hotel-crm' ), [ 'status' => 400 ] );
    }

    simple_hotel_crm_upsert_booking_note( $booking_id, $note, $booking_room_id, $stay_date, 'night' );
    simple_hotel_crm_clear_calendar_cache();

    return rest_ensure_response( [ 'success' => true ] );
}

function simple_hotel_crm_rest_save_room_day_extras( WP_REST_Request $request ) {
    global $wpdb;

    $booking_id = absint( $request->get_param( 'booking_id' ) );
    $booking_room_id = absint( $request->get_param( 'booking_room_id' ) );
    $stay_date = sanitize_text_field( (string) $request->get_param( 'stay_date' ) );
    $formula_raw = (string) $request->get_param( 'formula' );
    $amount_raw = (string) $request->get_param( 'amount' );
    $extras = simple_hotel_crm_evaluate_extras_formula( $formula_raw );
    $amount = '' !== trim( $amount_raw ) ? (float) simple_hotel_crm_normalize_decimal( $amount_raw ) : (float) $extras['total'];

    if ( $booking_id <= 0 || $booking_room_id <= 0 || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $stay_date ) ) {
        return new WP_Error( 'invalid_room_day_extras', __( 'Invalid room-day extras payload.', 'simple-hotel-crm' ), [ 'status' => 400 ] );
    }
    if ( ! $extras['valid'] && '' !== trim( $formula_raw ) ) {
        return new WP_Error( 'invalid_extras_formula', __( 'Extras formula only supports numbers joined with +.', 'simple-hotel-crm' ), [ 'status' => 400 ] );
    }

    $booking_nights_table = simple_hotel_crm_booking_room_nights_table();
    $night = $wpdb->get_row( $wpdb->prepare( "SELECT room_rate_amount, tourist_tax_amount FROM {$booking_nights_table} WHERE booking_room_id = %d AND stay_date = %s LIMIT 1", $booking_room_id, $stay_date ), ARRAY_A );
    if ( ! $night ) {
        return new WP_Error( 'booking_night_not_found', __( 'Booking night not found.', 'simple-hotel-crm' ), [ 'status' => 404 ] );
    }

    simple_hotel_crm_upsert_booking_adjustment( $booking_id, $booking_room_id, $stay_date, 'extras', $extras['valid'] ? $extras['formula'] : '', $amount );
    $total_amount = round( (float) $night['room_rate_amount'] + $amount + (float) $night['tourist_tax_amount'], 2 );
    $wpdb->update( $booking_nights_table, [ 'extras_amount' => $amount, 'total_amount' => $total_amount ], [ 'booking_room_id' => $booking_room_id, 'stay_date' => $stay_date ], [ '%f', '%f' ], [ '%d', '%s' ] );
    simple_hotel_crm_recalculate_booking_room_totals_from_nights( $booking_room_id );
    simple_hotel_crm_recalculate_booking_header_totals();
    simple_hotel_crm_clear_calendar_cache();

    return rest_ensure_response( [ 'success' => true ] );
}

function simple_hotel_crm_rest_get_quick_booking( WP_REST_Request $request ) {
    global $wpdb;

    $booking_id = absint( $request->get_param( 'booking_id' ) );
    $reserved_room_id = absint( $request->get_param( 'reserved_room_id' ) );
    $bookings_table = simple_hotel_crm_bookings_table();
    $guests_table = simple_hotel_crm_guests_table();
    $overlay = $reserved_room_id > 0 ? simple_hotel_crm_get_booking_overlay( $reserved_room_id ) : [];

    $booking = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT b.id, b.guest_id, b.status_code, b.source_channel, b.contacted_date, b.booking_note, b.internal_notes, g.first_name, g.last_name, g.phone, g.email
             FROM {$bookings_table} b
             JOIN {$guests_table} g ON g.id = b.guest_id
             WHERE b.id = %d AND b.is_deleted = 0
             LIMIT 1",
            $booking_id
        ),
        ARRAY_A
    );

    if ( ! $booking ) {
        return new WP_Error( 'booking_not_found', __( 'Booking not found.', 'simple-hotel-crm' ), [ 'status' => 404 ] );
    }

    $booking_room_id = 0;
    if ( $reserved_room_id > 0 ) {
        $booking_room_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM " . simple_hotel_crm_booking_rooms_table() . " WHERE legacy_reserved_room_id = %d LIMIT 1", $reserved_room_id ) );
    }
    $room_note = $booking_room_id > 0 ? simple_hotel_crm_get_booking_note_text( (int) $booking['id'], $booking_room_id ) : '';
    $booking_note_global = simple_hotel_crm_get_booking_note_text( (int) $booking['id'] );

    return rest_ensure_response( [
        'id' => (int) $booking['id'],
        'guest_name' => trim( (string) $booking['first_name'] . ' ' . (string) $booking['last_name'] ),
        'phone' => (string) $booking['phone'],
        'email' => (string) $booking['email'],
        'status_code' => (string) $booking['status_code'],
        'source_channel' => (string) $booking['source_channel'],
        'contacted_date' => (string) $booking['contacted_date'],
        'booking_note' => '' !== $room_note ? $room_note : (string) ( $overlay['booking_note'] ?? $booking_note_global ),
        'booking_note_global' => $booking_note_global,
        'extras_formula' => (string) ( $overlay['extras_formula'] ?? '' ),
        'internal_notes' => (string) $booking['internal_notes'],
        'status_options' => simple_hotel_crm_get_booking_status_options(),
        'channel_options' => simple_hotel_crm_get_booking_channel_options(),
        'detail_url' => admin_url( 'admin.php?page=simple-hotel-crm-booking-detail&booking_id=' . (int) $booking['id'] ),
        'guest_url' => admin_url( 'admin.php?page=simple-hotel-crm-guest-detail&guest_id=' . (int) $booking['guest_id'] ),
    ] );
}

function simple_hotel_crm_rest_save_quick_booking( WP_REST_Request $request ) {
    global $wpdb;

    $booking_id = absint( $request->get_param( 'booking_id' ) );
    $reserved_room_id = absint( $request->get_param( 'reserved_room_id' ) );
    $guest_name = trim( sanitize_text_field( (string) $request->get_param( 'guest_name' ) ) );
    $phone = sanitize_text_field( (string) $request->get_param( 'phone' ) );
    $email = sanitize_email( (string) $request->get_param( 'email' ) );
    $status_code = sanitize_text_field( (string) $request->get_param( 'status_code' ) );
    $has_contacted_date = null !== $request->get_param( 'contacted_date' );
    $contacted_date = sanitize_text_field( (string) $request->get_param( 'contacted_date' ) );
    $has_booking_note = null !== $request->get_param( 'booking_note' );
    $booking_note = sanitize_textarea_field( (string) $request->get_param( 'booking_note' ) );
    $extras_formula_raw = (string) $request->get_param( 'extras_formula' );
    $internal_notes = sanitize_textarea_field( (string) $request->get_param( 'internal_notes' ) );

    if ( '' === $guest_name ) {
        return new WP_Error( 'missing_guest_name', __( 'Guest name is required.', 'simple-hotel-crm' ), [ 'status' => 400 ] );
    }
    if ( ! isset( simple_hotel_crm_get_booking_status_options()[ $status_code ] ) ) {
        return new WP_Error( 'invalid_status_code', __( 'Please select a valid booking status.', 'simple-hotel-crm' ), [ 'status' => 400 ] );
    }
    if ( $has_contacted_date && '' !== $contacted_date && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $contacted_date ) ) {
        return new WP_Error( 'invalid_contacted_date', __( 'Contacted date is invalid.', 'simple-hotel-crm' ), [ 'status' => 400 ] );
    }

    $bookings_table = simple_hotel_crm_bookings_table();
    $guests_table = simple_hotel_crm_guests_table();
    $sync_bookings_table = simple_hotel_crm_sync_bookings_table();
    $overlay_table = simple_hotel_crm_booking_overlay_table();

    $booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$bookings_table} WHERE id = %d AND is_deleted = 0 LIMIT 1", $booking_id ), ARRAY_A );
    if ( ! $booking ) {
        return new WP_Error( 'booking_not_found', __( 'Booking not found.', 'simple-hotel-crm' ), [ 'status' => 404 ] );
    }

    list( $first_name, $last_name ) = simple_hotel_crm_split_guest_name( $guest_name );
    $wpdb->update( $guests_table, [
        'first_name' => $first_name,
        'last_name' => $last_name,
        'phone' => $phone,
        'email' => $email,
    ], [ 'id' => (int) $booking['guest_id'] ], [ '%s', '%s', '%s', '%s' ], [ '%d' ] );

    $booking_update = [
        'status_code' => $status_code,
        'internal_notes' => $internal_notes,
    ];
    $booking_update_formats = [ '%s', '%s' ];
    if ( $has_contacted_date ) {
        $booking_update['contacted_date'] = $contacted_date ?: null;
        $booking_update_formats[] = '%s';
    }
    $wpdb->update( $bookings_table, $booking_update, [ 'id' => $booking_id ], $booking_update_formats, [ '%d' ] );

    if ( ! empty( $booking['source_booking_id'] ) ) {
        $wpdb->update( $sync_bookings_table, [
            'status_code' => $status_code,
            'guest_name' => $guest_name,
            'phone' => $phone,
            'import_notes' => $internal_notes,
            'source_created_at' => ( $has_contacted_date ? $contacted_date : $booking['contacted_date'] ) ?: current_time( 'mysql' ),
        ], [ 'source_booking_id' => (string) $booking['source_booking_id'] ], [ '%s', '%s', '%s', '%s', '%s' ], [ '%s' ] );
    }

    if ( $reserved_room_id > 0 ) {
        $existing_overlay = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$overlay_table} WHERE reserved_room_id = %d", $reserved_room_id ), ARRAY_A );
        $extras = simple_hotel_crm_evaluate_extras_formula( $extras_formula_raw );
        if ( ! $extras['valid'] ) {
            return new WP_Error( 'invalid_extras_formula', __( 'Extras formula only supports numbers joined with +.', 'simple-hotel-crm' ), [ 'status' => 400 ] );
        }
        $booking_room_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM " . simple_hotel_crm_booking_rooms_table() . " WHERE legacy_reserved_room_id = %d LIMIT 1", $reserved_room_id ) );
        if ( $has_booking_note && $booking_room_id > 0 ) {
            simple_hotel_crm_upsert_booking_note( $booking_id, $booking_note, $booking_room_id, null, 'room' );
        }

        $overlay_payload = [
            'booking_id' => $booking_id,
            'reserved_room_id' => $reserved_room_id,
            'room_id' => isset( $existing_overlay['room_id'] ) ? (int) $existing_overlay['room_id'] : 0,
            'booking_note' => $booking_note,
            'extras_formula' => $extras['formula'],
            'extras_total' => $extras['total'],
            'manual_guest_name' => $guest_name,
            'manual_adults' => isset( $existing_overlay['manual_adults'] ) && '' !== (string) $existing_overlay['manual_adults'] ? (int) $existing_overlay['manual_adults'] : null,
            'manual_children' => isset( $existing_overlay['manual_children'] ) && '' !== (string) $existing_overlay['manual_children'] ? (int) $existing_overlay['manual_children'] : null,
            'manual_tarif' => isset( $existing_overlay['manual_tarif'] ) ? simple_hotel_crm_normalize_decimal( $existing_overlay['manual_tarif'] ) : null,
            'manual_commission' => isset( $existing_overlay['manual_commission'] ) ? simple_hotel_crm_normalize_decimal( $existing_overlay['manual_commission'] ) : null,
        ];
        $overlay_formats = [ '%d', '%d', '%d', '%s', '%s', '%f', '%s', '%d', '%d', '%f', '%f' ];
        if ( ! empty( $existing_overlay ) ) {
            $wpdb->update( $overlay_table, $overlay_payload, [ 'reserved_room_id' => $reserved_room_id ], $overlay_formats, [ '%d' ] );
        } else {
            $wpdb->insert( $overlay_table, $overlay_payload, $overlay_formats );
        }
    }

    simple_hotel_crm_clear_calendar_cache();

    return rest_ensure_response( [ 'success' => true ] );
}

