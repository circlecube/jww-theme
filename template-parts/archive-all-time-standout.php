<?php
/**
 * Template part: All-time standout (songs played at nearly every show with setlist data).
 * Used on main Shows archive only. Expects query var archive_all_time_standout_songs.
 *
 * @package JWW_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$consistent = get_query_var( 'archive_all_time_standout_songs', array() );
if ( empty( $consistent ) ) {
	return;
}

$show_stats = function_exists( 'jww_get_all_time_show_stats' ) ? jww_get_all_time_show_stats( true ) : array();
$show_count = (int) ( $show_stats['shows_with_data_count'] ?? 0 );
?>

<div class="tour-standout-card show-setlist-highlight-standout wp-block-group alignwide has-global-padding show-tour-stats-card" aria-labelledby="archive-standout-heading">
	<div class="show-setlist-data-card-header-wrapper">
		<h2 id="archive-standout-heading" class="wp-block-heading show-tour-stats-heading"><?php esc_html_e( 'Standout', 'jww-theme' ); ?></h2>
		<span class="show-setlist-data-card-info" title="<?php esc_attr_e( 'Songs that appear at nearly every show (all time, allowed one skip).', 'jww-theme' ); ?>">
			<span class="dashicons dashicons-chart-bar" aria-hidden="true"></span>
		</span>
	</div>
	<p class="show-tour-stats-meta">
		<?php
		printf(
			/* translators: 1: number of songs, 2: number of shows with setlist data */
			esc_html( _n( '%1$d song played at every (or all but one) of %2$d show with data.', '%1$d songs played at every (or all but one) of %2$d shows with data.', $show_count, 'jww-theme' ) ),
			count( $consistent ),
			$show_count
		);
		?>
	</p>
	<ul class="show-tour-stats-song-list tour-standout-list">
		<?php foreach ( $consistent as $row ) : ?>
			<li class="show-tour-stats-song-item">
				<span class="show-tour-stats-song-inner">
					<?php if ( ! empty( $row['thumbnail_url'] ) ) : ?>
						<span class="show-tour-stats-song-thumb">
							<?php if ( ! empty( $row['song_link'] ) ) : ?>
								<a href="<?php echo esc_url( $row['song_link'] ); ?>">
									<img src="<?php echo esc_url( $row['thumbnail_url'] ); ?>" alt="" width="40" height="40" loading="lazy" />
								</a>
							<?php else : ?>
								<img src="<?php echo esc_url( $row['thumbnail_url'] ); ?>" alt="" width="40" height="40" loading="lazy" />
							<?php endif; ?>
						</span>
					<?php endif; ?>
					<span class="show-tour-stats-song-info">
						<?php if ( ! empty( $row['song_link'] ) ) : ?>
							<a href="<?php echo esc_url( $row['song_link'] ); ?>"><?php echo esc_html( $row['song_title'] ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $row['song_title'] ); ?>
						<?php endif; ?>
					</span>
					<span class="show-tour-stats-count"><?php echo (int) $row['count']; ?></span>
				</span>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
