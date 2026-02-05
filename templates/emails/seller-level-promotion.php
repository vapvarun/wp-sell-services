<?php
/**
 * Seller Level Promotion Email (HTML)
 *
 * Sent to vendor when they are promoted to a higher level.
 * This template can be overridden in your theme:
 * yourtheme/wp-sell-services/emails/seller-level-promotion.php
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.0.0
 *
 * @var WP_User $recipient        Recipient user object.
 * @var string  $email_heading    Email heading.
 * @var string  $new_level        New level code.
 * @var string  $new_level_label  New level display name.
 * @var string  $old_level        Previous level code.
 * @var string  $old_level_label  Previous level display name.
 * @var string  $base_color       Brand color.
 */

defined( 'ABSPATH' ) || exit;

$base_color = $base_color ?? '#7f54b3';

// Level badge colors.
$level_colors = array(
	'new'       => '#6c757d',
	'level_1'   => '#28a745',
	'level_2'   => '#007bff',
	'top_rated' => '#ffc107',
);

$badge_color = $level_colors[ $new_level ] ?? $base_color;
?>

<div style="text-align: center; margin-bottom: 30px;">
	<div style="display: inline-block; font-size: 60px; margin-bottom: 10px;">
		<?php echo 'top_rated' === $new_level ? '&#127942;' : '&#127881;'; ?>
	</div>
</div>

<p style="margin: 0 0 16px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php
	printf(
		/* translators: %s: recipient name */
		esc_html__( 'Hi %s,', 'wp-sell-services' ),
		esc_html( $recipient->display_name )
	);
	?>
</p>

<p style="margin: 0 0 20px 0; font-size: 18px; color: #3c3c3c; line-height: 1.6;">
	<?php esc_html_e( 'Great news! Your hard work and dedication have paid off.', 'wp-sell-services' ); ?>
</p>

<div style="background: linear-gradient(135deg, <?php echo esc_attr( $base_color ); ?>15, <?php echo esc_attr( $badge_color ); ?>20); border-radius: 8px; padding: 30px; text-align: center; margin: 30px 0;">
	<p style="margin: 0 0 10px 0; font-size: 14px; color: #666; text-transform: uppercase; letter-spacing: 1px;">
		<?php esc_html_e( 'You\'ve been promoted to', 'wp-sell-services' ); ?>
	</p>
	<div style="display: inline-block; background-color: <?php echo esc_attr( $badge_color ); ?>; color: <?php echo 'top_rated' === $new_level ? '#333' : '#fff'; ?>; padding: 12px 30px; border-radius: 50px; font-size: 22px; font-weight: bold; margin: 10px 0;">
		<?php echo esc_html( $new_level_label ); ?>
	</div>
	<p style="margin: 15px 0 0 0; font-size: 13px; color: #888;">
		<?php
		printf(
			/* translators: %s: previous level */
			esc_html__( 'Previously: %s', 'wp-sell-services' ),
			esc_html( $old_level_label )
		);
		?>
	</p>
</div>

<p style="margin: 0 0 20px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php esc_html_e( 'This promotion recognizes your excellent performance on our platform. Keep up the great work!', 'wp-sell-services' ); ?>
</p>

<h3 style="margin: 30px 0 15px 0; font-size: 18px; color: #3c3c3c;">
	<?php esc_html_e( 'What This Means For You:', 'wp-sell-services' ); ?>
</h3>

<ul style="margin: 0 0 20px 0; padding: 0 0 0 20px; color: #3c3c3c; line-height: 1.8;">
	<?php if ( 'level_1' === $new_level || 'level_2' === $new_level || 'top_rated' === $new_level ) : ?>
		<li><?php esc_html_e( 'Enhanced visibility in search results', 'wp-sell-services' ); ?></li>
		<li><?php esc_html_e( 'Trusted seller badge on your profile', 'wp-sell-services' ); ?></li>
	<?php endif; ?>
	<?php if ( 'level_2' === $new_level || 'top_rated' === $new_level ) : ?>
		<li><?php esc_html_e( 'Priority customer support', 'wp-sell-services' ); ?></li>
		<li><?php esc_html_e( 'Featured placement opportunities', 'wp-sell-services' ); ?></li>
	<?php endif; ?>
	<?php if ( 'top_rated' === $new_level ) : ?>
		<li><?php esc_html_e( 'Top Rated badge - highest level of trust', 'wp-sell-services' ); ?></li>
		<li><?php esc_html_e( 'Early access to new features', 'wp-sell-services' ); ?></li>
		<li><?php esc_html_e( 'Exclusive promotions and opportunities', 'wp-sell-services' ); ?></li>
	<?php endif; ?>
</ul>

<p style="text-align: center; margin: 30px 0;">
	<a href="<?php echo esc_url( wpss_get_page_url( 'dashboard' ) ); ?>" class="button" style="display: inline-block; background-color: <?php echo esc_attr( $base_color ); ?>; color: #ffffff; padding: 14px 28px; text-decoration: none; border-radius: 4px; font-weight: 600; font-size: 16px;">
		<?php esc_html_e( 'View My Dashboard', 'wp-sell-services' ); ?>
	</a>
</p>

<p style="margin: 20px 0 0 0; font-size: 14px; color: #666666; line-height: 1.6; text-align: center;">
	<?php esc_html_e( 'Thank you for being a valued member of our marketplace!', 'wp-sell-services' ); ?>
</p>
