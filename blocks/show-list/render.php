<?php
/**
 * Render callback for the Show List block
 */

// Get block attributes with defaults
$filter_type = $attributes['filterType'] ?? 'all';
$tour_id = $attributes['tourId'] ?? '';
$location_id = $attributes['locationId'] ?? '';
$show_count = $attributes['showCount'] ?? 10;
$show_setlist = $attributes['showSetlist'] ?? false;
$show_venue = $attributes['showVenue'] ?? true;
$show_date = $attributes['showDate'] ?? true;
$layout = $attributes['layout'] ?? 'list';

// Build query args
$args = array(
	'post_type'      => 'show',
	'posts_per_page' => $show_count,
	'orderby'        => 'date',
	'order'          => 'DESC',
	'tax_query'      => array(),
);

// Set order based on filter type
if ( $filter_type === 'upcoming' ) {
	$args['order'] = 'ASC'; // Oldest upcoming first
	$args['meta_query'][] = array(
		'key'     => '',
		'compare' => 'EXISTS',
	);
} elseif ( $filter_type === 'past' ) {
	$args['order'] = 'DESC'; // Most recent past first
}

// Filter by tour
if ( $tour_id ) {
	$args['tax_query'][] = array(
		'taxonomy' => 'tour',
		'field'    => 'term_id',
		'terms'    => intval( $tour_id ),
	);
}

// Filter by location
if ( $location_id ) {
	$args['tax_query'][] = array(
		'taxonomy' => 'location',
		'field'    => 'term_id',
		'terms'    => intval( $location_id ),
	);
}

// Get shows
$shows_query = new WP_Query( $args );
$shows = $shows_query->posts;

// Filter by date if needed
$current_time = current_time( 'timestamp' );
if ( $filter_type === 'upcoming' ) {
	$shows = array_filter( $shows, function( $show ) use ( $current_time ) {
		return strtotime( $show->post_date ) > $current_time;
	} );
} elseif ( $filter_type === 'past' ) {
	$shows = array_filter( $shows, function( $show ) use ( $current_time ) {
		return strtotime( $show->post_date ) <= $current_time;
	} );
}

// Sort shows by date
usort( $shows, function( $a, $b ) use ( $filter_type ) {
	$a_time = strtotime( $a->post_date );
	$b_time = strtotime( $b->post_date );
	if ( $filter_type === 'upcoming' ) {
		return $a_time - $b_time; // Ascending for upcoming
	}
	return $b_time - $a_time; // Descending for past/all
} );

// Limit to show_count
$shows = array_slice( $shows, 0, $show_count );

if ( empty( $shows ) ) {
	echo '<p>No shows found.</p>';
	return;
}

// Output shows
$wrapper_class = 'show-list-block';
if ( $layout === 'grid' ) {
	$wrapper_class .= ' show-list-grid';
}

echo '<div class="' . esc_attr( $wrapper_class ) . '">';

foreach ( $shows as $show ) {
	$show_date = get_the_date( 'F j, Y', $show->ID );
	$location_id_field = get_field( 'show_location', $show->ID );
	$tour_id_field = get_field( 'show_tour', $show->ID );
	$setlist = get_field( 'setlist', $show->ID );
	$ticket_link = get_field( 'ticket_link', $show->ID );
	$is_upcoming = strtotime( $show->post_date ) > $current_time;
	
	// Get location name
	$location_name = '';
	if ( $location_id_field ) {
		$location_term = get_term( $location_id_field, 'location' );
		if ( $location_term && ! is_wp_error( $location_term ) ) {
			$location_name = $location_term->name;
		}
	}
	
	// Get tour name and link
	$tour_name = '';
	$tour_link = '';
	if ( $tour_id_field ) {
		$tour_term = get_term( $tour_id_field, 'tour' );
		if ( $tour_term && ! is_wp_error( $tour_term ) ) {
			$tour_name = $tour_term->name;
			$tour_link = get_term_link( $tour_term->term_id, 'tour' );
		}
	}
	
	// Count songs in setlist
	$song_count = 0;
	if ( $setlist && is_array( $setlist ) ) {
		foreach ( $setlist as $item ) {
			if ( isset( $item['entry_type'] ) && ( $item['entry_type'] === 'song-post' || $item['entry_type'] === 'song-text' ) ) {
				$song_count++;
			}
		}
	}
	
	?>
	<div class="show-list-item">
		<?php if ( $show_date ): ?>
			<div class="show-date">
				<strong><?php echo esc_html( $show_date ); ?></strong>
				<?php if ( $is_upcoming ): ?>
					<span class="show-upcoming-badge">Upcoming</span>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		
		<?php if ( $show_venue && $location_name ): ?>
			<div class="show-location"><?php echo esc_html( $location_name ); ?></div>
		<?php endif; ?>
		
		<?php if ( $tour_name ): ?>
			<div class="show-tour">
				<?php if ( $tour_link && ! is_wp_error( $tour_link ) ): ?>
					<em><a href="<?php echo esc_url( $tour_link ); ?>"><?php echo esc_html( $tour_name ); ?></a></em>
				<?php else: ?>
					<em><?php echo esc_html( $tour_name ); ?></em>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		
		<?php if ( $show_setlist ): ?>
			<div class="show-song-count"<?php echo $song_count === 0 ? ' title="' . esc_attr__( 'Setlist not added yet; will update when available.', 'jww-theme' ) . '"' : ''; ?>><?php echo $song_count > 0 ? esc_html( $song_count ) . ' songs' : '? songs'; ?></div>
		<?php endif; ?>
		
		<div class="show-links">
			<a href="<?php echo esc_url( get_permalink( $show->ID ) ); ?>">View Show</a>
			<?php if ( $is_upcoming && $ticket_link ): ?>
				| <a href="<?php echo esc_url( $ticket_link ); ?>" target="_blank" rel="noopener">Buy Tickets</a>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

echo '</div>';
