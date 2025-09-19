<?php
/**
 * No Permits Message Partial
 * File: templates/partials/no-permits-message.php
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="no-permits">
    <div class="no-permits-icon">
        <span class="dashicons dashicons-clipboard"></span>
    </div>
    
    <?php if ($filters['status'] !== 'all'): ?>
        <?php
        $status_labels = array(
            'pending_review' => __('Pending Review', 'work-permit-system'),
            'pending_approval' => __('Pending Approval', 'work-permit-system'),
            'approved' => __('Approved', 'work-permit-system'),
            'cancelled' => __('Rejected', 'work-permit-system')
        );
        $status_text = isset($status_labels[$filters['status']]) ? $status_labels[$filters['status']] : $filters['status'];
        ?>
        <h3><?php printf(esc_html__('No %s permits', 'work-permit-system'), strtolower($status_text)); ?></h3>
        <p><?php printf(esc_html__('You have no permits with status "%s" at this time.', 'work-permit-system'), $status_text); ?></p>
        <button type="button" class="button" onclick="WPS_Dashboard.clearStatusFilter();">
            <?php esc_html_e('View All Permits', 'work-permit-system'); ?>
        </button>
        
    <?php elseif ($filters['work_category'] !== 'all'): ?>
        <?php
        $selected_category = null;
        if (!empty($filter_options['work_categories'])) {
            foreach ($filter_options['work_categories'] as $cat) {
                if ($cat->id == $filters['work_category']) {
                    $selected_category = $cat->category_name;
                    break;
                }
            }
        }
        ?>
        <h3><?php esc_html_e('No permits found', 'work-permit-system'); ?></h3>
        <p><?php printf(esc_html__('No permits found for work type "%s".', 'work-permit-system'), $selected_category ?: 'Selected'); ?></p>
        <button type="button" class="button" onclick="WPS_Dashboard.clearCategoryFilter();">
            <?php esc_html_e('View All Work Types', 'work-permit-system'); ?>
        </button>
        
    <?php elseif (!empty($filters['search'])): ?>
        <h3><?php esc_html_e('No search results', 'work-permit-system'); ?></h3>
        <p><?php printf(esc_html__('No permits found matching "%s".', 'work-permit-system'), esc_html($filters['search'])); ?></p>
        <button type="button" class="button" onclick="WPS_Dashboard.clearSearch();">
            <?php esc_html_e('Clear Search', 'work-permit-system'); ?>
        </button>
        
    <?php else: ?>
        <h3><?php esc_html_e('No permits assigned', 'work-permit-system'); ?></h3>
        <p>
            <?php 
            if ($is_approver) {
                esc_html_e('You currently have no work permits assigned for final approval.', 'work-permit-system');
            } else {
                esc_html_e('You currently have no work permits assigned for review.', 'work-permit-system');
            }
            ?>
        </p>
    <?php endif; ?>
</div>