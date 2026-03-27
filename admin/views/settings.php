<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap iqw-wrap">
    <h1><?php _e( 'Settings', 'iqw' ); ?></h1>

    <form method="post" action="">
        <?php wp_nonce_field( 'iqw_settings_nonce' ); ?>

        <div class="iqw-settings-grid">
            <!-- General -->
            <div class="iqw-card">
                <h2><?php _e( 'General Settings', 'iqw' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="iqw_company_name"><?php _e( 'Company Name', 'iqw' ); ?></label></th>
                        <td><input type="text" id="iqw_company_name" name="iqw_company_name" value="<?php echo esc_attr( get_option( 'iqw_company_name' ) ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="iqw_company_phone"><?php _e( 'Company Phone', 'iqw' ); ?></label></th>
                        <td><input type="text" id="iqw_company_phone" name="iqw_company_phone" value="<?php echo esc_attr( get_option( 'iqw_company_phone' ) ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="iqw_admin_email"><?php _e( 'Notification Email', 'iqw' ); ?></label></th>
                        <td><input type="email" id="iqw_admin_email" name="iqw_admin_email" value="<?php echo esc_attr( get_option( 'iqw_admin_email' ) ); ?>" class="regular-text">
                        <p class="description"><?php _e( 'Where lead notifications are sent.', 'iqw' ); ?></p></td>
                    </tr>
                    <tr>
                        <th><label for="iqw_primary_color"><?php _e( 'Primary Color', 'iqw' ); ?></label></th>
                        <td><input type="color" id="iqw_primary_color" name="iqw_primary_color" value="<?php echo esc_attr( get_option( 'iqw_primary_color', '#4CAF50' ) ); ?>"></td>
                    </tr>
                </table>
            </div>

            <!-- Security -->
            <div class="iqw-card">
                <h2><?php _e( 'Spam Protection', 'iqw' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><?php _e( 'Honeypot', 'iqw' ); ?></th>
                        <td><label><input type="checkbox" name="iqw_honeypot_enabled" value="1" <?php checked( get_option( 'iqw_honeypot_enabled', true ) ); ?>> <?php _e( 'Enable honeypot field (catches bots)', 'iqw' ); ?></label></td>
                    </tr>
                    <tr>
                        <th><?php _e( 'Time Check', 'iqw' ); ?></th>
                        <td>
                            <label><input type="checkbox" name="iqw_time_check_enabled" value="1" <?php checked( get_option( 'iqw_time_check_enabled', true ) ); ?>> <?php _e( 'Enable minimum time check', 'iqw' ); ?></label>
                            <br><input type="number" name="iqw_time_check_seconds" value="<?php echo esc_attr( get_option( 'iqw_time_check_seconds', 3 ) ); ?>" min="1" max="30" style="width:60px;"> <?php _e( 'seconds minimum', 'iqw' ); ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e( 'Rate Limiting', 'iqw' ); ?></th>
                        <td>
                            <label><input type="checkbox" name="iqw_rate_limit_enabled" value="1" <?php checked( get_option( 'iqw_rate_limit_enabled', true ) ); ?>> <?php _e( 'Enable rate limiting', 'iqw' ); ?></label>
                            <br><input type="number" name="iqw_rate_limit_max" value="<?php echo esc_attr( get_option( 'iqw_rate_limit_max', 5 ) ); ?>" min="1" max="100" style="width:60px;"> <?php _e( 'submissions per', 'iqw' ); ?>
                            <input type="number" name="iqw_rate_limit_window" value="<?php echo esc_attr( get_option( 'iqw_rate_limit_window', 3600 ) ); ?>" min="60" max="86400" style="width:80px;"> <?php _e( 'seconds per IP', 'iqw' ); ?>
                        </td>
                    </tr>
                </table>

                <h3><?php _e( 'Google reCAPTCHA v3', 'iqw' ); ?></h3>
                <table class="form-table">
                    <tr>
                        <th><?php _e( 'Enable', 'iqw' ); ?></th>
                        <td><label><input type="checkbox" name="iqw_recaptcha_enabled" value="1" <?php checked( get_option( 'iqw_recaptcha_enabled' ) ); ?>> <?php _e( 'Enable reCAPTCHA v3', 'iqw' ); ?></label></td>
                    </tr>
                    <tr>
                        <th><label for="iqw_recaptcha_site_key"><?php _e( 'Site Key', 'iqw' ); ?></label></th>
                        <td><input type="text" id="iqw_recaptcha_site_key" name="iqw_recaptcha_site_key" value="<?php echo esc_attr( get_option( 'iqw_recaptcha_site_key' ) ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="iqw_recaptcha_secret"><?php _e( 'Secret Key', 'iqw' ); ?></label></th>
                        <td><input type="text" id="iqw_recaptcha_secret" name="iqw_recaptcha_secret" value="<?php echo esc_attr( get_option( 'iqw_recaptcha_secret' ) ); ?>" class="regular-text"></td>
                    </tr>
                </table>
            </div>

            <!-- Google Places / Address Autocomplete -->
            <div class="iqw-card">
                <h2><?php _e( 'Address Autocomplete', 'iqw' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="iqw_google_places_key"><?php _e( 'Google Places API Key', 'iqw' ); ?></label></th>
                        <td><input type="text" id="iqw_google_places_key" name="iqw_google_places_key" value="<?php echo esc_attr( get_option( 'iqw_google_places_key' ) ); ?>" class="regular-text" placeholder="AIzaSy...">
                        <p class="description"><?php _e( 'Enable Places API in Google Cloud Console. Used for address field autocomplete.', 'iqw' ); ?></p></td>
                    </tr>
                </table>
            </div>

            <!-- Stripe -->
            <div class="iqw-card">
                <h2><?php _e( 'Stripe Payments', 'iqw' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="iqw_stripe_enabled"><?php _e( 'Enable', 'iqw' ); ?></label></th>
                        <td><label><input type="checkbox" name="iqw_stripe_enabled" value="1" <?php checked( get_option( 'iqw_stripe_enabled' ) ); ?>> <?php _e( 'Enable Stripe payment collection', 'iqw' ); ?></label></td>
                    </tr>
                    <tr>
                        <th><label for="iqw_stripe_mode"><?php _e( 'Mode', 'iqw' ); ?></label></th>
                        <td><select name="iqw_stripe_mode" id="iqw_stripe_mode">
                            <option value="test" <?php selected( get_option( 'iqw_stripe_mode', 'test' ), 'test' ); ?>><?php _e( 'Test', 'iqw' ); ?></option>
                            <option value="live" <?php selected( get_option( 'iqw_stripe_mode' ), 'live' ); ?>><?php _e( 'Live', 'iqw' ); ?></option>
                        </select></td>
                    </tr>
                    <tr>
                        <th><label for="iqw_stripe_pk_test"><?php _e( 'Test Publishable Key', 'iqw' ); ?></label></th>
                        <td><input type="text" id="iqw_stripe_pk_test" name="iqw_stripe_pk_test" value="<?php echo esc_attr( get_option( 'iqw_stripe_pk_test' ) ); ?>" class="regular-text" placeholder="pk_test_..."></td>
                    </tr>
                    <tr>
                        <th><label for="iqw_stripe_sk_test"><?php _e( 'Test Secret Key', 'iqw' ); ?></label></th>
                        <td><input type="password" id="iqw_stripe_sk_test" name="iqw_stripe_sk_test" value="<?php echo esc_attr( get_option( 'iqw_stripe_sk_test' ) ); ?>" class="regular-text" placeholder="sk_test_..."></td>
                    </tr>
                    <tr>
                        <th><label for="iqw_stripe_pk_live"><?php _e( 'Live Publishable Key', 'iqw' ); ?></label></th>
                        <td><input type="text" id="iqw_stripe_pk_live" name="iqw_stripe_pk_live" value="<?php echo esc_attr( get_option( 'iqw_stripe_pk_live' ) ); ?>" class="regular-text" placeholder="pk_live_..."></td>
                    </tr>
                    <tr>
                        <th><label for="iqw_stripe_sk_live"><?php _e( 'Live Secret Key', 'iqw' ); ?></label></th>
                        <td><input type="password" id="iqw_stripe_sk_live" name="iqw_stripe_sk_live" value="<?php echo esc_attr( get_option( 'iqw_stripe_sk_live' ) ); ?>" class="regular-text" placeholder="sk_live_..."></td>
                    </tr>
                    <tr>
                        <th><label for="iqw_stripe_currency"><?php _e( 'Currency', 'iqw' ); ?></label></th>
                        <td><input type="text" id="iqw_stripe_currency" name="iqw_stripe_currency" value="<?php echo esc_attr( get_option( 'iqw_stripe_currency', 'usd' ) ); ?>" class="small-text" placeholder="usd"></td>
                    </tr>
                </table>
            </div>

            <!-- Mailchimp -->
            <div class="iqw-card">
                <h2><?php _e( 'Mailchimp', 'iqw' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="iqw_mailchimp_enabled"><?php _e( 'Enable', 'iqw' ); ?></label></th>
                        <td><label><input type="checkbox" name="iqw_mailchimp_enabled" value="1" <?php checked( get_option( 'iqw_mailchimp_enabled' ) ); ?>> <?php _e( 'Subscribe leads to Mailchimp on form submission', 'iqw' ); ?></label></td>
                    </tr>
                    <tr>
                        <th><label for="iqw_mailchimp_api_key"><?php _e( 'API Key', 'iqw' ); ?></label></th>
                        <td>
                            <input type="password" id="iqw_mailchimp_api_key" name="iqw_mailchimp_api_key" value="<?php echo esc_attr( get_option( 'iqw_mailchimp_api_key' ) ); ?>" class="regular-text" placeholder="xxxxxxxxxxxxxxxx-us14">
                            <button type="button" class="button button-small" id="iqw-test-mc" style="margin-left:4px;"><?php _e( 'Test & Fetch Lists', 'iqw' ); ?></button>
                            <p class="description"><?php _e( 'Find your API key at Mailchimp → Account → Extras → API Keys.', 'iqw' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="iqw_mailchimp_list_id"><?php _e( 'Audience/List ID', 'iqw' ); ?></label></th>
                        <td>
                            <input type="text" id="iqw_mailchimp_list_id" name="iqw_mailchimp_list_id" value="<?php echo esc_attr( get_option( 'iqw_mailchimp_list_id' ) ); ?>" class="regular-text" placeholder="abc1234567">
                            <div id="iqw-mc-lists" style="margin-top:4px;"></div>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="iqw_mailchimp_double_optin"><?php _e( 'Double Opt-in', 'iqw' ); ?></label></th>
                        <td><label><input type="checkbox" name="iqw_mailchimp_double_optin" value="1" <?php checked( get_option( 'iqw_mailchimp_double_optin' ) ); ?>> <?php _e( 'Require email confirmation before subscribing', 'iqw' ); ?></label></td>
                    </tr>
                </table>
            </div>

            <!-- PayPal -->
            <div class="iqw-card" style="opacity:0.7;">
                <h2><?php _e( 'PayPal Payments', 'iqw' ); ?> <span style="background:#ff9800;color:#fff;font-size:11px;padding:2px 8px;border-radius:3px;vertical-align:middle;">Coming Soon</span></h2>
                <p style="color:#666;font-size:13px;"><?php _e( 'PayPal integration is planned for a future update. Use Stripe for payment collection, or use webhooks to connect to PayPal via Zapier.', 'iqw' ); ?></p>
            </div>

            <!-- Form Abandonment -->
            <div class="iqw-card">
                <h2><?php _e( 'Form Abandonment Recovery', 'iqw' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><?php _e( 'Enable Recovery Emails', 'iqw' ); ?></th>
                        <td><label><input type="checkbox" name="iqw_abandonment_recovery_enabled" value="1" <?php checked( get_option( 'iqw_abandonment_recovery_enabled' ) ); ?>> <?php _e( 'Auto-email users who started but didn\'t finish a form', 'iqw' ); ?></label></td>
                    </tr>
                    <tr>
                        <th><?php _e( 'Delay (minutes)', 'iqw' ); ?></th>
                        <td><input type="number" name="iqw_abandonment_delay_minutes" value="<?php echo esc_attr( get_option( 'iqw_abandonment_delay_minutes', 60 ) ); ?>" min="5" class="small-text">
                        <p class="description"><?php _e( 'Wait this long after last activity before sending recovery email. Minimum 5 minutes.', 'iqw' ); ?></p></td>
                    </tr>
                    <tr>
                        <th><?php _e( 'Email Subject', 'iqw' ); ?></th>
                        <td><input type="text" name="iqw_abandonment_email_subject" value="<?php echo esc_attr( get_option( 'iqw_abandonment_email_subject', 'Continue your {form_title} quote' ) ); ?>" class="regular-text">
                        <p class="description"><?php _e( 'Merge tags: {form_title}, {first_name}, {company_name}', 'iqw' ); ?></p></td>
                    </tr>
                    <tr>
                        <th><?php _e( 'Email Body', 'iqw' ); ?></th>
                        <td><textarea name="iqw_abandonment_email_body" class="large-text" rows="5" placeholder="Leave blank for default template"><?php echo esc_textarea( get_option( 'iqw_abandonment_email_body', '' ) ); ?></textarea>
                        <p class="description"><?php _e( 'HTML allowed. Merge tags: {first_name}, {full_name}, {form_title}, {resume_link}, {resume_url}, {company_name}. Leave blank for built-in template.', 'iqw' ); ?></p></td>
                    </tr>
                </table>
            </div>

            <!-- SMS Notifications (Twilio) -->
            <div class="iqw-card">
                <h2><?php _e( 'SMS Notifications (Twilio)', 'iqw' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><?php _e( 'Enable', 'iqw' ); ?></th>
                        <td><label><input type="checkbox" name="iqw_sms_enabled" value="1" <?php checked( get_option( 'iqw_sms_enabled' ) ); ?>> <?php _e( 'Send SMS when new lead comes in', 'iqw' ); ?></label></td>
                    </tr>
                    <tr>
                        <th><?php _e( 'Twilio Account SID', 'iqw' ); ?></th>
                        <td><input type="text" name="iqw_twilio_sid" id="iqw_twilio_sid" value="<?php echo esc_attr( get_option( 'iqw_twilio_sid' ) ); ?>" class="regular-text" placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"></td>
                    </tr>
                    <tr>
                        <th><?php _e( 'Auth Token', 'iqw' ); ?></th>
                        <td><input type="password" name="iqw_twilio_token" id="iqw_twilio_token" value="<?php echo esc_attr( get_option( 'iqw_twilio_token' ) ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><?php _e( 'From Number', 'iqw' ); ?></th>
                        <td><input type="text" name="iqw_twilio_from" id="iqw_twilio_from" value="<?php echo esc_attr( get_option( 'iqw_twilio_from' ) ); ?>" class="regular-text" placeholder="+15551234567">
                        <p class="description"><?php _e( 'Your Twilio phone number (must include country code).', 'iqw' ); ?></p></td>
                    </tr>
                    <tr>
                        <th><?php _e( 'Notify Number', 'iqw' ); ?></th>
                        <td><input type="text" name="iqw_sms_notify_number" id="iqw_sms_notify_number" value="<?php echo esc_attr( get_option( 'iqw_sms_notify_number' ) ); ?>" class="regular-text" placeholder="+18325551234">
                        <p class="description"><?php _e( 'Agent phone number to receive SMS alerts.', 'iqw' ); ?></p></td>
                    </tr>
                    <tr>
                        <th><?php _e( 'Extra Numbers', 'iqw' ); ?></th>
                        <td><input type="text" name="iqw_sms_extra_numbers" value="<?php echo esc_attr( get_option( 'iqw_sms_extra_numbers' ) ); ?>" class="regular-text" placeholder="+18325559999, +18325558888">
                        <p class="description"><?php _e( 'Comma-separated. Additional numbers to notify.', 'iqw' ); ?></p></td>
                    </tr>
                    <tr>
                        <th><?php _e( 'Message Template', 'iqw' ); ?></th>
                        <td><textarea name="iqw_sms_template" class="large-text" rows="3" placeholder="New {form_title} lead!&#10;{full_name}&#10;{phone}&#10;{email}"><?php echo esc_textarea( get_option( 'iqw_sms_template', "New {form_title} lead!\n{full_name}\n{phone}\n{email}" ) ); ?></textarea>
                        <p class="description"><?php _e( 'Use merge tags: {full_name}, {email}, {phone}, {form_title}, {entry_id}, or any field key.', 'iqw' ); ?></p></td>
                    </tr>
                    <tr>
                        <th><?php _e( 'Test', 'iqw' ); ?></th>
                        <td><button type="button" class="button" id="iqw-test-sms"><?php _e( 'Send Test SMS', 'iqw' ); ?></button>
                        <span id="iqw-sms-test-result" style="margin-left:8px;"></span></td>
                    </tr>
                </table>
            </div>

            <!-- GDPR / Privacy -->
            <div class="iqw-card">
                <h2><?php _e( 'GDPR & Privacy', 'iqw' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><?php _e( 'Attach Quote to Email', 'iqw' ); ?></th>
                        <td><label><input type="checkbox" name="iqw_pdf_attach_to_email" value="1" <?php checked( get_option( 'iqw_pdf_attach_to_email' ) ); ?>> <?php _e( 'Attach entry summary (PDF if DomPDF installed, HTML otherwise) to admin notification email', 'iqw' ); ?></label>
                        <?php if ( IQW_PDF::has_dompdf() ) : ?>
                            <p class="description" style="color:green;">✅ <?php _e( 'DomPDF detected — real PDF files will be generated.', 'iqw' ); ?></p>
                        <?php else : ?>
                            <p class="description">ℹ️ <?php _e( 'DomPDF not found. HTML printable summary will be attached. To enable real PDF: install DomPDF via Composer in your site root or plugin directory.', 'iqw' ); ?></p>
                        <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e( 'Auto-Detect Location', 'iqw' ); ?></th>
                        <td><label><input type="checkbox" name="iqw_geolocation_enabled" value="1" <?php checked( get_option( 'iqw_geolocation_enabled' ) ); ?>> <?php _e( 'Auto-fill city, state, and zip fields from visitor IP address', 'iqw' ); ?></label>
                        <p class="description"><?php _e( 'Uses ip-api.com (free). Disabled when GDPR "Disable IP Tracking" is on.', 'iqw' ); ?></p></td>
                    </tr>
                    <tr>
                        <th><label for="iqw_gdpr_disable_ip"><?php _e( 'Disable IP Tracking', 'iqw' ); ?></label></th>
                        <td><label><input type="checkbox" name="iqw_gdpr_disable_ip" value="1" <?php checked( get_option( 'iqw_gdpr_disable_ip' ) ); ?>> <?php _e( 'Do not store visitor IP addresses in form entries', 'iqw' ); ?></label></td>
                    </tr>
                    <tr>
                        <th><?php _e( 'Disable User Agent Tracking', 'iqw' ); ?></th>
                        <td><label><input type="checkbox" name="iqw_gdpr_disable_ua" value="1" <?php checked( get_option( 'iqw_gdpr_disable_ua' ) ); ?>> <?php _e( 'Do not store browser/device info', 'iqw' ); ?></label></td>
                    </tr>
                    <tr>
                        <th><?php _e( 'Disable Referrer Tracking', 'iqw' ); ?></th>
                        <td><label><input type="checkbox" name="iqw_gdpr_disable_referrer" value="1" <?php checked( get_option( 'iqw_gdpr_disable_referrer' ) ); ?>> <?php _e( 'Do not store referrer URL', 'iqw' ); ?></label></td>
                    </tr>
                    <tr>
                        <th><?php _e( 'Auto-Delete Entries', 'iqw' ); ?></th>
                        <td>
                            <input type="number" name="iqw_gdpr_auto_delete_days" value="<?php echo esc_attr( get_option( 'iqw_gdpr_auto_delete_days', 0 ) ); ?>" min="0" class="small-text"> <?php _e( 'days', 'iqw' ); ?>
                            <p class="description"><?php _e( '0 = never auto-delete. Entries older than this will be permanently deleted daily.', 'iqw' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e( 'WordPress Privacy Tools', 'iqw' ); ?></th>
                        <td>
                            <p class="description"><?php _e( 'This plugin integrates with WordPress Privacy Tools (Tools → Export/Erase Personal Data). When a data request is made for an email address, all form entries matching that email will be included in the export or erased.', 'iqw' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Danger Zone -->
            <div class="iqw-card">
                <h2 style="color:#b32d2e;"><?php _e( 'Danger Zone', 'iqw' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><?php _e( 'Delete Data on Uninstall', 'iqw' ); ?></th>
                        <td><label><input type="checkbox" name="iqw_delete_data_uninstall" value="1" <?php checked( get_option( 'iqw_delete_data_uninstall' ) ); ?>> <?php _e( 'Remove ALL plugin data (forms, entries, settings) when plugin is deleted.', 'iqw' ); ?></label>
                        <p class="description" style="color:#b32d2e;"><?php _e( 'Warning: This cannot be undone!', 'iqw' ); ?></p></td>
                    </tr>
                </table>
            </div>
        </div>

        <p class="submit">
            <input type="submit" name="iqw_save_settings" class="button button-primary" value="<?php _e( 'Save Settings', 'iqw' ); ?>">
        </p>
    </form>
</div>
