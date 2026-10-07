<?php
/**
 * Order requirements, read-only: the buyer's answers and files, or what is missing.
 *
 * One partial for the buyer's and seller's order view and the admin order
 * screen (Basecamp 10337161480). The buyer's form to submit them stays in the
 * order view. The admin screen always shows the section, so it says plainly
 * when nothing was submitted or the service asks for nothing.
 *
 * Override: {theme}/wp-sell-services/order/requirements.php
 *
 * @package WPSellServices
 * @since   1.8.0
 *
 * @var \WPSellServices\Models\ServiceOrder $wpss_order                The order.
 * @var string                              $wpss_viewer               'buyer', 'vendor' or 'admin'.
 * @var array|null                          $wpss_service_requirements The service's questions; read when not passed.
 * @var array|null                          $wpss_submitted            ServiceOrder::get_submitted_requirements(); read when not passed.
 * @var bool                                $wpss_hide_not_provided    True while the buyer's late-submission form shows instead.
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $wpss_order ) ) {
	return;
}

$wpss_viewer               = $wpss_viewer ?? 'buyer';
$wpss_service_requirements = $wpss_service_requirements ?? ( $wpss_order->service_id ? wpss_get_service_requirements( (int) $wpss_order->service_id ) : array() );
$wpss_submitted            = $wpss_submitted ?? $wpss_order->get_submitted_requirements();

$service_requirements  = $wpss_service_requirements;
$submitted_data        = $wpss_submitted['data'];
$submitted_attachments = $wpss_submitted['attachments'];
$submitted_at          = $wpss_submitted['submitted_at'];

$wpss_has_submitted = ! empty( $submitted_data ) || ! empty( $submitted_attachments );
$wpss_not_provided  = ! $wpss_has_submitted && ! empty( $service_requirements ) && empty( $wpss_hide_not_provided )
	&& in_array( $wpss_order->status, array( 'in_progress', 'pending_approval', 'completed', 'delivered', 'late', 'revision_requested' ), true );
?>
<?php if ( $wpss_has_submitted ) : ?>
	<section class="wpss-order-section wpss-order-section--requirements-view">
		<div class="wpss-order-section__header">
			<h2 class="wpss-order-section__title">
				<i data-lucide="clipboard-check" class="wpss-icon" aria-hidden="true"></i>
				<?php esc_html_e( 'Order Requirements', 'wp-sell-services' ); ?>
			</h2>
			<?php if ( $submitted_at ) : ?>
				<span class="wpss-order-section__timestamp">
					<?php
					printf(
						/* translators: %s: submission date/time */
						esc_html__( 'Submitted %s', 'wp-sell-services' ),
						esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $submitted_at ) ) )
					);
					?>
				</span>
			<?php endif; ?>
		</div>
		<div class="wpss-order-section__body">
			<?php foreach ( $service_requirements as $index => $requirement ) : ?>
				<?php
				$question        = $requirement['label'];
				$wpss_field_type = $requirement['type'];
				$response_value  = wpss_requirement_answer( $requirement, $submitted_data );
				if ( is_array( $response_value ) ) {
					$response_value = implode( ', ', array_map( 'strval', $response_value ) );
				}

				// Find attachment for this field (if file type). Answers and
				// attachments are keyed by requirement id; pre-1.7.1 rows by
				// question text.
				$field_attachment = null;
				if ( 'file' === $wpss_field_type && ! empty( $submitted_attachments ) ) {
					foreach ( $submitted_attachments as $att ) {
						if ( isset( $att['key'] ) && in_array( $att['key'], array( $requirement['id'], $question ), true ) ) {
							$field_attachment = $att;
							break;
						}
					}
				}

				// Determine if text is long (for expand/collapse).
				$is_long_text = is_string( $response_value ) && strlen( $response_value ) > 300;
				?>
				<div class="wpss-requirement-view <?php echo $is_long_text ? 'wpss-requirement-view--expandable' : ''; ?>">
					<h4 class="wpss-requirement-view__question"><?php echo esc_html( $question ); ?></h4>
					<div class="wpss-requirement-view__answer <?php echo $is_long_text ? 'wpss-requirement-view__answer--collapsed' : ''; ?>">
						<?php if ( 'file' === $wpss_field_type && $field_attachment ) : ?>
							<?php
							// Private records (1.7.0+) carry a path, not a url: the ONE
							// resolver hands back the guarded endpoint, or '' when the
							// file is not addressable - same as the orphan list below.
							$field_attachment['order_id'] = $wpss_order->id;
							$field_file_url               = wpss_get_order_file_url( $field_attachment );
							$field_file_name              = wpss_format_attachment_name( (string) ( $field_attachment['name'] ?? '' ) );
							$is_image                     = '' !== $field_file_url && in_array( strtolower( pathinfo( $field_file_name, PATHINFO_EXTENSION ) ), array( 'jpg', 'jpeg', 'png', 'gif', 'webp' ), true );
							?>
							<?php if ( $is_image ) : ?>
								<div class="wpss-requirement-view__image-preview">
									<img src="<?php echo esc_url( $field_file_url ); ?>" alt="<?php echo esc_attr( $field_file_name ); ?>" class="wpss-requirement-view__thumbnail" loading="lazy">
								</div>
							<?php endif; ?>
							<?php if ( '' !== $field_file_url ) : ?>
								<a href="<?php echo esc_url( $field_file_url ); ?>" class="wpss-file-link" target="_blank" download>
									<i data-lucide="download" class="wpss-icon" aria-hidden="true"></i>
									<?php echo esc_html( $field_file_name ); ?>
								</a>
							<?php else : ?>
								<?php echo esc_html( $field_file_name ); ?>
							<?php endif; ?>
						<?php elseif ( $response_value ) : ?>
							<?php
							$wpss_answer_text = (string) $response_value;
							require WPSS_PLUGIN_DIR . 'templates/partials/requirement-answer.php';
							?>
							<button type="button" class="wpss-requirement-view__copy-btn" data-copy-text="<?php echo esc_attr( $response_value ); ?>" title="<?php esc_attr_e( 'Copy to clipboard', 'wp-sell-services' ); ?>">
								<i data-lucide="copy" class="wpss-icon wpss-icon--sm" aria-hidden="true"></i>
							</button>
						<?php else : ?>
							<span class="wpss-text-muted"><?php esc_html_e( 'No response provided', 'wp-sell-services' ); ?></span>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>

			<?php
			/*
			 * Anything the buyer submitted that no configured question claims.
			 *
			 * The loop above walks the SERVICE's questions and looks each
			 * answer up by question text. A service with no configured
			 * questions therefore rendered nothing at all, even though the
			 * buyer had written a brief and the row was sitting in
			 * field_data - so the vendor opened the order and could not read
			 * what they had been asked to build (Basecamp 10254444197).
			 *
			 * Keying answers by question text has a second failure with the
			 * same shape: edit or delete a question after submission and its
			 * answer silently disappears too. Both are covered by rendering
			 * whatever is left over rather than by special-casing
			 * 'description'.
			 */
			$rendered_keys = array();
			foreach ( $service_requirements as $requirement ) {
				$rendered_keys[] = $requirement['id'];
				$rendered_keys[] = $requirement['label'];
			}

			$orphan_answers = array();
			foreach ( (array) $submitted_data as $key => $value ) {
				if ( in_array( (string) $key, $rendered_keys, true ) ) {
					continue;
				}
				if ( '' === trim( (string) ( is_scalar( $value ) ? $value : wp_json_encode( $value ) ) ) ) {
					continue;
				}
				$orphan_answers[ $key ] = $value;
			}
			?>

			<?php foreach ( $orphan_answers as $orphan_key => $orphan_value ) : ?>
				<?php
				// One label helper, shared with the admin order screen, which
				// used to print the raw key instead.
				$orphan_label = wpss_requirement_field_label( (string) $orphan_key );

				$orphan_text = is_scalar( $orphan_value )
					? (string) $orphan_value
					: wp_json_encode( $orphan_value );

				$orphan_long = strlen( $orphan_text ) > 300;
				?>
				<div class="wpss-requirement-view <?php echo $orphan_long ? 'wpss-requirement-view--expandable' : ''; ?>">
					<h4 class="wpss-requirement-view__question"><?php echo esc_html( $orphan_label ); ?></h4>
					<div class="wpss-requirement-view__answer <?php echo $orphan_long ? 'wpss-requirement-view__answer--collapsed' : ''; ?>">
						<?php
						$wpss_answer_text = $orphan_text;
						require WPSS_PLUGIN_DIR . 'templates/partials/requirement-answer.php';
						?>
					</div>
				</div>
			<?php endforeach; ?>

			<?php
			// Attachments the buyer uploaded that no configured file question
			// claims. Same reasoning: a delivered brief must not vanish
			// because the question it answered was removed.
			$orphan_attachments = array();
			foreach ( (array) $submitted_attachments as $att ) {
				if ( ! empty( $att['key'] ) && in_array( (string) $att['key'], $rendered_keys, true ) ) {
					continue;
				}
				$orphan_attachments[] = $att;
			}
			?>

			<?php if ( $orphan_attachments ) : ?>
				<div class="wpss-requirement-view">
					<h4 class="wpss-requirement-view__question"><?php esc_html_e( 'Files the buyer attached', 'wp-sell-services' ); ?></h4>
					<div class="wpss-requirement-view__answer">
						<ul class="wpss-requirement-view__files">
							<?php foreach ( $orphan_attachments as $orphan_att ) : ?>
								<?php
								$orphan_att['order_id'] = $wpss_order->id;
								$orphan_url             = function_exists( 'wpss_get_order_file_url' ) ? wpss_get_order_file_url( $orphan_att ) : '';
								$orphan_name            = wpss_format_attachment_name( (string) ( $orphan_att['name'] ?? '' ) );
								?>
								<li>
									<?php if ( $orphan_url ) : ?>
										<a href="<?php echo esc_url( $orphan_url ); ?>" rel="nofollow"><?php echo esc_html( $orphan_name ); ?></a>
									<?php else : ?>
										<?php echo esc_html( $orphan_name ); ?>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</section>
<?php endif; ?>

<?php if ( $wpss_not_provided ) : ?>
	<section class="wpss-order-section wpss-order-section--requirements-view">
		<div class="wpss-order-section__header">
			<h2 class="wpss-order-section__title">
				<i data-lucide="clipboard-check" class="wpss-icon" aria-hidden="true"></i>
				<?php esc_html_e( 'Order Requirements', 'wp-sell-services' ); ?>
			</h2>
		</div>
		<div class="wpss-order-section__body">
			<div class="wpss-notice wpss-notice--warning">
				<p class="wpss-notice__text">
					<strong><?php esc_html_e( 'Note:', 'wp-sell-services' ); ?></strong>
					<?php esc_html_e( 'No requirements were formally submitted for this order. Below are the questions the service requires:', 'wp-sell-services' ); ?>
				</p>
			</div>
			<?php foreach ( $service_requirements as $index => $requirement ) : ?>
				<?php
				$question = $requirement['label'];
				$required = $requirement['required'];
				?>
				<div class="wpss-requirement-view">
					<h4 class="wpss-requirement-view__question">
						<?php echo esc_html( $question ); ?>
						<?php if ( $required ) : ?>
							<span class="wpss-required">*</span>
						<?php endif; ?>
					</h4>
					<div class="wpss-requirement-view__answer">
						<span class="wpss-text-muted wpss-text-italic">
							<?php esc_html_e( 'Not provided', 'wp-sell-services' ); ?>
						</span>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</section>
<?php endif; ?>

<?php if ( 'admin' === $wpss_viewer && ! $wpss_has_submitted && ! $wpss_not_provided ) : ?>
	<section class="wpss-order-section wpss-order-section--requirements-view">
		<div class="wpss-order-section__header">
			<h2 class="wpss-order-section__title">
				<i data-lucide="clipboard-check" class="wpss-icon" aria-hidden="true"></i>
				<?php esc_html_e( 'Order Requirements', 'wp-sell-services' ); ?>
			</h2>
		</div>
		<div class="wpss-order-section__body">
			<p class="wpss-text-muted">
				<?php
				echo esc_html(
					empty( $service_requirements )
						? __( 'This service asks the buyer for no requirements.', 'wp-sell-services' )
						: __( 'The buyer has not submitted requirements yet.', 'wp-sell-services' )
				);
				?>
			</p>
		</div>
	</section>
<?php endif; ?>
