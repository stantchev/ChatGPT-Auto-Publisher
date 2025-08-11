<?php
/**
 * Post Generator Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class CGAP_Post_Generator {
    
    private $openai;
    private $settings;
    
    public function __construct() {
        $this->settings = get_option('cgap_settings', array());
        $this->openai = new CGAP_OpenAI_API();
    }
    
    /**
     * Generate a complete blog post
     */
    public function generate_post($topic, $focus_keyword = '', $tone = 'professional', $length = 'medium', $auto_publish = false, $language = 'en', $seo_options = array()) {
        try {
            // Create the prompt
            $prompt = $this->create_prompt($topic, $focus_keyword, $tone, $length, $language);
            
            // Generate content
            $system_message = $this->get_system_message($tone, $language);
            $result = $this->openai->generate_content($prompt, $system_message);
            
            // Parse the generated content
            $parsed_content = $this->parse_generated_content($result['content']);
            
            // Generate image if enabled
            $featured_image_id = null;
            if ($this->settings['include_images']) {
                $featured_image_id = $this->generate_featured_image($topic);
            }
            
            // Initialize SEO integration
            $seo_integration = new CGAP_SEO_Integration();
            
            // Create WordPress post
            $post_data = array(
                'post_title' => $parsed_content['title'],
                'post_content' => $parsed_content['content'],
                'post_excerpt' => $parsed_content['excerpt'],
                'post_status' => $auto_publish ? 'publish' : ($this->settings['default_post_status'] ?: 'draft'),
                'post_type' => 'post',
                'post_category' => array($this->settings['default_category'] ?: 1),
                'meta_input' => array(
                    '_cgap_generated' => true,
                    '_cgap_topic' => $topic,
                    '_cgap_focus_keyword' => $focus_keyword,
                    '_cgap_language' => $language,
                    '_cgap_tokens_used' => $result['tokens_used'],
                    '_cgap_model' => $result['model']
                )
            );
            
            $post_id = wp_insert_post($post_data);
            
            if (is_wp_error($post_id)) {
                throw new Exception('Failed to create post: ' . $post_id->get_error_message());
            }
            
            // Set SEO meta data using integration
            if ($this->settings['seo_optimization']) {
                $seo_meta = array(
                    'meta_description' => $parsed_content['meta_description'],
                    'focus_keyword' => $focus_keyword,
                    'seo_title' => $parsed_content['title']
                );
                $seo_integration->set_seo_meta($post_id, $seo_meta);
            }
            
            // Set featured image
            if ($featured_image_id) {
                set_post_thumbnail($post_id, $featured_image_id);
            }
            
            // Log the generation
            $this->log_generation($post_id, $prompt, $result);
            
            return array(
                'post_id' => $post_id,
                'title' => $parsed_content['title'],
                'content' => $parsed_content['content'],
                'excerpt' => $parsed_content['excerpt'],
                'meta_description' => $parsed_content['meta_description'],
                'tokens_used' => $result['tokens_used'],
                'model' => $result['model'],
                'cost' => $this->openai->calculate_cost($result['tokens_used'], $result['model']),
                'edit_url' => admin_url('post.php?post=' . $post_id . '&action=edit'),
                'view_url' => get_permalink($post_id)
            );
            
        } catch (Exception $e) {
            error_log('CGAP Post Generation Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Create prompt for content generation
     */
    private function create_prompt($topic, $focus_keyword, $tone, $length, $language = 'en') {
        $word_count = $this->get_word_count_for_length($length);
        $language_name = $this->get_language_name($language);
        
        $prompt = "Write a comprehensive blog post in {$language_name} about '{$topic}'";
        
        if (!empty($focus_keyword)) {
            $prompt .= " optimized for the focus keyword: '{$focus_keyword}'";
        }
        
        $prompt .= "\n\nRequirements:";
        $prompt .= "\n- Write approximately {$word_count} words";
        $prompt .= "\n- Write in {$language_name}";
        $prompt .= "\n- Use a {$tone} tone";
        $prompt .= "\n- Format the entire post using clean, valid HTML (not Markdown)";
        $prompt .= "\n- Include proper headings (H2, H3)";
        $prompt .= "\n- Write an engaging introduction";
        $prompt .= "\n- Provide valuable, actionable content";
        $prompt .= "\n- Include a strong conclusion";
        $prompt .= "\n- Optimize for SEO";
        $prompt .= "\n- Optimize for AIO - AI Overviews, ChatGPT";
        
        if (!empty($focus_keyword)) {
            $prompt .= "\n- Use the focus keyword '{$focus_keyword}' naturally throughout the content";
            $prompt .= "\n- Include the focus keyword in the title and first paragraph";
            $prompt .= "\n- Maintain optimal keyword density (0.5-2.5%)";
        }
        
        $prompt .= "\n\nFormat the response as follows:";
        $prompt .= "\n[TITLE]Your compelling title here[/TITLE]";
        $prompt .= "\n[META]Write a meta description (150-160 characters)[/META]";
        $prompt .= "\n[EXCERPT]Write a brief excerpt (150-200 words)[/EXCERPT]";
        $prompt .= "\n[CONTENT]Your full blog post content here[/CONTENT]";
        
        return $prompt;
    }
    
    /**
     * Get system message based on tone
     */
    private function get_system_message($tone, $language = 'en') {
        $language_name = $this->get_language_name($language);
        
        $messages = array(
            'professional' => "You are a professional content writer who creates authoritative, well-researched blog posts in {$language_name}. Write in a clear, professional tone that establishes expertise and trust. Follow SEO best practices and ensure content is optimized for search engines.",
            'casual' => "You are a friendly blogger who writes in a conversational, approachable style in {$language_name}. Use a warm, personal tone that connects with readers while maintaining SEO optimization.",
            'technical' => "You are a technical writer who creates detailed, accurate content in {$language_name} for knowledgeable audiences. Use precise terminology and provide in-depth explanations while ensuring SEO optimization.",
            'friendly' => "You are an enthusiastic content creator who writes engaging, upbeat content in {$language_name}. Use an encouraging, positive tone that motivates readers while following SEO best practices."
        );
        
        return $messages[$tone] ?? $messages['professional'];
    }
    
    /**
     * Get language name from code
     */
    private function get_language_name($code) {
        $languages = array(
            'en' => 'English',
            'bg' => 'Bulgarian',
            'es' => 'Spanish',
            'fr' => 'French',
            'de' => 'German',
            'it' => 'Italian',
            'pt' => 'Portuguese',
            'ru' => 'Russian'
        );
        
        return $languages[$code] ?? 'English';
    }
    
    /**
     * Parse generated content
     */
    private function parse_generated_content($content) {
        $parsed = array(
            'title' => '',
            'meta_description' => '',
            'excerpt' => '',
            'content' => ''
        );
        
        // Extract title
        if (preg_match('/\[TITLE\](.*?)\[\/TITLE\]/s', $content, $matches)) {
            $parsed['title'] = trim($matches[1]);
        }
        
        // Extract meta description
        if (preg_match('/\[META\](.*?)\[\/META\]/s', $content, $matches)) {
            $parsed['meta_description'] = trim($matches[1]);
        }
        
        // Extract excerpt
        if (preg_match('/\[EXCERPT\](.*?)\[\/EXCERPT\]/s', $content, $matches)) {
            $parsed['excerpt'] = trim($matches[1]);
        }
        
        // Extract content
        if (preg_match('/\[CONTENT\](.*?)\[\/CONTENT\]/s', $content, $matches)) {
            $parsed['content'] = trim($matches[1]);
        } else {
            // Fallback: use entire content if no tags found
            $parsed['content'] = $content;
        }
        
        // Generate fallbacks if sections are missing
        if (empty($parsed['title'])) {
            $lines = explode("\n", $content);
            $parsed['title'] = trim($lines[0]);
        }
        
        if (empty($parsed['excerpt'])) {
            $parsed['excerpt'] = wp_trim_words($parsed['content'], 30);
        }
        
        return $parsed;
    }
    
    /**
     * Generate featured image
     */
    private function generate_featured_image($topic) {
        try {
            $image_prompt = "Create a professional, high-quality featured image for a blog post about: {$topic}. Style: modern, clean, professional.";
            $image_url = $this->openai->generate_image($image_prompt);
            
            // Download and save image
            $image_id = $this->save_image_from_url($image_url, $topic);
            return $image_id;
            
        } catch (Exception $e) {
            error_log('CGAP Image Generation Error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Save image from URL to media library
     */
    private function save_image_from_url($url, $title) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $tmp = download_url($url);
        
        if (is_wp_error($tmp)) {
            return null;
        }
        
        $file_array = array(
            'name' => sanitize_file_name($title) . '.png',
            'tmp_name' => $tmp
        );
        
        $id = media_handle_sideload($file_array, 0);
        
        if (is_wp_error($id)) {
            @unlink($tmp);
            return null;
        }
        
        return $id;
    }
    
    /**
     * Get word count for length setting
     */
    private function get_word_count_for_length($length) {
        $counts = array(
            'short' => 400,
            'medium' => 800,
            'long' => 1500
        );
        
        return $counts[$length] ?? 800;
    }
    
    /**
     * Log generation to database
     */
    private function log_generation($post_id, $prompt, $result) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cgap_generation_logs';
        
        $wpdb->insert(
            $table_name,
            array(
                'post_id' => $post_id,
                'prompt' => $prompt,
                'response' => $result['content'],
                'model' => $result['model'],
                'tokens_used' => $result['tokens_used'],
                'cost' => $this->openai->calculate_cost($result['tokens_used'], $result['model']),
                'status' => 'completed',
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%d', '%f', '%s', '%s')
        );
    }
}
