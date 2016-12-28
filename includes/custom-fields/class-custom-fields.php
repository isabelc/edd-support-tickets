<?php
/**
 * EDD Support Tickets
 *
 * @package 	EDD_Support_Tickets
 * @subpackage 	Custom_Fields
 * @author 		Isabel Castillo
 * @license 	GPL-2.0+
 * @copyright 	Copyright (c) 2015-2016, Isabel Castillo
 */

class EDDSTIX_Custom_Fields {

	public function __construct() {

		/* Load custom fields dependencies */
		require_once EDDSTIX_PATH . 'includes/custom-fields/class-save.php';
		require_once EDDSTIX_PATH . 'includes/custom-fields/class-display.php';

		/**
		 * Array where all custom fields will be stored.
		 */
		$this->options = array();

		/**
		 * Register the taxonomies
		 */
		add_action( 'init', array( $this, 'register_taxonomies' ) );

		/**
		 * Instantiate the class that handles saving the custom fields.
		 */
		$eddstix_save = new EDDSTIX_Save_Fields();

		if ( is_admin() ) {

			/**
			 * Add custom columns
			 */
			add_action( 'manage_edd_ticket_posts_columns', array( $this, 'add_custom_column' ), 10, 1 );
			add_action( 'manage_edd_ticket_posts_columns', array( $this, 'move_status_first' ), 15, 1 );
			add_action( 'manage_edd_ticket_posts_custom_column', array( $this, 'custom_columns_content' ), 10, 2 );

			/**
			 * Add the taxonomies filters
			 */
			add_action( 'restrict_manage_posts', array( $this, 'custom_taxonomy_filter' ), 10, 0 );
			add_filter( 'parse_query', array( $this, 'custom_taxonomy_filter_convert_id_term' ), 10, 1 );
		} else {

			/* Now we can instantiate the save class and save */
			if ( isset( $_POST['eddstix_title'] ) && isset( $_POST['eddstix_message'] ) ) {

				/* Check for required fields and possibly block the submission. */
				add_filter( 'eddstix_before_submit_new_ticket_checks', array( $eddstix_save, 'check_required_fields' ) );

				/* Save the custom fields. */
				add_action( 'eddstix_open_ticket_after', array( $eddstix_save, 'save_submission' ), 10, 2 );
				
			}

			/* Display the custom fields on the submission form */
			add_action( 'eddstix_submission_form_inside_after_subject', array( 'EDDSTIX_Custom_Fields_Display', 'submission_form_fields' ) );
		}
		
	}

	/**
	 * Add a new custom field to the ticket.
	 * 
	 * @param (string) $name Option name
	 */
	public function add_field( $name = false, $args = array() ) {

		/* Option name is mandatory */
		if ( ! $name ) {
			return;
		}

		$name = sanitize_text_field( $name );

		/* Default arguments */
		$defaults = array(
			'callback'              => 'text', // Field callback to display its content
			'core'                  => false, // Is this a custom fields that belongs to the plugin core
			'required'              => false, // Is this field required for front-end submission
			'log'                   => false, // Should the content updates of this field be logged in the system
			'capability'            => 'publish_edd_support_tickets',// Required capability for this field
			'sanitize'              => 'sanitize_text_field', // Sanitize callback for the field value
			'save_callback'         => false, // Saving callback if a specific saving method is required
			'show_column'           => false, // Show field content in the tickets list & in the admin
			'column_callback'       => 'eddstix_cf_value', // Column callback function
			'sortable_column'       => false, // Not compatible with taxonomies
			'filterable'            => true, // Used for taxonomies only
			'title'                 => '', // Nicely formatted title for this field
			'placeholder'           => '', // Placeholder to display in the submission form
			'desc'                  => '', // Helper description for the field
			/* The following parameters are users for taxonomies only. */
			'label'                 => '',
			'label_plural'          => '',
			'taxo_hierarchical'     => true,
			'update_count_callback' => 'eddstix_update_ticket_tag_terms_count',
		);

		/* Merge args */
		$arguments = wp_parse_args( $args, $defaults );

		/* Field with args */
		$option = array( 'name' => $name, 'args' => $arguments );

		$this->options[$name] = apply_filters( 'eddstix_add_field', $option );

	}

	public function relate_ticket_to_ticket_tag() {
		register_taxonomy_for_object_type( 'ticket_tag', 'edd_ticket' );
	}
	public function relate_ticket_to_ticket_product() {
		register_taxonomy_for_object_type( 'ticket_product', 'edd_ticket' );
	}

	/**
	 * Register all custom taxonomies.
	 */
	public function register_taxonomies() {

		$options         = $this->options;
		foreach( $options as $option ) {

			/* Reset vars for safety */
			$labels = array();
			$args   = array();
			$name   = '';
			$plural = '';

			if ( 'taxonomy' == $option['args']['callback'] ) {

				$option_name = $option['name'];

				$name         = ! empty( $option['args']['label'] ) ? sanitize_text_field( $option['args']['label'] ) : ucwords( str_replace( array( '_', '-' ), ' ', $option_name ) );
				$plural       = ! empty( $option['args']['label_plural'] ) ? sanitize_text_field( $option['args']['label_plural'] ) : $name . 's';
				$labels = array(
					'name'              => $plural,
					'singular_name'     => $name,
					'search_items'      => sprintf( __( 'Search %s', 'edd-support-tickets' ), $plural ),
					'all_items'         => sprintf( __( 'All %s', 'edd-support-tickets' ), $plural ),
					'parent_item'       => sprintf( __( 'Parent %s', 'edd-support-tickets' ), $name ),
					'parent_item_colon' => sprintf( _x( 'Parent %s:', 'Parent term in a taxonomy where %s is dynamically replaced by the taxonomy (eg. "book")', 'edd-support-tickets' ), $name ),
					'edit_item'         => sprintf( __( 'Edit %s', 'edd-support-tickets' ), $name ),
					'update_item'       => sprintf( __( 'Update %s', 'edd-support-tickets' ), $name ),
					'add_new_item'      => sprintf( __( 'Add New %s', 'edd-support-tickets' ), $name ),
					'new_item_name'     => sprintf( _x( 'New %s Name', 'A new taxonomy term name where %s is dynamically replaced by the taxonomy (eg. "book")', 'edd-support-tickets' ), $name ),
					'menu_name'         => $plural,
				);

				$args = array(
					'hierarchical'			=> $option['args']['taxo_hierarchical'],
					'labels'				=> $labels,
					'show_ui'				=> isset( $option['args']['show_ui'] ) ? $option['args']['show_ui'] : true,
					'show_admin_column'		=> true,
					'show_in_nav_menus'		=> false,
					'query_var'				=> true,
					'capabilities'			=> array(
						'manage_terms' => 'manage_edd_support_ticket_terms',
						'edit_terms'   => 'edit_edd_support_ticket_terms',
						'delete_terms' => 'delete_edd_support_ticket_terms',
						'assign_terms' => 'assign_edd_support_ticket_terms'
					)
				);

				if ( false !== $option['args']['update_count_callback'] && function_exists( $option['args']['update_count_callback'] ) ) {
					$args['update_count_callback'] = $option['args']['update_count_callback'];
				}

				if ( isset( $option['args']['meta_box_cb'] ) ) {
					$args['meta_box_cb'] = $option['args']['meta_box_cb'];
				}

				register_taxonomy( $option_name, array( 'edd_ticket' ), $args );

				if ( in_array( $option_name, array( 'ticket_tag', 'ticket_product' ) ) ) {
					add_action( 'init', array( $this, "relate_ticket_to_$option_name" ), 200 );	
				}
			}
		}
	}

	/**
	 * Return the list of fields
	 * 
	 * @return (array) List of custom fields
	 */
	public function get_custom_fields() {
		return apply_filters( 'eddstix_get_custom_fields', $this->options );
	}

	/**
	 * Retrieve post meta value.
	 * 
	 * @param  (string)   $name    Option name
	 * @param  (integer)  $post_id Post ID
	 * @param  (mixed)    $default Default value
	 * @return (mixed)             Meta value
	 */
	public static function get_value( $name, $post_id, $default = false, $echo = false ) {

		if ( '_' !== substr( $name, 0, 1 ) ) {
			if ( 'eddstix' === substr( $name, 0, 7 ) ) {
				$name = "_$name";
			} else {
				$name = "_eddstix_$name";
			}
		} else {
			if ( '_eddstix' !== substr( $name, 0, 8) ) {
				$name = "_eddstix$name";
			}
		}

		/* Get option */
		$value = get_post_meta( $post_id, $name, true );

		/* Return value */
		if ( '' === $value ) {
			$value = $default;
		}

		if ( true === $echo ) {
			echo $value;
		} else {
			return $value;
		}

	}

	/**
	 * Add Status and custom columns to tickets list.
	 * 
	 * @param  array $columns List of default columns
	 * @return array          Updated list of columns
	 */
	public function add_custom_column( $columns ) {
		$new    = array();
		$custom = array();
		$fields = $this->get_custom_fields();

		/**
		 * Prepare all custom fields that are supposed to show up
		 * in the admin columns.
		 */
		foreach ( $fields as $field ) {

			/* If CF is a regular taxonomy we don't handle it, WordPress does */
			if ( 'taxonomy' == $field['args']['callback'] ) {
				continue;
			}

			if ( true === $field['args']['show_column'] ) {
				$id          = $field['name'];
				$title       = eddstix_get_field_title( $field );
				$custom[$id] = $title;
			}

		}

		/**
		 * Parse the old columns and add the new ones.
		 */
		foreach( $columns as $col_id => $col_label ) {

			/* Merge all custom columns right before the date column */
			if ( 'date' == $col_id ) {
				$new = array_merge( $new, $custom );
			}

			$new[$col_id] = $col_label;

		}
		return $new;
	}

	/**
	 * Move Status admin columns to first position after checkbox
	 *
	 * @param  array $columns List of admin columns
	 * @return array Re-ordered list
	 */
	public function move_status_first( $columns ) {

		if ( isset( $columns['status'] ) ) {
			$status_content = $columns['status'];
			unset( $columns['status'] );
		} else {
			return $columns;
		}

		$new = array();

		foreach ( $columns as $column => $content ) {

			if ( 'title' === $column ) {
				$new['status'] = $status_content;
			}

			$new[$column] = $content;

		}
		return $new;
	}

	/**
	 * Manage back-end custom columns content, mainly to route the status column
	 * and any user-added custom fields to their callback function.
	 * 
	 * @param  array   $column  Columns currently processed
	 * @param  integer $post_id ID of the post being processed
	 */
	public function custom_columns_content( $column, $post_id ) {

		$fields = $this->get_custom_fields();

		if ( isset( $fields[$column] ) ) {

			if ( true === $fields[$column]['args']['show_column'] ) {

				/* In case a custom callback is specified we use it */
				if ( function_exists( $fields[$column]['args']['column_callback'] ) ) {
					call_user_func( $fields[$column]['args']['column_callback'], $fields[$column]['name'], $post_id );
				}
				/* Otherwise we use the default rendering options */
				else {
					eddstix_cf_value( $fields[$column]['name'], $post_id );
				}

			}

		}

	}

	/**
	 * Add the Product and tag filters to the ticket list.
	 *
	 */
	public function custom_taxonomy_filter() {

		global $typenow;

		if ( 'edd_ticket' != $typenow ) {
			return;
		}
		$post_types = get_post_types( array( '_builtin' => false ) );

		if ( in_array( $typenow, $post_types ) ) {

			$filters = get_object_taxonomies( $typenow );

			/* Get all custom fields */
			$fields = $this->get_custom_fields();

			foreach ( $filters as $tax_slug ) {

				if ( ! array_key_exists( $tax_slug, $fields ) ) {
					continue;
				}

				if ( true !== $fields[$tax_slug]['args']['filterable'] ) {
					continue;
				}

				$tax_obj = get_taxonomy( $tax_slug );

				$args = array(
					'show_option_all' => __( 'Show All ' . $tax_obj->label ),
					'taxonomy'        => $tax_slug,
					'name'            => $tax_obj->name,
					'orderby'         => 'name',
					'hierarchical'    => $tax_obj->hierarchical,
					'hide_empty'      => true,
					'hide_if_empty'   => true,
				);

				if ( isset( $_GET[$tax_slug] ) ) {
					$args['selected'] = $_GET[$tax_slug];
				}
				wp_dropdown_categories( $args );
			}
		}

	}

	/**
	 * Convert product taxonomy term ID to slug.
	 *
	 * When filtering by product, WordPress uses the ID in the query, 
	 * but it does not work. We need to convert to the term slug.
	 * 
	 * In case of ticket_product taxonomy, the product slug also does not
	 * work when clicking a product in the admin columns. We convert it to
	 * the term name/slug, which is the Download ID of the product.
	 * 
	 * @param  object $query WordPress current main query
	 * @link   http://wordpress.stackexchange.com/questions/578/adding-a-taxonomy-filter-to-admin-list-for-a-custom-post-type
	 */
	public function custom_taxonomy_filter_convert_id_term( $query ) {

		global $pagenow;

		/* Check if we are in the correct post type */
		if ( is_admin() && 'edit.php' == $pagenow && isset( $_GET['post_type'] ) && 'edd_ticket' === $_GET['post_type'] && $query->is_main_query() ) {

			// remove filter to avoid error
			remove_all_filters( 'get_term', 1 );

			$fields = $this->get_custom_fields();

			/* Filter custom fields that are taxonomies */
			foreach ( $query->query_vars as $arg => $value ) {
				if ( array_key_exists( $arg, $fields ) && 'taxonomy' === $fields[$arg]['args']['callback'] && true === $fields[$arg]['args']['filterable'] ) {

					/**
					 * If filtering by product, get the product ID who matches this $value
					 */

					if ( 'ticket_product' == $arg ) {

						if ( is_numeric( $value ) ) {

							// Admin filter is in use, so convert the term id to Product id
							$term = get_term_by( 'id', $value, $arg );

							$download_arg = empty( $term->name ) ? '' : $term->name;

						} else {
							$download_arg = $value;
						}

						if ( $download_arg ) {
							$download = edd_get_download( $download_arg );
							if ( isset( $download->ID ) ) {
								$query->query_vars[ $arg ] = $download->ID;
							}
						}

					} else {
						// Other ticket taxonomies
						$term = get_term_by( 'id', $value, $arg );
						if ( false !== $term ) {
							$query->query_vars[ $arg ] = $term->slug;
						}
					}
				}
			}
		}
	}
}

/**
 * Instantiate the global $eddstix_cf object containing all the custom fields.
 * This object is used throughout the entire plugin so it is best to be able
 * to access it anytime and not to redeclare a second object when registering
 * new custom fields.
 *
 * @var    object
 */
$eddstix_cf = new EDDSTIX_Custom_Fields;

/**
 * Return a custom field value.
 *
 * @param  (string)   $name    Option name
 * @param  (integer)  $post_id Post ID
 * @param  (mixed)    $default Default value
 * @return (mixed)             Meta value
 */
function eddstix_get_cf_value( $name, $post_id, $default = false ) {
	return EDDSTIX_Custom_Fields::get_value( $name, $post_id, $default = false );
}

/**
 * Display a custom field value.
 *
 * @param  (string)   $name    Option name
 * @param  (integer)  $post_id Post ID
 * @param  (mixed)    $default Default value
 * @return (mixed)             Meta value
 */
function eddstix_cf_value( $name, $post_id, $default = '' ) {
	return EDDSTIX_Custom_Fields::get_value( $name, $post_id, $default, true );
}

/**
 * Add a new custom field.
 *
 * @param  string  $name  The ID of the custom field to add
 * @param  array   $args  Additional arguments for the custom field
 * @return boolean        Returns true on success or false on failure
 */
function eddstix_add_custom_field( $name, $args = array() ) {

	global $eddstix_cf;

	if ( ! isset( $eddstix_cf ) || ! class_exists( 'EDDSTIX_Custom_Fields' ) )
		return false;

	$eddstix_cf->add_field( $name, $args );
	return true;
}

eddstix_register_core_fields();
/**
 * Register the cure custom fields.
 *
 * @return void
 */
function eddstix_register_core_fields() {

	global $eddstix_cf;

	if ( ! isset( $eddstix_cf ) ) {
		return;
	}

	$eddstix_cf->add_field( 'assignee',   array( 'core' => true, 'show_column' => false, 'log' => true, 'title' => __( 'Support Staff', 'edd-support-tickets' ) ) );
	$eddstix_cf->add_field( 'status',     array( 'core' => true, 'show_column' => true, 'log' => false, 'callback' => false, 'column_callback' => 'eddstix_cf_display_status', 'save_callback' => null ) );
	$eddstix_cf->add_field( 'ticket_tag', array(
		'core'					=> true,
		'show_column'			=> true,// backend
		'log'					=> false,
		'callback'				=> 'taxonomy',
		'show_ui'				=> true,
		'save_callback'			=> null,
		'label'					=> __( 'Tag', 'edd-support-tickets' ),
		'name'					=> __( 'Tag', 'edd-support-tickets' ),
		'label_plural'			=> __( 'Tags', 'edd-support-tickets' ),
		'taxo_hierarchical'		=> false
		)
	);

	$options = get_option( 'eddstix_settings' );

	if ( isset( $options['multiple_products'] ) && true === boolval( $options['multiple_products'] ) ) {
		/* Filter the taxonomy labels */
		$labels = apply_filters( 'eddstix_product_taxonomy_labels', array(
			'label'        => __( 'Product', 'edd-support-tickets' ),
			'name'         => __( 'Product', 'edd-support-tickets' ),
			'label_plural' => __( 'Products', 'edd-support-tickets' )
			)
		);

		$eddstix_cf->add_field( 'ticket_product', array(
			'core'					=> false,
			'show_column'			=> true,
			'log'					=> true,
			'callback'				=> 'taxonomy',
			'required'				=> true,
			'show_ui'				=> false,
			'label'					=> $labels['label'],
			'name'					=> $labels['name'],
			'label_plural'			=> $labels['label_plural'],
			'title'					=> $labels['label'],
			'taxo_hierarchical' 	=> false,
			'meta_box_cb'			=> false
			)
		);
	}
}