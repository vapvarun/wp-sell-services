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
	 * Constructor.
	 */
	public function __construct() {
		$this->order_service = new OrderService();
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
		return wpss_get_service_requirements( $service_id );
	}

	/**
	 * Submit requirements for an order.
	 *
	 * The one path for the buyer form (AJAX), the app (REST) and anything
	 * else: validates and sanitises against the service's requirement schema,
	 * stores the answers keyed by requirement id, and starts the order.
	 *
	 * @param int                  $order_id    Order ID.
	 * @param array<string, mixed> $field_data  Submitted answers keyed by requirement id.
	 * @param array<string, mixed> $files       Uploaded files ($_FILES entries) keyed by requirement id.
	 * @param array<int, mixed>    $attachments Files already stored (REST upload flow), appended as-is.
	 * @return array{success: bool, message: string, errors?: array<string,string>, submitted?: array<string,mixed>, late_submission?: bool}
	 */
	public function submit( int $order_id, array $field_data, array $files = array(), array $attachments = array() ): array {
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
			$field_data  = $this->sanitize( array(), $field_data );
			$attachments = array_merge( $attachments, $this->process_uploads( $files, $order_id ) );

			// Save requirements directly without service field validation.
			$saved = $this->save( $order_id, $field_data, $attachments );

			if ( ! $saved ) {
				return array(
					'success' => false,
					'message' => __( 'Failed to save requirements. Please try again.', 'wp-sell-services' ),
				);
			}

			// Advance the order to in_progress. Requirements are already saved at
			// this point, so a failed transition must NOT be reported as success —
			// otherwise the order is silently stuck in pending_requirements while
			// the buyer is told the vendor has started.
			if ( ! $this->order_service->start_work( $order_id ) ) {
				return array(
					'success' => false,
					'message' => __( 'Requirements were saved but the order could not be started. Please contact support.', 'wp-sell-services' ),
				);
			}

			return array(
				'success'         => true,
				'message'         => __( 'Requirements submitted successfully. The vendor will start working on your order.', 'wp-sell-services' ),
				'submitted'       => $field_data,
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

		$field_data  = $this->sanitize( $fields, $field_data );
		$attachments = array_merge( $attachments, $this->process_uploads( $files, $order_id ) );

		$saved = $this->save( $order_id, $field_data, $attachments );

		if ( ! $saved ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to save requirements. Please try again.', 'wp-sell-services' ),
			);
		}

		// Start order work (only if not a late submission - order is already in
		// progress). The transition to in_progress is the source of truth for
		// the order timeline; if it fails, the requirements are saved but the
		// order is stuck, so surface that instead of a false success.
		if ( ! $is_late_submission && ! $this->order_service->start_work( $order_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'Requirements were saved but the order could not be started. Please contact support.', 'wp-sell-services' ),
			);
		}

		$success_message = $is_late_submission
			? __( 'Requirements submitted successfully. The vendor has been notified.', 'wp-sell-services' )
			: __( 'Requirements submitted successfully. The vendor will start working on your order.', 'wp-sell-services' );

		return array(
			'success'         => true,
			'message'         => $success_message,
			'submitted'       => $field_data,
			'late_submission' => $is_late_submission,
		);
	}

	/**
	 * Validate submitted requirements against the service's schema.
	 *
	 * Answers are keyed by requirement id. A required field must be present
	 * (a file, for file fields); a present value must fit its type.
	 *
	 * @param array<int, array<string, mixed>> $fields     Normalised requirement rows.
	 * @param array<string, mixed>             $field_data Submitted answers keyed by requirement id.
	 * @param array<string, mixed>             $files      Uploaded files keyed by requirement id.
	 * @return array{valid: bool, errors: array<string, string>}
	 */
	public function validate( array $fields, array $field_data, array $files = array() ): array {
		$errors = array();

		foreach ( $fields as $field ) {
			$id    = $field['id'];
			$label = $field['label'];
			$type  = $field['type'];
			$value = $field_data[ $id ] ?? '';
			$empty = '' === $value || ( is_array( $value ) && empty( $value ) );

			if ( 'file' === $type ) {
				if ( ! empty( $field['required'] ) && empty( $files[ $id ]['name'] ) ) {
					/* translators: %s: field label */
					$errors[ $id ] = sprintf( __( '%s is required.', 'wp-sell-services' ), $label );
				}
				continue;
			}

			if ( $empty ) {
				if ( ! empty( $field['required'] ) ) {
					/* translators: %s: field label */
					$errors[ $id ] = sprintf( __( '%s is required.', 'wp-sell-services' ), $label );
				}
				continue;
			}

			$multi = in_array( $type, array( 'multiselect', 'checkbox' ), true ) && ! empty( $field['options'] );

			if ( is_array( $value ) !== $multi ) {
				/* translators: %s: field label */
				$errors[ $id ] = sprintf( __( 'Invalid value for %s.', 'wp-sell-services' ), $label );
				continue;
			}

			$bad = false;
			switch ( $type ) {
				case 'number':
					$bad = ! is_numeric( $value );
					break;
				case 'url':
					$bad = false === filter_var( $value, FILTER_VALIDATE_URL );
					break;
				case 'email':
					$bad = ! is_email( (string) $value );
					break;
				case 'date':
					$bad = false === strtotime( (string) $value );
					break;
				case 'select':
				case 'radio':
					$bad = ! in_array( (string) $value, $field['options'], true );
					break;
				case 'multiselect':
				case 'checkbox':
					foreach ( (array) $value as $v ) {
						if ( $multi && ! in_array( (string) $v, $field['options'], true ) ) {
							$bad = true;
						}
					}
					break;
			}

			if ( $bad ) {
				/* translators: %s: field label */
				$errors[ $id ] = sprintf( __( 'Invalid value for %s.', 'wp-sell-services' ), $label );
			}
		}

		return array(
			'valid'  => empty( $errors ),
			'errors' => $errors,
		);
	}

	/**
	 * Sanitise submitted answers by field type.
	 *
	 * Keys that no requirement claims (the default form's `description` and
	 * `additional_notes`) are kept as text, so a brief written against a
	 * service with no questions still reaches the vendor.
	 *
	 * @param array<int, array<string, mixed>> $fields     Normalised requirement rows.
	 * @param array<string, mixed>             $field_data Submitted answers keyed by requirement id.
	 * @return array<string, mixed>
	 */
	public function sanitize( array $fields, array $field_data ): array {
		$types = array();
		foreach ( $fields as $field ) {
			$types[ $field['id'] ] = $field['type'];
		}

		$clean = array();
		foreach ( $field_data as $key => $value ) {
			$key  = sanitize_key( (string) $key );
			$type = $types[ $key ] ?? 'textarea';

			if ( '' === $key || 'file' === $type ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$clean[ $key ] = array_values( array_map( 'sanitize_text_field', array_filter( $value, 'is_scalar' ) ) );
				continue;
			}

			$value = (string) $value;
			switch ( $type ) {
				case 'number':
					$clean[ $key ] = is_numeric( $value ) ? $value + 0 : '';
					break;
				case 'url':
					$clean[ $key ] = esc_url_raw( $value );
					break;
				case 'email':
					$clean[ $key ] = sanitize_email( $value );
					break;
				case 'textarea':
					$clean[ $key ] = sanitize_textarea_field( $value );
					break;
				default:
					$clean[ $key ] = sanitize_text_field( $value );
			}
		}

		return $clean;
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

		$files = self::flatten_file_inputs( $files );

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

			// A buyer's brief is private by any reasonable reading, and the docs
			// say so - but post_status 'private' hides the attachment ROW while
			// wp_handle_upload() had already written the bytes into the public
			// uploads tree, so anyone holding the URL could read it forever
			// (Basecamp 10239807824). wpss_store_order_file() writes outside the
			// web root and hands back an id, not a URL.
			$stored = wpss_store_order_file( $file, $order_id, 'requirement' );

			if ( $stored ) {
				$stored['key'] = $key;
				$attachments[] = $stored;
			}
		}

		return $attachments;
	}

	/**
	 * Normalise PHP's multi-file $_FILES shape into one entry per file.
	 *
	 * The requirements template has always rendered `name="requirements_files[]"`,
	 * which makes PHP hand over ONE entry whose every key is an array:
	 * `['name' => ['a.pdf'], 'error' => [0], ...]`. The loop below reads
	 * `$file['error']` expecting an int, so `UPLOAD_ERR_OK !== $file['error']`
	 * compared an int to an array, was always true, and skipped every attachment
	 * without a word. Requirement files have therefore never been saved from
	 * that form - the buyer picks a file, sees the success message, and nothing
	 * is stored.
	 *
	 * Single-file entries pass through untouched.
	 *
	 * @since 1.7.0
	 *
	 * @param array<string,mixed> $files Raw $_FILES subset.
	 * @return array<int|string,array<string,mixed>> One entry per uploaded file.
	 */
	private static function flatten_file_inputs( array $files ): array {
		$flat = array();

		foreach ( $files as $key => $file ) {
			if ( ! is_array( $file ) || ! isset( $file['name'] ) ) {
				continue;
			}

			if ( ! is_array( $file['name'] ) ) {
				$flat[ $key ] = $file;
				continue;
			}

			foreach ( array_keys( $file['name'] ) as $i ) {
				$single = array();

				foreach ( array( 'name', 'type', 'tmp_name', 'error', 'size' ) as $prop ) {
					$single[ $prop ] = $file[ $prop ][ $i ] ?? null;
				}

				if ( ! empty( $single['name'] ) ) {
					$flat[ $key . '_' . $i ] = $single;
				}
			}
		}

		return $flat;
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
			$formatted[] = array(
				'label' => $field['label'],
				'type'  => $field['type'],
				'value' => $this->format_value( wpss_requirement_answer( $field, $requirements['field_data'] ), $field['type'] ),
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
