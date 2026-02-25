<?php
/**
 * Template part: Tour song performance counts for the current show's tour.
 * Displays how many times each song has been played across the tour (thus far).
 * Only shown when the show is part of a tour. Cached per tour; invalidated when any show in the tour is updated.
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

$tour_id = get_field( 'show_tour', $show_id );
if ( ! $tour_id ) {
	return;
}

$tour_id = is_object( $tour_id ) && isset( $tour_id->term_id ) ? (int) $tour_id->term_id : (int) $tour_id;
if ( ! $tour_id ) {
	return;
}

if ( ! function_exists( 'jww_get_tour_song_counts' ) ) {
	return;
}

$tour_term = get_term( $tour_id, 'tour' );
if ( ! $tour_term || is_wp_error( $tour_term ) ) {
	return;
}

$data = jww_get_tour_song_counts( $tour_id );
if ( empty( $data['songs'] ) ) {
	return;
}

$tour_name = $tour_term->name;
$tour_link = get_term_link( $tour_term->term_id, 'tour' );
$show_count = (int) $data['show_count'];
$songs = $data['songs'];
?>

<div class="show-tour-stats wp-block-group alignwide has-global-padding show-tour-stats-card" aria-labelledby="show-tour-stats-heading">
	<h2 id="show-tour-stats-heading" class="wp-block-heading show-tour-stats-heading">
		<?php
		printf(
			/* translators: 1: tour name, 2: number of shows */
			esc_html__( 'Tour stats: %1$s', 'jww-theme' ),
			$tour_link && ! is_wp_error( $tour_link )
				? '<a href="' . esc_url( $tour_link ) . '">' . esc_html( $tour_name ) . '</a>'
				: esc_html( $tour_name )
		);
		?>
	</h2>
	<p class="show-tour-stats-meta">
		<?php
		printf(
			/* translators: %d: number of shows with setlists in the tour */
			esc_html( _n( 'Song performance counts across %d show in this tour.', 'Song performance counts across %d shows in this tour.', $show_count, 'jww-theme' ) ),
			$show_count
		);
		?>
	</p>
	<ul class="show-tour-stats-song-list">
		<?php foreach ( $songs as $row ) : ?>
			<?php
			$pct = $show_count > 0 ? round( ( (int) $row['count'] / $show_count ) * 100, 1 ) : 0;
			?>
			<li class="show-tour-stats-song-item" style="--show-tour-stats-pct: <?php echo (float) $pct; ?>;">
				<span class="show-tour-stats-song-bar" aria-hidden="true"></span>
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
