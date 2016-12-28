<?php
/**
 * Process registration form
 */
function eddstix_process_register_form( $data = false ) {

	// Block spam bots
	if ( ! empty( $_POST['pooh_hundred_acre_wood_field'] ) ) {
		return false;
	}
	global $post;
	$allow = intval( eddstix_get_option( 'allow_registrations' ) );

	/* Are registrations closed? */
	if ( empty( $allow ) ) {
		// This will never be reached since form will not appear if reg is closed,
		// unless they alter the template.
		wp_redirect( esc_url_raw( add_query_arg( array(
			'message' => eddstix_create_notification( __( 'Registrations are currently not allowed.', 'edd-support-tickets' ) ),
			get_permalink( $post->ID )
		) ) ) );
		exit;
	}

	if ( false === $data ) {
		$data = $_POST;
	}

	$first_name = empty( $data['first_name'] ) ? false : sanitize_text_field( $data['first_name'] );
	$email      = empty( $data['email'] ) ? false : sanitize_email( $data['email'] );
	$password   = empty( $data['password'] ) ? false : $data['password'];
	
	/* Save the user information in session to pre populate the form in case of error. */
	$_SESSION['eddstix_registration_form'] = array(
		'first_name'	=> $first_name,
		'email'			=> $email,
		'password'		=> $password
	);

	// This hook is triggered all the time even if the checks don't pass.
	do_action( 'eddstix_pre_register_account', $data );

	/* Make sure we have the necessary data. */
	if ( ! $email || ! $first_name || ! $password ) {
		wp_redirect( esc_url_raw( add_query_arg( array(
			'message' => eddstix_create_notification( apply_filters( 'eddstix_blank_fields_notice', __( 'You didn\'t correctly fill all the fields.', 'edd-support-tickets' ) ) ),
			get_permalink( $post->ID )
		) ) ) );
		exit;
	}

	// Check if email exists
	$exists_msg = apply_filters( 'eddstix_acct_exists_notice', __( 'An account with this email address already exists. Please log in.', 'edd-support-tickets' ) );

	if ( email_exists( $email ) ) {
		wp_redirect( esc_url_raw( add_query_arg( array(
					'message' => eddstix_create_notification( $exists_msg ),
					get_permalink( $post->ID )
		) ) ) );
		exit;
	}

	$username = strtolower( $first_name );
	$taken_name = get_user_by( 'login', $username );
	
	// If name is not already taken as username, assign it.

	if ( empty( $taken_name ) ) {

		$new_user_login = $username;

	} else {

		$taken_emailname = get_user_by( 'login', $email );

		// If email is not already taken AS A USERNAME, assign it.
		if ( empty( $taken_emailname ) ) {
			$new_user_login = $email;
		} else {

			// Create a unique username
			$suffix = 1;
			do {
				$unique_username = sanitize_user( $username . $suffix );
				$user = get_user_by( 'login', $unique_username );
				$suffix ++;
			} while ( is_a( $user, 'WP_User' ) );
			$new_user_login = $unique_username;
		}

	}
	
	$greeting = apply_filters( 'eddstix_welcome_new_user', __( 'Your account has been successfully created. You can now submit a support ticket.', 'edd-support-tickets' ) );

	if ( 2 === $allow ) {
		// Everyone can register
		$register_clearance = true;

	} elseif ( 1 === $allow ) {
		// Only EDD customers can register.

		/** 
		 * Check if email is from an existing guest customer. 
		 * If yes, register & log in.
		 *
		*/

		$customer = EDD()->customers->get_customer_by( 'email', $email );

		// Is it a customer?

		if ( $customer ) {

			// Did they actually make a purchase?

			if ( ! empty( $customer->purchase_count ) ) {

				// Is this customer a registered user?

				// I've seen that user_id can be -1 or null ... so target guest buyers like so...
				$user_id = ! empty( $customer->user_id ) ? intval( $customer->user_id ) : 0;
				if ( $user_id > 0 ) {

					wp_redirect( esc_url_raw( add_query_arg( array(
						'message' => eddstix_create_notification( $exists_msg ),
						get_permalink( $post->ID )
					) ) ) );
					exit;

				} else {
					// register this customer
					$register_clearance = true;
				}
					
			} else {

				// This "customer" has no purchases, so not a true customer.
				wp_redirect( esc_url_raw( add_query_arg( array(
					'message' => eddstix_create_notification( apply_filters( 'eddstix_buy_notice', __( 'Sorry, but you must purchase something in order to receive support.', 'edd-support-tickets' ) ) ),
					get_permalink( $post->ID )
				) ) ) );
				exit;
			}
				
		} else {

			// Not a customer
	
			$msg = apply_filters( 'eddstix_not_customer_notice', __( 'Sorry, but your email address is not recognized as a customer email. Please try the email address that you used to make a purchase.', 'edd-support-tickets' ) );
	
			wp_redirect( esc_url_raw( add_query_arg( array(
				'message' => eddstix_create_notification( $msg ),
				get_permalink( $post->ID )
			) ) ) );
			exit;

		} // End customer check

	} // end if only EDD customers can register.

	if ( ! empty( $register_clearance ) ) {

		$args = apply_filters( 'eddstix_insert_user_data', array(
			'user_login'	=> $new_user_login,
			'user_email'	=> $email,
			'first_name'	=> $first_name,
			'display_name'	=> "$first_name",
			'role'			=> 'subscriber'
		) );

		// only add user if we have a unique userlogin
		if ( ! empty( $new_user_login ) ) {
			$user_data = array(
				'user_email' 	=> $email,
				'user_login' 	=> $new_user_login,
				'user_pass'		=> $password,
				'first_name'	=> $first_name,
			);

			$new_user_id = wp_insert_user( $user_data );


			if ( is_wp_error( $new_user_id ) ) {

				do_action( 'eddstix_register_account_failed', $new_user_id, $args );

				$error = $new_user_id->get_error_message();

				wp_redirect( esc_url_raw( add_query_arg( array(
					'message' => eddstix_create_notification( $error ),
					get_permalink( $post->ID )
				) ) ) );
				exit;

			} else {
			
				if ( $new_user_id ) {

					// Fired right after the user is successfully added to the database.
					do_action( 'eddstix_register_account_after', $new_user_id, $args );

					/* Delete the user information data from session. */
					unset( $_SESSION['eddstix_registration_form'] );					
					$disable_new_reg_notify = eddstix_get_option( 'disable_new_reg_notify' );
					if ( empty( $disable_new_reg_notify ) ) {
						wp_new_user_notification( $new_user_id );
					}
					if ( headers_sent() ) {
						wp_redirect( esc_url_raw( add_query_arg( array(
							'message' => eddstix_create_notification( __( 'Your account has been created. Please log in.', 'edd-support-tickets' ) ),
							get_permalink( $post->ID )
						) ) ) );
						exit;
					}

					if ( ! is_user_logged_in() ) {

						/* Automatically log the user in */
						wp_set_current_user( $new_user_id, $new_user_login );
						wp_set_auth_cookie( $new_user_id );
						do_action( 'wp_login', $new_user_login, get_userdata( $new_user_id ) );
						wp_redirect( esc_url_raw( add_query_arg( array(
							'message' => eddstix_create_notification( $greeting ),
							get_permalink( $post->ID )
						) ) ) );
						exit;
					}

				}
			}
		} else {
			// email exists.
			wp_redirect( esc_url_raw( add_query_arg( array(
				'message' => eddstix_create_notification( $exists_msg ),
				get_permalink( $post->ID )
			) ) ) );
			exit;

		}

	}
	return false;
}

/**
 * Get temporary user data.
 *
 * If the user registration fails some of the user data is saved
 * (all except the password) and can be used to pre-populate the registration
 * form after the page reloads. This function returns the desired field value
 * if any.
 *
 * @param  string $field Name of the field to get the value for
 *
 * @return string        The sanitized field value if any, an empty string otherwise
 */
function eddstix_get_registration_field_value( $field ) {

	if ( isset( $_SESSION ) && isset( $_SESSION['eddstix_registration_form'][ $field ] ) ) {
		return sanitize_text_field( $_SESSION['eddstix_registration_form'][ $field ] );
	} else {
		return '';
	}

}

/**
 * Try to log the user if credentials are submitted.
 *
 * If credentials are passed through the POST data
 * we try to log the user in.
 */
function eddstix_try_login() {

	global $post;

	if ( isset( $_POST['log'] ) ) {

		$login = wp_signon();

		if ( is_wp_error( $login ) ) {
			$error = $login->get_error_message();
			wp_redirect( esc_url_raw( add_query_arg( array( 'message' => urlencode( base64_encode( json_encode( $error ) ) ) ), get_permalink( $post->ID ) ) ) );
			exit;
		} elseif ( is_a( $login, 'WP_User' ) ) {
			wp_redirect( get_permalink( $post->ID ) );
			exit;
		} else {
			wp_redirect( esc_url_raw( add_query_arg( array( 'message' => urlencode( base64_encode( json_encode( __( 'We were unable to log you in for an unknown reason.', 'edd-support-tickets' ) ) ) ) ), get_permalink( $post->ID ) ) ) );
			exit;
		}
	}
}

/**
 * Checks if current user can view a specific ticket.
 *
 * @param  integer $post_id ID of the ticket
 *
 * @return boolean
 */
function eddstix_can_view_ticket( $post_id ) {

	/* Only logged in users can view */
	if ( ! is_user_logged_in() ) {
		return false;
	}
	$user 		= get_current_user_id();
	$post 		= get_post( $post_id );
	$author 	= intval( $post->post_author );
	$assignee 	= intval( get_post_meta( $post_id, '_eddstix_assignee', true ) );

	if ( in_array( $user, array( $author, $assignee ) ) ||
		current_user_can( 'manage_edd_ticket_settings' ) ||
		true === boolval( eddstix_get_option( 'agent_see_all' ) ) && current_user_can( 'edit_edd_support_tickets' ) ) {
			return true;
	}
	return false;
}

/**
 * Check if the current user can reply from the frontend.
 *
 * @param  boolean $admins_allowed Shall admins/agents be allowed to reply from the frontend
 * @param  integer $post_id        ID of the ticket to check
 *
 * @return boolean                 True if the user can reply
 */
function eddstix_can_reply_ticket( $admins_allowed = false, $post_id = null ) {

	if ( is_null( $post_id ) ) {
		global $post;
		$post_id = $post->ID;
	}
	// Allow admins to post through front-end. The filter overwrites the function parameter.
	$admins_allowed = apply_filters( 'eddstix_can_agent_reply_frontend', $admins_allowed );
	$post           = get_post( $post_id );
	$author_id      = $post->post_author;

	if ( is_user_logged_in() ) {

		global $current_user;
		if ( ! current_user_can( 'publish_edd_support_tickets' ) ) {
			return false;
		}

		$user_id = $current_user->data->ID;

		/* If the current user is the author then yes */
		if ( $user_id == $author_id ) {
			return true;
		} else {
			if ( current_user_can( 'edit_edd_support_tickets' ) && true === $admins_allowed ) {
				return true;
			} else {
				return false;
			}

		}

	} else {
		return false;
	}

}
/**
 * Get user role nicely formatted.
 *
 * @param  string $role User role
 *
 * @return string       Nicely formatted user role
 */
function eddstix_get_user_nice_role( $role ) {
	// Remove the prefix on EEDSTIX roles
	if ( 'eddstix_' === substr( $role, 0, 8 ) ) {
		// replace with prefix Support 
		$role = str_replace( 'eddstix_', __( 'Support ', 'edd-support-tickets' ), $role );
	}

	// Covert separators to spaces
	$role = str_replace( array( '-', '_' ), ' ', $role );
	return ucwords( $role );
}

/**
 * Get support staff available for auto-assignment.
 *
 * @param array $roles Roles to assign tickets to
 * @return array List of user IDs
 */
function eddstix_get_support_staff( $roles ) {

	/* Check if we have a result already cached. */
	$result = get_transient( 'eddstix_get_support_staff' );


	if ( false !== $result ) {

		$all_staff = get_users( array( 'include' => (array) $result ) );

	} else {
		$all_staff = array();
		foreach ( $roles as $role ) :

			$results = get_users( array( 'role' => $role ) );

			if ($results) {
				$all_staff = array_merge( $all_staff, $results );
			}
		
		endforeach;
	}

	/* The array where we save all user IDs we want to keep. */
	$staff_ids = array();

	/* Loop through the users list and filter them */
	foreach ( $all_staff as $staff ) {

		/* Check for edit_edd_support_tickets capability */
		if ( ! array_key_exists( 'edit_edd_support_tickets', $staff->allcaps ) ) {
			continue;
		}

		/* Add this staff to our final list. */
		array_push( $staff_ids, $staff->ID );

	}

	/* avoid running this query too many times. */
	set_transient( 'eddstix_get_support_staff', $staff_ids, apply_filters( 'eddstix_get_staff_cache_expiration', 60 * 60 ) );

	return apply_filters( 'eddstix_get_support_staff', $staff_ids );
}

/**
 * Get users with required capabilities for this plugin.
 *
 * @param array $args Arguments used to filter the users
 * @return array An array of users objects
 */
function eddstix_get_users( $args = array() ) {

	$defaults = array(
		'cap'         => '',
		'cap_exclude' => '',
	);

	/* The array where we save all users we want to keep. */
	$list = array();

	$args = wp_parse_args( $args, $defaults );

	$all_users = get_users();


	/* Loop through the users list and filter them */
	foreach ( $all_users as $user ) {

		/* Check for required capability */
		if ( ! empty( $args['cap'] ) ) {
			if ( ! array_key_exists( $args['cap'], $user->allcaps ) ) {
				continue;
			}
		}

		/* Check for excluded capability */
		if ( ! empty( $args['cap_exclude'] ) ) {
			if ( array_key_exists( $args['cap_exclude'], $user->allcaps ) ) {
				continue;
			}
		}

		/* Now we add this user to our final list. */
		array_push( $list, $user );
	}

	return apply_filters( 'eddstix_get_users', $list );

}

/**
 * List existing support staff for the Default Agent option.
 * 
 * This does not set the staff available for auto-assigment.
 * This lists all existing support staff on the settings page to 
 * allow you to then choose one as the default agent. Existing support staff
 * includes roles of admin, Support Supervisor, Support Agent, and EDD 
 * shop_manager and shop_worker.
 *
 * @return array A list of user ids with their display name
 */
function eddstix_existing_staff() {

	$list = array();

	/* List all users */
	$all_users = eddstix_get_users( array( 'cap' => 'edit_edd_support_tickets' ) );

	foreach ( $all_users as $user ) {
		$user_id          = $user->ID;
		$user_name        = $user->data->display_name;
		$list[ $user_id ] = $user_name;
	}

	return apply_filters( 'eddstix_existing_staff', $list );
}

/**
 * Creates a dropdown list of users for the Stakeholders metabox.
 *
 * @param  array $args Arguments
 * @return string Users dropdown
 */
function eddstix_users_dropdown( $args = array() ) {

	global $current_user, $post;

	$defaults = array(
		'name'           => 'eddstix_user',
		'id'             => '',
		'class'          => '',
		'exclude'        => array(),
		'selected'       => '',
		'cap'            => '',
		'cap_exclude'    => '',
		'agent_fallback' => false,
		'please_select'  => false,
		'select2'        => false,
		'disabled'       => false,
	);

	$args = wp_parse_args( $args, $defaults );

	/* List all users */
	$all_users = eddstix_get_users( array( 'cap' => $args['cap'], 'cap_exclude' => $args['cap_exclude'], 'exclude' => $args['exclude'] ) );

	/**
	 * We use a marker to keep track of when a user was selected.
	 * This allows for adding a fallback if nobody was selected.
	 * 
	 * @var boolean
	 */
	$marker = false;

	$options = '';

	/* The ticket is being created, use the current user by default */
	if ( ! empty( $args['selected'] ) ) {
		$user = get_user_by( 'id', intval( $args['selected'] ) );
		if ( false !== $user && ! is_wp_error( $user ) ) {
			$marker = true;
			$options .= "<option value='{$user->ID}' selected='selected'>{$user->data->display_name}</option>";
		}
	}

	// Agents cannot assign tickets to other agents, unless agent_see_all is enabled
	$agent_see_all = eddstix_get_option( 'agent_see_all' );
	if ( ( 'eddstix_assignee' == $args['name'] ) && ( false === $marker ) && empty( $agent_see_all ) && ( ! current_user_can( 'manage_edd_ticket_settings' ) ) ) {
			$options .= "<option value='{$current_user->ID}' selected='selected'>{$current_user->data->display_name}</option>";
	} else {

		foreach ( $all_users as $user ) {

			/* This user was already added, skip it */
			if ( ! empty( $args['selected'] ) && $user->ID === intval( $args['selected'] ) ) {
				continue;
			}

			$user_id       = $user->ID;
			$user_name     = $user->data->display_name;
			$selected_attr = '';

			if ( false === $marker ) {
				if ( false !== $args['selected'] ) {
					if ( ! empty( $args['selected'] ) ) {
						if ( $args['selected'] === $user_id ) {
							$selected_attr = 'selected="selected"';
						}
					} else {
						if ( isset( $post ) && $user_id == $post->post_author ) {
							$selected_attr = 'selected="selected"';
						}
					}
				}
			}

			/* Set the marker as true to avoid selecting more than one user */
			if ( ! empty( $selected_attr ) ) {
				$marker = true;
			}

			/* Output the option */
			$options .= "<option value='$user_id' $selected_attr>$user_name</option>";

		}
	}

	/* In case there is no selected user yet we add the post author, or the currently logged user (most likely an admin) */
	if ( true === $args['agent_fallback'] && false === $marker ) {
		$fallback    = $current_user;
		$fb_selected = false === $marker ? 'selected="selected"' : '';
		$options .= "<option value='{$fallback->ID}' $fb_selected>{$fallback->data->display_name}</option>";
	}
	$contents = eddstix_dropdown( $args, $options );
	return $contents;
}

/**
 * Display a dropdown of the support customers.
 *
 * Wrapper function for eddstix_users_dropdown where
 * the cap_exclude is set to exclude all users with
 * the capability to edit a ticket (exclude staff). 
 * For the purpose of finding Support customers only, no staff.
 *
 * @param  array $args Arguments
 * @return string HTML dropdown
 */
function eddstix_support_customers_dropdown( $args = array() ) {
	$args['cap_exclude']	= 'edit_edd_support_tickets';
	$args['cap']			= 'publish_edd_support_tickets';
	echo eddstix_users_dropdown( $args );
}