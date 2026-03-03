<?php
/**
 * Template part: Song archive Live Stats view — same card + accordion as archive-show song live stats.
 *
 * @package JWW_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'jww_get_all_time_song_stats' ) ) {
	return;
}

$song_stats_list  = jww_get_all_time_song_stats( true );
$song_stats_count = is_array( $song_stats_list ) ? count( $song_stats_list ) : 0;
$block_content    = render_block( array(
	'blockName' => 'jww/song-stats-table',
	'attrs'     => array(),
) );

if ( ! $block_content ) {
	return;
}

$default_open = true;
?>
<div class="wp-block-group alignwide shows-table-card" style="margin-bottom:var(--wp--preset--spacing--50);">
	<details class="shows-accordion" id="song-archive-live-stats-accordion"<?php echo $default_open ? ' open' : ''; ?> aria-labelledby="song-archive-live-stats-heading">
		<summary class="shows-accordion-summary">
			<h2 id="song-archive-live-stats-heading" class="wp-block-heading show-setlist-data-heading"><?php esc_html_e( 'Song Performances', 'jww-theme' ); ?></h2>
			<span class="shows-accordion-count"><?php echo esc_html( '(' . $song_stats_count . ')' ); ?></span>
		</summary>
		<div class="shows-table-wrapper">
			<?php echo $block_content; ?>
		</div>
	</details>
</div>
