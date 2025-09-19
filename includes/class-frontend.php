<?php
/**
 * Complete Fixed Frontend functionality class - Proper Two-Document Handling
 * File: includes/class-frontend.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPS_Frontend {
    
    public function __construct() {
        add_shortcode('work_permit_form', array($this, 'display_permit_form'));
        add_action('wp_ajax_submit_work_permit', array($this, 'handle_permit_submission'));
        add_action('wp_ajax_nopriv_submit_work_permit', array($this, 'handle_permit_submission'));
    }
    
    public function display_permit_form() {
        ob_start();
        include WPS_PLUGIN_PATH . 'templates/frontend/permit-form.php';
        return ob_get_clean();
    }
    
    public function handle_permit_submission() {
        if (!wp_verify_nonce($_POST['nonce'], 'submit_work_permit')) {
            wp_die(__('Security check failed', 'work-permit-system'));
        }
        
        // Server-side email confirmation check if needed
        if (isset($_POST['confirm_email_address'])) {
            $email = sanitize_email($_POST['email_address']);
            $confirm_email = sanitize_email($_POST['confirm_email_address']);
            
            if ($email !== $confirm_email) {
                wp_send_json_error(array(
                    'message' => __('Email addresses do not match.', 'work-permit-system')
                ));
            }
        }
        
        // Validate form data
        $validation_result = $this->validate_and_sanitize_input($_POST, $_FILES);
        
        if (is_wp_error($validation_result)) {
            wp_send_json_error(array('message' => $validation_result->get_error_message()));
        }
        
        // Insert permit into database FIRST
        $permit_id = WPS_Database::insert_permit($validation_result);
        
        if (!$permit_id) {
            wp_send_json_error(array('message' => __('Failed to submit permit. Please try again.', 'work-permit-system')));
        }
        
        // Handle both document uploads with proper tracking
        $document_upload_results = $this->handle_supporting_document_uploads($_FILES, $permit_id);
        
        // Log document upload results but don't fail the permit submission
        $successful_uploads = 0;
        $upload_errors = array();
        $uploaded_files = array();
        
        foreach ($document_upload_results as $type => $result) {
            if (is_wp_error($result)) {
                $error_message = $result->get_error_message();
                $upload_errors[] = ucfirst($type) . ': ' . $error_message;
                error_log('WPS Frontend: ' . ucfirst($type) . ' document upload failed for permit ' . $permit_id . ': ' . $error_message);
            } else if ($result && !empty($result['document_id'])) {
                $successful_uploads++;
                $uploaded_files[] = $result['original_filename'];
                if (!empty($result['is_duplicate'])) {
                    error_log('WPS Frontend: ' . ucfirst($type) . ' was duplicate for permit ' . $permit_id . ' (ID: ' . $result['document_id'] . ')');
                } else {
                }
            } else if ($result === null) {
            }
        }
        
        // Send notifications
        $this->send_submission_notifications($permit_id);
        
        // Prepare response
        $response_message = __('Work permit submitted successfully! You will receive an email once reviewed.', 'work-permit-system');
        
        if ($successful_uploads > 0) {
            $response_message .= ' ' . sprintf(__('%d supporting document(s) uploaded successfully.', 'work-permit-system'), $successful_uploads);
            if (!empty($uploaded_files)) {
                $response_message .= ' (' . implode(', ', $uploaded_files) . ')';
            }
        }
        
        if (!empty($upload_errors)) {
            $response_message .= ' ' . __('Note: Some document uploads failed:', 'work-permit-system') . ' ' . implode(', ', $upload_errors);
        }
        
        wp_send_json_success(array(
            'message' => $response_message,
            'permit_id' => $permit_id,
            'has_supporting_documents' => $successful_uploads > 0,
            'supporting_document_count' => $successful_uploads,
            'uploaded_files' => $uploaded_files,
            'upload_errors' => $upload_errors
        ));
    }
    
    /**
     * Handle both personnel and details document uploads separately
     */
    private function handle_supporting_document_uploads($files_data, $permit_id) {
        
        $results = array();
        
        // Handle personnel extra info document with specific document type
        $results['personnel'] = $this->handle_single_document_upload(
            $files_data, 
            'personnel_extra_info', 
            $permit_id, 
            'personnel_document'
        );
        
        // Handle details extra info document with specific document type  
        $results['details'] = $this->handle_single_document_upload(
            $files_data, 
            'details_extra_info', 
            $permit_id, 
            'details_document'
        );
        
        return $results;
    }
    
    /**
     * Handle single document upload with proper validation and NO duplicate checking
     */
    private function handle_single_document_upload($files_data, $file_key, $permit_id, $document_type) {
        // Check if document was uploaded
        if (empty($files_data[$file_key]['name']) || 
            $files_data[$file_key]['error'] === UPLOAD_ERR_NO_FILE) {
            return null; // No file uploaded, not an error
        }
        
        // Check if document manager is available
        if (!class_exists('WPS_Document_Manager')) {
            error_log('WPS Frontend: Document Manager class not available');
            return new WP_Error(
                'document_manager_unavailable',
                __('Document upload system is not available.', 'work-permit-system')
            );
        }
        
        $file = $files_data[$file_key];
        
        // Use Document Manager to handle the upload
        $upload_result = WPS_Document_Manager::handle_supporting_document_upload(
            $file,
            $permit_id,
            null, // user_id - will be null for frontend submissions
            'applicant',
            $document_type
        );
        
        if (is_wp_error($upload_result)) {
            error_log('WPS Frontend: ' . ucfirst($document_type) . ' upload failed: ' . $upload_result->get_error_message());
            return $upload_result;
        }
        
        if (!empty($upload_result['is_duplicate'])) {
            error_log('WPS Frontend: ' . ucfirst($document_type) . ' was identified as duplicate, using existing file');
        } else {
        }
        
        return $upload_result;
    }
    
    /**
     * Send submission notifications with proper document checking
     */
    private function send_submission_notifications($permit_id) {
        $permit = WPS_Database::get_permit_by_id($permit_id);
        
        if (!$permit) {
            error_log('WPS Frontend: Could not load permit for notifications: ' . $permit_id);
            return;
        }
        
        // Send notification to reviewer if assigned
        if ($permit->reviewer_user_id && class_exists('WPS_Email')) {
            // Use the enhanced notification method that checks for actual document existence
            if (method_exists('WPS_Email', 'send_reviewer_notification_with_docs')) {
                WPS_Email::send_reviewer_notification_with_docs($permit_id);
            } else {
                WPS_Email::send_reviewer_notification($permit_id);
            }
        } else {
            error_log('WPS Frontend: No reviewer assigned or email class unavailable for permit ' . $permit_id);
        }
        
        // Send confirmation to applicant - WITHOUT any attachments
        WPS_Email::send_applicant_confirmation($permit_id);
    }
    
    
    /**
     * Enhanced validate_and_sanitize_input
     */
    private function validate_and_sanitize_input($post_data, $files_data = array()) {
        $required_fields = array(
            'email_address'  => 'sanitize_email',
            'phone_number'    => 'sanitize_text_field',
            'issued_to'      => 'sanitize_text_field',
            'tenant'         => 'sanitize_text_field',
            'work_area'      => 'sanitize_text_field',
            'tenant_field'   => 'sanitize_textarea_field',
            'requested_start_date' => 'sanitize_text_field',
            'requested_start_time' => 'sanitize_text_field',
            'requested_end_date' => 'sanitize_text_field',
            'requested_end_time' => 'sanitize_text_field',
            'personnel_list' => array($this, 'sanitize_personnel_list'),
            'requester_position' => 'sanitize_text_field',
        );
        
        $sanitized_data = array();
        
        // Validate and sanitize required fields
        foreach ($required_fields as $field => $sanitize_function) {
            if (!isset($post_data[$field])) {
                return new WP_Error(
                    'missing_field',
                    sprintf(__('The field "%s" is required.', 'work-permit-system'), $this->format_field_name($field))
                );
            }
            
            $field_value = is_array($post_data[$field]) ? implode(', ', $post_data[$field]) : $post_data[$field];
            
            if (empty(trim($field_value))) {
                return new WP_Error(
                    'missing_field',
                    sprintf(__('The field "%s" is required.', 'work-permit-system'), $this->format_field_name($field))
                );
            }
            
            $sanitized_data[$field] = call_user_func($sanitize_function, $post_data[$field]);
        }
    
        // Validate email format
        if (!is_email($sanitized_data['email_address'])) {
            return new WP_Error(
                'invalid_email',
                __('Please provide a valid email address.', 'work-permit-system')
            );
        }
        
        // Enhanced single selection validation
        $issued_for_result = $this->validate_strict_single_selection($post_data, 'issued_for', 'Work Type (Issued For)');
        $requestor_type_result = $this->validate_strict_single_selection($post_data, 'requestor_type', 'Requestor Type');
        
        if (is_wp_error($issued_for_result)) {
            return $issued_for_result;
        }
        
        if (is_wp_error($requestor_type_result)) {
            return $requestor_type_result;
        }
        
        $sanitized_data['issued_for'] = $issued_for_result;
        $sanitized_data['requestor_type'] = $requestor_type_result;
        
        // Find work_category_id
        $work_category_id = $this->get_work_category_id($issued_for_result);
        if (!$work_category_id) {
            $available_categories = $this->get_allowed_work_categories();
            $categories_list = !empty($available_categories) ? implode(', ', $available_categories) : 'None available';
            
            return new WP_Error(
                'invalid_category',
                sprintf(__('Invalid work category selected: "%s". Available categories: %s', 'work-permit-system'), 
                    $issued_for_result,
                    $categories_list
                )
            );
        }
        $sanitized_data['work_category_id'] = $work_category_id;

        $issued_for_value = $sanitized_data['issued_for'];
        // Check if "Others" category was selected
        if ($issued_for_value === 'Others') {
            // Others was selected, now validate the specification
            $other_specify = isset($post_data['other_specify']) ? trim(sanitize_text_field($post_data['other_specify'])) : '';
            
            if (empty($other_specify)) {
                return new WP_Error(
                    'other_specify_required',
                    __('When selecting "Others", you must specify the type of work.', 'work-permit-system')
                );
            }
            
            if (strlen($other_specify) < 3) {
                return new WP_Error(
                    'other_specify_too_short',
                    __('Please provide a more detailed description (minimum 3 characters).', 'work-permit-system')
                );
            }
            
            if (strlen($other_specify) > 200) {
                return new WP_Error(
                    'other_specify_too_long',
                    __('Description is too long (maximum 200 characters).', 'work-permit-system')
                );
            }
            
            // Add the specification to sanitized data
            $sanitized_data['other_specify'] = $other_specify;
        } else {
            // Not Others category, make sure no specification is accidentally included
            unset($sanitized_data['other_specify']);
        }
        
        return $sanitized_data;
    }
    
    /**
     * Enhanced strict single selection validation
     */
    private function validate_strict_single_selection($post_data, $field_name, $field_label) {
        $possible_keys = array(
            $field_name . '[]',
            $field_name,
            $field_name . '[0]'
        );
        
        $field_values = null;
        
        foreach ($possible_keys as $key) {
            if (isset($post_data[$key])) {
                $field_values = $post_data[$key];
                break;
            }
        }
        
        if ($field_values === null) {
            return new WP_Error(
                'missing_' . $field_name,
                sprintf(__('Please select an option for "%s".', 'work-permit-system'), $field_label)
            );
        }
        
        // Normalize to array and sanitize
        if (is_array($field_values)) {
            $selected_values = array_filter(array_map('trim', array_map('stripslashes', array_map('sanitize_text_field', $field_values))));
        } else {
            $sanitized_value = trim(stripslashes(sanitize_text_field($field_values)));
            $selected_values = !empty($sanitized_value) ? array($sanitized_value) : array();
        }
        
        // Must have exactly one selection
        if (empty($selected_values)) {
            return new WP_Error(
                'empty_' . $field_name,
                sprintf(__('Please select one option for "%s".', 'work-permit-system'), $field_label)
            );
        }
        
        if (count($selected_values) > 1) {
            return new WP_Error(
                'multiple_' . $field_name,
                sprintf(__('Only one option can be selected for "%s".', 'work-permit-system'), $field_label)
            );
        }
        
        $selected_value = $selected_values[0];
        
        // Field-specific processing
        if ($field_name === 'issued_for') {
            return $this->process_and_validate_issued_for($selected_value, $post_data);
        } elseif ($field_name === 'requestor_type') {
            return $this->process_and_validate_requestor_type($selected_value);
        }
        
        return $selected_value;
    }
    
    /**
     * Process and validate issued_for selection
     */
    private function process_and_validate_issued_for($selected_value, $post_data) {
        $allowed_values = $this->get_allowed_work_categories();
        
        if (empty($allowed_values)) {
            return new WP_Error(
                'no_categories_available',
                __('No work categories are currently available.', 'work-permit-system')
            );
        }
        
        // Handle "Others" option
        if ($selected_value === 'Others') {
            if (!in_array('Others', $allowed_values)) {
                return new WP_Error(
                    'other_not_available',
                    __('The "Others" work category is not currently available.', 'work-permit-system')
                );
            }
            
            $other_specify = isset($post_data['other_specify']) ? trim(sanitize_text_field($post_data['other_specify'])) : '';
            
            if (empty($other_specify)) {
                return new WP_Error(
                    'other_specify_required',
                    __('When selecting "Others", you must specify the type of work.', 'work-permit-system')
                );
            }
            
            return 'Others';
        } else {
            // Validate against allowed predefined values
            if (!in_array($selected_value, $allowed_values)) {
                return new WP_Error(
                    'invalid_issued_for',
                    sprintf(__('Invalid work type selected: "%s".', 'work-permit-system'), $selected_value)
                );
            }
            
            return $selected_value;
        }
    }
    
    /**
     * Process and validate requestor_type selection
     */
    private function process_and_validate_requestor_type($selected_value) {
        $allowed_values = array(
            "In-house Crew/Contractor",
            "Tenant's Personnel", 
            "Tenant's Contractor/Supplier"
        );
        
        if (!in_array($selected_value, $allowed_values, true)) {
            return new WP_Error(
                'invalid_requestor_type',
                sprintf(__('Invalid requestor type selected: "%s".', 'work-permit-system'), $selected_value)
            );
        }
        
        return $selected_value;
    }
    
    /**
     * Get allowed work categories from database
     */
    private function get_allowed_work_categories() {
        $categories = WPS_Database::get_all_categories(true);
        
        if (empty($categories)) {
            return array();
        }
        
        return array_column($categories, 'category_name');
    }
    
    /**
     * Get work category ID
     */
    private function get_work_category_id($issued_for) {
        global $wpdb;
        $categories_table = $wpdb->prefix . 'wps_work_categories';
        
        $category_name = $issued_for;
        
        // Handle "Others" variants
        if ($issued_for === 'Others' || strpos($issued_for, 'Others') !== false) {
            $category_name = 'Others';
        }
        
        $category_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $categories_table WHERE category_name = %s AND is_active = 1",
            $category_name
        ));
        
        if ($wpdb->last_error) {
            error_log('WPS Database Error in get_work_category_id: ' . $wpdb->last_error);
            return null;
        }
        
        return $category_id ? intval($category_id) : null;
    }
    
    /**
     * Custom sanitization for personnel_list array
     */
    private function sanitize_personnel_list($personnel_list) {
        if (is_array($personnel_list)) {
            $sanitized_names = array_map('sanitize_text_field', $personnel_list);
            $sanitized_names = array_filter($sanitized_names);
            return implode(', ', $sanitized_names);
        } else {
            return sanitize_text_field($personnel_list);
        }
    }
    
    /**
     * Format field name for display in error messages
     */
    private function format_field_name($field_name) {
        $formatted = str_replace('_', ' ', $field_name);
        return ucwords($formatted);
    }
}