<?php
/**
 * Template part: Location insight — Overview cards (Total, Upcoming, Past, Venues, Shows with setlist data).
 * Used on taxonomy-location only. Expects query vars: location_total_shows, location_upcoming_count, location_past_count, location_venues_count, location_shows_with_data_count, location_openers, location_closers.
 *
 * @package JWW_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$total_shows   = (int) get_query_var( 'location_total_shows', 0 );
$upcoming      = (int) get_query_var( 'location_upcoming_count', 0 );
$past          = (int) get_query_var( 'location_past_count', 0 );
$venues_count  = (int) get_query_var( 'location_venues_count', 0 );
$shows_w_data  = (int) get_query_var( 'location_shows_with_data_count', 0 );

$cards = array(
	array(
		'id'     => 'location-overview-total',
		'label'  => __( 'Total shows', 'jww-theme' ),
		'value'  => $total_shows,
		'icon'   => 'dashicons-tickets-alt',
		'title'  => __( 'Total number of shows at this location.', 'jww-theme' ),
	),
	array(
		'id'     => 'location-overview-upcoming',
		'label'  => __( 'Upcoming', 'jww-theme' ),
		'value'  => $upcoming,
		'icon'   => 'dashicons-clock',
		'title'  => __( 'Shows that haven\'t happened yet.', 'jww-theme' ),
	),
	array(
		'id'     => 'location-overview-past',
		'label'  => __( 'Past shows', 'jww-theme' ),
		'value'  => $past,
		'icon'   => 'dashicons-calendar-alt',
		'title'  => __( 'Shows that have already taken place.', 'jww-theme' ),
	),
	array(
		'id'     => 'location-overview-venues',
		'label'  => __( 'Venues', 'jww-theme' ),
		'value'  => $venues_count,
		'icon'   => 'dashicons-location',
		'title'  => __( 'Number of venues in this locale.', 'jww-theme' ),
	),
	array(
		'id'     => 'location-overview-shows-with-data',
		'label'  => __( 'Shows with setlist data', 'jww-theme' ),
		'value'  => $shows_w_data,
		'icon'   => 'dashicons-playlist-audio',
		'title'  => __( 'Shows that have setlist data.', 'jww-theme' ),
	),
);

$location_openers = get_query_var( 'location_openers', array() );
$location_closers = get_query_var( 'location_closers', array() );
if ( ! empty( $location_openers ) ) {
	$cards[] = array(
		'id'        => 'location-overview-openers',
		'label'     => __( 'Popular Openers', 'jww-theme' ),
		'title'     => __( 'Top 3 songs that opened shows at this location.', 'jww-theme' ),
		'icon'      => 'dashicons-arrow-up-alt',
		'list_type' => 'openers',
		'items'     => array_slice( $location_openers, 0, 3 ),
	);
}
if ( ! empty( $location_closers ) ) {
	$cards[] = array(
		'id'        => 'location-overview-closers',
		'label'     => __( 'Popular Closers', 'jww-theme' ),
		'title'     => __( 'Top 3 songs that closed shows at this location (often the encore).', 'jww-theme' ),
		'icon'      => 'dashicons-arrow-down-alt',
		'list_type' => 'closers',
		'items'     => array_slice( $location_closers, 0, 3 ),
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
			<strong><?php echo esc_html( (string) $card['value'] ); ?></strong>
		</p>
		<?php endif; ?>
	</div>
	<?php
endforeach;
