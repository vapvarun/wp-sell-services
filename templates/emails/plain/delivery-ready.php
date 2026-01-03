<?php
/**
 * Delivery Ready email (plain text)
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
$vendor  = get_user_by( 'id', $order->vendor_id );

printf(
	/* translators: %s: Vendor name */
	esc_html__( '%s has delivered your order! Please review the delivery and accept it if you are satisfied.', 'wp-sell-services' ),
	esc_html( $vendor ? $vendor->display_name : __( 'The seller', 'wp-sell-services' ) )
);
echo "\n\n";

/* translators: %s: Order number */
printf( esc_html__( 'Order #%s', 'wp-sell-services' ), esc_html( $order->order_number ) );
echo "\n\n";

echo esc_html__( 'Service:', 'wp-sell-services' ) . ' ' . esc_html( $service ? $service->title : __( 'N/A', 'wp-sell-services' ) ) . "\n";
echo esc_html__( 'Seller:', 'wp-sell-services' ) . ' ' . esc_html( $vendor ? $vendor->display_name : __( 'N/A', 'wp-sell-services' ) ) . "\n";

if ( $order->revisions_remaining > 0 ) {
	echo esc_html__( 'Revisions Remaining:', 'wp-sell-services' ) . ' ' . esc_html( $order->revisions_remaining ) . "\n";
}
echo "\n";

echo esc_html__( 'Please review the delivery carefully. You can accept the delivery or request revisions if needed.', 'wp-sell-services' ) . "\n\n";

echo esc_html__( 'Review Delivery:', 'wp-sell-services' ) . ' ' . esc_url( wpss_get_order_url( $order->id ) ) . "\n";

echo "\n\n----------------------------------------\n\n";

echo esc_html( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
