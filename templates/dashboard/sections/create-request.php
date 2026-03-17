<?php
/**
 * Dashboard Section: Create Buyer Request
 *
 * Styled buyer request form matching the unified dashboard design.
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
 * Fires before the create request dashboard section content.
 *
 * @since 1.1.0
 *
 * @param string $section_name Section identifier ('create_request').
 * @param int    $user_id      Current user ID.
 */
do_action( 'wpss_dashboard_section_before', 'create_request', $user_id );

// Get categories for the dropdown.
$categories = get_terms(
	array(
		'taxonomy'   => 'wpss_service_category',
		'hide_empty' => false,
	)
);
?>

<div class="wpss-request-wizard">
	<form id="wpss-post-request-form" class="wpss-profile-form" data-ajax-form="post-request">
		<?php wp_nonce_field( 'wpss_post_request', 'wpss_request_nonce' ); ?>

		<!-- Request Details Section -->
		<div class="wpss-profile-form__section">
			<h3><?php esc_html_e( 'What do you need?', 'wp-sell-services' ); ?></h3>

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
					placeholder="<?php esc_attr_e( 'e.g., I need a WordPress website designed', 'wp-sell-services' ); ?>"
				>
				<p class="wpss-form-hint"><?php esc_html_e( 'Be specific about what you need', 'wp-sell-services' ); ?></p>
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
					placeholder="<?php esc_attr_e( 'Describe your project in detail. Include any specific requirements, preferences, or examples...', 'wp-sell-services' ); ?>"
				></textarea>
				<p class="wpss-form-hint"><?php esc_html_e( 'The more details you provide, the better proposals you\'ll receive', 'wp-sell-services' ); ?></p>
			</div>

			<div class="wpss-form-row">
				<label for="request_category"><?php esc_html_e( 'Category', 'wp-sell-services' ); ?></label>
				<select name="category" id="request_category" class="wpss-input">
					<option value=""><?php esc_html_e( 'Select a category (optional)', 'wp-sell-services' ); ?></option>
					<?php if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) : ?>
						<?php foreach ( $categories as $category ) : ?>
							<option value="<?php echo esc_attr( $category->term_id ); ?>">
								<?php echo esc_html( $category->name ); ?>
							</option>
						<?php endforeach; ?>
					<?php endif; ?>
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
							placeholder="0"
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
							placeholder="0"
						>
					</div>
				</div>
			</div>
			<p class="wpss-form-hint"><?php esc_html_e( 'Leave empty if you\'re flexible on budget', 'wp-sell-services' ); ?></p>

			<div class="wpss-form-row">
				<label for="request_deadline"><?php esc_html_e( 'Deadline', 'wp-sell-services' ); ?></label>
				<input
					type="date"
					name="deadline"
					id="request_deadline"
					class="wpss-input"
					min="<?php echo esc_attr( wp_date( 'Y-m-d', strtotime( '+1 day' ) ) ); ?>"
				>
				<p class="wpss-form-hint"><?php esc_html_e( 'When do you need this completed?', 'wp-sell-services' ); ?></p>
			</div>

			<div class="wpss-form-row">
				<label for="request_skills"><?php esc_html_e( 'Required Skills', 'wp-sell-services' ); ?></label>
				<input
					type="text"
					name="skills_required"
					id="request_skills"
					class="wpss-input"
					placeholder="<?php esc_attr_e( 'e.g., WordPress, PHP, JavaScript (comma-separated)', 'wp-sell-services' ); ?>"
				>
				<p class="wpss-form-hint"><?php esc_html_e( 'Separate multiple skills with commas.', 'wp-sell-services' ); ?></p>
			</div>
		</div>

		<!-- Submit Section -->
		<div class="wpss-profile-form__actions">
			<button type="submit" class="wpss-btn wpss-btn--primary wpss-btn--lg">
				<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<path d="m3 11 18-5v12L3 13v-2z"/>
				</svg>
				<?php esc_html_e( 'Post Request', 'wp-sell-services' ); ?>
			</button>
			<p class="wpss-form-hint wpss-form-hint--center">
				<?php esc_html_e( 'Your request will be visible to all sellers who can then send you proposals.', 'wp-sell-services' ); ?>
			</p>
		</div>
	</form>
</div>

<style>
/* Request Wizard Styles */
.wpss-request-wizard {
	max-width: 720px;
}

.wpss-required {
	color: var(--wpss-danger);
}

.wpss-input-group {
	display: flex;
	align-items: stretch;
}

.wpss-input-group__prefix {
	display: flex;
	align-items: center;
	padding: 0 12px;
	background: var(--wpss-gray-100);
	border: 1px solid var(--wpss-gray-300);
	border-right: none;
	border-radius: var(--wpss-radius) 0 0 var(--wpss-radius);
	font-size: 14px;
	color: var(--wpss-gray-500);
}

.wpss-input-group .wpss-input {
	border-radius: 0 var(--wpss-radius) var(--wpss-radius) 0;
	flex: 1;
}

.wpss-btn--lg {
	padding: 12px 24px;
	font-size: 15px;
}

.wpss-btn svg {
	flex-shrink: 0;
}

.wpss-profile-form__actions {
	text-align: center;
}

.wpss-form-hint--center {
	text-align: center;
	margin-top: 12px;
}
</style>

<script>
(function($) {
	'use strict';

	$('#wpss-post-request-form').on('submit', function(e) {
		e.preventDefault();

		var $form = $(this);
		var $button = $form.find('button[type="submit"]');
		var originalHtml = $button.html();

		// Validate budget
		var minBudget = parseFloat($('#request_budget_min').val()) || 0;
		var maxBudget = parseFloat($('#request_budget_max').val()) || 0;

		if (maxBudget > 0 && minBudget > maxBudget) {
			alert('<?php echo esc_js( __( 'Minimum budget cannot be greater than maximum budget.', 'wp-sell-services' ) ); ?>');
			return;
		}

		$button
			.prop('disabled', true)
			.html('<span class="wpss-spinner"></span> <?php echo esc_js( __( 'Posting...', 'wp-sell-services' ) ); ?>');

		$.ajax({
			url: wpssUnifiedDashboard.ajaxUrl,
			type: 'POST',
			data: $form.serialize() + '&action=wpss_post_request',
			success: function(response) {
				if (response.success) {
					// Show success message
					$form.html(
						'<div class="wpss-empty-state wpss-empty-state--compact">' +
							'<div class="wpss-empty-state__icon" style="color: var(--wpss-success);">' +
								'<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>' +
							'</div>' +
							'<h3><?php echo esc_js( __( 'Request Posted!', 'wp-sell-services' ) ); ?></h3>' +
							'<p><?php echo esc_js( __( 'Your request is now live. Sellers will be able to send you proposals.', 'wp-sell-services' ) ); ?></p>' +
							'<a href="' + (response.data.redirect || '?section=requests') + '" class="wpss-btn wpss-btn--primary">' +
								'<?php echo esc_js( __( 'View My Requests', 'wp-sell-services' ) ); ?>' +
							'</a>' +
						'</div>'
					);
				} else {
					alert(response.data.message || '<?php echo esc_js( __( 'An error occurred.', 'wp-sell-services' ) ); ?>');
					$button
						.prop('disabled', false)
						.html(originalHtml);
				}
			},
			error: function() {
				alert('<?php echo esc_js( __( 'An error occurred. Please try again.', 'wp-sell-services' ) ); ?>');
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
 * Fires after the create request dashboard section content.
 *
 * @since 1.1.0
 *
 * @param string $section_name Section identifier ('create_request').
 * @param int    $user_id      Current user ID.
 */
do_action( 'wpss_dashboard_section_after', 'create_request', $user_id );
?>
