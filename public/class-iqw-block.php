<?php
/**
 * Gutenberg Block
 * Registers the Insurance Quote Wizard block for the block editor.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class IQW_Block {

    public function register() {
        add_action( 'init', array( $this, 'register_block' ) );
        add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
    }

    /**
     * Register block type
     */
    public function register_block() {
        if ( ! function_exists( 'register_block_type' ) ) return;

        register_block_type( 'iqw/quote-wizard', array(
            'attributes'      => array(
                'formId' => array(
                    'type'    => 'number',
                    'default' => 0,
                ),
            ),
            'render_callback' => array( $this, 'render_block' ),
        ) );
    }

    /**
     * Render block on frontend
     */
    public function render_block( $attributes ) {
        $form_id = absint( $attributes['formId'] ?? 0 );

        if ( ! $form_id ) {
            if ( current_user_can( 'manage_options' ) ) {
                return '<div class="iqw-error" style="padding:20px;background:#fff3cd;border:1px solid #ffc107;border-radius:8px;color:#856404;">Insurance Quote Wizard: Please select a form in the block settings.</div>';
            }
            return '';
        }

        return IQW_Public::render_form( $form_id );
    }

    /**
     * Enqueue block editor script
     */
    public function enqueue_editor_assets() {
        // Get forms for the dropdown
        $forms = IQW_Form_Builder::get_forms( array( 'status' => 'active' ) );
        $form_options = array(
            array( 'value' => 0, 'label' => __( '— Select a form —', 'iqw' ) ),
        );
        foreach ( $forms as $form ) {
            $form_options[] = array(
                'value' => (int) $form->id,
                'label' => $form->title . ' (' . ucfirst( $form->type ) . ')',
            );
        }

        wp_enqueue_script(
            'iqw-block-editor',
            IQW_PLUGIN_URL . 'admin/js/iqw-block.js',
            array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render' ),
            IQW_VERSION,
            true
        );

        wp_localize_script( 'iqw-block-editor', 'iqwBlockData', array(
            'forms' => $form_options,
        ) );
    }
}
