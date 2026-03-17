<?php
/**
 * Hook Loader Class
 *
 * @package WPSellServices\Core
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Registers all actions and filters for the plugin.
 *
 * Maintains a list of all hooks that are registered throughout the plugin,
 * and registers them with the WordPress API. Call the run function to execute
 * the list of actions and filters.
 *
 * @since 1.0.0
 */
class Loader {

	/**
	 * The array of actions registered with WordPress.
	 *
	 * @var array<int, array{hook: string, component: object|null, callback: string|callable, priority: int, accepted_args: int}>
	 */
	protected array $actions = array();

	/**
	 * The array of filters registered with WordPress.
	 *
	 * @var array<int, array{hook: string, component: object|null, callback: string|callable, priority: int, accepted_args: int}>
	 */
	protected array $filters = array();

	/**
	 * Add a new action to the collection to be registered with WordPress.
	 *
	 * @param string          $hook          The name of the WordPress action.
	 * @param object|callable $component     A reference to the instance of the object or callable.
	 * @param string|null     $callback      The name of the function/method or null if $component is callable.
	 * @param int             $priority      Optional. The priority at which the function should be fired. Default 10.
	 * @param int             $accepted_args Optional. The number of arguments. Default 1.
	 * @return void
	 */
	public function add_action(
		string $hook,
		object|callable $component,
		?string $callback = null,
		int $priority = 10,
		int $accepted_args = 1
	): void {
		$this->actions = $this->add( $this->actions, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * Add a new filter to the collection to be registered with WordPress.
	 *
	 * @param string          $hook          The name of the WordPress filter.
	 * @param object|callable $component     A reference to the instance of the object or callable.
	 * @param string|null     $callback      The name of the function/method or null if $component is callable.
	 * @param int             $priority      Optional. The priority at which the function should be fired. Default 10.
	 * @param int             $accepted_args Optional. The number of arguments. Default 1.
	 * @return void
	 */
	public function add_filter(
		string $hook,
		object|callable $component,
		?string $callback = null,
		int $priority = 10,
		int $accepted_args = 1
	): void {
		$this->filters = $this->add( $this->filters, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * A utility function to register the actions and hooks into a single collection.
	 *
	 * @param array<int, array{hook: string, component: object|null, callback: string|callable, priority: int, accepted_args: int}> $hooks The collection of hooks.
	 * @param string          $hook          The name of the WordPress hook.
	 * @param object|callable $component     A reference to the instance of the object or callable.
	 * @param string|null     $callback      The name of the function/method or null if $component is callable.
	 * @param int             $priority      The priority at which the function should be fired.
	 * @param int             $accepted_args The number of arguments.
	 * @return array<int, array{hook: string, component: object|null, callback: string|callable, priority: int, accepted_args: int}>
	 */
	private function add(
		array $hooks,
		string $hook,
		object|callable $component,
		?string $callback,
		int $priority,
		int $accepted_args
	): array {
		$hooks[] = array(
			'hook'          => $hook,
			'component'     => is_callable( $component ) ? null : $component,
			'callback'      => is_callable( $component ) ? $component : $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);

		return $hooks;
	}

	/**
	 * Register the filters and actions with WordPress.
	 *
	 * @return void
	 */
	public function run(): void {
		foreach ( $this->filters as $hook ) {
			$callable = $this->get_callable( $hook );
			add_filter( $hook['hook'], $callable, $hook['priority'], $hook['accepted_args'] );
		}

		foreach ( $this->actions as $hook ) {
			$callable = $this->get_callable( $hook );
			add_action( $hook['hook'], $callable, $hook['priority'], $hook['accepted_args'] );
		}
	}

	/**
	 * Get the callable for a hook.
	 *
	 * @param array{hook: string, component: object|null, callback: string|callable, priority: int, accepted_args: int} $hook The hook data.
	 * @return callable
	 */
	private function get_callable( array $hook ): callable {
		if ( null === $hook['component'] ) {
			// It's already a callable (closure or function).
			return $hook['callback'];
		}

		// It's a method on an object.
		return array( $hook['component'], $hook['callback'] );
	}
}
