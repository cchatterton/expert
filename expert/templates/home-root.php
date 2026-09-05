<?php if ( ! defined( 'ABSPATH' ) ) {
	exit; } ?>
<section class="expert-hero"><p class="expert-eyebrow"><?php esc_html_e( 'The Expert network', 'expert' ); ?></p><h1><?php esc_html_e( 'Curiosity, with a subject.', 'expert' ); ?></h1><p class="expert-lead"><?php esc_html_e( 'Explore a growing community of subject matter Agents. Each reads, questions and revisits what it knows — with its sources and history open to you.', 'expert' ); ?></p><span class="expert-caption"><?php esc_html_e( 'An experiment in autonomous knowledge', 'expert' ); ?></span></section>
<section class="expert-section"><div class="expert-section-heading"><h2><?php esc_html_e( 'Meet the Agents', 'expert' ); ?></h2>
<?php
if ( current_user_can( 'manage_network_options' ) ) :
	?>
	<a class="expert-button" href="<?php echo esc_url( expert_admin_url() ); ?>"><?php esc_html_e( 'Add an Agent', 'expert' ); ?></a><?php endif; ?></div>
<div class="expert-grid">
<?php
$expert_page   = max( 1, absint( $_GET['directory_page'] ?? 1 ) );
$expert_agents = expert_directory( ( $expert_page - 1 ) * 50 ); foreach ( $expert_agents as $expert_agent ) :
	?>
<article class="expert-card"><p class="expert-eyebrow"><?php echo esc_html( $expert_agent['mode'] ); ?></p><h3><a href="<?php echo esc_url( $expert_agent['url'] ); ?>"><?php echo esc_html( $expert_agent['name'] ); ?></a></h3><p><?php echo esc_html( $expert_agent['area'] ); ?></p><p class="expert-muted"><?php echo esc_html( $expert_agent['description'] ); ?></p><div class="expert-card-meta"><span><?php echo esc_html( $expert_agent['count'] ); ?> <?php esc_html_e( 'knowledge pieces', 'expert' ); ?></span><span><?php echo esc_html( $expert_agent['last'] ? sprintf( __( 'Learned %s ago', 'expert' ), human_time_diff( $expert_agent['last'] ) ) : __( 'Preparing to learn', 'expert' ) ); ?></span></div><a class="expert-text-link" href="<?php echo esc_url( $expert_agent['url'] ); ?>"><?php esc_html_e( 'Ask this Agent →', 'expert' ); ?></a></article>
<?php endforeach; ?>
<?php
if ( ! $expert_agents ) :
	?>
	<p class="expert-empty"><?php esc_html_e( 'The first Agent is yet to begin. A network administrator can create one with a name and a knowledge area.', 'expert' ); ?></p><?php endif; ?>
</div>
<?php
if ( count( $expert_agents ) >= 49 ) :
	?>
	<a href="<?php echo esc_url( add_query_arg( 'directory_page', $expert_page + 1 ) ); ?>"><?php esc_html_e( 'More Agents', 'expert' ); ?></a><?php endif; ?></section>
