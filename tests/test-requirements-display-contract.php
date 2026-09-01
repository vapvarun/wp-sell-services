<?php
/**
 * Submitted requirements display contract.
 *
 * The read-only view walks the SERVICE's configured questions and looks each
 * answer up by question text. A service with no configured questions rendered
 * nothing at all, so a vendor opened the order and could not read the brief the
 * buyer had written and paid for (Basecamp 10254444197).
 *
 * Keying answers by question text has a second failure of the same shape: edit
 * or delete a question after submission and its answer disappears too. Both are
 * covered here, because fixing only the reported one leaves the other live.
 *
 * Run: wp eval-file tests/test-requirements-display-contract.php
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

echo "\nSubmitted requirements display contract\n\n";

/**
 * Work out what the template would render, using its own logic.
 *
 * @param array $configured Service questions.
 * @param array $submitted  Submitted field_data.
 * @return array<string,string> Label => answer.
 */
function wpss_rendered_answers( array $configured, array $submitted ): array {
	$out  = array();
	$keys = array();

	foreach ( $configured as $req ) {
		$q          = (string) ( $req['question'] ?? '' );
		$keys[]     = $q;
		$out[ $q ]  = (string) ( $submitted[ $q ] ?? '' );
	}

	foreach ( $submitted as $key => $value ) {
		if ( in_array( (string) $key, $keys, true ) ) {
			continue;
		}
		if ( '' === trim( (string) ( is_scalar( $value ) ? $value : wp_json_encode( $value ) ) ) ) {
			continue;
		}
		$label         = 'description' === $key ? 'What the buyer asked for' : ucfirst( str_replace( array( '_', '-' ), ' ', (string) $key ) );
		$out[ $label ] = (string) $value;
	}

	return $out;
}

// 1. The reported case: no questions configured, a brief submitted.
$rendered = wpss_rendered_answers( array(), array( 'description' => 'Need a custom WP plugin.' ) );
wpss_t( ! empty( $rendered ), 'a brief is shown when the service has no configured questions' );
wpss_t( in_array( 'Need a custom WP plugin.', $rendered, true ), 'the brief text itself reaches the vendor' );
wpss_t( isset( $rendered['What the buyer asked for'] ), 'the freeform brief gets a readable label, not the raw key' );

// 2. The normal case still renders once, not twice.
$configured = array( array( 'question' => 'Brand colour?', 'type' => 'text' ) );
$submitted  = array( 'Brand colour?' => 'Deep blue', 'description' => 'Match our style guide.' );
$rendered   = wpss_rendered_answers( $configured, $submitted );

wpss_t( 2 === count( $rendered ), 'a configured answer and a brief render as two blocks, not three' );
wpss_t( 'Deep blue' === ( $rendered['Brand colour?'] ?? '' ), 'the configured question keeps its own answer' );
wpss_t( 1 === count( array_keys( $rendered, 'Deep blue', true ) ), 'the configured answer is not also repeated as an orphan' );

// 3. The second failure of the same shape: a question removed after submission.
$rendered = wpss_rendered_answers( array(), array( 'Brand colour?' => 'Deep blue' ) );
wpss_t( in_array( 'Deep blue', $rendered, true ), 'an answer survives its question being deleted' );

// 4. Empty answers are not rendered as blank blocks.
$rendered = wpss_rendered_answers( array(), array( 'description' => '   ', 'notes' => '' ) );
wpss_t( empty( $rendered ), 'blank submitted values do not render empty blocks' );

// 5. The template actually contains the fallback, not just this test.
$tpl = file_get_contents( dirname( __DIR__ ) . '/templates/order/order-view.php' );
wpss_t( false !== strpos( $tpl, '$orphan_answers' ), 'the template renders answers no question claims' );
wpss_t( false !== strpos( $tpl, '$orphan_attachments' ), 'the template renders attachments no question claims' );
// The label moved into wpss_requirement_field_label() so the admin screen
// could share it, so assert the behaviour rather than the old inline literal.
wpss_t(
	false !== strpos( $tpl, 'wpss_requirement_field_label(' )
		&& 'What the buyer asked for' === wpss_requirement_field_label( 'description' ),
	'the freeform description is labelled rather than special-cased away'
);

// All three surfaces, not just the one the card named.
//
// The brief reached the buyer view and the seller view but the ADMIN order
// screen printed the raw storage key, so the site owner read "description"
// where the other two read "What the buyer asked for". Same flow, two labels.
wpss_t(
	'What the buyer asked for' === wpss_requirement_field_label( 'description' ),
	'the freeform brief has a human label'
);
wpss_t(
	'Brand colour' === wpss_requirement_field_label( 'brand_colour' ),
	'a configured question is titled from its own key, not renamed'
);

$admin = (string) file_get_contents( WPSS_PLUGIN_DIR . 'src/Admin/Admin.php' );
wpss_t(
	false !== strpos( $admin, 'wpss_requirement_field_label( (string) $wpss_req_key )' ),
	'the admin order screen uses the shared label helper'
);
wpss_t(
	false === strpos( $admin, "esc_html( (string) \$wpss_req_key )" ),
	'the admin order screen no longer prints the raw storage key'
);

$view = (string) file_get_contents( WPSS_PLUGIN_DIR . 'templates/order/order-view.php' );
wpss_t(
	false !== strpos( $view, 'wpss_requirement_field_label(' ),
	'the order view uses the same helper, so the label cannot drift between surfaces'
);

echo "\n{$GLOBALS['wpss_pass']} passed, {$GLOBALS['wpss_fail']} failed\n";
