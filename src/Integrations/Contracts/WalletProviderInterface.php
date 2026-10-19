<?php
/**
 * Wallet Provider Interface
 *
 * @package WPSellServices\Integrations\Contracts
 * @since   1.9.0
 */

declare(strict_types=1);

namespace WPSellServices\Integrations\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Interface for wallet providers.
 *
 * @since 1.0.0
 */
interface WalletProviderInterface {

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
	 * Check if the wallet plugin is active.
	 *
	 * @return bool
	 */
	public function is_active(): bool;

	/**
	 * Initialize the wallet provider.
	 *
	 * @return void
	 */
	public function init(): void;

	/**
	 * Get user wallet balance.
	 *
	 * @param int $user_id User ID.
	 * @return float Wallet balance.
	 */
	public function get_balance( int $user_id ): float;

	/**
	 * Credit amount to user wallet.
	 *
	 * @param int    $user_id User ID.
	 * @param float  $amount Amount to credit.
	 * @param string $description Transaction description.
	 * @param array  $meta Additional metadata.
	 * @return bool True on success.
	 */
	public function credit( int $user_id, float $amount, string $description = '', array $meta = [] ): bool;

	/**
	 * Debit amount from user wallet.
	 *
	 * @param int    $user_id User ID.
	 * @param float  $amount Amount to debit.
	 * @param string $description Transaction description.
	 * @param array  $meta Additional metadata.
	 * @return bool True on success.
	 */
	public function debit( int $user_id, float $amount, string $description = '', array $meta = [] ): bool;

	/**
	 * Check if user has sufficient balance.
	 *
	 * @param int   $user_id User ID.
	 * @param float $amount Required amount.
	 * @return bool True if sufficient.
	 */
	public function has_sufficient_balance( int $user_id, float $amount ): bool;

	/**
	 * Get user transaction history.
	 *
	 * @param int $user_id User ID.
	 * @param int $limit Number of transactions.
	 * @param int $offset Offset for pagination.
	 * @return array Transaction history.
	 */
	public function get_transactions( int $user_id, int $limit = 20, int $offset = 0 ): array;
}
