<?php
/**
 * Plugin Name: Simple Hotel CRM
 * Description: A simple WordPress-native hotel CRM with calendar and booking management tools
 * Version: 1.6
 * Author: Angus Watson, Quinn (mistral/codestral) & Kylie (stepfun/step-3.5-flash:free)
 * Text Domain: simple-hotel-crm
 */

defined( 'ABSPATH' ) || exit;

define( 'SIMPLE_HOTEL_CRM_VERSION', '1.6' );
define( 'SIMPLE_HOTEL_CRM_DB_VERSION', '5' );

add_action( 'plugins_loaded', 'simple_hotel_crm_check_dependency' );
function simple_hotel_crm_check_dependency() {
    if ( 'motopress' !== simple_hotel_crm_get_data_source() ) {
        return;
    }

    if ( ! function_exists( 'MPHB' ) || ! class_exists( 'HotelBookingPlugin' ) ) {
        add_action( 'admin_notices', function() {
            ?>
            <div class="notice notice-error">
                <p><?php esc_html_e( 'Simple Hotel CRM requires MotoPress Hotel Booking when the MotoPress source is selected.', 'simple-hotel-crm' ); ?></p>
            </div>
            <?php
        } );
        return;
    }
}

register_activation_hook( __FILE__, 'simple_hotel_crm_activate' );
add_action( 'plugins_loaded', 'simple_hotel_crm_maybe_upgrade' );

function simple_hotel_crm_activate() {
    simple_hotel_crm_install_tables();
}

function simple_hotel_crm_maybe_upgrade() {
    $installed_version = get_option( 'simple_hotel_crm_db_version' );
    if ( SIMPLE_HOTEL_CRM_DB_VERSION !== (string) $installed_version ) {
        simple_hotel_crm_install_tables();
    }
}

function simple_hotel_crm_install_tables() {
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate = $wpdb->get_charset_collate();
    $daily_notes_table = simple_hotel_crm_daily_notes_table();
    $overlay_table     = simple_hotel_crm_booking_overlay_table();
    $sync_rooms_table  = simple_hotel_crm_sync_rooms_table();
    $sync_bookings_table = simple_hotel_crm_sync_bookings_table();
    $crm_rooms_table   = simple_hotel_crm_rooms_table();
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
        special_requests longtext NULL,
        internal_notes longtext NULL,
        invoice_ninja_client_id varchar(191) NULL,
        invoice_ninja_invoice_id varchar(191) NULL,
        invoiced_at datetime NULL,
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
        guest_count int(11) NOT NULL DEFAULT 0,
        adults int(11) NOT NULL DEFAULT 0,
        children int(11) NOT NULL DEFAULT 0,
        babies int(11) NOT NULL DEFAULT 0,
        room_rate_amount decimal(10,2) NOT NULL DEFAULT 0.00,
        extras_amount decimal(10,2) NOT NULL DEFAULT 0.00,
        tourist_tax_amount decimal(10,2) NOT NULL DEFAULT 0.00,
        total_amount decimal(10,2) NOT NULL DEFAULT 0.00,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY legacy_reserved_room_id (legacy_reserved_room_id),
        KEY booking_id (booking_id),
        KEY room_id (room_id)
    ) {$charset_collate};";

    $sql_crm_booking_nights = "CREATE TABLE {$crm_booking_nights_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        booking_room_id bigint(20) unsigned NOT NULL,
        stay_date date NOT NULL,
        guest_count int(11) NOT NULL DEFAULT 0,
        adults int(11) NOT NULL DEFAULT 0,
        children int(11) NOT NULL DEFAULT 0,
        babies int(11) NOT NULL DEFAULT 0,
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
    dbDelta( $sql_sync_rooms );
    dbDelta( $sql_sync_bookings );
    dbDelta( $sql_crm_rooms );
    dbDelta( $sql_crm_guests );
    dbDelta( $sql_crm_bookings );
    dbDelta( $sql_crm_booking_rooms );
    dbDelta( $sql_crm_booking_nights );

    simple_hotel_crm_seed_rooms_table();
    simple_hotel_crm_maybe_migrate_sync_data_to_crm();

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

function simple_hotel_crm_maybe_migrate_sync_data_to_crm() {
    global $wpdb;

    $crm_bookings_table = simple_hotel_crm_bookings_table();
    $crm_booking_rooms_table = simple_hotel_crm_booking_rooms_table();
    $crm_booking_nights_table = simple_hotel_crm_booking_room_nights_table();
    $crm_guests_table = simple_hotel_crm_guests_table();
    $crm_rooms_table = simple_hotel_crm_rooms_table();
    $sync_bookings_table = simple_hotel_crm_sync_bookings_table();

    $crm_booking_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$crm_bookings_table}" );
    $sync_booking_count = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT external_booking_id) FROM {$sync_bookings_table}" );
    if ( $crm_booking_count > 0 || $sync_booking_count <= 0 ) {
        return;
    }

    $booking_groups = $wpdb->get_results( "SELECT external_booking_id, MIN(guest_name) AS guest_name, MIN(phone) AS phone, MIN(source_channel) AS source_channel, MIN(status_code) AS status_code, MIN(check_in) AS check_in_date, MIN(check_out) AS check_out_date, MIN(source_created_at) AS source_created_at, MIN(import_notes) AS import_notes, MIN(invoice_ninja_client_id) AS invoice_ninja_client_id, MIN(invoice_ninja_invoice_id) AS invoice_ninja_invoice_id FROM {$sync_bookings_table} GROUP BY external_booking_id ORDER BY external_booking_id ASC", ARRAY_A );

    foreach ( $booking_groups as $booking_group ) {
        list( $first_name, $last_name ) = simple_hotel_crm_split_guest_name( $booking_group['guest_name'] );

        $guest_inserted = $wpdb->insert(
            $crm_guests_table,
            [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'phone' => (string) $booking_group['phone'],
            ],
            [ '%s', '%s', '%s' ]
        );
        if ( false === $guest_inserted ) {
            continue;
        }
        $guest_id = (int) $wpdb->insert_id;

        $room_groups = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT external_booking_room_id, room_sync_id, MIN(adults) AS adults, MIN(children) AS children, MIN(babies) AS babies, MIN(guest_count) AS guest_count, SUM(room_amount) AS room_rate_amount, SUM(COALESCE(extras_amount, 0)) AS extras_amount, SUM(COALESCE(tourist_tax_amount, 0)) AS tourist_tax_amount, SUM(total_amount) AS total_amount FROM {$sync_bookings_table} WHERE external_booking_id = %d GROUP BY external_booking_room_id, room_sync_id ORDER BY external_booking_room_id ASC",
                $booking_group['external_booking_id']
            ),
            ARRAY_A
        );
        if ( empty( $room_groups ) ) {
            continue;
        }

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
                'guest_id' => $guest_id,
                'source_channel' => (string) $booking_group['source_channel'],
                'source_booking_id' => '',
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
                'internal_notes' => (string) $booking_group['import_notes'],
                'invoice_ninja_client_id' => (string) $booking_group['invoice_ninja_client_id'],
                'invoice_ninja_invoice_id' => (string) $booking_group['invoice_ninja_invoice_id'],
            ],
            [ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%f', '%f', '%f', '%f', '%s', '%s', '%s', '%s' ]
        );
        if ( false === $booking_inserted ) {
            continue;
        }
        $booking_id = (int) $wpdb->insert_id;

        foreach ( $room_groups as $room_group ) {
            $crm_room_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$crm_rooms_table} WHERE sync_room_id = %d LIMIT 1", $room_group['room_sync_id'] ) );
            if ( $crm_room_id <= 0 ) {
                continue;
            }

            $booking_room_inserted = $wpdb->insert(
                $crm_booking_rooms_table,
                [
                    'booking_id' => $booking_id,
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

function simple_hotel_crm_user_can_access() {
    return is_user_logged_in() && current_user_can( 'manage_options' );
}

function simple_hotel_crm_enqueue_shared_assets( $context = 'frontend' ) {
    wp_register_style( 'simple-hotel-crm', plugin_dir_url( __FILE__ ) . 'assets/style.css', [], SIMPLE_HOTEL_CRM_VERSION );
    wp_enqueue_style( 'simple-hotel-crm' );

    wp_register_script( 'simple-hotel-crm', plugin_dir_url( __FILE__ ) . 'assets/calendar-navigation.js', [ 'jquery' ], SIMPLE_HOTEL_CRM_VERSION, true );
    wp_localize_script( 'simple-hotel-crm', 'simpleHotelCrm', [
        'restUrl'        => esc_url_raw( rest_url( 'simple-hotel-crm/v1/table' ) ),
        'dailyNotesUrl'  => esc_url_raw( rest_url( 'simple-hotel-crm/v1/daily-note' ) ),
        'bookingUrl'     => esc_url_raw( rest_url( 'simple-hotel-crm/v1/booking-overlay' ) ),
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

function simple_hotel_crm_get_locked_booking_statuses() {
    if ( function_exists( 'MPHB' ) && method_exists( MPHB()->postTypes()->booking()->statuses(), 'getLockedRoomStatuses' ) ) {
        return MPHB()->postTypes()->booking()->statuses()->getLockedRoomStatuses();
    }

    return [ 'confirmed', 'pending', 'pending-user', 'pending-payment' ];
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

function simple_hotel_crm_extract_tarif_from_room_breakdown( $room_breakdown ) {
    if ( ! is_array( $room_breakdown ) ) {
        return '';
    }

    if ( isset( $room_breakdown['room']['discount_total'] ) && '' !== $room_breakdown['room']['discount_total'] ) {
        return (float) $room_breakdown['room']['discount_total'];
    }
    if ( isset( $room_breakdown['room']['total'] ) && '' !== $room_breakdown['room']['total'] ) {
        return (float) $room_breakdown['room']['total'];
    }
    if ( isset( $room_breakdown['discount_total'] ) && '' !== $room_breakdown['discount_total'] ) {
        return (float) $room_breakdown['discount_total'];
    }
    if ( isset( $room_breakdown['total'] ) && '' !== $room_breakdown['total'] ) {
        return (float) $room_breakdown['total'];
    }

    return '';
}

function simple_hotel_crm_extract_tarif( $booking, $reserved_room ) {
    $tarif = '';

    if ( $reserved_room && method_exists( $reserved_room, 'getLastRoomPriceBreakdown' ) ) {
        $tarif = simple_hotel_crm_extract_tarif_from_room_breakdown( $reserved_room->getLastRoomPriceBreakdown() );
        if ( '' !== $tarif ) {
            return $tarif;
        }
    }

    if ( class_exists( '\MPHB\Utils\PriceBreakdownHelper' ) && method_exists( '\MPHB\Utils\PriceBreakdownHelper', 'getLastRoomPriceBreakdown' ) ) {
        $tarif = simple_hotel_crm_extract_tarif_from_room_breakdown( \MPHB\Utils\PriceBreakdownHelper::getLastRoomPriceBreakdown( $reserved_room ) );
        if ( '' !== $tarif ) {
            return $tarif;
        }
    }

    if ( $reserved_room ) {
        $reserved_room_id = method_exists( $reserved_room, 'getId' ) ? (int) $reserved_room->getId() : 0;
        if ( $reserved_room_id > 0 ) {
            $reserved_room_price = get_post_meta( $reserved_room_id, '_mphb_reserved_room_price', true );
            if ( is_numeric( $reserved_room_price ) ) {
                return (float) $reserved_room_price;
            }
            $reserved_room_price = get_post_meta( $reserved_room_id, '_mphb_room_price', true );
            if ( is_numeric( $reserved_room_price ) ) {
                return (float) $reserved_room_price;
            }
        }
    }

    if ( $reserved_room && method_exists( $reserved_room, 'getPrice' ) ) {
        $price = $reserved_room->getPrice();
        if ( is_numeric( $price ) && $price > 0 ) {
            return (float) $price;
        }
    }

    if ( $reserved_room && method_exists( $reserved_room, 'getTotal' ) ) {
        $total = $reserved_room->getTotal();
        if ( is_numeric( $total ) && $total > 0 ) {
            return (float) $total;
        }
    }

    $breakdown = null;
    if ( $booking && method_exists( $booking, 'getLastPriceBreakdown' ) ) {
        $breakdown = $booking->getLastPriceBreakdown();
    }

    if ( ! is_array( $breakdown ) && $booking && method_exists( $booking, 'getId' ) ) {
        $breakdown = get_post_meta( $booking->getId(), '_mphb_booking_price_breakdown', true );
    }

    if ( is_array( $breakdown ) && ! empty( $breakdown['rooms'] ) && is_array( $breakdown['rooms'] ) ) {
        $reserved_room_id = $reserved_room && method_exists( $reserved_room, 'getId' ) ? (int) $reserved_room->getId() : 0;
        $room_id = $reserved_room && method_exists( $reserved_room, 'getRoomId' ) ? (int) $reserved_room->getRoomId() : 0;

        foreach ( $breakdown['rooms'] as $room_line ) {
            if ( isset( $room_line['reserved_room_id'] ) && (int) $room_line['reserved_room_id'] === $reserved_room_id ) {
                $tarif = simple_hotel_crm_extract_tarif_from_room_breakdown( $room_line );
                if ( '' !== $tarif ) {
                    return $tarif;
                }
            }
        }

        foreach ( $breakdown['rooms'] as $room_line ) {
            if ( isset( $room_line['room_id'] ) && (int) $room_line['room_id'] === $room_id ) {
                $tarif = simple_hotel_crm_extract_tarif_from_room_breakdown( $room_line );
                if ( '' !== $tarif ) {
                    return $tarif;
                }
            }
        }

        if ( 1 === count( $breakdown['rooms'] ) ) {
            $room_line = reset( $breakdown['rooms'] );
            $tarif = simple_hotel_crm_extract_tarif_from_room_breakdown( $room_line );
            if ( '' !== $tarif ) {
                return $tarif;
            }
        }
    }

    if ( $booking && method_exists( $booking, 'getId' ) ) {
        $booking_price = get_post_meta( $booking->getId(), '_mphb_booking_price', true );
        if ( is_numeric( $booking_price ) ) {
            return (float) $booking_price;
        }
    }

    return '';
}

function simple_hotel_crm_build_booking_payload( $booking, $reserved_room ) {
    $customer = method_exists( $booking, 'getCustomer' ) ? $booking->getCustomer() : null;

    $guest_name = '';
    if ( $reserved_room && method_exists( $reserved_room, 'getGuestName' ) ) {
        $guest_name = trim( (string) $reserved_room->getGuestName() );
    }

    if ( '' === $guest_name && $customer ) {
        $first_name = method_exists( $customer, 'getFirstName' ) ? (string) $customer->getFirstName() : '';
        $last_name  = method_exists( $customer, 'getLastName' ) ? (string) $customer->getLastName() : '';
        $guest_name = trim( $first_name . ' ' . $last_name );
    }

    $adults   = $reserved_room && method_exists( $reserved_room, 'getAdults' ) ? (int) $reserved_room->getAdults() : 0;
    $children = $reserved_room && method_exists( $reserved_room, 'getChildren' ) ? (int) $reserved_room->getChildren() : 0;

    $is_imported = method_exists( $booking, 'isImported' ) ? (bool) $booking->isImported() : ! empty( get_post_meta( $booking->getId(), '_mphb_sync_id', true ) );

    $tarif = '';
    if ( ! $is_imported ) {
        $tarif = simple_hotel_crm_extract_tarif( $booking, $reserved_room );
    }

    $reserved_room_id = $reserved_room && method_exists( $reserved_room, 'getId' ) ? (int) $reserved_room->getId() : 0;
    $overlay          = simple_hotel_crm_get_booking_overlay( $reserved_room_id );
    $overlay_adults   = isset( $overlay['manual_adults'] ) && '' !== $overlay['manual_adults'] ? (int) $overlay['manual_adults'] : null;
    $overlay_children = isset( $overlay['manual_children'] ) && '' !== $overlay['manual_children'] ? (int) $overlay['manual_children'] : null;
    $effective_adults = null !== $overlay_adults ? $overlay_adults : $adults;
    $effective_children = null !== $overlay_children ? $overlay_children : $children;

    $extras_formula = isset( $overlay['extras_formula'] ) ? (string) $overlay['extras_formula'] : '';
    $extras_total   = isset( $overlay['extras_total'] ) && '' !== $overlay['extras_total'] ? (float) $overlay['extras_total'] : null;

    $created_date = '';
    if ( method_exists( $booking, 'getPostId' ) ) {
        $created_timestamp = get_post_time( 'U', false, $booking->getPostId() );
        if ( $created_timestamp ) {
            $created_date = date_i18n( 'Y-m-d', $created_timestamp );
        }
    }
    if ( '' === $created_date && method_exists( $booking, 'getId' ) ) {
        $created_timestamp = get_post_time( 'U', false, $booking->getId() );
        if ( $created_timestamp ) {
            $created_date = date_i18n( 'Y-m-d', $created_timestamp );
        }
    }

    return (object) [
        'id'                => method_exists( $booking, 'getId' ) ? (int) $booking->getId() : 0,
        'status'            => method_exists( $booking, 'getStatus' ) ? (string) $booking->getStatus() : '',
        'check_in'          => method_exists( $booking, 'getCheckInDate' ) && $booking->getCheckInDate() ? $booking->getCheckInDate()->format( 'Y-m-d' ) : '',
        'check_out'         => method_exists( $booking, 'getCheckOutDate' ) && $booking->getCheckOutDate() ? $booking->getCheckOutDate()->format( 'Y-m-d' ) : '',
        'room_id'           => $reserved_room && method_exists( $reserved_room, 'getRoomId' ) ? (int) $reserved_room->getRoomId() : 0,
        'reserved_room_id'  => $reserved_room_id,
        'guest_name'        => isset( $overlay['manual_guest_name'] ) && '' !== trim( (string) $overlay['manual_guest_name'] ) ? trim( (string) $overlay['manual_guest_name'] ) : $guest_name,
        'phone'             => $customer && method_exists( $customer, 'getPhone' ) ? (string) $customer->getPhone() : '',
        'adults'            => $effective_adults,
        'children'          => $effective_children,
        'occupancy_str'     => simple_hotel_crm_format_occupancy( $effective_adults, $effective_children ),
        'channel'           => trim( ( $is_imported ? 'I' : 'W' ) . ( $created_date ? ' ' . $created_date : '' ) ),
        'channel_label'     => $is_imported ? 'Imported' : 'Website',
        'created_date'      => $created_date,
        'is_imported'       => $is_imported,
        'tarif'             => isset( $overlay['manual_tarif'] ) && '' !== $overlay['manual_tarif'] ? (float) $overlay['manual_tarif'] : $tarif,
        'commission'        => isset( $overlay['manual_commission'] ) && '' !== $overlay['manual_commission'] ? (float) $overlay['manual_commission'] : '',
        'extras_formula'    => $extras_formula,
        'extras_total'      => $extras_total,
        'booking_note'      => isset( $overlay['booking_note'] ) ? (string) $overlay['booking_note'] : '',
    ];
}

function simple_hotel_crm_get_data_source() {
    $source = get_option( 'simple_hotel_crm_booking_source', 'wp_sync' );
    return in_array( $source, [ 'motopress', 'wp_sync', 'external_pg' ], true ) ? $source : 'wp_sync';
}

function simple_hotel_crm_get_external_db_settings() {
    return [
        'host'     => (string) get_option( 'simple_hotel_crm_external_pg_host', '' ),
        'port'     => (string) get_option( 'simple_hotel_crm_external_pg_port', '5432' ),
        'dbname'   => (string) get_option( 'simple_hotel_crm_external_pg_dbname', '' ),
        'user'     => (string) get_option( 'simple_hotel_crm_external_pg_user', '' ),
        'password' => (string) get_option( 'simple_hotel_crm_external_pg_password', '' ),
        'sslmode'  => (string) get_option( 'simple_hotel_crm_external_pg_sslmode', 'disable' ),
    ];
}

function simple_hotel_crm_get_external_pg_connection() {
    static $connection = null;
    static $attempted = false;

    if ( $attempted ) {
        return $connection;
    }

    $attempted = true;

    if ( ! function_exists( 'pg_connect' ) ) {
        $connection = new WP_Error( 'missing_pgsql_extension', __( 'The pgsql PHP extension is not available on this WordPress server.', 'simple-hotel-crm' ) );
        return $connection;
    }

    $settings = simple_hotel_crm_get_external_db_settings();
    foreach ( [ 'host', 'port', 'dbname', 'user' ] as $required_key ) {
        if ( '' === $settings[ $required_key ] ) {
            $connection = new WP_Error( 'missing_pg_settings', __( 'External PostgreSQL settings are incomplete.', 'simple-hotel-crm' ) );
            return $connection;
        }
    }

    $connection_string = sprintf(
        'host=%s port=%s dbname=%s user=%s password=%s sslmode=%s connect_timeout=5',
        $settings['host'],
        $settings['port'],
        $settings['dbname'],
        $settings['user'],
        $settings['password'],
        $settings['sslmode'] ?: 'disable'
    );

    $connection = @pg_connect( $connection_string );
    if ( ! $connection ) {
        $connection = new WP_Error( 'pg_connect_failed', __( 'Could not connect to the external PostgreSQL database.', 'simple-hotel-crm' ) );
    }

    return $connection;
}

function simple_hotel_crm_get_room_colors() {
    return [
        'ANE' => '#cc99ff',
        'DEL' => '#b4c7e7',
        'LYS' => '#a9d18e',
        'TOU' => '#ffe699',
        'TUL' => '#f4b183',
        'COQ' => '#cccccc',
    ];
}

function simple_hotel_crm_get_room_sort_order( $room_code, $room_name ) {
    $room_code = strtoupper( (string) $room_code );
    $map = [ 'ANE' => 1, 'DEL' => 2, 'LYS' => 3, 'TOU' => 4, 'TUL' => 5, 'COQ' => 6 ];

    if ( isset( $map[ $room_code ] ) ) {
        return $map[ $room_code ];
    }

    $room_name = remove_accents( strtolower( (string) $room_name ) );
    $name_map = [ 'anemone' => 1, 'delphinium' => 2, 'lys' => 3, 'tournesol' => 4, 'tulipe' => 5, 'coquelicot' => 6 ];
    return $name_map[ $room_name ] ?? 999;
}

function simple_hotel_crm_format_channel_code( $source_channel, $created_date = '' ) {
    $source_channel = (string) $source_channel;
    $created_date   = (string) $created_date;

    $prefix_map = [
        'booking_com' => 'B',
        'direct'      => 'W',
        'motopress'   => 'W',
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

require_once plugin_dir_path( __FILE__ ) . 'includes/calendar-data.php';

require_once plugin_dir_path( __FILE__ ) . 'includes/admin-pages.php';

require_once plugin_dir_path( __FILE__ ) . 'includes/crm-bookings.php';

add_action( 'rest_api_init', function() {
    register_rest_route( 'simple-hotel-crm/v1', '/table', [
        'methods'  => 'GET',
        'callback' => 'simple_hotel_crm_rest_table',
        'args'     => [
            'month' => [ 'validate_callback' => function( $param ) { return is_numeric( $param ) && $param >= 1 && $param <= 12; }, 'required' => true ],
            'year'  => [ 'validate_callback' => function( $param ) { return is_numeric( $param ) && $param >= 2000 && $param <= 2100; }, 'required' => true ],
        ],
        'permission_callback' => function() { return simple_hotel_crm_user_can_access(); },
    ] );

    register_rest_route( 'simple-hotel-crm/v1', '/daily-note', [
        'methods'  => 'POST',
        'callback' => 'simple_hotel_crm_rest_save_daily_note',
        'permission_callback' => function() { return simple_hotel_crm_user_can_access(); },
    ] );

    register_rest_route( 'simple-hotel-crm/v1', '/booking-overlay', [
        'methods'  => 'POST',
        'callback' => 'simple_hotel_crm_rest_save_booking_overlay',
        'permission_callback' => function() { return simple_hotel_crm_user_can_access(); },
    ] );

    register_rest_route( 'simple-hotel-crm/v1', '/create-invoice', [
        'methods'  => 'POST',
        'callback' => 'simple_hotel_crm_rest_create_invoice',
        'permission_callback' => function() { return simple_hotel_crm_user_can_access(); },
        'args'     => [
            'booking_id' => [
                'validate_callback' => function( $param ) {
                    return is_numeric( $param ) && $param > 0;
                },
                'required' => true,
            ],
            'reserved_room_id' => [
                'validate_callback' => function( $param ) {
                    return is_numeric( $param ) && $param > 0;
                },
                'required' => true,
            ],
        ],
    ] );
} );

function simple_hotel_crm_rest_table( WP_REST_Request $request ) {
    $month = intval( $request->get_param( 'month' ) );
    $year  = intval( $request->get_param( 'year' ) );
    $context = 'admin' === $request->get_param( 'context' ) ? 'admin' : 'frontend';

    return rest_ensure_response( [ 'html' => simple_hotel_crm_render_calendar( simple_hotel_crm_get_calendar_data( $month, $year ), $context ) ] );
}

function simple_hotel_crm_rest_save_daily_note( WP_REST_Request $request ) {
    global $wpdb;

    $date = sanitize_text_field( (string) $request->get_param( 'date' ) );
    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
        return new WP_Error( 'invalid_date', __( 'Invalid note date.', 'simple-hotel-crm' ), [ 'status' => 400 ] );
    }

    $note = sanitize_textarea_field( (string) $request->get_param( 'note' ) );
    $table = simple_hotel_crm_daily_notes_table();

    if ( '' === trim( $note ) ) {
        $wpdb->delete( $table, [ 'note_date' => $date ], [ '%s' ] );
    } else {
        $wpdb->replace( $table, [ 'note_date' => $date, 'note_text' => $note ], [ '%s', '%s' ] );
    }

    simple_hotel_crm_clear_calendar_cache();

    return rest_ensure_response( [ 'success' => true, 'date' => $date, 'note' => $note ] );
}

function simple_hotel_crm_rest_save_booking_overlay( WP_REST_Request $request ) {
    global $wpdb;

    $reserved_room_id = absint( $request->get_param( 'reserved_room_id' ) );
    $booking_id       = absint( $request->get_param( 'booking_id' ) );
    $room_id          = absint( $request->get_param( 'room_id' ) );

    if ( $reserved_room_id <= 0 ) {
        return new WP_Error( 'missing_reserved_room', __( 'Missing reserved room ID.', 'simple-hotel-crm' ), [ 'status' => 400 ] );
    }

    $extras = simple_hotel_crm_evaluate_extras_formula( $request->get_param( 'extras_formula' ) );
    if ( ! $extras['valid'] ) {
        return new WP_Error( 'invalid_extras_formula', __( 'Extras formula only supports numbers joined with +.', 'simple-hotel-crm' ), [ 'status' => 400 ] );
    }

    $payload = [
        'booking_id'         => $booking_id,
        'reserved_room_id'   => $reserved_room_id,
        'room_id'            => $room_id,
        'booking_note'       => sanitize_textarea_field( (string) $request->get_param( 'booking_note' ) ),
        'extras_formula'     => $extras['formula'],
        'extras_total'       => $extras['total'],
        'manual_guest_name'  => sanitize_text_field( (string) $request->get_param( 'manual_guest_name' ) ),
        'manual_adults'      => '' === (string) $request->get_param( 'manual_adults' ) ? null : max( 0, intval( $request->get_param( 'manual_adults' ) ) ),
        'manual_children'    => '' === (string) $request->get_param( 'manual_children' ) ? null : max( 0, intval( $request->get_param( 'manual_children' ) ) ),
        'manual_tarif'       => simple_hotel_crm_normalize_decimal( $request->get_param( 'manual_tarif' ) ),
        'manual_commission'  => simple_hotel_crm_normalize_decimal( $request->get_param( 'manual_commission' ) ),
    ];

    $table = simple_hotel_crm_booking_overlay_table();
    $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE reserved_room_id = %d", $reserved_room_id ) );

    $formats = [ '%d', '%d', '%d', '%s', '%s', '%f', '%s', '%d', '%d', '%f', '%f' ];
    if ( $existing ) {
        $wpdb->update( $table, $payload, [ 'reserved_room_id' => $reserved_room_id ], $formats, [ '%d' ] );
    } else {
        $wpdb->insert( $table, $payload, $formats );
    }

    simple_hotel_crm_clear_calendar_cache();

    return rest_ensure_response( [
        'success'           => true,
        'reserved_room_id'  => $reserved_room_id,
        'extras_formula'    => $extras['formula'],
        'extras_total'      => $extras['total'],
        'occupancy_str'     => simple_hotel_crm_format_occupancy( $payload['manual_adults'] ?? 0, $payload['manual_children'] ?? 0 ),
    ] );
}

/**
 * Create invoice in Invoice Ninja via REST API.
 */
function simple_hotel_crm_rest_create_invoice( WP_REST_Request $request ) {
    $booking_id = absint( $request->get_param( 'booking_id' ) );
    $reserved_room_id = absint( $request->get_param( 'reserved_room_id' ) );
    if ( $booking_id <= 0 || $reserved_room_id <= 0 ) {
        return new WP_Error( 'invalid_booking', __( 'Invalid booking or room reference.', 'simple-hotel-crm' ), [ 'status' => 400 ] );
    }

    $api_url = get_option( 'simple_hotel_crm_invoice_ninja_url', '' );
    $api_token = get_option( 'simple_hotel_crm_invoice_ninja_token', '' );

    if ( empty( $api_url ) || empty( $api_token ) ) {
        return new WP_Error( 'invoice_ninja_not_configured', __( 'Invoice Ninja API not configured. Please set URL and token in settings.', 'simple-hotel-crm' ), [ 'status' => 500 ] );
    }

    $overlay = simple_hotel_crm_get_booking_overlay( $reserved_room_id );
    if ( empty( $overlay ) ) {
        return new WP_Error( 'no_overlay_data', __( 'No overlay data for this room booking yet. Save the tariff/commission/extras first.', 'simple-hotel-crm' ), [ 'status' => 404 ] );
    }

    $client_id = '';
    $source = simple_hotel_crm_get_data_source();
    if ( 'external_pg' === $source ) {
        $connection = simple_hotel_crm_get_external_pg_connection();
        if ( is_wp_error( $connection ) ) {
            return $connection;
        }

        $client_result = pg_query_params( $connection, 'SELECT invoice_ninja_client_id FROM bookings WHERE id = $1 LIMIT 1', [ $booking_id ] );
        if ( ! $client_result || 0 === pg_num_rows( $client_result ) ) {
            return new WP_Error( 'booking_not_found', __( 'Booking not found in the external database.', 'simple-hotel-crm' ), [ 'status' => 404 ] );
        }

        $client_row = pg_fetch_assoc( $client_result );
        $client_id = (string) ( $client_row['invoice_ninja_client_id'] ?? '' );
    } elseif ( 'wp_sync' === $source ) {
        global $wpdb;
        $crm_bookings_table = simple_hotel_crm_bookings_table();
        $client_id = (string) $wpdb->get_var( $wpdb->prepare( "SELECT invoice_ninja_client_id FROM {$crm_bookings_table} WHERE id = %d LIMIT 1", $booking_id ) );
        if ( '' === $client_id ) {
            return new WP_Error( 'booking_not_found', __( 'Booking not found in WordPress CRM data, or it has no Invoice Ninja client ID.', 'simple-hotel-crm' ), [ 'status' => 404 ] );
        }
    } else {
        $booking = get_post( $booking_id );
        if ( ! $booking || 'mphb_booking' !== $booking->post_type ) {
            return new WP_Error( 'booking_not_found', __( 'Booking not found.', 'simple-hotel-crm' ), [ 'status' => 404 ] );
        }

        $client_id = (string) get_post_meta( $booking_id, '_mphb_customer_id', true );
    }

    if ( '' === $client_id ) {
        return new WP_Error( 'no_customer', __( 'This booking has no linked Invoice Ninja client ID.', 'simple-hotel-crm' ), [ 'status' => 400 ] );
    }

    $invoice_lines = [];
    $total = 0.0;

    if ( isset( $overlay['manual_tarif'] ) && '' !== $overlay['manual_tarif'] && null !== $overlay['manual_tarif'] ) {
        $tarif = (float) $overlay['manual_tarif'];
        $invoice_lines[] = [ 'product_key' => 'room-charge', 'quantity' => 1, 'rate' => $tarif ];
        $total += $tarif;
    }

    if ( isset( $overlay['extras_total'] ) && '' !== $overlay['extras_total'] && null !== $overlay['extras_total'] ) {
        $extras = (float) $overlay['extras_total'];
        $invoice_lines[] = [ 'product_key' => 'extras-charge', 'quantity' => 1, 'rate' => $extras ];
        $total += $extras;
    }

    if ( isset( $overlay['manual_commission'] ) && '' !== $overlay['manual_commission'] && null !== $overlay['manual_commission'] ) {
        $commission = (float) $overlay['manual_commission'];
        $invoice_lines[] = [ 'product_key' => 'booking-commission', 'quantity' => 1, 'rate' => -$commission ];
        $total -= $commission;
    }

    if ( empty( $invoice_lines ) ) {
        return new WP_Error( 'empty_invoice', __( 'No invoiceable lines were found in the saved overlay data.', 'simple-hotel-crm' ), [ 'status' => 400 ] );
    }

    $payload = [
        'client_id' => $client_id,
        'lines' => $invoice_lines,
        'amount' => round( $total, 2 ),
        'is_amount_discount' => false,
        'discount' => 0,
    ];

    $response = wp_remote_post( trailingslashit( $api_url ) . 'api/v1/invoices', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ],
        'body' => wp_json_encode( $payload ),
        'timeout' => 15,
    ] );

    if ( is_wp_error( $response ) ) {
        return new WP_Error( 'invoice_ninja_request_failed', $response->get_error_message(), [ 'status' => 500 ] );
    }

    $code = wp_remote_retrieve_response_code( $response );
    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );

    if ( 201 !== $code || empty( $data['id'] ) ) {
        return new WP_Error( 'invoice_ninja_invalid_response', __( 'Failed to create invoice. Response:', 'simple-hotel-crm' ) . ' ' . $code . ' - ' . $body, [ 'status' => $code >= 500 ? 502 : 500 ] );
    }

    if ( 'external_pg' === $source ) {
        $connection = simple_hotel_crm_get_external_pg_connection();
        if ( ! is_wp_error( $connection ) ) {
            pg_query_params( $connection, 'UPDATE bookings SET invoice_ninja_invoice_id = $1, invoiced_at = NOW() WHERE id = $2', [ (string) $data['id'], $booking_id ] );
        }
    } elseif ( 'wp_sync' === $source ) {
        global $wpdb;
        $crm_bookings_table = simple_hotel_crm_bookings_table();
        $sync_bookings_table = simple_hotel_crm_sync_bookings_table();
        $wpdb->update(
            $crm_bookings_table,
            [
                'invoice_ninja_invoice_id' => (string) $data['id'],
                'invoiced_at' => current_time( 'mysql' ),
            ],
            [ 'id' => $booking_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );
        $wpdb->update(
            $sync_bookings_table,
            [ 'invoice_ninja_invoice_id' => (string) $data['id'] ],
            [ 'external_booking_room_id' => $reserved_room_id ],
            [ '%s' ],
            [ '%d' ]
        );
    } else {
        update_post_meta( $booking_id, '_simple_hotel_crm_invoice_ninja_id', $data['id'] );
    }

    return rest_ensure_response( [
        'success' => true,
        'invoice_id' => $data['id'],
        'invoice_number' => $data['invoice_number'] ?? '',
        'amount' => $data['amount'] ?? $total,
    ] );
}
