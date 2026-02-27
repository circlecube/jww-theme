<?php
/**
 * Template part: Tour insight — One-off (songs played at only one show in the tour).
 * Used on tour archive only. Logic in includes/tour-functions.php.
 *
 * @package JWW_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tour_term = get_queried_object();
if ( ! $tour_term || is_wp_error( $tour_term ) || ! isset( $tour_term->term_id ) || $tour_term->taxonomy !== 'tour' ) {
	return;
}

if ( ! function_exists( 'jww_get_tour_one_offs' ) ) {
	return;
}

$one_offs = jww_get_tour_one_offs( $tour_term->term_id );
if ( empty( $one_offs ) ) {
	return;
}

$data = jww_get_tour_song_counts( $tour_term->term_id );
$show_count = (int) $data['show_count'];
?>

<div class="tour-one-offs-card wp-block-group alignwide has-global-padding show-tour-stats-card" aria-labelledby="tour-one-offs-heading">
	<div class="show-setlist-data-card-header-wrapper">
		<h2 id="tour-one-offs-heading" class="wp-block-heading show-tour-stats-heading"><?php esc_html_e( 'One-off', 'jww-theme' ); ?></h2>
		<span class="show-setlist-data-card-info" title="<?php esc_attr_e( 'Songs that appear on only one setlist we have for this tour.', 'jww-theme' ); ?>">
			<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
		</span>
	</div>
	<p class="show-tour-stats-meta">
		<?php
		printf(
			/* translators: 1: number of songs, 2: number of shows with setlist data */
			esc_html( _n( '%1$d song played at only one of %2$d show with setlist data.', '%1$d songs played at only one of %2$d shows with setlist data.', $show_count, 'jww-theme' ) ),
			count( $one_offs ),
			$show_count
		);
		?>
	</p>
	<ul class="show-tour-stats-song-list tour-one-offs-list">
		<?php foreach ( $one_offs as $row ) : ?>
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
				</span>
				<?php if ( ! empty( $row['show_link'] ) && ! empty( $row['show_date'] ) ) : ?>
					<span class="tour-one-off-date">
						<a href="<?php echo esc_url( $row['show_link'] ); ?>"><?php echo esc_html( $row['show_date'] ); ?></a>
					</span>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
