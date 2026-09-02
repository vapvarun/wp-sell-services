<?php
/**
 * Dashboard partial: order list filters (status chips + order-number search).
 *
 * Shared by the buyer orders list and the vendor sales list so both sides
 * offer the same filter from the same status groups. Query args are
 * namespaced by $filter_prefix: {prefix}_status, {prefix}_search, {prefix}_page.
 *
 * @package WPSellServices\Templates
 * @since   1.7.1
 *
 * @var string                                                   $filter_prefix Query-arg prefix: 'orders' or 'sales'.
 * @var array<string, array{label: string, statuses: string[]}> $status_groups Status groups, incl. any runtime 'other' group.
 * @var array<string, int>                                       $status_counts Status => count for this user.
 * @var string                                                   $filter_group  Active group key.
 * @var string                                                   $filter_search Active search term.
 */

defined( 'ABSPATH' ) || exit;

$status_param = $filter_prefix . '_status';
$search_param = $filter_prefix . '_search';
$page_param   = $filter_prefix . '_page';

// Any filter change returns to page 1; keeping the old page number can land
// the user on an empty page of a much shorter list. add_query_arg() with no
// URL builds from the CURRENT request, so the other filters survive.
$chip_url = static function ( string $group ) use ( $status_param, $page_param ): string {
	$url = 'all' === $group ? remove_query_arg( $status_param ) : add_query_arg( $status_param, $group );

	return remove_query_arg( $page_param, $url );
};
?>
<div class="wpss-order-filters-bar">
	<nav class="wpss-order-filters" aria-label="<?php esc_attr_e( 'Filter orders by status', 'wp-sell-services' ); ?>">
		<?php
		// A chip with nothing in it is not rendered - someone who has never had
		// a dispute should not be shown a "Needs attention 0" tab. `all` always renders.
		foreach ( $status_groups as $group_key => $group ) :
			if ( 'all' === $group_key ) {
				$group_count = array_sum( $status_counts );
			} else {
				$group_count = 0;
				foreach ( $group['statuses'] as $group_status ) {
					$group_count += (int) ( $status_counts[ $group_status ] ?? 0 );
				}

				if ( $group_count < 1 ) {
					continue;
				}
			}

			$is_current = ( $group_key === $filter_group );
			?>
			<a href="<?php echo esc_url( $chip_url( $group_key ) ); ?>"
				class="wpss-order-filter<?php echo $is_current ? ' is-active' : ''; ?>"
				<?php echo $is_current ? 'aria-current="page"' : ''; ?>>
				<?php echo esc_html( $group['label'] ); ?>
				<span class="wpss-order-filter__count"><?php echo esc_html( number_format_i18n( $group_count ) ); ?></span>
			</a>
		<?php endforeach; ?>
	</nav>

	<form method="get" class="wpss-order-search" role="search">
		<?php
		// A GET form replaces the whole query string, so every other current
		// parameter (section on plain permalinks, the sales period, the active
		// chip) rides along as a hidden field. The page number is dropped so a
		// new search starts on page 1.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display filters.
		foreach ( wp_unslash( $_GET ) as $param_key => $param_value ) {
			$param_key = sanitize_key( (string) $param_key );

			if ( ! is_string( $param_value ) || '' === $param_value || in_array( $param_key, array( $search_param, $page_param ), true ) ) {
				continue;
			}

			printf( '<input type="hidden" name="%s" value="%s">', esc_attr( $param_key ), esc_attr( sanitize_text_field( $param_value ) ) );
		}
		?>
		<label for="wpss-<?php echo esc_attr( $filter_prefix ); ?>-search" class="screen-reader-text"><?php esc_html_e( 'Search by order number', 'wp-sell-services' ); ?></label>
		<input type="search" id="wpss-<?php echo esc_attr( $filter_prefix ); ?>-search" name="<?php echo esc_attr( $search_param ); ?>" class="wpss-input" value="<?php echo esc_attr( $filter_search ); ?>" placeholder="<?php esc_attr_e( 'Order number', 'wp-sell-services' ); ?>">
		<button type="submit" class="wpss-btn wpss-btn--outline wpss-btn--sm">
			<i data-lucide="search" class="wpss-icon" aria-hidden="true"></i>
			<?php esc_html_e( 'Search', 'wp-sell-services' ); ?>
		</button>
		<?php if ( '' !== $filter_search ) : ?>
			<a href="<?php echo esc_url( remove_query_arg( array( $search_param, $page_param ) ) ); ?>" class="wpss-btn wpss-btn--ghost wpss-btn--sm">
				<?php esc_html_e( 'Clear', 'wp-sell-services' ); ?>
			</a>
		<?php endif; ?>
	</form>
</div>
