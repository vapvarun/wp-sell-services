<?php
/**
 * Vendor Dashboard - My Account Template
 *
 * Uses CSS classes from vendor-dashboard.css design system.
 *
 * @package WPSellServices\Templates
 * @since   1.0.0
 *
 * @var \WPSellServices\Models\VendorProfile|null $vendor  Vendor profile.
 * @var object                                     $stats   Vendor statistics.
 * @var int                                        $user_id Current user ID.
 */

defined( 'ABSPATH' ) || exit;

// Enqueue vendor dashboard styles.
wp_enqueue_style( 'wpss-vendor-dashboard', WPSS_PLUGIN_URL . 'assets/css/vendor-dashboard.css', array( 'wpss-design-system' ), WPSS_VERSION );

/**
 * Fires before the vendor dashboard content.
 *
 * @since 1.1.0
 *
 * @param int $user_id Current user ID.
 */
do_action( 'wpss_vendor_dashboard_before', $user_id );
?>

<div class="wpss-dashboard">
	<header class="wpss-dashboard__header">
		<h1 class="wpss-dashboard__title"><?php esc_html_e( 'Vendor Dashboard', 'wp-sell-services' ); ?></h1>
		<div class="wpss-dashboard__actions">
			<a href="<?php echo esc_url( home_url( '/my-account/vendor-services/' ) ); ?>" class="wpss-btn wpss-btn--secondary">
				<?php esc_html_e( 'Manage Services', 'wp-sell-services' ); ?>
			</a>
			<?php
			$create_service_url = wpss_get_create_service_url();
			if ( $create_service_url ) :
				?>
				<a href="<?php echo esc_url( $create_service_url ); ?>" class="wpss-btn wpss-btn--primary">
					<?php esc_html_e( 'Create New Service', 'wp-sell-services' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</header>

	<?php if ( $vendor ) : ?>
		<div class="wpss-dashboard__body">
			<?php
			/**
			 * Fires at the start of vendor dashboard body for custom widgets.
			 *
			 * Allows developers to add custom dashboard widgets or cards.
			 *
			 * @since 1.1.0
			 *
			 * @param int $user_id Current user ID.
			 */
			do_action( 'wpss_vendor_dashboard_widgets', $user_id );
			?>

			<!-- Vendor Profile Card -->
			<div class="wpss-card wpss-card--profile">
				<div class="wpss-card__body">
					<div class="wpss-profile">
						<img src="<?php echo esc_url( $vendor->get_avatar_url() ); ?>" alt="" class="wpss-profile__avatar">
						<div class="wpss-profile__info">
							<h3 class="wpss-profile__name"><?php echo esc_html( $vendor->display_name ); ?></h3>
							<div class="wpss-profile__meta">
								<span class="wpss-badge wpss-badge--tier-<?php echo esc_attr( $vendor->tier ); ?>">
									<?php echo esc_html( $vendor->get_tier_label() ); ?>
								</span>
								<?php if ( $vendor->is_verified ) : ?>
									<span class="wpss-badge wpss-badge--verified">
										<i data-lucide="check" class="wpss-icon wpss-icon--sm" aria-hidden="true"></i>
										<?php esc_html_e( 'Verified', 'wp-sell-services' ); ?>
									</span>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Stats Grid -->
			<div class="wpss-stats-grid">
				<div class="wpss-stat-card">
					<div class="wpss-stat-card__icon wpss-stat-card__icon--earnings">
						<i data-lucide="dollar-sign" class="wpss-icon wpss-icon--lg" aria-hidden="true"></i>
					</div>
					<div class="wpss-stat-card__content">
						<div class="wpss-stat-card__value"><?php echo esc_html( wpss_format_price( (float) ( $stats->total_earnings ?? 0 ) ) ); ?></div>
						<div class="wpss-stat-card__label"><?php esc_html_e( 'Total Earnings', 'wp-sell-services' ); ?></div>
					</div>
				</div>

				<div class="wpss-stat-card">
					<div class="wpss-stat-card__icon wpss-stat-card__icon--completed">
						<i data-lucide="check-circle-2" class="wpss-icon wpss-icon--lg" aria-hidden="true"></i>
					</div>
					<div class="wpss-stat-card__content">
						<div class="wpss-stat-card__value"><?php echo esc_html( $stats->completed_orders ?? 0 ); ?></div>
						<div class="wpss-stat-card__label"><?php esc_html_e( 'Completed Orders', 'wp-sell-services' ); ?></div>
					</div>
				</div>

				<div class="wpss-stat-card">
					<div class="wpss-stat-card__icon wpss-stat-card__icon--active">
						<i data-lucide="clock" class="wpss-icon wpss-icon--lg" aria-hidden="true"></i>
					</div>
					<div class="wpss-stat-card__content">
						<div class="wpss-stat-card__value"><?php echo esc_html( $stats->active_orders ?? 0 ); ?></div>
						<div class="wpss-stat-card__label"><?php esc_html_e( 'Active Orders', 'wp-sell-services' ); ?></div>
					</div>
				</div>

				<div class="wpss-stat-card">
					<div class="wpss-stat-card__icon wpss-stat-card__icon--rating">
						<i data-lucide="star" class="wpss-icon wpss-icon--lg" aria-hidden="true"></i>
					</div>
					<div class="wpss-stat-card__content">
						<div class="wpss-stat-card__value"><?php echo esc_html( number_format( $vendor->rating, 1 ) ); ?></div>
						<div class="wpss-stat-card__label"><?php esc_html_e( 'Average Rating', 'wp-sell-services' ); ?></div>
					</div>
				</div>
			</div>

			<!-- Performance Metrics -->
			<div class="wpss-card">
				<div class="wpss-card__header">
					<h3 class="wpss-card__title"><?php esc_html_e( 'Performance Metrics', 'wp-sell-services' ); ?></h3>
				</div>
				<div class="wpss-card__body">
					<div class="wpss-metrics-grid">
						<div class="wpss-metric">
							<div class="wpss-metric__header">
								<span class="wpss-metric__label"><?php esc_html_e( 'Response Rate', 'wp-sell-services' ); ?></span>
								<span class="wpss-metric__value"><?php echo esc_html( number_format( $vendor->response_rate, 0 ) ); ?>%</span>
							</div>
							<div class="wpss-metric__bar">
								<div class="wpss-metric__fill" style="width: <?php echo esc_attr( $vendor->response_rate ); ?>%"></div>
							</div>
						</div>

						<div class="wpss-metric">
							<div class="wpss-metric__header">
								<span class="wpss-metric__label"><?php esc_html_e( 'Response Time', 'wp-sell-services' ); ?></span>
								<span class="wpss-metric__value"><?php echo esc_html( $vendor->get_response_time_label() ); ?></span>
							</div>
						</div>

						<div class="wpss-metric">
							<div class="wpss-metric__header">
								<span class="wpss-metric__label"><?php esc_html_e( 'On-Time Delivery', 'wp-sell-services' ); ?></span>
								<span class="wpss-metric__value"><?php echo esc_html( number_format( $vendor->delivery_rate, 0 ) ); ?>%</span>
							</div>
							<div class="wpss-metric__bar">
								<div class="wpss-metric__fill" style="width: <?php echo esc_attr( $vendor->delivery_rate ); ?>%"></div>
							</div>
						</div>

						<div class="wpss-metric">
							<div class="wpss-metric__header">
								<span class="wpss-metric__label"><?php esc_html_e( 'Order Completion', 'wp-sell-services' ); ?></span>
								<span class="wpss-metric__value"><?php echo esc_html( number_format( $vendor->completion_rate, 0 ) ); ?>%</span>
							</div>
							<div class="wpss-metric__bar">
								<div class="wpss-metric__fill" style="width: <?php echo esc_attr( $vendor->completion_rate ); ?>%"></div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Quick Links -->
			<div class="wpss-card-grid wpss-card-grid--3col">
				<a href="<?php echo esc_url( home_url( '/my-account/service-orders/' ) ); ?>" class="wpss-card wpss-card--link">
					<div class="wpss-card__body">
						<i data-lucide="file-text" class="wpss-icon wpss-icon--lg" aria-hidden="true"></i>
						<h4><?php esc_html_e( 'Orders', 'wp-sell-services' ); ?></h4>
						<p><?php esc_html_e( 'View and manage your orders', 'wp-sell-services' ); ?></p>
					</div>
				</a>

				<a href="<?php echo esc_url( home_url( '/my-account/conversations/' ) ); ?>" class="wpss-card wpss-card--link">
					<div class="wpss-card__body">
						<i data-lucide="message-square" class="wpss-icon wpss-icon--lg" aria-hidden="true"></i>
						<h4><?php esc_html_e( 'Messages', 'wp-sell-services' ); ?></h4>
						<p><?php esc_html_e( 'Chat with your buyers', 'wp-sell-services' ); ?></p>
					</div>
				</a>

				<a href="<?php echo esc_url( home_url( '/my-account/earnings/' ) ); ?>" class="wpss-card wpss-card--link">
					<div class="wpss-card__body">
						<i data-lucide="dollar-sign" class="wpss-icon wpss-icon--lg" aria-hidden="true"></i>
						<h4><?php esc_html_e( 'Earnings', 'wp-sell-services' ); ?></h4>
						<p><?php esc_html_e( 'Track your revenue', 'wp-sell-services' ); ?></p>
					</div>
				</a>
			</div>

			<?php
			/**
			 * Fires at the end of vendor dashboard body for custom actions.
			 *
			 * Allows developers to add custom action buttons or sections.
			 *
			 * @since 1.1.0
			 *
			 * @param int $user_id Current user ID.
			 */
			do_action( 'wpss_vendor_dashboard_actions', $user_id );
			?>
		</div>

	<?php else : ?>
		<div class="wpss-dashboard__body">
			<div class="wpss-empty-state">
				<div class="wpss-empty-state__icon">
					<i data-lucide="user" class="wpss-icon wpss-icon--lg" aria-hidden="true"></i>
				</div>
				<h3 class="wpss-empty-state__title"><?php esc_html_e( 'Set Up Your Vendor Profile', 'wp-sell-services' ); ?></h3>
				<p class="wpss-empty-state__text"><?php esc_html_e( 'Complete your profile to start selling services on our marketplace.', 'wp-sell-services' ); ?></p>
				<a href="<?php echo esc_url( home_url( '/become-a-seller/' ) ); ?>" class="wpss-btn wpss-btn--primary wpss-btn--lg">
					<?php esc_html_e( 'Get Started', 'wp-sell-services' ); ?>
				</a>
			</div>
		</div>
	<?php endif; ?>
</div>

<?php
/**
 * Fires after the vendor dashboard content.
 *
 * @since 1.1.0
 *
 * @param int $user_id Current user ID.
 */
do_action( 'wpss_vendor_dashboard_after', $user_id );
?>
