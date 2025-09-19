<?php
/**
 * Fixed Plugin Initializer with Document Cleanup
 * File: includes/class-plugin-initializer.php
 * Addresses document duplication issues
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPS_Plugin_Initializer {
    
    /**
     * Handle plugin activation
     */
    public static function on_activation() {
        // Create necessary directories
        self::create_directories();
        
        // Schedule cleanup cron job
        self::register_cleanup_cron();
        
        // Clean up any existing duplicates on activation
        self::cleanup_existing_duplicates();
        
        // Update version in database
        update_option('wps_supporting_docs_version', WPS_VERSION);
        
        error_log('WPS Init: Plugin activation completed with duplicate cleanup');
        
        return true;
    }
    
    /**
     * Handle plugin deactivation
     */
    public static function on_deactivation() {
        // Clear scheduled cron jobs
        wp_clear_scheduled_hook('wps_cleanup_expired_documents');
        wp_clear_scheduled_hook('wps_cleanup_duplicate_documents');
        
        error_log('WPS Init: Plugin deactivation completed');
    }
    
    /**
     * Register cron jobs for document cleanup
     */
    private static function register_cleanup_cron() {
        // Daily cleanup of expired documents
        if (!wp_next_scheduled('wps_cleanup_expired_documents')) {
            wp_schedule_event(time(), 'daily', 'wps_cleanup_expired_documents');
            error_log('WPS Init: Registered daily cleanup cron job');
        }
        
        // Weekly cleanup of duplicate documents
        if (!wp_next_scheduled('wps_cleanup_duplicate_documents')) {
            wp_schedule_event(time(), 'weekly', 'wps_cleanup_duplicate_documents');
            error_log('WPS Init: Registered weekly duplicate cleanup cron job');
        }
    }
    
    /**
     * Clean up existing duplicates on activation
     */
    private static function cleanup_existing_duplicates() {
        if (class_exists('WPS_Database')) {
            try {
                $removed_count = WPS_Database::cleanup_all_duplicate_documents();
                error_log('WPS Init: Cleaned up ' . $removed_count . ' duplicate documents on activation');
            } catch (Exception $e) {
                error_log('WPS Init: Error during duplicate cleanup: ' . $e->getMessage());
            }
        }
        
        if (class_exists('WPS_Document_Manager')) {
            try {
                // Clean up duplicates for each permit individually
                global $wpdb;
                $permits_table = $wpdb->prefix . 'work_permits';
                $permit_ids = $wpdb->get_col("SELECT id FROM $permits_table");
                
                foreach ($permit_ids as $permit_id) {
                    WPS_Document_Manager::cleanup_duplicate_documents($permit_id);
                }
                
                error_log('WPS Init: Completed per-permit duplicate cleanup');
            } catch (Exception $e) {
                error_log('WPS Init: Error during per-permit cleanup: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Create necessary directories
     */
    public static function create_directories() {
        $directories = array(
            WPS_PLUGIN_PATH . 'assets/supporting-documents',
            wp_upload_dir()['basedir'] . '/wps-temp',
            WPS_PLUGIN_PATH . 'assets/signatures/requester/',
            WPS_PLUGIN_PATH . 'assets/exports/',
            WPS_PLUGIN_PATH . 'assets/temp/'
        );
        
        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
                
                // Add security files
                $htaccess_content = "Options -Indexes\n";
                $htaccess_content .= "<Files *.php>\n";
                $htaccess_content .= "Order Deny,Allow\n";
                $htaccess_content .= "Deny from All\n";
                $htaccess_content .= "</Files>\n";
                
                file_put_contents($dir . '/.htaccess', $htaccess_content);
                file_put_contents($dir . '/index.php', '<?php // Silence is golden');
                
                error_log('WPS Init: Created directory: ' . $dir);
            }
        }
        
        return true;
    }
    
    /**
     * Handle cron cleanup event for expired documents
     */
    public static function handle_cleanup_cron() {
        if (class_exists('WPS_Document_Manager')) {
            $deleted_count = WPS_Document_Manager::cleanup_expired_documents();
            error_log('WPS Cron: Cleaned up ' . $deleted_count . ' expired documents');
        }
        
        // Also cleanup temp files
        if (class_exists('Work_Permit_System')) {
            Work_Permit_System::cleanup_temp_files();
        }
    }
    
    /**
     * Handle cron cleanup event for duplicate documents
     */
    public static function handle_duplicate_cleanup_cron() {
        if (class_exists('WPS_Database')) {
            try {
                $removed_count = WPS_Database::cleanup_all_duplicate_documents();
                error_log('WPS Cron: Weekly cleanup removed ' . $removed_count . ' duplicate documents');
            } catch (Exception $e) {
                error_log('WPS Cron: Error during weekly duplicate cleanup: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Register WordPress hooks
     */
    public static function register_hooks() {
        // Cron hooks
        add_action('wps_cleanup_expired_documents', array(__CLASS__, 'handle_cleanup_cron'));
        add_action('wps_cleanup_duplicate_documents', array(__CLASS__, 'handle_duplicate_cleanup_cron'));
        
        // Add admin notice for duplicate cleanup
        add_action('admin_notices', array(__CLASS__, 'show_cleanup_notice'));
    }
    
    /**
     * Show admin notice if duplicates are detected
     */
    public static function show_cleanup_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Check if we're on the work permits admin page
        if (!isset($_GET['page']) || $_GET['page'] !== 'work-permits') {
            return;
        }
        
        // Check for duplicates
        if (class_exists('WPS_Database')) {
            global $wpdb;
            $documents_table = $wpdb->prefix . 'wps_permit_documents';
            
            $duplicate_count = $wpdb->get_var("
                SELECT COUNT(*) - COUNT(DISTINCT permit_id, original_filename, file_size, document_type)
                FROM $documents_table 
                WHERE is_active = 1
            ");
            
            if ($duplicate_count > 0) {
                ?>
                <div class="notice notice-warning is-dismissible">
                    <p>
                        <strong>Work Permit System:</strong> 
                        <?php echo sprintf(
                            esc_html__('Detected %d duplicate documents. Click %shere%s to clean them up.', 'work-permit-system'),
                            $duplicate_count,
                            '<a href="' . esc_url(admin_url('admin.php?page=work-permits&action=cleanup_duplicates&nonce=' . wp_create_nonce('wps_cleanup_duplicates'))) . '">',
                            '</a>'
                        ); ?>
                    </p>
                </div>
                <?php
            }
        }
    }
    
    /**
     * Handle manual duplicate cleanup request
     */
    public static function handle_manual_cleanup() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        if (!wp_verify_nonce($_GET['nonce'] ?? '', 'wps_cleanup_duplicates')) {
            wp_die(__('Security check failed.'));
        }
        
        $removed_count = 0;
        
        if (class_exists('WPS_Database')) {
            $removed_count = WPS_Database::cleanup_all_duplicate_documents();
        }
        
        // Redirect back with success message
        $redirect_url = add_query_arg(
            array(
                'page' => 'work-permits',
                'cleanup_result' => $removed_count
            ),
            admin_url('admin.php')
        );
        
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Show cleanup result message
     */
    public static function show_cleanup_result() {
        if (isset($_GET['cleanup_result'])) {
            $removed_count = intval($_GET['cleanup_result']);
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <strong>Work Permit System:</strong> 
                    <?php echo sprintf(
                        esc_html__('Successfully cleaned up %d duplicate documents.', 'work-permit-system'),
                        $removed_count
                    ); ?>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Initialize admin actions
     */
    public static function init_admin_actions() {
        // Handle cleanup action
        if (is_admin() && isset($_GET['action']) && $_GET['action'] === 'cleanup_duplicates') {
            self::handle_manual_cleanup();
        }
        
        // Show cleanup result
        add_action('admin_notices', array(__CLASS__, 'show_cleanup_result'));
    }
    
    /**
     * Debug function to check for document issues
     */
    public static function debug_document_issues() {
        if (!current_user_can('manage_options')) {
            return array('error' => 'Insufficient permissions');
        }
        
        global $wpdb;
        $documents_table = $wpdb->prefix . 'wps_permit_documents';
        
        $debug_info = array();
        
        // Check for duplicates
        $duplicates = $wpdb->get_results("
            SELECT permit_id, original_filename, file_size, document_type, COUNT(*) as count
            FROM $documents_table 
            WHERE is_active = 1
            GROUP BY permit_id, original_filename, file_size, document_type
            HAVING count > 1
        ");
        
        $debug_info['duplicate_groups'] = count($duplicates);
        $debug_info['total_duplicates'] = array_sum(array_column($duplicates, 'count')) - count($duplicates);
        
        // Check for missing files
        $documents = $wpdb->get_results("SELECT id, file_path, original_filename FROM $documents_table WHERE is_active = 1");
        $missing_files = array();
        
        foreach ($documents as $doc) {
            if (!file_exists($doc->file_path)) {
                $missing_files[] = array(
                    'id' => $doc->id,
                    'filename' => $doc->original_filename,
                    'path' => $doc->file_path
                );
            }
        }
        
        $debug_info['missing_files'] = $missing_files;
        $debug_info['missing_file_count'] = count($missing_files);
        
        // Check signature migration status
        $debug_info['signature_migration_completed'] = get_option('wps_signature_migration_completed', false);
        $debug_info['signature_document_count'] = $wpdb->get_var("
            SELECT COUNT(*) FROM $documents_table 
            WHERE document_type = 'signature' AND is_active = 1
        ");
        
        return $debug_info;
    }
    
    /**
     * Reset signature migration for testing
     */
    public static function reset_signature_migration() {
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        if (class_exists('WPS_Database')) {
            WPS_Database::reset_signature_migration_flag();
            return true;
        }
        
        return false;
    }
}