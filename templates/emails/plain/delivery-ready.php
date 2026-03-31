<?php
/**
 * Delivery Ready Email (Plain Text)
 *
 * Sent to buyer when the vendor submits a delivery for review.
 * This template can be overridden in your theme:
 * yourtheme/wp-sell-services/emails/plain/delivery-ready.php
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
$vendor_name = $vendor ? $vendor->display_name : __( 'The vendor', 'wp-sell-services' );

echo '= ' . esc_html( $email_heading ) . " =\n\n";

printf(
	/* translators: %s: customer name */
	esc_html__( 'Hi %s,', 'wp-sell-services' ),
	esc_html( $customer ? $customer->display_name : __( 'there', 'wp-sell-services' ) )
);
echo "\n\n";

printf(
	/* translators: %s: vendor name */
	esc_html__( 'Great news! %s has submitted a delivery for your order.', 'wp-sell-services' ),
	esc_html( $vendor_name )
);
echo "\n\n";

echo "----------\n";
printf( esc_html__( 'Order: #%s', 'wp-sell-services' ), esc_html( $order->order_number ) );
echo "\n";
printf( esc_html__( 'Service: %s', 'wp-sell-services' ), esc_html( get_the_title( $order->service_id ) ) );
echo "\n";
printf( esc_html__( 'Vendor: %s', 'wp-sell-services' ), esc_html( $vendor_name ) );
echo "\n----------\n\n";

echo esc_html__( 'Please review the delivery and either accept it to complete the order, or request a revision if changes are needed.', 'wp-sell-services' );
echo "\n\n";

echo esc_html__( 'Review Delivery:', 'wp-sell-services' ) . ' ';
echo esc_url( wpss_get_order_url( $order->id ) );
echo "\n\n";

echo "---\n";
echo esc_html( wpss_get_platform_name() ) . "\n";
echo esc_url( home_url() );
