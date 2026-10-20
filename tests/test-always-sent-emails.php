<?php
/**
 * Payment-bearing emails ignore the recipient's email preferences.
 *
 * Run: wp eval-file tests/test-always-sent-emails.php
 *
 * Owner decision 2026-10-20: proposal, milestone and extension emails always
 * send, and the preferences screen says so. A switch that cannot mute anything
 * must not exist.
 *
 * @package WPSellServices
 */

use WPSellServices\Services\EmailService;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$map = new ReflectionMethod( EmailService::class, 'get_user_pref_category' );
$svc = new EmailService();

foreach ( array( 'PROPOSAL_SUBMITTED', 'PROPOSAL_ACCEPTED', 'MILESTONE_PROPOSED', 'MILESTONE_PAID', 'MILESTONE_SUBMITTED', 'MILESTONE_APPROVED', 'EXTENSION_PROPOSED', 'EXTENSION_APPROVED', 'EXTENSION_DECLINED' ) as $name ) {
	$check( "{$name} is not user-mutable", null === $map->invoke( $svc, constant( EmailService::class . '::TYPE_' . $name ) ) );
}

$vendor = get_users( array( 'role' => 'wpss_vendor', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 0;
$check( 'vendors are not offered a proposals switch', $vendor && ! array_key_exists( 'proposals', wpss_get_email_preference_categories( (int) $vendor ) ) );

exit( $fails ? 1 : 0 );
