<?php
/**
 * Recipient activation email template.
 *
 * Variables available: $email, $username, $password, $full_name, $product_name, $sender_name
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$site_name    = get_bloginfo( 'name' );
$admin_email  = get_bloginfo( 'admin_email' );
$login_url    = wp_login_url();
$first_name   = trim( current( explode( ' ', $full_name ) ) );

// Use WooCommerce email wrapper when available.
$has_wc_templates = function_exists( 'wc_get_template' );

if ( $has_wc_templates ) {
	wc_get_template( 'emails/email-header.php', array( 'email_heading' => __( 'Your Course is Ready!', 'jwb-checkout' ) ) );
}
?>
<?php if ( ! $has_wc_templates ) : ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo esc_html( $site_name ); ?> — <?php esc_html_e( 'Your Course is Ready!', 'jwb-checkout' ); ?></title>
<style>
  body { margin: 0; padding: 0; background-color: #f5f5f5; font-family: Arial, sans-serif; font-size: 15px; color: #333333; }
  .email-wrapper { max-width: 600px; margin: 30px auto; background: #ffffff; border: 1px solid #e0e0e0; border-radius: 4px; overflow: hidden; }
  .email-header { background-color: #8b5cf6; padding: 30px 40px; text-align: center; }
  .email-header h1 { margin: 0; color: #ffffff; font-size: 24px; }
  .email-body { padding: 40px; }
  .email-footer { background-color: #f7f7f7; padding: 20px 40px; text-align: center; font-size: 13px; color: #888888; border-top: 1px solid #e0e0e0; }
  .credentials-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
  .credentials-table td { padding: 10px 14px; border: 1px solid #e0e0e0; }
  .credentials-table td:first-child { font-weight: bold; background-color: #f7f7f7; width: 40%; }
  .cta-btn { display: inline-block; margin: 20px 0; padding: 14px 28px; background-color: #8b5cf6; color: #ffffff !important; text-decoration: none; border-radius: 4px; font-size: 15px; font-weight: bold; }
  .notice { font-size: 13px; color: #666666; margin-top: 10px; }
</style>
</head>
<body>
<div class="email-wrapper">
<div class="email-header">
  <h1><?php echo esc_html( $site_name ); ?></h1>
</div>
<div class="email-body">
<?php endif; ?>

<p><?php printf(
	/* translators: %s: recipient first name */
	esc_html__( 'Hi %s,', 'jwb-checkout' ),
	esc_html( $first_name )
); ?></p>

<p><?php printf(
	/* translators: 1: sender name, 2: site name */
	esc_html__( '%1$s has gifted you a course on %2$s!', 'jwb-checkout' ),
	esc_html( $sender_name ),
	esc_html( $site_name )
); ?></p>

<p><strong><?php esc_html_e( "You've been enrolled in:", 'jwb-checkout' ); ?></strong> <?php echo esc_html( $product_name ); ?></p>

<p><?php esc_html_e( 'Here are your login credentials to get started:', 'jwb-checkout' ); ?></p>

<table class="credentials-table" cellspacing="0" cellpadding="0">
	<tr>
		<td><?php esc_html_e( 'Email', 'jwb-checkout' ); ?></td>
		<td><?php echo esc_html( $email ); ?></td>
	</tr>
	<tr>
		<td><?php esc_html_e( 'Username', 'jwb-checkout' ); ?></td>
		<td><?php echo esc_html( $username ); ?></td>
	</tr>
	<tr>
		<td><?php esc_html_e( 'Temporary Password', 'jwb-checkout' ); ?></td>
		<td><?php echo esc_html( $password ); ?></td>
	</tr>
</table>

<p>
	<a href="<?php echo esc_url( $login_url ); ?>" class="cta-btn">
		<?php esc_html_e( 'Log In and Start Your Course', 'jwb-checkout' ); ?>
	</a>
</p>

<p class="notice"><?php esc_html_e( 'Please reset your password after your first login.', 'jwb-checkout' ); ?></p>

<p><?php printf(
	/* translators: %s: admin email address */
	__( 'If you have any questions, please contact us at <a href="mailto:%1$s">%1$s</a>.', 'jwb-checkout' ),
	esc_attr( $admin_email ),
	esc_html( $admin_email )
); ?></p>

<?php if ( $has_wc_templates ) : ?>
	<?php wc_get_template( 'emails/email-footer.php' ); ?>
<?php else : ?>
</div><!-- .email-body -->
<div class="email-footer">
	&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( $site_name ); ?>
</div>
</div><!-- .email-wrapper -->
</body>
</html>
<?php endif; ?>
