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

    WP_CLI::add_command( 'simple-hotel-crm find-duplicates', function( $args, $assoc_args ) {
        $do_delete = isset( $assoc_args['delete'] );
        $dry_run = ! $do_delete;
        $source = isset( $assoc_args['source'] ) ? $assoc_args['source'] : 'booking_com';
        $skip_empty = isset( $assoc_args['skip-empty'] );

        WP_CLI::line( "Finding duplicates for source_channel: {$source}" );
        if ( $dry_run ) {
            WP_CLI::line( 'DRY RUN — use --delete to actually remove duplicates.' );
        }
        WP_CLI::line( '' );

        $duplicates = simple_hotel_crm_find_duplicate_bookings( $source, ! $skip_empty );

        if ( empty( $duplicates ) ) {
            WP_CLI::success( 'No duplicates found.' );
            return;
        }

        $total_dupes = 0;
        foreach ( $duplicates as $group ) {
            $total_dupes += count( $group['duplicates'] );
        }

        WP_CLI::line( "Found " . count( $duplicates ) . " groups with " . $total_dupes . " duplicate bookings." );
        WP_CLI::line( '' );

        foreach ( $duplicates as $group ) {
            $sid = $group['source_booking_id'] ?: '(empty source_booking_id)';
            $keeper = $group['keeper'];
            $room_count_keeper = $group['room_counts'][ $keeper['id'] ] ?? 0;

            $first_date = $keeper['check_in_date'] ?? '?';

            WP_CLI::line( "--- Group: {$sid} ({$group['count']} bookings, keeper ID {$keeper['id']}, {$room_count_keeper} rooms, {$first_date}) ---" );
            WP_CLI::line( "  KEEPER: ID {$keeper['id']}, {$room_count_keeper} rooms, €{$keeper['total_amount']}, guest {$keeper['guest_id']}" );

            foreach ( $group['duplicates'] as $dup ) {
                $rc = $group['room_counts'][ $dup['id'] ] ?? 0;
                WP_CLI::line( "  DUPE:   ID {$dup['id']}, {$rc} rooms, €{$dup['total_amount']}, guest {$dup['guest_id']}, status {$dup['status_code']}" );
            }
            WP_CLI::line( '' );
        }

        if ( $dry_run ) {
            WP_CLI::line( "Run with --delete to remove these {$total_dupes} duplicate bookings." );
            return;
        }

        WP_CLI::confirm( "Are you sure you want to delete {$total_dupes} duplicate bookings?" );

        $results = simple_hotel_crm_delete_duplicate_bookings( $duplicates, false );
        $deleted_count = count( array_filter( $results, function( $r ) {
            return $r['deleted'];
        } ) );
        WP_CLI::success( "Deleted {$deleted_count} duplicate bookings." );

        simple_hotel_crm_clear_calendar_cache();
    } );

}
