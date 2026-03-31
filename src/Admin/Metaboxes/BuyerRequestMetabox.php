<?php
/**
 * Buyer Request Metabox
 *
 * Custom metabox for Buyer Request post type.
 *
 * @package WPSellServices\Admin\Metaboxes
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Admin\Metaboxes;

use WPSellServices\PostTypes\BuyerRequestPostType;
use WPSellServices\Services\BuyerRequestService;
use WPSellServices\Services\ProposalService;

defined( 'ABSPATH' ) || exit;

/**
 * BuyerRequestMetabox class.
 *
 * @since 1.0.0
 */
class BuyerRequestMetabox {

	/**
	 * Initialize metabox.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'add_meta_boxes', array( $this, 'register_metaboxes' ) );
		add_action( 'save_post_' . BuyerRequestPostType::POST_TYPE, array( $this, 'save_meta' ), 10, 2 );
	}

	/**
	 * Register metaboxes.
	 *
	 * @return void
	 */
	public function register_metaboxes(): void {
		add_meta_box(
			'wpss_request_details',
			__( 'Request Details', 'wp-sell-services' ),
			array( $this, 'render_details_metabox' ),
			BuyerRequestPostType::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'wpss_request_proposals',
			__( 'Proposals', 'wp-sell-services' ),
			array( $this, 'render_proposals_metabox' ),
			BuyerRequestPostType::POST_TYPE,
			'normal',
			'default'
		);

		add_meta_box(
			'wpss_request_attachments',
			__( 'Attachments', 'wp-sell-services' ),
			array( $this, 'render_attachments_metabox' ),
			BuyerRequestPostType::POST_TYPE,
			'side',
			'default'
		);
	}

	/**
	 * Render request details metabox.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_details_metabox( \WP_Post $post ): void {
		wp_nonce_field( 'wpss_request_meta', 'wpss_request_nonce' );

		$status        = get_post_meta( $post->ID, '_wpss_status', true ) ?: BuyerRequestService::STATUS_OPEN;
		$budget_type   = get_post_meta( $post->ID, '_wpss_budget_type', true ) ?: BuyerRequestService::BUDGET_FIXED;
		$budget_min    = get_post_meta( $post->ID, '_wpss_budget_min', true );
		$budget_max    = get_post_meta( $post->ID, '_wpss_budget_max', true );
		$delivery_days = get_post_meta( $post->ID, '_wpss_delivery_days', true );
		$expires_at    = get_post_meta( $post->ID, '_wpss_expires_at', true );
		$skills        = get_post_meta( $post->ID, '_wpss_skills_required', true ) ?: array();
		?>
		<table class="form-table wpss-metabox-table">
			<tr>
				<th><label for="wpss_status"><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></label></th>
				<td>
					<select id="wpss_status" name="wpss_status">
						<?php foreach ( BuyerRequestService::get_statuses() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status, $key ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="wpss_budget_type"><?php esc_html_e( 'Budget Type', 'wp-sell-services' ); ?></label></th>
				<td>
					<select id="wpss_budget_type" name="wpss_budget_type">
						<?php foreach ( BuyerRequestService::get_budget_types() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $budget_type, $key ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label><?php esc_html_e( 'Budget Amount', 'wp-sell-services' ); ?></label></th>
				<td>
					<label>
						<?php esc_html_e( 'Min:', 'wp-sell-services' ); ?>
						<input type="number" name="wpss_budget_min"
								value="<?php echo esc_attr( $budget_min ); ?>"
								min="0" step="0.01" class="small-text">
					</label>
					<label style="margin-left: 10px;">
						<?php esc_html_e( 'Max:', 'wp-sell-services' ); ?>
						<input type="number" name="wpss_budget_max"
								value="<?php echo esc_attr( $budget_max ); ?>"
								min="0" step="0.01" class="small-text">
					</label>
				</td>
			</tr>
			<tr>
				<th><label for="wpss_delivery_days"><?php esc_html_e( 'Expected Delivery (days)', 'wp-sell-services' ); ?></label></th>
				<td>
					<input type="number" id="wpss_delivery_days" name="wpss_delivery_days"
							value="<?php echo esc_attr( $delivery_days ); ?>"
							min="1" max="365" class="small-text">
				</td>
			</tr>
			<tr>
				<th><label for="wpss_expires_at"><?php esc_html_e( 'Expires On', 'wp-sell-services' ); ?></label></th>
				<td>
					<input type="datetime-local" id="wpss_expires_at" name="wpss_expires_at"
							value="<?php echo esc_attr( $expires_at ? gmdate( 'Y-m-d\TH:i', strtotime( $expires_at ) ) : '' ); ?>">
				</td>
			</tr>
			<tr>
				<th><label for="wpss_skills"><?php esc_html_e( 'Required Skills', 'wp-sell-services' ); ?></label></th>
				<td>
					<textarea id="wpss_skills" name="wpss_skills" rows="3" class="large-text"
								placeholder="<?php esc_attr_e( 'One skill per line', 'wp-sell-services' ); ?>"><?php echo esc_textarea( implode( "\n", $skills ) ); ?></textarea>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render proposals metabox.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_proposals_metabox( \WP_Post $post ): void {
		$proposal_service = new ProposalService();
		$proposals        = $proposal_service->get_by_request( $post->ID );

		if ( empty( $proposals ) ) {
			echo '<p>' . esc_html__( 'No proposals yet.', 'wp-sell-services' ) . '</p>';
			return;
		}

		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Vendor', 'wp-sell-services' ); ?></th>
					<th><?php esc_html_e( 'Price', 'wp-sell-services' ); ?></th>
					<th><?php esc_html_e( 'Delivery', 'wp-sell-services' ); ?></th>
					<th><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></th>
					<th><?php esc_html_e( 'Date', 'wp-sell-services' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $proposals as $proposal ) : ?>
					<?php $vendor = get_userdata( $proposal->vendor_id ); ?>
					<tr>
						<td>
							<?php if ( $vendor ) : ?>
								<a href="<?php echo esc_url( get_edit_user_link( $vendor->ID ) ); ?>">
									<?php echo esc_html( $vendor->display_name ); ?>
								</a>
							<?php else : ?>
								<?php esc_html_e( 'Unknown', 'wp-sell-services' ); ?>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( number_format( (float) ( $proposal->price ?? 0 ), 2 ) ); ?></td>
						<td>
							<?php
							printf(
								/* translators: %d: number of days */
								esc_html( _n( '%d day', '%d days', $proposal->delivery_days, 'wp-sell-services' ) ),
								esc_html( (string) $proposal->delivery_days )
							);
							?>
						</td>
						<td>
							<?php
							$statuses     = ProposalService::get_statuses();
							$status_class = 'wpss-status-badge wpss-status-' . esc_attr( $proposal->status );
							printf(
								'<span class="%s">%s</span>',
								esc_attr( $status_class ),
								esc_html( $statuses[ $proposal->status ] ?? $proposal->status )
							);
							?>
						</td>
						<td><?php echo esc_html( gmdate( 'M j, Y', strtotime( $proposal->created_at ) ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render attachments metabox.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_attachments_metabox( \WP_Post $post ): void {
		$attachments = get_post_meta( $post->ID, '_wpss_attachments', true ) ?: array();
		?>
		<div class="wpss-attachments-wrapper">
			<div id="wpss-request-attachments">
				<?php if ( empty( $attachments ) ) : ?>
					<p><?php esc_html_e( 'No attachments.', 'wp-sell-services' ); ?></p>
				<?php else : ?>
					<ul>
						<?php foreach ( $attachments as $attachment_id ) : ?>
							<?php
							$attachment = get_post( $attachment_id );
							if ( ! $attachment ) {
								continue;
							}
							?>
							<li>
								<?php if ( wp_attachment_is_image( $attachment_id ) ) : ?>
									<?php echo wp_get_attachment_image( $attachment_id, 'thumbnail' ); ?>
								<?php else : ?>
									<a href="<?php echo esc_url( wp_get_attachment_url( $attachment_id ) ); ?>" target="_blank">
										<?php echo esc_html( $attachment->post_title ); ?>
									</a>
								<?php endif; ?>
								<input type="hidden" name="wpss_attachments[]" value="<?php echo esc_attr( (string) $attachment_id ); ?>">
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
			<button type="button" class="button" id="wpss-add-attachment"><?php esc_html_e( 'Add Attachment', 'wp-sell-services' ); ?></button>
		</div>
		<?php
	}

	/**
	 * Save meta fields.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function save_meta( int $post_id, \WP_Post $post ): void {
		// Verify nonce.
		if ( ! isset( $_POST['wpss_request_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['wpss_request_nonce'] ), 'wpss_request_meta' ) ) {
			return;
		}

		// Check autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save status.
		if ( isset( $_POST['wpss_status'] ) ) {
			update_post_meta( $post_id, '_wpss_status', sanitize_key( $_POST['wpss_status'] ) );
		}

		// Save budget type.
		if ( isset( $_POST['wpss_budget_type'] ) ) {
			update_post_meta( $post_id, '_wpss_budget_type', sanitize_key( $_POST['wpss_budget_type'] ) );
		}

		// Save budget amounts (ensure non-negative).
		if ( isset( $_POST['wpss_budget_min'] ) ) {
			update_post_meta( $post_id, '_wpss_budget_min', max( 0, (float) $_POST['wpss_budget_min'] ) );
		}

		if ( isset( $_POST['wpss_budget_max'] ) ) {
			update_post_meta( $post_id, '_wpss_budget_max', max( 0, (float) $_POST['wpss_budget_max'] ) );
		}

		// Save delivery days.
		if ( isset( $_POST['wpss_delivery_days'] ) ) {
			update_post_meta( $post_id, '_wpss_delivery_days', absint( $_POST['wpss_delivery_days'] ) );
		}

		// Save expiry date.
		if ( isset( $_POST['wpss_expires_at'] ) && ! empty( $_POST['wpss_expires_at'] ) ) {
			$expires_at = sanitize_text_field( wp_unslash( $_POST['wpss_expires_at'] ) );
			update_post_meta( $post_id, '_wpss_expires_at', gmdate( 'Y-m-d H:i:s', strtotime( $expires_at ) ) );
		}

		// Save skills.
		if ( isset( $_POST['wpss_skills'] ) ) {
			$skills = array_filter( array_map( 'sanitize_text_field', explode( "\n", sanitize_textarea_field( wp_unslash( $_POST['wpss_skills'] ) ) ) ) );
			update_post_meta( $post_id, '_wpss_skills_required', $skills );
		}

		// Save attachments.
		if ( isset( $_POST['wpss_attachments'] ) && is_array( $_POST['wpss_attachments'] ) ) {
			$attachments = array_map( 'absint', $_POST['wpss_attachments'] );
			$attachments = array_filter( $attachments );
			update_post_meta( $post_id, '_wpss_attachments', $attachments );
		} else {
			delete_post_meta( $post_id, '_wpss_attachments' );
		}

		/**
		 * Fires after buyer request meta is saved.
		 *
		 * @since 1.0.0
		 * @param int      $post_id Post ID.
		 * @param \WP_Post $post    Post object.
		 */
		do_action( 'wpss_buyer_request_meta_saved', $post_id, $post );
	}
}
