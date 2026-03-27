<?php
/**
 * Admin Controller
 * Registers menus, pages, and admin assets.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class IQW_Admin {

    public function init() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

        // Admin AJAX handlers
        add_action( 'wp_ajax_iqw_save_form', array( $this, 'ajax_save_form' ) );
        add_action( 'wp_ajax_iqw_duplicate_form', array( $this, 'ajax_duplicate_form' ) );
        add_action( 'wp_ajax_iqw_delete_form', array( $this, 'ajax_delete_form' ) );
        add_action( 'wp_ajax_iqw_bulk_entry_action', array( $this, 'ajax_bulk_entry_action' ) );
        add_action( 'wp_ajax_iqw_add_note', array( $this, 'ajax_add_note' ) );
        add_action( 'wp_ajax_iqw_delete_note', array( $this, 'ajax_delete_note' ) );
        add_action( 'wp_ajax_iqw_clear_email_log', array( $this, 'ajax_clear_email_log' ) );
        add_action( 'wp_ajax_iqw_resend_email', array( $this, 'ajax_resend_email' ) );
        add_action( 'wp_ajax_iqw_import_form', array( $this, 'ajax_import_form' ) );
        add_action( 'wp_ajax_iqw_export_entries', array( new IQW_Export(), 'handle_export' ) );
        add_action( 'wp_ajax_iqw_edit_entry', array( $this, 'ajax_edit_entry' ) );

        // Dashboard widget
        add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_widget' ) );
    }

    /**
     * Register WordPress dashboard widget
     */
    public function register_dashboard_widget() {
        wp_add_dashboard_widget(
            'iqw_dashboard_widget',
            __( 'Insurance Quote Wizard', 'iqw' ),
            array( $this, 'render_dashboard_widget' )
        );
    }

    /**
     * Render WP dashboard widget
     */
    public function render_dashboard_widget() {
        $today = IQW_Submission::get_today_count();
        $week = IQW_Submission::get_week_count();
        $unread = IQW_Submission::get_unread_count();
        $counts = IQW_Submission::get_entry_counts();
        ?>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:12px;">
            <div style="text-align:center;padding:12px;background:#e3f2fd;border-radius:8px;">
                <div style="font-size:24px;font-weight:700;color:#1565c0;"><?php echo esc_html( $today ); ?></div>
                <div style="font-size:12px;color:#666;">Today</div>
            </div>
            <div style="text-align:center;padding:12px;background:#e8f5e9;border-radius:8px;">
                <div style="font-size:24px;font-weight:700;color:#2e7d32;"><?php echo esc_html( $week ); ?></div>
                <div style="font-size:12px;color:#666;">This Week</div>
            </div>
            <div style="text-align:center;padding:12px;background:#fff3e0;border-radius:8px;">
                <div style="font-size:24px;font-weight:700;color:#e65100;"><?php echo esc_html( $unread ); ?></div>
                <div style="font-size:12px;color:#666;">Unread</div>
            </div>
        </div>
        <p>
            <a href="<?php echo admin_url( 'admin.php?page=iqw-entries' ); ?>" class="button button-primary"><?php _e( 'View Entries', 'iqw' ); ?></a>
            <a href="<?php echo admin_url( 'admin.php?page=iqw-dashboard' ); ?>" class="button"><?php _e( 'Dashboard', 'iqw' ); ?></a>
        </p>
        <?php
    }

    /**
     * Register admin menu
     */
    public function register_menu() {
        $unread = IQW_Submission::get_unread_count();
        $badge  = $unread > 0 ? ' <span class="awaiting-mod">' . $unread . '</span>' : '';

        // Main menu
        add_menu_page(
            __( 'Quote Wizard', 'iqw' ),
            __( 'Quote Wizard', 'iqw' ) . $badge,
            'manage_options',
            'iqw-dashboard',
            array( $this, 'page_dashboard' ),
            'dashicons-shield',
            30
        );

        // Submenu: Dashboard (same as parent)
        add_submenu_page(
            'iqw-dashboard',
            __( 'Dashboard', 'iqw' ),
            __( 'Dashboard', 'iqw' ),
            'manage_options',
            'iqw-dashboard',
            array( $this, 'page_dashboard' )
        );

        // Submenu: All Forms
        add_submenu_page(
            'iqw-dashboard',
            __( 'All Forms', 'iqw' ),
            __( 'All Forms', 'iqw' ),
            'manage_options',
            'iqw-forms',
            array( $this, 'page_forms' )
        );

        // Submenu: Add New
        add_submenu_page(
            'iqw-dashboard',
            __( 'Add New Form', 'iqw' ),
            __( 'Add New', 'iqw' ),
            'manage_options',
            'iqw-form-edit',
            array( $this, 'page_form_edit' )
        );

        // Submenu: Entries
        add_submenu_page(
            'iqw-dashboard',
            __( 'Entries', 'iqw' ),
            __( 'Entries', 'iqw' ) . $badge,
            'manage_options',
            'iqw-entries',
            array( $this, 'page_entries' )
        );

        // Submenu: Settings
        add_submenu_page(
            'iqw-dashboard',
            __( 'Settings', 'iqw' ),
            __( 'Settings', 'iqw' ),
            'manage_options',
            'iqw-settings',
            array( $this, 'page_settings' )
        );

        // Submenu: Email Templates
        add_submenu_page(
            'iqw-dashboard',
            __( 'Email Templates', 'iqw' ),
            __( 'Email Templates', 'iqw' ),
            'manage_options',
            'iqw-email-templates',
            array( $this, 'page_email_templates' )
        );

        // Submenu: Email Log
        add_submenu_page(
            'iqw-dashboard',
            __( 'Email Log', 'iqw' ),
            __( 'Email Log', 'iqw' ),
            'manage_options',
            'iqw-email-log',
            array( $this, 'page_email_log' )
        );
    }

    /**
     * Enqueue admin CSS/JS only on our pages
     */
    public function enqueue_assets( $hook ) {
        // Detect our pages reliably using the 'page' query param
        // Hook names are fragile — they depend on sanitized menu title which
        // breaks with HTML badges, translations, or special characters
        $page = $_GET['page'] ?? '';
        $our_pages = array(
            'iqw-dashboard',
            'iqw-forms',
            'iqw-form-edit',
            'iqw-entries',
            'iqw-settings',
            'iqw-email-templates',
            'iqw-email-log',
        );

        if ( ! in_array( $page, $our_pages, true ) ) return;

        wp_enqueue_style(
            'iqw-admin',
            IQW_PLUGIN_URL . 'admin/css/iqw-admin.css',
            array(),
            IQW_VERSION
        );

        $suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

        wp_enqueue_script(
            'iqw-admin',
            IQW_PLUGIN_URL . 'admin/js/iqw-admin' . $suffix . '.js',
            array( 'jquery' ),
            IQW_VERSION,
            true
        );

        wp_localize_script( 'iqw-admin', 'iqwAdmin', array(
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'restUrl'  => rest_url( 'iqw/v1/' ),
            'nonce'    => wp_create_nonce( 'iqw_admin_nonce' ),
            'restNonce' => wp_create_nonce( 'wp_rest' ),
            'strings'  => array(
                'confirmDelete' => __( 'Are you sure? This cannot be undone.', 'iqw' ),
                'saved'         => __( 'Saved successfully!', 'iqw' ),
                'error'         => __( 'Something went wrong. Please try again.', 'iqw' ),
            ),
        ) );

        // Form builder JS only on form edit page
        if ( $page === 'iqw-form-edit' ) {
            wp_enqueue_script(
                'iqw-form-builder',
                IQW_PLUGIN_URL . 'admin/js/iqw-form-builder' . $suffix . '.js',
                array( 'jquery', 'iqw-admin' ),
                IQW_VERSION,
                true
            );
        }
    }

    // ================================================================
    // Page Renderers
    // ================================================================

    public function page_dashboard() {
        $entry_counts = IQW_Submission::get_entry_counts();
        $form_counts  = IQW_Form_Builder::get_form_counts();
        $recent       = IQW_Submission::get_entries( array( 'limit' => 10 ) );
        include IQW_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    public function page_forms() {
        $forms = IQW_Form_Builder::get_forms();
        include IQW_PLUGIN_DIR . 'admin/views/forms-list.php';
    }

    public function page_form_edit() {
        $form_id = absint( $_GET['id'] ?? 0 );
        $form = $form_id ? IQW_Form_Builder::get_form( $form_id ) : null;
        $field_types = IQW_Form_Builder::get_field_types();
        include IQW_PLUGIN_DIR . 'admin/views/form-builder.php';
    }

    public function page_entries() {
        $action = $_GET['action'] ?? 'list';

        if ( $action === 'view' && ! empty( $_GET['id'] ) ) {
            $entry = IQW_Submission::get_entry( absint( $_GET['id'] ) );
            $form  = $entry ? IQW_Form_Builder::get_form( $entry->form_id ) : null;
            $notes = $entry ? IQW_Submission::get_notes( $entry->id ) : array();
            include IQW_PLUGIN_DIR . 'admin/views/entry-detail.php';
        } else {
            $per_page = 20;
            $paged = max( 1, absint( $_GET['paged'] ?? 1 ) );

            $filter_args = array(
                'form_id'   => absint( $_GET['form_id'] ?? 0 ),
                'status'    => sanitize_text_field( $_GET['status'] ?? '' ),
                'search'    => sanitize_text_field( $_GET['s'] ?? '' ),
                'date_from' => sanitize_text_field( $_GET['date_from'] ?? '' ),
                'date_to'   => sanitize_text_field( $_GET['date_to'] ?? '' ),
                'limit'     => $per_page,
                'offset'    => ( $paged - 1 ) * $per_page,
            );

            $entries = IQW_Submission::get_entries( $filter_args );
            $total   = IQW_Submission::get_entries_total( $filter_args );
            $pages   = ceil( $total / $per_page );
            $counts  = IQW_Submission::get_entry_counts();
            $forms   = IQW_Form_Builder::get_forms();
            include IQW_PLUGIN_DIR . 'admin/views/entries-list.php';
        }
    }

    public function page_settings() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to manage plugin settings.', 'iqw' ) );
        }
        if ( isset( $_POST['iqw_save_settings'] ) && check_admin_referer( 'iqw_settings_nonce' ) ) {
            $settings_handler = new IQW_Admin_Settings();
            $settings_handler->save_settings( $_POST );
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.', 'iqw' ) . '</p></div>';
        }
        include IQW_PLUGIN_DIR . 'admin/views/settings.php';
    }

    public function page_email_templates() {
        // Save template
        if ( isset( $_POST['iqw_save_email_template'] ) && check_admin_referer( 'iqw_email_template_nonce' ) ) {
            $template_type = sanitize_text_field( $_POST['template_type'] ?? 'admin' );
            $raw_html = wp_unslash( $_POST['template_html'] ?? '' );

            // Custom KSES for email HTML — allows <style> blocks and inline styles
            // Only admins (manage_options) can reach this code path
            $allowed = wp_kses_allowed_html( 'post' );
            $allowed['style'] = array( 'type' => true );
            $allowed['table'] = array( 'class' => true, 'id' => true, 'style' => true, 'width' => true, 'cellpadding' => true, 'cellspacing' => true, 'border' => true, 'align' => true, 'bgcolor' => true );
            $allowed['tr'] = array( 'class' => true, 'style' => true, 'bgcolor' => true );
            $allowed['td'] = array( 'class' => true, 'style' => true, 'width' => true, 'colspan' => true, 'rowspan' => true, 'align' => true, 'valign' => true, 'bgcolor' => true );
            $allowed['th'] = $allowed['td'];
            $allowed['thead'] = array( 'class' => true, 'style' => true );
            $allowed['tbody'] = array( 'class' => true, 'style' => true );
            $allowed['div']['style'] = true;
            $allowed['span']['style'] = true;
            $allowed['p']['style'] = true;
            $allowed['a']['style'] = true;
            $allowed['img']['style'] = true;

            $template_html = wp_kses( $raw_html, $allowed );
            update_option( 'iqw_email_template_' . $template_type, $template_html );
            echo '<div class="notice notice-success"><p>' . __( 'Template saved.', 'iqw' ) . '</p></div>';
        }

        // Test email
        if ( isset( $_POST['iqw_send_test_email'] ) && check_admin_referer( 'iqw_email_template_nonce' ) ) {
            $test_to = sanitize_email( $_POST['test_email'] ?? '' );
            if ( is_email( $test_to ) ) {
                $emailer = new IQW_Email();
                $sent = $emailer->send_test_email( $test_to, sanitize_text_field( $_POST['template_type'] ?? 'admin' ) );
                echo $sent
                    ? '<div class="notice notice-success"><p>' . __( 'Test email sent!', 'iqw' ) . '</p></div>'
                    : '<div class="notice notice-error"><p>' . __( 'Failed to send test email.', 'iqw' ) . '</p></div>';
            }
        }

        $forms = IQW_Form_Builder::get_forms();
        include IQW_PLUGIN_DIR . 'admin/views/email-templates.php';
    }

    public function page_email_log() {
        $log = IQW_Email::get_all_email_logs( 200 );
        include IQW_PLUGIN_DIR . 'admin/views/email-log.php';
    }

    // ================================================================
    // AJAX Handlers
    // ================================================================

    public function ajax_save_form() {
        check_ajax_referer( 'iqw_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $form_id = absint( $_POST['form_id'] ?? 0 );
        $data = array(
            'title'          => sanitize_text_field( $_POST['title'] ?? '' ),
            'type'           => sanitize_text_field( $_POST['type'] ?? 'custom' ),
            'config'         => json_decode( wp_unslash( $_POST['config'] ?? '{}' ), true ),
            'email_settings' => json_decode( wp_unslash( $_POST['email_settings'] ?? '{}' ), true ),
            'status'         => sanitize_text_field( $_POST['status'] ?? 'draft' ),
        );

        if ( $form_id ) {
            $result = IQW_Form_Builder::update_form( $form_id, $data );
        } else {
            $result = IQW_Form_Builder::create_form( $data );
            $form_id = $result;
        }

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( array( 'form_id' => $form_id ) );
    }

    public function ajax_duplicate_form() {
        check_ajax_referer( 'iqw_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $form_id = absint( $_POST['form_id'] ?? 0 );
        $new_id = IQW_Form_Builder::duplicate_form( $form_id );

        if ( is_wp_error( $new_id ) ) {
            wp_send_json_error( $new_id->get_error_message() );
        }

        wp_send_json_success( array( 'new_id' => $new_id ) );
    }

    public function ajax_delete_form() {
        check_ajax_referer( 'iqw_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $form_id = absint( $_POST['form_id'] ?? 0 );
        $result = IQW_Form_Builder::delete_form( $form_id );
        wp_send_json_success( array( 'deleted' => $result ) );
    }

    public function ajax_bulk_entry_action() {
        check_ajax_referer( 'iqw_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $action = sanitize_text_field( $_POST['bulk_action'] ?? '' );
        $ids = array_map( 'absint', $_POST['entry_ids'] ?? array() );

        foreach ( $ids as $id ) {
            switch ( $action ) {
                case 'read':
                case 'starred':
                case 'archived':
                case 'trash':
                    IQW_Submission::update_entry_status( $id, $action );
                    break;
                case 'delete':
                    IQW_Submission::delete_entry( $id );
                    break;
            }
        }

        wp_send_json_success();
    }

    public function ajax_add_note() {
        check_ajax_referer( 'iqw_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $entry_id = absint( $_POST['entry_id'] ?? 0 );
        $note = sanitize_textarea_field( $_POST['note'] ?? '' );

        if ( ! $entry_id || ! $note ) {
            wp_send_json_error( 'Missing data.' );
        }

        IQW_Submission::add_note( $entry_id, $note );

        $notes = IQW_Submission::get_notes( $entry_id );
        $html = '';
        foreach ( $notes as $n ) {
            $html .= '<div class="iqw-note" data-note-id="' . esc_attr( $n->id ) . '">';
            $html .= '<div class="iqw-note-header">';
            $html .= '<strong>' . esc_html( $n->author ) . '</strong>';
            $html .= '<span class="iqw-note-date">' . esc_html( date( 'M j, Y g:i A', strtotime( $n->created_at ) ) ) . '</span>';
            $html .= '<a href="#" class="iqw-delete-note" data-note-id="' . esc_attr( $n->id ) . '" title="Delete">&times;</a>';
            $html .= '</div>';
            $html .= '<div class="iqw-note-body">' . nl2br( esc_html( $n->note ) ) . '</div>';
            $html .= '</div>';
        }

        wp_send_json_success( array( 'html' => $html ) );
    }

    public function ajax_delete_note() {
        check_ajax_referer( 'iqw_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $note_id = absint( $_POST['note_id'] ?? 0 );
        IQW_Submission::delete_note( $note_id );
        wp_send_json_success();
    }

    public function ajax_clear_email_log() {
        check_ajax_referer( 'iqw_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

        IQW_Email::clear_email_log();
        wp_send_json_success();
    }

    public function ajax_resend_email() {
        check_ajax_referer( 'iqw_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

        $entry_id = absint( $_POST['entry_id'] ?? 0 );
        $type = sanitize_text_field( $_POST['email_type'] ?? 'admin' );

        $emailer = new IQW_Email();
        $sent = $emailer->resend_email( $entry_id, $type );

        if ( $sent ) {
            wp_send_json_success( array( 'message' => 'Email resent successfully.' ) );
        } else {
            wp_send_json_error( 'Failed to resend email.' );
        }
    }

    public function ajax_import_form() {
        check_ajax_referer( 'iqw_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $title = sanitize_text_field( $_POST['title'] ?? 'Imported Form' );
        $type  = sanitize_text_field( $_POST['type'] ?? 'custom' );
        $status = sanitize_text_field( $_POST['status'] ?? 'draft' );

        // Parse and validate config JSON
        $config_raw = wp_unslash( $_POST['config'] ?? '{}' );
        $config = json_decode( $config_raw, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            wp_send_json_error( 'Invalid form config JSON.' );
        }

        // Parse email settings
        $email_raw = wp_unslash( $_POST['email_settings'] ?? '{}' );
        $email_settings = json_decode( $email_raw, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            $email_settings = array();
        }

        // Create the form
        $result = IQW_Form_Builder::create_form( array(
            'title'          => $title . ' (Imported)',
            'type'           => $type,
            'config'         => $config,
            'email_settings' => $email_settings,
            'status'         => $status,
        ) );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( array( 'form_id' => $result ) );
    }

    public function ajax_edit_entry() {
        check_ajax_referer( 'iqw_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $entry_id = absint( $_POST['entry_id'] ?? 0 );
        if ( ! $entry_id ) wp_send_json_error( 'Missing entry ID.' );

        $fields_raw = $_POST['fields'] ?? array();
        if ( empty( $fields_raw ) || ! is_array( $fields_raw ) ) {
            wp_send_json_error( 'No data provided.' );
        }

        // Sanitize all field values
        $clean = array();
        foreach ( $fields_raw as $key => $value ) {
            $key = sanitize_key( $key );
            $clean[ $key ] = is_array( $value )
                ? array_map( 'sanitize_text_field', $value )
                : sanitize_text_field( $value );
        }

        $result = IQW_Submission::update_entry_data( $entry_id, $clean );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( array( 'message' => 'Entry updated.' ) );
    }
}
