<?php
/**
 * Base Signature Handler Class
 * File: includes/class-signature-handler.php
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class WPS_Signature_Handler {
    
    protected $user_type;
    protected $upload_dir;
    
    public function __construct($user_type) {
        $this->user_type = $user_type;
        $this->upload_dir = WPS_PLUGIN_PATH . 'assets/signatures/' . $user_type . '/';
        
        $this->ensure_upload_directory();
    }
    
    /**
     * Ensure upload directory exists with security measures
     */
    protected function ensure_upload_directory() {
        if (!file_exists($this->upload_dir)) {
            wp_mkdir_p($this->upload_dir);
            
            // Create .htaccess for security
            $htaccess_content = "Options -Indexes\n";
            $htaccess_content .= "<Files *.php>\n";
            $htaccess_content .= "Order Deny,Allow\n";
            $htaccess_content .= "Deny from All\n";
            $htaccess_content .= "</Files>\n";
            
            file_put_contents($this->upload_dir . '.htaccess', $htaccess_content);
            file_put_contents($this->upload_dir . 'index.php', '<?php // Silence is golden');
        }
    }
    
    // ===== ABSTRACT METHODS =====
    
    abstract protected function validate_required_fields($data);
    abstract protected function get_user_by_id($user_id);
    abstract protected function insert_user($name, $email_address, $signature_filename);
    abstract protected function update_user($user_id, $name, $email_address, $signature_filename);
    abstract protected function delete_user($user_id);
}