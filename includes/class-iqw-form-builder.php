<?php
/**
 * Form Builder
 * Manages form configurations, field registry, and CRUD operations.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class IQW_Form_Builder {

    /**
     * Registered field types
     */
    private static $field_types = array();

    /**
     * Initialize default field types
     */
    public static function init() {
        self::register_default_fields();
    }

    /**
     * Register all default field types
     */
    private static function register_default_fields() {
        $fields = array(
            'text' => array(
                'label'       => __( 'Text Field', 'iqw' ),
                'icon'        => 'dashicons-editor-textcolor',
                'category'    => 'basic',
                'has_options'  => false,
            ),
            'email' => array(
                'label'       => __( 'Email', 'iqw' ),
                'icon'        => 'dashicons-email',
                'category'    => 'basic',
                'has_options'  => false,
                'validation'  => 'email',
            ),
            'phone' => array(
                'label'       => __( 'Phone', 'iqw' ),
                'icon'        => 'dashicons-phone',
                'category'    => 'basic',
                'has_options'  => false,
                'validation'  => 'phone',
            ),
            'number' => array(
                'label'       => __( 'Number', 'iqw' ),
                'icon'        => 'dashicons-calculator',
                'category'    => 'basic',
                'has_options'  => false,
            ),
            'currency' => array(
                'label'       => __( 'Currency', 'iqw' ),
                'icon'        => 'dashicons-money-alt',
                'category'    => 'basic',
                'has_options'  => false,
                'validation'  => 'currency',
            ),
            'date' => array(
                'label'       => __( 'Date Picker', 'iqw' ),
                'icon'        => 'dashicons-calendar-alt',
                'category'    => 'basic',
                'has_options'  => false,
            ),
            'select' => array(
                'label'       => __( 'Dropdown Select', 'iqw' ),
                'icon'        => 'dashicons-arrow-down-alt2',
                'category'    => 'choice',
                'has_options'  => true,
            ),
            'radio_cards' => array(
                'label'       => __( 'Radio Cards', 'iqw' ),
                'icon'        => 'dashicons-forms',
                'category'    => 'choice',
                'has_options'  => true,
                'description' => 'Large card-style radio buttons (Compare.com style)',
            ),
            'radio' => array(
                'label'       => __( 'Radio Buttons', 'iqw' ),
                'icon'        => 'dashicons-marker',
                'category'    => 'choice',
                'has_options'  => true,
            ),
            'checkbox_group' => array(
                'label'       => __( 'Checkbox Group', 'iqw' ),
                'icon'        => 'dashicons-yes-alt',
                'category'    => 'choice',
                'has_options'  => true,
            ),
            'textarea' => array(
                'label'       => __( 'Text Area', 'iqw' ),
                'icon'        => 'dashicons-editor-paragraph',
                'category'    => 'basic',
                'has_options'  => false,
            ),
            'address' => array(
                'label'       => __( 'Address', 'iqw' ),
                'icon'        => 'dashicons-location',
                'category'    => 'advanced',
                'has_options'  => false,
                'sub_fields'  => array( 'street', 'city', 'state', 'zip' ),
            ),
            'name' => array(
                'label'       => __( 'Full Name', 'iqw' ),
                'icon'        => 'dashicons-admin-users',
                'category'    => 'advanced',
                'has_options'  => false,
                'sub_fields'  => array( 'first_name', 'last_name' ),
            ),
            'hidden' => array(
                'label'       => __( 'Hidden Field', 'iqw' ),
                'icon'        => 'dashicons-hidden',
                'category'    => 'advanced',
                'has_options'  => false,
            ),
            'heading' => array(
                'label'       => __( 'Section Heading', 'iqw' ),
                'icon'        => 'dashicons-heading',
                'category'    => 'layout',
                'has_options'  => false,
                'is_input'    => false,
            ),
            'paragraph' => array(
                'label'       => __( 'Paragraph Text', 'iqw' ),
                'icon'        => 'dashicons-text',
                'category'    => 'layout',
                'has_options'  => false,
                'is_input'    => false,
            ),
            'file_upload' => array(
                'label'       => __( 'File Upload', 'iqw' ),
                'icon'        => 'dashicons-upload',
                'category'    => 'advanced',
                'has_options'  => false,
            ),
            'url' => array(
                'label'       => __( 'Website URL', 'iqw' ),
                'icon'        => 'dashicons-admin-links',
                'category'    => 'basic',
                'has_options'  => false,
            ),
            'consent' => array(
                'label'       => __( 'Consent / GDPR', 'iqw' ),
                'icon'        => 'dashicons-shield',
                'category'    => 'advanced',
                'has_options'  => false,
                'is_consent'  => true,
            ),
            'repeater' => array(
                'label'       => __( 'Repeater Group', 'iqw' ),
                'icon'        => 'dashicons-plus-alt2',
                'category'    => 'advanced',
                'has_options'  => false,
                'has_sub_fields' => true,
            ),
            'address' => array(
                'label'        => 'Address (Autocomplete)',
                'icon'         => 'dashicons-location',
                'category'     => 'advanced',
                'has_options'  => false,
            ),
            'payment' => array(
                'label'        => 'Payment (Stripe)',
                'icon'         => 'dashicons-cart',
                'category'     => 'advanced',
                'has_options'  => false,
            ),
            'calculated' => array(
                'label'        => 'Calculated Field',
                'icon'         => 'dashicons-calculator',
                'category'     => 'advanced',
                'has_options'  => false,
            ),
            'signature' => array(
                'label'        => 'Digital Signature',
                'icon'         => 'dashicons-edit-page',
                'category'     => 'advanced',
                'has_options'  => false,
            ),
        );

        foreach ( $fields as $type => $config ) {
            self::$field_types[ $type ] = $config;
        }
    }

    /**
     * Get all registered field types
     */
    public static function get_field_types() {
        if ( empty( self::$field_types ) ) {
            self::init();
        }
        return self::$field_types;
    }

    /**
     * Register a custom field type (extensibility)
     */
    public static function register_field_type( $type, $config ) {
        self::$field_types[ $type ] = $config;
    }

    // ================================================================
    // CRUD Operations
    // ================================================================

    /**
     * Get all forms
     */
    public static function get_forms( $args = array() ) {
        global $wpdb;
        $table = $wpdb->prefix . IQW_TABLE_FORMS;

        $defaults = array(
            'status'  => '',
            'type'    => '',
            'orderby' => 'created_at',
            'order'   => 'DESC',
            'limit'   => 50,
            'offset'  => 0,
        );
        $args = wp_parse_args( $args, $defaults );

        $where = array( '1=1' );
        $values = array();

        if ( ! empty( $args['status'] ) ) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }
        if ( ! empty( $args['type'] ) ) {
            $where[] = 'type = %s';
            $values[] = $args['type'];
        }

        $where_sql = implode( ' AND ', $where );
        $orderby = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );
        if ( ! $orderby ) {
            $orderby = 'created_at DESC';
        }

        $sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} LIMIT %d OFFSET %d";
        $values[] = $args['limit'];
        $values[] = $args['offset'];

        if ( count( $values ) > 2 ) {
            return $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
        } else {
            return $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$table} WHERE 1=1 ORDER BY {$orderby} LIMIT %d OFFSET %d",
                $args['limit'],
                $args['offset']
            ) );
        }
    }

    /**
     * Get single form by ID
     */
    public static function get_form( $id ) {
        global $wpdb;
        $table = $wpdb->prefix . IQW_TABLE_FORMS;

        $form = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d", $id
        ) );

        if ( $form ) {
            $form->config = json_decode( $form->config, true ) ?: array();
            $form->email_settings = json_decode( $form->email_settings, true ) ?: array();
        }

        return $form;
    }

    /**
     * Get single form by slug
     */
    public static function get_form_by_slug( $slug ) {
        global $wpdb;
        $table = $wpdb->prefix . IQW_TABLE_FORMS;

        $form = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE slug = %s AND status = 'active'", $slug
        ) );

        if ( $form ) {
            $form->config = json_decode( $form->config, true ) ?: array();
            $form->email_settings = json_decode( $form->email_settings, true ) ?: array();
        }

        return $form;
    }

    /**
     * Create a new form
     */
    public static function create_form( $data ) {
        global $wpdb;
        $table = $wpdb->prefix . IQW_TABLE_FORMS;

        // Validate config size (max 1MB)
        $config_json = is_string( $data['config'] ?? '' ) ? $data['config'] : wp_json_encode( $data['config'] ?? array() );
        if ( strlen( (string) $config_json ) > 1048576 ) {
            return new WP_Error( 'config_too_large', __( 'Form configuration exceeds 1MB limit.', 'iqw' ) );
        }

        $slug = sanitize_title( $data['title'] );
        if ( empty( $slug ) ) {
            $slug = 'form-' . wp_rand( 1000, 9999 );
        }

        // Ensure unique slug (retry up to 10 times)
        $base_slug = $slug;
        $attempt = 0;
        while ( $attempt < 10 ) {
            $existing = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE slug = %s", $slug
            ) );
            if ( $existing == 0 ) break;
            $attempt++;
            $slug = $base_slug . '-' . wp_rand( 100, 9999 );
        }

        $result = $wpdb->insert( $table, array(
            'title'          => sanitize_text_field( $data['title'] ),
            'slug'           => $slug,
            'type'           => sanitize_text_field( $data['type'] ?? 'custom' ),
            'config'         => wp_json_encode( $data['config'] ?? array() ),
            'email_settings' => wp_json_encode( $data['email_settings'] ?? array() ),
            'status'         => sanitize_text_field( $data['status'] ?? 'draft' ),
        ), array( '%s', '%s', '%s', '%s', '%s', '%s' ) );

        if ( $result === false ) {
            return new WP_Error( 'db_error', __( 'Failed to create form.', 'iqw' ) );
        }

        // Clear cache
        delete_transient( 'iqw_forms_cache' );

        return $wpdb->insert_id;
    }

    /**
     * Update a form
     */
    public static function update_form( $id, $data ) {
        global $wpdb;
        $table = $wpdb->prefix . IQW_TABLE_FORMS;

        $update = array();
        $format = array();

        if ( isset( $data['title'] ) ) {
            $update['title'] = sanitize_text_field( $data['title'] );
            $format[] = '%s';
        }
        if ( isset( $data['type'] ) ) {
            $update['type'] = sanitize_text_field( $data['type'] );
            $format[] = '%s';
        }
        if ( isset( $data['config'] ) ) {
            $update['config'] = is_string( $data['config'] ) ? $data['config'] : wp_json_encode( $data['config'] );
            $format[] = '%s';
        }
        if ( isset( $data['email_settings'] ) ) {
            $update['email_settings'] = is_string( $data['email_settings'] ) ? $data['email_settings'] : wp_json_encode( $data['email_settings'] );
            $format[] = '%s';
        }
        if ( isset( $data['status'] ) ) {
            $update['status'] = sanitize_text_field( $data['status'] );
            $format[] = '%s';
        }

        if ( empty( $update ) ) {
            return new WP_Error( 'no_data', __( 'No data to update.', 'iqw' ) );
        }

        $result = $wpdb->update( $table, $update, array( 'id' => $id ), $format, array( '%d' ) );

        // Clear cache
        delete_transient( 'iqw_forms_cache' );
        delete_transient( 'iqw_form_' . $id );

        return $result !== false;
    }

    /**
     * Delete a form
     */
    public static function delete_form( $id ) {
        global $wpdb;
        $table = $wpdb->prefix . IQW_TABLE_FORMS;

        $result = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

        // Clear cache
        delete_transient( 'iqw_forms_cache' );

        return $result !== false;
    }

    /**
     * Duplicate a form
     */
    public static function duplicate_form( $id ) {
        $form = self::get_form( $id );
        if ( ! $form ) {
            return new WP_Error( 'not_found', __( 'Form not found.', 'iqw' ) );
        }

        return self::create_form( array(
            'title'          => $form->title . ' (Copy)',
            'type'           => $form->type,
            'config'         => $form->config,
            'email_settings' => $form->email_settings,
            'status'         => 'draft',
        ) );
    }

    /**
     * Get form count by status
     */
    public static function get_form_counts() {
        global $wpdb;
        $table = $wpdb->prefix . IQW_TABLE_FORMS;

        $results = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$table} GROUP BY status"
        );

        $counts = array( 'all' => 0, 'active' => 0, 'draft' => 0, 'archived' => 0 );
        foreach ( $results as $row ) {
            $counts[ $row->status ] = (int) $row->count;
            $counts['all'] += (int) $row->count;
        }

        return $counts;
    }
}
