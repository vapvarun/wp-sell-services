<?php
/**
 * The member cart: items, count and line price.
 *
 * @package WPSellServices
 * @since   1.9.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * A buyer's cart, with items whose service is no longer purchasable removed.
 *
 * Adding to the cart validates properly - add_to_cart() requires the service
 * to exist, be a wpss_service, and be published. Nothing re-checked on the way back out, and
 * ten call sites read `_wpss_cart` straight from user meta, so an item whose
 * service was deleted, trashed or paused AFTER it was added survived, rendered
 * and could be bought.
 *
 * It was bought. A 2026-09-23 smoke left this behind:
 *
 *     order #583  service_id=1604  total=0.000  status=pending_requirements
 *
 * Service 1604 no longer existed. The platform created a real order row for
 * it, at zero, parked in a status that waits on the buyer forever. On a live
 * marketplace the same thing happens when a vendor simply PAUSES a service -
 * a documented feature - and the vendor receives an order they cannot fulfil
 * for a price that never reached them (Basecamp 10330917388).
 *
 * Guarding the cart endpoint alone would have fixed the screen and left the
 * checkout path that actually creates the order untouched, so the check lives
 * here and every reader calls it.
 *
 * @since 1.7.2
 *
 * @param  int  $user_id     Buyer.
 * @param  bool $keep_paused Keep paused/unpublished items, marked unavailable,
 *                           so the buyer is told rather than watching the cart
 *                           empty itself. Deleted services are always dropped -
 *                           there is nothing to come back to. Checkout passes
 *                           false: an unavailable item must never reach an order.
 * @return array<string, array<string, mixed>> Cart items keyed as stored.
 */
function wpss_get_user_cart( int $user_id, bool $keep_paused = false ): array {
	$cart = get_user_meta( $user_id, '_wpss_cart', true );

	if ( ! is_array( $cart ) ) {
		return array();
	}

	$out     = array();
	$changed = false;

	foreach ( $cart as $key => $item ) {
		$service = get_post( (int) ( $item['service_id'] ?? 0 ) );

		$reason = wpss_service_unavailable_reason( (int) ( $item['service_id'] ?? 0 ) );

		if ( '' !== $reason ) {
			/*
			 * Never remove a line the buyer did not remove.
			 *
			 * This used to drop a DELETED service silently and keep only a
			 * paused one. On the cart screen that is indistinguishable from the
			 * site losing the item: a two-item $100 cart became one item and $75
			 * with nothing said. The WooCommerce rail was corrected first and
			 * this one was left behind - the same "fixed one rail, missed the
			 * other" mistake, on the rail every FREE install runs.
			 *
			 * Deleted and paused now read the same way on both rails: the line
			 * stays, says why it cannot be bought, is kept out of the subtotal,
			 * and is refused at checkout until the buyer removes it.
			 *
			 * The money paths still pass $keep_paused = false and drop it, so an
			 * unavailable line can never reach an order.
			 */
			if ( ! $keep_paused ) {
				$changed = true;
				continue;
			}

			$item['unavailable']        = true;
			$item['unavailable_reason'] = $reason;
		}

		$out[ $key ] = $item;
	}

	// Persist only the removal of genuinely dead rows, so a paused service
	// coming back does not find the buyer's cart already emptied.
	if ( $changed && ! $keep_paused ) {
		update_user_meta( $user_id, '_wpss_cart', $out );
	}

	return $out;
}

/**
 * How many lines of a member's cart can be bought now.
 *
 * The number on the header cart. It counted the stored rows, so a service the
 * vendor trashed or paused still showed as an item to check out (Basecamp
 * 10330917388).
 *
 * @since 1.8.0
 *
 * @param int $user_id Member.
 * @return int
 */
function wpss_get_cart_count( int $user_id ): int {
	return count( array_filter( wpss_get_user_cart( $user_id, true ), static fn( $item ) => empty( $item['unavailable'] ) ) );
}

/**
 * Price one cart line the way checkout will charge it.
 *
 * A cart item stores what the buyer chose - service, package, quantity and
 * the add-on selection (id, quantity, option, text) - never a price. Every
 * screen that shows a cart total (the cart page, GET /cart, checkout) prices
 * the line here through CheckoutIntentService::price_service_line(), so a
 * percentage or per-quantity add-on, or tax, cannot read one way in the cart
 * and charge another (Basecamp 10336467507, 10336467589). Older items that
 * stored add-on prices still work: any price in the selection is ignored.
 *
 * @since 1.8.0
 *
 * @param array<string, mixed> $item Cart item.
 * @return array<string, mixed>|\WP_Error The priced line (see price_service_line()).
 */
function wpss_price_cart_item( array $item ) {
	return \WPSellServices\Checkout\CheckoutIntentService::price_service_line(
		(int) ( $item['service_id'] ?? 0 ),
		(int) ( $item['package_id'] ?? 0 ),
		max( 1, (int) ( $item['quantity'] ?? 1 ) ),
		$item['addons'] ?? array()
	);
}
