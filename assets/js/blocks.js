/**
 * WP Sell Services - Gutenberg Blocks
 *
 * Registers all Gutenberg blocks for the plugin.
 *
 * @package WPSellServices
 * @since   1.0.0
 */

(function(wp) {
	'use strict';

	const { registerBlockType } = wp.blocks;
	const { createElement: el, Fragment } = wp.element;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const {
		PanelBody,
		PanelRow,
		RangeControl,
		SelectControl,
		ToggleControl,
		TextControl,
		Spinner,
		Placeholder
	} = wp.components;
	const { __ } = wp.i18n;
	const { useSelect } = wp.data;
	const { useState, useEffect } = wp.element;
	const ServerSideRender = wp.serverSideRender;

	// Block icon component
	const BlockIcon = (iconName) => el('span', {
		className: 'dashicons dashicons-' + iconName
	});

	/**
	 * Service Grid Block
	 */
	registerBlockType('wpss/service-grid', {
		title: __('Service Grid', 'wp-sell-services'),
		description: __('Display services in a responsive grid layout.', 'wp-sell-services'),
		icon: 'grid-view',
		category: 'wp-sell-services',
		keywords: [__('services', 'wp-sell-services'), __('grid', 'wp-sell-services'), __('listing', 'wp-sell-services')],
		supports: {
			align: ['wide', 'full'],
			anchor: true
		},
		attributes: {
			columns: { type: 'number', default: 3 },
			perPage: { type: 'number', default: 9 },
			category: { type: 'number', default: 0 },
			orderBy: { type: 'string', default: 'date' },
			order: { type: 'string', default: 'DESC' },
			showPagination: { type: 'boolean', default: true },
			showRating: { type: 'boolean', default: true },
			showPrice: { type: 'boolean', default: true },
			showSeller: { type: 'boolean', default: true },
			featured: { type: 'boolean', default: false }
		},
		edit: function(props) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps({
				className: 'wpss-grid-cols-' + attributes.columns
			});

			const categories = wpssBlocks.categories || [];

			return el(Fragment, {},
				el(InspectorControls, {},
					el(PanelBody, { title: __('Layout Settings', 'wp-sell-services'), initialOpen: true },
						el(RangeControl, {
							label: __('Columns', 'wp-sell-services'),
							value: attributes.columns,
							onChange: (value) => setAttributes({ columns: value }),
							min: 2,
							max: 5
						}),
						el(RangeControl, {
							label: __('Services per page', 'wp-sell-services'),
							value: attributes.perPage,
							onChange: (value) => setAttributes({ perPage: value }),
							min: 3,
							max: 24
						})
					),
					el(PanelBody, { title: __('Filter Settings', 'wp-sell-services'), initialOpen: false },
						el(SelectControl, {
							label: __('Category', 'wp-sell-services'),
							value: attributes.category,
							options: [
								{ value: 0, label: __('All Categories', 'wp-sell-services') },
								...categories
							],
							onChange: (value) => setAttributes({ category: parseInt(value) })
						}),
						el(SelectControl, {
							label: __('Order by', 'wp-sell-services'),
							value: attributes.orderBy,
							options: [
								{ value: 'date', label: __('Date', 'wp-sell-services') },
								{ value: 'title', label: __('Title', 'wp-sell-services') },
								{ value: 'menu_order', label: __('Menu Order', 'wp-sell-services') },
								{ value: 'rand', label: __('Random', 'wp-sell-services') }
							],
							onChange: (value) => setAttributes({ orderBy: value })
						}),
						el(SelectControl, {
							label: __('Order', 'wp-sell-services'),
							value: attributes.order,
							options: [
								{ value: 'DESC', label: __('Descending', 'wp-sell-services') },
								{ value: 'ASC', label: __('Ascending', 'wp-sell-services') }
							],
							onChange: (value) => setAttributes({ order: value })
						}),
						el(ToggleControl, {
							label: __('Featured only', 'wp-sell-services'),
							checked: attributes.featured,
							onChange: (value) => setAttributes({ featured: value })
						})
					),
					el(PanelBody, { title: __('Display Settings', 'wp-sell-services'), initialOpen: false },
						el(ToggleControl, {
							label: __('Show pagination', 'wp-sell-services'),
							checked: attributes.showPagination,
							onChange: (value) => setAttributes({ showPagination: value })
						}),
						el(ToggleControl, {
							label: __('Show rating', 'wp-sell-services'),
							checked: attributes.showRating,
							onChange: (value) => setAttributes({ showRating: value })
						}),
						el(ToggleControl, {
							label: __('Show price', 'wp-sell-services'),
							checked: attributes.showPrice,
							onChange: (value) => setAttributes({ showPrice: value })
						}),
						el(ToggleControl, {
							label: __('Show seller', 'wp-sell-services'),
							checked: attributes.showSeller,
							onChange: (value) => setAttributes({ showSeller: value })
						})
					)
				),
				el('div', blockProps,
					el(ServerSideRender, {
						block: 'wpss/service-grid',
						attributes: attributes,
						EmptyResponsePlaceholder: () => el(Placeholder, {
							icon: 'grid-view',
							label: __('Service Grid', 'wp-sell-services'),
							instructions: __('No services found. Add some services to display them here.', 'wp-sell-services')
						})
					})
				)
			);
		},
		save: function() {
			return null; // Server-side rendered
		}
	});

	/**
	 * Service Search Block
	 */
	registerBlockType('wpss/service-search', {
		title: __('Service Search', 'wp-sell-services'),
		description: __('Display a search form to find services.', 'wp-sell-services'),
		icon: 'search',
		category: 'wp-sell-services',
		keywords: [__('search', 'wp-sell-services'), __('find', 'wp-sell-services'), __('filter', 'wp-sell-services')],
		supports: {
			align: ['wide', 'full'],
			anchor: true
		},
		attributes: {
			placeholder: { type: 'string', default: '' },
			showCategoryFilter: { type: 'boolean', default: true },
			buttonText: { type: 'string', default: '' },
			style: { type: 'string', default: 'default' }
		},
		edit: function(props) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps({
				className: 'wpss-search-style-' + attributes.style
			});

			return el(Fragment, {},
				el(InspectorControls, {},
					el(PanelBody, { title: __('Search Settings', 'wp-sell-services'), initialOpen: true },
						el(TextControl, {
							label: __('Placeholder text', 'wp-sell-services'),
							value: attributes.placeholder,
							onChange: (value) => setAttributes({ placeholder: value }),
							placeholder: __('What service are you looking for?', 'wp-sell-services')
						}),
						el(TextControl, {
							label: __('Button text', 'wp-sell-services'),
							value: attributes.buttonText,
							onChange: (value) => setAttributes({ buttonText: value }),
							placeholder: __('Search', 'wp-sell-services')
						}),
						el(ToggleControl, {
							label: __('Show category filter', 'wp-sell-services'),
							checked: attributes.showCategoryFilter,
							onChange: (value) => setAttributes({ showCategoryFilter: value })
						}),
						el(SelectControl, {
							label: __('Style', 'wp-sell-services'),
							value: attributes.style,
							options: [
								{ value: 'default', label: __('Default', 'wp-sell-services') },
								{ value: 'hero', label: __('Hero', 'wp-sell-services') },
								{ value: 'minimal', label: __('Minimal', 'wp-sell-services') }
							],
							onChange: (value) => setAttributes({ style: value })
						})
					)
				),
				el('div', blockProps,
					el(ServerSideRender, {
						block: 'wpss/service-search',
						attributes: attributes
					})
				)
			);
		},
		save: function() {
			return null;
		}
	});

	/**
	 * Service Categories Block
	 */
	registerBlockType('wpss/service-categories', {
		title: __('Service Categories', 'wp-sell-services'),
		description: __('Display service categories in a grid or list.', 'wp-sell-services'),
		icon: 'category',
		category: 'wp-sell-services',
		keywords: [__('categories', 'wp-sell-services'), __('taxonomy', 'wp-sell-services'), __('browse', 'wp-sell-services')],
		supports: {
			align: ['wide', 'full'],
			anchor: true
		},
		attributes: {
			layout: { type: 'string', default: 'grid' },
			columns: { type: 'number', default: 4 },
			showCount: { type: 'boolean', default: true },
			showIcon: { type: 'boolean', default: true },
			showImage: { type: 'boolean', default: false },
			hideEmpty: { type: 'boolean', default: true },
			parentOnly: { type: 'boolean', default: false },
			maxItems: { type: 'number', default: 8 },
			orderBy: { type: 'string', default: 'name' },
			order: { type: 'string', default: 'ASC' }
		},
		edit: function(props) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps({
				className: 'wpss-categories-' + attributes.layout + ' wpss-grid-cols-' + attributes.columns
			});

			return el(Fragment, {},
				el(InspectorControls, {},
					el(PanelBody, { title: __('Layout Settings', 'wp-sell-services'), initialOpen: true },
						el(SelectControl, {
							label: __('Layout', 'wp-sell-services'),
							value: attributes.layout,
							options: [
								{ value: 'grid', label: __('Grid', 'wp-sell-services') },
								{ value: 'list', label: __('List', 'wp-sell-services') }
							],
							onChange: (value) => setAttributes({ layout: value })
						}),
						el(RangeControl, {
							label: __('Columns', 'wp-sell-services'),
							value: attributes.columns,
							onChange: (value) => setAttributes({ columns: value }),
							min: 2,
							max: 6
						}),
						el(RangeControl, {
							label: __('Max items', 'wp-sell-services'),
							value: attributes.maxItems,
							onChange: (value) => setAttributes({ maxItems: value }),
							min: 2,
							max: 20
						})
					),
					el(PanelBody, { title: __('Display Settings', 'wp-sell-services'), initialOpen: false },
						el(ToggleControl, {
							label: __('Show service count', 'wp-sell-services'),
							checked: attributes.showCount,
							onChange: (value) => setAttributes({ showCount: value })
						}),
						el(ToggleControl, {
							label: __('Show icon', 'wp-sell-services'),
							checked: attributes.showIcon,
							onChange: (value) => setAttributes({ showIcon: value })
						}),
						el(ToggleControl, {
							label: __('Show image', 'wp-sell-services'),
							checked: attributes.showImage,
							onChange: (value) => setAttributes({ showImage: value })
						}),
						el(ToggleControl, {
							label: __('Hide empty categories', 'wp-sell-services'),
							checked: attributes.hideEmpty,
							onChange: (value) => setAttributes({ hideEmpty: value })
						}),
						el(ToggleControl, {
							label: __('Parent categories only', 'wp-sell-services'),
							checked: attributes.parentOnly,
							onChange: (value) => setAttributes({ parentOnly: value })
						})
					)
				),
				el('div', blockProps,
					el(ServerSideRender, {
						block: 'wpss/service-categories',
						attributes: attributes,
						EmptyResponsePlaceholder: () => el(Placeholder, {
							icon: 'category',
							label: __('Service Categories', 'wp-sell-services'),
							instructions: __('No categories found.', 'wp-sell-services')
						})
					})
				)
			);
		},
		save: function() {
			return null;
		}
	});

	/**
	 * Featured Services Block
	 */
	registerBlockType('wpss/featured-services', {
		title: __('Featured Services', 'wp-sell-services'),
		description: __('Display featured services in a carousel or grid.', 'wp-sell-services'),
		icon: 'star-filled',
		category: 'wp-sell-services',
		keywords: [__('featured', 'wp-sell-services'), __('carousel', 'wp-sell-services'), __('popular', 'wp-sell-services')],
		supports: {
			align: ['wide', 'full'],
			anchor: true
		},
		attributes: {
			layout: { type: 'string', default: 'carousel' },
			columns: { type: 'number', default: 4 },
			limit: { type: 'number', default: 8 },
			autoplay: { type: 'boolean', default: true },
			interval: { type: 'number', default: 5000 },
			showDots: { type: 'boolean', default: true },
			showArrows: { type: 'boolean', default: true },
			showRating: { type: 'boolean', default: true },
			showPrice: { type: 'boolean', default: true },
			title: { type: 'string', default: '' }
		},
		edit: function(props) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps({
				className: 'wpss-featured-' + attributes.layout + ' wpss-grid-cols-' + attributes.columns
			});

			return el(Fragment, {},
				el(InspectorControls, {},
					el(PanelBody, { title: __('Layout Settings', 'wp-sell-services'), initialOpen: true },
						el(TextControl, {
							label: __('Title', 'wp-sell-services'),
							value: attributes.title,
							onChange: (value) => setAttributes({ title: value }),
							placeholder: __('Featured Services', 'wp-sell-services')
						}),
						el(SelectControl, {
							label: __('Layout', 'wp-sell-services'),
							value: attributes.layout,
							options: [
								{ value: 'carousel', label: __('Carousel', 'wp-sell-services') },
								{ value: 'grid', label: __('Grid', 'wp-sell-services') }
							],
							onChange: (value) => setAttributes({ layout: value })
						}),
						el(RangeControl, {
							label: __('Columns', 'wp-sell-services'),
							value: attributes.columns,
							onChange: (value) => setAttributes({ columns: value }),
							min: 2,
							max: 5
						}),
						el(RangeControl, {
							label: __('Number of services', 'wp-sell-services'),
							value: attributes.limit,
							onChange: (value) => setAttributes({ limit: value }),
							min: 2,
							max: 16
						})
					),
					attributes.layout === 'carousel' && el(PanelBody, { title: __('Carousel Settings', 'wp-sell-services'), initialOpen: false },
						el(ToggleControl, {
							label: __('Autoplay', 'wp-sell-services'),
							checked: attributes.autoplay,
							onChange: (value) => setAttributes({ autoplay: value })
						}),
						attributes.autoplay && el(RangeControl, {
							label: __('Interval (ms)', 'wp-sell-services'),
							value: attributes.interval,
							onChange: (value) => setAttributes({ interval: value }),
							min: 2000,
							max: 10000,
							step: 500
						}),
						el(ToggleControl, {
							label: __('Show dots', 'wp-sell-services'),
							checked: attributes.showDots,
							onChange: (value) => setAttributes({ showDots: value })
						}),
						el(ToggleControl, {
							label: __('Show arrows', 'wp-sell-services'),
							checked: attributes.showArrows,
							onChange: (value) => setAttributes({ showArrows: value })
						})
					),
					el(PanelBody, { title: __('Display Settings', 'wp-sell-services'), initialOpen: false },
						el(ToggleControl, {
							label: __('Show rating', 'wp-sell-services'),
							checked: attributes.showRating,
							onChange: (value) => setAttributes({ showRating: value })
						}),
						el(ToggleControl, {
							label: __('Show price', 'wp-sell-services'),
							checked: attributes.showPrice,
							onChange: (value) => setAttributes({ showPrice: value })
						})
					)
				),
				el('div', blockProps,
					el(ServerSideRender, {
						block: 'wpss/featured-services',
						attributes: attributes,
						EmptyResponsePlaceholder: () => el(Placeholder, {
							icon: 'star-filled',
							label: __('Featured Services', 'wp-sell-services'),
							instructions: __('No featured services found. Mark some services as featured.', 'wp-sell-services')
						})
					})
				)
			);
		},
		save: function() {
			return null;
		}
	});

	/**
	 * Seller Card Block
	 */
	registerBlockType('wpss/seller-card', {
		title: __('Seller Card', 'wp-sell-services'),
		description: __('Display a seller profile card with stats.', 'wp-sell-services'),
		icon: 'businessman',
		category: 'wp-sell-services',
		keywords: [__('seller', 'wp-sell-services'), __('vendor', 'wp-sell-services'), __('profile', 'wp-sell-services')],
		supports: {
			anchor: true
		},
		attributes: {
			userId: { type: 'number', default: 0 },
			showBio: { type: 'boolean', default: true },
			showStats: { type: 'boolean', default: true },
			showRating: { type: 'boolean', default: true },
			showServices: { type: 'boolean', default: true },
			showButton: { type: 'boolean', default: true },
			layout: { type: 'string', default: 'vertical' }
		},
		edit: function(props) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps({
				className: 'wpss-seller-layout-' + attributes.layout
			});

			return el(Fragment, {},
				el(InspectorControls, {},
					el(PanelBody, { title: __('Seller Settings', 'wp-sell-services'), initialOpen: true },
						el(TextControl, {
							label: __('User ID', 'wp-sell-services'),
							help: __('Leave empty to show current user.', 'wp-sell-services'),
							type: 'number',
							value: attributes.userId || '',
							onChange: (value) => setAttributes({ userId: parseInt(value) || 0 })
						}),
						el(SelectControl, {
							label: __('Layout', 'wp-sell-services'),
							value: attributes.layout,
							options: [
								{ value: 'vertical', label: __('Vertical', 'wp-sell-services') },
								{ value: 'horizontal', label: __('Horizontal', 'wp-sell-services') }
							],
							onChange: (value) => setAttributes({ layout: value })
						})
					),
					el(PanelBody, { title: __('Display Settings', 'wp-sell-services'), initialOpen: false },
						el(ToggleControl, {
							label: __('Show bio', 'wp-sell-services'),
							checked: attributes.showBio,
							onChange: (value) => setAttributes({ showBio: value })
						}),
						el(ToggleControl, {
							label: __('Show stats', 'wp-sell-services'),
							checked: attributes.showStats,
							onChange: (value) => setAttributes({ showStats: value })
						}),
						el(ToggleControl, {
							label: __('Show rating', 'wp-sell-services'),
							checked: attributes.showRating,
							onChange: (value) => setAttributes({ showRating: value })
						}),
						el(ToggleControl, {
							label: __('Show services', 'wp-sell-services'),
							checked: attributes.showServices,
							onChange: (value) => setAttributes({ showServices: value })
						}),
						el(ToggleControl, {
							label: __('Show buttons', 'wp-sell-services'),
							checked: attributes.showButton,
							onChange: (value) => setAttributes({ showButton: value })
						})
					)
				),
				el('div', blockProps,
					el(ServerSideRender, {
						block: 'wpss/seller-card',
						attributes: attributes,
						EmptyResponsePlaceholder: () => el(Placeholder, {
							icon: 'businessman',
							label: __('Seller Card', 'wp-sell-services'),
							instructions: __('Enter a user ID to display their seller card.', 'wp-sell-services')
						})
					})
				)
			);
		},
		save: function() {
			return null;
		}
	});

	/**
	 * Buyer Requests Block
	 */
	registerBlockType('wpss/buyer-requests', {
		title: __('Buyer Requests', 'wp-sell-services'),
		description: __('Display buyer requests for sellers to browse and respond.', 'wp-sell-services'),
		icon: 'megaphone',
		category: 'wp-sell-services',
		keywords: [__('requests', 'wp-sell-services'), __('jobs', 'wp-sell-services'), __('projects', 'wp-sell-services')],
		supports: {
			align: ['wide', 'full'],
			anchor: true
		},
		attributes: {
			perPage: { type: 'number', default: 10 },
			category: { type: 'number', default: 0 },
			orderBy: { type: 'string', default: 'date' },
			order: { type: 'string', default: 'DESC' },
			showPagination: { type: 'boolean', default: true },
			showBudget: { type: 'boolean', default: true },
			showDeadline: { type: 'boolean', default: true },
			showOffers: { type: 'boolean', default: true },
			layout: { type: 'string', default: 'list' }
		},
		edit: function(props) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps({
				className: 'wpss-requests-' + attributes.layout
			});

			const categories = wpssBlocks.categories || [];

			return el(Fragment, {},
				el(InspectorControls, {},
					el(PanelBody, { title: __('Query Settings', 'wp-sell-services'), initialOpen: true },
						el(RangeControl, {
							label: __('Requests per page', 'wp-sell-services'),
							value: attributes.perPage,
							onChange: (value) => setAttributes({ perPage: value }),
							min: 3,
							max: 20
						}),
						el(SelectControl, {
							label: __('Category', 'wp-sell-services'),
							value: attributes.category,
							options: [
								{ value: 0, label: __('All Categories', 'wp-sell-services') },
								...categories
							],
							onChange: (value) => setAttributes({ category: parseInt(value) })
						}),
						el(SelectControl, {
							label: __('Order by', 'wp-sell-services'),
							value: attributes.orderBy,
							options: [
								{ value: 'date', label: __('Date', 'wp-sell-services') },
								{ value: 'title', label: __('Title', 'wp-sell-services') }
							],
							onChange: (value) => setAttributes({ orderBy: value })
						}),
						el(SelectControl, {
							label: __('Order', 'wp-sell-services'),
							value: attributes.order,
							options: [
								{ value: 'DESC', label: __('Newest first', 'wp-sell-services') },
								{ value: 'ASC', label: __('Oldest first', 'wp-sell-services') }
							],
							onChange: (value) => setAttributes({ order: value })
						})
					),
					el(PanelBody, { title: __('Display Settings', 'wp-sell-services'), initialOpen: false },
						el(ToggleControl, {
							label: __('Show pagination', 'wp-sell-services'),
							checked: attributes.showPagination,
							onChange: (value) => setAttributes({ showPagination: value })
						}),
						el(ToggleControl, {
							label: __('Show budget', 'wp-sell-services'),
							checked: attributes.showBudget,
							onChange: (value) => setAttributes({ showBudget: value })
						}),
						el(ToggleControl, {
							label: __('Show deadline', 'wp-sell-services'),
							checked: attributes.showDeadline,
							onChange: (value) => setAttributes({ showDeadline: value })
						}),
						el(ToggleControl, {
							label: __('Show offers count', 'wp-sell-services'),
							checked: attributes.showOffers,
							onChange: (value) => setAttributes({ showOffers: value })
						})
					)
				),
				el('div', blockProps,
					el(ServerSideRender, {
						block: 'wpss/buyer-requests',
						attributes: attributes,
						EmptyResponsePlaceholder: () => el(Placeholder, {
							icon: 'megaphone',
							label: __('Buyer Requests', 'wp-sell-services'),
							instructions: __('No buyer requests found.', 'wp-sell-services')
						})
					})
				)
			);
		},
		save: function() {
			return null;
		}
	});

})(window.wp);
