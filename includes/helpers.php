<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function simple_hotel_crm_user_can_access() {
    return is_user_logged_in() && current_user_can( 'manage_options' );
}

function simple_hotel_crm_enqueue_shared_assets( $context = 'frontend' ) {
    wp_register_style( 'simple-hotel-crm', plugin_dir_url( dirname( __DIR__ ) . '/simple-hotel-crm.php' ) . 'assets/style.css', [], SIMPLE_HOTEL_CRM_VERSION );
    wp_enqueue_style( 'simple-hotel-crm' );

    wp_register_script( 'simple-hotel-crm', plugin_dir_url( dirname( __DIR__ ) . '/simple-hotel-crm.php' ) . 'assets/calendar-navigation.js', [ 'jquery' ], SIMPLE_HOTEL_CRM_VERSION, true );
    wp_localize_script( 'simple-hotel-crm', 'simpleHotelCrm', [
        'restUrl'        => esc_url_raw( rest_url( 'simple-hotel-crm/v1/table' ) ),
        'dailyNotesUrl'  => esc_url_raw( rest_url( 'simple-hotel-crm/v1/daily-note' ) ),
        'quickBookingUrl'=> esc_url_raw( rest_url( 'simple-hotel-crm/v1/quick-booking' ) ),
        'nonce'          => wp_create_nonce( 'wp_rest' ),
        'context'        => $context,
        'adminPageUrl'   => admin_url( 'admin.php?page=simple-hotel-crm' ),
    ] );
    wp_enqueue_script( 'simple-hotel-crm' );
}

add_action( 'wp_enqueue_scripts', 'simple_hotel_crm_enqueue_assets' );
function simple_hotel_crm_enqueue_assets() {
    if ( ! simple_hotel_crm_user_can_access() ) {
        return;
    }

    $post = get_post();
    if ( ! $post || ! has_shortcode( $post->post_content, 'simple_hotel_crm' ) ) {
        return;
    }

    simple_hotel_crm_enqueue_shared_assets( 'frontend' );
}

add_action( 'admin_enqueue_scripts', 'simple_hotel_crm_enqueue_admin_assets' );
function simple_hotel_crm_enqueue_admin_assets( $hook_suffix ) {
    if ( ! simple_hotel_crm_user_can_access() ) {
        return;
    }

    if ( 'toplevel_page_simple-hotel-crm' !== $hook_suffix ) {
        return;
    }

    simple_hotel_crm_enqueue_shared_assets( 'admin' );
}

function simple_hotel_crm_get_daily_notes_for_month( $year, $month ) {
    global $wpdb;

    $table = simple_hotel_crm_daily_notes_table();
    $start = sprintf( '%04d-%02d-01', $year, $month );
    $end   = gmdate( 'Y-m-t', strtotime( $start ) );

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT note_date, note_text FROM {$table} WHERE note_date BETWEEN %s AND %s",
            $start,
            $end
        ),
        ARRAY_A
    );

    $notes = [];
    foreach ( $rows as $row ) {
        $notes[ $row['note_date'] ] = (string) $row['note_text'];
    }

    return $notes;
}

function simple_hotel_crm_get_booking_overlay( $reserved_room_id ) {
    static $cache = [];

    $reserved_room_id = (int) $reserved_room_id;
    if ( $reserved_room_id <= 0 ) {
        return [];
    }

    if ( isset( $cache[ $reserved_room_id ] ) ) {
        return $cache[ $reserved_room_id ];
    }

    global $wpdb;
    $table = simple_hotel_crm_booking_overlay_table();
    $row   = $wpdb->get_row(
        $wpdb->prepare( "SELECT * FROM {$table} WHERE reserved_room_id = %d LIMIT 1", $reserved_room_id ),
        ARRAY_A
    );

    $cache[ $reserved_room_id ] = is_array( $row ) ? $row : [];
    return $cache[ $reserved_room_id ];
}

function simple_hotel_crm_copy_booking_overlay( $from_reserved_room_id, $to_reserved_room_id, $booking_id = 0, $room_id = 0 ) {
    global $wpdb;

    $from_reserved_room_id = (int) $from_reserved_room_id;
    $to_reserved_room_id   = (int) $to_reserved_room_id;
    if ( $from_reserved_room_id <= 0 || $to_reserved_room_id <= 0 || $from_reserved_room_id === $to_reserved_room_id ) {
        return false;
    }

    $table = simple_hotel_crm_booking_overlay_table();
    $overlay = $wpdb->get_row(
        $wpdb->prepare( "SELECT * FROM {$table} WHERE reserved_room_id = %d LIMIT 1", $from_reserved_room_id ),
        ARRAY_A
    );
    if ( ! is_array( $overlay ) || empty( $overlay ) ) {
        return false;
    }

    unset( $overlay['id'] );
    $overlay['reserved_room_id'] = $to_reserved_room_id;
    if ( $booking_id > 0 ) {
        $overlay['booking_id'] = (int) $booking_id;
    }
    if ( $room_id > 0 ) {
        $overlay['room_id'] = (int) $room_id;
    }

    $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE reserved_room_id = %d", $to_reserved_room_id ) );
    $formats = [ '%d', '%d', '%d', '%s', '%s', '%f', '%s', '%d', '%d', '%f', '%f', '%s', '%s' ];
    if ( $existing ) {
        return false !== $wpdb->update( $table, $overlay, [ 'reserved_room_id' => $to_reserved_room_id ], $formats, [ '%d' ] );
    }

    return false !== $wpdb->insert( $table, $overlay, $formats );
}

function simple_hotel_crm_delete_booking_overlay( $reserved_room_id ) {
    global $wpdb;

    $reserved_room_id = (int) $reserved_room_id;
    if ( $reserved_room_id <= 0 ) {
        return false;
    }

    return false !== $wpdb->delete( simple_hotel_crm_booking_overlay_table(), [ 'reserved_room_id' => $reserved_room_id ], [ '%d' ] );
}

function simple_hotel_crm_format_occupancy( $adults, $children ) {
    $adults   = max( 0, (int) $adults );
    $children = max( 0, (int) $children );
    $parts    = [];

    if ( $adults > 0 ) {
        $parts[] = sprintf( '%dA', $adults );
    }
    if ( $children > 0 ) {
        $parts[] = sprintf( '%dC', $children );
    }
    if ( empty( $parts ) ) {
        $parts[] = '0';
    }

    return implode( ' ', $parts );
}

function simple_hotel_crm_normalize_decimal( $value ) {
    if ( '' === $value || null === $value ) {
        return null;
    }

    $value = str_replace( [ '€', ' ' ], '', (string) $value );
    $value = str_replace( ',', '.', $value );

    return is_numeric( $value ) ? round( (float) $value, 2 ) : null;
}

function simple_hotel_crm_evaluate_extras_formula( $formula ) {
    $formula = trim( (string) $formula );
    if ( '' === $formula ) {
        return [ 'formula' => '', 'total' => null, 'valid' => true ];
    }

    if ( '=' === substr( $formula, 0, 1 ) ) {
        $formula = substr( $formula, 1 );
    }

    $formula = preg_replace( '/\s+/', '', $formula );
    if ( '' === $formula ) {
        return [ 'formula' => '', 'total' => null, 'valid' => true ];
    }

    if ( ! preg_match( '/^\d+(?:[\.,]\d+)?(?:\+\d+(?:[\.,]\d+)?)*$/', $formula ) ) {
        return [ 'formula' => '=' . $formula, 'total' => null, 'valid' => false ];
    }

    $parts = explode( '+', str_replace( ',', '.', $formula ) );
    $total = 0.0;
    foreach ( $parts as $part ) {
        $total += (float) $part;
    }

    return [
        'formula' => '=' . $formula,
        'total'   => round( $total, 2 ),
        'valid'   => true,
    ];
}

function simple_hotel_crm_get_room_display_number( $room_code, $fallback = '' ) {
    $room_code = strtoupper( trim( (string) $room_code ) );
    $room_numbers = [
        'ANE' => 1,
        'DEL' => 2,
        'LYS' => 3,
        'TOU' => 4,
        'TUL' => 5,
        'COQ' => 0,
    ];

    if ( isset( $room_numbers[ $room_code ] ) ) {
        return (string) $room_numbers[ $room_code ];
    }

    return '' !== (string) $fallback ? (string) $fallback : (string) $room_code;
}

function simple_hotel_crm_find_crm_room_id( $room_identifier = 0, $fallbacks = [] ) {
    global $wpdb;

    $rooms_table = simple_hotel_crm_rooms_table();
    $room_identifier = (int) $room_identifier;
    if ( $room_identifier > 0 ) {
        $room_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$rooms_table} WHERE id = %d LIMIT 1", $room_identifier ) );
        if ( $room_id > 0 ) {
            return $room_id;
        }

        $room_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$rooms_table} WHERE sync_room_id = %d LIMIT 1", $room_identifier ) );
        if ( $room_id > 0 ) {
            return $room_id;
        }

        $room_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$rooms_table} WHERE external_room_id = %d LIMIT 1", $room_identifier ) );
        if ( $room_id > 0 ) {
            return $room_id;
        }
    }

    $room_code = strtoupper( trim( (string) ( $fallbacks['room_code'] ?? '' ) ) );
    $room_name = trim( (string) ( $fallbacks['room_name'] ?? '' ) );
    if ( '' !== $room_code ) {
        $room_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$rooms_table} WHERE room_code = %s LIMIT 1", $room_code ) );
        if ( $room_id > 0 ) {
            return $room_id;
        }
    }
    if ( '' !== $room_name ) {
        $room_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$rooms_table} WHERE room_name = %s LIMIT 1", $room_name ) );
        if ( $room_id > 0 ) {
            return $room_id;
        }
    }

    return 0;
}

function simple_hotel_crm_format_channel_code( $source_channel, $created_date = '' ) {
    $source_channel = (string) $source_channel;
    $created_date   = (string) $created_date;

    $prefix_map = [
        'booking_com' => 'B',
        'direct'      => 'W',
        'website'     => 'W',
        'email'       => 'E',
        'telephone'   => 'T',
        'phone'       => 'T',
    ];

    $prefix = $prefix_map[ $source_channel ] ?? strtoupper( substr( preg_replace( '/[^a-z]/i', '', $source_channel ), 0, 1 ) );
    if ( '' === $prefix ) {
        $prefix = '?';
    }

    return trim( $prefix . ( $created_date ? ' ' . $created_date : '' ) );
}

