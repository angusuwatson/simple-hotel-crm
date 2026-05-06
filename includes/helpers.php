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

function simple_hotel_crm_table_has_column( $table, $column ) {
    global $wpdb;

    $table = preg_replace( '/[^A-Za-z0-9_]/', '', (string) $table );
    $column = preg_replace( '/[^A-Za-z0-9_]/', '', (string) $column );
    if ( '' === $table || '' === $column ) {
        return false;
    }

    $result = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", $column ) );
    return ! empty( $result );
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

function simple_hotel_crm_normalize_color_value( $value ) {
    $value = trim( (string) $value );
    if ( '' === $value ) {
        return null;
    }

    $hex = sanitize_hex_color( $value );
    if ( $hex ) {
        return strtolower( $hex );
    }

    if ( preg_match( '/^rgb\(\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*\)$/i', $value, $matches ) ) {
        $r = max( 0, min( 255, (int) $matches[1] ) );
        $g = max( 0, min( 255, (int) $matches[2] ) );
        $b = max( 0, min( 255, (int) $matches[3] ) );
        return sprintf( '#%02x%02x%02x', $r, $g, $b );
    }

    return null;
}

function simple_hotel_crm_hex_to_rgb_string( $value ) {
    $hex = simple_hotel_crm_normalize_color_value( $value );
    if ( ! $hex ) {
        return 'rgb(204, 204, 204)';
    }

    $hex = ltrim( $hex, '#' );
    return sprintf( 'rgb(%d, %d, %d)', hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
}

function simple_hotel_crm_adjust_color_brightness( $hex, $steps ) {
    $hex = ltrim( trim( (string) simple_hotel_crm_normalize_color_value( $hex ) ), '#' );
    if ( 6 !== strlen( $hex ) ) {
        return '#444444';
    }
    $steps = max( -255, min( 255, (int) $steps ) );
    $r = max( 0, min( 255, hexdec( substr( $hex, 0, 2 ) ) + $steps ) );
    $g = max( 0, min( 255, hexdec( substr( $hex, 2, 2 ) ) + $steps ) );
    $b = max( 0, min( 255, hexdec( substr( $hex, 4, 2 ) ) + $steps ) );
    return sprintf( '#%02x%02x%02x', $r, $g, $b );
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

function simple_hotel_crm_get_channel_commission_percent( $source_channel ) {
    $source_channel = (string) $source_channel;
    if ( 'booking_com' === $source_channel ) {
        return max( 0, min( 100, (float) get_option( 'simple_hotel_crm_booking_com_commission_percent', 15 ) ) );
    }

    return 0.0;
}

function simple_hotel_crm_calculate_channel_commission( $source_channel, $room_rate_amount ) {
    $room_rate_amount = max( 0, (float) $room_rate_amount );
    $percent = simple_hotel_crm_get_channel_commission_percent( $source_channel );
    if ( $percent <= 0 || $room_rate_amount <= 0 ) {
        return 0.0;
    }

    return round( $room_rate_amount * ( $percent / 100 ), 2 );
}

function simple_hotel_crm_get_booking_com_ics_room_urls() {
    $urls = get_option( 'simple_hotel_crm_booking_com_ics_room_urls', [] );
    return is_array( $urls ) ? $urls : [];
}

function simple_hotel_crm_parse_ics_date_value( $value ) {
    $value = trim( (string) $value );
    if ( '' === $value ) {
        return '';
    }

    if ( preg_match( '/^(\d{4})(\d{2})(\d{2})$/', $value, $matches ) ) {
        return $matches[1] . '-' . $matches[2] . '-' . $matches[3];
    }
    if ( preg_match( '/^(\d{4})(\d{2})(\d{2})T/', $value, $matches ) ) {
        return $matches[1] . '-' . $matches[2] . '-' . $matches[3];
    }

    return '';
}

function simple_hotel_crm_parse_ics_content( $content ) {
    $content = str_replace( [ "\r\n", "\r" ], "\n", (string) $content );
    $raw_lines = explode( "\n", $content );
    $lines = [];
    foreach ( $raw_lines as $raw_line ) {
        if ( '' !== $raw_line && isset( $raw_line[0] ) && ( ' ' === $raw_line[0] || "\t" === $raw_line[0] ) && ! empty( $lines ) ) {
            $lines[ count( $lines ) - 1 ] .= substr( $raw_line, 1 );
        } else {
            $lines[] = $raw_line;
        }
    }

    $events = [];
    $event = null;
    foreach ( $lines as $line ) {
        $line = trim( $line );
        if ( 'BEGIN:VEVENT' === $line ) {
            $event = [];
            continue;
        }
        if ( 'END:VEVENT' === $line ) {
            if ( is_array( $event ) ) {
                $events[] = $event;
            }
            $event = null;
            continue;
        }
        if ( ! is_array( $event ) || false === strpos( $line, ':' ) ) {
            continue;
        }

        list( $property, $value ) = explode( ':', $line, 2 );
        $property = strtoupper( trim( (string) preg_replace( '/;.*$/', '', $property ) ) );
        $event[ $property ] = trim( (string) $value );
    }

    return $events;
}

function simple_hotel_crm_import_booking_com_ics_feeds() {
    global $wpdb;

    $rooms_table = simple_hotel_crm_rooms_table();
    $sync_bookings_table = simple_hotel_crm_sync_bookings_table();
    $room_urls = simple_hotel_crm_get_booking_com_ics_room_urls();
    $summary = [ 'feeds' => 0, 'events' => 0, 'staged' => 0, 'skipped' => 0, 'errors' => [] ];

    if ( empty( $room_urls ) ) {
        $summary['errors'][] = __( 'No Booking.com ICS URLs configured.', 'simple-hotel-crm' );
        return $summary;
    }

    foreach ( $room_urls as $room_id => $url ) {
        $room_id = absint( $room_id );
        $url = esc_url_raw( trim( (string) $url ) );
        if ( $room_id <= 0 || '' === $url ) {
            continue;
        }

        $room = $wpdb->get_row( $wpdb->prepare( "SELECT id, sync_room_id, external_room_id, room_code, room_name FROM {$rooms_table} WHERE id = %d LIMIT 1", $room_id ), ARRAY_A );
        if ( ! $room ) {
            $summary['errors'][] = sprintf( __( 'ICS room mapping missing CRM room %d.', 'simple-hotel-crm' ), $room_id );
            continue;
        }

        $response = wp_remote_get( $url, [ 'timeout' => 20 ] );
        if ( is_wp_error( $response ) ) {
            $summary['errors'][] = sprintf( __( 'ICS fetch failed for %1$s: %2$s', 'simple-hotel-crm' ), $room['room_name'], $response->get_error_message() );
            continue;
        }
        if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
            $summary['errors'][] = sprintf( __( 'ICS fetch failed for %1$s: HTTP %2$d', 'simple-hotel-crm' ), $room['room_name'], (int) wp_remote_retrieve_response_code( $response ) );
            continue;
        }

        $summary['feeds']++;
        $events = simple_hotel_crm_parse_ics_content( wp_remote_retrieve_body( $response ) );
        foreach ( $events as $event ) {
            $summary['events']++;
            $check_in = simple_hotel_crm_parse_ics_date_value( $event['DTSTART'] ?? '' );
            $check_out = simple_hotel_crm_parse_ics_date_value( $event['DTEND'] ?? '' );
            if ( '' === $check_in || '' === $check_out ) {
                $summary['skipped']++;
                continue;
            }

            $uid = trim( (string) ( $event['UID'] ?? '' ) );
            $summary_text = trim( (string) ( $event['SUMMARY'] ?? '' ) );
            $description = trim( (string) ( $event['DESCRIPTION'] ?? '' ) );
            $status = 'confirmed';
            $source_booking_id = '' !== $uid ? $uid : md5( $room_id . '|' . $check_in . '|' . $check_out . '|' . $summary_text );
            $external_booking_id = abs( crc32( $source_booking_id ) );
            $external_booking_room_id = abs( crc32( $room_id . '|' . $source_booking_id ) );
            $guest_name = '' !== $summary_text ? $summary_text : ( '' !== $description ? $description : __( 'Booking.com guest', 'simple-hotel-crm' ) );
            $nights = max( 1, (int) round( ( strtotime( $check_out ) - strtotime( $check_in ) ) / DAY_IN_SECONDS ) );

            for ( $i = 0; $i < $nights; $i++ ) {
                $stay_date = gmdate( 'Y-m-d', strtotime( $check_in . ' +' . $i . ' day' ) );
                $exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$sync_bookings_table} WHERE external_booking_room_id = %d AND stay_date = %s LIMIT 1", $external_booking_room_id, $stay_date ) );
                if ( $exists > 0 ) {
                    $summary['skipped']++;
                    continue;
                }
                $inserted = $wpdb->insert(
                    $sync_bookings_table,
                    [
                        'external_booking_id' => $external_booking_id,
                        'external_booking_room_id' => $external_booking_room_id,
                        'external_room_id' => (int) ( $room['external_room_id'] ?? 0 ),
                        'room_sync_id' => (int) ( $room['sync_room_id'] ?? 0 ),
                        'status_code' => $status,
                        'check_in' => $check_in,
                        'check_out' => $check_out,
                        'stay_date' => $stay_date,
                        'guest_count' => 0,
                        'adults' => 0,
                        'children' => 0,
                        'babies' => 0,
                        'total_amount' => 0,
                        'room_amount' => 0,
                        'extras_amount' => 0,
                        'tourist_tax_amount' => 0,
                        'room_count' => 1,
                        'source_channel' => 'booking_com',
                        'source_booking_id' => $source_booking_id,
                        'channel_label' => 'Booking.com',
                        'guest_name' => $guest_name,
                        'phone' => '',
                        'import_notes' => $description,
                        'source_created_at' => current_time( 'mysql' ),
                    ],
                    [ '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%f', '%f', '%f', '%f', '%d', '%s', '%s', '%s', '%s', '%s' ]
                );
                if ( false !== $inserted ) {
                    $summary['staged']++;
                }
            }
        }
    }

    simple_hotel_crm_import_sync_data_to_crm();
    simple_hotel_crm_clear_calendar_cache();
    return $summary;
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

