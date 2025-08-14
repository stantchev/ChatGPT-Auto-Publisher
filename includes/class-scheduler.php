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
        
        // Ensure cron hook is registered
        add_action('init', array($this, 'ensure_cron_scheduled'));
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
     * Ensure WordPress cron is properly scheduled
     */
    public function ensure_cron_scheduled() {
        if (!wp_next_scheduled('cgap_scheduled_post_generation')) {
            wp_schedule_event(time(), 'hourly', 'cgap_scheduled_post_generation');
            cgap_log('Scheduled cron event registered', 'info');
        }
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
        
        cgap_log('Processing scheduled posts - started', 'info');
        
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
        
        if (empty($scheduled_posts)) {
            cgap_log('No scheduled posts to process', 'info');
            return;
        }
        
        cgap_log('Found ' . count($scheduled_posts) . ' scheduled posts to process', 'info');
        
        foreach ($scheduled_posts as $scheduled_post) {
            try {
                cgap_log('Processing scheduled post ID: ' . $scheduled_post->id, 'info');
                $this->generate_scheduled_post($scheduled_post);
                $this->update_next_run($scheduled_post);
                cgap_log('Successfully processed scheduled post ID: ' . $scheduled_post->id, 'info');
            } catch (Exception $e) {
                cgap_log('Scheduled Post Error (ID: ' . $scheduled_post->id . '): ' . $e->getMessage(), 'error');
                
                // Mark as failed after 3 attempts
                $this->increment_failure_count($scheduled_post->id);
            }
        }
        
        cgap_log('Processing scheduled posts - completed', 'info');
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
        
        cgap_log('Generated scheduled post: ' . $result['post_id'] . ' for schedule ID: ' . $scheduled_post->id, 'info');
        
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
        
        // First, ensure the failure_count column exists
        $this->ensure_failure_count_column();
        
        $current_failures = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(failure_count, 0) FROM {$this->table_name} WHERE id = %d",
            $id
        ));
        
        $new_count = $current_failures + 1;
        $status = $new_count >= 3 ? 'failed' : 'active';
        
        cgap_log("Incrementing failure count for schedule ID {$id}: {$current_failures} -> {$new_count}", 'warning');
        
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
     * Ensure failure_count column exists in database
     */
    private function ensure_failure_count_column() {
        global $wpdb;
        
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM {$this->table_name} LIKE %s",
            'failure_count'
        ));
        
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE {$this->table_name} ADD COLUMN failure_count INT DEFAULT 0");
            cgap_log('Added failure_count column to scheduled posts table', 'info');
        }
    }
    
    /**
     * Toggle scheduled post status (pause/resume)
     */
    public function toggle_scheduled_post($id) {
        global $wpdb;
        
        $current_status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$this->table_name} WHERE id = %d",
            $id
        ));
        
        if (!$current_status) {
            throw new Exception(__('Scheduled post not found', 'chatgpt-auto-publisher'));
        }
        
        $new_status = ($current_status === 'active') ? 'paused' : 'active';
        
        $result = $wpdb->update(
            $this->table_name,
            array('status' => $new_status),
            array('id' => $id),
            array('%s'),
            array('%d')
        );
        
        if ($result === false) {
            throw new Exception(__('Failed to update scheduled post status', 'chatgpt-auto-publisher'));
        }
        
        cgap_log("Toggled scheduled post {$id} status: {$current_status} -> {$new_status}", 'info');
        
        return array(
            'new_status' => $new_status,
            'message' => sprintf(
                __('Schedule %s successfully', 'chatgpt-auto-publisher'),
                $new_status === 'active' ? 'resumed' : 'paused'
            )
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
    
    /**
     * Get detailed schedule information
     */
    public function get_schedule_details($id) {
        global $wpdb;
        
        $schedule = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $id
        ));
        
        if (!$schedule) {
            return null;
        }
        
        // Get generated posts count
        $generated_posts = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p 
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
             WHERE pm.meta_key = '_cgap_scheduled_post_id' 
             AND pm.meta_value = %d",
            $id
        ));
        
        $schedule->generated_posts_count = (int) $generated_posts;
        
        return $schedule;
    }
}