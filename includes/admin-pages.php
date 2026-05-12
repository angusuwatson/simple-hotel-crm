<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'admin_menu', 'simple_hotel_crm_register_admin_menu' );
add_action( 'admin_notices', 'simple_hotel_crm_render_admin_sync_notice' );
function simple_hotel_crm_register_admin_menu() {
    if ( ! simple_hotel_crm_user_can_access() ) {
        return;
    }

    add_menu_page( __( 'LGF Bookings', 'simple-hotel-crm' ), __( 'LGF Bookings', 'simple-hotel-crm' ), 'manage_options', 'simple-hotel-crm', 'simple_hotel_crm_render_admin_page', 'dashicons-chart-pie', 58 );
    add_submenu_page( 'simple-hotel-crm', __( 'Calendar', 'simple-hotel-crm' ), __( 'Calendar', 'simple-hotel-crm' ), 'manage_options', 'simple-hotel-crm', 'simple_hotel_crm_render_admin_page' );
    add_submenu_page( 'simple-hotel-crm', __( 'Bookings', 'simple-hotel-crm' ), __( 'Bookings', 'simple-hotel-crm' ), 'manage_options', 'simple-hotel-crm-bookings', 'simple_hotel_crm_render_bookings_page' );
    add_submenu_page( 'simple-hotel-crm', __( 'Merge Bookings', 'simple-hotel-crm' ), __( 'Merge Bookings', 'simple-hotel-crm' ), 'manage_options', 'simple-hotel-crm-merge-bookings', 'simple_hotel_crm_render_booking_merge_page' );
    add_submenu_page( null, __( 'Add Booking', 'simple-hotel-crm' ), __( 'Add Booking', 'simple-hotel-crm' ), 'manage_options', 'simple-hotel-crm-add-booking', 'simple_hotel_crm_render_add_booking_page' );
    add_submenu_page( null, __( 'Booking Detail', 'simple-hotel-crm' ), __( 'Booking Detail', 'simple-hotel-crm' ), 'manage_options', 'simple-hotel-crm-booking-detail', 'simple_hotel_crm_render_booking_detail_page' );
    add_submenu_page( 'simple-hotel-crm', __( 'Rooms', 'simple-hotel-crm' ), __( 'Rooms', 'simple-hotel-crm' ), 'manage_options', 'simple-hotel-crm-rooms', 'simple_hotel_crm_render_rooms_page' );
    add_submenu_page( 'simple-hotel-crm', __( 'Guests', 'simple-hotel-crm' ), __( 'Guests', 'simple-hotel-crm' ), 'manage_options', 'simple-hotel-crm-guests', 'simple_hotel_crm_render_guests_page' );
    add_submenu_page( null, __( 'Guest Duplicates', 'simple-hotel-crm' ), __( 'Guest Duplicates', 'simple-hotel-crm' ), 'manage_options', 'simple-hotel-crm-guest-duplicates', 'simple_hotel_crm_render_guest_duplicates_page' );
    add_submenu_page( null, __( 'Booking Transfers', 'simple-hotel-crm' ), __( 'Booking Transfers', 'simple-hotel-crm' ), 'manage_options', 'simple-hotel-crm-booking-transfers', 'simple_hotel_crm_render_booking_transfers_page' );
    add_submenu_page( null, __( 'Booking Merges', 'simple-hotel-crm' ), __( 'Booking Merges', 'simple-hotel-crm' ), 'manage_options', 'simple-hotel-crm-booking-merges', 'simple_hotel_crm_render_booking_merges_page' );
    add_submenu_page( null, __( 'Guest Detail', 'simple-hotel-crm' ), __( 'Guest Detail', 'simple-hotel-crm' ), 'manage_options', 'simple-hotel-crm-guest-detail', 'simple_hotel_crm_render_guest_detail_page' );
    add_submenu_page( 'simple-hotel-crm', __( 'Settings', 'simple-hotel-crm' ), __( 'Settings', 'simple-hotel-crm' ), 'manage_options', 'simple-hotel-crm-settings', 'simple_hotel_crm_render_settings_page' );
    
    add_submenu_page( null, __( 'Import', 'simple-hotel-crm' ), __( 'Import', 'simple-hotel-crm' ), 'manage_options', 'simple-hotel-crm-import', 'simple_hotel_crm_render_import_page' );
}

function simple_hotel_crm_render_admin_sync_notice() {
    if ( ! is_admin() || ! simple_hotel_crm_user_can_access() ) {
        return;
    }

    $page = sanitize_key( $_GET['page'] ?? '' );
    if ( 'simple-hotel-crm' !== $page || ! isset( $_GET['sync_done'] ) ) {
        return;
    }

    $sync_notice = get_transient( 'simple_hotel_crm_admin_sync_notice_' . get_current_user_id() );
    delete_transient( 'simple_hotel_crm_admin_sync_notice_' . get_current_user_id() );

    if ( ! empty( $sync_notice['success'] ) ) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( (string) $sync_notice['success'] ) . '</p></div>';
    }
    if ( ! empty( $sync_notice['errors'] ) && is_array( $sync_notice['errors'] ) ) {
        echo '<div class="notice notice-warning is-dismissible"><ul>';
        foreach ( $sync_notice['errors'] as $error ) {
            echo '<li>' . esc_html( (string) $error ) . '</li>';
        }
        echo '</ul></div>';
    }
}

function simple_hotel_crm_sync_all_motopress_rooms() {
    if ( ! function_exists( 'MPHB' ) || ! method_exists( MPHB(), 'postTypes' ) || ! method_exists( MPHB(), 'getQueuedSynchronizer' ) ) {
        return new WP_Error( 'motopress_unavailable', __( 'MotoPress plugin not available.', 'simple-hotel-crm' ) );
    }

    $room_post_type = MPHB()->postTypes()->room()->getPostType();
    $room_ids = get_posts( [
        'post_type' => $room_post_type,
        'post_status' => 'publish',
        'fields' => 'ids',
        'posts_per_page' => -1,
        'orderby' => 'ID',
        'order' => 'ASC',
    ] );

    $room_ids = array_values( array_filter( array_map( 'absint', $room_ids ) ) );
    if ( empty( $room_ids ) ) {
        return 0;
    }

    MPHB()->getQueuedSynchronizer()->sync( $room_ids );
    return count( $room_ids );
}

function simple_hotel_crm_render_admin_page() {
    if ( ! simple_hotel_crm_user_can_access() ) {
        wp_die( esc_html__( 'You do not have permission to access this page.', 'simple-hotel-crm' ) );
    }

    $month = isset( $_GET['month'] ) ? intval( $_GET['month'] ) : intval( date( 'n' ) );
    $year  = isset( $_GET['year'] ) ? intval( $_GET['year'] ) : intval( date( 'Y' ) );

    if ( isset( $_POST['simple_hotel_crm_calendar_sync_ics'] ) ) {
        check_admin_referer( 'simple_hotel_crm_calendar_sync_ics', 'simple_hotel_crm_calendar_sync_ics_nonce' );
        $ics_import_results = simple_hotel_crm_import_booking_com_ics_feeds();
        $motopress_sync_result = simple_hotel_crm_sync_all_motopress_rooms();
        $motopress_synced_count = is_wp_error( $motopress_sync_result ) ? 0 : (int) $motopress_sync_result;
        $sync_notice = [
            'success' => sprintf(
                __( 'Calendar sync complete. ICS feeds: %1$d, Events: %2$d, Staged nights: %3$d, Skipped: %4$d. MotoPress rooms queued: %5$d.', 'simple-hotel-crm' ),
                (int) ( $ics_import_results['feeds'] ?? 0 ),
                (int) ( $ics_import_results['events'] ?? 0 ),
                (int) ( $ics_import_results['staged'] ?? 0 ),
                (int) ( $ics_import_results['skipped'] ?? 0 ),
                $motopress_synced_count
            ),
            'errors' => array_values( array_filter( array_merge(
                ! empty( $ics_import_results['errors'] ) ? (array) $ics_import_results['errors'] : [],
                is_wp_error( $motopress_sync_result ) ? [ $motopress_sync_result->get_error_message() ] : []
            ) ) ),
        ];
        set_transient( 'simple_hotel_crm_admin_sync_notice_' . get_current_user_id(), $sync_notice, 5 * MINUTE_IN_SECONDS );
        wp_safe_redirect( admin_url( 'admin.php?page=simple-hotel-crm&month=' . $month . '&year=' . $year . '&sync_done=1' ) );
        exit;
    }

    $calendar_data = simple_hotel_crm_get_calendar_data( $month, $year );

    echo '<div class="wrap">';
    if ( isset( $_GET['created_booking'] ) ) {
        echo '<div class="notice notice-success"><p>' . esc_html( sprintf( __( 'Booking %d created.', 'simple-hotel-crm' ), absint( $_GET['created_booking'] ) ) ) . '</p></div>';
    }
    echo simple_hotel_crm_render_calendar( $calendar_data, 'admin' );
    echo '<a class="simple-hotel-crm-floating-add-booking" href="' . esc_url( admin_url( 'admin.php?page=simple-hotel-crm-add-booking' ) ) . '" aria-label="' . esc_attr__( 'Add Booking', 'simple-hotel-crm' ) . '">+</a>';
    echo '<form method="post" class="simple-hotel-crm-floating-sync-form">';
    wp_nonce_field( 'simple_hotel_crm_calendar_sync_ics', 'simple_hotel_crm_calendar_sync_ics_nonce' );
    echo '<button type="submit" name="simple_hotel_crm_calendar_sync_ics" value="1" class="simple-hotel-crm-floating-sync" aria-label="' . esc_attr__( 'Sync calendars', 'simple-hotel-crm' ) . '" title="' . esc_attr__( 'Sync Booking.com ICS and all MotoPress rooms', 'simple-hotel-crm' ) . '"><span class="dashicons dashicons-update"></span></button>';
    echo '</form>';
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
    $view = ( isset( $_GET['view'] ) && in_array( $_GET['view'], [ 'trash', 'archive', 'cancelled' ], true ) ) ? sanitize_key( $_GET['view'] ) : 'active';
    $is_deleted = 'trash' === $view ? 1 : 0;
    $archive_sql = 'active' === $view ? " AND (b.internal_notes IS NULL OR b.internal_notes NOT LIKE '%[MERGED_ARCHIVE]%') " : ( 'archive' === $view ? " AND b.internal_notes LIKE '%[MERGED_ARCHIVE]%' " : '' );
    $status_sql = 'cancelled' === $view ? " AND b.status_code = 'cancelled' " : ( 'active' === $view ? " AND b.status_code <> 'cancelled' " : '' );
    $sortable = [ 'id' => 'b.id', 'guest' => 'guest_name', 'check_in' => 'b.check_in_date', 'check_out' => 'b.check_out_date', 'rooms' => 'room_count', 'status' => 'b.status_code', 'channel' => 'b.source_channel', 'commission' => 'commission_amount', 'total' => 'b.total_amount' ];
    $search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
    $orderby_key = isset( $_GET['orderby'] ) && isset( $sortable[ $_GET['orderby'] ] ) ? $_GET['orderby'] : 'check_in';
    $order = isset( $_GET['order'] ) && 'asc' === strtolower( (string) $_GET['order'] ) ? 'ASC' : 'DESC';
    $order_sql = $sortable[ $orderby_key ];
    $allowed_per_page = [ 20, 50, 100 ];
    $user_per_page = (int) get_user_meta( get_current_user_id(), 'simple_hotel_crm_bookings_per_page', true );
    $per_page = isset( $_GET['per_page'] ) ? absint( $_GET['per_page'] ) : ( in_array( $user_per_page, $allowed_per_page, true ) ? $user_per_page : 20 );
    if ( ! in_array( $per_page, $allowed_per_page, true ) ) {
        $per_page = 20;
    }
    $paged = max( 1, absint( $_GET['paged'] ?? 1 ) );
    $offset = ( $paged - 1 ) * $per_page;
    $search_sql = '';
    $search_params = [];
    if ( '' !== $search ) {
        $search_sql = " AND (b.id LIKE %s OR b.source_booking_id LIKE %s OR g.first_name LIKE %s OR g.last_name LIKE %s OR CONCAT(g.first_name, ' ', g.last_name) LIKE %s OR b.source_channel LIKE %s OR b.status_code LIKE %s) ";
        $like = '%' . $wpdb->esc_like( $search ) . '%';
        $search_params = [ $like, $like, $like, $like, $like, $like, $like ];
    }

    if ( isset( $_POST['simple_hotel_crm_bulk_apply'] ) && ! empty( $_POST['booking_ids'] ) && is_array( $_POST['booking_ids'] ) ) {
        check_admin_referer( 'simple_hotel_crm_bulk_bookings' );
        $booking_ids = array_map( 'absint', (array) $_POST['booking_ids'] );
        $action = sanitize_text_field( wp_unslash( $_POST['bulk_action'] ?? '' ) );
        if ( 'delete' === $action ) {
            if ( 'trash' === $view ) {
                $deleted_count = 0;
                foreach ( $booking_ids as $booking_id ) {
                    if ( simple_hotel_crm_delete_booking( $booking_id ) ) {
                        $deleted_count++;
                    }
                }
                echo '<div class="notice notice-success"><p>' . esc_html( sprintf( __( '%d booking(s) permanently deleted.', 'simple-hotel-crm' ), $deleted_count ) ) . '</p></div>';
            } else {
                $wpdb->query( "UPDATE {$bookings_table} SET is_deleted = 1, deleted_at = NOW() WHERE id IN (" . implode( ',', $booking_ids ) . ")" );
                echo '<div class="notice notice-success"><p>' . esc_html__( 'Selected bookings moved to trash.', 'simple-hotel-crm' ) . '</p></div>';
            }
        } elseif ( 'restore' === $action ) {
            if ( 'archive' === $view ) {
                foreach ( $booking_ids as $booking_id ) {
                    $note = (string) $wpdb->get_var( $wpdb->prepare( "SELECT internal_notes FROM {$bookings_table} WHERE id = %d", $booking_id ) );
                    $note = preg_replace( '/\s*\[MERGED_ARCHIVE\]\s*/', ' ', $note );
                    $note = preg_replace( '/\s*\[MERGED_INTO_BOOKING:[^\]]*\]\s*/', ' ', $note );
                    $wpdb->update( $bookings_table, [ 'internal_notes' => trim( $note ) ], [ 'id' => $booking_id ], [ '%s' ], [ '%d' ] );
                }
                echo '<div class="notice notice-success"><p>' . esc_html__( 'Selected archive bookings restored.', 'simple-hotel-crm' ) . '</p></div>';
            } else {
                $wpdb->query( "UPDATE {$bookings_table} SET is_deleted = 0, deleted_at = NULL WHERE id IN (" . implode( ',', $booking_ids ) . ")" );
                echo '<div class="notice notice-success"><p>' . esc_html__( 'Selected bookings restored.', 'simple-hotel-crm' ) . '</p></div>';
            }
        } elseif ( 'cancel' === $action ) {
            $wpdb->query( "UPDATE {$bookings_table} SET status_code = 'cancelled' WHERE id IN (" . implode( ',', $booking_ids ) . ")" );
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Selected bookings marked cancelled.', 'simple-hotel-crm' ) . '</p></div>';
        } elseif ( 'confirm' === $action ) {
            $wpdb->query( "UPDATE {$bookings_table} SET status_code = 'confirmed' WHERE id IN (" . implode( ',', $booking_ids ) . ")" );
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Selected bookings marked confirmed.', 'simple-hotel-crm' ) . '</p></div>';
        }
        simple_hotel_crm_clear_calendar_cache();
    }

    if ( isset( $_GET['restore_booking'] ) ) {
        check_admin_referer( 'simple_hotel_crm_restore_booking_' . absint( $_GET['restore_booking'] ) );
        if ( 'archive' === $view ) {
            $booking_id = absint( $_GET['restore_booking'] );
            $note = (string) $wpdb->get_var( $wpdb->prepare( "SELECT internal_notes FROM {$bookings_table} WHERE id = %d", $booking_id ) );
            $note = preg_replace( '/\s*\[MERGED_ARCHIVE\]\s*/', ' ', $note );
            $note = preg_replace( '/\s*\[MERGED_INTO_BOOKING:[^\]]*\]\s*/', ' ', $note );
            $restored = false !== $wpdb->update( $bookings_table, [ 'internal_notes' => trim( $note ) ], [ 'id' => $booking_id ], [ '%s' ], [ '%d' ] );
            echo '<div class="notice ' . esc_attr( $restored ? 'notice-success' : 'notice-error' ) . '"><p>' . esc_html( $restored ? __( 'Archive restored.', 'simple-hotel-crm' ) : __( 'Archive could not be restored.', 'simple-hotel-crm' ) ) . '</p></div>';
        } else {
            $restored = false !== $wpdb->update( $bookings_table, [ 'is_deleted' => 0, 'deleted_at' => null ], [ 'id' => absint( $_GET['restore_booking'] ) ], [ '%d', '%s' ], [ '%d' ] );
            echo '<div class="notice ' . esc_attr( $restored ? 'notice-success' : 'notice-error' ) . '"><p>' . esc_html( $restored ? __( 'Booking restored.', 'simple-hotel-crm' ) : __( 'Booking could not be restored.', 'simple-hotel-crm' ) ) . '</p></div>';
        }
        simple_hotel_crm_clear_calendar_cache();
    }

    if ( isset( $_GET['delete_booking'] ) ) {
        check_admin_referer( 'simple_hotel_crm_delete_booking_' . absint( $_GET['delete_booking'] ) );
        if ( 'trash' === $view ) {
            $deleted = simple_hotel_crm_delete_booking( absint( $_GET['delete_booking'] ) );
            echo '<div class="notice ' . esc_attr( $deleted ? 'notice-success' : 'notice-error' ) . '"><p>' . esc_html( $deleted ? __( 'Booking permanently deleted.', 'simple-hotel-crm' ) : __( 'Booking could not be deleted.', 'simple-hotel-crm' ) ) . '</p></div>';
        } else {
            $deleted = false !== $wpdb->update( $bookings_table, [ 'is_deleted' => 1, 'deleted_at' => current_time( 'mysql' ) ], [ 'id' => absint( $_GET['delete_booking'] ) ], [ '%d', '%s' ], [ '%d' ] );
            echo '<div class="notice ' . esc_attr( $deleted ? 'notice-success' : 'notice-error' ) . '"><p>' . esc_html( $deleted ? __( 'Booking moved to trash.', 'simple-hotel-crm' ) : __( 'Booking could not be deleted.', 'simple-hotel-crm' ) ) . '</p></div>';
            simple_hotel_crm_clear_calendar_cache();
        }
    }

    $has_commission_amount = simple_hotel_crm_table_has_column( $booking_rooms_table, 'commission_amount' );
    $commission_select = $has_commission_amount ? 'COALESCE(SUM(br.commission_amount), 0)' : '0';

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT b.id, b.guest_id, b.status_code, b.check_in_date, b.check_out_date, b.source_channel, b.total_amount, b.created_at,
                    CONCAT(g.first_name, ' ', g.last_name) AS guest_name,
                    COUNT(br.id) AS room_count,
                    {$commission_select} AS commission_amount,
                    COALESCE(SUM(br.total_amount), 0) AS room_total_amount,
                    CASE WHEN COALESCE(b.total_amount, 0) > 0 THEN b.total_amount ELSE COALESCE(SUM(br.total_amount), 0) END AS display_total_amount
             FROM {$bookings_table} b
             JOIN {$guests_table} g ON g.id = b.guest_id
             LEFT JOIN {$booking_rooms_table} br ON br.booking_id = b.id
             WHERE b.is_deleted = %d {$archive_sql} {$status_sql} {$search_sql}
             GROUP BY b.id, b.guest_id, b.status_code, b.check_in_date, b.check_out_date, b.source_channel, b.total_amount, b.created_at, guest_name
             ORDER BY {$order_sql} {$order}
             LIMIT %d OFFSET %d",
            array_merge( [ $is_deleted ], $search_params, [ $per_page, $offset ] )
        ),
        ARRAY_A
    );
    $total_bookings = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$bookings_table} b JOIN {$guests_table} g ON g.id = b.guest_id WHERE b.is_deleted = %d {$archive_sql} {$status_sql} {$search_sql}",
            array_merge( [ $is_deleted ], $search_params )
        )
    );
    $totals_row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT COUNT(*) AS booking_count,
                    COALESCE(SUM(CASE WHEN COALESCE(b.total_amount, 0) > 0 THEN b.total_amount ELSE room_totals.room_total_amount END), 0) AS total_amount
             FROM {$bookings_table} b
             JOIN {$guests_table} g ON g.id = b.guest_id
             LEFT JOIN (
                SELECT booking_id, COALESCE(SUM(total_amount), 0) AS room_total_amount
                FROM {$booking_rooms_table}
                GROUP BY booking_id
             ) room_totals ON room_totals.booking_id = b.id
             WHERE b.is_deleted = %d {$archive_sql} {$status_sql} {$search_sql}",
            array_merge( [ $is_deleted ], $search_params )
        ),
        ARRAY_A
    );
    $totals_row['commission_amount'] = $has_commission_amount ? (float) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COALESCE(SUM(br.commission_amount), 0)
             FROM {$booking_rooms_table} br
             JOIN {$bookings_table} b ON b.id = br.booking_id
             JOIN {$guests_table} g ON g.id = b.guest_id
             WHERE b.is_deleted = %d {$archive_sql} {$status_sql} {$search_sql}",
            array_merge( [ $is_deleted ], $search_params )
        )
    ) : 0.0;
    $page_count = (int) ceil( $total_bookings / $per_page );

    $active_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$bookings_table} WHERE is_deleted = 0 AND status_code <> 'cancelled' AND (internal_notes IS NULL OR internal_notes NOT LIKE '%[MERGED_ARCHIVE]%')" );
    $cancelled_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$bookings_table} WHERE is_deleted = 0 AND status_code = 'cancelled' AND (internal_notes IS NULL OR internal_notes NOT LIKE '%[MERGED_ARCHIVE]%')" );
    $archive_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$bookings_table} WHERE is_deleted = 0 AND internal_notes LIKE '%[MERGED_ARCHIVE]%'" );
    $trash_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$bookings_table} WHERE is_deleted = 1" );
    echo '<div class="wrap">';
    echo '<h1>' . esc_html__( 'Bookings', 'simple-hotel-crm' ) . ' <a class="page-title-action" href="' . esc_url( admin_url( 'admin.php?page=simple-hotel-crm-add-booking' ) ) . '">' . esc_html__( 'Add Booking', 'simple-hotel-crm' ) . '</a></h1>';
    if ( isset( $_GET['per_page'] ) ) { update_user_meta( get_current_user_id(), 'simple_hotel_crm_bookings_per_page', $per_page ); }
    if ( isset( $_GET['per_page'] ) ) { update_user_meta( get_current_user_id(), 'simple_hotel_crm_guests_per_page', $per_page ); }
    echo '<form method="get" style="margin:12px 0;">';
    echo '<input type="hidden" name="page" value="simple-hotel-crm-bookings" />';
    echo '<input type="hidden" name="view" value="' . esc_attr( $view ) . '" />';
    echo '<input type="text" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Search bookings', 'simple-hotel-crm' ) . '" /> ';
    echo '<select name="per_page">';
    foreach ( [ 20, 50, 100 ] as $pp ) { echo '<option value="' . esc_attr( (string) $pp ) . '" ' . selected( $per_page, $pp, false ) . '>' . esc_html( (string) $pp ) . '</option>'; }
    echo '</select> ';
    echo '<button type="submit" class="button">' . esc_html__( 'Search', 'simple-hotel-crm' ) . '</button>';
    echo '</form>';
    echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=simple-hotel-crm-bookings&view=active' ) ) . '">' . esc_html__( 'Confirmed / active', 'simple-hotel-crm' ) . ' (' . esc_html( (string) $active_count ) . ')</a> | <a href="' . esc_url( admin_url( 'admin.php?page=simple-hotel-crm-bookings&view=cancelled' ) ) . '">' . esc_html__( 'Cancelled', 'simple-hotel-crm' ) . ' (' . esc_html( (string) $cancelled_count ) . ')</a> | <a href="' . esc_url( admin_url( 'admin.php?page=simple-hotel-crm-bookings&view=archive' ) ) . '">' . esc_html__( 'Merged archive', 'simple-hotel-crm' ) . ' (' . esc_html( (string) $archive_count ) . ')</a> | <a href="' . esc_url( admin_url( 'admin.php?page=simple-hotel-crm-bookings&view=trash' ) ) . '">' . esc_html__( 'Trash', 'simple-hotel-crm' ) . ' (' . esc_html( (string) $trash_count ) . ')</a></p>';
    echo '<form method="post">';
    wp_nonce_field( 'simple_hotel_crm_bulk_bookings' );
    echo '<p><select name="bulk_action"><option value="">' . esc_html__( 'Bulk actions', 'simple-hotel-crm' ) . '</option><option value="delete">' . esc_html__( 'Delete', 'simple-hotel-crm' ) . '</option><option value="restore">' . esc_html__( 'Restore', 'simple-hotel-crm' ) . '</option><option value="cancel">' . esc_html__( 'Mark cancelled', 'simple-hotel-crm' ) . '</option><option value="confirm">' . esc_html__( 'Mark confirmed', 'simple-hotel-crm' ) . '</option></select> ';
    submit_button( __( 'Apply', 'simple-hotel-crm' ), 'secondary', 'simple_hotel_crm_bulk_apply', false );
    if ( 'trash' === $view ) {
        echo ' <span class="description">' . esc_html__( 'Delete will permanently remove trashed bookings.', 'simple-hotel-crm' ) . '</span>';
    }
    echo '</p>';
    $header_link = function( $key, $label ) use ( $orderby_key, $order, $view ) {
        $next_order = ( $orderby_key === $key && 'ASC' === $order ) ? 'desc' : 'asc';
        $url = admin_url( 'admin.php?page=simple-hotel-crm-bookings&view=' . $view . '&orderby=' . $key . '&order=' . $next_order );
        return '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
    };
    echo '<table class="widefat striped"><thead><tr>';
    echo '<th><input type="checkbox" onclick="jQuery(\'.booking-bulk-cb\').prop(\'checked\', this.checked)" /></th>';
    echo '<th>' . $header_link( 'id', __( 'ID', 'simple-hotel-crm' ) ) . '</th>';
    echo '<th>' . $header_link( 'guest', __( 'Guest', 'simple-hotel-crm' ) ) . '</th>';
    echo '<th>' . $header_link( 'check_in', __( 'Check-in', 'simple-hotel-crm' ) ) . '</th>';
    echo '<th>' . $header_link( 'check_out', __( 'Check-out', 'simple-hotel-crm' ) ) . '</th>';
    echo '<th>' . $header_link( 'rooms', __( 'Rooms', 'simple-hotel-crm' ) ) . '</th>';
    echo '<th>' . $header_link( 'status', __( 'Status', 'simple-hotel-crm' ) ) . '</th>';
    echo '<th>' . $header_link( 'channel', __( 'Channel', 'simple-hotel-crm' ) ) . '</th>';
    echo '<th>' . $header_link( 'commission', __( 'Commission', 'simple-hotel-crm' ) ) . '</th>';
    echo '<th>' . $header_link( 'total', __( 'Total', 'simple-hotel-crm' ) ) . '</th>';
    echo '<th>' . esc_html__( 'Actions', 'simple-hotel-crm' ) . '</th>';
    echo '</tr></thead><tbody>';
    if ( empty( $rows ) ) {
        echo '<tr><td colspan="11">' . esc_html__( 'No bookings found.', 'simple-hotel-crm' ) . '</td></tr>';
    } else {
        foreach ( $rows as $row ) {
            $detail_url = admin_url( 'admin.php?page=simple-hotel-crm-booking-detail&booking_id=' . absint( $row['id'] ) );
            $delete_url = wp_nonce_url( admin_url( 'admin.php?page=simple-hotel-crm-bookings&delete_booking=' . absint( $row['id'] ) . '&view=' . $view ), 'simple_hotel_crm_delete_booking_' . absint( $row['id'] ) );
            $restore_url = wp_nonce_url( admin_url( 'admin.php?page=simple-hotel-crm-bookings&restore_booking=' . absint( $row['id'] ) . '&view=' . $view ), 'simple_hotel_crm_restore_booking_' . absint( $row['id'] ) );
            $archive_restore_url = wp_nonce_url( admin_url( 'admin.php?page=simple-hotel-crm-bookings&restore_booking=' . absint( $row['id'] ) . '&view=archive' ), 'simple_hotel_crm_restore_booking_' . absint( $row['id'] ) );
            echo '<tr>';
            echo '<td><input class="booking-bulk-cb" type="checkbox" name="booking_ids[]" value="' . esc_attr( (string) $row['id'] ) . '" /></td>';
            echo '<td><a href="' . esc_url( $detail_url ) . '">' . esc_html( (string) $row['id'] ) . '</a></td>';
            echo '<td><a href="' . esc_url( admin_url( 'admin.php?page=simple-hotel-crm-guest-detail&guest_id=' . absint( $row['guest_id'] ) ) ) . '">' . esc_html( trim( (string) $row['guest_name'] ) ) . '</a></td>';
            echo '<td>' . esc_html( (string) $row['check_in_date'] ) . '</td>';
            echo '<td>' . esc_html( (string) $row['check_out_date'] ) . '</td>';
            echo '<td>' . esc_html( (string) $row['room_count'] ) . '</td>';
            echo '<td>' . esc_html( (string) $row['status_code'] ) . '</td>';
            echo '<td>' . esc_html( (string) $row['source_channel'] ) . '</td>';
            echo '<td>' . ( (float) $row['commission_amount'] > 0 ? esc_html( number_format( (float) $row['commission_amount'], 2, '.', '' ) ) : '' ) . '</td>';
            echo '<td>' . esc_html( number_format( (float) $row['display_total_amount'], 2, '.', '' ) ) . '</td>';
            echo '<td><a class="button button-small" href="' . esc_url( $detail_url ) . '">' . esc_html__( 'View / Edit', 'simple-hotel-crm' ) . '</a> ' . ( 'trash' === $view ? '<a class="button button-small" href="' . esc_url( $restore_url ) . '">' . esc_html__( 'Restore', 'simple-hotel-crm' ) . '</a> <a class="button button-small button-link-delete" href="' . esc_url( $delete_url ) . '" onclick="return confirm(' . wp_json_encode( __( 'Permanently delete this booking?', 'simple-hotel-crm' ) ) . ');">' . esc_html__( 'Delete Permanently', 'simple-hotel-crm' ) . '</a>' : ( 'archive' === $view ? '<a class="button button-small" href="' . esc_url( $archive_restore_url ) . '">' . esc_html__( 'Restore from archive', 'simple-hotel-crm' ) . '</a> <a class="button button-small button-link-delete" href="' . esc_url( $delete_url ) . '" onclick="return confirm(' . wp_json_encode( __( 'Permanently delete this archived booking?', 'simple-hotel-crm' ) ) . ');">' . esc_html__( 'Delete Permanently', 'simple-hotel-crm' ) . '</a>' : '<a class="button button-small" href="' . esc_url( $delete_url ) . '" onclick="return confirm(' . wp_json_encode( __( 'Delete this booking?', 'simple-hotel-crm' ) ) . ');">' . esc_html__( 'Delete', 'simple-hotel-crm' ) . '</a>' ) ) . '</td>';
            echo '</tr>';
        }
    }
    echo '</tbody><tfoot><tr>';
    echo '<td></td>';
    echo '<td colspan="4"><strong>' . esc_html__( 'Totals', 'simple-hotel-crm' ) . '</strong></td>';
    echo '<td><strong>' . esc_html( (string) (int) ( $totals_row['booking_count'] ?? 0 ) ) . '</strong></td>';
    echo '<td></td>';
    echo '<td><strong>' . ( (float) ( $totals_row['commission_amount'] ?? 0 ) > 0 ? esc_html( number_format( (float) ( $totals_row['commission_amount'] ?? 0 ), 2, '.', '' ) ) : '' ) . '</strong></td>';
    echo '<td><strong>' . esc_html( number_format( (float) ( $totals_row['total_amount'] ?? 0 ), 2, '.', '' ) ) . '</strong></td>';
    echo '<td></td>';
    echo '</tr></tfoot></table></form>';
    if ( $page_count > 1 ) {
        $base_args = [ 'page' => 'simple-hotel-crm-bookings', 'view' => $view, 's' => $search, 'orderby' => $orderby_key, 'order' => strtolower( $order ), 'per_page' => $per_page ];
        echo '<div class="tablenav"><div class="tablenav-pages"><span class="pagination-links">';
        echo '<a class="first-page button" href="' . esc_url( add_query_arg( array_merge( $base_args, [ 'paged' => 1 ] ), admin_url( 'admin.php' ) ) ) . '">&laquo;</a> ';
        if ( $paged > 1 ) { echo '<a class="prev-page button" href="' . esc_url( add_query_arg( array_merge( $base_args, [ 'paged' => $paged - 1 ] ), admin_url( 'admin.php' ) ) ) . '">&lsaquo;</a> '; } else { echo '<span class="tablenav-pages-navspan button disabled">&lsaquo;</span> '; }
        $start = max( 1, $paged - 2 );
        $end = min( $page_count, $paged + 2 );
        if ( $start > 1 ) { echo '<span class="page-numbers dots">&hellip;</span> '; }
        for ( $i = $start; $i <= $end; $i++ ) { $url = add_query_arg( array_merge( $base_args, [ 'paged' => $i ] ), admin_url( 'admin.php' ) ); echo '<a class="page-numbers' . ( $i === $paged ? ' current' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( (string) $i ) . '</a> '; }
        if ( $end < $page_count ) { echo '<span class="page-numbers dots">&hellip;</span> '; }
        if ( $paged < $page_count ) { echo '<a class="next-page button" href="' . esc_url( add_query_arg( array_merge( $base_args, [ 'paged' => $paged + 1 ] ), admin_url( 'admin.php' ) ) ) . '">&rsaquo;</a> '; } else { echo '<span class="tablenav-pages-navspan button disabled">&rsaquo;</span> '; }
        echo '<a class="last-page button" href="' . esc_url( add_query_arg( array_merge( $base_args, [ 'paged' => $page_count ] ), admin_url( 'admin.php' ) ) ) . '">&raquo;</a>';
        echo '</span></div></div>';
    }
    echo '</div>';
}

function simple_hotel_crm_render_rooms_page() {
    if ( ! simple_hotel_crm_user_can_access() ) {
        wp_die( esc_html__( 'You do not have permission to access this page.', 'simple-hotel-crm' ) );
    }

    global $wpdb;
    $rooms_table = simple_hotel_crm_rooms_table();
    $room_pricing_table = simple_hotel_crm_room_pricing_table();
    $room_id = absint( $_GET['room_id'] ?? 0 );
    $editing = $room_id > 0;

    if ( isset( $_GET['toggle_room'] ) ) {
        check_admin_referer( 'simple_hotel_crm_toggle_room_' . absint( $_GET['toggle_room'] ) );
        $current = (int) $wpdb->get_var( $wpdb->prepare( "SELECT active FROM {$rooms_table} WHERE id = %d", absint( $_GET['toggle_room'] ) ) );
        $wpdb->update( $rooms_table, [ 'active' => $current ? 0 : 1 ], [ 'id' => absint( $_GET['toggle_room'] ) ], [ '%d' ], [ '%d' ] );
        echo '<div class="notice notice-success"><p>' . esc_html__( 'Room updated.', 'simple-hotel-crm' ) . '</p></div>';
        simple_hotel_crm_clear_calendar_cache();
    }

    if ( isset( $_POST['simple_hotel_crm_save_room'] ) ) {
        check_admin_referer( 'simple_hotel_crm_save_room', 'simple_hotel_crm_save_room_nonce' );
        $data = [
            'room_code' => strtoupper( sanitize_text_field( wp_unslash( $_POST['room_code'] ?? '' ) ) ),
            'room_name' => sanitize_text_field( wp_unslash( $_POST['room_name'] ?? '' ) ),
            'sort_order' => (int) ( $_POST['sort_order'] ?? 0 ),
            'color' => simple_hotel_crm_normalize_color_value( wp_unslash( $_POST['color'] ?? ( $_POST['color_hex'] ?? ( $_POST['color_rgb'] ?? '' ) ) ) ) ?: '#cccccc',
            'active' => empty( $_POST['active'] ) ? 0 : 1,
        ];
        if ( '' === $data['room_code'] || '' === $data['room_name'] ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Room code and room name are required.', 'simple-hotel-crm' ) . '</p></div>';
        } else {
            if ( $editing ) {
                $wpdb->update( $rooms_table, $data, [ 'id' => $room_id ], [ '%s', '%s', '%d', '%s', '%d' ], [ '%d' ] );
            } else {
                $wpdb->insert( $rooms_table, $data, [ '%s', '%s', '%d', '%s', '%d' ] );
                $room_id = (int) $wpdb->insert_id;
                $editing = $room_id > 0;
            }

            if ( $room_id > 0 ) {
                $submitted_pricing = isset( $_POST['occupancy_pricing'] ) && is_array( $_POST['occupancy_pricing'] ) ? wp_unslash( $_POST['occupancy_pricing'] ) : [];
                foreach ( range( 1, 4 ) as $occupancy_adults ) {
                    $raw_price = $submitted_pricing[ $occupancy_adults ]['price_amount'] ?? '';
                    $price_amount = (float) simple_hotel_crm_normalize_decimal( $raw_price );
                    $is_active = empty( $submitted_pricing[ $occupancy_adults ]['active'] ) ? 0 : 1;
                    if ( $price_amount <= 0 && ! $is_active ) {
                        $wpdb->delete( $room_pricing_table, [ 'room_id' => $room_id, 'occupancy_adults' => $occupancy_adults ], [ '%d', '%d' ] );
                        continue;
                    }
                    $wpdb->replace(
                        $room_pricing_table,
                        [
                            'room_id' => $room_id,
                            'occupancy_adults' => $occupancy_adults,
                            'price_amount' => max( 0, $price_amount ),
                            'active' => $is_active,
                        ],
                        [ '%d', '%d', '%f', '%d' ]
                    );
                }
            }

            echo '<div class="notice notice-success"><p>' . esc_html__( 'Room saved.', 'simple-hotel-crm' ) . '</p></div>';
            simple_hotel_crm_clear_calendar_cache();
        }
    }

    $room = $editing ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$rooms_table} WHERE id = %d", $room_id ), ARRAY_A ) : null;
    $room = array_merge(
        [ 'room_code' => '', 'room_name' => '', 'sort_order' => 0, 'color' => '#cccccc', 'active' => 1 ],
        is_array( $room ) ? $room : []
    );
    $room_color_rgb = simple_hotel_crm_hex_to_rgb_string( (string) $room['color'] );
    $room_pricing_rows = $room_id > 0 ? $wpdb->get_results( $wpdb->prepare( "SELECT occupancy_adults, price_amount, active FROM {$room_pricing_table} WHERE room_id = %d ORDER BY occupancy_adults ASC", $room_id ), ARRAY_A ) : [];
    $room_pricing = [];
    foreach ( range( 1, 4 ) as $occupancy_adults ) {
        $room_pricing[ $occupancy_adults ] = [ 'price_amount' => '', 'active' => 0 ];
    }
    foreach ( $room_pricing_rows as $pricing_row ) {
        $room_pricing[ (int) $pricing_row['occupancy_adults'] ] = [
            'price_amount' => number_format( (float) $pricing_row['price_amount'], 2, '.', '' ),
            'active' => (int) $pricing_row['active'],
        ];
    }
    $rooms = $wpdb->get_results( "SELECT * FROM {$rooms_table} ORDER BY sort_order ASC, room_name ASC", ARRAY_A );

    echo '<div class="wrap"><h1>' . esc_html__( 'Rooms', 'simple-hotel-crm' ) . '</h1>';
    echo '<div style="display:grid;grid-template-columns:minmax(320px,420px) 1fr;gap:24px;align-items:start;">';
    echo '<div><h2>' . esc_html( $editing ? __( 'Edit Room', 'simple-hotel-crm' ) : __( 'Add Room', 'simple-hotel-crm' ) ) . '</h2><form method="post">';
    wp_nonce_field( 'simple_hotel_crm_save_room', 'simple_hotel_crm_save_room_nonce' );
    echo '<table class="form-table">';
    echo '<tr><th><label for="room_code">' . esc_html__( 'Room code', 'simple-hotel-crm' ) . '</label></th><td><input id="room_code" name="room_code" type="text" class="regular-text" value="' . esc_attr( (string) $room['room_code'] ) . '" /></td></tr>';
    echo '<tr><th><label for="room_name">' . esc_html__( 'Room name', 'simple-hotel-crm' ) . '</label></th><td><input id="room_name" name="room_name" type="text" class="regular-text" value="' . esc_attr( (string) $room['room_name'] ) . '" /></td></tr>';
    echo '<tr><th><label for="sort_order">' . esc_html__( 'Sort order', 'simple-hotel-crm' ) . '</label></th><td><input id="sort_order" name="sort_order" type="number" value="' . esc_attr( (string) $room['sort_order'] ) . '" /></td></tr>';
    echo '<tr><th><label for="color">' . esc_html__( 'Color', 'simple-hotel-crm' ) . '</label></th><td><input id="color" name="color" type="color" value="' . esc_attr( (string) $room['color'] ) . '" /> <input id="color_hex" name="color_hex" type="text" class="regular-text" value="' . esc_attr( (string) $room['color'] ) . '" placeholder="#cccccc" style="max-width:110px;" /> <input id="color_rgb" name="color_rgb" type="text" class="regular-text" value="' . esc_attr( $room_color_rgb ) . '" placeholder="rgb(204, 204, 204)" style="max-width:170px;" /> <code>' . esc_html( (string) $room['color'] ) . '</code></td></tr>';
    echo '<tr><th>' . esc_html__( 'Active', 'simple-hotel-crm' ) . '</th><td><label><input type="checkbox" name="active" value="1" ' . checked( (int) $room['active'], 1, false ) . ' /> ' . esc_html__( 'Show this room in the calendar', 'simple-hotel-crm' ) . '</label></td></tr>';
    echo '</table>';
    echo '<h3>' . esc_html__( 'Occupancy pricing', 'simple-hotel-crm' ) . '</h3>';
    echo '<table class="widefat striped" style="max-width:420px;"><thead><tr><th>' . esc_html__( 'Adults', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Price', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Active', 'simple-hotel-crm' ) . '</th></tr></thead><tbody>';
    foreach ( range( 1, 4 ) as $occupancy_adults ) {
        echo '<tr>';
        echo '<td>' . esc_html( (string) $occupancy_adults ) . '</td>';
        echo '<td><input type="text" name="occupancy_pricing[' . esc_attr( (string) $occupancy_adults ) . '][price_amount]" value="' . esc_attr( (string) $room_pricing[ $occupancy_adults ]['price_amount'] ) . '" placeholder="0.00" /></td>';
        echo '<td><input type="checkbox" name="occupancy_pricing[' . esc_attr( (string) $occupancy_adults ) . '][active]" value="1" ' . checked( (int) $room_pricing[ $occupancy_adults ]['active'], 1, false ) . ' /></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    echo '<script>(function(){var picker=document.getElementById("color"),hex=document.getElementById("color_hex"),rgb=document.getElementById("color_rgb");function hexToRgb(v){v=(v||"").replace("#","");if(v.length!==6)return"";return "rgb("+parseInt(v.slice(0,2),16)+", "+parseInt(v.slice(2,4),16)+", "+parseInt(v.slice(4,6),16)+")";}function rgbToHex(v){var m=(v||"").match(/^rgb\(\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*\)$/i);if(!m)return"";var p=function(n){n=Math.max(0,Math.min(255,parseInt(n,10)||0));return n.toString(16).padStart(2,"0");};return "#"+p(m[1])+p(m[2])+p(m[3]);}if(picker&&hex&&rgb){picker.addEventListener("input",function(){hex.value=picker.value.toLowerCase();rgb.value=hexToRgb(picker.value);});hex.addEventListener("input",function(){if(/^#?[0-9a-f]{6}$/i.test(hex.value)){var v=hex.value.startsWith("#")?hex.value:"#"+hex.value;picker.value=v.toLowerCase();rgb.value=hexToRgb(v);}});rgb.addEventListener("input",function(){var v=rgbToHex(rgb.value);if(v){picker.value=v.toLowerCase();hex.value=v.toLowerCase();}});}}</script>';
    submit_button( __( 'Save Room', 'simple-hotel-crm' ), 'primary', 'simple_hotel_crm_save_room' );
    if ( $editing ) {
        echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=simple-hotel-crm-rooms' ) ) . '">' . esc_html__( 'Add New Room', 'simple-hotel-crm' ) . '</a>';
    }
    echo '</form></div>';

    echo '<div><h2>' . esc_html__( 'Current Rooms', 'simple-hotel-crm' ) . '</h2><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Code', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Name', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Order', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Color', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( '1A', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( '2A', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( '3A', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( '4A', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Status', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Actions', 'simple-hotel-crm' ) . '</th></tr></thead><tbody>';
    if ( empty( $rooms ) ) {
        echo '<tr><td colspan="6">' . esc_html__( 'No rooms yet.', 'simple-hotel-crm' ) . '</td></tr>';
    } else {
        foreach ( $rooms as $row ) {
            $edit_url = admin_url( 'admin.php?page=simple-hotel-crm-rooms&room_id=' . absint( $row['id'] ) );
            $toggle_url = wp_nonce_url( admin_url( 'admin.php?page=simple-hotel-crm-rooms&toggle_room=' . absint( $row['id'] ) ), 'simple_hotel_crm_toggle_room_' . absint( $row['id'] ) );
            $pricing_rows = $wpdb->get_results( $wpdb->prepare( "SELECT occupancy_adults, price_amount, active FROM {$room_pricing_table} WHERE room_id = %d ORDER BY occupancy_adults ASC", absint( $row['id'] ) ), ARRAY_A );
            $pricing_map = [];
            foreach ( $pricing_rows as $pricing_row ) {
                if ( ! empty( $pricing_row['active'] ) ) {
                    $pricing_map[ (int) $pricing_row['occupancy_adults'] ] = number_format( (float) $pricing_row['price_amount'], 2, '.', '' );
                }
            }
            echo '<tr>';
            echo '<td>' . esc_html( (string) $row['room_code'] ) . '</td>';
            echo '<td>' . esc_html( (string) $row['room_name'] ) . '</td>';
            echo '<td>' . esc_html( (string) $row['sort_order'] ) . '</td>';
            echo '<td><span style="display:inline-block;width:18px;height:18px;border:1px solid #ccc;background:' . esc_attr( (string) $row['color'] ) . ';vertical-align:middle;margin-right:6px;"></span>' . esc_html( (string) $row['color'] ) . '</td>';
            foreach ( range( 1, 4 ) as $occupancy_adults ) {
                echo '<td>' . esc_html( $pricing_map[ $occupancy_adults ] ?? '' ) . '</td>';
            }
            echo '<td>' . esc_html( ! empty( $row['active'] ) ? __( 'Active', 'simple-hotel-crm' ) : __( 'Inactive', 'simple-hotel-crm' ) ) . '</td>';
            echo '<td><a class="button button-small" href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'simple-hotel-crm' ) . '</a> <a class="button button-small" href="' . esc_url( $toggle_url ) . '">' . esc_html( ! empty( $row['active'] ) ? __( 'Deactivate', 'simple-hotel-crm' ) : __( 'Activate', 'simple-hotel-crm' ) ) . '</a></td>';
            echo '</tr>';
        }
    }
    echo '</tbody></table></div></div></div>';
}

function simple_hotel_crm_render_guests_page() {
    if ( ! simple_hotel_crm_user_can_access() ) {
        wp_die( esc_html__( 'You do not have permission to access this page.', 'simple-hotel-crm' ) );
    }

    global $wpdb;
    $guests_table = simple_hotel_crm_guests_table();
    $bookings_table = simple_hotel_crm_bookings_table();
    $view = ( isset( $_GET['view'] ) && 'trash' === $_GET['view'] ) ? 'trash' : 'active';
    $is_deleted = 'trash' === $view ? 1 : 0;
    $sortable = [ 'id' => 'g.id', 'name' => 'guest_name', 'email' => 'g.email', 'phone' => 'g.phone', 'bookings' => 'booking_count' ];
    $search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
    $orderby_key = isset( $_GET['orderby'] ) && isset( $sortable[ $_GET['orderby'] ] ) ? $_GET['orderby'] : 'name';
    $order = isset( $_GET['order'] ) && 'desc' === strtolower( (string) $_GET['order'] ) ? 'DESC' : 'ASC';
    $order_sql = $sortable[ $orderby_key ];
    $allowed_per_page = [ 20, 50, 100 ];
    $user_per_page = (int) get_user_meta( get_current_user_id(), 'simple_hotel_crm_guests_per_page', true );
    $per_page = isset( $_GET['per_page'] ) ? absint( $_GET['per_page'] ) : ( in_array( $user_per_page, $allowed_per_page, true ) ? $user_per_page : 20 );
    if ( ! in_array( $per_page, $allowed_per_page, true ) ) {
        $per_page = 20;
    }
    $paged = max( 1, absint( $_GET['paged'] ?? 1 ) );
    $offset = ( $paged - 1 ) * $per_page;
    $search_sql = '';
    $search_params = [];
    if ( '' !== $search ) {
        $search_sql = " AND (g.id LIKE %s OR g.first_name LIKE %s OR g.last_name LIKE %s OR CONCAT(g.first_name, ' ', g.last_name) LIKE %s OR g.email LIKE %s OR g.phone LIKE %s) ";
        $like = '%' . $wpdb->esc_like( $search ) . '%';
        $search_params = [ $like, $like, $like, $like, $like, $like ];
    }

    if ( isset( $_GET['restore_guest'] ) ) {
        check_admin_referer( 'simple_hotel_crm_restore_guest_' . absint( $_GET['restore_guest'] ) );
        $restored = false !== $wpdb->update( $guests_table, [ 'is_deleted' => 0, 'deleted_at' => null ], [ 'id' => absint( $_GET['restore_guest'] ) ], [ '%d', '%s' ], [ '%d' ] );
        echo '<div class="notice ' . esc_attr( $restored ? 'notice-success' : 'notice-error' ) . '"><p>' . esc_html( $restored ? __( 'Guest restored.', 'simple-hotel-crm' ) : __( 'Guest could not be restored.', 'simple-hotel-crm' ) ) . '</p></div>';
    }

    if ( isset( $_GET['delete_guest'] ) ) {
        check_admin_referer( 'simple_hotel_crm_delete_guest_' . absint( $_GET['delete_guest'] ) );
        if ( 'trash' === $view ) {
            $deleted = simple_hotel_crm_delete_guest( absint( $_GET['delete_guest'] ) );
            if ( is_wp_error( $deleted ) ) {
                echo '<div class="notice notice-error"><p>' . esc_html( $deleted->get_error_message() ) . '</p></div>';
            } else {
                echo '<div class="notice ' . esc_attr( $deleted ? 'notice-success' : 'notice-error' ) . '"><p>' . esc_html( $deleted ? __( 'Guest permanently deleted.', 'simple-hotel-crm' ) : __( 'Guest could not be deleted.', 'simple-hotel-crm' ) ) . '</p></div>';
            }
        } else {
            $booking_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$bookings_table} WHERE guest_id = %d AND is_deleted = 0", absint( $_GET['delete_guest'] ) ) );
            if ( $booking_count > 0 ) {
                echo '<div class="notice notice-error"><p>' . esc_html__( 'This guest cannot be deleted because bookings are still linked to them.', 'simple-hotel-crm' ) . '</p></div>';
            } else {
                $deleted = false !== $wpdb->update( $guests_table, [ 'is_deleted' => 1, 'deleted_at' => current_time( 'mysql' ) ], [ 'id' => absint( $_GET['delete_guest'] ) ], [ '%d', '%s' ], [ '%d' ] );
                echo '<div class="notice ' . esc_attr( $deleted ? 'notice-success' : 'notice-error' ) . '"><p>' . esc_html( $deleted ? __( 'Guest moved to trash.', 'simple-hotel-crm' ) : __( 'Guest could not be deleted.', 'simple-hotel-crm' ) ) . '</p></div>';
            }
        }
    }

    if ( isset( $_POST['simple_hotel_crm_bulk_apply_guests'] ) && ! empty( $_POST['guest_ids'] ) && is_array( $_POST['guest_ids'] ) ) {
        check_admin_referer( 'simple_hotel_crm_bulk_guests' );
        $guest_ids = array_filter( array_map( 'absint', (array) $_POST['guest_ids'] ) );
        $action = sanitize_text_field( wp_unslash( $_POST['bulk_action'] ?? '' ) );
        if ( ! empty( $guest_ids ) ) {
            if ( 'delete' === $action ) {
                if ( 'trash' === $view ) {
                    $deleted_count = 0;
                    $errors = [];
                    foreach ( $guest_ids as $guest_id ) {
                        $deleted = simple_hotel_crm_delete_guest( $guest_id );
                        if ( true === $deleted ) {
                            $deleted_count++;
                        } elseif ( is_wp_error( $deleted ) ) {
                            $errors[] = $deleted->get_error_message();
                        }
                    }
                    if ( $deleted_count > 0 ) {
                        echo '<div class="notice notice-success"><p>' . esc_html( sprintf( __( '%d guest(s) permanently deleted.', 'simple-hotel-crm' ), $deleted_count ) ) . '</p></div>';
                    }
                    if ( ! empty( $errors ) ) {
                        echo '<div class="notice notice-error"><p>' . esc_html( implode( ' ', array_unique( $errors ) ) ) . '</p></div>';
                    }
                } else {
                    $blocking = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$bookings_table} WHERE guest_id IN (" . implode( ',', $guest_ids ) . ") AND is_deleted = 0" );
                    if ( $blocking > 0 ) {
                        echo '<div class="notice notice-error"><p>' . esc_html__( 'One or more selected guests still have active bookings and could not be deleted.', 'simple-hotel-crm' ) . '</p></div>';
                    } else {
                        $wpdb->query( "UPDATE {$guests_table} SET is_deleted = 1, deleted_at = NOW() WHERE id IN (" . implode( ',', $guest_ids ) . ")" );
                        echo '<div class="notice notice-success"><p>' . esc_html__( 'Selected guests moved to trash.', 'simple-hotel-crm' ) . '</p></div>';
                    }
                }
            } elseif ( 'restore' === $action ) {
                $wpdb->query( "UPDATE {$guests_table} SET is_deleted = 0, deleted_at = NULL WHERE id IN (" . implode( ',', $guest_ids ) . ")" );
                echo '<div class="notice notice-success"><p>' . esc_html__( 'Selected guests restored.', 'simple-hotel-crm' ) . '</p></div>';
            }
        }
    }

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT g.id, CONCAT(g.first_name, ' ', g.last_name) AS guest_name, g.email, g.phone, COUNT(b.id) AS booking_count
             FROM {$guests_table} g
             LEFT JOIN {$bookings_table} b ON b.guest_id = g.id AND b.is_deleted = 0
             WHERE g.is_deleted = %d {$search_sql}
             GROUP BY g.id, guest_name, g.email, g.phone
             ORDER BY {$order_sql} {$order}
             LIMIT %d OFFSET %d",
            array_merge( [ $is_deleted ], $search_params, [ $per_page, $offset ] )
        ),
        ARRAY_A
    );
    $total_guests = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$guests_table} g WHERE g.is_deleted = %d {$search_sql}",
            array_merge( [ $is_deleted ], $search_params )
        )
    );
    $page_count = (int) ceil( $total_guests / $per_page );

    $active_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$guests_table} WHERE is_deleted = 0" );
    $trash_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$guests_table} WHERE is_deleted = 1" );
    $header_link = function( $key, $label ) use ( $orderby_key, $order, $view ) {
        $next_order = ( $orderby_key === $key && 'ASC' === $order ) ? 'desc' : 'asc';
        $url = admin_url( 'admin.php?page=simple-hotel-crm-guests&view=' . $view . '&orderby=' . $key . '&order=' . $next_order );
        return '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
    };

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__( 'Guests', 'simple-hotel-crm' ) . ' <a class="page-title-action" href="' . esc_url( admin_url( 'admin.php?page=simple-hotel-crm-guest-detail' ) ) . '">' . esc_html__( 'Add New', 'simple-hotel-crm' ) . '</a> <a class="page-title-action" href="' . esc_url( admin_url( 'admin.php?page=simple-hotel-crm-guest-duplicates' ) ) . '">' . esc_html__( 'Duplicate Check', 'simple-hotel-crm' ) . '</a></h1>';
    echo '<form method="get" style="margin:12px 0;">';
    echo '<input type="hidden" name="page" value="simple-hotel-crm-guests" />';
    echo '<input type="hidden" name="view" value="' . esc_attr( $view ) . '" />';
    echo '<input type="text" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Search guests', 'simple-hotel-crm' ) . '" /> ';
    echo '<select name="per_page">';
    foreach ( [ 20, 50, 100 ] as $pp ) { echo '<option value="' . esc_attr( (string) $pp ) . '" ' . selected( $per_page, $pp, false ) . '>' . esc_html( (string) $pp ) . '</option>'; }
    echo '</select> ';
    echo '<button type="submit" class="button">' . esc_html__( 'Search', 'simple-hotel-crm' ) . '</button>';
    echo '</form>';
    echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=simple-hotel-crm-guests&view=active' ) ) . '">' . esc_html__( 'Active', 'simple-hotel-crm' ) . ' (' . esc_html( (string) $active_count ) . ')</a> | <a href="' . esc_url( admin_url( 'admin.php?page=simple-hotel-crm-guests&view=trash' ) ) . '">' . esc_html__( 'Trash', 'simple-hotel-crm' ) . ' (' . esc_html( (string) $trash_count ) . ')</a></p>';
    echo '<form method="post">';
    wp_nonce_field( 'simple_hotel_crm_bulk_guests' );
    echo '<p><select name="bulk_action"><option value="">' . esc_html__( 'Bulk actions', 'simple-hotel-crm' ) . '</option><option value="delete">' . esc_html__( 'Delete', 'simple-hotel-crm' ) . '</option><option value="restore">' . esc_html__( 'Restore', 'simple-hotel-crm' ) . '</option><option value="cancel">' . esc_html__( 'Mark cancelled', 'simple-hotel-crm' ) . '</option><option value="confirm">' . esc_html__( 'Mark confirmed', 'simple-hotel-crm' ) . '</option></select> ';
    submit_button( __( 'Apply', 'simple-hotel-crm' ), 'secondary', 'simple_hotel_crm_bulk_apply_guests', false );
    if ( 'trash' === $view ) {
        echo ' <span class="description">' . esc_html__( 'Delete will permanently remove trashed guests.', 'simple-hotel-crm' ) . '</span>';
    }
    echo '</p>';
    echo '<table class="widefat striped"><thead><tr>';
    echo '<th><input type="checkbox" onclick="jQuery(\'.guest-bulk-cb\').prop(\'checked\', this.checked)" /></th>';
    echo '<th>' . $header_link( 'id', __( 'ID', 'simple-hotel-crm' ) ) . '</th>';
    echo '<th>' . $header_link( 'name', __( 'Name', 'simple-hotel-crm' ) ) . '</th>';
    echo '<th>' . $header_link( 'email', __( 'Email', 'simple-hotel-crm' ) ) . '</th>';
    echo '<th>' . $header_link( 'phone', __( 'Phone', 'simple-hotel-crm' ) ) . '</th>';
    echo '<th>' . $header_link( 'bookings', __( 'Bookings', 'simple-hotel-crm' ) ) . '</th>';
    echo '<th>' . esc_html__( 'Actions', 'simple-hotel-crm' ) . '</th>';
    echo '</tr></thead><tbody>';
    if ( empty( $rows ) ) {
        echo '<tr><td colspan="7">' . esc_html__( 'No guests found.', 'simple-hotel-crm' ) . '</td></tr>';
    } else {
        foreach ( $rows as $row ) {
            $detail_url = admin_url( 'admin.php?page=simple-hotel-crm-guest-detail&guest_id=' . absint( $row['id'] ) );
            $delete_url = wp_nonce_url( admin_url( 'admin.php?page=simple-hotel-crm-guests&delete_guest=' . absint( $row['id'] ) . '&view=' . $view ), 'simple_hotel_crm_delete_guest_' . absint( $row['id'] ) );
            $restore_url = wp_nonce_url( admin_url( 'admin.php?page=simple-hotel-crm-guests&restore_guest=' . absint( $row['id'] ) . '&view=' . $view ), 'simple_hotel_crm_restore_guest_' . absint( $row['id'] ) );
            echo '<tr>';
            echo '<td><input class="guest-bulk-cb" type="checkbox" name="guest_ids[]" value="' . esc_attr( (string) $row['id'] ) . '" /></td>';
            echo '<td><a href="' . esc_url( $detail_url ) . '">' . esc_html( (string) $row['id'] ) . '</a></td>';
            echo '<td><a href="' . esc_url( $detail_url ) . '">' . esc_html( trim( (string) $row['guest_name'] ) ) . '</a></td>';
            echo '<td>' . esc_html( (string) $row['email'] ) . '</td>';
            echo '<td>' . esc_html( (string) $row['phone'] ) . '</td>';
            echo '<td>' . esc_html( (string) $row['booking_count'] ) . '</td>';
            echo '<td>' . ( 'trash' === $view ? '<a class="button button-small" href="' . esc_url( $restore_url ) . '">' . esc_html__( 'Restore', 'simple-hotel-crm' ) . '</a> <a class="button button-small button-link-delete" href="' . esc_url( $delete_url ) . '" onclick="return confirm(' . wp_json_encode( __( 'Permanently delete this guest?', 'simple-hotel-crm' ) ) . ');">' . esc_html__( 'Delete Permanently', 'simple-hotel-crm' ) . '</a>' : '<a class="button button-small" href="' . esc_url( $detail_url ) . '">' . esc_html__( 'View / Edit', 'simple-hotel-crm' ) . '</a> <a class="button button-small" href="' . esc_url( $delete_url ) . '" onclick="return confirm(' . wp_json_encode( __( 'Delete this guest?', 'simple-hotel-crm' ) ) . ');">' . esc_html__( 'Delete', 'simple-hotel-crm' ) . '</a>' ) . '</td>';
            echo '</tr>';
        }
    }
    echo '</tbody></table></form>';
    if ( $page_count > 1 ) {
        $base_args = [ 'page' => 'simple-hotel-crm-guests', 'view' => $view, 's' => $search, 'orderby' => $orderby_key, 'order' => strtolower( $order ), 'per_page' => $per_page ];
        echo '<div class="tablenav"><div class="tablenav-pages"><span class="pagination-links">';
        echo '<a class="first-page button" href="' . esc_url( add_query_arg( array_merge( $base_args, [ 'paged' => 1 ] ), admin_url( 'admin.php' ) ) ) . '">&laquo;</a> ';
        if ( $paged > 1 ) { echo '<a class="prev-page button" href="' . esc_url( add_query_arg( array_merge( $base_args, [ 'paged' => $paged - 1 ] ), admin_url( 'admin.php' ) ) ) . '">&lsaquo;</a> '; } else { echo '<span class="tablenav-pages-navspan button disabled">&lsaquo;</span> '; }
        $start = max( 1, $paged - 2 );
        $end = min( $page_count, $paged + 2 );
        if ( $start > 1 ) { echo '<span class="page-numbers dots">&hellip;</span> '; }
        for ( $i = $start; $i <= $end; $i++ ) { $url = add_query_arg( array_merge( $base_args, [ 'paged' => $i ] ), admin_url( 'admin.php' ) ); echo '<a class="page-numbers' . ( $i === $paged ? ' current' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( (string) $i ) . '</a> '; }
        if ( $end < $page_count ) { echo '<span class="page-numbers dots">&hellip;</span> '; }
        if ( $paged < $page_count ) { echo '<a class="next-page button" href="' . esc_url( add_query_arg( array_merge( $base_args, [ 'paged' => $paged + 1 ] ), admin_url( 'admin.php' ) ) ) . '">&rsaquo;</a> '; } else { echo '<span class="tablenav-pages-navspan button disabled">&rsaquo;</span> '; }
        echo '<a class="last-page button" href="' . esc_url( add_query_arg( array_merge( $base_args, [ 'paged' => $page_count ] ), admin_url( 'admin.php' ) ) ) . '">&raquo;</a>';
        echo '</span></div></div>';
    }
    echo '</div>';
}

function simple_hotel_crm_normalize_guest_match_value( $value ) {
    $value = remove_accents( strtolower( trim( (string) $value ) ) );
    $value = preg_replace( '/[^a-z0-9]+/', ' ', $value );
    return trim( preg_replace( '/\s+/', ' ', (string) $value ) );
}

function simple_hotel_crm_find_duplicate_guest_groups() {
    global $wpdb;

    $guests_table = simple_hotel_crm_guests_table();
    $rows = $wpdb->get_results( "SELECT * FROM {$guests_table} WHERE is_deleted = 0 ORDER BY id ASC", ARRAY_A );
    $groups = [];

    foreach ( $rows as $row ) {
        $name_key = simple_hotel_crm_normalize_guest_match_value( trim( (string) $row['first_name'] . ' ' . (string) $row['last_name'] ) );
        $email_key = simple_hotel_crm_normalize_guest_match_value( (string) $row['email'] );
        $phone_key = preg_replace( '/\D+/', '', (string) $row['phone'] );

        $keys = [];
        if ( '' !== $name_key && '' !== $email_key ) {
            $keys[] = 'name_email:' . $name_key . '|' . $email_key;
        }
        if ( '' !== $name_key && '' !== $phone_key ) {
            $keys[] = 'name_phone:' . $name_key . '|' . $phone_key;
        }
        if ( '' !== $email_key && '' !== $phone_key ) {
            $keys[] = 'email_phone:' . $email_key . '|' . $phone_key;
        }
        if ( '' !== $name_key ) {
            $keys[] = 'name:' . $name_key;
        }

        foreach ( $keys as $key ) {
            if ( ! isset( $groups[ $key ] ) ) {
                $groups[ $key ] = [];
            }
            $groups[ $key ][ (int) $row['id'] ] = $row;
        }
    }

    $result = [];
    foreach ( $groups as $key => $group ) {
        if ( count( $group ) < 2 ) {
            continue;
        }
        $result[ $key ] = array_values( $group );
    }

    return $result;
}

function simple_hotel_crm_merge_guests( $primary_guest_id, $duplicate_guest_id ) {
    global $wpdb;

    $primary_guest_id = absint( $primary_guest_id );
    $duplicate_guest_id = absint( $duplicate_guest_id );
    if ( $primary_guest_id <= 0 || $duplicate_guest_id <= 0 || $primary_guest_id === $duplicate_guest_id ) {
        return new WP_Error( 'invalid_guest_merge', __( 'Please choose two different guests.', 'simple-hotel-crm' ) );
    }

    $guests_table = simple_hotel_crm_guests_table();
    $bookings_table = simple_hotel_crm_bookings_table();
    $primary = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$guests_table} WHERE id = %d LIMIT 1", $primary_guest_id ), ARRAY_A );
    $duplicate = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$guests_table} WHERE id = %d LIMIT 1", $duplicate_guest_id ), ARRAY_A );
    if ( ! $primary || ! $duplicate ) {
        return new WP_Error( 'missing_guest_merge', __( 'One or both guests could not be found.', 'simple-hotel-crm' ) );
    }

    $merge_fields = [ 'email', 'phone', 'address_line_1', 'address_line_2', 'city', 'postcode', 'country', 'notes' ];
    $update = [];
    foreach ( $merge_fields as $field ) {
        if ( empty( $primary[ $field ] ) && ! empty( $duplicate[ $field ] ) ) {
            $update[ $field ] = $duplicate[ $field ];
        }
    }

    $wpdb->query( 'START TRANSACTION' );
    if ( ! empty( $update ) ) {
        $wpdb->update( $guests_table, $update, [ 'id' => $primary_guest_id ] );
    }
    $wpdb->update( $bookings_table, [ 'guest_id' => $primary_guest_id ], [ 'guest_id' => $duplicate_guest_id ], [ '%d' ], [ '%d' ] );
    $deleted = $wpdb->delete( $guests_table, [ 'id' => $duplicate_guest_id ], [ '%d' ] );
    if ( false === $deleted ) {
        $wpdb->query( 'ROLLBACK' );
        return new WP_Error( 'guest_merge_failed', __( 'Could not merge guests.', 'simple-hotel-crm' ) );
    }
    $wpdb->query( 'COMMIT' );
    return true;
}

function simple_hotel_crm_find_booking_transfer_candidates() {
    global $wpdb;

    $bookings_table = simple_hotel_crm_bookings_table();
    $booking_rooms_table = simple_hotel_crm_booking_rooms_table();
    $guests_table = simple_hotel_crm_guests_table();

    $rows = $wpdb->get_results(
        "SELECT b.id, b.guest_id, b.check_in_date, b.check_out_date, b.source_channel, b.source_booking_id, b.status_code, b.booking_note, b.internal_notes, b.adults, b.children, b.babies, b.total_amount, COUNT(br.id) AS room_count, COALESCE(SUM(br.guest_count), 0) AS room_guest_count, GROUP_CONCAT(br.room_id ORDER BY br.room_id ASC SEPARATOR ',') AS room_ids, g.first_name, g.last_name, g.email, g.phone
         FROM {$bookings_table} b
         LEFT JOIN {$booking_rooms_table} br ON br.booking_id = b.id
         LEFT JOIN {$guests_table} g ON g.id = b.guest_id
         WHERE b.is_deleted = 0
         GROUP BY b.id
         ORDER BY b.check_in_date DESC, b.id DESC",
        ARRAY_A
    );

    $candidates = [];
    for ( $i = 0; $i < count( $rows ); $i++ ) {
        for ( $j = 0; $j < count( $rows ); $j++ ) {
            if ( $i === $j ) {
                continue;
            }
            $newer = $rows[ $i ];
            $older = $rows[ $j ];
            if ( (int) $newer['id'] < (int) $older['id'] ) {
                continue;
            }
            $same_channel = (string) $newer['source_channel'] === (string) $older['source_channel'];
            $same_dates = (string) $newer['check_in_date'] === (string) $older['check_in_date'] && (string) $newer['check_out_date'] === (string) $older['check_out_date'];
            $same_rooms = (string) $newer['room_ids'] === (string) $older['room_ids'];
            if ( ! $same_channel || ! $same_dates || ! $same_rooms ) {
                continue;
            }
            $score = 0;
            if ( $same_dates ) { $score += 5; }
            if ( $same_rooms ) { $score += 5; }
            if ( (int) $newer['adults'] === (int) $older['adults'] ) { $score += 1; }
            if ( (int) $newer['children'] === (int) $older['children'] ) { $score += 1; }
            if ( (int) $newer['babies'] === (int) $older['babies'] ) { $score += 1; }
            if ( ! empty( $newer['source_booking_id'] ) && $newer['source_booking_id'] === $older['source_booking_id'] ) { $score += 10; }
            if ( abs( (float) $newer['total_amount'] - (float) $older['total_amount'] ) < 0.01 ) { $score += 1; }
            $reason = [];
            if ( $same_channel ) { $reason[] = 'same channel'; }
            if ( $same_dates ) { $reason[] = 'dates'; }
            if ( $same_rooms ) { $reason[] = 'same rooms'; }
            if ( ! empty( $newer['source_booking_id'] ) && $newer['source_booking_id'] === $older['source_booking_id'] ) { $reason[] = 'same source id'; }
            if ( ! empty( $newer['internal_notes'] ) && false !== strpos( (string) $newer['internal_notes'], '[TRANSFERRED_FROM_BOOKING:' ) ) {
                continue;
            }
            $candidates[] = [ 'target' => $newer, 'source' => $older, 'score' => $score, 'reason' => implode( ', ', $reason ) ];
        }
    }

    usort( $candidates, static function( $a, $b ) { return (int) $b['score'] <=> (int) $a['score']; } );
    return array_slice( $candidates, 0, 50 );
}

function simple_hotel_crm_transfer_booking_details( $target_booking_id, $source_booking_id ) {
    global $wpdb;

    $bookings_table = simple_hotel_crm_bookings_table();
    $booking_rooms_table = simple_hotel_crm_booking_rooms_table();
    $booking_nights_table = simple_hotel_crm_booking_room_nights_table();
    $target = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$bookings_table} WHERE id = %d LIMIT 1", $target_booking_id ), ARRAY_A );
    $source = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$bookings_table} WHERE id = %d LIMIT 1", $source_booking_id ), ARRAY_A );
    if ( ! $target || ! $source ) {
        return new WP_Error( 'missing_booking', __( 'One or both bookings not found.', 'simple-hotel-crm' ) );
    }

    $marker = '[TRANSFERRED_FROM_BOOKING:' . (int) $source_booking_id . ']';
    $update = [
        'guest_id' => (int) $source['guest_id'],
        'source_channel' => (string) $target['source_channel'],
        'source_booking_id' => (string) $target['source_booking_id'],
        'status_code' => (string) $source['status_code'],
        'contacted_date' => ! empty( $source['contacted_date'] ) ? (string) $source['contacted_date'] : (string) $target['contacted_date'],
        'check_in_date' => (string) $source['check_in_date'],
        'check_out_date' => (string) $source['check_out_date'],
        'adults' => (int) $source['adults'],
        'children' => (int) $source['children'],
        'babies' => (int) $source['babies'],
        'room_rate_amount' => (float) $source['room_rate_amount'],
        'extras_amount' => (float) $source['extras_amount'],
        'tourist_tax_amount' => (float) $source['tourist_tax_amount'],
        'total_amount' => (float) $source['total_amount'],
        'booking_note' => (string) $source['booking_note'],
        'special_requests' => (string) $source['special_requests'],
        'internal_notes' => trim( (string) $source['internal_notes'] . ' ' . $target['internal_notes'] ) . ' ' . $marker,
    ];

    $wpdb->query( 'START TRANSACTION' );
    $wpdb->update( $bookings_table, $update, [ 'id' => $target_booking_id ], [ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%f', '%f', '%f', '%f', '%s', '%s', '%s' ], [ '%d' ] );

    $target_room_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$booking_rooms_table} WHERE booking_id = %d", $target_booking_id ) );
    if ( ! empty( $target_room_ids ) ) {
        $wpdb->query( "DELETE FROM {$booking_nights_table} WHERE booking_room_id IN (" . implode( ',', array_map( 'intval', $target_room_ids ) ) . ')' );
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$booking_rooms_table} WHERE booking_id = %d", $target_booking_id ) );
    }

    $source_rooms = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$booking_rooms_table} WHERE booking_id = %d ORDER BY id ASC", $source_booking_id ), ARRAY_A );
    foreach ( $source_rooms as $source_room ) {
        $room_payload = $source_room;
        unset( $room_payload['id'], $room_payload['booking_id'], $room_payload['created_at'] );
        $room_payload['booking_id'] = (int) $target_booking_id;
        $wpdb->insert( $booking_rooms_table, $room_payload );
        $new_booking_room_id = (int) $wpdb->insert_id;
        $source_nights = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$booking_nights_table} WHERE booking_room_id = %d ORDER BY stay_date ASC", (int) $source_room['id'] ), ARRAY_A );
        foreach ( $source_nights as $source_night ) {
            $night_payload = $source_night;
            unset( $night_payload['id'], $night_payload['booking_room_id'], $night_payload['created_at'] );
            $night_payload['booking_room_id'] = $new_booking_room_id;
            $wpdb->insert( $booking_nights_table, $night_payload );
        }
    }

    $wpdb->query( 'COMMIT' );
    simple_hotel_crm_clear_calendar_cache();
    return true;
}

function simple_hotel_crm_find_booking_merge_candidates() {
    global $wpdb;

    $bookings_table = simple_hotel_crm_bookings_table();
    $booking_rooms_table = simple_hotel_crm_booking_rooms_table();
    $guests_table = simple_hotel_crm_guests_table();
    $rows = $wpdb->get_results(
        "SELECT b.id, b.guest_id, b.check_in_date, b.check_out_date, b.source_channel, b.source_booking_id, b.status_code, b.booking_note, b.internal_notes, b.total_amount, GROUP_CONCAT(br.room_id ORDER BY br.room_id ASC SEPARATOR ',') AS room_ids, g.first_name, g.last_name
         FROM {$bookings_table} b
         LEFT JOIN {$booking_rooms_table} br ON br.booking_id = b.id
         LEFT JOIN {$guests_table} g ON g.id = b.guest_id
         WHERE b.is_deleted = 0 AND b.source_channel = 'booking_com'
         GROUP BY b.id
         ORDER BY b.check_in_date DESC, b.id DESC",
        ARRAY_A
    );

    $groups = [];
    foreach ( $rows as $row ) {
        if ( ! empty( $row['internal_notes'] ) && false !== strpos( (string) $row['internal_notes'], '[MERGED_ARCHIVE]' ) ) {
            continue;
        }
        $key = (string) $row['source_channel'] . '|' . (string) $row['check_in_date'] . '|' . (string) $row['check_out_date'];
        $groups[ $key ][] = $row;
    }

    $result = [];
    foreach ( $groups as $key => $group_rows ) {
        if ( count( $group_rows ) < 2 ) {
            continue;
        }
        $room_sets = array_unique( array_map( static function( $row ) { return (string) ( $row['room_ids'] ?? '' ); }, $group_rows ) );
        if ( count( $room_sets ) < 2 ) {
            continue;
        }
        $result[ $key ] = $group_rows;
    }

    return $result;
}

function simple_hotel_crm_merge_bookings( $primary_booking_id, $merge_booking_ids ) {
    global $wpdb;

    $bookings_table = simple_hotel_crm_bookings_table();
    $booking_rooms_table = simple_hotel_crm_booking_rooms_table();
    $booking_nights_table = simple_hotel_crm_booking_room_nights_table();
    $primary = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$bookings_table} WHERE id = %d LIMIT 1", $primary_booking_id ), ARRAY_A );
    if ( ! $primary ) {
        return new WP_Error( 'missing_primary_booking', __( 'Primary booking not found.', 'simple-hotel-crm' ) );
    }

    $wpdb->query( 'START TRANSACTION' );
    foreach ( $merge_booking_ids as $merge_booking_id ) {
        $merge_booking_id = absint( $merge_booking_id );
        if ( $merge_booking_id <= 0 || $merge_booking_id === (int) $primary_booking_id ) {
            continue;
        }
        $booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$bookings_table} WHERE id = %d LIMIT 1", $merge_booking_id ), ARRAY_A );
        if ( ! $booking ) {
            continue;
        }
        $rooms = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$booking_rooms_table} WHERE booking_id = %d ORDER BY id ASC", $merge_booking_id ), ARRAY_A );
        foreach ( $rooms as $room ) {
            $room_payload = $room;
            $old_room_id = (int) $room['id'];
            unset( $room_payload['id'], $room_payload['booking_id'], $room_payload['created_at'] );
            $room_payload['booking_id'] = (int) $primary_booking_id;
            $wpdb->insert( $booking_rooms_table, $room_payload );
            $new_booking_room_id = (int) $wpdb->insert_id;
            $nights = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$booking_nights_table} WHERE booking_room_id = %d ORDER BY stay_date ASC", $old_room_id ), ARRAY_A );
            foreach ( $nights as $night ) {
                $night_payload = $night;
                unset( $night_payload['id'], $night_payload['booking_room_id'], $night_payload['created_at'] );
                $night_payload['booking_room_id'] = $new_booking_room_id;
                $wpdb->insert( $booking_nights_table, $night_payload );
            }
        }
        $merged_note = trim( (string) $booking['internal_notes'] . ' [MERGED_ARCHIVE] [MERGED_INTO_BOOKING:' . (int) $primary_booking_id . ']' );
        $wpdb->update( $bookings_table, [ 'internal_notes' => $merged_note ], [ 'id' => $merge_booking_id ], [ '%s' ], [ '%d' ] );
    }
    simple_hotel_crm_recalculate_booking_header_totals();
    $wpdb->query( 'COMMIT' );
    simple_hotel_crm_clear_calendar_cache();
    return true;
}

function simple_hotel_crm_render_booking_merges_page() {
    if ( ! simple_hotel_crm_user_can_access() ) {
        wp_die( esc_html__( 'You do not have permission to access this page.', 'simple-hotel-crm' ) );
    }

    echo '<div class="wrap"><h1>' . esc_html__( 'Booking Merges', 'simple-hotel-crm' ) . '</h1><p>' . esc_html__( 'Merge same-channel, same-date Booking.com bookings with different rooms into one booking with multiple room lines.', 'simple-hotel-crm' ) . '</p>';
    if ( isset( $_POST['simple_hotel_crm_merge_bookings'] ) ) {
        check_admin_referer( 'simple_hotel_crm_merge_bookings' );
        $primary_booking_id = absint( $_POST['primary_booking_id'] ?? 0 );
        $merge_booking_ids = isset( $_POST['merge_booking_ids'] ) && is_array( $_POST['merge_booking_ids'] ) ? array_map( 'absint', wp_unslash( $_POST['merge_booking_ids'] ) ) : [];
        $result = simple_hotel_crm_merge_bookings( $primary_booking_id, $merge_booking_ids );
        echo '<div class="notice ' . esc_attr( is_wp_error( $result ) ? 'notice-error' : 'notice-success' ) . '"><p>' . esc_html( is_wp_error( $result ) ? $result->get_error_message() : __( 'Bookings merged.', 'simple-hotel-crm' ) ) . '</p></div>';
    }

    $groups = simple_hotel_crm_find_booking_merge_candidates();
    if ( empty( $groups ) ) {
        echo '<p>' . esc_html__( 'No likely booking merge groups found.', 'simple-hotel-crm' ) . '</p></div>';
        return;
    }

    foreach ( $groups as $group_key => $group_rows ) {
        echo '<form method="post" style="margin:0 0 24px 0;padding:12px;border:1px solid #ccd0d4;background:#fff;">';
        wp_nonce_field( 'simple_hotel_crm_merge_bookings' );
        echo '<h2 style="margin-top:0;">' . esc_html( $group_key ) . '</h2>';
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Keep as one booking', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Merge into primary', 'simple-hotel-crm' ) . '</th><th>ID</th><th>' . esc_html__( 'Guest', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Rooms', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Total', 'simple-hotel-crm' ) . '</th></tr></thead><tbody>';
        foreach ( $group_rows as $index => $booking ) {
            echo '<tr>';
            echo '<td><input type="radio" name="primary_booking_id" value="' . esc_attr( (string) $booking['id'] ) . '"' . checked( 0, $index, false ) . ' /></td>';
            echo '<td><input type="checkbox" name="merge_booking_ids[]" value="' . esc_attr( (string) $booking['id'] ) . '"' . checked( 0 !== $index, true, false ) . ' /></td>';
            echo '<td>' . esc_html( (string) $booking['id'] ) . '</td>';
            echo '<td>' . esc_html( trim( (string) $booking['first_name'] . ' ' . (string) $booking['last_name'] ) ?: __( '(no guest)', 'simple-hotel-crm' ) ) . '</td>';
            echo '<td>' . esc_html( (string) $booking['room_ids'] ) . '</td>';
            echo '<td>' . esc_html( number_format_i18n( (float) $booking['total_amount'], 2 ) ) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        submit_button( __( 'Merge Selected Bookings', 'simple-hotel-crm' ), 'primary', 'simple_hotel_crm_merge_bookings', false, [ 'onclick' => "return confirm('Merge selected bookings into the chosen primary booking?');" ] );
        echo '</form>';
    }
    echo '</div>';
}

function simple_hotel_crm_render_booking_transfers_page() {
    if ( ! simple_hotel_crm_user_can_access() ) {
        wp_die( esc_html__( 'You do not have permission to access this page.', 'simple-hotel-crm' ) );
    }

    echo '<div class="wrap"><h1>' . esc_html__( 'Booking Transfers', 'simple-hotel-crm' ) . '</h1><p>' . esc_html__( 'Target = newer booking. Source = older booking. Approve transfer when dates/rooms conflict and the newer booking should inherit guest or notes.', 'simple-hotel-crm' ) . '</p>';
    if ( isset( $_POST['simple_hotel_crm_transfer_booking_bulk'] ) ) {
        check_admin_referer( 'simple_hotel_crm_transfer_booking_bulk' );
        $pairs = isset( $_POST['transfer_pairs'] ) && is_array( $_POST['transfer_pairs'] ) ? wp_unslash( $_POST['transfer_pairs'] ) : [];
        $done = 0;
        foreach ( $pairs as $pair ) {
            if ( empty( $pair['selected'] ) ) {
                continue;
            }
            $result = simple_hotel_crm_transfer_booking_details( absint( $pair['target_booking_id'] ?? 0 ), absint( $pair['source_booking_id'] ?? 0 ) );
            if ( ! is_wp_error( $result ) ) {
                $done++;
            }
        }
        echo '<div class="notice notice-success"><p>' . esc_html( sprintf( __( 'Transfer complete. Approved %d pair(s).', 'simple-hotel-crm' ), $done ) ) . '</p></div>';
    } elseif ( isset( $_POST['simple_hotel_crm_transfer_booking'] ) ) {
        check_admin_referer( 'simple_hotel_crm_transfer_booking' );
        $result = simple_hotel_crm_transfer_booking_details( absint( $_POST['target_booking_id'] ?? 0 ), absint( $_POST['source_booking_id'] ?? 0 ) );
        echo '<div class="notice ' . esc_attr( is_wp_error( $result ) ? 'notice-error' : 'notice-success' ) . '"><p>' . esc_html( is_wp_error( $result ) ? $result->get_error_message() : __( 'Booking transfer complete.', 'simple-hotel-crm' ) ) . '</p></div>';
    }
    $candidates = simple_hotel_crm_find_booking_transfer_candidates();
    if ( empty( $candidates ) ) {
        echo '<p>' . esc_html__( 'No likely booking transfer pairs found.', 'simple-hotel-crm' ) . '</p></div>';
        return;
    }
    $per_page = 50;
    $paged = max( 1, absint( $_GET['transfer_paged'] ?? 1 ) );
    $total_candidates = count( $candidates );
    $page_count = max( 1, (int) ceil( $total_candidates / $per_page ) );
    if ( $paged > $page_count ) {
        $paged = $page_count;
    }
    $candidates = array_slice( $candidates, ( $paged - 1 ) * $per_page, $per_page );

    if ( $page_count > 1 ) {
        echo '<div class="tablenav top"><div class="tablenav-pages">';
        for ( $i = 1; $i <= $page_count; $i++ ) {
            $url = add_query_arg( [ 'page' => 'simple-hotel-crm-booking-transfers', 'transfer_paged' => $i ], admin_url( 'admin.php' ) );
            echo '<a class="button' . ( $i === $paged ? ' button-primary' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( (string) $i ) . '</a> ';
        }
        echo '</div></div>';
    }

    echo '<form method="post">';
    wp_nonce_field( 'simple_hotel_crm_transfer_booking_bulk' );
    echo '<p><label><input type="checkbox" id="transfer-select-all-top" /> ' . esc_html__( 'Select all', 'simple-hotel-crm' ) . '</label></p>';
    echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Select', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Newer target', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Older source', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Dates', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Rooms', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Diff', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Why matched', 'simple-hotel-crm' ) . '</th></tr></thead><tbody>';
    foreach ( $candidates as $index => $pair ) {
        $target = $pair['target'];
        $source = $pair['source'];
        echo '<tr>';
        echo '<td><input type="checkbox" name="transfer_pairs[' . esc_attr( (string) $index ) . '][selected]" value="1" checked="checked" />';
        echo '<input type="hidden" name="transfer_pairs[' . esc_attr( (string) $index ) . '][target_booking_id]" value="' . esc_attr( (string) $target['id'] ) . '" />';
        echo '<input type="hidden" name="transfer_pairs[' . esc_attr( (string) $index ) . '][source_booking_id]" value="' . esc_attr( (string) $source['id'] ) . '" /></td>';
        echo '<td>#' . esc_html( (string) $target['id'] ) . ' ' . esc_html( trim( (string) $target['first_name'] . ' ' . (string) $target['last_name'] ) ?: __( '(no guest)', 'simple-hotel-crm' ) ) . '<br /><span class="description">' . esc_html( (string) $target['source_channel'] ) . '</span></td>';
        echo '<td>#' . esc_html( (string) $source['id'] ) . ' ' . esc_html( trim( (string) $source['first_name'] . ' ' . (string) $source['last_name'] ) ?: __( '(no guest)', 'simple-hotel-crm' ) ) . '<br /><span class="description">' . esc_html( (string) $source['source_channel'] ) . '</span></td>';
        echo '<td>' . esc_html( (string) $target['check_in_date'] ) . ' → ' . esc_html( (string) $target['check_out_date'] ) . '</td>';
        echo '<td>' . esc_html( (string) $target['room_count'] ) . ' / ' . esc_html( (string) $target['room_ids'] ) . '</td>';
        echo '<td>';
        echo esc_html__( 'Guest:', 'simple-hotel-crm' ) . ' ' . ( empty( $target['guest_id'] ) ? esc_html__( 'missing', 'simple-hotel-crm' ) : esc_html__( 'set', 'simple-hotel-crm' ) ) . ' · ';
        echo esc_html__( 'Notes:', 'simple-hotel-crm' ) . ' ' . ( empty( $target['booking_note'] ) && empty( $target['internal_notes'] ) ? esc_html__( 'empty', 'simple-hotel-crm' ) : esc_html__( 'filled', 'simple-hotel-crm' ) ) . ' · ';
        echo esc_html__( 'Total:', 'simple-hotel-crm' ) . ' ' . esc_html( number_format_i18n( (float) $target['total_amount'], 2 ) ) . ' · ';
        echo esc_html__( 'Source:', 'simple-hotel-crm' ) . ' ' . esc_html( (string) $target['source_booking_id'] );
        echo '</td>';
        echo '<td>' . esc_html( (string) ( $pair['reason'] ?? '' ) ) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    echo '<p><label><input type="checkbox" id="transfer-select-all-bottom" /> ' . esc_html__( 'Select all', 'simple-hotel-crm' ) . '</label></p>';
    submit_button( __( 'Approve Selected Transfers', 'simple-hotel-crm' ), 'primary', 'simple_hotel_crm_transfer_booking_bulk', false, [ 'onclick' => "return confirm('Approve all selected booking transfers?');" ] );
    echo '</form>';
    if ( $page_count > 1 ) {
        echo '<div class="tablenav bottom"><div class="tablenav-pages">';
        for ( $i = 1; $i <= $page_count; $i++ ) {
            $url = add_query_arg( [ 'page' => 'simple-hotel-crm-booking-transfers', 'transfer_paged' => $i ], admin_url( 'admin.php' ) );
            echo '<a class="button' . ( $i === $paged ? ' button-primary' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( (string) $i ) . '</a> ';
        }
        echo '</div></div>';
    }
    echo '<script>document.addEventListener("change",function(e){if(e.target&&e.target.id&&e.target.id.indexOf("transfer-select-all")===0){document.querySelectorAll("input[name^=\"transfer_pairs\"][name$=\"[selected]\"]").forEach(function(cb){cb.checked=e.target.checked;});if(e.target.id==="transfer-select-all-top"){var b=document.getElementById("transfer-select-all-bottom");if(b)b.checked=e.target.checked;}if(e.target.id==="transfer-select-all-bottom"){var t=document.getElementById("transfer-select-all-top");if(t)t.checked=e.target.checked;}}});</script></div>';
}

function simple_hotel_crm_render_guest_duplicates_page() {
    if ( ! simple_hotel_crm_user_can_access() ) {
        wp_die( esc_html__( 'You do not have permission to access this page.', 'simple-hotel-crm' ) );
    }

    if ( isset( $_POST['simple_hotel_crm_merge_guests'] ) ) {
        check_admin_referer( 'simple_hotel_crm_merge_guests' );
        $result = simple_hotel_crm_merge_guests( absint( $_POST['primary_guest_id'] ?? 0 ), absint( $_POST['duplicate_guest_id'] ?? 0 ) );
        echo '<div class="notice ' . esc_attr( is_wp_error( $result ) ? 'notice-error' : 'notice-success' ) . '"><p>' . esc_html( is_wp_error( $result ) ? $result->get_error_message() : __( 'Guests merged.', 'simple-hotel-crm' ) ) . '</p></div>';
    }

    $groups = simple_hotel_crm_find_duplicate_guest_groups();
    echo '<div class="wrap">';
    echo '<h1>' . esc_html__( 'Guest Duplicates', 'simple-hotel-crm' ) . '</h1>';
    echo '<p>' . esc_html__( 'Review possible duplicate guests and merge them into a single primary guest.', 'simple-hotel-crm' ) . '</p>';
    if ( empty( $groups ) ) {
        echo '<p>' . esc_html__( 'No likely duplicate guests found.', 'simple-hotel-crm' ) . '</p>';
        echo '</div>';
        return;
    }

    foreach ( $groups as $group_key => $group ) {
        echo '<form method="post" style="margin:0 0 24px 0;padding:12px;border:1px solid #ccd0d4;background:#fff;">';
        wp_nonce_field( 'simple_hotel_crm_merge_guests' );
        echo '<h2 style="margin-top:0;">' . esc_html( $group_key ) . '</h2>';
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Keep', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Merge', 'simple-hotel-crm' ) . '</th><th>ID</th><th>' . esc_html__( 'Name', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Email', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Phone', 'simple-hotel-crm' ) . '</th></tr></thead><tbody>';
        foreach ( $group as $index => $guest ) {
            echo '<tr>';
            echo '<td><input type="radio" name="primary_guest_id" value="' . esc_attr( (string) $guest['id'] ) . '"' . checked( 0, $index, false ) . ' /></td>';
            echo '<td><input type="radio" name="duplicate_guest_id" value="' . esc_attr( (string) $guest['id'] ) . '"' . checked( 1, $index, false ) . ' /></td>';
            echo '<td>' . esc_html( (string) $guest['id'] ) . '</td>';
            echo '<td>' . esc_html( trim( (string) $guest['first_name'] . ' ' . (string) $guest['last_name'] ) ) . '</td>';
            echo '<td>' . esc_html( (string) $guest['email'] ) . '</td>';
            echo '<td>' . esc_html( (string) $guest['phone'] ) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        submit_button( __( 'Merge Selected Guests', 'simple-hotel-crm' ), 'primary', 'simple_hotel_crm_merge_guests', false, [ 'onclick' => "return confirm('" . esc_js( __( 'Merge the selected duplicate guest into the primary guest?', 'simple-hotel-crm' ) ) . "');" ] );
        echo '</form>';
    }

    echo '</div>';
}

function simple_hotel_crm_delete_booking( $booking_id ) {
    global $wpdb;

    $booking_id = absint( $booking_id );
    if ( $booking_id <= 0 ) {
        return false;
    }

    $bookings_table = simple_hotel_crm_bookings_table();
    $booking_rooms_table = simple_hotel_crm_booking_rooms_table();
    $booking_nights_table = simple_hotel_crm_booking_room_nights_table();
    $sync_bookings_table = simple_hotel_crm_sync_bookings_table();

    $room_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$booking_rooms_table} WHERE booking_id = %d", $booking_id ) );
    $legacy_ids = $wpdb->get_col( $wpdb->prepare( "SELECT legacy_reserved_room_id FROM {$booking_rooms_table} WHERE booking_id = %d", $booking_id ) );
    if ( ! empty( $room_ids ) ) {
        $wpdb->query( "DELETE FROM {$booking_nights_table} WHERE booking_room_id IN (" . implode( ',', array_map( 'intval', $room_ids ) ) . ")" );
    }
    if ( ! empty( $legacy_ids ) ) {
        $wpdb->query( "DELETE FROM {$sync_bookings_table} WHERE external_booking_room_id IN (" . implode( ',', array_map( 'intval', array_filter( $legacy_ids ) ) ) . ")" );
    }
    $wpdb->delete( $booking_rooms_table, [ 'booking_id' => $booking_id ], [ '%d' ] );
    $deleted = false !== $wpdb->delete( $bookings_table, [ 'id' => $booking_id ], [ '%d' ] );
    simple_hotel_crm_clear_calendar_cache();
    return $deleted;
}

function simple_hotel_crm_delete_guest( $guest_id ) {
    global $wpdb;
    $guest_id = absint( $guest_id );
    if ( $guest_id <= 0 ) {
        return false;
    }
    $bookings_table = simple_hotel_crm_bookings_table();
    $guests_table = simple_hotel_crm_guests_table();
    $booking_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$bookings_table} WHERE guest_id = %d", $guest_id ) );
    if ( $booking_count > 0 ) {
        return new WP_Error( 'guest_has_bookings', __( 'This guest cannot be deleted because bookings are still linked to them.', 'simple-hotel-crm' ) );
    }
    return false !== $wpdb->delete( $guests_table, [ 'id' => $guest_id ], [ '%d' ] );
}

function simple_hotel_crm_replace_booking_room_data( $booking_id, $data, $existing_booking = null ) {
    global $wpdb;

    $booking_id = absint( $booking_id );
    if ( $booking_id <= 0 ) {
        return new WP_Error( 'invalid_booking', __( 'Invalid booking.', 'simple-hotel-crm' ) );
    }

    $validated = simple_hotel_crm_validate_booking_form_data( $data );
    if ( is_wp_error( $validated ) ) {
        return $validated;
    }

    $sync_bookings_table = simple_hotel_crm_sync_bookings_table();
    $crm_rooms_table = simple_hotel_crm_rooms_table();
    $crm_bookings_table = simple_hotel_crm_bookings_table();
    $crm_booking_rooms_table = simple_hotel_crm_booking_rooms_table();
    $crm_booking_nights_table = simple_hotel_crm_booking_room_nights_table();
    $overlay_table = simple_hotel_crm_booking_overlay_table();
    $booking_notes_table = simple_hotel_crm_booking_notes_table();

    extract( $validated, EXTR_SKIP );
    $status_code = sanitize_text_field( (string) ( $data['status_code'] ?? 'confirmed' ) );
    $source_channel = sanitize_text_field( (string) ( $data['source_channel'] ?? 'direct' ) );
    $booking_note = sanitize_textarea_field( (string) ( $data['booking_note'] ?? '' ) );
    simple_hotel_crm_upsert_booking_note( $booking_id, $booking_note, null, null, 'booking' );
    $import_notes = sanitize_textarea_field( (string) ( $data['import_notes'] ?? '' ) );
    $source_created_at = ( '' !== $contacted_date ? $contacted_date : current_time( 'mysql' ) );

    $existing_rooms = $wpdb->get_results( $wpdb->prepare( "SELECT br.legacy_reserved_room_id, br.room_id FROM {$crm_booking_rooms_table} br WHERE br.booking_id = %d ORDER BY br.id ASC", $booking_id ), ARRAY_A );
    $legacy_ids = wp_list_pluck( $existing_rooms, 'legacy_reserved_room_id' );
    $overlay_map = [];
    foreach ( $existing_rooms as $existing_room ) {
        $room_id = (int) $existing_room['room_id'];
        if ( $room_id > 0 && ! isset( $overlay_map[ $room_id ] ) ) {
            $overlay_map[ $room_id ] = (int) $existing_room['legacy_reserved_room_id'];
        }
    }
    $calculated_room_lines = [];
    foreach ( $room_lines as $line ) {
        $crm_room_id = simple_hotel_crm_find_crm_room_id( $line['room_sync_id'] ?? 0 );
        $availability = simple_hotel_crm_check_wp_sync_room_availability( $crm_room_id, $check_in, $check_out );
        if ( is_wp_error( $availability ) ) {
            $current_match = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$crm_booking_rooms_table} WHERE booking_id = %d AND room_id = %d", $booking_id, $crm_room_id ) );
            if ( $current_match <= 0 ) {
                return $availability;
            }
        }
        $room = $crm_room_id > 0 ? $wpdb->get_row( $wpdb->prepare( "SELECT id, sync_room_id, external_room_id, room_code, room_name FROM {$crm_rooms_table} WHERE id = %d LIMIT 1", $crm_room_id ), ARRAY_A ) : null;
        if ( ! $room || $crm_room_id <= 0 ) {
            return new WP_Error( 'invalid_room', __( 'Please select a valid room.', 'simple-hotel-crm' ) );
        }
        $calculated_room_lines[] = array_merge(
            $line,
            simple_hotel_crm_calculate_room_pricing( $line, $nights, $crm_room_id, $source_channel ),
            [
                'crm_room_id' => $crm_room_id,
                'room' => $room,
            ]
        );
    }

    $wpdb->query( 'START TRANSACTION' );
    if ( ! empty( $legacy_ids ) ) {
        $wpdb->query( "DELETE FROM {$sync_bookings_table} WHERE external_booking_room_id IN (" . implode( ',', array_map( 'intval', array_filter( $legacy_ids ) ) ) . ")" );
    }
    $room_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$crm_booking_rooms_table} WHERE booking_id = %d", $booking_id ) );
    if ( ! empty( $room_ids ) ) {
        $wpdb->query( "DELETE FROM {$crm_booking_nights_table} WHERE booking_room_id IN (" . implode( ',', array_map( 'intval', $room_ids ) ) . ")" );
    }
    $wpdb->delete( $crm_booking_rooms_table, [ 'booking_id' => $booking_id ], [ '%d' ] );
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$booking_notes_table} WHERE booking_id = %d AND booking_room_id IS NOT NULL", $booking_id ) );

    $next_legacy_room_id = max( 1, (int) $wpdb->get_var( "SELECT COALESCE(MAX(external_booking_room_id), 0) + 1 FROM {$sync_bookings_table}" ) );
    if ( ! empty( $overlay_map ) ) {
        $next_legacy_room_id = max( $next_legacy_room_id, max( array_map( 'intval', $overlay_map ) ) + 1 );
    }
    $external_booking_id = $existing_booking ? (int) $existing_booking['id'] : $booking_id;
    $booking_adults = array_sum( array_column( $calculated_room_lines, 'adults' ) );
    $booking_children = array_sum( array_column( $calculated_room_lines, 'children' ) );
    $booking_babies = array_sum( array_column( $calculated_room_lines, 'babies' ) );
    $booking_room_rate = round( array_sum( array_column( $calculated_room_lines, 'room_rate_amount' ) ), 2 );
    $booking_extras = round( array_sum( array_column( $calculated_room_lines, 'extras_amount' ) ), 2 );
    $booking_tax = round( array_sum( array_column( $calculated_room_lines, 'tourist_tax_total' ) ), 2 );
    $booking_total = round( array_sum( array_column( $calculated_room_lines, 'total_amount' ) ), 2 );

    foreach ( $calculated_room_lines as $line ) {
        $crm_room_id = (int) $line['crm_room_id'];
        $room = $line['room'];
        $legacy_reserved_room_id = isset( $overlay_map[ $crm_room_id ] ) ? (int) $overlay_map[ $crm_room_id ] : $next_legacy_room_id++;
        $inserted = $wpdb->insert(
            $crm_booking_rooms_table,
            [
                'booking_id' => $booking_id,
                'room_id' => $crm_room_id,
                'legacy_reserved_room_id' => $legacy_reserved_room_id,
                'pricing_room_id' => $line['pricing_room_id'] > 0 ? $line['pricing_room_id'] : null,
                'occupancy_adults' => $line['occupancy_adults'],
                'guest_count' => $line['guest_count'],
                'adults' => $line['adults'],
                'children' => $line['children'],
                'babies' => $line['babies'],
                'base_price_amount' => $line['base_price_amount'],
                'discount_type' => $line['discount_type'],
                'discount_value' => $line['discount_value'],
                'discount_amount' => $line['discount_amount'],
                'subtotal_amount' => $line['subtotal_amount'],
                'commission_amount' => $line['commission_amount'],
                'room_rate_amount' => $line['room_rate_amount'],
                'extras_amount' => $line['extras_amount'],
                'tourist_tax_amount' => $line['tourist_tax_total'],
                'total_amount' => $line['total_amount'],
            ],
            [ '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%f', '%s', '%f', '%f', '%f', '%f', '%f', '%f', '%f', '%f' ]
        );
        if ( false === $inserted ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'booking_room_insert_failed', __( 'Could not save booking rooms.', 'simple-hotel-crm' ) );
        }
        $crm_booking_room_id = (int) $wpdb->insert_id;
        if ( isset( $overlay_map[ $crm_room_id ] ) && (int) $overlay_map[ $crm_room_id ] !== $legacy_reserved_room_id ) {
            simple_hotel_crm_copy_booking_overlay( (int) $overlay_map[ $crm_room_id ], $legacy_reserved_room_id, $booking_id, $crm_room_id );
        }
        $room_note = sanitize_textarea_field( (string) ( $line['room_note'] ?? '' ) );
        $extras_formula = sanitize_text_field( (string) ( $line['extras_formula'] ?? '' ) );
        $existing_overlay = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$overlay_table} WHERE reserved_room_id = %d LIMIT 1", $legacy_reserved_room_id ), ARRAY_A );
        $overlay_payload = [
            'booking_id' => $booking_id,
            'reserved_room_id' => $legacy_reserved_room_id,
            'room_id' => $crm_room_id,
            'booking_note' => $room_note,
            'extras_formula' => $extras_formula,
            'extras_total' => (float) $line['extras_amount'],
            'manual_guest_name' => (string) ( $existing_overlay['manual_guest_name'] ?? '' ),
            'manual_adults' => isset( $existing_overlay['manual_adults'] ) && '' !== (string) $existing_overlay['manual_adults'] ? (int) $existing_overlay['manual_adults'] : null,
            'manual_children' => isset( $existing_overlay['manual_children'] ) && '' !== (string) $existing_overlay['manual_children'] ? (int) $existing_overlay['manual_children'] : null,
            'manual_tarif' => isset( $existing_overlay['manual_tarif'] ) ? simple_hotel_crm_normalize_decimal( $existing_overlay['manual_tarif'] ) : null,
            'manual_commission' => isset( $existing_overlay['manual_commission'] ) ? simple_hotel_crm_normalize_decimal( $existing_overlay['manual_commission'] ) : null,
        ];
        $overlay_formats = [ '%d', '%d', '%d', '%s', '%s', '%f', '%s', '%d', '%d', '%f', '%f' ];
        if ( ! empty( $existing_overlay ) ) {
            $wpdb->update( $overlay_table, $overlay_payload, [ 'reserved_room_id' => $legacy_reserved_room_id ], $overlay_formats, [ '%d' ] );
        } else {
            $wpdb->insert( $overlay_table, $overlay_payload, $overlay_formats );
        }
        simple_hotel_crm_upsert_booking_note( $booking_id, $room_note, $crm_booking_room_id, null, 'room' );
        $base_price_nightly = simple_hotel_crm_distribute_amounts( $line['base_price_amount'], $nights );
        $discount_nightly = simple_hotel_crm_distribute_amounts( $line['discount_amount'], $nights );
        $subtotal_nightly = simple_hotel_crm_distribute_amounts( $line['subtotal_amount'], $nights );
        $commission_nightly = simple_hotel_crm_distribute_amounts( $line['commission_amount'], $nights );
        $room_rate_nightly = simple_hotel_crm_distribute_amounts( $line['room_rate_amount'], $nights );
        $extras_nightly = simple_hotel_crm_distribute_amounts( $line['extras_amount'], $nights );
        $tax_nightly = simple_hotel_crm_distribute_amounts( $line['tourist_tax_total'], $nights );
        for ( $i = 0; $i < $nights; $i++ ) {
            $stay_date = gmdate( 'Y-m-d', strtotime( $check_in . ' +' . $i . ' day' ) );
            $night_total = round( $room_rate_nightly[ $i ] + $extras_nightly[ $i ] + $tax_nightly[ $i ], 2 );
            $wpdb->insert(
                $crm_booking_nights_table,
                [
                    'booking_room_id' => $crm_booking_room_id,
                    'stay_date' => $stay_date,
                    'guest_count' => $line['guest_count'],
                    'adults' => $line['adults'],
                    'children' => $line['children'],
                    'babies' => $line['babies'],
                    'base_price_amount' => $base_price_nightly[ $i ],
                    'discount_amount' => $discount_nightly[ $i ],
                    'subtotal_amount' => $subtotal_nightly[ $i ],
                    'commission_amount' => $commission_nightly[ $i ],
                    'room_rate_amount' => $room_rate_nightly[ $i ],
                    'extras_amount' => $extras_nightly[ $i ],
                    'tourist_tax_amount' => $tax_nightly[ $i ],
                    'total_amount' => $night_total,
                ],
                [ '%d', '%s', '%d', '%d', '%d', '%d', '%f', '%f', '%f', '%f', '%f', '%f', '%f', '%f' ]
            );
            $wpdb->insert(
                $sync_bookings_table,
                [
                    'external_booking_id' => $external_booking_id,
                    'external_booking_room_id' => $legacy_reserved_room_id,
                    'external_room_id' => (int) $room['external_room_id'],
                    'room_sync_id' => (int) ( $room['sync_room_id'] ?? 0 ),
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
                    'guest_name' => trim( (string) ( $data['guest_name'] ?? '' ) ),
                    'phone' => sanitize_text_field( (string) ( $data['phone'] ?? '' ) ),
                    'import_notes' => $import_notes,
                    'invoice_ninja_client_id' => (string) ( $existing_booking['invoice_ninja_client_id'] ?? '' ),
                    'invoice_ninja_invoice_id' => (string) ( $existing_booking['invoice_ninja_invoice_id'] ?? '' ),
                    'source_created_at' => $source_created_at,
                ],
                [ '%d','%d','%d','%d','%s','%s','%s','%s','%d','%d','%d','%d','%f','%f','%f','%d','%s','%s','%s','%s','%s','%s','%s','%s','%s' ]
            );
        }
    }

    $wpdb->update(
        $crm_bookings_table,
        [
            'status_code' => $status_code,
            'source_channel' => $source_channel,
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
            'booking_note' => $booking_note,
            'internal_notes' => $import_notes,
        ],
        [ 'id' => $booking_id ],
        [ '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%f', '%f', '%f', '%f', '%s', '%s' ],
        [ '%d' ]
    );

    $wpdb->query( 'COMMIT' );

    if ( ! empty( $legacy_ids ) ) {
        $new_legacy_ids = [];
        foreach ( $room_lines as $line ) {
            $crm_room_id = (int) $line['room_sync_id'];
            if ( isset( $overlay_map[ $crm_room_id ] ) ) {
                $new_legacy_ids[] = (int) $overlay_map[ $crm_room_id ];
            }
        }
        foreach ( array_diff( array_map( 'intval', array_filter( $legacy_ids ) ), array_map( 'intval', array_filter( $new_legacy_ids ) ) ) as $stale_legacy_id ) {
            simple_hotel_crm_delete_booking_overlay( $stale_legacy_id );
        }
    }

    simple_hotel_crm_clear_calendar_cache();
    return true;
}

function simple_hotel_crm_merge_booking_com_rooms($booking_ids) {
    global $wpdb;
    
    // Start transaction
    $wpdb->query('START TRANSACTION');
    
    try {
        // Create new booking from the first booking in the list
        $first_booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . simple_hotel_crm_bookings_table() . " WHERE id = %d LIMIT 1",
            $booking_ids[0]
        ), ARRAY_A);
        
        // Create new booking
        $new_booking_id = simple_hotel_crm_create_booking_from_rooms($booking_ids);
        
        // Mark original rooms as processed
        foreach ($booking_ids as $booking_id) {
            $wpdb->update(
                simple_hotel_crm_bookings_table(),
                ['is_processed' => 1],
                ['id' => $booking_id],
                ['%d'],
                ['%d']
            );
        }
        
        // Commit transaction
        $wpdb->query('COMMIT');
        
        return $new_booking_id;
    } catch (Exception $e) {
        // Rollback on error
        $wpdb->query('ROLLBACK');
        throw $e;
    }
}

function simple_hotel_crm_create_booking_from_rooms($booking_ids) {
    global $wpdb;
    
    // Start transaction
    $wpdb->query('START TRANSACTION');
    
    try {
        // Get all rooms for these bookings
        $rooms = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . simple_hotel_crm_booking_rooms_table() . " WHERE booking_id IN (" . implode(',', array_map('intval', $booking_ids)) . ")"
        ), ARRAY_A);
        
        // Get the first booking to use as template
        $first_booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . simple_hotel_crm_bookings_table() . " WHERE id = %d LIMIT 1",
            $booking_ids[0]
        ), ARRAY_A);
        
        // Create new booking
        $new_booking = array_merge($first_booking, [
            'id' => 0, // Will be auto-incremented
            'is_processed' => 0,
            'total_amount' => array_sum(array_column($rooms, 'total_amount')),
            'room_rate_amount' => array_sum(array_column($rooms, 'room_rate_amount')),
            'extras_amount' => array_sum(array_column($rooms, 'extras_amount')),
            'tourist_tax_amount' => array_sum(array_column($rooms, 'tourist_tax_amount')),
            'adults' => array_sum(array_column($rooms, 'adults')),
            'children' => array_sum(array_column($rooms, 'children')),
            'babies' => array_sum(array_column($rooms, 'babies'))
        ]);
        
        // Insert new booking
        $wpdb->insert(simple_hotel_crm_bookings_table(), $new_booking);
        $new_booking_id = $wpdb->insert_id;
        
        // Add all rooms to new booking
        foreach ($rooms as $room) {
            $room['id'] = 0; // Will be auto-incremented
            $room['booking_id'] = $new_booking_id;
            $wpdb->insert(simple_hotel_crm_booking_rooms_table(), $room);
            $new_room_id = $wpdb->insert_id;
            
            // Copy room nights
            $room_nights = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM " . simple_hotel_crm_booking_room_nights_table() . " WHERE booking_room_id = %d",
                $room['id']
            ), ARRAY_A);
            
            foreach ($room_nights as $night) {
                $night['id'] = 0;
                $night['booking_room_id'] = $new_room_id; // ID of the newly inserted room
                $wpdb->insert(simple_hotel_crm_booking_room_nights_table(), $night);
            }
            
            // Copy booking overlay
            $overlay = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM " . simple_hotel_crm_booking_overlay_table() . " WHERE reserved_room_id = %d LIMIT 1",
                $room['legacy_reserved_room_id']
            ), ARRAY_A);
            
            if ($overlay) {
                unset($overlay['id']);
                $overlay['reserved_room_id'] = $room['legacy_reserved_room_id']; // Keep the same reserved_room_id
                $overlay['booking_id'] = $new_booking_id;
                $wpdb->insert(simple_hotel_crm_booking_overlay_table(), $overlay);
            }
            
            // Copy booking notes
            $notes = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM " . simple_hotel_crm_booking_notes_table() . " WHERE booking_id = %d AND booking_room_id = %d",
                $room['booking_id'], $room['id']
            ), ARRAY_A);
            
            foreach ($notes as $note) {
                unset($note['id']);
                $note['booking_id'] = $new_booking_id;
                $note['booking_room_id'] = $new_room_id;
                $wpdb->insert(simple_hotel_crm_booking_notes_table(), $note);
            }
            
            // Copy booking adjustments
            $adjustments = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM " . simple_hotel_crm_booking_adjustments_table() . " WHERE booking_id = %d AND booking_room_id = %d",
                $room['booking_id'], $room['id']
            ), ARRAY_A);
            
            foreach ($adjustments as $adjustment) {
                unset($adjustment['id']);
                $adjustment['booking_id'] = $new_booking_id;
                $adjustment['booking_room_id'] = $new_room_id;
                $wpdb->insert(simple_hotel_crm_booking_adjustments_table(), $adjustment);
            }
        }
        
        // Commit transaction
        $wpdb->query('COMMIT');
        
        return $new_booking_id;
    } catch (Exception $e) {
        // Rollback on error
        $wpdb->query('ROLLBACK');
        throw $e;
    }
}

function simple_hotel_crm_render_booking_merge_page() {
    simple_hotel_crm_render_booking_merges_page();
}

function simple_hotel_crm_render_booking_detail_page() {
    if ( ! simple_hotel_crm_user_can_access() ) {
        wp_die( esc_html__( 'You do not have permission to access this page.', 'simple-hotel-crm' ) );
    }

    global $wpdb;
    $booking_id = absint( $_GET['booking_id'] ?? 0 );
    $bookings_table = simple_hotel_crm_bookings_table();
    $guests_table = simple_hotel_crm_guests_table();
    $booking_rooms_table = simple_hotel_crm_booking_rooms_table();
    $rooms_table = simple_hotel_crm_rooms_table();

    $booking = $wpdb->get_row( $wpdb->prepare( "SELECT b.*, g.first_name, g.last_name, g.phone, g.email, g.id AS guest_id FROM {$bookings_table} b JOIN {$guests_table} g ON g.id = b.guest_id WHERE b.id = %d AND b.is_deleted = 0 LIMIT 1", $booking_id ), ARRAY_A );
    $posted_room_lines = wp_unslash( $_POST['room_lines'] ?? [] );
    if ( isset( $_POST['simple_hotel_crm_add_booking_room'] ) ) {
        $posted_room_lines[] = [ 'room_sync_id' => '', 'room_note' => '', 'adults' => 2, 'children' => 0, 'babies' => 0, 'room_rate_amount' => '0.00', 'discount_type' => 'none', 'discount_value' => '0.00', 'extras_formula' => '', 'extras_amount' => '0.00' ];
    }
    if ( isset( $_POST['simple_hotel_crm_save_booking'] ) && $booking ) {
        check_admin_referer( 'simple_hotel_crm_save_booking_' . $booking_id );
        $posted_room_lines = array_values( array_filter( $posted_room_lines, function( $line ) {
            return empty( $line['remove'] );
        } ) );
        $booking_form_data = [
            'guest_name' => trim( (string) $booking['first_name'] . ' ' . (string) $booking['last_name'] ),
            'phone' => (string) $booking['phone'],
            'check_in' => sanitize_text_field( wp_unslash( $_POST['check_in_date'] ?? '' ) ),
            'check_out' => sanitize_text_field( wp_unslash( $_POST['check_out_date'] ?? '' ) ),
            'source_channel' => sanitize_text_field( wp_unslash( $_POST['source_channel'] ?? '' ) ),
            'status_code' => sanitize_text_field( wp_unslash( $_POST['status_code'] ?? '' ) ),
            'contacted_date' => sanitize_text_field( wp_unslash( $_POST['contacted_date'] ?? '' ) ),
            'booking_note' => array_key_exists( 'booking_note', $_POST ) ? sanitize_textarea_field( wp_unslash( $_POST['booking_note'] ) ) : simple_hotel_crm_get_booking_note_text( $booking_id ),
            'import_notes' => sanitize_textarea_field( wp_unslash( $_POST['internal_notes'] ?? '' ) ),
            'room_lines' => $posted_room_lines,
        ];
        $result = simple_hotel_crm_replace_booking_room_data( $booking_id, $booking_form_data, $booking );
        if ( is_wp_error( $result ) ) {
            echo '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
        } else {
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Booking updated.', 'simple-hotel-crm' ) . '</p></div>';
        }
        $booking = $wpdb->get_row( $wpdb->prepare( "SELECT b.*, g.first_name, g.last_name, g.phone, g.email, g.id AS guest_id FROM {$bookings_table} b JOIN {$guests_table} g ON g.id = b.guest_id WHERE b.id = %d AND b.is_deleted = 0 LIMIT 1", $booking_id ), ARRAY_A );
    }
    if ( ! $booking ) {
        wp_die( esc_html__( 'Booking not found.', 'simple-hotel-crm' ) );
    }

    $booking_notes_rows = $wpdb->get_results( $wpdb->prepare( "SELECT booking_room_id, stay_date, note_scope, note_text FROM " . simple_hotel_crm_booking_notes_table() . " WHERE booking_id = %d ORDER BY booking_room_id ASC, stay_date ASC", $booking_id ), ARRAY_A );
    $booking_adjustment_rows = $wpdb->get_results( $wpdb->prepare( "SELECT booking_room_id, stay_date, adjustment_kind, formula, amount FROM " . simple_hotel_crm_booking_adjustments_table() . " WHERE booking_id = %d ORDER BY booking_room_id ASC, stay_date ASC", $booking_id ), ARRAY_A );
    $booking_note_summary = [ 'booking' => '', 'rooms' => [], 'days' => [] ];
    $booking_extras_summary = [];
    foreach ( $booking_notes_rows as $note_row ) {
        $scope = (string) ( $note_row['note_scope'] ?? '' );
        $room_note_room_id = (int) ( $note_row['booking_room_id'] ?? 0 );
        $stay_date = (string) ( $note_row['stay_date'] ?? '' );
        $note_text = (string) ( $note_row['note_text'] ?? '' );
        if ( 'booking' === $scope && 0 === $room_note_room_id && '' === $stay_date ) {
            $booking_note_summary['booking'] = $note_text;
        } elseif ( '' !== $stay_date && $room_note_room_id > 0 ) {
            $booking_note_summary['days'][] = [ 'booking_room_id' => $room_note_room_id, 'stay_date' => $stay_date, 'note_text' => $note_text ];
        } elseif ( $room_note_room_id > 0 ) {
            $booking_note_summary['rooms'][ $room_note_room_id ] = $note_text;
        }
    }
    foreach ( $booking_adjustment_rows as $adjustment_row ) {
        if ( 'extras' !== (string) ( $adjustment_row['adjustment_kind'] ?? '' ) ) {
            continue;
        }
        $booking_extras_summary[] = [
            'booking_room_id' => (int) ( $adjustment_row['booking_room_id'] ?? 0 ),
            'stay_date' => (string) ( $adjustment_row['stay_date'] ?? '' ),
            'formula' => (string) ( $adjustment_row['formula'] ?? '' ),
            'amount' => (float) ( $adjustment_row['amount'] ?? 0 ),
        ];
    }

    $room_pricing_table = simple_hotel_crm_room_pricing_table();
    $overlay_table = simple_hotel_crm_booking_overlay_table();
    $rooms = $wpdb->get_results( $wpdb->prepare( "SELECT br.*, r.room_name, r.room_code, r.sync_room_id, o.booking_note AS room_note_legacy, o.extras_formula FROM {$booking_rooms_table} br JOIN {$rooms_table} r ON r.id = br.room_id LEFT JOIN {$overlay_table} o ON o.reserved_room_id = br.legacy_reserved_room_id WHERE br.booking_id = %d ORDER BY r.sort_order ASC, r.room_name ASC", $booking_id ), ARRAY_A );
    foreach ( $rooms as &$room ) {
        $room['room_note'] = simple_hotel_crm_get_booking_note_text( $booking_id, (int) $room['id'] );
        if ( '' === (string) $room['room_note'] ) {
            $room['room_note'] = (string) ( $room['room_note_legacy'] ?? '' );
        }
    }
    unset( $room );
    foreach ( $rooms as &$room ) {
        $room['room_sync_id'] = (string) $room['room_id'];
    }
    unset( $room );
    if ( ! empty( $posted_room_lines ) ) {
        foreach ( $posted_room_lines as $index => $posted_room_line ) {
            if ( isset( $rooms[ $index ] ) ) {
                $rooms[ $index ] = array_merge( $rooms[ $index ], $posted_room_line );
            } else {
                $rooms[] = array_merge( [ 'id' => '', 'room_name' => '', 'sync_room_id' => '' ], $posted_room_line );
            }
        }
    }
    $available_rooms = simple_hotel_crm_get_room_options( (string) $booking['check_in_date'], (string) $booking['check_out_date'] );
    if ( ! is_array( $available_rooms ) ) {
        $available_rooms = [];
    }
    $current_room_ids = array_map( 'intval', wp_list_pluck( $rooms, 'room_id' ) );
    if ( ! empty( $current_room_ids ) ) {
        $current_rooms = $wpdb->get_results( "SELECT id, sync_room_id, room_name, room_code FROM {$rooms_table} WHERE id IN (" . implode( ',', $current_room_ids ) . ')', ARRAY_A );
        $available_by_id = [];
        foreach ( $available_rooms as $available_room ) {
            $available_by_id[ (int) $available_room['id'] ] = $available_room;
        }
        foreach ( $current_rooms as $current_room ) {
            $available_by_id[ (int) $current_room['id'] ] = $current_room;
        }
        $available_rooms = array_values( $available_by_id );
        usort( $available_rooms, static function( $a, $b ) {
            return strcmp( (string) $a['room_name'], (string) $b['room_name'] );
        } );
    }
    $room_pricing_rows = $wpdb->get_results( "SELECT room_id, occupancy_adults, price_amount, active FROM {$room_pricing_table} WHERE active = 1 ORDER BY room_id ASC, occupancy_adults ASC", ARRAY_A );
    $room_pricing_map = [];
    foreach ( $room_pricing_rows as $pricing_row ) {
        $room_pricing_map[ (int) $pricing_row['room_id'] ][ (int) $pricing_row['occupancy_adults'] ] = (float) $pricing_row['price_amount'];
    }

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__( 'Booking Detail', 'simple-hotel-crm' ) . ' #' . esc_html( (string) $booking_id ) . '</h1>';
    echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=simple-hotel-crm-bookings' ) ) . '">← ' . esc_html__( 'Back to Bookings', 'simple-hotel-crm' ) . '</a></p>';
    echo '<form method="post">';
    wp_nonce_field( 'simple_hotel_crm_save_booking_' . $booking_id );
    echo '<table class="form-table">';
    echo '<tr><th>' . esc_html__( 'Guest / Status / Channel', 'simple-hotel-crm' ) . '</th><td><div class="simple-hotel-crm-admin-inline-fields" style="display:flex;gap:12px;flex-wrap:nowrap;align-items:flex-end;"><label style="flex:1 1 34%;min-width:220px;">' . esc_html__( 'Guest', 'simple-hotel-crm' ) . '<br><a href="' . esc_url( admin_url( 'admin.php?page=simple-hotel-crm-guest-detail&guest_id=' . absint( $booking['guest_id'] ) ) ) . '">' . esc_html( trim( (string) $booking['first_name'] . ' ' . (string) $booking['last_name'] ) ) . '</a></label><label style="flex:1 1 33%;min-width:180px;">' . esc_html__( 'Status', 'simple-hotel-crm' ) . '<br><select name="status_code">';
    foreach ( simple_hotel_crm_get_booking_status_options() as $code => $label ) {
        echo '<option value="' . esc_attr( $code ) . '"' . selected( (string) $booking['status_code'], $code, false ) . '>' . esc_html( $label ) . '</option>';
    }
    echo '</select></label><label style="flex:1 1 33%;min-width:180px;">' . esc_html__( 'Channel', 'simple-hotel-crm' ) . '<br><select name="source_channel">';
    foreach ( simple_hotel_crm_get_booking_channel_options() as $code => $label ) {
        echo '<option value="' . esc_attr( $code ) . '"' . selected( (string) $booking['source_channel'], $code, false ) . '>' . esc_html( $label ) . '</option>';
    }
    echo '</select></label></div></td></tr>';
    echo '<tr><th>' . esc_html__( 'Dates', 'simple-hotel-crm' ) . '</th><td><div class="simple-hotel-crm-admin-inline-fields" style="display:flex;gap:12px;flex-wrap:nowrap;align-items:flex-end;"><label style="flex:1 1 33%;min-width:180px;">' . esc_html__( 'Contacted', 'simple-hotel-crm' ) . '<br><input type="date" name="contacted_date" value="' . esc_attr( (string) $booking['contacted_date'] ) . '" /></label><label style="flex:1 1 33%;min-width:180px;">' . esc_html__( 'Check-in', 'simple-hotel-crm' ) . '<br><input type="date" name="check_in_date" value="' . esc_attr( (string) $booking['check_in_date'] ) . '" /></label><label style="flex:1 1 33%;min-width:180px;">' . esc_html__( 'Check-out', 'simple-hotel-crm' ) . '<br><input type="date" name="check_out_date" value="' . esc_attr( (string) $booking['check_out_date'] ) . '" /></label></div></td></tr>';
    echo '<tr><th>' . esc_html__( 'Internal notes', 'simple-hotel-crm' ) . '</th><td><textarea name="internal_notes" rows="5" class="large-text">' . esc_textarea( (string) $booking['internal_notes'] ) . '</textarea></td></tr>';
    echo '</table>';
    echo '<h2>' . esc_html__( 'Stored Notes', 'simple-hotel-crm' ) . '</h2>';
    echo '<div class="card" style="padding:12px;margin:12px 0;max-width:100%;">';
    echo '<p><strong>' . esc_html__( 'Booking note', 'simple-hotel-crm' ) . ':</strong> ' . esc_html( $booking_note_summary['booking'] ?: __( '(empty)', 'simple-hotel-crm' ) ) . '</p>';
    if ( ! empty( $booking_note_summary['rooms'] ) ) {
        echo '<p><strong>' . esc_html__( 'Room notes', 'simple-hotel-crm' ) . ':</strong></p><ul style="margin-left:20px;">';
        foreach ( $rooms as $room ) {
            $room_note_text = $booking_note_summary['rooms'][ (int) $room['id'] ] ?? '';
            if ( '' === $room_note_text ) { continue; }
            echo '<li><strong>' . esc_html( (string) $room['room_code'] . ' - ' . (string) $room['room_name'] ) . ':</strong> ' . esc_html( $room_note_text ) . '</li>';
        }
        echo '</ul>';
    }
    if ( ! empty( $booking_note_summary['days'] ) ) {
        echo '<p><strong>' . esc_html__( 'Day notes', 'simple-hotel-crm' ) . ':</strong></p><ul style="margin-left:20px;">';
        foreach ( $booking_note_summary['days'] as $day_note ) {
            $room_label = '';
            foreach ( $rooms as $room ) {
                if ( (int) $room['id'] === (int) $day_note['booking_room_id'] ) {
                    $room_label = (string) $room['room_code'] . ' - ' . (string) $room['room_name'];
                    break;
                }
            }
            echo '<li><strong>' . esc_html( $day_note['stay_date'] ) . ( $room_label ? ' · ' . esc_html( $room_label ) : '' ) . ':</strong> ' . esc_html( (string) $day_note['note_text'] ) . '</li>';
        }
        echo '</ul>';
    }
    echo '</div>';
    echo '<h2>' . esc_html__( 'Stored Extras', 'simple-hotel-crm' ) . '</h2>';
    echo '<div class="card" style="padding:12px;margin:12px 0;max-width:100%;">';
    if ( empty( $booking_extras_summary ) ) {
        echo '<p>' . esc_html__( 'No day-specific extras saved.', 'simple-hotel-crm' ) . '</p>';
    } else {
        echo '<ul style="margin-left:20px;">';
        foreach ( $booking_extras_summary as $extra_row ) {
            $room_label = '';
            foreach ( $rooms as $room ) {
                if ( (int) $room['id'] === (int) $extra_row['booking_room_id'] ) {
                    $room_label = (string) $room['room_code'] . ' - ' . (string) $room['room_name'];
                    break;
                }
            }
            $extra_text = number_format( (float) $extra_row['amount'], 2, '.', '' );
            if ( '' !== (string) $extra_row['formula'] ) {
                $extra_text .= ' (' . (string) $extra_row['formula'] . ')';
            }
            echo '<li><strong>' . esc_html( $extra_row['stay_date'] ) . ( $room_label ? ' · ' . esc_html( $room_label ) : '' ) . ':</strong> ' . esc_html( $extra_text ) . '</li>';
        }
        echo '</ul>';
    }
    echo '</div>';
    echo '<h2>' . esc_html__( 'Rooms', 'simple-hotel-crm' ) . '</h2>';
    echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Remove', 'simple-hotel-crm' ) . '</th><th>ID</th><th>' . esc_html__( 'Room', 'simple-hotel-crm' ) . '</th><th>A</th><th>C</th><th>BB</th><th>' . esc_html__( 'Rate', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Discount', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Discount value', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Extras total', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Tax', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Commission', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Total', 'simple-hotel-crm' ) . '</th></tr></thead><tbody>';
    foreach ( $rooms as $index => $room ) {
        echo '<tr>';
        echo '<td><label><input type="checkbox" name="room_lines[' . esc_attr( $index ) . '][remove]" value="1" /> ' . esc_html__( 'Remove', 'simple-hotel-crm' ) . '</label></td>';
        echo '<td>' . esc_html( (string) $room['id'] ) . '</td>';
        echo '<td><select name="room_lines[' . esc_attr( $index ) . '][room_sync_id]">';
        echo '<option value="">' . esc_html__( 'Select room', 'simple-hotel-crm' ) . '</option>';
        foreach ( $available_rooms as $available_room ) {
            $label = (string) $available_room['room_code'] . ' - ' . (string) $available_room['room_name'];
            echo '<option value="' . esc_attr( (string) $available_room['id'] ) . '"' . selected( (string) ( $room['sync_room_id'] ?? $room['room_sync_id'] ?? '' ), (string) $available_room['id'], false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></td>';
        echo '<td><input type="number" min="0" style="width:44px;" name="room_lines[' . esc_attr( $index ) . '][adults]" value="' . esc_attr( (string) ( $room['adults'] ?? 0 ) ) . '" /></td>';
        echo '<td><input type="number" min="0" style="width:44px;" name="room_lines[' . esc_attr( $index ) . '][children]" value="' . esc_attr( (string) ( $room['children'] ?? 0 ) ) . '" /></td>';
        echo '<td><input type="number" min="0" style="width:44px;" name="room_lines[' . esc_attr( $index ) . '][babies]" value="' . esc_attr( (string) ( $room['babies'] ?? 0 ) ) . '" /></td>';
        echo '<td><input type="text" style="width:80px;" name="room_lines[' . esc_attr( $index ) . '][room_rate_amount]" value="' . esc_attr( isset( $room['room_rate_amount'] ) ? number_format( (float) $room['room_rate_amount'], 2, '.', '' ) : '0.00' ) . '" /></td>';
        echo '<td><select name="room_lines[' . esc_attr( $index ) . '][discount_type]" style="width:92px;"><option value="none"' . selected( (string) ( $room['discount_type'] ?? 'none' ), 'none', false ) . '>None</option><option value="percent"' . selected( (string) ( $room['discount_type'] ?? 'none' ), 'percent', false ) . '>Percent</option><option value="amount"' . selected( (string) ( $room['discount_type'] ?? 'none' ), 'amount', false ) . '>Amount</option></select></td>';
        echo '<td><input type="text" style="width:80px;" name="room_lines[' . esc_attr( $index ) . '][discount_value]" value="' . esc_attr( isset( $room['discount_value'] ) ? number_format( (float) $room['discount_value'], 2, '.', '' ) : '0.00' ) . '" /></td>';
        echo '<td><input type="text" style="width:80px;" name="room_lines[' . esc_attr( $index ) . '][extras_amount]" value="' . esc_attr( isset( $room['extras_amount'] ) ? number_format( (float) $room['extras_amount'], 2, '.', '' ) : '0.00' ) . '" /></td>';
        echo '<td>' . esc_html( isset( $room['tourist_tax_amount'] ) ? number_format( (float) $room['tourist_tax_amount'], 2, '.', '' ) : '' ) . '</td>';
        echo '<td><span class="simple-hotel-crm-line-commission-preview">' . esc_html( ( isset( $room['commission_amount'] ) && (float) $room['commission_amount'] > 0 ) ? number_format( (float) $room['commission_amount'], 2, '.', '' ) : '' ) . '</span></td>';
        echo '<td><span class="simple-hotel-crm-line-total-preview">' . esc_html( isset( $room['total_amount'] ) ? number_format( (float) $room['total_amount'], 2, '.', '' ) : '' ) . '</span></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    echo '<p><strong>' . esc_html__( 'Booking total preview', 'simple-hotel-crm' ) . ':</strong> <span class="simple-hotel-crm-booking-total-preview">0.00</span></p>';
    submit_button( __( 'Add room line', 'simple-hotel-crm' ), 'secondary', 'simple_hotel_crm_add_booking_room', false );
    echo ' ';
    submit_button( __( 'Save Booking', 'simple-hotel-crm' ), 'primary', 'simple_hotel_crm_save_booking', false );
    echo '</form>';
    $room_pricing_json = wp_json_encode( $room_pricing_map );
    $booking_com_commission_percent_json = wp_json_encode( (float) get_option( 'simple_hotel_crm_booking_com_commission_percent', 15 ) );
    echo <<<HTML
<script>
window.simpleHotelCrmRoomPricing = {$room_pricing_json};
window.simpleHotelCrmBookingComCommissionPercent = {$booking_com_commission_percent_json};
(function(){
  function num(v){v=(v||"0").toString().replace(/,/g, ".");var n=parseFloat(v);return isNaN(n)?0:n}
  function commissionPercent(channel){ return channel === 'booking_com' ? num(window.simpleHotelCrmBookingComCommissionPercent || 0) : 0; }
  function rateSource(row, nights){
    var room=row.querySelector("select[name*='[room_sync_id]']");
    var adults=row.querySelector("input[name*='[adults]']");
    var children=row.querySelector("input[name*='[children]']");
    var occupancy=(parseInt(adults&&adults.value||0,10)||0)+(parseInt(children&&children.value||0,10)||0);
    var base=((window.simpleHotelCrmRoomPricing||{})[room&&room.value]||{})[occupancy]||0;
    return {base:base, total:base>0?(base*nights):0};
  }
  function upd(forceAuto){
    var form=document.querySelector(".wrap form");if(!form)return;
    var ci=form.querySelector('[name="check_in_date"]');
    var co=form.querySelector('[name="check_out_date"]');
    var totalPreview=form.querySelector('.simple-hotel-crm-booking-total-preview');
    var channelField=form.querySelector('[name="source_channel"]');
    var bookingTotal=0;
    var nights=1;
    if(ci&&co&&ci.value&&co.value){nights=Math.max(1,Math.round((new Date(co.value)-new Date(ci.value))/86400000));}
    form.querySelectorAll("tbody tr").forEach(function(tr){
      var room=tr.querySelector("select[name*='[room_sync_id]']");
      var adults=tr.querySelector("input[name*='[adults]']");
      var rate=tr.querySelector("input[name*='[room_rate_amount]']");
      var dtype=tr.querySelector("select[name*='[discount_type]']");
      var dval=tr.querySelector("input[name*='[discount_value]']");
      var extras=tr.querySelector("input[name*='[extras_amount]']");
      var preview=tr.querySelector('.simple-hotel-crm-line-total-preview');
      var commissionPreview=tr.querySelector('.simple-hotel-crm-line-commission-preview');
      if(!room||!adults||!rate||!preview)return;
      var src=rateSource(tr,nights);
      if(src.base>0 && (forceAuto || rate.dataset.manualOverride!=='1')){ rate.value=src.total.toFixed(2); }
      var roomRate=num(rate.value);
      var discount=0;
      if(dtype&&dtype.value==='percent')discount=roomRate*(Math.min(100,num(dval&&dval.value))/100);
      if(dtype&&dtype.value==='amount')discount=Math.min(roomRate,num(dval&&dval.value));
      var netRoom=Math.max(0,roomRate-discount);
      var tax=(parseInt(adults.value||0,10)*0.8*nights);
      var total=netRoom+num(extras&&extras.value)+tax;
      var commission=netRoom*(commissionPercent(channelField&&channelField.value)/100);
      bookingTotal+=total;
      preview.textContent=total.toFixed(2);
      if(commissionPreview){commissionPreview.textContent=commission.toFixed(2);} 
    });
    if(totalPreview){totalPreview.textContent=bookingTotal.toFixed(2);}
  }
  document.addEventListener('input',function(e){
    if(e.target && e.target.matches("input[name*='[room_rate_amount]']")){ e.target.dataset.manualOverride='1'; }
    upd(false);
  });
  document.addEventListener('change',function(e){
    if(e.target && e.target.matches("select[name*='[room_sync_id]'], input[name*='[adults]'], input[name*='[children]']")){
      var row=e.target.closest('tr');
      var rate=row&&row.querySelector("input[name*='[room_rate_amount]']");
      if(rate){ delete rate.dataset.manualOverride; }
      upd(true);
      return;
    }
    if(e.target && e.target.matches('[name="check_in_date"], [name="check_out_date"]')){
      document.querySelectorAll("input[name*='[room_rate_amount]']").forEach(function(rate){ if(rate.dataset.manualOverride!=='1'){ delete rate.dataset.manualOverride; } });
      upd(true);
      return;
    }
    upd(false);
  });
  upd(false);
})();
</script>
HTML;
    echo '</div>';
}

function simple_hotel_crm_render_guest_detail_page() {
    if ( ! simple_hotel_crm_user_can_access() ) {
        wp_die( esc_html__( 'You do not have permission to access this page.', 'simple-hotel-crm' ) );
    }

    global $wpdb;
    $guest_id = absint( $_GET['guest_id'] ?? 0 );
    $guests_table = simple_hotel_crm_guests_table();
    $bookings_table = simple_hotel_crm_bookings_table();

    if ( isset( $_POST['simple_hotel_crm_save_guest'] ) ) {
        check_admin_referer( 'simple_hotel_crm_save_guest_' . $guest_id );
        $payload = [
            'first_name' => sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) ),
            'last_name' => sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) ),
            'email' => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
            'phone' => sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ),
            'address_line_1' => sanitize_text_field( wp_unslash( $_POST['address_line_1'] ?? '' ) ),
            'address_line_2' => sanitize_text_field( wp_unslash( $_POST['address_line_2'] ?? '' ) ),
            'city' => sanitize_text_field( wp_unslash( $_POST['city'] ?? '' ) ),
            'postcode' => sanitize_text_field( wp_unslash( $_POST['postcode'] ?? '' ) ),
            'country' => sanitize_text_field( wp_unslash( $_POST['country'] ?? '' ) ),
            'notes' => sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) ),
        ];
        if ( $guest_id > 0 ) {
            $wpdb->update( $guests_table, $payload, [ 'id' => $guest_id ], [ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ], [ '%d' ] );
        } else {
            $wpdb->insert( $guests_table, $payload, [ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ] );
            $guest_id = (int) $wpdb->insert_id;
        }
        echo '<div class="notice notice-success"><p>' . esc_html__( 'Guest saved.', 'simple-hotel-crm' ) . '</p></div>';
    }

    $guest = $guest_id > 0 ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$guests_table} WHERE id = %d AND is_deleted = 0 LIMIT 1", $guest_id ), ARRAY_A ) : [ 'first_name' => '', 'last_name' => '', 'email' => '', 'phone' => '', 'address_line_1' => '', 'address_line_2' => '', 'city' => '', 'postcode' => '', 'country' => '', 'notes' => '' ];
    $bookings = $guest_id > 0 ? $wpdb->get_results( $wpdb->prepare( "SELECT id, check_in_date, check_out_date, status_code, total_amount FROM {$bookings_table} WHERE guest_id = %d AND is_deleted = 0 ORDER BY check_in_date DESC", $guest_id ), ARRAY_A ) : [];
    echo '<div class="wrap">';
    echo '<h1>' . esc_html__( $guest_id > 0 ? 'Guest Detail' : 'Add Guest', 'simple-hotel-crm' ) . '</h1>';
    echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=simple-hotel-crm-guests' ) ) . '">← ' . esc_html__( 'Back to Guests', 'simple-hotel-crm' ) . '</a></p>';
    echo '<form method="post">';
    wp_nonce_field( 'simple_hotel_crm_save_guest_' . $guest_id );
    echo '<table class="form-table">';
    echo '<tr><th>' . esc_html__( 'First name', 'simple-hotel-crm' ) . '</th><td><input type="text" name="first_name" value="' . esc_attr( (string) $guest['first_name'] ) . '" class="regular-text" /></td></tr>';
    echo '<tr><th>' . esc_html__( 'Last name', 'simple-hotel-crm' ) . '</th><td><input type="text" name="last_name" value="' . esc_attr( (string) $guest['last_name'] ) . '" class="regular-text" /></td></tr>';
    echo '<tr><th>' . esc_html__( 'Email', 'simple-hotel-crm' ) . '</th><td><input type="email" name="email" value="' . esc_attr( (string) $guest['email'] ) . '" class="regular-text" /></td></tr>';
    echo '<tr><th>' . esc_html__( 'Phone', 'simple-hotel-crm' ) . '</th><td><input type="text" name="phone" value="' . esc_attr( (string) $guest['phone'] ) . '" class="regular-text" /></td></tr>';
    echo '<tr><th>' . esc_html__( 'Address line 1', 'simple-hotel-crm' ) . '</th><td><input type="text" name="address_line_1" value="' . esc_attr( (string) $guest['address_line_1'] ) . '" class="regular-text" /></td></tr>';
    echo '<tr><th>' . esc_html__( 'Address line 2', 'simple-hotel-crm' ) . '</th><td><input type="text" name="address_line_2" value="' . esc_attr( (string) $guest['address_line_2'] ) . '" class="regular-text" /></td></tr>';
    echo '<tr><th>' . esc_html__( 'City', 'simple-hotel-crm' ) . '</th><td><input type="text" name="city" value="' . esc_attr( (string) $guest['city'] ) . '" class="regular-text" /></td></tr>';
    echo '<tr><th>' . esc_html__( 'Postcode', 'simple-hotel-crm' ) . '</th><td><input type="text" name="postcode" value="' . esc_attr( (string) $guest['postcode'] ) . '" class="regular-text" /></td></tr>';
    echo '<tr><th>' . esc_html__( 'Country', 'simple-hotel-crm' ) . '</th><td><input type="text" name="country" value="' . esc_attr( (string) $guest['country'] ) . '" class="regular-text" /></td></tr>';
    echo '<tr><th>' . esc_html__( 'Notes', 'simple-hotel-crm' ) . '</th><td><textarea name="notes" rows="5" class="large-text">' . esc_textarea( (string) $guest['notes'] ) . '</textarea></td></tr>';
    echo '</table>';
    submit_button( __( 'Save Guest', 'simple-hotel-crm' ), 'primary', 'simple_hotel_crm_save_guest' );
    echo '</form>';
    if ( $guest_id > 0 ) {
        echo '<h2>' . esc_html__( 'Bookings', 'simple-hotel-crm' ) . '</h2><table class="widefat striped"><thead><tr><th>ID</th><th>' . esc_html__( 'Check-in', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Check-out', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Status', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Total', 'simple-hotel-crm' ) . '</th></tr></thead><tbody>';
        if ( empty( $bookings ) ) {
            echo '<tr><td colspan="5">' . esc_html__( 'No bookings found.', 'simple-hotel-crm' ) . '</td></tr>';
        } else {
            foreach ( $bookings as $booking ) {
                echo '<tr><td><a href="' . esc_url( admin_url( 'admin.php?page=simple-hotel-crm-booking-detail&booking_id=' . absint( $booking['id'] ) ) ) . '">' . esc_html( (string) $booking['id'] ) . '</a></td><td>' . esc_html( (string) $booking['check_in_date'] ) . '</td><td>' . esc_html( (string) $booking['check_out_date'] ) . '</td><td>' . esc_html( (string) $booking['status_code'] ) . '</td><td>' . esc_html( number_format( (float) $booking['total_amount'], 2, '.', '' ) ) . '</td></tr>';
            }
        }
        echo '</tbody></table>';
    }
    echo '</div>';
}

function simple_hotel_crm_render_add_booking_page() {
    if ( ! simple_hotel_crm_user_can_access() ) {
        wp_die( esc_html__( 'You do not have permission to access this page.', 'simple-hotel-crm' ) );
    }

    global $wpdb;
    $room_pricing_table = simple_hotel_crm_room_pricing_table();
    $error_message = '';
    $defaults = [
        'guest_name' => '',
        'phone' => '',
        'email' => '',
        'address_line_1' => '',
        'address_line_2' => '',
        'city' => '',
        'postcode' => '',
        'country' => '',
        'guest_notes' => '',
        'check_in' => '',
        'check_out' => '',
        'source_channel' => 'direct',
        'status_code' => 'confirmed',
        'contacted_date' => gmdate( 'Y-m-d' ),
        'booking_note' => '',
        'import_notes' => '',
        'room_lines' => [ [ 'room_sync_id' => '', 'room_note' => '', 'adults' => 2, 'children' => 0, 'babies' => 0, 'room_rate_amount' => '0.00', 'discount_type' => 'none', 'discount_value' => '0.00', 'extras_formula' => '', 'extras_amount' => '0.00' ] ],
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
    $room_pricing_rows = $wpdb->get_results( "SELECT room_id, occupancy_adults, price_amount, active FROM {$room_pricing_table} WHERE active = 1 ORDER BY room_id ASC, occupancy_adults ASC", ARRAY_A );
    $room_pricing_map = [];
    foreach ( $room_pricing_rows as $pricing_row ) {
        $room_pricing_map[ (int) $pricing_row['room_id'] ][ (int) $pricing_row['occupancy_adults'] ] = (float) $pricing_row['price_amount'];
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
        $form_data['room_lines'][] = [ 'room_sync_id' => '', 'room_note' => '', 'adults' => 2, 'children' => 0, 'babies' => 0, 'room_rate_amount' => '0.00', 'discount_type' => 'none', 'discount_value' => '0.00', 'extras_formula' => '', 'extras_amount' => '0.00' ];
    }
    if ( isset( $_POST['lgf_remove_room_line'] ) && is_array( $form_data['room_lines'] ) ) {
        $remove_index = absint( $_POST['lgf_remove_room_line'] );
        if ( isset( $form_data['room_lines'][ $remove_index ] ) ) {
            unset( $form_data['room_lines'][ $remove_index ] );
            $form_data['room_lines'] = array_values( $form_data['room_lines'] );
        }
        if ( empty( $form_data['room_lines'] ) ) {
            $form_data['room_lines'][] = [ 'room_sync_id' => '', 'room_note' => '', 'adults' => 2, 'children' => 0, 'babies' => 0, 'room_rate_amount' => '0.00', 'discount_type' => 'none', 'discount_value' => '0.00', 'extras_formula' => '', 'extras_amount' => '0.00' ];
        }
    }

    echo '<p>' . esc_html__( 'Choose dates first, then add one or more available rooms for the same booking.', 'simple-hotel-crm' ) . '</p>';
    echo '<form method="post">';
    wp_nonce_field( 'simple_hotel_crm_add_booking', 'simple_hotel_crm_add_booking_nonce' );
    echo '<table class="form-table">';
    echo '<tr><th><label for="check_in">' . esc_html__( 'Check-in', 'simple-hotel-crm' ) . '</label></th><td><input required type="date" name="check_in" id="check_in" value="' . esc_attr( $check_in_value ) . '" onchange="var o=document.getElementById(\'check_out\');if(this.value&&o&&(!o.value||o.value<=this.value)){var d=new Date(this.value+\'T00:00:00\');d.setDate(d.getDate()+1);o.value=d.toISOString().slice(0,10);} this.form.submit();" /></td></tr>';
    echo '<tr><th><label for="check_out">' . esc_html__( 'Check-out', 'simple-hotel-crm' ) . '</label></th><td><input required type="date" name="check_out" id="check_out" value="' . esc_attr( $check_out_value ) . '" onchange="this.form.submit();" /></td></tr>';
    echo '<tr><th scope="row">' . esc_html__( 'Rooms', 'simple-hotel-crm' ) . '</th><td>';
    $selected_room_ids = [];
    foreach ( (array) $form_data['room_lines'] as $line_index => $line ) {
        echo '<fieldset style="margin:0 0 16px 0;padding:12px;border:1px solid #ccd0d4;">';
        echo '<legend><strong>' . esc_html( sprintf( __( 'Room %d', 'simple-hotel-crm' ), $line_index + 1 ) ) . '</strong>';
        if ( count( (array) $form_data['room_lines'] ) > 1 ) {
            echo ' <button type="submit" class="button button-small" name="lgf_remove_room_line" value="' . esc_attr( (string) $line_index ) . '">' . esc_html__( 'Remove room', 'simple-hotel-crm' ) . '</button>';
        }
        echo '</legend>';
        echo '<p><label>' . esc_html__( 'Room', 'simple-hotel-crm' ) . ' <select name="room_lines[' . esc_attr( $line_index ) . '][room_sync_id]" ' . ( $dates_ready ? '' : 'disabled' ) . '>';
        echo '<option value="">' . esc_html__( $dates_ready ? 'Select an available room' : 'Choose dates first', 'simple-hotel-crm' ) . '</option>';
        foreach ( $rooms as $room ) {
            $room_id_value = (string) $room['id'];
            $is_selected_elsewhere = in_array( $room_id_value, $selected_room_ids, true );
            $current_selected = (string) ( $line['room_sync_id'] ?? '' );
            if ( $is_selected_elsewhere && $current_selected !== $room_id_value ) {
                continue;
            }
            $room_code = (string) $room['room_code'];
            $room_number = simple_hotel_crm_get_room_display_number( $room_code );
            echo '<option value="' . esc_attr( $room_id_value ) . '"' . selected( $current_selected, $room_id_value, false ) . '>' . esc_html( $room_number . ' - ' . $room['room_name'] ) . '</option>';
        }
        echo '</select></label></p>';
        $selected_room_ids[] = (string) ( $line['room_sync_id'] ?? '' );
        echo '<p><label>' . esc_html__( 'Adults', 'simple-hotel-crm' ) . ' <input type="number" min="0" name="room_lines[' . esc_attr( $line_index ) . '][adults]" value="' . esc_attr( (string) ( $line['adults'] ?? 0 ) ) . '" /></label> ';
        echo '<label>' . esc_html__( 'Children', 'simple-hotel-crm' ) . ' <input type="number" min="0" name="room_lines[' . esc_attr( $line_index ) . '][children]" value="' . esc_attr( (string) ( $line['children'] ?? 0 ) ) . '" /></label> ';
        echo '<label>' . esc_html__( 'Babies', 'simple-hotel-crm' ) . ' <input type="number" min="0" name="room_lines[' . esc_attr( $line_index ) . '][babies]" value="' . esc_attr( (string) ( $line['babies'] ?? 0 ) ) . '" /></label></p>';
        echo '<p><label>' . esc_html__( 'Room rate total', 'simple-hotel-crm' ) . ' <input type="text" name="room_lines[' . esc_attr( $line_index ) . '][room_rate_amount]" value="' . esc_attr( (string) ( $line['room_rate_amount'] ?? '0.00' ) ) . '" /></label> ';
        echo '<label>' . esc_html__( 'Discount', 'simple-hotel-crm' ) . ' <select name="room_lines[' . esc_attr( $line_index ) . '][discount_type]"><option value="none"' . selected( (string) ( $line['discount_type'] ?? 'none' ), 'none', false ) . '>None</option><option value="percent"' . selected( (string) ( $line['discount_type'] ?? 'none' ), 'percent', false ) . '>Percent</option><option value="amount"' . selected( (string) ( $line['discount_type'] ?? 'none' ), 'amount', false ) . '>Amount</option></select></label> ';
        echo '<label>' . esc_html__( 'Discount value', 'simple-hotel-crm' ) . ' <input type="text" name="room_lines[' . esc_attr( $line_index ) . '][discount_value]" value="' . esc_attr( (string) ( $line['discount_value'] ?? '0.00' ) ) . '" /></label> ';
        echo '<label>' . esc_html__( 'Extras total', 'simple-hotel-crm' ) . ' <input type="text" name="room_lines[' . esc_attr( $line_index ) . '][extras_amount]" value="' . esc_attr( (string) ( $line['extras_amount'] ?? '0.00' ) ) . '" /></label> ';
        echo '<span class="description simple-hotel-crm-price-preview" style="display:inline-block;min-width:180px;">' . esc_html__( 'Total preview updates below.', 'simple-hotel-crm' ) . '</span></p>';
        echo '</fieldset>';
    }
    if ( $dates_ready && empty( $rooms ) ) {
        echo '<p class="description">' . esc_html__( 'No rooms are available for those dates.', 'simple-hotel-crm' ) . '</p>';
    }
    submit_button( __( 'Add another room', 'simple-hotel-crm' ), 'secondary', 'lgf_add_room_line', false );
    echo '</td></tr>';
    echo '<tr><th><label for="contacted_date">' . esc_html__( 'Contacted date', 'simple-hotel-crm' ) . '</label></th><td><input type="date" name="contacted_date" id="contacted_date" value="' . esc_attr( (string) $form_data['contacted_date'] ) . '" /> &nbsp; <label for="source_channel" style="margin-left:8px;">' . esc_html__( 'Channel', 'simple-hotel-crm' ) . '</label> <select name="source_channel" id="source_channel">';
    foreach ( $channels as $code => $label ) {
        echo '<option value="' . esc_attr( $code ) . '"' . selected( (string) $form_data['source_channel'], $code, false ) . '>' . esc_html( $label ) . '</option>';
    }
    echo '</select> &nbsp; <label for="status_code" style="margin-left:8px;">' . esc_html__( 'Status', 'simple-hotel-crm' ) . '</label> <select name="status_code" id="status_code">';
    foreach ( $statuses as $code => $label ) {
        echo '<option value="' . esc_attr( $code ) . '"' . selected( (string) $form_data['status_code'], $code, false ) . '>' . esc_html( $label ) . '</option>';
    }
    echo '</select></td></tr>';
    echo '<tr><th colspan="2"><h2 style="margin:0;">' . esc_html__( 'Guest details', 'simple-hotel-crm' ) . '</h2></th></tr>';
    echo '<tr><th><label for="guest_name">' . esc_html__( 'Guest name', 'simple-hotel-crm' ) . '</label></th><td><input required type="text" name="guest_name" id="guest_name" class="regular-text" value="' . esc_attr( (string) $form_data['guest_name'] ) . '" /></td></tr>';
    echo '<tr><th><label for="phone">' . esc_html__( 'Phone', 'simple-hotel-crm' ) . '</label></th><td><input type="text" name="phone" id="phone" class="regular-text" value="' . esc_attr( (string) $form_data['phone'] ) . '" /></td></tr>';
    echo '<tr><th><label for="email">' . esc_html__( 'Email', 'simple-hotel-crm' ) . '</label></th><td><input type="email" name="email" id="email" class="regular-text" value="' . esc_attr( (string) $form_data['email'] ) . '" /></td></tr>';
    echo '<tr><th><label for="address_line_1">' . esc_html__( 'Address line 1', 'simple-hotel-crm' ) . '</label></th><td><input type="text" name="address_line_1" id="address_line_1" class="regular-text" value="' . esc_attr( (string) $form_data['address_line_1'] ) . '" /></td></tr>';
    echo '<tr><th><label for="address_line_2">' . esc_html__( 'Address line 2', 'simple-hotel-crm' ) . '</label></th><td><input type="text" name="address_line_2" id="address_line_2" class="regular-text" value="' . esc_attr( (string) $form_data['address_line_2'] ) . '" /></td></tr>';
    echo '<tr><th><label for="city">' . esc_html__( 'City', 'simple-hotel-crm' ) . '</label></th><td><input type="text" name="city" id="city" class="regular-text" value="' . esc_attr( (string) $form_data['city'] ) . '" /></td></tr>';
    echo '<tr><th><label for="postcode">' . esc_html__( 'Postcode', 'simple-hotel-crm' ) . '</label></th><td><input type="text" name="postcode" id="postcode" class="regular-text" value="' . esc_attr( (string) $form_data['postcode'] ) . '" /></td></tr>';
    echo '<tr><th><label for="country">' . esc_html__( 'Country', 'simple-hotel-crm' ) . '</label></th><td><input type="text" name="country" id="country" class="regular-text" value="' . esc_attr( (string) $form_data['country'] ) . '" /></td></tr>';
    echo '<tr><th><label for="guest_notes">' . esc_html__( 'Guest notes', 'simple-hotel-crm' ) . '</label></th><td><textarea name="guest_notes" id="guest_notes" rows="3" class="large-text">' . esc_textarea( (string) $form_data['guest_notes'] ) . '</textarea></td></tr>';
    echo '<tr><th><label for="booking_note">' . esc_html__( 'Booking note', 'simple-hotel-crm' ) . '</label></th><td><textarea name="booking_note" id="booking_note" rows="3" class="large-text">' . esc_textarea( (string) $form_data['booking_note'] ) . '</textarea></td></tr>';
    echo '<tr><th><label for="import_notes">' . esc_html__( 'Import / internal notes', 'simple-hotel-crm' ) . '</label></th><td><textarea name="import_notes" id="import_notes" rows="4" class="large-text">' . esc_textarea( (string) $form_data['import_notes'] ) . '</textarea></td></tr>';
    echo '</table>';
    echo '<p><strong>' . esc_html__( 'Booking total preview', 'simple-hotel-crm' ) . ':</strong> <span class="simple-hotel-crm-booking-total-preview">0.00</span></p>';
    submit_button( __( 'Create Booking', 'simple-hotel-crm' ), 'primary', 'lgf_add_booking_submit' );
    echo '</form>';
    $pricing_json = wp_json_encode( $room_pricing_map );
    $pricing_script = <<<'JS'
(function(){
    function num(v){
        v=(v||"0").toString().replace(/,/g, ".");
        var n=parseFloat(v);
        return isNaN(n)?0:n;
    }
    function commissionPercent(channel){
        return channel === 'booking_com' ? num(window.simpleHotelCrmBookingComCommissionPercent || 0) : 0;
    }
    function rateSource(box, nights){
        var room=box.querySelector("select[name*='[room_sync_id]']");
        var adults=box.querySelector("input[name*='[adults]']");
        var children=box.querySelector("input[name*='[children]']");
        var occupancy=(parseInt(adults&&adults.value||0,10)||0)+(parseInt(children&&children.value||0,10)||0);
        var base=((window.simpleHotelCrmRoomPricing||{})[room&&room.value]||{})[occupancy]||0;
        return {base:base,total:base>0?(base*nights):0};
    }
    function upd(forceAuto){
        var form=document.querySelector(".wrap form");
        if(!form) return;
        var ci=form.querySelector("#check_in");
        var co=form.querySelector("#check_out");
        var totalPreview=form.querySelector(".simple-hotel-crm-booking-total-preview");
        var channelField=form.querySelector("#source_channel");
        var bookingTotal=0;
        var nights=1;
        if(ci&&co&&ci.value&&co.value){
            nights=Math.max(1,Math.round((new Date(co.value)-new Date(ci.value))/86400000));
        }
        form.querySelectorAll("fieldset").forEach(function(fs){
            var room=fs.querySelector("select[name*='[room_sync_id]']");
            var adults=fs.querySelector("input[name*='[adults]']");
            var rate=fs.querySelector("input[name*='[room_rate_amount]']");
            var dtype=fs.querySelector("select[name*='[discount_type]']");
            var dval=fs.querySelector("input[name*='[discount_value]']");
            var extras=fs.querySelector("input[name*='[extras_amount]']");
            var preview=fs.querySelector(".simple-hotel-crm-price-preview");
            if(!room||!adults||!rate||!preview) return;
            var src=rateSource(fs,nights);
            if(src.base>0 && (forceAuto || rate.dataset.manualOverride!=="1")) rate.value=src.total.toFixed(2);
            var roomRate=num(rate.value);
            var discount=0;
            if(dtype&&dtype.value==="percent") discount=roomRate*(Math.min(100,num(dval&&dval.value))/100);
            if(dtype&&dtype.value==="amount") discount=Math.min(roomRate,num(dval&&dval.value));
            var netRoom=Math.max(0,roomRate-discount);
            var tax=(parseInt(adults.value||0,10)*0.8*nights);
            var total=netRoom+num(extras&&extras.value)+tax;
            var commission=netRoom*(commissionPercent(channelField&&channelField.value)/100);
            bookingTotal+=total;
            preview.textContent="Preview: room " + netRoom.toFixed(2) + " € + extras " + num(extras&&extras.value).toFixed(2) + " € + tax " + tax.toFixed(2) + " € = " + total.toFixed(2) + " € | commission " + commission.toFixed(2) + " €";
        });
        if(totalPreview){totalPreview.textContent=bookingTotal.toFixed(2) + " €";}
    }
    document.addEventListener("input", function(e){
        if(e.target && e.target.matches("input[name*='[room_rate_amount]']")) e.target.dataset.manualOverride="1";
        upd(false);
    });
    document.addEventListener("change", function(e){
        if(e.target && e.target.matches("select[name*='[room_sync_id]'], input[name*='[adults]'], input[name*='[children]']")){
            var box=e.target.closest('fieldset');
            var rate=box&&box.querySelector("input[name*='[room_rate_amount]']");
            if(rate) delete rate.dataset.manualOverride;
            upd(true);
            return;
        }
        if(e.target && e.target.matches("#check_in, #check_out")){
            document.querySelectorAll("input[name*='[room_rate_amount]']").forEach(function(rate){ if(rate.dataset.manualOverride!=="1") delete rate.dataset.manualOverride; });
            upd(true);
            return;
        }
        upd(false);
    });
    upd(false);
})();
JS;
    echo '<script>window.simpleHotelCrmRoomPricing=' . $pricing_json . ';window.simpleHotelCrmBookingComCommissionPercent=' . wp_json_encode( (float) get_option( 'simple_hotel_crm_booking_com_commission_percent', 15 ) ) . ';' . $pricing_script . '</script>';
    echo '</div>';
}

/**
 * Render Invoice Ninja settings page.
 */
function simple_hotel_crm_read_csv_file( $file ) {
    if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
        return new WP_Error( 'missing_file', __( 'Please upload a CSV file.', 'simple-hotel-crm' ) );
    }

    $handle = fopen( $file['tmp_name'], 'r' );
    if ( ! $handle ) {
        return new WP_Error( 'csv_open_failed', __( 'Could not open the CSV file.', 'simple-hotel-crm' ) );
    }

    $rows = [];
    $headers = fgetcsv( $handle );
    if ( ! is_array( $headers ) ) {
        fclose( $handle );
        return new WP_Error( 'csv_headers_missing', __( 'CSV headers are missing.', 'simple-hotel-crm' ) );
    }

    $headers = array_map( static function( $value ) {
        return sanitize_key( trim( (string) $value ) );
    }, $headers );

    while ( false !== ( $data = fgetcsv( $handle ) ) ) {
        if ( 1 === count( $data ) && '' === trim( (string) $data[0] ) ) {
            continue;
        }
        $row = [];
        foreach ( $headers as $index => $header ) {
            $row[ $header ] = isset( $data[ $index ] ) ? trim( (string) $data[ $index ] ) : '';
        }
        $rows[] = $row;
    }

    fclose( $handle );
    return $rows;
}

function simple_hotel_crm_normalize_import_name( $value ) {
    $value = remove_accents( strtolower( trim( (string) $value ) ) );
    $value = preg_replace( '/[^a-z0-9]+/', ' ', $value );
    return trim( preg_replace( '/\s+/', ' ', (string) $value ) );
}

function simple_hotel_crm_find_guest_for_import_row( $row ) {
    global $wpdb;
    $table = simple_hotel_crm_guests_table();
    $first_name = sanitize_text_field( $row['first_name'] ?? '' );
    $last_name = sanitize_text_field( $row['last_name'] ?? '' );
    $email = sanitize_email( $row['email'] ?? ( $row['guest_email'] ?? '' ) );
    $phone = sanitize_text_field( $row['phone'] ?? '' );

    if ( $email ) {
        $guest = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s LIMIT 1", $email ), ARRAY_A );
        if ( $guest ) {
            return $guest;
        }
    }
    if ( $phone && ( $first_name || $last_name ) ) {
        $guest = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE phone = %s AND first_name = %s AND last_name = %s LIMIT 1", $phone, $first_name, $last_name ), ARRAY_A );
        if ( $guest ) {
            return $guest;
        }
    }
    if ( empty( $first_name ) && empty( $last_name ) && ! empty( $row['guest_name'] ) ) {
        list( $first_name, $last_name ) = simple_hotel_crm_split_guest_name( sanitize_text_field( $row['guest_name'] ) );
    }
    if ( $first_name || $last_name ) {
        $guest = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE first_name = %s AND last_name = %s LIMIT 1", $first_name, $last_name ), ARRAY_A );
        if ( $guest ) {
            return $guest;
        }
    }

    $target_name = simple_hotel_crm_normalize_import_name( trim( $first_name . ' ' . $last_name ) ?: (string) ( $row['guest_name'] ?? '' ) );
    if ( '' !== $target_name ) {
        $guests = $wpdb->get_results( "SELECT * FROM {$table}", ARRAY_A );
        foreach ( $guests as $guest ) {
            $guest_name = simple_hotel_crm_normalize_import_name( trim( (string) ( $guest['first_name'] ?? '' ) . ' ' . (string) ( $guest['last_name'] ?? '' ) ) );
            if ( $guest_name === $target_name ) {
                return $guest;
            }
        }
    }

    return null;
}

function simple_hotel_crm_find_booking_for_import_row( $row, $guest_id = 0 ) {
    global $wpdb;
    $table = simple_hotel_crm_bookings_table();
    $external_booking_id = sanitize_text_field( $row['external_booking_id'] ?? '' );
    $check_in = sanitize_text_field( $row['check_in'] ?? '' );
    $check_out = sanitize_text_field( $row['check_out'] ?? '' );

    if ( $external_booking_id ) {
        $booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE source_booking_id = %s LIMIT 1", $external_booking_id ), ARRAY_A );
        if ( $booking ) return $booking;
    }
    if ( $guest_id > 0 && $check_in && $check_out ) {
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE guest_id = %d AND check_in_date = %s AND check_out_date = %s LIMIT 1", $guest_id, $check_in, $check_out ), ARRAY_A );
    }
    return null;
}

function simple_hotel_crm_import_guests_csv( $rows, $dry_run = false ) {
    global $wpdb;
    $table = simple_hotel_crm_guests_table();
    $summary = [ 'created' => 0, 'skipped' => 0, 'errors' => [] ];

    foreach ( $rows as $index => $row ) {
        $first_name = sanitize_text_field( $row['first_name'] ?? '' );
        $last_name = sanitize_text_field( $row['last_name'] ?? '' );
        $email = sanitize_email( $row['email'] ?? '' );
        $phone = sanitize_text_field( $row['phone'] ?? '' );
        if ( '' === $first_name && '' === $last_name ) {
            $summary['errors'][] = sprintf( __( 'Guests row %d: missing name.', 'simple-hotel-crm' ), $index + 2 );
            continue;
        }

        $existing = simple_hotel_crm_find_guest_for_import_row( $row );
        if ( ! empty( $existing['id'] ) ) {
            $summary['skipped']++;
            continue;
        }

        if ( $dry_run ) {
            $summary['created']++;
            continue;
        }

        $wpdb->insert( $table, [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'phone' => $phone,
            'address_line_1' => sanitize_text_field( $row['address_line_1'] ?? '' ),
            'address_line_2' => sanitize_text_field( $row['address_line_2'] ?? '' ),
            'city' => sanitize_text_field( $row['city'] ?? '' ),
            'postcode' => sanitize_text_field( $row['postcode'] ?? '' ),
            'country' => sanitize_text_field( $row['country'] ?? '' ),
            'notes' => sanitize_textarea_field( $row['notes'] ?? '' ),
        ], [ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ] );
        $summary['created']++;
    }

    return $summary;
}

function simple_hotel_crm_import_bookings_csv( $rows, $dry_run = false ) {
    global $wpdb;
    $guests_table = simple_hotel_crm_guests_table();
    $bookings_table = simple_hotel_crm_bookings_table();
    $summary = [ 'created' => 0, 'skipped' => 0, 'errors' => [] ];

    foreach ( $rows as $index => $row ) {
        $guest_email = sanitize_email( $row['guest_email'] ?? '' );
        $guest_name = trim( sanitize_text_field( $row['guest_name'] ?? '' ) );
        $check_in = sanitize_text_field( $row['check_in'] ?? '' );
        $check_out = sanitize_text_field( $row['check_out'] ?? '' );
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $check_in ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $check_out ) ) {
            $summary['errors'][] = sprintf( __( 'Bookings row %d: invalid dates.', 'simple-hotel-crm' ), $index + 2 );
            continue;
        }

        $guest = simple_hotel_crm_find_guest_for_import_row( [ 'email' => $guest_email, 'guest_name' => $guest_name ] );
        if ( ! $guest ) {
            $summary['errors'][] = sprintf( __( 'Bookings row %d: guest not found.', 'simple-hotel-crm' ), $index + 2 );
            continue;
        }

        $exists = simple_hotel_crm_find_booking_for_import_row( $row, (int) $guest['id'] );
        if ( ! empty( $exists['id'] ) ) {
            $summary['skipped']++;
            continue;
        }

        if ( $dry_run ) {
            $summary['created']++;
            continue;
        }

        $wpdb->insert( $bookings_table, [
            'guest_id' => (int) $guest['id'],
            'source_channel' => sanitize_text_field( $row['source_channel'] ?? 'direct' ),
            'source_booking_id' => sanitize_text_field( $row['external_booking_id'] ?? '' ),
            'status_code' => sanitize_text_field( $row['status_code'] ?? 'confirmed' ),
            'contacted_date' => sanitize_text_field( $row['contacted_date'] ?? '' ) ?: null,
            'check_in_date' => $check_in,
            'check_out_date' => $check_out,
            'currency' => 'EUR',
            'booking_note' => sanitize_textarea_field( $row['booking_note'] ?? '' ),
            'internal_notes' => sanitize_textarea_field( $row['internal_notes'] ?? '' ),
        ], [ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ] );
        $summary['created']++;
    }

    return $summary;
}

function simple_hotel_crm_import_booking_rooms_csv( $rows, $dry_run = false ) {
    global $wpdb;
    $guests_table = simple_hotel_crm_guests_table();
    $bookings_table = simple_hotel_crm_bookings_table();
    $rooms_table = simple_hotel_crm_rooms_table();
    $booking_rooms_table = simple_hotel_crm_booking_rooms_table();
    $booking_nights_table = simple_hotel_crm_booking_room_nights_table();
    $sync_bookings_table = simple_hotel_crm_sync_bookings_table();
    $summary = [ 'created' => 0, 'skipped' => 0, 'errors' => [] ];

    foreach ( $rows as $index => $row ) {
        $guest_email = sanitize_email( $row['guest_email'] ?? '' );
        $check_in = sanitize_text_field( $row['check_in'] ?? '' );
        $check_out = sanitize_text_field( $row['check_out'] ?? '' );
        $room_code = strtoupper( sanitize_text_field( $row['room_code'] ?? '' ) );
        $room_id_input = absint( $row['room_id'] ?? 0 );
        $guest = simple_hotel_crm_find_guest_for_import_row( [ 'email' => $guest_email, 'guest_name' => $row['guest_name'] ?? '' ] );
        if ( ! $guest ) {
            $summary['errors'][] = sprintf( __( 'Room row %d: guest not found.', 'simple-hotel-crm' ), $index + 2 );
            continue;
        }
        $booking = simple_hotel_crm_find_booking_for_import_row( $row, (int) $guest['id'] );
        $room = null;
        if ( $room_id_input > 0 ) {
            $room = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$rooms_table} WHERE id = %d LIMIT 1", $room_id_input ), ARRAY_A );
        }
        if ( ! $room && '' !== $room_code ) {
            $room = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$rooms_table} WHERE room_code = %s LIMIT 1", $room_code ), ARRAY_A );
        }
        if ( ! $booking || ! $room ) {
            $summary['errors'][] = sprintf( __( 'Room row %d: booking or room not found.', 'simple-hotel-crm' ), $index + 2 );
            continue;
        }
        $exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$booking_rooms_table} WHERE booking_id = %d AND room_id = %d LIMIT 1", $booking['id'], $room['id'] ) );
        if ( $exists ) {
            $summary['skipped']++;
            continue;
        }

        $adults = max( 0, (int) ( $row['adults'] ?? 0 ) );
        $children = max( 0, (int) ( $row['children'] ?? 0 ) );
        $babies = max( 0, (int) ( $row['babies'] ?? 0 ) );
        $guest_count = $adults + $children + $babies;
        $room_rate_amount = (float) simple_hotel_crm_normalize_decimal( $row['room_rate_amount'] ?? 0 );
        $extras_amount = (float) simple_hotel_crm_normalize_decimal( $row['extras_amount'] ?? 0 );
        $tourist_tax_amount = (float) simple_hotel_crm_normalize_decimal( $row['tourist_tax_amount'] ?? 0 );
        $total_amount = $room_rate_amount + $extras_amount + $tourist_tax_amount;

        if ( $dry_run ) {
            $summary['created']++;
            continue;
        }

        $legacy_reserved_room_id = max( 1, (int) $wpdb->get_var( "SELECT COALESCE(MAX(external_booking_room_id), 0) + 1 FROM {$sync_bookings_table}" ) );
        $wpdb->insert( $booking_rooms_table, [
            'booking_id' => (int) $booking['id'], 'room_id' => (int) $room['id'], 'legacy_reserved_room_id' => $legacy_reserved_room_id, 'guest_count' => $guest_count,
            'adults' => $adults, 'children' => $children, 'babies' => $babies,
            'room_rate_amount' => $room_rate_amount, 'extras_amount' => $extras_amount, 'tourist_tax_amount' => $tourist_tax_amount, 'total_amount' => $total_amount,
        ], [ '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%f', '%f', '%f', '%f' ] );
        $booking_room_id = (int) $wpdb->insert_id;
        $nights = max( 1, (int) round( ( strtotime( $check_out ) - strtotime( $check_in ) ) / DAY_IN_SECONDS ) );
        for ( $i = 0; $i < $nights; $i++ ) {
            $stay_date = gmdate( 'Y-m-d', strtotime( $check_in . ' +' . $i . ' day' ) );
            $night_room_rate = round( $room_rate_amount / $nights, 2 );
            $night_extras = round( $extras_amount / $nights, 2 );
            $night_tax = round( $tourist_tax_amount / $nights, 2 );
            $night_total = round( $total_amount / $nights, 2 );
            $wpdb->insert( $booking_nights_table, [
                'booking_room_id' => $booking_room_id, 'stay_date' => $stay_date, 'guest_count' => $guest_count,
                'adults' => $adults, 'children' => $children, 'babies' => $babies,
                'room_rate_amount' => $night_room_rate, 'extras_amount' => $night_extras,
                'tourist_tax_amount' => $night_tax, 'total_amount' => $night_total,
            ], [ '%d', '%s', '%d', '%d', '%d', '%d', '%f', '%f', '%f', '%f' ] );
            $wpdb->insert( $sync_bookings_table, [
                'external_booking_id' => (int) $booking['id'], 'external_booking_room_id' => $legacy_reserved_room_id,
                'external_room_id' => 0, 'room_sync_id' => 0, 'status_code' => (string) ( $booking['status_code'] ?? 'confirmed' ),
                'check_in' => $check_in, 'check_out' => $check_out, 'stay_date' => $stay_date,
                'guest_count' => $guest_count, 'adults' => $adults, 'children' => $children, 'babies' => $babies,
                'total_amount' => $night_total, 'room_amount' => $night_room_rate, 'extras_amount' => $night_extras, 'tourist_tax_amount' => $night_tax,
                'room_count' => 1, 'source_channel' => (string) ( $booking['source_channel'] ?? 'direct' ), 'source_booking_id' => (string) ( $booking['source_booking_id'] ?? '' ),
                'channel_label' => simple_hotel_crm_get_booking_channel_options()[ (string) ( $booking['source_channel'] ?? 'direct' ) ] ?? (string) ( $booking['source_channel'] ?? 'direct' ),
                'guest_name' => trim( (string) ( $guest['first_name'] ?? '' ) . ' ' . (string) ( $guest['last_name'] ?? '' ) ), 'phone' => (string) ( $guest['phone'] ?? '' ),
                'import_notes' => (string) ( $booking['internal_notes'] ?? '' ), 'invoice_ninja_client_id' => (string) ( $booking['invoice_ninja_client_id'] ?? '' ),
                'invoice_ninja_invoice_id' => (string) ( $booking['invoice_ninja_invoice_id'] ?? '' ), 'source_created_at' => (string) ( $booking['contacted_date'] ?? '' ),
            ], [ '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%f', '%f', '%f', '%f', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ] );
        }
        $summary['created']++;
    }

    simple_hotel_crm_clear_calendar_cache();
    return $summary;
}

function simple_hotel_crm_repair_bookings_csv( $rows, $dry_run = false ) {
    global $wpdb;

    $bookings_table = simple_hotel_crm_bookings_table();
    $summary = [ 'created' => 0, 'skipped' => 0, 'errors' => [] ];

    foreach ( $rows as $index => $row ) {
        $guest_email = sanitize_email( $row['guest_email'] ?? '' );
        $guest_name = trim( sanitize_text_field( $row['guest_name'] ?? '' ) );
        $check_in = sanitize_text_field( $row['check_in'] ?? '' );
        $check_out = sanitize_text_field( $row['check_out'] ?? '' );
        $guest = simple_hotel_crm_find_guest_for_import_row( [ 'email' => $guest_email, 'guest_name' => $guest_name, 'phone' => $row['phone'] ?? '' ] );
        $booking = simple_hotel_crm_find_booking_for_import_row( $row, (int) ( $guest['id'] ?? 0 ) );
        if ( ! $booking ) {
            $summary['errors'][] = sprintf( __( 'Repair bookings row %d: booking not found.', 'simple-hotel-crm' ), $index + 2 );
            continue;
        }

        $update = [];
        if ( '' !== $guest_name && ! $guest ) {
            list( $first_name, $last_name ) = simple_hotel_crm_split_guest_name( $guest_name );
            if ( ! $dry_run ) {
                $wpdb->insert( simple_hotel_crm_guests_table(), [ 'first_name' => $first_name, 'last_name' => $last_name, 'email' => $guest_email, 'phone' => sanitize_text_field( $row['phone'] ?? '' ) ], [ '%s', '%s', '%s', '%s' ] );
                $guest = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . simple_hotel_crm_guests_table() . ' WHERE id = %d', (int) $wpdb->insert_id ), ARRAY_A );
            }
        }
        if ( ! empty( $guest['id'] ) && (int) $booking['guest_id'] !== (int) $guest['id'] ) {
            $update['guest_id'] = (int) $guest['id'];
        }
        foreach ( [ 'status_code', 'source_channel' ] as $field ) {
            if ( ! empty( $row[ $field ] ) ) {
                $update[ $field ] = sanitize_text_field( $row[ $field ] );
            }
        }
        foreach ( [ 'booking_note', 'internal_notes' ] as $field ) {
            if ( array_key_exists( $field, $row ) && '' !== (string) $row[ $field ] ) {
                $update[ $field ] = sanitize_textarea_field( $row[ $field ] );
            }
        }
        foreach ( [ 'room_rate_amount', 'extras_amount', 'tourist_tax_amount', 'total_amount' ] as $field ) {
            if ( array_key_exists( $field, $row ) && null !== simple_hotel_crm_normalize_decimal( $row[ $field ] ) ) {
                $update[ $field ] = (float) simple_hotel_crm_normalize_decimal( $row[ $field ] );
            }
        }
        if ( empty( $update ) ) {
            $summary['skipped']++;
            continue;
        }
        if ( $dry_run ) {
            $summary['created']++;
            continue;
        }
        $formats = [];
        foreach ( $update as $key => $value ) {
            $formats[] = is_int( $value ) ? '%d' : ( is_float( $value ) ? '%f' : '%s' );
        }
        $wpdb->update( $bookings_table, $update, [ 'id' => (int) $booking['id'] ], $formats, [ '%d' ] );
        $summary['created']++;
    }

    return $summary;
}

function simple_hotel_crm_repair_booking_rooms_csv( $rows, $dry_run = false ) {
    global $wpdb;

    $booking_rooms_table = simple_hotel_crm_booking_rooms_table();
    $booking_nights_table = simple_hotel_crm_booking_room_nights_table();
    $summary = [ 'created' => 0, 'skipped' => 0, 'errors' => [] ];

    foreach ( $rows as $index => $row ) {
        $guest = simple_hotel_crm_find_guest_for_import_row( [ 'email' => $row['guest_email'] ?? '', 'guest_name' => $row['guest_name'] ?? '', 'phone' => $row['phone'] ?? '' ] );
        $booking = simple_hotel_crm_find_booking_for_import_row( $row, (int) ( $guest['id'] ?? 0 ) );
        $room_id = 0;
        if ( ! empty( $row['room_id'] ) ) {
            $room_id = (int) $row['room_id'];
        } elseif ( ! empty( $row['room_code'] ) ) {
            $room_id = simple_hotel_crm_find_crm_room_id( 0, [ 'room_code' => $row['room_code'], 'room_name' => $row['room_name'] ?? '' ] );
        }
        if ( ! $booking || $room_id <= 0 ) {
            $summary['errors'][] = sprintf( __( 'Repair room row %d: booking or room not found.', 'simple-hotel-crm' ), $index + 2 );
            continue;
        }
        $booking_room = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$booking_rooms_table} WHERE booking_id = %d AND room_id = %d LIMIT 1", (int) $booking['id'], $room_id ), ARRAY_A );
        if ( ! $booking_room ) {
            $summary['errors'][] = sprintf( __( 'Repair room row %d: existing room line not found.', 'simple-hotel-crm' ), $index + 2 );
            continue;
        }

        $adults = max( 0, (int) ( $row['adults'] ?? $booking_room['adults'] ?? 0 ) );
        $children = max( 0, (int) ( $row['children'] ?? $booking_room['children'] ?? 0 ) );
        $babies = max( 0, (int) ( $row['babies'] ?? $booking_room['babies'] ?? 0 ) );
        $guest_count = $adults + $children + $babies;
        $room_rate_amount = (float) simple_hotel_crm_normalize_decimal( $row['room_rate_amount'] ?? $booking_room['room_rate_amount'] ?? 0 );
        $extras_amount = (float) simple_hotel_crm_normalize_decimal( $row['extras_amount'] ?? $booking_room['extras_amount'] ?? 0 );
        $tourist_tax_amount = (float) simple_hotel_crm_normalize_decimal( $row['tourist_tax_amount'] ?? $booking_room['tourist_tax_amount'] ?? 0 );
        $total_amount = ( array_key_exists( 'total_amount', $row ) && null !== simple_hotel_crm_normalize_decimal( $row['total_amount'] ?? null ) ) ? (float) simple_hotel_crm_normalize_decimal( $row['total_amount'] ) : round( $room_rate_amount + $extras_amount + $tourist_tax_amount, 2 );
        if ( $dry_run ) {
            $summary['created']++;
            continue;
        }

        $wpdb->update( $booking_rooms_table, [
            'guest_count' => $guest_count,
            'adults' => $adults,
            'children' => $children,
            'babies' => $babies,
            'room_rate_amount' => $room_rate_amount,
            'extras_amount' => $extras_amount,
            'tourist_tax_amount' => $tourist_tax_amount,
            'total_amount' => $total_amount,
        ], [ 'id' => (int) $booking_room['id'] ], [ '%d', '%d', '%d', '%d', '%f', '%f', '%f', '%f' ], [ '%d' ] );

        $check_in = sanitize_text_field( $row['check_in'] ?? $booking['check_in_date'] ?? '' );
        $check_out = sanitize_text_field( $row['check_out'] ?? $booking['check_out_date'] ?? '' );
        $nights = max( 1, (int) round( ( strtotime( $check_out ) - strtotime( $check_in ) ) / DAY_IN_SECONDS ) );
        $night_room_rate = round( $room_rate_amount / $nights, 2 );
        $night_extras = round( $extras_amount / $nights, 2 );
        $night_tax = round( $tourist_tax_amount / $nights, 2 );
        $night_total = round( $total_amount / $nights, 2 );
        $wpdb->delete( $booking_nights_table, [ 'booking_room_id' => (int) $booking_room['id'] ], [ '%d' ] );
        for ( $i = 0; $i < $nights; $i++ ) {
            $stay_date = gmdate( 'Y-m-d', strtotime( $check_in . ' +' . $i . ' day' ) );
            $wpdb->insert( $booking_nights_table, [
                'booking_room_id' => (int) $booking_room['id'],
                'stay_date' => $stay_date,
                'guest_count' => $guest_count,
                'adults' => $adults,
                'children' => $children,
                'babies' => $babies,
                'room_rate_amount' => $night_room_rate,
                'extras_amount' => $night_extras,
                'tourist_tax_amount' => $night_tax,
                'total_amount' => $night_total,
            ], [ '%d', '%s', '%d', '%d', '%d', '%d', '%f', '%f', '%f', '%f' ] );
        }
        $summary['created']++;
    }

    simple_hotel_crm_clear_calendar_cache();
    return $summary;
}

function simple_hotel_crm_render_import_preview( $rows ) {
    if ( empty( $rows ) ) {
        return;
    }
    $preview_rows = array_slice( $rows, 0, 5 );
    $headers = array_keys( $preview_rows[0] );
    echo '<h2>' . esc_html__( 'Preview', 'simple-hotel-crm' ) . '</h2>';
    echo '<p>' . esc_html( sprintf( __( '%d row(s) found. Showing first 5.', 'simple-hotel-crm' ), count( $rows ) ) ) . '</p>';
    echo '<table class="widefat striped"><thead><tr>';
    foreach ( $headers as $header ) { echo '<th>' . esc_html( $header ) . '</th>'; }
    echo '</tr></thead><tbody>';
    foreach ( $preview_rows as $preview_row ) {
        echo '<tr>';
        foreach ( $headers as $header ) { echo '<td>' . esc_html( (string) ( $preview_row[$header] ?? '' ) ) . '</td>'; }
        echo '</tr>';
    }
    echo '</tbody></table>';
}

function simple_hotel_crm_render_import_panel() {
    $result = null;
    $preview_rows = [];
    if ( isset( $_POST['simple_hotel_crm_import_submit'] ) || isset( $_POST['simple_hotel_crm_import_preview'] ) ) {
        check_admin_referer( 'simple_hotel_crm_import', 'simple_hotel_crm_import_nonce' );
        $type = sanitize_text_field( wp_unslash( $_POST['import_type'] ?? '' ) );
        $rows = simple_hotel_crm_read_csv_file( $_FILES['import_csv'] ?? [] );
        if ( is_wp_error( $rows ) ) {
            $result = [ 'errors' => [ $rows->get_error_message() ], 'created' => 0, 'skipped' => 0 ];
        } else {
            $preview_rows = $rows;
            $dry_run = isset( $_POST['simple_hotel_crm_import_preview'] );
            if ( 'guests' === $type ) {
                $result = simple_hotel_crm_import_guests_csv( $rows, $dry_run );
            } elseif ( 'bookings' === $type ) {
                $result = simple_hotel_crm_import_bookings_csv( $rows, $dry_run );
            } elseif ( 'booking_rooms' === $type ) {
                $result = simple_hotel_crm_import_booking_rooms_csv( $rows, $dry_run );
            } elseif ( 'bookings_repair' === $type ) {
                $result = simple_hotel_crm_repair_bookings_csv( $rows, $dry_run );
            } elseif ( 'booking_rooms_repair' === $type ) {
                $result = simple_hotel_crm_repair_booking_rooms_csv( $rows, $dry_run );
            }
        }
    }

    echo '<p>' . esc_html__( 'Import missing records only. Recommended order: guests, bookings, booking rooms. For Booking.com workflow: sync ICS skeletons first, then use repair modes to enrich existing bookings/room lines from CSV backup.', 'simple-hotel-crm' ) . '</p>';
    echo '<p><a class="button" href="data:text/csv;charset=utf-8,' . rawurlencode( "external_guest_id,first_name,last_name,email,phone,address_line_1,address_line_2,city,postcode,country,notes\nG-1001,Jane,Doe,jane@example.com,123456789,1 Street,,Town,75000,France,VIP" ) . '" download="guests-template.csv">' . esc_html__( 'Download guests template', 'simple-hotel-crm' ) . '</a> ';
    echo '<a class="button" href="data:text/csv;charset=utf-8,' . rawurlencode( "external_booking_id,guest_email,guest_name,check_in,check_out,status_code,source_channel,contacted_date,booking_note,internal_notes\nB-2001,jane@example.com,Jane Doe,2026-06-01,2026-06-03,confirmed,direct,2026-05-01,Late arrival,Imported from CSV" ) . '" download="bookings-template.csv">' . esc_html__( 'Download bookings template', 'simple-hotel-crm' ) . '</a> ';
    echo '<a class="button" href="data:text/csv;charset=utf-8,' . rawurlencode( "external_booking_id,guest_email,guest_name,check_in,check_out,room_code,adults,children,babies,room_rate_amount,extras_amount,tourist_tax_amount\nB-2001,jane@example.com,Jane Doe,2026-06-01,2026-06-03,ANE,2,0,0,180,20,3.2" ) . '" download="booking-rooms-template.csv">' . esc_html__( 'Download booking rooms template', 'simple-hotel-crm' ) . '</a></p>';
    if ( ! empty( $preview_rows ) ) { simple_hotel_crm_render_import_preview( $preview_rows ); }
    if ( $result ) {
        echo '<div class="notice notice-success"><p>' . esc_html( sprintf( __( 'Created: %1$d, Skipped: %2$d', 'simple-hotel-crm' ), (int) $result['created'], (int) $result['skipped'] ) ) . '</p></div>';
        if ( ! empty( $result['errors'] ) ) {
            echo '<div class="notice notice-warning"><ul>';
            foreach ( $result['errors'] as $error ) {
                echo '<li>' . esc_html( $error ) . '</li>';
            }
            echo '</ul></div>';
        }
    }
    echo '<form method="post" enctype="multipart/form-data">';
    wp_nonce_field( 'simple_hotel_crm_import', 'simple_hotel_crm_import_nonce' );
    echo '<table class="form-table"><tr><th><label for="import_type">' . esc_html__( 'Import type', 'simple-hotel-crm' ) . '</label></th><td><select name="import_type" id="import_type"><option value="guests">' . esc_html__( 'Guests', 'simple-hotel-crm' ) . '</option><option value="bookings">' . esc_html__( 'Bookings', 'simple-hotel-crm' ) . '</option><option value="booking_rooms">' . esc_html__( 'Booking rooms', 'simple-hotel-crm' ) . '</option><option value="bookings_repair">' . esc_html__( 'Repair existing bookings', 'simple-hotel-crm' ) . '</option><option value="booking_rooms_repair">' . esc_html__( 'Repair existing booking rooms', 'simple-hotel-crm' ) . '</option></select></td></tr>';
    echo '<tr><th><label for="import_csv">' . esc_html__( 'CSV file', 'simple-hotel-crm' ) . '</label></th><td><input type="file" name="import_csv" id="import_csv" accept=".csv,text/csv" required /></td></tr></table>';
    submit_button( __( 'Preview / Dry Run', 'simple-hotel-crm' ), 'secondary', 'simple_hotel_crm_import_preview', false );
    echo ' ';
    submit_button( __( 'Import CSV', 'simple-hotel-crm' ), 'primary', 'simple_hotel_crm_import_submit', false );
    echo '</form>';
}

function simple_hotel_crm_resolve_motopress_local_accommodation_label( $accommodation_id = 0, $accommodation_type_id = 0 ) {
    global $wpdb;

    $accommodation_id = (int) $accommodation_id;
    $accommodation_type_id = (int) $accommodation_type_id;
    $post_ids = array_filter( [ $accommodation_id, $accommodation_type_id ] );
    if ( empty( $post_ids ) ) {
        return '';
    }

    $posts_table = $wpdb->posts;
    $placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );
    $rows = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title, post_type FROM {$posts_table} WHERE ID IN ({$placeholders})", $post_ids ), ARRAY_A );
    if ( empty( $rows ) ) {
        return '';
    }

    $labels = [];
    foreach ( $rows as $post_row ) {
        $labels[ (int) $post_row['ID'] ] = trim( (string) ( $post_row['post_title'] ?? '' ) );
    }

    return (string) ( $labels[ $accommodation_id ] ?? $labels[ $accommodation_type_id ] ?? '' );
}

function simple_hotel_crm_guess_crm_room_id_from_motopress_label( $label ) {
    global $wpdb;

    $label = trim( (string) $label );
    if ( '' === $label ) {
        return 0;
    }

    $rooms_table = simple_hotel_crm_rooms_table();
    $rooms = $wpdb->get_results( "SELECT id, room_code, room_name FROM {$rooms_table} WHERE active = 1 ORDER BY sort_order ASC, room_name ASC", ARRAY_A );
    $normalized_label = simple_hotel_crm_normalize_import_name( preg_replace( '/\([^)]*\)/', ' ', $label ) );

    foreach ( $rooms as $room ) {
        $room_name = trim( (string) ( $room['room_name'] ?? '' ) );
        $normalized_room_name = simple_hotel_crm_normalize_import_name( $room_name );

        if ( '' !== $normalized_room_name && false !== strpos( $normalized_label, $normalized_room_name ) ) {
            return (int) $room['id'];
        }
    }

    foreach ( $rooms as $room ) {
        $room_code = strtoupper( trim( (string) ( $room['room_code'] ?? '' ) ) );
        if ( '' !== $room_code && false !== strpos( strtoupper( $label ), $room_code ) ) {
            return (int) $room['id'];
        }
    }

    return 0;
}

function simple_hotel_crm_extract_motopress_room_candidates( $row ) {
    $row = is_array( $row ) ? $row : [];
    $candidates = [];
    $collections = [];

    foreach ( [ 'reserved_accommodations', 'rooms', 'reserved_rooms', 'reservedRooms', 'accommodations', 'room_stays', 'roomStays' ] as $key ) {
        if ( ! empty( $row[ $key ] ) && is_array( $row[ $key ] ) ) {
            $collections[] = $row[ $key ];
        }
    }

    foreach ( $collections as $collection ) {
        foreach ( $collection as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $room_id = (int) ( $item['room_id'] ?? $item['roomId'] ?? $item['id'] ?? $item['accommodation'] ?? $item['accommodation_id'] ?? $item['accommodationId'] ?? 0 );
            $accommodation_type = (int) ( $item['accommodation_type'] ?? 0 );
            $room_code = strtoupper( trim( (string) ( $item['room_code'] ?? $item['roomCode'] ?? $item['code'] ?? '' ) ) );
            $room_name = trim( (string) ( $item['room_name'] ?? $item['roomName'] ?? $item['name'] ?? $item['accommodation_title'] ?? $item['accommodationTitle'] ?? $item['accommodation_label'] ?? '' ) );
            if ( '' === $room_name ) {
                $room_name = simple_hotel_crm_resolve_motopress_local_accommodation_label( $room_id, $accommodation_type );
            }
            $adults = max( 0, (int) ( $item['adults'] ?? $item['adult_count'] ?? $item['adultCount'] ?? $row['adults'] ?? 0 ) );
            $children = max( 0, (int) ( $item['children'] ?? $item['child_count'] ?? $item['childCount'] ?? $row['children'] ?? 0 ) );
            $babies = max( 0, (int) ( $item['babies'] ?? $item['infants'] ?? $row['babies'] ?? 0 ) );
            $candidates[] = [
                'external_room_id' => $room_id,
                'room_code' => $room_code,
                'room_name' => $room_name,
            'accommodation_type' => $accommodation_type,
            'guest_name' => (string) ( $item['guest_name'] ?? '' ),
                'adults' => $adults,
                'children' => $children,
                'babies' => $babies,
            ];
        }
    }

    if ( empty( $candidates ) ) {
        $candidates[] = [
            'external_room_id' => (int) ( $row['room_id'] ?? $row['roomId'] ?? $row['accommodation'] ?? $row['accommodation_id'] ?? 0 ),
            'room_code' => strtoupper( trim( (string) ( $row['room_code'] ?? $row['roomCode'] ?? '' ) ) ),
            'room_name' => trim( (string) ( $row['room_name'] ?? $row['roomName'] ?? $row['accommodation_title'] ?? $row['accommodationTitle'] ?? '' ) ),
            'adults' => max( 0, (int) ( $row['adults'] ?? 0 ) ),
            'children' => max( 0, (int) ( $row['children'] ?? 0 ) ),
            'babies' => max( 0, (int) ( $row['babies'] ?? 0 ) ),
        ];
    }

    return array_values( array_filter( $candidates, static function( $candidate ) {
        return ! empty( $candidate['external_room_id'] ) || ! empty( $candidate['room_code'] ) || ! empty( $candidate['room_name'] );
    } ) );
}

function simple_hotel_crm_map_motopress_room_candidates( $row ) {
    $candidates = simple_hotel_crm_extract_motopress_room_candidates( $row );
    $mapped = [];

    foreach ( $candidates as $candidate ) {
        $crm_room_id = simple_hotel_crm_find_crm_room_id( (int) ( $candidate['external_room_id'] ?? 0 ), [
            'room_code' => $candidate['room_code'] ?? '',
            'room_name' => $candidate['room_name'] ?? '',
        ] );
        if ( $crm_room_id <= 0 ) {
            $crm_room_id = simple_hotel_crm_guess_crm_room_id_from_motopress_label( $candidate['room_name'] ?? '' );
        }
        $mapped[] = array_merge( $candidate, [
            'crm_room_id' => $crm_room_id,
            'mapping_status' => $crm_room_id > 0 ? 'matched' : 'unmatched',
        ] );
    }

    return $mapped;
}

function simple_hotel_crm_build_motopress_room_import_rows( $mapped_booking_row, $mapped_room_rows, $raw_booking_row = [] ) {
    $mapped_booking_row = is_array( $mapped_booking_row ) ? $mapped_booking_row : [];
    $mapped_room_rows   = is_array( $mapped_room_rows ) ? $mapped_room_rows : [];
    $raw_booking_row    = is_array( $raw_booking_row ) ? $raw_booking_row : [];
    $raw_reserved_rooms = ! empty( $raw_booking_row['reserved_accommodations'] ) && is_array( $raw_booking_row['reserved_accommodations'] ) ? array_values( $raw_booking_row['reserved_accommodations'] ) : [];
    $rows = [];

    foreach ( $mapped_room_rows as $index => $room_row ) {
        if ( ! is_array( $room_row ) ) {
            continue;
        }
        $raw_room_row = $raw_reserved_rooms[ $index ] ?? [];
        $room_rate_amount = 0.0;
        $tourist_tax_amount = 0.0;
        $extras_amount = 0.0;

        if ( ! empty( $raw_room_row['accommodation_price_per_days'] ) && is_array( $raw_room_row['accommodation_price_per_days'] ) ) {
            foreach ( $raw_room_row['accommodation_price_per_days'] as $day_row ) {
                $room_rate_amount += (float) simple_hotel_crm_normalize_decimal( $day_row['price'] ?? 0 );
            }
        }
        if ( ! empty( $raw_room_row['taxes']['accommodation'] ) && is_array( $raw_room_row['taxes']['accommodation'] ) ) {
            foreach ( $raw_room_row['taxes']['accommodation'] as $tax_row ) {
                $tourist_tax_amount += (float) simple_hotel_crm_normalize_decimal( $tax_row['value'] ?? 0 );
            }
        }

        $rows[] = [
            'external_booking_id' => (string) ( $mapped_booking_row['external_booking_id'] ?? '' ),
            'guest_name'          => (string) ( $mapped_booking_row['guest_name'] ?? '' ),
            'guest_email'         => (string) ( $mapped_booking_row['guest_email'] ?? '' ),
            'check_in'            => (string) ( $mapped_booking_row['check_in'] ?? '' ),
            'check_out'           => (string) ( $mapped_booking_row['check_out'] ?? '' ),
            'room_code'           => (string) ( $room_row['room_code'] ?? '' ),
            'room_name'           => (string) ( $room_row['room_name'] ?? '' ),
            'room_id'             => (int) ( $room_row['crm_room_id'] ?? 0 ),
            'adults'              => (int) ( $room_row['adults'] ?? 0 ),
            'children'            => (int) ( $room_row['children'] ?? 0 ),
            'babies'              => (int) ( $room_row['babies'] ?? 0 ),
            'room_rate_amount'    => round( $room_rate_amount, 2 ),
            'extras_amount'       => round( $extras_amount, 2 ),
            'tourist_tax_amount'  => round( $tourist_tax_amount, 2 ),
        ];
    }

    return $rows;
}

function simple_hotel_crm_analyze_motopress_room_import_row( $row ) {
    global $wpdb;

    $booking_rooms_table = simple_hotel_crm_booking_rooms_table();
    $issues = [];
    $guest = simple_hotel_crm_find_guest_for_import_row( [
        'email' => $row['guest_email'] ?? '',
        'guest_name' => $row['guest_name'] ?? '',
    ] );

    if ( ! $guest ) {
        $issues[] = 'guest_not_found';
    }

    $booking = $guest ? simple_hotel_crm_find_booking_for_import_row( $row, (int) $guest['id'] ) : null;
    if ( ! $booking ) {
        $issues[] = 'booking_header_not_found';
    }

    $room_id = (int) ( $row['room_id'] ?? 0 );
    if ( $room_id <= 0 ) {
        $issues[] = 'room_unmatched';
    }

    $exists = 0;
    if ( $booking && $room_id > 0 ) {
        $exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$booking_rooms_table} WHERE booking_id = %d AND room_id = %d LIMIT 1", (int) $booking['id'], $room_id ) );
    }

    return [
        'guest_match' => ! empty( $guest['id'] ),
        'booking_match' => ! empty( $booking['id'] ),
        'room_match' => $room_id > 0,
        'existing_room_line' => $exists > 0,
        'status' => ! empty( $issues ) ? 'error' : ( $exists > 0 ? 'duplicate' : 'ready' ),
        'issues' => $issues,
    ];
}

function simple_hotel_crm_analyze_motopress_booking_preview_row( $mapped_row ) {
    $mapped_row = is_array( $mapped_row ) ? $mapped_row : [];
    $issues = [];

    if ( empty( $mapped_row['guest_name'] ) ) {
        $issues[] = 'missing_guest_name';
    }
    if ( empty( $mapped_row['check_in'] ) ) {
        $issues[] = 'missing_check_in';
    }
    if ( empty( $mapped_row['check_out'] ) ) {
        $issues[] = 'missing_check_out';
    }

    $guest = simple_hotel_crm_find_guest_for_import_row( [
        'email' => $mapped_row['guest_email'] ?? '',
        'guest_name' => $mapped_row['guest_name'] ?? '',
        'phone' => $mapped_row['phone'] ?? '',
    ] );
    $booking = simple_hotel_crm_find_booking_for_import_row( $mapped_row, (int) ( $guest['id'] ?? 0 ) );

    return [
        'guest_match' => ! empty( $guest['id'] ),
        'booking_match' => ! empty( $booking['id'] ),
        'status' => ! empty( $issues ) ? 'incomplete' : ( ! empty( $booking['id'] ) ? 'duplicate' : 'new' ),
        'issues' => $issues,
    ];
}

function simple_hotel_crm_map_motopress_booking_preview_row( $row ) {
    $row = is_array( $row ) ? $row : [];

    $guest_name = '';
    if ( ! empty( $row['guest_name'] ) ) {
        $guest_name = trim( (string) $row['guest_name'] );
    } elseif ( ! empty( $row['customer']['full_name'] ) ) {
        $guest_name = trim( (string) $row['customer']['full_name'] );
    } else {
        $first_name = trim( (string) ( $row['first_name'] ?? $row['customer']['first_name'] ?? '' ) );
        $last_name  = trim( (string) ( $row['last_name'] ?? $row['customer']['last_name'] ?? '' ) );
        $guest_name = trim( $first_name . ' ' . $last_name );
    }

    if ( '' === $guest_name && ! empty( $row['reserved_accommodations'][0]['guest_name'] ) ) {
        $guest_name = trim( (string) $row['reserved_accommodations'][0]['guest_name'] );
    }

    $status = sanitize_key( (string) ( $row['status'] ?? $row['status_code'] ?? 'confirmed' ) );
    if ( ! isset( simple_hotel_crm_get_booking_status_options()[ $status ] ) ) {
        $status = 'confirmed';
    }

    return [
        'external_booking_id' => (string) ( $row['id'] ?? '' ),
        'guest_name'          => $guest_name,
        'guest_email'         => (string) ( $row['email'] ?? $row['customer']['email'] ?? '' ),
        'phone'               => (string) ( $row['phone'] ?? $row['customer']['phone'] ?? '' ),
        'check_in'            => (string) ( $row['check_in_date'] ?? $row['checkInDate'] ?? '' ),
        'check_out'           => (string) ( $row['check_out_date'] ?? $row['checkOutDate'] ?? '' ),
        'status_code'         => $status,
        'source_channel'      => 'booking_com',
        'booking_note'        => (string) ( $row['note'] ?? $row['booking_note'] ?? '' ),
        'internal_notes'      => (string) ( $row['internal_note'] ?? $row['internal_notes'] ?? '' ),
    ];
}

function simple_hotel_crm_render_motopress_sync_page() {
    if ( ! simple_hotel_crm_user_can_access() ) {
        wp_die( esc_html__( 'You do not have permission to access this page.', 'simple-hotel-crm' ) );
    }

    $consumer_key = get_option( 'simple_hotel_crm_motopress_consumer_key', '' );
    $consumer_secret = get_option( 'simple_hotel_crm_motopress_consumer_secret', '' );
    $test_result = '';
    $test_message = '';
    $preview_rows = [];
    $preview_mapped_rows = [];
    $preview_room_rows = [];
    $preview_room_import_rows = [];
    $preview_room_import_analysis = [];
    $preview_analysis_rows = [];
    $preview_summary = [ 'new' => 0, 'duplicate' => 0, 'incomplete' => 0, 'room_unmatched' => 0, 'room_ready' => 0, 'room_duplicate' => 0, 'room_error' => 0 ];
    $preview_message = '';
    $import_result = null;
    $room_import_result = null;

    if ( isset( $_POST['simple_hotel_crm_motopress_test'] ) ) {
        check_admin_referer( 'simple_hotel_crm_motopress_test' );
        $consumer_key = sanitize_text_field( wp_unslash( $_POST['consumer_key'] ) );
        $consumer_secret = sanitize_text_field( wp_unslash( $_POST['consumer_secret'] ) );

        $response = wp_remote_get( 'https://lagrangefleurie.fr/wp-json/mphb/v1/bookings', [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode( $consumer_key . ':' . $consumer_secret ),
            ],
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) {
            $test_result = 'error';
            $test_message = $response->get_error_message();
        } else {
            $body = wp_remote_retrieve_body( $response );
            $code = wp_remote_retrieve_response_code( $response );
            if ( $code === 200 && ! empty( $body ) ) {
                $test_result = 'success';
                $test_message = __( 'Connection successful! MotoPress API is accessible.', 'simple-hotel-crm' );
            } else {
                $test_result = 'error';
                $test_message = __( 'Failed to connect. Please check your API credentials and ensure the MotoPress REST API is enabled.', 'simple-hotel-crm' );
            }
        }
    }

    if ( isset( $_POST['simple_hotel_crm_motopress_save'] ) ) {
        check_admin_referer( 'simple_hotel_crm_motopress_save' );
        update_option( 'simple_hotel_crm_motopress_consumer_key', sanitize_text_field( wp_unslash( $_POST['consumer_key'] ) ) );
        update_option( 'simple_hotel_crm_motopress_consumer_secret', sanitize_text_field( wp_unslash( $_POST['consumer_secret'] ) ) );
        echo '<div class="notice notice-success"><p>' . esc_html__( 'MotoPress API credentials saved.', 'simple-hotel-crm' ) . '</p></div>';
    }

    if ( isset( $_POST['simple_hotel_crm_motopress_preview'] ) || isset( $_POST['simple_hotel_crm_motopress_import'] ) || isset( $_POST['simple_hotel_crm_motopress_room_import'] ) ) {
        check_admin_referer( isset( $_POST['simple_hotel_crm_motopress_import'] ) ? 'simple_hotel_crm_motopress_import' : ( isset( $_POST['simple_hotel_crm_motopress_room_import'] ) ? 'simple_hotel_crm_motopress_room_import' : 'simple_hotel_crm_motopress_preview' ) );
        $consumer_key = sanitize_text_field( wp_unslash( $_POST['consumer_key'] ?? $consumer_key ) );
        $consumer_secret = sanitize_text_field( wp_unslash( $_POST['consumer_secret'] ?? $consumer_secret ) );
        $per_page = max( 1, min( 50, absint( $_POST['preview_limit'] ?? 10 ) ) );
        $response = wp_remote_get( 'https://lagrangefleurie.fr/wp-json/mphb/v1/bookings?per_page=' . $per_page, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode( $consumer_key . ':' . $consumer_secret ),
            ],
            'timeout' => 20,
        ] );
        if ( is_wp_error( $response ) ) {
            $preview_message = $response->get_error_message();
        } else {
            $code = wp_remote_retrieve_response_code( $response );
            $data = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( 200 === $code && is_array( $data ) ) {
                $preview_rows = $data;
                $preview_mapped_rows = array_map( 'simple_hotel_crm_map_motopress_booking_preview_row', $preview_rows );
                $preview_room_rows = array_map( 'simple_hotel_crm_map_motopress_room_candidates', $preview_rows );
                $preview_analysis_rows = array_map( 'simple_hotel_crm_analyze_motopress_booking_preview_row', $preview_mapped_rows );
                foreach ( $preview_mapped_rows as $preview_index => $mapped_booking_row ) {
                    $preview_room_import_rows[ $preview_index ] = simple_hotel_crm_build_motopress_room_import_rows( $mapped_booking_row, $preview_room_rows[ $preview_index ] ?? [], $preview_rows[ $preview_index ] ?? [] );
                    $preview_room_import_analysis[ $preview_index ] = array_map( 'simple_hotel_crm_analyze_motopress_room_import_row', $preview_room_import_rows[ $preview_index ] );
                }
                foreach ( $preview_analysis_rows as $analysis_index => $analysis_row ) {
                    $status_key = (string) ( $analysis_row['status'] ?? '' );
                    if ( isset( $preview_summary[ $status_key ] ) ) {
                        $preview_summary[ $status_key ]++;
                    }
                    foreach ( (array) ( $preview_room_rows[ $analysis_index ] ?? [] ) as $room_row ) {
                        if ( 'matched' !== ( $room_row['mapping_status'] ?? '' ) ) {
                            $preview_summary['room_unmatched']++;
                        }
                    }
                    foreach ( (array) ( $preview_room_import_analysis[ $analysis_index ] ?? [] ) as $room_analysis_row ) {
                        $room_status = (string) ( $room_analysis_row['status'] ?? '' );
                        if ( 'ready' === $room_status ) {
                            $preview_summary['room_ready']++;
                        } elseif ( 'duplicate' === $room_status ) {
                            $preview_summary['room_duplicate']++;
                        } else {
                            $preview_summary['room_error']++;
                        }
                    }
                }
                $preview_message = sprintf( __( 'Fetched %d MotoPress booking rows for preview.', 'simple-hotel-crm' ), count( $preview_rows ) );

                if ( isset( $_POST['simple_hotel_crm_motopress_import'] ) ) {
                    $import_rows = [];
                    foreach ( $preview_mapped_rows as $index => $mapped_row ) {
                        $analysis_row = $preview_analysis_rows[ $index ] ?? [];
                        if ( 'new' === ( $analysis_row['status'] ?? '' ) ) {
                            $import_rows[] = $mapped_row;
                        }
                    }
                    $import_result = simple_hotel_crm_import_bookings_csv( $import_rows, false );
                    $preview_message = sprintf( __( 'Fetched %1$d MotoPress rows. Attempted header-only import for %2$d new rows.', 'simple-hotel-crm' ), count( $preview_rows ), count( $import_rows ) );
                }

                if ( isset( $_POST['simple_hotel_crm_motopress_room_import'] ) ) {
                    $room_import_rows = [];
                    foreach ( $preview_room_import_rows as $index => $room_rows ) {
                        foreach ( (array) $room_rows as $room_row_index => $room_import_row ) {
                            $room_analysis_row = $preview_room_import_analysis[ $index ][ $room_row_index ] ?? [];
                            if ( 'ready' === ( $room_analysis_row['status'] ?? '' ) ) {
                                $room_import_rows[] = $room_import_row;
                            }
                        }
                    }
                    $room_import_result = simple_hotel_crm_import_booking_rooms_csv( $room_import_rows, false );
                    $preview_message = sprintf( __( 'Fetched %1$d MotoPress rows. Attempted room-line import for %2$d ready rows.', 'simple-hotel-crm' ), count( $preview_rows ), count( $room_import_rows ) );
                }
            } else {
                $preview_message = __( 'Could not fetch preview rows from MotoPress.', 'simple-hotel-crm' );
            }
        }
    }

    echo '<div class="wrap">';
    echo '<h2>' . esc_html__( 'MotoPress Sync', 'simple-hotel-crm' ) . '</h2>';
    echo '<p>' . esc_html__( 'WordPress CRM tables remain the source of truth here. MotoPress is treated as an external source for preview/import, not the primary live datastore.', 'simple-hotel-crm' ) . '</p>';
    echo '<form method="post">';
    wp_nonce_field( 'simple_hotel_crm_motopress_save' );
    echo '<table class="form-table">
        <tr>
            <th><label for="consumer_key">' . esc_html__( 'MotoPress Consumer Key', 'simple-hotel-crm' ) . '</label></th>
            <td><input type="text" name="consumer_key" id="consumer_key" class="regular-text" value="' . esc_attr( $consumer_key ) . '" /></td>
        </tr>
        <tr>
            <th><label for="consumer_secret">' . esc_html__( 'MotoPress Consumer Secret', 'simple-hotel-crm' ) . '</label></th>
            <td><input type="text" name="consumer_secret" id="consumer_secret" class="regular-text" value="' . esc_attr( $consumer_secret ) . '" /></td>
        </tr>
    </table>';
    submit_button( __( 'Save API Credentials', 'simple-hotel-crm' ), 'primary', 'simple_hotel_crm_motopress_save', false );
    echo '</form>';

    echo '<h2>' . esc_html__( 'Test Connection', 'simple-hotel-crm' ) . '</h2>';
    echo '<form method="post">';
    wp_nonce_field( 'simple_hotel_crm_motopress_test' );
    echo '<table class="form-table">
        <tr>
            <th><label for="consumer_key_test">' . esc_html__( 'Consumer Key', 'simple-hotel-crm' ) . '</label></th>
            <td><input type="text" name="consumer_key" id="consumer_key_test" class="regular-text" value="' . esc_attr( $consumer_key ) . '" /></td>
        </tr>
        <tr>
            <th><label for="consumer_secret_test">' . esc_html__( 'Consumer Secret', 'simple-hotel-crm' ) . '</label></th>
            <td><input type="text" name="consumer_secret" id="consumer_secret_test" class="regular-text" value="' . esc_attr( $consumer_secret ) . '" /></td>
        </tr>
    </table>';
    submit_button( __( 'Test MotoPress Connection', 'simple-hotel-crm' ), 'secondary', 'simple_hotel_crm_motopress_test', false );
    echo '</form>';

    if ( ! empty( $test_result ) ) {
        echo '<div class="notice notice-' . esc_attr( $test_result ) . '"><p>' . esc_html( $test_message ) . '</p></div>';
    }

    echo '<h2>' . esc_html__( 'Preview Remote Bookings', 'simple-hotel-crm' ) . '</h2>';
    echo '<p>' . esc_html__( 'This fetches a small sample from MotoPress so you can inspect the remote data shape before we wire full import/reconcile actions.', 'simple-hotel-crm' ) . '</p>';
    echo '<form method="post">';
    wp_nonce_field( 'simple_hotel_crm_motopress_preview' );
    echo '<input type="hidden" name="consumer_key" value="' . esc_attr( $consumer_key ) . '" />';
    echo '<input type="hidden" name="consumer_secret" value="' . esc_attr( $consumer_secret ) . '" />';
    echo '<p><label for="preview_limit">' . esc_html__( 'Preview rows', 'simple-hotel-crm' ) . '</label> <input type="number" min="1" max="20" name="preview_limit" id="preview_limit" value="10" class="small-text" /></p>';
    submit_button( __( 'Fetch Preview', 'simple-hotel-crm' ), 'secondary', 'simple_hotel_crm_motopress_preview', false );
    echo '</form>';
    if ( '' !== $preview_message ) {
        echo '<p><strong>' . esc_html( $preview_message ) . '</strong></p>';
    }
    if ( is_array( $import_result ) ) {
        echo '<div class="notice notice-success"><p>' . esc_html( sprintf( __( 'MotoPress header import — Created: %1$d, Skipped: %2$d', 'simple-hotel-crm' ), (int) $import_result['created'], (int) $import_result['skipped'] ) ) . '</p></div>';
        if ( ! empty( $import_result['errors'] ) ) {
            echo '<div class="notice notice-warning"><ul>';
            foreach ( $import_result['errors'] as $error ) {
                echo '<li>' . esc_html( $error ) . '</li>';
            }
            echo '</ul></div>';
        }
    }
    if ( is_array( $room_import_result ) ) {
        echo '<div class="notice notice-success"><p>' . esc_html( sprintf( __( 'MotoPress room-line import — Created: %1$d, Skipped: %2$d', 'simple-hotel-crm' ), (int) $room_import_result['created'], (int) $room_import_result['skipped'] ) ) . '</p></div>';
        if ( ! empty( $room_import_result['errors'] ) ) {
            echo '<div class="notice notice-warning"><ul>';
            foreach ( $room_import_result['errors'] as $error ) {
                echo '<li>' . esc_html( $error ) . '</li>';
            }
            echo '</ul></div>';
        }
    }
    if ( ! empty( $preview_rows ) ) {
        echo '<div class="notice notice-info"><p><strong>' . esc_html__( 'Dry-run summary', 'simple-hotel-crm' ) . '</strong>: ' . esc_html__( 'New', 'simple-hotel-crm' ) . ' ' . esc_html( (string) (int) $preview_summary['new'] ) . ' · ' . esc_html__( 'Duplicate', 'simple-hotel-crm' ) . ' ' . esc_html( (string) (int) $preview_summary['duplicate'] ) . ' · ' . esc_html__( 'Incomplete', 'simple-hotel-crm' ) . ' ' . esc_html( (string) (int) $preview_summary['incomplete'] ) . ' · ' . esc_html__( 'Unmatched rooms', 'simple-hotel-crm' ) . ' ' . esc_html( (string) (int) $preview_summary['room_unmatched'] ) . ' · ' . esc_html__( 'Room lines ready', 'simple-hotel-crm' ) . ' ' . esc_html( (string) (int) $preview_summary['room_ready'] ) . ' · ' . esc_html__( 'Room lines duplicate', 'simple-hotel-crm' ) . ' ' . esc_html( (string) (int) $preview_summary['room_duplicate'] ) . ' · ' . esc_html__( 'Room lines error', 'simple-hotel-crm' ) . ' ' . esc_html( (string) (int) $preview_summary['room_error'] ) . '</p></div>';
        echo '<form method="post" style="margin:12px 0;">';
        wp_nonce_field( 'simple_hotel_crm_motopress_import' );
        echo '<input type="hidden" name="consumer_key" value="' . esc_attr( $consumer_key ) . '" />';
        echo '<input type="hidden" name="consumer_secret" value="' . esc_attr( $consumer_secret ) . '" />';
        echo '<input type="hidden" name="preview_limit" value="' . esc_attr( (string) max( 1, min( 50, absint( $_POST['preview_limit'] ?? 10 ) ) ) ) . '" />';
        submit_button( __( 'Import New Booking Headers', 'simple-hotel-crm' ), 'primary', 'simple_hotel_crm_motopress_import', false );
        echo ' <span class="description">' . esc_html__( 'Current step imports booking headers only. Room-line write step still disabled below.', 'simple-hotel-crm' ) . '</span>';
        echo '</form>';
        echo '<form method="post" style="margin:12px 0;">';
        wp_nonce_field( 'simple_hotel_crm_motopress_room_import' );
        echo '<input type="hidden" name="consumer_key" value="' . esc_attr( $consumer_key ) . '" />';
        echo '<input type="hidden" name="consumer_secret" value="' . esc_attr( $consumer_secret ) . '" />';
        echo '<input type="hidden" name="preview_limit" value="' . esc_attr( (string) max( 1, min( 50, absint( $_POST['preview_limit'] ?? 10 ) ) ) ) . '" />';
        submit_button( __( 'Import Ready Room Lines', 'simple-hotel-crm' ), 'secondary', 'simple_hotel_crm_motopress_room_import', false );
        echo ' <span class="description">' . esc_html__( 'Imports matched non-duplicate room lines only.', 'simple-hotel-crm' ) . '</span>';
        echo '</form>';
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>' . esc_html__( 'Check-in', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Check-out', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Remote status', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Import readiness', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Room mapping preview', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Room-line dry run', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Mapped CRM import shape', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Raw sample', 'simple-hotel-crm' ) . '</th></tr></thead><tbody>';
        foreach ( $preview_rows as $index => $preview_row ) {
            $mapped_row = $preview_mapped_rows[ $index ] ?? [];
            $analysis = $preview_analysis_rows[ $index ] ?? [];
            $room_rows = $preview_room_rows[ $index ] ?? [];
            $room_import_analysis = $preview_room_import_analysis[ $index ] ?? [];
            echo '<tr>';
            echo '<td>' . esc_html( (string) ( $preview_row['id'] ?? '' ) ) . '</td>';
            echo '<td>' . esc_html( (string) ( $preview_row['check_in_date'] ?? $preview_row['checkInDate'] ?? '' ) ) . '</td>';
            echo '<td>' . esc_html( (string) ( $preview_row['check_out_date'] ?? $preview_row['checkOutDate'] ?? '' ) ) . '</td>';
            echo '<td>' . esc_html( (string) ( $preview_row['status'] ?? $preview_row['status_code'] ?? '' ) ) . '</td>';
            echo '<td><strong>' . esc_html( (string) ( $analysis['status'] ?? '' ) ) . '</strong><br />' . esc_html__( 'Guest match:', 'simple-hotel-crm' ) . ' ' . esc_html( ! empty( $analysis['guest_match'] ) ? 'yes' : 'no' ) . '<br />' . esc_html__( 'Booking match:', 'simple-hotel-crm' ) . ' ' . esc_html( ! empty( $analysis['booking_match'] ) ? 'yes' : 'no' ) . ( ! empty( $analysis['issues'] ) ? '<br />' . esc_html__( 'Issues:', 'simple-hotel-crm' ) . ' ' . esc_html( implode( ', ', (array) $analysis['issues'] ) ) : '' ) . '</td>';
            echo '<td><code style="white-space:pre-wrap;display:block;max-width:280px;">' . esc_html( wp_json_encode( $room_rows ) ) . '</code></td>';
            echo '<td><code style="white-space:pre-wrap;display:block;max-width:280px;">' . esc_html( wp_json_encode( $room_import_analysis ) ) . '</code></td>';
            echo '<td><code style="white-space:pre-wrap;display:block;max-width:360px;">' . esc_html( wp_json_encode( $mapped_row ) ) . '</code></td>';
            echo '<td><code style="white-space:pre-wrap;display:block;max-width:360px;">' . esc_html( wp_json_encode( $preview_row ) ) . '</code></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    echo '<h2>' . esc_html__( 'How to Get API Credentials', 'simple-hotel-crm' ) . '</h2>';
    echo '<ol>
        <li>' . esc_html__( 'Log in to your WordPress admin at Lagrange Fleurie.', 'simple-hotel-crm' ) . '</li>
        <li>' . esc_html__( 'Go to MotoPress → Settings → API.', 'simple-hotel-crm' ) . '</li>
        <li>' . esc_html__( 'Create a new API user or use an existing one.', 'simple-hotel-crm' ) . '</li>
        <li>' . esc_html__( 'Copy the Consumer Key and Consumer Secret.', 'simple-hotel-crm' ) . '</li>
        <li>' . esc_html__( 'Paste them into the fields above and click Save.', 'simple-hotel-crm' ) . '</li>
    </ol>';
    echo '</div>';
}

function simple_hotel_crm_render_import_page() {
    if ( ! simple_hotel_crm_user_can_access() ) {
        wp_die( esc_html__( 'You do not have permission to access this page.', 'simple-hotel-crm' ) );
    }

    echo '<div class="wrap"><h1>' . esc_html__( 'Import CSV', 'simple-hotel-crm' ) . '</h1>';
    simple_hotel_crm_render_import_panel();
    echo '</div>';
}

function simple_hotel_crm_get_export_datasets() {
    global $wpdb;

    return [
        'all' => [
            'label' => __( 'Everything', 'simple-hotel-crm' ),
            'data' => [
                'exported_at' => current_time( 'mysql' ),
                'site_url' => site_url(),
                'plugin_version' => SIMPLE_HOTEL_CRM_VERSION,
                'rooms' => $wpdb->get_results( 'SELECT * FROM ' . simple_hotel_crm_rooms_table() . ' ORDER BY sort_order ASC, room_name ASC', ARRAY_A ),
                'guests' => $wpdb->get_results( 'SELECT * FROM ' . simple_hotel_crm_guests_table() . ' ORDER BY id ASC', ARRAY_A ),
                'bookings' => $wpdb->get_results( 'SELECT * FROM ' . simple_hotel_crm_bookings_table() . ' ORDER BY id ASC', ARRAY_A ),
                'booking_rooms' => $wpdb->get_results( 'SELECT * FROM ' . simple_hotel_crm_booking_rooms_table() . ' ORDER BY id ASC', ARRAY_A ),
                'booking_room_nights' => $wpdb->get_results( 'SELECT * FROM ' . simple_hotel_crm_booking_room_nights_table() . ' ORDER BY id ASC', ARRAY_A ),
                'daily_notes' => $wpdb->get_results( 'SELECT * FROM ' . simple_hotel_crm_daily_notes_table() . ' ORDER BY note_date ASC', ARRAY_A ),
                'overlays' => $wpdb->get_results( 'SELECT * FROM ' . simple_hotel_crm_booking_overlay_table() . ' ORDER BY id ASC', ARRAY_A ),
                'settings' => [
                    [ 'option_name' => 'invoice_ninja_url', 'option_value' => get_option( 'simple_hotel_crm_invoice_ninja_url', '' ) ],
                    [ 'option_name' => 'booking_source', 'option_value' => get_option( 'simple_hotel_crm_booking_source', 'wp_sync' ) ],
                ],
            ],
        ],
        'rooms' => [ 'label' => __( 'Rooms', 'simple-hotel-crm' ), 'data' => $wpdb->get_results( 'SELECT * FROM ' . simple_hotel_crm_rooms_table() . ' ORDER BY sort_order ASC, room_name ASC', ARRAY_A ) ],
        'guests' => [ 'label' => __( 'Guests', 'simple-hotel-crm' ), 'data' => $wpdb->get_results( 'SELECT * FROM ' . simple_hotel_crm_guests_table() . ' ORDER BY id ASC', ARRAY_A ) ],
        'bookings' => [ 'label' => __( 'Bookings', 'simple-hotel-crm' ), 'data' => $wpdb->get_results( 'SELECT * FROM ' . simple_hotel_crm_bookings_table() . ' ORDER BY id ASC', ARRAY_A ) ],
        'booking_rooms' => [ 'label' => __( 'Booking rooms', 'simple-hotel-crm' ), 'data' => $wpdb->get_results( 'SELECT * FROM ' . simple_hotel_crm_booking_rooms_table() . ' ORDER BY id ASC', ARRAY_A ) ],
        'booking_room_nights' => [ 'label' => __( 'Booking room nights', 'simple-hotel-crm' ), 'data' => $wpdb->get_results( 'SELECT * FROM ' . simple_hotel_crm_booking_room_nights_table() . ' ORDER BY id ASC', ARRAY_A ) ],
        'daily_notes' => [ 'label' => __( 'Daily notes', 'simple-hotel-crm' ), 'data' => $wpdb->get_results( 'SELECT * FROM ' . simple_hotel_crm_daily_notes_table() . ' ORDER BY note_date ASC', ARRAY_A ) ],
        'overlays' => [ 'label' => __( 'Overlays', 'simple-hotel-crm' ), 'data' => $wpdb->get_results( 'SELECT * FROM ' . simple_hotel_crm_booking_overlay_table() . ' ORDER BY id ASC', ARRAY_A ) ],
        'settings' => [ 'label' => __( 'Settings', 'simple-hotel-crm' ), 'data' => [
            [ 'option_name' => 'invoice_ninja_url', 'option_value' => get_option( 'simple_hotel_crm_invoice_ninja_url', '' ) ],
            [ 'option_name' => 'booking_source', 'option_value' => get_option( 'simple_hotel_crm_booking_source', 'wp_sync' ) ],
        ] ],
    ];
}

function simple_hotel_crm_output_csv_export( $rows, $filename ) {
    nocache_headers();
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename=' . $filename );
    $out = fopen( 'php://output', 'w' );
    if ( empty( $rows ) ) {
        fputcsv( $out, [ 'no_data' ] );
        fclose( $out );
        exit;
    }
    fputcsv( $out, array_keys( $rows[0] ) );
    foreach ( $rows as $row ) {
        fputcsv( $out, $row );
    }
    fclose( $out );
    exit;
}

function simple_hotel_crm_render_export_panel() {
    if ( isset( $_POST['simple_hotel_crm_export_download'] ) ) {
        check_admin_referer( 'simple_hotel_crm_export', 'simple_hotel_crm_export_nonce' );

        $datasets = simple_hotel_crm_get_export_datasets();
        $export_type = sanitize_key( wp_unslash( $_POST['export_type'] ?? 'all' ) );
        $export_format = sanitize_key( wp_unslash( $_POST['export_format'] ?? 'json' ) );
        if ( ! isset( $datasets[ $export_type ] ) ) {
            $export_type = 'all';
        }

        $payload = $datasets[ $export_type ]['data'];
        $timestamp = gmdate( 'Ymd-His' );
        if ( 'csv' === $export_format && 'all' !== $export_type && is_array( $payload ) ) {
            simple_hotel_crm_output_csv_export( $payload, 'simple-hotel-crm-' . $export_type . '-' . $timestamp . '.csv' );
        }

        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=simple-hotel-crm-' . $export_type . '-' . $timestamp . '.json' );
        echo wp_json_encode( $payload, JSON_PRETTY_PRINT );
        exit;
    }

    echo '<p>' . esc_html__( 'Choose which data to export and whether to download it as JSON or CSV.', 'simple-hotel-crm' ) . '</p>';
    echo '<form method="post">';
    wp_nonce_field( 'simple_hotel_crm_export', 'simple_hotel_crm_export_nonce' );
    echo '<table class="form-table">';
    echo '<tr><th><label for="export_type">' . esc_html__( 'Export data', 'simple-hotel-crm' ) . '</label></th><td><select name="export_type" id="export_type">';
    foreach ( simple_hotel_crm_get_export_datasets() as $key => $dataset ) {
        echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $dataset['label'] ) . '</option>';
    }
    echo '</select></td></tr>';
    echo '<tr><th><label for="export_format">' . esc_html__( 'Format', 'simple-hotel-crm' ) . '</label></th><td><select name="export_format" id="export_format"><option value="json">JSON</option><option value="csv">CSV</option></select></td></tr>';
    echo '</table>';
    submit_button( __( 'Download Export', 'simple-hotel-crm' ), 'primary', 'simple_hotel_crm_export_download', false );
    echo '</form>';
}

function simple_hotel_crm_render_settings_page() {
    global $wpdb;

    if ( ! simple_hotel_crm_user_can_access() ) {
        wp_die( esc_html__( 'You do not have permission to access this page.', 'simple-hotel-crm' ) );
    }

    $tab = isset( $_GET['tab'] ) && in_array( $_GET['tab'], [ 'general', 'invoice-ninja', 'import', 'export', 'motopress' ], true ) ? sanitize_key( $_GET['tab'] ) : 'general';

    if ( isset( $_POST['simple_hotel_crm_submit'] ) ) {
        check_admin_referer( 'simple_hotel_crm_settings', 'simple_hotel_crm_settings_nonce' );

        $api_url = isset( $_POST['simple_hotel_crm_invoice_ninja_url'] ) ? esc_url_raw( trim( wp_unslash( $_POST['simple_hotel_crm_invoice_ninja_url'] ) ) ) : '';
        $api_token = isset( $_POST['simple_hotel_crm_invoice_ninja_token'] ) ? sanitize_text_field( trim( wp_unslash( $_POST['simple_hotel_crm_invoice_ninja_token'] ) ) ) : '';
        $booking_com_commission_percent = isset( $_POST['simple_hotel_crm_booking_com_commission_percent'] ) ? max( 0, min( 100, (float) str_replace( ',', '.', wp_unslash( $_POST['simple_hotel_crm_booking_com_commission_percent'] ) ) ) ) : 15;
        $submitted_ics_urls = isset( $_POST['simple_hotel_crm_booking_com_ics_urls'] ) && is_array( $_POST['simple_hotel_crm_booking_com_ics_urls'] ) ? wp_unslash( $_POST['simple_hotel_crm_booking_com_ics_urls'] ) : [];
        $booking_com_ics_urls = [];
        foreach ( $submitted_ics_urls as $room_id => $room_url ) {
            $room_id = absint( $room_id );
            $room_url = esc_url_raw( trim( (string) $room_url ) );
            if ( $room_id > 0 && '' !== $room_url ) {
                $booking_com_ics_urls[ $room_id ] = $room_url;
            }
        }

        update_option( 'simple_hotel_crm_invoice_ninja_url', $api_url );
        update_option( 'simple_hotel_crm_invoice_ninja_token', $api_token );
        update_option( 'simple_hotel_crm_booking_com_commission_percent', $booking_com_commission_percent );
        update_option( 'simple_hotel_crm_booking_com_ics_room_urls', $booking_com_ics_urls );
        update_option( 'simple_hotel_crm_booking_source', 'wp_sync' );

        simple_hotel_crm_clear_calendar_cache();
        echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.', 'simple-hotel-crm' ) . '</p></div>';
    }

    $repair_results = null;
    $ics_import_results = null;
    $ics_debug_preview = [];
    if ( isset( $_POST['simple_hotel_crm_run_booking_com_ics_import'] ) ) {
        check_admin_referer( 'simple_hotel_crm_run_booking_com_ics_import', 'simple_hotel_crm_run_booking_com_ics_import_nonce' );
        $ics_import_results = simple_hotel_crm_import_booking_com_ics_feeds();
        echo '<div class="notice notice-success"><p>' . esc_html( sprintf( __( 'Booking.com ICS import complete. Feeds: %1$d, Events: %2$d, Staged nights: %3$d, Skipped: %4$d, Cancelled: %5$d', 'simple-hotel-crm' ), (int) ( $ics_import_results['feeds'] ?? 0 ), (int) ( $ics_import_results['events'] ?? 0 ), (int) ( $ics_import_results['staged'] ?? 0 ), (int) ( $ics_import_results['skipped'] ?? 0 ), (int) ( $ics_import_results['cancelled'] ?? 0 ) ) ) . '</p></div>';
        if ( ! empty( $ics_import_results['errors'] ) ) {
            echo '<div class="notice notice-warning"><ul>';
            foreach ( $ics_import_results['errors'] as $error ) {
                echo '<li>' . esc_html( $error ) . '</li>';
            }
            echo '</ul></div>';
        }
    }
    if ( isset( $_POST['simple_hotel_crm_rebuild_booking_com_ics_import'] ) ) {
        check_admin_referer( 'simple_hotel_crm_rebuild_booking_com_ics_import', 'simple_hotel_crm_rebuild_booking_com_ics_import_nonce' );
        echo '<div class="notice notice-error"><p>' . esc_html__( 'Booking.com ICS rebuild is disabled for safety. Use normal import only.', 'simple-hotel-crm' ) . '</p></div>';
    }
    if ( isset( $_POST['simple_hotel_crm_preview_booking_com_ics_grouping'] ) ) {
        check_admin_referer( 'simple_hotel_crm_preview_booking_com_ics_grouping', 'simple_hotel_crm_preview_booking_com_ics_grouping_nonce' );
        $ics_debug_preview = simple_hotel_crm_get_booking_com_ics_debug_preview();
    }

    if ( isset( $_POST['simple_hotel_crm_run_repairs'] ) ) {
        check_admin_referer( 'simple_hotel_crm_run_repairs', 'simple_hotel_crm_run_repairs_nonce' );
        simple_hotel_crm_install_tables();
        $repair_results = simple_hotel_crm_run_repair_routines();
        simple_hotel_crm_clear_calendar_cache();
        echo '<div class="notice notice-success"><p>' . esc_html__( 'Schema upgrade and repair routines completed.', 'simple-hotel-crm' ) . '</p><ul><li>' . esc_html__( 'Pricing rows updated:', 'simple-hotel-crm' ) . ' ' . esc_html( (string) (int) ( $repair_results['pricing_rows'] ?? 0 ) ) . '</li><li>' . esc_html__( 'Commission rows updated:', 'simple-hotel-crm' ) . ' ' . esc_html( (string) (int) ( $repair_results['commission_rows'] ?? 0 ) ) . '</li><li>' . esc_html__( 'Booking headers recalculated:', 'simple-hotel-crm' ) . ' ' . esc_html( (string) (int) ( $repair_results['booking_headers'] ?? 0 ) ) . '</li></ul></div>';
    }
    if ( isset( $_POST['simple_hotel_crm_reset_crm_data'] ) ) {
        check_admin_referer( 'simple_hotel_crm_reset_crm_data', 'simple_hotel_crm_reset_crm_data_nonce' );
        $reset_results = simple_hotel_crm_reset_crm_data();
        $failed_tables = [];
        foreach ( $reset_results as $table => $status ) {
            if ( ! $status ) {
                $failed_tables[] = $table;
            }
        }
        if ( empty( $failed_tables ) ) {
            echo '<div class="notice notice-success"><p>' . esc_html__( 'CRM booking data reset complete. Rooms, pricing, and settings kept.', 'simple-hotel-crm' ) . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'CRM reset finished with errors.', 'simple-hotel-crm' ) . '</p><ul>';
            foreach ( $failed_tables as $failed_table ) {
                echo '<li>' . esc_html( (string) $failed_table ) . '</li>';
            }
            echo '</ul></div>';
        }
    }

    $api_url = get_option( 'simple_hotel_crm_invoice_ninja_url', '' );
    $api_token = get_option( 'simple_hotel_crm_invoice_ninja_token', '' );
    $booking_com_commission_percent = get_option( 'simple_hotel_crm_booking_com_commission_percent', 15 );
    $booking_rooms_table = simple_hotel_crm_booking_rooms_table();
    $booking_nights_table = simple_hotel_crm_booking_room_nights_table();
    $schema_status = [
        'booking_rooms_commission' => simple_hotel_crm_table_has_column( $booking_rooms_table, 'commission_amount' ),
        'booking_rooms_subtotal'   => simple_hotel_crm_table_has_column( $booking_rooms_table, 'subtotal_amount' ),
        'booking_nights_commission'=> simple_hotel_crm_table_has_column( $booking_nights_table, 'commission_amount' ),
        'booking_nights_subtotal'  => simple_hotel_crm_table_has_column( $booking_nights_table, 'subtotal_amount' ),
    ];
    $repair_scan_counts = simple_hotel_crm_get_repair_scan_counts();
    $booking_com_ics_urls = simple_hotel_crm_get_booking_com_ics_room_urls();
    $rooms_for_ics = $wpdb->get_results( 'SELECT id, room_code, room_name, active FROM ' . simple_hotel_crm_rooms_table() . ' ORDER BY sort_order ASC, room_name ASC', ARRAY_A );

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__( 'LGF Bookings Settings', 'simple-hotel-crm' ) . '</h1>';
    echo '<p>' . esc_html__( 'LGF Bookings now uses the WordPress CRM tables as the main booking source.', 'simple-hotel-crm' ) . '</p>';
    echo '<p><strong>' . esc_html__( 'Plugin version:', 'simple-hotel-crm' ) . '</strong> ' . esc_html( SIMPLE_HOTEL_CRM_VERSION ) . ' &nbsp; <strong>' . esc_html__( 'DB version:', 'simple-hotel-crm' ) . '</strong> ' . esc_html( SIMPLE_HOTEL_CRM_DB_VERSION ) . '</p>';
    echo '<nav class="nav-tab-wrapper">';
    echo '<a href="' . esc_url( admin_url( 'admin.php?page=simple-hotel-crm-settings&tab=general' ) ) . '" class="nav-tab ' . ( 'general' === $tab ? 'nav-tab-active' : '' ) . '">' . esc_html__( 'General', 'simple-hotel-crm' ) . '</a>';
    echo '<a href="' . esc_url( admin_url( 'admin.php?page=simple-hotel-crm-settings&tab=motopress' ) ) . '" class="nav-tab ' . ( 'motopress' === $tab ? 'nav-tab-active' : '' ) . '">' . esc_html__( 'MotoPress Sync', 'simple-hotel-crm' ) . '</a>';
    echo '<a href="' . esc_url( admin_url( 'admin.php?page=simple-hotel-crm-settings&tab=invoice-ninja' ) ) . '" class="nav-tab ' . ( 'invoice-ninja' === $tab ? 'nav-tab-active' : '' ) . '">' . esc_html__( 'Invoice Ninja', 'simple-hotel-crm' ) . '</a>';
    echo '<a href="' . esc_url( admin_url( 'admin.php?page=simple-hotel-crm-settings&tab=import' ) ) . '" class="nav-tab ' . ( 'import' === $tab ? 'nav-tab-active' : '' ) . '">' . esc_html__( 'Import', 'simple-hotel-crm' ) . '</a>';
    echo '<a href="' . esc_url( admin_url( 'admin.php?page=simple-hotel-crm-settings&tab=export' ) ) . '" class="nav-tab ' . ( 'export' === $tab ? 'nav-tab-active' : '' ) . '">' . esc_html__( 'Export', 'simple-hotel-crm' ) . '</a>';
    echo '</nav>';

    if ( 'import' === $tab ) {
        simple_hotel_crm_render_import_panel();
    } elseif ( 'export' === $tab ) {
        simple_hotel_crm_render_export_panel();
    } elseif ( 'motopress' === $tab ) {
        simple_hotel_crm_render_motopress_sync_page();
    } elseif ( 'invoice-ninja' === $tab ) {
        echo '<form method="post">';
        wp_nonce_field( 'simple_hotel_crm_settings', 'simple_hotel_crm_settings_nonce' );
        echo '<h2>' . esc_html__( 'Invoice Ninja', 'simple-hotel-crm' ) . '</h2>';
        echo '<table class="form-table">';
        echo '<tr><th scope="row"><label for="simple_hotel_crm_invoice_ninja_url">' . esc_html__( 'Invoice Ninja URL', 'simple-hotel-crm' ) . '</label></th>';
        echo '<td><input type="url" id="simple_hotel_crm_invoice_ninja_url" name="simple_hotel_crm_invoice_ninja_url" value="' . esc_attr( $api_url ) . '" class="regular-text" placeholder="https://your-invoice-ninja.com" /></td></tr>';
        echo '<tr><th scope="row"><label for="simple_hotel_crm_invoice_ninja_token">' . esc_html__( 'API Token', 'simple-hotel-crm' ) . '</label></th>';
        echo '<td><input type="password" id="simple_hotel_crm_invoice_ninja_token" name="simple_hotel_crm_invoice_ninja_token" value="' . esc_attr( $api_token ) . '" class="regular-text" /></td></tr>';
        echo '</table>';
        submit_button( __( 'Save Invoice Ninja Settings', 'simple-hotel-crm' ), 'primary', 'simple_hotel_crm_submit' );
        echo '</form>';
    } else {
        echo '<form method="post">';
        wp_nonce_field( 'simple_hotel_crm_settings', 'simple_hotel_crm_settings_nonce' );
        echo '<h2>' . esc_html__( 'General', 'simple-hotel-crm' ) . '</h2>';
        echo '<table class="form-table">';
        echo '<tr><th scope="row">' . esc_html__( 'Plugin version', 'simple-hotel-crm' ) . '</th><td><code>' . esc_html( SIMPLE_HOTEL_CRM_VERSION ) . '</code></td></tr>';
        echo '<tr><th scope="row">' . esc_html__( 'DB version', 'simple-hotel-crm' ) . '</th><td><code>' . esc_html( SIMPLE_HOTEL_CRM_DB_VERSION ) . '</code></td></tr>';
        echo '<tr><th scope="row"><label for="simple_hotel_crm_booking_com_commission_percent">' . esc_html__( 'Booking.com commission %', 'simple-hotel-crm' ) . '</label></th>';
        echo '<td><input type="number" step="0.01" min="0" max="100" id="simple_hotel_crm_booking_com_commission_percent" name="simple_hotel_crm_booking_com_commission_percent" value="' . esc_attr( (string) $booking_com_commission_percent ) . '" class="small-text" /> <p class="description">' . esc_html__( 'Used for automatic commission calculation on Booking.com channel. Applied to discounted room charge only, not extras or tax.', 'simple-hotel-crm' ) . '</p></td></tr>';
        echo '<tr><th scope="row">' . esc_html__( 'Booking.com ICS room feeds', 'simple-hotel-crm' ) . '</th><td>';
        foreach ( $rooms_for_ics as $room_row ) {
            if ( empty( $room_row['active'] ) || 'Coquelicot' === (string) $room_row['room_name'] ) {
                continue;
            }
            echo '<p><label><strong>' . esc_html( (string) $room_row['room_name'] ) . '</strong> <code>' . esc_html( (string) $room_row['room_code'] ) . '</code><br /><input type="url" class="regular-text" name="simple_hotel_crm_booking_com_ics_urls[' . esc_attr( (string) $room_row['id'] ) . ']" value="' . esc_attr( (string) ( $booking_com_ics_urls[ $room_row['id'] ] ?? '' ) ) . '" placeholder="https://...ics" style="min-width:420px;" /></label></p>';
        }
        echo '<p class="description">' . esc_html__( 'One Booking.com ICS export URL per room. Used to stage bookings into sync_bookings and auto-import them into CRM.', 'simple-hotel-crm' ) . '</p></td></tr>';
        echo '</table>';
        submit_button( __( 'Save General Settings', 'simple-hotel-crm' ), 'primary', 'simple_hotel_crm_submit' );
        echo '</form>';

        echo '<hr />';
        echo '<h2>' . esc_html__( 'Repair Tools', 'simple-hotel-crm' ) . '</h2>';
        echo '<p>' . esc_html__( 'Use this to upgrade database tables and run built-in backfills for pricing and commission fields.', 'simple-hotel-crm' ) . '</p>';
        echo '<ul>';
        echo '<li>booking_rooms.commission_amount: ' . esc_html( $schema_status['booking_rooms_commission'] ? 'OK' : 'Missing' ) . '</li>';
        echo '<li>booking_rooms.subtotal_amount: ' . esc_html( $schema_status['booking_rooms_subtotal'] ? 'OK' : 'Missing' ) . '</li>';
        echo '<li>booking_room_nights.commission_amount: ' . esc_html( $schema_status['booking_nights_commission'] ? 'OK' : 'Missing' ) . '</li>';
        echo '<li>booking_room_nights.subtotal_amount: ' . esc_html( $schema_status['booking_nights_subtotal'] ? 'OK' : 'Missing' ) . '</li>';
        echo '</ul>';
        echo '<h3>' . esc_html__( 'Repair scan', 'simple-hotel-crm' ) . '</h3>';
        echo '<ul>';
        echo '<li>' . esc_html__( 'Pricing rows needing normalization:', 'simple-hotel-crm' ) . ' ' . esc_html( (string) (int) $repair_scan_counts['pricing_rows'] ) . '</li>';
        echo '<li>' . esc_html__( 'Commission rows needing backfill:', 'simple-hotel-crm' ) . ' ' . esc_html( (string) (int) $repair_scan_counts['commission_rows'] ) . '</li>';
        echo '<li>' . esc_html__( 'Booking headers needing recalculation:', 'simple-hotel-crm' ) . ' ' . esc_html( (string) (int) $repair_scan_counts['booking_headers'] ) . '</li>';
        echo '</ul>';
        echo '<p>' . esc_html__( 'Fetch Booking.com ICS room feeds, stage nights, then create only missing booking skeletons in CRM. Existing enriched bookings are skipped.', 'simple-hotel-crm' ) . '</p>';
        echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=simple-hotel-crm-booking-transfers' ) ) . '">' . esc_html__( 'Open Booking Transfers', 'simple-hotel-crm' ) . '</a> <a class="button" href="' . esc_url( admin_url( 'admin.php?page=simple-hotel-crm-booking-merges' ) ) . '">' . esc_html__( 'Open Booking Merges', 'simple-hotel-crm' ) . '</a></p>';
        echo '<form method="post">';
        wp_nonce_field( 'simple_hotel_crm_run_booking_com_ics_import', 'simple_hotel_crm_run_booking_com_ics_import_nonce' );
        submit_button( __( 'Sync Booking.com ICS Skeletons', 'simple-hotel-crm' ), 'secondary', 'simple_hotel_crm_run_booking_com_ics_import', false );
        echo '</form>';
        echo '<form method="post" style="margin-top:8px;">';
        wp_nonce_field( 'simple_hotel_crm_preview_booking_com_ics_grouping', 'simple_hotel_crm_preview_booking_com_ics_grouping_nonce' );
        submit_button( __( 'Preview ICS Grouping', 'simple-hotel-crm' ), 'secondary', 'simple_hotel_crm_preview_booking_com_ics_grouping', false );
        echo '</form>';
        if ( ! empty( $ics_debug_preview ) ) {
            echo '<h3>' . esc_html__( 'ICS grouping preview', 'simple-hotel-crm' ) . '</h3>';
            echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Room', 'simple-hotel-crm' ) . '</th><th>UID</th><th>' . esc_html__( 'Proposed group key', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Check-in', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Check-out', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Summary', 'simple-hotel-crm' ) . '</th></tr></thead><tbody>';
            foreach ( $ics_debug_preview as $preview_row ) {
                echo '<tr><td>' . esc_html( (string) $preview_row['room_name'] ) . '</td><td><code>' . esc_html( (string) $preview_row['uid'] ) . '</code></td><td><code>' . esc_html( (string) $preview_row['group_key'] ) . '</code></td><td>' . esc_html( (string) $preview_row['check_in'] ) . '</td><td>' . esc_html( (string) $preview_row['check_out'] ) . '</td><td>' . esc_html( (string) $preview_row['summary'] ) . '</td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '<p><button type="button" class="button" disabled>' . esc_html__( 'Rebuild Booking.com ICS Staging + Reimport', 'simple-hotel-crm' ) . '</button> <span class="description">' . esc_html__( 'Disabled for safety to avoid overwriting enriched Booking.com bookings.', 'simple-hotel-crm' ) . '</span></p>';

        echo '<form method="post">';
        wp_nonce_field( 'simple_hotel_crm_run_repairs', 'simple_hotel_crm_run_repairs_nonce' );
        submit_button( __( 'Run Schema Upgrade + Repairs', 'simple-hotel-crm' ), 'secondary', 'simple_hotel_crm_run_repairs' );
        echo '</form>';

        echo '<hr />';
        echo '<h2>' . esc_html__( 'Reset CRM Data', 'simple-hotel-crm' ) . '</h2>';
        echo '<p><strong>' . esc_html__( 'Warning:', 'simple-hotel-crm' ) . '</strong> ' . esc_html__( 'This permanently deletes bookings, booking rooms, nightly rows, overlays, staged sync rows, and guests. Rooms, pricing, and settings stay.', 'simple-hotel-crm' ) . '</p>';
        echo '<form method="post" onsubmit="return confirm(' . wp_json_encode( __( 'Permanently delete CRM booking data and guests? This cannot be undone.', 'simple-hotel-crm' ) ) . ');">';
        wp_nonce_field( 'simple_hotel_crm_reset_crm_data', 'simple_hotel_crm_reset_crm_data_nonce' );
        submit_button( __( 'Reset CRM Data', 'simple-hotel-crm' ), 'delete', 'simple_hotel_crm_reset_crm_data' );
        echo '</form>';
    }
    echo '</div>';
}

add_shortcode( 'simple_hotel_crm', 'simple_hotel_crm_shortcode' );
add_shortcode( 'lgf_bookings', 'simple_hotel_crm_shortcode' );
add_shortcode( 'lgf_calendar_view', 'simple_hotel_crm_shortcode' );
function simple_hotel_crm_render_calendar( $calendar_data, $context = 'frontend' ) {
    $template = locate_template( 'simple-hotel-crm/booking-view.php' );
    if ( ! $template ) {
        $template = locate_template( 'lgf-calendar-view/booking-view.php' );
    }
    if ( ! $template ) {
        $template = dirname( __DIR__ ) . '/templates/booking-view.php';
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

