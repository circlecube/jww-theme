<?php
/**
 * Render callback for the Album Covers block
 */

// Get block attributes with defaults
$selected_releases = $attributes['releases'] ?? [];
$posts_per_page = $attributes['postsPerPage'] ?? -1;
$order_by = $attributes['orderBy'] ?? 'date';
$order = $attributes['order'] ?? 'DESC';
$show_title = $attributes['showTitle'] ?? true;
$title = $attributes['title'] ?? '';
$title_level = $attributes['titleLevel'] ?? 2;

// Build query arguments
$query_args = [
    'post_type'      => 'album',
    'post_status'    => 'publish',
    'posts_per_page' => $posts_per_page,
    'orderby'        => $order_by,
    'order'          => $order,
];

// Add release taxonomy filter if releases are selected
if (!empty($selected_releases)) {
    $query_args['tax_query'] = [
        [
            'taxonomy' => 'release',
            'field'    => 'term_id',
            'terms'    => array_map('intval', $selected_releases),
            'operator' => 'IN'
        ]
    ];
}

// Get albums
$albums_query = new WP_Query($query_args);
$albums = $albums_query->posts;

// Set the albums and title info in query vars for the template part
set_query_var('albums', $albums);
set_query_var('show_title', $show_title);
set_query_var('title', $title);
set_query_var('title_level', $title_level);

// Load the template part
get_template_part('template-parts/album-covers');

// Reset query
wp_reset_postdata();
