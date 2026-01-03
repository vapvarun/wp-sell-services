<?php
/**
 * Requirements Submitted email (plain text)
 *
 * @package WPSellServices\Templates\Emails
 * @version 1.0.0
 *
 * @var \WPSellServices\Models\ServiceOrder $order
 * @var array                                $requirements
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
	esc_html__( '%s has submitted the requirements for your order. You can now start working on it.', 'wp-sell-services' ),
	esc_html( $buyer ? $buyer->display_name : __( 'The buyer', 'wp-sell-services' ) )
);
echo "\n\n";

/* translators: %s: Order number */
printf( esc_html__( 'Order #%s', 'wp-sell-services' ), esc_html( $order->order_number ) );
echo "\n\n";

echo esc_html__( 'Service:', 'wp-sell-services' ) . ' ' . esc_html( $service ? $service->title : __( 'N/A', 'wp-sell-services' ) ) . "\n";
$deadline_timestamp = $order->delivery_deadline instanceof \DateTimeInterface
	? $order->delivery_deadline->getTimestamp()
	: strtotime( $order->delivery_deadline );
echo esc_html__( 'Delivery Deadline:', 'wp-sell-services' ) . ' ' . esc_html( wp_date( get_option( 'date_format' ), $deadline_timestamp ) ) . "\n\n";

if ( ! empty( $requirements ) ) {
	echo esc_html__( 'Submitted Requirements:', 'wp-sell-services' ) . "\n";
	foreach ( $requirements as $field_id => $value ) {
		echo '- ' . esc_html( $field_id ) . ': ' . esc_html( is_array( $value ) ? implode( ', ', $value ) : $value ) . "\n";
	}
	echo "\n";
}

echo esc_html__( 'View Order:', 'wp-sell-services' ) . ' ' . esc_url( add_query_arg( 'order_id', $order->id, wpss_get_dashboard_url( 'sales' ) ) ) . "\n";

echo "\n\n----------------------------------------\n\n";

echo esc_html( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
