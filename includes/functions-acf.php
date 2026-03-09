<?php
/**
 * ACF (Advanced Custom Fields) Related Functions
 * 
 * @package JWW_Theme
 * @subpackage Includes
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add ACF Options Page for YouTube Import Settings and Setlist.fm API
 * Must be called on acf/init hook to avoid translation loading issues
 */
function jww_add_acf_options_page() {
	if( function_exists('acf_add_options_page') ) {
		acf_add_options_page(array(
			'page_title'    => 'YouTube Import Settings',
			'menu_title'    => 'YouTube Import',
			'menu_slug'     => 'youtube-import-settings',
			'capability'    => 'manage_options',
			'parent_slug'   => 'edit.php?post_type=song', // Add under Song post type menu
		));
		
		// Add setlist.fm API key to existing options page or create sub-page
		// The API key field will be added via ACF field group
	}
}
add_action( 'acf/init', 'jww_add_acf_options_page' );

/**
 * Auto-generate show title from location (city) and date
 * 
 * Format: {City} - {Month DD, YYYY}
 * Example: "Sydney - January 25, 2026"
 * 
 * This runs when a show is saved and will auto-generate the title
 * if it's empty or matches the auto-generated pattern.
 */
function jww_auto_generate_show_title( $post_id ) {
	// Only run for show post type
	if ( get_post_type( $post_id ) !== 'show' ) {
		return;
	}
	
	// Skip autosaves and revisions
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	
	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}
	
	// Get location taxonomy term
	$location_id = get_field( 'show_location', $post_id );
	if ( ! $location_id ) {
		return;
	}
	
	$location_term = get_term( $location_id, 'location' );
	if ( ! $location_term || is_wp_error( $location_term ) ) {
		return;
	}
	
	// Find the city in the location hierarchy (middle level)
	// Location hierarchy: Country (top) > City (middle) > Venue (bottom)
	$city_name = '';
	$current_term = $location_term;
	
	// Walk up the hierarchy to find the city
	// Strategy: If current has parent, check if parent has parent
	// - If parent has parent: parent is city, grandparent is country
	// - If parent has no parent: parent might be city or country
	// - If current has no parent: current might be city or country
	
	if ( $current_term->parent ) {
		// Current term has a parent
		$parent_term = get_term( $current_term->parent, 'location' );
		if ( $parent_term && ! is_wp_error( $parent_term ) ) {
			if ( $parent_term->parent ) {
				// Parent has a parent, so hierarchy is: grandparent (country) > parent (city) > current (venue)
				$city_name = $parent_term->name;
			} else {
				// Parent has no parent
				// Check if parent has children - if multiple children, parent is likely country
				$siblings = get_terms( array(
					'taxonomy' => 'location',
					'parent'   => $parent_term->term_id,
					'hide_empty' => false,
				) );
				if ( ! empty( $siblings ) && ! is_wp_error( $siblings ) && count( $siblings ) > 1 ) {
					// Parent has multiple children, so parent is country, current is city
					$city_name = $current_term->name;
				} else {
					// Parent is likely city (or only has one child)
					$city_name = $parent_term->name;
				}
			}
		}
	} else {
		// Current term has no parent - it's top level
		// Check if it has children (cities)
		$children = get_terms( array(
			'taxonomy' => 'location',
			'parent'   => $current_term->term_id,
			'hide_empty' => false,
		) );
		if ( ! empty( $children ) && ! is_wp_error( $children ) ) {
			// Has children - this is a country, use first child as city
			$city_name = $children[0]->name;
		} else {
			// No children - might be a city itself (if hierarchy isn't fully set up)
			$city_name = $current_term->name;
		}
	}
	
	// Fallback: use location term name if we still don't have a city
	if ( empty( $city_name ) ) {
		$city_name = $location_term->name;
	}
	
	// Get the post date
	$post_date = get_post_field( 'post_date', $post_id );
	if ( ! $post_date ) {
		return;
	}
	
	// Format date as "Month DD, YYYY"
	$formatted_date = date_i18n( 'F j, Y', strtotime( $post_date ) );
	
	// Generate title
	$new_title = $city_name . ' - ' . $formatted_date;
	
	// Get current title
	$current_title = get_the_title( $post_id );
	
	// Only update if title is empty, matches auto-generated pattern, or is "Auto Draft"
	$should_update = false;
	if ( empty( $current_title ) || $current_title === 'Auto Draft' ) {
		$should_update = true;
	} else {
		// Check if current title matches the auto-generated pattern
		// Pattern: "{City} - {Month DD, YYYY}" or similar variations
		$pattern = '/^' . preg_quote( $city_name, '/' ) . '\s*-\s*.+$/';
		if ( preg_match( $pattern, $current_title ) ) {
			$should_update = true;
		}
	}
	
	if ( $should_update ) {
		// Remove the hook to prevent infinite loop
		remove_action( 'save_post', 'jww_auto_generate_show_title' );
		
		// Update the post title
		$update_result = wp_update_post( array(
			'ID'         => $post_id,
			'post_title' => $new_title,
		), true );
		
		// Re-add the hook
		add_action( 'save_post', 'jww_auto_generate_show_title' );
		
		// Song stats cache is cleared by jww_clear_song_stats_on_show_save (save_post priority 20) for shows.

		if ( ! is_wp_error( $update_result ) ) {
			// Clear location hierarchy cache if location changed
			delete_transient( 'jww_archive_locations' );
			delete_transient( 'jww_archive_tours' );
		}
	}
}
add_action( 'save_post', 'jww_auto_generate_show_title', 10, 1 );

/**
 * Clear location/tour term caches when terms are updated
 */
function jww_clear_location_tour_caches( $term_id, $tt_id, $taxonomy ) {
	if ( $taxonomy === 'location' || $taxonomy === 'tour' ) {
		delete_transient( 'jww_archive_locations' );
		delete_transient( 'jww_archive_tours' );
		
		// Clear location hierarchy cache for this specific location
		if ( $taxonomy === 'location' ) {
			delete_transient( 'jww_location_hierarchy_' . $term_id );
		}
	}
}
add_action( 'edited_term', 'jww_clear_location_tour_caches', 10, 3 );
add_action( 'created_term', 'jww_clear_location_tour_caches', 10, 3 );
add_action( 'delete_term', 'jww_clear_location_tour_caches', 10, 3 );

/**
 * Get the explicit location type for a location term (from ACF or term meta).
 *
 * @param int $term_id Location term ID.
 * @return string One of 'country', 'state_province', 'city', 'venue', or '' if not set.
 */
function jww_get_location_type( $term_id ) {
	$term_id = (int) $term_id;
	if ( $term_id <= 0 ) {
		return '';
	}
	if ( function_exists( 'get_field' ) ) {
		$type = get_field( 'location_type', 'location_' . $term_id );
	} else {
		$type = get_term_meta( $term_id, 'location_type', true );
	}
	return in_array( $type, array( 'country', 'state_province', 'city', 'venue' ), true ) ? $type : '';
}

/**
 * Whether a country term uses a state/province level (from ACF or term meta).
 * Falls back to checking if the country has any child with location_type = state_province.
 *
 * @param int $country_term_id Top-level location (country) term ID.
 * @return bool
 */
function jww_country_has_states( $country_term_id ) {
	$country_term_id = (int) $country_term_id;
	if ( $country_term_id <= 0 ) {
		return false;
	}
	if ( function_exists( 'get_field' ) ) {
		$has = get_field( 'country_has_states', 'location_' . $country_term_id );
		if ( $has === true || $has === '1' || $has === 1 ) {
			return true;
		}
		if ( $has === false || $has === '0' || $has === 0 || $has === '' ) {
			// Explicit false/empty: still check for state_province children
		}
	} else {
		$has = get_term_meta( $country_term_id, 'country_has_states', true );
		if ( $has === true || $has === '1' || $has === 1 ) {
			return true;
		}
	}
	$children = get_terms( array(
		'taxonomy'   => 'location',
		'parent'     => $country_term_id,
		'hide_empty' => false,
		'fields'     => 'ids',
	) );
	if ( is_wp_error( $children ) || empty( $children ) ) {
		return false;
	}
	foreach ( $children as $child_id ) {
		if ( jww_get_location_type( $child_id ) === 'state_province' ) {
			return true;
		}
	}
	return false;
}

/**
 * Get country code (e.g. US, CA) for a country term. Used by setlist importer for API matching.
 *
 * @param int $country_term_id Country term ID.
 * @return string Empty if not set.
 */
function jww_get_country_code( $country_term_id ) {
	$country_term_id = (int) $country_term_id;
	if ( $country_term_id <= 0 ) {
		return '';
	}
	if ( function_exists( 'get_field' ) ) {
		$code = get_field( 'country_code', 'location_' . $country_term_id );
	} else {
		$code = get_term_meta( $country_term_id, 'country_code', true );
	}
	return is_string( $code ) ? trim( $code ) : '';
}

/**
 * Get list of country codes for countries that use state/province level (for setlist importer).
 * Uses ACF country_has_states + country_code when set; falls back to constant if none configured.
 *
 * @return array Uppercase country codes, e.g. array( 'US', 'CA', 'AU' ).
 */
function jww_get_countries_with_state_level_codes() {
	$countries = get_terms( array(
		'taxonomy'   => 'location',
		'parent'     => 0,
		'hide_empty' => false,
		'fields'     => 'ids',
	) );
	if ( is_wp_error( $countries ) || empty( $countries ) ) {
		return array( 'US', 'CA', 'AU' ); // Fallback
	}
	$codes = array();
	foreach ( $countries as $term_id ) {
		if ( ! jww_country_has_states( $term_id ) ) {
			continue;
		}
		$code = jww_get_country_code( $term_id );
		if ( $code !== '' ) {
			$codes[] = strtoupper( $code );
		}
	}
	if ( empty( $codes ) ) {
		return array( 'US', 'CA', 'AU' ); // Fallback when no ACF data yet
	}
	return array_values( array_unique( $codes ) );
}

/**
 * Get random lyrics data for reuse across templates, block, and REST API.
 *
 * @return array|null Associative array with song_id, song_title, song_link, lyrics_line, artist_name, featured_image_url; null on failure
 */
function jww_get_random_lyrics_data() {
	if ( ! function_exists( 'get_field' ) ) {
		return null;
	}
	if ( ! post_type_exists( 'song' ) ) {
		return null;
	}

	$songs = get_posts( array(
		'post_type'      => 'song',
		'posts_per_page' => 1,
		'orderby'        => 'rand',
		'meta_query'     => array(
			array( 'key' => 'lyrics', 'compare' => 'EXISTS' ),
			array( 'key' => 'lyrics', 'value' => '', 'compare' => '!=' ),
		),
		'tax_query'      => array(
			array(
				'taxonomy' => 'category',
				'field'    => 'slug',
				'terms'    => 'original',
			),
			array(
				'taxonomy' => 'post_tag',
				'field'    => 'slug',
				'terms'    => 'jesse-welles',
			),
		),
		'fields'         => 'ids',
	) );

	if ( empty( $songs ) ) {
		return null;
	}

	$song_id = (int) $songs[0];
	if ( $song_id <= 0 ) {
		return null;
	}
	$lyrics = get_field( 'lyrics', $song_id );
	if ( $lyrics === null || $lyrics === false || $lyrics === '' ) {
		return null;
	}
	$lyrics = is_string( $lyrics ) ? $lyrics : (string) $lyrics;
	$lyrics = wp_strip_all_tags( $lyrics );

	$lyrics_lines = array_filter(
		array_map( 'trim', explode( "\n", $lyrics ) ),
		function ( $line ) {
			return $line !== '' && strlen( $line ) > 10;
		}
	);
	$lyrics_lines = array_values( $lyrics_lines );
	if ( empty( $lyrics_lines ) ) {
		return null;
	}

	$lyrics_line = $lyrics_lines[ array_rand( $lyrics_lines ) ];
	$artist_name = 'Jesse Welles';
	$artist      = get_field( 'artist', $song_id );
	if ( ! empty( $artist ) ) {
		$artist_id = null;
		if ( is_array( $artist ) && isset( $artist[0] ) ) {
			$first     = $artist[0];
			$artist_id = is_object( $first ) && isset( $first->ID ) ? (int) $first->ID : ( is_numeric( $first ) ? (int) $first : null );
		} elseif ( is_object( $artist ) && isset( $artist->ID ) ) {
			$artist_id = (int) $artist->ID;
		} elseif ( is_numeric( $artist ) ) {
			$artist_id = (int) $artist;
		}
		if ( $artist_id ) {
			$artist_name = get_the_title( $artist_id );
			if ( $artist_name === '' ) {
				$artist_name = 'Jesse Welles';
			}
		}
	}

	$featured_image_url = null;
	$thumbnail_id       = get_post_thumbnail_id( $song_id );
	if ( $thumbnail_id ) {
		$featured_image_url = wp_get_attachment_image_url( $thumbnail_id, 'large' );
	}

	return array(
		'song_id'            => $song_id,
		'song_title'         => get_the_title( $song_id ),
		'song_link'          => get_permalink( $song_id ),
		'lyrics_line'        => $lyrics_line,
		'artist_name'        => $artist_name,
		'featured_image_url' => $featured_image_url ? $featured_image_url : null,
	);
}

/**
 * Get lyrics lines for a specific song (for social share "share a lyric" selector).
 * Same filtering as jww_get_random_lyrics_data: strip tags, split by newline, trim, exclude empty and very short lines.
 *
 * @param int $song_id Song post ID.
 * @return array List of lyric lines (strings). Empty if no lyrics or song not found.
 */
function jww_social_get_lyrics_lines_for_song( $song_id ) {
	if ( ! function_exists( 'get_field' ) ) {
		return array();
	}
	$song_id = (int) $song_id;
	if ( $song_id <= 0 || get_post_type( $song_id ) !== 'song' ) {
		return array();
	}
	$lyrics = get_field( 'lyrics', $song_id );
	if ( $lyrics === null || $lyrics === false || $lyrics === '' ) {
		return array();
	}
	$lyrics = is_string( $lyrics ) ? $lyrics : (string) $lyrics;
	$lyrics = wp_strip_all_tags( $lyrics );
	$lines = array_filter(
		array_map( 'trim', explode( "\n", $lyrics ) ),
		function ( $line ) {
			return $line !== '' && strlen( $line ) > 10;
		}
	);
	return array_values( $lines );
}
