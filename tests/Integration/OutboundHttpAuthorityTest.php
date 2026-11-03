<?php
/**
 * Every outbound HTTP call declares where its target comes from.
 *
 * A request whose host a member can choose makes the site's server call
 * wherever that member likes - the SSRF a vendor's gallery video opened in
 * 1.9.2. So each call says what its target is built from: a constant, an admin
 * setting, this site's own URL, or member input. Member input must say what
 * limits it.
 *
 * Call sites are found by tokenising src/ and keyed "file :: function" (with
 * " #n" for a repeat in one function), so moving a call into another function
 * asks the question again.
 *
 * @package WPSellServices\Tests
 */

declare(strict_types=1);

namespace WPSellServices\Tests\Integration;

use WPSellServices\Tests\TestCase;

class OutboundHttpAuthorityTest extends TestCase {

	private const ROOTS = array(
		'free' => __DIR__ . '/../..',
		'pro'  => __DIR__ . '/../../../wp-sell-services-pro',
	);

	private const TARGETS = array( 'constant', 'setting', 'site', 'member' );

	private const CALL = '/^(wp_remote_(get|post|request|head)|wp_safe_remote_(get|post|request|head)|download_url|wp_oembed_get|_wp_oembed_get_object)$/';

	/** @return array<string, array{string}> */
	public static function plugins(): array {
		return array(
			'free' => array( 'free' ),
			'pro'  => array( 'pro' ),
		);
	}

	private function root( string $plugin ): string {
		$root = (string) realpath( self::ROOTS[ $plugin ] );

		if ( '' === $root || ! is_dir( $root . '/src' ) ) {
			$this->markTestSkipped( "{$plugin} is not installed." );
		}

		return $root;
	}

	/**
	 * Call sites in one plugin's src/.
	 *
	 * @return array<string, string> key => function called.
	 */
	private function sites( string $root ): array {
		$out = array();
		$it  = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root . '/src', \FilesystemIterator::SKIP_DOTS ) );

		foreach ( $it as $file ) {
			if ( 'php' !== $file->getExtension() ) {
				continue;
			}

			$tokens = token_get_all( (string) file_get_contents( (string) $file ) );
			$fn     = '(file)';
			$seen   = array();

			foreach ( $tokens as $i => $token ) {
				if ( ! is_array( $token ) ) {
					continue;
				}

				if ( T_FUNCTION === $token[0] ) {
					for ( $j = $i + 1; isset( $tokens[ $j ] ) && '(' !== $tokens[ $j ]; $j++ ) {
						if ( is_array( $tokens[ $j ] ) && T_STRING === $tokens[ $j ][0] ) {
							$fn = $tokens[ $j ][1];
							break;
						}
					}
					continue;
				}

				if ( T_STRING !== $token[0] || ! preg_match( self::CALL, $token[1] ) ) {
					continue;
				}

				// A method of the same name, or this plugin declaring one, is not the core call.
				$prev = $tokens[ $i - 1 ] ?? '';
				if ( is_array( $prev ) && in_array( $prev[0], array( T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION ), true ) ) {
					continue;
				}

				$key          = substr( (string) $file, strlen( $root ) + 1 ) . ' :: ' . $fn;
				$seen[ $key ] = ( $seen[ $key ] ?? 0 ) + 1;
				$key         .= $seen[ $key ] > 1 ? ' #' . $seen[ $key ] : '';
				$out[ $key ]  = $token[1];
			}
		}

		ksort( $out );

		return $out;
	}

	private function manifest( string $root ): array {
		return (array) ( json_decode( (string) file_get_contents( $root . '/audit/outbound-http.json' ), true )['calls'] ?? array() );
	}

	/**
	 * @dataProvider plugins
	 */
	public function test_every_call_is_declared_and_none_is_stale( string $plugin ): void {
		$root  = $this->root( $plugin );
		$sites = $this->sites( $root );
		$calls = $this->manifest( $root );

		$this->assertGreaterThan( 5, count( $sites ), 'The sweep found almost no outbound calls, so it proved nothing.' );
		$this->assertSame(
			array(),
			array_keys( array_diff_key( $sites, $calls ) ),
			"These outbound calls are not declared. Add each to audit/outbound-http.json with its target.\n"
			. implode( "\n", array_keys( array_diff_key( $sites, $calls ) ) )
		);
		$this->assertSame(
			array(),
			array_keys( array_diff_key( $calls, $sites ) ),
			"These declarations match no call any more - re-key or delete them.\n" . implode( "\n", array_keys( array_diff_key( $calls, $sites ) ) )
		);
	}

	/**
	 * @dataProvider plugins
	 */
	public function test_every_target_is_named_and_member_input_is_limited( string $plugin ): void {
		$root     = $this->root( $plugin );
		$sites    = $this->sites( $root );
		$problems = array();

		foreach ( $this->manifest( $root ) as $key => $entry ) {
			$entry  = (array) $entry;
			$target = (string) ( $entry['target'] ?? '' );

			if ( ! in_array( $target, self::TARGETS, true ) ) {
				$problems[] = "{$key} - target must be one of " . implode( '|', self::TARGETS ) . '.';
				continue;
			}

			if ( 'constant' !== $target && '' === trim( (string) ( $entry['reason'] ?? '' ) ) ) {
				$problems[] = "{$key} - a {$target} target says who sets it and what bounds it.";
			}

			$safe = 0 === strpos( (string) ( $sites[ $key ] ?? '' ), 'wp_safe_remote_' );
			if ( 'member' === $target && ! $safe && '' === trim( (string) ( $entry['limited_by'] ?? '' ) ) ) {
				$problems[] = "{$key} - member input reaches a plain HTTP call. Use wp_safe_remote_*, or name in limited_by the check that bounds the host.";
			}

			$limit = (string) ( $entry['limited_by'] ?? '' );
			if ( '' !== $limit && ! function_exists( $limit ) ) {
				$problems[] = "{$key} - limited_by names {$limit}, which does not exist.";
			}
		}

		$this->assertSame( array(), $problems, implode( "\n", $problems ) );
	}
}
