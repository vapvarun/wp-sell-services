<?php
/**
 * A shopper's page view never waits on the exchange-rate API.
 *
 * Pro's display currency kept its rates in a 12-hour transient and fetched
 * inline when it expired, so the visitor who landed then waited on
 * openexchangerates.org (up to 8s), and every visitor arriving meanwhile
 * fetched too. Now a page view only reads the stored rates and queues one
 * background refresh; a failed refresh keeps the last good rates.
 *
 * Run: wp eval-file tests/test-exchange-rates-never-fetched-on-page-view.php
 *
 * The rates API is stubbed; the stored rates and settings are put back.
 *
 * @package WPSellServices
 */

use WPSellServices\Services\Scheduler;
use WPSellServicesPro\Currency\RateService;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

if ( ! class_exists( RateService::class ) ) {
	echo "SKIP  Pro is not active\n";
	return;
}

$hook = 'wpss_pro_refresh_rates';

// Feature on, with an app id.
add_filter(
	'option_' . RateService::OPTION,
	static fn( $v ) => array_merge( is_array( $v ) ? $v : array(), array( 'enabled' => 1, 'app_id' => 'qa-app-id' ) ),
	99
);

// The rates API: count calls, answer ok or down.
$calls  = 0;
$api_up = true;
add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) use ( &$calls, &$api_up ) {
		if ( false === strpos( $url, 'openexchangerates.org' ) ) {
			return $pre;
		}
		++$calls;
		return array(
			'headers'  => array(),
			'body'     => $api_up ? wp_json_encode( array( 'rates' => array( 'USD' => 1, 'EUR' => 0.9, 'INR' => 83.1 ) ) ) : '',
			'response' => array( 'code' => $api_up ? 200 : 503, 'message' => '' ),
			'cookies'  => array(),
			'filename' => null,
		);
	},
	10,
	3
);

$saved_option    = get_option( 'wpss_pro_oxr_rates', null );
$saved_transient = get_transient( 'wpss_pro_oxr_rates' );
delete_option( 'wpss_pro_oxr_rates' );
delete_transient( 'wpss_pro_oxr_rates' );
Scheduler::unschedule_all( $hook, Scheduler::GROUP_PRO );

// 1. A page view with nothing stored.
$map = ( new RateService() )->get_rate_map();
$check( 'a page view makes no call to the rates API', 0 === $calls, "{$calls} calls" );
$check( 'and shows no rates rather than waiting', array() === $map || ! isset( $map['EUR'] ) );
$check( 'it queues one background refresh', Scheduler::has_pending( $hook, array(), Scheduler::GROUP_PRO ) );
$check( 'the refresh job has a listener', has_action( $hook ) );

// 2. The background job runs.
do_action( $hook );
$check( 'the job fetches once', 1 === $calls, "{$calls} calls" );
$fresh = new RateService();
$check( 'the next page view shows the rates', 0.9 === ( $fresh->get_rate_map()['EUR'] ?? null ) );
$check( 'still without a call', 1 === $calls, "{$calls} calls" );

// 3. The API goes down at the next refresh: the last good rates stay.
$stored               = get_option( 'wpss_pro_oxr_rates' );
$stored['checked_at'] = time() - DAY_IN_SECONDS;
update_option( 'wpss_pro_oxr_rates', $stored, false );
$api_up = false;
( new RateService() )->refresh();
$check( 'a failed refresh keeps the last good rates', 0.9 === ( ( new RateService() )->get_rate_map()['EUR'] ?? null ) );
$check( 'and records the attempt so it is not retried every view', time() - (int) get_option( 'wpss_pro_oxr_rates' )['checked_at'] < 60 );

// 4. Rates a week stale are not shown.
$stored               = get_option( 'wpss_pro_oxr_rates' );
$stored['fetched_at'] = time() - 8 * DAY_IN_SECONDS;
update_option( 'wpss_pro_oxr_rates', $stored, false );
$check( 'rates over a week old are not shown', ! isset( ( new RateService() )->get_rate_map()['EUR'] ) );

// Clean up.
Scheduler::unschedule_all( $hook, Scheduler::GROUP_PRO );
delete_option( 'wpss_pro_oxr_rates' );
if ( null !== $saved_option ) {
	update_option( 'wpss_pro_oxr_rates', $saved_option, false );
}
if ( false !== $saved_transient ) {
	set_transient( 'wpss_pro_oxr_rates', $saved_transient, HOUR_IN_SECONDS );
}

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
