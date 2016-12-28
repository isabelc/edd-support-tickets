<?php
/**
 * The template for the Open a Ticket page.
 * If you need to customize this template, copy it to your 
 * theme/edd-support-tickets/ and then modify it.
 */

global $post;
?>

<div class="eddstix eddstix-submit-ticket">
	<form class="eddstix-form" role="form" method="post" action="<?php echo get_permalink( $post->ID ); ?>" id="eddstix-new-ticket" enctype="multipart/form-data">

		<?php do_action( 'eddstix_submission_form_inside_before_heading' ); ?>
		<div <?php eddstix_get_field_container_class( 'eddstix_title' ); ?>>
			<label><?php _e( 'Subject', 'edd-support-tickets' ); ?></label>
			<input name="eddstix_title" type="text" <?php eddstix_get_field_class( 'eddstix_title', 'eddstix-input-text' ); ?> value="<?php echo eddstix_get_field_value( 'eddstix_title', true ); ?>" placeholder="<?php echo apply_filters( 'eddstix_form_field_placeholder_eddstix_title', __( 'What is this about?', 'edd-support-tickets' ) ); ?>" required>
		</div>

		<?php
	
		/**
		 * The eddstix_submission_form_inside_after_subject hook has to be placed
		 * right after the subject field.
		 *
		 * This hook is very important as this is where the Download Products field 
		 * is hooked.
		 */
		do_action( 'eddstix_submission_form_inside_after_subject' );
		?>

		<div <?php eddstix_get_field_container_class( 'eddstix_message' ); ?>>
			<label><?php _e( 'How can we help you?', 'edd-support-tickets' ); ?></label>
			<?php
			/**
			 * The eddstix_get_message_textarea will generate the WYSIWYG editor
			 * used to submit the ticket description.
			 */
			eddstix_get_message_textarea(); ?>
		</div>

		<?php
		/**
		 * The eddstix_submission_form_inside_before_submit hook has to be placed
		 * right before the submission button.
		 */
		do_action( 'eddstix_submission_form_inside_before_submit' );

		wp_nonce_field( 'new_ticket', 'eddstix_nonce', false, true );
		eddstix_make_button( __( 'Submit ticket', 'edd-support-tickets' ), array( 'name' => 'eddstix-submit', 'onsubmit' => __( 'Please Wait...', 'edd-support-tickets' ) ) );
		
		/**
		 * The eddstix_submission_form_inside_after hook has to be placed
		 * right before the form closing tag.
		 */
		do_action( 'eddstix_submission_form_inside_after' );
		?>
	</form>
</div>