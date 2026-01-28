<?php
/**
 * Render callback for the Song History Chart block
 * 
 * Displays when a song was played over time
 */

// Get block attributes with defaults
$song_id = $attributes['songId'] ?? 0;
$limit = $attributes['limit'] ?? 50;
$chart_type = $attributes['chartType'] ?? 'list';

// If no song ID specified, try to get from context (single-song.php)
if ( ! $song_id && get_post_type() === 'song' ) {
	$song_id = get_the_ID();
}

if ( ! $song_id ) {
	echo '<p>Please select a song to display the history chart.</p>';
	return;
}

// Ensure show-functions.php is loaded
if ( ! function_exists( 'jww_get_song_play_count' ) ) {
	echo '<p>Song functions not available.</p>';
	return;
}

// Get all shows where this song was played
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
$play_history = array();

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
		
		$play_history[] = array(
			'show'          => $show,
			'show_id'       => $show->ID,
			'show_date'     => get_the_date( 'F j, Y', $show->ID ),
			'show_date_raw' => get_the_date( 'Y-m-d', $show->ID ),
			'show_link'     => get_permalink( $show->ID ),
			'location_name' => $location_name,
		);
	}
}

if ( empty( $play_history ) ) {
	echo '<p>This song has not been played live yet.</p>';
	return;
}

// Limit results
$play_history = array_slice( $play_history, 0, $limit );

$song_title = get_the_title( $song_id );
$wrapper_class = 'song-history-chart-block chart-type-' . esc_attr( $chart_type );

echo '<div class="' . esc_attr( $wrapper_class ) . '">';
echo '<h2 class="wp-block-heading">Play History: ' . esc_html( $song_title ) . '</h2>';

if ( $chart_type === 'timeline' ) {
	// Timeline view
	echo '<div class="history-timeline">';
	foreach ( $play_history as $index => $play ) {
		$is_last = ( $index === count( $play_history ) - 1 );
		echo '<div class="timeline-item">';
		echo '<div class="timeline-marker"></div>';
		echo '<div class="timeline-content">';
		echo '<div class="timeline-date">' . esc_html( $play['show_date'] ) . '</div>';
		echo '<h3 class="timeline-title"><a href="' . esc_url( $play['show_link'] ) . '">Show</a></h3>';
		if ( $play['location_name'] ) {
			echo '<div class="timeline-location">' . esc_html( $play['location_name'] ) . '</div>';
		}
		echo '</div>';
		echo '</div>';
	}
	echo '</div>';
} else {
	// List view
	echo '<div class="history-list">';
	echo '<ul class="play-history-list">';
	foreach ( $play_history as $play ) {
		echo '<li>';
		echo '<a href="' . esc_url( $play['show_link'] ) . '">' . esc_html( $play['show_date'] ) . '</a>';
		if ( $play['location_name'] ) {
			echo ' <span class="play-location">— ' . esc_html( $play['location_name'] ) . '</span>';
		}
		echo '</li>';
	}
	echo '</ul>';
	echo '</div>';
}

echo '</div>'; // .song-history-chart-block
