<?php
/**
 * Template part: Setlist album stats (which albums/releases the setlist represents).
 * Similar to setlist.fm "Songs on Albums": Covers, Others, then each Album with song count and list.
 * Used on single-show.php when the show has a setlist.
 * Sections are accordions (details/summary); albums show a small cover thumbnail.
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

if ( ! function_exists( 'jww_get_show_setlist_album_stats' ) ) {
	return;
}

$stats = jww_get_show_setlist_album_stats( $show_id );
if ( empty( $stats ) ) {
	return;
}

$total_songs = array_sum( array_column( $stats, 'count' ) );
?>

<div class="show-setlist-album-stats wp-block-group alignwide has-global-padding setlistAlbumStats" aria-labelledby="show-setlist-album-stats-heading">
	<div class="show-setlist-album-stats-card">
		<h2 id="show-setlist-album-stats-heading" class="wp-block-heading show-setlist-album-stats-heading"><?php esc_html_e( 'Songs on Albums', 'jww-theme' ); ?></h2>

		<?php if ( $total_songs > 0 ) : ?>
			<div class="show-setlist-album-stats-chart" role="img" aria-label="<?php esc_attr_e( 'Album representation in this setlist', 'jww-theme' ); ?>">
				<?php foreach ( $stats as $i => $group ) : ?>
					<?php
					$count = (int) $group['count'];
					$pct = $total_songs > 0 ? round( ( $count / $total_songs ) * 100, 1 ) : 0;
					if ( $pct <= 0 ) {
						continue;
					}
					$label = $group['label'];
					?>
					<div class="show-setlist-album-stats-chart-row show-setlist-album-stats-chart-segment-<?php echo (int) $i; ?>" title="<?php echo esc_attr( $label . ': ' . $pct . '%' ); ?>">
						<span class="show-setlist-album-stats-chart-row-label"><?php echo esc_html( $label ); ?> (<?php echo (float) $pct; ?>%)</span>
						<div class="show-setlist-album-stats-chart-row-bar">
							<span class="show-setlist-album-stats-chart-row-fill" style="width: <?php echo (float) $pct; ?>%;" aria-hidden="true"></span>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<div class="show-setlist-album-stats-cards">

		<?php foreach ( $stats as $group ) : ?>
			<?php
			$label = $group['label'];
			$count = (int) $group['count'];
			$songs = $group['songs'];
			$album_id = isset( $group['album_id'] ) ? $group['album_id'] : null;
			$thumbnail_url = isset( $group['thumbnail_url'] ) ? $group['thumbnail_url'] : null;
			$details_id = 'show-album-stats-' . sanitize_title( $label ) . '-' . ( $album_id ? $album_id : 'group' );
			?>
			<details class="show-setlist-album-stats-details" id="<?php echo esc_attr( $details_id ); ?>">
				<summary class="show-setlist-album-stats-summary">
					<?php if ( $thumbnail_url ) : ?>
						<span class="show-setlist-album-stats-thumb">
							<img src="<?php echo esc_url( $thumbnail_url ); ?>" alt="" width="96" height="96" loading="lazy" />
						</span>
					<?php else : ?>
						<span class="show-setlist-album-stats-thumb show-setlist-album-stats-thumb-placeholder" aria-hidden="true"></span>
					<?php endif; ?>
					<span class="show-setlist-album-stats-summary-text">
						<?php echo esc_html( $label ); ?>
						<strong class="show-setlist-album-stats-count"><?php echo (int) $count; ?></strong>
					</span>
				</summary>
				<div class="show-setlist-album-stats-content">
					<ul class="show-setlist-album-stats-songs">
						<?php foreach ( $songs as $song ) : ?>
							<li>
								<?php if ( ! empty( $song['link'] ) ) : ?>
									<a href="<?php echo esc_url( $song['link'] ); ?>"><?php echo esc_html( $song['title'] ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $song['title'] ); ?>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
					<?php if ( $album_id && get_post_type( $album_id ) === 'album' ) : ?>
						<p class="show-setlist-album-stats-album-link">
							<a href="<?php echo esc_url( get_permalink( $album_id ) ); ?>"><?php esc_html_e( 'View album', 'jww-theme' ); ?> →</a>
						</p>
					<?php endif; ?>
				</div>
			</details>
		<?php endforeach; ?>

		</div>
	</div>
</div>
