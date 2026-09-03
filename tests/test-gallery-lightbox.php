<?php
/**
 * Gallery lightbox contract (Basecamp 10244564339).
 *
 * The plugin ships ONE lightbox. single-service.js used to carry a second
 * implementation under the same `.wpss-lightbox` class name, and it never set
 * the `--visible` modifier frontend.css requires - so every click on a gallery
 * image appended a node with opacity 0 / visibility hidden and nothing showed
 * on screen. This asserts the duplicate stays deleted, the surviving opener is
 * reachable from both callers with the data it needs, the 16:9 stage actually
 * holds, and the overlay carries its accessibility attributes.
 *
 * Run: wp eval-file tests/test-gallery-lightbox.php
 *
 * No strict_types: wp eval-file eval()s the file, and a declare must be the
 * first statement in a script.
 *
 * @package WPSellServices
 */

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$read = static function ( string $rel ): string {
	return (string) file_get_contents( WPSS_PLUGIN_DIR . $rel );
};

$frontend_js  = $read( 'assets/js/frontend.js' );
$single_js    = $read( 'assets/js/single-service.js' );
$frontend_css = $read( 'assets/css/frontend.css' );
$single_css   = $read( 'assets/css/single-service.css' );

// --- one implementation, not two -------------------------------------------------
$check( 'the opener lives in frontend.js', str_contains( $frontend_js, 'WPSS.openLightbox = function(' ) );
$check( '  and single-service.js has no second opener', ! str_contains( $single_js, 'openLightbox:' ) );
$check( '  single-service.js calls the shared one', str_contains( $single_js, 'WPSS.openLightbox(' ) );
$check( '  the requirement thumbnail calls it too', str_contains( $frontend_js, ".wpss-requirement-view__thumbnail'" ) && 1 === substr_count( $frontend_js, 'WPSS.openLightbox = function(' ) );

/*
 * No .wpss-lightbox rule outside frontend.css. Two stylesheets declaring the
 * same root is exactly what hid the overlay, and single-service.css loads last
 * so it wins every property it re-declares.
 */
$css_dirty = array();
foreach ( glob( WPSS_PLUGIN_DIR . 'assets/css/*.css' ) as $css_file ) {
	$name = basename( $css_file );

	// frontend.css owns it; -rtl and .min are generated from their source.
	if ( str_starts_with( $name, 'frontend' ) || str_contains( $name, '.min.' ) ) {
		continue;
	}

	if ( preg_match( '/(^|[\s,{}])\.wpss-lightbox[\s,.:{[-]/', (string) file_get_contents( $css_file ) ) ) {
		$css_dirty[] = $name;
	}
}
$check( 'no .wpss-lightbox rule outside frontend.css', empty( $css_dirty ) );
foreach ( $css_dirty as $hit ) {
	echo "      {$hit}\n";
}
$check( '  the deleted block is gone from single-service.css', ! str_contains( $single_css, '.wpss-lightbox-close' ) );

// --- the markup carries what the opener needs ------------------------------------
$gallery = $read( 'templates/partials/service-gallery.php' );
$check( 'thumbs carry data-src for the opener', str_contains( $gallery, 'data-src="' ) );
$check( '  and an alt on the thumb image', str_contains( $gallery, "esc_attr( get_the_title() . ' - '" ) );
$check( '  the main image carries an alt', str_contains( $gallery, 'class="wpss-gallery-image"' ) && str_contains( $gallery, 'alt="<?php echo esc_attr( get_the_title() ); ?>"' ) );
$check( 'single-service.js reads the thumb data-src', str_contains( $single_js, ".wpss-gallery-thumb[data-src]'" ) );
$check( '  and falls back to the main image with no strip', str_contains( $single_js, "src: \$image.attr('src')" ) );

// --- the stage holds --------------------------------------------------------------
if ( preg_match( '/\.wpss-gallery-active\s*\{[^}]*\}/', $single_css, $stage ) ) {
	$check( 'the 16:9 stage clips overflow', str_contains( $stage[0], 'overflow: hidden' ) );
	$check( '  and keeps its aspect-ratio', str_contains( $stage[0], 'aspect-ratio: 16 / 9' ) );
} else {
	$check( 'the 16:9 stage clips overflow', false );
}

/*
 * frontend.css `.wpss-gallery-active img` is (0,1,1) and sets height: auto.
 * The image rule must outrank it, so it needs two classes.
 */
$check( 'the image rule outranks frontend.css height:auto', str_contains( $single_css, '.wpss-gallery-active .wpss-gallery-image {' ) );
if ( preg_match( '/\.wpss-gallery-active\s+\.wpss-gallery-image\s*\{[^}]*\}/', $single_css, $img_rule ) ) {
	$check( '  and letterboxes rather than crops', str_contains( $img_rule[0], 'object-fit: contain' ) );
	$check( '  filling the stage height', str_contains( $img_rule[0], 'height: 100%' ) );
}
$check( 'frontend.css still carries the height:auto rule this outranks', str_contains( $frontend_css, '.wpss-gallery-active img' ) );

// --- accessibility in the markup the opener builds --------------------------------
if ( preg_match( '/WPSS\.openLightbox = function\(.*?\n\t\};/s', $frontend_js, $opener ) ) {
	$body = $opener[0];
	$check( 'the overlay is a labelled modal dialog', str_contains( $body, 'role="dialog"' ) && str_contains( $body, 'aria-modal="true"' ) && str_contains( $body, 'aria-label="' ) );
	$check( '  close has an accessible name', str_contains( $body, 'wpss-lightbox__close" aria-label="' ) );
	$check( '  previous has an accessible name', str_contains( $body, 'wpss-lightbox__nav--prev" aria-label="' ) );
	$check( '  next has an accessible name', str_contains( $body, 'wpss-lightbox__nav--next" aria-label="' ) );
	$check( '  prev/next render only for more than one item', str_contains( $body, 'multiple = items.length > 1' ) && str_contains( $body, '(multiple' ) );
	$check( '  it adds the --visible modifier the CSS waits for', str_contains( $body, "addClass('wpss-lightbox--visible')" ) );
	$check( '  focus moves to close on open', str_contains( $body, ".wpss-lightbox__close').get(0).focus()" ) );
	$check( '  and is restored to the opener on close', str_contains( $body, 'opener = document.activeElement' ) && str_contains( $body, 'opener.focus()' ) );
	$check( '  Tab is trapped inside', str_contains( $body, "'Tab' === e.key" ) );
	$check( '  Escape closes', str_contains( $body, "'Escape' === e.key" ) );
	$check( '  arrows move the overlay', str_contains( $body, "'ArrowLeft' === e.key" ) && str_contains( $body, "'ArrowRight' === e.key" ) );
	$check( '  body scroll is locked while open', str_contains( $body, "addClass('wpss-modal-active')" ) && str_contains( $body, "removeClass('wpss-modal-active')" ) );
	$check( '  the source alt is carried through', str_contains( $body, "attr('alt', items[current].alt" ) );
	$check( '  click toggles zoom', str_contains( $body, "toggleClass('wpss-lightbox__content--zoomed')" ) );
} else {
	$check( 'the opener is readable', false );
}

$check( 'the page gallery yields the arrow keys while the overlay is open', str_contains( $single_js, 'WPSS.lightboxOpen' ) && str_contains( $frontend_js, 'WPSS.lightboxOpen = true' ) );

// --- close control is at least 40x40 ----------------------------------------------
if ( preg_match( '/\n\.wpss-lightbox__close\s*\{[^}]*\}/', $frontend_css, $close_rule ) ) {
	$check( 'close is at least 40x40', str_contains( $close_rule[0], 'width: 40px' ) && str_contains( $close_rule[0], 'height: 40px' ) );
} else {
	$check( 'close is at least 40x40', false );
}
$check( 'the zoomed state has a rule to render', str_contains( $frontend_css, '.wpss-lightbox__content--zoomed img' ) );
$check( 'nav controls are styled', str_contains( $frontend_css, '.wpss-lightbox__nav--prev' ) && str_contains( $frontend_css, '.wpss-lightbox__nav--next' ) );

// --- the accessible names are translatable ----------------------------------------
$frontend_php = $read( 'src/Frontend/Frontend.php' );
foreach ( array( 'imagePreview', 'close', 'previousImage', 'nextImage' ) as $key ) {
	$check( "i18n sends {$key}", str_contains( $frontend_php, "'{$key}'" ) );
}

// --- the built assets are not stale -----------------------------------------------
foreach ( array( 'assets/js/frontend.js', 'assets/js/single-service.js', 'assets/css/frontend.css', 'assets/css/single-service.css' ) as $src ) {
	$min = preg_replace( '/\.(js|css)$/', '.min.$1', $src );
	$check( "{$min} is newer than its source", filemtime( WPSS_PLUGIN_DIR . $min ) >= filemtime( WPSS_PLUGIN_DIR . $src ) );
}
$check( 'the built JS carries the shared opener', str_contains( $read( 'assets/js/frontend.min.js' ), 'openLightbox' ) );
$check( 'the built CSS carries the zoom rule', str_contains( $read( 'assets/css/frontend.min.css' ), 'wpss-lightbox__content--zoomed' ) );
$check( 'the built CSS dropped the duplicate lightbox', ! str_contains( $read( 'assets/css/single-service.min.css' ), 'wpss-lightbox-close' ) );

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
