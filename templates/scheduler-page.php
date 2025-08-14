<?php
/**
 * Scheduler Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$scheduler = new CGAP_Scheduler();
$scheduled_posts = $scheduler->get_scheduled_posts();
$stats = $scheduler->get_statistics();
?>

<div class="wrap cgap-scheduler">
    <h1><?php _e('Content Scheduler', 'chatgpt-auto-publisher'); ?></h1>
    
    <!-- Statistics -->
    <div class="cgap-stats-grid">
        <div class="cgap-stat-card">
            <div class="cgap-stat-icon">📅</div>
            <div class="cgap-stat-content">
                <h3><?php echo $stats['total']; ?></h3>
                <p><?php _e('Total Schedules', 'chatgpt-auto-publisher'); ?></p>
            </div>
        </div>
        
        <div class="cgap-stat-card">
            <div class="cgap-stat-icon">✅</div>
            <div class="cgap-stat-content">
                <h3><?php echo $stats['active']; ?></h3>
                <p><?php _e('Active', 'chatgpt-auto-publisher'); ?></p>
            </div>
        </div>
        
        <div class="cgap-stat-card">
            <div class="cgap-stat-icon">⏸️</div>
            <div class="cgap-stat-content">
                <h3><?php echo $stats['paused']; ?></h3>
                <p><?php _e('Paused', 'chatgpt-auto-publisher'); ?></p>
            </div>
        </div>
        
        <div class="cgap-stat-card">
            <div class="cgap-stat-icon">⏰</div>
            <div class="cgap-stat-content">
                <h3><?php echo $stats['next_run'] ? date('H:i', strtotime($stats['next_run'])) : 'N/A'; ?></h3>
                <p><?php _e('Next Run', 'chatgpt-auto-publisher'); ?></p>
            </div>
        </div>
    </div>
    
    <!-- Add New Schedule Form -->
    <div class="cgap-card">
        <h2><?php _e('Add New Schedule', 'chatgpt-auto-publisher'); ?></h2>
        
        <form id="cgap-scheduler-form" class="cgap-form">
            <?php wp_nonce_field('cgap_nonce', 'cgap_nonce'); ?>
            
            <div class="cgap-form-row cgap-form-grid">
                <div>
                    <label for="schedule_title"><?php _e('Schedule Title', 'chatgpt-auto-publisher'); ?> *</label>
                    <input type="text" id="schedule_title" name="title" required 
                           placeholder="<?php _e('e.g., Weekly Marketing Posts', 'chatgpt-auto-publisher'); ?>">
                </div>
                
                <div>
                    <label for="schedule_frequency"><?php _e('Frequency', 'chatgpt-auto-publisher'); ?></label>
                    <select id="schedule_frequency" name="frequency">
                        <option value="hourly"><?php _e('Hourly', 'chatgpt-auto-publisher'); ?></option>
                        <option value="daily" selected><?php _e('Daily', 'chatgpt-auto-publisher'); ?></option>
                        <option value="weekly"><?php _e('Weekly', 'chatgpt-auto-publisher'); ?></option>
                        <option value="monthly"><?php _e('Monthly', 'chatgpt-auto-publisher'); ?></option>
                    </select>
                </div>
            </div>
            
            <div class="cgap-form-row">
                <label for="schedule_keywords"><?php _e('Keywords/Topics', 'chatgpt-auto-publisher'); ?> *</label>
                <input type="text" id="schedule_keywords" name="keywords" required 
                       placeholder="<?php _e('digital marketing, SEO, content strategy', 'chatgpt-auto-publisher'); ?>">
                <p class="description"><?php _e('Comma-separated keywords that will be used to generate varied content', 'chatgpt-auto-publisher'); ?></p>
            </div>
            
            <div class="cgap-form-actions">
                <button type="submit" class="button button-primary">
                    <?php _e('Add Schedule', 'chatgpt-auto-publisher'); ?>
                </button>
            </div>
        </form>
    </div>
    
    <!-- Scheduled Posts List -->
    <div class="cgap-card">
        <h2><?php _e('Scheduled Posts', 'chatgpt-auto-publisher'); ?></h2>
        
        <?php if (empty($scheduled_posts)): ?>
            <p class="cgap-no-activity"><?php _e('No scheduled posts configured yet.', 'chatgpt-auto-publisher'); ?></p>
        <?php else: ?>
            <div class="cgap-scheduled-list">
                <?php foreach ($scheduled_posts as $post): ?>
                    <div class="cgap-scheduled-item">
                        <div class="cgap-scheduled-content">
                            <h4><?php echo esc_html($post->title); ?></h4>
                            <div class="cgap-scheduled-meta">
                                <span class="cgap-frequency"><?php echo ucfirst($post->frequency); ?></span>
                                <span class="cgap-next-run">
                                    <?php _e('Next:', 'chatgpt-auto-publisher'); ?> 
                                    <?php echo date('M j, Y H:i', strtotime($post->next_run)); ?>
                                </span>
                                <?php if ($post->last_run): ?>
                                    <span class="cgap-last-run">
                                        <?php _e('Last:', 'chatgpt-auto-publisher'); ?> 
                                        <?php echo cgap_time_ago($post->last_run); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="cgap-keywords">
                                <?php 
                                $keywords = explode(',', $post->keywords);
                                foreach ($keywords as $keyword): ?>
                                    <span class="cgap-keyword-tag"><?php echo esc_html(trim($keyword)); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="cgap-scheduled-actions">
                            <span class="cgap-status cgap-status-<?php echo $post->status; ?>">
                                <?php echo ucfirst($post->status); ?>
                            </span>
                            <button class="button button-small cgap-toggle-schedule" data-id="<?php echo $post->id; ?>" data-status="<?php echo $post->status; ?>">
                                <?php echo $post->status === 'active' ? __('Pause', 'chatgpt-auto-publisher') : __('Resume', 'chatgpt-auto-publisher'); ?>
                            </button>
                            <button class="button button-small button-link-delete cgap-delete-scheduled" data-id="<?php echo $post->id; ?>">
                                <?php _e('Delete', 'chatgpt-auto-publisher'); ?>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.cgap-scheduled-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.cgap-scheduled-item {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 16px;
    background: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #e1e5e9;
}

.cgap-scheduled-content {
    flex: 1;
    min-width: 0;
}

.cgap-scheduled-content h4 {
    margin: 0 0 8px 0;
    font-size: 16px;
    color: #1e293b;
}

.cgap-scheduled-meta {
    display: flex;
    gap: 16px;
    margin-bottom: 8px;
    font-size: 12px;
    color: #64748b;
}

.cgap-keywords {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}

.cgap-keyword-tag {
    display: inline-block;
    padding: 2px 8px;
    background: #e5e7eb;
    border-radius: 12px;
    font-size: 11px;
    color: #374151;
}

.cgap-scheduled-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.cgap-status {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 500;
    text-transform: uppercase;
}

.cgap-status-active {
    background: #dcfce7;
    color: #166534;
}

.cgap-status-paused {
    background: #fef3c7;
    color: #92400e;
}

.cgap-status-failed {
    background: #fee2e2;
    color: #991b1b;
}

.cgap-cron-status {
    margin-top: 20px;
    padding: 15px;
    background: #f0f9ff;
    border: 1px solid #0ea5e9;
    border-radius: 6px;
}

.cgap-cron-status h4 {
    margin: 0 0 10px 0;
    color: #0c4a6e;
}

.cgap-cron-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 10px;
}

.cgap-cron-item {
    text-align: center;
    padding: 10px;
    background: #fff;
    border-radius: 4px;
}

.cgap-cron-item strong {
    display: block;
    font-size: 16px;
    color: #1e293b;
}

.cgap-cron-item span {
    font-size: 12px;
    color: #64748b;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Add new schedule
    $('#cgap-scheduler-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $button = $form.find('button[type="submit"]');
        var originalText = $button.text();
        
        $button.text('Adding...').prop('disabled', true);
        
        $.ajax({
            url: cgap_ajax.ajax_url,
            type: 'POST',
            data: $form.serialize() + '&action=cgap_add_schedule',
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + (response.data || 'Unknown error'));
                }
            },
            complete: function() {
                $button.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // Toggle schedule status
    $('.cgap-toggle-schedule').on('click', function() {
        var $button = $(this);
        var postId = $button.data('id');
        var currentStatus = $button.data('status');
        var newStatus = currentStatus === 'active' ? 'paused' : 'active';
        
        $button.prop('disabled', true).text('Updating...');
        
        $.ajax({
            url: cgap_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'cgap_toggle_schedule',
                post_id: postId,
                nonce: $('#cgap_nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    // Update button and status without full page reload
                    $button.data('status', response.data.new_status);
                    $button.text(response.data.new_status === 'active' ? 'Pause' : 'Resume');
                    
                    // Update status badge
                    var $status = $button.siblings('.cgap-status');
                    $status.removeClass('cgap-status-active cgap-status-paused cgap-status-failed');
                    $status.addClass('cgap-status-' + response.data.new_status);
                    $status.text(response.data.new_status.charAt(0).toUpperCase() + response.data.new_status.slice(1));
                    
                    // Show success message
                    $('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>')
                        .prependTo('.wrap')
                        .delay(3000)
                        .fadeOut();
                } else {
                    alert('Error: ' + (response.data || 'Unknown error'));
                }
            },
            error: function() {
                alert('Request failed. Please try again.');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });
    
    // Delete schedule
    $('.cgap-delete-scheduled').on('click', function() {
        if (!confirm('Are you sure you want to delete this schedule?')) {
            return;
        }
        
        var $button = $(this);
        var postId = $button.data('id');
        
        $.ajax({
            url: cgap_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'cgap_delete_schedule',
                post_id: postId,
                nonce: $('#cgap_nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                }
            }
        });
    });
    
    // Add cron status check
    function checkCronStatus() {
        $.ajax({
            url: cgap_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'cgap_check_cron_status',
                nonce: $('#cgap_nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    updateCronStatus(response.data);
                }
            }
        });
    }
    
    function updateCronStatus(data) {
        var cronHtml = '<div class="cgap-cron-status">';
        cronHtml += '<h4>Cron Job Status</h4>';
        cronHtml += '<div class="cgap-cron-info">';
        cronHtml += '<div class="cgap-cron-item">';
        cronHtml += '<strong>' + (data.next_scheduled ? 'Active' : 'Inactive') + '</strong>';
        cronHtml += '<span>Status</span>';
        cronHtml += '</div>';
        if (data.next_scheduled) {
            cronHtml += '<div class="cgap-cron-item">';
            cronHtml += '<strong>' + data.next_scheduled + '</strong>';
            cronHtml += '<span>Next Run</span>';
            cronHtml += '</div>';
        }
        cronHtml += '</div></div>';
        
        $('.cgap-card:last').after(cronHtml);
    }
    
    // Check cron status on page load
    checkCronStatus();
});
</script>