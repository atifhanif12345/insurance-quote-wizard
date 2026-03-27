<?php
/**
 * Email Notification System - Phase 4
 * Professional EverQuote/QuoteWizard styled emails, per-entry logging,
 * test emails, resend, merge tags, template customization.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class IQW_Email {

    /**
     * Send all notifications for an entry
     */
    public function send_notifications( $entry_id, $form, $data ) {
        $settings = $form->email_settings ?? array();

        // Capture wp_mail errors
        add_action( 'wp_mail_failed', array( $this, 'capture_mail_error' ) );

        // Admin notification
        if ( ! empty( $settings['admin_enabled'] ) || ! isset( $settings['admin_enabled'] ) ) {
            $this->send_admin_notification( $entry_id, $form, $data, $settings );
        }

        // Conditional notification routing
        $routing = $settings['routing_rules'] ?? array();
        foreach ( $routing as $rule ) {
            if ( empty( $rule['enabled'] ) || empty( $rule['email'] ) || empty( $rule['field'] ) ) continue;
            $field_val = $data[ $rule['field'] ] ?? '';
            if ( is_array( $field_val ) ) $field_val = implode( ',', $field_val );
            $match_val = $rule['value'] ?? '';
            $operator = $rule['operator'] ?? 'is';

            $matched = false;
            switch ( $operator ) {
                case 'is':      $matched = ( strtolower( (string) $field_val ) === strtolower( (string) $match_val ) ); break;
                case 'is_not':  $matched = ( strtolower( (string) $field_val ) !== strtolower( (string) $match_val ) ); break;
                case 'contains': $matched = ( stripos( $field_val, $match_val ) !== false ); break;
                default:         $matched = ( strtolower( (string) $field_val ) === strtolower( (string) $match_val ) );
            }

            if ( $matched ) {
                $override = $settings;
                $override['admin_to'] = sanitize_email( $rule['email'] );
                if ( ! empty( $rule['cc'] ) ) $override['admin_cc'] = sanitize_text_field( $rule['cc'] );
                if ( ! empty( $rule['subject'] ) ) $override['admin_subject'] = sanitize_text_field( $rule['subject'] );
                $this->send_admin_notification( $entry_id, $form, $data, $override );
            }
        }

        // Customer confirmation
        if ( ! empty( $settings['customer_enabled'] ) && ! empty( $data['email'] ) ) {
            $this->send_customer_confirmation( $entry_id, $form, $data, $settings );
        }

        remove_action( 'wp_mail_failed', array( $this, 'capture_mail_error' ) );

        do_action( 'iqw_after_notifications', $entry_id, $form, $data );
    }

    /**
     * Capture wp_mail errors for logging
     */
    public function capture_mail_error( $wp_error ) {
        $log = get_option( 'iqw_email_errors', array() );
        $log[] = array(
            'error'   => $wp_error->get_error_message(),
            'data'    => $wp_error->get_error_data(),
            'date'    => current_time( 'mysql' ),
        );
        if ( count( $log ) > 50 ) $log = array_slice( $log, -50 );
        update_option( 'iqw_email_errors', $log, false );
    }

    /**
     * Send admin notification
     */
    private function send_admin_notification( $entry_id, $form, $data, $settings ) {
        $to = $settings['admin_to'] ?? get_option( 'iqw_admin_email', get_option( 'admin_email' ) );
        if ( empty( $to ) ) return false;

        $subject = $this->process_merge_tags(
            $settings['admin_subject'] ?? 'New {form_title} Lead - {full_name}',
            $form, $data, $entry_id
        );

        $body = $this->build_admin_email( $form, $data, $entry_id );

        $headers = array( 'Content-Type: text/html; charset=UTF-8' );
        $from_name = get_option( 'iqw_company_name', get_bloginfo( 'name' ) );
        $from_email = get_option( 'admin_email' );
        $headers[] = "From: {$from_name} <{$from_email}>";

        if ( ! empty( $data['email'] ) ) {
            $reply_name = $data['full_name'] ?? $data['first_name'] ?? '';
            $headers[] = "Reply-To: {$reply_name} <{$data['email']}>";
        }

        // CC
        $cc = $settings['admin_cc'] ?? '';
        if ( $cc ) {
            foreach ( array_map( 'trim', explode( ',', $cc ) ) as $addr ) {
                if ( is_email( $addr ) ) $headers[] = "Cc: {$addr}";
            }
        }

        // Attach PDF/printable summary if enabled
        $attachments = array();
        if ( get_option( 'iqw_pdf_attach_to_email', false ) ) {
            $attachments = IQW_PDF::attach_to_email( $entry_id );
        }

        $sent = wp_mail( $to, $subject, $body, $headers, $attachments );
        $this->log_email( $entry_id, 'admin', $to, $subject, $sent, $body );

        return $sent;
    }

    /**
     * Send customer confirmation
     */
    private function send_customer_confirmation( $entry_id, $form, $data, $settings ) {
        $to = sanitize_email( $data['email'] );
        if ( ! is_email( $to ) ) return false;

        $subject = $this->process_merge_tags(
            $settings['customer_subject'] ?? 'Thank you for your quote request, {first_name}!',
            $form, $data, $entry_id
        );

        $body = $this->build_customer_email( $form, $data, $entry_id );

        $from_name = get_option( 'iqw_company_name', get_bloginfo( 'name' ) );
        $from_email = get_option( 'admin_email' );
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            "From: {$from_name} <{$from_email}>",
        );

        $sent = wp_mail( $to, $subject, $body, $headers );
        $this->log_email( $entry_id, 'customer', $to, $subject, $sent, $body );

        return $sent;
    }

    /**
     * Resend email for an entry
     */
    public function resend_email( $entry_id, $type = 'admin' ) {
        $entry = IQW_Submission::get_entry( $entry_id );
        if ( ! $entry ) return false;

        $form = IQW_Form_Builder::get_form( $entry->form_id );
        if ( ! $form ) return false;

        $data = $entry->data;
        $settings = $form->email_settings ?? array();

        if ( $type === 'admin' ) {
            return $this->send_admin_notification( $entry_id, $form, $data, $settings );
        } else {
            return $this->send_customer_confirmation( $entry_id, $form, $data, $settings );
        }
    }

    /**
     * Send test email
     */
    public function send_test_email( $to, $type = 'admin' ) {
        $test_data = array(
            'full_name'        => 'John Smith',
            'first_name'       => 'John',
            'last_name'        => 'Smith',
            'email'            => $to,
            'phone'            => '(555) 123-4567',
            'street_address'   => '123 Main Street',
            'city'             => 'Amarillo',
            'state'            => 'TX',
            'zip_code'         => '79118',
            'date_of_birth'    => '1985-06-15',
            'gender'           => 'male',
            'currently_insured' => 'yes',
            'current_insurer'  => 'State Farm',
            'insurance_type'   => 'auto',
        );

        $form = (object) array(
            'id'     => 0,
            'title'  => 'Auto Insurance Quote (TEST)',
            'type'   => 'auto',
            'config' => array( 'steps' => array(
                array( 'title' => 'Contact Information', 'fields' => array(
                    array( 'key' => 'full_name', 'label' => 'Full Name', 'type' => 'text' ),
                    array( 'key' => 'email', 'label' => 'Email', 'type' => 'email' ),
                    array( 'key' => 'phone', 'label' => 'Phone', 'type' => 'phone' ),
                    array( 'key' => 'street_address', 'label' => 'Address', 'type' => 'text' ),
                    array( 'key' => 'city', 'label' => 'City', 'type' => 'text' ),
                    array( 'key' => 'state', 'label' => 'State', 'type' => 'text' ),
                    array( 'key' => 'zip_code', 'label' => 'ZIP', 'type' => 'text' ),
                )),
                array( 'title' => 'Insurance Info', 'fields' => array(
                    array( 'key' => 'currently_insured', 'label' => 'Currently Insured', 'type' => 'radio_cards' ),
                    array( 'key' => 'current_insurer', 'label' => 'Current Insurer', 'type' => 'select' ),
                )),
            )),
            'email_settings' => array(),
        );

        $subject = "[TEST] New Auto Insurance Quote Lead - John Smith";

        if ( $type === 'admin' ) {
            $body = $this->build_admin_email( $form, $test_data, 999 );
        } else {
            $body = $this->build_customer_email( $form, $test_data, 999 );
        }

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_option( 'iqw_company_name', get_bloginfo( 'name' ) ) . ' <' . get_option( 'admin_email' ) . '>',
        );

        return wp_mail( $to, $subject, $body, $headers );
    }

    /**
     * Build admin notification HTML - EverQuote/QuoteWizard style
     */
    private function build_admin_email( $form, $data, $entry_id ) {
        // Check for custom template
        $custom = get_option( 'iqw_email_template_admin', '' );
        if ( $custom ) {
            $template = $custom;
        } else {
            $template = $this->get_admin_template();
        }

        $fields_html = $this->build_fields_table( $form, $data );

        // Quick contact bar
        $contact_html = '';
        if ( ! empty( $data['phone'] ) ) {
            $contact_html .= '<a href="tel:' . esc_attr( preg_replace( '/[^\d+]/', '', $data['phone'] ) ) . '" style="display:inline-block;padding:10px 20px;background:#27ae60;color:#fff;text-decoration:none;border-radius:4px;margin-right:8px;font-weight:bold;">📞 Call ' . esc_html( $data['phone'] ) . '</a>';
        }
        if ( ! empty( $data['email'] ) ) {
            $contact_html .= '<a href="mailto:' . esc_attr( $data['email'] ) . '" style="display:inline-block;padding:10px 20px;background:#3498db;color:#fff;text-decoration:none;border-radius:4px;font-weight:bold;">✉ Email Client</a>';
        }

        // Location string
        $location = array_filter( array(
            $data['city'] ?? '', $data['state'] ?? '', $data['zip_code'] ?? ''
        ) );
        $location_str = implode( ', ', $location );

        $replacements = array(
            '{form_title}'    => esc_html( $form->title ?? '' ),
            '{form_type}'     => esc_html( ucfirst( $form->type ?? 'quote' ) ),
            '{entry_id}'      => $entry_id,
            '{fields_table}'  => $fields_html,
            '{contact_bar}'   => $contact_html,
            '{location}'      => esc_html( $location_str ?: 'Unknown' ),
            '{site_name}'     => get_bloginfo( 'name' ),
            '{site_url}'      => home_url(),
            '{admin_url}'     => admin_url( 'admin.php?page=iqw-entries&action=view&id=' . $entry_id ),
            '{date}'          => current_time( 'F j, Y g:i A' ),
            '{date_short}'    => current_time( 'm/d/Y h:i:s A' ),
            '{company_name}'  => get_option( 'iqw_company_name', get_bloginfo( 'name' ) ),
            '{company_phone}' => get_option( 'iqw_company_phone', '' ),
        );

        $replacements = array_merge( $replacements, $this->get_field_merge_tags( $data ) );

        return str_replace( array_keys( $replacements ), array_values( $replacements ), $template );
    }

    /**
     * Build customer confirmation email
     */
    private function build_customer_email( $form, $data, $entry_id ) {
        $custom = get_option( 'iqw_email_template_customer', '' );
        $template = $custom ?: $this->get_customer_template();

        $replacements = array(
            '{form_title}'    => esc_html( $form->title ?? '' ),
            '{entry_id}'      => $entry_id,
            '{company_name}'  => get_option( 'iqw_company_name', get_bloginfo( 'name' ) ),
            '{company_phone}' => get_option( 'iqw_company_phone', '' ),
            '{site_url}'      => home_url(),
            '{date}'          => current_time( 'F j, Y' ),
        );
        $replacements = array_merge( $replacements, $this->get_field_merge_tags( $data ) );

        return str_replace( array_keys( $replacements ), array_values( $replacements ), $template );
    }

    /**
     * Build fields table for admin email (EverQuote style)
     */
    private function build_fields_table( $form, $data ) {
        $html = '';
        $config = is_array( $form->config ) ? $form->config : ( is_object( $form->config ) ? (array) $form->config : array() );

        if ( ! empty( $config['steps'] ) ) {
            foreach ( $config['steps'] as $step ) {
                if ( empty( $step['fields'] ) ) continue;

                $has_data = false;
                foreach ( $step['fields'] as $f ) {
                    if ( ! empty( $data[ $f['key'] ?? '' ] ) ) { $has_data = true; break; }
                }
                if ( ! $has_data ) continue;

                $title = $step['title'] ?? 'Information';
                $html .= '<tr><td colspan="2" style="background:#4472C4;color:#fff;padding:8px 14px;font-weight:bold;font-size:13px;text-transform:uppercase;letter-spacing:0.5px;">' . esc_html( $title ) . '</td></tr>';

                $alt = false;
                foreach ( $step['fields'] as $field ) {
                    $key   = $field['key'] ?? '';
                    $label = $field['label'] ?? ucwords( str_replace( '_', ' ', $key ) );
                    $value = $data[ $key ] ?? '';

                    if ( is_array( $value ) ) $value = implode( ', ', $value );
                    if ( $value === '' || $value === null ) continue;

                    // Map option values to labels
                    if ( ! empty( $field['options'] ) ) {
                        foreach ( $field['options'] as $opt ) {
                            if ( $opt['value'] === $value ) { $value = $opt['label']; break; }
                        }
                    }

                    $bg = $alt ? '#f8f8f8' : '#ffffff';
                    $html .= '<tr style="background:' . $bg . ';">';
                    $html .= '<td style="padding:8px 14px;border-bottom:1px solid #eee;font-weight:600;width:40%;font-size:13px;color:#555;">' . esc_html( $label ) . '</td>';

                    // Special formatting
                    if ( $field['type'] === 'email' ) {
                        $html .= '<td style="padding:8px 14px;border-bottom:1px solid #eee;font-size:13px;"><a href="mailto:' . esc_attr( $value ) . '" style="color:#3498db;">' . esc_html( $value ) . '</a></td>';
                    } elseif ( $field['type'] === 'phone' ) {
                        $html .= '<td style="padding:8px 14px;border-bottom:1px solid #eee;font-size:13px;"><a href="tel:' . esc_attr( preg_replace( '/[^\d+]/', '', $value ) ) . '" style="color:#3498db;">' . esc_html( $value ) . '</a></td>';
                    } else {
                        $html .= '<td style="padding:8px 14px;border-bottom:1px solid #eee;font-size:13px;color:#333;">' . esc_html( $value ) . '</td>';
                    }

                    $html .= '</tr>';
                    $alt = ! $alt;
                }
            }
        } else {
            foreach ( $data as $key => $value ) {
                if ( is_array( $value ) ) $value = implode( ', ', $value );
                if ( $value === '' ) continue;
                $html .= '<tr>';
                $html .= '<td style="padding:8px 14px;border-bottom:1px solid #eee;font-weight:600;width:40%;font-size:13px;">' . esc_html( ucwords( str_replace( '_', ' ', $key ) ) ) . '</td>';
                $html .= '<td style="padding:8px 14px;border-bottom:1px solid #eee;font-size:13px;">' . esc_html( $value ) . '</td>';
                $html .= '</tr>';
            }
        }

        return $html;
    }

    /**
     * Process merge tags
     */
    private function process_merge_tags( $text, $form, $data, $entry_id ) {
        $text = str_replace( '{form_title}', $form->title ?? '', $text );
        $text = str_replace( '{entry_id}', $entry_id, $text );
        foreach ( $this->get_field_merge_tags( $data ) as $tag => $value ) {
            $text = str_replace( $tag, $value, $text );
        }
        return $text;
    }

    /**
     * Get merge tags from field data
     */
    private function get_field_merge_tags( $data ) {
        $tags = array();
        foreach ( $data as $key => $value ) {
            if ( is_array( $value ) ) $value = implode( ', ', $value );
            $tags[ '{' . $key . '}' ] = $value;
        }

        if ( ! empty( $data['full_name'] ) ) {
            $parts = explode( ' ', $data['full_name'], 2 );
            $tags['{first_name}'] = $tags['{first_name}'] ?? ( $parts[0] ?? '' );
            $tags['{last_name}']  = $tags['{last_name}'] ?? ( $parts[1] ?? '' );
        }

        return $tags;
    }

    /**
     * Get available merge tags for display
     */
    public static function get_available_merge_tags() {
        return array(
            '{full_name}'      => 'Full Name',
            '{first_name}'     => 'First Name',
            '{last_name}'      => 'Last Name',
            '{email}'          => 'Email Address',
            '{phone}'          => 'Phone Number',
            '{city}'           => 'City',
            '{state}'          => 'State',
            '{zip_code}'       => 'ZIP Code',
            '{form_title}'     => 'Form Title',
            '{entry_id}'       => 'Entry ID',
            '{date}'           => 'Date (full)',
            '{date_short}'     => 'Date (short)',
            '{site_name}'      => 'Site Name',
            '{site_url}'       => 'Site URL',
            '{admin_url}'      => 'Admin Entry URL',
            '{company_name}'   => 'Company Name',
            '{company_phone}'  => 'Company Phone',
            '{fields_table}'   => 'All Fields Table (admin only)',
            '{contact_bar}'    => 'Contact Buttons (admin only)',
            '{location}'       => 'City, State, ZIP',
        );
    }

    /**
     * Log email to custom database table
     */
    private function log_email( $entry_id, $type, $to, $subject, $sent, $body = '' ) {
        global $wpdb;
        $table = $wpdb->prefix . 'iqw_email_log';

        $wpdb->insert( $table, array(
            'entry_id'   => absint( $entry_id ),
            'type'       => sanitize_text_field( $type ),
            'recipient'  => sanitize_text_field( $to ),
            'subject'    => sanitize_text_field( $subject ),
            'sent'       => $sent ? 1 : 0,
        ), array( '%d', '%s', '%s', '%s', '%d' ) );
    }

    /**
     * Get email log for a specific entry
     */
    public static function get_entry_email_log( $entry_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'iqw_email_log';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE entry_id = %d ORDER BY created_at DESC",
            $entry_id
        ) );
    }

    /**
     * Get all email logs (for admin log page)
     */
    public static function get_all_email_logs( $limit = 100 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'iqw_email_log';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d",
            $limit
        ) );
    }

    /**
     * Clear all email logs
     */
    public static function clear_email_log() {
        global $wpdb;
        $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}iqw_email_log" );
    }

    // ================================================================
    // TEMPLATES
    // ================================================================

    /**
     * Admin email template - EverQuote/QuoteWizard inspired
     */
    private function get_admin_template() {
        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f0f2f5;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f2f5;padding:20px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.08);">

<!-- Header Banner -->
<tr><td style="background:linear-gradient(135deg,#1a5276,#2980b9);padding:24px 30px;text-align:center;">
<h1 style="margin:0;color:#fff;font-size:22px;font-weight:700;">You have a new {form_type} Insurance Lead</h1>
<p style="margin:6px 0 0;color:rgba(255,255,255,0.85);font-size:14px;">in {location}</p>
</td></tr>

<!-- Quick Contact Bar -->
<tr><td style="background:#f8f9fa;padding:16px 30px;text-align:center;border-bottom:1px solid #eee;">
{contact_bar}
</td></tr>

<!-- Lead Information -->
<tr><td style="padding:0;">
<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">

<!-- Lead Meta -->
<tr><td colspan="2" style="background:#34495e;color:#fff;padding:8px 14px;font-size:12px;">
<strong>Lead ID:</strong> #{entry_id} &nbsp;&nbsp;|&nbsp;&nbsp;
<strong>Date:</strong> {date_short} &nbsp;&nbsp;|&nbsp;&nbsp;
<strong>Form:</strong> {form_title}
</td></tr>

{fields_table}

</table>
</td></tr>

<!-- Action Button -->
<tr><td style="padding:24px 30px;text-align:center;border-top:2px solid #2980b9;">
<a href="{admin_url}" style="display:inline-block;padding:12px 32px;background:#2980b9;color:#fff;text-decoration:none;border-radius:6px;font-weight:bold;font-size:14px;">View Full Entry in Admin →</a>
</td></tr>

<!-- Footer -->
<tr><td style="padding:16px 30px;text-align:center;font-size:11px;color:#999;border-top:1px solid #f0f0f0;">
Entry #{entry_id} | {company_name} | <a href="{site_url}" style="color:#999;">{site_url}</a><br>
This is an automated notification from Insurance Quote Wizard.
</td></tr>

</table>
</td></tr>
</table>
</body></html>';
    }

    /**
     * Customer email template - professional, warm
     */
    private function get_customer_template() {
        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f0f2f5;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f2f5;padding:20px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.08);">

<!-- Header -->
<tr><td style="background:linear-gradient(135deg,#27ae60,#2ecc71);padding:32px 30px;text-align:center;">
<h1 style="margin:0;color:#fff;font-size:26px;font-weight:700;">Thank You, {first_name}! ✓</h1>
<p style="margin:8px 0 0;color:rgba(255,255,255,0.9);font-size:15px;">Your quote request has been received</p>
</td></tr>

<!-- Body -->
<tr><td style="padding:32px 30px;">
<p style="font-size:16px;color:#333;line-height:1.6;margin:0 0 16px;">We received your <strong>{form_title}</strong> request and our team is already reviewing it.</p>

<div style="background:#f8f9fa;border-radius:8px;padding:20px;margin:24px 0;border-left:4px solid #27ae60;">
<p style="margin:0;font-size:15px;color:#333;font-weight:600;">📋 What happens next?</p>
<ol style="margin:12px 0 0;padding-left:20px;color:#555;font-size:14px;line-height:1.8;">
<li>Our licensed agent reviews your information</li>
<li>We compare rates from multiple carriers</li>
<li>You receive personalized quotes within <strong>24 hours</strong></li>
</ol>
</div>

<p style="font-size:14px;color:#666;line-height:1.6;">If you have any questions in the meantime, please don\'t hesitate to reach out.</p>
</td></tr>

<!-- Contact Info -->
<tr><td style="padding:0 30px 32px;">
<div style="background:#f8f9fa;border-radius:8px;padding:20px;text-align:center;">
<p style="margin:0 0 4px;font-size:16px;font-weight:700;color:#333;">{company_name}</p>
<p style="margin:0;font-size:14px;color:#666;">{company_phone}</p>
<p style="margin:8px 0 0;"><a href="{site_url}" style="color:#27ae60;font-size:14px;">{site_url}</a></p>
</div>
</td></tr>

<!-- Footer -->
<tr><td style="padding:16px 30px;text-align:center;font-size:11px;color:#999;border-top:1px solid #f0f0f0;">
This email was sent because you submitted a quote request on our website.<br>
Reference #{entry_id} | {company_name}
</td></tr>

</table>
</td></tr>
</table>
</body></html>';
    }
}
