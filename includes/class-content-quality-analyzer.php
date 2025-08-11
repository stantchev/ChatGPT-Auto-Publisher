<?php
/**
 * Content Quality Analyzer Class
 * 
 * Advanced content analysis and optimization suggestions
 */

if (!defined('ABSPATH')) {
    exit;
}

class CGAP_Content_Quality_Analyzer {
    
    private $seo_integration;
    
    public function __construct() {
        $this->seo_integration = new CGAP_SEO_Integration();
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_cgap_get_content_suggestions', array($this, 'get_content_suggestions'));
        add_action('wp_ajax_cgap_analyze_competitors', array($this, 'analyze_competitors'));
        add_action('wp_ajax_cgap_check_content_gaps', array($this, 'check_content_gaps'));
    }
    
    /**
     * Get AI-powered content suggestions
     */
    public function get_content_suggestions() {
        check_ajax_referer('cgap_nonce', 'nonce');
        
        $content = sanitize_textarea_field($_POST['content'] ?? '');
        $keyword = sanitize_text_field($_POST['keyword'] ?? '');
        $title = sanitize_text_field($_POST['title'] ?? '');
        
        if (empty($content)) {
            wp_send_json_error(__('Content is required for analysis', 'chatgpt-auto-publisher'));
        }
        
        try {
            $openai = new CGAP_OpenAI_API();
            
            $prompt = "Analyze the following content and provide specific SEO and readability improvement suggestions:\n\n";
            $prompt .= "Title: {$title}\n";
            $prompt .= "Focus Keyword: {$keyword}\n";
            $prompt .= "Content: {$content}\n\n";
            $prompt .= "Provide suggestions in the following categories:\n";
            $prompt .= "1. SEO Optimization\n";
            $prompt .= "2. Content Structure\n";
            $prompt .= "3. Readability Improvements\n";
            $prompt .= "4. Keyword Optimization\n";
            $prompt .= "5. Content Gaps\n\n";
            $prompt .= "Format as JSON with categories as keys and arrays of suggestions as values.";
            
            $result = $openai->generate_content($prompt);
            
            // Try to parse JSON response
            $suggestions = json_decode($result['content'], true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                // Fallback: parse text response
                $suggestions = $this->parse_text_suggestions($result['content']);
            }
            
            wp_send_json_success(array(
                'suggestions' => $suggestions,
                'tokens_used' => $result['tokens_used']
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Parse text suggestions into structured format
     */
    private function parse_text_suggestions($text) {
        $suggestions = array(
            'SEO Optimization' => array(),
            'Content Structure' => array(),
            'Readability Improvements' => array(),
            'Keyword Optimization' => array(),
            'Content Gaps' => array()
        );
        
        $lines = explode("\n", $text);
        $current_category = null;
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Check if line is a category header
            foreach (array_keys($suggestions) as $category) {
                if (stripos($line, $category) !== false) {
                    $current_category = $category;
                    continue 2;
                }
            }
            
            // Add suggestion to current category
            if ($current_category && (preg_match('/^[-*•]\s*(.+)/', $line, $matches) || preg_match('/^\d+\.\s*(.+)/', $line, $matches))) {
                $suggestions[$current_category][] = trim($matches[1]);
            }
        }
        
        return $suggestions;
    }
    
    /**
     * Analyze competitors
     */
    public function analyze_competitors() {
        check_ajax_referer('cgap_nonce', 'nonce');
        
        $keyword = sanitize_text_field($_POST['keyword'] ?? '');
        
        if (empty($keyword)) {
            wp_send_json_error(__('Keyword is required for competitor analysis', 'chatgpt-auto-publisher'));
        }
        
        try {
            $openai = new CGAP_OpenAI_API();
            
            $prompt = "Provide a competitive analysis for the keyword '{$keyword}'. Include:\n\n";
            $prompt .= "1. Common content themes and topics\n";
            $prompt .= "2. Typical content length and structure\n";
            $prompt .= "3. Key points that top-ranking content covers\n";
            $prompt .= "4. Content gaps and opportunities\n";
            $prompt .= "5. Recommended content strategy\n\n";
            $prompt .= "Format as structured text with clear headings.";
            
            $result = $openai->generate_content($prompt);
            
            wp_send_json_success(array(
                'analysis' => $result['content'],
                'tokens_used' => $result['tokens_used']
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Check content gaps
     */
    public function check_content_gaps() {
        check_ajax_referer('cgap_nonce', 'nonce');
        
        $content = sanitize_textarea_field($_POST['content'] ?? '');
        $keyword = sanitize_text_field($_POST['keyword'] ?? '');
        
        if (empty($content) || empty($keyword)) {
            wp_send_json_error(__('Content and keyword are required', 'chatgpt-auto-publisher'));
        }
        
        try {
            $openai = new CGAP_OpenAI_API();
            
            $prompt = "Analyze the following content for the keyword '{$keyword}' and identify content gaps:\n\n";
            $prompt .= "Content: {$content}\n\n";
            $prompt .= "Identify:\n";
            $prompt .= "1. Missing subtopics that should be covered\n";
            $prompt .= "2. Questions readers might have that aren't answered\n";
            $prompt .= "3. Related keywords and topics to include\n";
            $prompt .= "4. Additional sections that would improve comprehensiveness\n";
            $prompt .= "5. Specific improvements to make the content more valuable\n\n";
            $prompt .= "Provide actionable recommendations.";
            
            $result = $openai->generate_content($prompt);
            
            wp_send_json_success(array(
                'gaps' => $result['content'],
                'tokens_used' => $result['tokens_used']
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Get content quality metrics
     */
    public function get_quality_metrics($content, $title = '', $keyword = '') {
        $metrics = array();
        
        // Basic metrics
        $word_count = str_word_count(strip_tags($content));
        $char_count = strlen(strip_tags($content));
        $paragraph_count = substr_count($content, '<p');
        
        $metrics['basic'] = array(
            'word_count' => $word_count,
            'character_count' => $char_count,
            'paragraph_count' => $paragraph_count,
            'reading_time' => ceil($word_count / 200) // Average reading speed
        );
        
        // SEO metrics
        $seo_analysis = $this->seo_integration->analyze_content();
        $metrics['seo'] = $seo_analysis;
        
        // Content structure
        $metrics['structure'] = array(
            'headings' => $this->count_headings($content),
            'lists' => substr_count($content, '<ul') + substr_count($content, '<ol'),
            'images' => substr_count($content, '<img'),
            'links' => substr_count($content, '<a')
        );
        
        // Keyword metrics
        if (!empty($keyword)) {
            $metrics['keyword'] = $this->analyze_keyword_metrics($content, $keyword);
        }
        
        return $metrics;
    }
    
    /**
     * Count headings by level
     */
    private function count_headings($content) {
        $headings = array();
        for ($i = 1; $i <= 6; $i++) {
            $headings["h{$i}"] = substr_count($content, "<h{$i}");
        }
        return $headings;
    }
    
    /**
     * Analyze keyword metrics
     */
    private function analyze_keyword_metrics($content, $keyword) {
        $text = strip_tags($content);
        $word_count = str_word_count($text);
        $keyword_count = substr_count(strtolower($text), strtolower($keyword));
        
        return array(
            'count' => $keyword_count,
            'density' => $word_count > 0 ? round(($keyword_count / $word_count) * 100, 2) : 0,
            'first_occurrence' => stripos($text, $keyword),
            'in_first_paragraph' => stripos($text, $keyword) < 200
        );
    }
}