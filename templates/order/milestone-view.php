<?php
/**
 * Milestone Sub-Order View
 *
 * Rendered when the current order in the dashboard context has
 * platform='milestone'. Milestones are phases of a parent service order,
 * not standalone deliveries, so this view shows a phase receipt with the
 * lifecycle-appropriate action for the current viewer:
 *
 *   - Buyer, pending_payment    → Accept & Pay / Decline
 *   - Vendor, pending_payment   → Cancel the proposal
 *   - Vendor, in_progress       → Submit Delivery
 *   - Buyer, pending_approval   → Approve / Request revision in chat
 *   - Either, completed         → Read-only receipt with commission split
 *
 * @package WPSellServices\Templates
 * @since   1.1.0
 *
 * @var \WPSellServices\Models\ServiceOrder $current_order Milestone sub-order row.
 * @var int                                 $user_id       Current user ID.
 */

defined( 'ABSPATH' ) || exit;

$is_buyer  = (int) $current_order->customer_id === $user_id;
$is_vendor = (int) $current_order->vendor_id === $user_id;

$currency   = $current_order->currency ?: ( get_option( 'wpss_general', array() )['currency'] ?? 'USD' );
$gross      = (float) $current_order->total;
$net_vendor = (float) ( $current_order->vendor_earnings ?? $gross );
$platform_f = (float) ( $current_order->platform_fee ?? 0 );
$parent_id  = (int) ( $current_order->platform_order_id ?? 0 );
$parent_url = $parent_id ? add_query_arg( 'order_id', $parent_id, remove_query_arg( 'order_id' ) ) : '';

$meta         = $current_order->meta ?? '';
$meta         = is_string( $meta ) ? json_decode( $meta, true ) : ( is_array( $meta ) ? $meta : array() );
$phase_title  = (string) ( $meta['title'] ?? '' );
$description  = (string) ( $meta['description'] ?? ( $current_order->vendor_notes ?? '' ) );
$deliverables = (string) ( $meta['deliverables'] ?? '' );
$submit_note  = (string) ( $meta['submit_note'] ?? '' );

$status       = (string) $current_order->status;
$is_unpaid    = 'pending_payment' === $status;
$is_working   = 'in_progress' === $status;
$is_submitted = 'pending_approval' === $status;
$is_completed = 'completed' === $status;
$is_cancelled = 'cancelled' === $status;

$base_url = function_exists( 'wpss_get_checkout_base_url' ) ? wpss_get_checkout_base_url() : home_url( '/checkout/' );
$pay_url  = add_query_arg( 'pay_order', (int) $current_order->id, $base_url );

$counterparty_id = $is_buyer ? (int) $current_order->vendor_id : (int) $current_order->customer_id;
$counterparty    = get_userdata( $counterparty_id );

$format = static function ( float $amount ) use ( $currency ): string {
	return function_exists( 'wpss_format_price' )
		? wpss_format_price( $amount, $currency )
		: number_format_i18n( $amount, 2 ) . ' ' . $currency;
};
?>

<div class="wpss-tip-view wpss-milestone-view">
	<?php
	// CB6 (plans/ORDER-FLOW-AUDIT.md): top breadcrumb to parent service order.
	if ( $parent_id ) :
		$parent_order = \WPSellServices\Models\ServiceOrder::find( $parent_id );
		if ( $parent_order ) :
			?>
			<div class="wpss-suborder-crumb">
				<i data-lucide="corner-down-right" class="wpss-icon" aria-hidden="true"></i>
				<?php
				printf(
					/* translators: %s: parent order number link */
					esc_html__( 'Milestone phase on order %s', 'wp-sell-services' ),
					'<a href="' . esc_url( $parent_url ) . '">#' . esc_html( $parent_order->order_number ) . '</a>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- URL + label escaped above.
				);
				?>
			</div>
			<?php
		endif;
	endif;
	?>
	<div class="wpss-tip-view__card">
		<div class="wpss-tip-view__icon wpss-milestone-view__icon" aria-hidden="true">
			<i data-lucide="flag" class="wpss-icon wpss-icon--lg"></i>
		</div>

		<h2 class="wpss-tip-view__title">
			<?php
			if ( $is_vendor ) {
				if ( $is_completed ) {
					esc_html_e( 'Phase approved', 'wp-sell-services' );
				} elseif ( $is_submitted ) {
					esc_html_e( 'Delivery submitted — awaiting buyer', 'wp-sell-services' );
				} elseif ( $is_working ) {
					esc_html_e( 'Paid — ready for delivery', 'wp-sell-services' );
				} elseif ( $is_unpaid ) {
					esc_html_e( 'Proposal sent — awaiting buyer payment', 'wp-sell-services' );
				} elseif ( $is_cancelled ) {
					// Cancelled reason differs by actor — you can re-propose after a buyer-decline
					// but not after an admin/system cancellation, so hinting the path here removes
					// the "what now?" uncertainty.
					esc_html_e( 'Phase cancelled by seller — you can propose a revised phase any time', 'wp-sell-services' );
				}
			} elseif ( $is_buyer ) {
				if ( $is_completed ) {
					esc_html_e( 'Phase approved', 'wp-sell-services' );
				} elseif ( $is_submitted ) {
					esc_html_e( 'Delivery ready for your review', 'wp-sell-services' );
				} elseif ( $is_working ) {
					esc_html_e( 'Paid — seller working', 'wp-sell-services' );
				} elseif ( $is_unpaid ) {
					esc_html_e( 'New phase from your seller', 'wp-sell-services' );
				} elseif ( $is_cancelled ) {
					esc_html_e( 'Phase cancelled — your seller can send a revised one', 'wp-sell-services' );
				}
			} else {
				esc_html_e( 'Phase', 'wp-sell-services' );
			}
			?>
		</h2>

		<?php if ( '' !== $phase_title ) : ?>
			<p class="wpss-milestone-view__phase-title"><?php echo esc_html( $phase_title ); ?></p>
		<?php endif; ?>

		<p class="wpss-tip-view__amount">
			<?php
			if ( $is_vendor && ( $is_completed || $is_submitted || $is_working ) ) {
				echo esc_html( $format( $net_vendor ) );
			} else {
				echo esc_html( $format( $gross ) );
			}
			?>
		</p>

		<dl class="wpss-tip-view__meta">
			<?php if ( $counterparty ) : ?>
				<div>
					<dt><?php echo esc_html( $is_vendor ? __( 'From', 'wp-sell-services' ) : __( 'Seller', 'wp-sell-services' ) ); ?></dt>
					<dd><?php echo esc_html( $counterparty->display_name ); ?></dd>
				</div>
			<?php endif; ?>

			<div>
				<dt><?php esc_html_e( 'Phase order #', 'wp-sell-services' ); ?></dt>
				<dd><?php echo esc_html( $current_order->order_number ); ?></dd>
			</div>

			<?php if ( $is_vendor && $platform_f > 0 && ( $is_completed || $is_submitted || $is_working ) ) : ?>
				<div>
					<dt><?php esc_html_e( 'Buyer paid', 'wp-sell-services' ); ?></dt>
					<dd><?php echo esc_html( $format( $gross ) ); ?></dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'Platform fee', 'wp-sell-services' ); ?></dt>
					<dd>&minus;<?php echo esc_html( $format( $platform_f ) ); ?></dd>
				</div>
			<?php endif; ?>

			<?php if ( '' !== $description ) : ?>
				<div class="wpss-tip-view__message">
					<dt><?php esc_html_e( 'Description', 'wp-sell-services' ); ?></dt>
					<dd><?php echo esc_html( $description ); ?></dd>
				</div>
			<?php endif; ?>

			<?php if ( '' !== $deliverables ) : ?>
				<div class="wpss-tip-view__message">
					<dt><?php esc_html_e( 'Deliverables', 'wp-sell-services' ); ?></dt>
					<dd><?php echo esc_html( $deliverables ); ?></dd>
				</div>
			<?php endif; ?>

			<?php if ( '' !== $submit_note && ( $is_submitted || $is_completed ) ) : ?>
				<div class="wpss-tip-view__message">
					<dt><?php esc_html_e( 'Delivery note', 'wp-sell-services' ); ?></dt>
					<dd><?php echo esc_html( $submit_note ); ?></dd>
				</div>
			<?php endif; ?>
		</dl>

		<div class="wpss-tip-view__actions">
			<?php if ( $is_buyer && $is_unpaid ) : ?>
				<a href="<?php echo esc_url( $pay_url ); ?>" class="wpss-btn wpss-btn--primary">
					<?php
					printf(
						/* translators: %s: gross amount */
						esc_html__( 'Accept & Pay %s', 'wp-sell-services' ),
						esc_html( $format( $gross ) )
					);
					?>
				</a>
				<button type="button" class="wpss-btn wpss-btn--secondary wpss-milestone-decline-btn"
					data-milestone="<?php echo esc_attr( (int) $current_order->id ); ?>">
					<?php esc_html_e( 'Decline', 'wp-sell-services' ); ?>
				</button>
			<?php endif; ?>

			<?php if ( $is_vendor && $is_unpaid ) : ?>
				<button type="button" class="wpss-btn wpss-btn--secondary wpss-milestone-delete-btn"
					data-milestone="<?php echo esc_attr( (int) $current_order->id ); ?>">
					<?php esc_html_e( 'Cancel proposal', 'wp-sell-services' ); ?>
				</button>
			<?php endif; ?>

			<?php if ( $is_vendor && ( $is_working || $is_submitted ) ) : ?>
				<button type="button" class="wpss-btn wpss-btn--primary wpss-milestone-submit-btn"
					data-milestone="<?php echo esc_attr( (int) $current_order->id ); ?>">
					<?php echo esc_html( $is_submitted ? __( 'Resubmit delivery', 'wp-sell-services' ) : __( 'Submit delivery', 'wp-sell-services' ) ); ?>
				</button>
			<?php endif; ?>

			<?php if ( $is_buyer && $is_submitted ) : ?>
				<button type="button" class="wpss-btn wpss-btn--primary wpss-milestone-approve-btn"
					data-milestone="<?php echo esc_attr( (int) $current_order->id ); ?>">
					<?php esc_html_e( 'Approve delivery', 'wp-sell-services' ); ?>
				</button>
				<?php
				// Revision request is a chat thread interaction by design —
				// per the product decision, milestones don't have a separate
				// reject status. This button routes the buyer back to the
				// parent order's conversation panel with the phase title
				// pre-loaded via a hash + localStorage hand-off, so the
				// seller can see exactly which phase is being discussed
				// without the buyer having to hunt for the chat.
				// Anchor to the composer textarea so the browser scrolls
				// straight to where the buyer needs to type. #wpss-message-input
				// is rendered by conversation.php inside the parent order view.
				$revision_url = $parent_url ? $parent_url . '#wpss-message-input' : '';
				if ( $revision_url ) :
					?>
					<a href="<?php echo esc_url( $revision_url ); ?>"
						class="wpss-btn wpss-btn--outline wpss-milestone-revision-link"
						data-phase-title="<?php echo esc_attr( $phase_title ); ?>">
						<?php esc_html_e( 'Request revision in chat', 'wp-sell-services' ); ?>
					</a>
				<?php endif; ?>
			<?php endif; ?>

			<?php if ( $parent_url ) : ?>
				<a href="<?php echo esc_url( $parent_url ); ?>" class="wpss-btn wpss-btn--outline">
					<?php esc_html_e( 'View original order', 'wp-sell-services' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>
</div>

<?php if ( $is_vendor && ( $is_working || $is_submitted ) ) : ?>
	<!-- Submit delivery modal (vendor only) -->
	<div class="wpss-modal wpss-extension-modal" id="wpss-milestone-submit-modal" role="dialog" aria-modal="true" aria-labelledby="wpss-ms-submit-title" hidden>
		<div class="wpss-modal__backdrop"></div>
		<div class="wpss-modal__dialog">
			<div class="wpss-modal__header">
				<h3 id="wpss-ms-submit-title" class="wpss-modal__title"><?php esc_html_e( 'Submit delivery', 'wp-sell-services' ); ?></h3>
				<button type="button" class="wpss-modal__close" aria-label="<?php esc_attr_e( 'Close', 'wp-sell-services' ); ?>">
					<i data-lucide="x" class="wpss-icon" aria-hidden="true"></i>
				</button>
			</div>
			<div class="wpss-modal__body">
				<p class="wpss-modal__intro"><?php esc_html_e( 'Tell the buyer what you delivered. They will review and approve, or ask for changes in chat.', 'wp-sell-services' ); ?></p>
				<form class="wpss-milestone-submit-form" data-milestone="<?php echo esc_attr( (int) $current_order->id ); ?>">
					<div class="wpss-form-row">
						<label for="wpss-ms-note"><?php esc_html_e( 'Delivery note', 'wp-sell-services' ); ?></label>
						<textarea id="wpss-ms-note" name="note" rows="4" class="wpss-textarea" placeholder="<?php esc_attr_e( 'e.g. 3 concept directions + source files, delivered in Figma + PDF export.', 'wp-sell-services' ); ?>"></textarea>
					</div>
					<div class="wpss-modal__feedback" role="status" aria-live="polite" hidden></div>
					<div class="wpss-modal__footer">
						<button type="button" class="wpss-btn wpss-btn--secondary wpss-modal__cancel"><?php esc_html_e( 'Cancel', 'wp-sell-services' ); ?></button>
						<button type="submit" class="wpss-btn wpss-btn--primary"><?php esc_html_e( 'Submit delivery', 'wp-sell-services' ); ?></button>
					</div>
				</form>
			</div>
		</div>
	</div>
<?php endif; ?>

<script>
(function () {
	var ajaxurl = window.ajaxurl || '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
	var actionNonce = '<?php echo esc_js( wp_create_nonce( 'wpss_milestone_action' ) ); ?>';

	function post(action, payload) {
		var data = new FormData();
		data.append('action', action);
		data.append('_ajax_nonce', actionNonce);
		Object.keys(payload).forEach(function (k) { data.append(k, payload[k]); });
		return fetch(ajaxurl, { method: 'POST', credentials: 'include', body: data }).then(function (r) { return r.json(); });
	}

	// Stash the phase title so the parent order-view can prefill the
	// chat box when the buyer lands on it — keeps the reference crisp
	// without cluttering the URL with long query strings.
	document.querySelectorAll('.wpss-milestone-revision-link').forEach(function (btn) {
		btn.addEventListener('click', function () {
			try {
				sessionStorage.setItem('wpss_revision_prefill', btn.dataset.phaseTitle || '');
			} catch (e) { /* storage disabled — silently continue, link still navigates */ }
		});
	});

	document.querySelectorAll('.wpss-milestone-approve-btn').forEach(function (btn) {
		btn.addEventListener('click', function () {
			if (!confirm('<?php echo esc_js( __( 'Approve delivery of this phase? This marks it as complete and cannot be undone.', 'wp-sell-services' ) ); ?>')) return;
			btn.disabled = true;
			post('wpss_approve_milestone', { milestone_id: btn.dataset.milestone }).then(function (res) {
				if (res && res.success) window.location.reload();
				else { btn.disabled = false; alert((res && res.data && res.data.message) || 'Error'); }
			});
		});
	});

	document.querySelectorAll('.wpss-milestone-decline-btn').forEach(function (btn) {
		btn.addEventListener('click', function () {
			if (!confirm('<?php echo esc_js( __( 'Decline this phase? Your seller can propose a revised one.', 'wp-sell-services' ) ); ?>')) return;
			btn.disabled = true;
			post('wpss_decline_milestone', { milestone_id: btn.dataset.milestone }).then(function (res) {
				if (res && res.success) window.location.reload();
				else { btn.disabled = false; alert((res && res.data && res.data.message) || 'Error'); }
			});
		});
	});

	document.querySelectorAll('.wpss-milestone-delete-btn').forEach(function (btn) {
		btn.addEventListener('click', function () {
			if (!confirm('<?php echo esc_js( __( 'Cancel this phase proposal? This removes it and cannot be undone.', 'wp-sell-services' ) ); ?>')) return;
			btn.disabled = true;
			post('wpss_delete_milestone', { milestone_id: btn.dataset.milestone }).then(function (res) {
				if (res && res.success) window.location.reload();
				else { btn.disabled = false; alert((res && res.data && res.data.message) || 'Error'); }
			});
		});
	});

	var submitModal = document.getElementById('wpss-milestone-submit-modal');
	if (submitModal) {
		var submitForm = submitModal.querySelector('.wpss-milestone-submit-form');
		var feedback = submitModal.querySelector('.wpss-modal__feedback');

		document.querySelectorAll('.wpss-milestone-submit-btn').forEach(function (btn) {
			btn.addEventListener('click', function () { submitModal.hidden = false; submitModal.classList.add('wpss-modal-open'); });
		});
		submitModal.querySelectorAll('.wpss-modal__close, .wpss-modal__cancel, .wpss-modal__backdrop').forEach(function (el) {
			el.addEventListener('click', function () { submitModal.hidden = true; submitModal.classList.remove('wpss-modal-open'); });
		});

		submitForm.addEventListener('submit', function (e) {
			e.preventDefault();
			var submitBtn = submitForm.querySelector('button[type=submit]');
			submitBtn.disabled = true;
			post('wpss_submit_milestone', {
				milestone_id: submitForm.dataset.milestone,
				note: submitForm.querySelector('[name=note]').value,
			}).then(function (res) {
				submitBtn.disabled = false;
				if (res && res.success) {
					feedback.hidden = false;
					feedback.className = 'wpss-modal__feedback wpss-modal__feedback--success';
					feedback.textContent = (res.data && res.data.message) || 'Submitted';
					setTimeout(function () { window.location.reload(); }, 700);
				} else {
					feedback.hidden = false;
					feedback.className = 'wpss-modal__feedback wpss-modal__feedback--error';
					feedback.textContent = (res && res.data && res.data.message) || 'Error';
				}
			});
		});
	}
}());
</script>
