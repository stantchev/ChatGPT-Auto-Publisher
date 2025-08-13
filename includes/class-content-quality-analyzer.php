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
        add_action('wp_ajax_cgap_analyze_content', array($this, 'analyze_content_ajax'));
    }
    
    /**
     * AJAX handler for content analysis
     */
    public function analyze_content_ajax() {
        check_ajax_referer('cgap_analyze_content', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions', 'chatgpt-auto-publisher'));
        }
        
        $content = sanitize_textarea_field($_POST['content'] ?? '');
        $title = sanitize_text_field($_POST['title'] ?? '');
        $keyword = sanitize_text_field($_POST['keyword'] ?? '');
        
        if (empty($content) && empty($title)) {
            wp_send_json_error(__('Content or title is required for analysis', 'chatgpt-auto-publisher'));
        }
        
        try {
            $analysis = $this->perform_content_analysis($content, $title, $keyword);
            wp_send_json_success($analysis);
        } catch (Exception $e) {
            cgap_log('Content Analysis Error: ' . $e->getMessage(), 'error');
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Perform comprehensive content analysis
     */
    public function perform_content_analysis($content, $title = '', $keyword = '') {
        $analysis = array(
            'readability' => $this->calculate_readability_score($content),
            'seo_score' => $this->calculate_seo_score($content, $title, $keyword),
            'content_structure' => $this->analyze_content_structure($content),
            'keyword_analysis' => $this->analyze_keyword_usage($content, $keyword),
            'meta_analysis' => $this->analyze_meta_elements($title, $keyword),
            'aio_compliance' => $this->check_aio_compliance($content, $title, $keyword),
            'overall_score' => 0
        );
        
        // Calculate overall score
        $analysis['overall_score'] = $this->calculate_overall_score($analysis);
        
        return $analysis;
    }
    
    /**
     * Check AIO (AI-Optimized) compliance
     */
    private function check_aio_compliance($content, $title, $keyword) {
        $compliance = array(
            'structure_readability' => $this->check_structure_compliance($content),
            'semantic_keywords' => $this->check_semantic_compliance($content, $keyword),
            'technical_elements' => $this->check_technical_compliance($content, $title),
            'topic_coverage' => $this->check_topic_coverage($content),
            'ai_agent_optimization' => $this->check_ai_optimization($content),
            'performance_standards' => $this->check_performance_standards($content),
            'authority_freshness' => $this->check_authority_indicators($content),
            'answer_engine_optimization' => $this->check_aeo_compliance($content)
        );
        
        // Calculate overall AIO score
        $total_score = 0;
        $category_count = 0;
        
        foreach ($compliance as $category => $data) {
            if (isset($data['score'])) {
                $total_score += $data['score'];
                $category_count++;
            }
        }
        
        $compliance['overall_aio_score'] = $category_count > 0 ? round($total_score / $category_count) : 0;
        
        return $compliance;
    }
    
    /**
     * Check structure and readability compliance
     */
    private function check_structure_compliance($content) {
        $score = 0;
        $issues = array();
        $suggestions = array();
        
        // Check for question-based headings
        $question_headings = preg_match_all('/<h[2-3][^>]*>[^<]*\?[^<]*<\/h[2-3]>/i', $content);
        if ($question_headings > 0) {
            $score += 20;
        } else {
            $issues[] = 'No question-based headings found';
            $suggestions[] = 'Add H2/H3 headings that pose questions readers might ask';
        }
        
        // Check paragraph length
        $paragraphs = explode('</p>', $content);
        $long_paragraphs = 0;
        foreach ($paragraphs as $paragraph) {
            $sentences = preg_split('/[.!?]+/', strip_tags($paragraph));
            if (count($sentences) > 5) {
                $long_paragraphs++;
            }
        }
        
        if ($long_paragraphs < count($paragraphs) * 0.3) {
            $score += 20;
        } else {
            $issues[] = 'Paragraphs are too long';
            $suggestions[] = 'Keep paragraphs to 2-4 sentences for better readability';
        }
        
        // Check for lists
        $lists = preg_match_all('/<(ul|ol)[^>]*>/i', $content);
        if ($lists > 0) {
            $score += 15;
        } else {
            $issues[] = 'No bullet points or numbered lists found';
            $suggestions[] = 'Add bullet points or numbered lists to break up content';
        }
        
        return array(
            'score' => min(100, $score),
            'issues' => $issues,
            'suggestions' => $suggestions
        );
    }
    
    /**
     * Check semantic keyword compliance
     */
    private function check_semantic_compliance($content, $keyword) {
        $score = 0;
        $issues = array();
        $suggestions = array();
        
        if (empty($keyword)) {
            return array(
                'score' => 0,
                'issues' => array('No focus keyword provided'),
                'suggestions' => array('Add a focus keyword for semantic analysis')
            );
        }
        
        $text = strip_tags($content);
        $first_paragraph = substr($text, 0, 300);
        
        // Check keyword in first paragraph
        if (stripos($first_paragraph, $keyword) !== false) {
            $score += 25;
        } else {
            $issues[] = 'Focus keyword not found in opening paragraph';
            $suggestions[] = 'Include the focus keyword in the first paragraph';
        }
        
        // Check keyword frequency
        $keyword_count = substr_count(strtolower($text), strtolower($keyword));
        $word_count = str_word_count($text);
        $density = $word_count > 0 ? ($keyword_count / $word_count) * 100 : 0;
        
        if ($keyword_count >= 4 && $keyword_count <= 8 && $density <= 2.5) {
            $score += 25;
        } else {
            $issues[] = 'Keyword frequency not optimal';
            $suggestions[] = 'Use the focus keyword 4-5 times naturally throughout the content';
        }
        
        return array(
            'score' => min(100, $score),
            'issues' => $issues,
            'suggestions' => $suggestions,
            'keyword_count' => $keyword_count,
            'density' => round($density, 2)
        );
    }
    
    /**
     * Check technical elements compliance
     */
    private function check_technical_compliance($content, $title) {
        $score = 0;
        $issues = array();
        $suggestions = array();
        
        // Check title length
        if (strlen($title) >= 30 && strlen($title) <= 60) {
            $score += 20;
        } else {
            $issues[] = 'Title length not optimal';
            $suggestions[] = 'Keep title between 30-60 characters';
        }
        
        // Check for images with alt text potential
        $images = preg_match_all('/<img[^>]*>/i', $content);
        if ($images > 0) {
            $score += 15;
            $suggestions[] = 'Ensure all images have descriptive alt text';
        } else {
            $suggestions[] = 'Consider adding relevant images with descriptive alt text';
        }
        
        // Check for structured data potential
        $has_faq = preg_match('/<h[2-6][^>]*>[^<]*\?[^<]*<\/h[2-6]>/i', $content);
        $has_steps = preg_match('/step\s+\d+/i', $content);
        
        if ($has_faq || $has_steps) {
            $score += 15;
            $suggestions[] = 'Content structure supports FAQ or HowTo schema markup';
        }
        
        return array(
            'score' => min(100, $score),
            'issues' => $issues,
            'suggestions' => $suggestions
        );
    }
    
    /**
     * Check topic coverage compliance
     */
    private function check_topic_coverage($content) {
        $score = 0;
        $issues = array();
        $suggestions = array();
        
        $text = strtolower(strip_tags($content));
        
        // Check for definitions
        $has_definitions = preg_match('/\b(is|are|means|refers to|defined as)\b/', $text);
        if ($has_definitions) {
            $score += 15;
        } else {
            $suggestions[] = 'Include clear definitions of key concepts';
        }
        
        // Check for benefits/advantages
        $has_benefits = preg_match('/\b(benefit|advantage|pro|positive|good)\b/', $text);
        if ($has_benefits) {
            $score += 15;
        } else {
            $suggestions[] = 'Discuss benefits and advantages';
        }
        
        // Check for examples
        $has_examples = preg_match('/\b(example|instance|case|such as|for example)\b/', $text);
        if ($has_examples) {
            $score += 15;
        } else {
            $suggestions[] = 'Add concrete examples to illustrate points';
        }
        
        // Check for statistics/data
        $has_data = preg_match('/\b(\d+%|\d+\s*(percent|million|billion|thousand))\b/', $text);
        if ($has_data) {
            $score += 10;
        } else {
            $suggestions[] = 'Include relevant statistics or data points';
        }
        
        return array(
            'score' => min(100, $score),
            'issues' => $issues,
            'suggestions' => $suggestions
        );
    }
    
    /**
     * Check AI agent optimization
     */
    private function check_ai_optimization($content) {
        $score = 0;
        $issues = array();
        $suggestions = array();
        
        // Check for step-by-step instructions
        $has_steps = preg_match_all('/\b(step\s+\d+|first|second|third|next|then|finally)\b/i', $content);
        if ($has_steps >= 3) {
            $score += 25;
        } else {
            $suggestions[] = 'Structure content with clear step-by-step instructions where applicable';
        }
        
        // Check for clear section structure
        $headings = preg_match_all('/<h[2-6][^>]*>/i', $content);
        if ($headings >= 3) {
            $score += 20;
        } else {
            $suggestions[] = 'Use more subheadings to create clear content sections';
        }
        
        // Check for concise language (avoid marketing fluff)
        $fluff_words = preg_match_all('/\b(amazing|incredible|revolutionary|game-changing|cutting-edge)\b/i', $content);
        if ($fluff_words < 3) {
            $score += 15;
        } else {
            $issues[] = 'Too much marketing language detected';
            $suggestions[] = 'Use precise, factual language instead of marketing terms';
        }
        
        return array(
            'score' => min(100, $score),
            'issues' => $issues,
            'suggestions' => $suggestions
        );
    }
    
    /**
     * Check performance standards
     */
    private function check_performance_standards($content) {
        $score = 50; // Base score for HTML content
        $issues = array();
        $suggestions = array();
        
        // Check content length (not too heavy)
        $word_count = str_word_count(strip_tags($content));
        if ($word_count >= 300 && $word_count <= 2000) {
            $score += 25;
        } else if ($word_count > 2000) {
            $issues[] = 'Content might be too long for optimal performance';
            $suggestions[] = 'Consider breaking long content into multiple pages';
        }
        
        // Check for mobile-friendly structure
        $short_paragraphs = 0;
        $paragraphs = explode('</p>', $content);
        foreach ($paragraphs as $paragraph) {
            if (str_word_count(strip_tags($paragraph)) <= 50) {
                $short_paragraphs++;
            }
        }
        
        if ($short_paragraphs >= count($paragraphs) * 0.7) {
            $score += 25;
        } else {
            $suggestions[] = 'Keep paragraphs short for better mobile readability';
        }
        
        return array(
            'score' => min(100, $score),
            'issues' => $issues,
            'suggestions' => $suggestions
        );
    }
    
    /**
     * Check authority and freshness indicators
     */
    private function check_authority_indicators($content) {
        $score = 0;
        $issues = array();
        $suggestions = array();
        
        // Check for source citations
        $has_sources = preg_match('/\b(according to|source|study|research|report)\b/i', $content);
        if ($has_sources) {
            $score += 30;
        } else {
            $suggestions[] = 'Include authoritative sources and citations';
        }
        
        // Check for current year references
        $current_year = date('Y');
        $has_current_info = preg_match('/\b' . $current_year . '\b/', $content);
        if ($has_current_info) {
            $score += 20;
        } else {
            $suggestions[] = 'Include current year information to show freshness';
        }
        
        return array(
            'score' => min(100, $score),
            'issues' => $issues,
            'suggestions' => $suggestions
        );
    }
    
    /**
     * Check Answer Engine Optimization (AEO) compliance
     */
    private function check_aeo_compliance($content) {
        $score = 0;
        $issues = array();
        $suggestions = array();
        
        // Check for FAQ sections
        $faq_headings = preg_match_all('/<h[2-6][^>]*>[^<]*\?[^<]*<\/h[2-6]>/i', $content);
        if ($faq_headings >= 2) {
            $score += 30;
        } else {
            $suggestions[] = 'Add FAQ section with common questions and concise answers';
        }
        
        // Check for direct answers after questions
        $question_answer_pairs = preg_match_all('/<h[2-6][^>]*>[^<]*\?[^<]*<\/h[2-6]>\s*<p[^>]*>[^<]{20,100}[.!]/', $content);
        if ($question_answer_pairs > 0) {
            $score += 25;
        } else {
            $suggestions[] = 'Provide direct, concise answers immediately after question headings';
        }
        
        // Check for quotable content (short, complete sentences)
        $short_sentences = preg_match_all('/[.!?]\s+[A-Z][^.!?]{10,80}[.!?]/', strip_tags($content));
        if ($short_sentences >= 5) {
            $score += 20;
        } else {
            $suggestions[] = 'Include more short, quotable sentences that AI can extract';
        }
        
        return array(
            'score' => min(100, $score),
            'issues' => $issues,
            'suggestions' => $suggestions
        );
    }
    /**
     * Get AI-powered content suggestions
     */
    public function get_content_suggestions() {
        check_ajax_referer('cgap_get_content_suggestions', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions', 'chatgpt-auto-publisher'));
        }
        
        $content = sanitize_textarea_field($_POST['content'] ?? '');
        $keyword = sanitize_text_field($_POST['keyword'] ?? '');
        $title = sanitize_text_field($_POST['title'] ?? '');
        
        if (empty($content)) {
            wp_send_json_error(__('Content is required for analysis', 'chatgpt-auto-publisher'));
        }
        
        try {
            $openai = new CGAP_OpenAI_API();
            
            $prompt = "Analyze the following content and provide specific SEO, AIO (AI-Optimized), and readability improvement suggestions:\n\n";
            $prompt .= "Title: {$title}\n";
            $prompt .= "Focus Keyword: {$keyword}\n";
            $prompt .= "Content: {$content}\n\n";
            $prompt .= "Provide actionable suggestions in the following categories:\n";
            $prompt .= "1. SEO Optimization\n";
            $prompt .= "2. Content Structure\n";
            $prompt .= "3. Readability Improvements\n";
            $prompt .= "4. Keyword Optimization\n";
            $prompt .= "5. AI Search Engine Optimization (AIO)\n";
            $prompt .= "6. Answer Engine Optimization (AEO)\n\n";
            $prompt .= "Focus on 2025 AI search standards. Format as clear, actionable bullet points.";
            
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
            cgap_log('Content Suggestions Error: ' . $e->getMessage(), 'error');
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
            'AI Search Engine Optimization (AIO)' => array(),
            'Answer Engine Optimization (AEO)' => array()
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
     * Calculate readability score using Flesch-Kincaid
     */
    private function calculate_readability_score($content) {
        $text = strip_tags($content);
        $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $words = str_word_count($text);
        $syllables = $this->count_syllables($text);
        
        if ($words == 0 || count($sentences) == 0) {
            return array('score' => 0, 'level' => 'Unknown');
        }
        
        $avg_sentence_length = $words / count($sentences);
        $avg_syllables_per_word = $syllables / $words;
        
        // Flesch Reading Ease Score
        $flesch_score = 206.835 - (1.015 * $avg_sentence_length) - (84.6 * $avg_syllables_per_word);
        $flesch_score = max(0, min(100, $flesch_score));
        
        $level = $this->get_readability_level($flesch_score);
        
        return array(
            'score' => round($flesch_score, 1),
            'level' => $level,
            'sentences' => count($sentences),
            'words' => $words,
            'avg_sentence_length' => round($avg_sentence_length, 1)
        );
    }
    
    /**
     * Count syllables in text
     */
    private function count_syllables($text) {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z]/', ' ', $text);
        $words = array_filter(explode(' ', $text));
        
        $syllable_count = 0;
        foreach ($words as $word) {
            $syllable_count += $this->count_word_syllables($word);
        }
        
        return max(1, $syllable_count);
    }
    
    /**
     * Count syllables in a single word
     */
    private function count_word_syllables($word) {
        $word = strtolower($word);
        $vowels = 'aeiouy';
        $syllables = 0;
        $prev_was_vowel = false;
        
        for ($i = 0; $i < strlen($word); $i++) {
            $is_vowel = strpos($vowels, $word[$i]) !== false;
            if ($is_vowel && !$prev_was_vowel) {
                $syllables++;
            }
            $prev_was_vowel = $is_vowel;
        }
        
        // Handle silent 'e'
        if (substr($word, -1) === 'e' && $syllables > 1) {
            $syllables--;
        }
        
        return max(1, $syllables);
    }
    
    /**
     * Get readability level from score
     */
    private function get_readability_level($score) {
        if ($score >= 90) return 'Very Easy';
        if ($score >= 80) return 'Easy';
        if ($score >= 70) return 'Fairly Easy';
        if ($score >= 60) return 'Standard';
        if ($score >= 50) return 'Fairly Difficult';
        if ($score >= 30) return 'Difficult';
        return 'Very Difficult';
    }
    
    /**
     * Calculate SEO score
     */
    private function calculate_seo_score($content, $title, $keyword) {
        $score = 0;
        $max_score = 100;
        $factors = array();
        
        // Title optimization (20 points)
        if (!empty($title)) {
            $title_score = 0;
            if (strlen($title) >= 30 && strlen($title) <= 60) {
                $title_score += 10;
            }
            if (!empty($keyword) && stripos($title, $keyword) !== false) {
                $title_score += 10;
            }
            $factors['title'] = $title_score;
            $score += $title_score;
        }
        
        // Content length (15 points)
        $word_count = str_word_count(strip_tags($content));
        $length_score = 0;
        if ($word_count >= 300) {
            $length_score = min(15, ($word_count / 300) * 15);
        }
        $factors['content_length'] = round($length_score);
        $score += $length_score;
        
        // Keyword usage (25 points)
        if (!empty($keyword)) {
            $keyword_score = $this->calculate_keyword_score($content, $keyword);
            $factors['keyword_usage'] = $keyword_score;
            $score += $keyword_score;
        }
        
        // Content structure (20 points)
        $structure_score = $this->calculate_structure_score($content);
        $factors['content_structure'] = $structure_score;
        $score += $structure_score;
        
        // Links (10 points)
        $link_score = $this->calculate_link_score($content);
        $factors['links'] = $link_score;
        $score += $link_score;
        
        // Images (10 points)
        $image_score = $this->calculate_image_score($content);
        $factors['images'] = $image_score;
        $score += $image_score;
        
        return array(
            'score' => min(100, round($score)),
            'factors' => $factors
        );
    }
    
    /**
     * Calculate keyword score
     */
    private function calculate_keyword_score($content, $keyword) {
        $text = strip_tags($content);
        $word_count = str_word_count($text);
        $keyword_count = substr_count(strtolower($text), strtolower($keyword));
        
        if ($word_count == 0) return 0;
        
        $density = ($keyword_count / $word_count) * 100;
        
        // Optimal density is 0.5% - 2.5%
        if ($density >= 0.5 && $density <= 2.5) {
            return 25;
        } elseif ($density > 0 && $density < 0.5) {
            return round(($density / 0.5) * 15);
        } elseif ($density > 2.5 && $density <= 4) {
            return round(25 - (($density - 2.5) / 1.5) * 10);
        }
        
        return 0;
    }
    
    /**
     * Calculate structure score
     */
    private function calculate_structure_score($content) {
        $score = 0;
        
        // Check for headings
        if (preg_match_all('/<h[1-6][^>]*>/i', $content)) {
            $score += 10;
        }
        
        // Check for paragraphs
        if (preg_match_all('/<p[^>]*>/i', $content)) {
            $score += 5;
        }
        
        // Check for lists
        if (preg_match('/<(ul|ol)[^>]*>/i', $content)) {
            $score += 5;
        }
        
        return $score;
    }
    
    /**
     * Calculate link score
     */
    private function calculate_link_score($content) {
        $internal_links = preg_match_all('/<a[^>]*href=["\'][^"\']*' . preg_quote(home_url(), '/') . '[^"\']*["\'][^>]*>/i', $content);
        $external_links = preg_match_all('/<a[^>]*href=["\']https?:\/\/(?!' . preg_quote(parse_url(home_url(), PHP_URL_HOST), '/') . ')[^"\']*["\'][^>]*>/i', $content);
        
        $score = 0;
        if ($internal_links > 0) $score += 5;
        if ($external_links > 0) $score += 5;
        
        return $score;
    }
    
    /**
     * Calculate image score
     */
    private function calculate_image_score($content) {
        $images = preg_match_all('/<img[^>]*>/i', $content);
        $images_with_alt = preg_match_all('/<img[^>]*alt=["\'][^"\']*["\'][^>]*>/i', $content);
        
        $score = 0;
        if ($images > 0) $score += 5;
        if ($images_with_alt > 0) $score += 5;
        
        return $score;
    }
    
    /**
     * Analyze content structure
     */
    private function analyze_content_structure($content) {
        return array(
            'headings' => array(
                'h1' => preg_match_all('/<h1[^>]*>/i', $content),
                'h2' => preg_match_all('/<h2[^>]*>/i', $content),
                'h3' => preg_match_all('/<h3[^>]*>/i', $content),
                'h4' => preg_match_all('/<h4[^>]*>/i', $content),
                'h5' => preg_match_all('/<h5[^>]*>/i', $content),
                'h6' => preg_match_all('/<h6[^>]*>/i', $content)
            ),
            'paragraphs' => preg_match_all('/<p[^>]*>/i', $content),
            'lists' => preg_match_all('/<(ul|ol)[^>]*>/i', $content),
            'images' => preg_match_all('/<img[^>]*>/i', $content),
            'links' => preg_match_all('/<a[^>]*>/i', $content)
        );
    }
    
    /**
     * Analyze keyword usage
     */
    private function analyze_keyword_usage($content, $keyword) {
        if (empty($keyword)) {
            return array('density' => 0, 'count' => 0, 'positions' => array());
        }
        
        $text = strip_tags($content);
        $word_count = str_word_count($text);
        $keyword_count = substr_count(strtolower($text), strtolower($keyword));
        $density = $word_count > 0 ? ($keyword_count / $word_count) * 100 : 0;
        
        // Find keyword positions
        $positions = array();
        $offset = 0;
        while (($pos = stripos($text, $keyword, $offset)) !== false) {
            $positions[] = $pos;
            $offset = $pos + strlen($keyword);
        }
        
        return array(
            'density' => round($density, 2),
            'count' => $keyword_count,
            'positions' => $positions,
            'optimal' => $density >= 0.5 && $density <= 2.5
        );
    }
    
    /**
     * Analyze meta elements
     */
    private function analyze_meta_elements($title, $keyword) {
        $analysis = array(
            'title_length' => strlen($title),
            'title_optimal' => strlen($title) >= 30 && strlen($title) <= 60,
            'keyword_in_title' => !empty($keyword) && stripos($title, $keyword) !== false
        );
        
        return $analysis;
    }
    
    /**
     * Calculate overall score
     */
    private function calculate_overall_score($analysis) {
        $readability_weight = 0.25;
        $seo_weight = 0.35;
        $aio_weight = 0.40; // Higher weight for AIO compliance
        
        $readability_score = $analysis['readability']['score'];
        $seo_score = $analysis['seo_score']['score'];
        $aio_score = isset($analysis['aio_compliance']['overall_aio_score']) ? $analysis['aio_compliance']['overall_aio_score'] : 0;
        
        return round(($readability_score * $readability_weight) + ($seo_score * $seo_weight) + ($aio_score * $aio_weight));
    }
    /**
     * Analyze competitors
     */
    public function analyze_competitors() {
        check_ajax_referer('cgap_analyze_competitors', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions', 'chatgpt-auto-publisher'));
        }
        
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
            cgap_log('Competitor Analysis Error: ' . $e->getMessage(), 'error');
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Check content gaps
     */
    public function check_content_gaps() {
        check_ajax_referer('cgap_check_content_gaps', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions', 'chatgpt-auto-publisher'));
        }
        
        $content = sanitize_textarea_field($_POST['content'] ?? '');
        $keyword = sanitize_text_field($_POST['keyword'] ?? '');
        
        if (empty($content) || empty($keyword)) {
            wp_send_json_error(__('Content and keyword are required', 'chatgpt-auto-publisher'));
        }
        
        try {
            $openai = new CGAP_OpenAI_API();
            
            $prompt = "Analyze the following content for the keyword '{$keyword}' and identify content gaps for 2025 AI search optimization:\n\n";
            $prompt .= "Content: {$content}\n\n";
            $prompt .= "Identify:\n";
            $prompt .= "1. Missing subtopics that should be covered\n";
            $prompt .= "2. Questions readers might have that aren't answered\n";
            $prompt .= "3. Related keywords and topics to include\n";
            $prompt .= "4. Additional sections that would improve comprehensiveness\n";
            $prompt .= "5. AI search engine optimization opportunities\n";
            $prompt .= "6. Answer Engine Optimization (AEO) improvements\n\n";
            $prompt .= "Focus on 2025 AI search standards and provide actionable recommendations.";
            
            $result = $openai->generate_content($prompt);
            
            wp_send_json_success(array(
                'gaps' => $result['content'],
                'tokens_used' => $result['tokens_used']
            ));
            
        } catch (Exception $e) {
            cgap_log('Content Gaps Error: ' . $e->getMessage(), 'error');
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