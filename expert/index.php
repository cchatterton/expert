<?php if ( ! defined( 'ABSPATH' ) ) {
	exit;
} get_header(); ?>
<section class="expert-section"><h1><?php echo esc_html( is_search() ? sprintf( __( 'Search: %s', 'expert' ), get_search_query() ) : ( is_archive() ? wp_strip_all_tags( get_the_archive_title() ) : __( 'Thinking and knowledge', 'expert' ) ) ); ?></h1>
<?php
if ( is_post_type_archive( 'expert_research' ) ) :
	?>
	<p><?php esc_html_e( 'These are open questions and research candidates, not established knowledge.', 'expert' ); ?></p><?php endif; ?>
<div class="expert-grid">
<?php
if ( have_posts() ) :
	while ( have_posts() ) :
		the_post();
		?>
		<article class="expert-card"><p class="expert-eyebrow"><?php echo esc_html( get_post_type_object( get_post_type() )->labels->singular_name ); ?></p><h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2><?php the_excerpt(); ?>
			<?php
			if ( 'expert_source' === get_post_type() ) :
				?>
				<p><?php echo esc_html( get_post_meta( get_the_ID(), '_expert_publisher', true ) ); ?> · <?php echo esc_html( get_post_meta( get_the_ID(), '_expert_classification', true ) ); ?></p><a href="<?php echo esc_url( get_post_meta( get_the_ID(), '_expert_url', true ) ); ?>" rel="nofollow noopener"><?php esc_html_e( 'Original source', 'expert' ); ?></a>
				<?php
elseif ( 'expert_research' === get_post_type() ) :
	?>
				<p><?php echo esc_html( get_post_meta( get_the_ID(), '_expert_status', true ) ); ?></p><?php endif; ?>
</article>
		<?php
endwhile; else :
	?>
				<p class="expert-empty"><?php esc_html_e( 'No knowledge pieces have been published here yet.', 'expert' ); ?></p><?php endif; ?></div><?php the_posts_pagination(); ?></section>
<?php get_footer(); ?>
