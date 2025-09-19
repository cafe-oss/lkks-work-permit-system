<?php
/**
 * Dashboard Action Modal Partial (Review/Approve)
 * File: templates/partials/dashboard-action-modal.php
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="<?php echo esc_attr($current_config['modal_id']); ?>" class="wps-modal" style="display: none;">
    <div class="wps-modal-content">
        <div class="wps-modal-header">
            <div class="wps-modal-title-status">
                <h3 id="modal-title"><?php echo esc_html($current_config['modal_title']); ?></h3>
                <div class="status-info">
                    <span id="modal-current-status" class="status-badge"></span>
                </div>
            </div>
            <button type="button" class="wps-modal-close" aria-label="<?php esc_attr_e('Close modal', 'work-permit-system'); ?>">&times;</button>
        </div>
        
        <form id="<?php echo esc_attr($current_config['form_id']); ?>">
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
                                        <span id="modal-tenant"></span>
                                    </div>
                                    <div class="info-item">
                                        <label><?php esc_html_e('Applicant Email:', 'work-permit-system'); ?></label>
                                        <span id="modal-email-address"></span>
                                    </div>
                                    <div class="info-item">
                                        <label><?php esc_html_e('Position:', 'work-permit-system'); ?></label>
                                        <span id="modal-requester-position"></span>
                                    </div>
                                    <div class="info-item">
                                        <label><?php esc_html_e('Issued To:', 'work-permit-system'); ?></label>
                                        <span id="modal-issued-to"></span>
                                    </div>
                                    <div class="info-item">
                                        <label><?php esc_html_e('Work Area:', 'work-permit-system'); ?></label>
                                        <span id="modal-work-area"></span>
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
                                            <span id="modal-reviewer-name"></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="info-item">
                                        <label><?php esc_html_e('Work Type:', 'work-permit-system'); ?></label>
                                        <span id="modal-requestor-type"></span>
                                    </div>
                                    <div class="info-item">
                                        <label><?php esc_html_e('Work Category:', 'work-permit-system'); ?></label>
                                        <span id="modal-category"></span>
                                    </div>
                                    <div class="info-item others-specification-item" style="display: none;">
                                        <label><?php esc_html_e('Work Specification:', 'work-permit-system'); ?></label>
                                        <span id="modal-other-specification"></span>
                                    </div>
                                    <div class="info-item">
                                        <div class="work-description">
                                            <label><?php esc_html_e('Work Description:', 'work-permit-system'); ?></label>
                                            <div id="modal-work-description"></div>
                                        </div>
                                    </div>
                                    <div class="info-item">
                                        <label><?php esc_html_e('Personnel List:', 'work-permit-system'); ?></label>
                                        <span id="modal-personnel"></span>
                                    </div>
                                    <div class="info-item">
                                        <label><?php esc_html_e('Requested Dates:', 'work-permit-system'); ?></label>
                                        <ul class="personnel-list">
                                            <li>
                                                <label><?php esc_html_e('From: ', 'work-permit-system'); ?></label>
                                                <span id="modal-start-date"></span>
                                            </li>
                                            <li>
                                                <label><?php esc_html_e('To: ', 'work-permit-system'); ?></label>
                                                <span id="modal-end-date"></span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="attachments-section"  id="attachments-section-unified">
                    <h4><?php esc_html_e('Supporting Documents', 'work-permit-system'); ?></h4>
                    <div id="view-attachments-container" class="attachments-container">
                        <div class="loading-attachments">Loading attachments...</div>
                    </div>
                </div>
                
                <div class="decision-section">
                    <h4 class="permit-section_title"><?php esc_html_e('COMMENTS:', 'work-permit-system'); ?></h4>

                    <div class="comment-field">
                        <small id="comment-help" class="help-text">
                            <?php esc_html_e('Provide comments when necessary. This will be included in email notifications.', 'work-permit-system'); ?>
                        </small>
                        <textarea id="<?php echo esc_attr($current_config['comment_field_id']); ?>" 
                                  name="comment" 
                                  rows="4" 
                                  placeholder="<?php esc_attr_e('Enter your comments here...', 'work-permit-system'); ?>"></textarea>
                    </div>
                </div>
                
                <input type="hidden" id="permit-id" name="permit_id" value="">
            </div>
            
            <div class="wps-modal-footer">
                <div class="modal-footer-left">
                    
                    <div class="decision-field">
                        <label for="<?php echo esc_attr($current_config['status_field_id']); ?>"><?php esc_html_e('Update Status:', 'work-permit-system'); ?> *</label>
                        <select id="<?php echo esc_attr($current_config['status_field_id']); ?>" name="status" required>
                            <option value=""><?php esc_html_e('Select Status', 'work-permit-system'); ?></option>
                            <?php foreach ($current_config['status_options'] as $value => $label): ?>
                                <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="modal-footer-right">
                    <button type="button" class="button wps-modal-close">
                        <?php esc_html_e('Cancel', 'work-permit-system'); ?>
                    </button>
                    <button type="submit" id="<?php echo esc_attr($current_config['submit_button_id']); ?>" class="button button-primary" disabled>
                        <?php echo esc_html($current_config['submit_button_text']); ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>