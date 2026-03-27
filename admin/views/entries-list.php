<?php if ( ! defined( 'ABSPATH' ) ) exit;
$current_status = sanitize_text_field( $_GET['status'] ?? '' );
$current_form   = absint( $_GET['form_id'] ?? 0 );
$date_from      = sanitize_text_field( $_GET['date_from'] ?? '' );
$date_to        = sanitize_text_field( $_GET['date_to'] ?? '' );
$search         = sanitize_text_field( $_GET['s'] ?? '' );
$paged          = max( 1, absint( $_GET['paged'] ?? 1 ) );
?>
<div class="wrap iqw-wrap">
    <h1 class="wp-heading-inline"><?php _e( 'Entries', 'iqw' ); ?></h1>
    <hr class="wp-header-end">

    <!-- Status tabs -->
    <ul class="subsubsub">
        <?php
        $statuses = array(
            ''         => array( 'label' => __( 'All', 'iqw' ), 'count' => $counts['all'] ),
            'new'      => array( 'label' => __( 'New', 'iqw' ), 'count' => $counts['new'] ),
            'read'     => array( 'label' => __( 'Read', 'iqw' ), 'count' => $counts['read'] ),
            'starred'  => array( 'label' => __( 'Starred', 'iqw' ), 'count' => $counts['starred'] ),
            'contacted' => array( 'label' => __( 'Contacted', 'iqw' ), 'count' => $counts['contacted'] ),
            'quoted'    => array( 'label' => __( 'Quoted', 'iqw' ), 'count' => $counts['quoted'] ),
            'sold'      => array( 'label' => __( 'Sold', 'iqw' ), 'count' => $counts['sold'] ),
            'lost'      => array( 'label' => __( 'Lost', 'iqw' ), 'count' => $counts['lost'] ),
            'archived' => array( 'label' => __( 'Archived', 'iqw' ), 'count' => $counts['archived'] ),
            'trash'    => array( 'label' => __( 'Trash', 'iqw' ), 'count' => $counts['trash'] ),
        );
        $si = 0;
        foreach ( $statuses as $key => $s ) :
            $active = ( $current_status === $key ) ? 'class="current"' : '';
            $url = admin_url( 'admin.php?page=iqw-entries' . ( $key ? '&status=' . $key : '' ) );
        ?>
        <li>
            <a href="<?php echo esc_url( $url ); ?>" <?php echo $active; ?>>
                <?php echo esc_html( $s['label'] ); ?> <span class="count">(<?php echo esc_html( $s['count'] ); ?>)</span>
            </a><?php if ( ++$si < count( $statuses ) ) echo ' |'; ?>
        </li>
        <?php endforeach; ?>
    </ul>

    <!-- Filters -->
    <div class="tablenav top" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
        <div class="alignleft actions" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
            <select name="bulk_action" id="iqw-bulk-action">
                <option value=""><?php _e( 'Bulk Actions', 'iqw' ); ?></option>
                <option value="read"><?php _e( 'Mark as Read', 'iqw' ); ?></option>
                <option value="starred"><?php _e( 'Star', 'iqw' ); ?></option>
                <option value="contacted"><?php _e( 'Contacted', 'iqw' ); ?></option>
                <option value="quoted"><?php _e( 'Quoted', 'iqw' ); ?></option>
                <option value="sold"><?php _e( 'Sold', 'iqw' ); ?></option>
                <option value="lost"><?php _e( 'Lost', 'iqw' ); ?></option>
                <option value="archived"><?php _e( 'Archive', 'iqw' ); ?></option>
                <option value="trash"><?php _e( 'Trash', 'iqw' ); ?></option>
                <?php if ( $current_status === 'trash' ) : ?>
                <option value="delete"><?php _e( 'Delete Permanently', 'iqw' ); ?></option>
                <?php endif; ?>
            </select>
            <button class="button" id="iqw-bulk-apply"><?php _e( 'Apply', 'iqw' ); ?></button>

            <span class="iqw-filter-sep">|</span>

            <select name="form_filter" id="iqw-form-filter">
                <option value=""><?php _e( 'All Forms', 'iqw' ); ?></option>
                <?php foreach ( $forms as $f ) : ?>
                <option value="<?php echo esc_attr( $f->id ); ?>" <?php selected( $current_form, $f->id ); ?>>
                    <?php echo esc_html( $f->title ); ?>
                </option>
                <?php endforeach; ?>
            </select>

            <input type="date" id="iqw-date-from" value="<?php echo esc_attr( $date_from ); ?>" placeholder="From" title="From date" style="width:140px;">
            <span>-</span>
            <input type="date" id="iqw-date-to" value="<?php echo esc_attr( $date_to ); ?>" placeholder="To" title="To date" style="width:140px;">

            <button class="button" id="iqw-filter-apply"><?php _e( 'Filter', 'iqw' ); ?></button>
            <?php if ( $current_form || $date_from || $date_to ) : ?>
            <a href="<?php echo admin_url( 'admin.php?page=iqw-entries' . ( $current_status ? '&status=' . $current_status : '' ) ); ?>" class="button" style="color:#999;"><?php _e( 'Clear', 'iqw' ); ?></a>
            <?php endif; ?>
        </div>

        <div style="margin-left:auto;display:flex;gap:8px;align-items:center;">
            <form method="get" action="<?php echo admin_url( 'admin.php' ); ?>" style="display:flex;gap:4px;">
                <input type="hidden" name="page" value="iqw-entries">
                <?php if ( $current_status ) : ?><input type="hidden" name="status" value="<?php echo esc_attr( $current_status ); ?>"><?php endif; ?>
                <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php _e( 'Search name, email, phone...', 'iqw' ); ?>" style="width:220px;">
                <input type="submit" class="button" value="<?php _e( 'Search', 'iqw' ); ?>">
            </form>

            <a href="<?php echo wp_nonce_url( admin_url( 'admin-ajax.php?action=iqw_export_entries&form_id=' . $current_form . '&status=' . $current_status . '&date_from=' . $date_from . '&date_to=' . $date_to ), 'iqw_export_nonce' ); ?>" class="button" title="Export CSV">
                <span class="dashicons dashicons-download" style="vertical-align:middle;margin-top:-2px;"></span> CSV
            </a>
            <button class="button" id="iqw-export-json-btn" title="Export selected as JSON">
                <span class="dashicons dashicons-media-code" style="vertical-align:middle;margin-top:-2px;"></span> Export JSON
            </button>
            <button class="button" id="iqw-import-json-btn" title="Import entries from JSON">
                <span class="dashicons dashicons-upload" style="vertical-align:middle;margin-top:-2px;"></span> Import JSON
            </button>
            <input type="file" id="iqw-import-file" accept=".json" style="display:none;">
        </div>
    </div>

    <?php if ( ! empty( $entries ) ) : ?>
    <!-- Results count -->
    <p class="iqw-results-count" style="color:#666;font-size:13px;margin:4px 0 8px;">
        <?php printf( __( 'Showing %d-%d of %d entries', 'iqw' ),
            ( ( $paged - 1 ) * $per_page ) + 1,
            min( $paged * $per_page, $total ),
            $total
        ); ?>
    </p>

    <table class="wp-list-table widefat fixed striped iqw-entries-table">
        <thead>
            <tr>
                <td class="check-column"><input type="checkbox" id="iqw-select-all"></td>
                <th style="width:30px;"></th>
                <th style="width:50px;"><?php _e( 'ID', 'iqw' ); ?></th>
                <th><?php _e( 'Name', 'iqw' ); ?></th>
                <th><?php _e( 'Email', 'iqw' ); ?></th>
                <th style="width:130px;"><?php _e( 'Phone', 'iqw' ); ?></th>
                <th style="width:140px;"><?php _e( 'Form', 'iqw' ); ?></th>
                <th style="width:80px;"><?php _e( 'Status', 'iqw' ); ?></th>
                <th style="width:150px;"><?php _e( 'Date', 'iqw' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $entries as $entry ) :
                $data = is_string( $entry->data ) ? json_decode( $entry->data, true ) : $entry->data;
                $eform = IQW_Form_Builder::get_form( $entry->form_id );
                $is_new = $entry->status === 'new';
                $is_starred = $entry->status === 'starred';
                $name = $data['full_name'] ?? trim( ( $data['first_name'] ?? '' ) . ' ' . ( $data['last_name'] ?? '' ) );
                $view_url = admin_url( 'admin.php?page=iqw-entries&action=view&id=' . $entry->id );
            ?>
            <tr class="<?php echo $is_new ? 'iqw-entry-new' : ''; ?>" id="iqw-row-<?php echo esc_attr( $entry->id ); ?>">
                <th class="check-column"><input type="checkbox" class="iqw-entry-cb" value="<?php echo esc_attr( $entry->id ); ?>"></th>
                <td>
                    <a href="#" class="iqw-star-toggle <?php echo $is_starred ? 'starred' : ''; ?>" data-id="<?php echo esc_attr( $entry->id ); ?>" title="Star">
                        <span class="dashicons dashicons-star-<?php echo $is_starred ? 'filled' : 'empty'; ?>"></span>
                    </a>
                </td>
                <td><a href="<?php echo esc_url( $view_url ); ?>">#<?php echo esc_html( $entry->id ); ?></a></td>
                <td>
                    <strong <?php echo $is_new ? 'style="color:#1565c0;"' : ''; ?>>
                        <a href="<?php echo esc_url( $view_url ); ?>"><?php echo esc_html( $name ?: '-' ); ?></a>
                    </strong>
                    <div class="row-actions">
                        <span><a href="<?php echo esc_url( $view_url ); ?>"><?php _e( 'View', 'iqw' ); ?></a> | </span>
                        <?php if ( ! empty( $data['email'] ) ) : ?>
                        <span><a href="mailto:<?php echo esc_attr( $data['email'] ); ?>"><?php _e( 'Email', 'iqw' ); ?></a> | </span>
                        <?php endif; ?>
                        <span><a href="#" class="iqw-inline-action" data-id="<?php echo esc_attr( $entry->id ); ?>" data-action="archived"><?php _e( 'Archive', 'iqw' ); ?></a> | </span>
                        <span class="trash"><a href="#" class="iqw-inline-action" data-id="<?php echo esc_attr( $entry->id ); ?>" data-action="trash"><?php _e( 'Trash', 'iqw' ); ?></a></span>
                    </div>
                </td>
                <td><?php echo esc_html( $data['email'] ?? '-' ); ?></td>
                <td><?php echo esc_html( $data['phone'] ?? '-' ); ?></td>
                <td>
                    <span class="iqw-type-badge iqw-type-<?php echo esc_attr( $eform ? $eform->type : 'custom' ); ?>">
                        <?php echo esc_html( $eform ? $eform->title : 'Form #' . $entry->form_id ); ?>
                    </span>
                </td>
                <td><span class="iqw-status iqw-status-<?php echo esc_attr( $entry->status ); ?>"><?php echo esc_html( ucfirst( $entry->status ) ); ?></span></td>
                <td>
                    <span title="<?php echo esc_attr( date( 'Y-m-d H:i:s', strtotime( $entry->created_at ) ) ); ?>">
                        <?php echo esc_html( date( 'M j, Y', strtotime( $entry->created_at ) ) ); ?>
                        <br><small style="color:#999;"><?php echo esc_html( date( 'g:i A', strtotime( $entry->created_at ) ) ); ?></small>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ( $pages > 1 ) : ?>
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <span class="displaying-num"><?php printf( __( '%d items', 'iqw' ), $total ); ?></span>
            <span class="pagination-links">
                <?php
                $base_url = admin_url( 'admin.php?page=iqw-entries' );
                $params = array_filter( array(
                    'status'    => $current_status,
                    'form_id'   => $current_form,
                    'date_from' => $date_from,
                    'date_to'   => $date_to,
                    's'         => $search,
                ) );

                // First
                if ( $paged > 1 ) {
                    echo '<a class="first-page button" href="' . esc_url( add_query_arg( array_merge( $params, array( 'paged' => 1 ) ), $base_url ) ) . '">&laquo;</a> ';
                    echo '<a class="prev-page button" href="' . esc_url( add_query_arg( array_merge( $params, array( 'paged' => $paged - 1 ) ), $base_url ) ) . '">&lsaquo;</a> ';
                }

                echo '<span class="paging-input">' . $paged . ' / ' . $pages . '</span> ';

                if ( $paged < $pages ) {
                    echo '<a class="next-page button" href="' . esc_url( add_query_arg( array_merge( $params, array( 'paged' => $paged + 1 ) ), $base_url ) ) . '">&rsaquo;</a> ';
                    echo '<a class="last-page button" href="' . esc_url( add_query_arg( array_merge( $params, array( 'paged' => $pages ) ), $base_url ) ) . '">&raquo;</a>';
                }
                ?>
            </span>
        </div>
    </div>
    <?php endif; ?>

    <?php else : ?>
    <div class="iqw-empty-state">
        <span class="dashicons dashicons-email-alt" style="font-size:48px;color:#ccc;"></span>
        <h2><?php _e( 'No entries found', 'iqw' ); ?></h2>
        <p><?php echo $search ? __( 'Try a different search term.', 'iqw' ) : __( 'Entries will appear here when visitors submit your quote forms.', 'iqw' ); ?></p>
    </div>
    <?php endif; ?>
</div>
