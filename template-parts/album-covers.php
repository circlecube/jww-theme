<?php
/**
 * Template part for displaying album covers
 * 
 * @package jww-theme
 */

// Get albums from query var (set by parent template)
$albums = get_query_var('albums');

if (!$albums) {
    echo '<p>Not yet released on an album.</p>';
    return;
}
?>

<div class="album-covers-section">
    <p><strong>Appears on:</strong></p>
    
    <div class="album-covers">
        <?php 
        // Handle both single album and multiple albums
        $albums_array = is_array($albums) ? $albums : [$albums];
        foreach ($albums_array as $album): 
            // Handle both post objects and IDs
            $album_id = is_object($album) ? $album->ID : $album;
            
            $album_title = get_the_title($album_id);
            $album_url = get_permalink($album_id);
            $album_cover = get_the_post_thumbnail($album_id, 'medium');
            ?>
            <a
                class="album"
                href="<?php echo esc_url($album_url); ?>"
            >
                <?php if ($album_cover): ?>
                    <?php echo $album_cover; ?>
                <?php endif; ?>
                <h2 class="album-title"><?php echo esc_html($album_title); ?></h2>
                <span class="album-date"><?php echo get_field('release_date', $album_id); ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>
