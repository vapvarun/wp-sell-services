<?php
/**
 * Template Loader
 *
 * @package WPSellServices\Frontend
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Frontend;

/**
 * Handles template loading with theme override support.
 *
 * @since 1.0.0
 */
class TemplateLoader {

	/**
	 * Template path in theme.
	 *
	 * @var string
	 */
	private string $theme_template_path = 'wp-sell-services/';

	/**
	 * Default template path in plugin.
	 *
	 * @var string
	 */
	private string $default_path;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->default_path = WPSS_PLUGIN_PATH . 'templates/';
	}

	/**
	 * Initialize template loader.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'template_include', [ $this, 'template_include' ] );
		add_filter( 'single_template', [ $this, 'single_service_template' ] );
		add_filter( 'archive_template', [ $this, 'archive_service_template' ] );
		add_filter( 'taxonomy_template', [ $this, 'taxonomy_template' ] );
	}

	/**
	 * Filter template include.
	 *
	 * @param string $template Template path.
	 * @return string
	 */
	public function template_include( string $template ): string {
		// Check for buyer request single.
		if ( is_singular( 'wpss_request' ) ) {
			$custom = $this->locate_template( 'single-request.php' );
			if ( $custom ) {
				return $custom;
			}
		}

		// Check for buyer request archive.
		if ( is_post_type_archive( 'wpss_request' ) ) {
			$custom = $this->locate_template( 'archive-request.php' );
			if ( $custom ) {
				return $custom;
			}
		}

		return $template;
	}

	/**
	 * Filter single service template.
	 *
	 * @param string $template Template path.
	 * @return string
	 */
	public function single_service_template( string $template ): string {
		if ( ! is_singular( 'wpss_service' ) ) {
			return $template;
		}

		$custom = $this->locate_template( 'single-service.php' );

		return $custom ?: $template;
	}

	/**
	 * Filter archive service template.
	 *
	 * @param string $template Template path.
	 * @return string
	 */
	public function archive_service_template( string $template ): string {
		if ( ! is_post_type_archive( 'wpss_service' ) ) {
			return $template;
		}

		$custom = $this->locate_template( 'archive-service.php' );

		return $custom ?: $template;
	}

	/**
	 * Filter taxonomy template.
	 *
	 * @param string $template Template path.
	 * @return string
	 */
	public function taxonomy_template( string $template ): string {
		if ( is_tax( 'wpss_service_category' ) ) {
			$custom = $this->locate_template( 'taxonomy-service-category.php' );
			if ( $custom ) {
				return $custom;
			}
		}

		if ( is_tax( 'wpss_service_tag' ) ) {
			$custom = $this->locate_template( 'taxonomy-service-tag.php' );
			if ( $custom ) {
				return $custom;
			}
		}

		return $template;
	}

	/**
	 * Locate a template file.
	 *
	 * Look in theme first, then plugin templates folder.
	 *
	 * @param string $template_name Template file name.
	 * @param string $template_path Path in theme (optional).
	 * @param string $default_path  Default path (optional).
	 * @return string Template path or empty string if not found.
	 */
	public function locate_template(
		string $template_name,
		string $template_path = '',
		string $default_path = ''
	): string {
		if ( ! $template_path ) {
			$template_path = $this->theme_template_path;
		}

		if ( ! $default_path ) {
			$default_path = $this->default_path;
		}

		// Look in theme/child-theme first.
		$template = locate_template(
			[
				trailingslashit( $template_path ) . $template_name,
				$template_name,
			]
		);

		// Fallback to plugin templates.
		if ( ! $template ) {
			$plugin_template = $default_path . $template_name;
			if ( file_exists( $plugin_template ) ) {
				$template = $plugin_template;
			}
		}

		return apply_filters( 'wpss_locate_template', $template, $template_name, $template_path );
	}

	/**
	 * Get template part.
	 *
	 * Similar to get_template_part() but with theme override support.
	 *
	 * @param string $slug Template slug.
	 * @param string $name Template name (optional).
	 * @param array  $args Arguments to pass to template (optional).
	 * @return void
	 */
	public function get_template_part( string $slug, string $name = '', array $args = [] ): void {
		$templates = [];

		if ( $name ) {
			$templates[] = "{$slug}-{$name}.php";
		}

		$templates[] = "{$slug}.php";

		$template = '';
		foreach ( $templates as $template_name ) {
			$template = $this->locate_template( $template_name );
			if ( $template ) {
				break;
			}
		}

		if ( $template ) {
			$this->load_template( $template, $args );
		}
	}

	/**
	 * Load a template with arguments.
	 *
	 * @param string $template_file Template file path.
	 * @param array  $args          Arguments to pass to template.
	 * @return void
	 */
	public function load_template( string $template_file, array $args = [] ): void {
		if ( ! file_exists( $template_file ) ) {
			return;
		}

		// Extract args to make them available in template.
		if ( ! empty( $args ) && is_array( $args ) ) {
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
			extract( $args, EXTR_SKIP );
		}

		include $template_file;
	}

	/**
	 * Get template content as string.
	 *
	 * @param string $template_name Template file name.
	 * @param array  $args          Arguments to pass to template.
	 * @return string Template content.
	 */
	public function get_template_html( string $template_name, array $args = [] ): string {
		ob_start();

		$template = $this->locate_template( $template_name );
		if ( $template ) {
			$this->load_template( $template, $args );
		}

		return ob_get_clean();
	}

	/**
	 * Include a template file.
	 *
	 * @param string $template_name Template file name.
	 * @param array  $args          Arguments to pass to template.
	 * @return void
	 */
	public function include_template( string $template_name, array $args = [] ): void {
		$template = $this->locate_template( $template_name );
		if ( $template ) {
			$this->load_template( $template, $args );
		}
	}

	/**
	 * Get the theme template path.
	 *
	 * @return string
	 */
	public function get_theme_template_path(): string {
		return $this->theme_template_path;
	}

	/**
	 * Get the default plugin template path.
	 *
	 * @return string
	 */
	public function get_default_path(): string {
		return $this->default_path;
	}

	/**
	 * Check if a template exists.
	 *
	 * @param string $template_name Template file name.
	 * @return bool
	 */
	public function template_exists( string $template_name ): bool {
		return (bool) $this->locate_template( $template_name );
	}

	/**
	 * Get all available templates.
	 *
	 * @return array List of template files.
	 */
	public function get_available_templates(): array {
		$templates = [];

		// Get plugin templates.
		$plugin_templates = glob( $this->default_path . '*.php' );
		if ( $plugin_templates ) {
			foreach ( $plugin_templates as $template ) {
				$templates[] = basename( $template );
			}
		}

		// Get templates from subdirectories.
		$subdirs = [ 'partials', 'order', 'myaccount', 'dashboard', 'vendor', 'emails' ];
		foreach ( $subdirs as $subdir ) {
			$subdir_templates = glob( $this->default_path . $subdir . '/*.php' );
			if ( $subdir_templates ) {
				foreach ( $subdir_templates as $template ) {
					$templates[] = $subdir . '/' . basename( $template );
				}
			}
		}

		return $templates;
	}
}
