<?php
/**
 * Enhanced Reviewer Actions Database Class with Pagination
 * File: includes/class-reviewer-database.php
 * Handles all reviewer-specific database operations with pagination support
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPS_Reviewer_Database {
    
    /**
     * Get permits assigned to reviewer with pagination, search and filter capabilities
     */
    public static function get_permits_by_reviewer_paginated($reviewer_user_id, $status = null, $page = 1, $per_page = 10, $filters = array()) {

        global $wpdb;
        
        $permits_table = $wpdb->prefix . 'work_permits';
        $categories_table = $wpdb->prefix . 'wps_work_categories';
        
        // Base conditions
        $where_conditions = array("wp.reviewer_user_id = %d");
        $params = array($reviewer_user_id);
        
        // Status filter (keep existing logic but use filters array if provided)
        $status_filter = !empty($filters['status']) ? $filters['status'] : $status;
        if ($status_filter && $status_filter !== 'all') {
            $where_conditions[] = "wp.status = %s";
            $params[] = $status_filter;
        } else {
            $where_conditions[] = "wp.status IN ('pending_review', 'pending_approval', 'approved', 'cancelled')";
        }
        
        // Search functionality
        if (!empty($filters['search'])) {
            $search_query = sanitize_text_field($filters['search']);
            $search_type = !empty($filters['search_type']) ? sanitize_text_field($filters['search_type']) : 'all';
            
            $search_conditions = self::build_search_conditions($search_query, $search_type);
            if (!empty($search_conditions['where'])) {
                $where_conditions[] = $search_conditions['where'];
                $params = array_merge($params, $search_conditions['params']);
            }
        }
        
        // Additional filters
        if (!empty($filters['date_from'])) {
            $where_conditions[] = "DATE(wp.submitted_date) >= %s";
            $params[] = sanitize_text_field($filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = "DATE(wp.submitted_date) <= %s";
            $params[] = sanitize_text_field($filters['date_to']);
        }
        
        if (!empty($filters['work_category']) && $filters['work_category'] !== 'all') {
            $where_conditions[] = "wp.work_category_id = %d";
            $params[] = intval($filters['work_category']);
        }
        
        if (!empty($filters['priority']) && $filters['priority'] === 'urgent') {
            $where_conditions[] = "wp.status = 'pending_review' AND DATEDIFF(NOW(), wp.submitted_date) > 2";
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Calculate offset
        $offset = ($page - 1) * $per_page;
        
        // Get total count for pagination
        $count_query = $wpdb->prepare("
            SELECT COUNT(*) 
            FROM $permits_table wp
            LEFT JOIN $categories_table wc ON wp.work_category_id = wc.id
            WHERE $where_clause
        ", $params);
        
        $total_items = $wpdb->get_var($count_query);
        
        // Order by
        $order_by = !empty($filters['order_by']) ? sanitize_text_field($filters['order_by']) : 'wp.submitted_date';
        $order_dir = !empty($filters['order_dir']) && strtoupper($filters['order_dir']) === 'ASC' ? 'ASC' : 'DESC';
        
        // Validate order_by field
        $allowed_order_fields = array(
            'wp.submitted_date', 'wp.tenant', 'wp.email_address', 'wc.category_name', 'wp.status', 'wp.requested_start_date'
        );
        
        if (!in_array($order_by, $allowed_order_fields)) {
            $order_by = 'wp.submitted_date';
        }
        
        // Get permits with pagination
        $params[] = $offset;
        $params[] = $per_page;
        
        $query = $wpdb->prepare("
            SELECT 
                wp.*,
                wc.category_name,
                DATEDIFF(NOW(), wp.submitted_date) as days_since_submitted
            FROM $permits_table wp
            LEFT JOIN $categories_table wc ON wp.work_category_id = wc.id
            WHERE $where_clause
            ORDER BY $order_by $order_dir
            LIMIT %d, %d
        ", $params);
        
        $permits = $wpdb->get_results($query);
        
        // Add user details and additional metadata
        foreach ($permits as $permit) {
            if ($permit->reviewer_user_id) {
                $reviewer = get_user_by('ID', $permit->reviewer_user_id);
                $permit->reviewer_name = $reviewer ? $reviewer->display_name : null;
                $permit->reviewer_email = $reviewer ? $reviewer->user_email : null;
            }
            
            if ($permit->approver_user_id) {
                $approver = get_user_by('ID', $permit->approver_user_id);
                $permit->approver_name = $approver ? $approver->display_name : null;
                $permit->approver_email = $approver ? $approver->user_email : null;
            }
            
            // Mark urgent permits
            $permit->is_urgent = ($permit->status === 'pending_review' && $permit->days_since_submitted > 2);
        }
        
        return array(
            'permits' => $permits,
            'total_items' => $total_items,
            'total_pages' => ceil($total_items / $per_page),
            'current_page' => $page,
            'per_page' => $per_page,
            'filters_applied' => $filters
        );
    }

    /**
     * Build search conditions - ADD THIS NEW METHOD
     */
    private static function build_search_conditions($search_query, $search_type) {
        global $wpdb;
        
        $search_term = '%' . $wpdb->esc_like($search_query) . '%';
        $conditions = array();
        $params = array();
        
        switch ($search_type) {
            case 'name':
                $conditions[] = "(wp.tenant LIKE %s OR wp.issued_to LIKE %s)";
                $params[] = $search_term;
                $params[] = $search_term;
                break;
                
            case 'email':
                $conditions[] = "wp.email_address LIKE %s";
                $params[] = $search_term;
                break;
                
            case 'work_type':
                $conditions[] = "wc.category_name LIKE %s";
                $params[] = $search_term;
                break;
                
            case 'status':
                // Convert search term to actual status values
                $status_search = strtolower(str_replace('%', '', $search_term));
                $status_conditions = array();
                $status_params = array();
                
                if (strpos($status_search, 'pending') !== false) {
                    if (strpos($status_search, 'review') !== false) {
                        $status_conditions[] = "wp.status = %s";
                        $status_params[] = 'pending_review';
                    }
                    if (strpos($status_search, 'approval') !== false) {
                        $status_conditions[] = "wp.status = %s";
                        $status_params[] = 'pending_approval';
                    }
                }
                if (strpos($status_search, 'approved') !== false) {
                    $status_conditions[] = "wp.status = %s";
                    $status_params[] = 'approved';
                }
                if (strpos($status_search, 'cancelled') !== false || strpos($status_search, 'rejected') !== false) {
                    $status_conditions[] = "wp.status = %s";
                    $status_params[] = 'cancelled';
                }
                
                if (!empty($status_conditions)) {
                    $conditions[] = '(' . implode(' OR ', $status_conditions) . ')';
                    $params = array_merge($params, $status_params);
                }
                break;
                
            case 'all':
            default:
                $conditions[] = "(wp.tenant LIKE %s OR wp.issued_to LIKE %s OR wp.email_address LIKE %s OR wc.category_name LIKE %s OR wp.work_area LIKE %s OR wp.tenant_field LIKE %s)";
                $params[] = $search_term;
                $params[] = $search_term;
                $params[] = $search_term;
                $params[] = $search_term;
                $params[] = $search_term;
                $params[] = $search_term;
                break;
        }
        
        return array(
            'where' => implode(' OR ', $conditions),
            'params' => $params
        );
    }

    /**
     * Get filter options - ADD THIS NEW METHOD
     */
    public static function get_filter_options($reviewer_user_id) {
        global $wpdb;
        
        $permits_table = $wpdb->prefix . 'work_permits';
        $categories_table = $wpdb->prefix . 'wps_work_categories';
        
        // Get work categories for this reviewer's permits
        $work_categories = $wpdb->get_results($wpdb->prepare("
            SELECT DISTINCT wc.id, wc.category_name
            FROM $permits_table wp
            LEFT JOIN $categories_table wc ON wp.work_category_id = wc.id
            WHERE wp.reviewer_user_id = %d
            ORDER BY wc.category_name ASC
        ", $reviewer_user_id));
        
        return array(
            'work_categories' => $work_categories,
            'search_types' => array(
                'all' => 'All Fields',
                'name' => 'Name/Tenant',
                'email' => 'Email Address',
                'work_type' => 'Work Type',
                // 'status' => 'Status'
            ),
            'sort_options' => array(
                'wp.submitted_date' => 'Submitted Date',
                'wp.tenant' => 'Name/Tenant',
                'wp.email_address' => 'Email',
                'wc.category_name' => 'Work Type',
                'wp.status' => 'Status',
                'wp.requested_start_date' => 'Start Date'
            )
        );
    }

    /**
     * Get search suggestions - ADD THIS NEW METHOD
     */
    public static function get_search_suggestions($reviewer_user_id, $search_type, $partial_query, $limit = 10) {
        global $wpdb;
        
        if (strlen($partial_query) < 2) {
            return array();
        }
        
        $permits_table = $wpdb->prefix . 'work_permits';
        $categories_table = $wpdb->prefix . 'wps_work_categories';
        $search_term = $wpdb->esc_like($partial_query) . '%';
        
        $suggestions = array();
        
        switch ($search_type) {
            case 'name':
                $results = $wpdb->get_results($wpdb->prepare("
                    SELECT DISTINCT tenant as suggestion
                    FROM $permits_table 
                    WHERE reviewer_user_id = %d AND tenant LIKE %s
                    UNION
                    SELECT DISTINCT issued_to as suggestion
                    FROM $permits_table 
                    WHERE reviewer_user_id = %d AND issued_to LIKE %s
                    LIMIT %d
                ", $reviewer_user_id, $search_term, $reviewer_user_id, $search_term, $limit));
                break;
                
            case 'email':
                $results = $wpdb->get_results($wpdb->prepare("
                    SELECT DISTINCT email_address as suggestion
                    FROM $permits_table 
                    WHERE reviewer_user_id = %d AND email_address LIKE %s
                    LIMIT %d
                ", $reviewer_user_id, $search_term, $limit));
                break;
                
            case 'work_type':
                $results = $wpdb->get_results($wpdb->prepare("
                    SELECT DISTINCT wc.category_name as suggestion
                    FROM $permits_table wp
                    LEFT JOIN $categories_table wc ON wp.work_category_id = wc.id
                    WHERE wp.reviewer_user_id = %d AND wc.category_name LIKE %s
                    LIMIT %d
                ", $reviewer_user_id, $search_term, $limit));
                break;
                
            default:
                $results = array();
                break;
        }
        
        foreach ($results as $result) {
            if (!empty($result->suggestion)) {
                $suggestions[] = $result->suggestion;
            }
        }
        
        return array_unique($suggestions);
    }
    
    /**
     * Get permit details for reviewer view
     */
    public static function get_permit_for_review($permit_id, $reviewer_user_id) {
        global $wpdb;
        
        $permits_table = $wpdb->prefix . 'work_permits';
        $categories_table = $wpdb->prefix . 'wps_work_categories';
        
        $permit = $wpdb->get_row($wpdb->prepare("
            SELECT 
                wp.*,
                wc.category_name
            FROM $permits_table wp
            LEFT JOIN $categories_table wc ON wp.work_category_id = wc.id
            WHERE wp.id = %d AND wp.reviewer_user_id = %d
        ", $permit_id, $reviewer_user_id));
        
        if ($permit) {
            // Add reviewer and approver details
            if ($permit->reviewer_user_id) {
                $reviewer = get_user_by('ID', $permit->reviewer_user_id);
                $permit->reviewer_name = $reviewer ? $reviewer->display_name : null;
                $permit->reviewer_email = $reviewer ? $reviewer->user_email : null;
            }
            
            if ($permit->approver_user_id) {
                $approver = get_user_by('ID', $permit->approver_user_id);
                $permit->approver_name = $approver ? $approver->display_name : null;
                $permit->approver_email = $approver ? $approver->user_email : null;
            }
        }
        
        return $permit;
    }
    
    /**
     * Update permit status by reviewer
     */
    public static function update_permit_by_reviewer($permit_id, $new_status, $reviewer_comment, $reviewer_user_id) {
        global $wpdb;
        
        // Validate reviewer permission
        if (!self::can_reviewer_update_permit($permit_id, $reviewer_user_id)) {
            return new WP_Error('permission_denied', 'You do not have permission to update this permit');
        }
        
        // Validate status transition
        $current_permit = WPS_Database::get_permit_by_id($permit_id);
        if (!$current_permit) {
            return new WP_Error('permit_not_found', 'Permit not found');
        }
        
        if (!self::is_valid_reviewer_status_transition($current_permit->status, $new_status)) {
            return new WP_Error('invalid_transition', 'Invalid status transition');
        }
        
        // Begin transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // Update permit status
            $update_data = array(
                'status' => $new_status,
                'updated_date' => current_time('mysql')
            );
            
            // Add status-specific timestamps
            if ($new_status === 'pending_approval') {
                $update_data['review_completed_date'] = current_time('mysql');
            } elseif ($new_status === 'cancelled') {
                $update_data['cancelled_date'] = current_time('mysql');
            }
            
            $permits_table = $wpdb->prefix . 'work_permits';
            $result = $wpdb->update(
                $permits_table,
                $update_data,
                array('id' => $permit_id),
                array('%s', '%s', '%s'),
                array('%d')
            );
            
            if ($result === false) {
                throw new Exception('Failed to update permit status');
            }
            
            // Add comment if provided
            if (!empty($reviewer_comment)) {
                $reviewer = get_user_by('ID', $reviewer_user_id);
                $action_text = $new_status === 'pending_approval' ? 'Approved for final approval' : 'Rejected';
                
                $comment_result = WPS_Database::add_permit_comment(
                    $permit_id,
                    $reviewer_user_id,
                    'reviewer',
                    $reviewer->display_name,
                    $reviewer->user_email,
                    $reviewer_comment,
                    $action_text,
                    $current_permit->status,
                    $new_status,
                    0
                );
                
                if ($comment_result === false) {
                    throw new Exception('Failed to add reviewer comment');
                }
            }
            
            // Add status history
            $reviewer = get_user_by('ID', $reviewer_user_id);
            $history_result = WPS_Database::add_status_change(
                $permit_id,
                $current_permit->status,
                $new_status,
                $reviewer_user_id,
                'reviewer',
                $reviewer->display_name,
                $reviewer_comment
            );
            
            if ($history_result === false) {
                throw new Exception('Failed to add status history');
            }
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
            return true;
            
        } catch (Exception $e) {
            // Rollback transaction
            $wpdb->query('ROLLBACK');
            return new WP_Error('update_failed', $e->getMessage());
        }
    }
    
    /**
     * Check if reviewer can update specific permit
     */
    public static function can_reviewer_update_permit($permit_id, $reviewer_user_id) {
        global $wpdb;
        
        $permits_table = $wpdb->prefix . 'work_permits';
        
        $permit = $wpdb->get_row($wpdb->prepare("
            SELECT reviewer_user_id, status 
            FROM $permits_table 
            WHERE id = %d
        ", $permit_id));
        
        if (!$permit) {
            return false;
        }
        
        // Check if permit is assigned to this reviewer
        if ($permit->reviewer_user_id != $reviewer_user_id) {
            return false;
        }
        
        // Check if permit is in reviewable status
        return $permit->status === 'pending_review';
    }
    
    /**
     * Validate reviewer status transitions
     */
    private static function is_valid_reviewer_status_transition($current_status, $new_status) {
        $valid_transitions = array(
            'pending_review' => array('pending_approval', 'cancelled')
        );
        
        return isset($valid_transitions[$current_status]) && 
               in_array($new_status, $valid_transitions[$current_status]);
    }
    
    /**
     * Get reviewer's pending permits count
     */
    public static function get_reviewer_pending_count($reviewer_user_id) {
        global $wpdb;
        
        $permits_table = $wpdb->prefix . 'work_permits';
        
        return $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM $permits_table 
            WHERE reviewer_user_id = %d AND status = 'pending_review'
        ", $reviewer_user_id));
    }
    
    /**
     * Get reviewer's completed permits count
     */
    public static function get_reviewer_completed_count($reviewer_user_id) {
        global $wpdb;
        
        $permits_table = $wpdb->prefix . 'work_permits';
        
        return $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM $permits_table 
            WHERE reviewer_user_id = %d AND status IN ('pending_approval', 'approved')
        ", $reviewer_user_id));
    }
    
    /**
     * Get reviewer's rejected permits count
     */
    public static function get_reviewer_rejected_count($reviewer_user_id) {
        global $wpdb;
        
        $permits_table = $wpdb->prefix . 'work_permits';
        
        return $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM $permits_table 
            WHERE reviewer_user_id = %d AND status = 'cancelled'
        ", $reviewer_user_id));
    }
    
    
    /**
     * Get permits that need urgent attention (over 2 days old)
     */
    public static function get_urgent_permits_for_reviewer($reviewer_user_id) {
        global $wpdb;
        
        $permits_table = $wpdb->prefix . 'work_permits';
        $categories_table = $wpdb->prefix . 'wps_work_categories';
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                wp.*,
                wc.category_name,
                DATEDIFF(NOW(), wp.submitted_date) as days_pending
            FROM $permits_table wp
            LEFT JOIN $categories_table wc ON wp.work_category_id = wc.id
            WHERE wp.reviewer_user_id = %d 
            AND wp.status = 'pending_review'
            AND DATEDIFF(NOW(), wp.submitted_date) > 2
            ORDER BY wp.submitted_date ASC
        ", $reviewer_user_id));
    }
    
    /**
     * Log reviewer action for audit trail
     */
    public static function log_reviewer_action($permit_id, $reviewer_user_id, $action, $details = '') {
        global $wpdb;
        
        $log_table = $wpdb->prefix . 'wps_reviewer_actions_log';
        
        // Create log table if it doesn't exist
        self::create_reviewer_log_table();
        
        $reviewer = get_user_by('ID', $reviewer_user_id);
        
        return $wpdb->insert(
            $log_table,
            array(
                'permit_id' => $permit_id,
                'reviewer_user_id' => $reviewer_user_id,
                'reviewer_name' => $reviewer->display_name,
                'action' => $action,
                'details' => $details,
                'action_date' => current_time('mysql'),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Create reviewer actions log table
     */
    private static function create_reviewer_log_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wps_reviewer_actions_log';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            permit_id mediumint(9) NOT NULL,
            reviewer_user_id bigint(20) UNSIGNED NOT NULL,
            reviewer_name varchar(255) NOT NULL,
            action varchar(50) NOT NULL,
            details text,
            action_date datetime DEFAULT CURRENT_TIMESTAMP,
            ip_address varchar(45) DEFAULT '',
            PRIMARY KEY (id),
            INDEX idx_permit (permit_id),
            INDEX idx_reviewer (reviewer_user_id),
            INDEX idx_action_date (action_date)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Get reviewer statistics for dashboard
     */
    public static function get_reviewer_dashboard_stats($reviewer_user_id) {
        return array(
            'pending_review' => self::get_reviewer_pending_count($reviewer_user_id),
            'completed' => self::get_reviewer_completed_count($reviewer_user_id),
            'rejected' => self::get_reviewer_rejected_count($reviewer_user_id),
            'urgent_permits' => count(self::get_urgent_permits_for_reviewer($reviewer_user_id)),
            'total_assigned' => self::get_reviewer_pending_count($reviewer_user_id) + 
                              self::get_reviewer_completed_count($reviewer_user_id) + 
                              self::get_reviewer_rejected_count($reviewer_user_id)
        );
    }
    
    /**
     * Validate permit ownership and status for reviewer actions
     */
    public static function validate_reviewer_permit_access($permit_id, $reviewer_user_id) {
        global $wpdb;
        
        $permits_table = $wpdb->prefix . 'work_permits';
        
        $permit = $wpdb->get_row($wpdb->prepare("
            SELECT id, reviewer_user_id, status, email_address
            FROM $permits_table 
            WHERE id = %d
        ", $permit_id));
        
        if (!$permit) {
            return array('valid' => false, 'error' => 'Permit not found');
        }
        
        // RELAXED: Allow reviewers to view any permit for "view details" functionality
        // Only restrict if they're trying to actually review (status update)
        // This allows the unified view modal to work properly
        
        return array('valid' => true, 'permit' => $permit);
    }

    /**
     * Get attachments for a specific permit (reviewer version)
     * FIXED VERSION - Replace the existing method in WPS_Reviewer_Database
     */
    public static function get_permit_attachments($permit_id) {
        // ADD STRICT VALIDATION
        $permit_id = intval($permit_id);
        if ($permit_id <= 0) {
            error_log('WPS Reviewer: Invalid permit ID: ' . $permit_id);
            return array(
                'attachments' => array(),
                'permit_info' => null,
                'error' => 'Invalid permit ID'
            );
        }
        
        // First, verify the permit exists and belongs to this reviewer
        $current_user_id = get_current_user_id();
        $access_check = self::validate_reviewer_permit_access($permit_id, $current_user_id);
        
        if (!$access_check['valid']) {
            error_log('WPS Reviewer: Access denied for permit ' . $permit_id . ': ' . $access_check['error']);
            return array(
                'attachments' => array(),
                'permit_info' => null,
                'error' => $access_check['error']
            );
        }
        
        // Get permit info for display
        $permit_info = self::get_permit_for_review($permit_id, $current_user_id);
        if (!$permit_info) {
            error_log('WPS Reviewer: Could not retrieve permit info for permit ' . $permit_id);
            return array(
                'attachments' => array(),
                'permit_info' => null,
                'error' => 'Could not retrieve permit information'
            );
        }
        
        // Check if Document Manager is available
        if (!class_exists('WPS_Document_Manager')) {
            error_log('WPS Reviewer: Document Manager not available');
            return array(
                'attachments' => array(),
                'permit_info' => $permit_info,
                'error' => 'Document Manager not available'
            );
        }
        
        // Get all documents for this specific permit
        try {
            $all_attachments = WPS_Document_Manager::get_permit_documents($permit_id, null, true);
            
            // ADDITIONAL VALIDATION: Double-check permit IDs match
            $validated_attachments = array();
            foreach ($all_attachments as $doc) {
                if (intval($doc->permit_id) === $permit_id) {
                    $validated_attachments[] = $doc;
                } else {
                    error_log('WPS Reviewer: MISMATCHED permit ID! Document ' . $doc->original_filename . ' has permit_id=' . $doc->permit_id . ' but expected ' . $permit_id);
                }
            }
            
            $all_attachments = $validated_attachments;
            
        } catch (Exception $e) {
            error_log('WPS Reviewer: Error getting documents: ' . $e->getMessage());
            return array(
                'attachments' => array(),
                'permit_info' => $permit_info,
                'error' => 'Error retrieving documents: ' . $e->getMessage()
            );
        }
        
        // Process attachments for frontend display
        $processed_attachments = array();
        foreach ($all_attachments as $attachment) {
            $processed = self::process_attachment_for_reviewer_display($attachment);
            if ($processed) {
                $processed_attachments[] = $processed;
            }
        }
        
        return array(
            'attachments' => $processed_attachments,
            'permit_info' => $permit_info,
            'error' => null
        );
    }

    /**
     * Process attachment for reviewer display
     * Add this method to the WPS_Reviewer_Database class
     */
    private static function process_attachment_for_reviewer_display($attachment) {
        // Ensure we have required properties
        if (!is_object($attachment)) {
            return null;
        }
        
        // Add any processing needed for reviewer display
        $processed = clone $attachment;
        
        // Add file extension
        if (isset($processed->original_filename)) {
            $processed->file_extension = strtolower(pathinfo($processed->original_filename, PATHINFO_EXTENSION));
        }
        
        // Format file size if not already formatted
        if (isset($processed->file_size) && is_numeric($processed->file_size)) {
            $processed->formatted_file_size = self::format_file_size($processed->file_size);
        }
        
        // Ensure upload_date is properly formatted
        if (isset($processed->upload_date)) {
            $processed->formatted_upload_date = date('M j, Y g:i A', strtotime($processed->upload_date));
        }
        
        // Set uploaded_by_type if not set
        if (!isset($processed->uploaded_by_type)) {
            $processed->uploaded_by_type = 'applicant';
        }
        
        return $processed;
    }

    /**
     * Helper method to format file size
     */
    private static function format_file_size($bytes) {
        if ($bytes === 0) return '0 Bytes';
        
        $k = 1024;
        $sizes = array('Bytes', 'KB', 'MB', 'GB', 'TB');
        $i = floor(log($bytes) / log($k));
        
        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }
}