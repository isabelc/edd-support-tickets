<?php
/**
 * Check if the ticket is old.
 *
 * A simple check based on the value of the "Ticket old" option.
 * If the last reply (or the ticket itself if no reply) is older
 * than the post date + the allowed delay, then it is considered old.
 *
 * @param  integer $post_id The ID of the ticket to check
 * @param  object $latest  The object containing the ticket replies. If the object was previously generated we pass it directly in order to avoid re-querying
 * @return boolean True if the ticket is old, false otherwise
 */
function eddstix_is_ticket_old( $post_id, $latest = null ) {

	$status = get_post_status( $post_id );
	if ( ! eddstix_is_status_open( $status ) ) {
		return false;
	}

	/* Prepare the new object */
	if ( is_null( $latest ) ) {
		$latest = new WP_Query(  array(
			'posts_per_page'         =>	1,
			'orderby'                =>	'post_date',
			'order'                  =>	'DESC',
			'post_type'              =>	'edd_ticket_reply',
			'post_parent'            =>	$post_id,
			'post_status'            =>	array( 'unread', 'read' ),
			'no_found_rows'          => true,
			'cache_results'          => false,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
			)
		);
	}

	/**
	* We check when was the last reply (if there was a reply).
	* Then, we compute the ticket age and if it is considered as 
	* old, we display an informational tag.
	*/
	if ( empty( $latest->posts ) ) {

		$post = get_post( $post_id );

		/* We get the post date */
		$date_created = $post->post_date;

	} else {

		/* We get the post date */
		$date_created = $latest->post->post_date;

	}

	$old_after = eddstix_get_option( 'old_ticket' );

	if ( strtotime( "$date_created +$old_after days" ) < strtotime( 'now' ) ) {
		return true;
	}

	return false;

}

/**
 * Check if a reply is needed.
 *
 * Takes a ticket ID and checks if a reply is needed. The check is based
 * on who replied last. If a client was the last to reply, or if the ticket
 * was just transferred from one agent to another, then it is considered
 * as "awaiting reply".
 *
 * @param  integer $post_id The ID of the ticket to check
 * @param  object  $latest  The object containing the ticket replies. If the object was previously generated we pass it directly in order to avoid re-querying
 * @return boolean True if a reply is needed, false otherwise
 */
function eddstix_is_reply_needed( $post_id, $latest = null ) {

	$status = get_post_status( $post_id );
	if ( ! eddstix_is_status_open( $status ) ) {
		return false;
	}

	/* Prepare the new object */
	if ( is_null( $latest ) ) {
		$latest = new WP_Query(  array(
			'posts_per_page'         =>	1,
			'orderby'                =>	'post_date',
			'order'                  =>	'DESC',
			'post_type'              =>	'edd_ticket_reply',
			'post_parent'            =>	$post_id,
			'post_status'            =>	array( 'unread', 'read' ),
			'no_found_rows'          => true,
			'cache_results'          => false,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
			)
		);
	}

	/* No reply yet. */
	if ( empty( $latest->posts ) ) {

		$post = get_post( $post_id );

		/* Make sure the ticket wan not created by an agent on behalf of the client. */
		if ( ! user_can( $post->post_author, 'edit_edd_support_tickets' ) ) {
			return true;
		}

	} else {

		$last = $latest->post_count-1;

		/* Check if the last user who replied is an agent. */
		if ( ! user_can( $latest->posts[$last]->post_author, 'edit_edd_support_tickets' ) && 'unread' === $latest->posts[$last]->post_status ) {
			return true;
		}

	}

	return false;

}

/**
 * Checks for templates overrides.
 *
 * Check if any of the plugin templates is being
 * overwritten by the child theme or the theme.
 *
 * @param  string $dir Directory to check
 * @return array       Array of overridden templates
 */
function eddstix_check_templates_override( $dir ) {

	$templates = array(
		'details.php',
		'list.php',
		'registration.php',
		'submission.php'
	);

	$overrides = array();

	if ( is_dir( $dir ) ) {

		$files = scandir( $dir );

		if ( empty( $files ) ) {
			return array();
		}

		foreach ( $files as $key => $file ) {
			if ( ! in_array( $file, $templates ) ) {
				continue;
			}

			array_push( $overrides, $file );
		}

	}

	return $overrides;

}

/**
 * Processes all EDD Support Tickets actions sent via POST 
 * by looking for the 'eddstix-action' request and running
 * do_action() to call the function.
 *
 * @return void
 */
function eddstix_process_actions() {
	if ( isset( $_POST['eddstix-action'] ) ) {
		do_action( 'eddstix_' . $_POST['eddstix-action'], $_POST );
	}
}
add_action( 'admin_init', 'eddstix_process_actions' );
/**
 * Custom tickets menu icon for admin menu
 */
function eddstix_custom_admin_menu_icon() {
   echo '<style>@font-face {
	font-family: "eddstix";
	src:    url("' . EDDSTIX_URL . 'assets/admin/fonts/eddstix.eot?zer0ev");
	src:    url("' . EDDSTIX_URL . 'assets/admin/fonts/eddstix.eot?zer0ev#iefix") format("embedded-opentype"),
	url("' . EDDSTIX_URL . 'assets/admin/fonts/eddstix.ttf?zer0ev") format("truetype"),
	url("' . EDDSTIX_URL . 'assets/admin/fonts/eddstix.woff?zer0ev") format("woff"),
	url("' . EDDSTIX_URL . 'assets/admin/fonts/eddstix.svg?zer0ev#eddstix") format("svg");
	font-weight: normal;
	font-style: normal;
	}#adminmenu .menu-icon-edd_ticket .dashicons-tickets-alt.dashicons-before::before {font-family: "eddstix" !important}#adminmenu .menu-icon-edd_ticket div.dashicons-tickets-alt::before{content:"\e900"}</style>';
}
add_action('admin_head', 'eddstix_custom_admin_menu_icon');
