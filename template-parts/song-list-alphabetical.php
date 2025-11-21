<?php
/**
 * Template part for displaying a alphabetical song list
 * 
 * @package jww-theme
 */

// Get artist_id, show_headers, and song_type from query vars (passed from block or template)
$artist_id = get_query_var('artist_id', '');
$show_headers = get_query_var('show_headers', true);
$song_type = get_query_var('song_type', 'originals');
?>


<?php
$query_args = array(
    'post_type'      => 'song',
    'posts_per_page' => -1,
    'orderby'        => 'title',
    'order'          => 'ASC'
);

// Add tax_query based on song_type
if ($song_type === 'covers') {
    $query_args['tax_query'] = array(
        array(
            'taxonomy' => 'category',
            'field'     => 'slug',
            'terms'    => 'cover'
        )
    );
} elseif ($song_type === 'originals') {
    $query_args['tax_query'] = array(
        array(
            'taxonomy' => 'category',
            'field'     => 'slug',
            'terms'    => 'original'
        )
    );
} elseif ($song_type === 'both') {
    $query_args['tax_query'] = array(
        array(
            'taxonomy' => 'category',
            'field'     => 'slug',
            'terms'    => array('original', 'cover')
        )
    );
}

// Add artist filter if artist_id is provided
if (!empty($artist_id) && $artist_id !== 'all') {
    $artist_id = intval($artist_id);
    // ACF relationship fields store data as serialized arrays
    // Check for the ID in serialized format: "i:ID;" or "s:2:\"ID\";"
    $query_args['meta_query'] = array(
        array(
            'key'     => 'artist',
            'value'   => '"' . $artist_id . '"',
            'compare' => 'LIKE'
        )
    );
}

$song_query = new WP_Query($query_args);

if ($song_query->have_posts()) { 
    $current_letter = '';
    ?>
    <div class="alphabetical-song-list <?php echo $show_headers ? 'with-headers' : ''; ?>">
    <ul class="wp-block-list song-list">
    <?php
    while ($song_query->have_posts()) {
        $song_query->the_post();
        $title = get_the_title();
        $first_letter = strtoupper(substr($title, 0, 1));
        
        // Check if we need to add a new letter header
        if ( $show_headers && $first_letter !== $current_letter) {
            // Close previous letter group if it exists
            if ($current_letter !== '') {
                echo '</ul>';
                echo '</div>';
            }
            
            // Add letter header (only if show_headers is true)
            if ($show_headers) {
                echo '<div class="alphabet-section">';
                echo '<h3 class="alphabet-letter" id="letter-' . strtolower($first_letter) . '">' . $first_letter . '</h3>';
            }
            echo '<ul class="wp-block-list song-list">';
            
            $current_letter = $first_letter;
        }
        ?>
        <li class="wp-block-list-item">
            <?php if ($show_headers) { ?><h4 class="wp-block-heading"><?php } ?>
                <a href="<?php the_permalink(); ?>">
                    <?php the_title(); ?>
                    <?php 
                        if ($artist_id === 'all') {
                            $artists = get_field('artist');
                            echo '<span class="song-meta song-artist">(' . $artists[0]->post_title . ')</span>';
                        }
                    ?>
                    <?php 
                        $cats = get_the_category();
                        foreach ($cats as $cat) {
                            if ($cat->slug === 'cover') {
                                echo '<span class="song-meta song-attribution">(' . get_field('attribution') . ')</span>';
                            }
                        }
                    ?>
                    <span class="song-meta song-date">(<?php echo get_the_date(); ?>)</span>
                </a>
            <?php if ($show_headers) { ?></h4><?php } ?>
        </li>
        <?php
    }
    // Close the last letter group
    if ($show_headers && $current_letter !== '') {
        echo '</ul>';
        echo '</div>';
    }
    ?>
    </ul>
    </div>
    <?php
}
wp_reset_postdata();
?>