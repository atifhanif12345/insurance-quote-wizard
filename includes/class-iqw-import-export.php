<?php
/**
 * Entry Import/Export
 * Import entries from CSV, export individual entries as JSON.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class IQW_Import_Export {

    /**
     * Export single entry as JSON
     */
    public static function ajax_export_entry() {
        check_ajax_referer( 'iqw_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $entry_id = absint( $_POST['entry_id'] ?? 0 );
        if ( ! $entry_id ) wp_send_json_error( 'Missing entry ID.' );

        $entry = IQW_Submission::get_entry( $entry_id );
        if ( ! $entry ) wp_send_json_error( 'Entry not found.' );

        $form = IQW_Form_Builder::get_form( $entry->form_id );

        $export = array(
            'plugin'     => 'Insurance Quote Wizard',
            'version'    => IQW_VERSION,
            'exported'   => current_time( 'c' ),
            'entry'      => array(
                'id'         => $entry->id,
                'form_id'    => $entry->form_id,
                'form_title' => $form ? $form->title : '',
                'status'     => $entry->status,
                'data'       => $entry->data,
                'ip_address' => $entry->ip_address,
                'user_agent' => $entry->user_agent ?? '',
                'created_at' => $entry->created_at,
            ),
        );

        wp_send_json_success( $export );
    }

    /**
     * Export multiple entries as JSON
     */
    public static function ajax_export_entries_json() {
        check_ajax_referer( 'iqw_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $entry_ids = array_map( 'absint', $_POST['entry_ids'] ?? array() );
        if ( empty( $entry_ids ) ) wp_send_json_error( 'No entries selected.' );

        $entries = array();
        foreach ( $entry_ids as $id ) {
            $entry = IQW_Submission::get_entry( $id );
            if ( ! $entry ) continue;
            $form = IQW_Form_Builder::get_form( $entry->form_id );
            $entries[] = array(
                'id'         => $entry->id,
                'form_id'    => $entry->form_id,
                'form_title' => $form ? $form->title : '',
                'status'     => $entry->status,
                'data'       => $entry->data,
                'ip_address' => $entry->ip_address,
                'created_at' => $entry->created_at,
            );
        }

        wp_send_json_success( array(
            'plugin'   => 'Insurance Quote Wizard',
            'version'  => IQW_VERSION,
            'exported' => current_time( 'c' ),
            'count'    => count( $entries ),
            'entries'  => $entries,
        ) );
    }

    /**
     * Import entries from JSON
     */
    public static function ajax_import_entries() {
        check_ajax_referer( 'iqw_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        if ( ! isset( $_FILES['import_file'] ) || empty( $_FILES['import_file']['tmp_name'] ) ) {
            wp_send_json_error( 'No file uploaded.' );
        }

        $file = $_FILES['import_file'];
        if ( $file['type'] !== 'application/json' && ! preg_match( '/\.json$/i', $file['name'] ) ) {
            wp_send_json_error( 'Please upload a JSON file.' );
        }

        $content = file_get_contents( $file['tmp_name'] );
        $data = json_decode( $content, true ) ?: array();

        if ( ! $data || empty( $data['entries'] ) ) {
            // Try single entry format
            if ( ! empty( $data['entry'] ) ) {
                $data['entries'] = array( $data['entry'] );
            } else {
                wp_send_json_error( 'Invalid JSON format. Expected IQW export file.' );
            }
        }

        global $wpdb;
        $table = $wpdb->prefix . IQW_TABLE_ENTRIES;
        $meta_table = $wpdb->prefix . IQW_TABLE_ENTRY_META;
        $imported = 0;
        $skipped = 0;

        foreach ( $data['entries'] as $entry_data ) {
            $form_id = absint( $entry_data['form_id'] ?? 0 );
            $fields = $entry_data['data'] ?? array();

            if ( ! $form_id || empty( $fields ) ) {
                $skipped++;
                continue;
            }

            // Verify form exists
            $form = IQW_Form_Builder::get_form( $form_id );
            if ( ! $form ) {
                $skipped++;
                continue;
            }

            // Insert entry
            $wpdb->insert( $table, array(
                'form_id'      => $form_id,
                'data'         => wp_json_encode( $fields ),
                'status'       => sanitize_text_field( $entry_data['status'] ?? 'new' ),
                'ip_address'   => sanitize_text_field( $entry_data['ip_address'] ?? 'imported' ),
                'user_agent'   => sanitize_text_field( $entry_data['user_agent'] ?? '' ),
                'referrer_url' => esc_url_raw( $entry_data['referrer_url'] ?? '' ),
                'created_at'   => $entry_data['created_at'] ?? current_time( 'mysql' ),
            ), array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' ) );

            $new_id = $wpdb->insert_id;
            if ( $new_id ) {
                // Insert searchable meta
                foreach ( $fields as $key => $value ) {
                    if ( is_array( $value ) ) $value = implode( ', ', $value );
                    $wpdb->insert( $meta_table, array(
                        'entry_id'   => $new_id,
                        'meta_key'   => sanitize_key( $key ),
                        'meta_value' => sanitize_text_field( $value ),
                    ), array( '%d', '%s', '%s' ) );
                }
                $imported++;
            } else {
                $skipped++;
            }
        }

        wp_send_json_success( array(
            'imported' => $imported,
            'skipped'  => $skipped,
            'message'  => sprintf( '%d entries imported, %d skipped.', $imported, $skipped ),
        ) );
    }
}
