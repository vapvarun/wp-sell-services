<?php
/**
 * New Order Email (Plain Text)
 *
 * Sent to vendor when a new service order is placed.
 * This template can be overridden in your theme:
 * yourtheme/wp-sell-services/emails/plain/new-order.php
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.0.0
 *
 * @var WPSellServices\Models\ServiceOrder $order Service order object.
 * @var WP_User $recipient     Recipient user object (vendor).
 * @var string  $email_heading Email heading.
 */

defined( 'ABSPATH' ) || exit;

$customer      = get_user_by( 'id', $order->customer_id );
$customer_name = $customer ? $customer->display_name : __( 'A customer', 'wp-sell-services' );
$vendor        = isset( $recipient ) ? $recipient : get_user_by( 'id', $order->vendor_id );

echo '= ' . esc_html( $email_heading ) . " =\n\n";

printf(
	/* translators: %s: vendor name */
	esc_html__( 'Hi %s,', 'wp-sell-services' ),
	esc_html( $vendor ? $vendor->display_name : __( 'there', 'wp-sell-services' ) )
);
echo "\n\n";

echo esc_html__( 'Great news! You have received a new service order.', 'wp-sell-services' );
echo "\n\n";

echo "----------\n";
printf( esc_html__( 'Order: #%s', 'wp-sell-services' ), esc_html( $order->order_number ) );
echo "\n";
printf( esc_html__( 'Service: %s', 'wp-sell-services' ), esc_html( get_the_title( $order->service_id ) ) );
echo "\n";
printf( esc_html__( 'Package: %s', 'wp-sell-services' ), esc_html( ucfirst( $order->package_type ) ) );
echo "\n";
printf( esc_html__( 'Customer: %s', 'wp-sell-services' ), esc_html( $customer_name ) );
echo "\n";
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_strip_all_tags() is a safe function.
printf( esc_html__( 'Total: %s', 'wp-sell-services' ), wp_strip_all_tags( wpss_format_price( $order->total ) ) );
echo "\n----------\n\n";

echo esc_html__( 'The customer will submit their requirements shortly. You\'ll receive another notification when they do.', 'wp-sell-services' );
echo "\n\n";

echo esc_html__( 'View Order:', 'wp-sell-services' ) . ' ';
echo esc_url( wpss_get_order_url( $order->id ) );
echo "\n\n";

echo "---\n";
echo esc_html( wpss_get_platform_name() ) . "\n";
echo esc_url( home_url() );
