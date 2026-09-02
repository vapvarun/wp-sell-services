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
 *   - Buyer, pending_approval   → Approve / Request changes
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
$parent_url = $parent_id ? wpss_get_order_url( $parent_id ) : '';

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
// A phase the buyer sent back. submit() has always accepted this as a
// from-state; until 1.7.0 nothing could put a phase into it, so the template
// had no branch and the page rendered with no heading at all.
$in_revision  = 'revision_requested' === $status;
$revision_ask = (string) ( $meta['revision_reason'] ?? '' );
$is_completed = 'completed' === $status;
$is_cancelled = 'cancelled' === $status;

// Through the seam, never rebuilt inline: ?pay_order=N is a real payment page
// only on the standalone rail. On WooCommerce it lands on the store cart and
// bounces the buyer away - which is why "Accept & Pay" did nothing on Woo
// sites while the same phase paid fine from the notification email, which
// does use the seam.
$pay_url = wpss_get_pay_order_url( (int) $current_order->id );

$counterparty_id = $is_buyer ? (int) $current_order->vendor_id : (int) $current_order->customer_id;
$counterparty    = get_userdata( $counterparty_id );

$format = static function ( float $amount ) use ( $currency ): string {
	return function_exists( 'wpss_format_price' )
		? wpss_format_price( $amount, $currency )
		: number_format_i18n( $amount, 2 ) . ' ' . $currency;
};

/**
 * Fires before the milestone sub-order view content.
 *
 * @since 1.1.0
 *
 * @param \WPSellServices\Models\ServiceOrder $current_order Milestone sub-order row.
 */
do_action( 'wpss_before_milestone_view', $current_order );
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
				} elseif ( $in_revision ) {
					esc_html_e( 'Changes requested — over to you', 'wp-sell-services' );
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
				} elseif ( $in_revision ) {
					esc_html_e( 'Sent back to your seller', 'wp-sell-services' );
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

			<?php if ( '' !== $submit_note && ( $is_submitted || $is_completed || $in_revision ) ) : ?>
				<div class="wpss-tip-view__message">
					<dt><?php esc_html_e( 'Delivery note', 'wp-sell-services' ); ?></dt>
					<dd><?php echo esc_html( $submit_note ); ?></dd>
				</div>
			<?php endif; ?>

			<?php if ( '' !== $revision_ask && $in_revision ) : ?>
				<div class="wpss-tip-view__message">
					<dt><?php esc_html_e( 'Changes requested', 'wp-sell-services' ); ?></dt>
					<dd><?php echo esc_html( $revision_ask ); ?></dd>
				</div>
			<?php endif; ?>
		</dl>

		<div class="wpss-tip-view__actions">
			<?php if ( $is_buyer && $is_unpaid && '' !== $pay_url ) : ?>
				<a href="<?php echo esc_url( $pay_url ); ?>" class="wpss-btn wpss-btn--primary">
					<?php
					printf(
						/* translators: %s: amount */
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

			<?php if ( $is_vendor && ( $is_working || $is_submitted || $in_revision ) ) : ?>
				<button type="button" class="wpss-btn wpss-btn--primary wpss-milestone-submit-btn"
					data-milestone="<?php echo esc_attr( (int) $current_order->id ); ?>">
					<?php echo esc_html( ( $is_submitted || $in_revision ) ? __( 'Resubmit delivery', 'wp-sell-services' ) : __( 'Submit delivery', 'wp-sell-services' ) ); ?>
				</button>
			<?php endif; ?>

			<?php if ( $is_buyer && $is_submitted ) : ?>
				<button type="button" class="wpss-btn wpss-btn--primary wpss-milestone-approve-btn"
					data-milestone="<?php echo esc_attr( (int) $current_order->id ); ?>">
					<?php esc_html_e( 'Approve delivery', 'wp-sell-services' ); ?>
				</button>
				<?php
				// A real action, not a link into the chat. Sending the phase
				// back is a state change the seller has to act on, and
				// submit() has always accepted revision_requested as a valid
				// from-state - nothing ever put a phase INTO it, so the buyer's
				// only route was a message the seller could miss
				// (Basecamp 10254720173). The reason still lands in the parent
				// thread, which is where the conversation lives.
				?>
				<button type="button" class="wpss-btn wpss-btn--outline wpss-milestone-revision-btn"
					data-milestone="<?php echo esc_attr( (int) $current_order->id ); ?>">
					<?php esc_html_e( 'Request changes', 'wp-sell-services' ); ?>
				</button>
			<?php endif; ?>

			<?php if ( $parent_url ) : ?>
				<a href="<?php echo esc_url( $parent_url ); ?>" class="wpss-btn wpss-btn--outline">
					<?php esc_html_e( 'View original order', 'wp-sell-services' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>
</div>

<?php if ( $is_vendor && ( $is_working || $is_submitted || $in_revision ) ) : ?>
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
				<p class="wpss-modal__intro"><?php esc_html_e( 'Tell the buyer what you delivered. They will review and approve, or send it back with notes.', 'wp-sell-services' ); ?></p>
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

<?php if ( $is_buyer && $is_submitted ) : ?>
	<!-- Request changes modal (buyer only) -->
	<div class="wpss-modal wpss-extension-modal" id="wpss-milestone-revision-modal" role="dialog" aria-modal="true" aria-labelledby="wpss-ms-revision-title" hidden>
		<div class="wpss-modal__backdrop"></div>
		<div class="wpss-modal__dialog">
			<div class="wpss-modal__header">
				<h3 id="wpss-ms-revision-title" class="wpss-modal__title"><?php esc_html_e( 'Request changes', 'wp-sell-services' ); ?></h3>
				<button type="button" class="wpss-modal__close" aria-label="<?php esc_attr_e( 'Close', 'wp-sell-services' ); ?>">
					<i data-lucide="x" class="wpss-icon" aria-hidden="true"></i>
				</button>
			</div>
			<div class="wpss-modal__body">
				<p class="wpss-modal__intro"><?php esc_html_e( 'Tell the seller what needs changing. The phase goes back to them and your notes are posted in the order conversation.', 'wp-sell-services' ); ?></p>
				<form class="wpss-milestone-revision-form" data-milestone="<?php echo esc_attr( (int) $current_order->id ); ?>">
					<div class="wpss-form-row">
						<label for="wpss-ms-reason"><?php esc_html_e( 'What needs changing?', 'wp-sell-services' ); ?></label>
						<textarea id="wpss-ms-reason" name="reason" rows="4" class="wpss-textarea" required placeholder="<?php esc_attr_e( 'e.g. The second concept is close - can the logo sit left of the wordmark instead of above it?', 'wp-sell-services' ); ?>"></textarea>
					</div>
					<div class="wpss-modal__feedback" role="status" aria-live="polite" hidden></div>
					<div class="wpss-modal__footer">
						<button type="button" class="wpss-btn wpss-btn--secondary wpss-modal__cancel"><?php esc_html_e( 'Cancel', 'wp-sell-services' ); ?></button>
						<button type="submit" class="wpss-btn wpss-btn--primary"><?php esc_html_e( 'Send back to seller', 'wp-sell-services' ); ?></button>
					</div>
				</form>
			</div>
		</div>
	</div>
<?php endif; ?>

<?php
/**
 * Fires after the milestone sub-order view content.
 *
 * @since 1.1.0
 *
 * @param \WPSellServices\Models\ServiceOrder $current_order Milestone sub-order row.
 */
do_action( 'wpss_after_milestone_view', $current_order );
?>

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

	// Confirm via the design-system modal (wpssConfirm), never native confirm()/
	// alert() — matches the rest of the plugin (portfolio/request delete, admin
	// order actions). wpss-ui is enqueued by UnifiedDashboard, so both helpers
	// are present; fall back gracefully if it ever fails to load.
	function confirmAction(message, tone) {
		if (window.wpssConfirm) {
			return window.wpssConfirm(message, tone ? { tone: tone } : {});
		}
		return Promise.resolve(window.confirm(message));
	}
	function notifyError(message) {
		if (window.wpssToast) { window.wpssToast(message, 'error'); }
		else { window.alert(message); }
	}

	document.querySelectorAll('.wpss-milestone-approve-btn').forEach(function (btn) {
		btn.addEventListener('click', function () {
			confirmAction('<?php echo esc_js( __( 'Approve delivery of this phase? This marks it as complete and cannot be undone.', 'wp-sell-services' ) ); ?>').then(function (ok) {
				if (!ok) return;
				btn.disabled = true;
				post('wpss_approve_milestone', { milestone_id: btn.dataset.milestone }).then(function (res) {
					if (res && res.success) window.location.reload();
					else { btn.disabled = false; notifyError((res && res.data && res.data.message) || 'Error'); }
				});
			});
		});
	});

	document.querySelectorAll('.wpss-milestone-decline-btn').forEach(function (btn) {
		btn.addEventListener('click', function () {
			confirmAction('<?php echo esc_js( __( 'Decline this phase? Your seller can propose a revised one.', 'wp-sell-services' ) ); ?>', 'danger').then(function (ok) {
				if (!ok) return;
				btn.disabled = true;
				post('wpss_decline_milestone', { milestone_id: btn.dataset.milestone }).then(function (res) {
					if (res && res.success) window.location.reload();
					else { btn.disabled = false; notifyError((res && res.data && res.data.message) || 'Error'); }
				});
			});
		});
	});

	document.querySelectorAll('.wpss-milestone-delete-btn').forEach(function (btn) {
		btn.addEventListener('click', function () {
			confirmAction('<?php echo esc_js( __( 'Cancel this phase proposal? This removes it and cannot be undone.', 'wp-sell-services' ) ); ?>', 'danger').then(function (ok) {
				if (!ok) return;
				btn.disabled = true;
				post('wpss_delete_milestone', { milestone_id: btn.dataset.milestone }).then(function (res) {
					if (res && res.success) window.location.reload();
					else { btn.disabled = false; notifyError((res && res.data && res.data.message) || 'Error'); }
				});
			});
		});
	});

	// One wiring, two modals. The seller's Submit delivery and the buyer's
	// Request changes are the same interaction with a different verb, and
	// the second was about to be a copy of the first.
	function wireModal(modalId, formClass, openerClass, action, field) {
		var modal = document.getElementById(modalId);
		if (!modal) return;

		var form = modal.querySelector('.' + formClass);
		var feedback = modal.querySelector('.wpss-modal__feedback');

		function close() { modal.hidden = true; modal.classList.remove('wpss-modal-open'); }

		document.querySelectorAll('.' + openerClass).forEach(function (btn) {
			btn.addEventListener('click', function () {
				modal.hidden = false;
				modal.classList.add('wpss-modal-open');
				var input = form.querySelector('[name=' + field + ']');
				if (input) input.focus();
			});
		});
		modal.querySelectorAll('.wpss-modal__close, .wpss-modal__cancel, .wpss-modal__backdrop').forEach(function (el) {
			el.addEventListener('click', close);
		});

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var submitBtn = form.querySelector('button[type=submit]');
			var payload = { milestone_id: form.dataset.milestone };
			payload[field] = form.querySelector('[name=' + field + ']').value;
			submitBtn.disabled = true;
			post(action, payload).then(function (res) {
				submitBtn.disabled = false;
				feedback.hidden = false;
				if (res && res.success) {
					feedback.className = 'wpss-modal__feedback wpss-modal__feedback--success';
					feedback.textContent = (res.data && res.data.message) || 'Done';
					setTimeout(function () { window.location.reload(); }, 700);
				} else {
					feedback.className = 'wpss-modal__feedback wpss-modal__feedback--error';
					feedback.textContent = (res && res.data && res.data.message) || 'Error';
				}
			});
		});
	}

	wireModal('wpss-milestone-submit-modal', 'wpss-milestone-submit-form', 'wpss-milestone-submit-btn', 'wpss_submit_milestone', 'note');
	wireModal('wpss-milestone-revision-modal', 'wpss-milestone-revision-form', 'wpss-milestone-revision-btn', 'wpss_request_milestone_revision', 'reason');
}());
</script>
