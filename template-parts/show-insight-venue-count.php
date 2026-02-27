<?php
/**
 * Template part: Show insight — Total shows at this venue (with link to venue).
 * Used on single show page when show has a location. Logic: jww_get_shows_by_venue, jww_get_location_hierarchy in includes/show-functions.php.
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
$count = is_array( $shows_at_venue ) ? count( $shows_at_venue ) : 0;
if ( $count <= 0 ) {
	return;
}
?>

<div class="show-insight-venue-count tour-overview-card show-tour-stats-card masonry-card--half wp-block-group alignwide has-global-padding" id="show-insight-venue-count" aria-labelledby="show-insight-venue-count-heading">
	<div class="show-setlist-data-card-header-wrapper">
		<h2 id="show-insight-venue-count-heading" class="wp-block-heading show-tour-stats-heading"><?php esc_html_e( 'Shows at venue', 'jww-theme' ); ?></h2>
		<span class="show-setlist-data-card-info" title="<?php esc_attr_e( 'Total shows we have at this venue.', 'jww-theme' ); ?>">
			<span class="dashicons dashicons-location" aria-hidden="true"></span>
		</span>
	</div>
	<p class="show-tour-stats-meta tour-overview-value tour-insight-stat-value">
		<strong><?php echo (int) $count; ?></strong>
	</p>
	<?php if ( $venue_name && $venue_link && ! is_wp_error( $venue_link ) ) : ?>
		<p class="show-tour-stats-meta show-insight-venue-count-link">
			<a href="<?php echo esc_url( $venue_link ); ?>"><?php echo esc_html( $venue_name ); ?></a>
		</p>
	<?php endif; ?>
</div>
