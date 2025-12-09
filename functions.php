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
 * Add ACF Options Page for YouTube Import Settings
 * Must be called on acf/init hook to avoid translation loading issues
 */
function jww_add_acf_options_page() {
	if( function_exists('acf_add_options_page') ) {
		acf_add_options_page(array(
			'page_title'    => 'YouTube Import Settings',
			'menu_title'    => 'YouTube Import',
			'menu_slug'     => 'youtube-import-settings',
			'capability'    => 'manage_options',
		));
	}
}
add_action( 'acf/init', 'jww_add_acf_options_page' );

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
 * Fallback meta description for SEO
 * 
 * Outputs a meta description tag based on post type and content.
 */
function jww_fallback_meta_description() {
	// Skip if Yoast SEO has already set a meta description
	if ( class_exists( 'WPSEO_Meta' ) && is_singular() ) {
		$yoast_desc = WPSEO_Meta::get_value( 'metadesc', get_the_ID() );
		if ( ! empty( $yoast_desc ) ) {
			return; // Yoast has a custom description set
		}
		// Also check if Yoast has a default template that would generate a description
		if ( function_exists( 'YoastSEO' ) ) {
			return; // Let Yoast handle it with its templates
		}
	}
	
	// Generate fallback description based on context
	$description = '';
	
	if ( is_singular() ) {
		global $post;
		$post_type = get_post_type();
		
		switch ( $post_type ) {
			case 'song':
				// Get artist name(s)
				$artist_ids = get_field( 'artist', $post->ID );
				$artist_string = 'Jesse Welles';
				
				if ( ! empty( $artist_ids ) ) {
					if ( ! is_array( $artist_ids ) ) {
						$artist_ids = array( $artist_ids );
					}
					$artist_names = array();
					foreach ( $artist_ids as $artist ) {
						$artist_id = is_object( $artist ) ? $artist->ID : $artist;
						$artist_names[] = get_the_title( $artist_id );
					}
					if ( ! empty( $artist_names ) ) {
						$artist_string = implode( ', ', $artist_names );
					}
				}
				
				// Try to get a snippet from lyrics
				$lyrics = get_field( 'lyrics', $post->ID );
				if ( ! empty( $lyrics ) ) {
					$lyrics_text = wp_strip_all_tags( $lyrics );
					$lyrics_snippet = wp_trim_words( $lyrics_text, 15, '...' );
					$description = sprintf(
						'"%s" by %s. %s',
						get_the_title(),
						$artist_string,
						$lyrics_snippet
					);
				} else {
					$description = sprintf(
						'Listen to "%s" by %s on Jesse Welles World.',
						get_the_title(),
						$artist_string
					);
				}
				break;
				
			case 'album':
				$artist_ids = get_field( 'artist', $post->ID );
				$artist_string = 'Jesse Welles';
				
				if ( ! empty( $artist_ids ) ) {
					if ( ! is_array( $artist_ids ) ) {
						$artist_ids = array( $artist_ids );
					}
					$artist_names = array();
					foreach ( $artist_ids as $artist ) {
						$artist_id = is_object( $artist ) ? $artist->ID : $artist;
						$artist_names[] = get_the_title( $artist_id );
					}
					if ( ! empty( $artist_names ) ) {
						$artist_string = implode( ', ', $artist_names );
					}
				}
				
				$description = sprintf(
					'%s - Album by %s. Explore tracks, lyrics, and more.',
					get_the_title(),
					$artist_string
				);
				break;
				
			case 'band':
				$description = sprintf(
					'%s - Artist profile, discography, songs, and more on Jesse Welles World.',
					get_the_title()
				);
				break;
				
			default:
				// Posts and pages - use excerpt or content
				if ( has_excerpt( $post->ID ) ) {
					$description = wp_strip_all_tags( get_the_excerpt( $post->ID ) );
				} else {
					$content = get_the_content( null, false, $post->ID );
					$content = wp_strip_all_tags( strip_shortcodes( $content ) );
					$description = wp_trim_words( $content, 25, '...' );
				}
				break;
		}
	} elseif ( is_home() || is_front_page() ) {
		$description = get_bloginfo( 'description' );
	} elseif ( is_archive() ) {
		if ( is_post_type_archive( 'song' ) ) {
			$description = 'Browse all songs, lyrics, and music from Jesse Welles World.';
		} elseif ( is_post_type_archive( 'album' ) ) {
			$description = 'Explore albums and discography from Jesse Welles World.';
		} elseif ( is_post_type_archive( 'band' ) ) {
			$description = 'Discover artists and bands on Jesse Welles World.';
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term && ! empty( $term->description ) ) {
				$description = wp_strip_all_tags( $term->description );
			} else {
				$description = sprintf( 'Browse content in %s on Jesse Welles World.', single_term_title( '', false ) );
			}
		}
	} elseif ( is_search() ) {
		$description = sprintf( 'Search results for "%s" on Jesse Welles World.', get_search_query() );
	}
	
	// Output the meta description if we have one
	if ( ! empty( $description ) ) {
		// Trim to recommended length (150-160 characters)
		if ( strlen( $description ) > 160 ) {
			$description = wp_trim_words( $description, 25, '...' );
			// If still too long, hard truncate
			if ( strlen( $description ) > 160 ) {
				$description = substr( $description, 0, 157 ) . '...';
			}
		}
		
		echo '<meta name="description" content="' . esc_attr( $description ) . '" />' . "\n";
	}
}
add_action( 'wp_head', 'jww_fallback_meta_description', 1 );

/**
 * Customize document title for song post type
 * 
 * Modifies the title tag format for songs to include artist name.
 * Works with Yoast SEO - this filter runs before Yoast's processing.
 * 
 * @param array $title_parts The document title parts.
 * @return array Modified title parts.
 */
function jww_song_document_title( $title_parts ) {
	// Only modify on singular song posts
	if ( ! is_singular( 'song' ) ) {
		return $title_parts;
	}
	
	global $post;
	
	// Get the artist(s) from ACF field
	$artist_ids = get_field( 'artist', $post->ID );
	
	if ( ! empty( $artist_ids ) ) {
		// Handle both single and array of artists
		if ( ! is_array( $artist_ids ) ) {
			$artist_ids = array( $artist_ids );
		}
		
		// Get artist names
		$artist_names = array();
		foreach ( $artist_ids as $artist ) {
			$artist_id = is_object( $artist ) ? $artist->ID : $artist;
			$artist_names[] = get_the_title( $artist_id );
		}
		
		if ( ! empty( $artist_names ) ) {
			$artist_string = implode( ', ', $artist_names );
			// Format: "Song Title by Artist Name"
			$title_parts['title'] = get_the_title( $post->ID ) . ' by ' . $artist_string;
		}
	}
	
	return $title_parts;
}
add_filter( 'document_title_parts', 'jww_song_document_title', 5 );

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
		
		// Exclude attachments and revisions from search results
		$post_types = array_diff( $post_types, array( 'attachment', 'acf-field', 'acf-field-group', 'acf-post-type', 'revision' ) );
		
		// Set the modified post types
		$query->set( 'post_type', $post_types );
		
		// Explicitly exclude revisions by post status
		// Revisions have post_status 'inherit', so we need to exclude them
		$post_status = $query->get( 'post_status' );
		if ( empty( $post_status ) ) {
			$post_status = array( 'publish' );
		} elseif ( is_string( $post_status ) ) {
			$post_status = array( $post_status );
		}
		// Ensure 'inherit' (revisions) is not included
		if ( in_array( 'inherit', $post_status, true ) ) {
			$post_status = array_diff( $post_status, array( 'inherit' ) );
		}
		// Ensure we have at least 'publish' status
		if ( empty( $post_status ) ) {
			$post_status = array( 'publish' );
		}
		$query->set( 'post_status', $post_status );
		
		// Also explicitly exclude revision post type
		// Revisions have post_type 'revision' and post_status 'inherit'
		// We've already excluded 'revision' from post_type, but add WHERE clause filter as backup
		
		// Set posts per page for search results (default is 10)
		$posts_per_page = apply_filters( 'jww_search_posts_per_page', 50 );
		$query->set( 'posts_per_page', $posts_per_page );
	}
}
add_action( 'pre_get_posts', 'jww_include_custom_post_types_in_search' );

/**
 * Sort search results by post type priority
 * 
 * Sorts results in the order: song, album, band, post, page
 * 
 * @param array    $posts Array of post objects
 * @param WP_Query $query The WordPress query object
 * @return array Sorted array of post objects
 */
function jww_sort_search_results_by_post_type( $posts, $query ) {
	// Only sort search queries on the frontend
	if ( ! is_admin() && $query->is_search() && $query->is_main_query() ) {
		// Define post type priority order
		$post_type_order = array(
			'song'  => 1,
			'album' => 2,
			'band'  => 3,
			'post'  => 4,
			'page'  => 5,
		);
		
		// Sort posts by post type priority
		usort( $posts, function( $a, $b ) use ( $post_type_order ) {
			$a_priority = isset( $post_type_order[ $a->post_type ] ) ? $post_type_order[ $a->post_type ] : 999;
			$b_priority = isset( $post_type_order[ $b->post_type ] ) ? $post_type_order[ $b->post_type ] : 999;
			
			// If same priority, maintain original order (by date, newest first)
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
add_filter( 'posts_results', 'jww_sort_search_results_by_post_type', 10, 2 );

/**
 * Filter search results to remove unwanted post types
 * 
 * Final safety net to remove revisions and ACF post types
 * that might slip through from any source.
 * 
 * @param array    $posts Array of post objects
 * @param WP_Query $query The WordPress query object
 * @return array Filtered array of post objects
 */
function jww_filter_unwanted_post_types_from_search( $posts, $query ) {
	// Only filter search queries on the frontend
	if ( ! is_admin() && $query->is_search() && $query->is_main_query() ) {
		// Remove unwanted post types
		$excluded_types = array( 'attachment', 'acf-field', 'acf-field-group', 'acf-post-type', 'revision', 'ap_outbox' );
		$posts = array_filter( $posts, function( $post ) use ( $excluded_types ) {
			return ! in_array( $post->post_type, $excluded_types, true );
		} );
		
		// Also filter by post_status to exclude revisions
		$posts = array_filter( $posts, function( $post ) {
			return $post->post_status !== 'inherit';
		} );
		
		// Re-index array
		$posts = array_values( $posts );
	}
	return $posts;
}
add_filter( 'posts_results', 'jww_filter_unwanted_post_types_from_search', 5, 2 );

// Removed jww_acf_lyrics_search_query - it was clearing the search term and limiting results
// Lyrics search is now handled by jww_search_acf_fields filter which extends the search
// without interfering with standard post type searches

/**
 * Extract snippet around search term match
 * 
 * Finds the first occurrence of any search term and extracts
 * a snippet of text around it.
 * 
 * @param string $content The full content to search
 * @param array  $search_terms Array of search terms
 * @param int    $snippet_length Characters before/after match
 * @return string|false Snippet text or false if no match
 */
function jww_extract_snippet_around_match( $content, $search_terms, $snippet_length = 150 ) {
	if ( empty( $content ) || empty( $search_terms ) ) {
		return false;
	}
	
	$content_lower = mb_strtolower( $content, 'UTF-8' );
	$best_position = -1;
	$best_term = '';
	
	// Find the first occurrence of any search term
	foreach ( $search_terms as $term ) {
		$term_trimmed = trim( $term );
		if ( strlen( $term_trimmed ) > 2 ) {
			$term_lower = mb_strtolower( $term_trimmed, 'UTF-8' );
			$position = mb_strpos( $content_lower, $term_lower );
			if ( $position !== false && ( $best_position === -1 || $position < $best_position ) ) {
				$best_position = $position;
				$best_term = $term_trimmed;
			}
		}
	}
	
	if ( $best_position === -1 ) {
		return false;
	}
	
	// Extract snippet around the match
	$start = max( 0, $best_position - $snippet_length );
	$end = min( mb_strlen( $content ), $best_position + mb_strlen( $best_term ) + $snippet_length );
	
	$snippet = mb_substr( $content, $start, $end - $start, 'UTF-8' );
	
	// Add ellipsis if not at start/end
	if ( $start > 0 ) {
		$snippet = '...' . $snippet;
	}
	if ( $end < mb_strlen( $content ) ) {
		$snippet = $snippet . '...';
	}
	
	return $snippet;
}

/**
 * Highlight search terms in text by extending to full words
 * 
 * Finds search terms in text and highlights the full word
 * containing each match. Works entirely in plain text first,
 * then applies all highlights at once to avoid position shifting.
 * 
 * @param string $text The text to highlight (should be plain text)
 * @param array  $search_terms Array of search terms
 * @return string Text with highlighted words
 */
function jww_highlight_search_terms_in_text( $text, $search_terms ) {
	if ( empty( $text ) || empty( $search_terms ) ) {
		return $text;
	}
	
	// Strip any existing HTML to work with plain text
	$plain_text = wp_strip_all_tags( $text );
	
	// Sort terms by length (longest first) to avoid partial matches within already highlighted text
	usort( $search_terms, function( $a, $b ) {
		return strlen( trim( $b ) ) - strlen( trim( $a ) );
	} );
	
	// Collect all words to highlight (with their positions) before making any replacements
	$words_to_highlight = array();
	$highlighted_ranges = array(); // Track ranges to avoid overlapping highlights
	
	foreach ( $search_terms as $term ) {
		$term_trimmed = trim( $term );
		if ( strlen( $term_trimmed ) < 3 ) {
			continue;
		}
		
		// Find all matches of this term in plain text
		$pattern = '/' . preg_quote( $term_trimmed, '/' ) . '/iu';
		$matches = array();
		preg_match_all( $pattern, $plain_text, $matches, PREG_OFFSET_CAPTURE );
		
		if ( empty( $matches[0] ) ) {
			continue;
		}
		
		foreach ( $matches[0] as $match ) {
			$match_text = $match[0];
			$match_pos = $match[1];
			
			// Check if this position is already within a highlighted range
			$already_highlighted = false;
			foreach ( $highlighted_ranges as $range ) {
				if ( $match_pos >= $range['start'] && $match_pos < $range['end'] ) {
					$already_highlighted = true;
					break;
				}
			}
			
			if ( $already_highlighted ) {
				continue;
			}
			
			// Find word boundaries in plain text
			$word_start = $match_pos;
			while ( $word_start > 0 ) {
				$char = mb_substr( $plain_text, $word_start - 1, 1, 'UTF-8' );
				if ( ! preg_match( '/[\w\']/', $char ) ) {
					break;
				}
				$word_start--;
			}
			
			$match_end = $match_pos + mb_strlen( $match_text, 'UTF-8' );
			$word_end = $match_end;
			while ( $word_end < mb_strlen( $plain_text, 'UTF-8' ) ) {
				$char = mb_substr( $plain_text, $word_end, 1, 'UTF-8' );
				if ( ! preg_match( '/[\w\']/', $char ) ) {
					break;
				}
				$word_end++;
			}
			
			// Extract the full word
			$full_word = mb_substr( $plain_text, $word_start, $word_end - $word_start, 'UTF-8' );
			
			if ( empty( $full_word ) ) {
				continue;
			}
			
			// Store this word to highlight (process from end to start)
			$words_to_highlight[] = array(
				'word' => $full_word,
				'start' => $word_start,
				'end' => $word_end
			);
			
			// Track this highlighted range
			$highlighted_ranges[] = array(
				'start' => $word_start,
				'end' => $word_end
			);
		}
	}
	
	// Sort words to highlight by position (end to start) to avoid position shifting
	usort( $words_to_highlight, function( $a, $b ) {
		return $b['start'] - $a['start'];
	} );
	
	// Apply all highlights from end to start
	foreach ( $words_to_highlight as $word_data ) {
		$before = mb_substr( $plain_text, 0, $word_data['start'], 'UTF-8' );
		$after = mb_substr( $plain_text, $word_data['end'], null, 'UTF-8' );
		$plain_text = $before . '<span class="search-term-highlight">' . $word_data['word'] . '</span>' . $after;
	}
	
	return $plain_text;
}

/**
 * Generate a search snippet with highlighted search terms
 * 
 * Creates a contextual snippet from post content or lyrics that contains
 * the search terms, with the terms highlighted.
 * 
 * @param int|null $post_id Optional. Post ID. Defaults to current post.
 * @return string HTML snippet with highlighted search terms
 */
function jww_get_search_snippet( $post_id = null ) {

	if ( ! $post_id ) {
		return '';
	}
	
	// Get the search query - try multiple methods
	$search_query = get_search_query();
	if ( empty( $search_query ) ) {
		// Fallback to GET parameter
		$search_query = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
	}
	
	if ( empty( $search_query ) ) {
		// If no search query, return regular excerpt
		return get_the_excerpt( $post_id );
	}
	
	// Get post content based on post type and where the search match is
	$content = '';
	$post_type = get_post_type( $post_id );
	
	// Get search terms for matching
	$search_terms_for_matching = explode( ' ', trim( $search_query ) );
	$search_terms_for_matching = array_filter( $search_terms_for_matching, function( $term ) {
		return strlen( trim( $term ) ) > 2;
	} );
	
	if ( 'song' === $post_type ) {
		// For songs, check multiple fields in priority order
		// Priority 1: Lyrics field
		$lyrics = get_field( 'lyrics', $post_id );
		if ( ! empty( $lyrics ) ) {
			$lyrics_text = wp_strip_all_tags( $lyrics );
			$lyrics_lower = mb_strtolower( $lyrics_text, 'UTF-8' );
			
			// Check if any search term matches in lyrics
			$found_in_lyrics = false;
			foreach ( $search_terms_for_matching as $term ) {
				if ( mb_strpos( $lyrics_lower, mb_strtolower( trim( $term ), 'UTF-8' ) ) !== false ) {
					$found_in_lyrics = true;
					break;
				}
			}
			
			if ( $found_in_lyrics ) {
				$content = $lyrics_text;
			}
		}
		
		// Priority 2: Post content (if not found in lyrics)
		if ( empty( $content ) ) {
			$post_obj = get_post( $post_id );
			if ( $post_obj && ! empty( $post_obj->post_content ) ) {
				$post_content = $post_obj->post_content;
				
				// Process blocks if this is block content
				if ( has_blocks( $post_content ) ) {
					$post_content = do_blocks( $post_content );
				}
				
				// Strip all HTML tags and normalize
				$post_content = wp_strip_all_tags( $post_content );
				$post_content = preg_replace( '/<!--.*?-->/s', '', $post_content );
				$post_content = preg_replace( '/\s+/', ' ', $post_content );
				$post_content = trim( $post_content );
				
				$post_content_lower = mb_strtolower( $post_content, 'UTF-8' );
				
				// Check if any search term matches in post content
				$found_in_content = false;
				foreach ( $search_terms_for_matching as $term ) {
					if ( mb_strpos( $post_content_lower, mb_strtolower( trim( $term ), 'UTF-8' ) ) !== false ) {
						$found_in_content = true;
						break;
					}
				}
				
				if ( $found_in_content ) {
					$content = $post_content;
				}
			}
		}
		
		// Priority 3: Lyric annotations (if not found in lyrics or content)
		if ( empty( $content ) ) {
			$lyric_annotations = get_field( 'lyric_annotations', $post_id );
			if ( ! empty( $lyric_annotations ) ) {
				$annotations_text = wp_strip_all_tags( $lyric_annotations );
				$annotations_lower = mb_strtolower( $annotations_text, 'UTF-8' );
				
				// Check if any search term matches in annotations
				$found_in_annotations = false;
				foreach ( $search_terms_for_matching as $term ) {
					if ( mb_strpos( $annotations_lower, mb_strtolower( trim( $term ), 'UTF-8' ) ) !== false ) {
						$found_in_annotations = true;
						break;
					}
				}
				
				if ( $found_in_annotations ) {
					$content = $annotations_text;
				}
			}
		}
		
		// Fallback: if no match found in any field, use lyrics if available
		if ( empty( $content ) && ! empty( $lyrics ) ) {
			$content = wp_strip_all_tags( $lyrics );
		}
	}
	
	// For non-song post types, use post content
	if ( empty( $content ) ) {
		$post_obj = get_post( $post_id );
		if ( $post_obj ) {
			// For block themes, process blocks first to get clean content
			$post_content = $post_obj->post_content;
			
			// Process blocks if this is block content
			if ( has_blocks( $post_content ) ) {
				$post_content = do_blocks( $post_content );
			}
			
			// Strip all HTML tags and normalize whitespace
			$content = wp_strip_all_tags( $post_content );
			
			// Remove any remaining HTML comments or block markup artifacts
			$content = preg_replace( '/<!--.*?-->/s', '', $content );
			
			// Normalize whitespace (multiple spaces/newlines to single space)
			$content = preg_replace( '/\s+/', ' ', $content );
			$content = trim( $content );
		}
	}
	
	if ( empty( $content ) ) {
		// If still no content, return regular excerpt
		return get_the_excerpt( $post_id );
	}
	
	// Split search query into individual terms
	$search_terms = explode( ' ', trim( $search_query ) );
	$search_terms = array_filter( $search_terms, function( $term ) {
		return strlen( trim( $term ) ) > 2; // Ignore very short terms
	} );
	
	if ( empty( $search_terms ) ) {
		return wp_trim_words( $content, 55, '...' );
	}
	
	// Extract snippet around the first match
	$snippet = jww_extract_snippet_around_match( $content, $search_terms, 150 );
	
	// If no snippet found, return trimmed content
	if ( $snippet === false ) {
		return wp_trim_words( $content, 55, '...' );
	}
	
	// Highlight search terms in the snippet
	$snippet = jww_highlight_search_terms_in_text( $snippet, $search_terms );
	
	// Return the snippet - span with style attribute should be preserved
	$allowed = wp_kses_allowed_html( 'post' );
	// $allowed['span']['style'] = true;
	$allowed['span']['class'] = true;
	
	return wp_kses( $snippet, $allowed );
}

/**
 * Allow span with style attribute in shortcode output
 * 
 * Ensures span tags with style attributes are preserved when shortcodes are processed
 * 
 * @param array $tags Allowed HTML tags
 * @return array Modified allowed tags
 */
function jww_allow_span_style_in_shortcodes( $tags ) {
	if ( ! isset( $tags['span'] ) ) {
		$tags['span'] = array();
	}
	$tags['span']['style'] = true;
	return $tags;
}
add_filter( 'wp_kses_allowed_html', 'jww_allow_span_style_in_shortcodes', 10, 1 );

/**
 * Shortcode for displaying search snippet with highlighted terms
 * Usage: [search_snippet] or [search_snippet post_id="123"]
 * 
 * @param array $atts Shortcode attributes
 * @return string HTML output for search snippet
 */
function jww_search_snippet_shortcode( $atts = array() ) {
	// Parse attributes
	$atts = shortcode_atts( array(
		'post_id' => null,
	), $atts, 'search_snippet' );
	
	// Get post ID from attribute or current context
	$post_id = ! empty( $atts['post_id'] ) ? intval( $atts['post_id'] ) : null;
	
	// If no post_id provided, get it from current context
	// In block templates, get_the_ID() should work when inside post-template blocks
	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}
	
	// Get the snippet with the determined post ID
	$snippet = jww_get_search_snippet( $post_id );
	
	// Return the snippet - span with inline style should be preserved
	return $snippet;
}
add_shortcode( 'search_snippet', 'jww_search_snippet_shortcode' );

/**
 * Filter to process search_snippet shortcode with proper post context
 * 
 * This ensures the shortcode gets the correct post ID when rendered
 * in block templates by hooking into the block rendering process
 */
function jww_process_search_snippet_in_blocks( $block_content, $block ) {
	// Only process HTML blocks that contain our shortcode
	if ( isset( $block['blockName'] ) && $block['blockName'] === 'core/html' ) {
		if ( strpos( $block_content, '[search_snippet]' ) !== false ) {
			// The post context should be set by now in post-template blocks
			$post_id = get_the_ID();
			if ( $post_id ) {
				// Replace shortcode with actual snippet
				$snippet = jww_get_search_snippet( $post_id );
				$block_content = str_replace( '[search_snippet]', $snippet, $block_content );
			}
		}
	}
	return $block_content;
}
add_filter( 'render_block', 'jww_process_search_snippet_in_blocks', 10, 2 );

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
	// Get the primary menu location or first available menu
	$menu_locations = get_nav_menu_locations();
	$menu_location_name = null;
	$menu_id = null;
	
	// Try to get primary menu location first
	if ( ! empty( $menu_locations['primary'] ) ) {
		$menu_location_name = 'primary';
		$menu_id = $menu_locations['primary'];
	} elseif ( ! empty( $menu_locations ) ) {
		// Fallback to first available menu location
		$menu_location_name = array_key_first( $menu_locations );
		$menu_id = $menu_locations[ $menu_location_name ];
	} else {
		// Fallback: get first menu from menus
		$menus = wp_get_nav_menus();
		if ( ! empty( $menus ) ) {
			$menu_id = $menus[0]->term_id;
		}
	}
	
	// Build navigation block attributes
	$base_attrs = array(
		'icon' => 'menu',
		'overlayBackgroundColor' => 'base',
		'overlayTextColor' => 'contrast',
		'className' => 'header-nav-site',
		'style' => array(
			'spacing' => array(
				'blockGap' => jww_is_home_page() ? 'var:preset|spacing|30' : 'var:preset|spacing|40'
			)
		),
		'fontSize' => 'medium',
		'layout' => array(
			'type' => 'flex',
			'justifyContent' => 'right',
			'orientation' => jww_is_home_page() ? 'vertical' : 'horizontal',
			'flexWrap' => 'nowrap'
		)
	);
	
	// Add menu reference - use menuId for menu term ID, or menuLocation for location name
	if ( $menu_id ) {
		$base_attrs['menuId'] = $menu_id;
	}
	if ( $menu_location_name ) {
		$base_attrs['menuLocation'] = $menu_location_name;
	}
	
	// Convert to JSON for block markup (unescaped slashes for proper JSON)
	$attrs_json = wp_json_encode( $base_attrs, JSON_UNESCAPED_SLASHES );
	$navigation = '<!-- wp:navigation ' . $attrs_json . ' /-->';
	
	$output = do_blocks( $navigation );
	
	// Ensure navigation block scripts are loaded
	// This is critical for mobile menu functionality
	if ( ! wp_script_is( 'wp-block-navigation-view-script', 'enqueued' ) ) {
		wp_enqueue_script( 'wp-block-navigation-view-script' );
	}
	
	return $output;
}
add_shortcode('header_navigation', 'jww_header_navigation_shortcode');

/**
 * Add custom columns to the Songs admin table
 */
function jww_add_song_admin_columns( $columns ) {
	// Insert Artist column after title
	$new_columns = array();
	foreach ( $columns as $key => $value ) {
		$new_columns[ $key ] = $value;
		if ( $key === 'title' ) {
			$new_columns['artist'] = 'Artist';
			$new_columns['album'] = 'Album';
		}
	}
	return $new_columns;
}
add_filter( 'manage_song_posts_columns', 'jww_add_song_admin_columns' );

/**
 * Populate custom columns in the Songs admin table
 */
function jww_populate_song_admin_columns( $column, $post_id ) {
	switch ( $column ) {
		case 'artist':
			$artist = get_field( 'artist', $post_id );
			if ( $artist ) {
				// Artist field returns object(s), handle both single and array
				if ( is_array( $artist ) ) {
					$artist_names = array();
					foreach ( $artist as $artist_obj ) {
						if ( is_object( $artist_obj ) && isset( $artist_obj->ID ) ) {
							$artist_names[] = '<a href="' . esc_url( get_edit_post_link( $artist_obj->ID ) ) . '">' . esc_html( get_the_title( $artist_obj->ID ) ) . '</a>';
						} elseif ( is_numeric( $artist_obj ) ) {
							$artist_names[] = '<a href="' . esc_url( get_edit_post_link( $artist_obj ) ) . '">' . esc_html( get_the_title( $artist_obj ) ) . '</a>';
						}
					}
					echo implode( ', ', $artist_names );
				} elseif ( is_object( $artist ) && isset( $artist->ID ) ) {
					echo '<a href="' . esc_url( get_edit_post_link( $artist->ID ) ) . '">' . esc_html( get_the_title( $artist->ID ) ) . '</a>';
				} elseif ( is_numeric( $artist ) ) {
					echo '<a href="' . esc_url( get_edit_post_link( $artist ) ) . '">' . esc_html( get_the_title( $artist ) ) . '</a>';
				}
			} else {
				echo '<span style="color: #999;">—</span>';
			}
			break;

		case 'album':
			$album_id = get_field( 'album', $post_id );
			if ( $album_id ) {
				// Album field returns ID(s), handle both single and array
				if ( is_array( $album_id ) ) {
					$album_names = array();
					foreach ( $album_id as $album ) {
						$album_post_id = is_numeric( $album ) ? $album : ( is_object( $album ) && isset( $album->ID ) ? $album->ID : null );
						if ( $album_post_id ) {
							$album_names[] = '<a href="' . esc_url( get_edit_post_link( $album_post_id ) ) . '">' . esc_html( get_the_title( $album_post_id ) ) . '</a>';
						}
					}
					echo implode( ', ', $album_names );
				} else {
					$album_post_id = is_numeric( $album_id ) ? $album_id : ( is_object( $album_id ) && isset( $album_id->ID ) ? $album_id->ID : null );
					if ( $album_post_id ) {
						echo '<a href="' . esc_url( get_edit_post_link( $album_post_id ) ) . '">' . esc_html( get_the_title( $album_post_id ) ) . '</a>';
					}
				}
			} else {
				echo '<span style="color: #999;">—</span>';
			}
			break;
	}
}
add_action( 'manage_song_posts_custom_column', 'jww_populate_song_admin_columns', 10, 2 );

/**
 * Make Artist and Album columns sortable
 */
function jww_make_song_columns_sortable( $columns ) {
	$columns['artist'] = 'artist';
	$columns['album'] = 'album';
	return $columns;
}
add_filter( 'manage_edit-song_sortable_columns', 'jww_make_song_columns_sortable' );

/**
 * Handle sorting for Artist and Album columns
 */
function jww_sort_songs_by_artist_album( $query ) {
	global $pagenow, $wpdb;
	
	// Only apply on admin edit screen for song post type
	if ( ! is_admin() || $pagenow !== 'edit.php' || ! isset( $_GET['post_type'] ) || $_GET['post_type'] !== 'song' ) {
		return;
	}
	
	// Check if we're sorting by artist or album
	$orderby = isset( $_GET['orderby'] ) ? $_GET['orderby'] : '';
	
	if ( $orderby === 'artist' || $orderby === 'album' ) {
		// Use posts_clauses to add custom JOIN and ORDER BY
		add_filter( 'posts_clauses', 'jww_sort_songs_by_related_title', 10, 2 );
	}
}
add_action( 'pre_get_posts', 'jww_sort_songs_by_artist_album' );

/**
 * Custom sorting by related post title using SQL JOIN
 */
function jww_sort_songs_by_related_title( $clauses, $query ) {
	global $wpdb;
	
	$orderby = isset( $_GET['orderby'] ) ? $_GET['orderby'] : '';
	$order = isset( $_GET['order'] ) ? strtoupper( $_GET['order'] ) : 'ASC';
	
	if ( $orderby === 'artist' ) {
		// Join postmeta to get artist field, then join posts to get artist title
		// ACF stores relationship fields as serialized arrays, so we search for the ID in quotes
		$field_key = 'field_6900d8748ad9f';
		$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS artist_meta ON (
			artist_meta.post_id = {$wpdb->posts}.ID 
			AND artist_meta.meta_key = '{$field_key}'
		)";
		$clauses['join'] .= " LEFT JOIN {$wpdb->posts} AS artist_posts ON (
			artist_meta.meta_value LIKE CONCAT('\"', artist_posts.ID, '\"')
		)";
		$clauses['orderby'] = "COALESCE(artist_posts.post_title, '') " . $order;
		$clauses['groupby'] = "{$wpdb->posts}.ID";
	} elseif ( $orderby === 'album' ) {
		// Join postmeta to get album field, then join posts to get album title
		$field_key = 'field_68cace791977a';
		$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS album_meta ON (
			album_meta.post_id = {$wpdb->posts}.ID 
			AND album_meta.meta_key = '{$field_key}'
		)";
		$clauses['join'] .= " LEFT JOIN {$wpdb->posts} AS album_posts ON (
			album_meta.meta_value LIKE CONCAT('\"', album_posts.ID, '\"')
		)";
		$clauses['orderby'] = "COALESCE(album_posts.post_title, '') " . $order;
		$clauses['groupby'] = "{$wpdb->posts}.ID";
	}
	
	// Remove this filter after use to avoid affecting other queries
	remove_filter( 'posts_clauses', 'jww_sort_songs_by_related_title', 10 );
	
	return $clauses;
}


