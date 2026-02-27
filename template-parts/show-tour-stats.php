<?php
/**
 * Template part: show-tour-stats — Song performance counts (used on single show page and tour archive).
 * Show context: gets tour from current show. Tour context: gets tour_id from query var. Logic in includes/tour-functions.php.
 *
 * @package JWW_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tour_id_from_var = get_query_var( 'tour_id', 0 );
$tour_id_from_var = $tour_id_from_var ? (int) $tour_id_from_var : 0;

if ( $tour_id_from_var ) {
	$tour_id = $tour_id_from_var;
	$show_id = 0;
} else {
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
	<div class="show-setlist-data-card-header-wrapper">
		<h2 id="show-tour-stats-heading" class="wp-block-heading show-tour-stats-heading"><?php esc_html_e( 'Tour stats', 'jww-theme' ); ?></h2>
		<span class="show-setlist-data-card-info" title="<?php esc_attr_e( 'How often each song appears in the setlists we have for this tour.', 'jww-theme' ); ?>">
			<span class="dashicons dashicons-analytics" aria-hidden="true"></span>
		</span>
	</div>
	<p class="show-tour-stats-meta">
		<?php
		$tour_markup = ( $tour_link && ! is_wp_error( $tour_link ) )
			? '<a href="' . esc_url( $tour_link ) . '">' . esc_html( $tour_name ) . '</a>'
			: esc_html( $tour_name );
		$meta_fmt = _n(
			'%1$s — Song counts across %2$d show with data in this tour.',
			'%1$s — Song counts across %2$d shows with data in this tour.',
			$show_count,
			'jww-theme'
		);
		printf( wp_kses_post( $meta_fmt ), $tour_markup, $show_count );
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
