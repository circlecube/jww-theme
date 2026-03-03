<?php
/**
 * Template for displaying show archives
 */

get_header();

// Get filter parameters
$filter_tour = isset( $_GET['tour'] ) ? intval( $_GET['tour'] ) : 0;
$filter_location = isset( $_GET['location'] ) ? intval( $_GET['location'] ) : 0; // This is the venue (final selection)
$filter_location_country = isset( $_GET['location_country'] ) ? intval( $_GET['location_country'] ) : 0;
$filter_location_state = isset( $_GET['location_state'] ) ? intval( $_GET['location_state'] ) : 0;
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

// Filter by location (venue, city, state, or country)
// Priority: venue > city > state > country
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
} elseif ( $filter_location_state ) {
	// Filter by state (includes all cities and venues in that state)
	$args['tax_query'][] = array(
		'taxonomy' => 'location',
		'field'    => 'term_id',
		'terms'    => $filter_location_state,
		'include_children' => true, // Include all cities and venues under this state
	);
} elseif ( $filter_location_country ) {
	// Filter by country (includes all states, cities and venues in that country)
	$args['tax_query'][] = array(
		'taxonomy' => 'location',
		'field'    => 'term_id',
		'terms'    => $filter_location_country,
		'include_children' => true, // Include all states, cities and venues under this country
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

// Get all tours and locations for filters (cached)
$cache_key_tours = 'jww_archive_tours';
$all_tours = get_transient( $cache_key_tours );
if ( $all_tours === false ) {
	$all_tours = get_terms( array(
		'taxonomy'   => 'tour',
		'hide_empty' => true,
	) );
	set_transient( $cache_key_tours, $all_tours, 6 * HOUR_IN_SECONDS );
}

// Get all locations for hierarchical filtering (cached)
$cache_key_locations = 'jww_archive_locations';
$all_locations = get_transient( $cache_key_locations );
if ( $all_locations === false ) {
	$all_locations = get_terms( array(
		'taxonomy'   => 'location',
		'hide_empty' => true,
		'orderby'    => 'name',
		'hierarchical' => true,
	) );
	set_transient( $cache_key_locations, $all_locations, 6 * HOUR_IN_SECONDS );
}

// Organize locations by hierarchy depth: 0 = country, 1 = state, 2 = city, 3 = venue
$countries = array();
$states = array();
$cities = array();
$venues = array();

$parent_map = array();
foreach ( $all_locations as $loc ) {
	$parent_map[ $loc->term_id ] = (int) $loc->parent;
}
foreach ( $all_locations as $location ) {
	$depth = 0;
	$tid = $location->term_id;
	while ( isset( $parent_map[ $tid ] ) && $parent_map[ $tid ] ) {
		$depth++;
		$tid = $parent_map[ $tid ];
	}
	if ( $depth === 0 ) {
		$countries[] = $location;
	} elseif ( $depth === 1 ) {
		$states[] = $location;
	} elseif ( $depth === 2 ) {
		$cities[] = $location;
	} else {
		$venues[] = $location;
	}
}

// Get selected location and determine which country/state/city/venue is selected
$selected_country = $filter_location_country;
$selected_state   = $filter_location_state;
$selected_city    = $filter_location_city;
$selected_venue   = $filter_location;

// If a venue is selected, walk up to set city, state, and country
if ( $selected_venue && ( ! $selected_city || ! $selected_country ) ) {
	$venue_term = get_term( $selected_venue, 'location' );
	if ( $venue_term && ! is_wp_error( $venue_term ) && $venue_term->parent ) {
		$selected_city = (int) $venue_term->parent;
		$city_term = get_term( $selected_city, 'location' );
		if ( $city_term && ! is_wp_error( $city_term ) && $city_term->parent ) {
			$parent_id = (int) $city_term->parent;
			$parent_term = get_term( $parent_id, 'location' );
			if ( $parent_term && ! is_wp_error( $parent_term ) ) {
				if ( $parent_term->parent ) {
					// 4-level: parent of city is state
					$selected_state = $parent_id;
					$selected_country = (int) $parent_term->parent;
				} else {
					// 3-level: parent of city is country
					$selected_country = $parent_id;
				}
			}
		}
	}
}

// If a city is selected but state/country not set, walk up
if ( $selected_city && ( ! $selected_state || ! $selected_country ) ) {
	$city_term = get_term( $selected_city, 'location' );
	if ( $city_term && ! is_wp_error( $city_term ) && $city_term->parent ) {
		$parent_id = (int) $city_term->parent;
		$parent_term = get_term( $parent_id, 'location' );
		if ( $parent_term && ! is_wp_error( $parent_term ) ) {
			if ( $parent_term->parent ) {
				$selected_state = $parent_id;
				$selected_country = (int) $parent_term->parent;
			} else {
				$selected_country = $parent_id;
			}
		}
	}
}

// If a state is selected but country not set
if ( $selected_state && ! $selected_country ) {
	$state_term = get_term( $selected_state, 'location' );
	if ( $state_term && ! is_wp_error( $state_term ) && $state_term->parent ) {
		$selected_country = (int) $state_term->parent;
	}
}

// Filter cities: by state when state selected, else by country
$filtered_cities = array();
if ( $selected_state ) {
	foreach ( $cities as $city ) {
		if ( (int) $city->parent === $selected_state ) {
			$filtered_cities[] = $city;
		}
	}
} elseif ( $selected_country ) {
	foreach ( $cities as $city ) {
		if ( (int) $city->parent === $selected_country ) {
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
		if ( (int) $venue->parent === $selected_city ) {
			$filtered_venues[] = $venue;
		}
	}
} else {
	$filtered_venues = $venues;
}

// Does the selected country have state-level children or use state level? (show state dropdown)
$selected_country_has_states = false;
if ( $selected_country ) {
	if ( function_exists( 'jww_country_has_states' ) && jww_country_has_states( $selected_country ) ) {
		$selected_country_has_states = true;
	}
	if ( ! $selected_country_has_states ) {
		foreach ( $states as $state ) {
			if ( (int) $state->parent === $selected_country ) {
				$selected_country_has_states = true;
				break;
			}
		}
	}
}
$filtered_states = array();
if ( $selected_country ) {
	foreach ( $states as $state ) {
		if ( (int) $state->parent === $selected_country ) {
			$filtered_states[] = $state;
		}
	}
} else {
	$filtered_states = $states;
}

// Calculate statistics
$total_shows = count( $shows );
$upcoming_count = count( $upcoming_shows );
$past_count = count( $past_shows );

// All-time stats for "All Time Concert Insights" (main archive only; taxonomy uses its own template)
if ( ! $is_taxonomy_archive && function_exists( 'jww_get_all_time_show_stats' ) ) {
	$all_time = jww_get_all_time_show_stats( true );
	set_query_var( 'archive_all_time_total_shows', $all_time['total_shows'] );
	set_query_var( 'archive_all_time_upcoming_count', $all_time['upcoming_count'] );
	set_query_var( 'archive_all_time_past_count', $all_time['past_count'] );
	set_query_var( 'archive_all_time_venues_count', $all_time['venues_count'] );
	set_query_var( 'archive_all_time_cities_count', $all_time['cities_count'] );
	set_query_var( 'archive_all_time_shows_with_data_count', $all_time['shows_with_data_count'] );
	set_query_var( 'archive_all_time_unique_songs_count', $all_time['unique_songs_count'] );
	set_query_var( 'archive_all_time_festivals_count', $all_time['festivals_count'] );
}
if ( ! $is_taxonomy_archive && function_exists( 'jww_get_all_time_opener_closer' ) ) {
	$oc = jww_get_all_time_opener_closer( true );
	set_query_var( 'archive_all_time_openers', $oc['openers'] ?? array() );
	set_query_var( 'archive_all_time_closers', $oc['closers'] ?? array() );
}
if ( ! $is_taxonomy_archive && function_exists( 'jww_get_all_time_most_played_songs' ) ) {
	set_query_var( 'archive_all_time_most_played_songs', jww_get_all_time_most_played_songs( 5, true ) );
}
if ( ! $is_taxonomy_archive && function_exists( 'jww_get_all_time_latest_debut' ) ) {
	set_query_var( 'archive_all_time_latest_debut', jww_get_all_time_latest_debut( true ) );
}
if ( ! $is_taxonomy_archive && function_exists( 'jww_get_all_time_one_offs' ) ) {
	set_query_var( 'archive_all_time_one_offs', jww_get_all_time_one_offs( true ) );
}
if ( ! $is_taxonomy_archive && function_exists( 'jww_get_all_time_standout_songs' ) ) {
	set_query_var( 'archive_all_time_standout_songs', jww_get_all_time_standout_songs( true ) );
}
if ( ! $is_taxonomy_archive && function_exists( 'jww_get_all_time_tours_list' ) ) {
	set_query_var( 'archive_all_time_tours_list', jww_get_all_time_tours_list( true ) );
}
if ( ! $is_taxonomy_archive && function_exists( 'jww_get_all_time_festivals_list' ) ) {
	set_query_var( 'archive_all_time_festivals_list', jww_get_all_time_festivals_list( true ) );
}
if ( ! $is_taxonomy_archive && function_exists( 'jww_get_all_time_longest_set' ) ) {
	set_query_var( 'archive_all_time_longest_set', jww_get_all_time_longest_set( true ) );
}

// Query vars for overview cards
set_query_var( 'show_stats_total', $total_shows );
set_query_var( 'show_stats_upcoming', $upcoming_count );
set_query_var( 'show_stats_past', $past_count );
?>

<main class="wp-block-group align alignwide is-layout-flow wp-block-group-is-layout-flow">
	<div
		class="wp-block-group has-global-padding" 
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

		<?php if ( ! $is_taxonomy_archive ) : ?>
		<h2 id="archive-all-time-insights-heading" class="show-setlist-data-heading wp-block-heading"><?php esc_html_e( 'All Time Concert Insights', 'jww-theme' ); ?></h2>
		<div class="wp-block-group alignwide show-stats-cards show-stats-cards-masonry" id="archive-stats-cards-masonry" style="margin-bottom:var(--wp--preset--spacing--50);">
			<?php get_template_part( 'template-parts/archive-all-time-insight-cards' ); ?>
		</div>
		<?php endif; ?>

		<!-- Filters (card-style when on main Shows archive) -->
		<?php if ( ! $is_taxonomy_archive ): ?>
		<div class="wp-block-group alignwide show-filters-card" style="margin-bottom:var(--wp--preset--spacing--50);">
			<div class="show-filters-card-inner">
				<h2 id="show-filters-heading" class="wp-block-heading show-filters-card-heading"><?php esc_html_e( 'Filter shows', 'jww-theme' ); ?></h2>
				<form method="get" action="<?php echo esc_url( get_post_type_archive_link( 'show' ) ); ?>" class="filter-form" aria-labelledby="show-filters-heading">
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
							
							<?php if ( ! empty( $states ) ) : ?>
							<!-- State Select (shown when country has state-level terms) -->
							<select name="location_state" id="filter-location-state" class="location-select" data-level="state" data-parent="country" <?php echo ( $selected_country && $selected_country_has_states ) ? '' : 'style="display:none;"'; ?>>
								<option value=""><?php esc_html_e( 'All States/Provinces', 'jww-theme' ); ?></option>
								<?php foreach ( $states as $state ) : ?>
									<option value="<?php echo esc_attr( $state->term_id ); ?>" data-parent-id="<?php echo esc_attr( $state->parent ); ?>" <?php selected( $selected_state, $state->term_id ); ?>>
										<?php echo esc_html( $state->name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<?php endif; ?>
							
							<!-- City Select (shown when country is selected; when country has states, after state is selected) -->
							<select name="location_city" id="filter-location-city" class="location-select" data-level="city" data-parent="<?php echo $selected_country_has_states ? 'state' : 'country'; ?>" <?php echo ( $selected_country && ( ! $selected_country_has_states || $selected_state ) ) ? '' : 'style="display:none;"'; ?>>
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
				<button type="submit" class="filter-submit wp-block-button__link wp-element-button">Filter</button>
				<?php if ( $filter_tour || $filter_location || $filter_location_country || $filter_location_state || $filter_location_city || $filter_type !== 'all' ): ?>
					<a href="<?php echo esc_url( get_post_type_archive_link( 'show' ) ); ?>" class="filter-clear wp-block-button__link wp-element-button">Clear Filters</a>
				<?php endif; ?>
			</form>
			</div>
		</div>
		<?php endif; ?>

		<!-- Shows tables: card + accordion (one section = "Shows", two = "Upcoming Shows" / "Past Shows") -->
		<?php
		$has_upcoming = ! empty( $upcoming_shows );
		$has_past     = ! empty( $past_shows );
		$single_section = ( $has_upcoming && ! $has_past ) || ( ! $has_upcoming && $has_past );
		$section_title = $single_section ? __( 'Shows', 'jww-theme' ) : null;

		if ( $has_upcoming ) :
			$upcoming_title = $section_title !== null ? $section_title : __( 'Upcoming Shows', 'jww-theme' );
			jww_render_shows_table_card( $upcoming_shows, array(
				'title'            => $upcoming_title,
				'table_type'       => 'upcoming',
				'show_tour_column' => true,
				'default_open'     => true,
				'accordion_id'     => 'archive-upcoming-shows-accordion',
			) );
		endif;

		if ( $has_past ) :
			$past_title = $section_title !== null ? $section_title : __( 'Past Shows', 'jww-theme' );
			jww_render_shows_table_card( $past_shows, array(
				'title'            => $past_title,
				'table_type'       => 'past',
				'show_tour_column' => true,
				'default_open'     => ! $has_upcoming,
				'accordion_id'     => 'archive-past-shows-accordion',
			) );
		endif;
		?>

		<?php if ( ! $has_upcoming && ! $has_past ): ?>
			<p>No shows found.</p>
		<?php endif; ?>

		<?php
		// Song live stats table (all songs, play counts, first/last played) — main Shows archive only. Same card + accordion as shows tables.
		if ( ! $is_taxonomy_archive && function_exists( 'jww_get_all_time_song_stats' ) ) :
			$song_stats_list = jww_get_all_time_song_stats( true );
			$song_stats_count = is_array( $song_stats_list ) ? count( $song_stats_list ) : 0;
			$song_stats_block = render_block( array(
				'blockName' => 'jww/song-stats-table',
				'attrs'     => array(),
			) );
			if ( $song_stats_block ) :
				$song_live_open = false; // Collapsed by default
				?>
				<div class="wp-block-group alignwide shows-table-card" style="margin-top:var(--wp--preset--spacing--50);margin-bottom:var(--wp--preset--spacing--50);">
					<details class="shows-accordion" id="archive-song-live-stats-accordion"<?php echo $song_live_open ? ' open' : ''; ?> aria-labelledby="archive-song-live-stats-heading">
						<summary class="shows-accordion-summary">
							<h2 id="archive-song-live-stats-heading" class="wp-block-heading show-setlist-data-heading"><?php esc_html_e( 'Song Performances', 'jww-theme' ); ?></h2>
							<span class="shows-accordion-count"><?php echo esc_html( '(' . $song_stats_count . ')' ); ?></span>
						</summary>
						<div class="shows-table-wrapper">
							<?php echo $song_stats_block; ?>
						</div>
					</details>
				</div>
				<?php
			endif;
		endif;
		?>
	</div>
</main>

<?php
// Open accordion when linking from insight cards or when page loads with hash (main Shows archive only)
if ( ! $is_taxonomy_archive ) :
	?>
	<script>
	(function() {
		function openAccordionById(id) {
			var el = document.getElementById(id);
			if (el && el.tagName === 'DETAILS') {
				el.setAttribute('open', '');
			}
		}
		function initArchiveAccordionLinks() {
			document.querySelectorAll('.archive-insight-accordion-link').forEach(function(a) {
				a.addEventListener('click', function(e) {
					var id = a.getAttribute('data-accordion-id');
					if (id) {
						e.preventDefault();
						openAccordionById(id);
						window.location.hash = id;
					}
				});
			});
			var hash = window.location.hash.replace(/^#/, '');
			if (hash && (hash === 'archive-upcoming-shows-accordion' || hash === 'archive-past-shows-accordion' || hash === 'archive-song-live-stats-accordion')) {
				openAccordionById(hash);
			}
		}
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', initArchiveAccordionLinks);
		} else {
			initArchiveAccordionLinks();
		}
	})();
	</script>
	<?php
endif;
?>

<?php get_footer(); ?>
