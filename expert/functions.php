<?php
/** Expert theme bootstrap. */
if ( ! defined( 'ABSPATH' ) ) {
	exit; }
define( 'EXPERT_VERSION', '1.1.0' );
foreach ( array( 'config', 'storage', 'setup', 'agent', 'access', 'local-ai', 'embeddings', 'engagement', 'knowledge', 'research-fetch', 'research', 'scheduler', 'rest', 'admin', 'assets', 'updates' ) as $expert_module ) {
	require_once __DIR__ . '/inc/' . $expert_module . '.php';
}
