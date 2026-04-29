<?php
/**
 * Plugin Name: Simple Hotel CRM
 * Description: A simple WordPress-native hotel CRM with calendar and booking management tools
 * Version: 1.6
 * Author: Angus Watson, Quinn (mistral/codestral) & Kylie (stepfun/step-3.5-flash:free)
 * Text Domain: simple-hotel-crm
 */

defined( 'ABSPATH' ) || exit;

define( 'SIMPLE_HOTEL_CRM_VERSION', '1.6' );
define( 'SIMPLE_HOTEL_CRM_DB_VERSION', '5' );

add_action( 'plugins_loaded', 'simple_hotel_crm_check_dependency' );
function simple_hotel_crm_check_dependency() {
    if ( 'motopress' !== simple_hotel_crm_get_data_source() ) {
        return;
    }

    if ( ! function_exists( 'MPHB' ) || ! class_exists( 'HotelBookingPlugin' ) ) {
        add_action( 'admin_notices', function() {
            ?>
            <div class="notice notice-error">
                <p><?php esc_html_e( 'Simple Hotel CRM requires MotoPress Hotel Booking when the MotoPress source is selected.', 'simple-hotel-crm' ); ?></p>
            </div>
            <?php
        } );
        return;
    }
}

register_activation_hook( __FILE__, 'simple_hotel_crm_activate' );
add_action( 'plugins_loaded', 'simple_hotel_crm_maybe_upgrade' );

function simple_hotel_crm_activate() {
    simple_hotel_crm_install_tables();
}

function simple_hotel_crm_maybe_upgrade() {
    $installed_version = get_option( 'simple_hotel_crm_db_version' );
    if ( SIMPLE_HOTEL_CRM_DB_VERSION !== (string) $installed_version ) {
        simple_hotel_crm_install_tables();
    }
}

require_once plugin_dir_path( __FILE__ ) . 'includes/schema.php';

require_once plugin_dir_path( __FILE__ ) . 'includes/helpers.php';

require_once plugin_dir_path( __FILE__ ) . 'includes/calendar-data.php';

require_once plugin_dir_path( __FILE__ ) . 'includes/admin-pages.php';

require_once plugin_dir_path( __FILE__ ) . 'includes/crm-bookings.php';

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

function simple_hotel_crm_rest_save_booking_overlay( WP_REST_Request $request ) {
    global $wpdb;

    $reserved_room_id = absint( $request->get_param( 'reserved_room_id' ) );
    $booking_id       = absint( $request->get_param( 'booking_id' ) );
    $room_id          = absint( $request->get_param( 'room_id' ) );

    if ( $reserved_room_id <= 0 ) {
        return new WP_Error( 'missing_reserved_room', __( 'Missing reserved room ID.', 'simple-hotel-crm' ), [ 'status' => 400 ] );
    }

    $extras = simple_hotel_crm_evaluate_extras_formula( $request->get_param( 'extras_formula' ) );
    if ( ! $extras['valid'] ) {
        return new WP_Error( 'invalid_extras_formula', __( 'Extras formula only supports numbers joined with +.', 'simple-hotel-crm' ), [ 'status' => 400 ] );
    }

    $payload = [
        'booking_id'         => $booking_id,
        'reserved_room_id'   => $reserved_room_id,
        'room_id'            => $room_id,
        'booking_note'       => sanitize_textarea_field( (string) $request->get_param( 'booking_note' ) ),
        'extras_formula'     => $extras['formula'],
        'extras_total'       => $extras['total'],
        'manual_guest_name'  => sanitize_text_field( (string) $request->get_param( 'manual_guest_name' ) ),
        'manual_adults'      => '' === (string) $request->get_param( 'manual_adults' ) ? null : max( 0, intval( $request->get_param( 'manual_adults' ) ) ),
        'manual_children'    => '' === (string) $request->get_param( 'manual_children' ) ? null : max( 0, intval( $request->get_param( 'manual_children' ) ) ),
        'manual_tarif'       => simple_hotel_crm_normalize_decimal( $request->get_param( 'manual_tarif' ) ),
        'manual_commission'  => simple_hotel_crm_normalize_decimal( $request->get_param( 'manual_commission' ) ),
    ];

    $table = simple_hotel_crm_booking_overlay_table();
    $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE reserved_room_id = %d", $reserved_room_id ) );

    $formats = [ '%d', '%d', '%d', '%s', '%s', '%f', '%s', '%d', '%d', '%f', '%f' ];
    if ( $existing ) {
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
