<?php
/**
 * OpenAI API Integration Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class CGAP_OpenAI_API {
    
    private $api_key;
    private $api_url = 'https://api.openai.com/v1/';
    private $model;
    private $max_tokens;
    private $temperature;
    
    public function __construct($api_key = null) {
        $settings = get_option('cgap_settings', array());
        
        $this->api_key = $api_key ?: $settings['openai_api_key'];
        $this->model = $settings['default_model'] ?: 'gpt-3.5-turbo';
        $this->max_tokens = $settings['max_tokens'] ?: 1500;
        $this->temperature = $settings['temperature'] ?: 0.7;
        
        if (empty($this->api_key)) {
            throw new Exception(__('OpenAI API key is not configured', 'chatgpt-auto-publisher'));
        }
    }
    
    /**
     * Test API connection
     */
    public function test_connection() {
        try {
            $response = $this->make_request('models', 'GET');
            return isset($response['data']) && is_array($response['data']);
        } catch (Exception $e) {
            error_log('CGAP API Test Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate content using ChatGPT
     */
    public function generate_content($prompt, $system_message = null) {
        $messages = array();
        
        if ($system_message) {
            $messages[] = array(
                'role' => 'system',
                'content' => $system_message
            );
        }
        
        $messages[] = array(
            'role' => 'user',
            'content' => $prompt
        );
        
        $data = array(
            'model' => $this->model,
            'messages' => $messages,
            'max_tokens' => $this->max_tokens,
            'temperature' => $this->temperature,
            'top_p' => 1,
            'frequency_penalty' => 0,
            'presence_penalty' => 0
        );
        
        $response = $this->make_request('chat/completions', 'POST', $data);
        
        if (isset($response['choices'][0]['message']['content'])) {
            return array(
                'content' => trim($response['choices'][0]['message']['content']),
                'tokens_used' => $response['usage']['total_tokens'] ?? 0,
                'model' => $response['model'] ?? $this->model
            );
        }
        
        throw new Exception(__('Invalid response from OpenAI API', 'chatgpt-auto-publisher'));
    }
    
    /**
     * Generate image using DALL-E
     */
    public function generate_image($prompt, $size = '1024x1024') {
        $data = array(
            'prompt' => $prompt,
            'n' => 1,
            'size' => $size,
            'response_format' => 'url'
        );
        
        $response = $this->make_request('images/generations', 'POST', $data);
        
        if (isset($response['data'][0]['url'])) {
            return $response['data'][0]['url'];
        }
        
        throw new Exception(__('Failed to generate image', 'chatgpt-auto-publisher'));
    }
    
    /**
     * Make API request
     */
    private function make_request($endpoint, $method = 'POST', $data = null) {
        $url = $this->api_url . $endpoint;
        
        $headers = array(
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json',
            'User-Agent' => 'ChatGPT-Auto-Publisher/' . CGAP_VERSION
        );
        
        $args = array(
            'method' => $method,
            'headers' => $headers,
            'timeout' => 60,
            'sslverify' => true
        );
        
        if ($data && $method !== 'GET') {
            $args['body'] = json_encode($data);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            throw new Exception('API Request Error: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded_body = json_decode($body, true);
        
        if ($status_code >= 400) {
            $error_message = 'API Error (' . $status_code . ')';
            
            if (isset($decoded_body['error']['message'])) {
                $error_message .= ': ' . $decoded_body['error']['message'];
            }
            
            throw new Exception($error_message);
        }
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from API');
        }
        
        return $decoded_body;
    }
    
    /**
     * Calculate estimated cost
     */
    public function calculate_cost($tokens, $model = null) {
        $model = $model ?: $this->model;
        
        // Pricing per 1K tokens (as of 2024)
        $pricing = array(
            'gpt-3.5-turbo' => 0.002,
            'gpt-4' => 0.03,
            'gpt-4-turbo' => 0.01,
            'gpt-4o' => 0.005
        );
        
        $rate = $pricing[$model] ?? 0.002;
        return ($tokens / 1000) * $rate;
    }
}