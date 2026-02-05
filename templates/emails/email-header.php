<?php
/**
 * Email Header Template
 *
 * Branded header for standalone email system.
 * This template can be overridden in your theme:
 * yourtheme/wp-sell-services/emails/email-header.php
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.0.0
 *
 * @var string $email_heading Email heading text.
 * @var string $header_image  Header image URL (optional).
 * @var string $base_color    Primary brand color.
 * @var string $bg_color      Background color.
 * @var string $body_color    Body background color.
 * @var string $text_color    Text color.
 * @var string $site_name     Site name.
 * @var string $site_url      Site URL.
 */

defined( 'ABSPATH' ) || exit;

$base_color = $base_color ?? '#7f54b3';
$bg_color   = $bg_color ?? '#f7f7f7';
$body_color = $body_color ?? '#ffffff';
$text_color = $text_color ?? '#3c3c3c';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( get_bloginfo( 'name' ) ); ?></title>
	<style type="text/css">
		/* Reset styles */
		body, table, td, p, a, li, blockquote {
			-webkit-text-size-adjust: 100%;
			-ms-text-size-adjust: 100%;
		}
		table, td {
			mso-table-lspace: 0pt;
			mso-table-rspace: 0pt;
		}
		img {
			-ms-interpolation-mode: bicubic;
			border: 0;
			outline: none;
			text-decoration: none;
		}
		body {
			margin: 0;
			padding: 0;
			width: 100%;
			height: 100%;
			background-color: <?php echo esc_attr( $bg_color ); ?>;
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
		}
		/* Links */
		a {
			color: <?php echo esc_attr( $base_color ); ?>;
			text-decoration: none;
		}
		a:hover {
			text-decoration: underline;
		}
		/* Button */
		.button {
			display: inline-block;
			background-color: <?php echo esc_attr( $base_color ); ?>;
			color: #ffffff !important;
			padding: 12px 24px;
			text-decoration: none;
			border-radius: 4px;
			font-weight: 600;
			margin: 10px 0;
		}
		.button:hover {
			opacity: 0.9;
			text-decoration: none;
		}
		/* Table styles */
		.email-table {
			width: 100%;
			border-collapse: collapse;
			margin-bottom: 20px;
		}
		.email-table th,
		.email-table td {
			padding: 12px;
			text-align: left;
			border-bottom: 1px solid #e5e5e5;
		}
		.email-table th {
			background-color: <?php echo esc_attr( $bg_color ); ?>;
			font-weight: 600;
			width: 35%;
		}
		/* Responsive */
		@media only screen and (max-width: 600px) {
			.email-wrapper {
				width: 100% !important;
				padding: 10px !important;
			}
			.email-content {
				padding: 20px !important;
			}
		}
	</style>
</head>
<body style="margin: 0; padding: 0; background-color: <?php echo esc_attr( $bg_color ); ?>;">
	<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background-color: <?php echo esc_attr( $bg_color ); ?>;">
		<tr>
			<td align="center" style="padding: 40px 20px;">
				<!-- Email Wrapper -->
				<table role="presentation" class="email-wrapper" cellpadding="0" cellspacing="0" width="600" style="max-width: 600px;">
					<!-- Header -->
					<tr>
						<td align="center" style="padding-bottom: 20px;">
							<?php if ( ! empty( $header_image ) ) : ?>
								<a href="<?php echo esc_url( $site_url ?? home_url() ); ?>">
									<img src="<?php echo esc_url( $header_image ); ?>" alt="<?php echo esc_attr( $site_name ?? get_bloginfo( 'name' ) ); ?>" style="max-width: 200px; height: auto;">
								</a>
							<?php else : ?>
								<a href="<?php echo esc_url( $site_url ?? home_url() ); ?>" style="font-size: 28px; font-weight: bold; color: <?php echo esc_attr( $base_color ); ?>; text-decoration: none;">
									<?php echo esc_html( $site_name ?? get_bloginfo( 'name' ) ); ?>
								</a>
							<?php endif; ?>
						</td>
					</tr>
					<!-- Content -->
					<tr>
						<td>
							<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background-color: <?php echo esc_attr( $body_color ); ?>; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
								<tr>
									<td class="email-content" style="padding: 40px;">
										<?php if ( ! empty( $email_heading ) ) : ?>
											<h1 style="margin: 0 0 24px 0; font-size: 24px; font-weight: 600; color: <?php echo esc_attr( $text_color ); ?>; line-height: 1.3;">
												<?php echo esc_html( $email_heading ); ?>
											</h1>
										<?php endif; ?>
										<?php
										/**
										 * Fires after the email header.
										 *
										 * Allows adding custom content after the email heading.
										 *
										 * @since 1.0.0
										 *
										 * @param string $email_heading Email heading text.
										 */
										do_action( 'wpss_email_header', $email_heading );
										?>
