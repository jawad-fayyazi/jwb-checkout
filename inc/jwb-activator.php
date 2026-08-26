<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Runs on plugin activation.
 */
function jwb_activate() {
	// Ensure WooCommerce is active.
	if ( ! class_exists( 'WooCommerce' ) ) {
		deactivate_plugins( plugin_basename( JWB_PLUGIN_DIR . 'jwb-checkout.php' ) );
		wp_die( esc_html__( 'JWB Checkout requires WooCommerce to be installed and active.', 'jwb-checkout' ) );
	}

	flush_rewrite_rules();
}
