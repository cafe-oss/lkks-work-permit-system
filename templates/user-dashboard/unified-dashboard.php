<?php
/**
 * Corrected Unified Dashboard Template for Approvers and Reviewers
 * File: templates/user-dashboard/unified-dashboard.php
 */

if (!defined('ABSPATH')) {
    exit;
}

// Determine dashboard type based on user capabilities or passed parameter
$dashboard_type = $dashboard_type ?? 'reviewer'; // Default to reviewer
$is_approver = ($dashboard_type === 'approver');
$is_reviewer = ($dashboard_type === 'reviewer');

// Set role-specific configuration
$config = array(
    'approver' => array(
        'page_title' => __('Approver Dashboard', 'work-permit-system'),
        'welcome_message' => __('WELCOME BACK, %s!', 'work-permit-system'),
        'intro_text' => __('Permit Management Dashboard', 'work-permit-system'),
        'action_button_text' => __('Approve Permit', 'work-permit-system'),
        'action_button_class' => 'approve-permit-btn',
        'modal_title' => __('Approve Permit', 'work-permit-system'),
        'modal_id' => 'approver-modal',
        'submit_button_text' => __('Submit', 'work-permit-system'),
        'submit_button_id' => 'submit-approval',
        'status_field_id' => 'approver-status',
        'comment_field_id' => 'approver-comment',
        'form_id' => 'approver-form',
        'actionable_status' => 'pending_approval',
        'ajax_action_get' => 'wps_get_permit_for_approval',
        'ajax_action_submit' => 'wps_approver_submit_decision',
        'ajax_action_attachments' => 'wps_get_approver_permit_attachments',
        'css_class' => 'approver-dashboard',
        'body_class' => 'toplevel_page_wps-approver-dashboard',
        'urgent_threshold_days' => 1,
        'urgent_message' => __('Urgent - pending approval for over 1 day', 'work-permit-system'),
        'columns' => array(
            __('Tenant', 'work-permit-system'),
            __('Work Category', 'work-permit-system'),
            __('Reviewer', 'work-permit-system'),
            __('Date Submitted', 'work-permit-system'),
            __('Status', 'work-permit-system'),
            __('Actions', 'work-permit-system')
        ),
        'status_options' => array(
            'approved' => __('Approve', 'work-permit-system'),
            'cancelled' => __('Reject', 'work-permit-system')
        )
    ),
    'reviewer' => array(
        'page_title' => __('Reviewer Dashboard', 'work-permit-system'),
        'welcome_message' => __('WELCOME BACK, %s!', 'work-permit-system'),
        'intro_text' => __('Permit Management Dashboard', 'work-permit-system'),
        'action_button_text' => __('Review Permit', 'work-permit-system'),
        'action_button_class' => 'review-permit-btn',
        'modal_title' => __('Review Permit', 'work-permit-system'),
        'modal_id' => 'reviewer-modal',
        'submit_button_text' => __('Submit', 'work-permit-system'),
        'submit_button_id' => 'submit-review',
        'status_field_id' => 'reviewer-status',
        'comment_field_id' => 'reviewer-comment',
        'form_id' => 'reviewer-form',
        'actionable_status' => 'pending_review',
        'ajax_action_get' => 'wps_get_permit_for_review',
        'ajax_action_submit' => 'wps_reviewer_submit_decision',
        'ajax_action_attachments' => 'wps_get_permit_attachments',
        'css_class' => 'reviewer-dashboard',
        'body_class' => 'toplevel_page_wps-reviewer-dashboard',
        'urgent_threshold_days' => 2,
        'urgent_message' => __('Urgent - submitted over 2 days ago', 'work-permit-system'),
        'columns' => array(
            __('Tenant', 'work-permit-system'),
            __('Work Category', 'work-permit-system'),
            __('Date Submitted', 'work-permit-system'),
            __('Status', 'work-permit-system'),
            __('Actions', 'work-permit-system')
        ),
        'status_options' => array(
            'pending_approval' => __('Approve', 'work-permit-system'),
            'cancelled' => __('Reject', 'work-permit-system')
        )
    )
);

$current_config = $config[$dashboard_type];

// Status labels mapping (shared between both types)
$status_labels = array(
    'pending_review' => __('Pending Review', 'work-permit-system'),
    'pending_approval' => __('Pending Approval', 'work-permit-system'),
    'approved' => __('Approved', 'work-permit-system'),
    'cancelled' => __('Rejected', 'work-permit-system')
);

// Status classes mapping (shared)
$status_classes = array(
    'pending_review' => 'status-pending-review',
    'pending_approval' => 'status-pending-approval',
    'approved' => 'status-approved',
    'cancelled' => 'status-cancelled'
);

?>

<div class="<?php echo esc_attr($current_config['css_class']); ?>" data-dashboard-type="<?php echo esc_attr($dashboard_type); ?>">
    <!-- Header Section -->
    <section class="dashboard__header">
        <div class="dashboard__intro">  
            <h2><?php echo esc_html($current_config['intro_text']); ?></h2>
            <div class="dashboard__stats">
                <div class="stat-card total-assigned">
                    <div class="stat-label"><?php esc_html_e('Total Assigned: ', 'work-permit-system'); ?></div>
                    <div class="stat-number"><?php echo esc_html($stats['total_assigned'] ?? 0); ?></div>
                </div>
                <?php if ($is_approver): ?>
                    <div class="stat-card pending-approval">
                        <div class="stat-label"><?php esc_html_e('Pending: ', 'work-permit-system'); ?></div>
                        <div class="stat-number"><?php echo esc_html($stats['pending_approval'] ?? 0); ?></div>
                    </div>
                <?php else: ?>
                    <div class="stat-card pending-review">
                        <div class="stat-label"><?php esc_html_e('Pending: ', 'work-permit-system'); ?></div>
                        <div class="stat-number"><?php echo esc_html($stats['pending_review'] ?? 0); ?></div>
                    </div>
                <?php endif; ?>
                <div class="stat-card approved">
                    <div class="stat-label"><?php esc_html_e('Approved: ', 'work-permit-system'); ?></div>
                    <div class="stat-number"><?php echo esc_html($stats['approved'] ?? $stats['completed'] ?? 0); ?></div>
                </div>
                <div class="stat-card rejected">
                    <div class="stat-label"><?php esc_html_e('Rejected: ', 'work-permit-system'); ?></div>
                    <div class="stat-number"><?php echo esc_html($stats['rejected'] ?? 0); ?></div>
                </div>
            </div>
        </div>

        <div class="dashboard__welcome">
            <h1><?php printf($current_config['welcome_message'], esc_html($current_user->display_name)); ?></h1>
        </div>
    </section>

    <!-- Search and Filter Section -->
    <?php 
    // CORRECTED: Don't set dashboard_context for reviewer/approver
    // This will use the dashboard_type instead
    include(WPS_PLUGIN_PATH . 'templates/partials/unified-dashboard-filters.php'); 
    ?>
    
    <!-- Data Grid Section -->
    <section class="dashboard__data-grid">
        <!-- Table Header -->
        <div class="data-grid__row data-grid__row--header">
            <?php foreach ($current_config['columns'] as $column): ?>
                <div class="data-grid__cell"><h3><?php echo esc_html($column); ?></h3></div>
            <?php endforeach; ?>
        </div>

        <!-- Table Data -->
        <div class="data-grid__row data-grid__row--data">
            <div class="permits-container">
                <?php if (empty($all_permits)): ?>
                    <?php include(WPS_PLUGIN_PATH . 'templates/partials/no-permits-message.php'); ?>
                <?php else: ?>
                    <?php foreach ($all_permits as $index => $permit): ?>
                        <?php 
                        $display_status = $permit->status;
                        $status_class = $status_classes[$display_status] ?? 'status-' . str_replace('_', '-', $display_status);
                        $is_last_item = ($index === count($all_permits) - 1);
                        $status_label = $status_labels[$display_status] ?? ucwords(str_replace('_', ' ', $display_status));
                        $is_actionable = $permit->status === $current_config['actionable_status'];
                        
                        // Check if permit is urgent
                        $date_field = $is_approver ? 'updated_date' : 'submitted_date';
                        $days_ago = (time() - strtotime($permit->$date_field)) / (24 * 60 * 60);
                        $is_urgent = $is_actionable && $days_ago > $current_config['urgent_threshold_days'];
                        
                        if ($is_approver) {
                            // For approvers: show View Details only for permits that have been approved or rejected by approver
                            $show_details = in_array($permit->status, ['approved', 'cancelled']);
                        } else {
                            // For reviewers: show View Details only for permits that have been reviewed (sent to approver, approved, or rejected)
                            $show_details = in_array($permit->status, ['pending_approval', 'approved', 'cancelled']);
                        }
                        ?>
                        <div class="permit-card grid__row permit-summary <?php echo $is_last_item ? 'no-border-bottom' : ''; ?> <?php echo $is_urgent ? 'urgent' : ''; ?>" 
                             data-permit-id="<?php echo esc_attr($permit->id); ?>" 
                             data-status="<?php echo esc_attr($display_status); ?>">
                            
                            <?php if ($is_urgent): ?>
                                <div class="urgent-indicator" title="<?php echo esc_attr($current_config['urgent_message']); ?>">⚠️</div>
                            <?php endif; ?>

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

                            <?php if ($is_approver): ?>
                                <!-- Reviewer Column (only for approvers) -->
                                <div class="data-grid__cell">
                                    <span><?php echo esc_html($permit->reviewer_name ?? 'N/A'); ?></span>
                                </div>
                            <?php endif; ?>

                            <!-- Submitted Date Column -->
                            <div class="data-grid__cell">
                                <?php echo nl2br(esc_html(wps_format_date_multiline($permit->submitted_date ?? 'N/A'))); ?>
                            </div>
                            

                            <!-- Status Column -->
                            <div class="data-grid__cell">
                                <span class="status-badge <?php echo esc_attr($status_class); ?>">
                                    <?php echo esc_html($status_label); ?>
                                </span>
                            </div>

                            <!-- Actions Column -->
                            <div class="data-grid__cell permit-actions">
                                <?php if ($is_actionable): ?>
                                    <button type="button" 
                                            class="button button-primary <?php echo esc_attr($current_config['action_button_class']); ?>" 
                                            data-permit-id="<?php echo esc_attr($permit->id); ?>">
                                        <?php echo esc_html($current_config['action_button_text']); ?>
                                    </button>
                                <?php endif; ?>

                                <?php if ($show_details && (!$is_actionable || !$is_reviewer)): ?>
                                    <button type="button" 
                                            class="button view-details-btn" 
                                            data-permit-id="<?php echo esc_attr($permit->id); ?>"
                                            <?php
                                            // Add all necessary data attributes for the modal
                                            $data_attrs = array(
                                                'email', 'tenant', 'issued-to', 'requester-position', 'requestor-type',
                                                'work-area', 'category-name', 'other-specification', 'personnel',
                                                'work-description', 'start-date', 'start-time', 'end-date', 'end-time',
                                                'status', 'submitted', 'reviewer'
                                            );
                                            foreach ($data_attrs as $attr) {
                                                $field_name = str_replace('-', '_', $attr);
                                                $value = '';
                                                switch ($field_name) {
                                                    case 'email': $value = $permit->email_address ?? ''; break;
                                                    case 'tenant': $value = $permit->tenant ?? ''; break;
                                                    case 'issued_to': $value = $permit->issued_to ?? ''; break;
                                                    case 'requester_position': $value = $permit->requester_position ?? ''; break;
                                                    case 'requestor_type': $value = $permit->requestor_type ?? ''; break;
                                                    case 'work_area': $value = $permit->work_area ?? ''; break;
                                                    case 'category_name': $value = $permit->category_name ?? ''; break;
                                                    case 'other_specification': $value = $permit->other_specification ?? ''; break;
                                                    case 'personnel': $value = $permit->personnel_list ?? ''; break;
                                                    case 'work_description': $value = $permit->tenant_field ?? ''; break;
                                                    case 'start_date': $value = $permit->requested_start_date ?? ''; break;
                                                    case 'start_time': $value = $permit->requested_start_time ?? ''; break;
                                                    case 'end_date': $value = $permit->requested_end_date ?? ''; break;
                                                    case 'end_time': $value = $permit->requested_end_time ?? ''; break;
                                                    case 'status': $value = $display_status; break;
                                                    case 'submitted': $value = wps_format_date($permit->submitted_date); break;
                                                    case 'reviewer': $value = $permit->reviewer_name ?? 'N/A'; break;
                                                }
                                                echo 'data-' . esc_attr($attr) . '="' . esc_attr($value) . '" ';
                                            }
                                            ?>>
                                        <?php esc_html_e('View Details', 'work-permit-system'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Pagination -->
        <?php if (($pagination_info['total_pages'] ?? 0) > 1): ?>
            <?php include(WPS_PLUGIN_PATH . 'templates/partials/unified-dashboard-pagination.php'); ?>
        <?php endif; ?>
    </section>
</div>

<!-- Action Modal (Review/Approve) -->
<?php include(WPS_PLUGIN_PATH . 'templates/partials/dashboard-action-modal.php'); ?>

<!-- View Details Modal -->
<?php include(WPS_PLUGIN_PATH . 'templates/partials/dashboard-details-modal.php'); ?>

<!-- View PDF Modal -->
<?php include(WPS_PLUGIN_PATH . 'templates/partials/dashboard-pdf-modal.php'); ?>

<!-- Initialize JavaScript -->
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function() {
    // Initialize the main dashboard system
    if (typeof WPS_Dashboard !== 'undefined') {
        WPS_Dashboard.init({
            dashboardType: '<?php echo esc_js($dashboard_type); ?>',
            config: <?php echo wp_json_encode($current_config); ?>,
            ajaxUrl: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
            nonce: '<?php echo wp_create_nonce('wps_user_action'); ?>'
        });
    }
    
    // Initialize the unified view modal with the same configuration
    if (typeof WPS_UnifiedViewModal !== 'undefined') {
        WPS_UnifiedViewModal.init({
            userType: '<?php echo esc_js($dashboard_type); ?>',
            ajaxUrl: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
            nonce: '<?php echo wp_create_nonce('wps_user_action'); ?>'
        });
    }
});
</script>