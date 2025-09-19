<?php
/**
 * Updated Admin Class with Unified AJAX Handler Only
 * File: includes/class-admin.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPS_Admin
{
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // ADMIN-ONLY AJAX ACTIONS (with admin-specific action names)
        add_action('wp_ajax_wps_get_permit_details', array($this, 'handle_get_permit_details'));
        add_action('wp_ajax_wps_export_permit_by_id_pdf', array($this, 'handle_export_permit_by_id_pdf'));
        
        // ADD THIS: Register the admin-specific attachments handler
        add_action('wp_ajax_wps_admin_get_permit_attachments', array($this, 'handle_admin_get_permit_attachments'));

        // Load unified AJAX handler for reviewer/approver dashboards
        $this->load_unified_ajax_handler();
        
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'redirect_users_to_dashboard'));
    }

    /**
     * Load unified AJAX handler only
     */
    private function load_unified_ajax_handler() {
        if (file_exists(WPS_PLUGIN_PATH . 'includes/class-unified-ajax-handler.php')) {
            require_once WPS_PLUGIN_PATH . 'includes/class-unified-ajax-handler.php';
        } else {
            error_log('WPS: Unified AJAX handler not found at: ' . WPS_PLUGIN_PATH . 'includes/class-unified-ajax-handler.php');
        }
    }

    public function add_admin_menu() {
        // Main admin page (administrators only)
        add_menu_page(
            __('Work Permits', 'work-permit-system'),
            __('Work Permits', 'work-permit-system'),
            'manage_options',
            'work-permits',
            array($this, 'admin_page'),
            'dashicons-clipboard',
            30
        );

        // Reviewer dashboard (for users with reviewer capabilities)
        if (current_user_can('wps_review_permits') && !current_user_can('manage_options')) {
            add_menu_page(
                __('Permits to Review', 'work-permit-system'),
                __('Permits to Review', 'work-permit-system'),
                'wps_review_permits',
                'wps-reviewer-dashboard',
                array($this, 'reviewer_dashboard_page'),
                'dashicons-clipboard',
                30
            );
        }

        // Approver dashboard (for users with approver capabilities)
        if (current_user_can('wps_approve_permits') && !current_user_can('manage_options')) {
            add_menu_page(
                __('Permits to Approve', 'work-permit-system'),
                __('Permits to Approve', 'work-permit-system'),
                'wps_approve_permits',
                'wps-approver-dashboard',
                array($this, 'approver_dashboard_page'),
                'dashicons-yes-alt',
                30
            );
        }
    }

    public function handle_admin_get_permit_attachments() {
        // Check user login
        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in');
            return;
        }
        
        // Verify admin nonce specifically
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wps_admin_nonce')) {
            error_log('WPS Admin: Nonce verification failed');
            wp_send_json_error('Security check failed');
            return;
        }
        
        // Check admin permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions - admin required');
            return;
        }
        
        // Get permit ID
        $permit_id = intval($_POST['permit_id'] ?? 0);
        if (!$permit_id) {
            wp_send_json_error('Invalid permit ID');
            return;
        }
        
        try {
            // Get permit info first
            $permit_info = WPS_Database::get_permit_by_id($permit_id);
            if (!$permit_info) {
                wp_send_json_error('Permit not found');
                return;
            }

            $documents = WPS_Document_Manager::get_permit_documents($permit_id, null, true);
            
            // Process attachments for admin display
            $processed_attachments = array();
            foreach ($documents as $doc) {
                $file_path = $doc->file_path ?? '';
                $file_exists = !empty($file_path) && file_exists($file_path);
                
                $processed_attachments[] = array(
                    'id' => $doc->id,
                    'original_filename' => $doc->original_filename ?? 'Unknown File',
                    'file_url' => $doc->file_url ?? '#',
                    'file_path' => $file_path,
                    'file_size' => $doc->file_size ?? 0,
                    'formatted_file_size' => $this->format_file_size($doc->file_size ?? 0),
                    'document_type' => $doc->document_type ?? 'supporting_document',
                    'upload_date' => $doc->upload_date ?? date('Y-m-d H:i:s'),
                    'formatted_upload_date' => $this->format_upload_date($doc->upload_date ?? date('Y-m-d H:i:s')),
                    'uploaded_by_type' => $doc->uploaded_by_type ?? 'applicant',
                    'description' => $doc->description ?? '',
                    'file_exists' => $file_exists
                );
            }
            
            // Success response with admin-specific data
            wp_send_json_success(array(
                'attachments' => $processed_attachments,
                'permit_info' => array(
                    'id' => $permit_info->id,
                    'issued_to' => $permit_info->tenant ?? 'Unknown Tenant',
                    'email_address' => $permit_info->email_address ?? 'No email',
                    'category_name' => $permit_info->category_name ?? 'Unknown Category',
                    'work_area' => $permit_info->work_area ?? 'Not specified',
                    'status' => $permit_info->status ?? 'unknown'
                )
            ));
            
        } catch (Exception $e) {
            error_log('WPS Admin: Error in admin attachments handler: ' . $e->getMessage());
            wp_send_json_error('Error loading attachments: ' . $e->getMessage());
        }
    }


    /**
     * Updated script enqueuing for unified dashboard system
     */
    public function enqueue_admin_scripts($hook) {
        // Load appropriate scripts based on page
        switch ($hook) {
            case 'toplevel_page_work-permits':
                // Load unified filters first for admin
                $this->enqueue_unified_filtering_assets('admin');
                // Then load admin-specific styles and scripts
                wp_enqueue_style('wps-admin', WPS_PLUGIN_URL . 'assets/css/admin.css', array('wps-unified-filters'), WPS_VERSION);
                $this->enqueue_main_admin_scripts();
                break;
                
            case 'toplevel_page_wps-reviewer-dashboard':
            case 'work-permits_page_wps-reviewer-dashboard':
                $this->enqueue_unified_dashboard_scripts('reviewer');
                break;
                
            case 'toplevel_page_wps-approver-dashboard':
            case 'work-permits_page_wps-approver-dashboard':
                $this->enqueue_unified_dashboard_scripts('approver');
                break;
                
            default:
                // FALLBACK: Check if this is a reviewer/approver page by other means
                if (isset($_GET['page'])) {
                    $page = sanitize_text_field($_GET['page']);
                    if ($page === 'wps-reviewer-dashboard') {
                        $this->enqueue_unified_dashboard_scripts('reviewer');
                    } elseif ($page === 'wps-approver-dashboard') {
                        $this->enqueue_unified_dashboard_scripts('approver');
                    }
                }
                break;
        }
    }

    private function enqueue_unified_filtering_assets($dashboard_type) {
        // Check if unified files exist
        $unified_filters_css = WPS_PLUGIN_PATH . 'assets/css/unified-dashboard-filters.css';
        $unified_filters_js = WPS_PLUGIN_PATH . 'assets/js/unified-dashboard-filters.js';
        
        if (file_exists($unified_filters_css) && file_exists($unified_filters_js)) {
            // Enqueue unified filtering assets
            wp_enqueue_style('wps-unified-filters', WPS_PLUGIN_URL . 'assets/css/unified-dashboard-filters.css', array(), WPS_VERSION);
            wp_enqueue_script('wps-unified-filters', WPS_PLUGIN_URL . 'assets/js/unified-dashboard-filters.js', array('jquery'), WPS_VERSION, true);
            
            // Localize the filters script
            wp_localize_script('wps-unified-filters', 'wps_filters_vars', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce($dashboard_type === 'admin' ? 'wps_admin_nonce' : 'wps_user_action'),
                'dashboard_type' => $dashboard_type
            ));
            
            return true;
        } else {
            error_log('WPS: Unified filtering assets not found');
            return false;
        }
    }

    /**
    * Main admin scripts (VIEW ONLY) - Updated to include unified table and view modal, nonce fixed
    */
    private function enqueue_main_admin_scripts() {
        // Load unified table CSS for admin
        wp_enqueue_style('wps-unified-table', WPS_PLUGIN_URL . 'assets/css/unified-table.css', array('wps-unified-filters'), WPS_VERSION);
        
        // Load unified view modal script FIRST
        wp_enqueue_script('wps-unified-view-modal', WPS_PLUGIN_URL . 'assets/js/unified-view-modal.js', array('jquery', 'wps-unified-filters'), WPS_VERSION, true);
        
        // FIXED: Initialize unified view modal with admin nonce
        wp_localize_script('wps-unified-view-modal', 'wps_unified_view_vars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wps_admin_nonce'), // ADMIN NONCE
            'user_type' => 'admin'
        ));
        
        // Load admin.js AFTER unified view modal
        wp_enqueue_script('wps-admin', WPS_PLUGIN_URL . 'assets/js/admin.js', array('jquery', 'wps-unified-view-modal'), WPS_VERSION, true);
        
        // Localize admin scripts
        wp_localize_script('wps-admin', 'wps_admin_vars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wps_admin_nonce'), // ADMIN NONCE
            'strings' => array(
                'loading' => __('Loading...', 'work-permit-system'),
                'view' => __('View', 'work-permit-system'),
                'error_loading' => __('Error loading permit details', 'work-permit-system'),
            )
        ));
    }

    /**
    * Unified dashboard scripts only - Updated to include unified table and view modal, nonce fixed
    */
    private function enqueue_unified_dashboard_scripts($dashboard_type) {
        // Load unified filtering first
        if (!$this->enqueue_unified_filtering_assets($dashboard_type)) {
            return; // Exit if unified assets failed to load
        }
        
        // Check if other unified files exist
        $unified_js_path = WPS_PLUGIN_PATH . 'assets/js/unified-dashboard.js';
        $unified_css_path = WPS_PLUGIN_PATH . 'assets/css/unified-dashboard.css';
        $unified_table_css_path = WPS_PLUGIN_PATH . 'assets/css/unified-table.css';
        $unified_view_modal_js_path = WPS_PLUGIN_PATH . 'assets/js/unified-view-modal.js';
        
        if (file_exists($unified_js_path) && file_exists($unified_css_path) && 
            file_exists($unified_table_css_path) && file_exists($unified_view_modal_js_path)) {
            
            // Load unified table CSS
            wp_enqueue_style('wps-unified-table', WPS_PLUGIN_URL . 'assets/css/unified-table.css', array('wps-unified-filters'), WPS_VERSION);
            
            // Load the unified dashboard CSS
            wp_enqueue_style('wps-unified-dashboard', WPS_PLUGIN_URL . 'assets/css/unified-dashboard.css', array('wps-unified-table'), WPS_VERSION);
            
            // Load unified view modal script
            wp_enqueue_script('wps-unified-view-modal', WPS_PLUGIN_URL . 'assets/js/unified-view-modal.js', array('jquery', 'wps-unified-filters'), WPS_VERSION, true);
            
            // FIXED: Initialize unified view modal with user nonce and correct user type
            wp_localize_script('wps-unified-view-modal', 'wps_unified_view_vars', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wps_user_action'), // USER NONCE for reviewers/approvers
                'user_type' => $dashboard_type // 'reviewer' or 'approver'
            ));
            
            // Load unified dashboard script
            wp_enqueue_script('wps-unified-dashboard', WPS_PLUGIN_URL . 'assets/js/unified-dashboard.js', array('jquery', 'wps-unified-view-modal', 'wps-unified-filters'), WPS_VERSION, true);

            // Get dashboard configuration
            $config = $this->get_dashboard_config($dashboard_type);

            // Localize unified script
            wp_localize_script('wps-unified-dashboard', 'wps_dashboard_vars', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'user_nonce' => wp_create_nonce('wps_user_action'), // USER NONCE
                'dashboard_type' => $dashboard_type,
                'config' => $config,
                'strings' => array(
                    'confirm_approve' => $dashboard_type === 'approver' ? 
                        __('Are you sure you want to give final approval to this permit?', 'work-permit-system') :
                        __('Are you sure you want to approve this permit and send it for final approval?', 'work-permit-system'),
                    'confirm_reject' => __('Are you sure you want to reject this permit?', 'work-permit-system'),
                    'comment_required' => $dashboard_type === 'approver' ? 
                        __('Please provide a reason for rejection.', 'work-permit-system') :
                        __('Please provide a comment for your decision.', 'work-permit-system'),
                    'loading' => __('Loading...', 'work-permit-system'),
                    'processing' => __('Processing...', 'work-permit-system'),
                    'error_loading' => __('Error loading permit details', 'work-permit-system'),
                    'network_error' => __('Network error occurred. Please try again.', 'work-permit-system'),
                    'success_approved' => $dashboard_type === 'approver' ?
                        __('Permit approved and activated!', 'work-permit-system') :
                        __('Permit approved and sent for final approval!', 'work-permit-system'),
                    'success_rejected' => __('Permit rejected successfully.', 'work-permit-system')
                )
            ));
            
            
        } else {
            error_log('WPS: Some unified files not found');
            // Show admin notice about missing files
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>';
                echo __('Work Permit System: Some unified files not found. Please ensure all files are in the assets directory.', 'work-permit-system');
                echo '</p></div>';
            });
        }
    }

    /**
     * Get dashboard configuration for unified system
     */
    private function get_dashboard_config($dashboard_type) {
        $base_config = array(
            'page_title' => $dashboard_type === 'approver' ? 
                __('Approver Dashboard', 'work-permit-system') : 
                __('Reviewer Dashboard', 'work-permit-system'),
            'welcome_message' => __('WELCOME BACK, %s!', 'work-permit-system'),
            'intro_text' => __('Permit Management Dashboard', 'work-permit-system'),
            'css_class' => $dashboard_type . '-dashboard',
            'body_class' => 'toplevel_page_wps-' . $dashboard_type . '-dashboard'
        );

        if ($dashboard_type === 'approver') {
            return array_merge($base_config, array(
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
                'urgent_threshold_days' => 1,
                'urgent_message' => __('Urgent - pending approval for over 1 day', 'work-permit-system'),
                'columns' => array(
                    __('Tenant', 'work-permit-system'),
                    __('Work Category', 'work-permit-system'),
                    __('Reviewer', 'work-permit-system'),
                    __('Status', 'work-permit-system'),
                    __('Actions', 'work-permit-system')
                ),
                'status_options' => array(
                    'approved' => __('Approve', 'work-permit-system'),
                    'cancelled' => __('Reject', 'work-permit-system')
                )
            ));
        } else {
            return array_merge($base_config, array(
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
                'urgent_threshold_days' => 2,
                'urgent_message' => __('Urgent - submitted over 2 days ago', 'work-permit-system'),
                'columns' => array(
                    __('Tenant', 'work-permit-system'),
                    __('Work Category', 'work-permit-system'),
                    __('Status', 'work-permit-system'),
                    __('Actions', 'work-permit-system')
                ),
                'status_options' => array(
                    'pending_approval' => __('Approve', 'work-permit-system'),
                    'cancelled' => __('Reject', 'work-permit-system')
                )
            ));
        }
    }

    /**
     * Redirect users to their appropriate dashboard
     */
    public function redirect_users_to_dashboard() {
        if (!is_admin() || !is_user_logged_in()) {
            return;
        }

        // Don't redirect administrators
        if (current_user_can('manage_options')) {
            return;
        }

        // Check if user is on the default admin dashboard
        global $pagenow;
        if ($pagenow === 'index.php' && !isset($_GET['page'])) {
            if (current_user_can('wps_review_permits')) {
                wp_redirect(admin_url('admin.php?page=wps-reviewer-dashboard'));
                exit;
            } elseif (current_user_can('wps_approve_permits')) {
                wp_redirect(admin_url('admin.php?page=wps-approver-dashboard'));
                exit;
            }
        }
    }

    /**
     * Main admin page
     */
    public function admin_page() {
        // Get current tab
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'dashboard';

        // Validate tab
        $valid_tabs = array('dashboard', 'permits');
        if (!in_array($current_tab, $valid_tabs)) {
            $current_tab = 'dashboard';
        }

        // Load admin page template
        include WPS_PLUGIN_PATH . 'templates/admin/admin-page.php';
    }

    /**
     * Unified dashboard rendering method
     */
    private function render_dashboard($dashboard_type) {
        global $current_user;
        
        // Set dashboard type for the template
        $is_approver = ($dashboard_type === 'approver');
        $is_reviewer = ($dashboard_type === 'reviewer');
        
        // Pagination and filter parameters
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = isset($_GET['per_page']) ? max(5, min(50, intval($_GET['per_page']))) : 10;
        
        // Build filters array from GET parameters
        $filters = array(
            'status' => isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : 'all',
            'search' => isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '',
            'search_type' => isset($_GET['search_type']) ? sanitize_text_field($_GET['search_type']) : 'all',
            'work_category' => isset($_GET['work_category']) ? sanitize_text_field($_GET['work_category']) : 'all',
            'reviewer' => isset($_GET['reviewer']) ? sanitize_text_field($_GET['reviewer']) : 'all',
            'date_from' => isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '',
            'date_to' => isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '',
            'priority' => isset($_GET['priority']) ? sanitize_text_field($_GET['priority']) : '',
            'order_by' => isset($_GET['order_by']) ? sanitize_text_field($_GET['order_by']) : 'wp.updated_date',
            'order_dir' => isset($_GET['order_dir']) ? sanitize_text_field($_GET['order_dir']) : 'DESC'
        );
        
        // Load the unified class
        require_once WPS_PLUGIN_PATH . 'includes/class-unified-dashboard-filters.php';

        // Use unified handler for both approver and reviewer
        $result = WPS_Unified_Dashboard_Filters::get_filtered_permits(
            $dashboard_type,  // 'approver' or 'reviewer'
            $current_user->ID,
            $filters,
            $current_page,
            $per_page
        );
        
        // CORRECTED: Extract data properly
        $all_permits = $result['permits'];
        $pagination_info = array(
            'total_items' => $result['total_items'],
            'total_pages' => $result['total_pages'],
            'current_page' => $result['current_page'],
            'per_page' => $result['per_page']
        );
        
        // Get stats and filter options
        $stats = WPS_Unified_Dashboard_Filters::get_dashboard_stats($dashboard_type, $current_user->ID);
        $filter_options = WPS_Unified_Dashboard_Filters::get_filter_options($dashboard_type, $current_user->ID);
        
        // Check if any filters are active
        $has_active_filters = !empty($filters['search']) || $filters['status'] !== 'all' || 
                            $filters['work_category'] !== 'all' || $filters['reviewer'] !== 'all' ||
                            !empty($filters['date_from']) || !empty($filters['date_to']) || 
                            !empty($filters['priority']);
        
        // Check if unified template exists
        $unified_template = WPS_PLUGIN_PATH . 'templates/user-dashboard/unified-dashboard.php';
        
        if (file_exists($unified_template)) {
            include $unified_template;
        } else {
            error_log('WPS: Unified template not found: ' . $unified_template);
            echo '<div class="wrap">';
            echo '<h1>' . ucfirst($dashboard_type) . ' Dashboard</h1>';
            echo '<p>Unified dashboard template not found. Please ensure unified-dashboard.php exists in templates/user-dashboard/</p>';
            echo '</div>';
        }
    }

    /**
     * Updated reviewer dashboard page using unified rendering
     */
    public function reviewer_dashboard_page() {
        try {
            $this->render_dashboard('reviewer');
        } catch (Exception $e) {
            error_log('WPS: Error in reviewer dashboard: ' . $e->getMessage());
            echo '<div class="wrap"><h1>Error</h1><p>Unable to load reviewer dashboard. Please contact administrator.</p></div>';
        }
    }

    /**
     * Updated approver dashboard page using unified rendering
     */
    public function approver_dashboard_page() {
        try {
            $this->render_dashboard('approver');
        } catch (Exception $e) {
            error_log('WPS: Error in approver dashboard: ' . $e->getMessage());
            echo '<div class="wrap"><h1>Error</h1><p>Unable to load approver dashboard. Please contact administrator.</p></div>';
        }
    }

    /**
     * Handle get permit details AJAX request (VIEW ONLY)
     */
    public function handle_get_permit_details() {
        if (!wp_verify_nonce($_POST['nonce'], 'wps_admin_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $permit_id = intval($_POST['permit_id']);
        if (!$permit_id) {
            wp_send_json_error('Invalid permit ID');
            return;
        }

        $permit = WPS_Database::get_permit_by_id($permit_id);
        if (!$permit) {
            wp_send_json_error('Permit not found');
            return;
        }

        // Format the data for view-only display
        $formatted_data = array(
            'id' => $permit->id,
            'permit_id' => $permit->permit_id,
            'email_address' => $permit->email_address,
            'phone_number' => $permit->phone_number,
            'tenant' => $permit->tenant,
            'work_area' => $permit->work_area,
            'category_name' => $permit->category_name,
            'tenant_field' => $permit->tenant_field,
            'submitted_date' => wps_format_date($permit->submitted_date),
            'requested_start_date' => $permit->requested_start_date,
            'requested_start_time' => $permit->requested_start_time,
            'requested_end_date' => $permit->requested_end_date,
            'requested_end_time' => $permit->requested_end_time,
            'personnel_list' => $permit->personnel_list,
            'requestor_type' => $permit->requestor_type,
            'requester_position' => $permit->requester_position,
            'admin_field' => isset($permit->admin_field) ? $permit->admin_field : '',
            'status' => $permit->status,
            'reviewer_name' => $permit->reviewer_name,
            'reviewer_email' => $permit->reviewer_email,
            'approver_name' => $permit->approver_name,
            'approver_email' => $permit->approver_email
        );

        // Get status history
        $status_history = WPS_Database::get_permit_status_history($permit_id);
        $formatted_data['status_history'] = $status_history;

        // Get comments
        $comments = WPS_Database::get_permit_comments($permit_id, false);
        $formatted_data['comments'] = $comments;

        wp_send_json_success($formatted_data);
    }

    /**
     * Handle export permit by ID to PDF AJAX request, nonce fixed
     */
    public function handle_export_permit_by_id_pdf() {
        
        // FIXED: Try both nonce types (admin and user)
        $nonce_valid = false;
        $nonce_value = $_POST['nonce'] ?? '';
        
        // Check admin nonce first
        if (wp_verify_nonce($nonce_value, 'wps_admin_nonce')) {
            $nonce_valid = true;
        }
        // If admin nonce fails, try user nonce
        elseif (wp_verify_nonce($nonce_value, 'wps_user_action')) {
            $nonce_valid = true;
        }
        
        if (!$nonce_valid) {
            error_log('WPS: Nonce validation failed. Received nonce: ' . $nonce_value);
            wp_send_json_error(__('Security check failed', 'work-permit-system'));
            return;
        }

        // Check permissions - allow admin, reviewer, and approver to export PDFs
        if (!current_user_can('manage_options') && 
            !current_user_can('wps_review_permits') && 
            !current_user_can('wps_approve_permits')) {
            error_log('WPS: User lacks required permissions');
            wp_send_json_error(__('Insufficient permissions', 'work-permit-system'));
            return;
        }

        $permit_id = intval($_POST['permit_id']);
        if (!$permit_id) {
            error_log('WPS: Invalid permit ID: ' . ($_POST['permit_id'] ?? 'not provided'));
            wp_send_json_error(__('Invalid permit ID', 'work-permit-system'));
            return;
        }

        // Check if permit exists
        $permit = WPS_Database::get_permit_by_id($permit_id);
        if (!$permit) {
            error_log('WPS: Permit not found: ' . $permit_id);
            wp_send_json_error(__('Permit not found', 'work-permit-system'));
            return;
        }

        try {
            // Initialize PDF export class if not already loaded
            if (!class_exists('WPS_PDF_Export')) {
                require_once WPS_PLUGIN_PATH . 'includes/class-pdf-export.php';
            }
            
            $pdf_export = new WPS_PDF_Export();
            
            // Use the existing method from WPS_PDF_Export class
            $pdf_file_path = $pdf_export->generate_pdf_file_by_id($permit_id);
            
            if (!$pdf_file_path || !file_exists($pdf_file_path)) {
                error_log('WPS: PDF generation failed for permit: ' . $permit_id);
                wp_send_json_error(__('Failed to generate PDF file', 'work-permit-system'));
                return;
            }
            
            // Get just the filename for the download URL
            $filename = basename($pdf_file_path);
            
            // Return download URL using existing download handler
            $download_url = add_query_arg([
                'action' => 'download_permits_pdf',
                'file' => $filename,
                'permit_id' => $permit_id,
                'nonce' => wp_create_nonce('download_pdf_nonce')
            ], admin_url('admin-ajax.php'));
            
            wp_send_json_success([
                'download_url' => $download_url,
                'message' => __('PDF generated successfully', 'work-permit-system')
            ]);
            
        } catch (Exception $e) {
            error_log('WPS PDF Export Error: ' . $e->getMessage());
            wp_send_json_error(__('Error generating PDF: ', 'work-permit-system') . $e->getMessage());
        }
    }

    public function handle_get_permit_attachments() {
        
        // Check user login
        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in');
            return;
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wps_admin_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        // Check admin permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        // Get permit ID
        $permit_id = intval($_POST['permit_id'] ?? 0);
        if (!$permit_id) {
            wp_send_json_error('Invalid permit ID');
            return;
        }
        
        // Get permit info
        $permit_info = WPS_Database::get_permit_by_id($permit_id);
        if (!$permit_info) {
            wp_send_json_error('Permit not found');
            return;
        }
        
        // Direct database query for attachments
        global $wpdb;
        $table_name = $wpdb->prefix . 'wps_permit_documents';
        
        $documents = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $table_name 
            WHERE permit_id = %d AND is_active = 1 
            ORDER BY upload_date DESC
        ", $permit_id));
        
        // Process attachments
        $processed_attachments = array();
        foreach ($documents as $doc) {
            $processed_attachments[] = array(
                'id' => $doc->id,
                'original_filename' => $doc->original_filename,
                'file_url' => $doc->file_url,
                'file_size' => $doc->file_size,
                'formatted_file_size' => size_format($doc->file_size),
                'document_type' => $doc->document_type ?? 'supporting_document',
                'upload_date' => $doc->upload_date,
                'formatted_upload_date' => date('M j, Y g:i A', strtotime($doc->upload_date)),
                'uploaded_by_type' => $doc->uploaded_by_type ?? 'applicant',
                'description' => $doc->description ?? '',
                'file_exists' => file_exists($doc->file_path)
            );
        }
        
        wp_send_json_success(array(
            'attachments' => $processed_attachments,
            'permit_info' => array(
                'id' => $permit_info->id,
                'email_address' => $permit_info->email_address,
                'category_name' => $permit_info->category_name ?? 'Unknown Category',
                'work_area' => $permit_info->work_area ?? 'Not specified'
            )
        ));
    }

    /**
     * Process attachment for admin display
     */
    private function process_attachment_for_admin_display($attachment) {
        // Ensure we have required properties
        if (!is_object($attachment)) {
            error_log('WPS: Invalid attachment object received');
            return null;
        }
        
        // Clone the attachment to avoid modifying the original
        $processed = clone $attachment;
        
        // Ensure required properties exist with defaults
        $processed->id = $processed->id ?? 0;
        $processed->original_filename = $processed->original_filename ?? 'Unknown File';
        $processed->file_size = $processed->file_size ?? 0;
        $processed->document_type = $processed->document_type ?? 'supporting_document';
        $processed->upload_date = $processed->upload_date ?? date('Y-m-d H:i:s');
        $processed->uploaded_by_type = $processed->uploaded_by_type ?? 'applicant';
        $processed->description = $processed->description ?? '';
        
        // Generate file URL if not present
        if (empty($processed->file_url) && !empty($processed->file_path)) {
            $upload_dir = wp_upload_dir();
            $processed->file_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $processed->file_path);
        }
        
        // Add file extension
        $processed->file_extension = strtolower(pathinfo($processed->original_filename, PATHINFO_EXTENSION));
        
        // Format file size
        $processed->formatted_file_size = $this->format_file_size($processed->file_size);
        
        // Format upload date
        $processed->formatted_upload_date = date('M j, Y g:i A', strtotime($processed->upload_date));
        
        return $processed;
    }

    /**
     * Helper method to format file size
     */
    private function format_file_size($bytes) {
        if ($bytes === 0) return '0 Bytes';
        
        $k = 1024;
        $sizes = array('Bytes', 'KB', 'MB', 'GB', 'TB');
        $i = floor(log($bytes) / log($k));
        
        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }

    private function format_upload_date($date_string) {
        if (empty($date_string)) return 'Unknown date';
        
        try {
            $date = new DateTime($date_string);
            return $date->format('M j, Y g:i A');
        } catch (Exception $e) {
            return 'Invalid date';
        }
    }

}