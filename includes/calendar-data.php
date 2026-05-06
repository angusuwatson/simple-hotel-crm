<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function simple_hotel_crm_get_empty_calendar_result( $month, $year, $days_in_month = 0 ) {
    return [
        'rooms'         => [],
        'matrix'        => [],
        'month'         => $month,
        'year'          => $year,
        'days_in_month' => $days_in_month,
        'days'          => $days_in_month > 0 ? range( 1, $days_in_month ) : [],
        'daily_notes'   => $days_in_month > 0 ? simple_hotel_crm_get_daily_notes_for_month( $year, $month ) : [],
    ];
}

function simple_hotel_crm_get_wp_sync_calendar_data( $month, $year ) {
    global $wpdb;

    $first_day = new DateTime( sprintf( '%04d-%02d-01', $year, $month ) );
    $days_in_month = (int) $first_day->format( 't' );
    $last_day = clone $first_day;
    $last_day->setDate( $year, $month, $days_in_month );

    $first_day_str = $first_day->format( 'Y-m-d' );
    $month_after_last_day_str = $last_day->modify( '+1 day' )->format( 'Y-m-d' );

    $rooms_table = simple_hotel_crm_rooms_table();
    $guests_table = simple_hotel_crm_guests_table();
    $bookings_table = simple_hotel_crm_bookings_table();
    $booking_rooms_table = simple_hotel_crm_booking_rooms_table();
    $booking_nights_table = simple_hotel_crm_booking_room_nights_table();

    $rooms_rows = $wpdb->get_results( "SELECT id, external_room_id, room_code, room_name, sort_order, color FROM {$rooms_table} WHERE active = 1 ORDER BY sort_order ASC, room_name ASC", ARRAY_A );

    $rooms = [];
    $matrix = [];
    foreach ( $rooms_rows as $room_row ) {
        $room = (object) [
            'id' => (int) $room_row['id'],
            'external_room_id' => (int) $room_row['external_room_id'],
            'title' => (string) $room_row['room_name'],
            'code' => (string) $room_row['room_code'],
            'color' => (string) ( $room_row['color'] ?: '#cccccc' ),
        ];
        $rooms[] = $room;
        $matrix[ $room->id ] = [];
    }

    if ( empty( $rooms ) ) {
        return simple_hotel_crm_get_empty_calendar_result( $month, $year, $days_in_month );
    }

    $booking_rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT
                b.id AS booking_id,
                b.status_code,
                b.check_in_date,
                b.check_out_date,
                b.total_amount AS booking_total_amount,
                b.source_channel,
                b.source_booking_id,
                b.booking_note,
                b.internal_notes,
                b.invoice_ninja_client_id,
                b.invoice_ninja_invoice_id,
                b.contacted_date,
                b.created_at,
                br.id AS booking_room_id,
                br.legacy_reserved_room_id,
                br.room_id,
                br.total_amount AS room_stay_total_amount,
                brn.stay_date,
                brn.guest_count,
                brn.adults,
                brn.children,
                brn.babies,
                brn.subtotal_amount,
                brn.room_rate_amount,
                brn.extras_amount,
                brn.tourist_tax_amount,
                brn.total_amount,
                g.first_name,
                g.last_name,
                g.phone,
                room_counts.room_count
             FROM {$bookings_table} b
             JOIN {$booking_rooms_table} br ON br.booking_id = b.id
             JOIN {$booking_nights_table} brn ON brn.booking_room_id = br.id
             JOIN {$guests_table} g ON g.id = b.guest_id
             JOIN (
                SELECT booking_id, COUNT(*) AS room_count
                FROM {$booking_rooms_table}
                GROUP BY booking_id
             ) room_counts ON room_counts.booking_id = b.id
             WHERE b.is_deleted = 0
               AND b.status_code IN ('pending', 'confirmed', 'checked_in')
               AND brn.stay_date >= %s
               AND brn.stay_date < %s
             ORDER BY brn.stay_date ASC, b.id ASC, br.id ASC",
            $first_day_str,
            $month_after_last_day_str
        ),
        ARRAY_A
    );

    foreach ( $booking_rows as $row ) {
        $room_id = (int) $row['room_id'];
        if ( ! isset( $matrix[ $room_id ] ) ) {
            continue;
        }

        $reserved_room_id = ! empty( $row['legacy_reserved_room_id'] ) ? (int) $row['legacy_reserved_room_id'] : (int) $row['booking_room_id'];
        $overlay = simple_hotel_crm_get_booking_overlay( $reserved_room_id );
        $adults = isset( $overlay['manual_adults'] ) && '' !== $overlay['manual_adults'] ? (int) $overlay['manual_adults'] : (int) $row['adults'];
        $children = isset( $overlay['manual_children'] ) && '' !== $overlay['manual_children'] ? (int) $overlay['manual_children'] : (int) $row['children'];
        $guest_name = trim( (string) $row['first_name'] . ' ' . (string) $row['last_name'] );
        $manual_guest_name = trim( (string) ( $overlay['manual_guest_name'] ?? '' ) );
        if ( '' !== $manual_guest_name && ! preg_match( '/^\d+(?:\.\d+)?$/', $manual_guest_name ) ) {
            $guest_name = $manual_guest_name;
        }
        $extras_formula = isset( $overlay['extras_formula'] ) ? (string) $overlay['extras_formula'] : '';
        $extras_total = isset( $overlay['extras_total'] ) && '' !== $overlay['extras_total'] ? (float) $overlay['extras_total'] : null;

        $date_str = (string) $row['stay_date'];
        if ( ! isset( $matrix[ $room_id ][ $date_str ] ) ) {
            $matrix[ $room_id ][ $date_str ] = [ 'booking' => null, 'is_checkin' => false, 'is_checkout' => false ];
        }

        $booking_payload = (object) [
            'id' => (int) $row['booking_id'],
            'status' => (string) $row['status_code'],
            'check_in' => (string) $row['check_in_date'],
            'check_out' => (string) $row['check_out_date'],
            'stay_date' => $date_str,
            'room_id' => $room_id,
            'reserved_room_id' => $reserved_room_id,
            'guest_name' => $guest_name,
            'phone' => (string) $row['phone'],
            'guest_count' => (int) $row['guest_count'],
            'babies' => (int) $row['babies'],
            'adults' => $adults,
            'children' => $children,
            'occupancy_str' => simple_hotel_crm_format_occupancy( $adults, $children ),
            'channel' => simple_hotel_crm_format_channel_code( (string) $row['source_channel'], ! empty( $row['contacted_date'] ) ? (string) $row['contacted_date'] : '' ),
            'channel_label' => (string) $row['source_channel'],
            'created_date' => ! empty( $row['contacted_date'] ) ? (string) $row['contacted_date'] : '',
            'is_imported' => 'direct' !== (string) $row['source_channel'],
            'tarif' => isset( $overlay['manual_tarif'] ) && '' !== $overlay['manual_tarif'] ? (float) $overlay['manual_tarif'] : (float) ( isset( $row['subtotal_amount'] ) ? $row['subtotal_amount'] : $row['room_rate_amount'] ),
            'commission' => isset( $overlay['manual_commission'] ) && '' !== $overlay['manual_commission'] ? (float) $overlay['manual_commission'] : simple_hotel_crm_calculate_channel_commission( (string) $row['source_channel'], (float) ( isset( $row['subtotal_amount'] ) ? $row['subtotal_amount'] : $row['room_rate_amount'] ) ),
            'extras_formula' => $extras_formula,
            'extras_total' => null !== $extras_total ? $extras_total : ( (float) $row['extras_amount'] > 0 ? (float) $row['extras_amount'] : null ),
            'booking_note' => (string) ( $row['booking_note'] ?? '' ),
            'import_notes' => (string) ( $row['internal_notes'] ?? '' ),
            'tourist_tax_amount' => (float) $row['tourist_tax_amount'],
            'reservation_total_amount' => (float) $row['booking_total_amount'],
            'room_stay_total_amount' => (float) $row['room_stay_total_amount'],
            'invoice_ninja_client_id' => (string) $row['invoice_ninja_client_id'],
            'invoice_ninja_invoice_id' => (string) $row['invoice_ninja_invoice_id'],
            'source_booking_id' => (string) $row['source_booking_id'],
        ];

        $matrix[ $room_id ][ $date_str ]['booking'] = $booking_payload;
        if ( $date_str === $booking_payload->check_in ) {
            $matrix[ $room_id ][ $date_str ]['is_checkin'] = true;
        }

        $last_night_date = gmdate( 'Y-m-d', strtotime( $booking_payload->check_out . ' -1 day' ) );
        if ( $date_str === $last_night_date ) {
            $check_out_date_str = (string) $booking_payload->check_out;
            if ( ! isset( $matrix[ $room_id ][ $check_out_date_str ] ) ) {
                $matrix[ $room_id ][ $check_out_date_str ] = [ 'booking' => null, 'is_checkin' => false, 'is_checkout' => false ];
            }
            $matrix[ $room_id ][ $check_out_date_str ]['is_checkout'] = true;
        }
    }

    return [
        'rooms' => $rooms,
        'matrix' => $matrix,
        'month' => $month,
        'year' => $year,
        'days_in_month' => $days_in_month,
        'days' => range( 1, $days_in_month ),
        'daily_notes' => simple_hotel_crm_get_daily_notes_for_month( $year, $month ),
    ];
}

function simple_hotel_crm_get_calendar_data( $month = null, $year = null ) {
    $month = $month ?: date( 'n' );
    $year  = $year ?: date( 'Y' );

    $transient_key = 'simple_hotel_crm_wp_sync_' . $year . '_' . $month;
    $cached = get_transient( $transient_key );
    if ( false !== $cached ) {
        return $cached;
    }

    $result = simple_hotel_crm_get_wp_sync_calendar_data( $month, $year );
    set_transient( $transient_key, $result, 30 * MINUTE_IN_SECONDS );
    return $result;
}

function simple_hotel_crm_get_month_tabs( $month, $year ) {
    $tabs = [];
    for ( $month_index = 1; $month_index <= 12; $month_index++ ) {
        $date = new DateTime( sprintf( '%04d-%02d-01', $year, $month_index ) );
        $tabs[] = [
            'month'   => (int) $date->format( 'n' ),
            'year'    => (int) $date->format( 'Y' ),
            'label'   => date_i18n( 'M', $date->getTimestamp() ),
            'current' => (int) $date->format( 'n' ) === (int) $month && (int) $date->format( 'Y' ) === (int) $year,
        ];
    }
    return $tabs;
}

function simple_hotel_crm_build_daily_summary( $calendar_data ) {
    $summary = [];

    foreach ( $calendar_data['days'] as $day ) {
        $date_str = sprintf( '%04d-%02d-%02d', $calendar_data['year'], $calendar_data['month'], $day );
        $income_day = 0.0;
        $commission_day = 0.0;
        $rooms_count = 0;
        $extras_count = 0;
        $tax_adults = 0;
        $tax_children = 0;
        $seen_bookings = [];

        foreach ( $calendar_data['matrix'] as $room_matrix ) {
            if ( empty( $room_matrix[ $date_str ]['booking'] ) ) {
                continue;
            }

            $booking = $room_matrix[ $date_str ]['booking'];
            $booking_key = ! empty( $booking->reserved_room_id ) ? 'rr_' . (int) $booking->reserved_room_id : 'b_' . (int) $booking->id;
            if ( isset( $seen_bookings[ $booking_key ] ) ) {
                continue;
            }

            $seen_bookings[ $booking_key ] = true;
            $rooms_count++;
            $extras_count += ! empty( $booking->extras_formula ) ? 1 : 0;
            $tax_adults += (int) ( $booking->adults ?? 0 );
            $tax_children += (int) ( $booking->children ?? 0 );

            $nights = max( 1, (int) round( ( strtotime( $booking->check_out ) - strtotime( $booking->check_in ) ) / DAY_IN_SECONDS ) );
            $income_day += (float) ( $booking->tarif ?? 0 ) / $nights;
            $commission_day += (float) ( $booking->commission ?? 0 ) / $nights;
        }

        $previous_income = $day > 1 ? ( $summary[ $day - 1 ]['income_accumulated'] ?? 0 ) : 0;
        $previous_commission = $day > 1 ? ( $summary[ $day - 1 ]['booking_accumulated'] ?? 0 ) : 0;

        $summary[ $day ] = [
            'income_day' => $income_day,
            'income_accumulated' => $previous_income + $income_day,
            'table_dhotes' => $extras_count,
            'rooms' => $rooms_count,
            'tourist_tax_adults' => $tax_adults,
            'tourist_tax_children' => $tax_children,
            'booking_payment' => $commission_day,
            'booking_accumulated' => $previous_commission + $commission_day,
        ];
    }

    return $summary;
}

