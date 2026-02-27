<?php
/**
 * Renders a shows table inside a card and accordion.
 * Used by taxonomy-tour.php, taxonomy-location.php, and archive-show.php.
 *
 * @param WP_Post[] $shows   Array of show posts.
 * @param array     $args    {
 *     @type string $title             Section heading (e.g. "Upcoming Shows", "Past Shows", "Shows").
 *     @type string $table_type        'upcoming' or 'past'. Upcoming has Tickets column; past has Songs column.
 *     @type bool   $show_tour_column  Whether to include the Tour column (false on taxonomy-tour).
 *     @type bool   $default_open      Whether the accordion is open by default.
 *     @type string $accordion_id      Optional. ID for the <details> element (for deep links and open-on-click).
 * }
 */
function jww_render_shows_table_card( array $shows, array $args = array() ) {
	if ( empty( $shows ) ) {
		return;
	}

	$args = wp_parse_args( $args, array(
		'title'             => 'Shows',
		'table_type'        => 'past',
		'show_tour_column'  => true,
		'default_open'      => false,
		'accordion_id'      => '',
	) );

	$title             = $args['title'];
	$table_type        = $args['table_type'];
	$show_tour_column  = (bool) $args['show_tour_column'];
	$default_open      = (bool) $args['default_open'];
	$accordion_id      = sanitize_key( (string) $args['accordion_id'] );
	$is_upcoming       = ( $table_type === 'upcoming' );
	$count             = count( $shows );

	// Prime meta cache for all shows
	$show_ids = wp_list_pluck( $shows, 'ID' );
	if ( ! empty( $show_ids ) && function_exists( 'update_post_meta_cache' ) ) {
		update_post_meta_cache( $show_ids );
	}

	$accordion_class = 'shows-accordion';
	$details_attr   = $default_open ? ' open' : '';
	if ( $accordion_id !== '' ) {
		$details_attr .= ' id="' . esc_attr( $accordion_id ) . '"';
	}
	?>
	<div class="wp-block-group alignwide shows-table-card" style="margin-bottom:var(--wp--preset--spacing--50);">
		<details class="<?php echo esc_attr( $accordion_class ); ?>"<?php echo $details_attr; ?>>
			<summary class="shows-accordion-summary">
				<h2 class="wp-block-heading" style="margin:0;display:inline;"><?php echo esc_html( $title ); ?></h2>
				<span class="shows-accordion-count"><?php echo esc_html( '(' . $count . ')' ); ?></span>
			</summary>
			<div class="shows-table-wrapper">
				<table class="shows-table sortable-table" data-table-type="<?php echo esc_attr( $table_type ); ?>">
					<thead>
						<tr>
							<th class="sortable" data-sort="title" data-sort-type="text">
								Show <span class="sort-indicator"></span>
							</th>
							<th class="sortable" data-sort="date" data-sort-type="date">
								Date <span class="sort-indicator"></span>
							</th>
							<th class="sortable" data-sort="city" data-sort-type="text">
								City/Country <span class="sort-indicator"></span>
							</th>
							<th class="sortable" data-sort="venue" data-sort-type="text">
								Venue <span class="sort-indicator"></span>
							</th>
							<?php if ( $is_upcoming ) : ?>
								<?php if ( $show_tour_column ) : ?>
									<th class="sortable" data-sort="tour" data-sort-type="text">
										Tour <span class="sort-indicator"></span>
									</th>
								<?php endif; ?>
								<th>Tickets</th>
							<?php else : ?>
								<th class="sortable" data-sort="songs" data-sort-type="number">
									Songs <span class="sort-indicator"></span>
								</th>
								<?php if ( $show_tour_column ) : ?>
									<th class="sortable" data-sort="tour" data-sort-type="text">
										Tour <span class="sort-indicator"></span>
									</th>
								<?php endif; ?>
							<?php endif; ?>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $shows as $show ) {
							jww_render_shows_table_row( $show, $table_type, $show_tour_column );
						}
						?>
					</tbody>
				</table>
			</div>
		</details>
	</div>
	<?php
}

/**
 * Renders a single show row for the shows table (used by jww_render_shows_table_card).
 *
 * @param WP_Post $show             Show post.
 * @param string  $table_type       'upcoming' or 'past'.
 * @param bool    $show_tour_column Whether to output the Tour cell.
 */
function jww_render_shows_table_row( $show, $table_type = 'past', $show_tour_column = true ) {
	$show_id       = $show->ID;
	$show_title    = get_the_title( $show_id );
	$show_date     = get_the_date( 'M j, Y', $show_id );
	$show_date_raw = get_the_date( 'Y-m-d', $show_id );
	$show_link     = get_permalink( $show_id );

	$fields      = get_fields( $show_id );
	$location_id = isset( $fields['show_location'] ) ? $fields['show_location'] : 0;
	$location_data = jww_get_location_hierarchy( $location_id );

	$tour_name = '';
	$tour_link = '';
	if ( $show_tour_column ) {
		$tour_id = isset( $fields['show_tour'] ) ? $fields['show_tour'] : 0;
		if ( $tour_id ) {
			$tour_term = get_term( $tour_id, 'tour' );
			if ( $tour_term && ! is_wp_error( $tour_term ) ) {
				$tour_name = $tour_term->name;
				$tour_link = get_term_link( $tour_term->term_id, 'tour' );
			}
		}
		// When no tour term, show festival name (field) if set; link to the show.
		if ( $tour_name === '' && ! empty( $fields['show_festival_name'] ) ) {
			$tour_name = trim( (string) $fields['show_festival_name'] );
			if ( $tour_name !== '' ) {
				$tour_link = get_permalink( $show_id );
			}
		}
	}

	$venue_image_id = function_exists( 'jww_get_venue_image_id' ) ? jww_get_venue_image_id( $location_id ) : 0;
	?>
	<tr>
		<td data-sort-value="<?php echo esc_attr( strtolower( $show_title ) ); ?>">
			<a href="<?php echo esc_url( $show_link ); ?>"><?php echo esc_html( $show_title ); ?></a>
		</td>
		<td data-sort-value="<?php echo esc_attr( $show_date_raw ); ?>">
			<a href="<?php echo esc_url( $show_link ); ?>"><?php echo esc_html( $show_date ); ?></a>
		</td>
		<td data-sort-value="<?php echo esc_attr( strtolower( strip_tags( $location_data['city_country'] ) ) ); ?>">
			<?php if ( $location_data['city_country'] ) : ?>
				<?php echo $location_data['city_country']; // Already escaped in function ?>
			<?php else : ?>
				<span class="empty-cell">—</span>
			<?php endif; ?>
		</td>
		<td data-sort-value="<?php echo esc_attr( strtolower( $location_data['venue'] ) ); ?>">
			<?php if ( $location_data['venue'] || $venue_image_id ) : ?>
				<span class="shows-table-venue-cell">
					<?php if ( $venue_image_id ) :
						echo wp_get_attachment_image( $venue_image_id, 'thumbnail', false, array( 'class' => 'shows-table-venue-img', 'loading' => 'lazy', 'decoding' => 'async' ) );
					endif;
					if ( $location_data['venue'] ) : ?>
						<span class="shows-table-venue-name">
							<?php if ( $location_data['venue_link'] && ! is_wp_error( $location_data['venue_link'] ) ) : ?>
								<a href="<?php echo esc_url( $location_data['venue_link'] ); ?>"><?php echo esc_html( $location_data['venue'] ); ?></a>
							<?php else : ?>
								<?php echo esc_html( $location_data['venue'] ); ?>
							<?php endif; ?>
						</span>
					<?php endif; ?>
				</span>
			<?php else : ?>
				<span class="empty-cell">—</span>
			<?php endif; ?>
		</td>
		<?php if ( $table_type === 'upcoming' ) : ?>
			<?php if ( $show_tour_column ) : ?>
				<td data-sort-value="<?php echo esc_attr( strtolower( $tour_name ) ); ?>">
					<?php if ( $tour_name ) : ?>
						<?php if ( $tour_link && ! is_wp_error( $tour_link ) ) : ?>
							<a href="<?php echo esc_url( $tour_link ); ?>"><?php echo esc_html( $tour_name ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $tour_name ); ?>
						<?php endif; ?>
					<?php else : ?>
						<span class="empty-cell">—</span>
					<?php endif; ?>
				</td>
			<?php endif; ?>
			<td>
				<?php
				$ticket_link = isset( $fields['ticket_link'] ) ? $fields['ticket_link'] : '';
				if ( $ticket_link ) : ?>
					<a href="<?php echo esc_url( $ticket_link ); ?>" target="_blank" rel="noopener" class="ticket-link">Get Tickets</a>
				<?php else : ?>
					<span class="empty-cell">—</span>
				<?php endif; ?>
			</td>
		<?php else : ?>
			<?php
			$setlist           = isset( $fields['setlist'] ) ? $fields['setlist'] : array();
			$song_count        = jww_count_setlist_songs( $setlist );
			$song_count_display = $song_count > 0 ? (string) $song_count : '?';
			$song_count_sort   = $song_count > 0 ? $song_count : -1;
			$song_count_title  = $song_count > 0 ? '' : __( 'Setlist not added yet; will update when available.', 'jww-theme' );
			?>
			<td data-sort-value="<?php echo esc_attr( $song_count_sort ); ?>">
				<a href="<?php echo esc_url( $show_link ); ?>"<?php echo $song_count_title ? ' title="' . esc_attr( $song_count_title ) . '"' : ''; ?>><?php echo esc_html( $song_count_display ); ?></a>
			</td>
			<?php if ( $show_tour_column ) : ?>
				<td data-sort-value="<?php echo esc_attr( strtolower( $tour_name ) ); ?>">
					<?php if ( $tour_name ) : ?>
						<?php if ( $tour_link && ! is_wp_error( $tour_link ) ) : ?>
							<a href="<?php echo esc_url( $tour_link ); ?>"><?php echo esc_html( $tour_name ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $tour_name ); ?>
						<?php endif; ?>
					<?php else : ?>
						<span class="empty-cell">—</span>
					<?php endif; ?>
				</td>
			<?php endif; ?>
		<?php endif; ?>
	</tr>
	<?php
}

/**
 * Renders the play history table (song performances) inside the same card + accordion structure.
 * Used by the Song Play History block in table mode. Columns: Date, Location, Venue, Set position, Days Since Last Played.
 *
 * @param array[] $performances Array of performance items from jww_get_song_all_performances (show_id, post_date, show_link, show_title, location_id, set_position, days_since_previous, location_data).
 * @param array   $args        {
 *     @type string $title        Section heading (default "Play history").
 *     @type bool   $default_open Whether the accordion is open by default.
 * }
 */
function jww_render_play_history_table_card( array $performances, array $args = array() ) {
	if ( empty( $performances ) ) {
		return;
	}

	$args = wp_parse_args( $args, array(
		'title'        => __( 'Play history', 'jww-theme' ),
		'default_open' => true,
	) );

	$title        = $args['title'];
	$default_open = (bool) $args['default_open'];
	$count        = count( $performances );
	$details_attr = $default_open ? ' open' : '';
	?>
	<div class="wp-block-group alignwide shows-table-card" style="margin-bottom:var(--wp--preset--spacing--50);">
		<details class="shows-accordion"<?php echo $details_attr; ?>>
			<summary class="shows-accordion-summary">
				<h2 class="wp-block-heading" style="margin:0;display:inline;"><?php echo esc_html( $title ); ?></h2>
				<span class="shows-accordion-count"><?php echo esc_html( '(' . $count . ')' ); ?></span>
			</summary>
			<div class="shows-table-wrapper">
				<table class="shows-table sortable-table" data-table-type="play-history">
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
							jww_render_play_history_table_row( $p );
						}
						?>
					</tbody>
				</table>
			</div>
		</details>
	</div>
	<?php
}

/**
 * Renders a single row for the play history table (used by jww_render_play_history_table_card).
 *
 * @param array $p Performance item (show_id, post_date, show_link, show_title, location_id, set_position, days_since_previous, location_data).
 */
function jww_render_play_history_table_row( array $p ) {
	$date_raw   = $p['post_date'];
	$date_fmt   = get_the_date( 'M j, Y', $p['show_id'] );
	$loc        = $p['location_data'];
	$city_plain = wp_strip_all_tags( $loc['city_country'] ?? '' );
	$days_display = $p['days_since_previous'] !== null ? (string) $p['days_since_previous'] : '—';
	$days_sort  = $p['days_since_previous'] !== null ? $p['days_since_previous'] : -1;
	$venue_img_id = function_exists( 'jww_get_venue_image_id' ) ? jww_get_venue_image_id( $p['location_id'] ) : 0;
	?>
	<tr>
		<td data-sort-value="<?php echo esc_attr( $date_raw ); ?>">
			<a href="<?php echo esc_url( $p['show_link'] ); ?>"><?php echo esc_html( $date_fmt ); ?></a>
		</td>
		<td data-sort-value="<?php echo esc_attr( strtolower( $city_plain ) ); ?>">
			<?php echo ! empty( $loc['city_country'] ) ? $loc['city_country'] : '<span class="empty-cell">—</span>'; ?>
		</td>
		<td data-sort-value="<?php echo esc_attr( strtolower( $loc['venue'] ?? '' ) ); ?>">
			<?php if ( ! empty( $loc['venue'] ) || $venue_img_id ) : ?>
				<span class="shows-table-venue-cell">
					<?php if ( $venue_img_id ) :
						echo wp_get_attachment_image( $venue_img_id, 'thumbnail', false, array( 'class' => 'shows-table-venue-img', 'loading' => 'lazy', 'decoding' => 'async' ) );
					endif;
					if ( ! empty( $loc['venue'] ) ) : ?>
						<span class="shows-table-venue-name">
							<?php if ( ! empty( $loc['venue_link'] ) && ! is_wp_error( $loc['venue_link'] ) ) : ?>
								<a href="<?php echo esc_url( $loc['venue_link'] ); ?>"><?php echo esc_html( $loc['venue'] ); ?></a>
							<?php else : ?>
								<?php echo esc_html( $loc['venue'] ); ?>
							<?php endif; ?>
						</span>
					<?php endif; ?>
				</span>
			<?php else : ?>
				<span class="empty-cell">—</span>
			<?php endif; ?>
		</td>
		<td data-sort-value="<?php echo esc_attr( $p['set_position'] ); ?>">
			<a href="<?php echo esc_url( $p['show_link'] ); ?>"><?php echo esc_html( (string) $p['set_position'] ); ?></a>
		</td>
		<td data-sort-value="<?php echo esc_attr( $days_sort ); ?>"><?php echo esc_html( $days_display ); ?></td>
	</tr>
	<?php
}