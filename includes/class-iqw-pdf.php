<?php
/**
 * PDF Generator
 * Generates professional PDF documents from form entries.
 * Supports DomPDF (if installed) for real .pdf files,
 * falls back to print-optimized HTML.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class IQW_PDF {

    /**
     * Check if DomPDF is available
     */
    public static function has_dompdf() {
        // Check composer autoload (plugin's own vendor or site-wide)
        if ( class_exists( '\\Dompdf\\Dompdf' ) ) return true;

        // Check common plugin paths
        $paths = array(
            WP_PLUGIN_DIR . '/dompdf-wp/vendor/autoload.php',
            WP_CONTENT_DIR . '/vendor/autoload.php',
            IQW_PLUGIN_DIR . 'vendor/autoload.php',
        );
        foreach ( $paths as $path ) {
            if ( file_exists( $path ) ) {
                require_once $path;
                if ( class_exists( '\\Dompdf\\Dompdf' ) ) return true;
            }
        }
        return false;
    }

    /**
     * Generate output for an entry — real PDF if DomPDF available, HTML otherwise
     */
    public static function generate( $entry_id, $format = 'auto' ) {
        $entry = IQW_Submission::get_entry( $entry_id );
        if ( ! $entry ) return new WP_Error( 'not_found', 'Entry not found.' );

        $form = IQW_Form_Builder::get_form( $entry->form_id );
        $data = $entry->data;
        $company = get_option( 'iqw_company_name', get_bloginfo( 'name' ) );
        $phone = get_option( 'iqw_company_phone', '' );

        $html = self::build_pdf_html( $entry, $form, $data, $company, $phone, false );

        $upload_dir = wp_upload_dir();
        $pdf_dir = $upload_dir['basedir'] . '/iqw-pdfs';
        if ( ! file_exists( $pdf_dir ) ) {
            wp_mkdir_p( $pdf_dir );
            file_put_contents( $pdf_dir . '/.htaccess', 'deny from all' );
            file_put_contents( $pdf_dir . '/index.php', '<?php // Silence is golden' );
        }

        $random = wp_generate_password( 12, false );
        $base_name = 'Quote-Entry-' . $entry_id . '-' . $random;

        // Try real PDF first
        if ( ( $format === 'auto' || $format === 'pdf' ) && self::has_dompdf() ) {
            $pdf_path = $pdf_dir . '/' . $base_name . '.pdf';
            $pdf_html = self::build_pdf_html( $entry, $form, $data, $company, $phone, true );

            $dompdf = new \Dompdf\Dompdf( array(
                'defaultFont'          => 'Helvetica',
                'isRemoteEnabled'      => false,
                'isHtml5ParserEnabled' => true,
            ) );
            $dompdf->loadHtml( $pdf_html );
            $dompdf->setPaper( 'letter', 'portrait' );
            $dompdf->render();

            file_put_contents( $pdf_path, $dompdf->output() );

            return array(
                'type'     => 'pdf',
                'path'     => $pdf_path,
                'url'      => $upload_dir['baseurl'] . '/iqw-pdfs/' . $base_name . '.pdf',
                'filename' => $base_name . '.pdf',
                'mime'     => 'application/pdf',
            );
        }

        // Fallback: HTML
        $html_path = $pdf_dir . '/' . $base_name . '.html';
        $printable_html = self::build_pdf_html( $entry, $form, $data, $company, $phone, false );
        file_put_contents( $html_path, $printable_html );

        return array(
            'type'      => 'html',
            'path'      => $html_path,
            'html_path' => $html_path,
            'url'       => $upload_dir['baseurl'] . '/iqw-pdfs/' . $base_name . '.html',
            'filename'  => $base_name . '.html',
            'mime'      => 'text/html',
        );
    }

    /**
     * Generate PDF and attach to admin notification email
     */
    public static function attach_to_email( $entry_id ) {
        $result = self::generate( $entry_id );
        if ( is_wp_error( $result ) ) return array();

        return array( $result['path'] );
    }

    /**
     * Build professional PDF/HTML content
     */
    private static function build_pdf_html( $entry, $form, $data, $company, $phone, $is_pdf = false ) {
        $form_title = $form ? $form->title : 'Quote Request';
        $config = $form ? $form->config : null;
        $name = $data['full_name'] ?? trim( ($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '') ) ?: 'Customer';
        $date = date( 'F j, Y g:i A', strtotime( $entry->created_at ) );
        $primary = get_option( 'iqw_primary_color', '#4CAF50' );

        // Build structured fields table
        $fields_html = '';
        if ( $config && ! empty( $config['steps'] ) ) {
            foreach ( $config['steps'] as $step ) {
                if ( empty( $step['fields'] ) ) continue;
                $has_data = false;
                foreach ( $step['fields'] as $f ) {
                    $k = $f['key'] ?? '';
                    if ( ! empty( $data[ $k ] ) && ( $f['type'] ?? '' ) !== 'heading' && ( $f['type'] ?? '' ) !== 'paragraph' ) { $has_data = true; break; }
                }
                if ( ! $has_data ) continue;

                $fields_html .= '<tr><td colspan="2" style="background:' . esc_attr( $primary ) . ';color:#fff;padding:10px 16px;font-weight:bold;font-size:13px;text-transform:uppercase;">' . esc_html( $step['title'] ?? 'Information' ) . '</td></tr>';

                $alt = false;
                foreach ( $step['fields'] as $field ) {
                    $key = $field['key'] ?? '';
                    $type = $field['type'] ?? '';
                    if ( $type === 'heading' || $type === 'paragraph' ) continue;
                    $value = $data[ $key ] ?? '';
                    if ( is_array( $value ) ) $value = implode( ', ', $value );
                    if ( $value === '' || $value === null ) continue;

                    // Signature: show as embedded image in PDF
                    if ( $type === 'signature' && strpos( $value, 'data:image' ) === 0 ) {
                        $bg = $alt ? '#f8f8f8' : '#ffffff';
                        $label = $field['label'] ?? 'Signature';
                        $fields_html .= '<tr style="background:' . $bg . ';">';
                        $fields_html .= '<td style="padding:8px 16px;border-bottom:1px solid #eee;font-weight:600;width:40%;font-size:13px;color:#555;vertical-align:top;">' . esc_html( $label ) . '</td>';
                        $fields_html .= '<td style="padding:8px 16px;border-bottom:1px solid #eee;"><img src="' . esc_attr( $value ) . '" style="max-width:250px;border:1px solid #ddd;border-radius:4px;" alt="Signature"></td>';
                        $fields_html .= '</tr>';
                        $alt = !$alt;
                        continue;
                    }

                    // Resolve option labels
                    if ( ! empty( $field['options'] ) ) {
                        foreach ( $field['options'] as $opt ) {
                            if ( $opt['value'] === $value ) { $value = $opt['label']; break; }
                        }
                    }
                    $bg = $alt ? '#f8f8f8' : '#ffffff';
                    $label = $field['label'] ?? ucwords( str_replace( '_', ' ', $key ) );
                    $fields_html .= '<tr style="background:' . $bg . ';">';
                    $fields_html .= '<td style="padding:8px 16px;border-bottom:1px solid #eee;font-weight:600;width:40%;font-size:13px;color:#555;">' . esc_html( $label ) . '</td>';
                    $fields_html .= '<td style="padding:8px 16px;border-bottom:1px solid #eee;font-size:13px;color:#333;">' . esc_html( $value ) . '</td>';
                    $fields_html .= '</tr>';
                    $alt = !$alt;
                }
            }
        } else {
            foreach ( $data as $key => $value ) {
                if ( is_array( $value ) ) $value = implode( ', ', $value );
                if ( $value === '' ) continue;
                if ( strpos( $value, 'data:image' ) === 0 ) {
                    $fields_html .= '<tr><td style="padding:8px 16px;border-bottom:1px solid #eee;font-weight:600;width:40%;">' . esc_html( ucwords( str_replace( '_', ' ', $key ) ) ) . '</td>';
                    $fields_html .= '<td style="padding:8px 16px;border-bottom:1px solid #eee;"><img src="' . esc_attr( $value ) . '" style="max-width:250px;" alt="Signature"></td></tr>';
                } else {
                    $fields_html .= '<tr><td style="padding:8px 16px;border-bottom:1px solid #eee;font-weight:600;width:40%;">' . esc_html( ucwords( str_replace( '_', ' ', $key ) ) ) . '</td>';
                    $fields_html .= '<td style="padding:8px 16px;border-bottom:1px solid #eee;">' . esc_html( $value ) . '</td></tr>';
                }
            }
        }

        // Print button only for HTML mode
        $print_btn = $is_pdf ? '' : '<button class="print-btn" onclick="window.print()">Print / Save as PDF</button>';

        return '<!DOCTYPE html><html><head><meta charset="UTF-8">
<title>' . esc_html( $form_title ) . ' - Entry #' . $entry->id . '</title>
<style>
@page { size: letter; margin: 0.75in; }
@media print { body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } .print-btn { display: none; } }
body { font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #333; margin: 0; padding: 20px; }
.header { background: ' . esc_attr( $primary ) . '; color: #fff; padding: 24px 30px; border-radius: 8px 8px 0 0; }
.header h1 { margin: 0; font-size: 22px; }
.header p { margin: 6px 0 0; opacity: 0.85; font-size: 13px; }
.meta { background: #f8f9fa; padding: 12px 30px; font-size: 12px; color: #666; border-bottom: 1px solid #eee; }
table { width: 100%; border-collapse: collapse; }
.footer { text-align: center; font-size: 11px; color: #999; margin-top: 30px; padding-top: 16px; border-top: 1px solid #eee; }
.print-btn { position: fixed; top: 10px; right: 10px; padding: 10px 20px; background: ' . esc_attr( $primary ) . '; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: bold; z-index: 999; }
.print-btn:hover { opacity: 0.9; }
</style></head><body>
' . $print_btn . '
<div style="max-width:700px;margin:0 auto;border:1px solid #e0e0e0;border-radius:8px;overflow:hidden;">
<div class="header">
<h1>' . esc_html( $form_title ) . '</h1>
<p>Entry #' . $entry->id . ' &bull; ' . esc_html( $name ) . '</p>
</div>
<div class="meta">
<strong>Date:</strong> ' . esc_html( $date ) . ' &nbsp;&bull;&nbsp;
<strong>Status:</strong> ' . esc_html( ucfirst( $entry->status ) ) . '
</div>
<table>' . $fields_html . '</table>
</div>
<div class="footer">
' . esc_html( $company ) . ( $phone ? ' &bull; ' . esc_html( $phone ) : '' ) . '<br>
Generated on ' . esc_html( date( 'F j, Y' ) ) . ' by Insurance Quote Wizard
</div>
</body></html>';
    }

    /**
     * AJAX handler: Generate and serve
     */
    public static function ajax_generate() {
        check_ajax_referer( 'iqw_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $entry_id = absint( $_POST['entry_id'] ?? 0 );
        if ( ! $entry_id ) wp_send_json_error( 'Missing entry ID.' );

        $result = self::generate( $entry_id );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( array(
            'html_url' => wp_nonce_url(
                admin_url( 'admin.php?iqw_pdf_entry=' . $entry_id ),
                'iqw_pdf_nonce'
            ),
            'type'     => $result['type'],
            'filename' => $result['filename'],
        ) );
    }

    /**
     * Serve printable summary / PDF directly
     */
    public static function handle_download() {
        if ( empty( $_GET['iqw_pdf_entry'] ) || ! current_user_can( 'manage_options' ) ) return;
        if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'iqw_pdf_nonce' ) ) return;

        $entry_id = absint( $_GET['iqw_pdf_entry'] );
        $result = self::generate( $entry_id );
        if ( is_wp_error( $result ) ) {
            wp_die( $result->get_error_message() );
        }

        header( 'Content-Type: ' . $result['mime'] );
        if ( $result['type'] === 'pdf' ) {
            header( 'Content-Disposition: inline; filename="' . $result['filename'] . '"' );
        } else {
            header( 'Content-Disposition: inline; filename="' . $result['filename'] . '"' );
        }
        readfile( $result['path'] );
        @unlink( $result['path'] );
        exit;
    }

    /**
     * Get download URL
     */
    public static function get_download_url( $entry_id ) {
        return wp_nonce_url(
            admin_url( 'admin-ajax.php?action=iqw_serve_print&iqw_pdf_entry=' . $entry_id ),
            'iqw_pdf_nonce'
        );
    }
}
