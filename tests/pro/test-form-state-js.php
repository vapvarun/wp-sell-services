<?php
/**
 * Pro admin form state: an inactive commission rule lists as Inactive and Edit restores the
 * Active checkbox from the rule, and the Push settings save has an ajaxUrl in scope
 * (Basecamp 10407616413, 10408271874).
 *
 * Run: wp eval-file tests/pro/test-form-state-js.php
 *
 * @package WPSellServices
 */

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

use WPSellServicesPro\TieredCommission\CommissionRuleRepository;
use WPSellServicesPro\TieredCommission\CommissionSettingsRenderer;

$repo = new CommissionRuleRepository();
$id   = $repo->create( array( 'rule_type' => 'volume', 'name' => 'QA inactive rule', 'rate' => 7, 'rate_type' => 'percentage', 'conditions' => array( 'min_earnings' => 100 ), 'is_active' => 0 ) );

ob_start();
( new CommissionSettingsRenderer( $repo ) )->render();
$html = (string) ob_get_clean();
$repo->delete( $id );

preg_match( '/<tr id="wpss-rule-row-' . $id . '">(.*?)<\/tr>/s', $html, $row );
$check( 'inactive rule lists as Inactive', ! empty( $row[1] ) && false !== strpos( $row[1], 'Inactive' ) && false === strpos( $row[1], '>Active<' ) );
$check( 'Edit button carries data-is-active="0"', ! empty( $row[1] ) && false !== strpos( $row[1], 'data-is-active="0"' ) );

$js = (string) file_get_contents( WPSS_PRO_PLUGIN_DIR . 'assets/js/admin.js' );
$check( 'edit handler sets the Active checkbox from data-is-active', (bool) preg_match( "/#wpss-rule-is-active'\)\.prop\('checked', String\(\\\$btn\.attr\('data-is-active'\)\)/", $js ) );
preg_match( '/initPushSettings = function\(\) \{(.*?)\n\t\};/s', $js, $push );
$check( 'Push save declares ajaxUrl before using it', ! empty( $push[1] ) && (bool) preg_match( '/var ajaxUrl = wpssProAdmin\.ajaxUrl;.*\$\.post\(ajaxUrl/s', $push[1] ) );
$min = (string) file_get_contents( WPSS_PRO_PLUGIN_DIR . 'assets/js/admin.min.js' );
$check( 'admin.min.js is rebuilt with the Push fix', false !== strpos( $min, 'data-is-active' ) );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
