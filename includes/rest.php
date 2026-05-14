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

    register_rest_route( 'simple-hotel-crm/v1', '/dashboard', [
        'methods'  => 'GET',
        'callback' => 'simple_hotel_crm_rest_dashboard',
        'permission_callback' => function() { return true; },
        'args'     => [
            'api_key' => [
                'required' => true,
                'validate_callback' => function( $param ) {
                    $stored = get_option( 'simple_hotel_crm_dashboard_api_key', '' );
                    return ! empty( $stored ) && hash_equals( $stored, $param );
                },
            ],
            'month' => [
                'validate_callback' => function( $param ) { return is_numeric( $param ) && $param >= 1 && $param <= 12; },
            ],
            'year' => [
                'validate_callback' => function( $param ) { return is_numeric( $param ) && $param >= 2000 && $param <= 2100; },
            ],
        ],
    ] );
} );

function simple_hotel_crm_rest_dashboard( WP_REST_Request $request ) {
    global $wpdb;

    $today = current_time( 'Y-m-d' );
    $month = intval( $request->get_param( 'month' ) ?: current_time( 'n' ) );
    $year  = intval( $request->get_param( 'year' ) ?: current_time( 'Y' ) );

    $bookings_table = simple_hotel_crm_bookings_table();
    $guests_table = simple_hotel_crm_guests_table();
    $rooms_table = simple_hotel_crm_rooms_table();
    $booking_rooms_table = simple_hotel_crm_booking_rooms_table();
    $booking_nights_table = simple_hotel_crm_booking_room_nights_table();
    $daily_notes_table = simple_hotel_crm_daily_notes_table();

    // Rooms
    $rooms = $wpdb->get_results( "SELECT id, room_code, room_name, sort_order, color FROM {$rooms_table} WHERE active = 1 ORDER BY sort_order ASC", ARRAY_A );

    // Today's arrivals (check_in = today, confirmed/checked_in)
    $arrivals = $wpdb->get_results( $wpdb->prepare( "
        SELECT b.id, b.status_code, b.check_in_date, b.check_out_date, b.total_amount, b.source_channel,
               b.adults, b.children, b.babies,
               g.first_name, g.last_name, g.phone, g.email
        FROM {$bookings_table} b
        JOIN {$guests_table} g ON g.id = b.guest_id
        WHERE b.is_deleted = 0
          AND (b.internal_notes IS NULL OR b.internal_notes NOT LIKE '%[MERGED_ARCHIVE]%')
          AND b.status_code IN ('confirmed', 'checked_in')
          AND b.check_in_date = %s
        ORDER BY g.last_name ASC", $today ), ARRAY_A );

    // Today's departures (check_out = today)
    $departures = $wpdb->get_results( $wpdb->prepare( "
        SELECT b.id, b.status_code, b.check_in_date, b.check_out_date, b.total_amount, b.source_channel,
               b.adults, b.children, b.babies,
               g.first_name, g.last_name, g.phone, g.email
        FROM {$bookings_table} b
        JOIN {$guests_table} g ON g.id = b.guest_id
        WHERE b.is_deleted = 0
          AND (b.internal_notes IS NULL OR b.internal_notes NOT LIKE '%[MERGED_ARCHIVE]%')
          AND b.status_code IN ('confirmed', 'checked_in', 'checked_out')
          AND b.check_out_date = %s
        ORDER BY g.last_name ASC", $today ), ARRAY_A );

    // Next 8 days occupancy (include yesterday for bread order)
    $week_start = date( 'Y-m-d', strtotime( $today . ' -1 day' ) );
    $week_end = date( 'Y-m-d', strtotime( $today . ' +7 days' ) );
    $occupancy = $wpdb->get_results( $wpdb->prepare( "
        SELECT
            brn.stay_date,
            br.room_id,
            r.room_code, r.room_name, r.color,
            brn.guest_count, brn.adults, brn.children, brn.babies,
            brn.total_amount, brn.room_rate_amount, brn.extras_amount, brn.tourist_tax_amount,
            b.id AS booking_id, b.status_code, b.check_in_date, b.check_out_date,
            b.source_channel,
            g.first_name, g.last_name
        FROM {$booking_nights_table} brn
        JOIN {$booking_rooms_table} br ON br.id = brn.booking_room_id
        JOIN {$bookings_table} b ON b.id = br.booking_id
        JOIN {$guests_table} g ON g.id = b.guest_id
        JOIN {$rooms_table} r ON r.id = br.room_id
        WHERE b.is_deleted = 0
          AND (b.internal_notes IS NULL OR b.internal_notes NOT LIKE '%[MERGED_ARCHIVE]%')
          AND b.status_code IN ('confirmed', 'checked_in')
          AND brn.stay_date >= %s
          AND brn.stay_date < %s
        ORDER BY brn.stay_date ASC, r.sort_order ASC", $week_start, $week_end ), ARRAY_A );

    // Taxe de séjour for given month
    $month_start = sprintf( '%04d-%02d-01', $year, $month );
    $month_end = date( 'Y-m-t', strtotime( $month_start ) );
    $taxe_sejour = $wpdb->get_var( $wpdb->prepare( "
        SELECT COALESCE(SUM(brn.tourist_tax_amount), 0)
        FROM {$booking_nights_table} brn
        JOIN {$booking_rooms_table} br ON br.id = brn.booking_room_id
        JOIN {$bookings_table} b ON b.id = br.booking_id
        WHERE b.is_deleted = 0
          AND (b.internal_notes IS NULL OR b.internal_notes NOT LIKE '%[MERGED_ARCHIVE]%')
          AND b.status_code IN ('confirmed', 'checked_in', 'checked_out')
          AND brn.stay_date >= %s
          AND brn.stay_date < %s", $month_start, $month_end ) );

    // Current trimester income
    $trimester_start = date( 'Y-m-d', strtotime( $today . ' -3 months' ) );
    $trimester_income = $wpdb->get_var( $wpdb->prepare( "
        SELECT COALESCE(SUM(b.total_amount), 0)
        FROM {$bookings_table} b
        WHERE b.is_deleted = 0
          AND b.status_code IN ('confirmed', 'checked_in', 'checked_out')
          AND b.check_in_date >= %s
          AND b.check_in_date < %s", $trimester_start, $today ) );

    // Previous year same trimester income
    $last_year_trimester_start = date( 'Y-m-d', strtotime( $trimester_start . ' -1 year' ) );
    $last_year_today = date( 'Y-m-d', strtotime( $today . ' -1 year' ) );
    $last_year_trimester_income = $wpdb->get_var( $wpdb->prepare( "
        SELECT COALESCE(SUM(b.total_amount), 0)
        FROM {$bookings_table} b
        WHERE b.is_deleted = 0
          AND b.status_code IN ('confirmed', 'checked_in', 'checked_out')
          AND b.check_in_date >= %s
          AND b.check_in_date < %s", $last_year_trimester_start, $last_year_today ) );

    // Daily notes for current month
    $daily_notes = $wpdb->get_results( $wpdb->prepare( "
        SELECT note_date, note_text
        FROM {$daily_notes_table}
        WHERE note_date >= %s AND note_date <= %s
        ORDER BY note_date ASC", $month_start, $month_end ), ARRAY_A );

    // Guest counts for next 7 days (for bread calc)
    $guest_counts = $wpdb->get_results( $wpdb->prepare( "
        SELECT brn.stay_date,
               SUM(brn.adults) AS total_adults,
               SUM(brn.children + brn.babies) AS total_children,
               SUM(brn.guest_count) AS total_guests
        FROM {$booking_nights_table} brn
        JOIN {$booking_rooms_table} br ON br.id = brn.booking_room_id
        JOIN {$bookings_table} b ON b.id = br.booking_id
        WHERE b.is_deleted = 0
          AND (b.internal_notes IS NULL OR b.internal_notes NOT LIKE '%[MERGED_ARCHIVE]%')
          AND b.status_code IN ('confirmed', 'checked_in')
          AND brn.stay_date >= %s
          AND brn.stay_date < %s
        GROUP BY brn.stay_date
        ORDER BY brn.stay_date ASC", $today, $week_end ), ARRAY_A );

    return rest_ensure_response( [
        'now'            => current_time( 'c' ),
        'today'          => $today,
        'rooms'          => $rooms,
        'arrivals'       => $arrivals,
        'departures'     => $departures,
        'occupancy'      => $occupancy,
        'taxe_sejour'    => [
            'month'  => $month,
            'year'   => $year,
            'amount' => round( (float) $taxe_sejour, 2 ),
        ],
        'trimester' => [
            'current_start' => $trimester_start,
            'current_end'   => $today,
            'current'       => round( (float) $trimester_income, 2 ),
            'last_year_start' => $last_year_trimester_start,
            'last_year_end' => $last_year_today,
            'last_year'     => round( (float) $last_year_trimester_income, 2 ),
        ],
        'daily_notes'    => $daily_notes,
        'guest_counts'   => $guest_counts,
    ] );
}

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
        return new WP_Error( 'invalid_extras_formula', __( 'Extras formula supports numbers with +, -, * and /.', 'simple-hotel-crm' ), [ 'status' => 400 ] );
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
            return new WP_Error( 'invalid_extras_formula', __( 'Extras formula supports numbers with +, -, * and /.', 'simple-hotel-crm' ), [ 'status' => 400 ] );
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

