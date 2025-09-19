<?php
/**
 * Template for displaying single song posts
 */

get_header();
?>

<main class="wp-block-group align is-layout-flow wp-container-core-group-is-layout-a77db08e wp-block-group-is-layout-flow">
    <div
        class="wp-block-group has-global-padding is-layout-constrained wp-block-group-is-layout-constrained" 
        style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50)"
    >
        
        <?php the_title('<h1 class="wp-block-post-title alignwide has-text-align-center has-xxx-large-font-size">', '</h1>'); ?>
        
        <div class="wp-block-post-content">
            <?php the_content(); ?>
        </div>
        
        <?php
        // Video field
        $video = get_field('video');
        if ($video): ?>
            <div class="wp-block-group alignwide video-section">
                <div class="video-container has-text-align-center">
                    <?php echo $video; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <?php
        // Get the album field and pass it to the template part
        $albums = get_field('album');
        set_query_var('albums', $albums);
        get_template_part('template-parts/album-covers');
        ?>
    </div>
</main>

<!-- Lyrics Section -->
<div class="wp-block-group has-accent-6-background-color has-background" style="border-style:none;border-width:0px">
    <div style="height:4rem" aria-hidden="true" class="wp-block-spacer"></div>
    
    <h2 class="wp-block-heading">Lyrics</h2>
    
    <?php
    $lyrics = get_field('lyrics'); // Replace with your actual ACF field name
    if ($lyrics): ?>
        <div class="lyrics-content">
            <?php echo wp_kses_post($lyrics); ?>
        </div>
    <?php else: ?>
        <p>None entered yet.</p>
    <?php endif; ?>
    
    <div style="height:4rem" aria-hidden="true" class="wp-block-spacer"></div>
</div>

<!-- Annotations Section -->
<div class="wp-block-group is-style-default has-base-background-color has-background" style="margin-top:0;margin-bottom:0">
    <div style="height:4rem" aria-hidden="true" class="wp-block-spacer"></div>
    
    <h3 class="wp-block-heading">Annotations and Notes on Lyrics</h3>
    
    <?php
    $annotations = get_field('annotations'); // Replace with your actual ACF field name
    if ($annotations): ?>
        <div class="annotations-content has-medium-font-size">
            <?php echo wp_kses_post($annotations); ?>
        </div>
    <?php else: ?>
        <p>None entered yet.</p>
    <?php endif; ?>
    
    <div style="height:4rem" aria-hidden="true" class="wp-block-spacer"></div>
</div>

<!-- Navigation -->
<div class="wp-block-group alignwide is-style-section-5" style="border-top-width:1px;border-bottom-width:1px;margin-top:0;margin-bottom:0">
    <div class="wp-block-group alignwide" style="border-top-color:var(--wp--preset--color--accent-6);border-top-width:1px">
        <nav class="wp-block-group alignwide" aria-label="Post navigation" style="padding-top:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40)">
            <?php
            the_post_navigation(array(
                'prev_text' => '<span class="nav-subtitle">' . esc_html__('Previous:', 'jww-theme') . '</span> <span class="nav-title">%title</span>',
                'next_text' => '<span class="nav-subtitle">' . esc_html__('Next:', 'jww-theme') . '</span> <span class="nav-title">%title</span>',
            ));
            ?>
        </nav>
    </div>
</div>

<!-- Comments -->
<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50)">
    <div style="height:var(--wp--preset--spacing--20)" aria-hidden="true" class="wp-block-spacer"></div>
    
    <div class="wp-block-comments wp-block-comments-query-loop" style="margin-top:var(--wp--preset--spacing--70);margin-bottom:var(--wp--preset--spacing--70)">
        <h2 class="wp-block-heading has-x-large-font-size">Comments</h2>
        
        <?php
        // If comments are open or we have at least one comment, load up the comment template.
        if (comments_open() || get_comments_number()) :
            comments_template();
        endif;
        ?>
    </div>
</div>

<?php get_footer(); ?>
