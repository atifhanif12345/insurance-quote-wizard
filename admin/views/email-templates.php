<?php if ( ! defined( 'ABSPATH' ) ) exit;
$active_tab = sanitize_text_field( $_GET['tab'] ?? 'admin' );
$admin_tpl  = get_option( 'iqw_email_template_admin', '' );
$cust_tpl   = get_option( 'iqw_email_template_customer', '' );

// Get default templates for reset
$emailer = new IQW_Email();
?>
<div class="wrap iqw-wrap">
    <h1><?php _e( 'Email Templates', 'iqw' ); ?></h1>

    <!-- Tabs -->
    <h2 class="nav-tab-wrapper">
        <a href="<?php echo admin_url( 'admin.php?page=iqw-email-templates&tab=admin' ); ?>" class="nav-tab <?php echo $active_tab === 'admin' ? 'nav-tab-active' : ''; ?>">
            <?php _e( 'Admin Notification', 'iqw' ); ?>
        </a>
        <a href="<?php echo admin_url( 'admin.php?page=iqw-email-templates&tab=customer' ); ?>" class="nav-tab <?php echo $active_tab === 'customer' ? 'nav-tab-active' : ''; ?>">
            <?php _e( 'Customer Confirmation', 'iqw' ); ?>
        </a>
        <a href="<?php echo admin_url( 'admin.php?page=iqw-email-templates&tab=per-form' ); ?>" class="nav-tab <?php echo $active_tab === 'per-form' ? 'nav-tab-active' : ''; ?>">
            <?php _e( 'Per-Form Settings', 'iqw' ); ?>
        </a>
        <a href="<?php echo admin_url( 'admin.php?page=iqw-email-templates&tab=merge-tags' ); ?>" class="nav-tab <?php echo $active_tab === 'merge-tags' ? 'nav-tab-active' : ''; ?>">
            <?php _e( 'Merge Tags', 'iqw' ); ?>
        </a>
    </h2>

    <?php if ( $active_tab === 'admin' || $active_tab === 'customer' ) : ?>
    <div class="iqw-email-editor-wrap" style="display:grid;grid-template-columns:1fr 300px;gap:20px;margin-top:20px;">
        <!-- Editor -->
        <div class="iqw-card">
            <h2 style="margin-top:0;"><?php echo $active_tab === 'admin' ? __( 'Admin Notification Template', 'iqw' ) : __( 'Customer Confirmation Template', 'iqw' ); ?></h2>
            <p style="color:#666;font-size:13px;">
                <?php echo $active_tab === 'admin'
                    ? __( 'This email is sent to you (the agent) when a new lead comes in. Leave blank to use the default EverQuote-style template.', 'iqw' )
                    : __( 'This email is sent to the customer after they submit a form. Leave blank to use the default template.', 'iqw' ); ?>
            </p>

            <form method="post">
                <?php wp_nonce_field( 'iqw_email_template_nonce' ); ?>
                <input type="hidden" name="template_type" value="<?php echo esc_attr( $active_tab ); ?>">

                <?php
                $template_content = $active_tab === 'admin' ? $admin_tpl : $cust_tpl;
                wp_editor( $template_content, 'iqw_template_editor', array(
                    'textarea_name' => 'template_html',
                    'textarea_rows' => 20,
                    'media_buttons' => true,
                    'teeny'         => false,
                    'quicktags'     => true,
                    'tinymce'       => array(
                        'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,|,forecolor,backcolor,|,bullist,numlist,|,alignleft,aligncenter,alignright,|,link,unlink,|,table,hr,|,wp_adv',
                        'toolbar2' => 'fontsizeselect,pastetext,removeformat,charmap,outdent,indent,|,undo,redo,|,fullscreen,wp_help',
                        'content_css'   => '',
                        'body_class'    => 'iqw-email-editor-body',
                        'valid_elements' => '*[*]',
                        'extended_valid_elements' => 'table[*],tr[*],td[*],th[*],thead[*],tbody[*],div[*],span[*],a[*],img[*],p[*],h1[*],h2[*],h3[*],h4[*],strong[*],em[*],br,hr',
                    ),
                ) );
                ?>

                <p style="margin-top:12px;">
                    <input type="submit" name="iqw_save_email_template" class="button button-primary" value="<?php _e( 'Save Template', 'iqw' ); ?>">
                    <button type="button" class="button" id="iqw-reset-template" onclick="if(confirm('Reset to default template? Your custom template will be lost.')) { document.getElementById('iqw-template-editor').value = ''; document.querySelector('[name=iqw_save_email_template]').click(); }">
                        <?php _e( 'Reset to Default', 'iqw' ); ?>
                    </button>
                </p>
            </form>

            <!-- Test Email -->
            <div style="margin-top:24px;padding-top:20px;border-top:1px solid #eee;">
                <h3><?php _e( 'Send Test Email', 'iqw' ); ?></h3>
                <form method="post" style="display:flex;gap:8px;align-items:end;">
                    <?php wp_nonce_field( 'iqw_email_template_nonce' ); ?>
                    <input type="hidden" name="template_type" value="<?php echo esc_attr( $active_tab ); ?>">
                    <div>
                        <label style="font-size:13px;font-weight:600;"><?php _e( 'Send to:', 'iqw' ); ?></label><br>
                        <input type="email" name="test_email" value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" style="width:280px;" required>
                    </div>
                    <input type="submit" name="iqw_send_test_email" class="button" value="<?php _e( 'Send Test', 'iqw' ); ?>">
                </form>
            </div>
        </div>

        <!-- Sidebar: Preview info -->
        <div>
            <div class="iqw-card">
                <h3 style="margin-top:0;"><?php _e( 'Template Tips', 'iqw' ); ?></h3>
                <ul style="font-size:13px;color:#555;line-height:1.8;padding-left:18px;">
                    <li><?php _e( 'Use HTML for email layout', 'iqw' ); ?></li>
                    <li><?php _e( 'Inline CSS only (no &lt;style&gt; tags)', 'iqw' ); ?></li>
                    <li><?php _e( 'Use table-based layout for email clients', 'iqw' ); ?></li>
                    <li><?php _e( 'Max width: 600px recommended', 'iqw' ); ?></li>
                    <li><?php _e( 'Leave blank = use default template', 'iqw' ); ?></li>
                    <li><?php _e( 'Test before going live!', 'iqw' ); ?></li>
                </ul>
            </div>

            <div class="iqw-card" style="margin-top:16px;">
                <h3 style="margin-top:0;"><?php _e( 'Common Merge Tags', 'iqw' ); ?></h3>
                <div style="font-size:12px;">
                    <?php foreach ( array( '{full_name}', '{first_name}', '{email}', '{phone}', '{form_title}', '{entry_id}', '{date}', '{company_name}', '{fields_table}' ) as $tag ) : ?>
                    <code style="display:inline-block;margin:2px;padding:2px 6px;background:#f0f0f0;border-radius:3px;cursor:pointer;" onclick="navigator.clipboard.writeText('<?php echo $tag; ?>')"><?php echo $tag; ?></code>
                    <?php endforeach; ?>
                </div>
                <p style="font-size:12px;color:#999;margin-top:8px;"><?php _e( 'Click to copy. See Merge Tags tab for full list.', 'iqw' ); ?></p>
            </div>
        </div>
    </div>

    <?php elseif ( $active_tab === 'per-form' ) : ?>
    <!-- Per-Form Email Settings -->
    <div style="margin-top:20px;">
        <?php foreach ( $forms as $form ) :
            $form_obj = IQW_Form_Builder::get_form( $form->id );
            $es = $form_obj->email_settings ?? array();
        ?>
        <div class="iqw-card" style="margin-bottom:16px;">
            <h2 style="margin-top:0;">
                <span class="iqw-type-badge iqw-type-<?php echo esc_attr( $form->type ); ?>"><?php echo esc_html( ucfirst( $form->type ) ); ?></span>
                <?php echo esc_html( $form->title ); ?>
            </h2>
            <table class="form-table">
                <tr>
                    <th><?php _e( 'Admin Notifications', 'iqw' ); ?></th>
                    <td>
                        <label><input type="checkbox" <?php checked( $es['admin_enabled'] ?? true ); ?> disabled> <?php _e( 'Enabled', 'iqw' ); ?></label>
                    </td>
                </tr>
                <tr>
                    <th><?php _e( 'Send To', 'iqw' ); ?></th>
                    <td><code><?php echo esc_html( $es['admin_to'] ?? get_option( 'iqw_admin_email' ) ); ?></code></td>
                </tr>
                <tr>
                    <th><?php _e( 'CC', 'iqw' ); ?></th>
                    <td><code><?php echo esc_html( $es['admin_cc'] ?? 'None' ); ?></code></td>
                </tr>
                <tr>
                    <th><?php _e( 'Admin Subject', 'iqw' ); ?></th>
                    <td><code><?php echo esc_html( $es['admin_subject'] ?? 'New {form_title} Lead - {full_name}' ); ?></code></td>
                </tr>
                <tr>
                    <th><?php _e( 'Customer Confirmation', 'iqw' ); ?></th>
                    <td>
                        <label><input type="checkbox" <?php checked( $es['customer_enabled'] ?? true ); ?> disabled> <?php _e( 'Enabled', 'iqw' ); ?></label>
                    </td>
                </tr>
                <tr>
                    <th><?php _e( 'Customer Subject', 'iqw' ); ?></th>
                    <td><code><?php echo esc_html( $es['customer_subject'] ?? 'Thank you for your quote request, {first_name}!' ); ?></code></td>
                </tr>
            </table>
            <p>
                <a href="<?php echo admin_url( 'admin.php?page=iqw-form-edit&id=' . $form->id ); ?>" class="button button-small">
                    <?php _e( 'Edit Form Email Settings', 'iqw' ); ?>
                </a>
            </p>
        </div>
        <?php endforeach; ?>
    </div>

    <?php elseif ( $active_tab === 'merge-tags' ) : ?>
    <!-- Merge Tags Reference -->
    <div style="margin-top:20px;">
        <div class="iqw-card">
            <h2 style="margin-top:0;"><?php _e( 'Available Merge Tags', 'iqw' ); ?></h2>
            <p style="color:#666;"><?php _e( 'Use these tags in email subjects and templates. They will be replaced with actual values from the form submission.', 'iqw' ); ?></p>
            <table class="widefat striped" style="max-width:700px;">
                <thead>
                    <tr>
                        <th style="width:200px;"><?php _e( 'Tag', 'iqw' ); ?></th>
                        <th><?php _e( 'Description', 'iqw' ); ?></th>
                        <th style="width:180px;"><?php _e( 'Example Output', 'iqw' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $tags = IQW_Email::get_available_merge_tags();
                    $examples = array(
                        '{full_name}' => 'Melissa Niccum',
                        '{first_name}' => 'Melissa',
                        '{last_name}' => 'Niccum',
                        '{email}' => 'melissa@email.com',
                        '{phone}' => '(806) 584-7095',
                        '{city}' => 'Amarillo',
                        '{state}' => 'TX',
                        '{zip_code}' => '79118',
                        '{form_title}' => 'Auto Insurance Quote',
                        '{entry_id}' => '42',
                        '{date}' => 'March 17, 2026 2:30 PM',
                        '{date_short}' => '03/17/2026 02:30:00 PM',
                        '{site_name}' => 'Luis Hernandez Insurance',
                        '{site_url}' => 'https://luishernandez.com',
                        '{admin_url}' => '(link to entry)',
                        '{company_name}' => 'Luis Hernandez Agency',
                        '{company_phone}' => '(832) 813-8337',
                        '{fields_table}' => '(formatted HTML table)',
                        '{contact_bar}' => '(call + email buttons)',
                        '{location}' => 'Amarillo, TX, 79118',
                    );
                    foreach ( $tags as $tag => $desc ) : ?>
                    <tr>
                        <td><code style="cursor:pointer;background:#f0f0f0;padding:2px 8px;border-radius:3px;" onclick="navigator.clipboard.writeText('<?php echo esc_attr( $tag ); ?>');this.style.background='#d4edda';setTimeout(()=>this.style.background='#f0f0f0',1000)"><?php echo esc_html( $tag ); ?></code></td>
                        <td><?php echo esc_html( $desc ); ?></td>
                        <td style="color:#999;font-size:12px;"><?php echo esc_html( $examples[ $tag ] ?? '' ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p style="margin-top:16px;font-size:13px;color:#666;">
                <strong><?php _e( 'Tip:', 'iqw' ); ?></strong>
                <?php _e( 'Any field key from your form can also be used as a merge tag. For example, if you have a field with key "vehicle_1_make", you can use {vehicle_1_make} in your template.', 'iqw' ); ?>
            </p>
        </div>
    </div>
    <?php endif; ?>
</div>
