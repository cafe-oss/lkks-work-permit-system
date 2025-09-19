/**
 * Truly Unified Dashboard Filters and Pagination JavaScript
 * File: assets/js/unified-dashboard-filters.js
 * COMPLETE REWRITE: All pagination logic centralized, same HTML/CSS classes for all dashboards
 */

(function($) {
    'use strict';

    // Global Filters Object
    window.WPS_DashboardFilters = {
        config: {
            isAdminDashboard: false,
            dashboardType: 'reviewer',
            ajaxUrl: '',
            nonce: '',
            formSelector: '#filters-form',
            searchInputSelector: '#search-input',
        },
        
        searchTimeout: null,
        suggestionsCache: {},

        /**
         * Initialize with unified approach
         */
        init: function(options) {
            this.config = $.extend(this.config, options);
            this.config.isAdminDashboard = (this.config.dashboardType === 'admin');
            
            // Set form selector based on dashboard type
            this.config.formSelector = this.config.isAdminDashboard ? '#filters-form' : '#search-filter-form';

            this.initializeFilterForm();
            this.initializeSearchSuggestions();
            this.initializeUnifiedPagination(); // UNIFIED pagination only
            this.initializeToggleFilters();
            this.initializeKeyboardShortcuts();

        },

        /**
         * Filter form initialization
         */
        initializeFilterForm: function() {
            // Main form submission
            $(this.config.formSelector).on('submit', (e) => {
                e.preventDefault();
                this.handleFormSubmit(e.target);
            });

            // Filter change handlers
            const filterSelectors = [
                '#status-filter',
                '#category-filter',
                '#work-category-filter',
                '#per-page',
                '#date-from',
                '#date-to',
                '#reviewer-filter'
            ];

            filterSelectors.forEach(selector => {
                $(document).on('change', selector, () => {
                    $(this.config.formSelector).trigger('submit');
                });
            });

            // Clear filters
            $('.clear-button').on('click', (e) => {
                e.preventDefault();
                this.clearAllFilters();
            });
        },

        /**
         * Enhanced form submission with proper parameter mapping
         */
        handleFormSubmit: function(form) {
            
            const formData = new FormData(form);
            const url = new URL(window.location);
            
            // Preserve page and tab
            const currentPage = url.searchParams.get('page');
            const currentTab = url.searchParams.get('tab');
            url.search = '';
            
            if (currentPage) url.searchParams.set('page', currentPage);
            if (currentTab) url.searchParams.set('tab', currentTab);

            // Add form data with consistent parameter mapping
            for (let [key, value] of formData.entries()) {
                if (value && value !== 'all' && value !== '') {
                    const paramName = this.mapParameterName(key);
                    url.searchParams.set(paramName, value);
                }
            }

            // Reset to page 1 when filtering
            url.searchParams.set('paged', '1');
            
            window.location.href = url.toString();
        },

        /**
         * Consistent parameter mapping across all dashboards
         */
        mapParameterName: function(key) {
            if (this.config.isAdminDashboard) {
                // Admin parameter mapping
                const adminMapping = {
                    'status_filter': 'status',
                    'work_category': 'category'
                };
                return adminMapping[key] || key;
            } else {
                // Unified dashboard parameter mapping
                const unifiedMapping = {
                    'status': 'status_filter',
                    'category': 'work_category'
                };
                return unifiedMapping[key] || key;
            }
        },

        /**
         * Clear all filters
         */
        clearAllFilters: function() {
            const url = new URL(window.location);
            const page = url.searchParams.get('page');
            const tab = url.searchParams.get('tab');
            
            url.search = '';
            if (page) url.searchParams.set('page', page);
            if (tab) url.searchParams.set('tab', tab);
            
            window.location.href = url.toString();
        },

        /**
         * Search suggestions (for reviewer/approver only)
         */
        initializeSearchSuggestions: function() {
            if (this.config.isAdminDashboard) {
                return; // Admin doesn't use search suggestions
            }

            const $searchInput = $(this.config.searchInputSelector);
            const $searchType = $('#search-type');

            $searchInput.on('input', () => {
                const query = $searchInput.val().trim();
                const type = $searchType.val();

                clearTimeout(this.searchTimeout);

                if (query.length >= 2) {
                    this.searchTimeout = setTimeout(() => {
                        this.loadSearchSuggestions(query, type);
                    }, 300);
                } else {
                    this.hideSuggestions();
                }
            });

            this.bindSuggestionEvents($searchInput);
        },

        bindSuggestionEvents: function($searchInput) {
            $(document).on('click', '.suggestion-item', function() {
                $searchInput.val($(this).text());
                WPS_DashboardFilters.hideSuggestions();
                $(WPS_DashboardFilters.config.formSelector).trigger('submit');
            });

            $(document).on('click', (e) => {
                if (!$(e.target).closest('.search-input-wrapper').length) {
                    this.hideSuggestions();
                }
            });

            $searchInput.on('keydown', (e) => this.handleSuggestionKeyboard(e, $searchInput));
        },

        handleSuggestionKeyboard: function(e, $searchInput) {
            const $suggestions = $('.suggestion-item:visible');
            const $active = $('.suggestion-item.active');

            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    if ($active.length === 0) {
                        $suggestions.first().addClass('active');
                    } else {
                        $active.removeClass('active').next().addClass('active');
                    }
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    if ($active.length > 0) {
                        $active.removeClass('active').prev().addClass('active');
                    }
                    break;
                case 'Enter':
                    if ($active.length > 0) {
                        e.preventDefault();
                        $searchInput.val($active.text());
                        this.hideSuggestions();
                        $(this.config.formSelector).trigger('submit');
                    }
                    break;
                case 'Escape':
                    this.hideSuggestions();
                    break;
            }
        },

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

        showSuggestions: function(suggestions) {
            const $container = $(this.config.suggestionsSelector);
            $container.empty();

            suggestions.forEach(suggestion => {
                $container.append($('<div class="suggestion-item"></div>').text(suggestion));
            });

            $container.addClass('show');
        },

        hideSuggestions: function() {
            $(this.config.suggestionsSelector).removeClass('show').empty();
            $('.suggestion-item').removeClass('active');
        },

        /**
         * UNIFIED PAGINATION - Same logic for all dashboard types
         */
        initializeUnifiedPagination: function() {

            // Single pagination handler for all dashboard types
            $(document).on('click', '.pagination-links a', (e) => {
                const $link = $(e.target).closest('a');
                
                // Skip if disabled, current, or dots
                if ($link.hasClass('current') || $link.hasClass('disabled') || $link.hasClass('dots')) {
                    e.preventDefault();
                    return false;
                }

                // Add loading state
                if (!$link.hasClass('loading')) {
                    $link.addClass('loading');
                    const originalText = $link.text();
                    $link.text('Loading...');
                    
                    // Restore text after delay (in case user goes back)
                    setTimeout(() => {
                        $link.removeClass('loading').text(originalText);
                    }, 3000);
                }

                // Let the browser navigate normally
            });

            // Page jump functionality
            $(document).on('click', '.pagination-jump-btn', () => {
                this.jumpToPage();
            });

            $(document).on('keypress', '#page-jump', (e) => {
                if (e.key === 'Enter') {
                    this.jumpToPage();
                }
            });

            // Keyboard shortcuts for pagination
            this.bindUnifiedPaginationKeyboardShortcuts();

        },

        /**
         * Page jump functionality
         */
        jumpToPage: function() {
            const $pageInput = $('#page-jump');
            const page = parseInt($pageInput.val());
            const maxPages = parseInt($pageInput.attr('max'));

            if (page >= 1 && page <= maxPages) {
                const url = new URL(window.location);
                url.searchParams.set('paged', page);
                window.location.href = url.toString();
            } else {
                alert('Please enter a valid page number between 1 and ' + maxPages);
                $pageInput.focus();
            }
        },

        /**
         * Unified keyboard shortcuts for pagination
         */
        bindUnifiedPaginationKeyboardShortcuts: function() {
            $(document).on('keydown', (e) => {
                // Only work with Ctrl/Cmd and not in input fields
                if (!e.ctrlKey && !e.metaKey) return;
                if ($(e.target).is('input, textarea, select')) return;

                let $link = null;
                
                if (e.key === 'ArrowLeft') {
                    $link = $('.pagination-links a:contains("Previous")');
                } else if (e.key === 'ArrowRight') {
                    $link = $('.pagination-links a:contains("Next")');
                }

                if ($link && $link.length && !$link.hasClass('disabled')) {
                    e.preventDefault();
                    window.location.href = $link.attr('href');
                }
            });
        },

        /**
         * Filter toggle functionality
         */
        initializeToggleFilters: function() {
            $('.filter-button').on('click', function() {
                const $button = $(this);
                const $filterContainer = $('.filter-container');

                $filterContainer.slideToggle(300);
                $button.toggleClass('active');

                const isVisible = $filterContainer.is(':visible');
                const buttonText = isVisible ? 'Hide Filters' : 'Show Filters';
                $button.find('svg').next().text(buttonText);
            });

            // Auto-show filters if any are active
            if (this.checkForActiveFilters()) {
                $('.filter-button').addClass('active');
                $('.filter-button').closest('.filter-row').next('.filter-row').show();
                $('.filter-button svg').next().text('Hide Filters');
            }
        },

        /**
         * Check for active filters
         */
        checkForActiveFilters: function() {
            const urlParams = new URLSearchParams(window.location.search);
            
            const filterParams = this.config.isAdminDashboard ? 
                ['status', 'category', 'search', 'search_type', 'date_from', 'date_to', 'per_page'] :
                ['status_filter', 'work_category', 'search', 'search_type', 'reviewer', 'date_from', 'date_to', 'per_page'];

            return filterParams.some(param => {
                const value = urlParams.get(param);
                return value && value !== '' && value !== 'all' && value !== '10';
            });
        },

        /**
         * Keyboard shortcuts
         */
        initializeKeyboardShortcuts: function() {
            $(document).on('keydown', (e) => {
                if ($(e.target).is('input, textarea, select')) return;

                // Ctrl/Cmd + F to focus search
                if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                    e.preventDefault();
                    $(this.config.searchInputSelector).focus();
                }

                // Ctrl/Cmd + Shift + R to clear filters
                if ((e.ctrlKey || e.metaKey) && e.key === 'r' && e.shiftKey) {
                    e.preventDefault();
                    this.clearAllFilters();
                }
            });
        }
    };

    // Auto-initialization
    $(document).ready(function() {
        
        const $body = $('body');
        const isReviewerDashboard = $body.hasClass('toplevel_page_wps-reviewer-dashboard');
        const isApproverDashboard = $body.hasClass('toplevel_page_wps-approver-dashboard');
        const isAdminDashboard = $body.hasClass('toplevel_page_work-permits');

        // Fallback: check URL parameter
        const urlParams = new URLSearchParams(window.location.search);
        const pageParam = urlParams.get('page');
        
        let dashboardType = null;
        
        if (isReviewerDashboard || pageParam === 'wps-reviewer-dashboard') {
            dashboardType = 'reviewer';
        } else if (isApproverDashboard || pageParam === 'wps-approver-dashboard') {
            dashboardType = 'approver';
        } else if (isAdminDashboard || pageParam === 'work-permits') {
            dashboardType = 'admin';
        }

        if (dashboardType) {
            
            // Get configuration from localized variables
            const ajaxUrl = window.wps_dashboard_vars?.ajax_url || 
                           window.wps_admin_vars?.ajax_url || 
                           window.wps_filters_vars?.ajax_url || 
                           ajaxurl;
                           
            const nonce = window.wps_dashboard_vars?.user_nonce || 
                         window.wps_admin_vars?.nonce || 
                         window.wps_filters_vars?.nonce || 
                         '';

            // Initialize with unified config
            WPS_DashboardFilters.init({
                dashboardType: dashboardType,
                ajaxUrl: ajaxUrl,
                nonce: nonce
            });
            
        } else {
            console.log('WPS Dashboard Filters: No matching dashboard detected');
        }
    });

})(jQuery);