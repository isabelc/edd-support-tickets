<?php
/**
 * The template to show the front-end My Tickets list.
 * If you need to customize this template, copy it to your 
 * theme/edd-support-tickets/ and then modify it.
 */

global $eddstix_tickets;

if ( $eddstix_tickets->have_posts() ):

	$columns = eddstix_get_tickets_list_columns();
	do_action( 'eddstix_tickets_list_before' );
	?>

	<div class="eddstix eddstix-ticket-list">
		<table id="eddstix_ticketlist" class="eddstix-table eddstix-table-hover">
			<thead>
				<tr>
					<?php foreach ( $columns as $column_id => $column ) {
						echo "<th id='eddstix-ticket-$column_id'>" . $column['title'] . "</th>";
					} ?>
				</tr>
			</thead>
			<tbody>
				<?php
				while( $eddstix_tickets->have_posts() ):

					$eddstix_tickets->the_post();

					echo '<tr>';

					foreach ( $columns as $column_id => $column ) {

						echo '<td>';

						eddstix_get_tickets_list_column_content( $column_id, $column );

						echo '</td>';

					}

					echo '</tr>';
				
				endwhile;

				wp_reset_query(); ?>
			</tbody>
		</table>
		<?php do_action( 'eddstix_tickets_list_after' ); ?>
	</div>
<?php else :
	eddstix_notification( 'info', sprintf( __( 'You haven\'t submitted a ticket yet. <a href="%s">Click here to submit your first ticket</a>.', 'edd-support-tickets' ), esc_url( get_permalink( eddstix_get_option( 'ticket_submit' ) ) ) ) );
endif; ?>