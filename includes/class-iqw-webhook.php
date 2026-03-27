<?php
/**
 * Webhook Handler
 * Sends form data to external URLs (Zapier, CRM, custom endpoints).
 * Uses WP-Cron for non-blocking background delivery.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class IQW_Webhook {

    /**
     * Schedule webhooks for background delivery (non-blocking)
     */
    public static function fire( $entry_id, $form, $data ) {
        $hooks = $form->email_settings['webhooks'] ?? array();
        if ( empty( $hooks ) ) return;

        $payload = array(
            'event'      => 'new_submission',
            'entry_id'   => $entry_id,
            'form_id'    => $form->id,
            'form_title' => $form->title,
            'form_type'  => $form->type,
            'fields'     => $data,
            'meta'       => array(
                'ip'         => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
                'submitted'  => current_time( 'c' ),
                'site_url'   => home_url(),
                'admin_url'  => admin_url( 'admin.php?page=iqw-entries&action=view&id=' . $entry_id ),
            ),
        );

        $payload = apply_filters( 'iqw_webhook_payload', $payload, $entry_id, $form );

        // Filter active hooks with matching conditions
        $active_hooks = array();
        foreach ( $hooks as $hook ) {
            if ( empty( $hook['url'] ) || empty( $hook['enabled'] ) ) continue;

            if ( ! empty( $hook['condition_field'] ) && ! empty( $hook['condition_value'] ) ) {
                $field_val = $data[ $hook['condition_field'] ] ?? '';
                if ( is_array( $field_val ) ) $field_val = implode( ',', $field_val );
                if ( strtolower( (string) $field_val ) !== strtolower( (string) ( $hook['condition_value'] ?? '' ) ) ) {
                    continue;
                }
            }
            $active_hooks[] = $hook;
        }

        if ( empty( $active_hooks ) ) return;

        // Store job in transient, schedule background delivery
        $job_key = 'iqw_wh_' . $entry_id . '_' . wp_rand( 1000, 9999 );
        set_transient( $job_key, array(
            'hooks'    => $active_hooks,
            'payload'  => $payload,
            'entry_id' => $entry_id,
        ), 3600 ); // 1 hour TTL — safe for delayed cron

        wp_schedule_single_event( time(), 'iqw_deliver_webhooks', array( $job_key ) );
        spawn_cron();
    }

    /**
     * Deliver webhooks (runs in background via WP-Cron)
     */
    public static function deliver( $job_key ) {
        $job = get_transient( $job_key );
        if ( ! $job ) return;
        delete_transient( $job_key );

        $payload  = $job['payload'] ?? array();
        $entry_id = $job['entry_id'] ?? 0;

        foreach ( $job['hooks'] as $hook ) {
            $args = array(
                'body'      => wp_json_encode( $payload ),
                'headers'   => array( 'Content-Type' => 'application/json' ),
                'timeout'   => 15,
                'blocking'  => true,
                'sslverify' => true,
            );

            if ( ! empty( $hook['headers'] ) && is_array( $hook['headers'] ) ) {
                foreach ( $hook['headers'] as $h ) {
                    if ( ! empty( $h['key'] ) && ! empty( $h['value'] ) ) {
                        $args['headers'][ sanitize_text_field( $h['key'] ) ] = sanitize_text_field( $h['value'] );
                    }
                }
            }

            $url = esc_url_raw( $hook['url'] );
            $response = wp_remote_post( $url, $args );

            $success = false;
            $error_msg = '';
            if ( is_wp_error( $response ) ) {
                $error_msg = $response->get_error_message();
            } else {
                $code = wp_remote_retrieve_response_code( $response );
                $success = ( $code >= 200 && $code < 300 );
                if ( ! $success ) $error_msg = 'HTTP ' . $code;
            }

            // Retry once on failure
            if ( ! $success ) {
                sleep( 2 );
                $retry = wp_remote_post( $url, $args );
                if ( ! is_wp_error( $retry ) ) {
                    $code = wp_remote_retrieve_response_code( $retry );
                    if ( $code >= 200 && $code < 300 ) {
                        $success = true;
                        $error_msg = '';
                    }
                }
            }

            self::log( $entry_id, $url, $success, $error_msg );
        }
    }

    /**
     * Log webhook delivery
     */
    private static function log( $entry_id, $url, $success, $error = '' ) {
        $log = get_option( 'iqw_webhook_log', array() );
        $log[] = array(
            'entry_id' => $entry_id,
            'url'      => $url,
            'success'  => $success,
            'error'    => $error,
            'time'     => current_time( 'mysql' ),
        );
        if ( count( $log ) > 200 ) {
            $log = array_slice( $log, -200 );
        }
        update_option( 'iqw_webhook_log', $log, false );
    }
}
