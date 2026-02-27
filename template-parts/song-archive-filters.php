<?php
/**
 * Template part: Song archive filter form in card style (matches show-filters-card).
 * Expects query vars: song_archive_display, song_archive_sort, song_archive_song_type, song_archive_artist_id, song_archive_bands, song_archive_url.
 *
 * @package JWW_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$display    = get_query_var( 'song_archive_display', 'grid' );
$sort       = get_query_var( 'song_archive_sort', 'alpha' );
$song_type  = get_query_var( 'song_archive_song_type', 'originals' );
$artist_id  = get_query_var( 'song_archive_artist_id', '7' );
$bands      = get_query_var( 'song_archive_bands', array() );
$archive_url = get_query_var( 'song_archive_url', get_post_type_archive_link( 'song' ) );
?>
<div class="wp-block-group alignwide show-filters-card song-filters-card">
	<div class="show-filters-card-inner">
		<h2 id="song-filters-heading" class="wp-block-heading show-filters-card-heading"><?php esc_html_e( 'Filter songs', 'jww-theme' ); ?></h2>
		<form method="get" action="<?php echo esc_url( $archive_url ?: '' ); ?>" class="filter-form" aria-labelledby="song-filters-heading" id="song-list-filters">
			<?php if ( $display !== 'live-stats' ) : ?>
			<div class="filter-group">
				<label for="artist_id"><?php esc_html_e( 'Band', 'jww-theme' ); ?></label>
				<select name="artist_id" id="artist_id" class="filter-select auto-submit">
					<option value="all" <?php selected( $artist_id, 'all' ); ?>><?php esc_html_e( 'All', 'jww-theme' ); ?></option>
					<?php foreach ( $bands as $band ) : ?>
						<option value="<?php echo esc_attr( $band->ID ); ?>" <?php selected( $artist_id, (string) $band->ID ); ?>><?php echo esc_html( $band->post_title ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<?php endif; ?>
			<div class="filter-group">
				<label for="display"><?php esc_html_e( 'Display', 'jww-theme' ); ?></label>
				<select name="display" id="display" class="filter-select auto-submit">
					<option value="list" <?php selected( $display, 'list' ); ?>><?php esc_html_e( 'Simple List', 'jww-theme' ); ?></option>
					<option value="list-headers" <?php selected( $display, 'list-headers' ); ?>><?php esc_html_e( 'List with Headers', 'jww-theme' ); ?></option>
					<option value="grid" <?php selected( $display, 'grid' ); ?>><?php esc_html_e( 'Grid', 'jww-theme' ); ?></option>
					<option value="live-stats" <?php selected( $display, 'live-stats' ); ?>><?php esc_html_e( 'Live Stats', 'jww-theme' ); ?></option>
				</select>
			</div>
			<?php if ( $display !== 'live-stats' ) : ?>
			<div class="filter-group">
				<label for="sort"><?php esc_html_e( 'Sort by', 'jww-theme' ); ?></label>
				<select name="sort" id="sort" class="filter-select auto-submit">
					<option value="alpha" <?php selected( $sort, 'alpha' ); ?>><?php esc_html_e( 'Name (A–Z)', 'jww-theme' ); ?></option>
					<option value="date" <?php selected( $sort, 'date' ); ?>><?php esc_html_e( 'Date (newest to oldest)', 'jww-theme' ); ?></option>
				</select>
			</div>
			<div class="filter-group">
				<label for="song_type"><?php esc_html_e( 'Song type', 'jww-theme' ); ?></label>
				<select name="song_type" id="song_type" class="filter-select auto-submit">
					<option value="originals" <?php selected( $song_type, 'originals' ); ?>><?php esc_html_e( 'Originals', 'jww-theme' ); ?></option>
					<option value="covers" <?php selected( $song_type, 'covers' ); ?>><?php esc_html_e( 'Covers', 'jww-theme' ); ?></option>
					<option value="both" <?php selected( $song_type, 'both' ); ?>><?php esc_html_e( 'Originals & Covers', 'jww-theme' ); ?></option>
				</select>
			</div>
			<?php endif; ?>
			<button type="submit" class="filter-submit wp-block-button__link wp-element-button"><?php esc_html_e( 'Apply', 'jww-theme' ); ?></button>
		</form>
	</div>
</div>
