/**
 * Clean Admin JavaScript - Pagination Removed
 * File: assets/js/admin.js
 * 
 * CLEANED: All pagination logic moved to unified-dashboard-filters.js
 * This file now only handles admin-specific utilities and view functions
 */
jQuery(document).ready(function($) {

    // Initialize unified view modal for admin
    if (typeof WPS_UnifiedViewModal !== 'undefined') {
        // Check if we have the unified view variables (from the PHP localization)
        if (typeof wps_unified_view_vars !== 'undefined') {
            WPS_UnifiedViewModal.init({
                userType: wps_unified_view_vars.user_type,
                ajaxUrl: wps_unified_view_vars.ajax_url,
                nonce: wps_unified_view_vars.nonce
            });
        } else {
            // Fallback to admin vars if unified vars not available
            WPS_UnifiedViewModal.init({
                userType: 'admin',
                ajaxUrl: wps_admin_vars.ajax_url,
                nonce: wps_admin_vars.nonce
            });
        }
    } else {
        console.error('WPS_UnifiedViewModal not available');
    }

    /**
     * Admin-specific utility functions only
     */
    function isImageFile(filename) {
        const IMAGE_EXTENSIONS = ['.jpg', '.jpeg', '.png', '.gif', '.webp'];
        return IMAGE_EXTENSIONS.some(ext => filename.toLowerCase().endsWith(ext));
    }

    function isPdfFile(filename) {
        return filename.toLowerCase().endsWith('.pdf');
    }

    function showImagePreview(imageUrl, filename) {
        showFilePreview(imageUrl, filename, 'image-preview', 
            `<img src="${imageUrl}" alt="${filename}" style="max-width: 100%; height: auto;">`);
    }

    function showPdfPreview(pdfUrl, filename) {
        showFilePreview(pdfUrl, filename, 'pdf-preview',
            `<iframe src="${pdfUrl}" width="100%" height="600px" style="border: none;"></iframe>`);
    }

    function showFilePreview(fileUrl, filename, modalType, content) {
        const modalId = `${modalType}-modal`;
        
        const previewHtml = `
            <div id="${modalId}" class="modal-overlay">
                <div class="modal-content ${modalType}-content">
                    <div class="modal-header">
                        <h3>${filename}</h3>
                        <span class="close-view-modal">&times;</span>
                    </div>
                    <div class="modal-body">
                        ${content}
                    </div>
                </div>
            </div>
        `;

        $('body').append(previewHtml);
        $(`#${modalId}`).show();
    }

    /**
     * Admin-specific attachment handlers
     */
    $(document).on('click', '.download-attachment', function(e) {
        e.preventDefault();
        const $button = $(this);
        const fileUrl = $button.data('file-url');
        const filename = $button.data('filename');

        if (fileUrl && fileUrl !== '#') {
            const link = document.createElement('a');
            link.href = fileUrl;
            link.download = filename;
            link.target = '_blank';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    });

    $(document).on('click', '.preview-attachment', function(e) {
        e.preventDefault();
        const $button = $(this);
        const fileUrl = $button.data('file-url');
        const filename = fileUrl.split('/').pop();

        if (isImageFile(filename)) {
            showImagePreview(fileUrl, filename);
        } else if (isPdfFile(filename)) {
            showPdfPreview(fileUrl, filename);
        } else {
            window.open(fileUrl, '_blank');
        }
    });

    /**
     * Modal close handlers (for attachment modals only)
     */
    $(document).on('click', '#attachments-modal .close-view-modal', function() {
        $('#attachments-modal').remove();
    });

    $(document).on('click', '#attachments-modal', function(e) {
        if (e.target === this) {
            $(this).remove();
        }
    });

    // REMOVED: All pagination logic - now handled by unified-dashboard-filters.js
    console.log('Admin.js loaded - pagination handled by unified-dashboard-filters.js');

});