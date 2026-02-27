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

// Calculate tour statistics (from setlists we have)
$unique_songs = array();
foreach ( $shows as $show ) {
	$setlist = get_field( 'setlist', $show->ID );
	if ( $setlist && is_array( $setlist ) ) {
		foreach ( $setlist as $item ) {
			if ( isset( $item['entry_type'] ) && $item['entry_type'] === 'song-post' && ! empty( $item['song'] ) ) {
				$song = is_array( $item['song'] ) ? $item['song'][0] : $item['song'];
				$song_id = is_object( $song ) ? $song->ID : $song;
				$unique_songs[ $song_id ] = true;
			}
		}
	}
}

// Query vars for tour overview cards and song-based cards
set_query_var( 'tour_id', $tour_term->term_id );
set_query_var( 'tour_total_shows', $total_shows );
set_query_var( 'tour_upcoming_count', $upcoming_count );
set_query_var( 'tour_past_count', $past_count );
set_query_var( 'tour_unique_songs_count', count( $unique_songs ) );
$tour_song_counts = function_exists( 'jww_get_tour_song_counts' ) ? jww_get_tour_song_counts( $tour_term->term_id ) : array( 'show_count' => 0 );
set_query_var( 'tour_shows_with_data_count', (int) ( $tour_song_counts['show_count'] ?? 0 ) );
set_query_var( 'tour_venues_count', function_exists( 'jww_get_tour_venues_count' ) ? jww_get_tour_venues_count( $tour_term->term_id ) : 0 );
$tour_opener_closer = function_exists( 'jww_get_tour_opener_closer' ) ? jww_get_tour_opener_closer( $tour_term->term_id ) : array( 'openers' => array(), 'closers' => array() );
set_query_var( 'tour_openers', $tour_opener_closer['openers'] ?? array() );
set_query_var( 'tour_closers', $tour_opener_closer['closers'] ?? array() );
set_query_var( 'tour_festivals_count', function_exists( 'jww_get_tour_festivals_count' ) ? jww_get_tour_festivals_count( $tour_term->term_id ) : 0 );
?>

<main class="wp-block-group align is-layout-flow wp-block-group-is-layout-flow">
	<div
		class="wp-block-group has-global-padding alignwide" 
		style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50)"
	>
		<h1 class="wp-block-post-title alignwide has-xxx-large-font-size">
			<?php echo esc_html( $tour_term->name ); ?> Tour
		</h1>
		
		<p class="back-link" style="margin-bottom:var(--wp--preset--spacing--30);">
			<a href="<?php echo esc_url( get_post_type_archive_link( 'show' ) ); ?>">← All Shows</a>
		</p>

		<h2 id="tour-insights-heading" class="show-setlist-data-heading wp-block-heading"><?php esc_html_e( 'Tour Insights', 'jww-theme' ); ?></h2>
		<div class="wp-block-group alignwide show-stats-cards show-stats-cards-masonry" id="tour-stats-cards-masonry" style="margin-bottom:var(--wp--preset--spacing--50);">
			<?php get_template_part( 'template-parts/tour-insight-overview-cards' ); ?>
			<?php get_template_part( 'template-parts/show-tour-stats' ); ?>
			<?php get_template_part( 'template-parts/tour-insight-release-representation' ); ?>
			<?php get_template_part( 'template-parts/tour-insight-standout' ); ?>
			<?php get_template_part( 'template-parts/tour-insight-debuts' ); ?>
			<?php get_template_part( 'template-parts/tour-insight-one-offs' ); ?>
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
				'show_tour_column' => false,
				'default_open'    => true,
			) );
		endif;

		if ( $has_past ) :
			$past_title = $section_title !== null ? $section_title : __( 'Past Shows', 'jww-theme' );
			jww_render_shows_table_card( $past_shows, array(
				'title'            => $past_title,
				'table_type'       => 'past',
				'show_tour_column' => false,
				'default_open'     => ! $has_upcoming,
			) );
		endif;
		?>

		<?php if ( ! $has_upcoming && ! $has_past ): ?>
			<p>No shows found for this tour.</p>
		<?php endif; ?>
	</div>
</main>

<?php get_footer(); ?>
