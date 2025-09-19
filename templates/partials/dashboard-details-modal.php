
<?php
/**
 * Dashboard Details Modal Partial
 * File: templates/partials/dashboard-details-modal.php
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="view-details-modal" class="wps-modal" style="display: none;">
    <div class="wps-modal-content">
        <div class="wps-modal-header">
            <div class="wps-modal-title-status">
                <h3 id="details-modal-title"></h3>
                <div class="status-info">
                    <span id="details-current-status" class="status-badge"></span>
                </div>
            </div>
            <button type="button" class="wps-modal-close" aria-label="<?php esc_attr_e('Close modal', 'work-permit-system'); ?>">&times;</button>
        </div>
        
        <div class="wps-modal-body">
            <div class="permit-info-section">
                <h4 class="permit-section_title"><?php esc_html_e('TENANT INFORMATION', 'work-permit-system'); ?></h4>
                
                <div class="info-grid">
                    <div class="permit-info-card">
                        <div class="permit-info-card-header">
                            <h5><?php esc_html_e('Permit Information', 'work-permit-system'); ?></h5>
                            <button type="button" class="chevron">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="m18 15-6-6-6 6"></path>
                                </svg>
                            </button>
                        </div>
                        <div class="permit-info-card-content">
                            <div class="card-info-grid">
                                <div class="info-item">
                                    <label><?php esc_html_e('Tenant:', 'work-permit-system'); ?></label>
                                    <span id="details-tenant"></span>
                                </div>
                                <div class="info-item">
                                    <label><?php esc_html_e('Applicant Email:', 'work-permit-system'); ?></label>
                                    <span id="details-email-address"></span>
                                </div>
                                <div class="info-item">
                                    <label><?php esc_html_e('Position:', 'work-permit-system'); ?></label>
                                    <span id="details-requester-position"></span>
                                </div>
                                <div class="info-item">
                                    <label><?php esc_html_e('Issued To:', 'work-permit-system'); ?></label>
                                    <span id="details-issued-to"></span>
                                </div>
                                <div class="info-item">
                                    <label><?php esc_html_e('Work Area:', 'work-permit-system'); ?></label>
                                    <span id="details-work-area"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="permit-info-card">
                        <div class="permit-info-card-header">
                            <h5><?php esc_html_e('Work and Personnel Details', 'work-permit-system'); ?></h5>
                            <button type="button" class="chevron">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="m18 15-6-6-6 6"></path>
                                </svg>
                            </button>
                        </div>
                        <div class="permit-info-card-content">
                            <div class="card-info-grid">
                                <?php if ($is_approver): ?>
                                    <div class="info-item">
                                        <label><?php esc_html_e('Reviewed by:', 'work-permit-system'); ?></label>
                                        <span id="details-reviewer"></span>
                                    </div>
                                <?php endif; ?>
                                <div class="info-item">
                                    <label><?php esc_html_e('Work Type:', 'work-permit-system'); ?></label>
                                    <span id="details-requestor-type"></span>
                                </div>
                                <div class="info-item">
                                    <label><?php esc_html_e('Work Category:', 'work-permit-system'); ?></label>
                                    <span id="details-category"></span>
                                </div>
                                <div class="info-item others-specification-item">
                                    <label><?php esc_html_e('Work Specification:', 'work-permit-system'); ?></label>
                                    <span id="details-other-specification"></span>
                                </div>
                                <div class="info-item">
                                    <div class="work-description">
                                        <label><?php esc_html_e('Work Description:', 'work-permit-system'); ?></label>
                                        <div id="details-work-description"></div>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <label><?php esc_html_e('Personnel List:', 'work-permit-system'); ?></label>
                                    <span id="details-personnel"></span>
                                </div>
                                <div class="info-item">
                                    <label><?php esc_html_e('Requested Dates:', 'work-permit-system'); ?></label>
                                    <ul class="personnel-list">
                                        <li>
                                            <label><?php esc_html_e('From: ', 'work-permit-system'); ?></label>
                                            <span id="details-start-date"></span>
                                        </li>
                                        <li>
                                            <label><?php esc_html_e('To: ', 'work-permit-system'); ?></label>
                                            <span id="details-end-date"></span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="attachments-section"  id="attachments-section-unified">
                <h4 class="permit-section_title"><?php esc_html_e('Supporting Documents', 'work-permit-system'); ?></h4>
                <div id="view-attachments-container" class="attachments-container">
                    <div class="loading-attachments"><?php esc_html_e('Loading attachments...', 'work-permit-system'); ?></div>
                </div>
            </div>
            
            <input type="hidden" id="details-permit-id" name="permit_id" value="">
        </div>

        
        
        <div class="wps-modal-footer">
            <!-- <button type="button" id="button print-permit">
                <?php esc_html_e('Print Permit', 'work-permit-system'); ?>
            </button> -->

            <button type="button" id="preview-permit">
                <?php esc_html_e('Preview Permit', 'work-permit-system'); ?>
            </button>

            <button type="button" class="button wps-modal-close">
                <?php esc_html_e('Close', 'work-permit-system'); ?>
            </button>
        </div>
    </div>
</div>