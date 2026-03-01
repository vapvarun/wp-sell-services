<?php
/**
 * Service Manager
 *
 * Business logic for service management.
 *
 * @package WPSellServices\Services
 * @since   1.0.0
 */

namespace WPSellServices\Services;

use WPSellServices\Database\Repositories\ServicePackageRepository;
use WPSellServices\Models\Service;

defined( 'ABSPATH' ) || exit;

/**
 * ServiceManager class.
 *
 * @since 1.0.0
 */
class ServiceManager {

	/**
	 * Service post type.
	 *
	 * @var string
	 */
	public const POST_TYPE = 'wpss_service';

	/**
	 * Package repository.
	 *
	 * @var ServicePackageRepository
	 */
	private ServicePackageRepository $package_repo;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->package_repo = new ServicePackageRepository();
	}

	/**
	 * Get a service by ID.
	 *
	 * @param int $service_id Service post ID.
	 * @return Service|null Service object or null.
	 */
	public function get( int $service_id ): ?Service {
		$post = get_post( $service_id );

		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}

		return Service::from_post( $post );
	}

	/**
	 * Create a new service.
	 *
	 * @param array<string, mixed> $data Service data.
	 * @return int|false Service ID or false on failure.
	 */
	public function create( array $data ): int|false {
		$defaults = array(
			'title'        => '',
			'content'      => '',
			'excerpt'      => '',
			'author'       => get_current_user_id(),
			'status'       => 'draft',
			'packages'     => array(),
			'categories'   => array(),
			'tags'         => array(),
			'gallery'      => array(),
			'faqs'         => array(),
			'requirements' => array(),
		);

		$data = wp_parse_args( $data, $defaults );

		// Validate required fields.
		if ( empty( $data['title'] ) ) {
			return false;
		}

		// Check max services limit for the author (skip for admins).
		$author_id = absint( $data['author'] );
		if ( $author_id && ! user_can( $author_id, 'manage_options' ) ) {
			$vendor_profile = \WPSellServices\Models\VendorProfile::get_by_user_id( $author_id );
			if ( $vendor_profile && $vendor_profile->has_reached_service_limit() ) {
				return false;
			}
		}

		// Create the post.
		$post_id = wp_insert_post(
			array(
				'post_type'    => self::POST_TYPE,
				'post_title'   => sanitize_text_field( $data['title'] ),
				'post_content' => wp_kses_post( $data['content'] ),
				'post_excerpt' => sanitize_textarea_field( $data['excerpt'] ),
				'post_author'  => absint( $data['author'] ),
				'post_status'  => $data['status'],
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return false;
		}

		// Save packages.
		if ( ! empty( $data['packages'] ) ) {
			$this->save_packages( $post_id, $data['packages'] );
		}

		// Save categories.
		if ( ! empty( $data['categories'] ) ) {
			wp_set_post_terms( $post_id, $data['categories'], 'wpss_service_category' );
		}

		// Save tags.
		if ( ! empty( $data['tags'] ) ) {
			wp_set_post_terms( $post_id, $data['tags'], 'wpss_service_tag' );
		}

		// Save gallery and set featured image.
		if ( ! empty( $data['gallery'] ) ) {
			$gallery_ids = array_map( 'absint', $data['gallery'] );
			update_post_meta(
				$post_id,
				'_wpss_gallery',
				array(
					'images' => array_values( array_filter( $gallery_ids ) ),
					'video'  => '',
				)
			);

			// Set featured image from first gallery image if not already set.
			if ( ! has_post_thumbnail( $post_id ) && ! empty( $gallery_ids[0] ) ) {
				set_post_thumbnail( $post_id, $gallery_ids[0] );
			}
		}

		// Save FAQs.
		if ( ! empty( $data['faqs'] ) ) {
			$this->save_faqs( $post_id, $data['faqs'] );
		}

		// Save requirements.
		if ( ! empty( $data['requirements'] ) ) {
			$this->save_requirements( $post_id, $data['requirements'] );
		}

		/**
		 * Fires after a service is created.
		 *
		 * @since 1.0.0
		 * @param int   $post_id Service post ID.
		 * @param array $data    Service data.
		 */
		do_action( 'wpss_service_created', $post_id, $data );

		return $post_id;
	}

	/**
	 * Update a service.
	 *
	 * @param int                  $service_id Service post ID.
	 * @param array<string, mixed> $data       Service data to update.
	 * @return bool True on success.
	 */
	public function update( int $service_id, array $data ): bool {
		$post = get_post( $service_id );

		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return false;
		}

		$post_data = array( 'ID' => $service_id );

		if ( isset( $data['title'] ) ) {
			$post_data['post_title'] = sanitize_text_field( $data['title'] );
		}

		if ( isset( $data['content'] ) ) {
			$post_data['post_content'] = wp_kses_post( $data['content'] );
		}

		if ( isset( $data['excerpt'] ) ) {
			$post_data['post_excerpt'] = sanitize_textarea_field( $data['excerpt'] );
		}

		if ( isset( $data['status'] ) ) {
			$post_data['post_status'] = $data['status'];
		}

		// Update post.
		$result = wp_update_post( $post_data, true );

		if ( is_wp_error( $result ) ) {
			return false;
		}

		// Update packages.
		if ( isset( $data['packages'] ) ) {
			$this->save_packages( $service_id, $data['packages'] );
		}

		// Update categories.
		if ( isset( $data['categories'] ) ) {
			wp_set_post_terms( $service_id, $data['categories'], 'wpss_service_category' );
		}

		// Update tags.
		if ( isset( $data['tags'] ) ) {
			wp_set_post_terms( $service_id, $data['tags'], 'wpss_service_tag' );
		}

		// Update gallery and set featured image.
		if ( isset( $data['gallery'] ) ) {
			$gallery_ids = array_map( 'absint', $data['gallery'] );

			// Preserve existing video URL if present.
			$existing_raw = get_post_meta( $service_id, '_wpss_gallery', true );
			$video_url    = wpss_get_gallery_video_url( $existing_raw );

			update_post_meta(
				$service_id,
				'_wpss_gallery',
				array(
					'images' => array_values( array_filter( $gallery_ids ) ),
					'video'  => $video_url,
				)
			);

			// Set featured image from first gallery image if not already set.
			if ( ! has_post_thumbnail( $service_id ) && ! empty( $gallery_ids[0] ) ) {
				set_post_thumbnail( $service_id, $gallery_ids[0] );
			}
		}

		// Update FAQs.
		if ( isset( $data['faqs'] ) ) {
			$this->save_faqs( $service_id, $data['faqs'] );
		}

		// Update requirements.
		if ( isset( $data['requirements'] ) ) {
			$this->save_requirements( $service_id, $data['requirements'] );
		}

		/**
		 * Fires after a service is updated.
		 *
		 * @since 1.0.0
		 * @param int   $service_id Service post ID.
		 * @param array $data       Updated data.
		 */
		do_action( 'wpss_service_updated', $service_id, $data );

		return true;
	}

	/**
	 * Delete a service.
	 *
	 * @param int  $service_id  Service post ID.
	 * @param bool $force_delete Whether to bypass trash.
	 * @return bool True on success.
	 */
	public function delete( int $service_id, bool $force_delete = false ): bool {
		$post = get_post( $service_id );

		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return false;
		}

		// Delete packages.
		$this->package_repo->delete_by_service( $service_id );

		// Delete FAQs.
		$this->delete_faqs( $service_id );

		// Delete requirements.
		$this->delete_requirements( $service_id );

		/**
		 * Fires before a service is deleted.
		 *
		 * @since 1.0.0
		 * @param int $service_id Service post ID.
		 */
		do_action( 'wpss_before_service_deleted', $service_id );

		$result = wp_delete_post( $service_id, $force_delete );

		return false !== $result;
	}

	/**
	 * Get services by vendor.
	 *
	 * @param int                  $vendor_id Vendor user ID.
	 * @param array<string, mixed> $args      Query arguments.
	 * @return array<Service> Array of Service objects.
	 */
	public function get_by_vendor( int $vendor_id, array $args = array() ): array {
		$defaults = array(
			'status'         => 'publish',
			'posts_per_page' => 10,
			'paged'          => 1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$query = new \WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'author'         => $vendor_id,
				'post_status'    => $args['status'],
				'posts_per_page' => $args['posts_per_page'],
				'paged'          => $args['paged'],
				'orderby'        => $args['orderby'],
				'order'          => $args['order'],
			)
		);

		$services = array();
		foreach ( $query->posts as $post ) {
			$services[] = Service::from_post( $post );
		}

		return $services;
	}

	/**
	 * Search services.
	 *
	 * @param string               $search Search term.
	 * @param array<string, mixed> $args   Query arguments.
	 * @return array<Service> Array of Service objects.
	 */
	public function search( string $search, array $args = array() ): array {
		$defaults = array(
			'posts_per_page' => 10,
			'paged'          => 1,
			'category'       => 0,
			'min_price'      => 0,
			'max_price'      => 0,
			'delivery_time'  => 0,
			'orderby'        => 'relevance',
			'order'          => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$query_args = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'publish',
			's'              => $search,
			'posts_per_page' => $args['posts_per_page'],
			'paged'          => $args['paged'],
		);

		// Category filter.
		if ( $args['category'] > 0 ) {
			$query_args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'wpss_service_category',
					'field'    => 'term_id',
					'terms'    => $args['category'],
				),
			);
		}

		// Price and delivery filters require meta queries.
		$meta_query = array();

		if ( $args['min_price'] > 0 || $args['max_price'] > 0 ) {
			// Price filtering would need to check package prices.
			// This is simplified - full implementation would join with packages table.
			if ( $args['min_price'] > 0 ) {
				$meta_query[] = array(
					'key'     => '_wpss_starting_price',
					'value'   => $args['min_price'],
					'compare' => '>=',
					'type'    => 'NUMERIC',
				);
			}
			if ( $args['max_price'] > 0 ) {
				$meta_query[] = array(
					'key'     => '_wpss_starting_price',
					'value'   => $args['max_price'],
					'compare' => '<=',
					'type'    => 'NUMERIC',
				);
			}
		}

		if ( ! empty( $meta_query ) ) {
			$query_args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		// Orderby.
		switch ( $args['orderby'] ) {
			case 'price_low':
				$query_args['meta_key'] = '_wpss_starting_price'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$query_args['orderby']  = 'meta_value_num';
				$query_args['order']    = 'ASC';
				break;
			case 'price_high':
				$query_args['meta_key'] = '_wpss_starting_price'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$query_args['orderby']  = 'meta_value_num';
				$query_args['order']    = 'DESC';
				break;
			case 'rating':
				$query_args['meta_key'] = '_wpss_rating_average'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$query_args['orderby']  = 'meta_value_num';
				$query_args['order']    = 'DESC';
				break;
			case 'popular':
				$query_args['meta_key'] = '_wpss_order_count'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$query_args['orderby']  = 'meta_value_num';
				$query_args['order']    = 'DESC';
				break;
			default:
				$query_args['orderby'] = $args['orderby'];
				$query_args['order']   = $args['order'];
		}

		$query = new \WP_Query( $query_args );

		$services = array();
		foreach ( $query->posts as $post ) {
			$services[] = Service::from_post( $post );
		}

		return $services;
	}

	/**
	 * Save service packages.
	 *
	 * @param int                        $service_id Service post ID.
	 * @param array<array<string,mixed>> $packages   Package data.
	 * @return bool True on success.
	 */
	public function save_packages( int $service_id, array $packages ): bool {
		$result = $this->package_repo->save_packages( $service_id, $packages );

		// Update starting price meta for filtering.
		$starting_price = $this->package_repo->get_starting_price( $service_id );
		if ( null !== $starting_price ) {
			update_post_meta( $service_id, '_wpss_starting_price', $starting_price );
		}

		return $result;
	}

	/**
	 * Get service packages.
	 *
	 * @param int $service_id Service post ID.
	 * @return array<object> Array of packages.
	 */
	public function get_packages( int $service_id ): array {
		return $this->package_repo->get_by_service( $service_id );
	}

	/**
	 * Save service FAQs.
	 *
	 * @param int                        $service_id Service post ID.
	 * @param array<array<string,mixed>> $faqs       FAQ data.
	 * @return void
	 */
	private function save_faqs( int $service_id, array $faqs ): void {
		// Sanitize FAQs before saving.
		$sanitized_faqs = array();
		foreach ( $faqs as $faq ) {
			$sanitized_faqs[] = array(
				'question' => sanitize_text_field( $faq['question'] ?? '' ),
				'answer'   => wp_kses_post( $faq['answer'] ?? '' ),
			);
		}
		update_post_meta( $service_id, '_wpss_faqs', $sanitized_faqs );
	}

	/**
	 * Delete service FAQs.
	 *
	 * @param int $service_id Service post ID.
	 * @return void
	 */
	private function delete_faqs( int $service_id ): void {
		delete_post_meta( $service_id, '_wpss_faqs' );
	}

	/**
	 * Save service requirements.
	 *
	 * @param int                        $service_id   Service post ID.
	 * @param array<array<string,mixed>> $requirements Requirement fields.
	 * @return void
	 */
	private function save_requirements( int $service_id, array $requirements ): void {
		// Sanitize requirements before saving.
		$sanitized_requirements = array();
		foreach ( $requirements as $field ) {
			$sanitized_requirements[] = array(
				'field_type'  => sanitize_key( $field['field_type'] ?? 'text' ),
				'label'       => sanitize_text_field( $field['label'] ?? '' ),
				'description' => sanitize_textarea_field( $field['description'] ?? '' ),
				'options'     => $field['options'] ?? array(),
				'is_required' => ! empty( $field['is_required'] ),
			);
		}
		update_post_meta( $service_id, '_wpss_requirements', $sanitized_requirements );
	}

	/**
	 * Delete service requirements.
	 *
	 * @param int $service_id Service post ID.
	 * @return void
	 */
	private function delete_requirements( int $service_id ): void {
		delete_post_meta( $service_id, '_wpss_requirements' );
	}

	/**
	 * Update service rating.
	 *
	 * @param int   $service_id Service post ID.
	 * @param float $average    Average rating.
	 * @param int   $count      Total review count.
	 * @return void
	 */
	public function update_rating( int $service_id, float $average, int $count ): void {
		update_post_meta( $service_id, '_wpss_rating_average', $average );
		update_post_meta( $service_id, '_wpss_rating_count', $count );
	}

	/**
	 * Increment order count.
	 *
	 * @param int $service_id Service post ID.
	 * @return void
	 */
	public function increment_order_count( int $service_id ): void {
		$count = (int) get_post_meta( $service_id, '_wpss_order_count', true );
		update_post_meta( $service_id, '_wpss_order_count', $count + 1 );
	}
}
