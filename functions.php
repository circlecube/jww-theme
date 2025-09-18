<?php

require __DIR__ . '/vendor/autoload.php';

require_once( get_stylesheet_directory() . '/includes/updates.php' );

/**
 * Enqueue styles - get parent theme styles first.
 */
function jww_enqueue() {

	$parent_style = 'parent-style'; // This is 'twentytwentyfive-style' for the Twenty Twentyfive theme.

	wp_enqueue_style( 
		$parent_style, 
		get_stylesheet_directory_uri() . '/twentytwentyfive.css',
	);
	wp_enqueue_style( 
		'jww-style',
		get_stylesheet_directory_uri() . '/style.css',
		array( $parent_style ),
		wp_get_theme()->get('Version')
	);
	
	// wp_enqueue_script( 
	// 	'jww-script', 
	// 	get_stylesheet_directory_uri() . '/js/script.js', 
	// 	null, 
	// 	wp_get_theme()->get('Version'),
	// 	true
	// );
}
add_action( 'wp_enqueue_scripts', 'jww_enqueue' );
