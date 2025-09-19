<?php

/**
 * PDF Content Template
 * File: templates/pdf-content.php
 * 
 * @var array $data Contains permit_info, permits, and stats arrays
 */

if (!defined('ABSPATH')) {
    exit;
}

// Include PDF styles
include WPS_PLUGIN_PATH . 'templates/pdf-exports/pdf-styles.php';

?>

<!-- Permits Table -->
<div class="header">
    <h1>Work Permits Export</h1>
    <p>Generated on <?php echo date_i18n('Y-m-d H:i:s'); ?> for <?php echo esc_html($data['permit_info']['name'] ?? 'Unknown'); ?></p>
</div>

<?php if (empty($data['permits'])): ?>
    <div style="text-align: center; padding: 50px;">
        <p style="font-size: 16px;"><?php _e('No work permits found.', 'work-permit-system'); ?></p>
    </div>
<?php else: ?>

    <!-- Summary Statistics -->
    <div style="margin-bottom: 30px; padding: 15px; background-color: #f5f5f5; border: 1px solid #ddd;">
        <h2>Summary</h2>
        <div class="field-row">
            <span class="field-label">Total Permits:</span>
            <span class="field-value"><?php echo esc_html($data['stats']['total'] ?? 0); ?></span>
        </div>
        <div class="field-row">
            <span class="field-label">Approved:</span>
            <span class="field-value"><?php echo esc_html($data['stats']['approved'] ?? 0); ?></span>
        </div>
        <div class="field-row">
            <span class="field-label">Pending:</span>
            <span class="field-value"><?php echo esc_html($data['stats']['pending'] ?? 0); ?></span>
        </div>
        <div class="field-row">
            <span class="field-label">Cancelled:</span>
            <span class="field-value"><?php echo esc_html($data['stats']['cancelled'] ?? 0); ?></span>
        </div>
    </div>

    <!-- Individual Permits -->
    <?php foreach ($data['permits'] as $index => $permit): ?>
        <div class="permit-card">
            <div class="permit-title">Work Permit #<?php echo esc_html($permit->id ?? ($index + 1)); ?></div>
            
            <!-- Basic Information -->
            <div class="field-row">
                <span class="field-label">Date Issued:</span>
                <span class="field-value"><?php echo esc_html($permit->date_issued ?? 'N/A'); ?></span>
            </div>
            
            <div class="field-row">
                <span class="field-label">Issued To:</span>
                <span class="field-value"><?php echo esc_html($permit->issued_to ?? 'N/A'); ?></span>
            </div>
            
            <div class="field-row">
                <span class="field-label">Email:</span>
                <span class="field-value"><?php echo esc_html($permit->email_address ?? 'N/A'); ?></span>
            </div>
            
            <div class="field-row">
                <span class="field-label">Tenant:</span>
                <span class="field-value"><?php echo esc_html($permit->tenant ?? 'N/A'); ?></span>
            </div>
            
            <div class="field-row">
                <span class="field-label">Work Description:</span>
                <span class="field-value"><?php echo esc_html($permit->issued_for ?? 'N/A'); ?></span>
            </div>
            
            <div class="field-row">
                <span class="field-label">Work Area:</span>
                <span class="field-value"><?php echo esc_html($permit->work_area ?? 'N/A'); ?></span>
            </div>
            
            <!-- Schedule Information -->
            <?php if (!empty($permit->requested_start_date) || !empty($permit->requested_end_date)): ?>
            <div style="margin: 15px 0; padding: 10px; background-color: #fafafa; border-left: 3px solid #0073aa;">
                <strong>Schedule:</strong><br>
                <?php if (!empty($permit->requested_start_date)): ?>
                    <div class="field-row">
                        <span class="field-label">Start:</span>
                        <span class="field-value"><?php echo esc_html($permit->requested_start_date); ?> 
                        <?php if (!empty($permit->requested_start_time)): ?>
                            at <?php echo esc_html($permit->requested_start_time); ?>
                        <?php endif; ?>
                        </span>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($permit->requested_end_date)): ?>
                    <div class="field-row">
                        <span class="field-label">End:</span>
                        <span class="field-value"><?php echo esc_html($permit->requested_end_date); ?>
                        <?php if (!empty($permit->requested_end_time)): ?>
                            at <?php echo esc_html($permit->requested_end_time); ?>
                        <?php endif; ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Personnel -->
            <?php if (!empty($permit->personnel_list)): ?>
            <div class="field-row">
                <span class="field-label">Personnel:</span>
                <span class="field-value"><?php echo nl2br(esc_html($permit->personnel_list)); ?></span>
            </div>
            <?php endif; ?>
            
            <!-- Status -->
            <div class="field-row">
                <span class="field-label">Status:</span>
                <span class="field-value status <?php echo esc_attr(strtolower($permit->status ?? 'unknown')); ?>">
                    <?php echo esc_html(ucfirst($permit->status ?? 'Unknown')); ?>
                </span>
            </div>
            
            <!-- Notes/Comments -->
            <?php if (!empty($permit->admin_field)): ?>
            <div style="margin-top: 15px; padding: 10px; background-color: #fff3cd; border: 1px solid #ffeaa7;">
                <strong>Admin Notes:</strong><br>
                <?php echo nl2br(esc_html($permit->admin_field)); ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($permit->tenant_field)): ?>
            <div style="margin-top: 10px; padding: 10px; background-color: #e8f4fd; border: 1px solid #b3d7ff;">
                <strong>Tenant Notes:</strong><br>
                <?php echo nl2br(esc_html($permit->tenant_field)); ?>
            </div>
            <?php endif; ?>
            
            <!-- Signatures Section -->
            <div class="signature-section">
                <strong>Signatures:</strong>
                <div style="display: flex; justify-content: space-between; margin-top: 10px;">
                    
                    <!-- Reviewer Signature -->
                    <div style="text-align: center; flex: 1;">
                        <?php if (!empty($permit->reviewer_signature)): ?>
                            <?php 
                            $signature_url = WPS_PLUGIN_URL . 'assets/signatures/reviewer/' . $permit->reviewer_signature;
                            ?>
                            <img src="<?php echo esc_url($signature_url); ?>" alt="Reviewer Signature" style="max-width: 120px; max-height: 60px;">
                        <?php endif; ?>
                        <div style="border-top: 1px solid #000; margin-top: 5px; padding-top: 5px;">
                            <?php echo esc_html($permit->reviewer_name ?? 'Reviewer'); ?>
                        </div>
                    </div>
                    
                    <!-- Requester Signature -->
                    <div style="text-align: center; flex: 1;">
                        <?php if (!empty($permit->approver_signatory_url)): ?>
                            <img src="<?php echo esc_url($permit->approver_signatory_url); ?>" alt="Requester Signature" style="max-width: 120px; max-height: 60px;">
                        <?php endif; ?>
                        <div style="border-top: 1px solid #000; margin-top: 5px; padding-top: 5px;">
                            <?php echo esc_html($permit->requester_position ?? 'Requester'); ?>
                        </div>
                    </div>
                    
                    <!-- Approver Signature -->
                    <div style="text-align: center; flex: 1;">
                        <?php if (!empty($permit->approved_signatory)): ?>
                            <?php 
                            $signature_url = WPS_PLUGIN_URL . 'assets/signatures/approver/' . $permit->approved_signatory;
                            ?>
                            <img src="<?php echo esc_url($signature_url); ?>" alt="Approver Signature" style="max-width: 120px; max-height: 60px;">
                        <?php endif; ?>
                        <div style="border-top: 1px solid #000; margin-top: 5px; padding-top: 5px;">
                            Approver
                        </div>
                    </div>
                    
                </div>
            </div>
            
        </div>
        
        <!-- Page break between permits (except for last one) -->
        <?php if ($index < count($data['permits']) - 1): ?>
            <div style="page-break-after: always;"></div>
        <?php endif; ?>
        
    <?php endforeach; ?>

<?php endif; ?>

