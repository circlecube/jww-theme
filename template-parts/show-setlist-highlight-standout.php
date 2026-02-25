<?php
/**
 * Template part: Standout card (songs consistent across the tour vs unique to this show).
 * Only outputs when the show has a tour with other shows and has consistent or unique songs. Used in Setlist Data flex group.
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

if ( ! function_exists( 'jww_get_show_setlist_highlights_standout' ) ) {
	return;
}

$standout = jww_get_show_setlist_highlights_standout( $show_id );
$comparison_shows = isset( $standout['comparison_shows'] ) ? $standout['comparison_shows'] : array();
$has_consistent = ! empty( $standout['consistent_songs'] );
$has_unique = ! empty( $standout['unique_songs'] );

if ( empty( $comparison_shows ) || ( ! $has_consistent && ! $has_unique ) ) {
	return;
}

$n_tour = count( $comparison_shows );
?>

<div class="show-setlist-highlight-standout wp-block-group alignwide has-global-padding" aria-labelledby="show-highlight-standout-heading">
	<div class="show-setlist-data-card">
		<h2 id="show-highlight-standout-heading" class="wp-block-heading show-setlist-data-card-heading"><?php esc_html_e( 'Standout', 'jww-theme' ); ?></h2>
		<p class="show-highlight-standout-intro">
			<?php
			printf(
				/* translators: %d = number of other shows in the tour */
				_n( 'Compared to %d other show in this tour.', 'Compared to %d other shows in this tour.', $n_tour, 'jww-theme' ),
				$n_tour
			);
			?>
		</p>
		<?php if ( $has_consistent ) : ?>
			<p class="show-setlist-data-card-content compared-consistent">
				<strong><?php esc_html_e( 'Consistent across the tour:', 'jww-theme' ); ?></strong>
				<?php
				$links = array();
				foreach ( $standout['consistent_songs'] as $item ) {
					$links[] = '<a href="' . esc_url( $item['song_link'] ) . '">' . esc_html( $item['song_title'] ) . '</a>';
				}
				echo wp_kses_post( implode( ', ', $links ) );
				?>
			</p>
		<?php endif; ?>
		<?php if ( $has_unique ) : ?>
			<p class="show-setlist-data-card-content compared-unique">
				<strong><?php esc_html_e( 'Unique to this show:', 'jww-theme' ); ?></strong>
				<?php
				$links = array();
				foreach ( $standout['unique_songs'] as $item ) {
					$links[] = '<a href="' . esc_url( $item['song_link'] ) . '">' . esc_html( $item['song_title'] ) . '</a>';
				}
				echo wp_kses_post( implode( ', ', $links ) );
				?>
			</p>
		<?php endif; ?>
	</div>
</div>
