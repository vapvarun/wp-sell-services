<?php
/**
 * Report dialog: one per page, shared by every Report link on it.
 *
 * Opened by a `.wpss-report-link` button, which carries what is being reported
 * (assets/js/frontend.js). Posts to POST wpss/v1/reports, the route the admin
 * Member Reports screen reads from. Until 1.8.0 that route had no entry point
 * on the website (Basecamp 10378022010).
 *
 * Override: {theme}/wp-sell-services/partials/report-modal.php
 *
 * @package WPSellServices
 * @since   1.8.0
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wpss-modal" id="wpss-report-modal" role="dialog" aria-modal="true" aria-labelledby="wpss-report-modal-title">
	<div class="wpss-modal__backdrop"></div>
	<div class="wpss-modal__dialog">
		<div class="wpss-modal__header">
			<h3 id="wpss-report-modal-title" class="wpss-modal__title"><?php esc_html_e( 'Report', 'wp-sell-services' ); ?></h3>
			<button type="button" class="wpss-modal__close" aria-label="<?php esc_attr_e( 'Close', 'wp-sell-services' ); ?>">
				<i data-lucide="x" class="wpss-icon" aria-hidden="true"></i>
			</button>
		</div>
		<form class="wpss-report-form" id="wpss-report-form" data-error="<?php esc_attr_e( 'The report could not be sent. Please try again.', 'wp-sell-services' ); ?>">
			<input type="hidden" name="target_type" value="">
			<input type="hidden" name="target_id" value="">

			<div class="wpss-modal__body">
				<p class="wpss-report-form__intro"><?php esc_html_e( 'Tell the site team what is wrong. They review every report; the person you report is not told who sent it.', 'wp-sell-services' ); ?></p>

				<div class="wpss-form-group">
					<label for="wpss-report-reason" class="wpss-label">
						<?php esc_html_e( 'Reason', 'wp-sell-services' ); ?>
						<span class="wpss-required" aria-hidden="true">*</span>
					</label>
					<select name="reason" id="wpss-report-reason" class="wpss-select" required>
						<option value=""><?php esc_html_e( 'Select a reason', 'wp-sell-services' ); ?></option>
						<?php foreach ( wpss_get_report_reasons() as $wpss_reason_key => $wpss_reason_label ) : ?>
							<option value="<?php echo esc_attr( $wpss_reason_key ); ?>"><?php echo esc_html( $wpss_reason_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="wpss-form-group">
					<label for="wpss-report-details" class="wpss-label"><?php esc_html_e( 'Details (optional)', 'wp-sell-services' ); ?></label>
					<textarea name="details" id="wpss-report-details" class="wpss-textarea" rows="4" maxlength="2000" placeholder="<?php esc_attr_e( 'What happened, and where can the team see it?', 'wp-sell-services' ); ?>"></textarea>
				</div>

				<p class="wpss-report-form__error" role="alert" hidden></p>
			</div>

			<div class="wpss-modal__footer">
				<button type="button" class="wpss-btn wpss-btn--secondary wpss-modal__close-btn"><?php esc_html_e( 'Cancel', 'wp-sell-services' ); ?></button>
				<button type="submit" class="wpss-btn wpss-btn--primary"><?php esc_html_e( 'Send report', 'wp-sell-services' ); ?></button>
			</div>
		</form>
	</div>
</div>
