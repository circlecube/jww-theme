<?php
/**
 * Template part: Show insight — Total shows in this state/province (with link to state archive).
 * Used on single show page when location has a state and country has states. Only displays when there is at least one show in the state.
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
$count = is_array( $shows_in_state ) ? count( $shows_in_state ) : 0;
if ( $count <= 0 ) {
	return;
}
?>

<div class="show-insight-state-count tour-overview-card show-tour-stats-card masonry-card--half wp-block-group alignwide has-global-padding" id="show-insight-state-count" aria-labelledby="show-insight-state-count-heading">
	<div class="show-setlist-data-card-header-wrapper">
		<h2 id="show-insight-state-count-heading" class="wp-block-heading show-tour-stats-heading"><?php esc_html_e( 'Shows in state', 'jww-theme' ); ?></h2>
		<span class="show-setlist-data-card-info" title="<?php esc_attr_e( 'Total shows we have in this state or province.', 'jww-theme' ); ?>">
			<span class="dashicons dashicons-location-alt" aria-hidden="true"></span>
		</span>
	</div>
	<p class="show-tour-stats-meta tour-overview-value tour-insight-stat-value">
		<strong><?php echo (int) $count; ?></strong>
	</p>
	<?php if ( $state_name && $state_link && ! is_wp_error( $state_link ) ) : ?>
		<p class="show-tour-stats-meta show-insight-state-count-link">
			<a href="<?php echo esc_url( $state_link ); ?>"><?php echo esc_html( $state_name ); ?></a>
		</p>
	<?php endif; ?>
</div>
