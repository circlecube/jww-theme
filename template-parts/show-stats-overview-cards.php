<?php
/**
 * Template part: Show stats overview cards (Total shows, Upcoming, Past).
 * Used on archive-show and taxonomy-location. Same card style as tour overview; expects query vars show_stats_total, show_stats_upcoming, show_stats_past.
 *
 * @package JWW_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$total_shows  = (int) get_query_var( 'show_stats_total', 0 );
$upcoming     = (int) get_query_var( 'show_stats_upcoming', 0 );
$past         = (int) get_query_var( 'show_stats_past', 0 );

$cards = array(
	array(
		'id'     => 'show-overview-total',
		'label'  => __( 'Total shows', 'jww-theme' ),
		'value'  => $total_shows,
		'icon'   => 'dashicons-tickets-alt',
		'title'  => __( 'Total number of shows.', 'jww-theme' ),
	),
	array(
		'id'     => 'show-overview-upcoming',
		'label'  => __( 'Upcoming', 'jww-theme' ),
		'value'  => $upcoming,
		'icon'   => 'dashicons-clock',
		'title'  => __( 'Shows that haven\'t happened yet.', 'jww-theme' ),
	),
	array(
		'id'     => 'show-overview-past',
		'label'  => __( 'Past shows', 'jww-theme' ),
		'value'  => $past,
		'icon'   => 'dashicons-calendar-alt',
		'title'  => __( 'Shows that have already taken place.', 'jww-theme' ),
	),
);

foreach ( $cards as $card ) :
	?>
	<div class="tour-overview-card tour-insight-card--compact tour-insight-card--stat show-stats-overview-card wp-block-group alignwide has-global-padding show-tour-stats-card" id="<?php echo esc_attr( $card['id'] ); ?>" aria-labelledby="<?php echo esc_attr( $card['id'] ); ?>-heading">
		<div class="show-setlist-data-card-header-wrapper">
			<h2 id="<?php echo esc_attr( $card['id'] ); ?>-heading" class="wp-block-heading show-tour-stats-heading"><?php echo esc_html( $card['label'] ); ?></h2>
			<span class="show-setlist-data-card-info" title="<?php echo esc_attr( $card['title'] ); ?>">
				<span class="dashicons <?php echo esc_attr( $card['icon'] ); ?>" aria-hidden="true"></span>
			</span>
		</div>
		<p class="show-tour-stats-meta tour-overview-value tour-insight-stat-value">
			<strong><?php echo esc_html( (string) $card['value'] ); ?></strong>
		</p>
	</div>
	<?php
endforeach;
