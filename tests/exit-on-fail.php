<?php
/**
 * Exit status for the tests/test-*.php contract scripts.
 *
 * CI runs every script in this directory through `wp eval-file` and fails the
 * job on a non-zero exit (.github/workflows/ci.yml). Each script counted its
 * own failures and printed them, but the exit had to be written by hand at the
 * end of every script and in most of them it never was - so a script could
 * print FAIL and leave the job green. A gate that reports red and stops nothing
 * is worse than no gate, because it looks like coverage (Basecamp 10269135242).
 *
 * Requiring this file derives the exit status from what the script printed,
 * so no script has to remember: a line beginning FAIL, or a non-zero
 * "N FAILED" / "N failed" summary, exits 1. A script that already exits
 * non-zero on its own keeps its own status.
 *
 * Absent a fixture, print SKIP with the reason and exit 0. Printing FAIL and
 * exiting 0 is the one thing that must not happen.
 *
 * @package WPSellServices
 */

if ( defined( 'WPSS_TEST_EXIT_ON_FAIL' ) ) {
	return;
}
define( 'WPSS_TEST_EXIT_ON_FAIL', true );

/**
 * Watch output as it is written, without holding it back.
 *
 * The chunk size of 1 makes PHP hand every echo straight through, so ordering
 * with WP_CLI::log() - which writes to STDOUT directly and never reaches this
 * callback - is preserved, and a script that dies mid-run has still printed
 * everything it got to.
 */
ob_start(
	static function ( $chunk ) {
		if ( preg_match( '/^[ \t]*FAIL\b/m', (string) $chunk )
			|| preg_match( '/\b[1-9][0-9]*\s+FAILED?\b/i', (string) $chunk ) ) {
			$GLOBALS['wpss_test_saw_failure'] = true;
		}

		return $chunk;
	},
	1
);

// exit() inside a shutdown function skips every one registered after it, so a
// failing script would leave its fixtures and restored options behind. Queue the
// exit behind everything the script registers: callbacks added during shutdown
// run after the ones already waiting.
register_shutdown_function(
	static function () {
		register_shutdown_function(
			static function () {
				wpss_test_sweep_created_rows();

				if ( ! empty( $GLOBALS['wpss_test_saw_failure'] ) ) {
					exit( 1 );
				}
			}
		);
	}
);

// Most scripts clean up only if they reach their last line, so a crash or a
// FAIL-then-return left users, services and their orders on the site for the
// next script to trip over. Remember every user and post the script creates and
// remove what it left, once everything else has had its turn.
$GLOBALS['wpss_test_created'] = array( 'users' => array(), 'posts' => array() );

// Audit rows written while the script ran (imports, deletes, status changes) go
// with it, so the Audit Log screen shows the site's history and not the suite's.
global $wpdb;
$GLOBALS['wpss_test_audit_mark'] = ( isset( $wpdb ) && $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}wpss_audit_log'" ) ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	? (int) $wpdb->get_var( "SELECT COALESCE(MAX(id),0) FROM {$wpdb->prefix}wpss_audit_log" ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	: null;

if ( function_exists( 'add_action' ) ) {
	add_action(
		'user_register',
		static function ( $user_id ) {
			$GLOBALS['wpss_test_created']['users'][] = (int) $user_id;
		}
	);
	add_action(
		'wp_insert_post',
		static function ( $post_id, $post, $update ) {
			// Only rows this script made: an update of an existing post is not ours to delete.
			if ( ! $update && ! in_array( $post->post_type, array( 'revision', 'nav_menu_item' ), true ) ) {
				$GLOBALS['wpss_test_created']['posts'][] = (int) $post_id;
			}
		},
		10,
		3
	);
}

if ( ! function_exists( 'wpss_test_sweep_created_rows' ) ) {
	/**
	 * Delete the users and posts this script made and did not remove itself.
	 */
	function wpss_test_sweep_created_rows(): void {
		if ( ! function_exists( 'wp_delete_post' ) || ! function_exists( 'get_userdata' ) ) {
			return;
		}

		// An admin may force-delete a service that has orders; nobody else may.
		wp_set_current_user( 1 );

		foreach ( array_unique( $GLOBALS['wpss_test_created']['posts'] ) as $post_id ) {
			if ( get_post( $post_id ) ) {
				wp_delete_post( $post_id, true );
			}
		}

		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( array_unique( $GLOBALS['wpss_test_created']['users'] ) as $user_id ) {
			if ( 1 !== $user_id && get_userdata( $user_id ) ) {
				wp_delete_user( $user_id );
			}
		}

		global $wpdb;
		if ( null !== $GLOBALS['wpss_test_audit_mark'] ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wpss_audit_log WHERE id > %d", $GLOBALS['wpss_test_audit_mark'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}
}

if ( ! function_exists( 'wpss_test_activate_vendor' ) ) {
	/**
	 * Give a throwaway user an active vendor profile (ProposalService::submit
	 * refuses anyone without one) and remove the row when the script ends.
	 *
	 * @param int $user_id Throwaway user.
	 */
	function wpss_test_activate_vendor( int $user_id ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_vendor_profiles';
		$wpdb->insert(
			$table,
			array(
				'user_id'      => $user_id,
				'display_name' => 'QA vendor ' . $user_id,
				'status'       => 'active',
			)
		);
		register_shutdown_function(
			static function () use ( $wpdb, $table, $user_id ) {
				$wpdb->delete( $table, array( 'user_id' => $user_id ) );
			}
		);
	}
}

if ( ! function_exists( 'wpss_test_fixture_user' ) ) {
	/**
	 * A real member of the seeded marketplace to test against, so a script runs
	 * on any site built by `wp wpss demo marketplace`, not only the one it was
	 * written on.
	 *
	 * 'vendor': the lowest-id active vendor with a published service.
	 * 'buyer':  the lowest-id member with an order who is neither a vendor nor an admin.
	 *
	 * @param string $kind 'vendor' or 'buyer'.
	 * @return int User ID, or 0 when the site has none (the script should SKIP).
	 */
	function wpss_test_fixture_user( string $kind ): int {
		global $wpdb;
		$profiles = $wpdb->prefix . 'wpss_vendor_profiles';
		$orders   = $wpdb->prefix . 'wpss_orders';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( 'vendor' === $kind ) {
			$ids = $wpdb->get_col(
				"SELECT p.user_id FROM {$profiles} p
				INNER JOIN {$wpdb->posts} s ON s.post_author = p.user_id AND s.post_type = 'wpss_service' AND s.post_status = 'publish'
				WHERE p.status = 'active' GROUP BY p.user_id ORDER BY p.user_id ASC LIMIT 20"
			);
		} else {
			$ids = $wpdb->get_col(
				"SELECT DISTINCT o.customer_id FROM {$orders} o
				LEFT JOIN {$profiles} p ON p.user_id = o.customer_id
				WHERE p.user_id IS NULL AND o.customer_id > 0 ORDER BY o.customer_id ASC LIMIT 20"
			);
		}
		// phpcs:enable

		foreach ( $ids as $id ) {
			$user = get_userdata( (int) $id );
			if ( $user && ! user_can( $user, 'manage_options' ) && 'active' === ( get_user_meta( $user->ID, 'wpss_account_status', true ) ?: 'active' ) ) {
				return (int) $user->ID;
			}
		}

		return 0;
	}
}

if ( ! function_exists( 'wpss_test_patch_row' ) ) {
	/**
	 * Change a few columns on an existing row and put them back when the script
	 * ends, so a script can stage the state it needs (a dispute in a given
	 * status, an order completed today) on whatever site it runs on.
	 *
	 * @param string               $table   Table name without the prefix, e.g. 'wpss_disputes'.
	 * @param int                  $id      Row id.
	 * @param array<string, mixed> $changes Column => value.
	 * @return bool Whether the row was found and changed.
	 */
	function wpss_test_patch_row( string $table, int $id, array $changes ): bool {
		global $wpdb;
		$table   = $wpdb->prefix . $table;
		$columns = implode( ', ', array_map( static fn( $c ) => '`' . esc_sql( $c ) . '`', array_keys( $changes ) ) );
		$before  = $wpdb->get_row( $wpdb->prepare( "SELECT {$columns} FROM {$table} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $before ) {
			return false;
		}

		$wpdb->update( $table, $changes, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		register_shutdown_function(
			static function () use ( $wpdb, $table, $id, $before ) {
				$wpdb->update( $table, $before, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			}
		);

		return true;
	}
}

if ( ! function_exists( 'wpss_test_dispute_in' ) ) {
	/**
	 * A dispute in the given status: an existing one, or another dispute staged
	 * into that status with wpss_test_patch_row() and restored afterwards.
	 *
	 * @param string $status  Dispute status, e.g. 'escalated' or 'resolved'.
	 * @param int    $exclude A dispute id not to use (one already staged for another status).
	 * @return int Dispute id, or 0 when the site has no dispute at all.
	 */
	function wpss_test_dispute_in( string $status, int $exclude = 0 ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_disputes';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE status = %s AND id <> %d ORDER BY id DESC LIMIT 1", $status, $exclude ) );
		if ( ! $id ) {
			$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE id <> %d ORDER BY id DESC LIMIT 1", $exclude ) );
			if ( ! $id || ! wpss_test_patch_row( 'wpss_disputes', $id, array( 'status' => $status ) ) ) {
				return 0;
			}
		}
		// phpcs:enable

		return $id;
	}
}

if ( ! function_exists( 'wpss_test_milestone_contract' ) ) {
	/**
	 * A throwaway milestone contract to test against, so a script that needs a
	 * phase asserts on a site seeded without one (CI's demo data has none).
	 *
	 * The parent is a request order and each phase carries the columns and meta
	 * MilestoneService::propose() writes, between the throwaway buyer and vendor
	 * from fixtures.php. The rows, and any conversation the run opens on them,
	 * are removed at shutdown.
	 *
	 * @param array<int, array<string, mixed>> $phases One set of column overrides per phase,
	 *                                               e.g. array( 'status' => 'completed', 'payment_status' => 'paid' ).
	 * @return array{parent: int, phases: int[], buyer: int, vendor: int}
	 */
	function wpss_test_milestone_contract( array $phases = array( array() ) ): array {
		require_once __DIR__ . '/fixtures.php';
		global $wpdb;
		$f = wpss_test_fixtures();

		$parent = ( $f->order )(
			array(
				'platform'   => 'request',
				'service_id' => 0,
				'subtotal'   => 0,
				'total'      => 0,
				'meta'       => wp_json_encode(
					array(
						'contract_type'     => 'milestone',
						'proposal_snapshot' => array( 'contract_type' => 'milestone', 'request_title' => 'QA milestone contract' ),
					)
				),
			)
		);

		$ids = array();
		foreach ( array_values( $phases ) as $i => $columns ) {
			$ids[] = ( $f->order )(
				array_merge(
					array(
						'order_number'      => 'MS-QA' . strtoupper( wp_generate_password( 6, false, false ) ),
						'platform'          => 'milestone',
						'platform_order_id' => $parent,
						'status'            => 'pending_payment',
						'payment_status'    => 'pending',
						'paid_at'           => null,
						'subtotal'          => 50,
						'total'             => 50,
						'meta'              => wp_json_encode(
							array(
								'milestone'             => true,
								'parent_order_id'       => $parent,
								'title'                 => 'QA phase ' . ( $i + 1 ),
								'description'           => 'QA phase',
								'deliverables'          => '',
								'sort_order'            => $i + 1,
								'is_contract_milestone' => true,
							)
						),
					),
					$columns
				)
			);
		}

		$orders = array_merge( array( $parent ), $ids );
		register_shutdown_function(
			static function () use ( $wpdb, $orders ) {
				// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$in    = implode( ',', array_map( 'intval', $orders ) );
				$convs = $wpdb->get_col( "SELECT id FROM {$wpdb->prefix}wpss_conversations WHERE order_id IN ({$in})" );
				if ( $convs ) {
					$wpdb->query( "DELETE FROM {$wpdb->prefix}wpss_messages WHERE conversation_id IN (" . implode( ',', array_map( 'intval', $convs ) ) . ')' );
					$wpdb->query( "DELETE FROM {$wpdb->prefix}wpss_conversations WHERE order_id IN ({$in})" );
				}
				// phpcs:enable
			}
		);

		return array(
			'parent' => $parent,
			'phases' => $ids,
			'buyer'  => $f->buyer,
			'vendor' => $f->vendor,
		);
	}
}

if ( ! function_exists( 'wpss_test_image' ) ) {
	/**
	 * A 1x1 PNG attachment owned by $author, for a site seeded with --no-images.
	 * Swept with the script's other posts (the file goes with it).
	 *
	 * @param int $author Attachment author.
	 * @return int Attachment id, or 0 when the upload failed.
	 */
	function wpss_test_image( int $author ): int {
		$bits = wp_upload_bits( 'qa-image-' . wp_generate_password( 6, false, false ) . '.png', null, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( ! empty( $bits['error'] ) ) {
			return 0;
		}

		return (int) wp_insert_attachment(
			array(
				'post_mime_type' => 'image/png',
				'post_title'     => 'QA image',
				'post_status'    => 'inherit',
				'post_author'    => $author,
			),
			$bits['file']
		);
	}
}

if ( ! function_exists( 'wpss_test_live_service' ) ) {
	/**
	 * A throwaway service that passes wpss_get_service_publish_errors(), is
	 * published and approved, owned by the fixtures.php vendor: title, category,
	 * description, packages, a main image and ($images - 1) further gallery
	 * images. Removed at shutdown with its images (and the category, when the
	 * site had none to file it in).
	 *
	 * Published by writing the status, not through wp_update_post(): the script
	 * gets the state it needs without the publish guards and their mail running.
	 *
	 * @param int $images Images in total, main image included (at least 1).
	 * @return int Service id.
	 */
	function wpss_test_live_service( int $images = 1 ): int {
		require_once __DIR__ . '/fixtures.php';
		global $wpdb;
		$f  = wpss_test_fixtures();
		$id = ( $f->service )( 'draft' );

		$terms = get_terms( array( 'taxonomy' => 'wpss_service_category', 'hide_empty' => false, 'number' => 1, 'fields' => 'ids' ) );
		$term  = is_array( $terms ) ? (int) ( $terms[0] ?? 0 ) : 0;
		if ( ! $term ) {
			$made = wp_insert_term( 'QA category ' . wp_generate_password( 4, false, false ), 'wpss_service_category' );
			$term = is_wp_error( $made ) ? 0 : (int) $made['term_id'];
			register_shutdown_function( static fn() => $term && wp_delete_term( $term, 'wpss_service_category' ) );
		}
		wp_set_object_terms( $id, array( $term ), 'wpss_service_category' );

		update_post_meta(
			$id,
			'_wpss_packages',
			array(
				array( 'id' => 1000, 'name' => 'Basic', 'description' => 'QA package.', 'price' => 40, 'delivery_days' => 3, 'revisions' => 1, 'features' => array( 'QA deliverable' ) ),
			)
		);

		$ids = array();
		for ( $i = 0; $i < max( 1, $images ); $i++ ) {
			$ids[] = wpss_test_image( $f->vendor );
		}
		set_post_thumbnail( $id, $ids[0] );
		update_post_meta( $id, '_wpss_gallery', array( 'images' => array_slice( $ids, 1 ), 'video' => '' ) );
		update_post_meta( $id, '_wpss_moderation_status', 'approved' );

		$wpdb->update( $wpdb->posts, array( 'post_status' => 'publish' ), array( 'ID' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		clean_post_cache( $id );

		return $id;
	}
}
