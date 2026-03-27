<?php
/**
 * Geolocation
 * Auto-detect visitor city/state/zip from IP using free API.
 * Results cached in transient to avoid repeated lookups.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class IQW_Geolocation {

    /**
     * Get location data from IP
     * Uses ip-api.com (free, no key needed, 45 req/min)
     */
    public static function get_location( $ip = '' ) {
        if ( ! $ip ) {
            $ip = self::get_client_ip();
        }

        // Skip private/local IPs
        if ( ! $ip || $ip === '127.0.0.1' || $ip === '::1' || strpos( $ip, '192.168.' ) === 0 || strpos( $ip, '10.' ) === 0 ) {
            return null;
        }

        // Check transient cache (1 hour)
        $cache_key = 'iqw_geo_' . md5( $ip );
        $cached = get_transient( $cache_key );
        if ( $cached !== false ) {
            return $cached;
        }

        // Call ip-api.com (free tier, JSON, no key)
        $url = 'http://ip-api.com/json/' . $ip . '?fields=status,country,countryCode,region,regionName,city,zip,lat,lon';

        $response = wp_remote_get( $url, array(
            'timeout' => 5,
            'headers' => array( 'Accept' => 'application/json' ),
        ) );

        if ( is_wp_error( $response ) ) {
            return null;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true ) ?: array();

        if ( ( $body['status'] ?? '' ) !== 'success' ) {
            return null;
        }

        $data = array(
            'city'         => sanitize_text_field( $body['city'] ?? '' ),
            'state'        => sanitize_text_field( $body['regionName'] ?? '' ),
            'state_code'   => sanitize_text_field( $body['region'] ?? '' ),
            'zip'          => sanitize_text_field( $body['zip'] ?? '' ),
            'country'      => sanitize_text_field( $body['country'] ?? '' ),
            'country_code' => sanitize_text_field( $body['countryCode'] ?? '' ),
            'lat'          => floatval( $body['lat'] ?? 0 ),
            'lon'          => floatval( $body['lon'] ?? 0 ),
        );

        // Cache for 1 hour
        set_transient( $cache_key, $data, HOUR_IN_SECONDS );

        return $data;
    }

    /**
     * REST endpoint: get location (called from frontend)
     */
    public static function rest_get_location( $request ) {
        if ( ! get_option( 'iqw_geolocation_enabled' ) ) {
            return new WP_Error( 'disabled', 'Geolocation is disabled.', array( 'status' => 403 ) );
        }

        // Respect GDPR disable IP setting
        if ( get_option( 'iqw_gdpr_disable_ip' ) ) {
            return new WP_Error( 'gdpr', 'IP tracking is disabled.', array( 'status' => 403 ) );
        }

        $data = self::get_location();

        if ( ! $data ) {
            return new WP_Error( 'not_found', 'Could not determine location.', array( 'status' => 404 ) );
        }

        return rest_ensure_response( $data );
    }

    /**
     * Register REST route
     */
    public static function register_routes() {
        register_rest_route( 'iqw/v1', '/geolocation', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'rest_get_location' ),
            'permission_callback' => '__return_true',
        ) );
    }

    /**
     * Get client IP (respects proxies)
     */
    private static function get_client_ip() {
        $headers = array(
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        );
        foreach ( $headers as $header ) {
            if ( ! empty( $_SERVER[ $header ] ) ) {
                $ip = trim( explode( ',', $_SERVER[ $header ] )[0] );
                if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                    return $ip;
                }
            }
        }
        return sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );
    }
}
