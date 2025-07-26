<?php
if ( ( $page_id = apply_filters( 'wpml_object_id', ideapark_mod( 'header_advert_bar_page' ), 'any' ) ) && 'publish' == ideapark_post_status( $page_id ) ) {
	global $post;
	if (  ideapark_is_elementor_page( $page_id ) ) {
		$page_content = Elementor\Plugin::instance()->frontend->get_builder_content_for_display( $page_id );
	} elseif ( $post = get_post( $page_id ) ) {
		$page_content = apply_filters( 'the_content', $post->post_content );
		$page_content = str_replace( ']]>', ']]&gt;', $page_content );
		$page_content = ideapark_wrap( $page_content, '<div class="entry-content">', '</div>' );
		wp_reset_postdata();
	} else {
		$page_content = '';
	}
	echo ideapark_wrap( $page_content );
}