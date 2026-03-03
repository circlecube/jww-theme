<?php
/**
 * Render callback for the Song Play History block
 * Displays all times a song was played: list or sortable table.
 */

$display_mode = $attributes['displayMode'] ?? 'list';
$song_id      = isset( $attributes['songId'] ) ? (int) $attributes['songId'] : 0;
$limit        = isset( $attributes['limit'] ) ? (int) $attributes['limit'] : 0;

if ( ! $song_id && get_post_type() === 'song' ) {
	$song_id = get_the_ID();
}

if ( ! $song_id ) {
	echo '<p class="song-play-history-block">' . esc_html__( 'Please select a song to display play history.', 'jww-theme' ) . '</p>';
	return;
}

if ( ! function_exists( 'jww_get_song_all_performances' ) ) {
	echo '<p class="song-play-history-block">' . esc_html__( 'Statistics functions not available.', 'jww-theme' ) . '</p>';
	return;
}

$performances = jww_get_song_all_performances( $song_id );
if ( $limit > 0 ) {
	$performances = array_slice( $performances, 0, $limit );
}

$wrapper_class = 'song-play-history-block display-' . esc_attr( $display_mode );
echo '<div class="' . esc_attr( $wrapper_class ) . '">';

if ( empty( $performances ) ) {
	echo '<p class="no-performances">' . esc_html__( 'This song has not been played live yet.', 'jww-theme' ) . '</p>';
	echo '</div>';
	return;
}

if ( $display_mode === 'table' ) {
	if ( function_exists( 'jww_render_play_history_table_card' ) ) {
		jww_render_play_history_table_card( $performances, array(
			'title'        => __( 'Song Performances', 'jww-theme' ),
			'default_open' => true,
		) );
	}
} else {
	// List (same structure as old Recent Shows)
	echo '<div class="stat-item stat-recent-shows">';
	echo '<div class="stat-label">' . esc_html__( 'Song Performances', 'jww-theme' ) . '</div>';
	echo '<div class="recent-shows-card">';
	echo '<ul class="recent-shows-list">';
	foreach ( $performances as $p ) {
		$tour_id = get_field( 'show_tour', $p['show_id'] );
		$tour_name = '';
		$tour_link = '';
		if ( $tour_id ) {
			$tour_term = get_term( $tour_id, 'tour' );
			if ( $tour_term && ! is_wp_error( $tour_term ) ) {
				$tour_name = $tour_term->name;
				$tour_link = get_term_link( $tour_term->term_id, 'tour' );
			}
		}
		$loc = $p['location_data'];
		$location_name = trim( wp_strip_all_tags( $loc['city_country'] ) . ( $loc['venue'] ? ' · ' . $loc['venue'] : '' ), ' ·' );
		$venue_img_id = function_exists( 'jww_get_venue_image_id' ) ? jww_get_venue_image_id( $p['location_id'] ) : 0;
		echo '<li class="recent-show-item-with-venue">';
		if ( $venue_img_id ) {
			echo '<a href="' . esc_url( $p['show_link'] ) . '" class="song-play-history-venue-link">';
			echo wp_get_attachment_image( $venue_img_id, 'medium', false, array( 'class' => 'song-play-history-venue-image', 'loading' => 'lazy', 'decoding' => 'async' ) );
			echo '</a>';
		}
		echo '<div class="recent-show-item-content">';
		echo '<a href="' . esc_url( $p['show_link'] ) . '">' . esc_html( $p['show_title'] ) . '</a>';
		if ( $location_name || $tour_name ) {
			echo '<div class="show-meta">';
			if ( $location_name ) {
				echo '<span class="show-location">' . esc_html( $location_name ) . '</span>';
			}
			if ( $tour_name ) {
				echo '<span class="show-tour">';
				if ( $tour_link && ! is_wp_error( $tour_link ) ) {
					echo '<a href="' . esc_url( $tour_link ) . '">' . esc_html( $tour_name ) . '</a>';
				} else {
					echo esc_html( $tour_name );
				}
				echo '</span>';
			}
			echo '</div>';
		}
		echo '</div>';
		echo '</li>';
	}
	echo '</ul>';
	echo '</div>';
	echo '</div>';
}

echo '</div>';
