<?php
/**
 * New Order email (plain text - sent to vendor)
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
$buyer   = get_user_by( 'id', $order->customer_id );

esc_html_e( 'You have received a new service order. Here are the details:', 'wp-sell-services' );
echo "\n\n";

/* translators: %s: Order number */
printf( esc_html__( 'Order #%s', 'wp-sell-services' ), esc_html( $order->order_number ) );
echo "\n\n";

echo esc_html__( 'Service:', 'wp-sell-services' ) . ' ' . esc_html( $service ? $service->title : __( 'N/A', 'wp-sell-services' ) ) . "\n";
echo esc_html__( 'Buyer:', 'wp-sell-services' ) . ' ' . esc_html( $buyer ? $buyer->display_name : __( 'Guest', 'wp-sell-services' ) ) . "\n";
echo esc_html__( 'Total:', 'wp-sell-services' ) . ' ' . esc_html( wpss_format_price( $order->total, $order->currency ) ) . "\n";
echo esc_html__( 'Delivery Deadline:', 'wp-sell-services' ) . ' ' . esc_html( wp_date( get_option( 'date_format' ), strtotime( $order->delivery_deadline ) ) ) . "\n\n";

echo esc_html__( 'View Order:', 'wp-sell-services' ) . ' ' . esc_url( add_query_arg( 'order_id', $order->id, wpss_get_dashboard_url( 'sales' ) ) ) . "\n";

echo "\n\n----------------------------------------\n\n";

echo esc_html( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
