<?php
/**
 * SMS Notifications via Twilio
 * Send SMS to agent when new lead comes in.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class IQW_SMS {

    /**
     * Send SMS notification after form submission
     */
    public static function notify( $entry_id, $form, $clean_data ) {
        $enabled = get_option( 'iqw_sms_enabled', false );
        if ( ! $enabled ) return;

        $account_sid = get_option( 'iqw_twilio_sid', '' );
        $auth_token  = get_option( 'iqw_twilio_token', '' );
        $from_number = get_option( 'iqw_twilio_from', '' );
        $to_number   = get_option( 'iqw_sms_notify_number', '' );

        if ( ! $account_sid || ! $auth_token || ! $from_number || ! $to_number ) return;

        // Check per-form override
        $form_sms = $form->email_settings['sms_enabled'] ?? null;
        if ( $form_sms === false ) return;

        // Build SMS body with merge tags
        $template = get_option( 'iqw_sms_template', '' );
        if ( ! $template ) {
            $template = "New {form_title} lead!\n{full_name}\n{phone}\n{email}";
        }

        $body = self::replace_merge_tags( $template, $clean_data, $form, $entry_id );

        // Truncate to SMS limit (1600 chars for Twilio)
        $body = substr( $body, 0, 1600 );

        // Send via Twilio REST API
        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . $account_sid . '/Messages.json';

        $response = wp_remote_post( $url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $account_sid . ':' . $auth_token ),
            ),
            'body' => array(
                'From' => $from_number,
                'To'   => $to_number,
                'Body' => $body,
            ),
            'timeout' => 10,
        ) );

        // Optionally send to multiple numbers
        $extra_numbers = get_option( 'iqw_sms_extra_numbers', '' );
        if ( $extra_numbers ) {
            $numbers = array_filter( array_map( 'trim', explode( ',', $extra_numbers ) ) );
            foreach ( $numbers as $num ) {
                if ( ! $num ) continue;
                wp_remote_post( $url, array(
                    'headers' => array(
                        'Authorization' => 'Basic ' . base64_encode( $account_sid . ':' . $auth_token ),
                    ),
                    'body' => array(
                        'From' => $from_number,
                        'To'   => $num,
                        'Body' => $body,
                    ),
                    'timeout' => 10,
                ) );
            }
        }
    }

    /**
     * Replace merge tags in SMS template
     */
    private static function replace_merge_tags( $template, $data, $form, $entry_id ) {
        $replacements = array(
            '{form_title}' => $form->title ?? '',
            '{entry_id}'   => $entry_id,
            '{date}'       => current_time( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
        );

        // Add all form data as merge tags
        foreach ( $data as $key => $value ) {
            if ( strpos( $key, '_' ) === 0 ) continue;
            $replacements[ '{' . $key . '}' ] = is_array( $value ) ? implode( ', ', $value ) : $value;
        }

        return str_replace( array_keys( $replacements ), array_values( $replacements ), $template );
    }

    /**
     * AJAX: Send test SMS
     */
    public static function ajax_test_sms() {
        check_ajax_referer( 'iqw_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $account_sid = sanitize_text_field( $_POST['sid'] ?? '' );
        $auth_token  = sanitize_text_field( $_POST['token'] ?? '' );
        $from        = sanitize_text_field( $_POST['from'] ?? '' );
        $to          = sanitize_text_field( $_POST['to'] ?? '' );

        if ( ! $account_sid || ! $auth_token || ! $from || ! $to ) {
            wp_send_json_error( 'All fields required.' );
        }

        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . $account_sid . '/Messages.json';

        $response = wp_remote_post( $url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $account_sid . ':' . $auth_token ),
            ),
            'body' => array(
                'From' => $from,
                'To'   => $to,
                'Body' => 'Test SMS from Insurance Quote Wizard plugin. If you received this, SMS notifications are working!',
            ),
            'timeout' => 10,
        ) );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true ) ?: array();

        if ( $code >= 200 && $code < 300 ) {
            wp_send_json_success( 'Test SMS sent! SID: ' . ( $body['sid'] ?? 'unknown' ) );
        } else {
            $msg = $body['message'] ?? ( 'HTTP ' . $code );
            wp_send_json_error( 'Twilio error: ' . $msg );
        }
    }
}
