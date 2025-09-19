<?php
/**
 * Complete Database operations class - WordPress User Integration with Fixed Status Flow
 * File: includes/class-database.php
 * Properly handles foreign key constraints for table creation
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPS_Database {
    
    // Status constants - these DON'T cause conflicts
    const STATUS_PENDING_REVIEW = 'pending_review';
    const STATUS_PENDING_APPROVAL = 'pending_approval';
    const STATUS_APPROVED = 'approved';
    const STATUS_CANCELLED = 'cancelled';
    
    /**
     * Get all valid statuses as array
     */
    public static function get_valid_statuses() {
        return array(
            self::STATUS_PENDING_REVIEW,
            self::STATUS_PENDING_APPROVAL,
            self::STATUS_APPROVED,
            self::STATUS_CANCELLED
        );
    }

    /**
     * Get valid statuses formatted for SQL IN clause
     */
    private static function get_valid_statuses_for_sql() {
        return "'" . implode("','", self::get_valid_statuses()) . "'";
    }

    /**
    * IMPROVED: Create tables method with duplicate prevention
    */
    private static function create_categories_table($charset_collate) {
        global $wpdb;
        $categories_table = $wpdb->prefix . 'wps_work_categories';
        $sql_categories = "CREATE TABLE $categories_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            category_name varchar(100) NOT NULL UNIQUE,
            description text,
            is_active tinyint(1) DEFAULT 1,
            sort_order int DEFAULT 0,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by mediumint(9) DEFAULT NULL,
            updated_by mediumint(9) DEFAULT NULL,
            PRIMARY KEY (id),
            INDEX idx_active (is_active),
            INDEX idx_sort (sort_order)
        ) $charset_collate;";
        return $sql_categories;
    }

    private static function create_permits_table($charset_collate) {
        global $wpdb;
        $permits_table = $wpdb->prefix . 'work_permits';
        $sql_permits = "CREATE TABLE $permits_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            permit_id varchar(20) DEFAULT NULL UNIQUE,
            email_address varchar(255) NOT NULL,
            phone_number varchar(20) NOT NULL,
            date_issued date DEFAULT NULL,
            issued_to text NOT NULL,
            tenant text NOT NULL,
            work_area text NOT NULL,
            work_category_id mediumint(9) NOT NULL,
            other_specification varchar(200) DEFAULT NULL,
            requestor_type varchar(255) NOT NULL,
            tenant_field text NOT NULL,
            requested_start_date date NOT NULL,
            requested_start_time time NOT NULL,
            requested_end_date date NOT NULL,
            requested_end_time time NOT NULL,
            personnel_list text DEFAULT NULL,
            reviewer_user_id bigint(20) UNSIGNED DEFAULT NULL,
            approver_user_id bigint(20) UNSIGNED DEFAULT NULL,
            approved_by INT(11) NULL,
            approver_signatory_url text,
            requester_position text,
            status varchar(25) DEFAULT '" . self::STATUS_PENDING_REVIEW . "',
            submitted_date datetime DEFAULT CURRENT_TIMESTAMP,
            updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            review_started_date datetime DEFAULT NULL,
            review_completed_date datetime DEFAULT NULL,
            approved_date datetime DEFAULT NULL,
            cancelled_date datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY unique_permit_id (permit_id),
            INDEX idx_permit_id (permit_id),
            INDEX idx_email (email_address),
            INDEX idx_status (status),
            INDEX idx_submitted_date (submitted_date),
            INDEX idx_reviewer_user (reviewer_user_id),
            INDEX idx_approver_user (approver_user_id),
            INDEX idx_work_category (work_category_id)
        ) $charset_collate;";

        return $sql_permits;
    }

    private static function create_documents_table($charset_collate) {
        global $wpdb;
        $documents_table = $wpdb->prefix . 'wps_permit_documents';
        $sql_documents = "CREATE TABLE $documents_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            permit_id mediumint(9) NOT NULL,
            document_type ENUM('supporting_document', 'signature', 'attachment') DEFAULT 'supporting_document',
            original_filename varchar(255) NOT NULL,
            stored_filename varchar(255) NOT NULL,
            file_path text NOT NULL,
            file_url text NOT NULL,
            file_size bigint(20) DEFAULT 0,
            mime_type varchar(100) DEFAULT NULL,
            uploaded_by_user_id bigint(20) UNSIGNED DEFAULT NULL,
            uploaded_by_type ENUM('applicant', 'reviewer', 'approver', 'admin') DEFAULT 'applicant',
            upload_date datetime DEFAULT CURRENT_TIMESTAMP,
            is_active tinyint(1) DEFAULT 1,
            description text DEFAULT NULL,
            PRIMARY KEY (id),
            INDEX idx_permit_id (permit_id),
            INDEX idx_document_type (document_type),
            INDEX idx_uploaded_by (uploaded_by_user_id),
            INDEX idx_upload_date (upload_date),
            INDEX idx_active (is_active)
        ) $charset_collate;";

        return $sql_documents;
    }

    private static function create_comments_table($charset_collate) {
        global $wpdb;
        $comments_table = $wpdb->prefix . 'wps_permit_comments';
        $sql_comments = "CREATE TABLE $comments_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            permit_id mediumint(9) NOT NULL,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            user_type ENUM('reviewer', 'approver', 'admin', 'system') NOT NULL,
            user_name varchar(255) NOT NULL,
            user_email varchar(255) NOT NULL,
            comment text NOT NULL,
            action_taken varchar(50) DEFAULT NULL,
            previous_status varchar(25) DEFAULT NULL,
            new_status varchar(25) DEFAULT NULL,
            is_internal tinyint(1) DEFAULT 0,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_permit (permit_id),
            INDEX idx_user_type (user_type),
            INDEX idx_user_id (user_id),
            INDEX idx_created_date (created_date)
        ) $charset_collate;";

        return $sql_comments;
    }

    private static function create_status_history_table($charset_collate) {
        global $wpdb;
        $status_history_table = $wpdb->prefix . 'wps_permit_status_history';
        $sql_status_history = "CREATE TABLE $status_history_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            permit_id mediumint(9) NOT NULL,
            previous_status varchar(25) DEFAULT NULL,
            new_status varchar(25) NOT NULL,
            changed_by_user_id bigint(20) UNSIGNED DEFAULT NULL,
            changed_by_type ENUM('reviewer', 'approver', 'admin', 'system') NOT NULL,
            changed_by_name varchar(255) NOT NULL,
            reason text,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_permit (permit_id),
            INDEX idx_status (new_status),
            INDEX idx_user_id (changed_by_user_id),
            INDEX idx_created_date (created_date)
        ) $charset_collate;";

        return $sql_status_history;
    }

    public static function create_tables() {
        global $wpdb;
        
        // PREVENT MULTIPLE SIMULTANEOUS CALLS
        $lock_key = 'wps_creating_tables';
        $lock_timeout = 60; // 1 minute timeout
        
        if (get_transient($lock_key)) {
            error_log('WPS: Table creation already in progress, skipping duplicate call');
            return;
        }
        
        // Set lock
        set_transient($lock_key, true, $lock_timeout);
        
        try {
            error_log('WPS: Starting table creation process... (PID: ' . getmypid() . ')');
            
            $charset_collate = $wpdb->get_charset_collate();
            
            // Check if tables already exist to avoid unnecessary recreation
            $tables_to_create = array(
                $wpdb->prefix . 'wps_work_categories',
                $wpdb->prefix . 'work_permits',
                $wpdb->prefix . 'wps_permit_documents',
                $wpdb->prefix . 'wps_permit_comments',
                $wpdb->prefix . 'wps_permit_status_history'
            );
            
            $existing_tables = array();
            foreach ($tables_to_create as $table_name) {
                if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name) {
                    $existing_tables[] = $table_name;
                }
            }
            
            if (count($existing_tables) === count($tables_to_create)) {
                error_log('WPS: All tables already exist, skipping table creation');
                // Still run FK constraints and other setup
                self::add_foreign_key_constraints();
                self::handle_post_table_setup();
                return;
            }
            
            error_log('WPS: Found ' . count($existing_tables) . ' existing tables out of ' . count($tables_to_create));
            
            // Your existing table creation SQL here...
            $sql_categories = self::create_categories_table($charset_collate);
            $sql_permits = self::create_permits_table($charset_collate);
            $sql_documents = self::create_documents_table($charset_collate);
            $sql_comments = self::create_comments_table($charset_collate);
            $sql_status_history = self::create_status_history_table($charset_collate);
            
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            
            // Execute table creation queries

            try {
                dbDelta($sql_categories);
                if ($wpdb->last_error) {
                    throw new Exception("Categories table creation failed: " . $wpdb->last_error);
                }
                error_log('WPS: Categories table processed successfully');
            } catch (Exception $e) {
                error_log('WPS: ' . $e->getMessage());
                throw $e;
            }

            try {
                dbDelta($sql_permits);
                if ($wpdb->last_error) {
                    throw new Exception("Permits table creation failed: " . $wpdb->last_error);
                }
                error_log('WPS: Permits table processed successfully');
            } catch (Exception $e) {
                error_log('WPS: ' . $e->getMessage());
                throw $e;
            }
            
            try {
                dbDelta($sql_documents);
                if ($wpdb->last_error) {
                    throw new Exception("Documents table creation failed: " . $wpdb->last_error);
                }
                error_log('WPS: Documents table processed successfully');
            } catch (Exception $e) {
                error_log('WPS: ' . $e->getMessage());
                throw $e;
            }
            
            try {
                dbDelta($sql_comments);
                if ($wpdb->last_error) {
                    throw new Exception("Comments table creation failed: " . $wpdb->last_error);
                }
                error_log('WPS: Comments table processed successfully');
            } catch (Exception $e) {
                error_log('WPS: ' . $e->getMessage());
                throw $e;
            }
            
            try {
                dbDelta($sql_status_history);
                if ($wpdb->last_error) {
                    throw new Exception("Status history table creation failed: " . $wpdb->last_error);
                }
                error_log('WPS: Status history table processed successfully');
            } catch (Exception $e) {
                error_log('WPS: ' . $e->getMessage());
                throw $e;
            }
            
            // Clean up orphaned references if needed
            $permits_table = $wpdb->prefix . 'work_permits';
            $permits_count = $wpdb->get_var("SELECT COUNT(*) FROM $permits_table");
            if ($permits_count > 0) {
                error_log("WPS: Found $permits_count existing permits, cleaning up references...");
                self::cleanup_invalid_user_references();
            } else {
                error_log('WPS: No existing permits found, skipping reference cleanup');
            }
            
            // Add foreign key constraints
            self::add_foreign_key_constraints();
            
            // Handle post-table setup
            self::handle_post_table_setup();
            
            error_log('WPS: Table creation process completed successfully');
            
        } finally {
            // Always release the lock
            delete_transient($lock_key);
        }
    }

    /**
    * Handle post-table setup tasks
    */
    private static function handle_post_table_setup() {
        // Migrate existing signature data
        if (!get_option('wps_signature_migration_completed', false)) {
            error_log('WPS: Starting signature migration...');
            self::migrate_existing_signature_data();
            update_option('wps_signature_migration_completed', true);
            error_log('WPS: Signature migration completed');
        } else {
            error_log('WPS: Signature migration already completed, skipping');
        }
        
        // Insert default categories
        self::insert_default_categories();
    }
    
    /**
    * Add foreign key constraints after tables are created - MYISAM COMPATIBILITY FIX
    */
    private static function add_foreign_key_constraints() {
        global $wpdb;
        
        $permits_table = $wpdb->prefix . 'work_permits';
        $categories_table = $wpdb->prefix . 'wps_work_categories';
        $comments_table = $wpdb->prefix . 'wps_permit_comments';
        $status_history_table = $wpdb->prefix . 'wps_permit_status_history';
        $documents_table = $wpdb->prefix . 'wps_permit_documents';
        
        // Check WordPress users table engine
        $users_engine = $wpdb->get_var($wpdb->prepare("
            SELECT ENGINE 
            FROM INFORMATION_SCHEMA.TABLES 
            WHERE TABLE_SCHEMA = %s 
            AND TABLE_NAME = %s
        ", $wpdb->dbname, $wpdb->users));
        
        error_log("WPS: WordPress users table ($wpdb->users) engine: " . ($users_engine ?: 'UNKNOWN'));
        error_log("WPS: Users.ID column type: " . self::get_users_id_column_type());
        
        // FIXED: Get existing foreign key constraints with proper IN clause
        $tables_to_check = array($permits_table, $comments_table, $status_history_table, $documents_table);
        $placeholders = implode(',', array_fill(0, count($tables_to_check), '%s'));
        $params = array_merge(array($wpdb->dbname), $tables_to_check);

        $existing_fks = $wpdb->get_results($wpdb->prepare("
            SELECT 
                kcu.CONSTRAINT_NAME,
                kcu.TABLE_NAME,
                kcu.COLUMN_NAME,
                kcu.REFERENCED_TABLE_NAME,
                kcu.REFERENCED_COLUMN_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
            JOIN INFORMATION_SCHEMA.TABLE_CONSTRAINTS tc ON kcu.CONSTRAINT_NAME = tc.CONSTRAINT_NAME
            WHERE kcu.TABLE_SCHEMA = %s 
            AND tc.CONSTRAINT_TYPE = 'FOREIGN KEY'
            AND kcu.TABLE_NAME IN ($placeholders)
        ", ...$params));
        
        $existing_constraint_names = array();
        foreach ($existing_fks as $fk) {
            $existing_constraint_names[] = $fk->CONSTRAINT_NAME;
            error_log("WPS: Found existing FK: {$fk->CONSTRAINT_NAME} on {$fk->TABLE_NAME}.{$fk->COLUMN_NAME} -> {$fk->REFERENCED_TABLE_NAME}.{$fk->REFERENCED_COLUMN_NAME}");
        }
        
        // Rest of your foreign key creation logic remains the same...
        
        // Define all foreign keys we want to create
        $foreign_keys_to_create = array(
            // Category FK (InnoDB to InnoDB)
            'fk_work_permits_category' => array(
                'table' => $permits_table,
                'column' => 'work_category_id',
                'ref_table' => $categories_table,
                'ref_column' => 'id',
                'sql' => "ALTER TABLE $permits_table ADD CONSTRAINT fk_work_permits_category FOREIGN KEY (work_category_id) REFERENCES $categories_table(id)"
            ),
            
            // Documents permit FK
            'fk_permit_documents_permit' => array(
                'table' => $documents_table,
                'column' => 'permit_id',
                'ref_table' => $permits_table,
                'ref_column' => 'id',
                'sql' => "ALTER TABLE $documents_table ADD CONSTRAINT fk_permit_documents_permit FOREIGN KEY (permit_id) REFERENCES $permits_table(id) ON DELETE CASCADE"
            ),
            
            // Comments permit FK
            'fk_permit_comments_permit' => array(
                'table' => $comments_table,
                'column' => 'permit_id',
                'ref_table' => $permits_table,
                'ref_column' => 'id',
                'sql' => "ALTER TABLE $comments_table ADD CONSTRAINT fk_permit_comments_permit FOREIGN KEY (permit_id) REFERENCES $permits_table(id) ON DELETE CASCADE"
            ),
            
            // Status history permit FK
            'fk_permit_status_permit' => array(
                'table' => $status_history_table,
                'column' => 'permit_id',
                'ref_table' => $permits_table,
                'ref_column' => 'id',
                'sql' => "ALTER TABLE $status_history_table ADD CONSTRAINT fk_permit_status_permit FOREIGN KEY (permit_id) REFERENCES $permits_table(id) ON DELETE CASCADE"
            )
        );
        
        // Add user foreign keys only if wp_users is InnoDB
        if ($users_engine === 'InnoDB') {
            $user_foreign_keys = array(
                'fk_work_permits_reviewer' => array(
                    'table' => $permits_table,
                    'column' => 'reviewer_user_id',
                    'ref_table' => $wpdb->users,
                    'ref_column' => 'ID',
                    'sql' => "ALTER TABLE $permits_table ADD CONSTRAINT fk_work_permits_reviewer FOREIGN KEY (reviewer_user_id) REFERENCES {$wpdb->users}(ID) ON DELETE SET NULL"
                ),
                'fk_work_permits_approver' => array(
                    'table' => $permits_table,
                    'column' => 'approver_user_id',
                    'ref_table' => $wpdb->users,
                    'ref_column' => 'ID',
                    'sql' => "ALTER TABLE $permits_table ADD CONSTRAINT fk_work_permits_approver FOREIGN KEY (approver_user_id) REFERENCES {$wpdb->users}(ID) ON DELETE SET NULL"
                ),
                'fk_permit_documents_user' => array(
                    'table' => $documents_table,
                    'column' => 'uploaded_by_user_id',
                    'ref_table' => $wpdb->users,
                    'ref_column' => 'ID',
                    'sql' => "ALTER TABLE $documents_table ADD CONSTRAINT fk_permit_documents_user FOREIGN KEY (uploaded_by_user_id) REFERENCES {$wpdb->users}(ID) ON DELETE SET NULL"
                ),
                'fk_permit_comments_user' => array(
                    'table' => $comments_table,
                    'column' => 'user_id',
                    'ref_table' => $wpdb->users,
                    'ref_column' => 'ID',
                    'sql' => "ALTER TABLE $comments_table ADD CONSTRAINT fk_permit_comments_user FOREIGN KEY (user_id) REFERENCES {$wpdb->users}(ID) ON DELETE SET NULL"
                ),
                'fk_permit_status_user' => array(
                    'table' => $status_history_table,
                    'column' => 'changed_by_user_id',
                    'ref_table' => $wpdb->users,
                    'ref_column' => 'ID',
                    'sql' => "ALTER TABLE $status_history_table ADD CONSTRAINT fk_permit_status_user FOREIGN KEY (changed_by_user_id) REFERENCES {$wpdb->users}(ID) ON DELETE SET NULL"
                )
            );
            
            $foreign_keys_to_create = array_merge($foreign_keys_to_create, $user_foreign_keys);
            error_log('WPS: wp_users is InnoDB - will attempt to add user foreign keys');
        } else {
            error_log('WPS: Skipping wp_users foreign keys - MyISAM engine detected or engine unknown');
            error_log('WPS: User data integrity will be maintained through application logic');
            self::setup_application_level_user_validation();
        }
        
        // Create foreign keys that don't already exist
        foreach ($foreign_keys_to_create as $constraint_name => $fk_info) {
            if (in_array($constraint_name, $existing_constraint_names)) {
                error_log("WPS: FK {$constraint_name} already exists, skipping");
                continue;
            }
            
            // Additional check: verify constraint doesn't exist by different means
            $constraint_exists = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = %s 
                AND TABLE_NAME = %s 
                AND COLUMN_NAME = %s 
                AND REFERENCED_TABLE_NAME = %s 
                AND REFERENCED_COLUMN_NAME = %s
            ", $wpdb->dbname, $fk_info['table'], $fk_info['column'], $fk_info['ref_table'], $fk_info['ref_column']));
            
            if ($constraint_exists > 0) {
                error_log("WPS: FK relationship already exists for {$fk_info['table']}.{$fk_info['column']} -> {$fk_info['ref_table']}.{$fk_info['ref_column']}, skipping");
                continue;
            }
            
            error_log("WPS: Attempting to create FK: {$constraint_name}");
            $result = $wpdb->query($fk_info['sql']);
            
            if ($result === false) {
                $error = $wpdb->last_error;
                error_log("WPS: Failed to add {$constraint_name}: {$error}");
                
                // Check for specific error types
                if (strpos($error, 'errno: 121') !== false) {
                    error_log("WPS: Error 121 indicates constraint already exists despite our checks");
                } elseif (strpos($error, 'errno: 150') !== false) {
                    error_log("WPS: Error 150 indicates foreign key constraint formation issue");
                }
            } else {
                error_log("WPS: Successfully created FK: {$constraint_name}");
            }
        }
        
        error_log('WPS: Foreign key constraint setup completed');
    }

    /**
    * NEW: Get wp_users ID column type for debugging
    */
    private static function get_users_id_column_type() {
        global $wpdb;
        
        $column_info = $wpdb->get_row($wpdb->prepare("
            SELECT DATA_TYPE, COLUMN_TYPE 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = %s 
            AND TABLE_NAME = %s 
            AND COLUMN_NAME = 'ID'
        ", $wpdb->dbname, $wpdb->users));
        
        return $column_info ? $column_info->COLUMN_TYPE : 'UNKNOWN';
    }


    /**
    * NEW: Set up application-level validation for MyISAM environments
    */
    private static function setup_application_level_user_validation() {
        error_log('WPS: Application-level user validation is active');
        // Add hooks for user deletion handling if needed
    }

     /**
    * Add application-level validation for user references
    * Call this to validate user IDs before insert/update operations
    */
    public static function validate_user_reference($user_id) {
        if (empty($user_id)) {
            return null; // NULL is allowed
        }
        
        // Check if user exists
        $user_exists = get_user_by('ID', $user_id);
        if (!$user_exists) {
            error_log("WPS: Invalid user ID referenced: $user_id");
            return null; // Return NULL for invalid user IDs
        }
        
        return $user_id;
    }

    /**
     * UPDATED: Clean up invalid user references with table existence checks
     */
    public static function cleanup_invalid_user_references() {
        global $wpdb;
        
        $permits_table = $wpdb->prefix . 'work_permits';
        $documents_table = $wpdb->prefix . 'wps_permit_documents';
        $comments_table = $wpdb->prefix . 'wps_permit_comments';
        $status_history_table = $wpdb->prefix . 'wps_permit_status_history';
        
        // Check if tables exist before attempting cleanup
        $tables_to_check = array(
            $permits_table,
            $documents_table,
            $comments_table,
            $status_history_table
        );
        
        $existing_tables = array();
        foreach ($tables_to_check as $table) {
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table) {
                $existing_tables[] = $table;
            }
        }
        
        if (empty($existing_tables)) {
            error_log('WPS: No tables exist yet, skipping user reference cleanup');
            return;
        }
        
        error_log('WPS: Running application-level user reference cleanup...');
        
        // Clean permits table if it exists
        if (in_array($permits_table, $existing_tables)) {
            $cleaned = $wpdb->query("
                UPDATE $permits_table SET reviewer_user_id = NULL 
                WHERE reviewer_user_id IS NOT NULL 
                AND NOT EXISTS (SELECT 1 FROM {$wpdb->users} WHERE ID = reviewer_user_id)
            ");
            if ($cleaned) error_log("WPS: Cleaned $cleaned invalid reviewer references");
            
            $cleaned = $wpdb->query("
                UPDATE $permits_table SET approver_user_id = NULL 
                WHERE approver_user_id IS NOT NULL 
                AND NOT EXISTS (SELECT 1 FROM {$wpdb->users} WHERE ID = approver_user_id)
            ");
            if ($cleaned) error_log("WPS: Cleaned $cleaned invalid approver references");
        }
        
        // Clean documents table if it exists
        if (in_array($documents_table, $existing_tables)) {
            $cleaned = $wpdb->query("
                UPDATE $documents_table SET uploaded_by_user_id = NULL 
                WHERE uploaded_by_user_id IS NOT NULL 
                AND NOT EXISTS (SELECT 1 FROM {$wpdb->users} WHERE ID = uploaded_by_user_id)
            ");
            if ($cleaned) error_log("WPS: Cleaned $cleaned invalid document user references");
        }
        
        // Clean comments table if it exists
        if (in_array($comments_table, $existing_tables)) {
            $cleaned = $wpdb->query("
                UPDATE $comments_table SET user_id = NULL 
                WHERE user_id IS NOT NULL 
                AND NOT EXISTS (SELECT 1 FROM {$wpdb->users} WHERE ID = user_id)
            ");
            if ($cleaned) error_log("WPS: Cleaned $cleaned invalid comment user references");
        }
        
        // Clean status history table if it exists
        if (in_array($status_history_table, $existing_tables)) {
            $cleaned = $wpdb->query("
                UPDATE $status_history_table SET changed_by_user_id = NULL 
                WHERE changed_by_user_id IS NOT NULL 
                AND NOT EXISTS (SELECT 1 FROM {$wpdb->users} WHERE ID = changed_by_user_id)
            ");
            if ($cleaned) error_log("WPS: Cleaned $cleaned invalid status history user references");
        }
        
        error_log('WPS: Application-level user reference cleanup completed');
    }

    /**
     * Migrate existing signature data to documents table
     */
    private static function migrate_existing_signature_data() {
        global $wpdb;
        
        $permits_table = $wpdb->prefix . 'work_permits';
        $documents_table = $wpdb->prefix . 'wps_permit_documents';
        
        // Check if documents table exists
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $documents_table)) === $documents_table;
        if (!$table_exists) {
            error_log('WPS: Documents table does not exist, skipping migration');
            return;
        }
        
        // Get all permits with signature URLs that haven't been migrated yet
        $permits_with_signatures = $wpdb->get_results("
            SELECT p.id, p.approver_signatory_url, p.email_address, p.submitted_date
            FROM $permits_table p 
            WHERE p.approver_signatory_url IS NOT NULL 
            AND p.approver_signatory_url != ''
            AND NOT EXISTS (
                SELECT 1 FROM $documents_table d 
                WHERE d.permit_id = p.id 
                AND d.document_type = 'signature'
                AND d.is_active = 1
            )
        ");
        
        $migrated_count = 0;
        
        foreach ($permits_with_signatures as $permit) {
            $signature_url = trim($permit->approver_signatory_url);
            
            if (empty($signature_url)) {
                continue;
            }
            
            // Extract filename from URL
            $parsed_url = parse_url($signature_url);
            $filename = basename($parsed_url['path'] ?? 'migrated_signature_' . $permit->id . '.jpg');
            
            // Use INSERT IGNORE to prevent duplicate key errors
            $result = $wpdb->query($wpdb->prepare("
                INSERT IGNORE INTO $documents_table (
                    permit_id, document_type, original_filename, stored_filename, 
                    file_path, file_url, file_size, mime_type, uploaded_by_type, 
                    upload_date, description
                ) VALUES (
                    %d, 'signature', %s, %s, %s, %s, 0, %s, 'applicant', %s, 'Migrated signature file'
                )
            ", 
                $permit->id,
                $filename,
                $filename,
                $signature_url,
                $signature_url,
                self::guess_mime_type($filename),
                $permit->submitted_date
            ));
            
            if ($result) {
                $migrated_count++;
            }
        }
        
    }

    /**
     * Guess MIME type from filename
     */
    private static function guess_mime_type($filename) {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        $mime_types = array(
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'doc' => 'application/msword',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp'
        );
        
        return $mime_types[$extension] ?? 'application/octet-stream';
    }
    
    /**
     * Check if documents table exists
     */
    public static function documents_table_exists() {
        global $wpdb;
        
        $documents_table = $wpdb->prefix . 'wps_permit_documents';
        return $wpdb->get_var("SHOW TABLES LIKE '$documents_table'") === $documents_table;
    }
    
    /**
     * Get table status for debugging
     */
    public static function get_documents_table_status() {
        global $wpdb;
        
        $documents_table = $wpdb->prefix . 'wps_permit_documents';
        
        $status = array(
            'table_exists' => self::documents_table_exists(),
            'record_count' => 0,
            'migration_completed' => get_option('wps_signature_migration_completed', false),
            'foreign_keys' => array()
        );
        
        if ($status['table_exists']) {
            // $status['record_count'] = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM %s", $documents_table));
            $status['record_count'] = $wpdb->get_var("SELECT COUNT(*) FROM $documents_table");
            
            // Check foreign keys with prepared statement
            $status['foreign_keys'] = $wpdb->get_results($wpdb->prepare("
                SELECT CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = %s 
                AND TABLE_NAME = %s
                AND CONSTRAINT_NAME LIKE %s
            ", $wpdb->dbname, $documents_table, 'fk_%'));
        }
        
        return $status;
    }
    
    /**
     * Insert default work categories
     */
    public static function insert_default_categories() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wps_work_categories';
        
        $existing_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        if ($existing_count == 0) {
            $default_categories = array(
                array('category_name' => 'Renovation Work', 'sort_order' => 1),
                array('category_name' => 'Electrical Works', 'sort_order' => 2),
                array('category_name' => 'Communication (ISP, Telco, POS)', 'sort_order' => 3),
                array('category_name' => 'Maintenance and Repairs (Building Admin)', 'sort_order' => 4),
                array('category_name' => 'Maintenance and Repairs (AHU)', 'sort_order' => 5),
                array('category_name' => 'Delivery (Construction)', 'sort_order' => 6),
                array('category_name' => 'Delivery (Merchandise)', 'sort_order' => 7),
                array('category_name' => 'Pullout', 'sort_order' => 8),
                array('category_name' => 'Welding', 'sort_order' => 9),
                array('category_name' => 'Painting', 'sort_order' => 10),
                array('category_name' => 'Plumbing', 'sort_order' => 11),
                array('category_name' => 'Sprinkler', 'sort_order' => 12),
                array('category_name' => 'Pest Control', 'sort_order' => 13),
                array('category_name' => 'Others', 'sort_order' => 14),
            );
            
            foreach ($default_categories as $category) {
                $wpdb->insert($table_name, $category, array('%s', '%d'));
            }
        }
    }
    
    // ===== WORK CATEGORIES METHODS =====
    
    public static function get_all_categories($active_only = true) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wps_work_categories';
        
        $where = $active_only ? 'WHERE is_active = 1' : '';
        return $wpdb->get_results("SELECT * FROM $table_name $where ORDER BY sort_order ASC, category_name ASC");
    }
    
    public static function get_category_by_id($category_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wps_work_categories';
        
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $category_id));
    }
    
    // ===== WORDPRESS USER INTEGRATION METHODS =====
    
    /**
     * Get reviewer for a specific category
     */
    public static function get_reviewer_for_category($category_name) {
        
        $users = get_users(array(
            'role' => 'wps_reviewer',
            'meta_key' => 'wps_assigned_categories'
        ));
        
        foreach ($users as $user) {
            $categories = get_user_meta($user->ID, 'wps_assigned_categories', true);
            
            if (is_array($categories) && in_array($category_name, $categories)) {
                return $user;
            }
        }
        
        return null;
    }
    
    /**
     * Get approver for a specific category
     */
    public static function get_approver_for_category($category_name) {
        
        $users = get_users(array(
            'role' => 'wps_approver',
            'meta_key' => 'wps_assigned_categories'
        ));
        
        foreach ($users as $user) {
            $categories = get_user_meta($user->ID, 'wps_assigned_categories', true);
            error_log('WPS DB: User ' . $user->display_name . ' (' . $user->user_email . ') has categories: ' . print_r($categories, true));
            
            if (is_array($categories) && in_array($category_name, $categories)) {
                return $user;
            }
        }
        
        return null;
    }

    /**
     * Get all reviewers
     */
    public static function get_all_reviewers() {
        $users = get_users(array('role' => 'wps_reviewer'));
        
        foreach ($users as $user) {
            $user->assigned_categories = get_user_meta($user->ID, 'wps_assigned_categories', true);
        }
        
        return $users;
    }
    
    /**
     * Get all approvers
     */
    public static function get_all_approvers() {
        $users = get_users(array('role' => 'wps_approver'));
        
        foreach ($users as $user) {
            $user->assigned_categories = get_user_meta($user->ID, 'wps_assigned_categories', true);
        }
        
        return $users;
    }
    
    // ===== PERMIT MANAGEMENT METHODS =====

    /**
    * UPDATED: Insert permit with user validation (since we can't use FK constraints to wp_users)
    */
    public static function insert_permit($sanitized_data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'work_permits';
        
        // Define default values for optional fields
        $defaults = array(
            'requester_position' => '',
            'approver_signatory_url' => null,
            'phone_number' => '',
            'tenant_field' => ''
        );
        
        // Merge with defaults to ensure all keys exist
        $sanitized_data = array_merge($defaults, $sanitized_data);
        
        // Get category name for assignment
        $category = self::get_category_by_id($sanitized_data['work_category_id']);
        $category_name = $category ? $category->category_name : null;
        
        // Auto-assign reviewer and approver users based on category
        $reviewer_user_id = null;
        $approver_user_id = null;
        
        if ($category_name) {
            $reviewer_user = self::get_reviewer_for_category($category_name);
            $approver_user = self::get_approver_for_category($category_name);
            
            // VALIDATE USER REFERENCES (application-level validation)
            if ($reviewer_user) {
                $reviewer_user_id = self::validate_user_reference($reviewer_user->ID);
            }
            
            if ($approver_user) {
                $approver_user_id = self::validate_user_reference($approver_user->ID);
            }
        }
        
        // Prepare insert data
        $insert_data = array(
            'email_address' => $sanitized_data['email_address'],
            'phone_number' => $sanitized_data['phone_number'],
            'issued_to' => $sanitized_data['issued_to'],
            'tenant' => $sanitized_data['tenant'],
            'work_area' => $sanitized_data['work_area'],
            'work_category_id' => $sanitized_data['work_category_id'],
            'other_specification' => isset($sanitized_data['other_specify']) ? $sanitized_data['other_specify'] : null,
            'requestor_type' => stripslashes($sanitized_data['requestor_type']),
            'tenant_field' => $sanitized_data['tenant_field'],
            'requested_start_date' => $sanitized_data['requested_start_date'],
            'requested_start_time' => $sanitized_data['requested_start_time'],
            'requested_end_date' => $sanitized_data['requested_end_date'],
            'requested_end_time' => $sanitized_data['requested_end_time'],
            'personnel_list' => $sanitized_data['personnel_list'],
            'requester_position' => $sanitized_data['requester_position'],
            'approver_signatory_url' => $sanitized_data['approver_signatory_url'],
            'reviewer_user_id' => $reviewer_user_id,  // Already validated
            'approver_user_id' => $approver_user_id,  // Already validated
            'status' => self::STATUS_PENDING_REVIEW
        );
        
        $format_array = array('%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s');

        $result = $wpdb->insert(
            $table_name,
            $insert_data,
            $format_array
        );
        
        if ($result) {
            $permit_id = $wpdb->insert_id;
            
            // Generate and update the permit_id
            $formatted_permit_id = 'WP' . str_pad($permit_id, 5, '0', STR_PAD_LEFT);
            
            $wpdb->update(
                $table_name,
                array('permit_id' => $formatted_permit_id),
                array('id' => $permit_id),
                array('%s'),
                array('%d')
            );
            
            // Get names for status message (with validation)
            $reviewer_name = $reviewer_user_id ? get_user_by('ID', $reviewer_user_id)->display_name ?? 'Unknown' : 'None';
            $approver_name = $approver_user_id ? get_user_by('ID', $approver_user_id)->display_name ?? 'Unknown' : 'None';

            if($reviewer_user_id && class_exists('WPS_Email')){
                // WPS_Email::send_reviewer_notification($permit_id);
            }

            // Add initial status history
            self::add_status_change(
                $permit_id, 
                null, 
                self::STATUS_PENDING_REVIEW,
                null, 
                'system', 
                'System', 
                'Work permit ' . $formatted_permit_id . ' submitted and auto-assigned to: Reviewer - ' . $reviewer_name . ', Approver - ' . $approver_name
            );
            
            return $permit_id;
        }
        
        error_log('WPS DB: Failed to insert permit: ' . $wpdb->last_error);
        return false;
    }

    /**
     * Insert date_issued after approver approved or cancelled
     */
    public static function insert_date_issued($permit_id, $date_issued) {
        global $wpdb;
        $permits_table = $wpdb->prefix . 'work_permits';

        if (!$permit_id || !is_numeric($permit_id)) {
            return false;
        }

        // Handle invalid dates properly
        $safe_date = (!empty($date_issued) && strtotime($date_issued)) ? $date_issued : NULL;

        // Update query
        $updated_date_issued = $wpdb->update(
            $permits_table,
            array( 'date_issued' => $safe_date ),
            array( 'id' => $permit_id ),
            array( '%s' ),
            array( '%d' )
        );

        return $updated_date_issued;
    }

    /**
     * Get permit by ID with user details
     */
    public static function get_permit_by_id($permit_id) {
        global $wpdb;
        $permits_table = $wpdb->prefix . 'work_permits';
        $categories_table = $wpdb->prefix . 'wps_work_categories';
        
        $query = $wpdb->prepare("
            SELECT 
                wp.*,
                wc.category_name
            FROM $permits_table wp
            LEFT JOIN $categories_table wc ON wp.work_category_id = wc.id
            WHERE wp.id = %d
        ", $permit_id);
        
        $permit = $wpdb->get_row($query);
        
        if ($permit) {
            // Add reviewer and approver user details
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
     * Get all permits with user details
     */
    public static function get_all_permits() {
        global $wpdb;
        $permits_table = $wpdb->prefix . 'work_permits';
        $categories_table = $wpdb->prefix . 'wps_work_categories';
        
        $query = "
            SELECT 
                wp.*,
                wc.category_name
            FROM $permits_table wp
            LEFT JOIN $categories_table wc ON wp.work_category_id = wc.id
            ORDER BY wp.submitted_date DESC
        ";
        
        $permits = $wpdb->get_results($query);
        
        // Add user details
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
        }
        
        return $permits;
    }
    
    /**
     * Get permits assigned to specific reviewer user - CORRECTED STATUS
     */
    public static function get_permits_by_reviewer_user($user_id, $status = null) {
        global $wpdb;
        $permits_table = $wpdb->prefix . 'work_permits';
        $categories_table = $wpdb->prefix . 'wps_work_categories';
        
        $where_conditions = array("wp.reviewer_user_id = %d");
        $params = array($user_id);
        
        if ($status) {
            $where_conditions[] = "wp.status = %s";
            $params[] = $status;
        } else {
            // Use the helper method for valid statuses
            $valid_statuses_sql = self::get_valid_statuses_for_sql();
            $where_conditions[] = "wp.status IN ($valid_statuses_sql)";
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $query = "
            SELECT 
                wp.*,
                wc.category_name
            FROM $permits_table wp
            LEFT JOIN $categories_table wc ON wp.work_category_id = wc.id
            WHERE $where_clause
            ORDER BY wp.submitted_date DESC
        ";
        
        // Only prepare if we have parameters
        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }
        
        $permits = $wpdb->get_results($query);
        
        // Add user details
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
        }
        
        return $permits;
    }
    
    /**
     * Get permits assigned to specific approver user - CORRECTED STATUS
     */
    public static function get_permits_by_approver_user($user_id, $status = null) {
        global $wpdb;
        $permits_table = $wpdb->prefix . 'work_permits';
        $categories_table = $wpdb->prefix . 'wps_work_categories';
        
        $where_conditions = array("wp.approver_user_id = %d");
        $params = array($user_id);
        
        if ($status) {
            $where_conditions[] = "wp.status = %s";
            $params[] = $status;
        } else {
            $approver_statuses = "'" . implode("','", array(
                self::STATUS_PENDING_APPROVAL,
                self::STATUS_APPROVED,
                self::STATUS_CANCELLED
            )) . "'";
            $where_conditions[] = "wp.status IN ($approver_statuses)";
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $query = "
            SELECT 
                wp.*,
                wc.category_name
            FROM $permits_table wp
            LEFT JOIN $categories_table wc ON wp.work_category_id = wc.id
            WHERE $where_clause
            ORDER BY wp.submitted_date DESC
        ";
        
        // Only prepare if we have parameters
        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }
        
        $permits = $wpdb->get_results($query);
        
        // Add user details
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
        }
        
        return $permits;
    }
    
    /**
    * UPDATED: Update permit status with user validation
    */
    public static function update_permit_status($permit_id, $new_status, $reviewer_user_id = null, $approver_user_id = null, $comment = '', $changed_by_user_id = null, $changed_by_type = 'admin', $changed_by_name = '') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'work_permits';
        
        $valid_statuses = self::get_valid_statuses();
        
        if (!in_array($new_status, $valid_statuses)) {
            return new WP_Error('invalid_status', 'Invalid status provided: ' . $new_status);
        }
        
        // Get current permit details
        $current_permit = $wpdb->get_row($wpdb->prepare("SELECT status FROM $table_name WHERE id = %d", $permit_id));
        $previous_status = $current_permit ? $current_permit->status : null;
        
        // Prepare update data
        $update_data = array(
            'status' => $new_status
        );
        $format = array('%s');
        
        // VALIDATE USER REFERENCES before updating
        if ($reviewer_user_id !== null) {
            $validated_reviewer = self::validate_user_reference($reviewer_user_id);
            $update_data['reviewer_user_id'] = $validated_reviewer;
            $format[] = $validated_reviewer ? '%d' : null;
        }
        
        if ($approver_user_id !== null) {
            $validated_approver = self::validate_user_reference($approver_user_id);
            $update_data['approver_user_id'] = $validated_approver;
            $format[] = $validated_approver ? '%d' : null;
        }
        
        // Validate changed_by_user_id
        $validated_changed_by = self::validate_user_reference($changed_by_user_id);
        
        // Add status-specific timestamps
        switch ($new_status) {
            case self::STATUS_PENDING_REVIEW: 
                $update_data['review_started_date'] = null;
                $update_data['review_completed_date'] = null;
                $update_data['approved_date'] = null;
                $update_data['cancelled_date'] = null;
                $format = array_merge($format, array('%s', '%s', '%s', '%s'));
                break;
            case self::STATUS_PENDING_APPROVAL:
                $update_data['review_completed_date'] = current_time('mysql');
                $format[] = '%s';
                break;
            case self::STATUS_APPROVED:
                $update_data['approved_date'] = current_time('mysql');
                $format[] = '%s';
                break;
            case self::STATUS_CANCELLED:
                $update_data['cancelled_date'] = current_time('mysql');
                $format[] = '%s';
                break;
        }
        
        $result = $wpdb->update(
            $table_name,
            $update_data,
            array('id' => $permit_id),
            $format,
            array('%d')
        );
        
        if ($result !== false) {
            // Add status history (with validated user ID)
            self::add_status_change(
                $permit_id, 
                $previous_status, 
                $new_status, 
                $validated_changed_by,  // Use validated user ID
                $changed_by_type, 
                $changed_by_name, 
                $comment
            );
            
            // Add comment if provided
            if (!empty($comment)) {
                $current_user = wp_get_current_user();
                self::add_permit_comment(
                    $permit_id, 
                    $validated_changed_by,  // Use validated user ID
                    $changed_by_type, 
                    $changed_by_name, 
                    $current_user ? $current_user->user_email : '',
                    $comment, 
                    'Status changed to: ' . $new_status,
                    $previous_status,
                    $new_status
                );
            }
        }
        
        return $result;
    }
    
    // ===== COMMENTS/HISTORY METHODS =====
    
    /**
    * UPDATED: Add permit comment with user validation
    */
    public static function add_permit_comment($permit_id, $user_id, $user_type, $user_name, $user_email, $comment, $action_taken = null, $previous_status = null, $new_status = null, $is_internal = 0) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wps_permit_comments';
        
        // VALIDATE USER REFERENCE
        $validated_user_id = self::validate_user_reference($user_id);
        
        return $wpdb->insert(
            $table_name,
            array(
                'permit_id' => $permit_id,
                'user_id' => $validated_user_id,  // Use validated user ID
                'user_type' => $user_type,
                'user_name' => $user_name,
                'user_email' => $user_email,
                'comment' => $comment,
                'action_taken' => $action_taken,
                'previous_status' => $previous_status,
                'new_status' => $new_status,
                'is_internal' => $is_internal
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d')
        );
    }
    
    /**
    * UPDATED: Add status change with user validation
    */
    public static function add_status_change($permit_id, $previous_status, $new_status, $changed_by_user_id, $changed_by_type, $changed_by_name, $reason = '') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wps_permit_status_history';
        
        // VALIDATE USER REFERENCE
        $validated_user_id = self::validate_user_reference($changed_by_user_id);
        
        return $wpdb->insert(
            $table_name,
            array(
                'permit_id' => $permit_id,
                'previous_status' => $previous_status,
                'new_status' => $new_status,
                'changed_by_user_id' => $validated_user_id,  // Use validated user ID
                'changed_by_type' => $changed_by_type,
                'changed_by_name' => $changed_by_name,
                'reason' => $reason
            ),
            array('%d', '%s', '%s', '%d', '%s', '%s', '%s')
        );
    }
    
    public static function get_permit_comments($permit_id, $include_internal = false) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wps_permit_comments';
        
        $where_internal = $include_internal ? '' : 'AND is_internal = 0';
        
        $query = $wpdb->prepare("
            SELECT * FROM $table_name 
            WHERE permit_id = %d $where_internal 
            ORDER BY created_date ASC
        ", $permit_id);
        
        return $wpdb->get_results($query);
    }
    
    public static function get_permit_status_history($permit_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wps_permit_status_history';
        
        $query = $wpdb->prepare("
            SELECT * FROM $table_name 
            WHERE permit_id = %d 
            ORDER BY created_date ASC
        ", $permit_id);
        
        return $wpdb->get_results($query);
    }
    
    // ===== UTILITY METHODS =====
    
    /**
     * Get permit stats with consistent 4-status system
     */
    public static function get_permit_stats() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'work_permits';
        
        return array(
            self::STATUS_PENDING_REVIEW => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE status = %s", 
                self::STATUS_PENDING_REVIEW
            )),
            self::STATUS_PENDING_APPROVAL => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE status = %s", 
                self::STATUS_PENDING_APPROVAL
            )),
            self::STATUS_APPROVED => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE status = %s", 
                self::STATUS_APPROVED
            )),
            self::STATUS_CANCELLED => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE status = %s", 
                self::STATUS_CANCELLED
            ))
        );
    }
    
    public static function get_permits_by_email($email) {
        global $wpdb;
        $permits_table = $wpdb->prefix . 'work_permits';
        $categories_table = $wpdb->prefix . 'wps_work_categories';
        
        $query = $wpdb->prepare("
            SELECT 
                wp.*,
                wc.category_name
            FROM $permits_table wp
            LEFT JOIN $categories_table wc ON wp.work_category_id = wc.id
            WHERE wp.email_address = %s 
            ORDER BY wp.submitted_date DESC
        ", $email);
        
        $permits = $wpdb->get_results($query);
        
        // Add user details
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
        }
        
        return $permits;
    }
    
    /**
     * Get permit stats by email with corrected 4-status system
     */
    public static function get_permit_stats_by_email($email) {
        $permits = self::get_permits_by_email($email);
        
        $stats = array(
            'total' => count($permits),
            'pending_review' => 0,
            'pending_approval' => 0,
            'approved' => 0,
            'cancelled' => 0
        );
        
        foreach ($permits as $permit) {
            if (isset($stats[$permit->status])) {
                $stats[$permit->status]++;
            }
        }
        
        return $stats;
    }
    
    /**
     * Get permits by reviewer email (for backward compatibility)
     */
    public static function get_permits_by_reviewer_email($reviewer_email, $status = null) {
        // Get user by email first
        $user = get_user_by('email', $reviewer_email);
        if (!$user) {
            return array();
        }
        
        return self::get_permits_by_reviewer_user($user->ID, $status);
    }
    
    /**
     * Get permits by approver email (for backward compatibility)
     */
    public static function get_permits_by_approver_email($approver_email, $status = null) {
        // Get user by email first
        $user = get_user_by('email', $approver_email);
        if (!$user) {
            return array();
        }
        
        return self::get_permits_by_approver_user($user->ID, $status);
    }
    
    // ===== SEARCH AND FILTERING METHODS =====
    
    /**
     * Get permits with advanced filtering
     */
    public static function get_permits_filtered($filters = array()) {
        global $wpdb;
        $permits_table = $wpdb->prefix . 'work_permits';
        $categories_table = $wpdb->prefix . 'wps_work_categories';
        
        $where_conditions = array('1=1');
        $params = array();
        
        // Filter by status
        if (!empty($filters['status'])) {
            $where_conditions[] = "wp.status = %s";
            $params[] = $filters['status'];
        }
        
        // Filter by category
        if (!empty($filters['category_id'])) {
            $where_conditions[] = "wp.work_category_id = %d";
            $params[] = $filters['category_id'];
        }
        
        // Filter by date range
        if (!empty($filters['date_from'])) {
            $where_conditions[] = "wp.submitted_date >= %s";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = "wp.submitted_date <= %s";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        
        // Filter by reviewer
        if (!empty($filters['reviewer_id'])) {
            $where_conditions[] = "wp.reviewer_user_id = %d";
            $params[] = $filters['reviewer_id'];
        }
        
        // Filter by approver
        if (!empty($filters['approver_id'])) {
            $where_conditions[] = "wp.approver_user_id = %d";
            $params[] = $filters['approver_id'];
        }
        
        // Search in multiple fields
        if (!empty($filters['search'])) {
            $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
            $where_conditions[] = "(wp.email_address LIKE %s OR wp.issued_to LIKE %s OR wp.tenant LIKE %s OR wp.work_area LIKE %s)";
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Order by
        $order_by = !empty($filters['order_by']) ? $filters['order_by'] : 'wp.submitted_date';
        $order_dir = !empty($filters['order_dir']) && strtoupper($filters['order_dir']) === 'ASC' ? 'ASC' : 'DESC';
        
        // Limit
        $limit_clause = '';
        if (!empty($filters['limit'])) {
            $limit_clause = $wpdb->prepare("LIMIT %d", $filters['limit']);
            if (!empty($filters['offset'])) {
                $limit_clause = $wpdb->prepare("LIMIT %d, %d", $filters['offset'], $filters['limit']);
            }
        }
        
        $query = "
            SELECT 
                wp.*,
                wc.category_name
            FROM $permits_table wp
            LEFT JOIN $categories_table wc ON wp.work_category_id = wc.id
            WHERE $where_clause
            ORDER BY $order_by $order_dir
            $limit_clause
        ";
        
        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }
        
        $permits = $wpdb->get_results($query);
        
        // Add user details
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
        }
        
        return $permits;
    }
    
    // ===== BACKUP COMPATIBILITY METHODS =====
    
    /**
     * Update permit data
     */
    public static function update_permit($permit_id, $data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'work_permits';
        
        // Prepare format array based on data types
        $format = array();
        foreach ($data as $key => $value) {
            if (in_array($key, array('work_category_id', 'reviewer_user_id', 'approver_user_id'))) {
                $format[] = '%d';
            } else {
                $format[] = '%s';
            }
        }
        
        return $wpdb->update(
            $table_name,
            $data,
            array('id' => $permit_id),
            $format,
            array('%d')
        );
    }
    
    /**
     * Get recent permits
     */
    public static function get_recent_permits($limit = 10) {
        return self::get_permits_filtered(array(
            'order_by' => 'wp.submitted_date',
            'order_dir' => 'DESC',
            'limit' => $limit
        ));
    }
    
    /**
     * Get category statistics
     */
    public static function get_category_stats() {
        global $wpdb;
        $permits_table = $wpdb->prefix . 'work_permits';
        $categories_table = $wpdb->prefix . 'wps_work_categories';
        
        $pending_review = self::STATUS_PENDING_REVIEW;
        $pending_approval = self::STATUS_PENDING_APPROVAL;
        $approved = self::STATUS_APPROVED;
        $cancelled = self::STATUS_CANCELLED;
        
        return $wpdb->get_results("
            SELECT 
                wc.category_name,
                wc.id as category_id,
                COUNT(wp.id) as permit_count,
                SUM(CASE WHEN wp.status = '$pending_review' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN wp.status = '$pending_approval' THEN 1 ELSE 0 END) as pending_approval_count,
                SUM(CASE WHEN wp.status = '$approved' THEN 1 ELSE 0 END) as approved_count,
                SUM(CASE WHEN wp.status = '$cancelled' THEN 1 ELSE 0 END) as cancelled_count
            FROM $categories_table wc
            LEFT JOIN $permits_table wp ON wc.id = wp.work_category_id
            WHERE wc.is_active = 1
            GROUP BY wc.id, wc.category_name
            ORDER BY permit_count DESC, wc.sort_order ASC
        ");
    }
    
    /**
     * Get status display text with consistent 4-status system
     */
    public static function get_status_display_text($status) {
        $status_map = array(
            self::STATUS_PENDING_REVIEW => 'Pending Review',
            self::STATUS_PENDING_APPROVAL => 'Pending Approval',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_CANCELLED => 'Cancelled'
        );
        
        return isset($status_map[$status]) ? $status_map[$status] : ucfirst(str_replace('_', ' ', $status ?? ''));
    }
 
    /**
     * Add approved_by column to work_permits table
     * Call this from your plugin activation or update routine
     */
    public static function add_approved_by_column() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'work_permits';
        
        // Check if column already exists
        $column_exists = $wpdb->get_results(
            "SHOW COLUMNS FROM $table_name LIKE 'approved_by'"
        );
        
        if (empty($column_exists)) {
            $sql = "ALTER TABLE $table_name ADD COLUMN approved_by INT(11) NULL AFTER approver_user_id";
            $result = $wpdb->query($sql);
            
            if ($result === false) {
                error_log('WPS: Failed to add approved_by column: ' . $wpdb->last_error);
            } else {
            }
        } else {
            error_log('WPS: approved_by column already exists');
        }
    }

    /**
     * CRITICAL FIX: Reset migration flag for testing/debugging
     */
    public static function reset_signature_migration_flag() {
        delete_option('wps_signature_migration_completed');
        error_log('WPS: Signature migration flag reset');
    }

    /**
     * IMPROVED: Clean up duplicate documents across the entire system
     */
    public static function cleanup_all_duplicate_documents() {
        global $wpdb;
        
        $documents_table = $wpdb->prefix . 'wps_permit_documents';
        
        // Find and remove duplicates based on permit_id, filename, size, and type
        $duplicates = $wpdb->get_results("
            SELECT permit_id, original_filename, file_size, document_type, COUNT(*) as count, 
                GROUP_CONCAT(id ORDER BY upload_date DESC) as ids
            FROM $documents_table 
            WHERE is_active = 1
            GROUP BY permit_id, original_filename, file_size, document_type
            HAVING count > 1
        ");
        
        $removed_count = 0;
        
        foreach ($duplicates as $duplicate_group) {
            $ids = explode(',', $duplicate_group->ids);
            // Keep the first (most recent) one, mark others as inactive
            array_shift($ids); // Remove the first ID to keep it
            
            foreach ($ids as $id_to_remove) {
                $wpdb->update(
                    $documents_table,
                    array('is_active' => 0),
                    array('id' => intval($id_to_remove)),
                    array('%d'),
                    array('%d')
                );
                $removed_count++;
                error_log('WPS: Marked duplicate document as inactive: ID ' . $id_to_remove);
            }
        }
        
        return $removed_count;
    }
}