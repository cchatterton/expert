<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$expert_page   = max( 1, absint( $_GET['directory_page'] ?? 1 ) );
$expert_agents = expert_directory( ( $expert_page - 1 ) * 50 );
$expert_total  = count( $expert_agents );
$expert_pieces = array_sum( array_map( static fn( $agent ) => absint( $agent['count'] ?? 0 ), $expert_agents ) );
$expert_active = count( array_filter( $expert_agents, static fn( $agent ) => 'Paused' !== ( $agent['mode'] ?? '' ) ) );
$expert_colours = array( 'var(--expert-cyan)', 'var(--expert-blue)', 'var(--expert-lime)', 'var(--expert-amber)' );
$expert_borders = array( 'solid', 'dashed', 'solid', 'dotted' );
?>
<section class="expert-hero expert-network-hero">
	<div class="expert-hero-copy">
		<p class="expert-eyebrow"><?php esc_html_e( 'The Expert network', 'expert' ); ?></p>
		<h1><?php esc_html_e( 'Curiosity, with a subject.', 'expert' ); ?></h1>
		<p class="expert-lead"><?php esc_html_e( 'Explore a growing community of subject matter Agents. Each reads, questions and revisits what it knows — with its sources and history open to you.', 'expert' ); ?></p>
		<span class="expert-caption"><?php esc_html_e( 'Autonomous knowledge · locally reasoned', 'expert' ); ?></span>
	</div>
	<div class="expert-core-map" aria-label="<?php echo esc_attr( sprintf( _n( '%d Agent in the Expert network', '%d Agents in the Expert network', $expert_total, 'expert' ), $expert_total ) ); ?>">
		<div class="expert-agent-atoms" aria-hidden="true">
			<?php foreach ( $expert_agents as $expert_index => $expert_agent ) :
				$expert_width     = 154 + ( ( $expert_index * 47 ) % 86 );
				$expert_height    = 76 + ( ( $expert_index * 29 ) % 76 );
				$expert_angle     = -64 + ( ( $expert_index * 71 ) % 128 );
				$expert_speed     = 18 + ( ( $expert_index * 13 ) % 21 );
				$expert_delay     = -1 * ( $expert_index / max( $expert_total, 1 ) ) * $expert_speed;
				$expert_direction = $expert_index % 2 ? 'reverse' : 'normal';
				$expert_style     = sprintf( '--atom-width:%.4frem;--atom-height:%.4frem;--atom-angle:%ddeg;--atom-speed:%ds;--atom-delay:%.3fs;--atom-direction:%s;--atom-colour:%s;--atom-border:%s', $expert_width / 16, $expert_height / 16, $expert_angle, $expert_speed, $expert_delay, $expert_direction, $expert_colours[ $expert_index % 4 ], $expert_borders[ $expert_index % 4 ] );
				?>
				<span class="expert-agent-atom" style="<?php echo esc_attr( $expert_style ); ?>"></span>
			<?php endforeach; ?>
			<?php foreach ( $expert_agents as $expert_index => $expert_agent ) :
				$expert_left   = 16 + ( ( $expert_index * 37 + 13 ) % 68 );
				$expert_top    = 14 + ( ( $expert_index * 53 + 7 ) % 72 );
				$expert_size   = 5 + ( ( $expert_index * 3 ) % 4 );
				$expert_x      = -7 + ( ( $expert_index * 11 ) % 15 );
				$expert_y      = -6 + ( ( $expert_index * 7 ) % 13 );
				$expert_speed  = 3.8 + ( ( $expert_index * 17 ) % 24 ) / 10;
				$expert_style  = sprintf( '--dot-left:%d%%;--dot-top:%d%%;--dot-size:%.4frem;--dot-x:%.4frem;--dot-y:%.4frem;--dot-speed:%.1fs;--dot-delay:%.2fs;--dot-colour:%s', $expert_left, $expert_top, $expert_size / 16, $expert_x / 16, $expert_y / 16, $expert_speed, -1 * $expert_index * .73, $expert_colours[ ( $expert_index * 3 + 1 ) % 4 ] );
				?>
				<i class="expert-agent-dot" style="<?php echo esc_attr( $expert_style ); ?>"></i>
			<?php endforeach; ?>
		</div>
		<div class="expert-core"><strong><?php echo esc_html( $expert_total ); ?></strong><span><?php esc_html_e( 'AGENTS', 'expert' ); ?></span></div>
		<span class="expert-map-label expert-label-a"><?php esc_html_e( 'RESEARCH', 'expert' ); ?></span>
		<span class="expert-map-label expert-label-b"><?php esc_html_e( 'SOURCES', 'expert' ); ?></span>
		<span class="expert-map-label expert-label-c"><?php esc_html_e( 'QUESTIONS', 'expert' ); ?></span>
		<span class="expert-map-label expert-label-d"><?php esc_html_e( 'REFLECTION', 'expert' ); ?></span>
	</div>
	<div class="expert-signal-stack">
		<div><span><?php esc_html_e( 'ACTIVE AGENTS', 'expert' ); ?></span><strong><?php echo esc_html( $expert_active ); ?></strong></div>
		<div><span><?php esc_html_e( 'KNOWLEDGE PIECES', 'expert' ); ?></span><strong><?php echo esc_html( $expert_pieces ); ?></strong></div>
		<div><span><?php esc_html_e( 'NETWORK STATE', 'expert' ); ?></span><strong><?php echo $expert_total ? esc_html__( 'LIVE', 'expert' ) : esc_html__( 'READY', 'expert' ); ?></strong></div>
	</div>
</section>
<section class="expert-section">
	<div class="expert-section-heading"><div><p class="expert-eyebrow"><?php esc_html_e( 'Persistent intelligence', 'expert' ); ?></p><h2><?php esc_html_e( 'Meet the Agents', 'expert' ); ?></h2></div>
	<?php if ( current_user_can( 'manage_network_options' ) ) : ?>
		<a class="expert-button" href="<?php echo esc_url( expert_admin_url() ); ?>"><?php esc_html_e( 'Add an Agent', 'expert' ); ?></a>
	<?php endif; ?></div>
	<div class="expert-grid">
	<?php foreach ( $expert_agents as $expert_agent ) : ?>
		<article class="expert-card"><p class="expert-eyebrow"><?php echo esc_html( $expert_agent['mode'] ); ?></p><h3><a href="<?php echo esc_url( $expert_agent['url'] ); ?>"><?php echo esc_html( $expert_agent['name'] ); ?></a></h3><p><?php echo esc_html( $expert_agent['area'] ); ?></p><p class="expert-muted"><?php echo esc_html( $expert_agent['description'] ); ?></p><div class="expert-card-meta"><span><?php echo esc_html( $expert_agent['count'] ); ?> <?php esc_html_e( 'knowledge pieces', 'expert' ); ?></span><span><?php echo esc_html( $expert_agent['last'] ? sprintf( __( 'Learned %s ago', 'expert' ), human_time_diff( $expert_agent['last'] ) ) : __( 'Preparing to learn', 'expert' ) ); ?></span></div><a class="expert-text-link" href="<?php echo esc_url( $expert_agent['url'] ); ?>"><?php esc_html_e( 'Ask this Agent ↗', 'expert' ); ?></a></article>
	<?php endforeach; ?>
	<?php if ( ! $expert_agents ) : ?>
		<p class="expert-empty"><?php esc_html_e( 'The first Agent is yet to begin. A network administrator can create one with a name and a knowledge area.', 'expert' ); ?></p>
	<?php endif; ?>
	</div>
	<?php if ( count( $expert_agents ) >= 49 ) : ?>
		<a class="expert-text-link" href="<?php echo esc_url( add_query_arg( 'directory_page', $expert_page + 1 ) ); ?>"><?php esc_html_e( 'More Agents', 'expert' ); ?></a>
	<?php endif; ?>
</section>
