<?php
/**
 * Tax, tip commission and refund amounts have one owner each; payment gateways only move the amount
 * already decided. A gateway that computes any of them forks the rule per processor and drifts.
 *
 * Owners: tax = wpss_calculate_tax() (money.php); tip commission = TippingService via CommissionService;
 * refund totals and splits = OrderWorkflowManager. Gateways read the persisted result.
 *
 * Run: wp eval-file tests/test-money-rules-gateway-independent.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

// Rule tokens a gateway must never use. Comments are stripped first, so explaining the rule is fine.
$forbidden = array(
	'tax'    => '/wpss_calculate_tax\s*\(|[\'"]wpss_tax[\'"]/',
	'tip'    => '/tip_commission_rate|wpss_tip_commission_rate/',
	'fee'    => '/CommissionService|compute_breakdown\s*\(|wpss_order_commission_base\s*\(/',
	'refund' => '/wpss_get_order_refunded_amount\s*\(|OrderWorkflowManager/',
);

$strip = static function ( string $code ): string {
	$out = '';
	foreach ( token_get_all( $code ) as $t ) {
		if ( is_array( $t ) && in_array( $t[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
			continue;
		}
		$out .= is_array( $t ) ? $t[1] : $t;
	}
	return $out;
};

$scan = static function ( array $files, array $rules ) use ( $strip ): array {
	$hits = array();
	foreach ( $files as $file ) {
		$code = $strip( (string) file_get_contents( $file ) );
		foreach ( $rules as $name => $re ) {
			if ( preg_match( $re, $code ) ) {
				$hits[] = basename( $file ) . ' [' . $name . ']';
			}
		}
	}
	return $hits;
};

$gateway_files = array();
$dirs          = array( WPSS_PLUGIN_DIR . 'src/Integrations/Stripe', WPSS_PLUGIN_DIR . 'src/Integrations/PayPal', WPSS_PLUGIN_DIR . 'src/Integrations/Gateways' );
if ( defined( 'WPSS_PRO_PLUGIN_DIR' ) ) {
	$dirs[] = WPSS_PRO_PLUGIN_DIR . 'src/Integrations/Razorpay';
}
foreach ( $dirs as $dir ) {
	if ( is_dir( $dir ) ) {
		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) ) as $f ) {
			if ( 'php' === $f->getExtension() ) {
				$gateway_files[] = $f->getPathname();
			}
		}
	}
}

// Gateways: no tax, tip, fee or refund-split rule. They get the amount and send it.
$gateway_rules = $forbidden;
unset( $gateway_rules['refund'] ); // Gateways may read the refund amount they are told to send; the split is the owner's.
$hits = $scan( $gateway_files, $gateway_rules );
$check( count( $gateway_files ) . ' gateway files hold no tax, tip or commission rule' . ( $hits ? ' (' . implode( ', ', $hits ) . ')' : '' ), ! $hits );

$hits = $scan( $gateway_files, array( 'refund' => '/wpss_get_order_refunded_amount\s*\(|new\s+OrderWorkflowManager/' ) );
$check( 'gateways do not total or split refunds themselves' . ( $hits ? ' (' . implode( ', ', $hits ) . ')' : '' ), ! $hits );

// The option and the rule each have one reader.
$readers = array();
foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( WPSS_PLUGIN_DIR . 'src', FilesystemIterator::SKIP_DOTS ) ) as $f ) {
	if ( 'php' !== $f->getExtension() ) {
		continue;
	}
	$code = $strip( (string) file_get_contents( $f->getPathname() ) );
	if ( preg_match( '/get_option\(\s*[\'"]wpss_tax[\'"]/', $code ) ) {
		$readers['tax'][] = basename( $f->getPathname() );
	}
	if ( preg_match( '/[\'"]tip_commission_rate[\'"]/', $code ) ) {
		$readers['tip'][] = basename( $f->getPathname() );
	}
}
$tax = $readers['tax'] ?? array();
sort( $tax );
$check( 'wpss_tax is read only by money.php and the schema migration (' . implode( ', ', $tax ) . ')', array( 'SchemaManager.php', 'money.php' ) === $tax );
$check( 'tip_commission_rate is read only by the Settings field and TippingService (' . implode( ', ', $readers['tip'] ?? array() ) . ')', array( 'Settings.php', 'TippingService.php' ) === ( $readers['tip'] ?? array() ) );

exit( $fails ? 1 : 0 );
