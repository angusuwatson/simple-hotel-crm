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
        'permission_callback' => function($r) { return simple_hotel_crm_user_can_access($r); },
    ] );

    register_rest_route( 'simple-hotel-crm/v1', '/daily-note', [
        'methods'  => 'POST',
        'callback' => 'simple_hotel_crm_rest_save_daily_note',
        'permission_callback' => function($r) { return simple_hotel_crm_user_can_access($r); },
    ] );

    register_rest_route( 'simple-hotel-crm/v1', '/create-invoice', [
        'methods'  => 'POST',
        'callback' => 'simple_hotel_crm_rest_create_invoice',
        'permission_callback' => function($r) { return simple_hotel_crm_user_can_access($r); },
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
        'permission_callback' => function($r) { return simple_hotel_crm_user_can_access($r); },
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
        'permission_callback' => function($r) { return simple_hotel_crm_user_can_access($r); },
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
        'permission_callback' => function($r) { return simple_hotel_crm_user_can_access($r); },
    ] );

    register_rest_route( 'simple-hotel-crm/v1', '/room-day-extras', [
        'methods'  => 'POST',
        'callback' => 'simple_hotel_crm_rest_save_room_day_extras',
        'permission_callback' => function($r) { return simple_hotel_crm_user_can_access($r); },
    ] );

    register_rest_route( 'simple-hotel-crm/v1', '/debug-ics', [
        'methods'             => 'GET',
        'callback'            => 'simple_hotel_crm_rest_debug_ics',
        'permission_callback' => function( $r ) { return simple_hotel_crm_user_can_access( $r ); },
    ] );

    register_rest_route( 'simple-hotel-crm/v1', '/square-webhook', [
        'methods'  => 'POST',
        'callback' => 'simple_hotel_crm_rest_square_webhook',
        'permission_callback' => '__return_true',
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

    register_rest_route( 'simple-hotel-crm/v1', '/ticket-data', [
        'methods'  => 'GET',
        'callback' => 'simple_hotel_crm_rest_ticket_data',
        'permission_callback' => function($r) { return simple_hotel_crm_user_can_access($r); },
        'args'     => [
            'date' => [
                'required' => true,
                'validate_callback' => function( $param ) {
                    return preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $param );
                },
            ],
            'booking_id' => [
                'validate_callback' => function( $param ) {
                    return is_numeric( $param ) && (int) $param > 0;
                },
            ],
        ],
    ] );

    register_rest_route( 'simple-hotel-crm/v1', '/ticket-save', [
        'methods'  => 'POST',
        'callback' => 'simple_hotel_crm_rest_ticket_save',
        'permission_callback' => function($r) { return simple_hotel_crm_user_can_access($r); },
    ] );

    register_rest_route( 'simple-hotel-crm/v1', '/ticket-checkout', [
        'methods'  => 'POST',
        'callback' => 'simple_hotel_crm_rest_ticket_checkout',
        'permission_callback' => function($r) { return simple_hotel_crm_user_can_access($r); },
    ] );

    register_rest_route( 'simple-hotel-crm/v1', '/ticket-show-bill', [
        'methods'  => 'POST',
        'callback' => 'simple_hotel_crm_rest_ticket_show_bill',
        'permission_callback' => function($r) { return simple_hotel_crm_user_can_access($r); },
    ] );

    register_rest_route( 'simple-hotel-crm/v1', '/ticket-checkout-status/(?P<action_id>[^/]+)', [
        'methods'             => 'GET',
        'callback'            => 'simple_hotel_crm_rest_ticket_checkout_status',
        'permission_callback' => '__return_true',
    ] );

    register_rest_route( 'simple-hotel-crm/v1', '/ticket-checkin', [
        'methods'  => 'POST',
        'callback' => 'simple_hotel_crm_rest_ticket_checkin',
        'permission_callback' => function($r) { return simple_hotel_crm_user_can_access($r); },
    ] );

    register_rest_route( 'simple-hotel-crm/v1', '/ticket-departure', [
        'methods'  => 'POST',
        'callback' => 'simple_hotel_crm_rest_ticket_departure',
        'permission_callback' => function($r) { return simple_hotel_crm_user_can_access($r); },
    ] );

    register_rest_route( 'simple-hotel-crm/v1', '/ticket-calendar', [
        'methods'  => 'GET',
        'callback' => 'simple_hotel_crm_rest_ticket_calendar',
        'permission_callback' => function($r) { return simple_hotel_crm_user_can_access($r); },
    ] );

    register_rest_route( 'simple-hotel-crm/v1', '/ticket-create-booking', [
        'methods'  => 'POST',
        'callback' => 'simple_hotel_crm_rest_ticket_create_booking',
        'permission_callback' => function($r) { return simple_hotel_crm_user_can_access($r); },
    ] );

    register_rest_route( 'simple-hotel-crm/v1', '/ticket-guest-search', [
        'methods'  => 'GET',
        'callback' => 'simple_hotel_crm_rest_ticket_guest_search',
        'permission_callback' => function($r) { return simple_hotel_crm_user_can_access($r); },
    ] );

    register_rest_route( 'simple-hotel-crm/v1', '/ticket-room-status', [
        'methods'  => 'GET',
        'callback' => 'simple_hotel_crm_rest_ticket_get_room_statuses',
        'permission_callback' => function($r) { return simple_hotel_crm_user_can_access($r); },
    ] );

    register_rest_route( 'simple-hotel-crm/v1', '/ticket-room-status', [
        'methods'  => 'POST',
        'callback' => 'simple_hotel_crm_rest_ticket_set_room_status',
        'permission_callback' => function($r) { return simple_hotel_crm_user_can_access($r); },
    ] );

    register_rest_route( 'simple-hotel-crm/v1', '/ticket-update-booking', [
        'methods'  => 'POST',
        'callback' => 'simple_hotel_crm_rest_ticket_update_booking',
        'permission_callback' => function($r) { return simple_hotel_crm_user_can_access($r); },
    ] );

    register_rest_route( 'simple-hotel-crm/v1', '/ticket-daily-note', [
        'methods'  => 'GET',
        'callback' => 'simple_hotel_crm_rest_ticket_daily_note',
        'permission_callback' => function($r) { return simple_hotel_crm_user_can_access($r); },
    ] );

    register_rest_route( 'simple-hotel-crm/v1', '/ticket-maintenance-notes', [
        'methods'  => 'GET',
        'callback' => 'simple_hotel_crm_rest_ticket_get_maintenance_notes',
        'permission_callback' => function($r) { return simple_hotel_crm_user_can_access($r); },
    ] );

    register_rest_route( 'simple-hotel-crm/v1', '/ticket-maintenance-notes', [
        'methods'  => 'POST',
        'callback' => 'simple_hotel_crm_rest_ticket_set_maintenance_notes',
        'permission_callback' => function($r) { return simple_hotel_crm_user_can_access($r); },
    ] );

    register_rest_route( 'simple-hotel-crm/v1', '/ticket-auth', [
        'methods'  => 'POST',
        'callback' => 'simple_hotel_crm_rest_ticket_auth',
        'permission_callback' => '__return_true',
        'args'     => [
            'username' => [
                'required' => true,
                'type'     => 'string',
            ],
            'password' => [
                'required' => true,
                'type'     => 'string',
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

    nocache_headers();
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
        'booking_note' => '' !== $room_note ? $room_note : $booking_note_global,
        'booking_note_global' => $booking_note_global,
        'extras_formula' => '',
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
        $booking_room_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM " . simple_hotel_crm_booking_rooms_table() . " WHERE legacy_reserved_room_id = %d LIMIT 1", $reserved_room_id ) );
        if ( $has_booking_note && $booking_room_id > 0 ) {
            simple_hotel_crm_upsert_booking_note( $booking_id, $booking_note, $booking_room_id, null, 'room' );
        }
    }

    simple_hotel_crm_clear_calendar_cache();

    return rest_ensure_response( [ 'success' => true ] );
}

function simple_hotel_crm_rest_square_webhook( WP_REST_Request $request ) {
    $signature = $request->get_header( 'x-square-hmacsha256-signature' );
    if ( empty( $signature ) ) {
        return new WP_Error( 'missing_signature', 'Missing webhook signature header.', [ 'status' => 401 ] );
    }

    $body = $request->get_body();

    if ( ! simple_hotel_crm_square_verify_webhook_signature( $body, $signature ) ) {
        return new WP_Error( 'invalid_signature', 'Invalid webhook signature.', [ 'status' => 401 ] );
    }

    $data = json_decode( $body, true );
    if ( empty( $data['type'] ) ) {
        return new WP_Error( 'invalid_payload', 'Invalid webhook payload.', [ 'status' => 400 ] );
    }

    if ( 'terminal.checkout.updated' !== $data['type'] ) {
        return rest_ensure_response( [ 'status' => 'ignored', 'type' => $data['type'] ] );
    }

    $checkout = isset( $data['data']['object']['checkout'] ) ? $data['data']['object']['checkout'] : [];
    $checkout_id = isset( $checkout['id'] ) ? $checkout['id'] : '';
    $checkout_status = isset( $checkout['status'] ) ? $checkout['status'] : '';
    $reference_id = isset( $checkout['reference_id'] ) ? $checkout['reference_id'] : '';

    if ( 'COMPLETED' !== $checkout_status || empty( $reference_id ) ) {
        return rest_ensure_response( [ 'status' => 'ignored' ] );
    }

    $booking_id = (int) $reference_id;
    $stored_checkout_id = get_post_meta( $booking_id, '_square_checkout_id', true );

    if ( empty( $stored_checkout_id ) ) {
        return rest_ensure_response( [ 'status' => 'ignored', 'reason' => 'no_checkout_for_booking' ] );
    }

    if ( $stored_checkout_id !== $checkout_id ) {
        return new WP_Error( 'checkout_mismatch', 'Checkout ID does not match stored value.', [ 'status' => 400 ] );
    }

    $result = simple_hotel_crm_square_handle_payment_complete( $booking_id, $checkout_id );
    if ( is_wp_error( $result ) ) {
        return $result;
    }

    return rest_ensure_response( [ 'status' => 'completed', 'booking_id' => $booking_id ] );
}

function simple_hotel_crm_rest_ticket_data( WP_REST_Request $request ) {
    global $wpdb;
    nocache_headers();
    $date = (string) $request->get_param( 'date' );
    $booking_id = absint( $request->get_param( 'booking_id' ) ?: 0 );

    $catalog = simple_hotel_crm_get_catalog_items();

    $bookings_table = simple_hotel_crm_bookings_table();
    $guests_table = simple_hotel_crm_guests_table();
    $booking_rooms_table = simple_hotel_crm_booking_rooms_table();
    $rooms_table = simple_hotel_crm_rooms_table();
    $booking_nights_table = simple_hotel_crm_booking_room_nights_table();

    $bookings = $wpdb->get_results( $wpdb->prepare( "
        SELECT b.id, b.status_code, b.total_amount, b.check_in_date, b.check_out_date, b.payment_status,
               b.source_channel, b.adults, b.children, b.babies,
               g.first_name, g.last_name, g.id AS guest_id,
               g.phone, g.email,
               g.address_line_1, g.address_line_2, g.city, g.postcode, g.country,
               g.date_of_birth, g.nationality, g.id_document_type, g.id_document_number, g.id_photo_url
        FROM {$bookings_table} b
        JOIN {$guests_table} g ON g.id = b.guest_id
        WHERE b.is_deleted = 0
          AND (b.internal_notes IS NULL OR b.internal_notes NOT LIKE '%[MERGED_ARCHIVE]%')
          AND b.status_code IN ('confirmed', 'checked_in')
          AND b.check_in_date <= %s
          AND b.check_out_date >= %s
        ORDER BY g.last_name ASC, b.check_in_date ASC
    ", $date, $date ), ARRAY_A );

    $booking_ids = array_column( $bookings, 'id' );
    $rooms_by_booking = [];
    if ( ! empty( $booking_ids ) ) {
        $placeholders = implode( ',', array_fill( 0, count( $booking_ids ), '%d' ) );
        $room_results = $wpdb->get_results( $wpdb->prepare( "
            SELECT br.booking_id, br.id AS booking_room_id, r.room_name, r.room_code, r.id AS room_id
            FROM {$booking_rooms_table} br
            JOIN {$rooms_table} r ON r.id = br.room_id
            WHERE br.booking_id IN ({$placeholders})
            ORDER BY r.sort_order ASC
        ", $booking_ids ), ARRAY_A );
        foreach ( $room_results as $rr ) {
            $rooms_by_booking[ $rr['booking_id'] ][] = $rr;
        }
    }

    $items = [];
    $booking_rooms = [];
    $room_nights = [];
    if ( $booking_id > 0 ) {
        $items = simple_hotel_crm_get_booking_items_by_room( $booking_id );
        $booking_rooms = isset( $rooms_by_booking[ $booking_id ] ) ? $rooms_by_booking[ $booking_id ] : [];
        $room_nights = $wpdb->get_results( $wpdb->prepare( "
            SELECT brn.id, brn.booking_room_id, brn.stay_date, brn.room_rate_amount, brn.extras_amount, brn.tourist_tax_amount, brn.total_amount,
                   r.room_name, r.room_code
            FROM {$booking_nights_table} brn
            JOIN {$booking_rooms_table} br ON br.id = brn.booking_room_id
            JOIN {$rooms_table} r ON r.id = br.room_id
            WHERE br.booking_id = %d
            ORDER BY brn.stay_date ASC, r.sort_order ASC
        ", $booking_id ), ARRAY_A );
    }

    $daily_notes_table = simple_hotel_crm_daily_notes_table();
    $month_start = date( 'Y-m-01', strtotime( $date ) );
    $month_end = date( 'Y-m-t', strtotime( $date ) );
    $daily_notes = $wpdb->get_results( $wpdb->prepare( "
        SELECT note_date, note_text
        FROM {$daily_notes_table}
        WHERE note_date >= %s AND note_date <= %s
        ORDER BY note_date ASC
    ", $month_start, $month_end ), ARRAY_A );

    $week_start = date( 'Y-m-d', strtotime( $date . ' -1 day' ) );
    $week_end = date( 'Y-m-d', strtotime( $date . ' +7 days' ) );

    $rooms = $wpdb->get_results( "
        SELECT id, room_name, room_code
        FROM {$rooms_table}
        WHERE is_deleted = 0
        ORDER BY sort_order ASC
    ", ARRAY_A );
    $occupancy = $wpdb->get_results( $wpdb->prepare( "
        SELECT brn.stay_date, brn.adults, brn.children, brn.babies, brn.guest_count,
               r.room_code, r.id AS room_id,
               b.id AS booking_id, b.status_code, g.first_name, g.last_name
        FROM {$booking_nights_table} brn
        JOIN {$booking_rooms_table} br ON br.id = brn.booking_room_id
        JOIN {$bookings_table} b ON b.id = br.booking_id
        JOIN {$rooms_table} r ON r.id = br.room_id
        JOIN {$guests_table} g ON g.id = b.guest_id
        WHERE b.is_deleted = 0
          AND b.status_code IN ('confirmed', 'checked_in')
          AND brn.stay_date >= %s
          AND brn.stay_date < %s
        ORDER BY brn.stay_date ASC, r.sort_order ASC
    ", $week_start, $week_end ), ARRAY_A );

    return rest_ensure_response( [
        'catalog'          => $catalog,
        'bookings'         => $bookings,
        'rooms_by_booking' => $rooms_by_booking,
        'items'            => $items,
        'booking_rooms'    => $booking_rooms,
        'room_nights'      => $room_nights,
        'daily_notes'      => $daily_notes,
        'occupancy'        => $occupancy,
        'rooms'            => $rooms,
    ] );
}

function simple_hotel_crm_rest_ticket_save( WP_REST_Request $request ) {
    global $wpdb;
    $booking_id = absint( $request->get_param( 'booking_id' ) );
    $booking_room_id = $request->has_param( 'booking_room_id' ) && null !== $request->get_param( 'booking_room_id' ) ? absint( $request->get_param( 'booking_room_id' ) ) : null;
    $date = (string) $request->get_param( 'date' );
    $items = $request->get_param( 'items' ) ?: [];

    if ( $booking_id <= 0 ) {
        return new WP_Error( 'invalid_booking', 'Invalid booking.', [ 'status' => 400 ] );
    }

    $table = simple_hotel_crm_booking_items_table();
    $where = [ 'booking_id' => $booking_id ];
    $where_format = [ '%d' ];
    if ( null !== $booking_room_id ) {
        $where['booking_room_id'] = $booking_room_id;
        $where_format[] = '%d';
    }
    if ( ! empty( $date ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
        $where['stay_date'] = $date;
        $where_format[] = '%s';
    }
    $wpdb->delete( $table, $where, $where_format );

    foreach ( $items as $item ) {
        $name = sanitize_text_field( (string) ( $item['name'] ?? '' ) );
        $qty = max( 1, absint( $item['qty'] ?? 1 ) );
        $price = round( abs( (float) ( $item['price'] ?? 0 ) ), 2 );
        if ( '' === $name || $price <= 0 ) {
            continue;
        }
        simple_hotel_crm_add_booking_item( $booking_id, $name, $qty, $price, $booking_room_id, $date );
    }

    $updated_items = simple_hotel_crm_get_booking_items_by_room( $booking_id );
    simple_hotel_crm_recalculate_booking_header_totals();
    simple_hotel_crm_clear_calendar_cache();

    // Handle native Terminal SDK payment recording
    $payment = $request->get_param( 'payment' );
    if ( is_array( $payment ) && ! empty( $payment['status'] ) && 'completed' === $payment['status'] ) {
        $paid_amount = round( (float) ( $payment['amount'] ?? 0 ), 2 );
        $payment_ref = sanitize_text_field( (string) ( $payment['ref'] ?? '' ) );
        $wpdb->update(
            simple_hotel_crm_bookings_table(),
            [ 'payment_status' => 'paid', 'payment_method' => 'terminal_sdk' ],
            [ 'id' => $booking_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );
        update_post_meta( $booking_id, '_square_checkout_status', 'completed' );
        if ( ! empty( $payment_ref ) ) {
            update_post_meta( $booking_id, '_terminal_sdk_payment_ref', $payment_ref );
        }
        if ( $paid_amount > 0 ) {
            update_post_meta( $booking_id, '_terminal_sdk_paid_amount', $paid_amount );
        }
        $invoice = simple_hotel_crm_create_invoice_ninja_invoice( $booking_id );
    }

    return rest_ensure_response( [ 'success' => true, 'items' => $updated_items ] );
}

function simple_hotel_crm_rest_ticket_checkout( WP_REST_Request $request ) {
    $booking_id = absint( $request->get_param( 'booking_id' ) );
    $amount = round( (float) ( $request->get_param( 'amount' ) ?: 0 ), 2 );
    $skip_receipt = ! empty( $request->get_param( 'skip_receipt' ) );
    $note = sanitize_text_field( (string) ( $request->get_param( 'note' ) ?: '' ) );

    if ( $booking_id <= 0 ) {
        return new WP_Error( 'invalid_booking', 'Invalid booking.', [ 'status' => 400 ] );
    }
    if ( $amount <= 0 ) {
        return new WP_Error( 'invalid_amount', 'Amount must be greater than 0.', [ 'status' => 400 ] );
    }
    if ( ! simple_hotel_crm_square_is_configured() ) {
        return new WP_Error( 'square_not_configured', 'Square Terminal is not configured.', [ 'status' => 400 ] );
    }

    $result = simple_hotel_crm_square_create_terminal_checkout( $booking_id, $amount, $skip_receipt, $note );
    if ( is_wp_error( $result ) ) {
        return new WP_Error( 'square_error', $result->get_error_message(), [ 'status' => 400 ] );
    }

    $checkout_id = isset( $result['checkout']['id'] ) ? $result['checkout']['id'] : '';

    return rest_ensure_response( [
        'success'     => true,
        'checkout_id' => $checkout_id,
        'status'      => 'pending',
        'booking_id'  => $booking_id,
    ] );
}

function simple_hotel_crm_rest_ticket_show_bill( WP_REST_Request $request ) {
    $booking_id = absint( $request->get_param( 'booking_id' ) );
    $amount = round( (float) ( $request->get_param( 'amount' ) ?: 0 ), 2 );
    $skip_receipt = ! empty( $request->get_param( 'skip_receipt' ) );

    if ( $booking_id <= 0 ) {
        return new WP_Error( 'invalid_booking', 'Invalid booking.', [ 'status' => 400 ] );
    }
    if ( $amount <= 0 ) {
        return new WP_Error( 'invalid_amount', 'Amount must be greater than 0.', [ 'status' => 400 ] );
    }
    if ( ! simple_hotel_crm_square_is_configured() ) {
        return new WP_Error( 'square_not_configured', 'Square Terminal is not configured.', [ 'status' => 400 ] );
    }

    $note = 'Booking #' . $booking_id;
    $result = simple_hotel_crm_square_create_terminal_checkout( $booking_id, $amount, $skip_receipt, $note );
    if ( is_wp_error( $result ) ) {
        error_log( 'LGF: ticket-show-bill failed: ' . $result->get_error_message() );
        return new WP_Error( 'square_error', $result->get_error_message(), [ 'status' => 400 ] );
    }

    $checkout_id = isset( $result['checkout']['id'] ) ? $result['checkout']['id'] : '';
    error_log( 'LGF: ticket-show-bill created checkout ' . $checkout_id . ' for booking ' . $booking_id . ' amount=' . $amount );

    return rest_ensure_response( [
        'success'     => true,
        'checkout_id' => $checkout_id,
        'booking_id'  => $booking_id,
        'amount'      => $amount,
    ] );
}

function simple_hotel_crm_rest_ticket_checkout_status( WP_REST_Request $request ) {
    $checkout_id = $request->get_param( 'action_id' );
    if ( empty( $checkout_id ) ) {
        return new WP_Error( 'invalid_checkout_id', 'Invalid checkout ID.', [ 'status' => 400 ] );
    }

    $checkout_status = simple_hotel_crm_square_get_checkout_status( $checkout_id );
    if ( is_wp_error( $checkout_status ) ) {
        return rest_ensure_response( [ 'status' => 'unknown' ] );
    }

    $lower = strtolower( $checkout_status );
    if ( $lower === 'completed' || $lower === 'canceled' || $lower === 'failed' ) {
        global $wpdb;
        $booking_id = (int) $wpdb->get_var( $wpdb->prepare( "
            SELECT post_id FROM {$wpdb->postmeta}
            WHERE meta_key = '_square_checkout_id'
            AND meta_value = %s
            LIMIT 1
        ", $checkout_id ) );
        if ( $booking_id ) {
            delete_post_meta( $booking_id, '_pending_checkout_amount' );
            delete_post_meta( $booking_id, '_pending_checkout_skip_receipt' );
            delete_post_meta( $booking_id, '_pending_checkout_action_id' );
        }
    }

    return rest_ensure_response( [ 'status' => $lower, 'checkout_id' => $checkout_id ] );
}

function simple_hotel_crm_rest_ticket_checkin( WP_REST_Request $request ) {
    global $wpdb;

    $booking_id = absint( $request->get_param( 'booking_id' ) );
    if ( $booking_id <= 0 ) {
        return new WP_Error( 'invalid_booking', 'Invalid booking.', [ 'status' => 400 ] );
    }

    $bookings_table = simple_hotel_crm_bookings_table();
    $guests_table = simple_hotel_crm_guests_table();

    $booking = $wpdb->get_row( $wpdb->prepare( "
        SELECT b.*, g.first_name, g.last_name, g.phone, g.email,
               g.address_line_1, g.address_line_2, g.city, g.postcode, g.country,
               g.date_of_birth, g.nationality, g.id_document_type, g.id_document_number, g.id_photo_url
        FROM {$bookings_table} b
        JOIN {$guests_table} g ON g.id = b.guest_id
        WHERE b.id = %d AND b.is_deleted = 0
        LIMIT 1
    ", $booking_id ) );

    if ( ! $booking ) {
        return new WP_Error( 'booking_not_found', 'Booking not found.', [ 'status' => 404 ] );
    }

    if ( 'confirmed' !== $booking->status_code ) {
        return new WP_Error( 'invalid_status', 'Only confirmed bookings can be checked in.', [ 'status' => 400 ] );
    }

    $today = current_time( 'Y-m-d' );
    if ( $booking->check_in_date !== $today ) {
        return new WP_Error( 'not_today', 'Only today\'s arrivals can be checked in.', [ 'status' => 400 ] );
    }

    $guest = (array) $request->get_param( 'guest' );

    $guest_update = [];

    $guest_fields = [
        'first_name', 'last_name', 'phone', 'email',
        'address_line_1', 'address_line_2', 'city', 'postcode', 'country',
        'date_of_birth', 'nationality', 'id_document_type', 'id_document_number',
    ];

    foreach ( $guest_fields as $field ) {
        if ( isset( $guest[ $field ] ) ) {
            $guest_update[ $field ] = sanitize_text_field( (string) $guest[ $field ] );
        }
    }

    $id_photo_base64 = isset( $guest['id_photo_base64'] ) ? trim( (string) $guest['id_photo_base64'] ) : '';
    if ( ! empty( $id_photo_base64 ) ) {
        $upload_dir = wp_upload_dir();
        $photo_dir = $upload_dir['basedir'] . '/lgf-id-photos';
        if ( ! file_exists( $photo_dir ) ) {
            wp_mkdir_p( $photo_dir );
        }

        if ( preg_match( '/^data:image\/(jpeg|png|gif|webp);base64,(.+)$/i', $id_photo_base64, $m ) ) {
            $ext = $m[1] === 'jpeg' ? 'jpg' : $m[1];
            $data = base64_decode( $m[2] );
            if ( $data && strlen( $data ) < 5 * 1024 * 1024 ) {
                $filename = 'guest-' . $booking->guest_id . '-' . time() . '.' . $ext;
                $filepath = $photo_dir . '/' . $filename;
                if ( file_put_contents( $filepath, $data ) ) {
                    $guest_update['id_photo_url'] = $upload_dir['baseurl'] . '/lgf-id-photos/' . $filename;
                }
            }
        }
    }

    if ( ! empty( $guest_update ) ) {
        $guest_update['updated_at'] = current_time( 'mysql' );
        $wpdb->update( $guests_table, $guest_update, [ 'id' => (int) $booking->guest_id ] );
    }

    $wpdb->update( $bookings_table, [ 'status_code' => 'checked_in' ], [ 'id' => $booking_id ] );

    error_log( 'LGF: Booking #' . $booking_id . ' checked in by API (guest_id=' . $booking->guest_id . ')' );

    return rest_ensure_response( [
        'success'    => true,
        'booking_id' => $booking_id,
        'guest_id'   => (int) $booking->guest_id,
        'status'     => 'checked_in',
    ] );
}

function simple_hotel_crm_rest_ticket_departure( WP_REST_Request $request ) {
    global $wpdb;

    $booking_id = absint( $request->get_param( 'booking_id' ) );
    if ( $booking_id <= 0 ) {
        return new WP_Error( 'invalid_booking', 'Invalid booking.', [ 'status' => 400 ] );
    }

    $bookings_table = simple_hotel_crm_bookings_table();
    $booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$bookings_table} WHERE id = %d AND is_deleted = 0 LIMIT 1", $booking_id ) );
    if ( ! $booking ) {
        return new WP_Error( 'booking_not_found', 'Booking not found.', [ 'status' => 404 ] );
    }

    if ( 'checked_in' !== $booking->status_code ) {
        return new WP_Error( 'invalid_status', 'Only checked-in bookings can depart.', [ 'status' => 400 ] );
    }

    $today = current_time( 'Y-m-d' );
    if ( $booking->check_out_date !== $today ) {
        return new WP_Error( 'not_today', 'Only today\'s departures can be checked out.', [ 'status' => 400 ] );
    }

    $wpdb->update( $bookings_table, [ 'status_code' => 'checked_out' ], [ 'id' => $booking_id ] );

    error_log( 'LGF: Booking #' . $booking_id . ' checked out by API' );

    return rest_ensure_response( [
        'success'    => true,
        'booking_id' => $booking_id,
        'status'     => 'checked_out',
    ] );
}

function simple_hotel_crm_rest_ticket_calendar( WP_REST_Request $request ) {
    global $wpdb;

    $start_date = (string) $request->get_param( 'date' );
    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start_date ) ) {
        $start_date = current_time( 'Y-m-d' );
    }
    $days = max( 7, min( 31, absint( $request->get_param( 'days' ) ?: 12 ) ) );
    $end_date = gmdate( 'Y-m-d', strtotime( $start_date . ' +' . $days . ' days' ) );

    $rooms_table = simple_hotel_crm_rooms_table();
    $bookings_table = simple_hotel_crm_bookings_table();
    $guests_table = simple_hotel_crm_guests_table();
    $booking_rooms_table = simple_hotel_crm_booking_rooms_table();
    $booking_nights_table = simple_hotel_crm_booking_room_nights_table();

    $rooms = $wpdb->get_results( "SELECT id, room_code, room_name, sort_order, color, active FROM {$rooms_table} WHERE active = 1 ORDER BY sort_order ASC", ARRAY_A );

    $occupancy = $wpdb->get_results( $wpdb->prepare( "
        SELECT brn.stay_date, br.room_id, r.room_code, r.room_name,
               b.id AS booking_id, b.status_code, b.check_in_date, b.check_out_date,
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
        ORDER BY brn.stay_date ASC, r.sort_order ASC
    ", $start_date, $end_date ), ARRAY_A );

    $daily_notes_table = simple_hotel_crm_daily_notes_table();
    $daily_notes = $wpdb->get_results( $wpdb->prepare( "
        SELECT note_date, note_text FROM {$daily_notes_table}
        WHERE note_date >= %s AND note_date < %s
        ORDER BY note_date ASC
    ", $start_date, $end_date ), ARRAY_A );

    return rest_ensure_response( [
        'date'       => $start_date,
        'days'       => $days,
        'rooms'      => $rooms,
        'occupancy'  => $occupancy,
        'daily_notes' => $daily_notes,
    ] );
}

function simple_hotel_crm_rest_ticket_create_booking( WP_REST_Request $request ) {
    global $wpdb;

    $guest_name = trim( sanitize_text_field( (string) $request->get_param( 'guest_name' ) ) );
    $phone = sanitize_text_field( (string) $request->get_param( 'phone' ) );
    $email = sanitize_email( (string) $request->get_param( 'email' ) );
    $check_in = sanitize_text_field( (string) $request->get_param( 'check_in' ) );
    $check_out = sanitize_text_field( (string) $request->get_param( 'check_out' ) );
    $room_id = absint( $request->get_param( 'room_id' ) );
    $adults = absint( $request->get_param( 'adults' ) ?: 1 );
    $children = absint( $request->get_param( 'children' ) );
    $babies = absint( $request->get_param( 'babies' ) );
    $total_amount = (float) $request->get_param( 'total_amount' ) ?: 0;

    if ( '' === $guest_name ) {
        return new WP_Error( 'missing_guest_name', 'Guest name is required.', [ 'status' => 400 ] );
    }
    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $check_in ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $check_out ) ) {
        return new WP_Error( 'invalid_dates', 'Valid check-in and check-out dates required.', [ 'status' => 400 ] );
    }
    if ( $check_out <= $check_in ) {
        return new WP_Error( 'invalid_dates', 'Check-out must be after check-in.', [ 'status' => 400 ] );
    }

    $guests_table = simple_hotel_crm_guests_table();
    $bookings_table = simple_hotel_crm_bookings_table();
    $booking_rooms_table = simple_hotel_crm_booking_rooms_table();
    $booking_nights_table = simple_hotel_crm_booking_room_nights_table();
    $rooms_table = simple_hotel_crm_rooms_table();

    if ( $room_id > 0 ) {
        $room = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$rooms_table} WHERE id = %d AND active = 1 LIMIT 1", $room_id ) );
        if ( ! $room ) {
            return new WP_Error( 'room_not_found', 'Selected room not found.', [ 'status' => 400 ] );
        }
    }

    $today = current_time( 'Y-m-d' );

    list( $first_name, $last_name ) = simple_hotel_crm_split_guest_name( $guest_name );
    $guest_inserted = $wpdb->insert(
        $guests_table,
        [
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'phone'      => $phone,
            'email'      => $email,
            'created_at' => current_time( 'mysql' ),
            'updated_at' => current_time( 'mysql' ),
        ],
        [ '%s', '%s', '%s', '%s', '%s', '%s' ]
    );
    if ( ! $guest_inserted ) {
        return new WP_Error( 'guest_create_failed', 'Could not create guest.', [ 'status' => 500 ] );
    }
    $guest_id = $wpdb->insert_id;

    $nights = max( 1, (int) round( ( strtotime( $check_out ) - strtotime( $check_in ) ) / DAY_IN_SECONDS ) );
    $guest_count = $adults + $children + $babies;

    $booking_inserted = $wpdb->insert(
        $bookings_table,
        [
            'guest_id'       => $guest_id,
            'source_channel' => 'direct',
            'status_code'    => 'confirmed',
            'check_in_date'  => $check_in,
            'check_out_date' => $check_out,
            'adults'         => $adults,
            'children'       => $children,
            'babies'         => $babies,
            'total_amount'   => $total_amount,
            'currency'       => 'EUR',
            'created_at'     => current_time( 'mysql' ),
            'updated_at'     => current_time( 'mysql' ),
        ],
        [ '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%f', '%s', '%s', '%s' ]
    );
    if ( ! $booking_inserted ) {
        return new WP_Error( 'booking_create_failed', 'Could not create booking.', [ 'status' => 500 ] );
    }
    $booking_id = $wpdb->insert_id;

    if ( $room_id > 0 ) {
        $wpdb->insert(
            $booking_rooms_table,
            [
                'booking_id'   => $booking_id,
                'room_id'      => $room_id,
                'occupancy_adults' => $adults,
                'guest_count'  => $guest_count,
                'adults'       => $adults,
                'children'     => $children,
                'babies'       => $babies,
            ],
            [ '%d', '%d', '%d', '%d', '%d', '%d', '%d' ]
        );
        $booking_room_id = $wpdb->insert_id;

        for ( $i = 0; $i < $nights; $i++ ) {
            $stay_date = gmdate( 'Y-m-d', strtotime( $check_in . ' +' . $i . ' days' ) );
            $wpdb->insert(
                $booking_nights_table,
                [
                    'booking_room_id' => $booking_room_id,
                    'stay_date'       => $stay_date,
                    'guest_count'     => $guest_count,
                    'adults'          => $adults,
                    'children'        => $children,
                    'babies'          => $babies,
                    'room_rate_amount' => 0,
                    'total_amount'    => 0,
                ],
                [ '%d', '%s', '%d', '%d', '%d', '%d', '%f', '%f' ]
            );
        }
    }

    simple_hotel_crm_clear_calendar_cache();

    error_log( 'LGF: Booking #' . $booking_id . ' created via PWA (guest_id=' . $guest_id . ')' );

    return rest_ensure_response( [
        'success'    => true,
        'booking_id' => $booking_id,
        'guest_id'   => $guest_id,
    ] );
}

function simple_hotel_crm_rest_ticket_guest_search( WP_REST_Request $request ) {
    global $wpdb;

    $q = trim( sanitize_text_field( (string) $request->get_param( 'q' ) ) );
    if ( strlen( $q ) < 2 ) {
        return rest_ensure_response( [ 'guests' => [] ] );
    }

    $guests_table = simple_hotel_crm_guests_table();
    $bookings_table = simple_hotel_crm_bookings_table();

    $like = '%' . $wpdb->esc_like( $q ) . '%';
    $guests = $wpdb->get_results( $wpdb->prepare( "
        SELECT g.id, g.first_name, g.last_name, g.phone, g.email,
               (SELECT COUNT(*) FROM {$bookings_table} b WHERE b.guest_id = g.id AND b.is_deleted = 0 AND b.status_code NOT IN ('cancelled')) AS booking_count,
               (SELECT MAX(b.check_in_date) FROM {$bookings_table} b WHERE b.guest_id = g.id AND b.is_deleted = 0) AS last_visit
        FROM {$guests_table} g
        WHERE g.is_deleted = 0
          AND (g.first_name LIKE %s OR g.last_name LIKE %s OR CONCAT(g.first_name, ' ', g.last_name) LIKE %s OR g.phone LIKE %s OR g.email LIKE %s)
        ORDER BY g.last_name ASC
        LIMIT 20
    ", $like, $like, $like, $like, $like ), ARRAY_A );

    return rest_ensure_response( [ 'guests' => $guests ] );
}

function simple_hotel_crm_rest_ticket_get_room_statuses( WP_REST_Request $request ) {
    global $wpdb;

    $rooms_table = simple_hotel_crm_rooms_table();
    $has_status = simple_hotel_crm_table_has_column( $rooms_table, 'room_status' );

    $rooms = $wpdb->get_results( "SELECT id, room_code, room_name, sort_order, color, active" . ( $has_status ? ", room_status" : "" ) . " FROM {$rooms_table} WHERE active = 1 ORDER BY sort_order ASC", ARRAY_A );

    return rest_ensure_response( [ 'rooms' => $rooms ] );
}

function simple_hotel_crm_rest_ticket_set_room_status( WP_REST_Request $request ) {
    global $wpdb;

    $room_id = absint( $request->get_param( 'room_id' ) );
    $status = sanitize_text_field( (string) $request->get_param( 'status' ) );

    if ( $room_id <= 0 ) {
        return new WP_Error( 'invalid_room', 'Invalid room ID.', [ 'status' => 400 ] );
    }
    if ( ! in_array( $status, [ 'clean', 'dirty', 'maintenance' ], true ) ) {
        return new WP_Error( 'invalid_status', 'Status must be clean, dirty, or maintenance.', [ 'status' => 400 ] );
    }

    $rooms_table = simple_hotel_crm_rooms_table();
    if ( ! simple_hotel_crm_table_has_column( $rooms_table, 'room_status' ) ) {
        $wpdb->query( "ALTER TABLE {$rooms_table} ADD COLUMN room_status varchar(20) NOT NULL DEFAULT 'clean'" );
    }

    $wpdb->update( $rooms_table, [ 'room_status' => $status ], [ 'id' => $room_id ], [ '%s' ], [ '%d' ] );

    return rest_ensure_response( [ 'success' => true, 'room_id' => $room_id, 'status' => $status ] );
}

function simple_hotel_crm_rest_ticket_update_booking( WP_REST_Request $request ) {
    global $wpdb;

    $booking_id = absint( $request->get_param( 'booking_id' ) );
    if ( $booking_id <= 0 ) {
        return new WP_Error( 'invalid_booking', 'Invalid booking.', [ 'status' => 400 ] );
    }

    $bookings_table = simple_hotel_crm_bookings_table();
    $booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$bookings_table} WHERE id = %d AND is_deleted = 0 LIMIT 1", $booking_id ) );
    if ( ! $booking ) {
        return new WP_Error( 'booking_not_found', 'Booking not found.', [ 'status' => 404 ] );
    }

    $update = [];
    $formats = [];

    $new_check_in = (string) $request->get_param( 'check_in' );
    $new_check_out = (string) $request->get_param( 'check_out' );

    if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $new_check_in ) && $new_check_in !== $booking->check_in_date ) {
        $update['check_in_date'] = $new_check_in;
        $formats[] = '%s';
    }
    if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $new_check_out ) && $new_check_out !== $booking->check_out_date ) {
        $update['check_out_date'] = $new_check_out;
        $formats[] = '%s';
    }

    if ( ! empty( $update ) ) {
        $update['updated_at'] = current_time( 'mysql' );
        $formats[] = '%s';
        $wpdb->update( $bookings_table, $update, [ 'id' => $booking_id ], $formats, [ '%d' ] );
    }

    $add_room_id = absint( $request->get_param( 'add_room_id' ) );
    if ( $add_room_id > 0 ) {
        $booking_rooms_table = simple_hotel_crm_booking_rooms_table();
        $booking_nights_table = simple_hotel_crm_booking_room_nights_table();
        $rooms_table = simple_hotel_crm_rooms_table();

        $room = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$rooms_table} WHERE id = %d AND active = 1 LIMIT 1", $add_room_id ) );
        if ( ! $room ) {
            return new WP_Error( 'room_not_found', 'Room not found.', [ 'status' => 400 ] );
        }

        $existing_room = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$booking_rooms_table} WHERE booking_id = %d AND room_id = %d LIMIT 1", $booking_id, $add_room_id ) );
        if ( ! $existing_room ) {
            $check_in = $new_check_in && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $new_check_in ) ? $new_check_in : $booking->check_in_date;
            $check_out = $new_check_out && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $new_check_out ) ? $new_check_out : $booking->check_out_date;
            $nights = max( 1, (int) round( ( strtotime( $check_out ) - strtotime( $check_in ) ) / DAY_IN_SECONDS ) );

            $wpdb->insert(
                $booking_rooms_table,
                [
                    'booking_id'   => $booking_id,
                    'room_id'      => $add_room_id,
                    'guest_count'  => $booking->adults ?: 1,
                    'adults'       => $booking->adults ?: 1,
                ],
                [ '%d', '%d', '%d', '%d' ]
            );
            $booking_room_id = $wpdb->insert_id;

            for ( $i = 0; $i < $nights; $i++ ) {
                $stay_date = gmdate( 'Y-m-d', strtotime( $check_in . ' +' . $i . ' days' ) );
                $wpdb->insert(
                    $booking_nights_table,
                    [
                        'booking_room_id' => $booking_room_id,
                        'stay_date'       => $stay_date,
                        'guest_count'     => $booking->adults ?: 1,
                        'adults'          => $booking->adults ?: 1,
                    ],
                    [ '%d', '%s', '%d', '%d' ]
                );
            }
        }
    }

    simple_hotel_crm_clear_calendar_cache();

    return rest_ensure_response( [ 'success' => true ] );
}

function simple_hotel_crm_rest_ticket_daily_note( WP_REST_Request $request ) {
    global $wpdb;

    $date = (string) $request->get_param( 'date' );
    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
        $date = current_time( 'Y-m-d' );
    }

    $table = simple_hotel_crm_daily_notes_table();
    $note = $wpdb->get_var( $wpdb->prepare( "SELECT note_text FROM {$table} WHERE note_date = %s", $date ) );

    return rest_ensure_response( [
        'date' => $date,
        'note' => null !== $note ? (string) $note : '',
    ] );
}

function simple_hotel_crm_rest_ticket_get_maintenance_notes() {
    $notes = get_option( 'simple_hotel_crm_maintenance_notes', [] );
    if ( ! is_array( $notes ) ) {
        $notes = [];
    }
    return rest_ensure_response( [ 'notes' => array_values( $notes ) ] );
}

function simple_hotel_crm_rest_ticket_set_maintenance_notes( WP_REST_Request $request ) {
    $notes = $request->get_param( 'notes' );
    if ( ! is_array( $notes ) ) {
        $notes = [];
    }
    $clean = [];
    foreach ( $notes as $note ) {
        if ( is_array( $note ) && isset( $note['text'] ) ) {
            $clean[] = [
                'id'    => sanitize_text_field( (string) ( $note['id'] ?? uniqid() ) ),
                'text'  => sanitize_textarea_field( (string) $note['text'] ),
                'date'  => sanitize_text_field( (string) ( $note['date'] ?? current_time( 'Y-m-d' ) ) ),
                'done'  => ! empty( $note['done'] ),
            ];
        }
    }
    update_option( 'simple_hotel_crm_maintenance_notes', $clean, false );
    return rest_ensure_response( [ 'success' => true, 'notes' => $clean ] );
}

function simple_hotel_crm_rest_ticket_auth( WP_REST_Request $request ) {
    $username = $request->get_param( 'username' );
    $password = $request->get_param( 'password' );

    $user = wp_authenticate( $username, $password );
    if ( is_wp_error( $user ) ) {
        return new WP_Error( 'auth_failed', 'Invalid username or password.', [ 'status' => 401 ] );
    }

    if ( ! user_can( $user, 'manage_options' ) ) {
        return new WP_Error( 'auth_failed', 'Insufficient privileges.', [ 'status' => 401 ] );
    }

    $api_key = get_option( 'simple_hotel_crm_dashboard_api_key', '' );
    if ( empty( $api_key ) ) {
        $api_key = wp_generate_password( 32, false );
        update_option( 'simple_hotel_crm_dashboard_api_key', $api_key );
    }

    return rest_ensure_response( [
        'ok'      => true,
        'name'    => $user->display_name,
        'api_key' => $api_key,
    ] );
}

function simple_hotel_crm_rest_debug_ics() {
    $data = simple_hotel_crm_debug_ics_feeds();
    return rest_ensure_response( $data );
}

