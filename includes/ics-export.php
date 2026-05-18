<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function simple_hotel_crm_ics_export_generate( $room_id ) {
    global $wpdb;
    $bookings_table = simple_hotel_crm_bookings_table();
    $booking_rooms_table = simple_hotel_crm_booking_rooms_table();

    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT b.check_in_date, b.check_out_date, br.id AS booking_room_id
         FROM {$booking_rooms_table} br
         INNER JOIN {$bookings_table} b ON b.id = br.booking_id
         WHERE br.room_id = %d
           AND b.source_channel = 'direct'
           AND b.status_code = 'confirmed'
           AND b.is_deleted = 0
         ORDER BY b.check_in_date ASC",
        $room_id
    ), ARRAY_A );

    $lines = [];
    $lines[] = 'BEGIN:VCALENDAR';
    $lines[] = 'VERSION:2.0';
    $lines[] = 'PRODID:-//LGF Bookings//WordPress';
    $lines[] = 'CALSCALE:GREGORIAN';
    $lines[] = 'METHOD:PUBLISH';

    foreach ( $rows as $row ) {
        $uid = 'lgf-room-' . absint( $room_id ) . '-br-' . absint( $row['booking_room_id'] ) . '-' . str_replace( '-', '', (string) $row['check_in_date'] ) . '@' . parse_url( home_url(), PHP_URL_HOST );
        $dtstart = str_replace( '-', '', (string) $row['check_in_date'] );
        $dtend   = str_replace( '-', '', (string) $row['check_out_date'] );

        $lines[] = 'BEGIN:VEVENT';
        $lines[] = 'UID:' . $uid;
        $lines[] = 'DTSTAMP:' . gmdate( 'Ymd\THis\Z' );
        $lines[] = 'DTSTART;VALUE=DATE:' . $dtstart;
        $lines[] = 'DTEND;VALUE=DATE:' . $dtend;
        $lines[] = 'SUMMARY:blocked';
        $lines[] = 'END:VEVENT';
    }

    $lines[] = 'END:VCALENDAR';

    return implode( "\r\n", $lines ) . "\r\n";
}

function simple_hotel_crm_ics_export_get_token( $room_id ) {
    global $wpdb;
    $rooms_table = simple_hotel_crm_rooms_table();
    $token = $wpdb->get_var( $wpdb->prepare( "SELECT ics_export_token FROM {$rooms_table} WHERE id = %d LIMIT 1", $room_id ) );
    if ( empty( $token ) ) {
        $token = wp_generate_password( 32, false );
        $wpdb->update( $rooms_table, [ 'ics_export_token' => $token ], [ 'id' => $room_id ], [ '%s' ], [ '%d' ] );
    }
    return $token;
}

function simple_hotel_crm_ics_export_get_token_by_room_id( $room_id ) {
    return simple_hotel_crm_ics_export_get_token( $room_id );
}

function simple_hotel_crm_ics_export_feed_url( $room_id ) {
    $token = simple_hotel_crm_ics_export_get_token( $room_id );
    return home_url( '/wp-content/uploads/lgf-ics/' . $token . '.ics' );
}

function simple_hotel_crm_ics_export_get_file_path( $token ) {
    $upload_dir = wp_upload_dir();
    return $upload_dir['basedir'] . '/lgf-ics/' . $token . '.ics';
}

function simple_hotel_crm_ics_export_refresh_file( $room_id, $token = null ) {
    if ( null === $token ) {
        $token = simple_hotel_crm_ics_export_get_token( $room_id );
    }
    $file_path = simple_hotel_crm_ics_export_get_file_path( $token );
    $dir = dirname( $file_path );
    if ( ! is_dir( $dir ) ) {
        wp_mkdir_p( $dir );
    }
    $ics = simple_hotel_crm_ics_export_generate( $room_id );
    file_put_contents( $file_path, $ics );
    return $file_path;
}

function simple_hotel_crm_ics_export_refresh_all_files() {
    global $wpdb;
    $rooms_table = simple_hotel_crm_rooms_table();
    $rooms = $wpdb->get_results( "SELECT id FROM {$rooms_table} WHERE active = 1", ARRAY_A );
    foreach ( $rooms as $room ) {
        simple_hotel_crm_ics_export_refresh_file( (int) $room['id'] );
    }
}

function simple_hotel_crm_ics_export_on_booking_change( $booking_id ) {
    global $wpdb;
    $booking_rooms_table = simple_hotel_crm_booking_rooms_table();
    $bookings_table = simple_hotel_crm_bookings_table();

    $booking = $wpdb->get_row( $wpdb->prepare( "SELECT source_channel, status_code FROM {$bookings_table} WHERE id = %d", $booking_id ), ARRAY_A );
    if ( ! $booking || 'direct' !== (string) $booking['source_channel'] || 'confirmed' !== (string) $booking['status_code'] ) {
        return;
    }

    $room_ids = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT room_id FROM {$booking_rooms_table} WHERE booking_id = %d AND room_id IS NOT NULL AND room_id > 0", $booking_id ) );
    foreach ( $room_ids as $room_id ) {
        $token = simple_hotel_crm_ics_export_get_token( (int) $room_id );
        if ( $token ) {
            simple_hotel_crm_ics_export_refresh_file( (int) $room_id, $token );
        }
    }
}

add_action( 'add_attachment', 'simple_hotel_crm_ics_export_on_booking_change' );
add_action( 'edit_attachment', 'simple_hotel_crm_ics_export_on_booking_change' );

add_action( 'init', 'simple_hotel_crm_ics_export_serve_file' );
function simple_hotel_crm_ics_export_serve_file() {
    $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
    if ( preg_match( '|^/wp-content/uploads/lgf-ics/([A-Za-z0-9]+)\.ics$|', $request_uri, $m ) ) {
        $token = $m[1];
        $file_path = simple_hotel_crm_ics_export_get_file_path( $token );
        if ( file_exists( $file_path ) ) {
            header( 'Content-Type: text/calendar; charset=utf-8' );
            header( 'Content-Length: ' . filesize( $file_path ) );
            header( 'Cache-Control: no-cache, must-revalidate, max-age=0' );
            readfile( $file_path );
            exit;
        } else {
            $room_id = simple_hotel_crm_ics_export_get_room_id_by_token( $token );
            if ( $room_id ) {
                simple_hotel_crm_ics_export_refresh_file( $room_id, $token );
                if ( file_exists( $file_path ) ) {
                    header( 'Content-Type: text/calendar; charset=utf-8' );
                    header( 'Content-Length: ' . filesize( $file_path ) );
                    header( 'Cache-Control: no-cache, must-revalidate, max-age=0' );
                    readfile( $file_path );
                    exit;
                }
            }
            wp_die( 'ICS feed not found.', '', [ 'response' => 404 ] );
        }
    }
}

function simple_hotel_crm_ics_export_get_room_id_by_token( $token ) {
    global $wpdb;
    $rooms_table = simple_hotel_crm_rooms_table();
    $result = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$rooms_table} WHERE ics_export_token = %s AND active = 1 LIMIT 1", $token ) );
    return $result ? (int) $result : null;
}