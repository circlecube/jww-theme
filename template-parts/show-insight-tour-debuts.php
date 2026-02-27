<?php
/**
 * Template part: Show insight — Tour debuts (songs played for the first time this tour at this show).
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

if ( ! function_exists( 'jww_get_show_setlist_highlights_tour_debuts' ) ) {
	return;
}

$data = jww_get_show_setlist_highlights_tour_debuts( $show_id );
if ( empty( $data ) ) {
	return;
}

$is_first_show = ! empty( $data['is_first_show'] );
$debuts = isset( $data['debuts'] ) ? $data['debuts'] : array();
if ( ! $is_first_show && empty( $debuts ) ) {
	return;
}
?>

<div class="show-setlist-highlight-tour-debuts wp-block-group alignwide has-global-padding" aria-labelledby="show-highlight-tour-debuts-heading">
	<div class="show-setlist-data-card">
		<div class="show-setlist-data-card-header-wrapper">
			<h2 id="show-highlight-tour-debuts-heading" class="wp-block-heading show-setlist-data-card-heading"><?php esc_html_e( 'Tour debuts', 'jww-theme' ); ?></h2>
			<span class="show-setlist-data-card-info" title="<?php esc_attr_e( 'First time played on this tour—hadn\'t appeared at any earlier show.', 'jww-theme' ); ?>">
				<span class="dashicons dashicons-location-alt" aria-hidden="true"></span>
			</span>
		</div>
		<?php if ( $is_first_show ) : ?>
			<p class="show-setlist-data-card-content show-setlist-data-card-content-tour-first">
				<?php esc_html_e( 'This is the first show of the tour—every song in the setlist is a tour debut!', 'jww-theme' ); ?>
			</p>
		<?php else : ?>
			<p class="show-setlist-data-card-content">
				<?php
				$links = array();
				foreach ( $debuts as $item ) {
					$links[] = '<a href="' . esc_url( $item['song_link'] ) . '">' . esc_html( $item['song_title'] ) . '</a>';
				}
				echo wp_kses_post( implode( ', ', $links ) );
				?>
			</p>
		<?php endif; ?>
	</div>
</div>
