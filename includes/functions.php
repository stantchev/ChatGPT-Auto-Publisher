<?php
/**
 * Helper Functions
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get plugin settings
 */
function cgap_get_settings() {
    $settings = new CGAP_Settings();
    return $settings->get_settings();
}

/**
 * Get specific setting
 */
function cgap_get_setting($key, $default = null) {
    $settings = new CGAP_Settings();
    return $settings->get_setting($key, $default);
}

/**
 * Check if API is configured
 */
function cgap_is_api_configured() {
    $settings = new CGAP_Settings();
    return $settings->is_api_configured();
}

/**
 * Log message
 */
function cgap_log($message, $level = 'info') {
    if (!cgap_get_setting('enable_logging')) {
        return;
    }
    
    $log_entry = array(
        'timestamp' => current_time('mysql'),
        'level' => $level,
        'message' => $message,
        'user_id' => get_current_user_id(),
        'ip_address' => cgap_get_client_ip()
    );
    
    error_log('CGAP [' . strtoupper($level) . ']: ' . $message);
    
    // Store in database for admin viewing
    global $wpdb;
    $table_name = $wpdb->prefix . 'cgap_logs';
    
    $wpdb->insert(
        $table_name,
        $log_entry,
        array('%s', '%s', '%s', '%d', '%s')
    );
}

/**
 * Get client IP address
 */
function cgap_get_client_ip() {
    $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
    
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Format cost for display
 */
function cgap_format_cost($cost) {
    return '$' . number_format($cost, 4);
}

/**
 * Get generation statistics
 */
function cgap_get_generation_stats($days = 30) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'cgap_generation_logs';
    $date_from = date('Y-m-d H:i:s', strtotime("-{$days} days"));
    
    $stats = array();
    
    // Total generations
    $stats['total_generations'] = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table_name} WHERE created_at >= %s",
        $date_from
    ));
    
    // Total tokens used
    $stats['total_tokens'] = $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(tokens_used) FROM {$table_name} WHERE created_at >= %s",
        $date_from
    )) ?: 0;
    
    // Total cost
    $stats['total_cost'] = $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(cost) FROM {$table_name} WHERE created_at >= %s",
        $date_from
    )) ?: 0;
    
    // Average tokens per generation
    $stats['avg_tokens'] = $stats['total_generations'] > 0 
        ? round($stats['total_tokens'] / $stats['total_generations']) 
        : 0;
    
    // Most used model
    $stats['popular_model'] = $wpdb->get_var($wpdb->prepare(
        "SELECT model FROM {$table_name} 
         WHERE created_at >= %s 
         GROUP BY model 
         ORDER BY COUNT(*) DESC 
         LIMIT 1",
        $date_from
    )) ?: 'N/A';
    
    return $stats;
}

/**
 * Clean old logs
 */
function cgap_clean_old_logs() {
    $retention_days = cgap_get_setting('log_retention_days', 30);
    $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
    
    global $wpdb;
    
    // Clean generation logs
    $logs_table = $wpdb->prefix . 'cgap_generation_logs';
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$logs_table} WHERE created_at < %s",
        $cutoff_date
    ));
    
    // Clean system logs if table exists
    $system_logs_table = $wpdb->prefix . 'cgap_logs';
    if ($wpdb->get_var("SHOW TABLES LIKE '{$system_logs_table}'") === $system_logs_table) {
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$system_logs_table} WHERE timestamp < %s",
            $cutoff_date
        ));
    }
}

/**
 * Schedule log cleanup
 */
function cgap_schedule_log_cleanup() {
    if (!wp_next_scheduled('cgap_cleanup_logs')) {
        wp_schedule_event(time(), 'daily', 'cgap_cleanup_logs');
    }
}

/**
 * Validate OpenAI API key format
 */
function cgap_validate_api_key($api_key) {
    // OpenAI API keys start with 'sk-' and are typically 51 characters long
    return preg_match('/^sk-[a-zA-Z0-9]{48}$/', $api_key);
}

/**
 * Get WordPress categories for dropdown
 */
function cgap_get_categories_dropdown() {
    $categories = get_categories(array(
        'hide_empty' => false,
        'orderby' => 'name',
        'order' => 'ASC'
    ));
    
    $options = array();
    foreach ($categories as $category) {
        $options[$category->term_id] = $category->name;
    }
    
    return $options;
}

/**
 * Get post statuses for dropdown
 */
function cgap_get_post_statuses() {
    return array(
        'draft' => __('Draft', 'chatgpt-auto-publisher'),
        'publish' => __('Published', 'chatgpt-auto-publisher'),
        'private' => __('Private', 'chatgpt-auto-publisher')
    );
}

/**
 * Format time ago
 */
function cgap_time_ago($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) {
        return __('Just now', 'chatgpt-auto-publisher');
    } elseif ($time < 3600) {
        $minutes = floor($time / 60);
        return sprintf(_n('%d minute ago', '%d minutes ago', $minutes, 'chatgpt-auto-publisher'), $minutes);
    } elseif ($time < 86400) {
        $hours = floor($time / 3600);
        return sprintf(_n('%d hour ago', '%d hours ago', $hours, 'chatgpt-auto-publisher'), $hours);
    } else {
        $days = floor($time / 86400);
        return sprintf(_n('%d day ago', '%d days ago', $days, 'chatgpt-auto-publisher'), $days);
    }
}

/**
 * Sanitize prompt input
 */
function cgap_sanitize_prompt($prompt) {
    // Remove potentially harmful content
    $prompt = wp_strip_all_tags($prompt);
    $prompt = sanitize_textarea_field($prompt);
    
    // Limit length
    $max_length = 2000;
    if (strlen($prompt) > $max_length) {
        $prompt = substr($prompt, 0, $max_length) . '...';
    }
    
    return $prompt;
}

/**
 * Check if user can generate content
 */
function cgap_user_can_generate() {
    return current_user_can('edit_posts') || current_user_can('manage_options');
}

/**
 * Get plugin version
 */
function cgap_get_version() {
    return CGAP_VERSION;
}

// Hook cleanup function
add_action('cgap_cleanup_logs', 'cgap_clean_old_logs');

// Schedule cleanup on plugin activation
register_activation_hook(CGAP_PLUGIN_FILE, 'cgap_schedule_log_cleanup');