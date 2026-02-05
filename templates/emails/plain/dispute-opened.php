<?php
/**
 * Dispute Opened Email (Plain Text)
 *
 * Sent when a dispute is opened on an order.
 * This template can be overridden in your theme:
 * yourtheme/wp-sell-services/emails/plain/dispute-opened.php
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.0.0
 *
 * @var WPSellServices\Models\ServiceOrder $order Service order object.
 * @var WP_User $recipient      Recipient user object.
 * @var string  $email_heading  Email heading.
 * @var string  $dispute_reason Reason for the dispute (optional).
 */

defined( 'ABSPATH' ) || exit;

echo "= " . esc_html( $email_heading ) . " =\n\n";

printf(
	/* translators: %s: recipient name */
	esc_html__( 'Hi %s,', 'wp-sell-services' ),
	esc_html( $recipient ? $recipient->display_name : __( 'there', 'wp-sell-services' ) )
);
echo "\n\n";

echo esc_html__( 'A dispute has been opened on your order. Our support team will review the case and reach out to both parties.', 'wp-sell-services' );
echo "\n\n";

echo "----------\n";
printf( esc_html__( 'Order: #%s', 'wp-sell-services' ), esc_html( $order->order_number ) );
echo "\n";
printf( esc_html__( 'Service: %s', 'wp-sell-services' ), esc_html( get_the_title( $order->service_id ) ) );
echo "\n";
echo esc_html__( 'Status: Disputed', 'wp-sell-services' );
echo "\n----------\n\n";

if ( ! empty( $dispute_reason ) ) {
	echo esc_html__( 'Dispute Reason:', 'wp-sell-services' ) . ' ' . esc_html( $dispute_reason );
	echo "\n\n";
}

echo esc_html__( 'You can respond to the dispute and provide additional information through the order page.', 'wp-sell-services' );
echo "\n\n";

echo esc_html__( 'View Dispute Details:', 'wp-sell-services' ) . ' ';
echo esc_url( wpss_get_order_url( $order->id ) );
echo "\n\n";

echo "---\n";
echo esc_html( get_bloginfo( 'name' ) ) . "\n";
echo esc_url( home_url() );
