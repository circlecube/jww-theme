<?php
/**
 * Template part for displaying album covers
 * 
 * @package jww-theme
 */

// Get albums and title info from query vars (set by parent template or block)
$albums = get_query_var('albums');
$show_title = get_query_var('show_title', true);
$title = get_query_var('title', '');
$title_level = get_query_var('title_level', 3);

if (!$albums) {
    echo '<p>Not yet released on an album.</p>';
    return;
}

// Determine the title text
$title_text = !empty($title) ? $title : 'Appears on:';
?>

<div class="album-covers-section">
    <?php if ($show_title): ?>
        <?php printf('<h%d class="album-covers-title"><strong>%s</strong></h%d>', 
            $title_level, 
            esc_html($title_text), 
            $title_level
        ); ?>
    <?php endif; ?>
    
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
