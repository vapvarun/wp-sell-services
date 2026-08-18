<?php
/**
 * Dashboard Section: Disputes (buyer + vendor)
 *
 * Gives members an on-site surface to see and inspect the disputes they are a
 * party to. Previously the dispute backend (REST + services) existed but had no
 * member-facing list/detail view anywhere — only a modal to OPEN a dispute.
 *
 * @package WPSellServices\Templates
 * @since   1.2.2
 *
 * @var int           $user_id        Current user ID.
 * @var VendorService $vendor_service Vendor service instance.
 * @var bool          $is_vendor      Whether user is a vendor.
 */

use WPSellServices\Models\Dispute;
use WPSellServices\Services\DisputeService;
use WPSellServices\Services\DisputeWorkflowManager;

defined( 'ABSPATH' ) || exit;

// Reason labels come from the model, which already owns the map. This template
// printed the raw column instead, so members were shown machine slugs like
// "not_as_described" where the open-dispute form offers "Not as described".
$wpss_dispute_reasons = Dispute::get_reasons();

do_action( 'wpss_dashboard_section_before', 'disputes', $user_id );

$dispute_service = new DisputeService();
$statuses        = DisputeService::get_statuses();

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display; access controlled by dispute/order ownership below.
$view_dispute_id = isset( $_GET['dispute'] ) ? absint( $_GET['dispute'] ) : 0;

$section_base_url = remove_query_arg( array( 'dispute' ) );

/**
 * Detail view — a single dispute the current user is a party to.
 */
if ( $view_dispute_id ) {
	$dispute       = $dispute_service->get( $view_dispute_id );
	$dispute_order = $dispute ? wpss_get_order( (int) $dispute->order_id ) : null;

	$is_party = $dispute_order && ( (int) $dispute_order->customer_id === $user_id || (int) $dispute_order->vendor_id === $user_id );

	if ( ! $dispute || ! $is_party ) {
		?>
		<div class="wpss-dashboard__empty">
			<p><?php esc_html_e( 'Dispute not found, or you do not have access to it.', 'wp-sell-services' ); ?></p>
			<a class="wpss-btn wpss-btn--secondary" href="<?php echo esc_url( $section_base_url ); ?>">
				<?php esc_html_e( 'Back to disputes', 'wp-sell-services' ); ?>
			</a>
		</div>
		<?php
		return;
	}

	$status_key = (string) $dispute->status;
	$timeline   = ( new DisputeWorkflowManager() )->get_timeline( (int) $dispute->id );
	?>
	<div class="wpss-section wpss-section--disputes wpss-card wpss-disputes wpss-dispute-detail">
		<p class="wpss-dispute-detail__back">
			<a href="<?php echo esc_url( $section_base_url ); ?>">&larr; <?php esc_html_e( 'All disputes', 'wp-sell-services' ); ?></a>
		</p>

		<div class="wpss-dispute-detail__head">
			<h2>
				<?php
				printf(
					/* translators: %s: order number */
					esc_html__( 'Dispute for order %s', 'wp-sell-services' ),
					esc_html( $dispute_order->order_number ?? ( '#' . (int) $dispute->order_id ) )
				);
				?>
			</h2>
			<span class="wpss-status-badge wpss-status-<?php echo esc_attr( $status_key ); ?>">
				<?php echo esc_html( $statuses[ $status_key ] ?? $status_key ); ?>
			</span>
		</div>

		<?php if ( ! empty( $dispute->reason ) || ! empty( $dispute->description ) ) : ?>
			<div class="wpss-dispute-detail__reason">
				<h3><?php esc_html_e( 'Reason', 'wp-sell-services' ); ?></h3>
				<?php if ( ! empty( $dispute->reason ) ) : ?>
					<p class="wpss-dispute-detail__reason-label"><strong><?php echo esc_html( $wpss_dispute_reasons[ $dispute->reason ] ?? $dispute->reason ); ?></strong></p>
				<?php endif; ?>
				<?php if ( ! empty( $dispute->description ) ) : ?>
					<p><?php echo esc_html( $dispute->description ); ?></p>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $timeline ) ) : ?>
			<div class="wpss-dispute-detail__timeline">
				<h3><?php esc_html_e( 'Activity', 'wp-sell-services' ); ?></h3>
				<ul class="wpss-dispute-timeline">
					<?php
					foreach ( $timeline as $entry ) :
						$entry_user  = ! empty( $entry['user_id'] ) ? get_userdata( (int) $entry['user_id'] ) : null;
						$entry_name  = $entry_user ? $entry_user->display_name : __( 'System', 'wp-sell-services' );
						$entry_when  = ! empty( $entry['created_at'] ) ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $entry['created_at'] ) : '';
						$entry_text  = (string) ( $entry['content'] ?? '' );
						$entry_class = 'wpss-dispute-timeline__item wpss-dispute-timeline__item--' . sanitize_html_class( (string) ( $entry['type'] ?? 'event' ) );
						?>
						<li class="<?php echo esc_attr( $entry_class ); ?>">
							<span class="wpss-dispute-timeline__meta">
								<strong><?php echo esc_html( $entry_name ); ?></strong>
								<?php if ( $entry_when ) : ?>
									<time><?php echo esc_html( $entry_when ); ?></time>
								<?php endif; ?>
							</span>
							<?php if ( '' !== $entry_text ) : ?>
								<span class="wpss-dispute-timeline__text"><?php echo esc_html( $entry_text ); ?></span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<?php
		// Messages & evidence thread. The AJAX write path
		// (wpss_add_dispute_evidence) and DisputeService::add_evidence() already
		// existed, but no member-facing surface ever rendered the thread or a
		// reply form — a party could OPEN a dispute and then never respond to it.
		// This wires the existing backend to the dashboard.
		$evidence_items   = $dispute_service->get_evidence( (int) $dispute->id );
		$can_add_evidence = ! in_array( $status_key, array( 'resolved', 'closed' ), true );
		?>
		<div class="wpss-dispute-detail__evidence">
			<h3><?php esc_html_e( 'Messages &amp; evidence', 'wp-sell-services' ); ?></h3>

			<div class="wpss-evidence-thread" id="wpss-evidence-thread">
				<?php if ( empty( $evidence_items ) ) : ?>
					<p class="wpss-evidence-empty"><?php esc_html_e( 'No messages yet. Add one below to make your case to the reviewer and the other party.', 'wp-sell-services' ); ?></p>
				<?php else : ?>
					<?php
					foreach ( $evidence_items as $item ) :
						$ev_user_id = (int) ( $item['user_id'] ?? 0 );
						$ev_user    = $ev_user_id ? get_userdata( $ev_user_id ) : null;
						$ev_name    = $ev_user ? $ev_user->display_name : __( 'System', 'wp-sell-services' );
						$ev_own     = $ev_user_id === $user_id;
						$ev_type    = (string) ( $item['type'] ?? 'text' );
						$ev_content = (string) ( $item['content'] ?? '' );
						$ev_desc    = (string) ( $item['description'] ?? '' );
						$ev_when    = ! empty( $item['created_at'] ) ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $item['created_at'] ) : '';
						?>
						<div class="wpss-evidence-item <?php echo $ev_own ? 'wpss-evidence-own' : 'wpss-evidence-other'; ?>">
							<div class="wpss-evidence-bubble">
								<span class="wpss-evidence-author"><strong><?php echo esc_html( $ev_name ); ?></strong></span>
								<div class="wpss-evidence-content">
									<?php if ( 'text' === $ev_type && '' !== $ev_content ) : ?>
										<div class="wpss-evidence-text"><?php echo wp_kses_post( nl2br( esc_html( $ev_content ) ) ); ?></div>
									<?php endif; ?>
									<?php if ( '' !== $ev_desc && 'text' !== $ev_type ) : ?>
										<div class="wpss-evidence-text"><?php echo wp_kses_post( nl2br( esc_html( $ev_desc ) ) ); ?></div>
									<?php endif; ?>
									<?php if ( 'image' === $ev_type && '' !== $ev_content ) : ?>
										<div class="wpss-evidence-image">
											<a href="<?php echo esc_url( $ev_content ); ?>" target="_blank" rel="noopener noreferrer">
												<img src="<?php echo esc_url( $ev_content ); ?>" alt="<?php esc_attr_e( 'Evidence image', 'wp-sell-services' ); ?>">
											</a>
										</div>
									<?php elseif ( in_array( $ev_type, array( 'file', 'link' ), true ) && '' !== $ev_content ) : ?>
										<div class="wpss-evidence-file">
											<a href="<?php echo esc_url( $ev_content ); ?>" target="_blank" rel="noopener noreferrer" class="wpss-file-link">
												<i data-lucide="file" class="wpss-icon" aria-hidden="true"></i>
												<span><?php echo esc_html( basename( $ev_content ) ); ?></span>
											</a>
										</div>
									<?php endif; ?>
								</div>
								<?php if ( $ev_when ) : ?>
									<span class="wpss-evidence-time"><?php echo esc_html( $ev_when ); ?></span>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>

			<?php if ( $can_add_evidence ) : ?>
				<form id="wpss-add-evidence-form" class="wpss-add-evidence-form" enctype="multipart/form-data">
					<?php wp_nonce_field( 'wpss_add_evidence', 'nonce' ); ?>
					<input type="hidden" name="dispute_id" value="<?php echo esc_attr( (int) $dispute->id ); ?>">
					<label class="wpss-form-label" for="wpss-evidence-message">
						<?php esc_html_e( 'Add a message or evidence', 'wp-sell-services' ); ?>
					</label>
					<textarea id="wpss-evidence-message" name="description" class="wpss-form-textarea" rows="3"
						placeholder="<?php esc_attr_e( 'Explain your side, or add context for the reviewer…', 'wp-sell-services' ); ?>"></textarea>
					<div class="wpss-add-evidence-form__row">
						<label class="wpss-btn wpss-btn--secondary wpss-btn--sm wpss-evidence-attach">
							<i data-lucide="paperclip" class="wpss-icon" aria-hidden="true"></i>
							<span><?php esc_html_e( 'Attach file', 'wp-sell-services' ); ?></span>
							<input type="file" name="evidence_file" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.zip,.txt" hidden>
						</label>
						<span class="wpss-evidence-filename" aria-live="polite"></span>
						<button type="submit" class="wpss-btn wpss-btn--primary wpss-btn--sm">
							<?php esc_html_e( 'Send', 'wp-sell-services' ); ?>
						</button>
					</div>
				</form>
			<?php else : ?>
				<p class="wpss-evidence-locked"><?php esc_html_e( 'This dispute is closed. No further messages can be added.', 'wp-sell-services' ); ?></p>
			<?php endif; ?>
		</div>

		<p class="wpss-dispute-detail__actions">
			<?php
			$view_order_url = add_query_arg(
				array(
					'section'  => 'orders',
					'order_id' => (int) $dispute->order_id,
				),
				$section_base_url
			);
			?>
			<a class="wpss-btn wpss-btn--secondary" href="<?php echo esc_url( $view_order_url ); ?>">
				<?php esc_html_e( 'View order', 'wp-sell-services' ); ?>
			</a>
		</p>
	</div>
	<?php
	return;
}

/**
 * List view — every dispute the current user is a party to.
 */
$disputes = $dispute_service->get_by_user( $user_id, array( 'limit' => 50 ) );
?>
<div class="wpss-section wpss-section--disputes wpss-card wpss-disputes">
	<?php
	// No <h2>Disputes</h2> here. The dashboard shell already renders the
	// section title in its header; this was the ONLY section of the fourteen
	// that also printed its own, so the page showed "Disputes" twice. (It was
	// hidden until now because the title map was missing 'disputes', so the
	// header said "Dashboard" and this looked like the real title.)
	?>

	<?php if ( empty( $disputes ) ) : ?>
		<div class="wpss-dashboard__empty">
			<p><?php esc_html_e( 'You have no disputes. Disputes you open on an order will appear here.', 'wp-sell-services' ); ?></p>
		</div>
	<?php else : ?>
		<table class="wpss-disputes-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Order', 'wp-sell-services' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Reason', 'wp-sell-services' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Opened', 'wp-sell-services' ); ?></th>
					<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'wp-sell-services' ); ?></span></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $disputes as $dispute ) :
					$status_key   = (string) $dispute->status;
					$order_number = $dispute->order_id ? ( '#' . (int) $dispute->order_id ) : '';
					$order_row    = wpss_get_order( (int) $dispute->order_id );
					if ( $order_row && ! empty( $order_row->order_number ) ) {
						$order_number = $order_row->order_number;
					}
					$detail_url = add_query_arg( 'dispute', (int) $dispute->id, $section_base_url );
					// $dispute is a hydrated Dispute model, so created_at is a
					// DateTimeImmutable - mysql2date() takes a STRING and fataled
					// with "date_create(): Argument #1 must be of type string",
					// white-screening this whole section. Same accessor the admin
					// order screen uses.
					//
					// The two mysql2date() calls further up this template are fine:
					// they read $entry['created_at'] / $item['created_at'] from
					// plain arrays, which are still strings.
					$opened = $dispute->created_at
						? wp_date( get_option( 'date_format' ), $dispute->created_at->getTimestamp() )
						: '';
					?>
					<?php
					// data-title feeds the stacked mobile layout: under 782px the
					// stylesheet hides <thead> and renders `content: attr(data-title)`
					// as each cell's label. Without it the labels were empty and
					// `justify-content: space-between` pushed every value hard right
					// against a blank gutter. Nobody had seen it, because this whole
					// section 500'd before the date fix above.
					?>
					<tr>
						<td data-title="<?php esc_attr_e( 'Order', 'wp-sell-services' ); ?>"><?php echo esc_html( $order_number ); ?></td>
						<?php $wpss_reason_key = (string) ( $dispute->reason ?? '' ); ?>
						<td data-title="<?php esc_attr_e( 'Reason', 'wp-sell-services' ); ?>"><?php echo esc_html( $wpss_dispute_reasons[ $wpss_reason_key ] ?? $wpss_reason_key ); ?></td>
						<td data-title="<?php esc_attr_e( 'Status', 'wp-sell-services' ); ?>">
							<span class="wpss-status-badge wpss-status-<?php echo esc_attr( $status_key ); ?>">
								<?php echo esc_html( $statuses[ $status_key ] ?? $status_key ); ?>
							</span>
						</td>
						<td data-title="<?php esc_attr_e( 'Opened', 'wp-sell-services' ); ?>"><?php echo esc_html( $opened ); ?></td>
						<td>
							<a class="wpss-btn wpss-btn--sm wpss-btn--secondary" href="<?php echo esc_url( $detail_url ); ?>">
								<?php esc_html_e( 'View', 'wp-sell-services' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
