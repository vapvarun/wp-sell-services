/**
 * WP Sell Services - Checkout JavaScript
 *
 * Handles service checkout functionality with WooCommerce integration.
 *
 * @package WPSellServices
 * @since   1.0.0
 */

(function($) {
    'use strict';

    const WPSSCheckout = {
        /**
         * Configuration.
         */
        config: {
            checkoutForm: 'form.checkout',
            orderReview: '#order_review',
            serviceFields: '.wpss-service-requirements',
            packageSelect: '.wpss-package-select',
            extrasContainer: '.wpss-checkout-extras',
            quantityInput: '.wpss-checkout-quantity',
            summaryContainer: '.wpss-checkout-summary'
        },

        /**
         * State.
         */
        state: {
            serviceId: null,
            packageIndex: 0,
            quantity: 1,
            extras: [],
            basePrice: 0,
            totalPrice: 0,
            deliveryDays: 0
        },

        /**
         * Initialize checkout functionality.
         */
        init: function() {
            if (!this.hasServiceInCart()) {
                return;
            }

            this.bindEvents();
            this.initRequirementsForm();
            this.initPackageSelection();
            this.initExtras();
            this.updateSummary();
        },

        /**
         * Check if cart has service items.
         */
        hasServiceInCart: function() {
            return typeof wpssCheckout !== 'undefined' && wpssCheckout.hasService;
        },

        /**
         * Bind event handlers.
         */
        bindEvents: function() {
            const self = this;

            // Package selection change.
            $(document).on('change', this.config.packageSelect, function() {
                self.state.packageIndex = parseInt($(this).val());
                self.updatePrice();
            });

            // Extras checkbox change.
            $(document).on('change', this.config.extrasContainer + ' input[type="checkbox"]', function() {
                self.updateExtras();
                self.updatePrice();
            });

            // Quantity change.
            $(document).on('change', this.config.quantityInput, function() {
                self.state.quantity = parseInt($(this).val()) || 1;
                self.updatePrice();
            });

            // Quantity buttons.
            $(document).on('click', '.wpss-qty-minus', function(e) {
                e.preventDefault();
                const $input = $(this).siblings('input');
                const val = parseInt($input.val()) - 1;
                if (val >= 1) {
                    $input.val(val).trigger('change');
                }
            });

            $(document).on('click', '.wpss-qty-plus', function(e) {
                e.preventDefault();
                const $input = $(this).siblings('input');
                const max = parseInt($input.attr('max')) || 99;
                const val = parseInt($input.val()) + 1;
                if (val <= max) {
                    $input.val(val).trigger('change');
                }
            });

            // Form validation before submit.
            $(this.config.checkoutForm).on('checkout_place_order', function() {
                return self.validateRequirements();
            });

            // WooCommerce update events.
            $(document.body).on('updated_checkout', function() {
                self.updateSummary();
            });

            // File upload handler.
            $(document).on('change', '.wpss-requirement-file input[type="file"]', function() {
                self.handleFileUpload($(this));
            });

            // Remove uploaded file.
            $(document).on('click', '.wpss-file-remove', function(e) {
                e.preventDefault();
                self.removeFile($(this));
            });
        },

        /**
         * Initialize requirements form.
         */
        initRequirementsForm: function() {
            const $form = $(this.config.serviceFields);

            if (!$form.length) {
                return;
            }

            // Show/hide conditional fields.
            $form.find('[data-conditional]').each(function() {
                const $field = $(this);
                const condition = $field.data('conditional');
                const $trigger = $form.find('[name="' + condition.field + '"]');

                $trigger.on('change', function() {
                    const value = $(this).val();
                    const show = condition.values.includes(value);
                    $field.toggle(show);

                    if (!show) {
                        $field.find('input, textarea, select').val('');
                    }
                }).trigger('change');
            });

            // Character counter for textareas.
            $form.find('textarea[maxlength]').each(function() {
                const $textarea = $(this);
                const max = parseInt($textarea.attr('maxlength'));
                const $counter = $('<span class="wpss-char-counter">0 / ' + max + '</span>');

                $textarea.after($counter);

                $textarea.on('input', function() {
                    const len = $(this).val().length;
                    $counter.text(len + ' / ' + max);

                    if (len > max * 0.9) {
                        $counter.addClass('warning');
                    } else {
                        $counter.removeClass('warning');
                    }
                });
            });
        },

        /**
         * Initialize package selection.
         */
        initPackageSelection: function() {
            const $select = $(this.config.packageSelect);

            if (!$select.length) {
                return;
            }

            // Get initial values from selected option.
            const $selected = $select.find('option:selected');
            this.state.packageIndex = parseInt($select.val());
            this.state.basePrice = parseFloat($selected.data('price')) || 0;
            this.state.deliveryDays = parseInt($selected.data('delivery')) || 0;
        },

        /**
         * Initialize extras.
         */
        initExtras: function() {
            this.updateExtras();
        },

        /**
         * Update extras state.
         */
        updateExtras: function() {
            const self = this;
            const $container = $(this.config.extrasContainer);

            this.state.extras = [];

            $container.find('input[type="checkbox"]:checked').each(function() {
                self.state.extras.push({
                    id: $(this).val(),
                    price: parseFloat($(this).data('price')) || 0,
                    time: parseInt($(this).data('delivery')) || 0
                });
            });
        },

        /**
         * Update price calculation.
         */
        updatePrice: function() {
            const self = this;

            // Calculate total.
            let total = this.state.basePrice;
            let extraDays = 0;

            this.state.extras.forEach(function(extra) {
                total += extra.price;
                extraDays += extra.time;
            });

            total *= this.state.quantity;
            this.state.totalPrice = total;

            // Update WooCommerce cart via AJAX.
            $.ajax({
                url: wpssCheckout.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpss_update_cart_item',
                    service_id: wpssCheckout.serviceId,
                    package_index: this.state.packageIndex,
                    quantity: this.state.quantity,
                    extras: this.state.extras.map(function(e) { return e.id; }),
                    nonce: wpssCheckout.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Trigger WooCommerce checkout update.
                        $(document.body).trigger('update_checkout');
                    }
                },
                error: function() {
                    console.warn('Failed to update cart item.');
                }
            });

            this.updateSummary();
        },

        /**
         * Update summary display.
         */
        updateSummary: function() {
            const $summary = $(this.config.summaryContainer);

            if (!$summary.length) {
                return;
            }

            // Update package name.
            const $packageSelect = $(this.config.packageSelect);
            if ($packageSelect.length) {
                const packageName = $packageSelect.find('option:selected').text();
                $summary.find('.wpss-summary-package').text(packageName);
            }

            // Calculate delivery time.
            let totalDays = this.state.deliveryDays;
            this.state.extras.forEach(function(extra) {
                totalDays += extra.time;
            });

            $summary.find('.wpss-summary-delivery').text(
                totalDays + ' ' + (totalDays === 1 ? 'day' : 'days')
            );

            // Update quantity.
            $summary.find('.wpss-summary-quantity').text(this.state.quantity);

            // Update total (this is also updated by WooCommerce).
            if (this.state.totalPrice > 0) {
                $summary.find('.wpss-summary-total').text(
                    this.formatPrice(this.state.totalPrice)
                );
            }
        },

        /**
         * Validate requirements before checkout.
         */
        validateRequirements: function() {
            const $form = $(this.config.serviceFields);
            let valid = true;
            const errors = [];

            // Clear previous errors.
            $form.find('.wpss-field-error').remove();
            $form.find('.wpss-field-invalid').removeClass('wpss-field-invalid');

            // Validate required fields.
            $form.find('[required]:visible').each(function() {
                const $field = $(this);
                const value = $field.val();

                if (!value || (typeof value === 'string' && !value.trim())) {
                    valid = false;
                    $field.addClass('wpss-field-invalid');

                    const $wrapper = $field.closest('.wpss-form-field');
                    const label = $wrapper.length ? $wrapper.find('label').text() : ($field.attr('name') || 'Field');
                    errors.push(label + ' is required.');

                    $field.after('<span class="wpss-field-error">This field is required.</span>');
                }
            });

            // Validate file requirements.
            $form.find('.wpss-requirement-file[data-required="true"]:visible').each(function() {
                const $container = $(this);
                const hasFiles = $container.find('.wpss-uploaded-file').length > 0;

                if (!hasFiles) {
                    valid = false;
                    $container.addClass('wpss-field-invalid');

                    const label = $container.find('label').text();
                    errors.push(label + ' is required.');
                }
            });

            // Validate minimum length.
            $form.find('[minlength]:visible').each(function() {
                const $field = $(this);
                const value = $field.val();
                const minLength = parseInt($field.attr('minlength'));

                if (value && value.length < minLength) {
                    valid = false;
                    $field.addClass('wpss-field-invalid');

                    $field.after('<span class="wpss-field-error">Minimum ' + minLength + ' characters required.</span>');
                }
            });

            // Show error summary if invalid.
            if (!valid && errors.length > 0) {
                this.showValidationErrors(errors);
            }

            return valid;
        },

        /**
         * Show validation errors.
         */
        showValidationErrors: function(errors) {
            let html = '<div class="wpss-checkout-errors woocommerce-error">';
            html += '<strong>Please complete the following:</strong><ul>';

            errors.forEach(function(error) {
                html += '<li>' + WPSSCheckout.escapeHtml(error) + '</li>';
            });

            html += '</ul></div>';

            // Remove existing errors.
            $('.wpss-checkout-errors').remove();

            // Add before checkout form.
            $(this.config.checkoutForm).before(html);

            // Scroll to errors.
            $('html, body').animate({
                scrollTop: $('.wpss-checkout-errors').offset().top - 100
            }, 500);
        },

        /**
         * Handle file upload.
         */
        handleFileUpload: function($input) {
            const self = this;
            const files = $input[0].files;
            const $container = $input.closest('.wpss-requirement-file');
            const $list = $container.find('.wpss-uploaded-files');
            const maxFiles = parseInt($container.data('max-files')) || 5;
            const maxSize = parseInt($container.data('max-size')) || 10; // MB
            const currentFiles = $list.find('.wpss-uploaded-file').length;

            // Check file count.
            if (currentFiles + files.length > maxFiles) {
                WPSS.showNotification('Maximum ' + maxFiles + ' files allowed.', 'warning');
                $input.val('');
                return;
            }

            // Validate and upload each file.
            Array.from(files).forEach(function(file) {
                // Check file size.
                if (file.size > maxSize * 1024 * 1024) {
                    WPSS.showNotification(file.name + ' is too large. Maximum size is ' + maxSize + 'MB.', 'warning');
                    return;
                }

                self.uploadFile(file, $container);
            });

            // Reset input.
            $input.val('');
        },

        /**
         * Upload file via AJAX.
         */
        uploadFile: function(file, $container) {
            const $list = $container.find('.wpss-uploaded-files');
            const $progress = $('<div class="wpss-file-uploading">' +
                '<span class="wpss-file-name">' + this.escapeHtml(file.name) + '</span>' +
                '<span class="wpss-file-progress">Uploading...</span>' +
                '</div>');

            $list.append($progress);

            const formData = new FormData();
            formData.append('action', 'wpss_upload_requirement_file');
            formData.append('file', file);
            formData.append('nonce', wpssCheckout.nonce);

            $.ajax({
                url: wpssCheckout.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    const xhr = new XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable) {
                            const percent = Math.round((e.loaded / e.total) * 100);
                            $progress.find('.wpss-file-progress').text(percent + '%');
                        }
                    });
                    return xhr;
                },
                success: function(response) {
                    if (response.success) {
                        const file = response.data;
                        const html = '<div class="wpss-uploaded-file" data-id="' + file.id + '">' +
                            '<span class="wpss-file-name">' + WPSSCheckout.escapeHtml(file.name) + '</span>' +
                            '<span class="wpss-file-size">' + file.size + '</span>' +
                            '<button type="button" class="wpss-file-remove">&times;</button>' +
                            '<input type="hidden" name="requirement_files[]" value="' + file.id + '">' +
                            '</div>';

                        $progress.replaceWith(html);
                    } else {
                        $progress.remove();
                        WPSS.showNotification(response.data.message || 'Upload failed.', 'error');
                    }
                },
                error: function() {
                    $progress.remove();
                    WPSS.showNotification('Upload failed. Please try again.', 'error');
                }
            });
        },

        /**
         * Remove uploaded file.
         */
        removeFile: function($btn) {
            const $file = $btn.closest('.wpss-uploaded-file');
            const fileId = $file.data('id');

            // Remove from server.
            $.ajax({
                url: wpssCheckout.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpss_remove_requirement_file',
                    file_id: fileId,
                    nonce: wpssCheckout.nonce
                },
                error: function() {
                    console.warn('Failed to remove file from server:', fileId);
                }
            });

            // Remove from UI.
            $file.fadeOut(function() {
                $(this).remove();
            });
        },

        /**
         * Format price.
         */
        formatPrice: function(amount) {
            if (typeof wpssCheckout.currencyFormat !== 'undefined') {
                return wpssCheckout.currencyFormat.replace('%s', parseFloat(amount).toFixed(2));
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
     * Requirements step functionality.
     */
    const WPSSRequirementsStep = {
        /**
         * Initialize.
         */
        init: function() {
            if (!$('.wpss-requirements-step').length) {
                return;
            }

            this.bindEvents();
        },

        /**
         * Bind events.
         */
        bindEvents: function() {
            const self = this;

            // Next step button.
            $(document).on('click', '.wpss-requirements-next', function(e) {
                e.preventDefault();
                if (self.validate()) {
                    self.nextStep();
                }
            });

            // Previous step button.
            $(document).on('click', '.wpss-requirements-prev', function(e) {
                e.preventDefault();
                self.prevStep();
            });

            // Submit requirements.
            $(document).on('submit', '.wpss-requirements-form', function(e) {
                e.preventDefault();
                if (self.validate()) {
                    self.submitRequirements($(this));
                }
            });

            // Skip for now.
            $(document).on('click', '.wpss-skip-requirements', function(e) {
                e.preventDefault();
                WPSS.showConfirm('You can submit requirements later. Continue to checkout?', function() {
                        self.skipRequirements();
                    }, { confirmText: 'Continue' });
            });
        },

        /**
         * Validate current step.
         */
        validate: function() {
            const $step = $('.wpss-requirements-step.active');
            let valid = true;

            $step.find('.wpss-field-error').remove();
            $step.find('.wpss-field-invalid').removeClass('wpss-field-invalid');

            $step.find('[required]').each(function() {
                const $field = $(this);
                const value = $field.val();

                if (!value || !value.trim()) {
                    valid = false;
                    $field.addClass('wpss-field-invalid');
                    $field.after('<span class="wpss-field-error">Required</span>');
                }
            });

            return valid;
        },

        /**
         * Go to next step.
         */
        nextStep: function() {
            const $current = $('.wpss-requirements-step.active');
            const $next = $current.next('.wpss-requirements-step');

            if ($next.length) {
                $current.removeClass('active');
                $next.addClass('active');
                this.updateProgress();
            }
        },

        /**
         * Go to previous step.
         */
        prevStep: function() {
            const $current = $('.wpss-requirements-step.active');
            const $prev = $current.prev('.wpss-requirements-step');

            if ($prev.length) {
                $current.removeClass('active');
                $prev.addClass('active');
                this.updateProgress();
            }
        },

        /**
         * Update progress indicator.
         */
        updateProgress: function() {
            const total = $('.wpss-requirements-step').length;
            const current = $('.wpss-requirements-step.active').index('.wpss-requirements-step') + 1;
            const percent = (current / total) * 100;

            $('.wpss-requirements-progress-bar').css('width', percent + '%');
            $('.wpss-requirements-progress-text').text('Step ' + current + ' of ' + total);
        },

        /**
         * Submit requirements.
         */
        submitRequirements: function($form) {
            const $btn = $form.find('button[type="submit"]');

            $btn.prop('disabled', true).text('Submitting...');

            const formData = new FormData($form[0]);
            formData.append('action', 'wpss_submit_requirements');
            formData.append('nonce', wpssCheckout.nonce);

            $.ajax({
                url: wpssCheckout.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        // Redirect to order or thank you page.
                        if (response.data.redirect) {
                            window.location.href = response.data.redirect;
                        } else {
                            window.location.reload();
                        }
                    } else {
                        WPSS.showNotification(response.data.message || 'Failed to submit requirements.', 'error');
                        $btn.prop('disabled', false).text('Submit');
                    }
                },
                error: function() {
                    WPSS.showNotification('An error occurred. Please try again.', 'error');
                    $btn.prop('disabled', false).text('Submit');
                }
            });
        },

        /**
         * Skip requirements for now.
         */
        skipRequirements: function() {
            $.ajax({
                url: wpssCheckout.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpss_skip_requirements',
                    order_id: wpssCheckout.orderId,
                    nonce: wpssCheckout.nonce
                },
                success: function(response) {
                    if (response.success && response.data.redirect) {
                        window.location.href = response.data.redirect;
                    }
                }
            });
        }
    };

    /**
     * Thank you page functionality.
     */
    const WPSSThankYou = {
        /**
         * Initialize.
         */
        init: function() {
            if (!$('.wpss-order-received').length) {
                return;
            }

            this.showRequirementsPrompt();
        },

        /**
         * Show requirements prompt if needed.
         */
        showRequirementsPrompt: function() {
            if (typeof wpssCheckout !== 'undefined' && wpssCheckout.pendingRequirements) {
                $('.wpss-requirements-prompt').show();
            }
        }
    };

    // Initialize on document ready.
    $(document).ready(function() {
        WPSSCheckout.init();
        WPSSRequirementsStep.init();
        WPSSThankYou.init();
    });

    // Expose globally.
    window.WPSSCheckout = WPSSCheckout;

})(jQuery);
