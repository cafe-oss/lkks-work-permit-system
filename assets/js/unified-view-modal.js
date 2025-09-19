/**
 * FIXED Unified View Modal for Approvers, Reviewers, and Admins
 * File: assets/js/unified-view-modal.js
 * 
 * FIXES: Attachment loading for View Details button
 */


(function($) {
    'use strict';

    // Global Unified View Modal Object
    window.WPS_UnifiedViewModal = {
        currentPermitData: null,
        userType: 'admin',
        ajaxUrl: '',
        nonce: '',
        isInitialized: false,

        /**
         * Initialize the unified view modal system
         */
        init: function(options) {
            this.userType = options.userType || 'admin';
            this.ajaxUrl = options.ajaxUrl || '';
            this.nonce = options.nonce || '';
            this.isInitialized = true;

            this.initializeEventListeners();
            this.initializeModalHandlers();

        },

        /**
         * Auto-initialize with fallback detection
         */
        autoInit: function() {
            if (this.isInitialized) return;
            
            // Try to get config from existing dashboard vars
            let userType = 'reviewer'; // default
            let ajaxUrl = '';
            let nonce = '';
            
            // Check for existing dashboard configuration
            if (typeof wps_dashboard_vars !== 'undefined') {
                userType = wps_dashboard_vars.dashboard_type || 'reviewer';
                ajaxUrl = wps_dashboard_vars.ajax_url || wps_dashboard_vars.ajaxUrl || '';
                nonce = wps_dashboard_vars.nonce || wps_dashboard_vars.user_nonce || '';
            } else if (typeof wps_unified_view_vars !== 'undefined') {
                userType = wps_unified_view_vars.user_type || 'reviewer';
                ajaxUrl = wps_unified_view_vars.ajax_url || '';
                nonce = wps_unified_view_vars.nonce || '';
            }

            this.init({
                userType: userType,
                ajaxUrl: ajaxUrl,
                nonce: nonce
            });
        },

        /**
         * Initialize event listeners
         */
        initializeEventListeners: function() {
            // View Details button click (for unified dashboard)
            $(document).on('click', '.view-details-btn', (e) => {
                e.preventDefault();
                this.handleViewDetailsClick(e.target);
            });

            // View button click (for admin dashboard-tab.php)
            $(document).on('click', '.view-permit', (e) => {
                e.preventDefault();
                this.handleViewPermitClick(e.target);
            });

            // Preview permit functionality
            $(document).on('click', '#preview-permit', (e) => {
                e.preventDefault();
                this.handlePreviewPermit();
            });

            // Print permit functionality
            $(document).on('click', '#print-permit', (e) => {
                e.preventDefault();
                this.handlePrintPermit();
            });

            // Attachments retry functionality
            $(document).on('click', '.retry-attachments-btn', (e) => {
                e.preventDefault();
                const permitId = $(e.target).data('permit-id');
                if (permitId) {
                    this.loadAttachmentsForModal(permitId);
                }
            });
        },

        /**
         * Initialize modal handlers
         */
        initializeModalHandlers: function() {
            // Close modal handlers
            $(document).on('click', '.close-view-modal, .wps-modal-close', (e) => {
                if ($(e.target).hasClass('close-view-modal') || $(e.target).hasClass('wps-modal-close')) {
                    e.preventDefault();
                    this.closeModal();
                }
            });

            // Modal overlay click to close
            $(document).on('click', '.modal-overlay, .wps-modal', (e) => {
                if ($(e.target).hasClass('modal-overlay') || $(e.target).hasClass('wps-modal')) {
                    this.closeModal();
                }
            });

            // Escape key to close modal
            $(document).on('keydown', (e) => {
                if (e.key === 'Escape') {
                    const $visibleModal = $('#view-details-modal:visible, #view-modal:visible');
                    if ($visibleModal.length > 0) {
                        this.closeModal();
                    }
                }
            });

            // Card header collapse/expand
            $(document).on('click', '.permit-info-card-header', function() {
                $(this).closest('.permit-info-card').toggleClass('collapsed');
            });
        },

        /**
         * Handle view details button click - MAIN ENTRY POINT - FIXED
         */
        handleViewDetailsClick: function(button) {
            const $button = $(button);
            const permitId = $button.data('permit-id');
            
            if (!permitId) {
                alert('Invalid permit ID');
                return;
            }

            // For View Details, we need to load full permit data via AJAX first
            // This ensures we have all the data including proper attachment permissions
            $button.text('Loading...').prop('disabled', true);
            this.loadPermitForViewing(permitId, $button);
        },

        /**
         * Handle view permit button click (for admin)
         */
        handleViewPermitClick: function(button) {
            const $button = $(button);
            const permitId = $button.data('permit-id');
            
            if (!permitId) {
                alert('Invalid permit ID');
                return;
            }

            $button.text('Loading...').prop('disabled', true);
            this.loadPermitForViewing(permitId, $button);
        },

        /**
         * Extract permit data from button attributes (FALLBACK ONLY)
         */
        extractPermitDataFromButton: function($button) {
            return {
                id: $button.data('permit-id'),
                email: $button.data('email') || 'N/A',
                tenant: $button.data('tenant') || 'N/A',
                issuedTo: $button.data('issued-to') || 'N/A',
                requestorType: $button.data('requestor-type') || 'N/A',
                position: $button.data('requester-position') || 'N/A',
                workArea: $button.data('work-area') || 'N/A',
                category: $button.data('category-name') || 'N/A',
                otherSpecification: $button.data('other-specification') || '',
                personnel: $button.data('personnel') || 'N/A',
                workDescription: $button.data('work-description') || 'N/A',
                startDate: $button.data('start-date') || 'N/A',
                startTime: $button.data('start-time') || '',
                endDate: $button.data('end-date') || 'N/A',
                endTime: $button.data('end-time') || '',
                status: $button.data('status') || 'N/A',
                submitted: $button.data('submitted') || 'N/A',
                reviewer: $button.data('reviewer') || 'N/A'
            };
        },

        /**
         * Load permit for viewing - FIXED TO USE CORRECT AJAX ACTION
         */
        loadPermitForViewing: function(permitId, $button) {

            // Use the correct AJAX action based on user type
            const actionName = this.getAjaxActionName('get_permit_details');
            
            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: actionName,
                    permit_id: permitId,
                    nonce: this.nonce
                },
                success: (response) => {
                    
                    if (response.success) {
                        this.currentPermitData = response.data;
                        this.populateUnifiedModal(response.data);
                        this.showModal();
                        
                        // Load attachments after modal is shown and data is loaded
                        setTimeout(() => {
                            this.loadAttachmentsForModal(permitId);
                        }, 200);
                    } else {
                        console.error('Failed to load permit details:', response);
                        alert('Error: ' + (response.data || 'Failed to load permit details'));
                    }
                },
                error: (xhr, status, error) => {
                    console.error('AJAX error loading permit:', {xhr, status, error});
                    alert('Network error occurred. Please try again.');
                },
                complete: () => {
                    this.restoreButtonState($button, 'View Details');
                }
            });
        },

        /**
         * Populate unified modal with full permit data (from AJAX)
         */
        populateUnifiedModal: function(data) {
            
            $('#details-modal-title').text(`Permit ${data.permit_id} - Details`);
            
            $('#details-permit-id').val(data.id);
            $('#details-email-address').text(data.email_address || 'N/A');
            $('#details-tenant').text(data.tenant || 'N/A');
            $('#details-work-area').text(data.work_area || 'N/A');
            $('#details-issued-to').text(data.issued_to || data.tenant || 'N/A');
            $('#details-requester-position').text(data.requester_position || 'N/A');
            $('#details-requestor-type').text(data.requestor_type || 'N/A');
            $('#details-personnel').text(data.personnel_list || 'N/A');
            $('#details-work-description').text(data.tenant_field || 'N/A');

            this.handleCategoryDisplay(data.category_name, data.other_specification);
            this.setModalDates(data);
            this.setModalStatus(data.status);

            if (this.userType === 'approver' && data.reviewer_name) {
                $('#details-reviewer').text(data.reviewer_name);
            }

            this.currentPermitData = data;
        },

        /**
         * Load attachments for modal - FIXED TO TARGET CORRECT CONTAINER
         */
        loadAttachmentsForModal: function(permitId) {
            
            // FIXED: Target the container in the visible modal specifically
            let attachmentsContainer;
            if ($('#view-details-modal:visible').length > 0) {
                attachmentsContainer = $('#view-details-modal #view-attachments-container');
            } else if ($('#reviewer-modal:visible').length > 0) {
                attachmentsContainer = $('#reviewer-modal #view-attachments-container');
            } else if ($('#approver-modal:visible').length > 0) {
                attachmentsContainer = $('#approver-modal #view-attachments-container');
            } else {
                attachmentsContainer = $('#view-attachments-container').first();
            }
            
            if (attachmentsContainer.length === 0) {
                console.error('Attachments container not found in modal');
                return;
            }
            
            // Show loading state
            attachmentsContainer.html(`
                <div class="loading-attachments">
                    <div class="loading-spinner"></div>
                    <p>Loading attachments...</p>
                </div>
            `);

            // Rest of your existing code stays the same...
            let ajaxAction;
            switch (this.userType) {
                case 'admin':
                    ajaxAction = 'wps_admin_get_permit_attachments';
                    break;
                case 'approver':
                    ajaxAction = 'wps_get_permit_attachments';
                    break;
                case 'reviewer':
                default:
                    ajaxAction = 'wps_get_permit_attachments';
                    break;
            }
            
            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: ajaxAction,
                    permit_id: permitId,
                    nonce: this.nonce
                },
                timeout: 15000,
                success: (response) => {
                    
                    if (response.success && response.data && response.data.attachments) {
                        if (response.data.attachments.length > 0) {
                            this.displayAttachmentsInModal(response.data.attachments, attachmentsContainer);
                        } else {
                            this.showNoAttachmentsMessage(attachmentsContainer);
                        }
                    } else {
                        console.error('Attachments load failed:', response);
                        this.showAttachmentsError(attachmentsContainer, permitId, response.data || 'Unknown error');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Attachments AJAX error:', {
                        status: status,
                        error: error,
                        response: xhr.responseText,
                        permitId: permitId,
                        userType: this.userType,
                        ajaxAction: ajaxAction
                    });
                    
                    this.showAttachmentsError(attachmentsContainer, permitId, `Network error: ${status}`);
                }
            });
        },

        /**
         * Display attachments in modal
         */
        displayAttachmentsInModal: function(attachments, container) {
            const attachmentsContainer = container || $('#view-attachments-container');
            
            let attachmentsHtml = `
                <div class="attachments-summary">
                    <h4>📁 ${attachments.length} Document${attachments.length !== 1 ? 's' : ''} Found</h4>
                </div>
                <div class="attachments-list">
            `;
            
            attachments.forEach((attachment) => {
                const fileName = attachment.original_filename || 'Unknown File';
                const fileSize = attachment.formatted_file_size || 'Unknown size';
                const fileUrl = attachment.file_url || '#';
                const fileExtension = this.getFileExtension(fileName);
                const canConvertToPdf = this.canConvertToPdf(fileName);
                const isPdf = fileExtension === 'pdf';
                
                let viewButton = '';
                if (isPdf) {
                    viewButton = `<button class="view-pdf-btn" data-file-url="${fileUrl}" data-filename="${fileName}">View PDF</button>`;
                } else if (canConvertToPdf) {
                    viewButton = `<button class="view-as-pdf-btn" data-document-id="${attachment.id}" data-filename="${fileName}">View as PDF</button>`;
                } else if (this.isImageFile(fileName)) {
                    viewButton = `<button class="view-image-btn" data-file-url="${fileUrl}" data-filename="${fileName}">Preview</button>`;
                }
                
                attachmentsHtml += `
                    <div class="attachment-item">
                        <div class="attachment-icon">${this.getFileIcon(fileName)}</div>
                        <div class="attachment-info">
                            <div class="attachment-name">${fileName}</div>
                            <div class="attachment-meta">
                                <span class="file-size">${fileSize}</span>
                                <span class="upload-date">${attachment.formatted_upload_date || 'Unknown date'}</span>
                                <span class="uploader">by ${attachment.uploaded_by_type || 'applicant'}</span>
                            </div>
                        </div>
                        <div class="attachment-actions">
                            ${viewButton}
                        </div>
                    </div>
                `;
            });
            
            attachmentsHtml += '</div>';
            attachmentsContainer.html(attachmentsHtml);
            
            this.bindAttachmentViewHandlers();
        },

        /**
         * Show no attachments message
         */
        showNoAttachmentsMessage: function(container) {
            container.html(`
                <div class="no-attachments">
                    <div class="no-attachments-icon">📎</div>
                    <p>No attachments found for this permit.</p>
                    <small>Files uploaded with the permit application would appear here.</small>
                </div>
            `);
        },

        /**
         * Show attachments error
         */
        showAttachmentsError: function(container, permitId, errorMessage) {
            container.html(`
                <div class="attachments-error">
                    <div class="error-icon">⚠️</div>
                    <p>Could not load attachments</p>
                    <small>${errorMessage}</small>
                    <button class="retry-attachments-btn" data-permit-id="${permitId}">Try Again</button>
                </div>
            `);
        },

        /**
         * Bind attachment viewing handlers
         */
        bindAttachmentViewHandlers: function() {
            const self = this;
            
            $(document).off('click', '.view-pdf-btn').on('click', '.view-pdf-btn', function(e) {
                e.preventDefault();
                const fileUrl = $(this).data('file-url');
                const filename = $(this).data('filename');
                self.openPdfInNewTab(fileUrl, filename);
            });
            
            $(document).off('click', '.view-as-pdf-btn').on('click', '.view-as-pdf-btn', function(e) {
                e.preventDefault();
                const documentId = $(this).data('document-id');
                const filename = $(this).data('filename');
                const $button = $(this);
                self.convertAndViewAsPdf(documentId, filename, $button);
            });
            
            $(document).off('click', '.view-image-btn').on('click', '.view-image-btn', function(e) {
                e.preventDefault();
                const fileUrl = $(this).data('file-url');
                const filename = $(this).data('filename');
                self.openImagePreview(fileUrl, filename);
            });
        },

        /**
         * Handle category display and others specification
         */
        handleCategoryDisplay: function(categoryName, otherSpecification) {
            const categoryElement = $('#details-category');
            const othersSpecElement = $('#details-other-specification');
            const othersSpecItem = $('.others-specification-item');

            if (categoryName?.toLowerCase() === 'others' && otherSpecification) {
                categoryElement.text('Others');
                othersSpecElement.text(otherSpecification);
                othersSpecItem.show();
            } else {
                categoryElement.text(categoryName || 'N/A');
                othersSpecItem.hide();
            }
        },

        /**
         * Set modal dates
         */
        setModalDates: function(data) {
            if (data.requested_start_date && data.requested_start_time) {
                $('#details-start-date').text(this.formatDateTime(data.requested_start_date, data.requested_start_time));
            } else {
                $('#details-start-date').text(data.requested_start_date || 'N/A');
            }

            if (data.requested_end_date && data.requested_end_time) {
                $('#details-end-date').text(this.formatDateTime(data.requested_end_date, data.requested_end_time));
            } else {
                $('#details-end-date').text(data.requested_end_date || 'N/A');
            }
        },

        /**
         * Set modal status
         */
        setModalStatus: function(status) {
            const statusLabels = {
                'pending_review': 'Pending Review',
                'pending_approval': 'Pending Approval',
                'approved': 'Approved',
                'cancelled': 'Rejected'
            };

            const statusClass = 'status-' + (status || '').replace('_', '-');
            const statusText = statusLabels[status] || (status || '').replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());

            $('#details-current-status')
                .removeClass()
                .addClass('status-badge ' + statusClass)
                .text(statusText);
        },

        /**
         * Show the modal
         */
        showModal: function() {
            // Hide other modals first
            $('#reviewer-modal, #approver-modal, #view-modal').hide();
            
            // Show the view details modal
            $('#view-details-modal').show();
            
            // MINIMAL FIX: Ensure attachments container is targeted correctly
            setTimeout(() => {
                const $container = $('#view-details-modal #view-attachments-container');
                if ($container.length === 0) {
                    // If specific container doesn't exist, create it or use general one
                    const $generalContainer = $('#view-attachments-container');
                    if ($generalContainer.length > 0 && !$generalContainer.closest('#view-details-modal').length) {
                        $generalContainer.attr('id', 'view-attachments-container-temp');
                        $('#view-details-modal').find('.attachments-section, .permit-info-card').last().after(
                            '<div id="view-attachments-container"></div>'
                        );
                    }
                }
            }, 100);
        },

        /**
         * Close the modal
         */
        closeModal: function() {
            console.log('Closing unified view modal...');
            $('#view-modal, #view-details-modal').hide();
            this.currentPermitData = null;
            this.clearModalContent();
        },

        /**
         * Clear modal content
         */
        clearModalContent: function() {
            const fieldsToClear = [
                'details-permit-id', 'details-email-address', 'details-tenant',
                'details-work-area', 'details-issued-to', 'details-requester-position',
                'details-requestor-type', 'details-category', 'details-personnel',
                'details-work-description', 'details-start-date', 'details-end-date',
                'details-current-status'
            ];

            fieldsToClear.forEach(id => $(`#${id}`).text(''));
            $('#details-current-status').removeClass();
            $('.others-specification-item').hide();
            
            if (this.userType === 'approver') {
                $('#details-reviewer').text('');
            }
        },

        // File handling methods
        getFileExtension: function(filename) {
            return filename.split('.').pop().toLowerCase();
        },

        canConvertToPdf: function(filename) {
            const convertibleExtensions = ['docx', 'doc'];
            const extension = this.getFileExtension(filename);
            return convertibleExtensions.includes(extension);
        },

        isImageFile: function(filename) {
            const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            const extension = this.getFileExtension(filename);
            return imageExtensions.includes(extension);
        },

        getFileIcon: function(filename) {
            const fileIcons = {
                'pdf': '📄', 'doc': '📝', 'docx': '📝', 'txt': '📄',
                'jpg': '🖼️', 'jpeg': '🖼️', 'png': '🖼️', 'gif': '🖼️', 'webp': '🖼️'
            };
            const extension = filename.split('.').pop().toLowerCase();
            return fileIcons[extension] || '📎';
        },

        // Viewing methods
        openPdfInNewTab: function(fileUrl, filename) {
            window.open(fileUrl, '_blank');
        },

        openImagePreview: function(fileUrl, filename) {
            window.open(fileUrl, '_blank');
        },

        convertAndViewAsPdf: function(documentId, filename, $button) {
            const originalText = $button.text();
            $button.text('Converting...').prop('disabled', true);
            
            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wps_view_document_as_pdf',
                    document_id: documentId,
                    nonce: this.nonce
                },
                timeout: 30000,
                success: (response) => {
                    if (response.success) {
                        window.open(response.data.pdf_url, '_blank');
                    } else {
                        alert('Error converting document: ' + (response.data || 'Unknown error'));
                    }
                },
                error: (xhr, status, error) => {
                    console.error('PDF conversion error:', {xhr, status, error});
                    if (status === 'timeout') {
                        alert('Document conversion timed out. The file may be too large or complex.');
                    } else {
                        alert('Network error occurred during conversion. Please try again.');
                    }
                },
                complete: () => {
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },

        // Preview and print handlers
        handlePreviewPermit: function() {
            if (!this.currentPermitData) {
                const permitId = $('#details-permit-id').val();
                if (!permitId) {
                    alert('No permit data available for preview');
                    return;
                }
                this.currentPermitData = { id: permitId };
            }
            
            const permitId = this.currentPermitData.id;
            const $button = $('#preview-permit');
            
            $button.text('Generating Preview...').prop('disabled', true);
            
            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wps_export_permit_by_id_pdf',
                    permit_id: permitId,
                    preview_mode: 'inline',
                    nonce: this.nonce
                },
                success: (response) => {
                    if (response.success && response.data.download_url) {
                        let previewUrl = response.data.download_url;
                        if (previewUrl.includes('?')) {
                            previewUrl += '&inline=1';
                        } else {
                            previewUrl += '?inline=1';
                        }
                        window.open(previewUrl, '_blank');
                    } else {
                        alert('Error generating preview: ' + (response.data || 'Unknown error'));
                    }
                },
                error: (xhr, status, error) => {
                    console.error('PDF preview generation error:', error);
                    alert('Network error occurred while generating preview');
                },
                complete: () => {
                    $button.text("Preview Permit").prop('disabled', false);
                }
            });
        },

        handlePrintPermit: function() {
            if (!this.currentPermitData || this.userType !== 'admin') {
                alert('No permit data available for printing');
                return;
            }
            
            const permitId = this.currentPermitData.id;
            const $button = $('#print-permit');
            const originalText = $button.text();
            
            $button.text('Generating PDF...').prop('disabled', true);
            
            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wps_export_permit_by_id_pdf',
                    permit_id: permitId,
                    nonce: this.nonce
                },
                success: (response) => {
                    if (response.success && response.data.download_url) {
                        this.printPDF(response.data.download_url);
                    } else {
                        alert('Error generating PDF: ' + (response.data || 'Unknown error'));
                    }
                },
                error: (xhr, status, error) => {
                    console.error('PDF generation error:', error);
                    alert('Network error occurred while generating PDF');
                },
                complete: () => {
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },

        printPDF: function(pdfUrl) {
            $('#print-iframe').remove();
            
            const $iframe = $('<iframe>')
                .attr('id', 'print-iframe')
                .attr('src', pdfUrl)
                .css({
                    'position': 'absolute',
                    'top': '-9999px',
                    'left': '-9999px',
                    'width': '1px',
                    'height': '1px',
                    'border': 'none'
                });
            
            $('body').append($iframe);
            
            $iframe.on('load', function() {
                setTimeout(function() {
                    try {
                        const iframeWindow = $iframe[0].contentWindow;
                        if (iframeWindow) {
                            iframeWindow.focus();
                            iframeWindow.print();
                        } else {
                            window.open(pdfUrl, '_blank');
                        }
                    } catch (e) {
                        console.error('Print iframe error:', e);
                        window.open(pdfUrl, '_blank');
                    }
                    
                    setTimeout(() => $iframe.remove(), 1000);
                }, 500);
            });
            
            setTimeout(() => {
                if ($iframe.length && !$iframe[0].contentDocument.readyState) {
                    console.warn('PDF iframe load timeout, opening in new window');
                    window.open(pdfUrl, '_blank');
                    $iframe.remove();
                }
            }, 5000);
        },

        // Utility methods - FIXED AJAX ACTION MAPPING
        getAjaxActionName: function(action) {
            if (action === 'get_permit_details') {
                switch (this.userType) {
                    case 'admin':
                        return 'wps_get_permit_details';
                    case 'approver':
                        return 'wps_get_permit_for_approval'; // FIXED: Use correct action for approvers
                    case 'reviewer':
                        return 'wps_get_permit_for_review'; // FIXED: Use correct action for reviewers
                    default:
                        return 'wps_get_permit_details';
                }
            }
            return action;
        },

        formatDateTime: function(date, time) {
            try {
                const dateTime = new Date(`${date} ${time}`);
                return dateTime.toLocaleDateString('en-US', {
                    weekday: 'short',
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit',
                    hour12: true
                });
            } catch (e) {
                return `${date} ${time}`;
            }
        },

        restoreButtonState: function($button, originalText) {
            if ($button?.length) {
                $button.removeClass('loading processing')
                    .prop('disabled', false)
                    .text(originalText || 'View Details');
            }
        }
    };

    // Auto-initialize when DOM is ready
    $(document).ready(function() {
        // Give priority to explicit initialization, but provide fallback
        setTimeout(function() {
            if (!window.WPS_UnifiedViewModal.isInitialized) {
                window.WPS_UnifiedViewModal.autoInit();
            }
        }, 500);

        // MINIMAL FIX: Add this AFTER the main object is defined
        // Monitor when view-details-modal becomes visible and trigger attachment loading
        const checkModalAndLoadAttachments = function() {
            if ($('#view-details-modal:visible').length > 0) {
                const permitId = $('#details-permit-id').val();
                if (permitId && window.WPS_UnifiedViewModal && window.WPS_UnifiedViewModal.loadAttachmentsForModal) {
                    setTimeout(() => {
                        window.WPS_UnifiedViewModal.loadAttachmentsForModal(permitId);
                    }, 300);
                }
            }
        };

        // Use modern MutationObserver instead of deprecated DOMNodeInserted
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'attributes' && 
                    mutation.attributeName === 'style' && 
                    mutation.target.id === 'view-details-modal') {
                    checkModalAndLoadAttachments();
                }
            });
        });

        // Start observing when modal exists
        const modal = document.getElementById('view-details-modal');
        if (modal) {
            observer.observe(modal, {
                attributes: true,
                attributeFilter: ['style']
            });
        }
    });

})(jQuery);