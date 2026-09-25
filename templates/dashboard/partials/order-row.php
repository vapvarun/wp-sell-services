<?php
/**
 * One order row in the buyer's My Orders or the seller's Sales Orders list.
 *
 * Both lists rendered their own copy of this card and only the seller's
 * showed any money; a buyer with 13 orders of the same service saw 13
 * identical rows (Basecamp 10337217098). Each row now names the order, its
 * amount, when it is due (late when overdue, wpss_is_order_late()) and what
 * the viewer has to do next.
 *
 * @package WPSellServices
 * @since   1.8.0
 *
 * @var object $order_item    Order row from OrderRepository.
 * @var string $wpss_side     'buyer' (My Orders) or 'seller' (Sales Orders).
 * @var array  $status_labels wpss_get_order_status_labels().
 */

defined( 'ABSPATH' ) || exit;

$wpss_is_seller = 'seller' === $wpss_side;
$order_platform = $order_item->platform ?? '';
$is_tip         = \WPSellServices\Services\TippingService::ORDER_TYPE === $order_platform;
$is_extension   = \WPSellServices\Services\ExtensionOrderService::ORDER_TYPE === $order_platform;
$is_milestone   = \WPSellServices\Services\MilestoneService::ORDER_TYPE === $order_platform;
$is_sub_order   = $is_tip || $is_extension || $is_milestone;
$service        = $order_item->service_id ? get_post( $order_item->service_id ) : null;
$other_party    = get_userdata( $wpss_is_seller ? $order_item->customer_id : $order_item->vendor_id );
$request_post   = ( ! $service && 'request' === $order_platform && $order_item->platform_order_id ) ? get_post( $order_item->platform_order_id ) : null;

if ( $is_sub_order ) {
	// Tip / extension / milestone rows reference the parent service order via
	// platform_order_id; fall back gracefully when the parent has been deleted.
	$parent_order = $order_item->platform_order_id ? wpss_get_order( (int) $order_item->platform_order_id ) : null;
	$parent_title = '';
	if ( $parent_order ) {
		$parent_service = $parent_order->service_id ? get_post( $parent_order->service_id ) : null;
		$parent_title   = $parent_service ? $parent_service->post_title : $parent_order->order_number;
	}
	if ( $is_tip ) {
		$order_title = $parent_title
			? sprintf( /* translators: %s: original service / order title */ __( 'Tip for %s', 'wp-sell-services' ), $parent_title )
			: __( 'Tip', 'wp-sell-services' );
	} elseif ( $is_extension ) {
		$order_title = $parent_title
			? sprintf( /* translators: %s: original service / order title */ __( 'Extension for %s', 'wp-sell-services' ), $parent_title )
			: __( 'Extension', 'wp-sell-services' );
	} else {
		// Milestone: prefer the phase title from the meta JSON, fall back to
		// the parent title if the meta was dropped.
		$ms_meta        = is_string( $order_item->meta ?? '' ) && '' !== $order_item->meta ? json_decode( $order_item->meta, true ) : array();
		$ms_phase_title = is_array( $ms_meta ) && ! empty( $ms_meta['title'] ) ? (string) $ms_meta['title'] : '';
		if ( '' !== $ms_phase_title && '' !== $parent_title ) {
			$order_title = sprintf( /* translators: 1: milestone phase title, 2: parent service title */ __( 'Milestone: %1$s (for %2$s)', 'wp-sell-services' ), $ms_phase_title, $parent_title );
		} elseif ( '' !== $ms_phase_title ) {
			$order_title = sprintf( /* translators: %s: milestone phase title */ __( 'Milestone: %s', 'wp-sell-services' ), $ms_phase_title );
		} elseif ( '' !== $parent_title ) {
			$order_title = sprintf( /* translators: %s: parent service title */ __( 'Milestone for %s', 'wp-sell-services' ), $parent_title );
		} else {
			$order_title = __( 'Milestone', 'wp-sell-services' );
		}
	}
} else {
	$order_title = $service ? $service->post_title : ( $request_post ? $request_post->post_title : __( 'Deleted Service', 'wp-sell-services' ) );
}

// What the viewer has to do next, if anything. The button carries it; with
// nothing to do the row just offers View.
$next_actions = $wpss_is_seller
	? array(
		'in_progress'        => __( 'Deliver', 'wp-sell-services' ),
		'late'               => __( 'Deliver', 'wp-sell-services' ),
		'revision_requested' => __( 'Deliver revision', 'wp-sell-services' ),
	)
	: array(
		'pending_payment'      => __( 'Complete payment', 'wp-sell-services' ),
		'pending_requirements' => __( 'Submit requirements', 'wp-sell-services' ),
		'pending_approval'     => __( 'Review delivery', 'wp-sell-services' ),
	);
$next_action  = $is_sub_order ? '' : ( $next_actions[ $order_item->status ] ?? '' );
$is_late      = wpss_is_order_late( $order_item );
// A due date means something only while the seller is working; before payment
// or after the work is done, the order date is what identifies the row.
$is_working = in_array( $order_item->status, array( 'in_progress', 'revision_requested', 'late' ), true );
?>
<div class="wpss-order-card<?php echo $is_tip ? ' wpss-order-card--tip' : ''; ?><?php echo $is_extension ? ' wpss-order-card--extension' : ''; ?><?php echo $is_milestone ? ' wpss-order-card--milestone' : ''; ?>">
	<div class="wpss-order-card__main">
		<?php if ( $is_tip || $is_extension || $is_milestone ) : ?>
			<div class="wpss-order-card__tip-icon<?php echo $is_extension ? ' wpss-order-card__extension-icon' : ''; ?><?php echo $is_milestone ? ' wpss-order-card__milestone-icon' : ''; ?>" aria-hidden="true">
				<i data-lucide="<?php echo esc_attr( $is_tip ? 'heart' : ( $is_extension ? 'clock' : 'flag' ) ); ?>" class="wpss-icon wpss-icon--lg" aria-hidden="true"></i>
			</div>
		<?php else : ?>
			<?php // Every service row gets a picture slot, so rows line up whether or not the service has an image. ?>
			<div class="wpss-order-card__image">
				<?php if ( $service && has_post_thumbnail( $service ) ) : ?>
					<?php echo get_the_post_thumbnail( $service, 'thumbnail' ); ?>
				<?php else : ?>
					<span class="wpss-order-card__placeholder"><i data-lucide="image" class="wpss-icon" aria-hidden="true"></i></span>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<div class="wpss-order-card__info">
			<h4 class="wpss-order-card__title">
				<?php if ( $is_sub_order ) : ?>
					<span class="wpss-badge wpss-badge--<?php echo esc_attr( $is_tip ? 'tip' : ( $is_extension ? 'extension' : 'milestone' ) ); ?>">
						<?php echo esc_html( $is_tip ? __( 'Tip', 'wp-sell-services' ) : ( $is_extension ? __( 'Extension', 'wp-sell-services' ) : __( 'Milestone', 'wp-sell-services' ) ) ); ?>
					</span>
					<?php echo esc_html( $order_title ); ?>
				<?php elseif ( $service ) : ?>
					<a href="<?php echo esc_url( get_permalink( $service ) ); ?>"><?php echo esc_html( $order_title ); ?></a>
				<?php else : ?>
					<?php echo esc_html( $order_title ); ?>
				<?php endif; ?>
			</h4>
			<p class="wpss-order-card__meta">
				<span class="wpss-order-card__number">#<?php echo esc_html( $order_item->order_number ); ?></span>
				<span class="wpss-order-card__sep">&bull;</span>
				<?php
				printf(
					esc_html( $wpss_is_seller ? /* translators: %s: buyer name */ __( 'Buyer: %s', 'wp-sell-services' ) : /* translators: %s: seller name */ __( 'by %s', 'wp-sell-services' ) ),
					esc_html( $other_party ? $other_party->display_name : __( 'Unknown', 'wp-sell-services' ) )
				);
				?>
				<span class="wpss-order-card__sep">&bull;</span>
				<?php if ( $wpss_is_seller ) : ?>
					<?php
					// The vendor's net take-home, counted the way the Earnings stat
					// counts it (wpss_get_order_revenue()): nothing until the buyer
					// pays, and a refund takes its share off.
					$row_money = wpss_get_order_revenue(
						(object) ( array( 'vendor_earnings' => $order_item->vendor_earnings ?? $order_item->total ) + (array) $order_item )
					);
					$row_gross = (float) $order_item->total;
					?>
					<?php if ( ! $row_money->counts ) : ?>
						<span class="wpss-order-card__amount">
							<?php
							echo esc_html(
								in_array( (string) $order_item->status, array( 'pending_payment', 'pending' ), true )
									/* translators: %s: amount the buyer will pay */
									? sprintf( __( 'Awaiting payment of %s', 'wp-sell-services' ), wpss_format_price( $row_gross, (string) $order_item->currency ) )
									: __( 'No earnings', 'wp-sell-services' )
							);
							?>
						</span>
					<?php else : ?>
						<span class="wpss-order-card__amount" title="<?php echo esc_attr( sprintf( /* translators: %s: gross amount the buyer paid */ __( 'Buyer paid %s (gross). You earn the net amount after platform fee.', 'wp-sell-services' ), wpss_format_price( $row_gross, (string) $order_item->currency ) ) ); ?>">
							<?php echo esc_html( wpss_format_price( $row_money->vendor_earnings, (string) $order_item->currency ) ); ?>
							<?php if ( abs( $row_gross - $row_money->vendor_earnings ) > 0.005 ) : ?>
								<small class="wpss-order-card__gross">
									<?php
									/* translators: %s: buyer-paid amount */
									printf( esc_html__( '(buyer paid %s)', 'wp-sell-services' ), esc_html( wpss_format_price( $row_gross, (string) $order_item->currency ) ) );
									?>
								</small>
							<?php endif; ?>
						</span>
					<?php endif; ?>
				<?php else : ?>
					<span class="wpss-order-card__amount"><?php echo esc_html( wpss_format_price( (float) $order_item->total, (string) $order_item->currency ) ); ?></span>
				<?php endif; ?>
				<?php if ( $is_working && ! empty( $order_item->delivery_deadline ) ) : ?>
					<span class="wpss-order-card__sep">&bull;</span>
					<span class="wpss-order-card__due">
						<?php
						printf(
							/* translators: %s: delivery due date */
							esc_html__( 'Due %s', 'wp-sell-services' ),
							esc_html( wp_date( get_option( 'date_format' ), strtotime( get_gmt_from_date( (string) $order_item->delivery_deadline ) . ' UTC' ) ) )
						);
						?>
					</span>
				<?php else : ?>
					<span class="wpss-order-card__sep">&bull;</span>
					<?php
					printf(
						/* translators: %s: order date */
						esc_html__( 'Ordered %s', 'wp-sell-services' ),
						esc_html( wp_date( get_option( 'date_format' ), strtotime( $order_item->created_at . ' UTC' ) ) )
					);
					?>
				<?php endif; ?>
			</p>
		</div>
	</div>
	<div class="wpss-order-card__actions">
		<?php if ( $is_late ) : ?>
			<span class="wpss-badge wpss-badge--danger"><?php esc_html_e( 'Late', 'wp-sell-services' ); ?></span>
		<?php endif; ?>
		<?php if ( 'late' !== $order_item->status ) : ?>
			<span class="wpss-status wpss-status--<?php echo esc_attr( sanitize_html_class( $order_item->status ) ); ?>">
				<?php echo esc_html( $status_labels[ $order_item->status ] ?? $order_item->status ); ?>
			</span>
		<?php endif; ?>
		<a href="<?php echo esc_url( wpss_get_order_url( (int) $order_item->id, $wpss_is_seller ? 'sales' : '' ) ); ?>" class="wpss-btn wpss-btn--sm <?php echo '' !== $next_action ? 'wpss-btn--primary' : 'wpss-btn--outline'; ?>">
			<?php echo esc_html( '' !== $next_action ? $next_action : __( 'View', 'wp-sell-services' ) ); ?>
		</a>
	</div>
</div>
