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
require_once( get_stylesheet_directory() . '/includes/functions-shortcodes.php' );
require_once( get_stylesheet_directory() . '/includes/functions-theme.php' );
require_once( get_stylesheet_directory() . '/includes/show-functions.php' );
require_once( get_stylesheet_directory() . '/includes/rest-api-show-endpoints.php' );
require_once( get_stylesheet_directory() . '/includes/class-setlist-importer.php' );
require_once( get_stylesheet_directory() . '/includes/class-show-importer.php' );
require_once( get_stylesheet_directory() . '/includes/class-song-duplicate-detector.php' );
require_once( get_stylesheet_directory() . '/includes/class-song-video-migrator.php' );
require_once( get_stylesheet_directory() . '/includes/class-song-export-import.php' );
