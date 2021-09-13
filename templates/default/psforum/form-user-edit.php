<?php

/**
 * PSForum User Profile Edit Part
 *
 * @package PSForum
 * @subpackage Theme
 */

?>

<form id="psf-your-profile" action="<?php psf_user_profile_edit_url( psf_get_displayed_user_id() ); ?>" method="post" enctype="multipart/form-data">

	<h2 class="entry-title"><?php _e( 'Name', 'psforum' ) ?></h2>

	<?php do_action( 'psf_user_edit_before' ); ?>

	<fieldset class="psf-form">
		<legend><?php _e( 'Name', 'psforum' ) ?></legend>

		<?php do_action( 'psf_user_edit_before_name' ); ?>

		<div>
			<label for="first_name"><?php _e( 'Vorname', 'psforum' ) ?></label>
			<input type="text" name="first_name" id="first_name" value="<?php psf_displayed_user_field( 'first_name', 'edit' ); ?>" class="regular-text" tabindex="<?php psf_tab_index(); ?>" />
		</div>

		<div>
			<label for="last_name"><?php _e( 'Nachname', 'psforum' ) ?></label>
			<input type="text" name="last_name" id="last_name" value="<?php psf_displayed_user_field( 'last_name', 'edit' ); ?>" class="regular-text" tabindex="<?php psf_tab_index(); ?>" />
		</div>

		<div>
			<label for="nickname"><?php _e( 'Nickname', 'psforum' ); ?></label>
			<input type="text" name="nickname" id="nickname" value="<?php psf_displayed_user_field( 'nickname', 'edit' ); ?>" class="regular-text" tabindex="<?php psf_tab_index(); ?>" />
		</div>

		<div>
			<label for="display_name"><?php _e( 'Anzeigename', 'psforum' ) ?></label>

			<?php psf_edit_user_display_name(); ?>

		</div>

		<?php do_action( 'psf_user_edit_after_name' ); ?>

	</fieldset>

	<h2 class="entry-title"><?php _e( 'Kontaktinformation', 'psforum' ) ?></h2>

	<fieldset class="psf-form">
		<legend><?php _e( 'Kontaktinformation', 'psforum' ) ?></legend>

		<?php do_action( 'psf_user_edit_before_contact' ); ?>

		<div>
			<label for="url"><?php _e( 'Webseite', 'psforum' ) ?></label>
			<input type="text" name="url" id="url" value="<?php psf_displayed_user_field( 'user_url', 'edit' ); ?>" class="regular-text code" tabindex="<?php psf_tab_index(); ?>" />
		</div>

		<?php foreach ( psf_edit_user_contact_methods() as $name => $desc ) : ?>

			<div>
				<label for="<?php echo esc_attr( $name ); ?>"><?php echo apply_filters( 'user_' . $name . '_label', $desc ); ?></label>
				<input type="text" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $name ); ?>" value="<?php psf_displayed_user_field( $name, 'edit' ); ?>" class="regular-text" tabindex="<?php psf_tab_index(); ?>" />
			</div>

		<?php endforeach; ?>

		<?php do_action( 'psf_user_edit_after_contact' ); ?>

	</fieldset>

	<h2 class="entry-title"><?php psf_is_user_home_edit() ? _e( 'Über dich', 'psforum' ) : _e( 'Über den Benutzer', 'psforum' ); ?></h2>

	<fieldset class="psf-form">
		<legend><?php psf_is_user_home_edit() ? _e( 'Über dich', 'psforum' ) : _e( 'Über den Benutzer', 'psforum' ); ?></legend>

		<?php do_action( 'psf_user_edit_before_about' ); ?>

		<div>
			<label for="description"><?php _e( 'Lebenslauf', 'psforum' ); ?></label>
			<textarea name="description" id="description" rows="5" cols="30" tabindex="<?php psf_tab_index(); ?>"><?php psf_displayed_user_field( 'description', 'edit' ); ?></textarea>
		</div>

		<?php do_action( 'psf_user_edit_after_about' ); ?>

	</fieldset>

	<h2 class="entry-title"><?php _e( 'Account', 'psforum' ) ?></h2>

	<fieldset class="psf-form">
		<legend><?php _e( 'Account', 'psforum' ) ?></legend>

		<?php do_action( 'psf_user_edit_before_account' ); ?>

		<div>
			<label for="user_login"><?php _e( 'Benutzername', 'psforum' ); ?></label>
			<input type="text" name="user_login" id="user_login" value="<?php psf_displayed_user_field( 'user_login', 'edit' ); ?>" disabled="disabled" class="regular-text" tabindex="<?php psf_tab_index(); ?>" />
		</div>

		<div>
			<label for="email"><?php _e( 'Email', 'psforum' ); ?></label>

			<input type="text" name="email" id="email" value="<?php psf_displayed_user_field( 'user_email', 'edit' ); ?>" class="regular-text" tabindex="<?php psf_tab_index(); ?>" />

			<?php

			// Handle address change requests
			$new_email = get_option( psf_get_displayed_user_id() . '_new_email' );
			if ( !empty( $new_email ) && $new_email !== psf_get_displayed_user_field( 'user_email', 'edit' ) ) : ?>

				<span class="updated inline">

					<?php printf( __( 'Es gibt eine ausstehende Änderung der E-Mail-Adresse in <code>%1$s</code>. <a href="%2$s">Abbrechen</a>', 'psforum' ), $new_email['newemail'], esc_url( self_admin_url( 'user.php?dismiss=' . psf_get_current_user_id()  . '_new_email' ) ) ); ?>

				</span>

			<?php endif; ?>

		</div>

		<div id="password">
			<label for="pass1"><?php _e( 'Neues Kennwort', 'psforum' ); ?></label>
			<fieldset class="psf-form password">
				<input type="password" name="pass1" id="pass1" size="16" value="" autocomplete="off" tabindex="<?php psf_tab_index(); ?>" />
				<span class="description"><?php _e( 'Wenn Du das Passwort ändern möchtest, gib ein neues ein. Andernfalls lasse dieses Feld leer.', 'psforum' ); ?></span>

				<input type="password" name="pass2" id="pass2" size="16" value="" autocomplete="off" tabindex="<?php psf_tab_index(); ?>" />
				<span class="description"><?php _e( 'Gib ein neues Passwort erneut ein.', 'psforum' ); ?></span><br />

				<div id="pass-strength-result"></div>
				<span class="description indicator-hint"><?php _e( 'Dein Passwort sollte mindestens zehn Zeichen lang sein. Verwende Groß- und Kleinbuchstaben, Zahlen und Symbole, um es noch stärker zu machen.', 'psforum' ); ?></span>
			</fieldset>
		</div>

		<?php do_action( 'psf_user_edit_after_account' ); ?>

	</fieldset>

	<?php if ( current_user_can( 'edit_users' ) && ! psf_is_user_home_edit() ) : ?>

		<h2 class="entry-title"><?php _e( 'Benutzer-Rolle', 'psforum' ) ?></h2>

		<fieldset class="psf-form">
			<legend><?php _e( 'Benutzer-Rolle', 'psforum' ); ?></legend>

			<?php do_action( 'psf_user_edit_before_role' ); ?>

			<?php if ( is_multisite() && is_super_admin() && current_user_can( 'manage_network_options' ) ) : ?>

				<div>
					<label for="super_admin"><?php _e( 'Netzwerkrolle', 'psforum' ); ?></label>
					<label>
						<input class="checkbox" type="checkbox" id="super_admin" name="super_admin"<?php checked( is_super_admin( psf_get_displayed_user_id() ) ); ?> tabindex="<?php psf_tab_index(); ?>" />
						<?php _e( 'Gewähre diesem Benutzer Super-Admin-Berechtigungen für das Netzwerk.', 'psforum' ); ?>
					</label>
				</div>

			<?php endif; ?>

			<?php psf_get_template_part( 'form', 'user-roles' ); ?>

			<?php do_action( 'psf_user_edit_after_role' ); ?>

		</fieldset>

	<?php endif; ?>

	<?php do_action( 'psf_user_edit_after' ); ?>

	<fieldset class="submit">
		<legend><?php _e( 'Änderungen speichern', 'psforum' ); ?></legend>
		<div>

			<?php psf_edit_user_form_fields(); ?>

			<button type="submit" tabindex="<?php psf_tab_index(); ?>" id="psf_user_edit_submit" name="psf_user_edit_submit" class="button submit user-submit"><?php psf_is_user_home_edit() ? _e( 'Profil aktualisieren', 'psforum' ) : _e( 'Benutzer aktualisieren', 'psforum' ); ?></button>
		</div>
	</fieldset>

</form>