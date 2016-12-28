<?php
/**
 * Open a new ticket from the front end.
 *
 * @param  array $data Ticket data
 * @return boolean
 */
function eddstix_open_ticket( $data ) {

	$title   = isset( $data['title'] ) ? wp_strip_all_tags( $data['title'] ) : false;
	$content = isset( $data['message'] ) ? wp_kses( $data['message'], wp_kses_allowed_html( 'post' ) ) : false;

	$submit  = eddstix_get_option( 'ticket_submit' ); // ID of the submission page

	// Verify user capability
	if ( false === eddstix_has_clearance() ) {

		eddstix_save_values();

		// Redirect to submit page
		wp_redirect( add_query_arg( array( 'message' => 11 ), get_permalink( $submit ) ) );

		// Break
		exit;
	}

	// Make sure we have at least a title (subject) and a message
	if ( false === $title || empty( $title ) ) {

		eddstix_save_values();

		// Redirect to submit page
		wp_redirect( add_query_arg( array( 'message' => 3 ), get_permalink( $submit ) ) );
		exit;
	}

	if ( true === ( $description_mandatory = apply_filters( 'eddstix_ticket_submission_description_mandatory', true ) ) && ( false === $content || empty( $content ) ) ) {

		eddstix_save_values();

		wp_redirect( add_query_arg( array( 'message' => 10 ), get_permalink( $submit ) ) );
		exit;

	}

	/**
	 * Allow the submission.
	 *
	 * This variable is used to add additional checks in the submission process.
	 * If the $go var is set to true, it gives a green light to this method
	 * and the ticket will be submitted. If the var is set to false, the process
	 * will be aborted.
	 *
	 */
	$go = apply_filters( 'eddstix_before_submit_new_ticket_checks', true );

	/* Check for the green light */
	if ( is_wp_error( $go ) ) {
		$messages = $go->get_error_messages();
		eddstix_save_values();
		wp_redirect( add_query_arg( array( 'message' => eddstix_create_notification( $messages ), get_permalink( $submit ) ) ) );

		exit;

	}

	/**
	 * Gather current user info
	 */
	if ( is_user_logged_in() ) {

		global $current_user;

		$user_id = $current_user->ID;

	} else {

		// Save the input
		eddstix_save_values();

		// Redirect to submit page
		wp_redirect( add_query_arg( array( 'message' => 5 ), get_permalink( $submit ) ) );

		// Break
		exit;

	}

	/**
	 * Submit the ticket.
	 *
	 * Now that all the verifications are passed
	 * we can proceed to the actual ticket submission.
	 */
	$post = array(
		'post_content'   => $content,
		'post_name'      => $title,
		'post_title'     => $title,
		'post_status'    => 'ticket_queued',
		'post_type'      => 'edd_ticket',
		'post_author'    => $user_id,
		'ping_status'    => 'closed',
		'comment_status' => 'closed',
	);

	return eddstix_insert_ticket( $post );
}
/**
 * Insert a new ticket from front end.
 *
 * @param  array $data Ticket data
 * @return boolean
 */

function eddstix_insert_ticket( $data = array() ) {

	if ( ! current_user_can( 'publish_edd_support_tickets' ) ) {
		return false;
	}

	$defaults = array(
		'post_content'   => '',
		'post_name'      => '',
		'post_title'     => '',
		'post_status'    => 'ticket_queued',
		'post_type'      => 'edd_ticket',
		'post_author'    => '',
		'ping_status'    => 'closed',
		'comment_status' => 'closed',
	);

	/* Parse the input data. */
	$data = wp_parse_args( $data, $defaults );

	/* Sanitize the data */
	if ( isset( $data['post_title'] ) && ! empty( $data['post_title'] ) ) {
		$data['post_title'] = wp_strip_all_tags( $data['post_title'] );
	}

	if ( ! empty( $data['post_content'] ) ) {
		$data['post_content'] = strip_shortcodes( $data['post_content'] );
	}
	
	$data = apply_filters( 'eddstix_open_ticket_data', $data );

	if ( isset( $data['post_name'] ) && ! empty( $data['post_name'] ) ) {
		$data['post_name'] = sanitize_text_field( $data['post_name'] );
	}

	/* Set the current user as author if the field is empty. */
	if ( empty( $data['post_author'] ) ) {
		global $current_user;
		$data['post_author'] = $current_user->ID;
	}

	/**
	 * Fire eddstix_before_open_ticket just before the post is actually
	 * inserted in the database.
	 */
	do_action( 'eddstix_open_ticket_before', $data );

	/**
	 * Insert the post in database using the regular WordPress wp_insert_post
	 * function with default values corresponding to our post type structure.
	 * 
	 * @var boolean
	 */
	$ticket_id = wp_insert_post( $data, false );

	if ( false === $ticket_id ) {
		// ticket couldn't be inserted.
		do_action( 'eddstix_open_ticket_failed', $data );
		return false;
	}

	$agent_id = eddstix_find_agent( $ticket_id );
	/* Assign an agent to the ticket */
	eddstix_assign_ticket( $ticket_id, $agent_id, false );

	// Fire action just after the post is successfully submitted from front end.
	do_action( 'eddstix_open_ticket_after', $ticket_id, $data );
	update_post_meta( $ticket_id, '_last_activity', current_time( 'mysql' ) );

	return $ticket_id;

}

/**
 * Get tickets.
 *
 * Get a list of tickets matching the arguments passed.
 * This function is basically a wrapper for WP_Query.
 *
 * @param  array $args Additional arguments (see WP_Query)
 * @return array Array of tickets, empty array if no tickets found
 */
function eddstix_get_tickets( $args = array() ) {

	$defaults = array(
		'post_type'					=> 'edd_ticket',
		'post_status'				=> array( 'ticket_queued', 'ticket_processing', 'ticket_hold', 'ticket_status_closed' ),
		'posts_per_page'			=> -1,
		'no_found_rows'				=> true,
		'cache_results'				=> false,
		'update_post_term_cache'	=> false,
		'update_post_meta_cache'	=> false
	);

	$args = wp_parse_args( $args, $defaults );

	$query = new WP_Query( $args );
	if ( empty( $query->posts ) ) {
		return array();
	} else {
		return $query->posts;
	}
}

/**
 * Add a new reply to a ticket.
 *
 * @param array           $data      The reply data to insert
 * @param boolean|integer $parent_id ID of the parent ticket (post)
 * @param boolean|integer $author_id The ID of the reply author (false if none)
 *
 * @return boolean|integer False on failure or reply ID on success
 */
function eddstix_add_reply( $data, $parent_id = false, $author_id = false ) {

	if ( false === $parent_id ) {

		if ( isset( $data['parent_id'] ) ) {

			/* Get the parent ID from $data if not provided in the arguments. */
			$parent_id = intval( $data['parent_id'] );
			$parent    = get_post( $parent_id );

			/* Mare sure the parent exists. */
			if ( is_null( $parent ) ) {
				return false;
			}

		} else {
			return false;
		}

	}

	/**
	 * Submit the reply.
	 *
	 * Now that all the verifications are passed
	 * we can proceed to the actual ticket submission.
	 */
	$defaults = array(
		'post_content'   => '',
		'post_name'      => sprintf( __( 'Reply to ticket %s', 'edd-support-tickets' ), "#$parent_id" ),
		'post_title'     => sprintf( __( 'Reply to ticket %s', 'edd-support-tickets' ), "#$parent_id" ),
		'post_status'    => 'unread',
		'post_type'      => 'edd_ticket_reply',
		'ping_status'    => 'closed',
		'comment_status' => 'closed',
		'post_parent'    => $parent_id,
	);

	$data = wp_parse_args( $data, $defaults );

	if ( false !== $author_id ) {
		$data['post_author'] = $author_id;
	} else {
		global $current_user;
		$data['post_author'] = $current_user->ID;
	}

	$insert = eddstix_insert_reply( $data, $parent_id );

	return $insert;

}

function eddstix_edit_reply( $reply_id = null, $content = '' ) {

	if ( is_null( $reply_id ) ) {
		if ( isset( $_POST['reply_id'] ) ) {
			$reply_id = intval( $_POST['reply_id'] );
		} else {
			return false;
		}
	}

	if ( empty( $content ) ) {
		if ( isset( $_POST['reply_content'] ) ) {
			$content = wp_kses( $_POST['reply_content'], wp_kses_allowed_html( 'post' ) );
		} else {
			return false;
		}
	}

	$reply = get_post( $reply_id );

	if ( is_null( $reply ) ) {
		return false;
	}

	$data = apply_filters( 'eddstix_edit_reply_data', array(
		'ID'             => $reply_id,
		'post_content'   => $content,
		'post_status'    => 'read',
		'comment_status' => 'closed',
		'ping_status'    => 'closed',
		'post_date'      => $reply->post_date,
		'post_date_gmt'  => $reply->post_date_gmt,
		'post_name'      => $reply->post_name,
		'post_parent'    => $reply->post_parent,
		'post_type'      => $reply->post_type,
		'post_author'    => $reply->post_author,
		), $reply_id
	);

	$edited = wp_insert_post( $data, true );

	if ( is_wp_error( $edited ) ) {
		do_action( 'eddstix_edit_reply_failed', $reply_id, $content, $edited );
		return $edited;
	}

	do_action( 'eddstix_reply_edited', $reply_id );

	return $reply_id;

}

function eddstix_mark_reply_read( $reply_id = null ) {

	if ( is_null( $reply_id ) ) {
		if ( isset( $_POST['reply_id'] ) ) {
			$reply_id = intval( $_POST['reply_id'] );
		} else {
			return false;
		}
	}

	$reply = get_post( $reply_id );

	if ( is_null( $reply ) ) {
		return false;
	}

	if ( 'read' === $reply->post_status ) {
		return $reply_id;
	}

	$data = apply_filters( 'eddstix_mark_reply_read_data', array(
		'ID'             => $reply_id,
		'post_status'    => 'read',
		'comment_status' => 'closed',
		'ping_status'    => 'closed',
		'post_content'   => $reply->post_content,
		'post_date'      => $reply->post_date,
		'post_date_gmt'  => $reply->post_date_gmt,
		'post_name'      => $reply->post_name,
		'post_parent'    => $reply->post_parent,
		'post_type'      => $reply->post_type,
		'post_author'    => $reply->post_author,
		), $reply_id
	);

	$edited = wp_insert_post( $data, true );

	if ( is_wp_error( $edited ) ) {
		do_action( 'eddstix_mark_reply_read_failed', $reply_id, $edited );
		return $edited;
	}

	do_action( 'eddstix_marked_reply_read', $reply_id );

	return $edited;

}

function eddstix_mark_reply_read_ajax() {
	
	$ID = eddstix_mark_reply_read();

	if ( false === $ID || is_wp_error( $ID ) ) {
		$ID = $ID->get_error_message();
	}

	echo $ID;
	die();
}

function eddstix_edit_reply_ajax() {
	
	$ID = eddstix_edit_reply();

	if ( false === $ID || is_wp_error( $ID ) ) {
		$ID = $ID->get_error_message();
	}

	echo $ID;
	die();
}

/**
 * Insert a new reply from front or back.
 *
 * The function is basically a wrapper for wp_insert_post
 * with some additional checks and new default arguments
 * adapted to the needs of the edd_ticket_reply post type.
 * If also gives some useful hooks at different steps of
 * the process.
 *
 * @param  array            $data     Array of arguments for this reply
 * @param  boolean          $post_id  ID of the parent post
 * @return integer|WP_Error           The reply ID on success or WP_Error on failure
 */
function eddstix_insert_reply( $data, $post_id = false ) {

	if ( false === $post_id ) {
		return false;
	}

	if ( ! current_user_can( 'publish_edd_support_tickets' ) ) {
		return false;
	}

	$defaults = array(
		'post_name'      => sprintf( __( 'Reply to ticket %s', 'edd-support-tickets' ), "#$post_id" ),
		'post_title'     => sprintf( __( 'Reply to ticket %s', 'edd-support-tickets' ), "#$post_id" ),
		'post_content'   => '',
		'post_status'    => 'unread',
		'post_type'      => 'edd_ticket_reply',
		'post_author'    => '',
		'post_parent'    => $post_id,
		'ping_status'    => 'closed',
		'comment_status' => 'closed',
	);

	$data = wp_parse_args( $data, $defaults );

	/* Set the current user as author if the field is empty. */
	if ( empty( $data['post_author'] ) ) {
		global $current_user;
		$data['post_author'] = $current_user->ID;
	}

	$data = apply_filters( 'eddstix_add_reply_data', $data, $post_id );

	/* Sanitize the data */
	if ( isset( $data['post_title'] ) && ! empty( $data['post_title'] ) ) {
		$data['post_title'] = wp_strip_all_tags( $data['post_title'] );
	}

	if ( ! empty( $data['post_content'] ) ) {
		$data['post_content'] = strip_shortcodes( $data['post_content'] );
	}

	if ( isset( $data['post_name'] ) && ! empty( $data['post_name'] ) ) {
		$data['post_name'] = sanitize_title( $data['post_name'] );
	}

	/**
	 * Fire eddstix_add_reply_before before the reply is added to the database.
	 * This hook is fired both on the back-end and the front-end.
	 *
	 * @param  array   $data    The data to be inserted to the database
	 * @param  integer $post_id ID of the parent post
	 */
	do_action( 'eddstix_add_reply_before', $data, $post_id );

	if ( is_admin() ) {

		/**
		 * Fired right before the data is added to the database on the back-end only.
		 *
		 * @param  array   $data    The data to be inserted to the database
		 * @param  integer $post_id ID of the parent post
		 */
		do_action( 'eddstix_add_reply_admin_before', $data, $post_id );

	} else {

		/**
		 * Fired right before the data is added to the database on the front-end only.
		 *
		 * @param  array   $data    The data to be inserted to the database
		 * @param  integer $post_id ID of the parent post
		 */
		do_action( 'eddstix_add_reply_public_before', $data, $post_id );

	}

	/* This is where we actually insert the post */
	$reply_id = wp_insert_post( $data, true );

	if ( is_wp_error( $reply_id ) ) {

		/**
		 * Fire eddstix_add_reply_failed if the reply couldn't be inserted.
		 * This hook will be fired both in the admin and in the front-end.
		 *
		 * @param  array   $data     The data we tried to add to the database
		 * @param  integer $post_id  ID of the parent post
		 * @param  object  $reply_id WP_Error object
		 */
		do_action( 'eddstix_add_reply_failed', $data, $post_id, $reply_id );

		if ( is_admin() ) {

			/**
			 * Fired if the reply instertion failed.
			 * This hook will only be fired in the admin.
			 *
			 * @param  array   $data     The data we tried to add to the database
			 * @param  integer $post_id  ID of the parent post
			 * @param  object  $reply_id WP_Error object
			 */
			do_action( 'eddstix_add_reply_admin_failed', $data, $post_id, $reply_id );

		} else {

			/**
			 * Fired if the reply instertion failed.
			 * This hook will only be fired in the frontèend.
			 *
			 * @param  array   $data     The data we tried to add to the database
			 * @param  integer $post_id  ID of the parent post
			 * @param  object  $reply_id WP_Error object
			 */

			do_action( 'eddstix_add_reply_public_failed', $data, $post_id, $reply_id );

		}

		return $reply_id;
	}

	/**
	 * Fire eddstix_add_reply_after after the reply was successfully added.
	 */
	do_action( 'eddstix_add_reply_after', $reply_id, $data );

	if ( is_admin() ) {
		/**
		 * Fired right after the data is added to the database on the back-end only.
		 *
		 * @param  integer $reply_id ID of the reply added to the database
		 * @param  array   $data     Data inserted to the database
		 */
		do_action( 'eddstix_add_reply_admin_after', $reply_id, $data );

	} else {

		/**
		 * Fired right after the data is added to the database on the front-end only.
		 *
		 * @param  integer $reply_id ID of the reply added to the database
		 * @param  array   $data     Data inserted to the database
		 */
		do_action( 'eddstix_add_reply_public_after', $reply_id, $data );
		update_post_meta( $post_id, '_last_activity', current_time( 'mysql' ) );

	}
	return $reply_id;

}

function eddstix_get_replies( $post_id, $status = 'any', $args = array() ) {

	$allowed_status = array(
		'any',
		'read',
		'unread'
	);

	if ( ! in_array( $status, $allowed_status ) ) {
		$status = 'any';
	}

	$defaults = array(
		'post_parent'            => $post_id,
		'post_type'              => 'edd_ticket_reply',
		'post_status'            => $status,
		'order'                  => 'DESC',
		'orderby'                => 'date',
		'posts_per_page'         => -1,
		'no_found_rows'          => true,
		'cache_results'          => false,
		'update_post_term_cache' => false,
		'update_post_meta_cache' => false,
	);	

	$args = wp_parse_args( $args, $defaults );	
	
	$replies = new WP_Query( $args );

	if ( is_wp_error( $replies ) ) {
		return $replies;
	}
	
	return $replies->posts;

}

/**
 * Find an available agent to assign a ticket to.
 *
 * This finds the agent with the least tickets currently open.
 *
 * @param  boolean|integer $ticket_id The ticket that needs an agent
 * @return integer ID of the best agent for the job
 */
function eddstix_find_agent( $ticket_id = false ) {

	if ( eddstix_get_option( 'disable_auto_assign' ) ) {	
		return apply_filters( 'eddstix_find_available_agent', eddstix_get_option( 'assignee_default' ), $ticket_id );
	}

	$available_staff = eddstix_get_option( 'roles_available_auto_assign' );

	if ( $available_staff ) {
		$roles = array();
		foreach ( $available_staff as $key => $value ) {
			array_push( $roles, $key );
		}
		
	} else {
		$roles = array( 'administrator' );
	}

	$users = eddstix_get_support_staff( $roles );

	shuffle( $users );

	$agent = array();

	foreach ( $users as $user ) {

		$posts_args = array(
			'post_type'					=> 'edd_ticket',
			'post_status'				=> array( 'ticket_queued', 'ticket_processing', 'ticket_hold' ),
			'posts_per_page'			=> - 1,
			'no_found_rows'				=> true,
			'cache_results'				=> false,
			'update_post_term_cache'	=> false,
			'update_post_meta_cache'	=> false,
			'meta_query'			=> array(
				array(
					'key'     => '_eddstix_assignee',
					'value'   => $user,
					'type'    => 'NUMERIC',
					'compare' => '='
				),
			)
		);

		$open_tickets = new WP_Query( $posts_args );
		$count        = count( $open_tickets->posts ); // Total number of open tickets for this agent

		if ( empty( $agent ) ) {
			$agent = array( 'tickets' => $count, 'user_id' => $user );
		} else {

			if ( $count < $agent['tickets'] ) {
				$agent = array( 'tickets' => $count, 'user_id' => $user );
			}

		}

	}
	return apply_filters( 'eddstix_find_available_agent', $agent['user_id'], $ticket_id );
}

/**
 * Assign an agent to a ticket.
 *
 * Assign the given agent to a ticket or find an available
 * agent if no agent ID is given.
 *
 * @param  integer  $ticket_id    ID of the post in need of a new agent
 * @param  integer  $agent_id     ID of the agent to assign the ticket to
 * @param  boolean  $log          Shall the assignment be logged or not
 * @return object|boolean|integer	WP_Error in case of problem, true if no change, or if the post meta ID of the agent was changed, or meta id for new assignment.
 */
function eddstix_assign_ticket( $ticket_id, $agent_id = null, $log = true ) {

	if ( 'edd_ticket' !== get_post_type( $ticket_id ) ) {
		return new WP_Error( 'incorrect_post_type', __( 'The given post ID is not a ticket', 'edd-support-tickets' ) );
	}
	if ( is_null( $agent_id ) ) {
		$agent_id = eddstix_find_agent( $ticket_id );
	}
	if ( ! user_can( $agent_id, 'edit_edd_support_tickets' ) ) {
		return new WP_Error( 'incorrect_agent', __( 'The chosen agent does not have the sufficient capabilities to be assigned a ticket', 'edd-support-tickets' ) );
	}

	/* Get the current agent if any */
	$current = get_post_meta( $ticket_id, '_eddstix_assignee', true );

	if ( $current === $agent_id ) {
		return true;
	}

	$update = update_post_meta( $ticket_id, '_eddstix_assignee', $agent_id, $current );

	/* Log the action */
	if ( true === $log ) {
		$log = array();
		$log[] = array(
			'action'   => 'updated',
			'label'    => __( 'Support staff', 'edd-support-tickets' ),
			'value'    => $agent_id,
			'field_id' => 'assignee'
		);
	}

	eddstix_log( $ticket_id, $log );

	do_action( 'eddstix_ticket_assigned', $ticket_id, $agent_id );

	return $update;

}

/**
 * Save form values.
 *
 * If the submission fails we save the form values in order to
 * pre-populate the form on page reload. This will avoid asking the user
 * to fill all the fields again.
 *
 * @return void
 */
function eddstix_save_values() {

	if ( isset( $_SESSION['eddstix_submission_form'] ) ) {
		unset( $_SESSION['eddstix_submission_form'] );
	}

	foreach ( $_POST as $key => $value ) {

		if ( ! empty( $value ) ) {
			$_SESSION['eddstix_submission_form'][$key] = $value;
		}

	}

}

/**
 * Update ticket status.
 *
 * Update the post_status of a ticket
 * using one of the custom status registered by the plugin.
 *
 * @param  integer $post_id ID of the ticket being updated
 * @param  string  $status  New status to attribute
 * @return mixed Post id if is was updated, otherwise false
 */
function eddstix_update_ticket_status( $post_id, $status ) {

	$custom_status = eddstix_get_custom_ticket_statuses();

	if ( ! array_key_exists( $status, $custom_status ) ) {
		return false;
	}

	$post = get_post( $post_id );
	$is_reopen = 'ticket_status_closed' == $post->post_status ? true : false;
	if ( ! $post || $post->post_status === $status ) {
		return false;
	}

	$my_post = array(
		'ID'          => $post_id,
		'post_status' => $status
	);

	$updated = wp_update_post( $my_post );

	if ( 0 !== intval( $updated ) ) {

		// Log, unless ticket is being closed or reopened, which gets logged elsewhere
		if ( ( ! $is_reopen ) && ( 'ticket_status_closed' != $status ) ) {
			eddstix_log( $post_id, sprintf( __( 'Ticket state changed to &laquo;%s&raquo;', 'edd-support-tickets' ), $custom_status[$status] ) );
		}
	}

	do_action( 'eddstix_ticket_status_updated', $post_id, $status, $updated );

	return $updated;

}

/**
 * Change a ticket status to closed.
 *
 * @param  integer $ticket_id ID of the ticket to close
 * @return integer|boolean ID of the post meta if exists, true on success or false on failure
 */
function eddstix_close_ticket( $ticket_id ) {

	global $current_user;

	if ( is_admin() ) {
		$cap = current_user_can( 'edit_edd_support_ticket', $ticket_id );
		if ( current_user_can( 'manage_edd_ticket_settings' ) ) {
			$cap = true;
		}
	} else {
		$author_id = get_post_field ('post_author', $ticket_id);
		$cap = get_current_user_id() == $author_id ? true : false;
	}

	if ( ! $cap ) {
			wp_die( __( 'You do not have the capacity to close this ticket', 'edd-support-tickets' ), __( 'Can&#39;t close ticket', 'edd-support-tickets' ), array( 'back_link' => true ) );
	}
	$ticket_id = intval( $ticket_id );

	if ( 'edd_ticket' == get_post_type( $ticket_id ) ) {

		$update = eddstix_update_ticket_status( $ticket_id, 'ticket_status_closed' );
		
		/* Log the action */
		eddstix_log( $ticket_id, __( 'The ticket was closed.', 'edd-support-tickets' ) );

		do_action( 'eddstix_after_close_ticket', $ticket_id, $update );

		if ( is_admin() ) {

			/**
			 * Fires after the ticket was closed in the admin only.
			 *
			 * @param integer $ticket_id ID of the ticket we just closed
			 * @param integer $user_id   ID of the user who did the action
			 * @param boolean $update    True on success, false on fialure
			 */
			do_action( 'eddstix_after_close_ticket_admin', $ticket_id, $current_user->ID, $update );

		} else {

			/**
			 * Fires after the ticket was closed in the front-end only.
			 * 
			 * @param integer $ticket_id ID of the ticket we just closed
			 * @param integer $user_id   ID of the user who did the action
			 * @param boolean $update    True on success, false on fialure
			 */
			do_action( 'eddstix_after_close_ticket_public', $ticket_id, $current_user->ID, $update );

		}

		return $update;

	} else {
		return false;
	}

}

/**
 * Re-open a ticket by changing status to processing.
 *
 * @param  integer $ticket_id ID of the ticket to re-open
 * @return integer|boolean ID of the post if updated, or false on failure
 */
function eddstix_reopen_ticket( $ticket_id ) {

	if ( is_admin() ) {
		$cap = current_user_can( 'edit_edd_support_ticket', $ticket_id );

		if ( current_user_can( 'manage_edd_ticket_settings' ) ) {
			$cap = true;
		}

	} else {
		$author_id = get_post_field ('post_author', $ticket_id);
		$cap = get_current_user_id() == $author_id ? true : false;
	}

	if ( ! $cap ) {
		wp_die( __( 'You do not have the capacity to manage this ticket', 'edd-support-tickets' ), __( 'Can&#39;t reopen ticket', 'edd-support-tickets' ), array( 'back_link' => true ) );
	}

	if ( 'edd_ticket' == get_post_type( $ticket_id ) ) {

		$update = eddstix_update_ticket_status( $ticket_id, 'ticket_processing' );

		/* Log the action */
		eddstix_log( $ticket_id, __( 'The ticket was re-opened.', 'edd-support-tickets' ) );

		do_action( 'eddstix_after_reopen_ticket', intval( $ticket_id ), $update );
		update_post_meta( $ticket_id, '_last_activity', current_time( 'mysql' ) );
		return $update;

	} else {
		return false;
	}

}

add_filter( 'map_meta_cap', 'eddstix_map_meta_cap', 10, 4 );

/**
* Filter user capabilities to allow Support Agents to edit Tickets
*
* @param array  $caps    Required capabilities for this action.
* @param string $cap     Capability name.
* @param int    $user_id The user ID.
* @param array  $args    Adds the context to the cap. Typically the object ID.
* @return returns the correct set of primitive capabilities this user must have to allow editing
*/
function eddstix_map_meta_cap( $caps, $cap, $user_id, $args ) {

	switch( $cap ) {
		case 'edit_edd_support_ticket' :
		case 'edit_post' :
			if ( empty( $args[0] ) ) {
				break;
			}
			$ticket = get_post( $args[0] );
			if ( empty( $ticket ) ) {
				break;
			}
			if ( 'edd_ticket' !==  $ticket->post_type ) {
				break;
			}
			$assignee = intval( get_post_meta( $ticket->ID, '_eddstix_assignee', true ) );
			if ( ! $assignee ) {
				$assignee = isset( $_REQUEST['eddstix_assignee'] ) ? $_REQUEST['eddstix_assignee'] : '';
			}

			// Grant Agent access to their own tickets.
			// If agent_see_all is enabled, grant agents access to all tickets if:
			// 		this is tickets admin list, or
			//		editing/replying to a ticket.

			if ( ( $assignee && $assignee == $user_id ) ||
				(
					true === boolval( eddstix_get_option( 'agent_see_all' ) ) &&
					(
						( isset( $_REQUEST['post_type'] ) && 'edd_ticket' == $_REQUEST['post_type'] ) || // tickets admin list
						( isset( $_REQUEST['action'] ) && 'edit' == $_REQUEST['action'] && isset( $_REQUEST['post'] ) && 'edd_ticket' == get_post( $_REQUEST['post'] )->post_type ) // editing/replying to a ticket
					)
				)
			)
			{
				$caps = array('edit_edd_support_tickets');
			}
			break;

		case 'edit_others_edd_support_tickets' :
				
			if ( isset( $_POST['post_ID'] ) ) {

				$assignee = intval( get_post_meta( $_POST['post_ID'], '_eddstix_assignee', true ) );

				if ( ! $assignee ) {
					$assignee = isset( $_REQUEST['eddstix_assignee'] ) ? $_REQUEST['eddstix_assignee'] : '';
				}
						
				// Grant agents access to own tickets,
				// Also grant access to all tickets if agent_see_all is enabled

				if ( ( $assignee && $assignee == $user_id ) ||
					(
						true === boolval( eddstix_get_option( 'agent_see_all' ) ) &&
						(
							isset( $_REQUEST['post_type'] ) && 'edd_ticket' == $_REQUEST['post_type']
							)
						)
					)
				{
					$caps = array('edit_edd_support_tickets');
				}
			}
		break;
	}
	return $caps;
}
