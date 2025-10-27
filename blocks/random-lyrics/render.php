<?php
/**
 * Random Lyrics Block Render Template
 */

// Get block attributes
$show_song_title = $attributes['showSongTitle'] ?? true;
$show_artist = $attributes['showArtist'] ?? true;
$refresh_on_load = $attributes['refreshOnLoad'] ?? true;

// Get all song posts that have lyrics
$songs = get_posts([
    'post_type' => 'song',
    'posts_per_page' => 1,
    'orderby'        => 'rand',
    'order'          => 'DESC',
    'meta_query' => [
        [
            'key' => 'lyrics',
            'compare' => 'EXISTS'
        ],
        [
            'key' => 'lyrics',
            'value' => '',
            'compare' => '!='
        ]
    ],
    'tax_query'      => array(
        array(
            'taxonomy' => 'category',
            'field'     => 'slug',
            'terms'    => 'original'
        )
    ),
    'fields' => 'ids'
]);

if (empty($songs)) {
    echo '<div class="wp-block-jww-theme-random-lyrics">';
    echo '<p class="random-lyrics-placeholder">No songs with lyrics found.</p>';
    echo '</div>';
    return;
}

// Get a random song
$random_song_id = $songs[0];
$song_title = get_the_title($random_song_id);
$lyrics = get_field('lyrics', $random_song_id);

if (empty($lyrics)) {
    echo '<div class="wp-block-jww-theme-random-lyrics">';
    echo '<p class="random-lyrics-placeholder">No lyrics found for this song.</p>';
    echo '</div>';
    return;
}

// Split lyrics into lines and filter out empty lines
$lyrics_lines = array_filter(
    array_map('trim', explode("\n", $lyrics)),
    function($line) {
        return !empty($line) && strlen($line) > 10; // Filter out very short lines
    }
);

if (empty($lyrics_lines)) {
    echo '<div class="wp-block-jww-theme-random-lyrics">';
    echo '<p class="random-lyrics-placeholder">No suitable lyrics lines found.</p>';
    echo '</div>';
    return;
}

// Get a random line
$random_line = $lyrics_lines[array_rand($lyrics_lines)];

// Strip HTML tags from the lyrics line
$random_line = strip_tags($random_line);

// Add refresh functionality if enabled
$refresh_class = $refresh_on_load ? 'refresh-on-load' : '';
?>

<div class="wp-block-jww-theme-random-lyrics <?php echo esc_attr($refresh_class); ?>" 
     data-song-id="<?php echo esc_attr($random_song_id); ?>"
     data-show-title="<?php echo esc_attr($show_song_title ? 'true' : 'false'); ?>"
     data-show-artist="<?php echo esc_attr($show_artist ? 'true' : 'false'); ?>">
    
    <?php 
    // Use shared template part
    $args = [
        'song_id' => $random_song_id,
        'lyrics_line' => $random_line,
        'show_song_title' => $show_song_title,
        'show_artist' => $show_artist
    ];
    echo '<div class="random-lyrics-quote-container">';
    get_template_part('template-parts/random-lyrics-quote', null, $args);
    echo '</div>';
    ?>
    
    <?php if ($refresh_on_load): ?>
    <div class="random-lyrics-controls">
        <button type="button" class="random-lyrics-refresh-btn" aria-label="Get new random lyrics">
            <span class="refresh-icon"><span class="fas fa-refresh" data-fa-animation-iteration-count="1"></span></span>
            <span class="refresh-text">New Lyrics</span>
        </button>
    </div>
    <?php endif; ?>
</div>

<?php if ($refresh_on_load): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const randomLyricsBlocks = document.querySelectorAll('.wp-block-jww-theme-random-lyrics.refresh-on-load');
    
    randomLyricsBlocks.forEach(function(block) {
        const refreshBtn = block.querySelector('.random-lyrics-refresh-btn');
        if (!refreshBtn) return;
        
        refreshBtn.addEventListener('click', function() {
            // Show loading state
            const quote = block.querySelector('.random-lyrics-quote-container');
            const originalContent = quote.innerHTML;
            quote.innerHTML = '<p class="random-lyrics-loading">Loading new lyrics...</p>';
            refreshBtn.disabled = true;
            refreshBtn.querySelector('.refresh-icon').classList.add('fa-spin');
            
            // Fetch new random lyrics via AJAX
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'get_random_lyrics',
                    nonce: '<?php echo wp_create_nonce('random_lyrics_nonce'); ?>',
                    show_title: block.dataset.showTitle,
                    show_artist: block.dataset.showArtist
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    quote.innerHTML = data.data.html;
                } else {
                    quote.innerHTML = originalContent;
                }
            })
            .catch(error => {
                console.error('Error fetching random lyrics:', error);
                quote.innerHTML = originalContent;
            })
            .finally(() => {
                refreshBtn.disabled = false;
                refreshBtn.querySelector('.refresh-icon').classList.remove('fa-spin');
            });
        });
    });
});
</script>
<?php endif; ?>
