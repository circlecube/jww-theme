<?php
/**
 * Title: Album Covers
 * Slug: jww-theme/album-covers
 * Categories: media
 * Description: Display album covers for songs
 * Keywords: album, covers, music, songs
 */

// Get albums from query var (set by parent template)
$albums = get_query_var('albums') ?? [];

if (!$albums) {
    echo '<!-- wp:paragraph -->
    <p>Not yet released on an album.</p>
    <!-- /wp:paragraph -->';
    return;
}

// Handle both single album and multiple albums
$albums_array = is_array($albums) ? $albums : [$albums];
?>
<!-- wp:group {"className":"album-covers-section","layout":{"type":"constrained"}} -->
<div class="wp-block-group album-covers-section">
    <!-- wp:paragraph -->
    <p><strong>Appears on:</strong></p>
    <!-- /wp:paragraph -->
    
    <!-- wp:group {"className":"album-covers","layout":{"type":"grid","columnCount":3}} -->
    <div class="wp-block-group album-covers">
        <?php foreach ($albums_array as $album): 
            $album_id = is_object($album) ? $album->ID : $album;
            $album_title = get_the_title($album_id) ?? '';
            $album_url = get_permalink($album_id) ?? '';
            $album_cover = get_the_post_thumbnail($album_id, 'medium') ?? '';
            $album_date = function_exists('get_field') ? (get_field('release_date', $album_id) ?? '') : '';
        ?>
        <!-- wp:group {"className":"album","layout":{"type":"constrained"}} -->
        <div class="wp-block-group album">
            <?php if ($album_cover): ?>
            <!-- wp:image {"align":"center","width":"200px","height":"200px","sizeSlug":"medium","linkDestination":"custom","href":"<?php echo esc_url($album_url); ?>"} -->
            <figure class="wp-block-image aligncenter size-medium is-resized">
                <a href="<?php echo esc_url($album_url); ?>">
                    <?php echo $album_cover; ?>
                </a>
            </figure>
            <!-- /wp:image -->
            <?php endif; ?>
            
            <!-- wp:heading {"textAlign":"center","level":3,"fontSize":"large"} -->
            <h3 class="wp-block-heading has-text-align-center has-large-font-size">
                <a href="<?php echo esc_url($album_url); ?>"><?php echo esc_html($album_title); ?></a>
            </h3>
            <!-- /wp:heading -->
            
            <!-- wp:paragraph {"align":"center","fontSize":"small"} -->
            <p class="has-text-align-center has-small-font-size"><?php echo esc_html($album_date); ?></p>
            <!-- /wp:paragraph -->
        </div>
        <!-- /wp:group -->
        <?php endforeach; ?>
    </div>
    <!-- /wp:group -->
</div>
<!-- /wp:group -->
