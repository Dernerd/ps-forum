<?php

/**
 * User Topics Created
 *
 * @package PSForum
 * @subpackage Theme
 */

?>

	<?php do_action( 'psf_template_before_user_topics_created' ); ?>

	<div id="psf-user-topics-started" class="psf-user-topics-started">
		<h2 class="entry-title"><?php _e( 'Forenthemen gestartet', 'psforum' ); ?></h2>
		<div class="psf-user-section">

			<?php if ( psf_get_user_topics_started() ) : ?>

				<?php psf_get_template_part( 'pagination', 'topics' ); ?>

				<?php psf_get_template_part( 'loop',       'topics' ); ?>

				<?php psf_get_template_part( 'pagination', 'topics' ); ?>

			<?php else : ?>

				<p><?php psf_is_user_home() ? _e( 'Du hast keine Themen erstellt.', 'psforum' ) : _e( 'Dieser Benutzer hat keine Themen erstellt.', 'psforum' ); ?></p>

			<?php endif; ?>

		</div>
	</div><!-- #psf-user-topics-started -->

	<?php do_action( 'psf_template_after_user_topics_created' ); ?>
