<?php
// Composer autoload
require __DIR__ . '/vendor/autoload.php';

// Theme updates
require_once( get_stylesheet_directory() . '/includes/updates.php' );

// YouTube Importer
require_once( get_stylesheet_directory() . '/includes/class-youtube-importer.php' );

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
		wp_get_theme()->get('Version'),
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

	// Add support for block template parts
	add_theme_support( 'block-template-parts' );
}
add_action( 'after_setup_theme', 'jww_theme_support' );

/**
 * Add ACF Options Page for YouTube Import Settings
 */
if( function_exists('acf_add_options_page') ) {
	acf_add_options_page(array(
		'page_title'    => 'YouTube Import Settings',
		'menu_title'    => 'YouTube Import',
		'menu_slug'     => 'youtube-import-settings',
		'capability'    => 'manage_options',
	));
}

/**
 * Include block registration files
 */
function jww_include_block_registrations() {
	require_once get_stylesheet_directory() . '/blocks/index.php';
}
add_action('init', 'jww_include_block_registrations', 5);
