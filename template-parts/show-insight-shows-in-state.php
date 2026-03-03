<?php
/**
 * Template part: Show insight — All other shows in the same state/province.
 * Only displays when the country has states/provinces and there is at least one other show in the state. Used on single show page.
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
// Use queried object so we always exclude the show page we're viewing (reliable when template part runs after loop).
if ( is_singular( 'show' ) ) {
	$show_id = get_queried_object_id();
}

$location_id = get_field( 'show_location', $show_id );
if ( ! $location_id ) {
	return;
}

if ( ! function_exists( 'jww_get_location_hierarchy' ) || ! function_exists( 'jww_get_shows_by_state' ) || ! function_exists( 'jww_country_has_states' ) ) {
	return;
}

$hierarchy = jww_get_location_hierarchy( $location_id );
$state_term_id = isset( $hierarchy['state_term_id'] ) ? (int) $hierarchy['state_term_id'] : 0;
$state_name = isset( $hierarchy['state_name'] ) ? $hierarchy['state_name'] : '';
$state_link = isset( $hierarchy['state_link'] ) ? $hierarchy['state_link'] : '';

if ( ! $state_term_id ) {
	return;
}

$state_term = get_term( $state_term_id, 'location' );
if ( ! $state_term || is_wp_error( $state_term ) || ! $state_term->parent ) {
	return;
}

$country_term_id = (int) $state_term->parent;
if ( ! jww_country_has_states( $country_term_id ) ) {
	return;
}

$shows_in_state = jww_get_shows_by_state( $state_term_id );
$current_show_id = (int) $show_id;
$other_shows = array();
foreach ( $shows_in_state as $show_post ) {
	$id = (int) $show_post->ID;
	if ( $id !== $current_show_id ) {
		$other_shows[] = $show_post;
	}
}

if ( empty( $other_shows ) ) {
	return;
}
?>

<div class="show-insight-shows-in-state tour-overview-card show-tour-stats-card masonry-card--half wp-block-group alignwide has-global-padding" id="show-insight-shows-in-state" aria-labelledby="show-insight-shows-in-state-heading">
	<div class="show-setlist-data-card-header-wrapper">
		<h2 id="show-insight-shows-in-state-heading" class="wp-block-heading show-tour-stats-heading"><?php esc_html_e( 'Other shows in this state', 'jww-theme' ); ?></h2>
		<span class="show-setlist-data-card-info" title="<?php esc_attr_e( 'All other shows in the same state or province.', 'jww-theme' ); ?>">
			<span class="dashicons dashicons-location-alt" aria-hidden="true"></span>
		</span>
	</div>
	<?php if ( $state_name && $state_link && ! is_wp_error( $state_link ) ) : ?>
		<p class="show-tour-stats-meta show-insight-state-count-link">
			<a href="<?php echo esc_url( $state_link ); ?>"><?php echo esc_html( $state_name ); ?></a>
		</p>
	<?php endif; ?>
	<ul class="show-tour-stats-meta tour-overview-value archive-insight-tours-list show-insight-shows-in-state-list">
		<?php foreach ( $other_shows as $other_show ) : ?>
			<li class="archive-insight-tour-item show-insight-state-show-item">
				<a href="<?php echo esc_url( get_permalink( $other_show->ID ) ); ?>"><?php echo esc_html( get_the_title( $other_show->ID ) ); ?></a>
				<!-- <span class="archive-insight-date-range"><?php echo esc_html( get_the_date( 'M j, Y', $other_show->ID ) ); ?></span> -->
			</li>
		<?php endforeach; ?>
	</ul>
</div>
