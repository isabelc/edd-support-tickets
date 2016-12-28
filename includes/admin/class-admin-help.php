<?php
/**
 * Contextual Help.
 *
 * @package 	EDD_Support_Tickets 
 * @subpackage 	Admin/Help
 * @author 		Isabel Castillo
 * @license 	GPL-2.0+
 * @copyright 	Copyright (c) 2015-2016, Isabel Castillo
 */

class EDDSTIX_Help {

	/**
	 * Instance of this class.
	 *
	 * @var      object
	 */
	protected static $instance = null;
	
	public function __construct() {
		add_filter( 'contextual_help', array( $this, 'settings_general_contextual_help' ), 10, 3 );
		add_filter( 'contextual_help', array( $this, 'settings_emails_contextual_help' ), 10, 3 );
	}

	/**
	 * Return an instance of this class.
	 *
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * General settings contextual help.
	 *
	 * @return void
	 */
	public function settings_general_contextual_help() {

		if ( ! isset( $_GET['post_type'] ) || 'edd_ticket' !== $_GET['post_type'] || isset( $_GET['tab'] ) && 'general' !== $_GET['tab'] ) {
			return;
		}
		
		$screen = get_current_screen();
		$screen->add_help_tab( array(
			'id'      => 'multiple_products_help',
			'title'   => __( 'Multiple Products', 'edd-support-tickets' ),
			'content' => __( '<h2>Multiple Products</h2><p>The plugin can handle single product support and multiple products support.</p><p>Enabling multiple products support will add a &quot;Product&quot; column to the tickets admin page. </p>', 'edd-support-tickets' )
		) );
		$screen->add_help_tab( array(
			'id'      => 'allow_registrations',
			'title'   => __( 'Allow Registrations', 'edd-support-tickets' ),
			'content' => __( '<h2>Allow Registrations</h2><p>

				The plugin can register new users to your WordPress site. You can choose to allow registrations only for EDD customers, or for everyone. (Even if you allow registrations for everyone, only EDD customers will be allowed to submit support requests.)</p><p>If you allow registrations through the plugin but not through WordPress, users will only be able to register through our registration form.</p><p>If you disable registrations, then only existing, registered EDD customers will be able to log in for support.</p>', 'edd-support-tickets' )
		) );
	}

	/**
	 * Emails settings contextual help.
	 *
	 * @return void
	 */
	public function settings_emails_contextual_help() {

		if ( ! isset( $_GET['post_type'] ) || 'edd_ticket' !== $_GET['post_type'] || ! isset( $_GET['tab'] ) || 'emails' !== $_GET['tab'] ) {
			return;
		}

		/**
		 * Gather the list of email template tags and their description
		 */
		$list_tags = EDDSTIX_Email_Notification::get_tags();

		$tags = '<table class="widefat"><thead><th class="row-title">' . __( 'Tag', 'edd-support-tickets' ) . '</th><th>' . __( 'Description', 'edd-support-tickets' ) . '</th></thead><tbody>';

		foreach ( $list_tags as $the_tag ) {
			$tags .= '<tr><td class="row-title"><strong>' . $the_tag['tag'] . '</strong></td><td>' . $the_tag['desc'] . '</td></tr>';
		}

		$tags .= '</tbody></table>';
		
		$screen = get_current_screen();
	
		$screen->add_help_tab( array(
			'id'      => 'template-tags',
			'title'   => __( 'Email Template Tags', 'edd-support-tickets' ),
			'content' => sprintf( __( '<p>When setting up your email content below, you can use certain template tags allowing you to dynamically add ticket-related information at the moment the email is sent. Here is the list of available tags:</p>%s', 'edd-support-tickets' ), $tags )
		) );
	}

}