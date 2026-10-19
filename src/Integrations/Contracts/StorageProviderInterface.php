<?php
/**
 * Storage Provider Interface
 *
 * Contract for cloud storage providers.
 *
 * @package WPSellServices\Integrations\Contracts
 * @since   1.9.0
 */

declare(strict_types=1);

namespace WPSellServices\Integrations\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * StorageProviderInterface interface.
 *
 * @since 1.0.0
 */
interface StorageProviderInterface {

	/**
	 * Get the unique provider identifier.
	 *
	 * @return string
	 */
	public function get_id(): string;

	/**
	 * Get the provider display name.
	 *
	 * @return string
	 */
	public function get_name(): string;

	/**
	 * Check if the provider is configured and ready.
	 *
	 * @return bool
	 */
	public function is_configured(): bool;

	/**
	 * Upload a file to cloud storage.
	 *
	 * @param string $local_path  Local file path.
	 * @param string $remote_path Remote path/key.
	 * @param array  $options     Upload options.
	 * @return array{key: string, url: string}|\WP_Error The stored key and URL, or the failure.
	 */
	public function upload( string $local_path, string $remote_path, array $options = [] ): array|\WP_Error;

	/**
	 * Download a file from cloud storage.
	 *
	 * @param string $remote_path Remote path/key.
	 * @param string $local_path  Local destination path.
	 * @return array{success: bool, path?: string, error?: string}
	 */
	public function download( string $remote_path, string $local_path ): array;

	/**
	 * Delete a file from cloud storage.
	 *
	 * @param string $remote_path Remote path/key.
	 * @return bool
	 */
	public function delete( string $remote_path ): bool;

	/**
	 * Check if a file exists in cloud storage.
	 *
	 * @param string $remote_path Remote path/key.
	 * @return bool
	 */
	public function exists( string $remote_path ): bool;

	/**
	 * Get a signed/presigned URL for private file access.
	 *
	 * @param string $remote_path Remote path/key.
	 * @param int    $expires     Expiration time in seconds.
	 * @return string|null
	 */
	public function get_signed_url( string $remote_path, int $expires = 3600 ): ?string;

	/**
	 * Get the public URL for a file.
	 *
	 * @param string $remote_path Remote path/key.
	 * @return string
	 */
	public function get_url( string $remote_path ): string;

	/**
	 * List files in a directory.
	 *
	 * @param string $prefix Directory prefix.
	 * @return array
	 */
	public function list_files( string $prefix = '' ): array;

	/**
	 * Get file metadata.
	 *
	 * @param string $remote_path Remote path/key.
	 * @return array|null
	 */
	public function get_metadata( string $remote_path ): ?array;
}
