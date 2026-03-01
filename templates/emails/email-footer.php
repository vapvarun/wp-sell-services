<?php
/**
 * Email Footer Template
 *
 * Branded footer for standalone email system.
 * This template can be overridden in your theme:
 * yourtheme/wp-sell-services/emails/email-footer.php
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.0.0
 *
 * @var string $footer_text Footer text.
 * @var string $base_color  Primary brand color.
 * @var string $bg_color    Background color.
 * @var string $text_color  Text color.
 * @var string $site_name   Site name.
 * @var string $site_url    Site URL.
 */

defined( 'ABSPATH' ) || exit;

$footer_text = $footer_text ?? sprintf(
	/* translators: %1$s: year, %2$s: platform name */
	__( '© %1$s %2$s. All rights reserved.', 'wp-sell-services' ),
	gmdate( 'Y' ),
	wpss_get_platform_name()
);
?>
										<?php
										/**
										 * Fires before the email footer.
										 *
										 * Allows adding custom content before the email footer.
										 *
										 * @since 1.0.0
										 */
										do_action( 'wpss_email_footer' );
										?>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<!-- Footer -->
					<tr>
						<td align="center" style="padding: 30px 20px;">
							<p style="margin: 0 0 10px 0; font-size: 14px; color: #666666; line-height: 1.5;">
								<?php echo wp_kses_post( $footer_text ); ?>
							</p>
							<p style="margin: 0; font-size: 13px; color: #999999;">
								<?php
								printf(
									/* translators: %s: site name with link */
									esc_html__( 'This email was sent by %s', 'wp-sell-services' ),
									'<a href="' . esc_url( $site_url ?? home_url() ) . '" style="color: #999999;">' . esc_html( $site_name ?? get_bloginfo( 'name' ) ) . '</a>'
								);
								?>
							</p>
							<p style="margin: 10px 0 0 0; font-size: 12px; color: #bbbbbb;">
								<a href="<?php echo esc_url( $site_url ?? home_url() ); ?>" style="color: #bbbbbb; text-decoration: none;">
									<?php esc_html_e( 'Visit our website', 'wp-sell-services' ); ?>
								</a>
								&nbsp;|&nbsp;
								<a href="<?php echo esc_url( wpss_get_page_url( 'dashboard' ) ); ?>" style="color: #bbbbbb; text-decoration: none;">
									<?php esc_html_e( 'My Account', 'wp-sell-services' ); ?>
								</a>
							</p>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>
