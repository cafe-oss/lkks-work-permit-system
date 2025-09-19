<?php
/**
 * Unified Dashboard Filters Partial
 * File: templates/partials/unified-dashboard-filters.php
 * Works for both admin and reviewer/approver dashboards
 */

if (!defined('ABSPATH')) {
    exit;
}

// Determine dashboard context
$is_admin_dashboard = (isset($dashboard_context) && $dashboard_context === 'admin');
$is_approver = (isset($dashboard_type) && $dashboard_type === 'approver');
$is_reviewer = (isset($dashboard_type) && $dashboard_type === 'reviewer');

// Set form action and page parameter based on context
if ($is_admin_dashboard) {
    $form_action = '';
    $page_param = 'work-permits';
    $tab_param = 'dashboard';
} else {
    $form_action = '';
    $page_param = $is_approver ? 'wps-approver-dashboard' : 'wps-reviewer-dashboard';
    $tab_param = null;
}

// Status options based on context
if ($is_admin_dashboard) {
    $status_options = array(
        '' => __('All Statuses', 'work-permit-system'),
        'pending_review' => __('Pending Review', 'work-permit-system'),
        'pending_approval' => __('Pending Approval', 'work-permit-system'),
        'approved' => __('Approved', 'work-permit-system'),
        'cancelled' => __('Rejected', 'work-permit-system')
    );
} elseif ($is_approver) {
    $status_options = array(
        'all' => __('All Status', 'work-permit-system'),
        'pending_approval' => __('Pending Approval', 'work-permit-system'),
        'approved' => __('Approved', 'work-permit-system'),
        'cancelled' => __('Rejected', 'work-permit-system')
    );
} else {
    $status_options = array(
        'all' => __('All Status', 'work-permit-system'),
        'pending_review' => __('Pending Review', 'work-permit-system'),
        'pending_approval' => __('Pending Approval', 'work-permit-system'),
        'approved' => __('Approved', 'work-permit-system'),
        'cancelled' => __('Rejected', 'work-permit-system')
    );
}

// Get current filter values
$current_status = $filters['status'] ?? ($_GET['status'] ?? ($_GET['status_filter'] ?? ''));
$current_search = $filters['search'] ?? ($_GET['search'] ?? '');
$current_search_type = $filters['search_type'] ?? ($_GET['search_type'] ?? 'all');
$current_category = $filters['work_category'] ?? ($_GET['category'] ?? ($_GET['work_category'] ?? ''));
$current_reviewer = $filters['reviewer'] ?? ($_GET['reviewer'] ?? '');
$current_date_from = $filters['date_from'] ?? ($_GET['date_from'] ?? '');
$current_date_to = $filters['date_to'] ?? ($_GET['date_to'] ?? '');

// Check if filters are active
$has_active_filters = !empty($current_search) || 
                     (!empty($current_status) && $current_status !== 'all') || 
                     (!empty($current_category) && $current_category !== 'all') ||
                     (!empty($current_reviewer) && $current_reviewer !== 'all') ||
                     !empty($current_date_from) || 
                     !empty($current_date_to);
?>

<div class="filters-section">
    <form method="get" action="<?php echo esc_attr($form_action); ?>" class="filters-form" id="<?php echo $is_admin_dashboard ? 'filters-form' : 'search-filter-form'; ?>">
        <!-- Hidden fields -->
        <input type="hidden" name="page" value="<?php echo esc_attr($page_param); ?>">
        <?php if ($tab_param): ?>
            <input type="hidden" name="tab" value="<?php echo esc_attr($tab_param); ?>">
        <?php endif; ?>
        <input type="hidden" name="paged" value="1">

        <!-- Main Search Bar -->
        <div class="filter-row">
            <div class="search-container">
                <div class="search-icon-wrapper">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="search-icon" aria-hidden="true">
                        <path d="m21 21-4.34-4.34"></path>
                        <circle cx="11" cy="11" r="8"></circle>
                    </svg>
                </div>
                
                <div class="<?php echo $is_admin_dashboard ? 'search-input-wrapper' : 'search-input-wrapper'; ?>">
                    <input type="text" 
                           id="search-input" 
                           name="search" 
                           value="<?php echo esc_attr($current_search); ?>"
                           placeholder="<?php echo $is_admin_dashboard ? 
                               esc_attr__('Search permits by ID, applicant name, or description...', 'work-permit-system') : 
                               esc_attr__('Search permits...', 'work-permit-system'); ?>"
                           class="search-input"
                           autocomplete="off">
                </div>
                
                <button type="submit" class="search-button">
                    <span class="dashicons dashicons-search"></span>
                    <?php esc_html_e('Search', 'work-permit-system'); ?>
                </button>

                <button type="button" class="filter-button">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="filter-icon" aria-hidden="true">
                        <line x1="21" x2="14" y1="4" y2="4"></line>
                        <line x1="10" x2="3" y1="4" y2="4"></line>
                        <line x1="21" x2="12" y1="12" y2="12"></line>
                        <line x1="8" x2="3" y1="12" y2="12"></line>
                        <line x1="21" x2="16" y1="20" y2="20"></line>
                        <line x1="12" x2="3" y1="20" y2="20"></line>
                        <line x1="14" x2="14" y1="2" y2="6"></line>
                        <line x1="8" x2="8" y1="10" y2="14"></line>
                        <line x1="16" x2="16" y1="18" y2="22"></line>
                    </svg>
                    <?php esc_html_e('Show Filters', 'work-permit-system'); ?>
                </button>
            </div>
        </div>

        <!-- Advanced Filters -->
        <div class="filter-row">
            <div class="filter-container">
                <div class="filter-grid">
                    
                    <select id="search-type" name="search_type" class="search-type-select">
                        <?php if (!empty($filter_options['search_types'])): ?>
                            <?php foreach ($filter_options['search_types'] as $value => $label): ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($current_search_type, $value); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="all" <?php selected($current_search_type, 'all'); ?>><?php esc_html_e('All Fields', 'work-permit-system'); ?></option>
                            <option value="name" <?php selected($current_search_type, 'name'); ?>><?php esc_html_e('Name/Tenant', 'work-permit-system'); ?></option>
                            <option value="email" <?php selected($current_search_type, 'email'); ?>><?php esc_html_e('Email Address', 'work-permit-system'); ?></option>
                            <option value="work_type" <?php selected($current_search_type, 'work_type'); ?>><?php esc_html_e('Work Type', 'work-permit-system'); ?></option>
                        <?php endif; ?>
                    </select>

                    <!-- Status Filter -->
                    <div>
                        <label class="filter-label" for="status-filter"><?php esc_html_e('Status:', 'work-permit-system'); ?></label>
                        <select name="<?php echo $is_admin_dashboard ? 'status' : 'status_filter'; ?>" id="status-filter">
                            <?php foreach ($status_options as $value => $label): ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($current_status, $value); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Date Range -->
                    <div>
                        <label class="filter-label" for="date-from"><?php esc_html_e('From:', 'work-permit-system'); ?></label>
                        <div class="date-input-container">
                            <div class="react-datepicker-wrapper">
                                <div class="react-datepicker__input-container">
                                    <input type="date" name="date_from" id="date-from" class="date-input" value="<?php echo esc_attr($current_date_from); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="filter-label" for="date-to"><?php esc_html_e('To:', 'work-permit-system'); ?></label>
                        <div class="date-input-container">
                            <div class="react-datepicker-wrapper">
                                <div class="react-datepicker__input-container">
                                    <input type="date" name="date_to" id="date-to" class="date-input" value="<?php echo esc_attr($current_date_to); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Work Categories -->
                    <div class="dropdown-container">
                        <label class="filter-label" for="category-filter"><?php esc_html_e('Category:', 'work-permit-system'); ?></label>
                        <select name="<?php echo $is_admin_dashboard ? 'category' : 'work_category'; ?>" id="category-filter" class="dropdown-button">
                            <option value=""><?php esc_html_e('All Categories', 'work-permit-system'); ?></option>
                            <?php 
                            $categories = $is_admin_dashboard ? 
                                ($categories ?? WPS_Database::get_all_categories()) : 
                                ($filter_options['work_categories'] ?? array());
                            
                            foreach ($categories as $category): 
                                $cat_id = isset($category->id) ? $category->id : $category->work_category_id;
                                $cat_name = isset($category->category_name) ? $category->category_name : $category->name;
                            ?>
                                <option value="<?php echo esc_attr($cat_id); ?>" <?php selected($current_category, $cat_id); ?>>
                                    <?php echo esc_html($cat_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Reviewer Filter (for approvers only) -->
                    <?php if ($is_approver && !empty($filter_options['reviewers'])): ?>
                    <div class="dropdown-container">
                        <label class="filter-label" for="reviewer-filter"><?php esc_html_e('Reviewer:', 'work-permit-system'); ?></label>
                        <select name="reviewer" id="reviewer-filter" class="dropdown-button">
                            <option value="all" <?php selected($current_reviewer, 'all'); ?>><?php esc_html_e('All Reviewers', 'work-permit-system'); ?></option>
                            <?php foreach ($filter_options['reviewers'] as $reviewer): ?>
                                <option value="<?php echo esc_attr($reviewer->reviewer_user_id); ?>" <?php selected($current_reviewer, $reviewer->reviewer_user_id); ?>>
                                    <?php echo esc_html($reviewer->display_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <!-- Per Page -->
                    <div>
                        <label class="filter-label" for="per-page"><?php esc_html_e('Show:', 'work-permit-system'); ?></label>
                        <select id="per-page" name="per_page">
                            <option value="5" <?php selected($pagination_info['per_page'] ?? 10, 5); ?>><?php esc_html_e('5', 'work-permit-system'); ?></option>
                            <option value="10" <?php selected($pagination_info['per_page'] ?? 10, 10); ?>><?php esc_html_e('10', 'work-permit-system'); ?></option>
                            <option value="20" <?php selected($pagination_info['per_page'] ?? 10, 20); ?>><?php esc_html_e('20', 'work-permit-system'); ?></option>
                            <option value="50" <?php selected($pagination_info['per_page'] ?? 10, 50); ?>><?php esc_html_e('50', 'work-permit-system'); ?></option>
                        </select>
                    </div>
                </div>

                <!-- Actions -->
                <div class="actions-container">
                    <?php if ($has_active_filters): ?>
                        <div class="clear-button-wrapper">
                            <a href="<?php 
                                if ($is_admin_dashboard) {
                                    echo esc_url(admin_url('admin.php?page=work-permits&tab=dashboard'));
                                } else {
                                    echo esc_url(admin_url('admin.php?page=' . $page_param));
                                }
                            ?>" class="clear-button">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20"><path fill="currentColor" d="m3.219 2.154l6.778 6.773l6.706-6.705c.457-.407.93-.164 1.119.04a.777.777 0 0 1-.044 1.035l-6.707 6.704l6.707 6.702c.298.25.298.74.059 1.014c-.24.273-.68.431-1.095.107l-6.745-6.749l-6.753 6.752c-.296.265-.784.211-1.025-.052c-.242-.264-.334-.72-.025-1.042l6.729-6.732l-6.701-6.704c-.245-.27-.33-.764 0-1.075s.822-.268.997-.068"/></svg>
                                <?php esc_html_e('Clear', 'work-permit-system'); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                    <button type="submit" class="apply-button">
                        <?php esc_html_e('Filter', 'work-permit-system'); ?>
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>