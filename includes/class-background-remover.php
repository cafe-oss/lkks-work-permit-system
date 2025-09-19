<?php
/**
 * Signature Background Remover Class
 * File: includes/class-background-remover.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPS_Background_Remover
{
    /**
     * Remove background from signature image
     *
     * @param array $file $_FILES array element
     * @param string $upload_dir Target directory path
     * @param string $prefix Optional filename prefix
     * @return array Result array with success/error status
     */
    public static function process_signature($file, $upload_dir, $prefix = '')
    {
        try {
            $validation = self::validate_upload($file);
            if (!$validation['success']) {
                return $validation;
            }
            
            $temp_file = $file['tmp_name'];
            $original_name = sanitize_file_name($file['name']);
            
            $timestamp = current_time('timestamp');
            $unique_name = $prefix . $timestamp . '_' . wp_generate_password(8, false) . '.png';
            $target_path = trailingslashit($upload_dir) . $unique_name;
            
            // Check if image already has transparency
            $transparency_check = self::has_transparency($temp_file, $file['type']);
            
            if ($transparency_check['has_transparency']) {
                $copy_result = self::copy_transparent_image($temp_file, $target_path, $file['type']);
                
                if (!$copy_result['success']) {
                    return $copy_result;
                }
                
                return array(
                    'success' => true,
                    'filename' => $unique_name,
                    'path' => $target_path,
                    'url' => str_replace(WPS_PLUGIN_PATH, WPS_PLUGIN_URL, $target_path),
                    'original_name' => $original_name,
                    'size' => filesize($target_path),
                    'already_transparent' => true
                );
            }
            
            // Process image to remove background
            $process_result = self::remove_background($temp_file, $target_path, $file['type']);
            
            if (!$process_result['success']) {
                return $process_result;
            }
            
            return array(
                'success' => true,
                'filename' => $unique_name,
                'path' => $target_path,
                'url' => str_replace(WPS_PLUGIN_PATH, WPS_PLUGIN_URL, $target_path),
                'original_name' => $original_name,
                'size' => filesize($target_path)
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => 'Background removal failed: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Check if image already has transparency
     *
     * @param string $source_path Source image path
     * @param string $source_type Source MIME type
     * @return array Result with has_transparency boolean
     */
    private static function has_transparency($source_path, $source_type)
    {
        if ($source_type === 'image/png') {
            $source_image = self::create_image_resource($source_path, $source_type);
            if (!$source_image) {
                return array('has_transparency' => false);
            }
            
            $width = imagesx($source_image);
            $height = imagesy($source_image);
            
            // Check corners and center for transparency
            $sample_points = array(
                array(0, 0),
                array($width-1, 0),
                array(0, $height-1),
                array($width-1, $height-1),
                array(intval($width/2), intval($height/2)),
            );
            
            foreach ($sample_points as $point) {
                $pixel_color = imagecolorat($source_image, $point[0], $point[1]);
                $pixel_rgba = imagecolorsforindex($source_image, $pixel_color);
                
                if (isset($pixel_rgba['alpha']) && $pixel_rgba['alpha'] > 0) {
                    imagedestroy($source_image);
                    return array('has_transparency' => true);
                }
            }
            
            // Check edges for transparency (sample every 5th pixel)
            $transparent_pixels = 0;
            $total_pixels = 0;
            
            // Top and bottom edges
            for ($x = 0; $x < $width; $x += 5) {
                foreach (array(0, $height-1) as $y) {
                    $pixel_color = imagecolorat($source_image, $x, $y);
                    $pixel_rgba = imagecolorsforindex($source_image, $pixel_color);
                    if (isset($pixel_rgba['alpha']) && $pixel_rgba['alpha'] > 0) {
                        $transparent_pixels++;
                    }
                    $total_pixels++;
                }
            }
            
            // Left and right edges
            for ($y = 0; $y < $height; $y += 5) {
                foreach (array(0, $width-1) as $x) {
                    $pixel_color = imagecolorat($source_image, $x, $y);
                    $pixel_rgba = imagecolorsforindex($source_image, $pixel_color);
                    if (isset($pixel_rgba['alpha']) && $pixel_rgba['alpha'] > 0) {
                        $transparent_pixels++;
                    }
                    $total_pixels++;
                }
            }
            
            imagedestroy($source_image);
            
            // If more than 10% of edge pixels are transparent, consider it transparent
            $transparency_ratio = $total_pixels > 0 ? ($transparent_pixels / $total_pixels) : 0;
            return array('has_transparency' => $transparency_ratio > 0.1);
        }
        
        if ($source_type === 'image/gif') {
            $source_image = self::create_image_resource($source_path, $source_type);
            if (!$source_image) {
                return array('has_transparency' => false);
            }
            
            $transparent_color_index = imagecolortransparent($source_image);
            imagedestroy($source_image);
            
            return array('has_transparency' => $transparent_color_index >= 0);
        }
        
        // JPEG doesn't support transparency
        return array('has_transparency' => false);
    }
    
    /**
     * Copy transparent image without processing
     *
     * @param string $source_path Source image path
     * @param string $target_path Target image path
     * @param string $source_type Source MIME type
     * @return array Processing result
     */
    private static function copy_transparent_image($source_path, $target_path, $source_type)
    {
        $source_image = self::create_image_resource($source_path, $source_type);
        if (!$source_image) {
            return array('success' => false, 'error' => 'Cannot create image resource');
        }
        
        $width = imagesx($source_image);
        $height = imagesy($source_image);
        
        $dest_image = imagecreatetruecolor($width, $height);
        if (!$dest_image) {
            imagedestroy($source_image);
            return array('success' => false, 'error' => 'Cannot create destination image');
        }
        
        // Enable transparency
        imagealphablending($dest_image, false);
        imagesavealpha($dest_image, true);
        
        imagecopy($dest_image, $source_image, 0, 0, 0, 0, $width, $height);
        
        $target_dir = dirname($target_path);
        if (!file_exists($target_dir)) {
            wp_mkdir_p($target_dir);
        }
        
        $saved = imagepng($dest_image, $target_path, 6);
        
        imagedestroy($source_image);
        imagedestroy($dest_image);
        
        if (!$saved) {
            return array('success' => false, 'error' => 'Cannot save image');
        }
        
        chmod($target_path, 0644);
        return array('success' => true);
    }
    
    /**
     * Remove background from image
     *
     * @param string $source_path Source image path
     * @param string $target_path Target image path
     * @param string $source_type Source MIME type
     * @return array Processing result
     */
    private static function remove_background($source_path, $target_path, $source_type)
    {
        $image_info = getimagesize($source_path);
        if (!$image_info) {
            return array('success' => false, 'error' => 'Cannot read image dimensions');
        }
        
        $source_image = self::create_image_resource($source_path, $source_type);
        if (!$source_image) {
            return array('success' => false, 'error' => 'Cannot create image resource');
        }
        
        $width = imagesx($source_image);
        $height = imagesy($source_image);
        
        $dest_image = imagecreatetruecolor($width, $height);
        if (!$dest_image) {
            imagedestroy($source_image);
            return array('success' => false, 'error' => 'Cannot create destination image');
        }
        
        // Enable transparency
        imagealphablending($dest_image, false);
        imagesavealpha($dest_image, true);
        
        // Fill with transparent color
        $transparent = imagecolorallocatealpha($dest_image, 0, 0, 0, 127);
        imagefill($dest_image, 0, 0, $transparent);
        
        // Define colors to remove (white and light backgrounds)
        $bg_colors_to_remove = array(
            array('r' => 255, 'g' => 255, 'b' => 255), // White
            array('r' => 254, 'g' => 254, 'b' => 254),
            array('r' => 253, 'g' => 253, 'b' => 253),
            array('r' => 248, 'g' => 248, 'b' => 248), // Light gray
            array('r' => 240, 'g' => 240, 'b' => 240),
        );
        
        // Process each pixel
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $pixel_color = imagecolorat($source_image, $x, $y);
                $pixel_rgb = imagecolorsforindex($source_image, $pixel_color);
                
                $should_remove = false;
                
                // Check if pixel matches background colors to remove
                foreach ($bg_colors_to_remove as $bg) {
                    if (self::color_similarity($pixel_rgb, $bg) < 30) {
                        $should_remove = true;
                        break;
                    }
                }
                
                if ($should_remove) {
                    // Make pixel transparent
                    $transparent_pixel = imagecolorallocatealpha($dest_image, 0, 0, 0, 127);
                    imagesetpixel($dest_image, $x, $y, $transparent_pixel);
                } else {
                    // Keep original pixel
                    $new_color = imagecolorallocate(
                        $dest_image,
                        $pixel_rgb['red'],
                        $pixel_rgb['green'],
                        $pixel_rgb['blue']
                    );
                    imagesetpixel($dest_image, $x, $y, $new_color);
                }
            }
        }
        
        $target_dir = dirname($target_path);
        if (!file_exists($target_dir)) {
            wp_mkdir_p($target_dir);
        }
        
        $saved = imagepng($dest_image, $target_path, 6);
        
        imagedestroy($source_image);
        imagedestroy($dest_image);
        
        if (!$saved) {
            return array('success' => false, 'error' => 'Cannot save processed image');
        }
        
        chmod($target_path, 0644);
        return array('success' => true);
    }
    
    /**
     * Calculate color similarity
     *
     * @param array $color1 First color array
     * @param array $color2 Second color array
     * @return float Similarity value (0 = identical, higher = more different)
     */
    private static function color_similarity($color1, $color2)
    {
        $r1 = isset($color1['red']) ? $color1['red'] : $color1['r'];
        $g1 = isset($color1['green']) ? $color1['green'] : $color1['g'];
        $b1 = isset($color1['blue']) ? $color1['blue'] : $color1['b'];
        
        return sqrt(pow($r1 - $color2['r'], 2) + pow($g1 - $color2['g'], 2) + pow($b1 - $color2['b'], 2));
    }
    
    /**
     * Validate uploaded file
     */
    private static function validate_upload($file)
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return array(
                'success' => false,
                'error' => self::get_upload_error_message($file['error'])
            );
        }
        
        if ($file['size'] > 5 * 1024 * 1024) { // 5MB max
            return array(
                'success' => false,
                'error' => 'File size too large. Maximum allowed: 5MB'
            );
        }
        
        // Validate file type
        $allowed_types = array('image/jpeg', 'image/jpg', 'image/png', 'image/gif');
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detected_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($detected_type, $allowed_types)) {
            return array(
                'success' => false,
                'error' => 'Invalid file type. Only JPG, PNG, and GIF images are allowed.'
            );
        }
        
        // Verify it's actually an image
        if (@getimagesize($file['tmp_name']) === false) {
            return array(
                'success' => false,
                'error' => 'Invalid image file.'
            );
        }
        
        return array('success' => true);
    }
    
    /**
     * Create image resource from file
     */
    private static function create_image_resource($file_path, $mime_type)
    {
        switch ($mime_type) {
            case 'image/jpeg':
            case 'image/jpg':
                return imagecreatefromjpeg($file_path);
            case 'image/png':
                return imagecreatefrompng($file_path);
            case 'image/gif':
                return imagecreatefromgif($file_path);
        }
        
        return false;
    }
    
    /**
     * Get upload error message
     */
    private static function get_upload_error_message($error_code)
    {
        $messages = array(
            UPLOAD_ERR_INI_SIZE => 'File exceeds the maximum allowed size in PHP configuration.',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds the maximum allowed size.',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension.',
        );
        
        return isset($messages[$error_code]) ? $messages[$error_code] : 'Unknown upload error.';
    }
    
    /**
     * Delete old signature file
     */
    public static function delete_old_signature($filename, $directory)
    {
        if (empty($filename)) {
            return true;
        }
        
        $file_path = trailingslashit($directory) . $filename;
        
        if (file_exists($file_path)) {
            return unlink($file_path);
        }
        
        return true;
    }
}