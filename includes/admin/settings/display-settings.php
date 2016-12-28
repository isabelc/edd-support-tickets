<?php
/**
 * Text Callback
 *
 * @param array $args Arguments passed by the setting
 * @return void
 */
function eddstix_text_callback( $args ) {

	global $eddstix_options;

	if ( isset( $eddstix_options[ $args['id'] ] ) )
		$value = $eddstix_options[ $args['id'] ];
	else
		$value = isset( $args['std'] ) ? $args['std'] : '';
	$size = ( isset( $args['size'] ) && ! is_null( $args['size'] ) ) ? $args['size'] : 'regular';
	$html = '<input type="text" class="' . $size . '-text" id="eddstix_settings[' . $args['id'] . ']" name="eddstix_settings[' . $args['id'] . ']" value="' . esc_attr( stripslashes( $value ) ) . '"/>';
	$html .= '<label for="eddstix_settings[' . $args['id'] . ']"> '  . $args['desc'] . '</label>';
	echo $html;
}

/**
 * Textarea Callback
 *
 * @param array $args Arguments passed by the setting
 * @return void
 */
function eddstix_textarea_callback( $args ) {
	global $eddstix_options;
	if ( isset( $eddstix_options[ $args['id'] ] ) )
		$value = $eddstix_options[ $args['id'] ];
	else
		$value = isset( $args['std'] ) ? $args['std'] : '';
	$html = '<textarea class="large-text" cols="50" rows="5" id="eddstix_settings[' . $args['id'] . ']" name="eddstix_settings[' . $args['id'] . ']">' . esc_textarea( stripslashes( $value ) ) . '</textarea>';
	$html .= '<label for="eddstix_settings[' . $args['id'] . ']"> '  . $args['desc'] . '</label>';
	echo $html;
}

/**
 * Checkbox Callback
 *
 * @param array $args Arguments passed by the setting
 * @return void
 */
function eddstix_checkbox_callback( $args ) {

	global $eddstix_options;
	$checked = empty( $eddstix_options[ $args['id'] ] ) ? '' : checked( $eddstix_options[ $args['id'] ], 1, false );
	$html = '<input type="checkbox" id="eddstix_settings[' . $args['id'] . ']" name="eddstix_settings[' . $args['id'] . ']" value="1" ' . $checked . '/>';
	$html .= '<label for="eddstix_settings[' . $args['id'] . ']"> '  . $args['desc'] . '</label>';
	echo $html;
}

/**
 * Radio Callback
 *
 * Renders radio boxes.
 *
 * @param array $args Arguments passed by the setting
 * @return void
 */
function eddstix_radio_callback( $args ) {
	global $eddstix_options;
	foreach ( $args['options'] as $key => $option ) :
		$checked = false;
		if ( isset( $eddstix_options[ $args['id'] ] ) && $eddstix_options[ $args['id'] ] == $key )
			$checked = true;
		elseif ( isset( $args['std'] ) && $args['std'] == $key && ! isset( $eddstix_options[ $args['id'] ] ) )
			$checked = true;
		echo '<input name="eddstix_settings[' . $args['id'] . ']" id="eddstix_settings[' . $args['id'] . '][' . $key . ']" type="radio" value="' . $key . '" ' . checked(true, $checked, false) . '/>&nbsp;';
		echo '<label for="eddstix_settings[' . $args['id'] . '][' . $key . ']">' . $option . '</label><br/>';
	endforeach;
	echo '<p class="description">' . $args['desc'] . '</p>';
}

/**
 * Select Callback
 *
 * Renders select fields.
 *
 * @param array $args Arguments passed by the setting
 * @return void
 */
function eddstix_select_callback($args) {
	global $eddstix_options;
	if ( isset( $eddstix_options[ $args['id'] ] ) )
		$value = $eddstix_options[ $args['id'] ];
	else
		$value = isset( $args['std'] ) ? $args['std'] : '';
    if ( isset( $args['placeholder'] ) )
        $placeholder = $args['placeholder'];
    else
		$placeholder = '';
    $html = '<select id="eddstix_settings[' . $args['id'] . ']" name="eddstix_settings[' . $args['id'] . ']" data-placeholder="' . $placeholder . '" />';
	foreach ( $args['options'] as $option => $name ) :
		$selected = selected( $option, $value, false );
		$html .= '<option value="' . $option . '" ' . $selected . '>' . $name . '</option>';
	endforeach;
	$html .= '</select>';
	$html .= '<label for="eddstix_settings[' . $args['id'] . ']"> '  . $args['desc'] . '</label>';
	echo $html;
}
/**
 * Header Callback
 *
 * Renders the header.
 *
 * @param array $args Arguments passed by the setting
 * @return void
 */
function eddstix_header_callback( $args ) {
	if ( ! empty( $args['desc'] ) ) {
		echo '<hr/>' . esc_html( $args['desc'] ) . '<hr/>';
	} else {
		echo '<hr />';
	}
}
/**
 * Descriptive text callback.
 *
 * Renders descriptive text onto the settings field.
 *
 * @param array $args Arguments passed by the setting
 * @return void
 */
function eddstix_descriptive_text_callback( $args ) {
	echo $args['desc'];
}

/**
 * Rich Editor Callback
 *
 * Renders rich editor fields.
 *
 * @param array $args Arguments passed by the setting
 * @global $wp_version WordPress Version
 */
function eddstix_rich_editor_callback( $args ) {
	global $eddstix_options, $wp_version;

	if ( isset( $eddstix_options[ $args['id'] ] ) ) {
		$value = $eddstix_options[ $args['id'] ];
		if ( empty( $args['allow_blank'] ) && empty( $value ) ) {
			$value = isset( $args['std'] ) ? $args['std'] : '';
		}
	} else {
		$value = isset( $args['std'] ) ? $args['std'] : '';
	}
	$rows = isset( $args['size'] ) ? $args['size'] : 20;
	if ( $wp_version >= 3.3 && function_exists( 'wp_editor' ) ) {
		ob_start();
		wp_editor( stripslashes( $value ), 'eddstix_settings_' . $args['id'], array( 'textarea_name' => 'eddstix_settings[' . $args['id'] . ']', 'textarea_rows' => $rows ) );
		$html = ob_get_clean();
	} else {
		$html = '<textarea class="large-text" rows="10" id="eddstix_settings[' . $args['id'] . ']" name="eddstix_settings[' . $args['id'] . ']">' . esc_textarea( stripslashes( $value ) ) . '</textarea>';
	}
	$html .= '<br/><label for="eddstix_settings[' . $args['id'] . ']"> '  . $args['desc'] . '</label>';
	echo $html;
}

/**
 * Multicheck Callback
 *
 * Renders multiple checkboxes.
 *
 * @param array $args Arguments passed by the setting
 * @return void
 */
function eddstix_multicheck_callback( $args ) {
	global $eddstix_options;
	if ( ! empty( $args['options'] ) ) {
		echo '<p class="description">' . $args['desc'] . '</p>';		
		foreach( $args['options'] as $key => $option ) :
			if ( isset( $eddstix_options[$args['id']][$key] ) ) {
				$enabled = $option;
			} else {
				$enabled = NULL;
			}
			echo '<input name="eddstix_settings[' . $args['id'] . '][' . $key . ']" id="eddstix_settings[' . $args['id'] . '][' . $key . ']" type="checkbox" value="' . $option . '" ' . checked($option, $enabled, false) . '/>&nbsp;';
			echo '<label for="eddstix_settings[' . $args['id'] . '][' . $key . ']">' . $option . '</label><br/>';
		endforeach;
	}
}

/**
 * Registers the license key field callback
 *
 * @param array $args Arguments passed by the setting
 * @return void
 */
function eddstix_license_key_callback( $args ) {

	global $eddstix_options;

	$messages = array();
	
	$license  = get_option( 'eddstix_license_status' );

	$value =  isset( $eddstix_options[ $args['id'] ] ) ? $eddstix_options[ $args['id'] ] : '';

	if( ! empty( $license ) && is_object( $license ) ) {

		// activate_license 'invalid' on anything other than valid, so if there was an error capture it
		if ( false === $license->success ) {

			switch( $license->error ) {

				case 'expired' :

					$class = 'error';
					$messages[] = sprintf(
						__( 'Your license key expired on %s. Please <a href="%s" target="_blank" title="Renew your license key">renew your license key</a>.', 'edd-support-tickets' ),
						date_i18n( get_option( 'date_format' ), strtotime( $license->expires, current_time( 'timestamp' ) ) ),
						'https://cosmicplugins.com/checkout/?edd_license_key=' . $value
					);

					$license_status = 'license-' . $class . '-notice';

					break;

				case 'missing' :

					$class = 'error';
					$messages[] = sprintf(
						__( 'Invalid license. Please <a href="%s" target="_blank" title="Visit account page">visit your account page</a> and verify it.', 'edd-support-tickets' ),
						'https://cosmicplugins.com/my-account/'
					);

					$license_status = 'license-' . $class . '-notice';

					break;

				case 'invalid' :
				case 'site_inactive' :

					$class = 'error';
					$messages[] = sprintf(
						__( 'Your license key is not active for this URL. Please <a href="%s" target="_blank" title="Visit account page">visit your account page</a> to manage your license key URLs.', 'edd-support-tickets' ),
						'https://cosmicplugins.com/my-account/'
					);

					$license_status = 'license-' . $class . '-notice';

					break;

				case 'item_name_mismatch' :

					$class = 'error';
					$messages[] = __( 'This is not an EDD Support Tickets license key.', 'edd-support-tickets' );

					$license_status = 'license-' . $class . '-notice';

					break;

				case 'no_activations_left':

					$class = 'error';
					$messages[] = sprintf( __( 'Your license key has reached its activation limit. <a href="%s">View possible upgrades</a> now.', 'edd-support-tickets' ), 'https://cosmicplugins.com/my-account/' );

					$license_status = 'license-' . $class . '-notice';

					break;
			}

		} else {

			switch( $license->license ) {

				case 'valid' :
				default:

					$class = 'valid';

					$now        = current_time( 'timestamp' );
					$expiration = strtotime( $license->expires, current_time( 'timestamp' ) );

					if( 'lifetime' === $license->expires ) {

						$messages[] = __( 'License key never expires.', 'edd-support-tickets' );

						$license_status = 'license-lifetime-notice';

					} elseif( $expiration > $now && $expiration - $now < ( DAY_IN_SECONDS * 30 ) ) {

						$messages[] = sprintf(
							__( 'Your license key expires soon! It expires on %s. <a href="%s" target="_blank" title="Renew license">Renew your license key</a>.', 'edd-support-tickets' ),
							date_i18n( get_option( 'date_format' ), strtotime( $license->expires, current_time( 'timestamp' ) ) ),
							'https://cosmicplugins.com/checkout/?edd_license_key=' . $value
						);

						$license_status = 'license-expires-soon-notice';

					} else {

						$messages[] = sprintf(
							__( 'Your license key expires on %s.', 'edd-support-tickets' ),
							date_i18n( get_option( 'date_format' ), strtotime( $license->expires, current_time( 'timestamp' ) ) )
						);
						$license_status = 'license-expiration-date-notice';

					}

					break;

			}

		}

	} else {
		$license_status = null;
	}
	$html = '<input type="text" class="regular-text" id="eddstix_settings[' . $args['id'] . ']" name="eddstix_settings[' . $args['id'] . ']" value="' . esc_attr( $value ) . '"/>';

	if ( ( is_object( $license ) && 'valid' == $license->license ) || 'valid' == $license ) {
		$html .= '<input type="submit" class="button-secondary" name="eddstix_license_key_deactivate" value="' . __( 'Deactivate License',  'edd-support-tickets' ) . '"/>';
	}

	$html .= '<label for="eddstix_settings[' . $args['id'] . ']"> '  . wp_kses_post( $args['desc'] ) . '</label>';

	if ( ! empty( $messages ) ) {
		foreach( $messages as $message ) {

			$html .= '<div class="eddstix-license-data edd-license-' . $class . '">';
				$html .= '<p>' . $message . '</p>';
			$html .= '</div>';

		}
	}
	wp_nonce_field( 'eddstix_license_key-nonce', 'eddstix_license_key-nonce' );
	if ( isset( $license_status ) ) {
		echo '<div class="' . $license_status . '">' . $html . '</div>';
	} else {
		echo '<div class="license-null">' . $html . '</div>';
	}		

}


/**
 * Retrieve settings tabs
 *
 * @return array $tabs
 */
function eddstix_get_settings_tabs() {
	$tabs 					= array();
	$tabs['general'] 		= __( 'General', 'edd-support-tickets' );
	$tabs['auto_assignment'] = __( 'Auto-assignment', 'edd-support-tickets' );
	$tabs['emails']			= __( 'Emails', 'edd-support-tickets' );
	$tabs['fileupload']		= __( 'File Upload', 'edd-support-tickets' );
	$tabs['advanced'] 		= __( 'Advanced', 'edd-support-tickets' );
	$tabs['license'] 		= __( 'License', 'edd-support-tickets' );
	return apply_filters( 'eddstix_settings_tabs', $tabs );
}

/**
 * Options Page
 *
 * Renders the options page contents.
 *
 * @return void
 */
function eddstix_options_page() {
	$active_tab = isset( $_GET['tab'] ) && array_key_exists( $_GET['tab'], eddstix_get_settings_tabs() ) ? $_GET['tab'] : 'general';
	ob_start();
	settings_errors( 'eddstix-notices' ); ?>
	<div class="wrap">
		<h2 class="nav-tab-wrapper">
			<?php
			foreach( eddstix_get_settings_tabs() as $tab_id => $tab_name ) {

				$tab_url = add_query_arg( array(
					'settings-updated' => false,
					'tab' => $tab_id
				) );
				$active = $active_tab == $tab_id ? ' nav-tab-active' : '';
				echo '<a href="' . esc_url( $tab_url ) . '" title="' . esc_attr( $tab_name ) . '" class="nav-tab' . $active . '">';
					echo esc_html( $tab_name );
				echo '</a>';
			}
			?>
		</h2>
		<div id="tab_container">
			<form method="post" action="options.php">
				<?php settings_fields( 'eddstix_settings' ); ?>
				<table id="eddstix-options" class="form-table">
				<?php do_settings_fields( 'eddstix_settings_' . $active_tab, 'eddstix_settings_' . $active_tab ); ?>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
	</div>
	<?php
	echo ob_get_clean();
}
