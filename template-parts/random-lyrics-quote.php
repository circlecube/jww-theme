<?php
/**
 * Template part for rendering random lyrics quote
 * 
 * @param int $song_id The song post ID
 * @param string $lyrics_line The lyrics line to display
 * @param bool $show_song_title Whether to show the song title
 * @param bool $show_artist Whether to show the artist name
 */

// Extract variables from the args array
$song_id = $args['song_id'] ?? 0;
$lyrics_line = $args['lyrics_line'] ?? '';
$show_song_title = $args['show_song_title'] ?? true;
$show_artist = $args['show_artist'] ?? true;

// Get song data
$song_title = get_the_title($song_id);
$artist_name = 'Jesse Welles'; // Default artist name

// Check if there's an artist field
$artist_field = get_field('artist', $song_id);
if (!empty($artist_field)) {
    $artist_name = get_the_title($artist_field[0]);
} else {
    $artist_name = 'Jesse Welles';
}
?>

<blockquote class="random-lyrics-quote">
    <p class="random-lyrics-text"><?php echo esc_html($lyrics_line); ?></p>
    
    <?php if ($show_song_title || $show_artist): ?>
    <cite class="random-lyrics-attribution">
        <?php if ($show_artist): ?>
            <span class="random-lyrics-artist"><?php echo esc_html($artist_name); ?></span>
        <?php endif; ?>
        
        <?php if ($show_song_title): ?>
            <span class="random-lyrics-song">
                <?php if ($show_artist): ?> — <?php endif; ?>
                <a href="<?php echo esc_url(get_permalink($song_id)); ?>">
                    <?php echo esc_html($song_title); ?>
                </a>
            </span>
        <?php endif; ?>
    </cite>
    <?php endif; ?>
</blockquote>
