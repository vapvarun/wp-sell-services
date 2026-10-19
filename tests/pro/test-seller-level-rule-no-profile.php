<?php
/**
 * A 'new' seller-level commission rule matches only users that have a vendor profile; a buyer or
 * unknown user has no level (Basecamp 10407787720).
 *
 * Run: wp eval-file tests/pro/test-seller-level-rule-no-profile.php
 *
 * @package WPSellServices
 */

use WPSellServicesPro\TieredCommission\Rules\SellerLevelCommissionRule;

if ( ! defined( 'WPSS_PRO_VERSION' ) ) {
	echo "SKIP  Pro is not active\n";
	return;
}

require_once dirname( __DIR__ ) . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$vendor_id = (int) $wpdb->get_var( "SELECT user_id FROM {$wpdb->prefix}wpss_vendor_profiles WHERE verification_tier = 'new' LIMIT 1" );
$buyer_id  = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->users} WHERE ID NOT IN (SELECT user_id FROM {$wpdb->prefix}wpss_vendor_profiles) LIMIT 1" );

$rule = new SellerLevelCommissionRule( (object) array( 'id' => 1, 'name' => 'New sellers', 'rate' => 20, 'rate_type' => 'percentage', 'priority' => 1, 'conditions' => wp_json_encode( array( 'level' => 'new' ) ) ) );
$check( 'fixture: a new-tier vendor and a non-vendor exist', $vendor_id > 0 && $buyer_id > 0 );
$check( 'new-tier vendor matches a new rule', $rule->matches( 0, $vendor_id, array() ) );
$check( 'user with no vendor profile does not match', ! $rule->matches( 0, $buyer_id, array() ) );
$check( 'unknown user id does not match', ! $rule->matches( 0, 99999999, array() ) );

exit( $fails ? 1 : 0 );
