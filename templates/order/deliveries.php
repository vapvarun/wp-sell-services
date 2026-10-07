<?php
/**
 * Order deliveries: each delivery with its message and files, or why there is none.
 *
 * One partial for the buyer's and seller's order view and the admin order
 * screen (Basecamp 10337161480). Read-only - accepting or asking for a
 * revision are the order view's own actions. The order view shows the empty
 * state only while approval is pending; the admin screen always shows the
 * section, so an empty one is never mistaken for a missing one.
 *
 * Override: {theme}/wp-sell-services/order/deliveries.php
 *
 * @package WPSellServices
 * @since   1.8.0
 *
 * @var \WPSellServices\Models\ServiceOrder $wpss_order      The order.
 * @var object[]                            $wpss_deliveries Rows from wpss_deliveries, newest first.
 * @var string                              $wpss_viewer     'buyer', 'vendor' or 'admin'.
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $wpss_order ) ) {
	return;
}

$wpss_deliveries = $wpss_deliveries ?? array();
$wpss_viewer     = $wpss_viewer ?? 'buyer';
?>
<?php if ( empty( $wpss_deliveries ) && ( 'pending_approval' === $wpss_order->status || 'admin' === $wpss_viewer ) ) : ?>
	<section class="wpss-order-section">
		<div class="wpss-order-section__header">
			<h2 class="wpss-order-section__title">
				<i data-lucide="upload" class="wpss-icon" aria-hidden="true"></i>
				<?php esc_html_e( 'Deliveries', 'wp-sell-services' ); ?>
			</h2>
		</div>
		<div class="wpss-order-section__body">
			<div class="wpss-empty-state wpss-empty-state--compact">
				<h3><?php esc_html_e( 'Nothing delivered yet', 'wp-sell-services' ); ?></h3>
				<p>
					<?php
					if ( 'pending_approval' !== $wpss_order->status ) {
						// Admin only: the section always shows there, so "none yet" is said.
						$wpss_empty_text = __( 'The seller has not submitted a delivery for this order.', 'wp-sell-services' );
					} elseif ( 'buyer' === $wpss_viewer ) {
						$wpss_empty_text = __( 'This order is marked for your approval, but the seller has not attached a delivery. Message them below, or open a dispute if the work does not arrive.', 'wp-sell-services' );
					} elseif ( 'admin' === $wpss_viewer ) {
						$wpss_empty_text = __( 'This order is waiting for approval with no delivery attached, so the buyer cannot accept it. Move it back to In Progress so the seller can deliver.', 'wp-sell-services' );
					} else {
						$wpss_empty_text = __( 'This order is waiting for approval with no delivery attached, so the buyer cannot accept it. Ask the site admin to move it back to In Progress so you can deliver.', 'wp-sell-services' );
					}
					echo esc_html( $wpss_empty_text );
					?>
				</p>
			</div>
		</div>
	</section>
<?php elseif ( ! empty( $wpss_deliveries ) ) : ?>
	<section class="wpss-order-section">
		<div class="wpss-order-section__header">
			<h2 class="wpss-order-section__title">
				<i data-lucide="upload" class="wpss-icon" aria-hidden="true"></i>
				<?php esc_html_e( 'Deliveries', 'wp-sell-services' ); ?>
			</h2>
		</div>
		<div class="wpss-order-section__body">
			<?php foreach ( $wpss_deliveries as $delivery ) : ?>
				<div class="wpss-delivery-item">
					<div class="wpss-delivery-item__header">
						<span class="wpss-delivery-item__date">
							<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $delivery->created_at ) ) ); ?>
						</span>
						<span class="<?php echo esc_attr( wpss_status_class( $delivery->status ) ); ?>">
							<?php echo esc_html( wpss_get_order_status_label( (string) $delivery->status ) ); ?>
						</span>
					</div>
					<div class="wpss-delivery-item__content">
						<?php echo wp_kses_post( wpautop( $delivery->message ) ); ?>
					</div>
					<?php
					$files = maybe_unserialize( $delivery->attachments );
					if ( is_string( $files ) ) {
						$decoded = json_decode( $files, true );
						$files   = is_array( $decoded ) ? $decoded : array();
					}
					if ( ! empty( $files ) && is_array( $files ) ) :
						?>
						<div class="wpss-delivery-item__files">
							<?php foreach ( $files as $file ) : ?>
								<?php
								// Three formats now: a 1.7.0 record addressed by id, a
								// pre-1.7.0 record carrying a stored public URL, or a bare
								// attachment ID from further back still. Only the first is
								// permission-checked; the older two are already public and
								// keep working, because breaking a delivered file to tighten
								// history would punish the buyer for our bug.
								if ( is_array( $file ) ) {
									$file['order_id'] = $file['order_id'] ?? $wpss_order->id;

									$att_id    = $file['id'] ?? 0;
									$file_url  = wpss_get_order_file_url( $file );
									$file_name = wpss_format_attachment_name( (string) ( $file['name'] ?? get_the_title( $att_id ) ) );

									if ( '' === $file_url ) {
										$file_url = wp_get_attachment_url( $att_id );
									}
								} else {
									$file_url  = wp_get_attachment_url( (int) $file );
									$file_name = get_the_title( (int) $file );
								}
								if ( ! $file_url ) {
									continue;
								}
								?>
								<a href="<?php echo esc_url( $file_url ); ?>" class="wpss-file-link" target="_blank" download>
									<i data-lucide="download" class="wpss-icon" aria-hidden="true"></i>
									<?php echo esc_html( $file_name ); ?>
								</a>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	</section>
<?php endif; ?>
