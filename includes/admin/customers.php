<?php
/**
 * Add Support Tickets to the EDD Customer Interface
*/

if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Add the Support Tickets tab to the customer interface if the customer has tickets
 *
 * @param  array $tabs The tabs currently added to the customer view
 * @return array       Updated tabs array
 */
function eddstix_customer_tab( $tabs ) {

	global $eddstix_tickets;
	if ( $eddstix_tickets ) {
		$tabs['support_tickets'] = array( 'dashicon' => 'dashicons-tickets-alt', 'title' => __( 'Support Tickets', 'edd-support-tickets' ) );
	}
	return $tabs;
}
add_filter( 'edd_customer_tabs', 'eddstix_customer_tab' );

/**
 * Register the Tickets view for the customer interface
 *
 * @param  array $tabs The tabs currently added to the customer views
 * @return array       Updated tabs array
 */
function eddstix_customer_view( $views ) {
	// globalize eddstix_tickets
	global $eddstix_tickets;
	$customer_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : false;
	if ( $customer_id ) {
		$customer = new EDD_Customer( $customer_id );
		$eddstix_tickets    = get_posts( array(
			'author'			=> $customer->user_id,
			'post_type'			=> 'edd_ticket',
			'post_status'            => 'any',		
			'posts_per_page'	=> 100,
			'orderby'   => 'meta_value',
			'meta_key' => '_last_activity'
		) );
		if ( $eddstix_tickets ) {
			$views['support_tickets'] = 'eddstix_customer_view_display';
		}
	}
	return $views;
}
add_filter( 'edd_customer_views', 'eddstix_customer_view' );

/**
 * Display the Support Tickets area for the customer view
 *
 * @param  object $customer The Customer being displayed
 * @return void
 */
function eddstix_customer_view_display( $customer ) {
	?>
	<div class="customer-notes-header">
		<?php echo get_avatar( $customer->email, 30 ); ?> <span><?php echo $customer->name; ?></span>
	</div>
	<?php
	eddstix_customer_all_tickets_table();
}

/**
 * Dislplay a table of all tickets for one customer on the back end.
 */
function eddstix_customer_all_tickets_table() {
	global $eddstix_tickets;
	$columns = eddstix_get_tickets_list_columns();
	
	if ( is_array( $eddstix_tickets ) ) : ?>
	<div id="customer-tables-wrapper" class="customer-section">
		<h3><?php _e( 'Support Tickets', 'edd-support-tickets' ); ?></h3>
		<table class="wp-list-table widefat striped downloads">
			<thead>
				<tr>
				<?php foreach ( $columns as $column_id => $column ) {
					echo "<th id='eddstix-ticket-$column_id'>" . $column['title'] . "</th>";
				} ?>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! empty( $eddstix_tickets ) ) :
					foreach ( $eddstix_tickets as $ticket ) :
						$ticket_id = $ticket->ID;
						$status = eddstix_get_custom_ticket_statuses();
						?>
						<tr>
							<td data-colname="Status"><?php echo $status[ $ticket->post_status ]; ?></td>
							<td data-colname="Title"><a href="<?php echo esc_url( admin_url( 'post.php?action=edit&post=' . $ticket_id ) ); ?>"><?php echo $ticket->post_title; ?></a></td>
							<td data-colname="Date"><?php echo get_the_date( get_option( 'date_format' ), $ticket_id ); ?></td>
							<td data-colname="Product"><?php $terms = get_the_terms( $ticket_id, 'ticket_product' );
								if ( empty( $terms ) ) {
									echo ' &nbsp; ';
									continue;
								}
								$download = isset( $terms[0]->slug ) ? edd_get_download( $terms[0]->slug ) : '';
								echo isset( $download->post_title ) ? $download->post_title : ' &nbsp;'; ?></td>
						</tr>
					<?php endforeach;
				else: ?>
					<tr><td colspan="2"><?php _e( 'No support tickets found', 'edd-support-tickets' ); ?></td></tr>
				<?php endif; ?>
			</tbody>
		</table>

	</div>
	<?php endif;	
}