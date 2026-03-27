<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap iqw-wrap">
    <h1 class="wp-heading-inline"><?php _e( 'All Forms', 'iqw' ); ?></h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=iqw-form-edit' ) ); ?>" class="page-title-action"><?php _e( 'Add New', 'iqw' ); ?></a>
    <hr class="wp-header-end">

    <!-- Import/Export Bar -->
    <div class="iqw-import-export-bar" style="display:flex;gap:8px;align-items:center;margin:12px 0;flex-wrap:wrap;">
        <?php if ( ! empty( $forms ) ) : ?>
        <button class="button" id="iqw-export-all-forms">
            <span class="dashicons dashicons-download" style="vertical-align:middle;margin-top:-2px;"></span> <?php _e( 'Export All Forms', 'iqw' ); ?>
        </button>
        <?php endif; ?>
        <button class="button" id="iqw-import-form-btn">
            <span class="dashicons dashicons-upload" style="vertical-align:middle;margin-top:-2px;"></span> <?php _e( 'Import Form', 'iqw' ); ?>
        </button>
        <input type="file" id="iqw-import-file" accept=".json" style="display:none;">
    </div>

    <?php if ( ! empty( $forms ) ) : ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width:40px;"><?php _e( 'ID', 'iqw' ); ?></th>
                <th><?php _e( 'Form Title', 'iqw' ); ?></th>
                <th style="width:120px;"><?php _e( 'Type', 'iqw' ); ?></th>
                <th style="width:100px;"><?php _e( 'Status', 'iqw' ); ?></th>
                <th style="width:80px;"><?php _e( 'Entries', 'iqw' ); ?></th>
                <th style="width:200px;"><?php _e( 'Shortcode', 'iqw' ); ?></th>
                <th style="width:160px;"><?php _e( 'Created', 'iqw' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $all_entry_counts = IQW_Submission::get_all_form_entry_counts();
            foreach ( $forms as $form ) :
                $entry_count = $all_entry_counts[ $form->id ] ?? 0;
            ?>
            <tr>
                <td><?php echo esc_html( $form->id ); ?></td>
                <td>
                    <strong>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=iqw-form-edit&id=' . $form->id ) ); ?>" class="row-title">
                            <?php echo esc_html( $form->title ); ?>
                        </a>
                    </strong>
                    <div class="row-actions">
                        <span class="edit">
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=iqw-form-edit&id=' . $form->id ) ); ?>"><?php _e( 'Edit', 'iqw' ); ?></a> |
                        </span>
                        <span class="duplicate">
                            <a href="#" class="iqw-duplicate-form" data-id="<?php echo esc_attr( $form->id ); ?>"><?php _e( 'Duplicate', 'iqw' ); ?></a> |
                        </span>
                        <span>
                            <a href="#" class="iqw-export-form" data-id="<?php echo esc_attr( $form->id ); ?>" data-title="<?php echo esc_attr( $form->title ); ?>"><?php _e( 'Export', 'iqw' ); ?></a> |
                        </span>
                        <span class="view">
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=iqw-entries&form_id=' . $form->id ) ); ?>"><?php _e( 'View Entries', 'iqw' ); ?></a> |
                        </span>
                        <span class="trash">
                            <a href="#" class="iqw-delete-form" data-id="<?php echo esc_attr( $form->id ); ?>" style="color:#b32d2e;"><?php _e( 'Delete', 'iqw' ); ?></a>
                        </span>
                    </div>
                </td>
                <td><span class="iqw-type-badge iqw-type-<?php echo esc_attr( $form->type ); ?>"><?php echo esc_html( ucfirst( $form->type ) ); ?></span></td>
                <td><span class="iqw-status iqw-status-<?php echo esc_attr( $form->status ); ?>"><?php echo esc_html( ucfirst( $form->status ) ); ?></span></td>
                <td><?php echo esc_html( $entry_count ); ?></td>
                <td><code class="iqw-shortcode-copy" title="Click to copy">[insurance_quote_wizard id="<?php echo esc_attr( $form->id ); ?>"]</code></td>
                <td><?php echo esc_html( date( 'M j, Y', strtotime( $form->created_at ) ) ); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else : ?>
    <div class="iqw-empty-state">
        <span class="dashicons dashicons-welcome-widgets-menus" style="font-size:48px;color:#ccc;"></span>
        <h2><?php _e( 'No forms yet', 'iqw' ); ?></h2>
        <p><?php _e( 'Create your first form or import an existing one.', 'iqw' ); ?></p>
        <p>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=iqw-form-edit' ) ); ?>" class="button button-primary button-hero"><?php _e( 'Create Your First Form', 'iqw' ); ?></a>
            <button class="button button-hero" id="iqw-import-form-btn-empty" style="margin-left:8px;"><?php _e( 'Import Form', 'iqw' ); ?></button>
        </p>
    </div>
    <?php endif; ?>
</div>

<!-- Hidden: all forms data for export -->
<script>
var iqwFormsExportData = <?php
    $export_data = array();
    foreach ( $forms as $f ) {
        $full = IQW_Form_Builder::get_form( $f->id );
        if ( $full ) {
            $export_data[ $f->id ] = array(
                'title'          => $full->title,
                'slug'           => $full->slug,
                'type'           => $full->type,
                'config'         => $full->config,
                'email_settings' => $full->email_settings,
                'status'         => $full->status,
                'exported_at'    => current_time( 'c' ),
                'plugin_version' => IQW_VERSION,
            );
        }
    }
    echo wp_json_encode( $export_data );
?>;
</script>
