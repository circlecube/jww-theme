<?php
/**
 * Tour statistics and insight helper functions.
 * Used by tour archive template and tour insight cards. Depends on show-functions.php (e.g. jww_get_song_ids_from_setlist, jww_get_song_first_played).
 *
 * @package JWW_Theme
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get shows for a specific tour.
 *
 * @param int $tour_id Tour term ID.
 * @return WP_Post[]
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
				'terms'    => (int) $tour_id,
			),
		),
	);
	return get_posts( $args );
}

/**
 * Get the number of unique cities in a tour (based on show venues: city = parent of venue term).
 *
 * @param int $tour_id Tour term ID.
 * @return int Number of unique city (location) terms.
 */
function jww_get_tour_cities_count( $tour_id ) {
	$tour_id = (int) $tour_id;
	if ( ! $tour_id ) {
		return 0;
	}
	$cache_key = 'jww_tour_cities_count_' . $tour_id;
	$cached = get_transient( $cache_key );
	if ( $cached !== false && is_numeric( $cached ) ) {
		return (int) $cached;
	}
	$shows = jww_get_shows_by_tour( $tour_id );
	$city_ids = array();
	foreach ( $shows as $show ) {
		$location_id = get_field( 'show_location', $show->ID );
		if ( ! $location_id ) {
			continue;
		}
		$location_id = is_object( $location_id ) && isset( $location_id->term_id ) ? (int) $location_id->term_id : (int) $location_id;
		if ( ! $location_id ) {
			continue;
		}
		$term = get_term( $location_id, 'location' );
		if ( ! $term || is_wp_error( $term ) || ! $term->parent ) {
			continue;
		}
		$city_ids[ $term->parent ] = true;
	}
	$count = count( $city_ids );
	set_transient( $cache_key, $count, HOUR_IN_SECONDS );
	return $count;
}

/**
 * Get the number of unique venues in a tour (unique show_location terms).
 *
 * @param int $tour_id Tour term ID.
 * @return int Number of unique venue (location) terms.
 */
function jww_get_tour_venues_count( $tour_id ) {
	$tour_id = (int) $tour_id;
	if ( ! $tour_id ) {
		return 0;
	}
	$cache_key = 'jww_tour_venues_count_' . $tour_id;
	$cached = get_transient( $cache_key );
	if ( $cached !== false && is_numeric( $cached ) ) {
		return (int) $cached;
	}
	$shows = jww_get_shows_by_tour( $tour_id );
	$venue_ids = array();
	foreach ( $shows as $show ) {
		$location_id = get_field( 'show_location', $show->ID );
		if ( ! $location_id ) {
			continue;
		}
		$location_id = is_object( $location_id ) && isset( $location_id->term_id ) ? (int) $location_id->term_id : (int) $location_id;
		if ( $location_id ) {
			$venue_ids[ $location_id ] = true;
		}
	}
	$count = count( $venue_ids );
	set_transient( $cache_key, $count, HOUR_IN_SECONDS );
	return $count;
}

/**
 * Get most common show openers and closers for a tour (first and last song of each setlist).
 * Returns top 3 openers and top 3 closers.
 *
 * @param int $tour_id Tour term ID.
 * @return array Keys: openers => array of [ song_id, title, link, count ], closers => array of [ song_id, title, link, count ].
 */
function jww_get_tour_opener_closer( $tour_id ) {
	$tour_id = (int) $tour_id;
	if ( ! $tour_id ) {
		return array( 'openers' => array(), 'closers' => array() );
	}
	$cache_key = 'jww_tour_opener_closer_' . $tour_id;
	$cached = get_transient( $cache_key );
	if ( $cached !== false && is_array( $cached ) && isset( $cached['openers'] ) ) {
		return $cached;
	}

	$shows = jww_get_shows_by_tour( $tour_id );
	$opener_counts = array();
	$closer_counts = array();

	foreach ( $shows as $show ) {
		$setlist = get_field( 'setlist', $show->ID );
		if ( ! $setlist || ! is_array( $setlist ) ) {
			continue;
		}
		$first_id = function_exists( 'jww_get_setlist_first_song_id' ) ? jww_get_setlist_first_song_id( $setlist ) : 0;
		$last_id  = function_exists( 'jww_get_setlist_last_song_id' ) ? jww_get_setlist_last_song_id( $setlist ) : 0;
		if ( $first_id ) {
			$opener_counts[ $first_id ] = ( $opener_counts[ $first_id ] ?? 0 ) + 1;
		}
		if ( $last_id ) {
			$closer_counts[ $last_id ] = ( $closer_counts[ $last_id ] ?? 0 ) + 1;
		}
	}

	$openers = array();
	$closers = array();
	$top_n = 3;

	if ( ! empty( $opener_counts ) ) {
		arsort( $opener_counts, SORT_NUMERIC );
		$take = array_slice( array_keys( $opener_counts ), 0, $top_n, true );
		foreach ( $take as $song_id ) {
			$song_id = (int) $song_id;
			$openers[] = array(
				'song_id' => $song_id,
				'title'   => get_the_title( $song_id ),
				'link'    => get_permalink( $song_id ),
				'count'   => $opener_counts[ $song_id ],
			);
		}
	}
	if ( ! empty( $closer_counts ) ) {
		arsort( $closer_counts, SORT_NUMERIC );
		$take = array_slice( array_keys( $closer_counts ), 0, $top_n, true );
		foreach ( $take as $song_id ) {
			$song_id = (int) $song_id;
			$closers[] = array(
				'song_id' => $song_id,
				'title'   => get_the_title( $song_id ),
				'link'    => get_permalink( $song_id ),
				'count'   => $closer_counts[ $song_id ],
			);
		}
	}

	$result = array( 'openers' => $openers, 'closers' => $closers );
	set_transient( $cache_key, $result, HOUR_IN_SECONDS );
	return $result;
}

/**
 * Get the number of shows in a tour that are marked as part of a festival.
 *
 * @param int $tour_id Tour term ID.
 * @return int Count of festival shows in the tour.
 */
function jww_get_tour_festivals_count( $tour_id ) {
	$tour_id = (int) $tour_id;
	if ( ! $tour_id ) {
		return 0;
	}
	$cache_key = 'jww_tour_festivals_count_' . $tour_id;
	$cached = get_transient( $cache_key );
	if ( $cached !== false && is_numeric( $cached ) ) {
		return (int) $cached;
	}
	$shows = jww_get_shows_by_tour( $tour_id );
	$count = 0;
	foreach ( $shows as $show ) {
		if ( get_field( 'show_festival', $show->ID ) ) {
			$count++;
		}
	}
	set_transient( $cache_key, $count, HOUR_IN_SECONDS );
	return $count;
}

/**
 * Get past show posts for a tour (post_date <= now). Used by tour-level insights.
 *
 * @param int $tour_id Tour term ID.
 * @return WP_Post[]
 */
function jww_get_past_shows_by_tour( $tour_id ) {
	$shows = jww_get_shows_by_tour( $tour_id );
	$now = current_time( 'mysql' );
	return array_values( array_filter( $shows, function( $show ) use ( $now ) {
		return $show->post_date <= $now;
	} ) );
}

/**
 * Get song performance counts for a tour (past shows with setlists only).
 * Cached in transient; invalidated when any show in that tour is saved.
 *
 * @param int $tour_id Tour term ID.
 * @return array [ 'show_count' => int, 'songs' => [ [ 'song_id', 'song_title', 'song_link', 'count', 'thumbnail_url' ], ... ] ] sorted by count desc.
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
	$now = current_time( 'mysql' );
	$shows = array_filter( $shows, function( $show ) use ( $now ) {
		return $show->post_date <= $now;
	} );

	$counts = array();
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
 * Get tour debuts: songs that had their first-ever live performance during this tour (past shows only).
 *
 * @param int $tour_id Tour term ID.
 * @return array List of items with song_id, song_title, song_link, thumbnail_url, debut_show_id, debut_show_date, debut_show_link (sorted by debut date).
 */
function jww_get_tour_debuts( $tour_id ) {
	$tour_id = (int) $tour_id;
	if ( ! $tour_id || ! function_exists( 'jww_get_song_first_played' ) ) {
		return array();
	}
	$past_shows = jww_get_past_shows_by_tour( $tour_id );
	$past_show_ids = array_flip( wp_list_pluck( $past_shows, 'ID' ) );
	$seen_song_ids = array();
	$debuts = array();
	foreach ( $past_shows as $show ) {
		$setlist = get_field( 'setlist', $show->ID );
		if ( ! $setlist || ! is_array( $setlist ) ) {
			continue;
		}
		$song_ids = jww_get_song_ids_from_setlist( $setlist );
		foreach ( $song_ids as $song_id ) {
			if ( isset( $seen_song_ids[ $song_id ] ) ) {
				continue;
			}
			$seen_song_ids[ $song_id ] = true;
			$first = jww_get_song_first_played( $song_id, true );
			if ( ! $first || ! isset( $past_show_ids[ (int) $first['show_id'] ] ) ) {
				continue;
			}
			$debuts[] = array(
				'song_id'          => $song_id,
				'song_title'       => get_the_title( $song_id ),
				'song_link'        => get_permalink( $song_id ),
				'thumbnail_url'    => get_the_post_thumbnail_url( $song_id, 'thumbnail' ) ?: null,
				'debut_show_id'    => (int) $first['show_id'],
				'debut_show_date'  => get_the_date( 'M j, Y', $first['show_id'] ),
				'debut_show_link'  => get_permalink( $first['show_id'] ),
			);
		}
	}
	usort( $debuts, function( $a, $b ) {
		return strtotime( get_the_date( 'Y-m-d', $a['debut_show_id'] ) ) - strtotime( get_the_date( 'Y-m-d', $b['debut_show_id'] ) );
	} );
	return $debuts;
}

/**
 * Get songs played at only one show in the tour (past shows with setlists only). Uses same cache as jww_get_tour_song_counts.
 * For each one-off, includes show_id, show_link, show_date for the single show where it was played.
 *
 * @param int $tour_id Tour term ID.
 * @return array Same structure as jww_get_tour_song_counts['songs'] but only entries with count === 1, plus show_id, show_link, show_date.
 */
function jww_get_tour_one_offs( $tour_id ) {
	$data = jww_get_tour_song_counts( $tour_id );
	$one_offs = array();
	foreach ( $data['songs'] as $row ) {
		if ( (int) $row['count'] !== 1 ) {
			continue;
		}
		$song_id = (int) $row['song_id'];
		$show_where_played = jww_get_tour_show_where_song_played( $tour_id, $song_id );
		if ( $show_where_played ) {
			$row['show_id']   = $show_where_played['show_id'];
			$row['show_link'] = $show_where_played['show_link'];
			$row['show_date'] = $show_where_played['show_date'];
		} else {
			$row['show_id']   = 0;
			$row['show_link'] = '';
			$row['show_date'] = '';
		}
		$one_offs[] = $row;
	}
	return $one_offs;
}

/**
 * Get the single show in a tour where a song was played (past shows with setlists only).
 *
 * @param int $tour_id Tour term ID.
 * @param int $song_id Song post ID.
 * @return array|null Keys show_id, show_link, show_date; or null if not found.
 */
function jww_get_tour_show_where_song_played( $tour_id, $song_id ) {
	$tour_id = (int) $tour_id;
	$song_id = (int) $song_id;
	if ( ! $tour_id || ! $song_id ) {
		return null;
	}
	$past_shows = jww_get_past_shows_by_tour( $tour_id );
	foreach ( $past_shows as $show ) {
		$setlist = get_field( 'setlist', $show->ID );
		if ( ! $setlist || ! is_array( $setlist ) ) {
			continue;
		}
		$song_ids = jww_get_song_ids_from_setlist( $setlist );
		if ( in_array( $song_id, $song_ids, true ) ) {
			return array(
				'show_id'   => $show->ID,
				'show_link' => get_permalink( $show->ID ),
				'show_date' => get_the_date( 'M j, Y', $show->ID ),
			);
		}
	}
	return null;
}

/**
 * Get consistent/standout songs for a tour: songs that appear in at least (show_count - 1) past shows.
 * Threshold scales with tour size so one skipped show still includes the song. Uses jww_get_tour_song_counts.
 *
 * @param int $tour_id Tour term ID.
 * @return array List of items with song_id, song_title, song_link, count, thumbnail_url (sorted by count desc).
 */
function jww_get_tour_standout_songs( $tour_id ) {
	$data = jww_get_tour_song_counts( $tour_id );
	$show_count = (int) $data['show_count'];
	if ( $show_count <= 0 ) {
		return array();
	}
	// Allow one skip: song must appear in at least (show_count - 1) shows.
	$min_count = max( 1, $show_count - 1 );
	$out = array();
	foreach ( $data['songs'] as $row ) {
		if ( (int) $row['count'] >= $min_count ) {
			$out[] = $row;
		}
	}
	return $out;
}

/**
 * Get album/release representation stats for all songs across a tour's setlists (past shows only).
 * Same structure as jww_get_show_setlist_album_stats; each group's count is unique songs (not total performances). Cached per tour.
 *
 * @param int $tour_id Tour term ID.
 * @return array{groups: array, total_entries: int} total_entries = unique song/track count in tour (for chart).
 */
function jww_get_tour_album_stats( $tour_id ) {
	$tour_id = (int) $tour_id;
	if ( ! $tour_id ) {
		return array( 'groups' => array(), 'total_entries' => 0 );
	}
	$cache_key = 'jww_tour_album_stats_' . $tour_id;
	$cached = get_transient( $cache_key );
	if ( $cached !== false && is_array( $cached ) && isset( $cached['groups'] ) ) {
		return $cached;
	}

	$past_shows = jww_get_past_shows_by_tour( $tour_id );
	$entries = array();
	$excluded_slugs = defined( 'JWW_SETLIST_EXCLUDED_RELEASE_SLUGS' ) ? array_map( 'trim', explode( ',', JWW_SETLIST_EXCLUDED_RELEASE_SLUGS ) ) : array( 'single', 'live' );
	foreach ( $past_shows as $show ) {
		$setlist = get_field( 'setlist', $show->ID );
		if ( ! $setlist || ! is_array( $setlist ) ) {
			continue;
		}
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
				if ( $notes && preg_match( '/\bcover\s*:/i', $notes ) ) {
					$is_cover = true;
				} elseif ( preg_match( '/\s*\([^)]*cover\)\s*$/i', $title ) ) {
					$is_cover = true;
				}
			}
			$entries[] = array(
				'song_id'   => $song_id,
				'title'     => $title,
				'link'      => $link,
				'is_cover'  => $is_cover,
				'set_order' => count( $entries ),
			);
		}
	}

	if ( empty( $entries ) ) {
		$result = array( 'groups' => array(), 'total_entries' => 0 );
		set_transient( $cache_key, $result, HOUR_IN_SECONDS );
		return $result;
	}

	$excluded_slugs = defined( 'JWW_SETLIST_EXCLUDED_RELEASE_SLUGS' ) ? array_map( 'trim', explode( ',', JWW_SETLIST_EXCLUDED_RELEASE_SLUGS ) ) : array( 'single', 'live' );
	$groups = array(
		'__covers' => array( 'label' => __( 'Covers', 'jww-theme' ), 'album_id' => null, 'count' => 0, 'songs' => array() ),
		'__others' => array( 'label' => __( 'Others', 'jww-theme' ), 'album_id' => null, 'count' => 0, 'songs' => array(), '_key' => 'others' ),
	);
	$album_groups = array();
	$seen_song_in_group = array(); // __covers, __others, or album_id => set of song_id for unique list

	foreach ( $entries as $e ) {
		$song_data = array( 'title' => $e['title'], 'link' => $e['link'], 'song_id' => $e['song_id'], 'set_order' => isset( $e['set_order'] ) ? $e['set_order'] : 9999 );

		if ( $e['is_cover'] ) {
			if ( ! isset( $seen_song_in_group['__covers'][ $e['song_id'] ] ) ) {
				$seen_song_in_group['__covers'][ $e['song_id'] ] = true;
				$groups['__covers']['count']++;
				$groups['__covers']['songs'][] = array_merge( $song_data, array( 'track_number' => null ) );
			}
			continue;
		}

		$album_ids = array();
		if ( $e['song_id'] ) {
			$album = get_field( 'album', $e['song_id'] );
			if ( $album ) {
				if ( is_array( $album ) ) {
					foreach ( $album as $a ) {
						$aid = is_object( $a ) && isset( $a->ID ) ? (int) $a->ID : (int) $a;
						if ( $aid > 0 ) {
							$album_ids[] = $aid;
						}
					}
				} elseif ( is_object( $album ) && isset( $album->ID ) ) {
					$album_ids[] = (int) $album->ID;
				} else {
					$aid = (int) $album;
					if ( $aid > 0 ) {
						$album_ids[] = $aid;
					}
				}
			}
		}

		$added_to_any_album = false;
		foreach ( $album_ids as $album_id ) {
			$release_terms = wp_get_post_terms( $album_id, 'release' );
			$has_excluded  = false;
			$has_album_or_ep = false;
			foreach ( $release_terms as $term ) {
				if ( in_array( $term->slug, $excluded_slugs, true ) ) {
					$has_excluded = true;
					break;
				}
				if ( $term->slug === 'album' || $term->slug === 'ep' ) {
					$has_album_or_ep = true;
				}
			}
			if ( ! $has_album_or_ep || $has_excluded ) {
				continue;
			}
			$album_title = get_the_title( $album_id );
			if ( $album_title === '' ) {
				continue;
			}

			if ( ! isset( $album_groups[ $album_id ] ) ) {
				$thumb_url = get_the_post_thumbnail_url( $album_id, 'thumbnail' );
				$album_groups[ $album_id ] = array(
					'label'         => $album_title,
					'album_id'      => $album_id,
					'count'         => 0,
					'songs'         => array(),
					'thumbnail_url' => $thumb_url ? $thumb_url : null,
				);
				$seen_song_in_group[ $album_id ] = array();
			}

			if ( ! isset( $seen_song_in_group[ $album_id ][ $e['song_id'] ] ) ) {
				$seen_song_in_group[ $album_id ][ $e['song_id'] ] = true;
				$album_groups[ $album_id ]['count']++;
				$track_number = null;
				$tracklist = get_field( 'tracklist', $album_id );
				if ( is_array( $tracklist ) && ! empty( $tracklist ) && $e['song_id'] ) {
					$tracklist_ids = array_map( function ( $s ) {
						return is_object( $s ) && isset( $s->ID ) ? (int) $s->ID : (int) $s;
					}, $tracklist );
					$pos = array_search( (int) $e['song_id'], $tracklist_ids, true );
					if ( $pos !== false ) {
						$track_number = $pos + 1;
					}
				}
				$album_groups[ $album_id ]['songs'][] = array_merge( $song_data, array( 'track_number' => $track_number ) );
			}
			$added_to_any_album = true;
		}

		if ( ! $added_to_any_album ) {
			if ( ! isset( $seen_song_in_group['__others'][ $e['song_id'] ] ) ) {
				$seen_song_in_group['__others'][ $e['song_id'] ] = true;
				$groups['__others']['count']++;
				$groups['__others']['songs'][] = array_merge( $song_data, array( 'track_number' => null ) );
			}
		}
	}

	// Total unique songs/tracks in the tour (for chart percentages).
	$unique_in_tour = array();
	foreach ( $entries as $e ) {
		if ( ! empty( $e['song_id'] ) ) {
			$unique_in_tour[ $e['song_id'] ] = true;
		} else {
			$unique_in_tour[ 'text-' . ( isset( $e['set_order'] ) ? $e['set_order'] : 0 ) ] = true;
		}
	}
	$total_entries = count( $unique_in_tour );

	foreach ( $album_groups as $album_id => $group ) {
		usort( $album_groups[ $album_id ]['songs'], function( $a, $b ) {
			$ta = isset( $a['track_number'] ) && $a['track_number'] !== null ? (int) $a['track_number'] : 9999;
			$tb = isset( $b['track_number'] ) && $b['track_number'] !== null ? (int) $b['track_number'] : 9999;
			if ( $ta !== $tb ) {
				return $ta - $tb;
			}
			$sa = isset( $a['set_order'] ) ? (int) $a['set_order'] : 9999;
			$sb = isset( $b['set_order'] ) ? (int) $b['set_order'] : 9999;
			return $sa - $sb;
		} );
	}

	$result = array();
	foreach ( $album_groups as $g ) {
		$result[] = $g;
	}
	usort( $result, function( $a, $b ) {
		return ( (int) $b['count'] ) - ( (int) $a['count'] );
	} );
	if ( $groups['__others']['count'] > 0 ) {
		$groups['__others']['thumbnail_url'] = null;
		$result[] = $groups['__others'];
	}
	if ( $groups['__covers']['count'] > 0 ) {
		$groups['__covers']['thumbnail_url'] = null;
		$result[] = $groups['__covers'];
	}

	set_transient( $cache_key, array( 'groups' => $result, 'total_entries' => $total_entries ), HOUR_IN_SECONDS );
	return array( 'groups' => $result, 'total_entries' => $total_entries );
}

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
		delete_transient( 'jww_tour_album_stats_' . $tid );
		delete_transient( 'jww_tour_cities_count_' . $tid );
		delete_transient( 'jww_tour_venues_count_' . $tid );
		delete_transient( 'jww_tour_opener_closer_' . $tid );
		delete_transient( 'jww_tour_festivals_count_' . $tid );
	}
	if ( isset( $GLOBALS['jww_old_tour_by_show'][ $post_id ] ) ) {
		unset( $GLOBALS['jww_old_tour_by_show'][ $post_id ] );
	}
}
add_action( 'save_post', 'jww_clear_tour_song_counts_on_show_save', 26 );
