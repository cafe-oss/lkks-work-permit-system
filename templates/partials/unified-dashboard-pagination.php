<?php
/**
 * Truly Unified Dashboard Pagination Partial
 * File: templates/partials/unified-dashboard-pagination.php
 * SAME HTML structure and CSS classes for ALL dashboard types
 */

if (!defined('ABSPATH')) {
    exit;
}

// Determine dashboard context
$is_admin_dashboard = (isset($dashboard_context) && $dashboard_context === 'admin');
$is_approver = (isset($dashboard_type) && $dashboard_type === 'approver');
$is_reviewer = (isset($dashboard_type) && $dashboard_type === 'reviewer');

// Get pagination data
$total_pages = $pagination_info['total_pages'] ?? $total_pages ?? 0;
$current_page = $pagination_info['current_page'] ?? $current_page ?? 1;
$total_items = $pagination_info['total_items'] ?? $total_filtered ?? 0;
$per_page = $pagination_info['per_page'] ?? 10;

// Only show pagination if there are multiple pages
if ($total_pages <= 1) {
    return;
}

// Build query args for pagination links based on dashboard type
$query_args = array();

if ($is_admin_dashboard) {
    $query_args['page'] = 'work-permits';
    $query_args['tab'] = 'dashboard';
    
    // Admin filter parameters
    if (!empty($_GET['status'])) $query_args['status'] = sanitize_text_field($_GET['status']);
    if (!empty($_GET['category'])) $query_args['category'] = sanitize_text_field($_GET['category']);
    if (!empty($_GET['search'])) $query_args['search'] = sanitize_text_field($_GET['search']);
    if (!empty($_GET['search_type'])) $query_args['search_type'] = sanitize_text_field($_GET['search_type']);
    if (!empty($_GET['date_from'])) $query_args['date_from'] = sanitize_text_field($_GET['date_from']);
    if (!empty($_GET['date_to'])) $query_args['date_to'] = sanitize_text_field($_GET['date_to']);
    if (!empty($_GET['per_page'])) $query_args['per_page'] = intval($_GET['per_page']);
} else {
    // Reviewer/Approver parameters
    $query_args['page'] = $is_approver ? 'wps-approver-dashboard' : 'wps-reviewer-dashboard';
    
    // Unified filter parameters
    if (!empty($_GET['status_filter'])) $query_args['status_filter'] = sanitize_text_field($_GET['status_filter']);
    if (!empty($_GET['search'])) $query_args['search'] = sanitize_text_field($_GET['search']);
    if (!empty($_GET['search_type'])) $query_args['search_type'] = sanitize_text_field($_GET['search_type']);
    if (!empty($_GET['work_category'])) $query_args['work_category'] = sanitize_text_field($_GET['work_category']);
    if (!empty($_GET['reviewer'])) $query_args['reviewer'] = sanitize_text_field($_GET['reviewer']);
    if (!empty($_GET['date_from'])) $query_args['date_from'] = sanitize_text_field($_GET['date_from']);
    if (!empty($_GET['date_to'])) $query_args['date_to'] = sanitize_text_field($_GET['date_to']);
    if (!empty($_GET['per_page'])) $query_args['per_page'] = intval($_GET['per_page']);
}

// Build base URL for pagination
$base_url = admin_url('admin.php');

// Generate pagination links using WordPress built-in function - SAME for all dashboards
$pagination_links = paginate_links(array(
    'base' => add_query_arg(array_merge($query_args, array('paged' => '%#%')), $base_url),
    'format' => '',
    'prev_text' => '&laquo; ' . esc_html__('Previous', 'work-permit-system'),
    'next_text' => esc_html__('Next', 'work-permit-system') . ' &raquo;',
    'current' => $current_page,
    'total' => $total_pages,
    'show_all' => false,
    'end_size' => 1,
    'mid_size' => 2,
    'prev_next' => true,
    'type' => 'array'
));

if ($pagination_links):
?>
<!-- UNIFIED PAGINATION STRUCTURE - Same for Admin, Reviewer, and Approver -->
<div class="data-grid__row data-grid__row--pagination">
    <div class="pagination-container">
        <div class="pagination-info">
            <span class="displaying-num">
                <?php printf(
                    esc_html(_n('%s item', '%s items', $total_items, 'work-permit-system')),
                    number_format_i18n($total_items)
                ); ?>
                <?php if ($total_pages > 1): ?>
                    (<?php printf(esc_html__('Page %d of %d', 'work-permit-system'), $current_page, $total_pages); ?>)
                <?php endif; ?>
            </span>
        </div>
        
        <div class="pagination-links">
            <?php foreach ($pagination_links as $link): ?>
                <?php echo $link; ?>
            <?php endforeach; ?>
        </div>
        
        <?php if ($total_pages > 5): ?>
            <div class="pagination-jump">
                <label for="page-jump"><?php esc_html_e('Go to page:', 'work-permit-system'); ?></label>
                <input type="number" id="page-jump" min="1" max="<?php echo esc_attr($total_pages); ?>" 
                       value="<?php echo esc_attr($current_page); ?>" class="small-text">
                <button type="button" class="button pagination-jump-btn">
                    <?php esc_html_e('Go', 'work-permit-system'); ?>
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Hidden data attributes for JavaScript -->
<script type="application/json" id="pagination-config">
{
    "dashboardType": "<?php echo esc_js($is_admin_dashboard ? 'admin' : ($is_approver ? 'approver' : 'reviewer')); ?>",
    "currentPage": <?php echo intval($current_page); ?>,
    "totalPages": <?php echo intval($total_pages); ?>,
    "totalItems": <?php echo intval($total_items); ?>,
    "perPage": <?php echo intval($per_page); ?>
}
</script>
<?php
endif;
?>