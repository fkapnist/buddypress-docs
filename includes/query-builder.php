<?php

class BP_Docs_Query {
	var $item_type;
	var $item_id;
	var $item_name;
	var $item_slug;
	
	var $doc_id;
	var $doc_slug;
	
	var $current_view;
	
	var $term_id;
	var $item_type_term_id;
	
	/**
	 * PHP 4 constructor
	 *
	 * @package BuddyPress Docs
	 * @since 1.0
	 */
	function bp_docs_query() {
		$this->__construct();
	}

	/**
	 * PHP 5 constructor
	 *
	 * @package BuddyPress Docs
	 * @since 1.0
	 */	
	function __construct() {
		$this->item_type = $this->get_item_type();
		$this->setup_item();
		$this->setup_terms();
		$this->current_view = $this->get_current_view();
		
		$this->template = $this->template_decider();
	}

	/**
	 * Gets the item type of the item you're looking at - e.g 'group', 'user'.
	 *
	 * @package BuddyPress Docs
	 * @since 1.0
	 *
	 * @return str $view The current item type
	 */
	function get_item_type() {
		global $bp;
		
		$type = '';
		
		// First, test to see whether this is a group docs page
		if ( $bp->current_component == $bp->groups->slug ) {
			$type = 'group';
		}
		
		return apply_filters( 'bp_docs_get_item_type', $type, $this );
	}
	
	/**
	 * Gets the item id of the item (eg group, user) associated with the page you're on.
	 *
	 * @package BuddyPress Docs
	 * @since 1.0
	 *
	 * @return str $view The current item type
	 */
	function setup_item() {
		global $bp;
		
		if ( empty( $this->item_type ) )
			return false;
		
		$id = '';
		$name = '';
		
		switch ( $this->item_type ) {
			case 'group' :
				if ( !empty( $bp->groups->current_group->id ) )
					$id = $bp->groups->current_group->id;
					$name = $bp->groups->current_group->name;
					$slug = $bp->groups->current_group->slug;
				break;
			case 'user' :
				if ( !empty( $bp->displayed_user->id ) )
					$id = $bp->displayed_user->id;
					$id = $bp->displayed_user->display_name;
					$id = $bp->displayed_user->userdata->user_nicename;
				break;
		}
		
		$this->item_id = apply_filters( 'bp_docs_get_item_id', $id );
		$this->item_name = apply_filters( 'bp_docs_get_item_name', $name );
		$this->item_slug = apply_filters( 'bp_docs_get_item_slug', $slug );
	}
	
	/**
	 * Gets the id of the taxonomy term associated with the item
	 *
	 * @package BuddyPress Docs
	 * @since 1.0
	 *
	 * @return str $view The current item type
	 */
	function setup_terms() {
		global $bp;
		
		// Get the term id for the item type
		$item_type_term = term_exists( $this->item_type, 'bp_docs_associated_item' );
		
		// If the item type term doesn't exist, then create it
		if ( empty( $item_type_term ) ) {
			// Filter this value to add your own item types, or to change slugs
			$defaults = apply_filters( 'bp_docs_item_type_term_values', array(
				'group' => array(
					'description' => __( 'Groups that have docs associated with them', 'bp-docs' ),
					'slug' => 'group'
				),
				'user' => array(
					'description' => __( 'Users that have docs associated with them', 'bp-docs' ),
					'slug' => 'user'
				)
			) );
		
			// Select the proper values from the defaults array
			$item_type_term_args = !empty( $defaults[$this->item_type] ) ? $defaults[$this->item_type] : false;
			
			// Create the item type term
			if ( !$item_type_term = wp_insert_term( __( 'Groups', 'buddypress' ), 'bp_docs_associated_item', $item_type_term_args ) )
				return false;	
		} 
		
		$this->item_type_term_id = apply_filters( 'bp_docs_get_item_type_term_id', $item_type_term['term_id'], $this );
			
		// Now, find the term associated with the item itself
		$item_term = term_exists( $this->item_id, 'bp_docs_associated_item', $this->item_type_term_id );
		
		// If the item term doesn't exist, then create it
		if ( empty( $item_term ) ) {
			// Set up the arguments for creating the term. Filter this to set your own
			$item_term_args = apply_filters( 'bp_docs_item_term_values', array(
				'description' => $this->item_name,
				'slug' => $this->item_slug,
				'parent' => $this->item_type_term_id
			) );
			
			// Create the item term
			if ( !$item_term = wp_insert_term( $this->item_id, 'bp_docs_associated_item', $item_term_args ) )
				return false;	
		}
		
		$this->term_id = apply_filters( 'bp_docs_get_item_term_id', $item_term['term_id'], $this );
	}

	/**
	 * Gets the current view, based on the page you're looking at.
	 *
	 * Filter 'bp_docs_get_current_view' to extend to different components.
	 *
	 * @package BuddyPress Docs
	 * @since 1.0
	 *
	 * @return str $view The current view. Core values: edit, single, list, category
	 */
	function get_current_view() {
		global $bp;
		
		$view = '';
		
		// First, test to see whether this is a group docs page
		if ( $this->item_type == 'group' ) {
			if ( empty( $bp->action_variables[0] ) ) {
				// An empty $bp->action_variables[0] means that you're looking at a list
				$view = 'list';
			} else if ( $bp->action_variables[0] == BP_DOCS_CATEGORY_SLUG ) {
				// Category view
				$view = 'category';
			} else if ( $bp->action_variables[0] == BP_DOCS_CREATE_SLUG ) {
				// Create new doc
				$view = 'create';
			} else if ( empty( $bp->action_variables[1] ) ) {
				// $bp->action_variables[1] is the slug for this doc. If there's no
				// further chunk, then we're attempting to view a single item
				$view = 'single';
			} else if ( !empty( $bp->action_variables[1] ) && $bp->action_variables[1] == BP_DOCS_EDIT_SLUG ) {
				// This is an edit page
				$view = 'edit';
			}
		}
		
		return apply_filters( 'bp_docs_get_current_view', $view );
	}
	
	/**
	 * Builds the WP query
	 *
	 * @package BuddyPress Docs
	 * @since 1.0
	 *
	 */
	function build_query() {
		// Get the tax term by id. Todo: Why can't I make this work less stupidly?
		$term = get_term_by( 'id', $this->term_id, 'bp_docs_associated_item' );
		
		$args = array(
			'post_type' => 'bp_doc',
			'bp_docs_associated_item' => $term->slug
		);
		
		return $args;
	}
	
	function template_decider() {
		global $bp;
		
		$template_path = BP_DOCS_INSTALL_PATH . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR;
		
		switch ( $this->current_view ) {
			case 'create' :
				// Make sure the user has permission to create
				// Then load the create template
			case 'list' :
				$args = $this->build_query();
				
				query_posts( $args );
				$template = $template_path . 'docs-loop.php';				
				break;
			case 'category' :
				// Check to make sure the category exists
				// If not, redirect back to list view with error
				// Otherwise, get args based on category ID
				// Then load the loop template
				break;
			case 'single' :
			case 'edit' :
				// First, find the slug
				if ( $this->item_type == 'group' )
					$slug = $bp->action_variables[0];
				
				$this->doc_slug = apply_filters( 'bp_docs_this_doc_slug', $slug, $this );
				
				$args = $this->build_query();
				
				// Add a 'name' argument so that we only get the specific post
				$args['name'] = $this->doc_slug;
				
				query_posts( $args );
				
				if ( $this->current_view == 'single' )
					$template = $template_path . 'single-doc.php';	
				else
					$template = $template_path . 'edit-doc.php';
				
				// Todo: Maybe some sort of error if there is no edit permission?
	
				break;
		}
		
		include( apply_filters( 'bp_docs_template', $template, $this ) );
	}


}

?>