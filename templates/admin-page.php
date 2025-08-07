<?php
/**
 * Main Admin Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$settings = cgap_get_settings();
$is_configured = cgap_is_api_configured();
$stats = cgap_get_generation_stats();
?>

<div class="wrap cgap-admin">
    <h1><?php _e('ChatGPT Auto Publisher', 'chatgpt-auto-publisher'); ?></h1>
    
    <?php if (!$is_configured): ?>
        <div class="notice notice-warning">
            <p>
                <?php _e('Please configure your OpenAI API key in the', 'chatgpt-auto-publisher'); ?>
                <a href="<?php echo admin_url('admin.php?page=cgap-settings'); ?>"><?php _e('Settings', 'chatgpt-auto-publisher'); ?></a>
                <?php _e('page to start generating content.', 'chatgpt-auto-publisher'); ?>
            </p>
        </div>
    <?php endif; ?>
    
    <div class="cgap-dashboard">
        <!-- Statistics Cards -->
        <div class="cgap-stats-grid">
            <div class="cgap-stat-card">
                <div class="cgap-stat-icon">📝</div>
                <div class="cgap-stat-content">
                    <h3><?php echo number_format($stats['total_generations']); ?></h3>
                    <p><?php _e('Total Generations', 'chatgpt-auto-publisher'); ?></p>
                </div>
            </div>
            
            <div class="cgap-stat-card">
                <div class="cgap-stat-icon">🎯</div>
                <div class="cgap-stat-content">
                    <h3><?php echo number_format($stats['total_tokens']); ?></h3>
                    <p><?php _e('Tokens Used', 'chatgpt-auto-publisher'); ?></p>
                </div>
            </div>
            
            <div class="cgap-stat-card">
                <div class="cgap-stat-icon">💰</div>
                <div class="cgap-stat-content">
                    <h3><?php echo cgap_format_cost($stats['total_cost']); ?></h3>
                    <p><?php _e('Total Cost', 'chatgpt-auto-publisher'); ?></p>
                </div>
            </div>
            
            <div class="cgap-stat-card">
                <div class="cgap-stat-icon">⚡</div>
                <div class="cgap-stat-content">
                    <h3><?php echo $stats['popular_model']; ?></h3>
                    <p><?php _e('Popular Model', 'chatgpt-auto-publisher'); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Content Generation Form -->
        <div class="cgap-main-content">
            <div class="cgap-card">
                <h2><?php _e('Generate New Content', 'chatgpt-auto-publisher'); ?></h2>
                
                <form id="cgap-generate-form" class="cgap-form">
                    <?php wp_nonce_field('cgap_nonce', 'cgap_nonce'); ?>
                    
                    <div class="cgap-form-row">
                        <label for="topic"><?php _e('Topic/Title', 'chatgpt-auto-publisher'); ?> *</label>
                        <input type="text" id="topic" name="topic" required 
                               placeholder="<?php _e('e.g., Digital Marketing Strategies for 2024', 'chatgpt-auto-publisher'); ?>">
                    </div>
                    
                    <div class="cgap-form-row">
                        <label for="keywords"><?php _e('Keywords (comma-separated)', 'chatgpt-auto-publisher'); ?></label>
                        <input type="text" id="keywords" name="keywords" 
                               placeholder="<?php _e('e.g., SEO, marketing, digital strategy', 'chatgpt-auto-publisher'); ?>">
                    </div>
                    
                    <div class="cgap-form-row cgap-form-grid">
                        <div>
                            <label for="tone"><?php _e('Tone', 'chatgpt-auto-publisher'); ?></label>
                            <select id="tone" name="tone">
                                <option value="professional" <?php selected($settings['default_tone'], 'professional'); ?>><?php _e('Professional', 'chatgpt-auto-publisher'); ?></option>
                                <option value="casual" <?php selected($settings['default_tone'], 'casual'); ?>><?php _e('Casual', 'chatgpt-auto-publisher'); ?></option>
                                <option value="technical" <?php selected($settings['default_tone'], 'technical'); ?>><?php _e('Technical', 'chatgpt-auto-publisher'); ?></option>
                                <option value="friendly" <?php selected($settings['default_tone'], 'friendly'); ?>><?php _e('Friendly', 'chatgpt-auto-publisher'); ?></option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="length"><?php _e('Length', 'chatgpt-auto-publisher'); ?></label>
                            <select id="length" name="length">
                                <option value="short" <?php selected($settings['default_length'], 'short'); ?>><?php _e('Short (400 words)', 'chatgpt-auto-publisher'); ?></option>
                                <option value="medium" <?php selected($settings['default_length'], 'medium'); ?>><?php _e('Medium (800 words)', 'chatgpt-auto-publisher'); ?></option>
                                <option value="long" <?php selected($settings['default_length'], 'long'); ?>><?php _e('Long (1500 words)', 'chatgpt-auto-publisher'); ?></option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="cgap-form-row">
                        <label class="cgap-checkbox">
                            <input type="checkbox" id="auto_publish" name="auto_publish" <?php checked($settings['auto_publish']); ?>>
                            <?php _e('Publish immediately (otherwise save as draft)', 'chatgpt-auto-publisher'); ?>
                        </label>
                    </div>
                    
                    <div class="cgap-form-actions">
                        <button type="submit" class="button button-primary cgap-generate-btn" <?php disabled(!$is_configured); ?>>
                            <span class="cgap-btn-text"><?php _e('Generate Content', 'chatgpt-auto-publisher'); ?></span>
                            <span class="cgap-btn-loading" style="display: none;"><?php _e('Generating...', 'chatgpt-auto-publisher'); ?></span>
                        </button>
                    </div>
                </form>
                
                <!-- Generation Result -->
                <div id="cgap-result" class="cgap-result" style="display: none;">
                    <h3><?php _e('Generated Content', 'chatgpt-auto-publisher'); ?></h3>
                    <div class="cgap-result-content"></div>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="cgap-sidebar">
            <div class="cgap-card">
                <h3><?php _e('Recent Activity', 'chatgpt-auto-publisher'); ?></h3>
                <div id="cgap-recent-activity">
                    <?php
                    global $wpdb;
                    $table_name = $wpdb->prefix . 'cgap_generation_logs';
                    $recent_logs = $wpdb->get_results(
                        "SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT 5"
                    );
                    
                    if ($recent_logs): ?>
                        <ul class="cgap-activity-list">
                            <?php foreach ($recent_logs as $log): ?>
                                <li class="cgap-activity-item">
                                    <div class="cgap-activity-content">
                                        <?php if ($log->post_id): ?>
                                            <a href="<?php echo get_edit_post_link($log->post_id); ?>" target="_blank">
                                                <?php echo esc_html(get_the_title($log->post_id)); ?>
                                            </a>
                                        <?php else: ?>
                                            <span><?php _e('Content Generation', 'chatgpt-auto-publisher'); ?></span>
                                        <?php endif; ?>
                                        <small><?php echo cgap_time_ago($log->created_at); ?></small>
                                    </div>
                                    <div class="cgap-activity-meta">
                                        <span class="cgap-tokens"><?php echo number_format($log->tokens_used); ?> tokens</span>
                                        <span class="cgap-cost"><?php echo cgap_format_cost($log->cost); ?></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="cgap-no-activity"><?php _e('No recent activity', 'chatgpt-auto-publisher'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="cgap-card">
                <h3><?php _e('Quick Actions', 'chatgpt-auto-publisher'); ?></h3>
                <div class="cgap-quick-actions">
                    <a href="<?php echo admin_url('admin.php?page=cgap-scheduler'); ?>" class="cgap-quick-action">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <?php _e('Manage Scheduler', 'chatgpt-auto-publisher'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=cgap-logs'); ?>" class="cgap-quick-action">
                        <span class="dashicons dashicons-list-view"></span>
                        <?php _e('View Logs', 'chatgpt-auto-publisher'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=cgap-settings'); ?>" class="cgap-quick-action">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <?php _e('Settings', 'chatgpt-auto-publisher'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>