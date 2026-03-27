/**
 * Insurance Quote Wizard - Admin JS
 */
(function($) {
    'use strict';

    // ================================================================
    // Shortcode copy to clipboard
    // ================================================================
    $(document).on('click', '.iqw-shortcode-copy', function() {
        const text = $(this).text();
        navigator.clipboard.writeText(text).then(() => {
            const $el = $(this);
            const orig = $el.text();
            $el.text('Copied!');
            setTimeout(() => $el.text(orig), 1500);
        });
    });

    // ================================================================
    // Form actions
    // ================================================================
    $(document).on('click', '.iqw-duplicate-form', function(e) {
        e.preventDefault();
        const formId = $(this).data('id');

        $.post(iqwAdmin.ajaxUrl, {
            action: 'iqw_duplicate_form',
            nonce: iqwAdmin.nonce,
            form_id: formId
        }, function(res) {
            if (res.success) {
                location.reload();
            } else {
                alert(res.data || iqwAdmin.strings.error);
            }
        }).fail(function() {
            alert('Network error. Please try again.');
        });
    });

    $(document).on('click', '.iqw-delete-form', function(e) {
        e.preventDefault();
        if (!confirm(iqwAdmin.strings.confirmDelete)) return;

        const formId = $(this).data('id');
        $.post(iqwAdmin.ajaxUrl, {
            action: 'iqw_delete_form',
            nonce: iqwAdmin.nonce,
            form_id: formId
        }, function(res) {
            if (res.success) location.reload();
        });
    });

    // ================================================================
    // Form config JSON save (Phase 1 interim)
    // ================================================================
    $(document).on('click', '#iqw-save-form-json', function() {
        const formId = $(this).data('form-id');
        const config = $('#iqw-form-config').val();
        const emailConfig = $('#iqw-email-config').val();

        // Validate JSON
        try { JSON.parse(config); } catch(e) { alert('Invalid form config JSON: ' + e.message); return; }
        try { JSON.parse(emailConfig); } catch(e) { alert('Invalid email config JSON: ' + e.message); return; }

        $.post(iqwAdmin.ajaxUrl, {
            action: 'iqw_save_form',
            nonce: iqwAdmin.nonce,
            form_id: formId,
            config: config,
            email_settings: emailConfig
        }, function(res) {
            if (res.success) {
                alert(iqwAdmin.strings.saved);
            } else {
                alert(res.data || iqwAdmin.strings.error);
            }
        });
    });

    // ================================================================
    // Entry actions
    // ================================================================
    $(document).on('click', '.iqw-entry-action', function(e) {
        e.preventDefault();
        const id = $(this).data('id');
        const status = $(this).data('status');

        if (status === 'trash' && !confirm(iqwAdmin.strings.confirmDelete)) return;

        $.post(iqwAdmin.ajaxUrl, {
            action: 'iqw_bulk_entry_action',
            nonce: iqwAdmin.nonce,
            bulk_action: status,
            entry_ids: [id]
        }, function(res) {
            if (res.success) location.reload();
        });
    });

    // Select all checkbox
    $('#iqw-select-all').on('change', function() {
        $('.iqw-entry-cb').prop('checked', $(this).prop('checked'));
    });

    // Bulk apply
    $('#iqw-bulk-apply').on('click', function() {
        const action = $('#iqw-bulk-action').val();
        if (!action) return;

        const ids = [];
        $('.iqw-entry-cb:checked').each(function() { ids.push($(this).val()); });
        if (!ids.length) { alert('Select entries first.'); return; }

        if (action === 'delete' && !confirm(iqwAdmin.strings.confirmDelete)) return;

        $.post(iqwAdmin.ajaxUrl, {
            action: 'iqw_bulk_entry_action',
            nonce: iqwAdmin.nonce,
            bulk_action: action,
            entry_ids: ids
        }, function(res) {
            if (res.success) location.reload();
        });
    });

    // Form filter + date range
    $('#iqw-filter-apply').on('click', function() {
        var url = new URL(window.location.href);
        var formId = $('#iqw-form-filter').val();
        var dateFrom = $('#iqw-date-from').val();
        var dateTo = $('#iqw-date-to').val();
        if (formId) url.searchParams.set('form_id', formId); else url.searchParams.delete('form_id');
        if (dateFrom) url.searchParams.set('date_from', dateFrom); else url.searchParams.delete('date_from');
        if (dateTo) url.searchParams.set('date_to', dateTo); else url.searchParams.delete('date_to');
        url.searchParams.delete('paged');
        window.location.href = url.toString();
    });

    // Star toggle
    $(document).on('click', '.iqw-star-toggle', function(e) {
        e.preventDefault();
        var $el = $(this), id = $el.data('id');
        var newStatus = $el.hasClass('starred') ? 'read' : 'starred';
        $.post(iqwAdmin.ajaxUrl, {
            action: 'iqw_bulk_entry_action', nonce: iqwAdmin.nonce,
            bulk_action: newStatus, entry_ids: [id]
        }, function(res) {
            if (res.success) {
                $el.toggleClass('starred');
                $el.find('.dashicons').toggleClass('dashicons-star-empty dashicons-star-filled');
            }
        });
    });

    // Inline row actions
    $(document).on('click', '.iqw-inline-action', function(e) {
        e.preventDefault();
        var id = $(this).data('id'), action = $(this).data('action');
        if (action === 'trash' && !confirm('Move to trash?')) return;
        $.post(iqwAdmin.ajaxUrl, {
            action: 'iqw_bulk_entry_action', nonce: iqwAdmin.nonce,
            bulk_action: action, entry_ids: [id]
        }, function(res) {
            if (res.success) $('#iqw-row-' + id).fadeOut(300, function(){ $(this).remove(); });
        });
    });

    // Notes - add
    $(document).on('click', '#iqw-save-note', function() {
        var entryId = $(this).data('entry-id');
        var note = $('#iqw-new-note').val().trim();
        if (!note) return;
        var $btn = $(this).prop('disabled', true).text('Saving...');
        $.post(iqwAdmin.ajaxUrl, {
            action: 'iqw_add_note', nonce: iqwAdmin.nonce,
            entry_id: entryId, note: note
        }, function(res) {
            if (res.success) { $('#iqw-notes-list').html(res.data.html); $('#iqw-new-note').val(''); }
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-edit" style="vertical-align:middle;margin-top:-2px;"></span> Add Note');
        });
    });

    // Notes - delete
    $(document).on('click', '.iqw-delete-note', function(e) {
        e.preventDefault();
        if (!confirm('Delete this note?')) return;
        var noteId = $(this).data('note-id'), $note = $(this).closest('.iqw-note');
        $.post(iqwAdmin.ajaxUrl, {
            action: 'iqw_delete_note', nonce: iqwAdmin.nonce, note_id: noteId
        }, function(res) { if (res.success) $note.slideUp(200, function(){ $(this).remove(); }); });
    });

    // Ctrl+Enter to save note
    $('#iqw-new-note').on('keydown', function(e) {
        if (e.key === 'Enter' && e.ctrlKey) { e.preventDefault(); $('#iqw-save-note').click(); }
    });

    // Resend email
    $(document).on('click', '.iqw-resend-email', function() {
        var $btn = $(this);
        var entryId = $btn.data('id');
        var type = $btn.data('type');

        if (!confirm('Resend ' + type + ' email for this entry?')) return;

        $btn.prop('disabled', true).text('Sending...');

        $.post(iqwAdmin.ajaxUrl, {
            action: 'iqw_resend_email',
            nonce: iqwAdmin.nonce,
            entry_id: entryId,
            email_type: type
        }, function(res) {
            if (res.success) {
                $btn.text('✓ Sent!').css('color', '#27ae60');
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                alert(res.data || 'Failed to resend.');
                $btn.prop('disabled', false).text('Resend ' + type.charAt(0).toUpperCase() + type.slice(1));
            }
        });
    });

    // ================================================================
    // Form Import / Export
    // ================================================================

    // Helper: download JSON file
    function downloadJSON(data, filename) {
        var blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    // Export single form
    $(document).on('click', '.iqw-export-form', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        var title = $(this).data('title') || 'form';
        var formData = window.iqwFormsExportData && window.iqwFormsExportData[id];
        if (!formData) { alert('Form data not available.'); return; }

        var slug = title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
        downloadJSON(formData, 'iqw-form-' + slug + '.json');
    });

    // Export all forms
    $(document).on('click', '#iqw-export-all-forms', function() {
        var data = window.iqwFormsExportData;
        if (!data || !Object.keys(data).length) { alert('No forms to export.'); return; }

        // Wrap in an array for multi-form import
        var exportArr = [];
        for (var id in data) {
            exportArr.push(data[id]);
        }

        var dateStr = new Date().toISOString().slice(0, 10);
        downloadJSON(exportArr, 'iqw-all-forms-' + dateStr + '.json');
    });

    // Import form - trigger file input
    $(document).on('click', '#iqw-import-form-btn, #iqw-import-form-btn-empty', function() {
        $('#iqw-import-file').click();
    });

    // Import form - handle file selection
    $(document).on('change', '#iqw-import-file', function() {
        var file = this.files[0];
        if (!file) return;

        if (!file.name.endsWith('.json')) {
            alert('Please select a .json file exported from Insurance Quote Wizard.');
            return;
        }

        var reader = new FileReader();
        reader.onload = function(e) {
            try {
                var data = JSON.parse(e.target.result);
            } catch(err) {
                alert('Invalid JSON file: ' + err.message);
                return;
            }

            // Normalize: if single form object, wrap in array
            var forms = Array.isArray(data) ? data : [data];

            if (!forms.length || !forms[0].config) {
                alert('This file does not contain valid form data.');
                return;
            }

            var count = forms.length;
            if (!confirm('Import ' + count + ' form(s)?\n\n' + forms.map(function(f){ return '• ' + (f.title || 'Untitled'); }).join('\n'))) {
                return;
            }

            // Import each form via AJAX
            var imported = 0;
            var errors = 0;

            forms.forEach(function(formData) {
                $.post(iqwAdmin.ajaxUrl, {
                    action: 'iqw_import_form',
                    nonce: iqwAdmin.nonce,
                    title: formData.title || 'Imported Form',
                    type: formData.type || 'custom',
                    config: JSON.stringify(formData.config || {}),
                    email_settings: JSON.stringify(formData.email_settings || {}),
                    status: formData.status || 'draft'
                }, function(res) {
                    if (res.success) imported++;
                    else errors++;

                    if (imported + errors === count) {
                        if (errors) {
                            alert('Imported ' + imported + ' form(s). ' + errors + ' failed.');
                        } else {
                            alert('Successfully imported ' + imported + ' form(s)!');
                        }
                        location.reload();
                    }
                });
            });
        };
        reader.readAsText(file);

        // Reset file input so same file can be selected again
        this.value = '';
    });

    // ================================================================
    // Entry Editing (Feature 5)
    // ================================================================
    $(document).on('click', '#iqw-edit-entry-btn', function() {
        $('.iqw-entry-display-val').hide();
        $('.iqw-entry-edit-input').show();
        $('#iqw-edit-entry-btn').hide();
        $('#iqw-save-entry-btn, #iqw-cancel-edit-btn').show();
    });

    $(document).on('click', '#iqw-cancel-edit-btn', function() {
        $('.iqw-entry-display-val').show();
        $('.iqw-entry-edit-input').hide();
        $('#iqw-edit-entry-btn').show();
        $('#iqw-save-entry-btn, #iqw-cancel-edit-btn').hide();
    });

    $(document).on('click', '#iqw-save-entry-btn', function() {
        var $btn = $(this);
        var entryId = $btn.data('entry-id');
        var fields = {};

        $('.iqw-entry-edit-input').each(function() {
            var name = $(this).attr('name');
            if (name) fields[name] = $(this).val();
        });

        $btn.prop('disabled', true).text('Saving...');

        $.post(iqwAdmin.ajaxUrl, {
            action: 'iqw_edit_entry',
            nonce: iqwAdmin.nonce,
            entry_id: entryId,
            fields: fields
        }, function(res) {
            if (res.success) {
                location.reload();
            } else {
                alert(res.data || 'Error saving entry.');
                $btn.prop('disabled', false).text('Save Changes');
            }
        }).fail(function() {
            alert('Network error.');
            $btn.prop('disabled', false).text('Save Changes');
        });
    });

    // ================================================================
    // PDF Generation (Feature 9)
    // ================================================================
    $(document).on('click', '#iqw-gen-pdf-btn', function() {
        var $btn = $(this);
        var entryId = $btn.data('id');
        $btn.prop('disabled', true).text('Generating...');

        $.post(iqwAdmin.ajaxUrl, {
            action: 'iqw_generate_pdf',
            nonce: iqwAdmin.nonce,
            entry_id: entryId
        }, function(res) {
            if (res.success && res.data.html_url) {
                window.open(res.data.html_url, '_blank');
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-media-document" style="vertical-align:middle;color:#1565c0;"></span> Printable Summary');
            } else {
                alert(res.data || 'Print view generation failed.');
                $btn.prop('disabled', false).text('Printable Summary');
            }
        }).fail(function() {
            alert('Network error.');
            $btn.prop('disabled', false).text('Printable Summary');
        });
    });

    // ================================================================
    // IMPORT / EXPORT ENTRIES
    // ================================================================

    // Export single entry as JSON
    $(document).on('click', '#iqw-export-entry-json', function() {
        var entryId = $(this).data('id');
        $.post(iqwAdmin.ajaxUrl, {
            action: 'iqw_export_entry',
            nonce: iqwAdmin.nonce,
            entry_id: entryId
        }, function(res) {
            if (res.success) {
                var blob = new Blob([JSON.stringify(res.data, null, 2)], { type: 'application/json' });
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'iqw-entry-' + entryId + '.json';
                a.click();
                URL.revokeObjectURL(url);
            } else {
                alert(res.data || 'Export failed.');
            }
        });
    });

    // Export selected entries as JSON (bulk)
    $(document).on('click', '#iqw-export-json-btn', function() {
        var ids = [];
        $('.iqw-entry-check:checked').each(function() { ids.push($(this).val()); });
        if (!ids.length) { alert('Please select entries to export.'); return; }

        $.post(iqwAdmin.ajaxUrl, {
            action: 'iqw_export_entries_json',
            nonce: iqwAdmin.nonce,
            entry_ids: ids
        }, function(res) {
            if (res.success) {
                var blob = new Blob([JSON.stringify(res.data, null, 2)], { type: 'application/json' });
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'iqw-entries-export.json';
                a.click();
                URL.revokeObjectURL(url);
            } else {
                alert(res.data || 'Export failed.');
            }
        });
    });

    // Import JSON button triggers file picker
    $(document).on('click', '#iqw-import-json-btn', function() {
        $('#iqw-import-file').click();
    });

    // Import file selected
    $(document).on('change', '#iqw-import-file', function() {
        var file = this.files[0];
        if (!file) return;

        var fd = new FormData();
        fd.append('action', 'iqw_import_entries');
        fd.append('nonce', iqwAdmin.nonce);
        fd.append('import_file', file);

        $.ajax({
            url: iqwAdmin.ajaxUrl,
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            success: function(res) {
                if (res.success) {
                    alert(res.data.message || 'Import complete!');
                    location.reload();
                } else {
                    alert(res.data || 'Import failed.');
                }
            },
            error: function() { alert('Network error.'); }
        });

        this.value = '';
    });

    // Test SMS
    $(document).on('click', '#iqw-test-sms', function() {
        var $btn = $(this), $result = $('#iqw-sms-test-result');
        $btn.prop('disabled', true).text('Sending...');
        $result.text('');
        $.post(iqwAdmin.ajaxUrl, {
            action: 'iqw_test_sms',
            nonce: iqwAdmin.nonce,
            sid: $('#iqw_twilio_sid').val(),
            token: $('#iqw_twilio_token').val(),
            from: $('#iqw_twilio_from').val(),
            to: $('#iqw_sms_notify_number').val()
        }, function(res) {
            $result.html(res.success
                ? '<span style="color:green;">✅ ' + res.data + '</span>'
                : '<span style="color:red;">❌ ' + (res.data || 'Failed') + '</span>');
            $btn.prop('disabled', false).text('Send Test SMS');
        }).fail(function() {
            $result.html('<span style="color:red;">❌ Network error</span>');
            $btn.prop('disabled', false).text('Send Test SMS');
        });
    });

    // Anonymize entry (GDPR)
    $(document).on('click', '#iqw-anonymize-entry', function() {
        if (!confirm('This will permanently replace all personal data (name, email, phone, address) with [REDACTED]. This cannot be undone. Continue?')) return;
        var entryId = $(this).data('id');
        var $btn = $(this);
        $btn.prop('disabled', true).text('Anonymizing...');
        $.post(iqwAdmin.ajaxUrl, {
            action: 'iqw_anonymize_entry',
            nonce: iqwAdmin.nonce,
            entry_id: entryId
        }, function(res) {
            if (res.success) {
                alert('Entry anonymized. Page will reload.');
                location.reload();
            } else {
                alert(res.data || 'Anonymization failed.');
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-privacy" style="vertical-align:middle;"></span> Anonymize (GDPR)');
            }
        });
    });

})(jQuery);
