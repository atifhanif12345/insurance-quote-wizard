<?php
/**
 * Plugin Name: Insurance Quote Wizard
 * Plugin URI: https://luishernandez.testingforwebs.com/
 * Description: Ultra-fast, Compare.com-style multi-step insurance quote forms for WordPress. Built for insurance agencies.
 * Version: 2.2.0
 * Author: Luis Hernandez Agency
 * Author URI: https://luishernandez.testingforwebs.com/
 * License: Private
 * Text Domain: iqw
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'IQW_VERSION', '2.2.0' );
define( 'IQW_PLUGIN_FILE', __FILE__ );
define( 'IQW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'IQW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'IQW_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Database table names (without prefix)
define( 'IQW_TABLE_FORMS', 'iqw_forms' );
define( 'IQW_TABLE_ENTRIES', 'iqw_entries' );
define( 'IQW_TABLE_ENTRY_META', 'iqw_entry_meta' );

/**
 * Activation hook
 */
function iqw_activate() {
    require_once IQW_PLUGIN_DIR . 'includes/class-iqw-activator.php';
    IQW_Activator::activate();
}
register_activation_hook( __FILE__, 'iqw_activate' );

/**
 * Deactivation hook
 */
function iqw_deactivate() {
    require_once IQW_PLUGIN_DIR . 'includes/class-iqw-deactivator.php';
    IQW_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'iqw_deactivate' );

/**
 * Load plugin core
 */
require_once IQW_PLUGIN_DIR . 'includes/class-iqw-loader.php';

/**
 * Initialize the plugin
 */
function iqw_init() {
    $loader = new IQW_Loader();
    $loader->run();
}
add_action( 'plugins_loaded', 'iqw_init' );

/**
 * Add settings link on plugins page
 */
function iqw_plugin_action_links( $links ) {
    $settings_link = '<a href="' . admin_url( 'admin.php?page=iqw-dashboard' ) . '">' . __( 'Dashboard', 'iqw' ) . '</a>';
    $entries_link  = '<a href="' . admin_url( 'admin.php?page=iqw-entries' ) . '">' . __( 'Entries', 'iqw' ) . '</a>';
    $settings      = '<a href="' . admin_url( 'admin.php?page=iqw-settings' ) . '">' . __( 'Settings', 'iqw' ) . '</a>';
    array_unshift( $links, $settings_link, $entries_link, $settings );
    return $links;
}
add_filter( 'plugin_action_links_' . IQW_PLUGIN_BASENAME, 'iqw_plugin_action_links' );
