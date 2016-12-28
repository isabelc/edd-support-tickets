<?php
/**
 * User.
 *
 * @package 	EDD_Support_Tickets
 * @subpackage 	Admin/User
 * @author 		Isabel Castillo
 * @license 	GPL-2.0+
 * @copyright 	Copyright (c) 2015-2016, Isabel Castillo
 */

class EDDSTIX_User {

	/**
	 * Instance of this class.
	 *
	 * @var      object
	 */
	protected static $instance = null;

	public function __construct() {
		add_action( 'show_user_profile', array( $this, 'user_profile_custom_fields' ) );
		add_action( 'personal_options_update',  array( $this, 'save_user_custom_fields' ) );
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
	 * Add user preferences to the profile page.
	 *
	 * @return void
	 */
	public function user_profile_custom_fields( $user ) { 
		if ( ! current_user_can( 'edit_edd_support_tickets' ) ) {
			return false;
		} ?>
		<h3><?php _e( 'EDD Support Tickets Preferences', 'edd-support-tickets' ); ?></h3>

		<table class="form-table">
			<tbody>
				<tr class="eddstix-after-reply-wrap">
					<th><label for="eddstix_after_reply"><?php echo _x( 'After Reply', 'Action after replying to a ticket', 'edd-support-tickets' ); ?></label></th>
					<td>
						<?php $after_reply = esc_attr( get_the_author_meta( 'eddstix_after_reply', $user->ID ) ); ?>
						<select name="eddstix_after_reply" id="eddstix_after_reply">
							<option value=""><?php _e( 'Default', 'edd-support-tickets' ); ?></option>
							<option value="stay" <?php if ( $after_reply === 'stay' ): ?>selected="selected"<?php endif; ?>><?php _e( 'Stay on screen', 'edd-support-tickets' ); ?></option>
							<option value="back" <?php if ( $after_reply === 'back' ): ?>selected="selected"<?php endif; ?>><?php _e( 'Back to list', 'edd-support-tickets' ); ?></option>
							<option value="ask" <?php if ( $after_reply === 'ask' ): ?>selected="selected"<?php endif; ?>><?php _e( 'Always ask', 'edd-support-tickets' ); ?></option>
						</select>
						<p class="description"><?php _e( 'Where do you want to go after replying to a ticket? Default is to stay on same screen.', 'edd-support-tickets' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>
	<?php }

	/**
	 * Save the user preferences.
	 *
	 * @param  integer $user_id ID of the user to modify
	 * @return void
	 */
	public function save_user_custom_fields( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return false;
		}

		update_user_meta( $user_id, 'eddstix_after_reply', $_POST['eddstix_after_reply'] );
	}
}