<?php
/**
 * Upgrade routine for version 3.0.0
 * 
 * Migrate song acf fields to new repeater format for embeds.
 * Migrates old video fields (video, tiktok_video, instagram_video, music_video, bandcamp fields)
 * to the new 'embeds' repeater field format.
 * 
 * @since 3.0.0
 */

// Ensure the migration class is available
if ( ! class_exists( 'Song_Video_Migrator' ) ) {
	require_once get_stylesheet_directory() . '/includes/class-song-video-migrator.php';
}

// Create instance to access migration methods
// Note: Constructor will add admin hooks, but admin page will be hidden after upgrade 3.0.0 completes
$migrator = new Song_Video_Migrator();

// Run the migration for all songs
error_log( "Theme Upgrade 3.0.0: Starting video field migration..." );

$result = $migrator->migrate_videos( -1, false ); // Migrate all songs, not preview

if ( is_wp_error( $result ) ) {
	error_log( "Theme Upgrade 3.0.0: Migration failed - " . $result->get_error_message() );
} else {
	$migrated = isset( $result['migrated'] ) ? $result['migrated'] : 0;
	$skipped = isset( $result['skipped'] ) ? $result['skipped'] : 0;
	$errors = isset( $result['errors'] ) ? $result['errors'] : 0;
	
	error_log( "Theme Upgrade 3.0.0: Migration completed - {$migrated} migrated, {$skipped} skipped, {$errors} errors" );
	
	// Log first few entries for debugging
	if ( isset( $result['log'] ) && is_array( $result['log'] ) ) {
		$log_sample = array_slice( $result['log'], 0, 10 );
		foreach ( $log_sample as $log_entry ) {
			error_log( "Theme Upgrade 3.0.0: " . $log_entry );
		}
		if ( count( $result['log'] ) > 10 ) {
			error_log( "Theme Upgrade 3.0.0: ... and " . ( count( $result['log'] ) - 10 ) . " more entries" );
		}
	}
}