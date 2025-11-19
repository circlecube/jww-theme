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
	// Also set standard Open Graph tags if not already set
	$og_title       = get_the_title( $post->ID );
	$post_type      = get_post_type( $post->ID );
	$og_description = has_excerpt( $post->ID ) ? get_the_excerpt( $post->ID ) : wp_trim_words( get_the_content( $post->ID ), 30 );
	$og_url         = get_permalink( $post->ID );
	$featured_image = get_post_thumbnail_id( $post->ID );
	$default_image  = get_theme_file_uri( 'assets/jesse-welles-world-illustration.png' );
	
	if ( $featured_image ) {
		// Get the full-size image URL
		$image_url = wp_get_attachment_image_url( $featured_image, 'large' );
		
		if ( $image_url ) {
			echo '<meta property="og:image" content="' . esc_url( $image_url ) . '" />' . "\n";
			// Get image MIME type
			$mime_type = get_post_mime_type( $featured_image );
			if ( $mime_type ) {
				echo '<meta property="og:image:type" content="' . esc_attr( $mime_type ) . '" />' . "\n";
			}
		}
	} else {
		// fallback to the site logo
		echo '<meta property="og:image" content="' . esc_url( $default_image ) . '" />' . "\n";
	}
	// get artist
	if ( isset( $post_type ) && ( $post_type === 'song' || $post_type === 'album' ) ) {
		$artist_id = get_field('artist', $post->ID);
		$artist_name = get_the_title($artist_id[0]);
		$title = $artist_name . ' - ' . $post_type . ' - ' . $og_title;
	} elseif ( isset( $post_type ) && $post_type === 'band' ) {
		$artist_name = get_the_title($post->ID);
		$title = $artist_name;
	} else {
		$artist_name = 'Jesse Welles';
		$title = $artist_name . ' - ' . $post_type . ' - ' . $og_title;
	}
	echo '<meta property="og:title" content="' . esc_attr( $title ) . '" />' . "\n";
	echo '<meta property="og:url" content="' . esc_url( $og_url ) . '" />' . "\n";
	echo '<meta property="og:logo" content="' . esc_url( $default_image ) . '" />' . "\n";

	switch ($post_type) {
		case 'song':
			echo '<meta property="og:type" content="music.song" />' . "\n";
			echo '<meta property="og:music:musician" content="' . esc_attr( $artist_name ) . '" />' . "\n";
			echo '<meta property="og:music:album" content="' . esc_attr( $album_name ) . '" />' . "\n";
			echo '<meta property="og:description" content="' . esc_attr( $og_title ) . ' by ' . esc_attr( $artist_name ) . '" />' . "\n";
			echo '<meta property="og:image:alt" content="' . esc_attr( $artist_name ) . ' playing ' . esc_attr( $og_title ) . '" />' . "\n";
			break;
		case 'album':
			echo '<meta property="og:type" content="music.album" />' . "\n";
			echo '<meta property="og:music:musician" content="' . esc_attr( $artist_name ) . '" />' . "\n";
			echo '<meta property="og:description" content="' . esc_attr( $og_title ) . ' by ' . esc_attr( $artist_name ) . '" />' . "\n";
			echo '<meta property="og:image:alt" content="' . esc_attr( $artist_name ) . ' released ' . esc_attr( $og_title ) . '" />' . "\n";
			break;
		case 'band':
			echo '<meta property="og:type" content="music.musician" />' . "\n";
			echo '<meta property="og:music:musician" content="' . esc_attr( $artist_name ) . '" />' . "\n";
			echo '<meta property="og:description" content="' . esc_attr( $artist_name ) . '" />' . "\n";
			echo '<meta property="og:image:alt" content="' . esc_attr( $artist_name ) . '" />' . "\n";
			break;
		default: // post, page, etc.
			echo '<meta property="og:type" content="article" />' . "\n";
			echo '<meta property="og:description" content="' . esc_attr( $og_description ) . '" />' . "\n";
			echo '<meta property="og:image:alt" content="' . esc_attr( $og_description ) . '" />' . "\n";
			break;
	}
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

/**
 * Include custom post types (bands, albums, songs) in WordPress search results
 * 
 * Modifies the main search query to include band, album, and song post types.
 * This ensures that bands, albums, and songs (including their lyrics/content)
 * appear in search results.
 */
function jww_include_custom_post_types_in_search( $query ) {
	// Only modify the main search query on the frontend
	if ( ! is_admin() && $query->is_main_query() && $query->is_search() ) {
		// Get the current post types being searched
		$post_types = $query->get( 'post_type' );
		
		// If post_type is not set or is a string, convert to array
		if ( empty( $post_types ) ) {
			$post_types = array( 'post', 'page' );
		} elseif ( is_string( $post_types ) ) {
			$post_types = array( $post_types );
		}
		
		// Add our custom post types to the search
		$custom_post_types = array( 'band', 'album', 'song' );
		$post_types = array_merge( $post_types, $custom_post_types );
		
		// Remove duplicates and ensure we have valid post types
		$post_types = array_unique( $post_types );
		
		// Exclude attachments (media files) from search results
		$post_types = array_diff( $post_types, array( 'attachment', 'acf-field', 'acf-field-group' ) );
		
		// Set the modified post types
		$query->set( 'post_type', $post_types );
		
		// Set custom ordering: songs, albums, bands, pages, posts
		$query->set( 'orderby', 'post_type' );
		$query->set( 'order', 'ASC' );
	}
}
add_action( 'pre_get_posts', 'jww_include_custom_post_types_in_search' );

/**
 * Exclude attachments from search results in REST API queries
 * 
 * Block templates use REST API queries which bypass pre_get_posts.
 * This filter catches those queries and excludes attachments.
 * 
 * @param array           $args    Query arguments
 * @param WP_REST_Request $request REST API request object
 * @return array Modified query arguments
 */
function jww_exclude_attachments_from_rest_search( $args, $request ) {
	// Check if this is a search query (search parameter or s parameter)
	if ( ( isset( $request['search'] ) && ! empty( $request['search'] ) ) || 
	     ( isset( $args['s'] ) && ! empty( $args['s'] ) ) ) {
		// Get current post types
		$post_types = isset( $args['post_type'] ) ? $args['post_type'] : array( 'post', 'page', 'song', 'album', 'band' );
		
		// Convert to array if string
		if ( is_string( $post_types ) ) {
			$post_types = array( $post_types );
		}
		
		// Exclude attachments
		$post_types = array_diff( $post_types, array( 'attachment', 'acf-field', 'acf-field-group' ) );
		
		// Set the modified post types
		$args['post_type'] = $post_types;
		
		// Set custom ordering: songs, albums, bands, pages, posts
		// Note: REST API queries will be sorted by the posts_results filter
		$args['orderby'] = 'post_type';
		$args['order'] = 'ASC';
	}
	
	return $args;
}
// Apply to all post types that might be searched
add_filter( 'rest_post_query', 'jww_exclude_attachments_from_rest_search', 10, 2 );
add_filter( 'rest_page_query', 'jww_exclude_attachments_from_rest_search', 10, 2 );
add_filter( 'rest_song_query', 'jww_exclude_attachments_from_rest_search', 10, 2 );
add_filter( 'rest_album_query', 'jww_exclude_attachments_from_rest_search', 10, 2 );
add_filter( 'rest_band_query', 'jww_exclude_attachments_from_rest_search', 10, 2 );

/**
 * Filter search results to remove attachments and sort by post type priority
 * 
 * This is a final safety net to remove any attachments that might
 * slip through from block template queries, and also sorts results
 * by post type priority: songs, albums, bands, pages, posts.
 * 
 * @param array    $posts Array of post objects
 * @param WP_Query $query The WordPress query object
 * @return array Filtered and sorted array of post objects
 */
function jww_filter_attachments_from_search_results( $posts, $query ) {
	// Only filter search queries on the frontend
	if ( ! is_admin() && $query->is_search() ) {
		// Remove attachments and ACF post types from results
		$excluded_types = array( 'attachment', 'acf-field', 'acf-field-group' );
		$posts = array_filter( $posts, function( $post ) use ( $excluded_types ) {
			return ! in_array( $post->post_type, $excluded_types, true );
		} );
		
		// Define post type priority order
		$post_type_order = array(
			'song'  => 1,
			'album' => 2,
			'band'  => 3,
			'page'  => 4,
			'post'  => 5,
		);
		
		// Sort posts by post type priority
		usort( $posts, function( $a, $b ) use ( $post_type_order ) {
			$a_priority = isset( $post_type_order[ $a->post_type ] ) ? $post_type_order[ $a->post_type ] : 999;
			$b_priority = isset( $post_type_order[ $b->post_type ] ) ? $post_type_order[ $b->post_type ] : 999;
			
			// If same priority, maintain original order (by date)
			if ( $a_priority === $b_priority ) {
				return strtotime( $b->post_date ) - strtotime( $a->post_date );
			}
			
			return $a_priority - $b_priority;
		} );
		
		// Re-index array
		$posts = array_values( $posts );
	}
	return $posts;
}
add_filter( 'posts_results', 'jww_filter_attachments_from_search_results', 10, 2 );

/**
 * Include ACF fields (lyrics and lyric_annotations) in WordPress search
 * 
 * Modifies the search query to search within ACF custom fields for songs.
 * This allows searching song lyrics and lyric annotations in addition to
 * the standard post title, content, and excerpt.
 */
function jww_search_acf_fields( $search, $wp_query ) {
	// Only modify the main search query on the frontend
	if ( ! is_admin() && $wp_query->is_main_query() && $wp_query->is_search() && ! empty( $wp_query->get( 's' ) ) ) {
		global $wpdb;
		
		// Get the search term
		$search_term = $wp_query->get( 's' );
		
		// Escape the search term for SQL
		$search_term = $wpdb->esc_like( $search_term );
		$search_term = '%' . $search_term . '%';
		
		// Get the post types being searched
		$post_types = $wp_query->get( 'post_type' );
		if ( empty( $post_types ) ) {
			$post_types = array( 'post', 'page' );
		} elseif ( is_string( $post_types ) ) {
			$post_types = array( $post_types );
		}
		
		// Only add ACF field search if 'song' post type is included
		if ( in_array( 'song', $post_types, true ) ) {
			// Add OR condition to search in ACF meta fields
			$search .= $wpdb->prepare(
				" OR (
					EXISTS (
						SELECT 1 FROM {$wpdb->postmeta}
						WHERE {$wpdb->postmeta}.post_id = {$wpdb->posts}.ID
						AND (
							({$wpdb->postmeta}.meta_key = 'lyrics' AND {$wpdb->postmeta}.meta_value LIKE %s)
							OR ({$wpdb->postmeta}.meta_key = 'lyric_annotations' AND {$wpdb->postmeta}.meta_value LIKE %s)
						)
					)
				)",
				$search_term,
				$search_term
			);
		}
	}
	
	return $search;
}
add_filter( 'posts_search', 'jww_search_acf_fields', 10, 2 );

/**
 * Get random lyrics data for reuse across templates
 * 
 * @return array|false Array with 'song_id', 'lyrics_line', and 'song_title', or false on failure
 */
function jww_get_random_lyrics_data() {
	// Get a random song that has lyrics
	$songs = get_posts([
		'post_type' => 'song',
		'posts_per_page' => 1,
		'orderby'        => 'rand',
		'order'          => 'DESC',
		'meta_query' => [
			[
				'key' => 'lyrics',
				'compare' => 'EXISTS'
			],
			[
				'key' => 'lyrics',
				'value' => '',
				'compare' => '!='
			]
		],
		'tax_query'      => array(
			array(
				'taxonomy' => 'category',
				'field'     => 'slug',
				'terms'    => 'original'
			)
		),
		'fields' => 'ids'
	]);

	if (empty($songs)) {
		return false;
	}

	// Get a random song
	$random_song_id = $songs[0];
	$song_title = get_the_title($random_song_id);
	$lyrics = get_field('lyrics', $random_song_id);

	if (empty($lyrics)) {
		return false;
	}

	// Split lyrics into lines and filter out empty lines
	$lyrics_lines = array_filter(
		array_map('trim', explode("\n", $lyrics)),
		function($line) {
			return !empty($line) && strlen($line) > 10; // Filter out very short lines
		}
	);

	if (empty($lyrics_lines)) {
		return false;
	}

	// Get a random line
	$random_line = $lyrics_lines[array_rand($lyrics_lines)];
	
	// Strip HTML tags from the lyrics line
	$random_line = strip_tags($random_line);

	return [
		'song_id' => $random_song_id,
		'lyrics_line' => $random_line,
		'song_title' => $song_title
	];
}

/**
 * Shortcode for displaying random lyrics inline (minimal format)
 * Usage: [random_lyrics_inline]
 * 
 * @return string HTML output or empty string on failure
 */
function jww_random_lyrics_inline_shortcode() {
	$random_lyrics = jww_get_random_lyrics_data();
	
	if (!$random_lyrics) {
		return '';
	}
	
	ob_start();
	get_template_part('template-parts/random-lyrics-p', null, $random_lyrics);
	return ob_get_clean();
}
add_shortcode('random_lyrics_inline', 'jww_random_lyrics_inline_shortcode');

/**
 * Is Home Page
 * 
 * @return bool True if current page is the home page, false otherwise
 */
function jww_is_home_page() {
	return is_front_page() || is_home();
}

/**
 * Shortcode for header site title (adapts based on page type)
 * Usage: [header_site_title]
 * 
 * @return string HTML output
 */
function jww_header_site_title_shortcode() {	
	if ( jww_is_home_page() ) {
		$site_title = '<!-- wp:site-title {"level":0,"className":"header-large-text","style":{"typography":{"fontSize":"7vw","lineHeight":"1.2"},"layout":{"selfStretch":"fill","flexSize":null},"elements":{"link":{"color":{"text":"var:preset|color|base"}}}},"textColor":"base"} /-->';
	} else {
		$site_title = '<!-- wp:site-title {"level":2,"className":"header-normal-text","fontSize":"xx-large","fontFamily":"roboto-slab"} /-->';
	}

	
	return do_blocks($site_title);
}
add_shortcode('header_site_title', 'jww_header_site_title_shortcode');

/**
 * Shortcode for header navigation (adapts based on page type)
 * Usage: [header_navigation]
 * 
 * @return string HTML output
 */
function jww_header_navigation_shortcode() {
	if ( jww_is_home_page() ) {
		$navigation = '<!-- wp:navigation {"overlayBackgroundColor":"base","overlayTextColor":"contrast","className":"header-nav-home","style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"layout":{"type":"flex","justifyContent":"right","orientation":"vertical","flexWrap":"nowrap"}} /-->';
	} else {
		$navigation = '<!-- wp:navigation {"className":"header-nav-site","overlayBackgroundColor":"base","overlayTextColor":"contrast","style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"fontSize":"medium","layout":{"type":"flex","justifyContent":"right","flexWrap":"wrap"}} /-->';
	}
	
	return do_blocks($navigation);
}
add_shortcode('header_navigation', 'jww_header_navigation_shortcode');
