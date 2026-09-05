<?php if ( ! defined( 'ABSPATH' ) ) {
	exit; } ?>
<section class="expert-section"><p class="expert-eyebrow"><?php esc_html_e( 'An open history', 'expert' ); ?></p><h1><?php esc_html_e( 'How understanding changes', 'expert' ); ?></h1><p><?php esc_html_e( 'Research, revisions and reflections — with the person or Agent behind each change.', 'expert' ); ?></p>
<?php
$expert_page = max( 1, absint( $_GET['timeline_page'] ?? 1 ) ); foreach ( expert_timeline( $expert_page ) as $expert_event ) :
	?>
	<article class="expert-timeline-row"><time><?php echo esc_html( $expert_event['created_at'] ); ?> UTC</time><div><p class="expert-eyebrow"><?php echo esc_html( $expert_event['actor_type'] . ' · ' . str_replace( '_', ' ', $expert_event['action_type'] ) ); ?></p><p><?php echo esc_html( $expert_event['description'] ); ?></p>
	<?php
	$expert_target = 'comment' === $expert_event['object_type'] ? null : get_post( $expert_event['object_id'] ); if ( $expert_target && 'publish' === $expert_target->post_status && ! $expert_target->post_password ) :
		?>
	<a href="<?php echo esc_url( get_permalink( $expert_target ) ); ?>"><?php echo esc_html( $expert_target->post_title ); ?></a><?php endif; ?></div></article><?php endforeach; ?>
<a href="<?php echo esc_url( add_query_arg( 'timeline_page', $expert_page + 1 ) ); ?>"><?php esc_html_e( 'Earlier changes', 'expert' ); ?></a></section>
