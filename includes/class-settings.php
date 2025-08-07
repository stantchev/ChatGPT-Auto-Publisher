<?php
/**
 * Settings Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class CGAP_Settings {
    
    private $option_name = 'cgap_settings';
    private $defaults;
    
    public function __construct() {
        $this->defaults = array(
            'openai_api_key' => '',
            'default_model' => 'gpt-3.5-turbo',
            'max_tokens' => 1500,
            'temperature' => 0.7,
            'auto_publish' => false,
            'default_post_status' => 'draft',
            'enable_scheduling' => false,
            'default_category' => 1,
            'seo_optimization' => true,
            'include_images' => false,
            'dalle_enabled' => false,
            'default_tone' => 'professional',
            'default_length' => 'medium',
            'rate_limit_requests' => 60,
            'rate_limit_window' => 3600,
            'enable_logging' => true,
            'log_retention_days' => 30
        );
    }
    
    /**
     * Get all settings
     */
    public function get_settings() {
        $settings = get_option($this->option_name, array());
        return wp_parse_args($settings, $this->defaults);
    }
    
    /**
     * Get specific setting
     */
    public function get_setting($key, $default = null) {
        $settings = $this->get_settings();
        return isset($settings[$key]) ? $settings[$key] : ($default ?: $this->defaults[$key]);
    }
    
    /**
     * Update settings
     */
    public function update_settings($new_settings) {
        $current_settings = $this->get_settings();
        $updated_settings = wp_parse_args($new_settings, $current_settings);
        
        // Validate settings
        $validated_settings = $this->validate_settings($updated_settings);
        
        return update_option($this->option_name, $validated_settings);
    }
    
    /**
     * Update specific setting
     */
    public function update_setting($key, $value) {
        $settings = $this->get_settings();
        $settings[$key] = $value;
        return $this->update_settings($settings);
    }
    
    /**
     * Validate settings
     */
    private function validate_settings($settings) {
        // Sanitize API key
        if (isset($settings['openai_api_key'])) {
            $settings['openai_api_key'] = sanitize_text_field($settings['openai_api_key']);
        }
        
        // Validate model
        $valid_models = array('gpt-3.5-turbo', 'gpt-4', 'gpt-4-turbo', 'gpt-4o');
        if (!in_array($settings['default_model'], $valid_models)) {
            $settings['default_model'] = 'gpt-3.5-turbo';
        }
        
        // Validate numeric values
        $settings['max_tokens'] = max(100, min(4000, intval($settings['max_tokens'])));
        $settings['temperature'] = max(0, min(2, floatval($settings['temperature'])));
        $settings['default_category'] = max(1, intval($settings['default_category']));
        $settings['rate_limit_requests'] = max(1, intval($settings['rate_limit_requests']));
        $settings['rate_limit_window'] = max(60, intval($settings['rate_limit_window']));
        $settings['log_retention_days'] = max(1, min(365, intval($settings['log_retention_days'])));
        
        // Validate post status
        $valid_statuses = array('draft', 'publish', 'private');
        if (!in_array($settings['default_post_status'], $valid_statuses)) {
            $settings['default_post_status'] = 'draft';
        }
        
        // Validate tone
        $valid_tones = array('professional', 'casual', 'technical', 'friendly');
        if (!in_array($settings['default_tone'], $valid_tones)) {
            $settings['default_tone'] = 'professional';
        }
        
        // Validate length
        $valid_lengths = array('short', 'medium', 'long');
        if (!in_array($settings['default_length'], $valid_lengths)) {
            $settings['default_length'] = 'medium';
        }
        
        // Convert boolean values
        $boolean_fields = array(
            'auto_publish', 'enable_scheduling', 'seo_optimization', 
            'include_images', 'dalle_enabled', 'enable_logging'
        );
        
        foreach ($boolean_fields as $field) {
            $settings[$field] = (bool) $settings[$field];
        }
        
        return $settings;
    }
    
    /**
     * Reset settings to defaults
     */
    public function reset_settings() {
        return update_option($this->option_name, $this->defaults);
    }
    
    /**
     * Export settings
     */
    public function export_settings() {
        $settings = $this->get_settings();
        
        // Remove sensitive data
        unset($settings['openai_api_key']);
        
        return json_encode($settings, JSON_PRETTY_PRINT);
    }
    
    /**
     * Import settings
     */
    public function import_settings($json_data) {
        $imported_settings = json_decode($json_data, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception(__('Invalid JSON data', 'chatgpt-auto-publisher'));
        }
        
        // Don't import sensitive data
        unset($imported_settings['openai_api_key']);
        
        return $this->update_settings($imported_settings);
    }
    
    /**
     * Get available models
     */
    public function get_available_models() {
        return array(
            'gpt-3.5-turbo' => array(
                'name' => 'GPT-3.5 Turbo',
                'description' => 'Fast and cost-effective for most tasks',
                'max_tokens' => 4096,
                'cost_per_1k' => 0.002
            ),
            'gpt-4' => array(
                'name' => 'GPT-4',
                'description' => 'More capable and accurate, higher cost',
                'max_tokens' => 8192,
                'cost_per_1k' => 0.03
            ),
            'gpt-4-turbo' => array(
                'name' => 'GPT-4 Turbo',
                'description' => 'Latest GPT-4 with improved performance',
                'max_tokens' => 128000,
                'cost_per_1k' => 0.01
            ),
            'gpt-4o' => array(
                'name' => 'GPT-4o',
                'description' => 'Multimodal flagship model',
                'max_tokens' => 128000,
                'cost_per_1k' => 0.005
            )
        );
    }
    
    /**
     * Check if API key is configured
     */
    public function is_api_configured() {
        $api_key = $this->get_setting('openai_api_key');
        return !empty($api_key) && strlen($api_key) > 20;
    }
    
    /**
     * Get rate limit status
     */
    public function get_rate_limit_status() {
        $requests_made = get_transient('cgap_rate_limit_count') ?: 0;
        $limit = $this->get_setting('rate_limit_requests');
        
        return array(
            'requests_made' => $requests_made,
            'limit' => $limit,
            'remaining' => max(0, $limit - $requests_made),
            'reset_time' => get_transient('cgap_rate_limit_reset') ?: time() + $this->get_setting('rate_limit_window')
        );
    }
    
    /**
     * Check rate limit
     */
    public function check_rate_limit() {
        $status = $this->get_rate_limit_status();
        
        if ($status['remaining'] <= 0) {
            throw new Exception(__('Rate limit exceeded. Please try again later.', 'chatgpt-auto-publisher'));
        }
        
        // Increment counter
        $new_count = $status['requests_made'] + 1;
        set_transient('cgap_rate_limit_count', $new_count, $this->get_setting('rate_limit_window'));
        
        if (!get_transient('cgap_rate_limit_reset')) {
            set_transient('cgap_rate_limit_reset', time() + $this->get_setting('rate_limit_window'), $this->get_setting('rate_limit_window'));
        }
        
        return true;
    }
}