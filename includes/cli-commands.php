<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( defined( 'WP_CLI' ) && WP_CLI ) {

    WP_CLI::add_command( 'simple-hotel-crm debug-ics', function( $args, $assoc_args ) {
        $data = simple_hotel_crm_debug_ics_feeds();

        WP_CLI::line( 'Timestamp: ' . $data['timestamp'] );
        WP_CLI::line( '' );

        if ( ! empty( $data['errors'] ) ) {
            WP_CLI::warning( 'Errors:' );
            foreach ( $data['errors'] as $error ) {
                WP_CLI::line( "  - {$error}" );
            }
            WP_CLI::line( '' );
        }

        foreach ( $data['rooms'] as $room ) {
            WP_CLI::line( "Room: {$room['room_name']} (ID {$room['room_id']}, sync_room_id {$room['sync_room_id']}, external_room_id {$room['external_room_id']})" );

            if ( $room['fetch_error'] ) {
                WP_CLI::warning( "  Fetch error: {$room['fetch_error']}" );
                WP_CLI::line( '' );
                continue;
            }

            if ( empty( $room['events'] ) ) {
                WP_CLI::line( '  No events found.' );
                WP_CLI::line( '' );
                continue;
            }

            foreach ( $room['events'] as $ev ) {
                WP_CLI::line( "  UID:          {$ev['uid_raw']}" );
                WP_CLI::line( "  Source ID:    {$ev['source_booking_id']}" );
                WP_CLI::line( "  Ext Booking:  {$ev['external_booking_id']}" );
                WP_CLI::line( "  Ext Room:     {$ev['external_booking_room_id']}" );
                WP_CLI::line( "  Summary:      {$ev['summary']}" );
                WP_CLI::line( "  Check-in:     {$ev['check_in']}" );
                WP_CLI::line( "  Check-out:    {$ev['check_out']}" );
                WP_CLI::line( "  Nights:       {$ev['nights']}" );
                if ( '' !== $ev['description'] ) {
                    WP_CLI::line( "  Description:  {$ev['description']}" );
                }
                WP_CLI::line( '' );
            }
        }

        if ( ! empty( $data['crm_bookings'] ) ) {
            WP_CLI::line( '--- CRM Bookings (source=booking_com) ---' );
            WP_CLI::line( '' );
            foreach ( $data['crm_bookings'] as $bk ) {
                WP_CLI::line( "  Booking ID:     {$bk['id']}" );
                WP_CLI::line( "  Source ID:      {$bk['source_booking_id']}" );
                WP_CLI::line( "  External ID:    {$bk['external_booking_id']}" );
                WP_CLI::line( "  Check-in:       {$bk['check_in_date']}" );
                WP_CLI::line( "  Check-out:      {$bk['check_out_date']}" );
                WP_CLI::line( "  Status:         {$bk['status_code']}" );
                WP_CLI::line( "  Room IDs:       " . implode( ', ', $bk['room_ids'] ) );
                WP_CLI::line( "  Adults:         {$bk['adults']}" );
                WP_CLI::line( "  Total Amount:   {$bk['total_amount']}" );
                WP_CLI::line( "  Guest ID:       {$bk['guest_id']}" );
                WP_CLI::line( "  Skeleton:       " . ( $bk['is_ics_skeleton'] ? 'yes' : 'no' ) );
                WP_CLI::line( "  Notes:          " . ( $bk['internal_notes'] ?? '' ) );
                WP_CLI::line( '' );
            }
        } else {
            WP_CLI::line( 'No CRM bookings with source=booking_com found.' );
        }
    } );

}
