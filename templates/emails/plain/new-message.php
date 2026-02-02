<?php
/**
 * New Message Email (Plain Text)
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html( wp_strip_all_tags( $email_heading ) );
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

printf( esc_html__( 'Order: #%s', 'wp-sell-services' ), esc_html( $order->order_number ) );
echo "\n\n";

if ( ! empty( $message_content ) ) {
	echo esc_html__( 'Message:', 'wp-sell-services' ) . "\n";
	echo esc_html( wp_strip_all_tags( $message_content ) ) . "\n\n";
}

printf( esc_html__( 'Reply: %s', 'wp-sell-services' ), esc_url( wpss_get_order_url( $order->id ) ) );
echo "\n";
