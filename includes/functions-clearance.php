<?php
/**
 * Clearance Functions.
 *
 * Functions that run clearance checks on users to see who can access support.
 *
 * @package 	EDD_Support_Tickets
 * @author 		Isabel Castillo
 * @license 	GPL-2.0+
 * @copyright 	Copyright (c) 2015-2016, Isabel Castillo
 */

/**
 * Convert array of download IDs to term ids
 * @param array of download IDs
 * @return array|boolean
 */
function eddstix_download_ids_to_term_ids( $download_ids ) {

	if ( empty( $download_ids ) ) {
		return false;
	}

	$term_ids = array();
	foreach ( $download_ids as $id ) {

		// get term id for DL ID
		$term_ids[] = eddstix_term_id_by_download( $id );

	}
	return $term_ids ? $term_ids : false;
}

/**
 * Does current user have clearance for support for any products?
 *
 * If multiple products is enabled, this returns terms ids for
 * products that user owns, or has valid licenses for, if license
 * requirement is enabled. 
 *
 * If multiple products is not enabled, 
 * returns true if customer has purchased any product, or owns any
 * valid license if license requirement is enabled.
 * 
 * @return mixed array|bool If multiple products is enabled, returns array of owned product term 
 							ids (only licensed ones if licensing requirement option is enabled), false if no products owned/licensed. If multiple is not enabled, returns true if any product is owned (or any license if licensing requirement is enabled), false if user has no products (or no licenses if licensing requirement option is enabled).
 */
function eddstix_has_clearance() {

	$current_user = wp_get_current_user();
	$user_id = $current_user->ID;

	if ('' == $user_id) {
		return false;
	}

	// if SL is active and enabled, get valid licenses
	if ( eddstix_get_option( 'need_license' ) && class_exists( 'EDD_Software_Licensing' ) ) {
		$download_ids = eddstix_get_valid_licenses( $user_id );

		// If "support free" filter is set to true, if user has any "free download" add the IDs.
		if ( apply_filters( 'eddstix_support_free_downloads', FALSE ) ) {
			$free_ids = eddstix_get_users_free_products( $user_id );
			if ( $free_ids ) {

				if ( ! is_array( $download_ids ) ) {
					$download_ids = array();
				}
				$result = array_merge( $download_ids, $free_ids );
				$download_ids = $result;
			}
		}
	} else {

		// SL is not enabled.
		$download_ids = apply_filters( 'eddstix_user_purchased_ids', eddstix_get_users_purchased_products( $user_id ) );

	}

	if ( $download_ids ) {
		if ( eddstix_get_option( 'multiple_products' ) ) {
			return eddstix_download_ids_to_term_ids( $download_ids );
		} else {
			return true;
		}
	}
	return false;
}

/**
 * Get Download IDs that have valid license keys, if any, of a user.
 *
 * Vaild licenses may be active or inactive, but not expired nor disabled.
 * @param string user ID
 *
 * @return mixed, array of licensed download IDs, if any, otherwise returns false.
 */
 
function eddstix_get_valid_licenses( $user_id ) {

	if ( empty( $user_id ) ) {
		return false;
	}

	// Get licenses for this user:

	$purchases = edd_get_users_purchases( $user_id, -1 );
	if ( empty( $purchases[0] ) ) {
		return false;
	}

	$payment_ids = array();
	foreach( $purchases as $purchase ) {
		$payment_ids[] = $purchase->ID;
	}

	$licenses    = get_posts( array(
		'post_type'      => 'edd_license',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'meta_query'     =>  array(
			array(
				'key'     => '_edd_sl_payment_id',
				'value'   => $payment_ids,
				'compare' => 'IN'
			)
		)
	) );

 	$download_ids = array();

	if ( $licenses ) {
		foreach ( $licenses as $license ) {

			// add download ID to array ONLY IF license is valid, i.e. not expired nor disabled

			if ( in_array( get_post_meta( $license->ID, '_edd_sl_status', true ), array( 'active', 'inactive' ) ) ) {

				$download_ids[] = get_post_meta( $license->ID, '_edd_sl_download_id', true );
				
			}
		}
	}

	return $download_ids ? $download_ids : false;
}

/**
 * Get a product term id by download id.
 * @return the term id|null
 */
function eddstix_term_id_by_download( $download_id ) {

	global $wpdb;
  
	$terms = $wpdb->get_results( $wpdb->prepare( "SELECT t.*, tt.* FROM $wpdb->terms AS t INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id WHERE tt.taxonomy IN ('%s') ORDER BY t.name ASC", 'ticket_product' ) );

	if ( ! is_array( $terms ) || ! isset( $terms[0] ) ) {
		return false;
	}

	foreach ( $terms as $key => $val ) {
		if ( $val->name == $download_id ) {
			return $val->term_id;
		}
	}
	return null;
}

/**
 * Get Users Purchased Product IDs
 *
 * Returns a list of unique product IDs purchased by a specific user. Unlike EDD's function, this eliminates products from abandoned/canceled carts, and those with pending/revoked payments.
 *
 * @param int|string $user User ID or email address
 *
 * @return bool|object List of unique product IDs purchased by user
 */
function eddstix_get_users_purchased_products( $user = 0 ) {

	if ( empty( $user ) ) {
		return false;
	}

	$purchases = edd_get_users_purchases( $user, -1 );

	if ( empty( $purchases[0] ) ) {
		return false;
	}

	// Get all the items purchased

	$payment_ids = array();
	foreach ( $purchases as $purchase ) {
		$payment_ids[] = $purchase->ID;
	}

	$purchase_data  = array();

	foreach ( $payment_ids as $payment_id ) {

		$purchase_data[] = edd_get_payment_meta_downloads( $payment_id );

	}

	if ( empty( $purchase_data ) ) {
		return false;
	}

	// Grab post ids of products purchased on this order
	$purchase_product_ids = array();
	foreach ( $purchase_data as $purchase_meta ) {
		foreach ( $purchase_meta as $key => $item ) {
			$download_id = $item['id'];
			if ( apply_filters( 'eddstix_support_free_downloads', FALSE ) ) {
				$purchase_product_ids[] = $download_id;
			} else {

				// By default, do not offer customer support for free downloads
				$price_id = empty( $item['options']['price_id'] ) ? false : $item['options']['price_id'];

				$is_free = edd_is_free_download( $download_id, $price_id );

				if ( true !== $is_free ) {
					$purchase_product_ids[] = $download_id;
				}
			}
		}
	}

	// Ensure that grabbed products actually HAVE downloads
	$purchase_product_ids = array_filter( $purchase_product_ids );

	if ( empty( $purchase_product_ids ) ) {
		return false;
	}
	$product_ids = array_unique( $purchase_product_ids );

	// Make sure we still have some products and a first item
	if ( empty ( $product_ids ) || ! isset( $product_ids[0] ) ) {
		return false;
	}

	return $product_ids;
}

/**
 * Get the IDs of any free downloads owned by a user.
 *
 * @param int|string $user User ID or email address
 *
 * @return bool|arary List of free download IDs owned by user
 */
function eddstix_get_users_free_products( $user = 0 ) {
	if ( empty( $user ) ) {
		return false;
	}

	$purchases = edd_get_users_purchases( $user, -1 );

	if ( empty( $purchases[0] ) ) {
		return false;
	}

	// Get all the items purchased

	$payment_ids = array();
	foreach ( $purchases as $purchase ) {
		$payment_ids[] = $purchase->ID;
	}

	$purchase_data  = array();

	foreach ( $payment_ids as $payment_id ) {
		$purchase_data[] = edd_get_payment_meta_downloads( $payment_id );
	}

	if ( empty( $purchase_data ) ) {
		return false;
	}

	// Grab post ids of products purchased on this order
	$free_product_ids = array();
	foreach ( $purchase_data as $purchase_meta ) {

		foreach ( $purchase_meta as $key => $item ) {
			$download_id = $item['id'];
			$price_id = empty( $item['options']['price_id'] ) ? false : $item['options']['price_id'];

			$is_free = edd_is_free_download( $download_id, $price_id );

			if ( true === $is_free ) {
				$free_product_ids[] = $download_id;
			}
		}
	}

	// Ensure that grabbed products actually HAVE downloads
	$free_product_ids = array_filter( $free_product_ids );

	if ( empty( $free_product_ids ) ) {
		return false;
	}
	$product_ids = array_unique( $free_product_ids );

	// Make sure we still have some products and a first item
	if ( empty ( $product_ids ) || ! isset( $product_ids[0] ) ) {
		return false;
	}

	return $product_ids;
}