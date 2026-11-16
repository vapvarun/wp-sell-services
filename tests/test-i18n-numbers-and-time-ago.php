<?php
/**
 * Shown numbers follow the site locale and "time ago" follows its word order.
 *
 * Run: wp eval-file tests/test-i18n-numbers-and-time-ago.php
 *
 * Ratings were printed with number_format() (4.5 on a German site whose screen
 * reader text said 4,5), an admin proposal price had no currency, and "ago" was
 * glued after the number in English order ("3 horas hace"); the messages list
 * dropped "ago" altogether.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$scan = static function ( string $pattern ): array {
	$hits = array();
	$it   = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( WPSS_PLUGIN_DIR, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $file ) {
		$rel = str_replace( WPSS_PLUGIN_DIR, '', (string) $file );
		if ( '.php' !== substr( $rel, -4 ) || ! preg_match( '#^(src|templates)/#', $rel ) ) {
			continue;
		}
		foreach ( file( (string) $file ) as $n => $line ) {
			if ( preg_match( $pattern, $line ) ) {
				$hits[] = $rel . ':' . ( $n + 1 );
			}
		}
	}
	return $hits;
};

// Numbers.
$hits = $scan( '/esc_html\(\s*number_format\(/' );
$check( 'no shown number uses the unlocalised number_format()', array() === $hits, implode( ', ', $hits ) );

$metabox = (string) file_get_contents( WPSS_PLUGIN_DIR . 'src/Admin/Metaboxes/BuyerRequestMetabox.php' );
$check( 'the admin proposal price carries the currency', false !== strpos( $metabox, 'wpss_format_price( $proposed_price )' ) );

// Time ago, in the translation's word order.
$spanish = static fn( $translation, $text, $domain ) => ( 'wp-sell-services' === $domain && '%s ago' === $text ) ? 'hace %s' : $translation;
add_filter( 'gettext', $spanish, 10, 3 );
$two_hours = gmdate( 'Y-m-d H:i:s', time() - 2 * HOUR_IN_SECONDS );
$ago       = wpss_time_ago( $two_hours );
$check( 'wpss_time_ago() puts the time where the translation does', 0 === strpos( $ago, 'hace ' ), $ago );

$note             = new \WPSellServices\Models\Notification();
$note->created_at = $two_hours;
$check( 'a notification time_ago is the same helper', $ago === $note->get_time_ago(), $note->get_time_ago() );
remove_filter( 'gettext', $spanish, 10 );

$hits = $scan( "/\.\s*' '\s*\.\s*__\(\s*'ago'/" );
$check( 'no "ago" is glued on in English word order', array() === $hits, implode( ', ', $hits ) );

$messages = (string) file_get_contents( WPSS_PLUGIN_DIR . 'templates/dashboard/sections/messages.php' );
$check( 'the messages list says "ago" through the helper', false !== strpos( $messages, 'wpss_time_ago( (string) $conversation->last_message_at )' ) );

$list = (string) file_get_contents( WPSS_PLUGIN_DIR . 'templates/partials/notifications-list.php' );
$check( 'the notifications list reads its UTC time through the helper', false !== strpos( $list, 'wpss_time_ago(' ) && false === strpos( $list, 'human_time_diff(' ) );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
