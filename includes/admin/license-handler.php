<?php
add_action( 'admin_init', 'eddstix_updater', 0 );
function eddstix_updater() {
	$license = trim( eddstix_get_option( 'license_key' ) );
	$file = dirname( dirname(__DIR__) ) . '/edd-support-tickets.php';

	$edd_updater = new EDD_SL_Plugin_Updater( EDDSTIX_COSMICPLUGINS_URL, $file, array(
			'version' 	=> EDDSTIX_VERSION,
			'license' 	=> $license,
			'item_name' => CP_EDDSUPPORTTICKETS_NAME,
			'author' 	=> 'Isabel Castillo'
		)
	);
}

// Activate license key on settings save
add_action('admin_init', 'eddstix_activate_license');

function eddstix_activate_license() {
	// did we click save settings
	if ( ! isset( $_POST['eddstix_settings'] ) ) {
		return;
	}

	if ( ! isset( $_REQUEST['eddstix_license_key-nonce'] ) || ! check_admin_referer( 'eddstix_license_key-nonce', 'eddstix_license_key-nonce' ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_edd_ticket_settings' ) ) {
		return;
	}

	if ( empty( $_POST['eddstix_settings']['license_key'] ) ) {
		delete_option( 'eddstix_license_status' );
		return;
	}

	foreach ( $_POST as $key => $value ) {
		if( false !== strpos( $key, 'license_key_deactivate' ) ) {
			// Don't activate a key when deactivating a different key
			return;
		}
	}

	$details = get_option( 'eddstix_license_status' );
	if ( is_object( $details ) && 'valid' === $details->license ) {
		return;
	}
	

	$license = sanitize_text_field( $_POST['eddstix_settings']['license_key'] );

	if ( empty( $license ) ) {
		return;
	}

	// Data to send to the API
	$api_params = array(
		'edd_action' => 'activate_license',
		'license'    => $license,
		'item_name'  => urlencode( CP_EDDSUPPORTTICKETS_NAME ),
		'url'        => home_url()
	);
	// Call the API
	$response = wp_remote_post(
		EDDSTIX_COSMICPLUGINS_URL,
		array(
			'timeout'   => 15,
			'sslverify' => false,
			'body'      => $api_params
		)
	);
	// Make sure there are no errors
	if ( is_wp_error( $response ) ) {
		return;
	}
	// Tell WordPress to look for updates
	set_site_transient( 'update_plugins', null );
	$license_data = json_decode( wp_remote_retrieve_body( $response ) );
	// update license status
	update_option( 'eddstix_license_status', $license_data );
}

add_action('admin_init', 'eddstix_deactivate_license');

function eddstix_deactivate_license() {

	if ( ! isset( $_POST['eddstix_settings'] ) ) {
		return;
	}
	if ( ! isset( $_POST['eddstix_settings'][ 'license_key'] ) ) {
		return;
	}
	if( ! wp_verify_nonce( $_REQUEST[ 'eddstix_license_key-nonce'], 'eddstix_license_key-nonce' ) ) {
		wp_die( __( 'Nonce verification failed', 'edd-support-tickets' ), __( 'Error', 'edd-support-tickets' ), array( 'response' => 403 ) );
	}
	if( ! current_user_can( 'manage_edd_ticket_settings' ) ) {
		return;
	}

	$license = trim( eddstix_get_option( 'license_key' ) );

	// Run on deactivate button press
	if ( isset( $_POST[ 'eddstix_license_key_deactivate'] ) ) {

		$api_params = array(
			'edd_action' => 'deactivate_license',
			'license'    => $license,
			'item_name'  => urlencode( CP_EDDSUPPORTTICKETS_NAME ),
			'url'        => home_url()
		);
		$response = wp_remote_post(
			EDDSTIX_COSMICPLUGINS_URL,
			array(
				'timeout'   => 15,
				'sslverify' => false,
				'body'      => $api_params
			)
		);
		if ( is_wp_error( $response ) ) {
			return;
		}
		$license_data = json_decode( wp_remote_retrieve_body( $response ) );
		delete_option( 'eddstix_license_status' );
	}
}

// Check that license is valid once per week
add_action( 'edd_weekly_scheduled_events', 'eddstix_weekly_license_check' );
function eddstix_weekly_license_check() {
	if( ! empty( $_POST['eddstix_settings'] ) ) {
		return; // Don't fire when saving settings
	}
	$license = trim( eddstix_get_option( 'license_key' ) );
	if( empty( $license ) ) {
		return;
	}
	$api_params = array(
		'edd_action'=> 'check_license',
		'license' 	=> $license,
		'item_name' => urlencode( CP_EDDSUPPORTTICKETS_NAME ),
		'url'       => home_url()
	);
	$response = wp_remote_post(
		EDDSTIX_COSMICPLUGINS_URL,
		array(
			'timeout'   => 15,
			'sslverify' => false,
			'body'      => $api_params
		)
	);
	if ( is_wp_error( $response ) ) {
		return false;
	}

	$license_data = json_decode( wp_remote_retrieve_body( $response ) );
	update_option( 'eddstix_license_status', $license_data );
}

add_action( 'in_plugin_update_message-' . plugin_basename( dirname( dirname(__DIR__) ) . '/edd-support-tickets.php' ), 'eddstix_plugin_row_license_missing', 10, 2 );
/**
 * If plugin needes update, display message inline on plugin row that the license key is missing
 *
 * @return  void
 */
function eddstix_plugin_row_license_missing( $plugin_data, $version_info ) {

	static $showed_imissing_key_message;
	$license = get_option( 'eddstix_license_status' );
	if( ( ! is_object( $license ) || 'valid' !== $license->license ) && empty( $showed_imissing_key_message[ CP_EDDSUPPORTTICKETS_NAME ] ) ) {
		echo '&nbsp;<strong><a href="' . esc_url( admin_url( 'edit.php?post_type=edd_ticket&page=eddstix-settings&tab=license' ) ) . '">Enter valid license key for automatic updates.</a></strong>';
		$showed_imissing_key_message[ CP_EDDSUPPORTTICKETS_NAME ] = true;
	}
}
