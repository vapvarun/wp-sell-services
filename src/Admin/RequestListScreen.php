<?php
/**
 * All Requests list screen: budget, status, proposals and deadline.
 *
 * @package WPSellServices\Admin
 * @since   1.8.0
 */

declare(strict_types=1);

namespace WPSellServices\Admin;

defined( 'ABSPATH' ) || exit;

use WPSellServices\Models\BuyerRequest;
use WPSellServices\PostTypes\BuyerRequestPostType;
use WPSellServices\Services\BuyerRequestService;

/**
 * The list showed title, author, categories and date, so an owner could not
 * tell which of 171 requests were still taking proposals (Basecamp
 * 10337190248). "Open" here is BuyerRequestService::open_meta_query(), the
 * same definition the storefront archive uses, so the filtered count matches
 * its "N requests found". Proposal counts are primed for the page by the
 * the_posts filter; the rest is post meta the list query already loads.
 */
class RequestListScreen {

	/**
	 * Status filter value for "open and not past its expiry".
	 */
	private const FILTER_OPEN = 'open';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'manage_' . BuyerRequestPostType::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . BuyerRequestPostType::POST_TYPE . '_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
		add_filter( 'default_hidden_columns', array( $this, 'hidden_columns' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( $this, 'filters' ) );
		add_action( 'pre_get_posts', array( $this, 'apply_filters' ) );
	}

	/**
	 * Add the columns after the title.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function columns( array $columns ): array {
		$out = array();

		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;

			if ( 'title' === $key ) {
				$out['wpss_request_status'] = __( 'Status', 'wp-sell-services' );
				$out['wpss_budget']         = __( 'Budget', 'wp-sell-services' );
				$out['wpss_proposals']      = __( 'Proposals', 'wp-sell-services' );
				$out['wpss_deadline']       = __( 'Deadline', 'wp-sell-services' );
			}
		}

		if ( isset( $out['author'] ) ) {
			$out['author'] = __( 'Buyer', 'wp-sell-services' );
		}

		return $out;
	}

	/**
	 * Categories are rarely set on requests; hide the column until asked for.
	 *
	 * @param string[]   $hidden Hidden column keys.
	 * @param \WP_Screen $screen Current screen.
	 * @return string[]
	 */
	public function hidden_columns( array $hidden, \WP_Screen $screen ): array {
		if ( 'edit-' . BuyerRequestPostType::POST_TYPE === $screen->id ) {
			$hidden[] = 'taxonomy-wpss_service_category';
		}

		return $hidden;
	}

	/**
	 * Render one of our columns.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Request ID.
	 * @return void
	 */
	public function render_column( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'wpss_request_status':
				$status = self::effective_status( $post_id );
				$labels = BuyerRequestService::get_statuses();
				printf(
					'<span class="%s">%s</span>',
					esc_attr( wpss_status_class( $status ) ),
					esc_html( $labels[ $status ] ?? ucfirst( $status ) )
				);
				break;

			case 'wpss_budget':
				$post = get_post( $post_id );
				echo $post ? esc_html( BuyerRequest::from_post( $post )->get_budget_display() ) : '&mdash;';
				break;

			case 'wpss_proposals':
				echo esc_html( number_format_i18n( ( new BuyerRequestService() )->get_proposal_count( $post_id ) ) );
				break;

			case 'wpss_deadline':
				$deadline = (string) get_post_meta( $post_id, '_wpss_deadline', true );
				echo $deadline ? esc_html( wp_date( get_option( 'date_format' ), strtotime( $deadline ) ) ) : '&mdash;';
				break;
		}
	}

	/**
	 * A request still marked open past its expiry reads Expired, as the
	 * storefront treats it, even before the expiry sweep has run.
	 *
	 * @param int $post_id Request ID.
	 * @return string Status slug.
	 */
	private static function effective_status( int $post_id ): string {
		$status  = (string) get_post_meta( $post_id, '_wpss_status', true );
		$status  = '' !== $status ? $status : BuyerRequestService::STATUS_OPEN;
		$expires = (string) get_post_meta( $post_id, '_wpss_expires_at', true );

		if ( BuyerRequestService::STATUS_OPEN === $status && '' !== $expires && $expires <= current_time( 'mysql' ) ) {
			return BuyerRequestService::STATUS_EXPIRED;
		}

		return $status;
	}

	/**
	 * Status dropdown above the list.
	 *
	 * @param string $post_type Post type of the screen.
	 * @return void
	 */
	public function filters( string $post_type ): void {
		if ( BuyerRequestPostType::POST_TYPE !== $post_type ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
		$current = isset( $_GET['wpss_request_status'] ) ? sanitize_key( wp_unslash( $_GET['wpss_request_status'] ) ) : '';
		$options = array( '' => __( 'All statuses', 'wp-sell-services' ) )
			+ array( self::FILTER_OPEN => __( 'Open (taking proposals)', 'wp-sell-services' ) )
			+ array_diff_key( BuyerRequestService::get_statuses(), array( BuyerRequestService::STATUS_OPEN => true ) );
		?>
		<label for="wpss-filter-request-status" class="screen-reader-text"><?php esc_html_e( 'Filter by request status', 'wp-sell-services' ); ?></label>
		<select name="wpss_request_status" id="wpss-filter-request-status">
			<?php foreach ( $options as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, $value ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Apply the status filter to the list query.
	 *
	 * @param \WP_Query $query Query.
	 * @return void
	 */
	public function apply_filters( \WP_Query $query ): void {
		global $pagenow;

		if ( ! is_admin() || 'edit.php' !== $pagenow || ! $query->is_main_query() || BuyerRequestPostType::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
		$status = isset( $_GET['wpss_request_status'] ) ? sanitize_key( wp_unslash( $_GET['wpss_request_status'] ) ) : '';

		if ( self::FILTER_OPEN === $status ) {
			// The storefront lists published open requests only.
			$query->set( 'post_status', 'publish' );
			$query->set( 'meta_query', BuyerRequestService::open_meta_query() ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		} elseif ( isset( BuyerRequestService::get_statuses()[ $status ] ) ) {
			$query->set(
				'meta_query',
				array(
					array(
						'key'   => '_wpss_status',
						'value' => $status,
					),
				)
			); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}
	}
}
