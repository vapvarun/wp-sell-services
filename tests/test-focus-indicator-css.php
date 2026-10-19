<?php
/**
 * Every source CSS rule that removes the outline must replace it with a ring (UX rule 14).
 *
 * Run: wp eval-file tests/test-focus-indicator-css.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$weak = array();
foreach ( glob( dirname( __DIR__ ) . '/assets/css/*.css' ) as $file ) {
	$name = basename( $file );
	if ( str_contains( $name, '.min.' ) || str_contains( $name, '-rtl' ) ) {
		continue;
	}
	preg_match_all( '/([^{}]+)\{([^{}]*outline\s*:\s*(?:none|0)\b[^{}]*)\}/', (string) file_get_contents( $file ), $m, PREG_SET_ORDER );
	foreach ( $m as $rule ) {
		// A rule shared with :hover has its :focus-visible ring in a separate rule.
		if ( ! str_contains( $rule[2], 'box-shadow' ) && ! str_contains( $rule[1], ':hover' ) ) {
			$weak[] = $name . ' :: ' . trim( preg_replace( '/\s+/', ' ', $rule[1] ) );
		}
	}
}

echo empty( $weak ) ? "PASS no outline removed without a ring\n" : "FAIL outline removed without a ring:\n  " . implode( "\n  ", $weak ) . "\n";
