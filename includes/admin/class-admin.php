<?php
/**
 * EDD Support Tickets Admin.
 *
 * @package 	EDD_Support_Tickets
 * @subpackage 	Admin
 * @author 		Isabel Castillo
 * @license 	GPL-2.0+
 * @copyright 	Copyright (c) 2015-2016, Isabel Castillo
 */

class EDD_Support_Tickets_Admin {

	/**
	 * Instance of this class.
	 *
	 * @var      object
	 */
	protected static $instance = null;

	/**
	 * Name of the nonce used to secure custom fields.
	 *
	 * @var      object
	 */
	public static $nonce_name = 'eddstix_cf';

	/**
	 * Action of the custom nonce.
	 *
	 * @var      object
	 */
	public static $nonce_action = 'eddstix_update_cf';

	/**
	 * Initialize the plugin by loading admin scripts & styles and adding a
	 * settings page and menu.
	 *
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( 'EDDSTIX_Editor_Ajax', 'get_instance' ), 11, 0 );
		add_action( 'wp_ajax_eddstix_edit_reply', 'eddstix_edit_reply_ajax' );
		add_action( 'wp_ajax_eddstix_mark_reply_read', 'eddstix_mark_reply_read_ajax' );

		if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {

			/* Load admin functions files */
			require_once EDDSTIX_PATH . 'includes/admin/functions-admin.php';
			require_once EDDSTIX_PATH . 'includes/admin/functions-tools.php';
			require_once EDDSTIX_PATH . 'includes/admin/class-admin-tickets-list.php';
			require_once EDDSTIX_PATH . 'includes/admin/class-admin-user.php';
			require_once EDDSTIX_PATH . 'includes/admin/class-admin-help.php';

			/* Load settings files */
			require_once EDDSTIX_PATH . 'includes/admin/settings/display-settings.php';

			/* Handle possible redirections first of all. */
			if ( isset( $_SESSION['eddstix_redirect'] ) ) {
				$redirect = esc_url_raw( $_SESSION['eddstix_redirect'] );
				unset( $_SESSION['eddstix_redirect'] );
				wp_redirect( $redirect );
				exit;
			}

			/* Execute custom actions */
			if ( isset( $_GET['eddstix-do'] ) ) {
				add_action( 'init', array( $this, 'custom_actions' ) );
			}

			/* Instantiate secondary classes */
			add_action( 'plugins_loaded', array( 'EDDSTIX_Tickets_List', 'get_instance' ), 11, 0 );
			add_action( 'plugins_loaded', array( 'EDDSTIX_User', 'get_instance' ), 11, 0 );
			add_action( 'plugins_loaded', array( 'EDDSTIX_Help', 'get_instance' ), 11, 0 );
			add_action( 'pre_get_posts', array( $this, 'filter_ticket_list' ) );
			add_action( 'admin_init', array( $this, 'system_tools' ), 10, 0 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
			add_action( 'admin_menu', array( $this, 'register_submenu_items' ) );
			add_action( 'admin_menu', array( $this, 'open_tickets_count_bubble' ) );
			add_action( 'admin_notices', array( $this, 'admin_notices' ) );
			add_action( 'add_meta_boxes', array( $this, 'metaboxes' ) );
			add_action( 'save_post_edd_ticket', array( $this, 'save_ticket' ) );
			add_action( 'eddstix_add_reply_after', array( $this, 'mark_replies_read' ), 10, 2 );
			add_action( 'before_delete_post', array( $this, 'delete_ticket_dependencies' ) );
			add_filter( 'post_row_actions', array( $this, 'ticket_action_row' ), 10, 2 );
			add_filter( 'wp_insert_post_data', array( $this, 'filter_ticket_data' ), 99, 2 );
			add_filter( 'wp_count_posts', array( $this, 'mod_count_posts' ), 10, 3 );

			/**
			 * Plugin setup.
			 *
			 * If the plugin has just been installed we need to set a couple of things.
			 * We will automatically create the "special" pages: tickets list and 
			 * ticket submission.
			 */
			if ( 'pending' === get_option( 'eddstix_setup', false ) ) {
				add_action( 'admin_init', array( $this, 'create_pages' ), 11, 0 );
			}

			/**
			 * Ask if multiple products will be supported, or only one.
			 *
			 * Still part of the installation process. However, if multiple
			 * products support is already enabled, it means that this is not
			 * the first activation of the plugin (multiple products support is 
			 * disabled by default). In this case we don't ask again.
			 */
			if ( 'pending' === get_option( 'eddstix_ask_multiple_products', false ) && ( ! isset( $_GET['page'] ) ) ) {
				$products = boolval( eddstix_get_option( 'multiple_products' ) );
				
				if ( true === $products ) {
					delete_option( 'eddstix_ask_multiple_products' );
				} else {
					if ( current_user_can( 'manage_edd_ticket_settings' ) ) {
						add_action( 'admin_notices', array( $this, 'ask_multiple_products' ) );
					}
				}
			}
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
	 * Add a link to the settings page.
	 *
	 * @param  array $links Plugin links
	 * @return array Links with added settings link
	 */
	public static function settings_page_link( $links ) {
		$url    = esc_url( add_query_arg( array( 'post_type' => 'edd_ticket', 'page' => 'eddstix-settings' ), admin_url( 'edit.php' ) ) );
		$link = "<a href='$url'>" . __( 'Settings', 'edd-support-tickets' ) . "</a>";
		array_unshift( $links, $link );
		return $links;
	}

	/**
	 * Filter the tickets admin list.
	 *
	 * Modify the Tickets Admin list main query, all views, 
	 * to only get tickets assigned to current user for Agent 
	 * and Shop Worker roles. (Admin, Support Supervisor, and
	 * EDD Shop Manager can see all tickets.)
	 * 
	 * Show only open tickets on Open view for all roles.
	 *
	 * @param  object $query WordPress main query
	 * @return boolean True if the main query was modified, false otherwise
	 */
	public function filter_ticket_list( $query ) {
		global $pagenow, $current_user;

		// If it's not the admin ticket list, return
		if( ! is_admin() || 'edit.php' != $pagenow || ! isset( $_GET['post_type'] ) || 'edd_ticket' !== $_GET['post_type'] ) {
			return;
		}

		/* Make sure this is the main query */
		if ( ! $query->is_main_query() ) {
			return false;
		}

		// Don't show closed tickets in Open View, or in My Open View
		if ( ! isset( $_GET['post_status'] ) || 'my_open_view' == $_GET['post_status'] ) {
			$query->set( 'post_status', array( 'ticket_queued', 'ticket_processing', 'ticket_hold' ) );
		}

		// Limit to their own tickets for:
			// Agent and Shop Worker if agent_see_all is empty,
			// OR for anyone on My Open View, regardless of agent_see_all
		$agent_see_all = eddstix_get_option( 'agent_see_all' );

		if ( 
			( empty( $agent_see_all ) && current_user_can( 'edit_edd_support_tickets' ) && ! current_user_can( 'manage_edd_ticket_settings' ) )
			|| isset( $_GET['post_status'] ) && 'my_open_view' == $_GET['post_status']
			) {

				// if sorting by title
				if ( 'title' == $query->get( 'orderby' ) ) {
					$query->set( 'meta_key', '_eddstix_assignee' );
					$query->set( 'meta_value', (int) $current_user->ID );

				} else {

					// Sort by last activity while also limiting by assignee key
					$query->set( 'meta_key', '_last_activity' );
					$query->set( 'orderby', 'meta_value' );
					$query->set( 'meta_query', array(
							array(
								'key' => '_last_activity'
							),
							array(
								'key' => '_eddstix_assignee',
								'value' => (int) $current_user->ID
								)
					));
				}


		} else {

			// all other cases and views

			// if not sorting by title,  set default orderby by last activity

			if ( 'title' != $query->get( 'orderby' ) ) {
				$query->set( 'meta_key', '_last_activity' );
				$query->set( 'orderby', 'meta_value' );
			}
		}
	}

	/**
	 * Load admin-specific JavaScript and styles.
	 * 
	 * @return null Return early if no settings page is registered.
	 */
	public function enqueue_admin_scripts() {
		if ( ! eddstix_is_plugin_page() ) {
			return;
		}
		wp_enqueue_style( 'eddstix-select2', EDDSTIX_URL . 'assets/admin/css/vendor/select2.min.css', null, '3.5.2', 'all' );
		wp_enqueue_style( 'eddstix-admin-styles', EDDSTIX_URL . 'assets/admin/css/admin.css', array( 'eddstix-select2' ), EDDSTIX_VERSION );
		if ( 'edd_ticket' == get_post_type() ) {
			wp_dequeue_script( 'autosave' );
		}

		$action = filter_input( INPUT_GET, 'action', FILTER_SANITIZE_STRING );

		wp_enqueue_script( 'eddstix-select2', EDDSTIX_URL . 'assets/admin/js/vendor/select2.min.js', array( 'jquery' ), '3.5.2', true );
		wp_enqueue_script( 'eddstix-admin-script', EDDSTIX_URL . 'assets/admin/js/admin.js', array( 'jquery', 'eddstix-select2' ), EDDSTIX_VERSION );

		if ( 'edit' === $action && 'edd_ticket' == get_post_type() ) {
			wp_enqueue_script( 'eddstix-admin-reply', EDDSTIX_URL . 'assets/admin/js/admin-reply.js', array( 'jquery' ), EDDSTIX_VERSION );
			wp_localize_script( 'eddstix-admin-reply', 'eddstixL10n', array( 'alertDelete' => __( 'Are you sure you want to delete this reply?', 'edd-support-tickets' ), 'alertNoTinyMCE' => __( 'No instance of TinyMCE found. Please use wp_editor on this page at least once: http://codex.wordpress.org/Function_Reference/wp_editor', 'edd-support-tickets' ) ) );
		}

	}

	/**
	 * Create the mandatory pages.
	 *
	 * @return void
	 */
	public function create_pages() {
		$options = get_option( 'eddstix_settings' );
		$update = false;

		if ( empty( $options['ticket_list'] ) ) {

			$list_args = array(
				'post_content'   => '[edd_support_tickets_list]',
				'post_title'     => wp_strip_all_tags( __( 'My Tickets', 'edd-support-tickets' ) ),
				'post_name'      => sanitize_title( __( 'My Tickets', 'edd-support-tickets' ) ),
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'ping_status'    => 'closed',
				'comment_status' => 'closed'
			);

			$list = wp_insert_post( $list_args, true );

			if ( ! is_wp_error( $list ) && is_int( $list ) ) {
				$options['ticket_list'] = $list;
				$update                 = true;
			}
		}

		if ( empty( $options['ticket_submit'] ) ) {

			$submit_args = array(
				'post_content'   => '[edd_support_ticket_submit]',
				'post_title'     => wp_strip_all_tags( __( 'Open a Ticket', 'edd-support-tickets' ) ),
				'post_name'      => sanitize_title( __( 'Open a Ticket', 'edd-support-tickets' ) ),
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'ping_status'    => 'closed',
				'comment_status' => 'closed'
			);
		
			$submit = wp_insert_post( $submit_args, true );

			if ( ! is_wp_error( $submit ) && is_int( $submit ) ) {
				$options['ticket_submit'] = $submit;
				$update                   = true;
			}

		}

		if ( $update ) {
			update_option( 'eddstix_settings', $options);
		}

		if ( ! empty( $options['ticket_submit'] ) && ! empty( $options['ticket_list'] ) ) {
			delete_option( 'eddstix_setup' );
		}
		// only called on setup
		flush_rewrite_rules();
	}

	/**
	 * Add items in action row.
	 *
	 * Add a quick option to open or close a ticket
	 * directly from the tickets list.
	 *
	 * @param  array $actions  List of existing options
	 * @param  object $post    Current post object
	 * @return array           List of options with ours added
	 */
	public function ticket_action_row( $actions, $post ) {
		if ( 'edd_ticket' === $post->post_type ) {

			$status = get_post_status( $post->ID );
			if ( eddstix_is_status_open( $status ) ) {
				$actions['close'] = '<a href="' . esc_url( eddstix_get_close_ticket_url( $post->ID ) ) . '">' . __( 'Close', 'edd-support-tickets' ) . '</a>';
			} else {
				$actions['open'] = '<a href="' . esc_url( eddstix_get_open_ticket_url( $post->ID ) ) . '">' . __( 'Open', 'edd-support-tickets' ) . '</a>';
			}
			
		}

		return $actions;
	}

	/**
	 * Display custom admin notices.
	 *
	 * Custom admin notices are usually triggered by custom actions.
	 *
	 * @return void
	 */
	public function admin_notices() {
		if ( isset( $_GET['eddstix-message'] ) ) {

			switch( $_GET['eddstix-message'] ) {

				case 'opened':
					?>
					<div class="updated">
						<p><?php printf( __( 'The ticket #%s has been (re)opened.', 'edd-support-tickets' ), intval( $_GET['post'] ) ); ?></p>
					</div>
					<?php
				break;

				case 'closed':
					?>
					<div class="updated">
						<p><?php printf( __( 'The ticket #%s has been closed.', 'edd-support-tickets' ), intval( $_GET['post'] ) ); ?></p>
					</div>
					<?php
				break;

			}

		}
	}

	/**
	 * Admin notice to ask user if they will support multiple products.
	 *
	 * @return void
	 */
	public function ask_multiple_products() {
		global $pagenow;

		$get = $_GET;

		if ( ! isset( $get ) || !is_array( $get ) ) {
			$get = array();
		}

		$get['eddstix-nonce']		= wp_create_nonce( 'eddstix_custom_action' );
		$get_single					= $get_multiple = $get;
		$get_single['eddstix-do']	= 'single-product';
		$get_multiple['eddstix-do']	= 'multiple-products';

		$single_url   = esc_url( add_query_arg( $get_single, admin_url( $pagenow ) ) );
		$multiple_url = esc_url( add_query_arg( $get_multiple, admin_url( $pagenow ) ) );
		?>
		<div class="updated">
			<p><?php _e( 'Will you be supporting multiple products on this support site? You can activate multi-products support now. <small>(This setting can be modified later)</small>', 'edd-support-tickets' ); ?></p>
			<p>
				<a href="<?php echo wp_sanitize_redirect( $single_url ); ?>" class="button-secondary"><?php _e( 'Single Product', 'edd-support-tickets' ); ?></a> 
				<a href="<?php echo wp_sanitize_redirect( $multiple_url ); ?>" class="button-secondary"><?php _e( 'Multiple Products', 'edd-support-tickets' ); ?></a>
			</p>
		</div>
	<?php }

	/**
	 * Filter ticket data before inserting or updating.
	 *
	 * Before inserting or updating a ticket in the database,
	 * we check the post status and possibly overwrite it
	 * with a registered custom status.
	 *
	 * @param  array $data    Post data
	 * @param  array $postarr Original post data
	 * @return array          Modified post data for insertion
	 */
	public function filter_ticket_data( $data, $postarr ) {
		global $current_user;

		if ( ! isset( $data['post_type'] ) || 'edd_ticket' !== $data['post_type'] ) {
			return $data;
		}

		/**
		 * If the ticket is being trashed we don't do anything.
		 */
		if ( 'trash' === $data['post_status'] ) {
			return $data;
		}

		/**
		 * Do not affect auto drafts
		 */
		if ( 'auto-draft' === $data['post_status'] ) {
			return $data;
		}

		/**
		 * If agent has JUST clicked Reply & Close, let it close.
		 */
		if ( 'ticket_status_closed' == $postarr['post_status'] ) {
			$data['post_status'] = 'ticket_status_closed';
			return $data;
		}

		/**
		 * As a double precaution to avoid error, check if agent 
		 * clicked Reply & Close, since the Close timing can be
		 * off in which case ticket wouldn't be closed in yet
		 * in the check above. (This often occurred during testing.)
		 */

		if ( isset( $_POST['eddstix_do'] ) && 'reply_close' == $_POST['eddstix_do'] ) {
			$data['post_status'] = 'ticket_status_closed';
			return $data;
		}

		/**
		 * Automatically set the ticket as processing if this is the first reply,
		 * but NOT if this is a bulk edit.
		 */
		if ( user_can( $current_user->ID, 'edit_edd_support_tickets' ) && isset( $postarr['ID'] ) && ! isset( $_REQUEST['_status'] ) ) {

			$replies = eddstix_get_replies( intval( $postarr['ID'] ) );
			if ( 0 === count( $replies ) ) {

				if ( ! isset( $_POST['post_status_override'] ) || 'ticket_queued' === $_POST['post_status_override'] ) {
					$_POST['post_status_override'] = 'ticket_processing';
				}
			}
		}

		
		// Update status if changed in the Details metabox

		if ( isset( $_POST['post_status_override'] ) && ! empty( $_POST['post_status_override'] ) ) {

			$status = eddstix_get_custom_ticket_statuses();

			if ( array_key_exists( $_POST['post_status_override'], $status ) ) {

				$data['post_status'] = $_POST['post_status_override'];

				if ( isset( $postarr['original_post_status'] ) && ( $postarr['original_post_status'] !== $_POST['post_status_override'] ) && isset( $_POST['eddstix_post_parent'] ) ) {

					eddstix_log( intval( $_POST['eddstix_post_parent'] ), sprintf( __( 'Ticket state changed to %s', 'edd-support-tickets' ), '&quot;' . $status[$_POST['post_status_override']] . '&quot;' ) );
				}
			}

		}
		return $data;
	}

	/**
	 * Register all submenu items.
	 *
	 * @return void
	 */
	public function register_submenu_items() {
		add_submenu_page( 'edit.php?post_type=edd_ticket', __( 'Debugging Tools', 'edd-support-tickets' ), __( 'Tools', 'edd-support-tickets' ), 'manage_edd_ticket_settings', 'eddstix-tools', array( $this, 'display_tools_page' ) );
		add_submenu_page( 'edit.php?post_type=edd_ticket', __( 'EDD Support Tickets Settings', 'edd-support-tickets' ), __( 'Settings', 'edd-support-tickets' ), 'manage_edd_ticket_settings', 'eddstix-settings', 'eddstix_options_page' );
		add_submenu_page( null, __( 'Tickets By Customer', 'edd-support-tickets' ), __( 'Tickets By Customer', 'edd-support-tickets' ), 'edit_edd_support_tickets', 'eddstix-alltickets', array( $this, 'display_alltickets_page' ) );
	}

	/**
	 * Add open ticket count to Tickets admin menu item.
	 *
	 * @return boolean True if the ticket count was added, false otherwise
	 */
	public function open_tickets_count_bubble() {
		if ( false === (bool) eddstix_get_option( 'show_count' ) ) {
			return false;
		}

		global $menu;

		$counts = wp_count_posts( 'edd_ticket' );
		$open_count = $counts->ticket_queued + $counts->ticket_processing + $counts->ticket_hold;
		if ( 0 === $open_count ) {
			return false;
		}

		foreach ( $menu as $key => $value ) {
			if ( $menu[$key][2] == 'edit.php?post_type=edd_ticket' ) {
				$menu[$key][0] .= ' <span class="awaiting-mod count-' . $open_count . '"><span class="pending-count">' . $open_count . '</span></span>';
			}
		}

		return true;
	}

	/**
	 * Filter wp_count_posts to count only current user's tickets.
	 *
	 * This is mainly used to correct the counts for the 
	 * ticket status Admin View links.
	 */
	public function mod_count_posts( $counts, $type, $perm ) {
		if ( 'edd_ticket' != $type ) {
			return $counts;
		}

		// If agent_see_all is empty, Agents and EDD Shop Workers should only count their own

		$agent_see_all = eddstix_get_option( 'agent_see_all' );

		if ( empty( $agent_see_all ) && current_user_can( 'edit_edd_support_tickets' ) && ! current_user_can( 'manage_edd_ticket_settings' ) ) {

			 	global $current_user;
			 	$args['meta_query'][] = array(
					'key'		=> '_eddstix_assignee',
					'value'		=> $current_user->ID,
					'compare'	=> '=',
				);

			 	$tickets = eddstix_get_tickets( $args );

			 	if ( ! $tickets ) {

					$counts->ticket_queued = 0;
					$counts->ticket_processing = 0;
					$counts->ticket_hold = 0;
					$counts->ticket_status_closed = 0;

			 		return $counts;
			 	}
			 	$queued = array();
			 	$processing = array();
			 	$hold = array();
			 	$closed = array();

			 	foreach ( $tickets as $ticket ) {

					$status = $ticket->post_status;

					switch ($status) {
					    case 'ticket_queued':
					        $queued[] = $ticket->ID;
					        break;
					    case 'ticket_processing':
					        $processing[] = $ticket->ID;
					        break;
					    case 'ticket_hold':
					        $hold[] = $ticket->ID;
					        break;
					    case 'ticket_status_closed':
					        $closed[] = $ticket->ID;
					        break;        
					}

				}
				$counts->ticket_queued = count( $queued );
				$counts->ticket_processing = count( $processing );
				$counts->ticket_hold = count( $hold );
				$counts->ticket_status_closed = count( $closed );

		}

		return $counts;
	}

	/**
	 * Render the plugin Tools page
	 */
	public function display_tools_page() {
		include_once EDDSTIX_PATH . 'includes/admin/tools/tools.php';
	}

	/**
	 * Render the Tickets By Customer page
	 */
	public function display_alltickets_page() {
		if ( ! empty( $_GET['ref'] ) ) {
			if ( 'list' == $_GET['ref'] ) {
				$backlink = esc_url( admin_url( 'edit.php?post_type=edd_ticket' ) );
			} elseif ( is_numeric( $_GET['ref'] ) ) {
				$id = $_GET['ref'];
				$backlink = esc_url( admin_url( "post.php?post=$id&action=edit" ) );
			}
		}

		if ( ( ! isset( $_GET['id'] ) ) || ( ! is_numeric( $_GET['id'] ) ) ) {
			echo '<p>' . __( 'There was an error.') . '</p>';
			if ( ! empty( $backlink ) ) {
				echo '<p><a href="' . $backlink . '"> &larr; Go Back</a></p>';
			}
			return;
		}

		global $eddstix_tickets;
		$eddstix_tickets    = get_posts( array(
			'author'			=> $_GET['id'],
			'post_type'			=> 'edd_ticket',
			'post_status'            => 'any',		
			'posts_per_page'	=> 100,
			'orderby'   => 'meta_value',
			'meta_key' => '_last_activity'
		) );

		$user = get_userdata( $_GET['id'] );
		?>
		<div class='wrap'>
		<h1><?php _e( 'Tickets By Customer', 'easy-digital-downloads' );?></h1>
		<?php echo '<p><a href="' . $backlink . '"> &larr; Go Back</a></p>'; ?>
		<div id="eddstix-alltickets-wrapper">
			<div class="eddstix-alltickets-header">
				<?php echo get_avatar( $user->user_email, 30 ); ?> <span><?php echo $user->display_name; ?></span>
			</div>
			<?php eddstix_customer_all_tickets_table(); ?>
		</div>
		</div>
		<?php
	}

	/**
	 * Execute plugin custom actions.
	 *
	 * Any custom actions the plugin can trigger through a URL variable
	 * will be executed here. It is all triggered by the var eddstix-do.
	 */
	public function custom_actions() {
		// Make sure we have a trigger
		if ( ! isset( $_GET['eddstix-do'] ) ) {
			return;
		}

		// Validate the nonce
		if ( ! isset( $_GET['eddstix-nonce'] ) || ! wp_verify_nonce( $_GET['eddstix-nonce'], 'eddstix_custom_action' ) ) {
			return;
		}

		$log    = array();
		$action = sanitize_text_field( $_GET['eddstix-do'] );

		switch ( $action ):

			case 'close':

				if ( isset( $_GET['post'] ) && 'edd_ticket' == get_post_type( intval( $_GET['post'] ) ) ) {

					$url = esc_url_raw( add_query_arg( array( 'post' => $_GET['post'], 'action' => 'edit', 'eddstix-message' => 'closed' ), admin_url( 'post.php' ) ) );
					eddstix_close_ticket( $_GET['post'] );
				}

			break;

			case 'open':

				if ( isset( $_GET['post'] ) && 'edd_ticket' == get_post_type( intval( $_GET['post'] ) ) ) {

					$url = esc_url_raw( add_query_arg( array( 'post' => $_GET['post'], 'action' => 'edit', 'eddstix-message' => 'opened' ), admin_url( 'post.php' ) ) );
					eddstix_reopen_ticket( $_GET['post'] );
				}

			break;

			case 'trash_reply':

				if ( isset( $_GET['del_id'] ) && current_user_can( 'edit_edd_support_tickets' ) ) {

					$del_id = intval( $_GET['del_id'] );

					/* Trash the post */
					wp_trash_post( $del_id, false );

					/* Redirect with clean URL */
					$url = wp_sanitize_redirect( esc_url_raw( add_query_arg( array( 'post' => $_GET['post'], 'action' => 'edit' ), admin_url( 'post.php' ) . "#eddstix-post-$del_id" ) ) );

					eddstix_redirect( 'trashed_reply', $url );
					exit;

				}

			break;

			case 'multiple-products':

				$options = get_option( 'eddstix_settings' );
				$options['multiple_products'] = '1';
				if ( update_option( 'eddstix_settings', $options ) ) {
					delete_option( 'eddstix_ask_multiple_products' );
				}
				eddstix_redirect( 'enable_multiple_products', esc_url_raw( remove_query_arg( array( 'eddstix-nonce', 'eddstix-do' ), eddstix_get_current_admin_url() ) ) );
				exit;

			break;

			case 'single-product':
				delete_option( 'eddstix_ask_multiple_products' );
				eddstix_redirect( 'enable_single_product', esc_url_raw( remove_query_arg( array( 'eddstix-nonce', 'eddstix-do' ), eddstix_get_current_admin_url() ) ) );
				exit;
			break;
		endswitch;

		/**
		 * eddstix_custom_actions hook
		 *
		 * Fired right after the action is executed. It is important to note that
		 * some of the actions are triggering a redirect after they're done and
		 * that in this case this hook won't be triggered.
		 *
		 * @param string $action The action that's being executed
		 */
		do_action( 'eddstix_execute_custom_action', $action );

		/* Log the action */
		if ( ! empty( $log ) ) {
			eddstix_log( $_GET['post'], $log );
		}

		/* Get URL vars */
		$args = $_GET;

		/* Remove custom action and nonce */
		unset( $_GET['eddstix-do'] );
		unset( $_GET['eddstix-nonce'] );

		/* Read-only redirect */
		eddstix_redirect( 'read_only', $url );
		exit;

	}

	/**
	 * Register the metaboxes.
	 *
	 * The function below registers all the metaboxes used
	 * in the ticket edit screen.
	 */
	public function metaboxes() {
		/* Remove the publishing metabox */
		remove_meta_box( 'submitdiv', 'edd_ticket', 'side' );

		/**
		 * Register the metaboxes.
		 */
		/* Issue details, only available for existing tickets */
		if ( isset( $_GET['post'] ) ) {
			add_meta_box( 'eddstix-mb-message', __( 'Ticket', 'edd-support-tickets' ), array( $this, 'metabox_callback' ), 'edd_ticket', 'normal', 'high', array( 'template' => 'message' ) );

			$status = get_post_status( intval( $_GET['post'] ) );

			// if the status is one of our custom.
			$custom_status = eddstix_get_custom_ticket_statuses();
			if ( array_key_exists( $status, $custom_status ) ) {

				add_meta_box( 'eddstix-mb-replies', __( 'Ticket Replies', 'edd-support-tickets' ), array( $this, 'metabox_callback' ), 'edd_ticket', 'normal', 'high', array( 'template' => 'replies' ) );
			}
		}

		/* Ticket details */
		add_meta_box( 'eddstix-mb-details', __( 'Details', 'edd-support-tickets' ), array( $this, 'metabox_callback' ), 'edd_ticket', 'side', 'high', array( 'template' => 'details' ) );

		/* Contacts involved in the ticket */
		add_meta_box( 'eddstix-mb-contacts', __( 'Stakeholders', 'edd-support-tickets' ), array( $this, 'metabox_callback' ), 'edd_ticket', 'side', 'high', array( 'template' => 'stakeholders' ) );

		/* Additional Info - Product metabox. */
		add_meta_box( 'eddstix-mb-cf', __( 'Additional Info', 'edd-support-tickets' ), array( $this, 'metabox_callback' ), 'edd_ticket', 'side', 'default', array( 'template' => 'additional-info' ) );
	}

	/**
	 * Metabox callback function.
	 *
	 * The below function is used to call the metaboxes content.
	 * A template name is given to the function. If the template
	 * does exist, the metabox is loaded. If not, nothing happens.
	 *
	 * @param  (integer) $post     Post ID
	 * @param  (string)  $template Metabox content template
	 *
	 * @return void
	 */
	public function metabox_callback( $post, $args ) {
		if ( ! is_array( $args ) || ! isset( $args['args']['template'] ) ) {
			_e( 'An error occurred while registering this metabox. Please contact the support.', 'edd-support-tickets' );
		}

		$template = $args['args']['template'];

		if ( ! file_exists( EDDSTIX_PATH . "includes/admin/metaboxes/$template.php" ) ) {
			_e( 'An error occured while loading this metabox. Please contact the support.', 'edd-support-tickets' );
		}

		/* Include the metabox content */
		include_once EDDSTIX_PATH . "includes/admin/metaboxes/$template.php";

	}

	/**
	 * Save ticket custom fields. Runs when editing a ticket in back end only.
	 *
	 * This function will save all custom fields for a ticket, including core custom fields.
	 * Does not fun upon front end submission.
	 * 
	 * @param  (int) $post_id Current post ID
	 */
	public function save_ticket( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE || wp_is_post_revision( $post_id ) ) {
			return;
		}
		/* We should already being avoiding Ajax, but let's make sure */
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}

		/* Check nonce */
		if ( ! isset( $_POST[ EDD_Support_Tickets_Admin::$nonce_name ] ) || ! wp_verify_nonce( $_POST[ EDD_Support_Tickets_Admin::$nonce_name ], EDD_Support_Tickets_Admin::$nonce_action ) ) {
			return;
		}
		/* Does the current user have permission? */
		if ( ! current_user_can( 'edit_edd_support_tickets', $post_id ) ) {
			return;
		}

		global $current_user;

		/**
		 * Store possible logs
		 */
		$log = array();

		$existing_ticket = eddstix_has_custom_status( $post_id );

		/* Save the possible ticket reply */
		if ( isset( $_POST['eddstix_reply'] ) && isset( $_POST['eddstix_reply_ticket'] ) && '' != $_POST['eddstix_reply'] ) {

			/* Check for the nonce */
			if ( wp_verify_nonce( $_POST['eddstix_reply_ticket'], 'reply_ticket' ) ) {
				$user_id = $current_user->ID;
				$content = wp_kses_post( $_POST['eddstix_reply'] );

				$data = array(
					'post_content'   => $content,
					'post_status'    => 'read',
					'post_type'      => 'edd_ticket_reply',
					'post_author'    => $user_id,
					'post_parent'    => $post_id,
					'ping_status'    => 'closed',
					'comment_status' => 'closed',
				);

				/**
				 * Remove the save_post hook now as we're going to trigger
				 * a new one by inserting the reply (and logging the history later).
				 */
				remove_action( 'save_post_edd_ticket', array( $this, 'save_ticket' ) );

				/* Insert the reply in DB */
				$reply = eddstix_add_reply( $data, $post_id );

				/* In case the insertion failed... */
				if ( is_wp_error( $reply ) ) {

					/* Set the redirection */
					$_SESSION['eddstix_redirect'] = esc_url_raw( add_query_arg( array( 'eddstix-message' => 'eddstix_reply_error' ), get_permalink( $post_id ) ) );

				} else {

					/**
					 * Delete the activity transient.
					 */
					delete_transient( "eddstix_activity_meta_post_$post_id" );

					/* Email the client */
					$new_reply = new EDDSTIX_Email_Notification( $post_id, array( 'reply_id' => $reply, 'action' => 'reply_agent' ) );

					/* The agent wants to close the ticket */
					if ( isset( $_POST['eddstix_do'] ) &&  'reply_close' == $_POST['eddstix_do'] ) {

						/* Confirm the post type and close */
						if ( 'edd_ticket' == get_post_type( $post_id ) ) {

							do_action( 'eddstix_ticket_before_close_by_agent', $post_id );

							$closed = eddstix_close_ticket( $post_id );

							/* Email the client */
							new EDDSTIX_Email_Notification( $post_id, array( 'action' => 'closed' ) );
							do_action( 'eddstix_ticket_closed_by_agent', $post_id );

						}

					}

				}

			}

		}

		do_action( 'eddstix_save_custom_fields_before', $post_id );

		/* Now we can instantiate the save class and save */
		$eddstix_save = new EDDSTIX_Save_Fields();
		$saved = $eddstix_save->save( $post_id );

		do_action( 'eddstix_save_custom_fields_after', $post_id );

		/* Log the action */
		if ( ! empty( $log ) ) {
			eddstix_log( $post_id, $log );
		}

		/* If this was a ticket update, we need to know where to go now... */
		if ( $existing_ticket ) {

			// This runs when creating a new Ticket from the admin,
			// and also when adding a reply from the admin.
			update_post_meta( $post_id, '_last_activity', current_time( 'mysql' ) );

			/* Go back to the tickets list */
			if ( isset( $_POST['eddstix_back_to_list'] ) && true === boolval( $_POST['eddstix_back_to_list'] ) || isset( $_POST['where_after'] ) && 'back_to_list' === $_POST['where_after'] ) {
				$args = array( 'post_type' => 'edd_ticket' );
				$_SESSION['eddstix_redirect'] = esc_url_raw( add_query_arg( $args, admin_url( 'edit.php' ) ) );
			}

		}

	}

	/**
	 * Mark replies as read.
	 *
	 * When an agent replies to a ticket, we mark all previous replies
	 * as read. We suppose it's all been read when the agent replies.
	 * This allows for keeping replies unread until an agent replies
	 * or manually marks the last reply as read.
	 *
	 * @return void
	 */
	public function mark_replies_read( $reply_id, $data ) {
		$replies = eddstix_get_replies( intval( $data['post_parent'] ), 'unread' );

		foreach ( $replies as $reply ) {
			eddstix_mark_reply_read( $reply->ID );
		}
	}

	/**
	 * Delete ticket dependencies.
	 *
	 * Delete all ticket dependencies when a ticket is deleted. This includes
	 * ticket replies and ticket history. Ticket attachments are deleted by
	 * EDDSTIX_File_Upload::delete_attachments()
	 * 
	 * @param  integer $post_id ID of the post to be deleted
	 * @return void
	 */
	public function delete_ticket_dependencies( $post_id ) {
		/* First of all we remove this action to avoid creating a loop */
		remove_action( 'before_delete_post', array( $this, 'delete_ticket_replies' ), 10, 1 );

		$args = array(
			'post_parent'            => $post_id,
			'post_type'              => apply_filters( 'eddstix_replies_post_type', array( 'edd_ticket_history', 'edd_ticket_reply' ) ),
			'post_status'            => 'any',
			'posts_per_page'         => -1,
			'no_found_rows'          => true,
			'cache_results'          => false,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
		);		
		
		$posts = new WP_Query( $args );

		foreach ( $posts->posts as $id => $post ) {

			do_action( 'eddstix_before_delete_dependency', $post->ID, $post );

			wp_delete_post( $post->ID, true );

			do_action( 'eddstix_after_delete_dependency', $post->ID, $post );
		}

	}

	public function system_tools() {
		if ( ! isset( $_GET['tool'] ) || ! isset( $_GET['_nonce'] ) ) {
			return false;
		}

		if ( ! wp_verify_nonce( $_GET['_nonce'], 'system_tool' ) ) {
			return false;
		}

		switch( sanitize_text_field( $_GET['tool'] ) ) {

			/* Clear all tickets metas */
			case 'tickets_metas';
				eddstix_clear_tickets_metas();
				break;

			case 'resync_products':
				eddstix_delete_resync_products();
				break;
		}

		/* Redirect in "read-only" mode */
		$url  = esc_url_raw( add_query_arg( array(
			'post_type' => 'edd_ticket',
			'page'      => 'eddstix-tools',
			'tab'       => 'cleanup',
			'done'      => sanitize_text_field( $_GET['tool'] )
			), admin_url( 'edit.php' )
		) );

		wp_redirect( wp_sanitize_redirect( $url ) );
		exit;

	}
}