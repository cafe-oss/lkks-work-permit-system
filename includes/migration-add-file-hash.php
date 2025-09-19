<?php
/**
 * Database Migration Script for WPS Document System
 * File: includes/migration-add-file-hash.php
 * Run this once to add the file_hash column and clean up duplicates
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPS_Database_Migration {
    
    /**
     * Run complete migration
     */
    public static function run_migration() {
        
        // Step 1: Add file_hash column
        $column_added = self::add_file_hash_column();
        
        if (!$column_added) {
            error_log('WPS Migration: Failed to add file_hash column, aborting migration');
            return false;
        }
        
        // Step 2: Generate hashes for existing files
        self::generate_hashes_for_existing_files();
        
        // Step 3: Clean up duplicates
        $duplicates_cleaned = self::cleanup_post_migration_duplicates();
        
        // Step 4: Set migration completion flag
        update_option('wps_file_hash_migration_completed', true);
        
        return true;
    }
    
    /**
     * Add file_hash column to documents table
     */
    private static function add_file_hash_column() {
        global $wpdb;
        
        $documents_table = $wpdb->prefix . 'wps_permit_documents';
        
        // Check if column already exists
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $documents_table LIKE 'file_hash'");
        
        if (empty($column_exists)) {
            // Add file_hash column
            $sql = "ALTER TABLE $documents_table ADD COLUMN file_hash VARCHAR(32) DEFAULT NULL AFTER mime_type";
            $result = $wpdb->query($sql);
            
            if ($result === false) {
                error_log('WPS Migration: Failed to add file_hash column: ' . $wpdb->last_error);
                return false;
            }
            
            // Add index for performance
            $wpdb->query("ALTER TABLE $documents_table ADD INDEX idx_file_hash (file_hash)");
            
            return true;
        } else {
            error_log('WPS Migration: file_hash column already exists');
            return true;
        }
    }
    
    /**
     * Generate file hashes for existing documents
     */
    private static function generate_hashes_for_existing_files() {
        global $wpdb;
        
        $documents_table = $wpdb->prefix . 'wps_permit_documents';
        
        // Get all documents without hashes
        $documents = $wpdb->get_results("
            SELECT id, file_path 
            FROM $documents_table 
            WHERE (file_hash IS NULL OR file_hash = '') 
            AND is_active = 1
        ");
        
        $updated_count = 0;
        $missing_count = 0;
        
        foreach ($documents as $document) {
            if (file_exists($document->file_path)) {
                $file_hash = md5_file($document->file_path);
                
                if ($file_hash) {
                    $wpdb->update(
                        $documents_table,
                        array('file_hash' => $file_hash),
                        array('id' => $document->id),
                        array('%s'),
                        array('%d')
                    );
                    $updated_count++;
                }
            } else {
                // Mark missing files as inactive
                $wpdb->update(
                    $documents_table,
                    array('is_active' => 0),
                    array('id' => $document->id),
                    array('%d'),
                    array('%d')
                );
                $missing_count++;
                error_log('WPS Migration: Marked missing file as inactive: ' . $document->file_path);
            }
        }
        
        error_log("WPS Migration: Generated hashes for $updated_count files, marked $missing_count missing files as inactive");
    }
    
    /**
     * Clean up duplicate documents after migration
     */
    private static function cleanup_post_migration_duplicates() {
        global $wpdb;
        
        $documents_table = $wpdb->prefix . 'wps_permit_documents';
        
        // Find duplicates by hash and permit_id
        $duplicates = $wpdb->get_results("
            SELECT file_hash, permit_id, COUNT(*) as count, 
                   GROUP_CONCAT(id ORDER BY upload_date DESC) as ids
            FROM $documents_table 
            WHERE file_hash IS NOT NULL 
            AND file_hash != '' 
            AND is_active = 1
            GROUP BY file_hash, permit_id
            HAVING count > 1
        ");
        
        $removed_count = 0;
        
        foreach ($duplicates as $duplicate_group) {
            $ids = explode(',', $duplicate_group->ids);
            // Keep the first (most recent) one, mark others as inactive
            array_shift($ids); // Remove the first ID to keep it
            
            foreach ($ids as $id_to_remove) {
                $wpdb->update(
                    $documents_table,
                    array('is_active' => 0),
                    array('id' => intval($id_to_remove)),
                    array('%d'),
                    array('%d')
                );
                $removed_count++;
            }
        }
        
        error_log('WPS Migration: Cleaned up ' . $removed_count . ' duplicate documents');
        return $removed_count;
    }
    
    /**
     * Check if migration is needed
     */
    public static function migration_needed() {
        // Check if migration already completed
        if (get_option('wps_file_hash_migration_completed', false)) {
            return false;
        }
        
        global $wpdb;
        $documents_table = $wpdb->prefix . 'wps_permit_documents';
        
        // Check if file_hash column exists
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $documents_table LIKE 'file_hash'");
        
        return empty($column_exists);
    }
    
}