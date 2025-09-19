<?php
/**
 * Main plugin class - Fixed Version
 * File: uninstall.php
 */

/**
 * Fired when the plugin is uninstalled - WordPress Best Practice Version
 */
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Load only essential dependencies
require_once plugin_dir_path(__FILE__) . 'includes/class-user-roles.php';

global $wpdb;

/**
 * Remove custom user roles
 */
WPS_User_Roles::remove_custom_roles();

/**
 * Delete plugin-created users (optional)
 */
$plugin_created_emails = array(
    'wp_engg@limketkai.com.ph',
    'wp_ops@limketkai.com.ph', 
    'wp_it@limketkai.com.ph',
    'jhonas.soler@limketkai.com.ph',
    'sim.lomongo@limketkai.com.ph',
    'joecyn.marba@limketkai.com.ph',
    'ralph.gamboa@limketkai.com.ph'
);

foreach ($plugin_created_emails as $email) {
    $user = get_user_by('email', $email);
    if ($user) {
        $user_roles = $user->roles;
        $has_other_roles = false;
        
        foreach ($user_roles as $role) {
            if (!in_array($role, array('wps_reviewer', 'wps_approver'))) {
                $has_other_roles = true;
                break;
            }
        }
        
        if (!$has_other_roles) {
            wp_delete_user($user->ID);
        }
    }
}

/**
 * Drop database tables
 */
// Disable foreign key checks
$wpdb->query("SET FOREIGN_KEY_CHECKS = 0");

$tables_to_drop = array(
    $wpdb->prefix . 'wps_permit_status_history',
    $wpdb->prefix . 'wps_permit_comments', 
    $wpdb->prefix . 'wps_permit_documents',
    $wpdb->prefix . 'work_permits',
    $wpdb->prefix . 'wps_work_categories'
);

foreach ($tables_to_drop as $table) {
    // Remove foreign key constraints
    $foreign_keys = $wpdb->get_results($wpdb->prepare("
        SELECT CONSTRAINT_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = %s 
        AND TABLE_NAME = %s 
        AND CONSTRAINT_NAME != 'PRIMARY'
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ", $wpdb->dbname, $table));
    
    foreach ($foreign_keys as $fk) {
        $wpdb->query("ALTER TABLE `$table` DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
    }
    
    // Drop table
    $wpdb->query("DROP TABLE IF EXISTS `$table`");
}

// Re-enable foreign key checks
$wpdb->query("SET FOREIGN_KEY_CHECKS = 1");

/**
 * Clean up options
 */
$options_to_delete = array(
    'wps_predefined_users_created',
    'wps_signature_migration_completed',
    'wps_plugin_version',
    'wps_database_version',
    'wps_plugin_settings',
    'wps_email_settings'
);

foreach ($options_to_delete as $option) {
    delete_option($option);
}

/**
 * Remove transients
 */
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wps_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_wps_%'");

/**
 * Remove user metadata
 */
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'wps_%'");

/**
 * Clean up files
 */
$directories_to_clean = array(
    plugin_dir_path(__FILE__) . 'assets/temp/',
    plugin_dir_path(__FILE__) . 'assets/exports/',
    plugin_dir_path(__FILE__) . 'assets/signatures/requester/'
);

foreach ($directories_to_clean as $dir) {
    if (is_dir($dir)) {
        $files = array_diff(scandir($dir), array('.', '..', '.htaccess', 'index.php'));
        foreach ($files as $file) {
            $file_path = $dir . $file;
            if (is_file($file_path)) {
                unlink($file_path);
            }
        }
    }
}

/**
 * Clean up WordPress uploads directory
 */
$upload_dir = wp_upload_dir();
$plugin_upload_path = $upload_dir['basedir'] . '/work-permit-system/';

if (is_dir($plugin_upload_path)) {
    $files = glob($plugin_upload_path . '*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    if (count(scandir($plugin_upload_path)) == 2) {
        rmdir($plugin_upload_path);
    }
}

/**
 * Clear scheduled events
 */
wp_clear_scheduled_hook('wps_cleanup_temp_files');

/**
 * Delete plugin pages
 */
$page = get_page_by_path('work-permit-form');
if ($page) {
    wp_delete_post($page->ID, true);
}
?>