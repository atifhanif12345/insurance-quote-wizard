<?php if ( ! defined( 'ABSPATH' ) ) exit;
$is_new = ! $form;
$form_config = $form ? $form->config : array( 'settings' => array( 'motivation_text' => '3 minutes until deals. Let\'s go!', 'redirect_url' => '', 'success_message' => '' ), 'steps' => array() );
$email_settings = $form ? $form->email_settings : array( 'admin_enabled' => true, 'admin_to' => get_option( 'iqw_admin_email' ), 'admin_cc' => '', 'admin_subject' => 'New {form_title} Lead - {full_name}', 'customer_enabled' => true, 'customer_subject' => 'Thank you for your quote request, {first_name}!' );
$form_title = $form ? $form->title : '';
$form_type = $form ? $form->type : 'custom';
$form_status = $form ? $form->status : 'draft';
$form_id_val = $form ? $form->id : 0;
?>
<div class="wrap iqw-wrap iqw-builder-wrap">
    <!-- Top Bar -->
    <div class="iqw-builder-topbar">
        <div class="iqw-builder-topbar-left">
            <a href="<?php echo admin_url( 'admin.php?page=iqw-forms' ); ?>" class="iqw-builder-back" title="Back to Forms">&larr;</a>
            <input type="text" id="iqw-form-title" value="<?php echo esc_attr( $form_title ); ?>" placeholder="<?php _e( 'Form Title...', 'iqw' ); ?>" class="iqw-builder-title-input">
            <select id="iqw-form-type" class="iqw-builder-select">
                <?php foreach ( array( 'auto' => 'Auto', 'home' => 'Home', 'generic' => 'Generic', 'custom' => 'Custom' ) as $k => $v ) : ?>
                <option value="<?php echo $k; ?>" <?php selected( $form_type, $k ); ?>><?php echo $v; ?></option>
                <?php endforeach; ?>
            </select>
            <select id="iqw-form-status" class="iqw-builder-select">
                <option value="active" <?php selected( $form_status, 'active' ); ?>>Active</option>
                <option value="draft" <?php selected( $form_status, 'draft' ); ?>>Draft</option>
            </select>
        </div>
        <div class="iqw-builder-topbar-right">
            <?php if ( $form ) : ?>
            <span class="iqw-builder-shortcode">
                <code onclick="navigator.clipboard.writeText(this.textContent);this.style.background='#d4edda';">[insurance_quote_wizard id="<?php echo $form->id; ?>"]</code>
            </span>
            <?php endif; ?>
            <?php if ( $form ) : ?>
            <button class="button" id="iqw-export-current-form" title="Export this form as JSON">
                <span class="dashicons dashicons-download" style="vertical-align:middle;margin-top:-2px;"></span> <?php _e( 'Export', 'iqw' ); ?>
            </button>
            <?php endif; ?>
            <button class="button" id="iqw-preview-btn"><?php _e( 'Preview', 'iqw' ); ?></button>
            <div class="iqw-preview-toggle" style="display:inline-flex;border:1px solid #ccc;border-radius:4px;overflow:hidden;margin-right:6px;vertical-align:middle;">
                <button class="button iqw-preview-mode active" data-mode="desktop" title="Desktop" style="border:0;border-radius:0;padding:2px 8px;margin:0;">
                    <span class="dashicons dashicons-desktop" style="font-size:16px;width:16px;height:16px;line-height:16px;"></span>
                </button>
                <button class="button iqw-preview-mode" data-mode="tablet" title="Tablet" style="border:0;border-left:1px solid #ccc;border-radius:0;padding:2px 8px;margin:0;">
                    <span class="dashicons dashicons-tablet" style="font-size:16px;width:16px;height:16px;line-height:16px;"></span>
                </button>
                <button class="button iqw-preview-mode" data-mode="mobile" title="Mobile" style="border:0;border-left:1px solid #ccc;border-radius:0;padding:2px 8px;margin:0;">
                    <span class="dashicons dashicons-smartphone" style="font-size:16px;width:16px;height:16px;line-height:16px;"></span>
                </button>
            </div>
            <button class="button button-primary" id="iqw-save-form-btn" data-form-id="<?php echo $form_id_val; ?>">
                <span class="dashicons dashicons-cloud-saved" style="vertical-align:middle;margin-top:-2px;"></span>
                <?php echo $is_new ? __( 'Create Form', 'iqw' ) : __( 'Save', 'iqw' ); ?>
            </button>
        </div>
    </div>

    <!-- Builder Tabs -->
    <div class="iqw-builder-tabs">
        <button class="iqw-builder-tab active" data-tab="builder"><?php _e( 'Form Builder', 'iqw' ); ?></button>
        <button class="iqw-builder-tab" data-tab="email"><?php _e( 'Email Settings', 'iqw' ); ?></button>
        <button class="iqw-builder-tab" data-tab="settings"><?php _e( 'Form Settings', 'iqw' ); ?></button>
        <button class="iqw-builder-tab" data-tab="json"><?php _e( 'JSON Editor', 'iqw' ); ?></button>
    </div>

    <!-- Tab: Form Builder -->
    <div class="iqw-builder-tab-content active" data-tab="builder">
        <div class="iqw-builder-layout">
            <!-- Left: Field Palette -->
            <div class="iqw-builder-palette">
                <h3><?php _e( 'Fields', 'iqw' ); ?></h3>
                <input type="text" id="iqw-palette-search" placeholder="<?php _e( 'Search fields...', 'iqw' ); ?>" style="width:100%;padding:6px 10px;margin-bottom:10px;border:1px solid #ddd;border-radius:4px;font-size:13px;box-sizing:border-box;">
                <div class="iqw-palette-group">
                    <h4><?php _e( 'Basic', 'iqw' ); ?></h4>
                    <?php foreach ( $field_types as $type => $ft ) : if ( ($ft['category'] ?? '') !== 'basic' ) continue; ?>
                    <div class="iqw-palette-item" draggable="true" data-field-type="<?php echo esc_attr( $type ); ?>">
                        <span class="dashicons <?php echo esc_attr( $ft['icon'] ); ?>"></span>
                        <span><?php echo esc_html( $ft['label'] ); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="iqw-palette-group">
                    <h4><?php _e( 'Choice', 'iqw' ); ?></h4>
                    <?php foreach ( $field_types as $type => $ft ) : if ( ($ft['category'] ?? '') !== 'choice' ) continue; ?>
                    <div class="iqw-palette-item" draggable="true" data-field-type="<?php echo esc_attr( $type ); ?>">
                        <span class="dashicons <?php echo esc_attr( $ft['icon'] ); ?>"></span>
                        <span><?php echo esc_html( $ft['label'] ); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="iqw-palette-group">
                    <h4><?php _e( 'Advanced', 'iqw' ); ?></h4>
                    <?php foreach ( $field_types as $type => $ft ) : if ( !in_array( $ft['category'] ?? '', array( 'advanced', 'layout' ) ) ) continue; ?>
                    <div class="iqw-palette-item" draggable="true" data-field-type="<?php echo esc_attr( $type ); ?>">
                        <span class="dashicons <?php echo esc_attr( $ft['icon'] ); ?>"></span>
                        <span><?php echo esc_html( $ft['label'] ); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="iqw-palette-group" style="margin-top:16px;">
                    <h4><?php _e( 'Templates', 'iqw' ); ?></h4>
                    <button class="button iqw-load-template" data-template="auto" style="width:100%;margin-bottom:6px;">🚗 Auto Insurance</button>
                    <button class="button iqw-load-template" data-template="home" style="width:100%;margin-bottom:6px;">🏠 Home Insurance</button>
                    <button class="button iqw-load-template" data-template="generic" style="width:100%;">📋 Generic Quote</button>
                </div>
            </div>

            <!-- Center: Steps & Fields Canvas -->
            <div class="iqw-builder-canvas">
                <div class="iqw-steps-header">
                    <h3><?php _e( 'Steps', 'iqw' ); ?></h3>
                    <button class="button button-small" id="iqw-add-step">
                        <span class="dashicons dashicons-plus-alt2" style="vertical-align:middle;margin-top:-2px;"></span> <?php _e( 'Add Step', 'iqw' ); ?>
                    </button>
                </div>
                <div class="iqw-steps-list" id="iqw-steps-list">
                    <!-- Steps rendered by JS -->
                </div>
                <div class="iqw-canvas-empty" id="iqw-canvas-empty" style="display:none;">
                    <span class="dashicons dashicons-welcome-widgets-menus" style="font-size:48px;color:#ccc;"></span>
                    <h3><?php _e( 'No steps yet', 'iqw' ); ?></h3>
                    <p><?php _e( 'Click "Add Step" or load a template to get started.', 'iqw' ); ?></p>
                </div>
            </div>

            <!-- Right: Field Settings Panel -->
            <div class="iqw-builder-settings" id="iqw-field-settings-panel" style="display:none;">
                <div class="iqw-settings-header">
                    <h3 id="iqw-settings-title"><?php _e( 'Field Settings', 'iqw' ); ?></h3>
                    <button class="iqw-settings-close" id="iqw-settings-close">&times;</button>
                </div>
                <div class="iqw-settings-body" id="iqw-settings-body">
                    <!-- Populated by JS -->
                </div>
            </div>
        </div>
    </div>

    <!-- Tab: Email Settings -->
    <div class="iqw-builder-tab-content" data-tab="email">
        <div style="max-width:700px;margin:20px auto;">
            <div class="iqw-card">
                <h2 style="margin-top:0;"><?php _e( 'Admin Notification', 'iqw' ); ?></h2>
                <table class="form-table">
                    <tr><th><?php _e( 'Enable', 'iqw' ); ?></th>
                    <td><label><input type="checkbox" id="iqw-email-admin-enabled" <?php checked( $email_settings['admin_enabled'] ?? true ); ?>> <?php _e( 'Send notification on submission', 'iqw' ); ?></label></td></tr>
                    <tr><th><?php _e( 'Send To', 'iqw' ); ?></th>
                    <td><input type="email" id="iqw-email-admin-to" value="<?php echo esc_attr( $email_settings['admin_to'] ?? get_option('iqw_admin_email') ); ?>" class="regular-text"></td></tr>
                    <tr><th><?php _e( 'CC', 'iqw' ); ?></th>
                    <td><input type="text" id="iqw-email-admin-cc" value="<?php echo esc_attr( $email_settings['admin_cc'] ?? '' ); ?>" class="regular-text" placeholder="email1@example.com, email2@example.com"></td></tr>
                    <tr><th><?php _e( 'Subject', 'iqw' ); ?></th>
                    <td><input type="text" id="iqw-email-admin-subject" value="<?php echo esc_attr( $email_settings['admin_subject'] ?? 'New {form_title} Lead - {full_name}' ); ?>" class="regular-text"></td></tr>
                </table>
            </div>
            <div class="iqw-card" style="margin-top:16px;">
                <h2 style="margin-top:0;"><?php _e( 'Customer Confirmation', 'iqw' ); ?></h2>
                <table class="form-table">
                    <tr><th><?php _e( 'Enable', 'iqw' ); ?></th>
                    <td><label><input type="checkbox" id="iqw-email-cust-enabled" <?php checked( $email_settings['customer_enabled'] ?? true ); ?>> <?php _e( 'Send confirmation to customer', 'iqw' ); ?></label></td></tr>
                    <tr><th><?php _e( 'Subject', 'iqw' ); ?></th>
                    <td><input type="text" id="iqw-email-cust-subject" value="<?php echo esc_attr( $email_settings['customer_subject'] ?? 'Thank you for your quote request, {first_name}!' ); ?>" class="regular-text">
                    <p class="description"><?php _e( 'Available tags: {first_name}, {full_name}, {form_title}', 'iqw' ); ?></p></td></tr>
                </table>
            </div>

            <!-- Conditional Routing -->
            <div class="iqw-card" style="margin-top:16px;">
                <h2 style="margin-top:0;"><?php _e( 'Conditional Email Routing', 'iqw' ); ?></h2>
                <p style="color:#666;font-size:13px;"><?php _e( 'Send additional notifications to different agents based on form field values.', 'iqw' ); ?></p>
                <div id="iqw-routing-rules"><!-- Rendered by JS --></div>
                <button type="button" class="button button-small" id="iqw-add-routing-rule">+ <?php _e( 'Add Routing Rule', 'iqw' ); ?></button>
            </div>

            <!-- Webhooks -->
            <div class="iqw-card" style="margin-top:16px;">
                <h2 style="margin-top:0;"><?php _e( 'Webhooks (Zapier / CRM)', 'iqw' ); ?></h2>
                <p style="color:#666;font-size:13px;"><?php _e( 'Send submission data to external URLs automatically. Works with Zapier, Make, HubSpot, or any webhook endpoint.', 'iqw' ); ?></p>
                <div id="iqw-webhooks-list"><!-- Rendered by JS --></div>
                <button type="button" class="button button-small" id="iqw-add-webhook">+ <?php _e( 'Add Webhook', 'iqw' ); ?></button>
            </div>
        </div>
    </div>

    <!-- Tab: Form Settings -->
    <div class="iqw-builder-tab-content" data-tab="settings">
        <div style="max-width:700px;margin:20px auto;">
            <div class="iqw-card">
                <h2 style="margin-top:0;"><?php _e( 'Form Settings', 'iqw' ); ?></h2>
                <table class="form-table">
                    <tr><th><?php _e( 'Visual Theme', 'iqw' ); ?></th>
                    <td>
                        <?php $theme = $form_config['settings']['theme'] ?? 'default'; ?>
                        <select id="iqw-setting-theme">
                            <option value="default" <?php selected( $theme, 'default' ); ?>><?php _e( 'Default (Green)', 'iqw' ); ?></option>
                            <option value="modern-dark" <?php selected( $theme, 'modern-dark' ); ?>><?php _e( 'Modern Dark — neon green, dark bg, glassmorphism', 'iqw' ); ?></option>
                            <option value="clean-pro" <?php selected( $theme, 'clean-pro' ); ?>><?php _e( 'Clean Professional — white, blue, subtle shadows', 'iqw' ); ?></option>
                            <option value="warm" <?php selected( $theme, 'warm' ); ?>><?php _e( 'Warm Friendly — orange, rounded, soft', 'iqw' ); ?></option>
                            <option value="bold" <?php selected( $theme, 'bold' ); ?>><?php _e( 'Bold Insurance — navy + gold, corporate, trust', 'iqw' ); ?></option>
                            <option value="minimal" <?php selected( $theme, 'minimal' ); ?>><?php _e( 'Minimal — no borders, serif, editorial', 'iqw' ); ?></option>
                        </select>
                        <p class="description"><?php _e( 'Choose a visual style for this form. Can be combined with Custom CSS below.', 'iqw' ); ?></p>
                    </td></tr>
                    <tr><th><?php _e( 'Form Mode', 'iqw' ); ?></th>
                    <td>
                        <?php $mode = $form_config['settings']['form_mode'] ?? 'wizard'; ?>
                        <select id="iqw-setting-form-mode">
                            <option value="wizard" <?php selected( $mode, 'wizard' ); ?>><?php _e( 'Multi-Step Wizard', 'iqw' ); ?></option>
                            <option value="single" <?php selected( $mode, 'single' ); ?>><?php _e( 'Single Page Form', 'iqw' ); ?></option>
                            <option value="conversational" <?php selected( $mode, 'conversational' ); ?>><?php _e( 'Conversational (Typeform-style)', 'iqw' ); ?></option>
                        </select>
                        <p class="description"><?php _e( 'Wizard = step-by-step with progress bar. Single = all fields on one page.', 'iqw' ); ?></p>
                    </td></tr>
                    <tr><th><?php _e( 'Review Step', 'iqw' ); ?></th>
                    <td><label><input type="checkbox" id="iqw-setting-review-step" <?php checked( ! empty( $form_config['settings']['review_step'] ) ); ?>> <?php _e( 'Show review/summary step before submit', 'iqw' ); ?></label>
                    <p class="description"><?php _e( 'Adds a final step where users can review and edit their answers before submitting.', 'iqw' ); ?></p></td></tr>
                    <tr><th><?php _e( 'Motivation Text', 'iqw' ); ?></th>
                    <td><input type="text" id="iqw-setting-motivation" value="<?php echo esc_attr( $form_config['settings']['motivation_text'] ?? '' ); ?>" class="regular-text" placeholder="3 minutes until deals. Let's go!"></td></tr>
                    <tr><th><?php _e( 'Success Message', 'iqw' ); ?></th>
                    <td><textarea id="iqw-setting-success" class="large-text" rows="3" placeholder="Thank you! Your quote request has been submitted."><?php echo esc_textarea( $form_config['settings']['success_message'] ?? '' ); ?></textarea>
                    <p class="description"><?php _e( 'Supports merge tags: {full_name}, {email}, {phone}, {entry_id}, {form_title}', 'iqw' ); ?></p></td></tr>
                    <tr><th><?php _e( 'Confirmation Type', 'iqw' ); ?></th>
                    <td>
                        <?php $conf_type = $form_config['settings']['confirmation_type'] ?? 'message'; ?>
                        <select id="iqw-setting-confirmation-type">
                            <option value="message" <?php selected( $conf_type, 'message' ); ?>><?php _e( 'Show Success Message', 'iqw' ); ?></option>
                            <option value="page" <?php selected( $conf_type, 'page' ); ?>><?php _e( 'Show Confirmation Page (rich content)', 'iqw' ); ?></option>
                            <option value="redirect" <?php selected( $conf_type, 'redirect' ); ?>><?php _e( 'Redirect to URL', 'iqw' ); ?></option>
                        </select>
                    </td></tr>
                    <tr id="iqw-conf-page-row" style="<?php echo $conf_type !== 'page' ? 'display:none;' : ''; ?>"><th><?php _e( 'Confirmation Content', 'iqw' ); ?></th>
                    <td><textarea id="iqw-setting-confirmation-content" class="large-text" rows="6" placeholder="<h2>Thank you, {full_name}!</h2>&#10;<p>Your quote request #{entry_id} has been received.</p>&#10;<p>We'll contact you at {phone} within 24 hours.</p>"><?php echo esc_textarea( $form_config['settings']['confirmation_content'] ?? '' ); ?></textarea>
                    <p class="description"><?php _e( 'HTML allowed. Use merge tags for dynamic content.', 'iqw' ); ?></p></td></tr>
                    <tr id="iqw-conf-redirect-row" style="<?php echo $conf_type !== 'redirect' ? 'display:none;' : ''; ?>"><th><?php _e( 'Redirect URL', 'iqw' ); ?></th>
                    <td><input type="url" id="iqw-setting-redirect" value="<?php echo esc_attr( $form_config['settings']['redirect_url'] ?? '' ); ?>" class="regular-text" placeholder="https://...">
                    <p class="description"><?php _e( 'User will be redirected after submission.', 'iqw' ); ?></p></td></tr>

                    <!-- Form Scheduling -->
                    <tr><th colspan="2"><h3 style="margin:16px 0 0;"><?php _e( 'Form Scheduling', 'iqw' ); ?></h3></th></tr>
                    <tr><th><?php _e( 'Enable Scheduling', 'iqw' ); ?></th>
                    <td><label><input type="checkbox" id="iqw-setting-schedule-enabled" <?php checked( ! empty( $form_config['settings']['schedule_enabled'] ) ); ?>> <?php _e( 'Restrict form availability by date/time', 'iqw' ); ?></label></td></tr>
                    <tr class="iqw-schedule-row" style="<?php echo empty( $form_config['settings']['schedule_enabled'] ) ? 'display:none;' : ''; ?>"><th><?php _e( 'Start Date', 'iqw' ); ?></th>
                    <td><input type="datetime-local" id="iqw-setting-schedule-start" value="<?php echo esc_attr( $form_config['settings']['schedule_start'] ?? '' ); ?>"></td></tr>
                    <tr class="iqw-schedule-row" style="<?php echo empty( $form_config['settings']['schedule_enabled'] ) ? 'display:none;' : ''; ?>"><th><?php _e( 'End Date', 'iqw' ); ?></th>
                    <td><input type="datetime-local" id="iqw-setting-schedule-end" value="<?php echo esc_attr( $form_config['settings']['schedule_end'] ?? '' ); ?>"></td></tr>
                    <tr class="iqw-schedule-row" style="<?php echo empty( $form_config['settings']['schedule_enabled'] ) ? 'display:none;' : ''; ?>"><th><?php _e( 'Max Submissions', 'iqw' ); ?></th>
                    <td><input type="number" id="iqw-setting-schedule-max" value="<?php echo esc_attr( $form_config['settings']['schedule_max_submissions'] ?? '' ); ?>" min="0" placeholder="0 = unlimited" class="small-text">
                    <p class="description"><?php _e( '0 or empty = unlimited. Form closes after this many submissions.', 'iqw' ); ?></p></td></tr>
                    <tr class="iqw-schedule-row" style="<?php echo empty( $form_config['settings']['schedule_enabled'] ) ? 'display:none;' : ''; ?>"><th><?php _e( 'Closed Message', 'iqw' ); ?></th>
                    <td><textarea id="iqw-setting-schedule-closed-msg" class="large-text" rows="2" placeholder="This form is currently closed."><?php echo esc_textarea( $form_config['settings']['schedule_closed_message'] ?? '' ); ?></textarea></td></tr>
                </table>
            </div>

            <!-- Custom CSS -->
            <div class="iqw-card" style="margin-top:16px;">
                <h2 style="margin-top:0;"><?php _e( 'Custom CSS', 'iqw' ); ?></h2>
                <p style="color:#666;font-size:13px;"><?php _e( 'Add custom CSS for this form only. Use .iqw-wizard-container as the root selector.', 'iqw' ); ?></p>
                <textarea id="iqw-setting-custom-css" style="width:100%;height:180px;font-family:'Courier New',monospace;font-size:13px;border:1px solid #ddd;padding:10px;border-radius:4px;" placeholder=".iqw-wizard-container { }&#10;.iqw-wizard-container .iqw-btn-next { background: #ff5722; }"><?php echo esc_textarea( $form_config['settings']['custom_css'] ?? '' ); ?></textarea>
            </div>

            <!-- Google Sheets -->
            <div class="iqw-card" style="margin-top:16px;">
                <h2 style="margin-top:0;"><?php _e( 'Google Sheets Integration', 'iqw' ); ?></h2>
                <p style="color:#666;font-size:13px;"><?php _e( 'Automatically push submissions to a Google Sheet.', 'iqw' ); ?></p>
                <?php $gs = $email_settings['google_sheets'] ?? array(); ?>
                <table class="form-table">
                    <tr><th><?php _e( 'Enable', 'iqw' ); ?></th>
                    <td><label><input type="checkbox" id="iqw-gs-enabled" <?php checked( ! empty( $gs['enabled'] ) ); ?>> <?php _e( 'Push submissions to Google Sheets', 'iqw' ); ?></label></td></tr>
                    <tr><th><?php _e( 'Spreadsheet ID', 'iqw' ); ?></th>
                    <td><input type="text" id="iqw-gs-spreadsheet-id" value="<?php echo esc_attr( $gs['spreadsheet_id'] ?? '' ); ?>" class="regular-text" placeholder="1BxiM...">
                    <p class="description"><?php _e( 'From the URL: docs.google.com/spreadsheets/d/<strong>THIS_PART</strong>/edit', 'iqw' ); ?></p></td></tr>
                    <tr><th><?php _e( 'API Key', 'iqw' ); ?></th>
                    <td><input type="text" id="iqw-gs-api-key" value="<?php echo esc_attr( $gs['api_key'] ?? '' ); ?>" class="regular-text" placeholder="AIzaSy...">
                    <p class="description"><?php _e( 'Google Cloud Console > APIs > Credentials > API Key. Enable Sheets API.', 'iqw' ); ?></p></td></tr>
                    <tr><th><?php _e( 'Sheet Name', 'iqw' ); ?></th>
                    <td><input type="text" id="iqw-gs-sheet-name" value="<?php echo esc_attr( $gs['sheet_name'] ?? 'Sheet1' ); ?>" class="regular-text" placeholder="Sheet1"></td></tr>
                    <tr><th></th>
                    <td><button type="button" class="button" id="iqw-gs-test">🔗 <?php _e( 'Test Connection', 'iqw' ); ?></button> <span id="iqw-gs-test-result" style="margin-left:8px;"></span></td></tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Tab: JSON Editor (advanced) -->
    <div class="iqw-builder-tab-content" data-tab="json">
        <div style="max-width:900px;margin:20px auto;">
            <div class="iqw-card">
                <h2 style="margin-top:0;"><?php _e( 'JSON Configuration (Advanced)', 'iqw' ); ?></h2>
                <p style="color:#e65100;font-size:13px;"><?php _e( 'Warning: Editing JSON directly can break your form. Use the visual builder instead.', 'iqw' ); ?></p>
                <textarea id="iqw-json-editor" style="width:100%;height:500px;font-family:'Courier New',monospace;font-size:13px;line-height:1.4;border:1px solid #ddd;padding:12px;border-radius:4px;"></textarea>
                <p style="margin-top:8px;">
                    <button class="button" id="iqw-json-apply"><?php _e( 'Apply JSON to Builder', 'iqw' ); ?></button>
                    <button class="button" id="iqw-json-format"><?php _e( 'Format JSON', 'iqw' ); ?></button>
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Hidden data for JS -->
<script>
var iqwBuilderData = {
    formId: <?php echo $form_id_val; ?>,
    config: <?php echo wp_json_encode( $form_config ); ?>,
    emailSettings: <?php echo wp_json_encode( $email_settings ); ?>,
    fieldTypes: <?php echo wp_json_encode( $field_types ); ?>,
    siteUrl: '<?php echo esc_url( home_url( '/' ) ); ?>',
    templates: {
        auto: '<?php echo esc_url( IQW_PLUGIN_URL . 'templates/forms/auto-insurance.json' ); ?>',
        home: '<?php echo esc_url( IQW_PLUGIN_URL . 'templates/forms/home-insurance.json' ); ?>',
        generic: '<?php echo esc_url( IQW_PLUGIN_URL . 'templates/forms/generic-quote.json' ); ?>',
        health: '<?php echo esc_url( IQW_PLUGIN_URL . 'templates/forms/health-insurance.json' ); ?>',
        life: '<?php echo esc_url( IQW_PLUGIN_URL . 'templates/forms/life-insurance.json' ); ?>',
        commercial: '<?php echo esc_url( IQW_PLUGIN_URL . 'templates/forms/commercial-insurance.json' ); ?>',
        contact: '<?php echo esc_url( IQW_PLUGIN_URL . 'templates/forms/contact-inquiry.json' ); ?>'
    },
    icons: ['shield','car','user','home','contact','license','notes','location']
};
</script>
