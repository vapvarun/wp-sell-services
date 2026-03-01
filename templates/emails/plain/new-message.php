<?php
/**
 * New Message Email (Plain Text)
 *
 * Sent when there's a new message on an order.
 * This template can be overridden in your theme:
 * yourtheme/wp-sell-services/emails/plain/new-message.php
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.0.0
 *
 * @var WPSellServices\Models\ServiceOrder $order Service order object.
 * @var WP_User $recipient       Recipient user object.
 * @var string  $email_heading   Email heading.
 * @var string  $message_content Message content.
 * @var string  $sender_name     Name of the message sender.
 */

defined( 'ABSPATH' ) || exit;

$sender_name = $sender_name ?? __( 'Someone', 'wp-sell-services' );

echo "= " . esc_html( $email_heading ) . " =\n\n";

printf(
	/* translators: %s: recipient name */
	esc_html__( 'Hi %s,', 'wp-sell-services' ),
	esc_html( $recipient ? $recipient->display_name : __( 'there', 'wp-sell-services' ) )
);
echo "\n\n";

printf(
	/* translators: %s: sender name */
	esc_html__( 'You have a new message from %s on your order.', 'wp-sell-services' ),
	esc_html( $sender_name )
);
echo "\n\n";

echo "----------\n";
printf( esc_html__( 'Order: #%s', 'wp-sell-services' ), esc_html( $order->order_number ) );
echo "\n----------\n\n";

if ( ! empty( $message_content ) ) {
	echo esc_html__( 'Message:', 'wp-sell-services' ) . "\n";
	echo "----------------------------------------\n";
	echo esc_html( wp_strip_all_tags( $message_content ) );
	echo "\n----------------------------------------\n\n";
}

echo esc_html__( 'Reply to Message:', 'wp-sell-services' ) . ' ';
echo esc_url( wpss_get_order_url( $order->id ) );
echo "\n\n";

echo "---\n";
echo esc_html( wpss_get_platform_name() ) . "\n";
echo esc_url( home_url() );
