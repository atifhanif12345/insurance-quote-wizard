<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap iqw-wrap">
    <?php if ( ! $entry ) : ?>
        <div class="notice notice-error"><p><?php _e( 'Entry not found.', 'iqw' ); ?></p></div>
        <?php return; endif; ?>

    <div class="iqw-entry-header">
        <div>
            <a href="<?php echo admin_url( 'admin.php?page=iqw-entries' ); ?>" class="iqw-back-link">&larr; <?php _e( 'Back to Entries', 'iqw' ); ?></a>
            <h1 style="display:inline;margin-left:8px;">
                <?php printf( __( 'Entry #%d', 'iqw' ), $entry->id ); ?>
                <span class="iqw-status iqw-status-<?php echo esc_attr( $entry->status ); ?>"><?php echo esc_html( ucfirst( $entry->status ) ); ?></span>
            </h1>
        </div>
        <div>
            <button onclick="window.print();" class="button"><span class="dashicons dashicons-printer" style="vertical-align:middle;"></span> <?php _e( 'Print', 'iqw' ); ?></button>
            <button class="button" id="iqw-edit-entry-btn" data-entry-id="<?php echo esc_attr( $entry->id ); ?>"><span class="dashicons dashicons-edit" style="vertical-align:middle;"></span> <?php _e( 'Edit Entry', 'iqw' ); ?></button>
            <button class="button button-primary" id="iqw-save-entry-btn" data-entry-id="<?php echo esc_attr( $entry->id ); ?>" style="display:none;"><span class="dashicons dashicons-saved" style="vertical-align:middle;"></span> <?php _e( 'Save Changes', 'iqw' ); ?></button>
            <button class="button" id="iqw-cancel-edit-btn" style="display:none;"><?php _e( 'Cancel', 'iqw' ); ?></button>
        </div>
    </div>

    <div class="iqw-entry-detail-wrap">
        <!-- Main Content -->
        <div class="iqw-entry-main">
            <?php
            $config = $form ? $form->config : null;
            $data = $entry->data;

            if ( $config && ! empty( $config['steps'] ) ) :
                foreach ( $config['steps'] as $si => $step ) :
                    if ( empty( $step['fields'] ) ) continue;
                    $has_data = false;
                    foreach ( $step['fields'] as $field ) {
                        $key = $field['key'] ?? '';
                        if ( ! empty( $data[ $key ] ) ) { $has_data = true; break; }
                    }
                    if ( ! $has_data ) continue;
            ?>
            <div class="iqw-entry-section">
                <h3 class="iqw-section-title">
                    <span><?php echo esc_html( $step['title'] ?? 'Section' ); ?></span>
                </h3>
                <table class="widefat">
                    <?php
                    $alt = false;
                    foreach ( $step['fields'] as $field ) :
                        $key = $field['key'] ?? '';
                        $value = $data[ $key ] ?? '';
                        if ( empty( $value ) && $value !== '0' ) continue;
                        if ( is_array( $value ) ) $value = implode( ', ', $value );
                        $alt = !$alt;

                        // Map value labels for select/radio fields
                        $display_value = $value;
                        if ( ! empty( $field['options'] ) ) {
                            foreach ( $field['options'] as $opt ) {
                                if ( $opt['value'] === $value ) {
                                    $display_value = $opt['label'];
                                    break;
                                }
                            }
                        }
                    ?>
                    <tr <?php echo $alt ? 'style="background:#fafafa;"' : ''; ?>>
                        <th style="width:200px;font-weight:600;font-size:13px;color:#555;">
                            <?php echo esc_html( $field['label'] ?? ucwords( str_replace( '_', ' ', $key ) ) ); ?>
                        </th>
                        <td style="font-size:14px;">
                            <span class="iqw-entry-display-val">
                            <?php
                            if ( $field['type'] === 'file_upload' && filter_var( $value, FILTER_VALIDATE_URL ) ) {
                                echo '<a href="' . esc_url( $value ) . '" target="_blank">📎 View File</a>';
                            } elseif ( $field['type'] === 'signature' && strpos( $value, 'data:image' ) === 0 ) {
                                echo '<img src="' . esc_attr( $value ) . '" alt="Signature" style="max-width:300px;border:1px solid #ddd;border-radius:4px;">';
                            } elseif ( $field['type'] === 'email' ) {
                                echo '<a href="mailto:' . esc_attr( $value ) . '">' . esc_html( $value ) . '</a>';
                            } elseif ( $field['type'] === 'phone' ) {
                                echo '<a href="tel:' . esc_attr( $value ) . '">' . esc_html( $value ) . '</a>';
                            } else {
                                echo esc_html( $display_value );
                            }
                            ?>
                            </span>
                            <input type="text" class="iqw-entry-edit-input regular-text" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>" style="display:none;">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <?php endforeach;
            else :
            ?>
            <div class="iqw-entry-section">
                <h3 class="iqw-section-title"><?php _e( 'Submitted Data', 'iqw' ); ?></h3>
                <table class="widefat">
                    <?php foreach ( $data as $key => $value ) :
                        if ( is_array( $value ) ) $value = implode( ', ', $value );
                    ?>
                    <tr>
                        <th style="width:200px;"><?php echo esc_html( ucwords( str_replace( '_', ' ', $key ) ) ); ?></th>
                        <td><?php
                            if ( strpos( $value, 'data:image' ) === 0 ) {
                                echo '<img src="' . esc_attr( $value ) . '" alt="Signature" style="max-width:300px;border:1px solid #ddd;border-radius:4px;">';
                            } else {
                                echo esc_html( $value );
                            }
                        ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <?php endif; ?>

            <!-- Notes Section -->
            <div class="iqw-entry-section iqw-notes-section">
                <h3 class="iqw-section-title" style="background:#f0f0f0;color:#333;">
                    <span><?php _e( 'Notes', 'iqw' ); ?></span>
                    <span class="iqw-note-count">(<?php echo count( $notes ); ?>)</span>
                </h3>
                <div class="iqw-notes-list" id="iqw-notes-list">
                    <?php if ( ! empty( $notes ) ) :
                        foreach ( $notes as $n ) : ?>
                    <div class="iqw-note" data-note-id="<?php echo esc_attr( $n->id ); ?>">
                        <div class="iqw-note-header">
                            <strong><?php echo esc_html( $n->author ); ?></strong>
                            <span class="iqw-note-date"><?php echo esc_html( date( 'M j, Y g:i A', strtotime( $n->created_at ) ) ); ?></span>
                            <a href="#" class="iqw-delete-note" data-note-id="<?php echo esc_attr( $n->id ); ?>" title="Delete">&times;</a>
                        </div>
                        <div class="iqw-note-body"><?php echo nl2br( esc_html( $n->note ) ); ?></div>
                    </div>
                    <?php endforeach;
                    else : ?>
                    <p class="iqw-no-notes" style="padding:16px;color:#999;text-align:center;margin:0;"><?php _e( 'No notes yet.', 'iqw' ); ?></p>
                    <?php endif; ?>
                </div>
                <div class="iqw-add-note" style="padding:16px;border-top:1px solid #eee;">
                    <textarea id="iqw-new-note" rows="3" style="width:100%;margin-bottom:8px;" placeholder="<?php _e( 'Add a note... (e.g., Called client, quoted $X)', 'iqw' ); ?>"></textarea>
                    <button class="button button-primary" id="iqw-save-note" data-entry-id="<?php echo esc_attr( $entry->id ); ?>">
                        <span class="dashicons dashicons-edit" style="vertical-align:middle;margin-top:-2px;"></span> <?php _e( 'Add Note', 'iqw' ); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="iqw-entry-sidebar">
            <!-- Entry Info -->
            <div class="iqw-sidebar-card">
                <h3><?php _e( 'Entry Info', 'iqw' ); ?></h3>
                <div class="iqw-sidebar-row">
                    <span class="dashicons dashicons-welcome-widgets-menus"></span>
                    <span><?php echo esc_html( $form ? $form->title : '-' ); ?></span>
                </div>
                <div class="iqw-sidebar-row">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    <span><?php echo esc_html( date( 'M j, Y g:i A', strtotime( $entry->created_at ) ) ); ?></span>
                </div>
                <?php if ( $entry->read_at ) : ?>
                <div class="iqw-sidebar-row">
                    <span class="dashicons dashicons-visibility"></span>
                    <span><?php printf( __( 'Read: %s', 'iqw' ), date( 'M j, g:i A', strtotime( $entry->read_at ) ) ); ?></span>
                </div>
                <?php endif; ?>
                <div class="iqw-sidebar-row">
                    <span class="dashicons dashicons-admin-site-alt3"></span>
                    <span><?php echo esc_html( $entry->ip_address ); ?></span>
                </div>
                <?php if ( $entry->referrer_url ) : ?>
                <div class="iqw-sidebar-row">
                    <span class="dashicons dashicons-admin-links"></span>
                    <span style="word-break:break-all;font-size:12px;"><?php echo esc_html( $entry->referrer_url ); ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Quick Contact -->
            <?php if ( ! empty( $data['email'] ) || ! empty( $data['phone'] ) ) : ?>
            <div class="iqw-sidebar-card">
                <h3><?php _e( 'Quick Contact', 'iqw' ); ?></h3>
                <?php if ( ! empty( $data['email'] ) ) : ?>
                <a href="mailto:<?php echo esc_attr( $data['email'] ); ?>" class="button button-primary iqw-contact-btn">
                    <span class="dashicons dashicons-email"></span> <?php _e( 'Email Client', 'iqw' ); ?>
                </a>
                <?php endif; ?>
                <?php if ( ! empty( $data['phone'] ) ) : ?>
                <a href="tel:<?php echo esc_attr( preg_replace( '/[^\d+]/', '', $data['phone'] ) ); ?>" class="button iqw-contact-btn">
                    <span class="dashicons dashicons-phone"></span> <?php echo esc_html( $data['phone'] ); ?>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Pipeline Status -->
            <div class="iqw-sidebar-card">
                <h3><?php _e( 'Pipeline Status', 'iqw' ); ?></h3>
                <div style="display:flex;flex-direction:column;gap:6px;">
                    <?php
                    $pipeline = array(
                        'contacted' => array( 'icon' => 'phone', 'color' => '#1565c0', 'label' => __( 'Contacted', 'iqw' ) ),
                        'quoted'    => array( 'icon' => 'media-spreadsheet', 'color' => '#e65100', 'label' => __( 'Quoted', 'iqw' ) ),
                        'sold'      => array( 'icon' => 'yes-alt', 'color' => '#2e7d32', 'label' => __( 'Sold', 'iqw' ) ),
                        'lost'      => array( 'icon' => 'dismiss', 'color' => '#999', 'label' => __( 'Lost', 'iqw' ) ),
                    );
                    foreach ( $pipeline as $pstatus => $pinfo ) :
                        $active = ( $entry->status === $pstatus );
                    ?>
                    <a href="#" class="button iqw-entry-action<?php echo $active ? ' button-primary' : ''; ?>" data-id="<?php echo esc_attr( $entry->id ); ?>" data-status="<?php echo esc_attr( $pstatus ); ?>" style="<?php echo $active ? 'background:' . esc_attr( $pinfo['color'] ) . ';border-color:' . esc_attr( $pinfo['color'] ) . ';' : ''; ?>">
                        <span class="dashicons dashicons-<?php echo esc_attr( $pinfo['icon'] ); ?>" style="vertical-align:middle;"></span>
                        <?php echo esc_html( $pinfo['label'] ); ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Actions -->
            <div class="iqw-sidebar-card">
                <h3><?php _e( 'Actions', 'iqw' ); ?></h3>
                <div style="display:flex;flex-direction:column;gap:8px;">
                    <a href="#" class="button iqw-entry-action" data-id="<?php echo esc_attr( $entry->id ); ?>" data-status="starred">
                        <span class="dashicons dashicons-star-<?php echo esc_attr( $entry->status ) === 'starred' ? 'filled' : 'empty'; ?>" style="vertical-align:middle;color:#f57f17;"></span>
                        <?php echo esc_attr( $entry->status ) === 'starred' ? __( 'Unstar', 'iqw' ) : __( 'Star', 'iqw' ); ?>
                    </a>
                    <button class="button" id="iqw-gen-pdf-btn" data-id="<?php echo esc_attr( $entry->id ); ?>">
                        <span class="dashicons dashicons-media-document" style="vertical-align:middle;color:#1565c0;"></span> <?php _e( 'Printable Summary', 'iqw' ); ?>
                    </button>
                    <button class="button" id="iqw-export-entry-json" data-id="<?php echo esc_attr( $entry->id ); ?>">
                        <span class="dashicons dashicons-media-code" style="vertical-align:middle;"></span> <?php _e( 'Export JSON', 'iqw' ); ?>
                    </button>
                    <button class="button" id="iqw-anonymize-entry" data-id="<?php echo esc_attr( $entry->id ); ?>" style="color:#e67e22;">
                        <span class="dashicons dashicons-privacy" style="vertical-align:middle;"></span> <?php _e( 'Anonymize (GDPR)', 'iqw' ); ?>
                    </button>
                    <a href="#" class="button iqw-entry-action" data-id="<?php echo esc_attr( $entry->id ); ?>" data-status="archived">
                        <span class="dashicons dashicons-archive" style="vertical-align:middle;"></span> <?php _e( 'Archive', 'iqw' ); ?>
                    </a>
                    <a href="#" class="button iqw-entry-action" data-id="<?php echo esc_attr( $entry->id ); ?>" data-status="trash" style="color:#b32d2e;">
                        <span class="dashicons dashicons-trash" style="vertical-align:middle;"></span> <?php _e( 'Trash', 'iqw' ); ?>
                    </a>
                </div>
            </div>

            <!-- Email History -->
            <div class="iqw-sidebar-card">
                <h3><?php _e( 'Email History', 'iqw' ); ?></h3>
                <?php
                $email_log = IQW_Email::get_entry_email_log( $entry->id );
                if ( ! empty( $email_log ) ) :
                    foreach ( $email_log as $em ) : ?>
                <div style="padding:6px 0;border-bottom:1px solid #f0f0f0;font-size:12px;">
                    <span class="iqw-type-badge <?php echo esc_attr( $em->type === 'admin' ? 'iqw-type-auto' : 'iqw-type-home' ); ?>" style="font-size:10px;padding:1px 6px;"><?php echo esc_html( ucfirst( $em->type ) ); ?></span>
                    <?php echo $em->sent ? '<span style="color:#27ae60;">✓</span>' : '<span style="color:#e74c3c;">✗</span>'; ?>
                    <span style="color:#999;"><?php echo esc_html( date( 'M j, g:i A', strtotime( $em->created_at ) ) ); ?></span>
                </div>
                <?php endforeach;
                else : ?>
                <p style="font-size:13px;color:#999;margin:0;"><?php _e( 'No emails sent yet.', 'iqw' ); ?></p>
                <?php endif; ?>

                <div style="margin-top:12px;display:flex;gap:6px;">
                    <button class="button button-small iqw-resend-email" data-id="<?php echo esc_attr( $entry->id ); ?>" data-type="admin">
                        <?php _e( 'Resend Admin', 'iqw' ); ?>
                    </button>
                    <?php if ( ! empty( $data['email'] ) ) : ?>
                    <button class="button button-small iqw-resend-email" data-id="<?php echo esc_attr( $entry->id ); ?>" data-type="customer">
                        <?php _e( 'Resend Customer', 'iqw' ); ?>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
