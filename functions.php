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
	
	// Add support for automatic document title generation
	add_theme_support( 'title-tag' );
	
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

/**
 * Add support for orderby=rand in REST API for songs
 * 
 * Allows random ordering of songs via REST API query parameter:
 * /wp-json/wp/v2/song?orderby=rand
 */
function jww_rest_song_query( $args, $request ) {
	// Check if orderby=rand is requested
	if ( isset( $request['orderby'] ) && $request['orderby'] === 'rand' ) {
		$args['orderby'] = 'rand';
		// Remove order parameter when using rand (it's not applicable)
		unset( $args['order'] );
	}
	
	return $args;
}
add_filter( 'rest_song_query', 'jww_rest_song_query', 10, 2 );

/**
 * Register orderby=rand as a valid REST API parameter for songs
 */
function jww_rest_song_collection_params( $query_params ) {
	if ( isset( $query_params['orderby'] ) && isset( $query_params['orderby']['enum'] ) ) {
		// Add 'rand' to the allowed orderby values if not already present
		if ( ! in_array( 'rand', $query_params['orderby']['enum'], true ) ) {
			$query_params['orderby']['enum'][] = 'rand';
		}
	}
	
	return $query_params;
}
add_filter( 'rest_song_collection_params', 'jww_rest_song_collection_params', 10, 1 );

/**
 * Add Open Graph meta tags for all posts
 * 
 * Sets the featured image as the Open Graph image for all singular posts
 */
function jww_open_graph_tags() {
	// Only run on singular posts (single post, page, or custom post type)
	if ( ! is_singular() ) {
		return;
	}
	
	global $post;
	
	// Get the featured image
	$featured_image_id = get_post_thumbnail_id( $post->ID );
	
	if ( $featured_image_id ) {
		// Get the full-size image URL
		$image_url = wp_get_attachment_image_url( $featured_image_id, 'full' );
		
		if ( $image_url ) {
			// Get image dimensions
			$image_meta = wp_get_attachment_image_src( $featured_image_id, 'full' );
			$image_width = isset( $image_meta[1] ) ? $image_meta[1] : '';
			$image_height = isset( $image_meta[2] ) ? $image_meta[2] : '';
			
			// Output Open Graph tags
			echo '<meta property="og:image" content="' . esc_url( $image_url ) . '" />' . "\n";
			
			if ( $image_width ) {
				echo '<meta property="og:image:width" content="' . esc_attr( $image_width ) . '" />' . "\n";
			}
			
			if ( $image_height ) {
				echo '<meta property="og:image:height" content="' . esc_attr( $image_height ) . '" />' . "\n";
			}
			
			// Get image MIME type
			$mime_type = get_post_mime_type( $featured_image_id );
			if ( $mime_type ) {
				echo '<meta property="og:image:type" content="' . esc_attr( $mime_type ) . '" />' . "\n";
			}
		}
	}
	
	// Also set standard Open Graph tags if not already set
	$og_title = get_the_title( $post->ID );
	$og_description = has_excerpt( $post->ID ) ? get_the_excerpt( $post->ID ) : wp_trim_words( get_the_content( $post->ID ), 30 );
	$og_url = get_permalink( $post->ID );
	
	echo '<meta property="og:title" content="' . esc_attr( $og_title ) . '" />' . "\n";
	echo '<meta property="og:description" content="' . esc_attr( wp_strip_all_tags( $og_description ) ) . '" />' . "\n";
	echo '<meta property="og:url" content="' . esc_url( $og_url ) . '" />' . "\n";
	echo '<meta property="og:type" content="article" />' . "\n";
}
add_action( 'wp_head', 'jww_open_graph_tags', 5 );

/**
 * Add featured image URL to REST API response for all post types
 * 
 * Adds a 'featured_image_url' field to all REST API responses for post types
 * that support featured images
 */
function jww_register_featured_image_rest_field() {
	// Get all public post types that support thumbnails
	$post_types = get_post_types( array(
		'public'       => true,
		'show_in_rest' => true,
	), 'names' );
	
	foreach ( $post_types as $post_type ) {
		// Check if post type supports thumbnails
		if ( post_type_supports( $post_type, 'thumbnail' ) ) {
			register_rest_field(
				$post_type,
				'featured_image_url',
				array(
					'get_callback' => function( $post ) {
						$featured_image_id = get_post_thumbnail_id( $post['id'] );
						if ( $featured_image_id ) {
							return wp_get_attachment_image_url( $featured_image_id, 'full' );
						}
						return null;
					},
					'schema' => array(
						'description' => __( 'URL of the featured image (full size).' ),
						'type'        => 'string',
						'format'      => 'uri',
						'context'     => array( 'view', 'edit' ),
					),
				)
			);
		}
	}
}
add_action( 'rest_api_init', 'jww_register_featured_image_rest_field' );
