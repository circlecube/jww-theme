<?php

require __DIR__ . '/vendor/autoload.php';

require_once( get_stylesheet_directory() . '/includes/updates.php' );

/**
 * Enqueue styles - get parent theme styles first.
 */
function jww_enqueue() {

	// Get parent theme stylesheet
	$parent_style = 'twentytwentyfive-style';
	
	// Enqueue parent theme styles
	wp_enqueue_style( 
		$parent_style, 
		get_template_directory_uri() . '/style.css',
		array(),
		wp_get_theme()->parent()->get('Version')
	);
	
	// Enqueue child theme styles
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

/**
 * Add theme support for block styles
 */
function jww_theme_support() {
	// Add support for block styles
	add_theme_support( 'wp-block-styles' );
	
	// Add support for editor styles
	add_theme_support( 'editor-styles' );
	
	// Add support for responsive embeds
	add_theme_support( 'responsive-embeds' );
	
	// Add support for navigation menus
	add_theme_support( 'menus' );
	
	// Register navigation menus
	register_nav_menus( array(
		'primary' => __( 'Primary Menu', 'jww-theme' ),
		'footer'  => __( 'Footer Menu', 'jww-theme' ),
	) );
}
add_action( 'after_setup_theme', 'jww_theme_support' );

/**
 * Filter YouTube oembed URLs to add rel=0 and modestbranding=1 parameters
 */
function jww_filter_youtube_oembed( $html, $url, $attr ) {
	// Check if it's a YouTube URL
	if ( strpos( $url, 'youtube.com' ) !== false || strpos( $url, 'youtu.be' ) !== false ) {
		// Add rel=0 to prevent related videos and modestbranding=1 to reduce YouTube branding
		$params = array();
		
		// Handle rel parameter
		if ( strpos( $url, 'rel=' ) === false ) {
			$params[] = 'rel=0';
		} else {
			// Replace existing rel parameter
			$url = preg_replace( '/rel=\d+/', 'rel=0', $url );
		}
		
		// Handle modestbranding parameter
		if ( strpos( $url, 'modestbranding=' ) === false ) {
			$params[] = 'modestbranding=1';
		} else {
			// Replace existing modestbranding parameter
			$url = preg_replace( '/modestbranding=\d+/', 'modestbranding=1', $url );
		}
		
		// Add parameters to URL if any were added
		if ( !empty( $params ) ) {
			$url .= ( strpos( $url, '?' ) !== false ? '&' : '?' ) . implode( '&', $params );
		}
		
		// Regenerate the oembed HTML with the modified URL
		$html = wp_oembed_get( $url, $attr );
	}
	
	return $html;
}
add_filter( 'oembed_result', 'jww_filter_youtube_oembed', 10, 3 );
