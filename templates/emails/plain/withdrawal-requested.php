<?php
/**
 * Withdrawal Requested Email (Plain Text)
 *
 * Sent to admin when a vendor submits a withdrawal request.
 * This template can be overridden in your theme:
 * yourtheme/wp-sell-services/emails/plain/withdrawal-requested.php
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.3.0
 *
 * @var WP_User|null $vendor          Vendor user object.
 * @var float        $amount          Requested withdrawal amount.
 * @var int          $withdrawal_id   Withdrawal record ID.
 * @var string       $admin_panel_url URL to the admin withdrawals page.
 * @var string       $email_heading   Email heading.
 */

defined( 'ABSPATH' ) || exit;

echo '= ' . esc_html( $email_heading ) . " =\n\n";

echo esc_html__( 'Hi Admin,', 'wp-sell-services' );
echo "\n\n";

echo esc_html__( 'A vendor has submitted a new withdrawal request that requires your review.', 'wp-sell-services' );
echo "\n\n";

echo "----------\n";
printf( esc_html__( 'Vendor: %s', 'wp-sell-services' ), esc_html( $vendor ? $vendor->display_name : __( 'Unknown', 'wp-sell-services' ) ) );
echo "\n";
printf( esc_html__( 'Email: %s', 'wp-sell-services' ), esc_html( $vendor ? $vendor->user_email : '' ) );
echo "\n";
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_strip_all_tags() is a safe function.
printf( esc_html__( 'Amount Requested: %s', 'wp-sell-services' ), wp_strip_all_tags( wpss_format_price( $amount ) ) );
echo "\n----------\n\n";

echo esc_html__( 'Review Withdrawal:', 'wp-sell-services' ) . ' ';
echo esc_url( $admin_panel_url );
echo "\n\n";

echo "---\n";
echo esc_html( wpss_get_platform_name() ) . "\n";
echo esc_url( home_url() );
