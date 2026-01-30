<?php
/**
 * Render callback for the Song Stats Table block
 * Displays all songs with live stats in a sortable table.
 */

if ( ! function_exists( 'jww_get_all_time_song_stats' ) ) {
	echo '<p class="song-stats-table-block">' . esc_html__( 'Statistics functions not available.', 'jww-theme' ) . '</p>';
	return;
}

$stats = jww_get_all_time_song_stats( true );

?>
<div class="song-stats-table-block wp-block-group alignwide">
	<div class="song-stats-table-wrapper">
		<table class="song-stats-table sortable-table" data-table-type="song-stats">
			<thead>
				<tr>
					<th class="song-stats-thumb-col"><?php esc_html_e( '', 'jww-theme' ); ?></th>
					<th class="sortable" data-sort="title" data-sort-type="text"><?php esc_html_e( 'Song', 'jww-theme' ); ?> <span class="sort-indicator"></span></th>
					<th class="sortable" data-sort="published" data-sort-type="date"><?php esc_html_e( 'First Published', 'jww-theme' ); ?> <span class="sort-indicator"></span></th>
					<th class="sortable" data-sort="plays" data-sort-type="number"><?php esc_html_e( 'Times Played', 'jww-theme' ); ?> <span class="sort-indicator"></span></th>
					<th class="sortable" data-sort="first_date" data-sort-type="date"><?php esc_html_e( 'First Played', 'jww-theme' ); ?> <span class="sort-indicator"></span></th>
					<th class="song-stats-location-col"><?php esc_html_e( 'First Location / Venue', 'jww-theme' ); ?></th>
					<th class="sortable" data-sort="last_date" data-sort-type="date"><?php esc_html_e( 'Last Played', 'jww-theme' ); ?> <span class="sort-indicator"></span></th>
					<th class="song-stats-location-col"><?php esc_html_e( 'Last Location / Venue', 'jww-theme' ); ?></th>
					<th class="sortable" data-sort="days" data-sort-type="number"><?php esc_html_e( 'Days Since Last', 'jww-theme' ); ?> <span class="sort-indicator"></span></th>
				</tr>
			</thead>
			<tbody>
				<?php
				if ( empty( $stats ) ) {
					echo '<tr><td colspan="9">' . esc_html__( 'No songs with live performances yet.', 'jww-theme' ) . '</td></tr>';
				} else {
					foreach ( $stats as $row ) {
						$fp = $row['first_played'];
						$lp = $row['last_played'];
						$fp_loc = $fp['location_data'] ?? array( 'city_country' => '', 'venue' => '', 'venue_link' => '' );
						$lp_loc = $lp['location_data'] ?? array( 'city_country' => '', 'venue' => '', 'venue_link' => '' );
						$pub_date    = ! empty( $row['first_published'] ) ? $row['first_published'] : '';
						$pub_display = ! empty( $row['first_published'] ) ? date_i18n( 'M j, Y', strtotime( $row['first_published'] ) ) : '—';
						$thumb = get_the_post_thumbnail( $row['song_id'], array( 48, 48 ), array( 'class' => 'song-stats-thumb' ) );
						?>
						<tr>
							<td class="song-stats-thumb-col">
								<?php if ( $thumb ) : ?>
									<a href="<?php echo esc_url( $row['song_link'] ); ?>"><?php echo $thumb; ?></a>
								<?php else : ?>
									<span class="empty-cell">—</span>
								<?php endif; ?>
							</td>
							<td data-sort-value="<?php echo esc_attr( strtolower( $row['song_title'] ) ); ?>">
								<a href="<?php echo esc_url( $row['song_link'] ); ?>"><?php echo esc_html( $row['song_title'] ); ?></a>
							</td>
							<td data-sort-value="<?php echo esc_attr( $pub_date ); ?>"><?php echo esc_html( $pub_display ); ?></td>
							<td data-sort-value="<?php echo esc_attr( $row['play_count'] ); ?>"><?php echo esc_html( $row['play_count'] ); ?></td>
							<td data-sort-value="<?php echo esc_attr( get_the_date( 'Y-m-d', $fp['show_id'] ) ); ?>">
								<a href="<?php echo esc_url( $fp['show_link'] ); ?>"><?php echo esc_html( $fp['show_date'] ); ?></a>
							</td>
							<td class="song-stats-location-col">
								<?php
								if ( $fp_loc['city_country'] || $fp_loc['venue'] ) {
									echo $fp_loc['city_country'] ? $fp_loc['city_country'] : '';
									if ( $fp_loc['venue'] ) {
										echo $fp_loc['city_country'] ? ' · ' : '';
										if ( $fp_loc['venue_link'] && ! is_wp_error( $fp_loc['venue_link'] ) ) {
											echo '<a href="' . esc_url( $fp_loc['venue_link'] ) . '">' . esc_html( $fp_loc['venue'] ) . '</a>';
										} else {
											echo esc_html( $fp_loc['venue'] );
										}
									}
								} else {
									echo '<span class="empty-cell">—</span>';
								}
								?>
							</td>
							<td data-sort-value="<?php echo esc_attr( get_the_date( 'Y-m-d', $lp['show_id'] ) ); ?>">
								<a href="<?php echo esc_url( $lp['show_link'] ); ?>"><?php echo esc_html( $lp['show_date'] ); ?></a>
							</td>
							<td class="song-stats-location-col">
								<?php
								if ( $lp_loc['city_country'] || $lp_loc['venue'] ) {
									echo $lp_loc['city_country'] ? $lp_loc['city_country'] : '';
									if ( $lp_loc['venue'] ) {
										echo $lp_loc['city_country'] ? ' · ' : '';
										if ( $lp_loc['venue_link'] && ! is_wp_error( $lp_loc['venue_link'] ) ) {
											echo '<a href="' . esc_url( $lp_loc['venue_link'] ) . '">' . esc_html( $lp_loc['venue'] ) . '</a>';
										} else {
											echo esc_html( $lp_loc['venue'] );
										}
									}
								} else {
									echo '<span class="empty-cell">—</span>';
								}
								?>
							</td>
							<td data-sort-value="<?php echo esc_attr( $row['days_since_last_played'] ); ?>"><?php echo esc_html( $row['days_since_last_played'] ); ?></td>
						</tr>
						<?php
					}
				}
				?>
			</tbody>
		</table>
	</div>
</div>
