<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'SIMPLE_HOTEL_CRM_UPDATE_METADATA_URL', 'https://raw.githubusercontent.com/angusuwatson/lgf-bookings-plugin/master/update.json' );
define( 'SIMPLE_HOTEL_CRM_UPDATE_METADATA_FALLBACK_URL', 'https://github.com/angusuwatson/lgf-bookings-plugin/raw/master/update.json' );
define( 'SIMPLE_HOTEL_CRM_UPDATE_CACHE_KEY', 'simple_hotel_crm_update_metadata' );
define( 'SIMPLE_HOTEL_CRM_UPDATE_ERROR_KEY', 'simple_hotel_crm_update_error' );
define( 'SIMPLE_HOTEL_CRM_PLUGIN_DIRNAME', dirname( SIMPLE_HOTEL_CRM_PLUGIN_BASENAME ) );

add_filter( 'pre_set_site_transient_update_plugins', 'simple_hotel_crm_check_for_updates' );
add_filter( 'plugins_api', 'simple_hotel_crm_plugin_info', 20, 3 );
add_filter( 'upgrader_source_selection', 'simple_hotel_crm_fix_update_source_dir', 10, 4 );
add_action( 'admin_init', 'simple_hotel_crm_maybe_refresh_update_cache' );
add_action( 'upgrader_process_complete', 'simple_hotel_crm_clear_update_cache', 10, 0 );
add_filter( 'plugin_action_links_' . SIMPLE_HOTEL_CRM_PLUGIN_BASENAME, 'simple_hotel_crm_add_refresh_updates_link' );
add_action( 'admin_notices', 'simple_hotel_crm_render_update_debug_notice' );

function simple_hotel_crm_fetch_update_json( $url ) {
    $response = wp_remote_get( $url, [ 'timeout' => 15, 'headers' => [ 'Cache-Control' => 'no-cache' ] ] );
    if ( is_wp_error( $response ) ) {
        return [ 'error' => $response->get_error_message() ];
    }

    $code = wp_remote_retrieve_response_code( $response );
    if ( $code < 200 || $code >= 300 ) {
        return [ 'error' => "HTTP {$code}" ];
    }

    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( ! is_array( $data ) || empty( $data['version'] ) || empty( $data['download_url'] ) ) {
        return [ 'error' => 'Invalid JSON or missing version/download_url' ];
    }

    return $data;
}

function simple_hotel_crm_get_update_metadata() {
    $cached = get_transient( SIMPLE_HOTEL_CRM_UPDATE_CACHE_KEY );
    if ( false !== $cached ) {
        return $cached;
    }

    $result = simple_hotel_crm_fetch_update_json( SIMPLE_HOTEL_CRM_UPDATE_METADATA_URL );
    if ( isset( $result['error'] ) ) {
        $result = simple_hotel_crm_fetch_update_json( SIMPLE_HOTEL_CRM_UPDATE_METADATA_FALLBACK_URL );
    }

    if ( isset( $result['error'] ) ) {
        set_transient( SIMPLE_HOTEL_CRM_UPDATE_ERROR_KEY, $result['error'], 5 * MINUTE_IN_SECONDS );
        return false;
    }

    delete_transient( SIMPLE_HOTEL_CRM_UPDATE_ERROR_KEY );
    set_transient( SIMPLE_HOTEL_CRM_UPDATE_CACHE_KEY, $result, 5 * MINUTE_IN_SECONDS );
    return $result;
}

function simple_hotel_crm_check_for_updates( $transient ) {
    if ( empty( $transient->checked ) || ! is_object( $transient ) ) {
        return $transient;
    }

    $metadata = simple_hotel_crm_get_update_metadata();
    if ( ! $metadata ) {
        return $transient;
    }

    $plugin_file = SIMPLE_HOTEL_CRM_PLUGIN_BASENAME;
    $current_version = $transient->checked[ $plugin_file ] ?? SIMPLE_HOTEL_CRM_VERSION;

    if ( version_compare( (string) $metadata['version'], (string) $current_version, '>' ) ) {
        $transient->response[ $plugin_file ] = (object) [
            'slug' => 'simple-hotel-crm',
            'plugin' => $plugin_file,
            'new_version' => (string) $metadata['version'],
            'package' => (string) $metadata['download_url'],
            'url' => (string) ( $metadata['homepage'] ?? 'https://github.com/angusuwatson/lgf-bookings-plugin' ),
            'tested' => (string) ( $metadata['tested'] ?? '' ),
            'requires' => (string) ( $metadata['requires'] ?? '' ),
            'requires_php' => (string) ( $metadata['requires_php'] ?? '' ),
        ];
    }

    return $transient;
}

function simple_hotel_crm_plugin_info( $result, $action, $args ) {
    if ( 'plugin_information' !== $action || empty( $args->slug ) || 'simple-hotel-crm' !== $args->slug ) {
        return $result;
    }

    $metadata = simple_hotel_crm_get_update_metadata();
    if ( ! $metadata ) {
        return $result;
    }

    return (object) [
        'name' => (string) $metadata['name'],
        'slug' => 'simple-hotel-crm',
        'version' => (string) $metadata['version'],
        'author' => '<a href="https://github.com/angusuwatson">Angus Watson</a>',
        'homepage' => (string) ( $metadata['homepage'] ?? '' ),
        'requires' => (string) ( $metadata['requires'] ?? '' ),
        'tested' => (string) ( $metadata['tested'] ?? '' ),
        'requires_php' => (string) ( $metadata['requires_php'] ?? '' ),
        'download_link' => (string) $metadata['download_url'],
        'sections' => (array) ( $metadata['sections'] ?? [] ),
    ];
}

function simple_hotel_crm_maybe_refresh_update_cache() {
    if ( ! is_admin() || ! current_user_can( 'update_plugins' ) ) {
        return;
    }

    $should_refresh = false;
    if ( isset( $_GET['force-check'] ) ) {
        $should_refresh = true;
    }
    if ( isset( $_GET['simple_hotel_crm_refresh_updates'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'simple_hotel_crm_refresh_updates' ) ) {
        $should_refresh = true;
    }

    if ( ! $should_refresh ) {
        return;
    }

    simple_hotel_crm_clear_update_cache();
    wp_clean_plugins_cache( true );
    delete_site_transient( 'update_plugins' );
}

function simple_hotel_crm_clear_update_cache() {
    delete_transient( SIMPLE_HOTEL_CRM_UPDATE_CACHE_KEY );
    delete_transient( SIMPLE_HOTEL_CRM_UPDATE_ERROR_KEY );
}

function simple_hotel_crm_add_refresh_updates_link( $links ) {
    if ( ! current_user_can( 'update_plugins' ) ) {
        return $links;
    }

    $url = wp_nonce_url( admin_url( 'plugins.php?simple_hotel_crm_refresh_updates=1' ), 'simple_hotel_crm_refresh_updates' );
    $links[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Check updates', 'simple-hotel-crm' ) . '</a>';
    return $links;
}

function simple_hotel_crm_render_update_debug_notice() {
    if ( ! is_admin() || ! current_user_can( 'update_plugins' ) || empty( $_GET['simple_hotel_crm_refresh_updates'] ) ) {
        return;
    }

    $metadata = simple_hotel_crm_get_update_metadata();
    $remote_version = is_array( $metadata ) && ! empty( $metadata['version'] ) ? (string) $metadata['version'] : 'unavailable';
    $error = get_transient( SIMPLE_HOTEL_CRM_UPDATE_ERROR_KEY );
    $error_msg = $error ? ' Error: ' . $error : '';
    echo '<div class="notice notice-info"><p>' . esc_html( sprintf( 'LGF Bookings updater checked. Installed: %s. Remote: %s. Plugin key: %s%s', SIMPLE_HOTEL_CRM_VERSION, $remote_version, SIMPLE_HOTEL_CRM_PLUGIN_BASENAME, $error_msg ) ) . '</p></div>';
}

function simple_hotel_crm_fix_update_source_dir( $source, $remote_source, $upgrader, $hook_extra ) {
    global $wp_filesystem;

    if ( empty( $hook_extra['plugin'] ) || SIMPLE_HOTEL_CRM_PLUGIN_BASENAME !== $hook_extra['plugin'] ) {
        return $source;
    }

    $desired = trailingslashit( $remote_source ) . SIMPLE_HOTEL_CRM_PLUGIN_DIRNAME;
    if ( untrailingslashit( $source ) === untrailingslashit( $desired ) ) {
        return $source;
    }

    if ( $wp_filesystem->exists( $desired ) ) {
        $wp_filesystem->delete( $desired, true );
    }

    if ( $wp_filesystem->move( $source, $desired ) ) {
        return $desired;
    }

    return $source;
}
