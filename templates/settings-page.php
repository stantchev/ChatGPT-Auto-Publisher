<?php
/**
 * Settings Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$settings = cgap_get_settings();
$available_models = (new CGAP_Settings())->get_available_models();
$categories = cgap_get_categories_dropdown();
$post_statuses = cgap_get_post_statuses();
$rate_limit_status = (new CGAP_Settings())->get_rate_limit_status();
?>

<div class="wrap cgap-settings">
    <h1><?php _e('ChatGPT Auto Publisher Settings', 'chatgpt-auto-publisher'); ?></h1>
    
    <form id="cgap-settings-form" class="cgap-form">
        <?php wp_nonce_field('cgap_nonce', 'cgap_nonce'); ?>
        
        <div class="cgap-settings-tabs">
            <nav class="cgap-tab-nav">
                <button type="button" class="cgap-tab-button active" data-tab="api"><?php _e('API Configuration', 'chatgpt-auto-publisher'); ?></button>
                <button type="button" class="cgap-tab-button" data-tab="content"><?php _e('Content Settings', 'chatgpt-auto-publisher'); ?></button>
                <button type="button" class="cgap-tab-button" data-tab="publishing"><?php _e('Publishing', 'chatgpt-auto-publisher'); ?></button>
                <button type="button" class="cgap-tab-button" data-tab="advanced"><?php _e('Advanced', 'chatgpt-auto-publisher'); ?></button>
            </nav>
            
            <!-- API Configuration Tab -->
            <div class="cgap-tab-content active" data-tab="api">
                <div class="cgap-card">
                    <h2><?php _e('OpenAI API Configuration', 'chatgpt-auto-publisher'); ?></h2>
                    
                    <div class="cgap-form-row">
                        <label for="openai_api_key"><?php _e('OpenAI API Key', 'chatgpt-auto-publisher'); ?> *</label>
                        <div class="cgap-input-group">
                            <input type="password" id="openai_api_key" name="openai_api_key" 
                                   value="<?php echo esc_attr($settings['openai_api_key']); ?>" 
                                   placeholder="sk-..." class="cgap-api-key-input">
                            <button type="button" id="cgap-test-api" class="button"><?php _e('Test Connection', 'chatgpt-auto-publisher'); ?></button>
                        </div>
                        <p class="description">
                            <?php _e('Get your API key from', 'chatgpt-auto-publisher'); ?> 
                            <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a>
                        </p>
                    </div>
                    
                    <div class="cgap-form-row">
                        <label for="default_model"><?php _e('Default Model', 'chatgpt-auto-publisher'); ?></label>
                        <select id="default_model" name="default_model">
                            <?php foreach ($available_models as $model_id => $model_info): ?>
                                <option value="<?php echo esc_attr($model_id); ?>" <?php selected($settings['default_model'], $model_id); ?>>
                                    <?php echo esc_html($model_info['name']); ?> - <?php echo esc_html($model_info['description']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="cgap-form-row cgap-form-grid">
                        <div>
                            <label for="max_tokens"><?php _e('Max Tokens', 'chatgpt-auto-publisher'); ?></label>
                            <input type="number" id="max_tokens" name="max_tokens" 
                                   value="<?php echo esc_attr($settings['max_tokens']); ?>" 
                                   min="100" max="4000" step="100">
                            <p class="description"><?php _e('Maximum tokens per request (100-4000)', 'chatgpt-auto-publisher'); ?></p>
                        </div>
                        
                        <div>
                            <label for="temperature"><?php _e('Temperature', 'chatgpt-auto-publisher'); ?></label>
                            <input type="number" id="temperature" name="temperature" 
                                   value="<?php echo esc_attr($settings['temperature']); ?>" 
                                   min="0" max="2" step="0.1">
                            <p class="description"><?php _e('Creativity level (0-2)', 'chatgpt-auto-publisher'); ?></p>
                        </div>
                    </div>
                    
                    <!-- Rate Limiting Info -->
                    <div class="cgap-rate-limit-info">
                        <h3><?php _e('Rate Limit Status', 'chatgpt-auto-publisher'); ?></h3>
                        <div class="cgap-rate-limit-stats">
                            <div class="cgap-rate-stat">
                                <strong><?php echo $rate_limit_status['requests_made']; ?></strong>
                                <span><?php _e('Requests Made', 'chatgpt-auto-publisher'); ?></span>
                            </div>
                            <div class="cgap-rate-stat">
                                <strong><?php echo $rate_limit_status['remaining']; ?></strong>
                                <span><?php _e('Remaining', 'chatgpt-auto-publisher'); ?></span>
                            </div>
                            <div class="cgap-rate-stat">
                                <strong><?php echo date('H:i:s', $rate_limit_status['reset_time']); ?></strong>
                                <span><?php _e('Reset Time', 'chatgpt-auto-publisher'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Content Settings Tab -->
            <div class="cgap-tab-content" data-tab="content">
                <div class="cgap-card">
                    <h2><?php _e('Default Content Settings', 'chatgpt-auto-publisher'); ?></h2>
                    
                    <div class="cgap-form-row cgap-form-grid">
                        <div>
                            <label for="default_tone"><?php _e('Default Tone', 'chatgpt-auto-publisher'); ?></label>
                            <select id="default_tone" name="default_tone">
                                <option value="professional" <?php selected($settings['default_tone'], 'professional'); ?>><?php _e('Professional', 'chatgpt-auto-publisher'); ?></option>
                                <option value="casual" <?php selected($settings['default_tone'], 'casual'); ?>><?php _e('Casual', 'chatgpt-auto-publisher'); ?></option>
                                <option value="technical" <?php selected($settings['default_tone'], 'technical'); ?>><?php _e('Technical', 'chatgpt-auto-publisher'); ?></option>
                                <option value="friendly" <?php selected($settings['default_tone'], 'friendly'); ?>><?php _e('Friendly', 'chatgpt-auto-publisher'); ?></option>
                                <option value="ai_optimized" <?php selected($settings['default_tone'], 'ai_optimized'); ?>><?php _e('AI Search Engines (AIO 2025)', 'chatgpt-auto-publisher'); ?></option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="default_length"><?php _e('Default Length', 'chatgpt-auto-publisher'); ?></label>
                            <select id="default_length" name="default_length">
                                <option value="short" <?php selected($settings['default_length'], 'short'); ?>><?php _e('Short (400 words)', 'chatgpt-auto-publisher'); ?></option>
                                <option value="medium" <?php selected($settings['default_length'], 'medium'); ?>><?php _e('Medium (800 words)', 'chatgpt-auto-publisher'); ?></option>
                                <option value="long" <?php selected($settings['default_length'], 'long'); ?>><?php _e('Long (1500 words)', 'chatgpt-auto-publisher'); ?></option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="cgap-form-row">
                        <label class="cgap-checkbox">
                            <input type="checkbox" name="seo_optimization" <?php checked($settings['seo_optimization']); ?>>
                            <?php _e('Enable SEO optimization (meta descriptions, focus keywords)', 'chatgpt-auto-publisher'); ?>
                        </label>
                    </div>
                    
                    <div class="cgap-form-row">
                        <label class="cgap-checkbox">
                            <input type="checkbox" name="include_images" <?php checked($settings['include_images']); ?>>
                            <?php _e('Generate featured images with DALL-E', 'chatgpt-auto-publisher'); ?>
                        </label>
                    </div>
                </div>
            </div>
            
            <!-- Publishing Tab -->
            <div class="cgap-tab-content" data-tab="publishing">
                <div class="cgap-card">
                    <h2><?php _e('Publishing Settings', 'chatgpt-auto-publisher'); ?></h2>
                    
                    <div class="cgap-form-row cgap-form-grid">
                        <div>
                            <label for="default_post_status"><?php _e('Default Post Status', 'chatgpt-auto-publisher'); ?></label>
                            <select id="default_post_status" name="default_post_status">
                                <?php foreach ($post_statuses as $status => $label): ?>
                                    <option value="<?php echo esc_attr($status); ?>" <?php selected($settings['default_post_status'], $status); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="default_category"><?php _e('Default Category', 'chatgpt-auto-publisher'); ?></label>
                            <select id="default_category" name="default_category">
                                <?php foreach ($categories as $cat_id => $cat_name): ?>
                                    <option value="<?php echo esc_attr($cat_id); ?>" <?php selected($settings['default_category'], $cat_id); ?>>
                                        <?php echo esc_html($cat_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="cgap-form-row">
                        <label class="cgap-checkbox">
                            <input type="checkbox" name="auto_publish" <?php checked($settings['auto_publish']); ?>>
                            <?php _e('Auto-publish generated content (bypass draft status)', 'chatgpt-auto-publisher'); ?>
                        </label>
                    </div>
                    
                    <div class="cgap-form-row">
                        <label class="cgap-checkbox">
                            <input type="checkbox" name="enable_scheduling" <?php checked($settings['enable_scheduling']); ?>>
                            <?php _e('Enable automated scheduling', 'chatgpt-auto-publisher'); ?>
                        </label>
                    </div>
                </div>
            </div>
            
            <!-- Advanced Tab -->
            <div class="cgap-tab-content" data-tab="advanced">
                <div class="cgap-card">
                    <h2><?php _e('Advanced Settings', 'chatgpt-auto-publisher'); ?></h2>
                    
                    <div class="cgap-form-row cgap-form-grid">
                        <div>
                            <label for="rate_limit_requests"><?php _e('Rate Limit (requests)', 'chatgpt-auto-publisher'); ?></label>
                            <input type="number" id="rate_limit_requests" name="rate_limit_requests" 
                                   value="<?php echo esc_attr($settings['rate_limit_requests']); ?>" 
                                   min="1" max="1000">
                            <p class="description"><?php _e('Max requests per time window', 'chatgpt-auto-publisher'); ?></p>
                        </div>
                        
                        <div>
                            <label for="rate_limit_window"><?php _e('Rate Limit Window (seconds)', 'chatgpt-auto-publisher'); ?></label>
                            <input type="number" id="rate_limit_window" name="rate_limit_window" 
                                   value="<?php echo esc_attr($settings['rate_limit_window']); ?>" 
                                   min="60" max="86400">
                            <p class="description"><?php _e('Time window for rate limiting', 'chatgpt-auto-publisher'); ?></p>
                        </div>
                    </div>
                    
                    <div class="cgap-form-row">
                        <label for="log_retention_days"><?php _e('Log Retention (days)', 'chatgpt-auto-publisher'); ?></label>
                        <input type="number" id="log_retention_days" name="log_retention_days" 
                               value="<?php echo esc_attr($settings['log_retention_days']); ?>" 
                               min="1" max="365">
                        <p class="description"><?php _e('How long to keep generation logs', 'chatgpt-auto-publisher'); ?></p>
                    </div>
                    
                    <div class="cgap-form-row">
                        <label class="cgap-checkbox">
                            <input type="checkbox" name="enable_logging" <?php checked($settings['enable_logging']); ?>>
                            <?php _e('Enable detailed logging', 'chatgpt-auto-publisher'); ?>
                        </label>
                    </div>
                    
                    <!-- Export/Import Settings -->
                    <div class="cgap-export-import">
                        <h3><?php _e('Export/Import Settings', 'chatgpt-auto-publisher'); ?></h3>
                        <div class="cgap-form-actions">
                            <button type="button" id="cgap-export-settings" class="button">
                                <?php _e('Export Settings', 'chatgpt-auto-publisher'); ?>
                            </button>
                            <button type="button" id="cgap-import-settings" class="button">
                                <?php _e('Import Settings', 'chatgpt-auto-publisher'); ?>
                            </button>
                            <input type="file" id="cgap-import-file" accept=".json" style="display: none;">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="cgap-form-actions cgap-settings-actions">
            <button type="submit" class="button button-primary">
                <?php _e('Save Settings', 'chatgpt-auto-publisher'); ?>
            </button>
            <button type="button" id="cgap-reset-settings" class="button button-secondary">
                <?php _e('Reset to Defaults', 'chatgpt-auto-publisher'); ?>
            </button>
        </div>
    </form>
</div>