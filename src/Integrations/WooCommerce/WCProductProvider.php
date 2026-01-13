<?php
/**
 * WooCommerce Product Provider
 *
 * @package WPSellServices\Integrations\WooCommerce
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Integrations\WooCommerce;

use WPSellServices\Integrations\Contracts\ProductProviderInterface;
use WPSellServices\Models\Service;
use WPSellServices\PostTypes\ServicePostType;

/**
 * Provides product functionality through WooCommerce.
 *
 * @since 1.0.0
 */
class WCProductProvider implements ProductProviderInterface {

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'product_type_selector', array( $this, 'add_service_type_option' ) );
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'add_service_options' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_service_meta' ) );
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_service_data_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_service_data_panel' ) );
	}

	/**
	 * Check if a product is marked as a service.
	 *
	 * @param int $product_id Platform product ID.
	 * @return bool
	 */
	public function is_service_product( int $product_id ): bool {
		return 'yes' === get_post_meta( $product_id, '_wpss_is_service', true );
	}

	/**
	 * Get service data from platform product.
	 *
	 * @param int $product_id Platform product ID.
	 * @return Service|null
	 */
	public function get_service( int $product_id ): ?Service {
		if ( ! $this->is_service_product( $product_id ) ) {
			return null;
		}

		$service_id = get_post_meta( $product_id, '_wpss_service_id', true );

		if ( $service_id ) {
			return wpss_get_service( (int) $service_id );
		}

		// Create Service from WC product data as fallback.
		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return null;
		}

		$service               = new Service();
		$service->id           = $product_id;
		$service->title        = $product->get_name();
		$service->description  = $product->get_description();
		$service->excerpt      = $product->get_short_description();
		$service->vendor_id    = (int) get_post_field( 'post_author', $product_id );
		$service->status       = $product->get_status();
		$service->thumbnail_id = $product->get_image_id();

		return $service;
	}

	/**
	 * Get vendor/author IDs for a service.
	 *
	 * @param int $product_id Product ID.
	 * @return int[]
	 */
	public function get_service_vendors( int $product_id ): array {
		$author_id = (int) get_post_field( 'post_author', $product_id );

		if ( ! $author_id ) {
			return array();
		}

		return array( $author_id );
	}

	/**
	 * Get service requirements configuration.
	 *
	 * @param int $product_id Product ID.
	 * @return array
	 */
	public function get_requirements( int $product_id ): array {
		$service_id = get_post_meta( $product_id, '_wpss_service_id', true );

		if ( $service_id ) {
			return get_post_meta( (int) $service_id, '_wpss_requirements', true ) ?: array();
		}

		return get_post_meta( $product_id, '_wpss_requirements', true ) ?: array();
	}

	/**
	 * Get estimated delivery time.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	public function get_delivery_time( int $product_id ): string {
		$days = (int) get_post_meta( $product_id, '_wpss_delivery_days', true );

		if ( ! $days ) {
			$service_id = get_post_meta( $product_id, '_wpss_service_id', true );
			if ( $service_id ) {
				$days = (int) get_post_meta( (int) $service_id, '_wpss_fastest_delivery', true );
			}
		}

		if ( ! $days ) {
			return '';
		}

		if ( 1 === $days ) {
			return __( '1 day', 'wp-sell-services' );
		}

		/* translators: %d: number of days */
		return sprintf( __( '%d days', 'wp-sell-services' ), $days );
	}

	/**
	 * Mark a product as service type.
	 *
	 * @param int  $product_id Product ID.
	 * @param bool $is_service Whether it's a service.
	 * @return void
	 */
	public function set_service_type( int $product_id, bool $is_service ): void {
		update_post_meta( $product_id, '_wpss_is_service', $is_service ? 'yes' : 'no' );
	}

	/**
	 * Add service type option to product editor.
	 *
	 * @param array $options Existing product type options.
	 * @return array
	 */
	public function add_service_type_option( array $options ): array {
		// We don't add a new product type, but use a checkbox instead.
		return $options;
	}

	/**
	 * Add service options to product general tab.
	 *
	 * @return void
	 */
	public function add_service_options(): void {
		global $post;

		echo '<div class="options_group show_if_simple show_if_variable">';

		woocommerce_wp_checkbox(
			array(
				'id'          => '_wpss_is_service',
				'label'       => __( 'This is a Service', 'wp-sell-services' ),
				'description' => __( 'Enable to sell this product as a service with requirements, delivery, and messaging.', 'wp-sell-services' ),
			)
		);

		// Link to existing service CPT.
		$services = get_posts(
			array(
				'post_type'      => ServicePostType::POST_TYPE,
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post_status'    => 'publish',
			)
		);

		$service_options = array( '' => __( '— Create new service —', 'wp-sell-services' ) );

		foreach ( $services as $service ) {
			$service_options[ $service->ID ] = $service->post_title;
		}

		woocommerce_wp_select(
			array(
				'id'          => '_wpss_service_id',
				'label'       => __( 'Link to Service', 'wp-sell-services' ),
				'description' => __( 'Link this product to an existing service or create a new one.', 'wp-sell-services' ),
				'options'     => $service_options,
				'class'       => 'wc-enhanced-select',
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => '_wpss_delivery_days',
				'label'             => __( 'Delivery Time (days)', 'wp-sell-services' ),
				'description'       => __( 'Default delivery time in days.', 'wp-sell-services' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '1',
					'step' => '1',
				),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => '_wpss_revisions',
				'label'             => __( 'Revisions Included', 'wp-sell-services' ),
				'description'       => __( 'Number of revisions included. Use -1 for unlimited.', 'wp-sell-services' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '-1',
					'step' => '1',
				),
			)
		);

		echo '</div>';
	}

	/**
	 * Add service data tab.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array
	 */
	public function add_service_data_tab( array $tabs ): array {
		$tabs['wpss_service'] = array(
			'label'    => __( 'Service Settings', 'wp-sell-services' ),
			'target'   => 'wpss_service_data',
			'class'    => array( 'show_if_wpss_service' ),
			'priority' => 25,
		);

		return $tabs;
	}

	/**
	 * Render service data panel.
	 *
	 * @return void
	 */
	public function render_service_data_panel(): void {
		global $post;

		echo '<div id="wpss_service_data" class="panel woocommerce_options_panel hidden">';
		echo '<div class="options_group">';

		echo '<p class="form-field">';
		echo '<label>' . esc_html__( 'Requirements', 'wp-sell-services' ) . '</label>';
		echo '<span class="description">' . esc_html__( 'Configure service requirements in the linked Service post.', 'wp-sell-services' ) . '</span>';
		echo '</p>';

		$service_id = get_post_meta( $post->ID, '_wpss_service_id', true );

		if ( $service_id ) {
			$edit_link = get_edit_post_link( (int) $service_id );
			echo '<p class="form-field">';
			echo '<a href="' . esc_url( $edit_link ) . '" class="button" target="_blank">';
			echo esc_html__( 'Edit Linked Service', 'wp-sell-services' );
			echo '</a>';
			echo '</p>';
		}

		echo '</div>';
		echo '</div>';
	}

	/**
	 * Save service meta on product save.
	 *
	 * @param int $product_id Product ID.
	 * @return void
	 */
	public function save_service_meta( int $product_id ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$is_service    = isset( $_POST['_wpss_is_service'] ) ? 'yes' : 'no';
		$service_id    = isset( $_POST['_wpss_service_id'] ) ? absint( $_POST['_wpss_service_id'] ) : 0;
		$delivery_days = isset( $_POST['_wpss_delivery_days'] ) ? absint( $_POST['_wpss_delivery_days'] ) : 7;
		$revisions     = isset( $_POST['_wpss_revisions'] ) ? intval( $_POST['_wpss_revisions'] ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		update_post_meta( $product_id, '_wpss_is_service', $is_service );
		update_post_meta( $product_id, '_wpss_delivery_days', $delivery_days );
		update_post_meta( $product_id, '_wpss_revisions', $revisions );

		if ( $service_id ) {
			update_post_meta( $product_id, '_wpss_service_id', $service_id );

			// Store product ID in service meta.
			$platform_ids                = get_post_meta( $service_id, '_wpss_platform_ids', true ) ?: array();
			$platform_ids['woocommerce'] = $product_id;
			update_post_meta( $service_id, '_wpss_platform_ids', $platform_ids );
		}
	}

	/**
	 * Sync service CPT with platform product.
	 *
	 * @param int $service_id  Service CPT ID.
	 * @param int $product_id  Platform product ID.
	 * @return bool
	 */
	public function sync_with_service( int $service_id, int $product_id ): bool {
		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return false;
		}

		// Update service with product data.
		wp_update_post(
			array(
				'ID'           => $service_id,
				'post_title'   => $product->get_name(),
				'post_content' => $product->get_description(),
				'post_excerpt' => $product->get_short_description(),
			)
		);

		// Update meta.
		if ( $product->get_image_id() ) {
			set_post_thumbnail( $service_id, $product->get_image_id() );
		}

		// Store platform mapping.
		$platform_ids                = get_post_meta( $service_id, '_wpss_platform_ids', true ) ?: array();
		$platform_ids['woocommerce'] = $product_id;
		update_post_meta( $service_id, '_wpss_platform_ids', $platform_ids );

		update_post_meta( $product_id, '_wpss_service_id', $service_id );
		update_post_meta( $product_id, '_wpss_is_service', 'yes' );

		return true;
	}

	/**
	 * Create or update WooCommerce product from service CPT.
	 *
	 * Called when a service is published via the wizard to sync to WooCommerce.
	 *
	 * @param int $service_id Service CPT ID.
	 * @return int|false WooCommerce product ID or false on failure.
	 */
	public function sync_service_to_product( int $service_id ): int|false {
		$service = get_post( $service_id );

		if ( ! $service || 'wpss_service' !== $service->post_type ) {
			return false;
		}

		// Check if product already exists.
		$platform_ids = get_post_meta( $service_id, '_wpss_platform_ids', true ) ?: array();
		$product_id   = $platform_ids['woocommerce'] ?? 0;

		// Get service data.
		$packages      = get_post_meta( $service_id, '_wpss_packages', true ) ?: array();
		$basic_package = $packages[0] ?? array();
		$price         = ! empty( $basic_package['price'] ) ? floatval( $basic_package['price'] ) : 0;
		$delivery_days = ! empty( $basic_package['delivery_days'] ) ? absint( $basic_package['delivery_days'] ) : 7;
		$revisions     = isset( $basic_package['revisions'] ) ? intval( $basic_package['revisions'] ) : 0;
		$thumbnail_id  = get_post_thumbnail_id( $service_id );

		if ( $product_id ) {
			// Update existing product.
			$product = wc_get_product( $product_id );

			if ( ! $product ) {
				$product_id = 0;
			}
		}

		if ( ! $product_id ) {
			// Create new simple product.
			$product = new \WC_Product_Simple();
		}

		// Set product data.
		$product->set_name( $service->post_title );
		$product->set_description( $service->post_content );
		$product->set_short_description( get_the_excerpt( $service_id ) );
		$product->set_status( $service->post_status );
		$product->set_catalog_visibility( 'visible' );
		$product->set_regular_price( (string) $price );
		$product->set_sold_individually( true );
		$product->set_virtual( true );

		if ( $thumbnail_id ) {
			$product->set_image_id( $thumbnail_id );
		}

		// Save the product.
		$product_id = $product->save();

		if ( ! $product_id ) {
			return false;
		}

		// Set service meta on product.
		update_post_meta( $product_id, '_wpss_is_service', 'yes' );
		update_post_meta( $product_id, '_wpss_service_id', $service_id );
		update_post_meta( $product_id, '_wpss_delivery_days', $delivery_days );
		update_post_meta( $product_id, '_wpss_revisions', $revisions );

		// Update platform IDs on service.
		$platform_ids['woocommerce'] = $product_id;
		update_post_meta( $service_id, '_wpss_platform_ids', $platform_ids );

		// Sync categories if mapped.
		$service_categories = wp_get_object_terms( $service_id, 'wpss_service_category', array( 'fields' => 'ids' ) );
		if ( ! is_wp_error( $service_categories ) && ! empty( $service_categories ) ) {
			// Map service categories to WC product categories if mapping exists.
			$wc_cat_ids = array();
			foreach ( $service_categories as $service_cat_id ) {
				$mapped_wc_cat = get_term_meta( $service_cat_id, '_wpss_wc_category_id', true );
				if ( $mapped_wc_cat ) {
					$wc_cat_ids[] = (int) $mapped_wc_cat;
				}
			}
			if ( ! empty( $wc_cat_ids ) ) {
				wp_set_object_terms( $product_id, $wc_cat_ids, 'product_cat' );
			}
		}

		/**
		 * Fires after a service is synced to WooCommerce product.
		 *
		 * @since 1.0.0
		 * @param int $service_id Service CPT ID.
		 * @param int $product_id WooCommerce product ID.
		 */
		do_action( 'wpss_service_synced_to_wc_product', $service_id, $product_id );

		return $product_id;
	}
}
