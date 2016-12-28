<?php 
add_shortcode( 'edd_support_tickets_list', 'eddstix_sc_client_tickets_list' );
/**
 * Tickets List page shortcode.
 */
function eddstix_sc_client_tickets_list() {

	global $eddstix_tickets, $current_user, $post;

	/**
	 * For some reason when the user ID is set to 0
	 * the query returns posts whose author has ID 1.
	 * In order to avoid that (for non logged users)
	 * we set the user ID to -1 if it is 0.
	 * 
	 * @var integer
	 */
	$author = ( 0 !== $current_user->ID ) ? $current_user->ID : -1;

	$args = array(
		'author'                 => $author,
		'post_type'              => 'edd_ticket',
		'post_status'            => 'any',
		'posts_per_page'         => -1,
		'no_found_rows'          => true,
		'orderby'   => 'meta_value',
		'meta_key' => '_last_activity'
	);

	$eddstix_tickets = new WP_Query( $args );

	/* Get the ticket content */
	ob_start();

	/**
	 * eddstix_frontend_plugin_page_top is executed at the top
	 * of every plugin page on the front end.
	 */
	do_action( 'eddstix_frontend_plugin_page_top', $post->ID, $post );

	/**
	 * Display possible messages to the visitor.
	 */
	if ( isset( $_GET['message'] ) ) {

		if ( is_numeric( $_GET['message'] ) ) {
			eddstix_notification( false, $_GET['message'] );
		} else {
			eddstix_notification( 'decode', $_GET['message'] );
		}
	}

	/* If user is not logged in we display the register form */
	if ( ! is_user_logged_in() ) :
		do_action( 'eddstix_before_my_tickets_login_register' );
		eddstix_get_template( 'registration' );

	else :

		eddstix_get_template( 'list' );

	endif;

	do_action( 'eddstix_after_tickets_list' );

	/**
	 * Finally get the buffer content and return.
	 * 
	 * @var string
	 */
	$content = ob_get_clean();

	return $content;

}