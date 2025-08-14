/**
 * Content Optimizer JavaScript
 * 
 * Handles real-time content optimization in WordPress post editor
 */
jQuery(document).ready(function($) {
    'use strict';
    
    const ContentOptimizer = {
        
        init: function() {
            this.bindEvents();
            this.initRealTimeAnalysis();
        },
        
        bindEvents: function() {
            $('#cgap-analyze-content').on('click', this.analyzeContent.bind(this));
            $('#cgap-generate-alt-texts').on('click', this.generateAltTexts.bind(this));
            $('#cgap-optimize-content').on('click', this.optimizeContent.bind(this));
            $('#cgap-get-suggestions').on('click', this.getSuggestions.bind(this));
            
            // Real-time analysis on keyword change
            $('#cgap-focus-keyword').on('input', this.debounce(this.triggerAnalysis.bind(this), 1000));
        },
        
        initRealTimeAnalysis: function() {
            // Monitor content changes in editor
            if (typeof tinymce !== 'undefined') {
                tinymce.on('AddEditor', function(e) {
                    e.editor.on('input change', ContentOptimizer.debounce(function() {
                        ContentOptimizer.triggerAnalysis();
                    }, 2000));
                });
            }
            
            // Monitor classic editor
            $('#content').on('input', this.debounce(this.triggerAnalysis.bind(this), 2000));
        },
        
        triggerAnalysis: function() {
            const keyword = $('#cgap-focus-keyword').val();
            if (keyword.length > 2) {
                this.analyzeContent(true); // Silent analysis
            }
        },
        
        analyzeContent: function(silent = false) {
            const content = this.getEditorContent();
            const title = $('#title').val() || '';
            const keyword = $('#cgap-focus-keyword').val() || '';
            const postId = $('#post_ID').val() || 0;
            
            if (!content && !title) {
                if (!silent) {
                    this.showMessage('Please add some content to analyze.', 'warning');
                }
                return;
            }
            
            if (!silent) {
                this.showLoading('cgap-analyze-content', cgap_optimizer.strings.analyzing);
            }
            
            $.ajax({
                url: cgap_optimizer.ajax_url,
                type: 'POST',
                data: {
                    action: 'cgap_analyze_post_content',
                    nonce: cgap_optimizer.nonce,
                    post_id: postId,
                    content: content,
                    title: title,
                    keyword: keyword
                },
                success: (response) => {
                    if (response.success) {
                        this.updateScores(response.data);
                        this.showAnalysisResults(response.data);
                        if (!silent) {
                            this.showMessage('Content analysis completed!', 'success');
                        }
                    } else {
                        if (!silent) {
                            this.showMessage(response.data || 'Analysis failed', 'error');
                        }
                    }
                },
                error: () => {
                    if (!silent) {
                        this.showMessage('Analysis request failed', 'error');
                    }
                },
                complete: () => {
                    if (!silent) {
                        this.hideLoading('cgap-analyze-content');
                    }
                }
            });
        },
        
        generateAltTexts: function() {
            const content = this.getEditorContent();
            const title = $('#title').val() || '';
            const keyword = $('#cgap-focus-keyword').val() || '';
            
            if (!content) {
                this.showMessage('Please add some content with images first.', 'warning');
                return;
            }
            
            this.showLoading('cgap-generate-alt-texts', cgap_optimizer.strings.generating_alt_texts);
            
            $.ajax({
                url: cgap_optimizer.ajax_url,
                type: 'POST',
                data: {
                    action: 'cgap_generate_alt_texts',
                    nonce: cgap_optimizer.nonce,
                    content: content,
                    title: title,
                    keyword: keyword
                },
                success: (response) => {
                    if (response.success) {
                        this.setEditorContent(response.data.content);
                        this.showMessage(response.data.message, 'success');
                    } else {
                        this.showMessage(response.data || 'Alt text generation failed', 'error');
                    }
                },
                error: () => {
                    this.showMessage('Alt text generation request failed', 'error');
                },
                complete: () => {
                    this.hideLoading('cgap-generate-alt-texts');
                }
            });
        },
        
        optimizeContent: function() {
            const content = this.getEditorContent();
            const title = $('#title').val() || '';
            const keyword = $('#cgap-focus-keyword').val() || '';
            
            if (!content) {
                this.showMessage('Please add some content to optimize.', 'warning');
                return;
            }
            
            if (!confirm('This will modify your content. Make sure to save a backup first. Continue?')) {
                return;
            }
            
            this.showLoading('cgap-optimize-content', cgap_optimizer.strings.optimizing);
            
            $.ajax({
                url: cgap_optimizer.ajax_url,
                type: 'POST',
                data: {
                    action: 'cgap_optimize_content',
                    nonce: cgap_optimizer.nonce,
                    content: content,
                    title: title,
                    keyword: keyword
                },
                success: (response) => {
                    if (response.success) {
                        this.setEditorContent(response.data.content);
                        this.showMessage(response.data.message, 'success');
                        // Re-analyze after optimization
                        setTimeout(() => this.analyzeContent(true), 1000);
                    } else {
                        this.showMessage(response.data || 'Content optimization failed', 'error');
                    }
                },
                error: () => {
                    this.showMessage('Content optimization request failed', 'error');
                },
                complete: () => {
                    this.hideLoading('cgap-optimize-content');
                }
            });
        },
        
        getSuggestions: function() {
            const content = this.getEditorContent();
            const title = $('#title').val() || '';
            const keyword = $('#cgap-focus-keyword').val() || '';
            
            if (!content) {
                this.showMessage('Please add some content to get suggestions.', 'warning');
                return;
            }
            
            this.showLoading('cgap-get-suggestions', cgap_optimizer.strings.getting_suggestions);
            
            $.ajax({
                url: cgap_optimizer.ajax_url,
                type: 'POST',
                data: {
                    action: 'cgap_get_optimization_suggestions',
                    nonce: cgap_optimizer.nonce,
                    content: content,
                    title: title,
                    keyword: keyword
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuggestions(response.data.suggestions);
                        this.showMessage('AI suggestions generated!', 'success');
                    } else {
                        this.showMessage(response.data || 'Failed to get suggestions', 'error');
                    }
                },
                error: () => {
                    this.showMessage('Suggestions request failed', 'error');
                },
                complete: () => {
                    this.hideLoading('cgap-get-suggestions');
                }
            });
        },
        
        getEditorContent: function() {
            // Try TinyMCE first
            if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
                return tinymce.get('content').getContent();
            }
            
            // Fallback to textarea
            return $('#content').val() || '';
        },
        
        setEditorContent: function(content) {
            // Try TinyMCE first
            if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
                tinymce.get('content').setContent(content);
            }
            
            // Also update textarea
            $('#content').val(content);
        },
        
        updateScores: function(analysis) {
            const scores = [
                { selector: '.cgap-score-item:nth-child(1) .cgap-score-circle', score: analysis.overall_score },
                { selector: '.cgap-score-item:nth-child(2) .cgap-score-circle', score: analysis.seo_score.score },
                { selector: '.cgap-score-item:nth-child(3) .cgap-score-circle', score: analysis.readability.score },
                { selector: '.cgap-score-item:nth-child(4) .cgap-score-circle', score: analysis.aio_compliance?.overall_aio_score || 0 }
            ];
            
            scores.forEach(item => {
                const $circle = $(item.selector);
                const score = Math.round(item.score);
                
                $circle.attr('data-score', score);
                $circle.css('--score', score);
                $circle.find('.cgap-score-value').text(score);
                
                // Update color class
                $circle.removeClass('score-low score-medium score-high');
                if (score < 50) {
                    $circle.addClass('score-low');
                } else if (score < 80) {
                    $circle.addClass('score-medium');
                } else {
                    $circle.addClass('score-high');
                }
            });
        },
        
        showAnalysisResults: function(analysis) {
            const $results = $('#cgap-analysis-results');
            const $content = $('#cgap-analysis-content');
            
            let html = '<div class="cgap-analysis-summary">';
            
            // SEO Analysis
            if (analysis.seo_score && analysis.seo_score.factors) {
                html += '<h5>SEO Analysis</h5>';
                Object.keys(analysis.seo_score.factors).forEach(factor => {
                    const score = analysis.seo_score.factors[factor];
                    const status = score >= 15 ? 'success' : score >= 8 ? 'warning' : 'error';
                    const factorName = factor.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                    
                    html += `
                        <div class="cgap-analysis-item ${status}">
                            <span class="cgap-analysis-factor">${factorName}</span>
                            <span class="cgap-analysis-score">${score}/25</span>
                        </div>
                    `;
                });
            }
            
            // Readability
            if (analysis.readability) {
                html += '<h5>Readability</h5>';
                html += `
                    <div class="cgap-readability-summary">
                        <div class="cgap-readability-item">
                            <strong>${analysis.readability.words}</strong> words
                        </div>
                        <div class="cgap-readability-item">
                            <strong>${analysis.readability.sentences}</strong> sentences
                        </div>
                        <div class="cgap-readability-item">
                            <strong>${analysis.readability.avg_sentence_length}</strong> avg sentence length
                        </div>
                        <div class="cgap-readability-level ${analysis.readability.level.toLowerCase().replace(' ', '-')}">
                            ${analysis.readability.level}
                        </div>
                    </div>
                `;
            }
            
            // AIO Compliance
            if (analysis.aio_compliance) {
                html += '<h5>AIO Compliance</h5>';
                const categories = [
                    { key: 'structure_readability', name: 'Structure & Readability' },
                    { key: 'semantic_keywords', name: 'Semantic Keywords' },
                    { key: 'technical_elements', name: 'Technical Elements' },
                    { key: 'topic_coverage', name: 'Topic Coverage' }
                ];
                
                categories.forEach(category => {
                    if (analysis.aio_compliance[category.key] && analysis.aio_compliance[category.key].score !== undefined) {
                        const score = analysis.aio_compliance[category.key].score;
                        const status = score >= 70 ? 'success' : score >= 40 ? 'warning' : 'error';
                        
                        html += `
                            <div class="cgap-analysis-item ${status}">
                                <span class="cgap-analysis-factor">${category.name}</span>
                                <span class="cgap-analysis-score">${score}/100</span>
                            </div>
                        `;
                    }
                });
            }
            
            html += '</div>';
            
            $content.html(html);
            $results.show();
        },
        
        showSuggestions: function(suggestions) {
            const $panel = $('#cgap-suggestions-panel');
            const $content = $('#cgap-suggestions-content');
            
            let html = '';
            
            Object.keys(suggestions).forEach(category => {
                if (suggestions[category].length > 0) {
                    html += `<h5>${category}</h5>`;
                    suggestions[category].forEach(suggestion => {
                        html += `
                            <div class="cgap-suggestion info">
                                <div class="cgap-suggestion-content">
                                    <div class="cgap-suggestion-message">${suggestion}</div>
                                </div>
                            </div>
                        `;
                    });
                }
            });
            
            $content.html(html);
            $panel.show();
        },
        
        showMessage: function(message, type = 'info') {
            const $messages = $('#cgap-optimizer-messages');
            const $notice = $(`
                <div class="notice notice-${type} is-dismissible cgap-optimizer-notice">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `);
            
            $messages.html($notice);
            
            // Auto-dismiss success messages
            if (type === 'success') {
                setTimeout(() => {
                    $notice.fadeOut(() => $notice.remove());
                }, 3000);
            }
            
            // Handle dismiss button
            $notice.on('click', '.notice-dismiss', function() {
                $notice.fadeOut(() => $notice.remove());
            });
        },
        
        showLoading: function(buttonId, text) {
            const $button = $('#' + buttonId);
            $button.prop('disabled', true);
            $button.data('original-text', $button.text());
            $button.text(text);
        },
        
        hideLoading: function(buttonId) {
            const $button = $('#' + buttonId);
            $button.prop('disabled', false);
            $button.text($button.data('original-text') || $button.text());
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
        }
    };
    
    // Initialize when DOM is ready
    ContentOptimizer.init();
    
    // Additional styles for analysis results
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .cgap-analysis-summary {
                margin-top: 15px;
            }
            
            .cgap-analysis-summary h5 {
                margin: 15px 0 10px 0;
                color: #1e293b;
                font-size: 13px;
                font-weight: 600;
                border-bottom: 1px solid #e1e5e9;
                padding-bottom: 5px;
            }
            
            .cgap-analysis-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 8px 12px;
                margin-bottom: 5px;
                border-radius: 4px;
                font-size: 12px;
            }
            
            .cgap-analysis-item.success {
                background: #f0fdf4;
                border-left: 3px solid #10b981;
            }
            
            .cgap-analysis-item.warning {
                background: #fffbeb;
                border-left: 3px solid #f59e0b;
            }
            
            .cgap-analysis-item.error {
                background: #fef2f2;
                border-left: 3px solid #ef4444;
            }
            
            .cgap-analysis-factor {
                font-weight: 500;
                color: #374151;
            }
            
            .cgap-analysis-score {
                font-weight: 600;
                font-family: monospace;
            }
            
            .cgap-readability-summary {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
                gap: 10px;
                margin-bottom: 10px;
            }
            
            .cgap-readability-item {
                text-align: center;
                padding: 8px;
                background: #f8f9fa;
                border-radius: 4px;
                font-size: 12px;
            }
            
            .cgap-readability-level {
                grid-column: 1 / -1;
                text-align: center;
                padding: 6px 12px;
                border-radius: 15px;
                font-size: 11px;
                font-weight: 500;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            .cgap-readability-level.very-easy,
            .cgap-readability-level.easy {
                background: #dcfce7;
                color: #166534;
            }
            
            .cgap-readability-level.fairly-easy,
            .cgap-readability-level.standard {
                background: #fef3c7;
                color: #92400e;
            }
            
            .cgap-readability-level.fairly-difficult,
            .cgap-readability-level.difficult,
            .cgap-readability-level.very-difficult {
                background: #fee2e2;
                color: #991b1b;
            }
        `)
        .appendTo('head');
});