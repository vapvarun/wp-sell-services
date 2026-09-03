<?php
/**
 * Checkout tax contract.
 *
 * A buyer was shown $100.30 and charged $85.00. Tax was written out inline in
 * four places and missing from a fifth - CheckoutIntentService, which is the
 * figure the gateway actually charges - so the 18% was displayed, recorded on
 * the order, and never collected from anybody (Basecamp 10254444011).
 *
 * The property that matters is simple and absolute: THE CHARGE EQUALS WHAT THE
 * BUYER WAS SHOWN. Everything below exists to hold that.
 *
 * Run: wp eval-file tests/test-checkout-tax-contract.php
 *
 * @package WPSellServices
 */

$GLOBALS['wpss_pass'] = 0;
$GLOBALS['wpss_fail'] = 0;

/**
 * Assert one condition.
 *
 * @param bool   $cond Condition.
 * @param string $msg  Message.
 * @return void
 */
function wpss_t( $cond, $msg ) {
	if ( $cond ) {
		++$GLOBALS['wpss_pass'];
		echo "  PASS  {$msg}\n";
	} else {
		++$GLOBALS['wpss_fail'];
		echo "  FAIL  {$msg}\n";
	}
}

echo "\nCheckout tax contract\n\n";

$service = get_posts(
	array(
		'post_type'      => 'wpss_service',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'author__not_in' => array( 1 ), // The probe buys as user 1, who cannot buy their own service.
	)
);

if ( ! $service ) {
	echo "  SKIP  no published service on this install\n";
	return;
}

$service_id = (int) $service[0];
$packages   = get_post_meta( $service_id, '_wpss_packages', true );
$price      = (float) ( $packages[0]['price'] ?? 0 );

$saved = get_option( 'wpss_tax', array() );

$intent_amount = static function () use ( $service_id ) {
	$svc = new \WPSellServices\Checkout\CheckoutIntentService();
	$ref = new ReflectionMethod( $svc, 'resolve_single' );
	$ref->setAccessible( true );
	$intent = $ref->invoke( $svc, array( 'service_id' => $service_id, 'package_id' => 0 ), 1 );
	return is_wp_error( $intent ) ? null : (float) $intent->amount;
};

// --- Exclusive tax: added on top -----------------------------------------
update_option(
	'wpss_tax',
	array( 'enable_tax' => true, 'tax_rate' => 18, 'tax_included' => false, 'tax_label' => 'Tax' )
);

$calc   = wpss_calculate_tax( $price, 0, $service_id );
$charge = $intent_amount();

wpss_t( abs( $calc['total'] - ( $price * 1.18 ) ) < 0.01, sprintf( 'exclusive tax adds 18%% (%.2f -> %.2f)', $price, $calc['total'] ) );
wpss_t( null !== $charge && abs( $charge - $calc['total'] ) < 0.01, sprintf( 'THE CHARGE EQUALS THE DISPLAYED TOTAL (%.2f)', (float) $charge ) );
wpss_t( null !== $charge && $charge > $price, 'the charge is more than the pre-tax price, i.e. tax is actually collected' );

// --- Inclusive tax: already inside the price ------------------------------
update_option(
	'wpss_tax',
	array( 'enable_tax' => true, 'tax_rate' => 18, 'tax_included' => true, 'tax_label' => 'Tax' )
);

$inc    = wpss_calculate_tax( $price, 0, $service_id );
$charge = $intent_amount();

wpss_t( abs( $inc['total'] - $price ) < 0.01, 'inclusive tax leaves the total at the sticker price' );
wpss_t( $inc['amount'] > 0 && $inc['amount'] < $price, 'inclusive tax is extracted from the price, not added to it' );
wpss_t( null !== $charge && abs( $charge - $price ) < 0.01, 'an inclusive-tax site is not overcharged by the rate' );

// --- Tax off --------------------------------------------------------------
update_option( 'wpss_tax', array( 'enable_tax' => false, 'tax_rate' => 18 ) );

$off    = wpss_calculate_tax( $price, 0, $service_id );
$charge = $intent_amount();

wpss_t( 0.0 === (float) $off['amount'], 'no tax is applied when tax is switched off' );
wpss_t( null !== $charge && abs( $charge - $price ) < 0.01, 'the charge is the bare price when tax is off' );

/*
 * --- Tax is applied ONCE, end to end ---------------------------------------
 *
 * Making the intent carry the taxed amount fixed the charge and broke the
 * order: settle_single() passed that figure through as the subtotal, and
 * create_order() taxed it a SECOND time. Pay $88.50, order total $104.43, and
 * commission billed on the tax as well (Basecamp 10254561978).
 *
 * The intent now carries both numbers - $amount is what the gateway charges,
 * $taxable_base is what the order row is built from - so this asserts they are
 * different in the right direction and that the difference is exactly one tax
 * pass.
 */
update_option(
	'wpss_tax',
	array( 'enable_tax' => true, 'tax_rate' => 18, 'tax_included' => false, 'tax_label' => 'Tax' )
);

$svc = new \WPSellServices\Checkout\CheckoutIntentService();
$ref = new ReflectionMethod( $svc, 'resolve_single' );
$ref->setAccessible( true );
$intent = $ref->invoke( $svc, array( 'service_id' => $service_id, 'package_id' => 0 ), 1 );

if ( ! is_wp_error( $intent ) ) {
	wpss_t( abs( $intent->taxable_base - $price ) < 0.01, sprintf( 'the intent carries the PRE-tax base (%.2f)', $intent->taxable_base ) );
	wpss_t( $intent->amount > $intent->taxable_base, 'the charge is the taxed figure, the base is not' );

	// The order row is built from the base, then taxed once. Taxing the CHARGE
	// instead is the regression, and it shows up as total > charge.
	$order_total = wpss_calculate_tax( $intent->taxable_base, 0, $service_id )['total'];
	wpss_t( abs( $order_total - $intent->amount ) < 0.01, sprintf( 'the order total equals the charge, not charge x 1.18 (%.2f)', $order_total ) );

	$double = wpss_calculate_tax( $intent->amount, 0, $service_id )['total'];
	wpss_t( abs( $order_total - $double ) > 0.01, sprintf( 'a second tax pass would have produced %.2f, and does not', $double ) );

	// Commission must come off the pre-tax base, so the platform never takes a
	// cut of the tax it is holding for someone else.
	wpss_t(
		abs( ( $intent->taxable_base - $intent->addons_total ) - $price ) < 0.01,
		'the subtotal handed to the order row is the pre-tax price'
	);

	/*
	 * Drive settle_single() for real.
	 *
	 * The assertions above check the INGREDIENTS - the intent carries the right
	 * two numbers and the helper does the right arithmetic. They all passed
	 * while settle_single() was still passing $charged_amount through as the
	 * subtotal, so they did not catch the regression they were written for.
	 *
	 * Only creating an order catches it, so that is what this does, and it
	 * removes the row afterwards.
	 */
	$provider = function_exists( 'wpss_get_order_provider' ) ? wpss_get_order_provider() : null;

	if ( $provider ) {
		$settle = new ReflectionMethod( $svc, 'settle_single' );
		$settle->setAccessible( true );
		$result = $settle->invoke( $svc, $intent, $provider, 'test', 'txn_tax_contract_probe', $intent->amount, $intent->currency );

		if ( ! empty( $result['success'] ) ) {
			global $wpdb;
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT subtotal, total, platform_fee FROM {$wpdb->prefix}wpss_orders WHERE id = %d",
					(int) $result['order_id']
				)
			);

			wpss_t( $row && abs( (float) $row->total - $intent->amount ) < 0.01, sprintf( 'THE ORDER TOTAL EQUALS THE AMOUNT CHARGED (%.2f vs %.2f)', $row ? (float) $row->total : 0, $intent->amount ) );
			wpss_t( $row && abs( (float) $row->subtotal - $price ) < 0.01, sprintf( 'the stored subtotal is the pre-tax price (%.2f)', $row ? (float) $row->subtotal : 0 ) );

			$rate = (float) ( get_option( 'wpss_commission', array() )['commission_rate'] ?? 10 );
			wpss_t(
				$row && abs( (float) $row->platform_fee - ( $price * $rate / 100 ) ) < 0.02,
				sprintf( 'commission is taken on the pre-tax price, not on the tax (%.2f)', $row ? (float) $row->platform_fee : 0 )
			);

			// Leave nothing behind.
			$wpdb->delete( $wpdb->prefix . 'wpss_orders', array( 'id' => (int) $result['order_id'] ) );
			$wpdb->delete( $wpdb->prefix . 'wpss_wallet_transactions', array( 'reference_id' => (int) $result['order_id'] ) );
		} else {
			echo "  SKIP  settle_single() did not create an order here\n";
		}
	}
}

/*
 * --- The cart is taxed like the single path -------------------------------
 *
 * With tax on, a two-item cart (25 + 35) was charged 60.00 while the single
 * path charged 29.50 for the same 25.00 service and every order row the cart
 * produced stored a taxed total (Basecamp 10264284228). The cart intent must
 * be the sum of the taxed rows it will settle into.
 */
update_option(
	'wpss_tax',
	array( 'enable_tax' => true, 'tax_rate' => 18, 'tax_included' => false, 'tax_label' => 'Tax' )
);

$two = get_posts(
	array(
		'post_type'      => 'wpss_service',
		'post_status'    => 'publish',
		'posts_per_page' => 2,
		'fields'         => 'ids',
		'author__not_in' => array( 1 ), // The probe buys as user 1, who cannot buy their own service.
		'meta_key'       => '_wpss_packages', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	)
);
$two = array_map( 'intval', $two );

if ( count( $two ) < 1 ) {
	echo "  SKIP  no service by another author for the cart probe\n";
} else {
	$cart = array();
	foreach ( array_pad( $two, 2, $two[0] ) as $i => $sid ) {
		$cart[ 'probe_' . $i ] = array(
			'service_id' => $sid,
			'package_id' => 0,
			'quantity'   => 1,
			'addons'     => array(),
		);
	}

	$saved_cart = get_user_meta( 1, '_wpss_cart', true );
	update_user_meta( 1, '_wpss_cart', $cart );

	$untaxed  = 0.0;
	$expected = 0.0;
	foreach ( $cart as $line ) {
		$p         = (float) ( get_post_meta( $line['service_id'], '_wpss_packages', true )[0]['price'] ?? 0 );
		$untaxed  += $p;
		$expected += (float) wpss_calculate_tax( $p, (int) get_post_field( 'post_author', $line['service_id'] ), $line['service_id'] )['total'];
	}

	$svc    = new \WPSellServices\Checkout\CheckoutIntentService();
	$intent = $svc->resolve( array( 'is_multi_checkout' => true ), 1 );

	if ( is_wp_error( $intent ) ) {
		wpss_t( false, 'cart intent resolves: ' . $intent->get_error_message() );
	} else {
		wpss_t( abs( $intent->amount - $expected ) < 0.01, sprintf( 'THE CART CHARGE IS THE TAXED TOTAL (%.2f, untaxed %.2f)', $intent->amount, $untaxed ) );
		wpss_t( $intent->amount > $untaxed, 'the cart charge is more than the pre-tax sum, i.e. tax is actually collected' );
		wpss_t( isset( $intent->tax ) && abs( $intent->tax - ( $expected - $untaxed ) ) < 0.01, sprintf( 'the cart intent carries the tax it charges (%.2f)', (float) ( $intent->tax ?? 0 ) ) );
		wpss_t( abs( $intent->taxable_base - $untaxed ) < 0.01, 'the cart intent carries the pre-tax base, like the single intent' );

		$provider = function_exists( 'wpss_get_order_provider' ) ? wpss_get_order_provider() : null;
		if ( $provider ) {
			$settle = new ReflectionMethod( $svc, 'settle_cart' );
			$settle->setAccessible( true );
			$result = $settle->invoke( $svc, $intent, $provider, 'test', 'txn_tax_contract_cart_probe' );

			if ( ! empty( $result['order_ids'] ) ) {
				global $wpdb;
				$ids  = implode( ',', array_map( 'intval', $result['order_ids'] ) );
				$rows = (float) $wpdb->get_var( "SELECT SUM(total) FROM {$wpdb->prefix}wpss_orders WHERE id IN ({$ids})" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

				wpss_t( count( $result['order_ids'] ) === count( $cart ), 'one order row per cart line' );
				wpss_t( abs( $rows - $intent->amount ) < 0.01, sprintf( 'THE SUM OF THE ORDER ROWS EQUALS THE CART CHARGE (%.2f vs %.2f)', $rows, $intent->amount ) );

				$wpdb->query( "DELETE FROM {$wpdb->prefix}wpss_orders WHERE id IN ({$ids})" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( "DELETE FROM {$wpdb->prefix}wpss_wallet_transactions WHERE reference_id IN ({$ids})" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			} else {
				echo "  SKIP  settle_cart() did not create orders here\n";
			}
		}
	}

	if ( is_array( $saved_cart ) && ! empty( $saved_cart ) ) {
		update_user_meta( 1, '_wpss_cart', $saved_cart );
	} else {
		delete_user_meta( 1, '_wpss_cart' );
	}
}

/*
 * --- PayPal charges what Stripe charges ------------------------------------
 *
 * PayPalGateway priced single checkout itself (package + add-ons, no tax) and
 * rebuilt the order subtotal from the gateway figure on capture. It cannot be
 * driven without API keys, so this holds the property structurally: every
 * PayPal amount comes out of CheckoutIntentService, capture verifies the
 * captured amount against it and settles through it, and no gateway-side
 * price arithmetic remains.
 */
$paypal_src = file_get_contents( dirname( __DIR__ ) . '/src/Integrations/PayPal/PayPalGateway.php' );
$stripe_src = file_get_contents( dirname( __DIR__ ) . '/src/Integrations/Stripe/StripeGateway.php' );

wpss_t( substr_count( $paypal_src, 'CheckoutIntentService' ) >= 2, 'PayPal resolves its amount through CheckoutIntentService, like Stripe' );
wpss_t( false !== strpos( $paypal_src, '->settle( $intent' ), 'PayPal settles through CheckoutIntentService::settle(), like Stripe' );
wpss_t( false !== strpos( $paypal_src, 'wpss_amounts_match(' ), 'PayPal capture verifies the captured amount against the intent' );
wpss_t( false === strpos( $paypal_src, "_wpss_packages" ), 'PayPal does not read package prices itself' );
wpss_t( false === strpos( $paypal_src, '- $addons_total' ), 'PayPal does not rebuild the subtotal from the gateway amount' );
wpss_t( false === strpos( $paypal_src, '->create_orders_from_cart(' ) && false === strpos( $stripe_src, '->create_orders_from_cart(' ), 'neither card rail creates cart orders outside settle()' );

// --- One implementation ---------------------------------------------------
update_option( 'wpss_tax', $saved );

$roots = array(
	dirname( __DIR__ ) . '/src/Integrations/Standalone/StandaloneCheckoutProvider.php',
	dirname( __DIR__ ) . '/src/Integrations/Standalone/StandaloneOrderProvider.php',
	dirname( __DIR__ ) . '/src/Checkout/CheckoutIntentService.php',
);

$inline = array();
foreach ( $roots as $file ) {
	$src = file_get_contents( $file );
	// The shape of the old inline maths, in either direction.
	if ( preg_match( '#/ \( 1 \+ \$[a-z_]*tax_rate / 100 \)|\$[a-z_]*tax_rate / 100 \)#', $src ) ) {
		$inline[] = basename( $file );
	}
}

wpss_t( empty( $inline ), 'no site computes tax inline any more (' . ( $inline ? implode( ', ', $inline ) : 'all use wpss_calculate_tax' ) . ')' );

foreach ( $roots as $file ) {
	wpss_t(
		false !== strpos( file_get_contents( $file ), 'wpss_calculate_tax(' ),
		basename( $file ) . ' uses the shared helper'
	);
}

// --- Commission is untouched ---------------------------------------------
// Tax is not revenue to split. CommissionService works from subtotal +
// addons_total, which is the pre-tax base, and must stay that way.
$commission_src = file_get_contents( dirname( __DIR__ ) . '/src/Services/CommissionService.php' );
wpss_t(
	false !== strpos( $commission_src, '$order->subtotal + (float) $order->addons_total' ),
	'commission is still calculated on the pre-tax base'
);
wpss_t(
	false === strpos( $commission_src, 'wpss_calculate_tax' ),
	'commission does not consult tax at all'
);

echo "\n{$GLOBALS['wpss_pass']} passed, {$GLOBALS['wpss_fail']} failed\n";
