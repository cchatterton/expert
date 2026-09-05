<?php if ( ! defined( 'ABSPATH' ) || post_password_required() ) {
	return; } ?>
<section class="expert-section expert-prose"><h2><?php esc_html_e( 'Reflections and discussion', 'expert' ); ?></h2>
<?php
if ( have_comments() ) :
	?>
	<ol class="expert-comments">
	<?php
	wp_list_comments(
		array(
			'style'       => 'ol',
			'short_ping'  => true,
			'avatar_size' => 0,
		)
	);
	?>
	</ol>
	<?php
	the_comments_pagination();
endif;
if ( comments_open() ) {
	comment_form(); }
?>
</section>
