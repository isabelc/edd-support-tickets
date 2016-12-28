<?php
/**
 * Admin Tickets List.
 *
 * @package 	EDD_Support_Tickets
 * @subpackage 	Admin/Tickets_List
 * @author 		Isabel Castillo
 * @license 	GPL-2.0+
 * @copyright 	Copyright (c) 2015-2016, Isabel Castillo
 */

class EDDSTIX_Tickets_List {

	/**
	 * Instance of this class.
	 *
	 * @var      object
	 */
	protected static $instance = null;

	public function __construct() {
		add_filter( 'manage_edd_ticket_posts_columns', array( $this, 'add_core_custom_columns' ), 16, 1 );
		add_action( 'manage_edd_ticket_posts_custom_column', array( $this, 'core_custom_columns_content' ), 10, 2 );
		add_filter( 'the_excerpt', array( $this, 'remove_excerpt' ), 10, 1 );
		add_filter( 'post_row_actions', array( $this, 'remove_quick_edit' ), 10, 2 );
		add_filter( 'views_edit-edd_ticket', array( $this, 'filter_view_links' ) );
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
	* Remove Quick Edit action
	* 
	* @global  object $post
	* @param   array $actions An array of row action links.
	* @return  array Udated array of row action links
	*/
	public function remove_quick_edit( $actions ) {
		global $post;
		if ( $post->post_type === 'edd_ticket' ) {
			unset($actions['inline hide-if-no-js']);
		}
		return $actions;
	}

	/**
	 * Filter the View links for EDD Tickets admin
	 */
	public function filter_view_links( $views ) {

		// change All label to Open
		$views['all'] = str_replace( 'All', 'Open', $views['all'] );

		// Replace the regular count with Open count for the Open link
		$counts = wp_count_posts( 'edd_ticket' );
		$count = $counts->ticket_queued + $counts->ticket_processing + $counts->ticket_hold;
		$views['all'] = preg_replace( '~(<span class="count">)(.*)(</span>)~', "$1($count)$3", $views['all'] );

		// Add "My Open" view link

		if ( current_user_can( 'manage_edd_ticket_settings' )
			|| 
			current_user_can( 'edit_edd_support_tickets' )
			&& true === boolval( eddstix_get_option( 'agent_see_all' ) ) ) {

			// Add current class if on that view
			$class = ( isset( $_GET['post_status'] ) && 'my_open_view' == $_GET['post_status'] ) ? " class='current'" : '';
			
			// Count the current user's Open tickets
			global $current_user;
			$args = array(
					'meta_query'	=> array( 
							array(
								'key'		=> '_eddstix_assignee',
								'value'		=> $current_user->ID,
								'compare'	=> '=',
							)
					),
					'post_status'	=> array( 'ticket_queued', 'ticket_processing', 'ticket_hold' ),
					'fields'		=> 'ids'
				);

			$my_open_count = count( eddstix_get_tickets( $args ) );

			$views['mine'] = "<a $class href='edit.php?post_status=my_open_view&amp;post_type=edd_ticket'>My Open <span class='count'>($my_open_count)</span></a>";


		}

		return $views;
	}

	/**
	 * Add ID, Assignee, and Activity custom columns.
	 *
	 * @param  array $columns List of default columns
	 * @return array Updated list of columns
	 */
	public function add_core_custom_columns( $columns ) {

		$new = array();

		/**
		 * Parse the old columns and add the new ones.
		 */
		foreach ( $columns as $col_id => $col_label ) {

			if ( 'title' === $col_id ) {
				$new['ticket_id'] = '#';
			}

			// Remove the date column that's replaced by the activity column
			if ( 'date' !== $col_id ) {
				$new[$col_id] = $col_label;

				if ( 'taxonomy-ticket_product' === $col_id ) {
					$new[$col_id] = __('Product', 'edd-support-tickets' );
				}

			} else {

				// Add Support Staff column
				if (
					current_user_can( 'manage_edd_ticket_settings' )
					|| 
					( current_user_can( 'edit_edd_support_tickets' )
					&& true === boolval( eddstix_get_option( 'agent_see_all' ) ) )
					) {

						// only admin, supervisor, and shop manager should see it.
						$new = array_merge( $new, array( 'eddstix-assignee' => __( 'Support Staff', 'edd-support-tickets' ) ) );
				}

				// Add the activity column
				$new = array_merge( $new, array( 'eddstix-activity' => __( 'Activity', 'edd-support-tickets' ) ) );
			}

		}

		return $new;
	}

	/**
	 * Manage core column content, mainly ID, Assignee, and Activity columns.
	 *
	 * @param  array $column Column currently processed
	 * @param  integer $post_id ID of the post being processed
	 */
	public function core_custom_columns_content( $column, $post_id ) {

		switch ( $column ) {

			case 'ticket_id':

				$link = esc_url( add_query_arg( array( 'post' => $post_id, 'action' => 'edit' ), admin_url( 'post.php' ) ) );
				echo "<a href='$link'>#$post_id</a>";

				break;

			case 'eddstix-assignee':

				$assignee = get_post_meta( $post_id, '_eddstix_assignee', true );
				$agent    = get_user_by( 'id', $assignee );
				echo $agent->data->display_name;

				break;

			case 'eddstix-activity':

				$latest        = null;
				$tags          = array();
				$activity_meta = get_transient( "eddstix_activity_meta_post_$post_id" );

				if ( false === $activity_meta ) {

					$post                         = get_post( $post_id );
					$activity_meta                = array();
					$activity_meta['ticket_date'] = $post->post_date;

					/* Get the last reply if any */
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

					if ( ! empty( $latest->posts ) ) {
						$user_data                      = get_user_by( 'id', $latest->post->post_author );
						$activity_meta['user_link']     = add_query_arg( array( 'user_id' => $latest->post->post_author ), admin_url( 'user-edit.php' ) );
						$activity_meta['user_id']       = $latest->post->post_author;
						$activity_meta['display_name'] = $user_data->display_name;
						$activity_meta['reply_date']    = $latest->post->post_date;
					}

					set_transient( "eddstix_activity_meta_post_$post_id", $activity_meta, apply_filters( 'eddstix_activity_meta_transient_lifetime', 60 * 60 ) ); // Set to 1 hour by default

				}

				echo '<ul>';

				/**
				 * We check when was the last reply (if there was a reply).
				 * Then, we compute the ticket age and if it is considered as
				 * old, we display an informational tag.
				 */
				if ( ! isset( $activity_meta['reply_date'] ) ) {
						
					$number_replies = _x( 'No reply yet.', 'No last reply', 'edd-support-tickets' );
				
				} else {

					$args = array(
						'post_parent'            => $post_id,
						'post_type'              => 'edd_ticket_reply',
						'post_status'            => array( 'unread', 'read' ),
						'posts_per_page'         => - 1,
						'orderby'                => 'date',
						'order'                  => 'DESC',
						'no_found_rows'          => true,
						'cache_results'          => false,
						'update_post_term_cache' => false,
						'update_post_meta_cache' => false,
					);

					$query = new WP_Query( $args );

					$user_link = current_user_can( 'edit_users' ) ? ( '<a href="' . esc_url( $activity_meta['user_link'] ) . '">' . $activity_meta['display_name'] . '</a>' ) : $activity_meta['display_name'];

					if ( true === user_can( $activity_meta['user_id'], 'edit_edd_support_tickets' ) ) {

						$role = _x( 'agent', 'User role', 'edd-support-tickets' );

					} else {
						$role = _x( 'client', 'User role', 'edd-support-tickets' );

						// link to All Tickets by this Customer
						$user_id = $activity_meta['user_id'];
						$user_link = '<a href="' . esc_url( admin_url( "edit.php?post_type=edd_ticket&page=eddstix-alltickets&id=$user_id&ref=list" ) ) . '">' . $activity_meta['display_name'] . '</a>';
					}

					$number_replies = _x( sprintf( _n( '%s reply.', '%s replies.', $query->post_count, 'edd-support-tickets' ), $query->post_count ), 'Number of replies to a ticket', 'edd-support-tickets' );

					?><li><?php
					printf( _x( '<a href="%1$s">Last replied</a> %2$s ago by %3$s (%4$s).', 'Last reply ago', 'edd-support-tickets' ),
						esc_url( add_query_arg( array( 'post' => $post_id, 'action' => 'edit' ), admin_url( 'post.php' ) ) ) . '#eddstix-post-' . $query->posts[0]->ID,
						human_time_diff( strtotime( $activity_meta['reply_date'] ), current_time( 'timestamp' ) ),
						$user_link,
						$role ); ?></li><?php

				}

				/**
				 * Add tags
				 */
				if ( true === eddstix_is_reply_needed( $post_id, $latest ) ) {
					array_push( $tags, "<span class='eddstix-label' style='background-color:" . apply_filters( 'eddstix_awaiting_badge_color', '#f1c40f' ) . ";'>" . __( 'Awaiting Support Reply', 'edd-support-tickets' ) . "</span>" );
				}

				if ( true === eddstix_is_ticket_old( $post_id, $latest ) ) {
					array_push( $tags, "<span class='eddstix-label'>" . __( 'Old', 'edd-support-tickets' ) . "</span>" );
				}

				if ( ! empty( $tags ) ) {
					echo '<li>' . implode( ' ', $tags ) . '</li>';
				}				

				?><li><?php printf( _x( 'Created %s ago.', 'Ticket created on', 'edd-support-tickets' ), human_time_diff( get_the_time( 'U', $post_id ), current_time( 'timestamp' ) ) ); ?></li><?php					

				?><li><?php echo $number_replies; ?></li><?php

				echo '</ul>';

				break;

		}

	}

	/**
	 * Remove the ticket excerpt.
	 *
	 * We don't want ot display the ticket excerpt in the tickets list
	 * when the excerpt mode is selected.
	 * 
	 * @param  string $content Ticket excerpt
	 * @return string Excerpt if applicable or empty string otherwise
	 */
	public function remove_excerpt( $content ) {

		global $mode;

		if ( ! is_admin() ||! isset( $_GET['post_type'] ) || 'edd_ticket' !== $_GET['post_type'] ) {
			return $content;
		}

		global $mode;

		if ( 'excerpt' === $mode ) {
			return '';
		}

		return $content;
	}
}