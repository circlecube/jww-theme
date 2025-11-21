<?php
/**
 * Render callback for the Song List block
 */

// Get block attributes with defaults
$list_type = $attributes['listType'] ?? 'alphabetical';
$artist_id = $attributes['artistId'] ?? '';
$show_headers = $attributes['showHeaders'] ?? true;
$include_covers = $attributes['includeCovers'] ?? false;

// Convert include_covers to song_type for template parts
$song_type = 'originals';
if ($include_covers) {
	// If includeCovers is true, check if list_type is 'covers' to determine song_type
	if ($list_type === 'covers') {
		$song_type = 'covers';
	} else {
		$song_type = 'both';
	}
}

// Determine which template part to load based on list type
// Note: 'covers' list type now uses alphabetical template with song_type='covers'
$template_part_map = [
    'alphabetical' => 'song-list-alphabetical',
    'chronological' => 'song-list-chronological',
    'covers' => 'song-list-alphabetical', // Use alphabetical template with song_type filter
    'grid' => 'song-list-grid'
];

$template_part = $template_part_map[$list_type] ?? 'song-list-alphabetical';

// Pass configuration to the template part via query vars
set_query_var('artist_id', $artist_id ? intval($artist_id) : '');
set_query_var('show_headers', $show_headers);
set_query_var('song_type', $song_type);

// Load the appropriate template part
get_template_part('template-parts/' . $template_part);

