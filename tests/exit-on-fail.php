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
