<?php
/**
 * Plugin Name: JWB Checkout
 * Description: Group codes, multi-course discounts, and gift course flows for JoannaWeaverBooks.com.
 * Author: TeknoFlair
 * Version: 1.0.1
 * Text Domain: jwb-checkout
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'JWB_VERSION',    '1.0.1' );

// Declare HPOS (High Performance Order Storage) compatibility.
add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

define( 'JWB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'JWB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require JWB_PLUGIN_DIR . 'inc/jwb-activator.php';
require JWB_PLUGIN_DIR . 'inc/jwb-tools.php';
require JWB_PLUGIN_DIR . 'inc/jwb-discount.php';
require JWB_PLUGIN_DIR . 'inc/jwb-checkout.php';
require JWB_PLUGIN_DIR . 'inc/jwb-cart.php';
require JWB_PLUGIN_DIR . 'inc/jwb-orders.php';
require JWB_PLUGIN_DIR . 'inc/jwb-emails.php';
require JWB_PLUGIN_DIR . 'inc/jwb-product-meta.php';

register_activation_hook( __FILE__, 'jwb_activate' );

add_action( 'wp_enqueue_scripts', function() {
	if ( ! is_checkout() && ! is_wc_endpoint_url( 'order-received' ) ) { return; }
	wp_enqueue_script( 'jwb-checkout', JWB_PLUGIN_URL . 'assets/js/checkout.js', array( 'jquery' ), JWB_VERSION, true );
	wp_enqueue_style( 'jwb-checkout', JWB_PLUGIN_URL . 'assets/css/checkout.css', array(), JWB_VERSION );
	wp_localize_script( 'jwb-checkout', 'jwb_params', array(
		'ajax_url' => admin_url( 'admin-ajax.php' ),
		'nonce'    => wp_create_nonce( 'jwb_nonce' ),
	) );
} );
