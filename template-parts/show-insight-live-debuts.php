<?php
/**
 * Template part: Show insight — Live debuts (songs played for the first time ever at this show).
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
		<div class="show-setlist-data-card-header-wrapper">
			<h2 id="show-highlight-debuts-heading" class="wp-block-heading show-setlist-data-card-heading"><?php esc_html_e( 'Live debuts!', 'jww-theme' ); ?></h2>
			<span class="show-setlist-data-card-info" title="<?php esc_attr_e( 'The first time this song has ever been performed live—anywhere!', 'jww-theme' ); ?>">
				<span class="dashicons dashicons-star-filled" aria-hidden="true"></span>
			</span>
		</div>
		<p class="show-setlist-data-card-content show-setlist-data-card-content-subtitle">
			<?php esc_html_e( 'First time played anywhere.', 'jww-theme' ); ?>
		</p>
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
