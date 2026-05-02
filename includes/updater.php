<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_filter( 'pre_set_site_transient_update_plugins', 'simple_hotel_crm_check_for_updates' );
add_filter( 'plugins_api', 'simple_hotel_crm_plugin_info', 20, 3 );
add_filter( 'upgrader_source_selection', 'simple_hotel_crm_fix_update_source_dir', 10, 4 );

function simple_hotel_crm_get_update_metadata() {
    $cache_key = 'simple_hotel_crm_update_metadata';
    $cached = get_transient( $cache_key );
    if ( false !== $cached ) {
        return $cached;
    }

    $response = wp_remote_get( 'https://raw.githubusercontent.com/angusuwatson/simple-hotel-crm/master/update.json', [ 'timeout' => 15 ] );
    if ( is_wp_error( $response ) ) {
        return false;
    }

    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( ! is_array( $data ) || empty( $data['version'] ) || empty( $data['download_url'] ) ) {
        return false;
    }

    set_transient( $cache_key, $data, 15 * MINUTE_IN_SECONDS );
    return $data;
}

function simple_hotel_crm_check_for_updates( $transient ) {
    if ( empty( $transient->checked ) || ! is_object( $transient ) ) {
        return $transient;
    }

    $metadata = simple_hotel_crm_get_update_metadata();
    if ( ! $metadata ) {
        return $transient;
    }

    $plugin_file = plugin_basename( dirname( __DIR__ ) . '/simple-hotel-crm.php' );
    $current_version = $transient->checked[ $plugin_file ] ?? SIMPLE_HOTEL_CRM_VERSION;

    if ( version_compare( (string) $metadata['version'], (string) $current_version, '>' ) ) {
        $transient->response[ $plugin_file ] = (object) [
            'slug' => 'simple-hotel-crm',
            'plugin' => $plugin_file,
            'new_version' => (string) $metadata['version'],
            'package' => (string) $metadata['download_url'],
            'url' => (string) ( $metadata['homepage'] ?? 'https://github.com/angusuwatson/simple-hotel-crm' ),
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

function simple_hotel_crm_fix_update_source_dir( $source, $remote_source, $upgrader, $hook_extra ) {
    global $wp_filesystem;

    if ( empty( $hook_extra['plugin'] ) || 'simple-hotel-crm/simple-hotel-crm.php' !== $hook_extra['plugin'] ) {
        return $source;
    }

    $desired = trailingslashit( $remote_source ) . 'simple-hotel-crm';
    if ( $source === $desired ) {
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
