<?php
/**
 * Services: lookup, packages, gallery, add-ons and the shared services grid renderer.
 *
 * Split out of src/functions.php, which had grown to 6,187 lines and 148
 * global functions in a single file. This is a positional move only - no
 * function was renamed, resignatured or changed, so every call site is
 * untouched. src/functions.php now just requires these files.
 *
 * @package WPSellServices
 * @since   1.5.1
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get service by ID.
 *
 * @param int $service_id Service post ID.
 * @return \WPSellServices\Models\Service|null
 */
function wpss_get_service( int $service_id ): ?\WPSellServices\Models\Service {
	$post = get_post( $service_id );

	if ( ! $post || \WPSellServices\PostTypes\ServicePostType::POST_TYPE !== $post->post_type ) {
		return null;
	}

	return \WPSellServices\Models\Service::from_post( $post );
}

/**
 * Get a service's publish status (active / paused).
 *
 * Shared accessor for every service-status read. Resolves the canonical
 * _wpss_status meta written by the admin service metabox Status select
 * (values: 'active', 'paused').
 *
 * The three SEO integrations used to read '_wpss_service_status', which
 * nothing has ever written, so every "paused service should be noindexed"
 * rule silently evaluated false and paused services stayed indexed.
 *
 * @since 1.2.3
 *
 * @param int $service_id Service post ID.
 * @return string 'active' or 'paused'. Defaults to 'active' when unset, which
 *                matches how an unsaved service behaves everywhere else.
 */
function wpss_get_service_status( int $service_id ): string {
	$status = get_post_meta( $service_id, '_wpss_status', true );

	return is_string( $status ) && '' !== $status ? $status : 'active';
}

/**
 * Get service URL.
 *
 * @param int $service_id Service ID.
 * @return string
 */
function wpss_get_service_url( int $service_id ): string {
	return get_permalink( $service_id ) ?: '';
}

/**
 * Get the Create Service URL.
 *
 * Returns the URL to the Dashboard create section where vendors can create new services.
 *
 * @since 1.1.0
 *
 * @return string Create service URL (dashboard with create section).
 */
function wpss_get_create_service_url(): string {
	$dashboard_url = wpss_get_page_url( 'dashboard' );
	if ( ! $dashboard_url ) {
		return '';
	}
	return wpss_append_dashboard_section( $dashboard_url, 'create' );
}

/**
 * Whether a person may see a service at all.
 *
 * Published services are public; an unpublished one (draft, pending, private,
 * rejected) is visible only to its author and site admins. The single rule for
 * every route that resolves a service by id - GET /services/{id} had it inline
 * and its /packages, /faqs, /addons, /reviews and review-summary siblings did
 * not, so they served unpublished listings to anyone (Basecamp 10336370426).
 *
 * @since 1.8.0
 *
 * @param int      $service_id Service post ID.
 * @param int|null $user_id    Viewer; null for the current user.
 * @return bool
 */
function wpss_can_view_service( int $service_id, ?int $user_id = null ): bool {
	$service = get_post( $service_id );

	if ( ! $service || 'wpss_service' !== $service->post_type ) {
		return false;
	}

	if ( 'publish' === $service->post_status ) {
		return true;
	}

	$user_id = null === $user_id ? get_current_user_id() : $user_id;

	return $user_id > 0 && ( (int) $service->post_author === $user_id || user_can( $user_id, 'manage_options' ) );
}

/**
 * The limits that govern how much a vendor can put on one service.
 *
 * Single source of truth. These used to live inside ServiceWizard, which meant
 * they reached the web wizard as a template var and reached nothing else - an
 * app building a create-service screen had no way to learn max_gallery before
 * the server rejected the eighth image. Defining them here lets the wizard and
 * the REST route read the same array instead of drifting apart, which is the
 * failure this codebase keeps paying for.
 *
 * Free ships conservative numbers; Pro raises them through the filters below.
 * -1 means unlimited.
 *
 * @since 1.7.1
 *
 * @return array<string,int> Limit key => maximum, or -1 for unlimited.
 */
function wpss_get_service_limits(): array {
	return array(
		/**
		 * Max pricing packages (tiers).
		 *
		 * Free: 3 (Basic, Standard, Premium)
		 * Pro: 3 (same, but more flexibility)
		 *
		 * @param int $max Maximum packages.
		 */
		'max_packages'     => apply_filters( 'wpss_service_max_packages', 3 ),

		/**
		 * Max gallery images (additional, not including main).
		 *
		 * Free: 4
		 * Pro: Unlimited (-1)
		 *
		 * @param int $max Maximum gallery images. -1 for unlimited.
		 */
		'max_gallery'      => apply_filters( 'wpss_service_max_gallery', 4 ),

		/**
		 * Max video URLs.
		 *
		 * Free: 1
		 * Pro: 1 (not raised — Pro lifts gallery, extras, FAQ and requirements only)
		 *
		 * @param int $max Maximum videos.
		 */
		'max_videos'       => apply_filters( 'wpss_service_max_videos', 1 ),

		/**
		 * Max service extras (add-ons).
		 *
		 * Free: 3
		 * Pro: Unlimited (-1)
		 *
		 * @param int $max Maximum extras. -1 for unlimited.
		 */
		'max_extras'       => apply_filters( 'wpss_service_max_extras', 3 ),

		/**
		 * Max FAQs.
		 *
		 * Free: 5
		 * Pro: Unlimited (-1)
		 *
		 * @param int $max Maximum FAQs. -1 for unlimited.
		 */
		'max_faq'          => apply_filters( 'wpss_service_max_faq', 5 ),

		/**
		 * Max buyer requirements.
		 *
		 * Free: 5
		 * Pro: Unlimited (-1)
		 *
		 * @param int $max Maximum requirements. -1 for unlimited.
		 */
		'max_requirements' => apply_filters( 'wpss_service_max_requirements', 5 ),

		/**
		 * Max service tags.
		 *
		 * The save paths already cut the list to this many; it lives here so
		 * the wizard can warn the vendor before their extra tags are dropped,
		 * and so a site can raise the cap like any other limit.
		 *
		 * @since 1.7.2
		 *
		 * @param int $max Maximum tags.
		 */
		'max_tags'         => apply_filters( 'wpss_service_max_tags', 5 ),
	);
}

/**
 * The numeric floors a service must clear to go live.
 *
 * Exists so the wizard's client-side checklist and the server-side validator
 * cannot hold different numbers. The validator below reads these, and
 * ServiceWizard hands the same array to the browser - #10304336130 was filed
 * because the 120-character floor was written out as a literal in four places.
 *
 * @since 1.7.1
 *
 * @return array{title_length:int,description_length:int,min_price:float}
 */
function wpss_service_publish_thresholds(): array {
	return array(
		'title_length'       => 10,
		'description_length' => 120,
		'min_price'          => (float) apply_filters( 'wpss_min_service_price', 5 ),
	);
}

/**
 * The rules a service must satisfy before buyers can see it.
 *
 * The one place those rules live. The frontend wizard enforced six of them
 * server-side while the admin metabox enforced none, so a site owner could
 * publish straight from wp-admin what the wizard refused from the vendor
 * dashboard: no category, no main image, no delivery time, and a price under
 * the minimum. Verified on a live install - a $2.00 service with no category
 * and no image published and appeared on /services/, auto-approved, because
 * admin-created services bypass moderation too. See Basecamp 10289819803.
 *
 * Callers pass what they have; a key that is absent is not checked, so a
 * partial save path can validate the subset it owns.
 *
 * @since 1.7.1
 *
 * @param array<string,mixed> $service {
 *     The service fields to check.
 *
 *     @type string                         $title        Service title.
 *     @type array<int,int>                 $category_ids Assigned category term ids.
 *     @type string                         $description  Service description.
 *     @type array<int,array<string,mixed>> $packages     Package rows with price + delivery_days.
 *     @type int                            $thumbnail_id Main image attachment id.
 * }
 * @return string[] User-facing error sentences; empty when the service may go live.
 */
function wpss_validate_service_publishable( array $service ): array {
	$errors = array();

	$thresholds = wpss_service_publish_thresholds();

	if ( array_key_exists( 'title', $service ) ) {
		$title = trim( (string) $service['title'] );
		if ( '' === $title ) {
			$errors[] = __( 'Please enter a service title.', 'wp-sell-services' );
		} elseif ( mb_strlen( $title ) < $thresholds['title_length'] ) {
			$errors[] = sprintf(
				/* translators: %d: minimum number of characters. */
				__( 'Please enter at least %d characters for the service title.', 'wp-sell-services' ),
				$thresholds['title_length']
			);
		}
	}

	if ( array_key_exists( 'category_ids', $service ) && empty( $service['category_ids'] ) ) {
		$errors[] = __( 'Please select a category.', 'wp-sell-services' );
	}

	if ( array_key_exists( 'description', $service ) ) {
		$description = trim( wp_strip_all_tags( (string) $service['description'] ) );
		if ( mb_strlen( $description ) < $thresholds['description_length'] ) {
			$errors[] = sprintf(
				/* translators: %d: minimum number of characters. */
				__( 'Description must be at least %d characters.', 'wp-sell-services' ),
				$thresholds['description_length']
			);
		}
	}

	if ( array_key_exists( 'packages', $service ) ) {
		$packages = array_filter(
			(array) $service['packages'],
			static function ( $package ) {
				if ( ! is_array( $package ) ) {
					return false;
				}

				// A tier the vendor switched off is not part of the offer, so it
				// must not be validated. The wizard keeps placeholder objects for
				// Standard and Premium with their names already filled in and
				// their prices empty; without this guard the placeholder passed
				// the name test below, then won "cheapest" at price 0 and had its
				// own empty fields reported against Basic - which is why a
				// Basic-only service could not be published at all.
				//
				// Checked with array_key_exists so callers that never send the
				// key (the admin metabox) keep their previous behaviour.
				if ( array_key_exists( 'enabled', $package ) && ! $package['enabled'] ) {
					return false;
				}

				return ! empty( $package['name'] ) || ! empty( $package['price'] );
			}
		);

		if ( empty( $packages ) ) {
			$errors[] = __( 'Please add at least one package.', 'wp-sell-services' );
		} else {
			// The cheapest package is the price buyers see on the card, so it is
			// the one the floor applies to - and it is the wizard's Basic tier by
			// construction.
			$min_price = $thresholds['min_price'];
			$cheapest  = null;

			foreach ( $packages as $package ) {
				if ( null === $cheapest || (float) ( $package['price'] ?? 0 ) < (float) ( $cheapest['price'] ?? 0 ) ) {
					$cheapest = $package;
				}
			}

			if ( (float) ( $cheapest['price'] ?? 0 ) < $min_price ) {
				$errors[] = sprintf(
					/* translators: %s: formatted minimum price (e.g. $5.00). */
					__( 'Basic package price must be at least %s.', 'wp-sell-services' ),
					wpss_format_price( $min_price )
				);
			}

			/*
			 * Every enabled tier, not only the cheapest one.
			 *
			 * The delivery and name checks used to run against $cheapest alone,
			 * so an enabled Standard or Premium with a price but no delivery
			 * time - or no name - published without complaint, and the buyer was
			 * shown a purchasable tier with no date on it. The price floor stays
			 * on the cheapest deliberately: that is the lowest a buyer can pay,
			 * so checking the minimum covers every tier at once (Basecamp
			 * 10320551446).
			 */
			$position = 0;

			foreach ( $packages as $key => $package ) {
				++$position;
				$name = trim( (string) ( $package['name'] ?? '' ) );

				/*
				 * The wizard keys packages by tier ('basic', 'standard'), REST
				 * and the metabox key them numerically. Naming a tier "the 1
				 * package" helps nobody, so fall back to its position instead.
				 */
				$label = '' !== $name
					? $name
					: ( is_numeric( $key ) ? '' : ucfirst( (string) $key ) );

				if ( '' === $name ) {
					$errors[] = '' !== $label
						? sprintf(
							/* translators: %s: package tier name (e.g. Standard). */
							__( 'Please name the %s package.', 'wp-sell-services' ),
							$label
						)
						: sprintf(
							/* translators: %d: position of the package in the list, starting at 1. */
							__( 'Please name package %d.', 'wp-sell-services' ),
							$position
						);
				}

				if ( empty( $package['delivery_days'] ) ) {
					$errors[] = '' !== $label
						? sprintf(
							/* translators: %s: package name or tier (e.g. Standard). */
							__( 'Please set a delivery time for the %s package.', 'wp-sell-services' ),
							$label
						)
						: sprintf(
							/* translators: %d: position of the package in the list, starting at 1. */
							__( 'Please set a delivery time for package %d.', 'wp-sell-services' ),
							$position
						);
				}

				// Express is optional, but once priced it must be a real, faster
				// time - otherwise the buyer pays extra for nothing.
				if ( (float) ( $package['express_price'] ?? 0 ) > 0 && null === wpss_get_package_express( wpss_sanitize_package_express( $package ) + array( 'delivery_days' => (int) ( $package['delivery_days'] ?? 0 ) ) ) ) {
					$errors[] = '' !== $label
						? sprintf(
							/* translators: %s: package name or tier (e.g. Standard). */
							__( 'Express delivery on the %s package must be faster than its delivery time.', 'wp-sell-services' ),
							$label
						)
						: sprintf(
							/* translators: %d: position of the package in the list, starting at 1. */
							__( 'Express delivery on package %d must be faster than its delivery time.', 'wp-sell-services' ),
							$position
						);
				}
			}
		}
	}

	if ( array_key_exists( 'thumbnail_id', $service ) && empty( $service['thumbnail_id'] ) ) {
		$errors[] = __( 'Please upload a main image.', 'wp-sell-services' );
	}

	/**
	 * Filter the reasons a service may not go live.
	 *
	 * @since 1.7.1
	 *
	 * @param string[]            $errors  Error sentences.
	 * @param array<string,mixed> $service The validated input.
	 */
	return apply_filters( 'wpss_service_publish_errors', $errors, $service );
}

/**
 * Move a newly written service to the status it was meant to have.
 *
 * Every creator must insert the post as a draft, write its packages, gallery,
 * terms and thumbnail, and only then call this. Inserting straight as
 * 'publish' ran the publish rules (ServiceMetabox::enforce_publish_rules) on a
 * post with no meta yet, which demoted it to draft, and nothing published it
 * again - the wizard said "published" while the service stayed a draft
 * (Basecamp 10350940481, 10350889943).
 *
 * @since 1.8.0
 *
 * @param int    $service_id Service post ID.
 * @param string $status     Intended status: publish, pending or draft.
 * @return string The status the service actually has afterwards.
 */
function wpss_settle_service_status( int $service_id, string $status ): string {
	if ( get_post_status( $service_id ) !== $status ) {
		wp_update_post(
			array(
				'ID'          => $service_id,
				'post_status' => $status,
			)
		);
		clean_post_cache( $service_id );
	}

	return (string) get_post_status( $service_id );
}

/**
 * What stands between a saved service and going live, read from the post.
 *
 * The same check as {@see wpss_validate_service_publishable()} for a service
 * that already exists, so every "make it live" path - the editor, moderation
 * approval, REST - asks one question and gets one answer.
 *
 * @since 1.8.0
 *
 * @param int $service_id Service post ID.
 * @return string[] User-facing error sentences; empty when the service may go live.
 */
function wpss_get_service_publish_errors( int $service_id ): array {
	$post = get_post( $service_id );

	if ( ! $post ) {
		return array( __( 'Service not found.', 'wp-sell-services' ) );
	}

	return wpss_validate_service_publishable(
		array(
			'title'        => $post->post_title,
			'category_ids' => wp_get_post_terms( $post->ID, 'wpss_service_category', array( 'fields' => 'ids' ) ),
			'description'  => $post->post_content,
			'packages'     => (array) get_post_meta( $post->ID, '_wpss_packages', true ),
			'thumbnail_id' => get_post_thumbnail_id( $post->ID ),
		)
	);
}

/**
 * Truncate a service's lists to wpss_get_service_limits().
 *
 * The one enforcer for every save path (wizard, REST, admin metabox,
 * ServiceManager). Pass whichever of the keyed lists the caller has; each is
 * cut to its cap and reported back so the caller can tell the user.
 *
 * @since 1.7.1
 *
 * @param array<string,mixed> $meta Any of packages, gallery, extras, faqs, requirements => list.
 * @return array{meta: array<string,mixed>, truncated: array<string,string>} The capped lists and, per cut key, a user-facing sentence.
 */
function wpss_enforce_service_limits( array $meta ): array {
	$limits = wpss_get_service_limits();
	$rules  = array(
		'packages'     => array( 'max_packages', __( 'packages', 'wp-sell-services' ) ),
		'gallery'      => array( 'max_gallery', __( 'additional gallery images', 'wp-sell-services' ) ),
		'extras'       => array( 'max_extras', __( 'extras', 'wp-sell-services' ) ),
		'faqs'         => array( 'max_faq', __( 'FAQs', 'wp-sell-services' ) ),
		'requirements' => array( 'max_requirements', __( 'requirements', 'wp-sell-services' ) ),
		// The wizard enforces the tag cap and REST did not, so the same service
		// could carry more tags depending on which surface created it.
		'tags'         => array( 'max_tags', __( 'tags', 'wp-sell-services' ) ),
	);

	$truncated = array();

	/*
	 * The gallery is keyed on the attachment id wherever it is rendered - most
	 * sharply in the wizard, whose x-for keys on image.id, where a repeated id
	 * breaks Alpine's reconciliation outright. A duplicate saved through REST
	 * therefore did not show up until the vendor next opened their own service
	 * to edit it, and then broke that screen. Collapse repeats at the single
	 * point every save path passes through, before the cap is applied, so the
	 * cap counts distinct images.
	 */
	if ( ! empty( $meta['gallery'] ) && is_array( $meta['gallery'] ) ) {
		$unique_gallery = array_values( array_unique( array_map( 'absint', $meta['gallery'] ) ) );

		if ( count( $unique_gallery ) !== count( $meta['gallery'] ) ) {
			$truncated['gallery_duplicates'] = __( 'The same image was listed more than once; the repeats were not saved.', 'wp-sell-services' );
		}

		$meta['gallery'] = $unique_gallery;
	}

	foreach ( $rules as $key => [ $limit_key, $label ] ) {
		$max = (int) ( $limits[ $limit_key ] ?? -1 );
		// Every save path stores the main image as the first gallery entry and
		// max_gallery counts the additional ones, so the list may hold one more.
		$cap = 'gallery' === $key ? $max + 1 : $max;

		if ( $max < 0 || empty( $meta[ $key ] ) || ! is_array( $meta[ $key ] ) || count( $meta[ $key ] ) <= $cap ) {
			continue;
		}

		$meta[ $key ]      = array_slice( $meta[ $key ], 0, $cap, true );
		$truncated[ $key ] = sprintf(
			/* translators: 1: maximum count, 2: list name (packages, gallery images, extras, FAQs, requirements) */
			__( 'A service can have at most %1$d %2$s; the extra entries were not saved.', 'wp-sell-services' ),
			$max,
			$label
		);
	}

	return array(
		'meta'      => $meta,
		'truncated' => $truncated,
	);
}

/**
 * Set or clear a service's Featured flag.
 *
 * The one writer: the service metabox and the list table's bulk action both come here, so the
 * `wpss_service_is_featured` filter sees every change. The caller checks wpss_user_can_feature_service().
 *
 * @since 1.9.0
 *
 * @param int  $service_id Service post ID.
 * @param bool $featured   Whether the service should be featured.
 * @return void
 */
function wpss_set_service_featured( int $service_id, bool $featured ): void {
	/**
	 * Filter whether a service is featured as it saves.
	 *
	 * The owner's choice is the default. A site can force the flag from its own rule - a vendor tier, a
	 * paid placement, a campaign window - without having to intercept save_post.
	 *
	 * @since 1.7.1
	 *
	 * @param bool $featured   Whether the service should be featured.
	 * @param int  $service_id Service post ID.
	 */
	$featured = (bool) apply_filters( 'wpss_service_is_featured', $featured, $service_id );

	if ( $featured ) {
		update_post_meta( $service_id, '_wpss_featured', 1 );
		return;
	}

	// Deleted rather than stored as 0: both readers compare the value to '1', and a row for every
	// service anyone ever unticked is meta nobody reads.
	delete_post_meta( $service_id, '_wpss_featured' );
}

/**
 * Whether the current user may set a service's Featured flag.
 *
 * Featured is marketplace curation, not service authoring: `_wpss_featured` is
 * what the Featured Services block and `[wpss_featured_services]` select on, so
 * it decides who appears in the promoted slot on the marketplace's front page.
 * `edit_post` is therefore the wrong gate - every vendor holds it on their own
 * service, which would let any vendor promote themselves above everyone else.
 * The owner's capability is the default; a site running paid placement or a
 * vendor tier lowers it through the filter rather than by editing the metabox.
 *
 * @since 1.7.1
 *
 * @param int $service_id Service post ID.
 * @return bool
 */
function wpss_user_can_feature_service( int $service_id = 0 ): bool {
	$can = current_user_can( 'manage_options' );

	/**
	 * Filter who may mark a service as Featured.
	 *
	 * @since 1.7.1
	 *
	 * @param bool $can        Whether the current user may set the flag.
	 * @param int  $service_id Service post ID.
	 */
	return (bool) apply_filters( 'wpss_user_can_feature_service', $can, $service_id );
}

/**
 * Why a service cannot be bought right now, or '' when it can.
 *
 * The ONE place either rail asks "is this still purchasable". The standalone
 * cart reads it through wpss_get_user_cart(); the WooCommerce cart reads it
 * through the Pro adapter's cart re-check. Two copies of this rule is how the
 * two rails drift, and the drift already happened once: the standalone cart was
 * fixed on 2026-09-23 and WooCommerce - the rail most sites actually run - kept
 * selling unpublished services for another afternoon, because "the cart" meant
 * a different thing there and nobody asked the question twice.
 *
 * @since 1.7.2
 *
 * @param  int $service_id Service post ID.
 * @return string Empty when purchasable; otherwise a sentence for the buyer.
 */
function wpss_service_unavailable_reason( int $service_id ): string {
	$service = $service_id ? get_post( $service_id ) : null;

	if ( ! $service || 'wpss_service' !== $service->post_type ) {
		return __( 'This service is no longer offered.', 'wp-sell-services' );
	}

	if ( 'publish' !== $service->post_status ) {
		return __( 'This service is not currently available.', 'wp-sell-services' );
	}

	// Paused: still listed, not sold. The cart, the Woo rail and the order
	// provider all ask here; only the standalone pricer checked it before
	// (Basecamp 10375174747). Same words as CheckoutIntentService's refusal.
	if ( 'paused' === wpss_get_service_status( $service_id ) ) {
		return __( 'This service is not taking new orders right now.', 'wp-sell-services' );
	}

	// Seller away: the service page says so and disables its button, but a
	// line already in the cart, a saved checkout link or a REST call still
	// sold it (Basecamp 10379595852).
	$seller = \WPSellServices\Models\VendorProfile::get_by_user_id( (int) $service->post_author );

	if ( $seller && $seller->is_on_vacation() ) {
		return __( 'This seller is away and not taking new orders right now.', 'wp-sell-services' );
	}

	/**
	 * Let an integration refuse a service for its own reason.
	 *
	 * @since 1.7.2
	 *
	 * @param string $reason     Empty when purchasable.
	 * @param int    $service_id Service post ID.
	 */
	return (string) apply_filters( 'wpss_service_unavailable_reason', '', $service_id );
}

/**
 * A service's rating: the average and count of its approved reviews.
 *
 * The one read for the service page header, the reviews block, checkout and
 * the service card. They used to read three ways - stored meta in the header,
 * a live query in the reviews block, a repository summary at checkout - and
 * showed "4.8 (4)" above "4.7 / 3 reviews" once the stored figure went stale
 * (Basecamp 10337201764). It stays a stored number because the "top rated"
 * sort and every card in a grid need it without a query each.
 *
 * @since 1.8.0
 *
 * @param int $service_id Service ID.
 * @return array{average: float, count: int} Average to one decimal, and count.
 */
function wpss_get_service_rating( int $service_id ): array {
	$count = (int) get_post_meta( $service_id, '_wpss_rating_count', true );

	return array(
		'average' => $count > 0 ? round( (float) get_post_meta( $service_id, '_wpss_rating_average', true ), 1 ) : 0.0,
		'count'   => $count,
	);
}

/**
 * Recount a service's rating from its approved reviews.
 *
 * The only writer of the stored rating; every review create, moderation and
 * delete calls it.
 *
 * @since 1.8.0
 *
 * @param int $service_id Service ID.
 * @return array{average: float, count: int} The stored rating.
 */
function wpss_recount_service_rating( int $service_id ): array {
	$stats = ( new \WPSellServices\Database\Repositories\ReviewRepository() )->rating_stats( 'service_id', $service_id );
	$count = $stats['count'];

	update_post_meta( $service_id, '_wpss_rating_average', $count > 0 ? round( $stats['average'], 2 ) : 0 );
	update_post_meta( $service_id, '_wpss_rating_count', $count );
	update_post_meta( $service_id, '_wpss_review_count', $count );

	return wpss_get_service_rating( $service_id );
}

/**
 * Recount a service's completed orders into _wpss_order_count.
 *
 * The stored count feeds the admin list, the editor stats, the service page,
 * schema markup and the "most orders" sort, but its only writer
 * (ServiceManager::increment_order_count()) was never called, so every real
 * sale read 0 (Basecamp 10337190248). It is now recounted from the orders
 * table whenever one of the service's orders changes status - the count
 * stays a stored number because the popularity sort needs one.
 *
 * @since 1.8.0
 *
 * @param int $service_id Service ID.
 * @return int The count.
 */
function wpss_sync_service_order_count( int $service_id ): int {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$count = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}wpss_orders WHERE service_id = %d AND status = 'completed' AND COALESCE( platform, '' ) NOT IN ( 'tip', 'extension', 'milestone' )",
			$service_id
		)
	);

	update_post_meta( $service_id, '_wpss_order_count', $count );

	return $count;
}

add_action(
	'wpss_order_status_changed',
	static function ( $order_id ) {
		$order = wpss_get_order( (int) $order_id );

		if ( $order && (int) $order->service_id > 0 ) {
			wpss_sync_service_order_count( (int) $order->service_id );
		}

		// A seller's level follows completed orders now, not a week later.
		if ( $order && 'completed' === $order->status && (int) $order->vendor_id > 0 ) {
			( new \WPSellServices\Services\SellerLevelService() )->recalculate( (int) $order->vendor_id );
		}
	},
	30
);

// ...and follows the rating as soon as a review lands.
add_action(
	'wpss_review_created',
	static function ( $review_id, $order_id ) {
		$order = wpss_get_order( (int) $order_id );

		if ( $order && (int) $order->vendor_id > 0 ) {
			( new \WPSellServices\Services\SellerLevelService() )->recalculate( (int) $order->vendor_id );
		}
	},
	30,
	2
);

/**
 * Whether the member may put this service live from a dashboard control.
 *
 * One gate for every door that flips a service to publish (dashboard publish,
 * bulk publish): marketplace standing first, then the plan limit when the
 * service is not already counted, then the publish bar. Pausing and deleting are
 * not gated, so a suspended seller can still take listings down.
 *
 * @since 1.9.0
 *
 * @param int $service_id Service post ID.
 * @param int $user_id    Member ID. Defaults to the current user.
 * @return string Refusal sentence, or '' when the service may go live.
 */
function wpss_service_publish_refusal( int $service_id, int $user_id = 0 ): string {
	$user_id = $user_id > 0 ? $user_id : get_current_user_id();

	$block = wpss_vendor_status_block( $user_id );
	if ( $block ) {
		return $block->get_error_message();
	}

	if ( 'publish' === get_post_status( $service_id ) ) {
		return '';
	}

	if ( ! user_can( $user_id, 'manage_options' ) && ! apply_filters( 'wpss_vendor_can_create_service', true, $user_id ) ) {
		/** This filter is documented in src/Frontend/ServiceWizard.php */
		return wp_strip_all_tags(
			(string) apply_filters(
				'wpss_service_limit_error_message',
				__( 'You have reached the maximum number of services allowed. Please remove an existing service before creating a new one.', 'wp-sell-services' )
			)
		);
	}

	$errors = wpss_validate_service_publishable(
		array(
			'title'       => get_the_title( $service_id ),
			'description' => (string) get_post_field( 'post_content', $service_id ),
			'packages'    => (array) get_post_meta( $service_id, '_wpss_packages', true ),
		)
	);

	return $errors ? implode( ' ', $errors ) : '';
}
