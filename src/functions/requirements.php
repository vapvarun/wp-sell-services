<?php
/**
 * Service requirement fields and the answers an order collects.
 *
 * @package WPSellServices
 * @since   1.9.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Human label for a submitted requirement key.
 *
 * The buyer's freeform brief is stored under the key `description`, and both
 * the order view and the admin order screen had to decide what to call it.
 * Only the order view did: admin printed the raw key, so the site owner read
 * "description" where the buyer and seller read "What the buyer asked for"
 * (Basecamp 10254444197). One flow, two labels.
 *
 * Any other key is a question the owner configured, so it is titled from its
 * own text rather than renamed.
 *
 * @since 1.7.0
 *
 * @param string $key Submitted field key.
 * @return string Label to show.
 */
function wpss_requirement_field_label( string $key ): string {
	$label = 'description' === $key
		? __( 'What the buyer asked for', 'wp-sell-services' )
		: ucfirst( str_replace( array( '_', '-' ), ' ', $key ) );

	/**
	 * Filter the label shown for a submitted requirement field.
	 *
	 * @since 1.7.0
	 *
	 * @param string $label Resolved label.
	 * @param string $key   Raw field key.
	 */
	return (string) apply_filters( 'wpss_requirement_field_label', $label, $key );
}

/**
 * Get order requirements URL.
 *
 * Pretty shape: `/{dashboard}/orders/{id}/requirements/`.
 *
 * A SUB-ORDER has no requirements step. A tip, a paid extension and a
 * milestone phase are all payments against work that already has its brief, so
 * sending the buyer to `/requirements/` gave them a URL and a page heading that
 * described something the screen was not - the content underneath is a tip
 * receipt or a phase receipt, and correct. Those land on the order itself.
 *
 * Every gateway routes its post-payment redirect through here, so fixing it in
 * one place covers Stripe, PayPal, offline and the intent service rather than
 * four separate redirects that would drift.
 *
 * @param int $order_id Order ID.
 * @return string
 */
function wpss_get_order_requirements_url( int $order_id ): string {
	$order = wpss_get_order( $order_id );

	if ( ! $order ) {
		return '';
	}

	$sub_order_platforms = function_exists( 'wpss_get_sub_order_platforms' ) ? wpss_get_sub_order_platforms() : array();

	if ( in_array( (string) ( $order->platform ?? '' ), $sub_order_platforms, true ) ) {
		return wpss_get_order_url( $order_id );
	}

	$dashboard_url = wpss_get_dashboard_url( 'orders' );

	if ( $dashboard_url ) {
		return wpss_append_dashboard_order( $dashboard_url, $order_id, 'requirements' );
	}

	$order_slug = apply_filters( 'wpss_service_order_slug', 'service-order' );
	return home_url( '/' . $order_slug . '/' . $order_id . '/requirements/' );
}

/**
 * Requirement field types a service may ask a buyer for.
 *
 * @since 1.7.1
 *
 * @return string[]
 */
function wpss_requirement_types(): array {
	return array( 'text', 'textarea', 'number', 'checkbox', 'select', 'multiselect', 'radio', 'file', 'date', 'url', 'email' );
}

/**
 * Requirement types a vendor can choose, with their labels.
 *
 * The one list every authoring surface renders from. Three had drifted: the
 * frontend wizard hardcoded four options, the admin metabox returned eight, and
 * the canonical whitelist above allows eleven. A vendor using the wizard - the
 * primary authoring path - could not add Number, Yes/No, Multiple Choice or
 * Date at all, though every one of them stores, renders and submits correctly
 * on the buyer's requirements form.
 *
 * Worse than the missing four: the two lists disagreed on what a label MEANT.
 * The wizard called `select` "Multiple Choice"; the metabox calls `select`
 * "Dropdown" and reserves "Multiple Choice" for `radio`. The same words
 * produced a different field depending on where the vendor was standing.
 *
 * Narrower than wpss_requirement_types() on purpose: `multiselect`, `url` and
 * `email` stay accepted for data already stored and for REST callers, but are
 * not offered in the authoring UI until they have a reviewed editing
 * experience. Widen here and both surfaces pick it up.
 *
 * @since 1.7.1
 *
 * @see Basecamp 10286129293
 *
 * @return array<string, string> Type slug => translated label.
 */
function wpss_requirement_type_labels(): array {
	return array(
		'text'     => __( 'Short Text', 'wp-sell-services' ),
		'textarea' => __( 'Long Text', 'wp-sell-services' ),
		'number'   => __( 'Number', 'wp-sell-services' ),
		'checkbox' => __( 'Yes/No', 'wp-sell-services' ),
		'select'   => __( 'Dropdown', 'wp-sell-services' ),
		'radio'    => __( 'Multiple Choice', 'wp-sell-services' ),
		'file'     => __( 'File Upload', 'wp-sell-services' ),
		'date'     => __( 'Date', 'wp-sell-services' ),
	);
}

/**
 * Requirement types that need a caller-supplied list of choices.
 *
 * @since 1.7.1
 *
 * @return string[]
 */
function wpss_requirement_choice_types(): array {
	return array( 'select', 'radio', 'multiselect' );
}

/**
 * Normalise a requirement list into the one schema every surface reads.
 *
 * Four shapes shared the `_wpss_requirements` key: the wizard wrote
 * question/type/options-string, the admin metabox question/type/choices, REST
 * and ServiceManager field_type/label/description/options-array/is_required,
 * and the REST validator looked for an `id` nothing wrote. Every reader
 * picked one shape, so requirements saved on one surface came up blank on
 * another and REST submissions failed on every field (Basecamp 10264288662).
 *
 * Output rows are exactly {id, label, type, required, options, description}.
 * The id is stable: it is kept when present and otherwise derived from the
 * label and position, so answers keyed by id survive a label edit.
 *
 * @since 1.7.1
 *
 * @param array<int|string, mixed> $raw Requirement rows in any historical shape.
 * @return array<int, array{id: string, label: string, type: string, required: bool, options: string[], description: string}>
 */
function wpss_normalize_service_requirements( array $raw ): array {
	$types   = wpss_requirement_types();
	$aliases = array(
		'multiple' => 'multiselect',
		'dropdown' => 'select',
		'upload'   => 'file',
	);
	$choice  = array( 'select', 'multiselect', 'radio', 'checkbox' );
	$out     = array();
	$seen    = array();

	foreach ( array_values( $raw ) as $index => $req ) {
		if ( ! is_array( $req ) ) {
			continue;
		}

		$label = sanitize_text_field( (string) ( $req['label'] ?? $req['question'] ?? '' ) );
		if ( '' === $label ) {
			continue;
		}

		$type = sanitize_key( (string) ( $req['type'] ?? $req['field_type'] ?? 'text' ) );
		$type = $aliases[ $type ] ?? $type;
		if ( ! in_array( $type, $types, true ) ) {
			$type = 'text';
		}

		$options = array();
		if ( in_array( $type, $choice, true ) ) {
			$raw_options = $req['options'] ?? $req['choices'] ?? array();
			if ( is_string( $raw_options ) ) {
				// A comma list from the wizard / metabox, or JSON from the retired table.
				$raw_options = str_starts_with( ltrim( $raw_options ), '[' ) ? (array) json_decode( $raw_options, true ) : explode( ',', $raw_options );
			}
			foreach ( (array) $raw_options as $key => $value ) {
				// Lists of strings, and the older value => label map, both land here.
				$value = is_scalar( $value ) ? trim( (string) $value ) : '';
				if ( '' === $value && is_string( $key ) ) {
					$value = trim( $key );
				}
				if ( '' !== $value ) {
					$options[] = sanitize_text_field( $value );
				}
			}
			$options = array_values( array_unique( $options ) );
		}

		// A bare number is a row id from the retired table, not a name.
		$id = sanitize_key( (string) ( $req['id'] ?? '' ) );
		if ( '' === $id || ctype_digit( $id ) ) {
			$id = sanitize_key( sanitize_title( $label ) );
			$id = ( '' === $id ? 'req' : $id ) . '-' . $index;
		}
		if ( isset( $seen[ $id ] ) ) {
			$id .= '-' . $index;
		}
		$seen[ $id ] = true;

		$out[] = array(
			'id'          => $id,
			'label'       => $label,
			'type'        => $type,
			'required'    => ! empty( $req['required'] ) || ! empty( $req['is_required'] ),
			'options'     => $options,
			'description' => sanitize_textarea_field( (string) ( $req['description'] ?? '' ) ),
		);
	}

	return $out;
}

/**
 * Get service requirements (questions buyer must answer), normalised.
 *
 * The one reader. Rows are in the shape wpss_normalize_service_requirements()
 * produces whichever surface saved them.
 *
 * @param int $service_id Service ID.
 * @return array<int, array{id: string, label: string, type: string, required: bool, options: string[], description: string}>
 */
function wpss_get_service_requirements( int $service_id ): array {
	$requirements = get_post_meta( $service_id, '_wpss_requirements', true );

	return wpss_normalize_service_requirements( is_array( $requirements ) ? $requirements : array() );
}

/**
 * Whether a paid order has to wait for the buyer's requirements.
 *
 * A service with no requirement questions has nothing to wait for, so its order starts the moment it
 * is paid (docs: "Skipping Requirements"). Buyer-request orders keep their own flow: the brief was
 * collected with the proposal.
 *
 * @since 1.9.0
 *
 * @param object $order Order row or model (needs platform and service_id).
 * @return bool True when the order waits in pending_requirements.
 */
function wpss_order_needs_requirements( object $order ): bool {
	$service_id = (int) ( $order->service_id ?? 0 );

	if ( ! $service_id || 'request' === (string) ( $order->platform ?? '' ) ) {
		return true;
	}

	return ! empty( wpss_get_service_requirements( $service_id ) );
}

/**
 * Save a service's requirements, normalised.
 *
 * The one writer. Callers cap the list with wpss_enforce_service_limits()
 * first; this stores what is left in the canonical shape, or clears the key
 * when nothing is left.
 *
 * @since 1.7.1
 *
 * @param int                      $service_id   Service ID.
 * @param array<int|string, mixed> $requirements Requirement rows in any shape.
 * @return void
 */
function wpss_save_service_requirements( int $service_id, array $requirements ): void {
	$requirements = wpss_normalize_service_requirements( $requirements );

	if ( empty( $requirements ) ) {
		delete_post_meta( $service_id, '_wpss_requirements' );
		return;
	}

	update_post_meta( $service_id, '_wpss_requirements', $requirements );
}

/**
 * The buyer's answer to one requirement.
 *
 * Answers are keyed by the requirement id. Orders submitted before 1.7.1 were
 * keyed by the question text, so that is read as the fallback.
 *
 * @since 1.7.1
 *
 * @param array<string, mixed> $requirement Normalised requirement row.
 * @param array<string, mixed> $answers     Submitted field_data.
 * @return mixed Answer, '' when none.
 */
function wpss_requirement_answer( array $requirement, array $answers ) {
	return $answers[ $requirement['id'] ] ?? $answers[ $requirement['label'] ] ?? '';
}

/**
 * Get submitted order requirements.
 *
 * @param int $order_id Order ID.
 * @return array
 */
function wpss_get_order_requirements( int $order_id ): array {
	global $wpdb;

	// Primed by wpss_prime_order_requirements() for a list; consumed once so a
	// write later in the same request is never answered from here.
	$primed = wpss_prime_order_requirements();

	if ( array_key_exists( $order_id, $primed ) ) {
		$value = $primed[ $order_id ];
		wpss_prime_order_requirements( array(), $order_id );
		return $value;
	}

	// The table is created by the installer, and the installed schema version
	// is an autoloaded option: reading it costs nothing, where the SHOW TABLES
	// this used to run cost one query per order on every list.
	if ( '0.0.0' === (string) get_option( \WPSellServices\Database\SchemaManager::VERSION_OPTION, '0.0.0' ) ) {
		// Fall back to order meta.
		$requirements = get_metadata( 'wpss_order', $order_id, '_requirements', true );
		return is_array( $requirements ) ? $requirements : array();
	}

	$table = $wpdb->prefix . 'wpss_order_requirements';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT field_data FROM {$table} WHERE order_id = %d ORDER BY id DESC LIMIT 1",
			$order_id
		),
		ARRAY_A
	);

	if ( ! $row || empty( $row['field_data'] ) ) {
		return array();
	}

	$decoded = json_decode( $row['field_data'], true );

	return is_array( $decoded ) ? $decoded : array();
}

/**
 * Load the submitted requirements for a page of orders in one query.
 *
 * GET /orders ran wpss_get_order_requirements() per row - two queries each,
 * one of them a SHOW TABLES. Priming here turns that into one SELECT for the
 * page. Each primed value is handed out ONCE by wpss_get_order_requirements()
 * and then forgotten, so a submission written later in the same request reads
 * fresh from the table rather than from a stale prime.
 *
 * @since 1.7.1
 *
 * @param int[]    $order_ids Orders about to be shaped. Empty to only read the store.
 * @param int|null $forget    Internal: drop one primed id after it has been read.
 * @return array<int,array<string,mixed>> The current primed map.
 */
function wpss_prime_order_requirements( array $order_ids = array(), ?int $forget = null ): array {
	static $primed = array();

	if ( null !== $forget ) {
		unset( $primed[ $forget ] );
		return $primed;
	}

	$order_ids = array_values( array_unique( array_filter( array_map( 'intval', $order_ids ) ) ) );

	if ( empty( $order_ids ) || '0.0.0' === (string) get_option( \WPSellServices\Database\SchemaManager::VERSION_OPTION, '0.0.0' ) ) {
		return $primed;
	}

	global $wpdb;

	$table        = $wpdb->prefix . 'wpss_order_requirements';
	$placeholders = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );

	// Ascending id so the newest row for an order overwrites the older ones -
	// the same "ORDER BY id DESC LIMIT 1" the single read uses.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT order_id, field_data FROM {$table} WHERE order_id IN ({$placeholders}) ORDER BY id ASC", $order_ids ), ARRAY_A );

	foreach ( $order_ids as $order_id ) {
		$primed[ $order_id ] = array();
	}

	foreach ( (array) $rows as $row ) {
		$decoded = json_decode( (string) $row['field_data'], true );

		$primed[ (int) $row['order_id'] ] = is_array( $decoded ) ? $decoded : array();
	}

	return $primed;
}

/**
 * Check if late requirements submission is allowed.
 *
 * @since 1.0.0
 *
 * @return bool Whether late requirements submission is enabled.
 */
function wpss_allow_late_requirements_submission(): bool {
	$allow_late = (bool) wpss_get_option( 'orders', 'allow_late_requirements' );

	/**
	 * Filter whether late requirements submission is allowed.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $allow_late Whether late submission is allowed.
	 */
	return (bool) apply_filters( 'wpss_allow_late_requirements_submission', $allow_late );
}
