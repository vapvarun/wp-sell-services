<?php
/**
 * Order Cancelled Email (Plain Text)
 *
 * Sent when an order is cancelled.
 * This template can be overridden in your theme:
 * yourtheme/wp-sell-services/emails/plain/order-cancelled.php
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.0.0
 *
 * @var WPSellServices\Models\ServiceOrder $order Service order object.
 * @var WP_User $recipient     Recipient user object.
 * @var string  $email_heading Email heading.
 * @var string  $reason        Cancellation reason (optional).
 */

defined( 'ABSPATH' ) || exit;

echo "= " . esc_html( $email_heading ) . " =\n\n";

printf(
	/* translators: %s: recipient name */
	esc_html__( 'Hi %s,', 'wp-sell-services' ),
	esc_html( $recipient ? $recipient->display_name : __( 'there', 'wp-sell-services' ) )
);
echo "\n\n";

echo esc_html__( 'We\'re sorry to inform you that your service order has been cancelled.', 'wp-sell-services' );
echo "\n\n";

echo "----------\n";
printf( esc_html__( 'Order: #%s', 'wp-sell-services' ), esc_html( $order->order_number ) );
echo "\n";
printf( esc_html__( 'Service: %s', 'wp-sell-services' ), esc_html( get_the_title( $order->service_id ) ) );
echo "\n";
echo esc_html__( 'Status: Cancelled', 'wp-sell-services' );
echo "\n----------\n\n";

if ( ! empty( $reason ) ) {
	echo esc_html__( 'Reason:', 'wp-sell-services' ) . ' ' . esc_html( $reason );
	echo "\n\n";
}

echo esc_html__( 'If you have any questions or believe this was done in error, please contact our support team.', 'wp-sell-services' );
echo "\n\n";

echo esc_html__( 'View Order Details:', 'wp-sell-services' ) . ' ';
echo esc_url( wpss_get_order_url( $order->id ) );
echo "\n\n";

echo "---\n";
echo esc_html( get_bloginfo( 'name' ) ) . "\n";
echo esc_url( home_url() );
