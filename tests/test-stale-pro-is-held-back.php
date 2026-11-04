<?php
/**
 * A Pro older than WPSS_MIN_PRO_VERSION never boots against this Free: its
 * `wpss_loaded` callback is removed before the action fires (Free updated
 * first, Pro 1.8.x then fatals on contracts that gained abstract methods).
 *
 * Run: wp eval-file tests/test-stale-pro-is-held-back.php
 *
 * Adds throwaway callbacks to `wpss_loaded` and `admin_notices` of this
 * process only; nothing persists.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

defined( 'ABSPATH' ) || exit;

$check = static function ( string $label, bool $ok, string $detail = '' ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
};

$check( 'a minimum Pro version is declared and is not newer than Free', defined( 'WPSS_MIN_PRO_VERSION' ) && version_compare( WPSS_MIN_PRO_VERSION, WPSS_VERSION, '<=' ) );

if ( defined( 'WPSS_PRO_VERSION' ) ) {
	$check( 'the installed Pro meets the minimum', version_compare( WPSS_PRO_VERSION, WPSS_MIN_PRO_VERSION, '>=' ), WPSS_PRO_VERSION );
}

// A stand-in for Pro's startup callback: same namespace, same hook.
eval( 'namespace WPSellServicesPro; function wpss_stale_probe( $plugin ) {}' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
$pro_cb   = 'WPSellServicesPro\\wpss_stale_probe';
$other_cb = static function () {};
add_action( 'wpss_loaded', $pro_cb );
add_action( 'wpss_loaded', $other_cb );

$hold = new ReflectionMethod( \WPSellServices\Core\Plugin::class, 'hold_back_stale_pro' );
$self = \WPSellServices\Core\Plugin::get_instance();

// Negative case: a Pro at the minimum is left alone, no notice.
$notices = has_action( 'admin_notices' ) ? count( $GLOBALS['wp_filter']['admin_notices']->callbacks, COUNT_RECURSIVE ) : 0;
$hold->invoke( $self, WPSS_MIN_PRO_VERSION );
$check( 'a Pro at the minimum keeps its startup callback', false !== has_action( 'wpss_loaded', $pro_cb ) );
$after = has_action( 'admin_notices' ) ? count( $GLOBALS['wp_filter']['admin_notices']->callbacks, COUNT_RECURSIVE ) : 0;
$check( 'a Pro at the minimum adds no notice', $after === $notices );

// Stale Pro: callback gone, other plugins' callbacks untouched, notice added.
$hold->invoke( $self, '1.8.0' );
$check( 'Pro 1.8.0 loses its startup callback', false === has_action( 'wpss_loaded', $pro_cb ) );
$check( 'a callback from another plugin is untouched', false !== has_action( 'wpss_loaded', $other_cb ) );
$now = has_action( 'admin_notices' ) ? count( $GLOBALS['wp_filter']['admin_notices']->callbacks, COUNT_RECURSIVE ) : 0;
$check( 'an admin notice is added', $now > $notices );
