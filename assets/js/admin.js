/**
 * Admin JavaScript for ChatGPT Auto Publisher
 * 
 * Handles all AJAX interactions with proper error handling and user feedback
 */
jQuery(document).ready(function($) {
    'use strict';
    
    /**
     * AJAX utility class for centralized request handling
     */
    const CGAPAjax = {
        
        /**
         * Default AJAX settings
         */
        defaults: {
            timeout: 120000, // 2 minutes
            dataType: 'json',
            beforeSend: function() {
                CGAPAjax.showLoading();
            },
            complete: function() {
                CGAPAjax.hideLoading();
            }
        },
        
        /**
         * Make AJAX request with proper error handling
         * 
         * @param {string} action - WordPress AJAX action
         * @param {object} data - Request data
         * @param {object} options - Additional options
         * @returns {Promise}
         */
        request: function(action, data = {}, options = {}) {
            return new Promise((resolve, reject) => {
                // Prepare request data
                const requestData = {
                    action: action,
                    nonce: cgap_ajax.nonces[action] || cgap_ajax.nonce,
                    ...data
                };
                
                // Merge options with defaults
                const ajaxOptions = {
                    ...this.defaults,
                    url: cgap_ajax.ajax_url,
                    type: 'POST',
                    data: requestData,
                    success: function(response) {
                        if (response.success) {
                            resolve(response.data);
                        } else {
                            reject(new Error(response.data || cgap_ajax.strings.error));
                        }
                    },
                    error: function(xhr, status, error) {
                        let message = cgap_ajax.strings.error;
                        
                        if (status === 'timeout') {
                            message = cgap_ajax.strings.timeout || 'Request timed out. Please try again.';
                        } else if (xhr.responseJSON && xhr.responseJSON.data) {
                            message = xhr.responseJSON.data;
                        } else if (error) {
                            message = error;
                        }
                        
                        reject(new Error(message));
                    },
                    ...options
                };
                
                $.ajax(ajaxOptions);
            });
        },
        
        /**
         * Show loading indicator
         */
        showLoading: function() {
            if (!$('#cgap-loading-overlay').length) {
                $('body').append('<div id="cgap-loading-overlay" class="cgap-loading-overlay"><div class="cgap-spinner"></div></div>');
            }
            $('#cgap-loading-overlay').show();
        },
        
        /**
         * Hide loading indicator
         */
        hideLoading: function() {
            $('#cgap-loading-overlay').hide();
        },
        
        /**
         * Show notification
         * 
         * @param {string} message
         * @param {string} type - success, error, warning, info
         */
        showNotice: function(message, type = 'info') {
            const $notice = $(`
                <div class="notice notice-${type} is-dismissible cgap-notice">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `);
            
            $('.wrap').prepend($notice);
            
            // Auto-dismiss after 5 seconds for success messages
            if (type === 'success') {
                setTimeout(() => {
                    $notice.fadeOut(() => $notice.remove());
                }, 5000);
            }
            
            // Handle dismiss button
            $notice.on('click', '.notice-dismiss', function() {
                $notice.fadeOut(() => $notice.remove());
            });
        }
    };
    
    /**
     * Content Generation Handler
     */
    const ContentGenerator = {
        
        init: function() {
            $('#cgap-generate-form').on('submit', this.handleSubmit.bind(this));
            $('#focus_keyword').on('input', this.handleKeywordChange.bind(this));
            $('#enable_seo_analysis').on('change', this.toggleSEOAnalysis.bind(this));
            $('#get-ai-suggestions').on('click', this.getAISuggestions.bind(this));
            $('#check-content-gaps').on('click', this.checkContentGaps.bind(this));
            $('#translate-content').on('click', this.translateContent.bind(this));
            $('#create-translated-post').on('click', this.createTranslatedPost.bind(this));
            this.restoreFormData();
            this.bindFormEvents();
            this.initSEOIntegration();
        },
        
        initSEOIntegration: function() {
            // Check SEO plugin status
            this.checkSEOPluginStatus();
            
            // Initialize content quality analysis
            if ($('#enable_seo_analysis').is(':checked')) {
                this.enableSEOAnalysis();
            }
        },
        
        checkSEOPluginStatus: function() {
            CGAPAjax.request('cgap_check_seo_plugins')
                .then(data => {
                    this.updateSEOPluginStatus(data);
                })
                .catch(error => {
                    console.log('SEO plugin check failed:', error);
                });
        },
        
        updateSEOPluginStatus: function(plugins) {
            const $status = $('#seo-plugin-status');
            const $select = $('#seo_plugin');
            
            // Find active plugin
            let activePlugin = null;
            Object.keys(plugins).forEach(key => {
                if (plugins[key].active) {
                    activePlugin = key;
                }
            });
            
            if (activePlugin) {
                $status.html(`<span class="cgap-seo-status active">✓ ${plugins[activePlugin].name} Active</span>`);
                $select.val(activePlugin);
            } else {
                $status.html('<span class="cgap-seo-status inactive">⚠ No SEO plugin detected</span>');
            }
        },
        
        handleKeywordChange: function() {
            const keyword = $('#focus_keyword').val();
            if (keyword.length > 0 && $('#enable_seo_analysis').is(':checked')) {
                this.debounce(this.analyzeContent.bind(this), 1000)();
            }
        },
        
        toggleSEOAnalysis: function() {
            if ($('#enable_seo_analysis').is(':checked')) {
                this.enableSEOAnalysis();
            } else {
                this.disableSEOAnalysis();
            }
        },
        
        enableSEOAnalysis: function() {
            $('#cgap-quality-panel').show();
            this.analyzeContent();
        },
        
        disableSEOAnalysis: function() {
            $('#cgap-quality-panel').hide();
        },
        
        analyzeContent: function() {
            const content = this.getGeneratedContent();
            const title = $('#topic').val();
            const keyword = $('#focus_keyword').val();
            
            if (!content && !title) return;
            
            CGAPAjax.request('cgap_analyze_content', {
                content: content || title,
                title: title,
                keyword: keyword
            })
            .then(data => {
                this.updateQualityScores(data);
                this.updateSEOAnalysis(data);
                this.updateAIOCompliance(data);
            })
            .catch(error => {
                console.log('Content analysis failed:', error);
            });
        },
        
        updateQualityScores: function(analysis) {
            // Update overall score
            this.updateScoreCircle($('.cgap-score-item:nth-child(1) .cgap-score-circle'), analysis.overall_score);
            
            // Update SEO score
            this.updateScoreCircle($('.cgap-score-item:nth-child(2) .cgap-score-circle'), analysis.seo_score.score);
            
            // Update readability score
            this.updateScoreCircle($('.cgap-score-item:nth-child(3) .cgap-score-circle'), analysis.readability.score);
        },
        
        updateScoreCircle: function($circle, score) {
            $circle.attr('data-score', score);
            $circle.css('--score', score);
            $circle.find('.cgap-score-value').text(score);
            
            // Update color based on score
            $circle.removeClass('score-low score-medium score-high');
            if (score < 50) {
                $circle.addClass('score-low');
            } else if (score < 80) {
                $circle.addClass('score-medium');
            } else {
                $circle.addClass('score-high');
            }
        },
        
        updateAIOCompliance: function(analysis) {
            if (!analysis.aio_compliance) return;
            
            const $content = $('#seo-analysis-content');
            let aioHtml = '';
            
            // Add AIO compliance section
            if (analysis.aio_compliance.overall_aio_score !== undefined) {
                aioHtml += '<div class="cgap-aio-compliance">';
                aioHtml += '<h5>🤖 AI Search Engine Optimization (AIO) Compliance</h5>';
                aioHtml += `<div class="cgap-aio-overall-score">Overall AIO Score: <strong>${analysis.aio_compliance.overall_aio_score}/100</strong></div>`;
                aioHtml += '<div class="cgap-aio-categories">';
                
                const categories = [
                    { key: 'structure_readability', name: 'Structure & Readability' },
                    { key: 'semantic_keywords', name: 'Semantic Keywords' },
                    { key: 'technical_elements', name: 'Technical Elements' },
                    { key: 'topic_coverage', name: 'Topic Coverage' },
                    { key: 'ai_agent_optimization', name: 'AI Agent Optimization' },
                    { key: 'performance_standards', name: 'Performance Standards' },
                    { key: 'authority_freshness', name: 'Authority & Freshness' },
                    { key: 'answer_engine_optimization', name: 'Answer Engine Optimization' }
                ];
                
                categories.forEach(category => {
                    if (analysis.aio_compliance[category.key] && analysis.aio_compliance[category.key].score !== undefined) {
                        const score = analysis.aio_compliance[category.key].score;
                        const status = score >= 70 ? 'good' : score >= 40 ? 'warning' : 'error';
                        
                        aioHtml += `
                            <div class="cgap-aio-category">
                                <span class="cgap-aio-category-name">${category.name}</span>
                                <span class="cgap-aio-category-score ${status}">${score}/100</span>
                            </div>
                        `;
                    }
                });
                
                aioHtml += '</div></div>';
            }
            
            // Prepend AIO compliance to existing content
            const existingContent = $content.html();
            $content.html(aioHtml + existingContent);
        },
        
        updateSEOAnalysis: function(analysis) {
            const $content = $('#seo-analysis-content');
            let html = '';
            
            // SEO factors
            if (analysis.seo_score.factors) {
                Object.keys(analysis.seo_score.factors).forEach(factor => {
                    const score = analysis.seo_score.factors[factor];
                    const status = score >= 15 ? 'good' : score >= 8 ? 'warning' : 'error';
                    
                    html += `
                        <div class="cgap-seo-factor">
                            <span class="cgap-seo-factor-name">${this.formatFactorName(factor)}</span>
                            <span class="cgap-seo-factor-score ${status}">${score}/25</span>
                        </div>
                    `;
                });
            }
            
            // Keyword analysis
            if (analysis.keyword_analysis) {
                html += '<div class="cgap-keyword-analysis">';
                html += `
                    <div class="cgap-keyword-metric">
                        <span class="cgap-keyword-metric-value">${analysis.keyword_analysis.density}%</span>
                        <span class="cgap-keyword-metric-label">Density</span>
                    </div>
                    <div class="cgap-keyword-metric">
                        <span class="cgap-keyword-metric-value">${analysis.keyword_analysis.count}</span>
                        <span class="cgap-keyword-metric-label">Occurrences</span>
                    </div>
                `;
                html += '</div>';
            }
            
            // Readability details
            if (analysis.readability) {
                html += '<div class="cgap-readability-details">';
                html += `
                    <div class="cgap-readability-metric">
                        <strong>${analysis.readability.words}</strong>
                        <div>Words</div>
                    </div>
                    <div class="cgap-readability-metric">
                        <strong>${analysis.readability.sentences}</strong>
                        <div>Sentences</div>
                    </div>
                    <div class="cgap-readability-metric">
                        <strong>${analysis.readability.avg_sentence_length}</strong>
                        <div>Avg Sentence Length</div>
                    </div>
                `;
                html += '</div>';
                html += `<span class="cgap-readability-level ${analysis.readability.level.toLowerCase().replace(' ', '-')}">${analysis.readability.level}</span>`;
            }
            
            // Append to existing content (AIO compliance is prepended)
            $content.append(html);
        },
        
        formatFactorName: function(factor) {
            return factor.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        },
        
        getAISuggestions: function() {
            const content = this.getGeneratedContent();
            const title = $('#topic').val();
            const keyword = $('#focus_keyword').val();
            
            if (!content && !title) {
                CGAPAjax.showNotice('Please generate content first', 'warning');
                return;
            }
            
            CGAPAjax.request('cgap_get_content_suggestions', {
                content: content || title,
                title: title,
                keyword: keyword
            })
            .then(data => {
                this.displayAISuggestions(data.suggestions);
            })
            .catch(error => {
                CGAPAjax.showNotice(error.message, 'error');
            });
        },
        
        displayAISuggestions: function(suggestions) {
            const $content = $('#ai-suggestions-content');
            let html = '';
            
            Object.keys(suggestions).forEach(category => {
                if (suggestions[category].length > 0) {
                    html += `<h5>${category}</h5>`;
                    suggestions[category].forEach(suggestion => {
                        html += `
                            <div class="cgap-suggestion info">
                                <div class="cgap-suggestion-icon">💡</div>
                                <div class="cgap-suggestion-content">
                                    <div class="cgap-suggestion-message">${suggestion}</div>
                                </div>
                            </div>
                        `;
                    });
                }
            });
            
            $content.html(html);
        },
        
        checkContentGaps: function() {
            const content = this.getGeneratedContent();
            const keyword = $('#focus_keyword').val();
            
            if (!content || !keyword) {
                CGAPAjax.showNotice('Please generate content and specify a keyword first', 'warning');
                return;
            }
            
            CGAPAjax.request('cgap_check_content_gaps', {
                content: content,
                keyword: keyword
            })
            .then(data => {
                $('#content-gaps-content').html(`<div class="cgap-content-gaps">${data.gaps.replace(/\n/g, '<br>')}</div>`);
            })
            .catch(error => {
                CGAPAjax.showNotice(error.message, 'error');
            });
        },
        
        translateContent: function() {
            const content = this.getGeneratedContent();
            const title = $('#topic').val();
            const targetLanguage = $('#translate_to').val();
            
            if (!content || !title) {
                CGAPAjax.showNotice('Please generate content first', 'warning');
                return;
            }
            
            CGAPAjax.request('cgap_translate_content', {
                content: content,
                title: title,
                target_language: this.getLanguageName(targetLanguage)
            })
            .then(data => {
                this.displayTranslation(data);
                $('#cgap-translation-panel').show();
            })
            .catch(error => {
                CGAPAjax.showNotice(error.message, 'error');
            });
        },
        
        displayTranslation: function(data) {
            $('.cgap-translated-title').text(data.title);
            $('.cgap-translated-content').html(data.content);
            $('#translation-result').show();
        },
        
        createTranslatedPost: function() {
            const translatedTitle = $('.cgap-translated-title').text();
            const translatedContent = $('.cgap-translated-content').html();
            const targetLanguage = $('#translate_to').val();
            
            CGAPAjax.request('cgap_create_translated_post', {
                title: translatedTitle,
                content: translatedContent,
                language: targetLanguage
            })
            .then(data => {
                CGAPAjax.showNotice('Translated post created successfully!', 'success');
                window.open(data.edit_url, '_blank');
            })
            .catch(error => {
                CGAPAjax.showNotice(error.message, 'error');
            });
        },
        
        getLanguageName: function(code) {
            const languages = {
                'en': 'English',
                'bg': 'Bulgarian',
                'es': 'Spanish',
                'fr': 'French',
                'de': 'German',
                'it': 'Italian',
                'pt': 'Portuguese',
                'ru': 'Russian'
            };
            return languages[code] || 'English';
        },
        
        getGeneratedContent: function() {
            // Try to get content from the result panel
            const $result = $('#cgap-result');
            if ($result.is(':visible')) {
                return $result.find('.cgap-content-text').text();
            }
            return '';
        },
        
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },
        
        handleSubmit: function(e) {
            e.preventDefault();
            
            const $form = $(e.target);
            const $button = $form.find('.cgap-generate-btn');
            
            // Validate form
            if (!this.validateForm($form)) {
                return;
            }
            
            // Prepare form data
            const formData = {
                topic: $('#topic').val().trim(),
                focus_keyword: $('#focus_keyword').val().trim(),
                content_language: $('#content_language').val(),
                tone: $('#tone').val(),
                length: $('#length').val(),
                auto_publish: $('#auto_publish').is(':checked'),
                seo_plugin: $('#seo_plugin').val(),
                enable_seo_analysis: $('#enable_seo_analysis').is(':checked'),
                ai_optimization: $('#ai_optimization').is(':checked')
            };
            
            // Show loading state
            this.setButtonLoading($button, true);
            
            // Make AJAX request
            CGAPAjax.request('cgap_generate_content', formData, {
                timeout: 180000 // 3 minutes for content generation
            })
            .then(data => {
                this.showGenerationResult(data);
                $form[0].reset();
                this.clearSavedFormData();
                
                // Show quality analysis and translation panels
                if (data.content) {
                    $('#cgap-quality-panel').show();
                    $('#cgap-translation-panel').show();
                    this.analyzeContent();
                }
                
                CGAPAjax.showNotice(cgap_ajax.strings.success, 'success');
                
                // Trigger custom event
                $(document).trigger('cgap_generation_success', [data]);
            })
            .catch(error => {
                CGAPAjax.showNotice(error.message, 'error');
            })
            .finally(() => {
                this.setButtonLoading($button, false);
            });
        },
        
        validateForm: function($form) {
            const topic = $('#topic').val().trim();
            
            if (!topic) {
                CGAPAjax.showNotice(cgap_ajax.strings.topic_required || 'Topic is required', 'error');
                $('#topic').focus();
                return false;
            }
            
            if (topic.length < 3) {
                CGAPAjax.showNotice('Topic must be at least 3 characters long', 'error');
                $('#topic').focus();
                return false;
            }
            
            return true;
        },
        
        setButtonLoading: function($button, loading) {
            const $btnText = $button.find('.cgap-btn-text');
            const $btnLoading = $button.find('.cgap-btn-loading');
            
            if (loading) {
                $button.prop('disabled', true);
                $btnText.hide();
                $btnLoading.show();
            } else {
                $button.prop('disabled', false);
                $btnText.show();
                $btnLoading.hide();
            }
        },
        
        showGenerationResult: function(data) {
            const $result = $('#cgap-result');
            const $content = $result.find('.cgap-result-content');
            
            let html = `
                <div class="cgap-result-header">
                    <h4>${this.escapeHtml(data.title)}</h4>
                    <div class="cgap-result-meta">
                        <span class="cgap-tokens">${data.tokens_used} tokens</span>
                        <span class="cgap-cost">${data.cost}</span>
                        <span class="cgap-model">${data.model}</span>
                    </div>
                </div>
            `;
            
            if (data.meta_description) {
                html += `
                    <div class="cgap-meta-description">
                        <strong>Meta Description:</strong> ${this.escapeHtml(data.meta_description)}
                    </div>
                `;
            }
            
            html += `
                <div class="cgap-content-preview">
                    <strong>Content Preview:</strong>
                    <div class="cgap-content-text">${this.escapeHtml(data.content.substring(0, 500))}...</div>
                </div>
                <div class="cgap-result-actions">
                    <a href="${data.edit_url}" class="button button-primary" target="_blank">Edit Post</a>
            `;
            
            if (data.view_url) {
                html += `<a href="${data.view_url}" class="button" target="_blank">View Post</a>`;
            }
            
            html += '</div>';
            
            $content.html(html);
            $result.show();
        },
        
        bindFormEvents: function() {
            // Auto-save form data
            $('#cgap-generate-form input, #cgap-generate-form select').on('change', this.saveFormData.bind(this));
        },
        
        saveFormData: function() {
            const formData = {
                topic: $('#topic').val(),
                focus_keyword: $('#focus_keyword').val(),
                content_language: $('#content_language').val(),
                tone: $('#tone').val(),
                length: $('#length').val(),
                auto_publish: $('#auto_publish').is(':checked'),
                seo_plugin: $('#seo_plugin').val(),
                enable_seo_analysis: $('#enable_seo_analysis').is(':checked'),
                ai_optimization: $('#ai_optimization').is(':checked')
            };
            
            localStorage.setItem('cgap_form_data', JSON.stringify(formData));
        },
        
        restoreFormData: function() {
            const savedData = localStorage.getItem('cgap_form_data');
            if (savedData) {
                try {
                    const formData = JSON.parse(savedData);
                    Object.keys(formData).forEach(key => {
                        const $field = $(`[name="${key}"]`);
                        if ($field.length) {
                            if ($field.is(':checkbox')) {
                                $field.prop('checked', formData[key]);
                            } else {
                                $field.val(formData[key]);
                            }
                        }
                    });
                } catch (e) {
                    // Ignore errors
                }
            }
        },
        
        clearSavedFormData: function() {
            localStorage.removeItem('cgap_form_data');
        },
        
        escapeHtml: function(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }
    };
    
    /**
     * Settings Handler
     */
    const SettingsManager = {
        
        init: function() {
            $('#cgap-settings-form').on('submit', this.handleSubmit.bind(this));
            $('#cgap-test-api').on('click', this.testApiConnection.bind(this));
            $('#cgap-export-settings').on('click', this.exportSettings.bind(this));
            $('#cgap-import-settings').on('click', this.importSettings.bind(this));
            $('#cgap-import-file').on('change', this.handleImportFile.bind(this));
            $('#cgap-reset-settings').on('click', this.resetSettings.bind(this));
        },
        
        handleSubmit: function(e) {
            e.preventDefault();
            
            const $form = $(e.target);
            const formData = this.serializeFormData($form);
            
            CGAPAjax.request('cgap_save_settings', formData)
                .then(message => {
                    CGAPAjax.showNotice(message, 'success');
                })
                .catch(error => {
                    CGAPAjax.showNotice(error.message, 'error');
                });
        },
        
        testApiConnection: function(e) {
            e.preventDefault();
            
            const apiKey = $('#openai_api_key').val().trim();
            if (!apiKey) {
                CGAPAjax.showNotice('Please enter an API key first', 'error');
                return;
            }
            
            const $button = $(e.target);
            const originalText = $button.text();
            
            $button.prop('disabled', true).text(cgap_ajax.strings.testing_api);
            
            CGAPAjax.request('cgap_test_api', { api_key: apiKey })
                .then(message => {
                    CGAPAjax.showNotice(message, 'success');
                })
                .catch(error => {
                    CGAPAjax.showNotice(error.message, 'error');
                })
                .finally(() => {
                    $button.prop('disabled', false).text(originalText);
                });
        },
        
        exportSettings: function() {
            const settings = {};
            $('#cgap-settings-form').serializeArray().forEach(item => {
                if (item.name !== 'openai_api_key' && item.name !== 'nonce') {
                    settings[item.name] = item.value;
                }
            });
            
            const dataStr = JSON.stringify(settings, null, 2);
            const dataBlob = new Blob([dataStr], { type: 'application/json' });
            const url = URL.createObjectURL(dataBlob);
            
            const link = document.createElement('a');
            link.href = url;
            link.download = `cgap-settings-${new Date().toISOString().split('T')[0]}.json`;
            link.click();
            
            URL.revokeObjectURL(url);
            CGAPAjax.showNotice('Settings exported successfully', 'success');
        },
        
        importSettings: function() {
            $('#cgap-import-file').click();
        },
        
        handleImportFile: function(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            const reader = new FileReader();
            reader.onload = (e) => {
                try {
                    const settings = JSON.parse(e.target.result);
                    this.applyImportedSettings(settings);
                    CGAPAjax.showNotice('Settings imported successfully. Click "Save Settings" to apply.', 'success');
                } catch (error) {
                    CGAPAjax.showNotice('Invalid settings file', 'error');
                }
            };
            reader.readAsText(file);
        },
        
        applyImportedSettings: function(settings) {
            Object.keys(settings).forEach(key => {
                const $field = $(`#${key}`);
                if ($field.length) {
                    if ($field.is(':checkbox')) {
                        $field.prop('checked', settings[key]);
                    } else {
                        $field.val(settings[key]);
                    }
                }
            });
        },
        
        resetSettings: function() {
            if (confirm('Are you sure you want to reset all settings to defaults? This cannot be undone.')) {
                $('#cgap-settings-form')[0].reset();
                CGAPAjax.showNotice('Settings reset to defaults. Click "Save Settings" to apply.', 'info');
            }
        },
        
        serializeFormData: function($form) {
            const formData = {};
            $form.serializeArray().forEach(item => {
                formData[item.name] = item.value;
            });
            
            // Handle checkboxes
            $form.find('input[type="checkbox"]').each(function() {
                formData[this.name] = this.checked;
            });
            
            return formData;
        }
    };
    
    /**
     * Scheduler Handler
     */
    const SchedulerManager = {
        
        init: function() {
            if ($('#cgap-scheduler-form').length) {
                $('#cgap-scheduler-form').on('submit', this.handleAddSchedule.bind(this));
                $('.cgap-delete-scheduled').on('click', this.handleDeleteSchedule.bind(this));
                $('.cgap-toggle-schedule').on('click', this.handleToggleSchedule.bind(this));
            }
        },
        
        handleAddSchedule: function(e) {
            e.preventDefault();
            
            const $form = $(e.target);
            const formData = {
                title: $('#schedule_title').val().trim(),
                keywords: $('#schedule_keywords').val().trim(),
                frequency: $('#schedule_frequency').val()
            };
            
            if (!this.validateScheduleForm(formData)) {
                return;
            }
            
            CGAPAjax.request('cgap_add_scheduled_post', formData)
                .then(() => {
                    location.reload();
                })
                .catch(error => {
                    CGAPAjax.showNotice(error.message, 'error');
                });
        },
        
        handleDeleteSchedule: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to delete this scheduled post?')) {
                return;
            }
            
            const postId = $(e.target).data('id');
            
            CGAPAjax.request('cgap_delete_scheduled_post', { id: postId })
                .then(() => {
                    location.reload();
                })
                .catch(error => {
                    CGAPAjax.showNotice(error.message, 'error');
                });
        },
        
        handleToggleSchedule: function(e) {
            e.preventDefault();
            
            const $button = $(e.target);
            const postId = $button.data('id');
            
            CGAPAjax.request('cgap_toggle_scheduled_post', { id: postId })
                .then(data => {
                    location.reload();
                })
                .catch(error => {
                    CGAPAjax.showNotice(error.message, 'error');
                });
        },
        
        validateScheduleForm: function(data) {
            if (!data.title) {
                CGAPAjax.showNotice('Schedule title is required', 'error');
                $('#schedule_title').focus();
                return false;
            }
            
            if (!data.keywords) {
                CGAPAjax.showNotice('Keywords are required', 'error');
                $('#schedule_keywords').focus();
                return false;
            }
            
            return true;
        }
    };
    
    /**
     * Logs Handler
     */
    const LogsManager = {
        
        init: function() {
            if ($('.cgap-view-log').length) {
                $('.cgap-view-log').on('click', this.handleViewLog.bind(this));
                $('.cgap-modal-close, .cgap-modal').on('click', this.handleCloseModal.bind(this));
                $('#cgap-export-logs').on('click', this.handleExportLogs.bind(this));
                $('#cgap-clear-logs').on('click', this.handleClearLogs.bind(this));
            }
        },
        
        handleViewLog: function(e) {
            e.preventDefault();
            
            const logId = $(e.target).data('id');
            
            CGAPAjax.request('cgap_get_log_details', { id: logId })
                .then(html => {
                    $('#cgap-log-details').html(html);
                    $('#cgap-log-modal').show();
                })
                .catch(error => {
                    CGAPAjax.showNotice(error.message, 'error');
                });
        },
        
        handleCloseModal: function(e) {
            if (e.target === e.currentTarget) {
                $('#cgap-log-modal').hide();
            }
        },
        
        handleExportLogs: function(e) {
            e.preventDefault();
            
            // Create a temporary form to trigger the export
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = cgap_ajax.ajax_url;
            form.style.display = 'none';
            
            const actionInput = document.createElement('input');
            actionInput.name = 'action';
            actionInput.value = 'cgap_export_logs';
            form.appendChild(actionInput);
            
            const nonceInput = document.createElement('input');
            nonceInput.name = 'nonce';
            nonceInput.value = cgap_ajax.nonces.cgap_export_logs || cgap_ajax.nonce;
            form.appendChild(nonceInput);
            
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        },
        
        handleClearLogs: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to clear old logs? This cannot be undone.')) {
                return;
            }
            
            CGAPAjax.request('cgap_clear_logs')
                .then(message => {
                    CGAPAjax.showNotice(message, 'success');
                    setTimeout(() => location.reload(), 1000);
                })
                .catch(error => {
                    CGAPAjax.showNotice(error.message, 'error');
                });
        }
    };
    
    /**
     * Tab Navigation Handler
     */
    const TabManager = {
        
        init: function() {
            $('.cgap-tab-button').on('click', this.handleTabClick.bind(this));
        },
        
        handleTabClick: function(e) {
            e.preventDefault();
            
            const $button = $(e.target);
            const tab = $button.data('tab');
            
            $('.cgap-tab-button').removeClass('active');
            $('.cgap-tab-content').removeClass('active');
            
            $button.addClass('active');
            $(`.cgap-tab-content[data-tab="${tab}"]`).addClass('active');
        }
    };
    
    // Initialize all handlers
    ContentGenerator.init();
    SettingsManager.init();
    SchedulerManager.init();
    LogsManager.init();
    TabManager.init();
    
    // Global error handler for uncaught AJAX errors
    $(document).ajaxError(function(event, xhr, settings, error) {
        if (settings.url === cgap_ajax.ajax_url) {
            console.error('CGAP AJAX Error:', error, xhr);
        }
    });
});