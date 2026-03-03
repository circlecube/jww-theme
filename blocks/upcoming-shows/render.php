<?php
/**
 * Render callback for the Upcoming Shows block.
 * Outputs the 5 next upcoming shows using the show-list-links template part.
 *
 * @package JWW_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$heading = isset( $attributes['heading'] ) && $attributes['heading'] !== '' ? $attributes['heading'] : __( 'Upcoming Shows', 'jww-theme' );

$query_args = array(
	'post_type'      => 'show',
	'post_status'    => array( 'publish', 'future' ),
	'posts_per_page' => 5,
	'orderby'        => 'date',
	'order'          => 'ASC',
	'date_query'     => array(
		array(
			'after' => current_time( 'mysql' ),
		),
	),
);

$shows = get_posts( $query_args );

if ( empty( $shows ) ) {
	echo '<p class="wp-block-jww-upcoming-shows">' . esc_html__( 'No upcoming shows.', 'jww-theme' ) . '</p>';
	return;
}

echo '<div class="wp-block-jww-upcoming-shows">';
get_template_part( 'template-parts/show-list-links', null, array(
	'shows'   => $shows,
	'heading' => $heading,
	'class'   => 'show-list-links upcoming-shows-list',
) );
echo '</div>';
