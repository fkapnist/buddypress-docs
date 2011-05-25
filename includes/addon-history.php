<?php

/**
 * The History tab add-on for BuddyPress Docs
 *
 * @package BuddyPress Docs
 * @subpackage History
 * @since 1.1
 */
class BP_Docs_History {
	var $action;
	var $left;
	var $right;
	var $revision;
	
	var $revision_id;
	
	var $left_revision;
	var $right_revision;
	var $revisions_are_identical;
	
	var $is_latest;
	
	/**
	 * PHP 4 constructor
	 *
	 * @package BuddyPress Docs
	 * @since 1.1
	 */
	function bp_docs_history() {
		$this->__construct();
	}

	/**
	 * PHP 5 constructor
	 *
	 * @package BuddyPress Docs
	 * @since 1.1
	 */
	function __construct() {
		global $bp;
		
		if ( 'history' != bp_docs_current_view() )
			return false;
	
		$this->setup_params();
		
		// Hooked to a page load action to make sure the post type is registered
		add_action( 'bp_docs_registered_post_type', array( $this, 'setup_action' ), 2 );
		
		$bp->bp_docs->history =& $this;
	}
	
	/**
	 * Setup params from the $_GET global
	 *
	 * Does some sanity checks along the way
	 *
	 * @package BuddyPress Docs
	 * @since 1.1
	 */
	function setup_params() {
		global $bp;
		
		$actions = array(
			'restore',
			'diff',
			'view'
		);
		
		$this->action = !empty( $_GET['action'] ) && in_array( $_GET['action'], $actions ) ? $_GET['action'] : 'view';
		
		$this->left = !empty( $_GET['left'] ) ? (int)$_GET['left'] : false;
		$this->right = !empty( $_GET['right'] ) ? (int)$_GET['right'] : false;
		
		// Try to get the revision id out of the URL. If it's not provided, default to the
		// current post
		$this->revision_id = !empty( $_GET['revision'] ) ? (int)$_GET['revision'] : false;
		if ( !$this->revision_id ) {
			if ( empty( $bp->bp_docs->current_post ) )
				$bp->bp_docs->current_post = bp_docs_get_current_doc();
			
			$this->revision_id = !empty( $bp->bp_docs->current_post->ID ) ? $bp->bp_docs->current_post->ID : false;	
		}
	}
	
	/**
	 * Determines what the user is trying to do on this page view.
	 *
	 * This determination is made mostly on the basis of the information passed in the URL
	 * parameters. This function is also responsible for some of the object setup (getting the
	 * revision post(s), etc). 
	 *
	 * This is cribbed nearly wholesale from wp-admin/revision.php. In the future I would like
	 * to clean it up to be less WordPressy and more pluginish.
	 *
	 * @package BuddyPress Docs
	 * @since 1.1
	 */
	function setup_action() {
		global $bp, $post;
		
		wp_enqueue_script( 'list-revisions' );
			
		$redirect = false;
		
		switch ( $this->action ) :
		case 'restore' :
			if ( !$this->revision = wp_get_post_revision( $this->revision_id ) )
				break;
			if ( !bp_docs_current_user_can( 'edit' ) )
				break;
			if ( !$post = get_post( $this->revision->post_parent ) )
				break;
		
			// Revisions disabled and we're not looking at an autosave
			if ( ( ! WP_POST_REVISIONS || !post_type_supports( $post->post_type, 'revisions') ) && !wp_is_post_autosave( $this->revision ) ) {
				$redirect = 'edit.php?post_type=' . $post->post_type;
				break;
			}
			
			$referer = 'restore-post_' . $post->ID . '|' . $this->revision->ID;
			check_admin_referer( $referer );
		
			wp_restore_post_revision( $this->revision->ID );
			
			bp_core_add_message( sprintf( __( 'You have successfully restored the Doc to the revision from %s.', 'bp-docs' ), $this->revision->post_date ) );
			$redirect = bp_docs_get_doc_link( $post->ID ) . '/' . BP_DOCS_HISTORY_SLUG . '/';
			break;
		case 'diff' :
			if ( !$this->left_revision  = get_post( $this->left ) )
				break;
			if ( !$this->right_revision = get_post( $this->right ) )
				break;
		
			if ( !current_user_can( 'read_post', $this->left_revision->ID ) || !current_user_can( 'read_post', $this->right_revision->ID ) )
				break;
		
			// If we're comparing a revision to itself, redirect to the 'view' page for that revision or the edit page for that post
			if ( $this->left_revision->ID == $this->right_revision->ID ) {
				$redirect = get_edit_post_link( $this->left_revision->ID );
				break;
			}
			
			// Don't allow reverse diffs?
			if ( strtotime( $this->right_revision->post_modified_gmt) < strtotime( $this->left_revision->post_modified_gmt ) ) {
				$redirect = add_query_arg( array( 'left' => $this->right, 'right' => $this->left ) );
				break;
			}
		
			if ( $this->left_revision->ID == $this->right_revision->post_parent ) // right is a revision of left
				$post =& $this->left_revision;
			elseif ( $this->left_revision->post_parent == $this->right_revision->ID ) // left is a revision of right
				$post =& $this->right_revision;
			elseif ( $this->left_revision->post_parent == $this->right_revision->post_parent ) // both are revisions of common parent
				$post = get_post( $this->left_revision->post_parent );
			else
				break; // Don't diff two unrelated revisions
		
			if ( ! WP_POST_REVISIONS || !post_type_supports( $post->post_type, 'revisions' ) ) { // Revisions disabled
			
				if (
					// we're not looking at an autosave
					( !wp_is_post_autosave( $this->left_revision ) && !wp_is_post_autosave( $this->right_revision ) )
				||
					// we're not comparing an autosave to the current post
					( $post->ID !== $this->left_revision->ID && $post->ID !== $this->right_revision->ID )
				) {
					$redirect = 'edit.php?post_type=' . $post->post_type;
					break;
				}
			}
			
			if (
				// They're the same
				$this->left_revision->ID == $this->right_revision->ID
			||
				// Neither is a revision
				( !wp_get_post_revision( $this->left_revision->ID ) && !wp_get_post_revision( $this->right_revision->ID ) )
			)
				break;
		
			$post_title = '<a href="' . get_edit_post_link() . '">' . get_the_title() . '</a>';
			$h2 = sprintf( __( 'Compare Revisions of &#8220;%1$s&#8221;' ), $post_title );
			$title = __( 'Revisions' );
		
			$this->left  = $this->left_revision->ID;
			$this->right = $this->right_revision->ID;
		
			$redirect = false;
			break;
		case 'view' :
		default :
			if ( !$this->revision = wp_get_post_revision( $this->revision_id ) ) {
				if ( $this->revision = get_post( $this->revision_id ) ) {
					$this->is_latest = true;
				} else {
					break;
				}
			}

			if ( !$post = get_post( $this->revision->post_parent ) )
				break;
		
			if ( !current_user_can( 'read_post', $this->revision->ID ) || !current_user_can( 'read_post', $post->ID ) )
				break;
		
			// Revisions disabled and we're not looking at an autosave
			if ( ( ! WP_POST_REVISIONS || !post_type_supports($post->post_type, 'revisions') ) && !wp_is_post_autosave( $this->revision ) ) {
				$redirect = 'edit.php?post_type=' . $post->post_type;
				break;
			}
		
			$post_title = '<a href="' . get_edit_post_link() . '">' . get_the_title() . '</a>';
			$revision_title = wp_post_revision_title( $this->revision, false );
			$h2 = sprintf( __( 'Revision for &#8220;%1$s&#8221; created on %2$s' ), $post_title, $revision_title );
			$title = __( 'Revisions' );
		
			// Sets up the diff radio buttons
			$this->left  = $this->revision->ID;
			$this->right = $post->ID;
		
			$redirect = false;
			break;
		endswitch;
		
		if ( $redirect )
			bp_core_redirect( $redirect );
		
		$this->setup_is_identical();
	}
	
	/**
	 * Determines whether left and right revisions are identical.
	 *
	 * This is cribbed nearly wholesale from wp-admin/revision.php. In the future I would like
	 * to clean it up to be less WordPressy and more pluginish.
	 *
	 * @package BuddyPress Docs
	 * @since 1.1
	 */
	function setup_is_identical() {
		$this->revisions_are_identical = true;
		
		foreach ( _wp_post_revision_fields() as $field => $field_title ) {
			if ( 'diff' == bp_docs_history_action() ) {
				$left_content = apply_filters( "_wp_post_revision_field_$field", $this->left_revision->$field, $field );
				$right_content = apply_filters( "_wp_post_revision_field_$field", $this->right_revision->$field, $field );
				if ( !$content = wp_text_diff( $left_content, $right_content ) )
					continue; // There is no difference between left and right
				$this->revisions_are_identical = false;
			} else {
				add_filter( "_wp_post_revision_field_$field", 'htmlspecialchars' );
				$content = apply_filters( "_wp_post_revision_field_$field", $this->revision->$field, $field );
			}
		}
	}
}

/**
 * Returns the current revision action.
 *
 * @package BuddyPress Docs
 * @since 1.1
 *
 * @return str $action The current revision action ('view', 'diff', 'restore')
 */
function bp_docs_history_action() {
	global $bp;
	
	$action = !empty( $bp->bp_docs->history->action ) ? $bp->bp_docs->history->action : false;
	
	return apply_filters( 'bp_docs_history_action', $action );
}

/**
 * Returns the data from a revision field.
 *
 * This is seriously cobbled together. WP uses specific functions to get template data out of the
 * right and left revisions, and specific functions for title vs content, etc. That seems like
 * overkill to me. I also added a default null $side, so that you can also use this function to
 * get the field of a single revision ($history->revision) instead of $history->left_revision, etc.
 *
 * @package BuddyPress Docs
 * @since 1.1
 *
 * @param str $side 'left', 'right', or false to show the main revision
 * @param str $field Which property of the revision do you want?
 * @return str $data The field data
 */
function bp_docs_history_post_revision_field( $side = false, $field = 'post_title' ) {
	global $bp;
	
	if ( $side ) {
		$side = 'right' == $side ? 'right_revision' : 'left_revision';
		$data = $bp->bp_docs->history->{$side}->{$field};
	} else {
		$data = $bp->bp_docs->history->revision->{$field};
	}
	
	return apply_filters( 'bp_docs_history_post_revision_title', $data );
}

/**
 * Returns whether the revisions are identical.
 *
 * @package BuddyPress Docs
 * @since 1.1
 *
 * @return bool True when left and right are the same
 */
function bp_docs_history_revisions_are_identical() {
	global $bp;

	return apply_filters( 'bp_docs_history_post_revision_title', $bp->bp_docs->history->revisions_are_identical );
}

/**
 * Returns whether the revision being viewed is the most recent (ie the current) rev of the Doc.
 *
 * I need this function in order to decide whether to show the revision content above the revision
 * history selector. That's because I don't like WP's default behavior, which is to show the most
 * recent revision.
 *
 * @package BuddyPress Docs
 * @since 1.1
 *
 * @return bool True when the current revision is the latest revision
 */
function bp_docs_history_is_latest() {
	global $bp;

	return apply_filters( 'bp_docs_history_post_revision_title', $bp->bp_docs->history->is_latest );
}

/**
 * Display list of a Docs's revisions. Borrowed heavily from WP's wp_list_post_revisions()
 *
 * @package BuddyPress Docs
 * @since 1.1
 *
 * @uses wp_get_post_revisions()
 * @uses wp_post_revision_title()
 * @uses get_edit_post_link()
 * @uses get_the_author_meta()
 *
 * @param int|object $post_id Post ID or post object.
 * @param string|array $args See description {@link wp_parse_args()}.
 * @return null
 */
function bp_docs_list_post_revisions( $post_id = 0, $args = null ) {
	global $bp;

	if ( !$post = get_post( $post_id ) )
		return;

	$defaults = array( 
		'parent' => false, 
		'right'  => $bp->bp_docs->history->right, 
		'left'   => $bp->bp_docs->history->left, 
		'format' => 'form-table', 
		'type'   => 'all' 
	);
	
	extract( wp_parse_args( $args, $defaults ), EXTR_SKIP );

	switch ( $type ) {
		case 'autosave' :
			if ( !$autosave = wp_get_post_autosave( $post->ID ) )
				return;
			$revisions = array( $autosave );
			break;
		case 'revision' : // just revisions - remove autosave later
		case 'all' :
		default :
			if ( !$revisions = wp_get_post_revisions( $post->ID ) )
				return;
			break;
	}

	/* translators: post revision: 1: when, 2: author name */
	$titlef = _x( '%1$s by %2$s', 'post revision' );

	if ( $parent )
		array_unshift( $revisions, $post );

	$rows = $right_checked = '';
	$class = false;
	$can_edit_post = bp_docs_current_user_can( 'edit' );
	foreach ( $revisions as $revision ) {
		if ( !current_user_can( 'read_post', $revision->ID ) )
			continue;
		if ( 'revision' === $type && wp_is_post_autosave( $revision ) )
			continue;
		
		$base_url = bp_docs_get_doc_link( get_the_ID() ) . '/' . BP_DOCS_HISTORY_SLUG . '/';

		$date = '<a href="' . add_query_arg( 'revision', $revision->ID ) . '">' . bp_format_time( strtotime( $revision->post_date ) ) . '</a>';
		$name = bp_core_get_userlink( $revision->post_author );
		
		if ( 'form-table' == $format ) {
			if ( $left )
				$left_checked = $left == $revision->ID ? ' checked="checked"' : '';
			else
				$left_checked = $right_checked ? ' checked="checked"' : ''; // [sic] (the next one)
			$right_checked = $right == $revision->ID ? ' checked="checked"' : '';

			$class = $class ? '' : " class='alternate'";

			if ( $post->ID != $revision->ID && $can_edit_post )
				$actions = '<a class="confirm" href="' . wp_nonce_url( add_query_arg( array( 'revision' => $revision->ID, 'action' => 'restore' ), $base_url ), "restore-post_$post->ID|$revision->ID" ) . '">' . __( 'Restore' ) . '</a>';
			else
				$actions = '';

			$rows .= "<tr$class>\n";
			$rows .= "\t<th style='white-space: nowrap' scope='row'><input type='radio' name='left' value='$revision->ID'$left_checked /></th>\n";
			$rows .= "\t<th style='white-space: nowrap' scope='row'><input type='radio' name='right' value='$revision->ID'$right_checked /></th>\n";
			$rows .= "\t<td>$date</td>\n";
			$rows .= "\t<td>$name</td>\n";
			$rows .= "\t<td class='action-links'>$actions</td>\n";
			$rows .= "</tr>\n";
		} else {
			$title = sprintf( $titlef, $date, $name );
			$rows .= "\t<li>$title</li>\n";
		}
	}

?>

<form action="" method="get">

<div class="tablenav">
	<div class="alignleft">
		<input type="submit" class="button-secondary" value="<?php esc_attr_e( 'Compare Revisions' ); ?>" />
		<input type="hidden" name="action" value="diff" />
		<input type="hidden" name="post_type" value="<?php echo esc_attr($post->post_type); ?>" />
	</div>
</div>

<br class="clear" />

<table class="widefat post-revisions" cellspacing="0" id="post-revisions">
	<col />
	<col />
	<col style="width: 33%" />
	<col style="width: 33%" />
	<col style="width: 33%" />
<thead>
<tr>
	<th scope="col"><?php /* translators: column name in revisons */ _ex( 'Old', 'revisions column name' ); ?></th>
	<th scope="col"><?php /* translators: column name in revisons */ _ex( 'New', 'revisions column name' ); ?></th>
	<th scope="col"><?php /* translators: column name in revisons */ _ex( 'Date Created', 'revisions column name' ); ?></th>
	<th scope="col"><?php _e( 'Author' ); ?></th>
	<th scope="col" class="action-links"><?php _e( 'Actions' ); ?></th>
</tr>
</thead>
<tbody>

<?php echo $rows; ?>

</tbody>
</table>

</form>

<?php

}


?>