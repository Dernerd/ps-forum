<?php

/**
 * User Details
 *
 * @package PSForum
 * @subpackage Theme
 */

?>

	<?php do_action( 'psf_template_before_user_details' ); ?>

	<div id="psf-single-user-details">
		<div id="psf-user-avatar">

			<span class='vcard'>
				<a class="url fn n" href="<?php psf_user_profile_url(); ?>" title="<?php psf_displayed_user_field( 'display_name' ); ?>" rel="me">
					<?php echo get_avatar( psf_get_displayed_user_field( 'user_email', 'raw' ), apply_filters( 'psf_single_user_details_avatar_size', 150 ) ); ?>
				</a>
			</span>

		</div><!-- #author-avatar -->

		<div id="psf-user-navigation">
			<ul>
				<li class="<?php if ( psf_is_single_user_profile() ) :?>current<?php endif; ?>">
					<span class="vcard psf-user-profile-link">
						<a class="url fn n" href="<?php psf_user_profile_url(); ?>" title="<?php printf( esc_attr__( "%s's Profil", 'psforum' ), psf_get_displayed_user_field( 'display_name' ) ); ?>" rel="me"><?php _e( 'Profil', 'psforum' ); ?></a>
					</span>
				</li>

				<li class="<?php if ( psf_is_single_user_topics() ) :?>current<?php endif; ?>">
					<span class='psf-user-topics-created-link'>
						<a href="<?php psf_user_topics_created_url(); ?>" title="<?php printf( esc_attr__( "Themen von %s gestartet", 'psforum' ), psf_get_displayed_user_field( 'display_name' ) ); ?>"><?php _e( 'Themen gestartet', 'psforum' ); ?></a>
					</span>
				</li>

				<li class="<?php if ( psf_is_single_user_replies() ) :?>current<?php endif; ?>">
					<span class='psf-user-replies-created-link'>
						<a href="<?php psf_user_replies_created_url(); ?>" title="<?php printf( esc_attr__( "Antworten von %s erstellt", 'psforum' ), psf_get_displayed_user_field( 'display_name' ) ); ?>"><?php _e( 'Antworten erstellt', 'psforum' ); ?></a>
					</span>
				</li>

				<?php if ( psf_is_favorites_active() ) : ?>
					<li class="<?php if ( psf_is_favorites() ) :?>current<?php endif; ?>">
						<span class="psf-user-favorites-link">
							<a href="<?php psf_favorites_permalink(); ?>" title="<?php printf( esc_attr__( "%s Favoriten", 'psforum' ), psf_get_displayed_user_field( 'display_name' ) ); ?>"><?php _e( 'Favoriten', 'psforum' ); ?></a>
						</span>
					</li>
				<?php endif; ?>

				<?php if ( psf_is_user_home() || current_user_can( 'edit_users' ) ) : ?>

					<?php if ( psf_is_subscriptions_active() ) : ?>
						<li class="<?php if ( psf_is_subscriptions() ) :?>current<?php endif; ?>">
							<span class="psf-user-subscriptions-link">
								<a href="<?php psf_subscriptions_permalink(); ?>" title="<?php printf( esc_attr__( "Abonnements von %s", 'psforum' ), psf_get_displayed_user_field( 'display_name' ) ); ?>"><?php _e( 'Abonnements', 'psforum' ); ?></a>
							</span>
						</li>
					<?php endif; ?>

					<li class="<?php if ( psf_is_single_user_edit() ) :?>current<?php endif; ?>">
						<span class="psf-user-edit-link">
							<a href="<?php psf_user_profile_edit_url(); ?>" title="<?php printf( esc_attr__( "Profil von %s bearbeiten", 'psforum' ), psf_get_displayed_user_field( 'display_name' ) ); ?>"><?php _e( 'Bearbeiten', 'psforum' ); ?></a>
						</span>
					</li>

				<?php endif; ?>

			</ul>
		</div><!-- #psf-user-navigation -->
	</div><!-- #psf-single-user-details -->

	<?php do_action( 'psf_template_after_user_details' ); ?>
