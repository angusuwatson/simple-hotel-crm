<?php
/**
 * Plugin Name: LGF Bookings
 * Description: A simple WordPress-native hotel CRM with calendar and booking management tools
 * Version: 1.8.9.27
 * Update URI: https://github.com/angusuwatson/simple-hotel-crm
 * Author: Angus Watson
 * Text Domain: simple-hotel-crm
 */

defined( 'ABSPATH' ) || exit;

define( 'SIMPLE_HOTEL_CRM_VERSION', '1.8.9.27' );
define( 'SIMPLE_HOTEL_CRM_DB_VERSION', '13' );
define( 'SIMPLE_HOTEL_CRM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

register_activation_hook( __FILE__, 'simple_hotel_crm_activate' );
add_action( 'plugins_loaded', 'simple_hotel_crm_maybe_upgrade' );

function simple_hotel_crm_activate() {
    simple_hotel_crm_install_tables();
    simple_hotel_crm_add_processed_flag();
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
