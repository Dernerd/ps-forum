<?php

/**
 * PSForum Forum Functions
 *
 * @package PSForum
 * @subpackage Functions
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/** Insert ********************************************************************/

/**
 * A wrapper for wp_insert_post() that also includes the necessary meta values
 * for the forum to function properly.
 *
 * @since PSForum (r3349)
 *
 * @uses psf_parse_args()
 * @uses psf_get_forum_post_type()
 * @uses wp_insert_post()
 * @uses update_post_meta()
 *
 * @param array $forum_data Forum post data
 * @param arrap $forum_meta Forum meta data
 */
function psf_insert_forum( $forum_data = array(), $forum_meta = array() ) {

	// Forum
	$forum_data = psf_parse_args( $forum_data, array(
		'post_parent'    => 0, // forum ID
		'post_status'    => psf_get_public_status_id(),
		'post_type'      => psf_get_forum_post_type(),
		'post_author'    => psf_get_current_user_id(),
		'post_password'  => '',
		'post_content'   => '',
		'post_title'     => '',
		'menu_order'     => 0,
		'comment_status' => 'closed'
	), 'insert_forum' );

	// Insert forum
	$forum_id   = wp_insert_post( $forum_data );

	// Bail if no forum was added
	if ( empty( $forum_id ) ) {
		return false;
	}

	// Forum meta
	$forum_meta = psf_parse_args( $forum_meta, array(
		'reply_count'          => 0,
		'topic_count'          => 0,
		'topic_count_hidden'   => 0,
		'total_reply_count'    => 0,
		'total_topic_count'    => 0,
		'last_topic_id'        => 0,
		'last_reply_id'        => 0,
		'last_active_id'       => 0,
		'last_active_time'     => 0,
		'forum_subforum_count' => 0,
	), 'insert_forum_meta' );

	// Insert forum meta
	foreach ( $forum_meta as $meta_key => $meta_value ) {
		update_post_meta( $forum_id, '_psf_' . $meta_key, $meta_value );
	}

	// Return new forum ID
	return $forum_id;
}

/** Post Form Handlers ********************************************************/

/**
 * Handles the front end forum submission
 *
 * @param string $action The requested action to compare this function to
 * @uses psf_add_error() To add an error message
 * @uses psf_verify_nonce_request() To verify the nonce and check the request
 * @uses psf_is_anonymous() To check if an anonymous post is being made
 * @uses current_user_can() To check if the current user can publish forum
 * @uses psf_get_current_user_id() To get the current user id
 * @uses psf_filter_anonymous_post_data() To filter anonymous data
 * @uses psf_set_current_anonymous_user_data() To set the anonymous user cookies
 * @uses is_wp_error() To check if the value retrieved is a {@link WP_Error}
 * @uses esc_attr() For sanitization
 * @uses psf_is_forum_category() To check if the forum is a category
 * @uses psf_is_forum_closed() To check if the forum is closed
 * @uses psf_is_forum_private() To check if the forum is private
 * @uses psf_check_for_flood() To check for flooding
 * @uses psf_check_for_duplicate() To check for duplicates
 * @uses psf_get_forum_post_type() To get the forum post type
 * @uses remove_filter() To remove kses filters if needed
 * @uses apply_filters() Calls 'psf_new_forum_pre_title' with the content
 * @uses apply_filters() Calls 'psf_new_forum_pre_content' with the content
 * @uses PSForum::errors::get_error_codes() To get the {@link WP_Error} errors
 * @uses wp_insert_post() To insert the forum
 * @uses do_action() Calls 'psf_new_forum' with the forum id, forum id,
 *                    anonymous data and reply author
 * @uses psf_stick_forum() To stick or super stick the forum
 * @uses psf_unstick_forum() To unstick the forum
 * @uses psf_get_forum_permalink() To get the forum permalink
 * @uses wp_safe_redirect() To redirect to the forum link
 * @uses PSForum::errors::get_error_messages() To get the {@link WP_Error} error
 *                                              messages
 */
function psf_new_forum_handler( $action = '' ) {

	// Bail if action is not psf-new-forum
	if ( 'psf-new-forum' !== $action )
		return;

	// Nonce check
	if ( ! psf_verify_nonce_request( 'psf-new-forum' ) ) {
		psf_add_error( 'psf_new_forum_nonce', __( '<strong>FEHLER</strong>: Willst Du das wirklich?', 'psforum' ) );
		return;
	}

	// Define local variable(s)
	$view_all = $anonymous_data = false;
	$forum_parent_id = $forum_author = 0;
	$forum_title = $forum_content = '';

	/** Forum Author **********************************************************/

	// User cannot create forums
	if ( !current_user_can( 'publish_forums' ) ) {
		psf_add_error( 'psf_forum_permissions', __( '<strong>FEHLER</strong>: Du bist nicht berechtigt, neue Foren zu erstellen.', 'psforum' ) );
		return;
	}

	// Forum author is current user
	$forum_author = psf_get_current_user_id();

	// Remove kses filters from title and content for capable users and if the nonce is verified
	if ( current_user_can( 'unfiltered_html' ) && !empty( $_POST['_psf_unfiltered_html_forum'] ) && wp_create_nonce( 'psf-unfiltered-html-forum_new' ) === $_POST['_psf_unfiltered_html_forum'] ) {
		remove_filter( 'psf_new_forum_pre_title',   'wp_filter_kses'      );
		remove_filter( 'psf_new_forum_pre_content', 'psf_encode_bad',  10 );
		remove_filter( 'psf_new_forum_pre_content', 'psf_filter_kses', 30 );
	}

	/** Forum Title ***********************************************************/

	if ( !empty( $_POST['psf_forum_title'] ) )
		$forum_title = esc_attr( strip_tags( $_POST['psf_forum_title'] ) );

	// Filter and sanitize
	$forum_title = apply_filters( 'psf_new_forum_pre_title', $forum_title );

	// No forum title
	if ( empty( $forum_title ) )
		psf_add_error( 'psf_forum_title', __( '<strong>FEHLER</strong>: Dein Forum benötigt einen Titel.', 'psforum' ) );

	/** Forum Content *********************************************************/

	if ( !empty( $_POST['psf_forum_content'] ) )
		$forum_content = $_POST['psf_forum_content'];

	// Filter and sanitize
	$forum_content = apply_filters( 'psf_new_forum_pre_content', $forum_content );

	// No forum content
	if ( empty( $forum_content ) )
		psf_add_error( 'psf_forum_content', __( '<strong>FEHLER</strong>: Deine Forumsbeschreibung darf nicht leer sein.', 'psforum' ) );

	/** Forum Parent **********************************************************/

	// Forum parent was passed (the norm)
	if ( !empty( $_POST['psf_forum_parent_id'] ) ) {
		$forum_parent_id = psf_get_forum_id( $_POST['psf_forum_parent_id'] );
	}

	// Filter and sanitize
	$forum_parent_id = apply_filters( 'psf_new_forum_pre_parent_id', $forum_parent_id );

	// No forum parent was passed (should never happen)
	if ( empty( $forum_parent_id ) ) {
		psf_add_error( 'psf_new_forum_missing_parent', __( '<strong>FEHLER</strong>: Dein Forum muss ein Elternteil haben.', 'psforum' ) );

	// Forum exists
	} elseif ( !empty( $forum_parent_id ) ) {

		// Forum is a category
		if ( psf_is_forum_category( $forum_parent_id ) ) {
			psf_add_error( 'psf_new_forum_forum_category', __( '<strong>FEHLER</strong>: Dieses Forum ist eine Kategorie. In diesem Forum können keine Foren erstellt werden.', 'psforum' ) );
		}

		// Forum is closed and user cannot access
		if ( psf_is_forum_closed( $forum_parent_id ) && !current_user_can( 'edit_forum', $forum_parent_id ) ) {
			psf_add_error( 'psf_new_forum_forum_closed', __( '<strong>FEHLER</strong>: Dieses Forum wurde für neue Foren geschlossen.', 'psforum' ) );
		}

		// Forum is private and user cannot access
		if ( psf_is_forum_private( $forum_parent_id ) && !current_user_can( 'read_private_forums' ) ) {
			psf_add_error( 'psf_new_forum_forum_private', __( '<strong>FEHLER</strong>: Dieses Forum ist privat und Du hast nicht die Möglichkeit, darin Foren zu lesen oder neue Foren zu erstellen.', 'psforum' ) );
		}

		// Forum is hidden and user cannot access
		if ( psf_is_forum_hidden( $forum_parent_id ) && !current_user_can( 'read_hidden_forums' ) ) {
			psf_add_error( 'psf_new_forum_forum_hidden', __( '<strong>FEHLER</strong>: Dieses Forum ist ausgeblendet und Du kannst darin keine Foren lesen oder neue Foren erstellen.', 'psforum' ) );
		}
	}

	/** Forum Flooding ********************************************************/

	if ( !psf_check_for_flood( $anonymous_data, $forum_author ) )
		psf_add_error( 'psf_forum_flood', __( '<strong>FEHLER</strong>: Mach langsam; du bewegst dich zu schnell.', 'psforum' ) );

	/** Forum Duplicate *******************************************************/

	if ( !psf_check_for_duplicate( array( 'post_type' => psf_get_forum_post_type(), 'post_author' => $forum_author, 'post_content' => $forum_content, 'anonymous_data' => $anonymous_data ) ) )
		psf_add_error( 'psf_forum_duplicate', __( '<strong>FEHLER</strong>: Dieses Forum existiert bereits.', 'psforum' ) );

	/** Forum Blacklist *******************************************************/

	if ( !psf_check_for_blacklist( $anonymous_data, $forum_author, $forum_title, $forum_content ) )
		psf_add_error( 'psf_forum_blacklist', __( '<strong>FEHLER</strong>: Dein Forum kann derzeit nicht erstellt werden.', 'psforum' ) );

	/** Forum Moderation ******************************************************/

	$post_status = psf_get_public_status_id();
	if ( !psf_check_for_moderation( $anonymous_data, $forum_author, $forum_title, $forum_content ) )
		$post_status = psf_get_pending_status_id();

	/** Additional Actions (Before Save) **************************************/

	do_action( 'psf_new_forum_pre_extras', $forum_parent_id );

	// Bail if errors
	if ( psf_has_errors() )
		return;

	/** No Errors *************************************************************/

	// Add the content of the form to $forum_data as an array
	// Just in time manipulation of forum data before being created
	$forum_data = apply_filters( 'psf_new_forum_pre_insert', array(
		'post_author'    => $forum_author,
		'post_title'     => $forum_title,
		'post_content'   => $forum_content,
		'post_parent'    => $forum_parent_id,
		'post_status'    => $post_status,
		'post_type'      => psf_get_forum_post_type(),
		'comment_status' => 'closed'
	) );

	// Insert forum
	$forum_id = wp_insert_post( $forum_data );

	/** No Errors *************************************************************/

	if ( !empty( $forum_id ) && !is_wp_error( $forum_id ) ) {

		/** Trash Check *******************************************************/

		// If the forum is trash, or the forum_status is switched to
		// trash, trash it properly
		if ( ( get_post_field( 'post_status', $forum_id ) === psf_get_trash_status_id() ) || ( $forum_data['post_status'] === psf_get_trash_status_id() ) ) {

			// Trash the reply
			wp_trash_post( $forum_id );

			// Force view=all
			$view_all = true;
		}

		/** Spam Check ********************************************************/

		// If reply or forum are spam, officially spam this reply
		if ( $forum_data['post_status'] === psf_get_spam_status_id() ) {
			add_post_meta( $forum_id, '_psf_spam_meta_status', psf_get_public_status_id() );

			// Force view=all
			$view_all = true;
		}

		/** Update counts, etc... *********************************************/

		do_action( 'psf_new_forum', array(
			'forum_id'           => $forum_id,
			'post_parent'        => $forum_parent_id,
			'forum_author'       => $forum_author,
			'last_topic_id'      => 0,
			'last_reply_id'      => 0,
			'last_active_id'     => 0,
			'last_active_time'   => 0,
			'last_active_status' => psf_get_public_status_id()
		) );

		/** Additional Actions (After Save) ***********************************/

		do_action( 'psf_new_forum_post_extras', $forum_id );

		/** Redirect **********************************************************/

		// Redirect to
		$redirect_to  = psf_get_redirect_to();

		// Get the forum URL
		$redirect_url = psf_get_forum_permalink( $forum_id, $redirect_to );

		// Add view all?
		if ( psf_get_view_all() || !empty( $view_all ) ) {

			// User can moderate, so redirect to forum with view all set
			if ( current_user_can( 'moderate' ) ) {
				$redirect_url = psf_add_view_all( $redirect_url );

			// User cannot moderate, so redirect to forum
			} else {
				$redirect_url = psf_get_forum_permalink( $forum_id );
			}
		}

		// Allow to be filtered
		$redirect_url = apply_filters( 'psf_new_forum_redirect_to', $redirect_url, $redirect_to );

		/** Successful Save ***************************************************/

		// Redirect back to new forum
		wp_safe_redirect( $redirect_url );

		// For good measure
		exit();

	// Errors
	} else {
		$append_error = ( is_wp_error( $forum_id ) && $forum_id->get_error_message() ) ? $forum_id->get_error_message() . ' ' : '';
		psf_add_error( 'psf_forum_error', __( '<strong>FEHLER</strong>: Die folgenden Probleme wurden in Deinem Forum gefunden:' . $append_error, 'psforum' ) );
	}
}

/**
 * Handles the front end edit forum submission
 *
 * @param string $action The requested action to compare this function to
 * @uses PSForum:errors::add() To log various error messages
 * @uses psf_get_forum() To get the forum
 * @uses psf_verify_nonce_request() To verify the nonce and check the request
 * @uses psf_is_forum_anonymous() To check if forum is by an anonymous user
 * @uses current_user_can() To check if the current user can edit the forum
 * @uses psf_filter_anonymous_post_data() To filter anonymous data
 * @uses is_wp_error() To check if the value retrieved is a {@link WP_Error}
 * @uses esc_attr() For sanitization
 * @uses psf_is_forum_category() To check if the forum is a category
 * @uses psf_is_forum_closed() To check if the forum is closed
 * @uses psf_is_forum_private() To check if the forum is private
 * @uses remove_filter() To remove kses filters if needed
 * @uses apply_filters() Calls 'psf_edit_forum_pre_title' with the title and
 *                        forum id
 * @uses apply_filters() Calls 'psf_edit_forum_pre_content' with the content
 *                        and forum id
 * @uses PSForum::errors::get_error_codes() To get the {@link WP_Error} errors
 * @uses wp_save_post_revision() To save a forum revision
 * @uses psf_update_forum_revision_log() To update the forum revision log
 * @uses wp_update_post() To update the forum
 * @uses do_action() Calls 'psf_edit_forum' with the forum id, forum id,
 *                    anonymous data and reply author
 * @uses psf_move_forum_handler() To handle movement of a forum from one forum
 *                                 to another
 * @uses psf_get_forum_permalink() To get the forum permalink
 * @uses wp_safe_redirect() To redirect to the forum link
 * @uses PSForum::errors::get_error_messages() To get the {@link WP_Error} error
 *                                              messages
 */
function psf_edit_forum_handler( $action = '' ) {

	// Bail if action is not psf-edit-forum
	if ( 'psf-edit-forum' !== $action )
		return;

	// Define local variable(s)
	$anonymous_data = array();
	$forum = $forum_id = $forum_parent_id = 0;
	$forum_title = $forum_content = $forum_edit_reason = '';

	/** Forum *****************************************************************/

	// Forum id was not passed
	if ( empty( $_POST['psf_forum_id'] ) ) {
		psf_add_error( 'psf_edit_forum_id', __( '<strong>FEHLER</strong>: Forum-ID nicht gefunden.', 'psforum' ) );
		return;

	// Forum id was passed
	} elseif ( is_numeric( $_POST['psf_forum_id'] ) ) {
		$forum_id = (int) $_POST['psf_forum_id'];
		$forum    = psf_get_forum( $forum_id );
	}

	// Nonce check
	if ( ! psf_verify_nonce_request( 'psf-edit-forum_' . $forum_id ) ) {
		psf_add_error( 'psf_edit_forum_nonce', __( '<strong>FEHLER</strong>: Willst Du das wirklich?', 'psforum' ) );
		return;

	// Forum does not exist
	} elseif ( empty( $forum ) ) {
		psf_add_error( 'psf_edit_forum_not_found', __( '<strong>FEHLER</strong>: Das Forum, das Du bearbeiten möchtest, wurde nicht gefunden.', 'psforum' ) );
		return;

	// User cannot edit this forum
	} elseif ( !current_user_can( 'edit_forum', $forum_id ) ) {
		psf_add_error( 'psf_edit_forum_permissions', __( '<strong>FEHLER</strong>: Du bist nicht berechtigt, dieses Forum zu bearbeiten.', 'psforum' ) );
		return;
	}

	// Remove kses filters from title and content for capable users and if the nonce is verified
	if ( current_user_can( 'unfiltered_html' ) && !empty( $_POST['_psf_unfiltered_html_forum'] ) && ( wp_create_nonce( 'psf-unfiltered-html-forum_' . $forum_id ) === $_POST['_psf_unfiltered_html_forum'] ) ) {
		remove_filter( 'psf_edit_forum_pre_title',   'wp_filter_kses'      );
		remove_filter( 'psf_edit_forum_pre_content', 'psf_encode_bad',  10 );
		remove_filter( 'psf_edit_forum_pre_content', 'psf_filter_kses', 30 );
	}

	/** Forum Parent ***********************************************************/

	// Forum parent id was passed
	if ( !empty( $_POST['psf_forum_parent_id'] ) ) {
		$forum_parent_id = psf_get_forum_id( $_POST['psf_forum_parent_id'] );
	}

	// Current forum this forum is in
	$current_parent_forum_id = psf_get_forum_parent_id( $forum_id );

	// Forum exists
	if ( !empty( $forum_parent_id ) && ( $forum_parent_id !== $current_parent_forum_id ) ) {

		// Forum is closed and user cannot access
		if ( psf_is_forum_closed( $forum_parent_id ) && !current_user_can( 'edit_forum', $forum_parent_id ) ) {
			psf_add_error( 'psf_edit_forum_forum_closed', __( '<strong>FEHLER</strong>: Dieses Forum wurde für neue Foren geschlossen.', 'psforum' ) );
		}

		// Forum is private and user cannot access
		if ( psf_is_forum_private( $forum_parent_id ) && !current_user_can( 'read_private_forums' ) ) {
			psf_add_error( 'psf_edit_forum_forum_private', __( '<strong>FEHLER</strong>: Dieses Forum ist privat und Du hast nicht die Möglichkeit, darin Foren zu lesen oder neue Foren zu erstellen.', 'psforum' ) );
		}

		// Forum is hidden and user cannot access
		if ( psf_is_forum_hidden( $forum_parent_id ) && !current_user_can( 'read_hidden_forums' ) ) {
			psf_add_error( 'psf_edit_forum_forum_hidden', __( '<strong>FEHLER</strong>: Dieses Forum ist ausgeblendet und Du kannst darin keine Foren lesen oder neue Foren erstellen.', 'psforum' ) );
		}
	}

	/** Forum Title ***********************************************************/

	if ( !empty( $_POST['psf_forum_title'] ) )
		$forum_title = esc_attr( strip_tags( $_POST['psf_forum_title'] ) );

	// Filter and sanitize
	$forum_title = apply_filters( 'psf_edit_forum_pre_title', $forum_title, $forum_id );

	// No forum title
	if ( empty( $forum_title ) )
		psf_add_error( 'psf_edit_forum_title', __( '<strong>FEHLER</strong>: Dein Forum benötigt einen Titel.', 'psforum' ) );

	/** Forum Content *********************************************************/

	if ( !empty( $_POST['psf_forum_content'] ) )
		$forum_content = $_POST['psf_forum_content'];

	// Filter and sanitize
	$forum_content = apply_filters( 'psf_edit_forum_pre_content', $forum_content, $forum_id );

	// No forum content
	if ( empty( $forum_content ) )
		psf_add_error( 'psf_edit_forum_content', __( '<strong>FEHLER</strong>: Deine Forumsbeschreibung darf nicht leer sein.', 'psforum' ) );

	/** Forum Blacklist *******************************************************/

	if ( !psf_check_for_blacklist( $anonymous_data, psf_get_forum_author_id( $forum_id ), $forum_title, $forum_content ) )
		psf_add_error( 'psf_forum_blacklist', __( '<strong>FEHLER</strong>: Dein Forum kann derzeit nicht bearbeitet werden.', 'psforum' ) );

	/** Forum Moderation ******************************************************/

	$post_status = psf_get_public_status_id();
	if ( !psf_check_for_moderation( $anonymous_data, psf_get_forum_author_id( $forum_id ), $forum_title, $forum_content ) )
		$post_status = psf_get_pending_status_id();

	/** Additional Actions (Before Save) **************************************/

	do_action( 'psf_edit_forum_pre_extras', $forum_id );

	// Bail if errors
	if ( psf_has_errors() )
		return;

	/** No Errors *************************************************************/

	// Add the content of the form to $forum_data as an array
	// Just in time manipulation of forum data before being edited
	$forum_data = apply_filters( 'psf_edit_forum_pre_insert', array(
		'ID'           => $forum_id,
		'post_title'   => $forum_title,
		'post_content' => $forum_content,
		'post_status'  => $post_status,
		'post_parent'  => $forum_parent_id
	) );

	// Insert forum
	$forum_id = wp_update_post( $forum_data );

	/** Revisions *************************************************************/

	/**
	 * @todo omitted for 2.1
	// Revision Reason
	if ( !empty( $_POST['psf_forum_edit_reason'] ) )
		$forum_edit_reason = esc_attr( strip_tags( $_POST['psf_forum_edit_reason'] ) );

	// Update revision log
	if ( !empty( $_POST['psf_log_forum_edit'] ) && ( "1" === $_POST['psf_log_forum_edit'] ) && ( $revision_id = wp_save_post_revision( $forum_id ) ) ) {
		psf_update_forum_revision_log( array(
			'forum_id'    => $forum_id,
			'revision_id' => $revision_id,
			'author_id'   => psf_get_current_user_id(),
			'reason'      => $forum_edit_reason
		) );
	}
	 */

	/** No Errors *************************************************************/

	if ( !empty( $forum_id ) && !is_wp_error( $forum_id ) ) {

		// Update counts, etc...
		do_action( 'psf_edit_forum', array(
			'forum_id'           => $forum_id,
			'post_parent'        => $forum_parent_id,
			'forum_author'       => $forum->post_author,
			'last_topic_id'      => 0,
			'last_reply_id'      => 0,
			'last_active_id'     => 0,
			'last_active_time'   => 0,
			'last_active_status' => psf_get_public_status_id()
		) );

		// If the new forum parent id is not equal to the old forum parent
		// id, run the psf_move_forum action and pass the forum's parent id
		// as the first arg and new forum parent id as the second.
		// @todo implement
		//if ( $forum_id !== $forum->post_parent )
		//	psf_move_forum_handler( $forum_parent_id, $forum->post_parent, $forum_id );

		/** Additional Actions (After Save) ***********************************/

		do_action( 'psf_edit_forum_post_extras', $forum_id );

		/** Redirect **********************************************************/

		// Redirect to
		$redirect_to = psf_get_redirect_to();

		// View all?
		$view_all = psf_get_view_all();

		// Get the forum URL
		$forum_url = psf_get_forum_permalink( $forum_id, $redirect_to );

		// Add view all?
		if ( !empty( $view_all ) )
			$forum_url = psf_add_view_all( $forum_url );

		// Allow to be filtered
		$forum_url = apply_filters( 'psf_edit_forum_redirect_to', $forum_url, $view_all, $redirect_to );

		/** Successful Edit ***************************************************/

		// Redirect back to new forum
		wp_safe_redirect( $forum_url );

		// For good measure
		exit();

	/** Errors ****************************************************************/

	} else {
		$append_error = ( is_wp_error( $forum_id ) && $forum_id->get_error_message() ) ? $forum_id->get_error_message() . ' ' : '';
		psf_add_error( 'psf_forum_error', __( '<strong>FEHLER</strong>: Die folgenden Probleme wurden in Deinem Forum gefunden:' . $append_error . 'Bitte versuche erneut.', 'psforum' ) );
	}
}

/**
 * Handle the saving of core forum metadata (Status, Visibility, and Type)
 *
 * @since PSForum (r3678)
 * @param int $forum_id
 * @uses psf_is_forum_closed() To check if forum is closed
 * @uses psf_close_forum() To close forum
 * @uses psf_open_forum() To open forum
 * @uses psf_is_forum_category() To check if forum is a category
 * @uses psf_categorize_forum() To turn forum into a category
 * @uses psf_normalize_forum() To turn category into forum
 * @uses psf_get_public_status_id() To get the public status ID
 * @uses psf_get_private_status_id() To get the private status ID
 * @uses psf_get_hidden_status_id() To get the hidden status ID
 * @uses psf_get_forum_visibility() To get the forums visibility
 * @uses psf_hide_forum() To hide a forum
 * @uses psf_privatize_forum() To make a forum private
 * @uses psf_publicize_forum() To make a forum public
 * @return If forum ID is empty
 */
function psf_save_forum_extras( $forum_id = 0 ) {

	// Validate the forum ID
	$forum_id = psf_get_forum_id( $forum_id );

	// Bail if forum ID is empty
	if ( empty( $forum_id ) || ! psf_is_forum( $forum_id ) )
		return;

	/** Forum Status ******************************************************/

	if ( !empty( $_POST['psf_forum_status'] ) && in_array( $_POST['psf_forum_status'], array( 'open', 'closed' ) ) ) {
		if ( 'closed' === $_POST['psf_forum_status'] && !psf_is_forum_closed( $forum_id, false ) ) {
			psf_close_forum( $forum_id );
		} elseif ( 'open' === $_POST['psf_forum_status'] && psf_is_forum_closed( $forum_id, false ) ) {
			psf_open_forum( $forum_id );
		}
	}

	/** Forum Type ********************************************************/

	if ( !empty( $_POST['psf_forum_type'] ) && in_array( $_POST['psf_forum_type'], array( 'forum', 'category' ) ) ) {
		if ( 'category' === $_POST['psf_forum_type'] && !psf_is_forum_category( $forum_id ) ) {
			psf_categorize_forum( $forum_id );
		} elseif ( 'forum' === $_POST['psf_forum_type'] && psf_is_forum_category( $forum_id ) ) {
			psf_normalize_forum( $forum_id );
		}
	}

	/** Forum Visibility **************************************************/

	if ( !empty( $_POST['psf_forum_visibility'] ) && in_array( $_POST['psf_forum_visibility'], array( psf_get_public_status_id(), psf_get_private_status_id(), psf_get_hidden_status_id() ) ) ) {

		// Get forums current visibility
		$visibility = psf_get_forum_visibility( $forum_id );

		// What is the new forum visibility setting?
		switch ( $_POST['psf_forum_visibility'] ) {

			// Hidden
			case psf_get_hidden_status_id()  :
				psf_hide_forum( $forum_id, $visibility );
				break;

			// Private
			case psf_get_private_status_id() :
				psf_privatize_forum( $forum_id, $visibility );
				break;

			// Publish (default)
			case psf_get_public_status_id()  :
			default        :
				psf_publicize_forum( $forum_id, $visibility );
				break;
		}
	}
}

/** Forum Actions *************************************************************/

/**
 * Closes a forum
 *
 * @since PSForum (r2746)
 *
 * @param int $forum_id forum id
 * @uses do_action() Calls 'psf_close_forum' with the forum id
 * @uses update_post_meta() To add the previous status to a meta
 * @uses do_action() Calls 'psf_opened_forum' with the forum id
 * @return mixed False or {@link WP_Error} on failure, forum id on success
 */
function psf_close_forum( $forum_id = 0 ) {

	$forum_id = psf_get_forum_id( $forum_id );

	do_action( 'psf_close_forum',  $forum_id );

	update_post_meta( $forum_id, '_psf_status', 'closed' );

	do_action( 'psf_closed_forum', $forum_id );

	return $forum_id;
}

/**
 * Opens a forum
 *
 * @since PSForum (r2746)
 *
 * @param int $forum_id forum id
 * @uses do_action() Calls 'psf_open_forum' with the forum id
 * @uses get_post_meta() To get the previous status
 * @uses update_post_meta() To delete the previous status meta
 * @uses do_action() Calls 'psf_opened_forum' with the forum id
 * @return mixed False or {@link WP_Error} on failure, forum id on success
 */
function psf_open_forum( $forum_id = 0 ) {

	$forum_id = psf_get_forum_id( $forum_id );

	do_action( 'psf_open_forum',   $forum_id );

	update_post_meta( $forum_id, '_psf_status', 'open' );

	do_action( 'psf_opened_forum', $forum_id );

	return $forum_id;
}

/**
 * Make the forum a category
 *
 * @since PSForum (r2746)
 *
 * @param int $forum_id Optional. Forum id
 * @uses update_post_meta() To update the forum category meta
 * @return bool False on failure, true on success
 */
function psf_categorize_forum( $forum_id = 0 ) {

	$forum_id = psf_get_forum_id( $forum_id );

	do_action( 'psf_categorize_forum',  $forum_id );

	update_post_meta( $forum_id, '_psf_forum_type', 'category' );

	do_action( 'psf_categorized_forum', $forum_id );

	return $forum_id;
}

/**
 * Remove the category status from a forum
 *
 * @since PSForum (r2746)
 *
 * @param int $forum_id Optional. Forum id
 * @uses delete_post_meta() To delete the forum category meta
 * @return bool False on failure, true on success
 */
function psf_normalize_forum( $forum_id = 0 ) {

	$forum_id = psf_get_forum_id( $forum_id );

	do_action( 'psf_normalize_forum',  $forum_id );

	update_post_meta( $forum_id, '_psf_forum_type', 'forum' );

	do_action( 'psf_normalized_forum', $forum_id );

	return $forum_id;
}

/**
 * Mark the forum as public
 *
 * @since PSForum (r2746)
 *
 * @param int $forum_id Optional. Forum id
 * @uses update_post_meta() To update the forum private meta
 * @return bool False on failure, true on success
 */
function psf_publicize_forum( $forum_id = 0, $current_visibility = '' ) {

	$forum_id = psf_get_forum_id( $forum_id );

	do_action( 'psf_publicize_forum',  $forum_id );

	// Get private forums
	$private = psf_get_private_forum_ids();

	// Find this forum in the array
	if ( in_array( $forum_id, $private ) ) {

		$offset = array_search( $forum_id, $private );

		// Splice around it
		array_splice( $private, $offset, 1 );

		// Update private forums minus this one
		update_option( '_psf_private_forums', array_unique( array_filter( array_values( $private ) ) ) );
	}

	// Get hidden forums
	$hidden = psf_get_hidden_forum_ids();

	// Find this forum in the array
	if ( in_array( $forum_id, $hidden ) ) {

		$offset = array_search( $forum_id, $hidden );

		// Splice around it
		array_splice( $hidden, $offset, 1 );

		// Update hidden forums minus this one
		update_option( '_psf_hidden_forums', array_unique( array_filter( array_values( $hidden ) ) ) );
	}

	// Only run queries if visibility is changing
	if ( psf_get_public_status_id() !== $current_visibility ) {

		// Update forums visibility setting
		global $wpdb;
		$wpdb->update( $wpdb->posts, array( 'post_status' => psf_get_public_status_id() ), array( 'ID' => $forum_id ) );
		wp_transition_post_status( psf_get_public_status_id(), $current_visibility, get_post( $forum_id ) );
		psf_clean_post_cache( $forum_id );
	}

	do_action( 'psf_publicized_forum', $forum_id );

	return $forum_id;
}

/**
 * Mark the forum as private
 *
 * @since PSForum (r2746)
 *
 * @param int $forum_id Optional. Forum id
 * @uses update_post_meta() To update the forum private meta
 * @return bool False on failure, true on success
 */
function psf_privatize_forum( $forum_id = 0, $current_visibility = '' ) {

	$forum_id = psf_get_forum_id( $forum_id );

	do_action( 'psf_privatize_forum',  $forum_id );

	// Only run queries if visibility is changing
	if ( psf_get_private_status_id() !== $current_visibility ) {

		// Get hidden forums
		$hidden = psf_get_hidden_forum_ids();

		// Find this forum in the array
		if ( in_array( $forum_id, $hidden ) ) {

			$offset = array_search( $forum_id, $hidden );

			// Splice around it
			array_splice( $hidden, $offset, 1 );

			// Update hidden forums minus this one
			update_option( '_psf_hidden_forums', array_unique( array_filter( array_values( $hidden ) ) ) );
		}

		// Add to '_psf_private_forums' site option
		$private   = psf_get_private_forum_ids();
		$private[] = $forum_id;
		update_option( '_psf_private_forums', array_unique( array_filter( array_values( $private ) ) ) );

		// Update forums visibility setting
		global $wpdb;
		$wpdb->update( $wpdb->posts, array( 'post_status' => psf_get_private_status_id() ), array( 'ID' => $forum_id ) );
		wp_transition_post_status( psf_get_private_status_id(), $current_visibility, get_post( $forum_id ) );
		psf_clean_post_cache( $forum_id );
	}

	do_action( 'psf_privatized_forum', $forum_id );

	return $forum_id;
}

/**
 * Mark the forum as hidden
 *
 * @since PSForum (r2996)
 *
 * @param int $forum_id Optional. Forum id
 * @uses update_post_meta() To update the forum private meta
 * @return bool False on failure, true on success
 */
function psf_hide_forum( $forum_id = 0, $current_visibility = '' ) {

	$forum_id = psf_get_forum_id( $forum_id );

	do_action( 'psf_hide_forum', $forum_id );

	// Only run queries if visibility is changing
	if ( psf_get_hidden_status_id() !== $current_visibility ) {

		// Get private forums
		$private = psf_get_private_forum_ids();

		// Find this forum in the array
		if ( in_array( $forum_id, $private ) ) {

			$offset = array_search( $forum_id, $private );

			// Splice around it
			array_splice( $private, $offset, 1 );

			// Update private forums minus this one
			update_option( '_psf_private_forums', array_unique( array_filter( array_values( $private ) ) ) );
		}

		// Add to '_psf_hidden_forums' site option
		$hidden   = psf_get_hidden_forum_ids();
		$hidden[] = $forum_id;
		update_option( '_psf_hidden_forums', array_unique( array_filter( array_values( $hidden ) ) ) );

		// Update forums visibility setting
		global $wpdb;
		$wpdb->update( $wpdb->posts, array( 'post_status' => psf_get_hidden_status_id() ), array( 'ID' => $forum_id ) );
		wp_transition_post_status( psf_get_hidden_status_id(), $current_visibility, get_post( $forum_id ) );
		psf_clean_post_cache( $forum_id );
	}

	do_action( 'psf_hid_forum',  $forum_id );

	return $forum_id;
}

/**
 * Recaches the private and hidden forums
 *
 * @since PSForum (r5017)
 *
 * @uses delete_option() to delete private and hidden forum pointers
 * @uses WP_Query() To query post IDs
 * @uses is_wp_error() To return if error occurred
 * @uses update_option() To update the private and hidden post ID pointers
 * @return array An array of the status code and the message
 */
function psf_repair_forum_visibility() {

	// First, delete everything.
	delete_option( '_psf_private_forums' );
	delete_option( '_psf_hidden_forums'  );

	// Next, get all the private and hidden forums
	$private_forums = new WP_Query( array(
		'suppress_filters' => true,
		'nopaging'         => true,
		'post_type'        => psf_get_forum_post_type(),
		'post_status'      => psf_get_private_status_id(),
		'fields'           => 'ids'
	) );
	$hidden_forums = new WP_Query( array(
		'suppress_filters' => true,
		'nopaging'         => true,
		'post_type'        => psf_get_forum_post_type(),
		'post_status'      => psf_get_hidden_status_id(),
		'fields'           => 'ids'
	) );

	// Reset the $post global
	wp_reset_postdata();

	// Bail if queries returned errors
	if ( is_wp_error( $private_forums ) || is_wp_error( $hidden_forums ) )
		return false;

	// Update the private/hidden options
	update_option( '_psf_private_forums', $private_forums->posts ); // Private forums
	update_option( '_psf_hidden_forums',  $hidden_forums->posts  ); // Hidden forums

	// Complete results
	return true;
}

/** Subscriptions *************************************************************/

/**
 * Remove a deleted forum from all users' subscriptions
 *
 * @since PSForum (r5156)
 *
 * @param int $forum_id Get the forum ID to remove
 * @uses psf_is_subscriptions_active() To check if the subscriptions are active
 * @uses psf_get_forum_id To get the forum id
 * @uses psf_get_forum_subscribers() To get the forum subscribers
 * @uses psf_remove_user_subscription() To remove the user subscription
 */
function psf_remove_forum_from_all_subscriptions( $forum_id = 0 ) {

	// Subscriptions are not active
	if ( ! psf_is_subscriptions_active() ) {
		return;
	}

	$forum_id = psf_get_forum_id( $forum_id );

	// Bail if no forum
	if ( empty( $forum_id ) ) {
		return;
	}

	// Get users
	$users = (array) psf_get_forum_subscribers( $forum_id );

	// Users exist
	if ( !empty( $users ) ) {

		// Loop through users
		foreach ( $users as $user ) {

			// Remove each user
			psf_remove_user_subscription( $user, $forum_id );
		}
	}
}

/** Count Bumpers *************************************************************/

/**
 * Bump the total topic count of a forum
 *
 * @since PSForum (r3825)
 *
 * @param int $forum_id Optional. Forum id.
 * @param int $difference Optional. Default 1
 * @param bool $update_ancestors Optional. Default true
 * @uses psf_get_forum_id() To get the forum id
 * @uses update_post_meta() To update the forum's topic count meta
 * @uses apply_filters() Calls 'psf_bump_forum_topic_count' with the topic
 *                        count, forum id, and difference
 * @return int Forum topic count
 */
function psf_bump_forum_topic_count( $forum_id = 0, $difference = 1, $update_ancestors = true ) {

	// Get some counts
	$forum_id          = psf_get_forum_id( $forum_id );
	$topic_count       = psf_get_forum_topic_count( $forum_id, false, true );
	$total_topic_count = psf_get_forum_topic_count( $forum_id, true,  true );

	// Update this forum id
	update_post_meta( $forum_id, '_psf_topic_count',       (int) $topic_count       + (int) $difference );
	update_post_meta( $forum_id, '_psf_total_topic_count', (int) $total_topic_count + (int) $difference );

	// Check for ancestors
	if ( true === $update_ancestors ) {

		// Get post ancestors
		$forum     = get_post( $forum_id );
		$ancestors = get_post_ancestors( $forum );

		// If has ancestors, loop through them...
		if ( !empty( $ancestors ) ) {
			foreach ( (array) $ancestors as $parent_forum_id ) {

				// Get forum counts
				$parent_topic_count       = psf_get_forum_topic_count( $parent_forum_id, false, true );
				$parent_total_topic_count = psf_get_forum_topic_count( $parent_forum_id, true,  true );

				// Update counts
				update_post_meta( $parent_forum_id, '_psf_topic_count',       (int) $parent_topic_count       + (int) $difference );
				update_post_meta( $parent_forum_id, '_psf_total_topic_count', (int) $parent_total_topic_count + (int) $difference );
			}
		}
	}

	return (int) apply_filters( 'psf_bump_forum_topic_count', (int) $total_topic_count + (int) $difference, $forum_id, (int) $difference, (bool) $update_ancestors );
}

/**
 * Bump the total hidden topic count of a forum
 *
 * @since PSForum (r3825)
 *
 * @param int $forum_id Optional. Forum id.
 * @param int $difference Optional. Default 1
 * @uses psf_get_forum_id() To get the forum id
 * @uses update_post_meta() To update the forum's topic count meta
 * @uses apply_filters() Calls 'psf_bump_forum_topic_count_hidden' with the
 *                        topic count, forum id, and difference
 * @return int Forum hidden topic count
 */
function psf_bump_forum_topic_count_hidden( $forum_id = 0, $difference = 1 ) {

	// Get some counts
	$forum_id    = psf_get_forum_id( $forum_id );
	$topic_count = psf_get_forum_topic_count_hidden( $forum_id, true );
	$new_count   = (int) $topic_count + (int) $difference;

	// Update this forum id
	update_post_meta( $forum_id, '_psf_topic_count_hidden', (int) $new_count );

	return (int) apply_filters( 'psf_bump_forum_topic_count_hidden', (int) $new_count, $forum_id, (int) $difference );
}

/**
 * Bump the total topic count of a forum
 *
 * @since PSForum (r3825)
 *
 * @param int $forum_id Optional. Forum id.
 * @param int $difference Optional. Default 1
 * @param bool $update_ancestors Optional. Default true
 * @uses psf_get_forum_id() To get the forum id
 * @uses update_post_meta() To update the forum's topic count meta
 * @uses apply_filters() Calls 'psf_bump_forum_reply_count' with the topic
 *                        count, forum id, and difference
 * @return int Forum topic count
 */
function psf_bump_forum_reply_count( $forum_id = 0, $difference = 1, $update_ancestors = true ) {

	// Get some counts
	$forum_id          = psf_get_forum_id( $forum_id );
	$topic_count       = psf_get_forum_reply_count( $forum_id, false, true );
	$total_reply_count = psf_get_forum_reply_count( $forum_id, true,  true );

	// Update this forum id
	update_post_meta( $forum_id, '_psf_reply_count',       (int) $topic_count       + (int) $difference );
	update_post_meta( $forum_id, '_psf_total_reply_count', (int) $total_reply_count + (int) $difference );

	// Check for ancestors
	if ( true === $update_ancestors ) {

		// Get post ancestors
		$forum     = get_post( $forum_id );
		$ancestors = get_post_ancestors( $forum );

		// If has ancestors, loop through them...
		if ( !empty( $ancestors ) ) {
			foreach ( (array) $ancestors as $parent_forum_id ) {

				// Get forum counts
				$parent_topic_count       = psf_get_forum_reply_count( $parent_forum_id, false, true );
				$parent_total_reply_count = psf_get_forum_reply_count( $parent_forum_id, true,  true );

				// Update counts
				update_post_meta( $parent_forum_id, '_psf_reply_count',       (int) $parent_topic_count       + (int) $difference );
				update_post_meta( $parent_forum_id, '_psf_total_reply_count', (int) $parent_total_reply_count + (int) $difference );
			}
		}
	}

	return (int) apply_filters( 'psf_bump_forum_reply_count', (int) $total_reply_count + (int) $difference, $forum_id, (int) $difference, (bool) $update_ancestors );
}

/** Forum Updaters ************************************************************/

/**
 * Update the forum last topic id
 *
 * @since PSForum (r2625)
 *
 * @param int $forum_id Optional. Forum id
 * @param int $topic_id Optional. Topic id
 * @uses psf_get_forum_id() To get the forum id
 * @uses psf_forum_query_subforum_ids() To get the subforum ids
 * @uses psf_update_forum_last_topic_id() To update the last topic id of child
 *                                         forums
 * @uses get_posts() To get the most recent topic in the forum
 * @uses update_post_meta() To update the forum's last active id meta
 * @uses apply_filters() Calls 'psf_update_forum_last_topic_id' with the last
 *                        reply id and forum id
 * @return bool True on success, false on failure
 */
function psf_update_forum_last_topic_id( $forum_id = 0, $topic_id = 0 ) {
	$forum_id = psf_get_forum_id( $forum_id );

	// Define local variable(s)
	$children_last_topic = 0;

	// Do some calculation if not manually set
	if ( empty( $topic_id ) ) {

		// Loop through children and add together forum reply counts
		$children = psf_forum_query_subforum_ids( $forum_id );
		if ( !empty( $children ) ) {
			foreach ( (array) $children as $child ) {
				$children_last_topic = psf_update_forum_last_topic_id( $child ); // Recursive
			}
		}

		// Setup recent topic query vars
		$post_vars = array(
			'post_parent' => $forum_id,
			'post_type'   => psf_get_topic_post_type(),
			'meta_key'    => '_psf_last_active_time',
			'orderby'     => 'meta_value',
			'numberposts' => 1
		);

		// Get the most recent topic in this forum_id
		$recent_topic = get_posts( $post_vars );
		if ( !empty( $recent_topic ) ) {
			$topic_id = $recent_topic[0]->ID;
		}
	}

	// Cast as integer in case of empty or string
	$topic_id            = (int) $topic_id;
	$children_last_topic = (int) $children_last_topic;

	// If child forums have higher id, use that instead
	if ( !empty( $children ) && ( $children_last_topic > $topic_id ) )
		$topic_id = $children_last_topic;

	// Update the last public topic ID
	if ( psf_is_topic_published( $topic_id ) )
		update_post_meta( $forum_id, '_psf_last_topic_id', $topic_id );

	return (int) apply_filters( 'psf_update_forum_last_topic_id', $topic_id, $forum_id );
}

/**
 * Update the forum last reply id
 *
 * @since PSForum (r2625)
 *
 * @param int $forum_id Optional. Forum id
 * @param int $reply_id Optional. Reply id
 * @uses psf_get_forum_id() To get the forum id
 * @uses psf_forum_query_subforum_ids() To get the subforum ids
 * @uses psf_update_forum_last_reply_id() To update the last reply id of child
 *                                         forums
 * @uses psf_forum_query_topic_ids() To get the topic ids in the forum
 * @uses psf_forum_query_last_reply_id() To get the forum's last reply id
 * @uses psf_is_reply_published() To make sure the reply is published
 * @uses update_post_meta() To update the forum's last active id meta
 * @uses apply_filters() Calls 'psf_update_forum_last_reply_id' with the last
 *                        reply id and forum id
 * @return bool True on success, false on failure
 */
function psf_update_forum_last_reply_id( $forum_id = 0, $reply_id = 0 ) {
	$forum_id = psf_get_forum_id( $forum_id );

	// Define local variable(s)
	$children_last_reply = 0;

	// Do some calculation if not manually set
	if ( empty( $reply_id ) ) {

		// Loop through children and get the most recent reply id
		$children = psf_forum_query_subforum_ids( $forum_id );
		if ( !empty( $children ) ) {
			foreach ( (array) $children as $child ) {
				$children_last_reply = psf_update_forum_last_reply_id( $child ); // Recursive
			}
		}

		// If this forum has topics...
		$topic_ids = psf_forum_query_topic_ids( $forum_id );
		if ( !empty( $topic_ids ) ) {

			// ...get the most recent reply from those topics...
			$reply_id = psf_forum_query_last_reply_id( $forum_id, $topic_ids );

			// ...and compare it to the most recent topic id...
			$reply_id = ( $reply_id > max( $topic_ids ) ) ? $reply_id : max( $topic_ids );
		}
	}

	// Cast as integer in case of empty or string
	$reply_id            = (int) $reply_id;
	$children_last_reply = (int) $children_last_reply;

	// If child forums have higher ID, check for newer reply id
	if ( !empty( $children ) && ( $children_last_reply > $reply_id ) )
		$reply_id = $children_last_reply;

	// Update the last public reply ID
	if ( psf_is_reply_published( $reply_id ) )
		update_post_meta( $forum_id, '_psf_last_reply_id', $reply_id );

	return (int) apply_filters( 'psf_update_forum_last_reply_id', $reply_id, $forum_id );
}

/**
 * Update the forum last active post id
 *
 * @since PSForum (r2860)
 *
 * @param int $forum_id Optional. Forum id
 * @param int $active_id Optional. Active post id
 * @uses psf_get_forum_id() To get the forum id
 * @uses psf_forum_query_subforum_ids() To get the subforum ids
 * @uses psf_update_forum_last_active_id() To update the last active id of
 *                                          child forums
 * @uses psf_forum_query_topic_ids() To get the topic ids in the forum
 * @uses psf_forum_query_last_reply_id() To get the forum's last reply id
 * @uses get_post_status() To make sure the reply is published
 * @uses update_post_meta() To update the forum's last active id meta
 * @uses apply_filters() Calls 'psf_update_forum_last_active_id' with the last
 *                        active post id and forum id
 * @return bool True on success, false on failure
 */
function psf_update_forum_last_active_id( $forum_id = 0, $active_id = 0 ) {

	$forum_id = psf_get_forum_id( $forum_id );

	// Define local variable(s)
	$children_last_active = 0;

	// Do some calculation if not manually set
	if ( empty( $active_id ) ) {

		// Loop through children and add together forum reply counts
		$children = psf_forum_query_subforum_ids( $forum_id );
		if ( !empty( $children ) ) {
			foreach ( (array) $children as $child ) {
				$children_last_active = psf_update_forum_last_active_id( $child, $active_id );
			}
		}

		// Don't count replies if the forum is a category
		$topic_ids = psf_forum_query_topic_ids( $forum_id );
		if ( !empty( $topic_ids ) ) {
			$active_id = psf_forum_query_last_reply_id( $forum_id, $topic_ids );
			$active_id = $active_id > max( $topic_ids ) ? $active_id : max( $topic_ids );

		// Forum has no topics
		} else {
			$active_id = 0;
		}
	}

	// Cast as integer in case of empty or string
	$active_id            = (int) $active_id;
	$children_last_active = (int) $children_last_active;

	// If child forums have higher id, use that instead
	if ( !empty( $children ) && ( $children_last_active > $active_id ) )
		$active_id = $children_last_active;

	// Update only if published
	if ( psf_get_public_status_id() === get_post_status( $active_id ) )
		update_post_meta( $forum_id, '_psf_last_active_id', (int) $active_id );

	return (int) apply_filters( 'psf_update_forum_last_active_id', (int) $active_id, $forum_id );
}

/**
 * Update the forums last active date/time (aka freshness)
 *
 * @since PSForum (r2680)
 *
 * @param int $forum_id Optional. Topic id
 * @param string $new_time Optional. New time in mysql format
 * @uses psf_get_forum_id() To get the forum id
 * @uses psf_get_forum_last_active_id() To get the forum's last post id
 * @uses get_post_field() To get the post date of the forum's last post
 * @uses update_post_meta() To update the forum last active time
 * @uses apply_filters() Calls 'psf_update_forum_last_active' with the new time
 *                        and forum id
 * @return bool True on success, false on failure
 */
function psf_update_forum_last_active_time( $forum_id = 0, $new_time = '' ) {
	$forum_id = psf_get_forum_id( $forum_id );

	// Check time and use current if empty
	if ( empty( $new_time ) )
		$new_time = get_post_field( 'post_date', psf_get_forum_last_active_id( $forum_id ) );

	// Update only if there is a time
	if ( !empty( $new_time ) )
		update_post_meta( $forum_id, '_psf_last_active_time', $new_time );

	return (int) apply_filters( 'psf_update_forum_last_active', $new_time, $forum_id );
}

/**
 * Update the forum sub-forum count
 *
 * @since PSForum (r2625)
 *
 * @param int $forum_id Optional. Forum id
 * @uses psf_get_forum_id() To get the forum id
 * @return bool True on success, false on failure
 */
function psf_update_forum_subforum_count( $forum_id = 0, $subforums = 0 ) {
	$forum_id = psf_get_forum_id( $forum_id );

	if ( empty( $subforums ) )
		$subforums = count( psf_forum_query_subforum_ids( $forum_id ) );

	update_post_meta( $forum_id, '_psf_forum_subforum_count', (int) $subforums );

	return (int) apply_filters( 'psf_update_forum_subforum_count', (int) $subforums, $forum_id );
}

/**
 * Adjust the total topic count of a forum
 *
 * @since PSForum (r2464)
 *
 * @param int $forum_id Optional. Forum id or topic id. It is checked whether it
 *                       is a topic or a forum. If it's a topic, its parent,
 *                       i.e. the forum is automatically retrieved.
 * @param bool $total_count Optional. To return the total count or normal
 *                           count?
 * @uses psf_get_forum_id() To get the forum id
 * @uses psf_forum_query_subforum_ids() To get the subforum ids
 * @uses psf_update_forum_topic_count() To update the forum topic count
 * @uses psf_forum_query_topic_ids() To get the forum topic ids
 * @uses update_post_meta() To update the forum's topic count meta
 * @uses apply_filters() Calls 'psf_update_forum_topic_count' with the topic
 *                        count and forum id
 * @return int Forum topic count
 */
function psf_update_forum_topic_count( $forum_id = 0 ) {
	$forum_id = psf_get_forum_id( $forum_id );
	$children_topic_count = 0;

	// Loop through subforums and add together forum topic counts
	$children = psf_forum_query_subforum_ids( $forum_id );
	if ( !empty( $children ) ) {
		foreach ( (array) $children as $child ) {
			$children_topic_count += psf_update_forum_topic_count( $child ); // Recursive
		}
	}

	// Get total topics for this forum
	$topics = (int) count( psf_forum_query_topic_ids( $forum_id ) );

	// Calculate total topics in this forum
	$total_topics = $topics + $children_topic_count;

	// Update the count
	update_post_meta( $forum_id, '_psf_topic_count',       (int) $topics       );
	update_post_meta( $forum_id, '_psf_total_topic_count', (int) $total_topics );

	return (int) apply_filters( 'psf_update_forum_topic_count', (int) $total_topics, $forum_id );
}

/**
 * Adjust the total hidden topic count of a forum (hidden includes trashed and spammed topics)
 *
 * @since PSForum (r2888)
 *
 * @param int $forum_id Optional. Topic id to update
 * @param int $topic_count Optional. Set the topic count manually
 * @uses psf_is_topic() To check if the supplied id is a topic
 * @uses psf_get_topic_id() To get the topic id
 * @uses psf_get_topic_forum_id() To get the topic forum id
 * @uses psf_get_forum_id() To get the forum id
 * @uses wpdb::prepare() To prepare our sql query
 * @uses wpdb::get_col() To execute our query and get the column back
 * @uses update_post_meta() To update the forum hidden topic count meta
 * @uses apply_filters() Calls 'psf_update_forum_topic_count_hidden' with the
 *                        hidden topic count and forum id
 * @return int Topic hidden topic count
 */
function psf_update_forum_topic_count_hidden( $forum_id = 0, $topic_count = 0 ) {
	global $wpdb;

	// If topic_id was passed as $forum_id, then get its forum
	if ( psf_is_topic( $forum_id ) ) {
		$topic_id = psf_get_topic_id( $forum_id );
		$forum_id = psf_get_topic_forum_id( $topic_id );

	// $forum_id is not a topic_id, so validate and proceed
	} else {
		$forum_id = psf_get_forum_id( $forum_id );
	}

	// Can't update what isn't there
	if ( !empty( $forum_id ) ) {

		// Get topics of forum
		if ( empty( $topic_count ) ) {
			$post_status = "'" . implode( "','", array( psf_get_trash_status_id(), psf_get_spam_status_id() ) ) . "'";
			$topic_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_parent = %d AND post_status IN ( {$post_status} ) AND post_type = '%s';", $forum_id, psf_get_topic_post_type() ) );
		}

		// Update the count
		update_post_meta( $forum_id, '_psf_topic_count_hidden', (int) $topic_count );
	}

	return (int) apply_filters( 'psf_update_forum_topic_count_hidden', (int) $topic_count, $forum_id );
}

/**
 * Adjust the total reply count of a forum
 *
 * @since PSForum (r2464)
 *
 * @param int $forum_id Optional. Forum id or topic id. It is checked whether it
 *                       is a topic or a forum. If it's a topic, its parent,
 *                       i.e. the forum is automatically retrieved.
 * @param bool $total_count Optional. To return the total count or normal
 *                           count?
 * @uses psf_get_forum_id() To get the forum id
 * @uses psf_forum_query_subforum_ids() To get the subforum ids
 * @uses psf_update_forum_reply_count() To update the forum reply count
 * @uses psf_forum_query_topic_ids() To get the forum topic ids
 * @uses wpdb::prepare() To prepare the sql statement
 * @uses wpdb::get_var() To execute the query and get the var back
 * @uses update_post_meta() To update the forum's reply count meta
 * @uses apply_filters() Calls 'psf_update_forum_topic_count' with the reply
 *                        count and forum id
 * @return int Forum reply count
 */
function psf_update_forum_reply_count( $forum_id = 0 ) {
	global $wpdb;

	$forum_id = psf_get_forum_id( $forum_id );
	$children_reply_count = 0;

	// Loop through children and add together forum reply counts
	$children = psf_forum_query_subforum_ids( $forum_id );
	if ( !empty( $children ) ) {
		foreach ( (array) $children as $child ) {
			$children_reply_count += psf_update_forum_reply_count( $child );
		}
	}

	// Don't count replies if the forum is a category
	$topic_ids = psf_forum_query_topic_ids( $forum_id );
	if ( !empty( $topic_ids ) ) {
		$topic_ids   = implode( ',', wp_parse_id_list( $topic_ids ) );
		$reply_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_parent IN ( {$topic_ids} ) AND post_status = '%s' AND post_type = '%s';", psf_get_public_status_id(), psf_get_reply_post_type() ) );
	} else {
		$reply_count = 0;
	}

	// Calculate total replies in this forum
	$total_replies = (int) $reply_count + $children_reply_count;

	// Update the count
	update_post_meta( $forum_id, '_psf_reply_count',       (int) $reply_count   );
	update_post_meta( $forum_id, '_psf_total_reply_count', (int) $total_replies );

	return (int) apply_filters( 'psf_update_forum_reply_count', (int) $total_replies, $forum_id );
}

/**
 * Updates the counts of a forum.
 *
 * This calls a few internal functions that all run manual queries against the
 * database to get their results. As such, this function can be costly to run
 * but is necessary to keep everything accurate.
 *
 * @since PSForum (r2908)
 *
 * @param mixed $args Supports these arguments:
 *  - forum_id: Forum id
 *  - last_topic_id: Last topic id
 *  - last_reply_id: Last reply id
 *  - last_active_id: Last active post id
 *  - last_active_time: last active time
 * @uses psf_update_forum_last_topic_id() To update the forum last topic id
 * @uses psf_update_forum_last_reply_id() To update the forum last reply id
 * @uses psf_update_forum_last_active_id() To update the last active post id
 * @uses get_post_field() To get the post date of the last active id
 * @uses psf_update_forum_last_active_time()  To update the last active time
 * @uses psf_update_forum_subforum_count() To update the subforum count
 * @uses psf_update_forum_topic_count() To update the forum topic count
 * @uses psf_update_forum_reply_count() To update the forum reply count
 * @uses psf_update_forum_topic_count_hidden() To update the hidden topic count
 */
function psf_update_forum( $args = '' ) {

	// Parse arguments against default values
	$r = psf_parse_args( $args, array(
		'forum_id'           => 0,
		'post_parent'        => 0,
		'last_topic_id'      => 0,
		'last_reply_id'      => 0,
		'last_active_id'     => 0,
		'last_active_time'   => 0,
		'last_active_status' => psf_get_public_status_id()
	), 'update_forum' );

	// Last topic and reply ID's
	psf_update_forum_last_topic_id( $r['forum_id'], $r['last_topic_id'] );
	psf_update_forum_last_reply_id( $r['forum_id'], $r['last_reply_id'] );

	// Active dance
	$r['last_active_id'] = psf_update_forum_last_active_id( $r['forum_id'], $r['last_active_id'] );

	// If no active time was passed, get it from the last_active_id
	if ( empty( $r['last_active_time'] ) ) {
		$r['last_active_time'] = get_post_field( 'post_date', $r['last_active_id'] );
	}

	if ( psf_get_public_status_id() === $r['last_active_status'] ) {
		psf_update_forum_last_active_time( $r['forum_id'], $r['last_active_time'] );
	}

	// Counts
	psf_update_forum_subforum_count    ( $r['forum_id'] );
	psf_update_forum_reply_count       ( $r['forum_id'] );
	psf_update_forum_topic_count       ( $r['forum_id'] );
	psf_update_forum_topic_count_hidden( $r['forum_id'] );

	// Update the parent forum if one was passed
	if ( !empty( $r['post_parent'] ) && is_numeric( $r['post_parent'] ) ) {
		psf_update_forum( array(
			'forum_id'    => $r['post_parent'],
			'post_parent' => get_post_field( 'post_parent', $r['post_parent'] )
		) );
	}
}

/** Helpers *******************************************************************/

/**
 * Return an associative array of available topic statuses
 *
 * @since PSForum (r5059)
 *
 * @return array
 */
function psf_get_forum_statuses() {
	return apply_filters( 'psf_get_forum_statuses', array(
		'open'   => _x( 'Offen',    'Open the forum',  'psforum' ),
		'closed' => _x( 'Geschlossen',  'Close the forum', 'psforum' )
	) );
}

/**
 * Return an associative array of forum types
 *
 * @since PSForum (r5059)
 *
 * @return array
 */
function psf_get_forum_types() {
	return apply_filters( 'psf_get_forum_types', array(
		'forum'    => _x( 'Forum',    'Forum akzeptiert neue Themen', 'psforum' ),
		'category' => _x( 'Kategorie', 'Forum ist eine Kategorie',      'psforum' )
	) );
}

/**
 * Return an associative array of forum visibility
 *
 * @since PSForum (r5059)
 *
 * @return array
 */
function psf_get_forum_visibilities() {
	return apply_filters( 'psf_get_forum_visibilities', array(
		psf_get_public_status_id()  => _x( 'Öffentlich',  'Make forum public',  'psforum' ),
		psf_get_private_status_id() => _x( 'Privat', 'Make forum private', 'psforum' ),
		psf_get_hidden_status_id()  => _x( 'Versteckt',  'Make forum hidden',  'psforum' )
	) );
}

/** Queries *******************************************************************/

/**
 * Returns the hidden forum ids
 *
 * Only hidden forum ids are returned. Public and private ids are not.
 *
 * @since PSForum (r3007)
 *
 * @uses get_option() Returns the unserialized array of hidden forum ids
 * @uses apply_filters() Calls 'psf_forum_query_topic_ids' with the topic ids
 *                        and forum id
 */
function psf_get_hidden_forum_ids() {
   	$forum_ids = get_option( '_psf_hidden_forums', array() );

	return apply_filters( 'psf_get_hidden_forum_ids', (array) $forum_ids );
}

/**
 * Returns the private forum ids
 *
 * Only private forum ids are returned. Public and hidden ids are not.
 *
 * @since PSForum (r3007)
 *
 * @uses get_option() Returns the unserialized array of private forum ids
 * @uses apply_filters() Calls 'psf_forum_query_topic_ids' with the topic ids
 *                        and forum id
 */
function psf_get_private_forum_ids() {
   	$forum_ids = get_option( '_psf_private_forums', array() );

	return apply_filters( 'psf_get_private_forum_ids', (array) $forum_ids );
}

/**
 * Returns a meta_query that either includes or excludes hidden forum IDs
 * from a query.
 *
 * @since PSForum (r3291)
 *
 * @param string Optional. The type of value to return. (string|array|meta_query)
 *
 * @uses psf_is_user_keymaster()
 * @uses psf_get_hidden_forum_ids()
 * @uses psf_get_private_forum_ids()
 * @uses apply_filters()
 */
function psf_exclude_forum_ids( $type = 'string' ) {

	// Setup arrays
	$private = $hidden = $meta_query = $forum_ids = array();

	// Default return value
	switch ( $type ) {
		case 'string' :
			$retval = '';
			break;

		case 'array'  :
			$retval = array();
			break;

		case 'meta_query' :
			$retval = array( array() ) ;
			break;
	}

	// Exclude for everyone but keymasters
	if ( ! psf_is_user_keymaster() ) {

		// Private forums
		if ( !current_user_can( 'read_private_forums' ) )
			$private = psf_get_private_forum_ids();

		// Hidden forums
		if ( !current_user_can( 'read_hidden_forums' ) )
			$hidden  = psf_get_hidden_forum_ids();

		// Merge private and hidden forums together
		$forum_ids = (array) array_filter( wp_parse_id_list( array_merge( $private, $hidden ) ) );

		// There are forums that need to be excluded
		if ( !empty( $forum_ids ) ) {

			switch ( $type ) {

				// Separate forum ID's into a comma separated string
				case 'string' :
					$retval = implode( ',', $forum_ids );
					break;

				// Use forum_ids array
				case 'array'  :
					$retval = $forum_ids;
					break;

				// Build a meta_query
				case 'meta_query' :
					$retval = array(
						'key'     => '_psf_forum_id',
						'value'   => implode( ',', $forum_ids ),
						'type'    => 'numeric',
						'compare' => ( 1 < count( $forum_ids ) ) ? 'NOT IN' : '!='
					);
					break;
			}
		}
	}

	// Filter and return the results
	return apply_filters( 'psf_exclude_forum_ids', $retval, $forum_ids, $type );
}

/**
 * Adjusts forum, topic, and reply queries to exclude items that might be
 * contained inside hidden or private forums that the user does not have the
 * capability to view.
 *
 * Doing it with an action allows us to trap all WP_Query's rather than needing
 * to hardcode this logic into each query. It also protects forum content for
 * plugins that might be doing their own queries.
 *
 * @since PSForum (r3291)
 *
 * @param WP_Query $posts_query
 *
 * @uses apply_filters()
 * @uses psf_exclude_forum_ids()
 * @uses psf_get_topic_post_type()
 * @uses psf_get_reply_post_type()
 * @return WP_Query
 */
function psf_pre_get_posts_normalize_forum_visibility( $posts_query = null ) {

	// Bail if all forums are explicitly allowed
	if ( true === apply_filters( 'psf_include_all_forums', false, $posts_query ) ) {
		return;
	}

	// Bail if $posts_query is not an object or of incorrect class
	if ( !is_object( $posts_query ) || !is_a( $posts_query, 'WP_Query' ) ) {
		return;
	}

	// Get query post types array .
	$post_types = (array) $posts_query->get( 'post_type' );

	// Forums
	if ( psf_get_forum_post_type() === implode( '', $post_types ) ) {

		// Prevent accidental wp-admin post_row override
		if ( is_admin() && isset( $_REQUEST['post_status'] ) ) {
			return;
		}

		/** Default ***********************************************************/

		// Get any existing post status
		$post_stati = $posts_query->get( 'post_status' );

		// Default to public status
		if ( empty( $post_stati ) ) {
			$post_stati = array( psf_get_public_status_id() );

		// Split the status string
		} elseif ( is_string( $post_stati ) ) {
			$post_stati = explode( ',', $post_stati );
		}

		/** Private ***********************************************************/

		// Remove psf_get_private_status_id() if user is not capable
		if ( ! current_user_can( 'read_private_forums' ) ) {
			$key = array_search( psf_get_private_status_id(), $post_stati );
			if ( !empty( $key ) ) {
				unset( $post_stati[$key] );
			}

		// ...or add it if they are
		} else {
			$post_stati[] = psf_get_private_status_id();
		}

		/** Hidden ************************************************************/

		// Remove psf_get_hidden_status_id() if user is not capable
		if ( ! current_user_can( 'read_hidden_forums' ) ) {
			$key = array_search( psf_get_hidden_status_id(), $post_stati );
			if ( !empty( $key ) ) {
				unset( $post_stati[$key] );
			}

		// ...or add it if they are
		} else {
			$post_stati[] = psf_get_hidden_status_id();
		}

		// Add the statuses
		$posts_query->set( 'post_status', array_unique( array_filter( $post_stati ) ) );
	}

	// Topics Or Replies
	if ( array_intersect( array( psf_get_topic_post_type(), psf_get_reply_post_type() ), $post_types ) ) {

		// Get forums to exclude
		$forum_ids = psf_exclude_forum_ids( 'meta_query' );

		// Bail if no forums to exclude
		if ( ! array_filter( $forum_ids ) ) {
			return;
		}

		// Get any existing meta queries
		$meta_query   = (array) $posts_query->get( 'meta_query', array() );

		// Add our meta query to existing
		$meta_query[] = $forum_ids;

		// Set the meta_query var
		$posts_query->set( 'meta_query', $meta_query );
	}
}

/**
 * Returns the forum's topic ids
 *
 * Only topics with published and closed statuses are returned
 *
 * @since PSForum (r2908)
 *
 * @param int $forum_id Forum id
 * @uses psf_get_topic_post_type() To get the topic post type
 * @uses psf_get_public_child_ids() To get the topic ids
 * @uses apply_filters() Calls 'psf_forum_query_topic_ids' with the topic ids
 *                        and forum id
 */
function psf_forum_query_topic_ids( $forum_id ) {
   	$topic_ids = psf_get_public_child_ids( $forum_id, psf_get_topic_post_type() );

	return apply_filters( 'psf_forum_query_topic_ids', $topic_ids, $forum_id );
}

/**
 * Returns the forum's subforum ids
 *
 * Only forums with published status are returned
 *
 * @since PSForum (r2908)
 *
 * @param int $forum_id Forum id
 * @uses psf_get_forum_post_type() To get the forum post type
 * @uses psf_get_public_child_ids() To get the forum ids
 * @uses apply_filters() Calls 'psf_forum_query_subforum_ids' with the subforum
 *                        ids and forum id
 */
function psf_forum_query_subforum_ids( $forum_id ) {
	$subforum_ids = psf_get_all_child_ids( $forum_id, psf_get_forum_post_type() );
	//usort( $subforum_ids, '_psf_forum_query_usort_subforum_ids' );

	return apply_filters( 'psf_get_forum_subforum_ids', $subforum_ids, $forum_id );
}

/**
 * Callback to sort forum ID's based on last active time
 *
 * @since PSForum (r3789)
 * @param int $a First forum ID to compare
 * @param int $b Second forum ID to compare
 * @return Position change based on sort
 */
function _psf_forum_query_usort_subforum_ids( $a = 0, $b = 0 ) {
	$ta = get_post_meta( $a, '_psf_last_active_time', true );
	$tb = get_post_meta( $b, '_psf_last_active_time', true );
	return ( $ta < $tb ) ? -1 : 1;
}

/**
 * Returns the forum's last reply id
 *
 * @since PSForum (r2908)
 *
 * @param int $forum_id Forum id
 * @param int $topic_ids Optional. Topic ids
 * @uses wp_cache_get() To check for cache and retrieve it
 * @uses psf_forum_query_topic_ids() To get the forum's topic ids
 * @uses wpdb::prepare() To prepare the query
 * @uses wpdb::get_var() To execute the query and get the var back
 * @uses psf_get_reply_post_type() To get the reply post type
 * @uses wp_cache_set() To set the cache for future use
 * @uses apply_filters() Calls 'psf_forum_query_last_reply_id' with the reply id
 *                        and forum id
 */
function psf_forum_query_last_reply_id( $forum_id, $topic_ids = 0 ) {
	global $wpdb;

	$cache_id = 'psf_get_forum_' . $forum_id . '_reply_id';
	$reply_id = wp_cache_get( $cache_id, 'psforum_posts' );

	if ( false === $reply_id ) {

		if ( empty( $topic_ids ) ) {
			$topic_ids = psf_forum_query_topic_ids( $forum_id );
		}

		if ( !empty( $topic_ids ) ) {
			$topic_ids = implode( ',', wp_parse_id_list( $topic_ids ) );
			$reply_id  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_parent IN ( {$topic_ids} ) AND post_status = '%s' AND post_type = '%s' ORDER BY ID DESC LIMIT 1;", psf_get_public_status_id(), psf_get_reply_post_type() ) );
			wp_cache_set( $cache_id, $reply_id, 'psforum_posts' ); // May be (int) 0
		} else {
			wp_cache_set( $cache_id, '0', 'psforum_posts' );
		}
	}

	return (int) apply_filters( 'psf_get_forum_last_reply_id', (int) $reply_id, $forum_id );
}

/** Listeners *****************************************************************/

/**
 * Check if it's a hidden forum or a topic or reply of a hidden forum and if
 * the user can't view it, then sets a 404
 *
 * @since PSForum (r2996)
 *
 * @uses current_user_can() To check if the current user can read private forums
 * @uses is_singular() To check if it's a singular page
 * @uses psf_is_user_keymaster() To check if user is a keymaster
 * @uses psf_get_forum_post_type() To get the forum post type
 * @uses psf_get_topic_post_type() To get the topic post type
 * @uses psf_get_reply_post_type() TO get the reply post type
 * @uses psf_get_topic_forum_id() To get the topic forum id
 * @uses psf_get_reply_forum_id() To get the reply forum id
 * @uses psf_is_forum_hidden() To check if the forum is hidden or not
 * @uses psf_set_404() To set a 404 status
 */
function psf_forum_enforce_hidden() {

	// Bail if not viewing a single item or if user has caps
	if ( !is_singular() || psf_is_user_keymaster() || current_user_can( 'read_hidden_forums' ) )
		return;

	global $wp_query;

	// Define local variable
	$forum_id = 0;

	// Check post type
	switch ( $wp_query->get( 'post_type' ) ) {

		// Forum
		case psf_get_forum_post_type() :
			$forum_id = psf_get_forum_id( $wp_query->post->ID );
			break;

		// Topic
		case psf_get_topic_post_type() :
			$forum_id = psf_get_topic_forum_id( $wp_query->post->ID );
			break;

		// Reply
		case psf_get_reply_post_type() :
			$forum_id = psf_get_reply_forum_id( $wp_query->post->ID );
			break;

	}

	// If forum is explicitly hidden and user not capable, set 404
	if ( !empty( $forum_id ) && psf_is_forum_hidden( $forum_id ) && !current_user_can( 'read_hidden_forums' ) )
		psf_set_404();
}

/**
 * Check if it's a private forum or a topic or reply of a private forum and if
 * the user can't view it, then sets a 404
 *
 * @since PSForum (r2996)
 *
 * @uses current_user_can() To check if the current user can read private forums
 * @uses is_singular() To check if it's a singular page
 * @uses psf_is_user_keymaster() To check if user is a keymaster
 * @uses psf_get_forum_post_type() To get the forum post type
 * @uses psf_get_topic_post_type() To get the topic post type
 * @uses psf_get_reply_post_type() TO get the reply post type
 * @uses psf_get_topic_forum_id() To get the topic forum id
 * @uses psf_get_reply_forum_id() To get the reply forum id
 * @uses psf_is_forum_private() To check if the forum is private or not
 * @uses psf_set_404() To set a 404 status
 */
function psf_forum_enforce_private() {

	// Bail if not viewing a single item or if user has caps
	if ( !is_singular() || psf_is_user_keymaster() || current_user_can( 'read_private_forums' ) )
		return;

	global $wp_query;

	// Define local variable
	$forum_id = 0;

	// Check post type
	switch ( $wp_query->get( 'post_type' ) ) {

		// Forum
		case psf_get_forum_post_type() :
			$forum_id = psf_get_forum_id( $wp_query->post->ID );
			break;

		// Topic
		case psf_get_topic_post_type() :
			$forum_id = psf_get_topic_forum_id( $wp_query->post->ID );
			break;

		// Reply
		case psf_get_reply_post_type() :
			$forum_id = psf_get_reply_forum_id( $wp_query->post->ID );
			break;

	}

	// If forum is explicitly hidden and user not capable, set 404
	if ( !empty( $forum_id ) && psf_is_forum_private( $forum_id ) && !current_user_can( 'read_private_forums' ) )
		psf_set_404();
}

/** Permissions ***************************************************************/

/**
 * Redirect if unathorized user is attempting to edit a forum
 *
 * @since PSForum (r3607)
 *
 * @uses psf_is_forum_edit()
 * @uses current_user_can()
 * @uses psf_get_forum_id()
 * @uses wp_safe_redirect()
 * @uses psf_get_forum_permalink()
 */
function psf_check_forum_edit() {

	// Bail if not editing a topic
	if ( !psf_is_forum_edit() )
		return;

	// User cannot edit topic, so redirect back to reply
	if ( !current_user_can( 'edit_forum', psf_get_forum_id() ) ) {
		wp_safe_redirect( psf_get_forum_permalink() );
		exit();
	}
}

/**
 * Delete all topics (and their replies) for a specific forum ID
 *
 * @since PSForum (r3668)
 *
 * @param int $forum_id
 * @uses psf_get_forum_id() To validate the forum ID
 * @uses psf_is_forum() To make sure it's a forum
 * @uses psf_get_topic_post_type() To get the topic post type
 * @uses psf_topics() To make sure there are topics to loop through
 * @uses wp_trash_post() To trash the post
 * @uses update_post_meta() To update the forum meta of trashed topics
 * @return If forum is not valid
 */
function psf_delete_forum_topics( $forum_id = 0 ) {

	// Validate forum ID
	$forum_id = psf_get_forum_id( $forum_id );
	if ( empty( $forum_id ) )
		return;

	// Forum is being permanently deleted, so its content has go too
	// Note that we get all post statuses here
	$topics = new WP_Query( array(
		'suppress_filters' => true,
		'post_type'        => psf_get_topic_post_type(),
		'post_parent'      => $forum_id,
		'post_status'      => array_keys( get_post_stati() ),
		'posts_per_page'   => -1,
		'nopaging'         => true,
		'fields'           => 'id=>parent'
	) );

	// Loop through and delete child topics. Topic replies will get deleted by
	// the psf_delete_topic() action.
	if ( !empty( $topics->posts ) ) {
		foreach ( $topics->posts as $topic ) {
			wp_delete_post( $topic->ID, true );
		}

		// Reset the $post global
		wp_reset_postdata();
	}

	// Cleanup
	unset( $topics );
}

/**
 * Trash all topics inside a forum
 *
 * @since PSForum (r3668)
 *
 * @param int $forum_id
 * @uses psf_get_forum_id() To validate the forum ID
 * @uses psf_is_forum() To make sure it's a forum
 * @uses psf_get_public_status_id() To return public post status
 * @uses psf_get_closed_status_id() To return closed post status
 * @uses psf_get_pending_status_id() To return pending post status
 * @uses psf_get_topic_post_type() To get the topic post type
 * @uses wp_trash_post() To trash the post
 * @uses update_post_meta() To update the forum meta of trashed topics
 * @return If forum is not valid
 */
function psf_trash_forum_topics( $forum_id = 0 ) {

	// Validate forum ID
	$forum_id = psf_get_forum_id( $forum_id );
	if ( empty( $forum_id ) )
		return;

	// Allowed post statuses to pre-trash
	$post_stati = implode( ',', array(
		psf_get_public_status_id(),
		psf_get_closed_status_id(),
		psf_get_pending_status_id()
	) );

	// Forum is being trashed, so its topics and replies are trashed too
	$topics = new WP_Query( array(
		'suppress_filters' => true,
		'post_type'        => psf_get_topic_post_type(),
		'post_parent'      => $forum_id,
		'post_status'      => $post_stati,
		'posts_per_page'   => -1,
		'nopaging'         => true,
		'fields'           => 'id=>parent'
	) );

	// Loop through and trash child topics. Topic replies will get trashed by
	// the psf_trash_topic() action.
	if ( !empty( $topics->posts ) ) {

		// Prevent debug notices
		$pre_trashed_topics = array();

		// Loop through topics, trash them, and add them to array
		foreach ( $topics->posts as $topic ) {
			wp_trash_post( $topic->ID, true );
			$pre_trashed_topics[] = $topic->ID;
		}

		// Set a post_meta entry of the topics that were trashed by this action.
		// This is so we can possibly untrash them, without untrashing topics
		// that were purposefully trashed before.
		update_post_meta( $forum_id, '_psf_pre_trashed_topics', $pre_trashed_topics );

		// Reset the $post global
		wp_reset_postdata();
	}

	// Cleanup
	unset( $topics );
}

/**
 * Trash all topics inside a forum
 *
 * @since PSForum (r3668)
 *
 * @param int $forum_id
 * @uses psf_get_forum_id() To validate the forum ID
 * @uses psf_is_forum() To make sure it's a forum
 * @uses get_post_meta() To update the forum meta of trashed topics
 * @uses wp_untrash_post() To trash the post
 * @return If forum is not valid
 */
function psf_untrash_forum_topics( $forum_id = 0 ) {

	// Validate forum ID
	$forum_id = psf_get_forum_id( $forum_id );

	if ( empty( $forum_id ) )
		return;

	// Get the topics that were not previously trashed
	$pre_trashed_topics = get_post_meta( $forum_id, '_psf_pre_trashed_topics', true );

	// There are topics to untrash
	if ( !empty( $pre_trashed_topics ) ) {

		// Maybe reverse the trashed topics array
		if ( is_array( $pre_trashed_topics ) )
			$pre_trashed_topics = array_reverse( $pre_trashed_topics );

		// Loop through topics
		foreach ( (array) $pre_trashed_topics as $topic ) {
			wp_untrash_post( $topic );
		}
	}
}

/** Before Delete/Trash/Untrash ***********************************************/

/**
 * Called before deleting a forum.
 *
 * This function is supplemental to the actual forum deletion which is
 * handled by WordPress core API functions. It is used to clean up after
 * a forum that is being deleted.
 *
 * @since PSForum (r3668)
 * @uses psf_get_forum_id() To get the forum id
 * @uses psf_is_forum() To check if the passed id is a forum
 * @uses do_action() Calls 'psf_delete_forum' with the forum id
 */
function psf_delete_forum( $forum_id = 0 ) {
	$forum_id = psf_get_forum_id( $forum_id );

	if ( empty( $forum_id ) || !psf_is_forum( $forum_id ) )
		return false;

	do_action( 'psf_delete_forum', $forum_id );
}

/**
 * Called before trashing a forum
 *
 * This function is supplemental to the actual forum being trashed which is
 * handled by WordPress core API functions. It is used to clean up after
 * a forum that is being trashed.
 *
 * @since PSForum (r3668)
 * @uses psf_get_forum_id() To get the forum id
 * @uses psf_is_forum() To check if the passed id is a forum
 * @uses do_action() Calls 'psf_trash_forum' with the forum id
 */
function psf_trash_forum( $forum_id = 0 ) {
	$forum_id = psf_get_forum_id( $forum_id );

	if ( empty( $forum_id ) || !psf_is_forum( $forum_id ) )
		return false;

	do_action( 'psf_trash_forum', $forum_id );
}

/**
 * Called before untrashing a forum
 *
 * @since PSForum (r3668)
 * @uses psf_get_forum_id() To get the forum id
 * @uses psf_is_forum() To check if the passed id is a forum
 * @uses do_action() Calls 'psf_untrash_forum' with the forum id
 */
function psf_untrash_forum( $forum_id = 0 ) {
	$forum_id = psf_get_forum_id( $forum_id );

	if ( empty( $forum_id ) || !psf_is_forum( $forum_id ) )
		return false;

	do_action( 'psf_untrash_forum', $forum_id );
}

/** After Delete/Trash/Untrash ************************************************/

/**
 * Called after deleting a forum
 *
 * @since PSForum (r3668)
 * @uses psf_get_forum_id() To get the forum id
 * @uses psf_is_forum() To check if the passed id is a forum
 * @uses do_action() Calls 'psf_deleted_forum' with the forum id
 */
function psf_deleted_forum( $forum_id = 0 ) {
	$forum_id = psf_get_forum_id( $forum_id );

	if ( empty( $forum_id ) || !psf_is_forum( $forum_id ) )
		return false;

	do_action( 'psf_deleted_forum', $forum_id );
}

/**
 * Called after trashing a forum
 *
 * @since PSForum (r3668)
 * @uses psf_get_forum_id() To get the forum id
 * @uses psf_is_forum() To check if the passed id is a forum
 * @uses do_action() Calls 'psf_trashed_forum' with the forum id
 */
function psf_trashed_forum( $forum_id = 0 ) {
	$forum_id = psf_get_forum_id( $forum_id );

	if ( empty( $forum_id ) || !psf_is_forum( $forum_id ) )
		return false;

	do_action( 'psf_trashed_forum', $forum_id );
}

/**
 * Called after untrashing a forum
 *
 * @since PSForum (r3668)
 * @uses psf_get_forum_id() To get the forum id
 * @uses psf_is_forum() To check if the passed id is a forum
 * @uses do_action() Calls 'psf_untrashed_forum' with the forum id
 */
function psf_untrashed_forum( $forum_id = 0 ) {
	$forum_id = psf_get_forum_id( $forum_id );

	if ( empty( $forum_id ) || !psf_is_forum( $forum_id ) )
		return false;

	do_action( 'psf_untrashed_forum', $forum_id );
}
