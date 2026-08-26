<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Helper function to force HTML emails
function jwb_force_html_content_type() {
	return 'text/html';
}

/**
 * Send the recipient activation email to a newly created user.
 *
 * @param array $data {
 *     @type string $email        Recipient email address.
 *     @type string $username     Generated username.
 *     @type string $password     Generated temporary password.
 *     @type string $full_name    Recipient's full name.
 *     @type string $product_name Name of the course/product.
 *     @type string $sender_name  Purchaser's full name.
 *     @type int    $order_id     WooCommerce order ID.
 * }
 */
/**
 * Send the recipient activation email to a newly created user.
 * Routes to specific templates based on the product type.
 */
function jwb_send_recipient_activation_email( $data ) {
	$email        = sanitize_email( $data['email'] );
	$username     = sanitize_text_field( $data['username'] );
	$password     = $data['password']; // raw — goes into email body only
	$full_name    = sanitize_text_field( $data['full_name'] );
	$product_name = sanitize_text_field( $data['product_name'] );
	$product_id   = isset( $data['product_id'] ) ? (int) $data['product_id'] : 0;
	$product_type = isset( $data['product_type'] ) ? sanitize_text_field( $data['product_type'] ) : '';
	$sender_name  = sanitize_text_field( $data['sender_name'] );
	$order_id     = (int) $data['order_id'];
	$login_url = isset( $data['login_url'] ) ? esc_url( $data['login_url'] ) : home_url( '/my-account/' );

	if ( ! is_email( $email ) ) { return; }

	$subject  = '';
	$template = '';

	// -----------------------------------------------------------------
	// Explicit Email Routing
	// -----------------------------------------------------------------
	if ( 'gift' === $product_type ) {
		
		// Only fetch the Main Product Description for Gifts
		$product_desc = '';
		if ( $product_id ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				$product_desc = apply_filters( 'the_content', $product->get_description() ); 
			}
		}

		$subject  = sprintf( __( 'You have received a gift from %s!', 'jwb-checkout' ), $sender_name );
		$template = JWB_PLUGIN_DIR . 'templates/email/jwb-gift.php'; // New dedicated Gift template

	} elseif ( 'group-study' === $product_type ) {
		
		// Placeholder for Phase 2 Group Flow
		$subject  = __( 'You have been invited to a Group Study!', 'jwb-checkout' );
		$template = JWB_PLUGIN_DIR . 'templates/email/jwb-group.php';
		
	} else {
		
		// Generic Fallback
		$subject  = sprintf( __( '[%s] Your Course is Ready!', 'jwb-checkout' ), get_bloginfo( 'name' ) );
		$template = JWB_PLUGIN_DIR . 'templates/email/recipient-activation.php';
		
	}

	// Render and send the assigned template
	if ( file_exists( $template ) ) {
		ob_start();
		include $template;
		$body = ob_get_clean();
        add_filter( 'wp_mail_content_type', 'jwb_force_html_content_type' );
        wp_mail( $email, $subject, $body );
        remove_filter( 'wp_mail_content_type', 'jwb_force_html_content_type' );
	} else {
		jwb_log( array( 'error' => 'Email template missing', 'template' => $template ) );
	}
}

// ---------------------------------------------------------------------------
// Multiple Group Recipient Email Route
// ---------------------------------------------------------------------------
function jwb_send_multiple_group_activation_email( $data ) {
	$email        = sanitize_email( $data['email'] );
	$username     = sanitize_text_field( $data['username'] );
	$password     = $data['password']; 
	$full_name    = sanitize_text_field( $data['full_name'] );
	$product_name = sanitize_text_field( $data['product_name'] );
	$product_id   = isset( $data['product_id'] ) ? (int) $data['product_id'] : 0;
	$sender_name  = sanitize_text_field( $data['sender_name'] );
	$coupon_code  = sanitize_text_field( $data['coupon_code'] );
	$is_new_user  = ! empty( $data['is_new_user'] ) ? true : false;
	$login_url = isset( $data['login_url'] ) ? esc_url( $data['login_url'] ) : home_url( '/my-account/' );

	if ( ! is_email( $email ) ) { return; }

	// Fetch the strictly defined MAIN Product Description for the recipient
	$product_desc = '';
	if ( $product_id ) {
		$product = wc_get_product( $product_id );
		if ( $product ) {
			$product_desc = apply_filters( 'the_content', $product->get_description() ); 
		}
	}

	$subject  = sprintf( __( '%s invited you to lead a Study Group!', 'jwb-checkout' ), $sender_name );
	$template = JWB_PLUGIN_DIR . 'templates/email/jwb-multiple-group.php';

	if ( file_exists( $template ) ) {
		ob_start();
		include $template;
		$body = ob_get_clean();
		add_filter( 'wp_mail_content_type', 'jwb_force_html_content_type' );
		wp_mail( $email, $subject, $body );
		remove_filter( 'wp_mail_content_type', 'jwb_force_html_content_type' );
	} else {
		jwb_log( array( 'error' => 'Multiple Group Email template missing', 'template' => $template ) );
	}
}

// ---------------------------------------------------------------------------
// Buyer Invoice: Inject Dynamic Short Description & Start Button
// ---------------------------------------------------------------------------
add_action( 'woocommerce_email_before_order_table', 'jwb_inject_dynamic_invoice_content', 10, 4 );

function jwb_inject_dynamic_invoice_content( $order, $sent_to_admin, $plain_text, $email ) {
	if ( $sent_to_admin || ! in_array( $email->id, array( 'customer_processing_order', 'customer_completed_order' ) ) ) {
		return;
	}

	if ( $plain_text ) { return; }

	$has_individual     = false;
	$has_group          = false;
	$product_to_feature = null;
	$current_priority   = 99; // Lower number = higher priority

	// 1. Scan the cart with strict priority rules
	foreach ( $order->get_items() as $item ) {
		$product_id = $item->get_product_id();
		$type       = jwb_get_product_type( $product_id );

		// Priority 1: Base Group (Buyer is the leader)
		if ( 'group-study' === $type ) {
			$has_group = true;
			if ( $current_priority > 1 ) {
				$product_to_feature = $product_id;
				$current_priority   = 1;
			}
		} 
		// Priority 2: Individual / Session
		elseif ( in_array( $type, array( 'individual-study', 'session' ) ) ) {
			$has_individual = true;
			if ( $current_priority > 2 ) {
				$product_to_feature = $product_id;
				$current_priority   = 2;
			}
		} 
		// Priority 3: Multiple Group Only (Buying for others)
		elseif ( 'multiple-group' === $type ) {
			if ( $current_priority > 3 ) {
				$product_to_feature = $product_id;
				$current_priority   = 3;
			}
		} 
		// Priority 4: Gift Only
		elseif ( 'gift' === $type ) {
			if ( $current_priority > 4 ) {
				$product_to_feature = $product_id;
				$current_priority   = 4;
			}
		}
	}

	if ( ! $product_to_feature ) { return; }

	$product = wc_get_product( $product_to_feature );
	if ( ! $product ) { return; }

	$short_desc = apply_filters( 'woocommerce_short_description', $product->get_short_description() );

    // Fetch the coupon code specifically for the Base Group buyer
	$buyer_coupon_code = '';
	if ( $has_group ) {
		$buyer_coupon_code = $order->get_meta( '_jwb_group_coupon_' . $product_to_feature );
		
		// TIMING FIX: Fallback lookup directly to the database in case order meta is delayed/cached
		if ( empty( $buyer_coupon_code ) ) {
			global $wpdb;
			$found_code = $wpdb->get_var( $wpdb->prepare( "
				SELECT post_title FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type = 'shop_coupon' 
				AND pm.meta_key = '_jwb_source_order' 
				AND pm.meta_value = %d
				LIMIT 1
			", $order->get_id() ) );
			
			if ( $found_code ) {
				$buyer_coupon_code = $found_code;
			}
		}
	}
	// 2. Render the Output
	echo '<div class="jwb-invoice-custom-content" style="margin-bottom: 30px; font-family: \'Helvetica Neue\', Helvetica, Arial, sans-serif;">';
	
	if ( ! empty( $short_desc ) ) {
		echo '<div style="color: #444444; line-height: 1.6; margin-bottom: 25px;">';
		echo wp_kses_post( $short_desc );
		echo '</div>';
	}
	
	// Coupon Block (Only shows if they bought the Base Group)
	if ( ! empty( $buyer_coupon_code ) ) {
		echo '<div style="background-color: #ffffff; padding: 0; margin: 0 0 25px 0; border: 1px solid #e0e0e0;">';
		echo '<div style="padding: 15px 20px; background-color: #f7f7f7; border-bottom: 1px solid #e0e0e0; font-weight: bold; color: #749c90;">Your Promo Code</div>';
		echo '<div style="padding: 20px; font-size: 14px; color: #444444;">';
		echo '<p style="margin-top: 0;">Share the code below with your group members. They can use it to receive 50% off any individual JWB course or study session.</p>';
		echo '<table style="width: 100%; border-collapse: collapse; margin-top: 15px;"><tr>';
		echo '<td style="padding: 10px 0; font-weight: bold; width: 60%; color: #333333;">Your 50% off promo code:</td>';
		echo '<td style="padding: 10px 14px; background-color: #f7f7f7; border: 1px solid #e0e0e0; font-family: monospace; font-size: 16px; letter-spacing: 1px; text-align: center; color: #333333;">' . esc_html( $buyer_coupon_code ) . '</td>';
		echo '</tr></table>';
		echo '</div></div>';
	}

	// 3. Strict Button Logic: ONLY show if they bought for themselves (Base Group or Individual)
	if ( $has_group || $has_individual ) {
		
		// Fetch the direct course link, fallback to my-account if missing
		$course_ids = array_filter( (array) get_post_meta( $product_to_feature, '_related_course', true ), 'is_numeric' );
		$target_url = ! empty( $course_ids ) ? get_permalink( reset( $course_ids ) ) : home_url( '/my-account/' );
		
		$btn_text   = $has_group ? __( 'ACCESS THE STUDY', 'jwb-checkout' ) : __( 'START YOUR STUDY', 'jwb-checkout' );
		
		echo '<div style="margin-bottom: 25px;">';
		echo '<a href="' . esc_url( $target_url ) . '" style="display: inline-block; padding: 14px 24px; background-color: #779c8e; color: #ffffff !important; text-decoration: none; font-weight: bold; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; border-radius: 4px;">';
		echo esc_html( $btn_text );
		echo '</a>';
		echo '</div>';
	}

	echo '</div>'; 
}

// ---------------------------------------------------------------------------
// Account Creation Email (Magic Link)
// ---------------------------------------------------------------------------
function jwb_send_account_creation_email( $user_id ) {
	$user = get_userdata( $user_id );
	if ( ! $user ) { return; }

	// Generate a secure WooCommerce reset key
	$key = get_password_reset_key( $user );
	if ( is_wp_error( $key ) ) { return; }

	$myaccount_url = wc_get_page_permalink( 'myaccount' );
	
	// Build the exact WooCommerce "Set New Password" URL
	$reset_url = add_query_arg( array(
		'action' => 'newaccount',
		'key'    => $key,
		'login'  => rawurlencode( $user->user_login )
	), wc_get_endpoint_url( 'lost-password', '', $myaccount_url ) );

	$site_name = get_bloginfo( 'name' );
	$subject   = sprintf( __( 'Welcome to %s', 'jwb-checkout' ), $site_name );
	$template  = JWB_PLUGIN_DIR . 'templates/email/jwb-new-account.php';

	if ( file_exists( $template ) ) {
		ob_start();
		$username = $user->user_login;
		include $template;
		$body = ob_get_clean();

		add_filter( 'wp_mail_content_type', 'jwb_force_html_content_type' );
		wp_mail( $user->user_email, $subject, $body );
		remove_filter( 'wp_mail_content_type', 'jwb_force_html_content_type' );
	} else {
		jwb_log( array( 'error' => 'Account Creation Email template missing', 'template' => $template ) );
	}
}