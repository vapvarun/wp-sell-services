<?php
/**
 * An app token used as the login password never mints a new token.
 *
 * Run: wp eval-file tests/test-token-cannot-mint.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$id   = wp_insert_user( array( 'user_login' => 'qa_rft_tok' . wp_rand( 1000, 9999 ), 'user_pass' => wp_generate_password(), 'user_email' => 'qa-rft-tok' . wp_rand( 1000, 9999 ) . '@example.test', 'role' => 'subscriber' ) );
$user = get_userdata( $id );
list( $token ) = WP_Application_Passwords::create_new_application_password( $id, array( 'name' => 'QA-RFT' ) );

wp_set_current_user( 0 );
$req = new WP_REST_Request( 'POST', '/wpss/v1/auth/login' );
$req->set_body_params( array( 'username' => $user->user_login, 'password' => $token ) );
$res = rest_do_request( $req );

$check( 'the token is refused with a 401', $res->is_error() && 401 === $res->get_status() );
$check( '  and no token is in the response', ! $res->is_error() ? false : empty( $res->get_data()['token'] ) );
$check( '  and the code is one of the two documented ones', in_array( $res->is_error() ? $res->as_error()->get_error_code() : '', array( 'invalid_credentials', 'wpss_token_cannot_mint' ), true ) );
$check( 'the member still holds exactly the one token they started with', 1 === count( WP_Application_Passwords::get_user_application_passwords( $id ) ) );

require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $id );
