<?php if ( ! defined( 'ABSPATH' ) ) exit;
$today_count = IQW_Submission::get_today_count();
$week_count  = IQW_Submission::get_week_count();
$daily_data  = IQW_Submission::get_daily_counts( 14 );
$chart_labels = wp_json_encode( array_column( $daily_data, 'label' ) );
$chart_values = wp_json_encode( array_column( $daily_data, 'count' ) );
?>
<div class="wrap iqw-wrap">
    <h1 class="iqw-page-title">
        <span class="dashicons dashicons-shield" style="color:#4CAF50;"></span>
        <?php _e( 'Insurance Quote Wizard', 'iqw' ); ?>
    </h1>

    <!-- Stat Cards -->
    <div class="iqw-dashboard-cards">
        <div class="iqw-card iqw-card-stat">
            <div class="iqw-card-icon" style="background:#e3f2fd;">
                <span class="dashicons dashicons-email-alt" style="color:#1565c0;"></span>
            </div>
            <div class="iqw-card-content">
                <div class="iqw-stat-number"><?php echo esc_html( $entry_counts['new'] ); ?></div>
                <div class="iqw-stat-label"><?php _e( 'New / Unread', 'iqw' ); ?></div>
            </div>
        </div>
        <div class="iqw-card iqw-card-stat">
            <div class="iqw-card-icon" style="background:#e8f5e9;">
                <span class="dashicons dashicons-chart-bar" style="color:#2e7d32;"></span>
            </div>
            <div class="iqw-card-content">
                <div class="iqw-stat-number"><?php echo esc_html( $today_count ); ?></div>
                <div class="iqw-stat-label"><?php _e( 'Today', 'iqw' ); ?></div>
            </div>
        </div>
        <div class="iqw-card iqw-card-stat">
            <div class="iqw-card-icon" style="background:#fff3e0;">
                <span class="dashicons dashicons-calendar" style="color:#e65100;"></span>
            </div>
            <div class="iqw-card-content">
                <div class="iqw-stat-number"><?php echo esc_html( $week_count ); ?></div>
                <div class="iqw-stat-label"><?php _e( 'This Week', 'iqw' ); ?></div>
            </div>
        </div>
        <div class="iqw-card iqw-card-stat">
            <div class="iqw-card-icon" style="background:#f3e5f5;">
                <span class="dashicons dashicons-chart-area" style="color:#7b1fa2;"></span>
            </div>
            <div class="iqw-card-content">
                <div class="iqw-stat-number"><?php echo esc_html( $entry_counts['all'] ); ?></div>
                <div class="iqw-stat-label"><?php _e( 'Total Entries', 'iqw' ); ?></div>
            </div>
        </div>
    </div>

    <!-- Chart + Recent -->
    <div class="iqw-dashboard-row">
        <div class="iqw-card iqw-card-wide">
            <h2 style="margin-top:0;"><?php _e( 'Entries - Last 14 Days', 'iqw' ); ?></h2>
            <div style="position:relative;height:200px;margin-bottom:16px;">
                <canvas id="iqw-chart" height="200"></canvas>
            </div>
            <script>
            (function(){
                var labels = <?php echo $chart_labels; ?>;
                var values = <?php echo $chart_values; ?>;
                var canvas = document.getElementById('iqw-chart');
                if(!canvas) return;
                var ctx = canvas.getContext('2d');
                var W = canvas.parentElement.offsetWidth;
                canvas.width = W; canvas.height = 200;
                var max = Math.max.apply(null, values) || 1;
                var padding = {top:20,right:20,bottom:30,left:40};
                var cw = W - padding.left - padding.right;
                var ch = 200 - padding.top - padding.bottom;
                var barW = Math.min(24, (cw / values.length) - 4);
                var gap = (cw - barW * values.length) / (values.length + 1);

                // Grid lines
                ctx.strokeStyle = '#f0f0f0'; ctx.lineWidth = 1;
                for(var i=0;i<=4;i++){
                    var y = padding.top + (ch / 4) * i;
                    ctx.beginPath(); ctx.moveTo(padding.left,y); ctx.lineTo(W-padding.right,y); ctx.stroke();
                }

                // Bars
                values.forEach(function(v,i){
                    var x = padding.left + gap + i * (barW + gap);
                    var h = (v / max) * ch;
                    var y = padding.top + ch - h;

                    // Bar
                    ctx.fillStyle = v > 0 ? '#4CAF50' : '#e0e0e0';
                    ctx.beginPath();
                    ctx.roundRect ? ctx.roundRect(x,y,barW,h,3) : ctx.fillRect(x,y,barW,h);
                    ctx.fill();

                    // Value on top
                    if(v > 0){
                        ctx.fillStyle = '#333'; ctx.font = '11px Arial'; ctx.textAlign = 'center';
                        ctx.fillText(v, x + barW/2, y - 4);
                    }

                    // Label
                    ctx.fillStyle = '#999'; ctx.font = '10px Arial'; ctx.textAlign = 'center';
                    ctx.fillText(labels[i], x + barW/2, padding.top + ch + 16);
                });
            })();
            </script>
        </div>
    </div>

    <!-- Recent Entries -->
    <div class="iqw-dashboard-row">
        <div class="iqw-card iqw-card-wide">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                <h2 style="margin:0;"><?php _e( 'Recent Entries', 'iqw' ); ?></h2>
                <a href="<?php echo admin_url( 'admin.php?page=iqw-entries' ); ?>" class="button button-small"><?php _e( 'View All', 'iqw' ); ?> &rarr;</a>
            </div>
            <?php if ( ! empty( $recent ) ) : ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th style="width:50px;">#</th>
                        <th><?php _e( 'Name', 'iqw' ); ?></th>
                        <th><?php _e( 'Email', 'iqw' ); ?></th>
                        <th><?php _e( 'Phone', 'iqw' ); ?></th>
                        <th style="width:130px;"><?php _e( 'Form', 'iqw' ); ?></th>
                        <th style="width:70px;"><?php _e( 'Status', 'iqw' ); ?></th>
                        <th style="width:130px;"><?php _e( 'Date', 'iqw' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $recent as $entry ) :
                        $d = is_string( $entry->data ) ? json_decode( $entry->data, true ) : $entry->data;
                        $f = IQW_Form_Builder::get_form( $entry->form_id );
                    ?>
                    <tr class="<?php echo esc_attr( $entry->status ) === 'new' ? 'iqw-entry-new' : ''; ?>">
                        <td><a href="<?php echo admin_url( 'admin.php?page=iqw-entries&action=view&id=' . $entry->id ); ?>">#<?php echo esc_attr( $entry->id ); ?></a></td>
                        <td><strong><?php echo esc_html( $d['full_name'] ?? trim( ($d['first_name'] ?? '') . ' ' . ($d['last_name'] ?? '') ) ?: '-' ); ?></strong></td>
                        <td><?php echo esc_html( $d['email'] ?? '-' ); ?></td>
                        <td><?php echo esc_html( $d['phone'] ?? '-' ); ?></td>
                        <td><span class="iqw-type-badge iqw-type-<?php echo esc_attr( $f ? $f->type : '' ); ?>"><?php echo esc_html( $f ? $f->title : '-' ); ?></span></td>
                        <td><span class="iqw-status iqw-status-<?php echo esc_attr( $entry->status ); ?>"><?php echo ucfirst( $entry->status ); ?></span></td>
                        <td><?php echo date( 'M j, g:i A', strtotime( $entry->created_at ) ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else : ?>
            <p class="iqw-empty"><?php _e( 'No entries yet.', 'iqw' ); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Links + Shortcodes -->
    <div class="iqw-dashboard-row" style="grid-template-columns:1fr 1fr;">
        <div class="iqw-card">
            <h2 style="margin-top:0;"><?php _e( 'Shortcodes', 'iqw' ); ?></h2>
            <?php
            $forms_list = IQW_Form_Builder::get_forms( array( 'status' => 'active' ) );
            if ( $forms_list ) :
                foreach ( $forms_list as $f ) : ?>
                <div style="margin-bottom:12px;">
                    <strong><?php echo esc_html( $f->title ); ?></strong><br>
                    <code class="iqw-shortcode-copy" style="cursor:pointer;" title="Click to copy">[insurance_quote_wizard id="<?php echo $f->id; ?>"]</code>
                </div>
                <?php endforeach;
            else : ?>
            <p><?php _e( 'No active forms.', 'iqw' ); ?></p>
            <?php endif; ?>
        </div>
        <div class="iqw-card">
            <h2 style="margin-top:0;"><?php _e( 'Forms Overview', 'iqw' ); ?></h2>
            <p><strong><?php echo esc_html( $form_counts['active'] ?? 0 ); ?></strong> <?php _e( 'Active', 'iqw' ); ?></p>
            <p><strong><?php echo esc_html( $form_counts['draft'] ?? 0 ); ?></strong> <?php _e( 'Draft', 'iqw' ); ?></p>
            <p><a href="<?php echo admin_url( 'admin.php?page=iqw-forms' ); ?>" class="button"><?php _e( 'Manage Forms', 'iqw' ); ?></a></p>
        </div>
    </div>
</div>
