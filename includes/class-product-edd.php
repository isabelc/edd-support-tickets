<?php
/**
 * Easy Digital Downloads Product Integration.
 *
 * This class will, if EDD is enabled, synchronize the EDD products
 * with the product taxonomy of EDD Support Tickets and make the management
 * of products completely transparent.
 *
 * @package 	EDD_Support_Tickets
 * @author 		Isabel Castillo
 * @license 	GPL-2.0+
 * @copyright 	Copyright (c) 2015-2016, Isabel Castillo
 * 
 */
class EDDSTIX_Product_EDD {

	/**
	 * Instance of this class.
	 *
	 * @var      object
	 */
	protected static $instance = null;

	public function __construct() {
		if ( $this->is_enabled() ) {
			$sync = new EDDSTIX_Product_Sync( 'download', 'ticket_product', true );
		}

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
	 * Check if EDD is present and enabled.
	 *
	 * @return boolean True if EDD is in use, false otherwise
	 */
	protected function is_enabled() {

		if ( ! class_exists( 'Easy_Digital_Downloads' ) ) {
			return false;
		}

		$plugins = get_option( 'active_plugins', array() );
		$active  = false;

		foreach ( $plugins as $plugin ) {
			if ( strpos( $plugin, 'easy-digital-downloads.php' ) !== false) {
				$active = true;
			}
		}
		return $active;
	}

}