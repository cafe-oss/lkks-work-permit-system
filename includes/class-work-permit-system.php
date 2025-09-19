<?php
/**
 * Main plugin class - Fixed Version
 * File: includes/class-work-permit-system.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class Work_Permit_System
{
    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->load_dependencies();
        $this->init_hooks();
    }

    private function load_dependencies()
    {
        // Load Composer autoloader if exists
        if (file_exists(WPS_PLUGIN_PATH . 'vendor/autoload.php')) {
            require_once WPS_PLUGIN_PATH . 'vendor/autoload.php';
        }

        // Core dependencies
        require_once WPS_PLUGIN_PATH . 'includes/functions.php';
        require_once WPS_PLUGIN_PATH . 'includes/class-database.php';
        
        // Check if these files exist before requiring them
        if (file_exists(WPS_PLUGIN_PATH . 'includes/class-pdf-database.php')) {
            require_once WPS_PLUGIN_PATH . 'includes/class-pdf-database.php';
        }
        
        // Base classes
        if (file_exists(WPS_PLUGIN_PATH . 'includes/migration-add-file-hash.php')) {
            require_once WPS_PLUGIN_PATH . 'includes/migration-add-file-hash.php';
            if (WPS_Database_Migration::migration_needed()) {
                WPS_Database_Migration::run_migration();
            }
        }
        require_once WPS_PLUGIN_PATH . 'includes/class-signature-handler.php';
        require_once WPS_PLUGIN_PATH . 'includes/class-document-manager.php';
        require_once WPS_PLUGIN_PATH . 'includes/class-document-converter.php';
        
        // WordPress User System class (simplified)
        require_once WPS_PLUGIN_PATH . 'includes/class-user-roles.php';
        
        // Main classes
        require_once WPS_PLUGIN_PATH . 'includes/class-admin.php';
        require_once WPS_PLUGIN_PATH . 'includes/class-frontend.php';
        require_once WPS_PLUGIN_PATH . 'includes/class-email.php';
        require_once WPS_PLUGIN_PATH . 'includes/class-pdf-export.php';
        
        if (file_exists(WPS_PLUGIN_PATH . 'includes/class-background-remover.php')) {
            require_once WPS_PLUGIN_PATH . 'includes/class-background-remover.php';
        }
        
        // Plugin initialization and utilities
        require_once WPS_PLUGIN_PATH . 'includes/class-plugin-initializer.php';
    }

    private function init_hooks()
    {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        if (is_admin()) {
            add_action('admin_init', array($this, 'ensure_directories_exist'));
        }
    }

    public function init()
    {
        load_plugin_textdomain('work-permit-system', false, dirname(plugin_basename(__FILE__)) . '/languages');

        // Initialize WordPress User System components
        new WPS_User_Roles();
        
        // Initialize main components
        new WPS_Admin();
        new WPS_Frontend();
        new WPS_PDF_Export();
        
        // REMOVED: Don't create users on every init - this was causing the issue
        // Only create users through the admin interface or activation hook
    }

    public function enqueue_frontend_scripts()
    {
        wp_enqueue_style('wps-frontend', WPS_PLUGIN_URL . 'assets/css/frontend.css', array(), WPS_VERSION);
        wp_enqueue_script('wps-frontend', WPS_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), WPS_VERSION, true);
        
        wp_enqueue_style('wps-email-confirmation', WPS_PLUGIN_URL . 'assets/css/email-confirmation.css', array(), WPS_VERSION);
        wp_enqueue_script('wps-email-confirmation', WPS_PLUGIN_URL . 'assets/js/email-confirmation.js', array('jquery'), WPS_VERSION, true);

        wp_enqueue_style('dashicons');

        // PDF export for logged-in users
        if (is_user_logged_in() && wps_current_user_can_submit_permits()) {
            wp_enqueue_style('pdf-export', WPS_PLUGIN_URL . 'assets/css/pdf-export.css', array(), WPS_VERSION);
            wp_enqueue_script('pdf-export', WPS_PLUGIN_URL . 'assets/js/pdf-export.js', array('jquery'), WPS_VERSION, true);

            wp_localize_script('pdf-export', 'wpsPdfExport', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wps_nonce'),
                'strings' => array(
                    'generating' => __('Generating PDF...', 'work-permit-system'),
                    'success' => __('PDF export initiated. Check your downloads.', 'work-permit-system'),
                    'processing' => __('Generating your PDF export...', 'work-permit-system'),
                    'exportBtn' => __('Export My Permits as PDF', 'work-permit-system')
                )
            ));
        }

        wp_localize_script('wps-frontend', 'wps_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('submit_work_permit')
        ));

        wp_localize_script('wps-frontend', 'wps_frontend_data', array(
            'strings' => array(
                'no_file_selected' => __('No file selected', 'work-permit-system'),
                'file_selected' => __('File selected:', 'work-permit-system'),
                'uploading' => __('Uploading...', 'work-permit-system'),
                'upload_error' => __('Upload failed', 'work-permit-system'),
                'file_too_large' => __('File is too large. Maximum size allowed is', 'work-permit-system'),
                'invalid_file_type' => __('Invalid file type. Only PDF, DOCX, DOC, JPG, PNG, GIF, and WEBP files are allowed.', 'work-permit-system'),
                'submitting' => __('Submitting your work permit application, please wait...', 'work-permit-system')
            ),
            'limits' => array(
                'max_file_size' => class_exists('WPS_Document_Manager') ? WPS_Document_Manager::get_max_file_size_display() : '5MB',
                'max_file_size_bytes' => 5242880
            )
        ));
    }

    public function enqueue_admin_scripts($hook)
    {
        // Only load admin scripts on the main Work Permits admin page
        if ($hook !== 'toplevel_page_work-permits') {
            return;
        }

        // Enqueue admin styles
        wp_enqueue_style('wps-admin', WPS_PLUGIN_URL . 'assets/css/admin.css', array(), WPS_VERSION);
        
        // Enqueue admin JavaScript
        wp_enqueue_script('wps-admin', WPS_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), WPS_VERSION, true);
        
        // Localize admin scripts
        wp_localize_script('wps-admin', 'wps_admin_vars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wps_admin_nonce'),
            'strings' => array(
                'loading' => __('Loading...', 'work-permit-system'),
                'review' => __('Review', 'work-permit-system'),
                'error_loading' => __('Failed to load permit details. Please try again.', 'work-permit-system'),
                'confirm_delete' => __('Are you sure you want to delete this item?', 'work-permit-system'),
                'success_updated' => __('Updated successfully!', 'work-permit-system'),
                'confirm_approve' => __('Are you sure you want to approve this permit?', 'work-permit-system'),
                'confirm_reject' => __('Are you sure you want to reject this permit?', 'work-permit-system'),
                'comment_required' => __('Please provide a comment for your decision.', 'work-permit-system'),
                'export_generating' => __('Generating export file...', 'work-permit-system'),
                'export_complete' => __('Export completed successfully!', 'work-permit-system'),
                'export_error' => __('Error generating export. Please try again.', 'work-permit-system')
            )
        ));

        
    }

    /**
     * Create upload directories with proper permissions
     */
    public static function create_upload_directories()
    {
        $directories = array(
            WPS_PLUGIN_PATH . 'assets/signatures/requester/',
            WPS_PLUGIN_PATH . 'assets/exports/', // For report exports
            WPS_PLUGIN_PATH . 'assets/temp/' // For temporary files
        );
        
        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
                
                // Security files
                $htaccess_content = "Options -Indexes\n";
                $htaccess_content .= "<Files *.php>\n";
                $htaccess_content .= "Order Deny,Allow\n";
                $htaccess_content .= "Deny from All\n";
                $htaccess_content .= "</Files>\n";
                
                file_put_contents($dir . '.htaccess', $htaccess_content);
                file_put_contents($dir . 'index.php', '<?php // Silence is golden');
            }
        }
    }

    /**
     * Ensure directories exist (safety check)
     */
    public function ensure_directories_exist()
    {
        $directories = array(
            WPS_PLUGIN_PATH . 'assets/signatures/requester/',
            WPS_PLUGIN_PATH . 'assets/exports/',
            WPS_PLUGIN_PATH . 'assets/temp/'
        );
        
        $missing_dirs = false;
        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                $missing_dirs = true;
                break;
            }
        }
        
        if ($missing_dirs) {
            self::create_upload_directories();
        }
    }

    /**
     * Clean up temporary files (can be called via cron)
     */
    public static function cleanup_temp_files()
    {
        $temp_dir = WPS_PLUGIN_PATH . 'assets/temp/';
        if (!is_dir($temp_dir)) {
            return;
        }

        $files = glob($temp_dir . '*');
        $current_time = time();
        
        foreach ($files as $file) {
            if (is_file($file)) {
                // Delete files older than 1 hour
                if ($current_time - filemtime($file) > 3600) {
                    unlink($file);
                }
            }
        }
    }

    public static function activate() {
        // Prevent multiple simultaneous activations
        $activation_lock = 'wps_activation_in_progress';
        if (get_transient($activation_lock)) {
            error_log('WPS: Activation already in progress, skipping duplicate call');
            return;
        }
        
        set_transient($activation_lock, true, 120); // 2 minute lock
        
        try {
            error_log('WPS: Starting plugin activation process...');
            
            // Load activation dependencies
            self::load_activation_dependencies();
            
            // Create database tables (with built-in duplicate prevention)
            WPS_Database::create_tables();
            
            // Add approved_by column if needed
            WPS_Database::add_approved_by_column();
            
            // Create upload directories
            self::create_upload_directories();
            
            // Create custom user roles on activation
            WPS_User_Roles::create_custom_roles();
            
            // Create predefined users (only if not already done)
            if (!get_option('wps_predefined_users_created', false)) {
                $user_roles = new WPS_User_Roles();
                $result = $user_roles->create_predefined_users();
                error_log('WPS Activation: User creation result: ' . print_r($result, true));
                
                // Mark as completed only if users were actually created or updated
                if ($result['created'] > 0 || $result['updated'] > 0) {
                    update_option('wps_predefined_users_created', true);
                    error_log('WPS: Predefined users creation marked as completed');
                }
            } else {
                error_log('WPS: Predefined users already created, skipping');
            }
            
            // Run document system migration
            if (file_exists(WPS_PLUGIN_PATH . 'includes/migration-add-file-hash.php')) {
                require_once WPS_PLUGIN_PATH . 'includes/migration-add-file-hash.php';
                if (WPS_Database_Migration::migration_needed()) {
                    WPS_Database_Migration::run_migration();
                }
            }
            
            // Initialize plugin initializer functionality
            if (class_exists('WPS_Plugin_Initializer')) {
                WPS_Plugin_Initializer::on_activation();
            }
            
            // Schedule cleanup for temporary files
            if (!wp_next_scheduled('wps_cleanup_temp_files')) {
                wp_schedule_event(time(), 'hourly', 'wps_cleanup_temp_files');
            }
            
            // Clean up duplicate documents on activation
            $cleaned_docs = WPS_Database::cleanup_all_duplicate_documents();
            error_log("WPS Init: Cleaned up $cleaned_docs duplicate documents on activation");
            
            flush_rewrite_rules();
            
            error_log('WPS Init: Plugin activation completed with duplicate cleanup');
            
        } finally {
            // Always release the activation lock
            delete_transient($activation_lock);
        }
    }

    public static function deactivate()
    {
        // Clear scheduled cleanup
        wp_clear_scheduled_hook('wps_cleanup_temp_files');
        
        // Note: We don't remove user roles on deactivation to preserve user accounts
        // They can be manually removed if needed
        flush_rewrite_rules();
    }
    
    /**
     * Plugin uninstall cleanup (called from uninstall.php if needed)
     */
    public static function uninstall()
    {
        error_log('WPS: Class uninstall method called - this should only be used for debugging');
        
        // Just call the individual cleanup methods that might be useful separately
        self::cleanup_plugin_options();
        self::cleanup_plugin_files();
        
        // Log that this method was called
        error_log('WPS: Class uninstall method completed - for full uninstall use uninstall.php');
    }
    
    /**
     * ADDITIONAL: Method to manually remove the constraint if needed
     */
    public static function remove_unique_permit_file_constraint() {
        global $wpdb;
        
        $documents_table = $wpdb->prefix . 'wps_permit_documents';
        
        // Check if the constraint exists
        $constraint_exists = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM INFORMATION_SCHEMA.STATISTICS 
            WHERE TABLE_SCHEMA = %s 
            AND TABLE_NAME = %s 
            AND INDEX_NAME = 'unique_permit_file'
        ", $wpdb->dbname, $documents_table));
        
        if ($constraint_exists > 0) {
            error_log('WPS: Found unique_permit_file constraint, removing...');
            $result = $wpdb->query("ALTER TABLE $documents_table DROP INDEX unique_permit_file");
            
            if ($result !== false) {
                error_log('WPS: Successfully removed unique_permit_file constraint');
                return true;
            } else {
                error_log('WPS: Failed to remove unique_permit_file constraint: ' . $wpdb->last_error);
                return false;
            }
        } else {
            error_log('WPS: unique_permit_file constraint does not exist');
            return true;
        }
    }
    
    /**
     * NEW: Clean up plugin-specific options
     */
    private static function cleanup_plugin_options()
    {
        // List of plugin options to remove
        $options_to_delete = array(
            'wps_predefined_users_created',
            'wps_signature_migration_completed',
            // Add other plugin options here as needed
            // 'wps_plugin_version',
            // 'wps_settings',
            // etc.
        );
        
        foreach ($options_to_delete as $option) {
            delete_option($option);
            error_log("WPS Uninstall: Deleted option $option");
        }
    }

    /**
     * Clean up plugin files on uninstall
     */
    private static function cleanup_plugin_files()
    {
        $directories_to_clean = array(
            WPS_PLUGIN_PATH . 'assets/temp/',
            WPS_PLUGIN_PATH . 'assets/exports/',
            WPS_PLUGIN_PATH . 'assets/signatures/requester/' // Added signature directory
        );
        
        foreach ($directories_to_clean as $dir) {
            if (is_dir($dir)) {
                error_log("WPS Uninstall: Cleaning directory $dir");
                
                // Get all files in directory
                $files = array_diff(scandir($dir), array('.', '..', '.htaccess', 'index.php'));
                $cleaned_files = 0;
                
                foreach ($files as $file) {
                    $file_path = $dir . $file;
                    if (is_file($file_path)) {
                        if (unlink($file_path)) {
                            $cleaned_files++;
                        } else {
                            error_log("WPS Uninstall: Failed to delete file $file_path");
                        }
                    } elseif (is_dir($file_path)) {
                        // Recursively delete subdirectories
                        self::recursive_directory_delete($file_path);
                    }
                }
                
                error_log("WPS Uninstall: Cleaned $cleaned_files files from $dir");
            }
        }
    }

    /**
     * NEW: Recursively delete directory and its contents
     */
    private static function recursive_directory_delete($dir)
    {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), array('.', '..'));
        
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            
            if (is_dir($path)) {
                self::recursive_directory_delete($path);
            } else {
                unlink($path);
            }
        }
        
        return rmdir($dir);
    }

    /**
     * NEW: Optional method to create a backup before uninstall
     */
    private static function create_backup_before_uninstall()
    {
        global $wpdb;
        
        // Create backup directory
        $backup_dir = WPS_PLUGIN_PATH . 'backup_' . date('Y-m-d_H-i-s') . '/';
        if (!wp_mkdir_p($backup_dir)) {
            error_log('WPS Uninstall: Failed to create backup directory');
            return false;
        }
        
        // Export table data to SQL files
        $tables = array(
            $wpdb->prefix . 'work_permits',
            $wpdb->prefix . 'wps_work_categories',
            $wpdb->prefix . 'wps_permit_documents',
            $wpdb->prefix . 'wps_permit_comments',
            $wpdb->prefix . 'wps_permit_status_history'
        );
        
        foreach ($tables as $table) {
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
            
            if ($table_exists) {
                $rows = $wpdb->get_results("SELECT * FROM $table", ARRAY_A);
                
                if (!empty($rows)) {
                    $sql_content = "-- Backup of $table created on " . date('Y-m-d H:i:s') . "\n\n";
                    
                    foreach ($rows as $row) {
                        $columns = array_keys($row);
                        $values = array_values($row);
                        
                        // Escape values
                        $escaped_values = array();
                        foreach ($values as $value) {
                            if ($value === null) {
                                $escaped_values[] = 'NULL';
                            } else {
                                $escaped_values[] = "'" . esc_sql($value) . "'";
                            }
                        }
                        
                        $sql_content .= "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $escaped_values) . ");\n";
                    }
                    
                    file_put_contents($backup_dir . basename($table) . '.sql', $sql_content);
                }
            }
        }
        
        error_log("WPS Uninstall: Backup created in $backup_dir");
        return true;
    }

    /**
     * Load required files for activation
     */
    private static function load_activation_dependencies()
    {
        require_once WPS_PLUGIN_PATH . 'includes/functions.php';
        require_once WPS_PLUGIN_PATH . 'includes/class-database.php';
        require_once WPS_PLUGIN_PATH . 'includes/class-user-roles.php';
        
        // Load plugin initializer for activation
        if (file_exists(WPS_PLUGIN_PATH . 'includes/class-plugin-initializer.php')) {
            require_once WPS_PLUGIN_PATH . 'includes/class-plugin-initializer.php';
        }
    }

    /**
     * Get plugin version for cache busting
     */
    public static function get_version()
    {
        return WPS_VERSION;
    }

    /**
     * Check if current page is work permits admin
     */
    public static function is_work_permits_admin()
    {
        global $pagenow;
        return is_admin() && $pagenow === 'admin.php' && isset($_GET['page']) && $_GET['page'] === 'work-permits';
    }
}