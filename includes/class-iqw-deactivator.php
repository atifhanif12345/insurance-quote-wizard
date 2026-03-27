<?php
/**
 * Plugin Deactivator
 * Cleanup tasks on deactivation (does NOT delete data).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class IQW_Deactivator {

    public static function deactivate() {
        // Clear any scheduled cron events
        wp_clear_scheduled_hook( 'iqw_daily_cleanup' );

        // Clear transients
        delete_transient( 'iqw_forms_cache' );

        // Flush rewrite rules
        flush_rewrite_rules();
    }
}
