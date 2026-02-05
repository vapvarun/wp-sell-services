<?php
/**
 * Order Completed Email (Plain Text)
 *
 * Sent to buyer when their order is marked as completed.
 * This template can be overridden in your theme:
 * yourtheme/wp-sell-services/emails/plain/order-completed.php
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.0.0
 *
 * @var WPSellServices\Models\ServiceOrder $order Service order object.
 * @var WP_User $recipient     Recipient user object (buyer).
 * @var string  $email_heading Email heading.
 */

defined( 'ABSPATH' ) || exit;

$customer    = isset( $recipient ) ? $recipient : get_user_by( 'id', $order->customer_id );
$vendor      = get_user_by( 'id', $order->vendor_id );
$vendor_name = $vendor ? $vendor->display_name : __( 'the vendor', 'wp-sell-services' );

echo "= " . esc_html( $email_heading ) . " =\n\n";

printf(
	/* translators: %s: customer name */
	esc_html__( 'Hi %s,', 'wp-sell-services' ),
	esc_html( $customer ? $customer->display_name : __( 'there', 'wp-sell-services' ) )
);
echo "\n\n";

echo esc_html__( 'Great news! Your service order has been completed.', 'wp-sell-services' );
echo "\n\n";

echo "----------\n";
printf( esc_html__( 'Order: #%s', 'wp-sell-services' ), esc_html( $order->order_number ) );
echo "\n";
printf( esc_html__( 'Service: %s', 'wp-sell-services' ), esc_html( get_the_title( $order->service_id ) ) );
echo "\n";
printf( esc_html__( 'Vendor: %s', 'wp-sell-services' ), esc_html( $vendor_name ) );
echo "\n";
printf( esc_html__( 'Total: %s', 'wp-sell-services' ), wp_strip_all_tags( wpss_format_price( $order->total ) ) );
echo "\n";
echo esc_html__( 'Status: Completed', 'wp-sell-services' );
echo "\n----------\n\n";

echo esc_html__( 'If you\'re happy with your order, please take a moment to leave a review for the vendor.', 'wp-sell-services' );
echo "\n\n";

echo esc_html__( 'View Order & Leave Review:', 'wp-sell-services' ) . ' ';
echo esc_url( wpss_get_order_url( $order->id ) );
echo "\n\n";

echo esc_html__( 'Thank you for using our marketplace!', 'wp-sell-services' );
echo "\n\n";

echo "---\n";
echo esc_html( get_bloginfo( 'name' ) ) . "\n";
echo esc_url( home_url() );
