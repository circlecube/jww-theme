<?php
/**
 * Template part for rendering minimal inline random lyrics (inline text format)
 * 
 * @param int $song_id The song post ID
 * @param string $lyrics_line The lyrics line to display
 * @param string $song_title The song title
 */

// Extract variables from the args array
$song_id = $args['song_id'] ?? 0;
$lyrics_line = $args['lyrics_line'] ?? '';
$song_title = $args['song_title'] ?? '';

$artist_field = get_field('artist', $song_id);
if (!empty($artist_field)) {
    $artist_name = get_the_title($artist_field[0]);
	$artist_url  = get_the_permalink($artist_field[0]);
} else {
    $artist_name = 'Jesse Welles';
	$artist_url  = get_permalink(7);
}

if (empty($song_id) || empty($lyrics_line) || empty($song_title)) {
	return;
}
?>
<p>“<?php echo esc_html($lyrics_line); ?>” — <a href="<?php echo esc_url(get_permalink($song_id)); ?>"><?php echo esc_html($song_title); ?></a> by <a href="<?php echo esc_url($artist_url); ?>"><?php echo esc_html($artist_name); ?></a></p>
