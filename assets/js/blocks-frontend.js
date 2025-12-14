/**
 * WP Sell Services - Blocks Frontend JavaScript
 *
 * Handles carousel functionality and other interactive elements.
 *
 * @package WPSellServices
 * @since   1.0.0
 */

(function($) {
	'use strict';

	window.WPSSBlocks = window.WPSSBlocks || {};

	/**
	 * Initialize all blocks.
	 */
	WPSSBlocks.init = function() {
		WPSSBlocks.initCarousels();
		WPSSBlocks.initSearchForms();
	};

	/**
	 * Initialize carousels.
	 */
	WPSSBlocks.initCarousels = function() {
		$('.wpss-featured-carousel').each(function() {
			new WPSSBlocks.Carousel(this);
		});
	};

	/**
	 * Initialize search forms.
	 */
	WPSSBlocks.initSearchForms = function() {
		$('.wpss-search-form').each(function() {
			const $form = $(this);

			// Auto-submit on category change (optional).
			$form.find('.wpss-category-select').on('change', function() {
				if ($(this).data('auto-submit')) {
					$form.submit();
				}
			});
		});
	};

	/**
	 * Carousel Class
	 *
	 * @param {HTMLElement} element Carousel container element.
	 */
	WPSSBlocks.Carousel = function(element) {
		this.$container = $(element);
		this.$track = this.$container.find('.wpss-featured-track');
		this.$slides = this.$container.find('.wpss-featured-slides');
		this.$slideItems = this.$container.find('.wpss-featured-slide');
		this.$prevBtn = this.$container.find('.wpss-carousel-prev');
		this.$nextBtn = this.$container.find('.wpss-carousel-next');
		this.$dotsContainer = this.$container.find('.wpss-carousel-dots');

		this.options = {
			autoplay: this.$container.data('autoplay') === true || this.$container.data('autoplay') === 'true',
			interval: parseInt(this.$container.data('interval')) || 5000,
			showDots: this.$container.data('dots') === true || this.$container.data('dots') === 'true',
			showArrows: this.$container.data('arrows') === true || this.$container.data('arrows') === 'true'
		};

		this.currentSlide = 0;
		this.slideCount = this.$slideItems.length;
		this.slidesPerView = this.getSlidesPerView();
		this.totalPages = Math.ceil(this.slideCount / this.slidesPerView);
		this.autoplayTimer = null;

		this.init();
	};

	/**
	 * Initialize carousel.
	 */
	WPSSBlocks.Carousel.prototype.init = function() {
		if (this.slideCount <= this.slidesPerView) {
			// No need for carousel if all slides fit.
			return;
		}

		this.buildDots();
		this.bindEvents();

		if (this.options.autoplay) {
			this.startAutoplay();
		}
	};

	/**
	 * Get number of slides per view based on viewport.
	 *
	 * @return {number}
	 */
	WPSSBlocks.Carousel.prototype.getSlidesPerView = function() {
		const viewportWidth = window.innerWidth;

		if (viewportWidth <= 480) {
			return 1;
		} else if (viewportWidth <= 768) {
			return 2;
		} else if (viewportWidth <= 1024) {
			return 3;
		}

		return 4;
	};

	/**
	 * Build pagination dots.
	 */
	WPSSBlocks.Carousel.prototype.buildDots = function() {
		if (!this.options.showDots || this.totalPages <= 1) {
			return;
		}

		let dotsHtml = '';
		for (let i = 0; i < this.totalPages; i++) {
			dotsHtml += '<span class="wpss-carousel-dot' + (i === 0 ? ' active' : '') + '" data-index="' + i + '"></span>';
		}

		this.$dotsContainer.html(dotsHtml);
	};

	/**
	 * Bind carousel events.
	 */
	WPSSBlocks.Carousel.prototype.bindEvents = function() {
		const self = this;

		// Previous button.
		this.$prevBtn.on('click', function(e) {
			e.preventDefault();
			self.goToPrev();
		});

		// Next button.
		this.$nextBtn.on('click', function(e) {
			e.preventDefault();
			self.goToNext();
		});

		// Dot navigation.
		this.$dotsContainer.on('click', '.wpss-carousel-dot', function(e) {
			e.preventDefault();
			self.goToSlide($(this).data('index'));
		});

		// Pause autoplay on hover.
		if (this.options.autoplay) {
			this.$container.on('mouseenter', function() {
				self.stopAutoplay();
			}).on('mouseleave', function() {
				self.startAutoplay();
			});
		}

		// Handle resize.
		let resizeTimer;
		$(window).on('resize', function() {
			clearTimeout(resizeTimer);
			resizeTimer = setTimeout(function() {
				self.handleResize();
			}, 250);
		});

		// Touch events for swipe.
		let touchStartX = 0;
		let touchEndX = 0;

		this.$track.on('touchstart', function(e) {
			touchStartX = e.originalEvent.touches[0].clientX;
		});

		this.$track.on('touchmove', function(e) {
			touchEndX = e.originalEvent.touches[0].clientX;
		});

		this.$track.on('touchend', function() {
			const diff = touchStartX - touchEndX;

			if (Math.abs(diff) > 50) {
				if (diff > 0) {
					self.goToNext();
				} else {
					self.goToPrev();
				}
			}
		});
	};

	/**
	 * Go to specific slide.
	 *
	 * @param {number} index Slide index.
	 */
	WPSSBlocks.Carousel.prototype.goToSlide = function(index) {
		if (index < 0) {
			index = this.totalPages - 1;
		} else if (index >= this.totalPages) {
			index = 0;
		}

		this.currentSlide = index;

		const slideWidth = this.$slideItems.first().outerWidth(true);
		const offset = -(index * this.slidesPerView * slideWidth);

		this.$slides.css('transform', 'translateX(' + offset + 'px)');

		// Update dots.
		this.$dotsContainer.find('.wpss-carousel-dot')
			.removeClass('active')
			.eq(index)
			.addClass('active');
	};

	/**
	 * Go to previous slide.
	 */
	WPSSBlocks.Carousel.prototype.goToPrev = function() {
		this.goToSlide(this.currentSlide - 1);
	};

	/**
	 * Go to next slide.
	 */
	WPSSBlocks.Carousel.prototype.goToNext = function() {
		this.goToSlide(this.currentSlide + 1);
	};

	/**
	 * Start autoplay.
	 */
	WPSSBlocks.Carousel.prototype.startAutoplay = function() {
		const self = this;

		this.stopAutoplay();

		this.autoplayTimer = setInterval(function() {
			self.goToNext();
		}, this.options.interval);
	};

	/**
	 * Stop autoplay.
	 */
	WPSSBlocks.Carousel.prototype.stopAutoplay = function() {
		if (this.autoplayTimer) {
			clearInterval(this.autoplayTimer);
			this.autoplayTimer = null;
		}
	};

	/**
	 * Handle window resize.
	 */
	WPSSBlocks.Carousel.prototype.handleResize = function() {
		const newSlidesPerView = this.getSlidesPerView();

		if (newSlidesPerView !== this.slidesPerView) {
			this.slidesPerView = newSlidesPerView;
			this.totalPages = Math.ceil(this.slideCount / this.slidesPerView);
			this.buildDots();
			this.goToSlide(0);
		}
	};

	/**
	 * AJAX Service Loading
	 */
	WPSSBlocks.loadServices = function($container, params) {
		$container.addClass('wpss-loading');

		$.ajax({
			url: wpssBlocksFrontend.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wpss_load_services',
				nonce: wpssBlocksFrontend.nonce,
				...params
			},
			success: function(response) {
				if (response.success && response.data.html) {
					$container.find('.wpss-services-grid').html(response.data.html);

					if (response.data.pagination) {
						$container.find('.wpss-pagination').html(response.data.pagination);
					}
				}
			},
			error: function() {
				console.error('Failed to load services.');
			},
			complete: function() {
				$container.removeClass('wpss-loading');
			}
		});
	};

	/**
	 * Handle pagination clicks (AJAX).
	 */
	$(document).on('click', '.wpss-block[data-ajax="true"] .wpss-pagination a', function(e) {
		e.preventDefault();

		const $link = $(this);
		const $container = $link.closest('.wpss-block');
		const page = $link.attr('href').match(/paged=(\d+)/);

		if (page && page[1]) {
			WPSSBlocks.loadServices($container, {
				page: parseInt(page[1]),
				attributes: $container.data('attributes')
			});

			// Scroll to top of container.
			$('html, body').animate({
				scrollTop: $container.offset().top - 100
			}, 300);
		}
	});

	/**
	 * Lazy load images.
	 */
	WPSSBlocks.lazyLoadImages = function() {
		if ('IntersectionObserver' in window) {
			const imageObserver = new IntersectionObserver(function(entries) {
				entries.forEach(function(entry) {
					if (entry.isIntersecting) {
						const $img = $(entry.target);
						const src = $img.data('src');

						if (src) {
							$img.attr('src', src).removeAttr('data-src');
						}

						imageObserver.unobserve(entry.target);
					}
				});
			}, {
				rootMargin: '100px'
			});

			$('.wpss-block img[data-src]').each(function() {
				imageObserver.observe(this);
			});
		} else {
			// Fallback for older browsers.
			$('.wpss-block img[data-src]').each(function() {
				const $img = $(this);
				$img.attr('src', $img.data('src')).removeAttr('data-src');
			});
		}
	};

	// Initialize on document ready.
	$(document).ready(function() {
		WPSSBlocks.init();
		WPSSBlocks.lazyLoadImages();
	});

})(jQuery);
