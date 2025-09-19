<?php

/**
 * PDF Export functionality for Work Permit System with FPDI Template Support
 * File: includes/class-pdf-export.php
 */

if (!defined('ABSPATH')) {
    exit;
}

// Import required libraries
use setasign\Fpdi\Tcpdf\Fpdi;
use TCPDF\TCPDF;

class WPS_PDF_Export {
    
    private $pdf_template_path;
    private $custom_font_path;
    private $custom_font_name;
    private $special_char_font;
    
    public function __construct() {
        add_action('wp_ajax_export_permits_pdf', array($this, 'export_permits_pdf'));
        add_action('wp_ajax_nopriv_export_permits_pdf', array($this, 'export_permits_pdf'));
        
        add_action('wp_ajax_download_permits_pdf', [$this, 'download_permits_pdf']);
        add_action('wp_ajax_nopriv_download_permits_pdf', [$this, 'download_permits_pdf']);

        // NEW: Add AJAX handler for single permit export by ID
        add_action('wp_ajax_wps_export_permit_by_id_pdf', array($this, 'export_permit_by_id_pdf'));
        add_action('wp_ajax_nopriv_wps_export_permit_by_id_pdf', array($this, 'export_permit_by_id_pdf'));
    
        add_shortcode('permits_pdf_export', array($this, 'render_export_button'));
        
        // Set PDF template path
        $this->pdf_template_path = WPS_PLUGIN_PATH . 'assets/permits/Work-Permit.pdf';
        
        // Set custom font path and name
        $this->custom_font_path = WPS_PLUGIN_PATH . 'assets/fonts/WorkSans-Regular.ttf';
        $this->custom_font_name = 'WorkSans-Regular'; // This will be the font name used in PDF
        $this->special_char_font = 'dejavusans';
    }

    /**
     * Add custom font to PDF instance - FPDI Compatible Version
     */
    private function add_custom_font($pdf) {
        if (!file_exists($this->custom_font_path)) {
            error_log('WPS Font Loading: Font file not found at ' . $this->custom_font_path);
            $this->custom_font_name = 'helvetica';
            return false;
        }

        try {
            // Method 1: Try using TCPDF_FONTS static method (more reliable with FPDI)
            if (class_exists('TCPDF_FONTS')) {
                $font_name = TCPDF_FONTS::addTTFfont($this->custom_font_path, 'TrueTypeUnicode');
                
                if ($font_name && !empty($font_name)) {
                    $this->custom_font_name = $font_name;
                    return true;
                }
            }
            
            // Method 2: Try the instance method as fallback
            if (method_exists($pdf, 'addTTFfont')) {
                $font_name = $pdf->addTTFfont(
                    $this->custom_font_path,    // font file path
                    'TrueTypeUnicode',          // font type
                    '',                         // encoding (empty for Unicode)
                    96,                         // flags
                    array(),                    // outdir (empty array for default)
                    array(),                    // platid (empty array for default)  
                    array(),                    // encid (empty array for default)
                    array()                     // cidinfo (empty array for default)
                );
                
                if ($font_name && !empty($font_name)) {
                    $this->custom_font_name = $font_name;
                    return true;
                }
            }
            
        } catch (Exception $e) {
            error_log('WPS Font Loading Exception: ' . $e->getMessage());
        } catch (Error $e) {
            error_log('WPS Font Loading Fatal Error: ' . $e->getMessage());
        }
        
        // Fallback to system font
        $this->custom_font_name = 'helvetica';
        return false;
    }

    /**
     * Pre-load font before creating PDF instance
     */
    private function preload_custom_font() {
        if (!file_exists($this->custom_font_path)) {
            return false;
        }
        
        try {
            if (class_exists('TCPDF_FONTS')) {
                $font_name = TCPDF_FONTS::addTTFfont($this->custom_font_path, 'TrueTypeUnicode');
                
                if ($font_name && !empty($font_name)) {
                    $this->custom_font_name = $font_name;
                    return true;
                }
            }
        } catch (Exception $e) {
            error_log('WPS Font Preload Error: ' . $e->getMessage());
        } catch (Error $e) {
            error_log('WPS Font Preload Fatal Error: ' . $e->getMessage());
        }
        
        return false;
    }

    public function render_export_button($atts) {
        $atts = shortcode_atts(array(
            'email' => ''
        ), $atts);

        ob_start();
        $this->load_template('pdf-export-button.php', array('email' => $atts['email']));
        return ob_get_clean();
    }
    
    /**
     * Load template file
     */
    private function load_template($template_name, $vars = array())
    {
        $template_path = WPS_PLUGIN_PATH . 'templates/pdf-exports/' . $template_name;

        if (file_exists($template_path)) {
            extract($vars);
            include $template_path;
        } else {
            echo '<p>' . __('Template not found: ', 'work-permit-system') . $template_name . '</p>';
        }
    }
    
    /**
     *  Download Manager for PDF 
     */
    public function download_permits_pdf() {
        
        if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'download_pdf_nonce')) {
            error_log('Download nonce verification failed');
            wp_die(__('Security check failed', 'work-permit-system'));
        }
        
        if (!isset($_GET['file'])) {
            error_log('No file parameter provided');
            wp_die(__('File parameter missing', 'work-permit-system'));
        }
        
        $filename = sanitize_file_name($_GET['file']);
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['path'] . '/' . $filename;
        
        if (!file_exists($file_path)) {
            error_log('File not found: ' . $file_path);
            wp_die(__('File not found', 'work-permit-system'));
        }
        
        // Clear any previous output
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // NEW: Check if this should be inline viewing (for preview)
        $inline_mode = isset($_GET['inline']) && $_GET['inline'] == '1';
        
        // Set headers based on viewing mode
        header('Content-Type: application/pdf');
        
        if ($inline_mode) {
            // For inline viewing (preview) - opens in browser
            header('Content-Disposition: inline; filename="' . $filename . '"');
        } else {
            // For download - forces download
            header('Content-Disposition: attachment; filename="' . $filename . '"');
        }
        
        header('Content-Length: ' . filesize($file_path));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        // Output file
        readfile($file_path);
        
        // Clean up temporary file (but only for downloads, not previews)
        if (!$inline_mode) {
            unlink($file_path);
        } else {
            error_log('File served for preview, not cleaning up yet');
        }
        
        exit;
    }

    /**
     * Generate PDF using FPDI with PDF template - Updated Version
     */
    private function generate_pdf_from_template($data)
    {
        if (!file_exists($this->pdf_template_path)) {
            throw new Exception(__('PDF template not found at: ', 'work-permit-system') . $this->pdf_template_path);
        }

        if (!class_exists('setasign\Fpdi\Tcpdf\Fpdi')) {
            throw new Exception(__('FPDI library not available. Please ensure setasign/fpdi is installed via Composer.', 'work-permit-system'));
        }

        // Try to preload the font before creating PDF instance
        $font_preloaded = $this->preload_custom_font();

        // Create new FPDI instance
        $pdf = new Fpdi();

        // Configure PDF
        $this->configure_fpdi_pdf($pdf, $data);

        // If font wasn't preloaded, try loading it on the instance
        if (!$font_preloaded) {
            $font_loaded = $this->add_custom_font($pdf);
            if (!$font_loaded) {
                error_log('WPS: Using fallback font ' . $this->custom_font_name);
            }
        }

        // Process permits
        $permits = $data['permits'];
        
        foreach ($permits as $permit) {
            try {
                // Import the PDF template
                $pageCount = $pdf->setSourceFile($this->pdf_template_path);
                
                // Use first page of template (modify if you have multi-page templates)
                $templateId = $pdf->importPage(1);
                
                // Add a page with same size as template
                $size = $pdf->getTemplateSize($templateId);
                $pdf->AddPage($size['orientation'], array($size['width'], $size['height']));
                
                // Use the imported page as template
                $pdf->useTemplate($templateId);
                
                // Now add dynamic content on top of the template
                $this->add_permit_data_to_pdf($pdf, $permit);
                
            } catch (Exception $e) {
                error_log('WPS Template Processing Error: ' . $e->getMessage());
                throw new Exception(__('Error processing PDF template: ', 'work-permit-system') . $e->getMessage());
            }
        }

        return $pdf->Output('', 'S');
    }

    /**
     * Configure FPDI PDF document settings
     */
    private function configure_fpdi_pdf($pdf, $data)
    {
        // Set document information
        $pdf->SetCreator('Work Permit System');
        $pdf->SetAuthor(get_bloginfo('name'));
        $pdf->SetTitle(__('Work Permits Export', 'work-permit-system'));
        $pdf->SetSubject(__('User Work Permits Data', 'work-permit-system'));
        $pdf->SetKeywords('Work Permit, ' . $data['permit_info']['name']);

        // Disable header and footer for template-based PDF
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Set margins to zero since we're using a template
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false);
    }

    /**
     * DEVELOPMENT ONLY - Add coordinate grid to help position elements
     * Remove this method after finding coordinates
     */
    private function add_coordinate_grid($pdf)
    {
        // Only run in development
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $pdf->SetFont($this->custom_font_name, '', 6);
        $pdf->SetTextColor(150, 150, 150);

        // Add vertical grid lines every 1mm with coordinate numbers every 5mm
        for ($x = 0; $x <= 210; $x += 1) {
            // Draw vertical line
            $pdf->Line($x, 0, $x, 297, array('width' => 0.05, 'color' => array(200, 200, 200)));
            
            // Add X coordinate number every 5mm to avoid clutter
            if ($x > 0 && $x % 5 == 0) {
                $pdf->SetXY($x - 2, 2);
                $pdf->Cell(4, 0, $x, 0, 0, 'C');
            }
        }

        // Add horizontal grid lines every 1mm with coordinate numbers every 5mm
        for ($y = 0; $y <= 297; $y += 1) {
            // Draw horizontal line
            $pdf->Line(0, $y, 210, $y, array('width' => 0.05, 'color' => array(200, 200, 200)));
            
            // Add Y coordinate number every 5mm to avoid clutter
            if ($y > 5 && $y % 5 == 0) {
                $pdf->SetXY(1, $y - 1);
                $pdf->Cell(8, 0, $y, 0, 0, 'L');
            }
        }

        // Add thicker lines every 10mm for major grid references
        for ($x = 0; $x <= 210; $x += 10) {
            $pdf->Line($x, 0, $x, 297, array('width' => 0.2, 'color' => array(150, 150, 150)));
        }
        
        for ($y = 0; $y <= 297; $y += 10) {
            $pdf->Line(0, $y, 210, $y, array('width' => 0.2, 'color' => array(150, 150, 150)));
        }

        // Add some sample reference points in red
        $pdf->SetTextColor(255, 0, 0);
        $pdf->SetFont($this->custom_font_name, '', 8);
        
        // Sample positions to help you orient
        $pdf->SetXY(50, 30);
        $pdf->Cell(0, 0, '(50, 30)', 1, 0, 'L');
        
        $pdf->SetXY(100, 60);
        $pdf->Cell(0, 0, '(100, 60)', 1, 0, 'L');
        
        $pdf->SetXY(150, 90);
        $pdf->Cell(0, 0, '(150, 90)', 1, 0, 'L');

        // Reset colors for normal content
        $pdf->SetTextColor(0, 0, 0);
    }

    /**
     * Get work category positions dynamically from database
     * This replaces your hard-coded $wd_positions array
     */
    private function get_work_category_positions() {
        // Get all categories from database
        $categories = WPS_Database::get_all_categories(true); // true = active only
        
        // Define position grid - adjust coordinates to match your PDF template
        $position_grid = [
            // Row 1
            [42.6, 59.8],   // renovation work
            [82.4, 59.8],   //  electrical works
            [121.1, 59.8],  // communication (isp, telco, pos)

            // row 2
            [42.6, 65.6],  // maintenance, repairs and budings
            [126.1, 65.6],   // maintenance and repairs (ahu)

            // row 3
            [42.6, 70.5], // delivery construction
            [92.8, 70.5],  // delivery (merchandise)
            [143.2, 70.5],  // pullout
            [167.1, 70.5],   // welding

            // row 4
            [42.6, 75.5], // painting
            [67.8, 75.5], // plumbing
            [94.6, 75.5], // sprinkler
            [121.8, 75.5], // pest control
        
        ];
        
        $category_positions = [];
        
        foreach ($categories as $index => $category) {
            // Skip if we run out of positions (shouldn't happen with proper template design)
            if ($index >= count($position_grid)) {
                error_log('WPS PDF: More categories than available positions on PDF template');
                break;
            }
            
            $category_positions[$category->category_name] = $position_grid[$index];
        }
        
        return $category_positions;
    }

    /**
     * Alternative method: Get positions based on sort_order from database
     */
    private function get_work_category_positions_by_sort_order() {
        $categories = WPS_Database::get_all_categories(true);
        
        // Define your template grid layout
        $grid_config = [
            'columns' => 4,  // 4 categories per row
            'start_x' => 39,  // Starting X coordinate
            'start_y' => 31.5, // Starting Y coordinate  
            'column_width' => 39, // Space between columns
            'row_height' => 4,    // Space between rows
        ];
        
        $category_positions = [];
        
        foreach ($categories as $index => $category) {
            // Calculate position based on index
            $row = floor($index / $grid_config['columns']);
            $col = $index % $grid_config['columns'];
            
            $x = $grid_config['start_x'] + ($col * $grid_config['column_width']);
            $y = $grid_config['start_y'] + ($row * $grid_config['row_height']);
            
            $category_positions[$category->category_name] = [$x, $y];
        }
        
        return $category_positions;
    }

    /**
     * Updated method to use dynamic categories in your PDF with borders and text
     * Replace your existing work description section with this
     */
    private function add_work_categories_to_pdf($pdf, $permit) {
        // Get dynamic category positions
        $wd_positions = $this->get_work_category_positions();
        
        // Use category_name from permit (this is the standard field)
        $selected_category = $permit->category_name ?? '';
        
        if (!empty($selected_category)) {
            // Handle multiple categories if comma-separated
            $selected_categories = array_map('trim', explode(',', $selected_category));

            foreach ($selected_categories as $category) {
                if ($category === '') continue;
                
                // Check if this is the "Others" category (case-insensitive)
                if (strtolower($category) === 'others' || strtolower($category) === 'other') {
                    // Handle "Others" category with custom specification
                    $others_x = 42.6;  // Adjust X coordinate to match your template
                    $others_y = 80.3; // Adjust Y coordinate to match your template
                    
                    // Add checkmark for Others
                    $pdf->SetXY($others_x, $others_y);
                    $pdf->SetFont($this->special_char_font, '', 10);
                    $pdf->SetTextColor(0, 0, 0); // Black checkmark
                    $pdf->Cell(0, 0, '✓', 0, 0, 'L');
                    $pdf->SetTextColor(0, 0, 0);
                    
                    // Add the specification text if provided
                    if (!empty($permit->other_specification)) {
                        $pdf->SetXY($others_x + 17, $others_y + 0.4); // Position after the checkbox
                        $pdf->SetFont($this->custom_font_name, '', 9.5);
                        $pdf->Cell(0, 0, ' ' . $permit->other_specification, 0, 0, 'L');
                    }
                    
                } else {
                    // FIXED: Handle regular categories with flexible matching
                    $matched_position = null;
                    
                    // Try exact match first
                    if (isset($wd_positions[$category])) {
                        $matched_position = $wd_positions[$category];
                    } else {
                        // If exact match fails, try fuzzy matching
                        foreach ($wd_positions as $template_category => $position) {
                            // Method 1: Case-insensitive comparison
                            if (strcasecmp($category, $template_category) === 0) {
                                $matched_position = $position;
                                break;
                            }
                            
                            // Method 2: Remove special characters and compare
                            $clean_selected = preg_replace('/[^a-zA-Z0-9\s]/', '', strtolower($category));
                            $clean_template = preg_replace('/[^a-zA-Z0-9\s]/', '', strtolower($template_category));
                            
                            if ($clean_selected === $clean_template) {
                                $matched_position = $position;
                                break;
                            }
                            
                            // Method 3: Check if category is contained within template category
                            if (stripos($template_category, $category) !== false) {
                                $matched_position = $position;
                                break;
                            }
                            
                            // Method 4: Check if template category is contained within selected category
                            if (stripos($category, $template_category) !== false) {
                                $matched_position = $position;
                                break;
                            }
                            
                            // Method 5: Split by common separators and check partial matches
                            $category_parts = preg_split('/[,\(\)\-\s]+/', strtolower($category), -1, PREG_SPLIT_NO_EMPTY);
                            $template_parts = preg_split('/[,\(\)\-\s]+/', strtolower($template_category), -1, PREG_SPLIT_NO_EMPTY);
                            
                            // Check if any significant part matches (skip very short words)
                            foreach ($category_parts as $cat_part) {
                                if (strlen($cat_part) > 2) { // Skip very short words
                                    foreach ($template_parts as $temp_part) {
                                        if (strlen($temp_part) > 2 && $cat_part === $temp_part) {
                                            $matched_position = $position;
                                            break 3; // Break out of all loops
                                        }
                                    }
                                }
                            }
                        }
                    }
                    
                    // Add checkmark if position was found
                    if ($matched_position !== null) {
                        [$x, $y] = $matched_position;
                        
                        // Add checkmark inside the box
                        $pdf->SetXY($x, $y);
                        $pdf->SetFont($this->special_char_font, '', 10);
                        $pdf->SetTextColor(0, 0, 0); // Black checkmark
                        $pdf->Cell(0, 0, '✓', 0, 0, 'L');
                        $pdf->SetTextColor(0, 0, 0);
                    } else {
                        // Log unmatched categories for debugging
                        error_log('WPS PDF: Could not match category: "' . $category . '"');
                        error_log('WPS PDF: Available categories: ' . implode(', ', array_keys($wd_positions)));
                    }
                }
            }
        }
    }

    /**
     * SIMPLE VERSION: Just show checkmarks with position numbers
     */
    private function debug_simple_checkmarks($pdf) {
        $positions = [
            [42.6, 69], [82.5, 69], [121.4, 69],
            [42.6, 75.3], [126.2, 75.3],
            [42.6, 81.2], [92.9, 81.2], [143.4, 81.2], [167, 81.2],
            [42.6, 87.2], [67.2, 87.2], [94, 87.2], [121, 87.2],
            [42.6, 93.4] // Others
        ];
        
        foreach ($positions as $index => $pos) {
            [$x, $y] = $pos;
            
            $pdf->SetXY($x, $y);
            $pdf->SetFont($this->special_char_font, '', 10);
            $pdf->SetTextColor(255, 0, 0); // Red for visibility
            $pdf->Cell(0, 0, '✓', 0, 0, 'L');
            
            // Position number
            $pdf->SetXY($x + 3, $y);
            $pdf->SetFont($this->custom_font_name, '', 8);
            $pdf->SetTextColor(0, 0, 255);
            $pdf->Cell(0, 0, ($index + 1), 0, 0, 'L');
        }
        
        $pdf->SetTextColor(0, 0, 0);
    }

    /**
     * handle bad date data
     */
    private function safe_format_date($date_value) {
        if (empty($date_value) || $date_value === '0000-00-00') {
            return '';
        }
        
        $timestamp = strtotime($date_value);
        if ($timestamp === false) {
            return '';
        }
        
        return date_i18n('M d, Y', $timestamp);
    }

    /**
     * Add permit data to PDF template - Updated with better font handling
     * CUSTOMIZE THESE COORDINATES TO MATCH YOUR PDF TEMPLATE LAYOUT
     */
    private function add_permit_data_to_pdf($pdf, $permit)
    {
        // TEMPORARY - Add coordinate grid (remove after positioning is done)
        // $this->add_coordinate_grid($pdf);

        // Test if the custom font is working by trying to set it
        try {
            $pdf->SetFont($this->custom_font_name, '', 9.5);
        } catch (Exception $e) {
            error_log('WPS Font Set Error: ' . $e->getMessage() . ' - falling back to helvetica');
            $this->custom_font_name = 'helvetica';
            $pdf->SetFont($this->custom_font_name, '', 9.5);
        }

        $pdf->SetTextColor(0, 0, 0);

        /*
         * CUSTOMIZE COORDINATES BELOW TO MATCH YOUR PDF TEMPLATE
         * Format: SetXY(x_coordinate, y_coordinate)
         * Coordinates are in millimeters from top-left corner (0,0)
         */

        $fields = [
            // permit id
            ['value' => $permit->permit_id ?? '', 'x' => 163, 'y' => 23.8, 'bold' => true],

            // date_issued (formatted date)
            ['value' => $this->safe_format_date($permit->date_issued), 'x' => 173, 'y' => 28.8, 'bold' => true],
            
            // email_address
            ['value' => $permit->email_address ?? '', 'x' => 39, 'y' => 49.9],
            
            // phone_number
            ['value' => $permit->phone_number ?? '', 'x' => 143, 'y' => 49.9],
            
            // issued_to
            ['value' => $permit->issued_to ?? '', 'x' => 30, 'y' => 44.7],
            
            // tenant
            ['value' => $permit->tenant ?? '', 'x' => 126, 'y' => 44.7],
            
            // work_area
            ['value' => $permit->work_area ?? '', 'x' => 63, 'y' => 87],
            
            // requested dates/times
            ['value' => $permit->requested_start_date ?? '', 'x' => 45, 'y' => 204.4],
            ['value' => $permit->requested_end_date ?? '', 'x' => 80, 'y' => 204.4],
            ['value' => wps_format_time($permit->requested_start_time ?? ''), 'x' => 45, 'y' => 209.9],
            ['value' => wps_format_time($permit->requested_end_time ?? ''), 'x' => 80, 'y' => 209.9],
        
            // approved dates/times
            ['value' => $permit->requested_start_date ?? '', 'x' => 145, 'y' => 204.4],
            ['value' => $permit->requested_end_date ?? '', 'x' => 180, 'y' => 204.4],
            ['value' => wps_format_time($permit->requested_start_time ?? ''), 'x' => 145, 'y' => 209.9],
            ['value' => wps_format_time($permit->requested_end_time ?? ''), 'x' => 180, 'y' => 209.9],
        
            // reviewer_name
            ['value' => $permit->reviewer_name ?? '', 'x' => 40, 'y' => 228],
        
            // approver_name
            ['value' => $permit->approver_name ?? '', 'x'=> 140, 'y' => 228],

            // requester_position
            ['value' => $permit->requester_position ?? '', 'x' => 128, 'y' => 94],
            // requested by
            ['value' => $permit->tenant ?? '', 'x' => 36, 'y' => 94],

        ];
        
        foreach ($fields as $field) {
            if (!empty($field['value'])) {
                // Check if the field should be bold
                $fontStyle = isset($field['bold']) && $field['bold'] ? 'B' : '';
                $pdf->SetFont($this->custom_font_name, $fontStyle, 9.5);
                $pdf->SetXY($field['x'], $field['y']);
                $pdf->Cell(0, 0, $field['value'], 0, 0, 'L');
            }
        }

        // temporary
        // $position_grid_temp = [

        //     "Renovation Work" => [42.6, 59.8],   // renovation work
        //     "Electrical Works" => [82.4, 59.8],   //  electrical works
        //     "Communication (ISP, Telco, POS)" => [121.1, 59.8],  // communication (isp, telco, pos)

        //     // row 2
        //     "Maintenance and Repairs (Building Admin)" => [42.6, 65.6],  // maintenance, repairs and budings
        //     "Maintenance and Repairs (AHU)" => [126.1, 65.6],   // maintenance and repairs (ahu)

        //     // row 3
        //     "Delivery (Construction)" => [42.6, 70.5], // delivery construction
        //     "Delivery (Merchandise)" => [92.8, 70.5],  // delivery (merchandise)
        //     "Pullout" => [143.2, 70.5],  // pullout
        //     "Welding" => [167.1, 70.5],   // welding

        //     // row 4
        //     "Painting" => [42.6, 75.5], // painting
        //     "Plumbing" => [67.8, 75.5], // plumbing
        //     "Sprinkler" => [94.6, 75.5], // sprinkler
        //     "Pest Control" => [121.8, 75.5], // pest control

        //     "Other" => [42.6, 80.3],
        // ];

        // foreach ($position_grid_temp as $ctgs => $position) {
        //     [$x, $y] = $position;
        //     $pdf->SetXY($x, $y);
        //     $pdf->SetFont($this->special_char_font, '', 10);
        //     $pdf->SetTextColor(0, 0, 0); // Black checkmark
        //     $pdf->Cell(0, 0, '✓', 0, 0, 'L');
        // }
        
        // Requestor Type - adjust coordinates to match your template
        $rt_positions = [
            "In-house Crew/Contractor"    => [33.3, 54.7],
            "Tenant's Personnel"          => [88.2, 54.7],
            "Tenant's Contractor/Supplier"=> [130.1, 54.7],
        ];

        // Parse selected requestor types
        $selected_requestor_types = array();
        if (!empty($permit->requestor_type)) {
            $selected_requestor_types = array_map('trim', explode(',', $permit->requestor_type));
        }

        // Loop through ALL requestor type options
        foreach ($rt_positions as $requestor_type => $position) {
            [$x, $y] = $position;
            
            // Check if this requestor type is selected
            $is_selected = in_array($requestor_type, $selected_requestor_types);
            
            // Add checkmark ONLY if selected
            if ($is_selected) {
                $pdf->SetXY($x, $y);
                $pdf->SetFont($this->special_char_font, '', 10);
                $pdf->SetTextColor(0, 0, 0); // Black checkmark
                $pdf->Cell(0, 0, '✓', 0, 0, 'L');
            }
        }

        // Work Description (Issued For) - using MultiCell for longer text
        $this->add_work_categories_to_pdf($pdf, $permit);
        // $this->debug_simple_checkmarks($pdf);
        
        // Personnel List - using MultiCell for multiple names
        $pl_positions = [
            "1"=> [14, 139.43],
            "2"=> [14, 144.4],
            "3"=> [14, 149.6],
            "4"=> [115, 139.3],
            "5"=> [115, 144.4],
            "6"=> [115, 149.6],
        ];
        
        if (!empty($permit->personnel_list)) {
            // Split by comma and remove extra spaces
            $personnel_list_array = array_map('trim', explode(',', $permit->personnel_list));
        
            foreach ($personnel_list_array as $index => $val) {
                if ($val === '') continue; // Skip empty values
        
                // Positions start from 1, so add +1 to index
                $posKey = (string)($index + 1);
        
                if (isset($pl_positions[$posKey])) {
                    [$x, $y] = $pl_positions[$posKey];
                    $pdf->SetXY($x, $y);
                    $pdf->SetFont($this->custom_font_name, '', 9.5); 
                    $pdf->Cell(0, 0, $val, 0, 0, 'L');
                }
            }
        }

        // Add signatures as images
        $this->add_signatures_to_pdf($pdf, $permit);

        // Admin Notes/Comments (if any)
        // Get combined comments in one line
        $reviewer = trim($permit->latest_reviewer_comment ?? '');
        $approver = !empty($permit->approver_comments) ? trim($permit->approver_comments[0]->comment ?? '') : '';

        $combined_comments = 
            (!empty($reviewer) && !empty($approver)) ? "Reviewer: $reviewer\n\nApprover: $approver" :
            (!empty($reviewer) ? "Reviewer: $reviewer" :
            (!empty($approver) ? "Approver: $approver" : ''));
        
        if (!empty($combined_comments)) {
            $pdf->SetFont($this->custom_font_name, '', 10);
            $pdf->SetXY(10, 170);
            $pdf->MultiCell(195, 4, $combined_comments, 0, 'L');
        }

        // Tenant Field (if any)
        if (!empty($permit->tenant_field)) {
            $pdf->SetFont($this->custom_font_name, '', 10);
            $pdf->SetXY(10, 105);
            $pdf->MultiCell(195, 4, 'Tenant Notes: ' . $permit->tenant_field, 0, 'L');
        }
    }

    /**
     * Get approver signature path based on user ID
     * Maps WordPress user to their signature file
     */
    private function get_approver_signature_path($user_id) {
        $user = get_user_by('ID', $user_id);
        
        if (!$user) {
            error_log('WPS: User not found for ID: ' . $user_id);
            return false;
        }
        
        // Map usernames to signature files
        $signature_mapping = array(
            'full_stack' => 'full_stack-signature.png',
            'joecyn_marba' => 'joecyn_marba-signature.png', 
            'ralph_gamboa' => 'ralph_gamboa-signature.png',
            'sim_lomongo' => 'sim_lomongo-signature.png'
        );
        
        $username = $user->user_login;
        
        if (isset($signature_mapping[$username])) {
            $signature_filename = $signature_mapping[$username];
            $signature_path = WPS_PLUGIN_PATH . 'assets/signatures/approver/' . $signature_filename;
            
            return $signature_path;
        }
        
        // Try common extensions if exact mapping not found
        $possible_extensions = array('.png', '.jpg', '.jpeg', '.gif');
        foreach ($possible_extensions as $ext) {
            $signature_path = WPS_PLUGIN_PATH . 'assets/signatures/approver/' . $username . '-signature' . $ext;
            if (file_exists($signature_path)) {
                return $signature_path;
            }
        }
        
        error_log('WPS: No signature found for approver: ' . $username);
        return false;
    }

    /**
     * Add signature images to PDF template
     * CUSTOMIZE COORDINATES TO MATCH YOUR TEMPLATE SIGNATURE 
     * Updated to handle approver signatures based on username
     */
    private function add_signatures_to_pdf($pdf, $permit)
    {
        $signature_width = 20;
        $signature_height = 0;

        // NEW: Approver signature based on approved_by user
        if (!empty($permit->approved_by) && $permit->status === 'approved') {
            $approver_signature_path = $this->get_approver_signature_path($permit->approved_by);
            
            if ($approver_signature_path && file_exists($approver_signature_path)) {
                
                $pdf->Image(
                    $approver_signature_path, 
                    140, 223,  // Adjust coordinates to match your template
                    $signature_width, $signature_height, 
                    '', '', '', false, 300, '', false, false, 0
                );
            } else {
                error_log('WPS: Approver signature not found for user ID: ' . $permit->approved_by);
            }
        }
        
        // FALLBACK: If approved_signatory field exists (legacy support)
        if (!empty($permit->approved_signatory)) {
            $approver_sig_path = WPS_PLUGIN_PATH . 'assets/signatures/approver/' . $permit->approved_signatory;
            if (file_exists($approver_sig_path)) {
                $pdf->Image(
                    $approver_sig_path, 
                    140, 223, 
                    $signature_width, $signature_height, 
                    '', '', '', false, 300, '', false, false, 0
                );
            }
        }
    }
    
    /**
     * Alternative template selection based on permit status
     * Use this if you want different templates for different statuses
     */
    private function get_template_for_permit($permit)
    {
        $status_templates = array(
            'approved' => WPS_PLUGIN_PATH . 'assets/permits/Work-Permit-Approved.pdf',
            'pending' => WPS_PLUGIN_PATH . 'assets/permits/Work-Permit-Pending.pdf',
            'cancelled' => WPS_PLUGIN_PATH . 'assets/permits/Work-Permit-Cancelled.pdf'
        );

        $status = strtolower($permit->status);
        
        if (isset($status_templates[$status]) && file_exists($status_templates[$status])) {
            return $status_templates[$status];
        }
        
        return $this->pdf_template_path; // Default template
    }

    private function set_pdf_headers($email_address) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="work-permits-' . sanitize_file_name($email_address) . '-' . date('Y-m-d') . '.pdf"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
    }

    /**
     * Ajax action for exporting permits to PDF
     */
    public function export_permits_pdf() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wps_nonce')) {
            wp_send_json_error(__('Security check failed', 'work-permit-system'));
        }
        
        $email_address = sanitize_email($_POST['email_address']);
        
        if (!$email_address) {
            wp_send_json_error(__('Invalid email address provided.', 'work-permit-system'));
        }
        
        try {
            $permit_data = $this->get_permit_data($email_address);
            
            // Generate PDF content
            if (file_exists($this->pdf_template_path) && class_exists('setasign\Fpdi\Tcpdf\Fpdi')) {
                $pdf_content = $this->generate_pdf_from_template($permit_data);
            } else {
                if (!class_exists('TCPDF')) {
                    wp_send_json_error(__('PDF library not available. Please ensure TCPDF is installed via Composer.', 'work-permit-system'));
                }
                $pdf_content = $this->generate_pdf_tcpdf($permit_data);
            }
            
            // Save PDF to temporary file
            $upload_dir = wp_upload_dir();
            $filename = 'work_permits_' . sanitize_file_name($email_address) . '_' . time() . '.pdf';
            $file_path = $upload_dir['path'] . '/' . $filename;
            
            if (file_put_contents($file_path, $pdf_content) === false) {
                wp_send_json_error(__('Failed to create PDF file.', 'work-permit-system'));
            }
            
            // Return download URL
            $download_url = add_query_arg([
                'action' => 'download_permits_pdf',
                'file' => $filename,
                'nonce' => wp_create_nonce('download_pdf_nonce')
            ], admin_url('admin-ajax.php'));
            
            wp_send_json_success([
                'download_url' => $download_url,
                'message' => __('PDF generated successfully', 'work-permit-system')
            ]);
            
        } catch (Exception $e) {
            error_log('WPS PDF Export Error: ' . $e->getMessage());
            wp_send_json_error(__('Error generating PDF: ', 'work-permit-system') . $e->getMessage());
        }
    }
    
    /**
     * Ajax action for exporting single permit to pdf by ID with preview support
     */
    public function export_permit_by_id_pdf() {
        // Verify nonce - accept multiple nonce types for different user roles
        $nonce_valid = wp_verify_nonce($_POST['nonce'], 'wps_nonce') ||
                      wp_verify_nonce($_POST['nonce'], 'wps_admin_nonce') ||
                      wp_verify_nonce($_POST['nonce'], 'wps_user_action');
        
        if (!$nonce_valid) {
            wp_send_json_error(__('Security check failed', 'work-permit-system'));
        }
        
        $permit_id = intval($_POST['permit_id']);
        $preview_mode = sanitize_text_field($_POST['preview_mode'] ?? '');
        
        if (!$permit_id) {
            wp_send_json_error(__('Invalid permit ID provided.', 'work-permit-system'));
        }
        
        try {
            $permit_data = $this->get_permit_data_by_id($permit_id);
            
            // Generate PDF content
            if (file_exists($this->pdf_template_path) && class_exists('setasign\Fpdi\Tcpdf\Fpdi')) {
                $pdf_content = $this->generate_pdf_from_template($permit_data);
            } else {
                if (!class_exists('TCPDF')) {
                    wp_send_json_error(__('PDF library not available. Please ensure TCPDF is installed via Composer.', 'work-permit-system'));
                }
                $pdf_content = $this->generate_pdf_tcpdf($permit_data);
            }
            
            // Save PDF to temporary file
            $upload_dir = wp_upload_dir();
            $filename = 'work_permit_' . $permit_id . '_' . time() . '.pdf';
            $file_path = $upload_dir['path'] . '/' . $filename;
            
            if (file_put_contents($file_path, $pdf_content) === false) {
                wp_send_json_error(__('Failed to create PDF file.', 'work-permit-system'));
            }
            
            // NEW: Create different URLs based on preview mode
            $base_args = [
                'action' => 'download_permits_pdf',
                'file' => $filename,
                'permit_id' => $permit_id,
                'nonce' => wp_create_nonce('download_pdf_nonce')
            ];
            
            // Add inline parameter for preview mode
            if ($preview_mode === 'inline') {
                $base_args['inline'] = '1';
            }
            
            $download_url = add_query_arg($base_args, admin_url('admin-ajax.php'));
            
            wp_send_json_success([
                'download_url' => $download_url,
                'message' => __('PDF generated successfully', 'work-permit-system'),
                'preview_mode' => $preview_mode
            ]);
            
        } catch (Exception $e) {
            error_log('WPS PDF Export Error: ' . $e->getMessage());
            wp_send_json_error(__('Error generating PDF: ', 'work-permit-system') . $e->getMessage());
        }
    }
    
    /**
     * Get permit data by email address (for multiple permits)
     */
    private function get_permit_data($email_address) {
        $permits = WPS_Database::get_permits_by_email($email_address);
        
        if (empty($permits)) {
            throw new Exception(__('No permits found for this email address', 'work-permit-system'));
        }

        // Add comments data to each permit
        foreach ($permits as &$permit) {
            // Add missing fields that might be needed
            if (!isset($permit->issued_for) && isset($permit->category_name)) {
                $permit->issued_for = $permit->category_name;
            }
            
            // Get comments for this permit
            $comments = WPS_Database::get_permit_comments($permit->id, false);
            $permit->all_comments = $comments;
            
            // Get reviewer comments specifically
            $reviewer_comments = array_filter($comments, function($comment) {
                return $comment->user_type === 'reviewer';
            });
            $permit->reviewer_comments = array_values($reviewer_comments);
            
            // Get approver comments specifically
            $approver_comments = array_filter($comments, function($comment) {
                return $comment->user_type === 'approver';
            });
            $permit->approver_comments = array_values($approver_comments);
            
            // Get latest reviewer comment
            if (!empty($reviewer_comments)) {
                $permit->latest_reviewer_comment = end($reviewer_comments)->comment;
            } else {
                $permit->latest_reviewer_comment = '';
            }
            
            // Get latest approver comment
            if (!empty($approver_comments)) {
                $permit->latest_approver_comment = end($approver_comments)->comment;
            } else {
                $permit->latest_approver_comment = '';
            }
        }

        $permit_info = array(
            'name' => $permits[0]->issued_to,
            'email' => $email_address,
            'total_permits' => count($permits)
        );

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

        return array(
            'permit_info' => $permit_info,
            'permits' => $permits,
            'stats' => $stats
        );
    }
    
    /**
     * Enhanced Get permit data by ID with comments (for single permit)
     * Replace your existing get_permit_data_by_id method with this
     */
    private function get_permit_data_by_id($permit_id) {
        $permit = WPS_Database::get_permit_by_id($permit_id);
        
        if (!$permit) {
            throw new Exception(__('No permit found with this ID', 'work-permit-system'));
        }

        // Add missing fields that might be needed
        if (!isset($permit->issued_for) && isset($permit->category_name)) {
            $permit->issued_for = $permit->category_name;
        }

        // Ensure other_specification is not null
        if (!isset($permit->other_specification)) {
            $permit->other_specification = '';
        }
        
        // CRITICAL: Get additional comments data
        $comments = WPS_Database::get_permit_comments($permit_id, false);
        $permit->all_comments = $comments;
        
        // Get reviewer comments specifically
        $reviewer_comments = array_filter($comments, function($comment) {
            return $comment->user_type === 'reviewer';
        });
        $permit->reviewer_comments = array_values($reviewer_comments);
        
        // Get approver comments specifically
        $approver_comments = array_filter($comments, function($comment) {
            return $comment->user_type === 'approver';
        });
        $permit->approver_comments = array_values($approver_comments);
        
        // Get latest reviewer comment
        if (!empty($reviewer_comments)) {
            $permit->latest_reviewer_comment = end($reviewer_comments)->comment;
        } else {
            $permit->latest_reviewer_comment = '';
        }
        
        // Get latest approver comment
        if (!empty($approver_comments)) {
            $permit->latest_approver_comment = end($approver_comments)->comment;
        } else {
            $permit->latest_approver_comment = '';
        }
        
        $permit_info = array(
            'name' => $permit->issued_to,
            'email' => $permit->email_address,
            'total_permits' => 1
        );

        $stats = array(
            'total' => 1,
            'pending_review' => 0,
            'pending_approval' => 0,
            'approved' => 0,
            'cancelled' => 0
        );

        // Set the status count (fix the key name)
        if (isset($stats[$permit->status])) {
            $stats[$permit->status] = 1;
        }

        return array(
            'permit_info' => $permit_info,
            'permits' => array($permit), // Single permit in array for consistent structure
            'stats' => $stats
        );
    }
    
    /**
     * Generate PDF file by permit ID (for single permit)
     */
    public function generate_pdf_file_by_id($permit_id, $save_path = null) {
        try {
            $permit_data = $this->get_permit_data_by_id($permit_id);

            // Use PDF template with FPDI if available, otherwise fallback to TCPDF
            if (file_exists($this->pdf_template_path) && class_exists('setasign\Fpdi\Tcpdf\Fpdi')) {
                $pdf_content = $this->generate_pdf_from_template($permit_data);
            } else {
                if (!class_exists('TCPDF')) {
                    throw new Exception('TCPDF class not found');
                }
                $pdf_content = $this->generate_pdf_tcpdf($permit_data);
            }

            if (!$save_path) {
                $upload_dir = wp_upload_dir();
                $permit = WPS_Database::get_permit_by_id($permit_id);
                $email_suffix = $permit ? sanitize_file_name($permit->email_address) : 'unknown';
                $save_path = $upload_dir['path'] . '/work-permit-' . $permit_id . '-' . $email_suffix . '-' . date('Y-m-d-H-i-s') . '.pdf';
            }

            if (file_put_contents($save_path, $pdf_content) === false) {
                throw new Exception('Failed to save PDF file');
            }

            return $save_path;
        } catch (Exception $e) {
            error_log('WPS PDF File Generation Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate PDF using TCPDF (fallback method when no template available)
     */
    private function generate_pdf_tcpdf($data) {
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // Add custom font
        $this->add_custom_font($pdf);

        $this->configure_pdf($pdf, $data);
        $pdf->AddPage();
        $pdf->SetFont($this->custom_font_name, '', 12); // Use custom font

        $html = $this->build_pdf_html($data);
        
        // Suppress TCPDF warnings and errors for cleaner output
        $pdf->writeHTML($html, true, false, true, false, '');

        return $pdf->Output('', 'S');
    }
    
    /**
     * Configure PDF document settings (for TCPDF fallback)
     */
    private function configure_pdf($pdf, $data)  {
        $pdf->SetCreator('Work Permit System');
        $pdf->SetAuthor(get_bloginfo('name'));
        $pdf->SetTitle(__('Work Permits Export', 'work-permit-system'));
        $pdf->SetSubject(__('User Work Permits Data', 'work-permit-system'));

        $pdf->SetHeaderData(
            '',
            0,
            __('Work Permits Export', 'work-permit-system'),
            sprintf(
                __('Generated on %s for %s', 'work-permit-system'),
                date_i18n('Y-m-d H:i:s'),
                $data['permit_info']['name']
            )
        );

        $pdf->setHeaderFont(array($this->custom_font_name, '', PDF_FONT_SIZE_MAIN));
        $pdf->setFooterFont(array($this->custom_font_name, '', PDF_FONT_SIZE_DATA));
        $pdf->SetDefaultMonospacedFont($this->custom_font_name);
        $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
        $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
    }

    /**
     * Build HTML content for PDF (fallback method)
     */
    private function build_pdf_html($data) {
        ob_start();
        $this->load_template('pdf-content.php', array('data' => $data));
        return ob_get_clean();
    }
}