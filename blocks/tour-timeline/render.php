<?php
/**
 * Render callback for the Tour Timeline block
 * 
 * Displays shows for a tour in chronological timeline format
 */

// Get block attributes with defaults
$tour_id = $attributes['tourId'] ?? 0;
$show_past_only = $attributes['showPastOnly'] ?? false;
$show_upcoming_only = $attributes['showUpcomingOnly'] ?? false;

// If no tour ID specified, try to get from context (tour archive page)
if ( ! $tour_id && is_tax( 'tour' ) ) {
	$tour_term = get_queried_object();
	if ( $tour_term && ! is_wp_error( $tour_term ) ) {
		$tour_id = $tour_term->term_id;
	}
}

if ( ! $tour_id ) {
	echo '<p>Please select a tour to display the timeline.</p>';
	return;
}

// Ensure show-functions.php is loaded
if ( ! function_exists( 'jww_get_shows_by_tour' ) ) {
	echo '<p>Tour functions not available.</p>';
	return;
}

// Get shows for this tour
$shows = jww_get_shows_by_tour( $tour_id );

if ( empty( $shows ) ) {
	echo '<p>No shows found for this tour.</p>';
	return;
}

// Get tour term
$tour_term = get_term( $tour_id, 'tour' );
$tour_name = $tour_term && ! is_wp_error( $tour_term ) ? $tour_term->name : 'Tour';

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

// Apply filters
if ( $show_past_only ) {
	$upcoming_shows = array();
} elseif ( $show_upcoming_only ) {
	$past_shows = array();
}

// Combine shows (upcoming first, then past)
$all_shows = array_merge( $upcoming_shows, $past_shows );

$wrapper_class = 'tour-timeline-block';

echo '<div class="' . esc_attr( $wrapper_class ) . '">';
echo '<h2 class="wp-block-heading">' . esc_html( $tour_name ) . ' Timeline</h2>';

if ( empty( $all_shows ) ) {
	echo '<p>No shows to display.</p>';
	echo '</div>';
	return;
}

echo '<div class="timeline-container">';

foreach ( $all_shows as $index => $show ) {
	$show_date = get_the_date( 'M j, Y', $show->ID );
	$show_date_raw = get_the_date( 'Y-m-d', $show->ID );
	$show_link = get_permalink( $show->ID );
	$show_title = get_the_title( $show->ID );
	$location_id = get_field( 'show_location', $show->ID );
	$is_upcoming = strtotime( $show->post_date ) > $current_time;
	
	// Get location name
	$location_name = '';
	if ( $location_id ) {
		$location_term = get_term( $location_id, 'location' );
		if ( $location_term && ! is_wp_error( $location_term ) ) {
			$location_name = $location_term->name;
		}
	}
	
	$is_last = ( $index === count( $all_shows ) - 1 );
	
	echo '<div class="timeline-item' . ( $is_upcoming ? ' upcoming' : '' ) . '">';
	echo '<div class="timeline-marker"></div>';
	echo '<div class="timeline-content">';
	echo '<div class="timeline-date">' . esc_html( $show_date ) . '</div>';
	echo '<h3 class="timeline-title"><a href="' . esc_url( $show_link ) . '">' . esc_html( $show_title ) . '</a></h3>';
	if ( $location_name ) {
		echo '<div class="timeline-location">' . esc_html( $location_name ) . '</div>';
	}
	if ( $is_upcoming ) {
		$ticket_link = get_field( 'ticket_link', $show->ID );
		if ( $ticket_link ) {
			echo '<div class="timeline-tickets"><a href="' . esc_url( $ticket_link ) . '" target="_blank" rel="noopener" class="ticket-link">Get Tickets</a></div>';
		}
	}
	echo '</div>';
	echo '</div>';
}

echo '</div>'; // .timeline-container
echo '</div>'; // .tour-timeline-block
