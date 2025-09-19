<?php
/**
 * Main Admin Page - Dashboard, Permits
 * File: templates/admin/admin-page.php
 */
// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Get available tabs
$tabs = array(
    // 'dashboard' => __('Dashboard', 'work-permit-system'),
    // 'permits' => __('All Permits', 'work-permit-system'),
);

?>
<div class="wrap">
    <!-- <h1><?php echo esc_html(get_admin_page_title()); ?></h1> -->
    
    <!-- Tab Navigation -->
    <!-- <nav class="nav-tab-wrapper wp-clearfix" aria-label="<?php esc_attr_e('Primary navigation', 'work-permit-system'); ?>">
        <?php foreach ($tabs as $tab_key => $tab_name): ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=work-permits&tab=' . $tab_key)); ?>" 
               class="nav-tab <?php echo ($current_tab === $tab_key) ? 'nav-tab-active' : ''; ?>"
               <?php if ($current_tab === $tab_key): ?>aria-current="page"<?php endif; ?>>
                <?php echo esc_html($tab_name); ?>
            </a>
        <?php endforeach; ?>
    </nav> -->
    
    <!-- Tab Content -->
    <div class="tab-content">
        <?php
        switch ($current_tab) {
            // case 'permits':
            //     include WPS_PLUGIN_PATH . 'templates/admin/permits-tab.php';
            //     break;
            case 'dashboard':
            default:
                include WPS_PLUGIN_PATH . 'templates/admin/dashboard-tab.php';
                break;
        }
        ?>
    </div>
</div>
