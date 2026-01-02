<?php
/**
 * Base test case for WP Sell Services tests.
 *
 * @package WPSellServices\Tests
 */

declare(strict_types=1);

namespace WPSellServices\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Yoast\PHPUnitPolyfills\TestCases\TestCase as PolyfillTestCase;

/**
 * Base test case class.
 *
 * Extends Yoast polyfills for PHPUnit 10 compatibility.
 */
abstract class TestCase extends PolyfillTestCase {

	/**
	 * Set up before each test.
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();
	}

	/**
	 * Tear down after each test.
	 *
	 * @return void
	 */
	protected function tear_down(): void {
		parent::tear_down();
	}

	/**
	 * Assert that a value is a WP_Error.
	 *
	 * @param mixed  $actual  Value to check.
	 * @param string $message Optional message.
	 * @return void
	 */
	protected function assertWPError( mixed $actual, string $message = '' ): void {
		$this->assertTrue( is_wp_error( $actual ), $message ?: 'Expected WP_Error instance.' );
	}

	/**
	 * Assert that a value is not a WP_Error.
	 *
	 * @param mixed  $actual  Value to check.
	 * @param string $message Optional message.
	 * @return void
	 */
	protected function assertNotWPError( mixed $actual, string $message = '' ): void {
		if ( is_wp_error( $actual ) ) {
			$message = $message ?: 'Unexpected WP_Error: ' . $actual->get_error_message();
		}
		$this->assertFalse( is_wp_error( $actual ), $message );
	}

	/**
	 * Assert that an array has a specific key with expected value.
	 *
	 * @param string $key      Array key.
	 * @param mixed  $expected Expected value.
	 * @param array  $array    Array to check.
	 * @param string $message  Optional message.
	 * @return void
	 */
	protected function assertArrayHasKeyWithValue( string $key, mixed $expected, array $array, string $message = '' ): void {
		$this->assertArrayHasKey( $key, $array, $message ?: "Array missing key: {$key}" );
		$this->assertSame( $expected, $array[ $key ], $message ?: "Array key {$key} has unexpected value." );
	}
}
