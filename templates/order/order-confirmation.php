<?php
/**
 * Template: Order Confirmation
 *
 * Displays the thank you / confirmation page after order placement.
 * Uses CSS classes from orders.css design system.
 *
 * @package WPSellServices\Templates
 * @since   1.0.0
 *
 * @var int $order_id Order ID passed from parent template.
 */

defined( 'ABSPATH' ) || exit;

// Enqueue orders styles.
wp_enqueue_style( 'wpss-orders', WPSS_PLUGIN_URL . 'assets/css/orders.css', array( 'wpss-design-system' ), WPSS_VERSION );

if ( empty( $order_id ) ) {
	return;
}

$order = wpss_get_order( $order_id );

if ( ! $order ) {
	echo '<div class="wpss-notice wpss-notice--error">' . esc_html__( 'Order not found.', 'wp-sell-services' ) . '</div>';
	return;
}

$user_id     = get_current_user_id();
$is_customer = (int) $order->customer_id === $user_id;

// Only customers and admins can view this.
if ( ! $is_customer && ! current_user_can( 'manage_options' ) ) {
	echo '<div class="wpss-notice wpss-notice--error">' . esc_html__( 'You do not have permission to view this page.', 'wp-sell-services' ) . '</div>';
	return;
}

$service = get_post( $order->service_id );
$vendor  = get_userdata( $order->vendor_id );

// Determine next action based on status.
$needs_requirements = in_array( $order->status, array( 'pending_requirements', 'pending_payment' ), true );
?>

<div class="wpss-order-confirmation">
	<div class="wpss-order-confirmation__header">
		<div class="wpss-order-confirmation__icon">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
				<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
				<polyline points="22 4 12 14.01 9 11.01"></polyline>
			</svg>
		</div>

		<h1 class="wpss-order-confirmation__title"><?php esc_html_e( 'Order Placed Successfully!', 'wp-sell-services' ); ?></h1>

		<p class="wpss-order-confirmation__message">
			<?php
			printf(
				/* translators: %s: order number */
				esc_html__( 'Thank you for your order. Your order number is %s.', 'wp-sell-services' ),
				'<strong>' . esc_html( $order->order_number ) . '</strong>'
			);
			?>
		</p>
	</div>

	<div class="wpss-order-confirmation__content">
		<!-- Order Summary -->
		<div class="wpss-order-confirmation__card">
			<h3 class="wpss-order-confirmation__card-title"><?php esc_html_e( 'Order Summary', 'wp-sell-services' ); ?></h3>

			<div class="wpss-order-confirmation__item">
				<?php if ( $service && has_post_thumbnail( $service->ID ) ) : ?>
					<img src="<?php echo esc_url( get_the_post_thumbnail_url( $service->ID, 'thumbnail' ) ); ?>"
						alt="<?php echo esc_attr( $service->post_title ); ?>"
						class="wpss-order-confirmation__item-thumb">
				<?php endif; ?>
				<div class="wpss-order-confirmation__item-details">
					<h4 class="wpss-order-confirmation__item-title"><?php echo esc_html( $service ? $service->post_title : __( 'Service', 'wp-sell-services' ) ); ?></h4>
					<?php if ( $vendor ) : ?>
						<p class="wpss-order-confirmation__item-vendor">
							<?php
							printf(
								/* translators: %s: vendor name */
								esc_html__( 'by %s', 'wp-sell-services' ),
								esc_html( $vendor->display_name )
							);
							?>
						</p>
					<?php endif; ?>
				</div>
				<div class="wpss-order-confirmation__item-price">
					<?php echo esc_html( wpss_format_price( (float) $order->total, $order->currency ) ); ?>
				</div>
			</div>

			<div class="wpss-order-confirmation__totals">
				<div class="wpss-order-confirmation__total-row">
					<span><?php esc_html_e( 'Subtotal', 'wp-sell-services' ); ?></span>
					<span><?php echo esc_html( wpss_format_price( (float) $order->subtotal, $order->currency ) ); ?></span>
				</div>
				<?php if ( $order->addons_total > 0 ) : ?>
					<div class="wpss-order-confirmation__total-row">
						<span><?php esc_html_e( 'Add-ons', 'wp-sell-services' ); ?></span>
						<span><?php echo esc_html( wpss_format_price( (float) $order->addons_total, $order->currency ) ); ?></span>
					</div>
				<?php endif; ?>
				<div class="wpss-order-confirmation__total-row wpss-order-confirmation__total-row--grand">
					<span><?php esc_html_e( 'Total', 'wp-sell-services' ); ?></span>
					<span><?php echo esc_html( wpss_format_price( (float) $order->total, $order->currency ) ); ?></span>
				</div>
			</div>
		</div>

		<!-- Next Steps -->
		<div class="wpss-order-confirmation__card wpss-order-confirmation__card--steps">
			<h3 class="wpss-order-confirmation__card-title"><?php esc_html_e( 'What Happens Next?', 'wp-sell-services' ); ?></h3>

			<?php if ( $needs_requirements ) : ?>
				<div class="wpss-order-confirmation__step wpss-order-confirmation__step--active">
					<span class="wpss-order-confirmation__step-number">1</span>
					<div class="wpss-order-confirmation__step-content">
						<h4><?php esc_html_e( 'Submit Requirements', 'wp-sell-services' ); ?></h4>
						<p><?php esc_html_e( 'Provide the details the seller needs to start working on your order.', 'wp-sell-services' ); ?></p>
						<a href="<?php echo esc_url( wpss_get_order_requirements_url( $order_id ) ); ?>" class="wpss-btn wpss-btn--primary">
							<?php esc_html_e( 'Submit Requirements Now', 'wp-sell-services' ); ?>
						</a>
					</div>
				</div>

				<div class="wpss-order-confirmation__step">
					<span class="wpss-order-confirmation__step-number">2</span>
					<div class="wpss-order-confirmation__step-content">
						<h4><?php esc_html_e( 'Seller Works on Order', 'wp-sell-services' ); ?></h4>
						<p><?php esc_html_e( 'Once requirements are submitted, the seller will start working on your order.', 'wp-sell-services' ); ?></p>
					</div>
				</div>

				<div class="wpss-order-confirmation__step">
					<span class="wpss-order-confirmation__step-number">3</span>
					<div class="wpss-order-confirmation__step-content">
						<h4><?php esc_html_e( 'Review Delivery', 'wp-sell-services' ); ?></h4>
						<p><?php esc_html_e( 'Accept the delivery or request revisions if needed.', 'wp-sell-services' ); ?></p>
					</div>
				</div>
			<?php else : ?>
				<div class="wpss-order-confirmation__step wpss-order-confirmation__step--completed">
					<span class="wpss-order-confirmation__step-number">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
							<polyline points="20 6 9 17 4 12"></polyline>
						</svg>
					</span>
					<div class="wpss-order-confirmation__step-content">
						<h4><?php esc_html_e( 'Order Placed', 'wp-sell-services' ); ?></h4>
						<p><?php esc_html_e( 'Your order has been placed successfully.', 'wp-sell-services' ); ?></p>
					</div>
				</div>

				<div class="wpss-order-confirmation__step wpss-order-confirmation__step--active">
					<span class="wpss-order-confirmation__step-number">2</span>
					<div class="wpss-order-confirmation__step-content">
						<h4><?php esc_html_e( 'Seller Working', 'wp-sell-services' ); ?></h4>
						<p><?php esc_html_e( 'The seller is now working on your order.', 'wp-sell-services' ); ?></p>
					</div>
				</div>

				<div class="wpss-order-confirmation__step">
					<span class="wpss-order-confirmation__step-number">3</span>
					<div class="wpss-order-confirmation__step-content">
						<h4><?php esc_html_e( 'Review Delivery', 'wp-sell-services' ); ?></h4>
						<p><?php esc_html_e( 'Accept the delivery or request revisions if needed.', 'wp-sell-services' ); ?></p>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<!-- Order Details -->
		<div class="wpss-order-confirmation__card">
			<h3 class="wpss-order-confirmation__card-title"><?php esc_html_e( 'Order Details', 'wp-sell-services' ); ?></h3>

			<dl class="wpss-order-confirmation__details-list">
				<dt><?php esc_html_e( 'Order Number', 'wp-sell-services' ); ?></dt>
				<dd><?php echo esc_html( $order->order_number ); ?></dd>

				<dt><?php esc_html_e( 'Date', 'wp-sell-services' ); ?></dt>
				<dd>
					<?php
					$created_at = $order->created_at instanceof \DateTimeImmutable
						? $order->created_at->format( 'Y-m-d H:i:s' )
						: $order->created_at;
					echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $created_at ) ) );
					?>
				</dd>

				<dt><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></dt>
				<dd>
					<span class="wpss-badge wpss-badge--status-<?php echo esc_attr( str_replace( '_', '-', $order->status ) ); ?>">
						<?php echo esc_html( wpss_get_order_status_label( $order->status ) ); ?>
					</span>
				</dd>

				<?php if ( $order->delivery_deadline ) : ?>
					<dt><?php esc_html_e( 'Expected Delivery', 'wp-sell-services' ); ?></dt>
					<dd>
						<?php
						$deadline = $order->delivery_deadline instanceof \DateTimeImmutable
							? $order->delivery_deadline->format( 'Y-m-d H:i:s' )
							: $order->delivery_deadline;
						echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $deadline ) ) );
						?>
					</dd>
				<?php endif; ?>

				<dt><?php esc_html_e( 'Revisions Included', 'wp-sell-services' ); ?></dt>
				<dd>
					<?php
					if ( -1 === $order->revisions_included ) {
						esc_html_e( 'Unlimited', 'wp-sell-services' );
					} else {
						echo esc_html( $order->revisions_included );
					}
					?>
				</dd>
			</dl>
		</div>
	</div>

	<div class="wpss-order-confirmation__actions">
		<a href="<?php echo esc_url( wpss_get_order_url( $order_id ) ); ?>" class="wpss-btn wpss-btn--outline">
			<?php esc_html_e( 'View Order Details', 'wp-sell-services' ); ?>
		</a>
		<a href="<?php echo esc_url( wpss_get_dashboard_url( 'orders' ) ); ?>" class="wpss-btn wpss-btn--secondary">
			<?php esc_html_e( 'Go to My Orders', 'wp-sell-services' ); ?>
		</a>
	</div>
</div>

<?php
/**
 * Hook: wpss_after_order_confirmation
 *
 * @param object $order Order object.
 */
do_action( 'wpss_after_order_confirmation', $order );
