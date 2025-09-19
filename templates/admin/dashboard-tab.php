<?php
/**
 * Admin Dashboard Tab Content with TRUE Unified Pagination
 * File: templates/admin/dashboard-tab.php
 * FIXED: Now uses exact same pagination logic as reviewer/approver dashboards
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load the unified class
require_once WPS_PLUGIN_PATH . 'includes/class-unified-dashboard-filters.php';

// Build filters from GET parameters - FIXED: Use proper admin parameter names
$filters = array(
    'status' => $_GET['status'] ?? '',
    'search' => $_GET['search'] ?? '',
    'search_type' => $_GET['search_type'] ?? 'all',
    'category' => $_GET['category'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
);

// Get pagination parameters
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = isset($_GET['per_page']) ? max(5, min(50, intval($_GET['per_page']))) : 10;

// Get filtered and paginated data
$result = WPS_Unified_Dashboard_Filters::get_filtered_permits(
    'admin',      // Dashboard type
    null,         // No user ID for admin
    $filters,
    $current_page,
    $per_page
);

// Extract data for template
$all_permits = $result['permits'];
$pagination_info = array(
    'total_items' => $result['total_items'],
    'total_pages' => $result['total_pages'],
    'current_page' => $result['current_page'],
    'per_page' => $result['per_page']
);

// Get filter options and stats
$filter_options = WPS_Unified_Dashboard_Filters::get_filter_options('admin');
$permit_stats = WPS_Unified_Dashboard_Filters::get_dashboard_stats('admin');
$categories = $filter_options['work_categories'];

// Calculate total count for stats
$total_count = array_sum($permit_stats);

// Set dashboard context for template - CRITICAL: This is used by unified-dashboard-pagination.php
$dashboard_context = 'admin';

// Get individual filter values for backward compatibility
$status_filter = $filters['status'];
$category_filter = $filters['category'];
$date_from = $filters['date_from'];
$date_to = $filters['date_to'];
$search = $filters['search'];

// Status options
$status_options = array(
    '' => __('All Statuses', 'work-permit-system'),
    'pending_review' => __('Pending Review', 'work-permit-system'),
    'pending_approval' => __('Pending Approval', 'work-permit-system'),
    'approved' => __('Approved', 'work-permit-system'),
    'cancelled' => __('Rejected', 'work-permit-system')
);


?>

<div class="dashboard-content">
    <!-- Stats Section -->
    <div class="stats-grid">
        <div class="stat-card purple">
            <div class="stat-header">
                <div class="stat-text">
                    <p class="title"><?php esc_html_e('Total Permits', 'work-permit-system'); ?></p>
                    <h3 class="purple"><?php echo esc_html($total_count); ?></h3>
                </div>
                <div class="stat-icon-wrapper">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="stat-icon purple" aria-hidden="true">
                        <rect width="8" height="4" x="8" y="2" rx="1" ry="1"></rect>
                        <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
                    </svg>
                </div>
            </div>
        </div>

        <div class="stat-card purple">
            <div class="stat-header">
                <div class="stat-text">
                    <p class="title"><?php esc_html_e('Today\'s Submissions', 'work-permit-system'); ?></p>
                    <h3 class="purple">
                        <?php 
                        // Get today's submissions
                        global $wpdb;
                        $today_count = $wpdb->get_var("
                            SELECT COUNT(*) 
                            FROM {$wpdb->prefix}work_permits 
                            WHERE DATE(submitted_date) = CURDATE()
                        ");
                        echo esc_html($today_count ?? 0); 
                        ?>
                    </h3>
                </div>
                <div class="stat-icon-wrapper">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="stat-icon purple" aria-hidden="true">
                        <path d="M8 2v4"></path>
                        <path d="M16 2v4"></path>
                        <rect width="18" height="18" x="3" y="4" rx="2"></rect>
                        <path d="M3 10h18"></path>
                    </svg>
                </div>
            </div>
        </div>

        <div class="stat-card amber">
            <div class="stat-header">
                <div class="stat-text">
                    <p class="title"><?php esc_html_e('Pending Review', 'work-permit-system'); ?></p>
                    <h3 class="amber"><?php echo esc_html($permit_stats['pending_review'] ?? 0); ?></h3>
                    <p class="percentage">
                        <?php 
                        $pending_percentage = $total_count > 0 ? round((($permit_stats['pending_review'] ?? 0) / $total_count) * 100, 1) : 0;
                        echo esc_html($pending_percentage . '%'); 
                        ?>
                    </p>
                </div>
                <div class="stat-icon-wrapper">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="stat-icon amber" aria-hidden="true">
                        <rect width="8" height="4" x="8" y="2" rx="1" ry="1"></rect>
                        <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
                        <path d="M12 11h4"></path>
                        <path d="M12 16h4"></path>
                        <path d="M8 11h.01"></path>
                        <path d="M8 16h.01"></path>
                    </svg>
                </div>
            </div>
            <div class="progress-section">
                <div class="progress-bar">
                    <div class="progress-fill purple" style="width: <?php echo esc_attr($pending_percentage); ?>%;"></div>
                </div>
            </div>
        </div>

        <div class="stat-card indigo">
            <div class="stat-header">
                <div class="stat-text">
                    <p class="title"><?php esc_html_e('Pending Approval', 'work-permit-system'); ?></p>
                    <h3 class="indigo"><?php echo esc_html($permit_stats['pending_approval'] ?? 0); ?></h3>
                    <p class="percentage">
                        <?php 
                        $approval_percentage = $total_count > 0 ? round((($permit_stats['pending_approval'] ?? 0) / $total_count) * 100, 1) : 0;
                        echo esc_html($approval_percentage . '%'); 
                        ?>
                    </p>
                </div>
                <div class="stat-icon-wrapper">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="stat-icon indigo" aria-hidden="true">
                        <rect width="8" height="4" x="8" y="2" rx="1" ry="1"></rect>
                        <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
                        <path d="M12 11h4"></path>
                        <path d="M12 16h4"></path>
                        <path d="M8 11h.01"></path>
                        <path d="M8 16h.01"></path>
                    </svg>
                </div>
            </div>
            <div class="progress-section">
                <div class="progress-bar">
                    <div class="progress-fill purple" style="width: <?php echo esc_attr($approval_percentage); ?>%;"></div>
                </div>
            </div>
        </div>

        <div class="stat-card green">
            <div class="stat-header">
                <div class="stat-text">
                    <p class="title"><?php esc_html_e('Approved', 'work-permit-system'); ?></p>
                    <h3 class="green"><?php echo esc_html($permit_stats['approved'] ?? 0); ?></h3>
                    <p class="percentage">
                        <?php 
                        $approved_percentage = $total_count > 0 ? round((($permit_stats['approved'] ?? 0) / $total_count) * 100, 1) : 0;
                        echo esc_html($approved_percentage . '%'); 
                        ?>
                    </p>
                </div>
                <div class="stat-icon-wrapper">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="stat-icon green" aria-hidden="true">
                        <path d="M21.801 10A10 10 0 1 1 17 3.335"></path>
                        <path d="m9 11 3 3L22 4"></path>
                    </svg>
                </div>
            </div>
            <div class="progress-section">
                <div class="progress-bar">
                    <div class="progress-fill green" style="width: <?php echo esc_attr($approved_percentage); ?>%;"></div>
                </div>
            </div>
        </div>

        <div class="stat-card red">
            <div class="stat-header">
                <div class="stat-text">
                    <p class="title"><?php esc_html_e('Rejected', 'work-permit-system'); ?></p>
                    <h3 class="red"><?php echo esc_html($permit_stats['cancelled'] ?? 0); ?></h3>
                    <p class="percentage">
                        <?php 
                        $cancelled_percentage = $total_count > 0 ? round((($permit_stats['cancelled'] ?? 0) / $total_count) * 100, 1) : 0;
                        echo esc_html($cancelled_percentage . '%'); 
                        ?>
                    </p>
                </div>
                <div class="stat-icon-wrapper">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="stat-icon red" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="m15 9-6 6"></path>
                        <path d="m9 9 6 6"></path>
                    </svg>
                </div>
            </div>
            <div class="progress-section">
                <div class="progress-bar">
                    <div class="progress-fill red" style="width: <?php echo esc_attr($cancelled_percentage); ?>%;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Use Unified Filters -->
    <?php include(WPS_PLUGIN_PATH . 'templates/partials/unified-dashboard-filters.php'); ?>

    <!-- All Permits -->
    <section class="dashboard__data-grid">
        <div class="data-grid__row data-grid__row--header">
            <div class="data-grid__cell"><h3>Tenant</h3></div>
            <div class="data-grid__cell"><h3>Work Category</h3></div>
            <div class="data-grid__cell"><h3>Reviewer</h3></div>
            <div class="data-grid__cell"><h3>Approver</h3></div>
            <div class="data-grid__cell"><h3>Date Submitted</h3></div>
            <div class="data-grid__cell"><h3>Status</h3></div>
            <div class="data-grid__cell"><h3>Actions</h3></div>
        </div>

        <div class="data-grid__row data-grid__row--data">
            <div class="permits-container">
                <?php if (empty($all_permits)): ?>
                    <?php include(WPS_PLUGIN_PATH . 'templates/partials/no-permits-message.php'); ?>
                <?php else: ?>
                    <?php foreach ($all_permits as $index => $permit): ?>
                        <?php 
                        $display_status = $permit->status;
                        $status_class = 'status-' . str_replace('_', '-', $permit->status);
                        $status_label = WPS_Database::get_status_display_text($permit->status);
                        $is_last_item = ($index === count($all_permits) - 1);
                        ?>
                        <div class="permit-card grid__row permit-summary <?php echo $is_last_item ? 'no-border-bottom' : ''; ?>" 
                            data-permit-id="<?php echo esc_attr($permit->id); ?>" 
                            data-status="<?php echo esc_attr($display_status); ?>">

                            <!-- Tenant Column -->
                            <div class="data-grid__cell">
                                <div class="applicant-info">
                                    <span class="applicant-permit_id"><?php echo esc_html($permit->permit_id ?? 'N/A'); ?></span>
                                    <div class="applicant-tenant"><?php echo esc_html($permit->tenant ?? 'N/A'); ?></div>
                                    <div class="applicant-email"><?php echo esc_html($permit->email_address ?? ''); ?></div>
                                </div>
                            </div>

                            <!-- Work Category Column -->
                            <div class="data-grid__cell">
                                <span><?php echo esc_html($permit->category_name ?? 'N/A'); ?></span>
                            </div>

                            <!-- Reviewer Column -->
                            <div class="data-grid__cell">
                                <span><?php echo esc_html($permit->reviewer_name ?? 'N/A'); ?></span>
                            </div>

                            <!-- Approver Column -->
                            <div class="data-grid__cell">
                                <span><?php echo esc_html($permit->approver_name ?? 'N/A'); ?></span>
                            </div>

                            <!-- Submitted Date Column -->
                            <div class="data-grid__cell">
                                <span><?php echo nl2br(esc_html(wps_format_date_multiline($permit->submitted_date ?? 'N/A'))); ?></span>
                            </div>

                            <!-- Status Column -->
                            <div class="data-grid__cell">
                                <span class="status-badge <?php echo esc_attr($status_class); ?>">
                                    <?php echo esc_html($status_label); ?>
                                </span>
                            </div>

                            <!-- Actions Column -->
                            <div class="data-grid__cell permit-actions">
                                <button type="button" 
                                        class="button button-small view-permit" 
                                        data-permit-id="<?php echo esc_attr($permit->id); ?>"
                                        aria-label="<?php echo esc_attr(sprintf(__('View permit %d', 'work-permit-system'), $permit->id)); ?>">
                                    <?php esc_html_e('View Details', 'work-permit-system'); ?>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- FIXED: Use Exact Same Unified Pagination as Reviewer/Approver -->
        <?php if ($pagination_info['total_pages'] > 1): ?>
            <div class="data-grid__row data-grid__row--pagination">
                <?php 
                // Set required variables for unified pagination partial
                $is_admin_dashboard = true; // This will be picked up by unified-dashboard-pagination.php
                
                // Include the exact same pagination partial used by reviewer/approver dashboards
                include(WPS_PLUGIN_PATH . 'templates/partials/unified-dashboard-pagination.php'); 
                ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php 
// Include the unified modal template
$is_approver = false; // Admin view, so no approver-specific fields
include(WPS_PLUGIN_PATH . 'templates/partials/dashboard-details-modal.php'); 

include(WPS_PLUGIN_PATH . 'templates/partials/dashboard-pdf-modal.php');

?>