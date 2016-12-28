<div id="eddstix-ticket-message" class="eddstix-ticket-content">
	<?php
	do_action( 'eddstix_backend_ticket_content_before', $post->ID, $post );

	echo apply_filters( 'the_content', $post->post_content );

	do_action( 'eddstix_backend_ticket_content_after', $post->ID, $post );
	?>
</div>