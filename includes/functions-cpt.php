<?php
/**
 * Custom Post Type and Taxonomy Registration Functions
 * 
 * @package JWW_Theme
 * @subpackage Includes
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register Show Post Type
 * 
 * This ensures the post type is registered. ACF should also register it from JSON,
 * but this provides a reliable fallback. If ACF registers it first, this will skip.
 */
function jww_register_show_post_type() {
	// Check if post type already exists (registered by ACF)
	if ( post_type_exists( 'show' ) ) {
		// ACF already registered it, but let's ensure archive is set correctly
		$post_type_obj = get_post_type_object( 'show' );
		if ( $post_type_obj && empty( $post_type_obj->has_archive ) ) {
			// Force archive if ACF didn't set it
			$post_type_obj->has_archive = 'shows';
		}
		return;
	}

	$args = array(
		'labels'              => array(
			'name'               => 'Shows',
			'singular_name'      => 'Show',
			'menu_name'          => 'Shows',
			'all_items'          => 'All Shows',
			'edit_item'          => 'Edit Show',
			'view_item'          => 'View Show',
			'view_items'         => 'View Shows',
			'add_new_item'       => 'Add New Show',
			'add_new'            => 'Add New Show',
			'new_item'           => 'New Show',
			'search_items'    => 'Search Shows',
			'not_found'          => 'No shows found',
			'not_found_in_trash' => 'No shows found in Trash',
			'archives'           => 'Shows Archive',
		),
		'public'              => true,
		'publicly_queryable'  => true,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'show_in_nav_menus'   => true,
		'show_in_admin_bar'    => true,
		'show_in_rest'         => true,
		'has_archive'         => 'shows', // Archive slug matches ACF JSON setting
		'rewrite'             => array(
			'slug'       => 'show',
			'with_front' => true,
			'feeds'      => false,
			'pages'      => true,
		),
		'query_var'            => 'show',
		'capability_type'      => 'post',
		'menu_position'       => 54,
		'menu_icon'            => 'dashicons-calendar-alt',
		'supports'             => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
		'taxonomies'           => array( 'tour', 'location' ),
	);

	register_post_type( 'show', $args );
}
add_action( 'init', 'jww_register_show_post_type', 0 );

/**
 * Enable song archive with slug "songs"
 * Song CPT is registered by ACF; we adjust archive and rewrite after init.
 */
function jww_enable_song_archive() {
	$obj = get_post_type_object( 'song' );
	if ( ! $obj ) {
		return;
	}
	$obj->has_archive = true;
	$obj->rewrite = array(
		'slug'       => 'songs',
		'with_front' => true,
		'feeds'      => false,
		'pages'      => true,
	);
}
add_action( 'init', 'jww_enable_song_archive', 20 );

/**
 * Register Tour and Location Taxonomies for Shows
 */
function jww_register_show_taxonomies() {
	// Register Tour Taxonomy (non-hierarchical)
	register_taxonomy( 'tour', array( 'show' ), array(
		'hierarchical'      => false,
		'labels'            => array(
			'name'              => 'Tours',
			'singular_name'     => 'Tour',
			'search_items'      => 'Search Tours',
			'all_items'         => 'All Tours',
			'parent_item'       => null,
			'parent_item_colon' => null,
			'edit_item'         => 'Edit Tour',
			'update_item'       => 'Update Tour',
			'add_new_item'      => 'Add New Tour',
			'new_item_name'     => 'New Tour Name',
			'menu_name'         => 'Tours',
		),
		'show_ui'           => true,
		'show_admin_column' => true,
		'query_var'         => 'tour', // Use 'tour' as query var name (not true) to avoid conflicts
		'rewrite'           => array( 'slug' => 'tour' ),
		'show_in_rest'      => true,
	) );

	// Register Location Taxonomy (hierarchical: Country > City > Venue)
	register_taxonomy( 'location', array( 'show' ), array(
		'hierarchical'      => true,
		'labels'            => array(
			'name'              => 'Locations',
			'singular_name'     => 'Location',
			'search_items'      => 'Search Locations',
			'all_items'         => 'All Locations',
			'parent_item'       => 'Parent Location',
			'parent_item_colon' => 'Parent Location:',
			'edit_item'         => 'Edit Location',
			'update_item'       => 'Update Location',
			'add_new_item'      => 'Add New Location',
			'new_item_name'     => 'New Location Name',
			'menu_name'         => 'Locations',
		),
		'show_ui'           => true,
		'show_admin_column' => false, // We'll add a custom column that shows the full hierarchy
		'query_var'         => 'location', // Use 'location' as query var name (not true) to avoid conflicts
		'rewrite'           => array( 'slug' => 'location' ),
		'show_in_rest'      => true,
	) );
}
add_action( 'init', 'jww_register_show_taxonomies', 0 );

/**
 * Include block registration files
 */
function jww_include_block_registrations() {
	require_once get_stylesheet_directory() . '/blocks/index.php';
}
add_action('init', 'jww_include_block_registrations', 5);

/**
 * Ensure /shows/ archive is recognized even with query parameters
 * 
 * Fixes 404 errors when accessing /shows/?type=all&tour=123 etc.
 * 
 * The issue: 'tour' and 'location' are taxonomy slugs, so WordPress tries to
 * interpret ?tour=123 as a taxonomy archive query, causing 404s. We need to
 * prevent this and ensure it stays as a post type archive.
 */
function jww_fix_show_archive_request( $query_vars ) {
	// Only on frontend
	if ( is_admin() ) {
		return $query_vars;
	}

	// Check if we're on the /shows/ URL
	$request_uri = $_SERVER['REQUEST_URI'] ?? '';
	$parsed_url = parse_url( $request_uri );
	$path = trim( $parsed_url['path'] ?? '', '/' );
	
	// Check if path is exactly "shows" (the archive)
	if ( $path === 'shows' ) {
		// Set post_type to ensure it's recognized as an archive
		$query_vars['post_type'] = 'show';
		
		// Remove any conflicting query vars that might cause 404
		unset( $query_vars['name'] );
		unset( $query_vars['pagename'] );
		
		// CRITICAL: Remove 'tour' and 'location' from query_vars to prevent WordPress
		// from treating them as taxonomy archive queries. They're just GET parameters
		// for filtering, not taxonomy queries. The template will read them from $_GET.
		// This prevents WordPress from trying to load a taxonomy archive instead.
		if ( isset( $query_vars['tour'] ) ) {
			unset( $query_vars['tour'] );
		}
		if ( isset( $query_vars['location'] ) ) {
			unset( $query_vars['location'] );
		}
		
		// Also ensure we're not being treated as a taxonomy archive
		unset( $query_vars['taxonomy'] );
		unset( $query_vars['term'] );
	}
	
	return $query_vars;
}
add_filter( 'request', 'jww_fix_show_archive_request', 5, 1 ); // Higher priority to run early

/**
 * Fix query object to ensure /shows/ archive is recognized with tour/location params
 * 
 * This runs after parse_query to fix any issues with query recognition.
 */
function jww_fix_show_archive_query( $query ) {
	// Only on frontend, main query
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	// Check if we're on the /shows/ URL
	$request_uri = $_SERVER['REQUEST_URI'] ?? '';
	$parsed_url = parse_url( $request_uri );
	$path = trim( $parsed_url['path'] ?? '', '/' );
	
	// If we're on /shows/ but WordPress thinks it's a taxonomy archive, fix it
	if ( $path === 'shows' && $query->is_tax() && ! $query->is_post_type_archive( 'show' ) ) {
		// Force it to be a post type archive instead
		$query->is_tax = false;
		$query->is_post_type_archive = true;
		$query->is_archive = true;
		$query->set( 'post_type', 'show' );
		$query->set( 'tour', '' );
		$query->set( 'location', '' );
	}
}
add_action( 'parse_query', 'jww_fix_show_archive_query', 5 );

/**
 * Include scheduled posts in show archives and taxonomy archives
 * 
 * Allows scheduled (future) shows to appear in archive listings
 */
function jww_include_scheduled_shows_in_archives( $query ) {
	// Only modify show archives on the frontend
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}
	
	// Include scheduled posts for show post type archives
	if ( is_post_type_archive( 'show' ) || is_tax( array( 'tour', 'location' ) ) ) {
		$post_status = $query->get( 'post_status' );
		
		// If post_status is not set or only 'publish', add 'future'
		if ( empty( $post_status ) ) {
			$query->set( 'post_status', array( 'publish', 'future' ) );
		} elseif ( is_string( $post_status ) && $post_status === 'publish' ) {
			$query->set( 'post_status', array( 'publish', 'future' ) );
		} elseif ( is_array( $post_status ) && ! in_array( 'future', $post_status, true ) ) {
			$post_status[] = 'future';
			$query->set( 'post_status', $post_status );
		}
	}
}
add_action( 'pre_get_posts', 'jww_include_scheduled_shows_in_archives' );

/**
 * Ensure /songs/ archive works with query parameters (display, sort, etc.)
 */
function jww_fix_song_archive_request( $query_vars ) {
	if ( is_admin() ) {
		return $query_vars;
	}
	$request_uri = $_SERVER['REQUEST_URI'] ?? '';
	$parsed = parse_url( $request_uri );
	$path = trim( $parsed['path'] ?? '', '/' );
	if ( $path === 'songs' ) {
		$query_vars['post_type'] = 'song';
		unset( $query_vars['name'] );
		unset( $query_vars['pagename'] );
	}
	return $query_vars;
}
add_filter( 'request', 'jww_fix_song_archive_request', 5 );
