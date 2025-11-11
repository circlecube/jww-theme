<?php
/**
 * Render callback for the Song List block
 */

// Get block attributes with defaults
$list_type = $attributes['listType'] ?? 'alphabetical';
$artist_id = $attributes['artistId'] ?? '';
$show_headers = $attributes['showHeaders'] ?? true;
$include_covers = $attributes['includeCovers'] ?? false;

// Determine which template part to load based on list type
$template_part_map = [
    'alphabetical' => 'song-list-alphabetical',
    'chronological' => 'song-list-chronological',
    'covers' => 'song-list-covers',
    'grid' => 'song-list-grid'
];

$template_part = $template_part_map[$list_type] ?? 'song-list-alphabetical';

// Pass artist_id, show_headers, and include_covers to the template part via query vars
set_query_var('artist_id', $artist_id ? intval($artist_id) : '');
set_query_var('show_headers', $show_headers);
set_query_var('include_covers', $include_covers);

// Load the appropriate template part
get_template_part('template-parts/' . $template_part);

