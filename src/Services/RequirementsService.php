<?php
/**
 * Requirements Service
 *
 * Handles order requirements submission and validation.
 *
 * @package WPSellServices\Services
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Services;

defined( 'ABSPATH' ) || exit;

use WPSellServices\Models\ServiceOrder;
use WPSellServices\CustomFields\FieldValidator;

/**
 * Manages order requirements submission.
 *
 * @since 1.0.0
 */
class RequirementsService {

	/**
	 * Order service instance.
	 *
	 * @var OrderService
	 */
	private OrderService $order_service;

	/**
	 * Field validator instance.
	 *
	 * @var FieldValidator
	 */
	private FieldValidator $validator;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->order_service = new OrderService();
		$this->validator     = new FieldValidator();
	}

	/**
	 * Get requirements for an order.
	 *
	 * @param int $order_id Order ID.
	 * @return array|null
	 */
	public function get( int $order_id ): ?array {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_order_requirements';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE order_id = %d ORDER BY submitted_at DESC LIMIT 1",
				$order_id
			)
		);

		if ( ! $row ) {
			return null;
		}

		return array(
			'id'           => (int) $row->id,
			'order_id'     => (int) $row->order_id,
			'field_data'   => json_decode( $row->field_data, true ) ?: array(),
			'attachments'  => json_decode( $row->attachments, true ) ?: array(),
			'submitted_at' => $row->submitted_at,
		);
	}

	/**
	 * Get service requirements fields.
	 *
	 * @param int $service_id Service post ID.
	 * @return array
	 */
	public function get_service_fields( int $service_id ): array {
		$requirements = get_post_meta( $service_id, '_wpss_requirements', true );

		if ( empty( $requirements ) || ! is_array( $requirements ) ) {
			return array();
		}

		return $requirements;
	}

	/**
	 * Submit requirements for an order.
	 *
	 * @param int   $order_id   Order ID.
	 * @param array $field_data Submitted field data.
	 * @param array $files      Uploaded files.
	 * @return array Result with success status and message.
	 */
	public function submit( int $order_id, array $field_data, array $files = array() ): array {
		$order = $this->order_service->get( $order_id );

		if ( ! $order ) {
			return array(
				'success' => false,
				'message' => __( 'Order not found.', 'wp-sell-services' ),
			);
		}

		// Check if order is in correct status.
		$allowed_status = ServiceOrder::STATUS_PENDING_REQUIREMENTS === $order->status;

		// Allow late submission if enabled and order is in_progress without existing requirements.
		$is_late_submission = false;
		if ( ! $allowed_status && ServiceOrder::STATUS_IN_PROGRESS === $order->status ) {
			$allow_late_submission = wpss_allow_late_requirements_submission();
			$has_existing          = $this->has_requirements( $order_id );

			if ( $allow_late_submission && ! $has_existing ) {
				$allowed_status     = true;
				$is_late_submission = true;
			}
		}

		if ( ! $allowed_status ) {
			return array(
				'success' => false,
				'message' => __( 'Requirements cannot be submitted for this order status.', 'wp-sell-services' ),
			);
		}

		// Get service requirements.
		$service = $order->get_service();

		// For buyer request orders (platform='request'), skip service requirement validation
		// Requirements were already collected in the proposal, so just save submitted data.
		if ( ! $service && 'request' === $order->platform ) {
			// Sanitize field data since we skip service-field validation.
			$field_data = array_map( function ( $value ) {
				return is_string( $value ) ? sanitize_textarea_field( $value ) : $value;
			}, $field_data );

			// Process file uploads.
			$attachments = $this->process_uploads( $files, $order_id );

			// Save requirements directly without service field validation.
			$saved = $this->save( $order_id, $field_data, $attachments );

			if ( ! $saved ) {
				return array(
					'success' => false,
					'message' => __( 'Failed to save requirements. Please try again.', 'wp-sell-services' ),
				);
			}

			// Start order work.
			$this->order_service->start_work( $order_id );

			return array(
				'success'         => true,
				'message'         => __( 'Requirements submitted successfully. The vendor will start working on your order.', 'wp-sell-services' ),
				'late_submission' => false,
			);
		}

		if ( ! $service ) {
			return array(
				'success' => false,
				'message' => __( 'Service not found.', 'wp-sell-services' ),
			);
		}

		$fields = $this->get_service_fields( $service->id );

		// Validate requirements.
		$validation = $this->validate( $fields, $field_data, $files );

		if ( ! $validation['valid'] ) {
			return array(
				'success' => false,
				'message' => __( 'Please fix the following errors:', 'wp-sell-services' ),
				'errors'  => $validation['errors'],
			);
		}

		// Process file uploads.
		$attachments = $this->process_uploads( $files, $order_id );

		// Save requirements.
		$saved = $this->save( $order_id, $field_data, $attachments );

		if ( ! $saved ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to save requirements. Please try again.', 'wp-sell-services' ),
			);
		}

		// Start order work (only if not a late submission - order is already in progress).
		if ( ! $is_late_submission ) {
			$this->order_service->start_work( $order_id );
		}

		$success_message = $is_late_submission
			? __( 'Requirements submitted successfully. The vendor has been notified.', 'wp-sell-services' )
			: __( 'Requirements submitted successfully. The vendor will start working on your order.', 'wp-sell-services' );

		return array(
			'success'         => true,
			'message'         => $success_message,
			'late_submission' => $is_late_submission,
		);
	}

	/**
	 * Validate submitted requirements.
	 *
	 * @param array $fields     Service requirement fields.
	 * @param array $field_data Submitted data.
	 * @param array $files      Uploaded files.
	 * @return array Validation result.
	 */
	public function validate( array $fields, array $field_data, array $files = array() ): array {
		$errors = array();

		foreach ( $fields as $index => $field ) {
			// Use 'question' as the primary key since that is what the AJAX handler uses.
			// Fall back to 'label' for backward compatibility with older field definitions.
			$field_key   = $field['question'] ?? $field['label'] ?? "field_{$index}";
			$field_label = $field['question'] ?? $field['label'] ?? "Field {$index}";
			$value       = $field_data[ $field_key ] ?? '';
			$required    = ! empty( $field['required'] );
			$type        = $field['type'] ?? 'text';

			// Check required fields.
			if ( $required ) {
				if ( 'file' === $type ) {
					if ( empty( $files[ $field_key ] ) || empty( $files[ $field_key ]['name'] ) ) {
						$errors[ $field_key ] = sprintf(
							/* translators: %s: field label */
							__( '%s is required.', 'wp-sell-services' ),
							$field_label
						);
						continue;
					}
				} elseif ( '' === $value || ( is_array( $value ) && empty( $value ) ) ) {
					$errors[ $field_key ] = sprintf(
						/* translators: %s: field label */
						__( '%s is required.', 'wp-sell-services' ),
						$field_label
					);
					continue;
				}
			}

			// Type-specific validation.
			if ( '' !== $value ) {
				switch ( $type ) {
					case 'number':
						if ( ! is_numeric( $value ) ) {
							$errors[ $field_key ] = sprintf(
								/* translators: %s: field label */
								__( '%s must be a number.', 'wp-sell-services' ),
								$field_label
							);
						}
						break;

					case 'select':
					case 'radio':
						$choices = $this->parse_choices( $field['choices'] ?? '' );
						if ( ! in_array( $value, $choices, true ) ) {
							$errors[ $field_key ] = sprintf(
								/* translators: %s: field label */
								__( 'Invalid selection for %s.', 'wp-sell-services' ),
								$field_label
							);
						}
						break;

					case 'checkbox':
						if ( ! empty( $field['choices'] ) ) {
							$choices = $this->parse_choices( $field['choices'] );
							$values  = is_array( $value ) ? $value : array( $value );
							foreach ( $values as $v ) {
								if ( ! in_array( $v, $choices, true ) ) {
									$errors[ $field_key ] = sprintf(
										/* translators: %s: field label */
										__( 'Invalid selection for %s.', 'wp-sell-services' ),
										$field_label
									);
									break;
								}
							}
						}
						break;
				}
			}
		}

		return array(
			'valid'  => empty( $errors ),
			'errors' => $errors,
		);
	}

	/**
	 * Parse choices from string or array.
	 *
	 * @param string|array $choices Choices.
	 * @return array
	 */
	private function parse_choices( $choices ): array {
		if ( is_array( $choices ) ) {
			return $choices;
		}

		if ( empty( $choices ) ) {
			return array();
		}

		return array_map( 'trim', explode( ',', $choices ) );
	}

	/**
	 * Process uploaded files.
	 *
	 * @param array $files    Files from $_FILES.
	 * @param int   $order_id Order ID.
	 * @return array Processed attachment data.
	 */
	private function process_uploads( array $files, int $order_id ): array {
		$attachments = array();

		if ( empty( $files ) ) {
			return $attachments;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		foreach ( $files as $key => $file ) {
			if ( empty( $file['name'] ) || UPLOAD_ERR_OK !== $file['error'] ) {
				continue;
			}

			// Check file type.
			$allowed_types = $this->get_allowed_file_types();
			$file_type     = wp_check_filetype( $file['name'] );

			if ( ! in_array( $file_type['ext'], $allowed_types, true ) ) {
				continue;
			}

			// Check file size (max 50MB).
			$max_size = 50 * 1024 * 1024;
			if ( $file['size'] > $max_size ) {
				continue;
			}

			// Upload file.
			$upload = wp_handle_upload( $file, array( 'test_form' => false ) );

			if ( isset( $upload['error'] ) ) {
				continue;
			}

			// Create attachment.
			$attachment_id = wp_insert_attachment(
				array(
					'post_mime_type' => $upload['type'],
					'post_title'     => sanitize_file_name( $file['name'] ),
					'post_content'   => '',
					'post_status'    => 'private',
				),
				$upload['file']
			);

			if ( ! is_wp_error( $attachment_id ) ) {
				wp_update_attachment_metadata(
					$attachment_id,
					wp_generate_attachment_metadata( $attachment_id, $upload['file'] )
				);

				$attachments[] = array(
					'id'   => $attachment_id,
					'key'  => $key,
					'name' => $file['name'],
					'url'  => $upload['url'],
					'type' => $upload['type'],
					'size' => $file['size'],
				);
			}
		}

		return $attachments;
	}

	/**
	 * Get allowed file types.
	 *
	 * @return array
	 */
	private function get_allowed_file_types(): array {
		$types = array(
			'jpg',
			'jpeg',
			'png',
			'gif',
			'webp',
			'pdf',
			'doc',
			'docx',
			'xls',
			'xlsx',
			'ppt',
			'pptx',
			'txt',
			'rtf',
			'csv',
			'zip',
			'rar',
			'7z',
			'mp3',
			'wav',
			'mp4',
			'mov',
			'avi',
			'psd',
			'ai',
			'eps',
			'svg',
		);

		/**
		 * Filter allowed file types for requirements.
		 *
		 * @param array $types Allowed extensions.
		 */
		return apply_filters( 'wpss_requirements_allowed_file_types', $types );
	}

	/**
	 * Save requirements to database.
	 *
	 * @param int   $order_id    Order ID.
	 * @param array $field_data  Field data.
	 * @param array $attachments Attachments.
	 * @return bool
	 */
	private function save( int $order_id, array $field_data, array $attachments ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_order_requirements';

		// Use transaction to prevent data loss if insert fails after delete.
		$wpdb->query( 'START TRANSACTION' );

		// Delete existing requirements if any.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $table, array( 'order_id' => $order_id ) );

		// Insert new requirements.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->insert(
			$table,
			array(
				'order_id'     => $order_id,
				'field_data'   => wp_json_encode( $field_data ),
				'attachments'  => wp_json_encode( $attachments ),
				'submitted_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		$wpdb->query( 'COMMIT' );

		if ( $result ) {
			/**
			 * Fires after requirements are submitted.
			 *
			 * @param int   $order_id    Order ID.
			 * @param array $field_data  Submitted data.
			 * @param array $attachments Uploaded attachments.
			 */
			do_action( 'wpss_requirements_submitted', $order_id, $field_data, $attachments );
		}

		return (bool) $result;
	}

	/**
	 * Check if order has submitted requirements.
	 *
	 * @param int $order_id Order ID.
	 * @return bool
	 */
	public function has_requirements( int $order_id ): bool {
		return null !== $this->get( $order_id );
	}

	/**
	 * Get formatted requirements for display.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function get_formatted( int $order_id ): array {
		$requirements = $this->get( $order_id );

		if ( ! $requirements ) {
			return array();
		}

		$order = $this->order_service->get( $order_id );
		if ( ! $order ) {
			return array();
		}

		$service = $order->get_service();
		if ( ! $service ) {
			return array();
		}

		$fields    = $this->get_service_fields( $service->id );
		$formatted = array();

		foreach ( $fields as $field ) {
			// Use 'question' as the primary key, matching what the AJAX handler stores.
			$key   = $field['question'] ?? $field['label'] ?? '';
			$value = $requirements['field_data'][ $key ] ?? '';

			$formatted[] = array(
				'label' => $field['question'] ?? $field['label'] ?? '',
				'type'  => $field['type'] ?? 'text',
				'value' => $this->format_value( $value, $field['type'] ?? 'text' ),
			);
		}

		// Add attachments.
		if ( ! empty( $requirements['attachments'] ) ) {
			$formatted[] = array(
				'label' => __( 'Attachments', 'wp-sell-services' ),
				'type'  => 'attachments',
				'value' => $requirements['attachments'],
			);
		}

		return $formatted;
	}

	/**
	 * Format value for display.
	 *
	 * @param mixed  $value Value.
	 * @param string $type  Field type.
	 * @return string
	 */
	private function format_value( $value, string $type ): string {
		if ( is_array( $value ) ) {
			return implode( ', ', $value );
		}

		if ( 'checkbox' === $type && $value ) {
			return __( 'Yes', 'wp-sell-services' );
		}

		return (string) $value;
	}
}
