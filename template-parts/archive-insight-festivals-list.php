<?php
/**
 * Template part: All-time festivals list (unique festival names with date).
 * Used on main Shows archive and single show Setlist Insights. Data from query var or jww_get_all_time_festivals_list().
 *
 * @package JWW_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$festivals = get_query_var( 'archive_all_time_festivals_list', null );
if ( $festivals === null && function_exists( 'jww_get_all_time_festivals_list' ) ) {
	$festivals = jww_get_all_time_festivals_list( true );
}
if ( ! is_array( $festivals ) || empty( $festivals ) ) {
	return;
}
?>

<div class="tour-overview-card wp-block-group alignwide has-global-padding show-tour-stats-card archive-insight-festivals-list-card" aria-labelledby="archive-all-time-festivals-list-heading">
	<div class="show-setlist-data-card-header-wrapper">
		<h2 id="archive-all-time-festivals-list-heading" class="wp-block-heading show-tour-stats-heading"><?php esc_html_e( 'Festivals', 'jww-theme' ); ?></h2>
		<span class="show-setlist-data-card-info" title="<?php esc_attr_e( 'Festivals played. Newest first.', 'jww-theme' ); ?>">
			<span class="dashicons dashicons-buddicons-groups" aria-hidden="true"></span>
		</span>
	</div>
	<ul class="show-tour-stats-meta tour-overview-value archive-insight-festivals-list">
		<?php foreach ( $festivals as $festival ) : ?>
			<li class="archive-insight-festival-item">
				<?php if ( ! empty( $festival['show_link'] ) ) : ?>
					<a href="<?php echo esc_url( $festival['show_link'] ); ?>"><?php echo esc_html( $festival['name'] ); ?></a>
				<?php else : ?>
					<?php echo esc_html( $festival['name'] ); ?>
				<?php endif; ?>
				<?php if ( ! empty( $festival['date'] ) ) : ?>
					<span class="archive-insight-festival-date"><?php echo esc_html( $festival['date'] ); ?></span>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
