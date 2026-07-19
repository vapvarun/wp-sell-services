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

                // If main area has a video, replace it with an image element.
                if ($active.find('.wpss-gallery-video').length) {
                    $active.html('<img src="' + src + '" alt="" class="wpss-gallery-image">');
                } else {
                    $active.find('img').attr('src', src);
                }
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

                // If modal is already open, update it with new package info.
                if ($(self.config.orderModal).hasClass('active')) {
                    self.updateOrderSummary();
                }
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

            // Load more reviews — REST GET /reviews, rendered client-side with
            // markup parity to the server-rendered review item.
            const perPage = 10;
            $reviews.on('click', '.wpss-load-more-reviews', function(e) {
                e.preventDefault();

                const $btn = $(this);
                const serviceId = $btn.data('service');
                const page = parseInt($btn.data('page'), 10);

                $btn.prop('disabled', true).text((wpssService.i18n && wpssService.i18n.loading) || 'Loading...');

                $.ajax({
                    url: wpssService.apiUrl + '/reviews?service_id=' + serviceId + '&page=' + page + '&per_page=' + perPage,
                    method: 'GET',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', wpssService.restNonce);
                    },
                    success: function(reviews, status, xhr) {
                        const list = Array.isArray(reviews) ? reviews : [];
                        const $list = $reviews.find('.wpss-reviews-list');
                        list.forEach(function(review) {
                            $list.append(self.renderReviewItem(review));
                        });

                        // Hydrate Lucide icons in the freshly inserted markup.
                        if (window.lucide && typeof window.lucide.createIcons === 'function') {
                            window.lucide.createIcons();
                        }

                        const total = parseInt(xhr.getResponseHeader('X-WP-Total'), 10) || 0;
                        const hasMore = (page * perPage) < total;
                        if (hasMore) {
                            $btn.data('page', page + 1).prop('disabled', false).text((wpssService.i18n && wpssService.i18n.loadMoreReviews) || 'Load More Reviews');
                        } else {
                            $btn.remove();
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).text((wpssService.i18n && wpssService.i18n.loadMoreReviews) || 'Load More Reviews');
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

            // Helpful button — REST POST /reviews/{id}/helpful.
            $reviews.on('click', '.wpss-review-helpful-btn', function(e) {
                e.preventDefault();

                const $btn = $(this);
                const reviewId = $btn.data('review');

                if ($btn.hasClass('marked')) {
                    return;
                }

                $.ajax({
                    url: wpssService.apiUrl + '/reviews/' + reviewId + '/helpful',
                    method: 'POST',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', wpssService.restNonce);
                    },
                    success: function(response) {
                        if (response && typeof response.count !== 'undefined') {
                            let $count = $btn.find('.wpss-helpful-count');
                            if (!$count.length) {
                                // Server omits the count span at zero; add it now.
                                $count = $('<span class="wpss-helpful-count"></span>').appendTo($btn);
                            }
                            $count.text('(' + response.count + ')');
                            $btn.addClass('marked');
                        }
                    }
                });
            });
        },

        /**
         * Render a single review item from a REST /reviews object, matching the
         * server-rendered markup so REST-loaded reviews are visually identical
         * to the first page rendered by PHP.
         *
         * @param {Object} review REST review object.
         * @return {string} HTML markup for one review.
         */
        renderReviewItem: function(review) {
            const i18n = (wpssService && wpssService.i18n) || {};
            const esc = function(s) { return $('<div>').text(s == null ? '' : s).html(); };

            const author = review.customer_name || i18n.anonymous || 'Anonymous';
            const avatar = review.customer_avatar || '';
            const rating = parseInt(review.rating, 10) || 0;

            let stars = '';
            for (let i = 1; i <= 5; i++) {
                stars += '<span class="wpss-star ' + (i <= rating ? 'filled' : '') + '">★</span>';
            }

            let reply = '';
            if (review.vendor_reply_html) {
                let replyDate = '';
                if (review.vendor_reply_human) {
                    replyDate = '<span class="wpss-reply-date">' + esc(review.vendor_reply_human) + '</span>';
                }
                reply =
                    '<div class="wpss-review-reply">' +
                        '<div class="wpss-reply-header">' +
                            '<strong>' + esc(i18n.sellerResponse || 'Seller Response:') + '</strong>' +
                            replyDate +
                        '</div>' +
                        review.vendor_reply_html +
                    '</div>';
            }

            let count = '';
            const helpfulCount = parseInt(review.helpful_count, 10) || 0;
            if (helpfulCount > 0) {
                count = '<span class="wpss-helpful-count">(' + helpfulCount + ')</span>';
            }

            return (
                '<div class="wpss-review">' +
                    '<div class="wpss-review-header">' +
                        '<img src="' + esc(avatar) + '" alt="' + esc(author) + '" class="wpss-review-avatar">' +
                        '<div class="wpss-review-info">' +
                            '<strong class="wpss-review-author">' + esc(author) + '</strong>' +
                            '<div class="wpss-review-rating">' + stars + '</div>' +
                        '</div>' +
                        '<span class="wpss-review-date">' + esc(review.created_human || '') + '</span>' +
                    '</div>' +
                    '<div class="wpss-review-content">' + (review.review_html || '') + '</div>' +
                    reply +
                    '<div class="wpss-review-actions">' +
                        '<button type="button" class="wpss-review-helpful-btn" data-review="' + esc(review.id) + '">' +
                            '<span class="wpss-helpful-icon"><i data-lucide="thumbs-up" class="wpss-icon wpss-icon--sm" aria-hidden="true"></i></span>' +
                            '<span class="wpss-helpful-text">' + esc(i18n.helpful || 'Helpful') + '</span>' +
                            count +
                        '</button>' +
                    '</div>' +
                '</div>'
            );
        },

        /**
         * Ensure the popup stylesheet is present on the page.
         *
         * The single-service page only enqueues single-service.css, but all
         * message/inquiry popup styling now lives in messaging.css (the single
         * source of truth). When that stylesheet is not already loaded, derive
         * its URL from the single-service stylesheet link and inject it once so
         * the popup renders with a solid, readable surface on every theme.
         *
         * @return {void}
         */
        ensurePopupStyles: function() {
            // Already loaded (e.g. dashboard messaging view) - nothing to do.
            if (document.querySelector('link[href*="messaging.css"], link[href*="messaging.min.css"]')) {
                return;
            }

            // Find the single-service stylesheet to borrow its directory + version.
            const links = document.querySelectorAll('link[rel="stylesheet"][href*="single-service"]');

            if (!links.length) {
                return;
            }

            const ref = links[0].getAttribute('href');
            // Swap the filename, preserve any ?ver= query string and .min variant.
            const href = ref.replace(/single-service(-rtl)?(\.min)?\.css/, 'messaging$2.css');

            if (href === ref) {
                return;
            }

            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = href;
            document.head.appendChild(link);
        },

        /**
         * Initialize modals.
         */
        initModals: function() {
            const self = this;

            // Make sure the popup surface styles are available on this page.
            this.ensurePopupStyles();

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
            $modal.find('.wpss-delivery-time').text(totalDays + ' ' + (totalDays === 1 ? ((wpssService.i18n && wpssService.i18n.day) || 'Day') : ((wpssService.i18n && wpssService.i18n.days) || 'Days')));
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
                quantity: this.state.quantity || 1,
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
                        // Guests can't have a cart — the server returns a login_url.
                        // Redirect them to log in (and back) instead of dead-ending
                        // on a "Could not add to cart" error they can't resolve.
                        if (response.data && response.data.login_url) {
                            window.location.href = response.data.login_url;
                            return;
                        }
                        self.showError(response.data.message || (wpssService.i18n && wpssService.i18n.error) || 'Could not add to cart. Please try again.');
                        $btn.prop('disabled', false).text((wpssService.i18n && wpssService.i18n.continueToCheckout) || 'Continue to Checkout');
                    }
                },
                error: function() {
                    self.showError((wpssService.i18n && wpssService.i18n.error) || (wpssService.i18n && wpssService.i18n.errorGeneric) || 'An error occurred.');
                    $btn.prop('disabled', false).text((wpssService.i18n && wpssService.i18n.continueToCheckout) || 'Continue to Checkout');
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
            const cartUrl = wpssService.cartUrl || checkoutUrl;

            $footer.html(
                '<div class="wpss-cart-success">' +
                '<p class="wpss-success-message">&#10003; ' + (wpssService.i18n.added || 'Added to cart!') + '</p>' +
                '<div class="wpss-cart-actions">' +
                '<a href="' + cartUrl + '" class="wpss-btn wpss-btn-outline">' +
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
         * Update cart count in header and mini-cart.
         */
        updateCartCount: function(count) {
            const $cartCount = $('.wpss-cart-count, .cart-count, .woocommerce-cart-count');
            $cartCount.text(count);

            // Update floating mini-cart indicator.
            if (typeof WPSS !== 'undefined' && WPSS.updateMiniCart) {
                WPSS.updateMiniCart(count);
            }

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

                $btn.prop('disabled', true).text((wpssService.i18n && wpssService.i18n.sending) || 'Sending...');

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
                            $btn.prop('disabled', false).text((wpssService.i18n && wpssService.i18n.sendMessage) || 'Send Message');
                        }
                    },
                    error: function() {
                        self.showError((wpssService.i18n && wpssService.i18n.contactFailed) || 'Failed to send message. Please try again.');
                        $btn.prop('disabled', false).text((wpssService.i18n && wpssService.i18n.sendMessage) || 'Send Message');
                    }
                });
            });
        },

        /**
         * Format price.
         */
        formatPrice: function(amount) {
            var decimals = (typeof wpssService.currencyDecimals !== 'undefined') ? wpssService.currencyDecimals : 2;
            if (typeof wpssService.currencyFormat !== 'undefined') {
                return wpssService.currencyFormat.replace('%s', parseFloat(amount).toFixed(decimals));
            }
            return '$' + parseFloat(amount).toFixed(decimals);
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
            html += '<h3>' + ((wpssService.i18n && wpssService.i18n.comparePackages) || 'Compare Packages') + '</h3>';
            html += '<table class="wpss-comparison-table">';
            html += '<thead><tr><th></th>';

            packages.forEach(function(pkg) {
                html += '<th>' + WPSSService.escapeHtml(pkg.name) + '</th>';
            });

            html += '</tr></thead><tbody>';
            html += '<tr><td>' + ((wpssService.i18n && wpssService.i18n.price) || 'Price') + '</td>';

            packages.forEach(function(pkg) {
                html += '<td><strong>' + WPSSService.escapeHtml(pkg.price) + '</strong></td>';
            });

            html += '</tr><tr><td>' + ((wpssService.i18n && wpssService.i18n.delivery) || 'Delivery') + '</td>';

            packages.forEach(function(pkg) {
                html += '<td>' + WPSSService.escapeHtml(pkg.delivery) + '</td>';
            });

            html += '</tr><tr><td>' + ((wpssService.i18n && wpssService.i18n.revisions) || 'Revisions') + '</td>';

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
                $btn.text((wpssService.i18n && wpssService.i18n.copied) || 'Copied!');
                setTimeout(function() {
                    $btn.text(originalText);
                }, 2000);
            });
        }
    };

    // Note: the single-service favorite toggle (.wpss-fav-toggle) is handled by
    // frontend.js (WPSS.initFavorites), which already talks to the REST favorites
    // controller (POST/DELETE /wpss/v1/favorites/{id}). The previous WPSSFavorite
    // handler here bound .wpss-favorite-btn, a class no template renders — it was
    // dead code and has been removed to avoid a second, divergent favorites path.

    // Initialize on document ready.
    $(document).ready(function() {
        WPSSService.init();
        WPSSPackageCompare.init();
        WPSSShare.init();
    });

    // Expose globally.
    window.WPSSService = WPSSService;

})(jQuery);
