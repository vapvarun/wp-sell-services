<?php
/**
 * Button copy is sentence case (2.0.0 copy sweep, C5).
 *
 * "View Request" sat beside "View order" on one card. Every member-facing button and link styled
 * .wpss-btn now reads in sentence case; this fails a label inside one that capitalises a word other
 * than the first of a sentence, a product or proper name, or a package tier.
 *
 * Run: wp eval-file tests/test-button-copy-sentence-case.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$keep = array( 'WordPress', 'WooCommerce', 'Stripe', 'PayPal', 'Pro', 'EDD', 'FluentCart', 'Razorpay', 'TeraWallet', 'MyCred', 'YouTube', 'Vimeo', 'Google', 'Connect', 'Basic', 'Standard', 'Premium', 'Express', 'I' );

/**
 * The words of a label that break sentence case.
 *
 * @param string   $label Label text.
 * @param string[] $keep  Words that keep their capital.
 * @return string[]
 */
$offenders = static function ( string $label, array $keep ): array {
	$label = (string) preg_replace( '/%(\d\$)?[sd]|&[a-z]+;|<[^>]+>/', ' ', $label );
	$bad   = array();
	foreach ( preg_split( '/(?<=[.!?:])\s+/', $label ) as $sentence ) {
		$words = preg_split( '/[\s\/&(),+-]+/', trim( $sentence ), -1, PREG_SPLIT_NO_EMPTY );
		foreach ( array_slice( $words, 1 ) as $word ) {
			$word = trim( $word, "\"'“”…." );
			if ( '' !== $word && ctype_upper( $word[0] ) && ! ctype_upper( $word ) && ! in_array( $word, $keep, true ) && ! preg_match( '/[a-z][A-Z]/', $word ) ) {
				$bad[] = $word;
			}
		}
	}
	return $bad;
};

$roots = array( WP_PLUGIN_DIR . '/wp-sell-services' => array( 'templates', 'src/Frontend', 'src/Blocks' ) );
if ( is_dir( WP_PLUGIN_DIR . '/wp-sell-services-pro' ) ) {
	$roots[ WP_PLUGIN_DIR . '/wp-sell-services-pro' ] = array( 'templates', 'src/Frontend' );
}

$fails   = array();
$buttons = 0;
foreach ( $roots as $root => $dirs ) {
	foreach ( $dirs as $dir ) {
		if ( ! is_dir( "{$root}/{$dir}" ) ) {
			continue;
		}
		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( "{$root}/{$dir}", FilesystemIterator::SKIP_DOTS ) ) as $file ) {
			if ( 'php' !== $file->getExtension() ) {
				continue;
			}
			$src = (string) file_get_contents( $file->getPathname() );
			// An attribute may hold a PHP block, whose closing bracket must not end the tag.
			preg_match_all( '#<(button|a)\b((?:<\?(?:php|=).*?\?>|[^>])*)>(.*?)</\1>#s', $src, $els, PREG_SET_ORDER );
			foreach ( $els as $el ) {
				if ( ! preg_match( '/class="[^"]*\bwpss-btn\b/', $el[2] ) ) {
					continue;
				}
				$el[2] = $el[3];
				preg_match_all( '/(?:__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e|_x|esc_html_x)\(\s*\'((?:\\\\.|[^\'])*)\'/', $el[2], $labels );
				foreach ( $labels[1] as $label ) {
					++$buttons;
					$bad = $offenders( stripslashes( $label ), $keep );
					if ( $bad ) {
						$fails[] = str_replace( WP_PLUGIN_DIR . '/', '', $file->getPathname() ) . ": '{$label}' (" . implode( ', ', $bad ) . ')';
					}
				}
			}
		}
	}
}

echo ( $buttons > 100 ? 'PASS' : 'FAIL' ) . "  found the button labels ({$buttons})\n";
echo ( $fails ? 'FAIL' : 'PASS' ) . '  every .wpss-btn label is sentence case' . ( $fails ? ":\n  " . implode( "\n  ", array_slice( $fails, 0, 20 ) ) : '' ) . "\n";

// The checker itself: it must catch Title Case and pass sentence case, tiers and product names.
$self = array(
	'View Request'                    => true,
	'View request'                    => false,
	'Add Premium tier'                => false,
	'Continue Stripe setup'           => false,
	'Network error. Please try again.' => false,
	'Accept &amp; Pay %s'             => true,
);
$self_ok = true;
foreach ( $self as $label => $should_fail ) {
	$self_ok = $self_ok && ( (bool) $offenders( $label, $keep ) === $should_fail );
}
echo ( $self_ok ? 'PASS' : 'FAIL' ) . "  the checker tells Title Case from sentence case\n";

$n = (int) ( $buttons <= 100 ) + (int) (bool) $fails + (int) ! $self_ok;
echo $n ? "{$n} FAILED\n" : "all passed\n";
