<?php
/**
 * Upgrade routine for version 1.3.0
 * 
 * Migrates data from 'attibution' field to 'attribution' field
 * to fix the typo in the field name.
 * 
 * @since 1.3.0
 */

// Get all posts that have the old 'attibution' field
$posts = get_posts( [
    'post_type' => 'song',
    'posts_per_page' => -1,
    'post_status' => 'any',
    'meta_query' => [
        [
            'key' => 'attibution',
            'compare' => 'EXISTS'
        ]
    ]
] );

$migrated_count = 0;

foreach ( $posts as $post ) {
    // Get the old field value
    $old_value = get_field( 'attibution', $post->ID );
    
    // Check if new field is empty
    $new_value = get_field( 'attribution', $post->ID );
    
    if ( ! empty( $old_value ) && empty( $new_value ) ) {
        // Migrate the data
        update_field( 'attribution', $old_value, $post->ID );
        $migrated_count++;
    }
}

// Log the migration results
error_log( "Theme Upgrade 1.2.0: Migrated 'attibution' to 'attribution' for {$migrated_count} songs." );
