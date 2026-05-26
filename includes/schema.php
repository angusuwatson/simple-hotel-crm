<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function simple_hotel_crm_add_processed_flag() {
    global $wpdb;
    
    $table_name = simple_hotel_crm_bookings_table();
    $wpdb->query("ALTER TABLE $table_name ADD COLUMN is_processed TINYINT(1) NOT NULL DEFAULT 0;");
}

function simple_hotel_crm_install_tables() {
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate = $wpdb->get_charset_collate();
    $daily_notes_table = simple_hotel_crm_daily_notes_table();
    $booking_notes_table = simple_hotel_crm_booking_notes_table();
    $booking_adjustments_table = simple_hotel_crm_booking_adjustments_table();
    $sync_rooms_table  = simple_hotel_crm_sync_rooms_table();
    $sync_bookings_table = simple_hotel_crm_sync_bookings_table();
    $crm_rooms_table   = simple_hotel_crm_rooms_table();
    $crm_room_pricing_table = simple_hotel_crm_room_pricing_table();
    $crm_guests_table  = simple_hotel_crm_guests_table();
    $crm_bookings_table = simple_hotel_crm_bookings_table();
    $crm_booking_rooms_table = simple_hotel_crm_booking_rooms_table();
    $crm_booking_nights_table = simple_hotel_crm_booking_room_nights_table();
    $crm_booking_items_table = simple_hotel_crm_booking_items_table();
    $crm_catalog_items_table = simple_hotel_crm_catalog_items_table();

    $sql_daily_notes = "CREATE TABLE {$daily_notes_table} (
        note_date date NOT NULL,
        note_text longtext NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (note_date)
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

    $sql_booking_adjustments = "CREATE TABLE {$booking_adjustments_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        booking_id bigint(20) unsigned NOT NULL,
        booking_room_id bigint(20) unsigned NOT NULL,
        stay_date date NOT NULL,
        adjustment_kind varchar(20) NOT NULL DEFAULT 'extras',
        formula text NULL,
        amount decimal(10,2) NOT NULL DEFAULT 0.00,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY booking_room_day_kind (booking_room_id, stay_date, adjustment_kind),
        KEY booking_id (booking_id),
        KEY stay_date (stay_date)
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
        invoice_ninja_product_id varchar(191) NULL,
        invoice_ninja_product_key varchar(255) NULL,
        ics_export_token varchar(32) NULL,
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
        date_of_birth date NULL,
        nationality varchar(100) NULL,
        id_document_type varchar(20) NULL,
        id_document_number varchar(100) NULL,
        id_photo_url varchar(255) NULL,
        notes longtext NULL,
        invoice_ninja_client_id varchar(191) NULL,
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
        manual_tarif decimal(10,2) NULL,
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

    $sql_crm_booking_items = "CREATE TABLE {$crm_booking_items_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        booking_id bigint(20) unsigned NOT NULL,
        booking_room_id bigint(20) unsigned NULL,
        stay_date date NULL,
        item_name varchar(255) NOT NULL,
        quantity int(11) NOT NULL DEFAULT 1,
        unit_price decimal(10,2) NOT NULL DEFAULT 0.00,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY booking_id (booking_id),
        KEY booking_room_id (booking_room_id),
        KEY stay_date (stay_date)
    ) {$charset_collate};";

    $sql_crm_catalog_items = "CREATE TABLE {$crm_catalog_items_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        item_name varchar(255) NOT NULL,
        unit_price decimal(10,2) NOT NULL DEFAULT 0.00,
        square_id varchar(191) NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY item_name (item_name)
    ) {$charset_collate};";

    dbDelta( $sql_daily_notes );
    dbDelta( $sql_booking_notes );
    dbDelta( $sql_booking_adjustments );
    dbDelta( $sql_sync_rooms );
    dbDelta( $sql_sync_bookings );
    dbDelta( $sql_crm_rooms );
    dbDelta( $sql_crm_room_pricing );
    dbDelta( $sql_crm_guests );
    dbDelta( $sql_crm_bookings );
    dbDelta( $sql_crm_booking_rooms );
    dbDelta( $sql_crm_booking_nights );
    dbDelta( $sql_crm_booking_items );
    dbDelta( $sql_crm_catalog_items );

    simple_hotel_crm_seed_rooms_table();
    simple_hotel_crm_maybe_migrate_sync_data_to_crm();
    simple_hotel_crm_repair_booking_room_links();
    simple_hotel_crm_run_repair_routines();

    simple_hotel_crm_migrate_overlay_to_booking_rooms();
    simple_hotel_crm_migrate_ics_export_token();
    simple_hotel_crm_migrate_booking_items();
    simple_hotel_crm_migrate_booking_items_rooms();
    simple_hotel_crm_migrate_catalog_items();
    simple_hotel_crm_migrate_room_status_column();

    update_option( 'simple_hotel_crm_db_version', SIMPLE_HOTEL_CRM_DB_VERSION );
}

function simple_hotel_crm_migrate_overlay_to_booking_rooms() {
    global $wpdb;

    $overlay_table = $wpdb->prefix . 'simple_hotel_crm_booking_overlays';
    $booking_rooms_table = simple_hotel_crm_booking_rooms_table();

    $overlay_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$overlay_table}'" );
    if ( ! $overlay_exists ) {
        return;
    }

    if ( ! simple_hotel_crm_table_has_column( $booking_rooms_table, 'manual_tarif' ) ) {
        $wpdb->query( "ALTER TABLE {$booking_rooms_table} ADD COLUMN manual_tarif decimal(10,2) NULL AFTER total_amount" );
    }

    $rows = $wpdb->get_results(
        "SELECT br.id AS booking_room_id, o.manual_tarif
         FROM {$booking_rooms_table} br
         JOIN {$overlay_table} o ON o.reserved_room_id = br.legacy_reserved_room_id
         WHERE o.manual_tarif IS NOT NULL AND o.manual_tarif > 0",
        ARRAY_A
    );
    foreach ( $rows as $row ) {
        $wpdb->update(
            $booking_rooms_table,
            [ 'manual_tarif' => (float) $row['manual_tarif'] ],
            [ 'id' => (int) $row['booking_room_id'] ],
            [ '%f' ],
            [ '%d' ]
        );
    }

    $wpdb->query( "DROP TABLE IF EXISTS {$overlay_table}" );
}

function simple_hotel_crm_migrate_ics_export_token() {
    global $wpdb;
    $rooms_table = simple_hotel_crm_rooms_table();
    if ( ! simple_hotel_crm_table_has_column( $rooms_table, 'ics_export_token' ) ) {
        $wpdb->query( "ALTER TABLE {$rooms_table} ADD COLUMN ics_export_token varchar(32) NULL AFTER invoice_ninja_product_key" );
    }
}

function simple_hotel_crm_migrate_booking_items() {
    global $wpdb;
    $table = simple_hotel_crm_booking_items_table();
    if ( simple_hotel_crm_table_has_column( $table, 'id' ) ) {
        return;
    }
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        booking_id bigint(20) unsigned NOT NULL,
        booking_room_id bigint(20) unsigned NULL,
        stay_date date NULL,
        item_name varchar(255) NOT NULL,
        quantity int(11) NOT NULL DEFAULT 1,
        unit_price decimal(10,2) NOT NULL DEFAULT 0.00,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY booking_id (booking_id),
        KEY booking_room_id (booking_room_id),
        KEY stay_date (stay_date)
    ) {$charset_collate};";
    dbDelta( $sql );
}

function simple_hotel_crm_migrate_booking_items_rooms() {
    global $wpdb;
    $table = simple_hotel_crm_booking_items_table();
    if ( simple_hotel_crm_table_has_column( $table, 'booking_room_id' ) ) {
        return;
    }
    $wpdb->query( "ALTER TABLE {$table} ADD COLUMN booking_room_id bigint(20) unsigned NULL AFTER booking_id" );
    $wpdb->query( "ALTER TABLE {$table} ADD COLUMN stay_date date NULL AFTER booking_room_id" );
    $wpdb->query( "ALTER TABLE {$table} ADD KEY booking_room_id (booking_room_id)" );
    $wpdb->query( "ALTER TABLE {$table} ADD KEY stay_date (stay_date)" );
}

function simple_hotel_crm_get_booking_items( $booking_id, $booking_room_id = null ) {
    global $wpdb;
    $table = simple_hotel_crm_booking_items_table();
    if ( null !== $booking_room_id ) {
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE booking_id = %d AND booking_room_id = %d ORDER BY id ASC", $booking_id, $booking_room_id ), ARRAY_A );
    }
    return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE booking_id = %d ORDER BY id ASC", $booking_id ), ARRAY_A );
}

function simple_hotel_crm_get_booking_items_by_room( $booking_id ) {
    global $wpdb;
    $table = simple_hotel_crm_booking_items_table();
    return $wpdb->get_results( $wpdb->prepare( "SELECT bi.*, br.room_id, sr.room_code, sr.room_name FROM {$table} bi LEFT JOIN " . simple_hotel_crm_booking_rooms_table() . " br ON br.id = bi.booking_room_id LEFT JOIN " . simple_hotel_crm_rooms_table() . " sr ON sr.id = br.room_id WHERE bi.booking_id = %d ORDER BY bi.booking_room_id IS NOT NULL ASC, bi.booking_room_id ASC, bi.stay_date ASC, bi.id ASC", $booking_id ), ARRAY_A );
}

function simple_hotel_crm_get_booking_items_total( $booking_id, $booking_room_id = null ) {
    global $wpdb;
    $table = simple_hotel_crm_booking_items_table();
    if ( null !== $booking_room_id ) {
        return (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(quantity * unit_price), 0) FROM {$table} WHERE booking_id = %d AND booking_room_id = %d", $booking_id, $booking_room_id ) );
    }
    return (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(quantity * unit_price), 0) FROM {$table} WHERE booking_id = %d", $booking_id ) );
}

function simple_hotel_crm_add_booking_item( $booking_id, $item_name, $quantity, $unit_price, $booking_room_id = null, $stay_date = null ) {
    global $wpdb;
    $table = simple_hotel_crm_booking_items_table();
    $data = [
        'booking_id' => absint( $booking_id ),
        'item_name' => sanitize_text_field( (string) $item_name ),
        'quantity' => max( 1, absint( $quantity ) ),
        'unit_price' => round( max( 0, (float) $unit_price ), 2 ),
    ];
    $format = [ '%d', '%s', '%d', '%f' ];
    if ( null !== $booking_room_id ) {
        $data['booking_room_id'] = absint( $booking_room_id );
        $format[] = '%d';
    }
    if ( null !== $stay_date ) {
        $data['stay_date'] = (string) $stay_date;
        $format[] = '%s';
    }
    return $wpdb->insert( $table, $data, $format );
}

function simple_hotel_crm_delete_booking_item( $item_id ) {
    global $wpdb;
    $table = simple_hotel_crm_booking_items_table();
    return $wpdb->delete( $table, [ 'id' => absint( $item_id ) ], [ '%d' ] );
}

function simple_hotel_crm_migrate_catalog_items() {
    global $wpdb;
    $table = simple_hotel_crm_catalog_items_table();
    if ( simple_hotel_crm_table_has_column( $table, 'id' ) ) {
        if ( ! simple_hotel_crm_table_has_column( $table, 'category' ) ) {
            $result = $wpdb->query( "ALTER TABLE {$table} ADD COLUMN category varchar(50) NOT NULL DEFAULT 'other'" );
            if ( false === $result ) {
                error_log( 'simple-hotel-crm: Failed to add category column: ' . $wpdb->last_error );
            }
        }
        if ( ! simple_hotel_crm_table_has_column( $table, 'favorite' ) ) {
            $result = $wpdb->query( "ALTER TABLE {$table} ADD COLUMN favorite tinyint(1) NOT NULL DEFAULT 0" );
            if ( false === $result ) {
                error_log( 'simple-hotel-crm: Failed to add favorite column: ' . $wpdb->last_error );
            }
        }
        return;
    }
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        item_name varchar(255) NOT NULL,
        unit_price decimal(10,2) NOT NULL DEFAULT 0.00,
        square_id varchar(191) NULL,
        category varchar(50) NOT NULL DEFAULT 'other',
        favorite tinyint(1) NOT NULL DEFAULT 0,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY item_name (item_name)
    ) {$charset_collate};";
    dbDelta( $sql );
}

function simple_hotel_crm_ensure_catalog_columns() {
    global $wpdb;
    $table = simple_hotel_crm_catalog_items_table();
    $errors = [];
    if ( ! simple_hotel_crm_table_has_column( $table, 'category' ) ) {
        $result = $wpdb->query( "ALTER TABLE {$table} ADD COLUMN category varchar(50) NOT NULL DEFAULT 'other'" );
        if ( false === $result ) {
            $errors[] = 'Failed to add category column: ' . $wpdb->last_error;
        }
    }
    if ( ! simple_hotel_crm_table_has_column( $table, 'favorite' ) ) {
        $result = $wpdb->query( "ALTER TABLE {$table} ADD COLUMN favorite tinyint(1) NOT NULL DEFAULT 0" );
        if ( false === $result ) {
            $errors[] = 'Failed to add favorite column: ' . $wpdb->last_error;
        }
    }
    return empty( $errors ) ? true : implode( '; ', $errors );
}

function simple_hotel_crm_toggle_favorite( $item_id ) {
    global $wpdb;
    $table = simple_hotel_crm_catalog_items_table();
    $current = $wpdb->get_var( $wpdb->prepare( "SELECT favorite FROM {$table} WHERE id = %d", $item_id ) );
    if ( null === $current ) {
        return false;
    }
    $new = $current ? 0 : 1;
    return $wpdb->update( $table, [ 'favorite' => $new ], [ 'id' => $item_id ], [ '%d' ], [ '%d' ] );
}

function simple_hotel_crm_repair_catalog_table() {
    $err = simple_hotel_crm_ensure_catalog_columns();
    return $err;
}

function simple_hotel_crm_get_catalog_items() {
    global $wpdb;
    $table = simple_hotel_crm_catalog_items_table();
    if ( simple_hotel_crm_table_has_column( $table, 'category' ) ) {
        return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY CASE category WHEN 'rooms' THEN 0 WHEN 'dinner' THEN 1 ELSE 2 END, item_name ASC", ARRAY_A );
    }
    return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY item_name ASC", ARRAY_A );
}

function simple_hotel_crm_import_catalog_csv( $file_path ) {
    global $wpdb;
    $table = simple_hotel_crm_catalog_items_table();

    // Ensure category column exists before importing
    $has_category = simple_hotel_crm_table_has_column( $table, 'category' );
    if ( ! $has_category ) {
        $result = simple_hotel_crm_ensure_catalog_columns();
        if ( true === $result ) {
            $has_category = true;
        } else {
            // Log the error but continue without category column
            error_log( 'simple-hotel-crm: ' . $result );
        }
    }

    $handle = fopen( $file_path, 'r' );
    if ( ! $handle ) {
        return new WP_Error( 'csv_open_failed', 'Could not open CSV file.' );
    }
    $header = fgetcsv( $handle );
    if ( ! $header ) {
        fclose( $handle );
        return new WP_Error( 'csv_empty', 'CSV file is empty.' );
    }
    $header = array_map( 'strtolower', $header );
    $name_col = null;
    $price_col = null;
    $square_col = null;
    $category_col = null;
    $variation_col = null;
    foreach ( $header as $i => $col ) {
        $col = trim( $col );
        if ( in_array( $col, [ 'name', 'item name', 'item_name' ], true ) ) {
            $name_col = $i;
        } elseif ( in_array( $col, [ 'price', 'unit_price', 'unit price', 'amount' ], true ) ) {
            $price_col = $i;
        } elseif ( in_array( $col, [ 'id', 'sku', 'square_id', 'item id' ], true ) ) {
            $square_col = $i;
        } elseif ( in_array( $col, [ 'category', 'cat' ], true ) ) {
            $category_col = $i;
        } elseif ( in_array( $col, [ 'variation name', 'variation_name', 'variant name', 'variant_name' ], true ) ) {
            $variation_col = $i;
        }
    }
    if ( null === $name_col || null === $price_col ) {
        fclose( $handle );
        return new WP_Error( 'csv_columns', 'CSV must have at least "name" and "price" columns. Found: ' . implode( ', ', $header ) );
    }
    $imported = 0;
    $skipped = 0;
    $errors = [];
    while ( ( $row = fgetcsv( $handle ) ) !== false ) {
        $name = isset( $row[ $name_col ] ) ? sanitize_text_field( trim( (string) $row[ $name_col ] ) ) : '';
        // If variation name column present, append to item name for uniqueness
        if ( null !== $variation_col && isset( $row[ $variation_col ] ) ) {
            $variation = sanitize_text_field( trim( (string) $row[ $variation_col ] ) );
            if ( '' !== $variation && $variation !== $name ) {
                $name .= ' (' . $variation . ')';
            }
        }
        $price_raw = isset( $row[ $price_col ] ) ? trim( (string) $row[ $price_col ] ) : '0';
        $price = (float) str_replace( [ ',', '€', '$' ], [ '.', '', '' ], $price_raw );
        $square_id = null !== $square_col && isset( $row[ $square_col ] ) ? sanitize_text_field( trim( (string) $row[ $square_col ] ) ) : null;
        $category = null !== $category_col && isset( $row[ $category_col ] ) ? sanitize_text_field( trim( (string) $row[ $category_col ] ) ) : 'other';
        if ( '' === $name || $price <= 0 ) {
            $skipped++;
            continue;
        }
        $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE item_name = %s", $name ) );
        if ( $existing ) {
            $data = [ 'unit_price' => $price, 'square_id' => $square_id ];
            $formats = [ '%f', '%s' ];
            if ( $has_category ) {
                $data['category'] = $category;
                $formats[] = '%s';
            }
            $result = $wpdb->update(
                $table,
                $data,
                [ 'id' => $existing ],
                $formats,
                [ '%d' ]
            );
            if ( false === $result ) {
                $errors[] = sprintf( 'Update failed for "%s": %s', $name, $wpdb->last_error );
                continue;
            }
        } else {
            $data = [ 'item_name' => $name, 'unit_price' => $price, 'square_id' => $square_id ];
            $formats = [ '%s', '%f', '%s' ];
            if ( $has_category ) {
                $data['category'] = $category;
                $formats[] = '%s';
            }
            $result = $wpdb->insert(
                $table,
                $data,
                $formats
            );
            if ( false === $result ) {
                $errors[] = sprintf( 'Insert failed for "%s": %s', $name, $wpdb->last_error );
                continue;
            }
        }
        $imported++;
    }
    fclose( $handle );
    return [ 'imported' => $imported, 'skipped' => $skipped, 'errors' => $errors ];
}

function simple_hotel_crm_add_catalog_item( $name, $price, $category = 'other', $square_id = null, $favorite = 0 ) {
    global $wpdb;
    $table = simple_hotel_crm_catalog_items_table();
    $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE item_name = %s", $name ) );
    if ( $existing ) {
        return simple_hotel_crm_update_catalog_item( $existing, [
            'unit_price' => $price,
            'category' => $category,
            'square_id' => $square_id,
        ] );
    }
    $data = [
        'item_name' => sanitize_text_field( $name ),
        'unit_price' => round( max( 0, (float) $price ), 2 ),
        'square_id' => ! empty( $square_id ) ? sanitize_text_field( $square_id ) : null,
    ];
    $formats = [ '%s', '%f', '%s' ];
    if ( simple_hotel_crm_table_has_column( $table, 'category' ) ) {
        $data['category'] = in_array( $category, [ 'rooms', 'dinner', 'other' ], true ) ? $category : 'other';
        $formats[] = '%s';
    }
    if ( simple_hotel_crm_table_has_column( $table, 'favorite' ) ) {
        $data['favorite'] = $favorite ? 1 : 0;
        $formats[] = '%d';
    }
    return $wpdb->insert( $table, $data, $formats );
}

function simple_hotel_crm_update_catalog_item( $item_id, $data ) {
    global $wpdb;
    $table = simple_hotel_crm_catalog_items_table();
    $update = [];
    $formats = [];
    $has_category = simple_hotel_crm_table_has_column( $table, 'category' );
    if ( isset( $data['item_name'] ) ) {
        $update['item_name'] = sanitize_text_field( $data['item_name'] );
        $formats[] = '%s';
    }
    if ( isset( $data['unit_price'] ) ) {
        $update['unit_price'] = round( max( 0, (float) $data['unit_price'] ), 2 );
        $formats[] = '%f';
    }
    if ( $has_category && isset( $data['category'] ) ) {
        $update['category'] = in_array( $data['category'], [ 'rooms', 'dinner', 'other' ], true ) ? $data['category'] : 'other';
        $formats[] = '%s';
    }
    if ( isset( $data['square_id'] ) ) {
        $update['square_id'] = ! empty( $data['square_id'] ) ? sanitize_text_field( $data['square_id'] ) : null;
        $formats[] = '%s';
    }
    if ( empty( $update ) ) {
        return false;
    }
    return $wpdb->update( $table, $update, [ 'id' => absint( $item_id ) ], $formats, [ '%d' ] );
}

function simple_hotel_crm_delete_catalog_item( $item_id ) {
    global $wpdb;
    return $wpdb->delete( simple_hotel_crm_catalog_items_table(), [ 'id' => absint( $item_id ) ], [ '%d' ] );
}

function simple_hotel_crm_daily_notes_table() {
    global $wpdb;
    return $wpdb->prefix . 'simple_hotel_crm_daily_notes';
}

function simple_hotel_crm_booking_notes_table() {
    global $wpdb;
    return $wpdb->prefix . 'simple_hotel_crm_booking_notes';
}

function simple_hotel_crm_booking_adjustments_table() {
    global $wpdb;
    return $wpdb->prefix . 'simple_hotel_crm_booking_adjustments';
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

function simple_hotel_crm_booking_items_table() {
    global $wpdb;
    return $wpdb->prefix . 'simple_hotel_crm_booking_items';
}

function simple_hotel_crm_catalog_items_table() {
    global $wpdb;
    return $wpdb->prefix . 'simple_hotel_crm_catalog_items';
}

function simple_hotel_crm_get_room_colors() {
    return [
        'C1' => '#ffcccc', 'C2' => '#ccffcc', 'C3' => '#ccccff',
        'C4' => '#ffffcc', 'C5' => '#ccffff', 'C6' => '#ffccff',
        'C7' => '#f2f2f2', 'C8' => '#e6e6e6', 'C9' => '#d9d9d9',
        'C10' => '#cccccc', 'C11' => '#b3b3b3', 'C12' => '#999999',
        'C13' => '#7f7f7f', 'C14' => '#666666', 'C15' => '#4c4c4c',
        'C16' => '#333333', 'C17' => '#191919', 'C18' => '#000000',
    ];
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

function simple_hotel_crm_get_booking_note_text( $booking_id, $booking_room_id = null, $stay_date = null ) {
    global $wpdb;

    $booking_notes_table = simple_hotel_crm_booking_notes_table();
    $booking_id = absint( $booking_id );
    if ( $booking_id <= 0 ) {
        return '';
    }

    if ( null !== $booking_room_id && null !== $stay_date ) {
        $note = $wpdb->get_var( $wpdb->prepare( "SELECT note_text FROM {$booking_notes_table} WHERE booking_id = %d AND booking_room_id = %d AND stay_date = %s LIMIT 1", $booking_id, absint( $booking_room_id ), (string) $stay_date ) );
        if ( null !== $note && '' !== trim( (string) $note ) ) {
            return (string) $note;
        }
    }
    if ( null !== $booking_room_id ) {
        $note = $wpdb->get_var( $wpdb->prepare( "SELECT note_text FROM {$booking_notes_table} WHERE booking_id = %d AND booking_room_id = %d AND stay_date IS NULL LIMIT 1", $booking_id, absint( $booking_room_id ) ) );
        if ( null !== $note && '' !== trim( (string) $note ) ) {
            return (string) $note;
        }
    }

    $note = $wpdb->get_var( $wpdb->prepare( "SELECT note_text FROM {$booking_notes_table} WHERE booking_id = %d AND booking_room_id IS NULL AND stay_date IS NULL LIMIT 1", $booking_id ) );
    return null !== $note ? (string) $note : '';
}

function simple_hotel_crm_upsert_booking_note( $booking_id, $note_text, $booking_room_id = null, $stay_date = null, $note_scope = 'booking' ) {
    global $wpdb;

    $booking_notes_table = simple_hotel_crm_booking_notes_table();
    $booking_id = absint( $booking_id );
    $booking_room_id = null !== $booking_room_id ? absint( $booking_room_id ) : null;
    $stay_date = null !== $stay_date && '' !== $stay_date ? (string) $stay_date : null;
    $note_text = sanitize_textarea_field( (string) $note_text );
    if ( $booking_id <= 0 ) {
        return false;
    }

    $where_sql = "booking_id = %d AND " . ( null === $booking_room_id ? 'booking_room_id IS NULL' : 'booking_room_id = %d' ) . " AND " . ( null === $stay_date ? 'stay_date IS NULL' : 'stay_date = %s' );
    $params = [ $booking_id ];
    if ( null !== $booking_room_id ) { $params[] = $booking_room_id; }
    if ( null !== $stay_date ) { $params[] = $stay_date; }
    $existing_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$booking_notes_table} WHERE {$where_sql} LIMIT 1", $params ) );

    if ( '' === trim( $note_text ) ) {
        if ( $existing_id > 0 ) {
            return false !== $wpdb->delete( $booking_notes_table, [ 'id' => $existing_id ], [ '%d' ] );
        }
        return true;
    }

    $payload = [
        'booking_id' => $booking_id,
        'booking_room_id' => $booking_room_id,
        'stay_date' => $stay_date,
        'note_scope' => (string) $note_scope,
        'note_text' => $note_text,
    ];
    $formats = [ '%d', '%d', '%s', '%s', '%s' ];
    if ( $existing_id > 0 ) {
        return false !== $wpdb->update( $booking_notes_table, $payload, [ 'id' => $existing_id ], $formats, [ '%d' ] );
    }
    return false !== $wpdb->insert( $booking_notes_table, $payload, $formats );
}

function simple_hotel_crm_get_booking_adjustment( $booking_room_id, $stay_date, $kind = 'extras' ) {
    global $wpdb;

    $table = simple_hotel_crm_booking_adjustments_table();
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE booking_room_id = %d AND stay_date = %s AND adjustment_kind = %s LIMIT 1", absint( $booking_room_id ), (string) $stay_date, (string) $kind ), ARRAY_A );
}

function simple_hotel_crm_upsert_booking_adjustment( $booking_id, $booking_room_id, $stay_date, $kind, $formula, $amount ) {
    global $wpdb;

    $table = simple_hotel_crm_booking_adjustments_table();
    $existing = simple_hotel_crm_get_booking_adjustment( $booking_room_id, $stay_date, $kind );
    $formula = sanitize_text_field( (string) $formula );
    $amount = round( max( 0, (float) $amount ), 2 );

    if ( '' === $formula && $amount <= 0 ) {
        if ( ! empty( $existing['id'] ) ) {
            return false !== $wpdb->delete( $table, [ 'id' => (int) $existing['id'] ], [ '%d' ] );
        }
        return true;
    }

    $payload = [
        'booking_id' => absint( $booking_id ),
        'booking_room_id' => absint( $booking_room_id ),
        'stay_date' => (string) $stay_date,
        'adjustment_kind' => (string) $kind,
        'formula' => $formula,
        'amount' => $amount,
    ];
    $formats = [ '%d', '%d', '%s', '%s', '%s', '%f' ];
    if ( ! empty( $existing['id'] ) ) {
        return false !== $wpdb->update( $table, $payload, [ 'id' => (int) $existing['id'] ], $formats, [ '%d' ] );
    }
    return false !== $wpdb->insert( $table, $payload, $formats );
}

function simple_hotel_crm_recalculate_booking_room_totals_from_nights( $booking_room_id ) {
    global $wpdb;

    $booking_room_id = absint( $booking_room_id );
    if ( $booking_room_id <= 0 ) {
        return false;
    }

    $booking_rooms_table = simple_hotel_crm_booking_rooms_table();
    $booking_nights_table = simple_hotel_crm_booking_room_nights_table();
    $totals = $wpdb->get_row( $wpdb->prepare( "SELECT COALESCE(SUM(extras_amount),0) AS extras_amount, COALESCE(SUM(total_amount),0) AS total_amount FROM {$booking_nights_table} WHERE booking_room_id = %d", $booking_room_id ), ARRAY_A );
    return false !== $wpdb->update( $booking_rooms_table, [
        'extras_amount' => (float) ( $totals['extras_amount'] ?? 0 ),
        'total_amount' => (float) ( $totals['total_amount'] ?? 0 ),
    ], [ 'id' => $booking_room_id ], [ '%f', '%f' ], [ '%d' ] );
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
    $booking_items_table = simple_hotel_crm_booking_items_table();

    $wpdb->query(
        "UPDATE {$bookings_table} b
         LEFT JOIN (
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
         LEFT JOIN (
            SELECT booking_id,
                   COALESCE(SUM(quantity * unit_price), 0) AS items_total
            FROM {$booking_items_table}
            GROUP BY booking_id
         ) items ON items.booking_id = b.id
         SET b.adults = COALESCE(room_totals.adults, 0),
             b.children = COALESCE(room_totals.children, 0),
             b.babies = COALESCE(room_totals.babies, 0),
             b.room_rate_amount = COALESCE(room_totals.room_rate_amount, 0),
             b.extras_amount = COALESCE(room_totals.extras_amount, 0) + COALESCE(items.items_total, 0),
             b.tourist_tax_amount = COALESCE(room_totals.tourist_tax_amount, 0),
             b.total_amount = COALESCE(room_totals.total_amount, 0) + COALESCE(items.items_total, 0)"
    );

    return max( 0, (int) $wpdb->rows_affected );
}

function simple_hotel_crm_run_repair_routines() {
    $room_night_dates = simple_hotel_crm_repair_room_night_dates();
    return [
        'pricing_rows' => simple_hotel_crm_backfill_pricing_amounts(),
        'commission_rows' => simple_hotel_crm_backfill_commission_amounts(),
        'booking_headers' => simple_hotel_crm_recalculate_booking_header_totals(),
        'room_night_dates_deleted' => $room_night_dates['deleted'],
        'room_night_dates_created' => $room_night_dates['created'],
    ];
}

function simple_hotel_crm_repair_room_night_dates() {
    global $wpdb;

    $bookings_table = simple_hotel_crm_bookings_table();
    $booking_rooms_table = simple_hotel_crm_booking_rooms_table();
    $booking_nights_table = simple_hotel_crm_booking_room_nights_table();

    $bad_nights = $wpdb->get_results(
        "SELECT brn.id, brn.booking_room_id, brn.stay_date,
                br.booking_id, br.room_id,
                br.adults AS room_adults, br.children AS room_children, br.babies AS room_babies,
                br.guest_count AS room_guest_count,
                b.check_in_date, b.check_out_date
         FROM {$booking_nights_table} brn
         JOIN {$booking_rooms_table} br ON br.id = brn.booking_room_id
         JOIN {$bookings_table} b ON b.id = br.booking_id
         WHERE b.is_deleted = 0
           AND b.status_code IN ('confirmed', 'checked_in')
           AND (brn.stay_date < b.check_in_date OR brn.stay_date >= b.check_out_date)"
    );

    if ( empty( $bad_nights ) ) {
        return [ 'deleted' => 0, 'created' => 0 ];
    }

    $night_ids = array_map( 'intval', wp_list_pluck( $bad_nights, 'id' ) );
    $wpdb->query( "DELETE FROM {$booking_nights_table} WHERE id IN (" . implode( ',', $night_ids ) . ")" );

    $affected = [];
    foreach ( $bad_nights as $night ) {
        $affected[ (int) $night->booking_room_id ] = $night;
    }

    $created_count = 0;
    foreach ( $affected as $booking_room_id => $sample ) {
        $check_in = $sample->check_in_date;
        $check_out = $sample->check_out_date;

        $existing_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$booking_nights_table} WHERE booking_room_id = %d AND stay_date >= %s AND stay_date < %s",
            $booking_room_id, $check_in, $check_out
        ) );

        $expected_nights = max( 1, (int) round( ( strtotime( $check_out ) - strtotime( $check_in ) ) / DAY_IN_SECONDS ) );
        if ( $existing_count >= $expected_nights ) {
            continue;
        }

        $adults = max( 1, (int) $sample->room_adults );
        $children = (int) $sample->room_children;
        $babies = (int) $sample->room_babies;
        $guest_count = max( 1, (int) $sample->room_guest_count ?: ( $adults + $children + $babies ) );

        $avg_rate = $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(AVG(room_rate_amount), 0) FROM {$booking_nights_table} WHERE booking_room_id = %d AND room_rate_amount > 0",
            $booking_room_id
        ) );
        $avg_extras = $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(AVG(extras_amount), 0) FROM {$booking_nights_table} WHERE booking_room_id = %d AND extras_amount > 0",
            $booking_room_id
        ) );
        $avg_tax = $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(AVG(tourist_tax_amount), 0) FROM {$booking_nights_table} WHERE booking_room_id = %d AND tourist_tax_amount > 0",
            $booking_room_id
        ) );

        for ( $i = 0; $i < $expected_nights; $i++ ) {
            $stay_date = gmdate( 'Y-m-d', strtotime( $check_in . ' +' . $i . ' day' ) );

            $exists = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$booking_nights_table} WHERE booking_room_id = %d AND stay_date = %s LIMIT 1",
                $booking_room_id, $stay_date
            ) );
            if ( $exists > 0 ) {
                continue;
            }

            $night_total = round( (float) $avg_rate + (float) $avg_extras + (float) $avg_tax, 2 );
            $wpdb->insert(
                $booking_nights_table,
                [
                    'booking_room_id' => $booking_room_id,
                    'stay_date' => $stay_date,
                    'guest_count' => $guest_count,
                    'adults' => $adults,
                    'children' => $children,
                    'babies' => $babies,
                    'room_rate_amount' => $avg_rate,
                    'extras_amount' => $avg_extras,
                    'tourist_tax_amount' => $avg_tax,
                    'total_amount' => $night_total,
                ],
                [ '%d', '%s', '%d', '%d', '%d', '%d', '%f', '%f', '%f', '%f' ]
            );
            $created_count++;
        }
    }

    return [ 'deleted' => count( $night_ids ), 'created' => $created_count ];
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
        'room_night_dates' => 0,
    ];

    $counts['room_night_dates'] = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$booking_nights_table} brn
         JOIN {$booking_rooms_table} br ON br.id = brn.booking_room_id
         JOIN {$bookings_table} b ON b.id = br.booking_id
         WHERE b.is_deleted = 0
           AND b.status_code IN ('confirmed', 'checked_in')
           AND (brn.stay_date < b.check_in_date OR brn.stay_date >= b.check_out_date)"
    );

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

function simple_hotel_crm_booking_is_ics_skeleton( $booking ) {
    if ( false === strpos( (string) ( $booking['internal_notes'] ?? '' ), '[ICS_SKELETON]' ) ) {
        return false;
    }
    if ( (int) ( $booking['adults'] ?? 0 ) > 0 ) { return false; }
    if ( (int) ( $booking['children'] ?? 0 ) > 0 ) { return false; }
    if ( (int) ( $booking['babies'] ?? 0 ) > 0 ) { return false; }
    if ( (float) ( $booking['room_rate_amount'] ?? 0 ) > 0 ) { return false; }
    if ( (float) ( $booking['extras_amount'] ?? 0 ) > 0 ) { return false; }
    if ( (float) ( $booking['tourist_tax_amount'] ?? 0 ) > 0 ) { return false; }
    if ( (float) ( $booking['total_amount'] ?? 0 ) > 0 ) { return false; }
    if ( ! empty( $booking['booking_note'] ) ) { return false; }
    global $wpdb;
    $booking_rooms_table = simple_hotel_crm_booking_rooms_table();
    $booking_id = (int) ( $booking['id'] ?? 0 );
    if ( $booking_id > 0 ) {
        $enriched_room = $wpdb->get_row( $wpdb->prepare(
            "SELECT id FROM {$booking_rooms_table} WHERE booking_id = %d AND (adults > 0 OR room_rate_amount > 0 OR total_amount > 0) LIMIT 1",
            $booking_id
        ), ARRAY_A );
        if ( $enriched_room ) { return false; }
    }
    return true;
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

    $booking_groups = $wpdb->get_results( "SELECT external_booking_id, MIN(source_booking_id) AS source_booking_id, MIN(guest_name) AS guest_name, MIN(phone) AS phone, MIN(source_channel) AS source_channel, MIN(status_code) AS status_code, MIN(check_in) AS check_in_date, MIN(check_out) AS check_out_date, MIN(source_created_at) AS source_created_at, MIN(import_notes) AS import_notes, MIN(invoice_ninja_client_id) AS invoice_ninja_client_id, MIN(invoice_ninja_invoice_id) AS invoice_ninja_invoice_id FROM {$sync_bookings_table} WHERE source_channel = 'booking_com' GROUP BY external_booking_id ORDER BY external_booking_id ASC", ARRAY_A );

    foreach ( $booking_groups as $booking_group ) {
        $wpdb->query( 'START TRANSACTION' );

        $room_groups = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT external_booking_room_id, room_sync_id, external_room_id, MIN(adults) AS adults, MIN(children) AS children, MIN(babies) AS babies, MIN(guest_count) AS guest_count, SUM(room_amount) AS room_rate_amount, SUM(COALESCE(extras_amount, 0)) AS extras_amount, SUM(COALESCE(tourist_tax_amount, 0)) AS tourist_tax_amount, SUM(total_amount) AS total_amount FROM {$sync_bookings_table} WHERE external_booking_id = %d GROUP BY external_booking_room_id, room_sync_id, external_room_id ORDER BY external_booking_room_id ASC",
                $booking_group['external_booking_id']
            ),
            ARRAY_A
        );
        if ( empty( $room_groups ) ) {
            $wpdb->query( 'COMMIT' );
            continue;
        }

        list( $first_name, $last_name ) = simple_hotel_crm_split_guest_name( $booking_group['guest_name'] );
        $source_id = (string) ( $booking_group['source_booking_id'] ?: $booking_group['external_booking_id'] );

        $guest_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT b.guest_id FROM {$crm_bookings_table} b WHERE b.source_booking_id = %s AND b.source_channel = %s AND (b.internal_notes NOT LIKE '%[MERGED_ARCHIVE]%' OR b.internal_notes IS NULL) LIMIT 1",
            $source_id,
            $booking_group['source_channel']
        ) );
        $guest = $guest_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . simple_hotel_crm_guests_table() . " WHERE id = %d", $guest_id ), ARRAY_A ) : null;

        if ( ! $guest ) {
            $guest_note = 'Booking.com ICS import ' . $source_id;
            $guest = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . simple_hotel_crm_guests_table() . " WHERE notes LIKE %s LIMIT 1", '%' . $wpdb->esc_like( $guest_note ) . '%' ), ARRAY_A );
        }

        if ( ! $guest ) {
            $check_in_date = (string) $booking_group['check_in_date'];
            $check_out_date = (string) $booking_group['check_out_date'];
            foreach ( $room_groups as $room_group ) {
                $crm_room_id = simple_hotel_crm_find_crm_room_id( (int) $room_group['room_sync_id'] );
                if ( $crm_room_id <= 0 ) {
                    $crm_room_id = simple_hotel_crm_find_crm_room_id( (int) $room_group['external_room_id'] );
                }
                if ( $crm_room_id <= 0 ) {
                    continue;
                }
                $existing_guest_id = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT b.guest_id FROM {$crm_bookings_table} b INNER JOIN {$crm_booking_rooms_table} br ON br.booking_id = b.id WHERE b.source_channel = 'booking_com' AND b.is_deleted = 0 AND (b.internal_notes NOT LIKE '%[MERGED_ARCHIVE]%' OR b.internal_notes IS NULL) AND br.room_id = %d AND b.check_in_date < %s AND b.check_out_date > %s AND b.status_code != 'cancelled' AND b.source_booking_id = %s ORDER BY b.id DESC LIMIT 1",
                    $crm_room_id,
                    $check_out_date,
                    $check_in_date,
                    $source_id
                ) );
                if ( $existing_guest_id > 0 ) {
                    $guest = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . simple_hotel_crm_guests_table() . " WHERE id = %d", $existing_guest_id ), ARRAY_A );
                    if ( $guest ) {
                        break;
                    }
                }
            }
        }

        if ( ! $guest ) {
            $guest_inserted = $wpdb->insert(
                simple_hotel_crm_guests_table(),
                [
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'phone' => (string) $booking_group['phone'],
                    'notes' => 'Booking.com ICS import ' . $source_id,
                ],
                [ '%s', '%s', '%s', '%s' ]
            );
            if ( false === $guest_inserted ) {
                $wpdb->query( 'ROLLBACK' );
                continue;
            }
            $guest = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . simple_hotel_crm_guests_table() . " WHERE id = %d", (int) $wpdb->insert_id ), ARRAY_A );
        }
        if ( ! $guest ) {
            $wpdb->query( 'ROLLBACK' );
            continue;
        }

        if ( ! empty( $booking_group['invoice_ninja_client_id'] ) && empty( $guest['invoice_ninja_client_id'] ) ) {
            $wpdb->update( simple_hotel_crm_guests_table(), [ 'invoice_ninja_client_id' => (string) $booking_group['invoice_ninja_client_id'] ], [ 'id' => (int) $guest['id'] ], [ '%s' ], [ '%d' ] );
            $guest['invoice_ninja_client_id'] = (string) $booking_group['invoice_ninja_client_id'];
        }

        $booking = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$crm_bookings_table} WHERE source_channel = %s AND (source_booking_id = %s OR source_booking_id = %s) AND (internal_notes NOT LIKE '%[MERGED_ARCHIVE]%' OR internal_notes IS NULL) LIMIT 1",
                (string) $booking_group['source_channel'],
                (string) ( $booking_group['source_booking_id'] ?: '' ),
                (string) $booking_group['external_booking_id']
            ),
            ARRAY_A
        );
        if ( $booking ) {
            $is_ics_skeleton = simple_hotel_crm_booking_is_ics_skeleton( $booking );
            if ( ! $is_ics_skeleton ) {
                // Strip [ICS_SKELETON] marker so future syncs don't mistake this for a skeleton
                $internal_notes = (string) ( $booking['internal_notes'] ?? '' );
                if ( false !== strpos( $internal_notes, '[ICS_SKELETON]' ) ) {
                    $cleaned = trim( str_replace( '[ICS_SKELETON]', '', $internal_notes ) );
                    $wpdb->update( $crm_bookings_table, [ 'internal_notes' => $cleaned ], [ 'id' => (int) $booking['id'] ], [ '%s' ], [ '%d' ] );
                }
                $real_source_booking_id = (string) ( $booking_group['source_booking_id'] ?: '' );
                if ( '' !== $real_source_booking_id && (string) $booking['source_booking_id'] !== $real_source_booking_id ) {
                    $wpdb->update( $crm_bookings_table, [ 'source_booking_id' => $real_source_booking_id ], [ 'id' => (int) $booking['id'] ], [ '%s' ], [ '%d' ] );
                }
                // Do NOT continue — fall through to room processing loop so NEW rooms
                // from the same reservation (same external_booking_id) get added.
                // Existing rooms are skipped by the dedup at line 1532.
            } else {
                // Skeleton booking found — update dates, then rebuild rooms from fresh sync data
                $wpdb->update(
                    $crm_bookings_table,
                    [
                        'check_in_date' => (string) $booking_group['check_in_date'],
                        'check_out_date' => (string) $booking_group['check_out_date'],
                        'status_code' => (string) $booking_group['status_code'],
                        'source_booking_id' => (string) ( $booking_group['source_booking_id'] ?: $booking_group['external_booking_id'] ),
                    ],
                    [ 'id' => (int) $booking['id'] ],
                    [ '%s', '%s', '%s', '%s' ],
                    [ '%d' ]
                );
                $booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$crm_bookings_table} WHERE id = %d", (int) $booking['id'] ), ARRAY_A );
            }
        }

        if ( ! $booking ) {
            $check_in_date = (string) $booking_group['check_in_date'];
            $check_out_date = (string) $booking_group['check_out_date'];
            foreach ( $room_groups as $room_group ) {
                $crm_room_id = simple_hotel_crm_find_crm_room_id( (int) $room_group['room_sync_id'] );
                if ( $crm_room_id <= 0 ) {
                    $crm_room_id = simple_hotel_crm_find_crm_room_id( (int) $room_group['external_room_id'] );
                }
                if ( $crm_room_id <= 0 ) {
                    continue;
                }
                $fallback = $wpdb->get_row( $wpdb->prepare(
                    "SELECT b.* FROM {$crm_bookings_table} b INNER JOIN {$crm_booking_rooms_table} br ON br.booking_id = b.id WHERE b.source_channel = 'booking_com' AND b.is_deleted = 0 AND (b.internal_notes NOT LIKE '%[MERGED_ARCHIVE]%' OR b.internal_notes IS NULL) AND br.room_id = %d AND b.check_in_date < %s AND b.check_out_date > %s AND b.status_code != 'cancelled' AND b.source_booking_id = %s ORDER BY b.id DESC LIMIT 1",
                    $crm_room_id,
                    $check_out_date,
                    $check_in_date,
                    (string) ( $booking_group['source_booking_id'] ?: '' )
                ), ARRAY_A );
                if ( $fallback ) {
                    $booking = $fallback;
                    $guest = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . simple_hotel_crm_guests_table() . " WHERE id = %d", (int) $booking['guest_id'] ), ARRAY_A );
                    if ( simple_hotel_crm_booking_is_ics_skeleton( $booking ) ) {
                        $wpdb->update(
                            $crm_bookings_table,
                            [
                                'check_in_date' => (string) $booking_group['check_in_date'],
                                'check_out_date' => (string) $booking_group['check_out_date'],
                                'status_code' => (string) $booking_group['status_code'],
                            ],
                            [ 'id' => (int) $booking['id'] ],
                            [ '%s', '%s', '%s' ],
                            [ '%d' ]
                        );
                        $booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$crm_bookings_table} WHERE id = %d", (int) $booking['id'] ), ARRAY_A );
                    }
                    $real_source_booking_id = (string) ( $booking_group['source_booking_id'] ?: '' );
                    if ( '' !== $real_source_booking_id ) {
                        $wpdb->update( $crm_bookings_table, [ 'source_booking_id' => $real_source_booking_id ], [ 'id' => (int) $booking['id'] ], [ '%s' ], [ '%d' ] );
                    }
                    break;
                }
            }
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
                    'internal_notes' => trim( '[ICS_SKELETON] ' . (string) $booking_group['import_notes'] ),
                    'invoice_ninja_client_id' => (string) $booking_group['invoice_ninja_client_id'],
                    'invoice_ninja_invoice_id' => (string) $booking_group['invoice_ninja_invoice_id'],
                ],
                [ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%f', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%s' ]
            );
            if ( false === $booking_inserted ) {
                $wpdb->query( 'ROLLBACK' );
                continue;
            }
            $booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$crm_bookings_table} WHERE id = %d", (int) $wpdb->insert_id ), ARRAY_A );
        }
        if ( ! $booking ) {
            $wpdb->query( 'ROLLBACK' );
            continue;
        }

        // For ICS skeleton bookings, delete existing rooms and nights and rebuild from fresh sync data
        $is_ics_skeleton = simple_hotel_crm_booking_is_ics_skeleton( $booking );
        if ( $is_ics_skeleton ) {
            $existing_room_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$crm_booking_rooms_table} WHERE booking_id = %d", (int) $booking['id'] ) );
            if ( ! empty( $existing_room_ids ) ) {
                $room_placeholders = implode( ',', array_fill( 0, count( $existing_room_ids ), '%d' ) );
                $wpdb->query( $wpdb->prepare(
                    "DELETE FROM {$crm_booking_nights_table} WHERE booking_room_id IN ({$room_placeholders})",
                    $existing_room_ids
                ) );
                $wpdb->query( $wpdb->prepare(
                    "DELETE FROM {$crm_booking_rooms_table} WHERE id IN ({$room_placeholders})",
                    $existing_room_ids
                ) );
            }
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

        // Recalculate booking-level totals from booking_rooms for ICS skeleton bookings
        if ( $is_ics_skeleton ) {
            $totals = $wpdb->get_row( $wpdb->prepare(
                "SELECT COALESCE(SUM(adults), 0) AS adults, COALESCE(SUM(children), 0) AS children, COALESCE(SUM(babies), 0) AS babies,
                 COALESCE(SUM(room_rate_amount), 0) AS room_rate_amount, COALESCE(SUM(extras_amount), 0) AS extras_amount,
                 COALESCE(SUM(tourist_tax_amount), 0) AS tourist_tax_amount, COALESCE(SUM(total_amount), 0) AS total_amount
                 FROM {$crm_booking_rooms_table} WHERE booking_id = %d",
                (int) $booking['id']
            ), ARRAY_A );
            if ( $totals ) {
                $wpdb->update(
                    $crm_bookings_table,
                    [
                        'adults' => (int) $totals['adults'],
                        'children' => (int) $totals['children'],
                        'babies' => (int) $totals['babies'],
                        'room_rate_amount' => (float) $totals['room_rate_amount'],
                        'extras_amount' => (float) $totals['extras_amount'],
                        'tourist_tax_amount' => (float) $totals['tourist_tax_amount'],
                        'total_amount' => (float) $totals['total_amount'],
                    ],
                    [ 'id' => (int) $booking['id'] ],
                    [ '%d', '%d', '%d', '%f', '%f', '%f', '%f' ],
                    [ '%d' ]
                );
            }
        }

        $wpdb->query( 'COMMIT' );
    }
}

function simple_hotel_crm_maybe_migrate_sync_data_to_crm() {
    simple_hotel_crm_import_sync_data_to_crm();
}

function simple_hotel_crm_migrate_room_status_column() {
    global $wpdb;
    $rooms_table = simple_hotel_crm_rooms_table();
    if ( ! simple_hotel_crm_table_has_column( $rooms_table, 'room_status' ) ) {
        $wpdb->query( "ALTER TABLE {$rooms_table} ADD COLUMN room_status varchar(20) NOT NULL DEFAULT 'clean' AFTER color" );
    }
}

