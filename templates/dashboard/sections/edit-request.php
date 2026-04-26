<?php
/**
 * Dashboard Section: Edit Buyer Request
 *
 * Allows users to edit their existing buyer requests.
 *
 * @package WPSellServices\Templates
 * @since   1.1.0
 *
 * @var int            $user_id        Current user ID.
 * @var VendorService  $vendor_service Vendor service instance.
 * @var bool           $is_vendor      Whether user is a vendor.
 */

defined( 'ABSPATH' ) || exit;

// Get request ID from query string.
$request_id = absint( $_GET['request_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

if ( ! $request_id ) {
	echo '<div class="wpss-empty-state"><h3>' . esc_html__( 'Request not found.', 'wp-sell-services' ) . '</h3>';
	echo '<a href="' . esc_url( add_query_arg( 'section', 'requests', get_permalink() ) ) . '" class="wpss-btn wpss-btn--primary">' . esc_html__( 'Back to Requests', 'wp-sell-services' ) . '</a></div>';
	return;
}

// Verify the request exists and belongs to the current user.
$request = get_post( $request_id );

if ( ! $request || 'wpss_request' !== $request->post_type || (int) $request->post_author !== $user_id ) {
	echo '<div class="wpss-empty-state"><h3>' . esc_html__( 'You do not have permission to edit this request.', 'wp-sell-services' ) . '</h3>';
	echo '<a href="' . esc_url( add_query_arg( 'section', 'requests', get_permalink() ) ) . '" class="wpss-btn wpss-btn--primary">' . esc_html__( 'Back to Requests', 'wp-sell-services' ) . '</a></div>';
	return;
}

// Load existing data.
$budget_min = get_post_meta( $request_id, '_wpss_budget_min', true );
$budget_max = get_post_meta( $request_id, '_wpss_budget_max', true );
// Support both the old single _wpss_budget key and the new min/max keys.
if ( ! $budget_min && ! $budget_max ) {
	$budget_single = get_post_meta( $request_id, '_wpss_budget', true );
	if ( $budget_single ) {
		$budget_max = $budget_single;
	}
}
// Get deadline from expires_at meta field and format for HTML date input
$expires_at = get_post_meta( $request_id, '_wpss_expires_at', true );
$deadline   = '';
if ( $expires_at ) {
	$deadline_date = new DateTime( $expires_at );
	$deadline      = $deadline_date->format( 'Y-m-d' );
}
$skills_required = get_post_meta( $request_id, '_wpss_skills_required', true );
$skills_string   = is_array( $skills_required ) ? implode( ', ', $skills_required ) : ( $skills_required ?: '' );
$current_status  = get_post_status( $request_id );

// Get categories for the dropdown.
$categories = get_terms(
	array(
		'taxonomy'   => 'wpss_service_category',
		'hide_empty' => false,
	)
);

$current_categories = wp_get_object_terms( $request_id, 'wpss_service_category', array( 'fields' => 'ids' ) );
$current_cat_id     = ! empty( $current_categories ) ? $current_categories[0] : 0;

/**
 * Fires before the edit request dashboard section content.
 *
 * @since 1.1.0
 *
 * @param string $section_name Section identifier ('edit_request').
 * @param int    $user_id      Current user ID.
 */
do_action( 'wpss_dashboard_section_before', 'edit_request', $user_id );
?>

<div class="wpss-request-wizard">
	<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
		<h2 style="margin: 0;"><?php esc_html_e( 'Edit Request', 'wp-sell-services' ); ?></h2>
		<a href="<?php echo esc_url( add_query_arg( 'section', 'requests', get_permalink() ) ); ?>" class="wpss-btn wpss-btn--outline wpss-btn--sm">
			<?php esc_html_e( 'Back to Requests', 'wp-sell-services' ); ?>
		</a>
	</div>

	<form id="wpss-edit-request-form" class="wpss-profile-form" data-ajax-form="edit-request">
		<?php wp_nonce_field( 'wpss_edit_request', 'wpss_request_nonce' ); ?>
		<input type="hidden" name="request_id" value="<?php echo esc_attr( $request_id ); ?>">

		<!-- Request Details Section -->
		<div class="wpss-profile-form__section">
			<h3><?php esc_html_e( 'Request Details', 'wp-sell-services' ); ?></h3>

			<div class="wpss-form-row">
				<label for="request_title">
					<?php esc_html_e( 'Title', 'wp-sell-services' ); ?>
					<span class="wpss-required">*</span>
				</label>
				<input
					type="text"
					name="title"
					id="request_title"
					class="wpss-input"
					required
					maxlength="100"
					value="<?php echo esc_attr( $request->post_title ); ?>"
				>
			</div>

			<div class="wpss-form-row">
				<label for="request_description">
					<?php esc_html_e( 'Description', 'wp-sell-services' ); ?>
					<span class="wpss-required">*</span>
				</label>
				<textarea
					name="description"
					id="request_description"
					class="wpss-textarea"
					rows="6"
					required
				><?php echo esc_textarea( $request->post_content ); ?></textarea>
			</div>

			<div class="wpss-form-row">
				<label for="request_category"><?php esc_html_e( 'Category', 'wp-sell-services' ); ?></label>
				<select name="category" id="request_category" class="wpss-input">
					<option value=""><?php esc_html_e( 'Select a category (optional)', 'wp-sell-services' ); ?></option>
					<?php if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) : ?>
						<?php foreach ( $categories as $category ) : ?>
							<option value="<?php echo esc_attr( $category->term_id ); ?>" <?php selected( $current_cat_id, $category->term_id ); ?>>
								<?php echo esc_html( $category->name ); ?>
							</option>
						<?php endforeach; ?>
					<?php endif; ?>
				</select>
			</div>

			<div class="wpss-form-row">
				<label for="request_post_status"><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></label>
				<select name="post_status" id="request_post_status" class="wpss-input">
					<option value="publish" <?php selected( $current_status, 'publish' ); ?>><?php esc_html_e( 'Published', 'wp-sell-services' ); ?></option>
					<option value="draft" <?php selected( $current_status, 'draft' ); ?>><?php esc_html_e( 'Draft', 'wp-sell-services' ); ?></option>
				</select>
			</div>
		</div>

		<!-- Budget & Timeline Section -->
		<div class="wpss-profile-form__section">
			<h3><?php esc_html_e( 'Budget & Timeline', 'wp-sell-services' ); ?></h3>

			<div class="wpss-form-row wpss-form-row--half">
				<div>
					<label for="request_budget_min"><?php esc_html_e( 'Minimum Budget', 'wp-sell-services' ); ?></label>
					<div class="wpss-input-group">
						<span class="wpss-input-group__prefix"><?php echo esc_html( wpss_get_currency_symbol() ); ?></span>
						<input
							type="number"
							name="budget_min"
							id="request_budget_min"
							class="wpss-input"
							min="0"
							step="1"
							value="<?php echo esc_attr( $budget_min ); ?>"
						>
					</div>
				</div>
				<div>
					<label for="request_budget_max"><?php esc_html_e( 'Maximum Budget', 'wp-sell-services' ); ?></label>
					<div class="wpss-input-group">
						<span class="wpss-input-group__prefix"><?php echo esc_html( wpss_get_currency_symbol() ); ?></span>
						<input
							type="number"
							name="budget_max"
							id="request_budget_max"
							class="wpss-input"
							min="0"
							step="1"
							value="<?php echo esc_attr( $budget_max ); ?>"
						>
					</div>
				</div>
			</div>

			<div class="wpss-form-row">
				<label for="request_deadline"><?php esc_html_e( 'Deadline', 'wp-sell-services' ); ?></label>
				<input
					type="date"
					name="deadline"
					id="request_deadline"
					class="wpss-input"
					value="<?php echo esc_attr( $deadline ); ?>"
				>
			</div>

			<div class="wpss-form-row">
				<label for="request_skills"><?php esc_html_e( 'Required Skills', 'wp-sell-services' ); ?></label>
				<input
					type="text"
					name="skills_required"
					id="request_skills"
					class="wpss-input"
					value="<?php echo esc_attr( $skills_string ); ?>"
					placeholder="<?php esc_attr_e( 'e.g., WordPress, PHP, JavaScript (comma-separated)', 'wp-sell-services' ); ?>"
				>
			</div>
		</div>

		<!-- Submit Section -->
		<div class="wpss-profile-form__actions">
			<button type="submit" class="wpss-btn wpss-btn--primary wpss-btn--lg">
				<i data-lucide="save" class="wpss-icon" aria-hidden="true"></i>
				<?php esc_html_e( 'Update Request', 'wp-sell-services' ); ?>
			</button>
		</div>
	</form>
</div>

<script>
function wpssShowNotice(msg, type) {
	type = type || 'error';
	var bgColor = type === 'success' ? '#d4edda' : '#f8d7da';
	var borderColor = type === 'success' ? '#c3e6cb' : '#f5c6cb';
	var textColor = type === 'success' ? '#155724' : '#721c24';
	var $notice = jQuery('<div class="wpss-inline-notice" style="padding:12px 16px;margin:10px 0;border:1px solid ' + borderColor + ';border-radius:4px;background:' + bgColor + ';color:' + textColor + ';position:relative;">' + msg + '<span style="position:absolute;right:10px;top:8px;cursor:pointer;font-size:18px;line-height:1;">&times;</span></div>');
	$notice.find('span').on('click', function() { $notice.fadeOut(200, function() { $notice.remove(); }); });
	jQuery('#wpss-edit-request-form, .wpss-dashboard').first().before($notice);
	setTimeout(function() { $notice.fadeOut(400, function() { $notice.remove(); }); }, 8000);
}
(function($) {
	'use strict';

	$('#wpss-edit-request-form').on('submit', function(e) {
		e.preventDefault();

		var $form = $(this);
		var $button = $form.find('button[type="submit"]');
		var originalHtml = $button.html();

		// Validate budget.
		var minBudget = parseFloat($('#request_budget_min').val()) || 0;
		var maxBudget = parseFloat($('#request_budget_max').val()) || 0;

		if (maxBudget > 0 && minBudget > maxBudget) {
			wpssShowNotice('<?php echo esc_js( __( 'Minimum budget cannot be greater than maximum budget.', 'wp-sell-services' ) ); ?>', 'error');
			return;
		}

		$button
			.prop('disabled', true)
			.html('<span class="wpss-spinner"></span> <?php echo esc_js( __( 'Updating...', 'wp-sell-services' ) ); ?>');

		$.ajax({
			url: wpssUnifiedDashboard.ajaxUrl,
			type: 'POST',
			data: $form.serialize() + '&action=wpss_update_request',
			success: function(response) {
				if (response.success) {
					$form.html(
						'<div class="wpss-empty-state wpss-empty-state--compact">' +
							'<div class="wpss-empty-state__icon" style="color: var(--wpss-success);">' +
								'<i data-lucide="check-circle-2" class="wpss-icon wpss-icon--lg" aria-hidden="true"></i>' +
							'</div>' +
							'<h3><?php echo esc_js( __( 'Request Updated!', 'wp-sell-services' ) ); ?></h3>' +
							'<a href="?section=requests" class="wpss-btn wpss-btn--primary">' +
								'<?php echo esc_js( __( 'View My Requests', 'wp-sell-services' ) ); ?>' +
							'</a>' +
						'</div>'
					);
				} else {
					wpssShowNotice(response.data.message || '<?php echo esc_js( __( 'An error occurred.', 'wp-sell-services' ) ); ?>', 'error');
					$button
						.prop('disabled', false)
						.html(originalHtml);
				}
			},
			error: function() {
				wpssShowNotice('<?php echo esc_js( __( 'An error occurred. Please try again.', 'wp-sell-services' ) ); ?>', 'error');
				$button
					.prop('disabled', false)
					.html(originalHtml);
			}
		});
	});
})(jQuery);
</script>

<?php
/**
 * Fires after the edit request dashboard section content.
 *
 * @since 1.1.0
 *
 * @param string $section_name Section identifier ('edit_request').
 * @param int    $user_id      Current user ID.
 */
do_action( 'wpss_dashboard_section_after', 'edit_request', $user_id );
?>
