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
	?>
	<div class="wp-block-group alignwide song-play-history-table-wrapper">
		<table class="song-play-history-table sortable-table" data-table-type="play-history">
			<thead>
				<tr>
					<th class="sortable" data-sort="date" data-sort-type="date"><?php esc_html_e( 'Date', 'jww-theme' ); ?> <span class="sort-indicator"></span></th>
					<th class="sortable" data-sort="location" data-sort-type="text"><?php esc_html_e( 'Location', 'jww-theme' ); ?> <span class="sort-indicator"></span></th>
					<th class="sortable" data-sort="venue" data-sort-type="text"><?php esc_html_e( 'Venue', 'jww-theme' ); ?> <span class="sort-indicator"></span></th>
					<th class="sortable" data-sort="position" data-sort-type="number"><?php esc_html_e( 'Set position', 'jww-theme' ); ?> <span class="sort-indicator"></span></th>
					<th class="sortable" data-sort="days" data-sort-type="number"><?php esc_html_e( 'Days Since Last Played', 'jww-theme' ); ?> <span class="sort-indicator"></span></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $performances as $p ) {
					$date_raw = $p['post_date'];
					$date_display = get_the_date( 'M j, Y', $p['show_id'] );
					$loc = $p['location_data'];
					$city_country_plain = wp_strip_all_tags( $loc['city_country'] );
					$days_display = $p['days_since_previous'] !== null ? (string) $p['days_since_previous'] : '—';
					$days_sort = $p['days_since_previous'] !== null ? $p['days_since_previous'] : -1;
					?>
					<tr>
						<td data-sort-value="<?php echo esc_attr( $date_raw ); ?>">
							<a href="<?php echo esc_url( $p['show_link'] ); ?>"><?php echo esc_html( $date_display ); ?></a>
						</td>
						<td data-sort-value="<?php echo esc_attr( strtolower( $city_country_plain ) ); ?>">
							<?php echo $loc['city_country'] ? $loc['city_country'] : '<span class="empty-cell">—</span>'; ?>
						</td>
						<td data-sort-value="<?php echo esc_attr( strtolower( $loc['venue'] ) ); ?>">
							<?php
							if ( $loc['venue'] ) {
								if ( $loc['venue_link'] && ! is_wp_error( $loc['venue_link'] ) ) {
									echo '<a href="' . esc_url( $loc['venue_link'] ) . '">' . esc_html( $loc['venue'] ) . '</a>';
								} else {
									echo esc_html( $loc['venue'] );
								}
							} else {
								echo '<span class="empty-cell">—</span>';
							}
							?>
						</td>
						<td data-sort-value="<?php echo esc_attr( $p['set_position'] ); ?>">
							<a href="<?php echo esc_url( $p['show_link'] ); ?>"><?php echo esc_html( $p['set_position'] ); ?></a>
						</td>
						<td data-sort-value="<?php echo esc_attr( $days_sort ); ?>"><?php echo esc_html( $days_display ); ?></td>
					</tr>
				<?php } ?>
			</tbody>
		</table>
	</div>
	<?php
} else {
	// List (same structure as old Recent Shows)
	echo '<div class="stat-item stat-recent-shows">';
	echo '<div class="stat-label">' . esc_html__( 'Play History', 'jww-theme' ) . '</div>';
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
		echo '<li>';
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
		echo '</li>';
	}
	echo '</ul>';
	echo '</div>';
	echo '</div>';
}

echo '</div>';
