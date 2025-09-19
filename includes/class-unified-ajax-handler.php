<?php
/**
 * Unified AJAX Handler Class (FIXED - Removed Strict Access Validation)
 * File: includes/class-unified-ajax-handler.php
 * 
 * This class consolidates all AJAX handlers for the unified dashboard system,
 * providing a single point of entry that handles all reviewer and approver actions.
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPS_Unified_Ajax_Handler {
    
    public function __construct() {
        // Register unified AJAX handlers
        add_action('wp_ajax_wps_get_permit_for_review', array($this, 'handle_get_permit_for_review'));
        add_action('wp_ajax_wps_get_permit_for_approval', array($this, 'handle_get_permit_for_approval'));
        add_action('wp_ajax_wps_reviewer_submit_decision', array($this, 'handle_reviewer_decision'));
        add_action('wp_ajax_wps_approver_submit_decision', array($this, 'handle_approver_decision'));
        add_action('wp_ajax_wps_get_permit_attachments', array($this, 'handle_get_attachments'));
        add_action('wp_ajax_wps_get_approver_permit_attachments', array($this, 'handle_get_attachments'));
        add_action('wp_ajax_wps_get_search_suggestions', array($this, 'handle_get_search_suggestions'));
        add_action('wp_ajax_wps_get_approver_search_suggestions', array($this, 'handle_get_search_suggestions'));
        add_action('wp_ajax_wps_get_approver_stats', array($this, 'handle_get_stats'));
        add_action('wp_ajax_wps_get_reviewer_stats', array($this, 'handle_get_stats'));
        add_action('wp_ajax_wps_approver_bulk_action', array($this, 'handle_bulk_action'));
        add_action('wp_ajax_wps_reviewer_bulk_action', array($this, 'handle_bulk_action'));
    }
    
    /**
     * Validate basic AJAX requirements
     */
    private function validate_ajax_request($nonce_action = 'wps_user_action') {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], $nonce_action)) {
            wp_send_json_error('Security check failed');
            return false;
        }
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in');
            return false;
        }
        
        return true;
    }
    
    /**
     * Handle get permit for review
     */
    public function handle_get_permit_for_review() {
        if (!$this->validate_ajax_request()) {
            return;
        }
        
        // Check user capabilities
        if (!current_user_can('wps_review_permits')) {
            wp_send_json_error('You do not have permission to review permits');
            return;
        }
        
        $permit_id = $this->validate_permit_id();
        if (!$permit_id) {
            return;
        }
        
        // Load reviewer database class
        require_once WPS_PLUGIN_PATH . 'includes/class-reviewer-database.php';
        
        // Get permit details for review
        $permit_data = WPS_Reviewer_Database::get_permit_for_review($permit_id, get_current_user_id());
        
        if (!$permit_data) {
            wp_send_json_error('Permit not found or access denied');
            return;
        }
        
        wp_send_json_success($permit_data);
    }
    
    /**
     * Handle get permit for approval
     */
    public function handle_get_permit_for_approval() {
        if (!$this->validate_ajax_request()) {
            return;
        }
        
        // Check user capabilities
        if (!current_user_can('wps_approve_permits')) {
            wp_send_json_error('You do not have permission to approve permits');
            return;
        }
        
        $permit_id = $this->validate_permit_id();
        if (!$permit_id) {
            return;
        }
        
        // Load approver database class
        require_once WPS_PLUGIN_PATH . 'includes/class-approver-database.php';
        
        // Get permit details for approval
        $permit_data = WPS_Approver_Database::get_permit_for_approval($permit_id, get_current_user_id());
        
        if (!$permit_data) {
            wp_send_json_error('Permit not found or access denied');
            return;
        }
        
        wp_send_json_success($permit_data);
    }
    
    /**
     * Handle reviewer decision submission - FIXED VERSION
     */
    public function handle_reviewer_decision() {
        if (!$this->validate_ajax_request()) {
            return;
        }
        
        // Check user capabilities
        if (!current_user_can('wps_review_permits')) {
            wp_send_json_error('You do not have permission to review permits');
            return;
        }
        
        if (!$this->validate_required_fields(['permit_id', 'status'])) {
            return;
        }
        
        $permit_id = $this->validate_permit_id();
        if (!$permit_id) {
            return;
        }
        
        $status = sanitize_text_field($_POST['status']);
        $comment = isset($_POST['comment']) ? sanitize_textarea_field($_POST['comment']) : '';
        
        // Validate status
        if (!in_array($status, ['pending_approval', 'cancelled'])) {
            wp_send_json_error('Invalid status');
            return;
        }
        
        // Load reviewer database class
        require_once WPS_PLUGIN_PATH . 'includes/class-reviewer-database.php';
        
        // Use the correct method name that exists in WPS_Reviewer_Database
        $result = WPS_Reviewer_Database::update_permit_by_reviewer(
            $permit_id, 
            $status, 
            $comment,
            get_current_user_id()
        );
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
            return;
        }
        
        if ($result) {
            // Send notifications (optional - you may want to add this functionality)
            $this->send_reviewer_decision_notifications($permit_id, $status, $comment, get_current_user_id());
            
            wp_send_json_success('Decision submitted successfully');
        } else {
            wp_send_json_error('Failed to submit decision');
        }
    }

    /**
     * Send notifications for reviewer decisions - ADD THIS METHOD
     */
    private function send_reviewer_decision_notifications($permit_id, $status, $comment, $reviewer_user_id) {
        try {
            // Check if email class exists
            if (!class_exists('WPS_Email')) {
                error_log('WPS: Email class not found - notifications skipped');
                return false;
            }
            
            // Get permit details
            if (!class_exists('WPS_Database')) {
                error_log('WPS: Database class not found - cannot get permit details');
                return false;
            }
            
            $permit = WPS_Database::get_permit_by_id($permit_id);
            if (!$permit) {
                error_log('WPS: Permit not found for notifications: ' . $permit_id);
                return false;
            }
            
            $reviewer_user = get_user_by('ID', $reviewer_user_id);
            if (!$reviewer_user) {
                error_log('WPS: Reviewer user not found: ' . $reviewer_user_id);
                return false;
            }
            
            // Prepare comment data
            $comment_data = array(
                'user_name' => $reviewer_user->display_name,
                'user_email' => $reviewer_user->user_email,
                'comment' => $comment,
                'action_taken' => $status === 'cancelled' ? 'Rejected' : 'Approved for final approval',
                'created_date' => current_time('mysql')
            );
            
            $notification_sent = false;
            
            switch ($status) {
                case 'pending_approval':
                    // Notify approver when reviewer approves
                    if (method_exists('WPS_Email', 'send_approver_notification')) {
                        $result = WPS_Email::send_approver_notification($permit->id, $comment_data);
                        error_log('WPS: Approver notification result: ' . ($result ? 'success' : 'failed'));
                        $notification_sent = $result;
                    } else {
                        error_log('WPS: send_approver_notification method not found');
                    }
                    break;
                    
                case 'cancelled':
                    // Notify applicant when rejected
                    if (method_exists('WPS_Email', 'send_status_notification_with_pdf')) {
                        $result = WPS_Email::send_status_notification_with_pdf($permit->id, 'cancelled', $comment_data);
                        error_log('WPS: Rejection notification result: ' . ($result ? 'success' : 'failed'));
                        $notification_sent = $result;
                    } else {
                        error_log('WPS: send_status_notification_with_pdf method not found');
                    }
                    break;
            }
            
            return $notification_sent;
            
        } catch (Exception $e) {
            error_log('WPS: Error sending reviewer notifications: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Handle approver decision submission
     */
    public function handle_approver_decision() {
        if (!$this->validate_ajax_request()) {
            return;
        }
        
        // Check user capabilities
        if (!current_user_can('wps_approve_permits')) {
            wp_send_json_error('You do not have permission to approve permits');
            return;
        }
        
        if (!$this->validate_required_fields(['permit_id', 'status'])) {
            return;
        }
        
        $permit_id = $this->validate_permit_id();
        if (!$permit_id) {
            return;
        }
        
        $status = sanitize_text_field($_POST['status']);
        $comment = isset($_POST['comment']) ? sanitize_textarea_field($_POST['comment']) : '';
        
        // Validate status
        if (!in_array($status, ['approved', 'cancelled'])) {
            wp_send_json_error('Invalid status');
            return;
        }
        
        // Load approver database class
        require_once WPS_PLUGIN_PATH . 'includes/class-approver-database.php';
        
        // Submit approver decision
        $result = WPS_Approver_Database::update_permit_by_approver(
            $permit_id, 
            $status, 
            $comment,
            get_current_user_id()
        );
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
            return;
        }
        
        if ($result) {
            // Send notifications for approver decisions
            $this->send_approver_decision_notifications($permit_id, $status, $comment, get_current_user_id());
            
            wp_send_json_success('Decision submitted successfully');
        } else {
            wp_send_json_error('Failed to submit decision');
        }
    }

    /**
    * ADD this method to WPS_Unified_Ajax_Handler for approver notifications
    */
    private function send_approver_decision_notifications($permit_id, $status, $comment, $approver_user_id) {
        try {
            // Check if email class exists
            if (!class_exists('WPS_Email')) {
                error_log('WPS: Email class not found - notifications skipped');
                return false;
            }
            
            // Get permit details
            if (!class_exists('WPS_Database')) {
                error_log('WPS: Database class not found - cannot get permit details');
                return false;
            }
            
            $permit = WPS_Database::get_permit_by_id($permit_id);
            if (!$permit) {
                error_log('WPS: Permit not found for notifications: ' . $permit_id);
                return false;
            }
            
            $approver_user = get_user_by('ID', $approver_user_id);
            if (!$approver_user) {
                error_log('WPS: Approver user not found: ' . $approver_user_id);
                return false;
            }
            
            // Prepare comment data
            $comment_data = array(
                'user_name' => $approver_user->display_name,
                'user_email' => $approver_user->user_email,
                'comment' => $comment,
                'action_taken' => $status === 'cancelled' ? 'Final rejection' : 'Final approval given',
                'created_date' => current_time('mysql')
            );
            
            $notification_sent = false;
            
            switch ($status) {
                case 'approved':
                    // Notify applicant when approved
                    if (method_exists('WPS_Email', 'send_status_notification_with_pdf')) {
                        $result = WPS_Email::send_status_notification_with_pdf($permit->id, 'approved', $comment_data);
                        error_log('WPS: Approval notification result: ' . ($result ? 'success' : 'failed'));
                        $notification_sent = $result;
                    } else {
                        error_log('WPS: send_status_notification_with_pdf method not found');
                    }
                    break;
                    
                case 'cancelled':
                    // Notify applicant and reviewer when rejected
                    if (method_exists('WPS_Email', 'send_status_notification_with_pdf')) {
                        $result = WPS_Email::send_status_notification_with_pdf($permit->id, 'cancelled', $comment_data);
                        error_log('WPS: Final rejection notification result: ' . ($result ? 'success' : 'failed'));
                        $notification_sent = $result;
                    } else {
                        error_log('WPS: send_status_notification_with_pdf method not found');
                    }
                    break;
            }
            
            return $notification_sent;
            
        } catch (Exception $e) {
            error_log('WPS: Error sending approver notifications: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Handle get attachments (unified handler) - FIXED VERSION
     * REMOVED STRICT ACCESS VALIDATION TO ALLOW VIEW DETAILS TO WORK
     */
    public function handle_get_attachments() {
        if (!$this->validate_ajax_request()) {
            return;
        }
        
        $permit_id = $this->validate_permit_id();
        if (!$permit_id) {
            return;
        }
        
        // Determine which handler to use based on user capability and action
        $action = $_POST['action'] ?? '';
        
        if ($action === 'wps_get_approver_permit_attachments' || current_user_can('wps_approve_permits')) {
            // Use approver handler
            if (!current_user_can('wps_approve_permits')) {
                wp_send_json_error('You do not have permission to approve permits');
                return;
            }
            
            require_once WPS_PLUGIN_PATH . 'includes/class-approver-database.php';
            
            // FIXED: Use direct database query approach without strict access validation
            $attachments_result = $this->get_attachments_direct($permit_id, 'approver');
            
        } elseif (current_user_can('wps_review_permits')) {
            // Use reviewer handler
            require_once WPS_PLUGIN_PATH . 'includes/class-reviewer-database.php';
            
            // FIXED: Use direct database query approach without strict access validation
            $attachments_result = $this->get_attachments_direct($permit_id, 'reviewer');
            
        } else {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        // Handle the response
        if (is_array($attachments_result)) {
            if (isset($attachments_result['error']) && !empty($attachments_result['error'])) {
                wp_send_json_error($attachments_result['error']);
                return;
            }
            
            // Success - return the structured data
            wp_send_json_success(array(
                'attachments' => $attachments_result['attachments'] ?? array(),
                'permit_info' => $attachments_result['permit_info'] ?? null
            ));
        } else {
            wp_send_json_error('Failed to load attachments');
        }
    }

    /**
     * FIXED: Direct attachment retrieval without strict access validation
     */
    private function get_attachments_direct($permit_id, $user_type) {
        // First, verify the permit exists (basic check only)
        if (!class_exists('WPS_Database')) {
            return array(
                'attachments' => array(),
                'permit_info' => null,
                'error' => 'Database class not available'
            );
        }
        
        $permit_info = WPS_Database::get_permit_by_id($permit_id);
        if (!$permit_info) {
            return array(
                'attachments' => array(),
                'permit_info' => null,
                'error' => 'Permit not found'
            );
        }
        
        // Check if Document Manager is available
        if (!class_exists('WPS_Document_Manager')) {
            return array(
                'attachments' => $permit_info,
                'permit_info' => $permit_info,
                'error' => 'Document Manager not available'
            );
        }
        
        // Get all documents for this specific permit - REMOVED STRICT VALIDATION
        try {
            $all_attachments = WPS_Document_Manager::get_permit_documents($permit_id, null, true);
            
            // Validate that permit IDs match
            $validated_attachments = array();
            foreach ($all_attachments as $doc) {
                if (intval($doc->permit_id) === $permit_id) {
                    $validated_attachments[] = $doc;
                }
            }
            
            $all_attachments = $validated_attachments;
            
        } catch (Exception $e) {
            error_log('WPS Unified: Error getting documents: ' . $e->getMessage());
            return array(
                'attachments' => array(),
                'permit_info' => $permit_info,
                'error' => 'Error retrieving documents: ' . $e->getMessage()
            );
        }
        
        // Process attachments for frontend display
        $processed_attachments = array();
        foreach ($all_attachments as $attachment) {
            $processed = $this->process_attachment_for_display($attachment);
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
     * Process attachment for display
     */
    private function process_attachment_for_display($attachment) {
        // Ensure we have required properties
        if (!is_object($attachment)) {
            return null;
        }
        
        // Add any processing needed for display
        $processed = clone $attachment;
        
        // Add file extension
        if (isset($processed->original_filename)) {
            $processed->file_extension = strtolower(pathinfo($processed->original_filename, PATHINFO_EXTENSION));
        }
        
        // Format file size if not already formatted
        if (isset($processed->file_size) && is_numeric($processed->file_size)) {
            $processed->formatted_file_size = $this->format_file_size($processed->file_size);
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
    private function format_file_size($bytes) {
        if ($bytes === 0) return '0 Bytes';
        
        $k = 1024;
        $sizes = array('Bytes', 'KB', 'MB', 'GB', 'TB');
        $i = floor(log($bytes) / log($k));
        
        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }
    
    /**
     * Handle search suggestions (unified handler) - FIXED VERSION
     */
    public function handle_get_search_suggestions() {
        if (!$this->validate_ajax_request()) {
            return;
        }
        
        if (!$this->validate_required_fields(['query', 'search_type'])) {
            return;
        }
        
        $query = sanitize_text_field($_POST['query']);
        $search_type = sanitize_text_field($_POST['search_type']);
        
        // Determine which handler to use based on action and capability
        $action = $_POST['action'] ?? '';
        
        if ($action === 'wps_get_approver_search_suggestions' || current_user_can('wps_approve_permits')) {
            // Use approver handler
            if (!current_user_can('wps_approve_permits')) {
                wp_send_json_error('You do not have permission to access this feature');
                return;
            }
            
            // Use approver handler with correct parameter order
            require_once WPS_PLUGIN_PATH . 'includes/class-approver-database.php';
            $suggestions = WPS_Approver_Database::get_search_suggestions(get_current_user_id(), $search_type, $query);
            
        } elseif (current_user_can('wps_review_permits')) {
            // Use reviewer handler with correct parameter order
            require_once WPS_PLUGIN_PATH . 'includes/class-reviewer-database.php';
            $suggestions = WPS_Reviewer_Database::get_search_suggestions(get_current_user_id(), $search_type, $query);
            
        } else {
            wp_send_json_error('You do not have permission to access this feature');
            return;
        }
        
        wp_send_json_success($suggestions);
    }
    
    /**
     * Handle get dashboard stats (unified handler)
     */
    public function handle_get_stats() {
        if (!$this->validate_ajax_request()) {
            return;
        }
        
        // Determine which handler to use based on capability
        if (current_user_can('wps_approve_permits')) {
            // Use approver handler
            require_once WPS_PLUGIN_PATH . 'includes/class-approver-database.php';
            $stats = WPS_Approver_Database::get_approver_dashboard_stats(get_current_user_id());
            
        } elseif (current_user_can('wps_review_permits')) {
            // Use reviewer handler
            require_once WPS_PLUGIN_PATH . 'includes/class-reviewer-database.php';
            $stats = WPS_Reviewer_Database::get_reviewer_dashboard_stats(get_current_user_id());
            
        } else {
            wp_send_json_error('You do not have permission to view this data');
            return;
        }
        
        if ($stats) {
            wp_send_json_success($stats);
        } else {
            wp_send_json_error('Stats not available');
        }
    }
    
    /**
     * Handle bulk actions (unified handler)
     */
    public function handle_bulk_action() {
        if (!$this->validate_ajax_request()) {
            return;
        }
        
        if (!$this->validate_required_fields(['bulk_action', 'permit_ids'])) {
            return;
        }
        
        $bulk_action = sanitize_text_field($_POST['bulk_action']);
        $permit_ids = array_map('intval', $_POST['permit_ids']);
        
        // Determine which handler to use based on action and capability
        $action = $_POST['action'] ?? '';
        
        if ($action === 'wps_approver_bulk_action' || current_user_can('wps_approve_permits')) {
            // Use approver handler
            if (!current_user_can('wps_approve_permits')) {
                wp_send_json_error('You do not have permission to perform bulk actions');
                return;
            }
            
            require_once WPS_PLUGIN_PATH . 'includes/class-approver-database.php';
            $result = WPS_Approver_Database::handle_bulk_action($bulk_action, $permit_ids, get_current_user_id());
            
        } elseif (current_user_can('wps_review_permits')) {
            // Use reviewer handler
            require_once WPS_PLUGIN_PATH . 'includes/class-reviewer-database.php';
            $result = WPS_Reviewer_Database::handle_bulk_action($bulk_action, $permit_ids, get_current_user_id());
            
        } else {
            wp_send_json_error('You do not have permission to perform bulk actions');
            return;
        }
        
        if ($result) {
            wp_send_json_success('Bulk action completed successfully');
        } else {
            wp_send_json_error('Failed to complete bulk action');
        }
    }
    
    /**
     * Log AJAX errors for debugging
     */
    private function log_ajax_error($message, $context = array()) {
        error_log('WPS Unified AJAX Error: ' . $message . ' | Context: ' . print_r($context, true));
    }
    
    /**
     * Enhanced error handling for AJAX requests
     */
    private function handle_ajax_error($message, $data = null) {
        $this->log_ajax_error($message, array(
            'user_id' => get_current_user_id(),
            'action' => $_POST['action'] ?? 'unknown',
            'data' => $data
        ));
        
        wp_send_json_error($message);
    }
    
    /**
     * Check if required POST data exists
     */
    private function validate_required_fields($required_fields) {
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                $this->handle_ajax_error("Missing required field: {$field}");
                return false;
            }
        }
        return true;
    }
    
    /**
     * Sanitize and validate permit ID
     */
    private function validate_permit_id() {
        if (!isset($_POST['permit_id'])) {
            $this->handle_ajax_error('Missing permit ID');
            return false;
        }
        
        $permit_id = intval($_POST['permit_id']);
        if (!$permit_id || $permit_id <= 0) {
            $this->handle_ajax_error('Invalid permit ID');
            return false;
        }
        
        return $permit_id;
    }
    
    /**
     * Check if user has access to specific permit
     */
    private function validate_permit_access($permit_id, $user_id, $role_type) {
        // Load appropriate database class
        if ($role_type === 'approver') {
            require_once WPS_PLUGIN_PATH . 'includes/class-approver-database.php';
            return WPS_Approver_Database::validate_approver_permit_access($permit_id, $user_id);
        } else {
            require_once WPS_PLUGIN_PATH . 'includes/class-reviewer-database.php';
            return WPS_Reviewer_Database::validate_reviewer_permit_access($permit_id, $user_id);
        }
    }
    
    /**
     * Generic AJAX handler with enhanced validation
     * This method can be used as a template for creating new unified handlers
     */
    public function handle_generic_action() {
        // Validate basic requirements
        if (!$this->validate_ajax_request()) {
            return;
        }
        
        // Validate required fields
        if (!$this->validate_required_fields(['permit_id'])) {
            return;
        }
        
        // Get and validate permit ID
        $permit_id = $this->validate_permit_id();
        if (!$permit_id) {
            return;
        }
        
        // Determine user role and validate access
        $current_user_id = get_current_user_id();
        $role_type = current_user_can('wps_approve_permits') ? 'approver' : 'reviewer';
        
        if (!current_user_can('wps_approve_permits') && !current_user_can('wps_review_permits')) {
            $this->handle_ajax_error('Insufficient permissions');
            return;
        }
        
        // Validate permit access
        $access_check = $this->validate_permit_access($permit_id, $current_user_id, $role_type);
        if (!$access_check['valid']) {
            $this->handle_ajax_error($access_check['error']);
            return;
        }
        
        // Delegate to appropriate database handler
        if ($role_type === 'approver') {
            require_once WPS_PLUGIN_PATH . 'includes/class-approver-database.php';
            // Call appropriate approver database method
        } else {
            require_once WPS_PLUGIN_PATH . 'includes/class-reviewer-database.php';
            // Call appropriate reviewer database method
        }
        
        wp_send_json_success('Action completed successfully');
    }
}

// Initialize the unified AJAX handler
new WPS_Unified_Ajax_Handler();