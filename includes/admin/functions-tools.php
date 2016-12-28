<?php
/**
 * Clear the activity meta for a given ticket.
 *
 * Deletes the activity meta transient from the database
 * for one given ticket.
 *
 * @param  integer $ticket_id ID of the ticket to clear the meta from
 * @return boolean True if meta was cleared, false otherwise
 * 
 */
function eddstix_clear_ticket_activity_meta( $ticket_id ) {
	return delete_transient( "eddstix_activity_meta_post_$ticket_id" );
}

/**
 * Clear all tickets metas.
 *
 * Gets all the existing tickets from the system
 * and clear their metas one by one.
 *
 * @return  True if some metas were cleared, false otherwise
 * 
 */
function eddstix_clear_tickets_metas() {
	$args = array(
		'post_type'              => 'edd_ticket',
		'post_status'            => 'any',
		'posts_per_page'         => -1,
		'no_found_rows'          => true,
		'cache_results'          => false,
		'update_post_term_cache' => false,
		'update_post_meta_cache' => false,
	);

	$query   = new WP_Query( $args );
	$cleared = false;
	
	if ( 0 == $query->post_count ) {
		return false;
	}

	foreach( $query->posts as $post ) {
		if ( eddstix_clear_ticket_activity_meta( $post->ID ) && false === $cleared ) {
			$cleared = true;
		}
	}
	return $cleared;
}

/**
 * Delete the synchronized product terms and re-synchronize.
 *
 * The function goes through all the available products
 * and deletes the associated synchronized terms along with
 * the term taxonomy and term relationship. It also deletes
 * the post metas where the taxonomy ID is stored.
 *
 * @return boolean True if the operation completed, false otherwise
 */
function eddstix_delete_resync_products() {
	$post_type = 'download';

	$sync  = new EDDSTIX_Product_Sync( '', 'ticket_product' );
	$posts = new WP_Query( array( 'post_type' => $post_type, 'posts_per_page' => -1, 'post_status' => 'any' ) );
	$sync->set_post_type( $post_type );

	if ( ! empty( $posts->posts ) ) {

		/* Remove all terms and post metas */
		foreach ( $posts->posts as $post ) {
			$sync->unsync_term( $post->ID );
		}

	}

	/* Now let's make sure we don't have some orphan post metas left */
	global $wpdb;

	$metas = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->postmeta} WHERE meta_key = '%s'", '_eddstix_product_term' ) );

	if ( ! empty( $metas ) ) {

		foreach ( $metas as $meta ) {

			$value = unserialize( $meta->meta_value );
			$term = get_term_by( 'id', $value['term_id'], 'ticket_product' );

			if ( empty( $term ) ) {
				delete_post_meta( $meta->post_id, '_eddstix_product_term' );
			}

		}

	}

	/* Delete the initial synchronization marker so that it's done again */
	delete_option( "eddstix_sync_$post_type" );
	/* Re-Synchronize */
	$sync->run_initial_sync();
	return true;
}

/**
 * Generates a System Info download file
 *
 * @return      void
 */
function eddstix_tools_sysinfo_download() {
	if( ! current_user_can( 'manage_edd_ticket_settings' ) ) {
		return;
	}
	nocache_headers();
	header( 'Content-Type: text/plain' );
	header( 'Content-Disposition: attachment; filename="eddstix-system-info.txt"' );
	echo wp_strip_all_tags( $_POST['eddstix-sysinfo'] );
	exit;
}
add_action( 'eddstix_download_sysinfo', 'eddstix_tools_sysinfo_download' );