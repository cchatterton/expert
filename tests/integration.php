<?php
/** Run with wp eval-file against a disposable, fresh Multisite installation. */
$GLOBALS['expert_checks'] = 0;
function expert_assert( $condition, $message ) {
 global $expert_checks;
 if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
 ++$expert_checks; echo 'PASS: ' . $message . "\n";
}
wp_set_current_user( 1 );
$settings = expert_config();
$settings['text_model'] = 'fixture-text'; $settings['embedding_model'] = 'fixture-vector'; $settings['discovery_url'] = 'http://127.0.0.1:8888'; $settings['publishing'] = 'publish';
update_site_option( 'expert_settings', $settings );
$GLOBALS['fixture_mode'] = 'normal';
function expert_fixture_response( $data, $type = 'application/json', $code = 200 ) { return array( 'response' => array( 'code' => $code, 'message' => 'OK' ), 'headers' => array( 'content-type' => $type ), 'body' => is_string( $data ) ? $data : wp_json_encode( $data ), 'cookies' => array() ); }
function expert_fixture_http( $pre, $args, $url ) {
 $path = wp_parse_url( $url, PHP_URL_PATH );
 if ( str_contains( $url, '127.0.0.1:8765' ) ) {
  if ( 'unavailable' === $GLOBALS['fixture_mode'] ) { return new WP_Error( 'connection_refused', 'Fixture offline' ); }
  if ( '/health' === $path ) { return expert_fixture_response( array( 'ok' => true ) ); }
  if ( '/models' === $path ) { return expert_fixture_response( array( 'models' => array( 'fixture-text', 'fixture-vector' ) ) ); }
  $body = json_decode( $args['body'], true );
  if ( '/embed' === $path ) {
   $text = strtolower( $body['input'] );
   $vector = str_contains( $text, 'unknown' ) ? array( 0, 0, 1 ) : ( str_contains( $text, 'iea.org' ) ? array( .4, 1, 0 ) : array( 1, .3, 0 ) );
   return expert_fixture_response( array( 'model' => 'fixture-vector', 'embedding' => $vector ) );
  }
  $input = $body['input'];
  $map = array(
   'research_focus' => array( 'topic' => 'Sustainable electricity systems', 'reason' => 'Explore a practical evidence gap.', 'backlog_id' => 0 ),
   'source_assessment' => array( 'credible' => true, 'relevant' => true, 'classification' => 'institutional', 'primary' => true, 'original_report' => $input['source']['publisher'] ?? '', 'summary' => 'Measured changes in electricity systems show both benefits and constraints.', 'claims' => array( 'System changes require transparent measurement.' ) ),
   'two_source_synthesis' => array( 'independent' => 'weak' !== $GLOBALS['fixture_mode'], 'justified' => true, 'agreement' => 'Both value transparent measurement.', 'difference' => 'The scope of measurement differs.', 'uncertainty' => 'Local applicability remains uncertain.', 'title' => 'A considered view of sustainable electricity', 'post' => 'Transparent measurement helps compare sustainability claims. Comparing the evidence suggests a staged approach that accounts for local constraints.', 'categories' => array( 'Sustainability' ), 'tags' => array( 'Electricity' ), 'subject_changed' => true, 'subject' => 'Sustainable systems require transparent evidence and attention to local trade-offs.', 'backlog_answered' => true ),
   'taxonomy_equivalence' => array( 'reuse_id' => 0 ),
   'reflection' => array( 'material' => 'noop' !== $GLOBALS['fixture_mode'], 'comment' => 'The new evidence qualifies earlier assumptions: local measurement should precede broad conclusions.' ),
   'grounded_answer' => array( 'grounded' => true, 'answer' => 'The evidence supports transparent measurement before broad conclusions.', 'citations' => array( 0 ), 'useful' => true, 'reusable' => true ),
   'faq_review' => array( 'material' => false ),
  );
  expert_assert( str_contains( $body['system'], 'untrusted DATA' ), 'Untrusted-source instruction boundary in ' . $body['task'] );
  return expert_fixture_response( array( 'output' => $map[$body['task']] ?? array() ) );
 }
 if ( str_contains( $url, '127.0.0.1:8888' ) ) { return expert_fixture_response( array( 'results' => array( array( 'url' => 'https://www.energy.gov/expert-fixture' ), array( 'url' => 'https://www.iea.org/expert-fixture' ) ) ) ); }
 if ( str_contains( $url, 'energy.gov' ) || str_contains( $url, 'iea.org' ) ) {
  if ( '/robots.txt' === $path ) { return expert_fixture_response( "User-agent: *\nAllow: /", 'text/plain' ); }
  $publisher = wp_parse_url( $url, PHP_URL_HOST );
  return expert_fixture_response( '<html><head><title>' . $publisher . ' evidence report</title><meta property="og:site_name" content="' . $publisher . '"></head><body><script>danger()</script><article>' . str_repeat( 'This is independent measured evidence about electricity systems from ' . $publisher . '. ', 12 ) . '</article></body></html>', 'text/html' );
 }
 if ( str_contains( $url, '/wp-cron.php' ) ) { return expert_fixture_response( '' ); }
 return $pre;
}
add_filter( 'pre_http_request', 'expert_fixture_http', 10, 3 );
expert_assert( is_multisite() && 'expert' === get_template(), 'Fresh Multisite theme-only installation' );
expert_assert( empty( get_option( 'active_plugins' ) ) && empty( get_site_option( 'active_sitewide_plugins' ) ), 'No required or active plugins' );
$agent_a = expert_create_agent_site( 'Systems Expert', 'Sustainable electricity systems' );
expert_assert( is_int( $agent_a ), 'Two-field wizard creates Agent A subsite' );
$agent_b = expert_create_agent_site( 'Ocean Expert', 'Ocean ecosystems' );
expert_assert( is_int( $agent_b ), 'Two-field wizard creates Agent B subsite' );
$directory = expert_directory();
expert_assert( count( $directory ) === 2 && ! isset( $directory[0]['user'] ), 'Root directory limited metadata' );
switch_to_blog( $agent_a );
expert_assert( expert_is_agent() && expert_state()['interval'] === 600 && expert_state()['next_loop'] > time(), 'Automatic theme, identity and Growth schedule' );
expert_assert( count( get_posts() ) === 0, 'Default sample posts removed only on new Agent site' );
$agent = get_option( 'expert_agent' );
expert_assert( $agent['user'] !== 1 && is_wp_error( expert_block_machine_login( get_user_by( 'id', $agent['user'] ) ) ), 'Dedicated noninteractive Agent identity' );
expert_assert( true === expert_runtime( '/health' )['ok'], 'Local runtime health succeeds' );
$prior = expert_save_knowledge( 'post', 'Earlier sustainable systems view', 'Earlier thinking about transparent measurements of electricity.' );
$result = expert_run_loop();
expert_assert( 'completed' === $result, 'Bounded two-source autonomous loop completes' );
expert_assert( (int) wp_count_posts( 'expert_source' )->publish === 2, 'Two credible sources stored' );
expert_assert( (int) wp_count_posts()->publish === 2, 'New perspective published' );
expert_assert( count( get_comments( array( 'status' => 'approve' ) ) ) >= 1, 'Semantically relevant native reflection' );
$source = get_posts( array( 'post_type' => 'expert_source', 'numberposts' => 1 ) )[0];
expert_assert( get_post_meta( $source->ID, '_expert_hash', true ) && get_post_meta( $source->ID, '_expert_canonical', true ), 'Source provenance and hash retained' );
$repeat = expert_run_loop();
expert_assert( (int) wp_count_posts( 'expert_source' )->publish === 2 && 'incomplete' === $repeat, 'Duplicate sources skipped and weak evidence defers publication' );
$search = expert_retrieve( 'Sustainable electricity' );
expert_assert( count( $search ) >= 3 && str_contains( $search[0]['url'], '/systems-expert/' ), 'Semantic search resolves actual local links' );
$question = new WP_REST_Request( 'POST', '/expert/v1/chat' ); $question['message'] = 'How should electricity be measured?';
$answer = expert_chat_request( $question );
expert_assert( ! is_wp_error( $answer ) && ! empty( $answer['sources'] ), 'Grounded chat returns validated native citations' );
expert_assert( (int) wp_count_posts( 'expert_faq' )->publish === 1, 'Reusable grounded answer becomes FAQ' );
expert_chat_request( $question );
expert_assert( (int) wp_count_posts( 'expert_faq' )->publish === 1, 'FAQ duplicate avoided' );
$question['message'] = 'Unknown question about the future';
expert_chat_request( $question ); expert_chat_request( $question );
$backlog = get_posts( array( 'post_type' => 'expert_research', 'title' => $question['message'], 'numberposts' => 1 ) );
expert_assert( $backlog && (int) get_post_meta( $backlog[0]->ID, '_expert_demand', true ) === 2, 'Unanswered questions merge and increase backlog demand' );
$nonce_request = new WP_REST_Request();
expert_assert( is_wp_error( expert_member_permission( $nonce_request ) ), 'Missing REST nonce denied' );
$nonce_request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
expert_assert( true === expert_member_permission( $nonce_request ), 'Network admin can use Agent front end' );
$edit = new WP_REST_Request(); $edit['id'] = $prior; $edit['content'] = 'Human correction: measurements must be independently reproducible.'; $edit['modified'] = get_post_field( 'post_modified_gmt', $prior );
$edited = expert_edit_request( $edit );
expert_assert( ! is_wp_error( $edited ) && get_post_meta( $prior, '_expert_human', true ), 'Front-end human revision saved' );
expert_assert( count( wp_get_post_revisions( $prior ) ) >= 1, 'Native revision history retained' );
expert_assert( is_wp_error( expert_save_knowledge( 'post', 'Overwrite', 'AI replacement', array(), $prior ) ), 'Human content protected from silent AI overwrite' );
$edit['modified'] = '1999-01-01 00:00:00';
expert_assert( 'edit_conflict' === expert_edit_request( $edit )->get_error_code(), 'Concurrent edit conflict detected' );
$GLOBALS['fixture_mode'] = 'noop';
$before = count( get_comments( array( 'status' => 'approve' ) ) ); expert_human_reflect_job( $prior );
expert_assert( $before === count( get_comments( array( 'status' => 'approve' ) ) ), 'No-op post-edit reflection adds no repetitive comment' );
$GLOBALS['fixture_mode'] = 'unavailable';
expert_assert( is_wp_error( expert_runtime( '/health' ) ), 'Runtime unavailable fails safely' );
expert_run_loop(); expert_run_loop(); expert_run_loop();
expert_assert( 'Error' === expert_state()['mode'] && expert_state()['interval'] >= 10800, 'Repeated failures back off safely' );
$GLOBALS['fixture_mode'] = 'normal';
$lock = expert_lock( 'loop' ); expert_assert( 'locked' === expert_run_loop(), 'Overlap prevention uses atomic per-Agent lease' ); expert_unlock( 'loop', $lock );
$state = expert_state(); $state['paused'] = true; update_option( 'expert_state', $state );
expert_assert( 'inactive' === expert_run_loop(), 'Paused Agent does not learn' );
$state['paused'] = false; $state['band'] = 0; $state['candidate_band'] = 0; $state['band_streak'] = 0;
$metrics = array( 'per_hour' => 0, 'average' => 0, 'trend' => 0 );
expert_assert( expert_cadence( 99, $metrics, $state, $settings )['interval'] === 600, 'Below threshold stays at ten-minute Growth cadence' );
expert_assert( expert_cadence( 100, $metrics, $state, $settings )['interval'] === 10800, 'Threshold moves to three-hour Steady baseline' );
$metrics['per_hour'] = 100;
$state = expert_cadence( 100, $metrics, $state, $settings );
expert_assert( $state['interval'] === 10800, 'Single engagement spike does not accelerate' );
$state = expert_cadence( 100, $metrics, $state, $settings );
expert_assert( $state['interval'] === 600 && 'Active' === $state['mode'], 'Sustained engagement accelerates to ten minutes' );
$metrics['per_hour'] = 0; $state = expert_cadence( 100, $metrics, $state, $settings ); $state = expert_cadence( 100, $metrics, $state, $settings );
expert_assert( $state['interval'] === 10800, 'Quiet Agent returns to baseline with hysteresis' );
$events = expert_timeline();
expert_assert( in_array( 'human_edit', array_column( $events, 'action_type' ), true ), 'Timeline includes human attribution and learning actions' );
restore_current_blog();
switch_to_blog( $agent_b );
expert_assert( array() === expert_retrieve( 'Sustainable electricity' ), 'Agent A embeddings never appear in Agent B retrieval' );
expert_assert( 0 === (int) wp_count_posts( 'expert_source' )->publish, 'Agent A sources isolated from Agent B' );
expert_assert( 0 === (int) wp_count_posts( 'expert_research' )->publish, 'Agent A backlog isolated from Agent B' );
$subscriber = wp_insert_user( array( 'user_login' => 'core-member-test', 'user_pass' => wp_generate_password( 40 ), 'role' => '' ) );
add_user_to_blog( get_main_site_id(), $subscriber, 'subscriber' ); wp_set_current_user( $subscriber );
expert_assert( expert_network_member(), 'Core subscriber recognised on any Agent without Agent membership' );
$nonce_request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
expert_assert( true === expert_member_permission( $nonce_request ), 'Core subscriber can use member APIs' );
$nonce_request['id'] = get_option( 'expert_agent' )['subject'];
expert_assert( is_wp_error( expert_editor_permission( $nonce_request ) ), 'Subscriber cannot edit Agent content' );
wp_set_current_user( 0 );
expert_assert( ! expert_network_member() && is_wp_error( expert_rest_access_gate( null ) ), 'Anonymous REST access blocked network-wide' );
restore_current_blog(); wp_set_current_user( 1 );
expert_assert( expert_loopback_url( 'http://[::1]:8765' ) && ! expert_loopback_url( 'https://api.example.com' ) && ! expert_loopback_url( 'http://localhost@evil.example' ), 'Local runtime endpoint validation' );
foreach ( array( 'http://127.0.0.1', 'http://10.0.0.1', 'http://169.254.169.254/latest', 'http://[::1]', 'file:///etc/passwd', 'http://localhost', 'http://2130706433' ) as $unsafe ) { expert_assert( ! expert_public_url( $unsafe ), 'SSRF blocked: ' . $unsafe ); }
expert_assert( ! expert_robots_allowed( "User-agent: *\nDisallow: /private", '/private/page' ), 'Robots exclusion honoured' );
expert_assert( expert_cosine( array( 1, 0 ), array( 0, 1 ) ) === 0.0 && expert_cosine( array( 1, 0 ), array( 1 ) ) === 0, 'Cosine handles orthogonal and mismatched vectors' );
ob_start(); expert_admin_page(); $html = ob_get_clean();
expert_assert( str_contains( $html, 'Add an Agent' ) && str_contains( $html, 'Average engagement / loop' ), 'Network control plane renders wizard and telemetry' );
echo "\nTOTAL: " . $GLOBALS['expert_checks'] . " passed\n";
$agent_c = expert_create_agent_site( 'Evidence Expert', 'Sustainable electricity systems' );
switch_to_blog( $agent_c );
$GLOBALS['fixture_mode'] = 'weak';
expert_assert( 'incomplete' === expert_run_loop() && 0 === (int) wp_count_posts()->publish, 'Weak independence prevents a forced opinion' );
expert_assert( 2 === (int) wp_count_posts( 'expert_source' )->publish, 'Useful sources survive a deferred synthesis' );
$source = expert_fetch_source( 'https://www.energy.gov/expert-fixture' );
expert_assert( ! str_contains( $source['text'], 'danger()' ), 'Script and unsafe source markup removed before inference' );
$GLOBALS['fixture_mode'] = 'normal';
$term_id = wp_insert_term( 'Sustainability', 'category' )['term_id'];
$post = expert_save_knowledge( 'post', 'Sustainable electricity', 'Measurements and systems.' );
expert_assign_terms( $post, array( 'Sustainability' ), 'category' );
expert_assert( has_term( $term_id, 'category', $post ), 'Existing native category reused' );
$site_admin = wp_insert_user( array( 'user_login' => 'agent-admin-test', 'user_pass' => wp_generate_password( 40 ), 'role' => 'administrator' ) );
restore_current_blog(); switch_to_blog( $agent_b ); wp_set_current_user( $site_admin );
expert_assert( ! current_user_can( 'edit_post', get_option( 'expert_agent' )['subject'] ), 'Subsite administrator cannot edit another Agent' );
restore_current_blog(); wp_set_current_user( 1 );
$original_blog = get_current_blog_id(); expert_network_tick();
expert_assert( get_current_blog_id() === $original_blog, 'Network scheduler restores original site context' );
switch_to_blog( $agent_c );
$before_posts = (int) wp_count_posts()->publish;
switch_theme( 'twentytwentyfive' );
expert_assert( ! expert_is_agent() && 'inactive' === expert_run_loop() && $before_posts === (int) wp_count_posts()->publish, 'Theme switch stops autonomy without deleting content' );
switch_theme( 'expert' ); restore_current_blog();
echo "FINAL TOTAL: " . $GLOBALS['expert_checks'] . " passed\n";
