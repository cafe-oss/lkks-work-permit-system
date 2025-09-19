<?php
/**
 * Unified Dashboard Filters Handler Class
 * File: includes/class-unified-dashboard-filters.php
 * Handles filtering and pagination for all dashboard types
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPS_Unified_Dashboard_Filters {
    
    /**
     * Get filtered and paginated permits for any dashboard type
     */
    public static function get_filtered_permits($dashboard_type, $user_id = null, $filters = array(), $page = 1, $per_page = 10) {
        global $wpdb;
        
        $permits_table = $wpdb->prefix . 'work_permits';
        $categories_table = $wpdb->prefix . 'wps_work_categories';
        
        // Base query parts
        $select = "
            SELECT 
                wp.*,
                wc.category_name,
                DATEDIFF(NOW(), wp.submitted_date) as days_since_submitted,
                DATEDIFF(NOW(), wp.updated_date) as days_since_updated
        ";
        
        $from = "
            FROM $permits_table wp
            LEFT JOIN $categories_table wc ON wp.work_category_id = wc.id
        ";
        
        // Build WHERE conditions based on dashboard type
        $where_conditions = array();
        $params = array();
        
        // Dashboard-specific conditions
        switch ($dashboard_type) {
            case 'admin':
                // Admin sees all permits
                $where_conditions[] = "1=1";
                break;
                
            case 'reviewer':
                if ($user_id) {
                    $where_conditions[] = "wp.reviewer_user_id = %d";
                    $params[] = $user_id;
                }
                $where_conditions[] = "wp.status IN ('pending_review', 'pending_approval', 'approved', 'cancelled')";
                break;
                
            case 'approver':
                if ($user_id) {
                    $where_conditions[] = "wp.approver_user_id = %d";
                    $params[] = $user_id;
                }
                $where_conditions[] = "wp.status IN ('pending_approval', 'approved', 'cancelled')";
                break;
        }
        
        // Apply filters
        $filter_conditions = self::build_filter_conditions($filters, $dashboard_type);
        if (!empty($filter_conditions['where'])) {
            $where_conditions = array_merge($where_conditions, $filter_conditions['where']);
            $params = array_merge($params, $filter_conditions['params']);
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Count total items
        $count_query = "SELECT COUNT(*) $from WHERE $where_clause";
        if (!empty($params)) {
            $count_query = $wpdb->prepare($count_query, $params);
        }
        $total_items = $wpdb->get_var($count_query);
        
        // Build ORDER BY
        $order_by = self::build_order_clause($filters, $dashboard_type);
        
        // Calculate pagination
        $offset = ($page - 1) * $per_page;
        $params[] = $offset;
        $params[] = $per_page;
        
        // Main query
        $main_query = "$select $from WHERE $where_clause $order_by LIMIT %d, %d";
        $permits = $wpdb->get_results($wpdb->prepare($main_query, $params));
        
        // Add user details and metadata
        foreach ($permits as $permit) {
            self::enhance_permit_data($permit, $dashboard_type);
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
     * Build filter conditions based on provided filters
     */
    private static function build_filter_conditions($filters, $dashboard_type) {
        global $wpdb;
        
        $where_conditions = array();
        $params = array();
        
        // Status filter
        $status_filter = $filters['status'] ?? '';
        if ($dashboard_type === 'admin') {
            $status_filter = $filters['status'] ?? '';
        } else {
            $status_filter = $filters['status_filter'] ?? $filters['status'] ?? '';
        }
        
        if (!empty($status_filter) && $status_filter !== 'all') {
            $where_conditions[] = "wp.status = %s";
            $params[] = $status_filter;
        }
        
        // Search functionality
        if (!empty($filters['search'])) {
            $search_conditions = self::build_search_conditions($filters['search'], $filters['search_type'] ?? 'all');
            if (!empty($search_conditions['where'])) {
                $where_conditions[] = $search_conditions['where'];
                $params = array_merge($params, $search_conditions['params']);
            }
        }
        
        // Date filters
        if (!empty($filters['date_from'])) {
            $where_conditions[] = "DATE(wp.submitted_date) >= %s";
            $params[] = sanitize_text_field($filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = "DATE(wp.submitted_date) <= %s";
            $params[] = sanitize_text_field($filters['date_to']);
        }
        
        // Work category filter
        $category_filter = $filters['work_category'] ?? $filters['category'] ?? '';
        if (!empty($category_filter) && $category_filter !== 'all') {
            $where_conditions[] = "wp.work_category_id = %d";
            $params[] = intval($category_filter);
        }
        
        // Reviewer filter (for approvers)
        if ($dashboard_type === 'approver' && !empty($filters['reviewer']) && $filters['reviewer'] !== 'all') {
            $where_conditions[] = "wp.reviewer_user_id = %d";
            $params[] = intval($filters['reviewer']);
        }
        
        // Priority/urgent filter
        if (!empty($filters['priority']) && $filters['priority'] === 'urgent') {
            if ($dashboard_type === 'reviewer') {
                $where_conditions[] = "wp.status = 'pending_review' AND DATEDIFF(NOW(), wp.submitted_date) > 2";
            } elseif ($dashboard_type === 'approver') {
                $where_conditions[] = "wp.status = 'pending_approval' AND DATEDIFF(NOW(), wp.updated_date) > 1";
            }
        }
        
        return array(
            'where' => $where_conditions,
            'params' => $params
        );
    }
    
    /**
     * Build search conditions
     */
    private static function build_search_conditions($search_query, $search_type) {
        global $wpdb;
        
        $search_term = '%' . $wpdb->esc_like($search_query) . '%';
        $conditions = array();
        $params = array();
        
        switch ($search_type) {
            case 'permit_id':
                $conditions[] = "(wp.permit_id LIKE %s)";
                $params[] = $search_term;
                break;

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
                $status_conditions = self::build_status_search_conditions($search_query);
                if (!empty($status_conditions['where'])) {
                    $conditions[] = $status_conditions['where'];
                    $params = array_merge($params, $status_conditions['params']);
                }
                break;
                
            case 'all':
            default:
                $conditions[] = "(
                    wp.permit_id LIKE %s OR 
                    wp.tenant LIKE %s OR 
                    wp.issued_to LIKE %s OR 
                    wp.email_address LIKE %s OR 
                    wc.category_name LIKE %s OR 
                    wp.work_area LIKE %s OR 
                    wp.tenant_field LIKE %s
                )";
                $params[] = $search_term; // permit_id
                $params[] = $search_term; // tenant
                $params[] = $search_term; // issued_to
                $params[] = $search_term; // email_address
                $params[] = $search_term; // category_name
                $params[] = $search_term; // work_area
                $params[] = $search_term; // tenant_field
                break;
        }
        
        return array(
            'where' => implode(' OR ', $conditions),
            'params' => $params
        );
    }
    
    /**
     * Build status search conditions
     */
    private static function build_status_search_conditions($search_query) {
        $status_search = strtolower(trim($search_query));
        $conditions = array();
        $params = array();
        
        if (strpos($status_search, 'pending') !== false) {
            if (strpos($status_search, 'review') !== false) {
                $conditions[] = "wp.status = %s";
                $params[] = 'pending_review';
            }
            if (strpos($status_search, 'approval') !== false) {
                $conditions[] = "wp.status = %s";
                $params[] = 'pending_approval';
            }
        }
        if (strpos($status_search, 'approved') !== false) {
            $conditions[] = "wp.status = %s";
            $params[] = 'approved';
        }
        if (strpos($status_search, 'cancelled') !== false || strpos($status_search, 'rejected') !== false) {
            $conditions[] = "wp.status = %s";
            $params[] = 'cancelled';
        }
        
        return array(
            'where' => !empty($conditions) ? '(' . implode(' OR ', $conditions) . ')' : '',
            'params' => $params
        );
    }
    
    /**
     * Build ORDER BY clause
     */
    private static function build_order_clause($filters, $dashboard_type) {
        $order_by = $filters['order_by'] ?? '';
        $order_dir = strtoupper($filters['order_dir'] ?? 'DESC');
        
        // Validate order direction
        if (!in_array($order_dir, ['ASC', 'DESC'])) {
            $order_dir = 'DESC';
        }
        
        // Default order by based on dashboard type
        if (empty($order_by)) {
            switch ($dashboard_type) {
                case 'admin':
                    $order_by = 'wp.submitted_date';
                    break;
                case 'reviewer':
                    $order_by = 'wp.submitted_date';
                    break;
                case 'approver':
                    $order_by = 'wp.updated_date';
                    break;
                default:
                    $order_by = 'wp.submitted_date';
            }
        }
        
        // Validate order_by field
        $allowed_order_fields = array(
            'wp.submitted_date', 'wp.updated_date', 'wp.tenant', 'wp.email_address',
            'wc.category_name', 'wp.status', 'wp.requested_start_date', 'wp.id'
        );
        
        if (!in_array($order_by, $allowed_order_fields)) {
            $order_by = 'wp.submitted_date';
        }
        
        return "ORDER BY $order_by $order_dir";
    }
    
    /**
     * Enhance permit data with user details and metadata
     */
    private static function enhance_permit_data($permit, $dashboard_type) {
        // Add reviewer details
        if ($permit->reviewer_user_id) {
            $reviewer = get_user_by('ID', $permit->reviewer_user_id);
            $permit->reviewer_name = $reviewer ? $reviewer->display_name : null;
            $permit->reviewer_email = $reviewer ? $reviewer->user_email : null;
        }
        
        // Add approver details
        if ($permit->approver_user_id) {
            $approver = get_user_by('ID', $permit->approver_user_id);
            $permit->approver_name = $approver ? $approver->display_name : null;
            $permit->approver_email = $approver ? $approver->user_email : null;
        }
        
        // Mark urgent permits
        switch ($dashboard_type) {
            case 'reviewer':
                $permit->is_urgent = ($permit->status === 'pending_review' && $permit->days_since_submitted > 2);
                break;
            case 'approver':
                $permit->is_urgent = ($permit->status === 'pending_approval' && $permit->days_since_updated > 1);
                break;
            default:
                $permit->is_urgent = false;
        }
    }
    
    /**
     * Get filter options for dashboard
     */
    public static function get_filter_options($dashboard_type, $user_id = null) {
        global $wpdb;
        
        $permits_table = $wpdb->prefix . 'work_permits';
        $categories_table = $wpdb->prefix . 'wps_work_categories';
        
        $options = array(
            'search_types' => array(
                'all' => __('All Fields', 'work-permit-system'),
                'permit_id' => __('Permit ID', 'work-permit-system'),
                'name' => __('Name/Tenant', 'work-permit-system'),
                'email' => __('Email Address', 'work-permit-system'),
                'work_type' => __('Work Type', 'work-permit-system')
            ),
            'work_categories' => array(),
            'reviewers' => array()
        );
        
        // Get work categories based on dashboard type
        if ($dashboard_type === 'admin') {
            $options['work_categories'] = WPS_Database::get_all_categories();
        } else {
            // Get categories for permits assigned to this user
            $user_condition = '';
            $user_param = array();
            
            if ($dashboard_type === 'reviewer' && $user_id) {
                $user_condition = 'WHERE wp.reviewer_user_id = %d';
                $user_param[] = $user_id;
            } elseif ($dashboard_type === 'approver' && $user_id) {
                $user_condition = 'WHERE wp.approver_user_id = %d';
                $user_param[] = $user_id;
            }
            
            $query = "
                SELECT DISTINCT wc.id, wc.category_name
                FROM $permits_table wp
                LEFT JOIN $categories_table wc ON wp.work_category_id = wc.id
                $user_condition
                ORDER BY wc.category_name ASC
            ";
            
            if (!empty($user_param)) {
                $query = $wpdb->prepare($query, $user_param);
            }
            
            $options['work_categories'] = $wpdb->get_results($query);
        }
        
        // Get reviewers for approver dashboard
        if ($dashboard_type === 'approver' && $user_id) {
            $options['reviewers'] = $wpdb->get_results($wpdb->prepare("
                SELECT DISTINCT wp.reviewer_user_id, u.display_name
                FROM $permits_table wp
                LEFT JOIN {$wpdb->users} u ON wp.reviewer_user_id = u.ID
                WHERE wp.approver_user_id = %d 
                AND wp.reviewer_user_id IS NOT NULL
                ORDER BY u.display_name ASC
            ", $user_id));
        }
        
        return $options;
    }
    
    /**
     * Get dashboard statistics
     */
    public static function get_dashboard_stats($dashboard_type, $user_id = null) {
        global $wpdb;
        
        $permits_table = $wpdb->prefix . 'work_permits';
        
        switch ($dashboard_type) {
            case 'admin':
                return WPS_Database::get_permit_stats();
                
            case 'reviewer':
                if (!$user_id) return array();
                
                return array(
                    'pending_review' => $wpdb->get_var($wpdb->prepare("
                        SELECT COUNT(*) FROM $permits_table 
                        WHERE reviewer_user_id = %d AND status = 'pending_review'
                    ", $user_id)),
                    'completed' => $wpdb->get_var($wpdb->prepare("
                        SELECT COUNT(*) FROM $permits_table 
                        WHERE reviewer_user_id = %d AND status IN ('pending_approval', 'approved')
                    ", $user_id)),
                    'rejected' => $wpdb->get_var($wpdb->prepare("
                        SELECT COUNT(*) FROM $permits_table 
                        WHERE reviewer_user_id = %d AND status = 'cancelled'
                    ", $user_id)),
                    'total_assigned' => $wpdb->get_var($wpdb->prepare("
                        SELECT COUNT(*) FROM $permits_table 
                        WHERE reviewer_user_id = %d
                    ", $user_id))
                );
                
            case 'approver':
                if (!$user_id) return array();
                
                return array(
                    'pending_approval' => $wpdb->get_var($wpdb->prepare("
                        SELECT COUNT(*) FROM $permits_table 
                        WHERE approver_user_id = %d AND status = 'pending_approval'
                    ", $user_id)),
                    'approved' => $wpdb->get_var($wpdb->prepare("
                        SELECT COUNT(*) FROM $permits_table 
                        WHERE approver_user_id = %d AND status = 'approved'
                    ", $user_id)),
                    'rejected' => $wpdb->get_var($wpdb->prepare("
                        SELECT COUNT(*) FROM $permits_table 
                        WHERE approver_user_id = %d AND status = 'cancelled'
                    ", $user_id)),
                    'total_assigned' => $wpdb->get_var($wpdb->prepare("
                        SELECT COUNT(*) FROM $permits_table 
                        WHERE approver_user_id = %d
                    ", $user_id))
                );
                
            default:
                return array();
        }
    }
    
    /**
     * Get search suggestions for autocomplete
     */
    public static function get_search_suggestions($dashboard_type, $user_id, $search_type, $partial_query, $limit = 10) {
        global $wpdb;
        
        if (strlen($partial_query) < 2) {
            return array();
        }
        
        $permits_table = $wpdb->prefix . 'work_permits';
        $categories_table = $wpdb->prefix . 'wps_work_categories';
        $search_term = $wpdb->esc_like($partial_query) . '%';
        
        // Build user condition
        $user_condition = '';
        $user_param = array();
        
        if ($dashboard_type === 'reviewer' && $user_id) {
            $user_condition = 'AND reviewer_user_id = %d';
            $user_param[] = $user_id;
        } elseif ($dashboard_type === 'approver' && $user_id) {
            $user_condition = 'AND approver_user_id = %d';
            $user_param[] = $user_id;
        }
        
        $suggestions = array();
        
        switch ($search_type) {
            case 'name':
                $query_params = array_merge([$search_term], $user_param, [$search_term], $user_param, [$limit]);
                $results = $wpdb->get_results($wpdb->prepare("
                    SELECT DISTINCT tenant as suggestion
                    FROM $permits_table 
                    WHERE tenant LIKE %s $user_condition
                    UNION
                    SELECT DISTINCT issued_to as suggestion
                    FROM $permits_table 
                    WHERE issued_to LIKE %s $user_condition
                    LIMIT %d
                ", $query_params));
                break;
                
            case 'email':
                $query_params = array_merge([$search_term], $user_param, [$limit]);
                $results = $wpdb->get_results($wpdb->prepare("
                    SELECT DISTINCT email_address as suggestion
                    FROM $permits_table 
                    WHERE email_address LIKE %s $user_condition
                    LIMIT %d
                ", $query_params));
                break;
                
            case 'work_type':
                $query_params = array_merge([$search_term], $user_param, [$limit]);
                $results = $wpdb->get_results($wpdb->prepare("
                    SELECT DISTINCT wc.category_name as suggestion
                    FROM $permits_table wp
                    LEFT JOIN $categories_table wc ON wp.work_category_id = wc.id
                    WHERE wc.category_name LIKE %s $user_condition
                    LIMIT %d
                ", $query_params));
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
}