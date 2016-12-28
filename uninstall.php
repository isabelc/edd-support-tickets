<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package 	EDD_Support_Tickets
 * @author 		Isabel Castillo
 * @license 	GPL-2.0+
 * @copyright 	Copyright (c) 2015-2016, Isabel Castillo
 */

// If uninstall not called from WordPress, then exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( is_multisite() ) {
	global $wpdb;
	$blogs = $wpdb->get_results( "SELECT blog_id FROM {$wpdb->blogs}", ARRAY_A );
	if ( $blogs ) {
		foreach ( $blogs as $blog ) {
			switch_to_blog( $blog['blog_id'] );
			eddstix_uninstall();
			restore_current_blog();
		}
	}
}
else {
	eddstix_uninstall();
}

/**
 * Uninstall function.
 *
 * The uninstall function will only proceed if
 * the user explicitly asks for all data to be removed.
 *
 * @return void
 */
function eddstix_uninstall() {

	$options = get_option( 'eddstix_settings' );

	/* Make sure that the user wants to remove all the data. */
	if ( isset( $options['delete_data'] ) && '1' === $options['delete_data'] ) {

		/* Remove all plugin options. */
		delete_option( 'eddstix_settings' );
		delete_option( 'eddstix_setup' );
		delete_option( 'eddstix_ask_multiple_products' );
		delete_option( 'eddstix_sync_download' );
		

		/* Delete the plugin pages.	 */
		wp_delete_post( intval( $options['ticket_submit'] ), true );
		wp_delete_post( intval( $options['ticket_list'] ), true );

		/**
		 * Delete all posts from all custom post types
		 * that the plugin created.
		 */
		$args = array(
			'post_type'              => array( 'edd_ticket', 'edd_ticket_reply', 'edd_ticket_history' ),
			'post_status'            => array( 'any', 'trash', 'auto-draft' ),
			'posts_per_page'         => -1,
			'no_found_rows'          => true,
			'cache_results'          => false,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
			
		);

		$posts = new WP_Query( $args );

		/* Delete all post types and attachments */
		foreach ( $posts->posts as $post ) {

			eddstix_delete_attachments( $post->ID );
			wp_delete_post( $post->ID, true );

			$upload_dir = wp_upload_dir();
			$dirpath    = trailingslashit( $upload_dir['basedir'] ) . "edd-support-tickets/ticket_$post->ID";

			if ( $post->post_parent == 0 ) {

				/* Delete the uploads folder */
				if ( is_dir( $dirpath ) ) {
					rmdir( $dirpath );
				}

				/* Remove transients */
				delete_transient( "eddstix_activity_meta_post_$post->ID" );
				delete_transient( 'eddstix_get_support_staff' );
			}
		}

		/* Delete taxonomies */
		eddstix_delete_taxonomy( 'ticket_tag' );
		eddstix_delete_taxonomy( 'ticket_product' );

		eddstix_remove_caps();
		remove_role( 'eddstix_supervisor' );
		remove_role( 'eddstix_agent' );
	}
}

/**
 * Delete all terms of the given taxonomy.
 *
 * As the get_terms function is not available during uninstall
 * (because the taxonomies are not registered), we need to work
 * directly with the $wpdb class. The function gets all taxonomy terms
 * and deletes them one by one.
 *
 * @param  string $taxonomy Name of the taxonomy to delete
 * @return void
 */
function eddstix_delete_taxonomy( $taxonomy ) {

	global $wpdb;
	$terms = $wpdb->get_results( $wpdb->prepare( "SELECT t.*, tt.* FROM $wpdb->terms AS t INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id WHERE tt.taxonomy IN ('%s') ORDER BY t.name ASC", $taxonomy ) );
	
	// Delete Terms  
	if ( $terms ) {
		foreach ( $terms as $term ) {
		    $wpdb->delete( $wpdb->term_taxonomy, array( 'term_taxonomy_id' => $term->term_taxonomy_id ) );
		    $wpdb->delete( $wpdb->term_relationships, array( 'term_taxonomy_id' => $term->term_taxonomy_id ) );
		    $wpdb->delete( $wpdb->terms, array( 'term_id' => $term->term_id ) );
		    // delete_option( 'prefix_' . $taxonomy->slug . '_option_name' );
		}
	}
		
	// Delete Taxonomy
	$wpdb->delete( $wpdb->term_taxonomy, array( 'taxonomy' => $taxonomy ), array( '%s' ) );

	// Delete the product term metas from Downloads
	$metas = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->postmeta} WHERE meta_key = '%s'", '_eddstix_product_term' ) );
	if ( ! empty( $metas ) ) {
		foreach ( $metas as $meta ) {
			delete_post_meta( $meta->post_id, '_eddstix_product_term' );
		}
	}
}

/**
 * Delete attachments.
 *
 * Delete all tickets and replies attachments.
 *
 * @param  integer $post_id ID of the post to delete attachments from
 * @return void
 */
function eddstix_delete_attachments( $post_id ) {

	$args = array(
		'post_type'              => 'attachment',
		'post_status'            => 'any',
		'posts_per_page'         => -1,
		'post_parent'            => $post_id,
		'no_found_rows'          => true,
		'cache_results'          => false,
		'update_post_term_cache' => false,
		'update_post_meta_cache' => false,
		
	);

	$posts = new WP_Query( $args );

	foreach ( $posts->posts as $post ) {
		wp_delete_attachment( $post->ID, true );
	}
}

/**
 * Remove core post type capabilities
 */
function eddstix_remove_caps() {
	global $wp_roles;
	if ( class_exists( 'WP_Roles' ) ) {
		if ( ! isset( $wp_roles ) ) {
			$wp_roles = new WP_Roles();
		}
	}
	if ( is_object( $wp_roles ) ) {

		// remove agent caps 

		$agent_cap = array(
			'edit_edd_support_tickets',
			'edit_private_edd_support_tickets',
			'edit_published_edd_support_tickets',
			'publish_edd_support_tickets',
			'read_private_edd_support_tickets',
			// Terms
			'manage_edd_support_ticket_terms',
			'edit_edd_support_ticket_terms',
			'delete_edd_support_ticket_terms',
			'assign_edd_support_ticket_terms',
		);

		foreach ( $agent_cap as $cap ) {
			$wp_roles->remove_cap( 'administrator', $cap );
			$wp_roles->remove_cap( 'eddstix_supervisor', $cap );
			$wp_roles->remove_cap( 'shop_manager', $cap );
			$wp_roles->remove_cap( 'eddstix_agent', $cap );
			$wp_roles->remove_cap( 'shop_worker', $cap );			
		}

		// remove management caps

		$management_caps = array(
			'edit_others_edd_support_tickets',
			'delete_edd_support_tickets',
			'delete_private_edd_support_tickets',
			'delete_published_edd_support_tickets',
			'delete_others_edd_support_tickets',
			'manage_edd_ticket_settings'
			);

		foreach ( $management_caps as $cap ) {
			$wp_roles->remove_cap( 'administrator', $cap );
			$wp_roles->remove_cap( 'eddstix_supervisor', $cap );
			$wp_roles->remove_cap( 'shop_manager', $cap );
		}

		// remove customer caps
		$wp_roles->remove_cap( 'subscriber', 'publish_edd_support_tickets' );
	}
}