<?php
/**
 * Class YouTube_Song_Importer
 *
 * Auto-imports videos from a YouTube channel RSS feed as 'song' posts.
 * Uses the 'embeds' repeater field (with embed_type='youtube' and youtube_video sub-field).
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
        add_action( 'wp_ajax_youtube_check_now', [ $this, 'ajax_check_now' ] );
        add_action( 'wp_ajax_youtube_get_cron_status', [ $this, 'ajax_get_cron_status' ] );
        
        // Add admin scripts for the options page
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
        
        // Add custom HTML to the options page
        add_action( 'admin_footer', [ $this, 'add_options_page_buttons' ] );
    }

    /**
     * Schedule the cron job based on ACF options page setting
     */
    public function maybe_schedule_cron() {
        $enabled = $this->get_option_with_fallback( 'youtube_import_enabled' );
        
        // If disabled, clear any existing schedule and return
        if ( ! $enabled ) {
            $timestamp = wp_next_scheduled( 'import_youtube_songs_event' );
            if ( $timestamp ) {
                wp_clear_scheduled_hook( 'import_youtube_songs_event' );
                $this->log_import_activity( 'YouTube import cron disabled - cleared existing schedule' );
            }
            return;
        }

        $interval = $this->get_option_with_fallback( 'youtube_import_interval' ) ?: 'hourly'; // hourly, twicedaily, daily

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
     * Get ACF option with fallback to raw option value
     * This ensures options work even if ACF isn't fully loaded (e.g., during cron)
     */
    private function get_option_with_fallback( $field_name ) {
        // Try ACF first
        if ( function_exists( 'get_field' ) ) {
            $value = get_field( $field_name, 'option' );
            if ( $value !== null && $value !== false ) {
                return $value;
            }
        }
        
        // Fallback to raw WordPress option (ACF stores options with 'options_' prefix)
        $raw_value = get_option( 'options_' . $field_name );
        if ( $raw_value !== false ) {
            return $raw_value;
        }
        
        return false;
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
     * Checks for new videos in the feed and creates song posts for them
     */
    public function import_feed() {
        // Check if import is enabled (with fallback for cron context)
        $enabled = $this->get_option_with_fallback( 'youtube_import_enabled' );
        if ( ! $enabled ) {
            $this->log_import_activity( '⊘ Import disabled' );
            return;
        }

        include_once ABSPATH . WPINC . '/feed.php';
        
        $feed_url = $this->get_option_with_fallback( 'youtube_feed_url' );
        if ( empty( $feed_url ) ) {
            $this->log_import_activity( '✗ No feed URL configured' );
            return;
        }
        
        if ( strpos( $feed_url, 'feeds/videos.xml' ) === false ) {
            $feed_url = $this->resolve_channel_feed( $feed_url );
        }

        $this->log_import_activity( '▶ Checking feed for new videos...' );

        if ( ! function_exists( 'fetch_feed' ) ) {
            $this->log_import_activity( '✗ Feed error: SimplePie not available' );
            return;
        }

        $feed = fetch_feed( $feed_url );
        if ( is_wp_error( $feed ) ) {
            $this->log_import_activity( '✗ Feed error: ' . $feed->get_error_message() );
            return;
        }

        $item_count = $feed->get_item_quantity();
        if ( $item_count === 0 ) {
            $this->log_import_activity( '⊘ Feed empty - no videos found' );
            return;
        }

        $items_processed = 0;
        $max_items       = 10;
        $imported_count  = 0;
        $skipped_count   = 0;

        foreach ( $feed->get_items() as $item ) {
            if ( $items_processed >= $max_items ) {
                break;
            }

            $video_url   = esc_url( $item->get_link() );
            $video_desc  = $this->get_video_description( $item );
            $video_title = sanitize_text_field( $item->get_title() );
            $video_id    = $this->extract_video_id_from_url( $video_url );

            // Skip if video already has a song post
            if ( $this->is_video_already_imported( $video_url ) ) {
                $skipped_count++;
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
                $this->log_import_activity( '✗ ERROR: "' . $video_title . '" - failed to create post' );
                $items_processed++;
                continue;
            }

            // Clean YouTube URL (remove ?feature=oembed parameter if present)
            $video_url = $this->clean_youtube_url( $video_url );
            
            // Save video URL to new embeds repeater field
            $embeds = array(
                array(
                    'embed_type' => 'youtube',
                    'youtube_video' => $video_url,
                ),
            );
            update_field( 'embeds', $embeds, $post_id );
            
            // Save description
            update_field( 'lyrics', $video_desc, $post_id );
            if ( $video_id ) {
                update_post_meta( $post_id, 'video_id', $video_id );
            }

            // Set thumbnail from YouTube
            $this->set_featured_image_from_youtube( $post_id, $video_url, $video_title );

            // Mark as imported
            update_post_meta( $post_id, 'imported', '1' );

            // Add 'needs-notes' tag since new imports won't have lyric_annotations
            wp_set_post_terms( $post_id, array( 'needs-notes' ), 'post_tag', true );

            $imported_count++;
            $this->log_import_activity( '✓ NEW: "' . $video_title . '"' );
            
            // Send email notification
            $this->send_import_notification_email( $post_id, $video_title, $video_url, $video_id );
            
            $items_processed++;
        }

        // Summary line
        $this->log_import_activity( "▣ Done: {$imported_count} new, {$skipped_count} existing, {$item_count} in feed" );
    }

    /**
     * Process existing song posts to add thumbnails if missing
     * This runs before importing new videos to ensure existing posts have thumbnails
     * Checks YouTube and TikTok video fields
     */
    private function process_existing_posts_for_thumbnails() {
        $this->log_import_activity( 'Processing existing posts for missing thumbnails...' );

        // Get all song posts that have video URLs (YouTube or TikTok) but no featured image and haven't been processed yet
        $posts = get_posts( [
            'post_type'      => 'song',
            'posts_per_page' => 50, // Process in batches to prevent timeouts
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'relation' => 'OR',
                    [
                        'key'     => 'field_68cb1e19f3fe2', // ACF field key for 'video' field (YouTube)
                        'compare' => 'EXISTS'
                    ],
                    [
                        'key'     => 'field_68ee7c26f2346', // ACF field key for 'tiktok_video' field
                        'compare' => 'EXISTS'
                    ]
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

            $thumbnail_result = false;
            $video_url = $this->get_youtube_video_url( $post->ID );
            $tiktok_url = get_field( 'tiktok_video', $post->ID );

            // Try YouTube first if available
            if ( $video_url ) {
                $video_id = $this->extract_video_id_from_url( $video_url );
                $this->log_import_activity( "PROCESSING: Adding YouTube thumbnail to '{$post_title}' (ID: {$post->ID}, video ID: {$video_id})" );

                // Extract video ID and add it if missing
                if ( $video_id && ! get_post_meta( $post->ID, 'video_id', true ) ) {
                    update_post_meta( $post->ID, 'video_id', $video_id );
                    $this->log_import_activity( "SUCCESS: Added video ID meta for post ID: {$post->ID}" );
                }

                // Try to add thumbnail
                $thumbnail_result = $this->set_featured_image_from_youtube( $post->ID, $video_url, $post_title );
                if ( $thumbnail_result ) {
                    $this->log_import_activity( "SUCCESS: Added YouTube thumbnail to '{$post_title}' (ID: {$post->ID})" );
                    $thumbnail_added_count++;
                } else {
                    $this->log_import_activity( "WARNING: Failed to add YouTube thumbnail to '{$post_title}' (ID: {$post->ID})" );
                }
            }

            // If YouTube didn't work or wasn't available, try TikTok
            if ( ! $thumbnail_result && $tiktok_url ) {
                // Extract TikTok URL from oembed field if needed (get raw value to avoid HTML)
                $tiktok_url_raw = get_field( 'tiktok_video', $post->ID, false );
                if ( empty( $tiktok_url_raw ) ) {
                    $tiktok_url_raw = $tiktok_url;
                }
                
                // If the URL is wrapped in HTML, extract it
                if ( is_string( $tiktok_url_raw ) && strpos( $tiktok_url_raw, '<' ) !== false ) {
                    if ( preg_match( '/href=["\']([^"\']+)["\']/', $tiktok_url_raw, $matches ) ) {
                        $tiktok_url_raw = $matches[1];
                    } elseif ( preg_match( '/https?:\/\/[^\s<>"\']+/', $tiktok_url_raw, $matches ) ) {
                        $tiktok_url_raw = $matches[0];
                    }
                }
                
                $this->log_import_activity( "PROCESSING: Adding TikTok thumbnail to '{$post_title}' (ID: {$post->ID}, TikTok URL: {$tiktok_url_raw})" );
                
                // Try to add thumbnail from TikTok
                $thumbnail_result = $this->set_featured_image_from_tiktok( $post->ID, $tiktok_url_raw, $post_title );
                if ( $thumbnail_result ) {
                    $this->log_import_activity( "SUCCESS: Added TikTok thumbnail to '{$post_title}' (ID: {$post->ID})" );
                    $thumbnail_added_count++;
                } else {
                    $this->log_import_activity( "WARNING: Failed to add TikTok thumbnail to '{$post_title}' (ID: {$post->ID})" );
                }
            }

            // If neither YouTube nor TikTok worked, log it
            if ( ! $video_url && ! $tiktok_url ) {
                $this->log_import_activity( "SKIP: Post '{$post_title}' (ID: {$post->ID}) has no video URL (YouTube or TikTok)" );
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

        // Check each post's embeds repeater field
        foreach ( $all_songs as $post_id ) {
            $embeds = get_field( 'embeds', $post_id );
            if ( $embeds && is_array( $embeds ) ) {
                foreach ( $embeds as $embed ) {
                    if ( isset( $embed['embed_type'] ) && $embed['embed_type'] === 'youtube' && ! empty( $embed['youtube_video'] ) ) {
                        $stored_video_url = $embed['youtube_video'];
                        // Handle ACF oembed field - can return array or string
                        if ( is_array( $stored_video_url ) ) {
                            $stored_video_url = $stored_video_url['url'] ?? $stored_video_url['html'] ?? '';
                        }
                        if ( $stored_video_url ) {
                            // Clean YouTube URL (remove ?feature=oembed parameter)
                            $stored_video_url = $this->clean_youtube_url( $stored_video_url );
                            $stored_video_id = $this->extract_video_id_from_url( $stored_video_url );
                            if ( $stored_video_id && $stored_video_id === $video_id ) {
                                return $post_id;
                            }
                        }
                    }
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
        $video_id = $this->extract_video_id_from_url( $video_url );
        
        if ( ! $video_id ) {
            return false;
        }

        // Method 1: Check by video ID in post meta
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
            'fields'      => 'ids',
        ] );

        if ( ! empty( $posts_by_id ) ) {
            return true;
        }

        // Method 2: Check by extracting video IDs from stored URLs in ACF fields
        $all_songs = get_posts( [
            'post_type'      => 'song',
            'post_status'    => array('publish', 'pending', 'draft', 'private'),
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ] );

        foreach ( $all_songs as $post_id ) {
            $embeds = get_field( 'embeds', $post_id );
            if ( $embeds && is_array( $embeds ) ) {
                foreach ( $embeds as $embed ) {
                    if ( isset( $embed['embed_type'] ) && $embed['embed_type'] === 'youtube' && ! empty( $embed['youtube_video'] ) ) {
                        $stored_video_url = $embed['youtube_video'];
                        // Handle ACF oembed field - can return array or string
                        if ( is_array( $stored_video_url ) ) {
                            $stored_video_url = $stored_video_url['url'] ?? $stored_video_url['html'] ?? '';
                        }
                        if ( $stored_video_url ) {
                            // Clean YouTube URL (remove ?feature=oembed parameter)
                            $stored_video_url = $this->clean_youtube_url( $stored_video_url );
                            $stored_video_id = $this->extract_video_id_from_url( $stored_video_url );
                            if ( $stored_video_id && $stored_video_id === $video_id ) {
                                return true;
                            }
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * Get video description from YouTube feed item
     * Tries multiple methods to access the media:group>media:description node
     */
    private function get_video_description( $item ) {
        // Method 1: Try to get media:description from Media RSS namespace
        $media_tags = $item->get_item_tags( 'http://search.yahoo.com/mrss/', 'description' );
        if ( ! empty( $media_tags ) && isset( $media_tags[0]['data'] ) && ! empty( $media_tags[0]['data'] ) ) {
            return sanitize_textarea_field( $media_tags[0]['data'] );
        }
        
        // Method 2: Try alternative namespace
        $media_tags = $item->get_item_tags( 'http://video.search.yahoo.com/mrss/', 'description' );
        if ( ! empty( $media_tags ) && isset( $media_tags[0]['data'] ) && ! empty( $media_tags[0]['data'] ) ) {
            return sanitize_textarea_field( $media_tags[0]['data'] );
        }
        
        // Method 3: Try accessing media:group>media:description directly
        $media_group = $item->get_item_tags( 'http://search.yahoo.com/mrss/', 'group' );
        if ( ! empty( $media_group ) && isset( $media_group[0]['child']['http://search.yahoo.com/mrss/']['description'][0]['data'] ) ) {
            $description = $media_group[0]['child']['http://search.yahoo.com/mrss/']['description'][0]['data'];
            if ( ! empty( $description ) ) {
                return sanitize_textarea_field( $description );
            }
        }
        
        // Method 4: Fallback to standard description
        $description = $item->get_description();
        if ( ! empty( $description ) ) {
            return sanitize_textarea_field( $description );
        }
        
        return '';
    }

    /**
     * Get YouTube video URL from a post (checks embeds repeater field)
     *
     * @param int $post_id Post ID
     * @return string|false Video URL or false if not found
     */
    private function get_youtube_video_url( $post_id ) {
        // Check embeds repeater field
        $embeds = get_field( 'embeds', $post_id );
        if ( $embeds && is_array( $embeds ) ) {
            foreach ( $embeds as $embed ) {
                if ( isset( $embed['embed_type'] ) && $embed['embed_type'] === 'youtube' && ! empty( $embed['youtube_video'] ) ) {
                    $video_url = $embed['youtube_video'];
                    // Handle ACF oembed field - can return array or string
                    if ( is_array( $video_url ) ) {
                        $video_url = $video_url['url'] ?? $video_url['html'] ?? '';
                    }
                    if ( $video_url ) {
                        // Clean YouTube URL (remove ?feature=oembed parameter)
                        return $this->clean_youtube_url( $video_url );
                    }
                }
            }
        }
        
        return false;
    }

    /**
     * Clean YouTube URL by removing unwanted parameters
     *
     * @param string $url YouTube URL
     * @return string Cleaned URL
     */
    private function clean_youtube_url( $url ) {
        if ( empty( $url ) ) {
            return $url;
        }
        
        // Remove ?feature=oembed and other unwanted parameters from YouTube URLs
        $url = preg_replace( '/\?feature=oembed(&.*)?$/', '', $url );
        $url = preg_replace( '/\?feature=oembed&/', '?', $url );
        
        return $url;
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
     * Download and attach the TikTok video thumbnail as featured image
     */
    private function set_featured_image_from_tiktok( $post_id, $tiktok_url, $post_title = null ) {
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

        // Normalize TikTok URL
        $tiktok_url = esc_url_raw( trim( $tiktok_url ) );
        if ( empty( $tiktok_url ) ) {
            $this->log_import_activity( "ERROR: Empty TikTok URL provided" );
            return false;
        }

        // Extract TikTok video ID from URL
        $video_id = $this->extract_tiktok_video_id_from_url( $tiktok_url );
        if ( ! $video_id ) {
            $this->log_import_activity( "ERROR: Could not extract TikTok video ID from URL: {$tiktok_url}" );
            return false;
        }

        $this->log_import_activity( "Starting featured image process for post {$post_id}, TikTok video ID: {$video_id}" );

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

        // Get thumbnail URL from TikTok oEmbed API
        $thumbnail_url = $this->get_tiktok_thumbnail_url( $tiktok_url );
        
        if ( ! $thumbnail_url ) {
            $this->log_import_activity( "ERROR: Could not retrieve thumbnail URL from TikTok for: {$tiktok_url}" );
            return false;
        }

        $this->log_import_activity( "Attempting download from TikTok: {$thumbnail_url}" );

        // Download the thumbnail
        $response = wp_remote_get( $thumbnail_url, [
            'timeout'    => 30,
            'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
            'sslverify'  => true
        ] );

        if ( is_wp_error( $response ) ) {
            $error_msg = $response->get_error_message();
            $this->log_import_activity( "ERROR: Failed to download TikTok thumbnail: {$error_msg}" );
            return false;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code !== 200 ) {
            $this->log_import_activity( "ERROR: Failed to download TikTok thumbnail: HTTP {$response_code}" );
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        if ( empty( $body ) ) {
            $this->log_import_activity( "ERROR: Empty response body from TikTok thumbnail" );
            return false;
        }

        // Check if it's actually an image
        $content_type = wp_remote_retrieve_header( $response, 'content-type' );
        if ( strpos( $content_type, 'image' ) === false ) {
            $this->log_import_activity( "ERROR: Invalid content type from TikTok ({$content_type})" );
            return false;
        }

        // Save to temporary file
        $tmp = wp_tempnam( 'tiktok_thumb_' );
        if ( ! $tmp ) {
            $this->log_import_activity( "ERROR: Could not create temp file for TikTok thumbnail" );
            return false;
        }

        $bytes_written = file_put_contents( $tmp, $body );
        if ( $bytes_written === false || $bytes_written === 0 ) {
            $this->log_import_activity( "ERROR: Could not write to temp file for TikTok thumbnail" );
            @unlink( $tmp );
            return false;
        }

        $this->log_import_activity( "SUCCESS: Downloaded TikTok thumbnail ({$bytes_written} bytes)" );

        // Verify the downloaded file exists and has content
        $file_size = filesize( $tmp );
        if ( $file_size === false || $file_size === 0 ) {
            $this->log_import_activity( "ERROR: Downloaded file is empty or doesn't exist: {$tmp}" );
            @unlink( $tmp );
            return false;
        }

        // Check file size limits
        $max_size = wp_max_upload_size();
        if ( $file_size > $max_size ) {
            $this->log_import_activity( "ERROR: File too large: {$file_size} bytes (max: {$max_size} bytes)" );
            @unlink( $tmp );
            return false;
        }

        $this->log_import_activity( "File validation passed: {$file_size} bytes" );

        // Use post title, fallback if not provided
        if ( ! $post_title ) {
            $post_title = get_the_title( $post_id );
        }

        // Sanitize filename
        $safe_title = sanitize_file_name( $post_title );
        $safe_title = substr( $safe_title, 0, 50 ); // Limit length

        // Determine file extension from content type
        $extension = 'jpg'; // default
        if ( strpos( $content_type, 'image/png' ) !== false ) {
            $extension = 'png';
        } elseif ( strpos( $content_type, 'image/webp' ) !== false ) {
            $extension = 'webp';
        }

        // Create filename: post-title-tiktok-video-id.ext
        $filename = $safe_title . '-tiktok-' . $video_id . '.' . $extension;

        $this->log_import_activity( "Using filename: {$filename} (from post title: {$post_title})" );

        // Use WordPress media API to sideload
        $file = [
            'name'     => $filename,
            'type'     => $content_type,
            'tmp_name' => $tmp,
            'size'     => $file_size,
            'error'    => 0,
        ];

        $this->log_import_activity( "Attempting to sideload TikTok image with size: {$file_size} bytes" );

        $attachment_id = media_handle_sideload( $file, $post_id );
        if ( is_wp_error( $attachment_id ) ) {
            $error_message = $attachment_id->get_error_message();
            $error_code = $attachment_id->get_error_code();
            $this->log_import_activity( "ERROR: Failed to sideload TikTok image - Code: {$error_code}, Message: {$error_message}" );
            @unlink( $tmp );
            return false;
        }

        $this->log_import_activity( "SUCCESS: TikTok image sideloaded with attachment ID: {$attachment_id}" );

        // Set as featured image
        $result = set_post_thumbnail( $post_id, $attachment_id );
        if ( $result ) {
            $this->log_import_activity( "SUCCESS: Set featured image for post {$post_id} (attachment: {$attachment_id})" );
        } else {
            $this->log_import_activity( "ERROR: Failed to set featured image for post {$post_id} (attachment: {$attachment_id})" );
        }

        // Clean up temp file
        @unlink( $tmp );

        return $result;
    }

    /**
     * Extract TikTok video ID from URL
     */
    private function extract_tiktok_video_id_from_url( $url ) {
        // Handle various TikTok URL formats:
        // https://www.tiktok.com/@username/video/VIDEO_ID
        // https://vm.tiktok.com/SHORT_CODE/
        // https://tiktok.com/@username/video/VIDEO_ID
        $patterns = [
            '/tiktok\.com\/@[^\/]+\/video\/(\d+)/',
            '/vm\.tiktok\.com\/([a-zA-Z0-9]+)/',
        ];

        foreach ( $patterns as $pattern ) {
            if ( preg_match( $pattern, $url, $matches ) ) {
                return $matches[1];
            }
        }

        return false;
    }

    /**
     * Get TikTok thumbnail URL using oEmbed API
     */
    private function get_tiktok_thumbnail_url( $tiktok_url ) {
        // Use TikTok oEmbed API to get thumbnail
        $oembed_url = 'https://www.tiktok.com/oembed?url=' . urlencode( $tiktok_url );
        
        $this->log_import_activity( "Fetching TikTok oEmbed data from: {$oembed_url}" );

        $response = wp_remote_get( $oembed_url, [
            'timeout'    => 30,
            'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
            'sslverify'  => true
        ] );

        if ( is_wp_error( $response ) ) {
            $this->log_import_activity( "ERROR: Failed to fetch TikTok oEmbed: " . $response->get_error_message() );
            return false;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code !== 200 ) {
            $this->log_import_activity( "ERROR: TikTok oEmbed returned HTTP {$response_code}" );
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! $data || ! isset( $data['thumbnail_url'] ) ) {
            $this->log_import_activity( "ERROR: TikTok oEmbed response missing thumbnail_url. Response keys: " . ( $data ? implode( ', ', array_keys( $data ) ) : 'null' ) );
            return false;
        }

        $thumbnail_url = $data['thumbnail_url'];
        $this->log_import_activity( "SUCCESS: Retrieved TikTok thumbnail URL: {$thumbnail_url}" );

        return $thumbnail_url;
    }

    /**
     * Send email notification to admin when a new song is imported
     */
    private function send_import_notification_email( $post_id, $video_title, $video_url, $video_id ) {
        // Get admin email
        $admin_email = get_option( 'admin_email' );
        
        if ( ! $admin_email ) {
            $this->log_import_activity( "WARNING: Admin email not configured, skipping notification" );
            return;
        }
        
        // Get edit link for the new post
        $edit_link = admin_url( 'post.php?action=edit&post=' . $post_id );
        $view_link = get_permalink( $post_id );
        
        // Email subject
        $subject = sprintf( 
            '[%s] New Song Imported: %s',
            get_bloginfo( 'name' ),
            $video_title
        );
        
        // Email body
        $message = sprintf(
            "A new song has been imported from YouTube:\n\n" .
            "Title: %s\n" .
            "Video ID: %s\n" .
            "Video URL: %s\n" .
            "Post ID: %d\n\n" .
            "Edit Post: %s\n" .
            "View Post: %s\n\n" .
            "---\n" .
            "This is an automated notification from the YouTube Importer.",
            esc_html( $video_title ),
            esc_html( $video_id ),
            esc_url( $video_url ),
            $post_id,
            esc_url( $edit_link ),
            esc_url( $view_link )
        );
        
        // Send email
        $sent = wp_mail( $admin_email, $subject, $message );
        
        if ( $sent ) {
            $this->log_import_activity( "SUCCESS: Email notification sent to {$admin_email} for '{$video_title}'" );
        } else {
            $this->log_import_activity( "WARNING: Failed to send email notification for '{$video_title}'" );
        }
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
                $video_url = $this->get_youtube_video_url( $post->ID );
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
                        $video_url = $this->get_youtube_video_url( $post->ID );
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

            // Get all song posts that have video URLs (YouTube or TikTok) but no featured image
            $posts = get_posts( [
                'post_type'      => 'song',
                'posts_per_page' => -1, // Get all posts
                'meta_query'     => [
                    'relation' => 'OR',
                    [
                        'key'     => 'field_68cb1e19f3fe2', // ACF field key for 'video' field (YouTube)
                        'compare' => 'EXISTS'
                    ],
                    [
                        'key'     => 'field_68ee7c26f2346', // ACF field key for 'tiktok_video' field
                        'compare' => 'EXISTS'
                    ]
                ]
            ] );

            $processed_count = 0;
            $thumbnail_added_count = 0;
            $skipped_count = 0;
            $image_tag_added_count = 0;
            $video_id_added_count = 0;
            $no_video_count = 0;
            $errors = [];

            foreach ( $posts as $post ) {
                $post_title = get_the_title( $post->ID );
                $processed_count++;
                
                // Check for featured image and add 'needs-image' tag if missing
                if ( ! has_post_thumbnail( $post->ID ) ) {
                    // Get existing tags
                    $existing_tags = wp_get_post_terms( $post->ID, 'post_tag', array( 'fields' => 'slugs' ) );
                    
                    // Add 'needs-image' tag if not already present
                    if ( ! in_array( 'needs-image', $existing_tags ) ) {
                        if ( empty( $existing_tags ) ) {
                            $existing_tags = array();
                        }
                        $existing_tags[] = 'needs-image';
                        wp_set_post_terms( $post->ID, $existing_tags, 'post_tag' );
                        $this->log_import_activity( "SUCCESS: Added 'needs-image' tag to '{$post_title}' (ID: {$post->ID})" );
                        $image_tag_added_count++;
                    }
                } else {
                    // Remove 'needs-image' tag if featured image exists
                    $existing_tags = wp_get_post_terms( $post->ID, 'post_tag', array( 'fields' => 'slugs' ) );
                    if ( in_array( 'needs-image', $existing_tags ) ) {
                        $existing_tags = array_diff( $existing_tags, array( 'needs-image' ) );
                        wp_set_post_terms( $post->ID, $existing_tags, 'post_tag' );
                        $this->log_import_activity( "SUCCESS: Removed 'needs-image' tag from '{$post_title}' (ID: {$post->ID}) - featured image exists" );
                    }
                    $this->log_import_activity( "SKIP: Post '{$post_title}' (ID: {$post->ID}) already has featured image" );
                    $skipped_count++;
                    continue;
                }

                $thumbnail_result = false;
                $video_url = $this->get_youtube_video_url( $post->ID );
                $tiktok_url = get_field( 'tiktok_video', $post->ID );

                // Try YouTube first if available
                if ( $video_url ) {
                    // Extract video ID and add it if missing
                    $video_id = $this->extract_video_id_from_url( $video_url );
                    if ( ! $video_id ) {
                        $this->log_import_activity( "SKIP: Post '{$post_title}' (ID: {$post->ID}) - could not extract video ID from URL: {$video_url}" );
                        // Continue to try TikTok if available
                    } else {
                        // Add video_id meta if missing
                        $existing_video_id = get_post_meta( $post->ID, 'video_id', true );
                        if ( ! $existing_video_id ) {
                            update_post_meta( $post->ID, 'video_id', $video_id );
                            $this->log_import_activity( "SUCCESS: Added missing video_id meta for '{$post_title}' (ID: {$post->ID}, video ID: {$video_id})" );
                            $video_id_added_count++;
                        }

                        $this->log_import_activity( "PROCESSING: Adding YouTube thumbnail to '{$post_title}' (ID: {$post->ID}, video ID: {$video_id})" );

                        // Try to add thumbnail
                        $thumbnail_result = $this->set_featured_image_from_youtube( $post->ID, $video_url, $post_title );
                        if ( $thumbnail_result ) {
                            // Remove 'needs-image' tag if thumbnail was successfully added
                            $existing_tags = wp_get_post_terms( $post->ID, 'post_tag', array( 'fields' => 'slugs' ) );
                            if ( in_array( 'needs-image', $existing_tags ) ) {
                                $existing_tags = array_diff( $existing_tags, array( 'needs-image' ) );
                                wp_set_post_terms( $post->ID, $existing_tags, 'post_tag' );
                                $this->log_import_activity( "SUCCESS: Removed 'needs-image' tag from '{$post_title}' (ID: {$post->ID}) - thumbnail added" );
                            }
                            $this->log_import_activity( "SUCCESS: Added YouTube thumbnail to '{$post_title}' (ID: {$post->ID})" );
                            $thumbnail_added_count++;
                        } else {
                            $error_msg = "Failed to add YouTube thumbnail to '{$post_title}' (ID: {$post->ID})";
                            $this->log_import_activity( "WARNING: {$error_msg}" );
                            $errors[] = $error_msg;
                        }
                    }
                }

                // If YouTube didn't work or wasn't available, try TikTok
                if ( ! $thumbnail_result && $tiktok_url ) {
                    // Extract TikTok URL from oembed field if needed (get raw value to avoid HTML)
                    $tiktok_url_raw = get_field( 'tiktok_video', $post->ID, false );
                    if ( empty( $tiktok_url_raw ) ) {
                        $tiktok_url_raw = $tiktok_url;
                    }
                    
                    // If the URL is wrapped in HTML, extract it
                    if ( is_string( $tiktok_url_raw ) && strpos( $tiktok_url_raw, '<' ) !== false ) {
                        if ( preg_match( '/href=["\']([^"\']+)["\']/', $tiktok_url_raw, $matches ) ) {
                            $tiktok_url_raw = $matches[1];
                        } elseif ( preg_match( '/https?:\/\/[^\s<>"\']+/', $tiktok_url_raw, $matches ) ) {
                            $tiktok_url_raw = $matches[0];
                        }
                    }
                    
                    $this->log_import_activity( "PROCESSING: Adding TikTok thumbnail to '{$post_title}' (ID: {$post->ID}, TikTok URL: {$tiktok_url_raw})" );
                    
                    // Try to add thumbnail from TikTok
                    $thumbnail_result = $this->set_featured_image_from_tiktok( $post->ID, $tiktok_url_raw, $post_title );
                    if ( $thumbnail_result ) {
                        // Remove 'needs-image' tag if thumbnail was successfully added
                        $existing_tags = wp_get_post_terms( $post->ID, 'post_tag', array( 'fields' => 'slugs' ) );
                        if ( in_array( 'needs-image', $existing_tags ) ) {
                            $existing_tags = array_diff( $existing_tags, array( 'needs-image' ) );
                            wp_set_post_terms( $post->ID, $existing_tags, 'post_tag' );
                            $this->log_import_activity( "SUCCESS: Removed 'needs-image' tag from '{$post_title}' (ID: {$post->ID}) - thumbnail added" );
                        }
                        $this->log_import_activity( "SUCCESS: Added TikTok thumbnail to '{$post_title}' (ID: {$post->ID})" );
                        $thumbnail_added_count++;
                    } else {
                        $error_msg = "Failed to add TikTok thumbnail to '{$post_title}' (ID: {$post->ID})";
                        $this->log_import_activity( "WARNING: {$error_msg}" );
                        $errors[] = $error_msg;
                    }
                }

                // If neither YouTube nor TikTok worked, log it
                if ( ! $video_url && ! $tiktok_url ) {
                    $this->log_import_activity( "SKIP: Post '{$post_title}' (ID: {$post->ID}) has no video URL (YouTube or TikTok)" );
                    $no_video_count++;
                } elseif ( ! $thumbnail_result ) {
                    $skipped_count++;
                }
            }

            $this->log_import_activity( "Bulk thumbnail update completed. Processed: {$processed_count}, Added: {$thumbnail_added_count}, Image tags added: {$image_tag_added_count}, Skipped: {$skipped_count}" );
            wp_die( "Bulk thumbnail update completed! Processed: {$processed_count}, Added: {$thumbnail_added_count}, Image tags added: {$image_tag_added_count}, Skipped: {$skipped_count}" );
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
        
        // Add inline styles
        wp_add_inline_style( 'wp-admin', $this->get_logs_styles() );
    }

    /**
     * Add custom buttons and status display to the options page
     */
    public function add_options_page_buttons() {
        $screen = get_current_screen();
        if ( ! $screen || $screen->id !== 'toplevel_page_youtube-import-settings' ) {
            return;
        }
        
        $nonce = wp_create_nonce( 'youtube_logs_nonce' );
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var nonce = '<?php echo esc_js( $nonce ); ?>';
            
            var controlsHtml = '<div class="postbox youtube-importer-controls">' +
                '<div class="postbox-header"><h2 class="hndle">Status & Tools</h2></div>' +
                '<div class="inside">' +
                    '<h3>Cron Status</h3>' +
                    '<div id="cron-status">Loading...</div>' +
                    
                    '<h3>Quick Actions</h3>' +
                    '<p>' +
                        '<button type="button" id="check-now" class="button button-primary">Check Now</button> ' +
                        '<span class="description">Manually trigger an import check for new videos</span>' +
                    '</p>' +
                    '<p>' +
                        '<button type="button" id="bulk-update-thumbnails" class="button">Bulk Update Thumbnails</button> ' +
                        '<span class="description">Check all songs for thumbnails and add them if they are missing</span>' +
                    '</p>' +
                    
                    '<h3>Import Logs</h3>' +
                    '<p>' +
                        '<button type="button" id="refresh-logs" class="button">Refresh Logs</button> ' +
                        '<button type="button" id="clear-logs" class="button">Clear Logs</button>' +
                    '</p>' +
                    '<div id="youtube-logs-content">Loading logs...</div>' +
                '</div>' +
            '</div>';
            
            // Insert after the ACF postbox in the main content area
            var $acfPostbox = $('#poststuff .postbox').last();
            if ($acfPostbox.length) {
                $acfPostbox.after(controlsHtml);
            } else {
                // Fallback: append to poststuff
                $('#poststuff').append(controlsHtml);
            }
            
            // Now bind event handlers (elements exist now)
            $('#check-now').on('click', function() {
                var $btn = $(this);
                $btn.prop('disabled', true).text('Checking...');
                $('#youtube-logs-content').html('Running import check...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: { action: 'youtube_check_now', nonce: nonce },
                    success: function(response) {
                        loadLogs();
                    },
                    error: function() {
                        loadLogs();
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text('Check Now');
                    }
                });
            });
            
            $('#bulk-update-thumbnails').on('click', function() {
                var $btn = $(this);
                $btn.prop('disabled', true).text('Processing...');
                $('#youtube-logs-content').html('Starting bulk thumbnail update... This may take a while.');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: { action: 'youtube_bulk_update_thumbnails', nonce: nonce },
                    success: function(response) {
                        loadLogs();
                    },
                    error: function() {
                        loadLogs();
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text('Bulk Update Thumbnails');
                    }
                });
            });
            
            $('#refresh-logs').on('click', function() {
                loadLogs();
                loadCronStatus();
            });
            
            $('#clear-logs').on('click', function() {
                $('#youtube-logs-content').html('Clearing logs...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: { action: 'youtube_clear_logs', nonce: nonce },
                    success: function(response) {
                        if (response.success) {
                            $('#youtube-logs-content').html('<p style="color: #68de7c;">Logs cleared successfully!</p>');
                        } else {
                            $('#youtube-logs-content').html('<p style="color: #f86368;">Error clearing logs</p>');
                        }
                    },
                    error: function() {
                        $('#youtube-logs-content').html('<p style="color: #f86368;">Error clearing logs</p>');
                    }
                });
            });
            
            function loadLogs() {
                $('#youtube-logs-content').html('Loading logs...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: { action: 'youtube_get_logs', nonce: nonce },
                    success: function(response) {
                        if (response.success) {
                            $('#youtube-logs-content').html(response.data);
                        } else {
                            $('#youtube-logs-content').html('<p style="color: #f86368;">Error loading logs</p>');
                        }
                    },
                    error: function() {
                        $('#youtube-logs-content').html('<p style="color: #f86368;">Error loading logs</p>');
                    }
                });
            }
            
            function loadCronStatus() {
                $('#cron-status').html('Loading...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: { action: 'youtube_get_cron_status', nonce: nonce },
                    success: function(response) {
                        if (response.success) {
                            var data = response.data;
                            var html = '<table class="cron-status-table">';
                            html += '<tr><td>Import Enabled:</td><td>' + (data.enabled ? '<span style="color:green">Yes</span>' : '<span style="color:red">No</span>') + '</td></tr>';
                            html += '<tr><td>Feed URL:</td><td>' + data.feed_url + '</td></tr>';
                            html += '<tr><td>Check Interval:</td><td>' + data.interval + '</td></tr>';
                            html += '<tr><td>Current Schedule:</td><td>' + data.current_schedule + '</td></tr>';
                            html += '<tr><td>Next Run:</td><td>' + data.next_run + (data.next_run_relative !== 'N/A' ? ' (' + data.next_run_relative + ')' : '') + '</td></tr>';
                            html += '<tr><td>Cron Health:</td><td>' + (data.cron_healthy ? '<span style="color:green">✓ Healthy</span>' : '<span style="color:orange">⚠ Check settings</span>') + '</td></tr>';
                            html += '</table>';
                            $('#cron-status').html(html);
                        } else {
                            $('#cron-status').html('<p style="color: red;">Error loading status</p>');
                        }
                    },
                    error: function() {
                        $('#cron-status').html('<p style="color: red;">Error loading status</p>');
                    }
                });
            }
            
            // Load initial data
            loadLogs();
            loadCronStatus();
        });
        </script>
        <?php
    }

    /**
     * Get the CSS styles for logs display
     */
    private function get_logs_styles() {
        return "
        .youtube-importer-controls.postbox {
            margin-top: 20px;
        }
        .youtube-importer-controls .inside {
            padding: 12px;
        }
        .youtube-importer-controls .inside h3 {
            margin: 20px 0 10px 0;
            padding-bottom: 8px;
            border-bottom: 1px solid #ddd;
            font-size: 13px;
            font-weight: 600;
        }
        .youtube-importer-controls .inside h3:first-child {
            margin-top: 0;
        }
        .youtube-importer-controls .inside p {
            margin: 10px 0;
        }
        .youtube-importer-controls .inside .description {
            color: #646970;
            font-style: italic;
            margin-left: 8px;
        }
        .cron-status-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .cron-status-table td {
            padding: 6px 10px;
            border-bottom: 1px solid #eee;
            word-break: break-word;
            font-size: 13px;
        }
        .cron-status-table td:first-child {
            width: 150px;
            color: #50575e;
            font-weight: 500;
        }
        #cron-status {
            background: #f6f7f7;
            border: 1px solid #dcdcde;
            border-radius: 4px;
            padding: 12px;
        }
        #youtube-logs-content {
            background: #1d2327;
            color: #f0f0f1;
            border: 1px solid #2c3338;
            border-radius: 4px;
            padding: 12px;
            max-height: 250px;
            overflow-y: auto;
            font-family: Consolas, Monaco, monospace;
            font-size: 11px;
            line-height: 1.5;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        #youtube-logs-content .log-entry {
            margin-bottom: 3px;
            padding: 1px 0;
        }
        #youtube-logs-content .log-entry.error {
            color: #f86368;
        }
        #youtube-logs-content .log-entry.success {
            color: #68de7c;
        }
        #youtube-logs-content .log-entry.info {
            color: #72aee6;
        }
        #youtube-logs-content .log-timestamp {
            color: #a7aaad;
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
        $notes_tag_added_count = 0;
        $image_tag_added_count = 0;
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

            // Check for lyric_annotations and add 'needs-notes' tag if missing
            $lyric_annotations = get_field( 'lyric_annotations', $post->ID );
            if ( empty( $lyric_annotations ) || trim( wp_strip_all_tags( $lyric_annotations ) ) === '' ) {
                // Get existing tags
                $existing_tags = wp_get_post_terms( $post->ID, 'post_tag', array( 'fields' => 'slugs' ) );
                
                // Add 'needs-notes' tag if not already present
                if ( ! in_array( 'needs-notes', $existing_tags ) ) {
                    if ( empty( $existing_tags ) ) {
                        $existing_tags = array();
                    }
                    $existing_tags[] = 'needs-notes';
                    wp_set_post_terms( $post->ID, $existing_tags, 'post_tag' );
                    $this->log_import_activity( "SUCCESS: Added 'needs-notes' tag to '{$post_title}' (ID: {$post->ID})" );
                    $notes_tag_added_count++;
                }
            } else {
                // Remove 'needs-notes' tag if lyric_annotations exist
                $existing_tags = wp_get_post_terms( $post->ID, 'post_tag', array( 'fields' => 'slugs' ) );
                if ( in_array( 'needs-notes', $existing_tags ) ) {
                    $existing_tags = array_diff( $existing_tags, array( 'needs-notes' ) );
                    wp_set_post_terms( $post->ID, $existing_tags, 'post_tag' );
                    $this->log_import_activity( "SUCCESS: Removed 'needs-notes' tag from '{$post_title}' (ID: {$post->ID}) - annotations found" );
                }
            }

            // Check for featured image and add 'needs-image' tag if missing
            if ( ! has_post_thumbnail( $post->ID ) ) {
                // Get existing tags
                $existing_tags = wp_get_post_terms( $post->ID, 'post_tag', array( 'fields' => 'slugs' ) );
                
                // Add 'needs-image' tag if not already present
                if ( ! in_array( 'needs-image', $existing_tags ) ) {
                    if ( empty( $existing_tags ) ) {
                        $existing_tags = array();
                    }
                    $existing_tags[] = 'needs-image';
                    wp_set_post_terms( $post->ID, $existing_tags, 'post_tag' );
                    $this->log_import_activity( "SUCCESS: Added 'needs-image' tag to '{$post_title}' (ID: {$post->ID})" );
                    $image_tag_added_count++;
                } else {
                    $this->log_import_activity( "SKIP: Post '{$post_title}' (ID: {$post->ID}) already has 'needs-image' tag" );
                }
            } else {
                // Remove 'needs-image' tag if featured image exists
                $existing_tags = wp_get_post_terms( $post->ID, 'post_tag', array( 'fields' => 'slugs' ) );
                if ( in_array( 'needs-image', $existing_tags ) ) {
                    $existing_tags = array_diff( $existing_tags, array( 'needs-image' ) );
                    wp_set_post_terms( $post->ID, $existing_tags, 'post_tag' );
                    $this->log_import_activity( "SUCCESS: Removed 'needs-image' tag from '{$post_title}' (ID: {$post->ID}) - featured image exists" );
                }
                $this->log_import_activity( "SKIP: Post '{$post_title}' (ID: {$post->ID}) already has featured image" );
                $skipped_count++;
                continue;
            }

            $thumbnail_result = false;
            $video_url = $this->get_youtube_video_url( $post->ID );
            $tiktok_url = get_field( 'tiktok_video', $post->ID );

            // Try YouTube first if available
            if ( $video_url ) {
                // Extract video ID and add it if missing
                $video_id = $this->extract_video_id_from_url( $video_url );
                if ( ! $video_id ) {
                    $this->log_import_activity( "SKIP: Post '{$post_title}' (ID: {$post->ID}) - could not extract video ID from URL: {$video_url}" );
                    // Continue to try TikTok if available
                } else {
                    // Add video_id meta if missing
                    $existing_video_id = get_post_meta( $post->ID, 'video_id', true );
                    if ( ! $existing_video_id ) {
                        update_post_meta( $post->ID, 'video_id', $video_id );
                        $this->log_import_activity( "SUCCESS: Added missing video_id meta for '{$post_title}' (ID: {$post->ID}, video ID: {$video_id})" );
                        $video_id_added_count++;
                    }

                    $this->log_import_activity( "PROCESSING: Adding YouTube thumbnail to '{$post_title}' (ID: {$post->ID}, video ID: {$video_id})" );

                    // Try to add thumbnail
                    $thumbnail_result = $this->set_featured_image_from_youtube( $post->ID, $video_url, $post_title );
                    if ( $thumbnail_result ) {
                        // Remove 'needs-image' tag if thumbnail was successfully added
                        $existing_tags = wp_get_post_terms( $post->ID, 'post_tag', array( 'fields' => 'slugs' ) );
                        if ( in_array( 'needs-image', $existing_tags ) ) {
                            $existing_tags = array_diff( $existing_tags, array( 'needs-image' ) );
                            wp_set_post_terms( $post->ID, $existing_tags, 'post_tag' );
                            $this->log_import_activity( "SUCCESS: Removed 'needs-image' tag from '{$post_title}' (ID: {$post->ID}) - thumbnail added" );
                        }
                        $this->log_import_activity( "SUCCESS: Added YouTube thumbnail to '{$post_title}' (ID: {$post->ID})" );
                        $thumbnail_added_count++;
                    } else {
                        $error_msg = "Failed to add YouTube thumbnail to '{$post_title}' (ID: {$post->ID})";
                        $this->log_import_activity( "WARNING: {$error_msg}" );
                        $errors[] = $error_msg;
                    }
                }
            }

            // If YouTube didn't work or wasn't available, try TikTok
            if ( ! $thumbnail_result && $tiktok_url ) {
                // Extract TikTok URL from oembed field if needed (get raw value to avoid HTML)
                $tiktok_url_raw = get_field( 'tiktok_video', $post->ID, false );
                if ( empty( $tiktok_url_raw ) ) {
                    $tiktok_url_raw = $tiktok_url;
                }
                
                // If the URL is wrapped in HTML, extract it
                if ( is_string( $tiktok_url_raw ) && strpos( $tiktok_url_raw, '<' ) !== false ) {
                    if ( preg_match( '/href=["\']([^"\']+)["\']/', $tiktok_url_raw, $matches ) ) {
                        $tiktok_url_raw = $matches[1];
                    } elseif ( preg_match( '/https?:\/\/[^\s<>"\']+/', $tiktok_url_raw, $matches ) ) {
                        $tiktok_url_raw = $matches[0];
                    }
                }
                
                $this->log_import_activity( "PROCESSING: Adding TikTok thumbnail to '{$post_title}' (ID: {$post->ID}, TikTok URL: {$tiktok_url_raw})" );
                
                // Try to add thumbnail from TikTok
                $thumbnail_result = $this->set_featured_image_from_tiktok( $post->ID, $tiktok_url_raw, $post_title );
                if ( $thumbnail_result ) {
                    // Remove 'needs-image' tag if thumbnail was successfully added
                    $existing_tags = wp_get_post_terms( $post->ID, 'post_tag', array( 'fields' => 'slugs' ) );
                    if ( in_array( 'needs-image', $existing_tags ) ) {
                        $existing_tags = array_diff( $existing_tags, array( 'needs-image' ) );
                        wp_set_post_terms( $post->ID, $existing_tags, 'post_tag' );
                        $this->log_import_activity( "SUCCESS: Removed 'needs-image' tag from '{$post_title}' (ID: {$post->ID}) - thumbnail added" );
                    }
                    $this->log_import_activity( "SUCCESS: Added TikTok thumbnail to '{$post_title}' (ID: {$post->ID})" );
                    $thumbnail_added_count++;
                } else {
                    $error_msg = "Failed to add TikTok thumbnail to '{$post_title}' (ID: {$post->ID})";
                    $this->log_import_activity( "WARNING: {$error_msg}" );
                    $errors[] = $error_msg;
                }
            }

            // If neither YouTube nor TikTok worked, log it
            if ( ! $video_url && ! $tiktok_url ) {
                $this->log_import_activity( "SKIP: Post '{$post_title}' (ID: {$post->ID}) has no video URL (YouTube or TikTok)" );
                $no_video_count++;
            } elseif ( ! $thumbnail_result ) {
                $skipped_count++;
            }
        }

        $total_songs = count( $posts );
        $this->log_import_activity( "Comprehensive bulk update completed. Total songs: {$total_songs}, Processed: {$processed_count}, Thumbnails added: {$thumbnail_added_count}, Video IDs added: {$video_id_added_count}, Lyrics tags added: {$lyrics_tag_added_count}, Notes tags added: {$notes_tag_added_count}, Image tags added: {$image_tag_added_count}, Skipped: {$skipped_count}, No video: {$no_video_count}" );
        
        $response_data = [
            'total_songs' => $total_songs,
            'processed' => $processed_count,
            'thumbnails_added' => $thumbnail_added_count,
            'video_ids_added' => $video_id_added_count,
            'lyrics_tags_added' => $lyrics_tag_added_count,
            'notes_tags_added' => $notes_tag_added_count,
            'image_tags_added' => $image_tag_added_count,
            'skipped' => $skipped_count,
            'no_video' => $no_video_count,
            'errors' => $errors
        ];

        wp_send_json_success( $response_data );
    }

    /**
     * AJAX handler to trigger import check now
     */
    public function ajax_check_now() {
        // Check nonce
        if ( ! wp_verify_nonce( $_POST['nonce'], 'youtube_logs_nonce' ) ) {
            wp_send_json_error( 'Security check failed' );
        }

        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions' );
        }

        $this->log_import_activity( "Manual import triggered via 'Check Now' button" );

        // Run the import directly (bypass the enabled check for manual trigger)
        $this->run_manual_import();

        wp_send_json_success( 'Import check completed. Check the logs for details.' );
    }

    /**
     * Run manual import (bypasses the enabled check)
     * Checks the feed for new videos and creates song posts for them
     * Uses fresh feed data (bypasses cache) for manual checks
     */
    private function run_manual_import() {
        include_once ABSPATH . WPINC . '/feed.php';
        
        $feed_url = $this->get_option_with_fallback( 'youtube_feed_url' );
        if ( empty( $feed_url ) ) {
            $this->log_import_activity( '✗ No feed URL configured' );
            return;
        }
        
        if ( strpos( $feed_url, 'feeds/videos.xml' ) === false ) {
            $feed_url = $this->resolve_channel_feed( $feed_url );
        }

        $this->log_import_activity( '▶ Manual check started (bypassing cache)...' );
        $this->log_import_activity( '  Feed URL: ' . $feed_url );

        if ( ! function_exists( 'fetch_feed' ) ) {
            $this->log_import_activity( '✗ Feed error: SimplePie not available' );
            return;
        }

        // Bypass cache for manual checks - set cache lifetime to 0
        add_filter( 'wp_feed_cache_transient_lifetime', [ $this, 'disable_feed_cache' ] );
        
        $feed = fetch_feed( $feed_url );
        
        // Remove the filter after fetching
        remove_filter( 'wp_feed_cache_transient_lifetime', [ $this, 'disable_feed_cache' ] );
        
        if ( is_wp_error( $feed ) ) {
            $this->log_import_activity( '✗ Feed error: ' . $feed->get_error_message() );
            return;
        }

        $item_count = $feed->get_item_quantity();
        if ( $item_count === 0 ) {
            $this->log_import_activity( '⊘ Feed empty - no videos found' );
            return;
        }

        $this->log_import_activity( "  Found {$item_count} videos in feed" );

        $items_processed = 0;
        $max_items       = 15; // Check more items to ensure we don't miss any
        $imported_count  = 0;
        $skipped_count   = 0;

        foreach ( $feed->get_items() as $item ) {
            if ( $items_processed >= $max_items ) {
                break;
            }

            $video_url   = esc_url( $item->get_link() );
            $video_title = sanitize_text_field( $item->get_title() );
            $video_id    = $this->extract_video_id_from_url( $video_url );

            // Skip if video already has a song post
            if ( $this->is_video_already_imported( $video_url ) ) {
                $skipped_count++;
                $items_processed++;
                continue;
            }

            // Get description after we know we're importing
            $video_desc = $this->get_video_description( $item );

            // Create the new song post
            $post_id = wp_insert_post( [
                'post_type'   => 'song',
                'post_status' => 'publish',
                'post_title'  => $video_title,
            ] );

            if ( ! $post_id || is_wp_error( $post_id ) ) {
                $this->log_import_activity( '✗ ERROR: "' . $video_title . '" - failed to create post' );
                $items_processed++;
                continue;
            }

            // Clean YouTube URL (remove ?feature=oembed parameter if present)
            $video_url = $this->clean_youtube_url( $video_url );
            
            // Save video URL to new embeds repeater field
            $embeds = array(
                array(
                    'embed_type' => 'youtube',
                    'youtube_video' => $video_url,
                ),
            );
            update_field( 'embeds', $embeds, $post_id );
            
            // Save description
            update_field( 'lyrics', $video_desc, $post_id );
            if ( $video_id ) {
                update_post_meta( $post_id, 'video_id', $video_id );
            }

            // Set thumbnail from YouTube
            $this->set_featured_image_from_youtube( $post_id, $video_url, $video_title );
            update_post_meta( $post_id, 'imported', '1' );

            // Add 'needs-notes' tag since new imports won't have lyric_annotations
            wp_set_post_terms( $post_id, array( 'needs-notes' ), 'post_tag', true );

            $imported_count++;
            $this->log_import_activity( '✓ SUCCESS: New Song Imported:"' . $video_title . '" (ID: ' . $video_id . ')' );
            
            $this->send_import_notification_email( $post_id, $video_title, $video_url, $video_id );
            
            $items_processed++;
        }

        $this->log_import_activity( "▣ Done: {$imported_count} new, {$skipped_count} existing, {$item_count} in feed" );
    }

    /**
     * Disable feed cache for manual import checks
     * Returns 0 to bypass SimplePie's cache
     */
    public function disable_feed_cache( $lifetime ) {
        return 0;
    }

    /**
     * AJAX handler to get cron status
     */
    public function ajax_get_cron_status() {
        // Check nonce
        if ( ! wp_verify_nonce( $_POST['nonce'], 'youtube_logs_nonce' ) ) {
            wp_send_json_error( 'Security check failed' );
        }

        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions' );
        }

        $enabled = $this->get_option_with_fallback( 'youtube_import_enabled' );
        $interval = $this->get_option_with_fallback( 'youtube_import_interval' ) ?: 'hourly';
        $feed_url = $this->get_option_with_fallback( 'youtube_feed_url' );
        $next_scheduled = wp_next_scheduled( 'import_youtube_songs_event' );
        $current_schedule = wp_get_schedule( 'import_youtube_songs_event' );

        $status = [
            'enabled' => (bool) $enabled,
            'interval' => $interval,
            'feed_url' => $feed_url ?: 'Not configured',
            'next_run' => $next_scheduled ? date( 'Y-m-d H:i:s', $next_scheduled ) : 'Not scheduled',
            'next_run_relative' => $next_scheduled ? human_time_diff( time(), $next_scheduled ) : 'N/A',
            'current_schedule' => $current_schedule ?: 'None',
            'cron_healthy' => (bool) ( $next_scheduled && $enabled ),
        ];

        wp_send_json_success( $status );
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