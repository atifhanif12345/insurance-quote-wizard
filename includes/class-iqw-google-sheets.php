<?php
/**
 * Google Sheets Integration
 * Pushes form submissions to a Google Sheet via Sheets API v4.
 * Uses Service Account or API Key approach.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class IQW_Google_Sheets {

    /**
     * Push entry data to Google Sheet (non-blocking via WP-Cron)
     */
    public static function push( $entry_id, $form, $data ) {
        $settings = $form->email_settings ?? array();
        $sheet_config = $settings['google_sheets'] ?? array();

        if ( empty( $sheet_config['enabled'] ) || empty( $sheet_config['spreadsheet_id'] ) || empty( $sheet_config['api_key'] ) ) {
            return;
        }

        // Schedule the actual API call to run in the background
        // This prevents blocking the form submission response
        wp_schedule_single_event( time(), 'iqw_push_to_sheets', array( $entry_id, $form->id ) );

        // Trigger the cron immediately (spawn-cron style)
        spawn_cron();
    }

    /**
     * Actually send data to Google Sheets (runs in background via cron)
     */
    public static function do_push( $entry_id, $form_id ) {
        $form = IQW_Form_Builder::get_form( $form_id );
        if ( ! $form ) return;

        $entry = IQW_Submission::get_entry( $entry_id );
        if ( ! $entry ) return;

        $data = $entry->data;
        $settings = $form->email_settings ?? array();
        $sheet_config = $settings['google_sheets'] ?? array();

        $spreadsheet_id = sanitize_text_field( $sheet_config['spreadsheet_id'] );
        $sheet_name = sanitize_text_field( $sheet_config['sheet_name'] ?? 'Sheet1' );
        $api_key = sanitize_text_field( $sheet_config['api_key'] );

        // Build row
        $row = array(
            $entry_id,
            current_time( 'm/d/Y H:i:s' ),
            $form->title,
        );

        $columns = $sheet_config['columns'] ?? array();
        if ( ! empty( $columns ) ) {
            foreach ( $columns as $col ) {
                $val = $data[ $col ] ?? '';
                if ( is_array( $val ) ) $val = implode( ', ', $val );
                $row[] = $val;
            }
        } else {
            foreach ( $data as $key => $value ) {
                if ( is_array( $value ) ) $value = implode( ', ', $value );
                $row[] = $value;
            }
        }

        // Append row via Sheets API v4
        $range = urlencode( $sheet_name . '!A:Z' );
        $url = 'https://sheets.googleapis.com/v4/spreadsheets/' . $spreadsheet_id .
               '/values/' . $range . ':append?valueInputOption=USER_ENTERED&key=' . $api_key;

        $response = wp_remote_post( $url, array(
            'body'    => wp_json_encode( array( 'values' => array( $row ) ) ),
            'headers' => array( 'Content-Type' => 'application/json' ),
            'timeout' => 30,
        ) );

        $success = false;
        if ( ! is_wp_error( $response ) ) {
            $code = wp_remote_retrieve_response_code( $response );
            $success = ( $code >= 200 && $code < 300 );
        }

        update_option( 'iqw_gsheets_last_sync', array(
            'entry_id' => $entry_id,
            'success'  => $success,
            'time'     => current_time( 'mysql' ),
            'error'    => $success ? '' : ( is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_body( $response ) ),
        ), false );
    }

    /**
     * Push header row (column names) to sheet
     */
    public static function push_headers( $form_id ) {
        $form = IQW_Form_Builder::get_form( $form_id );
        if ( ! $form ) return new WP_Error( 'not_found', 'Form not found.' );

        $settings = $form->email_settings ?? array();
        $sheet_config = $settings['google_sheets'] ?? array();

        if ( empty( $sheet_config['spreadsheet_id'] ) || empty( $sheet_config['api_key'] ) ) {
            return new WP_Error( 'not_configured', 'Google Sheets not configured.' );
        }

        $spreadsheet_id = sanitize_text_field( $sheet_config['spreadsheet_id'] );
        $sheet_name = sanitize_text_field( $sheet_config['sheet_name'] ?? 'Sheet1' );
        $api_key = sanitize_text_field( $sheet_config['api_key'] );

        // Build header row
        $headers = array( 'Entry ID', 'Date', 'Form Title' );
        $config = $form->config;
        if ( ! empty( $config['steps'] ) ) {
            foreach ( $config['steps'] as $step ) {
                foreach ( $step['fields'] ?? array() as $field ) {
                    if ( in_array( $field['type'] ?? '', array( 'heading', 'paragraph' ), true ) ) continue;
                    $headers[] = $field['label'] ?? $field['key'] ?? '';
                }
            }
        }

        $range = urlencode( $sheet_name . '!A1:Z1' );
        $url = 'https://sheets.googleapis.com/v4/spreadsheets/' . $spreadsheet_id .
               '/values/' . $range . '?valueInputOption=RAW&key=' . $api_key;

        $response = wp_remote_request( $url, array(
            'method'  => 'PUT',
            'body'    => wp_json_encode( array( 'values' => array( $headers ) ) ),
            'headers' => array( 'Content-Type' => 'application/json' ),
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) ) return $response;

        $code = wp_remote_retrieve_response_code( $response );
        return ( $code >= 200 && $code < 300 );
    }

    /**
     * AJAX handler for testing connection
     */
    public static function ajax_test_connection() {
        check_ajax_referer( 'iqw_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $spreadsheet_id = sanitize_text_field( $_POST['spreadsheet_id'] ?? '' );
        $api_key = sanitize_text_field( $_POST['api_key'] ?? '' );
        $sheet_name = sanitize_text_field( $_POST['sheet_name'] ?? 'Sheet1' );

        if ( ! $spreadsheet_id || ! $api_key ) {
            wp_send_json_error( 'Missing spreadsheet ID or API key.' );
        }

        $range = urlencode( $sheet_name . '!A1:A1' );
        $url = 'https://sheets.googleapis.com/v4/spreadsheets/' . $spreadsheet_id .
               '/values/' . $range . '?key=' . $api_key;

        $response = wp_remote_get( $url, array( 'timeout' => 10 ) );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( 'Connection failed: ' . $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code >= 200 && $code < 300 ) {
            wp_send_json_success( 'Connected successfully!' );
        } else {
            $body = json_decode( wp_remote_retrieve_body( $response ), true ) ?: array();
            $msg = $body['error']['message'] ?? 'API error (HTTP ' . $code . ')';
            wp_send_json_error( $msg );
        }
    }
}
