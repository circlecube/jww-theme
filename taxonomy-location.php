<?php
/**
 * Template for displaying location taxonomy archives
 * 
 * Template Name: Location Archive
 * Displays shows for a specific location/venue in table format
 */

get_header();

// Get the location term
$location_term = get_queried_object();
if ( ! $location_term || is_wp_error( $location_term ) ) {
	get_footer();
	return;
}

// Get shows for this location
$shows = jww_get_shows_by_venue( $location_term->term_id );

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

// Get location hierarchy for display
$location_path = array();
$current_term = $location_term;
while ( $current_term ) {
	array_unshift( $location_path, $current_term );
	if ( $current_term->parent ) {
		$current_term = get_term( $current_term->parent, 'location' );
	} else {
		break;
	}
}
// Reverse to show Venue > City > Country
$location_path = array_reverse( $location_path );
$location_display = array();
foreach ( $location_path as $term ) {
	$location_display[] = $term->name;
}
$location_display_string = implode( ' > ', $location_display );

/**
 * Helper function to get location hierarchy (City/Country and Venue)
 */
function jww_get_location_hierarchy( $location_id ) {
	if ( ! $location_id ) {
		return array(
			'city_country' => '',
			'venue'        => '',
			'venue_link'   => '',
		);
	}
	
	$location_term = get_term( $location_id, 'location' );
	if ( ! $location_term || is_wp_error( $location_term ) ) {
		return array(
			'city_country' => '',
			'venue'        => '',
			'venue_link'   => '',
		);
	}
	
	// Build hierarchy path from venue up to country
	// Using array_unshift, so path[0] = country, path[1] = city, path[2] = venue
	$path = array();
	$current_term = $location_term;
	while ( $current_term ) {
		array_unshift( $path, $current_term );
		if ( $current_term->parent ) {
			$current_term = get_term( $current_term->parent, 'location' );
		} else {
			break;
		}
	}
	
	// After building: path[0] = country, path[1] = city, path[2] = venue
	// We want to display: Venue > City > Country (reversed order)
	$venue = '';
	$venue_link = '';
	$city_name = '';
	$city_link = '';
	$country_name = '';
	$country_link = '';
	
	$path_count = count( $path );
	
	if ( $path_count >= 1 ) {
		// Last item is the venue (deepest level)
		$venue_term = $path[$path_count - 1];
		$venue = $venue_term->name;
		$venue_link = get_term_link( $venue_term->term_id, 'location' );
	}
	
	if ( $path_count >= 2 ) {
		// Second to last is city
		$city_term = $path[$path_count - 2];
		$city_name = $city_term->name;
		$city_link = get_term_link( $city_term->term_id, 'location' );
	}
	
	if ( $path_count >= 3 ) {
		// First item is country
		$country_term = $path[0];
		$country_name = $country_term->name;
		$country_link = get_term_link( $country_term->term_id, 'location' );
	}
	
	// Build city_country string with links for display
	$city_country_parts = array();
	if ( $city_name ) {
		if ( $city_link && ! is_wp_error( $city_link ) ) {
			$city_country_parts[] = '<a href="' . esc_url( $city_link ) . '">' . esc_html( $city_name ) . '</a>';
		} else {
			$city_country_parts[] = esc_html( $city_name );
		}
	}
	if ( $country_name ) {
		if ( $country_link && ! is_wp_error( $country_link ) ) {
			$city_country_parts[] = '<a href="' . esc_url( $country_link ) . '">' . esc_html( $country_name ) . '</a>';
		} else {
			$city_country_parts[] = esc_html( $country_name );
		}
	}
	
	return array(
		'city_country' => implode( ', ', $city_country_parts ),
		'venue'        => $venue,
		'venue_link'   => $venue_link,
	);
}

/**
 * Helper function to count songs in setlist
 */
function jww_count_setlist_songs( $setlist ) {
	if ( ! $setlist || ! is_array( $setlist ) ) {
		return 0;
	}
	
	$count = 0;
	foreach ( $setlist as $item ) {
		if ( isset( $item['entry_type'] ) && ( $item['entry_type'] === 'song-post' || $item['entry_type'] === 'song-text' ) ) {
			$count++;
		}
	}
	return $count;
}
?>

<main class="wp-block-group align is-layout-flow wp-block-group-is-layout-flow">
	<div
		class="wp-block-group has-global-padding is-layout-constrained wp-block-group-is-layout-constrained" 
		style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50)"
	>
		<h1 class="wp-block-post-title alignwide has-xxx-large-font-size">
			<?php echo esc_html( $location_term->name ); ?>
		</h1>
		
		<p class="back-link" style="margin-bottom:var(--wp--preset--spacing--20);">
			<a href="<?php echo esc_url( get_post_type_archive_link( 'show' ) ); ?>">← All Shows</a>
		</p>
		
		<?php if ( count( $location_display ) > 1 ): ?>
			<p class="location-hierarchy" style="margin-bottom:var(--wp--preset--spacing--30);"><?php echo esc_html( $location_display_string ); ?></p>
		<?php endif; ?>

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
							Title <span class="sort-indicator"></span>
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
						<th class="sortable" data-sort="tour" data-sort-type="text">
							Tour <span class="sort-indicator"></span>
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
						$tour_id = isset( $fields['show_tour'] ) ? $fields['show_tour'] : 0;
						$ticket_link = isset( $fields['ticket_link'] ) ? $fields['ticket_link'] : '';
						
						$location_data = jww_get_location_hierarchy( $location_id );
						
						// Get tour name and link
						$tour_name = '';
						$tour_link = '';
						if ( $tour_id ) {
							$tour_term_obj = get_term( $tour_id, 'tour' );
							if ( $tour_term_obj && ! is_wp_error( $tour_term_obj ) ) {
								$tour_name = $tour_term_obj->name;
								$tour_link = get_term_link( $tour_term_obj->term_id, 'tour' );
							}
						}
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
							<td data-sort-value="<?php echo esc_attr( strtolower( $tour_name ) ); ?>">
								<?php if ( $tour_name ): ?>
									<?php if ( $tour_link && ! is_wp_error( $tour_link ) ): ?>
										<a href="<?php echo esc_url( $tour_link ); ?>"><?php echo esc_html( $tour_name ); ?></a>
									<?php else: ?>
										<?php echo esc_html( $tour_name ); ?>
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
							Title <span class="sort-indicator"></span>
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
						<th class="sortable" data-sort="tour" data-sort-type="text">
							Tour <span class="sort-indicator"></span>
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
						$tour_id = isset( $fields['show_tour'] ) ? $fields['show_tour'] : 0;
						$setlist = isset( $fields['setlist'] ) ? $fields['setlist'] : array();
						$song_count = jww_count_setlist_songs( $setlist );
						
						$location_data = jww_get_location_hierarchy( $location_id );
						
						// Get tour name and link
						$tour_name = '';
						$tour_link = '';
						if ( $tour_id ) {
							$tour_term_obj = get_term( $tour_id, 'tour' );
							if ( $tour_term_obj && ! is_wp_error( $tour_term_obj ) ) {
								$tour_name = $tour_term_obj->name;
								$tour_link = get_term_link( $tour_term_obj->term_id, 'tour' );
							}
						}
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
							<td data-sort-value="<?php echo esc_attr( strtolower( $tour_name ) ); ?>">
								<?php if ( $tour_name ): ?>
									<?php if ( $tour_link && ! is_wp_error( $tour_link ) ): ?>
										<a href="<?php echo esc_url( $tour_link ); ?>"><?php echo esc_html( $tour_name ); ?></a>
									<?php else: ?>
										<?php echo esc_html( $tour_name ); ?>
									<?php endif; ?>
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

		<?php if ( empty( $upcoming_shows ) && empty( $past_shows ) ): ?>
			<p>No shows found for this location.</p>
		<?php endif; ?>
	</div>
</main>

<?php get_footer(); ?>
