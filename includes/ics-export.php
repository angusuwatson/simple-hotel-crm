<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'init', 'simple_hotel_crm_ics_export_init' );
function simple_hotel_crm_ics_export_init() {
    add_rewrite_rule( '^ics-export/([A-Za-z0-9]+)/?$', 'index.php?ics_feed_token=$matches[1]', 'top' );
    add_rewrite_tag( '%ics_feed_token%', '([^/]+)' );
}

add_filter( 'query_vars', function( $vars ) {
    $vars[] = 'ics_feed_token';
    return $vars;
} );

add_action( 'template_redirect', 'simple_hotel_crm_ics_export_serve_feed' );
function simple_hotel_crm_ics_export_serve_feed() {
    $token = get_query_var( 'ics_feed_token', '' );
    if ( ! $token ) {
        return;
    }
    $room_id = simple_hotel_crm_ics_export_get_room_id_by_token( $token );
    if ( ! $room_id ) {
        wp_die( esc_html__( 'Invalid ICS feed token.', 'simple-hotel-crm' ), '', [ 'response' => 403 ] );
    }
    $ics = simple_hotel_crm_ics_export_generate( $room_id );
    header( 'Content-Type: text/calendar; charset=utf-8' );
    header( 'Content-Disposition: inline; filename="room-' . absint( $room_id ) . '.ics"' );
    echo $ics;
    exit;
}

function simple_hotel_crm_ics_export_get_room_id_by_token( $token ) {
    global $wpdb;
    $rooms_table = simple_hotel_crm_rooms_table();
    $result = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$rooms_table} WHERE ics_export_token = %s AND active = 1 LIMIT 1", $token ) );
    return $result ? (int) $result : null;
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
    $lines[] = 'PRODID:-//LGF Bookings//EN';
    $lines[] = 'CALSCALE:GREGORIAN';
    $lines[] = 'METHOD:PUBLISH';

    foreach ( $rows as $row ) {
        $uid = 'lgb-room-' . $room_id . '-br-' . (int) $row['booking_room_id'] . '@' . parse_url( home_url(), PHP_URL_HOST );
        $dtstart = str_replace( '-', '', (string) $row['check_in_date'] );
        $dtend   = str_replace( '-', '', (string) $row['check_out_date'] );

        $lines[] = 'BEGIN:VEVENT';
        $lines[] = 'UID:' . $uid;
        $lines[] = 'DTSTART;VALUE=DATE:' . $dtstart;
        $lines[] = 'DTEND;VALUE=DATE:' . $dtend;
        $lines[] = 'SUMMARY:LGF Booked';
        $lines[] = 'END:VEVENT';
    }

    $lines[] = 'END:VCALENDAR';

    return implode( "\r\n", $lines ) . "\r\n";
}

function simple_hotel_crm_ics_export_feed_url( $room_id ) {
    $token = simple_hotel_crm_ics_export_get_token( $room_id );
    return home_url( '/ics-export/' . $token . '/' );
}
