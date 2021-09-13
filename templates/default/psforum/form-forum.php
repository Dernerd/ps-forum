<?php

/**
 * New/Edit Forum
 *
 * @package PSForum
 * @subpackage Theme
 */

?>

<?php if ( psf_is_forum_edit() ) : ?>

<div id="psforum-forums">

	<?php psf_breadcrumb(); ?>

	<?php psf_single_forum_description( array( 'forum_id' => psf_get_forum_id() ) ); ?>

<?php endif; ?>

<?php if ( psf_current_user_can_access_create_forum_form() ) : ?>

	<div id="new-forum-<?php psf_forum_id(); ?>" class="psf-forum-form">

		<form id="new-post" name="new-post" method="post" action="<?php the_permalink(); ?>">

			<?php do_action( 'psf_theme_before_forum_form' ); ?>

			<fieldset class="psf-form">
				<legend>

					<?php
						if ( psf_is_forum_edit() )
							printf( __( 'Jetzt am bearbeiten &ldquo;%s&rdquo;', 'psforum' ), psf_get_forum_title() );
						else
							psf_is_single_forum() ? printf( __( 'Neues Forum erstellen in &ldquo;%s&rdquo;', 'psforum' ), psf_get_forum_title() ) : _e( 'Neues Forum erstellen', 'psforum' );
					?>

				</legend>

				<?php do_action( 'psf_theme_before_forum_form_notices' ); ?>

				<?php if ( !psf_is_forum_edit() && psf_is_forum_closed() ) : ?>

					<div class="psf-template-notice">
						<p><?php _e( 'Dieses Forum ist für neue Inhalte geschlossen, Dein Konto erlaubt Dir dies jedoch weiterhin.', 'psforum' ); ?></p>
					</div>

				<?php endif; ?>

				<?php if ( current_user_can( 'unfiltered_html' ) ) : ?>

					<div class="psf-template-notice">
						<p><?php _e( 'Dein Konto hat die Möglichkeit, uneingeschränkte HTML-Inhalte zu posten.', 'psforum' ); ?></p>
					</div>

				<?php endif; ?>

				<?php do_action( 'psf_template_notices' ); ?>

				<div>

					<?php do_action( 'psf_theme_before_forum_form_title' ); ?>

					<p>
						<label for="psf_forum_title"><?php printf( __( 'Forumsname (Maximale Länge: %d):', 'psforum' ), psf_get_title_max_length() ); ?></label><br />
						<input type="text" id="psf_forum_title" value="<?php psf_form_forum_title(); ?>" tabindex="<?php psf_tab_index(); ?>" size="40" name="psf_forum_title" maxlength="<?php psf_title_max_length(); ?>" />
					</p>

					<?php do_action( 'psf_theme_after_forum_form_title' ); ?>

					<?php do_action( 'psf_theme_before_forum_form_content' ); ?>

					<?php psf_the_content( array( 'context' => 'forum' ) ); ?>

					<?php do_action( 'psf_theme_after_forum_form_content' ); ?>

					<?php if ( ! ( psf_use_wp_editor() || current_user_can( 'unfiltered_html' ) ) ) : ?>

						<p class="form-allowed-tags">
							<label><?php _e( 'Du kannst diese <abbr title="HyperText Markup Language">HTML</abbr>-Tags und -Attribute verwenden:','psforum' ); ?></label><br />
							<code><?php psf_allowed_tags(); ?></code>
						</p>

					<?php endif; ?>

					<?php do_action( 'psf_theme_before_forum_form_type' ); ?>

					<p>
						<label for="psf_forum_type"><?php _e( 'Forumstyp:', 'psforum' ); ?></label><br />
						<?php psf_form_forum_type_dropdown(); ?>
					</p>

					<?php do_action( 'psf_theme_after_forum_form_type' ); ?>

					<?php do_action( 'psf_theme_before_forum_form_status' ); ?>

					<p>
						<label for="psf_forum_status"><?php _e( 'Status:', 'psforum' ); ?></label><br />
						<?php psf_form_forum_status_dropdown(); ?>
					</p>

					<?php do_action( 'psf_theme_after_forum_form_status' ); ?>

					<?php do_action( 'psf_theme_before_forum_form_status' ); ?>

					<p>
						<label for="psf_forum_visibility"><?php _e( 'Sichtbarkeit:', 'psforum' ); ?></label><br />
						<?php psf_form_forum_visibility_dropdown(); ?>
					</p>

					<?php do_action( 'psf_theme_after_forum_visibility_status' ); ?>

					<?php do_action( 'psf_theme_before_forum_form_parent' ); ?>

					<p>
						<label for="psf_forum_parent_id"><?php _e( 'Elternforum:', 'psforum' ); ?></label><br />

						<?php
							psf_dropdown( array(
								'select_id' => 'psf_forum_parent_id',
								'show_none' => __( '(Kein Elternteil)', 'psforum' ),
								'selected'  => psf_get_form_forum_parent(),
								'exclude'   => psf_get_forum_id()
							) );
						?>
					</p>

					<?php do_action( 'psf_theme_after_forum_form_parent' ); ?>

					<?php do_action( 'psf_theme_before_forum_form_submit_wrapper' ); ?>

					<div class="psf-submit-wrapper">

						<?php do_action( 'psf_theme_before_forum_form_submit_button' ); ?>

						<button type="submit" tabindex="<?php psf_tab_index(); ?>" id="psf_forum_submit" name="psf_forum_submit" class="button submit"><?php _e( 'Einreichen', 'psforum' ); ?></button>

						<?php do_action( 'psf_theme_after_forum_form_submit_button' ); ?>

					</div>

					<?php do_action( 'psf_theme_after_forum_form_submit_wrapper' ); ?>

				</div>

				<?php psf_forum_form_fields(); ?>

			</fieldset>

			<?php do_action( 'psf_theme_after_forum_form' ); ?>

		</form>
	</div>

<?php elseif ( psf_is_forum_closed() ) : ?>

	<div id="no-forum-<?php psf_forum_id(); ?>" class="psf-no-forum">
		<div class="psf-template-notice">
			<p><?php printf( __( 'Das Forum &#8216;%s&#8217; ist für neue Inhalte geschlossen.', 'psforum' ), psf_get_forum_title() ); ?></p>
		</div>
	</div>

<?php else : ?>

	<div id="no-forum-<?php psf_forum_id(); ?>" class="psf-no-forum">
		<div class="psf-template-notice">
			<p><?php is_user_logged_in() ? _e( 'Du kannst keine neuen Foren erstellen.', 'psforum' ) : _e( 'Du musst angemeldet sein, um neue Foren zu erstellen.', 'psforum' ); ?></p>
		</div>
	</div>

<?php endif; ?>

<?php if ( psf_is_forum_edit() ) : ?>

</div>

<?php endif; ?>
