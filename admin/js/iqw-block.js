/**
 * Insurance Quote Wizard - Gutenberg Block
 */
(function() {
    var el = wp.element.createElement;
    var registerBlockType = wp.blocks.registerBlockType;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var PanelBody = wp.components.PanelBody;
    var SelectControl = wp.components.SelectControl;
    var ServerSideRender = wp.serverSideRender;
    var Placeholder = wp.components.Placeholder;

    var forms = (window.iqwBlockData && window.iqwBlockData.forms) || [];

    registerBlockType('iqw/quote-wizard', {
        title: 'Insurance Quote Wizard',
        description: 'Display an insurance quote form.',
        icon: 'shield',
        category: 'widgets',
        keywords: ['insurance', 'quote', 'form', 'wizard'],
        attributes: {
            formId: { type: 'number', default: 0 }
        },

        edit: function(props) {
            var formId = props.attributes.formId;

            var inspectorControls = el(InspectorControls, {},
                el(PanelBody, { title: 'Form Settings', initialOpen: true },
                    el(SelectControl, {
                        label: 'Select Form',
                        value: formId,
                        options: forms.map(function(f) {
                            return { value: f.value, label: f.label };
                        }),
                        onChange: function(val) {
                            props.setAttributes({ formId: parseInt(val) || 0 });
                        }
                    })
                )
            );

            if (!formId) {
                return el('div', {},
                    inspectorControls,
                    el(Placeholder, {
                        icon: 'shield',
                        label: 'Insurance Quote Wizard',
                        instructions: 'Select a form from the block settings panel on the right.',
                    },
                        el(SelectControl, {
                            value: formId,
                            options: forms.map(function(f) {
                                return { value: f.value, label: f.label };
                            }),
                            onChange: function(val) {
                                props.setAttributes({ formId: parseInt(val) || 0 });
                            }
                        })
                    )
                );
            }

            return el('div', {},
                inspectorControls,
                el(ServerSideRender, {
                    block: 'iqw/quote-wizard',
                    attributes: props.attributes,
                })
            );
        },

        save: function() {
            return null; // Server-side rendered
        }
    });
})();
