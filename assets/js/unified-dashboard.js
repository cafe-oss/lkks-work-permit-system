/**
 * Unified Dashboard JavaScript for Approvers and Reviewers (View Functions Removed)
 * File: assets/js/unified-dashboard.js
 * 
 * Cleaned version with view functionality moved to unified-view-modal.js
 */

(function($) {
    'use strict';

    // Constants
    const STATUS_LABELS = {
        'pending_review': 'Pending Review',
        'pending_approval': 'Pending Approval',
        'approved': 'Approved',
        'cancelled': 'Rejected'
    };

    // Global Dashboard Object
    window.WPS_Dashboard = {
        config: null,
        currentPermitData: null,
        searchTimeout: null,
        suggestionsCache: {},

        /**
         * Initialize the dashboard
         */
        init: function(options) {
            this.config = $.extend({
                dashboardType: 'reviewer',
                ajaxUrl: '',
                nonce: '',
                config: {}
            }, options);

            // Initialize all components
            this.initializeSearchAndFilters();
            this.initializePagination();
            this.initializeMainFunctionality();
            this.initializeEventListeners();
            this.initializeModalHandlers();
            this.initializeButtonHandlers();

            // Initialize unified view modal
            if (typeof WPS_UnifiedViewModal !== 'undefined') {
                WPS_UnifiedViewModal.init({
                    userType: this.config.dashboardType,
                    ajaxUrl: this.config.ajaxUrl,
                    nonce: this.config.nonce
                });
            }

        },

        /**
         * Search and Filter Functionality
         */
        initializeSearchAndFilters: function() {

            // Main search form submission
            $('#search-filter-form').on('submit', (e) => {
                e.preventDefault();
                this.handleSearchFormSubmit(e.target);
            });

            // Quick filter changes
            $('#status-filter, #per-page, #work-category-filter, #reviewer-filter').on('change', () => {
                $('#search-filter-form').trigger('submit');
            });

            // Clear filters button
            $('#clear-filters').on('click', this.clearAllFilters.bind(this));

            // Initialize search suggestions
            this.initializeSearchSuggestions();
        },

        handleSearchFormSubmit: function(form) {
            const formData = new FormData(form);
            const url = new URL(window.location);
            
            // Preserve current page, clear other params
            const currentPage = url.searchParams.get('page');
            url.search = '';
            if (currentPage) url.searchParams.set('page', currentPage);

            // Add form data to URL
            for (let [key, value] of formData.entries()) {
                if (value && value !== 'all' && value !== '') {
                    url.searchParams.set(key, value);
                }
            }

            // Reset to page 1 when searching/filtering
            url.searchParams.set('paged', '1');
            window.location.href = url.toString();
        },

        clearAllFilters: function() {
            const url = new URL(window.location);
            const page = url.searchParams.get('page');
            url.search = '';
            if (page) url.searchParams.set('page', page);
            window.location.href = url.toString();
        },

        /**
         * Search Suggestions Functionality
         */
        initializeSearchSuggestions: function() {
            const searchInput = $('#search-input');
            const searchType = $('#search-type');

            // Search input with debounce
            searchInput.on('input', () => {
                const query = searchInput.val().trim();
                const type = searchType.val();

                clearTimeout(this.searchTimeout);

                if (query.length >= 2) {
                    this.searchTimeout = setTimeout(() => {
                        this.loadSearchSuggestions(query, type);
                    }, 300);
                } else {
                    this.hideSuggestions();
                }
            });

            this.bindSuggestionEvents(searchInput);
        },

        bindSuggestionEvents: function(searchInput) {
            // Handle suggestion clicks
            $(document).on('click', '.suggestion-item', () => {
                searchInput.val($(this).text());
                this.hideSuggestions();
                $('#search-filter-form').trigger('submit');
            });

            // Hide suggestions when clicking outside
            $(document).on('click', (e) => {
                if (!$(e.target).closest('.search-input-wrapper').length) {
                    this.hideSuggestions();
                }
            });

            // Keyboard navigation
            searchInput.on('keydown', (e) => this.handleSuggestionKeyboard(e, searchInput));
        },

        handleSuggestionKeyboard: function(e, searchInput) {
            const suggestions = $('.suggestion-item:visible');
            const active = $('.suggestion-item.active');

            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    if (active.length === 0) {
                        suggestions.first().addClass('active');
                    } else {
                        active.removeClass('active').next().addClass('active');
                    }
                    break;

                case 'ArrowUp':
                    e.preventDefault();
                    if (active.length > 0) {
                        active.removeClass('active').prev().addClass('active');
                    }
                    break;

                case 'Enter':
                    if (active.length > 0) {
                        e.preventDefault();
                        searchInput.val(active.text());
                        this.hideSuggestions();
                        $('#search-filter-form').trigger('submit');
                    }
                    break;

                case 'Escape':
                    this.hideSuggestions();
                    break;
            }
        },

        /**
         * Load search suggestions via AJAX
         */
        loadSearchSuggestions: function(query, type) {
            const cacheKey = `${type}:${query}`;

            if (this.suggestionsCache[cacheKey]) {
                this.showSuggestions(this.suggestionsCache[cacheKey]);
                return;
            }

            const actionName = this.config.dashboardType === 'approver' ? 
                'wps_get_approver_search_suggestions' : 'wps_get_search_suggestions';

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: actionName,
                    search_type: type,
                    query: query,
                    nonce: this.config.nonce
                },
                success: (response) => {
                    if (response.success && response.data.length > 0) {
                        this.suggestionsCache[cacheKey] = response.data;
                        this.showSuggestions(response.data);
                    } else {
                        this.hideSuggestions();
                    }
                },
                error: () => this.hideSuggestions()
            });
        },
        hideSuggestions: function() {
            $('.suggestion-item').removeClass('active');
        },

        /**
         * Pagination Functionality
         */
        initializePagination: function() {
            // Pagination links
            $(document).on('click', '.pagination-links a', (e) => {
                e.preventDefault();
                const $link = $(e.target);
                const url = $link.attr('href');
                
                if (url && !$link.hasClass('current')) {
                    $link.addClass('loading').text('Loading...');
                    window.location.href = url;
                }
            });

            // Page jump functionality
            $('#page-jump').on('keypress', (e) => {
                if (e.key === 'Enter') {
                    this.jumpToPage();
                }
            });

            // Keyboard shortcuts for pagination
            this.bindPaginationKeyboardShortcuts();
        },

        bindPaginationKeyboardShortcuts: function() {
            $(document).on('keydown', (e) => {
                if (!e.ctrlKey && !e.metaKey) return;

                let link = null;
                if (e.key === 'ArrowLeft') {
                    link = $('.pagination-links .prev');
                } else if (e.key === 'ArrowRight') {
                    link = $('.pagination-links .next');
                }

                if (link && link.length && !link.hasClass('disabled')) {
                    e.preventDefault();
                    window.location.href = link.attr('href');
                }
            });
        },

        jumpToPage: function() {
            const pageInput = document.getElementById('page-jump');
            const page = parseInt(pageInput.value);
            const maxPages = parseInt(pageInput.max);

            if (page >= 1 && page <= maxPages) {
                const url = new URL(window.location);
                url.searchParams.set('paged', page);
                window.location.href = url.toString();
            } else {
                alert('Please enter a valid page number.');
            }
        },

        /**
         * Main Functionality (Review/Approve)
         */
        initializeMainFunctionality: function() {
            // Action button click (review/approve)
            $(document).on('click', `.${this.config.config.action_button_class}`, (e) => {
                e.preventDefault();
                this.handleActionButtonClick(e.target);
            });

            // Decision change in modal
            $(document).on('change', `#${this.config.config.status_field_id}`, () => {
                this.handleStatusChange();
            });

            // Comment input validation
            $(document).on('input', `#${this.config.config.comment_field_id}`, () => {
                this.validateForm();
            });

            // Form submission
            $(document).on('submit', `#${this.config.config.form_id}`, (e) => {
                e.preventDefault();
                this.submitDecision();
            });

            // Card header collapse/expand
            $('.permit-info-card-header').on('click', function() {
                $(this).closest('.permit-info-card').toggleClass('collapsed');
            });
        },

        handleActionButtonClick: function(button) {
            const $button = $(button);
            const permitId = $button.data('permit-id');

            if (!permitId) {
                alert('Invalid permit ID');
                return;
            }

            // Show loading state
            $button.text('Loading...').prop('disabled', true);
            this.loadPermitForAction(permitId, $button);
        },

        /**
         * Load permit for action (review/approve)
         */
        loadPermitForAction: function(permitId, $button) {

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: this.config.config.ajax_action_get,
                    permit_id: permitId,
                    nonce: this.config.nonce
                },
                timeout: 30000,
                success: (response) => {
                    if (response.success) {
                        this.currentPermitData = response.data;
                        this.populateActionModal(response.data);
                        $(`#${this.config.config.modal_id}`).show().addClass('active');
                    } else {
                        alert('Error: ' + (response.data || 'Failed to load permit details'));
                    }
                },
                error: (xhr, status, error) => {
                    console.error('AJAX error:', {xhr, status, error});
                    let errorMessage = 'Network error occurred. Please try again.';
                    
                    if (status === 'timeout') {
                        errorMessage = 'Request timed out. Please try again.';
                    } else if (xhr.status === 403) {
                        errorMessage = 'Permission denied. Please refresh the page and try again.';
                    }
                    
                    alert(errorMessage);
                },
                complete: () => {
                    this.restoreButtonState($button, this.config.config.action_button_text);
                }
            });
        },

        /**
         * Populate action modal (review/approve)
         */
        populateActionModal: function(data) {
            // Basic permit information
            this.setModalField('modal-title', `${this.config.config.modal_title} #${data.permit_id}`);
            this.setModalField('modal-permit-id', '#' + data.id);
            this.setModalField('modal-email-address', data.email_address);
            this.setModalField('modal-issued-to', data.issued_to);
            this.setModalField('modal-requester-position', data.requester_position);
            this.setModalField('modal-requestor-type', data.requestor_type);
            this.setModalField('modal-tenant', data.tenant);
            this.setModalField('modal-work-area', data.work_area);
            this.setModalField('modal-personnel', data.personnel_list);
            this.setModalField('modal-work-description', data.tenant_field);

            // Handle reviewer name for approvers
            if (this.config.dashboardType === 'approver') {
                this.setModalField('modal-reviewer-name', data.reviewer_name || 'N/A');
            }

            // Handle work category with Others specification
            this.handleOthersCategory(data);

            // Format and set dates
            this.setModalDates(data);

            // Set current status
            this.setModalStatus(data);

            // Reset and configure form
            this.resetModalForm(data);

            if (typeof WPS_UnifiedViewModal !== 'undefined') {
                WPS_UnifiedViewModal.userType = this.config.dashboardType;
                WPS_UnifiedViewModal.ajaxUrl = this.config.ajaxUrl;
                WPS_UnifiedViewModal.nonce = this.config.nonce;
                WPS_UnifiedViewModal.loadAttachmentsForModal(data.id);
            }
        },

        setModalField: function(id, value) {
            $(`#${id}`).text(value || '');
        },

        handleOthersCategory: function(data) {
            const categoryElement = $('#modal-category');
            const othersSpecElement = $('#modal-other-specification');
            const othersSpecItem = $('.others-specification-item');

            if (data.category_name?.toLowerCase() === 'others' && data.other_specification) {
                categoryElement.text('Others');
                othersSpecElement.text(data.other_specification);
                othersSpecItem.show();
            } else {
                categoryElement.text(data.category_name || '');
                othersSpecItem.hide();
            }
        },

        setModalDates: function(data) {
            if (data.requested_start_date && data.requested_start_time) {
                $('#modal-start-date').text(this.formatDateTime(data.requested_start_date, data.requested_start_time));
            }

            if (data.requested_end_date && data.requested_end_time) {
                $('#modal-end-date').text(this.formatDateTime(data.requested_end_date, data.requested_end_time));
            }
        },

        setModalStatus: function(data) {
            const statusClass = 'status-' + (data.status || '').replace('_', '-');
            const statusText = STATUS_LABELS[data.status] || (data.status || '').replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());

            $('#modal-current-status')
                .removeClass()
                .addClass('status-badge ' + statusClass)
                .text(statusText);
        },

        resetModalForm: function(data) {
            // Set hidden field
            $('#permit-id').val(data.id);

            // Reset form fields
            $(`#${this.config.config.status_field_id}`).val('');
            $(`#${this.config.config.comment_field_id}`).val('');
            $('.comment-field').removeClass('required');
            $('#comment-required-indicator').hide();
            $('#comment-help').text('Provide comments when necessary. This will be included in email notifications.');

            this.validateForm();

            // Set permit ID for attachments button
            $('#modal-view-attachments-btn').data('permit-id', data.id);
        },

        /**
         * Handle status change in modal
         */
        handleStatusChange: function() {
            const decision = $(`#${this.config.config.status_field_id}`).val();
            const commentField = $('.comment-field');
            const commentTextarea = $(`#${this.config.config.comment_field_id}`);
            const commentIndicator = $('#comment-required-indicator');
            const helpText = $('#comment-help');

            // Reset field state
            commentField.removeClass('required');
            commentTextarea.prop('required', false);
            commentIndicator.hide();

            if (decision === 'cancelled') {
                this.configureRejectionComments(commentField, commentTextarea, commentIndicator, helpText);
            } else if (decision === 'approved' || decision === 'pending_approval') {
                this.configureApprovalComments(helpText);
            }

            this.validateForm();
        },

        configureRejectionComments: function(commentField, commentTextarea, commentIndicator, helpText) {
            commentField.addClass('required');
            commentTextarea.prop('required', true);
            commentIndicator.show();
            helpText.text('Please provide a clear reason for rejection. This will be sent to the applicant' + 
                         (this.config.dashboardType === 'approver' ? ' and reviewer.' : '.'));
        },

        configureApprovalComments: function(helpText) {
            helpText.text('Optional comments about your ' + 
                         (this.config.dashboardType === 'approver' ? 'final approval' : 'approval') + 
                         ' decision. This will be sent to the ' + 
                         (this.config.dashboardType === 'approver' ? 'applicant.' : 'approver.'));
        },

        /**
         * Submit decision
         */
        submitDecision: function() {
            const formData = this.getFormData();
            
            if (!this.validateSubmission(formData)) {
                return;
            }

            if (!this.confirmSubmission(formData)) {
                return;
            }

            this.processSubmission(formData);
        },

        getFormData: function() {
            return {
                permit_id: $('#permit-id').val(),
                status: $(`#${this.config.config.status_field_id}`).val(),
                comment: $(`#${this.config.config.comment_field_id}`).val().trim()
            };
        },

        validateSubmission: function(formData) {
            if (!formData.status) {
                alert('Please select a decision.');
                return false;
            }

            if (formData.status === 'cancelled' && !formData.comment) {
                alert('Rejection reason is required.');
                $(`#${this.config.config.comment_field_id}`).focus();
                return false;
            }

            return true;
        },

        confirmSubmission: function(formData) {
            const confirmMessage = this.getConfirmMessage(formData.status);
            return confirm(confirmMessage);
        },

        getConfirmMessage: function(status) {
            if (status === 'cancelled') {
                return 'Are you sure you want to reject this permit? The applicant' + 
                       (this.config.dashboardType === 'approver' ? ' and reviewer' : '') + ' will be notified.';
            }
            
            return this.config.dashboardType === 'approver' ? 
                'Are you sure you want to give final approval? This will activate the permit immediately.' :
                'Are you sure you want to approve this permit? It will be sent for final approval.';
        },

        processSubmission: function(formData) {
            const submitButton = $(`#${this.config.config.submit_button_id}`);
            const originalText = submitButton.text();

            submitButton.addClass('processing').text('Processing...').prop('disabled', true);

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: this.config.config.ajax_action_submit,
                    permit_id: formData.permit_id,
                    status: formData.status,
                    comment: formData.comment,
                    nonce: this.config.nonce
                },
                success: (response) => {
                    if (response.success) {
                        const successMessage = this.getSuccessMessage(formData.status);
                        alert(successMessage);
                        this.closeActionModal();
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        alert('Error: ' + (response.data || 'Failed to submit decision'));
                    }
                },
                error: () => {
                    alert('Network error occurred. Please try again.');
                },
                complete: () => {
                    submitButton.removeClass('processing').text(originalText).prop('disabled', false);
                    this.validateForm();
                }
            });
        },

        getSuccessMessage: function(status) {
            if (status === 'cancelled') {
                return 'Permit rejected successfully. The applicant' + 
                       (this.config.dashboardType === 'approver' ? ' and reviewer have' : ' has') + ' been notified.';
            }
            
            return this.config.dashboardType === 'approver' ?
                'Permit approved and activated! The applicant and team have been notified.' :
                'Permit approved and sent for final approval. The approver has been notified.';
        },

        /**
         * Validate form
         */
        validateForm: function() {
            const status = $(`#${this.config.config.status_field_id}`).val();
            const comment = $(`#${this.config.config.comment_field_id}`).val().trim();
            const submitButton = $(`#${this.config.config.submit_button_id}`);

            let isValid = false;

            if (status === 'approved' || status === 'pending_approval') {
                isValid = true;
            } else if (status === 'cancelled') {
                isValid = comment.length > 0;
            }

            submitButton.prop('disabled', !isValid);
        },

        /**
         * Close action modal
         */
        closeActionModal: function() {

            // Hide the modal
            $(`#${this.config.config.modal_id}`).hide();

            // Clear current permit data
            this.currentPermitData = null;

            // Reset form completely
            const form = $(`#${this.config.config.form_id}`)[0];
            if (form) form.reset();

            // Clear form fields explicitly
            $(`#${this.config.config.status_field_id}`).val('');
            $(`#${this.config.config.comment_field_id}`).val('');
            $('#permit-id').val('');

            // Reset form states
            $('.comment-field').removeClass('required');
            $('#comment-required-indicator').hide();
            $('#comment-help').text('Provide comments when necessary. This will be included in email notifications.');

            // Reset submit button
            $(`#${this.config.config.submit_button_id}`).prop('disabled', true).removeClass('processing');

            // Clear modal content
            $('.info-item span, #modal-work-description').text('');
            $('.others-specification-item').hide();
            $('.wps-modal').removeClass('show active');

        },

        /**
         * Event Listeners
         */
        initializeEventListeners: function() {
            // Filter changes
            ['#status-filter', '#per-page', '#work-category-filter'].forEach(selector => {
                const element = document.querySelector(selector);
                if (element) {
                    element.addEventListener('change', () => this.applyFilters());
                }
            });
        },

        /**
         * Modal Handlers
         */
        initializeModalHandlers: function() {
            // Remove existing handlers to prevent duplicates
            $(document).off('click.modal-close keydown.modal-escape');

            // Modal close button and overlay click
            $(document).on('click.modal-close', '.wps-modal-close, .wps-modal', (e) => {
                if ($(e.target).hasClass('wps-modal-close') || $(e.target).hasClass('wps-modal')) {
                    e.preventDefault();
                    e.stopPropagation();
                    this.handleModalClose($(e.target).closest('.wps-modal'));
                }
            });

            // Escape key handler
            $(document).on('keydown.modal-escape', (e) => {
                if (e.key === 'Escape') {
                    const $visibleModal = $('.wps-modal:visible');
                    if ($visibleModal.length > 0) {
                        this.handleModalClose($visibleModal);
                    }
                }
            });
        },

        handleModalClose: function($modal) {
            const modalId = $modal.attr('id');
            
            if (modalId === this.config.config.modal_id) {
                this.closeActionModal();
            } else {
                $modal.hide();
            }
        },

        /**
         * Button Handlers
         */
        initializeButtonHandlers: function() {
            // Remove existing handlers to prevent duplicates
            $(document).off('click.review-permit click.approve-permit');

            // Main action button (review/approve)
            $(document).on('click.review-permit click.approve-permit', `.${this.config.config.action_button_class}`, (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.handleButtonClick(e.target, 'action');
            });
        },

        handleButtonClick: function(button, type) {
            const $button = $(button);
            
            // Prevent multiple clicks
            if ($button.hasClass('loading')) {
                return false;
            }

            if (type === 'action') {
                this.handleActionButtonClick(button);
            }

            return false;
        },

        /**
         * Utility Functions
         */
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
                    .text(originalText || 'Action');
            }
        },

        applyFilters: function() {
            $('#search-filter-form').trigger('submit');
        }
    };

})(jQuery);