<?php
/**
 * EDD Support Tickets
 *
 * @package 	EDD_Support_Tickets
 * @author 		Isabel Castillo
 * @license		GPL-2.0+
 * @copyright 	Copyright (c) 2015-2016, Isabel Castillo
 */

class EDD_Support_Tickets {

	/**
	 * Instance of this class.
	 *
	 * @var      object
	 */
	protected static $instance = null;

	/**
	 * Initialize the plugin by setting localization and loading public scripts
	 * and styles.
	 */
	private function __construct() {

		add_action( 'init', array( 'EDDSTIX_Product_EDD', 'get_instance' ), 11, 0 );
		add_action( 'plugins_loaded', array( 'EDDSTIX_Ticket_Post_Type', 'get_instance' ), 11, 0 );
		if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {

			// Generate the object used for the custom loop for displaying ticket replies
			add_action( 'wp', array( $this, 'get_replies_object' ), 10, 0 );
			add_action( 'init', array( $this, 'load_textdomain' ) );
			add_action( 'init', array( $this, 'init' ), 11, 0 );
			
			// Add a link to agent's tickets in the toolbar
			add_action( 'admin_bar_menu', array( $this, 'toolbar_tickets_link' ), 999, 1 );
			// Load public-facing scripts
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10, 0 );
			add_action( 'template_redirect', array( $this, 'redirect_archive' ), 10, 0 );
			add_filter( 'template_include', array( $this, 'template_include' ), 10, 1 );
			add_filter( 'authenticate', array( $this, 'email_signon' ), 20, 3 );

			/* Hook all email notifications */
			add_action( 'eddstix_open_ticket_after', array( $this, 'notify_confirmation' ), 10, 2 );
			add_action( 'eddstix_ticket_assigned', array( $this, 'notify_assignment' ), 10, 2 );
			add_action( 'eddstix_add_reply_after', array( $this, 'notify_reply' ), 10, 2 );
			add_action( 'eddstix_after_close_ticket_admin', array( $this, 'notify_close' ), 10, 1 );

			/**
			 * Modify the ticket single page content.
			 *
			 * eddstix_single_ticket() is located in includes/functions-templating.php
			 */
			add_filter( 'the_content', 'eddstix_single_ticket', 10, 1 );
		}
	}

	/**
	 * Actions run on plugin initialization.
	 *
	 * A certain number of things can possibly run after
	 * the plugin initialized. Those actions are fired from here
	 * if the trigger is present.
	 *
	 * @return void
	 */
	public function init() {
		/**
		 * Log user in.
		 *
		 * If we have a login in the post data we try to log the user in.
		 * The login process relies on the WordPress core functions. If the login
		 * is successful, the user is redirected to the page he was requesting,
		 * otherwise the standard WordPress error messages are returned.
		 *
		 */
		if ( isset( $_POST['eddstix_login'] ) ) {
			add_action( 'wp', 'eddstix_try_login' );
		}

		/**
		 * Register a new account.
		 *
		 * If eddstix_registration is passed we trigger the account registration function.
		 * The registration function will do a certain number of checks and if all of them
		 * are successful, a new user is created using the WordPress core functions.
		 */
		if ( isset( $_POST['eddstix_registration'] ) ) {
			add_action( 'wp', 'eddstix_process_register_form', 10, 0 );
		}

		/**
		 * Run custom actions.
		 *
		 * The plugin can run a number of custom actions triggered by a URL parameter.
		 * If the $action parameter is set in the URL we run this method.
		 *
		 */
		if ( isset( $_GET['action'] ) ) {
			add_action( 'wp', array( $this, 'custom_actions' ) );
		}

		/**
		 * Open a new ticket.
		 *
		 * If a ticket title is passed in the post we trigger the function that adds
		 * new tickets. The function does a certain number of checks and has several
		 * action hooks and filters. Post-insertion actions like adding post metas
		 * and redirecting the user are run from here.
		 *
		 */
		if ( isset( $_POST['eddstix_title'] ) ) {

			// Verify the nonce first
			if ( ! isset( $_POST['eddstix_nonce'] ) || ! wp_verify_nonce( $_POST['eddstix_nonce'], 'new_ticket' ) ) {

				/* Save the input */
				eddstix_save_values();

				// Redirect to submit page
				wp_redirect( esc_url_raw( add_query_arg( array( 'message' => 4 ), get_permalink( eddstix_get_option( 'ticket_submit' ) ) ) ) );
				exit;
			}

			$ticket_id = eddstix_open_ticket( array( 'title' => $_POST['eddstix_title'], 'message' => $_POST['eddstix_message'] ) );

			/* Submission failure */
			if ( false === $ticket_id ) {

				/* Save the input */
				eddstix_save_values();

				/**
				 * Redirect to the newly created ticket
				 */
				$submit = eddstix_get_option( 'ticket_submit' );
				$url = esc_url_raw( add_query_arg( array( 'message' => 6 ), get_permalink( $submit ) ) );
				eddstix_redirect( 'ticket_added_failed', $url, $submit );
				exit;
			}

			/* Submission succeeded */
			else {

				/**
				 * Empty the temporary sessions
				 */
				unset( $_SESSION['eddstix_submission_form'] );
				unset( $_SESSION['eddstix_submission_error'] );

				/**
				 * Redirect to the newly created ticket
				 */
				$url = esc_url_raw( add_query_arg( array( 'message' => '1' ), get_permalink( $ticket_id ) ) );
				eddstix_redirect( 'ticket_added', $url, $ticket_id );
				exit;
			}
		}

		/**
		 * Save a new reply.
		 *
		 * This adds a new reply to an existing ticket. The ticket
		 * can possibly be closed by the user in which case we update
		 * the post meta if the reply submission is successful.
		 *
		 */
		if ( isset( $_POST['eddstix_user_reply'] ) ) {

			/**
			 * Define if the reply can be submitted empty or not.
			 *
			 * @var boolean
			 */
			$can_submit_empty = apply_filters( 'eddstix_can_reply_be_empty', false );

			/**
			 * Get the parent ticket ID.
			 */
			$parent_id = intval( $_POST['ticket_id'] );

			if ( empty( $_POST['eddstix_user_reply'] ) && false === $can_submit_empty ) {
				eddstix_redirect( 'reply_not_added', add_query_arg( array( 'message' => eddstix_create_notification( __( 'You cannot submit an empty reply.', 'edd-support-tickets' ) ) ), get_permalink( $parent_id ) ), $parent_id );
				exit;
			}


			/* Sanitize the data */
			$data = array( 'post_content' => wp_kses( $_POST['eddstix_user_reply'], wp_kses_allowed_html( 'post' ) ) );

			/* Add the reply */
			$reply_id = eddstix_add_reply( $data, $parent_id );

			/* Possibly close the ticket */
			if ( isset( $_POST['eddstix_close_ticket'] ) && false !== $reply_id ) {
				eddstix_close_ticket( intval( $_POST['ticket_id'] ) );
			}

			if ( false === $reply_id ) {
				eddstix_redirect( 'reply_added_failed', add_query_arg( array( 'message' => '7' ), get_permalink( $parent_id ) ) );
				exit;
			} else {

				/**
				 * Delete the activity transient.
				 */
				delete_transient( "eddstix_activity_meta_post_$parent_id" );
				$msg_text = isset( $_POST['eddstix_close_ticket'] ) ? '14' : '8';
				eddstix_redirect( 'reply_added', add_query_arg( array( 'message' => $msg_text ), get_permalink( $parent_id ) ) . "#reply-$reply_id", $parent_id );
				exit;
			}
		}

	}

	/**
	 * Allow email to be used as the login.
	 *
	 * @param  WP_User|WP_Error|null $user     User to authenticate.
	 * @param  string $username User login
	 * @param  string $password User password
	 *
	 * @return object WP_User if authentication succeed, WP_Error on failure
	 */
	public function email_signon( $user, $username, $password ) {

		/* Authentication was successful, we don't touch it */
		if ( is_object( $user ) && is_a( $user, 'WP_User' ) ) {
			return $user;
		}

		/**
		 * If the $user isn't a WP_User object nor a WP_Error
		 * we don' touch it and let WordPress handle it.
		 */
		if ( ! is_wp_error( $user ) ) {
			return $user;
		}

		/**
		 * We only wanna alter the authentication process if the username was rejected.
		 * If the error is different, we let WordPress handle it.
		 */
		if ( 'invalid_username' !== $user->get_error_code() ) {
			return $user;
		}

		/**
		 * If the username is not an email there is nothing else we can do,
		 * the error is probably legitimate.
		 */
		if ( ! is_email( $username ) ) {
			return $user;
		}

		/* Try to get the user with this email address */
		$user_data = get_user_by( 'email', $username );

		/**
		 * If there is no user with this email the error is legitimate
		 * so let's just return it.
		 */
		if ( false === $user_data || ! is_a( $user_data, 'WP_User' ) ) {
			return $user;
		}

		return wp_authenticate_username_password( null, $user_data->data->user_login, $password );

	}

	/**
	 * Run pre-defined actions.
	 *
	 * Specific actions can be performed on page load.
	 * Those actions are triggered by a URL parameter ($action).
	 *
	 * @return void
	 */
	public function custom_actions() {

		if ( ! isset( $_GET['action'] ) ) {
			return;
		}

		$action = sanitize_text_field( $_GET['action'] );

		switch( $action ) {
			case 'reopen':
				if ( isset( $_GET['ticket_id'] ) ) {
					eddstix_reopen_ticket( $_GET['ticket_id'] );
				}
				eddstix_redirect( 'ticket_reopen', add_query_arg( array( 'message' => '9' ), get_permalink( intval( $_GET['ticket_id'] ) ) ), intval( $_GET['ticket_id'] ) );
				exit;

			break;
		}
	}

	/**
	 * Return an instance of this class.
	 *
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Fired when the plugin is activated.
	 *
	 * @param    boolean    $network_wide    True if WPMU superadmin uses "Network Activate" action, false if WPMU is disabled or plugin is activated on an individual blog.
	 */
	public static function activate( $network_wide ) {

		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			if ( $network_wide  ) {
				// Get all blog ids
				$blog_ids = self::get_blog_ids();
				foreach ( $blog_ids as $blog_id ) {
					switch_to_blog( $blog_id );
					self::single_activate();
				}
				restore_current_blog();
			} else {
				self::single_activate();
			}
		} else {
			self::single_activate();
		}
	}

	/**
	 * Get all blog ids of blogs in the current network that are:
	 * - not archived
	 * - not spam
	 * - not deleted
	 *
	 * @return   array|false    The blog ids, false if no matches.
	 */
	private static function get_blog_ids() {

		global $wpdb;

		// get an array of blog ids
		$sql = "SELECT blog_id FROM $wpdb->blogs
			WHERE archived = '0' AND spam = '0'
			AND deleted = '0'";

		return $wpdb->get_col( $sql );

	}

	/**
	 * Fired for each blog when the plugin is activated.
	 */
	private static function single_activate() {

		// Full list of capabilities. Given to Support Supervisors and EDD Shop Managers.

		$full_cap = array(
				"edit_edd_support_tickets",
				"edit_others_edd_support_tickets",
				"edit_private_edd_support_tickets",
				"edit_published_edd_support_tickets",
				"publish_edd_support_tickets",
				"read_private_edd_support_tickets",
				"delete_edd_support_tickets",
				"delete_private_edd_support_tickets",
				"delete_published_edd_support_tickets",
				"delete_others_edd_support_tickets",
				// Terms
				"manage_edd_support_ticket_terms",
				"edit_edd_support_ticket_terms",
				"delete_edd_support_ticket_terms",
				"assign_edd_support_ticket_terms",
				// Custom
				"manage_edd_ticket_settings"
		);

		/**
		 * Partial list of capabilities.
		 *
		 * A partial list of capabilities given to Support Agents and 
		 * EDD Shop Workers. Agents should be used if no other
		 * access than tickets is required. 
		 * @var array
		 */
		$agent_cap = array(
				"edit_edd_support_tickets",
				"edit_private_edd_support_tickets",
				"edit_published_edd_support_tickets",
				"publish_edd_support_tickets",
				"read_private_edd_support_tickets",
				// Terms
				"manage_edd_support_ticket_terms",
				"edit_edd_support_ticket_terms",
				"delete_edd_support_ticket_terms",
				"assign_edd_support_ticket_terms"
		);

		// Very limited list of capabilities for subscribers. 
		$customer_cap = 'publish_edd_support_tickets';

		// Get roles to copy capabilities from
		$editor 		= get_role( 'editor' );
		$subscriber 	= get_role( 'subscriber' );
		$admin 			= get_role( 'administrator' );
		$shop_manager	= get_role( 'shop_manager' );
		$shop_worker	= get_role( 'shop_worker' );

		// Create our roles
		$supervisor = add_role( 'eddstix_supervisor', __( 'Support Supervisor', 'edd-support-tickets' ), $editor->capabilities );
		$agent = add_role( 'eddstix_agent', __( 'Support Agent', 'edd-support-tickets' ), array(
			'read'                   => true,
			'upload_files'           => true,
		) );

		// Add full plugin capacities to admin, support supervisors, and shop managers

		foreach ( $full_cap as $cap ) {
			if ( null != $admin ) {
				$admin->add_cap( $cap );
			}
			if ( null != $supervisor ) {
				$supervisor->add_cap( $cap );
			}
			if ( null != $shop_manager ) {
				$shop_manager->add_cap( $cap );
			}			
		}

		// Add limited capacities to support agents and shop workers

		foreach ( $agent_cap as $cap ) {
			if ( null != $agent ) {
				$agent->add_cap( $cap );
			}
			if ( null != $shop_worker ) {
				$shop_worker->add_cap( $cap );
			}
		}

		 // Add limited capacities to subscribers

		if ( null != $subscriber ) {
			$subscriber->add_cap( $customer_cap );
		}

		// If no existing settings, set up some default ones.
		if ( false == get_option( 'eddstix_settings' ) ) {
			$options = array();
			foreach( eddstix_get_registered_settings() as $tab => $settings ) {
				foreach ( $settings as $option ) {
					if( ! empty( $option['std'] ) ) {
						$options[ $option['id'] ] = $option['std'];
					}
				}
			}
			update_option( 'eddstix_settings', $options );
		}

		add_option( 'eddstix_setup', 'pending' );
		add_option( 'eddstix_ask_multiple_products', 'pending' );
	}

	/**
	 * Load the plugin text domain for translation.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'edd-support-tickets', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Register and enqueue public-facing JavaScript files.
	 */
	public function enqueue_scripts() {

		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

		wp_register_script( 'eddstix', EDDSTIX_URL . 'assets/public/js/eddstix' . $suffix . '.js', array( 'jquery' ), EDDSTIX_VERSION, true );

		if ( ! is_admin() && eddstix_is_plugin_page() ) {
			wp_enqueue_script( 'eddstix' );
			wp_register_style( 'eddstix', EDDSTIX_URL . 'assets/public/css/eddstix' . $suffix . '.css', array(), EDDSTIX_VERSION );
			wp_enqueue_style( 'eddstix' );

		}

	}

	/**
	 * Construct the replies query.
	 *
	 * The replies query is used as a custom loop to display
	 * a ticket's replies in a clean way. The resulting object
	 * is made global as $eddstix_replies.
	 *
	 * @return void
	 */
	public function get_replies_object() {

		global $wp_query, $eddstix_replies;

		if ( isset( $wp_query->post ) && 'edd_ticket' === $wp_query->post->post_type ) {

			$args = apply_filters( 'eddstix_replies_object_args', array(
				'post_parent'            => $wp_query->post->ID,
				'post_type'              => 'edd_ticket_reply',
				'post_status'            => array( 'read', 'unread' ),
				'order'                  => eddstix_get_option( 'replies_order', 'ASC' ),
				'orderby'                => 'date',
				'posts_per_page'         => -1,
				'no_found_rows'          => false,
				'cache_results'          => false,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
				
			) );

			$eddstix_replies = new WP_Query( $args );

		}

	}

	/**
	 * Send email confirmation.
	 *
	 * Sends an email confirmation to the client.
	 *
	 * @param  integer $ticket_id ID of the new ticket
	 * @param  array   $data      Ticket data
	 * @return void
	 */
	public function notify_confirmation( $ticket_id, $data ) {
		eddstix_email_notify( $ticket_id, 'submission_confirmation' );
	}

	/**
	 * Send email assignment notification.
	 *
	 * Sends an email to the agent that a new ticket has been assigned.
	 *
	 * @param  integer $ticket_id ID of the new ticket
	 * @param  integer $agent_id  ID of the agent who's assigned
	 * @return void
	 */
	public function notify_assignment( $ticket_id, $agent_id ) {
		eddstix_email_notify( $ticket_id, 'new_ticket_assigned' );
	}

	public function notify_reply( $reply_id, $data ) {

		/* If the ID is set it means we're updating a post and NOT creating. In this case no notification. */
		if ( isset( $data['ID'] ) ) {
			return;
		}
		$case = user_can( $data['post_author'], 'edit_edd_support_tickets' ) ? 'agent_reply' : 'client_reply';
		eddstix_email_notify( $reply_id, $case );
	}

	public function notify_close( $ticket_id ) {
		eddstix_email_notify( $ticket_id, 'ticket_closed' );
	}

	/**
	 * Redirect ticket archive page.
	 *
	 * We don't use the archive page to display the ticket
	 * so let's redirect it to the user's tickets list instead.
	 *
	 * @return void
	 */
	public function redirect_archive() {
		if ( is_post_type_archive( 'edd_ticket' ) ) {
			eddstix_redirect( 'archive_redirect', get_permalink( eddstix_get_option( 'ticket_list' ) ) );
		}
	}

	/**
	 * Change ticket template.
	 *
	 * By default WordPress uses the single.php template
	 * to display the post type single page as a custom one doesn't exist.
	 * However we don't want all the meta that are usually displayed on a single.php
	 * template. For that reason we switch to the page.php template that usually
	 * doesn't contain all the post metas and author bio.
	 *
	 * @param  string $template Path to template
	 * @return string           Path to (possibly) new template
	 */
	public function template_include( $template ) {

		if ( ! is_singular( 'edd_ticket' ) ) {
			return $template;
		}

		$filename      = explode( '/', $template );
		$template_name = $filename[count($filename)-1];
		
		/* Don't change the template if it's already a custom one */
		if ( 'single-ticket.php' === $template_name ) {
			return $template;
		}

		unset( $filename[count($filename)-1] ); // Remove the template name
		$filename = implode( '/', $filename );
		$filename = $filename . '/page.php';

		if ( file_exists( $filename ) ) {
			return $filename;
		} else {
			return $template;
		}

	}
	/**
	 * Add link to agent's tickets to admin tool bar.
	 *
	 * @param  object $wp_admin_bar The WordPress toolbar object
	 * @return void
	 */
	public function toolbar_tickets_link( $wp_admin_bar ) {
		if ( ! current_user_can( 'edit_edd_support_tickets' ) ) {
			return;
		}

		$args = array( 'post_type' => 'edd_ticket' );
		$args = array(
			'id'    => 'eddstix_tickets',
			'title' => apply_filters( 'eddstix_toolbar_link', __( 'Tickets', 'edd-support-tickets' ) ),
			'href'  => esc_url( add_query_arg( $args, admin_url( 'edit.php' ) ) ),
			'meta'  => array( 'class' => 'eddstix-my-tickets' )
		);
		
		$wp_admin_bar->add_node( $args );
	}
}
