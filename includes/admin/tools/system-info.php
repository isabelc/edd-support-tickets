<p><?php _e( 'The system info is a built-in debugging tool. If you contact support, please provide this system info. <strong>Click the button below</strong> to download a text file with this system report.', 'edd-support-tickets' ); ?></p><form action="<?php echo esc_url( admin_url( 'edit.php?post_type=edd_ticket&page=eddstix-tools' ) ); ?>" method="post" dir="ltr"><textarea readonly="readonly" onclick="this.focus(); this.select()" id="system-info-textarea" name="eddstix-sysinfo" title="To copy the system info, click below then press Ctrl + C (PC) or Cmd + C (Mac).">
<?php
echo '### Begin System Info ###' . "\n\n";
echo '-- WordPress Info' . "\n\n";
echo 'Site URL:                 ' . site_url() . "\n";
echo 'Home URL:                 ' . home_url() . "\n";
echo 'WP Version:               ' . get_bloginfo('version') . "\n";
echo 'Multisite:                ' . ( is_multisite() ? 'Yes' : 'No' ) . "\n";
echo 'WP Language:              ' . get_locale() . "\n";
echo 'WP_DEBUG:                 ' . ( defined( 'WP_DEBUG' ) ? WP_DEBUG ? 'Enabled' : 'Disabled' : 'Not set' ) . "\n";
echo 'WP Active Plugins:        ' . count( (array) get_option( 'active_plugins' ) ) . "\n";
echo 'WP Max Upload Size:       ';
$wp_upload_max     = wp_max_upload_size();
$server_upload_max = intval( str_replace( 'M', '', ini_get('upload_max_filesize') ) ) * 1024 * 1024;
if ( $wp_upload_max <= $server_upload_max ) {
	echo size_format( $wp_upload_max );
} else {
	printf( '%s (The server only allows %s)', size_format( $wp_upload_max ), size_format( $server_upload_max ) );
}
echo "\n";
echo 'WP Memory Limit:          ' . WP_MEMORY_LIMIT . "\n";
echo 'WP Timezone:              ';
$timezone = get_option( 'timezone_string' );
echo empty( $timezone ) ? 'Timezone has not been set.' : $timezone . ' (UTC' . eddstix_get_offset_html5() . ')';
echo "\n";
echo "\n" . '-- Server' . "\n\n";
echo 'PHP Version:              ' . PHP_VERSION . "\n";
echo 'Software:                 ' . esc_html( $_SERVER['SERVER_SOFTWARE'] ) . "\n";

echo "\n" . '-- Plugin Settings' . "\n\n";

echo 'Version:                  ' . EDDSTIX_VERSION . "\n";
echo 'Tickets Slug:             ' . apply_filters( 'eddstix_rewrite_slug', 'ticket' ) . "\n";
echo 'Multiple Products:        ' . ( eddstix_get_option( 'multiple_products' ) ? 'Enabled' : 'Disabled' ) . "\n";

echo 'Require License Key:      ' . ( eddstix_get_option( 'need_license' ) ? 'Enabled' : 'Disabled' ) . "\n";
$reg = eddstix_get_option( 'allow_registrations' );
$reg_options = array( 'Disabled', 'Only for EDD customers', 'For everyone' );
echo 'Allow Registration:       ' . $reg_options[ $reg ] . "\n";
echo 'Auto-assignment:          ' . ( eddstix_get_option( 'disable_auto_assign' ) ? 'Disabled' : 'Enabled' ) . "\n";
echo 'Auto-assignment Staff:    ';
$staff = eddstix_get_option( 'roles_available_auto_assign' ) ? eddstix_get_option( 'roles_available_auto_assign' ) : array('none');
echo implode( ', ', array_values( $staff ) ) . "\n";
echo 'Default Agent:            ';
if ( $default_agent_id = eddstix_get_option( 'assignee_default' ) ) {
	$user = get_user_by( 'id', $default_agent_id );
}
echo empty( $user ) ? 'Not assigned' : $user->user_login;
echo "\n";
echo 'Uploads Folder:           ';
if ( ! is_dir( ABSPATH . 'wp-content/uploads/edd-support-tickets' ) ) {
	if ( ! is_writable( ABSPATH . 'wp-content/uploads' ) ) {
		echo 'The upload folder does not exist and cannot be created';
	} else {
		echo 'The upload folder does not exist but can be created';
	}
} else {
	if ( ! is_writable( ABSPATH . 'wp-content/uploads/edd-support-tickets' ) ) {
		echo 'The upload folder exists but is not writable';
	} else {
		echo 'The upload folder exists and is writable';
	}
}
echo  "\n";
echo 'Uploads:                  ' . ( boolval( eddstix_get_option( 'enable_attachments' ) ) ? 'Enabled' : 'Disabled' ) . "\n";
echo 'Allowed File Types:       ';
$filetypes = apply_filters( 'eddstix_attachments_filetypes', eddstix_get_option( 'attachments_filetypes' ) );
if ( empty( $filetypes ) ) {
	echo 'None';
} else {
	$filetypes = explode( ',', $filetypes );
	foreach ( $filetypes as $key => $type ) {
		$filetypes[$key] = ".$type";
	}
	$filetypes = implode( ', ', $filetypes );
	echo $filetypes;
}
echo "\n";
echo 'Maximum Upload File Size: ' . eddstix_get_option( 'filesize_max', 'blank' ) . " MB\n";
echo 'Maximum Upload Files:     ' . eddstix_get_option( 'attachments_max', 'blank' ) . "\n";
echo 'Agents See All:           ' . ( boolval( eddstix_get_option( 'agent_see_all' ) ) ? 'Enabled' : 'Disabled' ) . "\n";
echo "\n" . '-- Plugin Pages' . "\n\n";
echo 'Ticket Submission:        ';
$page_submit = eddstix_get_option( 'ticket_submit' );
echo empty( $page_submit ) ? 'Not set' : get_permalink( $page_submit ) . " (#$page_submit)";
echo "\n";
echo 'Tickets List:             ';
$page_list = eddstix_get_option( 'ticket_list' );
echo empty( $page_list ) ? 'Not set' : get_permalink( $page_list ) . " (#$page_list)";
echo "\n";
echo "\n" . '-- Email Notifications' . "\n\n";
echo 'Sender Name:              ' . eddstix_get_option( 'sender_name', get_bloginfo( 'name' ) ) . "\n";
echo 'Sender Email:             ' . eddstix_get_option( 'sender_email', get_bloginfo( 'admin_email' ) ) . "\n";
echo 'Reply-To Email:           ' . eddstix_get_option( 'reply_email', get_bloginfo( 'admin_email' ) ) . "\n";
echo 'Submission Confirmation:  ' . ( boolval( eddstix_get_option( 'enable_confirmation' ) ) ? 'Enabled' : 'Disabled' ) . "\n";
echo 'New Assignment:           ' . ( boolval( eddstix_get_option( 'enable_assignment' ) ) ? 'Enabled' : 'Disabled' ) . "\n";
echo 'New Agent Reply:          ' . ( boolval( eddstix_get_option( 'enable_reply_agent' ) ) ? 'Enabled' : 'Disabled' ) . "\n";
echo 'New Client Reply:         ' . ( boolval( eddstix_get_option( 'enable_reply_client' ) ) ? 'Enabled' : 'Disabled' ) . "\n";
echo 'Ticket Closed:            ' . ( boolval( eddstix_get_option( 'enable_closed' ) ) ? 'Enabled' : 'Disabled' ) . "\n";
echo "\n" . '-- Custom Fields' . "\n\n";

global $eddstix_cf;

$fields = $eddstix_cf->get_custom_fields();

if ( empty( $fields ) ) {
	echo 'None';
} else {
	foreach ( $fields as $field_id => $field ) {

		$values      = array();
		$attributes  = array( 'Capability' => $field['args']['capability'] );
		$attributes['Core']        = true === boolval( $field['args']['core'] ) ? 'Yes' : 'No';
		$attributes['Required']    = true === boolval( $field['args']['required'] ) ? 'Yes' : 'No';
		$attributes['Logged']      = true === boolval( $field['args']['log'] ) ? 'Yes' : 'No';
				$attributes['Show Column'] = true === boolval( $field['args']['show_column'] ) ? 'Yes' : 'No';

		if ( 'taxonomy' === $field['args']['callback'] ) {
			$attributes['Taxonomy'] = 'Yes (standard)';
		} else {
			$attributes['Taxonomy'] = 'No';
		}

		$attributes['Callback'] = $field['args']['callback'];
		foreach ( $attributes as $label => $value ) {
			array_push( $values,  "$label: $value" );
		}

		echo eddstix_get_field_title( $field ) . ":\t\t" . implode( ', ', $values ) . "\n";
	}
}

echo "\n" . '-- Plugins' . "\n\n";
echo "Installed:\n";
$active_plugins = (array) get_option( 'active_plugins', array() );

if ( is_multisite() )
	$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );

$wp_plugins = array();
foreach ( $active_plugins as $plugin ) {

	$plugin_data    = @get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin );
	if ( ! empty( $plugin_data['Name'] ) ) {
		$wp_plugins[] = $plugin_data['Name'] . ' by ' . $plugin_data['AuthorName'] . ' version ' . $plugin_data['Version'];
	}
}
if ( sizeof( $wp_plugins ) == 0 ) {
	echo '-';
} else {
	echo implode( ",\n", $wp_plugins );
}
echo "\n";
echo "\n" . '-- Theme' . "\n\n";
$active_theme = wp_get_theme();
echo 'Theme Name:               ' . $active_theme->Name . "\n";
echo 'Theme Version:            ' . $active_theme->Version . "\n";
if ( is_child_theme() ) {
	$parent_theme = wp_get_theme( $active_theme->Template );
	echo 'Parent Theme Name:        ' . $parent_theme->Name . "\n";
	echo 'Parent Theme Version:     ' . $parent_theme->Version . "\n";
}
echo 'Is Child Theme:           ' . ( empty( $parent_theme ) ? 'No' : 'Yes' ) . "\n";
echo "\n" . '-- Templates' . "\n\n";		
echo 'Overrides:';
$theme_directory       = trailingslashit( get_template_directory() ) . 'edd-support-tickets';
$child_theme_directory = trailingslashit( get_stylesheet_directory() ) . 'edd-support-tickets';
if ( is_dir( $child_theme_directory ) ) {

	$overrides = eddstix_check_templates_override( $child_theme_directory );

	if ( ! empty( $overrides ) ) {
		foreach ( $overrides as $key => $override ) {
			echo "\n$override\n";
		}
	} else {
		echo '                There is no template override';
	}

} elseif ( is_dir( $theme_directory ) ) {
	$overrides = eddstix_check_templates_override( $theme_directory );
	if ( ! empty( $overrides ) ) {
		foreach ( $overrides as $key => $override ) {
			echo "\n$override\n";
		}
	} else {
		echo '                There is no template override';
	}
} else {
	echo '                There is no template override';
}
echo "\n" . '### End System Info ###';
?>
</textarea>
<p class="submit">
<input type="hidden" name="eddstix-action" value="download_sysinfo" />
<?php submit_button( 'Download System Info File', 'primary', 'eddstix-download-sysinfo', false ); ?>
</p></form>