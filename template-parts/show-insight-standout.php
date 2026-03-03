<?php
/**
 * Template part: Show insight — Standout (songs consistent across the tour vs rarities).
 * Used on single show page. Logic in includes/show-functions.php.
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
if ( is_singular( 'show' ) ) {
	$show_id = get_queried_object_id();
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
		<h2 id="show-highlight-standout-heading" class="wp-block-heading show-setlist-data-card-heading">
			<span class="show-setlist-data-card-info" title="<?php esc_attr_e( 'How this show stacks up against other tour stops with setlists.', 'jww-theme' ); ?>">
				<span class="dashicons dashicons-chart-bar" aria-hidden="true"></span>
			</span>
			<?php esc_html_e( 'Standout', 'jww-theme' ); ?>
		</h2>
			
		<p class="show-highlight-standout-intro">
			<?php
			printf(
				/* translators: %d = number of other past shows in the tour */
				_n( 'Compared to %d other show in this tour with setlists.', 'Compared to %d other shows in this tour with setlists.', $n_tour, 'jww-theme' ),
				$n_tour
			);
			?>
		</p>
		<?php if ( $has_unique ) : ?>
			<div class="show-setlist-data-card-header-wrapper">
				<h3 class="show-setlist-data-card-content-subtitle"><?php esc_html_e( 'Rarities more unique to this show', 'jww-theme' ); ?></h3>
				<span class="show-setlist-data-card-info" title="<?php esc_attr_e( 'Played at only a few tour stops—rare for this tour.', 'jww-theme' ); ?>">
					<span class="dashicons dashicons-info" aria-hidden="true"></span>
				</span>
			</div>
			<p class="show-setlist-data-card-content compared-unique">
				<?php
				$links = array();
				foreach ( $standout['unique_songs'] as $item ) {
					$links[] = '<a href="' . esc_url( $item['song_link'] ) . '">' . esc_html( $item['song_title'] ) . '</a>';
				}
				echo wp_kses_post( implode( ', ', $links ) );
				?>
			</p>
		<?php endif; ?>
		<?php if ( $has_consistent ) : ?>
			<div class="show-setlist-data-card-header-wrapper">
				<h3 class="show-setlist-data-card-content-subtitle"><?php esc_html_e( 'Consistent across the tour', 'jww-theme' ); ?></h3>
				<span class="show-setlist-data-card-info" title="<?php esc_attr_e( 'These songs are tour staples! They\'ve been played at every show.','jww-theme' ); ?>">
					<span class="dashicons dashicons-info" aria-hidden="true"></span>
				</span>
			</div>
			<p class="show-setlist-data-card-content compared-consistent">
				<?php
				$links = array();
				foreach ( $standout['consistent_songs'] as $item ) {
					$links[] = '<a href="' . esc_url( $item['song_link'] ) . '">' . esc_html( $item['song_title'] ) . '</a>';
				}
				echo wp_kses_post( implode( ', ', $links ) );
				?>
			</p>
		<?php endif; ?>
	</div>
</div>
