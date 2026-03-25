<?php
/**
 * Integration Manager
 *
 * @package WPSellServices\Integrations
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Integrations;

defined( 'ABSPATH' ) || exit;

use WPSellServices\Integrations\Contracts\EcommerceAdapterInterface;
use WPSellServices\Integrations\Standalone\StandaloneAdapter;

/**
 * Manages all e-commerce and third-party integrations.
 *
 * @since 1.0.0
 */
class IntegrationManager {

	/**
	 * Registered e-commerce adapters.
	 *
	 * @var array<string, EcommerceAdapterInterface>
	 */
	private array $adapters = array();

	/**
	 * Active adapter.
	 *
	 * @var EcommerceAdapterInterface|null
	 */
	private ?EcommerceAdapterInterface $active_adapter = null;

	/**
	 * Initialize integrations.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->register_default_adapters();
		$this->detect_active_adapter();
		$this->init_active_adapter();
	}

	/**
	 * Register default e-commerce adapters.
	 *
	 * @return void
	 */
	private function register_default_adapters(): void {
		// Standalone adapter (free version includes this as the default).
		$this->register_adapter( 'standalone', new StandaloneAdapter() );

		/**
		 * Filter to register additional e-commerce adapters.
		 *
		 * Pro version uses this to add WooCommerce, EDD, Fluent Cart, SureCart adapters.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, EcommerceAdapterInterface> $adapters Registered adapters.
		 */
		$this->adapters = apply_filters( 'wpss_ecommerce_adapters', $this->adapters );
	}

	/**
	 * Register an e-commerce adapter.
	 *
	 * @param string                    $id      Unique adapter ID.
	 * @param EcommerceAdapterInterface $adapter Adapter instance.
	 * @return void
	 */
	public function register_adapter( string $id, EcommerceAdapterInterface $adapter ): void {
		$this->adapters[ $id ] = $adapter;
	}

	/**
	 * Detect and set the active adapter based on settings or availability.
	 *
	 * @return void
	 */
	private function detect_active_adapter(): void {
		// Get configured adapter from settings.
		$settings           = get_option( 'wpss_general', array() );
		$configured_adapter = $settings['ecommerce_platform'] ?? 'auto';

		if ( 'auto' !== $configured_adapter && isset( $this->adapters[ $configured_adapter ] ) ) {
			$adapter = $this->adapters[ $configured_adapter ];
			if ( $adapter->is_active() ) {
				$this->active_adapter = $adapter;
				return;
			}
		}

		// Auto-detect: prefer non-standalone adapters over standalone.
		// Standalone always returns true for is_active(), so check it last.
		$standalone = null;
		foreach ( $this->adapters as $adapter ) {
			if ( 'standalone' === $adapter->get_id() ) {
				$standalone = $adapter;
				continue;
			}
			if ( $adapter->is_active() ) {
				$this->active_adapter = $adapter;
				return;
			}
		}

		// Fall back to standalone if no other adapter is active.
		if ( null !== $standalone && $standalone->is_active() ) {
			$this->active_adapter = $standalone;
		}
	}

	/**
	 * Initialize the active adapter.
	 *
	 * @return void
	 */
	private function init_active_adapter(): void {
		if ( null !== $this->active_adapter ) {
			$this->active_adapter->init();

			/**
			 * Fires after the active e-commerce adapter is initialized.
			 *
			 * @since 1.0.0
			 *
			 * @param EcommerceAdapterInterface $adapter The active adapter.
			 */
			do_action( 'wpss_adapter_initialized', $this->active_adapter );
		}

	}

	/**
	 * Get the active adapter.
	 *
	 * @return EcommerceAdapterInterface|null
	 */
	public function get_active_adapter(): ?EcommerceAdapterInterface {
		return $this->active_adapter;
	}

	/**
	 * Get all registered adapters.
	 *
	 * @return array<string, EcommerceAdapterInterface>
	 */
	public function get_adapters(): array {
		return $this->adapters;
	}

	/**
	 * Get a specific adapter by ID.
	 *
	 * @param string $id Adapter ID.
	 * @return EcommerceAdapterInterface|null
	 */
	public function get_adapter( string $id ): ?EcommerceAdapterInterface {
		return $this->adapters[ $id ] ?? null;
	}

	/**
	 * Check if a specific adapter is active.
	 *
	 * @param string $id Adapter ID.
	 * @return bool
	 */
	public function is_adapter_active( string $id ): bool {
		return null !== $this->active_adapter && $this->active_adapter->get_id() === $id;
	}
}
