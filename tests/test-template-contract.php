<?php
/**
 * Template contract: emails resolve through the one locator, every template declares an @version and
 * says it can be overridden, and an override older than the shipped file is reported
 * (Basecamp 10416397937).
 *
 * Run: wp eval-file tests/test-template-contract.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$root = WPSS_PLUGIN_DIR . 'templates';

// 1. Every shipped template declares a version and tells the developer it can be overridden.
$no_version = array();
$no_note    = array();
foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
	if ( 'php' !== $file->getExtension() ) {
		continue;
	}
	$rel  = substr( $file->getPathname(), strlen( $root ) + 1 );
	$head = (string) file_get_contents( $file->getPathname(), false, null, 0, 4096 );
	if ( '' === wpss_template_version( $file->getPathname() ) ) {
		$no_version[] = $rel;
	}
	if ( ! preg_match( '/overrid/i', $head ) ) {
		$no_note[] = $rel;
	}
}
$check( 'every template declares @version' . ( $no_version ? ' (' . implode( ', ', array_slice( $no_version, 0, 3 ) ) . ')' : '' ), ! $no_version );
$check( 'every template header says it can be overridden' . ( $no_note ? ' (' . implode( ', ', array_slice( $no_note, 0, 3 ) ) . ')' : '' ), ! $no_note );

// 2. Emails go through wpss_locate_template(), so the wpss_get_template filter reaches them.
$src = (string) file_get_contents( WPSS_PLUGIN_DIR . 'src/Services/EmailService.php' );
$check( 'EmailService never calls locate_template() itself', 1 !== preg_match( '/(?<![_>:\\\\])locate_template\s*\(/', $src ) );

$swap = sys_get_temp_dir() . '/wpss-email-swap-' . wp_generate_password( 6, false ) . '.php';
file_put_contents( $swap, "<?php echo 'SWAPPED-BY-FILTER';" );
$hook = static function ( $template, $name ) use ( $swap ) {
	return 'emails/new-order.php' === $name ? $swap : $template;
};
add_filter( 'wpss_get_template', $hook, 10, 2 );
$method = new ReflectionMethod( \WPSellServices\Services\EmailService::class, 'get_template_file' );
$method->setAccessible( true );
$service = ( new ReflectionClass( \WPSellServices\Services\EmailService::class ) )->newInstanceWithoutConstructor();
$found   = $method->invoke( $service, \WPSellServices\Services\EmailService::TYPE_NEW_ORDER );
remove_filter( 'wpss_get_template', $hook, 10 );
unlink( $swap );
$check( 'a plugin can swap an email template with wpss_get_template', $found === $swap );

// 3. Override scan: current, outdated and unversioned copies are told apart.
$dir = sys_get_temp_dir() . '/wpss-overrides-' . wp_generate_password( 6, false );
wp_mkdir_p( $dir . '/emails' );
$shipped = wpss_template_version( $root . '/emails/new-order.php' );
file_put_contents( $dir . '/emails/new-order.php', "<?php\n/**\n * @version 0.0.1\n */\n" );
file_put_contents( $dir . '/emails/delivery-ready.php', "<?php\n/**\n * @version {$shipped}\n */\n" );
file_put_contents( $dir . '/emails/order-completed.php', "<?php\n// copied before versions existed\n" );
file_put_contents( $dir . '/emails/not-ours.php', "<?php\n" );
$rows = array();
foreach ( wpss_get_template_overrides( array( $dir ) ) as $row ) {
	$rows[ $row['path'] ] = $row['status'];
}
$check( 'an older override is reported outdated', 'outdated' === ( $rows['emails/new-order.php'] ?? '' ) );
$check( 'an override at the shipped version is current', 'current' === ( $rows['emails/delivery-ready.php'] ?? '' ) );
$check( 'an override with no version is reported unversioned', 'unversioned' === ( $rows['emails/order-completed.php'] ?? '' ) );
$check( 'a file the plugin does not ship is not listed', ! isset( $rows['emails/not-ours.php'] ) );
array_map( 'unlink', glob( $dir . '/emails/*.php' ) );
rmdir( $dir . '/emails' );
rmdir( $dir );

exit( $fails ? 1 : 0 );
