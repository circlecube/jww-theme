<?php
/**
 * Render callback for the Song Live Statistics block
 * 
 * Displays a single statistic for a song
 */

// Get block attributes with defaults
$stat_type = $attributes['statType'] ?? 'play_count';
$song_id = $attributes['songId'] ?? 0;

// If no song ID specified, try to get from context (single-song.php)
if ( ! $song_id && get_post_type() === 'song' ) {
	$song_id = get_the_ID();
}

if ( ! $song_id ) {
	echo '<p>Please select a song to display statistics.</p>';
	return;
}

// Ensure show-functions.php is loaded
if ( ! function_exists( 'jww_get_song_play_count' ) ) {
	echo '<p>Statistics functions not available.</p>';
	return;
}

$play_count = jww_get_song_play_count( $song_id );
$last_played = jww_get_song_last_played( $song_id );
$first_played = jww_get_song_first_played( $song_id );
$gap_analysis = jww_get_song_gap_analysis( $song_id );

$wrapper_class = 'song-live-stats-block stat-type-' . esc_attr( $stat_type );
echo '<div class="' . esc_attr( $wrapper_class ) . '">';

// Display the selected stat
switch ( $stat_type ) {
	case 'play_count':
		if ( $play_count === 0 ) {
			echo '<div class="stat-item stat-play-count">';
			echo '<div class="stat-label">Total Times Played</div>';
			echo '<div class="stat-value">0</div>';
			echo '<div class="stat-note">This song has not been played live yet.</div>';
			echo '</div>';
		} else {
			echo '<div class="stat-item stat-play-count">';
			echo '<div class="stat-label">Total Times Played</div>';
			echo '<div class="stat-value">' . esc_html( $play_count ) . '</div>';
			echo '</div>';
		}
		break;

	case 'last_played':
		if ( $last_played ) {
			$show_title = get_the_title( $last_played['show_id'] );
			echo '<div class="stat-item stat-last-played">';
			echo '<div class="stat-label">Last Played</div>';
			echo '<div class="stat-value">';
			echo '<a href="' . esc_url( $last_played['show_link'] ) . '">' . esc_html( $show_title ) . '</a>';
			echo '</div>';
			echo '</div>';
		} else {
			echo '<div class="stat-item stat-last-played">';
			echo '<div class="stat-label">Last Played</div>';
			echo '<div class="stat-value">—</div>';
			echo '<div class="stat-note">This song has not been played live yet.</div>';
			echo '</div>';
		}
		break;

	case 'first_played':
		if ( $first_played ) {
			$show_title = get_the_title( $first_played['show_id'] );
			echo '<div class="stat-item stat-first-played">';
			echo '<div class="stat-label">First Played</div>';
			echo '<div class="stat-value">';
			echo '<a href="' . esc_url( $first_played['show_link'] ) . '">' . esc_html( $show_title ) . '</a>';
			echo '</div>';
			echo '</div>';
		} else {
			echo '<div class="stat-item stat-first-played">';
			echo '<div class="stat-label">First Played</div>';
			echo '<div class="stat-value">—</div>';
			echo '<div class="stat-note">This song has not been played live yet.</div>';
			echo '</div>';
		}
		break;

	case 'days_since':
		if ( $gap_analysis && $gap_analysis['days_since'] > 0 ) {
			$days = $gap_analysis['days_since'];
			$years = floor( $days / 365 );
			$days_remaining = $days % 365;
			
			$time_string = '';
			if ( $years > 0 ) {
				$time_string = $years . ' year' . ( $years > 1 ? 's' : '' );
				if ( $days_remaining > 0 ) {
					$time_string .= ', ' . $days_remaining . ' day' . ( $days_remaining > 1 ? 's' : '' );
				}
			} else {
				$time_string = $days . ' day' . ( $days > 1 ? 's' : '' );
			}
			
			echo '<div class="stat-item stat-days-since">';
			echo '<div class="stat-label">Days Since Last Played</div>';
			echo '<div class="stat-value">' . esc_html( $time_string ) . '</div>';
			if ( $last_played ) {
				$show_title = get_the_title( $last_played['show_id'] );
				echo '<div class="stat-note">Last: <a href="' . esc_url( $last_played['show_link'] ) . '">' . esc_html( $show_title ) . '</a></div>';
			}
			echo '</div>';
		} else {
			echo '<div class="stat-item stat-days-since">';
			echo '<div class="stat-label">Days Since Last Played</div>';
			echo '<div class="stat-value">—</div>';
			echo '<div class="stat-note">This song has not been played live yet.</div>';
			echo '</div>';
		}
		break;
}

echo '</div>'; // .song-live-stats-block
