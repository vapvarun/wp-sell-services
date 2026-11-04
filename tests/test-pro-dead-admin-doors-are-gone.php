<?php
/**
 * Pro admin code that nothing could reach stays deleted.
 *
 * Two admin AJAX actions (wpss_pro_test_gateway, wpss_pro_test_email) had
 * handlers and JS bound to .wpss-test-gateway / .wpss-test-email, but no
 * screen rendered either button, so they could never fire. FluentCart's
 * render_service_fields() and its private wp_dropdown_posts() helper (an
 * unbounded get_posts) had no caller either.
 *
 * Run: wp eval-file tests/test-pro-dead-admin-doors-are-gone.php
 *
 * Read-only.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

if ( ! class_exists( \WPSellServicesPro\Core\Pro::class ) ) {
	echo "SKIP  Pro is not active\n";
	return;
}

$pro = dirname( __DIR__, 2 ) . '/wp-sell-services-pro/';
$js  = (string) file_get_contents( $pro . 'assets/js/admin.js' );

foreach ( array( 'wpss_pro_test_gateway', 'wpss_pro_test_email' ) as $action ) {
	$check( "no {$action} AJAX door", false === has_action( 'wp_ajax_' . $action ) );
	$check( "admin.js does not post {$action}", false === strpos( $js, $action ) );
}
$check( 'Pro has no test-gateway or test-email handler', ! method_exists( \WPSellServicesPro\Core\Pro::class, 'ajax_test_gateway' ) && ! method_exists( \WPSellServicesPro\Core\Pro::class, 'ajax_test_email' ) );
$check( 'FluentCart has no unhooked service-fields form', ! method_exists( \WPSellServicesPro\Integrations\FluentCart\FluentCartProductProvider::class, 'render_service_fields' ) );
$check( 'nor its unbounded posts dropdown', ! function_exists( 'WPSellServicesPro\Integrations\FluentCart\wp_dropdown_posts' ) && false === strpos( (string) file_get_contents( $pro . 'src/Integrations/FluentCart/FluentCartProductProvider.php' ), 'wp_dropdown_posts' ) );

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
