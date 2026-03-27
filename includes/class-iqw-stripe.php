<?php
/**
 * Stripe Payment Handler
 * Creates Stripe Checkout sessions and verifies payments.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class IQW_Stripe {

    /**
     * Get Stripe keys based on mode
     */
    private static function get_keys() {
        $mode = get_option( 'iqw_stripe_mode', 'test' );
        return array(
            'pk' => get_option( 'iqw_stripe_pk_' . $mode, '' ),
            'sk' => get_option( 'iqw_stripe_sk_' . $mode, '' ),
        );
    }

    /**
     * Check if Stripe is enabled and configured
     */
    public static function is_enabled() {
        if ( ! get_option( 'iqw_stripe_enabled' ) ) return false;
        $keys = self::get_keys();
        return ! empty( $keys['pk'] ) && ! empty( $keys['sk'] );
    }

    /**
     * Create a Payment Intent via Stripe API
     */
    public static function create_payment_intent( $amount, $currency = null, $metadata = array() ) {
        $keys = self::get_keys();
        if ( empty( $keys['sk'] ) ) {
            return new WP_Error( 'no_key', 'Stripe secret key not configured.' );
        }

        $currency = $currency ?: get_option( 'iqw_stripe_currency', 'usd' );
        $amount_cents = absint( round( floatval( $amount ) * 100 ) );

        if ( $amount_cents < 50 ) {
            return new WP_Error( 'min_amount', 'Minimum payment is $0.50.' );
        }

        $response = wp_remote_post( 'https://api.stripe.com/v1/payment_intents', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $keys['sk'],
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ),
            'body' => array(
                'amount'               => $amount_cents,
                'currency'             => strtolower( $currency ),
                'automatic_payment_methods[enabled]' => 'true',
                'metadata[source]'     => 'iqw_plugin',
                'metadata[form_id]'    => $metadata['form_id'] ?? '',
                'metadata[entry_id]'   => $metadata['entry_id'] ?? '',
            ),
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) ) return $response;

        $body = json_decode( wp_remote_retrieve_body( $response ), true ) ?: array();
        $code = wp_remote_retrieve_response_code( $response );

        if ( $code >= 200 && $code < 300 && ! empty( $body['client_secret'] ) ) {
            return $body;
        }

        return new WP_Error( 'stripe_error', $body['error']['message'] ?? 'Stripe API error.' );
    }

    /**
     * AJAX: Create payment intent from frontend
     */
    public static function ajax_create_intent() {
        if ( ! check_ajax_referer( 'iqw_submit_nonce', 'iqw_nonce', false ) ) {
            wp_send_json_error( 'Security check failed.', 403 );
        }

        $form_id = absint( $_POST['form_id'] ?? 0 );
        $client_amount = floatval( $_POST['amount'] ?? 0 );

        if ( ! $form_id ) wp_send_json_error( 'Missing form ID.' );

        // Server-side amount validation against form config
        $form = IQW_Form_Builder::get_form( $form_id );
        if ( ! $form ) wp_send_json_error( 'Form not found.' );

        $payment_field = self::find_payment_field( $form->config );
        if ( ! $payment_field ) wp_send_json_error( 'No payment field in this form.' );

        $configured_amount = floatval( $payment_field['amount'] ?? 0 );

        if ( $configured_amount > 0 ) {
            // Fixed amount — ignore client value, use config
            $amount = $configured_amount;
        } else {
            // Variable amount — use client value but enforce min
            $amount = $client_amount;
            $min = floatval( $payment_field['min_amount'] ?? 0.50 );
            $max = floatval( $payment_field['max_amount'] ?? 99999 );
            if ( $amount < $min ) wp_send_json_error( sprintf( 'Minimum amount is $%.2f.', $min ) );
            if ( $amount > $max ) wp_send_json_error( sprintf( 'Maximum amount is $%.2f.', $max ) );
        }

        $result = self::create_payment_intent( $amount, null, array( 'form_id' => $form_id ) );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( array(
            'client_secret' => $result['client_secret'],
            'payment_intent_id' => $result['id'],
        ) );
    }

    /**
     * Verify a payment intent status
     */
    public static function verify_payment( $payment_intent_id ) {
        $keys = self::get_keys();
        if ( empty( $keys['sk'] ) ) return false;

        $response = wp_remote_get( 'https://api.stripe.com/v1/payment_intents/' . $payment_intent_id, array(
            'headers' => array( 'Authorization' => 'Bearer ' . $keys['sk'] ),
            'timeout' => 10,
        ) );

        if ( is_wp_error( $response ) ) return false;

        $body = json_decode( wp_remote_retrieve_body( $response ), true ) ?: array();
        return ( $body['status'] ?? '' ) === 'succeeded';
    }

    /**
     * Find payment field in form config
     */
    public static function find_payment_field( $config ) {
        if ( empty( $config['steps'] ) ) return null;
        foreach ( $config['steps'] as $step ) {
            foreach ( $step['fields'] ?? array() as $field ) {
                if ( ( $field['type'] ?? '' ) === 'payment' ) return $field;
            }
        }
        return null;
    }
}
