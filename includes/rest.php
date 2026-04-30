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

    register_rest_route( 'simple-hotel-crm/v1', '/booking-overlay', [
        'methods'  => 'POST',
        'callback' => 'simple_hotel_crm_rest_save_booking_overlay',
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

function simple_hotel_crm_rest_get_quick_booking( WP_REST_Request $request ) {
    global $wpdb;

    $booking_id = absint( $request->get_param( 'booking_id' ) );
    $bookings_table = simple_hotel_crm_bookings_table();
    $guests_table = simple_hotel_crm_guests_table();

    $booking = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT b.id, b.guest_id, b.status_code, b.source_channel, b.contacted_date, b.booking_note, b.internal_notes, g.first_name, g.last_name, g.phone
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

    return rest_ensure_response( [
        'id' => (int) $booking['id'],
        'guest_name' => trim( (string) $booking['first_name'] . ' ' . (string) $booking['last_name'] ),
        'phone' => (string) $booking['phone'],
        'status_code' => (string) $booking['status_code'],
        'source_channel' => (string) $booking['source_channel'],
        'contacted_date' => (string) $booking['contacted_date'],
        'booking_note' => (string) $booking['booking_note'],
        'internal_notes' => (string) $booking['internal_notes'],
        'status_options' => simple_hotel_crm_get_booking_status_options(),
        'channel_options' => simple_hotel_crm_get_booking_channel_options(),
        'detail_url' => admin_url( 'admin.php?page=simple-hotel-crm-booking-detail&booking_id=' . (int) $booking['id'] ),
    ] );
}

function simple_hotel_crm_rest_save_quick_booking( WP_REST_Request $request ) {
    global $wpdb;

    $booking_id = absint( $request->get_param( 'booking_id' ) );
    $guest_name = trim( sanitize_text_field( (string) $request->get_param( 'guest_name' ) ) );
    $phone = sanitize_text_field( (string) $request->get_param( 'phone' ) );
    $status_code = sanitize_text_field( (string) $request->get_param( 'status_code' ) );
    $source_channel = sanitize_text_field( (string) $request->get_param( 'source_channel' ) );
    $contacted_date = sanitize_text_field( (string) $request->get_param( 'contacted_date' ) );
    $booking_note = sanitize_textarea_field( (string) $request->get_param( 'booking_note' ) );
    $internal_notes = sanitize_textarea_field( (string) $request->get_param( 'internal_notes' ) );

    if ( '' === $guest_name ) {
        return new WP_Error( 'missing_guest_name', __( 'Guest name is required.', 'simple-hotel-crm' ), [ 'status' => 400 ] );
    }
    if ( ! isset( simple_hotel_crm_get_booking_status_options()[ $status_code ] ) ) {
        return new WP_Error( 'invalid_status_code', __( 'Please select a valid booking status.', 'simple-hotel-crm' ), [ 'status' => 400 ] );
    }
    if ( ! isset( simple_hotel_crm_get_booking_channel_options()[ $source_channel ] ) ) {
        return new WP_Error( 'invalid_source_channel', __( 'Please select a valid booking channel.', 'simple-hotel-crm' ), [ 'status' => 400 ] );
    }
    if ( '' !== $contacted_date && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $contacted_date ) ) {
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
    ], [ 'id' => (int) $booking['guest_id'] ], [ '%s', '%s', '%s' ], [ '%d' ] );

    $wpdb->update( $bookings_table, [
        'status_code' => $status_code,
        'source_channel' => $source_channel,
        'contacted_date' => $contacted_date ?: null,
        'booking_note' => $booking_note,
        'internal_notes' => $internal_notes,
    ], [ 'id' => $booking_id ], [ '%s', '%s', '%s', '%s', '%s' ], [ '%d' ] );

    $wpdb->update( $sync_bookings_table, [
        'status_code' => $status_code,
        'source_channel' => $source_channel,
        'channel_label' => simple_hotel_crm_get_booking_channel_options()[ $source_channel ] ?? $source_channel,
        'guest_name' => $guest_name,
        'phone' => $phone,
        'import_notes' => $internal_notes,
        'source_created_at' => $contacted_date ?: ( $booking['contacted_date'] ?: current_time( 'mysql' ) ),
    ], [ 'external_booking_id' => $booking_id ], [ '%s', '%s', '%s', '%s', '%s', '%s', '%s' ], [ '%d' ] );

    simple_hotel_crm_clear_calendar_cache();

    return rest_ensure_response( [ 'success' => true ] );
}

function simple_hotel_crm_rest_save_booking_overlay( WP_REST_Request $request ) {
    global $wpdb;

    $reserved_room_id = absint( $request->get_param( 'reserved_room_id' ) );
    $booking_id       = absint( $request->get_param( 'booking_id' ) );
    $room_id          = absint( $request->get_param( 'room_id' ) );

    if ( $reserved_room_id <= 0 ) {
        return new WP_Error( 'missing_reserved_room', __( 'Missing reserved room ID.', 'simple-hotel-crm' ), [ 'status' => 400 ] );
    }

    $table = simple_hotel_crm_booking_overlay_table();
    $existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE reserved_room_id = %d", $reserved_room_id ), ARRAY_A );

    $raw_extras_formula = $request->has_param( 'extras_formula' )
        ? $request->get_param( 'extras_formula' )
        : ( $existing['extras_formula'] ?? '' );

    $extras = simple_hotel_crm_evaluate_extras_formula( $raw_extras_formula );
    if ( ! $extras['valid'] ) {
        return new WP_Error( 'invalid_extras_formula', __( 'Extras formula only supports numbers joined with +.', 'simple-hotel-crm' ), [ 'status' => 400 ] );
    }

    $payload = [
        'booking_id'         => $booking_id,
        'reserved_room_id'   => $reserved_room_id,
        'room_id'            => $room_id,
        'booking_note'       => $request->has_param( 'booking_note' ) ? sanitize_textarea_field( (string) $request->get_param( 'booking_note' ) ) : (string) ( $existing['booking_note'] ?? '' ),
        'extras_formula'     => $extras['formula'],
        'extras_total'       => $extras['total'],
        'manual_guest_name'  => $request->has_param( 'manual_guest_name' ) ? sanitize_text_field( (string) $request->get_param( 'manual_guest_name' ) ) : (string) ( $existing['manual_guest_name'] ?? '' ),
        'manual_adults'      => $request->has_param( 'manual_adults' ) ? ( '' === (string) $request->get_param( 'manual_adults' ) ? null : max( 0, intval( $request->get_param( 'manual_adults' ) ) ) ) : ( isset( $existing['manual_adults'] ) && '' !== (string) $existing['manual_adults'] ? intval( $existing['manual_adults'] ) : null ),
        'manual_children'    => $request->has_param( 'manual_children' ) ? ( '' === (string) $request->get_param( 'manual_children' ) ? null : max( 0, intval( $request->get_param( 'manual_children' ) ) ) ) : ( isset( $existing['manual_children'] ) && '' !== (string) $existing['manual_children'] ? intval( $existing['manual_children'] ) : null ),
        'manual_tarif'       => $request->has_param( 'manual_tarif' ) ? simple_hotel_crm_normalize_decimal( $request->get_param( 'manual_tarif' ) ) : ( isset( $existing['manual_tarif'] ) ? simple_hotel_crm_normalize_decimal( $existing['manual_tarif'] ) : null ),
        'manual_commission'  => $request->has_param( 'manual_commission' ) ? simple_hotel_crm_normalize_decimal( $request->get_param( 'manual_commission' ) ) : ( isset( $existing['manual_commission'] ) ? simple_hotel_crm_normalize_decimal( $existing['manual_commission'] ) : null ),
    ];

    $formats = [ '%d', '%d', '%d', '%s', '%s', '%f', '%s', '%d', '%d', '%f', '%f' ];
    if ( ! empty( $existing ) ) {
        $wpdb->update( $table, $payload, [ 'reserved_room_id' => $reserved_room_id ], $formats, [ '%d' ] );
    } else {
        $wpdb->insert( $table, $payload, $formats );
    }

    simple_hotel_crm_clear_calendar_cache();

    return rest_ensure_response( [
        'success'           => true,
        'reserved_room_id'  => $reserved_room_id,
        'extras_formula'    => $extras['formula'],
        'extras_total'      => $extras['total'],
        'occupancy_str'     => simple_hotel_crm_format_occupancy( $payload['manual_adults'] ?? 0, $payload['manual_children'] ?? 0 ),
    ] );
}

