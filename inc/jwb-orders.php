<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Central handler for order completion.
 * Triggers group coupon generation and gift recipient processing.
 */
add_action( 'woocommerce_order_status_completed', 'jwb_order_complete_actions', 10, 1 );

function jwb_order_complete_actions( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order ) { return; }

	jwb_maybe_generate_group_coupon( $order_id, $order );
	jwb_maybe_enroll_buyer( $order_id, $order );
	jwb_maybe_process_recipients( $order_id, $order );
	jwb_maybe_process_multiple_groups( $order_id, $order );
}

function jwb_maybe_enroll_buyer( $order_id, $order ) {
	// Idempotent: skips if we already enrolled the buyer for this order
	if ( $order->get_meta( '_jwb_buyer_enrolled' ) ) { return; }

	$buyer_id = $order->get_customer_id();
	if ( ! $buyer_id ) { return; }

	// Loop through items and enroll buyer in INDIVIDUAL, SESSION, or GROUP courses
	foreach ( $order->get_items() as $item ) {
		$product_id = $item->get_product_id();
		$type       = jwb_get_product_type( $product_id );

		// ADDED 'group-study' as a safety net fallback enrollment
		if ( in_array( $type, array( 'individual-study', 'session', 'group-study' ) ) ) {
			
			$course_ids = array_filter( (array) get_post_meta( $product_id, '_related_course', true ), 'is_numeric' );
			$group_ids  = array_filter( (array) get_post_meta( $product_id, '_related_group',  true ), 'is_numeric' );

			if ( ! empty( $course_ids ) && function_exists( 'ld_update_course_access' ) ) {
				foreach ( $course_ids as $cid ) {
					ld_update_course_access( $buyer_id, (int) $cid );
				}
			}

			if ( ! empty( $group_ids ) && function_exists( 'ld_update_group_access' ) ) {
				foreach ( $group_ids as $gid ) {
					ld_update_group_access( $buyer_id, (int) $gid );
				}
			}
		}
	}

	$order->update_meta_data( '_jwb_buyer_enrolled', true );
	$order->save();
}

// ---------------------------------------------------------------------------
// Multiple Group: Create accounts, enroll, generate coupon, and email
// ---------------------------------------------------------------------------

function jwb_maybe_process_multiple_groups( $order_id, $order ) {
	if ( $order->get_meta( '_jwb_multiple_groups_processed' ) ) { return; }

	$recipients = $order->get_meta( '_jwb_multiple_group_recipients' );
	if ( ! is_array( $recipients ) || empty( $recipients ) ) { return; }

	foreach ( $recipients as $recipient ) {
		$email      = sanitize_email( $recipient['email'] );
		$name       = sanitize_text_field( $recipient['name'] );
		$product_id = (int) $recipient['product_id'];

		if ( ! is_email( $email ) ) { continue; }
		
		$product_type = jwb_get_product_type( $product_id );
		if ( 'multiple-group' !== $product_type ) {
			jwb_log( array(
				'warning'    => 'Recipient product is not of type multiple-group — skipping recipient',
				'product_id' => $product_id,
				'email'      => $email,
			) );
			continue;
		}

		$paired_id = (int) get_post_meta( $product_id, '_jwb_paired_product', true );
		if ( ! $paired_id ) {
			jwb_log( array(
				'warning'    => 'Multiple-group product has no paired product set — skipping recipient',
				'product_id' => $product_id,
				'order_id'   => $order_id,
			) );
			continue;
		}

		$course_ids = array_filter( (array) get_post_meta( $paired_id, '_related_course', true ), 'is_numeric' );
		$group_ids  = array_filter( (array) get_post_meta( $paired_id, '_related_group',  true ), 'is_numeric' );

		if ( empty( $course_ids ) && empty( $group_ids ) ) {
			jwb_log( array(
				'warning'    => 'Paired product has no LearnDash courses or groups mapped — skipping recipient',
				'paired_id'  => $paired_id,
				'product_id' => $product_id,
				'email'      => $email,
			) );
			continue;
		}

		// 1. Create WP User Account
		$user_id  = email_exists( $email );
		$is_new   = false;
		$username = '';
		$password = ''; 

		if ( ! $user_id ) {
			$is_new   = true;
			$base     = sanitize_user( current( explode( '@', $email ) ), true );
			$username = $base;
			$counter  = 1;
			
			while ( username_exists( $username ) ) { $username = $base . $counter++; }

			$password = wp_generate_password( 12, false );
			$parts    = explode( ' ', trim( $name ), 2 );

			$user_id = wp_insert_user( array(
				'user_login' => $username,
				'user_pass'  => $password,
				'user_email' => $email,
				'first_name' => $parts[0],
				'last_name'  => isset( $parts[1] ) ? $parts[1] : '',
				'role'       => 'subscriber',
			) );
		}

		if ( is_wp_error( $user_id ) ) {
			jwb_log( array(
				'error'   => 'User creation failed',
				'email'   => $email,
				'message' => $user_id->get_error_message(),
			) );
			continue;
		}

		// 2. LearnDash Enrollment Safety Net
		if ( ! empty( $course_ids ) ) {
			if ( function_exists( 'ld_update_course_access' ) ) {
				foreach ( $course_ids as $cid ) { ld_update_course_access( $user_id, (int) $cid ); }
			} else {
				jwb_log( array( 'warning' => 'ld_update_course_access not found', 'user_id' => $user_id ) );
			}
		}

		if ( ! empty( $group_ids ) ) {
			if ( function_exists( 'ld_update_group_access' ) ) {
				foreach ( $group_ids as $gid ) { ld_update_group_access( $user_id, (int) $gid ); }
			} else {
				jwb_log( array( 'warning' => 'ld_update_group_access not found', 'user_id' => $user_id ) );
			}
		}

		// 3. Generate Unique 50% Off Student Code
		$coupon_code   = '';
		$template_code = 'jwb_group_template_' . $paired_id;
		$template      = new WC_Coupon( $template_code );

		if ( $template->get_id() ) {
			$usage_limit = 50;
			$coupon_id   = jwb_create_group_coupon( $order_id, $usage_limit, $template );
			
			if ( $coupon_id ) {
				$coupon_code = get_the_title( $coupon_id );
				$order->add_meta_data( '_jwb_mg_coupon_' . $user_id, $coupon_code );
			}
		} else {
			jwb_log( array(
				'warning'    => 'Template coupon not found for Multiple Group — skipping coupon generation',
				'template'   => $template_code,
				'product_id' => $product_id,
				'order_id'   => $order_id,
			) );
		}

		// 4. Trigger Email Notification
		
		$product      = wc_get_product( $product_id );
		$product_name = $product ? $product->get_name() : '';
		$sender_name  = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		if ( $is_new ) {
            jwb_send_account_creation_email( $user_id ); // account creation mail
		}
		
		$target_url = ! empty( $course_ids ) ? get_permalink( reset( $course_ids ) ) : home_url( '/my-account/' );
		
		jwb_send_multiple_group_activation_email( array(
			'email'        => $email,
			'username'     => $username,
			'password'     => $password, 
			'full_name'    => $name,
			'product_name' => $product_name,
			'product_id'   => $product_id,
			'sender_name'  => $sender_name,
			'order_id'     => $order_id,
			'coupon_code'  => $coupon_code,
			'login_url'    => $target_url,
		) );
	}


	$order->update_meta_data( '_jwb_multiple_groups_processed', true );
	$order->save();
}
// ---------------------------------------------------------------------------
// Group Study: generate 50% member coupons
// ---------------------------------------------------------------------------

/**
 * Generate a group member coupon for each group-study product in the order.
 *
 * Each product requires its own template coupon named jwb_group_template_{product_id}.
 * If no template exists for a product, it is skipped and a warning is logged.
 * Idempotent: skips if _jwb_group_coupons_generated is already set on the order.
 */
function jwb_maybe_generate_group_coupon( $order_id, $order ) {
	if ( $order->get_meta( '_jwb_group_coupons_generated' ) ) { return; }

	foreach ( $order->get_items() as $item ) {
		$product_id = $item->get_product_id();
		if ( ! jwb_product_has_category( $product_id, 'group-study' ) ) { continue; }

		$template_code = 'jwb_group_template_' . $product_id;
		$template      = new WC_Coupon( $template_code );

		if ( ! $template->get_id() ) {
			jwb_log( array(
				'warning'    => 'Template coupon not found — skipping product',
				'template'   => $template_code,
				'product_id' => $product_id,
				'order_id'   => $order_id,
			) );
			continue;
		}

		$usage_limit = 50;
		$coupon_id   = jwb_create_group_coupon( $order_id, $usage_limit, $template );
		if ( ! $coupon_id ) { continue; }

		$code = get_the_title( $coupon_id );
		$order->update_meta_data( '_jwb_group_coupon_' . $product_id, $code );
		$order->update_meta_data( '_jwb_group_coupon_id_' . $product_id, $coupon_id );
	}

	$order->update_meta_data( '_jwb_group_coupons_generated', true );
	$order->save();
}

/**
 * Clone a template coupon and return the new coupon post ID.
 *
 * @param int        $order_id
 * @param int        $usage_limit Quantity of group study seats purchased.
 * @param WC_Coupon  $template    The template coupon to clone.
 * @return int|false New coupon post ID, or false on failure.
 */
function jwb_create_group_coupon( $order_id, $usage_limit, $template ) {
	$code = strtoupper( wp_generate_password( 8, false ) );

	$coupon_id = wp_insert_post( array(
		'post_title'   => $code,
		'post_status'  => 'publish',
		'post_type'    => 'shop_coupon',
		'post_excerpt' => 'Order ID: ' . $order_id,
	) );

	if ( is_wp_error( $coupon_id ) ) {
		jwb_log( array( 'error' => 'Failed to create group coupon', 'order_id' => $order_id, 'message' => $coupon_id->get_error_message() ) );
		return false;
	}

	update_post_meta( $coupon_id, 'discount_type',              $template->get_discount_type() );
	update_post_meta( $coupon_id, 'coupon_amount',              $template->get_amount() );
	update_post_meta( $coupon_id, 'usage_limit',                $usage_limit );
	update_post_meta( $coupon_id, 'individual_use',             $template->get_individual_use() ? 'yes' : 'no' );
	update_post_meta( $coupon_id, 'product_ids',                get_post_meta( $template->get_id(), 'product_ids', true ) );
	update_post_meta( $coupon_id, 'exclude_product_ids',        get_post_meta( $template->get_id(), 'exclude_product_ids', true ) );
	update_post_meta( $coupon_id, 'product_categories',         $template->get_product_categories() );
	update_post_meta( $coupon_id, 'exclude_product_categories', $template->get_excluded_product_categories() );
	update_post_meta( $coupon_id, 'minimum_amount',             $template->get_minimum_amount() );
	update_post_meta( $coupon_id, 'free_shipping',              $template->get_free_shipping() ? 'yes' : 'no' );
	update_post_meta( $coupon_id, 'exclude_sale_items',         $template->get_exclude_sale_items() ? 'yes' : 'no' );
	update_post_meta( $coupon_id, 'date_expires',               strtotime( '+2 years' ) );
	update_post_meta( $coupon_id, 'apply_before_tax',           'yes' );
	update_post_meta( $coupon_id, '_jwb_coupon_type',           'group_member' );
	update_post_meta( $coupon_id, '_jwb_source_order',          $order_id );

	return $coupon_id;
}

/**
 * Return all generated group coupon codes for an order, each labeled with its product name.
 *
 * @param WC_Order $order
 * @return array[] Array of [ 'code' => string, 'product_name' => string ]
 */
function jwb_get_order_group_coupons( $order ) {
	$coupons = array();
	foreach ( $order->get_items() as $item ) {
		$product_id = $item->get_product_id();
		if ( ! jwb_product_has_category( $product_id, 'group-study' ) ) { continue; }
		$code = $order->get_meta( '_jwb_group_coupon_' . $product_id );
		if ( ! $code ) { continue; }
		$coupons[] = array(
			'code'         => $code,
			'product_name' => $item->get_name(),
		);
	}
	return $coupons;
}

// ---------------------------------------------------------------------------
// Group Study: coupon display on thank-you page
// ---------------------------------------------------------------------------

add_action( 'woocommerce_order_details_before_order_table', 'jwb_thankyou_group_coupon', 10, 1 );

function jwb_thankyou_group_coupon( $order ) {
	$coupons = jwb_get_order_group_coupons( $order );
	if ( empty( $coupons ) ) { return; }

	?>
	<div class="jwb-group-coupon-block">
		<h2><?php esc_html_e( 'Group Member Discount Codes', 'jwb-checkout' ); ?></h2>
		<p><?php esc_html_e( 'Share the code below with your group members. They can use it to receive 50% off any individual JWB course or study session.', 'jwb-checkout' ); ?></p>
		<?php foreach ( $coupons as $coupon ) : ?>
		<div class="jwb-coupon-row">
			<h4><?php echo esc_html( $coupon['product_name'] ); ?></h4>
			<div class="jwb-coupon-code-display">
				<span class="jwb-coupon-code" id="jwb-coupon-code-<?php echo esc_attr( $coupon['code'] ); ?>"><?php echo esc_html( $coupon['code'] ); ?></span>
				<button type="button" class="jwb-copy-btn button" data-code="<?php echo esc_attr( $coupon['code'] ); ?>">
					<?php esc_html_e( 'Copy', 'jwb-checkout' ); ?>
				</button>
			</div>
		</div>
		<?php endforeach; ?>
	</div>
	<?php
}

// ---------------------------------------------------------------------------
// Group Study: coupon display in order confirmation email
// ---------------------------------------------------------------------------

add_action( 'woocommerce_email_order_details', 'jwb_email_group_coupon', 10, 4 );

function jwb_email_group_coupon( $order, $sent_to_admin, $plain_text, $email ) {
	$coupons = jwb_get_order_group_coupons( $order );
	if ( empty( $coupons ) ) { return; }

	if ( $plain_text ) {
		echo "\n" . esc_html__( 'Group Member Discount Codes', 'jwb-checkout' ) . "\n";
		echo esc_html__( 'Share these discount codes with your group members for 50% off individual JWB courses or study sessions:', 'jwb-checkout' ) . "\n";
		foreach ( $coupons as $coupon ) {
			echo $coupon['product_name'] . ': ' . $coupon['code'] . "\n";
		}
		echo "\n";
		return;
	}

	?>
	<table cellspacing="0" cellpadding="6" style="width:100%;border:1px solid #e5e5e5;margin-bottom:20px;" border="1">
		<thead>
			<tr>
				<th colspan="2" style="background-color:#f7f7f7;border:1px solid #e5e5e5;padding:12px;text-align:left;">
					<?php esc_html_e( 'Group Member Discount Codes', 'jwb-checkout' ); ?>
				</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td colspan="2" style="border:1px solid #e5e5e5;padding:12px;">
					<?php esc_html_e( 'Share these discount codes with your group members for 50% off individual JWB courses or study sessions:', 'jwb-checkout' ); ?>
				</td>
			</tr>
			<?php foreach ( $coupons as $coupon ) : ?>
			<tr>
				<td style="border:1px solid #e5e5e5;padding:12px;font-weight:bold;">
					<?php echo esc_html( $coupon['product_name'] ); ?>
				</td>
				<td style="border:1px solid #e5e5e5;padding:12px;font-family:monospace;font-size:16px;letter-spacing:2px;">
					<?php echo esc_html( $coupon['code'] ); ?>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

// ---------------------------------------------------------------------------
// Group Study: block group member coupon on group-study-only carts
// ---------------------------------------------------------------------------

add_filter( 'woocommerce_coupon_is_valid', 'jwb_block_group_coupon_on_group_cart', 10, 3 );

function jwb_block_group_coupon_on_group_cart( $valid, $coupon, $discount ) {
	if ( ! $valid ) { return $valid; }

	$coupon_type = get_post_meta( $coupon->get_id(), '_jwb_coupon_type', true );
	if ( 'group_member' !== $coupon_type ) { return $valid; }

	$has_individual = jwb_cart_has_category( 'individual-study' );
	$has_group      = jwb_cart_has_category( 'group-study' );

	if ( $has_group && ! $has_individual ) {
		throw new Exception(
			esc_html__( 'This code is for individual courses or study sessions and cannot be applied to a group study purchase.', 'jwb-checkout' )
		);
	}

	return $valid;
}

// ---------------------------------------------------------------------------
// Gift Course: create recipient accounts and enroll on order completion
// ---------------------------------------------------------------------------

/**
 * Process gift recipients: create WordPress accounts, enroll in LearnDash, send activation emails.
 *
 * Security Upgraded: Generates 24-character hidden passwords and processes array based on 
 * secure cart quantities, ignoring frontend HTML tampering.
 */
function jwb_maybe_process_recipients( $order_id, $order ) {
	if ( $order->get_meta( '_jwb_recipients_processed' ) ) { return; }

	// Grab our securely saved array (which now accurately reflects quantities)
	$recipients = $order->get_meta( '_jwb_recipients' );
	if ( ! is_array( $recipients ) || empty( $recipients ) ) { return; }

	foreach ( $recipients as $recipient ) {
		$email      = sanitize_email( $recipient['email'] );
		$name       = sanitize_text_field( $recipient['name'] );
		$product_id = (int) $recipient['product_id'];

		if ( ! is_email( $email ) ) { continue; }

		$product_type = jwb_get_product_type( $product_id );
		if ( 'gift' !== $product_type ) {
			jwb_log( array(
				'warning'    => 'Recipient product is not of type gift — skipping recipient',
				'product_id' => $product_id,
				'email'      => $email,
			) );
			continue;
		}

		$paired_id = (int) get_post_meta( $product_id, '_jwb_paired_product', true );
		if ( ! $paired_id ) {
			jwb_log( array(
				'warning'    => 'Gift product has no paired product set — skipping recipient',
				'product_id' => $product_id,
				'order_id'   => $order_id,
			) );
			continue;
		}

		$course_ids = array_filter( (array) get_post_meta( $paired_id, '_related_course', true ), 'is_numeric' );
		$group_ids  = array_filter( (array) get_post_meta( $paired_id, '_related_group',  true ), 'is_numeric' );

		if ( empty( $course_ids ) && empty( $group_ids ) ) {
			jwb_log( array(
				'warning'    => 'Paired product has no LearnDash courses or groups mapped — skipping recipient',
				'paired_id'  => $paired_id,
				'product_id' => $product_id,
				'email'      => $email,
			) );
			continue;
		}

		$user_id = email_exists( $email );
		$is_new  = false;
		$username = '';

		if ( ! $user_id ) {
			$is_new  = true;
			$base    = sanitize_user( current( explode( '@', $email ) ), true );
			$username = $base;
			$counter  = 1;
			while ( username_exists( $username ) ) {
				$username = $base . $counter++;
			}

			// SECURITY FIX: 24-character random password. No more plaintext emails.
			$password = wp_generate_password( 12, false );
			$parts    = explode( ' ', trim( $name ), 2 );

			$user_id = wp_insert_user( array(
				'user_login' => $username,
				'user_pass'  => $password,
				'user_email' => $email,
				'first_name' => $parts[0],
				'last_name'  => isset( $parts[1] ) ? $parts[1] : '',
				'role'       => 'subscriber',
			) );
		}

		if ( is_wp_error( $user_id ) ) {
			jwb_log( array(
				'error'   => 'User creation failed',
				'email'   => $email,
				'message' => $user_id->get_error_message(),
			) );
			continue;
		}

		// ---------------------------------------------------------
		// LearnDash Enrollment
		// ---------------------------------------------------------
		if ( ! empty( $course_ids ) ) {
			if ( function_exists( 'ld_update_course_access' ) ) {
				foreach ( $course_ids as $cid ) {
					ld_update_course_access( $user_id, (int) $cid );
				}
			} else {
				jwb_log( array( 'warning' => 'ld_update_course_access not found', 'user_id' => $user_id ) );
			}
		}

		if ( ! empty( $group_ids ) ) {
			if ( function_exists( 'ld_update_group_access' ) ) {
				foreach ( $group_ids as $gid ) {
					ld_update_group_access( $user_id, (int) $gid );
				}
			} else {
				jwb_log( array( 'warning' => 'ld_update_group_access not found', 'user_id' => $user_id ) );
			}
		}

		// ---------------------------------------------------------
		// Trigger Welcome Email
		// ---------------------------------------------------------

		$product      = wc_get_product( $product_id );
		$product_name = $product ? $product->get_name() : '';
		$sender_name  = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
        
		if ( $is_new ) {
            jwb_send_account_creation_email( $user_id ); // account creation mail
		}
		$target_url = ! empty( $course_ids ) ? get_permalink( reset( $course_ids ) ) : home_url( '/my-account/' );
		
		jwb_send_recipient_activation_email( array(
			'email'        => $email,
			'username'     => $username,
			'password'     => $password, 
			'full_name'    => $name,
			'product_name' => $product_name,
			'product_id'   => $product_id,
			'product_type' => $product_type,
			'sender_name'  => $sender_name,
			'order_id'     => $order_id,
			'login_url'    => $target_url,
		) );
	}

	$order->update_meta_data( '_jwb_recipients_processed', true );
	$order->save();
}
