<?php
/**
 * Template part: Back in the Set card (songs reintroduced after 3+ shows).
 * Only outputs when the show has at least one return. Used in Setlist Data flex group.
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

if ( ! function_exists( 'jww_get_show_setlist_highlights_returns' ) ) {
	return;
}

$returns = jww_get_show_setlist_highlights_returns( $show_id );
if ( empty( $returns ) ) {
	return;
}
?>

<div class="show-setlist-highlight-returns wp-block-group alignwide has-global-padding" aria-labelledby="show-highlight-returns-heading">
	<div class="show-setlist-data-card">
		<h2 id="show-highlight-returns-heading" class="wp-block-heading show-setlist-data-card-heading"><?php esc_html_e( 'Back in the Set', 'jww-theme' ); ?></h2>
		<p class="show-setlist-data-card-content">
			<?php
			$parts = array();
			foreach ( $returns as $item ) {
				$n = $item['shows_since'];
				$label = sprintf(
					/* translators: %d = number of shows */
					_n( 'after %d show', 'after %d shows', $n, 'jww-theme' ),
					$n
				);
				$parts[] = '<a href="' . esc_url( $item['song_link'] ) . '">' . esc_html( $item['song_title'] ) . '</a> <span class="shows-since">(' . esc_html( $label ) . ')</span>';
			}
			echo wp_kses_post( implode( ', ', $parts ) );
			?>
		</p>
	</div>
</div>
