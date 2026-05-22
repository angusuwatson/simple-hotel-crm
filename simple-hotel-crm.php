<?php
/**
 * Plugin Name: LGF Bookings
 * Description: A simple WordPress-native hotel CRM with calendar and booking management tools
 * Version: 1.9.2.39
 * Update URI: https://github.com/angusuwatson/lgf-bookings-plugin
 * Author: Angus Watson
 * Text Domain: simple-hotel-crm
 */

defined( 'ABSPATH' ) || exit;

define( 'SIMPLE_HOTEL_CRM_VERSION', '1.9.2.39' );
define( 'SIMPLE_HOTEL_CRM_DB_VERSION', '20' );
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
    simple_hotel_crm_square_maybe_add_columns();
    if ( ! wp_next_scheduled( 'simple_hotel_crm_motopress_sync_cron' ) ) {
        wp_schedule_event( time(), 'shc_15min', 'simple_hotel_crm_motopress_sync_cron' );
    }
    if ( ! wp_next_scheduled( 'simple_hotel_crm_ics_cron' ) ) {
        wp_schedule_event( time(), 'shc_15min', 'simple_hotel_crm_ics_cron' );
    }
    update_option( 'simple_hotel_crm_needs_rewrite_flush', 1 );
}

function simple_hotel_crm_deactivate() {
    $timestamp = wp_next_scheduled( 'simple_hotel_crm_motopress_sync_cron' );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'simple_hotel_crm_motopress_sync_cron' );
    }
    $timestamp = wp_next_scheduled( 'simple_hotel_crm_ics_cron' );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'simple_hotel_crm_ics_cron' );
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
        update_option( 'simple_hotel_crm_needs_rewrite_flush', 1 );
    }
}

// ---- Terminal Web App rewrite ----
add_action( 'init', function() {
    add_rewrite_rule( '^terminal/?$', 'index.php?simple_hotel_crm_terminal=1', 'top' );
    add_rewrite_tag( '%simple_hotel_crm_terminal%', '1' );
    
    // Flush rewrite rules once on version upgrade (after rules are registered)
    if ( get_option( 'simple_hotel_crm_needs_rewrite_flush' ) ) {
        flush_rewrite_rules();
        delete_option( 'simple_hotel_crm_needs_rewrite_flush' );
    }
} );

add_action( 'template_redirect', function() {
    if ( get_query_var( 'simple_hotel_crm_terminal' ) ) {
        require_once plugin_dir_path( __FILE__ ) . 'terminal/index.php';
        exit;
    }
} );

add_action( 'template_redirect', function() {
    if ( get_query_var( 'simple_hotel_crm_terminal' ) ) {
        require_once plugin_dir_path( __FILE__ ) . 'terminal/index.php';
        exit;
    }
} );
