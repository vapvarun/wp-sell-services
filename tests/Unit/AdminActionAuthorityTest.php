<?php
/**
 * Every AJAX and admin-post handler declares what it checks, and the anonymous
 * ones say why they are reachable signed out.
 *
 * A handler reached over admin-ajax.php has no REST permission_callback and no
 * page capability check around it - whatever it checks itself is the whole
 * gate. That is how the signup handler came to create vendor accounts on a site
 * with registration switched off: the FORM refused and the handler did not
 * (Basecamp 10321653411). The nonce was present and correct the whole time,
 * which is why "has a nonce" is not the question this guard asks.
 *
 * Fails when a handler is registered but not declared, when this file names one
 * that is gone, when a handler has no nonce check and no allow-list entry
 * saying why, and when an anonymous-reachable handler carries no reason.
 *
 * @package WPSellServices\Tests
 */

declare(strict_types=1);

namespace WPSellServices\Tests\Unit;

use WPSellServices\Tests\TestCase;

class AdminActionAuthorityTest extends TestCase {

	private const MANIFEST = __DIR__ . '/../../audit/admin-action-authority.json';

	private const PAIRED = __DIR__ . '/../../../wp-sell-services-pro/audit/admin-action-authority.json';

	/** @var array<string, mixed> */
	private array $manifest = array();

	protected function set_up(): void {
		parent::set_up();

		$this->manifest = (array) json_decode( (string) file_get_contents( self::MANIFEST ), true );
	}

	/**
	 * Live handlers, with the source of each callback.
	 *
	 * @return array<string, array{callback:string, source:string, anonymous:bool}>
	 */
	private function live_handlers(): array {
		$out = array();

		foreach ( (array) ( $GLOBALS['wp_filter'] ?? array() ) as $hook => $obj ) {
			if ( ! preg_match( '/^(wp_ajax_nopriv_|wp_ajax_|admin_post_nopriv_|admin_post_)(.+)$/', (string) $hook, $m ) ) {
				continue;
			}

			if ( 0 !== strpos( $m[2], 'wpss' ) ) {
				continue;
			}

			foreach ( $obj->callbacks as $callbacks ) {
				foreach ( $callbacks as $cb ) {
					$fn = $cb['function'] ?? null;

					try {
						if ( is_array( $fn ) && isset( $fn[0], $fn[1] ) ) {
							$ref  = new \ReflectionMethod( is_object( $fn[0] ) ? get_class( $fn[0] ) : (string) $fn[0], (string) $fn[1] );
							$name = ( is_object( $fn[0] ) ? get_class( $fn[0] ) : (string) $fn[0] ) . '::' . $fn[1];
						} elseif ( $fn instanceof \Closure ) {
							$ref  = new \ReflectionFunction( $fn );
							$name = 'Closure';
						} elseif ( is_string( $fn ) && function_exists( $fn ) ) {
							$ref  = new \ReflectionFunction( $fn );
							$name = $fn;
						} else {
							continue;
						}
					} catch ( \Throwable $e ) {
						continue;
					}

					$file   = (string) $ref->getFileName();
					$source = '';

					if ( '' !== $file && is_readable( $file ) ) {
						$lines  = (array) file( $file );
						$source = self::strip_comments(
							implode( '', array_slice( $lines, $ref->getStartLine() - 1, $ref->getEndLine() - $ref->getStartLine() + 1 ) )
						);
					}

					$out[ $hook ] = array(
						'callback'  => $name,
						'source'    => $source,
						'anonymous' => ( 0 === strpos( (string) $hook, 'wp_ajax_nopriv_' ) || 0 === strpos( (string) $hook, 'admin_post_nopriv_' ) ),
						'pro'       => ( false !== strpos( $file, 'wp-sell-services-pro' ) ),
					);
				}
			}
		}

		ksort( $out );

		return $out;
	}

	/** Handlers this plugin owns. */
	private function mine(): array {
		return array_filter( $this->live_handlers(), static fn( $h ) => ! $h['pro'] );
	}

	/**
	 * TRIPWIRE - Trap 1, and the one this guard is most exposed to.
	 *
	 * is_admin() is false in a test process, so a sweep of wp_filter for
	 * admin_post_* can legitimately come back empty and every check below then
	 * passes having examined nothing. The standard names this exact failure.
	 */
	public function test_the_handler_registry_is_not_empty(): void {
		$live = $this->live_handlers();

		$this->assertNotEmpty(
			$live,
			'No wpss AJAX or admin-post handler was found in wp_filter. The hooks did not register in this process, '
			. 'so every other check in this file would pass having examined nothing - which is precisely how a guard '
			. 'goes green against vulnerable code.'
		);

		$this->assertNotEmpty( $this->manifest['handlers'] ?? array(), 'The manifest declares no handlers.' );
	}

	public function test_every_live_handler_is_declared(): void {
		$declared = array_keys( (array) ( $this->manifest['handlers'] ?? array() ) );
		$paired   = file_exists( self::PAIRED )
			? array_keys( (array) ( json_decode( (string) file_get_contents( self::PAIRED ), true )['handlers'] ?? array() ) )
			: array();

		$undeclared = array_diff( array_keys( $this->mine() ), $declared, $paired );

		$this->assertSame(
			array(),
			array_values( $undeclared ),
			"These handlers are registered but declared nowhere.\n"
			. "Add each to audit/admin-action-authority.json.\n"
			. implode( "\n", $undeclared )
		);
	}

	public function test_the_manifest_has_no_handler_that_no_longer_exists(): void {
		$stale = array_diff( array_keys( (array) ( $this->manifest['handlers'] ?? array() ) ), array_keys( $this->live_handlers() ) );

		$this->assertSame( array(), array_values( $stale ), implode( "\n", $stale ) );
	}

	/**
	 * Every handler verifies a nonce, or is allow-listed with a reason.
	 *
	 * Read from the LIVE callback's source, not from the manifest's own
	 * boolean - otherwise the manifest could simply declare a check into
	 * existence, which is Trap 3.
	 */
	public function test_every_handler_checks_a_nonce_or_says_why_not(): void {
		$allow    = (array) ( $this->manifest['nonce_allowlist'] ?? array() );
		$problems = array();
		$checked  = 0;

		foreach ( $this->mine() as $hook => $live ) {
			++$checked;

			/*
			 * Boundary, so a WRAPPER cannot satisfy the search.
			 *
			 * Without (?<![\w>:$]), a handler calling
			 * $this->check_ajax_referer_wrapper() - which may verify nothing -
			 * matches a search for check_ajax_referer and reads as protected.
			 * The standard's Trap 3.
			 */
			if ( preg_match( '/(?<![\w>:$])(check_ajax_referer|check_admin_referer|wp_verify_nonce)\s*\(/', $live['source'] ) ) {
				continue;
			}

			if ( '' === trim( (string) ( $allow[ $hook ] ?? '' ) ) ) {
				$problems[] = $hook . ' (' . $live['callback'] . ') - no nonce check and no allow-list entry explaining why.';
			}
		}

		$this->assertGreaterThan(
			0,
			$checked,
			'No handler source was examined, so this proved nothing.'
		);

		$this->assertSame( array(), $problems, implode( "\n", $problems ) );
	}

	/**
	 * An anonymous-reachable handler must carry a reason.
	 *
	 * wp_ajax_nopriv_ is the surface an unauthenticated caller can drive, so
	 * each one is a deliberate decision or a mistake, and the manifest is where
	 * the difference is recorded.
	 */
	public function test_anonymous_handlers_say_why_they_are_public(): void {
		$problems = array();
		$found    = 0;

		foreach ( $this->mine() as $hook => $live ) {
			if ( ! $live['anonymous'] ) {
				continue;
			}

			++$found;

			$declared = (array) ( $this->manifest['handlers'][ $hook ] ?? array() );

			if ( true !== ( $declared['anonymous'] ?? false ) ) {
				$problems[] = $hook . ' - reachable signed out but not declared anonymous in the manifest.';
				continue;
			}

			if ( '' === trim( (string) ( $declared['reason'] ?? '' ) ) ) {
				$problems[] = $hook . ' - anonymous with no reason. Say what an unauthenticated caller can do with it.';
			}
		}

		$this->assertGreaterThan(
			0,
			$found,
			'No anonymous handler was found. This plugin registers several, so a pass here means the sweep is broken.'
		);

		$this->assertSame( array(), $problems, implode( "\n", $problems ) );
	}

	/**
	 * PHP source with comments removed, line numbering preserved.
	 *
	 * The nonce search below used to run over raw source, so a COMMENTED-OUT
	 * check_ajax_referer() still matched and the guard reported green on a
	 * genuinely CSRF-unprotected handler. Proven by commenting out the real
	 * check at src/Frontend/AjaxHandlers.php:234 and watching this file still
	 * report OK (5 tests, 8 assertions) - Basecamp 10321653531.
	 *
	 * That is Trap 1's second clause verbatim: "a commented-out registration
	 * still matches a raw regex". Commenting a check out "just to test
	 * something" is one of the commonest ways a nonce dies, and this guard is
	 * the thing meant to notice.
	 *
	 * Lifted from CapabilityMapTest::…, which already did this - the newline
	 * padding keeps line numbers aligned for any caller that reports them.
	 *
	 * @param  string $source Raw PHP source.
	 * @return string Source with T_COMMENT / T_DOC_COMMENT blanked out.
	 */
	private static function strip_comments( string $source ): string {
		// token_get_all() needs an open tag to treat the input as PHP.
		$tokens = @token_get_all( '<?php ' . $source ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a partial method body can warn; the tokens are still usable.
		$code   = '';

		foreach ( $tokens as $token ) {
			if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				$code .= str_repeat( "\n", substr_count( $token[1], "\n" ) );
				continue;
			}

			$code .= is_array( $token ) ? $token[1] : $token;
		}

		return $code;
	}
}
