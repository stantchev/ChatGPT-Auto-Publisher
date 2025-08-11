# ChatGPT Auto Publisher WordPress Plugin

A professional WordPress plugin that integrates with OpenAI's ChatGPT API to automatically generate and publish high-quality blog posts with SEO optimization, scheduling capabilities, and comprehensive management features.

## Features

### AI Content Generation
- **OpenAI Integration**: Full integration with ChatGPT (GPT-3.5, GPT-4, GPT-4 Turbo)
- **Smart Content Creation**: Generates structured posts with proper headings, introductions, and conclusions
- **SEO Optimization**: Automatic meta descriptions, focus keywords, and SEO-friendly formatting
- **Multiple Tones**: Professional, casual, technical, and friendly writing styles
- **Flexible Length**: Short (400 words), medium (800 words), or long (1500+ words) content

### Automated Scheduling
- **Flexible Scheduling**: Hourly, daily, weekly, or monthly post generation
- **Topic Variations**: Automatically creates variations of base topics to avoid repetition
- **Smart Queuing**: Intelligent scheduling system with failure handling and retry logic
- **Batch Processing**: Efficient handling of multiple scheduled posts

### Image Generation
- **DALL-E Integration**: Automatic featured image generation using OpenAI's DALL-E
- **Smart Prompts**: Context-aware image prompts based on post content
- **Media Library**: Seamless integration with WordPress media library

### Analytics & Monitoring
- **Generation Logs**: Detailed logs of all content generation activities
- **Cost Tracking**: Monitor API usage and associated costs
- **Performance Metrics**: Track tokens used, generation success rates, and more
- **Rate Limiting**: Built-in rate limiting to prevent API overuse

### Advanced Management
- **User Permissions**: Role-based access control for content generation
- **Settings Export/Import**: Easy backup and migration of plugin settings
- **Error Handling**: Comprehensive error logging and recovery mechanisms
- **WordPress Integration**: Full compatibility with Gutenberg, Yoast SEO, and other popular plugins

## Installation

### Requirements
- WordPress 5.0 or higher
- PHP 7.4 or higher
- OpenAI API key
- MySQL 5.6 or higher

### Manual Installation

1. **Download the Plugin**
   ```bash
   git clone https://github.com/your-repo/chatgpt-auto-publisher.git
   cd chatgpt-auto-publisher
   ```

2. **Upload to WordPress**
   - Copy the plugin folder to `/wp-content/plugins/`
   - Or upload the ZIP file through WordPress admin

3. **Activate the Plugin**
   - Go to WordPress Admin → Plugins
   - Find "ChatGPT Auto Publisher" and click "Activate"

4. **Configure API Settings**
   - Navigate to ChatGPT Publisher → Settings
   - Enter your OpenAI API key
   - Configure default settings

## Configuration

### OpenAI API Setup

1. **Get API Key**
   - Visit [OpenAI Platform](https://platform.openai.com/api-keys)
   - Create a new API key
   - Copy the key (starts with `sk-`)

2. **Configure Plugin**
   - Go to ChatGPT Publisher → Settings → API Configuration
   - Paste your API key
   - Select your preferred model (GPT-3.5 Turbo recommended for cost-effectiveness)
   - Test the connection

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

## Usage

### Manual Content Generation

1. **Navigate to Dashboard**
   - Go to ChatGPT Publisher in WordPress admin
   - Click "Generate Content"

2. **Fill in Details**
   - Enter topic/title
   - Add relevant keywords (comma-separated)
   - Select tone and length
   - Choose whether to publish immediately or save as draft

3. **Generate and Review**
   - Click "Generate Content"
   - Review the generated post
   - Edit if necessary
   - Publish when ready

### Automated Scheduling

1. **Enable Scheduling**
   - Go to Settings → Publishing
   - Enable "Automated Scheduling"

2. **Create Schedule**
   - Navigate to Scheduler
   - Click "Add New Schedule"
   - Configure frequency, keywords, and settings
   - Save the schedule

3. **Monitor Execution**
   - Check the scheduler dashboard for next run times
   - View generation logs for automated posts
   - Adjust schedules as needed

## API Reference

### Content Generation Endpoint

```php
// Generate content programmatically
$generator = new CGAP_Post_Generator();
$result = $generator->generate_post(
    'Your Topic Here',
    'keyword1, keyword2',
    'professional',
    'medium',
    false // auto_publish
);
```

### Settings Management

```php
// Get plugin settings
$settings = cgap_get_settings();

// Update specific setting
cgap_update_setting('default_tone', 'casual');

// Check if API is configured
if (cgap_is_api_configured()) {
    // API is ready
}
```

### Hooks and Filters

```php
// Modify generated content before saving
add_filter('cgap_before_post_save', function($post_data, $generated_content) {
    // Modify post data
    return $post_data;
}, 10, 2);

// Custom post generation logic
add_action('cgap_after_post_generated', function($post_id, $generation_data) {
    // Custom actions after post generation
}, 10, 2);

// Modify AI prompt
add_filter('cgap_generation_prompt', function($prompt, $topic, $keywords) {
    // Customize the prompt sent to OpenAI
    return $prompt;
}, 10, 3);
```

## Database Schema

### Generation Logs Table
```sql
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

### Scheduled Posts Table
```sql
CREATE TABLE wp_cgap_scheduled_posts (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    title varchar(255) NOT NULL,
    keywords text NOT NULL,
    frequency varchar(20) NOT NULL,
    next_run datetime NOT NULL,
    last_run datetime DEFAULT NULL,
    status varchar(20) DEFAULT 'active',
    settings longtext DEFAULT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
);
```

## Security Considerations

### API Key Protection
- API keys are stored encrypted in the database
- Never expose API keys in frontend code
- Use environment variables for sensitive data

### User Permissions
- Only users with `manage_options` capability can configure settings
- Content generation requires `edit_posts` capability
- All AJAX requests are nonce-protected

### Rate Limiting
- Built-in rate limiting prevents API abuse
- Configurable limits per time window
- Automatic backoff on API errors

## Troubleshooting

### Common Issues

**API Connection Failed**
- Verify API key is correct and active
- Check OpenAI account has sufficient credits
- Ensure server can make HTTPS requests

**Content Generation Timeout**
- Increase `max_execution_time` in settings
- Reduce `max_tokens` for faster generation
- Check server memory limits

**Scheduled Posts Not Running**
- Verify WordPress cron is working
- Check if `wp_cron()` is disabled
- Review error logs for cron failures

### Debug Mode

Enable debug mode in settings or via environment variable:
```env
CGAP_DEBUG=true
CGAP_LOG_LEVEL=debug
```

### Log Files

Check WordPress debug logs and plugin-specific logs:
- WordPress: `/wp-content/debug.log`
- Plugin logs: Available in admin dashboard

## Performance Optimization

### Caching
- Generated content is cached to reduce API calls
- Settings are cached for better performance
- Use object caching for high-traffic sites

### Database Optimization
- Regular cleanup of old logs (configurable retention)
- Indexed tables for fast queries
- Optimized batch processing

### API Efficiency
- Smart token management
- Batch requests where possible
- Automatic retry with exponential backoff

### Coding Standards
- Follow WordPress Coding Standards
- Use proper sanitization and validation
- Include comprehensive documentation
- Write unit tests for new features

## License

Copyright © 2025 Milen Stanchev — https://stanchev.bg/

All rights reserved.

This software and associated files are the intellectual property of Milen Stanchev. No part of this codebase may be used, copied, modified, merged, published, distributed, sublicensed, sold, or reused in any way without the prior explicit written permission of the author.

Unauthorized use is strictly prohibited and may result in legal action.

For licensing inquiries, please contact:
https://stanchev.bg/

## Support

For support, feature requests, or bug reports:
- Create an issue on GitHub
- Contact support team on https://stanchev.bg/
- Check documentation wiki

## Changelog

### Version 1.0.2
Release Notes – SEO & AI Content Optimization Upgrade
- **Added** integration with major SEO plugins (Yoast, RankMath, AIOSEO, SEOPress, The SEO Framework) with real-time detection and data exchange.  
- **Introduced** real-time Content Quality Scoring (0–100) with readability, keyword density, and structure analysis.  
- **Implemented** AI-powered optimization tips, keyword placement recommendations, and competitor insights.  
- **Added** multi-language support (8 languages) with SEO-preserved translations and one-click post creation.  
- **Redesigned UI** for single focus keyword optimization, responsive layout, and real-time quality panels.  
- **Optimized** performance, improved security, and ensured full WordPress 5.0+ compatibility. 

### Version 1.0.1
- Initial release
- OpenAI ChatGPT integration
- Automated scheduling
- SEO optimization
- DALL-E image generation
- Comprehensive admin interface

---

**Made with ❤️ for the WordPress community**
