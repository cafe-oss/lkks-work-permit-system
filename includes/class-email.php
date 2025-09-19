<?php
/**
 * Complete FIXED Email notifications class with Professional HTML Template
 * File: includes/class-email.php
 */
if (!defined('ABSPATH')) {
    exit;
}

class WPS_Email {
    
    // Email attachment size limit (10MB total)
    private static $max_attachment_size = 10485760; // 10MB

    /**
     * Get HTML email template
     */
    private static function get_email_template($content, $subject = '') {
        $site_name = get_bloginfo('name');
        $site_url = home_url();
        $plugin_url = plugins_url('work-permit-system');
        
        return '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>' . esc_html($subject) . '</title>
            <style>
                body {
                    margin: 0;
                    padding: 0;
                    background: linear-gradient(135deg, rgba(155, 26, 26, 0.08) 0%, rgba(155, 26, 26, 0.05) 100%);
                    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
                    line-height: 1.6;
                    color: #333333;
                }
                
                .email-container {
                    max-width: 600px;
                    margin: 20px auto;
                    background: #ffffff;
                    border-radius: 12px;
                    box-shadow: 0 8px 32px rgba(155, 26, 26, 0.1);
                    overflow: hidden;
                    border: 1px solid rgba(155, 26, 26, 0.1);
                }
                
                .logo-section {
                    background: #ffffff;
                    padding: 30px 40px 20px 40px;
                    text-align: center;
                    border-bottom: 1px solid rgba(155, 26, 26, 0.1);
                }
                
                .logo-section img {
                    max-width: 200px;
                    height: auto;
                    display: block;
                    margin: 0 auto;
                }
                
                .header {
                    background: linear-gradient(135deg, #9B1A1A 0%, #7d1515 100%);
                    padding: 30px 40px;
                    text-align: center;
                    position: relative;
                    overflow: hidden;
                }
                
                .header::before {
                    content: "";
                    position: absolute;
                    top: -50%;
                    left: -50%;
                    width: 200%;
                    height: 200%;
                    background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
                    background-size: 20px 20px;
                    animation: shimmer 20s linear infinite;
                }
                
                @keyframes shimmer {
                    0% { transform: translateX(-50px) translateY(-50px); }
                    100% { transform: translateX(50px) translateY(50px); }
                }
                
                .header h1 {
                    color: #ffffff;
                    font-size: 28px;
                    font-weight: 700;
                    margin: 0;
                    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
                    position: relative;
                    z-index: 1;
                }
                
                .header p {
                    color: rgba(255, 255, 255, 0.9);
                    font-size: 16px;
                    margin: 8px 0 0 0;
                    position: relative;
                    z-index: 1;
                }
                
                .content {
                    padding: 40px;
                }
                
                .greeting {
                    font-size: 18px;
                    color: #2c2c2c;
                    font-weight: 600;
                    margin-bottom: 20px;
                }
                
                .permit-card {
                    background: linear-gradient(135deg, rgba(155, 26, 26, 0.03) 0%, rgba(155, 26, 26, 0.01) 100%);
                    border: 1px solid rgba(155, 26, 26, 0.15);
                    border-radius: 10px;
                    padding: 25px;
                    margin: 25px 0;
                    position: relative;
                }
                
                .permit-card::before {
                    content: "";
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 4px;
                    height: 100%;
                    background: linear-gradient(135deg, #9B1A1A 0%, #7d1515 100%);
                    border-radius: 2px 0 0 2px;
                }
                
                .permit-id {
                    font-size: 20px;
                    font-weight: 700;
                    color: #9B1A1A;
                    margin-bottom: 15px;
                    display: inline-block;
                    background: rgba(155, 26, 26, 0.08);
                    padding: 8px 16px;
                    border-radius: 25px;
                    border: 1px solid rgba(155, 26, 26, 0.2);
                }
                
                .permit-details {
                    display: grid;
                    gap: 12px;
                    margin-top: 20px;
                }
                
                .detail-row {
                    display: flex;
                    padding: 8px 0;
                    border-bottom: 1px solid rgba(155, 26, 26, 0.08);
                }
                
                .detail-label {
                    font-weight: 600;
                    color: #9B1A1A;
                    width: 140px;
                    flex-shrink: 0;
                }
                
                .detail-value {
                    color: #333333;
                    flex: 1;
                }
                
                .section-title {
                    font-size: 18px;
                    font-weight: 700;
                    color: #9B1A1A;
                    margin: 30px 0 15px 0;
                    padding-bottom: 8px;
                    border-bottom: 2px solid rgba(155, 26, 26, 0.2);
                }
                
                .comment-box {
                    background: rgba(155, 26, 26, 0.05);
                    border-left: 4px solid #9B1A1A;
                    padding: 20px;
                    margin: 20px 0;
                    border-radius: 0 8px 8px 0;
                    font-style: italic;
                }
                
                .action-steps {
                    background: #f8f9fa;
                    border: 1px solid #e9ecef;
                    border-radius: 8px;
                    padding: 25px;
                    margin: 25px 0;
                }
                
                .action-steps h3 {
                    color: #9B1A1A;
                    font-size: 18px;
                    margin-top: 0;
                    font-weight: 700;
                }
                
                .action-steps ul {
                    margin: 15px 0;
                    padding-left: 20px;
                }
                
                .action-steps li {
                    margin: 10px 0;
                    color: #495057;
                }
                
                .cta-button {
                    display: inline-block;
                    background: linear-gradient(135deg, #9B1A1A 0%, #7d1515 100%);
                    color: #ffffff !important;
                    padding: 15px 35px;
                    text-decoration: none !important;
                    border-radius: 50px;
                    font-weight: 600;
                    font-size: 16px;
                    text-align: center;
                    margin: 20px 0;
                    box-shadow: 0 4px 15px rgba(155, 26, 26, 0.3);
                    transition: all 0.3s ease;
                    border: none;
                }
                
                .cta-button:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 6px 20px rgba(155, 26, 26, 0.4);
                }
                
                .attachments-list {
                    background: rgba(248, 249, 250, 0.8);
                    border: 1px solid #dee2e6;
                    border-radius: 8px;
                    padding: 20px;
                    margin: 20px 0;
                }
                
                .attachments-list h4 {
                    color: #9B1A1A;
                    margin-top: 0;
                    font-size: 16px;
                    font-weight: 600;
                }
                
                .attachments-list ul {
                    margin: 10px 0 0 0;
                    padding-left: 20px;
                }
                
                .attachments-list li {
                    margin: 8px 0;
                    color: #495057;
                }
                
                .footer {
                    background: #2c2c2c;
                    padding: 30px 40px;
                    text-align: center;
                    color: #ffffff;
                }
                
                .footer-brand {
                    font-size: 20px;
                    font-weight: 700;
                    color: #9B1A1A;
                    margin-bottom: 10px;
                }
                
                .footer-text {
                    font-size: 14px;
                    color: #cccccc;
                    line-height: 1.5;
                    margin: 10px 0;
                }
                
                .footer-disclaimer {
                    font-size: 12px;
                    color: #999999;
                    margin-top: 20px;
                    padding-top: 20px;
                    border-top: 1px solid #444444;
                    font-style: italic;
                }
                
                .status-badge {
                    display: inline-block;
                    padding: 6px 14px;
                    border-radius: 20px;
                    font-size: 14px;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                
                .status-approved {
                    background: #d4edda;
                    color: #155724;
                    border: 1px solid #c3e6cb;
                }
                
                .status-cancelled {
                    background: #f8d7da;
                    color: #721c24;
                    border: 1px solid #f5c6cb;
                }
                
                .status-pending {
                    background: #fff3cd;
                    color: #856404;
                    border: 1px solid #ffeaa7;
                }
                
                @media (max-width: 600px) {
                    .email-container {
                        margin: 10px;
                        border-radius: 8px;
                    }
                    
                    .header, .content, .footer {
                        padding: 20px;
                    }
                    
                    .permit-details {
                        grid-template-columns: 1fr;
                    }
                    
                    .detail-row {
                        flex-direction: column;
                    }
                    
                    .detail-label {
                        width: auto;
                        margin-bottom: 5px;
                    }
                }
            </style>
        </head>
        <body>
            <div class="email-container">
                <div class="logo-section">
                    <img src="' . esc_url($plugin_url) . '/assets/logo/lkk-mall-logo-red-rgb.png" alt="LKKS Logo" />
                </div>
                
                <div class="header">
                    <h1>Limketkai Management System</h1>
                    <p>Work Permit Notification</p>
                </div>
                
                <div class="content">
                    ' . $content . '
                </div>
                
                <div class="footer">
                    <div class="footer-brand">Limketkai Management</div>
                    <div class="footer-text">
                        Professional Work Permit Management System<br>
                        Ensuring Safety, Compliance & Excellence
                    </div>
                    <div class="footer-disclaimer">
                        This is a system-generated message. Please do not reply to this email.<br>
                        For support or inquiries, please contact our management office directly.
                    </div>
                </div>
            </div>
        </body>
        </html>';
    }

    /**
     * helper method for generating filtered dashboard URLs
     */
    private static function get_filtered_dashboard_url($dashboard_page, $permit_id) {
        return add_query_arg(array(
            'page' => $dashboard_page,
            'paged' => 1,
            'search' => $permit_id,
            'search_type' => 'permit_id',
            'per_page' => 10
        ), admin_url('admin.php'));
    }

    /**
     * Send notification to reviewer with ALL documents attached
     */
    public static function send_reviewer_notification_with_docs($permit_id, $comment_data = null) {
        $permit = WPS_Database::get_permit_by_id($permit_id);
        
        if (!$permit || !$permit->reviewer_user_id) {
            error_log('WPS Email: Invalid permit or reviewer for permit ' . $permit_id);
            return false;
        }
        
        $reviewer_user = get_user_by('ID', $permit->reviewer_user_id);
        
        if (!$reviewer_user) {
            error_log('WPS Email: Reviewer user not found with ID: ' . $permit->reviewer_user_id);
            return false;
        }
        
        $subject = sprintf(__('Work Permit Review Required - Permit #%s', 'work-permit-system'), $permit->permit_id);
        
        // Build HTML content
        $content = '<div class="greeting">Dear ' . esc_html($reviewer_user->display_name) . ',</div>';
        
        $content .= '<p>A work permit has been assigned to you for review and requires your attention.</p>';
        
        // Permit card
        $content .= '<div class="permit-card">';
        $content .= '<div class="permit-id">Permit #' . esc_html($permit->permit_id) . '</div>';
        $content .= '<div class="permit-details">';
        $content .= self::build_html_permit_details($permit);
        $content .= '</div>';
        $content .= '</div>';
        
        if (!empty($permit->tenant_field)) {
            $content .= '<div class="section-title">Work Description</div>';
            $content .= '<div class="comment-box">' . nl2br(esc_html($permit->tenant_field)) . '</div>';
        }
        
        // Get ALL supporting documents
        $supporting_docs = self::get_all_documents_for_email($permit_id);
        
        if (!empty($supporting_docs)) {
            $content .= '<div class="attachments-list">';
            $content .= '<h4>📎 Supporting Documents (' . count($supporting_docs) . ' files attached)</h4>';
            $content .= '<ul>';
            foreach ($supporting_docs as $doc) {
                $doc_type_label = self::get_document_type_label($doc['document_type']);
                $content .= '<li>' . esc_html($doc_type_label) . '<strong>' . esc_html($doc['name']) . '</strong> (' . size_format($doc['size']) . ')</li>';
            }
            $content .= '</ul>';
            $content .= '</div>';
        } else {
            $content .= '<div class="attachments-list">';
            $content .= '<h4>📎 Supporting Documents</h4>';
            $content .= '<p><em>No supporting documents were provided with this permit application.</em></p>';
            $content .= '</div>';
        }
        
        // Dashboard link
        // $dashboard_url = admin_url('admin.php?page=wps-reviewer-dashboard');
        $dashboard_url = self::get_filtered_dashboard_url('wps-reviewer-dashboard', $permit->permit_id);
        $content .= '<div style="text-align: center; margin: 30px 0;">';
        $content .= '<a href="' . esc_url($dashboard_url) . '" class="cta-button">Review Permit Now</a>';
        $content .= '</div>';
        
        $content .= '<p style="margin-top: 30px;">Please login to your dashboard to review this permit and provide your assessment.</p>';
        $content .= '<p><strong>Thank you for your prompt attention to this matter.</strong></p>';
        
        $html_message = self::get_email_template($content, $subject);
        
        // Send email with attachments
        return self::send_email_with_attachments(
            $reviewer_user->user_email,
            $reviewer_user->display_name,
            $subject,
            $html_message,
            $permit_id,
            $supporting_docs
        );
    }

    /**
     * Get enhanced permit data with comments (similar to PDF export)
     */
    private static function get_permit_with_comments($permit_id) {
        $permit = WPS_Database::get_permit_by_id($permit_id);
        
        if (!$permit) {
            return false;
        }
        
        // Get all comments for this permit
        $comments = WPS_Database::get_permit_comments($permit_id, false);
        $permit->all_comments = $comments;
        
        // Get reviewer comments specifically
        $reviewer_comments = array_filter($comments, function($comment) {
            return $comment->user_type === 'reviewer';
        });
        $permit->reviewer_comments = array_values($reviewer_comments);
        
        // Get approver comments specifically
        $approver_comments = array_filter($comments, function($comment) {
            return $comment->user_type === 'approver';
        });
        $permit->approver_comments = array_values($approver_comments);
        
        // Get latest reviewer comment
        if (!empty($reviewer_comments)) {
            $permit->latest_reviewer_comment = end($reviewer_comments)->comment;
        } else {
            $permit->latest_reviewer_comment = '';
        }
        
        // Get latest approver comment
        if (!empty($approver_comments)) {
            $permit->latest_approver_comment = end($approver_comments)->comment;
        } else {
            $permit->latest_approver_comment = '';
        }
        
        return $permit;
    }

    /**
     * Build combined comments section for email (same as PDF export logic)
     */
    private static function build_combined_comments_section($permit) {
        $reviewer = trim($permit->latest_reviewer_comment ?? '');
        $approver = !empty($permit->approver_comments) ? trim($permit->approver_comments[0]->comment ?? '') : '';
        
        $combined_comments = 
            (!empty($reviewer) && !empty($approver)) ? "Reviewer: $reviewer\n\nApprover: $approver" :
            (!empty($reviewer) ? "Reviewer: $reviewer" :
            (!empty($approver) ? "Approver: $approver" : ''));
        
        return $combined_comments;
    }
    
    /**
     * Updated send_approver_notification_with_docs method with combined comments
     */
    public static function send_approver_notification_with_docs($permit_id, $comment_data = null) {
        // Get enhanced permit data with comments
        $permit = self::get_permit_with_comments($permit_id);
        
        if (!$permit || !$permit->approver_user_id) {
            error_log('WPS Email: Invalid permit or approver for permit ' . $permit_id);
            return false;
        }
        
        $approver_user = get_user_by('ID', $permit->approver_user_id);
        
        if (!$approver_user) {
            error_log('WPS Email: Approver user not found with ID: ' . $permit->approver_user_id);
            return false;
        }
        
        $subject = sprintf(__('Work Permit Approval Required - Permit #%s', 'work-permit-system'), $permit->permit_id);
        
        // Build HTML content
        $content = '<div class="greeting">Dear ' . esc_html($approver_user->display_name) . ',</div>';
        
        $content .= '<p>A work permit has been reviewed and is now ready for your final approval.</p>';
        
        // Permit card
        $content .= '<div class="permit-card">';
        $content .= '<div class="permit-id">Permit #' . esc_html($permit->permit_id) . '</div>';
        $content .= '<div class="permit-details">';
        $content .= self::build_html_permit_details($permit);
        $content .= '</div>';
        $content .= '</div>';
        
        if (!empty($permit->tenant_field)) {
            $content .= '<div class="section-title">Work Description</div>';
            $content .= '<div class="comment-box">' . nl2br(esc_html($permit->tenant_field)) . '</div>';
        }
        
        // UPDATED: Include all available comments (reviewer + any existing approver comments)
        $combined_comments = self::build_combined_comments_section($permit);
        
        if (!empty($combined_comments)) {
            $content .= '<div class="section-title">Review Comments</div>';
            $content .= '<div class="comment-box">' . nl2br(esc_html($combined_comments)) . '</div>';
        }
        
        // Get ALL supporting documents
        $supporting_docs = self::get_all_documents_for_email($permit_id);
        
        if (!empty($supporting_docs)) {
            $content .= '<div class="attachments-list">';
            $content .= '<h4>📎 Original Supporting Documents (' . count($supporting_docs) . ' files attached)</h4>';
            $content .= '<ul>';
            foreach ($supporting_docs as $doc) {
                $doc_type_label = self::get_document_type_label($doc['document_type']);
                $content .= '<li>' . esc_html($doc_type_label) . '<strong>' . esc_html($doc['name']) . '</strong> (' . size_format($doc['size']) . ')</li>';
            }
            $content .= '</ul>';
            $content .= '</div>';
        } else {
            $content .= '<div class="attachments-list">';
            $content .= '<h4>📎 Supporting Documents</h4>';
            $content .= '<p><em>No supporting documents were provided with this permit application.</em></p>';
            $content .= '</div>';
        }
        
        // Dashboard link with filtering
        $dashboard_url = self::get_filtered_dashboard_url('wps-approver-dashboard', $permit->permit_id);
        $content .= '<div style="text-align: center; margin: 30px 0;">';
        $content .= '<a href="' . esc_url($dashboard_url) . '" class="cta-button">Approve Permit Now</a>';
        $content .= '</div>';
        
        $content .= '<p style="margin-top: 30px;">Please login to your dashboard to review and approve this permit.</p>';
        $content .= '<p><strong>Thank you for your prompt attention to this matter.</strong></p>';
        
        $html_message = self::get_email_template($content, $subject);
        
        // Send email with attachments
        return self::send_email_with_attachments(
            $approver_user->user_email,
            $approver_user->display_name,
            $subject,
            $html_message,
            $permit_id,
            $supporting_docs
        );
    }
    
    /**
     * Updated send_status_notification_with_attachments method with combined comments
     */
    public static function send_status_notification_with_attachments($permit_id, $status, $comment_data = null, $include_supporting_docs = true) {
        // Get enhanced permit data with comments
        $permit = self::get_permit_with_comments($permit_id);

        if (!$permit) {
            error_log('WPS Email: Permit not found with ID: ' . $permit_id);
            return false;
        }

        // Set subject based on status
        if ($status === 'cancelled') {
            $subject = sprintf(__('Work Permit Update - Application Rejected', 'work-permit-system'));
        } else {
            $subject = sprintf(__('Work Permit Update - %s', 'work-permit-system'), ucfirst($status));
        }

        // Build HTML content
        $content = '<div class="greeting">Dear ' . esc_html($permit->tenant) . ',</div>';
        
        // Status badge
        $status_class = 'status-' . $status;
        if ($status === 'cancelled') $status_class = 'status-cancelled';
        elseif ($status === 'approved') $status_class = 'status-approved';
        else $status_class = 'status-pending';
        
        $status_text = ($status === 'cancelled') ? 'REJECTED' : strtoupper($status);
        
        $content .= '<p>Your work permit application has been processed. Please see the details below:</p>';
        
        // Permit card with status
        $content .= '<div class="permit-card">';
        $content .= '<div class="permit-id">Permit #' . esc_html($permit_id) . '<span style="margin-left: 15px;" class="status-badge ' . $status_class . '">' . $status_text . '</span></div>';
        $content .= '<div class="permit-details">';
        $content .= self::build_html_permit_details($permit);
        $content .= '</div>';
        $content .= '</div>';

        // UPDATED: Use combined comments logic (same as PDF export)
        $combined_comments = self::build_combined_comments_section($permit);
        
        if (!empty($combined_comments)) {
            $label = ($status === 'cancelled') ? 'Reason for Rejection' : 'Comments';
            $content .= '<div class="section-title">' . $label . '</div>';
            $content .= '<div class="comment-box">' . nl2br(esc_html($combined_comments)) . '</div>';
        }

        // Add status-specific guidelines
        if ($status === 'cancelled') {
            $content .= '<div class="action-steps">';
            $content .= '<h3>⚠️ NEXT STEPS REQUIRED</h3>';
            $content .= '<ul>';
            $content .= '<li><a href="' . esc_url(home_url('/sample-page/')) . '" style="color: #9B1A1A; text-decoration: none; font-weight: 600;">Create a new permit application</a></li>';
            $content .= '<li>Review and fix the issues mentioned in the rejection reason above</li>';
            $content .= '<li>Resubmit with correct information and all required documents</li>';
            $content .= '</ul>';
            $content .= '</div>';
        } elseif ($status === 'approved') {
            $content .= '<div class="action-steps">';
            $content .= '<h3>✅ PERMIT APPROVED - ACTION REQUIRED</h3>';
            $content .= '<ul>';
            $content .= '<li><strong>Print 1 hard copy</strong> of the approved permit (attached to this email)</li>';
            $content .= '<li><strong>Present the printed permit</strong> to the security guard at Limketkai Center before starting any work</li>';
            $content .= '<li>Keep the permit visible during work activities</li>';
            $content .= '</ul>';
            $content .= '</div>';
        }
        
        $content .= '<p style="margin-top: 30px;"><strong>Thank you for using our work permit system.</strong></p>';

        $html_message = self::get_email_template($content, $subject);

        // Get supporting documents if requested
        $supporting_docs = array();
        if ($include_supporting_docs) {
            $supporting_docs = self::get_all_documents_for_email($permit_id);
        }

        return self::send_email_with_attachments(
            $permit->email_address, 
            $permit->issued_to, 
            $subject, 
            $html_message, 
            $permit_id,
            $supporting_docs
        );
    }
    
    /**
     * Build HTML permit details section
     */
    private static function build_html_permit_details($permit) {
        $details = '';
        
        $fields = [
            'Tenant' => $permit->tenant,
            'Email' => $permit->email_address,
            'Work Area' => $permit->work_area,
            'Issued To' => $permit->issued_to,
            'Work Category' => (strtolower($permit->category_name) === 'others' && !empty($permit->other_specification)) 
                ? $permit->category_name . ' (' . $permit->other_specification . ')'
                : $permit->category_name,
            'Start Date' => $permit->requested_start_date . ' at ' . $permit->requested_start_time,
            'End Date' => $permit->requested_end_date . ' at ' . $permit->requested_end_time
        ];
        
        foreach ($fields as $label => $value) {
            $details .= '<div class="detail-row">';
            $details .= '<div class="detail-label">' . esc_html($label) . ':</div>';
            $details .= '<div class="detail-value">' . esc_html($value) . '</div>';
            $details .= '</div>';
        }
        
        return $details;
    }
    
    /**
     * Build permit details section (plain text version for backward compatibility)
     */
    // private static function build_permit_details($permit) {
    //     $details = sprintf(__('Applicant: %s', 'work-permit-system'), $permit->tenant) . "\n";
    //     $details .= sprintf(__('Email: %s', 'work-permit-system'), $permit->email_address) . "\n";
    //     $details .= sprintf(__('issued To: %s', 'work-permit-system'), $permit->issued_to) . "\n";
    //     $details .= sprintf(__('Work Area: %s', 'work-permit-system'), $permit->work_area) . "\n";
        
    //     // Handle work category with Others specification
    //     if (strtolower($permit->category_name) === 'others' && !empty($permit->other_specification)) {
    //         $details .= sprintf(__('Work Category: %s (%s)', 'work-permit-system'), 
    //                         $permit->category_name, $permit->other_specification) . "\n";
    //     } else {
    //         $details .= sprintf(__('Work Category: %s', 'work-permit-system'), $permit->category_name) . "\n";
    //     }
        
    //     $details .= sprintf(__('Requested Start: %s at %s', 'work-permit-system'), 
    //                     $permit->requested_start_date, $permit->requested_start_time) . "\n";
    //     $details .= sprintf(__('Requested End: %s at %s', 'work-permit-system'), 
    //                     $permit->requested_end_date, $permit->requested_end_time) . "\n\n";
        
    //     return $details;
    // }

    /**
     * Get ALL documents for email attachment with ZERO filtering
     */
    private static function get_all_documents_for_email($permit_id) {
        if (!class_exists('WPS_Document_Manager')) {
            error_log('WPS Email: Document Manager class not available');
            return array();
        }
        
        // Use the document manager to get documents
        $documents = WPS_Document_Manager::get_documents_for_email_attachment($permit_id);
        
        if (empty($documents)) {
            error_log('WPS Email: No documents found for permit ' . $permit_id);
            return array();
        }
        
        // FINAL VALIDATION: Verify each file exists and is accessible
        $valid_documents = array();
        foreach ($documents as $doc) {
            if (file_exists($doc['path']) && is_readable($doc['path']) && filesize($doc['path']) > 0) {
                $valid_documents[] = $doc;
            } else {
                error_log('WPS Email: Document file not accessible: ' . $doc['path']);
            }
        }
        
        return $valid_documents;
    }
    
    /**
     * Send email with attachments - guaranteed delivery
     */
    private static function send_email_with_attachments($email_address, $recipient_name, $subject, $html_message, $permit_id, $supporting_docs = array()) {
        $attachments = array();
        
        // Add PDF first (if available)
        $pdf_file_path = self::generate_pdf_attachment($permit_id, $email_address, 'reviewer');
        if ($pdf_file_path && file_exists($pdf_file_path)) {
            $attachments[] = $pdf_file_path;
        }
        
        // Add supporting documents
        $total_attachment_size = 0;
        
        foreach ($supporting_docs as $doc) {
            // Check size limit
            if (($total_attachment_size + $doc['size']) <= self::$max_attachment_size) {
                $attachments[] = $doc['path'];
                $total_attachment_size += $doc['size'];
            } else {
                error_log('WPS Email: Skipped document due to size limit: ' . $doc['name']);
                break;
            }
        }
        
        // Send email with HTML
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_option('wps_from_name', get_bloginfo('name')) . ' <' . get_option('wps_from_email', get_option('admin_email')) . '>'
        );
        
        $sent = wp_mail($email_address, $subject, $html_message, $headers, $attachments);
        
        // Clean up temporary PDF files
        if ($pdf_file_path && file_exists($pdf_file_path) && strpos($pdf_file_path, 'wps-temp') !== false) {
            unlink($pdf_file_path);
            error_log('WPS Email: Cleaned up temporary PDF file');
        }
        
        if ($sent) {
            // Success log
        } else {
            error_log('WPS Email: Failed to send email to ' . $email_address);
        }
        
        return $sent;
    }
    
    /**
     * Send confirmation email to applicant (WITH HTML TEMPLATE)
     */
    public static function send_applicant_confirmation($permit_id) {
        $permit = WPS_Database::get_permit_by_id($permit_id);
        
        if (!$permit) {
            error_log('WPS Email: Could not load permit for applicant confirmation: ' . $permit_id);
            return false;
        }
        
        $subject = sprintf(__('Work Permit Submission Confirmed - Permit #%d', 'work-permit-system'), $permit_id);
        
        // Build HTML content using the same template
        $content = '<div class="greeting">Dear ' . esc_html($permit->tenant) . ',</div>';
        
        $content .= '<p>Thank you for submitting your work permit application. Your submission has been received and is now under review.</p>';
        
        // Permit card with confirmed status
        $content .= '<div class="permit-card">';
        $content .= '<div class="permit-id">Permit #' . esc_html($permit_id) . '</div>';
        $content .= '<div style="margin: 15px 0;"><span class="status-badge status-pending">SUBMITTED</span></div>';
        $content .= '<div class="permit-details">';
        $content .= self::build_html_permit_details($permit);
        $content .= '</div>';
        $content .= '</div>';
        
        // Check for supporting documents
        if (class_exists('WPS_Document_Manager')) {
            $all_docs = WPS_Document_Manager::get_permit_documents($permit_id);
            $total_docs = count($all_docs);
            
            if ($total_docs > 0) {
                $content .= '<div class="attachments-list">';
                $content .= '<h4>📎 Supporting Documents Received (' . $total_docs . ' files)</h4>';
                $content .= '<ul>';
                
                foreach ($all_docs as $doc) {
                    if ($doc->document_type === 'personnel_document') {
                        $doc_label = 'Personnel Document';
                    } elseif ($doc->document_type === 'details_document') {
                        $doc_label = 'Details Document';
                    } else {
                        $doc_label = ucfirst(str_replace('_', ' ', $doc->document_type));
                    }
                    $content .= '<li>' . esc_html($doc_label) . ': <strong>' . esc_html($doc->original_filename) . '</strong></li>';
                }
                $content .= '</ul>';
                $content .= '</div>';
            } else {
                $content .= '<div class="attachments-list">';
                $content .= '<h4>📎 Supporting Documents</h4>';
                $content .= '<p><em>No supporting documents were provided with this application.</em></p>';
                $content .= '</div>';
            }
        }
        
        // Status information
        $content .= '<div class="action-steps">';
        $content .= '<h3>📋 What Happens Next?</h3>';
        $content .= '<ul>';
        $content .= '<li><strong>Current Status:</strong> Pending Review</li>';
        $content .= '<li>Your application will be reviewed by our team</li>';
        $content .= '<li>You will receive email updates as your permit progresses through the approval process</li>';
        $content .= '<li>Once approved, you will receive the official permit document via email</li>';
        $content .= '</ul>';
        $content .= '</div>';
        
        $content .= '<p style="margin-top: 30px;">We appreciate your submission and will process your application as quickly as possible.</p>';
        $content .= '<p><strong>Thank you for using our work permit system.</strong></p>';
        
        $html_message = self::get_email_template($content, $subject);
        
        // Send HTML email WITHOUT attachments
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_option('wps_from_name', get_bloginfo('name')) . ' <' . get_option('wps_from_email', get_option('admin_email')) . '>'
        );
        
        // NO ATTACHMENTS for applicant confirmation
        $sent = wp_mail($permit->email_address, $subject, $html_message, $headers);
        
        if ($sent) {
        } else {
            error_log('WPS Email: Failed to send applicant confirmation email to ' . $permit->email_address);
        }
        
        return $sent;
    }

    /**
     * Get human-readable document type label
     */
    private static function get_document_type_label($document_type) {
        $labels = array(
            'personnel_document' => 'Personnel Document',
            'details_document' => 'Details Document',
            'supporting_document' => 'Supporting Document',
            'signature' => 'Signature File',
            'attachment' => 'Attachment'
        );
        
        return isset($labels[$document_type]) ? $labels[$document_type] : ucfirst(str_replace('_', ' ', $document_type));
    }

    /**
     * Generate PDF attachment for email
     */
    private static function generate_pdf_attachment($permit_id, $email_address = null, $recipient_type = 'applicant') {
        try {
            if (!class_exists('WPS_PDF_Export') || !class_exists('TCPDF')) {
                error_log('WPS Email: PDF export not available');
                return false;
            }
            
            if (file_exists(WPS_PLUGIN_PATH . 'includes/class-pdf-database.php')) {
                require_once WPS_PLUGIN_PATH . 'includes/class-pdf-database.php';
            }
            
            $permit_data = WPS_PDF_Database::get_permit_for_pdf(intval($permit_id), $recipient_type);
            
            if (!$permit_data) {
                error_log('WPS Email: Permit not found with ID: ' . $permit_id);
                return false;
            }
            
            $upload_dir = wp_upload_dir();
            $temp_dir = $upload_dir['basedir'] . '/wps-temp';
            
            if (!file_exists($temp_dir)) {
                if (!wp_mkdir_p($temp_dir)) {
                    error_log('WPS Email: Failed to create temp directory: ' . $temp_dir);
                    return false;
                }
            }
            
            $email_for_filename = $email_address ?: $permit_data->email_address;
            $filename = sprintf(
                'work-permit-%d-%s-%s-%s.pdf',
                $permit_id,
                $recipient_type,
                sanitize_file_name($email_for_filename),
                date('Y-m-d-H-i-s')
            );
            $file_path = $temp_dir . '/' . $filename;
            
            $pdf_export = new WPS_PDF_Export();
            $generated_path = $pdf_export->generate_pdf_file_by_id($permit_id, $file_path);
            
            if ($generated_path && file_exists($generated_path)) {
                return $generated_path;
            } else {
                error_log('WPS Email: PDF generation failed for permit ' . $permit_id);
                return false;
            }
            
        } catch (Exception $e) {
            error_log('WPS Email PDF Generation Error: ' . $e->getMessage());
            return false;
        }
    }

    // Backward compatibility methods
    public static function send_status_notification($permit_id, $status) {
        return self::send_status_notification_with_attachments($permit_id, $status, null, false);
    }
    
    public static function send_status_notification_with_pdf($permit_id, $status, $comment_data = null) {
        return self::send_status_notification_with_attachments($permit_id, $status, $comment_data, true);
    }
    
    public static function send_reviewer_notification($permit_id, $comment_data = null) {
        return self::send_reviewer_notification_with_docs($permit_id, $comment_data);
    }
    
    public static function send_approver_notification($permit_id, $comment_data = null) {
        return self::send_approver_notification_with_docs($permit_id, $comment_data);
    }

    public static function get_max_attachment_size() {
        return self::$max_attachment_size;
    }

    public static function get_max_attachment_size_display() {
        return size_format(self::$max_attachment_size);
    }
}

?>