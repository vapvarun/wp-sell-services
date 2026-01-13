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
										<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
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
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
					</div>
					<div class="wpss-stat-card__content">
						<div class="wpss-stat-card__value"><?php echo esc_html( wc_price( $stats->total_earnings ?? 0 ) ); ?></div>
						<div class="wpss-stat-card__label"><?php esc_html_e( 'Total Earnings', 'wp-sell-services' ); ?></div>
					</div>
				</div>

				<div class="wpss-stat-card">
					<div class="wpss-stat-card__icon wpss-stat-card__icon--completed">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
					</div>
					<div class="wpss-stat-card__content">
						<div class="wpss-stat-card__value"><?php echo esc_html( $stats->completed_orders ?? 0 ); ?></div>
						<div class="wpss-stat-card__label"><?php esc_html_e( 'Completed Orders', 'wp-sell-services' ); ?></div>
					</div>
				</div>

				<div class="wpss-stat-card">
					<div class="wpss-stat-card__icon wpss-stat-card__icon--active">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
					</div>
					<div class="wpss-stat-card__content">
						<div class="wpss-stat-card__value"><?php echo esc_html( $stats->active_orders ?? 0 ); ?></div>
						<div class="wpss-stat-card__label"><?php esc_html_e( 'Active Orders', 'wp-sell-services' ); ?></div>
					</div>
				</div>

				<div class="wpss-stat-card">
					<div class="wpss-stat-card__icon wpss-stat-card__icon--rating">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
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
						<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--wpss-primary)" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
						<h4><?php esc_html_e( 'Orders', 'wp-sell-services' ); ?></h4>
						<p><?php esc_html_e( 'View and manage your orders', 'wp-sell-services' ); ?></p>
					</div>
				</a>

				<a href="<?php echo esc_url( home_url( '/my-account/conversations/' ) ); ?>" class="wpss-card wpss-card--link">
					<div class="wpss-card__body">
						<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--wpss-primary)" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
						<h4><?php esc_html_e( 'Messages', 'wp-sell-services' ); ?></h4>
						<p><?php esc_html_e( 'Chat with your buyers', 'wp-sell-services' ); ?></p>
					</div>
				</a>

				<a href="<?php echo esc_url( home_url( '/my-account/earnings/' ) ); ?>" class="wpss-card wpss-card--link">
					<div class="wpss-card__body">
						<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--wpss-primary)" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
						<h4><?php esc_html_e( 'Earnings', 'wp-sell-services' ); ?></h4>
						<p><?php esc_html_e( 'Track your revenue', 'wp-sell-services' ); ?></p>
					</div>
				</a>
			</div>
		</div>

	<?php else : ?>
		<div class="wpss-dashboard__body">
			<div class="wpss-empty-state">
				<div class="wpss-empty-state__icon">
					<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
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
