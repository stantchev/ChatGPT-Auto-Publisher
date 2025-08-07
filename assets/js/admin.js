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
            this.restoreFormData();
            this.bindFormEvents();
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
                keywords: $('#keywords').val().trim(),
                tone: $('#tone').val(),
                length: $('#length').val(),
                auto_publish: $('#auto_publish').is(':checked')
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
                keywords: $('#keywords').val(),
                tone: $('#tone').val(),
                length: $('#length').val(),
                auto_publish: $('#auto_publish').is(':checked')
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