<?php
/**
 * Template part: Show insight — Total shows in this city (with link to city archive).
 * Used on single show page when location has a city (parent term). Logic: jww_get_shows_by_city, jww_get_location_hierarchy in includes/show-functions.php.
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

if ( ! function_exists( 'jww_get_location_hierarchy' ) || ! function_exists( 'jww_get_shows_by_city' ) ) {
	return;
}

$hierarchy = jww_get_location_hierarchy( $location_id );
$city_term_id = isset( $hierarchy['city_term_id'] ) ? (int) $hierarchy['city_term_id'] : 0;
$city_name = isset( $hierarchy['city_name'] ) ? $hierarchy['city_name'] : '';
$city_link = isset( $hierarchy['city_link'] ) ? $hierarchy['city_link'] : '';

// Fallback: cached hierarchy may not have city_term_id (e.g. old cache). Derive city from venue's parent.
if ( ! $city_term_id && $location_id ) {
	$loc_term = get_term( (int) $location_id, 'location' );
	if ( $loc_term && ! is_wp_error( $loc_term ) && $loc_term->parent ) {
		$city_term_id = (int) $loc_term->parent;
		$city_term = get_term( $city_term_id, 'location' );
		if ( $city_term && ! is_wp_error( $city_term ) ) {
			$city_name = $city_term->name;
			$city_link = get_term_link( $city_term, 'location' );
			if ( is_wp_error( $city_link ) ) {
				$city_link = '';
			}
		}
	}
}

if ( ! $city_term_id ) {
	return;
}

$shows_in_city = jww_get_shows_by_city( $city_term_id );
$count = is_array( $shows_in_city ) ? count( $shows_in_city ) : 0;
if ( $count <= 0 ) {
	return;
}
?>

<div class="show-insight-city-count tour-overview-card show-tour-stats-card masonry-card--half wp-block-group alignwide has-global-padding" id="show-insight-city-count" aria-labelledby="show-insight-city-count-heading">
	<div class="show-setlist-data-card-header-wrapper">
		<h2 id="show-insight-city-count-heading" class="wp-block-heading show-tour-stats-heading"><?php esc_html_e( 'Shows in city', 'jww-theme' ); ?></h2>
		<span class="show-setlist-data-card-info" title="<?php esc_attr_e( 'Total shows we have in this city.', 'jww-theme' ); ?>">
			<span class="dashicons dashicons-location-alt" aria-hidden="true"></span>
		</span>
	</div>
	<p class="show-tour-stats-meta tour-overview-value tour-insight-stat-value">
		<strong><?php echo (int) $count; ?></strong>
	</p>
	<?php if ( $city_name && $city_link && ! is_wp_error( $city_link ) ) : ?>
		<p class="show-tour-stats-meta show-insight-city-count-link">
			<a href="<?php echo esc_url( $city_link ); ?>"><?php echo esc_html( $city_name ); ?></a>
		</p>
	<?php endif; ?>
</div>
