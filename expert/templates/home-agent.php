<?php if ( ! defined( 'ABSPATH' ) ) {
	exit;
} $expert_agent = get_option( 'expert_agent' );
$expert_state   = expert_state(); ?>
<section class="expert-hero"><p class="expert-eyebrow"><?php echo esc_html( $expert_state['mode'] ); ?> · <?php esc_html_e( 'An evolving perspective', 'expert' ); ?></p><h1><?php echo esc_html( $expert_agent['name'] ); ?></h1><p class="expert-lead"><?php echo esc_html( $expert_agent['area'] ); ?></p></section>
<section class="expert-understanding"><h2><?php esc_html_e( 'My current understanding', 'expert' ); ?></h2><div class="expert-prose"><?php echo wp_kses_post( wpautop( get_post_field( 'post_content', $expert_agent['subject'] ) ) ); ?></div>
<?php
$expert_edit_id = $expert_agent['subject'];
require get_template_directory() . '/templates/frontend-edit.php';
?>
</section>
<section class="expert-chat-panel"><p class="expert-eyebrow"><?php esc_html_e( 'Start a conversation', 'expert' ); ?></p><h2><?php esc_html_e( 'What would you like to understand?', 'expert' ); ?></h2><p><?php esc_html_e( 'Ask about this subject. I answer from what I have learned, and tell you where my knowledge ends.', 'expert' ); ?></p>
<div class="expert-local-ai" data-expert-local-ai><div><strong><?php esc_html_e( 'Private local AI', 'expert' ); ?></strong><p><?php esc_html_e( 'This signed-in device automatically helps the Agent answer and learn while an Agent page is visible. The model heals and reconnects after navigation; durable memory stays in WordPress.', 'expert' ); ?></p></div><progress class="expert-local-progress" max="100" value="0" aria-label="<?php esc_attr_e( 'Local AI preparation progress', 'expert' ); ?>"></progress><p class="expert-local-status" role="status" aria-live="polite"><?php esc_html_e( 'Starting local AI…', 'expert' ); ?></p></div>
<form class="expert-query" data-expert-route="chat"><label for="expert-question"><?php esc_html_e( 'Ask the Expert', 'expert' ); ?></label><textarea id="expert-question" name="message" required minlength="2" maxlength="1000" rows="3" placeholder="<?php esc_attr_e( 'What are you curious about?', 'expert' ); ?>"></textarea><button type="submit"><?php esc_html_e( 'Ask the Expert →', 'expert' ); ?></button><p class="expert-status" role="status" aria-live="polite"></p><div class="expert-results"></div></form></section>
<details class="expert-search"><summary><?php esc_html_e( 'Search the knowledge base by meaning', 'expert' ); ?></summary><form class="expert-query" data-expert-route="search"><label for="expert-search-message"><?php esc_html_e( 'Semantic search', 'expert' ); ?></label><input id="expert-search-message" name="message" required minlength="2" maxlength="1000"><button type="submit"><?php esc_html_e( 'Find knowledge', 'expert' ); ?></button><p class="expert-status" role="status"></p><div class="expert-results"></div></form></details>
<?php
foreach ( array(
	'post'            => __( 'Recent thinking', 'expert' ),
	'expert_source'   => __( 'Recently read', 'expert' ),
	'expert_research' => __( 'Questions I am exploring', 'expert' ),
) as $expert_type => $expert_heading ) :
	?>
<section class="expert-section"><h2><?php echo esc_html( $expert_heading ); ?></h2><div class="expert-grid">
	<?php
	$expert_items = get_posts(
		array(
			'post_type'   => $expert_type,
			'numberposts' => 3,
			'post_status' => 'publish',
		)
	); foreach ( $expert_items as $expert_item ) :
		?>
		<article class="expert-card"><p class="expert-eyebrow"><?php echo esc_html( get_the_date( '', $expert_item ) ); ?></p><h3><a href="<?php echo esc_url( get_permalink( $expert_item ) ); ?>"><?php echo esc_html( $expert_item->post_title ); ?></a></h3><p><?php echo esc_html( wp_trim_words( $expert_item->post_content, 30 ) ); ?></p>
		<?php
		if ( 'expert_source' === $expert_type ) :
			?>
		<p class="expert-muted"><?php echo esc_html( get_post_meta( $expert_item->ID, '_expert_publisher', true ) ); ?></p><?php endif; ?></article><?php endforeach; ?>
	<?php
	if ( ! $expert_items ) :
		?>
		<p class="expert-empty"><?php esc_html_e( 'Nothing here yet. This Agent is at the beginning of its learning journey.', 'expert' ); ?></p><?php endif; ?></div></section>
<?php endforeach; ?>
<section class="expert-section"><h2><?php esc_html_e( 'Latest changes', 'expert' ); ?></h2>
<?php
foreach ( array_slice( expert_timeline(), 0, 4 ) as $expert_event ) :
	?>
	<p class="expert-timeline-row"><time><?php echo esc_html( $expert_event['created_at'] ); ?> UTC</time><span><?php echo esc_html( $expert_event['description'] ); ?></span></p><?php endforeach; ?></section>
