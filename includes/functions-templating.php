<?php
/**
 * Templating Functions.
 *
 * This file contains all the templating functions. It aims at making it easy
 * for developers to gather ticket details and insert them in a custom template.
 *
 * @package 	EDD_Support_Tickets
 * @author 		Isabel Castillo
 * @license 	GPL-2.0+
 * @copyright 	Copyright (c) 2015-2016, Isabel Castillo
 */

/**
 * Alter page content for single ticket.
 *
 * In order to ensure maximum compatibility with all themes,
 * we hook onto the_content instead of changing the entire template
 * for ticket single.
 *
 * However, if the theme author has customized the single ticket template
 * we do not apply those modifications as the custom template will do the job.
 *
 * @param  string $content Post content
 * @return string          Ticket single
 */
function eddstix_single_ticket( $content ) {

	global $post;

	$slug = 'edd_ticket';

	/* Don't touch the admin */
	if ( is_admin() ) {
		return $content;
	}

	/* Only apply this on the ticket single. */
	if ( $slug !== $post->post_type ) {
		return $content;
	}

	/* Only apply this on the main query. */
	if ( ! is_main_query() ) {
		return $content;
	}

	/* Only apply this if it's inside of a loop. */
	if ( ! in_the_loop() ) {
		return $content;
	}

	/* Remove the filter to avoid infinite loops. */
	remove_filter( 'the_content', 'eddstix_single_ticket' );

	/**
	 * Display possible messages to the visitor.
	 */
	if ( isset( $_GET['message'] ) ) {
			eddstix_notification( false, $_GET['message'] );
	}

	/* Check if the current user can view the ticket */
	if ( ! eddstix_can_view_ticket( $post->ID ) ) {
		if ( is_user_logged_in() ) {
			return eddstix_notification( false, 13, false );
		} else {

			$output = '';
			$output .= eddstix_notification( false, 13, false );

			ob_start();
			eddstix_get_template( 'registration' );
			$output .= ob_get_clean();

			return $output;

		}
	}

	/* Get template name */
	$template_path = get_page_template();
	$template      = explode( '/', $template_path );
	$count         = count( $template );
	$template      = $template[$count-1];

	/* Don't apply the modifications on a custom template */
	if ( "single-$slug.php" === $template ) {
		return $content;
	}

	/* Get the ticket content */
	ob_start();

	/**
	 * eddstix_frontend_plugin_page_top is executed at the top
	 * of every plugin page on the front end.
	 */
	do_action( 'eddstix_frontend_plugin_page_top', $post->ID, $post );

	/**
	 * Get the custom template.
	 */
	eddstix_get_template( 'details' );

	/**
	 * Finally get the buffer content and return.
	 * 
	 * @var string
	 */
	$content = ob_get_clean();

	return $content;

}

/**
 * Get plugin template.
 *
 * The function takes a template file name and loads it
 * from whatever location the template is found first.
 * The template is being searched for (in order) in
 * the child theme, the theme and the default templates
 * folder within the plugin.
 *
 * @param  string $name  Name of the template to include
 * @param  array  $args  Pass variables to the template
 */
function eddstix_get_template( $name, $args = array() ) {

	if ( $args && is_array( $args ) )
		extract( $args );

	$template = eddstix_locate_template( $name );

	if ( ! file_exists( $template ) )
		return false;

	$template = apply_filters( 'eddstix_get_template', $template, $name, $args );

	do_action( 'eddstix_before_template', $name, $template, $args );

	include $template;

	do_action( 'eddstix_after_template', $name, $template, $args );

}

/**
 * Locate plugin template.
 *
 * The function will locate the template and return the path
 * from the child theme, if no child theme from the theme,
 * and if no template in the theme it will load the default
 * template stored in the plugin's /templates directory.
 *
 * @param  string $name Name of the template to locate
 *
 * @return string Template path
 */
function eddstix_locate_template( $name ) {
	$filename = "$name.php";
	$template = locate_template(
		array(
			'edd-support-tickets/' . $filename
		)
	);
	if ( ! $template ) { // then use ours
		$template = EDDSTIX_PATH . "templates/" . $filename;
	}

	return apply_filters( 'eddstix_locate_template', $template, $name );

}

/**
 * Get the ticket header.
 *
 * @param  array  $args Additional parameters
 * @return void
 */
function eddstix_ticket_header( $args = array() ) {
	global $eddstix_cf, $post;

	$default = array(
		'container'       => '',
		'container_id'    => '',
		'container_class' => '',
		'table_id'        => "header-ticket-$post->ID",
		'table_class'     => 'eddstix-table eddstix-ticket-details-header',
	);

	extract( shortcode_atts( $default, $args ) );

	$custom_fields = $eddstix_cf->get_custom_fields();
	$columns = array(
		'id'     => __( 'ID', 'edd-support-tickets' ),
		'status' => __( 'Status', 'edd-support-tickets' ),
		'date'   => __( 'Date', 'edd-support-tickets' ),
	);

	$columns_callbacks = array(
		'id'     => 'id',
		'status' => 'eddstix_cf_display_status',
		'date'   => 'date',
	);

	foreach ( $custom_fields as $field ) {

		/* Don't display core fields */
		if ( true === $field['args']['core'] ) {
			continue;
		}

		/* Don't display fields that aren't specifically designed to */
		if ( true === $field['args']['show_column'] ) {
			$columns[$field['name']]           = ! empty( $field['args']['title'] ) ? sanitize_text_field( $field['args']['title'] ) : eddstix_get_title_from_id( $field['name'] );

			$columns_callbacks[$field['name']] = ( 'taxonomy' === $field['args']['callback'] ) ? 'taxonomy' : $field['args']['column_callback'];
		}

	}

	$columns           = apply_filters( 'eddstix_tickets_details_columns', $columns );
	$columns_callbacks = apply_filters( 'eddstix_tickets_details_columns_callbacks', $columns_callbacks );
	?>

	<?php if ( ! empty( $container ) ): ?><<?php echo $container; ?>><?php endif; ?>

		<table id="<?php echo $table_id; ?>" class="<?php echo $table_class; ?>">
			<thead>
				<tr>
					<?php foreach ( $columns as $column => $label ): ?>
						<th><?php echo $label; ?></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<tr>
					<?php foreach ( $columns_callbacks as $column => $callback ): ?>
						<td>
							<?php eddstix_get_tickets_list_column_content( $column, array( 'callback' => $callback ) ); ?>
						</td>
					<?php endforeach; ?>
				</tr>
			</tbody>
		</table>

	<?php if ( ! empty( $container ) ): ?></<?php echo $container; ?>><?php endif; ?>

	<?php

}

/**
 * Display the reply form.
 *
 * @param  array  $args Additional arguments
 * @return void
 */
function eddstix_get_reply_form( $args = array() ) {

	global $wp_query;

	$post_id = $wp_query->post->ID;
	$status  = get_post_status( $post_id );
	$is_open = eddstix_is_status_open( $status );
	$can_reply = eddstix_can_reply_ticket();

	$defaults = array(
		'form_id'         => 'eddstix-new-reply',
		'form_class'      => 'eddstix-form',
		'container'       => 'div',
		'container_id'    => 'eddstix-reply-box',
		'container_class' => 'eddstix-form-group eddstix-wysiwyg-textarea',
		'textarea_before' => '',
		'textarea_after'  => '',
		'textarea_class'  => 'eddstix-form-control eddstix-wysiwyg',
	);

	extract( shortcode_atts( $defaults, $args ) );

	/**
	 * Filter the form class.
	 *
	 * This can be useful for addons doing something on the reply form,
	 * like adding an upload feature for instance.
	 *
	 * @var    string
	 */
	$form_class = apply_filters( 'eddstix_frontend_reply_form_class', $form_class );

	do_action( 'eddstix_ticket_details_reply_form_before' );

	if ( ! $is_open ):

		if ( false === $can_reply ) {
			eddstix_notification( 'info', sprintf( __( 'The ticket is closed. To re-open this ticket, please <a href="%s">go to your admin panel</a>.', 'edd-support-tickets' ), esc_url( add_query_arg( array( 'post' => $post_id, 'action' => 'edit' ), admin_url( 'post.php' ) ) ) ) );
		} else {
			eddstix_notification( 'info', sprintf( __( 'The ticket has been closed. If you feel that your issue has not been solved yet or something new came up in relation to this ticket, <a href="%s">you can re-open it by clicking this link</a>.', 'edd-support-tickets' ), esc_url( eddstix_get_reopen_url() ) ) );
		}

	/**
	 * Check if the ticket is currently open and if the current user
	 * is allowed to post a reply.
	 */
	elseif ( $is_open && true === $can_reply ): ?>

		<form id="<?php echo $form_id; ?>" class="<?php echo $form_class; ?>" method="post" action="<?php echo get_permalink( $post_id ); ?>" enctype="multipart/form-data">

			<?php do_action( 'eddstix_ticket_details_reply_textarea_before' ); ?>

			<<?php echo $container; ?> id="<?php echo $container_id; ?>" class="<?php echo $container_class; ?>">
				<?php echo $textarea_before;

				/**
				 * Load the visual editor
				 */
				$editor_defaults = apply_filters( 'eddstix_ticket_editor_args', array(
					'media_buttons' => false,
					'textarea_name' => 'eddstix_user_reply',
					'textarea_rows' => 10,
					'tabindex'      => 2,
					'editor_class'  => eddstix_get_field_class( 'eddstix_reply', $textarea_class, false ),
					'quicktags'     => false,
					'tinymce'       => array(
						'toolbar1' => 'bold,italic,underline,strikethrough,hr,|,bullist,numlist,|,link,unlink',
						'toolbar2' => ''
					),
				) );

				wp_editor( '', 'eddstix-reply-wysiwyg', apply_filters( 'eddstix_reply_wysiwyg_args', $editor_defaults ) );
				echo $textarea_after; ?>
			</<?php echo $container; ?>>
			<?php do_action( 'eddstix_ticket_details_reply_textarea_after' ); ?>
			<div class="checkbox">
				<label for="close_ticket">
					<input type="checkbox" name="eddstix_close_ticket" id="close_ticket" value="true"> <?php _e( 'Close this ticket', 'edd-support-tickets' ); ?>
				</label>
			</div>

			<?php do_action( 'eddstix_ticket_details_reply_close_checkbox_after' ); ?>

			<input type="hidden" name="ticket_id" value="<?php echo $post_id; ?>" />

			<?php
			wp_nonce_field( 'send_reply', 'client_reply', false, true );
			eddstix_make_button( __( 'Reply', 'edd-support-tickets' ), array( 'name' => 'eddstix-submit', 'onsubmit' => __( 'Please Wait...', 'edd-support-tickets' ) ) );

			do_action( 'eddstix_ticket_details_reply_form_before_close' );
			?>

		</form>

	<?php
	/**
	 * This case is an agent viewing the ticket from the front-end. All actions are tracked in the back-end only, that's why we prevent agents from replying through the front-end.
	 */
	elseif ( $is_open && false === $can_reply ) :
		eddstix_notification( 'info', sprintf( __( 'To reply to this ticket, please <a href="%s">go to your admin panel</a>.', 'edd-support-tickets' ), esc_url( add_query_arg( array( 'post' => $post_id, 'action' => 'edit' ), admin_url( 'post.php' ) ) ) ) );
	else :
		eddstix_notification( 'info', __( 'You are not allowed to reply to this ticket.', 'edd-support-tickets' ) );
	endif;

	do_action( 'eddstix_ticket_details_reply_form_after' );

}

/**
 * Get the URL to re-open a ticket.
 *
 * @param  integer $ticket_id ID of the ticket to re-open
 * @return string             The URL to trigger re-opening the ticket
 */
function eddstix_get_reopen_url( $ticket_id = null ) {

	global $wp_query;

	if ( is_null( $ticket_id ) ) {
		$ticket_id = intval( $wp_query->post->ID );
	}
	return apply_filters( 'eddstix_reopen_url', add_query_arg( array( 'action' => 'reopen', 'ticket_id' => $ticket_id ), get_permalink( $ticket_id ) ), $ticket_id );

}

/**
 * Get the login URL.
 *
 * This function returns the URL of the page used for logging in.
 * As of now it just uses the current post permalink
 * but it might be changed in the future.
 *
 * @return string URL of the login page
 */
function eddstix_get_login_url() {

	global $post;

	return get_permalink( $post->ID );

}

/**
 * Shows the message field.
 *
 * The function echoes the WYSIWYG editor where the user
 * may input the ticket description.
 *
 * @param  array  $editor_args Arguments used for TinyMCE
 * @return void
 */
function eddstix_get_message_textarea( $editor_args = array() ) {

	$editor_defaults = apply_filters( 'eddstix_ticket_editor_args', array(
		'media_buttons' => false,
		'textarea_name' => 'eddstix_message',
		'textarea_rows' => 10,
		'tabindex'      => 2,
		'editor_class'  => eddstix_get_field_class( 'eddstix_message', 'eddstix-wysiwyg', false ),
		'quicktags'     => false,
		'tinymce'       => array(
		'toolbar1' => 'bold,italic,underline,strikethrough,hr,|,bullist,numlist,|,link,unlink',
		'toolbar2' => ''
		),
	) );

	?><div class="eddstix-submit-ticket-wysiwyg"><?php
		wp_editor( eddstix_get_field_value( 'eddstix_message' ), 'eddstix-ticket-message', apply_filters( 'eddstix_reply_wysiwyg_args', $editor_defaults ) );
	?></div><?php
}

/**
 * Get tickets list columns.
 *
 * Retrieve the columns to display on the list of tickets
 * in the client area. The columns include status, date, ID
 * and Product if applicable.
 *
 * @return array The list of columns with their title and callback
 */
function eddstix_get_tickets_list_columns() {

	global $eddstix_cf;

	$custom_fields = $eddstix_cf->get_custom_fields();

	$columns = array(
		'status' => array( 'title' => __( 'Status', 'edd-support-tickets' ), 'callback' => 'eddstix_cf_display_status' ),
		'title'  => array( 'title' => __( 'Title', 'edd-support-tickets' ), 'callback' => 'title' ),
		'date'   => array( 'title' => __( 'Date', 'edd-support-tickets' ), 'callback' => 'date' ),
	);

	foreach ( $custom_fields as $field ) {

		/* Don't display core fields */
		if ( true === $field['args']['core'] ) {
			continue;
		}

		/* Don't display fields that aren't specifically designed to */
		if ( true === $field['args']['show_column'] ) {

			$column_title            = ! empty( $field['args']['title'] ) ? sanitize_text_field( $field['args']['title'] ) : eddstix_get_title_from_id( $field['name'] );
			$column_callback         = ( 'taxonomy' === $field['args']['callback'] ) ? 'taxonomy' : $field['args']['column_callback'];
			$columns[$field['name']] = array( 'title' => $column_title, 'callback' => $column_callback );
		}

	}

	return apply_filters( 'eddstix_tickets_list_columns', $columns );

}

/**
 * Get front-end ticket columns content.
 *
 * Used on both tickets list and single ticket.
 *
 * @param  string $column_id ID of the current column
 * @param  array  $column    Columns data
 * @return void
 */
function eddstix_get_tickets_list_column_content( $column_id, $column ) {

	$ticket_id = get_the_ID();

	$callback = $column['callback'];

	switch( $callback ) {

		case 'id':
			echo '#' . $ticket_id;
		break;

		case 'title':
			?><a href="<?php echo get_permalink( $ticket_id ); ?>"><?php the_title(); ?></a><?php
		break;

		case 'date':
			$offset = eddstix_get_offset_html5();
			?><time datetime="<?php echo get_the_date( 'Y-m-d\TH:i:s' ) . $offset ?>"><?php echo get_the_date( get_option( 'date_format' ) ) . ' ' . get_the_date( get_option( 'time_format' ) ); ?></time><?php
		break;

		case 'taxonomy':
			$terms = get_the_terms( $ticket_id, $column_id );
			if ( empty( $terms ) ) {
				continue;
			}
			$download = isset( $terms[0]->slug ) ? edd_get_download( $terms[0]->slug ) : '';
			echo isset( $download->post_title ) ? $download->post_title : '';
		break;

		default:
			if ( function_exists( $callback ) ) {
				call_user_func( $callback, $column_id, $ticket_id );
			}

		break;

	}
}

/**
 * Get HTML5 offset.
 *
 * Get the time offset based on the WordPress settings
 * and convert it into a standard HTML5 format.
 *
 * @return string HTML5 formatted time offset
 */
function eddstix_get_offset_html5() {

	$offset = get_option( 'gmt_offset' );

	/* Transform the offset in a W3C compliant format for datetime */
	$offset  = explode( '.', $offset );
	$hours   = $offset[0];
	$minutes = isset( $offset[1] ) ? $offset[1] : '00';
	$sign    = ( '-' === substr( $hours, 0, 1 ) ) ? '-' : '+';

	/* Remove the sign from the hours */
	if (  '-' === substr( $hours, 0, 1 ) ) {
		$hours = substr( $hours, 1 );
	}

	if ( 5 == $minutes ) {
		$minutes = '30';
	}

	if ( 1 === strlen( $hours ) ) {
		$hours = "0$hours";
	}

	$offset = "$sign$hours:$minutes";

	return $offset;

}

/**
 * Template tag to show a breadcrumb link back to the 'My Tickets'
 * page. This appears on single tickets and ticket submission page.
 */
function eddstix_crumb() {
	if ( ! current_user_can( 'edit_edd_support_tickets' ) ) {
		$page_id = eddstix_get_option( 'ticket_list' );
		$text = apply_filters( 'eddstix_crumb_text', '&larr; ' . get_the_title( $page_id ) );
		$link = apply_filters( 'eddstix_crumb_link', '<a href="' . get_permalink( $page_id ) . '">' . $text . '</a>' );
		echo apply_filters( 'eddstix_breadcrumb', '<span id="eddstix-crumb">' . $link . '</span>' );
	}
}
add_action( 'eddstix_tickets_list_before', 'eddstix_open_ticket_button' );
function eddstix_open_ticket_button() {
	eddstix_make_button( __( 'Open a ticket', 'edd-support-tickets' ), array( 'type' => 'link', 'link' => esc_url( get_permalink( eddstix_get_option( 'ticket_submit' ) ) ), 'class' => 'eddstix-btn eddstix-btn-open-ticket' ) );
}
/**
 * Add intro text above the ticket submission form.
 */
add_action( 'eddstix_show_form_before', 'eddstix_show_form_intro_text' );
function eddstix_show_form_intro_text() {
	echo '<p class="eddstix-submit-intro">' . apply_filters( 'eddstix_submit_ticket_form_intro', __( 'Fill this out to open a new support ticket.', 'edd-support-tickets' ) ) . '</p>';
}
/**
 * Add intro text above login/register forms on ticket submission page.
 */
add_action( 'eddstix_before_submit_login_register', 'eddstix_login_register_intro' );
function eddstix_login_register_intro() {
	echo '<p class="eddstix-submit-intro">' . apply_filters( 'eddstix_submit_ticket_login_intro', __( 'Please log in to open a support ticket.', 'edd-support-tickets' ) ) . '</p>';
}
