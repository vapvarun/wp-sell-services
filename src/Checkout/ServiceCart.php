<?php
/**
 * Puts a priced service line in the buyer's cart on whichever rail is active.
 *
 * One door for every "add this service to my cart" path (the order modal, Order
 * Again): the rail refuses up front when it cannot check the service out, an
 * adapter such as WooCommerce handles its own cart through
 * `wpss_add_service_to_cart`, and the standalone rail keeps the selection in user meta.
 *
 * @package WPSellServices\Checkout
 * @since   1.9.0
 */

declare(strict_types=1);

namespace WPSellServices\Checkout;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Rail-aware add-to-cart.
 *
 * @since 1.9.0
 */
final class ServiceCart {

	/**
	 * The checkout URL for a priced line, carrying the whole selection.
	 *
	 * Ids travel in the URL; a quantity, option or text needs the full selection
	 * beside them or checkout would drop it. A rail with no catalog checkout
	 * returns an empty URL, and that is refused here rather than handed on: the
	 * buyer got "Added to cart", a cart row and a Checkout link that reloaded the
	 * service page forever (Basecamp 10268056147, 10268056083).
	 *
	 * @param int                  $service_id Service post ID.
	 * @param array<string, mixed> $line       Priced line (CheckoutIntentService::price_service_line()).
	 * @param int                  $quantity   Package quantity, appended when above one.
	 * @return string|WP_Error
	 */
	public static function checkout_url( int $service_id, array $line, int $quantity = 1 ) {
		$url = wpss_get_service_checkout_url( $service_id, (int) $line['package_id'], array_map( 'intval', array_column( $line['addons'], 'id' ) ) );

		if ( '' === $url ) {
			$adapter = wpss_get_active_adapter();

			return new WP_Error(
				'wpss_checkout_unavailable',
				sprintf(
					/* translators: %s: name of the active store platform, e.g. Fluent Cart */
					__( 'Checkout is not available for this service on %s, so nothing was added to your cart. Please let the site owner know.', 'wp-sell-services' ),
					$adapter ? $adapter->get_name() : __( 'this store platform', 'wp-sell-services' )
				),
				array( 'status' => 409 )
			);
		}

		if ( $quantity > 1 ) {
			$url = add_query_arg( 'quantity', $quantity, $url );
		}

		$meta = CheckoutIntentService::selection_metadata( $line['addons'] );

		return empty( $meta['addon_sel'] ) ? $url : add_query_arg( 'addon_sel', rawurlencode( $meta['addon_sel'] ), $url );
	}

	/**
	 * Add a priced line to the buyer's cart.
	 *
	 * @param int                  $user_id    Buyer.
	 * @param int                  $service_id Service post ID.
	 * @param array<string, mixed> $line       Priced line (CheckoutIntentService::price_service_line()).
	 * @param int                  $quantity   Package quantity.
	 * @return array{cart_count:int, checkout_url:string}|WP_Error
	 */
	public static function add( int $user_id, int $service_id, array $line, int $quantity = 1 ) {
		$adapter = wpss_get_active_adapter();

		if ( ! $adapter ) {
			return new WP_Error( 'wpss_no_platform', __( 'No e-commerce platform is active. Please check your settings.', 'wp-sell-services' ), array( 'status' => 409 ) );
		}

		$cart_item = array(
			'service_id' => $service_id,
			'package_id' => (int) $line['package_id'],
			'package'    => $line['package'],
			// The selection only: the cart prices it again on every read
			// (wpss_price_cart_item()), so a price can never go stale here.
			'addons'     => array_map(
				static fn( $addon ) => array_intersect_key( $addon, array_flip( array( 'id', 'quantity', 'option', 'text' ) ) ),
				$line['addons']
			),
			'quantity'   => $quantity,
			'total'      => (float) $line['total'],
		);

		// Resolved before anything is written to any cart: a rail that cannot
		// check this service out refuses here instead of leaving a dead end.
		$checkout_url = self::checkout_url( $service_id, $line, $quantity );

		if ( is_wp_error( $checkout_url ) ) {
			return $checkout_url;
		}

		/**
		 * Filter to let e-commerce adapters handle cart addition natively.
		 *
		 * When a non-standalone adapter (e.g. WooCommerce) is active, it should
		 * handle adding the service to its own cart system instead of the
		 * standalone user-meta cart.
		 *
		 * Return an array with 'handled' => true to skip standalone cart logic.
		 * Optionally include 'checkout_url' and 'cart_count' in the returned array.
		 *
		 * @since 1.3.0
		 *
		 * @param array|false                                                           $result    False by default (not handled).
		 * @param array                                                                 $cart_item Cart item data.
		 * @param \WPSellServices\Integrations\Contracts\EcommerceAdapterInterface|null $adapter   Active adapter.
		 */
		$adapter_result = apply_filters( 'wpss_add_service_to_cart', false, $cart_item, $adapter );

		if ( is_array( $adapter_result ) && ! empty( $adapter_result['handled'] ) ) {
			if ( ! empty( $adapter_result['error'] ) ) {
				return new WP_Error( 'wpss_cart_refused', (string) $adapter_result['error'], array( 'status' => 409 ) );
			}

			if ( ! empty( $adapter_result['checkout_url'] ) ) {
				$checkout_url = (string) $adapter_result['checkout_url'];
				if ( $quantity > 1 ) {
					$checkout_url = add_query_arg( 'quantity', $quantity, $checkout_url );
				}
			}

			return array(
				'cart_count'   => (int) ( $adapter_result['cart_count'] ?? 1 ),
				'checkout_url' => $checkout_url,
			);
		}

		// Standalone cart: stored in user meta.
		$cart = get_user_meta( $user_id, '_wpss_cart', true );

		if ( ! is_array( $cart ) ) {
			$cart = array();
		}

		$item_key              = md5( $service_id . '-' . $line['package_id'] . '-' . wp_json_encode( $cart_item['addons'] ) );
		$cart_item['added_at'] = current_time( 'mysql', true );
		$cart[ $item_key ]     = $cart_item;

		update_user_meta( $user_id, '_wpss_cart', $cart );

		return array(
			'cart_count'   => wpss_get_cart_count( $user_id ),
			'checkout_url' => $checkout_url,
		);
	}
}
