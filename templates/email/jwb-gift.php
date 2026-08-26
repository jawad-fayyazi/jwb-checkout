<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$site_name    = get_bloginfo( 'name' );
$admin_email  = get_bloginfo( 'admin_email' );
$first_name   = trim( current( explode( ' ', $full_name ) ) );
$sender_first = trim( current( explode( ' ', $sender_name ) ) );
?>
<!DOCTYPE html>
<html lang="en-US">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<title><?php echo esc_html( $product_name ); ?></title>
	<style type="text/css">
		@media print { body { -webkit-print-color-adjust: exact; } }
	</style>
</head>
<body leftmargin="0" marginwidth="0" topmargin="0" marginheight="0" offset="0" style="padding: 0; background-color: #f7f7f7;">
	<div id="wrapper" dir="ltr" style="background-color: #f7f7f7; margin: 0; padding: 70px 0; -webkit-text-size-adjust: none !important; width: 100%;">
		<table border="0" cellpadding="0" cellspacing="0" height="100%" width="100%">
			<tr>
				<td align="center" valign="top">
					<table border="0" cellpadding="0" cellspacing="0" width="600" id="template_container" style="box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1) !important; background-color: #ffffff; border: 1px solid #dedede; border-radius: 3px !important;">
						<tr>
							<td align="center" valign="top">
								<!-- Header -->
								<table border="0" cellpadding="0" cellspacing="0" width="100%" id="template_header" style="background-color: #749c90; border-radius: 3px 3px 0 0 !important; color: #ffffff; border-bottom: 0; font-weight: bold; line-height: 100%; vertical-align: middle; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;">
									<tr>
										<td id="header_wrapper" style="padding: 36px 25px; display: block; text-align: center;">
											<h1 style="color: #ffffff; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; font-size: 30px; font-weight: 300; line-height: 150%; margin: 0; text-align: left; text-shadow: 0 1px 0 #90b0a6; background-color: inherit;">
												<?php echo esc_html( $product_name ); ?>
											</h1>
										</td>
									</tr>
								</table>
								<!-- End Header -->
							</td>
						</tr>
						<tr>
							<td align="center" valign="top">
								<!-- Body -->
								<table border="0" cellpadding="0" cellspacing="0" width="600" id="template_body">
									<tr>
										<td valign="top" id="body_content" style="background-color: #ffffff;">
											<!-- Content -->
											<table border="0" cellpadding="20" cellspacing="0" width="100%">
												<tr>
													<td valign="top" style="padding: 48px 48px 32px;">
														<div id="body_content_header" style="color: #494841; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; font-size: 14px; line-height: 150%; text-align: left;">
															<p style="margin: 0 0 16px;">
																<?php printf( 
																	esc_html__( '%1$s gifted you access to %2$s!', 'jwb-checkout' ), 
																	esc_html( $sender_name ), 
																	'<strong>' . esc_html( $product_name ) . '</strong>' 
																); ?>
															</p>
														</div>
														
														<?php if ( ! empty( $product_desc ) ) : ?>
														<div id="body_content_description" style="color: #494841; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; font-size: 14px; line-height: 150%; text-align: left; margin-top: 25px;">
															<?php echo wp_kses_post( $product_desc ); ?>
														</div>
														<?php endif; ?>
														
														<div id="body_content_message" style="color: #494841; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; font-size: 14px; line-height: 150%; text-align: left; margin-top: 25px;">
															<h3 style="color: #749c90; display: block; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; font-size: 16px; font-weight: bold; line-height: 130%; margin: 16px 0 8px; text-align: left; text-transform: uppercase;">
																<?php printf( esc_html__( 'FROM %s', 'jwb-checkout' ), esc_html( $sender_name ) ); ?>
															</h3>
															<p style="margin: 0 0 16px; padding: 20px; background-color: #F7F7F7; font-style: italic; font-weight: bold;">
																<?php printf( esc_html__( 'Hi %s,', 'jwb-checkout' ), esc_html( $first_name ) ); ?><br />
																<?php esc_html_e( 'Hope you enjoy the study!', 'jwb-checkout' ); ?><br />
																<?php esc_html_e( 'Blessings,', 'jwb-checkout' ); ?><br />
																<?php echo esc_html( $sender_first ); ?>
															</p>
														</div>
														
														<div id="body_content_redeem" style="color: #494841; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; font-size: 14px; line-height: 150%; text-align: left; margin-top: 25px;">
															<p style="margin: 0 0 16px;"><?php esc_html_e( 'To redeem your gift, click the button below and log in.', 'jwb-checkout' ); ?></p>
															<a id="redeem-gift-button" href="<?php echo esc_url( $login_url ); ?>" style="color: #749c90; font-weight: normal; text-decoration: underline; background-color: #749c90; color: #ffffff; display: inline-block; font-size: 14px; font-weight: bold; line-height: 50px; text-align: center; text-decoration: none; width: 200px; -webkit-text-size-adjust: none; text-transform: uppercase; padding-left: 15px; padding-right: 15px; margin-top: 20px;">
																<?php esc_html_e( 'Redeem your gift', 'jwb-checkout' ); ?>
															</a>
														</div>
														
														<div id="body_content_footer" style="color: #494841; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; font-size: 14px; line-height: 150%; text-align: left; margin-top: 45px; font-weight: bold; font-style: italic;">
															<p style="margin: 0 0 16px;">
																<?php printf( 
																	__( 'If you have any questions, please email us at <a href="mailto:%1$s" style="color: #749c90; font-weight: normal; text-decoration: underline;">%1$s</a>', 'jwb-checkout' ), 
																	esc_attr( $admin_email ) 
																); ?>
															</p>
														</div>
													</td>
												</tr>
											</table>
											<!-- End Content -->
										</td>
									</tr>
								</table>
								<!-- End Body -->
							</td>
						</tr>
					</table>
				</td>
			</tr>
			<tr>
				<td align="center" valign="top">
					<!-- Footer -->
					<table border="0" cellpadding="10" cellspacing="0" width="600" id="template_footer" style="background-color: #000;">
						<tr>
							<td valign="top" style="padding: 0; border-radius: 6px;">
								<table border="0" cellpadding="10" cellspacing="0" width="100%">
									<tr>
										<td colspan="2" valign="middle" id="credit" style="border: 0; color: #777670; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; font-size: 13px; line-height: 100%; text-align: center; padding: 15px 0; border-radius: 0; color: #fff;">
											<p style="margin: 0;"><?php echo esc_html( $product_name ); ?></p>
										</td>
									</tr>
								</table>
							</td>
						</tr>
					</table>
					<!-- End Footer -->
				</td>
			</tr>
		</table>
	</div>
</body>
</html>