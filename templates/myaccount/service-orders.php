<?php
/**
 * Service Orders - My Account Template
 *
 * Uses CSS classes from orders.css design system.
 *
 * @package WPSellServices\Templates
 * @since   1.0.0
 *
 * @var array $orders  Service orders.
 * @var int   $user_id Current user ID.
 */

defined( 'ABSPATH' ) || exit;

// Enqueue orders styles.
wp_enqueue_style( 'wpss-orders', WPSS_PLUGIN_URL . 'assets/css/orders.css', array( 'wpss-design-system' ), WPSS_VERSION );

$statuses       = \WPSellServices\Models\ServiceOrder::get_statuses();
$current_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

/**
 * Fires before the service orders content.
 *
 * @since 1.1.0
 *
 * @param int $user_id Current user ID.
 */
do_action( 'wpss_service_orders_before', $user_id );
?>

<div class="wpss-orders">
	<!-- Header with filters -->
	<div class="wpss-orders__header">
		<h2 class="wpss-orders__title"><?php esc_html_e( 'My Orders', 'wp-sell-services' ); ?></h2>
		<div class="wpss-orders__filters">
			<select class="wpss-select" onchange="if(this.value) window.location.href=this.value">
				<option value="<?php echo esc_url( remove_query_arg( 'status' ) ); ?>" <?php selected( $current_filter, '' ); ?>>
					<?php esc_html_e( 'All Orders', 'wp-sell-services' ); ?>
				</option>
				<?php foreach ( $statuses as $status_key => $status_label ) : ?>
					<option value="<?php echo esc_url( add_query_arg( 'status', $status_key ) ); ?>" <?php selected( $current_filter, $status_key ); ?>>
						<?php echo esc_html( $status_label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
	</div>

	<?php if ( empty( $orders ) ) : ?>
		<div class="wpss-orders__empty">
			<div class="wpss-empty-state">
				<div class="wpss-empty-state__icon">
					<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
						<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
						<polyline points="14 2 14 8 20 8"/>
						<line x1="16" y1="13" x2="8" y2="13"/>
						<line x1="16" y1="17" x2="8" y2="17"/>
					</svg>
				</div>
				<h3 class="wpss-empty-state__title"><?php esc_html_e( 'No Orders Yet', 'wp-sell-services' ); ?></h3>
				<p class="wpss-empty-state__text"><?php esc_html_e( 'You haven\'t placed any service orders yet. Browse our marketplace to find services.', 'wp-sell-services' ); ?></p>
				<a href="<?php echo esc_url( home_url( '/services/' ) ); ?>" class="wpss-btn wpss-btn--primary">
					<?php esc_html_e( 'Browse Services', 'wp-sell-services' ); ?>
				</a>
			</div>
		</div>
	<?php else : ?>
		<div class="wpss-orders__list">
			<table class="wpss-table wpss-table--orders">
				<thead>
					<tr>
						<th class="wpss-table__col--order"><?php esc_html_e( 'Order', 'wp-sell-services' ); ?></th>
						<th class="wpss-table__col--service"><?php esc_html_e( 'Service', 'wp-sell-services' ); ?></th>
						<th class="wpss-table__col--status"><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></th>
						<th class="wpss-table__col--total"><?php esc_html_e( 'Total', 'wp-sell-services' ); ?></th>
						<th class="wpss-table__col--deadline"><?php esc_html_e( 'Deadline', 'wp-sell-services' ); ?></th>
						<th class="wpss-table__col--actions"><?php esc_html_e( 'Actions', 'wp-sell-services' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $orders as $order_data ) : ?>
						<?php
						$order        = \WPSellServices\Models\ServiceOrder::from_db( $order_data );
						$service      = $order->get_service();
						$status_label = $statuses[ $order->status ] ?? $order->status;
						$status_class = str_replace( '_', '-', $order->status );
						?>
						<tr class="wpss-table__row">
							<td class="wpss-table__col--order" data-label="<?php esc_attr_e( 'Order', 'wp-sell-services' ); ?>">
								<a href="<?php echo esc_url( wpss_get_order_url( $order->id ) ); ?>" class="wpss-order-link">
									#<?php echo esc_html( $order->order_number ); ?>
								</a>
								<span class="wpss-order-date">
									<?php echo esc_html( wp_date( get_option( 'date_format' ), $order->created_at->getTimestamp() ) ); ?>
								</span>
							</td>
							<td class="wpss-table__col--service" data-label="<?php esc_attr_e( 'Service', 'wp-sell-services' ); ?>">
								<?php if ( $service ) : ?>
									<div class="wpss-service-info">
										<?php if ( has_post_thumbnail( $service->id ) ) : ?>
											<img src="<?php echo esc_url( get_the_post_thumbnail_url( $service->id, 'thumbnail' ) ); ?>" alt="" class="wpss-service-info__thumb">
										<?php endif; ?>
										<div class="wpss-service-info__details">
											<a href="<?php echo esc_url( $service->get_permalink() ); ?>" class="wpss-service-info__title">
												<?php echo esc_html( $service->title ); ?>
											</a>
											<span class="wpss-service-info__package"><?php echo esc_html( $order->package_name ); ?></span>
										</div>
									</div>
								<?php else : ?>
									<span class="wpss-service-info--deleted"><?php esc_html_e( 'Service no longer available', 'wp-sell-services' ); ?></span>
								<?php endif; ?>
							</td>
							<td class="wpss-table__col--status" data-label="<?php esc_attr_e( 'Status', 'wp-sell-services' ); ?>">
								<span class="wpss-badge wpss-badge--status-<?php echo esc_attr( $status_class ); ?>">
									<?php echo esc_html( $status_label ); ?>
								</span>
								<?php if ( $order->is_late() ) : ?>
									<span class="wpss-badge wpss-badge--late"><?php esc_html_e( 'Late', 'wp-sell-services' ); ?></span>
								<?php endif; ?>
							</td>
							<td class="wpss-table__col--total" data-label="<?php esc_attr_e( 'Total', 'wp-sell-services' ); ?>">
								<strong><?php echo esc_html( $order->get_formatted_total() ); ?></strong>
							</td>
							<td class="wpss-table__col--deadline" data-label="<?php esc_attr_e( 'Deadline', 'wp-sell-services' ); ?>">
								<?php if ( $order->delivery_deadline ) : ?>
									<div class="wpss-deadline">
										<span class="wpss-deadline__date">
											<?php echo esc_html( wp_date( get_option( 'date_format' ), $order->delivery_deadline->getTimestamp() ) ); ?>
										</span>
										<?php
										$time_remaining = $order->get_time_remaining();
										if ( $time_remaining ) :
											$is_urgent = $time_remaining < DAY_IN_SECONDS;
											?>
											<span class="wpss-deadline__remaining <?php echo $is_urgent ? 'wpss-deadline__remaining--urgent' : ''; ?>">
												<?php
												/* translators: %s: time remaining */
												printf( esc_html__( '%s left', 'wp-sell-services' ), esc_html( human_time_diff( time(), $order->delivery_deadline->getTimestamp() ) ) );
												?>
											</span>
										<?php endif; ?>
									</div>
								<?php else : ?>
									<span class="wpss-deadline--none">&mdash;</span>
								<?php endif; ?>
							</td>
							<td class="wpss-table__col--actions" data-label="<?php esc_attr_e( 'Actions', 'wp-sell-services' ); ?>">
								<div class="wpss-actions">
									<a href="<?php echo esc_url( wpss_get_order_url( $order->id ) ); ?>" class="wpss-btn wpss-btn--sm wpss-btn--secondary">
										<?php esc_html_e( 'View', 'wp-sell-services' ); ?>
									</a>
									<?php if ( \WPSellServices\Models\ServiceOrder::STATUS_PENDING_REQUIREMENTS === $order->status ) : ?>
										<a href="<?php echo esc_url( wpss_get_order_requirements_url( $order->id ) ); ?>" class="wpss-btn wpss-btn--sm wpss-btn--primary">
											<?php esc_html_e( 'Submit Requirements', 'wp-sell-services' ); ?>
										</a>
									<?php endif; ?>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>

<?php
/**
 * Fires after the service orders content.
 *
 * @since 1.1.0
 *
 * @param int $user_id Current user ID.
 */
do_action( 'wpss_service_orders_after', $user_id );
?>
