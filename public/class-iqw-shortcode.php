<?php
/**
 * Shortcode Handler
 * [insurance_quote_wizard id="1"]
 * [insurance_quote_wizard slug="auto-insurance"]
 * [insurance_quote_wizard id="1" popup="yes" button_text="Get a Quote"]
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class IQW_Shortcode {

    public function register() {
        add_shortcode( 'insurance_quote_wizard', array( $this, 'render' ) );
    }

    public function render( $atts ) {
        $atts = shortcode_atts( array(
            'id'             => 0,
            'slug'           => '',
            'theme'          => '',
            'popup'          => 'no',
            'button_text'    => __( 'Get a Free Quote', 'iqw' ),
            'button_class'   => '',
            // Popup customization
            'preset'         => '',          // "field_key:value,field_key2:value2"
            'popup_width'    => '',          // e.g. "600px" — overrides default 720px
            'popup_overlay'  => '',          // e.g. "rgba(0,0,0,0.8)"
            'close_on_esc'   => 'yes',       // yes/no
            'popup_position' => 'center',    // center | bottom-right | bottom-left | bottom-center
            // Confirmation / redirect
            'redirect_url'   => '',          // Override form-level redirect: redirect after submit
        ), $atts, 'insurance_quote_wizard' );

        $form_id = absint( $atts['id'] );

        if ( ! $form_id && ! empty( $atts['slug'] ) ) {
            $form = IQW_Form_Builder::get_form_by_slug( sanitize_title( $atts['slug'] ) );
            if ( $form ) {
                $form_id = $form->id;
            }
        }

        if ( ! $form_id ) {
            if ( current_user_can( 'manage_options' ) ) {
                return '<div class="iqw-error" style="padding:20px;background:#fff3cd;border:1px solid #ffc107;border-radius:4px;">Insurance Quote Wizard: Please specify a form ID or slug.</div>';
            }
            return '';
        }

        // Popup mode
        $is_popup = in_array( strtolower( $atts['popup'] ), array( 'yes', 'true', '1' ), true );

        if ( $is_popup ) {
            $btn_class = 'iqw-popup-trigger' . ( $atts['button_class'] ? ' ' . esc_attr( $atts['button_class'] ) : '' );
            $uid       = 'iqw-popup-' . $form_id . '-' . wp_rand( 1000, 9999 );

            // Parse preset: "insurance_type:auto,source:hero" → JSON object
            $preset_json = '';
            if ( ! empty( $atts['preset'] ) ) {
                $preset_arr = array();
                foreach ( explode( ',', $atts['preset'] ) as $pair ) {
                    $pair = trim( $pair );
                    // Support both "key:value" and "key=value"
                    if ( strpos( $pair, ':' ) !== false ) {
                        [ $k, $v ] = explode( ':', $pair, 2 );
                    } elseif ( strpos( $pair, '=' ) !== false ) {
                        [ $k, $v ] = explode( '=', $pair, 2 );
                    } else {
                        continue;
                    }
                    $preset_arr[ sanitize_key( trim( $k ) ) ] = sanitize_text_field( trim( $v ) );
                }
                if ( ! empty( $preset_arr ) ) {
                    $preset_json = wp_json_encode( $preset_arr );
                }
            }

            // Build data-* attributes for the trigger button
            $data_attrs  = ' data-iqw-popup="' . esc_attr( $uid ) . '"';
            $data_attrs .= ' data-iqw-form-id="' . esc_attr( $form_id ) . '"';
            if ( $preset_json )                  $data_attrs .= ' data-iqw-preset="' . esc_attr( $preset_json ) . '"';
            if ( $atts['popup_width'] )          $data_attrs .= ' data-iqw-width="' . esc_attr( $atts['popup_width'] ) . '"';
            if ( $atts['popup_overlay'] )        $data_attrs .= ' data-iqw-overlay="' . esc_attr( $atts['popup_overlay'] ) . '"';
            if ( $atts['popup_position'] && $atts['popup_position'] !== 'center' )
                                                 $data_attrs .= ' data-iqw-pos="' . esc_attr( $atts['popup_position'] ) . '"';
            $data_attrs .= ' data-iqw-esc="' . ( strtolower( $atts['close_on_esc'] ) === 'no' ? 'no' : 'yes' ) . '"';

            $form_html = IQW_Public::render_form( $form_id, $atts );

            $output  = '<a href="#" class="' . esc_attr( $btn_class ) . '"' . $data_attrs . '>' . esc_html( $atts['button_text'] ) . '</a>';
            $output .= '<template id="' . esc_attr( $uid ) . '">' . $form_html . '</template>';

            // One shared listener (attached once via flag on window)
            $output .= '<script>
(function(){
    if(window._iqwPopupListenerAttached) return;
    window._iqwPopupListenerAttached = true;

    document.addEventListener("click", function(e){
        var trigger = e.target.closest("[data-iqw-popup]");
        if(!trigger) return;
        e.preventDefault();

        var uid = trigger.getAttribute("data-iqw-popup");
        var tpl = document.getElementById(uid);
        if(!tpl) return;

        // Read customization from trigger data attributes
        var customWidth   = trigger.dataset.iqwWidth   || "";
        var customOverlay = trigger.dataset.iqwOverlay || "";
        var customPos     = trigger.dataset.iqwPos     || "";
        var closeOnEsc    = trigger.dataset.iqwEsc     !== "no";
        var presetData    = trigger.dataset.iqwPreset  || "";

        // Build overlay
        var overlay = document.createElement("div");
        overlay.className = "iqw-popup-overlay";
        if(customOverlay) overlay.style.background = customOverlay;
        if(customPos)     overlay.dataset.iqwPos = customPos;

        // Build container
        var container = document.createElement("div");
        container.className = "iqw-popup-container";
        if(customWidth) container.style.maxWidth = customWidth;

        // Close button (created & appended before innerHTML manipulation)
        var closeBtn = document.createElement("button");
        closeBtn.className = "iqw-popup-close";
        closeBtn.setAttribute("aria-label", "Close");
        closeBtn.innerHTML = "&times;";

        function closePopup(){
            // Always remove ESC listener regardless of how popup was closed
            if(_escHandler){ document.removeEventListener("keydown", _escHandler); _escHandler = null; }
            overlay.classList.add("closing");
            setTimeout(function(){ if(overlay.parentNode) overlay.parentNode.removeChild(overlay); }, 300);
        }
        closeBtn.addEventListener("click", closePopup);

        // Append form HTML safely (insertAdjacentHTML keeps closeBtn listener intact)
        container.appendChild(closeBtn);
        container.insertAdjacentHTML("beforeend", tpl.innerHTML);
        overlay.appendChild(container);

        // Click outside to close
        overlay.addEventListener("click", function(ev){ if(ev.target === overlay) closePopup(); });

        // ESC key to close — listener stored so it is removed on ALL close paths
        var _escHandler = null;
        if(closeOnEsc){
            _escHandler = function(ev){ if(ev.key === "Escape") closePopup(); };
            document.addEventListener("keydown", _escHandler);
        }

        document.body.appendChild(overlay);

        // Mark wizard container as popup + inject preset before init
        var wizContainer = overlay.querySelector(".iqw-wizard-container");
        if(wizContainer){
            wizContainer.dataset.iqwPopup = "true";
            if(presetData) wizContainer.dataset.preset = presetData;
        }

        // Init wizard (after paint so DOM is ready)
        setTimeout(function(){
            if(window.IQWWizard && wizContainer){
                // If this popup was auto-opened for a draft resume, pass the token
                var _urlParams = new URLSearchParams(window.location.search);
                var _resumeToken = _urlParams.get("iqw_resume") || "";
                var _resumeForm  = parseInt(_urlParams.get("iqw_form") || "0");
                var _thisFormId  = parseInt(wizContainer.dataset.formId || "0");
                var _opts = {isPopup: true};
                if(_resumeToken && _resumeForm && _resumeForm === _thisFormId){
                    _opts.resumeToken = _resumeToken;
                }
                new window.IQWWizard(wizContainer.id, wizContainer.dataset.formId, _opts);
            }
        }, 50);
    });

    // Auto-open this popup if ?iqw_resume=TOKEN&iqw_form=FORM_ID matches this popup's form
    (function(){
        var params = new URLSearchParams(window.location.search);
        var resumeToken = params.get("iqw_resume");
        var resumeForm  = parseInt(params.get("iqw_form") || "0");
        if(!resumeToken || !resumeForm) return;
        // Find trigger button for this form
        var triggers = document.querySelectorAll("[data-iqw-form-id=\""+resumeForm+"\"][data-iqw-popup]");
        if(!triggers.length) return;
        // Slight delay to let page finish rendering
        setTimeout(function(){ triggers[0].click(); }, 300);
    })();
})();
</script>';

            return $output;
        }

        return IQW_Public::render_form( $form_id, $atts );
    }
}
