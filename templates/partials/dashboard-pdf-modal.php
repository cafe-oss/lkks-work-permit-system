<?php
/**
 * Dashboard Details Modal Partial
 * File: templates/partials/dashboard-pdf-modal.php
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- PDF Preview Modal -->
<div id="pdf-preview-modal" class="pdf-preview-modal" style="display: none;">
    <div class="pdf-preview-content">
        <div class="pdf-preview-header">
            <h3 class="pdf-preview-title"><?php esc_html_e('Permit Document Preview', 'work-permit-system'); ?></h3>
            <button class="pdf-preview-close" aria-label="<?php esc_attr_e('Close preview', 'work-permit-system'); ?>">&times;</button>
        </div>
        
        <div class="pdf-preview-body">
            <div class="pdf-controls">
                <button id="pdf-prev-page"><?php esc_html_e('Previous', 'work-permit-system'); ?></button>
                <button id="pdf-next-page"><?php esc_html_e('Next', 'work-permit-system'); ?></button>
                <span class="pdf-page-info">
                    <?php esc_html_e('Page', 'work-permit-system'); ?> <span id="pdf-current-page">1</span> <?php esc_html_e('of', 'work-permit-system'); ?> <span id="pdf-total-pages">1</span>
                </span>
                <div class="pdf-zoom-controls">
                    <button id="pdf-zoom-out">-</button>
                    <span id="pdf-zoom-level">100%</span>
                    <button id="pdf-zoom-in">+</button>
                    <button id="pdf-fit-width"><?php esc_html_e('Fit Width', 'work-permit-system'); ?></button>
                </div>
            </div>
            
            <div class="pdf-canvas-container">
                <div id="pdf-loading" class="pdf-loading">
                    <div class="pdf-loading-spinner"></div>
                    <p><?php esc_html_e('Loading PDF...', 'work-permit-system'); ?></p>
                </div>
                <div id="pdf-error" class="pdf-error" style="display: none;">
                    <p><?php esc_html_e('Failed to load PDF', 'work-permit-system'); ?></p>
                    <button onclick="WPS_PdfPreview.retry()"><?php esc_html_e('Try Again', 'work-permit-system'); ?></button>
                </div>
                <canvas id="pdf-canvas" class="pdf-canvas" style="display: none;"></canvas>
            </div>
        </div>
        
        <div class="pdf-footer">
            <span><?php esc_html_e('Use controls above to navigate and zoom', 'work-permit-system'); ?></span>
            <a id="pdf-download-link" href="#" class="pdf-download-btn" target="_blank">
                <?php esc_html_e('Download PDF', 'work-permit-system'); ?>
            </a>
        </div>
    </div>
</div>