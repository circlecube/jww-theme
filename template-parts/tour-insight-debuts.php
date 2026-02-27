<?php
/**
 * Template part: Tour insight — Tour debuts (songs that had their first-ever live performance during this tour).
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

if ( ! function_exists( 'jww_get_tour_debuts' ) ) {
	return;
}

$debuts = jww_get_tour_debuts( $tour_term->term_id );
if ( empty( $debuts ) ) {
	return;
}
?>

<div class="show-setlist-highlight-debuts tour-debuts-card wp-block-group alignwide has-global-padding show-tour-stats-card" aria-labelledby="tour-debuts-heading">
	<div class="show-setlist-data-card-header-wrapper">
		<h2 id="tour-debuts-heading" class="wp-block-heading show-tour-stats-heading"><?php esc_html_e( 'Tour debuts', 'jww-theme' ); ?></h2>
		<span class="show-setlist-data-card-info" title="<?php esc_attr_e( 'Songs that had their first-ever live performance during this tour (from setlists we have).', 'jww-theme' ); ?>">
			<span class="dashicons dashicons-star-filled" aria-hidden="true"></span>
		</span>
	</div>
	<p class="show-tour-stats-meta">
		<?php
		printf(
			/* translators: %d: number of songs */
			esc_html( _n( '%d song.', '%d songs.', count( $debuts ), 'jww-theme' ) ),
			count( $debuts )
		);
		?>
	</p>
	<ul class="show-tour-stats-song-list tour-debuts-list">
		<?php foreach ( $debuts as $item ) : ?>
			<li class="show-tour-stats-song-item">
				<span class="show-tour-stats-song-inner">
					<?php if ( ! empty( $item['thumbnail_url'] ) ) : ?>
						<span class="show-tour-stats-song-thumb">
							<?php if ( ! empty( $item['song_link'] ) ) : ?>
								<a href="<?php echo esc_url( $item['song_link'] ); ?>">
									<img src="<?php echo esc_url( $item['thumbnail_url'] ); ?>" alt="" width="40" height="40" loading="lazy" />
								</a>
							<?php else : ?>
								<img src="<?php echo esc_url( $item['thumbnail_url'] ); ?>" alt="" width="40" height="40" loading="lazy" />
							<?php endif; ?>
						</span>
					<?php endif; ?>
					<span class="show-tour-stats-song-info">
						<?php if ( ! empty( $item['song_link'] ) ) : ?>
							<a href="<?php echo esc_url( $item['song_link'] ); ?>"><?php echo esc_html( $item['song_title'] ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $item['song_title'] ); ?>
						<?php endif; ?>
					</span>
				</span>
				<?php if ( ! empty( $item['debut_show_link'] ) ) : ?>
					<span class="tour-debut-show">
						<a href="<?php echo esc_url( $item['debut_show_link'] ); ?>"><?php echo esc_html( $item['debut_show_date'] ); ?></a>
					</span>
				<?php else : ?>
					<span class="tour-debut-show"><?php echo esc_html( $item['debut_show_date'] ); ?></span>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
