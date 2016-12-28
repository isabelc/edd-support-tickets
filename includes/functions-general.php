<?php
/**
 * Functions that are used both in back-end and front-end.
 */

/**
 * Get plugin option.
 * 
 * @param  string $option  Option to look for
 * @param  string $default Value to return if the requested option doesn't exist
 * @return mixed           Value for the requested option
 */
function eddstix_get_option( $option, $default = false ) {
	$options = get_option( 'eddstix_settings' );
	$value = isset( $options[$option] ) ? $options[$option] : $default;
	return apply_filters( 'eddstix_option_' . $option, $value );
}

/**
 * Add a security nonce.
 *
 * Adds a security nonce to URLs with a 
 * trigger for plugin custom action.
 * 
 * @param  (string) $url URL to nonce
 * @return (string)      Nonced URL
 */
function eddstix_nonce_url( $url ) {
	return esc_url( add_query_arg( array( 'eddstix-nonce' => wp_create_nonce( 'eddstix_custom_action' ) ), $url ) );
}

/**
 * Check a custom action nonce.
 *
 * @param  string $nonce  Nonce to be checked
 * @return boolean        Nonce validity
 */
function eddstix_check_nonce( $nonce ) {
	return wp_verify_nonce( $nonce, 'eddstix_custom_action' );
}

/**
 * Add custom action and nonce to URL.
 *
 * The function adds a custom action trigger using the eddstix-do
 * URL parameter and adds a security nonce for plugin custom actions.
 *  
 * @param  (string) $url    URL to customize
 * @param  (string) $action Custom action to add
 * @return (string)         Customized URL
 */
function eddstix_url_add_custom_action( $url, $action ) {
	$url = esc_url_raw( add_query_arg( array( 'eddstix-do' => sanitize_text_field( $action ) ), $url ) );
	return eddstix_nonce_url( $url );
}
/**
 * Returns the URL to re-open a ticket in the back end.
 */
function eddstix_get_open_ticket_url( $ticket_id, $action = 'open' ) {

	$remove = array( 'post', 'message' );
	$args   = $_GET;

	foreach ( $remove as $key ) {
		if ( isset( $args[$key] ) ) {
			unset( $args[$key] );
		}
	}
	$args['post'] = intval( $ticket_id );
	return eddstix_url_add_custom_action( add_query_arg( $args, admin_url( 'post.php' ) ), $action );
}
/**
 * Returns the URL to close a ticket in the back end.
 */
function eddstix_get_close_ticket_url( $ticket_id ) {
	return eddstix_get_open_ticket_url( $ticket_id, 'close' );
}

/**
 * Is plugin page.
 *
 * Checks if the current page belongs to the plugin or not.
 * 
 * @return boolean ether or not the current page belongs to the plugin
 */
function eddstix_is_plugin_page( $slug = '' ) {

	global $post;

	$plugin_admin_pages  = apply_filters( 'eddstix_plugin_admin_pages',  array( 'eddstix-tools', 'eddstix-settings' ) );
	/* Check for plugin pages in the admin */
	if ( is_admin() ) {

		/* First of all let's check if there is a specific slug given */
		if ( ! empty( $slug ) && in_array( $slug, $plugin_admin_pages ) ) {
			return true;
		}

		/* If the current post if of one of our post types */
		if ( isset( $post ) && isset( $post->post_type ) && ( 'edd_ticket' == $post->post_type ) ) {
			return true;
		}

		/* If the page we're in relates to one of our post types */
		if ( isset( $_GET['post_type'] ) && 'edd_ticket' == $_GET['post_type'] ) {
			return true;
		}

		/* If the page belongs to the plugin */
		if ( isset( $_GET['page'] ) && in_array( $_GET['page'], $plugin_admin_pages ) ) {
			return true;
		}

		/* In none of the previous conditions was true, return false by default. */
		return false;

	} else {

		$pages = array( eddstix_get_option( 'ticket_list' ), eddstix_get_option( 'ticket_submit' ) );
		if ( is_singular( 'edd_ticket' ) ) {
			return true;
		}

		if ( isset( $post ) && is_object( $post ) && in_array( $post->ID, $pages ) ) {
			return true;
		}

		return false;

	}

}

/**
 * Convert a slug into a nicely formatted title.
 *
 * @param  string $id slug to transform
 * @return string Nicely formatted title
 */
function eddstix_get_title_from_id( $id ) {
	return ucwords( str_replace( array( '-', '_' ), ' ', $id ) );
}

function eddstix_get_field_title( $field ) {
	if ( ! empty( $field['args']['title'] ) ) {
		return sanitize_text_field( $field['args']['title'] );
	} else {
		return eddstix_get_title_from_id( $field['name'] );
	}
}

function eddstix_make_button( $label = null, $args = array() ) {

	if ( is_null( $label ) ) {
		$label = __e( 'Submit', 'edd-support-tickets' );
	}

	$defaults = array(
		'type'     => 'button',
		'link'     => '',
		'class'    => 'eddstix-btn',
		'name'     => 'submit',
		'value'    => '',
		'onsubmit' => ''
	);

	extract( shortcode_atts( $defaults, $args ) );

	if ( 'link' === $type && ! empty( $link ) ) {
		?><a href="<?php echo esc_url( $link ); ?>" class="<?php echo $class; ?>" <?php if ( ! empty( $onsubmit ) ): echo "data-onsubmit='$onsubmit'"; endif; ?>><?php echo $label; ?></a><?php
	} else {
		?><button type="submit" class="<?php echo $class; ?>" name="<?php echo $name; ?>" value="<?php echo $value; ?>" <?php if ( ! empty( $onsubmit ) ): echo "data-onsubmit='$onsubmit'"; endif; ?>><?php echo $label; ?></button><?php
	}

}

/**
 * Get available ticket statuses wrapper function.
 *
 * @return array List of custom ticket statuses with labels
 */
function eddstix_get_custom_ticket_statuses() {
	return EDDSTIX_Ticket_Post_Type::get_custom_ticket_statuses();
}

/**
 * Get available ticket status keys
 *
 * @return array List of custom ticket status keys
 */
function eddstix_get_custom_ticket_status_keys() {
	
	$statuses = eddstix_get_custom_ticket_statuses();
	$statuses_keys = array();

	foreach ( $statuses as $status_id => $status_label ) {
		$statuses_keys[] = $status_id;
	}

	return $statuses_keys;
}

/**
 * Check whether a ticket has one of our custom statuses,
 * i.e. not 'draft', or 'future' or such.
 * @param mixed $ticket_id post ID of the ticket to check
 * @return bool true if ticket has an EDD Ticket custom status, otherwise false
 */

function eddstix_has_custom_status( $ticket_id ) {
	if ( empty( $ticket_id ) ) {
		return false;
	}
	$current_status = get_post_status( $ticket_id );
	$custom_statuses = eddstix_get_custom_ticket_status_keys();
 
 	return in_array( $current_status, $custom_statuses );
}

/**
 * Check whether a ticket status is open or closed.
 * @param string $status post_status of the ticket to check
 * @return bool true if ticket is open, otherwise false
 */

function eddstix_is_status_open( $status ) {
	if ( empty( $status ) ) {
		return false;
	}
 	if ( 'ticket_status_closed' == $status ) {
		return false;
	} else {
		return true;
	}
}

/**
 * Get the url of the current admin page
 *
 * @return string The URL
 */

function eddstix_get_current_admin_url() {

	global $pagenow;

	$get = $_GET;

	if ( ! isset( $get ) || ! is_array( $get ) ) {
		$get = array();
	}

	return esc_url( add_query_arg( $get, admin_url( $pagenow ) ) );

}

/**
 * Redirect to another page.
 *
 * The function will redirect to another page by using
 * wp_redirect if headers haven't been sent already. Otherwise
 * it uses a meta refresh tag.
 *
 * @param  string  $case     Redirect case used for filtering
 * @param  string  $location URL to redirect to
 * @param  mixed   $post_id  The ID of the post to redirect to (or null if none specified)
 * @return integer           Returns false if location is not provided, true otherwise
 */
function eddstix_redirect( $case, $location = null, $post_id = null ) {
	if ( is_null( $location ) ) {
		return false;
	}

	/**
	 * Filter the redirect URL.
	 *
	 * @param  string URL to redirect to
	 * @param  mixed  ID of the post to redirect to or null if none specified
	 */
	$location = apply_filters( "eddstix_redirect_$case", $location, $post_id );
	$location = wp_sanitize_redirect( $location );

	if ( ! headers_sent() ) {
		wp_redirect( $location, 302 );
	} else {
		echo "<meta http-equiv='refresh' content='0; url=$location'>";
	}

	return true;

}

/**
 * Create dropdown of things.
 *
 * @param  array $args     Dropdown settings
 * @param  string $options Dropdown options
 * @return string          Dropdown with custom options
 */
function eddstix_dropdown( $args, $options ) {

	$defaults = array(
		'name'          => 'eddstix_user',
		'id'            => '',
		'class'         => '',
		'please_select' => false,
		'select2'       => false,
		'disabled'      => false,
	);

	extract( wp_parse_args( $args, $defaults ) );

	$class = (array) $class;

	if ( true === $select2 ) {
		array_push( $class, 'eddstix-select2' );
	}

	ob_start(); ?>

	<select name="<?php echo $name; ?>" <?php if ( ! empty( $class ) ) echo 'class="' . implode( ' ' , $class ) . '"'; ?> <?php if ( ! empty( $id ) ) echo "id='$id'";  if ( true === $disabled ) { echo 'disabled'; } ?>>
		<?php
		if ( $please_select ) {
			echo '<option value="">' . __( 'Please select', 'edd-support-tickets' ) . '</option>';
		}

		echo $options;
		?>
	</select>

	<?php
	$contents = ob_get_contents();

	ob_end_clean();

	return $contents;

}
/**
 * Embed support for Gists with a URL
 */
if ( function_exists( 'wp_embed_register_handler' ) ) {
	wp_embed_register_handler( 'gist', '#(https://gist.github.com/([^\/]+\/)?([a-zA-Z0-9]+)(\/[a-zA-Z0-9]+)?)(\#file(\-|_)(.+))?$#i', 'eddstix_gist_embed_handler' );
}
function eddstix_gist_embed_handler( $matches, $attr, $url, $rawattr ) {

	/**
	 * Check if a file is specified. If not we set this match as null.
	 */
	if ( ! isset( $matches[7] ) || ! $matches[7] ) {
		$matches[7] = null;
	}

	$url  = $matches[1];  // Gist full URL
	$id   = $matches[3];  // Gist ID
	$file = $matches[7];  // Gist file
	$url  = $url . '.js'; // Append the .js extension

	/* Possibly add the file name within the Gist */
	if ( ! empty( $file ) ) {
		$file = preg_replace( '/[\-\.]([a-z]+)$/', '.\1', $file );
		$url = $url . '?file=' . $file;
	}

	$noscript = sprintf( __( 'View the code on <a href="%s">Gist</a>.', 'edd-support-tickets' ), esc_url( $url ) );
	$embed = sprintf( '<div class="oembed-gist"><script src="%s"></script><noscript>%s</noscript></div>', $url, $noscript );

	return apply_filters( 'embed_gist', $embed, $matches, $attr, $url, $rawattr );
}
