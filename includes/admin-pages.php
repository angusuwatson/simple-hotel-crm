<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'admin_menu', 'simple_hotel_crm_register_admin_menu' );
function simple_hotel_crm_register_admin_menu() {
    if ( ! simple_hotel_crm_user_can_access() ) {
        return;
    }

    add_menu_page( __( 'Simple Hotel CRM', 'simple-hotel-crm' ), __( 'Simple Hotel CRM', 'simple-hotel-crm' ), 'manage_options', 'simple-hotel-crm', 'simple_hotel_crm_render_admin_page', 'dashicons-calendar-alt', 58 );
    add_submenu_page( 'simple-hotel-crm', __( 'Calendar', 'simple-hotel-crm' ), __( 'Calendar', 'simple-hotel-crm' ), 'manage_options', 'simple-hotel-crm', 'simple_hotel_crm_render_admin_page' );
    add_submenu_page( 'simple-hotel-crm', __( 'Add Booking', 'simple-hotel-crm' ), __( 'Add Booking', 'simple-hotel-crm' ), 'manage_options', 'simple-hotel-crm-add-booking', 'simple_hotel_crm_render_add_booking_page' );
    add_submenu_page( 'simple-hotel-crm', __( 'Bookings', 'simple-hotel-crm' ), __( 'Bookings', 'simple-hotel-crm' ), 'manage_options', 'simple-hotel-crm-bookings', 'simple_hotel_crm_render_bookings_page' );
    add_submenu_page( null, __( 'Booking Detail', 'simple-hotel-crm' ), __( 'Booking Detail', 'simple-hotel-crm' ), 'manage_options', 'simple-hotel-crm-booking-detail', 'simple_hotel_crm_render_booking_detail_page' );
    add_submenu_page( 'simple-hotel-crm', __( 'Rooms', 'simple-hotel-crm' ), __( 'Rooms', 'simple-hotel-crm' ), 'manage_options', 'simple-hotel-crm-rooms', 'simple_hotel_crm_render_rooms_page' );
    add_submenu_page( 'simple-hotel-crm', __( 'Guests', 'simple-hotel-crm' ), __( 'Guests', 'simple-hotel-crm' ), 'manage_options', 'simple-hotel-crm-guests', 'simple_hotel_crm_render_guests_page' );
    add_submenu_page( null, __( 'Guest Detail', 'simple-hotel-crm' ), __( 'Guest Detail', 'simple-hotel-crm' ), 'manage_options', 'simple-hotel-crm-guest-detail', 'simple_hotel_crm_render_guest_detail_page' );
    add_submenu_page( 'simple-hotel-crm', __( 'Import', 'simple-hotel-crm' ), __( 'Import', 'simple-hotel-crm' ), 'manage_options', 'simple-hotel-crm-import', 'simple_hotel_crm_render_import_page' );
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
    $view = ( isset( $_GET['view'] ) && 'trash' === $_GET['view'] ) ? 'trash' : 'active';
    $is_deleted = 'trash' === $view ? 1 : 0;
    $sortable = [ 'id' => 'b.id', 'guest' => 'guest_name', 'check_in' => 'b.check_in_date', 'check_out' => 'b.check_out_date', 'rooms' => 'room_count', 'status' => 'b.status_code', 'channel' => 'b.source_channel', 'total' => 'b.total_amount' ];
    $orderby_key = isset( $_GET['orderby'] ) && isset( $sortable[ $_GET['orderby'] ] ) ? $_GET['orderby'] : 'check_in';
    $order = isset( $_GET['order'] ) && 'asc' === strtolower( (string) $_GET['order'] ) ? 'ASC' : 'DESC';
    $order_sql = $sortable[ $orderby_key ];

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
            $wpdb->query( "UPDATE {$bookings_table} SET is_deleted = 0, deleted_at = NULL WHERE id IN (" . implode( ',', $booking_ids ) . ")" );
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Selected bookings restored.', 'simple-hotel-crm' ) . '</p></div>';
        }
        simple_hotel_crm_clear_calendar_cache();
    }

    if ( isset( $_GET['restore_booking'] ) ) {
        check_admin_referer( 'simple_hotel_crm_restore_booking_' . absint( $_GET['restore_booking'] ) );
        $restored = false !== $wpdb->update( $bookings_table, [ 'is_deleted' => 0, 'deleted_at' => null ], [ 'id' => absint( $_GET['restore_booking'] ) ], [ '%d', '%s' ], [ '%d' ] );
        echo '<div class="notice ' . esc_attr( $restored ? 'notice-success' : 'notice-error' ) . '"><p>' . esc_html( $restored ? __( 'Booking restored.', 'simple-hotel-crm' ) : __( 'Booking could not be restored.', 'simple-hotel-crm' ) ) . '</p></div>';
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

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT b.id, b.guest_id, b.status_code, b.check_in_date, b.check_out_date, b.source_channel, b.total_amount, b.created_at,
                    CONCAT(g.first_name, ' ', g.last_name) AS guest_name,
                    COUNT(br.id) AS room_count
             FROM {$bookings_table} b
             JOIN {$guests_table} g ON g.id = b.guest_id
             LEFT JOIN {$booking_rooms_table} br ON br.booking_id = b.id
             WHERE b.is_deleted = %d
             GROUP BY b.id, b.guest_id, b.status_code, b.check_in_date, b.check_out_date, b.source_channel, b.total_amount, b.created_at, guest_name
             ORDER BY {$order_sql} {$order}
             LIMIT 200",
            $is_deleted
        ),
        ARRAY_A
    );

    $active_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$bookings_table} WHERE is_deleted = 0" );
    $trash_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$bookings_table} WHERE is_deleted = 1" );
    echo '<div class="wrap">';
    echo '<h1>' . esc_html__( 'Bookings', 'simple-hotel-crm' ) . '</h1>';
    echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=simple-hotel-crm-bookings&view=active' ) ) . '">' . esc_html__( 'Active', 'simple-hotel-crm' ) . ' (' . esc_html( (string) $active_count ) . ')</a> | <a href="' . esc_url( admin_url( 'admin.php?page=simple-hotel-crm-bookings&view=trash' ) ) . '">' . esc_html__( 'Trash', 'simple-hotel-crm' ) . ' (' . esc_html( (string) $trash_count ) . ')</a></p>';
    echo '<form method="post">';
    wp_nonce_field( 'simple_hotel_crm_bulk_bookings' );
    echo '<p><select name="bulk_action"><option value="">' . esc_html__( 'Bulk actions', 'simple-hotel-crm' ) . '</option><option value="delete">' . esc_html__( 'Delete', 'simple-hotel-crm' ) . '</option><option value="restore">' . esc_html__( 'Restore', 'simple-hotel-crm' ) . '</option></select> ';
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
    echo '<th>' . $header_link( 'total', __( 'Total', 'simple-hotel-crm' ) ) . '</th>';
    echo '<th>' . esc_html__( 'Actions', 'simple-hotel-crm' ) . '</th>';
    echo '</tr></thead><tbody>';
    if ( empty( $rows ) ) {
        echo '<tr><td colspan="10">' . esc_html__( 'No bookings found.', 'simple-hotel-crm' ) . '</td></tr>';
    } else {
        foreach ( $rows as $row ) {
            $detail_url = admin_url( 'admin.php?page=simple-hotel-crm-booking-detail&booking_id=' . absint( $row['id'] ) );
            $delete_url = wp_nonce_url( admin_url( 'admin.php?page=simple-hotel-crm-bookings&delete_booking=' . absint( $row['id'] ) . '&view=' . $view ), 'simple_hotel_crm_delete_booking_' . absint( $row['id'] ) );
            $restore_url = wp_nonce_url( admin_url( 'admin.php?page=simple-hotel-crm-bookings&restore_booking=' . absint( $row['id'] ) . '&view=' . $view ), 'simple_hotel_crm_restore_booking_' . absint( $row['id'] ) );
            echo '<tr>';
            echo '<td><input class="booking-bulk-cb" type="checkbox" name="booking_ids[]" value="' . esc_attr( (string) $row['id'] ) . '" /></td>';
            echo '<td><a href="' . esc_url( $detail_url ) . '">' . esc_html( (string) $row['id'] ) . '</a></td>';
            echo '<td><a href="' . esc_url( admin_url( 'admin.php?page=simple-hotel-crm-guest-detail&guest_id=' . absint( $row['guest_id'] ) ) ) . '">' . esc_html( trim( (string) $row['guest_name'] ) ) . '</a></td>';
            echo '<td>' . esc_html( (string) $row['check_in_date'] ) . '</td>';
            echo '<td>' . esc_html( (string) $row['check_out_date'] ) . '</td>';
            echo '<td>' . esc_html( (string) $row['room_count'] ) . '</td>';
            echo '<td>' . esc_html( (string) $row['status_code'] ) . '</td>';
            echo '<td>' . esc_html( (string) $row['source_channel'] ) . '</td>';
            echo '<td>' . esc_html( number_format( (float) $row['total_amount'], 2, '.', '' ) ) . '</td>';
            echo '<td><a class="button button-small" href="' . esc_url( $detail_url ) . '">' . esc_html__( 'View / Edit', 'simple-hotel-crm' ) . '</a> ' . ( 'trash' === $view ? '<a class="button button-small" href="' . esc_url( $restore_url ) . '">' . esc_html__( 'Restore', 'simple-hotel-crm' ) . '</a> <a class="button button-small button-link-delete" href="' . esc_url( $delete_url ) . '" onclick="return confirm(' . wp_json_encode( __( 'Permanently delete this booking?', 'simple-hotel-crm' ) ) . ');">' . esc_html__( 'Delete Permanently', 'simple-hotel-crm' ) . '</a>' : '<a class="button button-small" href="' . esc_url( $delete_url ) . '" onclick="return confirm(' . wp_json_encode( __( 'Delete this booking?', 'simple-hotel-crm' ) ) . ');">' . esc_html__( 'Delete', 'simple-hotel-crm' ) . '</a>' ) . '</td>';
            echo '</tr>';
        }
    }
    echo '</tbody></table></form>';
    echo '</div>';
}

function simple_hotel_crm_render_rooms_page() {
    if ( ! simple_hotel_crm_user_can_access() ) {
        wp_die( esc_html__( 'You do not have permission to access this page.', 'simple-hotel-crm' ) );
    }

    global $wpdb;
    $rooms_table = simple_hotel_crm_rooms_table();
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
            'color' => sanitize_hex_color( wp_unslash( $_POST['color'] ?? '' ) ) ?: '#cccccc',
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
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Room saved.', 'simple-hotel-crm' ) . '</p></div>';
            simple_hotel_crm_clear_calendar_cache();
        }
    }

    $room = $editing ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$rooms_table} WHERE id = %d", $room_id ), ARRAY_A ) : null;
    $room = array_merge(
        [ 'room_code' => '', 'room_name' => '', 'sort_order' => 0, 'color' => '#cccccc', 'active' => 1 ],
        is_array( $room ) ? $room : []
    );
    $rooms = $wpdb->get_results( "SELECT * FROM {$rooms_table} ORDER BY sort_order ASC, room_name ASC", ARRAY_A );

    echo '<div class="wrap"><h1>' . esc_html__( 'Rooms', 'simple-hotel-crm' ) . '</h1>';
    echo '<div style="display:grid;grid-template-columns:minmax(320px,420px) 1fr;gap:24px;align-items:start;">';
    echo '<div><h2>' . esc_html( $editing ? __( 'Edit Room', 'simple-hotel-crm' ) : __( 'Add Room', 'simple-hotel-crm' ) ) . '</h2><form method="post">';
    wp_nonce_field( 'simple_hotel_crm_save_room', 'simple_hotel_crm_save_room_nonce' );
    echo '<table class="form-table">';
    echo '<tr><th><label for="room_code">' . esc_html__( 'Room code', 'simple-hotel-crm' ) . '</label></th><td><input id="room_code" name="room_code" type="text" class="regular-text" value="' . esc_attr( (string) $room['room_code'] ) . '" /></td></tr>';
    echo '<tr><th><label for="room_name">' . esc_html__( 'Room name', 'simple-hotel-crm' ) . '</label></th><td><input id="room_name" name="room_name" type="text" class="regular-text" value="' . esc_attr( (string) $room['room_name'] ) . '" /></td></tr>';
    echo '<tr><th><label for="sort_order">' . esc_html__( 'Sort order', 'simple-hotel-crm' ) . '</label></th><td><input id="sort_order" name="sort_order" type="number" value="' . esc_attr( (string) $room['sort_order'] ) . '" /></td></tr>';
    echo '<tr><th><label for="color">' . esc_html__( 'Color', 'simple-hotel-crm' ) . '</label></th><td><input id="color" name="color" type="color" value="' . esc_attr( (string) $room['color'] ) . '" /> <code>' . esc_html( (string) $room['color'] ) . '</code></td></tr>';
    echo '<tr><th>' . esc_html__( 'Active', 'simple-hotel-crm' ) . '</th><td><label><input type="checkbox" name="active" value="1" ' . checked( (int) $room['active'], 1, false ) . ' /> ' . esc_html__( 'Show this room in the calendar', 'simple-hotel-crm' ) . '</label></td></tr>';
    echo '</table>';
    submit_button( __( 'Save Room', 'simple-hotel-crm' ), 'primary', 'simple_hotel_crm_save_room' );
    if ( $editing ) {
        echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=simple-hotel-crm-rooms' ) ) . '">' . esc_html__( 'Add New Room', 'simple-hotel-crm' ) . '</a>';
    }
    echo '</form></div>';

    echo '<div><h2>' . esc_html__( 'Current Rooms', 'simple-hotel-crm' ) . '</h2><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Code', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Name', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Order', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Color', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Status', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Actions', 'simple-hotel-crm' ) . '</th></tr></thead><tbody>';
    if ( empty( $rooms ) ) {
        echo '<tr><td colspan="6">' . esc_html__( 'No rooms yet.', 'simple-hotel-crm' ) . '</td></tr>';
    } else {
        foreach ( $rooms as $row ) {
            $edit_url = admin_url( 'admin.php?page=simple-hotel-crm-rooms&room_id=' . absint( $row['id'] ) );
            $toggle_url = wp_nonce_url( admin_url( 'admin.php?page=simple-hotel-crm-rooms&toggle_room=' . absint( $row['id'] ) ), 'simple_hotel_crm_toggle_room_' . absint( $row['id'] ) );
            echo '<tr>';
            echo '<td>' . esc_html( (string) $row['room_code'] ) . '</td>';
            echo '<td>' . esc_html( (string) $row['room_name'] ) . '</td>';
            echo '<td>' . esc_html( (string) $row['sort_order'] ) . '</td>';
            echo '<td><span style="display:inline-block;width:18px;height:18px;border:1px solid #ccc;background:' . esc_attr( (string) $row['color'] ) . ';vertical-align:middle;margin-right:6px;"></span>' . esc_html( (string) $row['color'] ) . '</td>';
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
    $orderby_key = isset( $_GET['orderby'] ) && isset( $sortable[ $_GET['orderby'] ] ) ? $_GET['orderby'] : 'name';
    $order = isset( $_GET['order'] ) && 'desc' === strtolower( (string) $_GET['order'] ) ? 'DESC' : 'ASC';
    $order_sql = $sortable[ $orderby_key ];

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
             WHERE g.is_deleted = %d
             GROUP BY g.id, guest_name, g.email, g.phone
             ORDER BY {$order_sql} {$order}
             LIMIT 200",
            $is_deleted
        ),
        ARRAY_A
    );

    $active_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$guests_table} WHERE is_deleted = 0" );
    $trash_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$guests_table} WHERE is_deleted = 1" );
    $header_link = function( $key, $label ) use ( $orderby_key, $order, $view ) {
        $next_order = ( $orderby_key === $key && 'ASC' === $order ) ? 'desc' : 'asc';
        $url = admin_url( 'admin.php?page=simple-hotel-crm-guests&view=' . $view . '&orderby=' . $key . '&order=' . $next_order );
        return '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
    };

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__( 'Guests', 'simple-hotel-crm' ) . ' <a class="page-title-action" href="' . esc_url( admin_url( 'admin.php?page=simple-hotel-crm-guest-detail' ) ) . '">' . esc_html__( 'Add New', 'simple-hotel-crm' ) . '</a></h1>';
    echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=simple-hotel-crm-guests&view=active' ) ) . '">' . esc_html__( 'Active', 'simple-hotel-crm' ) . ' (' . esc_html( (string) $active_count ) . ')</a> | <a href="' . esc_url( admin_url( 'admin.php?page=simple-hotel-crm-guests&view=trash' ) ) . '">' . esc_html__( 'Trash', 'simple-hotel-crm' ) . ' (' . esc_html( (string) $trash_count ) . ')</a></p>';
    echo '<form method="post">';
    wp_nonce_field( 'simple_hotel_crm_bulk_guests' );
    echo '<p><select name="bulk_action"><option value="">' . esc_html__( 'Bulk actions', 'simple-hotel-crm' ) . '</option><option value="delete">' . esc_html__( 'Delete', 'simple-hotel-crm' ) . '</option><option value="restore">' . esc_html__( 'Restore', 'simple-hotel-crm' ) . '</option></select> ';
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

    $sync_rooms_table = simple_hotel_crm_sync_rooms_table();
    $sync_bookings_table = simple_hotel_crm_sync_bookings_table();
    $crm_rooms_table = simple_hotel_crm_rooms_table();
    $crm_bookings_table = simple_hotel_crm_bookings_table();
    $crm_booking_rooms_table = simple_hotel_crm_booking_rooms_table();
    $crm_booking_nights_table = simple_hotel_crm_booking_room_nights_table();

    extract( $validated, EXTR_SKIP );
    $status_code = sanitize_text_field( (string) ( $data['status_code'] ?? 'confirmed' ) );
    $source_channel = sanitize_text_field( (string) ( $data['source_channel'] ?? 'direct' ) );
    $booking_note = sanitize_textarea_field( (string) ( $data['booking_note'] ?? '' ) );
    $import_notes = sanitize_textarea_field( (string) ( $data['import_notes'] ?? '' ) );
    $source_created_at = ( '' !== $contacted_date ? $contacted_date : current_time( 'mysql' ) );

    $existing_rooms = $wpdb->get_results( $wpdb->prepare( "SELECT br.legacy_reserved_room_id, br.room_id, r.sync_room_id FROM {$crm_booking_rooms_table} br JOIN {$crm_rooms_table} r ON r.id = br.room_id WHERE br.booking_id = %d ORDER BY br.id ASC", $booking_id ), ARRAY_A );
    $legacy_ids = wp_list_pluck( $existing_rooms, 'legacy_reserved_room_id' );
    $overlay_map = [];
    foreach ( $existing_rooms as $existing_room ) {
        $sync_room_id = (int) $existing_room['sync_room_id'];
        if ( $sync_room_id > 0 && ! isset( $overlay_map[ $sync_room_id ] ) ) {
            $overlay_map[ $sync_room_id ] = (int) $existing_room['legacy_reserved_room_id'];
        }
    }
    foreach ( $room_lines as $line ) {
        $availability = simple_hotel_crm_check_wp_sync_room_availability( $line['room_sync_id'], $check_in, $check_out );
        if ( is_wp_error( $availability ) ) {
            $crm_room_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$crm_rooms_table} WHERE sync_room_id = %d LIMIT 1", $line['room_sync_id'] ) );
            $current_match = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$crm_booking_rooms_table} WHERE booking_id = %d AND room_id = %d", $booking_id, $crm_room_id ) );
            if ( $current_match <= 0 ) {
                return $availability;
            }
        }
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

    $next_legacy_room_id = max( 1, (int) $wpdb->get_var( "SELECT COALESCE(MAX(external_booking_room_id), 0) + 1 FROM {$sync_bookings_table}" ) );
    $external_booking_id = $existing_booking ? (int) $existing_booking['id'] : $booking_id;
    $booking_adults = array_sum( array_column( $room_lines, 'adults' ) );
    $booking_children = array_sum( array_column( $room_lines, 'children' ) );
    $booking_babies = array_sum( array_column( $room_lines, 'babies' ) );
    $booking_room_rate = round( array_sum( array_column( $room_lines, 'room_rate_amount' ) ), 2 );
    $booking_extras = round( array_sum( array_column( $room_lines, 'extras_amount' ) ), 2 );
    $booking_tax = round( array_sum( array_column( $room_lines, 'tourist_tax_total' ) ), 2 );
    $booking_total = round( array_sum( array_column( $room_lines, 'total_amount' ) ), 2 );

    foreach ( $room_lines as $line ) {
        $room = $wpdb->get_row( $wpdb->prepare( "SELECT id, external_room_id, room_code, room_name FROM {$sync_rooms_table} WHERE id = %d LIMIT 1", $line['room_sync_id'] ), ARRAY_A );
        if ( ! $room ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'invalid_room', __( 'Please select a valid room.', 'simple-hotel-crm' ) );
        }
        $crm_room_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$crm_rooms_table} WHERE sync_room_id = %d LIMIT 1", $line['room_sync_id'] ) );
        $legacy_reserved_room_id = isset( $overlay_map[ (int) $line['room_sync_id'] ] ) ? (int) $overlay_map[ (int) $line['room_sync_id'] ] : $next_legacy_room_id++;
        $inserted = $wpdb->insert(
            $crm_booking_rooms_table,
            [
                'booking_id' => $booking_id,
                'room_id' => $crm_room_id,
                'legacy_reserved_room_id' => $legacy_reserved_room_id,
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
        if ( false === $inserted ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'booking_room_insert_failed', __( 'Could not save booking rooms.', 'simple-hotel-crm' ) );
        }
        if ( isset( $overlay_map[ (int) $line['room_sync_id'] ] ) && (int) $overlay_map[ (int) $line['room_sync_id'] ] !== $legacy_reserved_room_id ) {
            simple_hotel_crm_copy_booking_overlay( (int) $overlay_map[ (int) $line['room_sync_id'] ], $legacy_reserved_room_id, $booking_id, $crm_room_id );
        }
        $crm_booking_room_id = (int) $wpdb->insert_id;
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
                    'room_rate_amount' => $room_rate_nightly[ $i ],
                    'extras_amount' => $extras_nightly[ $i ],
                    'tourist_tax_amount' => $tax_nightly[ $i ],
                    'total_amount' => $night_total,
                ],
                [ '%d', '%s', '%d', '%d', '%d', '%d', '%f', '%f', '%f', '%f' ]
            );
            $wpdb->insert(
                $sync_bookings_table,
                [
                    'external_booking_id' => $external_booking_id,
                    'external_booking_room_id' => $legacy_reserved_room_id,
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
            $sync_room_id = (int) $line['room_sync_id'];
            if ( isset( $overlay_map[ $sync_room_id ] ) ) {
                $new_legacy_ids[] = (int) $overlay_map[ $sync_room_id ];
            }
        }
        foreach ( array_diff( array_map( 'intval', array_filter( $legacy_ids ) ), array_map( 'intval', array_filter( $new_legacy_ids ) ) ) as $stale_legacy_id ) {
            simple_hotel_crm_delete_booking_overlay( $stale_legacy_id );
        }
    }

    simple_hotel_crm_clear_calendar_cache();
    return true;
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
        $posted_room_lines[] = [ 'room_sync_id' => '', 'adults' => 2, 'children' => 0, 'babies' => 0, 'room_rate_amount' => '0.00', 'extras_amount' => '0.00' ];
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
            'booking_note' => sanitize_textarea_field( wp_unslash( $_POST['booking_note'] ?? '' ) ),
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

    $rooms = $wpdb->get_results( $wpdb->prepare( "SELECT br.*, r.room_name, r.room_code, r.sync_room_id FROM {$booking_rooms_table} br JOIN {$rooms_table} r ON r.id = br.room_id WHERE br.booking_id = %d ORDER BY r.sort_order ASC, r.room_name ASC", $booking_id ), ARRAY_A );
    if ( ! empty( $posted_room_lines ) ) {
        foreach ( $posted_room_lines as $index => $posted_room_line ) {
            if ( isset( $rooms[ $index ] ) ) {
                $rooms[ $index ] = array_merge( $rooms[ $index ], $posted_room_line );
            } else {
                $rooms[] = array_merge( [ 'id' => '', 'room_name' => '', 'sync_room_id' => '' ], $posted_room_line );
            }
        }
    }
    $available_rooms = $wpdb->get_results( "SELECT sync_room_id, room_name, room_code FROM {$rooms_table} WHERE active = 1 AND sync_room_id IS NOT NULL ORDER BY sort_order ASC, room_name ASC", ARRAY_A );
    echo '<div class="wrap">';
    echo '<h1>' . esc_html__( 'Booking Detail', 'simple-hotel-crm' ) . ' #' . esc_html( (string) $booking_id ) . '</h1>';
    echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=simple-hotel-crm-bookings' ) ) . '">← ' . esc_html__( 'Back to Bookings', 'simple-hotel-crm' ) . '</a></p>';
    echo '<form method="post">';
    wp_nonce_field( 'simple_hotel_crm_save_booking_' . $booking_id );
    echo '<table class="form-table">';
    echo '<tr><th>' . esc_html__( 'Guest', 'simple-hotel-crm' ) . '</th><td><a href="' . esc_url( admin_url( 'admin.php?page=simple-hotel-crm-guest-detail&guest_id=' . absint( $booking['guest_id'] ) ) ) . '">' . esc_html( trim( (string) $booking['first_name'] . ' ' . (string) $booking['last_name'] ) ) . '</a></td></tr>';
    echo '<tr><th>' . esc_html__( 'Status', 'simple-hotel-crm' ) . '</th><td><select name="status_code">';
    foreach ( simple_hotel_crm_get_booking_status_options() as $code => $label ) {
        echo '<option value="' . esc_attr( $code ) . '"' . selected( (string) $booking['status_code'], $code, false ) . '>' . esc_html( $label ) . '</option>';
    }
    echo '</select></td></tr>';
    echo '<tr><th>' . esc_html__( 'Channel', 'simple-hotel-crm' ) . '</th><td><select name="source_channel">';
    foreach ( simple_hotel_crm_get_booking_channel_options() as $code => $label ) {
        echo '<option value="' . esc_attr( $code ) . '"' . selected( (string) $booking['source_channel'], $code, false ) . '>' . esc_html( $label ) . '</option>';
    }
    echo '</select></td></tr>';
    echo '<tr><th>' . esc_html__( 'Contacted date', 'simple-hotel-crm' ) . '</th><td><input type="date" name="contacted_date" value="' . esc_attr( (string) $booking['contacted_date'] ) . '" /></td></tr>';
    echo '<tr><th>' . esc_html__( 'Check-in', 'simple-hotel-crm' ) . '</th><td><input type="date" name="check_in_date" value="' . esc_attr( (string) $booking['check_in_date'] ) . '" /></td></tr>';
    echo '<tr><th>' . esc_html__( 'Check-out', 'simple-hotel-crm' ) . '</th><td><input type="date" name="check_out_date" value="' . esc_attr( (string) $booking['check_out_date'] ) . '" /></td></tr>';
    echo '<tr><th>' . esc_html__( 'Booking note', 'simple-hotel-crm' ) . '</th><td><input type="text" name="booking_note" class="regular-text" value="' . esc_attr( (string) $booking['booking_note'] ) . '" /></td></tr>';
    echo '<tr><th>' . esc_html__( 'Internal notes', 'simple-hotel-crm' ) . '</th><td><textarea name="internal_notes" rows="5" class="large-text">' . esc_textarea( (string) $booking['internal_notes'] ) . '</textarea></td></tr>';
    echo '</table>';
    submit_button( __( 'Save Booking', 'simple-hotel-crm' ), 'primary', 'simple_hotel_crm_save_booking' );
    echo '</form>';
    echo '<h2>' . esc_html__( 'Rooms', 'simple-hotel-crm' ) . '</h2>';
    echo '<table class="widefat striped"><thead><tr><th>ID</th><th>' . esc_html__( 'Room', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Adults', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Children', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Babies', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Rate', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Extras', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Tax', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Total', 'simple-hotel-crm' ) . '</th><th>' . esc_html__( 'Remove', 'simple-hotel-crm' ) . '</th></tr></thead><tbody>';
    foreach ( $rooms as $index => $room ) {
        echo '<tr>';
        echo '<td>' . esc_html( (string) $room['id'] ) . '</td>';
        echo '<td><select name="room_lines[' . esc_attr( $index ) . '][room_sync_id]">';
        echo '<option value="">' . esc_html__( 'Select room', 'simple-hotel-crm' ) . '</option>';
        foreach ( $available_rooms as $available_room ) {
            $label = (string) $available_room['room_code'] . ' - ' . (string) $available_room['room_name'];
            echo '<option value="' . esc_attr( (string) $available_room['sync_room_id'] ) . '"' . selected( (string) ( $room['sync_room_id'] ?? $room['room_sync_id'] ?? '' ), (string) $available_room['sync_room_id'], false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></td>';
        echo '<td><input type="number" min="0" name="room_lines[' . esc_attr( $index ) . '][adults]" value="' . esc_attr( (string) ( $room['adults'] ?? 0 ) ) . '" /></td>';
        echo '<td><input type="number" min="0" name="room_lines[' . esc_attr( $index ) . '][children]" value="' . esc_attr( (string) ( $room['children'] ?? 0 ) ) . '" /></td>';
        echo '<td><input type="number" min="0" name="room_lines[' . esc_attr( $index ) . '][babies]" value="' . esc_attr( (string) ( $room['babies'] ?? 0 ) ) . '" /></td>';
        echo '<td><input type="text" name="room_lines[' . esc_attr( $index ) . '][room_rate_amount]" value="' . esc_attr( isset( $room['room_rate_amount'] ) ? number_format( (float) $room['room_rate_amount'], 2, '.', '' ) : '0.00' ) . '" /></td>';
        echo '<td><input type="text" name="room_lines[' . esc_attr( $index ) . '][extras_amount]" value="' . esc_attr( isset( $room['extras_amount'] ) ? number_format( (float) $room['extras_amount'], 2, '.', '' ) : '0.00' ) . '" /></td>';
        echo '<td>' . esc_html( isset( $room['tourist_tax_amount'] ) ? number_format( (float) $room['tourist_tax_amount'], 2, '.', '' ) : '' ) . '</td>';
        echo '<td>' . esc_html( isset( $room['total_amount'] ) ? number_format( (float) $room['total_amount'], 2, '.', '' ) : '' ) . '</td>';
        echo '<td><label><input type="checkbox" name="room_lines[' . esc_attr( $index ) . '][remove]" value="1" /> ' . esc_html__( 'Remove', 'simple-hotel-crm' ) . '</label></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    submit_button( __( 'Add room line', 'simple-hotel-crm' ), 'secondary', 'simple_hotel_crm_add_booking_room', false );
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
    echo '<tr><th><label for="email">' . esc_html__( 'Email', 'simple-hotel-crm' ) . '</label></th><td><input type="email" name="email" id="email" class="regular-text" value="' . esc_attr( (string) $form_data['email'] ) . '" /></td></tr>';
    echo '<tr><th><label for="address_line_1">' . esc_html__( 'Address line 1', 'simple-hotel-crm' ) . '</label></th><td><input type="text" name="address_line_1" id="address_line_1" class="regular-text" value="' . esc_attr( (string) $form_data['address_line_1'] ) . '" /></td></tr>';
    echo '<tr><th><label for="address_line_2">' . esc_html__( 'Address line 2', 'simple-hotel-crm' ) . '</label></th><td><input type="text" name="address_line_2" id="address_line_2" class="regular-text" value="' . esc_attr( (string) $form_data['address_line_2'] ) . '" /></td></tr>';
    echo '<tr><th><label for="city">' . esc_html__( 'City', 'simple-hotel-crm' ) . '</label></th><td><input type="text" name="city" id="city" class="regular-text" value="' . esc_attr( (string) $form_data['city'] ) . '" /></td></tr>';
    echo '<tr><th><label for="postcode">' . esc_html__( 'Postcode', 'simple-hotel-crm' ) . '</label></th><td><input type="text" name="postcode" id="postcode" class="regular-text" value="' . esc_attr( (string) $form_data['postcode'] ) . '" /></td></tr>';
    echo '<tr><th><label for="country">' . esc_html__( 'Country', 'simple-hotel-crm' ) . '</label></th><td><input type="text" name="country" id="country" class="regular-text" value="' . esc_attr( (string) $form_data['country'] ) . '" /></td></tr>';
    echo '<tr><th><label for="guest_notes">' . esc_html__( 'Guest notes', 'simple-hotel-crm' ) . '</label></th><td><textarea name="guest_notes" id="guest_notes" rows="3" class="large-text">' . esc_textarea( (string) $form_data['guest_notes'] ) . '</textarea></td></tr>';
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
    echo '<tr><th><label for="booking_note">' . esc_html__( 'Booking note', 'simple-hotel-crm' ) . '</label></th><td><textarea name="booking_note" id="booking_note" rows="3" class="large-text">' . esc_textarea( (string) $form_data['booking_note'] ) . '</textarea></td></tr>';
    echo '<tr><th><label for="import_notes">' . esc_html__( 'Import / internal notes', 'simple-hotel-crm' ) . '</label></th><td><textarea name="import_notes" id="import_notes" rows="4" class="large-text">' . esc_textarea( (string) $form_data['import_notes'] ) . '</textarea></td></tr>';
    echo '</table>';
    submit_button( __( 'Create Booking', 'simple-hotel-crm' ), 'primary', 'lgf_add_booking_submit' );
    echo '</form>';
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
        $guest = simple_hotel_crm_find_guest_for_import_row( [ 'email' => $guest_email, 'guest_name' => $row['guest_name'] ?? '' ] );
        if ( ! $guest ) {
            $summary['errors'][] = sprintf( __( 'Room row %d: guest not found.', 'simple-hotel-crm' ), $index + 2 );
            continue;
        }
        $booking = simple_hotel_crm_find_booking_for_import_row( $row, (int) $guest['id'] );
        $room = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$rooms_table} WHERE room_code = %s LIMIT 1", $room_code ), ARRAY_A );
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

function simple_hotel_crm_render_import_page() {
    if ( ! simple_hotel_crm_user_can_access() ) {
        wp_die( esc_html__( 'You do not have permission to access this page.', 'simple-hotel-crm' ) );
    }

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
            }
        }
    }

    echo '<div class="wrap"><h1>' . esc_html__( 'Import CSV', 'simple-hotel-crm' ) . '</h1>';
    echo '<p>' . esc_html__( 'Import missing records only. Recommended order: guests, bookings, booking rooms.', 'simple-hotel-crm' ) . '</p>';
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
    echo '<table class="form-table"><tr><th><label for="import_type">' . esc_html__( 'Import type', 'simple-hotel-crm' ) . '</label></th><td><select name="import_type" id="import_type"><option value="guests">' . esc_html__( 'Guests', 'simple-hotel-crm' ) . '</option><option value="bookings">' . esc_html__( 'Bookings', 'simple-hotel-crm' ) . '</option><option value="booking_rooms">' . esc_html__( 'Booking rooms', 'simple-hotel-crm' ) . '</option></select></td></tr>';
    echo '<tr><th><label for="import_csv">' . esc_html__( 'CSV file', 'simple-hotel-crm' ) . '</label></th><td><input type="file" name="import_csv" id="import_csv" accept=".csv,text/csv" required /></td></tr></table>';
    submit_button( __( 'Preview / Dry Run', 'simple-hotel-crm' ), 'secondary', 'simple_hotel_crm_import_preview', false );
    echo ' ';
    submit_button( __( 'Import CSV', 'simple-hotel-crm' ), 'primary', 'simple_hotel_crm_import_submit', false );
    echo '</form></div>';
}

function simple_hotel_crm_render_settings_page() {
    if ( ! simple_hotel_crm_user_can_access() ) {
        wp_die( esc_html__( 'You do not have permission to access this page.', 'simple-hotel-crm' ) );
    }

    if ( isset( $_POST['simple_hotel_crm_submit'] ) ) {
        check_admin_referer( 'simple_hotel_crm_settings', 'simple_hotel_crm_settings_nonce' );

        $api_url = isset( $_POST['simple_hotel_crm_invoice_ninja_url'] ) ? esc_url_raw( trim( wp_unslash( $_POST['simple_hotel_crm_invoice_ninja_url'] ) ) ) : '';
        $api_token = isset( $_POST['simple_hotel_crm_invoice_ninja_token'] ) ? sanitize_text_field( trim( wp_unslash( $_POST['simple_hotel_crm_invoice_ninja_token'] ) ) ) : '';

        update_option( 'simple_hotel_crm_invoice_ninja_url', $api_url );
        update_option( 'simple_hotel_crm_invoice_ninja_token', $api_token );
        update_option( 'simple_hotel_crm_booking_source', 'wp_sync' );

        simple_hotel_crm_clear_calendar_cache();
        echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.', 'simple-hotel-crm' ) . '</p></div>';
    }

    $api_url = get_option( 'simple_hotel_crm_invoice_ninja_url', '' );
    $api_token = get_option( 'simple_hotel_crm_invoice_ninja_token', '' );

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__( 'Simple Hotel CRM Settings', 'simple-hotel-crm' ) . '</h1>';
    echo '<p>' . esc_html__( 'Simple Hotel CRM now uses the WordPress CRM tables as the main booking source.', 'simple-hotel-crm' ) . '</p>';
    echo '<form method="post">';
    wp_nonce_field( 'simple_hotel_crm_settings', 'simple_hotel_crm_settings_nonce' );

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

