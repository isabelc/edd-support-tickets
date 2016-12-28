<?php
/**
 * Save custom fields.
 *
 * @package 	EDD_Support_Tickets
 * @subpackage 	Custom_Fields
 * @author 		Isabel Castillo
 * @license 	GPL-2.0+
 * @copyright 	Copyright (c) 2015-2016, Isabel Castillo
 */

/**
 * Save all custom fields for the ticket post type.
 */
class EDDSTIX_Save_Fields extends EDDSTIX_Custom_Fields {

	/**
	 * Initialize the class by getting all custom fields
	 * and prefixing options.
	 */
	public function __construct() {
		
	}

	/**
	 * Save the custom fields on back end.
	 *
	 * The following method will get all options,
	 * check if they have a submitted value, prefix and save it to the DB.
	 * 
	 * @param  (integer) $post_id Post ID
	 */
	public function save( $post_id = '' ) {

		/* Need the parent object */
		global $eddstix_cf;

		/**
		 * Clear! We can go ahead now...
		 */
		$options = $eddstix_cf->get_custom_fields();

		/**
		 * Save the possible messages in this array
		 */
		$messages = array();

		/**
		 * Save all notifications to send in this array for a later expedition
		 */
		$notify = array();

		/**
		 * If some of the fields are to be logged in the ticket history, we save it here
		 */
		$log = array();

		/* Go through all our options */
		foreach ( $options as $option ) {

			$option_name = 'eddstix_' . sanitize_text_field( $option['name'] );
			$option_args = $option['args'];

			/* Prepare current value */
			if ( isset( $_POST[$option_name] ) ) {
				$value = ( function_exists( $option_args['sanitize'] ) ) ? $option_args['sanitize']( $_POST[$option_name] ) : sanitize_text_field( $_POST[$option_name] );
			}

			/* Use a custom saving function if the save_callback is defined */
			if ( false !== $option_args['save_callback'] ) {
				if ( is_null( $option_args['save_callback'] ) ) {
					continue;
				}
				// for future use
				if ( function_exists( $option_args['save_callback'] ) ) {
					call_user_func( $option_args['save_callback'], $value, $post_id );
					continue;
				}

			}

			/* Process the agent (re)attribution differently */
			if ( 'assignee' === $option['name'] ) {

				/* Don't do anything if the agent didn't change */
				if ( isset( $_POST[$option_name] ) ) {
					if ( $_POST[$option_name] == get_post_meta( $post_id, '_eddstix_assignee', true ) ) {
						continue;
					}

					$reassign = eddstix_assign_ticket( $post_id, $_POST[$option_name], $option_args['log'] );
				}

				continue;
			}

			/* We handle different option types differently */
			if ( 'taxonomy' != $option_args['callback'] ) :

				/* Form the meta key */
				$key = "_$option_name";

				/* Get current option */
				$current = get_post_meta( $post_id, $key, true );

				/**
				 * First case scenario
				 *
				 * The option exists in DB but there is no value
				 * for it in the POST. This is often the case
				 * for checkboxes.
				 *
				 * Action: Delete option
				 */
				if ( '' != $current && ! isset( $_POST[$option_name] ) ) {

					/* Delete the post meta */
					delete_post_meta( $post_id, $key, $current );

					/* Log the action */
					if ( true === $option_args['log'] ) {
						$log[] = array(
							'action'   => 'deleted',
							'label'    => eddstix_get_field_title( $option ),
							'value'    => $current,
							'field_id' => $option['name']
						);
					}
				}

				/**
				 * Second case scenario
				 *
				 * The option exists in DB and a value has been passed
				 * in the POST.
				 *
				 * Action: Update post meta OR delete it
				 */
				elseif ( '' != $current && isset( $_POST[$option_name] ) ) {

					/* If an actual value is set, we udpate the post meta */
					if ( '' != $value && $current != $value ) {

						update_post_meta( $post_id, $key, $value, $current );

						/* Log the action */
						if ( true === $option_args['log'] ) {
							$log[] = array(
								'action'   => 'updated',
								'label'    => eddstix_get_field_title( $option ),
								'value'    => $value,
								'field_id' => $option['name']
							);
						}
					}

					/**
					 * If the value is empty we delete the post meta in order to keep the DB clean.
					 *
					 * Possible scenario: a text field's value is no longer needed, the user deletes the field's content.
					 * However, the field is still passed in the POST with  an empty value.
					 */
					elseif ( '' == $value ) {
						delete_post_meta( $post_id, $key, $current );

						/* Log the action */
						if ( true === $option_args['log'] ) {
							$log[] = array(
								'action'   => 'deleted',
								'label'    => eddstix_get_field_title( $option ),
								'value'    => $current,
								'field_id' => $option['name']
							);
						}
					}
				}

				/**
				 * Third case scenario
				 *
				 * The option doesn't exist in DB but a value was passed in the POST.
				 *
				 * Action: Add post meta
				 */
				elseif ( '' == $current && isset( $_POST[$option_name] ) ) {

					/* Let's not add an empty value */
					if ( '' != $value ) {

						add_post_meta( $post_id, $key, $value, true );

						/* Log the action */
						if ( true === $option_args['log'] ) {
							$log[] = array(
								'action'   => 'added',
								'label'    => eddstix_get_field_title( $option ),
								'value'    => $value,
								'field_id' => $option['name']
							);
						}

					}
				}

				/**
				 * Fourth case scenario
				 *
				 * The option doesn't exist in DB and there is no value for it in the POST.
				 *
				 * Action: Are you kiddin' me?
				 */
				else {
					// Do nothing
				}

			elseif ( 'taxonomy' == $option_args['callback'] ) :

				/* Make sure this taxonomy is our Product, which has to be handled as a select */
				if ( 'ticket_product' !== $option['name'] )
					continue;

				/* $option['name'] is ticket_product, but $option_name and $_POST['name'] are eddstix_ticket_product */

				$taxonomy = 'ticket_product';

				// Don't do anything if Product is not changed
				if ( ! isset( $_POST[$option_name] ) || empty( $_POST[$option_name] ) ) {
					continue;
				}
 
				/* Get all the terms (for tax=Product) for this ticket (should have only one term) */
				$terms = get_the_terms( $post_id, $taxonomy );

				/**
				 * As the taxonomy is handled like a select, we should have only one value. At least
				 * that's what we want. Hence, we loop through the possible multiple terms (which
				 * shouldn't happen) and only keep the last one.
				 */
				if ( is_array( $terms ) ) {
					foreach ( $terms as $term ) {
						$the_term = $term->term_id;
					}
				} else {
					$the_term = '';
				}

				/* Finally we save the new terms if changed */
				if ( $the_term !== (int) $value ) {

					$term = get_term_by( 'id', (int) $value, $taxonomy );
					if ( false === $term ) {
						continue;
					}

					/**
					 * Apply the get_term filters.
					 */
					$term = get_term( $term, $taxonomy );

					if ( ! is_wp_error( $term ) ) {
						wp_set_object_terms( $post_id, (int) $value, $taxonomy, false );
						/* Log the action */
						if ( true === $option_args['log'] ) {
							$log[] = array(
								'action'   => 'updated',
								'label'    => eddstix_get_field_title( $option ),
								'value'    => $term->name,
								'field_id' => $option['name']
							);
						}
					}
				}

			endif;

			/**
			 * Fired right after the option is updated
			 */
			do_action( "eddstix_cf_updated_$option_name" );

			/**
			 * If a message is associated to this option, we add it now
			 */
			if ( isset( $option_args['message'] ) ) {
				$messages[] = $option_args['message'];
			}

			/**
			 * If an email notification has to be sent we store it temporarily
			 */
			if ( isset( $option_args['notification'] ) ) {
				$notify[] = $option_args['notification'];
			}

		}

		/**
		 * Log the changes
		 */
		if ( ! empty( $log ) ) {
			eddstix_log( $post_id, $log );
		}

	}

	/**
	 * Save all custom fields upon front-end submission
	 *
	 * @return boolean Result of the operation
	 */
	public function save_submission( $post_id ) {
		/* Get all registered custom fields */
		global $eddstix_cf;

		$fields = $eddstix_cf->get_custom_fields();

		foreach ( $fields as $field ) {

			/* Prepare the field name as used in the form */
			$field_name = 'eddstix_' . $field['name'];

			if ( 'taxonomy' !== $field['args']['callback'] ) {

				/* Get the old value from database */
				$old = get_post_meta( $post_id, "_$field_name", true );

				/* If this field was submitted we can start processing it */
				if ( isset( $_POST[$field_name] ) ) {

					/* Begin with sanitizing the field value */
					if ( isset( $field['args']['sanitize'] ) && function_exists( $field['args']['sanitize'] ) ) {
						$value = $field['args']['sanitize']( $_POST[$field_name] );
					} else {
						$value = sanitize_text_field( $_POST[$field_name] );
					}

					/* If this custom field requires a custom processing we delegate the task to a dedicated function. */
					if ( false !== $field['args']['save_callback'] && function_exists( $field['args']['save_callback'] ) ) {
						call_user_func( $field['args']['save_callback'], $field, $post_id );
						continue;
					}

					/**
					 * If the new value is different from the previous one, or if there is no previous value,
					 * then we add/update the custom field value. Otherwise we don't do anything.
					 */
					if ( $old !== $value ) {
						update_post_meta( $post_id, "_$field_name", $value );
					}

				} else {

					/**
					 * If no value is set for this custom field and it exists in the database
					 * we delete it to avoid DB overload.
					 */
					if ( ! empty( $old ) && 'status' !== $field['name'] && 'assignee' !== $field['name'] ) {
						delete_post_meta( $post_id, "_$field_name", $old );
					}

				}

			}

			elseif ( 'taxonomy' === $field['args']['callback'] ) {
				/* If no value is submitted we delete the term relationship */
				if ( isset( $_POST[$field_name] ) && ! empty( $_POST[$field_name] ) ) {
					/* Begin with sanitizing the field value */
					if ( isset( $field['args']['sanitize'] ) && function_exists( $field['args']['sanitize'] ) ) {
						$value = $field['args']['sanitize']( $_POST[$field_name] );
					} else {
						$value = sanitize_text_field( $_POST[$field_name] );
					}

					/* Get all the available terms for this taxonomy */
					$terms   = get_terms( $field['name'], array( 'hide_empty' => false ) );
					$term_id = false;

					/* If no terms are registered for this taxonomy or the taxonomy doesn't exist we can't save a value */
					if ( ! is_array( $terms ) || empty( $terms ) ) {
						continue;
					}

					foreach ( $terms as $term ) {
						if ( (int) $term->term_id === (int) $value ) {
							$term_id = (int) $term->term_id;
						}
					}

					/* If the submitted value doesn't match an existing term for this taxonomy we don't save the value */
					if ( false === $term_id ) {
						continue;
					}
					wp_set_object_terms( $post_id, (int) $value, $field['name'], false );
				}
			}
		}
	}

	/**
	 * Checks required custom fields.
	 *
	 * This function is hooked on the filter eddstix_before_submit_new_ticket_checks
	 * through the parent class. It checks all required custom fields
	 * and if they were correctly filled. If one or more required field(s) is/are
	 * missing then the submission process is stopped and an error message is returned.
	 *
	 * @return mixed True if no error or a WP_Error otherwise
	 */
	public function check_required_fields() {

		/* Get all registered custom fields */
		global $eddstix_cf;

		$fields = $eddstix_cf->get_custom_fields();

		/* Set the result as true by default, which is the "green light" value */
		$result = false;

		foreach ( $fields as $field ) {

			/* Prepare the field name as used in the form */
			$field_name = 'eddstix_' . $field['name'];

			if ( true === $field['args']['required'] && false === $field['args']['core'] ) {

				if ( ! isset( $_POST[$field_name] ) || empty( $_POST[$field_name] ) ) {

					/* Get field title */
					$title = ! empty( $field['args']['title'] ) ? $field['args']['title'] : eddstix_get_title_from_id( $field['name'] );

					/* Add the error message for this field. */
					if ( ! is_object( $result ) ) {
						$result = new WP_Error( 'required_field_missing', sprintf( __( 'The field %s is required.', 'edd-support-tickets' ), "<code>$title</code>", array( 'errors' => $field_name ) ) );
					} else {
						$result->add( 'required_field_missing', sprintf( __( 'The field %s is required.', 'edd-support-tickets' ), "<code>$title</code>", array( 'errors' => $field_name ) ) );
					}

					/* Set the field as incorrect. */
					if ( ! isset( $_SESSION['eddstix_submission_error'] ) ) {
						$_SESSION['eddstix_submission_error'] = array();
					}

					if ( ! in_array( $field_name, $_SESSION['eddstix_submission_error'] ) ) {
						array_push( $_SESSION['eddstix_submission_error'], $field_name );
					}

				}
			}

		}

		return $result;

	}
	
}