<?php
/**
 * Revision Requested email (plain text)
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

printf(
	/* translators: %s: Buyer name */
	esc_html__( '%s has requested a revision on their order.', 'wp-sell-services' ),
	esc_html( $buyer ? $buyer->display_name : __( 'The buyer', 'wp-sell-services' ) )
);
echo "\n\n";

/* translators: %s: Order number */
printf( esc_html__( 'Order #%s', 'wp-sell-services' ), esc_html( $order->order_number ) );
echo "\n\n";

echo esc_html__( 'Service:', 'wp-sell-services' ) . ' ' . esc_html( $service ? $service->title : __( 'N/A', 'wp-sell-services' ) ) . "\n";
echo esc_html__( 'Buyer:', 'wp-sell-services' ) . ' ' . esc_html( $buyer ? $buyer->display_name : __( 'Guest', 'wp-sell-services' ) ) . "\n";
echo esc_html__( 'Revisions Remaining:', 'wp-sell-services' ) . ' ' . esc_html( $order->revisions_remaining ) . "\n\n";

echo esc_html__( 'Please check the revision request and update your delivery accordingly.', 'wp-sell-services' ) . "\n\n";

echo esc_html__( 'View Request:', 'wp-sell-services' ) . ' ' . esc_url( add_query_arg( 'order_id', $order->id, wpss_get_dashboard_url( 'sales' ) ) ) . "\n";

echo "\n\n----------------------------------------\n\n";

echo esc_html( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
