<?php
/**
 * Plugin Name: ChatGPT Auto Publisher
 * Plugin URI: https://github.com/stantchev/ChatGPT-Auto-Publisher
 * Description: Automatically generate and publish WordPress posts using OpenAI's ChatGPT API with scheduling capabilities.
 * Version: 1.0.1
 * Author: Stanchev SEO
 * Author URI: https://stanchev.bg/
 * License: All rights reserved.
 * License URI: https://github.com/stantchev/ChatGPT-Auto-Publisher/tree/main?tab=License-1-ov-file#readme
 * Text Domain: chatgpt-auto-publisher
 * Domain Path: https://github.com/stantchev/ChatGPT-Auto-Publisher/blob/main/DOCUMENTATION.md
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CGAP_VERSION', '1.0.0');
define('CGAP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CGAP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CGAP_PLUGIN_FILE', __FILE__);

/**
 * Main plugin class
 */
class ChatGPT_Auto_Publisher {
    
    /**
     * Single instance of the plugin
     */
    private static $instance = null;
    
    /**
     * Get single instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
        $this->load_dependencies();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('cgap_scheduled_post_generation', array($this, 'generate_scheduled_post'));
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        require_once CGAP_PLUGIN_DIR . 'includes/class-seo-integration.php';
        require_once CGAP_PLUGIN_DIR . 'includes/class-content-quality-analyzer.php';
        require_once CGAP_PLUGIN_DIR . 'includes/class-ajax-handler.php';
        require_once CGAP_PLUGIN_DIR . 'includes/class-openai-api.php';
        require_once CGAP_PLUGIN_DIR . 'includes/class-post-generator.php';
        require_once CGAP_PLUGIN_DIR . 'includes/class-scheduler.php';
        require_once CGAP_PLUGIN_DIR . 'includes/class-settings.php';
        require_once CGAP_PLUGIN_DIR . 'includes/functions.php';
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        $this->create_tables();
        
        // Set default options
        $default_settings = array(
            'openai_api_key' => '',
            'default_model' => 'gpt-3.5-turbo',
            'max_tokens' => 1500,
            'temperature' => 0.7,
            'auto_publish' => false,
            'default_post_status' => 'draft',
            'enable_scheduling' => false,
            'default_category' => 1,
            'seo_optimization' => true,
            'include_images' => false
        );
        
        add_option('cgap_settings', $default_settings);
        
        // Schedule cron job for automated posting
        if (!wp_next_scheduled('cgap_scheduled_post_generation')) {
            wp_schedule_event(time(), 'hourly', 'cgap_scheduled_post_generation');
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('cgap_scheduled_post_generation');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain for translations
        load_plugin_textdomain('chatgpt-auto-publisher', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Table for storing generation logs
        $table_name = $wpdb->prefix . 'cgap_generation_logs';
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) DEFAULT NULL,
            prompt text NOT NULL,
            response longtext NOT NULL,
            model varchar(50) NOT NULL,
            tokens_used int(11) DEFAULT 0,
            cost decimal(10,6) DEFAULT 0.000000,
            status varchar(20) DEFAULT 'completed',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Table for scheduled posts
        $scheduled_table = $wpdb->prefix . 'cgap_scheduled_posts';
        
        $sql2 = "CREATE TABLE $scheduled_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            keywords text NOT NULL,
            frequency varchar(20) NOT NULL,
            next_run datetime NOT NULL,
            last_run datetime DEFAULT NULL,
            status varchar(20) DEFAULT 'active',
            settings longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY next_run (next_run),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        dbDelta($sql2);
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('ChatGPT Auto Publisher', 'chatgpt-auto-publisher'),
            __('ChatGPT Publisher', 'chatgpt-auto-publisher'),
            'manage_options',
            'chatgpt-auto-publisher',
            array($this, 'admin_page'),
            'dashicons-edit-large',
            30
        );
        
        add_submenu_page(
            'chatgpt-auto-publisher',
            __('Generate Content', 'chatgpt-auto-publisher'),
            __('Generate Content', 'chatgpt-auto-publisher'),
            'manage_options',
            'chatgpt-auto-publisher',
            array($this, 'admin_page')
        );
        
        add_submenu_page(
            'chatgpt-auto-publisher',
            __('Scheduler', 'chatgpt-auto-publisher'),
            __('Scheduler', 'chatgpt-auto-publisher'),
            'manage_options',
            'cgap-scheduler',
            array($this, 'scheduler_page')
        );
        
        add_submenu_page(
            'chatgpt-auto-publisher',
            __('Generation Logs', 'chatgpt-auto-publisher'),
            __('Generation Logs', 'chatgpt-auto-publisher'),
            'manage_options',
            'cgap-logs',
            array($this, 'logs_page')
        );
        
        add_submenu_page(
            'chatgpt-auto-publisher',
            __('Settings', 'chatgpt-auto-publisher'),
            __('Settings', 'chatgpt-auto-publisher'),
            'manage_options',
            'cgap-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'chatgpt-auto-publisher') === false && strpos($hook, 'cgap-') === false) {
            return;
        }
        
        wp_enqueue_script(
            'cgap-admin-js',
            CGAP_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            CGAP_VERSION,
            true
        );
        
        wp_enqueue_style(
            'cgap-admin-css',
            CGAP_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            CGAP_VERSION
        );
        
        // Create nonces for all AJAX actions
        $nonces = array();
        $ajax_actions = array(
            'cgap_generate_content',
            'cgap_save_settings',
            'cgap_test_api',
            'cgap_add_scheduled_post',
            'cgap_delete_scheduled_post',
            'cgap_toggle_scheduled_post',
            'cgap_get_log_details',
            'cgap_export_logs',
            'cgap_clear_logs',
            'cgap_analyze_content',
            'cgap_get_content_suggestions',
            'cgap_check_content_gaps',
            'cgap_translate_content',
            'cgap_check_seo_plugins',
            'cgap_create_translated_post'
        );
        
        foreach ($ajax_actions as $action) {
            $nonces[$action] = wp_create_nonce($action);
        }
        
        wp_localize_script('cgap-admin-js', 'cgap_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cgap_nonce'),
            'nonces' => $nonces,
            'strings' => array(
                'generating' => __('Generating content...', 'chatgpt-auto-publisher'),
                'error' => __('An error occurred. Please try again.', 'chatgpt-auto-publisher'),
                'success' => __('Content generated successfully!', 'chatgpt-auto-publisher'),
                'testing_api' => __('Testing API connection...', 'chatgpt-auto-publisher'),
                'api_success' => __('API connection successful!', 'chatgpt-auto-publisher'),
                'api_error' => __('API connection failed. Please check your settings.', 'chatgpt-auto-publisher'),
                'timeout' => __('Request timed out. Please try again.', 'chatgpt-auto-publisher'),
                'topic_required' => __('Topic is required', 'chatgpt-auto-publisher')
            )
        ));
    }
    
    /**
     * Enqueue frontend scripts
     */
    public function enqueue_frontend_scripts() {
        wp_enqueue_style(
            'cgap-frontend-css',
            CGAP_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            CGAP_VERSION
        );
    }
    
    /**
     * Main admin page
     */
    public function admin_page() {
        include CGAP_PLUGIN_DIR . 'templates/admin-page.php';
    }
    
    /**
     * Scheduler page
     */
    public function scheduler_page() {
        include CGAP_PLUGIN_DIR . 'templates/scheduler-page.php';
    }
    
    /**
     * Logs page
     */
    public function logs_page() {
        include CGAP_PLUGIN_DIR . 'templates/logs-page.php';
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        include CGAP_PLUGIN_DIR . 'templates/settings-page.php';
    }
    
    /**
     * Generate scheduled post
     */
    public function generate_scheduled_post() {
        $scheduler = new CGAP_Scheduler();
        $scheduler->process_scheduled_posts();
    }
}

// Initialize the plugin
ChatGPT_Auto_Publisher::get_instance();
