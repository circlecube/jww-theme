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
