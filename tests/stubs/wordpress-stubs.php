<?php
/**
 * WordPress function stubs for standalone testing.
 *
 * These are minimal stubs to allow unit tests to run without WordPress.
 * For full integration tests, use the WordPress test framework.
 *
 * @package WPSellServices\Tests
 */

declare(strict_types=1);

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * WordPress Error class stub.
	 */
	class WP_Error {
		public array $errors          = array();
		public array $error_data      = array();
		public array $additional_data = array();

		public function __construct( string $code = '', string $message = '', mixed $data = '' ) {
			if ( ! empty( $code ) ) {
				$this->add( $code, $message, $data );
			}
		}

		public function add( string $code, string $message, mixed $data = '' ): void {
			$this->errors[ $code ][]   = $message;
			$this->error_data[ $code ] = $data;
		}

		public function get_error_codes(): array {
			return array_keys( $this->errors );
		}

		public function get_error_code(): string {
			$codes = $this->get_error_codes();
			return $codes[0] ?? '';
		}

		public function get_error_messages( string $code = '' ): array {
			if ( empty( $code ) ) {
				$all = array();
				foreach ( $this->errors as $messages ) {
					$all = array_merge( $all, $messages );
				}
				return $all;
			}
			return $this->errors[ $code ] ?? array();
		}

		public function get_error_message( string $code = '' ): string {
			if ( empty( $code ) ) {
				$code = $this->get_error_code();
			}
			$messages = $this->get_error_messages( $code );
			return $messages[0] ?? '';
		}

		public function get_error_data( string $code = '' ): mixed {
			if ( empty( $code ) ) {
				$code = $this->get_error_code();
			}
			return $this->error_data[ $code ] ?? '';
		}

		public function has_errors(): bool {
			return ! empty( $this->errors );
		}
	}
}

if ( ! class_exists( 'WP_User' ) ) {
	/**
	 * WordPress User class stub.
	 */
	class WP_User {
		public int $ID              = 0;
		public string $user_login   = '';
		public string $user_email   = '';
		public string $display_name = '';
		public array $roles         = array();
		public array $caps          = array();
		public array $allcaps       = array();

		public function __construct( int $id = 0 ) {
			$this->ID = $id;
		}

		public function has_cap( string $cap ): bool {
			return isset( $this->allcaps[ $cap ] ) && $this->allcaps[ $cap ];
		}

		public function add_role( string $role ): void {
			$this->roles[] = $role;
		}

		public function add_cap( string $cap, bool $grant = true ): void {
			$this->allcaps[ $cap ] = $grant;
		}
	}
}

if ( ! class_exists( 'WP_Post' ) ) {
	/**
	 * WordPress Post class stub.
	 */
	class WP_Post {
		public int $ID              = 0;
		public int $post_author     = 0;
		public string $post_date    = '';
		public string $post_content = '';
		public string $post_title   = '';
		public string $post_excerpt = '';
		public string $post_status  = 'publish';
		public string $post_type    = 'post';
		public string $post_name    = '';

		public function __construct( \stdClass|array $post = array() ) {
			if ( is_array( $post ) ) {
				$post = (object) $post;
			}
			foreach ( get_object_vars( $post ) as $key => $value ) {
				if ( property_exists( $this, $key ) ) {
					$this->$key = $value;
				}
			}
		}
	}
}

// Common WordPress functions.
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( string $str ): string {
		return htmlspecialchars( strip_tags( trim( $str ) ), ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( string $str ): string {
		return htmlspecialchars( strip_tags( $str ), ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( string $str ): string {
		// Simplified - allow basic HTML.
		return strip_tags( $str, '<p><a><strong><em><ul><ol><li><br><h1><h2><h3><h4><h5><h6>' );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( mixed $maybeint ): int {
		return abs( (int) $maybeint );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( mixed $data, int $options = 0, int $depth = 512 ): string|false {
		return json_encode( $data, $options, $depth );
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( mixed $thing ): bool {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( string $capability ): bool {
		return true; // Stub - always returns true.
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id(): int {
		return 1; // Stub - returns default user ID.
	}
}

if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( string $text, string $domain = 'default' ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( string $url ): string {
		return filter_var( $url, FILTER_SANITIZE_URL ) ?: '';
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( array|string $args, array $defaults = array() ): array {
		if ( is_string( $args ) ) {
			parse_str( $args, $args );
		}
		return array_merge( $defaults, $args );
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( string $hook, callable $callback, int $priority = 10, int $args = 1 ): bool {
		return true; // Stub.
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( string $hook, callable $callback, int $priority = 10, int $args = 1 ): bool {
		return true; // Stub.
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( string $hook, mixed ...$args ): void {
		// Stub - no-op.
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( string $hook, mixed $value, mixed ...$args ): mixed {
		return $value; // Stub - returns value unchanged.
	}
}

// phpcs:enable
