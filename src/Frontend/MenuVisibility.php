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
 * @since   1.3.0
 */

namespace WPSellServices\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Applies role-based visibility to the dashboard nav and the admin menu.
 *
 * @since 1.3.0
 */
class MenuVisibility {

	/**
	 * Option that stores the per-role visibility map.
	 *
	 * @since 1.3.0
	 * @var   string
	 */
	public const OPTION = 'wpss_menu_visibility';

	/**
	 * Register hooks.
	 *
	 * @since 1.3.0
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
	 * @since 1.3.0
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
	 * @since 1.3.0
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
	 * Sanitize the posted form into { role: { dashboard: [section_key, ...] } }.
	 *
	 * STORAGE IS UNCHANGED — still a list of HIDDEN sections per role, so
	 * existing installs, the runtime check and the admin-menu surface keep
	 * working untouched. Only the FORM speaks in terms of what is visible,
	 * because "tick to hide" inverted the usual meaning of a checked box and was
	 * the likeliest way for an owner to configure the exact opposite of what
	 * they intended.
	 *
	 * Unchecked boxes are not posted, so visible-checkboxes alone cannot tell
	 * "the owner unticked everything" from "this role was never on the form".
	 * Each rendered role therefore posts a `_present` marker and only those
	 * roles are recomputed; without it, any partial submission would hide every
	 * section from every absent role.
	 *
	 * @since 1.3.0
	 *
	 * @param  mixed $input Raw posted value.
	 * @return array<string, mixed>
	 */
	public function sanitize( $input ): array {
		$valid_sections = array_keys( self::dashboard_sections() );
		$valid_roles    = array_keys( wp_roles()->get_names() );
		$stored         = (array) get_option( self::OPTION, array() );
		$clean          = array();

		if ( ! is_array( $input ) ) {
			return $clean;
		}

		// THIS METHOD MUST BE IDEMPOTENT. update_option() calls sanitize_option()
		// internally, so WordPress runs this callback a SECOND time on the value
		// this callback just returned. That value is in stored shape — `dashboard`
		// keys, no `_present` markers — so the visible/complement logic below
		// would find nothing to recompute, drop every role, and save an empty
		// map. The setting then silently never persisted.
		//
		// A form submission always carries at least one `_present`; a re-filter
		// of our own output never does. When it is the latter, validate the value
		// and hand it straight back.
		$is_form_submission = false;

		foreach ( $input as $surfaces ) {
			if ( is_array( $surfaces ) && isset( $surfaces['_present'] ) ) {
				$is_form_submission = true;
				break;
			}
		}

		if ( ! $is_form_submission ) {
			foreach ( $input as $role => $surfaces ) {
				$role = sanitize_key( $role );

				if ( ! in_array( $role, $valid_roles, true ) || empty( $surfaces['dashboard'] ) || ! is_array( $surfaces['dashboard'] ) ) {
					continue;
				}

				$hidden = array_intersect( array_map( 'sanitize_key', $surfaces['dashboard'] ), $valid_sections );

				if ( ! empty( $hidden ) ) {
					$clean[ $role ]['dashboard'] = array_values( array_unique( $hidden ) );
				}
			}

			return $clean;
		}

		// Roles absent from this submission keep what they already had.
		foreach ( $stored as $stored_role => $surfaces ) {
			$stored_role = sanitize_key( $stored_role );

			if ( in_array( $stored_role, $valid_roles, true ) && ! empty( $surfaces['dashboard'] ) ) {
				$kept = array_intersect( array_map( 'sanitize_key', (array) $surfaces['dashboard'] ), $valid_sections );

				if ( ! empty( $kept ) ) {
					$clean[ $stored_role ]['dashboard'] = array_values( array_unique( $kept ) );
				}
			}
		}

		foreach ( $input as $role => $surfaces ) {
			$role = sanitize_key( $role );

			if ( ! in_array( $role, $valid_roles, true ) || ! is_array( $surfaces ) || empty( $surfaces['_present'] ) ) {
				continue;
			}

			$visible = array();

			if ( ! empty( $surfaces['visible'] ) && is_array( $surfaces['visible'] ) ) {
				foreach ( $surfaces['visible'] as $section ) {
					$section = sanitize_key( $section );

					if ( in_array( $section, $valid_sections, true ) ) {
						$visible[] = $section;
					}
				}
			}

			// Hidden is simply the complement of what the owner left ticked.
			$hidden = array_values( array_diff( $valid_sections, $visible ) );

			if ( ! empty( $hidden ) ) {
				$clean[ $role ]['dashboard'] = $hidden;
			} else {
				// Everything visible — drop the role so the stored map stays
				// minimal and never persists an empty array.
				unset( $clean[ $role ] );
			}
		}

		return $clean;
	}

	/**
	 * Render the role x dashboard-section "hide" matrix in the Advanced tab.
	 *
	 * @since 1.3.0
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
					<?php esc_html_e( 'Choose which dashboard sections each role can see. Everything is visible by default; unticking a section also blocks its direct URL for that role.', 'wp-sell-services' ); ?>
				</p>
			</div>
			<div class="wpss-card__body">
				<form method="post" action="options.php">
					<?php settings_fields( 'wpss_menu_visibility' ); ?>

					<p class="description" style="margin:0 0 var(--wpss-space-4,16px);">
						<?php esc_html_e( 'A member who has more than one role sees a section only if every one of their roles allows it. Hiding a section from Subscriber also hides it from someone who is both a Subscriber and a Vendor.', 'wp-sell-services' ); ?>
					</p>

					<?php
					foreach ( $roles as $role_slug => $role_name ) :
						$role_hidden  = ! empty( $map[ $role_slug ]['dashboard'] ) ? (array) $map[ $role_slug ]['dashboard'] : array();
						$hidden_count = count( array_intersect( $role_hidden, array_keys( $sections ) ) );
						$field        = self::OPTION . '[' . $role_slug . ']';
						?>
						<details class="wpss-visibility-role" style="border:1px solid var(--wpss-admin-border,#dcdcde);border-radius:var(--wpss-radius,6px);margin-bottom:12px;">
							<summary style="cursor:pointer;padding:12px 14px;display:flex;align-items:center;justify-content:space-between;gap:12px;font-weight:600;">
								<span><?php echo esc_html( $role_name ); ?></span>
								<span style="font-weight:400;color:#646970;">
									<?php
									if ( 0 === $hidden_count ) {
										esc_html_e( 'Sees everything', 'wp-sell-services' );
									} else {
										printf(
											/* translators: %d: number of hidden sections */
											esc_html( _n( '%d section hidden', '%d sections hidden', $hidden_count, 'wp-sell-services' ) ),
											(int) $hidden_count
										);
									}
									?>
								</span>
							</summary>

							<div style="padding:0 14px 14px;">
								<?php // Marks this role as present in the submission; see sanitize(). ?>
								<input type="hidden" name="<?php echo esc_attr( $field ); ?>[_present]" value="1">

								<?php foreach ( $sections as $section_key => $section_label ) : ?>
									<?php $is_visible = ! in_array( $section_key, $role_hidden, true ); ?>
									<label style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--wpss-admin-border,#f0f0f1);">
										<input type="checkbox"
											name="<?php echo esc_attr( $field ); ?>[visible][]"
											value="<?php echo esc_attr( $section_key ); ?>"
											<?php checked( $is_visible ); ?>>
										<span><?php echo esc_html( $section_label ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						</details>
					<?php endforeach; ?>

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
	 * @since 1.3.0
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
	 * @since 1.3.0
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
	 * @since 1.3.0
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
