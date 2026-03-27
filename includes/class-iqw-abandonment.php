<?php
/**
 * Form Abandonment Tracking
 * Captures partial entries when users start but don't finish forms.
 * Sends recovery emails with resume links.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class IQW_Abandonment {

    private static $table = 'iqw_abandonments';

    /**
     * Create abandonment table on activation
     */
    public static function create_table() {
        global $wpdb;
        $table = $wpdb->prefix . self::$table;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            form_id BIGINT(20) UNSIGNED NOT NULL,
            email VARCHAR(200) DEFAULT NULL,
            data LONGTEXT,
            last_step INT DEFAULT 0,
            total_steps INT DEFAULT 0,
            ip_address VARCHAR(45) DEFAULT NULL,
            recovery_sent TINYINT(1) DEFAULT 0,
            recovered TINYINT(1) DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY form_id (form_id),
            KEY email (email),
            KEY recovery_sent (recovery_sent)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * AJAX: Save partial entry (called from frontend on step change)
     */
    public static function ajax_save_partial() {
        if ( ! check_ajax_referer( 'iqw_submit_nonce', 'iqw_nonce', false ) ) {
            wp_send_json_error( 'Security check failed.', 403 );
        }

        $form_id    = absint( $_POST['form_id'] ?? 0 );
        $fields     = $_POST['fields'] ?? array();
        $step       = absint( $_POST['current_step'] ?? 0 );
        $total      = absint( $_POST['total_steps'] ?? 0 );
        $abandon_id = absint( $_POST['abandon_id'] ?? 0 );

        if ( ! $form_id ) wp_send_json_error( 'Missing form ID.' );

        // Sanitize fields
        $clean = array();
        foreach ( $fields as $key => $value ) {
            $key = sanitize_key( $key );
            $clean[ $key ] = is_array( $value )
                ? array_map( 'sanitize_text_field', $value )
                : sanitize_text_field( $value );
        }

        // Extract email if present
        $email = '';
        foreach ( array( 'email', 'email_address', 'your_email' ) as $ek ) {
            if ( ! empty( $clean[ $ek ] ) && is_email( $clean[ $ek ] ) ) {
                $email = $clean[ $ek ];
                break;
            }
        }

        global $wpdb;
        $table = $wpdb->prefix . self::$table;

        if ( $abandon_id ) {
            // Update existing
            $wpdb->update( $table, array(
                'data'        => wp_json_encode( $clean ),
                'email'       => $email ?: null,
                'last_step'   => $step,
                'total_steps' => $total,
                'updated_at'  => current_time( 'mysql' ),
            ), array( 'id' => $abandon_id ), array( '%s', '%s', '%d', '%d', '%s' ), array( '%d' ) );

            wp_send_json_success( array( 'abandon_id' => $abandon_id ) );
        } else {
            // Create new
            $ip = get_option( 'iqw_gdpr_disable_ip' ) ? '0.0.0.0' : sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );

            $wpdb->insert( $table, array(
                'form_id'     => $form_id,
                'email'       => $email ?: null,
                'data'        => wp_json_encode( $clean ),
                'last_step'   => $step,
                'total_steps' => $total,
                'ip_address'  => $ip,
            ), array( '%d', '%s', '%s', '%d', '%d', '%s' ) );

            wp_send_json_success( array( 'abandon_id' => $wpdb->insert_id ) );
        }
    }

    /**
     * Mark as recovered (called after successful submission)
     */
    public static function mark_recovered( $form_id, $email ) {
        if ( ! $email ) return;
        global $wpdb;
        $table = $wpdb->prefix . self::$table;
        $wpdb->update( $table, array(
            'recovered' => 1,
        ), array(
            'form_id' => $form_id,
            'email'   => $email,
            'recovered' => 0,
        ), array( '%d' ), array( '%d', '%s', '%d' ) );
    }

    /**
     * Send recovery emails (called from WP-Cron, runs every hour)
     */
    public static function send_recovery_emails() {
        if ( ! get_option( 'iqw_abandonment_recovery_enabled' ) ) return;

        $delay_minutes = absint( get_option( 'iqw_abandonment_delay_minutes', 60 ) );
        if ( $delay_minutes < 5 ) $delay_minutes = 60;

        global $wpdb;
        $table = $wpdb->prefix . self::$table;

        // Find abandonments: has email, not recovered, not already sent, older than delay
        $cutoff = date( 'Y-m-d H:i:s', strtotime( "-{$delay_minutes} minutes" ) );
        $abandonments = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE email IS NOT NULL AND email != '' AND recovery_sent = 0 AND recovered = 0 AND updated_at < %s ORDER BY updated_at ASC LIMIT 20",
            $cutoff
        ) );

        if ( empty( $abandonments ) ) return;

        $subject_tpl = get_option( 'iqw_abandonment_email_subject', 'Continue your {form_title} quote' );
        $body_tpl = get_option( 'iqw_abandonment_email_body', '' );

        foreach ( $abandonments as $ab ) {
            $form = IQW_Form_Builder::get_form( $ab->form_id );
            if ( ! $form ) continue;

            $data = json_decode( $ab->data, true ) ?: array();

            // Build resume URL via save-continue token
            $token = wp_generate_password( 32, false );
            $drafts_table = $wpdb->prefix . 'iqw_drafts';
            $wpdb->insert( $drafts_table, array(
                'form_id'      => $ab->form_id,
                'token'        => $token,
                'data'         => $ab->data,
                'current_step' => $ab->last_step,
                'ip_address'   => $ab->ip_address ?: '0.0.0.0',
                'expires_at'   => date( 'Y-m-d H:i:s', strtotime( '+7 days' ) ),
            ), array( '%d', '%s', '%s', '%d', '%s', '%s' ) );

            $resume_url = add_query_arg( 'iqw_resume', $token, home_url( '/' ) );

            // Replace merge tags
            $replacements = array(
                '{form_title}'  => $form->title,
                '{full_name}'   => $data['full_name'] ?? $data['first_name'] ?? 'there',
                '{first_name}'  => $data['first_name'] ?? explode( ' ', $data['full_name'] ?? '' )[0] ?: 'there',
                '{email}'       => $ab->email,
                '{resume_url}'  => $resume_url,
                '{resume_link}' => '<a href="' . esc_url( $resume_url ) . '">Continue your quote</a>',
                '{company_name}' => get_option( 'iqw_company_name', get_bloginfo( 'name' ) ),
            );

            $subject = str_replace( array_keys( $replacements ), array_values( $replacements ), $subject_tpl );

            if ( $body_tpl ) {
                $body = str_replace( array_keys( $replacements ), array_values( $replacements ), $body_tpl );
            } else {
                // Default recovery email
                $company = get_option( 'iqw_company_name', get_bloginfo( 'name' ) );
                $body = '<div style="max-width:600px;margin:0 auto;font-family:Arial,sans-serif;">';
                $body .= '<h2 style="color:#333;">Hi ' . esc_html( $replacements['{first_name}'] ) . ',</h2>';
                $body .= '<p>We noticed you started a <strong>' . esc_html( $form->title ) . '</strong> but didn\'t finish.</p>';
                $body .= '<p>Your progress has been saved! Click below to pick up where you left off:</p>';
                $body .= '<p style="text-align:center;margin:24px 0;"><a href="' . esc_url( $resume_url ) . '" style="background:#4CAF50;color:#fff;padding:14px 28px;border-radius:6px;text-decoration:none;font-weight:bold;display:inline-block;">Continue My Quote →</a></p>';
                $body .= '<p style="color:#999;font-size:13px;">This link expires in 7 days. If you\'ve already completed your submission, please disregard this email.</p>';
                $body .= '<p style="color:#999;font-size:13px;">— ' . esc_html( $company ) . '</p>';
                $body .= '</div>';
            }

            $headers = array( 'Content-Type: text/html; charset=UTF-8' );
            $from_name = get_option( 'iqw_company_name', get_bloginfo( 'name' ) );
            $from_email = get_option( 'iqw_admin_email', get_option( 'admin_email' ) );
            $headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';

            $sent = wp_mail( $ab->email, $subject, $body, $headers );

            // Mark as sent regardless of success (prevent spam)
            $wpdb->update( $table, array( 'recovery_sent' => 1 ), array( 'id' => $ab->id ), array( '%d' ), array( '%d' ) );
        }
    }

    /**
     * Cleanup old abandonments (called from daily cron)
     */
    public static function cleanup() {
        global $wpdb;
        $table = $wpdb->prefix . self::$table;
        // Delete abandonments older than 30 days
        $wpdb->query( "DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)" );
    }

    /**
     * Get abandonment stats for a form
     */
    public static function get_stats( $form_id = 0 ) {
        global $wpdb;
        $table = $wpdb->prefix . self::$table;

        $where = $form_id ? $wpdb->prepare( "WHERE form_id = %d", $form_id ) : '';

        return array(
            'total'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} {$where}" ),
            'with_email' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} {$where}" . ( $where ? ' AND' : ' WHERE' ) . " email IS NOT NULL AND email != ''" ),
            'recovered' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} {$where}" . ( $where ? ' AND' : ' WHERE' ) . " recovered = 1" ),
            'emails_sent' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} {$where}" . ( $where ? ' AND' : ' WHERE' ) . " recovery_sent = 1" ),
        );
    }
}
