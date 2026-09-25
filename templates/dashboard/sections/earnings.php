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

// F9 payout banner: surface when the vendor has cleared earnings but has not
// yet configured a payout method, so the section opens with a single, designed
// call-to-action rather than a bare notice. Consumes the existing
// .wpss-payout-banner primitive (design-system.css / unified-dashboard.css) —
// info notice token surface, solid icon chip, primary-token CTA.
$payout_profile = EarningsService::get_payout_profile( $user_id );
$payout_method  = $payout_profile['method'];

// Say what is actually true of THIS vendor's balance.
//
// The old guard was `available > 0 || pending_clearance > 0`, which told a
// vendor "You have earnings ready for withdrawal!" while the stat card beside
// it read -$185.00. It fired for anyone with work in flight, because
// pending_clearance counts expected earnings from in-progress orders, not money
// that can be withdrawn. On this data 4 of 8 vendors saw it wrongly -- two in
// debt, two holding less than the $50 minimum.
//
// Three honest states instead of one optimistic one:
// - ready: available >= the minimum, so "ready for withdrawal" is true.
// - coming: money exists or is clearing, but cannot be withdrawn yet.
// - none: nothing to say, so say nothing.
//
// The minimum matters as much as the sign: telling someone holding $26 that
// their earnings are ready, when the site requires $50, is the same lie in a
// smaller hat.
$available_balance = (float) $earnings['available_balance'];
$pending_clearance = (float) $earnings['pending_clearance'];

if ( $available_balance < 0 ) {
	// In debt. Prompting about payouts here competes with the explanation of
	// what they owe, which is the only thing that matters to them right now.
	$payout_banner_state = 'none';
} elseif ( $available_balance >= (float) $min_withdrawal ) {
	$payout_banner_state = 'ready';
} elseif ( $available_balance > 0 || $pending_clearance > 0 ) {
	$payout_banner_state = 'coming';
} else {
	$payout_banner_state = 'none';
}

/**
 * Filters the payout banner state shown on the earnings section.
 *
 * Lets a site owner tune the prompt to their own payout rules -- for example
 * showing 'ready' as soon as any balance exists on a site with no minimum, or
 * suppressing it entirely for vendors they onboard by hand.
 *
 * @since 1.6.0
 *
 * @param string $payout_banner_state 'ready', 'coming' or 'none'.
 * @param float  $available_balance   Withdrawable balance.
 * @param float  $pending_clearance   Earnings still clearing.
 * @param int    $user_id             Vendor user ID.
 */
$payout_banner_state = (string) apply_filters(
	'wpss_payout_banner_state',
	$payout_banner_state,
	$available_balance,
	$pending_clearance,
	$user_id
);

// Only prompt when there is no payout method yet -- the banner exists to get one
// configured, not to report a balance the stat cards already show.
$show_payout_banner = empty( $payout_method ) && 'none' !== $payout_banner_state;
?>

<div class="wpss-section wpss-section--earnings wpss-card">
	<?php if ( $show_payout_banner ) : ?>
		<div class="wpss-dashboard__payout-banner" role="status">
			<span class="wpss-payout-banner__icon">
				<i data-lucide="wallet" class="wpss-icon" aria-hidden="true"></i>
			</span>
			<div class="wpss-payout-banner__content">
				<strong class="wpss-payout-banner__title">
					<?php
					if ( 'ready' === $payout_banner_state ) {
						esc_html_e( 'You have earnings ready for withdrawal!', 'wp-sell-services' );
					} else {
						esc_html_e( 'Your earnings are on the way', 'wp-sell-services' );
					}
					?>
				</strong>
				<span class="wpss-payout-banner__text">
					<?php
					if ( 'ready' === $payout_banner_state ) {
						esc_html_e( 'Set up your payout method below to start receiving payments.', 'wp-sell-services' );
					} else {
						printf(
							/* translators: %s: formatted minimum withdrawal amount. */
							esc_html__( 'Set up your payout method now so you are ready. You can withdraw once your available balance reaches %s.', 'wp-sell-services' ),
							esc_html( wpss_format_price( (float) $min_withdrawal ) )
						);
					}
					?>
				</span>
			</div>
			<a href="#wpss-withdrawal" class="wpss-btn wpss-btn--primary wpss-payout-banner__btn">
				<?php esc_html_e( 'Set Up Payouts', 'wp-sell-services' ); ?>
			</a>
		</div>
	<?php endif; ?>

	<?php if ( $available_balance < 0 ) : ?>
		<?php
		/*
		 * A bare "-$134.04" under "Available for Withdrawal" tells a vendor
		 * nothing except that something is wrong, and the first thing they do
		 * is open a ticket (Basecamp 10244795017).
		 *
		 * It means a past payout exceeded what they had actually earned - on
		 * this install because a ledger bug overstated balances before the
		 * -ABS() fix, and the corrected sum now shows the hole. The money is
		 * gone; nothing is being taken from them now, and future earnings clear
		 * it before anything becomes withdrawable. Say that, rather than
		 * leaving them to guess at a minus sign.
		 */
		?>
		<div class="wpss-notice wpss-notice--warning wpss-earnings-debt">
			<h3 class="wpss-earnings-debt__title"><?php esc_html_e( 'Your balance is below zero', 'wp-sell-services' ); ?></h3>
			<p>
				<?php
				printf(
					/* translators: %s: the amount the vendor is short, formatted. */
					esc_html__( 'A previous payout came to %s more than your earnings covered. Nothing is being taken from you now - your next earnings clear this first, and anything above it becomes available to withdraw as usual.', 'wp-sell-services' ),
					esc_html( wpss_format_price( abs( $available_balance ) ) )
				);
				?>
			</p>
			<p class="wpss-caption">
				<?php esc_html_e( 'If that does not look right, contact the site owner with your order history - they can see every entry behind this figure.', 'wp-sell-services' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<!-- Earnings Summary Cards -->
	<div class="wpss-stats-grid">
		<div class="wpss-stat-card wpss-stat-card--highlight<?php echo $available_balance < 0 ? ' wpss-stat-card--negative' : ''; ?>">
			<span class="wpss-stat-card__value"><?php echo esc_html( wpss_format_price( $earnings['available_balance'] ) ); ?></span>
			<span class="wpss-stat-card__label">
				<?php
				// The label has to match the number. "Available for Withdrawal:
				// -$134.04" is a contradiction - nothing negative is available.
				if ( $available_balance < 0 ) {
					esc_html_e( 'Balance to clear', 'wp-sell-services' );
				} else {
					esc_html_e( 'Available to withdraw', 'wp-sell-services' );
				}
				?>
			</span>
		</div>
		<div class="wpss-stat-card">
			<span class="wpss-stat-card__value"><?php echo esc_html( wpss_format_price( $earnings['pending_clearance'] ) ); ?></span>
			<span class="wpss-stat-card__label"><?php esc_html_e( 'Clearing from open orders', 'wp-sell-services' ); ?></span>
		</div>
		<div class="wpss-stat-card">
			<span class="wpss-stat-card__value"><?php echo esc_html( wpss_format_price( $earnings['pending_withdrawal'] ) ); ?></span>
			<span class="wpss-stat-card__label"><?php esc_html_e( 'Withdrawal in progress', 'wp-sell-services' ); ?></span>
		</div>
		<div class="wpss-stat-card">
			<span class="wpss-stat-card__value"><?php echo esc_html( wpss_format_price( $earnings['withdrawn'] ) ); ?></span>
			<span class="wpss-stat-card__label"><?php esc_html_e( 'Total paid out to you', 'wp-sell-services' ); ?></span>
		</div>
		<?php
		/*
		 * All six tiles share one grid on purpose. They used to be split into a
		 * four-tile row and a two-tile row, and the four never fitted: the rail
		 * is 742px and `.wpss-stats-grid` resolves to auto-fit/minmax(200px),
		 * so the row held three and left "Total Withdrawn" stranded on a line of
		 * its own beside an empty gap. Forcing four columns instead only moved
		 * the damage - `.wpss-stat-card` is display:flex (frontend.css), so the
		 * value and its label sit side by side and need about 230px; at four
		 * across they render 171px and clip the label.
		 *
		 * Six tiles over three columns is two balanced rows at the width the
		 * card was already rendering correctly, and it needs no CSS at all -
		 * which is why the --4 and --2 modifiers are gone from the markup.
		 * See Basecamp 10300099697.
		 */
		?>
		<div class="wpss-stat-card">
			<span class="wpss-stat-card__value"><?php echo esc_html( wpss_format_price( $earnings['total_earned'] ) ); ?></span>
			<span class="wpss-stat-card__label"><?php esc_html_e( 'Total earned so far', 'wp-sell-services' ); ?></span>
		</div>
		<div class="wpss-stat-card">
			<span class="wpss-stat-card__value"><?php echo esc_html( $earnings['completed_orders'] ); ?></span>
			<span class="wpss-stat-card__label"><?php esc_html_e( 'Completed Orders', 'wp-sell-services' ); ?></span>
		</div>
	</div>

	<?php
	/*
	 * Say how the figures relate to each other.
	 *
	 * The tiles are each correct and they do not add up, which is worse than
	 * being wrong: a vendor cannot tell whether they are being paid properly.
	 * "Clearing from open orders" is money not yet credited, so it is NOT part
	 * of "Total earned so far" - reading them as parts of one sum is the
	 * obvious interpretation and the wrong one. One plain sentence costs
	 * nothing and removes the doubt.
	 */
	?>
	<p class="wpss-earnings-explainer">
		<?php esc_html_e( 'Money clearing from open orders is not counted as earned until the order completes. Total earned so far is everything credited to you after any refunds, and is not reduced by withdrawals.', 'wp-sell-services' ); ?>
	</p>

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
	<div id="wpss-withdrawal" class="wpss-earnings__withdrawal" style="margin-top: 2rem;">
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
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $payout_method, $key ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

				<?php
				// Where the money goes: the payout profile, one field set per
				// method, prefilled from what the vendor saved. The request saves
				// any change (Basecamp 10336467884).
				foreach ( $methods as $wpss_method => $wpss_method_label ) :
					?>
					<fieldset class="wpss-payout-fields" data-payout-method="<?php echo esc_attr( $wpss_method ); ?>"<?php echo $payout_method === $wpss_method ? '' : ' hidden'; ?>>
						<legend class="screen-reader-text">
							<?php
							/* translators: %s: payout method */
							echo esc_html( sprintf( __( '%s details', 'wp-sell-services' ), $wpss_method_label ) );
							?>
						</legend>
						<?php foreach ( EarningsService::get_payout_fields( $wpss_method ) as $wpss_key => $wpss_field ) : ?>
							<?php $wpss_id = 'payout_' . $wpss_method . '_' . $wpss_key; ?>
							<div class="wpss-form-group">
								<label for="<?php echo esc_attr( $wpss_id ); ?>"><?php echo esc_html( $wpss_field['label'] ); ?><?php echo ! empty( $wpss_field['required'] ) ? ' <span class="wpss-required" aria-hidden="true">*</span>' : ''; ?></label>
								<?php
								$wpss_value = $payout_method === $wpss_method ? ( $payout_profile['details'][ $wpss_key ] ?? '' ) : '';
								if ( 'textarea' === $wpss_field['type'] ) :
									?>
									<textarea id="<?php echo esc_attr( $wpss_id ); ?>" class="wpss-textarea" rows="3" data-detail="<?php echo esc_attr( $wpss_key ); ?>"<?php echo ! empty( $wpss_field['required'] ) ? ' data-required="1"' : ''; ?>><?php echo esc_textarea( $wpss_value ); ?></textarea>
								<?php else : ?>
									<input type="<?php echo esc_attr( $wpss_field['type'] ); ?>" id="<?php echo esc_attr( $wpss_id ); ?>" class="wpss-input" value="<?php echo esc_attr( $wpss_value ); ?>" data-detail="<?php echo esc_attr( $wpss_key ); ?>"<?php echo ! empty( $wpss_field['required'] ) ? ' data-required="1"' : ''; ?>>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</fieldset>
				<?php endforeach; ?>

				<div class="wpss-form-group">
					<button type="submit" class="wpss-btn wpss-btn--primary" id="wpss-withdrawal-submit">
						<?php esc_html_e( 'Request Withdrawal', 'wp-sell-services' ); ?>
					</button>
				</div>

				<div id="wpss-withdrawal-message" class="wpss-notice" style="display: none;"></div>
			</form>
		<?php elseif ( 'negative' === EarningsService::classify_balance( (float) $earnings['available_balance'] ) ) : ?>
			<?php
			// A refund reversed earnings the vendor had already withdrawn, so
			// they owe the platform. Shown as a debt with a clear payback path,
			// NOT as "not enough to cash out yet" — that framing implies they
			// simply have not earned enough, when in fact money was taken back.
			// The balance is a ledger SUM, so new earnings clear it
			// automatically; nobody has to chase an invoice.
			$wpss_owed_amount   = abs( (float) $earnings['available_balance'] );
			$wpss_earn_more_url = wpss_append_dashboard_section( get_permalink(), 'services' );
			?>
			<div class="wpss-banner wpss-banner--danger wpss-earnings__payout-banner" role="status">
				<i data-lucide="alert-circle" class="wpss-icon wpss-icon--lg wpss-banner__icon" aria-hidden="true"></i>
				<div class="wpss-banner__content">
					<span class="wpss-banner__title">
						<?php
						printf(
							/* translators: %s: negative balance */
							esc_html__( 'Your balance is %s', 'wp-sell-services' ),
							esc_html( wpss_format_price( -$wpss_owed_amount ) )
						);
						?>
					</span>
					<span class="wpss-banner__text">
						<?php
						printf(
							/* translators: %s: amount owed */
							esc_html__( 'A refund returned %s to a buyer after you were paid, so that amount is owed back. Your next earnings clear it automatically — there is nothing to pay directly. Withdrawals resume once your balance is positive again.', 'wp-sell-services' ),
							esc_html( wpss_format_price( $wpss_owed_amount ) )
						);
						?>
					</span>
				</div>
				<a href="<?php echo esc_url( $wpss_earn_more_url ); ?>" class="wpss-btn wpss-btn--primary wpss-btn--sm wpss-banner__action">
					<i data-lucide="briefcase" class="wpss-icon wpss-icon--sm" aria-hidden="true"></i>
					<?php esc_html_e( 'Manage your services', 'wp-sell-services' ); ?>
				</a>
			</div>
		<?php else : ?>
			<?php
			$wpss_earn_more_url = wpss_append_dashboard_section( get_permalink(), 'services' );
			?>
			<div class="wpss-banner wpss-banner--warning wpss-earnings__payout-banner" role="status">
				<i data-lucide="piggy-bank" class="wpss-icon wpss-icon--lg wpss-banner__icon" aria-hidden="true"></i>
				<div class="wpss-banner__content">
					<span class="wpss-banner__title">
						<?php esc_html_e( 'Not enough to cash out yet', 'wp-sell-services' ); ?>
					</span>
					<span class="wpss-banner__text">
						<?php
						printf(
							/* translators: 1: current available balance, 2: minimum withdrawal amount */
							esc_html__( 'You have %1$s available. Earn at least %2$s before you can request a withdrawal.', 'wp-sell-services' ),
							esc_html( wpss_format_price( $earnings['available_balance'] ) ),
							esc_html( wpss_format_price( $min_withdrawal ) )
						);
						?>
					</span>
				</div>
				<a href="<?php echo esc_url( $wpss_earn_more_url ); ?>" class="wpss-btn wpss-btn--primary wpss-btn--sm wpss-banner__action">
					<i data-lucide="briefcase" class="wpss-icon wpss-icon--sm" aria-hidden="true"></i>
					<?php esc_html_e( 'Manage your services', 'wp-sell-services' ); ?>
				</a>
			</div>
		<?php endif; ?>
	</div>

	<?php
	/**
	 * Fires in the Payouts section's payout-methods area.
	 *
	 * The single place where payout-method rails render their vendor-facing UI,
	 * so every method lives in ONE vendor section instead of separate nav items.
	 * Manual withdrawal (above) is the universal baseline; add-ons (Pro PayPal
	 * Payouts, Stripe Connect) hook here to render their setup/status when the
	 * site owner has enabled that rail. Guarded with has_action() so a manual-
	 * only site shows no empty block.
	 *
	 * @since 1.2.0
	 *
	 * @param int    $user_id       Current vendor user ID.
	 * @param string $payout_method The vendor's saved payout-method meta, if any.
	 */
	if ( has_action( 'wpss_payout_methods' ) ) :
		?>
		<div class="wpss-earnings__payout-methods wpss-payout-methods" style="margin-top: 2rem;">
			<h3><?php esc_html_e( 'Payout Methods', 'wp-sell-services' ); ?></h3>
			<p class="wpss-payout-methods__intro">
				<?php esc_html_e( 'Set up how you would like to get paid. Your site can pay you manually using the withdrawal request above, or through one of the connected methods below.', 'wp-sell-services' ); ?>
			</p>
			<?php do_action( 'wpss_payout_methods', $user_id, $payout_method ); ?>
		</div>
		<?php
	endif;
	?>

	<!-- Wallet Transactions Ledger -->
	<div class="wpss-earnings__wallet wpss-wallet wpss-card">
		<div class="wpss-wallet__header">
			<div>
				<h3><?php esc_html_e( 'Wallet Transactions', 'wp-sell-services' ); ?></h3>
				<p class="wpss-form-hint"><?php esc_html_e( 'Every credit and debit on your wallet, newest first.', 'wp-sell-services' ); ?></p>
			</div>
			<?php
			/**
			 * Fires in the wallet ledger header, for ledger controls.
			 *
			 * Add-ons (Pro) hook here to add a period selector + CSV export for
			 * the ledger, so those controls live in the one Earnings & Payouts
			 * section rather than a duplicate template.
			 *
			 * @since 1.2.0
			 * @param int $user_id Current vendor user ID.
			 */
			do_action( 'wpss_earnings_ledger_actions', $user_id );
			?>
		</div>

		<div id="wpss-wallet-transactions"
			class="wpss-wallet__list"
			data-rest-path="wallet/transactions"
			data-per-page="10"
			aria-live="polite"
			aria-busy="true">
			<div class="wpss-wallet__loading">
				<span class="wpss-spinner" aria-hidden="true"></span>
				<?php esc_html_e( 'Loading transactions…', 'wp-sell-services' ); ?>
			</div>
		</div>

		<button type="button"
			id="wpss-wallet-load-more"
			class="wpss-btn wpss-btn--outline wpss-btn--sm wpss-wallet__more"
			style="display: none; margin-top: 1rem;">
			<?php esc_html_e( 'Load more', 'wp-sell-services' ); ?>
		</button>
	</div>

	<!-- Withdrawal History -->
	<div class="wpss-earnings__history wpss-card" style="margin-top: 2rem;">
		<h3><?php esc_html_e( 'Withdrawal History', 'wp-sell-services' ); ?></h3>

		<?php if ( ! empty( $withdrawals ) ) : ?>
			<div class="wpss-table-responsive">
				<table class="wpss-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', 'wp-sell-services' ); ?></th>
							<th><?php esc_html_e( 'Amount', 'wp-sell-services' ); ?></th>
							<th><?php esc_html_e( 'Method', 'wp-sell-services' ); ?></th>
							<th><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></th>
							<th><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'wp-sell-services' ); ?></span></th>
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
									// Semantic badge tones from design-system.css; the old
									// per-status classes (wpss-badge--pending ...) have no styles.
									$tones        = array(
										EarningsService::WITHDRAWAL_PENDING   => 'warning',
										EarningsService::WITHDRAWAL_APPROVED  => 'info',
										EarningsService::WITHDRAWAL_COMPLETED => 'success',
										EarningsService::WITHDRAWAL_REJECTED  => 'danger',
									);
									$status_class = 'wpss-badge--' . ( $tones[ $withdrawal['status'] ] ?? 'neutral' );
									$statuses     = EarningsService::get_withdrawal_statuses();
									?>
									<span class="wpss-badge <?php echo esc_attr( $status_class ); ?>">
										<?php echo esc_html( $statuses[ $withdrawal['status'] ] ?? ucfirst( $withdrawal['status'] ) ); ?>
									</span>
								</td>
								<td>
									<?php if ( EarningsService::WITHDRAWAL_PENDING === $withdrawal['status'] && empty( $withdrawal['is_auto'] ) ) : ?>
										<button type="button" class="wpss-btn wpss-btn--sm wpss-btn--link" data-wpss-rest-action="<?php echo esc_attr( 'withdrawals/' . (int) $withdrawal['id'] ); ?>" data-method="DELETE" data-confirm="<?php esc_attr_e( 'Cancel this withdrawal request? The amount goes back to your available balance.', 'wp-sell-services' ); ?>">
											<?php esc_html_e( 'Cancel', 'wp-sell-services' ); ?>
										</button>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php else : ?>
			<p class="wpss-text-muted"><?php esc_html_e( 'No withdrawal requests yet.', 'wp-sell-services' ); ?></p>
		<?php endif; ?>
	</div>
</div>


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
