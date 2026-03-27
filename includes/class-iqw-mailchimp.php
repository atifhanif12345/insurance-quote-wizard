<?php
/**
 * Mailchimp Integration
 * Subscribe form submitters to a Mailchimp audience on successful submission.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class IQW_Mailchimp {

    /**
     * Subscribe user after form submission
     */
    public static function subscribe( $entry_id, $form, $clean_data ) {
        $api_key  = get_option( 'iqw_mailchimp_api_key', '' );
        $list_id  = get_option( 'iqw_mailchimp_list_id', '' );
        $enabled  = get_option( 'iqw_mailchimp_enabled', false );

        if ( ! $enabled || ! $api_key || ! $list_id ) return;

        // Check per-form override
        $form_mc = $form->email_settings['mailchimp_enabled'] ?? null;
        if ( $form_mc === false ) return; // Explicitly disabled for this form

        // Find email field
        $email = $clean_data['email'] ?? '';
        if ( ! $email || ! is_email( $email ) ) return;

        // Build subscriber data
        $merge_fields = array();
        $fname = $clean_data['first_name'] ?? '';
        $lname = $clean_data['last_name'] ?? '';

        if ( ! $fname && ! empty( $clean_data['full_name'] ) ) {
            $parts = explode( ' ', $clean_data['full_name'], 2 );
            $fname = $parts[0] ?? '';
            $lname = $parts[1] ?? '';
        }

        if ( $fname ) $merge_fields['FNAME'] = $fname;
        if ( $lname ) $merge_fields['LNAME'] = $lname;

        // Phone
        if ( ! empty( $clean_data['phone'] ) ) {
            $merge_fields['PHONE'] = $clean_data['phone'];
        }

        // Tags from form type
        $tags = array();
        if ( ! empty( $form->type ) ) {
            $tags[] = ucfirst( $form->type ) . ' Insurance Lead';
        }
        $tags[] = 'IQW Form: ' . ( $form->title ?? 'Unknown' );

        // Get datacenter from API key
        $dc = 'us1';
        if ( strpos( $api_key, '-' ) !== false ) {
            $dc = explode( '-', $api_key )[1];
        }

        $url = 'https://' . $dc . '.api.mailchimp.com/3.0/lists/' . $list_id . '/members';

        $body = array(
            'email_address' => $email,
            'status'        => get_option( 'iqw_mailchimp_double_optin', false ) ? 'pending' : 'subscribed',
            'merge_fields'  => (object) $merge_fields,
        );

        if ( ! empty( $tags ) ) {
            $body['tags'] = $tags;
        }

        $response = wp_remote_post( $url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( 'anystring:' . $api_key ),
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( $body ),
            'timeout' => 10,
        ) );

        // Log result
        $code = is_wp_error( $response ) ? 0 : wp_remote_retrieve_response_code( $response );

        if ( $code === 400 ) {
            // Already subscribed — try PATCH to update
            $member_hash = md5( strtolower( $email ) );
            wp_remote_request( $url . '/' . $member_hash, array(
                'method'  => 'PATCH',
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode( 'anystring:' . $api_key ),
                    'Content-Type'  => 'application/json',
                ),
                'body'    => wp_json_encode( array(
                    'merge_fields' => (object) $merge_fields,
                    'tags'         => $tags ?? array(),
                ) ),
                'timeout' => 10,
            ) );
        }
    }

    /**
     * AJAX: Test Mailchimp connection
     */
    public static function ajax_test_connection() {
        check_ajax_referer( 'iqw_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $api_key = sanitize_text_field( $_POST['api_key'] ?? '' );
        if ( ! $api_key ) wp_send_json_error( 'API key required.' );

        $dc = 'us1';
        if ( strpos( $api_key, '-' ) !== false ) {
            $dc = explode( '-', $api_key )[1];
        }

        $response = wp_remote_get( 'https://' . $dc . '.api.mailchimp.com/3.0/lists?count=100', array(
            'headers' => array( 'Authorization' => 'Basic ' . base64_encode( 'anystring:' . $api_key ) ),
            'timeout' => 10,
        ) );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            wp_send_json_error( 'Invalid API key. Status: ' . $code );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true ) ?: array();
        $lists = array();
        foreach ( $body['lists'] ?? array() as $list ) {
            $lists[] = array(
                'id'   => $list['id'],
                'name' => $list['name'],
                'count' => $list['stats']['member_count'] ?? 0,
            );
        }

        wp_send_json_success( array( 'lists' => $lists ) );
    }
}
