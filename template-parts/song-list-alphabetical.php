<?php
/**
 * Template part for displaying a alphabetical song list
 * 
 * @package jww-theme
 */

// Get artist_id and show_headers from query vars (passed from block or template)
$artist_id = get_query_var('artist_id', '');
$show_headers = get_query_var('show_headers', true);
?>

<h2 class="wp-block-heading song-list-heading" id="alphabetical" name="alphabetical">Alphabetical</h2>
<?php
    $query_args = array(
        'post_type'      => 'song',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'tax_query'      => array(
            array(
                'taxonomy' => 'category',
                'field'     => 'slug',
                'terms'    => 'original'
            )
        )
    );

    // Add artist filter if artist_id is provided
    if (!empty($artist_id)) {
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
        <div class="alphabetical-song-list">
        <?php
        while ($song_query->have_posts()) {
            $song_query->the_post();
            $title = get_the_title();
            $first_letter = strtoupper(substr($title, 0, 1));
            
            // Check if we need to add a new letter header
            if ($first_letter !== $current_letter) {
                // Close previous letter group if it exists
                if ($current_letter !== '') {
                    echo '</ul>';
                    if ($show_headers) {
                        echo '</div>';
                    }
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
                <h4 class="wp-block-heading">
                    <a href="<?php the_permalink(); ?>">
                        <?php the_title(); ?>
                    </a>
                </h4>
            </li>
            <?php
        }
        // Close the last letter group
        if ($current_letter !== '') {
            echo '</ul>';
            if ($show_headers) {
                echo '</div>';
            }
        }
        ?>
        </div>
        <?php
    }
    wp_reset_postdata();
?>