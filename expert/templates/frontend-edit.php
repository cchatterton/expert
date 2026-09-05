<?php if ( ! defined( 'ABSPATH' ) ) {
	exit; }
if ( ! current_user_can( 'edit_post', $expert_edit_id ) ) {
	return; }
$expert_edit_post = get_post( $expert_edit_id ); ?>
<details class="expert-edit"><summary><?php esc_html_e( 'Edit this content', 'expert' ); ?></summary><form class="expert-query" data-expert-route="frontend-edit"><input type="hidden" name="id" value="<?php echo esc_attr( $expert_edit_id ); ?>"><input type="hidden" name="modified" value="<?php echo esc_attr( $expert_edit_post->post_modified_gmt ); ?>"><label for="expert-content-<?php echo esc_attr( $expert_edit_id ); ?>"><?php esc_html_e( 'Content', 'expert' ); ?></label><textarea id="expert-content-<?php echo esc_attr( $expert_edit_id ); ?>" name="content" rows="12" maxlength="30000" required><?php echo esc_textarea( $expert_edit_post->post_content ); ?></textarea>
<?php
if ( 'expert_source' === $expert_edit_post->post_type ) :
	foreach ( array( 'publisher', 'author', 'published' ) as $expert_field ) :
		?>
	<label for="expert-meta-<?php echo esc_attr( $expert_field ); ?>"><?php echo esc_html( ucfirst( $expert_field ) ); ?></label><input id="expert-meta-<?php echo esc_attr( $expert_field ); ?>" name="<?php echo esc_attr( $expert_field ); ?>" value="<?php echo esc_attr( get_post_meta( $expert_edit_id, '_expert_' . $expert_field, true ) ); ?>">
		<?php
endforeach;
endif;
?>
<p><?php esc_html_e( 'Your revision is attributed to you. The Agent will review related knowledge without erasing your correction.', 'expert' ); ?></p><button type="submit"><?php esc_html_e( 'Save revision', 'expert' ); ?></button><p class="expert-status" role="status"></p><div class="expert-results"></div></form></details>
