<?php

/**
 * User Subscriptions
 *
 * @package PSForum
 * @subpackage Theme
 */

?>

	<?php do_action( 'psf_template_before_user_subscriptions' ); ?>

	<?php if ( psf_is_subscriptions_active() ) : ?>

		<?php if ( psf_is_user_home() || current_user_can( 'edit_users' ) ) : ?>

			<div id="psf-user-subscriptions" class="psf-user-subscriptions">
				<h2 class="entry-title"><?php _e( 'Abonnierte Foren', 'psforum' ); ?></h2>
				<div class="psf-user-section">

					<?php if ( psf_get_user_forum_subscriptions() ) : ?>

						<?php psf_get_template_part( 'loop', 'forums' ); ?>

					<?php else : ?>

						<p><?php psf_is_user_home() ? _e( 'Du hast derzeit keine Foren abonniert.', 'psforum' ) : _e( 'Dieser Benutzer hat derzeit keine Foren abonniert.', 'psforum' ); ?></p>

					<?php endif; ?>

				</div>

				<h2 class="entry-title"><?php _e( 'Abonnierte Themen', 'psforum' ); ?></h2>
				<div class="psf-user-section">

					<?php if ( psf_get_user_topic_subscriptions() ) : ?>

						<?php psf_get_template_part( 'pagination', 'topics' ); ?>

						<?php psf_get_template_part( 'loop',       'topics' ); ?>

						<?php psf_get_template_part( 'pagination', 'topics' ); ?>

					<?php else : ?>

						<p><?php psf_is_user_home() ? _e( 'Du hast derzeit keine Themen abonniert.', 'psforum' ) : _e( 'Dieser Benutzer hat derzeit keine Themen abonniert.', 'psforum' ); ?></p>

					<?php endif; ?>

				</div>
			</div><!-- #psf-user-subscriptions -->

		<?php endif; ?>

	<?php endif; ?>

	<?php do_action( 'psf_template_after_user_subscriptions' ); ?>
