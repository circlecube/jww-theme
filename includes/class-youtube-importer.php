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
        add_action( 'wp_loaded', [ $this, 'maybe_schedule_cron' ] );

        // Optional: Allow manual trigger via URL: ?import_songs=1
        add_action( 'init', [ $this, 'maybe_manual_import' ] );
        add_action( 'init', [ $this, 'maybe_add_video_ids_to_existing_posts' ] );
        add_action( 'init', [ $this, 'maybe_fix_featured_images' ] );
        add_action( 'init', [ $this, 'maybe_test_image_import' ] );
        add_action( 'init', [ $this, 'maybe_test_thumbnail_download' ] );
        add_action( 'init', [ $this, 'maybe_update_all_thumbnails' ] );
        
        // Add AJAX handlers for logs functionality
        add_action( 'wp_ajax_youtube_get_logs', [ $this, 'ajax_get_logs' ] );
        add_action( 'wp_ajax_youtube_clear_logs', [ $this, 'ajax_clear_logs' ] );
        add_action( 'wp_ajax_youtube_bulk_update_thumbnails', [ $this, 'ajax_bulk_update_thumbnails' ] );
        
        // Add admin scripts for the options page
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
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

        // Check if SimplePie is available
        if ( ! function_exists( 'fetch_feed' ) ) {
            $this->log_import_activity( 'ERROR: fetch_feed function not available - SimplePie may not be loaded' );
            return;
        }

        $feed = fetch_feed( $feed_url );
        if ( is_wp_error( $feed ) ) {
            $this->log_import_activity( 'ERROR: YouTube feed error: ' . $feed->get_error_message() );
            return;
        }

        // Check if feed has items
        $item_count = $feed->get_item_quantity();
        if ( $item_count === 0 ) {
            $this->log_import_activity( 'WARNING: YouTube feed contains no items' );
            return;
        }

        $this->log_import_activity( "YouTube feed loaded successfully with {$item_count} items" );

        $items_processed = 0;
        $max_items       = 10; // Process max 10 items per run to prevent timeouts
        $imported_count  = 0;

        foreach ( $feed->get_items() as $item ) {
            if ( $items_processed >= $max_items ) {
                $this->log_import_activity( 'Reached maximum items limit (' . $max_items . '), stopping import' );
                break;
            }

            $video_url   = esc_url( $item->get_link() );
            $video_desc  = $this->get_video_description( $item );
            $video_title = sanitize_text_field( $item->get_title() );
            $video_id    = $this->extract_video_id_from_url( $video_url );
            $item_number = $items_processed + 1;
            $this->log_import_activity( "Processing feed item #{$item_number}: '{$video_title}' (ID: {$video_id})" );

            // Check if video already imported
            if ( $this->is_video_already_imported( $video_url ) ) {
                // Before skipping, check if the existing post needs a thumbnail
                $existing_post_id = $this->get_existing_post_id_by_video_url( $video_url );
                if ( $existing_post_id ) {
                    $this->log_import_activity( "EXISTING: Video already exists - '{$video_title}' (ID: {$video_id}, Post ID: {$existing_post_id})" );
                    
                    // Check if it needs a thumbnail
                    if ( ! has_post_thumbnail( $existing_post_id ) ) {
                        $this->log_import_activity( "MISSING THUMBNAIL: Adding thumbnail to existing post '{$video_title}' (Post ID: {$existing_post_id})" );
                        $thumbnail_result = $this->set_featured_image_from_youtube( $existing_post_id, $video_url, $video_title );
                        if ( $thumbnail_result ) {
                            $this->log_import_activity( "SUCCESS: Added thumbnail to existing post '{$video_title}' (Post ID: {$existing_post_id})" );
                        } else {
                            $this->log_import_activity( "WARNING: Failed to add thumbnail to existing post '{$video_title}' (Post ID: {$existing_post_id})" );
                        }
                    } else {
                        $this->log_import_activity( "SKIP: Video already exists with thumbnail - '{$video_title}' (ID: {$video_id}, Post ID: {$existing_post_id})" );
                    }
                } else {
                    $this->log_import_activity( "SKIP: Video already exists but post ID not found - '{$video_title}' (ID: {$video_id})" );
                }
                $items_processed++;
                continue;
            }

            $this->log_import_activity( "NEW: Creating new song post for '{$video_title}' (ID: {$video_id})" );

            // Create the new song post
            $post_id = wp_insert_post( [
                'post_type'   => 'song',
                'post_status' => 'publish',
                'post_title'  => $video_title,
            ] );

            if ( ! $post_id || is_wp_error( $post_id ) ) {
                $error_msg = is_wp_error( $post_id ) ? $post_id->get_error_message() : 'Unknown error';
                $this->log_import_activity( "ERROR: Failed to create song post for '{$video_title}': {$error_msg}" );
                $items_processed++;
                continue;
            }

            $this->log_import_activity( "SUCCESS: Created song post ID {$post_id} for '{$video_title}'" );

            // Save video URL into ACF field
            if ( ! update_field( 'video', $video_url, $post_id ) ) {
                $this->log_import_activity( "WARNING: Failed to update video field for post ID: {$post_id}" );
            } else {
                $this->log_import_activity( "SUCCESS: Updated video field for post ID: {$post_id}" );
            }

            // Save video description into ACF lyrics field
            if ( ! update_field( 'lyrics', $video_desc, $post_id ) ) {
                $this->log_import_activity( "WARNING: Failed to update lyrics field for post ID: {$post_id}" );
            } else {
                $this->log_import_activity( "SUCCESS: Updated lyrics field for post ID: {$post_id}, lyrics: {$video_desc}" );
            }

            // Also save video ID as separate meta for reliable duplicate detection
            if ( $video_id ) {
                update_post_meta( $post_id, 'video_id', $video_id );
                $this->log_import_activity( "SUCCESS: Saved video ID meta for post ID: {$post_id}" );
            } else {
                $this->log_import_activity( "WARNING: Could not extract video ID for post ID: {$post_id}" );
            }

            // Try setting thumbnail as featured image
            $this->log_import_activity( "Starting thumbnail process for post ID: {$post_id}" );
            $thumbnail_result = $this->set_featured_image_from_youtube( $post_id, $video_url, $video_title );
            if ( $thumbnail_result ) {
                $this->log_import_activity( "SUCCESS: Featured image set for '{$video_title}' (post ID: {$post_id})" );
            } else {
                $this->log_import_activity( "WARNING: Featured image failed for '{$video_title}' (post ID: {$post_id}) - post created without thumbnail" );
            }

            // Mark as imported
            update_post_meta( $post_id, 'imported', '1' );

            $imported_count++;
            $this->log_import_activity( "COMPLETE: Successfully imported '{$video_title}' (post ID: {$post_id}, video ID: {$video_id})" );
            $items_processed++;
        }

        // Log final statistics
        $this->log_import_activity( 'Import completed. Processed: ' . $items_processed . ', Imported: ' . $imported_count );
        
        // Log system status for debugging
        $this->log_system_status();
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

        $processed_count       = 0;
        $thumbnail_added_count = 0;

        foreach ( $posts as $post ) {
            $post_title = get_the_title( $post->ID );
            $post_number = $processed_count + 1;
            $this->log_import_activity( "Processing existing post #{$post_number}: '{$post_title}' (ID: {$post->ID})" );

            // Skip if already has featured image
            if ( has_post_thumbnail( $post->ID ) ) {
                $this->log_import_activity( "SKIP: Post '{$post_title}' (ID: {$post->ID}) already has featured image" );
                // Mark as imported even if it already has a thumbnail
                update_post_meta( $post->ID, 'imported', '1' );
                $processed_count++;
                continue;
            }

            $video_url = get_field( 'video', $post->ID );
            if ( ! $video_url ) {
                $this->log_import_activity( "SKIP: Post '{$post_title}' (ID: {$post->ID}) has no video URL" );
                // Mark as imported even if no video URL (to avoid checking again)
                update_post_meta( $post->ID, 'imported', '1' );
                $processed_count++;
                continue;
            }

            $video_id = $this->extract_video_id_from_url( $video_url );
            $this->log_import_activity( "PROCESSING: Adding thumbnail to '{$post_title}' (ID: {$post->ID}, video ID: {$video_id})" );

            // Extract video ID and add it if missing
            if ( $video_id && ! get_post_meta( $post->ID, 'video_id', true ) ) {
                update_post_meta( $post->ID, 'video_id', $video_id );
                $this->log_import_activity( "SUCCESS: Added video ID meta for post ID: {$post->ID}" );
            }

            // Try to add thumbnail
            $thumbnail_result = $this->set_featured_image_from_youtube( $post->ID, $video_url, $post_title );
            if ( $thumbnail_result ) {
                $this->log_import_activity( "SUCCESS: Added thumbnail to '{$post_title}' (ID: {$post->ID})" );
                $thumbnail_added_count++;
            } else {
                $this->log_import_activity( "WARNING: Failed to add thumbnail to '{$post_title}' (ID: {$post->ID})" );
            }
            
            // Mark as imported
            update_post_meta( $post->ID, 'imported', '1' );
            
            $processed_count++;
        }

        $this->log_import_activity( "Processed {$processed_count} existing posts, added thumbnails to {$thumbnail_added_count} posts" );
    }

    /**
     * Get the existing post ID for a video URL
     * Returns the post ID if found, false otherwise
     * Checks both video and music_video ACF fields
     */
    private function get_existing_post_id_by_video_url( $video_url ) {
        // Extract video ID from URL for more reliable comparison
        $video_id = $this->extract_video_id_from_url( $video_url );
        
        if ( ! $video_id ) {
            return false;
        }

        // Method 1: Check by video ID in post meta (most reliable)
        $posts_by_id = get_posts( [
            'post_type'   => 'song',
            'meta_query'  => [
                [
                    'key'     => 'video_id',
                    'value'   => $video_id,
                    'compare' => '='
                ]
            ],
            'post_status' => array('publish', 'pending', 'draft'),
            'fields'       => 'ids',
        ] );

        if ( ! empty( $posts_by_id ) ) {
            return $posts_by_id[0];
        }

        // Method 2: Check by extracting video IDs from stored URLs in ACF fields
        // Get ALL song posts to check (not filtering by meta_query to ensure we catch all posts)
        $all_songs = get_posts( [
            'post_type'      => 'song',
            'post_status'    => array('publish', 'pending', 'draft', 'private'),
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ] );

        // Check each post's video and music_video fields
        foreach ( $all_songs as $post_id ) {
            // Check video field
            $stored_video_url = get_field( 'video', $post_id );
            if ( $stored_video_url && ! empty( trim( $stored_video_url ) ) ) {
                $stored_video_id = $this->extract_video_id_from_url( $stored_video_url );
                if ( $stored_video_id && $stored_video_id === $video_id ) {
                    return $post_id;
                }
            }

            // Check music_video field
            $stored_music_video_url = get_field( 'music_video', $post_id );
            if ( $stored_music_video_url && ! empty( trim( $stored_music_video_url ) ) ) {
                $stored_music_video_id = $this->extract_video_id_from_url( $stored_music_video_url );
                if ( $stored_music_video_id && $stored_music_video_id === $video_id ) {
                    return $post_id;
                }
            }
        }

        return false;
    }

    /**
     * Check if a video has already been imported
     * Uses multiple detection methods to catch duplicates
     * Checks both video and music_video ACF fields
     */
    private function is_video_already_imported( $video_url ) {
        // Extract video ID from URL for more reliable comparison
        $video_id = $this->extract_video_id_from_url( $video_url );
        
        $this->log_import_activity( "Checking if video is already imported - URL: {$video_url}, Video ID: " . ( $video_id ? $video_id : 'NOT FOUND' ) );
        
        if ( ! $video_id ) {
            $this->log_import_activity( "WARNING: Could not extract video ID from URL: {$video_url}" );
            return false;
        }

        // Method 1: Check by video ID in post meta (most reliable - finds videos imported as main video)
        $posts_by_id = get_posts( [
            'post_type'   => 'song',
            'meta_query'  => [
                [
                    'key'     => 'video_id',
                    'value'   => $video_id,
                    'compare' => '='
                ]
            ],
            'post_status' => array('publish', 'pending', 'draft'),
            'fields'       => 'ids',
        ] );

        if ( ! empty( $posts_by_id ) ) {
            $this->log_import_activity( "Found existing video by video_id meta: {$video_id} (Post IDs: " . implode( ', ', $posts_by_id ) . ")" );
            return true;
        }

        // Method 2: Check by extracting video IDs from stored URLs in ACF fields
        // Get ALL song posts to check (not filtering by meta_query to ensure we catch all posts)
        $all_songs = get_posts( [
            'post_type'      => 'song',
            'post_status'    => array('publish', 'pending', 'draft', 'private'),
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ] );

        $this->log_import_activity( "Checking " . count( $all_songs ) . " song posts for video ID: {$video_id}" );

        // Check each post's video and music_video fields
        foreach ( $all_songs as $post_id ) {
            // Check video field
            $stored_video_url = get_field( 'video', $post_id );
            if ( $stored_video_url && ! empty( trim( $stored_video_url ) ) ) {
                $stored_video_id = $this->extract_video_id_from_url( $stored_video_url );
                if ( $stored_video_id && $stored_video_id === $video_id ) {
                    $this->log_import_activity( "Found existing video in 'video' field: {$video_id} (Post ID: {$post_id}, URL: {$stored_video_url})" );
                    return true;
                }
            }

            // Check music_video field
            $stored_music_video_url = get_field( 'music_video', $post_id );
            if ( $stored_music_video_url && ! empty( trim( $stored_music_video_url ) ) ) {
                $stored_music_video_id = $this->extract_video_id_from_url( $stored_music_video_url );
                if ( $stored_music_video_id && $stored_music_video_id === $video_id ) {
                    $this->log_import_activity( "Found existing video in 'music_video' field: {$video_id} (Post ID: {$post_id}, URL: {$stored_music_video_url})" );
                    return true;
                }
            }
            
            // Debug logging for post 359 specifically
            if ( $post_id == 359 ) {
                $this->log_import_activity( "DEBUG Post 359 - video field: " . ( $stored_video_url ? $stored_video_url : 'empty' ) . ", music_video field: " . ( $stored_music_video_url ? $stored_music_video_url : 'empty' ) );
            }
        }

        $this->log_import_activity( "Video ID {$video_id} not found in any existing posts - will import as new" );
        return false;
    }

    /**
     * Get video description from YouTube feed item
     * Tries multiple methods to access the media:group>media:description node
     */
    private function get_video_description( $item ) {
        // Method 1: Try to get media:description from Media RSS namespace
        // YouTube feeds use http://search.yahoo.com/mrss/ namespace
        $media_tags = $item->get_item_tags( 'http://search.yahoo.com/mrss/', 'description' );
        if ( ! empty( $media_tags ) && isset( $media_tags[0]['data'] ) ) {
            $description = $media_tags[0]['data'];
            if ( ! empty( $description ) ) {
                $this->log_import_activity( "Found description via Media RSS namespace: " . substr( $description, 0, 100 ) );
                return sanitize_textarea_field( $description );
            }
        }
        
        // Method 2: Try alternative namespace (some feeds use different URIs)
        $media_tags = $item->get_item_tags( 'http://video.search.yahoo.com/mrss/', 'description' );
        if ( ! empty( $media_tags ) && isset( $media_tags[0]['data'] ) ) {
            $description = $media_tags[0]['data'];
            if ( ! empty( $description ) ) {
                $this->log_import_activity( "Found description via alternative Media RSS namespace: " . substr( $description, 0, 100 ) );
                return sanitize_textarea_field( $description );
            }
        }
        
        // Method 3: Try accessing media:group>media:description directly
        $media_group = $item->get_item_tags( 'http://search.yahoo.com/mrss/', 'group' );
        if ( ! empty( $media_group ) && isset( $media_group[0]['child'] ) ) {
            $children = $media_group[0]['child'];
            if ( isset( $children['http://search.yahoo.com/mrss/']['description'] ) ) {
                $description_tag = $children['http://search.yahoo.com/mrss/']['description'][0];
                if ( isset( $description_tag['data'] ) ) {
                    $description = $description_tag['data'];
                    if ( ! empty( $description ) ) {
                        $this->log_import_activity( "Found description via media:group structure: " . substr( $description, 0, 100 ) );
                        return sanitize_textarea_field( $description );
                    }
                }
            }
        }
        
        // Method 4: Fallback to standard description
        $description = $item->get_description();
        if ( ! empty( $description ) ) {
            $this->log_import_activity( "Using fallback description: " . substr( $description, 0, 100 ) );
            return sanitize_textarea_field( $description );
        }
        
        $this->log_import_activity( "WARNING: No description found for video item" );
        return '';
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
        // Ensure WordPress media functions are loaded first
        if ( ! function_exists( 'media_handle_sideload' ) ) {
            $this->log_import_activity( "Loading WordPress media functions..." );
            require_once( ABSPATH . 'wp-admin/includes/media.php' );
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
            require_once( ABSPATH . 'wp-admin/includes/image.php' );
            
            if ( ! function_exists( 'media_handle_sideload' ) ) {
                $this->log_import_activity( "ERROR: WordPress media functions still not available after loading files" );
                return false;
            }
            $this->log_import_activity( "SUCCESS: WordPress media functions loaded" );
        }

        // Extract the video ID from URL using our improved method
        $video_id = $this->extract_video_id_from_url( $video_url );
        if ( ! $video_id ) {
            $this->log_import_activity( "ERROR: Could not extract video ID from URL: {$video_url}" );
            return false;
        }

        $this->log_import_activity( "Starting featured image process for post {$post_id}, video ID: {$video_id}" );

        // Check upload directory permissions
        $upload_dir = wp_upload_dir();
        if ( ! $upload_dir || $upload_dir['error'] ) {
            $this->log_import_activity( "ERROR: Upload directory not accessible: " . ( $upload_dir['error'] ?? 'Unknown error' ) );
            return false;
        }

        // Check if upload directory is writable
        if ( ! wp_is_writable( $upload_dir['path'] ) ) {
            $this->log_import_activity( "ERROR: Upload directory not writable: {$upload_dir['path']}" );
            return false;
        }

        $this->log_import_activity( "Upload directory check passed: {$upload_dir['path']}" );

        // Try multiple thumbnail resolutions in order of preference
        $thumbnail_urls = [
            "https://img.youtube.com/vi/{$video_id}/maxresdefault.jpg",
            "https://img.youtube.com/vi/{$video_id}/hqdefault.jpg",
            "https://img.youtube.com/vi/{$video_id}/mqdefault.jpg",
            "https://img.youtube.com/vi/{$video_id}/default.jpg"
        ];

        $tmp             = false;
        $thumbnail_url   = '';
        $download_errors = [];

        // Try each thumbnail URL until one works
        foreach ( $thumbnail_urls as $url ) {
            $this->log_import_activity( "Attempting download from: {$url}" );
            
            // Add timeout and user agent for better reliability
            $response = wp_remote_get( $url, [
                'timeout'    => 30,
                'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
                'sslverify'  => true
            ] );
            
            if ( is_wp_error( $response ) ) {
                $error_msg         = $response->get_error_message();
                $download_errors[] = "{$url}: {$error_msg}";
                $this->log_import_activity( "Download failed for {$url}: {$error_msg}" );
                continue;
            }
            
            $response_code = wp_remote_retrieve_response_code( $response );
            if ( $response_code !== 200 ) {
                $download_errors[] = "{$url}: HTTP {$response_code}";
                $this->log_import_activity( "Download failed for {$url}: HTTP {$response_code}" );
                continue;
            }
            
            $body = wp_remote_retrieve_body( $response );
            if ( empty( $body ) ) {
                $download_errors[] = "{$url}: Empty response body";
                $this->log_import_activity( "Download failed for {$url}: Empty response body" );
                continue;
            }
            
            // Check if it's actually an image (not an error page)
            $content_type = wp_remote_retrieve_header( $response, 'content-type' );
            if ( strpos( $content_type, 'image/jpeg' ) === false ) {
                $download_errors[] = "{$url}: Invalid content type ({$content_type})";
                $this->log_import_activity( "Download failed for {$url}: Invalid content type ({$content_type})" );
                continue;
            }
            
            // Save to temporary file
            $tmp = wp_tempnam( 'youtube_thumb_' );
            if ( ! $tmp ) {
                $download_errors[] = "{$url}: Could not create temp file";
                $this->log_import_activity( "Download failed for {$url}: Could not create temp file" );
                continue;
            }
            
            $bytes_written = file_put_contents( $tmp, $body );
            if ( $bytes_written === false || $bytes_written === 0 ) {
                $download_errors[] = "{$url}: Could not write to temp file";
                $this->log_import_activity( "Download failed for {$url}: Could not write to temp file" );
                @unlink( $tmp );
                continue;
            }
            
            $thumbnail_url = $url;
            $this->log_import_activity( "SUCCESS: Downloaded thumbnail from {$url} ({$bytes_written} bytes)" );
            break;
        }

        if ( ! $tmp || ! file_exists( $tmp ) ) {
            $this->log_import_activity( "ERROR: Failed to download any thumbnail for video ID: {$video_id}. Errors: " . implode( '; ', $download_errors ) );
            return false;
        }

        $this->log_import_activity( "Thumbnail downloaded successfully to: {$tmp}" );

        // Verify the downloaded file exists and has content
        $file_size = filesize( $tmp );
        if ( $file_size === false || $file_size === 0 ) {
            $this->log_import_activity( "ERROR: Downloaded file is empty or doesn't exist: {$tmp} (size: " . ( $file_size === false ? 'unknown' : $file_size ) . ")" );
            @unlink( $tmp );
            return false;
        }

        // Check file size limits (WordPress default is 2MB for images)
        $max_size = wp_max_upload_size();
        if ( $file_size > $max_size ) {
            $this->log_import_activity( "ERROR: File too large: {$file_size} bytes (max: {$max_size} bytes)" );
            @unlink( $tmp );
            return false;
        }

        $this->log_import_activity( "File validation passed: {$file_size} bytes" );

        // Use video title from feed, fallback to post title if not provided
        if ( ! $video_title ) {
            $video_title = get_the_title( $post_id );
        }
        
        // Sanitize filename more aggressively
        $safe_title = sanitize_file_name( $video_title );
        $safe_title = substr( $safe_title, 0, 50 ); // Limit length to prevent filesystem issues
        
        // Create filename: video-title-video-id.jpg
        $filename = $safe_title . '-' . $video_id . '.jpg';
        
        $this->log_import_activity( "Using filename: {$filename} (from video title: {$video_title})" );

        // Use WordPress media API to sideload
        $file = [
            'name'     => $filename,
            'type'     => 'image/jpeg',
            'tmp_name' => $tmp,
            'size'     => $file_size,
            'error'    => 0, // Add error field (0 = no error)
        ];

        $this->log_import_activity( "Attempting to sideload image with size: {$file_size} bytes" );


        $this->log_import_activity( "Calling media_handle_sideload with file array: " . print_r( $file, true ) );

        $attachment_id = media_handle_sideload( $file, $post_id );
        if ( is_wp_error( $attachment_id ) ) {
            $error_message = $attachment_id->get_error_message();
            $error_code = $attachment_id->get_error_code();
            $this->log_import_activity( "ERROR: Failed to sideload image - Code: {$error_code}, Message: {$error_message}" );
            
            // Log additional debugging info
            $this->log_import_activity( "DEBUG: Temp file exists: " . ( file_exists( $tmp ) ? 'YES' : 'NO' ) );
            $this->log_import_activity( "DEBUG: Temp file size: " . ( file_exists( $tmp ) ? filesize( $tmp ) : 'N/A' ) );
            $this->log_import_activity( "DEBUG: Post ID: {$post_id}" );
            $this->log_import_activity( "DEBUG: Upload dir: " . wp_upload_dir()['path'] );
            $this->log_import_activity( "DEBUG: Upload dir writable: " . ( wp_is_writable( wp_upload_dir()['path'] ) ? 'YES' : 'NO' ) );
            
            @unlink( $tmp );
            return false;
        }

        $this->log_import_activity( "SUCCESS: Image sideloaded with attachment ID: {$attachment_id}" );

        // Set as featured image
        $result = set_post_thumbnail( $post_id, $attachment_id );
        if ( $result ) {
            $this->log_import_activity( "SUCCESS: Set featured image for post {$post_id} (attachment: {$attachment_id})" );
        } else {
            $this->log_import_activity( "ERROR: Failed to set featured image for post {$post_id} (attachment: {$attachment_id})" );
            // Don't return false here - the image was uploaded successfully, just not set as featured
        }

        // Clean up temp file
        @unlink( $tmp );
        
        return $result;
    }

    /**
     * Log import activity for debugging and monitoring
     */
    private function log_import_activity( $message ) {
        error_log( '[YouTube Importer] ' . $message );
    }

    /**
     * Log system status for debugging
     */
    private function log_system_status() {
        $upload_dir         = wp_upload_dir();
        $max_upload         = wp_max_upload_size();
        $memory_limit       = ini_get( 'memory_limit' );
        $max_execution_time = ini_get( 'max_execution_time' );
        
        $this->log_import_activity( "System Status - Upload Dir: " . ( $upload_dir['error'] ? 'ERROR: ' . $upload_dir['error'] : $upload_dir['path'] ) );
        $this->log_import_activity( "System Status - Max Upload: {$max_upload} bytes, Memory: {$memory_limit}, Max Exec Time: {$max_execution_time}s" );
        $this->log_import_activity( "System Status - WordPress Version: " . get_bloginfo( 'version' ) . ", PHP Version: " . PHP_VERSION );
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

            $fixed    = 0;
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

    /**
     * Test method to debug image importing issues
     * Call this manually if needed: ?import_songs=1&test_image_import=1
     */
    public function maybe_test_image_import() {
        if ( isset( $_GET['test_image_import'] ) && current_user_can( 'manage_options' ) ) {
            if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'import_youtube_songs' ) ) {
                wp_die( 'Security check failed' );
            }

            // Test with a known video ID
            $test_video_id  = 'IplNliTAF0A';
            $test_video_url = "https://www.youtube.com/watch?v={$test_video_id}";
            $test_title     = "Test Video";

            $this->log_import_activity( "Testing image import for video ID: {$test_video_id}" );

            // Create a test post
            $post_id = wp_insert_post( [
                'post_type'   => 'song',
                'post_status' => 'publish',
                'post_title'  => $test_title,
            ] );

            if ( $post_id && ! is_wp_error( $post_id ) ) {
                $this->log_import_activity( "Created test post with ID: {$post_id}" );
                
                // Test the image import
                $this->set_featured_image_from_youtube( $post_id, $test_video_url, $test_title );
                
                // Check if it worked
                if ( has_post_thumbnail( $post_id ) ) {
                    $attachment_id = get_post_thumbnail_id( $post_id );
                    $this->log_import_activity( "SUCCESS: Featured image set with attachment ID: {$attachment_id}" );
                    wp_die( "SUCCESS: Test image import worked! Post ID: {$post_id}, Attachment ID: {$attachment_id}" );
                } else {
                    $this->log_import_activity( "FAILED: No featured image was set for test post {$post_id}" );
                    wp_die( "FAILED: Test image import did not work. Check the debug log for details." );
                }
            } else {
                $this->log_import_activity( "Failed to create test post" );
                wp_die( "Failed to create test post" );
            }
        }
    }

    /**
     * Test method to debug thumbnail download only (without WordPress integration)
     * Call this manually if needed: ?import_songs=1&test_thumbnail_download=1
     */
    public function maybe_test_thumbnail_download() {
        if ( isset( $_GET['test_thumbnail_download'] ) && current_user_can( 'manage_options' ) ) {
            if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'import_youtube_songs' ) ) {
                wp_die( 'Security check failed' );
            }

            $test_video_id = 'IplNliTAF0A';
            $this->log_import_activity( "Testing thumbnail download for video ID: {$test_video_id}" );

            // Try multiple thumbnail resolutions
            $thumbnail_urls = [
                "https://img.youtube.com/vi/{$test_video_id}/maxresdefault.jpg",
                "https://img.youtube.com/vi/{$test_video_id}/hqdefault.jpg",
                "https://img.youtube.com/vi/{$test_video_id}/mqdefault.jpg",
                "https://img.youtube.com/vi/{$test_video_id}/default.jpg"
            ];

            foreach ( $thumbnail_urls as $url ) {
                $this->log_import_activity( "Testing download from: {$url}" );
                
                $response = wp_remote_get( $url, [
                    'timeout'    => 30,
                    'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
                    'sslverify'  => true
                ]);
                
                if ( is_wp_error( $response ) ) {
                    $this->log_import_activity( "Download failed: " . $response->get_error_message() );
                    continue;
                }
                
                $response_code = wp_remote_retrieve_response_code( $response );
                $content_type = wp_remote_retrieve_header( $response, 'content-type' );
                $body_size = strlen( wp_remote_retrieve_body( $response ) );
                
                $this->log_import_activity( "Response: HTTP {$response_code}, Content-Type: {$content_type}, Size: {$body_size} bytes" );
                
                if ( $response_code === 200 && strpos( $content_type, 'image/jpeg' ) !== false && $body_size > 0 ) {
                    $this->log_import_activity( "SUCCESS: Valid thumbnail found at {$url}" );
                    wp_die( "SUCCESS: Thumbnail download test passed! URL: {$url}, Size: {$body_size} bytes" );
                }
            }
            
            wp_die( "FAILED: No valid thumbnails could be downloaded" );
        }
    }

    /**
     * Update thumbnails for all existing songs that are missing them
     * Call this manually if needed: ?import_songs=1&update_all_thumbnails=1
     */
    public function maybe_update_all_thumbnails() {
        if ( isset( $_GET['update_all_thumbnails'] ) && current_user_can( 'manage_options' ) ) {
            if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'import_youtube_songs' ) ) {
                wp_die( 'Security check failed' );
            }

            $this->log_import_activity( "Starting bulk thumbnail update for all songs..." );

            // Get all song posts that have video URLs but no featured image
            $posts = get_posts( [
                'post_type'      => 'song',
                'posts_per_page' => -1, // Get all posts
                'meta_query'     => [
                    'relation' => 'AND',
                    [
                        'key'     => 'field_68cb1e19f3fe2', // ACF field key for 'video' field
                        'compare' => 'EXISTS'
                    ]
                ]
            ] );

            $processed_count = 0;
            $thumbnail_added_count = 0;
            $skipped_count = 0;

            foreach ( $posts as $post ) {
                $post_title = get_the_title( $post->ID );
                $processed_count++;
                
                // Skip if already has featured image
                if ( has_post_thumbnail( $post->ID ) ) {
                    $this->log_import_activity( "SKIP: Post '{$post_title}' (ID: {$post->ID}) already has featured image" );
                    $skipped_count++;
                    continue;
                }

                $video_url = get_field( 'video', $post->ID );
                if ( ! $video_url ) {
                    $this->log_import_activity( "SKIP: Post '{$post_title}' (ID: {$post->ID}) has no video URL" );
                    $skipped_count++;
                    continue;
                }

                $video_id = $this->extract_video_id_from_url( $video_url );
                $this->log_import_activity( "PROCESSING: Adding thumbnail to '{$post_title}' (ID: {$post->ID}, video ID: {$video_id})" );

                // Try to add thumbnail
                $thumbnail_result = $this->set_featured_image_from_youtube( $post->ID, $video_url, $post_title );
                if ( $thumbnail_result ) {
                    $this->log_import_activity( "SUCCESS: Added thumbnail to '{$post_title}' (ID: {$post->ID})" );
                    $thumbnail_added_count++;
                } else {
                    $this->log_import_activity( "WARNING: Failed to add thumbnail to '{$post_title}' (ID: {$post->ID})" );
                }
            }

            $this->log_import_activity( "Bulk thumbnail update completed. Processed: {$processed_count}, Added: {$thumbnail_added_count}, Skipped: {$skipped_count}" );
            wp_die( "Bulk thumbnail update completed! Processed: {$processed_count}, Added: {$thumbnail_added_count}, Skipped: {$skipped_count}" );
        }
    }

    /**
     * Enqueue admin scripts for the options page
     */
    public function enqueue_admin_scripts( $hook ) {
        // Only load on the YouTube import options page
        if ( $hook !== 'toplevel_page_youtube-import-settings' ) {
            return;
        }

        wp_enqueue_script( 'jquery' );
        
        // Add inline script for logs functionality
        wp_add_inline_script( 'jquery', $this->get_logs_script() );
        
        // Add inline styles
        wp_add_inline_style( 'wp-admin', $this->get_logs_styles() );
    }

    /**
     * Get the JavaScript for logs functionality
     */
    private function get_logs_script() {
        return "
        jQuery(document).ready(function($) {
            // Load logs on page load
            loadLogs();
            
            // Refresh logs button
            $('#refresh-logs').on('click', function() {
                loadLogs();
            });
            
            // Clear logs button
            $('#clear-logs').on('click', function() {
                if (confirm('Are you sure you want to clear all YouTube import logs?')) {
                    clearLogs();
                }
            });
            
            // Bulk update thumbnails button
            $('#bulk-update-thumbnails').on('click', function() {
                if (confirm('Are you sure you want to bulk update thumbnails for all songs? This may take a while.')) {
                    bulkUpdateThumbnails();
                }
            });
            
            function loadLogs() {
                $('#youtube-logs-content').html('Loading logs...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'youtube_get_logs',
                        nonce: '" . wp_create_nonce( 'youtube_logs_nonce' ) . "'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#youtube-logs-content').html(response.data);
                        } else {
                            $('#youtube-logs-content').html('<p style=\"color: red;\">Error loading logs: ' + response.data + '</p>');
                        }
                    },
                    error: function() {
                        $('#youtube-logs-content').html('<p style=\"color: red;\">Error loading logs. Please try again.</p>');
                    }
                });
            }
            
            function clearLogs() {
                $('#youtube-logs-content').html('Clearing logs...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'youtube_clear_logs',
                        nonce: '" . wp_create_nonce( 'youtube_logs_nonce' ) . "'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#youtube-logs-content').html('<p style=\"color: green;\">Logs cleared successfully!</p>');
                        } else {
                            $('#youtube-logs-content').html('<p style=\"color: red;\">Error clearing logs: ' + response.data + '</p>');
                        }
                    },
                    error: function() {
                        $('#youtube-logs-content').html('<p style=\"color: red;\">Error clearing logs. Please try again.</p>');
                    }
                });
            }
            
            function bulkUpdateThumbnails() {
                $('#youtube-logs-content').html('Starting bulk thumbnail update... This may take a while.');
                $('#bulk-update-thumbnails').prop('disabled', true).text('Processing...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'youtube_bulk_update_thumbnails',
                        nonce: '" . wp_create_nonce( 'youtube_logs_nonce' ) . "'
                    },
                    success: function(response) {
                        if (response.success) {
                            var data = response.data;
                            var message = 'Comprehensive bulk update completed!\\n\\n';
                            message += 'Total songs: ' + data.total_songs + '\\n';
                            message += 'Processed: ' + data.processed + ' songs\\n';
                            message += 'Thumbnails added: ' + data.thumbnails_added + '\\n';
                            message += 'Video IDs added: ' + data.video_ids_added + '\\n';
                            message += 'Lyrics tags added: ' + data.lyrics_tags_added + '\\n';
                            message += 'Skipped: ' + data.skipped + ' (already had thumbnails)\\n';
                            message += 'No video URL: ' + data.no_video + '\\n';
                            
                            if (data.errors && data.errors.length > 0) {
                                message += 'Errors: ' + data.errors.length + '\\n';
                                message += 'Error details: ' + data.errors.join(', ');
                            }
                            
                            alert(message);
                            loadLogs(); // Refresh logs to show the new activity
                        } else {
                            $('#youtube-logs-content').html('<p style=\"color: red;\">Error updating thumbnails: ' + response.data + '</p>');
                        }
                    },
                    error: function() {
                        $('#youtube-logs-content').html('<p style=\"color: red;\">Error updating thumbnails. Please try again.</p>');
                    },
                    complete: function() {
                        $('#bulk-update-thumbnails').prop('disabled', false).text('Bulk Update Thumbnails');
                    }
                });
            }
        });
        ";
    }

    /**
     * Get the CSS styles for logs display
     */
    private function get_logs_styles() {
        return "
        #youtube-logs-content {
            background: #f1f1f1;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            max-height: 400px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 12px;
            line-height: 1.4;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        #youtube-logs-content .log-entry {
            margin-bottom: 5px;
            padding: 2px 0;
        }
        #youtube-logs-content .log-entry.error {
            color: #d63638;
        }
        #youtube-logs-content .log-entry.success {
            color: #00a32a;
        }
        #youtube-logs-content .log-entry.info {
            color: #0073aa;
        }
        #youtube-logs-content .log-timestamp {
            color: #666;
            font-weight: bold;
        }
        ";
    }

    /**
     * AJAX handler to get logs
     */
    public function ajax_get_logs() {
        // Check nonce
        if ( ! wp_verify_nonce( $_POST['nonce'], 'youtube_logs_nonce' ) ) {
            wp_die( 'Security check failed' );
        }

        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Insufficient permissions' );
        }

        $logs = $this->get_recent_logs();
        wp_send_json_success( $logs );
    }

    /**
     * AJAX handler to clear logs
     */
    public function ajax_clear_logs() {
        // Check nonce
        if ( ! wp_verify_nonce( $_POST['nonce'], 'youtube_logs_nonce' ) ) {
            wp_die( 'Security check failed' );
        }

        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Insufficient permissions' );
        }

        $this->clear_logs();
        wp_send_json_success( 'Logs cleared successfully' );
    }

    /**
     * AJAX handler to bulk update thumbnails
     */
    public function ajax_bulk_update_thumbnails() {
        // Check nonce
        if ( ! wp_verify_nonce( $_POST['nonce'], 'youtube_logs_nonce' ) ) {
            wp_die( 'Security check failed' );
        }

        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Insufficient permissions' );
        }

        $this->log_import_activity( "Starting comprehensive bulk thumbnail update via AJAX..." );

        // Get ALL song posts (not just recent ones)
        $posts = get_posts( [
            'post_type'      => 'song',
            'posts_per_page' => -1, // Get ALL posts
            'post_status'    => 'any', // Include all post statuses
            'orderby'        => 'date',
            'order'          => 'DESC'
        ] );

        $this->log_import_activity( "Found " . count( $posts ) . " total song posts to check" );

        $processed_count = 0;
        $thumbnail_added_count = 0;
        $skipped_count = 0;
        $video_id_added_count = 0;
        $no_video_count = 0;
        $lyrics_tag_added_count = 0;
        $errors = [];

        foreach ( $posts as $post ) {
            $post_title = get_the_title( $post->ID );
            $processed_count++;
            
            $this->log_import_activity( "Checking post #{$processed_count}: '{$post_title}' (ID: {$post->ID})" );

            // Check for lyrics and add 'needs-lyrics' tag if missing
            $lyrics = get_field( 'lyrics', $post->ID );
            if ( empty( $lyrics ) || trim( $lyrics ) === '' ) {
                // Get existing tags
                $existing_tags = wp_get_post_terms( $post->ID, 'post_tag', array( 'fields' => 'slugs' ) );
                
                // Add 'needs-lyrics' tag if not already present
                if ( ! in_array( 'needs-lyrics', $existing_tags ) ) {
                    if ( empty( $existing_tags ) ) {
                        $existing_tags = array();
                    }
                    $existing_tags[] = 'needs-lyrics';
                    wp_set_post_terms( $post->ID, $existing_tags, 'post_tag' );
                    $this->log_import_activity( "SUCCESS: Added 'needs-lyrics' tag to '{$post_title}' (ID: {$post->ID})" );
                    $lyrics_tag_added_count++;
                } else {
                    $this->log_import_activity( "SKIP: Post '{$post_title}' (ID: {$post->ID}) already has 'needs-lyrics' tag" );
                }
            } else {
                // Remove 'needs-lyrics' tag if lyrics exist
                $existing_tags = wp_get_post_terms( $post->ID, 'post_tag', array( 'fields' => 'slugs' ) );
                if ( in_array( 'needs-lyrics', $existing_tags ) ) {
                    $existing_tags = array_diff( $existing_tags, array( 'needs-lyrics' ) );
                    wp_set_post_terms( $post->ID, $existing_tags, 'post_tag' );
                    $this->log_import_activity( "SUCCESS: Removed 'needs-lyrics' tag from '{$post_title}' (ID: {$post->ID}) - lyrics found" );
                }
            }

            // Get video URL
            $video_url = get_field( 'video', $post->ID );
            if ( ! $video_url ) {
                $this->log_import_activity( "SKIP: Post '{$post_title}' (ID: {$post->ID}) has no video URL" );
                $no_video_count++;
                continue;
            }

            // Extract video ID and add it if missing
            $video_id = $this->extract_video_id_from_url( $video_url );
            if ( ! $video_id ) {
                $this->log_import_activity( "SKIP: Post '{$post_title}' (ID: {$post->ID}) - could not extract video ID from URL: {$video_url}" );
                $skipped_count++;
                continue;
            }

            // Add video_id meta if missing
            $existing_video_id = get_post_meta( $post->ID, 'video_id', true );
            if ( ! $existing_video_id ) {
                update_post_meta( $post->ID, 'video_id', $video_id );
                $this->log_import_activity( "SUCCESS: Added missing video_id meta for '{$post_title}' (ID: {$post->ID}, video ID: {$video_id})" );
                $video_id_added_count++;
            }

            // Skip if already has featured image
            if ( has_post_thumbnail( $post->ID ) ) {
                $this->log_import_activity( "SKIP: Post '{$post_title}' (ID: {$post->ID}) already has featured image" );
                $skipped_count++;
                continue;
            }

            $this->log_import_activity( "PROCESSING: Adding thumbnail to '{$post_title}' (ID: {$post->ID}, video ID: {$video_id})" );

            // Try to add thumbnail
            $thumbnail_result = $this->set_featured_image_from_youtube( $post->ID, $video_url, $post_title );
            if ( $thumbnail_result ) {
                $this->log_import_activity( "SUCCESS: Added thumbnail to '{$post_title}' (ID: {$post->ID})" );
                $thumbnail_added_count++;
            } else {
                $error_msg = "Failed to add thumbnail to '{$post_title}' (ID: {$post->ID})";
                $this->log_import_activity( "WARNING: {$error_msg}" );
                $errors[] = $error_msg;
            }
        }

        $total_songs = count( $posts );
        $this->log_import_activity( "Comprehensive bulk update completed. Total songs: {$total_songs}, Processed: {$processed_count}, Thumbnails added: {$thumbnail_added_count}, Video IDs added: {$video_id_added_count}, Lyrics tags added: {$lyrics_tag_added_count}, Skipped: {$skipped_count}, No video: {$no_video_count}" );
        
        $response_data = [
            'total_songs' => $total_songs,
            'processed' => $processed_count,
            'thumbnails_added' => $thumbnail_added_count,
            'video_ids_added' => $video_id_added_count,
            'lyrics_tags_added' => $lyrics_tag_added_count,
            'skipped' => $skipped_count,
            'no_video' => $no_video_count,
            'errors' => $errors
        ];

        wp_send_json_success( $response_data );
    }

    /**
     * Get recent logs from the debug log file
     */
    private function get_recent_logs( $limit = 50 ) {
        $debug_log = WP_CONTENT_DIR . '/debug.log';
        
        if ( ! file_exists( $debug_log ) ) {
            return '<p>No debug log file found.</p>';
        }

        // Read the last part of the log file
        $lines = file( $debug_log, FILE_IGNORE_NEW_LINES );
        if ( $lines === false ) {
            return '<p>Could not read debug log file.</p>';
        }

        // Filter for YouTube Importer logs
        $youtube_logs = array_filter( $lines, function( $line ) {
            return strpos( $line, '[YouTube Importer]' ) !== false;
        } );

        // Get the most recent logs
        $youtube_logs = array_slice( $youtube_logs, -$limit );

        if ( empty( $youtube_logs ) ) {
            return '<p>No YouTube import logs found.</p>';
        }

        // Format the logs
        $formatted_logs = '';
        foreach ( $youtube_logs as $log ) {
            $formatted_logs .= $this->format_log_entry( $log );
        }

        return $formatted_logs;
    }

    /**
     * Format a single log entry for display
     */
    private function format_log_entry( $log ) {
        // Extract timestamp
        if ( preg_match( '/^\[([^\]]+)\]/', $log, $matches ) ) {
            $timestamp = $matches[1];
            $message = substr( $log, strlen( $matches[0] ) );
        } else {
            $timestamp = '';
            $message = $log;
        }

        // Determine log level and styling
        $class = 'info';
        if ( strpos( $message, 'Failed' ) !== false || strpos( $message, 'Error' ) !== false ) {
            $class = 'error';
        } elseif ( strpos( $message, 'Successfully' ) !== false || strpos( $message, 'SUCCESS' ) !== false ) {
            $class = 'success';
        }

        return sprintf(
            '<div class="log-entry %s"><span class="log-timestamp">[%s]</span> %s</div>',
            esc_attr( $class ),
            esc_html( $timestamp ),
            esc_html( $message )
        );
    }

    /**
     * Clear YouTube import logs from the debug log
     */
    private function clear_logs() {
        $debug_log = WP_CONTENT_DIR . '/debug.log';
        
        if ( ! file_exists( $debug_log ) ) {
            return;
        }

        // Read all lines
        $lines = file( $debug_log, FILE_IGNORE_NEW_LINES );
        if ( $lines === false ) {
            return;
        }

        // Filter out YouTube Importer logs
        $filtered_lines = array_filter( $lines, function( $line ) {
            return strpos( $line, '[YouTube Importer]' ) === false;
        } );

        // Write back the filtered content
        file_put_contents( $debug_log, implode( "\n", $filtered_lines ) . "\n" );
    }
}

new YouTube_Song_Importer();