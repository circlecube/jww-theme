<?php
/**
 * Template part: Live debuts card (songs played for the first time at this show).
 * Only outputs when the show has at least one debut. Used in Setlist Data flex group.
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

if ( ! function_exists( 'jww_get_show_setlist_highlights_debuts' ) ) {
	return;
}

$debuts = jww_get_show_setlist_highlights_debuts( $show_id );
if ( empty( $debuts ) ) {
	return;
}
?>

<div class="show-setlist-highlight-debuts wp-block-group alignwide has-global-padding" aria-labelledby="show-highlight-debuts-heading">
	<div class="show-setlist-data-card">
		<h2 id="show-highlight-debuts-heading" class="wp-block-heading show-setlist-data-card-heading"><?php esc_html_e( 'Live debuts', 'jww-theme' ); ?></h2>
		<p class="show-setlist-data-card-content">
			<?php
			$links = array();
			foreach ( $debuts as $item ) {
				$links[] = '<a href="' . esc_url( $item['song_link'] ) . '">' . esc_html( $item['song_title'] ) . '</a>';
			}
			echo wp_kses_post( implode( ', ', $links ) );
			?>
		</p>
	</div>
</div>
