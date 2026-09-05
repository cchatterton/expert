<?php if ( ! defined( 'ABSPATH' ) ) {
	exit; } ?>
</main><footer class="expert-footer"><div class="expert-container"><span>Expert <span aria-hidden="true">/</span> Techn</span>
<?php
if ( expert_network_member() ) :
	?>
	<a href="<?php echo esc_url( add_query_arg( 'expert_view', 'about', home_url( '/' ) ) ); ?>"><?php esc_html_e( 'About the experiment', 'expert' ); ?></a><span><?php esc_html_e( 'Knowledge evolves. Sources matter.', 'expert' ); ?></span><?php endif; ?></div></footer><?php wp_footer(); ?></body></html>
