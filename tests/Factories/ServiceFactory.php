<?php
/**
 * Service Factory for testing.
 *
 * @package WPSellServices\Tests\Factories
 */

declare(strict_types=1);

namespace WPSellServices\Tests\Factories;

use WPSellServices\Models\Service;
use WPSellServices\Models\ServicePackage;
use WPSellServices\Models\ServiceAddon;
use WPSellServices\Services\ServiceManager;

/**
 * Creates test services with various configurations.
 */
class ServiceFactory {

	/**
	 * Counter for unique service generation.
	 *
	 * @var int
	 */
	private static int $counter = 0;

	/**
	 * Create a simple service (title, description, single package).
	 *
	 * @param array $attrs Override attributes.
	 * @return Service|array Returns Service object or array for testing.
	 */
	public static function simple( array $attrs = array() ): Service|array {
		++self::$counter;

		$data = array_merge(
			array(
				'title'    => 'Test Service ' . self::$counter,
				'content'  => 'This is a test service description.',
				'excerpt'  => 'Short description for test service.',
				'author'   => $attrs['vendor_id'] ?? 1,
				'status'   => 'publish',
				'packages' => array(
					self::package_data( 'Basic', 49.99, 3 ),
				),
			),
			$attrs
		);

		return self::create( $data );
	}

	/**
	 * Create a service with buyer requirements.
	 *
	 * @param array $attrs Override attributes.
	 * @return Service|array
	 */
	public static function with_requirements( array $attrs = array() ): Service|array {
		++self::$counter;

		$data = array_merge(
			array(
				'title'        => 'Service with Requirements ' . self::$counter,
				'content'      => 'This service has buyer requirements.',
				'excerpt'      => 'Service requiring buyer input.',
				'author'       => $attrs['vendor_id'] ?? 1,
				'status'       => 'publish',
				'packages'     => array(
					self::package_data( 'Standard', 79.99, 5 ),
				),
				'requirements' => array(
					array(
						'field_type'  => 'text',
						'label'       => 'Project Title',
						'description' => 'What is your project called?',
						'is_required' => true,
						'sort_order'  => 0,
					),
					array(
						'field_type'  => 'select',
						'label'       => 'Style Preference',
						'description' => 'Choose your preferred style.',
						'options'     => array( 'Modern', 'Classic', 'Minimalist' ),
						'is_required' => true,
						'sort_order'  => 1,
					),
					array(
						'field_type'  => 'file',
						'label'       => 'Reference Files',
						'description' => 'Upload any reference images or documents.',
						'is_required' => false,
						'sort_order'  => 2,
					),
				),
			),
			$attrs
		);

		return self::create( $data );
	}

	/**
	 * Create a service with a single pricing plan.
	 *
	 * @param array $attrs Override attributes.
	 * @return Service|array
	 */
	public static function single_plan( array $attrs = array() ): Service|array {
		++self::$counter;

		$data = array_merge(
			array(
				'title'    => 'Single Plan Service ' . self::$counter,
				'content'  => 'This service has one comprehensive plan.',
				'excerpt'  => 'Complete package.',
				'author'   => $attrs['vendor_id'] ?? 1,
				'status'   => 'publish',
				'packages' => array(
					self::package_data(
						'Complete Package',
						149.99,
						7,
						5,
						array(
							'All features included',
							'Priority support',
							'Source files',
							'Unlimited revisions within scope',
						)
					),
				),
			),
			$attrs
		);

		return self::create( $data );
	}

	/**
	 * Create a service with multiple pricing plans (Basic/Standard/Premium).
	 *
	 * @param array $attrs Override attributes.
	 * @return Service|array
	 */
	public static function multi_plan( array $attrs = array() ): Service|array {
		++self::$counter;

		$data = array_merge(
			array(
				'title'    => 'Multi Plan Service ' . self::$counter,
				'content'  => 'This service offers multiple pricing tiers.',
				'excerpt'  => 'Choose from Basic, Standard, or Premium.',
				'author'   => $attrs['vendor_id'] ?? 1,
				'status'   => 'publish',
				'packages' => array(
					self::package_data(
						'Basic',
						29.99,
						5,
						1,
						array( 'Basic delivery', '1 revision' )
					),
					self::package_data(
						'Standard',
						59.99,
						3,
						3,
						array( 'Standard delivery', '3 revisions', 'Source files' )
					),
					self::package_data(
						'Premium',
						99.99,
						1,
						-1, // Unlimited revisions.
						array( 'Express delivery', 'Unlimited revisions', 'Source files', 'Priority support' )
					),
				),
			),
			$attrs
		);

		return self::create( $data );
	}

	/**
	 * Create a service with add-ons/extras.
	 *
	 * @param array $attrs Override attributes.
	 * @return Service|array
	 */
	public static function with_addons( array $attrs = array() ): Service|array {
		++self::$counter;

		$data = array_merge(
			array(
				'title'    => 'Service with Addons ' . self::$counter,
				'content'  => 'This service has extra add-on options.',
				'excerpt'  => 'Enhance with add-ons.',
				'author'   => $attrs['vendor_id'] ?? 1,
				'status'   => 'publish',
				'packages' => array(
					self::package_data( 'Standard', 79.99, 5, 2 ),
				),
				'addons'   => array(
					array(
						'name'         => 'Rush Delivery',
						'description'  => 'Get your order 2 days faster.',
						'price'        => 49.99,
						'extra_days'   => -2,
						'max_quantity' => 1,
						'is_required'  => false,
						'sort_order'   => 0,
						'is_active'    => true,
					),
					array(
						'name'         => 'Extra Revisions',
						'description'  => 'Add 2 more revisions.',
						'price'        => 19.99,
						'extra_days'   => 0,
						'max_quantity' => 3,
						'is_required'  => false,
						'sort_order'   => 1,
						'is_active'    => true,
					),
					array(
						'name'         => 'Source Files',
						'description'  => 'Receive all source files.',
						'price'        => 29.99,
						'extra_days'   => 0,
						'max_quantity' => 1,
						'is_required'  => false,
						'sort_order'   => 2,
						'is_active'    => true,
					),
				),
			),
			$attrs
		);

		return self::create( $data );
	}

	/**
	 * Create a complete service with all features.
	 *
	 * @param array $attrs Override attributes.
	 * @return Service|array
	 */
	public static function complete( array $attrs = array() ): Service|array {
		++self::$counter;

		$data = array_merge(
			array(
				'title'        => 'Complete Service ' . self::$counter,
				'content'      => 'This is a fully-featured service with all options.',
				'excerpt'      => 'The ultimate service package.',
				'author'       => $attrs['vendor_id'] ?? 1,
				'status'       => 'publish',
				'packages'     => array(
					self::package_data( 'Basic', 49.99, 7, 1, array( 'Basic features' ) ),
					self::package_data( 'Standard', 99.99, 5, 3, array( 'Standard features', 'Source files' ) ),
					self::package_data( 'Premium', 199.99, 3, -1, array( 'All features', 'Priority support' ) ),
				),
				'addons'       => array(
					array(
						'name'         => 'Rush Delivery',
						'description'  => 'Express delivery.',
						'price'        => 49.99,
						'extra_days'   => -2,
						'max_quantity' => 1,
						'is_required'  => false,
						'sort_order'   => 0,
						'is_active'    => true,
					),
					array(
						'name'         => 'Extra Revisions',
						'description'  => 'Additional revisions.',
						'price'        => 19.99,
						'extra_days'   => 0,
						'max_quantity' => 5,
						'is_required'  => false,
						'sort_order'   => 1,
						'is_active'    => true,
					),
				),
				'requirements' => array(
					array(
						'field_type'  => 'text',
						'label'       => 'Business Name',
						'description' => 'Your business or project name.',
						'is_required' => true,
						'sort_order'  => 0,
					),
					array(
						'field_type'  => 'textarea',
						'label'       => 'Project Description',
						'description' => 'Describe what you need.',
						'is_required' => true,
						'sort_order'  => 1,
					),
					array(
						'field_type'  => 'file',
						'label'       => 'Brand Assets',
						'description' => 'Upload logos, fonts, or brand guidelines.',
						'is_required' => false,
						'sort_order'  => 2,
					),
				),
				'faqs'         => array(
					array(
						'question' => 'How long does delivery take?',
						'answer'   => 'Delivery time depends on the package selected.',
					),
					array(
						'question' => 'Can I request changes?',
						'answer'   => 'Yes, revisions are included based on your package.',
					),
				),
			),
			$attrs
		);

		return self::create( $data );
	}

	/**
	 * Create a service as draft (unpublished).
	 *
	 * @param array $attrs Override attributes.
	 * @return Service|array
	 */
	public static function draft( array $attrs = array() ): Service|array {
		return self::simple( array_merge( array( 'status' => 'draft' ), $attrs ) );
	}

	/**
	 * Create a service pending moderation.
	 *
	 * @param array $attrs Override attributes.
	 * @return Service|array
	 */
	public static function pending( array $attrs = array() ): Service|array {
		return self::simple( array_merge( array( 'status' => 'pending' ), $attrs ) );
	}

	/**
	 * Generate package data array.
	 *
	 * @param string $name          Package name.
	 * @param float  $price         Package price.
	 * @param int    $delivery_days Delivery days.
	 * @param int    $revisions     Number of revisions (-1 for unlimited).
	 * @param array  $features      List of features.
	 * @return array
	 */
	public static function package_data(
		string $name,
		float $price,
		int $delivery_days,
		int $revisions = 1,
		array $features = array()
	): array {
		return array(
			'name'          => $name,
			'description'   => "{$name} package description.",
			'price'         => $price,
			'delivery_days' => $delivery_days,
			'revisions'     => $revisions,
			'features'      => $features ?: array( "{$name} feature 1", "{$name} feature 2" ),
			'sort_order'    => 0,
			'is_active'     => true,
		);
	}

	/**
	 * Create a service using the ServiceManager or return data array.
	 *
	 * @param array $data Service data.
	 * @return Service|array
	 */
	private static function create( array $data ): Service|array {
		// In standalone mode (no WordPress), just return data array.
		// Check for global $wpdb which indicates WordPress is loaded.
		global $wpdb;

		if ( isset( $wpdb ) && $wpdb instanceof \wpdb && class_exists( ServiceManager::class ) ) {
			try {
				$manager    = new ServiceManager();
				$service_id = $manager->create( $data );

				if ( ! $service_id || is_wp_error( $service_id ) ) {
					throw new \RuntimeException( 'Failed to create service via ServiceManager.' );
				}

				return $manager->get( $service_id );
			} catch ( \Throwable $e ) {
				// Fall through to return data array.
			}
		}

		// Return data array for standalone testing.
		$data['id'] = self::$counter;
		return $data;
	}

	/**
	 * Reset the counter (for test isolation).
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$counter = 0;
	}
}
