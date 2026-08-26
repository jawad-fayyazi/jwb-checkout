<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
// Available variables: $username, $site_name, $myaccount_url, $reset_url
?>
<!DOCTYPE html>
<html dir="ltr" lang="en-US" prefix="og: https://ogp.me/ns#">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<title><?php echo esc_html( $site_name ); ?></title>
	<style type="text/css">
		@media print {
			body { -webkit-print-color-adjust: exact; }
		}
	</style>
</head>
<body leftmargin="0" marginwidth="0" topmargin="0" marginheight="0" offset="0" style="padding: 0;">
	<div id="wrapper" dir="ltr" style="background-color: #f7f7f7; margin: 0; padding: 70px 0; width: 100%; -webkit-text-size-adjust: none;" bgcolor="#f7f7f7" width="100%">
		<table border="0" cellpadding="0" cellspacing="0" height="100%" width="100%">
			<tr>
				<td align="center" valign="top">
					<table border="0" cellpadding="0" cellspacing="0" width="600" id="template_container" style="background-color: #fff; border: 1px solid #dedede; box-shadow: 0 1px 4px rgba(0,0,0,.1); border-radius: 3px;" bgcolor="#fff">
						<tr>
							<td align="center" valign="top">
								<!-- Header -->
								<table border="0" cellpadding="0" cellspacing="0" width="100%" id="template_header" style='background-color: #749c90; color: #fff; border-bottom: 0; font-weight: bold; line-height: 100%; vertical-align: middle; font-family: "Helvetica Neue",Helvetica,Roboto,Arial,sans-serif; border-radius: 3px 3px 0 0;' bgcolor="#749c90">
									<tr>
										<td id="header_wrapper" style="padding: 36px 48px; display: block;">
											<div id="trg_header_inner_cont" style="width: 100%; text-align: center;" width="100%" align="center">
												<h1 style='font-family: "Helvetica Neue",Helvetica,Roboto,Arial,sans-serif; font-size: 30px; font-weight: 300; line-height: 150%; margin: 0; text-align: left; text-shadow: 0 1px 0 #90b0a6; color: #fff; background-color: inherit;' bgcolor="inherit">Welcome to <?php echo esc_html( $site_name ); ?></h1>
											</div>
										</td>
									</tr>
								</table>
							</td>
						</tr>
						<tr>
							<td align="center" valign="top">
								<!-- Body -->
								<table border="0" cellpadding="0" cellspacing="0" width="600" id="template_body">
									<tr>
										<td valign="top" id="body_content" style="background-color: #fff;" bgcolor="#fff">
											<table border="0" cellpadding="20" cellspacing="0" width="100%">
												<tr>
													<td valign="top" style="padding: 48px 48px 32px;">
														<div id="body_content_inner" style='color: #494841; font-family: "Helvetica Neue",Helvetica,Roboto,Arial,sans-serif; font-size: 14px; line-height: 150%; text-align: left;' align="left">
															
															<p style="margin: 0 0 16px;">Hi <?php echo esc_html( $username ); ?>,</p>
															
															<p style="margin: 0 0 16px;">Thanks for creating an account on <?php echo esc_html( $site_name ); ?>. Your username is <strong><?php echo esc_html( $username ); ?></strong>. You can access your account area to view orders, change your password, and more at: <a href="<?php echo esc_url( $myaccount_url ); ?>" rel="nofollow" style="color: #749c90; font-weight: normal; text-decoration: underline;"><?php echo esc_url( $myaccount_url ); ?></a></p>
															
															<p style="margin: 0 0 16px;"><a href="<?php echo esc_url( $reset_url ); ?>" style="color: #749c90; font-weight: normal; text-decoration: underline;">Click here to set your new password.</a></p>
															
															<p style="margin: 0 0 16px;">We look forward to seeing you soon.</p>
														
														</div>
													</td>
												</tr>
											</table>
										</td>
									</tr>
								</table>
							</td>
						</tr>
						<tr>
							<td align="center" valign="top">
								<!-- Footer -->
								<table border="0" cellpadding="10" cellspacing="0" width="600" id="template_footer">
									<tr>
										<td valign="top" style="padding: 0; border-radius: 6px;">
											<table border="0" cellpadding="10" cellspacing="0" width="100%">
												<tr>
													<td colspan="2" valign="middle" id="credit" style='border-radius: 6px; border: 0; color: #777670; font-family: "Helvetica Neue",Helvetica,Roboto,Arial,sans-serif; font-size: 12px; line-height: 150%; text-align: center; padding: 24px 0;' align="center">
														<p style="margin: 0 0 16px;"><?php echo esc_html( $site_name ); ?></p>
													</td>
												</tr>
											</table>
										</td>
									</tr>
								</table>
							</td>
						</tr>
					</table>
				</td>
			</tr>
		</table>
	</div>
</body>
</html>