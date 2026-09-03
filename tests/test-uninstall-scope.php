<?php
/**
 * Uninstall scope contract (Basecamp 10268057514).
 *
 * The delete-data setting listed everything it removes and said nothing about
 * pages, so an owner reading that inventory assumed the mapped marketplace
 * pages went with it. They do not, and they must not: create_pages() adopts
 * any pre-existing page carrying the shortcode, so wpss_pages is a mix of
 * pages the plugin published and pages the owner wrote. This contract pins
 * the honest copy, and pins the marker that tells the two apart so a later
 * release can offer to remove only the plugin's own.
 *
 * Run (the flags load THIS tree, not the installed copy):
 *   wp eval-file tests/test-uninstall-scope.php \
 *     --skip-plugins=wp-sell-services,wp-sell-services-pro
 *
 * Inspects the uninstall routine's targets the way test-setup-health.php
 * inspects notices - it never runs uninstall against the database.
 *
 * @package WPSellServices
 */

$GLOBALS['wpss_pass'] = 0;
$GLOBALS['wpss_fail'] = 0;

/**
 * Assert one condition.
 *
 * @param bool   $cond Condition.
 * @param string $msg  Message.
 * @return void
 */
function wpss_t( $cond, $msg ) {
	if ( $cond ) {
		++$GLOBALS['wpss_pass'];
		echo "  PASS  {$msg}\n";
	} else {
		++$GLOBALS['wpss_fail'];
		echo "  FAIL  {$msg}\n";
	}
}

echo "\nUninstall scope contract\n\n";

$root = dirname( __DIR__ );

if ( function_exists( 'wpss_get_page_definitions' ) ) {
	$loaded_from = ( new ReflectionFunction( 'wpss_get_page_definitions' ) )->getFileName();

	if ( 0 !== strpos( $loaded_from, $root ) ) {
		echo "  ABORT the installed plugin is loaded from {$loaded_from}\n";
		echo "        re-run with --skip-plugins=wp-sell-services,wp-sell-services-pro\n\n";
		return;
	}
}

require_once $root . '/vendor/autoload.php';
require_once $root . '/wp-sell-services.php';
require_once $root . '/src/functions.php';
require_once $root . '/src/Core/Activator.php';

// ------------------------------------------------------------------ the copy

$settings = new \WPSellServices\Admin\Settings();
$settings->register_settings();

global $wp_settings_fields;

$description = (string) ( $wp_settings_fields['wpss_advanced']['wpss_advanced_section']['delete_data_on_uninstall']['args']['description'] ?? '' );

wpss_t( '' !== $description, 'the delete-data setting has a description' );
wpss_t( false !== stripos( $description, 'Settings > Pages' ), 'it names the pages mapped under Settings > Pages' );
wpss_t( false !== stripos( $description, 'left in place' ), 'it says those pages are left in place' );
wpss_t( false !== stripos( $description, 'Delete them by hand' ), 'it tells the owner how to remove them' );

// --------------------------------------------------------------- the marker

$created_key   = 'wpss_contract_created';
$adopted_key   = 'wpss_contract_adopted';
$created_code  = '[wpss_contract_created_probe]';
$adopted_code  = '[wpss_contract_adopted_probe]';
$saved_pages   = get_option( 'wpss_pages', array() );
$saved_terms   = get_option( 'wpss_terms_page' );
$throwaway_ids = array();

$probe_definitions = function ( $definitions ) use ( $created_key, $adopted_key, $created_code, $adopted_code ) {
		$definitions[ $created_key ] = array(
			'title'     => 'WPSS Contract Created',
			'shortcode' => $created_code,
			'slug'      => 'wpss-contract-created',
			'required'  => false,
		);
		$definitions[ $adopted_key ] = array(
			'title'     => 'WPSS Contract Adopted',
			'shortcode' => $adopted_code,
			'slug'      => 'wpss-contract-adopted',
			'required'  => false,
		);

	return $definitions;
};

add_filter( 'wpss_page_definitions', $probe_definitions );

// A page the OWNER wrote, carrying the shortcode - what create_pages() adopts.
$owner_page_id = wp_insert_post(
	array(
		'post_title'   => 'WPSS Contract Adopted',
		'post_content' => $adopted_code,
		'post_status'  => 'publish',
		'post_type'    => 'page',
	)
);

$throwaway_ids[] = $owner_page_id;

\WPSellServices\Core\Activator::create_pages();

$mapped     = get_option( 'wpss_pages', array() );
$created_id = (int) ( $mapped[ $created_key ] ?? 0 );
$adopted_id = (int) ( $mapped[ $adopted_key ] ?? 0 );

if ( $created_id && $created_id !== $owner_page_id ) {
	$throwaway_ids[] = $created_id;
}

wpss_t( $created_id > 0, 'create_pages() published the page it had to create' );
wpss_t( $created_key === get_post_meta( $created_id, '_wpss_created_page', true ), 'the page it created carries _wpss_created_page with its registry key' );
wpss_t( (int) $owner_page_id === $adopted_id, 'the owner-written page was adopted, not duplicated' );
wpss_t( '' === get_post_meta( $adopted_id, '_wpss_created_page', true ), 'the adopted page carries no marker, so a later release cannot delete it' );

// Restore: the throwaway pages and the two options this probe moved. The probe
// keys have to leave the registry AND the sanitize callback first - that
// callback deliberately merges into the stored value, so a plain update_option
// can add a key but never drop one.
foreach ( array_filter( $throwaway_ids ) as $throwaway_id ) {
	wp_delete_post( $throwaway_id, true );
}

remove_filter( 'wpss_page_definitions', $probe_definitions );
remove_all_filters( 'sanitize_option_wpss_pages' );
update_option( 'wpss_pages', $saved_pages );

if ( false === $saved_terms ) {
	delete_option( 'wpss_terms_page' );
}

wpss_t( $saved_pages === get_option( 'wpss_pages', array() ), 'restored: the page mapping is back to what it was' );
wpss_t( ! get_post( $created_id ) && ! get_post( $owner_page_id ), 'restored: both throwaway pages are gone' );

// ------------------------------------------------------- what uninstall targets

$uninstall = (string) file_get_contents( $root . '/uninstall.php' );

preg_match( '/\$post_types\s*=\s*array\(([^)]*)\)/', $uninstall, $match );

$targets = array_filter( array_map( 'trim', explode( ',', $match[1] ?? '' ) ) );

wpss_t( array( "'wpss_service'", "'wpss_request'" ) === array_values( $targets ), 'uninstall deletes only the two plugin post types' );
wpss_t( 1 === substr_count( $uninstall, 'wp_delete_post' ), 'it deletes posts in that one loop and nowhere else' );
wpss_t( false === strpos( $uninstall, "'page'" ), 'it never targets the page post type' );
wpss_t( false === strpos( $uninstall, '_wpss_created_page' ), 'it does not act on the marker in this release' );

echo "\n  {$GLOBALS['wpss_pass']} passed, {$GLOBALS['wpss_fail']} failed\n\n";
