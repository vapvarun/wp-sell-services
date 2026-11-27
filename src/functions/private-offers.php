<?php
/**
 * Private offers: a vendor sends one buyer a priced offer from a conversation.
 *
 * An offer is the buyer-request path started by the vendor (Basecamp 10551449528). It is stored as a
 * buyer request owned by the buyer with the WordPress `private` status, plus the vendor's proposal on
 * it, plus an `offer` message in the thread. No new table: proposals.request_id is NOT NULL, and the
 * private status keeps the request out of every list, search, feed and sitemap that reads `publish`.
 * Only its buyer, its vendor (wpss_offer_map_read_cap()) and admins may open it. Accepting runs
 * BuyerRequestService::convert_to_order(), so payment, the order, commission and emails are the
 * proposal path's own.
 *
 * @package WPSellServices
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

use WPSellServices\Models\Message;
use WPSellServices\Services\BuyerRequestService;
use WPSellServices\Services\ConversationService;
use WPSellServices\Services\ProposalService;

/**
 * The fields a vendor fills in to send an offer, with their limits.
 *
 * @since 2.0.0
 *
 * @return array<string, array<string, mixed>> Field key => label, type, required, default, min, max.
 */
function wpss_get_private_offer_fields(): array {
	$fields = array(
		'title'         => array(
			'label'    => __( 'What you will do', 'wp-sell-services' ),
			'type'     => 'text',
			'required' => true,
		),
		'description'   => array(
			'label'    => __( 'Details', 'wp-sell-services' ),
			'type'     => 'textarea',
			'required' => true,
		),
		'price'         => array(
			'label'    => __( 'Price', 'wp-sell-services' ),
			'type'     => 'number',
			'required' => true,
			'min'      => 1,
		),
		'delivery_days' => array(
			'label'    => __( 'Delivery (days)', 'wp-sell-services' ),
			'type'     => 'number',
			'required' => true,
			'default'  => 7,
			'min'      => 1,
			'max'      => 365,
		),
		'revisions'     => array(
			'label'   => __( 'Revisions', 'wp-sell-services' ),
			'type'    => 'number',
			'default' => 2,
			'min'     => 0,
			'max'     => 20,
		),
		'expires_days'  => array(
			'label'   => __( 'Offer valid for (days)', 'wp-sell-services' ),
			'type'    => 'number',
			'default' => 7,
			'min'     => 1,
			'max'     => 90,
		),
	);

	/**
	 * Filter the fields of a private offer: labels, defaults and limits.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, array<string, mixed>> $fields Field key => definition.
	 */
	return (array) apply_filters( 'wpss_private_offer_fields', $fields );
}

/**
 * Whether a buyer request is a private offer.
 *
 * @since 2.0.0
 *
 * @param int $request_id Request post ID.
 * @return bool
 */
function wpss_is_private_offer( int $request_id ): bool {
	return $request_id > 0 && (int) get_post_meta( $request_id, '_wpss_private_offer_vendor', true ) > 0;
}

/**
 * Send a private offer to the other member of a direct conversation.
 *
 * @since 2.0.0
 *
 * @param int                 $conversation_id Direct (pre-sale) conversation ID.
 * @param int                 $vendor_id       The vendor sending it.
 * @param array<string,mixed> $data            Field values (wpss_get_private_offer_fields()), plus optional contract_type and milestones.
 * @return array<string,mixed>|\WP_Error The offer as a client sees it, or why it was refused.
 */
function wpss_send_private_offer( int $conversation_id, int $vendor_id, array $data ) {
	$conversations = new ConversationService();
	$conversation  = $conversations->get( $conversation_id );

	if ( ! $conversation || (int) $conversation->order_id > 0 || ! $conversation->can_reply( $vendor_id ) ) {
		return new \WP_Error( 'wpss_offer_conversation', __( 'Offers are sent from a conversation you are part of, before an order exists.', 'wp-sell-services' ), array( 'status' => 403 ) );
	}

	if ( ! wpss_is_vendor( $vendor_id ) ) {
		return new \WP_Error( 'wpss_not_vendor', __( 'Only sellers can send offers.', 'wp-sell-services' ), array( 'status' => 403 ) );
	}

	$standing = wpss_vendor_status_block( $vendor_id );

	if ( $standing ) {
		return $standing;
	}

	$buyers   = array_values( array_diff( array_map( 'intval', $conversation->participants ), array( $vendor_id ) ) );
	$buyer_id = 1 === count( $buyers ) ? $buyers[0] : 0;

	if ( ! $buyer_id || ! get_userdata( $buyer_id ) ) {
		return new \WP_Error( 'wpss_offer_buyer', __( 'An offer goes to one member, the other person in this conversation.', 'wp-sell-services' ), array( 'status' => 400 ) );
	}

	if ( wpss_is_blocked_between( $vendor_id, $buyer_id ) ) {
		return new \WP_Error( 'wpss_blocked', __( 'You cannot send an offer in this conversation.', 'wp-sell-services' ), array( 'status' => 403 ) );
	}

	$values = array();

	foreach ( wpss_get_private_offer_fields() as $key => $field ) {
		$raw = $data[ $key ] ?? ( $field['default'] ?? '' );

		if ( 'number' === ( $field['type'] ?? '' ) ) {
			$value = 'price' === $key ? round( (float) $raw, 2 ) : (int) $raw;
			$ok    = is_numeric( $raw ) && $value >= ( $field['min'] ?? 0 ) && ( ! isset( $field['max'] ) || $value <= $field['max'] );
		} else {
			$value = 'textarea' === ( $field['type'] ?? '' ) ? sanitize_textarea_field( (string) $raw ) : sanitize_text_field( (string) $raw );
			$ok    = '' !== $value || empty( $field['required'] );
		}

		// A milestone offer takes its price and days from the phases (ProposalService::submit()).
		if ( ! $ok && ! ( in_array( $key, array( 'price', 'delivery_days' ), true ) && ProposalService::CONTRACT_TYPE_MILESTONE === ( $data['contract_type'] ?? '' ) ) ) {
			return new \WP_Error(
				'wpss_offer_field',
				/* translators: %s: field label. */
				sprintf( __( 'Please check "%s".', 'wp-sell-services' ), $field['label'] ?? $key ),
				array(
					'status' => 400,
					'field'  => $key,
				)
			);
		}

		$values[ $key ] = $value;
	}

	$request_id = wp_insert_post(
		array(
			'post_type'    => 'wpss_request',
			'post_title'   => $values['title'],
			'post_content' => $values['description'],
			'post_status'  => 'private',
			'post_author'  => $buyer_id,
		),
		true
	);

	if ( is_wp_error( $request_id ) ) {
		return new \WP_Error( 'wpss_offer_not_saved', __( 'The offer could not be saved. Please try again.', 'wp-sell-services' ), array( 'status' => 500 ) );
	}

	$expires_at = gmdate( 'Y-m-d H:i:s', time() + (int) $values['expires_days'] * DAY_IN_SECONDS );

	update_post_meta( $request_id, '_wpss_status', BuyerRequestService::STATUS_OPEN );
	update_post_meta( $request_id, '_wpss_budget_type', BuyerRequestService::BUDGET_FIXED );
	update_post_meta( $request_id, '_wpss_budget_min', (float) $values['price'] );
	update_post_meta( $request_id, '_wpss_budget_max', (float) $values['price'] );
	update_post_meta( $request_id, '_wpss_delivery_days', (int) $values['delivery_days'] );
	update_post_meta( $request_id, '_wpss_expires_at', $expires_at );
	update_post_meta( $request_id, '_wpss_private_offer_vendor', $vendor_id );
	update_post_meta( $request_id, '_wpss_private_offer_conversation', $conversation_id );
	update_post_meta( $request_id, '_wpss_private_offer_revisions', (int) $values['revisions'] );

	$proposal_id = ( new ProposalService() )->submit(
		(int) $request_id,
		$vendor_id,
		array(
			'description'   => $values['description'],
			'price'         => $values['price'],
			'delivery_days' => $values['delivery_days'],
			'contract_type' => $data['contract_type'] ?? ProposalService::CONTRACT_TYPE_FIXED,
			'milestones'    => $data['milestones'] ?? array(),
		)
	);

	if ( ! $proposal_id || is_wp_error( $proposal_id ) ) {
		wp_delete_post( (int) $request_id, true );

		return is_wp_error( $proposal_id ) ? $proposal_id : new \WP_Error( 'wpss_offer_not_saved', __( 'The offer could not be saved. Check the milestones and try again.', 'wp-sell-services' ), array( 'status' => 400 ) );
	}

	update_post_meta( $request_id, '_wpss_private_offer_proposal', (int) $proposal_id );

	$message = $conversations->send_message(
		$conversation_id,
		$vendor_id,
		sprintf(
			/* translators: 1: offer title, 2: price. */
			__( 'Private offer: %1$s, %2$s', 'wp-sell-services' ),
			$values['title'],
			wp_strip_all_tags( wpss_format_price( (float) ( ( new ProposalService() )->get( (int) $proposal_id )->proposed_price ?? 0 ) ) )
		),
		array(),
		Message::TYPE_OFFER,
		array(
			'proposal_id' => (int) $proposal_id,
			'request_id'  => (int) $request_id,
		)
	);

	/**
	 * Fires after a vendor sends a private offer.
	 *
	 * @since 2.0.0
	 *
	 * @param int $proposal_id     The offer's proposal ID.
	 * @param int $request_id      The hidden request ID.
	 * @param int $conversation_id Conversation it was sent in.
	 * @param int $vendor_id       Vendor.
	 * @param int $buyer_id        Buyer.
	 */
	do_action( 'wpss_private_offer_sent', (int) $proposal_id, (int) $request_id, $conversation_id, $vendor_id, $buyer_id );

	return (array) wpss_private_offer_for_client( (int) $proposal_id, $vendor_id ) + array( 'message_id' => $message ? (int) $message->id : 0 );
}

/**
 * An offer as one member sees it: terms, state and what they may do with it.
 *
 * @since 2.0.0
 *
 * @param int $proposal_id The offer's proposal ID.
 * @param int $user_id     Viewer.
 * @return array<string,mixed>|null Null when it is not an offer or the viewer may not see it.
 */
function wpss_private_offer_for_client( int $proposal_id, int $user_id ): ?array {
	$proposal   = $proposal_id ? ( new ProposalService() )->get( $proposal_id ) : null;
	$request_id = $proposal ? (int) $proposal->request_id : 0;

	if ( ! $proposal || ! wpss_is_private_offer( $request_id ) || ! user_can( $user_id, 'read_post', $request_id ) ) {
		return null;
	}

	$buyer_id   = (int) get_post_field( 'post_author', $request_id );
	$expires_at = (string) get_post_meta( $request_id, '_wpss_expires_at', true );
	$order_id   = (int) ( $proposal->order_id ?? 0 );
	$state      = match ( (string) $proposal->status ) {
		ProposalService::STATUS_ACCEPTED  => 'accepted',
		ProposalService::STATUS_REJECTED  => 'declined',
		ProposalService::STATUS_WITHDRAWN => 'withdrawn',
		default                           => wpss_private_offer_is_expired( $request_id ) ? 'expired' : 'pending',
	};

	return array(
		'id'            => $proposal_id,
		'request_id'    => $request_id,
		'title'         => get_post_field( 'post_title', $request_id ),
		'description'   => (string) $proposal->cover_letter,
		'price'         => (float) $proposal->proposed_price,
		'price_minor'   => wpss_amount_to_minor_units( (float) $proposal->proposed_price, wpss_get_currency() ),
		'currency'      => wpss_get_currency(),
		'delivery_days' => (int) $proposal->proposed_days,
		'revisions'     => (int) get_post_meta( $request_id, '_wpss_private_offer_revisions', true ),
		'contract_type' => (string) ( $proposal->contract_type ?? ProposalService::CONTRACT_TYPE_FIXED ),
		'state'         => $state,
		'expires_at'    => wpss_rest_date( $expires_at ),
		'vendor'        => wpss_rest_user( (int) $proposal->vendor_id ),
		'buyer'         => wpss_rest_user( $buyer_id ),
		'order_url'     => $order_id && in_array( $user_id, array( $buyer_id, (int) $proposal->vendor_id ), true ) ? wpss_get_order_url( $order_id ) : '',
		'can_respond'   => 'pending' === $state && $user_id === $buyer_id,
		'accept_route'  => sprintf( '/wpss/v1/buyer-requests/%d/proposals/%d/accept', $request_id, $proposal_id ),
		'decline_route' => sprintf( '/wpss/v1/buyer-requests/%d/proposals/%d/reject', $request_id, $proposal_id ),
	);
}

/**
 * A message's metadata as an array, whether it came as a model or a raw row.
 *
 * @since 2.0.0
 *
 * @param object $message Message model or row.
 * @return array<string,mixed>
 */
function wpss_message_metadata( object $message ): array {
	$meta = $message->metadata ?? array();

	return is_array( $meta ) ? $meta : (array) json_decode( (string) $meta, true );
}

/**
 * Whether an offer's acceptance window has passed (or the cron already closed it).
 *
 * @since 2.0.0
 *
 * @param int $request_id The hidden request ID.
 * @return bool
 */
function wpss_private_offer_is_expired( int $request_id ): bool {
	$expires_at = (string) get_post_meta( $request_id, '_wpss_expires_at', true );

	return BuyerRequestService::STATUS_EXPIRED === get_post_meta( $request_id, '_wpss_status', true )
		|| ( '' !== $expires_at && strtotime( $expires_at . ' UTC' ) < time() );
}

/**
 * Where an offer lives for its buyer and vendor: the conversation it was sent in.
 *
 * @since 2.0.0
 *
 * @param int $request_id The hidden request ID.
 * @return string URL, or '' when the request is not an offer.
 */
function wpss_private_offer_url( int $request_id ): string {
	$conversation_id = (int) get_post_meta( $request_id, '_wpss_private_offer_conversation', true );

	return wpss_is_private_offer( $request_id ) && $conversation_id
		? add_query_arg( 'conversation_id', $conversation_id, wpss_get_dashboard_url( 'messages' ) )
		: '';
}

/**
 * Let an offer's vendor read its hidden request, as its buyer (the author) and admins already can.
 *
 * This is the one gate: the request page, GET /buyer-requests/{id} and wpss_private_offer_for_client()
 * all ask current_user_can( 'read_post' ).
 *
 * @since 2.0.0
 *
 * @param string[] $caps    Primitive caps required.
 * @param string   $cap     Meta cap checked.
 * @param int      $user_id User.
 * @param array    $args    Context; [0] is the post ID.
 * @return string[]
 */
function wpss_offer_map_read_cap( array $caps, string $cap, int $user_id, array $args ): array {
	$post_id = (int) ( $args[0] ?? 0 );

	if ( 'read_post' === $cap && $post_id && 'wpss_request' === get_post_type( $post_id ) && 'private' === get_post_status( $post_id )
		&& $user_id && (int) get_post_meta( $post_id, '_wpss_private_offer_vendor', true ) === $user_id ) {
		return array( 'read' );
	}

	return $caps;
}
add_filter( 'map_meta_cap', 'wpss_offer_map_read_cap', 10, 4 );

/**
 * An offer is private by design: no "Private:" prefix on its title.
 *
 * @since 2.0.0
 *
 * @param string   $format Title format.
 * @param \WP_Post $post   Post.
 * @return string
 */
function wpss_offer_title_format( string $format, $post ): string {
	return $post instanceof \WP_Post && 'wpss_request' === $post->post_type ? '%s' : $format;
}
add_filter( 'private_title_format', 'wpss_offer_title_format', 10, 2 );

/**
 * An accepted offer's order gets the revisions the vendor offered.
 *
 * @since 2.0.0
 *
 * @param int    $revisions Revisions (2 by default).
 * @param object $proposal  Proposal.
 * @param object $request   Request.
 * @return int
 */
function wpss_offer_order_revisions( $revisions, $proposal, $request ): int {
	$request_id = (int) ( $request->id ?? $request->ID ?? 0 );

	return wpss_is_private_offer( $request_id ) ? (int) get_post_meta( $request_id, '_wpss_private_offer_revisions', true ) : (int) $revisions;
}
add_filter( 'wpss_proposal_order_revisions', 'wpss_offer_order_revisions', 10, 3 );

/**
 * Accepted: say so on the offer's own hook.
 *
 * @since 2.0.0
 *
 * @param int    $proposal_id Proposal ID.
 * @param object $proposal    Proposal.
 * @return void
 */
function wpss_offer_on_accepted( $proposal_id, $proposal ): void {
	$request_id = (int) ( $proposal->request_id ?? 0 );

	if ( wpss_is_private_offer( $request_id ) ) {
		/**
		 * Fires after a buyer accepts a private offer (the order now waits for payment).
		 *
		 * @since 2.0.0
		 *
		 * @param int $proposal_id Proposal ID.
		 * @param int $request_id  The hidden request ID.
		 */
		do_action( 'wpss_private_offer_accepted', (int) $proposal_id, $request_id );
	}
}
add_action( 'wpss_proposal_accepted', 'wpss_offer_on_accepted', 10, 2 );

/**
 * Declined: close the hidden request and say so.
 *
 * @since 2.0.0
 *
 * @param int    $proposal_id Proposal ID.
 * @param object $proposal    Proposal.
 * @return void
 */
function wpss_offer_on_declined( $proposal_id, $proposal ): void {
	$request_id = (int) ( $proposal->request_id ?? 0 );

	if ( ! wpss_is_private_offer( $request_id ) ) {
		return;
	}

	( new BuyerRequestService() )->update_status( $request_id, BuyerRequestService::STATUS_CLOSED );

	/**
	 * Fires after a buyer declines a private offer.
	 *
	 * @since 2.0.0
	 *
	 * @param int $proposal_id Proposal ID.
	 * @param int $request_id  The hidden request ID.
	 */
	do_action( 'wpss_private_offer_declined', (int) $proposal_id, $request_id );
}
add_action( 'wpss_proposal_rejected', 'wpss_offer_on_declined', 10, 2 );

/**
 * Withdrawn by its vendor: close the hidden request, so it cannot be accepted.
 *
 * @since 2.0.0
 *
 * @param int    $proposal_id Proposal ID.
 * @param object $proposal    Proposal.
 * @return void
 */
function wpss_offer_on_withdrawn( $proposal_id, $proposal ): void {
	$request_id = (int) ( $proposal->request_id ?? 0 );

	if ( wpss_is_private_offer( $request_id ) ) {
		( new BuyerRequestService() )->update_status( $request_id, BuyerRequestService::STATUS_CLOSED );
	}
}
add_action( 'wpss_proposal_withdrawn', 'wpss_offer_on_withdrawn', 10, 2 );
