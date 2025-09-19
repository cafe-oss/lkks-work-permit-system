<?php

/**
 * Plugin Name: Work Permit System
 * Plugin URI: https://limketkaimall.com
 * Description: A custom work permit management system for tenants and administrators, use [work_permit_form] shortcode to display the tenant's form.
 * Version: 1.0.0
 * Author: Limketkaimall IT Team
 * License: GPL v2 or later
 * Text Domain: work-permit-system
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WPS_PLUGIN_FILE', __FILE__);
define('WPS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPS_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WPS_VERSION', '1.0.0');

// Include the main plugin class
require_once WPS_PLUGIN_PATH . 'includes/class-work-permit-system.php';

// Initialize the plugin
function wps_init()
{
    return Work_Permit_System::get_instance();
}
add_action('plugins_loaded', 'wps_init');

// Add debugging for deprecated WP_User->id usage temporarirly
// add_action('plugins_loaded', function() {
//     if (defined('WP_DEBUG') && WP_DEBUG) {
//         add_action('deprecated_argument_run', function($function, $message, $version) {
//             if (strpos($message, 'WP_User->id') !== false) {
//                 error_log("WPS DEBUG - DEPRECATED CALL FOUND:");
//                 error_log("Function: " . $function);
//                 error_log("Message: " . $message);
//                 error_log("Version: " . $version);
//                 error_log("Backtrace: " . wp_debug_backtrace_summary());
//             }
//         }, 10, 3); // Accept all 3 parameters
//     }
// }, 1);

// Activation and deactivation hooks
register_activation_hook(__FILE__, array('Work_Permit_System', 'activate'));
register_deactivation_hook(__FILE__, array('Work_Permit_System', 'deactivate'));