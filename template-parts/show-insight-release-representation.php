<?php
/**
 * Template part: Show insight — Release representation (which albums/releases the setlist represents).
 * Used on single show page. Logic in includes/show-functions.php (jww_get_show_setlist_album_stats).
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
if ( empty( $stats ) || empty( $stats['groups'] ) ) {
	return;
}

$groups = $stats['groups'];
$total_entries = isset( $stats['total_entries'] ) ? (int) $stats['total_entries'] : array_sum( array_column( $groups, 'count' ) );
?>

<div class="show-setlist-album-stats wp-block-group alignwide has-global-padding setlistAlbumStats" aria-labelledby="show-setlist-album-stats-heading">
	<div class="show-setlist-album-stats-card">
		<div class="show-setlist-album-stats-header-wrapper">
			<h2 id="show-setlist-album-stats-heading" class="wp-block-heading show-setlist-album-stats-heading"><?php esc_html_e( 'Release representation', 'jww-theme' ); ?></h2>
			<span>
				<span class="dashicons dashicons-chart-pie" title="<?php esc_attr_e( 'What releases are represented in this setlist. Songs can appear on more than one release, so the numbers may not add up to 100%.', 'jww-theme' ); ?>"></span>
			</span>
		</div>

		<?php if ( $total_entries > 0 ) : ?>
			<div class="show-setlist-album-stats-chart" role="img" aria-label="<?php esc_attr_e( 'Album representation in this setlist', 'jww-theme' ); ?>">
				<?php foreach ( $groups as $i => $group ) : ?>
					<?php
					$count = (int) $group['count'];
					$pct = $total_entries > 0 ? round( ( $count / $total_entries ) * 100, 1 ) : 0;
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

		<?php foreach ( $groups as $group ) : ?>
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
							<img src="<?php echo esc_url( $thumbnail_url ); ?>" alt="" width="40" height="40" loading="lazy" />
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
					<div class="show-setlist-album-stats-content-inner">
					<?php $songs_list_class = 'show-setlist-album-stats-songs' . ( ! $album_id ? ' show-setlist-album-stats-songs-no-numbers' : '' ); ?>
					<?php if ( $album_id ) : ?>
						<ol class="<?php echo esc_attr( $songs_list_class ); ?>">
							<?php foreach ( $songs as $song ) : ?>
								<li<?php echo isset( $song['track_number'] ) && $song['track_number'] !== null ? ' value="' . esc_attr( (int) $song['track_number'] ) . '"' : ''; ?>>
									<?php if ( ! empty( $song['link'] ) ) : ?>
										<a href="<?php echo esc_url( $song['link'] ); ?>"><?php echo esc_html( $song['title'] ); ?></a>
									<?php else : ?>
										<?php echo esc_html( $song['title'] ); ?>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ol>
					<?php else : ?>
						<ul class="<?php echo esc_attr( $songs_list_class ); ?>">
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
					<?php endif; ?>
					<?php if ( $album_id && get_post_type( $album_id ) === 'album' ) : ?>
						<a href="<?php echo esc_url( get_permalink( $album_id ) ); ?>" class="show-setlist-album-stats-album-link">
							<?php if ( $thumbnail_url ) : ?>
								<span class="show-setlist-album-stats-album-link-img">
									<img src="<?php echo esc_url( $thumbnail_url ); ?>" alt="" width="64" height="64" loading="lazy" />
								</span>
							<?php endif; ?>
							<span class="show-setlist-album-stats-album-link-label"><?php esc_html_e( 'View album', 'jww-theme' ); ?></span>
						</a>
					<?php endif; ?>
					</div>
				</div>
			</details>
		<?php endforeach; ?>

		</div>
	</div>
</div>
