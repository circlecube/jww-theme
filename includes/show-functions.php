<?php
/**
 * Show and Song Statistics Helper Functions
 * 
 * @package JWW_Theme
 * @subpackage Includes
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clear song statistics caches for specific song IDs only (and global setlist/stats).
 * Use when a show's setlist is updated so only affected songs are invalidated.
 *
 * @param int[] $song_ids Song post IDs that appear in the setlist (before or after save). Pass empty to clear only global caches.
 */
function jww_clear_song_stats_for_song_ids( array $song_ids ) {
	delete_transient( 'jww_all_time_song_stats' );
	delete_transient( 'jww_all_show_setlists' );

	$song_ids = array_filter( array_map( 'intval', $song_ids ) );
	foreach ( $song_ids as $id ) {
		if ( $id > 0 ) {
			delete_transient( 'jww_song_stats_' . $id );
			delete_transient( 'jww_song_performances_' . $id );
		}
	}
}

/**
 * Clear all song statistics caches (full clear for imports/bulk operations).
 */
function jww_clear_song_stats_caches() {
	delete_transient( 'jww_all_time_song_stats' );
	delete_transient( 'jww_all_show_setlists' );

	global $wpdb;
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( '_transient_jww_song_stats_' ) . '%'
		)
	);
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( '_transient_timeout_jww_song_stats_' ) . '%'
		)
	);
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( '_transient_jww_song_performances_' ) . '%'
		)
	);
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( '_transient_timeout_jww_song_performances_' ) . '%'
		)
	);
}

/**
 * Get formatted setlist array for a show
 * 
 * @param int $show_id Show post ID
 * @return array Formatted setlist with song information
 */
function jww_get_show_setlist( $show_id ) {
	$setlist = get_field( 'setlist', $show_id );
	if ( ! $setlist || ! is_array( $setlist ) ) {
		return array();
	}
	
	$formatted = array();
	foreach ( $setlist as $item ) {
		$entry = array(
			'entry_type' => $item['entry_type'] ?? '',
			'notes'      => $item['notes'] ?? '',
			'original_artist' => '',
		);
		
		if ( $item['entry_type'] === 'song-post' && ! empty( $item['song'] ) ) {
			$song = is_array( $item['song'] ) ? $item['song'][0] : $item['song'];
			$song_id = is_object( $song ) ? $song->ID : $song;
			$entry['song_id'] = $song_id;
			$entry['song_title'] = get_the_title( $song_id );
			$entry['song_link'] = get_permalink( $song_id );
			
			// Check if this is a cover song and get original artist
			$song_terms = wp_get_post_terms( $song_id, 'category' );
			$is_cover = false;
			foreach ( $song_terms as $term ) {
				if ( $term->slug === 'cover' ) {
					$is_cover = true;
					break;
				}
			}
			
			if ( $is_cover ) {
				$attribution = get_field( 'attribution', $song_id );
				if ( $attribution ) {
					$entry['original_artist'] = $attribution;
				}
			}
		} elseif ( $item['entry_type'] === 'song-text' && ! empty( $item['song_text'] ) ) {
			$entry['song_title'] = $item['song_text'];
			$entry['song_id'] = null;
			$entry['song_link'] = null;
			
			// Extract cover artist from notes if present
			if ( $entry['notes'] && preg_match( '/^Cover:\s*(.+?)(?:\s*-\s*|$)/i', $entry['notes'], $matches ) ) {
				$entry['original_artist'] = $matches[1];
			}
		}
		
		$formatted[] = $entry;
	}
	
	return $formatted;
}

/**
 * Get all show setlists (cached for performance)
 * 
 * @param bool $use_cache Whether to use cached results
 * @return array Array of show data with setlists: [show_id => [setlist, post_date, ...]]
 */
function jww_get_all_show_setlists( $use_cache = true ) {
	static $cached_data = null;
	
	// Use static cache within same request
	if ( $cached_data !== null ) {
		return $cached_data;
	}
	
	// Try transient cache
	if ( $use_cache ) {
		$transient_key = 'jww_all_show_setlists';
		$cached = get_transient( $transient_key );
		if ( $cached !== false ) {
			$cached_data = $cached;
			return $cached_data;
		}
	}
	
	// Fetch all shows
	$args = array(
		'post_type'      => 'show',
		'posts_per_page' => -1,
		'post_status'    => array( 'publish', 'future' ),
		'fields'         => 'ids', // Only get IDs for better performance
	);
	
	$show_ids = get_posts( $args );
	
	// Prime meta cache for all shows at once (when available)
	if ( ! empty( $show_ids ) && function_exists( 'update_post_meta_cache' ) ) {
		update_post_meta_cache( $show_ids );
	}
	
	// Build data structure
	$all_data = array();
	foreach ( $show_ids as $show_id ) {
		$setlist = get_field( 'setlist', $show_id );
		if ( ! $setlist || ! is_array( $setlist ) ) {
			continue;
		}
		
		$post = get_post( $show_id );
		$all_data[ $show_id ] = array(
			'setlist'   => $setlist,
			'post_date' => $post->post_date,
			'post'      => $post,
		);
	}
	
	// Cache for 15 minutes
	if ( $use_cache ) {
		set_transient( 'jww_all_show_setlists', $all_data, 15 * MINUTE_IN_SECONDS );
	}
	
	$cached_data = $all_data;
	return $all_data;
}

/**
 * Get song statistics (cached per song)
 * 
 * @param int $song_id Song post ID
 * @param bool $use_cache Whether to use cached results
 * @return array Array with play_count, last_played, first_played, recent_shows
 */
function jww_get_song_stats( $song_id, $use_cache = true ) {
	// Try per-song cache first
	if ( $use_cache ) {
		$transient_key = 'jww_song_stats_' . $song_id;
		$cached = get_transient( $transient_key );
		if ( $cached !== false ) {
			return $cached;
		}
	}
	
	// Get all show data (uses its own cache)
	$all_shows = jww_get_all_show_setlists( $use_cache );
	
	$play_count = 0;
	$last_played = false;
	$first_played = false;
	$recent_shows = array();
	$shows_by_date = array();
	
	// Process all shows once
	foreach ( $all_shows as $show_id => $show_data ) {
		$setlist = $show_data['setlist'];
		$post_date = $show_data['post_date'];
		$post = $show_data['post'];
		
		$played_in_show = false;
		
		foreach ( $setlist as $item ) {
			if ( $item['entry_type'] === 'song-post' && ! empty( $item['song'] ) ) {
				$song = is_array( $item['song'] ) ? $item['song'][0] : $item['song'];
				$item_song_id = is_object( $song ) ? $song->ID : $song;
				
				if ( $item_song_id == $song_id ) {
					$play_count++;
					$played_in_show = true;
					break; // Found in this show, no need to check rest of setlist
				}
			}
		}
		
		if ( $played_in_show ) {
			$shows_by_date[] = array(
				'show_id'   => $show_id,
				'post_date' => $post_date,
				'post'      => $post,
			);
		}
	}
	
	// Sort by date
	usort( $shows_by_date, function( $a, $b ) {
		return strtotime( $a['post_date'] ) - strtotime( $b['post_date'] );
	} );
	
	// Get first and last
	if ( ! empty( $shows_by_date ) ) {
		$first = reset( $shows_by_date );
		$last = end( $shows_by_date );
		
		$first_loc_id = get_field( 'show_location', $first['show_id'] );
		$last_loc_id  = get_field( 'show_location', $last['show_id'] );
		
		$first_played = array(
			'show'        => $first['post'],
			'show_id'     => $first['show_id'],
			'show_date'   => get_the_date( 'F j, Y', $first['show_id'] ),
			'show_link'   => get_permalink( $first['show_id'] ),
			'location_id' => $first_loc_id ? (int) $first_loc_id : 0,
		);
		
		$last_played = array(
			'show'        => $last['post'],
			'show_id'     => $last['show_id'],
			'show_date'   => get_the_date( 'F j, Y', $last['show_id'] ),
			'show_link'   => get_permalink( $last['show_id'] ),
			'location_id' => $last_loc_id ? (int) $last_loc_id : 0,
		);
		
		// Get recent shows (last 10, most recent first)
		$recent_shows_data = array_slice( array_reverse( $shows_by_date ), 0, 10 );
		foreach ( $recent_shows_data as $show_data ) {
			$location_id = get_field( 'show_location', $show_data['show_id'] );
			$location_name = '';
			if ( $location_id ) {
				$location_term = get_term( $location_id, 'location' );
				if ( $location_term && ! is_wp_error( $location_term ) ) {
					$location_name = $location_term->name;
				}
			}
			
			$recent_shows[] = array(
				'show'          => $show_data['post'],
				'show_id'       => $show_data['show_id'],
				'show_title'    => get_the_title( $show_data['show_id'] ),
				'show_date'     => get_the_date( 'F j, Y', $show_data['show_id'] ),
				'show_link'     => get_permalink( $show_data['show_id'] ),
				'location_name' => $location_name,
			);
		}
	}
	
	$stats = array(
		'play_count'   => $play_count,
		'last_played'  => $last_played,
		'first_played' => $first_played,
		'recent_shows' => $recent_shows,
	);
	
	// Cache for 30 minutes
	if ( $use_cache ) {
		set_transient( 'jww_song_stats_' . $song_id, $stats, 30 * MINUTE_IN_SECONDS );
	}
	
	return $stats;
}

/**
 * Count how many times a song was played
 * 
 * @param int $song_id Song post ID
 * @param bool $use_cache Whether to use cached results
 * @return int Number of times played
 */
function jww_get_song_play_count( $song_id, $use_cache = true ) {
	$stats = jww_get_song_stats( $song_id, $use_cache );
	return $stats['play_count'];
}

/**
 * Get last show where a song was played
 * 
 * @param int $song_id Song post ID
 * @param bool $use_cache Whether to use cached results
 * @return array|false Show post object and date, or false if never played
 */
function jww_get_song_last_played( $song_id, $use_cache = true ) {
	$stats = jww_get_song_stats( $song_id, $use_cache );
	return $stats['last_played'] ? $stats['last_played'] : false;
}

/**
 * Get first show where a song was played
 * 
 * @param int $song_id Song post ID
 * @param bool $use_cache Whether to use cached results
 * @return array|false Show post object and date, or false if never played
 */
function jww_get_song_first_played( $song_id, $use_cache = true ) {
	$stats = jww_get_song_stats( $song_id, $use_cache );
	return $stats['first_played'] ? $stats['first_played'] : false;
}

/**
 * Get recent shows where a song was played
 * 
 * @param int $song_id Song post ID
 * @param int $limit Number of shows to return
 * @param bool $use_cache Whether to use cached results
 * @return array Array of show data
 */
function jww_get_song_recent_shows( $song_id, $limit = 10, $use_cache = true ) {
	$stats = jww_get_song_stats( $song_id, $use_cache );
	$recent_shows = $stats['recent_shows'];
	
	// Limit to requested number
	if ( count( $recent_shows ) > $limit ) {
		$recent_shows = array_slice( $recent_shows, 0, $limit );
	}
	
	return $recent_shows;
}

/**
 * Get upcoming shows
 * 
 * @param array $args Additional query arguments
 * @return array Array of show posts
 */
function jww_get_upcoming_shows( $args = array() ) {
	$defaults = array(
		'post_type'      => 'show',
		'posts_per_page' => -1,
		'post_status'    => array( 'publish', 'future' ),
		'orderby'        => 'date',
		'order'          => 'ASC',
		'date_query'     => array(
			array(
				'after' => current_time( 'mysql' ),
			),
		),
	);
	
	$query_args = wp_parse_args( $args, $defaults );
	return get_posts( $query_args );
}

/**
 * Get past shows
 * 
 * @param array $args Additional query arguments
 * @return array Array of show posts
 */
function jww_get_past_shows( $args = array() ) {
	$defaults = array(
		'post_type'      => 'show',
		'posts_per_page' => -1,
		'post_status'    => array( 'publish' ),
		'orderby'        => 'date',
		'order'          => 'DESC',
		'date_query'     => array(
			array(
				'before' => current_time( 'mysql' ),
			),
		),
	);
	
	$query_args = wp_parse_args( $args, $defaults );
	return get_posts( $query_args );
}

/**
 * Get shows for a specific tour
 * 
 * @param int $tour_id Tour term ID
 * @return array Array of show posts
 */
function jww_get_shows_by_tour( $tour_id ) {
	$args = array(
		'post_type'      => 'show',
		'posts_per_page' => -1,
		'post_status'    => array( 'publish', 'future' ),
		'orderby'        => 'date',
		'order'          => 'ASC',
		'tax_query'      => array(
			array(
				'taxonomy' => 'tour',
				'field'    => 'term_id',
				'terms'    => $tour_id,
			),
		),
	);
	
	return get_posts( $args );
}

/**
 * Get shows for a specific venue/location
 * 
 * @param int $venue_id Location term ID
 * @return array Array of show posts
 */
function jww_get_shows_by_venue( $venue_id ) {
	$args = array(
		'post_type'      => 'show',
		'posts_per_page' => -1,
		'post_status'    => array( 'publish', 'future' ),
		'orderby'        => 'date',
		'order'          => 'DESC',
		'tax_query'      => array(
			array(
				'taxonomy' => 'location',
				'field'    => 'term_id',
				'terms'    => $venue_id,
			),
		),
	);
	
	return get_posts( $args );
}

/**
 * Get all-time song statistics
 * 
 * @param bool $use_cache Whether to use cached results (default: true)
 * @return array Array of songs with play counts, sorted by count
 */
function jww_get_all_time_song_stats( $use_cache = true ) {
	// Try to get from cache first
	if ( $use_cache ) {
		$cached = get_transient( 'jww_all_time_song_stats' );
		if ( $cached !== false ) {
			return $cached;
		}
	}
	
	// Get all show data once (uses its own cache)
	$all_shows = jww_get_all_show_setlists( $use_cache );
	
	// Build song->shows mapping in one pass
	$song_shows = array(); // [song_id => [show_ids]]
	
	foreach ( $all_shows as $show_id => $show_data ) {
		$setlist = $show_data['setlist'];
		
		foreach ( $setlist as $item ) {
			if ( $item['entry_type'] === 'song-post' && ! empty( $item['song'] ) ) {
				$song = is_array( $item['song'] ) ? $item['song'][0] : $item['song'];
				$song_id = is_object( $song ) ? $song->ID : $song;
				
				if ( ! isset( $song_shows[ $song_id ] ) ) {
					$song_shows[ $song_id ] = array();
				}
				$song_shows[ $song_id ][] = array(
					'show_id'   => $show_id,
					'post_date' => $show_data['post_date'],
					'post'      => $show_data['post'],
				);
			}
		}
	}
	
	// Get all song IDs that were played
	$played_song_ids = array_keys( $song_shows );
	
	// Get song titles in batch
	$songs = get_posts( array(
		'post_type'      => 'song',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'post__in'       => $played_song_ids,
		'fields'         => 'ids',
	) );
	
	$stats = array();
	
	foreach ( $songs as $song_id ) {
		if ( ! isset( $song_shows[ $song_id ] ) ) {
			continue;
		}
		
		$shows_data = $song_shows[ $song_id ];
		$play_count = count( $shows_data );
		
		if ( $play_count > 0 ) {
			// Sort shows by date
			usort( $shows_data, function( $a, $b ) {
				return strtotime( $a['post_date'] ) - strtotime( $b['post_date'] );
			} );
			
			$first = reset( $shows_data );
			$last = end( $shows_data );
			
			$last_show_id = $last['show_id'];
			$first_show_id = $first['show_id'];
			$last_loc_id  = get_field( 'show_location', $last_show_id );
			$first_loc_id = get_field( 'show_location', $first_show_id );

			$last_played = array(
				'show'          => $last['post'],
				'show_id'       => $last_show_id,
				'show_date'     => get_the_date( 'F j, Y', $last_show_id ),
				'show_link'     => get_permalink( $last_show_id ),
				'location_id'   => $last_loc_id ? (int) $last_loc_id : 0,
				'location_data' => function_exists( 'jww_get_location_hierarchy' ) ? jww_get_location_hierarchy( $last_loc_id ? (int) $last_loc_id : 0 ) : array( 'city_country' => '', 'venue' => '', 'venue_link' => '' ),
			);

			$first_played = array(
				'show'          => $first['post'],
				'show_id'       => $first_show_id,
				'show_date'     => get_the_date( 'F j, Y', $first_show_id ),
				'show_link'     => get_permalink( $first_show_id ),
				'location_id'   => $first_loc_id ? (int) $first_loc_id : 0,
				'location_data' => function_exists( 'jww_get_location_hierarchy' ) ? jww_get_location_hierarchy( $first_loc_id ? (int) $first_loc_id : 0 ) : array( 'city_country' => '', 'venue' => '', 'venue_link' => '' ),
			);

			$song_post = get_post( $song_id );
			$first_published = $song_post ? $song_post->post_date : '';
			$days_since = (int) floor( ( current_time( 'timestamp' ) - strtotime( $last['post_date'] ) ) / DAY_IN_SECONDS );

			$stats[] = array(
				'song_id'               => $song_id,
				'song_title'             => get_the_title( $song_id ),
				'song_link'              => get_permalink( $song_id ),
				'first_published'        => $first_published,
				'play_count'             => $play_count,
				'last_played'            => $last_played,
				'first_played'           => $first_played,
				'days_since_last_played' => $days_since,
			);
		}
	}
	
	// Sort by play count (descending)
	usort( $stats, function( $a, $b ) {
		return $b['play_count'] - $a['play_count'];
	} );
	
	// Cache for 1 hour
	if ( $use_cache ) {
		set_transient( 'jww_all_time_song_stats', $stats, HOUR_IN_SECONDS );
	}
	
	return $stats;
}

/**
 * Get gap analysis for a song (days since last played)
 * 
 * @param int $song_id Song post ID
 * @param bool $use_cache Whether to use cached results
 * @return array|false Array with days since last played, or false if never played
 */
function jww_get_song_gap_analysis( $song_id, $use_cache = true ) {
	$stats = jww_get_song_stats( $song_id, $use_cache );
	
	if ( ! $stats['last_played'] ) {
		return false;
	}
	
	$last_played_date = strtotime( $stats['last_played']['show']->post_date );
	$current_date = current_time( 'timestamp' );
	$days_since = floor( ( $current_date - $last_played_date ) / DAY_IN_SECONDS );
	
	return array(
		'days_since'   => $days_since,
		'last_played'  => $stats['last_played'],
		'play_count'   => $stats['play_count'],
	);
}

/**
 * Get location hierarchy (City/Country and Venue) for display.
 * Cached for performance. Used by archive templates and blocks.
 *
 * @param int $location_id Location term ID.
 * @return array Keys: city_country (HTML), venue, venue_link.
 */
function jww_get_location_hierarchy( $location_id ) {
	if ( ! $location_id ) {
		return array(
			'city_country' => '',
			'venue'        => '',
			'venue_link'   => '',
		);
	}

	$cache_key = 'jww_location_hierarchy_' . $location_id;
	$cached = get_transient( $cache_key );
	if ( $cached !== false ) {
		return $cached;
	}

	$location_term = get_term( $location_id, 'location' );
	if ( ! $location_term || is_wp_error( $location_term ) ) {
		$result = array(
			'city_country' => '',
			'venue'        => '',
			'venue_link'   => '',
		);
		set_transient( $cache_key, $result, 6 * HOUR_IN_SECONDS );
		return $result;
	}

	$term_ids_to_fetch = array( $location_id );
	$parent_id = $location_term->parent;
	while ( $parent_id ) {
		$term_ids_to_fetch[] = $parent_id;
		$parent_term = get_term( $parent_id, 'location' );
		if ( $parent_term && ! is_wp_error( $parent_term ) && $parent_term->parent ) {
			$parent_id = $parent_term->parent;
		} else {
			break;
		}
	}

	$terms = get_terms( array(
		'taxonomy'   => 'location',
		'include'   => $term_ids_to_fetch,
		'hide_empty' => false,
	) );

	$terms_by_id = array();
	foreach ( $terms as $term ) {
		$terms_by_id[ $term->term_id ] = $term;
	}

	$path = array();
	$current_term = $location_term;
	while ( $current_term ) {
		array_unshift( $path, $current_term );
		if ( $current_term->parent && isset( $terms_by_id[ $current_term->parent ] ) ) {
			$current_term = $terms_by_id[ $current_term->parent ];
		} else {
			break;
		}
	}

	$venue = '';
	$venue_link = '';
	$city_name = '';
	$city_link = '';
	$country_name = '';
	$country_link = '';
	$path_count = count( $path );

	if ( $path_count >= 1 ) {
		$venue_term = $path[ $path_count - 1 ];
		$venue = $venue_term->name;
		$venue_link = get_term_link( $venue_term->term_id, 'location' );
	}
	if ( $path_count >= 2 ) {
		$city_term = $path[ $path_count - 2 ];
		$city_name = $city_term->name;
		$city_link = get_term_link( $city_term->term_id, 'location' );
	}
	if ( $path_count >= 3 ) {
		$country_term = $path[0];
		$country_name = $country_term->name;
		$country_link = get_term_link( $country_term->term_id, 'location' );
	}

	$city_country_parts = array();
	if ( $city_name ) {
		if ( $city_link && ! is_wp_error( $city_link ) ) {
			$city_country_parts[] = '<a href="' . esc_url( $city_link ) . '">' . esc_html( $city_name ) . '</a>';
		} else {
			$city_country_parts[] = esc_html( $city_name );
		}
	}
	if ( $country_name ) {
		if ( $country_link && ! is_wp_error( $country_link ) ) {
			$city_country_parts[] = '<a href="' . esc_url( $country_link ) . '">' . esc_html( $country_name ) . '</a>';
		} else {
			$city_country_parts[] = esc_html( $country_name );
		}
	}

	$result = array(
		'city_country' => implode( ', ', $city_country_parts ),
		'venue'        => $venue,
		'venue_link'   => is_wp_error( $venue_link ) ? '' : $venue_link,
	);
	set_transient( $cache_key, $result, 6 * HOUR_IN_SECONDS );
	return $result;
}

/**
 * Get the venue image attachment ID for a location term.
 * The location term (venue) can have term meta 'venue_image_id' set from the Venues admin page.
 *
 * @param int $location_id Location (venue) term ID.
 * @return int Attachment ID, or 0 if none.
 */
function jww_get_venue_image_id( $location_id ) {
	if ( ! $location_id ) {
		return 0;
	}
	$image_id = (int) get_term_meta( $location_id, 'venue_image_id', true );
	if ( $image_id <= 0 ) {
		return 0;
	}
	$post = get_post( $image_id );
	return ( $post && $post->post_type === 'attachment' ) ? $image_id : 0;
}

/**
 * Count song/entry items in a setlist (song-post and song-text only).
 *
 * @param array $setlist Setlist array from ACF.
 * @return int
 */
function jww_count_setlist_songs( $setlist ) {
	if ( ! $setlist || ! is_array( $setlist ) ) {
		return 0;
	}
	$count = 0;
	foreach ( $setlist as $item ) {
		if ( isset( $item['entry_type'] ) && ( $item['entry_type'] === 'song-post' || $item['entry_type'] === 'song-text' ) ) {
			$count++;
		}
	}
	return $count;
}

/**
 * Get all performances of a song (every show + set position + days since previous).
 * Cached per song. Used by song-play-history block.
 *
 * @param int  $song_id   Song post ID.
 * @param bool $use_cache Whether to use cached results.
 * @return array List of performances (newest first), each with show_id, post_date, show_link, show_title, location_id, set_position, days_since_previous, location_data (city_country, venue, venue_link).
 */
function jww_get_song_all_performances( $song_id, $use_cache = true ) {
	if ( ! $song_id ) {
		return array();
	}

	$cache_key = 'jww_song_performances_' . $song_id;
	if ( $use_cache ) {
		$cached = get_transient( $cache_key );
		if ( $cached !== false ) {
			return $cached;
		}
	}

	$all_shows = jww_get_all_show_setlists( $use_cache );
	$performances = array();

	foreach ( $all_shows as $show_id => $show_data ) {
		$setlist = $show_data['setlist'];
		$post_date = $show_data['post_date'];
		$position = 0;

		foreach ( $setlist as $item ) {
			if ( $item['entry_type'] === 'song-text' ) {
				$position++;
				continue;
			}
			if ( $item['entry_type'] !== 'song-post' || empty( $item['song'] ) ) {
				continue;
			}
			$song = is_array( $item['song'] ) ? $item['song'][0] : $item['song'];
			$item_song_id = is_object( $song ) ? $song->ID : $song;
			$position++;

			if ( (int) $item_song_id !== (int) $song_id ) {
				continue;
			}

			$location_id = get_field( 'show_location', $show_id );
			$performances[] = array(
				'show_id'       => $show_id,
				'post_date'     => $post_date,
				'show_link'     => get_permalink( $show_id ),
				'show_title'    => get_the_title( $show_id ),
				'location_id'   => $location_id ? (int) $location_id : 0,
				'set_position'  => $position,
			);
			break;
		}
	}

	// Sort by date ascending (oldest first) so we can compute days_since_previous
	usort( $performances, function( $a, $b ) {
		return strtotime( $a['post_date'] ) - strtotime( $b['post_date'] );
	} );

	$prev_date = null;
	foreach ( $performances as &$p ) {
		$p['days_since_previous'] = null;
		if ( $prev_date !== null ) {
			$p['days_since_previous'] = (int) floor( ( strtotime( $p['post_date'] ) - $prev_date ) / DAY_IN_SECONDS );
		}
		$prev_date = strtotime( $p['post_date'] );
		$p['location_data'] = jww_get_location_hierarchy( $p['location_id'] );
	}
	unset( $p );

	// Newest first for display
	$performances = array_reverse( $performances );

	if ( $use_cache ) {
		set_transient( $cache_key, $performances, 30 * MINUTE_IN_SECONDS );
	}

	return $performances;
}

/**
 * Extract song post IDs from an ACF setlist array.
 *
 * @param array $setlist Setlist from get_field( 'setlist', $show_id ).
 * @return int[]
 */
function jww_get_song_ids_from_setlist( $setlist ) {
	$ids = array();
	if ( ! $setlist || ! is_array( $setlist ) ) {
		return $ids;
	}
	foreach ( $setlist as $item ) {
		if ( ( $item['entry_type'] ?? '' ) !== 'song-post' || empty( $item['song'] ) ) {
			continue;
		}
		$song = is_array( $item['song'] ) ? $item['song'][0] : $item['song'];
		$id = is_object( $song ) ? $song->ID : $song;
		if ( $id ) {
			$ids[] = (int) $id;
		}
	}
	return array_unique( $ids );
}

/**
 * Store old setlist for a show before save (for segmented cache clear).
 */
function jww_store_show_setlist_before_save( $post_id ) {
	if ( get_post_type( $post_id ) !== 'show' ) {
		return;
	}
	if ( ! isset( $GLOBALS['jww_old_setlist_by_show'] ) ) {
		$GLOBALS['jww_old_setlist_by_show'] = array();
	}
	$GLOBALS['jww_old_setlist_by_show'][ $post_id ] = get_field( 'setlist', $post_id );
}
add_action( 'save_post', 'jww_store_show_setlist_before_save', 3 );

/**
 * Clear song stats caches only for songs in this show's setlist (before or after save).
 */
function jww_clear_song_stats_on_show_save( $post_id ) {
	if ( get_post_type( $post_id ) !== 'show' ) {
		return;
	}
	$old_setlist = isset( $GLOBALS['jww_old_setlist_by_show'][ $post_id ] ) ? $GLOBALS['jww_old_setlist_by_show'][ $post_id ] : array();
	$new_setlist = get_field( 'setlist', $post_id );
	$song_ids = array_merge(
		jww_get_song_ids_from_setlist( $old_setlist ),
		jww_get_song_ids_from_setlist( $new_setlist )
	);
	jww_clear_song_stats_for_song_ids( $song_ids );
	// Keep admin list performant: store song count in post meta when setlist changes.
	update_post_meta( $post_id, '_show_song_count', jww_count_setlist_songs( $new_setlist ) );
	if ( isset( $GLOBALS['jww_old_setlist_by_show'][ $post_id ] ) ) {
		unset( $GLOBALS['jww_old_setlist_by_show'][ $post_id ] );
	}
}
add_action( 'save_post', 'jww_clear_song_stats_on_show_save', 20 );

/**
 * Get shows immediately before and after a show (by date).
 *
 * @param int $show_id Current show post ID.
 * @param int $before  Number of previous shows to return.
 * @param int $after   Number of following shows to return.
 * @return array { 'previous' => WP_Post[], 'next' => WP_Post[] }
 */
function jww_get_adjacent_shows( $show_id, $before = 3, $after = 3 ) {
	$post = get_post( $show_id );
	if ( ! $post || $post->post_type !== 'show' ) {
		return array( 'previous' => array(), 'next' => array() );
	}
	$date = $post->post_date;

	$prev = get_posts( array(
		'post_type'      => 'show',
		'posts_per_page' => max( 0, $before ),
		'post_status'    => array( 'publish', 'future' ),
		'orderby'        => 'date',
		'order'          => 'DESC',
		'date_query'     => array( array( 'before' => $date ) ),
		'exclude'        => array( $show_id ),
	) );

	$next = get_posts( array(
		'post_type'      => 'show',
		'posts_per_page' => max( 0, $after ),
		'post_status'    => array( 'publish', 'future' ),
		'orderby'        => 'date',
		'order'          => 'ASC',
		'date_query'     => array( array( 'after' => $date ) ),
		'exclude'        => array( $show_id ),
	) );

	return array( 'previous' => $prev, 'next' => $next );
}

/**
 * Get ordered list of all show IDs by date (ascending). Cached for request.
 *
 * @param bool $use_cache Whether to use cached list.
 * @return int[] Show IDs in chronological order.
 */
function jww_get_show_ids_by_date( $use_cache = true ) {
	static $cached = null;
	if ( $cached !== null && $use_cache ) {
		return $cached;
	}
	$all = jww_get_all_show_setlists( $use_cache );
	$with_dates = array();
	foreach ( $all as $sid => $data ) {
		$with_dates[ $sid ] = $data['post_date'];
	}
	asort( $with_dates );
	$cached = array_keys( $with_dates );
	return $cached;
}

/**
 * Get other shows in the same tour as the given show (excluding the show itself).
 *
 * @param int $show_id Show post ID.
 * @return WP_Post[] Other show posts in the same tour, or empty array if no tour or none found.
 */
function jww_get_other_shows_in_tour( $show_id ) {
	$tour_id = get_field( 'show_tour', $show_id );
	if ( ! $tour_id ) {
		return array();
	}
	$tour_id = is_object( $tour_id ) && isset( $tour_id->term_id ) ? (int) $tour_id->term_id : (int) $tour_id;
	if ( ! $tour_id ) {
		return array();
	}
	$others = get_posts( array(
		'post_type'      => 'show',
		'posts_per_page' => -1,
		'post_status'    => array( 'publish', 'future' ),
		'post__not_in'   => array( (int) $show_id ),
		'tax_query'      => array(
			array(
				'taxonomy' => 'tour',
				'field'    => 'term_id',
				'terms'    => $tour_id,
			),
		),
		'orderby'        => 'date',
		'order'          => 'ASC',
	) );
	return is_array( $others ) ? $others : array();
}

/**
 * Build shared context for setlist highlight logic. Use this in highlight methods to avoid
 * repeating setlist/tour/date setup. Debuts use only show_id + song_ids (all-shows); returns
 * use date order (all shows); standout uses comparison_shows (tour).
 *
 * @param int $show_id Show post ID.
 * @return array {
 *   @type int        $show_id           Show post ID.
 *   @type array|null $setlist           Raw setlist from ACF (or null).
 *   @type int[]      $song_ids          Song IDs from setlist.
 *   @type WP_Post[]  $comparison_shows  Other shows in the same tour (for standout).
 *   @type int[]      $show_ids_by_date  All show IDs chronological (for returns).
 *   @type array      $show_id_to_index  Map show_id => index in chronological order.
 *   @type int|null   $current_index     This show's index in chronological order, or null.
 * }
 */
function jww_get_show_setlist_highlights_context( $show_id ) {
	$setlist = get_field( 'setlist', $show_id );
	$song_ids = array();
	if ( $setlist && is_array( $setlist ) ) {
		$song_ids = jww_get_song_ids_from_setlist( $setlist );
	}

	$show_ids_by_date = jww_get_show_ids_by_date( true );
	$show_id_to_index = array_flip( $show_ids_by_date );
	$current_index    = isset( $show_id_to_index[ $show_id ] ) ? $show_id_to_index[ $show_id ] : null;

	return array(
		'show_id'           => (int) $show_id,
		'setlist'           => $setlist,
		'song_ids'          => $song_ids,
		'comparison_shows'   => jww_get_other_shows_in_tour( $show_id ),
		'show_ids_by_date'  => $show_ids_by_date,
		'show_id_to_index'  => $show_id_to_index,
		'current_index'     => $current_index,
	);
}

/**
 * Get live debuts for a show: songs played for the first time ever at this show.
 * Not limited to tour — uses global first-played data (all shows).
 *
 * @param int   $show_id Show post ID.
 * @param array $context Optional. Result of jww_get_show_setlist_highlights_context( $show_id ). If omitted, context is built internally.
 * @return array List of items with song_id, song_title, song_link.
 */
function jww_get_show_setlist_highlights_debuts( $show_id, $context = null ) {
	if ( $context === null ) {
		$context = jww_get_show_setlist_highlights_context( $show_id );
	}
	$song_ids = $context['song_ids'];
	if ( empty( $song_ids ) ) {
		return array();
	}

	$debuts = array();
	if ( ! function_exists( 'jww_get_song_first_played' ) ) {
		return $debuts;
	}

	foreach ( $song_ids as $song_id ) {
		$first = jww_get_song_first_played( $song_id, true );
		if ( ! $first || (int) $first['show_id'] !== (int) $show_id ) {
			continue;
		}
		$debuts[] = array(
			'song_id'    => $song_id,
			'song_title' => get_the_title( $song_id ),
			'song_link'  => get_permalink( $song_id ),
		);
	}
	return $debuts;
}

/**
 * Get "back in the set" for a show: songs reintroduced after at least 3 shows (in global chronology).
 *
 * @param int   $show_id Show post ID.
 * @param array $context Optional. Result of jww_get_show_setlist_highlights_context( $show_id ). If omitted, context is built internally.
 * @return array List of items with song_id, song_title, song_link, shows_since.
 */
function jww_get_show_setlist_highlights_returns( $show_id, $context = null ) {
	if ( $context === null ) {
		$context = jww_get_show_setlist_highlights_context( $show_id );
	}
	$song_ids         = $context['song_ids'];
	$current_index    = $context['current_index'];
	$show_id_to_index = $context['show_id_to_index'];
	$show_ids_by_date = $context['show_ids_by_date'];

	if ( empty( $song_ids ) || $current_index === null || ! function_exists( 'jww_get_song_all_performances' ) ) {
		return array();
	}

	$returns = array();
	foreach ( $song_ids as $song_id ) {
		$performances = jww_get_song_all_performances( $song_id, true );
		$performances_chrono = array_reverse( $performances );
		$idx = null;
		foreach ( $performances_chrono as $i => $p ) {
			if ( (int) $p['show_id'] === (int) $show_id ) {
				$idx = $i;
				break;
			}
		}
		if ( $idx === null || $idx === 0 ) {
			continue;
		}
		$prev_perf_show_id = (int) $performances_chrono[ $idx - 1 ]['show_id'];
		$prev_idx = isset( $show_id_to_index[ $prev_perf_show_id ] ) ? $show_id_to_index[ $prev_perf_show_id ] : null;
		if ( $prev_idx === null ) {
			continue;
		}
		$shows_since = $current_index - $prev_idx - 1;
		if ( $shows_since < 3 ) {
			continue;
		}
		$returns[] = array(
			'song_id'     => $song_id,
			'song_title'  => get_the_title( $song_id ),
			'song_link'   => get_permalink( $song_id ),
			'shows_since' => $shows_since,
		);
	}
	return $returns;
}

/**
 * Get standout comparison for a show: songs consistent across the tour vs unique to this show.
 * Tour-only comparison (other shows in the same tour).
 *
 * @param int   $show_id Show post ID.
 * @param array $context Optional. Result of jww_get_show_setlist_highlights_context( $show_id ). If omitted, context is built internally.
 * @return array { 'consistent_songs' => [], 'unique_songs' => [], 'comparison_shows' => WP_Post[] }
 */
function jww_get_show_setlist_highlights_standout( $show_id, $context = null ) {
	if ( $context === null ) {
		$context = jww_get_show_setlist_highlights_context( $show_id );
	}
	$song_ids         = $context['song_ids'];
	$comparison_shows = $context['comparison_shows'];

	$result = array(
		'consistent_songs' => array(),
		'unique_songs'     => array(),
		'comparison_shows' => $comparison_shows,
	);
	if ( empty( $song_ids ) ) {
		return $result;
	}

	$song_tour_count = array();
	foreach ( $comparison_shows as $other_show ) {
		$adj_setlist = get_field( 'setlist', $other_show->ID );
		$adj_ids = jww_get_song_ids_from_setlist( $adj_setlist );
		foreach ( $adj_ids as $sid ) {
			$song_tour_count[ $sid ] = isset( $song_tour_count[ $sid ] ) ? $song_tour_count[ $sid ] + 1 : 1;
		}
	}

	$consistent_min = 2;
	foreach ( $song_ids as $sid ) {
		$count = isset( $song_tour_count[ $sid ] ) ? $song_tour_count[ $sid ] : 0;
		$item = array(
			'song_id'         => $sid,
			'song_title'      => get_the_title( $sid ),
			'song_link'       => get_permalink( $sid ),
			'tour_show_count' => $count,
		);
		if ( $count >= $consistent_min ) {
			$result['consistent_songs'][] = $item;
		} else {
			$result['unique_songs'][] = $item;
		}
	}
	return $result;
}

/**
 * Get setlist highlights for a show: debuts, reintroductions, and comparison with other shows in the same tour.
 * Aggregates the three highlight types. For per-type logic and customization, use the individual methods.
 *
 * @param int $show_id Show post ID.
 * @param int $adjacent_count Unused. Kept for backwards compatibility; comparison is now tour-based.
 * @return array { 'debuts' => [], 'returns' => [], 'consistent_songs' => [], 'unique_songs' => [], 'comparison_shows' => WP_Post[] }
 */
function jww_get_show_setlist_highlights( $show_id, $adjacent_count = 3 ) {
	$context = jww_get_show_setlist_highlights_context( $show_id );

	$debuts  = jww_get_show_setlist_highlights_debuts( $show_id, $context );
	$returns = jww_get_show_setlist_highlights_returns( $show_id, $context );
	$standout = jww_get_show_setlist_highlights_standout( $show_id, $context );

	return array(
		'debuts'           => $debuts,
		'returns'          => $returns,
		'consistent_songs' => $standout['consistent_songs'],
		'unique_songs'     => $standout['unique_songs'],
		'comparison_shows' => $standout['comparison_shows'],
	);
}

/**
 * Build YouTube search URL for a show: "artist city venue year".
 *
 * @param int    $show_id     Show post ID.
 * @param string $artist_name Artist name for search (e.g. "Jesse Welles").
 * @return string Full URL to YouTube results, or empty string if no location.
 */
function jww_get_show_youtube_search_url( $show_id, $artist_name = 'Jesse Welles' ) {
	$location_id = get_field( 'show_location', $show_id );
	if ( ! $location_id ) {
		return '';
	}
	$h = function_exists( 'jww_get_location_hierarchy' ) ? jww_get_location_hierarchy( (int) $location_id ) : array( 'city_country' => '', 'venue' => '' );
	$city_country = wp_strip_all_tags( $h['city_country'] ?? '' );
	$venue = $h['venue'] ?? '';
	$parts = array_filter( array( $artist_name, $city_country, $venue ) );
	$year = get_the_date( 'Y', $show_id );
	if ( $year ) {
		$parts[] = $year;
	}
	if ( empty( $parts ) ) {
		return '';
	}
	$query = implode( ' ', $parts );
	return 'https://www.youtube.com/results?search_query=' . rawurlencode( $query );
}

/**
 * Release type slugs to exclude from "Songs on Albums" (EP, single, live).
 * Only albums (release type "album") are included as separate rows.
 */
define( 'JWW_SETLIST_EXCLUDED_RELEASE_SLUGS', 'ep,single,live' );

/**
 * Get album/release representation stats for a show's setlist (setlist.fm style).
 * Groups songs by: Covers, Others (unreleased / not on album), then by Album (only release type "album").
 * Cached per show in a transient.
 *
 * @param int $show_id Show post ID.
 * @return array List of groups: [ ['label' => 'Covers'|'Others'|album title, 'album_id' => int|null, 'count' => int, 'songs' => [ ['title'=>, 'link'=>, 'song_id'=>] ] ], ... ]
 */
function jww_get_show_setlist_album_stats( $show_id ) {
	$cache_key = 'jww_show_album_stats_' . $show_id;
	$cached = get_transient( $cache_key );
	if ( $cached !== false && is_array( $cached ) ) {
		return $cached;
	}

	$setlist = get_field( 'setlist', $show_id );
	if ( ! $setlist || ! is_array( $setlist ) ) {
		$result = array();
		set_transient( $cache_key, $result, HOUR_IN_SECONDS );
		return $result;
	}

	$excluded_slugs = array_map( 'trim', explode( ',', JWW_SETLIST_EXCLUDED_RELEASE_SLUGS ) );

	// Collect song entries with song_id, title, link; and whether it's a cover.
	$entries = array();
	foreach ( $setlist as $item ) {
		$entry_type = $item['entry_type'] ?? 'song-post';
		if ( $entry_type === 'note' ) {
			continue;
		}
		$song_id = null;
		$title   = '';
		$link    = '';
		$notes   = $item['notes'] ?? '';
		$song_text = $item['song_text'] ?? '';
		if ( $entry_type === 'song-post' && ! empty( $item['song'] ) ) {
			$song = is_array( $item['song'] ) ? $item['song'][0] : $item['song'];
			if ( is_object( $song ) && isset( $song->ID ) ) {
				$song_id = (int) $song->ID;
				$title   = get_the_title( $song_id );
				$link    = get_permalink( $song_id );
			}
		} elseif ( $entry_type === 'song-text' && ! empty( $song_text ) ) {
			$title = $song_text;
		}
		if ( $title === '' ) {
			continue;
		}

		$is_cover = false;
		if ( $song_id ) {
			$terms = wp_get_post_terms( $song_id, 'category' );
			foreach ( $terms as $term ) {
				if ( $term->slug === 'cover' ) {
					$is_cover = true;
					break;
				}
			}
		} else {
			// Unlinked (song-text) entry: treat as cover if notes say "Cover:" or title ends with "(...cover)".
			if ( $notes && preg_match( '/\bcover\s*:/i', $notes ) ) {
				$is_cover = true;
			} elseif ( preg_match( '/\s*\([^)]*cover\)\s*$/i', $title ) ) {
				$is_cover = true;
			}
		}

		$entries[] = array(
			'song_id'  => $song_id,
			'title'    => $title,
			'link'     => $link,
			'is_cover' => $is_cover,
		);
	}

	// Group: Covers, Others, then by Album (only release type "album").
	$groups = array(
		'__covers' => array( 'label' => __( 'Covers', 'jww-theme' ), 'album_id' => null, 'count' => 0, 'songs' => array() ),
		'__others' => array( 'label' => __( 'Others', 'jww-theme' ), 'album_id' => null, 'count' => 0, 'songs' => array(), '_key' => 'others' ),
	);
	$album_groups = array(); // album_id => [ label, album_id, count, songs ]

	foreach ( $entries as $e ) {
		$song_data = array( 'title' => $e['title'], 'link' => $e['link'], 'song_id' => $e['song_id'] );

		if ( $e['is_cover'] ) {
			$groups['__covers']['songs'][] = $song_data;
			$groups['__covers']['count']++;
			continue;
		}

		$album_id = null;
		if ( $e['song_id'] ) {
			$album = get_field( 'album', $e['song_id'] );
			if ( $album ) {
				if ( is_array( $album ) ) {
					$album_id = isset( $album[0] ) ? ( is_object( $album[0] ) ? (int) $album[0]->ID : (int) $album[0] ) : 0;
				} elseif ( is_object( $album ) && isset( $album->ID ) ) {
					$album_id = (int) $album->ID;
				} else {
					$album_id = (int) $album;
				}
			}
		}

		$include_as_album = false;
		$album_title     = '';

		if ( $album_id ) {
			$release_terms = wp_get_post_terms( $album_id, 'release' );
			$has_excluded  = false;
			$has_album     = false;
			foreach ( $release_terms as $term ) {
				if ( in_array( $term->slug, $excluded_slugs, true ) ) {
					$has_excluded = true;
					break;
				}
				if ( $term->slug === 'album' ) {
					$has_album = true;
				}
			}
			if ( $has_album && ! $has_excluded ) {
				$include_as_album = true;
				$album_title     = get_the_title( $album_id );
			}
		}

		if ( $include_as_album && $album_title !== '' ) {
			if ( ! isset( $album_groups[ $album_id ] ) ) {
				$thumb_url = get_the_post_thumbnail_url( $album_id, 'thumbnail' );
				$album_groups[ $album_id ] = array(
					'label'         => $album_title,
					'album_id'      => $album_id,
					'count'         => 0,
					'songs'         => array(),
					'thumbnail_url' => $thumb_url ? $thumb_url : null,
				);
			}
			$album_groups[ $album_id ]['songs'][] = $song_data;
			$album_groups[ $album_id ]['count']++;
		} else {
			$groups['__others']['songs'][] = $song_data;
			$groups['__others']['count']++;
		}
	}

	// Build result: sort by count descending, but always put Others last.
	$result = array();
	if ( $groups['__covers']['count'] > 0 ) {
		$groups['__covers']['thumbnail_url'] = null;
		$result[] = $groups['__covers'];
	}
	foreach ( $album_groups as $g ) {
		$result[] = $g;
	}
	if ( $groups['__others']['count'] > 0 ) {
		$groups['__others']['thumbnail_url'] = null;
		$result[] = $groups['__others'];
	}
	// Sort by count descending (highest first), but keep Others at the end.
	$others = null;
	$rest = array();
	foreach ( $result as $g ) {
		if ( isset( $g['_key'] ) && $g['_key'] === 'others' ) {
			$others = $g;
		} else {
			$rest[] = $g;
		}
	}
	usort( $rest, function( $a, $b ) {
		return ( (int) $b['count'] ) - ( (int) $a['count'] );
	} );
	$result = $rest;
	if ( $others !== null ) {
		$result[] = $others;
	}

	set_transient( $cache_key, $result, HOUR_IN_SECONDS );
	return $result;
}

/**
 * Clear the setlist album stats transient when a show's setlist is updated.
 */
function jww_clear_show_setlist_album_stats_on_save( $post_id ) {
	if ( get_post_type( $post_id ) !== 'show' ) {
		return;
	}
	delete_transient( 'jww_show_album_stats_' . $post_id );
}
add_action( 'save_post', 'jww_clear_show_setlist_album_stats_on_save', 25 );

/**
 * Store show's tour ID before save (for cache invalidation when tour changes).
 */
function jww_store_show_tour_before_save( $post_id ) {
	if ( get_post_type( $post_id ) !== 'show' ) {
		return;
	}
	if ( ! isset( $GLOBALS['jww_old_tour_by_show'] ) ) {
		$GLOBALS['jww_old_tour_by_show'] = array();
	}
	$GLOBALS['jww_old_tour_by_show'][ $post_id ] = get_field( 'show_tour', $post_id );
}
add_action( 'save_post', 'jww_store_show_tour_before_save', 5 );

/**
 * Get song performance counts for a tour (all shows in the tour with setlists).
 * Cached in transient; invalidated when any show in that tour is saved.
 *
 * @param int $tour_id Tour term ID.
 * @return array [ 'show_count' => int, 'songs' => [ [ 'song_id' => int, 'song_title' => string, 'song_link' => string, 'count' => int ], ... ] ] sorted by count desc.
 */
function jww_get_tour_song_counts( $tour_id ) {
	$tour_id = (int) $tour_id;
	if ( ! $tour_id ) {
		return array( 'show_count' => 0, 'songs' => array() );
	}

	$cache_key = 'jww_tour_song_counts_' . $tour_id;
	$cached = get_transient( $cache_key );
	if ( $cached !== false && is_array( $cached ) ) {
		return $cached;
	}

	$shows = jww_get_shows_by_tour( $tour_id );
	$counts = array(); // song_id => count
	$show_count_with_setlist = 0;

	foreach ( $shows as $show ) {
		$setlist = get_field( 'setlist', $show->ID );
		if ( ! $setlist || ! is_array( $setlist ) ) {
			continue;
		}
		$show_count_with_setlist++;
		$song_ids = jww_get_song_ids_from_setlist( $setlist );
		foreach ( $song_ids as $sid ) {
			$counts[ $sid ] = isset( $counts[ $sid ] ) ? $counts[ $sid ] + 1 : 1;
		}
	}

	$songs = array();
	foreach ( $counts as $song_id => $count ) {
		$thumb_url = get_the_post_thumbnail_url( $song_id, 'thumbnail' );
		$songs[] = array(
			'song_id'       => $song_id,
			'song_title'    => get_the_title( $song_id ),
			'song_link'     => get_permalink( $song_id ),
			'count'         => $count,
			'thumbnail_url' => $thumb_url ? $thumb_url : null,
		);
	}
	usort( $songs, function( $a, $b ) {
		return $b['count'] - $a['count'];
	} );

	$result = array(
		'show_count' => $show_count_with_setlist,
		'songs'      => $songs,
	);
	set_transient( $cache_key, $result, HOUR_IN_SECONDS );
	return $result;
}

/**
 * Invalidate tour song counts when any show in that tour is saved (or when a show's tour changes).
 */
function jww_clear_tour_song_counts_on_show_save( $post_id ) {
	if ( get_post_type( $post_id ) !== 'show' ) {
		return;
	}
	$new_tour = get_field( 'show_tour', $post_id );
	$old_tour = isset( $GLOBALS['jww_old_tour_by_show'][ $post_id ] ) ? $GLOBALS['jww_old_tour_by_show'][ $post_id ] : null;

	$normalize = function( $id ) {
		if ( ! $id ) {
			return 0;
		}
		if ( is_object( $id ) && isset( $id->term_id ) ) {
			return (int) $id->term_id;
		}
		return (int) $id;
	};
	$tour_ids = array_unique( array_filter( array( $normalize( $new_tour ), $normalize( $old_tour ) ) ) );
	foreach ( $tour_ids as $tid ) {
		delete_transient( 'jww_tour_song_counts_' . $tid );
	}
	if ( isset( $GLOBALS['jww_old_tour_by_show'][ $post_id ] ) ) {
		unset( $GLOBALS['jww_old_tour_by_show'][ $post_id ] );
	}
}
add_action( 'save_post', 'jww_clear_tour_song_counts_on_show_save', 26 );
