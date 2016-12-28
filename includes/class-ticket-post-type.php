<?php
/**
 * Post Type.
 *
 * @package 	EDD_Support_Tickets
 * @author 		Isabel Castillo
 * @license 	GPL-2.0+
 * @copyright 	Copyright (c) 2015-2016, Isabel Castillo
 */

class EDDSTIX_Ticket_Post_Type {

	/**
	 * Instance of this class.
	 *
	 * @var      object
	 */
	protected static $instance = null;

	public function __construct() {
		add_action( 'init', array( $this, 'post_type' ), 10, 0 );
		add_action( 'init', array( $this, 'secondary_post_type' ), 10, 0 );
		add_action( 'init', array( $this, 'register_post_status' ), 10, 0 );
		add_action( 'post_updated_messages', array( $this, 'updated_messages' ), 10, 1 );
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
	 * Register the ticket post type.
	 */
	public function post_type() {

		$supports = array( 'title' );

		/* If the post is being created we add the editor */
		if ( ! isset( $_GET['post'] ) ) {
			array_push( $supports, 'editor' );
		}
		/* Post type labels */
		$labels = apply_filters( 'eddstix_ticket_type_labels', array(
			'name'               => _x( 'Tickets', 'post type general name', 'edd-support-tickets' ),
			'singular_name'      => _x( 'Ticket', 'post type singular name', 'edd-support-tickets' ),
			'menu_name'          => _x( 'Tickets', 'admin menu', 'edd-support-tickets' ),
			'name_admin_bar'     => _x( 'Ticket', 'add new on admin bar', 'edd-support-tickets' ),
			'add_new'            => _x( 'Add New', 'book', 'edd-support-tickets' ),
			'add_new_item'       => __( 'Add New Ticket', 'edd-support-tickets' ),
			'new_item'           => __( 'New Ticket', 'edd-support-tickets' ),
			'edit_item'          => __( 'Edit Ticket', 'edd-support-tickets' ),
			'view_item'          => __( 'View Ticket', 'edd-support-tickets' ),
			'all_items'          => __( 'All Tickets', 'edd-support-tickets' ),
			'search_items'       => __( 'Search Tickets', 'edd-support-tickets' ),
			'parent_item_colon'  => __( 'Parent Ticket:', 'edd-support-tickets' ),
			'not_found'          => __( 'No tickets found.', 'edd-support-tickets' ),
			'not_found_in_trash' => __( 'No tickets found in Trash.', 'edd-support-tickets' ),
		) );

		/* Post type arguments */
		$args = apply_filters( 'eddstix_ticket_type_args', array(
			'labels'				=> $labels,
			'public'				=> true,
			'exclude_from_search'	=> true,
			'publicly_queryable'	=> true,
			'show_ui'				=> true,
			'show_in_menu'			=> true,
			'query_var'				=> true,
			'rewrite'				=> array( 
					'slug' => apply_filters( 'eddstix_rewrite_slug', 'ticket' ), 'with_front' => false ),
			'capability_type'		=> 'edd_support_ticket',
			'map_meta_cap'			=> true,
			'has_archive'			=> true,
			'hierarchical'			=> false,
			'menu_icon'				=> 'dashicons-tickets-alt',
			'supports'				=> $supports
		) );
		register_post_type( 'edd_ticket', $args );

	}

	/**
	 * Ticket update messages.
	 *
	 * @param  array $messages Existing post update messages.
	 * @return array Amended post update messages with new CPT update messages.
	 */
	public function updated_messages( $messages ) {

		$post             = get_post();
		$post_type        = get_post_type( $post );
		$post_type_object = get_post_type_object( $post_type );

		if ( 'edd_ticket' !== $post_type ) {
			return $messages;
		}

		$messages[$post_type] = array(
			0  => '', // Unused. Messages start at index 1.
			1  => __( 'Ticket updated.', 'edd-support-tickets' ),
			2  => __( 'Custom field updated.', 'edd-support-tickets' ),
			3  => __( 'Custom field deleted.', 'edd-support-tickets' ),
			4  => __( 'Ticket updated.', 'edd-support-tickets' ),
			/* translators: %s: date and time of the revision */
			5  => isset( $_GET['revision'] ) ? sprintf( __( 'Ticket restored to revision from %s', 'edd-support-tickets' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
			6  => __( 'Ticket published.', 'edd-support-tickets' ),
			7  => __( 'Ticket saved.', 'edd-support-tickets' ),
			8  => __( 'Ticket submitted.', 'edd-support-tickets' ),
			9  => sprintf(
				__( 'Ticket scheduled for: <strong>%1$s</strong>.', 'edd-support-tickets' ),
				// translators: Publish box date format, see http://php.net/date
				date_i18n( __( 'M j, Y @ G:i', 'edd-support-tickets' ), strtotime( $post->post_date ) )
			),
			10 => __( 'Ticket draft updated.', 'edd-support-tickets' )
		);

		if ( $post_type_object->publicly_queryable ) {
			$permalink = get_permalink( $post->ID );

			$view_link = sprintf( ' <a href="%s">%s</a>', esc_url( $permalink ), __( 'View ticket', 'edd-support-tickets' ) );
			$messages[ $post_type ][1] .= $view_link;
			$messages[ $post_type ][6] .= $view_link;
			$messages[ $post_type ][9] .= $view_link;

			$preview_permalink = add_query_arg( 'preview', 'true', $permalink );
			$preview_link = sprintf( ' <a target="_blank" href="%s">%s</a>', esc_url( $preview_permalink ), __( 'Preview ticket', 'edd-support-tickets' ) );
			$messages[ $post_type ][8]  .= $preview_link;
			$messages[ $post_type ][10] .= $preview_link;
		}

		return $messages;
	}
	/**
	 * Register secondary post types.
	 *
	 * These post types aren't used by the client
	 * but are used to store extra information about the tickets.
	 *
	 */
	public function secondary_post_type() {
		register_post_type( 'edd_ticket_reply', array( 'public' => false, 'exclude_from_search' => true, 'supports' => array( 'editor' ) ) );
		register_post_type( 'edd_ticket_history', array( 'public' => false, 'exclude_from_search' => true ) );
	}

	/**
	 * Register custom ticket statuses.
	 *
	 * @return void
	 */
	public function register_post_status() {

		$status = self::get_custom_ticket_statuses();
		foreach ( $status as $id => $custom_status ) {
			$args = array(
				'label'                     => $custom_status,
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop( "$custom_status <span class='count'>(%s)</span>", "$custom_status <span class='count'>(%s)</span>", 'edd-support-tickets' ),
			);

			register_post_status( $id, $args );
		}

		/**
		 * Hardcode the read and unread status used for replies.
		 */
		register_post_status( 'read',   array( 'label' => _x( 'Read', 'Reply status', 'edd-support-tickets' ), 'public' => false ) );
		register_post_status( 'unread', array( 'label' => _x( 'Unread', 'Reply status', 'edd-support-tickets' ), 'public' => false ) );
	}

	/**
	 * Get available ticket statuses.
	 *
	 * @return array List of filtered statuses
	 */
	public static function get_custom_ticket_statuses() {
		$status = array(
			'ticket_queued'		=> _x( 'New', 'Ticket status', 'edd-support-tickets' ),
			'ticket_processing'	=> _x( 'In Progress', 'Ticket status', 'edd-support-tickets' ),
			'ticket_hold'			=> _x( 'On Hold', 'Ticket status', 'edd-support-tickets' ),
			'ticket_status_closed'		=> _x( 'Closed', 'Ticket status', 'edd-support-tickets' ),
		);

		return apply_filters( 'eddstix_ticket_statuses', $status );

	}

}
