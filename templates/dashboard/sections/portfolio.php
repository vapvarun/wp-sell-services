<?php
/**
 * Dashboard Section: Portfolio
 *
 * @package WPSellServices\Templates
 * @since   1.1.0
 *
 * @var int            $user_id        Current user ID.
 * @var VendorService  $vendor_service Vendor service instance.
 * @var bool           $is_vendor      Whether user is a vendor.
 */

defined( 'ABSPATH' ) || exit;

if ( ! $is_vendor ) {
	return;
}

$portfolio_service = new \WPSellServices\Services\PortfolioService();
$items             = $portfolio_service->get_by_vendor( $user_id, array( 'limit' => 50 ) );
$item_count        = $portfolio_service->get_count( $user_id );
$max_items         = (int) get_option( 'wpss_max_portfolio_items', 50 );

// Get vendor's services for the dropdown.
$vendor_services = get_posts(
	array(
		'post_type'      => 'wpss_service',
		'author'         => $user_id,
		'post_status'    => 'publish',
		'posts_per_page' => 100,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	)
);

/**
 * Fires before the portfolio dashboard section content.
 *
 * @since 1.1.0
 *
 * @param string $section_name Section identifier ('portfolio').
 * @param int    $user_id      Current user ID.
 */
do_action( 'wpss_dashboard_section_before', 'portfolio', get_userdata( $user_id ) );
?>

<div class="wpss-section wpss-section--portfolio">
	<div class="wpss-portfolio__header">
		<p class="wpss-portfolio__count">
			<?php
			printf(
				/* translators: 1: current count, 2: max items */
				esc_html__( '%1$d of %2$d items', 'wp-sell-services' ),
				$item_count,
				$max_items
			);
			?>
		</p>
		<?php if ( $item_count < $max_items ) : ?>
			<button type="button" class="wpss-btn wpss-btn--primary wpss-btn--small" id="wpss-portfolio-add-btn">
				<?php esc_html_e( 'Add Portfolio Item', 'wp-sell-services' ); ?>
			</button>
		<?php endif; ?>
	</div>

	<?php if ( empty( $items ) ) : ?>
		<div class="wpss-dashboard__empty">
			<h3><?php esc_html_e( 'No portfolio items yet', 'wp-sell-services' ); ?></h3>
			<p><?php esc_html_e( 'Showcase your best work to attract more buyers.', 'wp-sell-services' ); ?></p>
		</div>
	<?php else : ?>
		<div class="wpss-portfolio__grid" id="wpss-portfolio-grid">
			<?php foreach ( $items as $item ) : ?>
				<div class="wpss-portfolio__item" data-item-id="<?php echo esc_attr( $item['id'] ); ?>">
					<?php if ( ! empty( $item['media'] ) ) : ?>
						<div class="wpss-portfolio__media">
							<img src="<?php echo esc_url( $item['media'][0]['medium'] ?? $item['media'][0]['url'] ?? '' ); ?>" alt="<?php echo esc_attr( $item['title'] ); ?>">
						</div>
					<?php else : ?>
						<div class="wpss-portfolio__media wpss-portfolio__media--placeholder">
							<svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5">
								<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>
							</svg>
						</div>
					<?php endif; ?>

					<div class="wpss-portfolio__info">
						<h4 class="wpss-portfolio__title"><?php echo esc_html( $item['title'] ); ?></h4>
						<?php if ( ! empty( $item['description'] ) ) : ?>
							<p class="wpss-portfolio__desc"><?php echo esc_html( wp_trim_words( $item['description'], 15 ) ); ?></p>
						<?php endif; ?>

						<div class="wpss-portfolio__actions">
							<?php if ( ! empty( $item['is_featured'] ) ) : ?>
								<span class="wpss-badge wpss-badge--warning wpss-badge--small"><?php esc_html_e( 'Featured', 'wp-sell-services' ); ?></span>
							<?php endif; ?>
							<button type="button" class="wpss-btn wpss-btn--small wpss-btn--link wpss-portfolio-edit" data-item-id="<?php echo esc_attr( $item['id'] ); ?>">
								<?php esc_html_e( 'Edit', 'wp-sell-services' ); ?>
							</button>
							<button type="button" class="wpss-btn wpss-btn--small wpss-btn--link wpss-portfolio-toggle-featured" data-item-id="<?php echo esc_attr( $item['id'] ); ?>">
								<?php echo $item['is_featured'] ? esc_html__( 'Unfeature', 'wp-sell-services' ) : esc_html__( 'Feature', 'wp-sell-services' ); ?>
							</button>
							<button type="button" class="wpss-btn wpss-btn--small wpss-btn--link wpss-btn--danger wpss-portfolio-delete" data-item-id="<?php echo esc_attr( $item['id'] ); ?>">
								<?php esc_html_e( 'Delete', 'wp-sell-services' ); ?>
							</button>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<!-- Add/Edit Portfolio Modal -->
	<div class="wpss-modal" id="wpss-portfolio-modal" style="display: none;">
		<div class="wpss-modal__overlay"></div>
		<div class="wpss-modal__content">
			<div class="wpss-modal__header">
				<h3 id="wpss-portfolio-modal-title"><?php esc_html_e( 'Add Portfolio Item', 'wp-sell-services' ); ?></h3>
				<button type="button" class="wpss-modal__close">&times;</button>
			</div>
			<form id="wpss-portfolio-form" method="post">
				<?php wp_nonce_field( 'wpss_portfolio_nonce', 'portfolio_nonce' ); ?>
				<input type="hidden" name="item_id" id="wpss-portfolio-item-id" value="0">

				<div class="wpss-form-row">
					<label for="portfolio-title"><?php esc_html_e( 'Title', 'wp-sell-services' ); ?> <span class="required">*</span></label>
					<input type="text" id="portfolio-title" name="title" class="wpss-input" required>
				</div>

				<div class="wpss-form-row">
					<label for="portfolio-description"><?php esc_html_e( 'Description', 'wp-sell-services' ); ?></label>
					<textarea id="portfolio-description" name="description" rows="3" class="wpss-textarea"></textarea>
				</div>

				<div class="wpss-form-row">
					<label><?php esc_html_e( 'Images', 'wp-sell-services' ); ?></label>
					<div class="wpss-portfolio-media-preview" id="wpss-portfolio-media-preview"></div>
					<input type="hidden" name="media" id="wpss-portfolio-media" value="[]">
					<button type="button" class="wpss-btn wpss-btn--small wpss-btn--secondary" id="wpss-portfolio-upload-media">
						<?php esc_html_e( 'Upload Images', 'wp-sell-services' ); ?>
					</button>
				</div>

				<div class="wpss-form-row">
					<label for="portfolio-external-url"><?php esc_html_e( 'External URL', 'wp-sell-services' ); ?></label>
					<input type="url" id="portfolio-external-url" name="external_url" class="wpss-input" placeholder="https://">
				</div>

				<div class="wpss-form-row">
					<label for="portfolio-tags"><?php esc_html_e( 'Tags', 'wp-sell-services' ); ?></label>
					<input type="text" id="portfolio-tags" name="tags" class="wpss-input" placeholder="<?php esc_attr_e( 'e.g., logo, branding, modern', 'wp-sell-services' ); ?>">
					<p class="wpss-form-hint"><?php esc_html_e( 'Comma-separated list of tags.', 'wp-sell-services' ); ?></p>
				</div>

				<?php if ( ! empty( $vendor_services ) ) : ?>
					<div class="wpss-form-row">
						<label for="portfolio-service"><?php esc_html_e( 'Related Service', 'wp-sell-services' ); ?></label>
						<select id="portfolio-service" name="service_id" class="wpss-input">
							<option value="0"><?php esc_html_e( '-- None --', 'wp-sell-services' ); ?></option>
							<?php foreach ( $vendor_services as $service_id_opt ) : ?>
								<option value="<?php echo esc_attr( $service_id_opt ); ?>">
									<?php echo esc_html( get_the_title( $service_id_opt ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				<?php endif; ?>

				<div class="wpss-form-row">
					<label class="wpss-toggle">
						<input type="checkbox" name="is_featured" value="1" id="portfolio-featured">
						<span class="wpss-toggle__label"><?php esc_html_e( 'Mark as Featured', 'wp-sell-services' ); ?></span>
					</label>
				</div>

				<div class="wpss-modal__footer">
					<button type="button" class="wpss-btn wpss-btn--secondary wpss-modal__close-btn"><?php esc_html_e( 'Cancel', 'wp-sell-services' ); ?></button>
					<button type="submit" class="wpss-btn wpss-btn--primary"><?php esc_html_e( 'Save', 'wp-sell-services' ); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>

<?php
/**
 * Fires after the portfolio dashboard section content.
 *
 * @since 1.1.0
 *
 * @param string $section_name Section identifier ('portfolio').
 * @param int    $user_id      Current user ID.
 */
do_action( 'wpss_dashboard_section_after', 'portfolio', get_userdata( $user_id ) );
?>
