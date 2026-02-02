<?php
/**
 * Order Cancelled Email (Plain Text)
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html( wp_strip_all_tags( $email_heading ) );
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo esc_html__( 'Unfortunately, your service order has been cancelled.', 'wp-sell-services' ) . "\n\n";

printf( esc_html__( 'Order: #%s', 'wp-sell-services' ), esc_html( $order->order_number ) );
echo "\n";
printf( esc_html__( 'Service: %s', 'wp-sell-services' ), esc_html( get_the_title( $order->service_id ) ) );
echo "\n\n";

echo esc_html__( 'If you have any questions, please contact support.', 'wp-sell-services' ) . "\n";
