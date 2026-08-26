<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// ---------------------------------------------------------------------------
// Purchase-type toggle
// ---------------------------------------------------------------------------

/**
 * Render the Individual / Group / Gift purchase-type toggle at checkout.
 *
 * Displays tab-style buttons above the customer details section. Only shown when
 * all cart items share the same plugin-managed type (group-study, individual-study,
 * session, or gift). Mixed-type carts do not show the toggle.
 *
 * Context determines which tabs are available:
 *   - Session-level carts → Individual + Gift only.
 *   - Course-level carts  → Individual + Group + Gift.
 *
 * The active button reflects the current cart type and is disabled to prevent
 * re-clicking. Clicking an inactive button fires the jwb_swap_cart AJAX action,
 * then reloads the page so the toggle, recipient fields, and cart totals
 * re-render in the updated state.
 */
add_action( 'woocommerce_checkout_before_customer_details', 'jwb_render_purchase_type_toggle' );

/**
 * Render the Individual / Gift / Group purchase-type toggle at checkout.
 */
function jwb_render_purchase_type_toggle() {
	if ( ! WC()->cart || WC()->cart->is_empty() ) { return; }

	// Checkout protection: a bundled cart (2+ distinct studies) is locked into the
	// standard flow. Hide the purchase-type tabs so it can't be swapped into a
	// Gift / Multiple-Group form, which cannot process multiple different studies.
	if ( jwb_count_distinct_studies() >= 2 ) { return; }

	// Collect the plugin-managed type of every cart item.
	$types = array();
	foreach ( WC()->cart->get_cart() as $item ) {
		$type = jwb_get_product_type( $item['data']->get_id() );
		if ( $type ) {
			$types[] = $type;
		}
	}

	$unique_types = array_unique( $types );
	if ( empty( $unique_types ) ) { return; }

	// -----------------------------------------------------------------------
	// Determine the Active Tab Mode (Group Flow vs Individual Flow)
	// -----------------------------------------------------------------------
	$is_group_mode = in_array( 'group-study', $unique_types ) || in_array( 'multiple-group', $unique_types );

	if ( $is_group_mode ) {
		// Group Tabs
		$active_key = in_array( 'multiple-group', $unique_types ) ? 'multiple-group' : 'group';
		$buttons = array(
			array( 'direction' => 'to_group',          'key' => 'group',          'label' => __( 'Group Purchase', 'jwb-checkout' ) ),
			array( 'direction' => 'to_multiple_group', 'key' => 'multiple-group', 'label' => __( 'Multiple Groups', 'jwb-checkout' ) ),
		);
	} else {
		// Individual Tabs
		$active_key = in_array( 'gift', $unique_types ) ? 'gift' : 'individual';
		$buttons = array(
			array( 'direction' => 'to_individual', 'key' => 'individual', 'label' => __( 'Individual Purchase', 'jwb-checkout' ) ),
			array( 'direction' => 'to_gift',       'key' => 'gift',       'label' => __( 'Gift Purchase', 'jwb-checkout' ) ),
		);
	}

	// -----------------------------------------------------------------------
	// Render.
	// -----------------------------------------------------------------------
	echo '<div class="jwb-purchase-type-toggle" style="margin-bottom: 20px;">';
	foreach ( $buttons as $btn ) {
		$is_active = ( $btn['key'] === $active_key );
		printf(
			'<button type="button" class="jwb-toggle-btn button-%s%s" data-direction="%s"%s>%s</button>',
			esc_attr( $btn['key'] ),
			$is_active ? ' is-active' : '',
			esc_attr( $btn['direction'] ),
			$is_active ? ' disabled' : '',
			esc_html( $btn['label'] )
		);
	}
	echo '</div>';
}

// ---------------------------------------------------------------------------
// Render Multiple Group Recipient Fields
// ---------------------------------------------------------------------------
add_action( 'woocommerce_after_checkout_billing_form', 'jwb_render_multiple_group_fields' );

function jwb_render_multiple_group_fields( $checkout ) {
	if ( ! jwb_cart_has_category( 'multiple-group' ) ) { return; }

	$mg_slots = array();

	foreach ( WC()->cart->get_cart() as $cart_item_key => $item ) {
		$pid = $item['data']->get_id();
		if ( jwb_product_has_category( $pid, 'multiple-group' ) ) {
			$qty = $item['quantity'];
			for ( $q = 0; $q < $qty; $q++ ) {
				$mg_slots[] = array(
					'product_id'    => $pid,
					'name'          => $item['data']->get_name(),
					'cart_item_key' => $cart_item_key
				);
			}
		}
	}

	$count = count( $mg_slots );
	if ( $count < 1 ) { return; }

	$mg_product_id = $mg_slots[0]['product_id'];
	$add_url = add_query_arg( 'jwb-add-multiple-group', $mg_product_id, wc_get_checkout_url() );

	echo '<div class="jwb-recipient-fields-wrapper">';
	
	echo '<div class="jwb-recipient-header-wrap" style="margin-bottom: 20px;">';
	echo '<h3 style="margin: 0 0 12px 0;">' . esc_html__( 'MULTIPLE GROUPS', 'jwb-checkout' ) . '</h3>';
	echo '<a href="' . esc_url( $add_url ) . '" class="button jwb-add-recipients-btn" style="text-transform: uppercase; text-decoration: none; display: inline-block;">+ ' . esc_html__( 'ADD RECIPIENTS', 'jwb-checkout' ) . '</a>';
	echo '</div>';
	
	echo '<p>' . esc_html__( 'Enter the name and email for each additional group leader. They will receive an email to set up their account and a 50% off Student Code to distribute.', 'jwb-checkout' ) . '</p>';

	echo '<div class="jwb-recipient-fields-inner">';

	for ( $i = 0; $i < $count; $i++ ) {
		$item_name     = $mg_slots[ $i ]['name'];
		$cart_item_key = $mg_slots[ $i ]['cart_item_key'];
		$remove_url    = add_query_arg( 'jwb-remove-item', $cart_item_key, wc_get_checkout_url() );

		echo '<div class="jwb-recipient-row" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">';
		echo '<h4 style="display: flex; align-items: center; justify-content: space-between;">';
		echo '<span>' . sprintf( esc_html__( '%d. Leader for: %s', 'jwb-checkout' ), $i + 1, esc_html( $item_name ) ) . '</span>';
		echo '<a href="' . esc_url( $remove_url ) . '" style="color: #d22828; font-size: 13px; text-decoration: none; border: 1px solid #d22828; padding: 2px 8px; border-radius: 3px; white-space: nowrap;" title="Remove Leader">&times; Remove</a>';
		echo '</h4>';

		woocommerce_form_field( '_jwb_multiple_group_name_' . $i, array(
			'type'     => 'text',
			'class'    => array( 'form-row-first' ),
			'label'    => __( 'Leader Name', 'jwb-checkout' ),
			'required' => true,
		), $checkout->get_value( '_jwb_multiple_group_name_' . $i ) );

		woocommerce_form_field( '_jwb_multiple_group_email_' . $i, array(
			'type'     => 'email',
			'class'    => array( 'form-row-last' ),
			'label'    => __( 'Leader Email', 'jwb-checkout' ),
			'required' => true,
		), $checkout->get_value( '_jwb_multiple_group_email_' . $i ) );

		echo '</div>';
	}
	
	echo '</div></div>';
}

// ---------------------------------------------------------------------------
// Render Base Group Summary & Remove Button
// ---------------------------------------------------------------------------
add_action( 'woocommerce_after_checkout_billing_form', 'jwb_render_group_purchase_section', 5 );

function jwb_render_group_purchase_section( $checkout ) {
	$group_items = array();

	foreach ( WC()->cart->get_cart() as $cart_item_key => $item ) {
		$pid = $item['data']->get_id();
		if ( 'group-study' === jwb_get_product_type( $pid ) ) {
			$group_items[] = array(
				'name'          => $item['data']->get_name(),
				'cart_item_key' => $cart_item_key
			);
		}
	}

	if ( empty( $group_items ) ) { return; }

	echo '<div class="jwb-recipient-fields-wrapper jwb-group-fields-wrapper" style="margin-bottom: 30px;">';
	
	echo '<div class="jwb-recipient-header-wrap" style="margin-bottom: 20px;">';
	echo '<h3 style="margin: 0 0 12px 0;">' . esc_html__( 'GROUP PURCHASE', 'jwb-checkout' ) . '</h3>';
	echo '</div>';
	
	echo '<p>' . esc_html__( 'This group course will be added to your personal account as the Group Leader.', 'jwb-checkout' ) . '</p>';
	echo '<div class="jwb-recipient-fields-inner">';

	$count = count( $group_items );
	for ( $i = 0; $i < $count; $i++ ) {
		$g_item = $group_items[$i];
		$remove_url = add_query_arg( 'jwb-remove-item', $g_item['cart_item_key'], wc_get_checkout_url() );

		echo '<div class="jwb-recipient-row" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">';
		echo '<h4 style="display: flex; align-items: center; justify-content: space-between;">';
		echo '<span>' . sprintf( esc_html__( '%d. Course: %s', 'jwb-checkout' ), $i + 1, esc_html( $g_item['name'] ) ) . '</span>';
		echo '<a href="' . esc_url( $remove_url ) . '" style="color: #d22828; font-size: 13px; text-decoration: none; border: 1px solid #d22828; padding: 2px 8px; border-radius: 3px; white-space: nowrap;" title="Remove Course">&times; Remove</a>';
		echo '</h4>';
		echo '</div>';
	}
	
	echo '</div></div>';
}

// ---------------------------------------------------------------------------
// Render Individual Course Summary & Remove Button
// ---------------------------------------------------------------------------
add_action( 'woocommerce_after_checkout_billing_form', 'jwb_render_individual_purchase_section', 5 );

function jwb_render_individual_purchase_section( $checkout ) {
	$ind_items = array();

	// Check the cart for any Individual courses or Sessions
	foreach ( WC()->cart->get_cart() as $cart_item_key => $item ) {
		$pid = $item['data']->get_id();
		$type = jwb_get_product_type( $pid );
		
		if ( in_array( $type, array( 'individual-study', 'session' ) ) ) {
			$ind_items[] = array(
				'name'          => $item['data']->get_name(),
				'cart_item_key' => $cart_item_key
			);
		}
	}

	if ( empty( $ind_items ) ) { return; }

	// REUSING THE GIFT CSS CLASSES: 'jwb-recipient-fields-wrapper' will pull the exact same background/box styles!
	echo '<div class="jwb-recipient-fields-wrapper jwb-individual-fields-wrapper" style="margin-bottom: 30px;">';
	
	// UI Header
	echo '<div class="jwb-recipient-header-wrap" style="margin-bottom: 20px;">';
	echo '<h3 style="margin: 0 0 12px 0;">' . esc_html__( 'INDIVIDUAL PURCHASE', 'jwb-checkout' ) . '</h3>';
	echo '</div>';
	
	echo '<p>' . esc_html__( 'This course will be added to your personal account.', 'jwb-checkout' ) . '</p>';

	echo '<div class="jwb-recipient-fields-inner">';

	// Render the rows with the remove button
	$count = count( $ind_items );
	for ( $i = 0; $i < $count; $i++ ) {
		$ind_item = $ind_items[$i];
		$remove_url = add_query_arg( 'jwb-remove-item', $ind_item['cart_item_key'], wc_get_checkout_url() );

		// REUSING ROW CSS: 'jwb-recipient-row' ensures the borders and padding match the gift inputs perfectly.
		echo '<div class="jwb-recipient-row" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">';
		
		// Styled exactly like the Gift header
		echo '<h4 style="display: flex; align-items: center; justify-content: space-between;">';
		echo '<span>' . sprintf( esc_html__( '%d. Course: %s', 'jwb-checkout' ), $i + 1, esc_html( $ind_item['name'] ) ) . '</span>';
		echo '<a href="' . esc_url( $remove_url ) . '" style="color: #d22828; font-size: 13px; text-decoration: none; border: 1px solid #d22828; padding: 2px 8px; border-radius: 3px; white-space: nowrap;" title="Remove Course">&times; Remove</a>';
		echo '</h4>';
		
		echo '</div>';
	}
	
	echo '</div>'; // End inner
	echo '</div>'; // End wrapper
}

// ---------------------------------------------------------------------------
// Recipient fields at checkout
// ---------------------------------------------------------------------------

/**
 * Render name and email fields for each gift item in the cart.
 *
 * Only shown when the cart contains one or more gift-category products.
 * The buyer is never enrolled in gift courses — each recipient receives their
 * own account and course access on order completion.
 */
add_action( 'woocommerce_after_checkout_billing_form', 'jwb_render_recipient_fields' );

/**
 * Render name and email fields for each gift item based on QUANTITY.
 */
function jwb_render_recipient_fields( $checkout ) {
	if ( ! jwb_cart_has_category( 'gift' ) ) { return; }

	$gift_slots = array();

	// LOOP 1: Flatten the cart by quantity and grab the Cart Item Key
	foreach ( WC()->cart->get_cart() as $cart_item_key => $item ) {
		$pid = $item['data']->get_id();
		if ( jwb_product_has_category( $pid, 'gift' ) ) {
			$qty = $item['quantity'];
			for ( $q = 0; $q < $qty; $q++ ) {
				$gift_slots[] = array(
					'product_id'    => $pid,
					'name'          => $item['data']->get_name(),
					'cart_item_key' => $cart_item_key // We need this to remove the item later!
				);
			}
		}
	}

	$count = count( $gift_slots );
	if ( $count < 1 ) { return; }

	// Grab the product ID of the first gift in the cart for the Add button
	$gift_product_id = $gift_slots[0]['product_id'];
	$add_url = add_query_arg( 'jwb-add-gift', $gift_product_id, wc_get_checkout_url() );

	echo '<div class="jwb-recipient-fields-wrapper">';
	
	// UPDATED UI: Stacked Header and Button (No more flexbox, much cleaner)
	echo '<div class="jwb-recipient-header-wrap" style="margin-bottom: 20px;">';
	echo '<h3 style="margin: 0 0 12px 0;">' . esc_html__( 'GIFT PURCHASE', 'jwb-checkout' ) . '</h3>';
	echo '<a href="' . esc_url( $add_url ) . '" class="button jwb-add-recipients-btn" style="text-transform: uppercase; text-decoration: none; display: inline-block;">+ ' . esc_html__( 'ADD RECIPIENTS', 'jwb-checkout' ) . '</a>';
	echo '</div>';
	
	echo '<p>' . esc_html__( 'Enter the name and email for each gift course recipient. Each recipient will receive an email to set up their account and access their course.', 'jwb-checkout' ) . '</p>';

	echo '<div class="jwb-recipient-fields-inner">';

	for ( $i = 0; $i < $count; $i++ ) {
		$item_name     = $gift_slots[ $i ]['name'];
		$cart_item_key = $gift_slots[ $i ]['cart_item_key'];
		
		// Generate the Remove URL
		$remove_url = add_query_arg( 'jwb-remove-item', $cart_item_key, wc_get_checkout_url() );

		echo '<div class="jwb-recipient-row" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">';
		
		// UPDATED HEADING: Removed License #, Added the [-] Remove Button
		echo '<h4 style="display: flex; align-items: center; justify-content: space-between;">';
        echo '<span>' . sprintf( esc_html__( '%d. Recipient for: %s', 'jwb-checkout' ), $i + 1, esc_html( $item_name ) ) . '</span>';
        echo '<a href="' . esc_url( $remove_url ) . '" style="color: #d22828; font-size: 13px; text-decoration: none; border: 1px solid #d22828; padding: 2px 8px; border-radius: 3px; white-space: nowrap;" title="Remove Recipient">&times; Remove</a>';
		echo '</h4>';

		woocommerce_form_field( '_jwb_recipient_name_' . $i, array(
			'type'     => 'text',
			'class'    => array( 'form-row-first' ),
			'label'    => __( 'Recipient Name', 'jwb-checkout' ),
			'required' => true,
		), $checkout->get_value( '_jwb_recipient_name_' . $i ) );

		woocommerce_form_field( '_jwb_recipient_email_' . $i, array(
			'type'     => 'email',
			'class'    => array( 'form-row-last' ),
			'label'    => __( 'Recipient Email', 'jwb-checkout' ),
			'required' => true,
		), $checkout->get_value( '_jwb_recipient_email_' . $i ) );

		echo '</div>';
	}
	
	echo '</div>'; // End fields inner
	echo '</div>'; // End wrapper
}

/**
 * Validate recipient fields securely based on actual cart quantities.
 */
add_action( 'woocommerce_checkout_process', 'jwb_validate_recipient_fields' );

function jwb_validate_recipient_fields() {
	// Security Fix: Do not trust a hidden $_POST count. Count the actual cart instead.
	$required_slots = 0;
	foreach ( WC()->cart->get_cart() as $item ) {
		if ( jwb_product_has_category( $item['data']->get_id(), 'gift' ) ) {
			$required_slots += $item['quantity'];
		}
	}

	if ( $required_slots < 1 ) { return; }

	$billing_email = isset( $_POST['billing_email'] ) ? sanitize_email( strtolower( trim( $_POST['billing_email'] ) ) ) : '';
	$seen_emails   = array();

	// Loop strictly based on the required slots calculated from the server-side cart
	for ( $i = 0; $i < $required_slots; $i++ ) {
		$name  = isset( $_POST[ '_jwb_recipient_name_' . $i ] ) ? sanitize_text_field( $_POST[ '_jwb_recipient_name_' . $i ] ) : '';
		$email = isset( $_POST[ '_jwb_recipient_email_' . $i ] ) ? sanitize_email( strtolower( trim( $_POST[ '_jwb_recipient_email_' . $i ] ) ) ) : '';

		if ( empty( $name ) ) {
			wc_add_notice( sprintf( __( 'Please enter a name for recipient #%d.', 'jwb-checkout' ), $i + 1 ), 'error' );
		}

		if ( empty( $email ) || ! is_email( $email ) ) {
			wc_add_notice( sprintf( __( 'Please enter a valid email address for recipient #%d.', 'jwb-checkout' ), $i + 1 ), 'error' );
			continue;
		}

		if ( $email === $billing_email ) {
			wc_add_notice( sprintf( __( 'Recipient #%d email cannot be the same as your billing email.', 'jwb-checkout' ), $i + 1 ), 'error' );
			continue;
		}

		if ( in_array( $email, $seen_emails, true ) ) {
			wc_add_notice( sprintf( __( 'Recipient #%d email is a duplicate. Each recipient must have a unique email.', 'jwb-checkout' ), $i + 1 ), 'error' );
			continue;
		}

		$seen_emails[] = $email;
	}
}

/**
 * Save recipient fields securely to order meta.
 */
add_action( 'woocommerce_checkout_order_created', 'jwb_save_recipient_fields' );

function jwb_save_recipient_fields( $order ) {
	// Security Fix: Map fields directly to the paid Order Items, ignoring hidden HTML fields.
	$valid_gift_slots = array();
	
	// Get all gift items actually paid for in this order
	foreach ( $order->get_items() as $item_id => $item ) {
		$product_id = $item->get_product_id();
		if ( jwb_product_has_category( $product_id, 'gift' ) ) {
			$qty = $item->get_quantity();
			// Flatten by quantity
			for ( $q = 0; $q < $qty; $q++ ) {
				$valid_gift_slots[] = array(
					'product_id' => $product_id,
					'item_id'    => $item_id
				);
			}
		}
	}

	$max_slots = count( $valid_gift_slots );
	if ( $max_slots < 1 ) { return; }

	$recipients = array();

	// Safely map the POSTed emails strictly to the slots the buyer paid for
	for ( $i = 0; $i < $max_slots; $i++ ) {
		$name  = sanitize_text_field( $_POST[ '_jwb_recipient_name_' . $i ] ?? '' );
		$email = sanitize_email( strtolower( trim( $_POST[ '_jwb_recipient_email_' . $i ] ?? '' ) ) );

		if ( ! empty( $email ) ) {
			$recipients[] = array(
				'name'          => $name,
				'email'         => $email,
				'product_id'    => $valid_gift_slots[$i]['product_id'],
				'order_item_id' => $valid_gift_slots[$i]['item_id'], // Keep track of the specific line item
			);
		}
	}

	if ( ! empty( $recipients ) ) {
		$order->update_meta_data( '_jwb_recipients', $recipients );
		$order->save();
	}
}

// ---------------------------------------------------------------------------
// Multiple Group: Validation & Save
// ---------------------------------------------------------------------------
add_action( 'woocommerce_checkout_process', 'jwb_validate_multiple_group_fields' );

function jwb_validate_multiple_group_fields() {
	$required_slots = 0;
	foreach ( WC()->cart->get_cart() as $item ) {
		if ( jwb_product_has_category( $item['data']->get_id(), 'multiple-group' ) ) {
			$required_slots += $item['quantity'];
		}
	}

	if ( $required_slots < 1 ) { return; }

	$billing_email = isset( $_POST['billing_email'] ) ? sanitize_email( strtolower( trim( $_POST['billing_email'] ) ) ) : '';
	$seen_emails   = array();

	for ( $i = 0; $i < $required_slots; $i++ ) {
		$name  = isset( $_POST[ '_jwb_multiple_group_name_' . $i ] ) ? sanitize_text_field( $_POST[ '_jwb_multiple_group_name_' . $i ] ) : '';
		$email = isset( $_POST[ '_jwb_multiple_group_email_' . $i ] ) ? sanitize_email( strtolower( trim( $_POST[ '_jwb_multiple_group_email_' . $i ] ) ) ) : '';

		if ( empty( $name ) ) {
			wc_add_notice( sprintf( __( 'Please enter a name for leader #%d.', 'jwb-checkout' ), $i + 1 ), 'error' );
		}

		if ( empty( $email ) || ! is_email( $email ) ) {
			wc_add_notice( sprintf( __( 'Please enter a valid email address for leader #%d.', 'jwb-checkout' ), $i + 1 ), 'error' );
			continue;
		}

		if ( $email === $billing_email ) {
			wc_add_notice( sprintf( __( 'Leader #%d email cannot be the same as your billing email.', 'jwb-checkout' ), $i + 1 ), 'error' );
			continue;
		}

		if ( in_array( $email, $seen_emails, true ) ) {
			wc_add_notice( sprintf( __( 'Leader #%d email is a duplicate. Each leader must have a unique email.', 'jwb-checkout' ), $i + 1 ), 'error' );
			continue;
		}
		$seen_emails[] = $email;
	}
}
/**
 * ---------------------------------------------------------------------------
 * SMART CART ISOLATION
 * ---------------------------------------------------------------------------
 * Two independent rules are enforced on add-to-cart. Either one, when tripped,
 * empties the cart before the incoming item is added (so the buyer is left with
 * just the product they clicked).
 *
 * Rule 1 — Lane isolation (standard bundling):
 *   Lane A: Group / Multiple Group
 *   Lane B: Individual / Gift
 *   Lane C: Everything Else (Sessions, Books, Standard Products)
 *   Items from different lanes cannot coexist. Standard studies within the same
 *   lane bundle freely (e.g. two different Individual studies).
 *
 * Rule 2 — Complex single-study quarantine:
 *   A "complex" product (Gift or Multiple Group) may never share the cart with a
 *   different base study. This is symmetric: it trips whether the complex product
 *   is the item being added, or is already sitting in the cart when a different
 *   study is added. Effectively, any cart containing a complex product must be a
 *   single study; a bundled cart (2+ studies) can never hold a complex product.
 */
add_filter( 'woocommerce_add_to_cart_validation', 'jwb_smart_auto_clear_cart_3_lanes', 10, 3 );

function jwb_smart_auto_clear_cart_3_lanes( $passed, $product_id, $quantity ) {
	if ( ! WC()->cart || WC()->cart->is_empty() ) { return $passed; }

	// -----------------------------------------------------------------------
	// Rule 1 — Lane isolation.
	// -----------------------------------------------------------------------
	// What lane is the user entering?
	$adding_group = jwb_product_has_category( $product_id, 'group-study' ) || jwb_product_has_category( $product_id, 'multiple-group' );
	$adding_ind   = jwb_product_has_category( $product_id, 'individual-study' ) || jwb_product_has_category( $product_id, 'gift' );
	$adding_other = ! $adding_group && ! $adding_ind;

	// What is already inside the cart?
	$cart_has_group = jwb_cart_has_category( 'group-study' ) || jwb_cart_has_category( 'multiple-group' );
	$cart_has_ind   = jwb_cart_has_category( 'individual-study' ) || jwb_cart_has_category( 'gift' );

	$cart_has_other = false;
	foreach ( WC()->cart->get_cart() as $item ) {
		$pid = $item['data']->get_id();
		if ( ! jwb_product_has_category( $pid, 'group-study' ) &&
		     ! jwb_product_has_category( $pid, 'multiple-group' ) &&
		     ! jwb_product_has_category( $pid, 'individual-study' ) &&
		     ! jwb_product_has_category( $pid, 'gift' ) ) {
			$cart_has_other = true;
			break;
		}
	}

	$cross_lane = false;
	if ( $adding_group && ( $cart_has_ind || $cart_has_other ) ) {
		$cross_lane = true; // Block Group mixing with Ind or Other
	} elseif ( $adding_ind && ( $cart_has_group || $cart_has_other ) ) {
		$cross_lane = true; // Block Ind mixing with Group or Other
	} elseif ( $adding_other && ( $cart_has_group || $cart_has_ind ) ) {
		$cross_lane = true; // Block Other mixing with Group or Ind
	}

	// -----------------------------------------------------------------------
	// Rule 2 — Complex single-study quarantine (symmetric).
	// -----------------------------------------------------------------------
	// Determine the study set and complex-presence the cart WOULD have after this add.
	$adding_complex     = jwb_is_complex_product( $product_id );
	$cart_has_complex   = jwb_cart_has_category( 'gift' ) || jwb_cart_has_category( 'multiple-group' );
	$result_has_complex = $adding_complex || $cart_has_complex;

	$result_studies = jwb_get_cart_study_slugs();
	if ( jwb_get_product_type( $product_id ) ) {
		$incoming_slug = jwb_get_product_base_slug( $product_id );
		if ( '' !== $incoming_slug ) {
			$result_studies[] = $incoming_slug;
		}
	}
	$result_is_bundled = count( array_unique( $result_studies ) ) >= 2;

	// A complex product may not coexist with a second study.
	$complex_violation = ( $result_has_complex && $result_is_bundled );

	// -----------------------------------------------------------------------
	// Auto-clear if either rule trips. Return $passed so the incoming item is
	// then added to the freshly emptied cart.
	// -----------------------------------------------------------------------
	if ( $cross_lane || $complex_violation ) {
		WC()->cart->empty_cart();
		wc_add_notice( __( 'Your previous items have been removed from your cart.', 'jwb-checkout' ), 'notice' );
		return $passed;
	}

	return $passed;
}

add_action( 'woocommerce_checkout_order_created', 'jwb_save_multiple_group_fields' );

function jwb_save_multiple_group_fields( $order ) {
	$valid_mg_slots = array();
	
	foreach ( $order->get_items() as $item_id => $item ) {
		$product_id = $item->get_product_id();
		if ( jwb_product_has_category( $product_id, 'multiple-group' ) ) {
			$qty = $item->get_quantity();
			for ( $q = 0; $q < $qty; $q++ ) {
				$valid_mg_slots[] = array( 'product_id' => $product_id, 'item_id' => $item_id );
			}
		}
	}

	$max_slots = count( $valid_mg_slots );
	if ( $max_slots < 1 ) { return; }

	$recipients = array();
	for ( $i = 0; $i < $max_slots; $i++ ) {
		$name  = sanitize_text_field( $_POST[ '_jwb_multiple_group_name_' . $i ] ?? '' );
		$email = sanitize_email( strtolower( trim( $_POST[ '_jwb_multiple_group_email_' . $i ] ?? '' ) ) );

		if ( ! empty( $email ) ) {
			$recipients[] = array(
				'name'          => $name,
				'email'         => $email,
				'product_id'    => $valid_mg_slots[$i]['product_id'],
				'order_item_id' => $valid_mg_slots[$i]['item_id'], 
			);
		}
	}

	if ( ! empty( $recipients ) ) {
		$order->update_meta_data( '_jwb_multiple_group_recipients', $recipients );
		$order->save();
	}
}
