<?php if ( ! defined( 'ABSPATH' ) ) exit;
$has_failures = false;
if ( is_array( $log ) ) {
    foreach ( $log as $item ) {
        if ( is_object( $item ) && empty( $item->sent ) ) { $has_failures = true; break; }
    }
}
?>
<div class="wrap iqw-wrap">
    <h1><?php _e( 'Email Log', 'iqw' ); ?></h1>
    <p style="color:#666;"><?php _e( 'Recent email delivery log.', 'iqw' ); ?></p>

    <?php if ( $has_failures ) : ?>
    <div class="notice notice-error" style="padding:12px 16px;">
        <h3 style="margin:0 0 8px;color:#c62828;">&#9888; <?php _e( 'Email Delivery Failures Detected', 'iqw' ); ?></h3>
        <p style="margin:0 0 8px;"><?php _e( 'Some emails failed. Install <strong>WP Mail SMTP</strong> or <strong>FluentSMTP</strong> plugin and configure your email provider (Gmail, SendGrid, etc.) to fix this.', 'iqw' ); ?></p>
    </div>
    <?php endif; ?>

    <?php if ( ! empty( $log ) ) : ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width:50px;">#</th>
                <th style="width:70px;"><?php _e( 'Entry', 'iqw' ); ?></th>
                <th style="width:90px;"><?php _e( 'Type', 'iqw' ); ?></th>
                <th><?php _e( 'To', 'iqw' ); ?></th>
                <th><?php _e( 'Subject', 'iqw' ); ?></th>
                <th style="width:80px;"><?php _e( 'Status', 'iqw' ); ?></th>
                <th style="width:160px;"><?php _e( 'Date', 'iqw' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $log as $i => $item ) :
                if ( ! is_object( $item ) ) continue;
            ?>
            <tr>
                <td><?php echo esc_html( $item->id ?? ( $i + 1 ) ); ?></td>
                <td>
                    <?php if ( ! empty( $item->entry_id ) && $item->entry_id != 999 ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=iqw-entries&action=view&id=' . $item->entry_id ) ); ?>">
                        #<?php echo esc_html( $item->entry_id ); ?>
                    </a>
                    <?php else : ?>
                    <span style="color:#999;"><?php echo ( isset( $item->entry_id ) && $item->entry_id == 999 ) ? 'TEST' : '-'; ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="iqw-type-badge <?php echo ( $item->type ?? '' ) === 'admin' ? 'iqw-type-auto' : 'iqw-type-home'; ?>">
                        <?php echo esc_html( ucfirst( $item->type ?? 'unknown' ) ); ?>
                    </span>
                </td>
                <td><?php echo esc_html( $item->recipient ?? '' ); ?></td>
                <td style="font-size:13px;"><?php echo esc_html( $item->subject ?? '' ); ?></td>
                <td>
                    <?php if ( ! empty( $item->sent ) ) : ?>
                    <span style="color:#27ae60;font-weight:600;">&#10003; Sent</span>
                    <?php else : ?>
                    <span style="color:#e74c3c;font-weight:600;">&#10007; Failed</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:12px;color:#666;"><?php echo ! empty( $item->created_at ) ? esc_html( date( 'M j, Y g:i A', strtotime( $item->created_at ) ) ) : '-'; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <p style="margin-top:20px;">
        <button class="button" onclick="if(confirm('Clear all email logs?')){
            jQuery.post(ajaxurl, {action:'iqw_clear_email_log',nonce:'<?php echo esc_attr( wp_create_nonce( 'iqw_admin_nonce' ) ); ?>'},function(){location.reload();});
        }">
            <span class="dashicons dashicons-dismiss" style="vertical-align:middle;"></span> <?php _e( 'Clear Log', 'iqw' ); ?>
        </button>
    </p>

    <?php else : ?>
    <div class="iqw-empty-state">
        <span class="dashicons dashicons-email" style="font-size:48px;color:#ccc;"></span>
        <h2><?php _e( 'No emails logged yet', 'iqw' ); ?></h2>
        <p><?php _e( 'Email delivery history will appear here after form submissions.', 'iqw' ); ?></p>
    </div>
    <?php endif; ?>
</div>
