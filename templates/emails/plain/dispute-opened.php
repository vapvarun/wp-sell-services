<?php
/**
 * Dispute Opened email (plain text)
 *
 * @package WPSellServices\Templates\Emails
 * @version 1.0.0
 *
 * @var \WPSellServices\Models\ServiceOrder $order
 * @var string                               $email_heading
 */

defined( 'ABSPATH' ) || exit;

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html( wp_strip_all_tags( $email_heading ) );
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

$service = wpss_get_service( $order->service_id );

echo esc_html__( 'A dispute has been opened on the following order:', 'wp-sell-services' );
echo "\n\n";

/* translators: %s: Order number */
printf( esc_html__( 'Order #%s', 'wp-sell-services' ), esc_html( $order->order_number ) );
echo "\n\n";

echo esc_html__( 'Service:', 'wp-sell-services' ) . ' ' . esc_html( $service ? $service->title : __( 'N/A', 'wp-sell-services' ) ) . "\n";
echo esc_html__( 'Total:', 'wp-sell-services' ) . ' ' . esc_html( wpss_format_price( $order->total, $order->currency ) ) . "\n";
echo esc_html__( 'Status:', 'wp-sell-services' ) . ' ' . esc_html__( 'Disputed', 'wp-sell-services' ) . "\n\n";

echo esc_html__( 'Our support team will review the dispute and help resolve the issue. You may be contacted for additional information.', 'wp-sell-services' ) . "\n\n";

echo esc_html__( 'View Order:', 'wp-sell-services' ) . ' ' . esc_url( wpss_get_order_url( $order->id ) ) . "\n";

echo "\n\n----------------------------------------\n\n";

echo esc_html( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
