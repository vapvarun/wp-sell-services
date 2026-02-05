<?php
/**
 * Requirements Reminder Email (Plain Text)
 *
 * Sent to buyer when they haven't submitted requirements.
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.0.0
 *
 * @var WPSellServices\Models\ServiceOrder $order Service order object.
 * @var WP_User $recipient     Recipient user object.
 * @var string  $email_heading Email heading.
 * @var int     $reminder_num  Reminder number (1, 2, or 3).
 * @var string  $vendor_name   Vendor display name.
 * @var string  $service_title Service title.
 */

defined( 'ABSPATH' ) || exit;

$is_final = 3 === $reminder_num;

echo "= " . esc_html( $email_heading ) . " =\n\n";

printf(
	/* translators: %s: recipient name */
	esc_html__( 'Hi %s,', 'wp-sell-services' ),
	esc_html( $recipient->display_name )
);
echo "\n\n";

if ( $is_final ) {
	echo esc_html__( '*** FINAL REMINDER ***', 'wp-sell-services' ) . "\n\n";
}

echo esc_html__( 'Your vendor is waiting for your project requirements to start working on your order.', 'wp-sell-services' );
echo "\n\n";

echo "----------\n";
printf( esc_html__( 'Order: #%s', 'wp-sell-services' ), esc_html( $order->order_number ) );
echo "\n";
printf( esc_html__( 'Service: %s', 'wp-sell-services' ), esc_html( $service_title ) );
echo "\n";
printf( esc_html__( 'Vendor: %s', 'wp-sell-services' ), esc_html( $vendor_name ) );
echo "\n";
echo esc_html__( 'Status: Awaiting Requirements', 'wp-sell-services' );
echo "\n----------\n\n";

echo esc_html__( 'Please submit your requirements so the vendor can begin working on your order.', 'wp-sell-services' );
echo "\n\n";

echo esc_html__( 'Submit Requirements:', 'wp-sell-services' ) . ' ';
echo esc_url( wpss_get_order_url( $order->id ) );
echo "\n\n";

if ( $is_final ) {
	echo esc_html__( 'If you have questions, feel free to message your vendor through the order page.', 'wp-sell-services' );
	echo "\n\n";
}

echo "---\n";
echo esc_html( get_bloginfo( 'name' ) ) . "\n";
echo esc_url( home_url() );
