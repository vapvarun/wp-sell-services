<?php
/**
 * Service Orders - My Account Template
 *
 * @package WPSellServices\Templates
 * @since   1.0.0
 *
 * @var array $orders  Service orders.
 * @var int   $user_id Current user ID.
 */

defined( 'ABSPATH' ) || exit;

$statuses = \WPSellServices\Models\ServiceOrder::get_statuses();
?>

<div class="wpss-service-orders">
	<?php if ( empty( $orders ) ) : ?>
		<div class="wpss-no-orders">
			<p><?php esc_html_e( 'You have not placed any service orders yet.', 'wp-sell-services' ); ?></p>
			<a href="<?php echo esc_url( home_url( '/services/' ) ); ?>" class="button">
				<?php esc_html_e( 'Browse Services', 'wp-sell-services' ); ?>
			</a>
		</div>
	<?php else : ?>
		<table class="wpss-orders-table shop_table shop_table_responsive">
			<thead>
				<tr>
					<th class="wpss-order-number"><?php esc_html_e( 'Order', 'wp-sell-services' ); ?></th>
					<th class="wpss-order-service"><?php esc_html_e( 'Service', 'wp-sell-services' ); ?></th>
					<th class="wpss-order-status"><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></th>
					<th class="wpss-order-total"><?php esc_html_e( 'Total', 'wp-sell-services' ); ?></th>
					<th class="wpss-order-deadline"><?php esc_html_e( 'Deadline', 'wp-sell-services' ); ?></th>
					<th class="wpss-order-actions"><?php esc_html_e( 'Actions', 'wp-sell-services' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $orders as $order_data ) : ?>
					<?php
					$order = \WPSellServices\Models\ServiceOrder::from_db( $order_data );
					$service = $order->get_service();
					$status_label = $statuses[ $order->status ] ?? $order->status;
					?>
					<tr class="wpss-order-row wpss-order-status-<?php echo esc_attr( $order->status ); ?>">
						<td class="wpss-order-number" data-title="<?php esc_attr_e( 'Order', 'wp-sell-services' ); ?>">
							<a href="<?php echo esc_url( home_url( '/service-order/' . $order->id . '/' ) ); ?>">
								#<?php echo esc_html( $order->order_number ); ?>
							</a>
							<br>
							<small><?php echo esc_html( wp_date( get_option( 'date_format' ), $order->created_at->getTimestamp() ) ); ?></small>
						</td>
						<td class="wpss-order-service" data-title="<?php esc_attr_e( 'Service', 'wp-sell-services' ); ?>">
							<?php if ( $service ) : ?>
								<a href="<?php echo esc_url( $service->get_permalink() ); ?>">
									<?php echo esc_html( $service->title ); ?>
								</a>
							<?php else : ?>
								<span class="wpss-deleted-service"><?php esc_html_e( 'Service no longer available', 'wp-sell-services' ); ?></span>
							<?php endif; ?>
						</td>
						<td class="wpss-order-status" data-title="<?php esc_attr_e( 'Status', 'wp-sell-services' ); ?>">
							<span class="wpss-status-badge wpss-status-<?php echo esc_attr( $order->status ); ?>">
								<?php echo esc_html( $status_label ); ?>
							</span>
							<?php if ( $order->is_late() ) : ?>
								<span class="wpss-late-badge"><?php esc_html_e( 'Late', 'wp-sell-services' ); ?></span>
							<?php endif; ?>
						</td>
						<td class="wpss-order-total" data-title="<?php esc_attr_e( 'Total', 'wp-sell-services' ); ?>">
							<?php echo esc_html( $order->get_formatted_total() ); ?>
						</td>
						<td class="wpss-order-deadline" data-title="<?php esc_attr_e( 'Deadline', 'wp-sell-services' ); ?>">
							<?php if ( $order->delivery_deadline ) : ?>
								<?php echo esc_html( wp_date( get_option( 'date_format' ), $order->delivery_deadline->getTimestamp() ) ); ?>
								<?php
								$time_remaining = $order->get_time_remaining();
								if ( $time_remaining ) :
									?>
									<br>
									<small class="wpss-time-remaining">
										<?php
										/* translators: %s: time remaining */
										printf( esc_html__( '%s left', 'wp-sell-services' ), human_time_diff( time(), $order->delivery_deadline->getTimestamp() ) );
										?>
									</small>
								<?php endif; ?>
							<?php else : ?>
								<span class="wpss-no-deadline">—</span>
							<?php endif; ?>
						</td>
						<td class="wpss-order-actions" data-title="<?php esc_attr_e( 'Actions', 'wp-sell-services' ); ?>">
							<a href="<?php echo esc_url( home_url( '/service-order/' . $order->id . '/' ) ); ?>" class="button wpss-button-small">
								<?php esc_html_e( 'View', 'wp-sell-services' ); ?>
							</a>
							<?php if ( \WPSellServices\Models\ServiceOrder::STATUS_PENDING_REQUIREMENTS === $order->status ) : ?>
								<a href="<?php echo esc_url( home_url( '/service-order/' . $order->id . '/requirements/' ) ); ?>" class="button wpss-button-small wpss-button-primary">
									<?php esc_html_e( 'Submit Requirements', 'wp-sell-services' ); ?>
								</a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>

<style>
.wpss-service-orders {
	margin-top: 20px;
}

.wpss-orders-table {
	width: 100%;
	border-collapse: collapse;
}

.wpss-orders-table th,
.wpss-orders-table td {
	padding: 12px;
	text-align: left;
	border-bottom: 1px solid #e5e5e5;
}

.wpss-status-badge {
	display: inline-block;
	padding: 4px 8px;
	border-radius: 4px;
	font-size: 12px;
	font-weight: 500;
}

.wpss-status-pending_payment { background: #ffeaa7; color: #856404; }
.wpss-status-pending_requirements { background: #74b9ff; color: #0056b3; }
.wpss-status-in_progress { background: #81ecec; color: #00695c; }
.wpss-status-pending_approval { background: #dfe6e9; color: #2d3436; }
.wpss-status-completed { background: #00b894; color: #fff; }
.wpss-status-cancelled { background: #d63031; color: #fff; }
.wpss-status-disputed { background: #e17055; color: #fff; }

.wpss-late-badge {
	display: inline-block;
	padding: 2px 6px;
	background: #d63031;
	color: #fff;
	border-radius: 4px;
	font-size: 10px;
	margin-left: 4px;
}

.wpss-button-small {
	padding: 6px 12px !important;
	font-size: 12px !important;
}

.wpss-button-primary {
	background: #0073aa !important;
	color: #fff !important;
}

.wpss-no-orders {
	text-align: center;
	padding: 40px 20px;
}
</style>
