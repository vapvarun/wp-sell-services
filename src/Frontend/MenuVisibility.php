<?php
/**
 * Role-based menu visibility.
 *
 * Lets a site owner hide individual frontend-dashboard sections (and, via the
 * same option, admin submenu pages) per user role, without code. Storage shape:
 *
 *   get_option( 'wpss_menu_visibility' ) = array(
 *       '<role_slug>' => array(
 *           'dashboard' => array( '<section_key>', ... ), // hidden dashboard nav items
 *           'admin'     => array( '<menu_slug>',   ... ), // hidden admin submenu pages
 *       ),
 *   )
 *
 * An empty option (the default) hides nothing, so upgrades never change what a
 * role sees. A section/page is hidden for a user when ANY of the user's roles
 * lists it — most-restrictive wins, matching the "hide X for role Y" intent.
 *
 * @package WPSellServices\Frontend
 * @since   1.5.2
 */

namespace WPSellServices\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Applies role-based visibility to the dashboard nav and the admin menu.
 *
 * @since 1.5.2
 */
class MenuVisibility {

	/**
	 * Option that stores the per-role visibility map.
	 *
	 * @since 1.5.2
	 * @var   string
	 */
	public const OPTION = 'wpss_menu_visibility';

	/**
	 * Register hooks.
	 *
	 * @since 1.5.2
	 *
	 * @return void
	 */
	public function register(): void {
		// Frontend dashboard: deny access to a hidden section (the nav filters
		// itself off the same access check).
		add_filter( 'wpss_can_access_dashboard_section', array( $this, 'filter_section_access' ), 10, 3 );

		// Admin menu: remove hidden submenu pages late, after every page is
		// registered.
		add_action( 'admin_menu', array( $this, 'hide_admin_pages' ), 999 );

		// Settings UI: register the option in the Advanced tab's group so its form
		// saves it, and render the role x section matrix in that tab. Uses the
		// current sections hook (unified .wpss-card chrome), not the legacy action.
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'wpss_settings_sections_advanced', array( $this, 'render_settings_ui' ) );
	}

	/**
	 * Dashboard sections that can be hidden, with their labels.
	 *
	 * The stable key set the frontend nav uses (free + Pro 'analytics'). Kept here
	 * so the settings UI does not have to instantiate the dashboard.
	 *
	 * @since 1.5.2
	 *
	 * @return array<string,string>
	 */
	public static function dashboard_sections(): array {
		return array(
			'orders'        => __( 'My Orders', 'wp-sell-services' ),
			'favorites'     => __( 'Favorites', 'wp-sell-services' ),
			'requests'      => __( 'Buyer Requests', 'wp-sell-services' ),
			'services'      => __( 'My Services', 'wp-sell-services' ),
			'sales'         => __( 'Sales Orders', 'wp-sell-services' ),
			'earnings'      => __( 'Earnings & Payouts', 'wp-sell-services' ),
			'portfolio'     => __( 'Portfolio', 'wp-sell-services' ),
			'analytics'     => __( 'Analytics', 'wp-sell-services' ),
			'messages'      => __( 'Messages', 'wp-sell-services' ),
			'notifications' => __( 'Notifications', 'wp-sell-services' ),
			'disputes'      => __( 'Disputes', 'wp-sell-services' ),
			'profile'       => __( 'Profile', 'wp-sell-services' ),
		);
	}

	/**
	 * Register the visibility option in the Advanced settings group.
	 *
	 * @since 1.5.2
	 *
	 * @return void
	 */
	public function register_setting(): void {
		// Own settings group (not wpss_advanced): saving only this matrix must not
		// reset the other Advanced settings, which WP would do for any field of a
		// shared group absent from the submit.
		register_setting(
			'wpss_menu_visibility',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Sanitize the posted matrix into { role: { dashboard: [section_key, ...] } }.
	 *
	 * Only known section keys and existing roles survive; a role with nothing
	 * hidden is dropped so the stored map stays minimal.
	 *
	 * @since 1.5.2
	 *
	 * @param  mixed $input Raw posted value.
	 * @return array
	 */
	public function sanitize( $input ): array {
		$valid_sections = array_keys( self::dashboard_sections() );
		$valid_roles    = array_keys( wp_roles()->get_names() );
		$clean          = array();

		if ( ! is_array( $input ) ) {
			return $clean;
		}

		foreach ( $input as $role => $surfaces ) {
			$role = sanitize_key( $role );

			if ( ! in_array( $role, $valid_roles, true ) || ! is_array( $surfaces ) ) {
				continue;
			}

			$hidden = array();

			if ( ! empty( $surfaces['dashboard'] ) && is_array( $surfaces['dashboard'] ) ) {
				foreach ( $surfaces['dashboard'] as $section ) {
					$section = sanitize_key( $section );
					if ( in_array( $section, $valid_sections, true ) ) {
						$hidden[] = $section;
					}
				}
			}

			if ( ! empty( $hidden ) ) {
				$clean[ $role ]['dashboard'] = array_values( array_unique( $hidden ) );
			}
		}

		return $clean;
	}

	/**
	 * Render the role x dashboard-section "hide" matrix in the Advanced tab.
	 *
	 * @since 1.5.2
	 *
	 * @return void
	 */
	public function render_settings_ui(): void {
		$map      = get_option( self::OPTION, array() );
		$sections = self::dashboard_sections();
		$roles    = wp_roles()->get_names();
		?>
		<div class="wpss-card" data-section="menu-visibility">
			<div class="wpss-card__head">
				<p class="wpss-card__title"><?php esc_html_e( 'MENU VISIBILITY', 'wp-sell-services' ); ?></p>
				<p class="wpss-card__desc">
					<?php esc_html_e( 'Tick a box to hide that dashboard section from a role. Hiding a section also blocks its direct URL. Leave everything unticked to show all sections to everyone (the default).', 'wp-sell-services' ); ?>
				</p>
			</div>
			<div class="wpss-card__body">
				<form method="post" action="options.php">
					<?php settings_fields( 'wpss_menu_visibility' ); ?>
				<div style="overflow-x:auto;">
					<table class="widefat striped" style="min-width:640px;">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Dashboard section', 'wp-sell-services' ); ?></th>
								<?php foreach ( $roles as $role_slug => $role_name ) : ?>
									<th style="text-align:center;"><?php echo esc_html( $role_name ); ?></th>
								<?php endforeach; ?>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $sections as $section_key => $section_label ) : ?>
								<tr>
									<td><?php echo esc_html( $section_label ); ?></td>
									<?php
									foreach ( $roles as $role_slug => $role_name ) :
										$hidden = ! empty( $map[ $role_slug ]['dashboard'] ) && in_array( $section_key, (array) $map[ $role_slug ]['dashboard'], true );
										$name   = self::OPTION . '[' . esc_attr( $role_slug ) . '][dashboard][]';
										?>
										<td style="text-align:center;">
											<input type="checkbox"
												name="<?php echo esc_attr( $name ); ?>"
												value="<?php echo esc_attr( $section_key ); ?>"
												<?php checked( $hidden ); ?>
												aria-label="<?php echo esc_attr( sprintf( /* translators: 1: section, 2: role */ __( 'Hide %1$s from %2$s', 'wp-sell-services' ), $section_label, $role_name ) ); ?>">
										</td>
									<?php endforeach; ?>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
					<div class="wpss-settings-section__footer">
						<?php submit_button( __( 'Save Menu Visibility', 'wp-sell-services' ), 'primary', 'submit', false ); ?>
					</div>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Deny a dashboard section that is hidden for the current user's role.
	 *
	 * Only ever tightens access — never re-grants a section another rule already
	 * denied (e.g. a vendor-only section for a non-vendor).
	 *
	 * @since 1.5.2
	 *
	 * @param bool   $can_access Current access decision.
	 * @param string $section    Section key.
	 * @param int    $user_id    User ID.
	 * @return bool
	 */
	public function filter_section_access( bool $can_access, string $section, int $user_id ): bool {
		if ( ! $can_access ) {
			return false;
		}

		return ! in_array( $section, self::hidden_for_user( 'dashboard', $user_id ), true );
	}

	/**
	 * Remove admin submenu pages hidden for the current user's role.
	 *
	 * @since 1.5.2
	 *
	 * @return void
	 */
	public function hide_admin_pages(): void {
		$hidden = self::hidden_for_user( 'admin', get_current_user_id() );

		if ( empty( $hidden ) ) {
			return;
		}

		foreach ( $hidden as $menu_slug ) {
			// Parent slug is the plugin's top-level menu; the free plugin registers
			// every page under it.
			remove_submenu_page( 'wp-sell-services', $menu_slug );
		}
	}

	/**
	 * Hidden keys for a surface, unioned across the user's roles.
	 *
	 * @since 1.5.2
	 *
	 * @param string $surface 'dashboard' | 'admin'.
	 * @param int    $user_id User ID.
	 * @return string[]
	 */
	public static function hidden_for_user( string $surface, int $user_id ): array {
		if ( $user_id <= 0 ) {
			return array();
		}

		$map = get_option( self::OPTION, array() );

		if ( empty( $map ) || ! is_array( $map ) ) {
			return array();
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return array();
		}

		$hidden = array();

		foreach ( (array) $user->roles as $role ) {
			if ( ! empty( $map[ $role ][ $surface ] ) && is_array( $map[ $role ][ $surface ] ) ) {
				$hidden = array_merge( $hidden, $map[ $role ][ $surface ] );
			}
		}

		return array_values( array_unique( $hidden ) );
	}
}
