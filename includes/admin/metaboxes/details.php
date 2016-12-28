<?php
/**
 * Ticket Status.
 *
 * This metabox is used to display the ticket current status
 * and change it in one click.
 *
 * For more details on how the ticket status is changed,
 * @see EDD_Support_Tickets_Admin::custom_actions()
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}
global $pagenow, $post;

$post_status = isset( $post ) ? $post->post_status : '';
$is_open = eddstix_is_status_open( $post_status );

// Status action link
$action = ( ! $is_open ) ? eddstix_get_open_ticket_url( $post->ID ) : eddstix_get_close_ticket_url( $post->ID );

// Get available statuses.
$statuses = eddstix_get_custom_ticket_statuses();

// Get time
if ( isset( $post ) ) {
	$date = human_time_diff( get_the_time( 'U', $post->ID ), current_time( 'timestamp' ) );
}
?>
<div class="eddstix-ticket-status submitbox">
<div id="misc-publishing-actions">
	<p id="eddstix-ticket-status-display">
		<?php _e( 'Ticket status:', 'edd-support-tickets' ); ?>
		<?php if ( 'post-new.php' != $pagenow ):
			eddstix_cf_display_status( '', $post->ID );
			?>
		<?php else: ?>
			<span><?php _x( 'Creating...', 'Ticket creation', 'edd-support-tickets' ); ?></span>
		<?php endif; ?>
	</p>
	<?php if ( isset( $post ) ): ?><p id="eddstix-ticket-opened"><?php _e( 'Opened:', 'edd-support-tickets' ); ?> <em><strong><?php printf( __( '%s ago', 'edd-support-tickets' ), $date ); ?></em></strong></p><?php endif; ?>
	<?php if ( $is_open ): ?>
		<label for="eddstix-post-status"><?php _e( 'Edit status:', 'edd-support-tickets' ); ?></label>
		<p>
			<select id="eddstix-post-status" name="post_status_override" style="width: 100%">
				<?php foreach ( $statuses as $status => $label ):
					// Omit closed status
					if ( 'ticket_status_closed' == $status ) {
						continue;	
					}
					$selected = ( $post_status === $status ) ? 'selected="selected"' : '';
					if ( 'auto-draft' === $post_status && 'ticket_processing' === $status ) { $selected = 'selected="selected"'; } ?>
					<option value="<?php echo $status; ?>" <?php echo $selected; ?>><?php echo $label; ?></option>
				<?php endforeach; ?>
			</select>
			<?php if ( isset( $_GET['post'] ) ): ?>
				<input type="hidden" name="eddstix_post_parent" value="<?php echo $_GET['post']; ?>">
			<?php endif; ?>
		</p>
	<?php endif; ?>

	</div>
	<div id="major-publishing-actions">
		<?php if ( current_user_can( 'edit_edd_support_ticket', $post->ID ) ) : ?>
			<div id="delete-action">
				<a class="submitdelete deletion" href="<?php echo esc_url( $action ); ?>">
					<?php
					if ( ! $is_open ) {
						_e( 'Re-open', 'edd-support-tickets' );
					} elseif ( '' === $post_status ) {
						_e( 'Open', 'edd-support-tickets' );
					} else {
						_e( 'Close', 'edd-support-tickets' );
					}
					?>
				</a>
			</div>
		<?php endif;

		if ( current_user_can( 'edit_edd_support_tickets' ) ) : ?>
			<div id="publishing-action">
				<span class="spinner"></span>
				<?php if ( isset( $_GET['action'] ) && 'edit' === $_GET['action'] ) :
		
						submit_button( __( 'Update Ticket' ), 'primary large', 'save', false, array( 'accesskey' => 'u' ) );
				else:
						submit_button( __( 'Open Ticket' ), 'primary large', 'publish', false, array( 'accesskey' => 'o' ) );
				endif; ?>
			</div>
		<?php endif; ?>
		<div class="clear"></div>
	</div>
</div>