<?php
/**
 * Core Theme Functions
 * 
 * Handles theme setup, enqueuing, and core functionality.
 * 
 * @package JWW_Theme
 * @subpackage Includes
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ensure WordPress Interactivity API modules are properly loaded
 * 
 * Fixes conflict with third-party embeds (TikTok, etc.) that can cause
 * "Failed to resolve module specifier '@wordpress/interactivity'" errors.
 */
function jww_ensure_interactivity_api() {
	// Only needed on frontend
	if ( is_admin() ) {
		return;
	}
	
	// Ensure the interactivity API module is registered for pages using interactive blocks
	if ( function_exists( 'wp_register_script_module' ) ) {
		wp_enqueue_script_module( '@wordpress/interactivity' );
	}
}
add_action( 'wp_enqueue_scripts', 'jww_ensure_interactivity_api', 1 );

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
		'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css',
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
	
	// Enqueue sortable table script on show archives, song archive, and single song (Play history table)
	if ( is_post_type_archive( 'show' ) || is_post_type_archive( 'song' ) || is_singular( 'song' ) || is_tax( array( 'tour', 'location' ) ) ) {
		wp_enqueue_script( 
			'jww-archive-sort', 
			get_stylesheet_directory_uri() . '/includes/js/archive-show-sort.js', 
			array(), 
			wp_get_theme()->get('Version'), 
			true 
		);
		// Enqueue location cascade script for hierarchical location filtering
		wp_enqueue_script( 
			'jww-archive-location-cascade', 
			get_stylesheet_directory_uri() . '/includes/js/archive-show-location-cascade.js', 
			array(), 
			wp_get_theme()->get('Version'), 
			true 
		);
	}
	
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
	// Add support for title tag (required for Yoast SEO and WordPress to output <title>)
	add_theme_support( 'title-tag' );
	
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
	
	// Add support for post thumbnails (featured images)
	add_theme_support( 'post-thumbnails' );
	
	// Add support for HTML5 markup
	add_theme_support( 'html5', array(
		'search-form',
		'comment-form',
		'comment-list',
		'gallery',
		'caption',
		'style',
		'script',
	) );
	
	// Add support for automatic feed links
	add_theme_support( 'automatic-feed-links' );
	
	// Add support for custom logo
	add_theme_support( 'custom-logo', array(
		'height'      => 100,
		'width'       => 400,
		'flex-height' => true,
		'flex-width'  => true,
	) );
	
	// Add support for wide alignment
	add_theme_support( 'align-wide' );
}
add_action( 'after_setup_theme', 'jww_theme_support' );

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

/**
 * AJAX: Return play history table HTML for a song (lazy-loaded in accordion on single-song).
 */
function jww_ajax_song_live_stats_fragment() {
	$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';
	if ( ! wp_verify_nonce( $nonce, 'jww_song_live_stats' ) ) {
		wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
	}
	$song_id = isset( $_POST['song_id'] ) ? (int) $_POST['song_id'] : 0;
	if ( ! $song_id ) {
		wp_send_json_error( array( 'message' => 'Missing song_id' ), 400 );
	}
	$post = get_post( $song_id );
	if ( ! $post || $post->post_type !== 'song' || $post->post_status !== 'publish' ) {
		wp_send_json_error( array( 'message' => 'Invalid song' ), 404 );
	}

	ob_start();
	$play_history_content = render_block( array(
		'blockName' => 'jww/song-play-history',
		'attrs'     => array( 'displayMode' => 'table', 'songId' => $song_id, 'limit' => 0 ),
	) );
	if ( $play_history_content ) {
		echo $play_history_content;
	}
	$html = ob_get_clean();

	wp_send_json_success( array( 'html' => $html ) );
}
add_action( 'wp_ajax_jww_song_live_stats_fragment', 'jww_ajax_song_live_stats_fragment' );
add_action( 'wp_ajax_nopriv_jww_song_live_stats_fragment', 'jww_ajax_song_live_stats_fragment' );
