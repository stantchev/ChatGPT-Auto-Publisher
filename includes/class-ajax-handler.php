<?php
/**
 * AJAX Handler Class
 * 
 * Centralized AJAX request handling with proper security and error handling
 * 
 * @package ChatGPT_Auto_Publisher
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CGAP_Ajax_Handler {
    
    /**
     * Instance of this class
     * 
     * @var CGAP_Ajax_Handler
     */
    private static $instance = null;
    
    /**
     * Allowed AJAX actions and their required capabilities
     * 
     * @var array
     */
    private $ajax_actions = array(
        'cgap_generate_content' => array(
            'capability' => 'edit_posts',
            'method' => 'handle_generate_content',
            'nonce_action' => 'cgap_generate_content'
        ),
        'cgap_save_settings' => array(
            'capability' => 'manage_options',
            'method' => 'handle_save_settings',
            'nonce_action' => 'cgap_save_settings'
        ),
        'cgap_test_api' => array(
            'capability' => 'manage_options',
            'method' => 'handle_test_api',
            'nonce_action' => 'cgap_test_api'
        ),
        'cgap_add_scheduled_post' => array(
            'capability' => 'manage_options',
            'method' => 'handle_add_scheduled_post',
            'nonce_action' => 'cgap_add_scheduled_post'
        ),
        'cgap_delete_scheduled_post' => array(
            'capability' => 'manage_options',
            'method' => 'handle_delete_scheduled_post',
            'nonce_action' => 'cgap_delete_scheduled_post'
        ),
        'cgap_toggle_scheduled_post' => array(
            'capability' => 'manage_options',
            'method' => 'handle_toggle_scheduled_post',
            'nonce_action' => 'cgap_toggle_scheduled_post'
        ),
        'cgap_get_log_details' => array(
            'capability' => 'manage_options',
            'method' => 'handle_get_log_details',
            'nonce_action' => 'cgap_get_log_details'
        ),
        'cgap_export_logs' => array(
            'capability' => 'manage_options',
            'method' => 'handle_export_logs',
            'nonce_action' => 'cgap_export_logs'
        ),
        'cgap_clear_logs' => array(
            'capability' => 'manage_options',
            'method' => 'handle_clear_logs',
            'nonce_action' => 'cgap_clear_logs'
        ),
        'cgap_analyze_content' => array(
            'capability' => 'edit_posts',
            'method' => 'handle_analyze_content',
            'nonce_action' => 'cgap_analyze_content'
        ),
        'cgap_get_content_suggestions' => array(
            'capability' => 'edit_posts',
            'method' => 'handle_get_content_suggestions',
            'nonce_action' => 'cgap_get_content_suggestions'
        ),
        'cgap_check_content_gaps' => array(
            'capability' => 'edit_posts',
            'method' => 'handle_check_content_gaps',
            'nonce_action' => 'cgap_check_content_gaps'
        ),
        'cgap_translate_content' => array(
            'capability' => 'edit_posts',
            'method' => 'handle_translate_content',
            'nonce_action' => 'cgap_translate_content'
        ),
        'cgap_check_seo_plugins' => array(
            'capability' => 'manage_options',
            'method' => 'handle_check_seo_plugins',
            'nonce_action' => 'cgap_check_seo_plugins'
        ),
        'cgap_create_translated_post' => array(
            'capability' => 'edit_posts',
            'method' => 'handle_create_translated_post',
            'nonce_action' => 'cgap_create_translated_post'
        )
    );
    
    /**
     * Get instance
     * 
     * @return CGAP_Ajax_Handler
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Register AJAX handlers for logged-in users
        foreach ($this->ajax_actions as $action => $config) {
            add_action('wp_ajax_' . $action, array($this, 'route_ajax_request'));
        }
        
        // Add nopriv hooks for public actions if needed
        $public_actions = array(); // Add actions that should work for non-logged-in users
        foreach ($public_actions as $action) {
            add_action('wp_ajax_nopriv_' . $action, array($this, 'route_ajax_request'));
        }
    }
    
    /**
     * Route AJAX requests to appropriate handlers
     */
    public function route_ajax_request() {
        try {
            // Get the action from the request
            $action = sanitize_text_field($_REQUEST['action'] ?? '');
            
            if (empty($action) || !isset($this->ajax_actions[$action])) {
                $this->send_error_response(__('Invalid action', 'chatgpt-auto-publisher'), 400);
                return;
            }
            
            $config = $this->ajax_actions[$action];
            
            // Verify nonce
            $nonce_field = $_REQUEST['nonce'] ?? $_REQUEST['_wpnonce'] ?? '';
            if (!wp_verify_nonce($nonce_field, $config['nonce_action'])) {
                $this->send_error_response(__('Security check failed', 'chatgpt-auto-publisher'), 403);
                return;
            }
            
            // Check user capabilities
            if (!current_user_can($config['capability'])) {
                $this->send_error_response(__('Insufficient permissions', 'chatgpt-auto-publisher'), 403);
                return;
            }
            
            // Check rate limiting
            if (!$this->check_rate_limit()) {
                $this->send_error_response(__('Rate limit exceeded. Please try again later.', 'chatgpt-auto-publisher'), 429);
                return;
            }
            
            // Call the appropriate handler method
            if (method_exists($this, $config['method'])) {
                call_user_func(array($this, $config['method']));
            } else {
                $this->send_error_response(__('Handler method not found', 'chatgpt-auto-publisher'), 500);
            }
            
        } catch (Exception $e) {
            cgap_log('AJAX Error: ' . $e->getMessage(), 'error');
            $this->send_error_response($e->getMessage(), 500);
        }
    }
    
    /**
     * Handle content generation request
     */
    private function handle_generate_content() {
        // Validate required fields
        $required_fields = array('topic');
        $validation_errors = $this->validate_required_fields($required_fields);
        
        if (!empty($validation_errors)) {
            $this->send_error_response(implode(', ', $validation_errors), 400);
            return;
        }
        
        // Sanitize and validate input
        $topic = sanitize_text_field($_POST['topic']);
        $focus_keyword = sanitize_text_field($_POST['focus_keyword'] ?? '');
        $content_language = sanitize_text_field($_POST['content_language'] ?? 'en');
        $tone = sanitize_text_field($_POST['tone'] ?? 'professional');
        $length = sanitize_text_field($_POST['length'] ?? 'medium');
        $auto_publish = filter_var($_POST['auto_publish'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $seo_plugin = sanitize_text_field($_POST['seo_plugin'] ?? '');
        $enable_seo_analysis = filter_var($_POST['enable_seo_analysis'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $ai_optimization = filter_var($_POST['ai_optimization'] ?? false, FILTER_VALIDATE_BOOLEAN);
        
        // Validate tone and length values
        $valid_tones = array('professional', 'casual', 'technical', 'friendly');
        $valid_lengths = array('short', 'medium', 'long');
        
        if (!in_array($tone, $valid_tones)) {
            $tone = 'professional';
        }
        
        if (!in_array($length, $valid_lengths)) {
            $length = 'medium';
        }
        
        // Prepare SEO options
        $seo_options = array(
            'plugin' => $seo_plugin,
            'enable_analysis' => $enable_seo_analysis,
            'ai_optimization' => $ai_optimization
        );
        
        try {
            $generator = new CGAP_Post_Generator();
            $result = $generator->generate_post($topic, $focus_keyword, $tone, $length, $auto_publish, $content_language, $seo_options);
            
            if ($result) {
                $this->send_success_response($result);
            } else {
                $this->send_error_response(__('Failed to generate content', 'chatgpt-auto-publisher'), 500);
            }
            
        } catch (Exception $e) {
            cgap_log('Content Generation Error: ' . $e->getMessage(), 'error');
            $this->send_error_response($e->getMessage(), 500);
        }
    }
    
    /**
     * Handle settings save request
     */
    private function handle_save_settings() {
        try {
            $settings_manager = new CGAP_Settings();
            
            // Sanitize settings data
            $settings = array();
            $settings['openai_api_key'] = sanitize_text_field($_POST['openai_api_key'] ?? '');
            $settings['default_model'] = sanitize_text_field($_POST['default_model'] ?? 'gpt-3.5-turbo');
            $settings['max_tokens'] = absint($_POST['max_tokens'] ?? 1500);
            $settings['temperature'] = floatval($_POST['temperature'] ?? 0.7);
            $settings['auto_publish'] = filter_var($_POST['auto_publish'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $settings['default_post_status'] = sanitize_text_field($_POST['default_post_status'] ?? 'draft');
            $settings['enable_scheduling'] = filter_var($_POST['enable_scheduling'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $settings['default_category'] = absint($_POST['default_category'] ?? 1);
            $settings['seo_optimization'] = filter_var($_POST['seo_optimization'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $settings['include_images'] = filter_var($_POST['include_images'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $settings['default_tone'] = sanitize_text_field($_POST['default_tone'] ?? 'professional');
            $settings['default_length'] = sanitize_text_field($_POST['default_length'] ?? 'medium');
            $settings['rate_limit_requests'] = absint($_POST['rate_limit_requests'] ?? 60);
            $settings['rate_limit_window'] = absint($_POST['rate_limit_window'] ?? 3600);
            $settings['log_retention_days'] = absint($_POST['log_retention_days'] ?? 30);
            $settings['enable_logging'] = filter_var($_POST['enable_logging'] ?? true, FILTER_VALIDATE_BOOLEAN);
            
            $result = $settings_manager->update_settings($settings);
            
            if ($result) {
                $this->send_success_response(__('Settings saved successfully!', 'chatgpt-auto-publisher'));
            } else {
                $this->send_error_response(__('Failed to save settings', 'chatgpt-auto-publisher'), 500);
            }
            
        } catch (Exception $e) {
            cgap_log('Settings Save Error: ' . $e->getMessage(), 'error');
            $this->send_error_response($e->getMessage(), 500);
        }
    }
    
    /**
     * Handle API test request
     */
    private function handle_test_api() {
        $api_key = sanitize_text_field($_POST['api_key'] ?? '');
        
        if (empty($api_key)) {
            $this->send_error_response(__('API key is required', 'chatgpt-auto-publisher'), 400);
            return;
        }
        
        try {
            $openai = new CGAP_OpenAI_API($api_key);
            $test_result = $openai->test_connection();
            
            if ($test_result) {
                $this->send_success_response(__('API connection successful!', 'chatgpt-auto-publisher'));
            } else {
                $this->send_error_response(__('API connection failed', 'chatgpt-auto-publisher'), 400);
            }
            
        } catch (Exception $e) {
            cgap_log('API Test Error: ' . $e->getMessage(), 'error');
            $this->send_error_response($e->getMessage(), 400);
        }
    }
    
    /**
     * Handle add scheduled post request
     */
    private function handle_add_scheduled_post() {
        $required_fields = array('title', 'keywords', 'frequency');
        $validation_errors = $this->validate_required_fields($required_fields);
        
        if (!empty($validation_errors)) {
            $this->send_error_response(implode(', ', $validation_errors), 400);
            return;
        }
        
        $title = sanitize_text_field($_POST['title']);
        $keywords = sanitize_text_field($_POST['keywords']);
        $frequency = sanitize_text_field($_POST['frequency']);
        
        // Validate frequency
        $valid_frequencies = array('hourly', 'daily', 'weekly', 'monthly');
        if (!in_array($frequency, $valid_frequencies)) {
            $this->send_error_response(__('Invalid frequency', 'chatgpt-auto-publisher'), 400);
            return;
        }
        
        try {
            $scheduler = new CGAP_Scheduler();
            $result = $scheduler->add_scheduled_post($title, $keywords, $frequency);
            
            if ($result) {
                $this->send_success_response(__('Scheduled post added successfully!', 'chatgpt-auto-publisher'));
            } else {
                $this->send_error_response(__('Failed to add scheduled post', 'chatgpt-auto-publisher'), 500);
            }
            
        } catch (Exception $e) {
            cgap_log('Add Scheduled Post Error: ' . $e->getMessage(), 'error');
            $this->send_error_response($e->getMessage(), 500);
        }
    }
    
    /**
     * Handle delete scheduled post request
     */
    private function handle_delete_scheduled_post() {
        $id = absint($_POST['id'] ?? 0);
        
        if (empty($id)) {
            $this->send_error_response(__('Invalid post ID', 'chatgpt-auto-publisher'), 400);
            return;
        }
        
        try {
            $scheduler = new CGAP_Scheduler();
            $result = $scheduler->delete_scheduled_post($id);
            
            if ($result) {
                $this->send_success_response(__('Scheduled post deleted successfully!', 'chatgpt-auto-publisher'));
            } else {
                $this->send_error_response(__('Failed to delete scheduled post', 'chatgpt-auto-publisher'), 500);
            }
            
        } catch (Exception $e) {
            cgap_log('Delete Scheduled Post Error: ' . $e->getMessage(), 'error');
            $this->send_error_response($e->getMessage(), 500);
        }
    }
    
    /**
     * Handle toggle scheduled post status
     */
    private function handle_toggle_scheduled_post() {
        $id = absint($_POST['id'] ?? 0);
        
        if (empty($id)) {
            $this->send_error_response(__('Invalid post ID', 'chatgpt-auto-publisher'), 400);
            return;
        }
        
        try {
            $scheduler = new CGAP_Scheduler();
            $result = $scheduler->toggle_scheduled_post($id);
            
            $this->send_success_response($result);
            
        } catch (Exception $e) {
            cgap_log('Toggle Scheduled Post Error: ' . $e->getMessage(), 'error');
            $this->send_error_response($e->getMessage(), 500);
        }
    }
    
    /**
     * Handle get log details request
     */
    private function handle_get_log_details() {
        $id = absint($_POST['id'] ?? 0);
        
        if (empty($id)) {
            $this->send_error_response(__('Invalid log ID', 'chatgpt-auto-publisher'), 400);
            return;
        }
        
        try {
            global $wpdb;
            $table_name = $wpdb->prefix . 'cgap_generation_logs';
            
            $log = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE id = %d",
                $id
            ));
            
            if (!$log) {
                $this->send_error_response(__('Log not found', 'chatgpt-auto-publisher'), 404);
                return;
            }
            
            // Format log details for display
            $html = '<div class="cgap-log-details">';
            $html .= '<h4>' . __('Generation Details', 'chatgpt-auto-publisher') . '</h4>';
            $html .= '<p><strong>' . __('Date:', 'chatgpt-auto-publisher') . '</strong> ' . date('M j, Y H:i:s', strtotime($log->created_at)) . '</p>';
            $html .= '<p><strong>' . __('Model:', 'chatgpt-auto-publisher') . '</strong> ' . esc_html($log->model) . '</p>';
            $html .= '<p><strong>' . __('Tokens Used:', 'chatgpt-auto-publisher') . '</strong> ' . number_format($log->tokens_used) . '</p>';
            $html .= '<p><strong>' . __('Cost:', 'chatgpt-auto-publisher') . '</strong> ' . cgap_format_cost($log->cost) . '</p>';
            $html .= '<p><strong>' . __('Status:', 'chatgpt-auto-publisher') . '</strong> ' . ucfirst($log->status) . '</p>';
            
            if ($log->post_id && get_post($log->post_id)) {
                $html .= '<p><strong>' . __('Post:', 'chatgpt-auto-publisher') . '</strong> <a href="' . get_edit_post_link($log->post_id) . '" target="_blank">' . esc_html(get_the_title($log->post_id)) . '</a></p>';
            }
            
            $html .= '<h5>' . __('Prompt:', 'chatgpt-auto-publisher') . '</h5>';
            $html .= '<div class="cgap-log-prompt">' . nl2br(esc_html($log->prompt)) . '</div>';
            
            $html .= '<h5>' . __('Response Preview:', 'chatgpt-auto-publisher') . '</h5>';
            $html .= '<div class="cgap-log-response">' . nl2br(esc_html(wp_trim_words($log->response, 100))) . '</div>';
            $html .= '</div>';
            
            $this->send_success_response($html);
            
        } catch (Exception $e) {
            cgap_log('Get Log Details Error: ' . $e->getMessage(), 'error');
            $this->send_error_response($e->getMessage(), 500);
        }
    }
    
    /**
     * Handle export logs request
     */
    private function handle_export_logs() {
        try {
            global $wpdb;
            $table_name = $wpdb->prefix . 'cgap_generation_logs';
            
            $logs = $wpdb->get_results(
                "SELECT id, post_id, model, tokens_used, cost, status, created_at FROM {$table_name} ORDER BY created_at DESC"
            );
            
            if (empty($logs)) {
                $this->send_error_response(__('No logs to export', 'chatgpt-auto-publisher'), 404);
                return;
            }
            
            // Set headers for CSV download
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="cgap-logs-' . date('Y-m-d') . '.csv"');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            // Output CSV
            $output = fopen('php://output', 'w');
            
            // CSV headers
            fputcsv($output, array(
                'ID',
                'Post ID',
                'Post Title',
                'Model',
                'Tokens Used',
                'Cost',
                'Status',
                'Created At'
            ));
            
            // CSV data
            foreach ($logs as $log) {
                $post_title = $log->post_id ? get_the_title($log->post_id) : 'N/A';
                
                fputcsv($output, array(
                    $log->id,
                    $log->post_id ?: 'N/A',
                    $post_title,
                    $log->model,
                    $log->tokens_used,
                    $log->cost,
                    $log->status,
                    $log->created_at
                ));
            }
            
            fclose($output);
            exit;
            
        } catch (Exception $e) {
            cgap_log('Export Logs Error: ' . $e->getMessage(), 'error');
            $this->send_error_response($e->getMessage(), 500);
        }
    }
    
    /**
     * Handle clear logs request
     */
    private function handle_clear_logs() {
        try {
            cgap_clean_old_logs();
            $this->send_success_response(__('Old logs cleared successfully!', 'chatgpt-auto-publisher'));
            
        } catch (Exception $e) {
            cgap_log('Clear Logs Error: ' . $e->getMessage(), 'error');
            $this->send_error_response($e->getMessage(), 500);
        }
    }
    
    /**
     * Handle content analysis request
     */
    private function handle_analyze_content() {
        try {
            $seo_integration = new CGAP_SEO_Integration();
            $seo_integration->analyze_content();
        } catch (Exception $e) {
            cgap_log('Content Analysis Error: ' . $e->getMessage(), 'error');
            $this->send_error_response($e->getMessage(), 500);
        }
    }
    
    /**
     * Handle get content suggestions request
     */
    private function handle_get_content_suggestions() {
        try {
            $analyzer = new CGAP_Content_Quality_Analyzer();
            $analyzer->get_content_suggestions();
        } catch (Exception $e) {
            cgap_log('Content Suggestions Error: ' . $e->getMessage(), 'error');
            $this->send_error_response($e->getMessage(), 500);
        }
    }
    
    /**
     * Handle check content gaps request
     */
    private function handle_check_content_gaps() {
        try {
            $analyzer = new CGAP_Content_Quality_Analyzer();
            $analyzer->check_content_gaps();
        } catch (Exception $e) {
            cgap_log('Content Gaps Error: ' . $e->getMessage(), 'error');
            $this->send_error_response($e->getMessage(), 500);
        }
    }
    
    /**
     * Handle translate content request
     */
    private function handle_translate_content() {
        try {
            $seo_integration = new CGAP_SEO_Integration();
            $seo_integration->translate_content();
        } catch (Exception $e) {
            cgap_log('Translation Error: ' . $e->getMessage(), 'error');
            $this->send_error_response($e->getMessage(), 500);
        }
    }
    
    /**
     * Handle check SEO plugins request
     */
    private function handle_check_seo_plugins() {
        try {
            $seo_integration = new CGAP_SEO_Integration();
            $plugins = $seo_integration->get_available_seo_plugins();
            $this->send_success_response($plugins);
        } catch (Exception $e) {
            cgap_log('SEO Plugin Check Error: ' . $e->getMessage(), 'error');
            $this->send_error_response($e->getMessage(), 500);
        }
    }
    
    /**
     * Handle create translated post request
     */
    private function handle_create_translated_post() {
        $title = sanitize_text_field($_POST['title'] ?? '');
        $content = wp_kses_post($_POST['content'] ?? '');
        $language = sanitize_text_field($_POST['language'] ?? 'en');
        
        if (empty($title) || empty($content)) {
            $this->send_error_response(__('Title and content are required', 'chatgpt-auto-publisher'), 400);
            return;
        }
        
        try {
            $post_data = array(
                'post_title' => $title,
                'post_content' => $content,
                'post_status' => 'draft',
                'post_type' => 'post',
                'meta_input' => array(
                    '_cgap_generated' => true,
                    '_cgap_translated' => true,
                    '_cgap_language' => $language
                )
            );
            
            $post_id = wp_insert_post($post_data);
            
            if (is_wp_error($post_id)) {
                throw new Exception('Failed to create translated post: ' . $post_id->get_error_message());
            }
            
            $this->send_success_response(array(
                'post_id' => $post_id,
                'edit_url' => admin_url('post.php?post=' . $post_id . '&action=edit'),
                'view_url' => get_permalink($post_id)
            ));
            
        } catch (Exception $e) {
            cgap_log('Create Translated Post Error: ' . $e->getMessage(), 'error');
            $this->send_error_response($e->getMessage(), 500);
        }
    }
    
    /**
     * Validate required fields
     * 
     * @param array $required_fields
     * @return array
     */
    private function validate_required_fields($required_fields) {
        $errors = array();
        
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $errors[] = sprintf(__('%s is required', 'chatgpt-auto-publisher'), ucfirst(str_replace('_', ' ', $field)));
            }
        }
        
        return $errors;
    }
    
    /**
     * Check rate limiting
     * 
     * @return bool
     */
    private function check_rate_limit() {
        try {
            $settings = new CGAP_Settings();
            return $settings->check_rate_limit();
        } catch (Exception $e) {
            // If rate limit check fails, allow the request but log the error
            cgap_log('Rate Limit Check Error: ' . $e->getMessage(), 'warning');
            return true;
        }
    }
    
    /**
     * Send success response
     * 
     * @param mixed $data
     */
    private function send_success_response($data = null) {
        wp_send_json_success($data);
    }
    
    /**
     * Send error response
     * 
     * @param string $message
     * @param int $status_code
     */
    private function send_error_response($message, $status_code = 400) {
        status_header($status_code);
        wp_send_json_error($message);
    }
}

// Initialize the AJAX handler
CGAP_Ajax_Handler::get_instance();