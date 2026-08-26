<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Check if a specific product ID belongs to a given WooCommerce category slug.
 *
 * @param int    $product_id
 * @param string $cat Category slug.
 * @return bool
 */
function jwb_product_has_category( $product_id, $cat ) {
	if ( ! $product_id || ! $cat ) { return false; }
	$terms = get_the_terms( $product_id, 'product_cat' );
	if ( ! $terms || is_wp_error( $terms ) ) { return false; }
	foreach ( $terms as $term ) {
		if ( $term->slug === $cat ) { return true; }
	}
	return false;
}

/**
 * Check if the current WooCommerce cart contains at least one item in a given category slug.
 *
 * @param string $cat Category slug.
 * @return bool
 */
function jwb_cart_has_category( $cat ) {
	if ( ! WC()->cart || WC()->cart->is_empty() ) { return false; }
	foreach ( WC()->cart->get_cart() as $item ) {
		if ( jwb_product_has_category( $item['data']->get_id(), $cat ) ) { return true; }
	}
	return false;
}

/**
 * Return the plugin-managed type of a product: 'group-study', 'individual-study',
 * 'session', 'gift', 'multiple-group', or false if the product belongs to none of these categories.
 *
 * @param int $product_id
 * @return string|false
 */
function jwb_get_product_type( $product_id ) {
    static $managed = array( 'multiple-group', 'group-study', 'individual-study', 'session', 'gift' );
    
    foreach ( $managed as $type ) {
        if ( jwb_product_has_category( $product_id, $type ) ) {
            return $type;
        }
    }
    return false;
}

/**
 * Find a sibling product of a given type using the shared _jwb_course_base_slug meta field.
 *
 * @param int    $product_id  Source product.
 * @param string $target_type One of 'group-study', 'individual-study', 'session', 'gift'.
 * @return int|false Target product ID, or false if not found.
 */
function jwb_get_sibling_id( $product_id, $target_type ) {
	$base_slug = get_post_meta( (int) $product_id, '_jwb_course_base_slug', true );
	if ( empty( $base_slug ) ) { return false; }

	$query = new WP_Query( array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'post__not_in'   => array( (int) $product_id ),
		'tax_query'      => array(
			array(
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => $target_type,
			),
		),
		'meta_query'     => array(
			array(
				'key'   => '_jwb_course_base_slug',
				'value' => $base_slug,
			),
		),
	) );

	if ( $query->have_posts() ) {
		return (int) $query->posts[0];
	}

	return false;
}

/**
 * Return the course level of a gift product by reading its Paired Product field.
 *
 * @param int $gift_product_id
 * @return string|false 'individual-study', 'session', or false.
 */
function jwb_get_gift_level( $gift_product_id ) {
	$paired_id = (int) get_post_meta( (int) $gift_product_id, '_jwb_paired_product', true );
	if ( ! $paired_id ) { return false; }
	return jwb_get_product_type( $paired_id );
}

/**
 * Find the gift product that has the given product set as its Paired Product.
 *
 * @param int $product_id Source product (individual-study, group-study, or session).
 * @return int|false Gift product ID, or false if not found.
 */
function jwb_get_gift_sibling_id( $product_id ) {
	$query = new WP_Query( array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'tax_query'      => array( array(
			'taxonomy' => 'product_cat',
			'field'    => 'slug',
			'terms'    => 'gift',
		) ),
		'meta_query'     => array( array(
			'key'   => '_jwb_paired_product',
			'value' => (int) $product_id,
		) ),
	) );
	return $query->have_posts() ? (int) $query->posts[0] : false;
}

// ---------------------------------------------------------------------------
// Helper: Get Multiple Group Sibling ID
// ---------------------------------------------------------------------------
function jwb_get_multiple_group_sibling_id( $parent_id ) {
	$args = array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'tax_query'      => array(
			array(
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => 'multiple-group',
			),
		),
		'meta_query'     => array(
			array(
				'key'   => '_jwb_paired_product',
				'value' => $parent_id,
			),
		),
		'fields'         => 'ids',
	);
	$query = new WP_Query( $args );
	return ! empty( $query->posts ) ? $query->posts[0] : false;
}

/**
 * Count total plugin-eligible items in the cart (all four managed types).
 *
 * @return int
 */
function jwb_count_managed_cart_items() {
	if ( ! WC()->cart || WC()->cart->is_empty() ) { return 0; }
	$count = 0;
	foreach ( WC()->cart->get_cart() as $item ) {
		if ( jwb_get_product_type( $item['data']->get_id() ) ) {
			$count++;
		}
	}
	return $count;
}

/**
 * Return the course base slug for a product, or '' if none is set.
 *
 * The base slug (_jwb_course_base_slug) is the identity of a "study" — every
 * sibling product of the same course (individual, group, session, gift,
 * multiple-group) shares it.
 *
 * @param int $product_id
 * @return string
 */
function jwb_get_product_base_slug( $product_id ) {
	return (string) get_post_meta( (int) $product_id, '_jwb_course_base_slug', true );
}

/**
 * Return the distinct base slugs (studies) of all managed items in the cart.
 *
 * Unmanaged products and managed products with no base slug are ignored, so a
 * misconfigured product never inflates the count.
 *
 * @return string[] Unique base slugs (possibly empty).
 */
function jwb_get_cart_study_slugs() {
	$slugs = array();
	if ( ! WC()->cart || WC()->cart->is_empty() ) { return $slugs; }
	foreach ( WC()->cart->get_cart() as $item ) {
		$pid = $item['data']->get_id();
		if ( ! jwb_get_product_type( $pid ) ) { continue; }
		$slug = jwb_get_product_base_slug( $pid );
		if ( '' !== $slug ) { $slugs[] = $slug; }
	}
	return array_values( array_unique( $slugs ) );
}

/**
 * Count the number of distinct studies (base slugs) among managed cart items.
 * A cart with 2 or more is considered "bundled".
 *
 * @return int
 */
function jwb_count_distinct_studies() {
	return count( jwb_get_cart_study_slugs() );
}

/**
 * Whether a product is a "complex" purchase that must be quarantined to a
 * single-study cart: a gift or a multiple-group product.
 *
 * @param int $product_id
 * @return bool
 */
function jwb_is_complex_product( $product_id ) {
	return jwb_product_has_category( $product_id, 'gift' )
		|| jwb_product_has_category( $product_id, 'multiple-group' );
}

/**
 * Log a message to the WooCommerce logger under the jwb-checkout source.
 *
 * @param mixed $data
 */
function jwb_log( $data ) {
	$logger = wc_get_logger();
	$logger->debug( print_r( $data, true ), array( 'source' => 'jwb-checkout' ) );
}
