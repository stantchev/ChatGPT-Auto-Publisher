<?php
/**
 * Scheduler Class for Automated Post Generation
 */

if (!defined('ABSPATH')) {
    exit;
}

class CGAP_Scheduler {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'cgap_scheduled_posts';
    }
    
    /**
     * Add scheduled post
     */
    public function add_scheduled_post($title, $keywords, $frequency, $settings = array()) {
        global $wpdb;
        
        $next_run = $this->calculate_next_run($frequency);
        
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'title' => sanitize_text_field($title),
                'keywords' => sanitize_text_field($keywords),
                'frequency' => sanitize_text_field($frequency),
                'next_run' => $next_run,
                'settings' => json_encode($settings),
                'status' => 'active',
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        return $result !== false ? $wpdb->insert_id : false;
    }
    
    /**
     * Get all scheduled posts
     */
    public function get_scheduled_posts($status = 'active') {
        global $wpdb;
        
        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE status = %s ORDER BY next_run ASC",
            $status
        );
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Update scheduled post
     */
    public function update_scheduled_post($id, $data) {
        global $wpdb;
        
        return $wpdb->update(
            $this->table_name,
            $data,
            array('id' => $id),
            null,
            array('%d')
        );
    }
    
    /**
     * Delete scheduled post
     */
    public function delete_scheduled_post($id) {
        global $wpdb;
        
        return $wpdb->delete(
            $this->table_name,
            array('id' => $id),
            array('%d')
        );
    }
    
    /**
     * Process scheduled posts
     */
    public function process_scheduled_posts() {
        global $wpdb;
        
        $current_time = current_time('mysql');
        
        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             WHERE status = 'active' 
             AND next_run <= %s 
             ORDER BY next_run ASC 
             LIMIT 5",
            $current_time
        );
        
        $scheduled_posts = $wpdb->get_results($sql);
        
        foreach ($scheduled_posts as $scheduled_post) {
            try {
                $this->generate_scheduled_post($scheduled_post);
                $this->update_next_run($scheduled_post);
            } catch (Exception $e) {
                error_log('CGAP Scheduled Post Error: ' . $e->getMessage());
                
                // Mark as failed after 3 attempts
                $this->increment_failure_count($scheduled_post->id);
            }
        }
    }
    
    /**
     * Generate a scheduled post
     */
    private function generate_scheduled_post($scheduled_post) {
        $settings = json_decode($scheduled_post->settings, true) ?: array();
        
        $generator = new CGAP_Post_Generator();
        
        $tone = $settings['tone'] ?? 'professional';
        $length = $settings['length'] ?? 'medium';
        $auto_publish = $settings['auto_publish'] ?? false;
        
        // Generate variations of the topic
        $topic_variations = $this->generate_topic_variations($scheduled_post->title, $scheduled_post->keywords);
        $selected_topic = $topic_variations[array_rand($topic_variations)];
        
        $result = $generator->generate_post(
            $selected_topic,
            $scheduled_post->keywords,
            $tone,
            $length,
            $auto_publish
        );
        
        // Add scheduled post metadata
        update_post_meta($result['post_id'], '_cgap_scheduled_post_id', $scheduled_post->id);
        update_post_meta($result['post_id'], '_cgap_generated_topic', $selected_topic);
        
        return $result;
    }
    
    /**
     * Generate topic variations
     */
    private function generate_topic_variations($base_title, $keywords) {
        $variations = array($base_title);
        
        if (!empty($keywords)) {
            $keyword_array = array_map('trim', explode(',', $keywords));
            
            foreach ($keyword_array as $keyword) {
                $variations[] = $base_title . ': ' . $keyword . ' Guide';
                $variations[] = 'How to Use ' . $keyword . ' for ' . $base_title;
                $variations[] = $keyword . ' Tips for ' . $base_title;
                $variations[] = 'Best ' . $keyword . ' Practices in ' . $base_title;
            }
        }
        
        // Add time-based variations
        $current_year = date('Y');
        $variations[] = $base_title . ' in ' . $current_year;
        $variations[] = $base_title . ': ' . $current_year . ' Update';
        $variations[] = 'Latest ' . $base_title . ' Trends';
        
        return array_unique($variations);
    }
    
    /**
     * Update next run time
     */
    private function update_next_run($scheduled_post) {
        global $wpdb;
        
        $next_run = $this->calculate_next_run($scheduled_post->frequency, $scheduled_post->next_run);
        
        $wpdb->update(
            $this->table_name,
            array(
                'next_run' => $next_run,
                'last_run' => current_time('mysql')
            ),
            array('id' => $scheduled_post->id),
            array('%s', '%s'),
            array('%d')
        );
    }
    
    /**
     * Calculate next run time
     */
    private function calculate_next_run($frequency, $from_time = null) {
        $base_time = $from_time ? strtotime($from_time) : time();
        
        switch ($frequency) {
            case 'hourly':
                return date('Y-m-d H:i:s', $base_time + HOUR_IN_SECONDS);
            
            case 'daily':
                return date('Y-m-d H:i:s', $base_time + DAY_IN_SECONDS);
            
            case 'weekly':
                return date('Y-m-d H:i:s', $base_time + WEEK_IN_SECONDS);
            
            case 'monthly':
                return date('Y-m-d H:i:s', strtotime('+1 month', $base_time));
            
            default:
                return date('Y-m-d H:i:s', $base_time + DAY_IN_SECONDS);
        }
    }
    
    /**
     * Increment failure count
     */
    private function increment_failure_count($id) {
        global $wpdb;
        
        $current_failures = $wpdb->get_var($wpdb->prepare(
            "SELECT failure_count FROM {$this->table_name} WHERE id = %d",
            $id
        )) ?: 0;
        
        $new_count = $current_failures + 1;
        $status = $new_count >= 3 ? 'failed' : 'active';
        
        $wpdb->update(
            $this->table_name,
            array(
                'failure_count' => $new_count,
                'status' => $status
            ),
            array('id' => $id),
            array('%d', '%s'),
            array('%d')
        );
    }
    
    /**
     * Get scheduled post statistics
     */
    public function get_statistics() {
        global $wpdb;
        
        $stats = array();
        
        $stats['total'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        $stats['active'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'active'");
        $stats['paused'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'paused'");
        $stats['failed'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'failed'");
        
        $stats['next_run'] = $wpdb->get_var(
            "SELECT next_run FROM {$this->table_name} 
             WHERE status = 'active' 
             ORDER BY next_run ASC 
             LIMIT 1"
        );
        
        return $stats;
    }
}