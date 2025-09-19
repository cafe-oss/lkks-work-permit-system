<?php
/**
 * PDF-specific database operations class
 * File: includes/class-pdf-database.php
 * Handles data retrieval for PDF generation with contextual comments
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPS_PDF_Database {
    
    /**
     * Get permit details with contextual comments for PDF generation
     * 
     * @param int $permit_id Permit ID
     * @param string $recipient_type Who will receive this PDF: 'applicant', 'reviewer', 'approver', 'admin'
     * @param int $recipient_user_id User ID of recipient (for role-specific filtering)
     * @return object|null Permit data with contextual comments
     */
    public static function get_permit_for_pdf($permit_id, $recipient_type = 'applicant', $recipient_user_id = null) {
        $permit = WPS_Database::get_permit_by_id($permit_id);
        
        if (!$permit) {
            return null;
        }
        
        // Get contextual comments based on recipient type
        $permit->contextual_comments = self::get_contextual_comments($permit_id, $recipient_type, $permit->status, $recipient_user_id);
        
        // Get workflow history for PDF
        $permit->workflow_history = self::get_workflow_history_for_pdf($permit_id, $recipient_type);
        
        // Get approval chain information
        $permit->approval_chain = self::get_approval_chain_info($permit);
        
        return $permit;
    }
    
    /**
     * Get contextual comments based on recipient and permit status
     * 
     * @param int $permit_id Permit ID
     * @param string $recipient_type Who will receive the PDF
     * @param string $permit_status Current permit status
     * @param int $recipient_user_id User ID of recipient
     * @return array Filtered and formatted comments
     */
    private static function get_contextual_comments($permit_id, $recipient_type, $permit_status, $recipient_user_id = null) {
        global $wpdb;
        $comments_table = $wpdb->prefix . 'wps_permit_comments';
        
        $comments = array();
        
        switch ($recipient_type) {
            case 'reviewer':
                // Reviewer sees: admin comments, system messages
                $comments = self::get_comments_for_reviewer($permit_id, $recipient_user_id);
                break;
                
            case 'approver':
                // Approver sees: admin comments, reviewer comments, system messages
                $comments = self::get_comments_for_approver($permit_id, $recipient_user_id);
                break;
                
            case 'applicant':
                // Applicant sees: final decision comments only, status-appropriate messages
                $comments = self::get_comments_for_applicant($permit_id, $permit_status);
                break;
                
            case 'admin':
                // Admin sees: all comments including internal ones
                $comments = self::get_all_comments($permit_id, true);
                break;
                
            default:
                $comments = array();
        }
        
        return $comments;
    }
    
    /**
     * Get comments appropriate for reviewer PDF
     */
    private static function get_comments_for_reviewer($permit_id, $reviewer_user_id) {
        global $wpdb;
        $comments_table = $wpdb->prefix . 'wps_permit_comments';
        
        // Reviewer sees: admin comments, system messages, their own previous comments
        $query = $wpdb->prepare("
            SELECT c.*, u.display_name as user_display_name
            FROM $comments_table c
            LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
            WHERE c.permit_id = %d 
            AND (
                c.user_type IN ('admin', 'system') 
                OR (c.user_type = 'reviewer' AND c.user_id = %d)
            )
            AND c.is_internal = 0
            ORDER BY c.created_date ASC
        ", $permit_id, $reviewer_user_id);
        
        $comments = $wpdb->get_results($query);
        
        return self::format_comments_for_pdf($comments, 'reviewer');
    }
    
    /**
     * Get comments appropriate for approver PDF  
     */
    private static function get_comments_for_approver($permit_id, $approver_user_id) {
        global $wpdb;
        $comments_table = $wpdb->prefix . 'wps_permit_comments';
        
        // Approver sees: admin comments, reviewer comments, system messages, their own previous comments
        $query = $wpdb->prepare("
            SELECT c.*, u.display_name as user_display_name
            FROM $comments_table c
            LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
            WHERE c.permit_id = %d 
            AND (
                c.user_type IN ('admin', 'system', 'reviewer') 
                OR (c.user_type = 'approver' AND c.user_id = %d)
            )
            AND c.is_internal = 0
            ORDER BY c.created_date ASC
        ", $permit_id, $approver_user_id);
        
        $comments = $wpdb->get_results($query);
        
        return self::format_comments_for_pdf($comments, 'approver');
    }
    
    /**
     * Get comments appropriate for applicant PDF
     */
    private static function get_comments_for_applicant($permit_id, $permit_status) {
        global $wpdb;
        $comments_table = $wpdb->prefix . 'wps_permit_comments';
        
        // Applicant sees different comments based on permit status
        switch ($permit_status) {
            case 'approved':
                // Show final approval comments and any reviewer comments that were forwarded
                $query = $wpdb->prepare("
                    SELECT c.*, u.display_name as user_display_name
                    FROM $comments_table c
                    LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
                    WHERE c.permit_id = %d 
                    AND c.user_type IN ('reviewer', 'approver')
                    AND c.is_internal = 0
                    AND (
                        c.action_taken LIKE '%approve%' 
                        OR c.new_status = 'approved'
                        OR c.new_status = 'pending_approval'
                    )
                    ORDER BY c.created_date ASC
                ", $permit_id);
                break;
                
            case 'cancelled':
                // Show rejection comments
                $query = $wpdb->prepare("
                    SELECT c.*, u.display_name as user_display_name
                    FROM $comments_table c
                    LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
                    WHERE c.permit_id = %d 
                    AND c.user_type IN ('reviewer', 'approver')
                    AND c.is_internal = 0
                    AND (
                        c.action_taken LIKE '%reject%' 
                        OR c.new_status = 'cancelled'
                    )
                    ORDER BY c.created_date ASC
                ", $permit_id);
                break;
                
            default:
                // For pending statuses, show minimal information
                $query = $wpdb->prepare("
                    SELECT c.*, u.display_name as user_display_name
                    FROM $comments_table c
                    LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
                    WHERE c.permit_id = %d 
                    AND c.user_type = 'system'
                    AND c.is_internal = 0
                    ORDER BY c.created_date ASC
                ", $permit_id);
        }
        
        $comments = $wpdb->get_results($query);
        
        return self::format_comments_for_pdf($comments, 'applicant');
    }
    
    /**
     * Get all comments (for admin PDF)
     */
    private static function get_all_comments($permit_id, $include_internal = false) {
        global $wpdb;
        $comments_table = $wpdb->prefix . 'wps_permit_comments';
        
        $internal_filter = $include_internal ? '' : 'AND c.is_internal = 0';
        
        $query = $wpdb->prepare("
            SELECT c.*, u.display_name as user_display_name
            FROM $comments_table c
            LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
            WHERE c.permit_id = %d 
            $internal_filter
            ORDER BY c.created_date ASC
        ", $permit_id);
        
        $comments = $wpdb->get_results($query);
        
        return self::format_comments_for_pdf($comments, 'admin');
    }
    
    /**
     * Format comments for PDF display
     */
    private static function format_comments_for_pdf($comments, $recipient_type) {
        $formatted_comments = array();
        
        foreach ($comments as $comment) {
            $formatted_comment = array(
                'id' => $comment->id,
                'comment' => $comment->comment,
                'user_name' => $comment->user_display_name ?: $comment->user_name,
                'user_type' => $comment->user_type,
                'user_type_display' => self::get_user_type_display($comment->user_type),
                'action_taken' => $comment->action_taken,
                'created_date' => $comment->created_date,
                'formatted_date' => wps_format_date($comment->created_date),
                'previous_status' => $comment->previous_status,
                'new_status' => $comment->new_status,
                'previous_status_display' => WPS_Database::get_status_display_text($comment->previous_status),
                'new_status_display' => WPS_Database::get_status_display_text($comment->new_status),
                'is_internal' => $comment->is_internal
            );
            
            // Add context-specific formatting
            $formatted_comment['display_context'] = self::get_comment_display_context($formatted_comment, $recipient_type);
            
            $formatted_comments[] = $formatted_comment;
        }
        
        return $formatted_comments;
    }
    
    /**
     * Get workflow history for PDF
     */
    private static function get_workflow_history_for_pdf($permit_id, $recipient_type) {
        global $wpdb;
        $history_table = $wpdb->prefix . 'wps_permit_status_history';
        
        $query = $wpdb->prepare("
            SELECT h.*, u.display_name as user_display_name
            FROM $history_table h
            LEFT JOIN {$wpdb->users} u ON h.changed_by_user_id = u.ID
            WHERE h.permit_id = %d
            ORDER BY h.created_date ASC
        ", $permit_id);
        
        $history = $wpdb->get_results($query);
        
        $formatted_history = array();
        
        foreach ($history as $entry) {
            $formatted_entry = array(
                'previous_status' => $entry->previous_status,
                'new_status' => $entry->new_status,
                'previous_status_display' => WPS_Database::get_status_display_text($entry->previous_status),
                'new_status_display' => WPS_Database::get_status_display_text($entry->new_status),
                'changed_by_name' => $entry->user_display_name ?: $entry->changed_by_name,
                'changed_by_type' => $entry->changed_by_type,
                'changed_by_type_display' => self::get_user_type_display($entry->changed_by_type),
                'reason' => $entry->reason,
                'created_date' => $entry->created_date,
                'formatted_date' => wps_format_date($entry->created_date)
            );
            
            // Filter based on recipient type
            if (self::should_include_history_entry($formatted_entry, $recipient_type)) {
                $formatted_history[] = $formatted_entry;
            }
        }
        
        return $formatted_history;
    }
    
    /**
     * Get approval chain information
     */
    private static function get_approval_chain_info($permit) {
        $chain = array();
        
        // Reviewer information
        if ($permit->reviewer_user_id) {
            $reviewer = get_user_by('ID', $permit->reviewer_user_id);
            if ($reviewer) {
                $chain['reviewer'] = array(
                    'name' => $reviewer->display_name,
                    'email' => $reviewer->user_email,
                    'role' => 'Reviewer',
                    'assigned_categories' => get_user_meta($reviewer->ID, 'wps_assigned_categories', true)
                );
            }
        }
        
        // Approver information  
        if ($permit->approver_user_id) {
            $approver = get_user_by('ID', $permit->approver_user_id);
            if ($approver) {
                $chain['approver'] = array(
                    'name' => $approver->display_name,
                    'email' => $approver->user_email,
                    'role' => 'Approver',
                    'assigned_categories' => get_user_meta($approver->ID, 'wps_assigned_categories', true)
                );
            }
        }
        
        return $chain;
    }
    
    /**
     * Get user type display name
     */
    private static function get_user_type_display($user_type) {
        $display_map = array(
            'reviewer' => 'Reviewer',
            'approver' => 'Approver',
            'admin' => 'Administrator',
            'system' => 'System'
        );
        
        return isset($display_map[$user_type]) ? $display_map[$user_type] : ucfirst($user_type);
    }
    
    /**
     * Get comment display context based on recipient
     */
    private static function get_comment_display_context($comment, $recipient_type) {
        $context = array(
            'show_user_info' => true,
            'show_action' => true,
            'show_status_change' => true,
            'emphasis' => 'normal'
        );
        
        switch ($recipient_type) {
            case 'applicant':
                // Simplified display for applicants
                $context['show_user_info'] = false;
                if ($comment['user_type'] === 'system') {
                    $context['show_action'] = false;
                }
                break;
                
            case 'reviewer':
                // Emphasis on admin and system messages
                if (in_array($comment['user_type'], array('admin', 'system'))) {
                    $context['emphasis'] = 'high';
                }
                break;
                
            case 'approver':
                // Emphasis on reviewer decisions
                if ($comment['user_type'] === 'reviewer') {
                    $context['emphasis'] = 'high';
                }
                break;
        }
        
        return $context;
    }
    
    /**
     * Determine if history entry should be included for recipient
     */
    private static function should_include_history_entry($entry, $recipient_type) {
        switch ($recipient_type) {
            case 'applicant':
                // Only show major status changes for applicants
                return in_array($entry['new_status'], array('approved', 'cancelled'));
                
            case 'reviewer':
            case 'approver':
            case 'admin':
                // Show all history for internal users
                return true;
                
            default:
                return false;
        }
    }

    /**
     * Get comment count for a permit
     */
    private static function get_comments_count($permit_id) {
        global $wpdb;
        $comments_table = $wpdb->prefix . 'wps_permit_comments';
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $comments_table WHERE permit_id = %d AND is_internal = 0",
            $permit_id
        ));
    }
    
    /**
     * Calculate days in system
     */
    private static function calculate_days_in_system($permit) {
        $submitted = new DateTime($permit->submitted_date);
        $now = new DateTime();
        
        return $submitted->diff($now)->days;
    }
    
    /**
     * Calculate workflow duration (submission to final decision)
     */
    private static function calculate_workflow_duration($permit_id) {
        global $wpdb;
        $history_table = $wpdb->prefix . 'wps_permit_status_history';
        
        // Get first and last status change
        $duration_query = $wpdb->prepare("
            SELECT 
                MIN(created_date) as first_change,
                MAX(created_date) as last_change
            FROM $history_table 
            WHERE permit_id = %d 
            AND new_status IN ('approved', 'cancelled')
        ", $permit_id);
        
        $duration_data = $wpdb->get_row($duration_query);
        
        if ($duration_data && $duration_data->first_change && $duration_data->last_change) {
            $start = new DateTime($duration_data->first_change);
            $end = new DateTime($duration_data->last_change);
            return $start->diff($end)->days;
        }
        
        return null;
    }
    
    /**
     * Get permit with all related data for comprehensive PDF
     */
    public static function get_complete_permit_data($permit_id, $recipient_type = 'admin') {
        $permit = self::get_permit_for_pdf($permit_id, $recipient_type);
        
        if (!$permit) {
            return null;
        }
        
        // Add comprehensive data
        $permit->complete_comments = self::get_all_comments($permit_id, true);
        $permit->complete_history = self::get_workflow_history_for_pdf($permit_id, 'admin');
        $permit->related_permits = self::get_related_permits($permit);
        $permit->category_details = WPS_Database::get_category_by_id($permit->work_category_id);
        
        return $permit;
    }
    
    /**
     * Get related permits (same applicant, same category, etc.)
     */
    private static function get_related_permits($permit) {
        global $wpdb;
        $permits_table = $wpdb->prefix . 'work_permits';
        
        // Get other permits from same applicant
        $related = $wpdb->get_results($wpdb->prepare("
            SELECT id, status, submitted_date, work_category_id
            FROM $permits_table 
            WHERE email_address = %s 
            AND id != %d
            ORDER BY submitted_date DESC
            LIMIT 5
        ", $permit->email_address, $permit->id));
        
        return $related;
    }
    
    /**
     * Debug method to check PDF data structure
     */
    public static function debug_pdf_data($permit_id, $recipient_type) {
        $data = self::get_permit_for_pdf($permit_id, $recipient_type);
        
        return array(
            'permit_basic' => array(
                'id' => $data->id ?? null,
                'status' => $data->status ?? null,
                'email' => $data->email_address ?? null
            ),
            'comments_count' => count($data->contextual_comments ?? array()),
            'history_count' => count($data->workflow_history ?? array()),
            'approval_chain' => $data->approval_chain ?? array(),
            'recipient_type' => $recipient_type
        );
    }
}