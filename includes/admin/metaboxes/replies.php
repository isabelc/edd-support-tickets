<?php
global $post;
$status = $post->post_status;
?>

<table class="form-table eddstix-table-replies">
	<tbody>

		<?php
		/* If the post hasn't been saved yet we do not display the metabox's content */
		if ( ! eddstix_has_custom_status( $post->ID ) ) : ?>
			<div class="updated below-h2" style="margin-top: 2em;">
				<h2 style="margin: 0.5em 0; padding: 0; line-height: 100%;"><?php _e( 'Create Ticket', 'edd-support-tickets' ); ?></h2>
				<p><?php _e( 'Please save this ticket to reveal all options.', 'edd-support-tickets' ); ?></p>
			</div>

		<?php
		/* Now let's display the real content */
		else :

			/* We're going to get all the posts part of the ticket history */
			$replies_args = array(
				'posts_per_page' =>	-1,
				'orderby'        =>	'post_date',
				'order'          =>	eddstix_get_option( 'replies_order', 'ASC' ),
				'post_type'      =>	apply_filters( 'eddstix_replies_post_type', array( 'edd_ticket_history', 'edd_ticket_reply' ) ),
				'post_parent'    =>	$post->ID,
				'post_status'    =>	apply_filters( 'eddstix_replies_post_status', array( 'publish', 'inherit', 'private', 'trash', 'read', 'unread' ) )
			);

			$history = new WP_Query( $replies_args );

			if ( ! empty( $history->posts ) ) :

				foreach( $history->posts as $row ) :

					/**
					 * Reply posted by registered member
					 */
					if ( $row->post_author != 0 ) {

						$user_data 		= get_userdata( $row->post_author );
						$user_id 		= $user_data->data->ID;
						$user_name 		= $user_data->data->display_name;

					}

					/**
					 * Reply posted anonymously
					 */
					else {
						$user_name 		= __('Anonymous', 'edd-support-tickets');
						$user_id 		= 0;
					}

					$user_avatar     = get_avatar( $user_id, '64', get_option( 'avatar_default' ) );
					$date            = human_time_diff( get_the_time( 'U', $row->ID ), current_time( 'timestamp' ) );
					$post_type       = $row->post_type;
					$post_type_class = ( 'edd_ticket_reply' === $row->post_type && 'trash' === $row->post_status ) ? 'edd_ticket_history' : $row->post_type;

					/**
					 * Layout for replies
					 */
					
					do_action( 'eddstix_backend_replies_outside_row_before', $row );
					?>
					<tr valign="top" class="eddstix-table-row eddstix-<?php echo str_replace( '_', '-', $post_type_class ); ?> eddstix-<?php echo str_replace( '_', '-', $row->post_status ); ?>" id="eddstix-post-<?php echo $row->ID; ?>">
					
					<?php
					do_action( 'eddstix_backend_replies_inside_row_before', $row );

					switch( $post_type ):

						/* Ticket Reply */
						case 'edd_ticket_reply':

							if ( 'trash' != $row->post_status ): ?>

								<td class="col1" style="width: 64px;">

									<?php
									/* Display avatar only for replies */
									if ( 'edd_ticket_reply' == $row->post_type ) {
										echo $user_avatar;
									}
									?>
									
								</td>
								<td class="col2">

									<?php if ( 'unread' === $row->post_status ): ?><div id="eddstix-unread-<?php echo $row->ID; ?>" class="eddstix-unread-badge"><?php _e( 'Unread', 'edd-support-tickets' ); ?></div><?php endif; ?>
									<div class="eddstix-reply-meta">
										<div class="eddstix-reply-user">
											<strong class="eddstix-profilename"><?php echo $user_name; ?></strong> <span class="eddstix-profilerole">(<?php echo eddstix_get_user_nice_role( $user_data->roles[0] ); ?>)</span>
										</div>
										<div class="eddstix-reply-time">
											<time class="eddstix-timestamp" datetime="<?php echo get_the_date( 'Y-m-d\TH:i:s' ) . eddstix_get_offset_html5(); ?>"><span class="eddstix-human-date"><?php echo date( get_option( 'date_format' ), strtotime( $row->post_date ) ); ?> | </span><?php printf( __( '%s ago', 'edd-support-tickets' ), $date ); ?></time>
										</div>
									</div>

									<div class="eddstix-ticket-controls">
										<?php
										if ( isset( $_GET['post'] ) && is_numeric( $_GET['post'] ) && get_current_user_id() == $row->post_author ) {

											$_GET['del_id'] = $row->ID;
											$url 			= esc_url( add_query_arg( $_GET, admin_url( 'post.php' ) ) );
											$url 			= esc_url( remove_query_arg( 'message', $url ) );
											$delete 		= eddstix_url_add_custom_action( $url, 'trash_reply' );
											echo '<a class="button-secondary eddstix-delete" href="' . esc_url( $delete ) . '"title="' . __( 'Delete', 'edd-support-tickets' ) . '">' . __( 'Delete', 'edd-support-tickets' ) . '</a>';
											echo '<a class="button-secondary eddstix-edit" href="#" data-origin="#eddstix-reply-' . $row->ID . '" data-replyid="' . $row->ID . '" data-reply="eddstix-editwrap-' . $row->ID . '" data-wysiwygid="eddstix-editreply-' . $row->ID . '" title="' . __( 'Edit', 'edd-support-tickets' ) . '">' . __( 'Edit', 'edd-support-tickets' ) . '</a>';

										}

										if ( get_current_user_id() !== $row->post_author && 'unread' === $row->post_status ) {
											echo '<a class="button-secondary eddstix-mark-read" href="#" data-replyid="' . $row->ID . '" title="' . __( 'Mark as Read', 'edd-support-tickets' ) . '">' . __( 'Mark as Read', 'edd-support-tickets' ) . '</a>';
										}
										?>
									</div>

									<?php
									/* Filter the content before we display it */
									$content = apply_filters( 'the_content', $row->post_content );

									/* The content displayed to agents */
									echo '<div class="eddstix-reply-content" id="eddstix-reply-' . $row->ID . '">';

									do_action( 'eddstix_backend_reply_content_before', $row->ID );

									echo $content;

									do_action( 'eddstix_backend_reply_content_after', $row->ID );

									echo '</div>';
									?>
								</td>

							<?php elseif ( 'trash' == $row->post_status ): ?>
								<td colspan="3">
									<?php printf( __( 'This reply has been deleted by %s <em class="eddstix-time">%s ago.</em>', 'edd-support-tickets' ), "<strong>$user_name</strong>", human_time_diff( strtotime( $row->post_modified ), current_time( 'timestamp' ) ) ); ?>
								</td>
							<?php endif;

						break;

						case 'edd_ticket_history':

							do_action( 'eddstix_backend_history_content_before', $row->ID );

							/* Filter the content before we display it */
							$content = apply_filters( 'the_content', $row->post_content );

							do_action( 'eddstix_backend_history_content_after', $row->ID ); ?>

							<td colspan="3">
								<span class="eddstix-action-author"><?php echo $user_name; ?>, <em class='eddstix-time'><?php printf( __( '%s ago', 'edd-support-tickets' ), $date ); ?></em></span>
								<div class="eddstix-action-details"><?php echo $content; ?></div>
							</td>

						<?php break;

					endswitch;

					do_action( 'eddstix_backend_replies_inside_row_after', $row );
					?>

					</tr>

					<?php if ( 'edd_ticket_reply' === $post_type && 'trash' !== $row->post_status ): ?>

						<tr class="eddstix-editor eddstix-editwrap-<?php echo $row->ID; ?>" style="display:none;">
							<td colspan="2">
								<div class="eddstix-wp-editor" style="margin-bottom: 1em;"></div>
								<input id="eddstix-edited-reply-<?php echo $row->ID; ?>" type="hidden" name="edited_reply">
								<input type="submit" id="eddstix-edit-submit-<?php echo $row->ID; ?>" class="button-primary eddstix-btn-save-edit" value="<?php _e( 'Save changes', 'edd-support-tickets' ); ?>"> 
								<input type="button" class="eddstix-editcancel button-secondary" data-origin="#eddstix-reply-<?php echo $row->ID; ?>" data-replyid="<?php echo $row->ID; ?>" data-reply="eddstix-editwrap-<?php echo $row->ID; ?>" data-wysiwygid="eddstix-editreply-<?php echo $row->ID; ?>" value="<?php _e( 'Cancel', 'edd-support-tickets' ); ?>">
							</td>
						</tr>

					<?php endif;

					do_action( 'eddstix_backend_replies_outside_row_after', $row );

				endforeach;
			endif;
		endif; ?>
	</tbody>
</table>

<?php
if ( eddstix_is_status_open( $status ) && eddstix_has_custom_status( $post->ID ) ) :

	if ( current_user_can( 'edit_edd_support_ticket', $post->ID ) ) : ?>
		<h2>
			<?php
			/**
			 * eddstix_write_reply_title_admin filter
			 * @param  string  Title to display
			 * @param  WP_Post Current post object
			 */
			echo apply_filters( 'eddstix_write_reply_title_admin', sprintf( __( 'Write a reply to &quot;%s&quot;', 'edd-support-tickets' ), get_the_title( $post->ID ) ), $post ); ?>
		</h2>
		<div>
			<?php
			/**
			 * Load the WordPress WYSIWYG with minimal options
			 */
			/* The edition textarea */
			wp_editor( '', 'eddstix_reply', array(
				'media_buttons' => false,
				'teeny' 		=> true,
				'quicktags' 	=> true,
				)
			);
			?>
		</div>
		<?php
		/**
		 * Hook after the WYSIWYG editor to hook the upload field.
		 */
		do_action( 'eddstix_admin_after_wysiwyg' );

		/**
		 * Add a nonce for the reply
		 */
		wp_nonce_field( 'reply_ticket', 'eddstix_reply_ticket', false, true ); ?>
		<div class="eddstix-reply-actions">
			<?php
			/**
			 * Where should the user be redirected after submission.
			 * 
			 * @var string
			 */
			global $current_user;
			$where = get_user_meta( $current_user->ID, 'eddstix_after_reply', true );
			switch ( $where ):
				case 'back': ?>
					<input type="hidden" name="eddstix_back_to_list" value="1">
					<button type="submit" name="eddstix_do" class="button-primary" value="reply"><?php _e( 'Reply', 'edd-support-tickets' ); ?></button>
				<?php break;				

				break;

				case false:
				case '':
				case 'stay': 
					?><button type="submit" name="eddstix_do" class="button-primary" value="reply"><?php _e( 'Reply', 'edd-support-tickets' ); ?></button><?php
				break;

				case 'ask': 
					?><fieldset>
						<strong><?php _e( 'After Replying', 'edd-support-tickets' ); ?></strong><br>
						<label for="back_to_list"><input type="radio" id="back_to_list" name="where_after" value="back_to_list" checked="checked"> <?php _e( 'Back to list', 'edd-support-tickets' ); ?></label>
						<label for="stay_here"><input type="radio" id="stay_here" name="where_after" value="stay_here"> <?php _e( 'Stay on ticket screen', 'edd-support-tickets' ); ?></label>
					</fieldset>
					<button type="submit" name="eddstix_do" class="button-primary" value="reply"><?php _e( 'Reply', 'edd-support-tickets' ); ?></button>
				<?php break;
			endswitch; ?>
				<button type="submit" name="eddstix_do" class="button-secondary" value="reply_close"><?php _e( 'Reply & Close', 'edd-support-tickets' ); ?></button>
		</div>

	<?php else: ?>

		<p><?php _e( 'Sorry, you don\'t have sufficient permissions to reply to tickets.', 'edd-support-tickets' ); ?></p>

	<?php endif;
/* The ticket was closed */
elseif ( 'ticket_status_closed' == $status ): ?>
	<div class="updated below-h2" style="margin-top: 2em;">
		<h2 style="margin: 0.5em 0; padding: 0; line-height: 100%;"><?php _e('Ticket is closed', 'edd-support-tickets'); ?></h2>
		<p><?php printf( __( 'This ticket has been closed. If you want to write a new reply to this ticket, you need to <a href="%s">re-open it first</a>.', 'edd-support-tickets' ), esc_url( eddstix_get_open_ticket_url( $post->ID ) ) ); ?></p>
	</div>
<?php endif;