<?php
/**
 * Cancellation Requested Email (Plain Text)
 *
 * Sent to the vendor when a buyer requests order cancellation.
 * This template can be overridden in your theme:
 * yourtheme/wp-sell-services/emails/plain/cancellation-requested.php
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.3.0
 *
 * @var WPSellServices\Models\ServiceOrder $order Service order object.
 * @var WP_User $recipient     Recipient user object (vendor).
 * @var string  $email_heading Email heading.
 * @var string  $buyer_name    Buyer display name.
 * @var string  $reason        Cancellation reason label.
 * @var string  $note          Optional additional note from buyer.
 * @var string  $deadline      Response deadline (formatted date).
 */

defined( 'ABSPATH' ) || exit;

echo "= " . esc_html( $email_heading ) . " =\n\n";

printf(
	/* translators: %s: recipient name */
	esc_html__( 'Hi %s,', 'wp-sell-services' ),
	esc_html( $recipient ? $recipient->display_name : __( 'there', 'wp-sell-services' ) )
);
echo "\n\n";

if ( ! empty( $is_customer ) ) {
	echo esc_html__( 'Your cancellation request has been submitted for the following order. The vendor has 48 hours to respond.', 'wp-sell-services' );
} else {
	printf(
		/* translators: %s: buyer name */
		esc_html__( '%s has requested to cancel the following order. Please review and respond within 48 hours.', 'wp-sell-services' ),
		esc_html( $buyer_name )
	);
}
echo "\n\n";

echo "----------\n";
printf( esc_html__( 'Order: #%s', 'wp-sell-services' ), esc_html( $order->order_number ) );
echo "\n";
printf( esc_html__( 'Service: %s', 'wp-sell-services' ), esc_html( get_the_title( $order->service_id ) ) );
echo "\n";
printf( esc_html__( 'Order Total: %s', 'wp-sell-services' ), esc_html( wpss_format_price( (float) $order->total, $order->currency ) ) );
echo "\n";
echo esc_html__( 'Status: Cancellation Requested', 'wp-sell-services' );
echo "\n----------\n\n";

echo esc_html__( 'Cancellation Reason:', 'wp-sell-services' ) . ' ' . esc_html( $reason );
echo "\n";

if ( ! empty( $note ) ) {
	echo esc_html__( 'Additional Details:', 'wp-sell-services' ) . ' ' . esc_html( $note );
	echo "\n";
}

echo "\n";

printf(
	/* translators: %s: response deadline */
	esc_html__( 'IMPORTANT: You have until %s to respond.', 'wp-sell-services' ),
	esc_html( $deadline )
);
echo "\n";
echo esc_html__( 'If you do not respond, the order will be automatically cancelled.', 'wp-sell-services' );
echo "\n\n";

echo esc_html__( 'You can:', 'wp-sell-services' );
echo "\n";
echo '- ' . esc_html__( 'Accept Cancellation', 'wp-sell-services' ) . ' -- ' . esc_html__( 'the order will be cancelled and a refund may be initiated.', 'wp-sell-services' );
echo "\n";
echo '- ' . esc_html__( 'Dispute Cancellation', 'wp-sell-services' ) . ' -- ' . esc_html__( 'the case will be escalated to admin mediation.', 'wp-sell-services' );
echo "\n\n";

echo esc_html__( 'Respond to Request:', 'wp-sell-services' ) . ' ';
echo esc_url( wpss_get_order_url( $order->id ) );
echo "\n\n";

echo "---\n";
echo esc_html( get_bloginfo( 'name' ) ) . "\n";
echo esc_url( home_url() );
