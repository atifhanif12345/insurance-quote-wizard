<?php
/**
 * REST API
 * All plugin API endpoints.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class IQW_Rest_API {

    private $namespace = 'iqw/v1';

    /**
     * Register all routes
     */
    public function register_routes() {
        // Public: Form submission
        register_rest_route( $this->namespace, '/submit', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'submit_form' ),
            'permission_callback' => '__return_true',
        ) );

        // Public: Get form config (for rendering)
        register_rest_route( $this->namespace, '/forms/(?P<id>\d+)/config', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_form_config' ),
            'permission_callback' => '__return_true',
        ) );

        // Admin: Forms CRUD
        register_rest_route( $this->namespace, '/forms', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_forms' ),
                'permission_callback' => array( $this, 'admin_permission' ),
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'create_form' ),
                'permission_callback' => array( $this, 'admin_permission' ),
            ),
        ) );

        register_rest_route( $this->namespace, '/forms/(?P<id>\d+)', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_form' ),
                'permission_callback' => array( $this, 'admin_permission' ),
            ),
            array(
                'methods'             => 'PUT,PATCH',
                'callback'            => array( $this, 'update_form' ),
                'permission_callback' => array( $this, 'admin_permission' ),
            ),
            array(
                'methods'             => 'DELETE',
                'callback'            => array( $this, 'delete_form' ),
                'permission_callback' => array( $this, 'admin_permission' ),
            ),
        ) );

        // Admin: Entries CRUD
        register_rest_route( $this->namespace, '/entries', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_entries' ),
            'permission_callback' => array( $this, 'admin_permission' ),
        ) );

        register_rest_route( $this->namespace, '/entries/(?P<id>\d+)', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_entry' ),
                'permission_callback' => array( $this, 'admin_permission' ),
            ),
            array(
                'methods'             => 'PUT,PATCH',
                'callback'            => array( $this, 'update_entry' ),
                'permission_callback' => array( $this, 'admin_permission' ),
            ),
            array(
                'methods'             => 'DELETE',
                'callback'            => array( $this, 'delete_entry' ),
                'permission_callback' => array( $this, 'admin_permission' ),
            ),
        ) );

        // Admin: Export
        register_rest_route( $this->namespace, '/export', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'export_entries' ),
            'permission_callback' => array( $this, 'admin_permission' ),
        ) );
    }

    /**
     * Admin permission check
     */
    public function admin_permission() {
        return current_user_can( 'manage_options' );
    }

    // ================================================================
    // Public endpoints
    // ================================================================

    public function submit_form( $request ) {
        $submission = new IQW_Submission();
        return rest_ensure_response( $submission->handle_rest_submit( $request ) );
    }

    public function get_form_config( $request ) {
        $form = IQW_Form_Builder::get_form( $request['id'] );
        if ( ! $form || $form->status !== 'active' ) {
            return new WP_Error( 'not_found', 'Form not found.', array( 'status' => 404 ) );
        }

        return rest_ensure_response( array(
            'id'     => $form->id,
            'title'  => $form->title,
            'type'   => $form->type,
            'config' => $form->config,
        ) );
    }

    // ================================================================
    // Admin endpoints
    // ================================================================

    public function get_forms( $request ) {
        $forms = IQW_Form_Builder::get_forms( array(
            'status' => $request->get_param( 'status' ) ?? '',
            'type'   => $request->get_param( 'type' ) ?? '',
        ) );

        return rest_ensure_response( $forms );
    }

    public function get_form( $request ) {
        $form = IQW_Form_Builder::get_form( $request['id'] );
        if ( ! $form ) {
            return new WP_Error( 'not_found', 'Form not found.', array( 'status' => 404 ) );
        }
        return rest_ensure_response( $form );
    }

    public function create_form( $request ) {
        $result = IQW_Form_Builder::create_form( array(
            'title'          => $request->get_param( 'title' ),
            'type'           => $request->get_param( 'type' ),
            'config'         => $request->get_param( 'config' ),
            'email_settings' => $request->get_param( 'email_settings' ),
            'status'         => $request->get_param( 'status' ) ?? 'draft',
        ) );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( array( 'id' => $result, 'message' => 'Form created.' ) );
    }

    public function update_form( $request ) {
        $result = IQW_Form_Builder::update_form( $request['id'], $request->get_params() );
        if ( is_wp_error( $result ) ) return $result;

        return rest_ensure_response( array( 'success' => true, 'message' => 'Form updated.' ) );
    }

    public function delete_form( $request ) {
        $result = IQW_Form_Builder::delete_form( $request['id'] );
        return rest_ensure_response( array( 'success' => $result ) );
    }

    public function get_entries( $request ) {
        $entries = IQW_Submission::get_entries( array(
            'form_id' => $request->get_param( 'form_id' ) ?? 0,
            'status'  => $request->get_param( 'status' ) ?? '',
            'search'  => $request->get_param( 'search' ) ?? '',
            'limit'   => $request->get_param( 'per_page' ) ?? 20,
            'offset'  => $request->get_param( 'offset' ) ?? 0,
        ) );

        return rest_ensure_response( $entries );
    }

    public function get_entry( $request ) {
        $entry = IQW_Submission::get_entry( $request['id'] );
        if ( ! $entry ) {
            return new WP_Error( 'not_found', 'Entry not found.', array( 'status' => 404 ) );
        }
        return rest_ensure_response( $entry );
    }

    public function update_entry( $request ) {
        $status = $request->get_param( 'status' );
        if ( $status ) {
            IQW_Submission::update_entry_status( $request['id'], $status );
        }
        return rest_ensure_response( array( 'success' => true ) );
    }

    public function delete_entry( $request ) {
        $result = IQW_Submission::delete_entry( $request['id'] );
        return rest_ensure_response( array( 'success' => $result ) );
    }

    public function export_entries( $request ) {
        $export = new IQW_Export();
        return $export->handle_export( $request );
    }
}
