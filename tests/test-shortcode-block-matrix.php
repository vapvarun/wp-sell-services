<?php
/**
 * Every shortcode and block renders for every kind of viewer without an error.
 *
 * Run: wp eval-file tests/test-shortcode-block-matrix.php
 *
 * Twelve of the twenty shortcodes and four of the six blocks were rendered by
 * no check. Each is rendered with default attributes, logged out and as the
 * busiest buyer and vendor on the site (so lists render with data where there
 * is any). A thrown error, or any PHP warning, notice or deprecation raised
 * in this plugin's code while rendering, fails.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	if ( ! $ok ) {
		echo 'FAIL  ' . $label . "\n";
		++$fails;
	}
};

global $wpdb, $shortcode_tags;
$plugin = dirname( __DIR__ ) . '/';
$orders = $wpdb->prefix . 'wpss_orders';
// phpcs:disable WordPress.DB.DirectDatabaseQuery
$viewers = array(
	'logged out' => 0,
	'buyer'      => (int) $wpdb->get_var( "SELECT customer_id FROM {$orders} WHERE customer_id > 1 GROUP BY customer_id ORDER BY COUNT(*) DESC LIMIT 1" ),
	'vendor'     => (int) $wpdb->get_var( "SELECT vendor_id FROM {$orders} WHERE vendor_id > 1 GROUP BY vendor_id ORDER BY COUNT(*) DESC LIMIT 1" ),
);
// phpcs:enable

$owned = static function ( $cb ) use ( $plugin ): bool {
	try {
		$file = is_array( $cb ) ? ( new ReflectionMethod( $cb[0], $cb[1] ) )->getFileName() : ( new ReflectionFunction( $cb ) )->getFileName();
	} catch ( Throwable $e ) {
		return false;
	}
	return str_starts_with( (string) $file, $plugin );
};

$items = array();
foreach ( $shortcode_tags as $tag => $cb ) {
	if ( $owned( $cb ) ) {
		$items[ "[{$tag}]" ] = static fn() => do_shortcode( "[{$tag}]" );
	}
}
foreach ( WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $type ) {
	if ( str_starts_with( $name, 'wpss/' ) ) {
		$items[ $name ] = static fn() => render_block( array( 'blockName' => $name, 'attrs' => array(), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ) );
	}
}

$rendered = 0;
foreach ( $viewers as $who => $uid ) {
	if ( 'logged out' !== $who && ! $uid ) {
		continue;
	}
	wp_set_current_user( $uid );

	foreach ( $items as $label => $render ) {
		$errors = array();
		set_error_handler(
			static function ( $no, $msg, $file, $line ) use ( &$errors, $plugin ) {
				if ( str_starts_with( (string) $file, $plugin ) ) {
					$errors[] = "{$msg} at " . substr( $file, strlen( $plugin ) ) . ":{$line}";
				}
				return false;
			}
		);
		ob_start();
		try {
			$render();
			$threw = '';
		} catch ( Throwable $e ) {
			$threw = get_class( $e ) . ': ' . $e->getMessage();
		}
		ob_end_clean();
		restore_error_handler();
		++$rendered;

		$check( "{$label} ({$who}) threw {$threw}", '' === $threw );
		$check( "{$label} ({$who}) raised " . implode( '; ', $errors ), ! $errors );
	}
}
wp_set_current_user( 0 );

echo count( $items ) . " shortcodes and blocks, {$rendered} renders\n";
echo $fails ? "{$fails} FAILED\n" : "ALL PASS\n";
