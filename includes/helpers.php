<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function simple_hotel_crm_user_can_access( $request = null ) {
    if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
        return true;
    }
    $stored_key = get_option( 'simple_hotel_crm_dashboard_api_key', '' );
    if ( empty( $stored_key ) ) {
        return false;
    }
    if ( $request instanceof WP_REST_Request ) {
        $request_key = (string) $request->get_param( 'api_key' );
        if ( ! empty( $request_key ) && hash_equals( $stored_key, $request_key ) ) {
            return true;
        }
    }
    $header_key = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if ( ! empty( $header_key ) && hash_equals( $stored_key, $header_key ) ) {
        return true;
    }
    $request_key = $_GET['api_key'] ?? '';
    if ( ! empty( $request_key ) && hash_equals( $stored_key, $request_key ) ) {
        return true;
    }
    return false;
}

function simple_hotel_crm_enqueue_shared_assets( $context = 'frontend' ) {
    wp_register_style( 'simple-hotel-crm', plugin_dir_url( dirname( __DIR__ ) . '/simple-hotel-crm.php' ) . 'assets/style.css', [], SIMPLE_HOTEL_CRM_VERSION );
    wp_enqueue_style( 'simple-hotel-crm' );

    wp_register_script( 'simple-hotel-crm', plugin_dir_url( dirname( __DIR__ ) . '/simple-hotel-crm.php' ) . 'assets/calendar-navigation.js', [ 'jquery' ], SIMPLE_HOTEL_CRM_VERSION, true );
    wp_localize_script( 'simple-hotel-crm', 'simpleHotelCrm', [
        'restUrl'        => esc_url_raw( rest_url( 'simple-hotel-crm/v1/table' ) ),
        'dailyNotesUrl'  => esc_url_raw( rest_url( 'simple-hotel-crm/v1/daily-note' ) ),
        'quickBookingUrl'=> esc_url_raw( rest_url( 'simple-hotel-crm/v1/quick-booking' ) ),
        'roomDayNoteUrl'=> esc_url_raw( rest_url( 'simple-hotel-crm/v1/room-day-note' ) ),
        'roomDayExtrasUrl'=> esc_url_raw( rest_url( 'simple-hotel-crm/v1/room-day-extras' ) ),
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

    $normalized = str_replace( ',', '.', $formula );
    if ( ! preg_match( '/^[0-9+\-*\/().]+$/', $normalized ) ) {
        return [ 'formula' => '=' . $formula, 'total' => null, 'valid' => false ];
    }

    preg_match_all( '/\d+(?:\.\d+)?|[+\-*\/()]?/', $normalized, $matches );
    $tokens = array_values( array_filter( $matches[0], static function( $token ) { return '' !== $token; } ) );
    if ( empty( $tokens ) ) {
        return [ 'formula' => '=' . $formula, 'total' => null, 'valid' => false ];
    }

    $values = [];
    $ops = [];
    $precedence = [ '+' => 1, '-' => 1, '*' => 2, '/' => 2 ];
    $apply = static function( &$values, $op ) {
        if ( count( $values ) < 2 ) {
            return false;
        }
        $b = array_pop( $values );
        $a = array_pop( $values );
        switch ( $op ) {
            case '+': $values[] = $a + $b; return true;
            case '-': $values[] = $a - $b; return true;
            case '*': $values[] = $a * $b; return true;
            case '/':
                if ( abs( $b ) < 0.0000001 ) {
                    return false;
                }
                $values[] = $a / $b;
                return true;
        }
        return false;
    };

    $prev = null;
    foreach ( $tokens as $token ) {
        if ( preg_match( '/^\d+(?:\.\d+)?$/', $token ) ) {
            $values[] = (float) $token;
            $prev = 'number';
            continue;
        }
        if ( '(' === $token ) {
            $ops[] = $token;
            $prev = '(';
            continue;
        }
        if ( ')' === $token ) {
            while ( ! empty( $ops ) && '(' !== end( $ops ) ) {
                if ( ! $apply( $values, array_pop( $ops ) ) ) {
                    return [ 'formula' => '=' . $formula, 'total' => null, 'valid' => false ];
                }
            }
            if ( empty( $ops ) || '(' !== end( $ops ) ) {
                return [ 'formula' => '=' . $formula, 'total' => null, 'valid' => false ];
            }
            array_pop( $ops );
            $prev = ')';
            continue;
        }
        if ( in_array( $token, [ '+', '-', '*', '/' ], true ) ) {
            if ( null === $prev || in_array( $prev, [ '(', 'op' ], true ) ) {
                if ( '-' === $token ) {
                    $values[] = 0.0;
                } else {
                    return [ 'formula' => '=' . $formula, 'total' => null, 'valid' => false ];
                }
            }
            while ( ! empty( $ops ) && isset( $precedence[ end( $ops ) ] ) && $precedence[ end( $ops ) ] >= $precedence[ $token ] ) {
                if ( ! $apply( $values, array_pop( $ops ) ) ) {
                    return [ 'formula' => '=' . $formula, 'total' => null, 'valid' => false ];
                }
            }
            $ops[] = $token;
            $prev = 'op';
            continue;
        }
        return [ 'formula' => '=' . $formula, 'total' => null, 'valid' => false ];
    }

    while ( ! empty( $ops ) ) {
        $op = array_pop( $ops );
        if ( '(' === $op || ')' === $op || ! $apply( $values, $op ) ) {
            return [ 'formula' => '=' . $formula, 'total' => null, 'valid' => false ];
        }
    }

    if ( 1 !== count( $values ) ) {
        return [ 'formula' => '=' . $formula, 'total' => null, 'valid' => false ];
    }

    return [
        'formula' => '=' . $formula,
        'total'   => round( (float) $values[0], 2 ),
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

function simple_hotel_crm_extract_booking_com_ics_group_key( $event, $room_id = 0 ) {
    $uid = trim( (string) ( $event['UID'] ?? '' ) );
    $summary = trim( (string) ( $event['SUMMARY'] ?? '' ) );
    $description = trim( (string) ( $event['DESCRIPTION'] ?? '' ) );
    $haystacks = [ $uid, $summary, $description ];

    foreach ( $haystacks as $text ) {
        if ( preg_match( '/\b(\d{7,})\b/', $text, $matches ) ) {
            return (string) $matches[1];
        }
    }

    if ( '' !== $uid ) {
        $normalized_uid = preg_replace( '/(^|[^a-z0-9])' . preg_quote( (string) $room_id, '/' ) . '([^a-z0-9]|$)/i', '$1$2', strtolower( $uid ) );
        $normalized_uid = preg_replace( '/[^a-z0-9]+/i', '-', (string) $normalized_uid );
        $normalized_uid = trim( (string) $normalized_uid, '-' );
        if ( '' !== $normalized_uid ) {
            return $normalized_uid;
        }
        return $uid;
    }

    return md5( wp_json_encode( $event ) );
}

function simple_hotel_crm_clear_booking_com_ics_staged_rows() {
    global $wpdb;

    $sync_bookings_table = simple_hotel_crm_sync_bookings_table();
    $total_deleted = 0;
    while ( true ) {
        $count = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$sync_bookings_table} WHERE source_channel = %s AND source_booking_id <> %s LIMIT 500", 'booking_com', '' ) );
        if ( $count <= 0 ) {
            break;
        }
        $total_deleted += $count;
    }
    return $total_deleted;
}

function simple_hotel_crm_mark_missing_booking_com_bookings_cancelled( array $seen_uids ) {
    global $wpdb;

    $bookings_table = simple_hotel_crm_bookings_table();
    $seen_uids = array_values( array_filter( array_map( 'trim', $seen_uids ) ) );
    if ( empty( $seen_uids ) ) {
        return 0;
    }
    $placeholders = implode( ',', array_fill( 0, count( $seen_uids ), '%s' ) );
    $query = $wpdb->prepare(
        "UPDATE {$bookings_table}
         SET status_code = 'cancelled'
         WHERE source_channel = %s
           AND is_deleted = 0
           AND status_code = 'confirmed'
           AND internal_notes LIKE %s
           AND internal_notes NOT LIKE '%[MERGED_ARCHIVE]%'
           AND source_booking_id <> ''
           AND check_out_date >= %s
           AND source_booking_id NOT IN ({$placeholders})",
        array_merge( [ 'booking_com', '%[ICS_SKELETON]%', current_time( 'Y-m-d' ) ], $seen_uids )
    );
    return (int) $wpdb->query( $query );
}

function simple_hotel_crm_import_booking_com_ics_feeds() {
    global $wpdb;

    $rooms_table = simple_hotel_crm_rooms_table();
    $sync_bookings_table = simple_hotel_crm_sync_bookings_table();
    simple_hotel_crm_clear_booking_com_ics_staged_rows();
    $room_urls = simple_hotel_crm_get_booking_com_ics_room_urls();
    $summary = [ 'feeds' => 0, 'events' => 0, 'staged' => 0, 'skipped' => 0, 'errors' => [], 'seen_uids' => [] ];

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
            if ( ! in_array( $source_booking_id, $summary['seen_uids'], true ) ) {
                $summary['seen_uids'][] = $source_booking_id;
            }
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
                $staged_external_room_id = (int) ( $room['external_room_id'] ?? 0 );
                $staged_room_sync_id = (int) ( $room['sync_room_id'] ?? 0 );
                if ( $staged_external_room_id <= 0 ) {
                    $staged_external_room_id = (int) $room['id'];
                }
                if ( $staged_room_sync_id <= 0 ) {
                    $staged_room_sync_id = (int) $room['id'];
                }

                $inserted = $wpdb->insert(
                    $sync_bookings_table,
                    [
                        'external_booking_id' => $external_booking_id,
                        'external_booking_room_id' => $external_booking_room_id,
                        'external_room_id' => $staged_external_room_id,
                        'room_sync_id' => $staged_room_sync_id,
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
    $summary['cancelled'] = simple_hotel_crm_mark_missing_booking_com_bookings_cancelled( $summary['seen_uids'] );
    simple_hotel_crm_clear_calendar_cache();
    return $summary;
}

function simple_hotel_crm_reset_crm_data() {
    global $wpdb;

    $tables = [
        simple_hotel_crm_booking_room_nights_table(),
        simple_hotel_crm_booking_rooms_table(),
        simple_hotel_crm_booking_notes_table(),
        simple_hotel_crm_booking_adjustments_table(),
        simple_hotel_crm_sync_bookings_table(),
        simple_hotel_crm_bookings_table(),
        simple_hotel_crm_guests_table(),
    ];

    $results = [];
    foreach ( $tables as $table ) {
        $deleted = $wpdb->query( "TRUNCATE TABLE {$table}" );
        $results[ $table ] = false === $deleted ? false : true;
    }

    simple_hotel_crm_clear_calendar_cache();
    return $results;
}

function simple_hotel_crm_format_channel_code( $source_channel, $created_date = '' ) {
    $source_channel = (string) $source_channel;
    $created_date   = (string) $created_date;

    $prefix_map = [
        'booking_com' => 'B',
        'direct'      => 'D',
        'website'     => 'W',
    ];

    $prefix = $prefix_map[ $source_channel ] ?? strtoupper( substr( preg_replace( '/[^a-z]/i', '', $source_channel ), 0, 1 ) );
    if ( '' === $prefix ) {
        $prefix = '?';
    }

    return trim( $prefix . ( $created_date ? ' ' . $created_date : '' ) );
}

function simple_hotel_crm_debug_ics_feeds() {
    global $wpdb;

    $result = [
        'timestamp'    => current_time( 'mysql' ),
        'rooms'        => [],
        'crm_bookings' => [],
        'errors'       => [],
    ];

    $room_urls = simple_hotel_crm_get_booking_com_ics_room_urls();
    if ( empty( $room_urls ) ) {
        $result['errors'][] = __( 'No Booking.com ICS URLs configured.', 'simple-hotel-crm' );
        return $result;
    }

    $rooms_table = simple_hotel_crm_rooms_table();

    foreach ( $room_urls as $room_id => $url ) {
        $room_id = absint( $room_id );
        $url     = esc_url_raw( trim( (string) $url ) );
        if ( $room_id <= 0 || '' === $url ) {
            continue;
        }

        $room = $wpdb->get_row( $wpdb->prepare( "SELECT id, sync_room_id, external_room_id, room_code, room_name FROM {$rooms_table} WHERE id = %d LIMIT 1", $room_id ), ARRAY_A );
        if ( ! $room ) {
            $result['errors'][] = sprintf( __( 'Room %d: CRM room not found.', 'simple-hotel-crm' ), $room_id );
            continue;
        }

        $room_entry = [
            'room_id'        => (int) $room['id'],
            'room_name'      => $room['room_name'],
            'room_code'      => $room['room_code'],
            'sync_room_id'   => (int) $room['sync_room_id'],
            'external_room_id' => (int) $room['external_room_id'],
            'ics_url'        => $url,
            'events'         => [],
            'fetch_error'    => null,
        ];

        $response = wp_remote_get( $url, [ 'timeout' => 20 ] );
        if ( is_wp_error( $response ) ) {
            $room_entry['fetch_error'] = $response->get_error_message();
            $result['rooms'][]         = $room_entry;
            continue;
        }
        if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
            $room_entry['fetch_error'] = 'HTTP ' . (int) wp_remote_retrieve_response_code( $response );
            $result['rooms'][]         = $room_entry;
            continue;
        }

        $events = simple_hotel_crm_parse_ics_content( wp_remote_retrieve_body( $response ) );

        foreach ( $events as $event ) {
            $check_in      = simple_hotel_crm_parse_ics_date_value( $event['DTSTART'] ?? '' );
            $check_out     = simple_hotel_crm_parse_ics_date_value( $event['DTEND'] ?? '' );
            $uid           = trim( (string) ( $event['UID'] ?? '' ) );
            $summary_text  = trim( (string) ( $event['SUMMARY'] ?? '' ) );
            $description   = trim( (string) ( $event['DESCRIPTION'] ?? '' ) );
            $source_booking_id = '' !== $uid ? $uid : md5( $room_id . '|' . $check_in . '|' . $check_out . '|' . $summary_text );
            $external_booking_id      = abs( crc32( $source_booking_id ) );
            $external_booking_room_id = abs( crc32( $room_id . '|' . $source_booking_id ) );

            $nights = 0;
            if ( '' !== $check_in && '' !== $check_out ) {
                $nights = max( 1, (int) round( ( strtotime( $check_out ) - strtotime( $check_in ) ) / DAY_IN_SECONDS ) );
            }

            $room_entry['events'][] = [
                'uid_raw'                => $uid,
                'summary'                => $summary_text,
                'description'            => $description,
                'check_in'               => $check_in,
                'check_out'              => $check_out,
                'nights'                 => $nights,
                'source_booking_id'      => $source_booking_id,
                'external_booking_id'    => $external_booking_id,
                'external_booking_room_id' => $external_booking_room_id,
            ];
        }

        $result['rooms'][] = $room_entry;
    }

    // Show sync table stats
    $sync_bookings_table = simple_hotel_crm_sync_bookings_table();
    $result['sync_stats'] = [
        'total_rows'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$sync_bookings_table} WHERE source_channel = 'booking_com'" ),
        'distinct_groups' => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT CONCAT(external_booking_id, '-', room_sync_id, '-', external_room_id)) FROM {$sync_bookings_table} WHERE source_channel = 'booking_com'" ),
        'distinct_rooms'  => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT room_sync_id) FROM {$sync_bookings_table} WHERE source_channel = 'booking_com'" ),
        'room_sync_ids'   => $wpdb->get_col( "SELECT DISTINCT room_sync_id FROM {$sync_bookings_table} WHERE source_channel = 'booking_com' ORDER BY room_sync_id ASC" ),
    ];

    $bookings_table     = simple_hotel_crm_bookings_table();
    $booking_rooms_table = simple_hotel_crm_booking_rooms_table();

    $bookings = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT b.id, b.source_booking_id, b.external_booking_id, b.source_channel,
                    b.check_in_date, b.check_out_date, b.status_code, b.internal_notes,
                    b.adults, b.total_amount, b.guest_id
             FROM {$bookings_table} b
             WHERE b.source_channel = %s
             ORDER BY b.id ASC",
            'booking_com'
        ),
        ARRAY_A
    );

    foreach ( $bookings as $bk ) {
        $room_ids = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT room_id FROM {$booking_rooms_table} WHERE booking_id = %d", $bk['id'] ) );

        $result['crm_bookings'][] = [
            'id'                => (int) $bk['id'],
            'source_booking_id' => $bk['source_booking_id'],
            'external_booking_id' => $bk['external_booking_id'],
            'check_in_date'     => $bk['check_in_date'],
            'check_out_date'    => $bk['check_out_date'],
            'status_code'       => $bk['status_code'],
            'room_ids'          => array_map( 'intval', $room_ids ),
            'adults'            => (int) $bk['adults'],
            'total_amount'      => (float) $bk['total_amount'],
            'guest_id'          => (int) $bk['guest_id'],
            'is_ics_skeleton'   => simple_hotel_crm_booking_is_ics_skeleton( $bk ),
            'internal_notes'    => $bk['internal_notes'],
        ];
    }

    return $result;
}

