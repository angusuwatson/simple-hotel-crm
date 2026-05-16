<?php
/**
 * Plugin Name: LGF Bookings
 * Description: A simple WordPress-native hotel CRM with calendar and booking management tools
 * Version: 1.8.9.83
 * Update URI: https://github.com/angusuwatson/simple-hotel-crm
 * Author: Angus Watson
 * Text Domain: simple-hotel-crm
 */

defined( 'ABSPATH' ) || exit;

define( 'SIMPLE_HOTEL_CRM_VERSION', '1.8.9.83' );
define( 'SIMPLE_HOTEL_CRM_DB_VERSION', '14' );
define( 'SIMPLE_HOTEL_CRM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

register_activation_hook( __FILE__, 'simple_hotel_crm_activate' );
register_deactivation_hook( __FILE__, 'simple_hotel_crm_deactivate' );
add_action( 'plugins_loaded', 'simple_hotel_crm_maybe_upgrade' );

add_filter( 'cron_schedules', function( $schedules ) {
    $schedules['shc_15min'] = [
        'interval' => 15 * MINUTE_IN_SECONDS,
        'display'  => __( 'Every 15 minutes', 'simple-hotel-crm' ),
    ];
    return $schedules;
} );

add_action( 'simple_hotel_crm_motopress_sync_cron', 'simple_hotel_crm_motopress_cron_sync' );

function simple_hotel_crm_activate() {
    simple_hotel_crm_install_tables();
    simple_hotel_crm_add_processed_flag();
    if ( ! wp_next_scheduled( 'simple_hotel_crm_motopress_sync_cron' ) ) {
        wp_schedule_event( time(), 'shc_15min', 'simple_hotel_crm_motopress_sync_cron' );
    }
}

function simple_hotel_crm_deactivate() {
    $timestamp = wp_next_scheduled( 'simple_hotel_crm_motopress_sync_cron' );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'simple_hotel_crm_motopress_sync_cron' );
    }
}

function simple_hotel_crm_maybe_upgrade() {
    $installed_version = get_option( 'simple_hotel_crm_db_version' );
    if ( SIMPLE_HOTEL_CRM_DB_VERSION !== (string) $installed_version ) {
        simple_hotel_crm_install_tables();
    }

    $installed_plugin_version = (string) get_option( 'simple_hotel_crm_plugin_version', '' );
    if ( SIMPLE_HOTEL_CRM_VERSION !== $installed_plugin_version ) {
        simple_hotel_crm_clear_calendar_cache();
        update_option( 'simple_hotel_crm_plugin_version', SIMPLE_HOTEL_CRM_VERSION );
    }
}

require_once plugin_dir_path( __FILE__ ) . 'includes/schema.php';

require_once plugin_dir_path( __FILE__ ) . 'includes/helpers.php';

require_once plugin_dir_path( __FILE__ ) . 'includes/calendar-data.php';

require_once plugin_dir_path( __FILE__ ) . 'includes/admin-pages.php';

require_once plugin_dir_path( __FILE__ ) . 'includes/crm-bookings.php';

require_once plugin_dir_path( __FILE__ ) . 'includes/rest.php';

require_once plugin_dir_path( __FILE__ ) . 'includes/invoicing.php';

require_once plugin_dir_path( __FILE__ ) . 'includes/updater.php';
