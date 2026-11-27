<?php
/**
 * Feature modules: one registry, one switch per optional feature.
 *
 * @package WPSellServices
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Every registered feature module, keyed by id.
 *
 * A module is an optional feature the site owner switches on or off on Settings > Features
 * (Basecamp 10551496924). Core flows (orders, delivery, messaging, reviews, disputes, earnings) are
 * not modules, and neither are gateways, shop adapters, payout rails or storage providers, which
 * keep their own registries and switches.
 *
 * Read after `init` priority 0: labels are translated, so the registry is built no earlier.
 *
 * @since 2.0.0
 *
 * @param bool $fresh Rebuild instead of returning the registry frozen at boot.
 * @return array<string, array{id: string, name: string, description: string, group: string, tier: string, default: bool, requires: string[], boot: ?callable, seed: ?callable, settings_url: string, available: bool, unavailable_reason: string}>
 */
function wpss_modules( bool $fresh = false ): array {
	static $frozen = null;

	if ( null !== $frozen && ! $fresh ) {
		return $frozen;
	}

	/**
	 * Register feature modules.
	 *
	 * Each entry, keyed by a lowercase id:
	 * - name, description: shown on Settings > Features.
	 * - group: selling, money, brand, engagement or operations.
	 * - tier: 'free' or 'pro'. Modules never check a licence.
	 * - default: on or off for a site that never chose.
	 * - requires: module ids that must be on for this one to be on.
	 * - boot: called once at `init` priority 0 whether the module is on or off, so existing orders,
	 *   ledger rows, webhooks and refunds keep working; the module gates new use on
	 *   wpss_module_enabled().
	 * - seed: returns the old switch's value (or null) the first time a site sees the module, so an
	 *   upgrade keeps every feature that was running.
	 * - settings_url, available, unavailable_reason.
	 *
	 * @since 2.0.0
	 *
	 * @param array $modules Modules keyed by id.
	 */
	$modules = (array) apply_filters( 'wpss_modules', array() );
	$valid   = array();

	foreach ( $modules as $id => $module ) {
		if ( ! is_string( $id ) || ! preg_match( '/^[a-z][a-z0-9_]*$/', $id ) || ! is_array( $module ) || empty( $module['name'] ) ) {
			_doing_it_wrong(
				'wpss_modules',
				/* translators: %s: module id. */
				esc_html( sprintf( __( 'The module "%s" was ignored: it needs a lowercase id and a name.', 'wp-sell-services' ), (string) $id ) ),
				'2.0.0'
			);
			continue;
		}

		$valid[ $id ] = array(
			'id'                 => $id,
			'name'               => (string) $module['name'],
			'description'        => (string) ( $module['description'] ?? '' ),
			'group'              => (string) ( $module['group'] ?? 'operations' ),
			'tier'               => 'pro' === ( $module['tier'] ?? '' ) ? 'pro' : 'free',
			'default'            => ! empty( $module['default'] ),
			'requires'           => array_values( array_filter( (array) ( $module['requires'] ?? array() ), 'is_string' ) ),
			'boot'               => is_callable( $module['boot'] ?? null ) ? $module['boot'] : null,
			'seed'               => is_callable( $module['seed'] ?? null ) ? $module['seed'] : null,
			'settings_url'       => (string) ( $module['settings_url'] ?? '' ),
			'available'          => ! isset( $module['available'] ) || (bool) $module['available'],
			'unavailable_reason' => (string) ( $module['unavailable_reason'] ?? '' ),
		);
	}

	if ( did_action( 'init' ) ) {
		$frozen = $valid;
	}

	return $valid;
}

/**
 * The owner's saved switch for each module, falling back to the module's default.
 *
 * @since 2.0.0
 *
 * @param array|null $stored Saved switches (id => bool); null reads the option.
 * @return array<string, bool> Module id => switched on (before requirements).
 */
function wpss_module_switches( ?array $stored = null ): array {
	$stored   = $stored ?? (array) get_option( 'wpss_modules', array() );
	$switches = array();

	foreach ( wpss_modules() as $id => $module ) {
		$switches[ $id ] = $module['available'] && (bool) ( $stored[ $id ] ?? $module['default'] );
	}

	return $switches;
}

/**
 * Whether a feature module is on: registered, available, switched on, and every module it
 * requires is on too.
 *
 * @since 2.0.0
 *
 * @param string $id Module id.
 * @return bool
 */
function wpss_module_enabled( string $id ): bool {
	$modules  = wpss_modules();
	$switches = wpss_module_switches();
	$seen     = array();
	$queue    = array( $id );

	while ( $queue ) {
		$next = array_shift( $queue );
		if ( isset( $seen[ $next ] ) ) {
			continue;
		}
		if ( empty( $switches[ $next ] ) ) {
			return false;
		}
		$seen[ $next ] = true;
		$queue         = array_merge( $queue, $modules[ $next ]['requires'] );
	}

	return true;
}

/**
 * Switch modules on or off.
 *
 * Turning a module on is refused while a module it requires is off. Turning a module off also
 * turns off every module that requires it. Off only hides new use: existing orders, ledger rows,
 * webhooks and refunds of the module keep working.
 *
 * @since 2.0.0
 *
 * @param array<string, bool> $changes Module id => on.
 * @return array<string, bool>|WP_Error Every switch that changed, cascades included.
 */
function wpss_set_modules( array $changes ) {
	$modules = wpss_modules();
	$stored  = (array) get_option( 'wpss_modules', array() );
	$before  = wpss_module_switches( $stored );

	foreach ( $changes as $id => $on ) {
		if ( ! isset( $modules[ $id ] ) ) {
			/* translators: %s: module id. */
			return new WP_Error( 'wpss_module_unknown', sprintf( __( 'There is no feature called "%s".', 'wp-sell-services' ), (string) $id ), array( 'status' => 400 ) );
		}
		if ( $on && ! $modules[ $id ]['available'] ) {
			/* translators: %s: feature name. */
			return new WP_Error( 'wpss_module_unavailable', sprintf( __( '%s is not available yet.', 'wp-sell-services' ), $modules[ $id ]['name'] ), array( 'status' => 400 ) );
		}
		$stored[ $id ] = (bool) $on;
	}

	$after = wpss_module_switches( $stored );

	foreach ( $changes as $id => $on ) {
		$missing = $on ? array_filter( $modules[ $id ]['requires'], static fn( $r ) => empty( $after[ $r ] ) ) : array();
		if ( $missing ) {
			$names = array_map( static fn( $r ) => $modules[ $r ]['name'] ?? $r, $missing );
			/* translators: 1: feature name, 2: comma-separated feature names. */
			return new WP_Error( 'wpss_module_needs', sprintf( __( '%1$s needs %2$s turned on first.', 'wp-sell-services' ), $modules[ $id ]['name'], implode( ', ', $names ) ), array( 'status' => 400 ) );
		}
	}

	// A module whose requirement went off goes off with it, repeated until nothing else falls.
	do {
		$fell = false;
		foreach ( $modules as $id => $module ) {
			if ( ! empty( $after[ $id ] ) && array_filter( $module['requires'], static fn( $r ) => empty( $after[ $r ] ) ) ) {
				$stored[ $id ] = false;
				$after[ $id ]  = false;
				$fell          = true;
			}
		}
	} while ( $fell );

	$changed = array_filter( $after, static fn( $on, $id ) => $on !== ( $before[ $id ] ?? null ), ARRAY_FILTER_USE_BOTH );

	update_option( 'wpss_modules', $stored, true );

	if ( $changed ) {
		/**
		 * Fires after feature modules are switched on or off.
		 *
		 * @since 2.0.0
		 *
		 * @param array<string, bool> $changed Module id => now on, cascades included.
		 */
		do_action( 'wpss_modules_changed', $changed );
	}

	return $changed;
}

/**
 * Boot every registered module once, requirements first, after seeding new ones from their old
 * switch. Hooked at `init` priority 0.
 *
 * @since 2.0.0
 *
 * @return void
 */
function wpss_boot_modules(): void {
	static $booted = false;

	if ( $booted ) {
		return;
	}
	$booted = true;

	$modules = wpss_modules( true );

	wpss_seed_modules();

	$done = array();
	$boot = static function ( string $id, array $path ) use ( &$boot, &$done, $modules ): void {
		if ( isset( $done[ $id ] ) || in_array( $id, $path, true ) || ! isset( $modules[ $id ] ) ) {
			return;
		}
		foreach ( $modules[ $id ]['requires'] as $required ) {
			$boot( $required, array_merge( $path, array( $id ) ) );
		}
		$done[ $id ] = true;
		if ( $modules[ $id ]['boot'] ) {
			call_user_func( $modules[ $id ]['boot'] );
		}
	};

	foreach ( array_keys( $modules ) as $id ) {
		$boot( $id, array() );
	}
}

/**
 * Upgrade: the first time a site meets a module, keep whatever its old switch said.
 *
 * Only modules with no saved switch are seeded, so an owner's later choice is never overwritten.
 *
 * @since 2.0.0
 *
 * @return string[] Module ids seeded.
 */
function wpss_seed_modules(): array {
	$stored = (array) get_option( 'wpss_modules', array() );
	$seeded = array();

	foreach ( wpss_modules() as $id => $module ) {
		if ( array_key_exists( $id, $stored ) || ! $module['seed'] ) {
			continue;
		}
		$value = call_user_func( $module['seed'] );
		if ( null !== $value ) {
			$stored[ $id ] = (bool) $value;
			$seeded[]      = $id;
		}
	}

	if ( $seeded ) {
		update_option( 'wpss_modules', $stored, true );
	}

	return $seeded;
}

/**
 * The Features screen's rows, grouped, with each module's state and what it needs.
 *
 * @since 2.0.0
 *
 * @return array<string, array{label: string, modules: array<int, array>}> Group => label and rows.
 */
function wpss_module_rows(): array {
	$labels  = array(
		'selling'    => __( 'Selling', 'wp-sell-services' ),
		'money'      => __( 'Money', 'wp-sell-services' ),
		'brand'      => __( 'Branding', 'wp-sell-services' ),
		'engagement' => __( 'Engagement', 'wp-sell-services' ),
		'operations' => __( 'Operations', 'wp-sell-services' ),
	);
	$modules = wpss_modules();
	$groups  = array();

	foreach ( $modules as $id => $module ) {
		$group = $module['group'];
		if ( ! isset( $groups[ $group ] ) ) {
			$groups[ $group ] = array(
				'label'   => $labels[ $group ] ?? ucfirst( $group ),
				'modules' => array(),
			);
		}

		$groups[ $group ]['modules'][] = array(
			'id'                 => $id,
			'name'               => $module['name'],
			'description'        => $module['description'],
			'tier'               => $module['tier'],
			'enabled'            => wpss_module_enabled( $id ),
			'available'          => $module['available'],
			'unavailable_reason' => $module['unavailable_reason'],
			'requires'           => array_map( static fn( $r ) => $modules[ $r ]['name'] ?? $r, $module['requires'] ),
			'settings_url'       => $module['settings_url'],
		);
	}

	// The owner's reading order, then anything an extension invents.
	return array_intersect_key( array_replace( array_fill_keys( array_keys( $labels ), null ), $groups ), $groups );
}

/**
 * A module's on/off state for its own settings card, linking to Settings > Features.
 *
 * The switch lives in one place; a feature's settings card shows where it is instead of a
 * second checkbox.
 *
 * @since 2.0.0
 *
 * @param string $id Module id.
 * @return void
 */
function wpss_render_module_state( string $id ): void {
	$on   = wpss_module_enabled( $id );
	$link = '<a href="' . esc_url( wpss_get_settings_url( 'features' ) ) . '">' . esc_html__( 'Settings > Features', 'wp-sell-services' ) . '</a>';
	?>
	<p class="wpss-module-state">
		<span class="wpss-gateway-badge wpss-gateway-badge--<?php echo $on ? 'live' : 'off'; ?>"><?php echo $on ? esc_html__( 'On', 'wp-sell-services' ) : esc_html__( 'Off', 'wp-sell-services' ); ?></span>
		<?php
		/* translators: %s: link to Settings > Features. */
		echo wp_kses( sprintf( __( 'Switch this feature on or off in %s.', 'wp-sell-services' ), $link ), array( 'a' => array( 'href' => array() ) ) );
		?>
	</p>
	<?php
}
