<?php
// Composer autoload
require __DIR__ . '/vendor/autoload.php';

// Theme updates
require_once( get_stylesheet_directory() . '/includes/updates.php' );

// YouTube Importer
require_once( get_stylesheet_directory() . '/includes/class-youtube-importer.php' );

// Link Functions (Music streaming and purchase links)
require_once( get_stylesheet_directory() . '/includes/link-functions.php' );

// Template Tags
require_once( get_stylesheet_directory() . '/includes/template-tags.php' );

// Include modular function files
require_once( get_stylesheet_directory() . '/includes/functions-acf.php' );
require_once( get_stylesheet_directory() . '/includes/functions-cpt.php' );
require_once( get_stylesheet_directory() . '/includes/functions-meta.php' );
require_once( get_stylesheet_directory() . '/includes/functions-search.php' );
require_once( get_stylesheet_directory() . '/includes/functions-rest.php' );
require_once( get_stylesheet_directory() . '/includes/functions-admin.php' );
require_once( get_stylesheet_directory() . '/includes/class-venues-admin.php' );
require_once( get_stylesheet_directory() . '/includes/class-location-reorganizer.php' );
require_once( get_stylesheet_directory() . '/includes/functions-shortcodes.php' );
require_once( get_stylesheet_directory() . '/includes/functions-theme.php' );
require_once( get_stylesheet_directory() . '/includes/show-functions.php' );
require_once( get_stylesheet_directory() . '/includes/shows-table-render.php' );
require_once( get_stylesheet_directory() . '/includes/tour-functions.php' );
require_once( get_stylesheet_directory() . '/includes/rest-api-show-endpoints.php' );
require_once( get_stylesheet_directory() . '/includes/class-social-config.php' );
require_once( get_stylesheet_directory() . '/includes/class-threads-oauth.php' );
require_once( get_stylesheet_directory() . '/includes/class-facebook-oauth.php' );
require_once( get_stylesheet_directory() . '/includes/class-instagram-oauth.php' );
require_once( get_stylesheet_directory() . '/includes/social-clients/mastodon.php' );
require_once( get_stylesheet_directory() . '/includes/social-clients/bluesky.php' );
require_once( get_stylesheet_directory() . '/includes/social-clients/pinterest.php' );
require_once( get_stylesheet_directory() . '/includes/social-clients/threads.php' );
require_once( get_stylesheet_directory() . '/includes/social-clients/facebook.php' );
require_once( get_stylesheet_directory() . '/includes/social-clients/instagram.php' );
require_once( get_stylesheet_directory() . '/includes/class-social-publisher.php' );
require_once( get_stylesheet_directory() . '/includes/class-social-admin.php' );
new Social_Admin();
require_once( get_stylesheet_directory() . '/includes/class-social-post-meta-box.php' );
new Social_Post_Meta_Box();
require_once( get_stylesheet_directory() . '/includes/class-setlist-importer.php' );
require_once( get_stylesheet_directory() . '/includes/class-show-importer.php' );
require_once( get_stylesheet_directory() . '/includes/class-song-duplicate-detector.php' );
require_once( get_stylesheet_directory() . '/includes/class-song-video-migrator.php' );
require_once( get_stylesheet_directory() . '/includes/class-song-export-import.php' );
require_once( get_stylesheet_directory() . '/includes/class-song-chords-tabs.php' );
require_once( get_stylesheet_directory() . '/includes/class-song-chord-library-admin.php' );
require_once( get_stylesheet_directory() . '/includes/class-song-import-chords-tabs-admin.php' );

// Flush rewrite rules when theme is activated so /threads-oauth/, /facebook-oauth/, and /instagram-oauth/ work. If you get 404 on those URLs, visit Settings → Permalinks and click Save.
add_action( 'after_switch_theme', 'flush_rewrite_rules' );
