<?php
// Composer autoload
require __DIR__ . '/vendor/autoload.php';

// Theme updates
require_once( get_stylesheet_directory() . '/includes/updates.php' );

// YouTube Importer
require_once( get_stylesheet_directory() . '/includes/class-youtube-importer.php' );

// Link Functions (Music streaming and purchase links)
require_once( get_stylesheet_directory() . '/includes/link-functions.php' );

// Template Tags
require_once( get_stylesheet_directory() . '/includes/template-tags.php' );

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
	
	// Enqueue Font Awesome
	wp_enqueue_style( 
		'font-awesome',
		'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
		array(),
		'6.5.1'
	);
	
	// Enqueue child theme styles
	wp_enqueue_style( 
		'jww-style',
		get_stylesheet_directory_uri() . '/style.css',
		array( $parent_style, 'font-awesome' ),
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

/**
 * Theme Upgrade Handler
 * 
 * Handles upgrade routines when the theme is updated.
 * Uses wp-forge/wp-upgrade-handler for proper version management.
 */
function jww_handle_theme_upgrades() {
	// Only run upgrades in admin
	if ( ! is_admin() ) {
		return;
	}
	
	// Define current theme version
	$current_version = wp_get_theme()->get('Version');
	
	// Get the stored theme version from database
	$stored_version = get_option( 'jww_theme_version', '1.1.9' );
	
	// Use the upgrade handler
	$upgrade_handler = new \WP_Forge\UpgradeHandler\UpgradeHandler(
		get_stylesheet_directory() . '/upgrades',  // Directory where upgrade routines live
		$stored_version,                           // Old theme version (from database)
		$current_version                           // New theme version (from code)
	);
	
	// Run upgrades if needed
	$did_upgrade = $upgrade_handler->maybe_upgrade();
	
	if ( $did_upgrade ) {
		// Update the stored version to prevent running upgrades again
		update_option( 'jww_theme_version', $current_version, true );
		
		// Log that upgrades were completed
		error_log( "Theme upgraded from {$stored_version} to {$current_version}" );
	}
}
add_action( 'admin_init', 'jww_handle_theme_upgrades' );
