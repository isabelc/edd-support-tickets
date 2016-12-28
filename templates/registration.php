<?php
/**
 * The template file to display the Log in and Registration form when
 * visitor is not logged in. 
 * If you need to customize this template,
 * copy it to your theme/edd-support-tickets/ and then modify it.
 */
global $post;
$registration  = eddstix_get_option( 'allow_registrations' );
$redirect_to   = get_permalink( $post->ID );
$wrapper_class = 0 == $registration ? 'eddstix-login-only' : 'eddstix-login-register';
?>
<div class="eddstix <?php echo $wrapper_class; ?>">

	<?php do_action( 'eddstix_before_login_register' ); ?>

	<form class="eddstix-form" method="post" role="form" action="<?php echo eddstix_get_login_url(); ?>">
		<h3><?php _e( 'Log in', 'edd-support-tickets' ); ?></h3>

		<?php
		/* Registrations are not allowed. */
		if ( 0 == $registration ) {
			eddstix_notification( false, 12 );
		}
		do_action( 'eddstix_before_login_fields' ); ?>
		<div class="eddstix-form-group">			
			<label><?php _e( 'Email or username', 'edd-support-tickets' ); ?></label>
			<input type="text" name="log" class="eddstix-form-control eddstix-input-text" placeholder="<?php _e( 'Email or username', 'edd-support-tickets' ); ?>" required>
		</div>
		<div class="eddstix-form-group">
			<label><?php _e( 'Password', 'edd-support-tickets' ); ?></label>
			<input type="password" name="pwd" class="eddstix-form-control eddstix-input-text" placeholder="<?php _e( 'Password', 'edd-support-tickets' ); ?>" required>
		</div>
		<?php do_action( 'eddstix_after_login_fields' ); ?>
		<div class="eddstix-checkbox">
			<label><input type="checkbox" name="rememberme" class="eddstix-form-control-checkbox"> <?php echo _e( 'Remember Me', 'edd-support-tickets' ); ?></label>
		</div>
		<input type="hidden" name="eddstix_login" value="1">
		<?php eddstix_make_button( __( 'Log in', 'edd-support-tickets' ), array( 'onsubmit' => __( 'Logging In...', 'edd-support-tickets' ) ) ); ?>
		<p class="eddstix-lost-pw"><a href="<?php echo wp_lostpassword_url( esc_url( add_query_arg( array( 'message' => 2 ), get_permalink() ) ) ); ?>" title="Lost Password">Lost Password</a></p>
	</form>

	<?php if ( $registration != 0 && ! is_singular( 'edd_ticket' ) ) : ?>
		<form class="eddstix-form" method="post" action="<?php echo $redirect_to; ?>">
			<h3><?php _e( 'Register', 'edd-support-tickets' ); ?></h3>
			<?php do_action( 'eddstix_before_registration_fields' ); ?>
			<div class="eddstix-form-group">
				<label><?php _e( 'First Name', 'edd-support-tickets' ); ?></label>
				<input class="eddstix-form-control eddstix-input-text" type="text" placeholder="<?php _e( 'First Name', 'edd-support-tickets' ); ?>" name="first_name" value="<?php echo eddstix_get_registration_field_value( 'first_name' ); ?>" required>
			</div>
			<div class="eddstix-form-group">
				<label><?php _e( 'Email', 'edd-support-tickets' ); ?></label>
				<input class="eddstix-form-control eddstix-input-text" type="email" placeholder="<?php _e( 'Email', 'edd-support-tickets' ); ?>" name="email" value="<?php echo eddstix_get_registration_field_value( 'email' ); ?>" required>
			</div>
			<div class="eddstix-form-group">
				<label><?php _e( 'Enter a password', 'edd-support-tickets' ); ?></label>
				<input type="password" name="password" class="eddstix-form-control eddstix-input-text" placeholder="<?php _e( 'Password', 'edd-support-tickets' ); ?>" value="<?php echo eddstix_get_registration_field_value( 'password' ); ?>" required>
			</div>

			<div id="pooh-hundred-acre-wood" class="eddstix-form-group">
			    <label for="pooh-hundred-acre-wood-field" id="pooh-hundred-acre-wood-label"><?php _e( 'For Official Use Only', 'textdomain' ); ?></label>
			    <input name="pooh_hundred_acre_wood_field" type="text" id="pooh-hundred-acre-wood-field" class="eddstix-form-control" value="" />
			</div>
			<?php do_action( 'eddstix_after_registration_fields' ); ?>
			<input type="hidden" name="eddstix_registration" value="true">
			<?php
			wp_nonce_field( 'register', 'user_registration', false, true );
			eddstix_make_button( __( 'Create Account', 'edd-support-tickets' ), array( 'onsubmit' => __( 'Creating Account...', 'edd-support-tickets' ) ) );
			?>
		</form>
	<?php endif; ?>
</div>