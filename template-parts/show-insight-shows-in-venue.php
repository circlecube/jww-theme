<?php
/**
 * Template part: Show insight — All other shows at this venue.
 * Only displays when there is at least one other show at the same venue. Used on single show page.
 *
 * @package JWW_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$show_id = get_the_ID();
if ( ! $show_id || get_post_type() !== 'show' ) {
	return;
}
if ( is_singular( 'show' ) ) {
	$show_id = get_queried_object_id();
}

$location_id = get_field( 'show_location', $show_id );
if ( ! $location_id ) {
	return;
}

if ( ! function_exists( 'jww_get_location_hierarchy' ) || ! function_exists( 'jww_get_shows_by_venue' ) ) {
	return;
}

$hierarchy = jww_get_location_hierarchy( $location_id );
$venue_term_id = isset( $hierarchy['venue_term_id'] ) ? (int) $hierarchy['venue_term_id'] : (int) $location_id;
$venue_name = isset( $hierarchy['venue'] ) ? $hierarchy['venue'] : '';
$venue_link = isset( $hierarchy['venue_link'] ) ? $hierarchy['venue_link'] : '';

$shows_at_venue = jww_get_shows_by_venue( $venue_term_id );
$current_show_id = (int) $show_id;
$other_shows = array();
foreach ( $shows_at_venue as $show_post ) {
	if ( (int) $show_post->ID !== $current_show_id ) {
		$other_shows[] = $show_post;
	}
}

if ( empty( $other_shows ) ) {
	return;
}
?>

<div class="show-insight-shows-in-venue tour-overview-card show-tour-stats-card masonry-card--half wp-block-group alignwide has-global-padding" id="show-insight-shows-in-venue" aria-labelledby="show-insight-shows-in-venue-heading">
	<div class="show-setlist-data-card-header-wrapper">
		<h2 id="show-insight-shows-in-venue-heading" class="wp-block-heading show-tour-stats-heading"><?php esc_html_e( 'Other shows at this venue', 'jww-theme' ); ?></h2>
		<span class="show-setlist-data-card-info" title="<?php esc_attr_e( 'All other shows at this venue.', 'jww-theme' ); ?>">
			<span class="dashicons dashicons-location" aria-hidden="true"></span>
		</span>
	</div>
	<?php if ( $venue_name && $venue_link && ! is_wp_error( $venue_link ) ) : ?>
		<p class="show-tour-stats-meta show-insight-venue-count-link">
			<a href="<?php echo esc_url( $venue_link ); ?>"><?php echo esc_html( $venue_name ); ?></a>
		</p>
	<?php endif; ?>
	<ul class="show-tour-stats-meta tour-overview-value archive-insight-tours-list show-insight-shows-in-venue-list">
		<?php foreach ( $other_shows as $other_show ) : ?>
			<li class="archive-insight-tour-item show-insight-venue-show-item">
				<a href="<?php echo esc_url( get_permalink( $other_show->ID ) ); ?>"><?php echo esc_html( get_the_title( $other_show->ID ) ); ?></a>
				<!-- <span class="archive-insight-date-range"><?php echo esc_html( get_the_date( 'M j, Y', $other_show->ID ) ); ?></span> -->
			</li>
		<?php endforeach; ?>
	</ul>
</div>
