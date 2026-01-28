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
 * Count how many times a song was played
 * 
 * @param int $song_id Song post ID
 * @return int Number of times played
 */
function jww_get_song_play_count( $song_id ) {
	$args = array(
		'post_type'      => 'show',
		'posts_per_page' => -1,
		'post_status'    => array( 'publish', 'future' ),
		'meta_query'     => array(
			array(
				'key'     => 'setlist',
				'compare' => 'EXISTS',
			),
		),
	);
	
	$shows = get_posts( $args );
	$count = 0;
	
	foreach ( $shows as $show ) {
		$setlist = get_field( 'setlist', $show->ID );
		if ( ! $setlist || ! is_array( $setlist ) ) {
			continue;
		}
		
		foreach ( $setlist as $item ) {
			if ( $item['entry_type'] === 'song-post' && ! empty( $item['song'] ) ) {
				$song = is_array( $item['song'] ) ? $item['song'][0] : $item['song'];
				$item_song_id = is_object( $song ) ? $song->ID : $song;
				if ( $item_song_id == $song_id ) {
					$count++;
				}
			}
		}
	}
	
	return $count;
}

/**
 * Get last show where a song was played
 * 
 * @param int $song_id Song post ID
 * @return array|false Show post object and date, or false if never played
 */
function jww_get_song_last_played( $song_id ) {
	$args = array(
		'post_type'      => 'show',
		'posts_per_page' => -1,
		'post_status'    => array( 'publish', 'future' ),
		'orderby'        => 'date',
		'order'          => 'DESC',
		'meta_query'     => array(
			array(
				'key'     => 'setlist',
				'compare' => 'EXISTS',
			),
		),
	);
	
	$shows = get_posts( $args );
	
	foreach ( $shows as $show ) {
		$setlist = get_field( 'setlist', $show->ID );
		if ( ! $setlist || ! is_array( $setlist ) ) {
			continue;
		}
		
		foreach ( $setlist as $item ) {
			if ( $item['entry_type'] === 'song-post' && ! empty( $item['song'] ) ) {
				$song = is_array( $item['song'] ) ? $item['song'][0] : $item['song'];
				$item_song_id = is_object( $song ) ? $song->ID : $song;
				if ( $item_song_id == $song_id ) {
					return array(
						'show'      => $show,
						'show_id'   => $show->ID,
						'show_date' => get_the_date( 'F j, Y', $show->ID ),
						'show_link' => get_permalink( $show->ID ),
					);
				}
			}
		}
	}
	
	return false;
}

/**
 * Get first show where a song was played
 * 
 * @param int $song_id Song post ID
 * @return array|false Show post object and date, or false if never played
 */
function jww_get_song_first_played( $song_id ) {
	$args = array(
		'post_type'      => 'show',
		'posts_per_page' => -1,
		'post_status'    => array( 'publish', 'future' ),
		'orderby'        => 'date',
		'order'          => 'ASC',
		'meta_query'     => array(
			array(
				'key'     => 'setlist',
				'compare' => 'EXISTS',
			),
		),
	);
	
	$shows = get_posts( $args );
	
	foreach ( $shows as $show ) {
		$setlist = get_field( 'setlist', $show->ID );
		if ( ! $setlist || ! is_array( $setlist ) ) {
			continue;
		}
		
		foreach ( $setlist as $item ) {
			if ( $item['entry_type'] === 'song-post' && ! empty( $item['song'] ) ) {
				$song = is_array( $item['song'] ) ? $item['song'][0] : $item['song'];
				$item_song_id = is_object( $song ) ? $song->ID : $song;
				if ( $item_song_id == $song_id ) {
					return array(
						'show'      => $show,
						'show_id'   => $show->ID,
						'show_date' => get_the_date( 'F j, Y', $show->ID ),
						'show_link' => get_permalink( $show->ID ),
					);
				}
			}
		}
	}
	
	return false;
}

/**
 * Get recent shows where a song was played
 * 
 * @param int $song_id Song post ID
 * @param int $limit Number of shows to return
 * @return array Array of show data
 */
function jww_get_song_recent_shows( $song_id, $limit = 10 ) {
	$args = array(
		'post_type'      => 'show',
		'posts_per_page' => -1,
		'post_status'    => array( 'publish', 'future' ),
		'orderby'        => 'date',
		'order'          => 'DESC',
		'meta_query'     => array(
			array(
				'key'     => 'setlist',
				'compare' => 'EXISTS',
			),
		),
	);
	
	$shows = get_posts( $args );
	$recent_shows = array();
	
	foreach ( $shows as $show ) {
		$setlist = get_field( 'setlist', $show->ID );
		if ( ! $setlist || ! is_array( $setlist ) ) {
			continue;
		}
		
		$played = false;
		foreach ( $setlist as $item ) {
			if ( $item['entry_type'] === 'song-post' && ! empty( $item['song'] ) ) {
				$song = is_array( $item['song'] ) ? $item['song'][0] : $item['song'];
				$item_song_id = is_object( $song ) ? $song->ID : $song;
				if ( $item_song_id == $song_id ) {
					$played = true;
					break;
				}
			}
		}
		
		if ( $played ) {
			$location_id = get_field( 'show_location', $show->ID );
			$location_name = '';
			if ( $location_id ) {
				$location_term = get_term( $location_id, 'location' );
				if ( $location_term && ! is_wp_error( $location_term ) ) {
					$location_name = $location_term->name;
				}
			}
			
			$recent_shows[] = array(
				'show'          => $show,
				'show_id'       => $show->ID,
				'show_title'    => get_the_title( $show->ID ),
				'show_date'     => get_the_date( 'F j, Y', $show->ID ),
				'show_link'     => get_permalink( $show->ID ),
				'location_name' => $location_name,
			);
			
			if ( count( $recent_shows ) >= $limit ) {
				break;
			}
		}
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
	
	$songs = get_posts( array(
		'post_type'      => 'song',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'fields'         => 'ids', // Only get IDs for better performance
	) );
	
	$stats = array();
	
	foreach ( $songs as $song_id ) {
		$play_count = jww_get_song_play_count( $song_id );
		if ( $play_count > 0 ) {
			$last_played = jww_get_song_last_played( $song_id );
			$first_played = jww_get_song_first_played( $song_id );
			
			$stats[] = array(
				'song_id'      => $song_id,
				'song_title'   => get_the_title( $song_id ),
				'song_link'    => get_permalink( $song_id ),
				'play_count'   => $play_count,
				'last_played'  => $last_played,
				'first_played' => $first_played,
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
 * @return array|false Array with days since last played, or false if never played
 */
function jww_get_song_gap_analysis( $song_id ) {
	$last_played = jww_get_song_last_played( $song_id );
	if ( ! $last_played ) {
		return false;
	}
	
	$last_played_date = strtotime( $last_played['show']->post_date );
	$current_date = current_time( 'timestamp' );
	$days_since = floor( ( $current_date - $last_played_date ) / DAY_IN_SECONDS );
	
	return array(
		'days_since'   => $days_since,
		'last_played'  => $last_played,
		'play_count'   => jww_get_song_play_count( $song_id ),
	);
}
