# ChatGPT Auto Publisher WordPress Plugin Documentation

## Plugin Overview

**Plugin Name**: ChatGPT Auto Publisher  
**Version**: 1.0.2  
**Author**: Stanchev SEO  
**License**: All rights reserved  

ChatGPT Auto Publisher is a professional WordPress plugin that integrates with OpenAI's ChatGPT API to automatically generate and publish high-quality blog posts. The plugin features SEO optimization, automated scheduling capabilities, DALL-E image generation, and comprehensive management tools for content creators and website administrators.

**Primary Purpose**: Automate content creation workflows by leveraging AI to generate engaging, SEO-optimized blog posts with minimal manual intervention while maintaining quality and consistency.

---

## Installation Instructions

### Requirements

Before installing the plugin, ensure your environment meets these requirements:

- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher  
- **MySQL**: 5.6 or higher
- **OpenAI API Key**: Required for content generation
- **Server Requirements**: HTTPS enabled, `wp_remote_request()` function available

### Step 1: Download and Upload

1. **Download the Plugin**
   ```bash
   # Clone from repository (if available)
   git clone https://github.com/your-repo/chatgpt-auto-publisher.git
   
   # Or download ZIP file from releases
   wget https://github.com/your-repo/chatgpt-auto-publisher/releases/latest/download/chatgpt-auto-publisher.zip
   ```

2. **Upload to WordPress**
   ```bash
   # Via FTP/SFTP
   unzip chatgpt-auto-publisher.zip
   cp -r chatgpt-auto-publisher/ /path/to/wordpress/wp-content/plugins/
   
   # Or upload ZIP through WordPress admin
   # Go to Plugins → Add New → Upload Plugin
   ```

### Step 2: Activate the Plugin

1. Navigate to **WordPress Admin → Plugins**
2. Find "ChatGPT Auto Publisher" in the list
3. Click **"Activate"**

### Step 3: Initial Configuration

After activation, you'll see a new menu item "ChatGPT Publisher" in your WordPress admin:

```php
// The plugin automatically creates these database tables:
// - wp_cgap_generation_logs (stores generation history)
// - wp_cgap_scheduled_posts (manages automated scheduling)
```

---

## Configuration

### Required Configuration

#### OpenAI API Key Setup

1. **Obtain API Key**
   - Visit [OpenAI Platform](https://platform.openai.com/api-keys)
   - Create a new API key
   - Copy the key (format: `sk-...`)

2. **Configure in WordPress**
   ```php
   // Navigate to: ChatGPT Publisher → Settings → API Configuration
   // Enter your API key in the "OpenAI API Key" field
   // Click "Test Connection" to verify
   ```

### Optional Configuration Parameters

#### Content Settings

| Parameter | Default | Description |
|-----------|---------|-------------|
| `default_model` | `gpt-3.5-turbo` | AI model for content generation |
| `max_tokens` | `1500` | Maximum tokens per request (100-4000) |
| `temperature` | `0.7` | Creativity level (0-2) |
| `default_tone` | `professional` | Writing tone (professional, casual, technical, friendly) |
| `default_length` | `medium` | Content length (short: 400, medium: 800, long: 1500 words) |

#### Publishing Settings

| Parameter | Default | Description |
|-----------|---------|-------------|
| `auto_publish` | `false` | Automatically publish generated content |
| `default_post_status` | `draft` | Default status for new posts |
| `default_category` | `1` | Default category ID for posts |
| `seo_optimization` | `true` | Enable SEO meta descriptions and keywords |
| `include_images` | `false` | Generate featured images with DALL-E |

#### Advanced Settings

| Parameter | Default | Description |
|-----------|---------|-------------|
| `rate_limit_requests` | `60` | Maximum requests per time window |
| `rate_limit_window` | `3600` | Rate limit window in seconds |
| `enable_logging` | `true` | Enable detailed generation logging |
| `log_retention_days` | `30` | How long to keep logs |

### Environment Variables (Optional)

Create a `.env` file in the plugin directory for additional configuration:

```env
# OpenAI Configuration
OPENAI_API_KEY=sk-your-api-key-here
OPENAI_ORG_ID=org-your-org-id

# Plugin Settings
CGAP_DEBUG=false
CGAP_LOG_LEVEL=info
CGAP_RATE_LIMIT=60
CGAP_MAX_EXECUTION_TIME=300

# Security
CGAP_ENCRYPTION_KEY=your-32-character-key
```

---

## Usage Examples

### Example 1: Basic Content Generation

Generate a single blog post manually through the admin interface:

```php
// Navigate to: ChatGPT Publisher → Generate Content
// Fill in the form:

$generation_data = [
    'topic' => 'Digital Marketing Strategies for Small Businesses',
    'keywords' => 'digital marketing, small business, SEO, social media',
    'tone' => 'professional',
    'length' => 'medium',
    'auto_publish' => false
];

// Click "Generate Content" - the plugin will:
// 1. Create an optimized prompt
// 2. Send request to OpenAI API
// 3. Parse and format the response
// 4. Create WordPress post with SEO metadata
// 5. Log the generation details
```

### Example 2: Programmatic Content Generation

Generate content programmatically using the plugin's classes:

```php
<?php
// Ensure WordPress is loaded
if (!defined('ABSPATH')) {
    require_once('/path/to/wordpress/wp-load.php');
}

// Initialize the post generator
$generator = new CGAP_Post_Generator();

try {
    // Generate a blog post
    $result = $generator->generate_post(
        'The Future of E-commerce Technology',  // topic
        'ecommerce, technology, AI, automation', // keywords
        'technical',                            // tone
        'long',                                // length
        true                                   // auto_publish
    );
    
    // Access the results
    echo "Post ID: " . $result['post_id'] . "\n";
    echo "Title: " . $result['title'] . "\n";
    echo "Tokens Used: " . $result['tokens_used'] . "\n";
    echo "Cost: " . $result['cost'] . "\n";
    echo "Edit URL: " . $result['edit_url'] . "\n";
    
} catch (Exception $e) {
    error_log('Content generation failed: ' . $e->getMessage());
}
```

### Example 3: Automated Scheduling Setup

Set up automated content generation with scheduling:

```php
<?php
// Initialize the scheduler
$scheduler = new CGAP_Scheduler();

// Add a new scheduled post
$schedule_id = $scheduler->add_scheduled_post(
    'Weekly Tech News Roundup',              // title
    'technology, news, trends, innovation',   // keywords
    'weekly',                                // frequency
    [
        'tone' => 'professional',
        'length' => 'medium',
        'auto_publish' => true
    ]
);

if ($schedule_id) {
    echo "Schedule created with ID: " . $schedule_id . "\n";
    
    // Get schedule statistics
    $stats = $scheduler->get_statistics();
    echo "Total active schedules: " . $stats['active'] . "\n";
    echo "Next run: " . $stats['next_run'] . "\n";
} else {
    echo "Failed to create schedule\n";
}

// The WordPress cron will automatically process scheduled posts
// Hook: 'cgap_scheduled_post_generation'
```

---

## API Reference

### Core Classes

#### CGAP_Post_Generator

Main class for content generation functionality.

**Methods:**

```php
public function generate_post($topic, $keywords = '', $tone = 'professional', $length = 'medium', $auto_publish = false)
```

**Parameters:**
- `$topic` (string, required): Main topic or title for the post
- `$keywords` (string, optional): Comma-separated keywords
- `$tone` (string, optional): Writing tone - 'professional', 'casual', 'technical', 'friendly'
- `$length` (string, optional): Content length - 'short', 'medium', 'long'
- `$auto_publish` (boolean, optional): Whether to publish immediately

**Returns:**
```php
[
    'post_id' => 123,
    'title' => 'Generated Post Title',
    'content' => 'Full post content...',
    'excerpt' => 'Post excerpt...',
    'meta_description' => 'SEO meta description',
    'tokens_used' => 1250,
    'model' => 'gpt-3.5-turbo',
    'cost' => 0.0025,
    'edit_url' => 'https://site.com/wp-admin/post.php?post=123&action=edit',
    'view_url' => 'https://site.com/post-slug/'
]
```

#### CGAP_Scheduler

Manages automated content scheduling.

**Methods:**

```php
public function add_scheduled_post($title, $keywords, $frequency, $settings = [])
public function get_scheduled_posts($status = 'active')
public function update_scheduled_post($id, $data)
public function delete_scheduled_post($id)
public function process_scheduled_posts()
```

#### CGAP_Settings

Handles plugin configuration and settings.

**Methods:**

```php
public function get_settings()
public function get_setting($key, $default = null)
public function update_settings($new_settings)
public function update_setting($key, $value)
public function is_api_configured()
public function check_rate_limit()
```

#### CGAP_OpenAI_API

Direct interface with OpenAI API.

**Methods:**

```php
public function test_connection()
public function generate_content($prompt, $system_message = null)
public function generate_image($prompt, $size = '1024x1024')
public function calculate_cost($tokens, $model = null)
```

### Helper Functions

```php
// Get plugin settings
cgap_get_settings()
cgap_get_setting($key, $default = null)

// Check API configuration
cgap_is_api_configured()

// Logging
cgap_log($message, $level = 'info')

// Utilities
cgap_format_cost($cost)
cgap_get_generation_stats($days = 30)
cgap_clean_old_logs()
cgap_time_ago($datetime)
```

### WordPress Hooks

#### Actions

```php
// Triggered after successful post generation
do_action('cgap_after_post_generated', $post_id, $generation_data);

// Scheduled post processing
do_action('cgap_scheduled_post_generation');

// Log cleanup
do_action('cgap_cleanup_logs');
```

#### Filters

```php
// Modify content before saving
apply_filters('cgap_before_post_save', $post_data, $generated_content);

// Customize AI prompt
apply_filters('cgap_generation_prompt', $prompt, $topic, $keywords);

// Modify generated content
apply_filters('cgap_generated_content', $content, $topic, $keywords);
```

---

## Troubleshooting

### Common Issues and Solutions

#### 1. API Connection Failed

**Symptoms:**
- "API connection failed" error message
- Content generation not working

**Solutions:**
```php
// Check API key format
if (!preg_match('/^sk-[a-zA-Z0-9]{48}$/', $api_key)) {
    echo "Invalid API key format";
}

// Verify server can make HTTPS requests
$response = wp_remote_get('https://api.openai.com/v1/models', [
    'headers' => ['Authorization' => 'Bearer ' . $api_key]
]);

if (is_wp_error($response)) {
    echo "Server cannot connect to OpenAI: " . $response->get_error_message();
}
```

**Common causes:**
- Incorrect API key
- Insufficient OpenAI credits
- Server firewall blocking HTTPS requests
- `wp_remote_request()` function disabled

#### 2. Content Generation Timeout

**Symptoms:**
- Request timeout errors
- Incomplete content generation

**Solutions:**
```php
// Increase PHP execution time
ini_set('max_execution_time', 300);

// Reduce max_tokens in settings
update_option('cgap_settings', [
    'max_tokens' => 1000  // Reduce from default 1500
]);

// Check server memory limits
if (memory_get_usage() > (1024 * 1024 * 128)) { // 128MB
    echo "Consider increasing PHP memory_limit";
}
```

#### 3. Scheduled Posts Not Running

**Symptoms:**
- Scheduled posts remain in queue
- No automatic content generation

**Solutions:**
```php
// Check if WordPress cron is working
if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
    echo "WordPress cron is disabled. Enable it or set up system cron.";
}

// Manually trigger scheduled posts
do_action('cgap_scheduled_post_generation');

// Check next scheduled event
$next_run = wp_next_scheduled('cgap_scheduled_post_generation');
echo "Next scheduled run: " . date('Y-m-d H:i:s', $next_run);
```

#### 4. High API Costs

**Symptoms:**
- Unexpected OpenAI charges
- Rate limit exceeded errors

**Solutions:**
```php
// Monitor token usage
$stats = cgap_get_generation_stats(30);
echo "Tokens used (30 days): " . number_format($stats['total_tokens']);
echo "Estimated cost: " . cgap_format_cost($stats['total_cost']);

// Implement stricter rate limiting
cgap_update_setting('rate_limit_requests', 20);  // Reduce from 60
cgap_update_setting('max_tokens', 800);          // Reduce token limit
```

#### 5. Database Issues

**Symptoms:**
- Plugin activation errors
- Missing generation logs

**Solutions:**
```sql
-- Check if tables exist
SHOW TABLES LIKE 'wp_cgap_%';

-- Recreate tables if missing
CREATE TABLE wp_cgap_generation_logs (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    post_id bigint(20) DEFAULT NULL,
    prompt text NOT NULL,
    response longtext NOT NULL,
    model varchar(50) NOT NULL,
    tokens_used int(11) DEFAULT 0,
    cost decimal(10,6) DEFAULT 0.000000,
    status varchar(20) DEFAULT 'completed',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
);
```

### Debug Mode

Enable debug mode for detailed troubleshooting:

```php
// Add to wp-config.php
define('CGAP_DEBUG', true);
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Check debug logs
tail -f /path/to/wordpress/wp-content/debug.log | grep CGAP
```

---

## Changelog

### Version 1.0.1 (Old)
- **Added**: Initial release with core functionality
- **Added**: OpenAI ChatGPT integration (GPT-3.5, GPT-4, GPT-4 Turbo)
- **Added**: Automated scheduling system
- **Added**: SEO optimization features
- **Added**: DALL-E image generation
- **Added**: Comprehensive admin interface
- **Added**: Generation logging and analytics
- **Added**: Rate limiting and cost tracking
- **Added**: Export/import settings functionality

### Version 1.0.2 (08.11.2025)
- **Added**: Integration with popular SEO plugins
- **Added**: Multi-language content generation
- **Added**: Content quality scoring

### Upcoming Features (Roadmap)
- **Planned**: Bulk content generation
- **Planned**: Custom post type support
- **Planned**: Advanced prompt templates
- **Planned**: Social media auto-posting
- **Planned**: A/B testing for generated content

---

## Support Information

### Getting Help

#### Documentation and Resources
- **Plugin Documentation**: This document
- **WordPress Codex**: [WordPress Plugin Development](https://codex.wordpress.org/Plugin_API)
- **OpenAI Documentation**: [OpenAI API Reference](https://platform.openai.com/docs)

#### Community Support
- **WordPress Forums**: Search for "ChatGPT Auto Publisher"
- **GitHub Issues**: Report bugs and feature requests
- **Stack Overflow**: Tag questions with `wordpress` and `chatgpt-auto-publisher`

### Reporting Bugs

When reporting bugs, please include:

1. **WordPress Version**: `<?php echo get_bloginfo('version'); ?>`
2. **Plugin Version**: `<?php echo CGAP_VERSION; ?>`
3. **PHP Version**: `<?php echo PHP_VERSION; ?>`
4. **Error Messages**: Copy exact error text
5. **Steps to Reproduce**: Detailed reproduction steps
6. **Expected vs Actual Behavior**: What should happen vs what happens

**Bug Report Template:**
```markdown
**Environment:**
- WordPress: 6.4.1
- Plugin: 1.0.1
- PHP: 8.1.0
- Server: Apache/Nginx

**Issue Description:**
Brief description of the problem

**Steps to Reproduce:**
1. Go to...
2. Click on...
3. See error...

**Expected Behavior:**
What should happen

**Actual Behavior:**
What actually happens

**Error Messages:**
```
Copy any error messages here
```

**Additional Context:**
Any other relevant information
```

### Contributing

#### Development Setup

```bash
# Clone the repository
git clone https://github.com/your-repo/chatgpt-auto-publisher.git
cd chatgpt-auto-publisher

# Set up development environment
cp .env.example .env
# Edit .env with your settings

# Install development dependencies (if any)
composer install --dev
npm install
```

#### Coding Standards

- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- Use proper sanitization and validation
- Include comprehensive documentation
- Write unit tests for new features

#### Pull Request Process

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Make your changes
4. Test thoroughly
5. Commit with clear messages: `git commit -m 'Add amazing feature'`
6. Push to your fork: `git push origin feature/amazing-feature`
7. Open a Pull Request

### Commercial Support

For priority support, custom development, or enterprise features:

- **Website**: [https://stanchev.bg/](https://stanchev.bg/)
- **Email**: Contact through website
- **Response Time**: 24-48 hours for commercial support

### License and Legal

**Copyright**: © 2025 Milen Stanchev — https://stanchev.bg/

**License**: All rights reserved. This software and associated files are the intellectual property of Milen Stanchev. No part of this codebase may be used, copied, modified, merged, published, distributed, sublicensed, sold, or reused in any way without the prior explicit written permission of the author.

**Disclaimer**: This plugin integrates with third-party services (OpenAI). Users are responsible for compliance with OpenAI's terms of service and any applicable data protection regulations.

---

*Last updated: January 2025*
*Plugin Version: 1.0.1*
*Documentation Version: 1.0*
