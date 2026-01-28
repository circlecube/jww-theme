<?php
/**
 * Template for displaying show archives
 */

get_header();

// Get filter parameters
$filter_tour = isset( $_GET['tour'] ) ? intval( $_GET['tour'] ) : 0;
$filter_location = isset( $_GET['location'] ) ? intval( $_GET['location'] ) : 0; // This is the venue (final selection)
$filter_location_country = isset( $_GET['location_country'] ) ? intval( $_GET['location_country'] ) : 0;
$filter_location_city = isset( $_GET['location_city'] ) ? intval( $_GET['location_city'] ) : 0;
$filter_type = isset( $_GET['type'] ) ? sanitize_text_field( $_GET['type'] ) : 'all'; // all, upcoming, past

// Check if we're on a taxonomy archive (tour or location)
$is_taxonomy_archive = is_tax( 'tour' ) || is_tax( 'location' );
$taxonomy_term = null;
if ( $is_taxonomy_archive ) {
	$taxonomy_term = get_queried_object();
	// Set filter based on taxonomy (override GET parameters)
	if ( is_tax( 'tour' ) ) {
		$filter_tour = $taxonomy_term->term_id;
		$filter_location = 0; // Clear location filter
	} elseif ( is_tax( 'location' ) ) {
		$filter_location = $taxonomy_term->term_id;
		$filter_tour = 0; // Clear tour filter
	}
}

// Build query args
$args = array(
	'post_type'      => 'show',
	'posts_per_page' => -1, // Show all
	'orderby'        => 'date',
	'order'          => 'ASC', // Will be sorted after query
	'post_status'    => array( 'publish', 'future' ), // Include scheduled posts
	'meta_query'     => array(),
	'tax_query'      => array(),
);

// Filter by tour
if ( $filter_tour ) {
	$args['tax_query'][] = array(
		'taxonomy' => 'tour',
		'field'    => 'term_id',
		'terms'    => $filter_tour,
	);
}

// Filter by location (venue, city, or country)
// Priority: venue > city > country
if ( $filter_location ) {
	// Filter by specific venue
	$args['tax_query'][] = array(
		'taxonomy' => 'location',
		'field'    => 'term_id',
		'terms'    => $filter_location,
	);
} elseif ( $filter_location_city ) {
	// Filter by city (includes all venues in that city)
	$args['tax_query'][] = array(
		'taxonomy' => 'location',
		'field'    => 'term_id',
		'terms'    => $filter_location_city,
		'include_children' => true, // Include all venues under this city
	);
} elseif ( $filter_location_country ) {
	// Filter by country (includes all cities and venues in that country)
	$args['tax_query'][] = array(
		'taxonomy' => 'location',
		'field'    => 'term_id',
		'terms'    => $filter_location_country,
		'include_children' => true, // Include all cities and venues under this country
	);
}

// Get shows
$shows_query = new WP_Query( $args );
$shows = $shows_query->posts;

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

// Apply filter_type if set
if ( $filter_type === 'upcoming' ) {
	$past_shows = array();
} elseif ( $filter_type === 'past' ) {
	$upcoming_shows = array();
}

// Get all tours and locations for filters
$all_tours = get_terms( array(
	'taxonomy'   => 'tour',
	'hide_empty' => true,
) );

// Get all locations for hierarchical filtering
$all_locations = get_terms( array(
	'taxonomy'   => 'location',
	'hide_empty' => true,
	'orderby'    => 'name',
	'hierarchical' => true,
) );

// Organize locations by hierarchy level (Country > City > Venue)
$countries = array();
$cities = array();
$venues = array();

foreach ( $all_locations as $location ) {
	if ( ! $location->parent ) {
		// Top level = Country
		$countries[] = $location;
	} else {
		$parent = get_term( $location->parent, 'location' );
		if ( $parent && ! $parent->parent ) {
			// Second level = City
			$cities[] = $location;
		} else {
			// Third level = Venue
			$venues[] = $location;
		}
	}
}

// Get selected location and determine which country/city/venue is selected
$selected_country = $filter_location_country;
$selected_city = $filter_location_city;
$selected_venue = $filter_location;

// If a venue is selected, determine its parent city and country
if ( $selected_venue && ! $selected_city ) {
	$venue_term = get_term( $selected_venue, 'location' );
	if ( $venue_term && ! is_wp_error( $venue_term ) && $venue_term->parent ) {
		$selected_city = $venue_term->parent;
		$city_term = get_term( $selected_city, 'location' );
		if ( $city_term && ! is_wp_error( $city_term ) && $city_term->parent ) {
			$selected_country = $city_term->parent;
		}
	}
}

// Filter cities by selected country
$filtered_cities = array();
if ( $selected_country ) {
	foreach ( $cities as $city ) {
		if ( $city->parent == $selected_country ) {
			$filtered_cities[] = $city;
		}
	}
} else {
	$filtered_cities = $cities;
}

// Filter venues by selected city
$filtered_venues = array();
if ( $selected_city ) {
	foreach ( $venues as $venue ) {
		if ( $venue->parent == $selected_city ) {
			$filtered_venues[] = $venue;
		}
	}
} else {
	$filtered_venues = $venues;
}

// Calculate statistics
$total_shows = count( $shows );
$upcoming_count = count( $upcoming_shows );
$past_count = count( $past_shows );

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
			<?php 
			if ( $is_taxonomy_archive && $taxonomy_term ) {
				echo esc_html( $taxonomy_term->name ) . ' Shows';
			} else {
				echo 'Shows';
			}
			?>
		</h1>

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

		<!-- Filters -->
		<?php if ( ! $is_taxonomy_archive ): ?>
		<div class="wp-block-group alignwide show-filters" style="margin-bottom:var(--wp--preset--spacing--40)">
			<form method="get" action="<?php echo esc_url( get_post_type_archive_link( 'show' ) ); ?>" class="filter-form" style="display:flex;flex-wrap:wrap;gap:var(--wp--preset--spacing--20);align-items:end">
				<div class="filter-group">
					<label for="filter-type">Show Type:</label>
					<select name="type" id="filter-type">
						<option value="all" <?php selected( $filter_type, 'all' ); ?>>All Shows</option>
						<option value="upcoming" <?php selected( $filter_type, 'upcoming' ); ?>>Upcoming</option>
						<option value="past" <?php selected( $filter_type, 'past' ); ?>>Past Shows</option>
					</select>
				</div>
				<?php if ( ! empty( $all_tours ) && ! is_wp_error( $all_tours ) ): ?>
					<div class="filter-group">
						<label for="filter-tour">Tour:</label>
						<select name="tour" id="filter-tour">
							<option value="">All Tours</option>
							<?php foreach ( $all_tours as $tour ): ?>
								<option value="<?php echo esc_attr( $tour->term_id ); ?>" <?php selected( $filter_tour, $tour->term_id ); ?>>
									<?php echo esc_html( $tour->name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $all_locations ) && ! is_wp_error( $all_locations ) ): ?>
					<div class="filter-group filter-location-group">
						<label>Location:</label>
						<div class="location-cascade" style="display:flex;gap:var(--wp--preset--spacing--20);flex-wrap:wrap;">
							<!-- Country Select -->
							<select name="location_country" id="filter-location-country" class="location-select" data-level="country">
								<option value="">All Countries</option>
								<?php foreach ( $countries as $country ): ?>
									<option value="<?php echo esc_attr( $country->term_id ); ?>" <?php selected( $selected_country, $country->term_id ); ?>>
										<?php echo esc_html( $country->name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							
							<!-- City Select (shown when country is selected) -->
							<select name="location_city" id="filter-location-city" class="location-select" data-level="city" data-parent="country" <?php echo $selected_country ? '' : 'style="display:none;"'; ?>>
								<option value="">All Cities</option>
								<?php foreach ( $cities as $city ): ?>
									<option value="<?php echo esc_attr( $city->term_id ); ?>" data-parent-id="<?php echo esc_attr( $city->parent ); ?>" <?php selected( $selected_city, $city->term_id ); ?>>
										<?php echo esc_html( $city->name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							
							<!-- Venue Select (shown when city is selected) -->
							<select name="location" id="filter-location" class="location-select" data-level="venue" data-parent="city" <?php echo $selected_city ? '' : 'style="display:none;"'; ?>>
								<option value="">All Venues</option>
								<?php foreach ( $venues as $venue ): ?>
									<option value="<?php echo esc_attr( $venue->term_id ); ?>" data-parent-id="<?php echo esc_attr( $venue->parent ); ?>" <?php selected( $filter_location, $venue->term_id ); ?>>
										<?php echo esc_html( $venue->name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
				<?php endif; ?>
				<button type="submit" class="wp-block-button__link wp-element-button">Filter</button>
				<?php if ( $filter_tour || $filter_location || $filter_location_country || $filter_location_city || $filter_type !== 'all' ): ?>
					<a href="<?php echo esc_url( get_post_type_archive_link( 'show' ) ); ?>" class="wp-block-button__link wp-element-button">Clear Filters</a>
				<?php endif; ?>
			</form>
		</div>
		<?php endif; ?>

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
					<?php foreach ( $upcoming_shows as $show ): 
						$show_title = get_the_title( $show->ID );
						$show_date = get_the_date( 'M j, Y', $show->ID );
						$show_date_raw = get_the_date( 'Y-m-d', $show->ID );
						$show_link = get_permalink( $show->ID );
						$location_id = get_field( 'show_location', $show->ID );
						$tour_id = get_field( 'show_tour', $show->ID );
						$ticket_link = get_field( 'ticket_link', $show->ID );
						
						$location_data = jww_get_location_hierarchy( $location_id );
						
						// Get tour name and link
						$tour_name = '';
						$tour_link = '';
						if ( $tour_id ) {
							$tour_term = get_term( $tour_id, 'tour' );
							if ( $tour_term && ! is_wp_error( $tour_term ) ) {
								$tour_name = $tour_term->name;
								$tour_link = get_term_link( $tour_term->term_id, 'tour' );
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
					<?php foreach ( $past_shows as $show ): 
						$show_title = get_the_title( $show->ID );
						$show_date = get_the_date( 'M j, Y', $show->ID );
						$show_date_raw = get_the_date( 'Y-m-d', $show->ID );
						$show_link = get_permalink( $show->ID );
						$location_id = get_field( 'show_location', $show->ID );
						$tour_id = get_field( 'show_tour', $show->ID );
						$setlist = get_field( 'setlist', $show->ID );
						$song_count = jww_count_setlist_songs( $setlist );
						
						$location_data = jww_get_location_hierarchy( $location_id );
						
						// Get tour name and link
						$tour_name = '';
						$tour_link = '';
						if ( $tour_id ) {
							$tour_term = get_term( $tour_id, 'tour' );
							if ( $tour_term && ! is_wp_error( $tour_term ) ) {
								$tour_name = $tour_term->name;
								$tour_link = get_term_link( $tour_term->term_id, 'tour' );
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
			<p>No shows found.</p>
		<?php endif; ?>
	</div>
</main>

<?php get_footer(); ?>
