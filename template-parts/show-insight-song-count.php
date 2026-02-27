<?php
/**
 * Template part: Show insight — Total songs in setlist (quick stat card).
 * Used on single show page when setlist exists. Logic: jww_count_setlist_songs in includes/show-functions.php.
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

$setlist = get_field( 'setlist', $show_id );
if ( ! $setlist || ! is_array( $setlist ) ) {
	return;
}

if ( ! function_exists( 'jww_count_setlist_songs' ) ) {
	return;
}

$count = jww_count_setlist_songs( $setlist );
if ( $count <= 0 ) {
	return;
}
?>

<div class="show-insight-song-count tour-overview-card show-tour-stats-card masonry-card--half wp-block-group alignwide has-global-padding" id="show-insight-song-count" aria-labelledby="show-insight-song-count-heading">
	<div class="show-setlist-data-card-header-wrapper">
		<h2 id="show-insight-song-count-heading" class="wp-block-heading show-tour-stats-heading"><?php esc_html_e( 'Songs played', 'jww-theme' ); ?></h2>
		<span class="show-setlist-data-card-info" title="<?php esc_attr_e( 'Total songs in this setlist.', 'jww-theme' ); ?>">
			<span class="dashicons dashicons-playlist-audio" aria-hidden="true"></span>
		</span>
	</div>
	<p class="show-tour-stats-meta tour-overview-value tour-insight-stat-value">
		<strong><?php echo (int) $count; ?></strong>
	</p>
</div>
