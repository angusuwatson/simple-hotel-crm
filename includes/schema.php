<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function simple_hotel_crm_install_tables() {
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate = $wpdb->get_charset_collate();
    $daily_notes_table = simple_hotel_crm_daily_notes_table();
    $overlay_table     = simple_hotel_crm_booking_overlay_table();
    $booking_notes_table = simple_hotel_crm_booking_notes_table();
    $sync_rooms_table  = simple_hotel_crm_sync_rooms_table();
    $sync_bookings_table = simple_hotel_crm_sync_bookings_table();
    $crm_rooms_table   = simple_hotel_crm_rooms_table();
    $crm_room_pricing_table = simple_hotel_crm_room_pricing_table();
    $crm_guests_table  = simple_hotel_crm_guests_table();
    $crm_bookings_table = simple_hotel_crm_bookings_table();
    $crm_booking_rooms_table = simple_hotel_crm_booking_rooms_table();
    $crm_booking_nights_table = simple_hotel_crm_booking_room_nights_table();

    $sql_daily_notes = "CREATE TABLE {$daily_notes_table} (
        note_date date NOT NULL,
        note_text longtext NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (note_date)
    ) {$charset_collate};";

    $sql_overlays = "CREATE TABLE {$overlay_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        booking_id bigint(20) unsigned NOT NULL DEFAULT 0,
        reserved_room_id bigint(20) unsigned NOT NULL DEFAULT 0,
        room_id bigint(20) unsigned NOT NULL DEFAULT 0,
        booking_note longtext NULL,
        extras_formula text NULL,
        extras_total decimal(10,2) NULL,
        manual_guest_name varchar(255) NULL,
        manual_adults int(11) NULL,
        manual_children int(11) NULL,
        manual_tarif decimal(10,2) NULL,
        manual_commission decimal(10,2) NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY reserved_room_id (reserved_room_id),
        KEY booking_id (booking_id),
        KEY room_id (room_id)
    ) {$charset_collate};";

    $sql_booking_notes = "CREATE TABLE {$booking_notes_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        booking_id bigint(20) unsigned NOT NULL,
        booking_room_id bigint(20) unsigned NULL,
        stay_date date NULL,
        note_scope varchar(20) NOT NULL DEFAULT 'booking',
        note_text longtext NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY booking_id (booking_id),
        KEY booking_room_id (booking_room_id),
        KEY stay_date (stay_date),
        KEY note_scope (note_scope)
    ) {$charset_collate};";

    $sql_sync_rooms = "CREATE TABLE {$sync_rooms_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        external_room_id bigint(20) unsigned NOT NULL DEFAULT 0,
        room_code varchar(50) NOT NULL,
        room_name varchar(255) NOT NULL,
        sort_order int(11) NOT NULL DEFAULT 0,
        active tinyint(1) NOT NULL DEFAULT 1,
        synced_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY external_room_id (external_room_id),
        KEY room_code (room_code),
        KEY active (active)
    ) {$charset_collate};";

    $sql_sync_bookings = "CREATE TABLE {$sync_bookings_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        external_booking_id bigint(20) unsigned NOT NULL DEFAULT 0,
        external_booking_room_id bigint(20) unsigned NOT NULL DEFAULT 0,
        external_room_id bigint(20) unsigned NOT NULL DEFAULT 0,
        room_sync_id bigint(20) unsigned NOT NULL DEFAULT 0,
        status_code varchar(50) NOT NULL,
        check_in date NOT NULL,
        check_out date NOT NULL,
        stay_date date NOT NULL,
        guest_count int(11) NOT NULL DEFAULT 0,
        adults int(11) NOT NULL DEFAULT 0,
        children int(11) NOT NULL DEFAULT 0,
        babies int(11) NOT NULL DEFAULT 0,
        total_amount decimal(10,2) NOT NULL DEFAULT 0.00,
        room_amount decimal(10,2) NOT NULL DEFAULT 0.00,
        extras_amount decimal(10,2) NULL,
        tourist_tax_amount decimal(10,2) NULL,
        room_count int(11) NOT NULL DEFAULT 1,
        source_channel varchar(50) NOT NULL,
        source_booking_id varchar(191) NULL,
        channel_label varchar(191) NULL,
        guest_name varchar(255) NOT NULL,
        phone varchar(100) NULL,
        import_notes longtext NULL,
        invoice_ninja_client_id varchar(191) NULL,
        invoice_ninja_invoice_id varchar(191) NULL,
        source_created_at datetime NULL,
        synced_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY external_booking_room_night (external_booking_room_id, stay_date),
        KEY room_sync_id (room_sync_id),
        KEY external_booking_id (external_booking_id),
        KEY external_room_id (external_room_id),
        KEY stay_date (stay_date),
        KEY booking_dates (check_in, check_out),
        KEY status_code (status_code)
    ) {$charset_collate};";

    $sql_crm_rooms = "CREATE TABLE {$crm_rooms_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        sync_room_id bigint(20) unsigned NULL,
        external_room_id bigint(20) unsigned NULL,
        room_code varchar(50) NOT NULL,
        room_name varchar(255) NOT NULL,
        sort_order int(11) NOT NULL DEFAULT 0,
        color varchar(20) NULL,
        active tinyint(1) NOT NULL DEFAULT 1,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY sync_room_id (sync_room_id),
        KEY external_room_id (external_room_id),
        KEY room_code (room_code),
        KEY active (active)
    ) {$charset_collate};";

    $sql_crm_room_pricing = "CREATE TABLE {$crm_room_pricing_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        room_id bigint(20) unsigned NOT NULL,
        occupancy_adults int(11) NOT NULL DEFAULT 1,
        price_amount decimal(10,2) NOT NULL DEFAULT 0.00,
        active tinyint(1) NOT NULL DEFAULT 1,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY room_occupancy (room_id, occupancy_adults),
        KEY room_id (room_id),
        KEY active (active)
    ) {$charset_collate};";

    $sql_crm_guests = "CREATE TABLE {$crm_guests_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        first_name varchar(100) NULL,
        last_name varchar(100) NULL,
        email varchar(191) NULL,
        phone varchar(100) NULL,
        address_line_1 varchar(191) NULL,
        address_line_2 varchar(191) NULL,
        city varchar(100) NULL,
        postcode varchar(40) NULL,
        country varchar(100) NULL,
        notes longtext NULL,
        is_deleted tinyint(1) NOT NULL DEFAULT 0,
        deleted_at datetime NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY email (email),
        KEY phone (phone),
        KEY last_name (last_name)
    ) {$charset_collate};";

    $sql_crm_bookings = "CREATE TABLE {$crm_bookings_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        guest_id bigint(20) unsigned NOT NULL,
        source_channel varchar(50) NOT NULL,
        source_booking_id varchar(191) NULL,
        status_code varchar(50) NOT NULL,
        contacted_date date NULL,
        check_in_date date NOT NULL,
        check_out_date date NOT NULL,
        adults int(11) NOT NULL DEFAULT 0,
        children int(11) NOT NULL DEFAULT 0,
        babies int(11) NOT NULL DEFAULT 0,
        room_rate_amount decimal(10,2) NOT NULL DEFAULT 0.00,
        extras_amount decimal(10,2) NOT NULL DEFAULT 0.00,
        tourist_tax_amount decimal(10,2) NOT NULL DEFAULT 0.00,
        total_amount decimal(10,2) NOT NULL DEFAULT 0.00,
        currency varchar(10) NOT NULL DEFAULT 'EUR',
        booking_note longtext NULL,
        special_requests longtext NULL,
        internal_notes longtext NULL,
        invoice_ninja_client_id varchar(191) NULL,
        invoice_ninja_invoice_id varchar(191) NULL,
        invoiced_at datetime NULL,
        is_deleted tinyint(1) NOT NULL DEFAULT 0,
        deleted_at datetime NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY guest_id (guest_id),
        KEY status_code (status_code),
        KEY booking_dates (check_in_date, check_out_date)
    ) {$charset_collate};";

    $sql_crm_booking_rooms = "CREATE TABLE {$crm_booking_rooms_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        booking_id bigint(20) unsigned NOT NULL,
        room_id bigint(20) unsigned NOT NULL,
        legacy_reserved_room_id bigint(20) unsigned NULL,
        pricing_room_id bigint(20) unsigned NULL,
        occupancy_adults int(11) NOT NULL DEFAULT 0,
        guest_count int(11) NOT NULL DEFAULT 0,
        adults int(11) NOT NULL DEFAULT 0,
        children int(11) NOT NULL DEFAULT 0,
        babies int(11) NOT NULL DEFAULT 0,
        base_price_amount decimal(10,2) NOT NULL DEFAULT 0.00,
        discount_type varchar(20) NOT NULL DEFAULT 'none',
        discount_value decimal(10,2) NOT NULL DEFAULT 0.00,
        discount_amount decimal(10,2) NOT NULL DEFAULT 0.00,
        subtotal_amount decimal(10,2) NOT NULL DEFAULT 0.00,
        commission_amount decimal(10,2) NOT NULL DEFAULT 0.00,
        room_rate_amount decimal(10,2) NOT NULL DEFAULT 0.00,
        extras_amount decimal(10,2) NOT NULL DEFAULT 0.00,
        tourist_tax_amount decimal(10,2) NOT NULL DEFAULT 0.00,
        total_amount decimal(10,2) NOT NULL DEFAULT 0.00,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY legacy_reserved_room_id (legacy_reserved_room_id),
        KEY booking_id (booking_id),
        KEY room_id (room_id),
        KEY pricing_room_id (pricing_room_id)
    ) {$charset_collate};";

    $sql_crm_booking_nights = "CREATE TABLE {$crm_booking_nights_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        booking_room_id bigint(20) unsigned NOT NULL,
        stay_date date NOT NULL,
        guest_count int(11) NOT NULL DEFAULT 0,
        adults int(11) NOT NULL DEFAULT 0,
        children int(11) NOT NULL DEFAULT 0,
        babies int(11) NOT NULL DEFAULT 0,
        base_price_amount decimal(10,2) NOT NULL DEFAULT 0.00,
        discount_amount decimal(10,2) NOT NULL DEFAULT 0.00,
        subtotal_amount decimal(10,2) NOT NULL DEFAULT 0.00,
        commission_amount decimal(10,2) NOT NULL DEFAULT 0.00,
        room_rate_amount decimal(10,2) NOT NULL DEFAULT 0.00,
        extras_amount decimal(10,2) NOT NULL DEFAULT 0.00,
        tourist_tax_amount decimal(10,2) NOT NULL DEFAULT 0.00,
        total_amount decimal(10,2) NOT NULL DEFAULT 0.00,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY booking_room_stay (booking_room_id, stay_date),
        KEY stay_date (stay_date)
    ) {$charset_collate};";

    dbDelta( $sql_daily_notes );
    dbDelta( $sql_overlays );
    dbDelta( $sql_booking_notes );
    dbDelta( $sql_sync_rooms );
    dbDelta( $sql_sync_bookings );
    dbDelta( $sql_crm_rooms );
    dbDelta( $sql_crm_room_pricing );
    dbDelta( $sql_crm_guests );
    dbDelta( $sql_crm_bookings );
    dbDelta( $sql_crm_booking_rooms );
    dbDelta( $sql_crm_booking_nights );

    simple_hotel_crm_seed_rooms_table();
    simple_hotel_crm_maybe_migrate_sync_data_to_crm();
    simple_hotel_crm_repair_booking_room_links();
    simple_hotel_crm_migrate_overlay_notes_to_booking_notes();
    simple_hotel_crm_run_repair_routines();

    update_option( 'simple_hotel_crm_db_version', SIMPLE_HOTEL_CRM_DB_VERSION );
}

function simple_hotel_crm_daily_notes_table() {
    global $wpdb;
    return $wpdb->prefix . 'simple_hotel_crm_daily_notes';
}

function simple_hotel_crm_booking_overlay_table() {
    global $wpdb;
    return $wpdb->prefix . 'simple_hotel_crm_booking_overlays';
}

function simple_hotel_crm_booking_notes_table() {
    global $wpdb;
    return $wpdb->prefix . 'simple_hotel_crm_booking_notes';
}

function simple_hotel_crm_sync_rooms_table() {
    global $wpdb;
    return $wpdb->prefix . 'simple_hotel_crm_sync_rooms';
}

function simple_hotel_crm_sync_bookings_table() {
    global $wpdb;
    return $wpdb->prefix . 'simple_hotel_crm_sync_bookings';
}

function simple_hotel_crm_rooms_table() {
    global $wpdb;
    return $wpdb->prefix . 'simple_hotel_crm_rooms';
}

function simple_hotel_crm_room_pricing_table() {
    global $wpdb;
    return $wpdb->prefix . 'simple_hotel_crm_room_pricing';
}

function simple_hotel_crm_guests_table() {
    global $wpdb;
    return $wpdb->prefix . 'simple_hotel_crm_guests';
}

function simple_hotel_crm_bookings_table() {
    global $wpdb;
    return $wpdb->prefix . 'simple_hotel_crm_bookings';
}

function simple_hotel_crm_booking_rooms_table() {
    global $wpdb;
    return $wpdb->prefix . 'simple_hotel_crm_booking_rooms';
}

function simple_hotel_crm_booking_room_nights_table() {
    global $wpdb;
    return $wpdb->prefix . 'simple_hotel_crm_booking_room_nights';
}

function simple_hotel_crm_seed_rooms_table() {
    global $wpdb;

    $sync_rooms_table = simple_hotel_crm_sync_rooms_table();
    $crm_rooms_table = simple_hotel_crm_rooms_table();

    $sync_rooms = $wpdb->get_results( "SELECT id, external_room_id, room_code, room_name, sort_order, active FROM {$sync_rooms_table}", ARRAY_A );
    if ( empty( $sync_rooms ) ) {
        return;
    }

    $colors = simple_hotel_crm_get_room_colors();
    foreach ( $sync_rooms as $room ) {
        $wpdb->replace(
            $crm_rooms_table,
            [
                'sync_room_id' => (int) $room['id'],
                'external_room_id' => (int) $room['external_room_id'],
                'room_code' => (string) $room['room_code'],
                'room_name' => (string) $room['room_name'],
                'sort_order' => (int) $room['sort_order'],
                'color' => $colors[ strtoupper( (string) $room['room_code'] ) ] ?? '#cccccc',
                'active' => (int) $room['active'],
            ],
            [ '%d', '%d', '%s', '%s', '%d', '%s', '%d' ]
        );
    }
}

function simple_hotel_crm_repair_booking_room_links() {
    global $wpdb;

    $crm_rooms_table = simple_hotel_crm_rooms_table();
    $crm_booking_rooms_table = simple_hotel_crm_booking_rooms_table();
    $sync_bookings_table = simple_hotel_crm_sync_bookings_table();

    $sync_rows = $wpdb->get_results( "SELECT DISTINCT external_booking_room_id, room_sync_id, external_room_id FROM {$sync_bookings_table} WHERE external_booking_room_id > 0", ARRAY_A );
    foreach ( $sync_rows as $sync_row ) {
        $crm_room_id = simple_hotel_crm_find_crm_room_id( (int) $sync_row['room_sync_id'], [ 'room_code' => '', 'room_name' => '' ] );
        if ( $crm_room_id <= 0 ) {
            $crm_room_id = simple_hotel_crm_find_crm_room_id( (int) $sync_row['external_room_id'] );
        }
        if ( $crm_room_id <= 0 ) {
            continue;
        }
        $wpdb->update(
            $crm_booking_rooms_table,
            [ 'room_id' => $crm_room_id ],
            [ 'legacy_reserved_room_id' => (int) $sync_row['external_booking_room_id'] ],
            [ '%d' ],
            [ '%d' ]
        );
    }
}

function simple_hotel_crm_migrate_overlay_notes_to_booking_notes() {
    global $wpdb;

    $crm_bookings_table = simple_hotel_crm_bookings_table();
    $crm_booking_rooms_table = simple_hotel_crm_booking_rooms_table();
    $overlay_table = simple_hotel_crm_booking_overlay_table();

    $wpdb->query(
        "UPDATE {$crm_bookings_table} b
         JOIN (
            SELECT br.booking_id, MIN(o.booking_note) AS booking_note
            FROM {$crm_booking_rooms_table} br
            JOIN {$overlay_table} o ON o.reserved_room_id = br.legacy_reserved_room_id
            WHERE o.booking_note IS NOT NULL AND o.booking_note <> ''
            GROUP BY br.booking_id
         ) notes ON notes.booking_id = b.id
         SET b.booking_note = notes.booking_note
         WHERE (b.booking_note IS NULL OR b.booking_note = '')"
    );
}

function simple_hotel_crm_backfill_pricing_amounts() {
    global $wpdb;

    $booking_rooms_table = simple_hotel_crm_booking_rooms_table();
    $booking_nights_table = simple_hotel_crm_booking_room_nights_table();
    $updated = 0;

    if ( simple_hotel_crm_table_has_column( $booking_rooms_table, 'subtotal_amount' ) ) {
        $wpdb->query( "UPDATE {$booking_rooms_table} SET subtotal_amount = room_rate_amount WHERE subtotal_amount = 0 AND room_rate_amount > 0" );
        $updated += max( 0, (int) $wpdb->rows_affected );
    }
    if ( simple_hotel_crm_table_has_column( $booking_rooms_table, 'base_price_amount' ) ) {
        $wpdb->query( "UPDATE {$booking_rooms_table} SET base_price_amount = room_rate_amount WHERE base_price_amount = 0 AND room_rate_amount > 0" );
        $updated += max( 0, (int) $wpdb->rows_affected );
    }
    if ( simple_hotel_crm_table_has_column( $booking_nights_table, 'subtotal_amount' ) ) {
        $wpdb->query( "UPDATE {$booking_nights_table} SET subtotal_amount = room_rate_amount WHERE subtotal_amount = 0 AND room_rate_amount > 0" );
        $updated += max( 0, (int) $wpdb->rows_affected );
    }
    if ( simple_hotel_crm_table_has_column( $booking_nights_table, 'base_price_amount' ) ) {
        $wpdb->query( "UPDATE {$booking_nights_table} SET base_price_amount = room_rate_amount WHERE base_price_amount = 0 AND room_rate_amount > 0" );
        $updated += max( 0, (int) $wpdb->rows_affected );
    }

    return $updated;
}

function simple_hotel_crm_backfill_commission_amounts() {
    global $wpdb;

    $bookings_table = simple_hotel_crm_bookings_table();
    $booking_rooms_table = simple_hotel_crm_booking_rooms_table();
    $booking_nights_table = simple_hotel_crm_booking_room_nights_table();
    $updated = 0;

    $room_rows = $wpdb->get_results(
        "SELECT br.id, br.booking_id, br.room_rate_amount, b.source_channel
         FROM {$booking_rooms_table} br
         JOIN {$bookings_table} b ON b.id = br.booking_id
         WHERE br.commission_amount = 0",
        ARRAY_A
    );
    foreach ( $room_rows as $room_row ) {
        $commission_amount = simple_hotel_crm_calculate_channel_commission( (string) $room_row['source_channel'], (float) $room_row['room_rate_amount'] );
        if ( $commission_amount <= 0 ) {
            continue;
        }
        $result = $wpdb->update( $booking_rooms_table, [ 'commission_amount' => $commission_amount ], [ 'id' => (int) $room_row['id'] ], [ '%f' ], [ '%d' ] );
        if ( false !== $result ) {
            $updated += (int) $result;
        }
    }

    $night_rows = $wpdb->get_results(
        "SELECT brn.id, brn.room_rate_amount, b.source_channel
         FROM {$booking_nights_table} brn
         JOIN {$booking_rooms_table} br ON br.id = brn.booking_room_id
         JOIN {$bookings_table} b ON b.id = br.booking_id
         WHERE brn.commission_amount = 0",
        ARRAY_A
    );
    foreach ( $night_rows as $night_row ) {
        $commission_amount = simple_hotel_crm_calculate_channel_commission( (string) $night_row['source_channel'], (float) $night_row['room_rate_amount'] );
        if ( $commission_amount <= 0 ) {
            continue;
        }
        $result = $wpdb->update( $booking_nights_table, [ 'commission_amount' => $commission_amount ], [ 'id' => (int) $night_row['id'] ], [ '%f' ], [ '%d' ] );
        if ( false !== $result ) {
            $updated += (int) $result;
        }
    }

    return $updated;
}

function simple_hotel_crm_recalculate_booking_header_totals() {
    global $wpdb;

    $bookings_table = simple_hotel_crm_bookings_table();
    $booking_rooms_table = simple_hotel_crm_booking_rooms_table();

    $wpdb->query(
        "UPDATE {$bookings_table} b
         JOIN (
            SELECT booking_id,
                   COALESCE(SUM(adults), 0) AS adults,
                   COALESCE(SUM(children), 0) AS children,
                   COALESCE(SUM(babies), 0) AS babies,
                   COALESCE(SUM(room_rate_amount), 0) AS room_rate_amount,
                   COALESCE(SUM(extras_amount), 0) AS extras_amount,
                   COALESCE(SUM(tourist_tax_amount), 0) AS tourist_tax_amount,
                   COALESCE(SUM(total_amount), 0) AS total_amount
            FROM {$booking_rooms_table}
            GROUP BY booking_id
         ) room_totals ON room_totals.booking_id = b.id
         SET b.adults = room_totals.adults,
             b.children = room_totals.children,
             b.babies = room_totals.babies,
             b.room_rate_amount = room_totals.room_rate_amount,
             b.extras_amount = room_totals.extras_amount,
             b.tourist_tax_amount = room_totals.tourist_tax_amount,
             b.total_amount = room_totals.total_amount"
    );

    return max( 0, (int) $wpdb->rows_affected );
}

function simple_hotel_crm_run_repair_routines() {
    return [
        'pricing_rows' => simple_hotel_crm_backfill_pricing_amounts(),
        'commission_rows' => simple_hotel_crm_backfill_commission_amounts(),
        'booking_headers' => simple_hotel_crm_recalculate_booking_header_totals(),
    ];
}

function simple_hotel_crm_get_repair_scan_counts() {
    global $wpdb;

    $bookings_table = simple_hotel_crm_bookings_table();
    $booking_rooms_table = simple_hotel_crm_booking_rooms_table();
    $booking_nights_table = simple_hotel_crm_booking_room_nights_table();

    $counts = [
        'pricing_rows' => 0,
        'commission_rows' => 0,
        'booking_headers' => 0,
    ];

    if ( simple_hotel_crm_table_has_column( $booking_rooms_table, 'subtotal_amount' ) ) {
        $counts['pricing_rows'] += (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$booking_rooms_table} WHERE subtotal_amount = 0 AND room_rate_amount > 0" );
    }
    if ( simple_hotel_crm_table_has_column( $booking_rooms_table, 'base_price_amount' ) ) {
        $counts['pricing_rows'] += (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$booking_rooms_table} WHERE base_price_amount = 0 AND room_rate_amount > 0" );
    }
    if ( simple_hotel_crm_table_has_column( $booking_nights_table, 'subtotal_amount' ) ) {
        $counts['pricing_rows'] += (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$booking_nights_table} WHERE subtotal_amount = 0 AND room_rate_amount > 0" );
    }
    if ( simple_hotel_crm_table_has_column( $booking_nights_table, 'base_price_amount' ) ) {
        $counts['pricing_rows'] += (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$booking_nights_table} WHERE base_price_amount = 0 AND room_rate_amount > 0" );
    }
    if ( simple_hotel_crm_table_has_column( $booking_rooms_table, 'commission_amount' ) ) {
        $counts['commission_rows'] += (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$booking_rooms_table} br JOIN {$bookings_table} b ON b.id = br.booking_id WHERE br.commission_amount = 0 AND br.room_rate_amount > 0 AND b.source_channel = 'booking_com'" );
    }
    if ( simple_hotel_crm_table_has_column( $booking_nights_table, 'commission_amount' ) ) {
        $counts['commission_rows'] += (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$booking_nights_table} brn JOIN {$booking_rooms_table} br ON br.id = brn.booking_room_id JOIN {$bookings_table} b ON b.id = br.booking_id WHERE brn.commission_amount = 0 AND brn.room_rate_amount > 0 AND b.source_channel = 'booking_com'" );
    }
    $counts['booking_headers'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$bookings_table} b LEFT JOIN (SELECT booking_id, COALESCE(SUM(total_amount), 0) AS room_total_amount FROM {$booking_rooms_table} GROUP BY booking_id) room_totals ON room_totals.booking_id = b.id WHERE COALESCE(b.total_amount, 0) <> COALESCE(room_totals.room_total_amount, 0)" );

    return $counts;
}

function simple_hotel_crm_booking_is_enriched( $booking ) {
    global $wpdb;

    $booking = is_array( $booking ) ? $booking : [];
    $booking_id = (int) ( $booking['id'] ?? 0 );
    if ( $booking_id <= 0 ) {
        return false;
    }

    $booking_rooms_table = simple_hotel_crm_booking_rooms_table();
    $room_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$booking_rooms_table} WHERE booking_id = %d", $booking_id ) );
    if ( $room_count > 0 ) {
        return true;
    }

    return ( (float) ( $booking['room_rate_amount'] ?? 0 ) > 0 )
        || ( (float) ( $booking['extras_amount'] ?? 0 ) > 0 )
        || ( (float) ( $booking['tourist_tax_amount'] ?? 0 ) > 0 )
        || ( (float) ( $booking['total_amount'] ?? 0 ) > 0 )
        || ! empty( $booking['booking_note'] )
        || ! empty( $booking['internal_notes'] );
}

function simple_hotel_crm_import_sync_data_to_crm() {
    global $wpdb;

    $crm_bookings_table = simple_hotel_crm_bookings_table();
    $crm_booking_rooms_table = simple_hotel_crm_booking_rooms_table();
    $crm_booking_nights_table = simple_hotel_crm_booking_room_nights_table();
    $sync_bookings_table = simple_hotel_crm_sync_bookings_table();

    $sync_booking_count = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT external_booking_id) FROM {$sync_bookings_table}" );
    if ( $sync_booking_count <= 0 ) {
        return;
    }

    $booking_groups = $wpdb->get_results( "SELECT external_booking_id, MIN(source_booking_id) AS source_booking_id, MIN(guest_name) AS guest_name, MIN(phone) AS phone, MIN(source_channel) AS source_channel, MIN(status_code) AS status_code, MIN(check_in) AS check_in_date, MIN(check_out) AS check_out_date, MIN(source_created_at) AS source_created_at, MIN(import_notes) AS import_notes, MIN(invoice_ninja_client_id) AS invoice_ninja_client_id, MIN(invoice_ninja_invoice_id) AS invoice_ninja_invoice_id FROM {$sync_bookings_table} GROUP BY external_booking_id ORDER BY external_booking_id ASC", ARRAY_A );

    foreach ( $booking_groups as $booking_group ) {
        $room_groups = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT external_booking_room_id, room_sync_id, external_room_id, MIN(adults) AS adults, MIN(children) AS children, MIN(babies) AS babies, MIN(guest_count) AS guest_count, SUM(room_amount) AS room_rate_amount, SUM(COALESCE(extras_amount, 0)) AS extras_amount, SUM(COALESCE(tourist_tax_amount, 0)) AS tourist_tax_amount, SUM(total_amount) AS total_amount FROM {$sync_bookings_table} WHERE external_booking_id = %d GROUP BY external_booking_room_id, room_sync_id, external_room_id ORDER BY external_booking_room_id ASC",
                $booking_group['external_booking_id']
            ),
            ARRAY_A
        );
        if ( empty( $room_groups ) ) {
            continue;
        }

        list( $first_name, $last_name ) = simple_hotel_crm_split_guest_name( $booking_group['guest_name'] );
        $guest_note = 'Booking.com ICS import ' . (string) ( $booking_group['source_booking_id'] ?: $booking_group['external_booking_id'] );
        $guest = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . simple_hotel_crm_guests_table() . " WHERE notes LIKE %s LIMIT 1", '%' . $wpdb->esc_like( $guest_note ) . '%' ), ARRAY_A );
        if ( ! $guest ) {
            $guest_inserted = $wpdb->insert(
                simple_hotel_crm_guests_table(),
                [
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'phone' => (string) $booking_group['phone'],
                    'notes' => $guest_note,
                ],
                [ '%s', '%s', '%s', '%s' ]
            );
            if ( false === $guest_inserted ) {
                continue;
            }
            $guest = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . simple_hotel_crm_guests_table() . " WHERE id = %d", (int) $wpdb->insert_id ), ARRAY_A );
        }
        if ( ! $guest ) {
            continue;
        }

        $booking = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$crm_bookings_table} WHERE source_channel = %s AND (source_booking_id = %s OR source_booking_id = %s) LIMIT 1",
                (string) $booking_group['source_channel'],
                (string) ( $booking_group['source_booking_id'] ?: '' ),
                (string) $booking_group['external_booking_id']
            ),
            ARRAY_A
        );
        if ( 'booking_com' === (string) ( $booking_group['source_channel'] ?? '' ) && $booking ) {
            $real_source_booking_id = (string) ( $booking_group['source_booking_id'] ?: '' );
            if ( '' !== $real_source_booking_id && (string) $booking['source_booking_id'] !== $real_source_booking_id ) {
                $wpdb->update( $crm_bookings_table, [ 'source_booking_id' => $real_source_booking_id ], [ 'id' => (int) $booking['id'] ], [ '%s' ], [ '%d' ] );
            }
            continue;
        }

        if ( ! $booking ) {
            $booking_adults = array_sum( array_map( 'intval', wp_list_pluck( $room_groups, 'adults' ) ) );
            $booking_children = array_sum( array_map( 'intval', wp_list_pluck( $room_groups, 'children' ) ) );
            $booking_babies = array_sum( array_map( 'intval', wp_list_pluck( $room_groups, 'babies' ) ) );
            $booking_room_rate = round( array_sum( array_map( 'floatval', wp_list_pluck( $room_groups, 'room_rate_amount' ) ) ), 2 );
            $booking_extras = round( array_sum( array_map( 'floatval', wp_list_pluck( $room_groups, 'extras_amount' ) ) ), 2 );
            $booking_tax = round( array_sum( array_map( 'floatval', wp_list_pluck( $room_groups, 'tourist_tax_amount' ) ) ), 2 );
            $booking_total = round( array_sum( array_map( 'floatval', wp_list_pluck( $room_groups, 'total_amount' ) ) ), 2 );

            $booking_inserted = $wpdb->insert(
                $crm_bookings_table,
                [
                    'guest_id' => (int) $guest['id'],
                    'source_channel' => (string) $booking_group['source_channel'],
                    'source_booking_id' => (string) ( $booking_group['source_booking_id'] ?: $booking_group['external_booking_id'] ),
                    'status_code' => (string) $booking_group['status_code'],
                    'contacted_date' => ! empty( $booking_group['source_created_at'] ) ? substr( (string) $booking_group['source_created_at'], 0, 10 ) : null,
                    'check_in_date' => (string) $booking_group['check_in_date'],
                    'check_out_date' => (string) $booking_group['check_out_date'],
                    'adults' => $booking_adults,
                    'children' => $booking_children,
                    'babies' => $booking_babies,
                    'room_rate_amount' => $booking_room_rate,
                    'extras_amount' => $booking_extras,
                    'tourist_tax_amount' => $booking_tax,
                    'total_amount' => $booking_total,
                    'currency' => 'EUR',
                    'booking_note' => '',
                    'internal_notes' => (string) $booking_group['import_notes'],
                    'invoice_ninja_client_id' => (string) $booking_group['invoice_ninja_client_id'],
                    'invoice_ninja_invoice_id' => (string) $booking_group['invoice_ninja_invoice_id'],
                ],
                [ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%f', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%s' ]
            );
            if ( false === $booking_inserted ) {
                continue;
            }
            $booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$crm_bookings_table} WHERE id = %d", (int) $wpdb->insert_id ), ARRAY_A );
        }
        if ( ! $booking ) {
            continue;
        }

        foreach ( $room_groups as $room_group ) {
            $crm_room_id = simple_hotel_crm_find_crm_room_id( (int) $room_group['room_sync_id'] );
            if ( $crm_room_id <= 0 ) {
                $crm_room_id = simple_hotel_crm_find_crm_room_id( (int) $room_group['external_room_id'] );
            }
            if ( $crm_room_id <= 0 ) {
                continue;
            }

            $existing_booking_room_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$crm_booking_rooms_table} WHERE legacy_reserved_room_id = %d OR (booking_id = %d AND room_id = %d) LIMIT 1", (int) $room_group['external_booking_room_id'], (int) $booking['id'], $crm_room_id ) );
            if ( $existing_booking_room_id > 0 ) {
                continue;
            }

            $booking_room_inserted = $wpdb->insert(
                $crm_booking_rooms_table,
                [
                    'booking_id' => (int) $booking['id'],
                    'room_id' => $crm_room_id,
                    'legacy_reserved_room_id' => (int) $room_group['external_booking_room_id'],
                    'guest_count' => (int) $room_group['guest_count'],
                    'adults' => (int) $room_group['adults'],
                    'children' => (int) $room_group['children'],
                    'babies' => (int) $room_group['babies'],
                    'room_rate_amount' => (float) $room_group['room_rate_amount'],
                    'extras_amount' => (float) $room_group['extras_amount'],
                    'tourist_tax_amount' => (float) $room_group['tourist_tax_amount'],
                    'total_amount' => (float) $room_group['total_amount'],
                ],
                [ '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%f', '%f', '%f', '%f' ]
            );
            if ( false === $booking_room_inserted ) {
                continue;
            }
            $crm_booking_room_id = (int) $wpdb->insert_id;

            $night_rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT stay_date, guest_count, adults, children, babies, room_amount, COALESCE(extras_amount, 0) AS extras_amount, COALESCE(tourist_tax_amount, 0) AS tourist_tax_amount, total_amount FROM {$sync_bookings_table} WHERE external_booking_room_id = %d ORDER BY stay_date ASC",
                    $room_group['external_booking_room_id']
                ),
                ARRAY_A
            );

            foreach ( $night_rows as $night_row ) {
                $exists_night = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$crm_booking_nights_table} WHERE booking_room_id = %d AND stay_date = %s LIMIT 1", $crm_booking_room_id, (string) $night_row['stay_date'] ) );
                if ( $exists_night > 0 ) {
                    continue;
                }
                $wpdb->insert(
                    $crm_booking_nights_table,
                    [
                        'booking_room_id' => $crm_booking_room_id,
                        'stay_date' => (string) $night_row['stay_date'],
                        'guest_count' => (int) $night_row['guest_count'],
                        'adults' => (int) $night_row['adults'],
                        'children' => (int) $night_row['children'],
                        'babies' => (int) $night_row['babies'],
                        'room_rate_amount' => (float) $night_row['room_amount'],
                        'extras_amount' => (float) $night_row['extras_amount'],
                        'tourist_tax_amount' => (float) $night_row['tourist_tax_amount'],
                        'total_amount' => (float) $night_row['total_amount'],
                    ],
                    [ '%d', '%s', '%d', '%d', '%d', '%d', '%f', '%f', '%f', '%f' ]
                );
            }
        }
    }
}

function simple_hotel_crm_maybe_migrate_sync_data_to_crm() {
    simple_hotel_crm_import_sync_data_to_crm();
}

