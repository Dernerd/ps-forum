<?php

/**
 * Anonymous User
 *
 * @package PSForum
 * @subpackage Theme
 */

?>

<?php if ( psf_current_user_can_access_anonymous_user_form() ) : ?>

	<?php do_action( 'psf_theme_before_anonymous_form' ); ?>

	<fieldset class="psf-form">
		<legend><?php ( psf_is_topic_edit() || psf_is_reply_edit() ) ? _e( 'Autor Information', 'psforum' ) : _e( 'Dein information:', 'psforum' ); ?></legend>

		<?php do_action( 'psf_theme_anonymous_form_extras_top' ); ?>

		<p>
			<label for="psf_anonymous_author"><?php _e( 'Name (erforderlich):', 'psforum' ); ?></label><br />
			<input type="text" id="psf_anonymous_author"  value="<?php psf_author_display_name(); ?>" tabindex="<?php psf_tab_index(); ?>" size="40" name="psf_anonymous_name" />
		</p>

		<p>
			<label for="psf_anonymous_email"><?php _e( 'eMail (wird nicht veröffentlicht) (erforderlich):', 'psforum' ); ?></label><br />
			<input type="text" id="psf_anonymous_email"   value="<?php psf_author_email(); ?>" tabindex="<?php psf_tab_index(); ?>" size="40" name="psf_anonymous_email" />
		</p>

		<p>
			<label for="psf_anonymous_website"><?php _e( 'Webseite:', 'psforum' ); ?></label><br />
			<input type="text" id="psf_anonymous_website" value="<?php psf_author_url(); ?>" tabindex="<?php psf_tab_index(); ?>" size="40" name="psf_anonymous_website" />
		</p>

		<?php do_action( 'psf_theme_anonymous_form_extras_bottom' ); ?>

	</fieldset>

	<?php do_action( 'psf_theme_after_anonymous_form' ); ?>

<?php endif; ?>
