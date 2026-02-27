<?php
/**
 * Template part: Tour insight — Overview cards (Total shows, Upcoming or Cities, Past, Unique songs).
 * Used on tour archive only. Expects query vars set by taxonomy-tour. When tour has no upcoming shows, "Upcoming" is replaced by "Cities" (count of cities in the tour).
 *
 * @package JWW_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$total_shows   = (int) get_query_var( 'tour_total_shows', 0 );
$upcoming      = (int) get_query_var( 'tour_upcoming_count', 0 );
$past          = (int) get_query_var( 'tour_past_count', 0 );
$unique_songs  = (int) get_query_var( 'tour_unique_songs_count', 0 );
$tour_id       = (int) get_query_var( 'tour_id', 0 );

$cards = array(
	array(
		'id'     => 'tour-overview-total',
		'label'  => __( 'Total shows', 'jww-theme' ),
		'value'  => $total_shows,
		'icon'   => 'dashicons-tickets-alt',
		'title'  => __( 'Total number of shows in this tour.', 'jww-theme' ),
	),
);

if ( $upcoming > 0 ) {
	$cards[] = array(
		'id'     => 'tour-overview-upcoming',
		'label'  => __( 'Upcoming', 'jww-theme' ),
		'value'  => $upcoming,
		'icon'   => 'dashicons-clock',
		'title'  => __( 'Shows that haven\'t happened yet.', 'jww-theme' ),
	);
} elseif ( $tour_id && function_exists( 'jww_get_tour_cities_count' ) ) {
	$cities_count = jww_get_tour_cities_count( $tour_id );
	$cards[] = array(
		'id'     => 'tour-overview-cities',
		'label'  => __( 'Cities', 'jww-theme' ),
		'value'  => $cities_count,
		'icon'   => 'dashicons-location-alt',
		'title'  => __( 'Number of cities played in this tour.', 'jww-theme' ),
	);
}

$cards[] = array(
	'id'     => 'tour-overview-past',
	'label'  => __( 'Past shows', 'jww-theme' ),
	'value'  => $past,
	'icon'   => 'dashicons-calendar-alt',
	'title'  => __( 'Shows that have already taken place.', 'jww-theme' ),
);
$cards[] = array(
	'id'     => 'tour-overview-unique-songs',
	'label'  => __( 'Unique songs', 'jww-theme' ),
	'value'  => $unique_songs,
	'icon'   => 'dashicons-playlist-audio',
	'title'  => __( 'Distinct songs across the setlists we have for this tour.', 'jww-theme' ),
);

$venues_count   = (int) get_query_var( 'tour_venues_count', 0 );
$shows_w_data   = (int) get_query_var( 'tour_shows_with_data_count', 0 );
$cards[] = array(
	'id'     => 'tour-overview-venues',
	'label'  => __( 'Venues', 'jww-theme' ),
	'value'  => $venues_count,
	'icon'   => 'dashicons-location',
	'title'  => __( 'Number of venues played in this tour.', 'jww-theme' ),
	'size'   => 'half',
);
$cards[] = array(
	'id'     => 'tour-overview-shows-with-data',
	'label'  => __( 'Shows with setlist data', 'jww-theme' ),
	'value'  => $shows_w_data,
	'icon'   => 'dashicons-playlist-audio',
	'title'  => __( 'Shows that have setlist data.', 'jww-theme' ),
	'size'   => 'half',
);

$tour_festivals = (int) get_query_var( 'tour_festivals_count', 0 );
if ( $tour_festivals > 0 ) {
	$cards[] = array(
		'id'     => 'tour-overview-festivals',
		'label'  => __( 'Festival shows', 'jww-theme' ),
		'value'  => $tour_festivals,
		'icon'   => 'dashicons-flag',
		'title'  => __( 'Shows in this tour that were part of a festival.', 'jww-theme' ),
		'size'   => 'half',
	);
}

$tour_openers = get_query_var( 'tour_openers', array() );
$tour_closers = get_query_var( 'tour_closers', array() );
if ( ! empty( $tour_openers ) ) {
	$cards[] = array(
		'id'        => 'tour-overview-openers',
		'label'     => __( 'Popular Openers', 'jww-theme' ),
		'title'     => __( 'Top 3 songs that opened shows in this tour.', 'jww-theme' ),
		'icon'      => 'dashicons-arrow-up-alt',
		'size'      => 'half',
		'list_type' => 'openers',
		'items'     => array_slice( $tour_openers, 0, 3 ),
	);
}
if ( ! empty( $tour_closers ) ) {
	$cards[] = array(
		'id'        => 'tour-overview-closers',
		'label'     => __( 'Popular Closers', 'jww-theme' ),
		'title'     => __( 'Top 3 songs that closed shows in this tour (often the encore).', 'jww-theme' ),
		'icon'      => 'dashicons-arrow-down-alt',
		'size'      => 'half',
		'list_type' => 'closers',
		'items'     => array_slice( $tour_closers, 0, 3 ),
	);
}

foreach ( $cards as $card ) :
	$is_full_width = ! empty( $card['list_type'] );
	$card_class = $is_full_width ? 'tour-overview-card tour-insight-card--compact tour-insight-card--stat wp-block-group alignwide has-global-padding show-tour-stats-card' : 'tour-overview-card tour-insight-card--compact tour-insight-card--stat masonry-card--half wp-block-group alignwide has-global-padding show-tour-stats-card';
	?>
	<div class="<?php echo esc_attr( $card_class ); ?>" id="<?php echo esc_attr( $card['id'] ); ?>" aria-labelledby="<?php echo esc_attr( $card['id'] ); ?>-heading">
		<div class="show-setlist-data-card-header-wrapper">
			<h2 id="<?php echo esc_attr( $card['id'] ); ?>-heading" class="wp-block-heading show-tour-stats-heading"><?php echo esc_html( $card['label'] ); ?></h2>
			<span class="show-setlist-data-card-info" title="<?php echo esc_attr( $card['title'] ); ?>">
				<span class="dashicons <?php echo esc_attr( $card['icon'] ); ?>" aria-hidden="true"></span>
			</span>
		</div>
		<?php if ( ! empty( $card['list_type'] ) && ! empty( $card['items'] ) ) : ?>
			<ol class="show-tour-stats-meta tour-overview-value tour-insight-stat-value archive-insight-ranked-list">
				<?php foreach ( $card['items'] as $item ) : ?>
					<li>
						<?php if ( ! empty( $item['link'] ) ) : ?>
							<a href="<?php echo esc_url( $item['link'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $item['title'] ); ?>
						<?php endif; ?>
						<?php if ( ! empty( $item['count'] ) ) : ?>
							<span class="archive-insight-count">(<?php echo (int) $item['count']; ?>×)</span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>
		<?php else : ?>
		<p class="show-tour-stats-meta tour-overview-value tour-insight-stat-value">
			<?php
			if ( ! empty( $card['value_link'] ) ) {
				echo '<a href="' . esc_url( $card['value_link'] ) . '">' . esc_html( (string) $card['value'] ) . '</a>';
			} else {
				echo '<strong>' . esc_html( (string) $card['value'] ) . '</strong>';
			}
			?>
		</p>
		<?php endif; ?>
	</div>
	<?php
endforeach;
