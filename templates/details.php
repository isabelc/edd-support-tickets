<?php
/**
 * Ticket Details Template for a single ticket.
 * 
 * If you need to customize this template, copy it to your 
 * theme/edd-support-tickets/ and then modify it.
 */

/* Exit if accessed directly */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $eddstix_replies, $post;

/* Get author meta */
$author = get_user_by( 'id', $post->post_author );
?>
<div class="eddstix eddstix-ticket-details">

	<?php
	eddstix_crumb();
	/**
	 * Display the table header containing the tickets details.
	 * By default, the header will contain ticket ID, status, date,
	 * and product (if multiple_products is enabled).
	 */
	eddstix_ticket_header();
	?>
	<table class="eddstix-table eddstix-ticket-replies">
		<tbody>
			<tr class="eddstix-reply-single" valign="top">
				<td style="width: 64px;">
					<div class="eddstix-user-profile">
						<?php echo get_avatar( $post->post_author, '64', get_option( 'avatar_default' ) ); ?>
					</div>
				</td>

				<td>
					<div class="eddstix-reply-meta">
						<div class="eddstix-reply-user">
							<strong class="eddstix-profilename"><?php echo $author->data->display_name; ?></strong>
						</div>
						<div class="eddstix-reply-time">
							<time class="eddstix-timestamp" datetime="<?php echo get_the_date( 'Y-m-d\TH:i:s' ) . eddstix_get_offset_html5(); ?>">
								<span class="eddstix-human-date"><?php echo get_the_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $post->ID ); ?></span>
								<span class="eddstix-date-ago"><?php printf( __( '%s ago', 'edd-support-tickets' ), human_time_diff( get_the_time( 'U', $post->ID ), current_time( 'timestamp' ) ) ); ?></span>
							</time>
						</div>
					</div>

					<?php do_action( 'eddstix_frontend_ticket_content_before', $post->ID, $post );

					/**
					 * Display the original ticket's content
					 */
					echo '<div class="eddstix-reply-content">' . apply_filters( 'the_content', $post->post_content ) . '</div>';

					do_action( 'eddstix_frontend_ticket_content_after', $post->ID, $post ); ?>

				</td>

			</tr>

			<?php
			/**
			 * Start the loop for the ticket replies.
			 */
			if ( $eddstix_replies->have_posts() ):
				while ( $eddstix_replies->have_posts() ):

					$eddstix_replies->the_post();
					$user      = get_userdata( $post->post_author );
					$user_role = get_the_author_meta( 'roles' );
					$user_role = $user_role[0];
					$time_ago  = human_time_diff( get_the_time( 'U', $post->ID ), current_time( 'timestamp' ) ); ?>

					<tr id="reply-<?php echo the_ID(); ?>" class="eddstix-reply-single eddstix-status-<?php echo get_post_status(); ?>" valign="top">

						<?php
						/**
						 * Make sure the reply hasn't been deleted.
						 */
						if ( 'trash' === get_post_status() ) { ?>

							<td colspan="2">
								<?php printf( __( 'This reply has been deleted %s ago.', 'edd-support-tickets' ), $time_ago ); ?>
							</td>
						
						<?php continue; } ?>

						<td style="width: 64px;">
							<div class="eddstix-user-profile">
								<?php echo get_avatar( get_the_author_meta( 'user_email' ), 64, get_option( 'avatar_default' ) ); ?>
							</div>
						</td>

						<td>
							<div class="eddstix-reply-meta">
								<div class="eddstix-reply-user">
									<strong class="eddstix-profilename"><?php echo $user->data->display_name; ?></strong>
								</div>
								<div class="eddstix-reply-time">
									<time class="eddstix-timestamp" datetime="<?php echo get_the_date( 'Y-m-d\TH:i:s' ) . eddstix_get_offset_html5(); ?>">
										<span class="eddstix-human-date"><?php echo get_the_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $post->ID ); ?></span>
										<span class="eddstix-date-ago"><?php printf( __( '%s ago', 'edd-support-tickets' ), $time_ago ); ?></span>
									</time>
								</div>
							</div>

							<?php do_action( 'eddstix_frontend_reply_content_before', get_the_ID() ); ?>

							<div class="eddstix-reply-content"><?php the_content(); ?></div>

							<?php do_action( 'eddstix_frontend_reply_content_after', get_the_ID() ); ?>
						</td>

					</tr>

				<?php endwhile;
			endif;

			wp_reset_query(); ?>
		</tbody>
	</table>
	<h3><?php _e( 'Write a reply', 'edd-support-tickets' ); ?></h3>
	<?php eddstix_get_reply_form(); ?>
</div>