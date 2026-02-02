<?php
/**
 * Order In Progress Email (Plain Text)
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html( wp_strip_all_tags( $email_heading ) );
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo esc_html__( 'Good news! The vendor has started working on your order.', 'wp-sell-services' ) . "\n\n";

printf( esc_html__( 'Order: #%s', 'wp-sell-services' ), esc_html( $order->order_number ) );
echo "\n";
printf( esc_html__( 'Service: %s', 'wp-sell-services' ), esc_html( get_the_title( $order->service_id ) ) );
echo "\n\n";
printf( esc_html__( 'View order: %s', 'wp-sell-services' ), esc_url( wpss_get_order_url( $order->id ) ) );
echo "\n";
