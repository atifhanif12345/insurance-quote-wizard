/**
 * Insurance Quote Wizard v1.1 - Phase 2
 * Vanilla JS, zero dependencies, Compare.com Polish
 * Features: slide direction, auto-advance, phone format, review step,
 *           step counter, currency prefix, keyboard nav, loading spinner
 */
(function() {
    'use strict';

    class IQWWizard {
        constructor(containerId, formId, options) {
            this.container = document.getElementById(containerId);
            if (!this.container) return;

            this.options = options || {};
            this.formId = formId;
            this.config = window['iqwForm_' + formId];
            if (!this.config || !this.config.config || !this.config.config.steps) return;

            this.steps = this.config.config.steps;
            this.currentStep = 0;
            this.formData = {};
            this.visibleSteps = [];
            this.direction = 'forward'; // 'forward' or 'backward'
            this.isSubmitting = false;
            this.validationTimers = {};

            this.init();
        }

        init() {
            this.draftToken = '';
            this.applyUrlParams();
            this._applyPreset();

            const isPopup = this.options.isPopup || this.container.dataset.iqwPopup === 'true';

            // Popup opened via resume link — load draft using token passed in options
            if (isPopup && this.options.resumeToken) {
                this.loadDraftFromUrl(this.options.resumeToken);
                this.applyCustomColor();
                return;
            }

            // Inline form — resume from ?iqw_resume= URL param
            if (!isPopup) {
                const params = new URLSearchParams(window.location.search);
                if (params.get('iqw_resume')) {
                    this.loadDraftFromUrl();
                    this.applyCustomColor();
                    return;
                }
            }

            this._initRender();
        }

        _initRender() {
            this.isSingleMode = (this.config.config.settings && this.config.config.settings.form_mode === 'single');
            this.isConversational = (this.config.config.settings && this.config.config.settings.form_mode === 'conversational');
            this.calculateVisibleSteps();

            if (this.isConversational) {
                this.renderConversational();
            } else if (this.isSingleMode) {
                this.renderSinglePage();
            } else {
                this.renderSteps();
                this.renderProgressBar();
                this.bindNavigation();
                this.bindKeyboard();
                const startStep = (this._resumeToStep != null && this.visibleSteps.includes(this._resumeToStep)) ? this._resumeToStep : (this.visibleSteps[0] || 0);
                this.showStep(startStep);
            }

            this.applyCustomColor();
            this.updateMotivation();
            this.initAddressAutocomplete();
            this.initStripeElements();
            this.initSignatures();
            this.bindSaveContinue();

            // Track form view via AJAX (works even with page caching)
            this._trackEvent('iqw_track_view', 0);

            // Evaluate calculated fields on initial render
            this._evaluateCalculatedFields();

            // Auto-fill location from IP
            this._initGeolocation();
        }

        // Single Page Mode: render all steps on one page
        renderSinglePage() {
            // Hide wizard-specific UI
            const progressBar = this.$('#iqw-progress-' + this.formId);
            if (progressBar) progressBar.style.display = 'none';

            const motivation = this.container.querySelector('.iqw-motivation');
            if (motivation) motivation.style.display = 'none';

            const saveLater = this.$('#iqw-save-later-' + this.formId);
            if (saveLater) saveLater.style.display = 'none';

            // Add single-mode class
            this.container.classList.add('iqw-single-mode');

            // Render all visible steps as sections
            const wrapper = this.$('#iqw-steps-' + this.formId);
            wrapper.innerHTML = '';

            this.visibleSteps.forEach(stepIdx => {
                const step = this.steps[stepIdx];
                const section = document.createElement('div');
                section.className = 'iqw-single-section iqw-step';
                section.id = 'iqw-step-' + this.formId + '-' + stepIdx;
                section.dataset.stepIndex = stepIdx;

                let html = '<h3 class="iqw-single-section-title">' + this.esc(step.title) + '</h3>';
                if (step.fields) {
                    this._currentRenderStep = step;
                    html += this._renderFieldsHtml(step.fields);
                    this._currentRenderStep = null;
                }

                section.innerHTML = html;
                wrapper.appendChild(section);
            });

            // Show all sections
            wrapper.style.display = 'block';

            // Change nav: hide back button, show submit directly
            const backBtn = this.$('#iqw-back-' + this.formId);
            if (backBtn) backBtn.style.display = 'none';

            const nextBtn = this.$('#iqw-next-' + this.formId);
            if (nextBtn) nextBtn.textContent = this.config.strings.submit || 'Get My Quotes';

            this.currentStep = this.visibleSteps[0] || 0;

            this.bindFieldEvents();

            // Bind submit button
            if (nextBtn) {
                nextBtn.addEventListener('click', () => {
                    // If review step was shown and user clicks submit again, do actual submit
                    if (this._reviewShown) {
                        this.visibleSteps.forEach(si => this.collectStepData(si));
                        this.submitForm();
                        return;
                    }

                    // Validate all visible steps
                    let allValid = true;
                    this.visibleSteps.forEach(si => {
                        this.currentStep = si;
                        if (!this.validateStep(si)) allValid = false;
                    });
                    if (!allValid) {
                        const firstErr = this.container.querySelector('.iqw-field-error.visible');
                        if (firstErr) firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        return;
                    }
                    // Collect all data
                    this.visibleSteps.forEach(si => this.collectStepData(si));

                    // Check if review step is enabled
                    const reviewEnabled = this.config.config.settings && this.config.config.settings.review_step;
                    if (reviewEnabled) {
                        this._reviewShown = true;
                        // Hide all single-mode sections
                        this.container.querySelectorAll('.iqw-single-section').forEach(s => s.style.display = 'none');
                        this.showReviewStep();
                        // Scroll to top
                        this.container.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        return;
                    }

                    this.submitForm();
                });
            }
        }

        // ================================================================
        // CONVERSATIONAL MODE (Typeform-style, one question at a time)
        // ================================================================
        renderConversational() {
            // Flatten all visible fields from all visible steps into slides
            this._convFields = [];
            this.visibleSteps.forEach(si => {
                const step = this.steps[si];
                if (!step || !step.fields) return;
                step.fields.forEach(field => {
                    if (field.type === 'heading' || field.type === 'paragraph') return;
                    if (field.conditions && field.conditions.rules && field.conditions.rules.length) {
                        if (!this.evaluateConditions(field.conditions)) return;
                    }
                    this._convFields.push({ ...field, _stepIdx: si });
                });
            });

            this._convIndex = 0;
            const wrapper = this.$('#iqw-steps-' + this.formId);
            if (!wrapper) return;

            // Hide wizard UI
            const progress = this.$('#iqw-progress-' + this.formId);
            if (progress) progress.style.display = 'none';
            const motivation = this.container.querySelector('.iqw-motivation');
            if (motivation) motivation.style.display = 'none';

            wrapper.classList.add('iqw-conversational');
            this._convRenderSlide(wrapper);

            // Bind nav buttons
            const nextBtn = this.$('#iqw-next-' + this.formId);
            const backBtn = this.$('#iqw-back-' + this.formId);
            if (nextBtn) {
                nextBtn.style.display = 'none'; // We use slide-level buttons
            }
            if (backBtn) backBtn.style.display = 'none';
        }

        _convRenderSlide(wrapper) {
            const total = this._convFields.length;
            const idx = this._convIndex;

            // Submit slide
            if (idx >= total) {
                wrapper.innerHTML = '';
                // Collect all data
                this.visibleSteps.forEach(si => this.collectStepData(si));
                this.submitForm();
                return;
            }

            const field = this._convFields[idx];
            let html = '<div class="iqw-conv-slide active" data-conv-index="' + idx + '">';
            html += '<div class="iqw-conv-question">' + this.esc(field.label || 'Question ' + (idx + 1));
            if (field.required) html += ' <span class="iqw-required">*</span>';
            html += '</div>';
            html += '<div class="iqw-conv-input-wrap">';

            // Render field content (reuse existing render methods)
            const stepEl = document.createElement('div');
            stepEl.dataset.stepIndex = field._stepIdx;
            stepEl.id = 'iqw-step-' + this.formId + '-' + field._stepIdx;
            stepEl.className = 'iqw-step';

            // Render just the field input (without label — we show it as question)
            const tempField = { ...field, label: '' };
            switch (field.type) {
                case 'radio_cards':
                    html += this.renderRadioCards(tempField).replace(/<label[^>]*>[^<]*<\/label>/i, '');
                    break;
                case 'checkbox_group':
                    html += this.renderCheckboxGroup(tempField).replace(/<label[^>]*>[^<]*<\/label>/i, '');
                    break;
                case 'select':
                    html += '<select class="iqw-select iqw-conv-field" name="' + this.esc(field.key) + '" data-type="select">';
                    html += '<option value="">Choose...</option>';
                    (field.options || []).forEach(o => {
                        const sel = this.formData[field.key] === o.value ? ' selected' : '';
                        html += '<option value="' + this.esc(o.value) + '"' + sel + '>' + this.esc(o.label) + '</option>';
                    });
                    html += '</select>';
                    break;
                case 'textarea':
                    html += '<textarea class="iqw-textarea iqw-conv-field" name="' + this.esc(field.key) + '" rows="4" placeholder="' + this.esc(field.placeholder || '') + '" data-type="textarea">' + this.esc(this.formData[field.key] || '') + '</textarea>';
                    break;
                default:
                    const inputType = field.type === 'email' ? 'email' : (field.type === 'number' ? 'number' : (field.type === 'phone' ? 'tel' : 'text'));
                    html += '<input type="' + inputType + '" class="iqw-input iqw-conv-field" name="' + this.esc(field.key) + '" ' +
                        'value="' + this.esc(this.formData[field.key] || '') + '" ' +
                        'placeholder="' + this.esc(field.placeholder || '') + '" data-type="' + this.esc(field.type) + '"' +
                        (field.type === 'number' && field.min !== undefined ? ' min="' + field.min + '"' : '') +
                        (field.type === 'number' && field.max !== undefined ? ' max="' + field.max + '"' : '') + '>';
            }

            html += '</div>';
            html += '<div class="iqw-field-error" id="iqw-error-' + this.esc(field.key) + '" style="color:#e74c3c;margin-top:8px;font-size:13px;"></div>';

            // Nav buttons
            html += '<div class="iqw-conv-nav">';
            if (idx > 0) {
                html += '<button type="button" class="iqw-btn-back iqw-conv-back">← Back</button>';
            }
            html += '<button type="button" class="iqw-btn-next iqw-conv-next">' + (idx === total - 1 ? (this.config.strings.submit || 'Submit') : 'Continue →') + '</button>';
            html += '</div>';

            // Progress
            html += '<div class="iqw-conv-progress">' + (idx + 1) + ' of ' + total;
            html += '<div class="iqw-conv-progress-bar"><div class="iqw-conv-progress-fill" style="width:' + ((idx + 1) / total * 100) + '%"></div></div>';
            html += '</div>';
            html += '</div>';

            wrapper.innerHTML = html;

            // Bind events
            this.bindFieldEvents();
            this.initSignatures();
            const firstInput = wrapper.querySelector('.iqw-conv-field, .iqw-radio-card, .iqw-input, .iqw-select');
            if (firstInput) setTimeout(() => firstInput.focus(), 100);

            // Enter key to advance
            wrapper.querySelectorAll('.iqw-conv-field').forEach(inp => {
                inp.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' && inp.tagName !== 'TEXTAREA') {
                        e.preventDefault();
                        this._convNext(wrapper);
                    }
                });
            });

            // Next button
            const nextBtn = wrapper.querySelector('.iqw-conv-next');
            if (nextBtn) {
                nextBtn.addEventListener('click', () => this._convNext(wrapper));
            }

            // Back button
            const backBtn = wrapper.querySelector('.iqw-conv-back');
            if (backBtn) {
                backBtn.addEventListener('click', () => {
                    this._convCollectCurrent(wrapper);
                    this._convIndex--;
                    this._convRenderSlide(wrapper);
                });
            }

            // Auto-advance for radio cards
            wrapper.querySelectorAll('.iqw-radio-card').forEach(card => {
                card.addEventListener('click', () => {
                    setTimeout(() => this._convNext(wrapper), 300);
                });
            });
        }

        _convCollectCurrent(wrapper) {
            wrapper.querySelectorAll('input, select, textarea').forEach(el => {
                const name = el.name;
                if (!name) return;
                if (el.type === 'radio') { if (el.checked) this.formData[name] = el.value; }
                else if (el.type === 'checkbox') {
                    if (!Array.isArray(this.formData[name])) this.formData[name] = [];
                    if (el.checked && !this.formData[name].includes(el.value)) this.formData[name].push(el.value);
                } else { this.formData[name] = el.value; }
            });
        }

        _convNext(wrapper) {
            this._convCollectCurrent(wrapper);
            const field = this._convFields[this._convIndex];

            // Validate current field
            if (field.required) {
                const val = this.formData[field.key];
                if (!val || (Array.isArray(val) && val.length === 0)) {
                    const errEl = wrapper.querySelector('#iqw-error-' + field.key);
                    if (errEl) { errEl.textContent = (field.label || 'This field') + ' is required.'; errEl.style.display = 'block'; }
                    return;
                }
            }

            this._convIndex++;

            // Re-evaluate conditions for next fields
            while (this._convIndex < this._convFields.length) {
                const next = this._convFields[this._convIndex];
                if (next.conditions && next.conditions.rules && next.conditions.rules.length) {
                    if (!this.evaluateConditions(next.conditions)) {
                        this._convIndex++;
                        continue;
                    }
                }
                break;
            }

            this._convRenderSlide(wrapper);
        }

        // Shared: render fields with row grouping for column layout
        _renderFieldsHtml(fields) {
            let html = '';
            let rowHtml = '';
            let rowWidth = 0;
            let inRow = false;

            fields.forEach(field => {
                const w = field.width || 'full';
                const widthMap = { full: 100, half: 50, third: 33, 'two-third': 66, quarter: 25 };
                const pct = widthMap[w] || 100;

                if (pct < 100) {
                    if (!inRow || rowWidth + pct > 100) {
                        if (inRow) html += rowHtml + '</div>';
                        rowHtml = '';
                        rowWidth = 0;
                        inRow = true;
                        html += '<div class="iqw-field-row">';
                    }
                    rowHtml += this.renderField(field);
                    rowWidth += pct;
                } else {
                    if (inRow) { html += rowHtml + '</div>'; rowHtml = ''; rowWidth = 0; inRow = false; }
                    html += this.renderField(field);
                }
            });
            if (inRow) html += rowHtml + '</div>';
            return html;
        }

        // Load draft — token can be passed directly (popup) or read from URL (inline)
        loadDraftFromUrl(tokenOverride) {
            const params = new URLSearchParams(window.location.search);
            const token = tokenOverride || params.get('iqw_resume');
            if (!token) { this._initRender(); return; }

            // Show loading state
            this.container.classList.add('iqw-loading-draft');
            const loader = document.createElement('div');
            loader.className = 'iqw-draft-loader';
            loader.innerHTML = '<div class="iqw-draft-loader-inner"><span class="iqw-draft-spinner"></span> Restoring your progress...</div>';
            this.container.prepend(loader);

            const fd = new FormData();
            fd.append('action', 'iqw_load_draft');
            fd.append('draft_token', token);

            fetch(this.config.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(r => r.json())
                .then(res => {
                    if (res.success && res.data) {
                        // Verify draft belongs to this form
                        if (res.data.form_id && parseInt(res.data.form_id) !== parseInt(this.formId)) {
                            console.warn('IQW: Draft form_id mismatch. Draft is for form ' + res.data.form_id + ', current form is ' + this.formId);
                        } else {
                            this.formData = res.data.data || {};
                            this.draftToken = res.data.token || '';
                            this._resumeToStep = res.data.current_step || 0;
                        }
                    }
                    loader.remove();
                    this.container.classList.remove('iqw-loading-draft');
                    this._initRender();
                })
                .catch(() => {
                    loader.remove();
                    this.container.classList.remove('iqw-loading-draft');
                    this._initRender();
                });
        }

        bindSaveContinue() {
            const saveBtn = this.$('#iqw-save-later-' + this.formId);
            if (!saveBtn) return;

            saveBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.collectStepData(this.currentStep);

                const fd = new FormData();
                fd.append('action', 'iqw_save_draft');
                fd.append('form_id', this.formId);
                fd.append('iqw_nonce', this.config.nonce);
                fd.append('current_step', this.currentStep);
                fd.append('draft_token', this.draftToken);
                fd.append('page_url', window.location.href);

                Object.keys(this.formData).forEach(key => {
                    const val = this.formData[key];
                    if (Array.isArray(val)) {
                        val.forEach(v => fd.append('fields[' + key + '][]', v));
                    } else {
                        fd.append('fields[' + key + ']', val);
                    }
                });

                saveBtn.textContent = '⏳ Saving...';

                fetch(this.config.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(r => r.json())
                    .then(res => {
                        const msgEl = this.$('#iqw-save-later-msg-' + this.formId);
                        if (res.success && res.data) {
                            this.draftToken = res.data.token;
                            if (msgEl) {
                                msgEl.style.display = 'block';
                                msgEl.innerHTML = '<div class="iqw-save-later-success">' +
                                    '<strong>✓ Progress saved!</strong> ' +
                                    'Bookmark this link to continue later:<br>' +
                                    '<input type="text" class="iqw-resume-url" value="' + this.esc(res.data.resume_url) + '" readonly onclick="this.select();document.execCommand(\'copy\');">' +
                                    '</div>';
                            }
                        } else {
                            if (msgEl) {
                                msgEl.style.display = 'block';
                                msgEl.innerHTML = '<div class="iqw-save-later-error">Could not save. Please try again.</div>';
                            }
                        }
                        saveBtn.textContent = '💾 Save & Finish Later';
                    })
                    .catch(() => { saveBtn.textContent = '💾 Save & Finish Later'; });
            });
        }

        applyCustomColor() {
            const c = this.config.primaryColor;
            if (c) {
                this.container.style.setProperty('--iqw-primary', c);
                // Calculate darker shade
                const r = parseInt(c.slice(1,3),16), g = parseInt(c.slice(3,5),16), b = parseInt(c.slice(5,7),16);
                this.container.style.setProperty('--iqw-primary-dark', '#' + [r,g,b].map(v => Math.max(0,v-30).toString(16).padStart(2,'0')).join(''));
                this.container.style.setProperty('--iqw-primary-rgb', `${r},${g},${b}`);
                this.container.style.setProperty('--iqw-primary-light', `rgba(${r},${g},${b},0.08)`);
            }
        }

        updateMotivation() {
            const mot = this.container.querySelector('.iqw-motivation-text');
            if (mot && this.config.config.settings && this.config.config.settings.motivation_text) {
                mot.textContent = this.config.config.settings.motivation_text;
            }
        }

        // ================================================================
        // CONDITIONAL LOGIC
        // ================================================================
        calculateVisibleSteps() {
            this.visibleSteps = [];
            for (let i = 0; i < this.steps.length; i++) {
                const step = this.steps[i];
                if (!step.conditions || !step.conditions.rules || this.evaluateConditions(step.conditions)) {
                    this.visibleSteps.push(i);
                }
            }
        }

        evaluateConditions(conditions) {
            if (!conditions || !conditions.rules) return true;
            const logic = conditions.logic || 'and';
            const results = conditions.rules.map(r => this.evalRule(r));
            return logic === 'or' ? results.some(r => r) : results.every(r => r);
        }

        evalRule(rule) {
            const rawVal = this.formData[rule.field];
            const target = String(rule.value || '').toLowerCase();
            const isArray = Array.isArray(rawVal);

            // Handle array values (checkbox groups)
            if (isArray) {
                const arrLower = rawVal.map(v => String(v).toLowerCase());
                switch (rule.operator) {
                    case 'is': case 'equals': return arrLower.length === 1 && arrLower[0] === target;
                    case 'is_not': case 'not_equals': return !arrLower.includes(target);
                    case 'contains': return arrLower.includes(target);
                    case 'not_contains': return !arrLower.includes(target);
                    case 'empty': return arrLower.length === 0;
                    case 'not_empty': return arrLower.length > 0;
                    case 'in': return target.split(',').map(s=>s.trim()).some(t => arrLower.includes(t));
                    case 'not_in': return !target.split(',').map(s=>s.trim()).some(t => arrLower.includes(t));
                    default: return true;
                }
            }

            const val = String(rawVal || '').toLowerCase();
            switch (rule.operator) {
                case 'is': case 'equals': return val === target;
                case 'is_not': case 'not_equals': return val !== target;
                case 'contains': return val.includes(target);
                case 'not_contains': return !val.includes(target);
                case 'starts_with': return val.startsWith(target);
                case 'ends_with': return val.endsWith(target);
                case 'empty': return !val;
                case 'not_empty': return !!val;
                case 'gt': case 'greater_than': return parseFloat(val) > parseFloat(target);
                case 'lt': case 'less_than': return parseFloat(val) < parseFloat(target);
                case 'gte': return parseFloat(val) >= parseFloat(target);
                case 'lte': return parseFloat(val) <= parseFloat(target);
                case 'in': return target.split(',').map(s=>s.trim().toLowerCase()).includes(val);
                case 'not_in': return !target.split(',').map(s=>s.trim().toLowerCase()).includes(val);
                default: return true;
            }
        }

        // ================================================================
        // RENDERING
        // ================================================================
        renderSteps() {
            const wrapper = this.$(  '#iqw-steps-' + this.formId);
            wrapper.innerHTML = '';

            this.steps.forEach((step, index) => {
                const el = document.createElement('div');
                el.className = 'iqw-step';
                el.id = 'iqw-step-' + this.formId + '-' + index;
                el.dataset.stepIndex = index;

                let html = '';

                // Step counter
                html += '<div class="iqw-step-counter"></div>';
                html += '<h2 class="iqw-step-title">' + this.esc(step.title) + '</h2>';

                if (step.fields) {
                    this._currentRenderStep = step;
                    html += this._renderFieldsHtml(step.fields);
                    this._currentRenderStep = null;
                }

                el.innerHTML = html;
                wrapper.appendChild(el);
            });

            this.bindFieldEvents();
        }

        renderField(field) {
            // Field-level conditional visibility
            const isVisible = !field.conditions || !field.conditions.rules || !field.conditions.rules.length || this.evaluateConditions(field.conditions);
            const hideStyle = isVisible ? '' : ' style="display:none;"';
            const condAttr = (field.conditions && field.conditions.rules && field.conditions.rules.length) ? ' data-has-conditions="1"' : '';
            const widthClass = field.width && field.width !== 'full' ? ' iqw-field-' + this.esc(field.width) : '';

            let html = '<div class="iqw-field-wrap' + widthClass + '" data-field-key="' + this.esc(field.key) + '"' + condAttr + hideStyle + '>';

            switch (field.type) {
                case 'radio_cards': html += this.renderRadioCards(field); break;
                case 'radio': html += this.renderRadioCards(field); break;
                case 'checkbox_group': html += this.renderCheckboxGroup(field); break;
                case 'select': html += this.renderSelect(field); break;
                case 'textarea': html += this.renderTextarea(field); break;
                case 'currency': html += this.renderCurrency(field); break;
                case 'phone': html += this.renderPhone(field); break;
                case 'date': html += this.renderDate(field); break;
                case 'file_upload': html += this.renderFileUpload(field); break;
                case 'url': html += this.renderUrl(field); break;
                case 'consent': html += this.renderConsent(field); break;
                case 'repeater': html += this.renderRepeater(field); break;
                case 'address': html += this.renderAddress(field); break;
                case 'payment': html += this.renderPayment(field); break;
                case 'calculated': html += this.renderCalculated(field); break;
                case 'signature': html += this.renderSignature(field); break;
                case 'heading': html += '<h3 style="margin:0;font-size:18px;color:var(--iqw-dark);">' + this.esc(field.label) + '</h3>'; break;
                case 'paragraph': html += '<p style="color:var(--iqw-gray);font-size:14px;">' + this.esc(field.label) + '</p>'; break;
                default: html += this.renderInput(field);
            }

            // Help text / description (Feature 6)
            if (field.help_text) {
                html += '<div class="iqw-help-text">' + this.esc(field.help_text) + '</div>';
            }

            html += '<div class="iqw-field-error" id="iqw-error-' + this.esc(field.key) + '"></div>';
            html += '</div>';
            return html;
        }

        _labelHtml(field) {
            if (field.hide_label) return '';
            return '<label class="iqw-field-label">' + this.esc(field.label) + req + '</label>';
        }

        renderInput(field) {
            const type = field.type === 'email' ? 'email' : (field.type === 'number' ? 'number' : 'text');
            const val = this.formData[field.key] || '';

            return this._labelHtml(field) +
                '<input type="' + type + '" class="iqw-input" name="' + this.esc(field.key) + '" ' +
                'placeholder="' + this.esc(field.placeholder || '') + '" value="' + this.esc(val) + '" ' +
                (field.min !== undefined ? 'min="' + field.min + '" ' : '') +
                (field.max !== undefined ? 'max="' + field.max + '" ' : '') +
                'autocomplete="off" data-type="' + this.esc(field.type) + '">';
        }

        renderPhone(field) {
            const val = this.formData[field.key] || '';
            return this._labelHtml(field) +
                '<input type="tel" class="iqw-input iqw-phone-input" name="' + this.esc(field.key) + '" ' +
                'placeholder="' + this.esc(field.placeholder || '(555) 123-4567') + '" value="' + this.esc(val) + '" ' +
                'maxlength="14" autocomplete="tel" data-type="phone">';
        }

        renderCurrency(field) {
            const val = this.formData[field.key] || '';
            return this._labelHtml(field) +
                '<div class="iqw-currency-wrap">' +
                '<span class="iqw-currency-prefix">$</span>' +
                '<input type="text" inputmode="numeric" class="iqw-input iqw-currency-input" name="' + this.esc(field.key) + '" ' +
                'placeholder="' + this.esc(field.placeholder || '0') + '" value="' + this.esc(val) + '" ' +
                'data-type="currency"></div>';
        }

        renderDate(field) {
            const val = this.formData[field.key] || '';
            
            // Parse existing value
            let selMonth = '', selDay = '', selYear = '';
            if (val) {
                const parts = val.includes('-') ? val.split('-') : val.split('/');
                if (parts.length === 3) {
                    if (val.includes('-')) { selYear = parts[0]; selMonth = parts[1]; selDay = parts[2]; }
                    else { selMonth = parts[0]; selDay = parts[1]; selYear = parts[2]; }
                }
            }

            // Month options
            const months = ['','01-Jan','02-Feb','03-Mar','04-Apr','05-May','06-Jun','07-Jul','08-Aug','09-Sep','10-Oct','11-Nov','12-Dec'];
            let monthOpts = '<option value="">Month</option>';
            months.forEach((m,i) => { if(i===0) return; const mv = String(i).padStart(2,'0'); monthOpts += '<option value="'+mv+'"'+(selMonth===mv?' selected':'')+'>'+m+'</option>'; });

            // Day options
            let dayOpts = '<option value="">Day</option>';
            for(let d=1; d<=31; d++) { const dv = String(d).padStart(2,'0'); dayOpts += '<option value="'+dv+'"'+(selDay===dv?' selected':'')+'>'+d+'</option>'; }

            // Year options
            const isDOB = field.validation === 'dob_driver';
            const curYear = new Date().getFullYear();
            const startYear = isDOB ? curYear - 16 : curYear;
            const endYear = isDOB ? curYear - 100 : curYear - 120;
            let yearOpts = '<option value="">Year</option>';
            for(let y=startYear; y>=endYear; y--) { yearOpts += '<option value="'+y+'"'+(selYear==y?' selected':'')+'>'+y+'</option>'; }

            return this._labelHtml(field) +
                '<div class="iqw-date-selects" data-field-key="' + this.esc(field.key) + '">' +
                '<select class="iqw-select iqw-date-month" data-part="month">' + monthOpts + '</select>' +
                '<select class="iqw-select iqw-date-day" data-part="day">' + dayOpts + '</select>' +
                '<select class="iqw-select iqw-date-year" data-part="year">' + yearOpts + '</select>' +
                '<input type="hidden" class="iqw-date-hidden" name="' + this.esc(field.key) + '" value="' + this.esc(val) + '" data-type="date">' +
                '</div>';
        }

        renderSelect(field) {
            const val = this.formData[field.key] || '';

            let html = this._labelHtml(field);
            html += '<select class="iqw-select" name="' + this.esc(field.key) + '" data-type="select">';
            html += '<option value="">Select...</option>';
            (field.options || []).forEach(opt => {
                html += '<option value="' + this.esc(opt.value) + '"' + (val === opt.value ? ' selected' : '') + '>' + this.esc(opt.label) + '</option>';
            });
            html += '</select>';
            return html;
        }

        renderTextarea(field) {
            const val = this.formData[field.key] || '';

            return this._labelHtml(field) +
                '<textarea class="iqw-input iqw-textarea" name="' + this.esc(field.key) + '" ' +
                'placeholder="' + this.esc(field.placeholder || '') + '" ' +
                'rows="4" data-type="textarea">' + this.esc(val) + '</textarea>';
        }

        renderFileUpload(field) {
            const accept = field.accept || '.pdf,.jpg,.jpeg,.png,.doc,.docx';
            const maxSize = field.max_size || 10;
            return this._labelHtml(field) +
                '<div class="iqw-file-upload-wrap">' +
                '<input type="file" class="iqw-file-input" name="fields_' + this.esc(field.key) + '" ' +
                'accept="' + this.esc(accept) + '" data-max-size="' + maxSize + '" data-type="file_upload" data-field-key="' + this.esc(field.key) + '">' +
                '<div class="iqw-file-label">' +
                '<span class="iqw-file-icon">📎</span>' +
                '<span class="iqw-file-text">Click to upload or drag file here</span>' +
                '<span class="iqw-file-hint">Max ' + maxSize + 'MB &middot; ' + this.esc(accept) + '</span>' +
                '</div>' +
                '<div class="iqw-file-preview" style="display:none;"></div>' +
                '</div>';
        }

        renderUrl(field) {
            const val = this.formData[field.key] || '';
            return this._labelHtml(field) +
                '<input type="url" class="iqw-input" name="' + this.esc(field.key) + '" ' +
                'placeholder="' + this.esc(field.placeholder || 'https://') + '" value="' + this.esc(val) + '" ' +
                'data-type="url">';
        }

        renderConsent(field) {
            const checked = this.formData[field.key] === 'yes';
            return '<label class="iqw-consent-label">' +
                '<input type="checkbox" class="iqw-consent-input" name="' + this.esc(field.key) + '" value="yes" ' +
                (checked ? 'checked ' : '') + 'data-type="consent">' +
                '<span class="iqw-consent-check"></span>' +
                '<span class="iqw-consent-text">' + this.esc(field.label) + (field.required ? ' <span class="iqw-required">*</span>' : '') + '</span>' +
                '</label>';
        }

        renderAddress(field) {
            const labels = { street: 'Street Address', city: 'City', state: 'State', zip: 'ZIP Code' };
            const placeholders = { street: '123 Main St', city: 'City', state: 'State', zip: '12345' };

            let html = this._labelHtml(field);
            html += '<div class="iqw-address-group">';

            // Street with autocomplete
            const streetKey = field.key + '_street';
            const streetVal = this.formData[streetKey] || '';
            html += '<input type="text" class="iqw-input iqw-address-autocomplete" name="' + this.esc(streetKey) + '" ' +
                'placeholder="' + this.esc(placeholders.street) + '" value="' + this.esc(streetVal) + '" ' +
                'data-field-key="' + this.esc(field.key) + '" data-type="address" autocomplete="off">';

            // City, State, ZIP row
            html += '<div class="iqw-field-row" style="margin-top:8px;">';
            ['city', 'state', 'zip'].forEach(sub => {
                const subKey = field.key + '_' + sub;
                const subVal = this.formData[subKey] || '';
                const w = sub === 'city' ? 'iqw-field-half' : (sub === 'state' ? 'iqw-field-quarter' : 'iqw-field-quarter');
                html += '<div class="iqw-field-wrap ' + w + '" style="margin-bottom:0;">';
                if (sub === 'state') {
                    html += '<select class="iqw-select iqw-address-sub" name="' + this.esc(subKey) + '" data-type="select">';
                    html += '<option value="">State</option>';
                    const states = ['AL','AK','AZ','AR','CA','CO','CT','DE','FL','GA','HI','ID','IL','IN','IA','KS','KY','LA','ME','MD','MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ','NM','NY','NC','ND','OH','OK','OR','PA','RI','SC','SD','TN','TX','UT','VT','VA','WA','WV','WI','WY'];
                    states.forEach(s => { html += '<option value="' + s + '"' + (subVal === s ? ' selected' : '') + '>' + s + '</option>'; });
                    html += '</select>';
                } else {
                    html += '<input type="text" class="iqw-input iqw-address-sub" name="' + this.esc(subKey) + '" ' +
                        'placeholder="' + this.esc(labels[sub]) + '" value="' + this.esc(subVal) + '" data-type="text">';
                }
                html += '</div>';
            });
            html += '</div></div>';
            return html;
        }

        renderPayment(field) {
            const amount = field.amount || '';

            let html = this._labelHtml(field);
            html += '<div class="iqw-payment-wrap" data-field-key="' + this.esc(field.key) + '">';

            if (!amount) {
                html += '<div style="margin-bottom:10px;">';
                html += '<label class="iqw-field-label iqw-field-label-sm">Amount ($)</label>';
                html += '<input type="number" class="iqw-input iqw-payment-amount" name="' + this.esc(field.key) + '_amount" ' +
                    'placeholder="0.00" step="0.01" min="0.50" value="' + this.esc(this.formData[field.key + '_amount'] || '') + '" data-type="number">';
                html += '</div>';
            } else {
                html += '<div class="iqw-payment-fixed-amount">Amount: <strong>$' + this.esc(parseFloat(amount).toFixed(2)) + '</strong></div>';
                html += '<input type="hidden" name="' + this.esc(field.key) + '_amount" value="' + this.esc(amount) + '">';
            }

            // Stripe card element container
            html += '<div class="iqw-stripe-card-element" id="iqw-card-' + this.esc(field.key) + '"></div>';
            html += '<div class="iqw-stripe-errors" id="iqw-card-errors-' + this.esc(field.key) + '"></div>';
            html += '<input type="hidden" name="' + this.esc(field.key) + '_payment_intent" class="iqw-payment-intent-id" value="">';
            html += '</div>';
            return html;
        }

        renderCalculated(field) {
            const prefix = field.prefix || '';
            const suffix = field.suffix || '';
            const decimals = field.decimal_places ?? 2;
            const val = this.formData[field.key] || '0';

            let html = this._labelHtml(field);
            html += '<div class="iqw-calculated-field" data-field-key="' + this.esc(field.key) + '" ' +
                'data-formula="' + this.esc(field.formula || '') + '" ' +
                'data-decimals="' + decimals + '" ' +
                'data-prefix="' + this.esc(prefix) + '" ' +
                'data-suffix="' + this.esc(suffix) + '">';
            html += '<span class="iqw-calc-prefix">' + this.esc(prefix) + '</span>';
            html += '<span class="iqw-calc-value">' + this.esc(parseFloat(val).toFixed(decimals)) + '</span>';
            html += '<span class="iqw-calc-suffix">' + this.esc(suffix) + '</span>';
            html += '</div>';
            html += '<input type="hidden" name="' + this.esc(field.key) + '" value="' + this.esc(val) + '" class="iqw-calc-hidden" data-type="calculated">';
            return html;
        }

        // Real-time calculated field evaluation
        _evaluateCalculatedFields() {
            this.container.querySelectorAll('.iqw-calculated-field').forEach(el => {
                const formula = el.dataset.formula;
                if (!formula) return;
                const decimals = parseInt(el.dataset.decimals) || 2;
                const key = el.dataset.fieldKey;

                // Replace {field_key} with current values
                let expr = formula.replace(/\{([^}]+)\}/g, (match, fieldKey) => {
                    const val = parseFloat(this.formData[fieldKey] || 0);
                    return isNaN(val) ? 0 : val;
                });

                // Safe math evaluation (no eval — only numbers and operators)
                let result = 0;
                try {
                    // Sanitize: only allow digits, dots, operators, parens, spaces
                    expr = expr.replace(/[^0-9.+\-*/() ]/g, '');
                    if (expr.trim()) {
                        result = Function('"use strict"; return (' + expr + ')')();
                    }
                } catch(e) { result = 0; }

                if (isNaN(result) || !isFinite(result)) result = 0;
                result = parseFloat(result.toFixed(decimals));

                el.querySelector('.iqw-calc-value').textContent = result.toFixed(decimals);
                this.formData[key] = result.toString();

                // Update hidden input
                const hidden = this.container.querySelector('input[name="' + key + '"]');
                if (hidden) hidden.value = result;
            });
        }

        renderSignature(field) {
            const w = field.canvas_width || 400;
            const h = field.canvas_height || 150;
            const color = field.pen_color || '#000000';

            let html = this._labelHtml(field);
            html += '<div class="iqw-signature-wrap" data-field-key="' + this.esc(field.key) + '">';
            html += '<canvas class="iqw-signature-canvas" width="' + w + '" height="' + h + '" data-pen-color="' + this.esc(color) + '" style="border:2px solid #ddd;border-radius:8px;cursor:crosshair;touch-action:none;max-width:100%;"></canvas>';
            html += '<div class="iqw-signature-actions">';
            html += '<button type="button" class="iqw-signature-clear" data-field-key="' + this.esc(field.key) + '">✕ Clear</button>';
            html += '<span class="iqw-signature-hint">Draw your signature above</span>';
            html += '</div>';
            html += '<input type="hidden" name="' + this.esc(field.key) + '" class="iqw-signature-data" value="" data-type="signature">';
            html += '</div>';
            return html;
        }

        // Initialize signature canvases with drawing logic
        initSignatures() {
            this.container.querySelectorAll('.iqw-signature-canvas').forEach(canvas => {
                if (canvas._iqwSigInit) return;
                canvas._iqwSigInit = true;

                const ctx = canvas.getContext('2d');
                const color = canvas.dataset.penColor || '#000000';
                const fieldKey = canvas.closest('.iqw-signature-wrap').dataset.fieldKey;
                const hidden = canvas.closest('.iqw-signature-wrap').querySelector('.iqw-signature-data');
                let drawing = false;
                let hasSigned = false;

                ctx.strokeStyle = color;
                ctx.lineWidth = 2.5;
                ctx.lineCap = 'round';
                ctx.lineJoin = 'round';

                const getPos = (e) => {
                    const rect = canvas.getBoundingClientRect();
                    const scaleX = canvas.width / rect.width;
                    const scaleY = canvas.height / rect.height;
                    if (e.touches && e.touches[0]) {
                        return { x: (e.touches[0].clientX - rect.left) * scaleX, y: (e.touches[0].clientY - rect.top) * scaleY };
                    }
                    return { x: (e.clientX - rect.left) * scaleX, y: (e.clientY - rect.top) * scaleY };
                };

                const startDraw = (e) => {
                    e.preventDefault();
                    drawing = true;
                    hasSigned = true;
                    const pos = getPos(e);
                    ctx.beginPath();
                    ctx.moveTo(pos.x, pos.y);
                };

                const draw = (e) => {
                    if (!drawing) return;
                    e.preventDefault();
                    const pos = getPos(e);
                    ctx.lineTo(pos.x, pos.y);
                    ctx.stroke();
                };

                const endDraw = (_e) => {
                    if (!drawing) return;
                    drawing = false;
                    ctx.closePath();
                    // Save as base64 PNG
                    if (hasSigned && hidden) {
                        hidden.value = canvas.toDataURL('image/png');
                        this.formData[fieldKey] = hidden.value;
                    }
                };

                // Mouse events
                canvas.addEventListener('mousedown', startDraw);
                canvas.addEventListener('mousemove', draw);
                canvas.addEventListener('mouseup', endDraw);
                canvas.addEventListener('mouseleave', endDraw);

                // Touch events (mobile)
                canvas.addEventListener('touchstart', startDraw, { passive: false });
                canvas.addEventListener('touchmove', draw, { passive: false });
                canvas.addEventListener('touchend', endDraw);

                // Clear button
                const clearBtn = canvas.closest('.iqw-signature-wrap').querySelector('.iqw-signature-clear');
                if (clearBtn) {
                    clearBtn.addEventListener('click', () => {
                        ctx.clearRect(0, 0, canvas.width, canvas.height);
                        hasSigned = false;
                        if (hidden) { hidden.value = ''; this.formData[fieldKey] = ''; }
                    });
                }

                // Pre-fill if draft had signature
                if (this.formData[fieldKey] && this.formData[fieldKey].startsWith('data:image')) {
                    const img = new Image();
                    img.onload = () => { ctx.drawImage(img, 0, 0); hasSigned = true; };
                    img.src = this.formData[fieldKey];
                    if (hidden) hidden.value = this.formData[fieldKey];
                }
            });
        }

        renderRepeater(field) {
            const subFields = field.sub_fields || [];
            const maxItems = field.max_items || 5;
            const btnLabel = field.add_button_label || 'Add Another';
            const items = this.formData['__repeater_' + field.key + '_count'] || 1;
            const count = Math.max(1, parseInt(items));

            let html = this._labelHtml(field);
            html += '<div class="iqw-repeater" data-field-key="' + this.esc(field.key) + '" data-max="' + maxItems + '">';

            for (let i = 0; i < count; i++) {
                html += this._renderRepeaterItem(field, subFields, i, count > 1);
            }

            html += '</div>';
            if (count < maxItems) {
                html += '<button type="button" class="iqw-repeater-add" data-field-key="' + this.esc(field.key) + '">' +
                    '+ ' + this.esc(btnLabel) + '</button>';
            }
            return html;
        }

        _renderRepeaterItem(field, subFields, index, canRemove) {
            let html = '<div class="iqw-repeater-item" data-index="' + index + '">';
            html += '<div class="iqw-repeater-header">';
            html += '<span class="iqw-repeater-num">#' + (index + 1) + '</span>';
            if (canRemove) {
                html += '<button type="button" class="iqw-repeater-remove" data-field-key="' + this.esc(field.key) + '" data-index="' + index + '">&times;</button>';
            }
            html += '</div>';

            subFields.forEach(sf => {
                const subKey = field.key + '_' + index + '_' + sf.key;
                const val = this.formData[subKey] || '';
                const subReq = sf.required ? '<span class="iqw-required">*</span>' : '';
                html += '<div class="iqw-repeater-field">';
                html += '<label class="iqw-field-label iqw-field-label-sm">' + this.esc(sf.label) + subReq + '</label>';

                if (sf.type === 'select' && sf.options) {
                    html += '<select class="iqw-select" name="' + this.esc(subKey) + '" data-type="select">';
                    html += '<option value="">Select...</option>';
                    sf.options.forEach(opt => {
                        html += '<option value="' + this.esc(opt.value) + '"' + (val === opt.value ? ' selected' : '') + '>' + this.esc(opt.label) + '</option>';
                    });
                    html += '</select>';
                } else {
                    const inputType = sf.type === 'email' ? 'email' : (sf.type === 'number' ? 'number' : 'text');
                    html += '<input type="' + inputType + '" class="iqw-input" name="' + this.esc(subKey) + '" ' +
                        'placeholder="' + this.esc(sf.placeholder || '') + '" value="' + this.esc(val) + '" data-type="' + this.esc(sf.type || 'text') + '">';
                }
                if (sf.help_text) {
                    html += '<div class="iqw-help-text">' + this.esc(sf.help_text) + '</div>';
                }
                html += '</div>';
            });
            html += '</div>';
            return html;
        }

        renderRadioCards(field) {
            const val = this.formData[field.key] || '';
            const optCount = (field.options || []).length;
            const useGrid = optCount > 4;
            let html = '';

            // Show label ALWAYS except: single field in step with <=2 options
            // (because step title already acts as the question, like Compare.com)
            const stepEl = this._currentRenderStep;
            const isSingleFieldStep = stepEl && stepEl.fields && stepEl.fields.length === 1;
            const hideLabel = isSingleFieldStep && optCount <= 2;

            if (field.label && !hideLabel) {
                html += this._labelHtml(field);
            }

            html += '<div class="iqw-radio-cards' + (useGrid ? ' grid' : '') + '" data-auto-advance="' + (optCount <= 4 ? '1' : '0') + '">';
            (field.options || []).forEach(opt => {
                const sel = val === opt.value ? ' selected' : '';
                html += '<label class="iqw-radio-card' + sel + '" tabindex="0" role="radio" aria-checked="' + (val === opt.value) + '">';
                html += '<input type="radio" name="' + this.esc(field.key) + '" value="' + this.esc(opt.value) + '"' + (val === opt.value ? ' checked' : '') + '>';
                if (!useGrid) html += '<span class="iqw-radio-indicator"></span>';
                html += '<span>' + this.esc(opt.label) + '</span>';
                html += '</label>';
            });
            html += '</div>';
            return html;
        }

        renderCheckboxGroup(field) {
            const vals = this.formData[field.key] || [];
            let html = this._labelHtml(field);
            html += '<div class="iqw-checkbox-group">';
            (field.options || []).forEach(opt => {
                const checked = Array.isArray(vals) && vals.includes(opt.value);
                html += '<label class="iqw-checkbox-item' + (checked ? ' selected' : '') + '" tabindex="0">';
                html += '<input type="checkbox" name="' + this.esc(field.key) + '[]" value="' + this.esc(opt.value) + '"' + (checked ? ' checked' : '') + '>';
                html += '<span>' + this.esc(opt.label) + '</span></label>';
            });
            html += '</div>';
            return html;
        }

        // ================================================================
        // PROGRESS BAR (with SVG icons like Compare.com)
        // ================================================================
        renderProgressBar() {
            const bar = this.$('#iqw-progress-' + this.formId);
            if (!bar) return;

            const icons = {
                shield: '<svg viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/></svg>',
                car: '<svg viewBox="0 0 24 24"><path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.5 16c-.83 0-1.5-.67-1.5-1.5S5.67 13 6.5 13s1.5.67 1.5 1.5S7.33 16 6.5 16zm11 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zM5 11l1.5-4.5h11L19 11H5z"/></svg>',
                user: '<svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>',
                home: '<svg viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>',
                contact: '<svg viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>',
                license: '<svg viewBox="0 0 24 24"><path d="M20 7h-4V4c0-1.1-.9-2-2-2h-4c-1.1 0-2 .9-2 2v3H4c-1.1 0-2 .9-2 2v11c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V9c0-1.1-.9-2-2-2zM10 4h4v3h-4V4z"/></svg>',
                notes: '<svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>',
                location: '<svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>'
            };

            // Get unique category icons from visible steps
            const categories = [];
            const seen = new Set();
            this.visibleSteps.forEach(i => {
                const icon = this.steps[i].icon || 'shield';
                if (!seen.has(icon) && categories.length < 5) {
                    seen.add(icon);
                    categories.push({ icon, index: categories.length });
                }
            });

            bar.innerHTML = '';
            categories.forEach((cat, i) => {
                const step = document.createElement('div');
                step.className = 'iqw-progress-step';

                const icon = document.createElement('div');
                icon.className = 'iqw-progress-icon';
                icon.innerHTML = icons[cat.icon] || icons.shield;
                icon.dataset.catIndex = i;
                step.appendChild(icon);

                if (i < categories.length - 1) {
                    const line = document.createElement('div');
                    line.className = 'iqw-progress-line';
                    line.dataset.catIndex = i;
                    step.appendChild(line);
                }

                bar.appendChild(step);
            });

            this._progressCategories = categories;
        }

        updateProgressBar() {
            const bar = this.$('#iqw-progress-' + this.formId);
            if (!bar || !this._progressCategories) return;

            // Calculate progress as percentage of visible steps completed
            const visIdx = this.visibleSteps.indexOf(this.currentStep);
            const totalVisible = this.visibleSteps.length;
            const catCount = this._progressCategories.length;

            // Map visible step progress to category icons proportionally
            const progress = totalVisible > 1 ? visIdx / (totalVisible - 1) : 0;
            const currentCatIndex = Math.min(Math.round(progress * (catCount - 1)), catCount - 1);

            bar.querySelectorAll('.iqw-progress-icon').forEach((icon, i) => {
                icon.classList.remove('active', 'completed');
                if (i < currentCatIndex) icon.classList.add('completed');
                else if (i === currentCatIndex) icon.classList.add('active');
            });
            bar.querySelectorAll('.iqw-progress-line').forEach((line, i) => {
                line.classList.remove('completed', 'active');
                if (i < currentCatIndex) line.classList.add('completed');
                else if (i === currentCatIndex) line.classList.add('active');
            });
        }

        // ================================================================
        // NAVIGATION
        // ================================================================
        bindNavigation() {
            const nextBtn = this.$('#iqw-next-' + this.formId);
            const backBtn = this.$('#iqw-back-' + this.formId);
            if (nextBtn) nextBtn.addEventListener('click', () => this.nextStep());
            if (backBtn) backBtn.addEventListener('click', (e) => { e.preventDefault(); this.prevStep(); });
        }

        bindKeyboard() {
            this.container.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    const tag = e.target.tagName;
                    if (tag === 'TEXTAREA') return;
                    e.preventDefault();
                    this.nextStep();
                }
            });
        }

        showStep(index) {
            const all = this.container.querySelectorAll('.iqw-step');
            all.forEach(s => { s.classList.remove('active', 'reverse'); s.style.display = 'none'; });

            const stepEl = this.$('#iqw-step-' + this.formId + '-' + index);
            if (!stepEl) return;

            stepEl.style.display = 'block';
            stepEl.classList.add('active');
            if (this.direction === 'backward') stepEl.classList.add('reverse');

            this.currentStep = index;

            // Update step counter
            const visIdx = this.visibleSteps.indexOf(index);
            const counter = stepEl.querySelector('.iqw-step-counter');
            if (counter) {
                counter.textContent = 'Step ' + (visIdx + 1) + ' of ' + this.visibleSteps.length;
            }

            // Update nav buttons
            const backBtn = this.$('#iqw-back-' + this.formId);
            const nextBtn = this.$('#iqw-next-' + this.formId);
            backBtn.style.display = visIdx > 0 ? '' : 'none';

            const isLast = visIdx === this.visibleSteps.length - 1;
            nextBtn.textContent = isLast ? (this.config.strings.submit || 'Get My Quotes') : (this.config.strings.next || 'Next');

            this.updateProgressBar();

            // Focus first input
            setTimeout(() => {
                const first = stepEl.querySelector('input:not([type="hidden"]):not([type="radio"]):not([type="checkbox"]), select, textarea');
                if (first && first.type !== 'date') first.focus();
            }, 350);

            // Scroll to form
            const rect = this.container.getBoundingClientRect();
            if (rect.top < 0) {
                this.container.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        nextStep() {
            if (this.isSubmitting) return;
            if (!this.validateStep(this.currentStep)) return;

            this.collectStepData(this.currentStep);
            this.calculateVisibleSteps();

            const visIdx = this.visibleSteps.indexOf(this.currentStep);

            // Track analytics: first step = start, rest = step reached
            if (visIdx === 0 && !this._startTracked) {
                this._startTracked = true;
                this._trackEvent('iqw_track_start', this.currentStep);
            } else {
                this._trackEvent('iqw_track_step', this.currentStep);
                this._savePartialEntry();
            }

            if (visIdx >= this.visibleSteps.length - 1) {
                // If review step enabled, show it before submit
                const reviewEnabled = this.config.config.settings && this.config.config.settings.review_step;
                if (reviewEnabled && !this._reviewShown) {
                    this._reviewShown = true;
                    this.showReviewStep();
                    return;
                }
                this.submitForm();
                return;
            }

            this.direction = 'forward';
            const nextIndex = this.visibleSteps[visIdx + 1];
            this.showStep(nextIndex);
        }

        prevStep() {
            // If on review step, go back
            if (this._reviewShown) {
                this._reviewShown = false;
                const wrapper = this.$('#iqw-steps-' + this.formId);
                const reviewEl = wrapper.querySelector('.iqw-review-step');
                if (reviewEl) reviewEl.remove();
                if (this.isSingleMode) {
                    // Re-show all single-mode sections
                    this.container.querySelectorAll('.iqw-single-section').forEach(s => s.style.display = '');
                } else {
                    this.showStep(this.visibleSteps[this.visibleSteps.length - 1]);
                }
                return;
            }

            this.collectStepData(this.currentStep);
            this.calculateVisibleSteps();

            const visIdx = this.visibleSteps.indexOf(this.currentStep);
            if (visIdx <= 0) return;

            this.direction = 'backward';
            this.showStep(this.visibleSteps[visIdx - 1]);
        }

        // Jump to step (for review "Edit" buttons)
        jumpToStep(stepIndex) {
            this._reviewShown = false;
            const wrapper = this.$('#iqw-steps-' + this.formId);
            const reviewEl = wrapper.querySelector('.iqw-review-step');
            if (reviewEl) reviewEl.remove();
            if (this.isSingleMode) {
                // Re-show all single-mode sections, scroll to the target section
                this.container.querySelectorAll('.iqw-single-section').forEach(s => s.style.display = '');
                const target = this.$('#iqw-step-' + this.formId + '-' + stepIndex);
                if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } else {
                this.direction = 'backward';
                this.showStep(stepIndex);
            }
        }

        // Track analytics event (non-blocking)
        _trackEvent(action, step) {
            const fd = new FormData();
            fd.append('action', action);
            fd.append('form_id', this.formId);
            fd.append('step', step);
            fd.append('iqw_nonce', this.config.nonce);
            fetch(this.config.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' }).catch(() => {});
        }

        // Auto-fill city/state/zip from visitor IP
        _initGeolocation() {
            if (!this.config.geolocationEnabled) return;

            const geoMap = {
                city: ['city', 'your_city'],
                state: ['state', 'your_state'],
                state_code: ['state_code', 'state_abbr'],
                zip: ['zip', 'zip_code', 'zipcode', 'postal_code', 'your_zip'],
                country: ['country'],
            };

            const allFields = [];
            (this.steps || []).forEach(step => {
                (step.fields || []).forEach(f => allFields.push(f));
            });
            const allKeys = allFields.map(f => f.key);

            let hasGeoField = false;
            Object.values(geoMap).forEach(keys => {
                keys.forEach(k => { if (allKeys.includes(k)) hasGeoField = true; });
            });

            if (!hasGeoField) return;

            fetch(this.config.restUrl + 'geolocation', { credentials: 'same-origin' })
                .then(r => r.json())
                .then(data => {
                    if (!data || data.code) return;

                    Object.entries(geoMap).forEach(([geoKey, fieldKeys]) => {
                        const val = data[geoKey];
                        if (!val) return;
                        fieldKeys.forEach(fk => {
                            if (!allKeys.includes(fk)) return;
                            if (this.formData[fk]) return;

                            this.formData[fk] = val;
                            const input = this.container.querySelector('[name="' + fk + '"]');
                            if (input) {
                                input.value = val;
                                if (input.tagName === 'SELECT') {
                                    for (let i = 0; i < input.options.length; i++) {
                                        const opt = input.options[i];
                                        if (opt.value.toLowerCase() === val.toLowerCase() || opt.text.toLowerCase() === val.toLowerCase()) {
                                            input.selectedIndex = i;
                                            this.formData[fk] = opt.value;
                                            break;
                                        }
                                    }
                                }
                            }
                        });
                    });

                    this._evaluateCalculatedFields();
                })
                .catch(() => {});
        }

        // Save partial entry for abandonment tracking
        _savePartialEntry() {
            const fd = new FormData();
            fd.append('action', 'iqw_save_partial');
            fd.append('form_id', this.formId);
            fd.append('iqw_nonce', this.config.nonce);
            fd.append('current_step', this.currentStep);
            fd.append('total_steps', this.visibleSteps.length);
            if (this._abandonId) fd.append('abandon_id', this._abandonId);

            Object.keys(this.formData).forEach(key => {
                const val = this.formData[key];
                if (Array.isArray(val)) {
                    val.forEach(v => fd.append('fields[' + key + '][]', v));
                } else {
                    fd.append('fields[' + key + ']', val);
                }
            });

            fetch(this.config.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(r => r.json())
                .then(res => {
                    if (res.success && res.data && res.data.abandon_id) {
                        this._abandonId = res.data.abandon_id;
                    }
                })
                .catch(() => {});
        }

        // Render review/summary step
        showReviewStep() {
            const wrapper = this.$('#iqw-steps-' + this.formId);

            // Hide all steps
            wrapper.querySelectorAll('.iqw-step').forEach(s => {
                s.classList.remove('active');
                s.style.display = 'none';
            });

            // Remove old review if exists
            const old = wrapper.querySelector('.iqw-review-step');
            if (old) old.remove();

            // Build review HTML
            let html = '<div class="iqw-step iqw-review-step active" style="display:block;">';
            html += '<div class="iqw-step-counter">Review Your Answers</div>';
            html += '<h2 class="iqw-step-title">Please review before submitting</h2>';

            this.visibleSteps.forEach(si => {
                const step = this.steps[si];
                if (!step || !step.fields) return;

                let hasData = false;
                step.fields.forEach(f => {
                    const v = this.formData[f.key];
                    if (v && v !== '' && (!Array.isArray(v) || v.length > 0)) hasData = true;
                });
                if (!hasData) return;

                html += '<div class="iqw-review-section">';
                html += '<div class="iqw-review-section-header">';
                html += '<span class="iqw-review-section-title">' + this.esc(step.title) + '</span>';
                html += '<button type="button" class="iqw-review-edit-btn" data-step="' + si + '">✎ Edit</button>';
                html += '</div>';

                step.fields.forEach(f => {
                    if (f.type === 'heading' || f.type === 'paragraph' || f.type === 'consent') return;
                    if (f.conditions && f.conditions.rules && f.conditions.rules.length && !this.evaluateConditions(f.conditions)) return;

                    let val = this.formData[f.key] || '';
                    if (Array.isArray(val)) val = val.join(', ');
                    if (!val) return;

                    // Map value to label for options fields
                    if (f.options) {
                        const opt = f.options.find(o => o.value === val);
                        if (opt) val = opt.label;
                    }

                    html += '<div class="iqw-review-row">';
                    html += '<span class="iqw-review-label">' + this.esc(f.label || f.key) + '</span>';
                    html += '<span class="iqw-review-value">' + this.esc(val) + '</span>';
                    html += '</div>';
                });

                html += '</div>';
            });

            html += '</div>';
            wrapper.insertAdjacentHTML('beforeend', html);

            // Bind edit buttons
            wrapper.querySelectorAll('.iqw-review-edit-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    this.jumpToStep(parseInt(btn.dataset.step));
                });
            });

            // Update nav
            const backBtn = this.$('#iqw-back-' + this.formId);
            if (backBtn) backBtn.style.display = '';
            const nextBtn = this.$('#iqw-next-' + this.formId);
            if (nextBtn) nextBtn.textContent = this.config.strings.submit || 'Get My Quotes';

            this.updateProgressBar();
        }

        // ================================================================
        // DATA COLLECTION
        // ================================================================
        collectStepData(stepIndex) {
            const stepEl = this.$('#iqw-step-' + this.formId + '-' + stepIndex);
            if (!stepEl) return;

            stepEl.querySelectorAll('input, select, textarea').forEach(el => {
                const name = el.name.replace('[]', '');
                if (!name || name === 'action' || name === 'form_id' || name.startsWith('iqw_')) return;

                if (el.type === 'radio') {
                    if (el.checked) this.formData[name] = el.value;
                } else if (el.type === 'checkbox') {
                    if (!Array.isArray(this.formData[name])) this.formData[name] = [];
                    const arr = this.formData[name];
                    if (el.checked && !arr.includes(el.value)) arr.push(el.value);
                    else if (!el.checked) this.formData[name] = arr.filter(v => v !== el.value);
                } else {
                    this.formData[name] = el.value;
                }
            });
        }

        // ================================================================
        // VALIDATION
        // ================================================================
        validateStep(stepIndex) {
            const step = this.steps[stepIndex];
            if (!step || !step.fields) return true;

            let valid = true;
            const stepEl = this.$('#iqw-step-' + this.formId + '-' + stepIndex);

            step.fields.forEach(field => {
                if (field.type === 'heading' || field.type === 'paragraph') return;

                // Check if field is conditionally hidden
                if (field.conditions && !this.evaluateConditions(field.conditions)) return;

                const err = this.validateField(field, stepEl);
                const errEl = this.$('#iqw-error-' + field.key);
                if (errEl) {
                    errEl.textContent = err || '';
                    errEl.classList.toggle('visible', !!err);
                }

                const wrap = stepEl.querySelector('[data-field-key="' + field.key + '"]');
                if (wrap) {
                    const inp = wrap.querySelector('.iqw-input, .iqw-select, .iqw-textarea');
                    if (inp) {
                        inp.classList.toggle('error', !!err);
                        if (!err && inp.value) inp.classList.add('valid');
                    }
                }

                if (err) valid = false;
            });

            return valid;
        }

        validateField(field, stepEl) {
            let value = '';

            if (field.type === 'radio_cards' || field.type === 'radio') {
                const checked = stepEl.querySelector('input[name="' + field.key + '"]:checked');
                value = checked ? checked.value : '';
            } else if (field.type === 'checkbox_group') {
                const checked = stepEl.querySelectorAll('input[name="' + field.key + '[]"]:checked');
                value = checked.length > 0 ? Array.from(checked).map(c => c.value) : [];
            } else {
                const input = stepEl.querySelector('[name="' + field.key + '"]');
                value = input ? input.value.trim() : '';
            }

            // Required
            if (field.required) {
                const empty = Array.isArray(value) ? value.length === 0 : !value;
                if (empty) return this.config.strings.required || 'This field is required.';
            }

            if (!value || (Array.isArray(value) && !value.length)) return null;

            // Type validation
            if (field.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                return this.config.strings.invalidEmail || 'Please enter a valid email.';
            }
            if (field.type === 'phone') {
                const digits = value.replace(/\D/g, '');
                if (digits.length < 10) return this.config.strings.invalidPhone || 'Please enter a valid 10-digit phone number.';
            }
            if (field.validation === 'zip' && !/^\d{5}(-\d{4})?$/.test(value)) {
                return 'Please enter a valid 5-digit ZIP code.';
            }
            if (field.validation === 'vin' && value && !/^[A-HJ-NPR-Z0-9*]{10,17}$/i.test(value)) {
                return 'Please enter a valid VIN.';
            }
            if (field.validation === 'dob_driver') {
                const age = this.calcAge(value);
                if (age < 15) return 'Driver must be at least 15 years old.';
                if (age > 100) return 'Please check the date of birth.';
            }
            if (field.type === 'number' || field.type === 'currency') {
                const num = parseFloat(value.replace(/[^\d.]/g, ''));
                if (isNaN(num)) return 'Please enter a valid number.';
                if (field.min !== undefined && num < field.min) return 'Minimum value is ' + field.min + '.';
                if (field.max !== undefined && num > field.max) return 'Maximum value is ' + field.max + '.';
            }
            if (field.type === 'url' && value && !/^https?:\/\/.+\..+/.test(value)) {
                return 'Please enter a valid URL (e.g., https://example.com).';
            }
            if (field.type === 'consent' && field.required && value !== 'yes') {
                return 'You must agree to continue.';
            }

            return null;
        }

        calcAge(dateStr) {
            try {
                const birth = new Date(dateStr);
                const now = new Date();
                let age = now.getFullYear() - birth.getFullYear();
                const m = now.getMonth() - birth.getMonth();
                if (m < 0 || (m === 0 && now.getDate() < birth.getDate())) age--;
                return age;
            } catch(e) { return 0; }
        }

        getMaxDOB() {
            const d = new Date();
            d.setFullYear(d.getFullYear() - 15);
            return d.toISOString().split('T')[0];
        }

        // ================================================================
        // SUBMISSION
        // ================================================================
        submitForm() {
            if (this.isSubmitting) return;
            this.isSubmitting = true;

            const nextBtn = this.$('#iqw-next-' + this.formId);
            nextBtn.classList.add('loading');
            nextBtn.disabled = true;

            // Step 1: reCAPTCHA (if enabled)
            const getRecaptcha = () => {
                return new Promise(resolve => {
                    if (this.config.recaptchaEnabled && this.config.recaptchaSiteKey && window.grecaptcha) {
                        window.grecaptcha.ready(() => {
                            window.grecaptcha.execute(this.config.recaptchaSiteKey, { action: 'iqw_submit' })
                                .then(token => resolve(token))
                                .catch(() => resolve(''));
                        });
                    } else {
                        resolve('');
                    }
                });
            };

            // Step 2: Stripe payment (if payment field exists)
            const processPayment = () => {
                return new Promise((resolve, reject) => {
                    const cardMount = this.container.querySelector('.iqw-stripe-card-element');
                    if (!cardMount || !cardMount._iqwCard || !this._stripeInstance) {
                        resolve(''); // No payment field — proceed without payment
                        return;
                    }

                    // Get amount from form
                    const amountInput = this.container.querySelector('.iqw-payment-amount, input[name$="_amount"]');
                    const amount = amountInput ? parseFloat(amountInput.value) : 0;
                    if (amount <= 0) {
                        reject('Please enter a valid payment amount.');
                        return;
                    }

                    // Step 2a: Create PaymentIntent on server
                    const intentFd = new FormData();
                    intentFd.append('action', 'iqw_create_payment_intent');
                    intentFd.append('iqw_nonce', this.config.nonce);
                    intentFd.append('form_id', this.formId);
                    intentFd.append('amount', amount);

                    fetch(this.config.ajaxUrl, { method: 'POST', body: intentFd, credentials: 'same-origin' })
                        .then(r => r.json())
                        .then(res => {
                            if (!res.success || !res.data.client_secret) {
                                reject(res.data || 'Payment setup failed.');
                                return;
                            }

                            // Step 2b: Confirm card payment with Stripe
                            this._stripeInstance.confirmCardPayment(res.data.client_secret, {
                                payment_method: { card: cardMount._iqwCard }
                            }).then(result => {
                                if (result.error) {
                                    reject(result.error.message);
                                } else if (result.paymentIntent && result.paymentIntent.status === 'succeeded') {
                                    resolve(result.paymentIntent.id);
                                } else {
                                    reject('Payment was not completed.');
                                }
                            });
                        })
                        .catch(() => reject('Payment processing error. Please try again.'));
                });
            };

            // Execute: reCAPTCHA → Stripe → Submit
            getRecaptcha().then(recaptchaToken => {
                processPayment()
                    .then(paymentIntentId => {
                        this._doSubmit(recaptchaToken, paymentIntentId);
                    })
                    .catch(err => {
                        this.showFormError(err);
                        this.resetSubmitBtn(nextBtn);
                    });
            });
        }

        _doSubmit(recaptchaToken, paymentIntentId) {
            const nextBtn = this.$('#iqw-next-' + this.formId);
            const formEl = this.$('#iqw-form-' + this.formId);
            const fd = new FormData(formEl);

            // Add all collected data
            Object.keys(this.formData).forEach(key => {
                const val = this.formData[key];
                if (Array.isArray(val)) {
                    val.forEach(v => fd.append('fields[' + key + '][]', v));
                } else {
                    fd.set('fields[' + key + ']', val);
                }
            });

            // Add reCAPTCHA token if present
            if (recaptchaToken) {
                fd.set('iqw_recaptcha_token', recaptchaToken);
            }

            // Add Stripe payment intent ID if payment was processed
            if (paymentIntentId) {
                fd.set('iqw_payment_intent_id', paymentIntentId);
            }

            // Send draft token so server can cleanup after successful submit
            if (this.draftToken) {
                fd.set('iqw_draft_token', this.draftToken);
            }

            // 30-second timeout — releases stuck loading state on poor connections
            const _abortCtrl = new AbortController();
            const _timeoutId = setTimeout(() => _abortCtrl.abort(), 30000);

            fetch(this.config.ajaxUrl, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                signal: _abortCtrl.signal,
            })
            .then(res => { clearTimeout(_timeoutId); return res.json(); })
            .then(response => {
                if (response.success) {
                    // Hide form elements
                    formEl.style.display = 'none';
                    const progressEl = this.$('#iqw-progress-' + this.formId);
                    if (progressEl) progressEl.style.display = 'none';
                    const mot = this.container.querySelector('.iqw-motivation');
                    if (mot) mot.style.display = 'none';
                    const nav = this.$('#iqw-nav-' + this.formId);
                    if (nav) nav.style.display = 'none';

                    const settings = this.config.config.settings || {};
                    const confType = settings.confirmation_type || 'message';
                    const entryId = (response.data && response.data.entry_id) || '';

                    // Merge tag replacer
                    const replaceTags = (text) => {
                        return (text || '').replace(/\{([^}]+)\}/g, (m, key) => {
                            if (key === 'entry_id') return entryId;
                            if (key === 'form_title') return this.config.config.title || '';
                            return this.formData[key] || m;
                        });
                    };

                    // Shortcode-level redirect_url overrides form setting (popup per-button redirect)
                    const redirectOverride = this.container.dataset.redirect || '';

                    if ((confType === 'redirect' && settings.redirect_url) || redirectOverride) {
                        // Show brief success then redirect
                        const successEl = this.$('#iqw-success-' + this.formId);
                        if (successEl) {
                            const textEl = successEl.querySelector('.iqw-success-text');
                            if (textEl) textEl.textContent = 'Redirecting you now...';
                            successEl.style.display = 'block';
                        }
                        const targetUrl = redirectOverride || settings.redirect_url;
                        setTimeout(() => { window.location.href = targetUrl; }, 1200);

                    } else if (confType === 'page' && settings.confirmation_content) {
                        // Rich confirmation page with merge tags
                        const content = replaceTags(settings.confirmation_content);
                        const successEl = this.$('#iqw-success-' + this.formId);
                        if (successEl) {
                            successEl.innerHTML = '<div class="iqw-confirmation-page">' + content + '</div>';
                            successEl.style.display = 'block';
                        }

                    } else {
                        // Default: show success message with merge tags
                        const successEl = this.$('#iqw-success-' + this.formId);
                        if (successEl) {
                            const msg = replaceTags(settings.success_message || this.config.strings.success || 'Thank you!');
                            // Use textContent (not innerHTML) to prevent DOM XSS from merge tag values
                            const textEl = successEl.querySelector('.iqw-success-text') || successEl.querySelector('p');
                            if (textEl) textEl.textContent = msg;
                            successEl.style.display = 'block';
                        }
                    }

                    // Legacy redirect support
                    if (confType !== 'redirect' && !redirectOverride && response.data && response.data.redirect) {
                        setTimeout(() => { window.location.href = response.data.redirect; }, 2000);
                    }
                } else {
                    // Show inline error instead of ugly alert
                    const msg = (response.data && response.data.message) || this.config.strings.error || 'Something went wrong.';
                    this.showFormError(msg);

                    // If validation errors, show them on specific fields
                    if (response.data && response.data.errors) {
                        Object.keys(response.data.errors).forEach(key => {
                            const errEl = this.$('#iqw-error-' + key);
                            if (errEl) {
                                errEl.textContent = response.data.errors[key];
                                errEl.classList.add('visible');
                            }
                        });
                    }

                    this.resetSubmitBtn(nextBtn);
                }
            })
            .catch(err => {
                clearTimeout(_timeoutId);
                const msg = err && err.name === 'AbortError'
                    ? 'Request timed out. Please check your connection and try again.'
                    : (this.config.strings.error || 'Something went wrong. Please try again.');
                this.showFormError(msg);
                this.resetSubmitBtn(nextBtn);
            });
        }

        resetSubmitBtn(btn) {
            this.isSubmitting = false;
            btn.classList.remove('loading');
            btn.disabled = false;
            btn.textContent = this.config.strings.submit || 'Get My Quotes';
        }

        // ================================================================
        // FIELD EVENTS
        // ================================================================
        bindFieldEvents() {
            // Radio cards - click + auto-advance
            this.container.querySelectorAll('.iqw-radio-card').forEach(card => {
                card.addEventListener('click', (e) => {
                    e.preventDefault();
                    const input = card.querySelector('input');
                    const name = input.name;
                    const cards = this.container.querySelectorAll('.iqw-radio-card input[name="' + name + '"]');

                    cards.forEach(r => {
                        r.closest('.iqw-radio-card').classList.remove('selected');
                        r.closest('.iqw-radio-card').setAttribute('aria-checked', 'false');
                    });

                    card.classList.add('selected');
                    card.setAttribute('aria-checked', 'true');
                    input.checked = true;

                    // Store value immediately
                    this.formData[name] = input.value;

                    // Clear error
                    const errEl = this.$('#iqw-error-' + name);
                    if (errEl) errEl.classList.remove('visible');

                    // Re-evaluate field conditions (show/hide dependent fields)
                    this.reevaluateFieldConditions();
                    this._evaluateCalculatedFields();

                    // Auto-advance if it's the only field in the step and <= 4 options
                    const stepEl = card.closest('.iqw-step');
                    const step = this.steps[parseInt(stepEl.dataset.stepIndex)];
                    if (step && step.fields && step.fields.length === 1) {
                        const group = card.closest('.iqw-radio-cards');
                        if (group && group.dataset.autoAdvance === '1') {
                            setTimeout(() => this.nextStep(), 350);
                        }
                    }
                });

                // Keyboard: Enter/Space to select
                card.addEventListener('keydown', (e) => {
                    if (e.key === ' ' || e.key === 'Enter') {
                        e.preventDefault();
                        card.click();
                    }
                });
            });

            // Checkbox cards
            this.container.querySelectorAll('.iqw-checkbox-item').forEach(item => {
                const handler = (e) => {
                    const cb = item.querySelector('input');
                    if (e.target !== cb) {
                        e.preventDefault();
                        cb.checked = !cb.checked;
                    }
                    item.classList.toggle('selected', cb.checked);
                };
                item.addEventListener('click', handler);
                item.addEventListener('keydown', (e) => {
                    if (e.key === ' ' || e.key === 'Enter') { e.preventDefault(); handler(e); }
                });
            });

            // Input focus - clear error + re-evaluate conditions on change
            this.container.querySelectorAll('.iqw-input, .iqw-select, .iqw-textarea').forEach(input => {
                input.addEventListener('focus', () => {
                    input.classList.remove('error');
                    const name = input.name;
                    const errEl = this.$('#iqw-error-' + name);
                    if (errEl) errEl.classList.remove('visible');
                });

                // Re-evaluate conditions on every change (for selects, inputs that trigger conditions)
                input.addEventListener('change', () => {
                    const name = input.name;
                    if (name) this.formData[name] = input.value;
                    this.reevaluateFieldConditions();
                    this._evaluateCalculatedFields();
                });

                // Real-time calc update on input (for number fields etc.)
                input.addEventListener('input', () => {
                    const name = input.name;
                    if (name) this.formData[name] = input.value;
                    this._evaluateCalculatedFields();
                });

                // Real-time validation on blur
                input.addEventListener('blur', () => {
                    const name = input.name;
                    const stepEl = input.closest('.iqw-step');
                    const step = this.steps[parseInt(stepEl?.dataset?.stepIndex)];
                    if (!step) return;

                    const field = step.fields?.find(f => f.key === name);
                    if (field && input.value) {
                        const err = this.validateField(field, stepEl);
                        const errEl = this.$('#iqw-error-' + name);
                        if (errEl) {
                            errEl.textContent = err || '';
                            errEl.classList.toggle('visible', !!err);
                        }
                        input.classList.toggle('error', !!err);
                        if (!err) input.classList.add('valid');
                    }
                });
            });

            // Phone auto-formatting
            this.container.querySelectorAll('.iqw-phone-input').forEach(input => {
                input.addEventListener('input', () => {
                    let v = input.value.replace(/\D/g, '');
                    if (v.length > 10) v = v.substring(0, 10);
                    if (v.length >= 7) v = '(' + v.substring(0,3) + ') ' + v.substring(3,6) + '-' + v.substring(6);
                    else if (v.length >= 4) v = '(' + v.substring(0,3) + ') ' + v.substring(3);
                    else if (v.length >= 1) v = '(' + v;
                    input.value = v;
                });
            });

            // Date dropdown selectors - combine into hidden field
            this.container.querySelectorAll('.iqw-date-selects').forEach(wrap => {
                const selects = wrap.querySelectorAll('select');
                const hidden = wrap.querySelector('.iqw-date-hidden');
                const fieldKey = wrap.dataset.fieldKey;
                selects.forEach(sel => {
                    sel.addEventListener('change', () => {
                        const m = wrap.querySelector('.iqw-date-month').value;
                        const d = wrap.querySelector('.iqw-date-day').value;
                        const y = wrap.querySelector('.iqw-date-year').value;
                        if (m && d && y) {
                            hidden.value = y + '-' + m + '-' + d;
                            this.formData[fieldKey] = hidden.value;
                        } else {
                            hidden.value = '';
                        }
                        // Clear error on change
                        const errEl = this.$('#iqw-error-' + fieldKey);
                        if (errEl) errEl.classList.remove('visible');
                    });
                });
            });

            // Currency formatting
            this.container.querySelectorAll('.iqw-currency-input').forEach(input => {
                input.addEventListener('blur', () => {
                    let v = input.value.replace(/[^\d.]/g, '');
                    if (v && !isNaN(v)) {
                        input.value = parseFloat(v).toLocaleString('en-US');
                    }
                });
                input.addEventListener('focus', () => {
                    input.value = input.value.replace(/,/g, '');
                });
            });

            // File upload preview
            this.container.querySelectorAll('.iqw-file-input').forEach(input => {
                const wrap = input.closest('.iqw-file-upload-wrap');
                const preview = wrap.querySelector('.iqw-file-preview');
                const label = wrap.querySelector('.iqw-file-label');
                const maxMB = parseInt(input.dataset.maxSize) || 10;

                input.addEventListener('change', () => {
                    const file = input.files[0];
                    if (!file) { preview.style.display = 'none'; label.style.display = ''; return; }
                    if (file.size > maxMB * 1024 * 1024) {
                        const errEl = this.$('#iqw-error-' + input.dataset.fieldKey);
                        if (errEl) { errEl.textContent = 'File too large. Max ' + maxMB + 'MB.'; errEl.classList.add('visible'); }
                        input.value = '';
                        return;
                    }
                    label.style.display = 'none';
                    preview.style.display = 'flex';
                    preview.innerHTML = '<span class="iqw-file-name">📄 ' + this.esc(file.name) + ' (' + (file.size / 1024 / 1024).toFixed(1) + 'MB)</span>' +
                        '<button type="button" class="iqw-file-remove" onclick="this.closest(\'.iqw-file-upload-wrap\').querySelector(\'input\').value=\'\';this.closest(\'.iqw-file-preview\').style.display=\'none\';this.closest(\'.iqw-file-upload-wrap\').querySelector(\'.iqw-file-label\').style.display=\'\';">&times;</button>';
                });
            });

            // Consent checkbox
            this.container.querySelectorAll('.iqw-consent-input').forEach(cb => {
                cb.addEventListener('change', () => {
                    this.formData[cb.name] = cb.checked ? 'yes' : '';
                    const errEl = this.$('#iqw-error-' + cb.name);
                    if (errEl) errEl.classList.remove('visible');
                });
            });

            // Repeater add/remove
            this.container.querySelectorAll('.iqw-repeater-add').forEach(btn => {
                btn.addEventListener('click', () => {
                    const key = btn.dataset.fieldKey;
                    const repeater = this.container.querySelector('.iqw-repeater[data-field-key="' + key + '"]');
                    if (!repeater) return;
                    const max = parseInt(repeater.dataset.max) || 5;
                    const items = repeater.querySelectorAll('.iqw-repeater-item');
                    if (items.length >= max) return;

                    // Find the step index from the containing step/section element
                    const parentStep = btn.closest('[data-step-index]');
                    const stepIdx = parentStep ? parseInt(parentStep.dataset.stepIndex) : this.currentStep;

                    this.collectStepData(stepIdx);
                    this.formData['__repeater_' + key + '_count'] = items.length + 1;

                    // Re-render this step
                    const stepEl = this.$('#iqw-step-' + this.formId + '-' + stepIdx);
                    const step = this.steps[stepIdx];
                    if (stepEl && step) {
                        let html = this.isSingleMode
                            ? '<h3 class="iqw-single-section-title">' + this.esc(step.title) + '</h3>'
                            : '<div class="iqw-step-counter"></div><h2 class="iqw-step-title">' + this.esc(step.title) + '</h2>';
                        if (step.fields) {
                            this._currentRenderStep = step;
                            html += this._renderFieldsHtml(step.fields);
                            this._currentRenderStep = null;
                        }
                        stepEl.innerHTML = html;
                        this.bindFieldEvents();
                        if (!this.isSingleMode) {
                            const visIdx = this.visibleSteps.indexOf(stepIdx);
                            const counter = stepEl.querySelector('.iqw-step-counter');
                            if (counter) counter.textContent = 'Step ' + (visIdx + 1) + ' of ' + this.visibleSteps.length;
                        }
                    }
                });
            });

            this.container.querySelectorAll('.iqw-repeater-remove').forEach(btn => {
                btn.addEventListener('click', () => {
                    const key = btn.dataset.fieldKey;
                    const idx = parseInt(btn.dataset.index);

                    // Find step index from DOM parent
                    const parentStep = btn.closest('[data-step-index]');
                    const stepIdx = parentStep ? parseInt(parentStep.dataset.stepIndex) : this.currentStep;

                    this.collectStepData(stepIdx);

                    // Remove repeater item data and re-index
                    const repeater = this.container.querySelector('.iqw-repeater[data-field-key="' + key + '"]');
                    const count = repeater ? repeater.querySelectorAll('.iqw-repeater-item').length : 1;
                    const step = this.steps[stepIdx];
                    const field = step ? step.fields.find(f => f.key === key) : null;
                    const subFields = field ? (field.sub_fields || []) : [];

                    // Shift data down
                    for (let i = idx; i < count - 1; i++) {
                        subFields.forEach(sf => {
                            this.formData[key + '_' + i + '_' + sf.key] = this.formData[key + '_' + (i + 1) + '_' + sf.key] || '';
                        });
                    }
                    // Clear last
                    subFields.forEach(sf => {
                        delete this.formData[key + '_' + (count - 1) + '_' + sf.key];
                    });

                    this.formData['__repeater_' + key + '_count'] = Math.max(1, count - 1);

                    // Re-render
                    const stepEl = this.$('#iqw-step-' + this.formId + '-' + stepIdx);
                    if (stepEl && step) {
                        let html = this.isSingleMode
                            ? '<h3 class="iqw-single-section-title">' + this.esc(step.title) + '</h3>'
                            : '<div class="iqw-step-counter"></div><h2 class="iqw-step-title">' + this.esc(step.title) + '</h2>';
                        this._currentRenderStep = step;
                        html += this._renderFieldsHtml(step.fields);
                        this._currentRenderStep = null;
                        stepEl.innerHTML = html;
                        this.bindFieldEvents();
                    }
                });
            });
        }

        // ================================================================
        // ADDRESS AUTOCOMPLETE (Google Places)
        // ================================================================
        initAddressAutocomplete() {
            if (!window.google || !window.google.maps || !window.google.maps.places) return;
            this.container.querySelectorAll('.iqw-address-autocomplete').forEach(input => {
                if (input._iqwAutocomplete) return; // Already initialized
                const autocomplete = new google.maps.places.Autocomplete(input, {
                    types: ['address'],
                    componentRestrictions: { country: 'us' },
                    fields: ['address_components', 'formatted_address']
                });
                input._iqwAutocomplete = autocomplete;
                const fieldKey = input.dataset.fieldKey;

                autocomplete.addListener('place_changed', () => {
                    const place = autocomplete.getPlace();
                    if (!place.address_components) return;
                    const parts = {};
                    place.address_components.forEach(c => {
                        if (c.types.includes('street_number')) parts.street_number = c.long_name;
                        if (c.types.includes('route')) parts.route = c.long_name;
                        if (c.types.includes('locality')) parts.city = c.long_name;
                        if (c.types.includes('administrative_area_level_1')) parts.state = c.short_name;
                        if (c.types.includes('postal_code')) parts.zip = c.long_name;
                    });

                    const street = (parts.street_number || '') + ' ' + (parts.route || '');
                    input.value = street.trim();
                    this.formData[fieldKey + '_street'] = street.trim();

                    const parent = input.closest('.iqw-address-group');
                    if (parent) {
                        const cityInput = parent.querySelector('[name="' + fieldKey + '_city"]');
                        const stateInput = parent.querySelector('[name="' + fieldKey + '_state"]');
                        const zipInput = parent.querySelector('[name="' + fieldKey + '_zip"]');
                        if (cityInput) { cityInput.value = parts.city || ''; this.formData[fieldKey + '_city'] = parts.city || ''; }
                        if (stateInput) { stateInput.value = parts.state || ''; this.formData[fieldKey + '_state'] = parts.state || ''; }
                        if (zipInput) { zipInput.value = parts.zip || ''; this.formData[fieldKey + '_zip'] = parts.zip || ''; }
                    }
                });
            });
        }

        // ================================================================
        // STRIPE PAYMENT ELEMENTS
        // ================================================================
        initStripeElements() {
            if (!window.Stripe || !this.config.stripeKey) return;
            if (this._stripeInstance) return;
            this._stripeInstance = Stripe(this.config.stripeKey);

            this.container.querySelectorAll('.iqw-stripe-card-element').forEach(mountPoint => {
                if (mountPoint._iqwCardMounted) return;
                const elements = this._stripeInstance.elements();
                const card = elements.create('card', {
                    style: {
                        base: { fontSize: '15px', color: '#333', fontFamily: 'inherit', '::placeholder': { color: '#aab7c4' } },
                        invalid: { color: '#e74c3c' }
                    }
                });
                card.mount(mountPoint);
                mountPoint._iqwCardMounted = true;
                mountPoint._iqwCard = card;
                mountPoint._iqwElements = elements;

                const errDiv = mountPoint.nextElementSibling;
                card.on('change', (event) => {
                    if (errDiv) errDiv.textContent = event.error ? event.error.message : '';
                });
            });
        }
        applyUrlParams() {
            const params = new URLSearchParams(window.location.search);
            params.forEach((value, key) => {
                if (key.startsWith('iqw_') || key === 'page' || key === 'id') return;
                this.formData[key] = decodeURIComponent(value);
            });
        }

        // Apply preset values injected via data-preset attribute (popup hidden fields)
        _applyPreset() {
            const presetAttr = this.container.dataset.preset;
            if (!presetAttr) return;
            try {
                const preset = JSON.parse(presetAttr);
                if (preset && typeof preset === 'object') {
                    Object.assign(this.formData, preset);
                }
            } catch(e) {
                // ignore malformed JSON
            }
        }

        // Re-evaluate field-level conditions on current step (live show/hide)
        reevaluateFieldConditions() {
            // In single mode, evaluate ALL visible steps
            const stepsToCheck = this.isSingleMode ? this.visibleSteps : [this.currentStep];

            stepsToCheck.forEach(si => {
                const stepEl = this.$('#iqw-step-' + this.formId + '-' + si);
                if (!stepEl) return;
                const step = this.steps[si];
                if (!step || !step.fields) return;

                // Collect data from this step
                this.collectStepData(si);

                step.fields.forEach(field => {
                    if (!field.conditions || !field.conditions.rules || !field.conditions.rules.length) return;
                    const wrap = stepEl.querySelector('[data-field-key="' + field.key + '"]');
                    if (!wrap) return;

                    const visible = this.evaluateConditions(field.conditions);
                    wrap.style.display = visible ? '' : 'none';
                });
            });

            // Also re-evaluate step visibility
            this.calculateVisibleSteps();
        }

        // ================================================================
        // UTILITIES
        // ================================================================
        $(selector) {
            return document.querySelector(selector);
        }

        esc(str) {
            if (str === null || str === undefined) return '';
            const div = document.createElement('div');
            div.textContent = String(str);
            return div.innerHTML;
        }

        showFormError(msg) {
            this.hideFormError();
            const errDiv = document.createElement('div');
            errDiv.className = 'iqw-form-error-banner';
            errDiv.id = 'iqw-form-error-' + this.formId;
            errDiv.innerHTML = '<span class="iqw-form-error-icon">!</span> ' + this.esc(msg);
            const nav = this.$('#iqw-nav-' + this.formId);
            if (nav) nav.parentNode.insertBefore(errDiv, nav);
            // Auto dismiss after 8 seconds
            setTimeout(() => this.hideFormError(), 8000);
        }

        hideFormError() {
            const existing = this.$('#iqw-form-error-' + this.formId);
            if (existing) existing.remove();
        }
    }

    // ================================================================
    // AUTO INIT (with error boundary)
    // ================================================================
    function initAll() {
        document.querySelectorAll('.iqw-wizard-container').forEach(c => {
            const id = c.dataset.formId;
            if (id) {
                try {
                    new IQWWizard(c.id, id);
                } catch(err) {
                    console.error('IQW Wizard init error for form #' + id + ':', err);
                    c.innerHTML = '<div style="padding:24px;text-align:center;color:#666;">' +
                        '<p>We\'re sorry, the form could not be loaded. Please refresh the page or contact us directly.</p></div>';
                }
            }
        });
    }

    // Global error handler for wizard
    window.addEventListener('error', function(e) {
        if (e.filename && e.filename.indexOf('iqw-wizard') > -1) {
            console.error('IQW Wizard runtime error:', e.message, e.filename, e.lineno);
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }

    // Expose for external use
    window.IQWWizard = IQWWizard;
})();
