<?php
/**
 * Template for displaying single album posts
 */

get_header();
?>

<main class="wp-block-group" style="margin-top:var(--wp--preset--spacing--60)">
    <div class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)">
        
        <?php the_title('<h1 class="wp-block-post-title">', '</h1>'); ?>
        
        <div class="album-release-info">
            <span>Released </span>
            <?php
            $release_date = get_field('release_date');
            if ($release_date) {
                echo '<span>' . esc_html($release_date) . '</span>';
            }
            ?>
        </div>
        
        <div class="album-content">
            <?php if (has_post_thumbnail()): ?>
                <div class="album-cover">
                    <?php the_post_thumbnail('large'); ?>
                </div>
            <?php endif; ?>
            
            <div class="album-tracks">
                <p>Tracks:</p>
                <?php
                $tracks = get_field('tracks'); // Replace with your actual ACF field name for tracks
                if ($tracks): ?>
                    <ol>
                        <?php foreach ($tracks as $track): ?>
                            <li><a href="<?php echo esc_url(get_permalink($track->ID)); ?>"><?php echo esc_html(get_the_title($track->ID)); ?></a></li>
                        <?php endforeach; ?>
                    </ol>
                <?php else: ?>
                    <p>No Songs Added (yet)</p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="wp-block-post-content alignfull">
            <?php the_content(); ?>
        </div>
        
    </div>
</main>

<!-- Albums Section -->
<div class="wp-block-group alignwide" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)">
    <h2 class="albums-section-heading">Albums</h2>
    
    <?php
    // Get all albums and pass them to the template part
    $all_albums = get_posts(array(
        'post_type' => 'album',
        'posts_per_page' => 100,
        'orderby' => 'date',
        'order' => 'DESC'
    ));
    
    set_query_var('albums', $all_albums);
    get_template_part('template-parts/album-covers');
    ?>
</div>

<!-- Navigation -->
<div class="wp-block-group alignwide" style="margin-top:var(--wp--preset--spacing--60);margin-bottom:var(--wp--preset--spacing--60)">
    <nav class="wp-block-group alignwide" aria-label="Post navigation" style="border-top-color:var(--wp--preset--color--accent-6);border-top-width:1px;padding-top:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40)">
        <?php
        the_post_navigation(array(
            'prev_text' => '<span class="nav-subtitle">' . esc_html__('Previous:', 'jww-theme') . '</span> <span class="nav-title">%title</span>',
            'next_text' => '<span class="nav-subtitle">' . esc_html__('Next:', 'jww-theme') . '</span> <span class="nav-title">%title</span>',
        ));
        ?>
    </nav>
</div>

<!-- Comments -->
<div class="wp-block-comments wp-block-comments-query-loop" style="margin-top:var(--wp--preset--spacing--70);margin-bottom:var(--wp--preset--spacing--70)">
    <h2 class="wp-block-heading has-x-large-font-size">Comments</h2>
    
    <?php
    // If comments are open or we have at least one comment, load up the comment template.
    if (comments_open() || get_comments_number()) :
        comments_template();
    endif;
    ?>
</div>

<?php get_footer(); ?>
