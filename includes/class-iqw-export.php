<?php
/**
 * Export Handler
 * CSV export for form entries.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class IQW_Export {

    /**
     * Handle export request
     */
    public function handle_export( $request = null ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized', 'iqw' ) );
        }

        // Verify nonce for AJAX requests (REST API has its own auth)
        if ( ! $request && ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'iqw_export_nonce' ) ) {
            wp_die( __( 'Security check failed.', 'iqw' ) );
        }

        $form_id    = absint( $_GET['form_id'] ?? ( $request ? $request->get_param( 'form_id' ) : 0 ) );
        $status     = sanitize_text_field( $_GET['status'] ?? ( $request ? $request->get_param( 'status' ) : '' ) );
        $date_from  = sanitize_text_field( $_GET['date_from'] ?? ( $request ? $request->get_param( 'date_from' ) : '' ) );
        $date_to    = sanitize_text_field( $_GET['date_to'] ?? ( $request ? $request->get_param( 'date_to' ) : '' ) );

        $entries = IQW_Submission::get_entries( array(
            'form_id' => $form_id,
            'status'  => $status,
            'limit'   => 10000,
            'offset'  => 0,
        ) );

        // Date filtering
        if ( $date_from || $date_to ) {
            $entries = array_filter( $entries, function( $entry ) use ( $date_from, $date_to ) {
                $date = strtotime( $entry->created_at );
                if ( $date_from && $date < strtotime( $date_from ) ) return false;
                if ( $date_to && $date > strtotime( $date_to . ' 23:59:59' ) ) return false;
                return true;
            } );
        }

        if ( empty( $entries ) ) {
            if ( $request ) {
                return new WP_Error( 'no_data', 'No entries to export.' );
            }
            wp_die( __( 'No entries to export.', 'iqw' ) );
        }

        // Collect all unique field keys
        $all_keys = array();
        foreach ( $entries as $entry ) {
            $data = is_string( $entry->data ) ? ( json_decode( $entry->data, true ) ?: array() ) : ( is_array( $entry->data ) ? $entry->data : array() );
            if ( is_array( $data ) ) {
                $all_keys = array_merge( $all_keys, array_keys( $data ) );
            }
        }
        $all_keys = array_unique( $all_keys );

        // Build CSV
        $filename = 'iqw-entries-' . date( 'Y-m-d-His' ) . '.csv';

        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $output = fopen( 'php://output', 'w' );

        // UTF-8 BOM for Excel
        fputs( $output, "\xEF\xBB\xBF" );

        // Headers
        $headers = array_merge(
            array( 'Entry ID', 'Form ID', 'Status', 'IP Address', 'Submitted At' ),
            array_map( function( $key ) { return ucwords( str_replace( '_', ' ', $key ) ); }, $all_keys )
        );
        fputcsv( $output, $headers );

        // Rows
        foreach ( $entries as $entry ) {
            $data = is_string( $entry->data ) ? ( json_decode( $entry->data, true ) ?: array() ) : ( is_array( $entry->data ) ? $entry->data : array() );

            $row = array(
                $entry->id,
                $entry->form_id,
                $entry->status,
                $entry->ip_address,
                $entry->created_at,
            );

            foreach ( $all_keys as $key ) {
                $value = $data[ $key ] ?? '';
                if ( is_array( $value ) ) $value = implode( ', ', $value );
                $row[] = $value;
            }

            fputcsv( $output, $row );
        }

        fclose( $output );
        exit;
    }
}
