<?php

/**
 * Single Reply Content Part
 *
 * @package PSForum
 * @subpackage Theme
 */

?>

<div id="psforum-forums">

	<?php psf_breadcrumb(); ?>

	<?php do_action( 'psf_template_before_single_reply' ); ?>

	<?php if ( post_password_required() ) : ?>

		<?php psf_get_template_part( 'form', 'protected' ); ?>

	<?php else : ?>

		<?php psf_get_template_part( 'loop', 'single-reply' ); ?>

	<?php endif; ?>

	<?php do_action( 'psf_template_after_single_reply' ); ?>

</div>
