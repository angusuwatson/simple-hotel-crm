<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function simple_hotel_crm_user_can_access() {
    return is_user_logged_in() && current_user_can( 'manage_options' );
}

function simple_hotel_crm_enqueue_shared_assets( $context = 'frontend' ) {
    wp_register_style( 'simple-hotel-crm', plugin_dir_url( __FILE__ ) . 'assets/style.css', [], SIMPLE_HOTEL_CRM_VERSION );
    wp_enqueue_style( 'simple-hotel-crm' );

    wp_register_script( 'simple-hotel-crm', plugin_dir_url( __FILE__ ) . 'assets/calendar-navigation.js', [ 'jquery' ], SIMPLE_HOTEL_CRM_VERSION, true );
    wp_localize_script( 'simple-hotel-crm', 'simpleHotelCrm', [
        'restUrl'        => esc_url_raw( rest_url( 'simple-hotel-crm/v1/table' ) ),
        'dailyNotesUrl'  => esc_url_raw( rest_url( 'simple-hotel-crm/v1/daily-note' ) ),
        'bookingUrl'     => esc_url_raw( rest_url( 'simple-hotel-crm/v1/booking-overlay' ) ),
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

function simple_hotel_crm_get_locked_booking_statuses() {
    if ( function_exists( 'MPHB' ) && method_exists( MPHB()->postTypes()->booking()->statuses(), 'getLockedRoomStatuses' ) ) {
        return MPHB()->postTypes()->booking()->statuses()->getLockedRoomStatuses();
    }

    return [ 'confirmed', 'pending', 'pending-user', 'pending-payment' ];
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

function simple_hotel_crm_extract_tarif_from_room_breakdown( $room_breakdown ) {
    if ( ! is_array( $room_breakdown ) ) {
        return '';
    }

    if ( isset( $room_breakdown['room']['discount_total'] ) && '' !== $room_breakdown['room']['discount_total'] ) {
        return (float) $room_breakdown['room']['discount_total'];
    }
    if ( isset( $room_breakdown['room']['total'] ) && '' !== $room_breakdown['room']['total'] ) {
        return (float) $room_breakdown['room']['total'];
    }
    if ( isset( $room_breakdown['discount_total'] ) && '' !== $room_breakdown['discount_total'] ) {
        return (float) $room_breakdown['discount_total'];
    }
    if ( isset( $room_breakdown['total'] ) && '' !== $room_breakdown['total'] ) {
        return (float) $room_breakdown['total'];
    }

    return '';
}

function simple_hotel_crm_extract_tarif( $booking, $reserved_room ) {
    $tarif = '';

    if ( $reserved_room && method_exists( $reserved_room, 'getLastRoomPriceBreakdown' ) ) {
        $tarif = simple_hotel_crm_extract_tarif_from_room_breakdown( $reserved_room->getLastRoomPriceBreakdown() );
        if ( '' !== $tarif ) {
            return $tarif;
        }
    }

    if ( class_exists( '\MPHB\Utils\PriceBreakdownHelper' ) && method_exists( '\MPHB\Utils\PriceBreakdownHelper', 'getLastRoomPriceBreakdown' ) ) {
        $tarif = simple_hotel_crm_extract_tarif_from_room_breakdown( \MPHB\Utils\PriceBreakdownHelper::getLastRoomPriceBreakdown( $reserved_room ) );
        if ( '' !== $tarif ) {
            return $tarif;
        }
    }

    if ( $reserved_room ) {
        $reserved_room_id = method_exists( $reserved_room, 'getId' ) ? (int) $reserved_room->getId() : 0;
        if ( $reserved_room_id > 0 ) {
            $reserved_room_price = get_post_meta( $reserved_room_id, '_mphb_reserved_room_price', true );
            if ( is_numeric( $reserved_room_price ) ) {
                return (float) $reserved_room_price;
            }
            $reserved_room_price = get_post_meta( $reserved_room_id, '_mphb_room_price', true );
            if ( is_numeric( $reserved_room_price ) ) {
                return (float) $reserved_room_price;
            }
        }
    }

    if ( $reserved_room && method_exists( $reserved_room, 'getPrice' ) ) {
        $price = $reserved_room->getPrice();
        if ( is_numeric( $price ) && $price > 0 ) {
            return (float) $price;
        }
    }

    if ( $reserved_room && method_exists( $reserved_room, 'getTotal' ) ) {
        $total = $reserved_room->getTotal();
        if ( is_numeric( $total ) && $total > 0 ) {
            return (float) $total;
        }
    }

    $breakdown = null;
    if ( $booking && method_exists( $booking, 'getLastPriceBreakdown' ) ) {
        $breakdown = $booking->getLastPriceBreakdown();
    }

    if ( ! is_array( $breakdown ) && $booking && method_exists( $booking, 'getId' ) ) {
        $breakdown = get_post_meta( $booking->getId(), '_mphb_booking_price_breakdown', true );
    }

    if ( is_array( $breakdown ) && ! empty( $breakdown['rooms'] ) && is_array( $breakdown['rooms'] ) ) {
        $reserved_room_id = $reserved_room && method_exists( $reserved_room, 'getId' ) ? (int) $reserved_room->getId() : 0;
        $room_id = $reserved_room && method_exists( $reserved_room, 'getRoomId' ) ? (int) $reserved_room->getRoomId() : 0;

        foreach ( $breakdown['rooms'] as $room_line ) {
            if ( isset( $room_line['reserved_room_id'] ) && (int) $room_line['reserved_room_id'] === $reserved_room_id ) {
                $tarif = simple_hotel_crm_extract_tarif_from_room_breakdown( $room_line );
                if ( '' !== $tarif ) {
                    return $tarif;
                }
            }
        }

        foreach ( $breakdown['rooms'] as $room_line ) {
            if ( isset( $room_line['room_id'] ) && (int) $room_line['room_id'] === $room_id ) {
                $tarif = simple_hotel_crm_extract_tarif_from_room_breakdown( $room_line );
                if ( '' !== $tarif ) {
                    return $tarif;
                }
            }
        }

        if ( 1 === count( $breakdown['rooms'] ) ) {
            $room_line = reset( $breakdown['rooms'] );
            $tarif = simple_hotel_crm_extract_tarif_from_room_breakdown( $room_line );
            if ( '' !== $tarif ) {
                return $tarif;
            }
        }
    }

    if ( $booking && method_exists( $booking, 'getId' ) ) {
        $booking_price = get_post_meta( $booking->getId(), '_mphb_booking_price', true );
        if ( is_numeric( $booking_price ) ) {
            return (float) $booking_price;
        }
    }

    return '';
}

function simple_hotel_crm_build_booking_payload( $booking, $reserved_room ) {
    $customer = method_exists( $booking, 'getCustomer' ) ? $booking->getCustomer() : null;

    $guest_name = '';
    if ( $reserved_room && method_exists( $reserved_room, 'getGuestName' ) ) {
        $guest_name = trim( (string) $reserved_room->getGuestName() );
    }

    if ( '' === $guest_name && $customer ) {
        $first_name = method_exists( $customer, 'getFirstName' ) ? (string) $customer->getFirstName() : '';
        $last_name  = method_exists( $customer, 'getLastName' ) ? (string) $customer->getLastName() : '';
        $guest_name = trim( $first_name . ' ' . $last_name );
    }

    $adults   = $reserved_room && method_exists( $reserved_room, 'getAdults' ) ? (int) $reserved_room->getAdults() : 0;
    $children = $reserved_room && method_exists( $reserved_room, 'getChildren' ) ? (int) $reserved_room->getChildren() : 0;

    $is_imported = method_exists( $booking, 'isImported' ) ? (bool) $booking->isImported() : ! empty( get_post_meta( $booking->getId(), '_mphb_sync_id', true ) );

    $tarif = '';
    if ( ! $is_imported ) {
        $tarif = simple_hotel_crm_extract_tarif( $booking, $reserved_room );
    }

    $reserved_room_id = $reserved_room && method_exists( $reserved_room, 'getId' ) ? (int) $reserved_room->getId() : 0;
    $overlay          = simple_hotel_crm_get_booking_overlay( $reserved_room_id );
    $overlay_adults   = isset( $overlay['manual_adults'] ) && '' !== $overlay['manual_adults'] ? (int) $overlay['manual_adults'] : null;
    $overlay_children = isset( $overlay['manual_children'] ) && '' !== $overlay['manual_children'] ? (int) $overlay['manual_children'] : null;
    $effective_adults = null !== $overlay_adults ? $overlay_adults : $adults;
    $effective_children = null !== $overlay_children ? $overlay_children : $children;

    $extras_formula = isset( $overlay['extras_formula'] ) ? (string) $overlay['extras_formula'] : '';
    $extras_total   = isset( $overlay['extras_total'] ) && '' !== $overlay['extras_total'] ? (float) $overlay['extras_total'] : null;

    $created_date = '';
    if ( method_exists( $booking, 'getPostId' ) ) {
        $created_timestamp = get_post_time( 'U', false, $booking->getPostId() );
        if ( $created_timestamp ) {
            $created_date = date_i18n( 'Y-m-d', $created_timestamp );
        }
    }
    if ( '' === $created_date && method_exists( $booking, 'getId' ) ) {
        $created_timestamp = get_post_time( 'U', false, $booking->getId() );
        if ( $created_timestamp ) {
            $created_date = date_i18n( 'Y-m-d', $created_timestamp );
        }
    }

    return (object) [
        'id'                => method_exists( $booking, 'getId' ) ? (int) $booking->getId() : 0,
        'status'            => method_exists( $booking, 'getStatus' ) ? (string) $booking->getStatus() : '',
        'check_in'          => method_exists( $booking, 'getCheckInDate' ) && $booking->getCheckInDate() ? $booking->getCheckInDate()->format( 'Y-m-d' ) : '',
        'check_out'         => method_exists( $booking, 'getCheckOutDate' ) && $booking->getCheckOutDate() ? $booking->getCheckOutDate()->format( 'Y-m-d' ) : '',
        'room_id'           => $reserved_room && method_exists( $reserved_room, 'getRoomId' ) ? (int) $reserved_room->getRoomId() : 0,
        'reserved_room_id'  => $reserved_room_id,
        'guest_name'        => isset( $overlay['manual_guest_name'] ) && '' !== trim( (string) $overlay['manual_guest_name'] ) ? trim( (string) $overlay['manual_guest_name'] ) : $guest_name,
        'phone'             => $customer && method_exists( $customer, 'getPhone' ) ? (string) $customer->getPhone() : '',
        'adults'            => $effective_adults,
        'children'          => $effective_children,
        'occupancy_str'     => simple_hotel_crm_format_occupancy( $effective_adults, $effective_children ),
        'channel'           => trim( ( $is_imported ? 'I' : 'W' ) . ( $created_date ? ' ' . $created_date : '' ) ),
        'channel_label'     => $is_imported ? 'Imported' : 'Website',
        'created_date'      => $created_date,
        'is_imported'       => $is_imported,
        'tarif'             => isset( $overlay['manual_tarif'] ) && '' !== $overlay['manual_tarif'] ? (float) $overlay['manual_tarif'] : $tarif,
        'commission'        => isset( $overlay['manual_commission'] ) && '' !== $overlay['manual_commission'] ? (float) $overlay['manual_commission'] : '',
        'extras_formula'    => $extras_formula,
        'extras_total'      => $extras_total,
        'booking_note'      => isset( $overlay['booking_note'] ) ? (string) $overlay['booking_note'] : '',
    ];
}

function simple_hotel_crm_get_data_source() {
    $source = get_option( 'simple_hotel_crm_booking_source', 'wp_sync' );
    return in_array( $source, [ 'motopress', 'wp_sync', 'external_pg' ], true ) ? $source : 'wp_sync';
}

function simple_hotel_crm_get_external_db_settings() {
    return [
        'host'     => (string) get_option( 'simple_hotel_crm_external_pg_host', '' ),
        'port'     => (string) get_option( 'simple_hotel_crm_external_pg_port', '5432' ),
        'dbname'   => (string) get_option( 'simple_hotel_crm_external_pg_dbname', '' ),
        'user'     => (string) get_option( 'simple_hotel_crm_external_pg_user', '' ),
        'password' => (string) get_option( 'simple_hotel_crm_external_pg_password', '' ),
        'sslmode'  => (string) get_option( 'simple_hotel_crm_external_pg_sslmode', 'disable' ),
    ];
}

function simple_hotel_crm_get_external_pg_connection() {
    static $connection = null;
    static $attempted = false;

    if ( $attempted ) {
        return $connection;
    }

    $attempted = true;

    if ( ! function_exists( 'pg_connect' ) ) {
        $connection = new WP_Error( 'missing_pgsql_extension', __( 'The pgsql PHP extension is not available on this WordPress server.', 'simple-hotel-crm' ) );
        return $connection;
    }

    $settings = simple_hotel_crm_get_external_db_settings();
    foreach ( [ 'host', 'port', 'dbname', 'user' ] as $required_key ) {
        if ( '' === $settings[ $required_key ] ) {
            $connection = new WP_Error( 'missing_pg_settings', __( 'External PostgreSQL settings are incomplete.', 'simple-hotel-crm' ) );
            return $connection;
        }
    }

    $connection_string = sprintf(
        'host=%s port=%s dbname=%s user=%s password=%s sslmode=%s connect_timeout=5',
        $settings['host'],
        $settings['port'],
        $settings['dbname'],
        $settings['user'],
        $settings['password'],
        $settings['sslmode'] ?: 'disable'
    );

    $connection = @pg_connect( $connection_string );
    if ( ! $connection ) {
        $connection = new WP_Error( 'pg_connect_failed', __( 'Could not connect to the external PostgreSQL database.', 'simple-hotel-crm' ) );
    }

    return $connection;
}

function simple_hotel_crm_get_room_colors() {
    return [
        'ANE' => '#cc99ff',
        'DEL' => '#b4c7e7',
        'LYS' => '#a9d18e',
        'TOU' => '#ffe699',
        'TUL' => '#f4b183',
        'COQ' => '#cccccc',
    ];
}

function simple_hotel_crm_get_room_sort_order( $room_code, $room_name ) {
    $room_code = strtoupper( (string) $room_code );
    $map = [ 'ANE' => 1, 'DEL' => 2, 'LYS' => 3, 'TOU' => 4, 'TUL' => 5, 'COQ' => 6 ];

    if ( isset( $map[ $room_code ] ) ) {
        return $map[ $room_code ];
    }

    $room_name = remove_accents( strtolower( (string) $room_name ) );
    $name_map = [ 'anemone' => 1, 'delphinium' => 2, 'lys' => 3, 'tournesol' => 4, 'tulipe' => 5, 'coquelicot' => 6 ];
    return $name_map[ $room_name ] ?? 999;
}

function simple_hotel_crm_format_channel_code( $source_channel, $created_date = '' ) {
    $source_channel = (string) $source_channel;
    $created_date   = (string) $created_date;

    $prefix_map = [
        'booking_com' => 'B',
        'direct'      => 'W',
        'motopress'   => 'W',
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

