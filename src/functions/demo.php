<?php
/**
 * Demo content detection and removal.
 *
 * @package WPSellServices
 * @since   1.9.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether demo content is on the site: any post or member carrying the marker.
 *
 * Asked of the content itself, not of a flag. The wizard refused to import
 * because an option still said "imported" after the CLI had deleted
 * everything (Basecamp 10378021873).
 *
 * @since 1.8.0
 *
 * @return bool
 */
function wpss_has_demo_content(): bool {
	$marker = array(
		'meta_key'   => '_wpss_demo_content', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'meta_value' => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		'fields'     => 'ids',
	);

	return (bool) get_posts(
		$marker + array(
			'post_type'      => array( 'wpss_service', 'wpss_request', 'attachment' ),
			'post_status'    => 'any',
			'posts_per_page' => 1,
		)
	) || (bool) get_users( $marker + array( 'number' => 1 ) );
}

/**
 * Delete demo content: marked services, requests and media, demo vendors, and
 * the categories the demo created that nothing uses any more.
 *
 * The one routine behind Settings > Delete Demo Content and `wp wpss demo
 * delete`. They were two: the admin one removed services and vendors, the CLI
 * one removed posts and left the vendors and the "imported" flag.
 *
 * Categories: only one the demo created (marked at creation) and only when no
 * post of any status is in it and it has no child. The admin routine used to
 * delete every category whose published count was zero, demo or not - an
 * owner's own unused categories, and parents whose services sat in children.
 *
 * A demo service a real buyer has ordered is kept, with its seller and its
 * image: deleting a service deletes its orders, and "delete the samples" must
 * never erase a sale (Basecamp 10379596258). Orders the demo importer made
 * itself do not count. 'kept' says how many services stayed.
 *
 * @since 1.8.0
 *
 * @return array{posts:int,services:int,vendors:int,categories:int,kept:int} What was deleted, and what was kept.
 */
function wpss_delete_demo_content(): array {
	global $wpdb;

	$marker  = array(
		'meta_key'   => '_wpss_demo_content', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'meta_value' => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		'fields'     => 'ids',
	);
	$deleted = array(
		'posts'      => 0,
		'services'   => 0,
		'vendors'    => 0,
		'categories' => 0,
		'kept'       => 0,
	);

	$posts = get_posts(
		$marker + array(
			'post_type'      => array( 'wpss_service', 'wpss_request', 'attachment' ),
			'post_status'    => 'any',
			'posts_per_page' => -1,
		)
	);

	// Demo services with an order the importer did not make.
	$ordered = array();

	if ( $posts ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- ids are integers from get_posts().
		$ordered = array_map( 'intval', $wpdb->get_col( "SELECT DISTINCT service_id FROM {$wpdb->prefix}wpss_orders WHERE service_id IN (" . implode( ',', array_map( 'intval', $posts ) ) . ") AND ( meta IS NULL OR meta NOT LIKE '%Seeded demo order.%' )" ) );
	}

	$keep_users = array();
	$keep_media = array();

	foreach ( $ordered as $service_id ) {
		$keep_users[] = (int) get_post_field( 'post_author', $service_id );
		$keep_media[] = (int) get_post_thumbnail_id( $service_id );
	}

	$deleted['kept'] = count( $ordered );

	foreach ( $posts as $post_id ) {
		// ponytail: keeps the featured image and attached files of a kept
		// service; a gallery image stored only by ID in meta is not traced.
		if ( in_array( (int) $post_id, $ordered, true ) || in_array( (int) $post_id, $keep_media, true ) || in_array( (int) get_post_field( 'post_parent', $post_id ), $ordered, true ) ) {
			continue;
		}

		$is_service = 'wpss_service' === get_post_type( $post_id );

		if ( wp_delete_post( (int) $post_id, true ) ) {
			++$deleted['posts'];
			$deleted['services'] += $is_service ? 1 : 0;
		}
	}

	require_once ABSPATH . 'wp-admin/includes/user.php';

	foreach ( array_diff( array_map( 'intval', get_users( $marker ) ), $keep_users ) as $user_id ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $wpdb->prefix . 'wpss_vendor_profiles', array( 'user_id' => (int) $user_id ), array( '%d' ) );

		if ( wp_delete_user( (int) $user_id ) ) {
			++$deleted['vendors'];
		}
	}

	$demo_terms = get_terms(
		array(
			'taxonomy'   => 'wpss_service_category',
			'hide_empty' => false,
			'fields'     => 'ids',
			'meta_key'   => '_wpss_demo_content', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'orderby'    => 'term_id',
			'order'      => 'DESC', // Children were created after their parents.
		)
	);

	foreach ( is_array( $demo_terms ) ? $demo_terms : array() as $term_id ) {
		$in_use = get_objects_in_term( (int) $term_id, 'wpss_service_category' );
		$kids   = get_term_children( (int) $term_id, 'wpss_service_category' );

		if ( empty( $in_use ) && empty( $kids ) && true === wp_delete_term( (int) $term_id, 'wpss_service_category' ) ) {
			++$deleted['categories'];
		}
	}

	// Written by 1.7.x and earlier; nothing reads it any more.
	// Seeded order briefs point at demo media deleted above; a brief left
	// behind would link to a file that is gone.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( "DELETE r FROM {$wpdb->prefix}wpss_order_requirements r INNER JOIN {$wpdb->prefix}wpss_orders o ON o.id = r.order_id WHERE o.meta LIKE '%Seeded demo order.%'" );

	delete_option( 'wpss_demo_content_imported' );

	return $deleted;
}
