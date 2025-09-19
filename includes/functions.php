<?php
/**
 * Helper functions - Sequential Workflow
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Format date for display
 */
function wps_format_date($date)
{
    return date_i18n('M j, Y g:i A', strtotime($date));
}

/**
 * Format date for display splits them into two lines.
 */
function wps_format_date_multiline($date)
{
    $timestamp = strtotime($date);
    if (!$timestamp) {
        return '';
    }

    // First line: Date
    $formatted_date = date_i18n('M j, Y', $timestamp);

    // Second line: Time
    $formatted_time = date_i18n('g:i A', $timestamp);

    // Return with line break
    return $formatted_date . "\n" . $formatted_time;
}


/**
 * Format time for display
 */
function wps_format_time($time)
{
    return date_i18n('g:i A', strtotime($time));
}

/**
 * Check if current user can submit permits (tenant/subscriber functionality)
 */
function wps_current_user_can_submit_permits()
{
    // Since no login required, always return true
    // Or you could implement other logic like IP restrictions, etc.
    return true;
}

/**
 * Check if current user can access admin management features
 * Only users with IDs 2 and 9 can access restricted admin tabs
 * 
 * @return bool True if user can access restricted features, false otherwise
 */
function wps_user_can_access_admin_features() {
    // Don't allow if user is not logged in
    if (!is_user_logged_in()) {
        return false;
    }
    
    $current_user_id = get_current_user_id();
    $allowed_user_ids = apply_filters('wps_allowed_admin_user_ids', array(2, 9, 1));
    
    // Additional security: ensure user has basic admin capabilities
    if (!current_user_can('read')) {
        return false;
    }
    
    return in_array($current_user_id, $allowed_user_ids, true);
}

/**
 * Display admin access notice
 * 
 * @param string $type Type of notice (error, warning, info, success)
 * @param string $title Notice title
 * @param string $message Notice message
 * @param bool $is_dismissible Whether notice can be dismissed
 */
function wps_display_admin_notice($type = 'info', $title = '', $message = '', $is_dismissible = true) {
    $allowed_types = array('error', 'warning', 'info', 'success');
    $type = in_array($type, $allowed_types) ? $type : 'info';
    
    $dismissible_class = $is_dismissible ? ' is-dismissible' : '';
    
    echo '<div class="notice notice-' . esc_attr($type) . esc_attr($dismissible_class) . '">';
    
    if (!empty($title)) {
        echo '<p><strong>' . esc_html($title) . '</strong></p>';
    }
    
    if (!empty($message)) {
        echo '<p>' . wp_kses_post($message) . '</p>';
    }
    
    echo '</div>';
}