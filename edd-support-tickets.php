<?php
/*
Plugin Name: EDD Support Tickets
Plugin URI:	https://cosmicplugins.com/downloads/edd-support-tickets/
Description: Support ticket system which allows access only to your Easy Digital Downloads customers.
Version: 1.2
Author:	Isabel Castillo
Author URI:	http://isabelcastillo.com
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: edd-support-tickets
Domain Path: languages

Copyright 2016 Isabel Castillo

EDD Support Tickets is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

EDD Support Tickets is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with EDD Support Tickets. If not, see <http://www.gnu.org/licenses/>.

*/

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! defined( 'EDDSTIX_VERSION' ) ) {
	define( 'EDDSTIX_VERSION', '1.2' );// @todo update
}
if ( ! defined( 'EDDSTIX_URL' ) ) {
	define( 'EDDSTIX_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'EDDSTIX_PATH' ) ) {
	define( 'EDDSTIX_PATH', plugin_dir_path( __FILE__ ) );
}
// the URL of the site with EDD installed
if ( ! defined( 'EDDSTIX_COSMICPLUGINS_URL' ) ) {
	define( 'EDDSTIX_COSMICPLUGINS_URL', 'https://cosmicplugins.com' );
}
// the name of your product. Value should match the download name in EDD exactly
if ( ! defined( 'CP_EDDSUPPORTTICKETS_NAME' ) ) {
	define( 'CP_EDDSUPPORTTICKETS_NAME', 'EDD Support Tickets' );
}

/**
 * Settings
 */
global $eddstix_options;
require_once EDDSTIX_PATH . 'includes/admin/settings/register-settings.php';
$eddstix_options = get_option( 'eddstix_settings' );

require_once EDDSTIX_PATH . 'includes/functions-fallback.php';
require_once EDDSTIX_PATH . 'class-edd-support-tickets.php';

register_activation_hook( __FILE__, array( 'EDD_Support_Tickets', 'activate' ) );

/**
 * Get an instance of the plugin
 */
add_action( 'plugins_loaded', array( 'EDD_Support_Tickets', 'get_instance' ) );

/**
 * Classes and functions files that are shared through the backend and the frontend.
 */
require_once EDDSTIX_PATH . 'includes/class-product-edd.php';
require_once EDDSTIX_PATH . 'includes/custom-fields/class-custom-fields.php';
require_once EDDSTIX_PATH . 'includes/class-file-upload.php';
require_once EDDSTIX_PATH . 'includes/functions-post.php';
require_once EDDSTIX_PATH . 'includes/functions-user.php';
require_once EDDSTIX_PATH . 'includes/class-log-history.php';
require_once EDDSTIX_PATH . 'includes/class-email-notification.php';
require_once EDDSTIX_PATH . 'includes/functions-general.php';
require_once EDDSTIX_PATH . 'includes/functions-custom-fields.php';
require_once EDDSTIX_PATH . 'includes/functions-clearance.php';
require_once EDDSTIX_PATH . 'includes/functions-templating.php';
require_once EDDSTIX_PATH . 'includes/class-ticket-post-type.php';
require_once EDDSTIX_PATH . 'includes/class-product-sync.php';

if ( ! is_admin() ) {

	//  Public-Facing Only Functionality
	require_once EDDSTIX_PATH . 'includes/class-notification.php';
	require_once EDDSTIX_PATH . 'includes/shortcodes/shortcode-tickets.php';
	require_once EDDSTIX_PATH . 'includes/shortcodes/shortcode-submit.php';
	
} else {

	// Admin Only Functionality
	require_once EDDSTIX_PATH . 'includes/admin/class-admin-editor-ajax.php';
	require_once EDDSTIX_PATH . 'includes/admin/class-admin.php';
	require_once EDDSTIX_PATH . 'includes/admin/customers.php';
	if( ! class_exists( 'EDD_SL_Plugin_Updater' ) ) {
		include EDDSTIX_PATH . 'includes/admin/EDD_SL_Plugin_Updater.php';
	}
	require_once EDDSTIX_PATH . 'includes/admin/license-handler.php';
	add_action( 'plugins_loaded', array( 'EDD_Support_Tickets_Admin', 'get_instance' ) );
	// Add link to plugin row
	add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( 'EDD_Support_Tickets_Admin', 'settings_page_link' ) );
}
/**
 * Start the session if needed
 */
if ( ! session_id() && ! headers_sent() ) {
	session_start();
}
