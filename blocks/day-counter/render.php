<?php
/**
 * Render callback for the Day Counter block
 */

// Get block attributes with defaults
$custom_text = "Days since Jesse's latest song, ";
$show_emoji = $attributes['showEmoji'] ?? true;

if ( $attributes['customText'] && !empty($attributes['customText']) ) {
    $custom_text = $attributes['customText'];
}

// Get the most recent song post
$query_args = [
    'order'          => 'DESC',
    'orderby'        => 'date',
    'post_type'      => 'song',
    'post_status'    => 'publish',
    'posts_per_page' => 1,
];

$latest_song = get_posts($query_args);

if (empty($latest_song)) {
    return '<p class="wp-block-jww-day-counter">No songs found to calculate days from.</p>';
}

$song = $latest_song[0];
$song_id = $song->ID;
$song_date = $song->post_date;
$song_title = $song->post_title ?? '';

// Calculate days since the song was published
$song_timestamp = strtotime($song_date);
$current_timestamp = current_time('timestamp');
$days_since = floor(($current_timestamp - $song_timestamp) / DAY_IN_SECONDS);

// Determine emoji based on days since
$emoji = '😄'; // Default happy
if ($show_emoji) {
    if ($days_since <= 1) {
        $emoji = '🎉'; // Very recent - celebration
    } elseif ($days_since <= 2) {
        $emoji = '😄'; // Recent - very happy
    } elseif ($days_since <= 4) {
        $emoji = '😊'; // This week - happy
    } elseif ($days_since <= 8) {
        $emoji = '🙂'; // Two weeks - slightly happy
    } elseif ($days_since <= 12) {
        $emoji = '😐'; // Two weeks - neutral
    } elseif ($days_since <= 20) {
        $emoji = '😕'; // Three weeks - concerned
    } elseif ($days_since <= 30) {
        $emoji = '😟'; // One month - worried
    } elseif ($days_since <= 60) {
        $emoji = '😰'; // Two months - anxious
    } else {
        $emoji = '😱'; // Over two months - panic!
    }
}

// Build the output
$output = '<div class="wp-block-jww-day-counter">';
$output .= '<div class="day-counter-content">';
$output .= '<div class="day-count">';
if ($show_emoji) {
    $output .= '<span class="emoji">' . $emoji . '</span>';
}
$output .= '<strong>' . esc_html($days_since) . '</strong>';
$output .= '</div>';

$output .= '<div class="counter-text">';
$output .= esc_html($custom_text);
// Latest song title and link to song post
$output .= '<a href="' . get_the_permalink($song_id) . '">' . esc_html($song_title) . '</a>.';
$output .= '</div>';
$output .= '</div>';
$output .= '</div>';

echo $output;
