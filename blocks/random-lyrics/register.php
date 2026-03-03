<?php
/**
 * Random Lyrics Block Registration
 *
 * Data comes from jww_get_random_lyrics_data() in includes/functions-acf.php.
 * Refresh uses GET /wp-json/jww/v1/lyrics/random.
 */

function jww_register_random_lyrics_block() {
	register_block_type(
		get_stylesheet_directory() . '/blocks/random-lyrics',
		array(
			'render_callback' => 'jww_render_random_lyrics_block',
		)
	);
}
add_action( 'init', 'jww_register_random_lyrics_block' );

/**
 * Render callback for the random lyrics block
 */
function jww_render_random_lyrics_block( $attributes, $content, $block ) {
	ob_start();
	include get_stylesheet_directory() . '/blocks/random-lyrics/render.php';
	return ob_get_clean();
}
