<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * ---------------------------------------------------------------------------
 * UNIFIED BULK DISCOUNT ENGINE
 * ---------------------------------------------------------------------------
 * Safely calculates quantities and applies a dynamic 50% discount as a native cart fee.
 */
add_action( 'woocommerce_cart_calculate_fees', 'jwb_apply_bulk_discount_fee', 10, 1 );

function jwb_apply_bulk_discount_fee( $cart ) {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) { return; }

	$ind_qty   = 0;
	$gift_qty  = 0;
	$group_qty = 0;
	$multi_qty = 0;

	$gift_price  = 0;
	$multi_price = 0;

	// 1. Tally the exact quantities and grab the live product prices
	foreach ( $cart->get_cart() as $item ) {
		$pid   = $item['data']->get_id();
		$qty   = $item['quantity'];
		$price = (float) $item['data']->get_price(); // Get the dynamic price

		if ( jwb_product_has_category( $pid, 'individual-study' ) ) { 
			$ind_qty += $qty; 
		}
		if ( jwb_product_has_category( $pid, 'gift' ) ) { 
			$gift_qty += $qty; 
			$gift_price = $price; // Store the gift's current price
		}
		if ( jwb_product_has_category( $pid, 'group-study' ) ) { 
			$group_qty += $qty; 
		}
		if ( jwb_product_has_category( $pid, 'multiple-group' ) ) { 
			$multi_qty += $qty; 
			$multi_price = $price; // Store the multiple-group's current price
		}
	}

	// 2. Calculate Discountable Items based on Scenarios A, B, C, D
	$discountable_gifts  = ( $ind_qty > 0 ) ? $gift_qty : max( 0, $gift_qty - 1 );
	$discountable_groups = ( $group_qty > 0 ) ? $multi_qty : max( 0, $multi_qty - 1 );

	// 3. Apply the dynamic 50% discount math
	$total_discount_amount = 0;

	if ( $discountable_gifts > 0 && $gift_price > 0 ) {
		$total_discount_amount += ( $discountable_gifts * ( $gift_price * 0.5 ) ); 
	}
	
	if ( $discountable_groups > 0 && $multi_price > 0 ) {
		$total_discount_amount += ( $discountable_groups * ( $multi_price * 0.5 ) ); 
	}

	if ( $total_discount_amount > 0 ) {
		$cart->add_fee( __( 'MULTIPLE COURSE DISCOUNT', 'jwb-checkout' ), -$total_discount_amount, true );
	}
}

/**
 * ---------------------------------------------------------------------------
 * PROMO CODE BLOCKER
 * ---------------------------------------------------------------------------
 * Disables coupons if a bulk discount is actively applying.
 */
add_filter( 'woocommerce_coupon_is_valid', 'jwb_block_coupon_when_discount_active', 10, 3 );

function jwb_block_coupon_when_discount_active( $valid, $coupon, $discount ) {
	if ( ! $valid ) { return $valid; }

	// Allow special group member coupons
	if ( 'group_member' === get_post_meta( $coupon->get_id(), '_jwb_coupon_type', true ) ) {
		return $valid;
	}

	if ( ! WC()->cart || WC()->cart->is_empty() ) { return $valid; }

	$ind_qty   = 0;
	$gift_qty  = 0;
	$group_qty = 0;
	$multi_qty = 0;

	foreach ( WC()->cart->get_cart() as $item ) {
		$pid = $item['data']->get_id();
		$qty = $item['quantity'];

		if ( jwb_product_has_category( $pid, 'individual-study' ) ) { $ind_qty += $qty; }
		if ( jwb_product_has_category( $pid, 'gift' ) ) { $gift_qty += $qty; }
		if ( jwb_product_has_category( $pid, 'group-study' ) ) { $group_qty += $qty; }
		if ( jwb_product_has_category( $pid, 'multiple-group' ) ) { $multi_qty += $qty; }
	}

	$discountable_gifts  = ( $ind_qty > 0 ) ? $gift_qty : max( 0, $gift_qty - 1 );
	$discountable_groups = ( $group_qty > 0 ) ? $multi_qty : max( 0, $multi_qty - 1 );

	// Block if any bulk discount is active
	if ( $discountable_gifts > 0 || $discountable_groups > 0 ) {
		throw new Exception(
			esc_html__( 'Note: Promo codes cannot be combined with the 50% discount.', 'jwb-checkout' )
		);
	}

	return $valid;
}

/**
 * ---------------------------------------------------------------------------
 * PROACTIVE COUPON CLEANUP
 * ---------------------------------------------------------------------------
 */
add_action( 'woocommerce_before_calculate_totals', 'jwb_auto_remove_incompatible_coupons', 10, 1 );

function jwb_auto_remove_incompatible_coupons( $cart ) {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) { return; }
	if ( did_action( 'woocommerce_before_calculate_totals' ) >= 2 ) { return; }

	$ind_qty   = 0;
	$gift_qty  = 0;
	$group_qty = 0;
	$multi_qty = 0;

	foreach ( $cart->get_cart() as $item ) {
		$pid = $item['data']->get_id();
		$qty = $item['quantity'];

		if ( jwb_product_has_category( $pid, 'individual-study' ) ) { $ind_qty += $qty; }
		if ( jwb_product_has_category( $pid, 'gift' ) ) { $gift_qty += $qty; }
		if ( jwb_product_has_category( $pid, 'group-study' ) ) { $group_qty += $qty; }
		if ( jwb_product_has_category( $pid, 'multiple-group' ) ) { $multi_qty += $qty; }
	}

	$discountable_gifts  = ( $ind_qty > 0 ) ? $gift_qty : max( 0, $gift_qty - 1 );
	$discountable_groups = ( $group_qty > 0 ) ? $multi_qty : max( 0, $multi_qty - 1 );

	if ( $discountable_gifts > 0 || $discountable_groups > 0 ) {
		$applied_coupons = $cart->get_applied_coupons();
		if ( ! empty( $applied_coupons ) ) {
			foreach ( $applied_coupons as $code ) {
				$coupon = new WC_Coupon( $code );
				if ( 'group_member' !== $coupon->get_meta( '_jwb_coupon_type' ) ) {
					$cart->remove_coupon( $code );
				}
			}
		}
	}
}

/**
 * ---------------------------------------------------------------------------
 * CHECKOUT NOTICES
 * ---------------------------------------------------------------------------
 */
add_action( 'woocommerce_before_cart',          'jwb_maybe_show_discount_notice' );
add_action( 'woocommerce_before_checkout_form', 'jwb_maybe_show_discount_notice' );

function jwb_maybe_show_discount_notice() {
	if ( ! WC()->cart || WC()->cart->is_empty() ) { return; }

	$ind_qty   = 0;
	$gift_qty  = 0;
	$group_qty = 0;
	$multi_qty = 0;

	foreach ( WC()->cart->get_cart() as $item ) {
		$pid = $item['data']->get_id();
		$qty = $item['quantity'];

		if ( jwb_product_has_category( $pid, 'individual-study' ) ) { $ind_qty += $qty; }
		if ( jwb_product_has_category( $pid, 'gift' ) ) { $gift_qty += $qty; }
		if ( jwb_product_has_category( $pid, 'group-study' ) ) { $group_qty += $qty; }
		if ( jwb_product_has_category( $pid, 'multiple-group' ) ) { $multi_qty += $qty; }
	}

	$discountable_gifts  = ( $ind_qty > 0 ) ? $gift_qty : max( 0, $gift_qty - 1 );
	$discountable_groups = ( $group_qty > 0 ) ? $multi_qty : max( 0, $multi_qty - 1 );

	if ( $discountable_gifts > 0 || $discountable_groups > 0 ) {
		jwb_add_unique_notice(
			__( 'Note: Promo codes cannot be combined with the 50% discount.', 'jwb-checkout' ),
			'50% discount'
		);
	}
}

/**
 * Helper to prevent duplicate notices.
 */
function jwb_add_unique_notice( $message, $fingerprint ) {
	$existing = WC()->session ? WC()->session->get( 'wc_notices', array() ) : array();
	$notices  = isset( $existing['notice'] ) ? $existing['notice'] : array();
	foreach ( $notices as $notice ) {
		$text = is_array( $notice ) ? ( $notice['notice'] ?? '' ) : $notice;
		if ( false !== strpos( $text, $fingerprint ) ) { return; }
	}
	wc_add_notice( $message, 'notice' );
}