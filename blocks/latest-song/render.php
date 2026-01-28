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
$song_title = $song->post_title ?? '';

// Get first YouTube embed from embeds repeater
$video_embed = '';
$embeds = get_field( 'embeds', $song_id );
if ( $embeds && is_array( $embeds ) ) {
	foreach ( $embeds as $row ) {
		$source = $row['embed_type'] ?? 'youtube';
		if ( $source !== 'youtube' ) {
			continue;
		}
		$embed_raw = $row['youtube_video'] ?? '';
		if ( empty( $embed_raw ) ) {
			continue;
		}
		// ACF oembed can return HTML string or array with 'html' / 'url'
		if ( is_array( $embed_raw ) ) {
			$video_embed = $embed_raw['html'] ?? $embed_raw['url'] ?? '';
		} else {
			$video_embed = $embed_raw;
		}
		if ( $video_embed ) {
			break;
		}
	}
}

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
    $output .= '<div class="latest-song-block">';
    $output .= '<h2 class="wp-block-heading">Latest Song</h2>';
    $output .= '<p class="wp-block-paragraph">New songs posted frequently — as best as we can keep up with Jesse’s pace.</p>';
    // song title and link to song post
    $output .= '<h3 class="wp-block-heading"><a href="' . get_the_permalink($song_id) . '">' . esc_html($song_title) . '</a></h3>';
    $output .= '<div class="latest-song-video">';
    $output .= $video_embed; // ACF oembed field already outputs safe HTML
    $output .= '</div></div>';
} elseif ($show_video) {
    $output .= '<p class="latest-song-no-video">No video available for this song.</p>';
}

$output .= '</div>';

echo $output;
