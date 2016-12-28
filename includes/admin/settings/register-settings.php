<?php
/**
 * Register Settings
 *
 * @package 	EDD_Support_Tickets
 * @copyright 	Copyright (c) 2015-2016, Isabel Castillo
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Get Settings
 *
 * Retrieves all plugin settings
 *
 * @return array EDDSTIX settings
 */

/**
 * Add all settings sections and fields
 *
 * @return void
*/
function eddstix_register_settings() {

	foreach( eddstix_get_registered_settings() as $tab => $settings ) {

		add_settings_section(
			'eddstix_settings_' . $tab,
			__return_null(),
			'__return_false',
			'eddstix_settings_' . $tab
		);

		foreach ( $settings as $option ) {

			$name = isset( $option['name'] ) ? $option['name'] : '';
			add_settings_field(
				'eddstix_settings[' . $option['id'] . ']',
				$name,
				'eddstix_' . $option['type'] . '_callback',
				'eddstix_settings_' . $tab,
				'eddstix_settings_' . $tab,
				array(
					'section'     => $tab,
					'id'          => isset( $option['id'] )          ? $option['id']      : null,
					'desc'        => ! empty( $option['desc'] )      ? $option['desc']    : '',
					'name'        => isset( $option['name'] )        ? $option['name']    : null,
					'size'        => isset( $option['size'] )        ? $option['size']    : null,
					'options'     => isset( $option['options'] )     ? $option['options'] : '',
					'std'         => isset( $option['std'] )         ? $option['std']     : ''
				)
			);

		}

	}

	register_setting( 'eddstix_settings', 'eddstix_settings', 'eddstix_settings_sanitize' );

}
add_action('admin_init', 'eddstix_register_settings');

/** 
 * Retrieve the array of plugin settings.
 *
 * @return array
*/
function eddstix_get_registered_settings() {

	$user_registration = boolval( get_option( 'users_can_register' ) );
	$registration_allowed  = ( true === $user_registration ) ? _x( 'enabled', 'User registration is enabled', 'edd-support-tickets' ) : _x( 'disabled', 'User registration is disabled', 'edd-support-tickets' );

	/**
	 * Filters are provided for each settings
	 * section to allow extensions to add more settings.
	 */
	$eddstix_settings = array(
		/** General Settings */
		'general' => apply_filters( 'eddstix_settings_general',
			array(
				array(
					'name' 		=> __( 'Multiple Products', 'edd-support-tickets' ),
					'id' 		=> 'multiple_products',
					'type' 		=> 'checkbox',
					'desc' 		=> __( 'Enable support for multiple products (as opposed to sites that sell just one product).', 'edd-support-tickets' )
				),
				array(
					'name' 		=> __( 'Require License Key', 'edd-support-tickets' ),
					'id' 		=> 'need_license',
					'type' 		=> 'checkbox',
					'desc' 		=> __( 'Customers must have a valid license key to submit a support ticket. <strong>Requires EDD Software Licensing extension.</strong>', 'edd-support-tickets' )
				),
				array(
					'name' 		=> __( 'Allow Registrations', 'edd-support-tickets' ),
					'id' 		=> 'allow_registrations',
					'type' 		=> 'select',
					'desc' 		=> sprintf( __( 'Allow users to register to your site. This setting can be enabled even though the WordPress setting is disabled. Currently, you have registration %s in WordPress settings. Even if you allow registrations for everyone, only customers can access support.', 'edd-support-tickets' ),  "<strong>$registration_allowed</strong>" ),
					'options'	=> array( 'disabled', 'only for EDD customers', 'for everyone' ),
					'std'		=>  1 // EDD customers only
				),
				array(
					'name' 		=> __( 'Replies Order', 'edd-support-tickets' ),
					'id' 		=> 'replies_order',
					'type' 		=> 'radio',
					'desc' 		=> __( 'In which order should the replies be displayed (for both client and admin side)?', 'edd-support-tickets' ),
					'options'	=> array(
						'ASC' => __( 'Old to New', 'edd-support-tickets' ),
						'DESC' => __( 'New to Old', 'edd-support-tickets' ) ),
					'std'		=> 'ASC'
				),
				array(
					'name' 		=> __( 'Show Count Bubble', 'edd-support-tickets' ),
					'id' 		=> 'show_count',
					'type' 		=> 'checkbox',
					'desc' 		=> __( 'Display the number of open tickets in an admin menu notification bubble.', 'edd-support-tickets' ),
					'std'		=> '1'
				),
				array(
					'name'	=> __( 'Old Tickets', 'edd-support-tickets' ),
					'id'	=> 'old_ticket',
					'type'	=> 'text',
					'size'	=> 'small',
					'std'	=> 10,
					'desc'	=> __( 'After how many days should a ticket be considered &quot;old&quot;?', 'edd-support-tickets' )
				),
				array(
					'name'	=> __( 'Plugin Pages', 'edd-support-tickets' ),
					'id'	=> 'plugin_pages_heading',
					'type'	=> 'header',
				),
				array(
					'name'	=> __( 'Ticket Submission', 'edd-support-tickets' ),
					'id'	=> 'ticket_submit',
					'type'	=> 'select',
					'desc'	=> sprintf( __( 'The page used for ticket submission. This page must contain the shortcode %s', 'edd-support-tickets' ), '<code>[edd_support_ticket_submit]</code>' ),
					'options' => eddstix_get_pages(),
					'std' 	=> eddstix_get_option( 'ticket_submit' ) ? eddstix_get_option( 'ticket_submit' ) : ''
				),
				array(
					'name' 		=> __( 'Tickets List', 'edd-support-tickets' ),
					'id' 		=> 'ticket_list',
					'type' 		=> 'select',
					'desc' 		=> sprintf( __( 'The page that will list all tickets for a client. This page must contain the shortcode %s', 'edd-support-tickets' ), '<code>[edd_support_tickets_list]</code>' ),
					'options' => eddstix_get_pages(),
					'std' 	=> eddstix_get_option( 'ticket_list' ) ? eddstix_get_option( 'ticket_list' ) : ''
				)
			)
		),

		/** Auto Assignment Settings */
		'auto_assignment'	=> apply_filters( 'eddstix_settings_auto_assignment',
			array(

				array(
					'id'	=> 'auto_assignment_header',
					'type' 	=> 'header',
					'desc'	=> 'Auto-assignment means that tickets are automatically assigned to support agents in your pool of available agents.'
				),
				array(
					'name' 		=> __( 'Staff Available For Auto-assignment', 'edd-support-tickets' ),
					'id' 		=> 'roles_available_auto_assign',
					'type' 		=> 'multicheck',
					'options'	=> array(
						'eddstix_agent'		=> __( 'Support Agent', 'edd-support-tickets' ),
						'eddstix_supervisor' => __( 'Support Supervisor', 'edd-support-tickets' ),
						'shop_worker'		=> __( 'EDD Shop Worker', 'edd-support-tickets' ),
						'shop_manager'		=> __( 'EDD Shop Manager', 'edd-support-tickets' ),
						'administrator'		=> __( 'Administrator', 'edd-support-tickets' )
						),
					'desc' 		=> __( 'Which roles should be included in the pool of available support agents?', 'edd-support-tickets' ),
					'std'		=> array( 'administrator' => 'Administrator')
				),
				array(
					'name' 		=> __( 'Disable Auto-assignment', 'edd-support-tickets' ),
					'id' 		=> 'disable_auto_assign',
					'type' 		=> 'checkbox',
					'desc' 		=> __( 'Check this to disable Auto-assignment. If checked, all tickets will be assigned to the following agent.', 'edd-support-tickets' )
				),				
				array(
					'name' 		=> __( 'Default Agent', 'edd-support-tickets' ),
					'id' 		=> 'assignee_default',
					'type' 		=> 'select',
					'desc' 		=> __( 'If Auto-assignment is disabled, who should all tickets get assigned to? This option only works if you disable Auto-assignment.', 'edd-support-tickets' ),
					'options' => isset( $_GET['post_type'] ) && 'edd_ticket' === $_GET['post_type'] && isset( $_GET['page'] ) && 'eddstix-settings' === $_GET['page'] ? eddstix_existing_staff() : array(),
					'std' 	=> ''
				),

			)
		),

		/** Emails Settings */
		'emails'	=> apply_filters( 'eddstix_settings_emails',
			array(
				array(
					'id'	=> 'email_settings_header',
					'type' 	=> 'header',
					'desc'	=> __( 'Email settings are for the different emails that are sent out by the plugin. For more information about the special tags that can be used in email content, please click the &laquo;Help&raquo; button in the top right hand corner of this screen.', 'edd-support-tickets' )
				),
				array(
					'name' 		=> __( 'Sender Name', 'edd-support-tickets' ),
					'id' 		=> 'sender_name',
					'type' 		=> 'text',
					'std' 	=> get_bloginfo( 'name' )
				),
				array(
					'name' 		=> __( 'Sender Email', 'edd-support-tickets' ),
					'id' 		=> 'sender_email',
					'type' 		=> 'text',
					'std' 	=> get_bloginfo( 'admin_email' )
				),
				array(
					'name' 		=> __( 'Reply-To Email', 'edd-support-tickets' ),
					'id' 		=> 'reply_email',
					'type' 		=> 'text',
					'std' 	=> get_bloginfo( 'admin_email' )
				),
				/* Submission confirmation */
				array(
					'name' => __( 'Submission Confirmation', 'edd-support-tickets' ),
					'id'	=> 'submission_conf_heading',
					'type' => 'header',
				),
				array(
					'id'	=> 'submission_conf_desc',
					'type' => 'descriptive_text',
					'desc' => __( 'This email is sent to a customer immediately after they open a new ticket.', 'edd-support-tickets' )
				),
				array(
					'name'	=> __( 'Enable', 'edd-support-tickets' ),
					'id'	=> 'enable_confirmation',
					'type'	=> 'checkbox',
					'std'	=> '1',
					'desc'	=> __( 'Activate this type of email', 'edd-support-tickets' )
				),
				array(
					'name' 		=> __( 'Subject', 'edd-support-tickets' ),
					'id' 		=> 'subject_confirmation',
					'type' 		=> 'text',
					'std' 	=> __( 'Request received: {ticket_title}', 'edd-support-tickets' )
				),
				array(
					'name'     => __( 'Content', 'edd-support-tickets' ),
					'id'       => 'content_confirmation',
					'type'     => 'rich_editor',
					'std'		=> '<p>Hi <strong><em>{client_name}</em>,</strong></p><p>Your request (#{ticket_id}) has been received, and is being reviewed by our support staff.</p><p>To add additional comments:</p><h2><a href="{ticket_url}">View Ticket</a></h2><p>or follow this link: {ticket_link}</p><p>Regards,<br>{site_name}</p>'
				),
				/* New assignment */
				array(
					'name' => __( 'New Assignment', 'edd-support-tickets' ),
					'id'	=> 'new_assignment_heading',
					'type' => 'header',
				),
				array(
					'id'	=> 'new_assignment_desc',
					'type' => 'descriptive_text',
					'desc' => __( 'This email is sent to a support agent when they receive a new ticket.', 'edd-support-tickets' )
				),
				array(
					'name' 		=> __( 'Enable', 'edd-support-tickets' ),
					'id' 		=> 'enable_assignment',
					'type' 		=> 'checkbox',
					'std'		=> '1',
					'desc' 		=> __( 'Activate this type of email', 'edd-support-tickets' )
				),
				array(
					'name' 		=> __( 'Subject', 'edd-support-tickets' ),
					'id' 		=> 'subject_assignment',
					'type' 		=> 'text',
					'std' 	=> __( 'Ticket #{ticket_id} assigned', 'edd-support-tickets' )
				),
				array(
					'name'     => __( 'Content', 'edd-support-tickets' ),
					'id'       => 'content_assignment',
					'type'     => 'rich_editor',
					'std'		=> '<p>Hi <strong><em>{agent_name},</em></strong></p><p>The request, <strong>{ticket_title}</strong> (#{ticket_id}), has been assigned to you.</p><h2><a href="{ticket_admin_url}">View  Ticket</a></h2><p>or follow this link: {ticket_admin_link}</p><p>Regards,<br />{site_name}</p>'
				),
				/* New reply from agent */
				array(
					'name' => __( 'New Reply from Agent', 'edd-support-tickets' ),
					'id'	=> 'new_reply_heading',
					'type' => 'header',
				),
				array(
					'id'	=> 'agent_reply_desc',
					'type' => 'descriptive_text',
					'desc' => __( 'This email is sent to a customer when a reply to their ticket is posted.', 'edd-support-tickets' )
				),
				array(
					'name' 		=> __( 'Enable', 'edd-support-tickets' ),
					'id' 		=> 'enable_reply_agent',
					'type' 		=> 'checkbox',
					'std'		=> '1',
					'desc' 		=> __( 'Activate this type of email', 'edd-support-tickets' )
				),
				array(
					'name' 		=> __( 'Subject', 'edd-support-tickets' ),
					'id' 		=> 'subject_reply_agent',
					'type' 		=> 'text',
					'std' 	=> __( 'New reply to: {ticket_title}', 'edd-support-tickets' )
				),
				array(
					'name'     => __( 'Content', 'edd-support-tickets' ),
					'id'       => 'content_reply_agent',
					'type'     => 'rich_editor',
					'std'		=> '<p>Hi <strong><em>{client_name}</em>,</strong></p><p>A support agent just replied to your ticket, "<strong>{ticket_title}</strong>" (#{ticket_id}). To view the reply or add additional comments:</p><h2><a href="{ticket_url}">View Ticket</a></h2><p>or follow this link: {ticket_link}</p><p>Regards,<br>{site_name}</p>'
				),
				/* New reply from client */
				array(
					'name' => __( 'New Reply from Client', 'edd-support-tickets' ),
					'id'	=> 'new_reply_client_heading',
					'type' => 'header',
				),
				array(
					'id'	=> 'client_reply_desc',
					'type' => 'descriptive_text',
					'desc' => __( 'This email is sent to the support agent when a customer adds a reply to a ticket.', 'edd-support-tickets' )
				),
				array(
					'name' 		=> __( 'Enable', 'edd-support-tickets' ),
					'id' 		=> 'enable_reply_client',
					'type' 		=> 'checkbox',
					'std'		=> '1',
					'desc' 		=> __( 'Activate this type of email', 'edd-support-tickets' )
				),
				array(
					'name' 		=> __( 'Subject', 'edd-support-tickets' ),
					'id' 		=> 'subject_reply_client',
					'type' 		=> 'text',
					'std' 	=> __( 'Ticket #{ticket_id}', 'edd-support-tickets' )
				),
				array(
					'name'     => __( 'Content', 'edd-support-tickets' ),
					'id'       => 'content_reply_client',
					'type'     => 'rich_editor',
					'std'		=> '<p>Hi <strong><em>{agent_name},</em></strong></p><p>A client you are in charge of just posted a new reply to their ticket, "<strong>{ticket_title}</strong>".</p><h2><a href="{ticket_admin_url}">View  Ticket</a></h2><p>or follow this link: {ticket_admin_link}</p><p>Regards,<br>{site_name}</p>'
				),
				/* Ticket closed */
				array(
					'name' => __( 'Ticket Closed', 'edd-support-tickets' ),
					'id'	=> 'ticket_closed_heading',
					'type' => 'header',
				),
				array(
					'id'	=> 'ticket_closed_desc',
					'type' => 'descriptive_text',
					'desc' => __( 'This email is sent to the customer when their ticket is closed.', 'edd-support-tickets' )
				),
				array(
					'name' 		=> __( 'Enable', 'edd-support-tickets' ),
					'id' 		=> 'enable_closed',
					'type' 		=> 'checkbox',
					'std'  => '1',
					'desc' 		=> __( 'Activate this type of email', 'edd-support-tickets' )
				),
				array(
					'name' 		=> __( 'Subject', 'edd-support-tickets' ),
					'id' 		=> 'subject_closed',
					'type' 		=> 'text',
					'std' 	=> __( 'Request closed: {ticket_title}', 'edd-support-tickets' )
				),
				array(
					'name'     => __( 'Content', 'edd-support-tickets' ),
					'id'       => 'content_closed',
					'type'     => 'rich_editor',
					'std'  => '<p>Hi <strong><em>{client_name},</em></strong></p>Your support request (<a href="{ticket_url}">#{ticket_id}</a>) has been closed by <strong>{agent_name}</strong>.</p><p>Regards,<br>{site_name}</p>'
				),
				/* New reply from client */
				array(
					'name'	=> __( 'New Registration Notification', 'edd-support-tickets' ),
					'id'	=> 'new_reg_heading',
					'type'	=> 'header',
				),
				array(
					'id'	=> 'new_reg_desc',
					'type' => 'descriptive_text',
					'desc' => __( 'WordPress sends New Registration Notifications to the administrator when a new user is registered.', 'edd-support-tickets' )
				),
				array(
					'name'	=> __( 'Disable', 'edd-support-tickets' ),
					'id'	=> 'disable_new_reg_notify',
					'type'	=> 'checkbox',
					'desc'	=> __( 'Check this box if you do NOT want to receive an email when a new user is registered by the plugin.', 'edd-support-tickets' )
				)
			)
		),
		/** File Upload Settings */
		'fileupload'	=> apply_filters( 'eddstix_settings_fileupload',
			array(
				array(
					'name' 		=> __( 'Enable File Upload', 'edd-support-tickets' ),
					'id' 		=> 'enable_attachments',
					'type' 		=> 'checkbox',
					'std'		=> '1',
					'desc' 		=> __( 'Check this to allow your users (and agents) to upload attachments to tickets and replies.', 'edd-support-tickets' )
				),
				array(
					'name' 		=> __( 'Maximum Files', 'edd-support-tickets' ),
					'id' 		=> 'attachments_max',
					'type' 		=> 'text',
					'size' => 'small',
					'std' 		=> 2,
					'desc' 		=> __( 'How many files can a user attach to a ticket or a reply?', 'edd-support-tickets' )
				),
				array(
					'name' 		=> __( 'Maximum File Size', 'edd-support-tickets' ),
					'id' 		=> 'filesize_max',
					'type' 		=> 'text',
					'size' => 'small',
					'std'		=> 2,
					'desc' 		=> sprintf( __( 'What is the maximum size allowed for one file (in <code>MB</code>)? Your server allows up to %s.', 'edd-support-tickets' ), ini_get('upload_max_filesize') )
				),
				array(
					'name' 		=> __( 'Allowed File Types', 'edd-support-tickets' ),
					'id' 		=> 'attachments_filetypes',
					'type' 		=> 'textarea',
					'std' 	=> 'jpg,jpeg,png,gif,pdf,doc,docx,ppt,pptx,pps,ppsx,odt,xls,xlsx,mp3,m4a,ogg,wav,mp4,m4v,mov,wmv,avi,mpg,ogv,3gp,3g2,zip',
					'desc' 		=> sprintf( __( 'Which file types do you allow your users to attach? Please separate each extension by a comma (%s).', 'edd-support-tickets' ), '<code>,</code>' )
				),

			)
		),

		/** Advanced Settings */
		'advanced'	=> apply_filters( 'eddstix_settings_advanced',
			array(
				array(
					'name' 		=> __( 'Allow Agents To See All', 'edd-support-tickets' ),
					'id' 		=> 'agent_see_all',
					'type' 		=> 'checkbox',
					'desc' 		=> __( 'Check this to allow support agents (and EDD Shop Workers) to see all tickets in the tickets list. They\'ll be able to work on all tickets. If unchecked, agents will only see tickets assigned to them. (Support Supervisors and EDD Shop Managers always see all tickets.)', 'edd-support-tickets' )
				),
				array(
					'name' => __( 'Danger Zone', 'edd-support-tickets' ),
					'id'	=> 'danger_zone_heading',
					'type' => 'header',
				),
				array(
					'name' 		=> __( 'Delete Data', 'edd-support-tickets' ),
					'id' 		=> 'delete_data',
					'type' 		=> 'checkbox',
					'desc' 		=> __( 'Delete ALL plugin data on uninstall? This cannot be undone.', 'edd-support-tickets' )
				)
			)
		),
		'license'	=> apply_filters( 'eddstix_settings_license',
			array(
				array(
					'id'	=> 'license_settings_header',
					'type' 	=> 'descriptive_text',
					'desc'	=> sprintf( __( 'Enter your EDD Support Tickets license key here to receive version updates. If your license key has expired, please %1$srenew your license%2$s.', 'edd-support-tickets' ),
						'<a href="https://cosmicplugins.com/faq#jl-licenserenew" target="_blank">',
						'</a>' )
				),
				array(
					'name' 		=> __( 'License Key', 'edd-support-tickets' ),
					'id' 		=> 'license_key',
					'type' 		=> 'license_key'
				),
			)
		)
	);

	return apply_filters( 'eddstix_registered_settings', $eddstix_settings );
}

/**
 * Settings Sanitization
 *
 * Adds a settings error (for the updated message)
 *
 * @param array $input The value inputted in the field
 *
 * @return string $input Sanitizied value
 */
function eddstix_settings_sanitize( $input = array() ) {

	$eddstix_options = get_option( 'eddstix_settings' );
	if ( empty( $_POST['_wp_http_referer'] ) ) {
		return $input;
	}

	parse_str( $_POST['_wp_http_referer'], $referrer );

	$settings 	= eddstix_get_registered_settings();
	$tab 		= isset( $referrer['tab'] ) ? $referrer['tab'] : 'general';
	$input 		= $input ? $input : array();

	$types = array();
	foreach ( $settings[ $tab ] as $v ) {
		$types[ $v['id'] ] = $v['type'];
	}

	// Loop through each setting being saved and pass it through a sanitization filter
	foreach ( $input as $key => $value ) {

		// Get the setting type (checkbox, select, etc)
		$type = empty( $types[ $key ] ) ? false : $types[ $key ];

		if ( $type ) {
			// Field type specific filter
			$input[ $key ] = apply_filters( 'eddstix_settings_sanitize_' . $type, $value, $key );
		}

		// General filter
		$input[ $key ] = apply_filters( 'eddstix_settings_sanitize', $input[ $key ], $key );
	}

	// Loop through the whitelist and unset any that are empty for the tab being saved
	if ( ! empty( $settings[ $tab ] ) ) {
		foreach ( $settings[ $tab ] as $setting ) {
			$key = $setting['id'];
			if ( empty( $input[ $key ] ) ) {
				unset( $eddstix_options[ $key ] );
			}

		}
	}

	// Merge our new settings with the existing
	$output = array_merge( $eddstix_options, $input );

	add_settings_error( 'eddstix-notices', '', __( 'Settings updated.', 'edd-support-tickets' ), 'updated' );
	return $output;
}

/**
 * Sanitize text fields
 *
 * @param string $input The field value
 * @param string $key The field key
 * @return string $input Sanitizied value
 */
function eddstix_sanitize_text_field( $input, $key ) {
	return trim( $input );
}
add_filter( 'eddstix_settings_sanitize_text', 'eddstix_sanitize_text_field', 10, 2 );

/**
 * Sanitize multicheck field.
 *
 * Just used to delete the eddstix_get_support_staff transient 
 *
 * @param array $input The field value
 * @param string $key The field key
 * @return array $input Sanitizied value
 */
function eddstix_clear_support_staff_transient( $input, $key ) {
	if ( 'roles_available_auto_assign' == $key ) {
		delete_transient( 'eddstix_get_support_staff' );
	}
	return $input;
}
add_filter( 'eddstix_settings_sanitize_multicheck', 'eddstix_clear_support_staff_transient', 10, 2 );

/**
* Retrieve a list of all published pages
*
* On large sites this can be expensive, so only load if on the settings page.
*
* @return array $pages_options An array of the pages
*/
function eddstix_get_pages() {
	$pages_options = array( '' => '' );
	if ( ! isset( $_GET['page'] ) || 'eddstix-settings' != $_GET['page'] ) {
		return $pages_options;
	}
	$pages = get_pages();
	if ( $pages ) {
		foreach ( $pages as $page ) {
			$pages_options[ $page->ID ] = $page->post_title;
		}
	}
	return $pages_options;
}

/**
 * Set cap required to save EDDSTIX settings
 *
 * @return string capability required
 */
function eddstix_set_settings_cap() {
	return 'manage_edd_ticket_settings';
}
add_filter( 'option_page_capability_eddstix_settings', 'eddstix_set_settings_cap' );
