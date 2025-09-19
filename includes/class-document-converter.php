<?php
/**
 * Document Converter Class - Fixed for Windows Permission Issues
 * File: includes/class-document-converter.php
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load Composer autoloader if available
if (file_exists(WPS_PLUGIN_PATH . 'vendor/autoload.php')) {
    require_once WPS_PLUGIN_PATH . 'vendor/autoload.php';
}

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\Shared\Html;
use Dompdf\Dompdf;
use Dompdf\Options;

class WPS_Document_Converter {
    
    private static $temp_dir;
    private static $converted_cache = array();
    
    public function __construct() {
        self::$temp_dir = WPS_PLUGIN_PATH . 'assets/temp/';
        
        // Ensure temp directory exists with proper permissions
        $this->setup_temp_directory();
        
        // Configure PHPWord settings for Windows compatibility
        $this->configure_phpword_settings();
        
        // Register AJAX handlers
        add_action('wp_ajax_wps_view_document_as_pdf', array($this, 'handle_view_document_as_pdf'));
        add_action('wp_ajax_wps_convert_document_to_pdf', array($this, 'handle_convert_document_to_pdf'));
    }
    
    /**
     * Setup temp directory with proper permissions
     */
    private function setup_temp_directory() {
        if (!file_exists(self::$temp_dir)) {
            wp_mkdir_p(self::$temp_dir);
        }
        
        // Ensure directory is writable
        if (!is_writable(self::$temp_dir)) {
            error_log('WPS Document Converter: Temp directory not writable: ' . self::$temp_dir);
        }
        
        // Create .htaccess for security
        $htaccess_file = self::$temp_dir . '.htaccess';
        if (!file_exists($htaccess_file)) {
            file_put_contents($htaccess_file, "Options -Indexes\nDeny from all\n");
        }
        
        // Create index.php for security
        $index_file = self::$temp_dir . 'index.php';
        if (!file_exists($index_file)) {
            file_put_contents($index_file, '<?php // Silence is golden');
        }
    }
    
    /**
     * Configure PHPWord settings for Windows compatibility
     */
    private function configure_phpword_settings() {
        try {
            // Set the temp directory to our plugin's temp directory
            Settings::setTempDir(self::$temp_dir);
            
            // Set output escaping for better HTML compatibility
            Settings::setOutputEscapingEnabled(true);
            
        } catch (Exception $e) {
            error_log('WPS Document Converter: Failed to configure PHPWord settings: ' . $e->getMessage());
        }
    }
    
    /**
     * Check if document conversion is available
     */
    public static function is_conversion_available() {
        $phpword_available = class_exists('PhpOffice\PhpWord\IOFactory');
        $dompdf_available = class_exists('Dompdf\Dompdf');
        $temp_writable = is_writable(self::$temp_dir);
        
        if (!$phpword_available) {
            error_log('WPS Document Converter: PHPWord not available');
        }
        if (!$dompdf_available) {
            error_log('WPS Document Converter: Dompdf not available');
        }
        if (!$temp_writable) {
            error_log('WPS Document Converter: Temp directory not writable');
        }
        
        return $phpword_available && $dompdf_available && $temp_writable;
    }
    
    /**
     * Get supported file types for conversion
     */
    public static function get_convertible_types() {
        return array('docx', 'doc');
    }
    
    /**
     * Check if file type can be converted
     */
    public static function can_convert_file($filename) {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($extension, self::get_convertible_types());
    }
    
    /**
     * Convert DOCX to PDF - Fixed for Windows
     */
    public static function convert_docx_to_pdf($docx_path, $output_path = null) {
        if (!self::is_conversion_available()) {
            throw new Exception('Document conversion libraries not available or temp directory not writable');
        }
        
        if (!file_exists($docx_path)) {
            throw new Exception('Source document not found: ' . $docx_path);
        }
        
        try {
            // Generate output path if not provided
            if (!$output_path) {
                $filename = basename($docx_path, '.docx') . '_' . time() . '.pdf';
                $output_path = self::$temp_dir . $filename;
            }
            
            // Check cache first
            $cache_key = md5($docx_path . filemtime($docx_path));
            if (isset(self::$converted_cache[$cache_key]) && file_exists(self::$converted_cache[$cache_key])) {
                return self::$converted_cache[$cache_key];
            }
            
            // Method 1: Try direct HTML conversion approach (more reliable on Windows)
            try {
                $pdf_path = self::convert_via_html_method($docx_path, $output_path);
                if (file_exists($pdf_path) && filesize($pdf_path) > 0) {
                    // Cache the result
                    self::$converted_cache[$cache_key] = $pdf_path;
                    return $pdf_path;
                }
            } catch (Exception $e) {
                error_log('WPS Document Converter: HTML method failed: ' . $e->getMessage());
            }
            
            // Method 2: Try simplified text extraction approach as fallback
            try {
                $pdf_path = self::convert_via_text_extraction($docx_path, $output_path);
                if (file_exists($pdf_path) && filesize($pdf_path) > 0) {
                    // Cache the result
                    self::$converted_cache[$cache_key] = $pdf_path;
                    return $pdf_path;
                }
            } catch (Exception $e) {
                error_log('WPS Document Converter: Text extraction method failed: ' . $e->getMessage());
            }
            
            throw new Exception('All conversion methods failed');
            
        } catch (Exception $e) {
            error_log('WPS Document Converter: Conversion failed: ' . $e->getMessage());
            throw new Exception('Failed to convert document: ' . $e->getMessage());
        }
    }
    
    /**
     * Convert via HTML method (primary approach)
     */
    private static function convert_via_html_method($docx_path, $output_path) {
        // Load the DOCX file
        $phpWord = IOFactory::load($docx_path);
        
        // Create HTML writer with specific settings
        $htmlWriter = IOFactory::createWriter($phpWord, 'HTML');
        
        // Create unique temporary HTML file in our temp directory
        $temp_html = self::$temp_dir . 'html_' . uniqid() . '.html';
        
        // Save to HTML
        $htmlWriter->save($temp_html);
        
        if (!file_exists($temp_html)) {
            throw new Exception('Failed to create temporary HTML file');
        }
        
        // Read HTML content
        $html_content = file_get_contents($temp_html);
        
        if (empty($html_content)) {
            unlink($temp_html);
            throw new Exception('HTML content is empty');
        }
        
        // Clean up HTML content for better PDF rendering
        $html_content = self::clean_html_for_pdf($html_content);
        
        // Convert HTML to PDF
        $pdf_path = self::html_to_pdf($html_content, $output_path);
        
        // Clean up temporary HTML file
        if (file_exists($temp_html)) {
            unlink($temp_html);
        }
        
        return $pdf_path;
    }
    
    /**
     * Convert via text extraction (fallback method)
     */
    private static function convert_via_text_extraction($docx_path, $output_path) {
        // Load the DOCX file
        $phpWord = IOFactory::load($docx_path);
        
        // Extract plain text content
        $text_content = '';
        
        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getText')) {
                    $text_content .= $element->getText() . "\n\n";
                } elseif (method_exists($element, 'getElements')) {
                    // Handle nested elements like TextRun
                    foreach ($element->getElements() as $textElement) {
                        if (method_exists($textElement, 'getText')) {
                            $text_content .= $textElement->getText();
                        }
                    }
                    $text_content .= "\n\n";
                }
            }
        }
        
        if (empty($text_content)) {
            throw new Exception('No text content extracted from document');
        }
        
        // Create simple HTML from text
        $html_content = self::text_to_html($text_content, basename($docx_path));
        
        // Convert HTML to PDF
        return self::html_to_pdf($html_content, $output_path);
    }
    
    /**
     * Convert text to formatted HTML
     */
    private static function text_to_html($text_content, $title) {
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>' . htmlspecialchars($title) . '</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            font-size: 12pt; 
            line-height: 1.6; 
            margin: 30px;
            color: #333;
        }
        .document-title {
            font-size: 16pt;
            font-weight: bold;
            margin-bottom: 20px;
            text-align: center;
            color: #2c3e50;
        }
        .content {
            text-align: justify;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
    <div class="document-title">' . htmlspecialchars($title) . '</div>
    <div class="content">' . nl2br(htmlspecialchars($text_content)) . '</div>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Convert HTML to PDF using Dompdf
     */
    private static function html_to_pdf($html_content, $output_path) {
        // Configure Dompdf with Windows-friendly settings
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isFontSubsettingEnabled', false); // Disable font subsetting for compatibility
        $options->set('tempDir', self::$temp_dir); // Use our temp directory
        $options->set('fontDir', self::$temp_dir); // Use our temp directory for fonts
        $options->set('fontCache', self::$temp_dir); // Use our temp directory for font cache
        $options->set('chroot', array(self::$temp_dir)); // Restrict file access to our temp directory
        
        // Create Dompdf instance
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html_content);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        // Save PDF
        $pdf_content = $dompdf->output();
        
        if (empty($pdf_content)) {
            throw new Exception('PDF content is empty');
        }
        
        $result = file_put_contents($output_path, $pdf_content);
        
        if ($result === false) {
            throw new Exception('Failed to write PDF file to: ' . $output_path);
        }
        
        return $output_path;
    }
    
    /**
     * Clean HTML content for better PDF rendering
     */
    private static function clean_html_for_pdf($html) {
        // Remove problematic elements
        $html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<link[^>]*>/i', '', $html);
        
        // Replace existing style with our improved CSS
        $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);
        
        // Add our CSS for better formatting
        $css = '<style>
            body { 
                font-family: Arial, sans-serif; 
                font-size: 12pt; 
                line-height: 1.5; 
                margin: 25px;
                color: #333;
                background: white;
            }
            h1, h2, h3, h4, h5, h6 { 
                color: #2c3e50; 
                margin-top: 20px; 
                margin-bottom: 10px; 
                page-break-after: avoid;
            }
            h1 { font-size: 18pt; }
            h2 { font-size: 16pt; }
            h3 { font-size: 14pt; }
            p { 
                margin-bottom: 10px; 
                text-align: justify;
                orphans: 2;
                widows: 2;
            }
            table { 
                width: 100%; 
                border-collapse: collapse; 
                margin: 10px 0; 
                page-break-inside: avoid;
            }
            td, th { 
                border: 1px solid #ddd; 
                padding: 8px; 
                text-align: left; 
                vertical-align: top;
            }
            th { 
                background-color: #f5f5f5; 
                font-weight: bold; 
            }
            img {
                max-width: 100%;
                height: auto;
            }
            .page-break { 
                page-break-before: always; 
            }
            /* Remove any absolute positioning */
            * {
                position: static !important;
            }
        </style>';
        
        // Insert CSS into head or at the beginning
        if (strpos($html, '<head>') !== false) {
            $html = str_replace('<head>', '<head>' . $css, $html);
        } else {
            $html = $css . $html;
        }
        
        // Ensure proper encoding
        if (strpos($html, '<meta charset') === false && strpos($html, '<head>') !== false) {
            $html = str_replace('<head>', '<head><meta charset="UTF-8">', $html);
        }
        
        return $html;
    }
    
    /**
     * AJAX handler for viewing document as PDF
     */
    public function handle_view_document_as_pdf() {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wps_user_action') && 
            !wp_verify_nonce($_POST['nonce'] ?? '', 'wps_admin_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in');
            return;
        }
        
        $document_id = intval($_POST['document_id'] ?? 0);
        if (!$document_id) {
            wp_send_json_error('Invalid document ID');
            return;
        }
        
        try {
            // Get document details
            $document = WPS_Document_Manager::get_document_by_id($document_id);
            if (!$document) {
                wp_send_json_error('Document not found');
                return;
            }
            
            // Check if user has permission to view this document
            if (!$this->user_can_view_document($document)) {
                wp_send_json_error('Permission denied');
                return;
            }
            
            // Check if file exists
            if (!file_exists($document->file_path)) {
                wp_send_json_error('Document file not found');
                return;
            }
            
            $filename = $document->original_filename;
            
            // If it's already a PDF, return direct URL
            if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'pdf') {
                wp_send_json_success(array(
                    'pdf_url' => $document->file_url,
                    'filename' => $filename,
                    'converted' => false
                ));
                return;
            }
            
            // If it's convertible to PDF
            if (self::can_convert_file($filename)) {
                if (!self::is_conversion_available()) {
                    wp_send_json_error('Document conversion not available. Please check server configuration.');
                    return;
                }
                
                // Convert to PDF
                $pdf_path = self::convert_docx_to_pdf($document->file_path);
                
                // Create a temporary URL for the converted PDF
                $pdf_filename = basename($pdf_path);
                $pdf_url = add_query_arg(array(
                    'action' => 'wps_serve_temp_pdf',
                    'file' => $pdf_filename,
                    'nonce' => wp_create_nonce('serve_temp_pdf')
                ), admin_url('admin-ajax.php'));
                
                wp_send_json_success(array(
                    'pdf_url' => $pdf_url,
                    'filename' => pathinfo($filename, PATHINFO_FILENAME) . '.pdf',
                    'converted' => true
                ));
                return;
            }
            
            // For other file types, return original URL
            wp_send_json_success(array(
                'pdf_url' => $document->file_url,
                'filename' => $filename,
                'converted' => false,
                'message' => 'File cannot be converted to PDF'
            ));
            
        } catch (Exception $e) {
            error_log('WPS Document Viewer Error: ' . $e->getMessage());
            wp_send_json_error('Error processing document: ' . $e->getMessage());
        }
    }
    
    /**
     * Check if user can view document
     */
    private function user_can_view_document($document) {
        // Admin can view all documents
        if (current_user_can('manage_options')) {
            return true;
        }
        
        // Get permit details
        $permit = WPS_Database::get_permit_by_id($document->permit_id);
        if (!$permit) {
            return false;
        }
        
        $current_user_id = get_current_user_id();
        
        // Reviewer can view documents for their assigned permits
        if (current_user_can('wps_review_permits') && $permit->reviewer_user_id == $current_user_id) {
            return true;
        }
        
        // Approver can view documents for their assigned permits
        if (current_user_can('wps_approve_permits') && $permit->approver_user_id == $current_user_id) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Serve temporary PDF file
     */
    public static function serve_temp_pdf() {
        if (!wp_verify_nonce($_GET['nonce'] ?? '', 'serve_temp_pdf')) {
            wp_die('Security check failed');
        }
        
        $filename = sanitize_file_name($_GET['file'] ?? '');
        if (empty($filename)) {
            wp_die('Invalid filename');
        }
        
        $file_path = self::$temp_dir . $filename;
        
        if (!file_exists($file_path)) {
            wp_die('File not found');
        }
        
        // Security check - ensure file is in our temp directory
        $real_file_path = realpath($file_path);
        $real_temp_dir = realpath(self::$temp_dir);
        
        if (strpos($real_file_path, $real_temp_dir) !== 0) {
            wp_die('Invalid file path');
        }
        
        // Clear any previous output
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Set headers for PDF viewing
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($file_path));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        // Output file
        readfile($file_path);
        
        // Clean up temporary file after serving
        register_shutdown_function(function() use ($file_path) {
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        });
        
        exit;
    }
    
    /**
     * Clean up old temporary files
     */
    public static function cleanup_temp_files() {
        if (!is_dir(self::$temp_dir)) {
            return;
        }
        
        $files = glob(self::$temp_dir . '*');
        if (!$files) {
            return;
        }
        
        $current_time = time();
        $deleted_count = 0;
        
        foreach ($files as $file) {
            if (is_file($file) && !in_array(basename($file), array('.htaccess', 'index.php'))) {
                // Delete files older than 1 hour
                if ($current_time - filemtime($file) > 3600) {
                    if (unlink($file)) {
                        $deleted_count++;
                    }
                }
            }
        }
        
        if ($deleted_count > 0) {
            error_log("WPS Document Converter: Cleaned up {$deleted_count} temporary files");
        }
    }
    
    /**
     * Get conversion status and debug info
     */
    public static function get_conversion_status() {
        return array(
            'phpword_available' => class_exists('PhpOffice\PhpWord\IOFactory'),
            'dompdf_available' => class_exists('Dompdf\Dompdf'),
            'temp_dir_exists' => file_exists(self::$temp_dir),
            'temp_dir_writable' => is_writable(self::$temp_dir),
            'temp_dir_path' => self::$temp_dir,
            'conversion_available' => self::is_conversion_available()
        );
    }
}

// Initialize the converter
new WPS_Document_Converter();

// Register AJAX handler for serving temporary PDFs
add_action('wp_ajax_wps_serve_temp_pdf', array('WPS_Document_Converter', 'serve_temp_pdf'));
add_action('wp_ajax_nopriv_wps_serve_temp_pdf', array('WPS_Document_Converter', 'serve_temp_pdf'));

// Schedule regular cleanup of temporary files
add_action('wps_cleanup_temp_files', array('WPS_Document_Converter', 'cleanup_temp_files'));