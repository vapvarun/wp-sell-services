<?php
/**
 * Conversation panel state contract.
 *
 * On an unpaid order the panel invited the buyer to "Start the conversation"
 * directly above "Messaging is not available for this order status" - the
 * product arguing with itself on one screen (Basecamp 10240019323).
 *
 * The invite and the composer are both gated on $can_message, so there is one
 * authority. This asserts the two can never disagree again, by checking every
 * status rather than the one that was reported.
 *
 * Run: wp eval-file tests/test-conversation-states-contract.php
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

echo "\nConversation panel state contract\n\n";

$tpl = file_get_contents( dirname( __DIR__ ) . '/templates/order/conversation.php' );

// 1. One authority. The invite and the composer must both hang off the same
//    variable - the original defect was the invite carrying its own status list.
$invite_guard   = preg_match( '/if \( \$can_message \) \{\s*\$wpss_empty_text = __\( \'Start the conversation/', $tpl );
$composer_guard = false !== strpos( $tpl, '<?php if ( $can_message ) : ?>' );
wpss_t( 1 === $invite_guard, 'the invite is gated on $can_message' );
wpss_t( $composer_guard, 'the composer is gated on $can_message' );

// 2. The empty paragraph is not emitted when there is nothing to say - it used
//    to render a bare <p> under the heading on every blocked non-terminal
//    status, which reads as a half-loaded panel.
wpss_t(
	false !== strpos( $tpl, "if ( '' !== \$wpss_empty_text ) :" ),
	'no empty paragraph is rendered when there is no message for the status'
);

// 3. Every status is either messageable or has a reason. A status that is
//    blocked with no explanation is the defect in a new costume.
preg_match( '/\$active_message_statuses = array\((.*?)\);/s', $tpl, $m );
$active = array();
if ( ! empty( $m[1] ) ) {
	preg_match_all( "/'([a-z_]+)'/", $m[1], $sm );
	$active = $sm[1];
}
wpss_t( count( $active ) >= 10, 'the messageable status list is populated (' . count( $active ) . ')' );

$explained = array( 'completed', 'cancelled', 'refunded', 'partially_refunded', 'rejected', 'pending_payment' );
foreach ( $explained as $status ) {
	wpss_t( ! in_array( $status, $active, true ), sprintf( '%s is blocked, as intended', $status ) );
}

// The pre-payment case must name the step that unlocks it rather than only
// saying it is shut.
wpss_t(
	false !== strpos( $tpl, 'You can message this seller once the order is paid.' ),
	'the pre-payment reason says WHEN messaging opens, not just that it is closed'
);

// 4. Every status the model knows must be covered by one branch or the other,
//    so a status added later cannot silently fall into a bare panel.
if ( class_exists( '\WPSellServices\Models\ServiceOrder' ) ) {
	$ref      = new ReflectionClass( '\WPSellServices\Models\ServiceOrder' );
	$statuses = array();
	foreach ( $ref->getConstants() as $name => $value ) {
		if ( 0 === strpos( $name, 'STATUS_' ) && is_string( $value ) ) {
			$statuses[] = $value;
		}
	}
	$uncovered = array();
	foreach ( $statuses as $s ) {
		if ( ! in_array( $s, $active, true ) && false === strpos( $tpl, "'" . $s . "'" ) ) {
			$uncovered[] = $s;
		}
	}
	wpss_t(
		empty( $uncovered ),
		'every order status is either messageable or named in the template (' . ( $uncovered ? implode( ', ', $uncovered ) : 'all covered' ) . ')'
	);
}

echo "\n{$GLOBALS['wpss_pass']} passed, {$GLOBALS['wpss_fail']} failed\n";
