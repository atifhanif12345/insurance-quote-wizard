/**
 * Insurance Quote Wizard - Visual Form Builder
 * Phase 5: Full drag-drop step/field management
 */
(function($){
    'use strict';

    var B = iqwBuilderData;
    var config = B.config || { settings:{}, steps:[] };
    var selectedField = null; // { stepIdx, fieldIdx }
    var dragData = null;
    var _keyCounter = 0; // monotonic counter — guarantees unique keys even within same millisecond

    // ================================================================
    // INIT
    // ================================================================
    function init() {
        renderSteps();
        bindTabs();
        bindTopbar();
        bindPaletteDrag();
        bindTemplates();
        bindJsonEditor();
        updateEmptyState();
        syncJsonEditor();
        renderRoutingRules();
        renderWebhooks();
        bindRoutingWebhookEvents();
        bindGoogleSheetsTest();

        // Confirmation type toggle
        $(document).on('change', '#iqw-setting-confirmation-type', function(){
            var v = $(this).val();
            $('#iqw-conf-page-row').toggle(v === 'page');
            $('#iqw-conf-redirect-row').toggle(v === 'redirect');
        });

        // Schedule enable toggle
        $(document).on('change', '#iqw-setting-schedule-enabled', function(){
            $('.iqw-schedule-row').toggle($(this).is(':checked'));
        });

        // Mailchimp test connection
        $(document).on('click', '#iqw-test-mc', function(){
            var $btn = $(this);
            var apiKey = $('#iqw_mailchimp_api_key').val();
            if(!apiKey){ alert('Enter API key first.'); return; }
            $btn.prop('disabled',true).text('Testing...');
            $.post(iqwAdmin.ajaxUrl, { action:'iqw_test_mailchimp', nonce:iqwAdmin.nonce, api_key:apiKey }, function(res){
                if(res.success && res.data.lists){
                    var html = '<select onchange="document.getElementById(\'iqw_mailchimp_list_id\').value=this.value;"><option>-- Select List --</option>';
                    res.data.lists.forEach(function(l){ html += '<option value="'+l.id+'">'+l.name+' ('+l.count+' subscribers)</option>'; });
                    html += '</select>';
                    $('#iqw-mc-lists').html('<span style="color:green;">✅ Connected!</span> ' + html);
                } else {
                    $('#iqw-mc-lists').html('<span style="color:red;">❌ ' + (res.data || 'Connection failed.') + '</span>');
                }
                $btn.prop('disabled',false).text('Test & Fetch Lists');
            }).fail(function(){ $btn.prop('disabled',false).text('Test & Fetch Lists'); });
        });
    }

    function bindGoogleSheetsTest() {
        $(document).off('click','#iqw-gs-test').on('click','#iqw-gs-test', function(){
            var $btn = $(this), $result = $('#iqw-gs-test-result');
            $btn.prop('disabled',true).text('Testing...');
            $result.text('').css('color','');
            $.post(iqwAdmin.ajaxUrl, {
                action: 'iqw_test_gsheets',
                nonce: iqwAdmin.nonce,
                spreadsheet_id: $('#iqw-gs-spreadsheet-id').val(),
                api_key: $('#iqw-gs-api-key').val(),
                sheet_name: $('#iqw-gs-sheet-name').val() || 'Sheet1'
            }, function(res){
                if(res.success){
                    $result.text('✓ ' + res.data).css('color','#27ae60');
                } else {
                    $result.text('✗ ' + (res.data||'Failed')).css('color','#e74c3c');
                }
                $btn.prop('disabled',false).text('🔗 Test Connection');
            }).fail(function(){
                $result.text('✗ Network error').css('color','#e74c3c');
                $btn.prop('disabled',false).text('🔗 Test Connection');
            });
        });
    }

    // ================================================================
    // TABS
    // ================================================================
    function bindTabs() {
        $(document).on('click', '.iqw-builder-tab', function(){
            var tab = $(this).data('tab');
            $('.iqw-builder-tab').removeClass('active');
            $(this).addClass('active');
            $('.iqw-builder-tab-content').removeClass('active');
            $('.iqw-builder-tab-content[data-tab="'+tab+'"]').addClass('active');
            if(tab==='json') syncJsonEditor();
        });
    }

    // ================================================================
    // TOP BAR: Save, Preview
    // ================================================================
    function bindTopbar() {
        // Save
        $(document).on('click','#iqw-save-form-btn', function(){
            var $btn = $(this);
            var title = $('#iqw-form-title').val().trim();

            // Validate title
            if(!title){
                alert('Please enter a form title.');
                $('#iqw-form-title').focus();
                return;
            }

            // Validate: at least one step
            if(!config.steps || !config.steps.length){
                alert('Please add at least one step before saving.');
                return;
            }

            // Validate: all field keys must be unique across all steps
            var _allKeys = [], _dupKeys = [];
            config.steps.forEach(function(step){
                (step.fields||[]).forEach(function(f){
                    if(!f.key) return;
                    if(_allKeys.indexOf(f.key) > -1){ if(_dupKeys.indexOf(f.key) === -1) _dupKeys.push(f.key); }
                    else _allKeys.push(f.key);
                });
            });
            if(_dupKeys.length){
                alert('Duplicate field keys detected:\n\n' + _dupKeys.join(', ') + '\n\nField keys must be unique. Edit each field and change its key.');
                return;
            }

            $btn.prop('disabled',true).text('Saving...');
            collectFormSettings();
            var emailSettings = collectEmailSettings();

            $.post(iqwAdmin.ajaxUrl, {
                action: 'iqw_save_form',
                nonce: iqwAdmin.nonce,
                form_id: B.formId,
                title: title,
                type: $('#iqw-form-type').val(),
                status: $('#iqw-form-status').val(),
                config: JSON.stringify(config),
                email_settings: JSON.stringify(emailSettings)
            }, function(res){
                if(res.success){
                    if(!B.formId && res.data.form_id){
                        B.formId = res.data.form_id;
                        $btn.data('form-id', B.formId);
                        window.history.replaceState(null,null, window.location.pathname+'?page=iqw-form-edit&id='+B.formId);
                    }
                    $btn.text('✓ Saved!').css('background','#27ae60');
                    setTimeout(function(){ $btn.text('Save').css('background','').prop('disabled',false); },1500);
                } else {
                    alert(res.data || 'Error saving.');
                    $btn.text('Save').prop('disabled',false);
                }
            }).fail(function(){
                alert('Network error. Please check your connection and try again.');
                $btn.text('Save').prop('disabled',false);
            });
        });

        // Preview
        $(document).on('click','#iqw-preview-btn', function(){
            if(!B.formId){
                alert('Save the form first to enable preview.');
                return;
            }
            collectFormSettings();
            // Build preview URL in iframe
            var previewUrl = B.siteUrl + '?iqw_preview=1&form_id=' + B.formId + '&t=' + Date.now();
            var currentMode = $('.iqw-preview-mode.active').data('mode') || 'desktop';
            var widthMap = { desktop: '100%', tablet: '768px', mobile: '375px' };
            var modal = $('<div class="iqw-preview-modal" style="position:fixed;inset:0;z-index:999999;background:rgba(0,0,0,0.7);display:flex;align-items:center;justify-content:center;padding:20px;">' +
                '<div class="iqw-preview-inner" style="background:#f0f0f0;border-radius:12px;overflow:hidden;width:90%;max-width:900px;max-height:90vh;display:flex;flex-direction:column;">' +
                '<div style="display:flex;justify-content:space-between;align-items:center;padding:10px 16px;background:#fff;border-bottom:1px solid #ddd;">' +
                '<div style="display:flex;gap:4px;">' +
                '<button class="button iqw-pm-btn' + (currentMode==='desktop' ? ' button-primary' : '') + '" data-w="100%"><span class="dashicons dashicons-desktop"></span></button>' +
                '<button class="button iqw-pm-btn' + (currentMode==='tablet' ? ' button-primary' : '') + '" data-w="768px"><span class="dashicons dashicons-tablet"></span></button>' +
                '<button class="button iqw-pm-btn' + (currentMode==='mobile' ? ' button-primary' : '') + '" data-w="375px"><span class="dashicons dashicons-smartphone"></span></button>' +
                '</div>' +
                '<span style="font-size:13px;color:#666;">Form Preview</span>' +
                '<button class="button iqw-preview-close">&times; Close</button>' +
                '</div>' +
                '<div style="flex:1;overflow:auto;display:flex;justify-content:center;padding:20px;background:#e0e0e0;">' +
                '<iframe src="' + previewUrl + '" style="width:' + widthMap[currentMode] + ';max-width:100%;height:600px;border:1px solid #ccc;border-radius:8px;background:#fff;transition:width 0.3s;"></iframe>' +
                '</div></div></div>');
            $('body').append(modal);
            modal.on('click','.iqw-preview-close', function(){ modal.remove(); });
            modal.on('click', function(e){ if($(e.target).hasClass('iqw-preview-modal')) modal.remove(); });
            modal.on('click','.iqw-pm-btn', function(){
                modal.find('.iqw-pm-btn').removeClass('button-primary');
                $(this).addClass('button-primary');
                modal.find('iframe').css('width', $(this).data('w'));
            });
        });

        // Topbar preview mode toggle (updates state for next preview open)
        $(document).on('click','.iqw-preview-mode', function(){
            $('.iqw-preview-mode').removeClass('active');
            $(this).addClass('active');
        });

        // Export current form from builder
        $(document).on('click','#iqw-export-current-form', function(){
            collectFormSettings();
            var exportData = {
                title: $('#iqw-form-title').val(),
                type: $('#iqw-form-type').val(),
                config: config,
                email_settings: collectEmailSettings(),
                status: $('#iqw-form-status').val(),
                exported_at: new Date().toISOString(),
                plugin_version: '1.6.0'
            };
            var slug = exportData.title.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/(^-|-$)/g,'');
            var blob = new Blob([JSON.stringify(exportData,null,2)], {type:'application/json'});
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url; a.download = 'iqw-form-' + slug + '.json';
            document.body.appendChild(a); a.click();
            document.body.removeChild(a); URL.revokeObjectURL(url);
        });
    }

    function collectFormSettings() {
        config.settings = config.settings || {};
        config.settings.theme = $('#iqw-setting-theme').val() || 'default';
        config.settings.form_mode = $('#iqw-setting-form-mode').val() || 'wizard';
        config.settings.review_step = $('#iqw-setting-review-step').is(':checked');
        config.settings.motivation_text = $('#iqw-setting-motivation').val();
        config.settings.success_message = $('#iqw-setting-success').val();
        config.settings.redirect_url = $('#iqw-setting-redirect').val();
        config.settings.confirmation_type = $('#iqw-setting-confirmation-type').val() || 'message';
        config.settings.confirmation_content = $('#iqw-setting-confirmation-content').val();
        config.settings.schedule_enabled = $('#iqw-setting-schedule-enabled').is(':checked');
        config.settings.schedule_start = $('#iqw-setting-schedule-start').val();
        config.settings.schedule_end = $('#iqw-setting-schedule-end').val();
        config.settings.schedule_max_submissions = parseInt($('#iqw-setting-schedule-max').val()) || 0;
        config.settings.schedule_closed_message = $('#iqw-setting-schedule-closed-msg').val();
        config.settings.custom_css = $('#iqw-setting-custom-css').val();
    }

    function collectEmailSettings() {
        return {
            admin_enabled: $('#iqw-email-admin-enabled').is(':checked'),
            admin_to: $('#iqw-email-admin-to').val(),
            admin_cc: $('#iqw-email-admin-cc').val(),
            admin_subject: $('#iqw-email-admin-subject').val(),
            customer_enabled: $('#iqw-email-cust-enabled').is(':checked'),
            customer_subject: $('#iqw-email-cust-subject').val(),
            routing_rules: iqwRoutingRules || [],
            webhooks: iqwWebhooks || [],
            google_sheets: {
                enabled: $('#iqw-gs-enabled').is(':checked'),
                spreadsheet_id: $('#iqw-gs-spreadsheet-id').val(),
                api_key: $('#iqw-gs-api-key').val(),
                sheet_name: $('#iqw-gs-sheet-name').val() || 'Sheet1'
            }
        };
    }

    // Routing rules + webhooks state
    var iqwRoutingRules = (B.emailSettings && B.emailSettings.routing_rules) || [];
    var iqwWebhooks = (B.emailSettings && B.emailSettings.webhooks) || [];

    function renderRoutingRules() {
        var $c = $('#iqw-routing-rules').empty();
        iqwRoutingRules.forEach(function(r, i) {
            var fieldOpts = '';
            (config.steps||[]).forEach(function(step){ (step.fields||[]).forEach(function(f){
                fieldOpts += '<option value="'+esc(f.key)+'"'+(r.field===f.key?' selected':'')+'>'+esc(f.label||f.key)+'</option>';
            }); });
            $c.append(
                '<div class="iqw-routing-rule" data-idx="'+i+'" style="border:1px solid #e0e0e0;border-radius:6px;padding:12px;margin-bottom:8px;background:#fafafa;">' +
                '<div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin-bottom:8px;">' +
                '<label><input type="checkbox" class="iqw-rr-enabled" '+(r.enabled?'checked':'')+' data-idx="'+i+'"> Active</label>' +
                '<span style="color:#999;">|</span> When ' +
                '<select class="iqw-rr-field" data-idx="'+i+'" style="max-width:150px;">'+fieldOpts+'</select>' +
                '<select class="iqw-rr-op" data-idx="'+i+'"><option value="is"'+(r.operator==='is'?' selected':'')+'>is</option><option value="is_not"'+(r.operator==='is_not'?' selected':'')+'>is not</option><option value="contains"'+(r.operator==='contains'?' selected':'')+'>contains</option></select>' +
                '<input type="text" class="iqw-rr-value" data-idx="'+i+'" value="'+esc(r.value||'')+'" placeholder="value" style="width:120px;">' +
                '<button class="iqw-rr-del" data-idx="'+i+'" style="color:#e74c3c;background:none;border:none;cursor:pointer;font-size:18px;">&times;</button>' +
                '</div>' +
                '<div style="display:flex;gap:6px;flex-wrap:wrap;">' +
                '<input type="email" class="iqw-rr-email" data-idx="'+i+'" value="'+esc(r.email||'')+'" placeholder="Send to email" style="width:200px;">' +
                '<input type="text" class="iqw-rr-cc" data-idx="'+i+'" value="'+esc(r.cc||'')+'" placeholder="CC (optional)" style="width:180px;">' +
                '<input type="text" class="iqw-rr-subject" data-idx="'+i+'" value="'+esc(r.subject||'')+'" placeholder="Custom subject (optional)" style="width:220px;">' +
                '</div></div>'
            );
        });
    }

    function renderWebhooks() {
        var $c = $('#iqw-webhooks-list').empty();
        iqwWebhooks.forEach(function(h, i) {
            var fieldOpts = '<option value="">Always fire</option>';
            (config.steps||[]).forEach(function(step){ (step.fields||[]).forEach(function(f){
                fieldOpts += '<option value="'+esc(f.key)+'"'+(h.condition_field===f.key?' selected':'')+'>'+esc(f.label||f.key)+'</option>';
            }); });
            $c.append(
                '<div class="iqw-webhook-row" data-idx="'+i+'" style="border:1px solid #e0e0e0;border-radius:6px;padding:12px;margin-bottom:8px;background:#fafafa;">' +
                '<div style="display:flex;gap:6px;align-items:center;margin-bottom:8px;">' +
                '<label><input type="checkbox" class="iqw-wh-enabled" '+(h.enabled?'checked':'')+' data-idx="'+i+'"> Active</label>' +
                '<input type="url" class="iqw-wh-url" data-idx="'+i+'" value="'+esc(h.url||'')+'" placeholder="https://hooks.zapier.com/..." style="flex:1;">' +
                '<button class="iqw-wh-del" data-idx="'+i+'" style="color:#e74c3c;background:none;border:none;cursor:pointer;font-size:18px;">&times;</button>' +
                '</div>' +
                '<div style="display:flex;gap:6px;align-items:center;font-size:13px;">' +
                '<span style="color:#666;">Only when:</span> ' +
                '<select class="iqw-wh-cond-field" data-idx="'+i+'" style="max-width:150px;">'+fieldOpts+'</select>' +
                '<input type="text" class="iqw-wh-cond-value" data-idx="'+i+'" value="'+esc(h.condition_value||'')+'" placeholder="equals value" style="width:120px;">' +
                '</div></div>'
            );
        });
    }

    function bindRoutingWebhookEvents() {
        // Routing rules
        $(document).off('click','#iqw-add-routing-rule').on('click','#iqw-add-routing-rule', function(){
            iqwRoutingRules.push({ enabled:true, field:'', operator:'is', value:'', email:'', cc:'', subject:'' });
            renderRoutingRules();
        });
        $(document).off('click','.iqw-rr-del').on('click','.iqw-rr-del', function(){
            iqwRoutingRules.splice($(this).data('idx'),1); renderRoutingRules();
        });
        $(document).off('input change','.iqw-rr-enabled,.iqw-rr-field,.iqw-rr-op,.iqw-rr-value,.iqw-rr-email,.iqw-rr-cc,.iqw-rr-subject');
        $(document).on('input change','.iqw-rr-enabled,.iqw-rr-field,.iqw-rr-op,.iqw-rr-value,.iqw-rr-email,.iqw-rr-cc,.iqw-rr-subject', function(){
            var i = $(this).data('idx'), r = iqwRoutingRules[i]; if(!r) return;
            if($(this).hasClass('iqw-rr-enabled')) r.enabled = $(this).is(':checked');
            else if($(this).hasClass('iqw-rr-field')) r.field = $(this).val();
            else if($(this).hasClass('iqw-rr-op')) r.operator = $(this).val();
            else if($(this).hasClass('iqw-rr-value')) r.value = $(this).val();
            else if($(this).hasClass('iqw-rr-email')) r.email = $(this).val();
            else if($(this).hasClass('iqw-rr-cc')) r.cc = $(this).val();
            else if($(this).hasClass('iqw-rr-subject')) r.subject = $(this).val();
        });

        // Webhooks
        $(document).off('click','#iqw-add-webhook').on('click','#iqw-add-webhook', function(){
            iqwWebhooks.push({ enabled:true, url:'', condition_field:'', condition_value:'', headers:[] });
            renderWebhooks();
        });
        $(document).off('click','.iqw-wh-del').on('click','.iqw-wh-del', function(){
            iqwWebhooks.splice($(this).data('idx'),1); renderWebhooks();
        });
        $(document).off('input change','.iqw-wh-enabled,.iqw-wh-url,.iqw-wh-cond-field,.iqw-wh-cond-value');
        $(document).on('input change','.iqw-wh-enabled,.iqw-wh-url,.iqw-wh-cond-field,.iqw-wh-cond-value', function(){
            var i = $(this).data('idx'), h = iqwWebhooks[i]; if(!h) return;
            if($(this).hasClass('iqw-wh-enabled')) h.enabled = $(this).is(':checked');
            else if($(this).hasClass('iqw-wh-url')) h.url = $(this).val();
            else if($(this).hasClass('iqw-wh-cond-field')) h.condition_field = $(this).val();
            else if($(this).hasClass('iqw-wh-cond-value')) h.condition_value = $(this).val();
        });
    }

    // ================================================================
    // RENDER STEPS
    // ================================================================
    function renderSteps() {
        var $list = $('#iqw-steps-list').empty();

        // ALWAYS bind Add Step - even when empty
        $('#iqw-add-step').off('click').on('click', function(){
            config.steps = config.steps || [];
            config.steps.push({ id:'step_'+(config.steps.length+1), title:'New Step', icon:'shield', fields:[] });
            renderSteps();
        });

        if(!config.steps || !config.steps.length){
            updateEmptyState();
            return;
        }

        config.steps.forEach(function(step, si){
            var $step = $('<div class="iqw-builder-step" data-step="'+si+'">');

            // Step header
            var $header = $('<div class="iqw-step-bar">');
            $header.append('<span class="iqw-step-drag dashicons dashicons-move" title="Drag to reorder"></span>');
            $header.append('<span class="iqw-step-num">Step '+(si+1)+'</span>');
            $header.append('<input type="text" class="iqw-step-title-input" value="'+esc(step.title||'')+'" placeholder="Step title..." data-step="'+si+'">');
            $header.append('<select class="iqw-step-icon-select" data-step="'+si+'">' +
                B.icons.map(function(ic){ return '<option value="'+ic+'"'+(step.icon===ic?' selected':'')+'>'+ic+'</option>'; }).join('') +
            '</select>');

            // Step conditions indicator
            if(step.conditions && step.conditions.rules && step.conditions.rules.length){
                $header.append('<span class="iqw-step-condition-badge" title="Has conditions">⚡'+step.conditions.rules.length+'</span>');
            }

            $header.append('<button class="iqw-step-cond-btn" data-step="'+si+'" title="Step Conditions">⚡</button>');
            $header.append('<button class="iqw-step-move-up" data-step="'+si+'" title="Move step up" '+(si===0?'disabled':'')+'>▲</button>');
            $header.append('<button class="iqw-step-move-dn" data-step="'+si+'" title="Move step down" '+(si===config.steps.length-1?'disabled':'')+'>▼</button>');
            $header.append('<button class="iqw-step-dup" data-step="'+si+'" title="Duplicate Step">⧉</button>');
            $header.append('<button class="iqw-step-del" data-step="'+si+'" title="Delete Step">&times;</button>');
            $step.append($header);

            // Fields list
            var $fields = $('<div class="iqw-step-fields" data-step="'+si+'">');
            (step.fields||[]).forEach(function(field, fi){
                $fields.append(renderFieldCard(field, si, fi));
            });

            // Drop zone
            $fields.append('<div class="iqw-field-dropzone" data-step="'+si+'"><span class="dashicons dashicons-plus-alt"></span> Drag field here or <a href="#" class="iqw-add-field-link" data-step="'+si+'">click to add</a></div>');

            $step.append($fields);
            $list.append($step);
        });

        updateEmptyState();
        bindStepEvents();
        bindFieldDragInCanvas();
    }

    function renderFieldCard(field, si, fi) {
        var typeInfo = B.fieldTypes[field.type] || {};
        var icon = typeInfo.icon || 'dashicons-admin-generic';
        var label = field.label || field.key || 'Untitled';
        var typeName = typeInfo.label || field.type;
        var req = field.required ? '<span class="iqw-field-req">*</span>' : '';
        var condBadge = (field.conditions && field.conditions.rules && field.conditions.rules.length) ? '<span class="iqw-field-cond-badge">⚡</span>' : '';
        var widthBadge = (field.width && field.width !== 'full') ? '<span class="iqw-field-width-badge">' + esc(field.width) + '</span>' : '';

        var html = '<div class="iqw-field-card" draggable="true" data-step="'+si+'" data-field="'+fi+'">';
        html += '<span class="iqw-field-card-drag dashicons dashicons-move"></span>';
        html += '<span class="dashicons '+esc(icon)+' iqw-field-card-icon"></span>';
        html += '<div class="iqw-field-card-info">';
        html += '<div class="iqw-field-card-label">'+esc(label)+req+condBadge+widthBadge+'</div>';
        html += '<div class="iqw-field-card-type">'+esc(typeName)+' &middot; <code>'+esc(field.key||'')+'</code></div>';
        html += '</div>';
        html += '<button class="iqw-field-card-edit" data-step="'+si+'" data-field="'+fi+'" title="Edit">✎</button>';
        html += '<button class="iqw-field-card-dup" data-step="'+si+'" data-field="'+fi+'" title="Duplicate">⧉</button>';
        html += '<button class="iqw-field-card-del" data-step="'+si+'" data-field="'+fi+'" title="Delete">&times;</button>';
        html += '</div>';
        return html;
    }

    // ================================================================
    // STEP EVENTS
    // ================================================================
    function bindStepEvents() {
        // Delete step — prevent deleting the last remaining step
        $(document).off('click','.iqw-step-del').on('click','.iqw-step-del', function(){
            if(config.steps.length <= 1){
                alert('A form must have at least one step. Add a new step before deleting this one.');
                return;
            }
            if(!confirm('Delete this step and all its fields? This cannot be undone.')) return;
            var si = $(this).data('step');
            config.steps.splice(si, 1);
            closeSettings();
            renderSteps();
        });

        // Duplicate step — deep clone with fresh unique keys for all fields
        $(document).off('click','.iqw-step-dup').on('click','.iqw-step-dup', function(){
            var si = $(this).data('step');
            var clone = JSON.parse(JSON.stringify(config.steps[si]));
            clone.id = 'step_' + Date.now().toString(36);
            clone.title = (clone.title || 'Step') + ' (copy)';
            // Re-key every field so there are no duplicate field keys
            var _dupCounter = 0;
            (clone.fields || []).forEach(function(f){
                f.key = f.type + '_' + Date.now().toString(36) + '_' + (++_dupCounter);
            });
            config.steps.splice(si + 1, 0, clone);
            renderSteps();
        });

        // Move step up
        $(document).off('click','.iqw-step-move-up').on('click','.iqw-step-move-up', function(){
            var si = $(this).data('step');
            if(si <= 0) return;
            var tmp = config.steps[si-1];
            config.steps[si-1] = config.steps[si];
            config.steps[si] = tmp;
            renderSteps();
        });

        // Move step down
        $(document).off('click','.iqw-step-move-dn').on('click','.iqw-step-move-dn', function(){
            var si = $(this).data('step');
            if(si >= config.steps.length - 1) return;
            var tmp = config.steps[si+1];
            config.steps[si+1] = config.steps[si];
            config.steps[si] = tmp;
            renderSteps();
        });

        // Step title change
        $(document).off('input','.iqw-step-title-input').on('input','.iqw-step-title-input', function(){
            config.steps[$(this).data('step')].title = $(this).val();
        });

        // Step icon change
        $(document).off('change','.iqw-step-icon-select').on('change','.iqw-step-icon-select', function(){
            config.steps[$(this).data('step')].icon = $(this).val();
        });

        // Edit field
        $(document).off('click','.iqw-field-card-edit').on('click','.iqw-field-card-edit', function(){
            var si=$(this).data('step'), fi=$(this).data('field');
            openFieldSettings(si, fi);
        });

        // Click field card to edit
        $(document).off('click','.iqw-field-card').on('click','.iqw-field-card', function(e){
            if($(e.target).closest('.iqw-field-card-del,.iqw-field-card-dup,.iqw-field-card-drag').length) return;
            var si=$(this).data('step'), fi=$(this).data('field');
            openFieldSettings(si, fi);
        });

        // Delete field
        $(document).off('click','.iqw-field-card-del').on('click','.iqw-field-card-del', function(e){
            e.stopPropagation();
            if(!confirm('Delete this field?')) return;
            var si=$(this).data('step'), fi=$(this).data('field');
            config.steps[si].fields.splice(fi, 1);
            closeSettings();
            renderSteps();
        });

        // Duplicate field
        $(document).off('click','.iqw-field-card-dup').on('click','.iqw-field-card-dup', function(e){
            e.stopPropagation();
            var si=$(this).data('step'), fi=$(this).data('field');
            var original = config.steps[si].fields[fi];
            var clone = JSON.parse(JSON.stringify(original));
            clone.key = clone.type + '_' + Date.now().toString(36) + '_' + (++_keyCounter);
            clone.label = (clone.label || 'Field') + ' (copy)';
            config.steps[si].fields.splice(fi + 1, 0, clone);
            renderSteps();
            openFieldSettings(si, fi + 1);
        });

        // Add field link
        $(document).off('click','.iqw-add-field-link').on('click','.iqw-add-field-link', function(e){
            e.preventDefault();
            var si=$(this).data('step');
            addFieldToStep(si, 'text');
        });

        // Step conditions
        $(document).off('click','.iqw-step-cond-btn').on('click','.iqw-step-cond-btn', function(){
            openStepConditions($(this).data('step'));
        });

        // Step drag reorder
        makeStepsSortable();
    }

    function makeStepsSortable(){
        var list = document.getElementById('iqw-steps-list');
        if(!list) return;
        var dragStep = null;
        $(list).children('.iqw-builder-step').each(function(){
            this.draggable = true;
            this.addEventListener('dragstart', function(e){
                dragStep = parseInt(this.dataset.step);
                e.dataTransfer.effectAllowed='move';
                this.classList.add('dragging');
            });
            this.addEventListener('dragend', function(){ this.classList.remove('dragging'); dragStep=null; });
            this.addEventListener('dragover', function(e){
                e.preventDefault();
                if(dragStep===null) return;
                this.classList.add('drag-over');
            });
            this.addEventListener('dragleave', function(){ this.classList.remove('drag-over'); });
            this.addEventListener('drop', function(e){
                e.preventDefault();
                this.classList.remove('drag-over');
                if(dragStep===null) return;
                var target = parseInt(this.dataset.step);
                if(dragStep===target) return;
                var moved = config.steps.splice(dragStep,1)[0];
                config.steps.splice(target,0,moved);
                renderSteps();
            });
        });
    }

    // ================================================================
    // FIELD DRAG FROM PALETTE
    // ================================================================
    function bindPaletteDrag() {
        // Palette search filter
        $('#iqw-palette-search').on('input', function(){
            var q = $(this).val().toLowerCase().trim();
            $('.iqw-palette-item').each(function(){
                var label = $(this).text().toLowerCase();
                var type = ($(this).data('field-type') || '').toLowerCase();
                var match = !q || label.indexOf(q) > -1 || type.indexOf(q) > -1;
                $(this).toggle(match);
            });
            // Hide empty group headers
            $('.iqw-palette-group').each(function(){
                var visible = $(this).find('.iqw-palette-item:visible').length;
                $(this).find('h4').toggle(visible > 0);
            });
        });

        $(document).on('dragstart', '.iqw-palette-item', function(e){
            dragData = { type:'new_field', fieldType: $(this).data('field-type') };
            e.originalEvent.dataTransfer.effectAllowed='copy';
            $(this).addClass('dragging');
        });
        $(document).on('dragend', '.iqw-palette-item', function(){ $(this).removeClass('dragging'); dragData=null; });

        // Drop on step fields area
        $(document).on('dragover', '.iqw-step-fields, .iqw-field-dropzone', function(e){ e.preventDefault(); $(this).closest('.iqw-step-fields').addClass('drag-over'); });
        $(document).on('dragleave', '.iqw-step-fields', function(){ $(this).removeClass('drag-over'); });
        $(document).on('drop', '.iqw-step-fields, .iqw-field-dropzone', function(e){
            e.preventDefault();
            $(this).closest('.iqw-step-fields').removeClass('drag-over');
            var targetStepIdx = parseInt($(this).closest('.iqw-step-fields').data('step'));
            if(dragData && dragData.type==='new_field'){
                addFieldToStep(targetStepIdx, dragData.fieldType);
                dragData = null;
            } else if(dragData && dragData.type==='move_field'){
                var srcStep = dragData.step, srcField = dragData.field;
                if(srcStep === targetStepIdx) return; // Same step handled by card-level handler

                // Determine insertion index by checking which card the cursor is nearest to
                var targetIdx = config.steps[targetStepIdx].fields.length; // default: end
                var cards = $(this).closest('.iqw-step-fields').find('.iqw-field-card');
                if(cards.length) {
                    var dropY = e.originalEvent.clientY;
                    cards.each(function(){
                        var rect = this.getBoundingClientRect();
                        var mid = rect.top + rect.height / 2;
                        if(dropY < mid){
                            targetIdx = parseInt(this.dataset.field);
                            return false; // break
                        }
                    });
                }
                var moved = config.steps[srcStep].fields.splice(srcField, 1)[0];
                config.steps[targetStepIdx].fields.splice(targetIdx, 0, moved);
                dragData = null;
                renderSteps();
            }
        });
    }

    function bindFieldDragInCanvas(){
        // Field reorder within steps
        $('.iqw-field-card').each(function(){
            this.addEventListener('dragstart', function(e){
                e.stopPropagation(); // prevent step-level drag from also firing
                dragData = { type:'move_field', step:parseInt(this.dataset.step), field:parseInt(this.dataset.field) };
                e.dataTransfer.effectAllowed='move';
                this.classList.add('dragging');
            });
            this.addEventListener('dragend', function(){ this.classList.remove('dragging'); dragData=null; });
        });

        // Drop on other field cards (reorder)
        $('.iqw-field-card').each(function(){
            this.addEventListener('dragover', function(e){ e.preventDefault(); this.classList.add('drag-over'); });
            this.addEventListener('dragleave', function(){ this.classList.remove('drag-over'); });
            this.addEventListener('drop', function(e){
                e.preventDefault(); e.stopPropagation();
                this.classList.remove('drag-over');
                if(!dragData || dragData.type!=='move_field') return;
                var targetStep = parseInt(this.dataset.step);
                var targetField = parseInt(this.dataset.field);
                var srcStep = dragData.step, srcField = dragData.field;

                var moved = config.steps[srcStep].fields.splice(srcField,1)[0];
                config.steps[targetStep].fields.splice(targetField,0,moved);
                renderSteps();
            });
        });
    }

    function addFieldToStep(stepIdx, fieldType) {
        var typeInfo = B.fieldTypes[fieldType] || {};
        var key = fieldType + '_' + Date.now().toString(36) + '_' + (++_keyCounter);
        var field = {
            key: key,
            type: fieldType,
            label: typeInfo.label || fieldType,
            required: false,
            placeholder: '',
            help_text: ''
        };

        if(typeInfo.has_options){
            field.options = [
                { value:'option_1', label:'Option 1' },
                { value:'option_2', label:'Option 2' }
            ];
        }

        if(fieldType === 'file_upload'){
            field.accept = '.pdf,.jpg,.jpeg,.png,.doc,.docx';
            field.max_size = 10;
        }

        if(fieldType === 'repeater'){
            field.sub_fields = [
                { key:'item_name', type:'text', label:'Name', required:true, placeholder:'' }
            ];
            field.max_items = 5;
            field.add_button_label = 'Add Another';
        }

        if(fieldType === 'consent'){
            field.label = 'I agree to the terms and conditions';
            field.required = true;
        }

        if(fieldType === 'calculated'){
            field.formula = '';
            field.decimal_places = 2;
            field.prefix = '$';
            field.suffix = '';
            field.label = 'Estimated Total';
        }

        if(fieldType === 'signature'){
            field.label = 'Signature';
            field.pen_color = '#000000';
            field.canvas_width = 400;
            field.canvas_height = 150;
            field.required = true;
        }

        config.steps[stepIdx].fields = config.steps[stepIdx].fields || [];
        config.steps[stepIdx].fields.push(field);
        renderSteps();
        openFieldSettings(stepIdx, config.steps[stepIdx].fields.length - 1);
    }

    // ================================================================
    // FIELD SETTINGS PANEL
    // ================================================================
    function openFieldSettings(si, fi) {
        selectedField = { stepIdx:si, fieldIdx:fi };
        var field = config.steps[si].fields[fi];
        if(!field) return;

        // Highlight active card
        $('.iqw-field-card').removeClass('active');
        $('.iqw-field-card[data-step="'+si+'"][data-field="'+fi+'"]').addClass('active');

        var $panel = $('#iqw-field-settings-panel').show();
        var $body = $('#iqw-settings-body').empty();
        var typeInfo = B.fieldTypes[field.type] || {};

        $('#iqw-settings-title').text(typeInfo.label || field.type);

        // Key
        $body.append(settingRow('Field Key', '<input type="text" class="iqw-setting-input" data-prop="key" value="'+esc(field.key||'')+'">'));

        // Label
        $body.append(settingRow('Label', '<input type="text" class="iqw-setting-input" data-prop="label" value="'+esc(field.label||'')+'">'));
        $body.append(settingRow('', '<label><input type="checkbox" class="iqw-setting-check" data-prop="hide_label" '+(field.hide_label?'checked':'')+'>Hide label on frontend</label>'));

        // Placeholder
        if(['text','email','phone','number','currency','textarea','date'].indexOf(field.type) > -1){
            $body.append(settingRow('Placeholder', '<input type="text" class="iqw-setting-input" data-prop="placeholder" value="'+esc(field.placeholder||'')+'">'));
        }

        // Required
        $body.append(settingRow('Required', '<label><input type="checkbox" class="iqw-setting-check" data-prop="required" '+(field.required?'checked':'')+'>Required field</label>'));

        // Field Width (Column Layout)
        if(['heading','paragraph'].indexOf(field.type) === -1){
            var w = field.width || 'full';
            $body.append(settingRow('Width', '<select class="iqw-setting-input" data-prop="width">' +
                '<option value="full"'+(w==='full'?' selected':'')+'>Full Width (100%)</option>' +
                '<option value="half"'+(w==='half'?' selected':'')+'>Half (50%)</option>' +
                '<option value="third"'+(w==='third'?' selected':'')+'>One Third (33%)</option>' +
                '<option value="two-third"'+(w==='two-third'?' selected':'')+'>Two Thirds (66%)</option>' +
                '<option value="quarter"'+(w==='quarter'?' selected':'')+'>Quarter (25%)</option>' +
            '</select>'));
        }

        // Validation
        if(['text','number','date'].indexOf(field.type) > -1){
            $body.append(settingRow('Validation', '<select class="iqw-setting-input" data-prop="validation">' +
                '<option value="">None</option><option value="zip"'+(field.validation==='zip'?' selected':'')+'>ZIP Code</option>' +
                '<option value="vin"'+(field.validation==='vin'?' selected':'')+'>VIN</option>' +
                '<option value="dob_driver"'+(field.validation==='dob_driver'?' selected':'')+'>DOB (Driver 15+)</option></select>'));
        }

        // Min/Max for numbers
        if(field.type==='number'||field.type==='currency'){
            $body.append(settingRow('Min Value', '<input type="number" class="iqw-setting-input" data-prop="min" value="'+(field.min||'')+'">'));
            $body.append(settingRow('Max Value', '<input type="number" class="iqw-setting-input" data-prop="max" value="'+(field.max||'')+'">'));
        }

        // Help Text (Feature 6)
        if(['heading','paragraph'].indexOf(field.type) === -1){
            $body.append(settingRow('Help Text', '<input type="text" class="iqw-setting-input" data-prop="help_text" value="'+esc(field.help_text||'')+'" placeholder="e.g., Enter your 10-digit phone number">'));
        }

        // Calculated field settings
        if(field.type==='calculated'){
            // Build field key list for formula reference
            var fieldKeys = [];
            config.steps.forEach(function(step){
                (step.fields||[]).forEach(function(f){
                    if(f.key !== field.key && f.type !== 'heading' && f.type !== 'paragraph'){
                        fieldKeys.push(f.key);
                    }
                });
            });
            var keyHint = fieldKeys.length ? '<br><span style="font-size:11px;color:#888;">Available: ' + fieldKeys.map(function(k){ return '{'+k+'}'; }).join(', ') + '</span>' : '';
            $body.append(settingRow('Formula', '<input type="text" class="iqw-setting-input" data-prop="formula" value="'+esc(field.formula||'')+'" placeholder="{age} * 2.5 + 50" style="font-family:monospace;">' + keyHint + '<br><span style="font-size:11px;color:#666;">Use {field_key} for values. Operators: + - * / ( )</span>'));
            $body.append(settingRow('Decimal Places', '<input type="number" class="iqw-setting-input" data-prop="decimal_places" value="'+(field.decimal_places ?? 2)+'" min="0" max="6">'));
            $body.append(settingRow('Prefix', '<input type="text" class="iqw-setting-input" data-prop="prefix" value="'+esc(field.prefix||'')+'" placeholder="$" style="width:60px;">'));
            $body.append(settingRow('Suffix', '<input type="text" class="iqw-setting-input" data-prop="suffix" value="'+esc(field.suffix||'')+'" placeholder="/mo" style="width:80px;">'));
        }

        // Signature field settings
        if(field.type==='signature'){
            $body.append(settingRow('Pen Color', '<input type="color" class="iqw-setting-input" data-prop="pen_color" value="'+(field.pen_color||'#000000')+'">'));
            $body.append(settingRow('Canvas Width', '<input type="number" class="iqw-setting-input" data-prop="canvas_width" value="'+(field.canvas_width||400)+'" min="200" max="800">'));
            $body.append(settingRow('Canvas Height', '<input type="number" class="iqw-setting-input" data-prop="canvas_height" value="'+(field.canvas_height||150)+'" min="80" max="400">'));
        }

        // File upload settings
        if(field.type==='file_upload'){
            $body.append(settingRow('Accepted Files', '<input type="text" class="iqw-setting-input" data-prop="accept" value="'+esc(field.accept||'.pdf,.jpg,.jpeg,.png')+'" placeholder=".pdf,.jpg,.png">'));
            $body.append(settingRow('Max Size (MB)', '<input type="number" class="iqw-setting-input" data-prop="max_size" value="'+(field.max_size||10)+'" min="1" max="50">'));
        }

        // Repeater settings
        if(field.type==='repeater'){
            $body.append(settingRow('Max Items', '<input type="number" class="iqw-setting-input" data-prop="max_items" value="'+(field.max_items||5)+'" min="1" max="20">'));
            $body.append(settingRow('Button Label', '<input type="text" class="iqw-setting-input" data-prop="add_button_label" value="'+esc(field.add_button_label||'Add Another')+'">'));

            // Sub-fields editor
            var sfHtml = '<div id="iqw-sub-fields-editor">';
            (field.sub_fields||[]).forEach(function(sf,sfi){
                sfHtml += '<div class="iqw-sub-field-row" data-idx="'+sfi+'" style="margin-bottom:10px;padding:8px;background:#f9f9f9;border:1px solid #eee;border-radius:4px;">';
                sfHtml += '<div style="display:flex;gap:4px;align-items:center;margin-bottom:4px;">';
                sfHtml += '<input type="text" class="iqw-sf-key" value="'+esc(sf.key||'')+'" placeholder="key" style="width:22%;">';
                sfHtml += '<input type="text" class="iqw-sf-label" value="'+esc(sf.label||'')+'" placeholder="label" style="width:28%;">';
                sfHtml += '<select class="iqw-sf-type" style="width:22%;"><option value="text"'+(sf.type==='text'?' selected':'')+'>Text</option><option value="email"'+(sf.type==='email'?' selected':'')+'>Email</option><option value="phone"'+(sf.type==='phone'?' selected':'')+'>Phone</option><option value="number"'+(sf.type==='number'?' selected':'')+'>Number</option><option value="select"'+(sf.type==='select'?' selected':'')+'>Select</option><option value="date"'+(sf.type==='date'?' selected':'')+'>Date</option></select>';
                sfHtml += '<label style="font-size:11px;white-space:nowrap;"><input type="checkbox" class="iqw-sf-required" data-idx="'+sfi+'"'+(sf.required?' checked':'')+' style="margin:0 2px;"> Req</label>';
                sfHtml += '<button class="iqw-sf-del" data-idx="'+sfi+'" style="color:#e74c3c;background:none;border:none;cursor:pointer;font-size:16px;">&times;</button>';
                sfHtml += '</div>';
                // Options editor for select sub-fields
                if(sf.type === 'select'){
                    sfHtml += '<div class="iqw-sf-options" data-idx="'+sfi+'" style="margin-left:12px;margin-top:4px;">';
                    sfHtml += '<span style="font-size:11px;color:#888;">Options (one per line: value|label):</span>';
                    var optStr = (sf.options||[]).map(function(o){ return o.value+'|'+o.label; }).join('\n');
                    sfHtml += '<textarea class="iqw-sf-opts-text" data-idx="'+sfi+'" style="width:100%;height:60px;font-size:12px;font-family:monospace;margin-top:2px;" placeholder="option_1|Option 1&#10;option_2|Option 2">'+esc(optStr)+'</textarea>';
                    sfHtml += '</div>';
                }
                sfHtml += '</div>';
            });
            sfHtml += '</div><button class="button button-small" id="iqw-add-sub-field" style="margin-top:6px;">+ Add Sub-Field</button>';
            $body.append(settingRow('Sub-Fields', sfHtml));
        }

        // Options for choice fields
        if(typeInfo.has_options){
            var optsHtml = '<div class="iqw-options-editor" id="iqw-options-editor">';
            (field.options||[]).forEach(function(opt,oi){
                optsHtml += optionRow(opt, oi);
            });
            optsHtml += '</div><button class="button button-small" id="iqw-add-option" style="margin-top:8px;">+ Add Option</button>';
            $body.append(settingRow('Options', optsHtml));
        }

        // Conditional Logic
        $body.append('<div class="iqw-setting-section"><h4>Conditional Logic</h4></div>');
        var condHtml = '<div id="iqw-field-conditions">';
        if(field.conditions && field.conditions.rules && field.conditions.rules.length){
            condHtml += '<p style="font-size:12px;color:#666;">Show this field only when:</p>';
            var fLogic = (field.conditions && field.conditions.logic) || 'and';
            condHtml += '<select class="iqw-field-cond-logic" style="margin-bottom:8px;"><option value="and"'+(fLogic==='and'?' selected':'')+'>ALL conditions (AND)</option><option value="or"'+(fLogic==='or'?' selected':'')+'>ANY condition (OR)</option></select>';
            field.conditions.rules.forEach(function(r,ri){
                condHtml += conditionRuleRow(r, ri, 'field');
            });
        }
        condHtml += '</div>';
        condHtml += '<button class="button button-small" id="iqw-add-field-condition">+ Add Condition</button>';
        $body.append(condHtml);

        bindSettingsEvents();
    }

    function settingRow(label, inputHtml) {
        return '<div class="iqw-setting-row"><label class="iqw-setting-label">'+label+'</label><div class="iqw-setting-control">'+inputHtml+'</div></div>';
    }

    function optionRow(opt, idx) {
        return '<div class="iqw-option-row" data-idx="'+idx+'">' +
            '<input type="text" class="iqw-opt-value" value="'+esc(opt.value||'')+'" placeholder="value" style="width:40%;">' +
            '<input type="text" class="iqw-opt-label" value="'+esc(opt.label||'')+'" placeholder="label" style="width:45%;">' +
            '<button class="iqw-opt-del" data-idx="'+idx+'" title="Remove">&times;</button></div>';
    }

    function conditionRuleRow(rule, idx, context) {
        // Build field options from all steps
        var fieldOpts = '';
        config.steps.forEach(function(step){
            (step.fields||[]).forEach(function(f){
                fieldOpts += '<option value="'+esc(f.key)+'"'+(rule.field===f.key?' selected':'')+'>'+esc(f.label||f.key)+'</option>';
            });
        });

        return '<div class="iqw-cond-rule" data-idx="'+idx+'" data-context="'+context+'">' +
            '<select class="iqw-cond-field">'+fieldOpts+'</select>' +
            '<select class="iqw-cond-operator">' +
                '<option value="is"'+(rule.operator==='is'?' selected':'')+'>is</option>' +
                '<option value="is_not"'+(rule.operator==='is_not'?' selected':'')+'>is not</option>' +
                '<option value="contains"'+(rule.operator==='contains'?' selected':'')+'>contains</option>' +
                '<option value="not_contains"'+(rule.operator==='not_contains'?' selected':'')+'>not contains</option>' +
                '<option value="starts_with"'+(rule.operator==='starts_with'?' selected':'')+'>starts with</option>' +
                '<option value="ends_with"'+(rule.operator==='ends_with'?' selected':'')+'>ends with</option>' +
                '<option value="empty"'+(rule.operator==='empty'?' selected':'')+'>is empty</option>' +
                '<option value="not_empty"'+(rule.operator==='not_empty'?' selected':'')+'>is not empty</option>' +
                '<option value="gt"'+(rule.operator==='gt'?' selected':'')+'>greater than</option>' +
                '<option value="gte"'+(rule.operator==='gte'?' selected':'')+'>greater or equal</option>' +
                '<option value="lt"'+(rule.operator==='lt'?' selected':'')+'>less than</option>' +
                '<option value="lte"'+(rule.operator==='lte'?' selected':'')+'>less or equal</option>' +
                '<option value="in"'+(rule.operator==='in'?' selected':'')+'>in list (a,b,c)</option>' +
                '<option value="not_in"'+(rule.operator==='not_in'?' selected':'')+'>not in list</option>' +
            '</select>' +
            '<input type="text" class="iqw-cond-value" value="'+esc(rule.value||'')+'" placeholder="value">' +
            '<button class="iqw-cond-del" data-idx="'+idx+'">&times;</button></div>';
    }

    function closeSettings() {
        selectedField = null;
        $('#iqw-field-settings-panel').hide();
        $('.iqw-field-card').removeClass('active');
    }

    function bindSettingsEvents() {
        // Close
        $('#iqw-settings-close').off('click').on('click', closeSettings);

        // Setting input changes
        $(document).off('input change','.iqw-setting-input,.iqw-setting-check');
        $(document).on('input change','.iqw-setting-input,.iqw-setting-check', function(){
            if(!selectedField) return;
            var field = config.steps[selectedField.stepIdx].fields[selectedField.fieldIdx];
            var prop = $(this).data('prop');
            if($(this).is(':checkbox')){
                field[prop] = $(this).is(':checked');
            } else {
                var val = $(this).val();
                if(prop==='min'||prop==='max') val = val ? parseFloat(val) : undefined;
                field[prop] = val;
            }
            // Update card label in real time
            if(prop==='label'||prop==='key'||prop==='required'){
                renderSteps();
                // Re-highlight
                $('.iqw-field-card[data-step="'+selectedField.stepIdx+'"][data-field="'+selectedField.fieldIdx+'"]').addClass('active');
            }
        });

        // Options management
        $(document).off('input','.iqw-opt-value,.iqw-opt-label');
        $(document).on('input','.iqw-opt-value,.iqw-opt-label', function(){
            if(!selectedField) return;
            var field = config.steps[selectedField.stepIdx].fields[selectedField.fieldIdx];
            var idx = $(this).closest('.iqw-option-row').data('idx');
            if($(this).hasClass('iqw-opt-value')) field.options[idx].value = $(this).val();
            else field.options[idx].label = $(this).val();
        });

        $(document).off('click','.iqw-opt-del').on('click','.iqw-opt-del', function(){
            if(!selectedField) return;
            var field = config.steps[selectedField.stepIdx].fields[selectedField.fieldIdx];
            field.options.splice($(this).data('idx'),1);
            openFieldSettings(selectedField.stepIdx, selectedField.fieldIdx);
        });

        $(document).off('click','#iqw-add-option').on('click','#iqw-add-option', function(){
            if(!selectedField) return;
            var field = config.steps[selectedField.stepIdx].fields[selectedField.fieldIdx];
            field.options = field.options || [];
            var n = field.options.length+1;
            field.options.push({ value:'option_'+n, label:'Option '+n });
            openFieldSettings(selectedField.stepIdx, selectedField.fieldIdx);
        });

        // Field conditions
        $(document).off('click','#iqw-add-field-condition').on('click','#iqw-add-field-condition', function(){
            if(!selectedField) return;
            var field = config.steps[selectedField.stepIdx].fields[selectedField.fieldIdx];
            field.conditions = field.conditions || { logic:'and', rules:[] };
            field.conditions.rules.push({ field:'', operator:'is', value:'' });
            openFieldSettings(selectedField.stepIdx, selectedField.fieldIdx);
        });

        // Field condition logic (AND/OR) change
        $(document).off('change','.iqw-field-cond-logic').on('change','.iqw-field-cond-logic', function(){
            if(!selectedField) return;
            var field = config.steps[selectedField.stepIdx].fields[selectedField.fieldIdx];
            if(field.conditions) field.conditions.logic = $(this).val();
        });

        bindConditionEvents('field');

        // Sub-field management (Repeater)
        $(document).off('input','.iqw-sf-key,.iqw-sf-label').on('input','.iqw-sf-key,.iqw-sf-label', function(){
            if(!selectedField) return;
            var field = config.steps[selectedField.stepIdx].fields[selectedField.fieldIdx];
            var idx = $(this).closest('.iqw-sub-field-row').data('idx');
            if(!field.sub_fields || !field.sub_fields[idx]) return;
            if($(this).hasClass('iqw-sf-key')) field.sub_fields[idx].key = $(this).val();
            else field.sub_fields[idx].label = $(this).val();
        });
        $(document).off('change','.iqw-sf-type').on('change','.iqw-sf-type', function(){
            if(!selectedField) return;
            var field = config.steps[selectedField.stepIdx].fields[selectedField.fieldIdx];
            var idx = $(this).closest('.iqw-sub-field-row').data('idx');
            if(field.sub_fields && field.sub_fields[idx]){
                field.sub_fields[idx].type = $(this).val();
                // Re-render to show/hide options editor
                openFieldSettings(selectedField.stepIdx, selectedField.fieldIdx);
            }
        });
        // Sub-field required checkbox
        $(document).off('change','.iqw-sf-required').on('change','.iqw-sf-required', function(){
            if(!selectedField) return;
            var field = config.steps[selectedField.stepIdx].fields[selectedField.fieldIdx];
            var idx = parseInt($(this).data('idx'));
            if(field.sub_fields && field.sub_fields[idx]) field.sub_fields[idx].required = $(this).is(':checked');
        });
        // Sub-field options textarea (for select type)
        $(document).off('input','.iqw-sf-opts-text').on('input','.iqw-sf-opts-text', function(){
            if(!selectedField) return;
            var field = config.steps[selectedField.stepIdx].fields[selectedField.fieldIdx];
            var idx = parseInt($(this).data('idx'));
            if(!field.sub_fields || !field.sub_fields[idx]) return;
            var lines = $(this).val().split('\n').filter(function(l){ return l.trim(); });
            field.sub_fields[idx].options = lines.map(function(line){
                var parts = line.split('|');
                return { value: (parts[0]||'').trim(), label: (parts[1]||parts[0]||'').trim() };
            });
        });
        $(document).off('click','.iqw-sf-del').on('click','.iqw-sf-del', function(){
            if(!selectedField) return;
            var field = config.steps[selectedField.stepIdx].fields[selectedField.fieldIdx];
            field.sub_fields.splice($(this).data('idx'),1);
            openFieldSettings(selectedField.stepIdx, selectedField.fieldIdx);
        });
        $(document).off('click','#iqw-add-sub-field').on('click','#iqw-add-sub-field', function(){
            if(!selectedField) return;
            var field = config.steps[selectedField.stepIdx].fields[selectedField.fieldIdx];
            field.sub_fields = field.sub_fields || [];
            var n = field.sub_fields.length+1;
            field.sub_fields.push({ key:'field_'+n, type:'text', label:'Field '+n, required:false, placeholder:'' });
            openFieldSettings(selectedField.stepIdx, selectedField.fieldIdx);
        });
    }

    // ================================================================
    // STEP CONDITIONS
    // ================================================================
    function openStepConditions(si) {
        var step = config.steps[si];
        step.conditions = step.conditions || { logic:'and', rules:[] };

        var html = '<div class="iqw-cond-modal"><div class="iqw-cond-modal-inner">';
        html += '<h3>Step Conditions: "'+esc(step.title)+'"</h3>';
        html += '<p style="font-size:13px;color:#666;">Show this step only when these conditions are met:</p>';
        html += '<select class="iqw-cond-logic-select" data-step="'+si+'"><option value="and"'+(step.conditions.logic==='and'?' selected':'')+'>ALL conditions (AND)</option><option value="or"'+(step.conditions.logic==='or'?' selected':'')+'>ANY condition (OR)</option></select>';
        html += '<div id="iqw-step-cond-rules">';
        step.conditions.rules.forEach(function(r,ri){
            html += conditionRuleRow(r, ri, 'step');
        });
        html += '</div>';
        html += '<button class="button button-small" id="iqw-add-step-cond-rule" data-step="'+si+'">+ Add Rule</button>';
        html += '<p style="font-size:12px;color:#e67e22;margin-top:12px;">⚠ Conditions auto-update as you type. Click <strong>Save Form</strong> at the top to persist changes.</p>';
        html += '<div style="margin-top:16px;text-align:right;">';
        html += '<button class="button button-primary" id="iqw-cond-modal-close">Done</button></div>';
        html += '</div></div>';

        $('body').append(html);

        // Bind
        $(document).on('click','#iqw-cond-modal-close', function(){ $('.iqw-cond-modal').remove(); renderSteps(); });
        $(document).on('change','.iqw-cond-logic-select', function(){ config.steps[$(this).data('step')].conditions.logic = $(this).val(); });
        $(document).on('click','#iqw-add-step-cond-rule', function(){
            var s = $(this).data('step');
            config.steps[s].conditions.rules.push({ field:'', operator:'is', value:'' });
            var r = config.steps[s].conditions.rules;
            $('#iqw-step-cond-rules').append(conditionRuleRow(r[r.length-1], r.length-1, 'step'));
        });

        bindConditionEvents('step');
    }

    function bindConditionEvents(context) {
        // Use namespaced events so field and step don't interfere
        var ns = '.iqwCond' + context;

        // Unbind only THIS context's events
        $(document).off('input' + ns + ' change' + ns);
        $(document).on('input' + ns + ' change' + ns, '.iqw-cond-field,.iqw-cond-operator,.iqw-cond-value', function(){
            var $rule = $(this).closest('.iqw-cond-rule');
            var idx = $rule.data('idx');
            var ctx = $rule.data('context');
            var rules;
            if(ctx==='field' && selectedField){
                rules = config.steps[selectedField.stepIdx].fields[selectedField.fieldIdx].conditions.rules;
            } else if(ctx==='step') {
                var si = parseInt($rule.closest('[data-step]').data('step') || 0);
                rules = config.steps[si] ? config.steps[si].conditions.rules : null;
            }
            if(!rules || !rules[idx]) return;
            if($(this).hasClass('iqw-cond-field')) rules[idx].field = $(this).val();
            else if($(this).hasClass('iqw-cond-operator')) rules[idx].operator = $(this).val();
            else rules[idx].value = $(this).val();
        });

        $(document).off('click' + ns, '.iqw-cond-del');
        $(document).on('click' + ns, '.iqw-cond-del', function(){
            var $rule = $(this).closest('.iqw-cond-rule');
            var idx = parseInt($rule.data('idx'));
            var ctx = $rule.data('context');
            if(ctx==='field' && selectedField){
                config.steps[selectedField.stepIdx].fields[selectedField.fieldIdx].conditions.rules.splice(idx,1);
                openFieldSettings(selectedField.stepIdx, selectedField.fieldIdx);
            } else if(ctx==='step') {
                var si = parseInt($rule.closest('[data-step]').data('step') || 0);
                if(config.steps[si] && config.steps[si].conditions && config.steps[si].conditions.rules){
                    config.steps[si].conditions.rules.splice(idx, 1);
                }
                // Re-open step conditions modal to refresh UI
                $('.iqw-cond-modal').remove();
                openStepConditions(si);
            }
        });
    }

    // ================================================================
    // TEMPLATES
    // ================================================================
    function bindTemplates() {
        $(document).on('click','.iqw-load-template', function(){
            if(config.steps.length && !confirm('Loading a template will replace all current steps. Continue?')) return;
            var tpl = $(this).data('template');
            var url = B.templates[tpl];
            if(!url) return;

            $.getJSON(url, function(data){
                config.steps = data.steps || [];
                config.settings = data.settings || config.settings;
                renderSteps();
                syncJsonEditor();
                // Update title if empty
                var titles = { auto:'Auto Insurance Quote', home:'Home Insurance Quote', generic:'General Insurance Quote' };
                if(!$('#iqw-form-title').val()) $('#iqw-form-title').val(titles[tpl]||'');
                if($('#iqw-form-type').val()==='custom') $('#iqw-form-type').val(tpl==='generic'?'generic':tpl);
            }).fail(function(){ alert('Failed to load template.'); });
        });
    }

    // ================================================================
    // JSON EDITOR
    // ================================================================
    function bindJsonEditor() {
        $('#iqw-json-apply').on('click', function(){
            try {
                var parsed = JSON.parse($('#iqw-json-editor').val());
                config = parsed;
                renderSteps();
                alert('JSON applied to builder!');
            } catch(e) { alert('Invalid JSON: '+e.message); }
        });

        $('#iqw-json-format').on('click', function(){
            try {
                var parsed = JSON.parse($('#iqw-json-editor').val());
                $('#iqw-json-editor').val(JSON.stringify(parsed, null, 2));
            } catch(e) { alert('Invalid JSON: '+e.message); }
        });
    }

    function syncJsonEditor() {
        collectFormSettings();
        $('#iqw-json-editor').val(JSON.stringify(config, null, 2));
    }

    // ================================================================
    // UTILITIES
    // ================================================================
    function updateEmptyState() {
        if(!config.steps || !config.steps.length){
            $('#iqw-canvas-empty').show();
            $('#iqw-steps-list').hide();
        } else {
            $('#iqw-canvas-empty').hide();
            $('#iqw-steps-list').show();
        }
    }

    function esc(s) {
        if(!s) return '';
        return $('<div>').text(s).html();
    }

    // ================================================================
    // INIT
    // ================================================================
    $(document).ready(init);

})(jQuery);
