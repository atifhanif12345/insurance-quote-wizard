<?php
/**
 * GDPR & Privacy Tools
 * Integrates with WordPress Privacy Export/Erase + auto-delete old entries.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class IQW_GDPR {

    /**
     * Register WordPress Privacy hooks
     */
    public static function init() {
        add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
        add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
    }

    /**
     * Register data exporter for WP Privacy Tools
     */
    public static function register_exporter( $exporters ) {
        $exporters['iqw-form-entries'] = array(
            'exporter_friendly_name' => __( 'Insurance Quote Wizard Form Entries', 'iqw' ),
            'callback'               => array( __CLASS__, 'export_personal_data' ),
        );
        return $exporters;
    }

    /**
     * Register data eraser for WP Privacy Tools
     */
    public static function register_eraser( $erasers ) {
        $erasers['iqw-form-entries'] = array(
            'eraser_friendly_name' => __( 'Insurance Quote Wizard Form Entries', 'iqw' ),
            'callback'             => array( __CLASS__, 'erase_personal_data' ),
        );
        return $erasers;
    }

    /**
     * Export personal data for a given email address
     */
    public static function export_personal_data( $email, $page = 1 ) {
        global $wpdb;

        $entries_table = $wpdb->prefix . IQW_TABLE_ENTRIES;
        $meta_table    = $wpdb->prefix . IQW_TABLE_ENTRY_META;
        $per_page      = 50;
        $offset        = ( $page - 1 ) * $per_page;

        // Find entries by email in entry_meta
        $entry_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT entry_id FROM {$meta_table} WHERE meta_key = 'email' AND meta_value = %s LIMIT %d OFFSET %d",
            $email, $per_page, $offset
        ) );

        $export_items = array();

        foreach ( $entry_ids as $entry_id ) {
            $entry = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$entries_table} WHERE id = %d", $entry_id
            ) );

            if ( ! $entry ) continue;

            $form = IQW_Form_Builder::get_form( $entry->form_id );
            $data = json_decode( $entry->data, true ) ?: array();

            $item_data = array();
            $item_data[] = array( 'name' => __( 'Form', 'iqw' ), 'value' => $form ? $form->title : 'Form #' . $entry->form_id );
            $item_data[] = array( 'name' => __( 'Entry ID', 'iqw' ), 'value' => $entry->id );
            $item_data[] = array( 'name' => __( 'Submitted', 'iqw' ), 'value' => $entry->created_at );
            $item_data[] = array( 'name' => __( 'IP Address', 'iqw' ), 'value' => $entry->ip_address ?: 'Not stored' );

            foreach ( $data as $key => $value ) {
                if ( strpos( $key, '_' ) === 0 ) continue; // Skip internal keys
                $item_data[] = array(
                    'name'  => $key,
                    'value' => is_array( $value ) ? implode( ', ', $value ) : $value,
                );
            }

            $export_items[] = array(
                'group_id'          => 'iqw-entries',
                'group_label'       => __( 'Insurance Quote Form Entries', 'iqw' ),
                'group_description' => __( 'Data submitted through insurance quote forms.', 'iqw' ),
                'item_id'           => 'iqw-entry-' . $entry->id,
                'data'              => $item_data,
            );
        }

        return array(
            'data' => $export_items,
            'done' => count( $entry_ids ) < $per_page,
        );
    }

    /**
     * Erase personal data for a given email address
     */
    public static function erase_personal_data( $email, $page = 1 ) {
        global $wpdb;

        $entries_table = $wpdb->prefix . IQW_TABLE_ENTRIES;
        $meta_table    = $wpdb->prefix . IQW_TABLE_ENTRY_META;
        $notes_table   = $wpdb->prefix . IQW_TABLE_ENTRY_NOTES;
        $per_page      = 50;
        $offset        = ( $page - 1 ) * $per_page;

        $entry_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT entry_id FROM {$meta_table} WHERE meta_key = 'email' AND meta_value = %s LIMIT %d OFFSET %d",
            $email, $per_page, $offset
        ) );

        $removed = 0;

        foreach ( $entry_ids as $entry_id ) {
            // Delete entry + meta + notes
            $wpdb->delete( $meta_table, array( 'entry_id' => $entry_id ), array( '%d' ) );
            $wpdb->delete( $notes_table, array( 'entry_id' => $entry_id ), array( '%d' ) );
            $wpdb->delete( $entries_table, array( 'id' => $entry_id ), array( '%d' ) );
            $removed++;
        }

        return array(
            'items_removed'  => $removed,
            'items_retained' => false,
            'messages'       => array(),
            'done'           => count( $entry_ids ) < $per_page,
        );
    }

    /**
     * Anonymize a single entry (replace PII with redacted values)
     */
    public static function anonymize_entry( $entry_id ) {
        global $wpdb;

        $entries_table = $wpdb->prefix . IQW_TABLE_ENTRIES;
        $meta_table    = $wpdb->prefix . IQW_TABLE_ENTRY_META;

        $entry = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$entries_table} WHERE id = %d", $entry_id
        ) );

        if ( ! $entry ) return false;

        $data = json_decode( $entry->data, true ) ?: array();

        // PII field keys to anonymize
        $pii_keys = array(
            'full_name', 'first_name', 'last_name', 'name',
            'email', 'phone', 'address', 'street_address',
            'city', 'zip', 'zip_code', 'ssn', 'dob', 'date_of_birth',
        );

        // Also anonymize address sub-fields
        foreach ( array_keys( $data ) as $key ) {
            if ( preg_match( '/_street$|_city$|_state$|_zip$/', $key ) ) {
                $pii_keys[] = $key;
            }
        }

        foreach ( $pii_keys as $pii_key ) {
            if ( isset( $data[ $pii_key ] ) ) {
                $data[ $pii_key ] = '[REDACTED]';
            }
        }

        // Update entry
        $wpdb->update( $entries_table, array(
            'data'       => wp_json_encode( $data ),
            'ip_address' => '0.0.0.0',
            'user_agent' => '',
        ), array( 'id' => $entry_id ), array( '%s', '%s', '%s' ), array( '%d' ) );

        // Update meta
        foreach ( $pii_keys as $pii_key ) {
            $wpdb->update( $meta_table, array(
                'meta_value' => '[REDACTED]',
            ), array(
                'entry_id' => $entry_id,
                'meta_key' => $pii_key,
            ), array( '%s' ), array( '%d', '%s' ) );
        }

        return true;
    }

    /**
     * Auto-delete old entries (called from daily cron)
     */
    public static function auto_delete_old_entries() {
        $days = absint( get_option( 'iqw_gdpr_auto_delete_days', 0 ) );
        if ( $days <= 0 ) return;

        global $wpdb;
        $entries_table = $wpdb->prefix . IQW_TABLE_ENTRIES;
        $meta_table    = $wpdb->prefix . IQW_TABLE_ENTRY_META;
        $notes_table   = $wpdb->prefix . IQW_TABLE_ENTRY_NOTES;

        $cutoff = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

        // Get old entry IDs
        $old_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT id FROM {$entries_table} WHERE created_at < %s", $cutoff
        ) );

        if ( empty( $old_ids ) ) return;

        $ids_placeholder = implode( ',', array_map( 'absint', $old_ids ) );

        $wpdb->query( "DELETE FROM {$meta_table} WHERE entry_id IN ({$ids_placeholder})" );
        $wpdb->query( "DELETE FROM {$notes_table} WHERE entry_id IN ({$ids_placeholder})" );
        $wpdb->query( "DELETE FROM {$entries_table} WHERE id IN ({$ids_placeholder})" );
    }

    /**
     * AJAX: Anonymize entry
     */
    public static function ajax_anonymize_entry() {
        check_ajax_referer( 'iqw_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $entry_id = absint( $_POST['entry_id'] ?? 0 );
        if ( ! $entry_id ) wp_send_json_error( 'Missing entry ID.' );

        $result = self::anonymize_entry( $entry_id );
        if ( $result ) {
            wp_send_json_success( 'Entry anonymized.' );
        }
        wp_send_json_error( 'Entry not found.' );
    }
}
