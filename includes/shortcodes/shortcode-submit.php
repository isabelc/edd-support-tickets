<?php
add_shortcode( 'edd_support_ticket_submit', 'eddstix_sc_submit_form' );
/**
 * Submission for shortcode.
 */
function eddstix_sc_submit_form() {

	global $post;

	ob_start();

	?><div class="eddstix"><?php

		/**
		 * eddstix_frontend_plugin_page_top is executed at the top
		 * of every plugin page on the front end.
		 */
		do_action( 'eddstix_frontend_plugin_page_top', $post->ID, $post );

		// Display possible messages to the visitor.
		if ( isset( $_GET['message'] ) ) {
			if ( is_numeric( $_GET['message'] ) ) {
				eddstix_notification( false, $_GET['message'] );
			} else {
				eddstix_notification( 'decode', $_GET['message'] );
			}
		}

		// If user is not logged in we display the register form
		if ( ! is_user_logged_in() ) :
			do_action( 'eddstix_before_submit_login_register' );
			eddstix_get_template( 'registration' );

		// If user is logged in we display the ticket submission form
		else :

			do_action( 'eddstix_before_ticket_submission_form_before_wrapper' );
			echo '<div class="eddstix">';
			eddstix_crumb();

			/**
			 * eddstix_before_all_templates hook.
			 *
			 * This hook is called at the top of every template
			 * used for the plugin front-end. This allows for adding actions
			 * on all plugin related pages.
			 */
			do_action( 'eddstix_before_all_templates' );

			// do_action( 'eddstix_before_ticket_submission_form' );

			/**
			 * We check if the user is authorized to submit a ticket.
			 * User can't have the admin capability. If the user isn't
			 * authorized to submit, we return the error message hereafter.
			 *
			 * Admins and agents aren't allowed to submit a ticket as they
			 * need to do it in the back-end.
			 *
			 * If you want to allow admins and agents to submit tickets through the
			 * front-end, please use the filter eddstix_agent_submit_front_end and set the value to (bool) true.
			 */

			if (  current_user_can( 'edit_edd_support_tickets' ) && ( false === apply_filters( 'eddstix_agent_submit_front_end', false ) ) ) {

				/**
				 * Keep in mind that if you allow agents to open ticket through the
				 * front-end, actions will not be tracked.
				 */
				eddstix_notification( 'info', sprintf( __( 'Sorry, support team members cannot submit tickets from here. If you need to open a ticket, please go to your admin panel or <a href="%s">click here to open a new ticket</a>.', 'edd-support-tickets' ), esc_url( add_query_arg( array( 'post_type' => 'edd_ticket' ), admin_url( 'post-new.php' ) ) ) ) );
			}

			// Show the actual submission form
			else {

				 // Is user cleared for support on at least 1 product?
				if ( false === eddstix_has_clearance() ):

					if ( eddstix_get_option( 'need_license' ) ) {
						$msg = apply_filters( 'eddstix_sorry_no_support', __( 'Sorry, but you need a valid license key in order to receive support. If this is a mistake, contact us.', 'edd-support-tickets' ) );
					} else {
						$msg = apply_filters( 'eddstix_sorry_no_support', __( 'Sorry, but support is for customers who have purchased something. If this is a mistake, contact us.', 'edd-support-tickets' ) );
					}
					eddstix_notification( 'failure', $msg );

				// If the user is authorized to post a ticket, we display the submit form
				else:
					global $post;
					do_action( 'eddstix_show_form_before' );
					eddstix_get_template( 'submission' );
					do_action( 'eddstix_show_form_after' );

				endif;
			}
			
			do_action( 'eddstix_after_ticket_submission_form' );

			echo '</div>';

		endif;
		do_action( 'eddstix_after_ticket_submit' ); ?>

	</div>

	<?php
	$sc = ob_get_contents();

	ob_end_clean();

	return $sc;
}