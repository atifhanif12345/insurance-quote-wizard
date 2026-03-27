<?php
/**
 * Uninstall handler
 * Runs when plugin is DELETED (not deactivated).
 * Only removes data if user opted in via settings.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

$delete_data = get_option( 'iqw_delete_data_uninstall', false );

if ( $delete_data ) {
    global $wpdb;

    // Drop custom tables (using esc_sql for table names)
    $tables = array(
        $wpdb->prefix . 'iqw_drafts',
        $wpdb->prefix . 'iqw_abandonments',
        $wpdb->prefix . 'iqw_email_log',
        $wpdb->prefix . 'iqw_entry_notes',
        $wpdb->prefix . 'iqw_entry_meta',
        $wpdb->prefix . 'iqw_entries',
        $wpdb->prefix . 'iqw_forms',
    );
    foreach ( $tables as $table ) {
        // Use esc_sql instead of %i for WP 6.0+ compatibility (%i requires WP 6.2+)
        $safe_table = esc_sql( $table );
        $wpdb->query( "DROP TABLE IF EXISTS `{$safe_table}`" );
    }

    // Delete all plugin options using prepare with LIKE
    $wpdb->query(
        $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", 'iqw\_%' )
    );

    // Delete transients
    $wpdb->query(
        $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '\_transient\_iqw\_%' )
    );
    $wpdb->query(
        $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '\_transient\_timeout\_iqw\_%' )
    );
}
