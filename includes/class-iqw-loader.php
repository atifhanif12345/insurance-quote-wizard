<?php
/**
 * Plugin Loader
 * Central hub that loads all components and registers hooks.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class IQW_Loader {

    /**
     * Initialize the plugin
     */
    public function run() {
        $this->load_dependencies();
        $this->register_hooks();

        if ( is_admin() ) {
            $this->init_admin();
        }

        $this->init_public();
    }

    /**
     * Load all required class files
     */
    private function load_dependencies() {
        $includes = IQW_PLUGIN_DIR . 'includes/';
        $admin    = IQW_PLUGIN_DIR . 'admin/';
        $public   = IQW_PLUGIN_DIR . 'public/';

        // Core classes
        require_once $includes . 'class-iqw-form-builder.php';
        require_once $includes . 'class-iqw-conditional-logic.php';
        require_once $includes . 'class-iqw-validator.php';
        require_once $includes . 'class-iqw-submission.php';
        require_once $includes . 'class-iqw-email.php';
        require_once $includes . 'class-iqw-export.php';
        require_once $includes . 'class-iqw-rest-api.php';
        require_once $includes . 'class-iqw-security.php';
        require_once $includes . 'class-iqw-webhook.php';
        require_once $includes . 'class-iqw-save-continue.php';
        require_once $includes . 'class-iqw-pdf.php';
        require_once $includes . 'class-iqw-google-sheets.php';
        require_once $includes . 'class-iqw-analytics.php';
        require_once $includes . 'class-iqw-stripe.php';
        require_once $includes . 'class-iqw-mailchimp.php';
        require_once $includes . 'class-iqw-gdpr.php';
        require_once $includes . 'class-iqw-sms.php';
        require_once $includes . 'class-iqw-abandonment.php';
        require_once $includes . 'class-iqw-geolocation.php';
        require_once $includes . 'class-iqw-import-export.php';

        // Admin classes
        if ( is_admin() ) {
            require_once $admin . 'class-iqw-admin.php';
            require_once $admin . 'class-iqw-admin-forms.php';
            require_once $admin . 'class-iqw-admin-entries.php';
            require_once $admin . 'class-iqw-admin-settings.php';
        }

        // Public classes
        require_once $public . 'class-iqw-public.php';
        require_once $public . 'class-iqw-shortcode.php';
        require_once $public . 'class-iqw-block.php';
    }

    /**
     * Register global hooks
     */
    private function register_hooks() {
        // REST API
        add_action( 'rest_api_init', array( new IQW_Rest_API(), 'register_routes' ) );
        add_action( 'rest_api_init', array( 'IQW_Geolocation', 'register_routes' ) );

        // AJAX: Form submission
        add_action( 'wp_ajax_iqw_submit_form', array( new IQW_Submission(), 'handle_ajax_submit' ) );
        add_action( 'wp_ajax_nopriv_iqw_submit_form', array( new IQW_Submission(), 'handle_ajax_submit' ) );

        // AJAX: Save & Continue Later
        add_action( 'wp_ajax_iqw_save_draft', array( 'IQW_Save_Continue', 'save_draft' ) );
        add_action( 'wp_ajax_nopriv_iqw_save_draft', array( 'IQW_Save_Continue', 'save_draft' ) );
        add_action( 'wp_ajax_iqw_load_draft', array( 'IQW_Save_Continue', 'load_draft' ) );
        add_action( 'wp_ajax_nopriv_iqw_load_draft', array( 'IQW_Save_Continue', 'load_draft' ) );

        // AJAX: PDF Generation (admin only)
        add_action( 'wp_ajax_iqw_generate_pdf', array( 'IQW_PDF', 'ajax_generate' ) );

        // PDF direct download
        add_action( 'admin_init', array( 'IQW_PDF', 'handle_download' ) );

        // AJAX: Google Sheets test
        add_action( 'wp_ajax_iqw_test_gsheets', array( 'IQW_Google_Sheets', 'ajax_test_connection' ) );

        // AJAX: Analytics tracking (frontend, nopriv)
        add_action( 'wp_ajax_iqw_track_view', array( 'IQW_Analytics', 'ajax_track_view' ) );
        add_action( 'wp_ajax_nopriv_iqw_track_view', array( 'IQW_Analytics', 'ajax_track_view' ) );
        add_action( 'wp_ajax_iqw_track_start', array( 'IQW_Analytics', 'ajax_track_start' ) );
        add_action( 'wp_ajax_nopriv_iqw_track_start', array( 'IQW_Analytics', 'ajax_track_start' ) );
        add_action( 'wp_ajax_iqw_track_step', array( 'IQW_Analytics', 'ajax_track_step' ) );
        add_action( 'wp_ajax_nopriv_iqw_track_step', array( 'IQW_Analytics', 'ajax_track_step' ) );
        add_action( 'wp_ajax_iqw_get_analytics', array( 'IQW_Analytics', 'ajax_get_analytics' ) );

        // AJAX: Stripe payment intent
        add_action( 'wp_ajax_iqw_create_payment_intent', array( 'IQW_Stripe', 'ajax_create_intent' ) );
        add_action( 'wp_ajax_nopriv_iqw_create_payment_intent', array( 'IQW_Stripe', 'ajax_create_intent' ) );

        // AJAX: Import/Export entries
        add_action( 'wp_ajax_iqw_export_entry', array( 'IQW_Import_Export', 'ajax_export_entry' ) );
        add_action( 'wp_ajax_iqw_export_entries_json', array( 'IQW_Import_Export', 'ajax_export_entries_json' ) );
        add_action( 'wp_ajax_iqw_import_entries', array( 'IQW_Import_Export', 'ajax_import_entries' ) );
        add_action( 'wp_ajax_iqw_test_mailchimp', array( 'IQW_Mailchimp', 'ajax_test_connection' ) );
        add_action( 'wp_ajax_iqw_anonymize_entry', array( 'IQW_GDPR', 'ajax_anonymize_entry' ) );

        // GDPR: WP Privacy Tools integration
        IQW_GDPR::init();

        add_action( 'wp_ajax_iqw_test_sms', array( 'IQW_SMS', 'ajax_test_sms' ) );
        add_action( 'wp_ajax_iqw_save_partial', array( 'IQW_Abandonment', 'ajax_save_partial' ) );
        add_action( 'wp_ajax_nopriv_iqw_save_partial', array( 'IQW_Abandonment', 'ajax_save_partial' ) );

        // Load translations
        add_action( 'init', array( $this, 'load_textdomain' ) );

        // Check for DB upgrades
        add_action( 'init', array( $this, 'check_version' ) );

        // Schedule daily draft cleanup
        if ( ! wp_next_scheduled( 'iqw_daily_cleanup' ) ) {
            wp_schedule_event( time(), 'daily', 'iqw_daily_cleanup' );
        }
        add_action( 'iqw_daily_cleanup', array( 'IQW_Save_Continue', 'cleanup' ) );
        add_action( 'iqw_daily_cleanup', array( 'IQW_GDPR', 'auto_delete_old_entries' ) );
        add_action( 'iqw_daily_cleanup', array( 'IQW_Abandonment', 'cleanup' ) );

        // Hourly: send recovery emails for abandoned forms
        if ( ! wp_next_scheduled( 'iqw_hourly_tasks' ) ) {
            wp_schedule_event( time(), 'hourly', 'iqw_hourly_tasks' );
        }
        add_action( 'iqw_hourly_tasks', array( 'IQW_Abandonment', 'send_recovery_emails' ) );

        // Google Sheets background push
        add_action( 'iqw_push_to_sheets', array( 'IQW_Google_Sheets', 'do_push' ), 10, 2 );

        // Webhook background delivery
        add_action( 'iqw_deliver_webhooks', array( 'IQW_Webhook', 'deliver' ), 10, 1 );
    }

    /**
     * Initialize admin
     */
    private function init_admin() {
        $admin = new IQW_Admin();
        $admin->init();
    }

    /**
     * Initialize public-facing
     */
    private function init_public() {
        $public = new IQW_Public();
        $public->init();

        // Register shortcode
        $shortcode = new IQW_Shortcode();
        $shortcode->register();

        // Register Gutenberg block
        $block = new IQW_Block();
        $block->register();
    }

    /**
     * Load plugin translations
     */
    public function load_textdomain() {
        load_plugin_textdomain( 'iqw', false, dirname( IQW_PLUGIN_BASENAME ) . '/languages/' );
    }

    /**
     * Check if DB needs upgrading
     */
    public function check_version() {
        $installed_version = get_option( 'iqw_version', '0' );
        if ( version_compare( $installed_version, IQW_VERSION, '<' ) ) {
            require_once IQW_PLUGIN_DIR . 'includes/class-iqw-activator.php';
            IQW_Activator::activate();
        }
    }
}
