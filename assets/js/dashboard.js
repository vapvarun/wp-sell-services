/**
 * WP Sell Services - Vendor Dashboard JavaScript
 *
 * Dashboard functionality for vendor management interface.
 *
 * @package WPSellServices
 * @since   1.0.0
 */

(function($) {
    'use strict';

    const WPSSDashboard = {
        /**
         * Configuration.
         */
        config: {
            container: '.wpss-dashboard',
            navigation: '.wpss-dashboard-nav',
            content: '.wpss-dashboard-content',
            statsContainer: '.wpss-stats-overview',
            chartContainer: '.wpss-chart-container',
            ordersTable: '.wpss-orders-table',
            servicesGrid: '.wpss-services-grid',
            dateRangePicker: '.wpss-date-range'
        },

        /**
         * State.
         */
        state: {
            currentTab: 'overview',
            dateRange: 'month',
            charts: {},
            loading: false
        },

        /**
         * Initialize dashboard.
         */
        init: function() {
            const $container = $(this.config.container);

            if (!$container.length) {
                return;
            }

            this.bindEvents();
            this.initCharts();
            this.initDateRangePicker();
            this.loadInitialData();
        },

        /**
         * Bind event handlers.
         */
        bindEvents: function() {
            const self = this;

            // Tab navigation.
            $(document).on('click', this.config.navigation + ' a', function(e) {
                e.preventDefault();
                self.switchTab($(this).data('tab'));
            });

            // Date range change.
            $(document).on('change', this.config.dateRangePicker, function() {
                self.state.dateRange = $(this).val();
                self.refreshStats();
            });

            // Service actions.
            $(document).on('click', '.wpss-service-action', function(e) {
                e.preventDefault();
                self.handleServiceAction($(this));
            });

            // Order actions.
            $(document).on('click', '.wpss-order-action', function(e) {
                e.preventDefault();
                self.handleOrderAction($(this));
            });

            // Quick filters.
            $(document).on('click', '.wpss-filter-btn', function(e) {
                e.preventDefault();
                $(this).siblings().removeClass('active');
                $(this).addClass('active');
                self.applyFilter($(this).data('filter'));
            });

            // Bulk actions.
            $(document).on('click', '.wpss-bulk-action-btn', function(e) {
                e.preventDefault();
                self.handleBulkAction();
            });

            // Select all checkbox.
            $(document).on('change', '.wpss-select-all', function() {
                $('.wpss-select-item').prop('checked', $(this).prop('checked'));
                self.updateBulkActionVisibility();
            });

            // Individual checkboxes.
            $(document).on('change', '.wpss-select-item', function() {
                self.updateBulkActionVisibility();
            });

            // Search functionality.
            $(document).on('input', '.wpss-dashboard-search', function() {
                self.handleSearch($(this).val());
            });

            // Pagination.
            $(document).on('click', '.wpss-pagination a', function(e) {
                e.preventDefault();
                self.loadPage($(this).data('page'));
            });

            // Refresh stats button.
            $(document).on('click', '.wpss-refresh-stats', function(e) {
                e.preventDefault();
                self.refreshStats();
            });

            // Export data.
            $(document).on('click', '.wpss-export-btn', function(e) {
                e.preventDefault();
                self.exportData($(this).data('type'));
            });
        },

        /**
         * Switch dashboard tab.
         */
        switchTab: function(tab) {
            const self = this;

            if (this.state.loading || tab === this.state.currentTab) {
                return;
            }

            this.state.currentTab = tab;

            // Update navigation.
            $(this.config.navigation + ' a').removeClass('active');
            $(this.config.navigation + ' a[data-tab="' + tab + '"]').addClass('active');

            // Update URL without reload.
            if (history.pushState) {
                const url = new URL(window.location);
                url.searchParams.set('tab', tab);
                history.pushState({tab: tab}, '', url);
            }

            // Load tab content.
            this.loadTabContent(tab);
        },

        /**
         * Load tab content.
         */
        loadTabContent: function(tab) {
            const self = this;
            const $content = $(this.config.content);

            this.state.loading = true;
            $content.addClass('loading');

            $.ajax({
                url: wpssData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpss_get_dashboard_tab',
                    tab: tab,
                    nonce: wpssData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $content.html(response.data.html);

                        // Re-initialize components.
                        if (tab === 'overview') {
                            self.initCharts();
                        }

                        // Trigger event for extensions.
                        $(document).trigger('wpss_tab_loaded', [tab, response.data]);
                    } else {
                        self.showNotice(response.data.message || 'Failed to load content', 'error');
                    }
                },
                error: function() {
                    self.showNotice('Network error. Please try again.', 'error');
                },
                complete: function() {
                    self.state.loading = false;
                    $content.removeClass('loading');
                }
            });
        },

        /**
         * Initialize charts.
         */
        initCharts: function() {
            const self = this;

            // Only initialize if Chart.js is available.
            if (typeof Chart === 'undefined') {
                return;
            }

            // Earnings chart.
            const $earningsCanvas = $('#wpss-earnings-chart');
            if ($earningsCanvas.length) {
                this.state.charts.earnings = new Chart($earningsCanvas[0].getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: [],
                        datasets: [{
                            label: 'Earnings',
                            data: [],
                            borderColor: '#4f46e5',
                            backgroundColor: 'rgba(79, 70, 229, 0.1)',
                            fill: true,
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return self.formatCurrency(value);
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // Orders chart.
            const $ordersCanvas = $('#wpss-orders-chart');
            if ($ordersCanvas.length) {
                this.state.charts.orders = new Chart($ordersCanvas[0].getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: [],
                        datasets: [{
                            label: 'Orders',
                            data: [],
                            backgroundColor: '#10b981',
                            borderRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        }
                    }
                });
            }

            // Status distribution chart.
            const $statusCanvas = $('#wpss-status-chart');
            if ($statusCanvas.length) {
                this.state.charts.status = new Chart($statusCanvas[0].getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: ['Active', 'In Progress', 'Completed', 'Cancelled'],
                        datasets: [{
                            data: [0, 0, 0, 0],
                            backgroundColor: ['#10b981', '#f59e0b', '#4f46e5', '#ef4444']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            }
        },

        /**
         * Initialize date range picker.
         */
        initDateRangePicker: function() {
            const $picker = $(this.config.dateRangePicker);
            if (!$picker.length) {
                return;
            }

            // Set initial value.
            $picker.val(this.state.dateRange);
        },

        /**
         * Load initial dashboard data.
         */
        loadInitialData: function() {
            this.refreshStats();
        },

        /**
         * Refresh statistics.
         */
        refreshStats: function() {
            const self = this;

            $.ajax({
                url: wpssData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpss_get_dashboard_stats',
                    range: this.state.dateRange,
                    nonce: wpssData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.updateStats(response.data);
                    }
                }
            });
        },

        /**
         * Update stats display.
         */
        updateStats: function(data) {
            const self = this;

            // Update stat cards.
            if (data.stats) {
                Object.keys(data.stats).forEach(function(key) {
                    const $stat = $('.wpss-stat-card[data-stat="' + key + '"]');
                    if ($stat.length) {
                        $stat.find('.wpss-stat-value').text(data.stats[key].value);

                        const $change = $stat.find('.wpss-stat-change');
                        if ($change.length && data.stats[key].change !== undefined) {
                            const change = data.stats[key].change;
                            $change.text((change >= 0 ? '+' : '') + change + '%')
                                   .toggleClass('positive', change >= 0)
                                   .toggleClass('negative', change < 0);
                        }
                    }
                });
            }

            // Update charts.
            if (data.charts) {
                if (data.charts.earnings && this.state.charts.earnings) {
                    this.state.charts.earnings.data.labels = data.charts.earnings.labels;
                    this.state.charts.earnings.data.datasets[0].data = data.charts.earnings.data;
                    this.state.charts.earnings.update();
                }

                if (data.charts.orders && this.state.charts.orders) {
                    this.state.charts.orders.data.labels = data.charts.orders.labels;
                    this.state.charts.orders.data.datasets[0].data = data.charts.orders.data;
                    this.state.charts.orders.update();
                }

                if (data.charts.status && this.state.charts.status) {
                    this.state.charts.status.data.datasets[0].data = data.charts.status.data;
                    this.state.charts.status.update();
                }
            }
        },

        /**
         * Handle service action.
         */
        handleServiceAction: function($btn) {
            const self = this;
            const action = $btn.data('action');
            const serviceId = $btn.closest('[data-service-id]').data('service-id');

            if (!serviceId) {
                return;
            }

            // Confirmation for destructive actions.
            if (['delete', 'unpublish'].includes(action)) {
                if (!confirm('Are you sure you want to ' + action + ' this service?')) {
                    return;
                }
            }

            $btn.prop('disabled', true).addClass('loading');

            $.ajax({
                url: wpssData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpss_service_action',
                    service_action: action,
                    service_id: serviceId,
                    nonce: wpssData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotice(response.data.message, 'success');

                        // Update UI based on action.
                        if (action === 'delete') {
                            $btn.closest('[data-service-id]').fadeOut(function() {
                                $(this).remove();
                            });
                        } else {
                            self.refreshCurrentView();
                        }
                    } else {
                        self.showNotice(response.data.message || 'Action failed', 'error');
                    }
                },
                error: function() {
                    self.showNotice('Network error. Please try again.', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).removeClass('loading');
                }
            });
        },

        /**
         * Handle order action.
         */
        handleOrderAction: function($btn) {
            const self = this;
            const action = $btn.data('action');
            const orderId = $btn.closest('[data-order-id]').data('order-id');

            if (!orderId) {
                return;
            }

            // Some actions need confirmation.
            if (['cancel', 'refund'].includes(action)) {
                if (!confirm('Are you sure you want to ' + action + ' this order?')) {
                    return;
                }
            }

            $btn.prop('disabled', true).addClass('loading');

            $.ajax({
                url: wpssData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpss_order_action',
                    order_action: action,
                    order_id: orderId,
                    nonce: wpssData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotice(response.data.message, 'success');
                        self.refreshCurrentView();
                    } else {
                        self.showNotice(response.data.message || 'Action failed', 'error');
                    }
                },
                error: function() {
                    self.showNotice('Network error. Please try again.', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).removeClass('loading');
                }
            });
        },

        /**
         * Apply filter.
         */
        applyFilter: function(filter) {
            const self = this;
            const $container = $(this.config.content);

            $container.addClass('loading');

            $.ajax({
                url: wpssData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpss_filter_dashboard',
                    tab: this.state.currentTab,
                    filter: filter,
                    nonce: wpssData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $container.find('.wpss-filterable-content').html(response.data.html);
                    }
                },
                complete: function() {
                    $container.removeClass('loading');
                }
            });
        },

        /**
         * Handle bulk action.
         */
        handleBulkAction: function() {
            const self = this;
            const action = $('.wpss-bulk-action-select').val();
            const selectedIds = [];

            $('.wpss-select-item:checked').each(function() {
                selectedIds.push($(this).val());
            });

            if (!action || selectedIds.length === 0) {
                this.showNotice('Please select items and an action.', 'warning');
                return;
            }

            if (!confirm('Apply this action to ' + selectedIds.length + ' items?')) {
                return;
            }

            $.ajax({
                url: wpssData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpss_bulk_action',
                    bulk_action: action,
                    ids: selectedIds,
                    type: this.state.currentTab,
                    nonce: wpssData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotice(response.data.message, 'success');
                        self.refreshCurrentView();
                    } else {
                        self.showNotice(response.data.message || 'Bulk action failed', 'error');
                    }
                },
                error: function() {
                    self.showNotice('Network error. Please try again.', 'error');
                }
            });
        },

        /**
         * Update bulk action visibility.
         */
        updateBulkActionVisibility: function() {
            const checkedCount = $('.wpss-select-item:checked').length;
            const $bulkBar = $('.wpss-bulk-actions-bar');

            if (checkedCount > 0) {
                $bulkBar.addClass('visible');
                $bulkBar.find('.wpss-selected-count').text(checkedCount);
            } else {
                $bulkBar.removeClass('visible');
            }
        },

        /**
         * Handle search.
         */
        handleSearch: function(query) {
            const self = this;

            // Debounce search.
            clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(function() {
                self.performSearch(query);
            }, 300);
        },

        /**
         * Perform search.
         */
        performSearch: function(query) {
            const self = this;

            if (query.length < 2 && query.length > 0) {
                return;
            }

            const $container = $(this.config.content).find('.wpss-filterable-content');
            $container.addClass('loading');

            $.ajax({
                url: wpssData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpss_search_dashboard',
                    tab: this.state.currentTab,
                    query: query,
                    nonce: wpssData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $container.html(response.data.html);
                    }
                },
                complete: function() {
                    $container.removeClass('loading');
                }
            });
        },

        /**
         * Load page.
         */
        loadPage: function(page) {
            const self = this;
            const $container = $(this.config.content).find('.wpss-filterable-content');

            $container.addClass('loading');

            $.ajax({
                url: wpssData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpss_paginate_dashboard',
                    tab: this.state.currentTab,
                    page: page,
                    nonce: wpssData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $container.html(response.data.html);

                        // Scroll to top of content.
                        $('html, body').animate({
                            scrollTop: $(self.config.content).offset().top - 50
                        }, 300);
                    }
                },
                complete: function() {
                    $container.removeClass('loading');
                }
            });
        },

        /**
         * Refresh current view.
         */
        refreshCurrentView: function() {
            this.loadTabContent(this.state.currentTab);
        },

        /**
         * Export data.
         */
        exportData: function(type) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = wpssData.ajaxUrl;

            const fields = {
                action: 'wpss_export_data',
                type: type,
                tab: this.state.currentTab,
                range: this.state.dateRange,
                nonce: wpssData.nonce
            };

            Object.keys(fields).forEach(function(key) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = fields[key];
                form.appendChild(input);
            });

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        },

        /**
         * Show notice.
         */
        showNotice: function(message, type) {
            type = type || 'info';

            const $notice = $('<div class="wpss-notice wpss-notice-' + type + '">' +
                '<span class="message">' + this.escapeHtml(message) + '</span>' +
                '<button type="button" class="dismiss">&times;</button>' +
                '</div>');

            // Remove existing notices.
            $('.wpss-notice').remove();

            // Add new notice.
            $(this.config.container).prepend($notice);

            // Auto dismiss.
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);

            // Manual dismiss.
            $notice.find('.dismiss').on('click', function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            });
        },

        /**
         * Format currency.
         */
        formatCurrency: function(amount) {
            if (typeof wpssData.currencyFormat !== 'undefined') {
                return wpssData.currencyFormat.replace('%s', parseFloat(amount).toFixed(2));
            }
            return '$' + parseFloat(amount).toFixed(2);
        },

        /**
         * Escape HTML.
         */
        escapeHtml: function(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    /**
     * Service Editor Module.
     */
    const WPSSServiceEditor = {
        /**
         * Initialize service editor.
         */
        init: function() {
            if (!$('.wpss-service-editor').length) {
                return;
            }

            this.bindEvents();
            this.initPackages();
            this.initImageUpload();
            this.initFaq();
        },

        /**
         * Bind events.
         */
        bindEvents: function() {
            const self = this;

            // Add package.
            $(document).on('click', '.wpss-add-package', function(e) {
                e.preventDefault();
                self.addPackage();
            });

            // Remove package.
            $(document).on('click', '.wpss-remove-package', function(e) {
                e.preventDefault();
                self.removePackage($(this));
            });

            // Add FAQ.
            $(document).on('click', '.wpss-add-faq', function(e) {
                e.preventDefault();
                self.addFaq();
            });

            // Remove FAQ.
            $(document).on('click', '.wpss-remove-faq', function(e) {
                e.preventDefault();
                $(this).closest('.wpss-faq-item').remove();
            });

            // Form submission.
            $(document).on('submit', '.wpss-service-form', function(e) {
                e.preventDefault();
                self.saveService($(this));
            });
        },

        /**
         * Initialize packages section.
         */
        initPackages: function() {
            // Make packages sortable if sortable is available.
            if ($.fn.sortable) {
                $('.wpss-packages-list').sortable({
                    handle: '.wpss-package-handle',
                    update: function() {
                        WPSSServiceEditor.updatePackageOrder();
                    }
                });
            }
        },

        /**
         * Add package.
         */
        addPackage: function() {
            const $list = $('.wpss-packages-list');
            const index = $list.children().length;

            const html = this.getPackageTemplate(index);
            $list.append(html);
        },

        /**
         * Get package template.
         */
        getPackageTemplate: function(index) {
            return `
                <div class="wpss-package-item" data-index="${index}">
                    <div class="wpss-package-header">
                        <span class="wpss-package-handle">&#9776;</span>
                        <input type="text" name="packages[${index}][name]" placeholder="Package Name" required>
                        <button type="button" class="wpss-remove-package">&times;</button>
                    </div>
                    <div class="wpss-package-body">
                        <div class="wpss-field">
                            <label>Description</label>
                            <textarea name="packages[${index}][description]" rows="3"></textarea>
                        </div>
                        <div class="wpss-field-row">
                            <div class="wpss-field">
                                <label>Price</label>
                                <input type="number" name="packages[${index}][price]" step="0.01" min="0" required>
                            </div>
                            <div class="wpss-field">
                                <label>Delivery (days)</label>
                                <input type="number" name="packages[${index}][delivery_days]" min="1" required>
                            </div>
                            <div class="wpss-field">
                                <label>Revisions</label>
                                <input type="number" name="packages[${index}][revisions]" min="0">
                            </div>
                        </div>
                        <div class="wpss-field">
                            <label>What's Included</label>
                            <div class="wpss-package-features">
                                <input type="text" name="packages[${index}][features][]" placeholder="Feature 1">
                            </div>
                            <button type="button" class="wpss-add-feature">+ Add Feature</button>
                        </div>
                    </div>
                </div>
            `;
        },

        /**
         * Remove package.
         */
        removePackage: function($btn) {
            if ($('.wpss-package-item').length <= 1) {
                WPSSDashboard.showNotice('At least one package is required.', 'warning');
                return;
            }

            $btn.closest('.wpss-package-item').remove();
            this.updatePackageOrder();
        },

        /**
         * Update package order.
         */
        updatePackageOrder: function() {
            $('.wpss-package-item').each(function(index) {
                $(this).attr('data-index', index);
                $(this).find('input, textarea').each(function() {
                    const name = $(this).attr('name');
                    if (name) {
                        $(this).attr('name', name.replace(/\[\d+\]/, '[' + index + ']'));
                    }
                });
            });
        },

        /**
         * Initialize image upload.
         */
        initImageUpload: function() {
            const self = this;

            // Click to upload.
            $(document).on('click', '.wpss-upload-btn', function(e) {
                e.preventDefault();
                $(this).siblings('input[type="file"]').click();
            });

            // File selected.
            $(document).on('change', '.wpss-image-upload input[type="file"]', function() {
                self.handleImageUpload($(this));
            });

            // Remove image.
            $(document).on('click', '.wpss-image-remove', function(e) {
                e.preventDefault();
                self.removeImage($(this));
            });

            // Make images sortable.
            if ($.fn.sortable) {
                $('.wpss-images-list').sortable({
                    update: function() {
                        self.updateImageOrder();
                    }
                });
            }
        },

        /**
         * Handle image upload.
         */
        handleImageUpload: function($input) {
            const files = $input[0].files;
            const $container = $input.closest('.wpss-image-upload');
            const $list = $container.find('.wpss-images-list');
            const maxImages = parseInt($container.data('max') || 5);

            if ($list.children().length + files.length > maxImages) {
                WPSSDashboard.showNotice('Maximum ' + maxImages + ' images allowed.', 'warning');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'wpss_upload_service_image');
            formData.append('nonce', wpssData.nonce);

            for (let i = 0; i < files.length; i++) {
                formData.append('images[]', files[i]);
            }

            $.ajax({
                url: wpssData.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success && response.data.images) {
                        response.data.images.forEach(function(image) {
                            const html = `
                                <div class="wpss-image-item" data-id="${image.id}">
                                    <img src="${image.url}" alt="">
                                    <input type="hidden" name="gallery[]" value="${image.id}">
                                    <button type="button" class="wpss-image-remove">&times;</button>
                                </div>
                            `;
                            $list.append(html);
                        });
                    } else {
                        WPSSDashboard.showNotice(response.data.message || 'Upload failed', 'error');
                    }
                },
                error: function() {
                    WPSSDashboard.showNotice('Upload failed. Please try again.', 'error');
                }
            });

            // Reset input.
            $input.val('');
        },

        /**
         * Remove image.
         */
        removeImage: function($btn) {
            $btn.closest('.wpss-image-item').remove();
            this.updateImageOrder();
        },

        /**
         * Update image order.
         */
        updateImageOrder: function() {
            // Order is maintained by DOM order; hidden inputs handle submission.
        },

        /**
         * Initialize FAQ section.
         */
        initFaq: function() {
            // Collapsible FAQ items.
            $(document).on('click', '.wpss-faq-item .wpss-faq-question', function() {
                $(this).closest('.wpss-faq-item').toggleClass('expanded');
            });
        },

        /**
         * Add FAQ.
         */
        addFaq: function() {
            const $list = $('.wpss-faq-list');
            const index = $list.children().length;

            const html = `
                <div class="wpss-faq-item">
                    <div class="wpss-faq-question">
                        <input type="text" name="faq[${index}][question]" placeholder="Question" required>
                        <button type="button" class="wpss-remove-faq">&times;</button>
                    </div>
                    <div class="wpss-faq-answer">
                        <textarea name="faq[${index}][answer]" placeholder="Answer" rows="3" required></textarea>
                    </div>
                </div>
            `;

            $list.append(html);
        },

        /**
         * Save service.
         */
        saveService: function($form) {
            const self = this;
            const $btn = $form.find('button[type="submit"]');

            $btn.prop('disabled', true).addClass('loading');

            const formData = new FormData($form[0]);
            formData.append('action', 'wpss_save_service');
            formData.append('nonce', wpssData.nonce);

            $.ajax({
                url: wpssData.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        WPSSDashboard.showNotice(response.data.message || 'Service saved successfully!', 'success');

                        // Redirect if new service.
                        if (response.data.redirect) {
                            window.location.href = response.data.redirect;
                        }
                    } else {
                        WPSSDashboard.showNotice(response.data.message || 'Save failed', 'error');
                    }
                },
                error: function() {
                    WPSSDashboard.showNotice('Network error. Please try again.', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).removeClass('loading');
                }
            });
        }
    };

    /**
     * Withdrawal Module.
     */
    const WPSSWithdrawals = {
        /**
         * Initialize withdrawals.
         */
        init: function() {
            if (!$('.wpss-withdrawals').length) {
                return;
            }

            this.bindEvents();
        },

        /**
         * Bind events.
         */
        bindEvents: function() {
            const self = this;

            // Request withdrawal.
            $(document).on('submit', '.wpss-withdrawal-form', function(e) {
                e.preventDefault();
                self.requestWithdrawal($(this));
            });

            // Cancel withdrawal.
            $(document).on('click', '.wpss-cancel-withdrawal', function(e) {
                e.preventDefault();
                if (confirm('Cancel this withdrawal request?')) {
                    self.cancelWithdrawal($(this).data('id'));
                }
            });
        },

        /**
         * Request withdrawal.
         */
        requestWithdrawal: function($form) {
            const $btn = $form.find('button[type="submit"]');

            $btn.prop('disabled', true).addClass('loading');

            $.ajax({
                url: wpssData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpss_request_withdrawal',
                    amount: $form.find('[name="amount"]').val(),
                    method: $form.find('[name="method"]').val(),
                    nonce: wpssData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WPSSDashboard.showNotice(response.data.message, 'success');
                        WPSSDashboard.refreshCurrentView();
                    } else {
                        WPSSDashboard.showNotice(response.data.message || 'Request failed', 'error');
                    }
                },
                error: function() {
                    WPSSDashboard.showNotice('Network error. Please try again.', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).removeClass('loading');
                }
            });
        },

        /**
         * Cancel withdrawal.
         */
        cancelWithdrawal: function(id) {
            $.ajax({
                url: wpssData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpss_cancel_withdrawal',
                    withdrawal_id: id,
                    nonce: wpssData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WPSSDashboard.showNotice('Withdrawal cancelled.', 'success');
                        WPSSDashboard.refreshCurrentView();
                    } else {
                        WPSSDashboard.showNotice(response.data.message || 'Cancel failed', 'error');
                    }
                }
            });
        }
    };

    // Initialize on document ready.
    $(document).ready(function() {
        WPSSDashboard.init();
        WPSSServiceEditor.init();
        WPSSWithdrawals.init();
    });

    // Handle browser back/forward.
    $(window).on('popstate', function(e) {
        if (e.originalEvent.state && e.originalEvent.state.tab) {
            WPSSDashboard.switchTab(e.originalEvent.state.tab);
        }
    });

    // Expose globally.
    window.WPSSDashboard = WPSSDashboard;
    window.WPSSServiceEditor = WPSSServiceEditor;
    window.WPSSWithdrawals = WPSSWithdrawals;

})(jQuery);
