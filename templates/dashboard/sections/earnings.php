<?php
/**
 * Dashboard Section: Earnings (vendor only)
 *
 * @package WPSellServices\Templates
 * @since   1.1.0
 *
 * @var int            $user_id        Current user ID.
 * @var VendorService  $vendor_service Vendor service instance.
 * @var bool           $is_vendor      Whether user is a vendor.
 */

use WPSellServices\Services\EarningsService;

defined( 'ABSPATH' ) || exit;

/**
 * Fires before the earnings dashboard section content.
 *
 * @since 1.1.0
 *
 * @param string $section_name Section identifier ('earnings').
 * @param int    $user_id      Current user ID.
 */
do_action( 'wpss_dashboard_section_before', 'earnings', $user_id );

$earnings_service = new EarningsService();
$earnings         = $earnings_service->get_summary( $user_id );
$withdrawals      = $earnings_service->get_withdrawals( $user_id, array( 'limit' => 10 ) );
$methods          = EarningsService::get_withdrawal_methods();
$min_withdrawal   = EarningsService::get_min_withdrawal_amount();
?>

<div class="wpss-section wpss-section--earnings">
	<!-- Earnings Summary Cards -->
	<div class="wpss-stats-grid wpss-stats-grid--4">
		<div class="wpss-stat-card wpss-stat-card--highlight">
			<span class="wpss-stat-card__value"><?php echo esc_html( wpss_format_price( $earnings['available_balance'] ) ); ?></span>
			<span class="wpss-stat-card__label"><?php esc_html_e( 'Available for Withdrawal', 'wp-sell-services' ); ?></span>
		</div>
		<div class="wpss-stat-card">
			<span class="wpss-stat-card__value"><?php echo esc_html( wpss_format_price( $earnings['pending_clearance'] ) ); ?></span>
			<span class="wpss-stat-card__label"><?php esc_html_e( 'Pending Clearance', 'wp-sell-services' ); ?></span>
		</div>
		<div class="wpss-stat-card">
			<span class="wpss-stat-card__value"><?php echo esc_html( wpss_format_price( $earnings['pending_withdrawal'] ) ); ?></span>
			<span class="wpss-stat-card__label"><?php esc_html_e( 'Pending Withdrawal', 'wp-sell-services' ); ?></span>
		</div>
		<div class="wpss-stat-card">
			<span class="wpss-stat-card__value"><?php echo esc_html( wpss_format_price( $earnings['withdrawn'] ) ); ?></span>
			<span class="wpss-stat-card__label"><?php esc_html_e( 'Total Withdrawn', 'wp-sell-services' ); ?></span>
		</div>
	</div>

	<!-- Total Earnings Card -->
	<div class="wpss-stats-grid wpss-stats-grid--2" style="margin-top: 1rem;">
		<div class="wpss-stat-card">
			<span class="wpss-stat-card__value"><?php echo esc_html( wpss_format_price( $earnings['total_earned'] ) ); ?></span>
			<span class="wpss-stat-card__label"><?php esc_html_e( 'Total Earned (All Time)', 'wp-sell-services' ); ?></span>
		</div>
		<div class="wpss-stat-card">
			<span class="wpss-stat-card__value"><?php echo esc_html( $earnings['completed_orders'] ); ?></span>
			<span class="wpss-stat-card__label"><?php esc_html_e( 'Completed Orders', 'wp-sell-services' ); ?></span>
		</div>
	</div>

	<?php
	/**
	 * Fires after earnings summary stats.
	 *
	 * Allows developers to add custom earnings widgets or displays.
	 *
	 * @since 1.1.0
	 *
	 * @param int $user_id Current user ID.
	 */
	do_action( 'wpss_earnings_summary', $user_id );
	?>

	<!-- Withdrawal Request Form -->
	<div class="wpss-earnings__withdrawal" style="margin-top: 2rem;">
		<h3><?php esc_html_e( 'Request Withdrawal', 'wp-sell-services' ); ?></h3>

		<?php if ( $earnings['available_balance'] >= $min_withdrawal ) : ?>
			<form id="wpss-withdrawal-form" class="wpss-form">
				<?php wp_nonce_field( 'wpss_request_withdrawal', 'wpss_withdrawal_nonce' ); ?>

				<div class="wpss-form-row">
					<div class="wpss-form-group">
						<label for="withdrawal_amount"><?php esc_html_e( 'Amount', 'wp-sell-services' ); ?></label>
						<input type="number"
								name="amount"
								id="withdrawal_amount"
								class="wpss-input"
								min="<?php echo esc_attr( $min_withdrawal ); ?>"
								max="<?php echo esc_attr( $earnings['available_balance'] ); ?>"
								step="0.01"
								placeholder="<?php echo esc_attr( wpss_format_price( $min_withdrawal ) ); ?>"
								required>
						<span class="wpss-form-hint">
							<?php
							printf(
								/* translators: 1: minimum amount, 2: maximum available */
								esc_html__( 'Min: %1$s | Max: %2$s', 'wp-sell-services' ),
								esc_html( wpss_format_price( $min_withdrawal ) ),
								esc_html( wpss_format_price( $earnings['available_balance'] ) )
							);
							?>
						</span>
					</div>

					<div class="wpss-form-group">
						<label for="withdrawal_method"><?php esc_html_e( 'Payment Method', 'wp-sell-services' ); ?></label>
						<select name="method" id="withdrawal_method" class="wpss-select" required>
							<option value=""><?php esc_html_e( 'Select method', 'wp-sell-services' ); ?></option>
							<?php foreach ( $methods as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

				<div class="wpss-form-group" id="wpss-payment-details-wrapper" style="display: none;">
					<label for="payment_details"><?php esc_html_e( 'Payment Details', 'wp-sell-services' ); ?></label>
					<textarea name="details"
								id="payment_details"
								class="wpss-textarea"
								rows="3"
								placeholder="<?php esc_attr_e( 'Enter your payment details (e.g., PayPal email, bank account info)', 'wp-sell-services' ); ?>"></textarea>
					<span class="wpss-form-hint" id="wpss-method-hint"></span>
				</div>

				<div class="wpss-form-group">
					<button type="submit" class="wpss-btn wpss-btn--primary" id="wpss-withdrawal-submit">
						<?php esc_html_e( 'Request Withdrawal', 'wp-sell-services' ); ?>
					</button>
				</div>

				<div id="wpss-withdrawal-message" class="wpss-notice" style="display: none;"></div>
			</form>
		<?php else : ?>
			<div class="wpss-notice wpss-notice--info">
				<p>
					<?php
					printf(
						/* translators: %s: minimum withdrawal amount */
						esc_html__( 'You need at least %s in available balance to request a withdrawal.', 'wp-sell-services' ),
						esc_html( wpss_format_price( $min_withdrawal ) )
					);
					?>
				</p>
			</div>
		<?php endif; ?>
	</div>

	<!-- Withdrawal History -->
	<?php if ( ! empty( $withdrawals ) ) : ?>
		<div class="wpss-earnings__history" style="margin-top: 2rem;">
			<h3><?php esc_html_e( 'Withdrawal History', 'wp-sell-services' ); ?></h3>

			<div class="wpss-table-responsive">
				<table class="wpss-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', 'wp-sell-services' ); ?></th>
							<th><?php esc_html_e( 'Amount', 'wp-sell-services' ); ?></th>
							<th><?php esc_html_e( 'Method', 'wp-sell-services' ); ?></th>
							<th><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $withdrawals as $withdrawal ) : ?>
							<tr>
								<td><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $withdrawal['created_at'] ) ) ); ?></td>
								<td><?php echo esc_html( wpss_format_price( $withdrawal['amount'] ) ); ?></td>
								<td><?php echo esc_html( $methods[ $withdrawal['method'] ] ?? ucfirst( $withdrawal['method'] ) ); ?></td>
								<td>
									<?php
									$status_class = 'wpss-badge--' . esc_attr( $withdrawal['status'] );
									$statuses     = EarningsService::get_withdrawal_statuses();
									?>
									<span class="wpss-badge <?php echo esc_attr( $status_class ); ?>">
										<?php echo esc_html( $statuses[ $withdrawal['status'] ] ?? ucfirst( $withdrawal['status'] ) ); ?>
									</span>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	<?php endif; ?>
</div>

<script>
jQuery(function($) {
	var $form = $('#wpss-withdrawal-form');
	var $methodSelect = $('#withdrawal_method');
	var $detailsWrapper = $('#wpss-payment-details-wrapper');
	var $methodHint = $('#wpss-method-hint');
	var $submitBtn = $('#wpss-withdrawal-submit');
	var $message = $('#wpss-withdrawal-message');

	// Method hints
	var methodHints = {
		'paypal': '<?php echo esc_js( __( 'Enter your PayPal email address', 'wp-sell-services' ) ); ?>',
		'bank_transfer': '<?php echo esc_js( __( 'Enter your bank account details (Bank name, Account number, Routing number)', 'wp-sell-services' ) ); ?>'
	};

	// Show/hide payment details based on method selection
	$methodSelect.on('change', function() {
		var method = $(this).val();
		if (method) {
			$detailsWrapper.show();
			$methodHint.text(methodHints[method] || '');
		} else {
			$detailsWrapper.hide();
		}
	});

	// Form submission
	$form.on('submit', function(e) {
		e.preventDefault();

		var amount = $('#withdrawal_amount').val();
		var method = $methodSelect.val();
		var details = $('#payment_details').val();

		if (!amount || !method) {
			showMessage('<?php echo esc_js( __( 'Please fill in all required fields.', 'wp-sell-services' ) ); ?>', 'error');
			return;
		}

		$submitBtn.prop('disabled', true).text('<?php echo esc_js( __( 'Processing...', 'wp-sell-services' ) ); ?>');

		$.ajax({
			url: wpssUnifiedDashboard.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wpss_request_withdrawal',
				wpss_withdrawal_nonce: $form.find('[name="wpss_withdrawal_nonce"]').val(),
				amount: amount,
				method: method,
				details: details
			},
			success: function(response) {
				if (response.success) {
					showMessage(response.data.message, 'success');
					$form[0].reset();
					$detailsWrapper.hide();
					// Reload after success to update balances
					setTimeout(function() {
						location.reload();
					}, 2000);
				} else {
					showMessage(response.data.message || '<?php echo esc_js( __( 'An error occurred.', 'wp-sell-services' ) ); ?>', 'error');
				}
			},
			error: function() {
				showMessage('<?php echo esc_js( __( 'An error occurred. Please try again.', 'wp-sell-services' ) ); ?>', 'error');
			},
			complete: function() {
				$submitBtn.prop('disabled', false).text('<?php echo esc_js( __( 'Request Withdrawal', 'wp-sell-services' ) ); ?>');
			}
		});
	});

	function showMessage(text, type) {
		$message.removeClass('wpss-notice--success wpss-notice--error wpss-notice--info')
			.addClass('wpss-notice--' + type)
			.html('<p>' + text + '</p>')
			.show();
	}
});
</script>

<?php
/**
 * Fires after the earnings dashboard section content.
 *
 * @since 1.1.0
 *
 * @param string $section_name Section identifier ('earnings').
 * @param int    $user_id      Current user ID.
 */
do_action( 'wpss_dashboard_section_after', 'earnings', $user_id );
?>
