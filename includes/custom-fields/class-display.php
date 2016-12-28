<?php
/**
 * Display custom fields.
 *
 * @package 	EDD_Support_Tickets
 * @subpackage 	Custom_Fields
 * @author 		Isabel Castillo
 * @license 	GPL-2.0+
 * @copyright 	Copyright (c) 2015-2016, Isabel Castillo
 */
class EDDSTIX_Custom_Fields_Display extends EDDSTIX_Custom_Fields {

	/**
	 * Get all the registered custom fields and display them
	 * on the ticket submission form on the front-end.
	 */
	public static function submission_form_fields() {

		/* Get all the registered fields from the $eddstix_cf object */
		global $eddstix_cf;

		$fields = $eddstix_cf->get_custom_fields();

		if ( ! empty( $fields ) ) {
			foreach ( $fields as $name => $field ) {

				/* Do not display core fields */
				if ( true === $field['args']['core'] ) {
					continue;
				}

				$title    = ! empty( $field['args']['title'] ) ? $field['args']['title'] : eddstix_get_title_from_id( $name );
				$callback = ! empty( $field['args']['callback'] ) ? $field['args']['callback'] : 'text';

				/* Check for a custom function */
				if ( function_exists( $callback ) ) {
					call_user_func( $callback, $field );
				}

				/* Check for a matching method in the custom fields display class */
				elseif ( method_exists( 'EDDSTIX_Custom_Fields_Display', $callback ) ) {
					call_user_func( array( 'EDDSTIX_Custom_Fields_Display', $callback ), $field );
				}

				/* Fallback on a standard text field */
				else {
					EDDSTIX_Custom_Fields_Display::text( $field );
				}
			}
		}
	}
	
	/**
	 * Text field.
	 */
	public static function text( $field ) {

		if ( isset( $post ) ) {
			$post_id = $post->ID;
		} elseif ( isset( $_GET['post'] ) ) {
			$post_id = intval( $_GET['post'] );
		} else {
			$post_id = false;
		}

		$field_id    = 'eddstix_' . $field['name'];
		$value       = eddstix_get_cf_value( $field_id, $post_id );
		$label       = eddstix_get_field_title( $field );
		$field_class = isset( $field['args']['field_class'] ) ? $field['args']['field_class'] : ''; ?>

		<div <?php eddstix_get_field_container_class( $field_id ); ?> id="<?php echo $field_id; ?>_container">
			
			<label for="<?php echo $field_id; ?>"><strong><?php echo $label; ?></strong></label>

			<?php if ( ! is_admin() || current_user_can( $field['args']['capability'] ) ): ?>
				<input type="text" id="<?php echo $field_id; ?>" <?php eddstix_get_field_class( $field_id, $field_class ); ?> name="<?php echo $field_id; ?>" value="<?php echo $value; ?>" <?php if ( $field['args']['placeholder'] !== '' ): ?>placeholder="<?php echo $field['args']['placeholder'];?>"<?php endif; ?> <?php if ( true === $field['args']['required'] ): ?>required<?php endif; ?>>
			<?php else: ?>
				<p id="<?php echo $field_id; ?>"><?php echo $value; ?></p>
			<?php endif;

			if ( isset( $field['args']['desc'] ) && '' != $field['args']['desc'] ) : ?><p class="<?php echo is_admin() ? 'description' : 'eddstix-help-block'; ?>"><?php echo esc_attr( $field['args']['desc'] ); ?></p><?php endif; ?>
		</div>

	<?php }

	/**
	 * URL field.
	 */
	public static function url( $field ) {

		if ( isset( $post ) ) {
			$post_id = $post->ID;
		} elseif ( isset( $_GET['post'] ) ) {
			$post_id = intval( $_GET['post'] );
		} else {
			$post_id = false;
		}

		$field_id    = 'eddstix_' . $field['name'];
		$value       = eddstix_get_cf_value( $field_id, $post_id );
		$label       = eddstix_get_field_title( $field );
		$field_class = isset( $field['args']['field_class'] ) ? $field['args']['field_class'] : ''; ?>

		<div <?php eddstix_get_field_container_class( $field_id ); ?> id="<?php echo $field_id; ?>_container">
			
			<label for="<?php echo $field_id; ?>"><strong><?php echo $label; ?></strong></label>
			<?php if ( ! is_admin() || current_user_can( $field['args']['capability'] ) ): ?>
				<input type="url" id="<?php echo $field_id; ?>" <?php eddstix_get_field_class( $field_id, $field_class ); ?> name="<?php echo $field_id; ?>" value="<?php echo $value; ?>" <?php if ( $field['args']['placeholder'] !== '' ): ?>placeholder="<?php echo $field['args']['placeholder'];?>"<?php endif; ?> <?php if ( true === $field['args']['required'] ): ?>required<?php endif; ?>>
			<?php else: ?>
				<p id="<?php echo $field_id; ?>"><?php echo $value; ?></p>
			<?php endif;

			if ( isset( $field['args']['desc'] ) && '' != $field['args']['desc'] ) : ?><p class="<?php echo is_admin() ? 'description' : 'eddstix-help-block'; ?>"><?php echo esc_attr( $field['args']['desc'] ); ?></p><?php endif; ?>
		</div>

	<?php }

	/**
	 * Textarea field.
	 */
	public static function textarea( $field ) {

		if ( isset( $post ) ) {
			$post_id = $post->ID;
		} elseif ( isset( $_GET['post'] ) ) {
			$post_id = intval( $_GET['post'] );
		} else {
			$post_id = false;
		}

		$field_id    = 'eddstix_' . $field['name'];
		$value       = eddstix_get_cf_value( $field_id, $post_id );
		$label       = eddstix_get_field_title( $field );
		$field_class = isset( $field['args']['field_class'] ) ? $field['args']['field_class'] : ''; ?>

		<div <?php eddstix_get_field_container_class( $field_id ); ?> id="<?php echo $field_id; ?>_container">
			
			<label for="<?php echo $field_id; ?>"><strong><?php echo $label; ?></strong></label>

			<?php if ( ! is_admin() || current_user_can( $field['args']['capability'] ) ): ?>
				<textarea id="<?php echo $field_id; ?>" <?php eddstix_get_field_class( $field_id, $field_class ); ?> name="<?php echo $field_id; ?>" <?php if ( $field['args']['placeholder'] !== '' ): ?>placeholder="<?php echo $field['args']['placeholder'];?>"<?php endif; ?> <?php if ( true === $field['args']['required'] ): ?>required<?php endif; ?>><?php echo $value; ?></textarea>
			<?php else: ?>
				<p id="<?php echo $field_id; ?>"><?php echo $value; ?></p>
			<?php endif;

			if ( isset( $field['args']['desc'] ) && '' != $field['args']['desc'] ) : ?><p class="<?php echo is_admin() ? 'description' : 'eddstix-help-block'; ?>"><?php echo esc_attr( $field['args']['desc'] ); ?></p><?php endif; ?>
		</div>

	<?php }

	/**
	 * "Fake" taxonomy select for Products
	 * 
	 * @param  array $field Field options
	 */
	public static function taxonomy( $field ) {
		global $post, $pagenow;
		$field_name = sanitize_text_field( $field['name'] );
		$field_id 	= 'eddstix_' . $field_name;
		$label 		= eddstix_get_field_title( $field );
		$current = get_the_terms( $post->ID, $field_name );
		$args 	= array( 'hide_empty' => 0 );

		// Only on front end for customers, limit product list to owned or licensed products
		if ( ! is_admin() && 'ticket_product' == $field_name ) {
			$include = eddstix_has_clearance();

			if ( is_array( $include ) && ! empty( $include[0] ) ) {
				$args['include'] = $include;
			} else {
				return;
			}
		}

		$terms         = get_terms( $field_name, $args );
		$value         = '';
		$ordered_terms = array();

		if ( is_array( $current ) ) {
			foreach ( $current as $term ) {
				$value = $term->term_id;
			}

		}

		// In case the taxonomy does not exist
		if ( is_wp_error( $terms ) || ( ! $terms ) ) {
			return;
		}

		if ( is_admin() ) {
		
			// Management can always edit ticket_product.
			// Allow Agents/workers to edit ticket_product if they are creating a new ticket in the back-end

			if ( current_user_can( 'manage_edd_ticket_settings' ) ||
				( current_user_can( 'edit_edd_support_tickets' ) && 'post-new.php' == $pagenow )
				) {
					$disabled = false;
			} else {
					$disabled = true;
			}

		} else {
			$disabled = false;
		}

		/**
		 * Re-order the terms hierarchically.
		 */
		eddstix_sort_terms_hierarchicaly( $terms, $ordered_terms );
		?>

		<div <?php eddstix_get_field_container_class( $field_id ); ?> id="<?php echo $field_id; ?>_container">
			<label for="<?php echo $field_id; ?>"><strong><?php echo $label; ?></strong></label>

			<?php if ( ! is_admin() || current_user_can( $field['args']['capability'] ) ) : ?>
				<select name="<?php echo $field_id; ?>" id="<?php echo $field_id; ?>" <?php eddstix_get_field_class( $field_id, 'eddstix-select2' ); if ( true === $disabled ) { echo ' disabled'; } if ( true === $field['args']['required'] ) { echo ' required'; } ?>>

					<?php if ( $value ) {
						$ordered_terms = eddstix_sort_terms_by_selected( $ordered_terms, $value );
					} else {


						?><option value=""><?php _e( 'Please select', 'edd-support-tickets' ); ?></option><?php	
					}

					foreach ( $ordered_terms as $term ) {
						eddstix_hierarchical_taxonomy_dropdown_options( $term, $value );
					} ?>

				</select>

			<?php else : ?>
				<p id="<?php echo $field_id; ?>"><?php echo $value; ?></p>
			<?php endif;

			if ( isset( $field['args']['desc'] ) && '' != $field['args']['desc'] ) : ?><p class="<?php echo is_admin() ? 'description' : 'eddstix-help-block'; ?>"><?php echo esc_attr( $field['args']['desc'] ); ?></p><?php endif; ?>
		</div>
	<?php 
	}

}

/**
 * Display the post status.
 *
 * Gets the ticket status.
 *
 * @param string $name field name
 * @param integer $post_id ID of the post being processed
 * @return string Formatted ticket status
 */
function eddstix_cf_display_status( $name, $post_id ) {

	$post_status = get_post_status( $post_id );
	$custom_status = eddstix_get_custom_ticket_statuses();

	if ( ! array_key_exists( $post_status, $custom_status ) ) {
		$label  = __( 'Open', 'edd-support-tickets' );
		$color  = apply_filters( 'eddstix_default_label_color', '#169baa' );
		$tag    = "<span class='eddstix-label' style='background-color:$color;'>$label</span>";
	} else {
		$defaults = array(
				'ticket_queued'			=> apply_filters( 'eddstix_new_label_color', '#9b59b6' ),
				'ticket_processing'		=> apply_filters( 'eddstix_in_progress_label_color', '#26a65b' ),
				'ticket_hold'			=> apply_filters( 'eddstix_hold_label_color', '#daa479' ),
				'ticket_status_closed'	=> apply_filters( 'eddstix_closed_label_color', '#dd3333' )
		);
		$label = $custom_status[$post_status];

		if ( isset( $defaults[$post_status] ) ) {
			$color = $defaults[$post_status];
		} else {
			$color = apply_filters( 'eddstix_default_label_color', '#169baa' );
		}

		$tag = "<span class='eddstix-label' style='background-color:$color;'>$label</span>";
	}
	echo $tag;

}
function eddstix_sort_terms_by_selected( $ordered_terms, $value ) {
	$selected = '';
	foreach ( $ordered_terms as $key => $val ) {
		if ( $val->term_id == $value ) {
			$selected = $key;
		}
	}

	if ( $selected ) {
		$out = array( $selected => $ordered_terms[ $selected ] ) + $ordered_terms;
	} else {
		$out = $ordered_terms;
	}

	return $out;
}

/**
 * Recursively sort an array of taxonomy terms hierarchically. Child categories will be
 * placed under a 'children' member of their parent term.
 *
 * Hierarchical support is here to allow users to add custom taxonomies as custom fields.
 *
 * @param Array   $cats     taxonomy term objects to sort
 * @param Array   $into     result array to put them in
 * @param integer $parentId the current parent ID to put them in
 * @link  http://wordpress.stackexchange.com/a/99516/16176
 */
function eddstix_sort_terms_hierarchicaly( Array &$cats, Array &$into, $parentId = 0 ) {

	foreach ($cats as $i => $cat) {
		if ($cat->parent == $parentId) {
			$into[$cat->term_id] = $cat;
			unset($cats[$i]);
		}
	}

	foreach ($into as $topCat) {
		$topCat->children = array();
		eddstix_sort_terms_hierarchicaly( $cats, $topCat->children, $topCat->term_id );
	}
}

/**
 * Recursively displays hierarchical options into a select dropdown.
 *
 * Also works for non-heirarchical options (our Ticket Products). 
 * Hierarchical support is here to allow users to add custom taxonomies as custom fields.
 *
 * @param  object $term  The term to display
 * @param  string $value The value to compare against
 * @return void
 */
function eddstix_hierarchical_taxonomy_dropdown_options( $term, $value, $level = 1 ) {

	$option = '';

	/* Add a visual indication that this is a child term */
	if ( 1 !== $level ) {
		for ( $i = 1; $i < ( $level - 1 ); $i++ ) {
			$option .= '&nbsp;&nbsp;&nbsp;&nbsp;';
		}
		$option .= '&angrt; ';
	}
	$option .= $term->name;
	?><option value="<?php echo $term->term_id; ?>" <?php if ( (int) $value === $term->term_id || $value == $term->slug  ) { echo 'selected="selected"'; } ?>><?php echo $option; ?></option>

	<?php if ( isset( $term->children ) && ! empty( $term->children ) ) {
		++$level;
		foreach ( $term->children as $child ) {
			eddstix_hierarchical_taxonomy_dropdown_options( $child, $value, $level );
		}
	}

}
