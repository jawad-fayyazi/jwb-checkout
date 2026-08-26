<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Course Base Slug field — added to the product General tab.
 *
 * A shared identifier that links sibling products (group-study, individual-study,
 * session, gift) belonging to the same course. Must be identical on every sibling.
 *
 * Required for:
 *   - The Individual / Group / Gift checkout toggle (cart swap)
 *   - Resolving which sibling's LearnDash courses to enroll a gift recipient in
 */

add_action( 'woocommerce_product_options_general_product_data', 'jwb_add_course_base_slug_field' );

function jwb_add_course_base_slug_field() {
	woocommerce_wp_text_input( array(
		'id'          => '_jwb_course_base_slug',
		'label'       => __( 'Course Base Slug', 'jwb-checkout' ),
		'description' => __( 'Shared slug that links sibling products (group, individual, session, gift) of the same course. e.g. having-a-mary-heart', 'jwb-checkout' ),
		'desc_tip'    => true,
	) );
}

add_action( 'woocommerce_process_product_meta', 'jwb_save_course_base_slug_field' );

function jwb_save_course_base_slug_field( $post_id ) {
	$value = isset( $_POST['_jwb_course_base_slug'] ) ? sanitize_title( $_POST['_jwb_course_base_slug'] ) : '';
	update_post_meta( $post_id, '_jwb_course_base_slug', $value );
}

/**
 * Paired Product field — added to the product General tab.
 *
 * For gift products only: identifies the individual-study or session product this
 * gift represents. Required for the checkout toggle (to know which product to swap
 * back to) and for recipient enrollment (to read LearnDash course/group mappings).
 */

add_action( 'woocommerce_product_options_general_product_data', 'jwb_add_paired_product_field' );

function jwb_add_paired_product_field() {
	$managed_categories = array( 'group-study', 'individual-study', 'session', 'gift' );

	$query = new WP_Query( array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'orderby'        => 'title',
		'order'          => 'ASC',
		'tax_query'      => array( array(
			'taxonomy' => 'product_cat',
			'field'    => 'slug',
			'terms'    => $managed_categories,
		) ),
		'meta_query'     => array( array(
			'key'     => '_jwb_course_base_slug',
			'value'   => '',
			'compare' => '!=',
		) ),
	) );

	$options = array( '' => '' );
	foreach ( $query->posts as $pid ) {
		$terms = get_the_terms( $pid, 'product_cat' );
		$cat   = '';
		if ( $terms && ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( in_array( $term->slug, $managed_categories, true ) ) {
					$cat = $term->slug;
					break;
				}
			}
		}
		$options[ $pid ] = get_the_title( $pid ) . ' [' . $cat . ']';
	}

	$current = get_post_meta( get_the_ID(), '_jwb_paired_product', true );

	woocommerce_wp_select( array(
		'id'          => '_jwb_paired_product',
		'label'       => __( 'Paired Product', 'jwb-checkout' ),
		'description' => __( 'For gift products only — select the individual-study or session product this gift represents. Required for checkout toggle and recipient enrollment.', 'jwb-checkout' ),
		'desc_tip'    => true,
		'value'       => $current ? (int) $current : '',
		'options'     => $options,
	) );
}

add_action( 'woocommerce_process_product_meta', 'jwb_save_paired_product_field' );

function jwb_save_paired_product_field( $post_id ) {
	$value = isset( $_POST['_jwb_paired_product'] ) ? absint( $_POST['_jwb_paired_product'] ) : 0;
	update_post_meta( $post_id, '_jwb_paired_product', $value > 0 ? $value : '' );
}
