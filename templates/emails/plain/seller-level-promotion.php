<?php
/**
 * Seller Level Promotion Email (Plain Text)
 *
 * Sent to vendor when they are promoted to a higher level.
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.0.0
 *
 * @var WP_User $recipient        Recipient user object.
 * @var string  $email_heading    Email heading.
 * @var string  $new_level        New level code.
 * @var string  $new_level_label  New level display name.
 * @var string  $old_level        Previous level code.
 * @var string  $old_level_label  Previous level display name.
 */

defined( 'ABSPATH' ) || exit;

echo "= " . esc_html( $email_heading ) . " =\n\n";

printf(
	/* translators: %s: recipient name */
	esc_html__( 'Hi %s,', 'wp-sell-services' ),
	esc_html( $recipient->display_name )
);
echo "\n\n";

echo esc_html__( 'Great news! Your hard work and dedication have paid off.', 'wp-sell-services' );
echo "\n\n";

echo "****************************************\n";
echo esc_html__( 'You\'ve been promoted to:', 'wp-sell-services' ) . "\n";
echo strtoupper( esc_html( $new_level_label ) ) . "\n";
printf(
	/* translators: %s: previous level */
	esc_html__( '(Previously: %s)', 'wp-sell-services' ),
	esc_html( $old_level_label )
);
echo "\n****************************************\n\n";

echo esc_html__( 'This promotion recognizes your excellent performance on our platform. Keep up the great work!', 'wp-sell-services' );
echo "\n\n";

echo esc_html__( 'What This Means For You:', 'wp-sell-services' ) . "\n";
echo "----------\n";

if ( 'level_1' === $new_level || 'level_2' === $new_level || 'top_rated' === $new_level ) {
	echo "- " . esc_html__( 'Enhanced visibility in search results', 'wp-sell-services' ) . "\n";
	echo "- " . esc_html__( 'Trusted seller badge on your profile', 'wp-sell-services' ) . "\n";
}

if ( 'level_2' === $new_level || 'top_rated' === $new_level ) {
	echo "- " . esc_html__( 'Priority customer support', 'wp-sell-services' ) . "\n";
	echo "- " . esc_html__( 'Featured placement opportunities', 'wp-sell-services' ) . "\n";
}

if ( 'top_rated' === $new_level ) {
	echo "- " . esc_html__( 'Top Rated badge - highest level of trust', 'wp-sell-services' ) . "\n";
	echo "- " . esc_html__( 'Early access to new features', 'wp-sell-services' ) . "\n";
	echo "- " . esc_html__( 'Exclusive promotions and opportunities', 'wp-sell-services' ) . "\n";
}

echo "\n";
echo esc_html__( 'View My Dashboard:', 'wp-sell-services' ) . ' ';
echo esc_url( wpss_get_page_url( 'dashboard' ) );
echo "\n\n";

echo esc_html__( 'Thank you for being a valued member of our marketplace!', 'wp-sell-services' );
echo "\n\n";

echo "---\n";
echo esc_html( wpss_get_platform_name() ) . "\n";
echo esc_url( home_url() );
