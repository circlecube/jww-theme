<?php
/**
 * SEO, Meta Tags, Open Graph, and Title Functions
 * 
 * @package JWW_Theme
 * @subpackage Includes
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
				
			case 'show':
				$location_terms = wp_get_post_terms( $post->ID, 'location' );
				$tour_terms = wp_get_post_terms( $post->ID, 'tour' );
				
				$location_string = '';
				if ( ! empty( $location_terms ) && ! is_wp_error( $location_terms ) ) {
					$location_names = array();
					foreach ( $location_terms as $term ) {
						$location_names[] = $term->name;
					}
					$location_string = implode( ', ', $location_names );
				}
				
				$tour_string = '';
				if ( ! empty( $tour_terms ) && ! is_wp_error( $tour_terms ) ) {
					$tour_string = $tour_terms[0]->name;
				}
				
				$date_string = get_the_date( 'F j, Y', $post->ID );
				
				if ( $date_string && $location_string ) {
					$description = sprintf(
						'Jesse Welles live show on %s at %s%s on Jesse Welles World.',
						$date_string,
						$location_string,
						$tour_string ? ' - ' . $tour_string : ''
					);
				} else {
					$description = sprintf(
						'%s - Live show on Jesse Welles World.',
						get_the_title()
					);
				}
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
		} elseif ( is_post_type_archive( 'show' ) ) {
			$description = 'Browse all live shows, setlists, and tour dates from Jesse Welles World.';
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
	} elseif ( isset( $post_type ) && $post_type === 'show' ) {
		$location_terms = wp_get_post_terms( $post->ID, 'location' );
		$location_string = '';
		if ( ! empty( $location_terms ) && ! is_wp_error( $location_terms ) ) {
			$location_names = array();
			foreach ( $location_terms as $term ) {
				$location_names[] = $term->name;
			}
			$location_string = implode( ', ', $location_names );
		}
		$date_string = get_the_date( 'F j, Y', $post->ID );
		$title = 'Jesse Welles' . ( $date_string ? ' - ' . $date_string : '' ) . ( $location_string ? ' at ' . $location_string : '' );
		$artist_name = 'Jesse Welles';
	} else {
		$artist_name = 'Jesse Welles';
		$title = $artist_name . ' - ' . $post_type . ' - ' . $og_title;
	}
	echo '<meta property="og:title" content="' . esc_attr( $title ) . '" />' . "\n";
	echo '<meta property="og:url" content="' . esc_url( $og_url ) . '" />' . "\n";
	echo '<meta property="og:logo" content="' . esc_url( $default_image ) . '" />' . "\n";

	switch ($post_type) {
		case 'song':
			$album_name = '';
			$album = get_field( 'album', $post->ID );
			if ( $album ) {
				$album_id = is_array( $album ) ? ( $album[0] ?? 0 ) : $album;
				if ( $album_id ) {
					$album_name = get_the_title( $album_id );
				}
			}
			echo '<meta property="og:type" content="music.song" />' . "\n";
			echo '<meta property="og:music:musician" content="' . esc_attr( $artist_name ) . '" />' . "\n";
			if ( $album_name !== '' ) {
				echo '<meta property="og:music:album" content="' . esc_attr( $album_name ) . '" />' . "\n";
			}
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
		case 'show':
			$location_terms = wp_get_post_terms( $post->ID, 'location' );
			$tour_terms = wp_get_post_terms( $post->ID, 'tour' );
			
			$location_string = '';
			if ( ! empty( $location_terms ) && ! is_wp_error( $location_terms ) ) {
				$location_names = array();
				foreach ( $location_terms as $term ) {
					$location_names[] = $term->name;
				}
				$location_string = implode( ', ', $location_names );
			}
			
			$tour_string = '';
			if ( ! empty( $tour_terms ) && ! is_wp_error( $tour_terms ) ) {
				$tour_string = $tour_terms[0]->name;
			}
			
			$date_string = get_the_date( 'F j, Y', $post->ID );
			
			$show_description = 'Jesse Welles live show';
			if ( $date_string ) {
				$show_description .= ' on ' . $date_string;
			}
			if ( $location_string ) {
				$show_description .= ' at ' . $location_string;
			}
			if ( $tour_string ) {
				$show_description .= ' - ' . $tour_string;
			}
			
			echo '<meta property="og:type" content="event" />' . "\n";
			echo '<meta property="og:description" content="' . esc_attr( $show_description ) . '" />' . "\n";
			if ( $date_string ) {
				echo '<meta property="og:event:start_time" content="' . esc_attr( get_the_date( 'c', $post->ID ) ) . '" />' . "\n";
			}
			echo '<meta property="og:image:alt" content="' . esc_attr( $show_description ) . '" />' . "\n";
			break;
		default: // post, page, etc.
			echo '<meta property="og:type" content="article" />' . "\n";
			echo '<meta property="og:description" content="' . esc_attr( $og_description ) . '" />' . "\n";
			echo '<meta property="og:image:alt" content="' . esc_attr( $og_description ) . '" />' . "\n";
			break;
	}
}
add_action( 'wp_head', 'jww_open_graph_tags', 5 );
