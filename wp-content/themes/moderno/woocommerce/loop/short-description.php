<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
global $post;

if ( ideapark_mod( 'product_short_description' ) && ( $short_description = apply_filters( 'woocommerce_short_description', $post->post_excerpt ) ) ) { ?>
	<div class="c-product-grid__short-desc">
		<?php echo ideapark_wrap( $short_description ); ?>
	</div>
<?php } ?>