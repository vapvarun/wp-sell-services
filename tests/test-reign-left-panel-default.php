<?php
/**
 * Reign's Left Panel is hidden by default on plugin pages; an explicit choice wins.
 *
 * On a Reign site with the Left Panel on and a menu assigned, every buyer and
 * vendor saw Reign's rail beside the dashboard's own navigation (Basecamp
 * 10469984984). TemplateLoader already defaults Reign's per-page layout on
 * plugin pages; it now defaults "Hide Left Panel Menu" too. Reign's values:
 * 'yes' hides, 'no' forces the panel on, '0' (or empty) is Default.
 *
 * Run (Reign active): wp eval-file tests/test-reign-left-panel-default.php
 *
 * Writes the dashboard page's reign_wbcom_metabox_data and restores it.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

if ( 'reign-theme' !== get_template() ) {
	echo "SKIP  Reign is not the active theme\n";
	return;
}

$page_id = (int) wpss_get_page_id( 'dashboard' );
$other   = (int) get_option( 'page_on_front' );
if ( ! $page_id ) {
	echo "SKIP  no dashboard page mapped\n";
	return;
}

// What Reign's reign-panel.php reads.
$panel = static function ( int $id ): string {
	$data = get_post_meta( $id, 'reign_wbcom_metabox_data', true );
	return (string) ( $data['layout']['display_left_panel_menu'] ?? '' );
};

global $wpdb;
$key      = 'reign_wbcom_metabox_data';
$original = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1", $page_id, $key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$store    = static function ( $value ) use ( $page_id, $key ) {
	delete_post_meta( $page_id, $key );
	if ( null !== $value ) {
		add_post_meta( $page_id, $key, $value );
	}
	wp_cache_delete( $page_id, 'post_meta' );
};

try {
	$store( null );
	$check( 'a plugin page left at Default hides the panel', 'yes' === $panel( $page_id ), $panel( $page_id ) );

	$store( array( 'layout' => array( 'display_left_panel_menu' => '0' ) ) );
	$check( "Reign's stored Default ('0') hides the panel", 'yes' === $panel( $page_id ), $panel( $page_id ) );

	$store( array( 'layout' => array( 'display_left_panel_menu' => 'no' ) ) );
	$check( 'a page set to show the panel still shows it', 'no' === $panel( $page_id ), $panel( $page_id ) );

	$store( array( 'layout' => array( 'site_layout' => 'right_sidebar' ) ) );
	$check( "an owner's own site layout is kept", 'right_sidebar' === ( get_post_meta( $page_id, $key, true )['layout']['site_layout'] ?? '' ) );

	$store( null );
	add_filter( 'wpss_reign_hide_left_panel', '__return_false' );
	$check( 'the opt-out filter leaves the panel at Default', '' === $panel( $page_id ), $panel( $page_id ) );
	remove_filter( 'wpss_reign_hide_left_panel', '__return_false' );

	if ( $other && $other !== $page_id && ! in_array( $other, array_map( 'intval', (array) get_option( 'wpss_pages', array() ) ), true ) ) {
		$check( 'a page that is not a plugin page is untouched', 'yes' !== $panel( $other ) );
	}
} finally {
	$store( null === $original ? null : maybe_unserialize( $original ) );
}

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
