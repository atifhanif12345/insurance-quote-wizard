<?php
/**
 * Plugin Activator
 * Creates database tables and installs default form templates.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class IQW_Activator {

    /**
     * Run activation tasks
     */
    public static function activate() {
        self::create_tables();
        self::install_default_forms();
        IQW_Abandonment::create_table();
        self::set_default_options();

        // Store version for future upgrades
        update_option( 'iqw_version', IQW_VERSION );
        update_option( 'iqw_installed_at', current_time( 'mysql' ) );

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Create custom database tables
     */
    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Table 1: Forms
        $table_forms = $wpdb->prefix . IQW_TABLE_FORMS;
        $sql_forms = "CREATE TABLE {$table_forms} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(100) NOT NULL,
            type VARCHAR(50) NOT NULL DEFAULT 'custom',
            config LONGTEXT NOT NULL,
            email_settings LONGTEXT DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY type (type),
            KEY status (status)
        ) {$charset_collate};";

        // Table 2: Entries
        $table_entries = $wpdb->prefix . IQW_TABLE_ENTRIES;
        $sql_entries = "CREATE TABLE {$table_entries} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            form_id BIGINT(20) UNSIGNED NOT NULL,
            data LONGTEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'new',
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            referrer_url VARCHAR(500) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            read_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY form_id (form_id),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};";

        // Table 3: Entry Meta (searchable index)
        $table_meta = $wpdb->prefix . IQW_TABLE_ENTRY_META;
        $sql_meta = "CREATE TABLE {$table_meta} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            entry_id BIGINT(20) UNSIGNED NOT NULL,
            field_key VARCHAR(100) NOT NULL,
            field_value LONGTEXT DEFAULT NULL,
            PRIMARY KEY (id),
            KEY entry_id (entry_id),
            KEY field_key (field_key),
            KEY entry_field (entry_id, field_key)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_forms );
        dbDelta( $sql_entries );
        dbDelta( $sql_meta );

        // Table 4: Entry Notes
        $table_notes = $wpdb->prefix . 'iqw_entry_notes';
        $sql_notes = "CREATE TABLE {$table_notes} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            entry_id BIGINT(20) UNSIGNED NOT NULL,
            note TEXT NOT NULL,
            author VARCHAR(100) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY entry_id (entry_id)
        ) {$charset_collate};";
        dbDelta( $sql_notes );

        // Table 5: Email Log
        $table_email_log = $wpdb->prefix . 'iqw_email_log';
        $sql_email_log = "CREATE TABLE {$table_email_log} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            entry_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            type VARCHAR(20) NOT NULL DEFAULT 'admin',
            recipient VARCHAR(255) NOT NULL,
            subject VARCHAR(500) DEFAULT NULL,
            sent TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY entry_id (entry_id),
            KEY created_at (created_at)
        ) {$charset_collate};";
        dbDelta( $sql_email_log );

        // Table 6: Form Drafts (Save & Continue Later)
        $table_drafts = $wpdb->prefix . 'iqw_drafts';
        $sql_drafts = "CREATE TABLE {$table_drafts} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            form_id BIGINT(20) UNSIGNED NOT NULL,
            token VARCHAR(64) NOT NULL,
            data LONGTEXT NOT NULL,
            current_step INT NOT NULL DEFAULT 0,
            ip_address VARCHAR(45) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY token (token),
            KEY form_id (form_id),
            KEY expires_at (expires_at)
        ) {$charset_collate};";
        dbDelta( $sql_drafts );
    }

    /**
     * Install default form templates
     */
    private static function install_default_forms() {
        global $wpdb;

        $table = $wpdb->prefix . IQW_TABLE_FORMS;

        $templates = array(
            array(
                'title'  => 'Auto Insurance Quote',
                'slug'   => 'auto-insurance',
                'type'   => 'auto',
                'file'   => 'auto-insurance.json',
            ),
            array(
                'title'  => 'Home Insurance Quote',
                'slug'   => 'home-insurance',
                'type'   => 'home',
                'file'   => 'home-insurance.json',
            ),
            array(
                'title'  => 'General Insurance Quote',
                'slug'   => 'generic-quote',
                'type'   => 'generic',
                'file'   => 'generic-quote.json',
            ),
            array(
                'title'  => 'Health Insurance Quote',
                'slug'   => 'health-insurance',
                'type'   => 'health',
                'file'   => 'health-insurance.json',
            ),
            array(
                'title'  => 'Life Insurance Quote',
                'slug'   => 'life-insurance',
                'type'   => 'life',
                'file'   => 'life-insurance.json',
            ),
            array(
                'title'  => 'Commercial Insurance Quote',
                'slug'   => 'commercial-insurance',
                'type'   => 'commercial',
                'file'   => 'commercial-insurance.json',
            ),
            array(
                'title'  => 'Contact Inquiry',
                'slug'   => 'contact-inquiry',
                'type'   => 'generic',
                'file'   => 'contact-inquiry.json',
            ),
        );

        $default_email_settings = wp_json_encode( array(
            'admin_enabled'    => true,
            'admin_to'         => get_option( 'admin_email' ),
            'admin_cc'         => '',
            'admin_subject'    => 'New {form_title} Lead - {full_name}',
            'admin_template'   => 'default',
            'customer_enabled' => true,
            'customer_subject' => 'Thank you for your quote request, {first_name}!',
            'customer_template' => 'default',
        ) );

        foreach ( $templates as $tpl ) {
            // Check if THIS specific form slug already exists
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE slug = %s", $tpl['slug']
            ) );

            if ( $exists > 0 ) {
                continue; // Already installed, skip
            }

            $json_path = IQW_PLUGIN_DIR . 'templates/forms/' . $tpl['file'];
            $config = file_exists( $json_path ) ? file_get_contents( $json_path ) : '{}';

            $wpdb->insert( $table, array(
                'title'          => $tpl['title'],
                'slug'           => $tpl['slug'],
                'type'           => $tpl['type'],
                'config'         => $config,
                'email_settings' => $default_email_settings,
                'status'         => 'active',
            ), array( '%s', '%s', '%s', '%s', '%s', '%s' ) );
        }
    }

    /**
     * Set default plugin options
     */
    private static function set_default_options() {
        $defaults = array(
            'iqw_recaptcha_enabled'   => false,
            'iqw_recaptcha_site_key'  => '',
            'iqw_recaptcha_secret'    => '',
            'iqw_honeypot_enabled'    => true,
            'iqw_time_check_enabled'  => true,
            'iqw_time_check_seconds'  => 3,
            'iqw_rate_limit_enabled'  => true,
            'iqw_rate_limit_max'      => 20,
            'iqw_rate_limit_window'   => 3600,
            'iqw_admin_email'         => get_option( 'admin_email' ),
            'iqw_company_name'        => get_bloginfo( 'name' ),
            'iqw_company_phone'       => '',
            'iqw_primary_color'       => '#4CAF50',
            'iqw_delete_data_uninstall' => false,
        );

        foreach ( $defaults as $key => $value ) {
            if ( get_option( $key ) === false ) {
                update_option( $key, $value );
            }
        }
    }
}
