<?php
/**
 * Render callback for the Latest Song block
 */

// Get block attributes with defaults
$show_title = $attributes['showTitle'] ?? true;
$show_video = $attributes['showVideo'] ?? true;
$title_level = $attributes['titleLevel'] ?? 2;
$selected_categories = $attributes['categories'] ?? [];

// Build query arguments
$query_args = [
    'order'          => 'DESC',
    'orderby'        => 'date',
    'post_type'      => 'song',
    'post_status'    => 'publish',
    'posts_per_page' => 1,
];

// Add category filter if categories are selected
if (!empty($selected_categories)) {
    $query_args['category__in'] = array_map('intval', $selected_categories);
}

// Get the most recent song post
$latest_song = get_posts($query_args);

if (empty($latest_song)) {
    return '<p class="wp-block-jww-latest-song">No songs found.</p>';
}

$song = $latest_song[0];
$song_id = $song->ID;
$song_title = $song->post_title;
$video_embed = get_field('video', $song_id);

// Build the output
$output = '<div class="wp-block-jww-latest-song">';

// Show title if enabled
if ($show_title && $song_title) {
    $output .= sprintf(
        '<h%d class="latest-song-title">%s</h%d>',
        $title_level,
        esc_html($song_title),
        $title_level
    );
}

// Show video if enabled and available
if ($show_video && $video_embed) {
    $output .= '<div class="latest-song-video">';
    $output .= $video_embed; // ACF oembed field already outputs safe HTML
    $output .= '</div>';
} elseif ($show_video) {
    $output .= '<p class="latest-song-no-video">No video available for this song.</p>';
}

$output .= '</div>';

echo $output;
