<?php
/**
 * One buyer answer, with its expand/collapse control.
 *
 * Two places render a submitted requirement answer: the question the vendor
 * configured, and an "orphan" answer whose question has since been removed from
 * the service. They were written separately, and only the first grew a
 * text-content wrapper and a Show more button. The orphan branch still applied
 * the --collapsed class, so a long answer was clipped with no control anywhere
 * to open it - the buyer's brief was simply unreadable past the fold
 * (Basecamp 10313470306).
 *
 * Both now include this file, so the markup the CSS and frontend.js expect
 * cannot exist on one path and not the other.
 *
 * Expects:
 *   string $wpss_answer_text  The answer, already unslashed, not yet escaped.
 *
 * @package WPSellServices
 * @since   1.7.3
 */

defined( 'ABSPATH' ) || exit;

$wpss_answer_text = isset( $wpss_answer_text ) ? (string) $wpss_answer_text : '';

/**
 * Length past which an answer is collapsed behind a Show more control.
 *
 * @since 1.7.3
 *
 * @param int    $threshold Character count. Default 300.
 * @param string $answer    The answer being rendered.
 */
$wpss_answer_long = strlen( $wpss_answer_text ) > (int) apply_filters( 'wpss_requirement_answer_collapse_at', 300, $wpss_answer_text );
?>
<div class="wpss-requirement-view__text-content">
	<?php echo wp_kses_post( wpautop( $wpss_answer_text ) ); ?>
</div>
<?php if ( $wpss_answer_long ) : ?>
	<button type="button" class="wpss-requirement-view__expand-btn" aria-expanded="false">
		<span class="wpss-expand-text"><?php esc_html_e( 'Show more', 'wp-sell-services' ); ?></span>
		<span class="wpss-collapse-text" style="display:none;"><?php esc_html_e( 'Show less', 'wp-sell-services' ); ?></span>
		<i data-lucide="chevron-down" class="wpss-icon wpss-expand-icon" aria-hidden="true"></i>
	</button>
<?php endif; ?>
