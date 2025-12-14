<?php
/**
 * Field Renderer
 *
 * @package WPSellServices\CustomFields
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\CustomFields;

/**
 * Renders custom fields in forms.
 *
 * @since 1.0.0
 */
class FieldRenderer {

	/**
	 * Field manager instance.
	 *
	 * @var FieldManager
	 */
	private FieldManager $manager;

	/**
	 * Constructor.
	 *
	 * @param FieldManager|null $manager Field manager instance.
	 */
	public function __construct( ?FieldManager $manager = null ) {
		$this->manager = $manager ?? FieldManager::instance();
	}

	/**
	 * Render a requirements form.
	 *
	 * @param array $fields     Array of field configurations.
	 * @param array $values     Current values (for editing).
	 * @param array $form_attrs Form HTML attributes.
	 * @return string HTML output.
	 */
	public function render_form( array $fields, array $values = [], array $form_attrs = [] ): string {
		$defaults = [
			'id'     => 'wpss-requirements-form',
			'class'  => 'wpss-requirements-form',
			'method' => 'post',
		];

		$attrs = wp_parse_args( $form_attrs, $defaults );
		$attr_string = $this->build_attributes( $attrs );

		ob_start();
		?>
		<form <?php echo $attr_string; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php wp_nonce_field( 'wpss_submit_requirements', 'wpss_requirements_nonce' ); ?>

			<div class="wpss-requirements-fields">
				<?php
				foreach ( $fields as $field ) {
					$field_id = $field['id'] ?? '';
					$value = $values[ $field_id ] ?? null;
					echo $this->render_field( $field, $value ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				?>
			</div>

			<div class="wpss-requirements-submit">
				<button type="submit" class="wpss-button wpss-button-primary">
					<?php esc_html_e( 'Submit Requirements', 'wp-sell-services' ); ?>
				</button>
			</div>
		</form>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render a single field.
	 *
	 * @param array $field Field configuration.
	 * @param mixed $value Current value.
	 * @return string HTML output.
	 */
	public function render_field( array $field, $value = null ): string {
		$type = $field['type'] ?? 'text';
		$field_type = $this->manager->get( $type );

		if ( ! $field_type ) {
			return '';
		}

		$field_id = $field['id'] ?? 'field_' . wp_generate_uuid4();
		$required = ! empty( $field['required'] );
		$description = $field['description'] ?? '';

		ob_start();
		?>
		<div class="wpss-field wpss-field-<?php echo esc_attr( $type ); ?>" data-field-id="<?php echo esc_attr( $field_id ); ?>">
			<label class="wpss-field-label" for="<?php echo esc_attr( $field_id ); ?>">
				<?php echo esc_html( $field['label'] ?? '' ); ?>
				<?php if ( $required ) : ?>
					<span class="wpss-required">*</span>
				<?php endif; ?>
			</label>

			<div class="wpss-field-input">
				<?php echo $field_type->render( $field, $value ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>

			<?php if ( $description ) : ?>
				<p class="wpss-field-description"><?php echo esc_html( $description ); ?></p>
			<?php endif; ?>

			<div class="wpss-field-error" style="display: none;"></div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render submitted values (read-only display).
	 *
	 * @param array $fields Array of field configurations.
	 * @param array $values Submitted values.
	 * @return string HTML output.
	 */
	public function render_submitted( array $fields, array $values ): string {
		ob_start();
		?>
		<div class="wpss-requirements-submitted">
			<?php foreach ( $fields as $field ) : ?>
				<?php
				$field_id = $field['id'] ?? '';
				$value = $values[ $field_id ] ?? '';
				$type = $field['type'] ?? 'text';
				$field_type = $this->manager->get( $type );

				if ( ! $field_type || empty( $value ) ) {
					continue;
				}

				$formatted = $field_type->format_value( $value, $field );
				?>
				<div class="wpss-requirement-item">
					<dt class="wpss-requirement-label"><?php echo esc_html( $field['label'] ?? '' ); ?></dt>
					<dd class="wpss-requirement-value"><?php echo wp_kses_post( $formatted ); ?></dd>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render field type picker (for admin builder).
	 *
	 * @return string HTML output.
	 */
	public function render_field_type_picker(): string {
		$types = $this->manager->get_all();

		ob_start();
		?>
		<div class="wpss-field-type-picker">
			<h4><?php esc_html_e( 'Add Field', 'wp-sell-services' ); ?></h4>
			<div class="wpss-field-types">
				<?php foreach ( $types as $type => $field ) : ?>
					<button type="button" class="wpss-field-type-btn" data-type="<?php echo esc_attr( $type ); ?>">
						<span class="dashicons <?php echo esc_attr( $field->get_icon() ); ?>"></span>
						<span class="wpss-field-type-label"><?php echo esc_html( $field->get_label() ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Build HTML attributes string.
	 *
	 * @param array $attrs Attributes array.
	 * @return string
	 */
	private function build_attributes( array $attrs ): string {
		$parts = [];
		foreach ( $attrs as $key => $value ) {
			if ( is_bool( $value ) ) {
				if ( $value ) {
					$parts[] = esc_attr( $key );
				}
			} else {
				$parts[] = esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
			}
		}
		return implode( ' ', $parts );
	}
}
