<?php
/**
 * Every shown member name comes from wpss_get_member_display_name().
 *
 * Run: wp eval-file tests/test-member-display-name-one-helper.php
 *
 * 61 sites wrote `$user ? $user->display_name : __( 'Unknown' )` themselves, so
 * a site filtering wpss_member_display_name still saw full names on those
 * screens and in those emails. Each site keeps its own fallback word.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

// No private copy of the rule is left in shipped code.
$hits = array();
$it   = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( WPSS_PLUGIN_DIR, FilesystemIterator::SKIP_DOTS ) );
foreach ( $it as $file ) {
	$rel = str_replace( WPSS_PLUGIN_DIR, '', (string) $file );
	if ( '.php' !== substr( $rel, -4 ) || ! preg_match( '#^(src|templates)/#', $rel ) ) {
		continue;
	}
	foreach ( file( (string) $file ) as $n => $line ) {
		if ( preg_match( '/\$(\w+)\s*\?\s*\$\1->display_name\s*:\s*(__|esc_html__|sprintf)\(/', $line ) ) {
			$hits[] = $rel . ':' . ( $n + 1 );
		}
	}
}
$check( 'no shown name falls back by hand', array() === $hits, implode( ', ', $hits ) );

// Behaviour: the filter reaches a model that used its own copy.
$user_id = (int) get_current_user_id() ?: 1;
$row     = (object) array(
	'id'              => 0,
	'conversation_id' => 0,
	'sender_id'       => $user_id,
	'content'         => '',
	'attachments'     => '',
	'metadata'        => '',
	'read_by'         => '',
	'is_edited'       => 0,
	'created_at'      => '',
	'updated_at'      => '',
);
$rename = static fn( $name, $id, $exists ) => $exists ? 'Member #' . $id : $name;
add_filter( 'wpss_member_display_name', $rename, 10, 3 );
$check( 'a message sender name follows wpss_member_display_name', 'Member #' . $user_id === \WPSellServices\Models\Message::from_db( $row )->get_sender_name() );
remove_filter( 'wpss_member_display_name', $rename, 10 );

$row->sender_id = PHP_INT_MAX >> 33; // No such user.
$check( 'a deleted sender keeps the contextual fallback word', __( 'Unknown', 'wp-sell-services' ) === \WPSellServices\Models\Message::from_db( $row )->get_sender_name() );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
