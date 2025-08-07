<?php
/**
 * Logs Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

// Pagination
$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;

// Get logs
global $wpdb;
$table_name = $wpdb->prefix . 'cgap_generation_logs';

$total_logs = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
$logs = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT %d OFFSET %d",
    $per_page,
    $offset
));

$total_pages = ceil($total_logs / $per_page);
$stats = cgap_get_generation_stats();
?>

<div class="wrap cgap-logs">
    <h1><?php _e('Generation Logs', 'chatgpt-auto-publisher'); ?></h1>
    
    <!-- Statistics -->
    <div class="cgap-stats-grid">
        <div class="cgap-stat-card">
            <div class="cgap-stat-icon">📊</div>
            <div class="cgap-stat-content">
                <h3><?php echo number_format($stats['total_generations']); ?></h3>
                <p><?php _e('Total Generations', 'chatgpt-auto-publisher'); ?></p>
            </div>
        </div>
        
        <div class="cgap-stat-card">
            <div class="cgap-stat-icon">🎯</div>
            <div class="cgap-stat-content">
                <h3><?php echo number_format($stats['total_tokens']); ?></h3>
                <p><?php _e('Total Tokens', 'chatgpt-auto-publisher'); ?></p>
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
            <div class="cgap-stat-icon">📈</div>
            <div class="cgap-stat-content">
                <h3><?php echo number_format($stats['avg_tokens']); ?></h3>
                <p><?php _e('Avg Tokens/Post', 'chatgpt-auto-publisher'); ?></p>
            </div>
        </div>
    </div>
    
    <!-- Logs Table -->
    <div class="cgap-card">
        <div class="cgap-logs-header">
            <h2><?php _e('Recent Generations', 'chatgpt-auto-publisher'); ?></h2>
            <div class="cgap-logs-actions">
                <button id="cgap-export-logs" class="button">
                    <?php _e('Export CSV', 'chatgpt-auto-publisher'); ?>
                </button>
                <button id="cgap-clear-logs" class="button button-secondary">
                    <?php _e('Clear Old Logs', 'chatgpt-auto-publisher'); ?>
                </button>
            </div>
        </div>
        
        <?php if (empty($logs)): ?>
            <p class="cgap-no-activity"><?php _e('No generation logs found.', 'chatgpt-auto-publisher'); ?></p>
        <?php else: ?>
            <div class="cgap-logs-table-wrapper">
                <table class="cgap-logs-table">
                    <thead>
                        <tr>
                            <th><?php _e('Date', 'chatgpt-auto-publisher'); ?></th>
                            <th><?php _e('Post', 'chatgpt-auto-publisher'); ?></th>
                            <th><?php _e('Model', 'chatgpt-auto-publisher'); ?></th>
                            <th><?php _e('Tokens', 'chatgpt-auto-publisher'); ?></th>
                            <th><?php _e('Cost', 'chatgpt-auto-publisher'); ?></th>
                            <th><?php _e('Status', 'chatgpt-auto-publisher'); ?></th>
                            <th><?php _e('Actions', 'chatgpt-auto-publisher'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td>
                                    <div class="cgap-log-date">
                                        <?php echo date('M j, Y', strtotime($log->created_at)); ?>
                                        <small><?php echo date('H:i:s', strtotime($log->created_at)); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($log->post_id && get_post($log->post_id)): ?>
                                        <a href="<?php echo get_edit_post_link($log->post_id); ?>" target="_blank">
                                            <?php echo esc_html(get_the_title($log->post_id)); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="cgap-no-post"><?php _e('Post not found', 'chatgpt-auto-publisher'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="cgap-model-badge cgap-model-<?php echo str_replace('.', '-', $log->model); ?>">
                                        <?php echo esc_html($log->model); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="cgap-tokens"><?php echo number_format($log->tokens_used); ?></span>
                                </td>
                                <td>
                                    <span class="cgap-cost"><?php echo cgap_format_cost($log->cost); ?></span>
                                </td>
                                <td>
                                    <span class="cgap-status cgap-status-<?php echo $log->status; ?>">
                                        <?php echo ucfirst($log->status); ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="button button-small cgap-view-log" data-id="<?php echo $log->id; ?>">
                                        <?php _e('View', 'chatgpt-auto-publisher'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="cgap-pagination">
                    <?php
                    $pagination_args = array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('&laquo; Previous'),
                        'next_text' => __('Next &raquo;'),
                        'total' => $total_pages,
                        'current' => $current_page
                    );
                    echo paginate_links($pagination_args);
                    ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Log Detail Modal -->
<div id="cgap-log-modal" class="cgap-modal" style="display: none;">
    <div class="cgap-modal-content">
        <div class="cgap-modal-header">
            <h3><?php _e('Generation Details', 'chatgpt-auto-publisher'); ?></h3>
            <button class="cgap-modal-close">&times;</button>
        </div>
        <div class="cgap-modal-body">
            <div id="cgap-log-details"></div>
        </div>
    </div>
</div>

<style>
.cgap-logs-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.cgap-logs-actions {
    display: flex;
    gap: 10px;
}

.cgap-logs-table-wrapper {
    overflow-x: auto;
}

.cgap-logs-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}

.cgap-logs-table th,
.cgap-logs-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #e1e5e9;
}

.cgap-logs-table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #374151;
}

.cgap-logs-table tr:hover {
    background: #f8f9fa;
}

.cgap-log-date {
    display: flex;
    flex-direction: column;
}

.cgap-log-date small {
    color: #64748b;
    font-size: 11px;
}

.cgap-model-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
    text-transform: uppercase;
}

.cgap-model-gpt-3-5-turbo {
    background: #dbeafe;
    color: #1e40af;
}

.cgap-model-gpt-4 {
    background: #f3e8ff;
    color: #7c3aed;
}

.cgap-model-gpt-4-turbo {
    background: #ecfdf5;
    color: #059669;
}

.cgap-tokens {
    font-family: monospace;
    font-weight: 500;
}

.cgap-cost {
    font-family: monospace;
    font-weight: 500;
    color: #059669;
}

.cgap-no-post {
    color: #64748b;
    font-style: italic;
}

.cgap-pagination {
    text-align: center;
    margin-top: 20px;
}

.cgap-pagination .page-numbers {
    display: inline-block;
    padding: 8px 12px;
    margin: 0 2px;
    text-decoration: none;
    border: 1px solid #e1e5e9;
    border-radius: 4px;
    color: #374151;
}

.cgap-pagination .page-numbers:hover,
.cgap-pagination .page-numbers.current {
    background: #3b82f6;
    color: white;
    border-color: #3b82f6;
}

/* Modal Styles */
.cgap-modal {
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
}

.cgap-modal-content {
    background-color: #fff;
    margin: 5% auto;
    padding: 0;
    border-radius: 8px;
    width: 80%;
    max-width: 800px;
    max-height: 80vh;
    overflow: hidden;
}

.cgap-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #e1e5e9;
}

.cgap-modal-header h3 {
    margin: 0;
}

.cgap-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #64748b;
}

.cgap-modal-close:hover {
    color: #374151;
}

.cgap-modal-body {
    padding: 20px;
    max-height: 60vh;
    overflow-y: auto;
}

@media (max-width: 768px) {
    .cgap-logs-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .cgap-logs-table th,
    .cgap-logs-table td {
        padding: 8px;
        font-size: 12px;
    }
    
    .cgap-modal-content {
        width: 95%;
        margin: 10% auto;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // View log details
    $('.cgap-view-log').on('click', function() {
        var logId = $(this).data('id');
        
        $.ajax({
            url: cgap_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'cgap_get_log_details',
                nonce: $('#cgap_nonce').val(),
                id: logId
            },
            success: function(response) {
                if (response.success) {
                    $('#cgap-log-details').html(response.data);
                    $('#cgap-log-modal').show();
                }
            }
        });
    });
    
    // Close modal
    $('.cgap-modal-close, .cgap-modal').on('click', function(e) {
        if (e.target === this) {
            $('#cgap-log-modal').hide();
        }
    });
    
    // Export logs
    $('#cgap-export-logs').on('click', function() {
        window.location.href = cgap_ajax.ajax_url + '?action=cgap_export_logs&nonce=' + $('#cgap_nonce').val();
    });
    
    // Clear old logs
    $('#cgap-clear-logs').on('click', function() {
        if (confirm('Are you sure you want to clear old logs? This cannot be undone.')) {
            $.ajax({
                url: cgap_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'cgap_clear_logs',
                    nonce: $('#cgap_nonce').val()
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    }
                }
            });
        }
    });
});
</script>