<?php

/**
 * PSForum Admin Metaboxes
 *
 * @package PSForum
 * @subpackage Administration
 */

/** Dashboard *****************************************************************/

/**
 * PSForum Dashboard Right Now Widget
 *
 * Adds a dashboard widget with forum statistics
 *
 * @since PSForum (r2770)
 *
 * @uses psf_get_version() To get the current PSForum version
 * @uses psf_get_statistics() To get the forum statistics
 * @uses current_user_can() To check if the user is capable of doing things
 * @uses psf_get_forum_post_type() To get the forum post type
 * @uses psf_get_topic_post_type() To get the topic post type
 * @uses psf_get_reply_post_type() To get the reply post type
 * @uses get_admin_url() To get the administration url
 * @uses add_query_arg() To add custom args to the url
 * @uses do_action() Calls 'psf_dashboard_widget_right_now_content_table_end'
 *                    below the content table
 * @uses do_action() Calls 'psf_dashboard_widget_right_now_table_end'
 *                    below the discussion table
 * @uses do_action() Calls 'psf_dashboard_widget_right_now_discussion_table_end'
 *                    below the discussion table
 * @uses do_action() Calls 'psf_dashboard_widget_right_now_end' below the widget
 */
function psf_dashboard_widget_right_now() {

	// Get the statistics
	$r = psf_get_statistics(); ?>

	<div class="table table_content">

		<p class="sub"><?php esc_html_e( 'Diskussion', 'psforum' ); ?></p>

		<table>

			<tr class="first">

				<?php
					$num  = $r['forum_count'];
					$text = _n( 'Forum', 'Foren', $r['forum_count'], 'psforum' );
					if ( current_user_can( 'publish_forums' ) ) {
						$link = add_query_arg( array( 'post_type' => psf_get_forum_post_type() ), get_admin_url( null, 'edit.php' ) );
						$num  = '<a href="' . esc_url( $link ) . '">' . $num  . '</a>';
						$text = '<a href="' . esc_url( $link ) . '">' . $text . '</a>';
					}
				?>

				<td class="first b b-forums"><?php echo $num; ?></td>
				<td class="t forums"><?php echo $text; ?></td>

			</tr>

			<tr>

				<?php
					$num  = $r['topic_count'];
					$text = _n( 'Topic', 'Themen', $r['topic_count'], 'psforum' );
					if ( current_user_can( 'publish_topics' ) ) {
						$link = add_query_arg( array( 'post_type' => psf_get_topic_post_type() ), get_admin_url( null, 'edit.php' ) );
						$num  = '<a href="' . esc_url( $link ) . '">' . $num  . '</a>';
						$text = '<a href="' . esc_url( $link ) . '">' . $text . '</a>';
					}
				?>

				<td class="first b b-topics"><?php echo $num; ?></td>
				<td class="t topics"><?php echo $text; ?></td>

			</tr>

			<tr>

				<?php
					$num  = $r['reply_count'];
					$text = _n( 'Antwort', 'Antworten', $r['reply_count'], 'psforum' );
					if ( current_user_can( 'publish_replies' ) ) {
						$link = add_query_arg( array( 'post_type' => psf_get_reply_post_type() ), get_admin_url( null, 'edit.php' ) );
						$num  = '<a href="' . esc_url( $link ) . '">' . $num  . '</a>';
						$text = '<a href="' . esc_url( $link ) . '">' . $text . '</a>';
					}
				?>

				<td class="first b b-replies"><?php echo $num; ?></td>
				<td class="t replies"><?php echo $text; ?></td>

			</tr>

			<?php if ( psf_allow_topic_tags() ) : ?>

				<tr>

					<?php
						$num  = $r['topic_tag_count'];
						$text = _n( 'Themen-Tag', 'Topic Tags', $r['topic_tag_count'], 'psforum' );
						if ( current_user_can( 'manage_topic_tags' ) ) {
							$link = add_query_arg( array( 'taxonomy' => psf_get_topic_tag_tax_id(), 'post_type' => psf_get_topic_post_type() ), get_admin_url( null, 'edit-tags.php' ) );
							$num  = '<a href="' . esc_url( $link ) . '">' . $num  . '</a>';
							$text = '<a href="' . esc_url( $link ) . '">' . $text . '</a>';
						}
					?>

					<td class="first b b-topic_tags"><span class="total-count"><?php echo $num; ?></span></td>
					<td class="t topic_tags"><?php echo $text; ?></td>

				</tr>

			<?php endif; ?>

			<?php do_action( 'psf_dashboard_widget_right_now_content_table_end' ); ?>

		</table>

	</div>


	<div class="table table_discussion">

		<p class="sub"><?php esc_html_e( 'Benutzer &amp; Moderation', 'psforum' ); ?></p>

		<table>

			<tr class="first">

				<?php
					$num  = $r['user_count'];
					$text = _n( 'Benutzer', 'Benutzer', $r['user_count'], 'psforum' );
					if ( current_user_can( 'edit_users' ) ) {
						$link = get_admin_url( null, 'users.php' );
						$num  = '<a href="' . esc_url( $link ) . '">' . $num  . '</a>';
						$text = '<a href="' . esc_url( $link ) . '">' . $text . '</a>';
					}
				?>

				<td class="b b-users"><span class="total-count"><?php echo $num; ?></span></td>
				<td class="last t users"><?php echo $text; ?></td>

			</tr>

			<?php if ( isset( $r['topic_count_hidden'] ) ) : ?>

				<tr>

					<?php
						$num  = $r['topic_count_hidden'];
						$text = _n( 'Verstecktes Thema', 'Versteckte Themen', $r['topic_count_hidden'], 'psforum' );
						$link = add_query_arg( array( 'post_type' => psf_get_topic_post_type() ), get_admin_url( null, 'edit.php' ) );
						if ( '0' !== $num ) {
							$link = add_query_arg( array( 'post_status' => psf_get_spam_status_id() ), $link );
						}
                        $num  = '<a href="' . esc_url( $link ) . '" title="' . esc_attr( $r['hidden_topic_title'] ) . '">' . $num  . '</a>';
						$text = '<a class="waiting" href="' . esc_url( $link ) . '" title="' . esc_attr( $r['hidden_topic_title'] ) . '">' . $text . '</a>';
					?>

					<td class="b b-hidden-topics"><?php echo $num; ?></td>
					<td class="last t hidden-replies"><?php echo $text; ?></td>

				</tr>

			<?php endif; ?>

			<?php if ( isset( $r['reply_count_hidden'] ) ) : ?>

				<tr>

					<?php
						$num  = $r['reply_count_hidden'];
						$text = _n( 'Versteckte Antwort', 'Versteckte Antworten', $r['reply_count_hidden'], 'psforum' );
						$link = add_query_arg( array( 'post_type' => psf_get_reply_post_type() ), get_admin_url( null, 'edit.php' ) );
						if ( '0' !== $num ) {
							$link = add_query_arg( array( 'post_status' => psf_get_spam_status_id() ), $link );
						}
                        $num  = '<a href="' . esc_url( $link ) . '" title="' . esc_attr( $r['hidden_reply_title'] ) . '">' . $num  . '</a>';
						$text = '<a class="waiting" href="' . esc_url( $link ) . '" title="' . esc_attr( $r['hidden_reply_title'] ) . '">' . $text . '</a>';
					?>

					<td class="b b-hidden-replies"><?php echo $num; ?></td>
					<td class="last t hidden-replies"><?php echo $text; ?></td>

				</tr>

			<?php endif; ?>

			<?php if ( psf_allow_topic_tags() && isset( $r['empty_topic_tag_count'] ) ) : ?>

				<tr>

					<?php
						$num  = $r['empty_topic_tag_count'];
						$text = _n( 'Leeres Themen-Tag', 'Leere Themen-Tags', $r['empty_topic_tag_count'], 'psforum' );
						$link = add_query_arg( array( 'taxonomy' => psf_get_topic_tag_tax_id(), 'post_type' => psf_get_topic_post_type() ), get_admin_url( null, 'edit-tags.php' ) );
						$num  = '<a href="' . esc_url( $link ) . '">' . $num  . '</a>';
						$text = '<a class="waiting" href="' . esc_url( $link ) . '">' . $text . '</a>';
					?>

					<td class="b b-hidden-topic-tags"><?php echo $num; ?></td>
					<td class="last t hidden-topic-tags"><?php echo $text; ?></td>

				</tr>

			<?php endif; ?>

			<?php do_action( 'psf_dashboard_widget_right_now_discussion_table_end' ); ?>

		</table>

	</div>

	<?php do_action( 'psf_dashboard_widget_right_now_table_end' ); ?>

	<div class="versions">

		<span id="wp-version-message">
			<?php printf( __( 'Du verwendest <span class="b">PS Forum %s</span>.', 'psforum' ), psf_get_version() ); ?>
		</span>

	</div>

	<br class="clear" />

	<?php

	do_action( 'psf_dashboard_widget_right_now_end' );
}

/** Forums ********************************************************************/

/**
 * Forum metabox
 *
 * The metabox that holds all of the additional forum information
 *
 * @since PSForum (r2744)
 *
 * @uses psf_is_forum_closed() To check if a forum is closed or not
 * @uses psf_is_forum_category() To check if a forum is a category or not
 * @uses psf_is_forum_private() To check if a forum is private or not
 * @uses psf_dropdown() To show a dropdown of the forums for forum parent
 * @uses do_action() Calls 'psf_forum_metabox'
 */
function psf_forum_metabox() {

	// Post ID
	$post_id     = get_the_ID();
	$post_parent = psf_get_global_post_field( 'post_parent', 'raw'  );
	$menu_order  = psf_get_global_post_field( 'menu_order',  'edit' );

	/** Type ******************************************************************/

	?>

	<p>
		<strong class="label"><?php esc_html_e( 'Typ:', 'psforum' ); ?></strong>
		<label class="screen-reader-text" for="psf_forum_type_select"><?php esc_html_e( 'Typ:', 'psforum' ) ?></label>
		<?php psf_form_forum_type_dropdown( array( 'forum_id' => $post_id ) ); ?>
	</p>

	<?php

	/** Status ****************************************************************/

	?>

	<p>
		<strong class="label"><?php esc_html_e( 'Status:', 'psforum' ); ?></strong>
		<label class="screen-reader-text" for="psf_forum_status_select"><?php esc_html_e( 'Status:', 'psforum' ) ?></label>
		<?php psf_form_forum_status_dropdown( array( 'forum_id' => $post_id ) ); ?>
	</p>

	<?php

	/** Visibility ************************************************************/

	?>

	<p>
		<strong class="label"><?php esc_html_e( 'Sichtbarkeit', 'psforum' ); ?></strong>
		<label class="screen-reader-text" for="psf_forum_visibility_select"><?php esc_html_e( 'Sichtbarkeit:', 'psforum' ) ?></label>
		<?php psf_form_forum_visibility_dropdown( array( 'forum_id' => $post_id ) ); ?>
	</p>

	<hr />

	<?php

	/** Parent ****************************************************************/

	?>

	<p>
		<strong class="label"><?php esc_html_e( 'Eltern:', 'psforum' ); ?></strong>
		<label class="screen-reader-text" for="parent_id"><?php esc_html_e( 'Forum Eltern', 'psforum' ); ?></label>
		<?php psf_dropdown( array(
			'post_type'          => psf_get_forum_post_type(),
			'selected'           => $post_parent,
			'numberposts'        => -1,
			'orderby'            => 'title',
			'order'              => 'ASC',
			'walker'             => '',
			'exclude'            => $post_id,

			// Output-related
			'select_id'          => 'parent_id',
			'tab'                => psf_get_tab_index(),
			'options_only'       => false,
			'show_none'          => __( '&mdash; Keine Eltern &mdash;', 'psforum' ),
			'disable_categories' => false,
			'disabled'           => ''
		) ); ?>
	</p>

	<p>
		<strong class="label"><?php esc_html_e( 'Sortierung:', 'psforum' ); ?></strong>
		<label class="screen-reader-text" for="menu_order"><?php esc_html_e( 'Forenbestellung', 'psforum' ); ?></label>
		<input name="menu_order" type="number" step="1" size="4" id="menu_order" value="<?php echo esc_attr( $menu_order ); ?>" />
	</p>

	<input name="ping_status" type="hidden" id="ping_status" value="open" />

	<?php
	wp_nonce_field( 'psf_forum_metabox_save', 'psf_forum_metabox' );
	do_action( 'psf_forum_metabox', $post_id );
}

/** Topics ********************************************************************/

/**
 * Topic metabox
 *
 * The metabox that holds all of the additional topic information
 *
 * @since PSForum (r2464)
 *
 * @uses psf_get_topic_forum_id() To get the topic forum id
 * @uses do_action() Calls 'psf_topic_metabox'
 */
function psf_topic_metabox() {

	// Post ID
	$post_id = get_the_ID();

	/** Type ******************************************************************/

	?>

	<p>
		<strong class="label"><?php esc_html_e( 'Typ:', 'psforum' ); ?></strong>
		<label class="screen-reader-text" for="psf_stick_topic"><?php esc_html_e( 'Thementyp', 'psforum' ); ?></label>
		<?php psf_form_topic_type_dropdown( array( 'topic_id' => $post_id ) ); ?>
	</p>

	<?php

	/** Status ****************************************************************/

	?>

	<p>
		<strong class="label"><?php esc_html_e( 'Status:', 'psforum' ); ?></strong>
		<label class="screen-reader-text" for="psf_open_close_topic"><?php esc_html_e( 'Wähle aus, ob das Thema geöffnet oder geschlossen werden soll.', 'psforum' ); ?></label>
		<?php psf_form_topic_status_dropdown( array( 'select_id' => 'post_status', 'topic_id' => $post_id ) ); ?>
	</p>

	<?php

	/** Parent *****************************************************************/

	?>

	<p>
		<strong class="label"><?php esc_html_e( 'Forum:', 'psforum' ); ?></strong>
		<label class="screen-reader-text" for="parent_id"><?php esc_html_e( 'Forum', 'psforum' ); ?></label>
		<?php psf_dropdown( array(
			'post_type'          => psf_get_forum_post_type(),
			'selected'           => psf_get_topic_forum_id( $post_id ),
			'numberposts'        => -1,
			'orderby'            => 'title',
			'order'              => 'ASC',
			'walker'             => '',
			'exclude'            => '',

			// Output-related
			'select_id'          => 'parent_id',
			'tab'                => psf_get_tab_index(),
			'options_only'       => false,
			'show_none'          => __( '&mdash; Keine Eltern &mdash;', 'psforum' ),
			'disable_categories' => current_user_can( 'edit_forums' ),
			'disabled'           => ''
		) ); ?>
	</p>

	<input name="ping_status" type="hidden" id="ping_status" value="open" />

	<?php
	wp_nonce_field( 'psf_topic_metabox_save', 'psf_topic_metabox' );
	do_action( 'psf_topic_metabox', $post_id );
}

/** Replies *******************************************************************/

/**
 * Reply metabox
 *
 * The metabox that holds all of the additional reply information
 *
 * @since PSForum (r2464)
 *
 * @uses psf_get_topic_post_type() To get the topic post type
 * @uses do_action() Calls 'psf_reply_metabox'
 */
function psf_reply_metabox() {

	// Post ID
	$post_id = get_the_ID();

	// Get some meta
	$reply_topic_id = psf_get_reply_topic_id( $post_id );
	$reply_forum_id = psf_get_reply_forum_id( $post_id );
	$reply_to       = psf_get_reply_to(       $post_id );

	// Allow individual manipulation of reply forum
	if ( current_user_can( 'edit_others_replies' ) || current_user_can( 'moderate' ) ) : ?>

		<p>
			<strong class="label"><?php esc_html_e( 'Forum:', 'psforum' ); ?></strong>
			<label class="screen-reader-text" for="psf_forum_id"><?php esc_html_e( 'Forum', 'psforum' ); ?></label>
			<?php psf_dropdown( array(
				'post_type'          => psf_get_forum_post_type(),
				'selected'           => $reply_forum_id,
				'numberposts'        => -1,
				'orderby'            => 'title',
				'order'              => 'ASC',
				'walker'             => '',
				'exclude'            => '',

				// Output-related
				'select_id'          => 'psf_forum_id',
				'tab'                => psf_get_tab_index(),
				'options_only'       => false,
				'show_none'          => __( '&mdash; Keine Eltern &mdash;', 'psforum' ),
				'disable_categories' => current_user_can( 'edit_forums' ),
				'disabled'           => ''
			) ); ?>
		</p>

	<?php endif; ?>

	<p>
		<strong class="label"><?php esc_html_e( 'Thema:', 'psforum' ); ?></strong>
		<label class="screen-reader-text" for="parent_id"><?php esc_html_e( 'Thema', 'psforum' ); ?></label>
		<input name="parent_id" id="psf_topic_id" type="text" value="<?php echo esc_attr( $reply_topic_id ); ?>" data-ajax-url="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'psf_suggest_topic' ), admin_url( 'admin-ajax.php', 'relative' ) ) ), 'psf_suggest_topic_nonce' ); ?>" />
	</p>

	<p>
		<strong class="label"><?php esc_html_e( 'Antwort an:', 'psforum' ); ?></strong>
		<label class="screen-reader-text" for="psf_reply_to"><?php esc_html_e( 'Antwort an', 'psforum' ); ?></label>
		<input name="psf_reply_to" id="psf_reply_to" type="text" value="<?php echo esc_attr( $reply_to ); ?>" />
	</p>

	<input name="ping_status" type="hidden" id="ping_status" value="open" />

	<?php
	wp_nonce_field( 'psf_reply_metabox_save', 'psf_reply_metabox' );
	do_action( 'psf_reply_metabox', $post_id );
}

/** Users *********************************************************************/

/**
 * Anonymous user information metabox
 *
 * @since PSForum (r2828)
 *
 * @uses psf_is_reply_anonymous() To check if reply is anonymous
 * @uses psf_is_topic_anonymous() To check if topic is anonymous
 * @uses get_the_ID() To get the global post ID
 * @uses get_post_meta() To get the author user information
 */
function psf_author_metabox() {

	// Post ID
	$post_id = get_the_ID();

	// Show extra bits if topic/reply is anonymous
	if ( psf_is_reply_anonymous( $post_id ) || psf_is_topic_anonymous( $post_id ) ) : ?>

		<p>
			<strong class="label"><?php esc_html_e( 'Name:', 'psforum' ); ?></strong>
			<label class="screen-reader-text" for="psf_anonymous_name"><?php esc_html_e( 'Name', 'psforum' ); ?></label>
			<input type="text" id="psf_anonymous_name" name="psf_anonymous_name" value="<?php echo esc_attr( get_post_meta( $post_id, '_psf_anonymous_name', true ) ); ?>" />
		</p>

		<p>
			<strong class="label"><?php esc_html_e( 'Email:', 'psforum' ); ?></strong>
			<label class="screen-reader-text" for="psf_anonymous_email"><?php esc_html_e( 'Email', 'psforum' ); ?></label>
			<input type="text" id="psf_anonymous_email" name="psf_anonymous_email" value="<?php echo esc_attr( get_post_meta( $post_id, '_psf_anonymous_email', true ) ); ?>" />
		</p>

		<p>
			<strong class="label"><?php esc_html_e( 'Webseite:', 'psforum' ); ?></strong>
			<label class="screen-reader-text" for="psf_anonymous_website"><?php esc_html_e( 'Webseite', 'psforum' ); ?></label>
			<input type="text" id="psf_anonymous_website" name="psf_anonymous_website" value="<?php echo esc_attr( get_post_meta( $post_id, '_psf_anonymous_website', true ) ); ?>" />
		</p>

	<?php else : ?>

		<p>
			<strong class="label"><?php esc_html_e( 'ID:', 'psforum' ); ?></strong>
			<label class="screen-reader-text" for="psf_author_id"><?php esc_html_e( 'ID', 'psforum' ); ?></label>
			<input type="text" id="psf_author_id" name="post_author_override" value="<?php echo esc_attr( psf_get_global_post_field( 'post_author' ) ); ?>" data-ajax-url="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'psf_suggest_user' ), admin_url( 'admin-ajax.php', 'relative' ) ) ), 'psf_suggest_user_nonce' ); ?>" />
		</p>

	<?php endif; ?>

	<p>
		<strong class="label"><?php esc_html_e( 'IP:', 'psforum' ); ?></strong>
		<label class="screen-reader-text" for="psf_author_ip_address"><?php esc_html_e( 'IP Addresse', 'psforum' ); ?></label>
		<input type="text" id="psf_author_ip_address" name="psf_author_ip_address" value="<?php echo esc_attr( get_post_meta( $post_id, '_psf_author_ip', true ) ); ?>" disabled="disabled" />
	</p>

	<?php

	do_action( 'psf_author_metabox', $post_id );
}
