<?php
/**
 * Service Moderation Page
 *
 * Admin page for reviewing and approving vendor services.
 *
 * @package WPSellServices\Admin\Pages
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Admin\Pages;

use WPSellServices\Services\EmailService;
use WPSellServices\Services\ModerationService;

defined( 'ABSPATH' ) || exit;

/**
 * Service Moderation Page Class.
 *
 * @since 1.0.0
 */
class ServiceModerationPage {

	/**
	 * Moderation statuses.
	 */
	public const STATUS_PENDING  = 'pending';
	public const STATUS_APPROVED = 'approved';
	public const STATUS_REJECTED = 'rejected';

	/**
	 * Meta key for moderation status.
	 */
	public const META_KEY = '_wpss_moderation_status';

	/**
	 * Meta key for rejection reason.
	 */
	public const REJECTION_REASON_KEY = '_wpss_rejection_reason';

	/**
	 * Screen option for items per page.
	 *
	 * @var string
	 */
	public const SCREEN_OPTION = 'wpss_moderation_per_page';

	/**
	 * Initialize the page.
	 *
	 * @return void
	 */
	public function init(): void {
		// Always register admin menu - shows enabled/disabled state.
		add_action( 'admin_menu', array( $this, 'add_menu_page' ), 15 );
		// Priority 20 ensures this runs after Admin::enqueue_scripts registers wpss-admin.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 20 );

		// Screen options.
		add_filter( 'set-screen-option', array( $this, 'set_screen_option' ), 10, 3 );

		// AJAX handlers - always registered for admin use.
		add_action( 'wp_ajax_wpss_approve_service', array( $this, 'ajax_approve_service' ) );
		add_action( 'wp_ajax_wpss_reject_service', array( $this, 'ajax_reject_service' ) );
		add_action( 'wp_ajax_wpss_bulk_moderate_services', array( $this, 'ajax_bulk_moderate' ) );

		// Add moderation column to services list.
		add_filter( 'manage_wpss_service_posts_columns', array( $this, 'add_moderation_column' ) );
		add_action( 'manage_wpss_service_posts_custom_column', array( $this, 'render_moderation_column' ), 10, 2 );
		add_filter( 'manage_edit-wpss_service_sortable_columns', array( $this, 'sortable_columns' ) );

		// Add quick edit support.
		add_action( 'quick_edit_custom_box', array( $this, 'quick_edit_fields' ), 10, 2 );

		// Save quick edit and bulk edit moderation status.
		add_action( 'save_post_wpss_service', array( $this, 'save_moderation_status' ), 10, 2 );

		// Hide native Status dropdown in Quick Edit when moderation is active.
		if ( ModerationService::is_enabled() ) {
			add_action( 'admin_head-edit.php', array( $this, 'hide_quick_edit_status' ) );
		}

		// Add metabox to service edit screen.
		add_action( 'add_meta_boxes', array( $this, 'add_moderation_metabox' ) );

		// Admin notices.
		add_action( 'admin_notices', array( $this, 'pending_services_notice' ) );

		// Only apply moderation workflow when enabled.
		if ( ModerationService::is_enabled() ) {
			// Set default moderation status on new service.
			add_action( 'save_post_wpss_service', array( $this, 'set_default_moderation_status' ), 10, 3 );

			// Filter frontend queries.
			add_action( 'pre_get_posts', array( $this, 'filter_frontend_queries' ) );

			// Modify publish to pending for vendors.
			add_filter( 'wp_insert_post_data', array( $this, 'intercept_publish' ), 10, 2 );
		}
	}

	/**
	 * Add submenu page.
	 *
	 * @return void
	 */
	public function add_menu_page(): void {
		$pending_count = $this->get_pending_count();
		$menu_title    = __( 'Moderation', 'wp-sell-services' );

		if ( $pending_count > 0 ) {
			$menu_title .= sprintf( ' <span class="awaiting-mod">%d</span>', $pending_count );
		}

		$hook = add_submenu_page(
			'wp-sell-services',
			__( 'Service Moderation', 'wp-sell-services' ),
			$menu_title,
			'manage_options',
			'wpss-moderation',
			array( $this, 'render_page' )
		);

		// Add screen options.
		add_action( "load-{$hook}", array( $this, 'add_screen_options' ) );
	}

	/**
	 * Add screen options for items per page.
	 *
	 * @return void
	 */
	public function add_screen_options(): void {
		$option = 'per_page';
		$args   = array(
			'label'   => __( 'Services per page', 'wp-sell-services' ),
			'default' => 20,
			'option'  => self::SCREEN_OPTION,
		);

		add_screen_option( $option, $args );
	}

	/**
	 * Save screen option value.
	 *
	 * @param mixed  $status Screen option value. Default false to skip.
	 * @param string $option The option name.
	 * @param int    $value  The number of items per page.
	 * @return mixed
	 */
	public function set_screen_option( $status, string $option, int $value ) {
		if ( self::SCREEN_OPTION === $option ) {
			return $value;
		}

		return $status;
	}

	/**
	 * Enqueue page scripts.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_scripts( string $hook ): void {
		if ( 'wp-sell-services_page_wpss-moderation' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'wpss-admin' );
		wp_enqueue_script( 'wpss-admin' );

		wp_add_inline_script(
			'wpss-admin',
			'window.wpssModeration = ' . wp_json_encode(
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'wpss_moderation' ),
					'i18n'    => array(
						'confirmApprove' => __( 'Approve this service?', 'wp-sell-services' ),
						'confirmReject'  => __( 'Reject this service?', 'wp-sell-services' ),
						'rejectReason'   => __( 'Please provide a reason for rejection:', 'wp-sell-services' ),
						'loading'        => __( 'Processing...', 'wp-sell-services' ),
						'approved'       => __( 'Service approved!', 'wp-sell-services' ),
						'rejected'       => __( 'Service rejected.', 'wp-sell-services' ),
						'error'          => __( 'An error occurred. Please try again.', 'wp-sell-services' ),
						'selectServices' => __( 'Please select at least one service.', 'wp-sell-services' ),
						'confirmBulk'    => __( 'Apply this action to selected services?', 'wp-sell-services' ),
					),
				)
			)
		);
	}

	/**
	 * Get count of pending services.
	 *
	 * @return int
	 */
	public function get_pending_count(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT p.ID)
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
				WHERE p.post_type = 'wpss_service'
				AND p.post_status IN ('pending', 'publish')
				AND pm.meta_value = %s",
				self::META_KEY,
				self::STATUS_PENDING
			)
		);

		return absint( $count );
	}

	/**
	 * Render the moderation page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		// Check if moderation is enabled.
		if ( ! ModerationService::is_enabled() ) {
			$this->render_disabled_notice();
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : self::STATUS_PENDING;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$paged = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;

		// Get items per page from screen option.
		$user_id  = get_current_user_id();
		$per_page = get_user_meta( $user_id, self::SCREEN_OPTION, true );
		if ( empty( $per_page ) || $per_page < 1 ) {
			$per_page = 20;
		}

		$args = array(
			'post_type'      => 'wpss_service',
			'post_status'    => array( 'pending', 'publish' ),
			'posts_per_page' => absint( $per_page ),
			'paged'          => $paged,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( 'all' !== $status_filter ) {
			if ( self::STATUS_PENDING === $status_filter ) {
				$args['meta_query'] = array(
					array(
						'key'     => self::META_KEY,
						'value'   => self::STATUS_PENDING,
						'compare' => '=',
					),
				);
			} else {
				$args['meta_query'] = array(
					array(
						'key'     => self::META_KEY,
						'value'   => $status_filter,
						'compare' => '=',
					),
				);
			}
		}

		$query    = new \WP_Query( $args );
		$services = $query->posts;

		// Get counts for tabs.
		$counts = $this->get_status_counts();
		?>
		<div class="wrap wpss-moderation-wrap">
			<h1><?php esc_html_e( 'Service Moderation', 'wp-sell-services' ); ?></h1>
			<hr class="wp-header-end">

			<!-- Status Tabs -->
			<ul class="subsubsub">
				<li>
					<a href="<?php echo esc_url( add_query_arg( 'status', 'all' ) ); ?>"
						class="<?php echo 'all' === $status_filter ? 'current' : ''; ?>">
						<?php esc_html_e( 'All', 'wp-sell-services' ); ?>
						<span class="count">(<?php echo esc_html( array_sum( $counts ) ); ?>)</span>
					</a> |
				</li>
				<li>
					<a href="<?php echo esc_url( add_query_arg( 'status', self::STATUS_PENDING ) ); ?>"
						class="<?php echo self::STATUS_PENDING === $status_filter ? 'current' : ''; ?>">
						<?php esc_html_e( 'Pending', 'wp-sell-services' ); ?>
						<span class="count">(<?php echo esc_html( $counts[ self::STATUS_PENDING ] ?? 0 ); ?>)</span>
					</a> |
				</li>
				<li>
					<a href="<?php echo esc_url( add_query_arg( 'status', self::STATUS_APPROVED ) ); ?>"
						class="<?php echo self::STATUS_APPROVED === $status_filter ? 'current' : ''; ?>">
						<?php esc_html_e( 'Approved', 'wp-sell-services' ); ?>
						<span class="count">(<?php echo esc_html( $counts[ self::STATUS_APPROVED ] ?? 0 ); ?>)</span>
					</a> |
				</li>
				<li>
					<a href="<?php echo esc_url( add_query_arg( 'status', self::STATUS_REJECTED ) ); ?>"
						class="<?php echo self::STATUS_REJECTED === $status_filter ? 'current' : ''; ?>">
						<?php esc_html_e( 'Rejected', 'wp-sell-services' ); ?>
						<span class="count">(<?php echo esc_html( $counts[ self::STATUS_REJECTED ] ?? 0 ); ?>)</span>
					</a>
				</li>
			</ul>
			<div class="clear"></div>

			<!-- Bulk Actions -->
			<form method="post" id="wpss-moderation-form">
				<?php wp_nonce_field( 'wpss_moderation_bulk', 'wpss_moderation_nonce' ); ?>

				<div class="tablenav top">
					<div class="alignleft actions bulkactions">
						<select name="bulk_action" id="bulk-action-selector">
							<option value=""><?php esc_html_e( 'Bulk Actions', 'wp-sell-services' ); ?></option>
							<option value="approve"><?php esc_html_e( 'Approve', 'wp-sell-services' ); ?></option>
							<option value="reject"><?php esc_html_e( 'Reject', 'wp-sell-services' ); ?></option>
						</select>
						<button type="button" class="button wpss-bulk-action-btn" id="doaction">
							<?php esc_html_e( 'Apply', 'wp-sell-services' ); ?>
						</button>
					</div>
				</div>

				<table class="wp-list-table widefat fixed striped wpss-moderation-table">
					<thead>
						<tr>
							<td class="manage-column column-cb check-column">
								<input type="checkbox" id="cb-select-all-1">
							</td>
							<th scope="col" class="manage-column column-thumbnail"><?php esc_html_e( 'Image', 'wp-sell-services' ); ?></th>
							<th scope="col" class="manage-column column-title"><?php esc_html_e( 'Service', 'wp-sell-services' ); ?></th>
							<th scope="col" class="manage-column column-vendor"><?php esc_html_e( 'Vendor', 'wp-sell-services' ); ?></th>
							<th scope="col" class="manage-column column-category"><?php esc_html_e( 'Category', 'wp-sell-services' ); ?></th>
							<th scope="col" class="manage-column column-price"><?php esc_html_e( 'Price', 'wp-sell-services' ); ?></th>
							<th scope="col" class="manage-column column-date"><?php esc_html_e( 'Submitted', 'wp-sell-services' ); ?></th>
							<th scope="col" class="manage-column column-status"><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></th>
							<th scope="col" class="manage-column column-actions"><?php esc_html_e( 'Actions', 'wp-sell-services' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $services ) ) : ?>
							<tr>
								<td colspan="9">
									<?php esc_html_e( 'No services found.', 'wp-sell-services' ); ?>
								</td>
							</tr>
						<?php else : ?>
							<?php foreach ( $services as $service ) : ?>
								<?php $this->render_service_row( $service ); ?>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
					<tfoot>
						<tr>
							<td class="manage-column column-cb check-column">
								<input type="checkbox" id="cb-select-all-2">
							</td>
							<th scope="col" class="manage-column column-thumbnail"><?php esc_html_e( 'Image', 'wp-sell-services' ); ?></th>
							<th scope="col" class="manage-column column-title"><?php esc_html_e( 'Service', 'wp-sell-services' ); ?></th>
							<th scope="col" class="manage-column column-vendor"><?php esc_html_e( 'Vendor', 'wp-sell-services' ); ?></th>
							<th scope="col" class="manage-column column-category"><?php esc_html_e( 'Category', 'wp-sell-services' ); ?></th>
							<th scope="col" class="manage-column column-price"><?php esc_html_e( 'Price', 'wp-sell-services' ); ?></th>
							<th scope="col" class="manage-column column-date"><?php esc_html_e( 'Submitted', 'wp-sell-services' ); ?></th>
							<th scope="col" class="manage-column column-status"><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></th>
							<th scope="col" class="manage-column column-actions"><?php esc_html_e( 'Actions', 'wp-sell-services' ); ?></th>
						</tr>
					</tfoot>
				</table>

				<!-- Pagination -->
				<?php if ( $query->max_num_pages > 1 ) : ?>
					<div class="tablenav bottom">
						<div class="tablenav-pages">
							<?php
							$page_links = paginate_links(
								array(
									'base'      => add_query_arg( 'paged', '%#%' ),
									'format'    => '',
									'prev_text' => '&laquo;',
									'next_text' => '&raquo;',
									'total'     => $query->max_num_pages,
									'current'   => $paged,
								)
							);
							echo wp_kses_post( $page_links );
							?>
						</div>
					</div>
				<?php endif; ?>
			</form>
		</div>

		<style>
			.wpss-moderation-table .column-cb { width: 30px; }
			.wpss-moderation-table .column-thumbnail { width: 60px; }
			.wpss-moderation-table .column-title { width: 25%; }
			.wpss-moderation-table .column-vendor { width: 12%; }
			.wpss-moderation-table .column-category { width: 12%; }
			.wpss-moderation-table .column-price { width: 8%; }
			.wpss-moderation-table .column-date { width: 10%; }
			.wpss-moderation-table .column-status { width: 10%; }
			.wpss-moderation-table .column-actions { width: 15%; }
			.wpss-moderation-table .service-thumb { width: 50px; height: 50px; object-fit: cover; border-radius: 4px; }
			.wpss-moderation-table .wpss-status-badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
			.wpss-moderation-table .wpss-status-pending { background: #fff3cd; color: #856404; }
			.wpss-moderation-table .wpss-status-approved { background: #d4edda; color: #155724; }
			.wpss-moderation-table .wpss-status-rejected { background: #f8d7da; color: #721c24; }
			.wpss-moderation-table .row-actions { padding-top: 5px; }
			.wpss-moderation-table .row-actions a { margin-right: 10px; }
			.wpss-moderation-table .approve-action { color: #46b450; }
			.wpss-moderation-table .reject-action { color: #dc3232; }
			.wpss-rejection-reason { color: #666; font-size: 12px; font-style: italic; margin-top: 5px; }
		</style>

		<script>
		// Define wpssModeration inline (wp_add_inline_script runs in footer, after this script).
		window.wpssModeration = window.wpssModeration || 
		<?php
		echo wp_json_encode(
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wpss_moderation' ),
				'i18n'    => array(
					'confirmApprove' => __( 'Approve this service?', 'wp-sell-services' ),
					'confirmReject'  => __( 'Reject this service?', 'wp-sell-services' ),
					'rejectReason'   => __( 'Please provide a reason for rejection:', 'wp-sell-services' ),
					'loading'        => __( 'Processing...', 'wp-sell-services' ),
					'approved'       => __( 'Service approved!', 'wp-sell-services' ),
					'rejected'       => __( 'Service rejected.', 'wp-sell-services' ),
					'error'          => __( 'An error occurred. Please try again.', 'wp-sell-services' ),
					'selectServices' => __( 'Please select at least one service.', 'wp-sell-services' ),
					'confirmBulk'    => __( 'Apply this action to selected services?', 'wp-sell-services' ),
				),
			)
		);
		?>
		;
		function wpssAdminNotice(msg, type) {
			type = type || 'error';
			var cls = type === 'success' ? 'notice-success' : 'notice-error';
			var $notice = $('<div class="notice ' + cls + ' is-dismissible"><p>' + msg + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss</span></button></div>');
			$('.wrap h1, .wrap h2').first().after($notice);
			$notice.find('.notice-dismiss').on('click', function() { $notice.fadeOut(200, function() { $notice.remove(); }); });
			setTimeout(function() { $notice.fadeOut(400, function() { $notice.remove(); }); }, 6000);
		}

		jQuery(function($) {
			var wpssModeration = window.wpssModeration;

			// Approve single service.
			$(document).on('click', '.wpss-approve-service', function(e) {
				e.preventDefault();
				var $btn = $(this);
				var serviceId = $btn.data('service');

				if (!confirm(wpssModeration.i18n.confirmApprove)) {
					return;
				}

				$btn.text(wpssModeration.i18n.loading);

				$.post(wpssModeration.ajaxUrl, {
					action: 'wpss_approve_service',
					service_id: serviceId,
					nonce: wpssModeration.nonce
				}, function(response) {
					if (response.success) {
						location.reload();
					} else {
						wpssAdminNotice(response.data.message || wpssModeration.i18n.error, 'error');
						$btn.text('Approve');
					}
				}).fail(function() {
					wpssAdminNotice(wpssModeration.i18n.error, 'error');
					$btn.text('Approve');
				});
			});

			// Reject single service.
			$(document).on('click', '.wpss-reject-service', function(e) {
				e.preventDefault();
				var $btn = $(this);
				var serviceId = $btn.data('service');

				var reason = prompt(wpssModeration.i18n.rejectReason);
				if (reason === null) {
					return;
				}

				$btn.text(wpssModeration.i18n.loading);

				$.post(wpssModeration.ajaxUrl, {
					action: 'wpss_reject_service',
					service_id: serviceId,
					reason: reason,
					nonce: wpssModeration.nonce
				}, function(response) {
					if (response.success) {
						location.reload();
					} else {
						wpssAdminNotice(response.data.message || wpssModeration.i18n.error, 'error');
						$btn.text('Reject');
					}
				}).fail(function() {
					wpssAdminNotice(wpssModeration.i18n.error, 'error');
					$btn.text('Reject');
				});
			});

			// Bulk actions.
			$('#doaction').on('click', function() {
				var action = $('#bulk-action-selector').val();
				if (!action) {
					return;
				}

				var serviceIds = [];
				$('input[name="service_ids[]"]:checked').each(function() {
					serviceIds.push($(this).val());
				});

				if (serviceIds.length === 0) {
					wpssAdminNotice(wpssModeration.i18n.selectServices, 'error');
					return;
				}

				if (!confirm(wpssModeration.i18n.confirmBulk)) {
					return;
				}

				var reason = '';
				if (action === 'reject') {
					reason = prompt(wpssModeration.i18n.rejectReason);
					if (reason === null) {
						return;
					}
				}

				$.post(wpssModeration.ajaxUrl, {
					action: 'wpss_bulk_moderate_services',
					bulk_action: action,
					service_ids: serviceIds,
					reason: reason,
					nonce: wpssModeration.nonce
				}, function(response) {
					if (response.success) {
						location.reload();
					} else {
						wpssAdminNotice(response.data.message || wpssModeration.i18n.error, 'error');
					}
				});
			});

			// Select all checkboxes.
			$('#cb-select-all-1, #cb-select-all-2').on('change', function() {
				$('input[name="service_ids[]"]').prop('checked', $(this).prop('checked'));
			});
		});
		</script>
		<?php
	}

	/**
	 * Render a single service row.
	 *
	 * @param \WP_Post $service The service post.
	 * @return void
	 */
	private function render_service_row( \WP_Post $service ): void {
		$status           = get_post_meta( $service->ID, self::META_KEY, true );
		$status           = $status ? $status : self::STATUS_APPROVED;
		$rejection_reason = get_post_meta( $service->ID, self::REJECTION_REASON_KEY, true );
		$vendor           = get_user_by( 'ID', $service->post_author );
		$categories       = get_the_terms( $service->ID, 'wpss_service_category' );
		$price            = get_post_meta( $service->ID, '_wpss_starting_price', true );
		$thumbnail        = get_the_post_thumbnail_url( $service->ID, 'thumbnail' );
		?>
		<tr>
			<th scope="row" class="check-column">
				<input type="checkbox" name="service_ids[]" value="<?php echo esc_attr( $service->ID ); ?>">
			</th>
			<td class="column-thumbnail">
				<?php if ( $thumbnail ) : ?>
					<img src="<?php echo esc_url( $thumbnail ); ?>" alt="" class="service-thumb">
				<?php else : ?>
					<i data-lucide="image" class="wpss-icon" style="font-size: 40px; color: #ccc;" aria-hidden="true"></i>
				<?php endif; ?>
			</td>
			<td class="column-title">
				<strong>
					<a href="<?php echo esc_url( get_edit_post_link( $service->ID ) ); ?>">
						<?php echo esc_html( $service->post_title ); ?>
					</a>
				</strong>
				<div class="row-actions">
					<span class="edit">
						<a href="<?php echo esc_url( get_edit_post_link( $service->ID ) ); ?>">
							<?php esc_html_e( 'Edit', 'wp-sell-services' ); ?>
						</a>
					</span>
					|
					<span class="view">
						<a href="<?php echo esc_url( get_permalink( $service->ID ) ); ?>" target="_blank">
							<?php esc_html_e( 'View', 'wp-sell-services' ); ?>
						</a>
					</span>
				</div>
			</td>
			<td class="column-vendor">
				<?php if ( $vendor ) : ?>
					<a href="<?php echo esc_url( get_edit_user_link( $vendor->ID ) ); ?>">
						<?php echo esc_html( $vendor->display_name ); ?>
					</a>
				<?php else : ?>
					<em><?php esc_html_e( 'Unknown', 'wp-sell-services' ); ?></em>
				<?php endif; ?>
			</td>
			<td class="column-category">
				<?php
				if ( $categories && ! is_wp_error( $categories ) ) {
					$cat_names = wp_list_pluck( $categories, 'name' );
					echo esc_html( implode( ', ', $cat_names ) );
				} else {
					echo '—';
				}
				?>
			</td>
			<td class="column-price">
				<?php echo $price ? esc_html( wpss_format_price( (float) $price ) ) : '—'; ?>
			</td>
			<td class="column-date">
				<?php echo esc_html( get_the_date( '', $service ) ); ?>
			</td>
			<td class="column-status">
				<span class="wpss-status-badge wpss-status-<?php echo esc_attr( $status ); ?>">
					<?php echo esc_html( ucfirst( $status ) ); ?>
				</span>
				<?php if ( self::STATUS_REJECTED === $status && $rejection_reason ) : ?>
					<div class="wpss-rejection-reason">
						<?php echo esc_html( $rejection_reason ); ?>
					</div>
				<?php endif; ?>
			</td>
			<td class="column-actions">
				<?php if ( self::STATUS_APPROVED !== $status ) : ?>
					<a href="#" class="button button-small wpss-approve-service approve-action" data-service="<?php echo esc_attr( $service->ID ); ?>">
						<?php esc_html_e( 'Approve', 'wp-sell-services' ); ?>
					</a>
				<?php endif; ?>
				<?php if ( self::STATUS_REJECTED !== $status ) : ?>
					<a href="#" class="button button-small wpss-reject-service reject-action" data-service="<?php echo esc_attr( $service->ID ); ?>">
						<?php esc_html_e( 'Reject', 'wp-sell-services' ); ?>
					</a>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Get status counts.
	 *
	 * @return array
	 */
	private function get_status_counts(): array {
		global $wpdb;

		$counts = array(
			self::STATUS_PENDING  => 0,
			self::STATUS_APPROVED => 0,
			self::STATUS_REJECTED => 0,
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					COALESCE(pm.meta_value, %s) as status,
					COUNT(*) as count
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
				WHERE p.post_type = 'wpss_service'
				AND p.post_status IN ('pending', 'publish')
				GROUP BY COALESCE(pm.meta_value, %s)",
				self::STATUS_APPROVED,
				self::META_KEY,
				self::STATUS_APPROVED
			)
		);

		foreach ( $results as $row ) {
			if ( isset( $counts[ $row->status ] ) ) {
				$counts[ $row->status ] = absint( $row->count );
			}
		}

		return $counts;
	}

	/**
	 * AJAX: Approve a service.
	 *
	 * @return void
	 */
	public function ajax_approve_service(): void {
		check_ajax_referer( 'wpss_moderation', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ) );
		}

		$service_id = absint( $_POST['service_id'] ?? 0 );

		if ( ! $service_id || get_post_type( $service_id ) !== 'wpss_service' ) {
			wp_send_json_error( array( 'message' => __( 'Invalid service.', 'wp-sell-services' ) ) );
		}

		update_post_meta( $service_id, self::META_KEY, self::STATUS_APPROVED );
		delete_post_meta( $service_id, self::REJECTION_REASON_KEY );

		// Publish the service so it's visible on the frontend.
		wp_update_post(
			array(
				'ID'          => $service_id,
				'post_status' => 'publish',
			)
		);

		/**
		 * Fires when a service is approved.
		 *
		 * @param int $service_id The service ID.
		 */
		do_action( 'wpss_service_approved', $service_id );

		// Notify vendor.
		$this->notify_vendor( $service_id, 'approved' );

		wp_send_json_success( array( 'message' => __( 'Service approved.', 'wp-sell-services' ) ) );
	}

	/**
	 * AJAX: Reject a service.
	 *
	 * @return void
	 */
	public function ajax_reject_service(): void {
		check_ajax_referer( 'wpss_moderation', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ) );
		}

		$service_id = absint( $_POST['service_id'] ?? 0 );
		$reason     = sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) );

		if ( ! $service_id || get_post_type( $service_id ) !== 'wpss_service' ) {
			wp_send_json_error( array( 'message' => __( 'Invalid service.', 'wp-sell-services' ) ) );
		}

		update_post_meta( $service_id, self::META_KEY, self::STATUS_REJECTED );

		if ( $reason ) {
			update_post_meta( $service_id, self::REJECTION_REASON_KEY, $reason );
		}

		/**
		 * Fires when a service is rejected.
		 *
		 * @param int    $service_id The service ID.
		 * @param string $reason     The rejection reason.
		 */
		do_action( 'wpss_service_rejected', $service_id, $reason );

		// Notify vendor.
		$this->notify_vendor( $service_id, 'rejected', $reason );

		wp_send_json_success( array( 'message' => __( 'Service rejected.', 'wp-sell-services' ) ) );
	}

	/**
	 * AJAX: Bulk moderate services.
	 *
	 * @return void
	 */
	public function ajax_bulk_moderate(): void {
		check_ajax_referer( 'wpss_moderation', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ) );
		}

		$bulk_action = sanitize_text_field( wp_unslash( $_POST['bulk_action'] ?? '' ) );
		$service_ids = array_map( 'absint', (array) ( $_POST['service_ids'] ?? array() ) );
		$reason      = sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) );

		if ( empty( $service_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No services selected.', 'wp-sell-services' ) ) );
		}

		$processed = 0;

		foreach ( $service_ids as $service_id ) {
			if ( get_post_type( $service_id ) !== 'wpss_service' ) {
				continue;
			}

			if ( 'approve' === $bulk_action ) {
				update_post_meta( $service_id, self::META_KEY, self::STATUS_APPROVED );
				delete_post_meta( $service_id, self::REJECTION_REASON_KEY );
				wp_update_post(
					array(
						'ID'          => $service_id,
						'post_status' => 'publish',
					)
				);
				do_action( 'wpss_service_approved', $service_id );
				$this->notify_vendor( $service_id, 'approved' );
			} elseif ( 'reject' === $bulk_action ) {
				update_post_meta( $service_id, self::META_KEY, self::STATUS_REJECTED );
				if ( $reason ) {
					update_post_meta( $service_id, self::REJECTION_REASON_KEY, $reason );
				}
				do_action( 'wpss_service_rejected', $service_id, $reason );
				$this->notify_vendor( $service_id, 'rejected', $reason );
			}

			++$processed;
		}

		wp_send_json_success(
			array(
				/* translators: %d: number of services processed */
				'message' => sprintf( __( '%d services processed.', 'wp-sell-services' ), $processed ),
			)
		);
	}

	/**
	 * Notify vendor about moderation decision.
	 *
	 * @param int    $service_id The service ID.
	 * @param string $status     The moderation status.
	 * @param string $reason     Optional rejection reason.
	 * @return void
	 */
	private function notify_vendor( int $service_id, string $status, string $reason = '' ): void {
		$service = get_post( $service_id );
		if ( ! $service ) {
			return;
		}

		$vendor = get_user_by( 'ID', $service->post_author );
		if ( ! $vendor ) {
			return;
		}

		$subject = 'approved' === $status
			? __( 'Your service has been approved', 'wp-sell-services' )
			: __( 'Your service was not approved', 'wp-sell-services' );

		$edit_url = add_query_arg(
			array(
				'section'    => 'edit-service',
				'service_id' => $service_id,
			),
			wpss_get_dashboard_url()
		);

		$email_type = 'approved' === $status ? 'moderation_approved' : 'moderation_rejected';
		if ( EmailService::is_type_enabled( $email_type ) ) {
			( new EmailService() )->send(
				$vendor->user_email,
				$subject,
				EmailService::TYPE_MODERATION_RESPONSE,
				array(
					'recipient'     => $vendor,
					'service_title' => $service->post_title,
					'status'        => $status,
					'message'       => $reason,
					'service_url'   => get_permalink( $service_id ),
					'edit_url'      => $edit_url,
				)
			);
		}
	}

	/**
	 * Set default moderation status on new service.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @param bool     $update  Whether this is an update.
	 * @return void
	 */
	public function set_default_moderation_status( int $post_id, \WP_Post $post, bool $update ): void {
		// Skip if updating existing post.
		if ( $update ) {
			return;
		}

		// Skip auto-drafts.
		if ( 'auto-draft' === $post->post_status ) {
			return;
		}

		// Skip if meta already exists.
		if ( metadata_exists( 'post', $post_id, self::META_KEY ) ) {
			return;
		}

		// Admins get auto-approved.
		if ( current_user_can( 'manage_options' ) ) {
			update_post_meta( $post_id, self::META_KEY, self::STATUS_APPROVED );
		} else {
			update_post_meta( $post_id, self::META_KEY, self::STATUS_PENDING );
		}
	}

	/**
	 * Filter frontend queries to only show approved services.
	 *
	 * @param \WP_Query $query The query object.
	 * @return void
	 */
	public function filter_frontend_queries( \WP_Query $query ): void {
		// Skip admin.
		if ( is_admin() ) {
			return;
		}

		// Skip if not main query or not our post type.
		if ( ! $query->is_main_query() ) {
			return;
		}

		// Check if querying services.
		$post_type = $query->get( 'post_type' );
		if ( 'wpss_service' !== $post_type && ! $query->is_singular( 'wpss_service' ) ) {
			// Also check archive.
			if ( ! $query->is_post_type_archive( 'wpss_service' ) ) {
				return;
			}
		}

		// Add meta query for approved status.
		$meta_query_raw      = $query->get( 'meta_query' );
		$existing_meta_query = $meta_query_raw ? $meta_query_raw : array();

		$existing_meta_query[] = array(
			'relation' => 'OR',
			array(
				'key'     => self::META_KEY,
				'value'   => self::STATUS_APPROVED,
				'compare' => '=',
			),
			array(
				'key'     => self::META_KEY,
				'compare' => 'NOT EXISTS',
			),
		);

		$query->set( 'meta_query', $existing_meta_query );
	}

	/**
	 * Add moderation column to services list.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function add_moderation_column( array $columns ): array {
		$new_columns = array();

		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;

			// Add moderation column after title.
			if ( 'title' === $key ) {
				$new_columns['wpss_moderation'] = __( 'Moderation', 'wp-sell-services' );
			}
		}

		return $new_columns;
	}

	/**
	 * Render moderation column.
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function render_moderation_column( string $column, int $post_id ): void {
		if ( 'wpss_moderation' !== $column ) {
			return;
		}

		$status_raw = get_post_meta( $post_id, self::META_KEY, true );
		$status     = $status_raw ? $status_raw : self::STATUS_APPROVED;

		$status_labels = array(
			self::STATUS_PENDING  => __( 'Pending', 'wp-sell-services' ),
			self::STATUS_APPROVED => __( 'Approved', 'wp-sell-services' ),
			self::STATUS_REJECTED => __( 'Rejected', 'wp-sell-services' ),
		);

		$label = $status_labels[ $status ] ?? ucfirst( $status );

		printf(
			'<span class="wpss-status-badge wpss-status-%s" style="display:inline-block;padding:3px 8px;border-radius:3px;font-size:11px;font-weight:600;">%s</span>',
			esc_attr( $status ),
			esc_html( $label )
		);

		// Add inline styles for status badges.
		static $styles_added = false;
		if ( ! $styles_added ) {
			echo '<style>
				.wpss-status-pending { background: #fff3cd; color: #856404; }
				.wpss-status-approved { background: #d4edda; color: #155724; }
				.wpss-status-rejected { background: #f8d7da; color: #721c24; }
			</style>';
			$styles_added = true;
		}
	}

	/**
	 * Make moderation column sortable.
	 *
	 * @param array $columns Sortable columns.
	 * @return array
	 */
	public function sortable_columns( array $columns ): array {
		$columns['wpss_moderation'] = 'wpss_moderation';
		return $columns;
	}

	/**
	 * Quick edit fields.
	 *
	 * @param string $column_name Column name.
	 * @param string $post_type   Post type.
	 * @return void
	 */
	public function quick_edit_fields( string $column_name, string $post_type ): void {
		if ( 'wpss_moderation' !== $column_name || 'wpss_service' !== $post_type ) {
			return;
		}
		?>
		<fieldset class="inline-edit-col-right">
			<div class="inline-edit-col">
				<label>
					<span class="title"><?php esc_html_e( 'Moderation', 'wp-sell-services' ); ?></span>
					<select name="wpss_moderation_status">
						<option value="<?php echo esc_attr( self::STATUS_PENDING ); ?>"><?php esc_html_e( 'Pending', 'wp-sell-services' ); ?></option>
						<option value="<?php echo esc_attr( self::STATUS_APPROVED ); ?>"><?php esc_html_e( 'Approved', 'wp-sell-services' ); ?></option>
						<option value="<?php echo esc_attr( self::STATUS_REJECTED ); ?>"><?php esc_html_e( 'Rejected', 'wp-sell-services' ); ?></option>
					</select>
				</label>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Hide native Status dropdown in Quick Edit for services when moderation is active.
	 *
	 * @return void
	 */
	public function hide_quick_edit_status(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'wpss_service' !== $screen->post_type ) {
			return;
		}
		?>
		<style>
			.inline-edit-row .inline-edit-status { display: none !important; }
		</style>
		<?php
	}

	/**
	 * Save moderation status from quick edit, bulk edit, or admin edit screen.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function save_moderation_status( int $post_id, \WP_Post $post ): void {
		// Skip when moderation is disabled.
		if ( ! ModerationService::is_enabled() ) {
			return;
		}

		// Skip autosaves.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Skip revisions.
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Only admins can change moderation status.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Check if moderation status was submitted.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['wpss_moderation_status'] ) ) {
			// If this is a new service created by admin, auto-approve.
			if ( 'auto-draft' !== $post->post_status && ! metadata_exists( 'post', $post_id, self::META_KEY ) ) {
				update_post_meta( $post_id, self::META_KEY, self::STATUS_APPROVED );
			}
			return;
		}

		// Verify nonce (from metabox or inline edit).
		$nonce_valid = false;
		if ( isset( $_POST['wpss_moderation_nonce'] ) ) {
			$nonce_valid = wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpss_moderation_nonce'] ) ), 'wpss_moderation_metabox' );
		}
		// Quick edit uses WordPress's built-in inline-save action which verifies its own nonce.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$is_inline_edit = isset( $_POST['action'] ) && 'inline-save' === $_POST['action'];

		if ( ! $nonce_valid && ! $is_inline_edit ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$new_status = sanitize_text_field( wp_unslash( $_POST['wpss_moderation_status'] ) );

		// Validate status.
		$valid_statuses = array( self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED );
		if ( ! in_array( $new_status, $valid_statuses, true ) ) {
			return;
		}

		$old_status = get_post_meta( $post_id, self::META_KEY, true );

		// Update the moderation status.
		update_post_meta( $post_id, self::META_KEY, $new_status );

		// Clear rejection reason when approving.
		if ( self::STATUS_APPROVED === $new_status && self::STATUS_REJECTED === $old_status ) {
			delete_post_meta( $post_id, self::REJECTION_REASON_KEY );
		}

		// Sync post_status to match moderation status.
		if ( $new_status !== $old_status ) {
			$post_status_map = array(
				self::STATUS_APPROVED => 'publish',
				self::STATUS_REJECTED => 'draft',
				self::STATUS_PENDING  => 'pending',
			);

			if ( isset( $post_status_map[ $new_status ] ) && $post->post_status !== $post_status_map[ $new_status ] ) {
				// Remove this action to prevent infinite loop.
				remove_action( 'save_post_wpss_service', array( $this, 'save_moderation_status' ), 10 );
				wp_update_post(
					array(
						'ID'          => $post_id,
						'post_status' => $post_status_map[ $new_status ],
					)
				);
				add_action( 'save_post_wpss_service', array( $this, 'save_moderation_status' ), 10, 2 );
			}

			// Fire appropriate action.
			if ( self::STATUS_APPROVED === $new_status ) {
				do_action( 'wpss_service_approved', $post_id );
			} elseif ( self::STATUS_REJECTED === $new_status ) {
				do_action( 'wpss_service_rejected', $post_id, '' );
			}
		}
	}

	/**
	 * Add moderation metabox to service edit screen.
	 *
	 * @return void
	 */
	public function add_moderation_metabox(): void {
		add_meta_box(
			'wpss_moderation_status',
			__( 'Moderation Status', 'wp-sell-services' ),
			array( $this, 'render_moderation_metabox' ),
			'wpss_service',
			'side',
			'high'
		);
	}

	/**
	 * Render moderation metabox.
	 *
	 * @param \WP_Post $post Current post.
	 * @return void
	 */
	public function render_moderation_metabox( \WP_Post $post ): void {
		$current_status = get_post_meta( $post->ID, self::META_KEY, true );
		if ( ! $current_status ) {
			$current_status = self::STATUS_APPROVED;
		}

		$statuses = array(
			self::STATUS_PENDING  => __( 'Pending Review', 'wp-sell-services' ),
			self::STATUS_APPROVED => __( 'Approved', 'wp-sell-services' ),
			self::STATUS_REJECTED => __( 'Rejected', 'wp-sell-services' ),
		);

		$status_colors = array(
			self::STATUS_PENDING  => '#856404',
			self::STATUS_APPROVED => '#155724',
			self::STATUS_REJECTED => '#721c24',
		);

		wp_nonce_field( 'wpss_moderation_metabox', 'wpss_moderation_nonce' );
		?>
		<div class="wpss-moderation-metabox">
			<p>
				<label for="wpss_moderation_status"><strong><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></strong></label>
			</p>
			<p>
				<select name="wpss_moderation_status" id="wpss_moderation_status" style="width: 100%;">
					<?php foreach ( $statuses as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_status, $value ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>
			<p class="description">
				<?php if ( ! ModerationService::is_enabled() ) : ?>
					<em><?php esc_html_e( 'Note: Service moderation is currently disabled in settings.', 'wp-sell-services' ); ?></em>
				<?php else : ?>
					<?php esc_html_e( 'Only approved services are visible on the frontend.', 'wp-sell-services' ); ?>
				<?php endif; ?>
			</p>
		</div>
		<style>
			.wpss-moderation-metabox select { margin-top: 5px; }
		</style>
		<?php
	}

	/**
	 * Render disabled notice when moderation is off.
	 *
	 * @return void
	 */
	private function render_disabled_notice(): void {
		$settings_url = admin_url( 'admin.php?page=wpss-settings&tab=vendor' );
		?>
		<div class="wrap wpss-moderation-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Service Moderation', 'wp-sell-services' ); ?></h1>

			<div class="notice notice-info inline" style="margin-top: 20px;">
				<p>
					<strong><?php esc_html_e( 'Service moderation is currently disabled.', 'wp-sell-services' ); ?></strong>
				</p>
				<p>
					<?php esc_html_e( 'When enabled, new services submitted by vendors will require admin approval before they become visible on the marketplace.', 'wp-sell-services' ); ?>
				</p>
				<p>
					<a href="<?php echo esc_url( $settings_url ); ?>" class="button button-primary">
						<?php esc_html_e( 'Enable in Settings', 'wp-sell-services' ); ?>
					</a>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Show admin notice for pending services.
	 *
	 * @return void
	 */
	public function pending_services_notice(): void {
		// Only show when moderation is enabled.
		if ( ! ModerationService::is_enabled() ) {
			return;
		}

		$screen = get_current_screen();

		// Only show on our plugin pages.
		if ( ! $screen || strpos( $screen->id, 'wp-sell-services' ) === false ) {
			return;
		}

		// Skip the moderation page itself.
		if ( 'wp-sell-services_page_wpss-moderation' === $screen->id ) {
			return;
		}

		$pending_count = $this->get_pending_count();

		if ( $pending_count > 0 ) {
			printf(
				'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
				sprintf(
					esc_html(
						/* translators: %d: Number of services pending moderation review. */
						_n(
							'You have %d service pending review.',
							'You have %d services pending review.',
							$pending_count,
							'wp-sell-services'
						)
					),
					esc_html( $pending_count )
				),
				esc_url( admin_url( 'admin.php?page=wpss-moderation' ) ),
				esc_html__( 'Review now', 'wp-sell-services' )
			);
		}
	}

	/**
	 * Intercept publish action for vendors to set pending status.
	 *
	 * @param array $data    Post data.
	 * @param array $postarr Post array.
	 * @return array
	 */
	public function intercept_publish( array $data, array $postarr ): array {
		// Only for services.
		if ( 'wpss_service' !== $data['post_type'] ) {
			return $data;
		}

		// Admins can publish directly.
		if ( current_user_can( 'manage_options' ) ) {
			return $data;
		}

		// If being published, check if moderation is approved.
		if ( 'publish' === $data['post_status'] ) {
			$post_id = $postarr['ID'] ?? 0;

			// For new posts, prevent publish - will be set to pending moderation.
			if ( ! $post_id ) {
				$data['post_status'] = 'pending';
				return $data;
			}

			// For existing posts, check current moderation status.
			$moderation_status = get_post_meta( $post_id, self::META_KEY, true );

			// Only allow publish if moderation status is approved.
			if ( self::STATUS_APPROVED !== $moderation_status ) {
				// Set post status to pending and update moderation meta.
				$data['post_status'] = 'pending';

				// If rejected or new, reset moderation to pending.
				if ( self::STATUS_REJECTED === $moderation_status || ! $moderation_status ) {
					update_post_meta( $post_id, self::META_KEY, self::STATUS_PENDING );
					delete_post_meta( $post_id, self::REJECTION_REASON_KEY );
				}
			}
		}

		return $data;
	}

	/**
	 * Get moderation status for a service.
	 *
	 * @param int $service_id Service ID.
	 * @return string
	 */
	public static function get_status( int $service_id ): string {
		$status = get_post_meta( $service_id, self::META_KEY, true );
		return $status ? $status : self::STATUS_APPROVED;
	}

	/**
	 * Check if a service is approved.
	 *
	 * @param int $service_id Service ID.
	 * @return bool
	 */
	public static function is_approved( int $service_id ): bool {
		return self::get_status( $service_id ) === self::STATUS_APPROVED;
	}
}
