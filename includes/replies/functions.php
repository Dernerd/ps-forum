<?php

/**
 * PSForum Reply Functions
 *
 * @package PSForum
 * @subpackage Functions
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/** Insert ********************************************************************/

/**
 * A wrapper for wp_insert_post() that also includes the necessary meta values
 * for the reply to function properly.
 *
 * @since PSForum (r3349)
 *
 * @uses psf_parse_args()
 * @uses psf_get_reply_post_type()
 * @uses wp_insert_post()
 * @uses update_post_meta()
 *
 * @param array $reply_data Forum post data
 * @param arrap $reply_meta Forum meta data
 */
function psf_insert_reply( $reply_data = array(), $reply_meta = array() ) {

	// Forum
	$reply_data = psf_parse_args( $reply_data, array(
		'post_parent'    => 0, // topic ID
		'post_status'    => psf_get_public_status_id(),
		'post_type'      => psf_get_reply_post_type(),
		'post_author'    => psf_get_current_user_id(),
		'post_password'  => '',
		'post_content'   => '',
		'post_title'     => '',
		'menu_order'     => 0,
		'comment_status' => 'closed'
	), 'insert_reply' );

	// Insert reply
	$reply_id   = wp_insert_post( $reply_data );

	// Bail if no reply was added
	if ( empty( $reply_id ) ) {
		return false;
	}

	// Forum meta
	$reply_meta = psf_parse_args( $reply_meta, array(
		'author_ip' => psf_current_author_ip(),
		'forum_id'  => 0,
		'topic_id'  => 0,
	), 'insert_reply_meta' );

	// Insert reply meta
	foreach ( $reply_meta as $meta_key => $meta_value ) {
		update_post_meta( $reply_id, '_psf_' . $meta_key, $meta_value );
	}

	// Update the topic
	$topic_id = psf_get_reply_topic_id( $reply_id );
	if ( !empty( $topic_id ) ) {
		psf_update_topic( $topic_id );
	}

	// Return new reply ID
	return $reply_id;
}

/** Post Form Handlers ********************************************************/

/**
 * Handles the front end reply submission
 *
 * @since PSForum (r2574)
 *
 * @param string $action The requested action to compare this function to
 * @uses psf_add_error() To add an error message
 * @uses psf_verify_nonce_request() To verify the nonce and check the request
 * @uses psf_is_anonymous() To check if an anonymous post is being made
 * @uses current_user_can() To check if the current user can publish replies
 * @uses psf_get_current_user_id() To get the current user id
 * @uses psf_filter_anonymous_post_data() To filter anonymous data
 * @uses psf_set_current_anonymous_user_data() To set the anonymous user
 *                                                cookies
 * @uses is_wp_error() To check if the value retrieved is a {@link WP_Error}
 * @uses remove_filter() To remove kses filters if needed
 * @uses esc_attr() For sanitization
 * @uses psf_check_for_flood() To check for flooding
 * @uses psf_check_for_duplicate() To check for duplicates
 * @uses apply_filters() Calls 'psf_new_reply_pre_title' with the title
 * @uses apply_filters() Calls 'psf_new_reply_pre_content' with the content
 * @uses psf_get_reply_post_type() To get the reply post type
 * @uses wp_set_post_terms() To set the topic tags
 * @uses wp_insert_post() To insert the reply
 * @uses do_action() Calls 'psf_new_reply' with the reply id, topic id, forum
 *                    id, anonymous data, reply author, edit (false), and
 *                    the reply to id
 * @uses psf_get_reply_url() To get the paginated url to the reply
 * @uses wp_safe_redirect() To redirect to the reply url
 * @uses PSForum::errors::get_error_message() To get the {@link WP_Error} error
 *                                              message
 */
function psf_new_reply_handler( $action = '' ) {

	// Bail if action is not psf-new-reply
	if ( 'psf-new-reply' !== $action )
		return;

	// Nonce check
	if ( ! psf_verify_nonce_request( 'psf-new-reply' ) ) {
		psf_add_error( 'psf_new_reply_nonce', __( '<strong>FEHLER</strong>: Willst Du das wirklich?', 'psforum' ) );
		return;
	}

	// Define local variable(s)
	$topic_id = $forum_id = $reply_author = $anonymous_data = $reply_to = 0;
	$reply_title = $reply_content = $terms = '';

	/** Reply Author **********************************************************/

	// User is anonymous
	if ( psf_is_anonymous() ) {

		// Filter anonymous data
		$anonymous_data = psf_filter_anonymous_post_data();

		// Anonymous data checks out, so set cookies, etc...
		if ( !empty( $anonymous_data ) && is_array( $anonymous_data ) ) {
			psf_set_current_anonymous_user_data( $anonymous_data );
		}

	// User is logged in
	} else {

		// User cannot create replies
		if ( !current_user_can( 'publish_replies' ) ) {
			psf_add_error( 'psf_reply_permissions', __( '<strong>FEHLER</strong>: Du bist nicht berechtigt zu antworten.', 'psforum' ) );
		}

		// Reply author is current user
		$reply_author = psf_get_current_user_id();

	}

	/** Topic ID **************************************************************/

	// Topic id was not passed
	if ( empty( $_POST['psf_topic_id'] ) ) {
		psf_add_error( 'psf_reply_topic_id', __( '<strong>FEHLER</strong>: Themen-ID fehlt.', 'psforum' ) );

	// Topic id is not a number
	} elseif ( ! is_numeric( $_POST['psf_topic_id'] ) ) {
		psf_add_error( 'psf_reply_topic_id', __( '<strong>FEHLER</strong>: Themen-ID muss eine Zahl sein.', 'psforum' ) );

	// Topic id might be valid
	} else {

		// Get the topic id
		$posted_topic_id = intval( $_POST['psf_topic_id'] );

		// Topic id is a negative number
		if ( 0 > $posted_topic_id ) {
			psf_add_error( 'psf_reply_topic_id', __( '<strong>FEHLER</strong>: Themen-ID darf keine negative Zahl sein.', 'psforum' ) );

		// Topic does not exist
		} elseif ( ! psf_get_topic( $posted_topic_id ) ) {
			psf_add_error( 'psf_reply_topic_id', __( '<strong>FEHLER</strong>: Thema existiert nicht.', 'psforum' ) );

		// Use the POST'ed topic id
		} else {
			$topic_id = $posted_topic_id;
		}
	}

	/** Forum ID **************************************************************/

	// Try to use the forum id of the topic
	if ( !isset( $_POST['psf_forum_id'] ) && !empty( $topic_id ) ) {
		$forum_id = psf_get_topic_forum_id( $topic_id );

	// Error check the POST'ed forum id
	} elseif ( isset( $_POST['psf_forum_id'] ) ) {

		// Empty Forum id was passed
		if ( empty( $_POST['psf_forum_id'] ) ) {
			psf_add_error( 'psf_reply_forum_id', __( '<strong>FEHLER</strong>: Forum-ID fehlt.', 'psforum' ) );

		// Forum id is not a number
		} elseif ( ! is_numeric( $_POST['psf_forum_id'] ) ) {
			psf_add_error( 'psf_reply_forum_id', __( '<strong>FEHLER</strong>: Die Forums-ID muss eine Zahl sein.', 'psforum' ) );

		// Forum id might be valid
		} else {

			// Get the forum id
			$posted_forum_id = intval( $_POST['psf_forum_id'] );

			// Forum id is empty
			if ( 0 === $posted_forum_id ) {
				psf_add_error( 'psf_topic_forum_id', __( '<strong>FEHLER</strong>: Forum-ID fehlt.', 'psforum' ) );

			// Forum id is a negative number
			} elseif ( 0 > $posted_forum_id ) {
				psf_add_error( 'psf_topic_forum_id', __( '<strong>FEHLER</strong>: Forum-ID darf keine negative Zahl sein.', 'psforum' ) );

			// Forum does not exist
			} elseif ( ! psf_get_forum( $posted_forum_id ) ) {
				psf_add_error( 'psf_topic_forum_id', __( '<strong>FEHLER</strong>: Forum existiert nicht.', 'psforum' ) );

			// Use the POST'ed forum id
			} else {
				$forum_id = $posted_forum_id;
			}
		}
	}

	// Forum exists
	if ( !empty( $forum_id ) ) {

		// Forum is a category
		if ( psf_is_forum_category( $forum_id ) ) {
			psf_add_error( 'psf_new_reply_forum_category', __( '<strong>FEHLER</strong>: Dieses Forum ist eine Kategorie. In diesem Forum können keine Antworten erstellt werden.', 'psforum' ) );

		// Forum is not a category
		} else {

			// Forum is closed and user cannot access
			if ( psf_is_forum_closed( $forum_id ) && !current_user_can( 'edit_forum', $forum_id ) ) {
				psf_add_error( 'psf_new_reply_forum_closed', __( '<strong>FEHLER</strong>: Dieses Forum wurde für neue Antworten geschlossen.', 'psforum' ) );
			}

			// Forum is private and user cannot access
			if ( psf_is_forum_private( $forum_id ) ) {
				if ( !current_user_can( 'read_private_forums' ) ) {
					psf_add_error( 'psf_new_reply_forum_private', __( '<strong>FEHLER</strong>: Dieses Forum ist privat und Du hast nicht die Möglichkeit, darin zu lesen oder neue Antworten zu erstellen.', 'psforum' ) );
				}

			// Forum is hidden and user cannot access
			} elseif ( psf_is_forum_hidden( $forum_id ) ) {
				if ( !current_user_can( 'read_hidden_forums' ) ) {
					psf_add_error( 'psf_new_reply_forum_hidden', __( '<strong>FEHLER</strong>: Dieses Forum ist ausgeblendet und Du hast nicht die Möglichkeit, darin Antworten zu lesen oder neue Antworten zu erstellen.', 'psforum' ) );
				}
			}
		}
	}

	/** Unfiltered HTML *******************************************************/

	// Remove kses filters from title and content for capable users and if the nonce is verified
	if ( current_user_can( 'unfiltered_html' ) && !empty( $_POST['_psf_unfiltered_html_reply'] ) && wp_create_nonce( 'psf-unfiltered-html-reply_' . $topic_id ) === $_POST['_psf_unfiltered_html_reply'] ) {
		remove_filter( 'psf_new_reply_pre_title',   'wp_filter_kses'      );
		remove_filter( 'psf_new_reply_pre_content', 'psf_encode_bad',  10 );
		remove_filter( 'psf_new_reply_pre_content', 'psf_filter_kses', 30 );
	}

	/** Reply Title ***********************************************************/

	if ( !empty( $_POST['psf_reply_title'] ) )
		$reply_title = esc_attr( strip_tags( $_POST['psf_reply_title'] ) );

	// Filter and sanitize
	$reply_title = apply_filters( 'psf_new_reply_pre_title', $reply_title );

	/** Reply Content *********************************************************/

	if ( !empty( $_POST['psf_reply_content'] ) )
		$reply_content = $_POST['psf_reply_content'];

	// Filter and sanitize
	$reply_content = apply_filters( 'psf_new_reply_pre_content', $reply_content );

	// No reply content
	if ( empty( $reply_content ) )
		psf_add_error( 'psf_reply_content', __( '<strong>FEHLER</strong>: Deine Antwort darf nicht leer sein.', 'psforum' ) );

	/** Reply Flooding ********************************************************/

	if ( !psf_check_for_flood( $anonymous_data, $reply_author ) )
		psf_add_error( 'psf_reply_flood', __( '<strong>FEHLER</strong>: Mach langsam; du bewegst dich zu schnell.', 'psforum' ) );

	/** Reply Duplicate *******************************************************/

	if ( !psf_check_for_duplicate( array( 'post_type' => psf_get_reply_post_type(), 'post_author' => $reply_author, 'post_content' => $reply_content, 'post_parent' => $topic_id, 'anonymous_data' => $anonymous_data ) ) )
		psf_add_error( 'psf_reply_duplicate', __( '<strong>FEHLER</strong>: Doppelte Antwort erkannt; es sieht so aus, als hätten Du das schon gesagt!', 'psforum' ) );

	/** Reply Blacklist *******************************************************/

	if ( !psf_check_for_blacklist( $anonymous_data, $reply_author, $reply_title, $reply_content ) )
		psf_add_error( 'psf_reply_blacklist', __( '<strong>FEHLER</strong>: Deine Antwort kann derzeit nicht erstellt werden.', 'psforum' ) );

	/** Reply Status **********************************************************/

	// Maybe put into moderation
	if ( !psf_check_for_moderation( $anonymous_data, $reply_author, $reply_title, $reply_content ) ) {
		$reply_status = psf_get_pending_status_id();

	// Default
	} else {
		$reply_status = psf_get_public_status_id();
	}

	/** Reply To **************************************************************/

	// Handle Reply To of the reply; $_REQUEST for non-JS submissions
	if ( isset( $_REQUEST['psf_reply_to'] ) ) {
		$reply_to = psf_validate_reply_to( $_REQUEST['psf_reply_to'] );
	}

	/** Topic Closed **********************************************************/

	// If topic is closed, moderators can still reply
	if ( psf_is_topic_closed( $topic_id ) && ! current_user_can( 'moderate' ) ) {
		psf_add_error( 'psf_reply_topic_closed', __( '<strong>FEHLER</strong>: Thema ist geschlossen.', 'psforum' ) );
	}

	/** Topic Tags ************************************************************/

	// Either replace terms
	if ( psf_allow_topic_tags() && current_user_can( 'assign_topic_tags' ) && ! empty( $_POST['psf_topic_tags'] ) ) {
		$terms = esc_attr( strip_tags( $_POST['psf_topic_tags'] ) );

	// ...or remove them.
	} elseif ( isset( $_POST['psf_topic_tags'] ) ) {
		$terms = '';

	// Existing terms
	} else {
		$terms = psf_get_topic_tag_names( $topic_id );
	}

	/** Additional Actions (Before Save) **************************************/

	do_action( 'psf_new_reply_pre_extras', $topic_id, $forum_id );

	// Bail if errors
	if ( psf_has_errors() )
		return;

	/** No Errors *************************************************************/

	// Add the content of the form to $reply_data as an array
	// Just in time manipulation of reply data before being created
	$reply_data = apply_filters( 'psf_new_reply_pre_insert', array(
		'post_author'    => $reply_author,
		'post_title'     => $reply_title,
		'post_content'   => $reply_content,
		'post_status'    => $reply_status,
		'post_parent'    => $topic_id,
		'post_type'      => psf_get_reply_post_type(),
		'comment_status' => 'closed',
		'menu_order'     => psf_get_topic_reply_count( $topic_id, false ) + 1
	) );

	// Insert reply
	$reply_id = wp_insert_post( $reply_data );

	/** No Errors *************************************************************/

	// Check for missing reply_id or error
	if ( !empty( $reply_id ) && !is_wp_error( $reply_id ) ) {

		/** Topic Tags ********************************************************/

		// Just in time manipulation of reply terms before being edited
		$terms = apply_filters( 'psf_new_reply_pre_set_terms', $terms, $topic_id, $reply_id );

		// Insert terms
		$terms = wp_set_post_terms( $topic_id, $terms, psf_get_topic_tag_tax_id(), false );

		// Term error
		if ( is_wp_error( $terms ) ) {
			psf_add_error( 'psf_reply_tags', __( '<strong>FEHLER</strong>: Beim Hinzufügen der Tags zum Thema ist ein Problem aufgetreten.', 'psforum' ) );
		}

		/** Trash Check *******************************************************/

		// If this reply starts as trash, add it to pre_trashed_replies
		// for the topic, so it is properly restored.
		if ( psf_is_topic_trash( $topic_id ) || ( $reply_data['post_status'] === psf_get_trash_status_id() ) ) {

			// Trash the reply
			wp_trash_post( $reply_id );

			// Only add to pre-trashed array if topic is trashed
			if ( psf_is_topic_trash( $topic_id ) ) {

				// Get pre_trashed_replies for topic
				$pre_trashed_replies = (array) get_post_meta( $topic_id, '_psf_pre_trashed_replies', true );

				// Add this reply to the end of the existing replies
				$pre_trashed_replies[] = $reply_id;

				// Update the pre_trashed_reply post meta
				update_post_meta( $topic_id, '_psf_pre_trashed_replies', $pre_trashed_replies );
			}

		/** Spam Check ********************************************************/

		// If reply or topic are spam, officially spam this reply
		} elseif ( psf_is_topic_spam( $topic_id ) || ( $reply_data['post_status'] === psf_get_spam_status_id() ) ) {
			add_post_meta( $reply_id, '_psf_spam_meta_status', psf_get_public_status_id() );

			// Only add to pre-spammed array if topic is spam
			if ( psf_is_topic_spam( $topic_id ) ) {

				// Get pre_spammed_replies for topic
				$pre_spammed_replies = (array) get_post_meta( $topic_id, '_psf_pre_spammed_replies', true );

				// Add this reply to the end of the existing replies
				$pre_spammed_replies[] = $reply_id;

				// Update the pre_spammed_replies post meta
				update_post_meta( $topic_id, '_psf_pre_spammed_replies', $pre_spammed_replies );
			}
		}

		/** Update counts, etc... *********************************************/

		do_action( 'psf_new_reply', $reply_id, $topic_id, $forum_id, $anonymous_data, $reply_author, false, $reply_to );

		/** Additional Actions (After Save) ***********************************/

		do_action( 'psf_new_reply_post_extras', $reply_id );

		/** Redirect **********************************************************/

		// Redirect to
		$redirect_to = psf_get_redirect_to();

		// Get the reply URL
		$reply_url = psf_get_reply_url( $reply_id, $redirect_to );

		// Allow to be filtered
		$reply_url = apply_filters( 'psf_new_reply_redirect_to', $reply_url, $redirect_to, $reply_id );

		/** Successful Save ***************************************************/

		// Redirect back to new reply
		wp_safe_redirect( $reply_url );

		// For good measure
		exit();

	/** Errors ****************************************************************/

	} else {
		$append_error = ( is_wp_error( $reply_id ) && $reply_id->get_error_message() ) ? $reply_id->get_error_message() . ' ' : '';
		psf_add_error( 'psf_reply_error', __( '<strong>FEHLER</strong>: Die folgenden Probleme wurden bei Deiner Antwort gefunden:' . $append_error . 'Bitte versuche es erneut.', 'psforum' ) );
	}
}

/**
 * Handles the front end edit reply submission
 *
 * @param string $action The requested action to compare this function to
 * @uses psf_add_error() To add an error message
 * @uses psf_get_reply() To get the reply
 * @uses psf_verify_nonce_request() To verify the nonce and check the request
 * @uses psf_is_reply_anonymous() To check if the reply was by an anonymous user
 * @uses current_user_can() To check if the current user can edit that reply
 * @uses psf_filter_anonymous_post_data() To filter anonymous data
 * @uses is_wp_error() To check if the value retrieved is a {@link WP_Error}
 * @uses remove_filter() To remove kses filters if needed
 * @uses esc_attr() For sanitization
 * @uses apply_filters() Calls 'psf_edit_reply_pre_title' with the title and
 *                       reply id
 * @uses apply_filters() Calls 'psf_edit_reply_pre_content' with the content
 *                        reply id
 * @uses wp_set_post_terms() To set the topic tags
 * @uses psf_has_errors() To get the {@link WP_Error} errors
 * @uses wp_save_post_revision() To save a reply revision
 * @uses psf_update_reply_revision_log() To update the reply revision log
 * @uses wp_update_post() To update the reply
 * @uses psf_get_reply_topic_id() To get the reply topic id
 * @uses psf_get_topic_forum_id() To get the topic forum id
 * @uses psf_get_reply_to() To get the reply to id
 * @uses do_action() Calls 'psf_edit_reply' with the reply id, topic id, forum
 *                    id, anonymous data, reply author, bool true (for edit),
 *                    and the reply to id
 * @uses psf_get_reply_url() To get the paginated url to the reply
 * @uses wp_safe_redirect() To redirect to the reply url
 * @uses PSForum::errors::get_error_message() To get the {@link WP_Error} error
 *                                             message
 */
function psf_edit_reply_handler( $action = '' ) {

	// Bail if action is not psf-edit-reply
	if ( 'psf-edit-reply' !== $action )
		return;

	// Define local variable(s)
	$revisions_removed = false;
	$reply = $reply_id = $reply_author = $topic_id = $forum_id = $anonymous_data = 0;
	$reply_title = $reply_content = $reply_edit_reason = $terms = '';

	/** Reply *****************************************************************/

	// Reply id was not passed
	if ( empty( $_POST['psf_reply_id'] ) ) {
		psf_add_error( 'psf_edit_reply_id', __( '<strong>FEHLER</strong>: Antwort-ID nicht gefunden.', 'psforum' ) );
		return;

	// Reply id was passed
	} elseif ( is_numeric( $_POST['psf_reply_id'] ) ) {
		$reply_id = (int) $_POST['psf_reply_id'];
		$reply    = psf_get_reply( $reply_id );
	}

	// Nonce check
	if ( ! psf_verify_nonce_request( 'psf-edit-reply_' . $reply_id ) ) {
		psf_add_error( 'psf_edit_reply_nonce', __( '<strong>FEHLER</strong>: Willst du das wirklich?', 'psforum' ) );
		return;
	}

	// Reply does not exist
	if ( empty( $reply ) ) {
		psf_add_error( 'psf_edit_reply_not_found', __( '<strong>FEHLER</strong>: Die Antwort, die Du bearbeiten möchtest, wurde nicht gefunden.', 'psforum' ) );
		return;

	// Reply exists
	} else {

		// Check users ability to create new reply
		if ( ! psf_is_reply_anonymous( $reply_id ) ) {

			// User cannot edit this reply
			if ( !current_user_can( 'edit_reply', $reply_id ) ) {
				psf_add_error( 'psf_edit_reply_permissions', __( '<strong>FEHLER</strong>: Du bist nicht berechtigt, diese Antwort zu bearbeiten.', 'psforum' ) );
				return;
			}

			// Set reply author
			$reply_author = psf_get_reply_author_id( $reply_id );

		// It is an anonymous post
		} else {

			// Filter anonymous data
			$anonymous_data = psf_filter_anonymous_post_data();
		}
	}

	// Remove kses filters from title and content for capable users and if the nonce is verified
	if ( current_user_can( 'unfiltered_html' ) && !empty( $_POST['_psf_unfiltered_html_reply'] ) && wp_create_nonce( 'psf-unfiltered-html-reply_' . $reply_id ) === $_POST['_psf_unfiltered_html_reply'] ) {
		remove_filter( 'psf_edit_reply_pre_title',   'wp_filter_kses'      );
		remove_filter( 'psf_edit_reply_pre_content', 'psf_encode_bad',  10 );
		remove_filter( 'psf_edit_reply_pre_content', 'psf_filter_kses', 30 );
	}

	/** Reply Topic ***********************************************************/

	$topic_id = psf_get_reply_topic_id( $reply_id );

	/** Topic Forum ***********************************************************/

	$forum_id = psf_get_topic_forum_id( $topic_id );

	// Forum exists
	if ( !empty( $forum_id ) && ( $forum_id !== psf_get_reply_forum_id( $reply_id ) ) ) {

		// Forum is a category
		if ( psf_is_forum_category( $forum_id ) ) {
			psf_add_error( 'psf_edit_reply_forum_category', __( '<strong>FEHLER</strong>: Dieses Forum ist eine Kategorie. In diesem Forum können keine Antworten erstellt werden.', 'psforum' ) );

		// Forum is not a category
		} else {

			// Forum is closed and user cannot access
			if ( psf_is_forum_closed( $forum_id ) && !current_user_can( 'edit_forum', $forum_id ) ) {
				psf_add_error( 'psf_edit_reply_forum_closed', __( '<strong>FEHLER</strong>: Dieses Forum wurde für neue Antworten geschlossen.', 'psforum' ) );
			}

			// Forum is private and user cannot access
			if ( psf_is_forum_private( $forum_id ) ) {
				if ( !current_user_can( 'read_private_forums' ) ) {
					psf_add_error( 'psf_edit_reply_forum_private', __( '<strong>FEHLER</strong>: Dieses Forum ist privat und Du hast nicht die Möglichkeit, darin zu lesen oder neue Antworten zu erstellen.', 'psforum' ) );
				}

			// Forum is hidden and user cannot access
			} elseif ( psf_is_forum_hidden( $forum_id ) ) {
				if ( !current_user_can( 'read_hidden_forums' ) ) {
					psf_add_error( 'psf_edit_reply_forum_hidden', __( '<strong>FEHLER</strong>: Dieses Forum ist ausgeblendet und Du hast nicht die Möglichkeit, darin Antworten zu lesen oder neue Antworten zu erstellen.', 'psforum' ) );
				}
			}
		}
	}

	/** Reply Title ***********************************************************/

	if ( !empty( $_POST['psf_reply_title'] ) )
		$reply_title = esc_attr( strip_tags( $_POST['psf_reply_title'] ) );

	// Filter and sanitize
	$reply_title = apply_filters( 'psf_edit_reply_pre_title', $reply_title, $reply_id );

	/** Reply Content *********************************************************/

	if ( !empty( $_POST['psf_reply_content'] ) )
		$reply_content = $_POST['psf_reply_content'];

	// Filter and sanitize
	$reply_content = apply_filters( 'psf_edit_reply_pre_content', $reply_content, $reply_id );

	// No reply content
	if ( empty( $reply_content ) )
		psf_add_error( 'psf_edit_reply_content', __( '<strong>FEHLER</strong>: Deine Antwort darf nicht leer sein.', 'psforum' ) );

	/** Reply Blacklist *******************************************************/

	if ( !psf_check_for_blacklist( $anonymous_data, $reply_author, $reply_title, $reply_content ) )
		psf_add_error( 'psf_reply_blacklist', __( '<strong>FEHLER</strong>: Deine Antwort kann derzeit nicht bearbeitet werden.', 'psforum' ) );

	/** Reply Status **********************************************************/

	// Maybe put into moderation
	if ( !psf_check_for_moderation( $anonymous_data, $reply_author, $reply_title, $reply_content ) ) {

		// Set post status to pending if public
		if ( psf_get_public_status_id() === $reply->post_status ) {
			$reply_status = psf_get_pending_status_id();
		}

	// Use existing post_status
	} else {
		$reply_status = $reply->post_status;
	}

	/** Reply To **************************************************************/

	// Handle Reply To of the reply; $_REQUEST for non-JS submissions
	if ( isset( $_REQUEST['psf_reply_to'] ) ) {
		$reply_to = psf_validate_reply_to( $_REQUEST['psf_reply_to'] );
	}

	/** Topic Tags ************************************************************/

	// Either replace terms
	if ( psf_allow_topic_tags() && current_user_can( 'assign_topic_tags' ) && ! empty( $_POST['psf_topic_tags'] ) ) {
		$terms = esc_attr( strip_tags( $_POST['psf_topic_tags'] ) );

	// ...or remove them.
	} elseif ( isset( $_POST['psf_topic_tags'] ) ) {
		$terms = '';

	// Existing terms
	} else {
		$terms = psf_get_topic_tag_names( $topic_id );
	}

	/** Additional Actions (Before Save) **************************************/

	do_action( 'psf_edit_reply_pre_extras', $reply_id );

	// Bail if errors
	if ( psf_has_errors() )
		return;

	/** No Errors *************************************************************/

	// Add the content of the form to $reply_data as an array
	// Just in time manipulation of reply data before being edited
	$reply_data = apply_filters( 'psf_edit_reply_pre_insert', array(
		'ID'           => $reply_id,
		'post_title'   => $reply_title,
		'post_content' => $reply_content,
		'post_status'  => $reply_status,
		'post_parent'  => $topic_id,
		'post_author'  => $reply_author,
		'post_type'    => psf_get_reply_post_type()
	) );

	// Toggle revisions to avoid duplicates
	if ( post_type_supports( psf_get_reply_post_type(), 'revisions' ) ) {
		$revisions_removed = true;
		remove_post_type_support( psf_get_reply_post_type(), 'revisions' );
	}

	// Insert topic
	$reply_id = wp_update_post( $reply_data );

	// Toggle revisions back on
	if ( true === $revisions_removed ) {
		$revisions_removed = false;
		add_post_type_support( psf_get_reply_post_type(), 'revisions' );
	}

	/** Topic Tags ************************************************************/

	// Just in time manipulation of reply terms before being edited
	$terms = apply_filters( 'psf_edit_reply_pre_set_terms', $terms, $topic_id, $reply_id );

	// Insert terms
	$terms = wp_set_post_terms( $topic_id, $terms, psf_get_topic_tag_tax_id(), false );

	// Term error
	if ( is_wp_error( $terms ) ) {
		psf_add_error( 'psf_reply_tags', __( '<strong>FEHLER</strong>: Beim Hinzufügen der Tags zum Thema ist ein Problem aufgetreten.', 'psforum' ) );
	}

	/** Revisions *************************************************************/

	// Revision Reason
	if ( !empty( $_POST['psf_reply_edit_reason'] ) )
		$reply_edit_reason = esc_attr( strip_tags( $_POST['psf_reply_edit_reason'] ) );

	// Update revision log
	if ( !empty( $_POST['psf_log_reply_edit'] ) && ( "1" === $_POST['psf_log_reply_edit'] ) ) {
		$revision_id = wp_save_post_revision( $reply_id );
		if ( !empty( $revision_id ) ) {
			psf_update_reply_revision_log( array(
				'reply_id'    => $reply_id,
				'revision_id' => $revision_id,
				'author_id'   => psf_get_current_user_id(),
				'reason'      => $reply_edit_reason
			) );
		}
	}

	/** No Errors *************************************************************/

	if ( !empty( $reply_id ) && !is_wp_error( $reply_id ) ) {

		// Update counts, etc...
		do_action( 'psf_edit_reply', $reply_id, $topic_id, $forum_id, $anonymous_data, $reply_author , true, $reply_to );

		/** Additional Actions (After Save) ***********************************/

		do_action( 'psf_edit_reply_post_extras', $reply_id );

		/** Redirect **********************************************************/

		// Redirect to
		$redirect_to = psf_get_redirect_to();

		// Get the reply URL
		$reply_url = psf_get_reply_url( $reply_id, $redirect_to );

		// Allow to be filtered
		$reply_url = apply_filters( 'psf_edit_reply_redirect_to', $reply_url, $redirect_to );

		/** Successful Edit ***************************************************/

		// Redirect back to new reply
		wp_safe_redirect( $reply_url );

		// For good measure
		exit();

	/** Errors ****************************************************************/

	} else {
		$append_error = ( is_wp_error( $reply_id ) && $reply_id->get_error_message() ) ? $reply_id->get_error_message() . ' ' : '';
		psf_add_error( 'psf_reply_error', __( '<strong>FEHLER</strong>: Die folgenden Probleme wurden bei Deiner Antwort gefunden:' . $append_error . 'Please try again.', 'psforum' ) );
	}
}

/**
 * Handle all the extra meta stuff from posting a new reply or editing a reply
 *
 * @param int $reply_id Optional. Reply id
 * @param int $topic_id Optional. Topic id
 * @param int $forum_id Optional. Forum id
 * @param bool|array $anonymous_data Optional logged-out user data.
 * @param int $author_id Author id
 * @param bool $is_edit Optional. Is the post being edited? Defaults to false.
 * @param int $reply_to Optional. Reply to id
 * @uses psf_get_reply_id() To get the reply id
 * @uses psf_get_topic_id() To get the topic id
 * @uses psf_get_forum_id() To get the forum id
 * @uses psf_get_current_user_id() To get the current user id
 * @uses psf_get_reply_topic_id() To get the reply topic id
 * @uses psf_get_topic_forum_id() To get the topic forum id
 * @uses update_post_meta() To update the reply metas
 * @uses set_transient() To update the flood check transient for the ip
 * @uses psf_update_user_last_posted() To update the users last posted time
 * @uses psf_is_subscriptions_active() To check if the subscriptions feature is
 *                                      activated or not
 * @uses psf_is_user_subscribed() To check if the user is subscribed
 * @uses psf_remove_user_subscription() To remove the user's subscription
 * @uses psf_add_user_subscription() To add the user's subscription
 * @uses psf_update_reply_forum_id() To update the reply forum id
 * @uses psf_update_reply_topic_id() To update the reply topic id
 * @uses psf_update_reply_to() To update the reply to id
 * @uses psf_update_reply_walker() To update the reply's ancestors' counts
 */
function psf_update_reply( $reply_id = 0, $topic_id = 0, $forum_id = 0, $anonymous_data = false, $author_id = 0, $is_edit = false, $reply_to = 0 ) {

	// Validate the ID's passed from 'psf_new_reply' action
	$reply_id = psf_get_reply_id( $reply_id );
	$topic_id = psf_get_topic_id( $topic_id );
	$forum_id = psf_get_forum_id( $forum_id );
	$reply_to = psf_validate_reply_to( $reply_to );

	// Bail if there is no reply
	if ( empty( $reply_id ) )
		return;

	// Check author_id
	if ( empty( $author_id ) )
		$author_id = psf_get_current_user_id();

	// Check topic_id
	if ( empty( $topic_id ) )
		$topic_id = psf_get_reply_topic_id( $reply_id );

	// Check forum_id
	if ( !empty( $topic_id ) && empty( $forum_id ) )
		$forum_id = psf_get_topic_forum_id( $topic_id );

	// If anonymous post, store name, email, website and ip in post_meta.
	// It expects anonymous_data to be sanitized.
	// Check psf_filter_anonymous_post_data() for sanitization.
	if ( !empty( $anonymous_data ) && is_array( $anonymous_data ) ) {

		// Parse arguments against default values
		$r = psf_parse_args( $anonymous_data, array(
			'psf_anonymous_name'    => '',
			'psf_anonymous_email'   => '',
			'psf_anonymous_website' => '',
		), 'update_reply' );

		// Update all anonymous metas
		foreach ( $r as $anon_key => $anon_value ) {
			update_post_meta( $reply_id, '_' . $anon_key, (string) $anon_value, false );
		}

		// Set transient for throttle check (only on new, not edit)
		if ( empty( $is_edit ) ) {
			set_transient( '_psf_' . psf_current_author_ip() . '_last_posted', time() );
		}

	} else {
		if ( empty( $is_edit ) && !current_user_can( 'throttle' ) ) {
			psf_update_user_last_posted( $author_id );
		}
	}

	// Handle Subscription Checkbox
	if ( psf_is_subscriptions_active() && !empty( $author_id ) && !empty( $topic_id ) ) {
		$subscribed = psf_is_user_subscribed( $author_id, $topic_id );
		$subscheck  = ( !empty( $_POST['psf_topic_subscription'] ) && ( 'psf_subscribe' === $_POST['psf_topic_subscription'] ) ) ? true : false;

		// Subscribed and unsubscribing
		if ( true === $subscribed && false === $subscheck ) {
			psf_remove_user_subscription( $author_id, $topic_id );

		// Subscribing
		} elseif ( false === $subscribed && true === $subscheck ) {
			psf_add_user_subscription( $author_id, $topic_id );
		}
	}

	// Reply meta relating to reply position in tree
	psf_update_reply_forum_id( $reply_id, $forum_id );
	psf_update_reply_topic_id( $reply_id, $topic_id );
	psf_update_reply_to      ( $reply_id, $reply_to );

	// Update associated topic values if this is a new reply
	if ( empty( $is_edit ) ) {

		// Update poster IP if not editing
		update_post_meta( $reply_id, '_psf_author_ip', psf_current_author_ip(), false );

		// Last active time
		$last_active_time = current_time( 'mysql' );

		// Walk up ancestors and do the dirty work
		psf_update_reply_walker( $reply_id, $last_active_time, $forum_id, $topic_id, false );
	}
}

/**
 * Walk up the ancestor tree from the current reply, and update all the counts
 *
 * @since PSForum (r2884)
 *
 * @param int $reply_id Optional. Reply id
 * @param string $last_active_time Optional. Last active time
 * @param int $forum_id Optional. Forum id
 * @param int $topic_id Optional. Topic id
 * @param bool $refresh If set to true, unsets all the previous parameters.
 *                       Defaults to true
 * @uses psf_get_reply_id() To get the reply id
 * @uses psf_get_reply_topic_id() To get the reply topic id
 * @uses psf_get_reply_forum_id() To get the reply forum id
 * @uses get_post_ancestors() To get the ancestors of the reply
 * @uses psf_is_reply() To check if the ancestor is a reply
 * @uses psf_is_topic() To check if the ancestor is a topic
 * @uses psf_update_topic_last_reply_id() To update the topic last reply id
 * @uses psf_update_topic_last_active_id() To update the topic last active id
 * @uses psf_get_topic_last_active_id() To get the topic last active id
 * @uses get_post_field() To get the post date of the last active id
 * @uses psf_update_topic_last_active_time() To update the last active topic meta
 * @uses psf_update_topic_voice_count() To update the topic voice count
 * @uses psf_update_topic_reply_count() To update the topic reply count
 * @uses psf_update_topic_reply_count_hidden() To update the topic hidden reply
 *                                              count
 * @uses psf_is_forum() To check if the ancestor is a forum
 * @uses psf_update_forum_last_topic_id() To update the last topic id forum meta
 * @uses psf_update_forum_last_reply_id() To update the last reply id forum meta
 * @uses psf_update_forum_last_active_id() To update the forum last active id
 * @uses psf_get_forum_last_active_id() To get the forum last active id
 * @uses psf_update_forum_last_active_time() To update the forum last active time
 * @uses psf_update_forum_reply_count() To update the forum reply count
 */
function psf_update_reply_walker( $reply_id, $last_active_time = '', $forum_id = 0, $topic_id = 0, $refresh = true ) {

	// Verify the reply ID
	$reply_id = psf_get_reply_id( $reply_id );

	// Reply was passed
	if ( !empty( $reply_id ) ) {

		// Get the topic ID if none was passed
		if ( empty( $topic_id ) ) {
			$topic_id = psf_get_reply_topic_id( $reply_id );
		}

		// Get the forum ID if none was passed
		if ( empty( $forum_id ) ) {
			$forum_id = psf_get_reply_forum_id( $reply_id );
		}
	}

	// Set the active_id based on topic_id/reply_id
	$active_id = empty( $reply_id ) ? $topic_id : $reply_id;

	// Setup ancestors array to walk up
	$ancestors = array_values( array_unique( array_merge( array( $topic_id, $forum_id ), (array) get_post_ancestors( $topic_id ) ) ) );

	// If we want a full refresh, unset any of the possibly passed variables
	if ( true === $refresh )
		$forum_id = $topic_id = $reply_id = $active_id = $last_active_time = 0;

	// Walk up ancestors
	if ( !empty( $ancestors ) ) {
		foreach ( $ancestors as $ancestor ) {

			// Reply meta relating to most recent reply
			if ( psf_is_reply( $ancestor ) ) {
				// @todo - hierarchical replies

			// Topic meta relating to most recent reply
			} elseif ( psf_is_topic( $ancestor ) ) {

				// Last reply and active ID's
				psf_update_topic_last_reply_id ( $ancestor, $reply_id  );
				psf_update_topic_last_active_id( $ancestor, $active_id );

				// Get the last active time if none was passed
				$topic_last_active_time = $last_active_time;
				if ( empty( $last_active_time ) ) {
					$topic_last_active_time = get_post_field( 'post_date', psf_get_topic_last_active_id( $ancestor ) );
				}

				// Only update if reply is published
				if ( psf_is_reply_published( $reply_id ) ) {
					psf_update_topic_last_active_time( $ancestor, $topic_last_active_time );
				}

				// Counts
				psf_update_topic_voice_count       ( $ancestor );
				psf_update_topic_reply_count       ( $ancestor );
				psf_update_topic_reply_count_hidden( $ancestor );

			// Forum meta relating to most recent topic
			} elseif ( psf_is_forum( $ancestor ) ) {

				// Last topic and reply ID's
				psf_update_forum_last_topic_id( $ancestor, $topic_id );
				psf_update_forum_last_reply_id( $ancestor, $reply_id );

				// Last Active
				psf_update_forum_last_active_id( $ancestor, $active_id );

				// Get the last active time if none was passed
				$forum_last_active_time = $last_active_time;
				if ( empty( $last_active_time ) ) {
					$forum_last_active_time = get_post_field( 'post_date', psf_get_forum_last_active_id( $ancestor ) );
				}

				// Only update if reply is published
				if ( psf_is_reply_published( $reply_id ) ) {
					psf_update_forum_last_active_time( $ancestor, $forum_last_active_time );
				}

				// Counts
				psf_update_forum_reply_count( $ancestor );
			}
		}
	}
}

/** Reply Updaters ************************************************************/

/**
 * Update the reply with its forum id it is in
 *
 * @since PSForum (r2855)
 *
 * @param int $reply_id Optional. Reply id to update
 * @param int $forum_id Optional. Forum id
 * @uses psf_get_reply_id() To get the reply id
 * @uses psf_get_forum_id() To get the forum id
 * @uses get_post_ancestors() To get the reply's forum
 * @uses get_post_field() To get the post type of the post
 * @uses update_post_meta() To update the reply forum id meta
 * @uses apply_filters() Calls 'psf_update_reply_forum_id' with the forum id
 *                        and reply id
 * @return bool Reply's forum id
 */
function psf_update_reply_forum_id( $reply_id = 0, $forum_id = 0 ) {

	// Validation
	$reply_id = psf_get_reply_id( $reply_id );
	$forum_id = psf_get_forum_id( $forum_id );

	// If no forum_id was passed, walk up ancestors and look for forum type
	if ( empty( $forum_id ) ) {

		// Get ancestors
		$ancestors = (array) get_post_ancestors( $reply_id );

		// Loop through ancestors
		if ( !empty( $ancestors ) ) {
			foreach ( $ancestors as $ancestor ) {

				// Get first parent that is a forum
				if ( get_post_field( 'post_type', $ancestor ) === psf_get_forum_post_type() ) {
					$forum_id = $ancestor;

					// Found a forum, so exit the loop and continue
					continue;
				}
			}
		}
	}

	// Update the forum ID
	psf_update_forum_id( $reply_id, $forum_id );

	return apply_filters( 'psf_update_reply_forum_id', (int) $forum_id, $reply_id );
}

/**
 * Update the reply with its topic id it is in
 *
 * @since PSForum (r2855)
 *
 * @param int $reply_id Optional. Reply id to update
 * @param int $topic_id Optional. Topic id
 * @uses psf_get_reply_id() To get the reply id
 * @uses psf_get_topic_id() To get the topic id
 * @uses get_post_ancestors() To get the reply's topic
 * @uses get_post_field() To get the post type of the post
 * @uses update_post_meta() To update the reply topic id meta
 * @uses apply_filters() Calls 'psf_update_reply_topic_id' with the topic id
 *                        and reply id
 * @return bool Reply's topic id
 */
function psf_update_reply_topic_id( $reply_id = 0, $topic_id = 0 ) {

	// Validation
	$reply_id = psf_get_reply_id( $reply_id );
	$topic_id = psf_get_topic_id( $topic_id );

	// If no topic_id was passed, walk up ancestors and look for topic type
	if ( empty( $topic_id ) ) {

		// Get ancestors
		$ancestors = (array) get_post_ancestors( $reply_id );

		// Loop through ancestors
		if ( !empty( $ancestors ) ) {
			foreach ( $ancestors as $ancestor ) {

				// Get first parent that is a forum
				if ( get_post_field( 'post_type', $ancestor ) === psf_get_topic_post_type() ) {
					$topic_id = $ancestor;

					// Found a forum, so exit the loop and continue
					continue;
				}
			}
		}
	}

	// Update the topic ID
	psf_update_topic_id( $reply_id, $topic_id );

	return apply_filters( 'psf_update_reply_topic_id', (int) $topic_id, $reply_id );
}

/*
 * Update the reply's meta data with its reply to id
 *
 * @since PSForum (r4944)
 *
 * @param int $reply_id Reply id to update
 * @param int $reply_to Optional. Reply to id
 * @uses psf_get_reply_id() To get the reply id
 * @uses update_post_meta() To update the reply to meta
 * @uses apply_filters() Calls 'psf_update_reply_to' with the reply id and
 *                        and reply to id
 * @return bool Reply's parent reply id
 */
function psf_update_reply_to( $reply_id = 0, $reply_to = 0 ) {

	// Validation
	$reply_id = psf_get_reply_id( $reply_id );
	$reply_to = psf_validate_reply_to( $reply_to );

	// Update or delete the `reply_to` postmeta
	if ( ! empty( $reply_id ) ) {

		// Update the reply to
		if ( !empty( $reply_to ) ) {
			update_post_meta( $reply_id, '_psf_reply_to', $reply_to );

		// Delete the reply to
		} else {
			delete_post_meta( $reply_id, '_psf_reply_to' );
		}
	}

	return (int) apply_filters( 'psf_update_reply_to', (int) $reply_to, $reply_id );
}

/**
 * Update the revision log of the reply
 *
 * @since PSForum (r2782)
 *
 * @param mixed $args Supports these args:
 *  - reply_id: reply id
 *  - author_id: Author id
 *  - reason: Reason for editing
 *  - revision_id: Revision id
 * @uses psf_get_reply_id() To get the reply id
 * @uses psf_format_revision_reason() To format the reason
 * @uses psf_get_reply_raw_revision_log() To get the raw reply revision log
 * @uses update_post_meta() To update the reply revision log meta
 * @return mixed False on failure, true on success
 */
function psf_update_reply_revision_log( $args = '' ) {

	// Parse arguments against default values
	$r = psf_parse_args( $args, array(
		'reason'      => '',
		'reply_id'    => 0,
		'author_id'   => 0,
		'revision_id' => 0
	), 'update_reply_revision_log' );

	// Populate the variables
	$r['reason']      = psf_format_revision_reason( $r['reason'] );
	$r['reply_id']    = psf_get_reply_id( $r['reply_id'] );
	$r['author_id']   = psf_get_user_id ( $r['author_id'], false, true );
	$r['revision_id'] = (int) $r['revision_id'];

	// Get the logs and append the new one to those
	$revision_log                      = psf_get_reply_raw_revision_log( $r['reply_id'] );
	$revision_log[ $r['revision_id'] ] = array( 'author' => $r['author_id'], 'reason' => $r['reason'] );

	// Finally, update
	update_post_meta( $r['reply_id'], '_psf_revision_log', $revision_log );

	return apply_filters( 'psf_update_reply_revision_log', $revision_log, $r['reply_id'] );
}

/**
 * Move reply handler
 *
 * Handles the front end move reply submission
 *
 * @since PSForum (r4521)
 *
 * @param string $action The requested action to compare this function to
 * @uses psf_add_error() To add an error message
 * @uses psf_get_reply() To get the reply
 * @uses psf_get_topic() To get the topics
 * @uses psf_verify_nonce_request() To verify the nonce and check the request
 * @uses current_user_can() To check if the current user can edit the reply and topics
 * @uses psf_get_topic_post_type() To get the topic post type
 * @uses is_wp_error() To check if the value retrieved is a {@link WP_Error}
 * @uses do_action() Calls 'psf_pre_move_reply' with the from reply id, source
 *                    and destination topic ids
 * @uses psf_get_reply_post_type() To get the reply post type
 * @uses wpdb::prepare() To prepare our sql query
 * @uses wpdb::get_results() To execute the sql query and get results
 * @uses wp_update_post() To update the replies
 * @uses psf_update_reply_topic_id() To update the reply topic id
 * @uses psf_get_topic_forum_id() To get the topic forum id
 * @uses psf_update_reply_forum_id() To update the reply forum id
 * @uses do_action() Calls 'psf_split_topic_reply' with the reply id and
 *                    destination topic id
 * @uses psf_update_topic_last_reply_id() To update the topic last reply id
 * @uses psf_update_topic_last_active_time() To update the topic last active meta
 * @uses do_action() Calls 'psf_post_split_topic' with the destination and
 *                    source topic ids and source topic's forum id
 * @uses psf_get_topic_permalink() To get the topic permalink
 * @uses wp_safe_redirect() To redirect to the topic link
 */
function psf_move_reply_handler( $action = '' ) {

	// Bail if action is not 'psf-move-reply'
	if ( 'psf-move-reply' !== $action )
		return;

	// Prevent debug notices
	$move_reply_id = $destination_topic_id = 0;
	$destination_topic_title = '';
	$destination_topic = $move_reply = $source_topic = '';

	/** Move Reply ***********************************************************/

	if ( empty( $_POST['psf_reply_id'] ) ) {
		psf_add_error( 'psf_move_reply_reply_id', __( '<strong>FEHLER</strong>: Antwort-ID zum Umzug nicht gefunden!', 'psforum' ) );
	} else {
		$move_reply_id = (int) $_POST['psf_reply_id'];
	}

	$move_reply = psf_get_reply( $move_reply_id );

	// Reply exists
	if ( empty( $move_reply ) )
		psf_add_error( 'psf_mover_reply_r_not_found', __( '<strong>FEHLER</strong>: Die zu verschiebende Antwort wurde nicht gefunden.', 'psforum' ) );

	/** Topic to Move From ***************************************************/

	// Get the reply's current topic
	$source_topic = psf_get_topic( $move_reply->post_parent );

	// No topic
	if ( empty( $source_topic ) ) {
		psf_add_error( 'psf_move_reply_source_not_found', __( '<strong>FEHLER</strong>: Das Thema, von dem Du verschieben möchtest, wurde nicht gefunden.', 'psforum' ) );
	}

	// Nonce check failed
	if ( ! psf_verify_nonce_request( 'psf-move-reply_' . $move_reply->ID ) ) {
		psf_add_error( 'psf_move_reply_nonce', __( '<strong>FEHLER</strong>: Willst Du das wirklich?', 'psforum' ) );
		return;
	}

	// Use cannot edit topic
	if ( !current_user_can( 'edit_topic', $source_topic->ID ) ) {
		psf_add_error( 'psf_move_reply_source_permission', __( '<strong>FEHLER</strong>: Du bist nicht berechtigt, das Quellthema zu bearbeiten.', 'psforum' ) );
	}

	// How to move
	if ( !empty( $_POST['psf_reply_move_option'] ) ) {
		$move_option = (string) trim( $_POST['psf_reply_move_option'] );
	}

	// Invalid move option
	if ( empty( $move_option ) || !in_array( $move_option, array( 'existing', 'topic' ) ) ) {
		psf_add_error( 'psf_move_reply_option', __( '<strong>FEHLER</strong>: Du musst eine gültige Verschiebeoption auswählen.', 'psforum' ) );

	// Valid move option
	} else {

		// What kind of move
		switch ( $move_option ) {

			// Into an existing topic
			case 'existing' :

				// Get destination topic id
				if ( empty( $_POST['psf_destination_topic'] ) ) {
					psf_add_error( 'psf_move_reply_destination_id', __( '<strong>FEHLER</strong>: Zielthemen-ID nicht gefunden!', 'psforum' ) );
				} else {
					$destination_topic_id = (int) $_POST['psf_destination_topic'];
				}

				// Get the destination topic
				$destination_topic = psf_get_topic( $destination_topic_id );

				// No destination topic
				if ( empty( $destination_topic ) ) {
					psf_add_error( 'psf_move_reply_destination_not_found', __( '<strong>FEHLER</strong>: Das Thema, in das Du verschieben möchtest, wurde nicht gefunden!', 'psforum' ) );
				}

				// User cannot edit the destination topic
				if ( !current_user_can( 'edit_topic', $destination_topic->ID ) ) {
					psf_add_error( 'psf_move_reply_destination_permission', __( '<strong>FEHLER</strong>: Du bist nicht berechtigt, das Zielthema zu bearbeiten!', 'psforum' ) );
				}

				// Bump the reply position
				$reply_position = psf_get_topic_reply_count( $destination_topic->ID ) + 1;

				// Update the reply
				wp_update_post( array(
					'ID'          => $move_reply->ID,
					'post_title'  => sprintf( __( 'Antwort auf: %s', 'psforum' ), $destination_topic->post_title ),
					'post_name'   => false, // will be automatically generated
					'post_parent' => $destination_topic->ID,
					'menu_order'  => $reply_position,
					'guid'        => ''
				) );

				// Adjust reply meta values
				psf_update_reply_topic_id( $move_reply->ID, $destination_topic->ID );
				psf_update_reply_forum_id( $move_reply->ID, psf_get_topic_forum_id( $destination_topic->ID ) );

				break;

			// Move reply to a new topic
			case 'topic' :
			default :

				// User needs to be able to publish topics
				if ( current_user_can( 'publish_topics' ) ) {

					// Use the new title that was passed
					if ( !empty( $_POST['psf_reply_move_destination_title'] ) ) {
						$destination_topic_title = esc_attr( strip_tags( $_POST['psf_reply_move_destination_title'] ) );

					// Use the source topic title
					} else {
						$destination_topic_title = $source_topic->post_title;
					}

					// Update the topic
					$destination_topic_id = wp_update_post( array(
						'ID'          => $move_reply->ID,
						'post_title'  => $destination_topic_title,
						'post_name'   => false,
						'post_type'   => psf_get_topic_post_type(),
						'post_parent' => $source_topic->post_parent,
						'guid'        => ''
					) );
					$destination_topic = psf_get_topic( $destination_topic_id );

					// Make sure the new topic knows its a topic
					psf_update_topic_topic_id( $move_reply->ID );

					// Shouldn't happen
					if ( false === $destination_topic_id || is_wp_error( $destination_topic_id ) || empty( $destination_topic ) ) {
						psf_add_error( 'psf_move_reply_destination_reply', __( '<strong>FEHLER</strong>: Beim Konvertieren der Antwort in das Thema ist ein Problem aufgetreten. Bitte versuche es erneut.', 'psforum' ) );
					}

				// User cannot publish posts
				} else {
					psf_add_error( 'psf_move_reply_destination_permission', __( '<strong>FEHLER</strong>: Du bist nicht berechtigt, neue Themen zu erstellen. Die Antwort konnte nicht in ein Thema umgewandelt werden.', 'psforum' ) );
				}

				break;
		}
	}

	// Bail if there are errors
	if ( psf_has_errors() ) {
		return;
	}

	/** No Errors - Clean Up **************************************************/

	// Update counts, etc...
	do_action( 'psf_pre_move_reply', $move_reply->ID, $source_topic->ID, $destination_topic->ID );

	/** Date Check ************************************************************/

	// Check if the destination topic is older than the move reply
	if ( strtotime( $move_reply->post_date ) < strtotime( $destination_topic->post_date ) ) {

		// Set destination topic post_date to 1 second before from reply
		$destination_post_date = date( 'Y-m-d H:i:s', strtotime( $move_reply->post_date ) - 1 );

		// Update destination topic
		wp_update_post( array(
			'ID'            => $destination_topic_id,
			'post_date'     => $destination_post_date,
			'post_date_gmt' => get_gmt_from_date( $destination_post_date )
		) );
	}

	// Set the last reply ID and freshness to the move_reply
	$last_reply_id = $move_reply->ID;
	$freshness     = $move_reply->post_date;

	// Get the reply to
	$parent = psf_get_reply_to( $move_reply->ID );

	// Fix orphaned children
	$children = get_posts( array(
		'post_type'  => psf_get_reply_post_type(),
		'meta_key'   => '_psf_reply_to',
		'meta_value' => $move_reply->ID,
	) );
	foreach ( $children as $child )
		psf_update_reply_to( $child->ID, $parent );

	// Remove reply_to from moved reply
	delete_post_meta( $move_reply->ID, '_psf_reply_to' );

	// It is a new topic and we need to set some default metas to make
	// the topic display in psf_has_topics() list
	if ( 'topic' === $move_option ) {
		psf_update_topic_last_reply_id   ( $destination_topic->ID, $last_reply_id );
		psf_update_topic_last_active_id  ( $destination_topic->ID, $last_reply_id );
		psf_update_topic_last_active_time( $destination_topic->ID, $freshness     );

	// Otherwise update the existing destination topic
	} else {
		psf_update_topic_last_reply_id   ( $destination_topic->ID );
		psf_update_topic_last_active_id  ( $destination_topic->ID );
		psf_update_topic_last_active_time( $destination_topic->ID );
	}

	// Update source topic ID last active
	psf_update_topic_last_reply_id   ( $source_topic->ID );
	psf_update_topic_last_active_id  ( $source_topic->ID );
	psf_update_topic_last_active_time( $source_topic->ID );

	/** Successful Move ******************************************************/

	// Update counts, etc...
	do_action( 'psf_post_move_reply', $move_reply->ID, $source_topic->ID, $destination_topic->ID );

	// Redirect back to the topic
	wp_safe_redirect( psf_get_topic_permalink( $destination_topic->ID ) );

	// For good measure
	exit();
}

/**
 * Fix counts on reply move
 *
 * When a reply is moved, update the counts of source and destination topic
 * and their forums.
 *
 * @since PSForum (r4521)
 *
 * @param int $move_reply_id Move reply id
 * @param int $source_topic_id Source topic id
 * @param int $destination_topic_id Destination topic id
 * @uses psf_update_forum_topic_count() To update the forum topic counts
 * @uses psf_update_forum_reply_count() To update the forum reply counts
 * @uses psf_update_topic_reply_count() To update the topic reply counts
 * @uses psf_update_topic_voice_count() To update the topic voice counts
 * @uses psf_update_topic_reply_count_hidden() To update the topic hidden reply
 *                                              count
 * @uses do_action() Calls 'psf_move_reply_count' with the move reply id,
 *                    source topic id & destination topic id
 */
function psf_move_reply_count( $move_reply_id, $source_topic_id, $destination_topic_id ) {

	// Forum topic counts
	psf_update_forum_topic_count( psf_get_topic_forum_id( $destination_topic_id ) );

	// Forum reply counts
	psf_update_forum_reply_count( psf_get_topic_forum_id( $destination_topic_id ) );

	// Topic reply counts
	psf_update_topic_reply_count( $source_topic_id      );
	psf_update_topic_reply_count( $destination_topic_id );

	// Topic hidden reply counts
	psf_update_topic_reply_count_hidden( $source_topic_id      );
	psf_update_topic_reply_count_hidden( $destination_topic_id );

	// Topic voice counts
	psf_update_topic_voice_count( $source_topic_id      );
	psf_update_topic_voice_count( $destination_topic_id );

	do_action( 'psf_move_reply_count', $move_reply_id, $source_topic_id, $destination_topic_id );
}

/** Reply Actions *************************************************************/

/**
 * Handles the front end spamming/unspamming and trashing/untrashing/deleting of
 * replies
 *
 * @since PSForum (r2740)
 *
 * @param string $action The requested action to compare this function to
 * @uses psf_get_reply() To get the reply
 * @uses current_user_can() To check if the user is capable of editing or
 *                           deleting the reply
 * @uses check_ajax_referer() To verify the nonce and check the referer
 * @uses psf_get_reply_post_type() To get the reply post type
 * @uses psf_is_reply_spam() To check if the reply is marked as spam
 * @uses psf_spam_reply() To make the reply as spam
 * @uses psf_unspam_reply() To unmark the reply as spam
 * @uses wp_trash_post() To trash the reply
 * @uses wp_untrash_post() To untrash the reply
 * @uses wp_delete_post() To delete the reply
 * @uses do_action() Calls 'psf_toggle_reply_handler' with success, post data
 *                    and action
 * @uses psf_get_reply_url() To get the reply url
 * @uses wp_safe_redirect() To redirect to the reply
 * @uses PSForum::errors:add() To log the error messages
 */
function psf_toggle_reply_handler( $action = '' ) {

	// Bail if required GET actions aren't passed
	if ( empty( $_GET['reply_id'] ) )
		return;

	// Setup possible get actions
	$possible_actions = array(
		'psf_toggle_reply_spam',
		'psf_toggle_reply_trash'
	);

	// Bail if actions aren't meant for this function
	if ( !in_array( $action, $possible_actions ) )
		return;

	$failure   = '';                         // Empty failure string
	$view_all  = false;                      // Assume not viewing all
	$reply_id  = (int) $_GET['reply_id'];    // What's the reply id?
	$success   = false;                      // Flag
	$post_data = array( 'ID' => $reply_id ); // Prelim array

	// Make sure reply exists
	$reply = psf_get_reply( $reply_id );
	if ( empty( $reply ) )
		return;

	// What is the user doing here?
	if ( !current_user_can( 'edit_reply', $reply->ID ) || ( 'psf_toggle_reply_trash' === $action && !current_user_can( 'delete_reply', $reply->ID ) ) ) {
		psf_add_error( 'psf_toggle_reply_permission', __( '<strong>FEHLER:</strong> Du hast keine Berechtigung dazu!', 'psforum' ) );
		return;
	}

	// What action are we trying to perform?
	switch ( $action ) {

		// Toggle spam
		case 'psf_toggle_reply_spam' :
			check_ajax_referer( 'spam-reply_' . $reply_id );

			$is_spam  = psf_is_reply_spam( $reply_id );
			$success  = $is_spam ? psf_unspam_reply( $reply_id ) : psf_spam_reply( $reply_id );
			$failure  = $is_spam ? __( '<strong>FEHLER</strong>: Beim Aufheben der Markierung der Antwort als Spam ist ein Problem aufgetreten!', 'psforum' ) : __( '<strong>FEHLER</strong>: Beim Markieren der Antwort als Spam ist ein Problem aufgetreten!', 'psforum' );
			$view_all = !$is_spam;

			break;

		// Toggle trash
		case 'psf_toggle_reply_trash' :

			$sub_action = in_array( $_GET['sub_action'], array( 'trash', 'untrash', 'delete' ) ) ? $_GET['sub_action'] : false;

			if ( empty( $sub_action ) )
				break;

			switch ( $sub_action ) {
				case 'trash':
					check_ajax_referer( 'trash-' . psf_get_reply_post_type() . '_' . $reply_id );

					$view_all = true;
					$success  = wp_trash_post( $reply_id );
					$failure  = __( '<strong>FEHLER</strong>: Beim Löschen der Antwort ist ein Problem aufgetreten!', 'psforum' );

					break;

				case 'untrash':
					check_ajax_referer( 'untrash-' . psf_get_reply_post_type() . '_' . $reply_id );

					$success = wp_untrash_post( $reply_id );
					$failure = __( '<strong>FEHLER</strong>: Beim Aufheben der Antwort ist ein Problem aufgetreten!', 'psforum' );

					break;

				case 'delete':
					check_ajax_referer( 'delete-' . psf_get_reply_post_type() . '_' . $reply_id );

					$success = wp_delete_post( $reply_id );
					$failure = __( '<strong>FEHLER</strong>: Beim Löschen der Antwort ist ein Problem aufgetreten!', 'psforum' );

					break;
			}

			break;
	}

	// Do additional reply toggle actions
	do_action( 'psf_toggle_reply_handler', $success, $post_data, $action );

	// No errors
	if ( ( false !== $success ) && !is_wp_error( $success ) ) {

		/** Redirect **********************************************************/

		// Redirect to
		$redirect_to = psf_get_redirect_to();

		// Get the reply URL
		$reply_url = psf_get_reply_url( $reply_id, $redirect_to );

		// Add view all if needed
		if ( !empty( $view_all ) )
			$reply_url = psf_add_view_all( $reply_url, true );

		// Redirect back to reply
		wp_safe_redirect( $reply_url );

		// For good measure
		exit();

	// Handle errors
	} else {
		psf_add_error( 'psf_toggle_reply', $failure );
	}
}

/** Reply Actions *************************************************************/

/**
 * Marks a reply as spam
 *
 * @since PSForum (r2740)
 *
 * @param int $reply_id Reply id
 * @uses psf_get_reply() To get the reply
 * @uses do_action() Calls 'psf_spam_reply' with the reply ID
 * @uses add_post_meta() To add the previous status to a meta
 * @uses wp_update_post() To insert the updated post
 * @uses do_action() Calls 'psf_spammed_reply' with the reply ID
 * @return mixed False or {@link WP_Error} on failure, reply id on success
 */
function psf_spam_reply( $reply_id = 0 ) {

	// Get reply
	$reply = psf_get_reply( $reply_id );
	if ( empty( $reply ) )
		return $reply;

	// Bail if already spam
	if ( psf_get_spam_status_id() === $reply->post_status )
		return false;

	// Execute pre spam code
	do_action( 'psf_spam_reply', $reply_id );

	// Add the original post status as post meta for future restoration
	add_post_meta( $reply_id, '_psf_spam_meta_status', $reply->post_status );

	// Set post status to spam
	$reply->post_status = psf_get_spam_status_id();

	// No revisions
	remove_action( 'pre_post_update', 'wp_save_post_revision' );

	// Update the reply
	$reply_id = wp_update_post( $reply );

	// Execute post spam code
	do_action( 'psf_spammed_reply', $reply_id );

	// Return reply_id
	return $reply_id;
}

/**
 * Unspams a reply
 *
 * @since PSForum (r2740)
 *
 * @param int $reply_id Reply id
 * @uses psf_get_reply() To get the reply
 * @uses do_action() Calls 'psf_unspam_reply' with the reply ID
 * @uses get_post_meta() To get the previous status meta
 * @uses delete_post_meta() To delete the previous status meta
 * @uses wp_update_post() To insert the updated post
 * @uses do_action() Calls 'psf_unspammed_reply' with the reply ID
 * @return mixed False or {@link WP_Error} on failure, reply id on success
 */
function psf_unspam_reply( $reply_id = 0 ) {

	// Get reply
	$reply = psf_get_reply( $reply_id );
	if ( empty( $reply ) )
		return $reply;

	// Bail if already not spam
	if ( psf_get_spam_status_id() !== $reply->post_status )
		return false;

	// Execute pre unspam code
	do_action( 'psf_unspam_reply', $reply_id );

	// Get pre spam status
	$reply->post_status = get_post_meta( $reply_id, '_psf_spam_meta_status', true );

	// If no previous status, default to publish
	if ( empty( $reply->post_status ) ) {
		$reply->post_status = psf_get_public_status_id();
	}

	// Delete pre spam meta
	delete_post_meta( $reply_id, '_psf_spam_meta_status' );

	// No revisions
	remove_action( 'pre_post_update', 'wp_save_post_revision' );

	// Update the reply
	$reply_id = wp_update_post( $reply );

	// Execute post unspam code
	do_action( 'psf_unspammed_reply', $reply_id );

	// Return reply_id
	return $reply_id;
}

/** Before Delete/Trash/Untrash ***********************************************/

/**
 * Called before deleting a reply
 *
 * @uses psf_get_reply_id() To get the reply id
 * @uses psf_is_reply() To check if the passed id is a reply
 * @uses do_action() Calls 'psf_delete_reply' with the reply id
 */
function psf_delete_reply( $reply_id = 0 ) {
	$reply_id = psf_get_reply_id( $reply_id );

	if ( empty( $reply_id ) || !psf_is_reply( $reply_id ) )
		return false;

	do_action( 'psf_delete_reply', $reply_id );
}

/**
 * Called before trashing a reply
 *
 * @uses psf_get_reply_id() To get the reply id
 * @uses psf_is_reply() To check if the passed id is a reply
 * @uses do_action() Calls 'psf_trash_reply' with the reply id
 */
function psf_trash_reply( $reply_id = 0 ) {
	$reply_id = psf_get_reply_id( $reply_id );

	if ( empty( $reply_id ) || !psf_is_reply( $reply_id ) )
		return false;

	do_action( 'psf_trash_reply', $reply_id );
}

/**
 * Called before untrashing (restoring) a reply
 *
 * @uses psf_get_reply_id() To get the reply id
 * @uses psf_is_reply() To check if the passed id is a reply
 * @uses do_action() Calls 'psf_unstrash_reply' with the reply id
 */
function psf_untrash_reply( $reply_id = 0 ) {
	$reply_id = psf_get_reply_id( $reply_id );

	if ( empty( $reply_id ) || !psf_is_reply( $reply_id ) )
		return false;

	do_action( 'psf_untrash_reply', $reply_id );
}

/** After Delete/Trash/Untrash ************************************************/

/**
 * Called after deleting a reply
 *
 * @uses psf_get_reply_id() To get the reply id
 * @uses psf_is_reply() To check if the passed id is a reply
 * @uses do_action() Calls 'psf_deleted_reply' with the reply id
 */
function psf_deleted_reply( $reply_id = 0 ) {
	$reply_id = psf_get_reply_id( $reply_id );

	if ( empty( $reply_id ) || !psf_is_reply( $reply_id ) )
		return false;

	do_action( 'psf_deleted_reply', $reply_id );
}

/**
 * Called after trashing a reply
 *
 * @uses psf_get_reply_id() To get the reply id
 * @uses psf_is_reply() To check if the passed id is a reply
 * @uses do_action() Calls 'psf_trashed_reply' with the reply id
 */
function psf_trashed_reply( $reply_id = 0 ) {
	$reply_id = psf_get_reply_id( $reply_id );

	if ( empty( $reply_id ) || !psf_is_reply( $reply_id ) )
		return false;

	do_action( 'psf_trashed_reply', $reply_id );
}

/**
 * Called after untrashing (restoring) a reply
 *
 * @uses psf_get_reply_id() To get the reply id
 * @uses psf_is_reply() To check if the passed id is a reply
 * @uses do_action() Calls 'psf_untrashed_reply' with the reply id
 */
function psf_untrashed_reply( $reply_id = 0 ) {
	$reply_id = psf_get_reply_id( $reply_id );

	if ( empty( $reply_id ) || !psf_is_reply( $reply_id ) )
		return false;

	do_action( 'psf_untrashed_reply', $reply_id );
}

/** Settings ******************************************************************/

/**
 * Return the replies per page setting
 *
 * @since PSForum (r3540)
 *
 * @param int $default Default replies per page (15)
 * @uses get_option() To get the setting
 * @uses apply_filters() To allow the return value to be manipulated
 * @return int
 */
function psf_get_replies_per_page( $default = 15 ) {

	// Get database option and cast as integer
	$retval = get_option( '_psf_replies_per_page', $default );

	// If return val is empty, set it to default
	if ( empty( $retval ) )
		$retval = $default;

	// Filter and return
	return (int) apply_filters( 'psf_get_replies_per_page', $retval, $default );
}

/**
 * Return the replies per RSS page setting
 *
 * @since PSForum (r3540)
 *
 * @param int $default Default replies per page (25)
 * @uses get_option() To get the setting
 * @uses apply_filters() To allow the return value to be manipulated
 * @return int
 */
function psf_get_replies_per_rss_page( $default = 25 ) {

	// Get database option and cast as integer
	$retval = get_option( '_psf_replies_per_rss_page', $default );

	// If return val is empty, set it to default
	if ( empty( $retval ) )
		$retval = $default;

	// Filter and return
	return (int) apply_filters( 'psf_get_replies_per_rss_page', $retval, $default );
}

/** Autoembed *****************************************************************/

/**
 * Check if autoembeds are enabled and hook them in if so
 *
 * @since PSForum (r3752)
 * @global WP_Embed $wp_embed
 */
function psf_reply_content_autoembed() {
	global $wp_embed;

	if ( psf_use_autoembed() && is_a( $wp_embed, 'WP_Embed' ) ) {
		add_filter( 'psf_get_reply_content', array( $wp_embed, 'autoembed' ), 2 );
	}
}

/** Filters *******************************************************************/

/**
 * Used by psf_has_replies() to add the lead topic post to the posts loop
 *
 * This function filters the 'post_where' of the WP_Query, and changes the query
 * to include both the topic AND its children in the same loop.
 *
 * @since PSForum (r4058)
 *
 * @param string $where
 * @return string
 */
function _psf_has_replies_where( $where = '', $query = false ) {

	/** Bail ******************************************************************/

	// Bail if the sky is falling
	if ( empty( $where ) || empty( $query ) ) {
		return $where;
	}

	// Bail if no post_parent to replace
	if ( ! is_numeric( $query->get( 'post_parent' ) ) ) {
		return $where;
	}

	// Bail if not a topic and reply query
	if ( array( psf_get_topic_post_type(), psf_get_reply_post_type() ) !== $query->get( 'post_type' ) ) {
		return $where;
	}

	// Bail if including or excluding specific post ID's
	if ( $query->get( 'post__not_in' ) || $query->get( 'post__in' ) ) {
		return $where;
	}

	/** Proceed ***************************************************************/

	global $wpdb;

	// Table name for posts
	$table_name = $wpdb->prefix . 'posts';

	// Get the topic ID from the post_parent, set in psf_has_replies()
	$topic_id   = psf_get_topic_id( $query->get( 'post_parent' ) );

	// The texts to search for
	$search     = array(
		"FROM {$table_name} " ,
		"WHERE 1=1  AND {$table_name}.post_parent = {$topic_id}"
	);

	// The texts to replace them with
	$replace     = array(
		$search[0] . "FORCE INDEX (PRIMARY, post_parent) " ,
		"WHERE 1=1 AND ({$table_name}.ID = {$topic_id} OR {$table_name}.post_parent = {$topic_id})"
	);

	// Try to replace the search text with the replacement
	$new_where = str_replace( $search, $replace, $where );
	if ( ! empty( $new_where ) ) {
		$where = $new_where;
	}

	return $where;
}

/** Feeds *********************************************************************/

/**
 * Output an RSS2 feed of replies, based on the query passed.
 *
 * @since PSForum (r3171)
 *
 * @uses psf_version()
 * @uses psf_is_single_topic()
 * @uses psf_user_can_view_forum()
 * @uses psf_get_topic_forum_id()
 * @uses psf_show_load_topic()
 * @uses psf_topic_permalink()
 * @uses psf_topic_title()
 * @uses psf_get_topic_reply_count()
 * @uses psf_topic_content()
 * @uses psf_has_replies()
 * @uses psf_replies()
 * @uses psf_the_reply()
 * @uses psf_reply_url()
 * @uses psf_reply_title()
 * @uses psf_reply_content()
 * @uses get_wp_title_rss()
 * @uses get_option()
 * @uses bloginfo_rss
 * @uses self_link()
 * @uses the_author()
 * @uses get_post_time()
 * @uses rss_enclosure()
 * @uses do_action()
 * @uses apply_filters()
 *
 * @param array $replies_query
 */
function psf_display_replies_feed_rss2( $replies_query = array() ) {

	// User cannot access forum this topic is in
	if ( psf_is_single_topic() && !psf_user_can_view_forum( array( 'forum_id' => psf_get_topic_forum_id() ) ) )
		return;

	// Adjust the title based on context
	if ( psf_is_single_topic() && psf_user_can_view_forum( array( 'forum_id' => psf_get_topic_forum_id() ) ) )
		$title = apply_filters( 'wp_title_rss', get_wp_title_rss( ' &#187; ' ) );
	elseif ( !psf_show_lead_topic() )
		$title = ' &#187; ' .  __( 'Alle Beiträge',   'psforum' );
	else
		$title = ' &#187; ' .  __( 'Alle Antworten', 'psforum' );

	// Display the feed
	header( 'Content-Type: ' . feed_content_type( 'rss2' ) . '; charset=' . get_option( 'blog_charset' ), true );
	header( 'Status: 200 OK' );
	echo '<?xml version="1.0" encoding="' . get_option( 'blog_charset' ) . '"?' . '>'; ?>

	<rss version="2.0"
		xmlns:content="http://purl.org/rss/1.0/modules/content/"
		xmlns:wfw="http://wellformedweb.org/CommentAPI/"
		xmlns:dc="http://purl.org/dc/elements/1.1/"
		xmlns:atom="http://www.w3.org/2005/Atom"

		<?php do_action( 'psf_feed' ); ?>
	>

	<channel>
		<title><?php bloginfo_rss('name'); echo $title; ?></title>
		<atom:link href="<?php self_link(); ?>" rel="self" type="application/rss+xml" />
		<link><?php self_link(); ?></link>
		<description><?php //?></description>
		<pubDate><?php echo mysql2date( 'D, d M Y H:i:s O', current_time( 'mysql' ), false ); ?></pubDate>
		<generator>http://psforum.org/?v=<?php psf_version(); ?></generator>
		<language><?php bloginfo_rss( 'language' ); ?></language>

		<?php do_action( 'psf_feed_head' ); ?>

		<?php if ( psf_is_single_topic() ) : ?>
			<?php if ( psf_user_can_view_forum( array( 'forum_id' => psf_get_topic_forum_id() ) ) ) : ?>
				<?php if ( psf_show_lead_topic() ) : ?>

					<item>
						<guid><?php psf_topic_permalink(); ?></guid>
						<title><![CDATA[<?php psf_topic_title(); ?>]]></title>
						<link><?php psf_topic_permalink(); ?></link>
						<pubDate><?php echo mysql2date( 'D, d M Y H:i:s +0000', get_post_time( 'Y-m-d H:i:s', true ), false ); ?></pubDate>
						<dc:creator><?php the_author(); ?></dc:creator>

						<description>
							<![CDATA[
							<p><?php printf( __( 'Antworten: %s', 'psforum' ), psf_get_topic_reply_count() ); ?></p>
							<?php psf_topic_content(); ?>
							]]>
						</description>

						<?php rss_enclosure(); ?>

						<?php do_action( 'psf_feed_item' ); ?>

					</item>

				<?php endif; ?>
			<?php endif; ?>
		<?php endif; ?>

		<?php if ( psf_has_replies( $replies_query ) ) : ?>
			<?php while ( psf_replies() ) : psf_the_reply(); ?>

				<item>
					<guid><?php psf_reply_url(); ?></guid>
					<title><![CDATA[<?php psf_reply_title(); ?>]]></title>
					<link><?php psf_reply_url(); ?></link>
					<pubDate><?php echo mysql2date( 'D, d M Y H:i:s +0000', get_post_time( 'Y-m-d H:i:s', true ), false ); ?></pubDate>
					<dc:creator><?php the_author() ?></dc:creator>

					<description>
						<![CDATA[
						<?php psf_reply_content(); ?>
						]]>
					</description>

					<?php rss_enclosure(); ?>

					<?php do_action( 'psf_feed_item' ); ?>

				</item>

			<?php endwhile; ?>
		<?php endif; ?>

		<?php do_action( 'psf_feed_footer' ); ?>

	</channel>
	</rss>

<?php

	// We're done here
	exit();
}

/** Permissions ***************************************************************/

/**
 * Redirect if unathorized user is attempting to edit a reply
 *
 * @since PSForum (r3605)
 *
 * @uses psf_is_reply_edit()
 * @uses current_user_can()
 * @uses psf_get_topic_id()
 * @uses wp_safe_redirect()
 * @uses psf_get_topic_permalink()
 */
function psf_check_reply_edit() {

	// Bail if not editing a topic
	if ( !psf_is_reply_edit() )
		return;

	// User cannot edit topic, so redirect back to reply
	if ( !current_user_can( 'edit_reply', psf_get_reply_id() ) ) {
		wp_safe_redirect( psf_get_reply_url() );
		exit();
	}
}

/** Reply Position ************************************************************/

/**
 * Update the position of the reply.
 *
 * The reply position is stored in the menu_order column of the posts table.
 * This is done to prevent using a meta_query to retrieve posts in the proper
 * freshness order. By updating the menu_order accordingly, we're able to
 * leverage core WordPress query ordering much more effectively.
 *
 * @since PSForum (r3933)
 *
 * @global type $wpdb
 * @param type $reply_id
 * @param type $reply_position
 * @return mixed
 */
function psf_update_reply_position( $reply_id = 0, $reply_position = 0 ) {

	// Bail if reply_id is empty
	$reply_id = psf_get_reply_id( $reply_id );
	if ( empty( $reply_id ) )
		return false;

	// If no position was passed, get it from the db and update the menu_order
	if ( empty( $reply_position ) ) {
		$reply_position = psf_get_reply_position_raw( $reply_id, psf_get_reply_topic_id( $reply_id ) );
	}

	// Update the replies' 'menp_order' with the reply position
	wp_update_post( array(
		'ID'         => $reply_id,
		'menu_order' => $reply_position
	) );

	return (int) $reply_position;
}

/**
 * Get the position of a reply by querying the DB directly for the replies
 * of a given topic.
 *
 * @since PSForum (r3933)
 *
 * @param int $reply_id
 * @param int $topic_id
 */
function psf_get_reply_position_raw( $reply_id = 0, $topic_id = 0 ) {

	// Get required data
	$reply_id       = psf_get_reply_id( $reply_id );
	$topic_id       = !empty( $topic_id ) ? psf_get_topic_id( $topic_id ) : psf_get_reply_topic_id( $reply_id );
	$reply_position = 0;

	// If reply is actually the first post in a topic, return 0
	if ( $reply_id !== $topic_id ) {

		// Make sure the topic has replies before running another query
		$reply_count = psf_get_topic_reply_count( $topic_id, false );
		if ( !empty( $reply_count ) ) {

			// Get reply id's
			$topic_replies = psf_get_all_child_ids( $topic_id, psf_get_reply_post_type() );
			if ( !empty( $topic_replies ) ) {

				// Reverse replies array and search for current reply position
				$topic_replies  = array_reverse( $topic_replies );
				$reply_position = array_search( (string) $reply_id, $topic_replies );

				// Bump the position to compensate for the lead topic post
				$reply_position++;
			}
		}
	}

	return (int) $reply_position;
}

/** Hierarchical Replies ******************************************************/

/**
 * List replies
 *
 * @since PSForum (r4944)
 */
function psf_list_replies( $args = array() ) {

	// Reset the reply depth
	psforum()->reply_query->reply_depth = 0;

	// In reply loop
	psforum()->reply_query->in_the_loop = true;

	$r = psf_parse_args( $args, array(
		'walker'       => null,
		'max_depth'    => psf_thread_replies_depth(),
		'style'        => 'ul',
		'callback'     => null,
		'end_callback' => null,
		'page'         => 1,
		'per_page'     => -1
	), 'list_replies' );

	// Get replies to loop through in $_replies
	$walker = new PSF_Walker_Reply;
	$walker->paged_walk( psforum()->reply_query->posts, $r['max_depth'], $r['page'], $r['per_page'], $r );

	psforum()->max_num_pages            = $walker->max_pages;
	psforum()->reply_query->in_the_loop = false;
}

/**
 * Validate a `reply_to` field for hierarchical replies
 *
 * Checks for 2 scenarios:
 * -- The reply to ID is actually a reply
 * -- The reply to ID does not match the current reply
 *
 * @since PSForum (r5377)
 *
 * @param int $reply_to
 * @param int $reply_id
 *
 * @return int $reply_to
 */
function psf_validate_reply_to( $reply_to = 0, $reply_id = 0 ) {

	// The parent reply must actually be a reply
	if ( ! psf_is_reply( $reply_to ) ) {
		$reply_to = 0;
	}

	// The parent reply cannot be itself
	if ( $reply_id === $reply_to ) {
		$reply_to = 0;
	}

	return (int) $reply_to;
}
