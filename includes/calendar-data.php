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
        if ( isset( $overlay['manual_guest_name'] ) && '' !== trim( (string) $overlay['manual_guest_name'] ) ) {
            $guest_name = trim( (string) $overlay['manual_guest_name'] );
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
            'channel' => simple_hotel_crm_format_channel_code( (string) $row['source_channel'], ! empty( $row['created_at'] ) ? substr( (string) $row['created_at'], 0, 10 ) : '' ),
            'channel_label' => (string) $row['source_channel'],
            'created_date' => ! empty( $row['created_at'] ) ? substr( (string) $row['created_at'], 0, 10 ) : '',
            'is_imported' => 'motopress' !== (string) $row['source_channel'],
            'tarif' => isset( $overlay['manual_tarif'] ) && '' !== $overlay['manual_tarif'] ? (float) $overlay['manual_tarif'] : (float) $row['room_rate_amount'],
            'commission' => isset( $overlay['manual_commission'] ) && '' !== $overlay['manual_commission'] ? (float) $overlay['manual_commission'] : '',
            'extras_formula' => $extras_formula,
            'extras_total' => null !== $extras_total ? $extras_total : ( (float) $row['extras_amount'] > 0 ? (float) $row['extras_amount'] : null ),
            'booking_note' => '' !== trim( (string) ( $row['booking_note'] ?? '' ) ) ? (string) $row['booking_note'] : ( isset( $overlay['booking_note'] ) ? (string) $overlay['booking_note'] : '' ),
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

function simple_hotel_crm_get_external_calendar_data( $month, $year ) {
    $first_day = new DateTime( sprintf( '%04d-%02d-01', $year, $month ) );
    $days_in_month = (int) $first_day->format( 't' );
    $last_day = clone $first_day;
    $last_day->setDate( $year, $month, $days_in_month );

    $first_day_str = $first_day->format( 'Y-m-d' );
    $month_after_last_day_str = $last_day->modify( '+1 day' )->format( 'Y-m-d' );

    $connection = simple_hotel_crm_get_external_pg_connection();
    if ( is_wp_error( $connection ) ) {
        add_action( 'admin_notices', function() use ( $connection ) {
            echo '<div class="notice notice-error"><p>' . esc_html( $connection->get_error_message() ) . '</p></div>';
        } );
        return simple_hotel_crm_get_empty_calendar_result( $month, $year, $days_in_month );
    }

    $rooms_result = pg_query( $connection, "SELECT id, code, name FROM rooms WHERE active = true ORDER BY code" );
    if ( ! $rooms_result ) {
        return simple_hotel_crm_get_empty_calendar_result( $month, $year, $days_in_month );
    }

    $rooms = [];
    $room_colors = simple_hotel_crm_get_room_colors();
    $matrix = [];

    while ( $room_row = pg_fetch_assoc( $rooms_result ) ) {
        $room_code = (string) $room_row['code'];
        $room = (object) [
            'id'    => (int) $room_row['id'],
            'title' => (string) $room_row['name'],
            'code'  => $room_code,
            'sort_order' => simple_hotel_crm_get_room_sort_order( $room_code, $room_row['name'] ),
            'color' => $room_colors[ strtoupper( $room_code ) ] ?? '#cccccc',
        ];
        $rooms[] = $room;
        $matrix[ $room->id ] = [];
    }

    usort( $rooms, function( $a, $b ) {
        return ( $a->sort_order <=> $b->sort_order ) ?: strcasecmp( $a->title, $b->title );
    } );

    if ( empty( $rooms ) ) {
        return simple_hotel_crm_get_empty_calendar_result( $month, $year, $days_in_month );
    }

    $bookings_sql = "
        SELECT
            b.id AS booking_id,
            b.status_code,
            b.check_in_date,
            b.check_out_date,
            br.guest_count,
            br.adults,
            br.children,
            br.babies,
            br.total_amount,
            br.room_rate_amount,
            br.extras_amount,
            br.tourist_tax_amount,
            brn.stay_date,
            brn.guest_count AS night_guest_count,
            brn.adults AS night_adults,
            brn.children AS night_children,
            brn.babies AS night_babies,
            brn.total_amount AS night_total_amount,
            brn.room_rate_amount AS night_room_rate_amount,
            brn.extras_amount AS night_extras_amount,
            brn.tourist_tax_amount AS night_tourist_tax_amount,
            b.total_amount AS booking_total_amount,
            b.source_channel,
            b.source_booking_id,
            b.internal_notes,
            b.invoice_ninja_client_id,
            b.invoice_ninja_invoice_id,
            b.created_at,
            br.id AS booking_room_id,
            br.room_id,
            r.name AS room_name,
            g.first_name,
            g.last_name,
            g.phone,
            bc.label AS channel_label,
            room_counts.room_count
        FROM bookings b
        JOIN booking_rooms br ON br.booking_id = b.id
        JOIN booking_room_nights brn ON brn.booking_room_id = br.id
        JOIN rooms r ON r.id = br.room_id
        JOIN guests g ON g.id = b.guest_id
        LEFT JOIN booking_channels bc ON bc.code = b.source_channel
        JOIN (
            SELECT booking_id, COUNT(*) AS room_count
            FROM booking_rooms
            GROUP BY booking_id
        ) room_counts ON room_counts.booking_id = b.id
        JOIN booking_statuses bs ON bs.code = b.status_code
        WHERE bs.blocks_availability = true
          AND brn.stay_date >= $1::date
          AND brn.stay_date < $2::date
        ORDER BY brn.stay_date ASC, b.id ASC, br.id ASC
    ";

    $bookings_result = pg_query_params( $connection, $bookings_sql, [ $first_day_str, $month_after_last_day_str ] );
    if ( ! $bookings_result ) {
        return simple_hotel_crm_get_empty_calendar_result( $month, $year, $days_in_month );
    }

    while ( $row = pg_fetch_assoc( $bookings_result ) ) {
        $room_id = (int) $row['room_id'];
        if ( ! isset( $matrix[ $room_id ] ) ) {
            continue;
        }

        $reserved_room_id = (int) $row['booking_room_id'];
        $overlay = simple_hotel_crm_get_booking_overlay( $reserved_room_id );
        $adults = isset( $overlay['manual_adults'] ) && '' !== $overlay['manual_adults'] ? (int) $overlay['manual_adults'] : (int) $row['night_adults'];
        $children = isset( $overlay['manual_children'] ) && '' !== $overlay['manual_children'] ? (int) $overlay['manual_children'] : (int) $row['night_children'];
        $guest_name = trim( (string) $row['first_name'] . ' ' . (string) $row['last_name'] );
        if ( isset( $overlay['manual_guest_name'] ) && '' !== trim( (string) $overlay['manual_guest_name'] ) ) {
            $guest_name = trim( (string) $overlay['manual_guest_name'] );
        }

        $extras_formula = isset( $overlay['extras_formula'] ) ? (string) $overlay['extras_formula'] : '';
        $extras_total   = isset( $overlay['extras_total'] ) && '' !== $overlay['extras_total'] ? (float) $overlay['extras_total'] : null;
        $date_str       = (string) $row['stay_date'];

        if ( ! isset( $matrix[ $room_id ][ $date_str ] ) ) {
            $matrix[ $room_id ][ $date_str ] = [ 'booking' => null, 'is_checkin' => false, 'is_checkout' => false ];
        }

        $booking_payload = (object) [
            'id'                      => (int) $row['booking_id'],
            'status'                  => (string) $row['status_code'],
            'check_in'                => (string) $row['check_in_date'],
            'check_out'               => (string) $row['check_out_date'],
            'stay_date'               => $date_str,
            'room_id'                 => $room_id,
            'reserved_room_id'        => $reserved_room_id,
            'guest_name'              => $guest_name,
            'phone'                   => (string) $row['phone'],
            'guest_count'             => isset( $row['night_guest_count'] ) ? (int) $row['night_guest_count'] : 0,
            'babies'                  => isset( $row['night_babies'] ) ? (int) $row['night_babies'] : 0,
            'adults'                  => $adults,
            'children'                => $children,
            'occupancy_str'           => simple_hotel_crm_format_occupancy( $adults, $children ),
            'channel'                 => simple_hotel_crm_format_channel_code( (string) $row['source_channel'], substr( (string) $row['created_at'], 0, 10 ) ),
            'channel_label'           => (string) ( $row['channel_label'] ?: $row['source_channel'] ),
            'created_date'            => substr( (string) $row['created_at'], 0, 10 ),
            'is_imported'             => 'motopress' !== (string) $row['source_channel'],
            'tarif'                   => isset( $overlay['manual_tarif'] ) && '' !== $overlay['manual_tarif'] ? (float) $overlay['manual_tarif'] : (float) $row['night_room_rate_amount'],
            'commission'              => isset( $overlay['manual_commission'] ) && '' !== $overlay['manual_commission'] ? (float) $overlay['manual_commission'] : '',
            'extras_formula'          => $extras_formula,
            'extras_total'            => null !== $extras_total ? $extras_total : ( ( isset( $row['night_extras_amount'] ) && '' !== $row['night_extras_amount'] && (float) $row['night_extras_amount'] > 0 ) ? (float) $row['night_extras_amount'] : null ),
            'booking_note'            => isset( $overlay['booking_note'] ) ? (string) $overlay['booking_note'] : '',
            'import_notes'            => (string) ( $row['internal_notes'] ?? '' ),
            'tourist_tax_amount'      => isset( $row['night_tourist_tax_amount'] ) ? (float) $row['night_tourist_tax_amount'] : 0.0,
            'reservation_total_amount'=> isset( $row['booking_total_amount'] ) ? (float) $row['booking_total_amount'] : (float) $row['total_amount'],
            'room_stay_total_amount'  => isset( $row['total_amount'] ) ? (float) $row['total_amount'] : 0.0,
            'invoice_ninja_client_id' => (string) $row['invoice_ninja_client_id'],
            'invoice_ninja_invoice_id'=> (string) $row['invoice_ninja_invoice_id'],
            'source_booking_id'       => (string) $row['source_booking_id'],
        ];

        $matrix[ $room_id ][ $date_str ]['booking'] = clone $booking_payload;
        if ( $date_str === $booking_payload->check_in ) {
            $matrix[ $room_id ][ $date_str ]['is_checkin'] = true;
        }

        $last_night_str = gmdate( 'Y-m-d', strtotime( $booking_payload->check_out . ' -1 day' ) );
        if ( $date_str === $last_night_str ) {
            $check_out_date_str = $booking_payload->check_out;
            if ( $check_out_date_str >= $first_day_str && $check_out_date_str < $month_after_last_day_str ) {
                if ( ! isset( $matrix[ $room_id ][ $check_out_date_str ] ) ) {
                    $matrix[ $room_id ][ $check_out_date_str ] = [ 'booking' => null, 'is_checkin' => false, 'is_checkout' => false ];
                }
                $matrix[ $room_id ][ $check_out_date_str ]['is_checkout'] = true;
            }
        }
    }

    return [
        'rooms'         => $rooms,
        'matrix'        => $matrix,
        'month'         => $month,
        'year'          => $year,
        'days_in_month' => $days_in_month,
        'days'          => range( 1, $days_in_month ),
        'daily_notes'   => simple_hotel_crm_get_daily_notes_for_month( $year, $month ),
    ];
}

function simple_hotel_crm_get_motopress_calendar_data( $month, $year ) {
    if ( ! function_exists( 'MPHB' ) || ! function_exists( 'mphb_rooms_facade' ) ) {
        return simple_hotel_crm_get_empty_calendar_result( $month, $year );
    }

    $first_day = new DateTime( sprintf( '%04d-%02d-01', $year, $month ) );
    $days_in_month = (int) $first_day->format( 't' );
    $last_day = clone $first_day;
    $last_day->setDate( $year, $month, $days_in_month )->setTime( 23, 59, 59 );

    $first_day_str = $first_day->format( 'Y-m-d' );
    $last_day_str  = $last_day->format( 'Y-m-d' );

    $room_args = [
        'post_type'      => 'mphb_room',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'fields'         => 'ids',
    ];

    if ( function_exists( 'pll_current_language' ) ) {
        $room_args['lang'] = pll_current_language();
    } elseif ( function_exists( 'icl_object_id' ) && defined( 'ICL_LANGUAGE_CODE' ) ) {
        $room_args['lang'] = ICL_LANGUAGE_CODE;
    }

    $is_language_filtered = ! empty( $room_args['lang'] );
    $room_ids = get_posts( $room_args );

    if ( empty( $room_ids ) && ! $is_language_filtered ) {
        global $wpdb;
        $room_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s ORDER BY title",
                'mphb_room',
                'publish'
            )
        );
    }

    $rooms = array_map( function( $room_id ) use ( $is_language_filtered ) {
        $title = get_the_title( $room_id );
        if ( ! $is_language_filtered && preg_match( '/^(.*?)(?:\s+1)$/', $title, $matches ) ) {
            $title = $matches[1];
        }

        return (object) [ 'id' => (int) $room_id, 'title' => $title ];
    }, $room_ids );

    $room_colors = simple_hotel_crm_get_room_colors();
    foreach ( $rooms as $idx => $room ) {
        $room_code = '';
        if ( isset( $room->code ) ) {
            $room_code = (string) $room->code;
        } elseif ( property_exists( $room, 'title' ) ) {
            $title_map = [ 'Anémone' => 'ANE', 'Delphinium' => 'DEL', 'Lys' => 'LYS', 'Tournesol' => 'TOU', 'Tulipe' => 'TUL', 'Coquelicot' => 'COQ' ];
            $room_code = $title_map[ $room->title ] ?? '';
        }
        $rooms[ $idx ]->sort_order = simple_hotel_crm_get_room_sort_order( $room_code, $room->title );
        $rooms[ $idx ]->color = $room_colors[ strtoupper( $room_code ) ] ?? '#cccccc';
    }

    usort( $rooms, function( $a, $b ) {
        return ( $a->sort_order <=> $b->sort_order ) ?: strcasecmp( $a->title, $b->title );
    } );

    if ( empty( $rooms ) ) {
        return simple_hotel_crm_get_empty_calendar_result( $month, $year, $days_in_month );
    }

    $matrix = [];
    foreach ( $rooms as $room ) {
        $matrix[ $room->id ] = [];
    }

    $bookings = MPHB()->getBookingRepository()->findAllInPeriod(
        $first_day_str,
        $last_day_str,
        [
            'room_locked' => true,
            'post_status' => simple_hotel_crm_get_locked_booking_statuses(),
            'orderby'     => 'date',
            'order'       => 'ASC',
        ]
    );

    foreach ( $bookings as $booking ) {
        if ( ! $booking || ! method_exists( $booking, 'getReservedRooms' ) ) {
            continue;
        }

        $reserved_rooms = $booking->getReservedRooms();
        $check_in = $booking->getCheckInDate();
        $check_out = $booking->getCheckOutDate();

        if ( ! $check_in || ! $check_out ) {
            continue;
        }

        foreach ( $reserved_rooms as $reserved_room ) {
            if ( ! $reserved_room || ! method_exists( $reserved_room, 'getRoomId' ) ) {
                continue;
            }

            $room_id = (int) $reserved_room->getRoomId();
            if ( ! isset( $matrix[ $room_id ] ) ) {
                continue;
            }

            $booking_payload = simple_hotel_crm_build_booking_payload( $booking, $reserved_room );

            for ( $date = clone $check_in; $date < $check_out; $date->modify( '+1 day' ) ) {
                $date_str = $date->format( 'Y-m-d' );
                if ( $date_str < $first_day_str || $date_str > $last_day_str ) {
                    continue;
                }

                if ( ! isset( $matrix[ $room_id ][ $date_str ] ) ) {
                    $matrix[ $room_id ][ $date_str ] = [ 'booking' => null, 'is_checkin' => false, 'is_checkout' => false ];
                }

                $matrix[ $room_id ][ $date_str ]['booking'] = clone $booking_payload;
                if ( $date_str === $booking_payload->check_in ) {
                    $matrix[ $room_id ][ $date_str ]['is_checkin'] = true;
                }
            }

            $check_out_date_str = $check_out->format( 'Y-m-d' );
            if ( $check_out_date_str >= $first_day_str && $check_out_date_str <= $last_day_str ) {
                if ( ! isset( $matrix[ $room_id ][ $check_out_date_str ] ) ) {
                    $matrix[ $room_id ][ $check_out_date_str ] = [ 'booking' => null, 'is_checkin' => false, 'is_checkout' => false ];
                }

                $matrix[ $room_id ][ $check_out_date_str ]['is_checkout'] = true;
            }
        }
    }

    return [
        'rooms'         => $rooms,
        'matrix'        => $matrix,
        'month'         => $month,
        'year'          => $year,
        'days_in_month' => $days_in_month,
        'days'          => range( 1, $days_in_month ),
        'daily_notes'   => simple_hotel_crm_get_daily_notes_for_month( $year, $month ),
    ];
}

function simple_hotel_crm_get_calendar_data( $month = null, $year = null ) {
    $month = $month ?: date( 'n' );
    $year  = $year ?: date( 'Y' );

    $transient_key = 'simple_hotel_crm_' . simple_hotel_crm_get_data_source() . '_' . $year . '_' . $month;
    $cached = get_transient( $transient_key );
    if ( false !== $cached ) {
        return $cached;
    }

    $source = simple_hotel_crm_get_data_source();
    if ( 'external_pg' === $source ) {
        $result = simple_hotel_crm_get_external_calendar_data( $month, $year );
    } elseif ( 'wp_sync' === $source ) {
        $result = simple_hotel_crm_get_wp_sync_calendar_data( $month, $year );
    } else {
        $result = simple_hotel_crm_get_motopress_calendar_data( $month, $year );
    }

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

