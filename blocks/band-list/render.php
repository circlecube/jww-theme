<?php
/**
 * Render callback for the Band List block
 */

// Get block attributes with defaults
$show_title = $attributes['showTitle'] ?? true;
$title = $attributes['title'] ?? '';
$title_level = $attributes['titleLevel'] ?? 2;
$show_dates = $attributes['showDates'] ?? false;

// Query all bands in chronological order (by publish date)
$query_args = [
    'post_type'      => 'band',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'orderby'        => 'date',
    'order'          => 'ASC', // Oldest first (chronological)
];

$bands_query = new WP_Query($query_args);
$bands = $bands_query->posts;

// Set the bands and title info in query vars for the template part
set_query_var('bands', $bands ?? []);
set_query_var('show_title', $show_title ?? true);
set_query_var('title', $title ?? '');
set_query_var('title_level', $title_level ?? 2);
set_query_var('show_dates', $show_dates ?? false);

// Load the template part
get_template_part('template-parts/band-list');

// Reset query
wp_reset_postdata();

