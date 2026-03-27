<?php
/**
 * Submission Handler
 * Processes form submissions, saves to DB, triggers emails.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class IQW_Submission {

    /**
     * Handle AJAX form submission
     */
    public function handle_ajax_submit() {
        // Verify nonce
        if ( ! check_ajax_referer( 'iqw_submit_nonce', 'iqw_nonce', false ) ) {
            wp_send_json_error( array(
                'message' => __( 'Security verification failed. Please refresh and try again.', 'iqw' ),
            ), 403 );
        }

        // Get form ID
        $form_id = absint( $_POST['form_id'] ?? 0 );
        if ( ! $form_id ) {
            wp_send_json_error( array(
                'message' => __( 'Invalid form.', 'iqw' ),
            ), 400 );
        }

        // Load form
        $form = IQW_Form_Builder::get_form( $form_id );
        if ( ! $form || $form->status !== 'active' ) {
            wp_send_json_error( array(
                'message' => __( 'This form is no longer available.', 'iqw' ),
            ), 404 );
        }

        // Security checks
        $security = new IQW_Security();
        $security_check = $security->validate_submission( $_POST );
        if ( is_wp_error( $security_check ) ) {
            wp_send_json_error( array(
                'message' => $security_check->get_error_message(),
            ), 429 );
        }

        // Get submitted field data
        $raw_data = $_POST['fields'] ?? array();
        if ( empty( $raw_data ) ) {
            wp_send_json_error( array(
                'message' => __( 'No data submitted.', 'iqw' ),
            ), 400 );
        }

        // Validate fields
        $validator = new IQW_Validator();

        // Server-side scheduling check (prevents direct POST bypass)
        $sched = $form->config['settings'] ?? array();
        if ( ! empty( $sched['schedule_enabled'] ) ) {
            $now = current_time( 'timestamp' );
            if ( ! empty( $sched['schedule_start'] ) && $now < strtotime( $sched['schedule_start'] ) ) {
                wp_send_json_error( array( 'message' => __( 'This form is not yet open for submissions.', 'iqw' ) ), 403 );
            }
            if ( ! empty( $sched['schedule_end'] ) && $now > strtotime( $sched['schedule_end'] ) ) {
                wp_send_json_error( array( 'message' => __( 'This form is no longer accepting submissions.', 'iqw' ) ), 403 );
            }
            if ( ! empty( $sched['schedule_max_submissions'] ) ) {
                $max = intval( $sched['schedule_max_submissions'] );
                if ( $max > 0 ) {
                    $counts = self::get_entry_counts( $form_id );
                    if ( ( ( $counts['total'] ?? 0 ) - ( $counts['trash'] ?? 0 ) ) >= $max ) {
                        wp_send_json_error( array( 'message' => __( 'This form has reached its submission limit.', 'iqw' ) ), 403 );
                    }
                }
            }
        }

        $validation = $validator->validate_submission( $raw_data, $form->config );
        if ( is_wp_error( $validation ) ) {
            wp_send_json_error( array(
                'message' => $validation->get_error_message(),
                'errors'  => $validation->get_error_data(),
            ), 422 );
        }

        // Sanitize all field data
        $clean_data = $validator->sanitize_fields( $raw_data, $form->config );

        // Handle file uploads
        $clean_data = $this->process_file_uploads( $clean_data, $form->config );

        // Verify Stripe payment if form has a payment field
        if ( $this->form_has_payment_field( $form->config ) ) {
            $payment_intent_id = sanitize_text_field( $post_data['iqw_payment_intent_id'] ?? '' );
            if ( empty( $payment_intent_id ) ) {
                wp_send_json_error( array(
                    'message' => __( 'Payment is required. Please complete payment before submitting.', 'iqw' ),
                ), 422 );
            }
            if ( ! IQW_Stripe::verify_payment( $payment_intent_id ) ) {
                wp_send_json_error( array(
                    'message' => __( 'Payment verification failed. Please try again or use a different card.', 'iqw' ),
                ), 422 );
            }
            $clean_data['_payment_intent_id'] = $payment_intent_id;
            $clean_data['_payment_status'] = 'succeeded';
        }

        // Save entry
        $entry_id = $this->save_entry( $form_id, $clean_data );
        if ( is_wp_error( $entry_id ) ) {
            wp_send_json_error( array(
                'message' => __( 'Failed to save your submission. Please try again.', 'iqw' ),
            ), 500 );
        }

        // Send email notifications
        $emailer = new IQW_Email();
        $emailer->send_notifications( $entry_id, $form, $clean_data );

        // Fire webhooks (Zapier, CRM, etc.)
        IQW_Webhook::fire( $entry_id, $form, $clean_data );

        // Push to Google Sheets
        IQW_Google_Sheets::push( $entry_id, $form, $clean_data );

        // Subscribe to Mailchimp
        IQW_Mailchimp::subscribe( $entry_id, $form, $clean_data );

        // Send SMS notification
        IQW_SMS::notify( $entry_id, $form, $clean_data );

        // Mark abandonment as recovered
        IQW_Abandonment::mark_recovered( $form_id, $clean_data['email'] ?? '' );

        // Track analytics completion
        IQW_Analytics::track_completion( $form->id );

        // Delete draft if user was resuming from save-and-continue
        $draft_token = sanitize_text_field( $post_data['iqw_draft_token'] ?? '' );
        if ( $draft_token ) {
            IQW_Save_Continue::delete_draft( $draft_token );
        }

        // Success response
        wp_send_json_success( array(
            'message'  => __( 'Thank you! Your quote request has been submitted.', 'iqw' ),
            'entry_id' => $entry_id,
            'redirect' => $form->config['settings']['redirect_url'] ?? '',
        ) );
    }

    /**
     * Handle REST API submission
     */
    public function handle_rest_submit( $request ) {
        // Verify nonce for CSRF protection
        $nonce = $request->get_param( 'iqw_nonce' ) ?? $request->get_header( 'X-WP-Nonce' ) ?? '';
        if ( ! wp_verify_nonce( $nonce, 'iqw_submit_nonce' ) ) {
            return new WP_Error( 'nonce_failed', __( 'Security verification failed. Please refresh and try again.', 'iqw' ), array( 'status' => 403 ) );
        }

        $form_id  = absint( $request->get_param( 'form_id' ) );
        $fields   = $request->get_param( 'fields' );

        if ( ! $form_id || empty( $fields ) ) {
            return new WP_Error( 'invalid_data', __( 'Invalid submission data.', 'iqw' ), array( 'status' => 400 ) );
        }

        // Security checks (rate limiting, honeypot, time check)
        $security = new IQW_Security();
        $security_check = $security->validate_submission( $request->get_params() );
        if ( is_wp_error( $security_check ) ) {
            return new WP_Error( 'security_failed', $security_check->get_error_message(), array( 'status' => 429 ) );
        }

        $form = IQW_Form_Builder::get_form( $form_id );
        if ( ! $form || $form->status !== 'active' ) {
            return new WP_Error( 'form_not_found', __( 'Form not found.', 'iqw' ), array( 'status' => 404 ) );
        }

        // Validate
        $validator = new IQW_Validator();
        $validation = $validator->validate_submission( $fields, $form->config );
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }

        // Sanitize & save
        $clean_data = $validator->sanitize_fields( $fields, $form->config );

        // Verify Stripe payment if form has a payment field
        if ( $this->form_has_payment_field( $form->config ) ) {
            $payment_intent_id = sanitize_text_field( $request->get_param( 'iqw_payment_intent_id' ) ?? '' );
            if ( empty( $payment_intent_id ) ) {
                return new WP_Error( 'payment_required', __( 'Payment is required.', 'iqw' ), array( 'status' => 422 ) );
            }
            if ( ! IQW_Stripe::verify_payment( $payment_intent_id ) ) {
                return new WP_Error( 'payment_failed', __( 'Payment verification failed.', 'iqw' ), array( 'status' => 422 ) );
            }
            $clean_data['_payment_intent_id'] = $payment_intent_id;
            $clean_data['_payment_status'] = 'succeeded';
        }

        $entry_id = $this->save_entry( $form_id, $clean_data );

        if ( is_wp_error( $entry_id ) ) {
            return $entry_id;
        }

        // Send emails
        $emailer = new IQW_Email();
        $emailer->send_notifications( $entry_id, $form, $clean_data );

        // Fire webhooks
        IQW_Webhook::fire( $entry_id, $form, $clean_data );

        // Push to Google Sheets
        IQW_Google_Sheets::push( $entry_id, $form, $clean_data );

        // Subscribe to Mailchimp
        IQW_Mailchimp::subscribe( $entry_id, $form, $clean_data );

        // Send SMS notification
        IQW_SMS::notify( $entry_id, $form, $clean_data );

        // Mark abandonment as recovered
        IQW_Abandonment::mark_recovered( $form_id, $clean_data['email'] ?? '' );

        // Track analytics completion
        IQW_Analytics::track_completion( $form->id );

        return array(
            'success'  => true,
            'entry_id' => $entry_id,
            'message'  => __( 'Submission saved successfully.', 'iqw' ),
        );
    }

    /**
     * Save entry to database
     */
    private function save_entry( $form_id, $data ) {
        global $wpdb;

        $table_entries = $wpdb->prefix . IQW_TABLE_ENTRIES;
        $table_meta    = $wpdb->prefix . IQW_TABLE_ENTRY_META;

        // Strip internal metadata keys
        $save_data = array();
        foreach ( $data as $k => $v ) {
            if ( strpos( $k, '__repeater_' ) === 0 ) continue;
            $save_data[ $k ] = $v;
        }

        // Insert main entry
        $result = $wpdb->insert( $table_entries, array(
            'form_id'      => $form_id,
            'data'         => wp_json_encode( $save_data ),
            'status'       => 'new',
            'ip_address'   => get_option( 'iqw_gdpr_disable_ip' ) ? '0.0.0.0' : $this->get_client_ip(),
            'user_agent'   => get_option( 'iqw_gdpr_disable_ua' ) ? '' : substr( sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' ), 0, 255 ),
            'referrer_url' => get_option( 'iqw_gdpr_disable_referrer' ) ? '' : substr( esc_url_raw( $_SERVER['HTTP_REFERER'] ?? '' ), 0, 500 ),
        ), array( '%d', '%s', '%s', '%s', '%s', '%s' ) );

        if ( $result === false ) {
            return new WP_Error( 'db_error', __( 'Database error saving entry.', 'iqw' ) );
        }

        $entry_id = $wpdb->insert_id;

        // Insert searchable meta for key fields
        $searchable_keys = array(
            'full_name', 'first_name', 'last_name', 'email', 'phone',
            'city', 'state', 'zip', 'insurance_type',
        );

        foreach ( $data as $key => $value ) {
            if ( in_array( $key, $searchable_keys, true ) && ! empty( $value ) ) {
                $wpdb->insert( $table_meta, array(
                    'entry_id'    => $entry_id,
                    'field_key'   => sanitize_key( $key ),
                    'field_value' => is_array( $value ) ? wp_json_encode( $value ) : sanitize_text_field( $value ),
                ), array( '%d', '%s', '%s' ) );
            }
        }

        // Fire action for extensibility
        do_action( 'iqw_entry_created', $entry_id, $form_id, $data );

        return $entry_id;
    }

    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' );
        foreach ( $ip_keys as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip = explode( ',', $_SERVER[ $key ] );
                $ip = trim( $ip[0] );
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }

    // ================================================================
    // Entry CRUD (for admin)
    // ================================================================

    /**
     * Get entries with filters
     */
    public static function get_entries( $args = array() ) {
        global $wpdb;
        $table = $wpdb->prefix . IQW_TABLE_ENTRIES;

        $defaults = array(
            'form_id'   => 0,
            'status'    => '',
            'search'    => '',
            'date_from' => '',
            'date_to'   => '',
            'orderby'   => 'created_at',
            'order'     => 'DESC',
            'limit'     => 20,
            'offset'    => 0,
        );
        $args = wp_parse_args( $args, $defaults );

        $where = array( '1=1' );
        $values = array();

        if ( $args['form_id'] ) {
            $where[] = 'e.form_id = %d';
            $values[] = $args['form_id'];
        }
        if ( $args['status'] && $args['status'] !== 'all' ) {
            $where[] = 'e.status = %s';
            $values[] = $args['status'];
        }
        if ( ! empty( $args['date_from'] ) ) {
            $where[] = 'e.created_at >= %s';
            $values[] = $args['date_from'] . ' 00:00:00';
        }
        if ( ! empty( $args['date_to'] ) ) {
            $where[] = 'e.created_at <= %s';
            $values[] = $args['date_to'] . ' 23:59:59';
        }

        // Search via meta table
        $join = '';
        if ( ! empty( $args['search'] ) ) {
            $meta_table = $wpdb->prefix . IQW_TABLE_ENTRY_META;
            $join = "LEFT JOIN {$meta_table} m ON e.id = m.entry_id";
            $where[] = 'm.field_value LIKE %s';
            $values[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
        }

        $where_sql = implode( ' AND ', $where );
        $group = ! empty( $args['search'] ) ? 'GROUP BY e.id' : '';

        // Safe orderby
        $allowed_ob = array( 'created_at', 'id', 'status', 'form_id' );
        $ob = in_array( $args['orderby'], $allowed_ob, true ) ? $args['orderby'] : 'created_at';
        $od = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

        $values[] = (int) $args['limit'];
        $values[] = (int) $args['offset'];

        $sql = "SELECT e.* FROM {$table} e {$join} WHERE {$where_sql} {$group} ORDER BY e.{$ob} {$od} LIMIT %d OFFSET %d";

        return $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
    }

    /**
     * Get single entry
     */
    public static function get_entry( $id ) {
        global $wpdb;
        $table = $wpdb->prefix . IQW_TABLE_ENTRIES;

        $entry = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d", $id
        ) );

        if ( $entry ) {
            $entry->data = json_decode( $entry->data, true ) ?: array();

            // Mark as read
            if ( $entry->status === 'new' ) {
                $wpdb->update( $table,
                    array( 'status' => 'read', 'read_at' => current_time( 'mysql' ) ),
                    array( 'id' => $id ),
                    array( '%s', '%s' ),
                    array( '%d' )
                );
                $entry->status = 'read';
                $entry->read_at = current_time( 'mysql' );
            }
        }

        return $entry;
    }

    /**
     * Update entry status
     */
    public static function update_entry_status( $id, $status ) {
        global $wpdb;
        $table = $wpdb->prefix . IQW_TABLE_ENTRIES;

        $allowed = array( 'new', 'read', 'starred', 'archived', 'trash', 'contacted', 'quoted', 'sold', 'lost' );
        if ( ! in_array( $status, $allowed, true ) ) {
            return false;
        }

        return $wpdb->update( $table,
            array( 'status' => $status ),
            array( 'id' => $id ),
            array( '%s' ),
            array( '%d' )
        ) !== false;
    }

    /**
     * Delete entry permanently
     */
    public static function delete_entry( $id ) {
        global $wpdb;

        // Get entry data to find uploaded file URLs before deleting
        $table = $wpdb->prefix . IQW_TABLE_ENTRIES;
        $entry = $wpdb->get_row( $wpdb->prepare( "SELECT data FROM {$table} WHERE id = %d", $id ) );
        if ( $entry ) {
            $data = json_decode( $entry->data, true ) ?: array();
            if ( is_array( $data ) ) {
                $upload_dir = wp_upload_dir();
                foreach ( $data as $value ) {
                    if ( ! is_string( $value ) ) continue;
                    // Delete uploaded files that are within our uploads directory
                    if ( filter_var( $value, FILTER_VALIDATE_URL ) && strpos( $value, $upload_dir['baseurl'] ) === 0 ) {
                        $file_path = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $value );
                        if ( file_exists( $file_path ) ) {
                            @unlink( $file_path );
                        }
                    }
                }
            }
        }

        // Delete generated PDFs for this entry
        $upload_dir = wp_upload_dir();
        $pdf_dir = $upload_dir['basedir'] . '/iqw-pdfs';
        if ( is_dir( $pdf_dir ) ) {
            $pattern = $pdf_dir . '/entry-' . $id . '-*.html';
            foreach ( glob( $pattern ) as $pdf_file ) {
                @unlink( $pdf_file );
            }
        }

        // Delete meta
        $wpdb->delete(
            $wpdb->prefix . IQW_TABLE_ENTRY_META,
            array( 'entry_id' => $id ),
            array( '%d' )
        );

        // Delete related notes
        $wpdb->delete(
            $wpdb->prefix . 'iqw_entry_notes',
            array( 'entry_id' => $id ),
            array( '%d' )
        );

        // Delete related email logs
        $wpdb->delete(
            $wpdb->prefix . 'iqw_email_log',
            array( 'entry_id' => $id ),
            array( '%d' )
        );

        // Delete entry
        return $wpdb->delete(
            $wpdb->prefix . IQW_TABLE_ENTRIES,
            array( 'id' => $id ),
            array( '%d' )
        ) !== false;
    }

    /**
     * Get entry counts by status
     */
    public static function get_entry_counts( $form_id = 0 ) {
        global $wpdb;
        $table = $wpdb->prefix . IQW_TABLE_ENTRIES;

        if ( $form_id ) {
            $results = $wpdb->get_results( $wpdb->prepare(
                "SELECT status, COUNT(*) as count FROM {$table} WHERE form_id = %d GROUP BY status",
                $form_id
            ) );
        } else {
            $results = $wpdb->get_results(
                "SELECT status, COUNT(*) as count FROM {$table} GROUP BY status"
            );
        }

        $counts = array( 'all' => 0, 'new' => 0, 'read' => 0, 'starred' => 0, 'contacted' => 0, 'quoted' => 0, 'sold' => 0, 'lost' => 0, 'archived' => 0, 'trash' => 0 );
        foreach ( $results as $row ) {
            $counts[ $row->status ] = (int) $row->count;
            if ( $row->status !== 'trash' ) {
                $counts['all'] += (int) $row->count;
            }
        }

        return $counts;
    }

    /**
     * Get entry counts for ALL forms in one query (fixes N+1)
     */
    public static function get_all_form_entry_counts() {
        global $wpdb;
        $table = $wpdb->prefix . IQW_TABLE_ENTRIES;

        $results = $wpdb->get_results(
            "SELECT form_id, COUNT(*) as count FROM {$table} WHERE status != 'trash' GROUP BY form_id"
        );

        $counts = array();
        foreach ( $results as $row ) {
            $counts[ (int) $row->form_id ] = (int) $row->count;
        }

        return $counts;
    }

    /**
     * Get new (unread) entry count for admin menu badge
     */
    public static function get_unread_count() {
        global $wpdb;
        $table = $wpdb->prefix . IQW_TABLE_ENTRIES;

        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} WHERE status = 'new'"
        );
    }

    /**
     * Get total entries count (for pagination)
     */
    public static function get_entries_total( $args = array() ) {
        global $wpdb;
        $table = $wpdb->prefix . IQW_TABLE_ENTRIES;

        $where = array( '1=1' );
        $values = array();

        if ( ! empty( $args['form_id'] ) ) {
            $where[] = 'e.form_id = %d';
            $values[] = $args['form_id'];
        }
        if ( ! empty( $args['status'] ) && $args['status'] !== 'all' ) {
            $where[] = 'e.status = %s';
            $values[] = $args['status'];
        }
        if ( ! empty( $args['date_from'] ) ) {
            $where[] = 'e.created_at >= %s';
            $values[] = $args['date_from'] . ' 00:00:00';
        }
        if ( ! empty( $args['date_to'] ) ) {
            $where[] = 'e.created_at <= %s';
            $values[] = $args['date_to'] . ' 23:59:59';
        }

        $join = '';
        if ( ! empty( $args['search'] ) ) {
            $meta_table = $wpdb->prefix . IQW_TABLE_ENTRY_META;
            $join = "LEFT JOIN {$meta_table} m ON e.id = m.entry_id";
            $where[] = 'm.field_value LIKE %s';
            $values[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
        }

        $where_sql = implode( ' AND ', $where );
        $distinct = ! empty( $args['search'] ) ? 'DISTINCT e.id' : '*';

        if ( ! empty( $values ) ) {
            return (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT({$distinct}) FROM {$table} e {$join} WHERE {$where_sql}",
                $values
            ) );
        }

        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} e WHERE 1=1" );
    }

    /**
     * Get entries for today (dashboard)
     */
    public static function get_today_count() {
        global $wpdb;
        $table = $wpdb->prefix . IQW_TABLE_ENTRIES;
        $today = current_time( 'Y-m-d' );

        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE created_at >= %s AND status != 'trash'",
            $today . ' 00:00:00'
        ) );
    }

    /**
     * Get entries for this week
     */
    public static function get_week_count() {
        global $wpdb;
        $table = $wpdb->prefix . IQW_TABLE_ENTRIES;
        $week_start = date( 'Y-m-d', strtotime( 'monday this week' ) );

        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE created_at >= %s AND status != 'trash'",
            $week_start . ' 00:00:00'
        ) );
    }

    /**
     * Get daily entry counts for chart (last N days)
     */
    public static function get_daily_counts( $days = 14 ) {
        global $wpdb;
        $table = $wpdb->prefix . IQW_TABLE_ENTRIES;
        $start = date( 'Y-m-d', strtotime( "-{$days} days" ) );

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT DATE(created_at) as day, COUNT(*) as count
             FROM {$table}
             WHERE created_at >= %s AND status != 'trash'
             GROUP BY DATE(created_at)
             ORDER BY day ASC",
            $start . ' 00:00:00'
        ) );

        // Fill in missing days with 0
        $counts = array();
        $date = new DateTime( $start );
        $today = new DateTime( current_time( 'Y-m-d' ) );

        while ( $date <= $today ) {
            $day = $date->format( 'Y-m-d' );
            $found = 0;
            foreach ( $results as $r ) {
                if ( $r->day === $day ) { $found = (int) $r->count; break; }
            }
            $counts[] = array( 'date' => $day, 'label' => $date->format( 'M j' ), 'count' => $found );
            $date->modify( '+1 day' );
        }

        return $counts;
    }

    // ================================================================
    // Entry Notes
    // ================================================================

    /**
     * Add a note to an entry
     */
    public static function add_note( $entry_id, $note ) {
        global $wpdb;

        $current_user = wp_get_current_user();
        $author = $current_user->display_name ?: $current_user->user_login;

        return $wpdb->insert(
            $wpdb->prefix . 'iqw_entry_notes',
            array(
                'entry_id'   => absint( $entry_id ),
                'note'       => sanitize_textarea_field( $note ),
                'author'     => $author,
            ),
            array( '%d', '%s', '%s' )
        );
    }

    /**
     * Get notes for an entry
     */
    public static function get_notes( $entry_id ) {
        global $wpdb;

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}iqw_entry_notes WHERE entry_id = %d ORDER BY created_at DESC",
            $entry_id
        ) );
    }

    /**
     * Delete a note
     */
    public static function delete_note( $note_id ) {
        global $wpdb;
        return $wpdb->delete( $wpdb->prefix . 'iqw_entry_notes', array( 'id' => $note_id ), array( '%d' ) );
    }

    /**
     * Check if form config contains a payment field
     */
    private function form_has_payment_field( $config ) {
        if ( empty( $config['steps'] ) ) return false;
        foreach ( $config['steps'] as $step ) {
            foreach ( $step['fields'] ?? array() as $field ) {
                if ( ( $field['type'] ?? '' ) === 'payment' ) return true;
            }
        }
        return false;
    }

    /**
     * Process file uploads from $_FILES
     */
    private function process_file_uploads( $data, $config ) {
        if ( empty( $_FILES ) ) return $data;

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $allowed_types = array(
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        );
        $max_size = 10 * 1024 * 1024; // 10MB

        foreach ( $_FILES as $key => $file_info ) {
            $clean_key = str_replace( 'fields_', '', sanitize_key( $key ) );

            if ( $file_info['error'] === UPLOAD_ERR_NO_FILE ) continue;
            if ( $file_info['error'] !== UPLOAD_ERR_OK ) {
                $data[ $clean_key ] = '[Upload error]';
                continue;
            }
            if ( $file_info['size'] > $max_size ) {
                $data[ $clean_key ] = '[File too large - max 10MB]';
                continue;
            }
            if ( ! in_array( $file_info['type'], $allowed_types, true ) ) {
                $data[ $clean_key ] = '[Invalid file type]';
                continue;
            }

            $upload = wp_handle_upload( $file_info, array( 'test_form' => false ) );
            if ( ! empty( $upload['url'] ) ) {
                $data[ $clean_key ] = esc_url_raw( $upload['url'] );
            } else {
                $data[ $clean_key ] = '[Upload failed]';
            }
        }

        return $data;
    }

    /**
     * Update entry data (admin editing)
     */
    public static function update_entry_data( $entry_id, $new_data ) {
        global $wpdb;
        $table = $wpdb->prefix . IQW_TABLE_ENTRIES;
        $meta_table = $wpdb->prefix . IQW_TABLE_ENTRY_META;

        $result = $wpdb->update(
            $table,
            array( 'data' => wp_json_encode( $new_data ) ),
            array( 'id' => $entry_id ),
            array( '%s' ),
            array( '%d' )
        );

        if ( $result === false ) {
            return new WP_Error( 'db_error', __( 'Failed to update entry.', 'iqw' ) );
        }

        // Update searchable meta
        $wpdb->delete( $meta_table, array( 'entry_id' => $entry_id ), array( '%d' ) );

        $searchable_keys = array(
            'full_name', 'first_name', 'last_name', 'email', 'phone',
            'city', 'state', 'zip', 'insurance_type',
        );
        foreach ( $new_data as $key => $value ) {
            if ( in_array( $key, $searchable_keys, true ) && ! empty( $value ) ) {
                $wpdb->insert( $meta_table, array(
                    'entry_id'    => $entry_id,
                    'field_key'   => sanitize_key( $key ),
                    'field_value' => is_array( $value ) ? wp_json_encode( $value ) : sanitize_text_field( $value ),
                ), array( '%d', '%s', '%s' ) );
            }
        }

        return true;
    }
}
