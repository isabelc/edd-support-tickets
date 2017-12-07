<?php
/**
 * This metabox is used to display the ticket Product metabox.
 */

// If this file is called directly, abort. 
if ( ! defined( 'WPINC' ) ) {
	die;
} ?>

<div class="eddstix-additional-info">
	<?php
	/**
	 * Get all custom fields and display them
	 */
	global $eddstix_cf;
	$options = $eddstix_cf->get_custom_fields();
	if ( ! empty( $options ) ) {

		do_action( 'eddstix_mb_details_before_addl_info' );

		foreach( $options as $option ) {

			$core = isset( $option['args']['core'] ) ? $option['args']['core'] : false;

			/**
			 * Don't display core fields
			 */
			if ( $core )
				continue;

			/**
			 * Output the field
			 */
			if ( method_exists( 'EDDSTIX_Custom_Fields_Display', $option['args']['callback'] ) ) {
				EDDSTIX_Custom_Fields_Display::{$option['args']['callback']}( $option );
			}
			do_action( 'eddstix_display_custom_fields', $option );
		}
		do_action( 'eddstix_mb_details_after_addl_info' );
	} ?>
</div>