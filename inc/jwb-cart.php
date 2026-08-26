<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * AJAX handler: swap all plugin-managed cart items to a different purchase type.
 *
 * Directions:
 *   to_individual — swap to individual-study or session (mirrors the gift product's level)
 *   to_group      — swap to group-study
 *   to_gift       — swap to gift
 *
 * Rules:
 *   - Products already in the target type are skipped.
 *   - Sessions cannot be swapped to_group (no group version exists for a session).
 *   - If any sibling cannot be resolved the entire swap is aborted before touching
 *     the cart (all-or-nothing).
 */
add_action( 'wp_ajax_jwb_swap_cart',        'jwb_ajax_swap_cart' );
add_action( 'wp_ajax_nopriv_jwb_swap_cart', 'jwb_ajax_swap_cart' );

function jwb_ajax_swap_cart() {
	check_ajax_referer( 'jwb_nonce', 'nonce' );

	$direction = isset( $_POST['direction'] ) ? sanitize_text_field( $_POST['direction'] ) : '';
    if ( ! in_array( $direction, array( 'to_individual', 'to_group', 'to_gift', 'to_multiple_group' ), true ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid direction.', 'jwb-checkout' ) ) );
		return;
	}

	if ( ! WC()->cart || WC()->cart->is_empty() ) {
		wp_send_json_error( array( 'message' => __( 'Cart is empty.', 'jwb-checkout' ) ) );
		return;
	}

	// -----------------------------------------------------------------------
	// Phase 1 — Build the swap plan. Validate all siblings before touching the cart.
	// -----------------------------------------------------------------------
	$plan = array(); // cart_key => [ 'new_product_id' => int, 'qty' => int, 'keep_original' => bool ]

	foreach ( WC()->cart->get_cart() as $cart_key => $item ) {
		$product_id   = $item['data']->get_id();
		$qty          = $item['quantity'];
		$current_type = jwb_get_product_type( $product_id );

		// Unmanaged products are never touched.
		if ( ! $current_type ) { continue; }

		// Resolve target type from direction + current type.
		switch ( $direction ) {
			case 'to_individual':
				if ( in_array( $current_type, array( 'individual-study', 'session' ), true ) ) {
					continue 2; // already individual — skip
				}
				
				if ( 'gift' === $current_type ) {
					$paired_id = (int) get_post_meta( $product_id, '_jwb_paired_product', true );
					if ( ! $paired_id ) {
						wp_send_json_error( array( 'message' => __( 'One of your gift items has no paired product configured.', 'jwb-checkout' ) ) );
						return;
					}
					
					// 1. Scan the real cart to see if an Individual course is already present
					$has_ind = false;
					foreach ( WC()->cart->get_cart() as $check_item ) {
						if ( in_array( jwb_get_product_type( $check_item['product_id'] ), array( 'individual-study', 'session' ) ) ) {
							$has_ind = true;
							break;
						}
					}

					// 2. Scan our upcoming $plan array to see if we ALREADY queued a gift to convert
					foreach ( $plan as $planned_swap ) {
						if ( ! empty( $planned_swap['new_product_id'] ) ) {
							$has_ind = true;
							break;
						}
					}

					// 3. If they don't have it, add it. If they do, just remove the gift.
					$plan[ $cart_key ] = array(
						'new_product_id' => $has_ind ? false : $paired_id,
						'qty'            => 1, // Force qty to 1 so it perfectly respects the Woo limit
					);
					
					continue 2;
				} else {
					$target_type = 'individual-study';
				}
				break;

			case 'to_group':
				if ( 'session' === $current_type ) {
					wp_send_json_error( array( 'message' => __( 'Study sessions do not have a group version.', 'jwb-checkout' ) ) );
					return;
				}
				if ( 'group-study' === $current_type ) {
					continue 2; // already group — skip
				}

				if ( 'multiple-group' === $current_type ) {
					$paired_id = (int) get_post_meta( $product_id, '_jwb_paired_product', true );
					if ( ! $paired_id ) {
						wp_send_json_error( array( 'message' => __( 'Multiple group item has no paired product configured.', 'jwb-checkout' ) ) );
						return;
					}
					
					// 1. Scan the real cart to see if a Group course is already present
					$has_group = false;
					foreach ( WC()->cart->get_cart() as $check_item ) {
						if ( 'group-study' === jwb_get_product_type( $check_item['product_id'] ) ) {
							$has_group = true;
							break;
						}
					}

					// 2. Scan our upcoming $plan array to see if we ALREADY queued a group to convert
					foreach ( $plan as $planned_swap ) {
						if ( ! empty( $planned_swap['new_product_id'] ) ) {
							$has_group = true;
							break;
						}
					}

					// 3. If they don't have it, add it. If they do, just remove the multiple-group.
					$plan[ $cart_key ] = array(
						'new_product_id' => $has_group ? false : $paired_id,
						'qty'            => 1,
					);
					
					continue 2;
				} else {
					$target_type = 'group-study';
				}
				break;

			case 'to_gift':
				if ( 'gift' === $current_type ) {
					continue 2; // already gift — skip
				}
				$sibling_id = jwb_get_gift_sibling_id( $product_id );
				if ( ! $sibling_id ) {
					wp_send_json_error( array(
						'message' => sprintf(
							/* translators: %s: target product type */
							__( 'Could not find the %s version of one of your cart items. Please contact support.', 'jwb-checkout' ),
							'gift'
						),
					) );
					return;
				}
				
				// MODIFICATION FOR SCENARIO B: 
				// We flag this specific action to keep the individual course and strictly add 1 gift.
				$plan[ $cart_key ] = array( 
					'new_product_id' => $sibling_id, 
					'qty'            => 1, 
					'keep_original'  => true 
				);
				continue 2;
			case 'to_multiple_group':
				if ( 'multiple-group' === $current_type ) {
					continue 2; // already multiple group — skip
				}
				
				$sibling_id = jwb_get_multiple_group_sibling_id( $product_id );
				if ( ! $sibling_id ) {
					wp_send_json_error( array(
						'message' => __( 'Could not find the multiple group version. Please contact support.', 'jwb-checkout' ),
					) );
					return;
				}
				
				// MODIFICATION FOR SCENARIO B (Groups): 
				// We flag this to keep the base Group course and strictly add 1 Multiple Group course.
				$plan[ $cart_key ] = array( 
					'new_product_id' => $sibling_id, 
					'qty'            => 1, 
					'keep_original'  => true 
				);
				continue 2;

			default:
				continue 2;
		}

		// Resolve sibling ID for to_individual and to_group directions.
		$sibling_id = jwb_get_sibling_id( $product_id, $target_type );

		if ( ! $sibling_id ) {
			wp_send_json_error( array(
				'message' => sprintf(
					/* translators: %s: target product type */
					__( 'Could not find the %s version of one of your cart items. Please contact support.', 'jwb-checkout' ),
					$target_type
				),
			) );
			return;
		}

		$plan[ $cart_key ] = array(
			'new_product_id' => $sibling_id,
			'qty'            => $qty,
		);
	}

	if ( empty( $plan ) ) {
		wp_send_json_success( array( 'message' => __( 'Nothing to swap.', 'jwb-checkout' ) ) );
		return;
	}

	// -----------------------------------------------------------------------
	// Phase 2 — Remove old items and add new ones.
	// -----------------------------------------------------------------------
	foreach ( $plan as $cart_key => $swap ) {
		
		// MODIFICATION: Only remove the original item if the 'keep_original' flag is NOT set.
		// This protects the Individual course when adding a Gift, but swaps everything else normally.
		if ( empty( $swap['keep_original'] ) ) {
			WC()->cart->remove_cart_item( $cart_key );
		}
		
		WC()->cart->add_to_cart( $swap['new_product_id'], $swap['qty'] );
	}

	WC()->cart->calculate_totals();

	wp_send_json_success( array( 'message' => __( 'Cart updated.', 'jwb-checkout' ) ) );
}
// ---------------------------------------------------------------------------
// Listener: Add another Gift to the Cart via URL Reload
// ---------------------------------------------------------------------------
add_action( 'template_redirect', 'jwb_handle_add_gift_url' );

function jwb_handle_add_gift_url() {
	// Only trigger if we are on the checkout page and our custom URL parameter is present
	if ( is_checkout() && isset( $_GET['jwb-add-gift'] ) ) {
		
		$product_id = absint( $_GET['jwb-add-gift'] );
		
		if ( $product_id > 0 ) {
			// Add exactly 1 quantity of the product to the cart
			WC()->cart->add_to_cart( $product_id, 1 );
			
			// Redirect back to the clean checkout URL so if they hit "refresh" in their browser, it doesn't accidentally add another one
			wp_safe_redirect( wc_get_checkout_url() );
			exit;
		}
	}
}
// ---------------------------------------------------------------------------
// Listener: Remove ANY Item from the Cart via URL Reload
// ---------------------------------------------------------------------------
add_action( 'template_redirect', 'jwb_handle_remove_item_url' );

function jwb_handle_remove_item_url() {
	// Trigger if we are on the checkout page and our generic remove parameter is present
	if ( is_checkout() && isset( $_GET['jwb-remove-item'] ) ) {
		
		$cart_item_key = sanitize_text_field( $_GET['jwb-remove-item'] );
		
		if ( ! empty( $cart_item_key ) ) {
			$cart = WC()->cart->get_cart();
			
			// Verify the item is actually in the cart
			if ( isset( $cart[ $cart_item_key ] ) ) {
				// Reduce quantity by 1 (WooCommerce auto-removes it if it hits 0)
				$current_qty = $cart[ $cart_item_key ]['quantity'];
				WC()->cart->set_quantity( $cart_item_key, $current_qty - 1 );
			}
			
			// Redirect back to clean URL
			wp_safe_redirect( wc_get_checkout_url() );
			exit;
		}
	}
}

// ---------------------------------------------------------------------------
// Listener: Add another Multiple Group to the Cart via URL Reload
// ---------------------------------------------------------------------------
add_action( 'template_redirect', 'jwb_handle_add_multiple_group_url' );

function jwb_handle_add_multiple_group_url() {
	if ( is_checkout() && isset( $_GET['jwb-add-multiple-group'] ) ) {
		
		$product_id = absint( $_GET['jwb-add-multiple-group'] );
		
		if ( $product_id > 0 ) {
			WC()->cart->add_to_cart( $product_id, 1 );
			wp_safe_redirect( wc_get_checkout_url() );
			exit;
		}
	}
}