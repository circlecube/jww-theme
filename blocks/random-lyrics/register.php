<?php
/**
 * Random Lyrics Block Registration
 */

function jww_register_random_lyrics_block() {
    register_block_type(
        get_stylesheet_directory() . '/blocks/random-lyrics',
        [
            'render_callback' => 'jww_render_random_lyrics_block',
        ]
    );
}
add_action('init', 'jww_register_random_lyrics_block');

/**
 * Render callback for the random lyrics block
 */
function jww_render_random_lyrics_block($attributes, $content, $block) {
    ob_start();
    include get_stylesheet_directory() . '/blocks/random-lyrics/render.php';
    return ob_get_clean();
}

/**
 * AJAX handler for refreshing random lyrics
 */
function jww_ajax_get_random_lyrics() {
    // Check nonce
    if (!wp_verify_nonce($_POST['nonce'], 'random_lyrics_nonce')) {
        wp_die('Security check failed');
    }

    // Get a random song posts that has lyrics
    $songs = get_posts([
        'post_type' => 'song',
        'posts_per_page' => '1',
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
        wp_send_json_error('No songs with lyrics found');
        return;
    }

    // Get a random song
    $random_song_id = $songs[0];
    $song_title = get_the_title($random_song_id);
    $lyrics = get_field('lyrics', $random_song_id);

    if (empty($lyrics)) {
        wp_send_json_error('No lyrics found for this song');
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
        wp_send_json_error('No suitable lyrics lines found');
        return;
    }

    // Get a random line
    $random_line = $lyrics_lines[array_rand($lyrics_lines)];
    // Strip HTML tags from the lyrics line
    $random_line = strip_tags($random_line);

    // Get block attributes from the request or use defaults
    $show_song_title = isset($_POST['show_title']) ? $_POST['show_title'] === 'true' : true;
    $show_artist = isset($_POST['show_artist']) ? $_POST['show_artist'] === 'true' : true;

    // Use shared template part to generate HTML
    $args = [
        'song_id' => $random_song_id,
        'lyrics_line' => $random_line,
        'show_song_title' => $show_song_title,
        'show_artist' => $show_artist
    ];
    
    ob_start();
    get_template_part('template-parts/random-lyrics-quote', null, $args);
    $html = ob_get_clean();

    wp_send_json_success(['html' => $html]);
}
add_action('wp_ajax_get_random_lyrics', 'jww_ajax_get_random_lyrics');
add_action('wp_ajax_nopriv_get_random_lyrics', 'jww_ajax_get_random_lyrics');
