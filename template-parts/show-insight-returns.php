<?php
/**
 * Template part: Show insight — Back in the Set (songs reintroduced after 3+ shows).
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
		<div class="show-setlist-data-card-header-wrapper">
			<h2 id="show-highlight-returns-heading" class="wp-block-heading show-setlist-data-card-heading"><?php esc_html_e( 'Back in the Set', 'jww-theme' ); ?></h2>
			<span class="show-setlist-data-card-info" title="<?php esc_attr_e( 'Back from a break! These hadn\'t been played for 3 or more shows.', 'jww-theme' ); ?>">
				<span class="dashicons dashicons-backup" aria-hidden="true"></span>
			</span>
		</div>
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
