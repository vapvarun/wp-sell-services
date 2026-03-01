<?php
/**
 * Revision Requested Email (Plain Text)
 *
 * Sent to vendor when the buyer requests a revision.
 * This template can be overridden in your theme:
 * yourtheme/wp-sell-services/emails/plain/revision-requested.php
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.0.0
 *
 * @var WPSellServices\Models\ServiceOrder $order Service order object.
 * @var WP_User $recipient     Recipient user object (vendor).
 * @var string  $email_heading Email heading.
 */

defined( 'ABSPATH' ) || exit;

$vendor        = isset( $recipient ) ? $recipient : get_user_by( 'id', $order->vendor_id );
$customer      = get_user_by( 'id', $order->customer_id );
$customer_name = $customer ? $customer->display_name : __( 'The customer', 'wp-sell-services' );

echo "= " . esc_html( $email_heading ) . " =\n\n";

printf(
	/* translators: %s: vendor name */
	esc_html__( 'Hi %s,', 'wp-sell-services' ),
	esc_html( $vendor ? $vendor->display_name : __( 'there', 'wp-sell-services' ) )
);
echo "\n\n";

printf(
	/* translators: %s: customer name */
	esc_html__( '%s has requested a revision for their delivery.', 'wp-sell-services' ),
	esc_html( $customer_name )
);
echo "\n\n";

echo "----------\n";
printf( esc_html__( 'Order: #%s', 'wp-sell-services' ), esc_html( $order->order_number ) );
echo "\n";
printf( esc_html__( 'Service: %s', 'wp-sell-services' ), esc_html( get_the_title( $order->service_id ) ) );
echo "\n";
echo esc_html__( 'Status: Revision Requested', 'wp-sell-services' );
echo "\n----------\n\n";

echo esc_html__( 'Please review the feedback and submit a revised delivery to meet the customer\'s expectations.', 'wp-sell-services' );
echo "\n\n";

echo esc_html__( 'View Feedback:', 'wp-sell-services' ) . ' ';
echo esc_url( wpss_get_order_url( $order->id ) );
echo "\n\n";

echo "---\n";
echo esc_html( wpss_get_platform_name() ) . "\n";
echo esc_url( home_url() );
