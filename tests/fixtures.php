<?php
/**
 * Throwaway fixtures for the tests/test-*.php scripts, so a guard describes the plugin and never this site's
 * leftover data (QA gate rule: no "busiest user", "latest order" or named personas).
 *
 * Every row made here is removed at shutdown, including when a test dies half way.
 *
 * Usage: require_once __DIR__ . '/fixtures.php'; $f = wpss_test_fixtures();
 *        $f->buyer, $f->vendor (active profile), $f->service(), $f->order( array $overrides )
 *
 * @package WPSellServices
 */

if ( ! function_exists( 'wpss_test_fixtures' ) ) {

	/**
	 * Fixture factory.
	 *
	 * @return object
	 */
	function wpss_test_fixtures(): object {
		static $f = null;
		if ( $f ) {
			return $f;
		}

		global $wpdb;

		$f           = new stdClass();
		$f->users    = array();
		$f->posts    = array();
		$f->orders   = array();
		$f->tag      = 'qa' . wp_generate_password( 6, false );
		$f->orders_t = $wpdb->prefix . 'wpss_orders';

		// Clean up however the test ends. A shutdown function alone is not enough: after an
		// uncaught Error, WordPress's fatal-error handler (an earlier shutdown function) exits
		// first, so the fixtures leaked. The exception handler runs before shutdown.
		$cleanup = static function () use ( $f, $wpdb ): void {
			static $done = false;
			if ( $done ) {
				return;
			}
			$done = true;
			// Ledger rows first: deleting a user zeroes its rows' user_id, and a test's own orders are
			// not in $f->orders, so rows are matched by fixture user as well as by fixture order.
			$ledger = $wpdb->prefix . 'wpss_wallet_transactions';
			foreach ( $f->users as $id ) {
				$wpdb->delete( $ledger, array( 'user_id' => $id ) );
			}
			foreach ( $f->orders as $id ) {
				$wpdb->delete( $ledger, array( 'reference_type' => 'order', 'reference_id' => $id ) );
			}
			foreach ( $f->orders as $id ) {
				$wpdb->delete( $f->orders_t, array( 'id' => $id ) );
			}
			foreach ( $f->posts as $id ) {
				wp_delete_post( $id, true );
			}
			require_once ABSPATH . 'wp-admin/includes/user.php';
			foreach ( $f->users as $id ) {
				$wpdb->delete( $wpdb->prefix . 'wpss_vendor_profiles', array( 'user_id' => $id ) );
				$wpdb->delete( $wpdb->prefix . 'wpss_notifications', array( 'user_id' => $id ) );
				wp_delete_user( $id );
			}
		};
		register_shutdown_function( $cleanup );
		set_exception_handler(
			static function ( Throwable $e ) use ( $cleanup ): void {
				$cleanup();
				// Rethrowing from here recurses, so report it the way PHP would and fail the run.
				echo 'FAIL  uncaught ' . get_class( $e ) . ': ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine() . "\n";
				exit( 255 );
			}
		);

		$make_user = static function ( string $role, string $suffix ) use ( $f ): int {
			$id = wp_insert_user(
				array(
					'user_login' => "{$f->tag}_{$suffix}",
					'user_pass'  => wp_generate_password(),
					'user_email' => "{$f->tag}_{$suffix}@example.test",
					'role'       => $role,
				)
			);
			$f->users[] = (int) $id;
			return (int) $id;
		};

		$f->buyer  = $make_user( 'subscriber', 'buyer' );
		$f->admin  = $make_user( 'administrator', 'admin' );
		$f->vendor = $make_user( 'wpss_vendor', 'vendor' );
		( new \WPSellServices\Database\Repositories\VendorProfileRepository() )->upsert(
			$f->vendor,
			array(
				'display_name'      => 'QA Vendor',
				'status'            => 'active',
				'verification_tier' => \WPSellServices\Models\VendorProfile::TIER_NEW,
			)
		);

		$f->service = static function ( string $status = 'draft' ) use ( $f ): int {
			$id = wp_insert_post(
				array(
					'post_type'    => 'wpss_service',
					'post_status'  => $status,
					'post_title'   => 'QA fixture service for the standing and publish guards',
					'post_content' => str_repeat( 'Fixture service description. ', 12 ),
					'post_author'  => $f->vendor,
				)
			);
			$f->posts[] = (int) $id;
			return (int) $id;
		};

		$f->order = static function ( array $overrides = array() ) use ( $f, $wpdb ): int {
			$wpdb->insert(
				$f->orders_t,
				array_merge(
					array(
						'order_number'   => strtoupper( $f->tag ) . '-' . wp_generate_password( 5, false ),
						'customer_id'    => $f->buyer,
						'vendor_id'      => $f->vendor,
						'service_id'     => 0,
						'platform'       => 'standalone',
						'status'         => 'in_progress',
						'payment_status' => 'paid',
						'subtotal'       => 40,
						'total'          => 40,
						'currency'       => wpss_get_currency(),
						'paid_at'        => current_time( 'mysql' ),
						'created_at'     => current_time( 'mysql' ),
					),
					$overrides
				)
			);
			$f->orders[] = (int) $wpdb->insert_id;
			return (int) $wpdb->insert_id;
		};

		return $f;
	}
}
