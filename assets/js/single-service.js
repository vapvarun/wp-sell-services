/**
 * WP Sell Services - Single Service Page JavaScript
 *
 * Handles all interactions on the single service page.
 *
 * @package WPSellServices
 * @since   1.0.0
 */

(function($) {
    'use strict';

    const WPSSService = {
        /**
         * Configuration.
         */
        config: {
            gallery: '.wpss-service-gallery',
            packages: '.wpss-packages-widget',
            faqs: '.wpss-service-faqs',
            reviews: '.wpss-service-reviews',
            orderModal: '#wpss-order-modal',
            contactModal: '#wpss-contact-modal'
        },

        /**
         * State.
         */
        state: {
            selectedPackage: 0,
            basePrice: 0,
            deliveryDays: 0,
            quantity: 1,
            extras: [],
            totalPrice: 0
        },

        /**
         * Initialize single service functionality.
         */
        init: function() {
            this.initGallery();
            this.initPackages();
            this.initFaqs();
            this.initReviews();
            this.initModals();
            this.initOrderForm();
            this.initContactForm();
            this.initStickyPackages();
        },

        /**
         * Initialize gallery functionality.
         */
        initGallery: function() {
            const self = this;
            const $gallery = $(this.config.gallery);

            if (!$gallery.length) {
                return;
            }

            // Thumbnail clicks.
            $gallery.on('click', '.wpss-gallery-thumb', function(e) {
                e.preventDefault();

                const $thumb = $(this);
                const $active = $gallery.find('.wpss-gallery-active');
                const src = $thumb.data('src');

                // Update active state.
                $gallery.find('.wpss-gallery-thumb').removeClass('active');
                $thumb.addClass('active');

                // Update main image.
                $active.find('img').attr('src', src);
            });

            // Lightbox for main image.
            $gallery.on('click', '.wpss-gallery-image', function(e) {
                e.preventDefault();
                self.openLightbox($(this).attr('src'));
            });

            // Keyboard navigation.
            $(document).on('keydown', function(e) {
                if (!$gallery.is(':visible')) {
                    return;
                }

                const $thumbs = $gallery.find('.wpss-gallery-thumb');
                const $active = $thumbs.filter('.active');
                const currentIndex = $thumbs.index($active);

                if (e.key === 'ArrowLeft' && currentIndex > 0) {
                    $thumbs.eq(currentIndex - 1).trigger('click');
                } else if (e.key === 'ArrowRight' && currentIndex < $thumbs.length - 1) {
                    $thumbs.eq(currentIndex + 1).trigger('click');
                }
            });
        },

        /**
         * Open lightbox.
         */
        openLightbox: function(src) {
            // If using a lightbox library.
            if (typeof lightbox !== 'undefined') {
                lightbox.start($(this.config.gallery + ' .wpss-gallery-image'));
                return;
            }

            // Simple lightbox fallback.
            const $lightbox = $('<div class="wpss-lightbox">' +
                '<button class="wpss-lightbox-close">&times;</button>' +
                '<img src="' + src + '" alt="">' +
                '</div>');

            $('body').append($lightbox);

            $lightbox.on('click', function(e) {
                if ($(e.target).hasClass('wpss-lightbox') || $(e.target).hasClass('wpss-lightbox-close')) {
                    $lightbox.remove();
                }
            });

            $(document).on('keydown.lightbox', function(e) {
                if (e.key === 'Escape') {
                    $lightbox.remove();
                    $(document).off('keydown.lightbox');
                }
            });
        },

        /**
         * Initialize packages functionality.
         */
        initPackages: function() {
            const self = this;
            const $packages = $(this.config.packages);

            if (!$packages.length) {
                return;
            }

            // Initialize selectedPackage from first active tab's data-package attribute.
            var $activeTab = $packages.find('.wpss-package-tab.active');
            if ($activeTab.length) {
                this.state.selectedPackage = $activeTab.data('package');
            }

            // Tab switching.
            $packages.on('click', '.wpss-package-tab', function(e) {
                e.preventDefault();

                const packageIndex = $(this).data('package');

                // Update tabs.
                $packages.find('.wpss-package-tab').removeClass('active');
                $(this).addClass('active');

                // Update content.
                $packages.find('.wpss-package').removeClass('active');
                $packages.find('.wpss-package[data-package="' + packageIndex + '"]').addClass('active');

                // Update state.
                self.state.selectedPackage = packageIndex;
            });

            // Order button click.
            $packages.on('click', '.wpss-order-btn', function(e) {
                e.preventDefault();

                const $btn = $(this);
                const packageIndex = $btn.data('package');
                const price = parseFloat($btn.data('price'));

                self.state.selectedPackage = packageIndex;
                self.state.basePrice = price;

                // Get delivery days from package.
                const $package = $packages.find('.wpss-package[data-package="' + packageIndex + '"]');
                const deliveryText = $package.find('.wpss-detail-value').first().text();
                const deliveryMatch = deliveryText.match(/\d+/);
                self.state.deliveryDays = deliveryMatch ? parseInt(deliveryMatch[0]) : 0;

                self.openOrderModal();
            });
        },

        /**
         * Initialize FAQs accordion.
         */
        initFaqs: function() {
            const $faqs = $(this.config.faqs);

            if (!$faqs.length) {
                return;
            }

            $faqs.on('click', '.wpss-faq-question', function(e) {
                e.preventDefault();

                const $question = $(this);
                const $item = $question.closest('.wpss-faq-item');
                const $answer = $item.find('.wpss-faq-answer');
                const isExpanded = $question.attr('aria-expanded') === 'true';

                // Toggle aria-expanded state.
                $question.attr('aria-expanded', !isExpanded);

                // Slide animation with hidden attribute management.
                if (isExpanded) {
                    // Collapsing: animate first, then hide.
                    $answer.slideUp(200, function() {
                        $(this).prop('hidden', true);
                    });
                } else {
                    // Expanding: remove hidden first, then animate.
                    $answer.prop('hidden', false).hide().slideDown(200);
                }
            });
        },

        /**
         * Initialize reviews functionality.
         */
        initReviews: function() {
            const self = this;
            const $reviews = $(this.config.reviews);

            if (!$reviews.length) {
                return;
            }

            // Load more reviews.
            $reviews.on('click', '.wpss-load-more-reviews', function(e) {
                e.preventDefault();

                const $btn = $(this);
                const serviceId = $btn.data('service');
                const page = parseInt($btn.data('page'));

                $btn.prop('disabled', true).text(wpssService.i18n.loading || 'Loading...');

                $.ajax({
                    url: wpssService.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wpss_load_reviews',
                        service_id: serviceId,
                        page: page,
                        nonce: wpssService.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $reviews.find('.wpss-reviews-list').append(response.data.html);

                            if (response.data.has_more) {
                                $btn.data('page', page + 1).prop('disabled', false).text('Load More Reviews');
                            } else {
                                $btn.remove();
                            }
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).text('Load More Reviews');
                    }
                });
            });

            // Smooth scroll to reviews.
            $('a[href="#reviews"]').on('click', function(e) {
                e.preventDefault();

                $('html, body').animate({
                    scrollTop: $reviews.offset().top - 100
                }, 500);
            });

            // Helpful button.
            $reviews.on('click', '.wpss-review-helpful-btn', function(e) {
                e.preventDefault();

                const $btn = $(this);
                const reviewId = $btn.data('review');

                $.ajax({
                    url: wpssService.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wpss_mark_review_helpful',
                        review_id: reviewId,
                        nonce: wpssService.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            const $count = $btn.find('.wpss-helpful-count');
                            $count.text(response.data.count);
                            $btn.addClass('marked');
                        }
                    }
                });
            });
        },

        /**
         * Initialize modals.
         */
        initModals: function() {
            const self = this;

            // Close on overlay click.
            $(document).on('click', '.wpss-modal-overlay', function() {
                self.closeModals();
            });

            // Close button.
            $(document).on('click', '.wpss-modal-close', function(e) {
                e.preventDefault();
                self.closeModals();
            });

            // Escape key.
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    self.closeModals();
                }
            });

            // Contact seller link.
            $(document).on('click', '.wpss-contact-link, .wpss-contact-btn', function(e) {
                e.preventDefault();
                self.openContactModal();
            });
        },

        /**
         * Open order modal.
         */
        openOrderModal: function() {
            const $modal = $(this.config.orderModal);

            // Update modal content.
            this.updateOrderSummary();

            // Show modal.
            $modal.prop('hidden', false).addClass('active');
            $('body').addClass('wpss-modal-open');

            // Focus first input.
            $modal.find('input:first').focus();
        },

        /**
         * Open contact modal.
         */
        openContactModal: function() {
            const $modal = $(this.config.contactModal);

            $modal.prop('hidden', false).addClass('active');
            $('body').addClass('wpss-modal-open');

            $modal.find('textarea').focus();
        },

        /**
         * Close all modals.
         */
        closeModals: function() {
            $('.wpss-modal').prop('hidden', true).removeClass('active');
            $('body').removeClass('wpss-modal-open');
        },

        /**
         * Initialize order form.
         */
        initOrderForm: function() {
            const self = this;
            const $modal = $(this.config.orderModal);

            if (!$modal.length) {
                return;
            }

            // Extras checkbox change.
            $modal.on('change', 'input[name="extras[]"]', function() {
                self.updateExtras();
                self.updateOrderSummary();
            });

            // Quantity controls.
            $modal.on('click', '.wpss-quantity-minus', function(e) {
                e.preventDefault();
                const $input = $(this).siblings('input');
                const current = parseInt($input.val());
                if (current > 1) {
                    $input.val(current - 1).trigger('change');
                }
            });

            $modal.on('click', '.wpss-quantity-plus', function(e) {
                e.preventDefault();
                const $input = $(this).siblings('input');
                const current = parseInt($input.val());
                const max = parseInt($input.attr('max'));
                if (current < max) {
                    $input.val(current + 1).trigger('change');
                }
            });

            $modal.on('change', 'input[name="quantity"]', function() {
                self.state.quantity = parseInt($(this).val()) || 1;
                self.updateOrderSummary();
            });

            // Add to cart button.
            $modal.on('click', '.wpss-add-to-cart-btn', function(e) {
                e.preventDefault();
                self.addToCart();
            });
        },

        /**
         * Update extras state.
         */
        updateExtras: function() {
            const self = this;
            const $modal = $(this.config.orderModal);

            this.state.extras = [];

            $modal.find('input[name="extras[]"]:checked').each(function() {
                self.state.extras.push({
                    index: $(this).val(),
                    price: parseFloat($(this).data('price')),
                    time: parseInt($(this).data('time')) || 0
                });
            });
        },

        /**
         * Update order summary.
         */
        updateOrderSummary: function() {
            const $modal = $(this.config.orderModal);
            const $packages = $(this.config.packages);

            // Get package info.
            const $activePackage = $packages.find('.wpss-package[data-package="' + this.state.selectedPackage + '"]');
            const packageName = $activePackage.find('.wpss-package-name').text();

            // Calculate total.
            let totalPrice = this.state.basePrice;
            let totalDays = this.state.deliveryDays;

            this.state.extras.forEach(function(extra) {
                totalPrice += extra.price;
                totalDays += extra.time;
            });

            totalPrice *= this.state.quantity;
            this.state.totalPrice = totalPrice;

            // Update display.
            $modal.find('.wpss-package-name').text(packageName);
            $modal.find('.wpss-delivery-time').text(totalDays + ' ' + (totalDays === 1 ? 'Day' : 'Days'));
            $modal.find('.wpss-total-price').text(this.formatPrice(totalPrice));

            // Update hidden input.
            $modal.find('input[name="package_index"]').val(this.state.selectedPackage);
        },

        /**
         * Add to cart.
         */
        addToCart: function() {
            const self = this;
            const $modal = $(this.config.orderModal);
            const $btn = $modal.find('.wpss-add-to-cart-btn');

            $btn.prop('disabled', true).text(wpssService.i18n.addingToCart || 'Adding to cart...');

            const data = {
                action: 'wpss_add_service_to_cart',
                service_id: wpssService.serviceId,
                package_index: this.state.selectedPackage,
                addons: this.state.extras.map(function(e) { return e.index; }),
                nonce: wpssService.nonce
            };

            $.ajax({
                url: wpssService.ajaxUrl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        $btn.text(wpssService.i18n.added || 'Added to cart!');

                        // Store checkout URL from response (includes service_id).
                        if (response.data.checkout_url) {
                            self.state.checkoutUrl = response.data.checkout_url;
                        }

                        // Update cart count in header.
                        self.updateCartCount(response.data.cart_count);

                        // Show success state.
                        setTimeout(function() {
                            self.showCartOptions();
                        }, 500);
                    } else {
                        self.showError(response.data.message || wpssService.i18n.error);
                        $btn.prop('disabled', false).text('Continue to Checkout');
                    }
                },
                error: function() {
                    self.showError(wpssService.i18n.error || 'An error occurred.');
                    $btn.prop('disabled', false).text('Continue to Checkout');
                }
            });
        },

        /**
         * Show cart options after adding.
         */
        showCartOptions: function() {
            const $modal = $(this.config.orderModal);
            const $footer = $modal.find('.wpss-modal-footer');
            const checkoutUrl = this.state.checkoutUrl || wpssService.checkoutUrl;

            $footer.html(
                '<div class="wpss-cart-success">' +
                '<p class="wpss-success-message">&#10003; ' + (wpssService.i18n.added || 'Added to cart!') + '</p>' +
                '<div class="wpss-cart-actions">' +
                '<a href="' + checkoutUrl + '" class="wpss-btn wpss-btn-outline">' +
                (wpssService.i18n.viewCart || 'View Cart') +
                '</a>' +
                '<a href="' + checkoutUrl + '" class="wpss-btn wpss-btn-primary">' +
                (wpssService.i18n.checkout || 'Checkout') +
                '</a>' +
                '</div>' +
                '</div>'
            );
        },

        /**
         * Update cart count in header.
         */
        updateCartCount: function(count) {
            const $cartCount = $('.wpss-cart-count, .cart-count, .woocommerce-cart-count');
            $cartCount.text(count);

            // Trigger WooCommerce cart fragments refresh if available.
            if (typeof wc_cart_fragments_params !== 'undefined') {
                $(document.body).trigger('wc_fragment_refresh');
            }
        },

        /**
         * Initialize contact form.
         */
        initContactForm: function() {
            const self = this;
            const $modal = $(this.config.contactModal);

            if (!$modal.length) {
                return;
            }

            $modal.on('submit', '#wpss-contact-form', function(e) {
                e.preventDefault();

                const $form = $(this);
                const $btn = $form.find('button[type="submit"]');

                $btn.prop('disabled', true).text('Sending...');

                const formData = new FormData($form[0]);
                formData.append('action', 'wpss_contact_vendor');
                formData.append('nonce', wpssService.nonce);

                $.ajax({
                    url: wpssService.ajaxUrl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            $form.html(
                                '<div class="wpss-success-message">' +
                                '<span class="wpss-success-icon">&#10003;</span>' +
                                '<p>' + response.data.message + '</p>' +
                                '</div>'
                            );

                            setTimeout(function() {
                                self.closeModals();
                            }, 2000);
                        } else {
                            self.showError(response.data.message);
                            $btn.prop('disabled', false).text('Send Message');
                        }
                    },
                    error: function() {
                        self.showError('Failed to send message. Please try again.');
                        $btn.prop('disabled', false).text('Send Message');
                    }
                });
            });
        },

        /**
         * Initialize sticky packages sidebar.
         *
         * JS-based sticky because CSS position:sticky is broken when a parent
         * element has overflow:hidden (common in themes like BuddyX).
         */
        initStickyPackages: function() {
            const $sidebar = $('.wpss-service-sidebar');
            const $main = $('.wpss-service-main');

            if (!$sidebar.length || !$main.length) {
                return;
            }

            // Only on desktop.
            if (window.innerWidth < 992) {
                return;
            }

            // Store original width before making fixed (grid column width).
            const sidebarWidth = $sidebar.outerWidth();
            const sidebarLeft = $sidebar.offset().left;
            const sidebarTop = $sidebar.offset().top;
            const headerHeight = document.body.classList.contains('admin-bar') ? 32 + 48 : 48;

            $(window).on('scroll.wpssSticky resize.wpssSticky', function() {
                // Disable on mobile.
                if (window.innerWidth < 992) {
                    $sidebar.css({
                        position: '',
                        top: '',
                        left: '',
                        width: ''
                    });
                    return;
                }

                const scrollTop = $(window).scrollTop();
                const mainBottom = $main.offset().top + $main.outerHeight();
                const sidebarHeight = $sidebar.outerHeight();

                if (scrollTop + headerHeight > sidebarTop && scrollTop + headerHeight + sidebarHeight < mainBottom) {
                    // Fixed in viewport.
                    $sidebar.css({
                        position: 'fixed',
                        top: headerHeight + 'px',
                        left: sidebarLeft + 'px',
                        width: sidebarWidth + 'px'
                    });
                } else if (scrollTop + headerHeight + sidebarHeight >= mainBottom) {
                    // Stick at bottom of main content.
                    $sidebar.css({
                        position: 'absolute',
                        top: (mainBottom - sidebarHeight - sidebarTop + $sidebar.parent().offset().top) + 'px',
                        left: '',
                        width: sidebarWidth + 'px'
                    });
                } else {
                    // Default position.
                    $sidebar.css({
                        position: '',
                        top: '',
                        left: '',
                        width: ''
                    });
                }
            });
        },

        /**
         * Format price.
         */
        formatPrice: function(amount) {
            if (typeof wpssService.currencyFormat !== 'undefined') {
                return wpssService.currencyFormat.replace('%s', parseFloat(amount).toFixed(2));
            }
            return '$' + parseFloat(amount).toFixed(2);
        },

        /**
         * Show error message.
         */
        showError: function(message) {
            const $modal = $('.wpss-modal.active');

            if ($modal.length) {
                const $error = $('<div class="wpss-modal-error">' + this.escapeHtml(message) + '</div>');
                $modal.find('.wpss-modal-body').prepend($error);

                setTimeout(function() {
                    $error.fadeOut(function() {
                        $(this).remove();
                    });
                }, 5000);
            }
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
     * Compare packages functionality.
     */
    const WPSSPackageCompare = {
        init: function() {
            const $packages = $(WPSSService.config.packages);

            if (!$packages.length || $packages.find('.wpss-package').length < 2) {
                return;
            }

            this.bindEvents();
        },

        bindEvents: function() {
            $(document).on('click', '.wpss-compare-packages', function(e) {
                e.preventDefault();
                WPSSPackageCompare.showComparison();
            });
        },

        showComparison: function() {
            const $packages = $(WPSSService.config.packages);
            const packages = [];

            $packages.find('.wpss-package').each(function() {
                const $pkg = $(this);
                packages.push({
                    name: $pkg.find('.wpss-package-name').text(),
                    price: $pkg.find('.wpss-package-price').text(),
                    delivery: $pkg.find('.wpss-detail-value').first().text(),
                    revisions: $pkg.find('.wpss-detail-value').eq(1).text(),
                    features: []
                });

                $pkg.find('.wpss-package-features li').each(function() {
                    packages[packages.length - 1].features.push({
                        text: $(this).text(),
                        included: $(this).hasClass('included')
                    });
                });
            });

            // Build comparison table.
            let html = '<div class="wpss-comparison-modal wpss-modal">';
            html += '<div class="wpss-modal-overlay"></div>';
            html += '<div class="wpss-modal-content wpss-modal-large">';
            html += '<button class="wpss-modal-close">&times;</button>';
            html += '<h3>Compare Packages</h3>';
            html += '<table class="wpss-comparison-table">';
            html += '<thead><tr><th></th>';

            packages.forEach(function(pkg) {
                html += '<th>' + WPSSService.escapeHtml(pkg.name) + '</th>';
            });

            html += '</tr></thead><tbody>';
            html += '<tr><td>Price</td>';

            packages.forEach(function(pkg) {
                html += '<td><strong>' + WPSSService.escapeHtml(pkg.price) + '</strong></td>';
            });

            html += '</tr><tr><td>Delivery</td>';

            packages.forEach(function(pkg) {
                html += '<td>' + WPSSService.escapeHtml(pkg.delivery) + '</td>';
            });

            html += '</tr><tr><td>Revisions</td>';

            packages.forEach(function(pkg) {
                html += '<td>' + WPSSService.escapeHtml(pkg.revisions) + '</td>';
            });

            html += '</tr>';

            // Features.
            if (packages[0] && packages[0].features.length > 0) {
                packages[0].features.forEach(function(feature, index) {
                    html += '<tr><td>' + WPSSService.escapeHtml(feature.text) + '</td>';
                    packages.forEach(function(pkg) {
                        const pkgFeature = pkg.features[index];
                        const icon = pkgFeature && pkgFeature.included ? '&#10003;' : '&times;';
                        const cls = pkgFeature && pkgFeature.included ? 'included' : 'not-included';
                        html += '<td class="' + cls + '">' + icon + '</td>';
                    });
                    html += '</tr>';
                });
            }

            html += '</tbody></table>';
            html += '</div></div>';

            $('body').append(html);
            $('.wpss-comparison-modal').prop('hidden', false).addClass('active');
        }
    };

    /**
     * Share functionality.
     */
    const WPSSShare = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $(document).on('click', '.wpss-share-btn', function(e) {
                e.preventDefault();

                const platform = $(this).data('platform');
                const url = encodeURIComponent(window.location.href);
                const title = encodeURIComponent(document.title);

                let shareUrl = '';

                switch (platform) {
                    case 'facebook':
                        shareUrl = 'https://www.facebook.com/sharer/sharer.php?u=' + url;
                        break;
                    case 'twitter':
                        shareUrl = 'https://twitter.com/intent/tweet?url=' + url + '&text=' + title;
                        break;
                    case 'linkedin':
                        shareUrl = 'https://www.linkedin.com/sharing/share-offsite/?url=' + url;
                        break;
                    case 'pinterest':
                        const img = $('.wpss-gallery-image').first().attr('src');
                        shareUrl = 'https://pinterest.com/pin/create/button/?url=' + url + '&media=' + encodeURIComponent(img) + '&description=' + title;
                        break;
                    case 'copy':
                        WPSSShare.copyToClipboard(window.location.href);
                        return;
                }

                if (shareUrl) {
                    window.open(shareUrl, '_blank', 'width=600,height=400');
                }
            });
        },

        copyToClipboard: function(text) {
            navigator.clipboard.writeText(text).then(function() {
                // Show copied message.
                const $btn = $('.wpss-share-btn[data-platform="copy"]');
                const originalText = $btn.text();
                $btn.text('Copied!');
                setTimeout(function() {
                    $btn.text(originalText);
                }, 2000);
            });
        }
    };

    /**
     * Save/Favorite functionality.
     */
    const WPSSFavorite = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $(document).on('click', '.wpss-favorite-btn', function(e) {
                e.preventDefault();

                const $btn = $(this);
                const serviceId = $btn.data('service');
                const isFavorited = $btn.hasClass('favorited');

                $btn.prop('disabled', true);

                $.ajax({
                    url: wpssService.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: isFavorited ? 'wpss_unfavorite_service' : 'wpss_favorite_service',
                        service_id: serviceId,
                        nonce: wpssService.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $btn.toggleClass('favorited');

                            const $icon = $btn.find('.wpss-favorite-icon');
                            $icon.text(isFavorited ? '♡' : '♥');

                            const $count = $btn.find('.wpss-favorite-count');
                            if ($count.length) {
                                $count.text(response.data.count);
                            }
                        }
                    },
                    complete: function() {
                        $btn.prop('disabled', false);
                    }
                });
            });
        }
    };

    // Initialize on document ready.
    $(document).ready(function() {
        WPSSService.init();
        WPSSPackageCompare.init();
        WPSSShare.init();
        WPSSFavorite.init();
    });

    // Expose globally.
    window.WPSSService = WPSSService;

})(jQuery);
