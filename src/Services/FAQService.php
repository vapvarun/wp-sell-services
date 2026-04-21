<?php
/**
 * FAQ Service
 *
 * @package WPSellServices\Services
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Manages service FAQs.
 *
 * @since 1.0.0
 */
class FAQService {

	/**
	 * Meta key for FAQ data.
	 */
	private const META_KEY = '_wpss_faqs';

	/**
	 * Get FAQs for a service.
	 *
	 * @param int $service_id Service ID.
	 * @return array FAQ items.
	 */
	public function get_faqs( int $service_id ): array {
		$faqs = get_post_meta( $service_id, self::META_KEY, true );
		return is_array( $faqs ) ? $faqs : [];
	}

	/**
	 * Save FAQs for a service.
	 *
	 * @param int   $service_id Service ID.
	 * @param array $faqs       FAQ items.
	 * @return bool
	 */
	public function save_faqs( int $service_id, array $faqs ): bool {
		$sanitized = [];

		foreach ( $faqs as $faq ) {
			$question = sanitize_text_field( $faq['question'] ?? '' );
			$answer   = wp_kses_post( $faq['answer'] ?? '' );

			if ( $question && $answer ) {
				$sanitized[] = [
					'question' => $question,
					'answer'   => $answer,
				];
			}
		}

		return (bool) update_post_meta( $service_id, self::META_KEY, $sanitized );
	}

	/**
	 * Add FAQ to service.
	 *
	 * @param int    $service_id Service ID.
	 * @param string $question   Question text.
	 * @param string $answer     Answer text.
	 * @return bool
	 */
	public function add_faq( int $service_id, string $question, string $answer ): bool {
		$faqs = $this->get_faqs( $service_id );

		$faqs[] = [
			'question' => sanitize_text_field( $question ),
			'answer'   => wp_kses_post( $answer ),
		];

		return $this->save_faqs( $service_id, $faqs );
	}

	/**
	 * Update FAQ at index.
	 *
	 * @param int    $service_id Service ID.
	 * @param int    $index      FAQ index.
	 * @param string $question   Question text.
	 * @param string $answer     Answer text.
	 * @return bool
	 */
	public function update_faq( int $service_id, int $index, string $question, string $answer ): bool {
		$faqs = $this->get_faqs( $service_id );

		if ( ! isset( $faqs[ $index ] ) ) {
			return false;
		}

		$faqs[ $index ] = [
			'question' => sanitize_text_field( $question ),
			'answer'   => wp_kses_post( $answer ),
		];

		return $this->save_faqs( $service_id, $faqs );
	}

	/**
	 * Remove FAQ at index.
	 *
	 * @param int $service_id Service ID.
	 * @param int $index      FAQ index.
	 * @return bool
	 */
	public function remove_faq( int $service_id, int $index ): bool {
		$faqs = $this->get_faqs( $service_id );

		if ( ! isset( $faqs[ $index ] ) ) {
			return false;
		}

		array_splice( $faqs, $index, 1 );

		return $this->save_faqs( $service_id, $faqs );
	}

	/**
	 * Reorder FAQs.
	 *
	 * @param int   $service_id Service ID.
	 * @param array $order      New order (array of indices).
	 * @return bool
	 */
	public function reorder( int $service_id, array $order ): bool {
		$faqs      = $this->get_faqs( $service_id );
		$reordered = [];

		foreach ( $order as $index ) {
			if ( isset( $faqs[ $index ] ) ) {
				$reordered[] = $faqs[ $index ];
			}
		}

		return $this->save_faqs( $service_id, $reordered );
	}

	/**
	 * Render FAQs HTML.
	 *
	 * @param int   $service_id Service ID.
	 * @param array $args       Display arguments.
	 * @return string HTML output.
	 */
	public function render( int $service_id, array $args = [] ): string {
		$defaults = [
			'class'      => 'wpss-faqs',
			'accordion'  => true,
			'show_title' => true,
			'title'      => __( 'Frequently Asked Questions', 'wp-sell-services' ),
		];

		$args = wp_parse_args( $args, $defaults );
		$faqs = $this->get_faqs( $service_id );

		if ( empty( $faqs ) ) {
			return '';
		}

		$accordion_class = $args['accordion'] ? 'wpss-faqs-accordion' : '';

		ob_start();
		?>
		<div class="<?php echo esc_attr( $args['class'] . ' ' . $accordion_class ); ?>">
			<?php if ( $args['show_title'] ) : ?>
				<h3 class="wpss-faqs-title"><?php echo esc_html( $args['title'] ); ?></h3>
			<?php endif; ?>

			<div class="wpss-faqs-list">
				<?php foreach ( $faqs as $index => $faq ) : ?>
					<div class="wpss-faq-item" data-index="<?php echo esc_attr( $index ); ?>">
						<div class="wpss-faq-question" role="button" aria-expanded="false">
							<span class="wpss-faq-question-text"><?php echo esc_html( $faq['question'] ); ?></span>
							<i data-lucide="chevron-down" class="wpss-icon wpss-faq-toggle" aria-hidden="true"></i>
						</div>
						<div class="wpss-faq-answer" aria-hidden="true">
							<?php echo wp_kses_post( $faq['answer'] ); ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<?php if ( $args['accordion'] ) : ?>
			<script>
			document.querySelectorAll('.wpss-faqs-accordion .wpss-faq-question').forEach(function(question) {
				question.addEventListener('click', function() {
					var item = this.closest('.wpss-faq-item');
					var answer = item.querySelector('.wpss-faq-answer');
					var isOpen = this.getAttribute('aria-expanded') === 'true';

					this.setAttribute('aria-expanded', !isOpen);
					answer.setAttribute('aria-hidden', isOpen);
					item.classList.toggle('wpss-faq-open', !isOpen);
				});
			});
			</script>
		<?php endif; ?>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render admin FAQ manager.
	 *
	 * @param int $service_id Service ID.
	 * @return string HTML output.
	 */
	public function render_admin( int $service_id ): string {
		$faqs = $this->get_faqs( $service_id );

		ob_start();
		?>
		<div class="wpss-faqs-admin" data-service-id="<?php echo esc_attr( $service_id ); ?>">
			<div class="wpss-faqs-list" id="wpss-faqs-list">
				<?php foreach ( $faqs as $index => $faq ) : ?>
					<?php echo $this->render_admin_item( $faq, $index ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endforeach; ?>
			</div>

			<button type="button" class="button wpss-add-faq" id="wpss-add-faq">
				<i data-lucide="plus" class="wpss-icon" aria-hidden="true"></i>
				<?php esc_html_e( 'Add FAQ', 'wp-sell-services' ); ?>
			</button>

			<input type="hidden" name="wpss_faqs" id="wpss-faqs-data" value="<?php echo esc_attr( wp_json_encode( $faqs ) ); ?>">
		</div>

		<template id="wpss-faq-template">
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_admin_item() returns escaped HTML.
			echo $this->render_admin_item(
				[
					'question' => '',
					'answer'   => '',
				],
				'{{INDEX}}'
			);
			?>
		</template>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render admin FAQ item.
	 *
	 * @param array      $faq   FAQ data.
	 * @param int|string $index Item index.
	 * @return string HTML output.
	 */
	private function render_admin_item( array $faq, $index ): string {
		ob_start();
		?>
		<div class="wpss-faq-admin-item" data-index="<?php echo esc_attr( $index ); ?>">
			<div class="wpss-faq-header">
				<i data-lucide="move" class="wpss-icon wpss-faq-drag" aria-hidden="true"></i>
				<span class="wpss-faq-number"><?php echo esc_html( is_numeric( $index ) ? $index + 1 : '#' ); ?></span>
				<button type="button" class="wpss-faq-remove" title="<?php esc_attr_e( 'Remove', 'wp-sell-services' ); ?>">
					<i data-lucide="x-circle" class="wpss-icon" aria-hidden="true"></i>
				</button>
			</div>
			<div class="wpss-faq-fields">
				<div class="wpss-faq-field">
					<label><?php esc_html_e( 'Question', 'wp-sell-services' ); ?></label>
					<input type="text" name="wpss_faqs[<?php echo esc_attr( $index ); ?>][question]" value="<?php echo esc_attr( $faq['question'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Enter question...', 'wp-sell-services' ); ?>">
				</div>
				<div class="wpss-faq-field">
					<label><?php esc_html_e( 'Answer', 'wp-sell-services' ); ?></label>
					<textarea name="wpss_faqs[<?php echo esc_attr( $index ); ?>][answer]" rows="3" placeholder="<?php esc_attr_e( 'Enter answer...', 'wp-sell-services' ); ?>"><?php echo esc_textarea( $faq['answer'] ?? '' ); ?></textarea>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Generate FAQ schema markup.
	 *
	 * @param int $service_id Service ID.
	 * @return array Schema data.
	 */
	public function get_schema( int $service_id ): array {
		$faqs = $this->get_faqs( $service_id );

		if ( empty( $faqs ) ) {
			return [];
		}

		$questions = [];

		foreach ( $faqs as $faq ) {
			$questions[] = [
				'@type'          => 'Question',
				'name'           => $faq['question'],
				'acceptedAnswer' => [
					'@type' => 'Answer',
					'text'  => wp_strip_all_tags( $faq['answer'] ),
				],
			];
		}

		return [
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $questions,
		];
	}
}
