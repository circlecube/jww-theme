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
	$show_id = $show->ID;
	$show_date = get_the_date( 'M j, Y', $show_id );
	$show_link = get_permalink( $show_id );
	$show_title = get_the_title( $show_id );
	$location_id = get_field( 'show_location', $show_id );
	$is_upcoming = strtotime( $show->post_date ) > $current_time;

	// Build location path (venue → city → state → country), each term clickable; country abbreviated if available
	$location_html = '';
	if ( $location_id ) {
		$location_term = get_term( $location_id, 'location' );
		if ( $location_term && ! is_wp_error( $location_term ) ) {
			$path = array();
			$current_term = $location_term;
			while ( $current_term ) {
				array_unshift( $path, $current_term );
				$current_term = $current_term->parent ? get_term( $current_term->parent, 'location' ) : null;
				if ( ! $current_term || is_wp_error( $current_term ) ) {
					break;
				}
			}
			$parts = array();
			foreach ( array_reverse( $path ) as $term ) {
				$link = get_term_link( $term->term_id, 'location' );
				$label = $term->name;
				if ( function_exists( 'jww_get_location_type' ) && jww_get_location_type( $term->term_id ) === 'country' && function_exists( 'jww_get_country_code' ) ) {
					$code = jww_get_country_code( $term->term_id );
					if ( $code !== '' ) {
						$label = $code;
					}
				}
				if ( $link && ! is_wp_error( $link ) ) {
					$parts[] = '<a href="' . esc_url( $link ) . '">' . esc_html( $label ) . '</a>';
				} else {
					$parts[] = esc_html( $label );
				}
			}
			$location_html = implode( ', ', $parts );
		}
	}

	// Past-show data: featured image, song count, rarities line
	$thumbnail_id = $is_upcoming ? 0 : get_post_thumbnail_id( $show_id );
	$song_count = $is_upcoming ? 0 : (int) get_post_meta( $show_id, '_show_song_count', true );
	$rarities_parts = array();
	if ( ! $is_upcoming && function_exists( 'jww_get_show_setlist_highlights_debuts' ) && function_exists( 'jww_get_show_setlist_highlights_standout' ) ) {
		$debuts = jww_get_show_setlist_highlights_debuts( $show_id );
		$standout = jww_get_show_setlist_highlights_standout( $show_id );
		$unique = isset( $standout['unique_songs'] ) ? $standout['unique_songs'] : array();
		if ( ! empty( $debuts ) ) {
			$links = array();
			foreach ( $debuts as $item ) {
				$links[] = '<a href="' . esc_url( $item['song_link'] ) . '">' . esc_html( $item['song_title'] ) . '</a>';
			}
			$rarities_parts[] = _x( 'Live debuts:', 'Tour timeline rarities label', 'jww-theme' ) . ' ' . implode( ', ', $links );
		}
		if ( ! empty( $unique ) ) {
			$links = array();
			foreach ( $unique as $item ) {
				$links[] = '<a href="' . esc_url( $item['song_link'] ) . '">' . esc_html( $item['song_title'] ) . '</a>';
			}
			$rarities_parts[] = _x( 'Rarities:', 'Tour timeline rarities label', 'jww-theme' ) . ' ' . implode( ', ', $links );
		}
	}
	$rarities_html = empty( $rarities_parts ) ? '' : implode( '. ', $rarities_parts );

	$is_last = ( $index === count( $all_shows ) - 1 );

	echo '<div class="timeline-item' . ( $is_upcoming ? ' upcoming' : '' ) . '">';
	echo '<div class="timeline-marker"></div>';
	echo '<div class="timeline-content">';

	echo '<div class="timeline-content-body">';
	// Date at top
	echo '<div class="timeline-date">' . esc_html( $show_date ) . '</div>';

	// Title (link to show)
	echo '<h3 class="timeline-title"><a href="' . esc_url( $show_link ) . '">' . esc_html( $show_title ) . '</a></h3>';

	// Full location: venue, city, state (if any), country (abbrev if available); each term clickable
	if ( $location_html ) {
		echo '<div class="timeline-location">' . $location_html . '</div>';
	}

	// Song count (past shows only)
	if ( $song_count > 0 ) {
		echo '<div class="timeline-song-count">';
		printf(
			esc_html( _n( '%s song', '%s songs', $song_count, 'jww-theme' ) ),
			(int) $song_count
		);
		echo '</div>';
	}

	// Rarities / live debuts line (past shows only)
	if ( $rarities_html !== '' ) {
		echo '<div class="timeline-rarities">' . wp_kses_post( $rarities_html ) . '</div>';
	}

	// Tickets (upcoming only)
	if ( $is_upcoming ) {
		$ticket_link = get_field( 'ticket_link', $show_id );
		if ( $ticket_link ) {
			echo '<div class="timeline-tickets"><a href="' . esc_url( $ticket_link ) . '" target="_blank" rel="noopener" class="ticket-link">' . esc_html__( 'Get Tickets', 'jww-theme' ) . '</a></div>';
		}
	}
	echo '</div>'; // .timeline-content-body

	// Featured image (past shows only, right side, vertical aspect ratio, clickable to show)
	if ( $thumbnail_id ) {
		$thumb_src = wp_get_attachment_image_url( $thumbnail_id, 'medium' );
		if ( $thumb_src ) {
			echo '<a href="' . esc_url( $show_link ) . '" class="timeline-featured-image-link">';
			echo wp_get_attachment_image( $thumbnail_id, 'medium', false, array( 'class' => 'timeline-featured-image', 'loading' => 'lazy', 'decoding' => 'async' ) );
			echo '</a>';
		}
	}

	echo '</div>';
	echo '</div>';
}

echo '</div>'; // .timeline-container
echo '</div>'; // .tour-timeline-block
