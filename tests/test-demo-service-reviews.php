<?php
/**
 * A demo service imported by the setup wizard gets several sample reviews, each on an order of its
 * own: every review at order 0 collided on uniq_order_review and only one ever landed
 * (Basecamp 10380420186).
 *
 * Run: wp eval-file tests/test-demo-service-reviews.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

if ( ! class_exists( 'WP_CLI_Command' ) ) {
	require_once WPSS_PLUGIN_DIR . 'src/CLI/wp-cli-stubs.php';
}
require_once WPSS_PLUGIN_DIR . 'src/CLI/ServiceCommands.php';

global $wpdb;
wp_set_current_user( 1 );
$commands  = new \WPSellServices\CLI\ServiceCommands();
$class     = new ReflectionClass( $commands );
$templates = $class->getProperty( 'service_templates' )->getValue( $commands );
$template  = null;
foreach ( $templates as $candidate ) {
	if ( ! empty( $candidate['stats']['reviews'] ) ) {
		$template = $candidate;
		break;
	}
}
$template['title'] .= ' QA reviews probe';

$id = $class->getMethod( 'create_service' )->invoke( $commands, $template );
$check( 'demo service created', is_int( $id ) && $id > 0 );

$reviews = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wpss_reviews WHERE service_id = %d", $id ) );
$orders  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT order_id) FROM {$wpdb->prefix}wpss_reviews WHERE service_id = %d AND order_id > 0", $id ) );
$check( 'several sample reviews landed', $reviews >= 3 );
$check( 'each review sits on its own order', $reviews === $orders );
$check( 'the service shows a rating', (float) get_post_meta( $id, '_wpss_rating_average', true ) > 0 );
$marked = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wpss_orders WHERE service_id = %d AND meta LIKE %s", $id, '%Seeded demo order.%' ) );
$check( 'the orders carry the importer marker, so demo delete tells them from real sales', $marked === $orders );

wp_delete_post( $id, true );
$left = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wpss_orders WHERE service_id = %d", $id ) )
	+ (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wpss_reviews WHERE service_id = %d", $id ) );
$check( 'deleting the service removes its orders and reviews', 0 === $left );
if ( $left ) {
	$wpdb->delete( $wpdb->prefix . 'wpss_reviews', array( 'service_id' => $id ) );
	$wpdb->delete( $wpdb->prefix . 'wpss_orders', array( 'service_id' => $id ) );
}

exit( $fails ? 1 : 0 );
