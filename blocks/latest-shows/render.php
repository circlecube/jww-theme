<?php
/**
 * Render callback for the Latest Shows block.
 * Outputs the 5 most recent (past) shows using the show-list-links template part.
 *
 * @package JWW_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$heading = isset( $attributes['heading'] ) && $attributes['heading'] !== '' ? $attributes['heading'] : __( 'Latest Shows', 'jww-theme' );

$query_args = array(
	'post_type'      => 'show',
	'post_status'    => array( 'publish' ),
	'posts_per_page' => 5,
	'orderby'        => 'date',
	'order'          => 'DESC',
	'date_query'     => array(
		array(
			'before' => current_time( 'mysql' ),
		),
	),
);

$shows = get_posts( $query_args );

if ( empty( $shows ) ) {
	echo '<p class="wp-block-jww-latest-shows">' . esc_html__( 'No shows found.', 'jww-theme' ) . '</p>';
	return;
}

echo '<div class="wp-block-jww-latest-shows">';
get_template_part( 'template-parts/show-list-links', null, array(
	'shows'   => $shows,
	'heading' => $heading,
	'class'   => 'show-list-links latest-shows-list',
) );
echo '</div>';
