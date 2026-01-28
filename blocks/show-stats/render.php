<?php
/**
 * Render callback for the Show Statistics block
 */

// Get block attributes with defaults
$stat_type = $attributes['statType'] ?? 'song_plays';
$limit = $attributes['limit'] ?? 10;
$sort_by = $attributes['sortBy'] ?? 'count';

// Ensure show-functions.php is loaded
if ( ! function_exists( 'jww_get_all_time_song_stats' ) ) {
	echo '<p>Statistics functions not available.</p>';
	return;
}

$wrapper_class = 'show-stats-block show-stats-' . esc_attr( $stat_type );

echo '<div class="' . esc_attr( $wrapper_class ) . '">';

switch ( $stat_type ) {
	case 'song_plays':
		$stats = jww_get_all_time_song_stats();
		
		// Sort based on sortBy attribute
		if ( $sort_by === 'name' ) {
			usort( $stats, function( $a, $b ) {
				return strcmp( $a['song_title'], $b['song_title'] );
			} );
		} elseif ( $sort_by === 'date' ) {
			usort( $stats, function( $a, $b ) {
				$a_date = $a['last_played'] ? strtotime( $a['last_played']['show']->post_date ) : 0;
				$b_date = $b['last_played'] ? strtotime( $b['last_played']['show']->post_date ) : 0;
				return $b_date - $a_date; // Most recent first
			} );
		}
		// Default is already sorted by count
		
		$stats = array_slice( $stats, 0, $limit );
		
		echo '<h3>All-Time Song Play Counts</h3>';
		echo '<ul class="song-play-stats">';
		foreach ( $stats as $stat ) {
			echo '<li>';
			echo '<a href="' . esc_url( $stat['song_link'] ) . '">' . esc_html( $stat['song_title'] ) . '</a>';
			echo ' <span class="play-count">(' . esc_html( $stat['play_count'] ) . ' times)</span>';
			if ( $stat['last_played'] ) {
				echo ' <span class="last-played">Last: <a href="' . esc_url( $stat['last_played']['show_link'] ) . '">' . esc_html( $stat['last_played']['show_date'] ) . '</a></span>';
			}
			echo '</li>';
		}
		echo '</ul>';
		break;
		
	case 'gap_analysis':
		$songs = get_posts( array(
			'post_type'      => 'song',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		) );
		
		$gaps = array();
		foreach ( $songs as $song ) {
			$gap = jww_get_song_gap_analysis( $song->ID );
			if ( $gap ) {
				$gaps[] = array(
					'song_id'     => $song->ID,
					'song_title'  => get_the_title( $song->ID ),
					'song_link'   => get_permalink( $song->ID ),
					'days_since'  => $gap['days_since'],
					'play_count'  => $gap['play_count'],
					'last_played' => $gap['last_played'],
				);
			}
		}
		
		// Sort by days since (longest gap first)
		usort( $gaps, function( $a, $b ) {
			return $b['days_since'] - $a['days_since'];
		} );
		
		$gaps = array_slice( $gaps, 0, $limit );
		
		echo '<h3>Songs with Longest Gaps Since Last Played</h3>';
		echo '<ul class="gap-analysis-stats">';
		foreach ( $gaps as $gap ) {
			$years = floor( $gap['days_since'] / 365 );
			$days = $gap['days_since'] % 365;
			$time_string = '';
			if ( $years > 0 ) {
				$time_string = $years . ' year' . ( $years > 1 ? 's' : '' );
				if ( $days > 0 ) {
					$time_string .= ', ' . $days . ' day' . ( $days > 1 ? 's' : '' );
				}
			} else {
				$time_string = $gap['days_since'] . ' day' . ( $gap['days_since'] > 1 ? 's' : '' );
			}
			
			echo '<li>';
			echo '<a href="' . esc_url( $gap['song_link'] ) . '">' . esc_html( $gap['song_title'] ) . '</a>';
			echo ' <span class="gap-time">' . esc_html( $time_string ) . ' ago</span>';
			echo ' <span class="play-count">(' . esc_html( $gap['play_count'] ) . ' total plays)</span>';
			if ( $gap['last_played'] ) {
				echo ' <span class="last-played">Last: <a href="' . esc_url( $gap['last_played']['show_link'] ) . '">' . esc_html( $gap['last_played']['show_date'] ) . '</a></span>';
			}
			echo '</li>';
		}
		echo '</ul>';
		break;
		
	case 'venue_stats':
		// Get all shows
		$shows = get_posts( array(
			'post_type'      => 'show',
			'posts_per_page' => -1,
			'post_status'    => array( 'publish', 'future' ),
		) );
		
		$venue_stats = array();
		foreach ( $shows as $show ) {
			$location_id = get_field( 'show_location', $show->ID );
			if ( ! $location_id ) {
				continue;
			}
			
			$location_term = get_term( $location_id, 'location' );
			if ( ! $location_term || is_wp_error( $location_term ) ) {
				continue;
			}
			
			$venue_key = $location_term->term_id;
			if ( ! isset( $venue_stats[ $venue_key ] ) ) {
				$venue_stats[ $venue_key ] = array(
					'venue_id'    => $location_term->term_id,
					'venue_name'  => $location_term->name,
					'venue_link'  => get_term_link( $location_term->term_id, 'location' ),
					'show_count'  => 0,
					'song_count'  => 0,
				);
			}
			
			$venue_stats[ $venue_key ]['show_count']++;
			
			// Count songs in setlist
			$setlist = get_field( 'setlist', $show->ID );
			if ( $setlist && is_array( $setlist ) ) {
				foreach ( $setlist as $item ) {
					if ( isset( $item['entry_type'] ) && ( $item['entry_type'] === 'song-post' || $item['entry_type'] === 'song-text' ) ) {
						$venue_stats[ $venue_key ]['song_count']++;
					}
				}
			}
		}
		
		// Sort based on sortBy
		if ( $sort_by === 'name' ) {
			uasort( $venue_stats, function( $a, $b ) {
				return strcmp( $a['venue_name'], $b['venue_name'] );
			} );
		} else {
			// Sort by show count
			uasort( $venue_stats, function( $a, $b ) {
				return $b['show_count'] - $a['show_count'];
			} );
		}
		
		$venue_stats = array_slice( $venue_stats, 0, $limit );
		
		echo '<h3>Venue Statistics</h3>';
		echo '<ul class="venue-stats">';
		foreach ( $venue_stats as $stat ) {
			echo '<li>';
			echo '<a href="' . esc_url( $stat['venue_link'] ) . '">' . esc_html( $stat['venue_name'] ) . '</a>';
			echo ' <span class="show-count">' . esc_html( $stat['show_count'] ) . ' show' . ( $stat['show_count'] > 1 ? 's' : '' ) . '</span>';
			echo ' <span class="song-count">(' . esc_html( $stat['song_count'] ) . ' total songs)</span>';
			echo '</li>';
		}
		echo '</ul>';
		break;
		
	case 'tour_stats':
		// Get all tours
		$tours = get_terms( array(
			'taxonomy'   => 'tour',
			'hide_empty' => false,
		) );
		
		if ( is_wp_error( $tours ) || empty( $tours ) ) {
			echo '<p>No tours found.</p>';
			break;
		}
		
		$tour_stats = array();
		foreach ( $tours as $tour ) {
			$shows = jww_get_shows_by_tour( $tour->term_id );
			$song_count = 0;
			$unique_songs = array();
			
			foreach ( $shows as $show ) {
				$setlist = get_field( 'setlist', $show->ID );
				if ( $setlist && is_array( $setlist ) ) {
					foreach ( $setlist as $item ) {
						if ( isset( $item['entry_type'] ) && $item['entry_type'] === 'song-post' && ! empty( $item['song'] ) ) {
							$song = is_array( $item['song'] ) ? $item['song'][0] : $item['song'];
							$song_id = is_object( $song ) ? $song->ID : $song;
							$unique_songs[ $song_id ] = true;
							$song_count++;
						} elseif ( isset( $item['entry_type'] ) && $item['entry_type'] === 'song-text' && ! empty( $item['song_text'] ) ) {
							$song_count++;
						}
					}
				}
			}
			
			$tour_stats[] = array(
				'tour_id'      => $tour->term_id,
				'tour_name'    => $tour->name,
				'tour_link'    => get_term_link( $tour->term_id, 'tour' ),
				'show_count'   => count( $shows ),
				'song_count'   => $song_count,
				'unique_songs' => count( $unique_songs ),
			);
		}
		
		// Sort based on sortBy
		if ( $sort_by === 'name' ) {
			usort( $tour_stats, function( $a, $b ) {
				return strcmp( $a['tour_name'], $b['tour_name'] );
			} );
		} else {
			// Sort by show count
			usort( $tour_stats, function( $a, $b ) {
				return $b['show_count'] - $a['show_count'];
			} );
		}
		
		$tour_stats = array_slice( $tour_stats, 0, $limit );
		
		echo '<h3>Tour Statistics</h3>';
		echo '<ul class="tour-stats">';
		foreach ( $tour_stats as $stat ) {
			echo '<li>';
			echo '<a href="' . esc_url( $stat['tour_link'] ) . '">' . esc_html( $stat['tour_name'] ) . '</a>';
			echo ' <span class="show-count">' . esc_html( $stat['show_count'] ) . ' show' . ( $stat['show_count'] > 1 ? 's' : '' ) . '</span>';
			echo ' <span class="song-count">(' . esc_html( $stat['unique_songs'] ) . ' unique songs, ' . esc_html( $stat['song_count'] ) . ' total plays)</span>';
			echo '</li>';
		}
		echo '</ul>';
		break;
}

echo '</div>';
