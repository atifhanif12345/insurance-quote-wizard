<?php
/**
 * Form Analytics
 * Tracks form views, starts, completions, and drop-off by step.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class IQW_Analytics {

    /**
     * Track form view (fires when form renders on page)
     */
    public static function track_view( $form_id ) {
        self::increment( $form_id, 'views' );
    }

    /**
     * Track form view via AJAX (works with page caching)
     */
    public static function ajax_track_view() {
        if ( ! check_ajax_referer( 'iqw_submit_nonce', 'iqw_nonce', false ) ) {
            wp_send_json_error( 'Invalid request.', 403 );
        }
        $form_id = absint( $_POST['form_id'] ?? 0 );
        if ( ! $form_id ) wp_send_json_error();
        self::track_view( $form_id );
        wp_send_json_success();
    }

    /**
     * Track form start (fires on first nextStep)
     */
    public static function ajax_track_start() {
        if ( ! check_ajax_referer( 'iqw_submit_nonce', 'iqw_nonce', false ) ) {
            wp_send_json_error( 'Invalid request.', 403 );
        }
        $form_id = absint( $_POST['form_id'] ?? 0 );
        $step = absint( $_POST['step'] ?? 0 );
        if ( ! $form_id ) wp_send_json_error();
        self::increment( $form_id, 'starts' );
        self::track_step( $form_id, $step );
        wp_send_json_success();
    }

    /**
     * Track step reached (for drop-off analysis)
     */
    public static function ajax_track_step() {
        if ( ! check_ajax_referer( 'iqw_submit_nonce', 'iqw_nonce', false ) ) {
            wp_send_json_error( 'Invalid request.', 403 );
        }
        $form_id = absint( $_POST['form_id'] ?? 0 );
        $step = absint( $_POST['step'] ?? 0 );
        if ( ! $form_id ) wp_send_json_error();
        self::track_step( $form_id, $step );
        wp_send_json_success();
    }

    /**
     * Track completion (fires after successful submit)
     */
    public static function track_completion( $form_id ) {
        self::increment( $form_id, 'completions' );
    }

    /**
     * Increment a counter for a form
     */
    private static function increment( $form_id, $type ) {
        $key = 'iqw_analytics_' . $form_id;
        $data = get_option( $key, array() );
        $today = date( 'Y-m-d' );

        if ( ! isset( $data[ $today ] ) ) {
            $data[ $today ] = array( 'views' => 0, 'starts' => 0, 'completions' => 0, 'steps' => array() );
        }
        $data[ $today ][ $type ] = ( $data[ $today ][ $type ] ?? 0 ) + 1;

        // Keep last 90 days
        $cutoff = date( 'Y-m-d', strtotime( '-90 days' ) );
        foreach ( array_keys( $data ) as $d ) {
            if ( $d < $cutoff ) unset( $data[ $d ] );
        }

        update_option( $key, $data, false );
    }

    /**
     * Track step reached
     */
    private static function track_step( $form_id, $step ) {
        $key = 'iqw_analytics_' . $form_id;
        $data = get_option( $key, array() );
        $today = date( 'Y-m-d' );

        if ( ! isset( $data[ $today ] ) ) {
            $data[ $today ] = array( 'views' => 0, 'starts' => 0, 'completions' => 0, 'steps' => array() );
        }
        if ( ! isset( $data[ $today ]['steps'][ $step ] ) ) {
            $data[ $today ]['steps'][ $step ] = 0;
        }
        $data[ $today ]['steps'][ $step ]++;

        update_option( $key, $data, false );
    }

    /**
     * Get analytics data for a form
     */
    public static function get_data( $form_id, $days = 30 ) {
        $key = 'iqw_analytics_' . $form_id;
        $data = get_option( $key, array() );
        $cutoff = date( 'Y-m-d', strtotime( "-{$days} days" ) );

        $totals = array( 'views' => 0, 'starts' => 0, 'completions' => 0, 'steps' => array() );
        $daily = array();

        foreach ( $data as $date => $day_data ) {
            if ( $date < $cutoff ) continue;
            $totals['views'] += $day_data['views'] ?? 0;
            $totals['starts'] += $day_data['starts'] ?? 0;
            $totals['completions'] += $day_data['completions'] ?? 0;

            foreach ( $day_data['steps'] ?? array() as $s => $c ) {
                $totals['steps'][ $s ] = ( $totals['steps'][ $s ] ?? 0 ) + $c;
            }

            $daily[ $date ] = $day_data;
        }

        $totals['conversion_rate'] = $totals['views'] > 0
            ? round( ( $totals['completions'] / $totals['views'] ) * 100, 1 )
            : 0;
        $totals['start_rate'] = $totals['views'] > 0
            ? round( ( $totals['starts'] / $totals['views'] ) * 100, 1 )
            : 0;
        $totals['completion_rate'] = $totals['starts'] > 0
            ? round( ( $totals['completions'] / $totals['starts'] ) * 100, 1 )
            : 0;

        return array( 'totals' => $totals, 'daily' => $daily );
    }

    /**
     * AJAX: Get analytics for dashboard
     */
    public static function ajax_get_analytics() {
        check_ajax_referer( 'iqw_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $form_id = absint( $_POST['form_id'] ?? 0 );
        $days = absint( $_POST['days'] ?? 30 );

        wp_send_json_success( self::get_data( $form_id, $days ) );
    }
}
