<?php
/**
 * New Order Email (Plain Text)
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html( wp_strip_all_tags( $email_heading ) );
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo esc_html__( 'You have received a new service order!', 'wp-sell-services' ) . "\n\n";

printf( esc_html__( 'Order: #%s', 'wp-sell-services' ), esc_html( $order->order_number ) );
echo "\n";
printf( esc_html__( 'Service: %s', 'wp-sell-services' ), esc_html( get_the_title( $order->service_id ) ) );
echo "\n";
printf( esc_html__( 'Total: %s', 'wp-sell-services' ), wp_strip_all_tags( wpss_format_price( $order->total ) ) );
echo "\n\n";
printf( esc_html__( 'View order: %s', 'wp-sell-services' ), esc_url( wpss_get_order_url( $order->id ) ) );
echo "\n";
