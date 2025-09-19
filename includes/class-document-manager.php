<?php
/**
 * FIXED Document Manager Class - Consistent Unique Filename Convention
 * File: includes/class-document-manager.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPS_Document_Manager {
    
    private static $allowed_types = array(
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
        'application/msword', // .doc
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp'
    );
    
    private static $max_file_size = 5242880; // 5MB in bytes
    
    /**
     * MAIN UPLOAD HANDLER - Using ONLY filename-based uniqueness
     */
    public static function handle_supporting_document_upload($file_data, $permit_id, $uploaded_by_user_id = null, $uploaded_by_type = 'applicant', $document_type = 'supporting_document') {
        // Get permit details for unique filename generation
        $permit = WPS_Database::get_permit_by_id($permit_id);
        if (!$permit) {
            return new WP_Error('permit_not_found', __('Permit not found.', 'work-permit-system'));
        }
        
        // STEP 1: Validate file first
        $validation_result = self::validate_uploaded_file($file_data);
        if (is_wp_error($validation_result)) {
            return $validation_result;
        }
        
        // STEP 2: Create upload directory
        $upload_result = self::setup_upload_directory();
        if (is_wp_error($upload_result)) {
            return $upload_result;
        }
        
        $upload_dir = $upload_result['path'];
        $upload_url = $upload_result['url'];
        
        // STEP 3: Generate GUARANTEED unique filename using your format
        $unique_filename = self::generate_guaranteed_unique_filename(
            $file_data['name'], 
            $permit_id,
            $permit->tenant,
            $upload_dir
        );
        
        $file_path = $upload_dir . '/' . $unique_filename;
        
        // STEP 4: Final safety check - this should never be needed with proper unique generation
        if (file_exists($file_path)) {
            error_log('WPS Document Manager: WARNING - Unique filename generation failed, file exists: ' . $unique_filename);
            return new WP_Error(
                'filename_collision',
                __('Unable to generate unique filename. Please try again.', 'work-permit-system')
            );
        }
        
        // STEP 5: Check for logical duplicates (same permit + document type + original filename)
        // This is separate from filename uniqueness
        $existing_doc = self::check_for_logical_duplicate($permit_id, $document_type, $file_data['name']);
        
        if ($existing_doc) {
            error_log('WPS Document Manager: Logical duplicate detected, returning existing: ' . $existing_doc->original_filename);
            return array(
                'document_id' => $existing_doc->id,
                'file_url' => $existing_doc->file_url,
                'file_path' => $existing_doc->file_path,
                'original_filename' => $existing_doc->original_filename,
                'stored_filename' => $existing_doc->stored_filename,
                'file_size' => $existing_doc->file_size,
                'is_duplicate' => true,
                'duplicate_reason' => 'same_document_type_and_filename'
            );
        }
        
        // STEP 6: Move uploaded file
        if (!move_uploaded_file($file_data['tmp_name'], $file_path)) {
            return new WP_Error(
                'upload_failed',
                __('Failed to save uploaded file. Please check server permissions.', 'work-permit-system')
            );
        }
        
        // Set proper file permissions
        chmod($file_path, 0644);
        
        // STEP 7: Generate file hash for future reference
        $file_hash = md5_file($file_path);
        
        // STEP 8: Save to database
        $document_data = array(
            'permit_id' => $permit_id,
            'document_type' => $document_type,
            'original_filename' => sanitize_file_name($file_data['name']),
            'stored_filename' => $unique_filename,
            'file_path' => $file_path,
            'file_url' => $upload_url . '/' . $unique_filename,
            'file_size' => $file_data['size'],
            'mime_type' => $file_data['type'],
            'uploaded_by_user_id' => $uploaded_by_user_id,
            'uploaded_by_type' => $uploaded_by_type,
            'description' => 'Supporting document uploaded with permit application',
            'file_hash' => $file_hash
        );
        
        $document_id = self::save_document_to_database($document_data);
        
        if (is_wp_error($document_id)) {
            // Clean up file if database save failed
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            return $document_id;
        }
        
        return array(
            'document_id' => $document_id,
            'file_url' => $upload_url . '/' . $unique_filename,
            'file_path' => $file_path,
            'original_filename' => $file_data['name'],
            'stored_filename' => $unique_filename,
            'file_size' => $file_data['size'],
            'is_duplicate' => false
        );
    }

    /**
     * Generate GUARANTEED unique filename following your exact format
     * Format: document-name_permit-id_tenant_upload-datetime_hash.ext
     */
    private static function generate_guaranteed_unique_filename($original_filename, $permit_id, $tenant_name, $upload_dir) {
        $extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
        $document_name = sanitize_file_name(pathinfo($original_filename, PATHINFO_FILENAME));
        
        // Clean and limit document name (remove special chars, limit length)
        $document_name = preg_replace('/[^a-zA-Z0-9\-_]/', '-', $document_name);
        $document_name = substr($document_name, 0, 30); // Reduced to allow more space for uniqueness
        $document_name = trim($document_name, '-_') ?: 'document';
        
        // Clean and limit tenant name
        $tenant_safe = preg_replace('/[^a-zA-Z0-9\-_]/', '-', sanitize_file_name($tenant_name));
        $tenant_safe = substr($tenant_safe, 0, 15); // Reduced to allow more space for uniqueness
        $tenant_safe = trim($tenant_safe, '-_') ?: 'unknown';
        
        // Generate timestamp with microseconds for better uniqueness
        $upload_datetime = date('Ymd-His') . '-' . substr(microtime(false), 2, 6);
        
        // Generate a longer random hash for guaranteed uniqueness
        $random_hash = substr(md5(uniqid(mt_rand(), true) . $permit_id . time()), 0, 8);
        
        // Build filename following your exact format
        $filename = sprintf(
            '%s_permit%d_%s_%s_%s.%s',
            $document_name,
            $permit_id,
            $tenant_safe,
            $upload_datetime,
            $random_hash,
            $extension
        );
        
        // Ensure the filename doesn't already exist (extra safety)
        $counter = 1;
        $original_filename_base = $filename;
        while (file_exists($upload_dir . '/' . $filename)) {
            $pathinfo = pathinfo($original_filename_base);
            $filename = $pathinfo['filename'] . '_' . $counter . '.' . $pathinfo['extension'];
            $counter++;
            
            // Prevent infinite loop
            if ($counter > 1000) {
                error_log('WPS Document Manager: Unable to generate unique filename after 1000 attempts');
                break;
            }
        }
        
        return $filename;
    }
    
    /**
     * Check for logical duplicates (same permit + document type + original filename)
     * This is different from filename uniqueness - this prevents uploading the same document twice
     */
    private static function check_for_logical_duplicate($permit_id, $document_type, $original_filename) {
        global $wpdb;
        
        $documents_table = $wpdb->prefix . 'wps_permit_documents';
        
        // Check if the SAME document has already been uploaded for this permit
        $existing = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $documents_table 
            WHERE permit_id = %d 
            AND document_type = %s 
            AND original_filename = %s 
            AND is_active = 1
            ORDER BY upload_date DESC
            LIMIT 1
        ", $permit_id, $document_type, sanitize_file_name($original_filename)));
        
        if ($existing && file_exists($existing->file_path)) {
            return $existing;
        }
        
        return null;
    }
    
    /**
     * Enhanced database save without unique constraint conflicts
     */
    private static function save_document_to_database($document_data) {
        global $wpdb;
        
        $documents_table = $wpdb->prefix . 'wps_permit_documents';
        
        // Check if file_hash column exists, if not add it
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $documents_table LIKE 'file_hash'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $documents_table ADD COLUMN file_hash VARCHAR(32) DEFAULT NULL AFTER mime_type");
            $wpdb->query("ALTER TABLE $documents_table ADD INDEX idx_file_hash (file_hash)");
        }
        
        $result = $wpdb->insert(
            $documents_table,
            $document_data,
            array('%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            error_log('WPS Document Manager: Database error - ' . $wpdb->last_error);
            return new WP_Error(
                'database_error',
                __('Failed to save document information. Please try again.', 'work-permit-system')
            );
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get supporting documents for email attachment - NO FILTERING
     */
    public static function get_documents_for_email_attachment($permit_id) {
        
        // Get ALL documents for this permit (don't filter by type)
        $documents = self::get_permit_documents($permit_id, null, true);
        
        if (empty($documents)) {
            error_log('WPS Document Manager: No documents found for permit ' . $permit_id);
            return array();
        }
        
        $attachment_files = array();
        
        foreach ($documents as $document) {
            // Simply check if file exists
            if ($document->file_exists) {
                $attachment_files[] = array(
                    'path' => $document->file_path,
                    'name' => $document->original_filename,
                    'size' => $document->file_size,
                    'type' => $document->mime_type,
                    'stored_name' => $document->stored_filename,
                    'document_type' => $document->document_type
                );
            } else {
                error_log('WPS Document Manager: Skipped missing file: ' . $document->file_path);
            }
        }
        
        return $attachment_files;
    }
    
    /**
     * Get documents for a permit - SIMPLIFIED, NO DUPLICATE FILTERING
     */
    public static function get_permit_documents($permit_id, $document_type = null, $active_only = true) {
        global $wpdb;
        
        $documents_table = $wpdb->prefix . 'wps_permit_documents';
        
        $where_conditions = array('permit_id = %d');
        $params = array($permit_id);
        
        if ($document_type) {
            $where_conditions[] = 'document_type = %s';
            $params[] = $document_type;
        }
        
        if ($active_only) {
            $where_conditions[] = 'is_active = 1';
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $query = $wpdb->prepare("
            SELECT d.*, u.display_name as uploader_name
            FROM $documents_table d
            LEFT JOIN {$wpdb->users} u ON d.uploaded_by_user_id = u.ID
            WHERE $where_clause
            ORDER BY d.upload_date DESC, d.id DESC
        ", $params);
        
        $documents = $wpdb->get_results($query);
        
        // Add file existence check and formatted data
        foreach ($documents as $document) {
            $document->file_exists = file_exists($document->file_path);
            $document->formatted_file_size = size_format($document->file_size);
            $document->formatted_upload_date = wps_format_date($document->upload_date);
            
            // Mark missing files as inactive
            if (!$document->file_exists) {
                error_log('WPS Document Manager: File missing, marking as inactive: ' . $document->file_path);
                $wpdb->update(
                    $documents_table,
                    array('is_active' => 0),
                    array('id' => $document->id),
                    array('%d'),
                    array('%d')
                );
            }
        }
        
        return $documents;
    }
    
    /**
     * Validate uploaded file
     */
    private static function validate_uploaded_file($file_data) {
        // Check for upload errors
        if ($file_data['error'] !== UPLOAD_ERR_OK) {
            $error_messages = array(
                UPLOAD_ERR_INI_SIZE => 'File is too large (exceeds server limit)',
                UPLOAD_ERR_FORM_SIZE => 'File is too large (exceeds form limit)',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary directory',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
            );
            
            $error_message = isset($error_messages[$file_data['error']]) 
                ? $error_messages[$file_data['error']] 
                : 'Unknown upload error';
                
            return new WP_Error('upload_error', $error_message);
        }
        
        // Check file size
        if ($file_data['size'] > self::$max_file_size) {
            return new WP_Error(
                'file_too_large',
                sprintf(__('File is too large. Maximum size allowed is %s.', 'work-permit-system'), size_format(self::$max_file_size))
            );
        }
        
        // Check file type
        if (!in_array($file_data['type'], self::$allowed_types)) {
            return new WP_Error(
                'invalid_file_type',
                __('Invalid file type. Only PDF, DOCX, DOC, JPG, PNG, GIF, and WEBP files are allowed.', 'work-permit-system')
            );
        }
        
        // Additional security check - verify file extension matches MIME type
        $file_extension = strtolower(pathinfo($file_data['name'], PATHINFO_EXTENSION));
        if (!self::is_valid_extension_for_mime($file_extension, $file_data['type'])) {
            return new WP_Error(
                'invalid_file_extension',
                __('File extension does not match file type. This could be a security risk.', 'work-permit-system')
            );
        }
        
        return true;
    }
    
    /**
     * Verify file extension matches MIME type
     */
    private static function is_valid_extension_for_mime($extension, $mime_type) {
        $valid_combinations = array(
            'pdf' => array('application/pdf'),
            'docx' => array('application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            'doc' => array('application/msword'),
            'jpg' => array('image/jpeg'),
            'jpeg' => array('image/jpeg'),
            'png' => array('image/png'),
            'gif' => array('image/gif'),
            'webp' => array('image/webp')
        );
        
        if (!isset($valid_combinations[$extension])) {
            return false;
        }
        
        return in_array($mime_type, $valid_combinations[$extension]);
    }
    
    /**
     * Setup upload directory
     */
    private static function setup_upload_directory() {
        $upload_base_dir = WPS_PLUGIN_PATH . 'assets/supporting-documents';
        $upload_base_url = WPS_PLUGIN_URL . 'assets/supporting-documents';
        
        // Create directory structure: /year/month/
        $year = date('Y');
        $month = date('m');
        
        $upload_dir = $upload_base_dir . '/' . $year . '/' . $month;
        $upload_url = $upload_base_url . '/' . $year . '/' . $month;
        
        if (!file_exists($upload_dir)) {
            if (!wp_mkdir_p($upload_dir)) {
                return new WP_Error(
                    'directory_creation_failed',
                    __('Failed to create upload directory. Please check server permissions.', 'work-permit-system')
                );
            }
        }
        
        // Create .htaccess file for security
        self::create_security_htaccess($upload_base_dir);
        
        return array(
            'path' => $upload_dir,
            'url' => $upload_url
        );
    }
    
    /**
     * Create .htaccess file for security
     */
    private static function create_security_htaccess($directory) {
        $htaccess_file = $directory . '/.htaccess';
        
        if (!file_exists($htaccess_file)) {
            $htaccess_content = "# WPS Supporting Documents Security\n";
            $htaccess_content .= "# Prevent direct access to files\n";
            $htaccess_content .= "Order Deny,Allow\n";
            $htaccess_content .= "Deny from all\n";
            $htaccess_content .= "\n# Allow only specific file types\n";
            $htaccess_content .= "<Files ~ \"\\.(pdf|docx|doc|jpg|jpeg|png|gif|webp)$\">\n";
            $htaccess_content .= "    Order Allow,Deny\n";
            $htaccess_content .= "    Allow from all\n";
            $htaccess_content .= "</Files>\n";
            
            file_put_contents($htaccess_file, $htaccess_content);
        }
    }
    
    // Keep existing utility methods...
    public static function get_document_by_id($document_id) {
        global $wpdb;
        
        $documents_table = $wpdb->prefix . 'wps_permit_documents';
        
        $query = $wpdb->prepare("
            SELECT d.*, u.display_name as uploader_name
            FROM $documents_table d
            LEFT JOIN {$wpdb->users} u ON d.uploaded_by_user_id = u.ID
            WHERE d.id = %d AND d.is_active = 1
        ", $document_id);
        
        $document = $wpdb->get_row($query);
        
        if ($document) {
            $document->formatted_file_size = size_format($document->file_size);
            $document->formatted_upload_date = wps_format_date($document->upload_date);
            $document->file_exists = file_exists($document->file_path);
        }
        
        return $document;
    }
    
    public static function delete_document($document_id, $delete_file = true) {
        global $wpdb;
        
        $document = self::get_document_by_id($document_id);
        if (!$document) {
            return new WP_Error('document_not_found', __('Document not found.', 'work-permit-system'));
        }
        
        $documents_table = $wpdb->prefix . 'wps_permit_documents';
        
        // Soft delete - mark as inactive
        $result = $wpdb->update(
            $documents_table,
            array('is_active' => 0),
            array('id' => $document_id),
            array('%d'),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('delete_failed', __('Failed to delete document.', 'work-permit-system'));
        }
        
        // Optionally delete physical file
        if ($delete_file && file_exists($document->file_path)) {
            unlink($document->file_path);
        }
        
        return true;
    }
    
    public static function get_total_document_size($permit_id) {
        global $wpdb;
        
        $documents_table = $wpdb->prefix . 'wps_permit_documents';
        
        return $wpdb->get_var($wpdb->prepare("
            SELECT SUM(file_size) 
            FROM $documents_table 
            WHERE permit_id = %d AND is_active = 1
        ", $permit_id));
    }
    
    public static function cleanup_expired_documents() {
        global $wpdb;
        
        $documents_table = $wpdb->prefix . 'wps_permit_documents';
        
        // Find inactive documents older than 30 days
        $expired_documents = $wpdb->get_results("
            SELECT file_path 
            FROM $documents_table 
            WHERE is_active = 0 
            AND upload_date < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        
        $deleted_count = 0;
        
        foreach ($expired_documents as $document) {
            if (file_exists($document->file_path)) {
                if (unlink($document->file_path)) {
                    $deleted_count++;
                }
            }
        }
        
        // Remove database records for expired documents
        $wpdb->query("
            DELETE FROM $documents_table 
            WHERE is_active = 0 
            AND upload_date < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        
        return $deleted_count;
    }
    
    public static function get_document_statistics() {
        global $wpdb;
        
        $documents_table = $wpdb->prefix . 'wps_permit_documents';
        
        return array(
            'total_documents' => $wpdb->get_var("SELECT COUNT(*) FROM $documents_table WHERE is_active = 1"),
            'total_size' => $wpdb->get_var("SELECT SUM(file_size) FROM $documents_table WHERE is_active = 1"),
            'by_type' => $wpdb->get_results("
                SELECT document_type, COUNT(*) as count, SUM(file_size) as total_size 
                FROM $documents_table 
                WHERE is_active = 1 
                GROUP BY document_type
            "),
            'by_mime' => $wpdb->get_results("
                SELECT mime_type, COUNT(*) as count 
                FROM $documents_table 
                WHERE is_active = 1 
                GROUP BY mime_type 
                ORDER BY count DESC
            ")
        );
    }

    public static function get_document_for_viewing($document_id) {
        $document = self::get_document_by_id($document_id);
        
        if (!$document) {
            return null;
        }
        
        // Add conversion capability info
        $document->can_convert_to_pdf = WPS_Document_Converter::can_convert_file($document->original_filename);
        $document->is_pdf = strtolower(pathinfo($document->original_filename, PATHINFO_EXTENSION)) === 'pdf';
        
        return $document;
    }
    
    public static function get_allowed_file_types_display() {
        return array(
            'PDF documents (.pdf)',
            'Word documents (.docx, .doc)', 
            'Images (.jpg, .jpeg, .png, .gif, .webp)'
        );
    }
    
    public static function get_max_file_size_display() {
        return size_format(self::$max_file_size);
    }
}