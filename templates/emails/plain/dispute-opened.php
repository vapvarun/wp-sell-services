<?php
/**
 * Dispute Opened Email (Plain Text)
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html( wp_strip_all_tags( $email_heading ) );
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo esc_html__( 'A dispute has been opened on your order.', 'wp-sell-services' ) . "\n\n";

printf( esc_html__( 'Order: #%s', 'wp-sell-services' ), esc_html( $order->order_number ) );
echo "\n";
printf( esc_html__( 'Service: %s', 'wp-sell-services' ), esc_html( get_the_title( $order->service_id ) ) );
echo "\n\n";

echo esc_html__( 'Our support team will review this dispute and reach out to both parties.', 'wp-sell-services' ) . "\n\n";

printf( esc_html__( 'View order: %s', 'wp-sell-services' ), esc_url( wpss_get_order_url( $order->id ) ) );
echo "\n";
