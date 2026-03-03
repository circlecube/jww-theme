<?php
/**
 * Template part: Show insight — All other shows in the same city.
 * Only displays when there is at least one other show in the city. Used on single show page.
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

if ( ! function_exists( 'jww_get_location_hierarchy' ) || ! function_exists( 'jww_get_shows_by_city' ) ) {
	return;
}

$hierarchy = jww_get_location_hierarchy( $location_id );
$city_term_id = isset( $hierarchy['city_term_id'] ) ? (int) $hierarchy['city_term_id'] : 0;
$city_name = isset( $hierarchy['city_name'] ) ? $hierarchy['city_name'] : '';
$city_link = isset( $hierarchy['city_link'] ) ? $hierarchy['city_link'] : '';

if ( ! $city_term_id ) {
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
$current_show_id = (int) $show_id;
$other_shows = array();
foreach ( $shows_in_city as $show_post ) {
	if ( (int) $show_post->ID !== $current_show_id ) {
		$other_shows[] = $show_post;
	}
}

if ( empty( $other_shows ) ) {
	return;
}
?>

<div class="show-insight-shows-in-city tour-overview-card show-tour-stats-card masonry-card--half wp-block-group alignwide has-global-padding" id="show-insight-shows-in-city" aria-labelledby="show-insight-shows-in-city-heading">
	<div class="show-setlist-data-card-header-wrapper">
		<h2 id="show-insight-shows-in-city-heading" class="wp-block-heading show-tour-stats-heading"><?php esc_html_e( 'Other shows in this city', 'jww-theme' ); ?></h2>
		<span class="show-setlist-data-card-info" title="<?php esc_attr_e( 'All other shows in the same city.', 'jww-theme' ); ?>">
			<span class="dashicons dashicons-location-alt" aria-hidden="true"></span>
		</span>
	</div>
	<?php if ( $city_name && $city_link && ! is_wp_error( $city_link ) ) : ?>
		<p class="show-tour-stats-meta show-insight-city-count-link">
			<a href="<?php echo esc_url( $city_link ); ?>"><?php echo esc_html( $city_name ); ?></a>
		</p>
	<?php endif; ?>
	<ul class="show-tour-stats-meta tour-overview-value archive-insight-tours-list show-insight-shows-in-city-list">
		<?php foreach ( $other_shows as $other_show ) : ?>
			<li class="archive-insight-tour-item show-insight-city-show-item">
				<a href="<?php echo esc_url( get_permalink( $other_show->ID ) ); ?>"><?php echo esc_html( get_the_title( $other_show->ID ) ); ?></a>
				<!-- <span class="archive-insight-date-range"><?php echo esc_html( get_the_date( 'M j, Y', $other_show->ID ) ); ?></span> -->
			</li>
		<?php endforeach; ?>
	</ul>
</div>
