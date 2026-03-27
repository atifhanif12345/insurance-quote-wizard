<?php
/**
 * Admin Settings Handler
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class IQW_Admin_Settings {

    /**
     * Save settings from POST
     */
    public function save_settings( $post_data ) {
        $settings = array(
            'iqw_admin_email'           => sanitize_email( $post_data['iqw_admin_email'] ?? '' ),
            'iqw_company_name'          => sanitize_text_field( $post_data['iqw_company_name'] ?? '' ),
            'iqw_company_phone'         => sanitize_text_field( $post_data['iqw_company_phone'] ?? '' ),
            'iqw_primary_color'         => sanitize_hex_color( $post_data['iqw_primary_color'] ?? '#4CAF50' ),
            'iqw_recaptcha_enabled'     => ! empty( $post_data['iqw_recaptcha_enabled'] ),
            'iqw_recaptcha_site_key'    => sanitize_text_field( $post_data['iqw_recaptcha_site_key'] ?? '' ),
            'iqw_recaptcha_secret'      => sanitize_text_field( $post_data['iqw_recaptcha_secret'] ?? '' ),
            'iqw_honeypot_enabled'      => ! empty( $post_data['iqw_honeypot_enabled'] ),
            'iqw_time_check_enabled'    => ! empty( $post_data['iqw_time_check_enabled'] ),
            'iqw_time_check_seconds'    => absint( $post_data['iqw_time_check_seconds'] ?? 3 ),
            'iqw_rate_limit_enabled'    => ! empty( $post_data['iqw_rate_limit_enabled'] ),
            'iqw_rate_limit_max'        => absint( $post_data['iqw_rate_limit_max'] ?? 5 ),
            'iqw_rate_limit_window'     => absint( $post_data['iqw_rate_limit_window'] ?? 3600 ),
            'iqw_delete_data_uninstall' => ! empty( $post_data['iqw_delete_data_uninstall'] ),
            // Google Places
            'iqw_google_places_key'     => sanitize_text_field( $post_data['iqw_google_places_key'] ?? '' ),
            // Stripe
            'iqw_stripe_enabled'        => ! empty( $post_data['iqw_stripe_enabled'] ),
            'iqw_stripe_mode'           => sanitize_text_field( $post_data['iqw_stripe_mode'] ?? 'test' ),
            'iqw_stripe_pk_test'        => sanitize_text_field( $post_data['iqw_stripe_pk_test'] ?? '' ),
            'iqw_stripe_sk_test'        => sanitize_text_field( $post_data['iqw_stripe_sk_test'] ?? '' ),
            'iqw_stripe_pk_live'        => sanitize_text_field( $post_data['iqw_stripe_pk_live'] ?? '' ),
            'iqw_stripe_sk_live'        => sanitize_text_field( $post_data['iqw_stripe_sk_live'] ?? '' ),
            'iqw_stripe_currency'       => sanitize_text_field( $post_data['iqw_stripe_currency'] ?? 'usd' ),
            // Mailchimp
            'iqw_mailchimp_enabled'     => ! empty( $post_data['iqw_mailchimp_enabled'] ),
            'iqw_mailchimp_api_key'     => sanitize_text_field( $post_data['iqw_mailchimp_api_key'] ?? '' ),
            'iqw_mailchimp_list_id'     => sanitize_text_field( $post_data['iqw_mailchimp_list_id'] ?? '' ),
            'iqw_mailchimp_double_optin' => ! empty( $post_data['iqw_mailchimp_double_optin'] ),
            // GDPR
            'iqw_geolocation_enabled'   => ! empty( $post_data['iqw_geolocation_enabled'] ),
            'iqw_pdf_attach_to_email'   => ! empty( $post_data['iqw_pdf_attach_to_email'] ),
            'iqw_gdpr_disable_ip'       => ! empty( $post_data['iqw_gdpr_disable_ip'] ),
            'iqw_gdpr_disable_ua'       => ! empty( $post_data['iqw_gdpr_disable_ua'] ),
            'iqw_gdpr_disable_referrer' => ! empty( $post_data['iqw_gdpr_disable_referrer'] ),
            'iqw_gdpr_auto_delete_days' => absint( $post_data['iqw_gdpr_auto_delete_days'] ?? 0 ),
            // SMS
            'iqw_sms_enabled'           => ! empty( $post_data['iqw_sms_enabled'] ),
            'iqw_twilio_sid'            => sanitize_text_field( $post_data['iqw_twilio_sid'] ?? '' ),
            'iqw_twilio_token'          => sanitize_text_field( $post_data['iqw_twilio_token'] ?? '' ),
            'iqw_twilio_from'           => sanitize_text_field( $post_data['iqw_twilio_from'] ?? '' ),
            'iqw_sms_notify_number'     => sanitize_text_field( $post_data['iqw_sms_notify_number'] ?? '' ),
            'iqw_sms_extra_numbers'     => sanitize_text_field( $post_data['iqw_sms_extra_numbers'] ?? '' ),
            'iqw_sms_template'          => sanitize_textarea_field( $post_data['iqw_sms_template'] ?? '' ),
            // Form Abandonment
            'iqw_abandonment_recovery_enabled' => ! empty( $post_data['iqw_abandonment_recovery_enabled'] ),
            'iqw_abandonment_delay_minutes'    => max( 5, absint( $post_data['iqw_abandonment_delay_minutes'] ?? 60 ) ),
            'iqw_abandonment_email_subject'    => sanitize_text_field( $post_data['iqw_abandonment_email_subject'] ?? '' ),
            'iqw_abandonment_email_body'       => wp_kses_post( wp_unslash( $post_data['iqw_abandonment_email_body'] ?? '' ) ),
        );

        foreach ( $settings as $key => $value ) {
            update_option( $key, $value );
        }
    }
}
