<?php
/**
 * Cleanup
 *
 * The Cleanup tools are a set of functions that helps accomplish some of the more technical
 * operations on the plugin data.
 *
 * The functions are triggered by a URL parameter and the trigger is pulled from the
 * system_tools method within the EDD_Support_Tickets_Admin class. Those functions must be 
 * triggered early so that we can safely redirect to "read only" pages after the function was
 * executed.
 */

/**
 * Build the link that triggers a specific tool.
 *
 * @param  string $tool Tool to trigger
 * @param  array  $args Arbitrary arguments
 * @return string       URL that triggers the tool function
 */
function eddstix_tool_link( $tool, $args = array() ) {

	$args['tool']   = $tool;
	$args['_nonce'] = wp_create_nonce( 'system_tool' );

	return esc_url( add_query_arg( $args, admin_url( 'edit.php' ) ) );
}

if ( isset( $_GET['done'] ) ) {

	switch( $_GET['done'] ) {

		case 'tickets_metas':
			$message = __( 'Tickets transients were cleared', 'edd-support-tickets' );
			break;
		case 'resync_products':
			$message = __( 'All products have been re-synchronized', 'edd-support-tickets' );
			break;
	}

}

if ( isset( $message ) ) {
	echo "<div class='updated below-h2'><p>$message</p></div>";
}
?>
<p><?php _e( 'These tool are intended for advanced users. Be aware that some of these tools can definitively erase data.', 'edd-support-tickets' ); ?></p>
<table class="widefat eddstix-system-tools-table" id="eddstix-system-tools">
	<thead>
		<tr>
			<th data-override="key" class="row-title"><?php _e( 'Tools', 'edd-support-tickets' ); ?></th>
			<th data-override="value"></th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td class="row-title"><label for="tablecell"><?php _e( 'Tickets Transient Meta', 'edd-support-tickets' ); ?></label></td>
			<td>
				<a href="<?php echo eddstix_tool_link( 'tickets_metas' ); ?>" class="button-secondary"><?php _e( 'Clear', 'edd-support-tickets' ); ?></a> 
				<span class="eddstix-system-tools-desc"><?php _e( 'Clear all transients for all tickets.', 'edd-support-tickets' ); ?></span>
			</td>
		</tr>
		<?php do_action( 'eddstix_system_tools_table_after' ); ?>
	</tbody>
</table>