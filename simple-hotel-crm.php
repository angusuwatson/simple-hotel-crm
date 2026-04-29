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
             WHERE b.status_code IN ('pending', 'confirmed', 'checked_in')
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
            'booking_note' => isset( $overlay['booking_note'] ) ? (string) $overlay['booking_note'] : '',
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

add_action( 'admin_menu', 'simple_hotel_crm_register_admin_menu' );
function simple_hotel_crm_register_admin_menu() {
    if ( ! simple_hotel_crm_user_can_access() ) {
        return;
    }

    add_menu_page( __( 'Simple Hotel CRM', 'simple-hotel-crm' ), __( 'Simple Hotel CRM', 'simple-hotel-crm' ), 'manage_options', 'simple-hotel-crm', 'simple_hotel_crm_render_admin_page', 'dashicons-calendar-alt', 58 );
    add_submenu_page( 'simple-hotel-crm', __( 'Calendar', 'simple-hotel-crm' ), __( 'Calendar', 'simple-hotel-crm' ), 'manage_options', 'simple-hotel-crm', 'simple_hotel_crm_render_admin_page' );
    add_submenu_page( 'simple-hotel-crm', __( 'Add Booking', 'simple-hotel-crm' ), __( 'Add Booking', 'simple-hotel-crm' ), 'manage_options', 'simple-hotel-crm-add-booking', 'simple_hotel_crm_render_add_booking_page' );
    add_submenu_page( 'simple-hotel-crm', __( 'Bookings', 'simple-hotel-crm' ), __( 'Bookings', 'simple-hotel-crm' ), 'manage_options', 'simple-hotel-crm-bookings', 'simple_hotel_crm_render_bookings_page' );
    add_submenu_page( 'simple-hotel-crm', __( 'Guests', 'simple-hotel-crm' ), __( 'Guests', 'simple-hotel-crm' ), 'manage_options', 'simple-hotel-crm-guests', 'simple_hotel_crm_render_guests_page' );
    add_submenu_page( 'simple-hotel-crm', __( 'Invoice Ninja Settings', 'simple-hotel-crm' ), __( 'Settings', 'simple-hotel-crm' ), 'manage_options', 'simple-hotel-crm-settings', 'simple_hotel_crm_render_settings_page' );
}

function simple_hotel_crm_render_admin_page() {
    if ( ! simple_hotel_crm_user_can_access() ) {
        wp_die( esc_html__( 'You do not have permission to access this page.', 'simple-hotel-crm' ) );
    }

    $month = isset( $_GET['month'] ) ? intval( $_GET['month'] ) : intval( date( 'n' ) );
    $year  = isset( $_GET['year'] ) ? intval( $_GET['year'] ) : intval( date( 'Y' ) );
    $calendar_data = simple_hotel_crm_get_calendar_data( $month, $year );

    echo '<div class="wrap">';
    if ( isset( $_GET['created_booking'] ) ) {
        echo '<div class="notice notice-success"><p>' . esc_html( sprintf( __( 'Booking %d created.', 'simple-hotel-crm' ), absint( $_GET['created_booking'] ) ) ) . '</p></div>';
    }
    echo simple_hotel_crm_render_calendar( $calendar_data, 'admin' );
    echo '</div>';
}

function simple_hotel_crm_render_bookings_page() {
    if ( ! simple_hotel_crm_user_can_access() ) {
        wp_die( esc_html__( 'You do not have permission to access this page.', 'simple-hotel-crm' ) );
    }

    global $wpdb;
    $bookings_table = simple_hotel_crm_bookings_table();
    $guests_table = simple_hotel_crm_guests_table();
    $booking_rooms_table = simple_hotel_crm_booking_rooms_table();

    $rows = $wpdb->get_results(
        "SELECT b.id, b.status_code, b.check_in_date, b.check_out_date, b.source_channel, b.total_amount, b.created_at,
                g.first_name, g.last_name,
                COUNT(br.id) AS room_count
         FROM {$bookings_table} b
         JOIN {$guests_table} g ON g.id = b.guest_id
         LEFT JOIN {$booking_rooms_table} br ON br.booking_id = b.id
         GROUP BY b.id, b.status_code, b.check_in_date, b.check_out_date, b.source_channel, b.total_amount, b.created_at, g.first_name, g.last_name
         ORDER BY b.check_in_date DESC, b.id DESC
         LIMIT 200",
        ARRAY_A
    );

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__( 'Bookings', 'simple-hotel-crm' ) . '</h1>';
    echo '<table class="widefat striped"><thead><tr>';
    echo '<th>' . esc_html__( 'ID', 'simple-hotel-crm' ) . '</th>';
    echo '<th>' . esc_html__( 'Guest', 'simple-hotel-crm' ) . '</th>';
    echo '<th>' . esc_html__( 'Check-in', 'simple-hotel-crm' ) . '</th>';
    echo '<th>' . esc_html__( 'Check-out', 'simple-hotel-crm' ) . '</th>';
    echo '<th>' . esc_html__( 'Rooms', 'simple-hotel-crm' ) . '</th>';
    echo '<th>' . esc_html__( 'Status', 'simple-hotel-crm' ) . '</th>';
    echo '<th>' . esc_html__( 'Channel', 'simple-hotel-crm' ) . '</th>';
    echo '<th>' . esc_html__( 'Total', 'simple-hotel-crm' ) . '</th>';
    echo '</tr></thead><tbody>';
    if ( empty( $rows ) ) {
        echo '<tr><td colspan="8">' . esc_html__( 'No bookings found.', 'simple-hotel-crm' ) . '</td></tr>';
    } else {
        foreach ( $rows as $row ) {
            $guest_name = trim( (string) $row['first_name'] . ' ' . (string) $row['last_name'] );
            echo '<tr>';
            echo '<td>' . esc_html( (string) $row['id'] ) . '</td>';
            echo '<td>' . esc_html( $guest_name ) . '</td>';
            echo '<td>' . esc_html( (string) $row['check_in_date'] ) . '</td>';
            echo '<td>' . esc_html( (string) $row['check_out_date'] ) . '</td>';
            echo '<td>' . esc_html( (string) $row['room_count'] ) . '</td>';
            echo '<td>' . esc_html( (string) $row['status_code'] ) . '</td>';
            echo '<td>' . esc_html( (string) $row['source_channel'] ) . '</td>';
            echo '<td>' . esc_html( number_format( (float) $row['total_amount'], 2, '.', '' ) ) . '</td>';
            echo '</tr>';
        }
    }
    echo '</tbody></table>';
    echo '</div>';
}

function simple_hotel_crm_render_guests_page() {
    if ( ! simple_hotel_crm_user_can_access() ) {
        wp_die( esc_html__( 'You do not have permission to access this page.', 'simple-hotel-crm' ) );
    }

    global $wpdb;
    $guests_table = simple_hotel_crm_guests_table();
    $bookings_table = simple_hotel_crm_bookings_table();

    $rows = $wpdb->get_results(
        "SELECT g.id, g.first_name, g.last_name, g.email, g.phone, COUNT(b.id) AS booking_count
         FROM {$guests_table} g
         LEFT JOIN {$bookings_table} b ON b.guest_id = g.id
         GROUP BY g.id, g.first_name, g.last_name, g.email, g.phone
         ORDER BY g.last_name ASC, g.first_name ASC, g.id DESC
         LIMIT 200",
        ARRAY_A
    );

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__( 'Guests', 'simple-hotel-crm' ) . '</h1>';
    echo '<table class="widefat striped"><thead><tr>';
    echo '<th>' . esc_html__( 'ID', 'simple-hotel-crm' ) . '</th>';
    echo '<th>' . esc_html__( 'Name', 'simple-hotel-crm' ) . '</th>';
    echo '<th>' . esc_html__( 'Email', 'simple-hotel-crm' ) . '</th>';
    echo '<th>' . esc_html__( 'Phone', 'simple-hotel-crm' ) . '</th>';
    echo '<th>' . esc_html__( 'Bookings', 'simple-hotel-crm' ) . '</th>';
    echo '</tr></thead><tbody>';
    if ( empty( $rows ) ) {
        echo '<tr><td colspan="5">' . esc_html__( 'No guests found.', 'simple-hotel-crm' ) . '</td></tr>';
    } else {
        foreach ( $rows as $row ) {
            $guest_name = trim( (string) $row['first_name'] . ' ' . (string) $row['last_name'] );
            echo '<tr>';
            echo '<td>' . esc_html( (string) $row['id'] ) . '</td>';
            echo '<td>' . esc_html( $guest_name ) . '</td>';
            echo '<td>' . esc_html( (string) $row['email'] ) . '</td>';
            echo '<td>' . esc_html( (string) $row['phone'] ) . '</td>';
            echo '<td>' . esc_html( (string) $row['booking_count'] ) . '</td>';
            echo '</tr>';
        }
    }
    echo '</tbody></table>';
    echo '</div>';
}

function simple_hotel_crm_render_add_booking_page() {
    if ( ! simple_hotel_crm_user_can_access() ) {
        wp_die( esc_html__( 'You do not have permission to access this page.', 'simple-hotel-crm' ) );
    }

    $error_message = '';
    $defaults = [
        'guest_name' => '',
        'phone' => '',
        'check_in' => '',
        'check_out' => '',
        'source_channel' => 'direct',
        'status_code' => 'confirmed',
        'contacted_date' => gmdate( 'Y-m-d' ),
        'import_notes' => '',
        'room_lines' => [ [ 'room_sync_id' => '', 'adults' => 2, 'children' => 0, 'babies' => 0, 'room_rate_amount' => '0.00', 'extras_amount' => '0.00' ] ],
    ];
    $form_data = array_merge( $defaults, wp_unslash( $_POST ) );

    if ( isset( $_POST['lgf_add_booking_submit'] ) ) {
        check_admin_referer( 'simple_hotel_crm_add_booking', 'simple_hotel_crm_add_booking_nonce' );
        $result = simple_hotel_crm_create_booking( $form_data );
        if ( is_wp_error( $result ) ) {
            $error_message = $result->get_error_message();
        } else {
            $check_in_ts = strtotime( (string) $form_data['check_in'] );
            $redirect_url = add_query_arg(
                [
                    'page' => 'simple-hotel-crm',
                    'month' => $check_in_ts ? (int) gmdate( 'n', $check_in_ts ) : (int) gmdate( 'n' ),
                    'year' => $check_in_ts ? (int) gmdate( 'Y', $check_in_ts ) : (int) gmdate( 'Y' ),
                    'created_booking' => (int) $result['booking_id'],
                ],
                admin_url( 'admin.php' )
            );
            echo '<script>window.location.href=' . wp_json_encode( $redirect_url ) . ';</script>';
            echo '<p><a href="' . esc_url( $redirect_url ) . '">' . esc_html__( 'Continue to calendar', 'simple-hotel-crm' ) . '</a></p>';
            return;
        }
    }

    $check_in_value = (string) $form_data['check_in'];
    $check_out_value = (string) $form_data['check_out'];
    if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $check_in_value ) && ( '' === $check_out_value || $check_out_value <= $check_in_value ) ) {
        $check_out_value = gmdate( 'Y-m-d', strtotime( $check_in_value . ' +1 day' ) );
        $form_data['check_out'] = $check_out_value;
    }

    $rooms = simple_hotel_crm_get_room_options( $check_in_value, $check_out_value );
    if ( is_wp_error( $rooms ) ) {
        $error_message = $rooms->get_error_message();
        $rooms = [];
    }
    $channels = simple_hotel_crm_get_booking_channel_options();
    $statuses = simple_hotel_crm_get_booking_status_options();
    $dates_ready = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $check_in_value ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $check_out_value ) && $check_out_value > $check_in_value;

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__( 'Add Booking', 'simple-hotel-crm' ) . '</h1>';
    if ( $error_message ) {
        echo '<div class="notice notice-error"><p>' . esc_html( $error_message ) . '</p></div>';
    }
    if ( isset( $_POST['lgf_add_room_line'] ) && is_array( $form_data['room_lines'] ) ) {
        $form_data['room_lines'][] = [ 'room_sync_id' => '', 'adults' => 2, 'children' => 0, 'babies' => 0, 'room_rate_amount' => '0.00', 'extras_amount' => '0.00' ];
    }

    echo '<p>' . esc_html__( 'Choose dates first, then add one or more available rooms for the same booking.', 'simple-hotel-crm' ) . '</p>';
    echo '<form method="post">';
    wp_nonce_field( 'simple_hotel_crm_add_booking', 'simple_hotel_crm_add_booking_nonce' );
    echo '<table class="form-table">';
    echo '<tr><th><label for="guest_name">' . esc_html__( 'Guest name', 'simple-hotel-crm' ) . '</label></th><td><input required type="text" name="guest_name" id="guest_name" class="regular-text" value="' . esc_attr( (string) $form_data['guest_name'] ) . '" /></td></tr>';
    echo '<tr><th><label for="phone">' . esc_html__( 'Phone', 'simple-hotel-crm' ) . '</label></th><td><input type="text" name="phone" id="phone" class="regular-text" value="' . esc_attr( (string) $form_data['phone'] ) . '" /></td></tr>';
    echo '<tr><th><label for="check_in">' . esc_html__( 'Check-in', 'simple-hotel-crm' ) . '</label></th><td><input required type="date" name="check_in" id="check_in" value="' . esc_attr( $check_in_value ) . '" onchange="var o=document.getElementById(\'check_out\');if(this.value&&o&&(!o.value||o.value<=this.value)){var d=new Date(this.value+\'T00:00:00\');d.setDate(d.getDate()+1);o.value=d.toISOString().slice(0,10);} this.form.submit();" /></td></tr>';
    echo '<tr><th><label for="check_out">' . esc_html__( 'Check-out', 'simple-hotel-crm' ) . '</label></th><td><input required type="date" name="check_out" id="check_out" value="' . esc_attr( $check_out_value ) . '" onchange="this.form.submit();" /></td></tr>';
    echo '<tr><th scope="row">' . esc_html__( 'Rooms', 'simple-hotel-crm' ) . '</th><td>';
    $room_numbers = [ 'ANE' => 1, 'DEL' => 2, 'LYS' => 3, 'TOU' => 4, 'TUL' => 5, 'COQ' => 0 ];
    $selected_room_ids = [];
    foreach ( (array) $form_data['room_lines'] as $line_index => $line ) {
        echo '<fieldset style="margin:0 0 16px 0;padding:12px;border:1px solid #ccd0d4;">';
        echo '<legend><strong>' . esc_html( sprintf( __( 'Room %d', 'simple-hotel-crm' ), $line_index + 1 ) ) . '</strong></legend>';
        echo '<p><label>' . esc_html__( 'Room', 'simple-hotel-crm' ) . ' <select name="room_lines[' . esc_attr( $line_index ) . '][room_sync_id]" ' . ( $dates_ready ? '' : 'disabled' ) . ' required>';
        echo '<option value="">' . esc_html__( $dates_ready ? 'Select an available room' : 'Choose dates first', 'simple-hotel-crm' ) . '</option>';
        foreach ( $rooms as $room ) {
            $room_id_value = (string) $room['id'];
            $is_selected_elsewhere = in_array( $room_id_value, $selected_room_ids, true );
            $current_selected = (string) ( $line['room_sync_id'] ?? '' );
            if ( $is_selected_elsewhere && $current_selected !== $room_id_value ) {
                continue;
            }
            $room_code = (string) $room['room_code'];
            $room_number = $room_numbers[ $room_code ] ?? '';
            echo '<option value="' . esc_attr( $room_id_value ) . '"' . selected( $current_selected, $room_id_value, false ) . '>' . esc_html( $room_number . ' - ' . $room['room_name'] ) . '</option>';
        }
        echo '</select></label></p>';
        $selected_room_ids[] = (string) ( $line['room_sync_id'] ?? '' );
        echo '<p><label>' . esc_html__( 'Adults', 'simple-hotel-crm' ) . ' <input type="number" min="0" name="room_lines[' . esc_attr( $line_index ) . '][adults]" value="' . esc_attr( (string) ( $line['adults'] ?? 0 ) ) . '" /></label> ';
        echo '<label>' . esc_html__( 'Children', 'simple-hotel-crm' ) . ' <input type="number" min="0" name="room_lines[' . esc_attr( $line_index ) . '][children]" value="' . esc_attr( (string) ( $line['children'] ?? 0 ) ) . '" /></label> ';
        echo '<label>' . esc_html__( 'Babies', 'simple-hotel-crm' ) . ' <input type="number" min="0" name="room_lines[' . esc_attr( $line_index ) . '][babies]" value="' . esc_attr( (string) ( $line['babies'] ?? 0 ) ) . '" /></label></p>';
        echo '<p><label>' . esc_html__( 'Room rate total', 'simple-hotel-crm' ) . ' <input type="text" name="room_lines[' . esc_attr( $line_index ) . '][room_rate_amount]" value="' . esc_attr( (string) ( $line['room_rate_amount'] ?? '0.00' ) ) . '" /></label> ';
        echo '<label>' . esc_html__( 'Extras total', 'simple-hotel-crm' ) . ' <input type="text" name="room_lines[' . esc_attr( $line_index ) . '][extras_amount]" value="' . esc_attr( (string) ( $line['extras_amount'] ?? '0.00' ) ) . '" /></label></p>';
        echo '</fieldset>';
    }
    if ( $dates_ready && empty( $rooms ) ) {
        echo '<p class="description">' . esc_html__( 'No rooms are available for those dates.', 'simple-hotel-crm' ) . '</p>';
    }
    submit_button( __( 'Add another room', 'simple-hotel-crm' ), 'secondary', 'lgf_add_room_line', false );
    echo '</td></tr>';
    echo '<tr><th><label for="source_channel">' . esc_html__( 'Channel', 'simple-hotel-crm' ) . '</label></th><td><select name="source_channel" id="source_channel">';
    foreach ( $channels as $code => $label ) {
        echo '<option value="' . esc_attr( $code ) . '"' . selected( (string) $form_data['source_channel'], $code, false ) . '>' . esc_html( $label ) . '</option>';
    }
    echo '</select></td></tr>';
    echo '<tr><th><label for="status_code">' . esc_html__( 'Status', 'simple-hotel-crm' ) . '</label></th><td><select name="status_code" id="status_code">';
    foreach ( $statuses as $code => $label ) {
        echo '<option value="' . esc_attr( $code ) . '"' . selected( (string) $form_data['status_code'], $code, false ) . '>' . esc_html( $label ) . '</option>';
    }
    echo '</select></td></tr>';
    echo '<tr><th><label for="contacted_date">' . esc_html__( 'Contacted date', 'simple-hotel-crm' ) . '</label></th><td><input type="date" name="contacted_date" id="contacted_date" value="' . esc_attr( (string) $form_data['contacted_date'] ) . '" /></td></tr>';
    echo '<tr><th><label for="import_notes">' . esc_html__( 'Import / internal notes', 'simple-hotel-crm' ) . '</label></th><td><textarea name="import_notes" id="import_notes" rows="4" class="large-text">' . esc_textarea( (string) $form_data['import_notes'] ) . '</textarea></td></tr>';
    echo '</table>';
    submit_button( __( 'Create Booking', 'simple-hotel-crm' ), 'primary', 'lgf_add_booking_submit' );
    echo '</form>';
    echo '</div>';
}

/**
 * Render Invoice Ninja settings page.
 */
function simple_hotel_crm_render_settings_page() {
    if ( ! simple_hotel_crm_user_can_access() ) {
        wp_die( esc_html__( 'You do not have permission to access this page.', 'simple-hotel-crm' ) );
    }

    if ( isset( $_POST['simple_hotel_crm_submit'] ) ) {
        check_admin_referer( 'simple_hotel_crm_settings', 'simple_hotel_crm_settings_nonce' );

        $api_url = isset( $_POST['simple_hotel_crm_invoice_ninja_url'] ) ? esc_url_raw( trim( wp_unslash( $_POST['simple_hotel_crm_invoice_ninja_url'] ) ) ) : '';
        $api_token = isset( $_POST['simple_hotel_crm_invoice_ninja_token'] ) ? sanitize_text_field( trim( wp_unslash( $_POST['simple_hotel_crm_invoice_ninja_token'] ) ) ) : '';
        $booking_source = isset( $_POST['simple_hotel_crm_booking_source'] ) ? sanitize_text_field( wp_unslash( $_POST['simple_hotel_crm_booking_source'] ) ) : 'wp_sync';
        $booking_source = in_array( $booking_source, [ 'motopress', 'wp_sync', 'external_pg' ], true ) ? $booking_source : 'wp_sync';

        update_option( 'simple_hotel_crm_invoice_ninja_url', $api_url );
        update_option( 'simple_hotel_crm_invoice_ninja_token', $api_token );
        update_option( 'simple_hotel_crm_booking_source', $booking_source );
        update_option( 'simple_hotel_crm_external_pg_host', isset( $_POST['simple_hotel_crm_external_pg_host'] ) ? sanitize_text_field( trim( wp_unslash( $_POST['simple_hotel_crm_external_pg_host'] ) ) ) : '' );
        update_option( 'simple_hotel_crm_external_pg_port', isset( $_POST['simple_hotel_crm_external_pg_port'] ) ? sanitize_text_field( trim( wp_unslash( $_POST['simple_hotel_crm_external_pg_port'] ) ) ) : '5432' );
        update_option( 'simple_hotel_crm_external_pg_dbname', isset( $_POST['simple_hotel_crm_external_pg_dbname'] ) ? sanitize_text_field( trim( wp_unslash( $_POST['simple_hotel_crm_external_pg_dbname'] ) ) ) : '' );
        update_option( 'simple_hotel_crm_external_pg_user', isset( $_POST['simple_hotel_crm_external_pg_user'] ) ? sanitize_text_field( trim( wp_unslash( $_POST['simple_hotel_crm_external_pg_user'] ) ) ) : '' );
        update_option( 'simple_hotel_crm_external_pg_password', isset( $_POST['simple_hotel_crm_external_pg_password'] ) ? sanitize_text_field( trim( wp_unslash( $_POST['simple_hotel_crm_external_pg_password'] ) ) ) : '' );
        update_option( 'simple_hotel_crm_external_pg_sslmode', isset( $_POST['simple_hotel_crm_external_pg_sslmode'] ) ? sanitize_text_field( trim( wp_unslash( $_POST['simple_hotel_crm_external_pg_sslmode'] ) ) ) : 'disable' );

        simple_hotel_crm_clear_calendar_cache();
        echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.', 'simple-hotel-crm' ) . '</p></div>';
    }

    $api_url = get_option( 'simple_hotel_crm_invoice_ninja_url', '' );
    $api_token = get_option( 'simple_hotel_crm_invoice_ninja_token', '' );
    $booking_source = simple_hotel_crm_get_data_source();
    $external_db = simple_hotel_crm_get_external_db_settings();

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__( 'Simple Hotel CRM Settings', 'simple-hotel-crm' ) . '</h1>';
    echo '<form method="post">';
    wp_nonce_field( 'simple_hotel_crm_settings', 'simple_hotel_crm_settings_nonce' );
    echo '<h2>' . esc_html__( 'Booking Source', 'simple-hotel-crm' ) . '</h2>';
    echo '<table class="form-table">';
    echo '<tr><th scope="row"><label for="simple_hotel_crm_booking_source">' . esc_html__( 'Booking data source', 'simple-hotel-crm' ) . '</label></th>';
    echo '<td><select id="simple_hotel_crm_booking_source" name="simple_hotel_crm_booking_source">';
    echo '<option value="motopress"' . selected( $booking_source, 'motopress', false ) . '>' . esc_html__( 'MotoPress / WordPress', 'simple-hotel-crm' ) . '</option>';
    echo '<option value="wp_sync"' . selected( $booking_source, 'wp_sync', false ) . '>' . esc_html__( 'WordPress CRM tables', 'simple-hotel-crm' ) . '</option>';
    echo '<option value="external_pg"' . selected( $booking_source, 'external_pg', false ) . '>' . esc_html__( 'External PostgreSQL (LGF database)', 'simple-hotel-crm' ) . '</option>';
    echo '</select><p class="description">' . esc_html__( 'Use the WordPress CRM tables as the primary local system of record, or external PostgreSQL for direct local development/testing.', 'simple-hotel-crm' ) . '</p></td></tr>';
    echo '</table>';

    echo '<h2>' . esc_html__( 'WordPress CRM', 'simple-hotel-crm' ) . '</h2>';
    echo '<p>' . esc_html__( 'New bookings created in WordPress write to the CRM tables first. Compatibility sync rows are still maintained behind the scenes for the current calendar and integrations.', 'simple-hotel-crm' ) . '</p>';

    echo '<h2>' . esc_html__( 'External PostgreSQL', 'simple-hotel-crm' ) . '</h2>';
    echo '<table class="form-table">';
    echo '<tr><th scope="row"><label for="simple_hotel_crm_external_pg_host">' . esc_html__( 'Host', 'simple-hotel-crm' ) . '</label></th><td><input type="text" id="simple_hotel_crm_external_pg_host" name="simple_hotel_crm_external_pg_host" value="' . esc_attr( $external_db['host'] ) . '" class="regular-text" placeholder="127.0.0.1 or postgres" /></td></tr>';
    echo '<tr><th scope="row"><label for="simple_hotel_crm_external_pg_port">' . esc_html__( 'Port', 'simple-hotel-crm' ) . '</label></th><td><input type="text" id="simple_hotel_crm_external_pg_port" name="simple_hotel_crm_external_pg_port" value="' . esc_attr( $external_db['port'] ) . '" class="small-text" placeholder="5432" /></td></tr>';
    echo '<tr><th scope="row"><label for="simple_hotel_crm_external_pg_dbname">' . esc_html__( 'Database name', 'simple-hotel-crm' ) . '</label></th><td><input type="text" id="simple_hotel_crm_external_pg_dbname" name="simple_hotel_crm_external_pg_dbname" value="' . esc_attr( $external_db['dbname'] ) . '" class="regular-text" placeholder="lgf_bookings" /></td></tr>';
    echo '<tr><th scope="row"><label for="simple_hotel_crm_external_pg_user">' . esc_html__( 'User', 'simple-hotel-crm' ) . '</label></th><td><input type="text" id="simple_hotel_crm_external_pg_user" name="simple_hotel_crm_external_pg_user" value="' . esc_attr( $external_db['user'] ) . '" class="regular-text" placeholder="lgf" /></td></tr>';
    echo '<tr><th scope="row"><label for="simple_hotel_crm_external_pg_password">' . esc_html__( 'Password', 'simple-hotel-crm' ) . '</label></th><td><input type="password" id="simple_hotel_crm_external_pg_password" name="simple_hotel_crm_external_pg_password" value="' . esc_attr( $external_db['password'] ) . '" class="regular-text" /></td></tr>';
    echo '<tr><th scope="row"><label for="simple_hotel_crm_external_pg_sslmode">' . esc_html__( 'SSL mode', 'simple-hotel-crm' ) . '</label></th><td><select id="simple_hotel_crm_external_pg_sslmode" name="simple_hotel_crm_external_pg_sslmode">';
    foreach ( [ 'disable', 'prefer', 'require' ] as $sslmode ) {
        echo '<option value="' . esc_attr( $sslmode ) . '"' . selected( $external_db['sslmode'], $sslmode, false ) . '>' . esc_html( $sslmode ) . '</option>';
    }
    echo '</select></td></tr>';
    echo '</table>';

    echo '<h2>' . esc_html__( 'Invoice Ninja', 'simple-hotel-crm' ) . '</h2>';
    echo '<table class="form-table">';
    echo '<tr><th scope="row"><label for="simple_hotel_crm_invoice_ninja_url">' . esc_html__( 'Invoice Ninja URL', 'simple-hotel-crm' ) . '</label></th>';
    echo '<td><input type="url" id="simple_hotel_crm_invoice_ninja_url" name="simple_hotel_crm_invoice_ninja_url" value="' . esc_attr( $api_url ) . '" class="regular-text" placeholder="https://your-invoice-ninja.com" /></td></tr>';
    echo '<tr><th scope="row"><label for="simple_hotel_crm_invoice_ninja_token">' . esc_html__( 'API Token', 'simple-hotel-crm' ) . '</label></th>';
    echo '<td><input type="password" id="simple_hotel_crm_invoice_ninja_token" name="simple_hotel_crm_invoice_ninja_token" value="' . esc_attr( $api_token ) . '" class="regular-text" /></td></tr>';
    echo '</table>';
    submit_button( __( 'Save Settings', 'simple-hotel-crm' ), 'primary', 'simple_hotel_crm_submit' );
    echo '</form>';
    echo '</div>';
}

add_shortcode( 'simple_hotel_crm', 'simple_hotel_crm_shortcode' );
add_shortcode( 'lgf_calendar_view', 'simple_hotel_crm_shortcode' );
function simple_hotel_crm_render_calendar( $calendar_data, $context = 'frontend' ) {
    $template = locate_template( 'simple-hotel-crm/booking-view.php' );
    if ( ! $template ) {
        $template = locate_template( 'lgf-calendar-view/booking-view.php' );
    }
    if ( ! $template ) {
        $template = plugin_dir_path( __FILE__ ) . 'templates/booking-view.php';
    }

    $calendar_context = $context;
    $calendar_base_url = 'admin' === $context ? admin_url( 'admin.php?page=simple-hotel-crm' ) : get_permalink();

    ob_start();
    include $template;
    return ob_get_clean();
}

function simple_hotel_crm_shortcode( $atts ) {
    if ( ! simple_hotel_crm_user_can_access() ) {
        return '';
    }

    $atts = shortcode_atts( [ 'month' => date( 'n' ), 'year' => date( 'Y' ) ], $atts, 'simple_hotel_crm' );
    $month = intval( $atts['month'] );
    $year  = intval( $atts['year'] );
    $calendar_data = simple_hotel_crm_get_calendar_data( $month, $year );

    return simple_hotel_crm_render_calendar( $calendar_data, 'frontend' );
}

function simple_hotel_crm_clear_calendar_cache() {
    global $wpdb;
    $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_simple_hotel_crm_%' OR option_name LIKE '_transient_timeout_simple_hotel_crm_%'" );
}

function simple_hotel_crm_get_sync_room_options( $check_in = '', $check_out = '' ) {
    global $wpdb;
    $rooms_table = simple_hotel_crm_rooms_table();
    $bookings_table = simple_hotel_crm_bookings_table();
    $booking_rooms_table = simple_hotel_crm_booking_rooms_table();

    if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $check_in ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $check_out ) && $check_out > $check_in ) {
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.sync_room_id AS id, r.external_room_id, r.room_code, r.room_name
                 FROM {$rooms_table} r
                 WHERE r.active = 1
                   AND r.sync_room_id IS NOT NULL
                   AND NOT EXISTS (
                       SELECT 1 FROM {$booking_rooms_table} br
                       JOIN {$bookings_table} b ON b.id = br.booking_id
                       WHERE br.room_id = r.id
                         AND b.status_code IN ('pending', 'confirmed', 'checked_in')
                         AND b.check_in_date < %s
                         AND b.check_out_date > %s
                   )
                 ORDER BY r.sort_order ASC, r.room_name ASC",
                $check_out,
                $check_in
            ),
            ARRAY_A
        );
    }

    return $wpdb->get_results( "SELECT sync_room_id AS id, external_room_id, room_code, room_name FROM {$rooms_table} WHERE active = 1 AND sync_room_id IS NOT NULL ORDER BY sort_order ASC, room_name ASC", ARRAY_A );
}

function simple_hotel_crm_get_external_room_options( $check_in = '', $check_out = '' ) {
    $connection = simple_hotel_crm_get_external_pg_connection();
    if ( is_wp_error( $connection ) ) {
        return $connection;
    }

    if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $check_in ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $check_out ) && $check_out > $check_in ) {
        $result = pg_query_params(
            $connection,
            "SELECT r.id, r.code AS room_code, r.name AS room_name
             FROM rooms r
             WHERE r.active = true
               AND NOT EXISTS (
                   SELECT 1
                   FROM booking_rooms br
                   JOIN bookings b ON b.id = br.booking_id
                   JOIN booking_statuses bs ON bs.code = b.status_code
                   WHERE br.room_id = r.id
                     AND bs.blocks_availability = true
                     AND b.check_in_date < $1::date
                     AND b.check_out_date > $2::date
               )
             ORDER BY r.code ASC, r.name ASC",
            [ $check_out, $check_in ]
        );
    } else {
        $result = pg_query( $connection, "SELECT id, code AS room_code, name AS room_name FROM rooms WHERE active = true ORDER BY code ASC, name ASC" );
    }

    if ( ! $result ) {
        return [];
    }

    $rooms = [];
    while ( $row = pg_fetch_assoc( $result ) ) {
        $rooms[] = [
            'id' => (int) $row['id'],
            'external_room_id' => (int) $row['id'],
            'room_code' => (string) $row['room_code'],
            'room_name' => (string) $row['room_name'],
        ];
    }

    usort( $rooms, function( $a, $b ) {
        return ( simple_hotel_crm_get_room_sort_order( $a['room_code'], $a['room_name'] ) <=> simple_hotel_crm_get_room_sort_order( $b['room_code'], $b['room_name'] ) ) ?: strcasecmp( $a['room_name'], $b['room_name'] );
    } );

    return $rooms;
}

function simple_hotel_crm_get_room_options( $check_in = '', $check_out = '' ) {
    return 'external_pg' === simple_hotel_crm_get_data_source()
        ? simple_hotel_crm_get_external_room_options( $check_in, $check_out )
        : simple_hotel_crm_get_sync_room_options( $check_in, $check_out );
}

function simple_hotel_crm_get_booking_status_options() {
    return [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'checked_in' => 'Checked in',
        'checked_out' => 'Checked out',
        'cancelled' => 'Cancelled',
        'no_show' => 'No show',
    ];
}

function simple_hotel_crm_get_booking_channel_options() {
    return [
        'direct' => 'Website',
        'booking_com' => 'Booking.com',
        'email' => 'Email',
        'telephone' => 'Telephone',
        'motopress' => 'MotoPress',
    ];
}

function simple_hotel_crm_distribute_amounts( $total, $count ) {
    $count = max( 1, (int) $count );
    $total_cents = (int) round( (float) $total * 100 );
    $base = intdiv( $total_cents, $count );
    $remainder = $total_cents % $count;
    $values = [];
    for ( $i = 0; $i < $count; $i++ ) {
        $values[] = ( $base + ( $i < $remainder ? 1 : 0 ) ) / 100;
    }
    return $values;
}

function simple_hotel_crm_validate_booking_form_data( $data ) {
    $guest_name = trim( (string) ( $data['guest_name'] ?? '' ) );
    if ( '' === $guest_name ) {
        return new WP_Error( 'missing_guest_name', __( 'Guest name is required.', 'simple-hotel-crm' ) );
    }

    $check_in = (string) ( $data['check_in'] ?? '' );
    $check_out = (string) ( $data['check_out'] ?? '' );
    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $check_in ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $check_out ) || $check_out <= $check_in ) {
        return new WP_Error( 'invalid_dates', __( 'Check-in and check-out dates are invalid.', 'simple-hotel-crm' ) );
    }

    $nights = max( 1, (int) round( ( strtotime( $check_out ) - strtotime( $check_in ) ) / DAY_IN_SECONDS ) );
    $contacted_date = isset( $data['contacted_date'] ) ? (string) $data['contacted_date'] : '';
    if ( '' !== $contacted_date && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $contacted_date ) ) {
        return new WP_Error( 'invalid_contacted_date', __( 'Contacted date is invalid.', 'simple-hotel-crm' ) );
    }

    $room_lines = isset( $data['room_lines'] ) && is_array( $data['room_lines'] ) ? $data['room_lines'] : [];
    if ( empty( $room_lines ) ) {
        return new WP_Error( 'missing_rooms', __( 'Add at least one room.', 'simple-hotel-crm' ) );
    }

    $validated_lines = [];
    $selected_rooms = [];
    foreach ( $room_lines as $index => $line ) {
        $room_sync_id = absint( $line['room_sync_id'] ?? 0 );
        if ( $room_sync_id <= 0 ) {
            continue;
        }
        if ( in_array( $room_sync_id, $selected_rooms, true ) ) {
            return new WP_Error( 'duplicate_room', __( 'The same room cannot be selected twice in one booking.', 'simple-hotel-crm' ) );
        }
        $selected_rooms[] = $room_sync_id;
        $adults = max( 0, (int) ( $line['adults'] ?? 0 ) );
        $children = max( 0, (int) ( $line['children'] ?? 0 ) );
        $babies = max( 0, (int) ( $line['babies'] ?? 0 ) );
        $guest_count = $adults + $children + $babies;
        $room_rate_amount = max( 0, (float) simple_hotel_crm_normalize_decimal( $line['room_rate_amount'] ?? 0 ) );
        $extras_amount = max( 0, (float) simple_hotel_crm_normalize_decimal( $line['extras_amount'] ?? 0 ) );
        $tourist_tax_total = round( $adults * 0.80 * $nights, 2 );
        $total_amount = round( $room_rate_amount + $extras_amount + $tourist_tax_total, 2 );
        $validated_lines[] = [
            'room_sync_id' => $room_sync_id,
            'adults' => $adults,
            'children' => $children,
            'babies' => $babies,
            'guest_count' => $guest_count,
            'room_rate_amount' => $room_rate_amount,
            'extras_amount' => $extras_amount,
            'tourist_tax_total' => $tourist_tax_total,
            'total_amount' => $total_amount,
        ];
    }
    if ( empty( $validated_lines ) ) {
        return new WP_Error( 'missing_rooms', __( 'Select at least one room.', 'simple-hotel-crm' ) );
    }

    return [
        'guest_name' => $guest_name,
        'check_in' => $check_in,
        'check_out' => $check_out,
        'nights' => $nights,
        'contacted_date' => $contacted_date,
        'room_lines' => $validated_lines,
    ];
}

function simple_hotel_crm_check_wp_sync_room_availability( $room_sync_id, $check_in, $check_out ) {
    global $wpdb;

    $rooms_table = simple_hotel_crm_rooms_table();
    $bookings_table = simple_hotel_crm_bookings_table();
    $booking_rooms_table = simple_hotel_crm_booking_rooms_table();
    $crm_room_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$rooms_table} WHERE sync_room_id = %d LIMIT 1", $room_sync_id ) );

    if ( $crm_room_id <= 0 ) {
        return true;
    }

    $conflict_count = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$booking_rooms_table} br
             JOIN {$bookings_table} b ON b.id = br.booking_id
             WHERE br.room_id = %d
               AND b.status_code IN ('pending', 'confirmed', 'checked_in')
               AND b.check_in_date < %s
               AND b.check_out_date > %s",
            $crm_room_id,
            $check_out,
            $check_in
        )
    );

    if ( $conflict_count > 0 ) {
        return new WP_Error( 'room_unavailable', __( 'That room already has an overlapping booking for the selected dates.', 'simple-hotel-crm' ) );
    }

    return true;
}

function simple_hotel_crm_check_external_pg_room_availability( $room_id, $check_in, $check_out ) {
    $connection = simple_hotel_crm_get_external_pg_connection();
    if ( is_wp_error( $connection ) ) {
        return $connection;
    }

    $result = pg_query_params(
        $connection,
        "SELECT COUNT(*)
         FROM booking_rooms br
         JOIN bookings b ON b.id = br.booking_id
         JOIN booking_statuses bs ON bs.code = b.status_code
         WHERE br.room_id = $1
           AND bs.blocks_availability = true
           AND b.check_in_date < $2::date
           AND b.check_out_date > $3::date",
        [ $room_id, $check_out, $check_in ]
    );
    if ( ! $result ) {
        return new WP_Error( 'pg_availability_failed', __( 'Could not check room availability in the external PostgreSQL database.', 'simple-hotel-crm' ) );
    }

    if ( (int) pg_fetch_result( $result, 0, 0 ) > 0 ) {
        return new WP_Error( 'room_unavailable', __( 'That room already has an overlapping booking for the selected dates.', 'simple-hotel-crm' ) );
    }

    return true;
}

function simple_hotel_crm_create_wp_crm_booking( $data ) {
    global $wpdb;

    simple_hotel_crm_seed_rooms_table();

    $rooms_table = simple_hotel_crm_sync_rooms_table();
    $bookings_table = simple_hotel_crm_sync_bookings_table();
    $crm_rooms_table = simple_hotel_crm_rooms_table();
    $crm_guests_table = simple_hotel_crm_guests_table();
    $crm_bookings_table = simple_hotel_crm_bookings_table();
    $crm_booking_rooms_table = simple_hotel_crm_booking_rooms_table();
    $crm_booking_nights_table = simple_hotel_crm_booking_room_nights_table();

    $validated = simple_hotel_crm_validate_booking_form_data( $data );
    if ( is_wp_error( $validated ) ) {
        return $validated;
    }

    extract( $validated, EXTR_SKIP );

    $source_channel = sanitize_text_field( (string) ( $data['source_channel'] ?? 'direct' ) );
    $status_code = sanitize_text_field( (string) ( $data['status_code'] ?? 'confirmed' ) );
    $phone = sanitize_text_field( (string) ( $data['phone'] ?? '' ) );
    $import_notes = sanitize_textarea_field( (string) ( $data['import_notes'] ?? '' ) );
    $source_created_at = ( '' !== $contacted_date ? $contacted_date : current_time( 'mysql' ) );

    $external_booking_id = (int) $wpdb->get_var( "SELECT COALESCE(MAX(external_booking_id), 0) + 1 FROM {$bookings_table}" );
    $next_booking_room_id = (int) $wpdb->get_var( "SELECT COALESCE(MAX(external_booking_room_id), 0) + 1 FROM {$bookings_table}" );
    $booking_adults = array_sum( array_column( $room_lines, 'adults' ) );
    $booking_children = array_sum( array_column( $room_lines, 'children' ) );
    $booking_babies = array_sum( array_column( $room_lines, 'babies' ) );
    $booking_room_rate = round( array_sum( array_column( $room_lines, 'room_rate_amount' ) ), 2 );
    $booking_extras = round( array_sum( array_column( $room_lines, 'extras_amount' ) ), 2 );
    $booking_tax = round( array_sum( array_column( $room_lines, 'tourist_tax_total' ) ), 2 );
    $booking_total = round( array_sum( array_column( $room_lines, 'total_amount' ) ), 2 );

    foreach ( $room_lines as $line ) {
        $availability = simple_hotel_crm_check_wp_sync_room_availability( $line['room_sync_id'], $check_in, $check_out );
        if ( is_wp_error( $availability ) ) {
            return $availability;
        }
    }

    list( $first_name, $last_name ) = simple_hotel_crm_split_guest_name( $guest_name );

    $wpdb->query( 'START TRANSACTION' );

    $guest_inserted = $wpdb->insert(
        $crm_guests_table,
        [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'phone' => $phone,
        ],
        [ '%s', '%s', '%s' ]
    );
    if ( false === $guest_inserted ) {
        $wpdb->query( 'ROLLBACK' );
        return new WP_Error( 'guest_insert_failed', __( 'Could not create the guest in WordPress CRM tables.', 'simple-hotel-crm' ) );
    }
    $guest_id = (int) $wpdb->insert_id;

    $booking_inserted = $wpdb->insert(
        $crm_bookings_table,
        [
            'guest_id' => $guest_id,
            'source_channel' => $source_channel,
            'source_booking_id' => '',
            'status_code' => $status_code,
            'contacted_date' => $contacted_date ?: null,
            'check_in_date' => $check_in,
            'check_out_date' => $check_out,
            'adults' => $booking_adults,
            'children' => $booking_children,
            'babies' => $booking_babies,
            'room_rate_amount' => $booking_room_rate,
            'extras_amount' => $booking_extras,
            'tourist_tax_amount' => $booking_tax,
            'total_amount' => $booking_total,
            'currency' => 'EUR',
            'internal_notes' => $import_notes,
            'invoice_ninja_client_id' => '',
            'invoice_ninja_invoice_id' => '',
        ],
        [ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%f', '%f', '%f', '%f', '%s', '%s', '%s', '%s' ]
    );
    if ( false === $booking_inserted ) {
        $wpdb->query( 'ROLLBACK' );
        return new WP_Error( 'booking_insert_failed', __( 'Could not create the booking in WordPress CRM tables.', 'simple-hotel-crm' ) );
    }
    $booking_id = (int) $wpdb->insert_id;

    $last_booking_room_id = 0;
    foreach ( $room_lines as $line ) {
        $room = $wpdb->get_row( $wpdb->prepare( "SELECT id, external_room_id, room_code, room_name FROM {$rooms_table} WHERE id = %d LIMIT 1", $line['room_sync_id'] ), ARRAY_A );
        if ( ! $room ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'invalid_room', __( 'Please select a valid room.', 'simple-hotel-crm' ) );
        }

        $crm_room_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$crm_rooms_table} WHERE sync_room_id = %d LIMIT 1", $line['room_sync_id'] ) );
        if ( $crm_room_id <= 0 ) {
            $colors = simple_hotel_crm_get_room_colors();
            $wpdb->insert(
                $crm_rooms_table,
                [
                    'sync_room_id' => (int) $room['id'],
                    'external_room_id' => (int) $room['external_room_id'],
                    'room_code' => (string) $room['room_code'],
                    'room_name' => (string) $room['room_name'],
                    'sort_order' => simple_hotel_crm_get_room_sort_order( $room['room_code'], $room['room_name'] ),
                    'color' => $colors[ strtoupper( (string) $room['room_code'] ) ] ?? '#cccccc',
                    'active' => 1,
                ],
                [ '%d', '%d', '%s', '%s', '%d', '%s', '%d' ]
            );
            $crm_room_id = (int) $wpdb->insert_id;
        }

        $external_booking_room_id = $next_booking_room_id++;
        $booking_room_inserted = $wpdb->insert(
            $crm_booking_rooms_table,
            [
                'booking_id' => $booking_id,
                'room_id' => $crm_room_id,
                'legacy_reserved_room_id' => $external_booking_room_id,
                'guest_count' => $line['guest_count'],
                'adults' => $line['adults'],
                'children' => $line['children'],
                'babies' => $line['babies'],
                'room_rate_amount' => $line['room_rate_amount'],
                'extras_amount' => $line['extras_amount'],
                'tourist_tax_amount' => $line['tourist_tax_total'],
                'total_amount' => $line['total_amount'],
            ],
            [ '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%f', '%f', '%f', '%f' ]
        );
        if ( false === $booking_room_inserted ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'booking_room_insert_failed', __( 'Could not create a booking room in WordPress CRM tables.', 'simple-hotel-crm' ) );
        }

        $crm_booking_room_id = (int) $wpdb->insert_id;
        $last_booking_room_id = $crm_booking_room_id;
        $room_rate_nightly = simple_hotel_crm_distribute_amounts( $line['room_rate_amount'], $nights );
        $extras_nightly = simple_hotel_crm_distribute_amounts( $line['extras_amount'], $nights );
        $tax_nightly = simple_hotel_crm_distribute_amounts( $line['tourist_tax_total'], $nights );

        for ( $i = 0; $i < $nights; $i++ ) {
            $stay_date = gmdate( 'Y-m-d', strtotime( $check_in . ' +' . $i . ' day' ) );
            $night_total = round( $room_rate_nightly[ $i ] + $extras_nightly[ $i ] + $tax_nightly[ $i ], 2 );

            $night_inserted = $wpdb->insert(
                $crm_booking_nights_table,
                [
                    'booking_room_id' => $crm_booking_room_id,
                    'stay_date' => $stay_date,
                    'guest_count' => $line['guest_count'],
                    'adults' => $line['adults'],
                    'children' => $line['children'],
                    'babies' => $line['babies'],
                    'room_rate_amount' => $room_rate_nightly[ $i ],
                    'extras_amount' => $extras_nightly[ $i ],
                    'tourist_tax_amount' => $tax_nightly[ $i ],
                    'total_amount' => $night_total,
                ],
                [ '%d', '%s', '%d', '%d', '%d', '%d', '%f', '%f', '%f', '%f' ]
            );
            if ( false === $night_inserted ) {
                $wpdb->query( 'ROLLBACK' );
                return new WP_Error( 'booking_night_insert_failed', __( 'Could not create booking nights in WordPress CRM tables.', 'simple-hotel-crm' ) );
            }

            $sync_inserted = $wpdb->insert(
                $bookings_table,
                [
                    'external_booking_id' => $external_booking_id,
                    'external_booking_room_id' => $external_booking_room_id,
                    'external_room_id' => (int) $room['external_room_id'],
                    'room_sync_id' => (int) $room['id'],
                    'status_code' => $status_code,
                    'check_in' => $check_in,
                    'check_out' => $check_out,
                    'stay_date' => $stay_date,
                    'guest_count' => $line['guest_count'],
                    'adults' => $line['adults'],
                    'children' => $line['children'],
                    'babies' => $line['babies'],
                    'total_amount' => $night_total,
                    'room_amount' => $room_rate_nightly[ $i ],
                    'extras_amount' => $extras_nightly[ $i ] > 0 ? $extras_nightly[ $i ] : null,
                    'tourist_tax_amount' => $tax_nightly[ $i ],
                    'room_count' => count( $room_lines ),
                    'source_channel' => $source_channel,
                    'source_booking_id' => '',
                    'channel_label' => simple_hotel_crm_get_booking_channel_options()[ $source_channel ] ?? $source_channel,
                    'guest_name' => $guest_name,
                    'phone' => $phone,
                    'import_notes' => $import_notes,
                    'invoice_ninja_client_id' => '',
                    'invoice_ninja_invoice_id' => '',
                    'source_created_at' => $source_created_at,
                ],
                [ '%d','%d','%d','%d','%s','%s','%s','%s','%d','%d','%d','%d','%f','%f','%f','%d','%s','%s','%s','%s','%s','%s','%s','%s','%s' ]
            );
            if ( false === $sync_inserted ) {
                $wpdb->query( 'ROLLBACK' );
                return new WP_Error( 'sync_booking_insert_failed', __( 'Could not create the compatibility sync booking rows.', 'simple-hotel-crm' ) );
            }
        }
    }

    $wpdb->query( 'COMMIT' );
    simple_hotel_crm_clear_calendar_cache();
    return [ 'booking_id' => $booking_id, 'booking_room_id' => $last_booking_room_id ];
}

function simple_hotel_crm_split_guest_name( $guest_name ) {
    $guest_name = trim( (string) $guest_name );
    if ( '' === $guest_name ) {
        return [ '', '' ];
    }
    $parts = preg_split( '/\s+/', $guest_name );
    $last_name = array_pop( $parts );
    $first_name = trim( implode( ' ', $parts ) );
    return [ '' !== $first_name ? $first_name : $last_name, '' !== $first_name ? $last_name : '' ];
}

function simple_hotel_crm_create_external_pg_booking( $data ) {
    $connection = simple_hotel_crm_get_external_pg_connection();
    if ( is_wp_error( $connection ) ) {
        return $connection;
    }

    $validated = simple_hotel_crm_validate_booking_form_data( $data );
    if ( is_wp_error( $validated ) ) {
        return $validated;
    }

    extract( $validated, EXTR_SKIP );
    $source_channel = sanitize_text_field( (string) ( $data['source_channel'] ?? 'direct' ) );
    if ( ! in_array( $source_channel, [ 'direct', 'motopress', 'booking_com' ], true ) ) {
        $source_channel = 'direct';
    }
    $status_code = sanitize_text_field( (string) ( $data['status_code'] ?? 'confirmed' ) );
    $phone = sanitize_text_field( (string) ( $data['phone'] ?? '' ) );
    $import_notes = sanitize_textarea_field( (string) ( $data['import_notes'] ?? '' ) );
    list( $first_name, $last_name ) = simple_hotel_crm_split_guest_name( $guest_name );

    foreach ( $room_lines as $line ) {
        $availability = simple_hotel_crm_check_external_pg_room_availability( $line['room_sync_id'], $check_in, $check_out );
        if ( is_wp_error( $availability ) ) {
            return $availability;
        }
    }

    $booking_adults = array_sum( array_column( $room_lines, 'adults' ) );
    $booking_children = array_sum( array_column( $room_lines, 'children' ) );
    $booking_babies = array_sum( array_column( $room_lines, 'babies' ) );
    $booking_room_rate = array_sum( array_column( $room_lines, 'room_rate_amount' ) );
    $booking_extras = array_sum( array_column( $room_lines, 'extras_amount' ) );
    $booking_tax = array_sum( array_column( $room_lines, 'tourist_tax_total' ) );
    $booking_total = array_sum( array_column( $room_lines, 'total_amount' ) );

    if ( ! pg_query( $connection, 'BEGIN' ) ) {
        return new WP_Error( 'pg_transaction_failed', __( 'Could not start the external PostgreSQL booking transaction.', 'simple-hotel-crm' ) );
    }

    $guest_result = pg_query_params( $connection, 'INSERT INTO guests (first_name, last_name, phone) VALUES ($1, $2, $3) RETURNING id', [ $first_name, $last_name, $phone ?: null ] );
    if ( ! $guest_result ) {
        pg_query( $connection, 'ROLLBACK' );
        return new WP_Error( 'pg_guest_insert_failed', __( 'Could not create the guest in the external PostgreSQL database.', 'simple-hotel-crm' ) );
    }
    $guest_id = (int) pg_fetch_result( $guest_result, 0, 0 );

    $booking_result = pg_query_params(
        $connection,
        'INSERT INTO bookings (guest_id, source_channel, source_booking_id, status_code, contacted_date, check_in_date, check_out_date, adults, children, babies, room_rate_amount, extras_amount, tourist_tax_amount, total_amount, internal_notes) VALUES ($1, $2, NULL, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14) RETURNING id',
        [ $guest_id, $source_channel, $status_code, $contacted_date ?: null, $check_in, $check_out, $booking_adults, $booking_children, $booking_babies, $booking_room_rate, $booking_extras, $booking_tax, $booking_total, $import_notes ?: null ]
    );
    if ( ! $booking_result ) {
        pg_query( $connection, 'ROLLBACK' );
        return new WP_Error( 'pg_booking_insert_failed', __( 'Could not create the booking in the external PostgreSQL database.', 'simple-hotel-crm' ) );
    }
    $booking_id = (int) pg_fetch_result( $booking_result, 0, 0 );

    $last_booking_room_id = 0;
    foreach ( $room_lines as $line ) {
        $room_result = pg_query_params(
            $connection,
            'INSERT INTO booking_rooms (booking_id, room_id, guest_count, adults, children, babies, room_rate_amount, extras_amount, tourist_tax_amount, total_amount) VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10) RETURNING id',
            [ $booking_id, $line['room_sync_id'], $line['guest_count'], $line['adults'], $line['children'], $line['babies'], $line['room_rate_amount'], $line['extras_amount'], $line['tourist_tax_total'], $line['total_amount'] ]
        );
        if ( ! $room_result ) {
            pg_query( $connection, 'ROLLBACK' );
            return new WP_Error( 'pg_booking_room_insert_failed', __( 'Could not create a booking room in the external PostgreSQL database.', 'simple-hotel-crm' ) );
        }

        $booking_room_id = (int) pg_fetch_result( $room_result, 0, 0 );
        $last_booking_room_id = $booking_room_id;
        $room_rate_nightly = simple_hotel_crm_distribute_amounts( $line['room_rate_amount'], $nights );
        $extras_nightly = simple_hotel_crm_distribute_amounts( $line['extras_amount'], $nights );
        $tax_nightly = simple_hotel_crm_distribute_amounts( $line['tourist_tax_total'], $nights );

        for ( $i = 0; $i < $nights; $i++ ) {
            $stay_date = gmdate( 'Y-m-d', strtotime( $check_in . ' +' . $i . ' day' ) );
            $night_total = round( $room_rate_nightly[ $i ] + $extras_nightly[ $i ] + $tax_nightly[ $i ], 2 );
            $night_result = pg_query_params(
                $connection,
                'INSERT INTO booking_room_nights (booking_room_id, stay_date, guest_count, adults, children, babies, room_rate_amount, extras_amount, tourist_tax_amount, total_amount) VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10)',
                [ $booking_room_id, $stay_date, $line['guest_count'], $line['adults'], $line['children'], $line['babies'], $room_rate_nightly[ $i ], $extras_nightly[ $i ], $tax_nightly[ $i ], $night_total ]
            );
            if ( ! $night_result ) {
                pg_query( $connection, 'ROLLBACK' );
                return new WP_Error( 'pg_booking_night_insert_failed', __( 'Could not create booking nights in the external PostgreSQL database.', 'simple-hotel-crm' ) );
            }
        }
    }

    if ( ! pg_query( $connection, 'COMMIT' ) ) {
        pg_query( $connection, 'ROLLBACK' );
        return new WP_Error( 'pg_commit_failed', __( 'Could not commit the external PostgreSQL booking transaction.', 'simple-hotel-crm' ) );
    }

    simple_hotel_crm_clear_calendar_cache();
    return [ 'booking_id' => $booking_id, 'booking_room_id' => $last_booking_room_id ];
}

function simple_hotel_crm_create_booking( $data ) {
    return 'external_pg' === simple_hotel_crm_get_data_source()
        ? simple_hotel_crm_create_external_pg_booking( $data )
        : simple_hotel_crm_create_wp_crm_booking( $data );
}

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
