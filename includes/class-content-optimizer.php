<?php
/**
 * Content Auto-Optimization Class
 * 
 * Integrates with WordPress post editor to provide real-time content optimization
 * similar to Yoast SEO functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class CGAP_Content_Optimizer {
    
    private $seo_integration;
    private $quality_analyzer;
    
    public function __construct() {
        $this->seo_integration = new CGAP_SEO_Integration();
        $this->quality_analyzer = new CGAP_Content_Quality_Analyzer();
        
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Add meta box to post editor
        add_action('add_meta_boxes', array($this, 'add_optimization_meta_box'));
        
        // Save optimization data
        add_action('save_post', array($this, 'save_optimization_data'));
        
        // Enqueue scripts for post editor
        add_action('admin_enqueue_scripts', array($this, 'enqueue_editor_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_cgap_analyze_post_content', array($this, 'analyze_post_content'));
        add_action('wp_ajax_cgap_generate_alt_texts', array($this, 'generate_alt_texts'));
        add_action('wp_ajax_cgap_optimize_content', array($this, 'optimize_content'));
        add_action('wp_ajax_cgap_get_optimization_suggestions', array($this, 'get_optimization_suggestions'));
    }
    
    /**
     * Add optimization meta box to post editor
     */
    public function add_optimization_meta_box() {
        $post_types = array('post', 'page');
        
        foreach ($post_types as $post_type) {
            add_meta_box(
                'cgap-content-optimizer',
                __('ChatGPT Content Optimizer', 'chatgpt-auto-publisher'),
                array($this, 'render_optimization_meta_box'),
                $post_type,
                'normal',
                'high'
            );
        }
    }
    
    /**
     * Render optimization meta box
     */
    public function render_optimization_meta_box($post) {
        wp_nonce_field('cgap_optimization_nonce', 'cgap_optimization_nonce');
        
        $focus_keyword = get_post_meta($post->ID, '_cgap_focus_keyword', true);
        $optimization_score = get_post_meta($post->ID, '_cgap_optimization_score', true);
        $last_analysis = get_post_meta($post->ID, '_cgap_last_analysis', true);
        
        ?>
        <div id="cgap-content-optimizer-panel">
            <!-- Focus Keyword Section -->
            <div class="cgap-optimizer-section">
                <h4><?php _e('Focus Keyword', 'chatgpt-auto-publisher'); ?></h4>
                <div class="cgap-keyword-input-group">
                    <input type="text" 
                           id="cgap-focus-keyword" 
                           name="cgap_focus_keyword" 
                           value="<?php echo esc_attr($focus_keyword); ?>"
                           placeholder="<?php _e('Enter your focus keyword...', 'chatgpt-auto-publisher'); ?>"
                           class="widefat">
                    <button type="button" id="cgap-analyze-content" class="button button-secondary">
                        <?php _e('Analyze Content', 'chatgpt-auto-publisher'); ?>
                    </button>
                </div>
            </div>
            
            <!-- Content Quality Scores -->
            <div class="cgap-optimizer-section">
                <h4><?php _e('Content Quality Analysis', 'chatgpt-auto-publisher'); ?></h4>
                <div id="cgap-quality-scores" class="cgap-scores-grid">
                    <div class="cgap-score-item">
                        <div class="cgap-score-circle" data-score="0">
                            <span class="cgap-score-value">0</span>
                        </div>
                        <label><?php _e('Overall Score', 'chatgpt-auto-publisher'); ?></label>
                    </div>
                    
                    <div class="cgap-score-item">
                        <div class="cgap-score-circle" data-score="0">
                            <span class="cgap-score-value">0</span>
                        </div>
                        <label><?php _e('SEO Score', 'chatgpt-auto-publisher'); ?></label>
                    </div>
                    
                    <div class="cgap-score-item">
                        <div class="cgap-score-circle" data-score="0">
                            <span class="cgap-score-value">0</span>
                        </div>
                        <label><?php _e('Readability', 'chatgpt-auto-publisher'); ?></label>
                    </div>
                    
                    <div class="cgap-score-item">
                        <div class="cgap-score-circle" data-score="0">
                            <span class="cgap-score-value">0</span>
                        </div>
                        <label><?php _e('AIO Compliance', 'chatgpt-auto-publisher'); ?></label>
                    </div>
                </div>
            </div>
            
            <!-- Optimization Actions -->
            <div class="cgap-optimizer-section">
                <h4><?php _e('Quick Actions', 'chatgpt-auto-publisher'); ?></h4>
                <div class="cgap-action-buttons">
                    <button type="button" id="cgap-generate-alt-texts" class="button">
                        <span class="dashicons dashicons-format-image"></span>
                        <?php _e('Generate Alt Texts', 'chatgpt-auto-publisher'); ?>
                    </button>
                    
                    <button type="button" id="cgap-optimize-content" class="button">
                        <span class="dashicons dashicons-admin-tools"></span>
                        <?php _e('Auto-Optimize Content', 'chatgpt-auto-publisher'); ?>
                    </button>
                    
                    <button type="button" id="cgap-get-suggestions" class="button">
                        <span class="dashicons dashicons-lightbulb"></span>
                        <?php _e('Get AI Suggestions', 'chatgpt-auto-publisher'); ?>
                    </button>
                </div>
            </div>
            
            <!-- Analysis Results -->
            <div id="cgap-analysis-results" class="cgap-optimizer-section" style="display: none;">
                <h4><?php _e('Analysis Results', 'chatgpt-auto-publisher'); ?></h4>
                <div id="cgap-analysis-content"></div>
            </div>
            
            <!-- Optimization Suggestions -->
            <div id="cgap-suggestions-panel" class="cgap-optimizer-section" style="display: none;">
                <h4><?php _e('Optimization Suggestions', 'chatgpt-auto-publisher'); ?></h4>
                <div id="cgap-suggestions-content"></div>
            </div>
            
            <!-- Status Messages -->
            <div id="cgap-optimizer-messages"></div>
        </div>
        
        <style>
        #cgap-content-optimizer-panel {
            padding: 15px;
        }
        
        .cgap-optimizer-section {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #e1e5e9;
            border-radius: 6px;
            background: #f8f9fa;
        }
        
        .cgap-optimizer-section h4 {
            margin: 0 0 15px 0;
            color: #1e293b;
            font-size: 14px;
            font-weight: 600;
        }
        
        .cgap-keyword-input-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .cgap-keyword-input-group input {
            flex: 1;
        }
        
        .cgap-scores-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
        }
        
        .cgap-score-item {
            text-align: center;
        }
        
        .cgap-score-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 8px;
            background: #e5e7eb;
            position: relative;
            transition: all 0.3s ease;
        }
        
        .cgap-score-circle.score-low {
            background: conic-gradient(#ef4444 0deg, #ef4444 calc(var(--score, 0) * 3.6deg), #e5e7eb calc(var(--score, 0) * 3.6deg), #e5e7eb 360deg);
        }
        
        .cgap-score-circle.score-medium {
            background: conic-gradient(#f59e0b 0deg, #f59e0b calc(var(--score, 0) * 3.6deg), #e5e7eb calc(var(--score, 0) * 3.6deg), #e5e7eb 360deg);
        }
        
        .cgap-score-circle.score-high {
            background: conic-gradient(#10b981 0deg, #10b981 calc(var(--score, 0) * 3.6deg), #e5e7eb calc(var(--score, 0) * 3.6deg), #e5e7eb 360deg);
        }
        
        .cgap-score-value {
            font-size: 16px;
            font-weight: 700;
            color: #1e293b;
        }
        
        .cgap-score-item label {
            display: block;
            font-size: 11px;
            color: #64748b;
            font-weight: 500;
        }
        
        .cgap-action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .cgap-action-buttons .button {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .cgap-suggestion {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px;
            margin-bottom: 10px;
            border-radius: 6px;
            border-left: 4px solid;
        }
        
        .cgap-suggestion.error {
            background: #fef2f2;
            border-left-color: #ef4444;
        }
        
        .cgap-suggestion.warning {
            background: #fffbeb;
            border-left-color: #f59e0b;
        }
        
        .cgap-suggestion.info {
            background: #eff6ff;
            border-left-color: #3b82f6;
        }
        
        .cgap-suggestion.success {
            background: #f0fdf4;
            border-left-color: #10b981;
        }
        
        .cgap-optimizer-messages .notice {
            margin: 10px 0;
        }
        
        @media (max-width: 768px) {
            .cgap-scores-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .cgap-keyword-input-group {
                flex-direction: column;
                align-items: stretch;
            }
            
            .cgap-action-buttons {
                flex-direction: column;
            }
        }
        </style>
        <?php
    }
    
    /**
     * Enqueue scripts for post editor
     */
    public function enqueue_editor_scripts($hook) {
        if (!in_array($hook, array('post.php', 'post-new.php'))) {
            return;
        }
        
        wp_enqueue_script(
            'cgap-content-optimizer',
            CGAP_PLUGIN_URL . 'assets/js/content-optimizer.js',
            array('jquery', 'wp-tinymce'),
            CGAP_VERSION,
            true
        );
        
        wp_localize_script('cgap-content-optimizer', 'cgap_optimizer', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cgap_optimizer_nonce'),
            'strings' => array(
                'analyzing' => __('Analyzing content...', 'chatgpt-auto-publisher'),
                'generating_alt_texts' => __('Generating alt texts...', 'chatgpt-auto-publisher'),
                'optimizing' => __('Optimizing content...', 'chatgpt-auto-publisher'),
                'getting_suggestions' => __('Getting AI suggestions...', 'chatgpt-auto-publisher'),
                'error' => __('An error occurred. Please try again.', 'chatgpt-auto-publisher'),
                'success' => __('Operation completed successfully!', 'chatgpt-auto-publisher')
            )
        ));
    }
    
    /**
     * Save optimization data
     */
    public function save_optimization_data($post_id) {
        if (!isset($_POST['cgap_optimization_nonce']) || 
            !wp_verify_nonce($_POST['cgap_optimization_nonce'], 'cgap_optimization_nonce')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save focus keyword
        if (isset($_POST['cgap_focus_keyword'])) {
            update_post_meta($post_id, '_cgap_focus_keyword', sanitize_text_field($_POST['cgap_focus_keyword']));
        }
        
        // Save optimization timestamp
        update_post_meta($post_id, '_cgap_last_optimization', current_time('mysql'));
    }
    
    /**
     * Analyze post content via AJAX
     */
    public function analyze_post_content() {
        check_ajax_referer('cgap_optimizer_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions', 'chatgpt-auto-publisher'));
        }
        
        $post_id = absint($_POST['post_id'] ?? 0);
        $content = wp_kses_post($_POST['content'] ?? '');
        $title = sanitize_text_field($_POST['title'] ?? '');
        $keyword = sanitize_text_field($_POST['keyword'] ?? '');
        
        if (empty($content) && empty($title)) {
            wp_send_json_error(__('Content or title is required', 'chatgpt-auto-publisher'));
        }
        
        try {
            $analysis = $this->quality_analyzer->perform_content_analysis($content, $title, $keyword);
            
            // Save analysis results
            if ($post_id > 0) {
                update_post_meta($post_id, '_cgap_optimization_score', $analysis['overall_score']);
                update_post_meta($post_id, '_cgap_last_analysis', current_time('mysql'));
                update_post_meta($post_id, '_cgap_analysis_data', $analysis);
            }
            
            wp_send_json_success($analysis);
            
        } catch (Exception $e) {
            cgap_log('Content Analysis Error: ' . $e->getMessage(), 'error');
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Generate alt texts for images via AJAX
     */
    public function generate_alt_texts() {
        check_ajax_referer('cgap_optimizer_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions', 'chatgpt-auto-publisher'));
        }
        
        $content = wp_kses_post($_POST['content'] ?? '');
        $post_title = sanitize_text_field($_POST['title'] ?? '');
        $keyword = sanitize_text_field($_POST['keyword'] ?? '');
        
        if (empty($content)) {
            wp_send_json_error(__('Content is required', 'chatgpt-auto-publisher'));
        }
        
        try {
            $updated_content = $this->generate_image_alt_texts($content, $post_title, $keyword);
            
            wp_send_json_success(array(
                'content' => $updated_content,
                'message' => __('Alt texts generated successfully!', 'chatgpt-auto-publisher')
            ));
            
        } catch (Exception $e) {
            cgap_log('Alt Text Generation Error: ' . $e->getMessage(), 'error');
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Auto-optimize content via AJAX
     */
    public function optimize_content() {
        check_ajax_referer('cgap_optimizer_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions', 'chatgpt-auto-publisher'));
        }
        
        $content = wp_kses_post($_POST['content'] ?? '');
        $title = sanitize_text_field($_POST['title'] ?? '');
        $keyword = sanitize_text_field($_POST['keyword'] ?? '');
        
        if (empty($content)) {
            wp_send_json_error(__('Content is required', 'chatgpt-auto-publisher'));
        }
        
        try {
            $optimized_content = $this->auto_optimize_content($content, $title, $keyword);
            
            wp_send_json_success(array(
                'content' => $optimized_content,
                'message' => __('Content optimized successfully!', 'chatgpt-auto-publisher')
            ));
            
        } catch (Exception $e) {
            cgap_log('Content Optimization Error: ' . $e->getMessage(), 'error');
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Get optimization suggestions via AJAX
     */
    public function get_optimization_suggestions() {
        check_ajax_referer('cgap_optimizer_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions', 'chatgpt-auto-publisher'));
        }
        
        $content = wp_kses_post($_POST['content'] ?? '');
        $title = sanitize_text_field($_POST['title'] ?? '');
        $keyword = sanitize_text_field($_POST['keyword'] ?? '');
        
        if (empty($content)) {
            wp_send_json_error(__('Content is required', 'chatgpt-auto-publisher'));
        }
        
        try {
            $suggestions = $this->get_ai_optimization_suggestions($content, $title, $keyword);
            
            wp_send_json_success($suggestions);
            
        } catch (Exception $e) {
            cgap_log('Optimization Suggestions Error: ' . $e->getMessage(), 'error');
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Generate alt texts for images in content
     */
    private function generate_image_alt_texts($content, $title, $keyword) {
        if (!cgap_is_api_configured()) {
            throw new Exception(__('OpenAI API is not configured', 'chatgpt-auto-publisher'));
        }
        
        $openai = new CGAP_OpenAI_API();
        
        // Find all images without alt text
        preg_match_all('/<img[^>]*src=["\']([^"\']*)["\'][^>]*>/i', $content, $matches, PREG_SET_ORDER);
        
        $updated_content = $content;
        $generated_count = 0;
        
        foreach ($matches as $match) {
            $img_tag = $match[0];
            $img_src = $match[1];
            
            // Skip if already has alt text
            if (preg_match('/alt=["\'][^"\']*["\']/', $img_tag)) {
                continue;
            }
            
            try {
                // Generate alt text using AI
                $prompt = "Generate a descriptive alt text for an image in a blog post titled '{$title}'";
                if (!empty($keyword)) {
                    $prompt .= " about '{$keyword}'";
                }
                $prompt .= ". The image URL is: {$img_src}. ";
                $prompt .= "Create a concise, descriptive alt text (max 125 characters) that describes what the image likely shows based on the context. ";
                $prompt .= "Focus on accessibility and SEO. Return only the alt text, no quotes or extra text.";
                
                $result = $openai->generate_content($prompt);
                $alt_text = trim($result['content']);
                
                // Clean up the alt text
                $alt_text = str_replace(array('"', "'", "\n", "\r"), '', $alt_text);
                $alt_text = substr($alt_text, 0, 125); // Limit length
                
                // Add alt attribute to image
                $new_img_tag = str_replace('<img', '<img alt="' . esc_attr($alt_text) . '"', $img_tag);
                $updated_content = str_replace($img_tag, $new_img_tag, $updated_content);
                
                $generated_count++;
                
                // Limit to prevent excessive API usage
                if ($generated_count >= 5) {
                    break;
                }
                
            } catch (Exception $e) {
                cgap_log('Alt text generation failed for image: ' . $img_src . ' - ' . $e->getMessage(), 'warning');
                continue;
            }
        }
        
        if ($generated_count === 0) {
            throw new Exception(__('No images found that need alt text, or all images already have alt text.', 'chatgpt-auto-publisher'));
        }
        
        return $updated_content;
    }
    
    /**
     * Auto-optimize content structure and SEO
     */
    private function auto_optimize_content($content, $title, $keyword) {
        if (!cgap_is_api_configured()) {
            throw new Exception(__('OpenAI API is not configured', 'chatgpt-auto-publisher'));
        }
        
        $openai = new CGAP_OpenAI_API();
        
        $prompt = "Optimize the following HTML content for SEO and readability while maintaining the original meaning and structure:\n\n";
        $prompt .= "Title: {$title}\n";
        $prompt .= "Focus Keyword: {$keyword}\n";
        $prompt .= "Content: {$content}\n\n";
        $prompt .= "Optimization requirements:\n";
        $prompt .= "1. Improve heading structure (H2, H3 hierarchy)\n";
        $prompt .= "2. Add bullet points or numbered lists where appropriate\n";
        $prompt .= "3. Optimize keyword placement naturally\n";
        $prompt .= "4. Improve paragraph structure (2-4 sentences each)\n";
        $prompt .= "5. Add internal linking opportunities (use placeholder links)\n";
        $prompt .= "6. Ensure content is scannable and well-structured\n";
        $prompt .= "7. Maintain all existing HTML formatting\n";
        $prompt .= "8. Keep the same content length and meaning\n\n";
        $prompt .= "Return only the optimized HTML content, no explanations.";
        
        $result = $openai->generate_content($prompt);
        
        return trim($result['content']);
    }
    
    /**
     * Get AI-powered optimization suggestions
     */
    private function get_ai_optimization_suggestions($content, $title, $keyword) {
        if (!cgap_is_api_configured()) {
            throw new Exception(__('OpenAI API is not configured', 'chatgpt-auto-publisher'));
        }
        
        $openai = new CGAP_OpenAI_API();
        
        $prompt = "Analyze the following content and provide specific, actionable SEO and content optimization suggestions:\n\n";
        $prompt .= "Title: {$title}\n";
        $prompt .= "Focus Keyword: {$keyword}\n";
        $prompt .= "Content: {$content}\n\n";
        $prompt .= "Provide suggestions in these categories:\n";
        $prompt .= "1. SEO Improvements\n";
        $prompt .= "2. Content Structure\n";
        $prompt .= "3. Readability Enhancements\n";
        $prompt .= "4. Keyword Optimization\n";
        $prompt .= "5. User Experience\n\n";
        $prompt .= "Format each suggestion as a clear, actionable bullet point. ";
        $prompt .= "Focus on specific, implementable recommendations.";
        
        $result = $openai->generate_content($prompt);
        
        // Parse suggestions into categories
        $suggestions = $this->parse_optimization_suggestions($result['content']);
        
        return array(
            'suggestions' => $suggestions,
            'tokens_used' => $result['tokens_used']
        );
    }
    
    /**
     * Parse optimization suggestions into structured format
     */
    private function parse_optimization_suggestions($text) {
        $suggestions = array(
            'SEO Improvements' => array(),
            'Content Structure' => array(),
            'Readability Enhancements' => array(),
            'Keyword Optimization' => array(),
            'User Experience' => array()
        );
        
        $lines = explode("\n", $text);
        $current_category = null;
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Check if line is a category header
            foreach (array_keys($suggestions) as $category) {
                if (stripos($line, $category) !== false || 
                    stripos($line, str_replace(' ', '', $category)) !== false) {
                    $current_category = $category;
                    continue 2;
                }
            }
            
            // Add suggestion to current category
            if ($current_category && (preg_match('/^[-*•]\s*(.+)/', $line, $matches) || 
                                     preg_match('/^\d+\.\s*(.+)/', $line, $matches))) {
                $suggestions[$current_category][] = trim($matches[1]);
            }
        }
        
        // Remove empty categories
        $suggestions = array_filter($suggestions, function($category) {
            return !empty($category);
        });
        
        return $suggestions;
    }
    
    /**
     * Get optimization status for a post
     */
    public function get_optimization_status($post_id) {
        $focus_keyword = get_post_meta($post_id, '_cgap_focus_keyword', true);
        $optimization_score = get_post_meta($post_id, '_cgap_optimization_score', true);
        $last_analysis = get_post_meta($post_id, '_cgap_last_analysis', true);
        $analysis_data = get_post_meta($post_id, '_cgap_analysis_data', true);
        
        return array(
            'focus_keyword' => $focus_keyword,
            'optimization_score' => (int) $optimization_score,
            'last_analysis' => $last_analysis,
            'analysis_data' => $analysis_data,
            'has_analysis' => !empty($analysis_data)
        );
    }
}

// Initialize the content optimizer
new CGAP_Content_Optimizer();