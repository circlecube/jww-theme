<?php
/**
 * Render callback for the Album Covers block
 */

// Get block attributes with defaults
$selected_album_id = isset( $attributes['selectedAlbumId'] ) ? (int) $attributes['selectedAlbumId'] : 0;
$selected_releases = $attributes['releases'] ?? [];
$posts_per_page = $attributes['postsPerPage'] ?? -1;
$order_by = $attributes['orderBy'] ?? 'date';
$order = $attributes['order'] ?? 'DESC';
$show_title = $attributes['showTitle'] ?? true;
$title = $attributes['title'] ?? '';
$title_level = $attributes['titleLevel'] ?? 2;
$artist_id = $attributes['artist'] ?? '';

// Build query arguments
$query_args = [
    'post_type'      => 'album',
    'post_status'    => 'publish',
    'posts_per_page' => $posts_per_page,
    'orderby'        => $order_by,
    'order'          => $order,
];

// Single album selection overrides all other filters
if ( $selected_album_id > 0 ) {
    $query_args['post__in'] = [ $selected_album_id ];
    $query_args['posts_per_page'] = 1;
    $query_args['orderby'] = 'post__in';
} else {
    // Add release taxonomy filter if releases are selected
    if ( ! empty( $selected_releases ) ) {
        $query_args['tax_query'] = [
            [
                'taxonomy' => 'release',
                'field'    => 'term_id',
                'terms'    => array_map( 'intval', $selected_releases ),
                'operator' => 'IN'
            ]
        ];
    }

    // Add artist filter if artist ID is specified
    if ( ! empty( $artist_id ) ) {
        $artist_id = intval( $artist_id );
        $query_args['meta_query'] = [
            [
                'key'     => 'artist',
                'value'   => '"' . $artist_id . '"',
                'compare' => 'LIKE'
            ]
        ];
    }
}

// Get albums
$albums_query = new WP_Query( $query_args );
$albums = $albums_query->posts;

// Set the albums and title info in query vars for the template part
set_query_var('albums', $albums ?? []);
set_query_var('show_title', $show_title ?? true);
set_query_var('title', $title ?? '');
set_query_var('title_level', $title_level ?? 2);

// Load the template part
get_template_part('template-parts/album-covers');

// Reset query
wp_reset_postdata();
