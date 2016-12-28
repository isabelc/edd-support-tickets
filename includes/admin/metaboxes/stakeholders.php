<?php
/**
 * Ticket Stakeholders.
 *
 * This metabox is used to display all parties involved in the ticket resolution.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}
global $wp_roles, $pagenow;

wp_nonce_field( EDD_Support_Tickets_Admin::$nonce_action, EDD_Support_Tickets_Admin::$nonce_name, false, true );

// Management can always edit stakeholders.
// Allow Agents/workers to edit stakeholders if they are creating a new ticket in the back-end
if ( current_user_can( 'manage_edd_ticket_settings' ) ||
	( current_user_can( 'edit_edd_support_tickets' ) && 'post-new.php' == $pagenow )
	) {
		$disabled = false;
} else {
		$disabled = true;
}
?>
<div id="eddstix-stakeholders">
	<label for="eddstix-issuer"><strong><?php _e( 'Support Customer', 'edd-support-tickets' ); ?></strong></label> 
	<p><?php $users_atts = array( 'agent_fallback' => true, 'select2' => true, 'name' => 'post_author_override', 'id' => 'eddstix-issuer' );

		if ( isset( $post ) ) {
			$users_atts['selected'] = $post->post_author;

			// if this is not a new post, add an All Customer Tickets link
			$link = esc_url( admin_url( "edit.php?post_type=edd_ticket&page=eddstix-alltickets&id=$post->post_author&ref=$post->ID" ) );
			$see_all = ( 'post-new.php' != $pagenow ) ? "(<a href='$link'>customer tickets</a>)" : '';
		}
		$users_atts['disabled'] = $disabled;
		eddstix_support_customers_dropdown( $users_atts ); ?>
	</p>
	<p class="description"><?php printf( __( 'This ticket has been raised by the user above. %s', 'edd-support-tickets' ), $see_all ); ?></p><br /><hr><br />

	<label for="eddstix-assignee"><strong><?php _e( 'Support Staff', 'edd-support-tickets' ); ?></strong></label>
	<p>
		<?php 
		// If agent see all is enabled, allow agents to edit staff.
		if ( true === boolval( eddstix_get_option( 'agent_see_all' ) ) ) {
			$disabled = false;
		}

		$staff_atts = array(
			'cap'      => 'edit_edd_support_tickets',
			'name'     => 'eddstix_assignee',
			'id'       => 'eddstix-assignee',
			'disabled' => $disabled,
			'select2'  => true
		);

		if ( isset( $post ) ) {
			$staff_atts['selected'] = get_post_meta( $post->ID, '_eddstix_assignee', true );
		}
		echo eddstix_users_dropdown( $staff_atts );
		?>
	</p>
	<p class="description"><?php printf( __( 'The above agent is currently responsible for this ticket.', 'edd-support-tickets' ), '#' ); ?></p>
</div>