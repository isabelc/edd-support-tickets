<div class="wrap">
	<h2 class="nav-tab-wrapper">
		<a href="<?php echo esc_url( add_query_arg( array( 'post_type' => 'edd_ticket', 'page' => 'eddstix-tools', 'tab' => 'sysinfo' ), admin_url( 'edit.php' ) ) ); ?>" class="nav-tab <?php if ( ! isset( $_GET['tab'] ) || 'sysinfo' === $_GET['tab'] ): ?> nav-tab-active<?php endif; ?>"><?php _e( 'System Info', 'edd-support-tickets' ); ?></a>
		<a href="<?php echo esc_url( add_query_arg( array( 'post_type' => 'edd_ticket', 'page' => 'eddstix-tools', 'tab' => 'cleanup' ), admin_url( 'edit.php' ) ) ); ?>" class="nav-tab <?php if ( isset( $_GET['tab'] ) && 'cleanup' === $_GET['tab'] ): ?> nav-tab-active<?php endif; ?>"><?php _e( 'Cleanup', 'edd-support-tickets' ); ?></a>
	</h2><?php
	if ( ! isset( $_GET['tab'] ) ) {
		require_once EDDSTIX_PATH . 'includes/admin/tools/system-info.php';
	} else {
		switch( $_GET['tab'] ) {
			case 'cleanup':
				require_once EDDSTIX_PATH . 'includes/admin/tools/cleanup.php';
			break;

			default:
				require_once EDDSTIX_PATH . 'includes/admin/tools/system-info.php';
		}
	}
	?>
</div>