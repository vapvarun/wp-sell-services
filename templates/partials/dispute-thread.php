<?php
/**
 * A dispute's "Messages & evidence" thread, with the reply form while it is open.
 *
 * One thread for the buyer's and seller's dashboard and the admin dispute
 * screen. The admin replies into it through the same write path
 * (wpss_add_dispute_evidence -> DisputeService::add_evidence), labelled Admin
 * to the parties, instead of a separate notes box (Basecamp 10337171525).
 * The reply is posted by assets/js/dispute-thread.js.
 *
 * @package WPSellServices
 * @since   1.8.0
 *
 * @var object $wpss_dispute     The dispute.
 * @var array  $wpss_evidence    DisputeService::get_evidence().
 * @var int    $wpss_viewer_id   The user looking at it.
 * @var int    $wpss_customer_id The order's buyer.
 * @var int    $wpss_vendor_id   The order's vendor.
 */

defined( 'ABSPATH' ) || exit;

$wpss_is_admin    = ! in_array( (int) $wpss_viewer_id, array( (int) $wpss_customer_id, (int) $wpss_vendor_id ), true );
$can_add_evidence = ! in_array( (string) $wpss_dispute->status, array( 'resolved', 'closed' ), true );

if ( $can_add_evidence ) {
	\WPSellServices\Assets\ScriptRegistry::enqueue_ui();
	\WPSellServices\Assets\ScriptRegistry::enqueue( 'wpss-dispute-thread', 'assets/js/dispute-thread.js', array( \WPSellServices\Assets\ScriptRegistry::HANDLE_UI ) );
}
?>
<div class="wpss-dispute-detail__evidence">
	<h3><?php esc_html_e( 'Messages &amp; evidence', 'wp-sell-services' ); ?></h3>

	<div class="wpss-evidence-thread" id="wpss-evidence-thread">
		<?php if ( empty( $wpss_evidence ) ) : ?>
			<p class="wpss-evidence-empty">
			<?php
			echo esc_html(
				$wpss_is_admin
					? __( 'No messages yet. Ask the buyer and the vendor for their side and any files - screenshots, the delivered work - before you rule.', 'wp-sell-services' )
					: __( 'No messages yet. Add one below to make your case to the reviewer and the other party.', 'wp-sell-services' )
			);
			?>
		</p>
		<?php else : ?>
			<?php
			foreach ( $wpss_evidence as $wpss_item ) {
				wpss_get_template_part(
					'partials/dispute-evidence-item',
					'',
					array(
						'wpss_item'        => $wpss_item,
						'wpss_viewer_id'   => $wpss_viewer_id,
						'wpss_customer_id' => $wpss_customer_id,
						'wpss_vendor_id'   => $wpss_vendor_id,
					)
				);
			}
			?>
		<?php endif; ?>
	</div>

	<?php if ( $can_add_evidence ) : ?>
		<form id="wpss-add-evidence-form" class="wpss-add-evidence-form" enctype="multipart/form-data"
		data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
		data-sending="<?php esc_attr_e( 'Sending…', 'wp-sell-services' ); ?>"
		data-error="<?php esc_attr_e( 'Something went wrong. Please try again.', 'wp-sell-services' ); ?>">
			<?php wp_nonce_field( 'wpss_add_evidence', 'nonce' ); ?>
			<input type="hidden" name="dispute_id" value="<?php echo esc_attr( (int) $wpss_dispute->id ); ?>">
			<label class="wpss-form-label" for="wpss-evidence-message">
				<?php esc_html_e( 'Add a message or evidence', 'wp-sell-services' ); ?>
			</label>
			<textarea id="wpss-evidence-message" name="description" class="wpss-form-textarea" rows="3"
				placeholder="<?php echo esc_attr( $wpss_is_admin ? __( 'Ask either party a question or for evidence. Both see it, labelled Admin.', 'wp-sell-services' ) : __( 'Explain your side, or add context for the reviewer…', 'wp-sell-services' ) ); ?>"></textarea>
			<div class="wpss-add-evidence-form__row">
				<label class="wpss-btn wpss-btn--secondary wpss-btn--sm wpss-evidence-attach">
					<i data-lucide="paperclip" class="wpss-icon" aria-hidden="true"></i>
					<span><?php esc_html_e( 'Attach file', 'wp-sell-services' ); ?></span>
					<input type="file" name="evidence_file" accept="<?php echo esc_attr( wpss_upload_accept( 'dispute' ) ); ?>" hidden>
				</label>
				<span class="wpss-evidence-filename" aria-live="polite"></span>
				<button type="submit" class="wpss-btn wpss-btn--primary wpss-btn--sm">
					<?php esc_html_e( 'Send', 'wp-sell-services' ); ?>
				</button>
			</div>
		</form>
	<?php else : ?>
		<p class="wpss-evidence-locked"><?php esc_html_e( 'This dispute is closed. No further messages can be added.', 'wp-sell-services' ); ?></p>
	<?php endif; ?>
</div>
