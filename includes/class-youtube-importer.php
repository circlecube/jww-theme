<?php
/**
 * Class YouTube_Song_Importer
 *
 * Auto-imports videos from a YouTube channel RSS feed as 'song' posts.
 * Requires ACF and a custom field 'video' (type: URL or text).
 */

class YouTube_Song_Importer {

    public function __construct() {
        // Run import on schedule or manually via URL param
        add_action( 'import_youtube_songs_event', [ $this, 'import_feed' ] );
        add_action( 'init', [ $this, 'maybe_schedule_cron' ] );

        // Optional: Allow manual trigger via URL: ?import_songs=1
        add_action( 'init', [ $this, 'maybe_manual_import' ] );
        add_action( 'init', [ $this, 'maybe_add_video_ids_to_existing_posts' ] );
        add_action( 'init', [ $this, 'maybe_fix_featured_images' ] );
    }

    /**
     * Schedule the cron job based on ACF options page setting
     */
    public function maybe_schedule_cron() {
        $enabled = get_field( 'youtube_import_enabled', 'option' );
        
        // If disabled, clear any existing schedule and return
        if ( ! $enabled ) {
            $timestamp = wp_next_scheduled( 'import_youtube_songs_event' );
            if ( $timestamp ) {
                wp_clear_scheduled_hook( 'import_youtube_songs_event' );
                $this->log_import_activity( 'YouTube import cron disabled - cleared existing schedule' );
            }
            return;
        }

        $interval = get_field( 'youtube_import_interval', 'option' ) ?: 'hourly'; // hourly, twicedaily, daily

        // Clear existing schedule if interval changed
        $timestamp = wp_next_scheduled( 'import_youtube_songs_event' );
        if ( $timestamp && wp_get_schedule( 'import_youtube_songs_event' ) !== $interval ) {
            wp_clear_scheduled_hook( 'import_youtube_songs_event' );
        }

        if ( ! wp_next_scheduled( 'import_youtube_songs_event' ) ) {
            wp_schedule_event( time(), $interval, 'import_youtube_songs_event' );
            $this->log_import_activity( 'YouTube import cron enabled - scheduled for ' . $interval );
        }
    }

    /**
     * Optional manual trigger (for testing)
     */
    public function maybe_manual_import() {
        if ( isset( $_GET['import_songs'] ) && current_user_can( 'manage_options' ) ) {
            // Add nonce verification for security
            if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'import_youtube_songs' ) ) {
                wp_die( 'Security check failed' );
            }
            
            // Check if import is enabled
            $enabled = get_field( 'youtube_import_enabled', 'option' );
            if ( ! $enabled ) {
                wp_die( 'YouTube import is disabled. Enable it in the options page first.' );
            }
            
            $this->import_feed();
            wp_die( 'YouTube songs imported!' );
        }
    }

    /**
     * Fetch and import the YouTube feed
     */
    public function import_feed() {
        // Check if import is enabled
        $enabled = get_field( 'youtube_import_enabled', 'option' );
        if ( ! $enabled ) {
            $this->log_import_activity( 'YouTube import is disabled - skipping import' );
            return;
        }

        // First, process existing posts that need thumbnails
        $this->process_existing_posts_for_thumbnails();

        include_once ABSPATH . WPINC . '/feed.php';
        
        $feed_url = get_field( 'youtube_feed_url', 'option' );
        if ( empty( $feed_url ) ) {
            $this->log_import_activity( 'No YouTube feed URL configured - skipping import' );
            return;
        }
        
        if ( strpos( $feed_url, 'feeds/videos.xml' ) === false ) {
            // If user entered a channel handle like @xyz, try to convert it
            $feed_url = $this->resolve_channel_feed( $feed_url );
        }

        $this->log_import_activity( 'Starting YouTube feed import from: ' . $feed_url );

        $feed = fetch_feed( $feed_url );
        if ( is_wp_error( $feed ) ) {
            $this->log_import_activity( 'YouTube feed error: ' . $feed->get_error_message() );
            return;
        }

        $items_processed = 0;
        $max_items = 10; // Process max 10 items per run to prevent timeouts
        $imported_count = 0;

        foreach ( $feed->get_items() as $item ) {
            if ( $items_processed >= $max_items ) {
                $this->log_import_activity( 'Reached maximum items limit (' . $max_items . '), stopping import' );
                break;
            }

            $video_url   = esc_url( $item->get_link() );
            $video_title = sanitize_text_field( $item->get_title() );

            // Skip if video already imported
            if ( $this->is_video_already_imported( $video_url ) ) {
                $this->log_import_activity( 'Skipping duplicate video: ' . $video_title );
                $items_processed++;
                continue;
            }

            // Create the new song post
            $post_id = wp_insert_post( [
                'post_type'   => 'song',
                'post_status' => 'publish',
                'post_title'  => $video_title,
            ] );

            if ( ! $post_id || is_wp_error( $post_id ) ) {
                $this->log_import_activity( 'Failed to create song post for: ' . $video_title );
                $items_processed++;
                continue;
            }

            // Save video URL into ACF field
            if ( ! update_field( 'video', $video_url, $post_id ) ) {
                $this->log_import_activity( 'Failed to update video field for post ID: ' . $post_id );
            }

            // Also save video ID as separate meta for reliable duplicate detection
            $video_id = $this->extract_video_id_from_url( $video_url );
            if ( $video_id ) {
                update_post_meta( $post_id, 'video_id', $video_id );
            }

            // Try setting thumbnail as featured image
            $this->set_featured_image_from_youtube( $post_id, $video_url, $video_title );

            // Mark as imported
            update_post_meta( $post_id, 'imported', '1' );

            $imported_count++;
            $this->log_import_activity( 'Successfully imported: ' . $video_title . ' (ID: ' . $post_id . ')' );
            $items_processed++;
        }

        $this->log_import_activity( 'Import completed. Processed: ' . $items_processed . ', Imported: ' . $imported_count );
    }

    /**
     * Process existing song posts to add thumbnails if missing
     * This runs before importing new videos to ensure existing posts have thumbnails
     */
    private function process_existing_posts_for_thumbnails() {
        $this->log_import_activity( 'Processing existing posts for missing thumbnails...' );

        // Get all song posts that have video URLs but no featured image and haven't been processed yet
        $posts = get_posts( [
            'post_type'      => 'song',
            'posts_per_page' => 50, // Process in batches to prevent timeouts
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => 'field_68cb1e19f3fe2', // ACF field key for 'video' field
                    'compare' => 'EXISTS'
                ],
                [
                    'key'     => 'imported',
                    'compare' => 'NOT EXISTS'
                ]
            ]
        ] );

        $processed_count = 0;
        $thumbnail_added_count = 0;

        foreach ( $posts as $post ) {
            // Skip if already has featured image
            if ( has_post_thumbnail( $post->ID ) ) {
                // Mark as imported even if it already has a thumbnail
                update_post_meta( $post->ID, 'imported', '1' );
                $processed_count++;
                continue;
            }

            $video_url = get_field( 'video', $post->ID );
            if ( ! $video_url ) {
                // Mark as imported even if no video URL (to avoid checking again)
                update_post_meta( $post->ID, 'imported', '1' );
                $processed_count++;
                continue;
            }

            // Extract video ID and add it if missing
            $video_id = $this->extract_video_id_from_url( $video_url );
            if ( $video_id && ! get_post_meta( $post->ID, 'video_id', true ) ) {
                update_post_meta( $post->ID, 'video_id', $video_id );
            }

            // Try to add thumbnail
            $this->set_featured_image_from_youtube( $post->ID, $video_url, get_the_title( $post->ID ) );
            
            // Mark as imported
            update_post_meta( $post->ID, 'imported', '1' );
            
            $processed_count++;
            $thumbnail_added_count++;
        }

        $this->log_import_activity( "Processed {$processed_count} existing posts, added thumbnails to {$thumbnail_added_count} posts" );
    }

    /**
     * Check if a video has already been imported
     * Uses multiple detection methods to catch duplicates
     */
    private function is_video_already_imported( $video_url ) {
        // Extract video ID from URL for more reliable comparison
        $video_id = $this->extract_video_id_from_url( $video_url );
        
        if ( ! $video_id ) {
            return false;
        }

        // Method 1: Check by video ID in post meta (most reliable)
        $posts_by_id = get_posts( [
            'post_type'  => 'song',
            'meta_query' => [
                [
                    'key'     => 'video_id',
                    'value'   => $video_id,
                    'compare' => '='
                ]
            ],
            'fields'     => 'ids',
        ] );

        if ( ! empty( $posts_by_id ) ) {
            return true;
        }

        // Method 2: Check by normalized URL in ACF field
        $normalized_url = $this->normalize_youtube_url( $video_url );
        $posts_by_url = get_posts( [
            'post_type'  => 'song',
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key'     => 'field_68cb1e19f3fe2', // ACF field key for 'video' field
                    'value'   => $normalized_url,
                    'compare' => '='
                ],
                [
                    'key'     => 'field_68cb1e19f3fe2',
                    'value'   => $video_url,
                    'compare' => '='
                ]
            ],
            'fields'     => 'ids',
        ] );

        return ! empty( $posts_by_url );
    }

    /**
     * Extract video ID from YouTube URL
     */
    private function extract_video_id_from_url( $url ) {
        // Handle various YouTube URL formats
        $patterns = [
            '/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/',
            '/youtube\.com\/v\/([a-zA-Z0-9_-]{11})/',
        ];

        foreach ( $patterns as $pattern ) {
            if ( preg_match( $pattern, $url, $matches ) ) {
                return $matches[1];
            }
        }

        return false;
    }

    /**
     * Normalize YouTube URL to standard format
     */
    private function normalize_youtube_url( $url ) {
        $video_id = $this->extract_video_id_from_url( $url );
        if ( $video_id ) {
            return 'https://www.youtube.com/watch?v=' . $video_id;
        }
        return $url;
    }

    /**
     * Attempt to resolve a @handle to a feed URL using YouTube's page HTML
     */
    private function resolve_channel_feed( $handle_url ) {
        $handle_url = trailingslashit( $handle_url );
        $html = wp_remote_retrieve_body( wp_remote_get( $handle_url ) );
        if ( preg_match( '/"channelId":"(UC[0-9A-Za-z_-]+)"/', $html, $matches ) ) {
            return 'https://www.youtube.com/feeds/videos.xml?channel_id=' . $matches[1];
        }
        return $handle_url; // fallback
    }

    /**
     * Download and attach the YouTube video thumbnail as featured image
     */
    private function set_featured_image_from_youtube( $post_id, $video_url, $video_title = null ) {
        // Extract the video ID from URL using our improved method
        $video_id = $this->extract_video_id_from_url( $video_url );
        if ( ! $video_id ) {
            $this->log_import_activity( "Could not extract video ID from URL: {$video_url}" );
            return;
        }

        $this->log_import_activity( "Setting featured image for post {$post_id}, video ID: {$video_id}" );

        // YouTube thumbnails follow a standard URL pattern
        $thumbnail_url = "https://img.youtube.com/vi/{$video_id}/maxresdefault.jpg";
        $this->log_import_activity( "Downloading thumbnail from: {$thumbnail_url}" );

        // Download the image
        $tmp = download_url( $thumbnail_url );
        if ( is_wp_error( $tmp ) ) {
            $this->log_import_activity( "Failed to download thumbnail: " . $tmp->get_error_message() );
            return;
        }

        $this->log_import_activity( "Thumbnail downloaded to: {$tmp}" );

        // Use video title from feed, fallback to post title if not provided
        if ( ! $video_title ) {
            $video_title = get_the_title( $post_id );
        }
        
        $safe_title = sanitize_file_name( $video_title );
        
        // Create filename: video-title-video-id.jpg
        $filename = $safe_title . '-' . $video_id . '.jpg';
        
        $this->log_import_activity( "Using filename: {$filename} (from video title: {$video_title})" );

        // Use WordPress media API to sideload
        $file = [
            'name'     => $filename,
            'type'     => 'image/jpeg',
            'tmp_name' => $tmp,
            'size'     => filesize( $tmp ),
        ];

        $attachment_id = media_handle_sideload( $file, $post_id );
        if ( is_wp_error( $attachment_id ) ) {
            $this->log_import_activity( "Failed to sideload image: " . $attachment_id->get_error_message() );
            @unlink( $tmp );
            return;
        }

        $this->log_import_activity( "Image sideloaded with attachment ID: {$attachment_id}" );

        // Set as featured image
        $result = set_post_thumbnail( $post_id, $attachment_id );
        if ( $result ) {
            $this->log_import_activity( "Successfully set featured image for post {$post_id}" );
        } else {
            $this->log_import_activity( "Failed to set featured image for post {$post_id}" );
        }

        // Clean up temp file
        @unlink( $tmp );
    }

    /**
     * Log import activity for debugging and monitoring
     */
    private function log_import_activity( $message ) {
        error_log( '[YouTube Importer] ' . $message );
    }

    /**
     * Utility method to add video_id meta to existing posts (run once to fix existing data)
     * Call this manually if needed: ?import_songs=1&add_video_ids=1
     */
    public function maybe_add_video_ids_to_existing_posts() {
        if ( isset( $_GET['add_video_ids'] ) && current_user_can( 'manage_options' ) ) {
            if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'import_youtube_songs' ) ) {
                wp_die( 'Security check failed' );
            }

            $posts = get_posts( [
                'post_type'      => 'song',
                'posts_per_page' => -1,
                'meta_query'     => [
                    [
                        'key'     => 'field_68cb1e19f3fe2',
                        'compare' => 'EXISTS'
                    ],
                    [
                        'key'     => 'video_id',
                        'compare' => 'NOT EXISTS'
                    ]
                ]
            ] );

            $updated = 0;
            foreach ( $posts as $post ) {
                $video_url = get_field( 'video', $post->ID );
                if ( $video_url ) {
                    $video_id = $this->extract_video_id_from_url( $video_url );
                    if ( $video_id ) {
                        update_post_meta( $post->ID, 'video_id', $video_id );
                        $updated++;
                    }
                }
            }

            wp_die( "Updated {$updated} posts with video_id meta." );
        }
    }

    /**
     * Utility method to fix featured images for existing posts
     * Call this manually if needed: ?import_songs=1&fix_featured_images=1
     */
    public function maybe_fix_featured_images() {
        if ( isset( $_GET['fix_featured_images'] ) && current_user_can( 'manage_options' ) ) {
            if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'import_youtube_songs' ) ) {
                wp_die( 'Security check failed' );
            }

            $posts = get_posts( [
                'post_type'      => 'song',
                'posts_per_page' => -1,
                'meta_query'     => [
                    [
                        'key'     => 'video_id',
                        'compare' => 'EXISTS'
                    ]
                ]
            ] );

            $fixed = 0;
            $skipped = 0;
            foreach ( $posts as $post ) {
                // Skip if already has featured image
                if ( has_post_thumbnail( $post->ID ) ) {
                    $skipped++;
                    continue;
                }

                $video_id = get_post_meta( $post->ID, 'video_id', true );
                if ( $video_id ) {
                    // Get video title (should be the same as post title for imported songs)
                    $video_title = get_the_title( $post->ID );
                    $safe_title = sanitize_file_name( $video_title );
                    $filename_pattern = $safe_title . '-' . $video_id . '.jpg';
                    
                    // Try to find existing attachment by filename (new pattern)
                    $attachment = get_posts( [
                        'post_type'      => 'attachment',
                        'post_mime_type' => 'image',
                        'meta_query'     => [
                            [
                                'key'     => '_wp_attached_file',
                                'value'   => $filename_pattern,
                                'compare' => 'LIKE'
                            ]
                        ]
                    ] );
                    
                    // If not found with new pattern, try old pattern as fallback
                    if ( empty( $attachment ) ) {
                        $attachment = get_posts( [
                            'post_type'      => 'attachment',
                            'post_mime_type' => 'image',
                            'meta_query'     => [
                                [
                                    'key'     => '_wp_attached_file',
                                    'value'   => "youtube-{$video_id}.jpg",
                                    'compare' => 'LIKE'
                                ]
                            ]
                        ] );
                    }

                    if ( ! empty( $attachment ) ) {
                        // Found existing attachment, set as featured image
                        set_post_thumbnail( $post->ID, $attachment[0]->ID );
                        $fixed++;
                        $this->log_import_activity( "Fixed featured image for post {$post->ID} using existing attachment {$attachment[0]->ID}" );
                    } else {
                        // No existing attachment, try to download new one
                        $video_url = get_field( 'video', $post->ID );
                        if ( $video_url ) {
                            $this->set_featured_image_from_youtube( $post->ID, $video_url, $video_title );
                            $fixed++;
                        }
                    }
                }
            }

            wp_die( "Fixed {$fixed} posts with featured images, skipped {$skipped} that already had featured images." );
        }
    }
}

new YouTube_Song_Importer();