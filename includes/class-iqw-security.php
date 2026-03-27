<?php
/**
 * Security Handler
 * Nonce verification, honeypot, rate limiting, reCAPTCHA.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class IQW_Security {

    /**
     * Validate submission security checks
     */
    public function validate_submission( $post_data ) {
        // Honeypot check
        if ( get_option( 'iqw_honeypot_enabled', true ) ) {
            if ( ! empty( $post_data['iqw_website_url'] ) ) {
                return new WP_Error( 'spam', __( 'Spam detected.', 'iqw' ) );
            }
        }

        // Time-based check
        if ( get_option( 'iqw_time_check_enabled', true ) ) {
            $min_seconds = (int) get_option( 'iqw_time_check_seconds', 3 );
            $form_loaded = absint( $post_data['iqw_form_loaded'] ?? 0 );

            if ( $form_loaded > 0 ) {
                $elapsed = time() - $form_loaded;
                if ( $elapsed < $min_seconds ) {
                    return new WP_Error( 'spam', __( 'Please take your time filling out the form.', 'iqw' ) );
                }
            }
        }

        // Rate limiting
        if ( get_option( 'iqw_rate_limit_enabled', true ) ) {
            $rate_check = $this->check_rate_limit();
            if ( is_wp_error( $rate_check ) ) {
                return $rate_check;
            }
        }

        // reCAPTCHA check
        if ( get_option( 'iqw_recaptcha_enabled', false ) ) {
            $token = $post_data['iqw_recaptcha_token'] ?? '';
            $recaptcha_check = $this->verify_recaptcha( $token );
            if ( is_wp_error( $recaptcha_check ) ) {
                return $recaptcha_check;
            }
        }

        return true;
    }

    /**
     * Check rate limiting by IP using atomic DB increment to prevent race conditions.
     * Uses INSERT ... ON DUPLICATE KEY UPDATE so the counter increment is atomic at the
     * MySQL level — no two concurrent requests can both read 0 and both write 1.
     */
    private function check_rate_limit() {
        global $wpdb;

        // Skip rate limiting for logged-in administrators (for testing)
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }

        $ip     = $this->get_client_ip();
        $max    = (int) get_option( 'iqw_rate_limit_max', 20 );
        $window = (int) get_option( 'iqw_rate_limit_window', 3600 );
        $expiry = time() + $window;

        $option_name  = '_transient_iqw_rate_' . md5( $ip );
        $timeout_name = '_transient_timeout_iqw_rate_' . md5( $ip );

        // Atomically insert or increment the counter in a single query
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$wpdb->options} (option_name, option_value, autoload)
                 VALUES (%s, '1', 'no')
                 ON DUPLICATE KEY UPDATE option_value = CAST(option_value AS UNSIGNED) + 1",
                $option_name
            )
        );

        // Set expiry only if not already set (INSERT IGNORE keeps existing expiry)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->query(
            $wpdb->prepare(
                "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload)
                 VALUES (%s, %d, 'no')",
                $timeout_name,
                $expiry
            )
        );

        // Read the current count after atomic increment
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
                $option_name
            )
        );

        // Enforce expiry: if window has passed, reset and allow
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $stored_expiry = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
                $timeout_name
            )
        );

        if ( $stored_expiry > 0 && time() > $stored_expiry ) {
            // Window expired — clean up and allow
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->delete( $wpdb->options, array( 'option_name' => $option_name ), array( '%s' ) );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->delete( $wpdb->options, array( 'option_name' => $timeout_name ), array( '%s' ) );
            return true;
        }

        if ( $count > $max ) {
            return new WP_Error(
                'rate_limited',
                __( 'Too many submissions. Please try again later.', 'iqw' )
            );
        }

        return true;
    }

    /**
     * Verify Google reCAPTCHA v3 token
     */
    private function verify_recaptcha( $token ) {
        if ( empty( $token ) ) {
            return new WP_Error( 'recaptcha', __( 'reCAPTCHA verification failed.', 'iqw' ) );
        }

        $secret = get_option( 'iqw_recaptcha_secret', '' );
        if ( empty( $secret ) ) {
            return true; // Skip if not configured
        }

        $response = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', array(
            'body' => array(
                'secret'   => $secret,
                'response' => $token,
                'remoteip' => $this->get_client_ip(),
            ),
            'timeout' => 10,
        ) );

        if ( is_wp_error( $response ) ) {
            return true; // Fail open if reCAPTCHA service is down
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true ) ?: array();

        if ( empty( $body['success'] ) || ( $body['score'] ?? 1 ) < 0.3 ) {
            return new WP_Error( 'recaptcha', __( 'reCAPTCHA verification failed.', 'iqw' ) );
        }

        return true;
    }

    /**
     * Get client IP
     */
    private function get_client_ip() {
        $keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' );
        foreach ( $keys as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip = explode( ',', $_SERVER[ $key ] );
                $ip = trim( $ip[0] );
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }

    /**
     * Generate honeypot field HTML
     */
    public static function honeypot_field() {
        if ( ! get_option( 'iqw_honeypot_enabled', true ) ) return '';

        return '<div style="position:absolute;left:-9999px;top:-9999px;opacity:0;height:0;overflow:hidden;" aria-hidden="true">
            <label for="iqw_website_url">Leave this empty</label>
            <input type="text" name="iqw_website_url" id="iqw_website_url" value="" tabindex="-1" autocomplete="off">
        </div>';
    }

    /**
     * Generate timestamp field HTML - uses JS to set value client-side
     * so it works correctly even with full-page caching
     */
    public static function timestamp_field() {
        if ( ! get_option( 'iqw_time_check_enabled', true ) ) return '';

        return '<input type="hidden" name="iqw_form_loaded" value="0" class="iqw-timestamp-field">' .
               '<script>document.querySelectorAll(".iqw-timestamp-field").forEach(function(el){el.value=Math.floor(Date.now()/1000);});</script>';
    }
}
