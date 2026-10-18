<?php
/**
 * Pro follow-ups contract (Basecamp 10267994010, items 5, 6 and 8).
 *
 * Three invariants that had no test:
 *
 *   5. The FluentCart and EDD rails read service requirements through Free's
 *      wpss_get_service_requirements(), so a question renders with its real
 *      label, its configured type and its options - and the message for a
 *      missed required field names the question. The raw reads they replaced
 *      keyed on `question`, a shape 1.7.1 no longer writes, so every currently
 *      saved service rendered unlabelled textareas.
 *   6. Automatic license re-checks are paused on local and development sites.
 *      A placeholder key answered "invalid", check_license() overwrote the
 *      stored status object and Pro went dark on the developer's own copy.
 *   8. The Razorpay setup guide lists exactly the webhook events
 *      handle_webhook() acts on - no more payment.captured, which that method
 *      deliberately ignores.
 *
 * Restores everything it touches: the license option byte-exact, the cron
 * schedule it found, the filters it adds and the throwaway posts it creates.
 *
 * Run: wp eval-file tests/pro/test-pro-followups.php
 *
 * @package WPSellServices
 */

namespace FluentCart\App\Helpers {
	// Test-only cart so the checkout provider's server-side read has something
	// to walk on a sandbox with no FluentCart. Never declared when the real
	// plugin is active.
if ( ! defined( 'WPSS_PRO_VERSION' ) ) {
	echo "SKIP  Pro is not active\n";
	return;
}

require_once dirname( __DIR__ ) . '/exit-on-fail.php';

	if ( ! class_exists( __NAMESPACE__ . '\CartHelper' ) ) {
		/**
		 * Minimal stand-in for FluentCart's cart helper.
		 */
		class CartHelper {
			/**
			 * The cart the test put in place.
			 *
			 * @return object
			 */
			public static function getCart() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
				return (object) array( 'cart_data' => $GLOBALS['wpss_fc_cart'] ?? array() );
			}
		}
	}
}

namespace {

	use WPSellServicesPro\Integrations\EDD\EDDProductProvider;
	use WPSellServicesPro\Integrations\FluentCart\FluentCartCheckoutProvider;
	use WPSellServicesPro\Integrations\FluentCart\FluentCartProductProvider;
	use WPSellServicesPro\License\Manager;

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

	echo "\nPro follow-ups contract (items 5, 6, 8)\n\n";

	// -----------------------------------------------------------------------
	// Item 5: requirements on the beta rails.
	// -----------------------------------------------------------------------

	add_filter( 'wpss_pro_beta_rails', '__return_true', 99 );
	wpss_t(
		function_exists( 'WPSellServicesPro\\wpss_pro_beta_rails_enabled' ) && \WPSellServicesPro\wpss_pro_beta_rails_enabled(),
		'item 5: beta rails filter is on for this run'
	);

	// A service saved the way 1.7.1 saves it, plus one row in the retired
	// wizard shape so the normalisation is exercised too.
	$service_id = wp_insert_post(
		array(
			'post_type'   => 'wpss_service',
			'post_title'  => 'FU568 throwaway service',
			'post_status' => 'publish',
		)
	);

	wpss_save_service_requirements(
		$service_id,
		array(
			array(
				'label'    => 'Brand name',
				'type'     => 'text',
				'required' => true,
			),
			array(
				'label'    => 'Preferred palette',
				'type'     => 'select',
				'options'  => array( 'Warm', 'Cool', 'Monochrome' ),
				'required' => true,
			),
			array(
				'label'    => 'Logo source file',
				'type'     => 'file',
				'required' => true,
			),
		)
	);

	// A fourth row appended in the retired wizard shape, written straight to
	// the meta so the normalisation happens on READ - which is the whole point
	// of routing these providers through Free's reader.
	$stored   = get_post_meta( $service_id, '_wpss_requirements', true );
	$stored[] = array(
		'question' => 'Turnaround',
		'type'     => 'dropdown',
		'options'  => 'Standard,Rush',
	);
	update_post_meta( $service_id, '_wpss_requirements', $stored );

	$product_id = wp_insert_post(
		array(
			'post_type'   => 'post',
			'post_title'  => 'FU568 throwaway FluentCart product',
			'post_status' => 'publish',
		)
	);
	update_post_meta( $product_id, '_wpss_service_id', $service_id );

	$fc_product = new FluentCartProductProvider();
	$rows       = $fc_product->get_requirements( $product_id );

	wpss_t( 4 === count( $rows ), 'item 5: FluentCart product provider returns every requirement row' );
	wpss_t(
		'Brand name' === ( $rows[0]['label'] ?? '' ) && 'Preferred palette' === ( $rows[1]['label'] ?? '' ),
		'item 5: FluentCart product provider rows carry label, not question'
	);
	wpss_t(
		'select' === ( $rows[1]['type'] ?? '' ) && array( 'Warm', 'Cool', 'Monochrome' ) === ( $rows[1]['options'] ?? array() ),
		'item 5: FluentCart product provider keeps the select type and its options'
	);
	wpss_t(
		'select' === ( $rows[3]['type'] ?? '' ) && array( 'Standard', 'Rush' ) === ( $rows[3]['options'] ?? array() ),
		'item 5: FluentCart product provider normalises the retired wizard shape'
	);

	// EDD stores its rows on the download post under the same key.
	$download_id = wp_insert_post(
		array(
			'post_type'   => 'post',
			'post_title'  => 'FU568 throwaway EDD download',
			'post_status' => 'publish',
		)
	);
	update_post_meta(
		$download_id,
		'_wpss_requirements',
		array(
			array(
				'question' => 'Target audience',
				'type'     => 'dropdown',
				'choices'  => 'B2B,B2C',
				'required' => true,
			),
		)
	);

	$edd_rows = ( new EDDProductProvider() )->get_requirements( $download_id );
	wpss_t(
		'Target audience' === ( $edd_rows[0]['label'] ?? '' )
			&& 'select' === ( $edd_rows[0]['type'] ?? '' )
			&& array( 'B2B', 'B2C' ) === ( $edd_rows[0]['options'] ?? array() ),
		'item 5: EDD product provider returns label, type and options from the canonical shape'
	);

	// The rendered checkout inputs.
	$GLOBALS['wpss_fc_cart'] = array( array( 'post_id' => $product_id ) );

	$checkout = new FluentCartCheckoutProvider();

	ob_start();
	$checkout->render_service_checkout_fields( array( 'cart' => (object) array( 'cart_data' => $GLOBALS['wpss_fc_cart'] ) ) );
	$html = (string) ob_get_clean();

	wpss_t( false !== strpos( $html, '_0">Brand name' ), 'item 5: checkout renders the requirement label' );
	wpss_t(
		false !== strpos( $html, '<select id="service_req_' . $service_id . '_1"' ),
		'item 5: checkout renders a select for the select type'
	);
	wpss_t(
		false !== strpos( $html, '<option value="Monochrome">Monochrome</option>' ),
		'item 5: checkout renders the select options'
	);
	wpss_t(
		false !== strpos( $html, 'type="file" id="service_req_' . $service_id . '_2"' ),
		'item 5: checkout renders a file input for the file type'
	);
	wpss_t(
		false === strpos( $html, 'type="file" id="service_req_' . $service_id . '_2" name="service_req_' . $service_id . '_2" class="wpss-fc-requirements__input" required' ),
		'item 5: the file requirement is not marked required at checkout'
	);

	// A missed required field names the question.
	$verdict = $checkout->validate_service_requirements(
		true,
		array(
			'wpss_requirements_present'          => '1',
			'service_req_' . $service_id . '_0'  => 'Acme',
			'service_req_' . $service_id . '_1'  => '',
		)
	);

	wpss_t( is_wp_error( $verdict ), 'item 5: a missed required requirement blocks the checkout' );
	wpss_t(
		is_wp_error( $verdict ) && false !== strpos( $verdict->get_error_message(), 'Preferred palette' ),
		'item 5: the block message names the question - got: ' . ( is_wp_error( $verdict ) ? $verdict->get_error_message() : 'no error' )
	);

	$verdict = $checkout->validate_service_requirements(
		true,
		array(
			'wpss_requirements_present'         => '1',
			'service_req_' . $service_id . '_0' => 'Acme',
			'service_req_' . $service_id . '_1' => 'Warm',
		)
	);
	wpss_t( true === $verdict, 'item 5: the file requirement alone does not block a completed checkout' );

	// -----------------------------------------------------------------------
	// Item 6: license re-checks paused on local / development.
	// -----------------------------------------------------------------------

	$manager      = new Manager();
	$status_key   = 'wpss_pro_license_key_license';
	$cron_hook    = 'edd_sl_sdk_weekly_license_check_wp-sell-services-pro';
	$status_orig  = get_option( $status_key );
	$status_md5   = md5( maybe_serialize( $status_orig ) );
	$cron_orig    = wp_get_schedule( $cron_hook );

	$has_guard = method_exists( $manager, 'is_local_environment' );

	/**
	 * The guard's verdict, false when the method does not exist yet.
	 *
	 * @param Manager $manager License manager.
	 * @param bool    $has     Whether the method exists.
	 * @return bool
	 */
	$paused = static function ( $manager, $has ) {
		return $has && $manager->is_local_environment();
	};

	wpss_t( $has_guard, 'item 6: Manager::is_local_environment exists' );
	wpss_t(
		$paused( $manager, $has_guard ),
		'item 6: is_local_environment is true on this sandbox (env: ' . wp_get_environment_type() . ')'
	);

	// The License page render must not touch the stored status object.
	$pro = new \WPSellServicesPro\Core\Pro( (object) array() );
	ob_start();
	$pro->render_license_page();
	$page = (string) ob_get_clean();

	$after_md5 = md5( maybe_serialize( get_option( $status_key ) ) );
	wpss_t( $status_md5 === $after_md5, 'item 6: rendering the License page leaves the stored license option byte-identical' );

	if ( $status_md5 !== $after_md5 ) {
		update_option( $status_key, $status_orig );
		echo "  ....  restored {$status_key} from the snapshot\n";
	}

	wpss_t(
		false !== strpos( $page, 'Automatic license re-checks are paused' ),
		'item 6: the License page says re-checks are paused on this environment'
	);

	$manager->schedule_daily_check();
	wpss_t( false === wp_get_schedule( $cron_hook ), 'item 6: no daily license cron is scheduled while local' );

	add_filter( 'wpss_pro_pause_license_recheck', '__return_false', 99 );
	wpss_t( $has_guard && ! $paused( $manager, $has_guard ), 'item 6: the force filter re-enables the re-check' );
	$manager->schedule_daily_check();
	wpss_t( 'daily' === wp_get_schedule( $cron_hook ), 'item 6: with the filter forced on, the daily cron is scheduled again' );
	remove_filter( 'wpss_pro_pause_license_recheck', '__return_false', 99 );

	// -----------------------------------------------------------------------
	// Item 8: the Razorpay instruction list matches the handled events.
	// -----------------------------------------------------------------------

	/**
	 * Source text of one RazorpayGateway method, as loaded on this site.
	 *
	 * @param string $method Method name.
	 * @return string Empty when the method does not exist.
	 */
	function wpss_fu568_rzp_source( string $method ): string {
		$class = \WPSellServicesPro\Integrations\Razorpay\RazorpayGateway::class;
		if ( ! method_exists( $class, $method ) ) {
			return '';
		}
		$ref   = new \ReflectionMethod( $class, $method );
		$lines = file( (string) $ref->getFileName() );
		return implode( '', array_slice( (array) $lines, $ref->getStartLine() - 1, $ref->getEndLine() - $ref->getStartLine() + 1 ) );
	}

	preg_match_all( "/case '([a-z._]+)':/", wpss_fu568_rzp_source( 'handle_webhook' ), $m );
	$handled = $m[1] ?? array();

	preg_match_all( '/<code>([a-z]+\.[a-z]+)<\/code>/', wpss_fu568_rzp_source( 'render_settings_fields' ), $m );
	$listed = $m[1] ?? array();

	sort( $handled );
	sort( $listed );

	wpss_t( ! empty( $handled ) && ! empty( $listed ), 'item 8: found both the handled events and the instruction list' );
	wpss_t(
		$handled === $listed,
		'item 8: the setup guide lists exactly the handled events - handled: ' . implode( ',', $handled ) . ' | listed: ' . implode( ',', $listed )
	);

	// -----------------------------------------------------------------------
	// Restore.
	// -----------------------------------------------------------------------

	remove_filter( 'wpss_pro_beta_rails', '__return_true', 99 );
	unset( $GLOBALS['wpss_fc_cart'] );

	wp_delete_post( $service_id, true );
	wp_delete_post( $product_id, true );
	wp_delete_post( $download_id, true );

	if ( $cron_orig !== wp_get_schedule( $cron_hook ) ) {
		wp_clear_scheduled_hook( $cron_hook );
		if ( false !== $cron_orig ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, $cron_orig, $cron_hook );
		}
		echo "  ....  restored the {$cron_hook} schedule to '" . ( $cron_orig ?: 'none' ) . "'\n";
	}

	$final_md5 = md5( maybe_serialize( get_option( $status_key ) ) );
	echo "\n  license option md5: snapshot {$status_md5} | now {$final_md5}\n";

	printf( "\n%d passed, %d failed\n\n", (int) $GLOBALS['wpss_pass'], (int) $GLOBALS['wpss_fail'] );

	if ( $GLOBALS['wpss_fail'] > 0 ) {
		exit( 1 );
	}
}
