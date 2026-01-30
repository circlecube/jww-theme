<?php
/**
 * Template for displaying tour taxonomy archives
 * 
 * Template Name: Tour Archive
 * Displays shows for a specific tour in table format
 */

get_header();

// Get the tour term
$tour_term = get_queried_object();
if ( ! $tour_term || is_wp_error( $tour_term ) ) {
	get_footer();
	return;
}

// Get shows for this tour
$shows = jww_get_shows_by_tour( $tour_term->term_id );

// Separate upcoming and past shows
$current_time = current_time( 'timestamp' );
$upcoming_shows = array();
$past_shows = array();

foreach ( $shows as $show ) {
	$show_time = strtotime( $show->post_date );
	if ( $show_time > $current_time ) {
		$upcoming_shows[] = $show;
	} else {
		$past_shows[] = $show;
	}
}

// Sort upcoming shows ascending by date
usort( $upcoming_shows, function( $a, $b ) {
	return strtotime( $a->post_date ) - strtotime( $b->post_date );
} );

// Sort past shows descending by date
usort( $past_shows, function( $a, $b ) {
	return strtotime( $b->post_date ) - strtotime( $a->post_date );
} );

// Calculate statistics
$total_shows = count( $shows );
$upcoming_count = count( $upcoming_shows );
$past_count = count( $past_shows );

// Calculate tour statistics
$total_songs = 0;
$unique_songs = array();
foreach ( $shows as $show ) {
	$setlist = get_field( 'setlist', $show->ID );
	if ( $setlist && is_array( $setlist ) ) {
		foreach ( $setlist as $item ) {
			if ( isset( $item['entry_type'] ) && ( $item['entry_type'] === 'song-post' || $item['entry_type'] === 'song-text' ) ) {
				$total_songs++;
				if ( $item['entry_type'] === 'song-post' && ! empty( $item['song'] ) ) {
					$song = is_array( $item['song'] ) ? $item['song'][0] : $item['song'];
					$song_id = is_object( $song ) ? $song->ID : $song;
					$unique_songs[ $song_id ] = true;
				}
			}
		}
	}
}
?>

<main class="wp-block-group align is-layout-flow wp-block-group-is-layout-flow">
	<div
		class="wp-block-group has-global-padding is-layout-constrained wp-block-group-is-layout-constrained" 
		style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50)"
	>
		<h1 class="wp-block-post-title alignwide has-xxx-large-font-size">
			<?php echo esc_html( $tour_term->name ); ?> Tour
		</h1>
		
		<p class="back-link" style="margin-bottom:var(--wp--preset--spacing--30);">
			<a href="<?php echo esc_url( get_post_type_archive_link( 'show' ) ); ?>">← All Shows</a>
		</p>

		<!-- Statistics -->
		<div class="wp-block-group alignwide show-stats" style="margin-bottom:var(--wp--preset--spacing--40)">
			<div class="stats-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:var(--wp--preset--spacing--30)">
				<div class="stat-item">
					<strong><?php echo esc_html( $total_shows ); ?></strong>
					<div>Total Shows</div>
				</div>
				<div class="stat-item">
					<strong><?php echo esc_html( $upcoming_count ); ?></strong>
					<div>Upcoming</div>
				</div>
				<div class="stat-item">
					<strong><?php echo esc_html( $past_count ); ?></strong>
					<div>Past Shows</div>
				</div>
				<div class="stat-item">
					<strong><?php echo esc_html( count( $unique_songs ) ); ?></strong>
					<div>Unique Songs</div>
				</div>
				<div class="stat-item">
					<strong><?php echo esc_html( $total_songs ); ?></strong>
					<div>Total Song Plays</div>
				</div>
			</div>
		</div>

		<!-- Upcoming Shows Table -->
		<?php if ( ! empty( $upcoming_shows ) ): ?>
		<div class="wp-block-group alignwide shows-table-wrapper" style="margin-bottom:var(--wp--preset--spacing--50)">
			<h2 class="wp-block-heading">Upcoming Shows</h2>
			<table class="shows-table sortable-table" data-table-type="upcoming">
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
						<th>Tickets</th>
					</tr>
				</thead>
				<tbody>
					<?php 
					// Prime meta cache for all shows at once (when available)
					$upcoming_show_ids = wp_list_pluck( $upcoming_shows, 'ID' );
					if ( ! empty( $upcoming_show_ids ) && function_exists( 'update_post_meta_cache' ) ) {
						update_post_meta_cache( $upcoming_show_ids );
					}
					
					foreach ( $upcoming_shows as $show ): 
						$show_title = get_the_title( $show->ID );
						$show_date = get_the_date( 'M j, Y', $show->ID );
						$show_date_raw = get_the_date( 'Y-m-d', $show->ID );
						$show_link = get_permalink( $show->ID );
						
						// Use get_fields() to get all ACF fields at once (more efficient)
						$fields = get_fields( $show->ID );
						$location_id = isset( $fields['show_location'] ) ? $fields['show_location'] : 0;
						$ticket_link = isset( $fields['ticket_link'] ) ? $fields['ticket_link'] : '';
						
						$location_data = jww_get_location_hierarchy( $location_id );
					?>
						<tr>
							<td data-sort-value="<?php echo esc_attr( strtolower( $show_title ) ); ?>">
								<a href="<?php echo esc_url( $show_link ); ?>"><?php echo esc_html( $show_title ); ?></a>
							</td>
							<td data-sort-value="<?php echo esc_attr( $show_date_raw ); ?>">
								<a href="<?php echo esc_url( $show_link ); ?>"><?php echo esc_html( $show_date ); ?></a>
							</td>
							<td data-sort-value="<?php echo esc_attr( strtolower( strip_tags( $location_data['city_country'] ) ) ); ?>">
								<?php if ( $location_data['city_country'] ): ?>
									<?php echo $location_data['city_country']; // Already escaped in function ?>
								<?php else: ?>
									<span class="empty-cell">—</span>
								<?php endif; ?>
							</td>
							<td data-sort-value="<?php echo esc_attr( strtolower( $location_data['venue'] ) ); ?>">
								<?php if ( $location_data['venue'] ): ?>
									<?php if ( $location_data['venue_link'] && ! is_wp_error( $location_data['venue_link'] ) ): ?>
										<a href="<?php echo esc_url( $location_data['venue_link'] ); ?>"><?php echo esc_html( $location_data['venue'] ); ?></a>
									<?php else: ?>
										<?php echo esc_html( $location_data['venue'] ); ?>
									<?php endif; ?>
								<?php else: ?>
									<span class="empty-cell">—</span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $ticket_link ): ?>
									<a href="<?php echo esc_url( $ticket_link ); ?>" target="_blank" rel="noopener" class="ticket-link">Get Tickets</a>
								<?php else: ?>
									<span class="empty-cell">—</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php endif; ?>

		<!-- Past Shows Table -->
		<?php if ( ! empty( $past_shows ) ): ?>
		<div class="wp-block-group alignwide shows-table-wrapper">
			<h2 class="wp-block-heading">Past Shows</h2>
			<table class="shows-table sortable-table" data-table-type="past">
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
						<th class="sortable" data-sort="songs" data-sort-type="number">
							Songs <span class="sort-indicator"></span>
						</th>
					</tr>
				</thead>
				<tbody>
					<?php 
					// Prime meta cache for all shows at once (when available)
					$past_show_ids = wp_list_pluck( $past_shows, 'ID' );
					if ( ! empty( $past_show_ids ) && function_exists( 'update_post_meta_cache' ) ) {
						update_post_meta_cache( $past_show_ids );
					}
					
					foreach ( $past_shows as $show ): 
						$show_title = get_the_title( $show->ID );
						$show_date = get_the_date( 'M j, Y', $show->ID );
						$show_date_raw = get_the_date( 'Y-m-d', $show->ID );
						$show_link = get_permalink( $show->ID );
						
						// Use get_fields() to get all ACF fields at once (more efficient)
						$fields = get_fields( $show->ID );
						$location_id = isset( $fields['show_location'] ) ? $fields['show_location'] : 0;
						$setlist = isset( $fields['setlist'] ) ? $fields['setlist'] : array();
						$song_count = jww_count_setlist_songs( $setlist );
						
						$location_data = jww_get_location_hierarchy( $location_id );
					?>
						<tr>
							<td data-sort-value="<?php echo esc_attr( strtolower( $show_title ) ); ?>">
								<a href="<?php echo esc_url( $show_link ); ?>"><?php echo esc_html( $show_title ); ?></a>
							</td>
							<td data-sort-value="<?php echo esc_attr( $show_date_raw ); ?>">
								<a href="<?php echo esc_url( $show_link ); ?>"><?php echo esc_html( $show_date ); ?></a>
							</td>
							<td data-sort-value="<?php echo esc_attr( strtolower( strip_tags( $location_data['city_country'] ) ) ); ?>">
								<?php if ( $location_data['city_country'] ): ?>
									<?php echo $location_data['city_country']; // Already escaped in function ?>
								<?php else: ?>
									<span class="empty-cell">—</span>
								<?php endif; ?>
							</td>
							<td data-sort-value="<?php echo esc_attr( strtolower( $location_data['venue'] ) ); ?>">
								<?php if ( $location_data['venue'] ): ?>
									<?php if ( $location_data['venue_link'] && ! is_wp_error( $location_data['venue_link'] ) ): ?>
										<a href="<?php echo esc_url( $location_data['venue_link'] ); ?>"><?php echo esc_html( $location_data['venue'] ); ?></a>
									<?php else: ?>
										<?php echo esc_html( $location_data['venue'] ); ?>
									<?php endif; ?>
								<?php else: ?>
									<span class="empty-cell">—</span>
								<?php endif; ?>
							</td>
							<td data-sort-value="<?php echo esc_attr( $song_count ); ?>">
								<a href="<?php echo esc_url( $show_link ); ?>"><?php echo esc_html( $song_count ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php endif; ?>

		<?php if ( empty( $upcoming_shows ) && empty( $past_shows ) ): ?>
			<p>No shows found for this tour.</p>
		<?php endif; ?>
	</div>
</main>

<?php get_footer(); ?>
