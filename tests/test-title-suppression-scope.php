<?php
/**
 * Title-suppression scope contract.
 *
 * ShellHeader::maybe_suppress_theme_title() blanks `the_title` for the queried
 * object on every plugin-shell surface, so the active theme stops printing a
 * duplicate H1 above the plugin's own heading. The filter cannot tell who is
 * asking, so it blanks the title for the PLUGIN's reads too.
 *
 * That shipped seven silent defects on a single service page: the Service and
 * Product schema had `"name":""`, the breadcrumb crumb in the same JSON-LD was
 * empty, the on-screen breadcrumb was empty, og:title and twitter:title were
 * dropped from the head entirely so every social share was untitled, the
 * gallery's main image carried alt="" and its thumbnails alt=" - 1", and the
 * services archive fell back to "All Services" instead of the owner's own page
 * name.
 *
 * The rule this locks in: plugin code reads the RAW post_title. Anything that
 * needs the real title of the post being viewed uses get_post_field() or the
 * model, never get_the_title().
 *
 * Run: wp eval-file tests/test-title-suppression-scope.php
 *
 * No strict_types: wp eval-file eval()s the file, and a declare must be the
 * first statement in a script.
 *
 * @package WPSellServices
 */

$fails  = 0;
$passes = 0;
$check  = static function ( string $label, bool $ok ) use ( &$fails, &$passes ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$ok ? $passes++ : $fails++;
};

// --- find a published service that renders a gallery ------------------------------
$service_id = 0;

/*
 * Ask only for services that actually carry gallery meta. Scanning the newest N
 * services and reading meta per row is an N+1 that also finds nothing on a site
 * whose newest services are gallery-less - which is exactly how this fixture
 * first came back empty on a 389-service sandbox.
 */
$ids = get_posts(
	array(
		'post_type'      => 'wpss_service',
		'post_status'    => 'publish',
		'posts_per_page' => 25,
		'fields'         => 'ids',
		'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- one-off contract script, not a page render.
			array(
				'key'     => '_wpss_gallery',
				'compare' => 'EXISTS',
			),
		),
	)
);
foreach ( $ids as $id ) {
	$gallery = wpss_get_gallery_ids( get_post_meta( $id, '_wpss_gallery', true ) );
	if ( has_post_thumbnail( $id ) ) {
		$gallery[] = get_post_thumbnail_id( $id );
	}
	if ( count( array_unique( array_filter( $gallery ) ) ) > 1 ) {
		$service_id = (int) $id;
		break;
	}
}

if ( ! $service_id ) {
	echo "SKIP  no published service with a multi-image gallery on this site\n";
	return;
}

$title = (string) get_post_field( 'post_title', $service_id );
$check( 'fixture: a multi-image service with a real title (#' . $service_id . ')', '' !== $title );

/*
 * Put the request on the single-service surface so the suppression is ACTIVE.
 * Without this the test passes for the wrong reason: off a shell surface the
 * filter returns the title untouched and every assertion below is vacuous.
 */
global $wp_query, $wp_the_query, $post;

/*
 * $wp_the_query matters as much as $wp_query. is_main_query() compares against
 * $wp_the_query, and the suppression returns early when it is false - so
 * setting only $wp_query leaves the filter dormant and every assertion below
 * passes for the wrong reason. That is how the first run of this contract went
 * green against code that was still broken.
 */
$wp_the_query = new WP_Query(
	array(
		'p'         => $service_id,
		'post_type' => 'wpss_service',
	)
);
$wp_query = $wp_the_query;
$wp_query->the_post();
$post = get_post( $service_id );

$check( 'the suppression is actually active for this request', '' === get_the_title( $service_id ) );

// --- structured data ---------------------------------------------------------------
$schema         = new \WPSellServices\SEO\SchemaMarkup();
$service_schema = $schema->get_service_schema( $service_id );
$crumb_schema   = $schema->get_breadcrumb_schema();

$check( 'the Service/Product schema carries the service name', $title === (string) ( $service_schema['name'] ?? '' ) );

$crumb_names = array();
foreach ( (array) ( $crumb_schema['itemListElement'] ?? array() ) as $crumb ) {
	$crumb_names[] = (string) ( $crumb['name'] ?? '' );
}
$check( 'the breadcrumb schema names the current page', in_array( $title, $crumb_names, true ) );
$check( '  and no crumb has an empty name', ! in_array( '', $crumb_names, true ) );

// --- Open Graph / Twitter -----------------------------------------------------------
$seo = new \WPSellServices\SEO\SEO();
$og  = $seo->get_open_graph_data( $service_id );

$check( 'og:title is present and is the service title', isset( $og['og:title'] ) && $title === (string) $og['og:title'] );
$check( 'twitter:title is present and is the service title', isset( $og['twitter:title'] ) && $title === (string) $og['twitter:title'] );

// --- gallery alt text ----------------------------------------------------------------
ob_start();
wpss_get_template_part( 'partials/service-gallery', '', array( 'service_id' => $service_id ) );
$gallery_html = (string) ob_get_clean();

$check( 'the gallery rendered', str_contains( $gallery_html, 'wpss-gallery-image' ) );
$check( '  the main image alt is the service title', str_contains( $gallery_html, 'alt="' . esc_attr( $title ) . '"' ) );
$check( '  no image in the gallery has an empty alt', ! preg_match( '/<img[^>]*\balt=""/', $gallery_html ) );
$check( '  no thumbnail alt is a bare " - N" with no title', ! preg_match( '/alt="\s+-\s+\d+"/', $gallery_html ) );

/*
 * Thumbnail numbering runs 1..N with no gaps. array_unique() and array_filter()
 * preserve keys, so a vendor whose featured image also sits in the gallery used
 * to get "Title - 1", "Title - 3", "Title - 4" for a three-image strip.
 */
preg_match_all( '/alt="[^"]*? - (\d+)"/', $gallery_html, $m );
$numbers  = array_map( 'intval', $m[1] );
$expected = range( 1, count( $numbers ) );
$check( '  thumbnail numbering is sequential from 1', $numbers === $expected || array() === $numbers );

wp_reset_postdata();

// --- source rule: no plugin surface reads the queried title through the filter --------
$sources = array(
	'src/SEO/SchemaMarkup.php',
	'src/SEO/SEO.php',
	'src/Frontend/SingleServiceView.php',
	'src/Frontend/ServiceArchiveView.php',
	'templates/partials/service-gallery.php',
);
foreach ( $sources as $rel ) {
	$code = (string) file_get_contents( WPSS_PLUGIN_DIR . $rel );
	// Strip comments so the explanatory notes naming get_the_title() do not trip this.
	$stripped = '';
	foreach ( token_get_all( '<?php ' . $code ) as $token ) {
		if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
			continue;
		}
		$stripped .= is_array( $token ) ? $token[1] : $token;
	}
	$check( $rel . ' does not read the title through get_the_title()', ! str_contains( $stripped, 'get_the_title(' ) );
}

echo "\n" . $passes . ' passed, ' . $fails . " failed\n";
