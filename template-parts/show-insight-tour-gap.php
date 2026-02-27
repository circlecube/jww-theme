<?php
/**
 * Template part: Show insight — Gap in tour (days since previous show).
 * Used on single show page when show is in a tour and not the first show. Logic in includes/show-functions.php.
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

if ( ! get_field( 'show_tour', $show_id ) ) {
	return;
}

if ( ! function_exists( 'jww_get_show_tour_gap_days' ) ) {
	return;
}

$gap_days = jww_get_show_tour_gap_days( $show_id );
if ( $gap_days === null ) {
	return;
}
?>

<div class="show-insight-tour-gap tour-overview-card show-tour-stats-card masonry-card--half wp-block-group alignwide has-global-padding" id="show-insight-tour-gap" aria-labelledby="show-insight-tour-gap-heading">
	<div class="show-setlist-data-card-header-wrapper">
		<h2 id="show-insight-tour-gap-heading" class="wp-block-heading show-tour-stats-heading"><?php esc_html_e( 'Gap in tour', 'jww-theme' ); ?></h2>
		<span class="show-setlist-data-card-info" title="<?php esc_attr_e( 'Days since the previous show in this tour.', 'jww-theme' ); ?>">
			<span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
		</span>
	</div>
	<p class="show-tour-stats-meta tour-overview-value tour-insight-stat-value">
		<strong><?php echo (int) $gap_days; ?></strong> <?php echo esc_html( _n( 'day', 'days', $gap_days, 'jww-theme' ) ); ?>
	</p>
</div>
