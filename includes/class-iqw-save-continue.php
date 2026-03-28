<?php
/**
 * Save & Continue Later
 * Allows users to save partial form progress and resume via unique URL.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class IQW_Save_Continue {

    private static $table = 'iqw_drafts';
    private static $expiry_days = 7;

    /**
     * Save draft (AJAX handler)
     */
    public static function save_draft() {
        if ( ! check_ajax_referer( 'iqw_submit_nonce', 'iqw_nonce', false ) ) {
            wp_send_json_error( 'Security check failed.', 403 );
        }

        $form_id  = absint( $_POST['form_id'] ?? 0 );
        $fields   = $_POST['fields'] ?? array();
        $step     = absint( $_POST['current_step'] ?? 0 );
        $token    = sanitize_text_field( $_POST['draft_token'] ?? '' );
        $page_url = esc_url_raw( $_POST['page_url'] ?? '' );

        if ( ! $form_id || empty( $fields ) ) {
            wp_send_json_error( 'Missing data.' );
        }

        // Sanitize fields
        $clean = array();
        foreach ( $fields as $key => $value ) {
            $key = sanitize_key( $key );
            $clean[ $key ] = is_array( $value )
                ? array_map( 'sanitize_text_field', $value )
                : sanitize_text_field( $value );
        }

        global $wpdb;
        $table = $wpdb->prefix . self::$table;
        $expires = date( 'Y-m-d H:i:s', strtotime( '+' . self::$expiry_days . ' days' ) );

        if ( $token ) {
            // Update existing draft
            $existing = $wpdb->get_row( $wpdb->prepare(
                "SELECT id FROM {$table} WHERE token = %s AND form_id = %d AND expires_at > NOW()",
                $token, $form_id
            ) );

            if ( $existing ) {
                $wpdb->update( $table, array(
                    'data'         => wp_json_encode( $clean ),
                    'current_step' => $step,
                    'expires_at'   => $expires,
                ), array( 'id' => $existing->id ), array( '%s', '%d', '%s' ), array( '%d' ) );

                wp_send_json_success( array(
                    'token'      => $token,
                    'resume_url' => self::get_resume_url( $token, $form_id, $page_url ),
                    'message'    => __( 'Progress saved! You can resume anytime.', 'iqw' ),
                ) );
                return;
            }
        }

        // Create new draft
        $token = wp_generate_password( 32, false );
        $ip_keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );
        $ip = '0.0.0.0';
        foreach ( $ip_keys as $k ) {
            if ( ! empty( $_SERVER[ $k ] ) ) {
                $ip = sanitize_text_field( explode( ',', $_SERVER[ $k ] )[0] );
                break;
            }
        }

        $wpdb->insert( $table, array(
            'form_id'      => $form_id,
            'token'        => $token,
            'data'         => wp_json_encode( $clean ),
            'current_step' => $step,
            'ip_address'   => $ip,
            'expires_at'   => $expires,
        ), array( '%d', '%s', '%s', '%d', '%s', '%s' ) );

        wp_send_json_success( array(
            'token'      => $token,
            'resume_url' => self::get_resume_url( $token, $form_id, $page_url ),
            'message'    => __( 'Progress saved! You can resume anytime.', 'iqw' ),
        ) );
    }

    /**
     * Load draft data
     */
    public static function load_draft() {
        $token = sanitize_text_field( $_GET['iqw_resume'] ?? $_POST['draft_token'] ?? '' );
        if ( ! $token ) wp_send_json_error( 'No token.' );

        global $wpdb;
        $table = $wpdb->prefix . self::$table;

        $draft = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE token = %s AND expires_at > NOW()",
            $token
        ) );

        if ( ! $draft ) {
            wp_send_json_error( 'Draft expired or not found.' );
        }

        wp_send_json_success( array(
            'form_id'      => (int) $draft->form_id,
            'data'         => json_decode( $draft->data, true ) ?: array(),
            'current_step' => (int) $draft->current_step,
            'token'        => $draft->token,
        ) );
    }

    /**
     * Get resume URL
     * Includes form_id so popup forms can auto-open on the correct popup.
     */
    public static function get_resume_url( $token, $form_id = 0, $page_url = '' ) {
        // Prefer the page_url sent by the frontend (most reliable)
        if ( ! $page_url ) {
            $page_url = wp_get_referer();
        }
        if ( ! $page_url ) {
            $page_url = home_url( '/' );
        }
        // Strip any existing resume params
        $page_url = remove_query_arg( array( 'iqw_resume', 'iqw_form' ), $page_url );
        return add_query_arg( array(
            'iqw_resume' => $token,
            'iqw_form'   => $form_id ? absint( $form_id ) : 0,
        ), $page_url );
    }

    /**
     * Cleanup expired drafts (called on init occasionally)
     */
    public static function cleanup() {
        global $wpdb;
        $table = $wpdb->prefix . self::$table;
        $wpdb->query( "DELETE FROM {$table} WHERE expires_at < NOW()" );
    }

    /**
     * Delete draft after successful submission
     */
    public static function delete_draft( $token ) {
        if ( ! $token ) return;
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . self::$table, array( 'token' => $token ), array( '%s' ) );
    }
}
