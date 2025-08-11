<?php
/**
 * SEO Plugin Integration Class
 * 
 * Handles integration with popular SEO plugins and provides
 * advanced content optimization features
 */

if (!defined('ABSPATH')) {
    exit;
}

class CGAP_SEO_Integration {
    
    private $supported_plugins = array();
    private $active_seo_plugin = null;
    
    public function __construct() {
        $this->init_supported_plugins();
        $this->detect_active_seo_plugin();
        $this->init_hooks();
    }
    
    /**
     * Initialize supported SEO plugins
     */
    private function init_supported_plugins() {
        $this->supported_plugins = array(
            'yoast' => array(
                'name' => 'Yoast SEO',
                'plugin_file' => 'wordpress-seo/wp-seo.php',
                'class' => 'WPSEO_Options',
                'meta_prefix' => '_yoast_wpseo_'
            ),
            'rankmath' => array(
                'name' => 'RankMath',
                'plugin_file' => 'seo-by-rankmath/rank-math.php',
                'class' => 'RankMath\\Helper',
                'meta_prefix' => 'rank_math_'
            ),
            'aioseo' => array(
                'name' => 'All in One SEO Pack',
                'plugin_file' => 'all-in-one-seo-pack/all_in_one_seo_pack.php',
                'class' => 'AIOSEO\\Plugin\\AIOSEO',
                'meta_prefix' => '_aioseo_'
            ),
            'seopress' => array(
                'name' => 'SEOPress',
                'plugin_file' => 'wp-seopress/seopress.php',
                'class' => 'SEOPress_Options',
                'meta_prefix' => '_seopress_'
            ),
            'seo_framework' => array(
                'name' => 'The SEO Framework',
                'plugin_file' => 'autodescription/autodescription.php',
                'class' => 'The_SEO_Framework\\Load',
                'meta_prefix' => '_genesis_'
            )
        );
    }
    
    /**
     * Detect active SEO plugin
     */
    private function detect_active_seo_plugin() {
        foreach ($this->supported_plugins as $key => $plugin) {
            if (is_plugin_active($plugin['plugin_file']) && class_exists($plugin['class'])) {
                $this->active_seo_plugin = $key;
                break;
            }
        }
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_cgap_analyze_content', array($this, 'analyze_content'));
        add_action('wp_ajax_cgap_get_seo_suggestions', array($this, 'get_seo_suggestions'));
        add_action('wp_ajax_cgap_translate_content', array($this, 'translate_content'));
    }
    
    /**
     * Get available SEO plugins
     */
    public function get_available_seo_plugins() {
        $available = array();
        
        foreach ($this->supported_plugins as $key => $plugin) {
            $available[$key] = array(
                'name' => $plugin['name'],
                'active' => is_plugin_active($plugin['plugin_file']),
                'installed' => file_exists(WP_PLUGIN_DIR . '/' . $plugin['plugin_file'])
            );
        }
        
        return $available;
    }
    
    /**
     * Get active SEO plugin
     */
    public function get_active_seo_plugin() {
        return $this->active_seo_plugin;
    }
    
    /**
     * Set SEO meta data for post
     */
    public function set_seo_meta($post_id, $meta_data) {
        if (!$this->active_seo_plugin) {
            return false;
        }
        
        $plugin = $this->supported_plugins[$this->active_seo_plugin];
        $prefix = $plugin['meta_prefix'];
        
        // Set meta description
        if (!empty($meta_data['meta_description'])) {
            update_post_meta($post_id, $prefix . 'metadesc', $meta_data['meta_description']);
        }
        
        // Set focus keyword
        if (!empty($meta_data['focus_keyword'])) {
            switch ($this->active_seo_plugin) {
                case 'yoast':
                    update_post_meta($post_id, $prefix . 'focuskw', $meta_data['focus_keyword']);
                    break;
                case 'rankmath':
                    update_post_meta($post_id, $prefix . 'focus_keyword', $meta_data['focus_keyword']);
                    break;
                case 'aioseo':
                    update_post_meta($post_id, $prefix . 'keyphrases', json_encode(array(
                        array('keyphrase' => $meta_data['focus_keyword'], 'score' => 100)
                    )));
                    break;
                default:
                    update_post_meta($post_id, $prefix . 'keywords', $meta_data['focus_keyword']);
            }
        }
        
        // Set SEO title
        if (!empty($meta_data['seo_title'])) {
            update_post_meta($post_id, $prefix . 'title', $meta_data['seo_title']);
        }
        
        return true;
    }
    
    /**
     * Analyze content quality
     */
    public function analyze_content() {
        check_ajax_referer('cgap_nonce', 'nonce');
        
        $content = sanitize_textarea_field($_POST['content'] ?? '');
        $title = sanitize_text_field($_POST['title'] ?? '');
        $keyword = sanitize_text_field($_POST['keyword'] ?? '');
        
        if (empty($content)) {
            wp_send_json_error(__('Content is required for analysis', 'chatgpt-auto-publisher'));
        }
        
        $analysis = array(
            'readability' => $this->calculate_readability_score($content),
            'seo_score' => $this->calculate_seo_score($content, $title, $keyword),
            'content_structure' => $this->analyze_content_structure($content),
            'keyword_analysis' => $this->analyze_keyword_usage($content, $keyword),
            'meta_analysis' => $this->analyze_meta_elements($title, $keyword),
            'overall_score' => 0
        );
        
        // Calculate overall score
        $analysis['overall_score'] = $this->calculate_overall_score($analysis);
        
        wp_send_json_success($analysis);
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
        $readability_weight = 0.3;
        $seo_weight = 0.7;
        
        $readability_score = $analysis['readability']['score'];
        $seo_score = $analysis['seo_score']['score'];
        
        return round(($readability_score * $readability_weight) + ($seo_score * $seo_weight));
    }
    
    /**
     * Get SEO suggestions
     */
    public function get_seo_suggestions() {
        check_ajax_referer('cgap_nonce', 'nonce');
        
        $content = sanitize_textarea_field($_POST['content'] ?? '');
        $title = sanitize_text_field($_POST['title'] ?? '');
        $keyword = sanitize_text_field($_POST['keyword'] ?? '');
        
        $suggestions = array();
        
        // Title suggestions
        if (strlen($title) < 30) {
            $suggestions[] = array(
                'type' => 'warning',
                'category' => 'Title',
                'message' => __('Title is too short. Aim for 30-60 characters.', 'chatgpt-auto-publisher')
            );
        } elseif (strlen($title) > 60) {
            $suggestions[] = array(
                'type' => 'warning',
                'category' => 'Title',
                'message' => __('Title is too long. Keep it under 60 characters.', 'chatgpt-auto-publisher')
            );
        }
        
        // Keyword suggestions
        if (!empty($keyword)) {
            if (stripos($title, $keyword) === false) {
                $suggestions[] = array(
                    'type' => 'error',
                    'category' => 'Keyword',
                    'message' => __('Focus keyword not found in title.', 'chatgpt-auto-publisher')
                );
            }
            
            $keyword_analysis = $this->analyze_keyword_usage($content, $keyword);
            if ($keyword_analysis['density'] < 0.5) {
                $suggestions[] = array(
                    'type' => 'warning',
                    'category' => 'Keyword',
                    'message' => __('Keyword density is too low. Consider adding the keyword more naturally.', 'chatgpt-auto-publisher')
                );
            } elseif ($keyword_analysis['density'] > 2.5) {
                $suggestions[] = array(
                    'type' => 'error',
                    'category' => 'Keyword',
                    'message' => __('Keyword density is too high. This may be seen as keyword stuffing.', 'chatgpt-auto-publisher')
                );
            }
        }
        
        // Content suggestions
        $word_count = str_word_count(strip_tags($content));
        if ($word_count < 300) {
            $suggestions[] = array(
                'type' => 'warning',
                'category' => 'Content',
                'message' => sprintf(__('Content is too short (%d words). Aim for at least 300 words.', 'chatgpt-auto-publisher'), $word_count)
            );
        }
        
        // Structure suggestions
        if (!preg_match('/<h[2-6][^>]*>/i', $content)) {
            $suggestions[] = array(
                'type' => 'warning',
                'category' => 'Structure',
                'message' => __('Add subheadings (H2, H3) to improve content structure.', 'chatgpt-auto-publisher')
            );
        }
        
        // Image suggestions
        if (!preg_match('/<img[^>]*>/i', $content)) {
            $suggestions[] = array(
                'type' => 'info',
                'category' => 'Images',
                'message' => __('Consider adding images to make content more engaging.', 'chatgpt-auto-publisher')
            );
        }
        
        wp_send_json_success($suggestions);
    }
    
    /**
     * Translate content
     */
    public function translate_content() {
        check_ajax_referer('cgap_nonce', 'nonce');
        
        $content = sanitize_textarea_field($_POST['content'] ?? '');
        $target_language = sanitize_text_field($_POST['target_language'] ?? '');
        $title = sanitize_text_field($_POST['title'] ?? '');
        
        if (empty($content) || empty($target_language)) {
            wp_send_json_error(__('Content and target language are required', 'chatgpt-auto-publisher'));
        }
        
        try {
            $openai = new CGAP_OpenAI_API();
            
            // Translate title
            $translated_title = '';
            if (!empty($title)) {
                $title_prompt = "Translate the following title to {$target_language}, maintaining SEO best practices: {$title}";
                $title_result = $openai->generate_content($title_prompt);
                $translated_title = trim($title_result['content']);
            }
            
            // Translate content
            $content_prompt = "Translate the following content to {$target_language}, maintaining the HTML structure, SEO optimization, and readability: {$content}";
            $content_result = $openai->generate_content($content_prompt);
            $translated_content = trim($content_result['content']);
            
            wp_send_json_success(array(
                'title' => $translated_title,
                'content' => $translated_content,
                'tokens_used' => ($title_result['tokens_used'] ?? 0) + $content_result['tokens_used']
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
}