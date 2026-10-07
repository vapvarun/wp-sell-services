<?php
/**
 * Login page contract.
 *
 * [wpss_login] rendered core's bare wp_login_form() beside the styled
 * [wpss_register] card, and every Sign in link went to wp-login.php even on a
 * site with its own login page (Basecamp 10352980066). The page is now an
 * optional entry in the page registry and core's login_url follows it, the
 * same way register_url follows the registration page.
 *
 * Run: wp eval-file tests/test-login-page-contract.php
 *
 * Creates one page and restores wpss_pages at the end.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$defs = wpss_get_page_definitions();
$check( 'login is an optional page using [wpss_login]', isset( $defs['login'] ) && '[wpss_login]' === $defs['login']['shortcode'] && empty( $defs['login']['required'] ) );

$saved   = get_option( 'wpss_pages', array() );
$target  = home_url( '/some-service/' );
$page_id = 0;
$made    = 0;

try {
	// Unmapped: core's URL, untouched.
	$pages = $saved;
	unset( $pages['login'] );
	update_option( 'wpss_pages', $pages );
	$check( 'with no login page, Sign in stays on wp-login.php', false !== strpos( wp_login_url( $target ), 'wp-login.php' ) );

	// Mapped.
	$page_id = (int) wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Login contract',
			'post_content' => '[wpss_login]',
		)
	);
	$pages['login'] = $page_id;
	update_option( 'wpss_pages', $pages );

	$url = wp_login_url( $target );
	$check( 'with a login page, Sign in goes to it', 0 === strpos( $url, (string) get_permalink( $page_id ) ) );
	$check( '  carrying where the visitor came from', $target === wp_parse_args( (string) wp_parse_url( $url, PHP_URL_QUERY ) )['redirect_to'] ?? '' );
	$check( 'wp-admin re-authentication stays on wp-login.php', false !== strpos( wp_login_url( admin_url(), true ), 'wp-login.php' ) );

	// The form.
	$_GET['redirect_to'] = $target;
	$html                = do_shortcode( '[wpss_login]' );
	unset( $_GET['redirect_to'] );
	$check( '[wpss_login] renders the sign-up card, not core\'s bare form', false !== strpos( $html, 'wpss-vr__card' ) && false === strpos( $html, 'id="loginform"' ) );
	$check( '  posts to wp-login.php', false !== strpos( $html, 'wp-login.php' ) && false !== strpos( $html, 'name="log"' ) && false !== strpos( $html, 'name="pwd"' ) );
	$check( '  and returns the visitor where they came from', false !== strpos( $html, 'name="redirect_to" value="' . esc_url( $target ) . '"' ) );

	$_GET['redirect_to'] = 'https://evil.example/';
	$html                = do_shortcode( '[wpss_login]' );
	unset( $_GET['redirect_to'] );
	$check( '  but never to another site', false === strpos( $html, 'evil.example' ) );

	// The installer. Mapping this page changes sign-in for the whole site, so
	// an update never does it, and a fresh install only where login is still
	// WordPress's own (owner decision, Basecamp 10352980066).
	$mapped = static fn(): int => (int) ( get_option( 'wpss_pages', array() )['login'] ?? 0 );
	$unmap  = static function () use ( $saved ): void {
		$pages = $saved;
		unset( $pages['login'] );
		update_option( 'wpss_pages', $pages );
	};

	$unmap();
	\WPSellServices\Core\Activator::create_pages( false );
	$check( 'an update does not create or map a Log In page', 0 === $mapped() );

	$theirs = static fn() => home_url( '/members/sign-in/' );
	add_filter( 'login_url', $theirs, 5 );
	$check( 'a site whose login link was moved is not on core login', false === \WPSellServices\Core\Activator::site_uses_core_login() );
	\WPSellServices\Core\Activator::create_pages( true );
	$check( 'a fresh install on a site with its own login creates none', 0 === $mapped() );
	remove_filter( 'login_url', $theirs, 5 );

	// A hide-login plugin renames wp-login.php through site_url instead
	// (Basecamp 10379596006).
	$renamed = static fn( $url ) => str_replace( 'wp-login.php', 'secret-door', (string) $url );
	add_filter( 'site_url', $renamed, 5 );
	$check( 'nor is a site whose wp-login.php was renamed', false === \WPSellServices\Core\Activator::site_uses_core_login() );
	remove_filter( 'site_url', $renamed, 5 );

	$check( 'with our own redirect set aside, this site is on core login', true === \WPSellServices\Core\Activator::site_uses_core_login() );
	\WPSellServices\Core\Activator::create_pages( true );
	$made = $mapped();
	$check( 'a fresh install on core login maps a Log In page', $made > 0 );

	$pages          = get_option( 'wpss_pages', array() );
	$pages['login'] = $page_id;
	update_option( 'wpss_pages', $pages );
	\WPSellServices\Core\Activator::create_pages( false );
	$check( 'an update leaves the page the owner mapped', $page_id === $mapped() );
} finally {
	update_option( 'wpss_pages', $saved );
	// A page the installer published for this test, never one it adopted.
	if ( ! empty( $made ) && $made !== $page_id && $made !== (int) ( $saved['login'] ?? 0 ) && 'login' === get_post_meta( $made, '_wpss_created_page', true ) ) {
		wp_delete_post( $made, true );
	}
	if ( $page_id ) {
		wp_delete_post( $page_id, true );
	}
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
