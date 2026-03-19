<?php
/**
 * Dashboard Section: Buyer Requests
 *
 * @package WPSellServices\Templates
 * @since   1.1.0
 *
 * @var int            $user_id        Current user ID.
 * @var VendorService  $vendor_service Vendor service instance.
 * @var bool           $is_vendor      Whether user is a vendor.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fires before the requests dashboard section content.
 *
 * @since 1.1.0
 *
 * @param string $section_name Section identifier ('requests').
 * @param int    $user_id      Current user ID.
 */
do_action( 'wpss_dashboard_section_before', 'requests', $user_id );

// Get user's buyer requests.
$args = array(
	'post_type'      => 'wpss_request',
	'author'         => $user_id,
	'posts_per_page' => 20,
	'post_status'    => array( 'publish', 'draft', 'pending' ),
	'orderby'        => 'date',
	'order'          => 'DESC',
);

$requests = new WP_Query( $args );

// Get stats.
$active_count = count(
	get_posts(
		array(
			'post_type'   => 'wpss_request',
			'author'      => $user_id,
			'post_status' => 'publish',
			'numberposts' => -1,
			'fields'      => 'ids',
		)
	)
);
?>

<div class="wpss-section wpss-section--requests">
	<div class="wpss-stats-grid">
		<div class="wpss-stat-card">
			<span class="wpss-stat-card__value"><?php echo esc_html( $active_count ); ?></span>
			<span class="wpss-stat-card__label"><?php esc_html_e( 'Active Requests', 'wp-sell-services' ); ?></span>
		</div>
	</div>

	<?php if ( ! $requests->have_posts() ) : ?>
		<div class="wpss-empty-state">
			<div class="wpss-empty-state__icon">
				<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="m3 11 18-5v12L3 13v-2z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>
			</div>
			<h3><?php esc_html_e( 'No requests yet', 'wp-sell-services' ); ?></h3>
			<p><?php esc_html_e( "Can't find the right service? Post a request and let sellers come to you.", 'wp-sell-services' ); ?></p>
			<a href="<?php echo esc_url( add_query_arg( 'section', 'create-request', wpss_get_page_url( 'dashboard' ) ?: get_permalink() ) ); ?>" class="wpss-btn wpss-btn--primary">
				<?php esc_html_e( 'Post a Request', 'wp-sell-services' ); ?>
			</a>
		</div>
	<?php else : ?>
		<div class="wpss-requests-list">
			<?php
			while ( $requests->have_posts() ) :
				$requests->the_post();
				$request_id  = get_the_ID();
				$budget      = get_post_meta( $request_id, '_wpss_budget', true );
				$deadline    = get_post_meta( $request_id, '_wpss_deadline', true );
				// Query actual proposal count from DB instead of potentially stale meta.
				global $wpdb;
				$offers = (int) $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}wpss_proposals WHERE request_id = %d",
					$request_id
				) );
				$item_status = get_post_status();
				?>
				<div class="wpss-request-card">
					<div class="wpss-request-card__main">
						<h4 class="wpss-request-card__title"><?php the_title(); ?></h4>
						<p class="wpss-request-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 20 ) ); ?></p>
						<div class="wpss-request-card__meta">
							<?php if ( $budget ) : ?>
								<span>
									<?php
									printf(
										/* translators: %s: budget amount */
										esc_html__( 'Budget: %s', 'wp-sell-services' ),
										esc_html( wpss_format_price( $budget ) )
									);
									?>
								</span>
							<?php endif; ?>
							<?php if ( $deadline ) : ?>
								<span class="wpss-request-card__sep">&bull;</span>
								<span>
									<?php
									printf(
										/* translators: %s: deadline date */
										esc_html__( 'Deadline: %s', 'wp-sell-services' ),
										esc_html( wp_date( get_option( 'date_format' ), strtotime( $deadline ) ) )
									);
									?>
								</span>
							<?php endif; ?>
							<span class="wpss-request-card__sep">&bull;</span>
							<span>
								<?php
								printf(
									/* translators: %d: number of offers */
									esc_html( _n( '%d offer', '%d offers', $offers, 'wp-sell-services' ) ),
									esc_html( $offers )
								);
								?>
							</span>
						</div>
					</div>
					<div class="wpss-request-card__actions">
						<span class="wpss-status wpss-status--<?php echo esc_attr( $item_status ); ?>">
							<?php
							$status_obj = get_post_status_object( $item_status );
							echo esc_html( $status_obj ? $status_obj->label : ucfirst( $item_status ) );
							?>
						</span>
						<a href="<?php the_permalink(); ?>" class="wpss-btn wpss-btn--outline wpss-btn--sm">
							<?php esc_html_e( 'View Offers', 'wp-sell-services' ); ?>
						</a>
						<?php if ( 'publish' === $item_status ) : ?>
							<button type="button" class="wpss-btn wpss-btn--link wpss-btn--sm wpss-close-request" data-request-id="<?php echo esc_attr( $request_id ); ?>">
								<?php esc_html_e( 'Close', 'wp-sell-services' ); ?>
							</button>
						<?php elseif ( 'draft' === $item_status ) : ?>
							<button type="button" class="wpss-btn wpss-btn--link wpss-btn--sm wpss-reopen-request" data-request-id="<?php echo esc_attr( $request_id ); ?>">
								<?php esc_html_e( 'Reopen', 'wp-sell-services' ); ?>
							</button>
						<?php endif; ?>
						<a href="<?php echo esc_url( add_query_arg( array( 'section' => 'edit-request', 'request_id' => $request_id ), wpss_get_page_url( 'dashboard' ) ?: get_permalink() ) ); ?>" class="wpss-btn wpss-btn--link wpss-btn--sm">
							<?php esc_html_e( 'Edit', 'wp-sell-services' ); ?>
						</a>
						<button type="button" class="wpss-btn wpss-btn--link wpss-btn--sm wpss-btn--danger wpss-delete-request" data-request-id="<?php echo esc_attr( $request_id ); ?>">
							<?php esc_html_e( 'Delete', 'wp-sell-services' ); ?>
						</button>
					</div>
				</div>
			<?php endwhile; ?>
			<?php wp_reset_postdata(); ?>
		</div>
	<?php endif; ?>
</div>

<?php
/**
 * Fires after the requests dashboard section content.
 *
 * @since 1.1.0
 *
 * @param string $section_name Section identifier ('requests').
 * @param int    $user_id      Current user ID.
 */
do_action( 'wpss_dashboard_section_after', 'requests', $user_id );
?>
