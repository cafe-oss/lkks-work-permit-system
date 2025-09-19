<?php
/**
 * WordPress User Roles Integration for Work Permit System - Temporary Admin Access
 * File: includes/class-user-roles.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPS_User_Roles {
    
    public function __construct() {
        add_action('init', array($this, 'create_custom_roles'));
        // Temporary: Add admin menu for testing
    }
    
    /**
     * Temporary reviewer dashboard for testing
     */
    public function temp_reviewer_dashboard_page() {
        echo '<div class="wrap">';
        echo '<h1>Reviewer Dashboard (Testing Mode)</h1>';
        echo '<div class="notice notice-info"><p><strong>Testing Mode:</strong> This is a temporary dashboard for testing purposes.</p></div>';
        
        // Get some test permits assigned to reviewers
        $test_permits = WPS_Database::get_all_permits();
        $reviewer_permits = array_filter($test_permits, function($permit) {
            return in_array($permit->status, ['pending_review', 'pending_approval']);
        });
        
        if (!empty($reviewer_permits)) {
            echo '<h2>Permits for Review</h2>';
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr>';
            echo '<th>ID</th><th>Applicant</th><th>Category</th><th>Status</th><th>Submitted</th><th>Actions</th>';
            echo '</tr></thead><tbody>';
            
            foreach (array_slice($reviewer_permits, 0, 10) as $permit) {
                $status_class = 'status-' . str_replace('_', '-', $permit->status);
                echo '<tr>';
                echo '<td>' . esc_html($permit->id) . '</td>';
                echo '<td>' . esc_html($permit->email_address) . '</td>';
                echo '<td>' . esc_html($permit->category_name) . '</td>';
                echo '<td><span class="status-badge ' . esc_attr($status_class) . '">' . esc_html(ucwords(str_replace('_', ' ', $permit->status))) . '</span></td>';
                echo '<td>' . esc_html(wps_format_date($permit->submitted_date)) . '</td>';
                echo '<td><button class="button button-small">Review</button></td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
        } else {
            echo '<p>No permits currently available for review.</p>';
        }
        
        echo '</div>';
    }
    
    /**
     * Temporary approver dashboard for testing
     */
    public function temp_approver_dashboard_page() {
        echo '<div class="wrap">';
        echo '<h1>Approver Dashboard (Testing Mode)</h1>';
        echo '<div class="notice notice-info"><p><strong>Testing Mode:</strong> This is a temporary dashboard for testing purposes.</p></div>';
        
        // Get some test permits for approval
        $test_permits = WPS_Database::get_all_permits();
        $approver_permits = array_filter($test_permits, function($permit) {
            return $permit->status === 'pending_approval';
        });
        
        if (!empty($approver_permits)) {
            echo '<h2>Permits for Approval</h2>';
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr>';
            echo '<th>ID</th><th>Applicant</th><th>Category</th><th>Reviewer</th><th>Submitted</th><th>Actions</th>';
            echo '</tr></thead><tbody>';
            
            foreach (array_slice($approver_permits, 0, 10) as $permit) {
                echo '<tr>';
                echo '<td>' . esc_html($permit->id) . '</td>';
                echo '<td>' . esc_html($permit->email_address) . '</td>';
                echo '<td>' . esc_html($permit->category_name) . '</td>';
                echo '<td>' . esc_html($permit->reviewer_name ?? 'Not assigned') . '</td>';
                echo '<td>' . esc_html(wps_format_date($permit->submitted_date)) . '</td>';
                echo '<td>';
                echo '<button class="button button-primary button-small">Approve</button> ';
                echo '<button class="button button-secondary button-small">Reject</button>';
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
        } else {
            echo '<p>No permits currently pending approval.</p>';
        }
        
        echo '</div>';
    }
    
    /**
     * Create custom user roles for the work permit system
     */
    public static function create_custom_roles() {
        // Only run this once or when specifically requested
        if (get_option('wps_roles_created') && !isset($_GET['refresh_roles'])) {
            return;
        }
        
        // Reviewer role
        add_role('wps_reviewer', 'Work Permit Reviewer', array(
            'read' => true,
            'wps_review_permits' => true,
            'wps_view_assigned_permits' => true,
            'wps_add_comments' => true,
            'wps_change_permit_status' => true,
        ));
        
        // Approver role
        add_role('wps_approver', 'Work Permit Approver', array(
            'read' => true,
            'wps_approve_permits' => true,
            'wps_view_assigned_permits' => true,
            'wps_add_comments' => true,
            'wps_change_permit_status' => true,
            'wps_final_approval' => true,
        ));
        
        // Add capabilities to administrator
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->add_cap('wps_review_permits');
            $admin_role->add_cap('wps_approve_permits');
            $admin_role->add_cap('wps_view_assigned_permits');
            $admin_role->add_cap('wps_add_comments');
            $admin_role->add_cap('wps_change_permit_status');
            $admin_role->add_cap('wps_final_approval');
            $admin_role->add_cap('wps_manage_system');
        }
        
        update_option('wps_roles_created', true);
    }
    
    /**
     * Create predefined users based on the email assignments
     * This can be called programmatically instead of through a submenu
     */
    public function create_predefined_users() {
        $users_to_create = array(
            // Reviewers
            // array(
            //     'email' => 'kl092973@gmail.com',
            //     'username' => 'we_dev',
            //     'display_name' => 'Web Dev Team',
            //     'role' => 'wps_reviewer',
            //     'categories' => array('Renovation Work', 'Others')
            // ),
            array(
                'email' => 'wp_engg@limketkai.com.ph',
                'username' => 'wp_engineering',
                'display_name' => 'Engineering Team',
                'role' => 'wps_reviewer',
                'categories' => array('Electrical Works', 'Delivery (Construction)', 'Welding', 'Renovation Work')
            ),
            array(
                'email' => 'wp_ops@limketkai.com.ph',
                'username' => 'wp_operations',
                'display_name' => 'Operations Team',
                'role' => 'wps_reviewer',
                'categories' => array('Maintenance and Repairs (Building Admin)', 'Delivery (Merchandise)', 'Pullout', 'Painting', 'Plumbing', 'Sprinkler', 'Pest Control', 'Others')
                // 'categories' => array('Maintenance and Repairs (Building Admin)', 'Delivery (Merchandise)', 'Pullout', 'Painting', 'Plumbing', 'Sprinkler', 'Pest Control')
            ),
            array(
                'email' => 'wp_it@limketkai.com.ph',
                'username' => 'wp_it_team',
                'display_name' => 'IT Team',
                'role' => 'wps_reviewer',
                'categories' => array('Communication (ISP, Telco, POS)')
            ),
            array(
                'email' => 'jhonas.soler@limketkai.com.ph',
                'username' => 'jhonas_soler',
                'display_name' => 'Jhonas Soler',
                'role' => 'wps_reviewer',
                'categories' => array('Maintenance and Repairs (AHU)')
            ),
            
            // Approvers
            // array(
            //     'email' => 'alvincafejhon@yahoo.com',
            //     'username' => 'full_stack',
            //     'display_name' => 'Full Stack Dev',
            //     'role' => 'wps_approver',
            //     'categories' => array('Renovation Work', 'Others')
            // ),
            array(
                'email' => 'sim.lomongo@limketkai.com.ph',
                'username' => 'sim_lomongo',
                'display_name' => 'Sim Lomongo',
                'role' => 'wps_approver',
                'categories' => array('Renovation Work', 'Electrical Works', 'Maintenance and Repairs (AHU)', 'Delivery (Construction)', 'Welding')
            ),
            array(
                'email' => 'joecyn.marba@limketkai.com.ph',
                'username' => 'joecyn_marba',
                'display_name' => 'Joecyn Marba',
                'role' => 'wps_approver',
                'categories' => array('Communication (ISP, Telco, POS)')
            ),
            array(
                'email' => 'ralph.gamboa@limketkai.com.ph',
                'username' => 'ralph_gamboa',
                'display_name' => 'Ralph Gamboa',
                'role' => 'wps_approver',
                'categories' => array('Maintenance and Repairs (Building Admin)', 'Delivery (Merchandise)', 'Pullout', 'Painting', 'Plumbing', 'Sprinkler', 'Pest Control', 'Others')
                // 'categories' => array('Maintenance and Repairs (Building Admin)', 'Delivery (Merchandise)', 'Pullout', 'Painting', 'Plumbing', 'Sprinkler', 'Pest Control')
            )
        );
        
        $created_count = 0;
        $updated_count = 0;
        $errors = array();
        
        foreach ($users_to_create as $user_data) {
            $user_exists = get_user_by('email', $user_data['email']);
            
            if ($user_exists) {
                // Update existing user - ensure they have the correct role
                $user_id = $user_exists->ID;
                
                // Remove old custom roles and add the correct one
                $user_exists->remove_role('wps_reviewer');
                $user_exists->remove_role('wps_approver');
                $user_exists->add_role($user_data['role']);
                
                // Update display name
                wp_update_user(array(
                    'ID' => $user_id,
                    'display_name' => $user_data['display_name']
                ));
                
                // Update user meta for categories
                update_user_meta($user_id, 'wps_assigned_categories', $user_data['categories']);
                
                $updated_count++;
            } else {
                // Create new user
                // $password = wp_generate_password(12);
                $password = "test123";
                
                // Check if username already exists, if so, make it unique
                $username = $user_data['username'];
                if (username_exists($username)) {
                    $username = $user_data['username'] . '_' . rand(100, 999);
                }
                
                $user_id = wp_create_user(
                    $username,
                    $password,
                    $user_data['email']
                );
                
                if (is_wp_error($user_id)) {
                    $errors[] = "Failed to create user {$user_data['email']}: " . $user_id->get_error_message();
                    continue;
                }
                
                // Set display name and role
                wp_update_user(array(
                    'ID' => $user_id,
                    'display_name' => $user_data['display_name']
                ));
                
                $user = new WP_User($user_id);
                $user->add_role($user_data['role']);
                
                // Store assigned categories as user meta
                update_user_meta($user_id, 'wps_assigned_categories', $user_data['categories']);
                
                // Mark this user as created by the plugin for easier cleanup
                update_user_meta($user_id, 'wps_plugin_created', true);
                
                // Send password reset email
                wp_send_new_user_notifications($user_id, 'user');
                
                $created_count++;
            }
        }
        
        // Add debug logging
        if (!empty($errors)) {
            error_log("WPS User Creation Errors: " . print_r($errors, true));
        }
        
        return array(
            'created' => $created_count,
            'updated' => $updated_count,
            'errors' => $errors
        );
    }
    
    /**
     * Remove custom roles on plugin deactivation
     */
    public static function remove_custom_roles() {
        remove_role('wps_reviewer');
        remove_role('wps_approver');
        delete_option('wps_roles_created');
    }
}