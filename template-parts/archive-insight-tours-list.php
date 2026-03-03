<?php
/**
 * Template part: All-time tours list (all tours with show count and date range).
 * Used on main Shows archive and single show Setlist Insights. Data from query var or jww_get_all_time_tours_list().
 *
 * @package JWW_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tours = get_query_var( 'archive_all_time_tours_list', null );
if ( $tours === null && function_exists( 'jww_get_all_time_tours_list' ) ) {
	$tours = jww_get_all_time_tours_list( true );
}
if ( ! is_array( $tours ) || empty( $tours ) ) {
	return;
}
?>

<div class="tour-overview-card wp-block-group alignwide has-global-padding show-tour-stats-card archive-insight-tours-list-card" aria-labelledby="archive-all-time-tours-list-heading">
	<div class="show-setlist-data-card-header-wrapper">
		<h2 id="archive-all-time-tours-list-heading" class="wp-block-heading show-tour-stats-heading"><?php esc_html_e( 'Tours', 'jww-theme' ); ?></h2>
		<span class="show-setlist-data-card-info" title="<?php esc_attr_e( 'All tours with show count and date range. Newest first.', 'jww-theme' ); ?>">
			<span class="dashicons dashicons-flag" aria-hidden="true"></span>
		</span>
	</div>
	<ul class="show-tour-stats-meta tour-overview-value archive-insight-tours-list">
		<?php foreach ( $tours as $tour ) : ?>
			<li class="archive-insight-tour-item">
				<?php if ( ! empty( $tour['link'] ) ) : ?>
					<a href="<?php echo esc_url( $tour['link'] ); ?>"><?php echo esc_html( $tour['name'] ); ?></a>
				<?php else : ?>
					<?php echo esc_html( $tour['name'] ); ?>
				<?php endif; ?>
				<span class="archive-insight-count">(<?php echo (int) $tour['show_count']; ?>)</span>
				<?php if ( ! empty( $tour['date_range'] ) ) : ?>
					<span class="archive-insight-date-range"><?php echo esc_html( $tour['date_range'] ); ?></span>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
