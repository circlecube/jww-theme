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

// Shows with setlist data (at least one song)
$shows_with_data_count = 0;
foreach ( $shows as $show ) {
	$setlist = get_field( 'setlist', $show->ID );
	if ( function_exists( 'jww_count_setlist_songs' ) && jww_count_setlist_songs( $setlist ) > 0 ) {
		$shows_with_data_count++;
	}
}

// Query vars for location overview cards
set_query_var( 'location_total_shows', $total_shows );
set_query_var( 'location_upcoming_count', $upcoming_count );
set_query_var( 'location_past_count', $past_count );
set_query_var( 'location_venues_count', function_exists( 'jww_get_location_venue_count' ) ? jww_get_location_venue_count( $location_term->term_id ) : 0 );
set_query_var( 'location_shows_with_data_count', $shows_with_data_count );

$location_opener_closer = function_exists( 'jww_get_location_opener_closer' ) ? jww_get_location_opener_closer( $location_term->term_id ) : array( 'openers' => array(), 'closers' => array() );
set_query_var( 'location_openers', $location_opener_closer['openers'] ?? array() );
set_query_var( 'location_closers', $location_opener_closer['closers'] ?? array() );

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
?>

<main class="wp-block-group align alignwide is-layout-flow wp-block-group-is-layout-flow">
	<div
		class="wp-block-group has-global-padding" 
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

		<h2 id="location-insights-heading" class="show-setlist-data-heading wp-block-heading"><?php esc_html_e( 'Locale-Based Insights', 'jww-theme' ); ?></h2>
		<div class="wp-block-group alignwide show-stats-cards show-stats-cards-masonry" id="location-stats-cards-masonry" style="margin-bottom:var(--wp--preset--spacing--50);">
			<?php get_template_part( 'template-parts/location-insight-overview-cards' ); ?>
		</div>

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
			) );
		endif;

		if ( $has_past ) :
			$past_title = $section_title !== null ? $section_title : __( 'Past Shows', 'jww-theme' );
			jww_render_shows_table_card( $past_shows, array(
				'title'            => $past_title,
				'table_type'       => 'past',
				'show_tour_column' => true,
				'default_open'     => true,
			) );
		endif;
		?>

		<?php if ( ! $has_upcoming && ! $has_past ): ?>
			<p>No shows found for this location.</p>
		<?php endif; ?>
	</div>
</main>

<?php get_footer(); ?>
