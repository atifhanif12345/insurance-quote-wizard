<?php
/**
 * Public Controller
 * Handles frontend asset loading and form rendering.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class IQW_Public {

    public function init() {
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
        add_action( 'template_redirect', array( $this, 'handle_preview' ) );
    }

    /**
     * Handle ?iqw_preview=1&form_id=X — renders form in minimal page (admin only)
     */
    public function handle_preview() {
        if ( empty( $_GET['iqw_preview'] ) || empty( $_GET['form_id'] ) ) return;
        if ( ! current_user_can( 'manage_options' ) ) return;

        $form_id = absint( $_GET['form_id'] );
        $shortcode = new IQW_Shortcode();
        $form_html = $shortcode->render( array( 'id' => $form_id ) );

        // Minimal page with WP styles
        ?><!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo( 'charset' ); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <meta name="robots" content="noindex, nofollow">
            <title>Form Preview</title>
            <?php wp_head(); ?>
            <style>body{margin:0;padding:24px;background:#f5f5f5;font-family:-apple-system,BlinkMacSystemFont,sans-serif;}</style>
        </head>
        <body>
            <div style="max-width:800px;margin:0 auto;">
                <?php echo $form_html; ?>
            </div>
            <?php wp_footer(); ?>
        </body>
        </html><?php
        exit;
    }

    /**
     * Register (not enqueue) assets - only loaded when shortcode is present
     */
    public function register_assets() {
        $suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

        wp_register_style(
            'iqw-wizard',
            IQW_PLUGIN_URL . 'public/css/iqw-wizard' . $suffix . '.css',
            array(),
            IQW_VERSION
        );

        wp_register_style(
            'iqw-themes',
            IQW_PLUGIN_URL . 'public/css/iqw-themes.css',
            array( 'iqw-wizard' ),
            IQW_VERSION
        );

        wp_register_style(
            'iqw-google-fonts',
            'https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=DM+Sans:wght@400;500;600;700&family=Nunito:wght@400;600;700;800&family=Poppins:wght@400;600;700;800&family=Libre+Baskerville:ital,wght@0,400;0,700;1,400&display=swap',
            array(),
            null
        );

        wp_register_script(
            'iqw-wizard',
            IQW_PLUGIN_URL . 'public/js/iqw-wizard' . $suffix . '.js',
            array(),
            IQW_VERSION,
            true
        );
    }

    /**
     * Render a form (called by shortcode/block)
     */
    public static function render_form( $form_id, $atts = array() ) {
        $form = IQW_Form_Builder::get_form( $form_id );

        if ( ! $form || $form->status !== 'active' ) {
            if ( current_user_can( 'manage_options' ) ) {
                return '<div class="iqw-error">Form #' . esc_html( $form_id ) . ' not found or inactive.</div>';
            }
            return '';
        }

        // Form Scheduling check
        $settings = $form->config['settings'] ?? array();
        if ( ! empty( $settings['schedule_enabled'] ) && ! current_user_can( 'manage_options' ) ) {
            $now = current_time( 'timestamp' );
            $is_closed = false;
            $closed_reason = '';

            if ( ! empty( $settings['schedule_start'] ) ) {
                $start = strtotime( $settings['schedule_start'] );
                if ( $start && $now < $start ) {
                    $is_closed = true;
                    $closed_reason = sprintf(
                        __( 'This form opens on %s.', 'iqw' ),
                        date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $start )
                    );
                }
            }

            if ( ! empty( $settings['schedule_end'] ) ) {
                $end = strtotime( $settings['schedule_end'] );
                if ( $end && $now > $end ) {
                    $is_closed = true;
                    $closed_reason = __( 'This form is no longer accepting submissions.', 'iqw' );
                }
            }

            if ( ! empty( $settings['schedule_max_submissions'] ) ) {
                $max = intval( $settings['schedule_max_submissions'] );
                if ( $max > 0 ) {
                    $counts = IQW_Submission::get_entry_counts( $form_id );
                    $total = ( $counts['total'] ?? 0 ) - ( $counts['trash'] ?? 0 );
                    if ( $total >= $max ) {
                        $is_closed = true;
                        $closed_reason = __( 'This form has reached its submission limit.', 'iqw' );
                    }
                }
            }

            if ( $is_closed ) {
                $msg = ! empty( $settings['schedule_closed_message'] )
                    ? $settings['schedule_closed_message']
                    : $closed_reason;
                return '<div class="iqw-form-closed">' .
                    '<div class="iqw-form-closed-icon">🔒</div>' .
                    '<h3>' . esc_html__( 'Form Closed', 'iqw' ) . '</h3>' .
                    '<p>' . esc_html( $msg ) . '</p>' .
                    '</div>';
            }
        }

        // Enqueue assets
        wp_enqueue_style( 'iqw-wizard' );
        wp_enqueue_script( 'iqw-wizard' );

        // Load theme CSS + fonts if theme is set
        $theme = $form->config['settings']['theme'] ?? 'default';
        if ( $theme && $theme !== 'default' ) {
            wp_enqueue_style( 'iqw-google-fonts' );
            wp_enqueue_style( 'iqw-themes' );
        }

        // Load reCAPTCHA v3 script if enabled
        $recaptcha_enabled = get_option( 'iqw_recaptcha_enabled', false );
        $recaptcha_site_key = get_option( 'iqw_recaptcha_site_key', '' );
        if ( $recaptcha_enabled && $recaptcha_site_key ) {
            wp_enqueue_script(
                'google-recaptcha',
                'https://www.google.com/recaptcha/api.js?render=' . esc_attr( $recaptcha_site_key ),
                array(),
                null,
                true
            );
        }

        // Google Places API for address autocomplete
        $places_key = get_option( 'iqw_google_places_key', '' );
        if ( $places_key ) {
            wp_enqueue_script(
                'google-places',
                'https://maps.googleapis.com/maps/api/js?key=' . esc_attr( $places_key ) . '&libraries=places',
                array(),
                null,
                true
            );
        }

        // Stripe.js for payment fields
        $stripe_enabled = get_option( 'iqw_stripe_enabled' );
        $stripe_pk = '';
        if ( $stripe_enabled ) {
            $mode = get_option( 'iqw_stripe_mode', 'test' );
            $stripe_pk = get_option( 'iqw_stripe_pk_' . $mode, '' );
            if ( $stripe_pk ) {
                wp_enqueue_script( 'stripe-js', 'https://js.stripe.com/v3/', array(), null, true );
            }
        }

        // Pass form data to JS
        wp_localize_script( 'iqw-wizard', 'iqwForm_' . $form->id, array(
            'formId'    => $form->id,
            'config'    => $form->config,
            'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
            'restUrl'   => rest_url( 'iqw/v1/' ),
            'nonce'     => wp_create_nonce( 'iqw_submit_nonce' ),
            'primaryColor' => get_option( 'iqw_primary_color', '#4CAF50' ),
            'recaptchaEnabled' => (bool) $recaptcha_enabled,
            'recaptchaSiteKey' => $recaptcha_site_key,
            'stripeKey' => $stripe_pk,
            'geolocationEnabled' => (bool) get_option( 'iqw_geolocation_enabled' ) && ! get_option( 'iqw_gdpr_disable_ip' ),
            'strings'   => array(
                'next'      => __( 'Next', 'iqw' ),
                'back'      => __( 'Back', 'iqw' ),
                'submit'    => __( 'Get My Quotes', 'iqw' ),
                'loading'   => __( 'Submitting...', 'iqw' ),
                'success'   => __( 'Thank you! We will contact you shortly.', 'iqw' ),
                'error'     => __( 'Something went wrong. Please try again.', 'iqw' ),
                'required'  => __( 'This field is required.', 'iqw' ),
                'invalidEmail' => __( 'Please enter a valid email.', 'iqw' ),
                'invalidPhone' => __( 'Please enter a valid phone number.', 'iqw' ),
            ),
        ) );

        // Build HTML
        ob_start();

        // Strip referrer if resume token is in URL (prevents token leaking to external sites)
        if ( ! empty( $_GET['iqw_resume'] ) ) {
            echo '<meta name="referrer" content="no-referrer">';
        }

        // Inject custom CSS for this form
        $custom_css = $form->config['settings']['custom_css'] ?? '';
        if ( $custom_css ) {
            // Strip HTML tags, then remove CSS data-exfiltration vectors
            $safe_css = wp_strip_all_tags( $custom_css );
            $safe_css = preg_replace( '/url\s*\([^)]*\)/i', 'url(#removed)', $safe_css ); // block url() data exfil
            $safe_css = preg_replace( '/@import\b[^;]*;?/i', '', $safe_css );             // block @import
            $safe_css = preg_replace( '/expression\s*\(/i', '', $safe_css );               // block IE expression()
            echo '<style id="iqw-custom-css-' . esc_attr( $form->id ) . '">' . $safe_css . '</style>';
        }
        ?>
        <?php
            $theme_class    = ( $theme && $theme !== 'default' ) ? ' iqw-theme-' . esc_attr( $theme ) : '';
            // wp_validate_redirect() ensures redirect stays on same domain — prevents open redirect
            $redirect_override = ! empty( $atts['redirect_url'] )
                ? wp_validate_redirect( esc_url_raw( $atts['redirect_url'] ), '' )
                : '';
        ?>
        <div class="iqw-wizard-container<?php echo $theme_class; ?>" id="iqw-wizard-<?php echo esc_attr( $form->id ); ?>" data-form-id="<?php echo esc_attr( $form->id ); ?>"<?php echo $redirect_override ? ' data-redirect="' . $redirect_override . '"' : ''; ?><?php echo $theme_class ? ' style="font-family: var(--iqw-font, inherit);"' : ''; ?>>
            <!-- Progress Bar -->
            <div class="iqw-progress-bar" id="iqw-progress-<?php echo esc_attr( $form->id ); ?>"></div>

            <!-- Motivational text -->
            <div class="iqw-motivation">
                <span class="iqw-motivation-icon">⏳</span>
                <span class="iqw-motivation-text"><?php _e( '3 minutes until deals. Let\'s go!', 'iqw' ); ?></span>
            </div>

            <!-- Form -->
            <form class="iqw-wizard-form" id="iqw-form-<?php echo esc_attr( $form->id ); ?>" novalidate>
                <input type="hidden" name="action" value="iqw_submit_form">
                <input type="hidden" name="form_id" value="<?php echo esc_attr( $form->id ); ?>">
                <input type="hidden" name="iqw_nonce" value="<?php echo wp_create_nonce( 'iqw_submit_nonce' ); ?>">
                <?php echo IQW_Security::timestamp_field(); ?>
                <?php echo IQW_Security::honeypot_field(); ?>

                <!-- Steps rendered by JS -->
                <div class="iqw-steps-wrapper" id="iqw-steps-<?php echo esc_attr( $form->id ); ?>"></div>
            </form>

            <!-- Navigation (outside form to prevent enter-submit) -->
            <div class="iqw-nav" id="iqw-nav-<?php echo esc_attr( $form->id ); ?>">
                <a href="#" class="iqw-btn-back" id="iqw-back-<?php echo esc_attr( $form->id ); ?>" style="display:none;">
                    ← <?php _e( 'Back', 'iqw' ); ?>
                </a>
                <div class="iqw-nav-right">
                    <?php $enable_save = $settings['enable_save_later'] ?? true; ?>
                    <?php if ( $enable_save ) : ?>
                    <a href="#" class="iqw-save-later" id="iqw-save-later-<?php echo esc_attr( $form->id ); ?>" title="<?php _e( 'Save progress and finish later', 'iqw' ); ?>">
                        💾 <?php _e( 'Save & Finish Later', 'iqw' ); ?>
                    </a>
                    <?php endif; ?>
                    <button type="button" class="iqw-btn-next" id="iqw-next-<?php echo esc_attr( $form->id ); ?>">
                        <?php _e( 'Next', 'iqw' ); ?>
                    </button>
                </div>
            </div>

            <!-- Save & Continue Success -->
            <div class="iqw-save-later-msg" id="iqw-save-later-msg-<?php echo esc_attr( $form->id ); ?>" style="display:none;"></div>

            <!-- Success Message -->
            <div class="iqw-success" id="iqw-success-<?php echo esc_attr( $form->id ); ?>" style="display:none;">
                <div class="iqw-success-icon">✓</div>
                <h2><?php _e( 'Thank You!', 'iqw' ); ?></h2>
                <p class="iqw-success-text"><?php _e( 'Your quote request has been submitted. One of our agents will contact you shortly.', 'iqw' ); ?></p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
