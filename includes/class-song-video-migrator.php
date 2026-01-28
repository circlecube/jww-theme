<?php
/**
 * Class Song_Video_Migrator
 *
 * Migrates existing video fields (video, tiktok_video, instagram_video, music_video)
 * to the new repeater field format (embeds).
 *
 * @package JWW_Theme
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Song_Video_Migrator {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'wp_ajax_jww_migrate_videos', array( $this, 'ajax_migrate_videos' ) );
		add_action( 'wp_ajax_jww_migrate_single_song', array( $this, 'ajax_migrate_single_song' ) );
		add_action( 'wp_ajax_jww_get_next_unmigrated_song', array( $this, 'ajax_get_next_unmigrated_song' ) );
		add_action( 'wp_ajax_jww_get_migration_status', array( $this, 'ajax_get_migration_status' ) );
	}

	/**
	 * Add admin page
	 * Only show if migration hasn't been completed via upgrade routine
	 */
	public function add_admin_page() {
		// Check if upgrade 2.3.0 has already run (migration should be complete)
		$stored_version = get_option( 'jww_theme_version', '1.1.9' );
		
		// Hide admin page if upgrade 2.3.0 has already run
		// Migration will be handled automatically by the upgrade routine
		if ( version_compare( $stored_version, '2.3.0', '>=' ) ) {
			// Upgrade has run, hide the page
			// Functionality is still available for manual use if needed via direct URL
			return;
		}
		
		add_submenu_page(
			'edit.php?post_type=song',
			'Migrate Videos',
			'Migrate Videos',
			'manage_options',
			'song-video-migration',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin scripts
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( $hook !== 'song_page_song-video-migration' ) {
			return;
		}

		wp_enqueue_script(
			'jww-video-migration',
			get_stylesheet_directory_uri() . '/includes/js/video-migration.js',
			array( 'jquery' ),
			wp_get_theme()->get( 'Version' ),
			true
		);

		wp_localize_script( 'jww-video-migration', 'jwwVideoMigration', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'jww_video_migration' ),
		) );
	}

	/**
	 * Render admin page
	 */
	public function render_admin_page() {
		// Load status asynchronously to improve page load time
		$status = array(
			'total_songs'           => 'Loading...',
			'songs_with_old_fields' => 'Loading...',
			'already_migrated'      => 'Loading...',
			'needs_migration'       => 'Loading...',
		);
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p class="description">
				This tool migrates existing video fields to the new repeater format.
				Old fields (video, tiktok_video, instagram_video, music_video) will be converted to the new "embeds" repeater field.
			</p>

			<div class="migration-status">
				<h2>Migration Status</h2>
				<div id="status-loading" style="padding: 10px;">
					<p>Loading migration status...</p>
				</div>
				<table class="widefat" id="status-table" style="display: none;">
					<tbody>
						<tr>
							<td><strong>Total Songs:</strong></td>
							<td id="status-total"><?php echo esc_html( $status['total_songs'] ); ?></td>
						</tr>
						<tr>
							<td><strong>Songs with Old Video Fields:</strong></td>
							<td id="status-old-fields"><?php echo esc_html( $status['songs_with_old_fields'] ); ?></td>
						</tr>
						<tr>
							<td><strong>Already Migrated:</strong></td>
							<td id="status-migrated"><?php echo esc_html( $status['already_migrated'] ); ?></td>
						</tr>
						<tr>
							<td><strong>Needs Migration:</strong></td>
							<td id="status-needs"><strong><?php echo esc_html( $status['needs_migration'] ); ?></strong></td>
						</tr>
					</tbody>
				</table>
			</div>

			<div class="migration-actions" style="margin-top: 20px;" id="migration-actions-container">
				<div id="actions-loading">
					<p>Loading actions...</p>
				</div>
				<div id="actions-content" style="display: none;">
					<div id="actions-needs-migration" style="display: none;">
						<h3>Test Migration (Single Song)</h3>
						<p class="description">Test the migration on the next unmigrated song. This will migrate the most recently published song that hasn't been migrated yet.</p>
						<div style="margin-bottom: 20px;">
							<button type="button" class="button button-primary" id="migrate-next-song">
								Migrate Next Song
							</button>
							<div id="next-song-info" style="margin-top: 10px; color: #666; font-style: italic;"></div>
						</div>

						<hr style="margin: 30px 0;">

						<h3>Bulk Migration</h3>
						<button type="button" class="button button-primary" id="start-migration">
							Start Migration (All Songs)
						</button>
						<button type="button" class="button" id="preview-migration">
							Preview Migration (First 5 Songs)
						</button>
					</div>
					<div id="actions-all-migrated" style="display: none;">
						<div class="notice notice-success">
							<p>All songs have been migrated! No action needed.</p>
						</div>
					</div>
				</div>
			</div>

			<div id="migration-progress" style="display: none; margin-top: 20px;">
				<h3>Migration Progress</h3>
				<div class="progress-bar-container" style="background: #f0f0f0; border: 1px solid #ccc; padding: 10px;">
					<div id="progress-bar" style="background: #2271b1; height: 30px; width: 0%; transition: width 0.3s;">
						<span id="progress-text" style="display: block; line-height: 30px; color: white; text-align: center; font-weight: bold;">0%</span>
					</div>
				</div>
				<div id="migration-log" style="margin-top: 15px; max-height: 400px; overflow-y: auto; background: #f9f9f9; padding: 10px; border: 1px solid #ddd;">
					<p>Migration log will appear here...</p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Get migration status
	 *
	 * @param bool $use_cache Whether to use cached results
	 * @return array Status information
	 */
	public function get_migration_status( $use_cache = true ) {
		// Try to get from cache first
		if ( $use_cache ) {
			$cached = get_transient( 'jww_video_migration_status' );
			if ( $cached !== false ) {
				return $cached;
			}
		}

		// Get total count (fast query)
		$total_songs = wp_count_posts( 'song' );
		$total_songs = isset( $total_songs->publish ) ? (int) $total_songs->publish : 0;

		// Count songs with old fields using direct SQL (much faster than looping)
		// ACF stores fields in postmeta - check for field names directly
		global $wpdb;
		
		// Query for songs with old video fields
		// ACF stores as both the field name and field_ references, but we'll check the actual field names
		$old_fields_sql = "
			SELECT COUNT(DISTINCT pm.post_id) 
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
			WHERE p.post_type = 'song' 
			AND p.post_status = 'publish'
			AND (
				pm.meta_key = 'video' 
				OR pm.meta_key = 'tiktok_video'
				OR pm.meta_key = 'instagram_video'
				OR pm.meta_key = 'music_video'
			)
			AND pm.meta_value != ''
			AND pm.meta_value IS NOT NULL
		";
		
		$songs_with_old_fields = (int) $wpdb->get_var( $old_fields_sql );

		// Count songs with new repeater field
		$new_field_sql = "
			SELECT COUNT(DISTINCT pm.post_id) 
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
			WHERE p.post_type = 'song' 
			AND p.post_status = 'publish'
			AND pm.meta_key = 'embeds'
			AND pm.meta_value != ''
			AND pm.meta_value IS NOT NULL
		";
		
		$already_migrated = (int) $wpdb->get_var( $new_field_sql );

		// Count songs that need migration (have old fields but NOT new field)
		// Use LEFT JOIN for better performance
		$needs_migration_sql = "
			SELECT COUNT(DISTINCT pm1.post_id)
			FROM {$wpdb->postmeta} pm1
			INNER JOIN {$wpdb->posts} p ON pm1.post_id = p.ID
			LEFT JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id 
				AND pm2.meta_key = 'song_videos' 
				AND pm2.meta_value != '' 
				AND pm2.meta_value IS NOT NULL
			WHERE p.post_type = 'song' 
			AND p.post_status = 'publish'
			AND (
				pm1.meta_key = 'video' 
				OR pm1.meta_key = 'tiktok_video'
				OR pm1.meta_key = 'instagram_video'
				OR pm1.meta_key = 'music_video'
			)
			AND pm1.meta_value != ''
			AND pm1.meta_value IS NOT NULL
			AND pm2.post_id IS NULL
		";
		
		$needs_migration = (int) $wpdb->get_var( $needs_migration_sql );

		$status = array(
			'total_songs'           => $total_songs,
			'songs_with_old_fields' => $songs_with_old_fields,
			'already_migrated'      => $already_migrated,
			'needs_migration'       => $needs_migration,
		);

		// Cache for 5 minutes
		if ( $use_cache ) {
			set_transient( 'jww_video_migration_status', $status, 5 * MINUTE_IN_SECONDS );
		}

		return $status;
	}

	/**
	 * Clear migration status cache
	 */
	public function clear_status_cache() {
		delete_transient( 'jww_video_migration_status' );
	}

	/**
	 * AJAX: Get migration status
	 */
	public function ajax_get_migration_status() {
		check_ajax_referer( 'jww_video_migration', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$status = $this->get_migration_status();
		wp_send_json_success( array( 'status' => $status ) );
	}

	/**
	 * AJAX: Migrate videos
	 */
	public function ajax_migrate_videos() {
		check_ajax_referer( 'jww_video_migration', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$preview = isset( $_POST['preview'] ) && $_POST['preview'] === 'true';
		$limit = $preview ? 5 : -1;

		$result = $this->migrate_videos( $limit, $preview );
		
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message' => $preview ? 'Preview completed' : 'Migration completed',
			'result'  => $result,
		) );
	}

	/**
	 * AJAX: Migrate single song
	 */
	public function ajax_migrate_single_song() {
		check_ajax_referer( 'jww_video_migration', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		// Get the next unmigrated song
		$song = $this->get_next_unmigrated_song();
		
		if ( ! $song ) {
			wp_send_json_error( array( 'message' => 'No unmigrated songs found' ) );
		}

		$result = $this->migrate_single_song( $song->ID );
		
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message' => 'Song migrated successfully',
			'result'  => $result,
		) );
	}

	/**
	 * AJAX: Get next unmigrated song
	 */
	public function ajax_get_next_unmigrated_song() {
		check_ajax_referer( 'jww_video_migration', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$song = $this->get_next_unmigrated_song();
		
		if ( ! $song ) {
			wp_send_json_error( array( 'message' => 'No unmigrated songs found' ) );
		}

		wp_send_json_success( array(
			'song' => array(
				'id'    => $song->ID,
				'title' => get_the_title( $song->ID ),
				'date'  => get_the_date( 'F j, Y', $song->ID ),
			),
		) );
	}

	/**
	 * Get the next unmigrated song (most recent)
	 *
	 * @return WP_Post|false Song post object or false if none found
	 */
	private function get_next_unmigrated_song() {
		global $wpdb;

		// Get songs with old fields but NOT new field, ordered by date (newest first)
		$sql = "
			SELECT DISTINCT p.ID
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id
			LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id 
				AND pm2.meta_key = 'embeds' 
				AND pm2.meta_value != '' 
				AND pm2.meta_value IS NOT NULL
			WHERE p.post_type = 'song' 
			AND p.post_status = 'publish'
			AND (
				pm1.meta_key = 'video' 
				OR pm1.meta_key = 'tiktok_video'
				OR pm1.meta_key = 'instagram_video'
				OR pm1.meta_key = 'music_video'
			)
			AND pm1.meta_value != ''
			AND pm1.meta_value IS NOT NULL
			AND pm2.post_id IS NULL
			ORDER BY p.post_date DESC
			LIMIT 1
		";

		$song_id = $wpdb->get_var( $sql );

		if ( ! $song_id ) {
			return false;
		}

		return get_post( $song_id );
	}

	/**
	 * Migrate videos for all songs
	 *
	 * @param int $limit Maximum number of songs to process (-1 for all)
	 * @param bool $preview Whether this is a preview (don't actually migrate)
	 * @return array|WP_Error Result with counts
	 */
	public function migrate_videos( $limit = -1, $preview = false ) {
		$all_songs = get_posts( array(
			'post_type'      => 'song',
			'posts_per_page' => $limit > 0 ? $limit : -1,
			'post_status'    => 'publish',
			'fields'         => 'ids',
		) );

		$migrated = 0;
		$skipped = 0;
		$errors = 0;
		$log = array();

		foreach ( $all_songs as $song_id ) {
			$song_title = get_the_title( $song_id );

			// Check if already migrated
			$existing_videos = get_field( 'embeds', $song_id );
			if ( $existing_videos && is_array( $existing_videos ) && ! empty( $existing_videos ) ) {
				$skipped++;
				$log[] = "SKIP: '{$song_title}' (ID: {$song_id}) - Already has new format";
				continue;
			}

		// Collect videos from old fields
		$videos = array();

		// YouTube video - oembed fields might return URL or embed code
		$video = get_field( 'video', $song_id );
		if ( $video ) {
			// If it's an array (oembed format), extract the URL
			if ( is_array( $video ) && isset( $video['url'] ) ) {
				$video = $video['url'];
			}
			// If it's embed code, try to extract URL
			if ( ! filter_var( $video, FILTER_VALIDATE_URL ) && preg_match( '/https?:\/\/[^\s<>"\']+/', $video, $matches ) ) {
				$video = $matches[0];
			}
			if ( $video ) {
				// Clean YouTube URL (remove ?feature=oembed parameter)
				$video = $this->clean_youtube_url( $video );
				$videos[] = array(
					'embed_type'  => 'youtube',
					'youtube_video' => $video,
				);
			}
		}

		// TikTok video
		$tiktok = get_field( 'tiktok_video', $song_id );
		if ( $tiktok ) {
			if ( is_array( $tiktok ) && isset( $tiktok['url'] ) ) {
				$tiktok = $tiktok['url'];
			}
			if ( ! filter_var( $tiktok, FILTER_VALIDATE_URL ) && preg_match( '/https?:\/\/[^\s<>"\']+/', $tiktok, $matches ) ) {
				$tiktok = $matches[0];
			}
			if ( $tiktok ) {
			$videos[] = array(
				'embed_type'  => 'tiktok',
				'tiktok_video' => $tiktok,
			);
			}
		}

		// Instagram video
		$instagram = get_field( 'instagram_video', $song_id );
		if ( $instagram ) {
			if ( is_array( $instagram ) && isset( $instagram['url'] ) ) {
				$instagram = $instagram['url'];
			}
			if ( ! filter_var( $instagram, FILTER_VALIDATE_URL ) && preg_match( '/https?:\/\/[^\s<>"\']+/', $instagram, $matches ) ) {
				$instagram = $matches[0];
			}
			if ( $instagram ) {
			$videos[] = array(
				'embed_type'   => 'instagram',
				'instagram_video' => $instagram,
			);
			}
		}

		// Music video (YouTube) - add as separate YouTube entry
		$music_video = get_field( 'music_video', $song_id );
		if ( $music_video ) {
			if ( is_array( $music_video ) && isset( $music_video['url'] ) ) {
				$music_video = $music_video['url'];
			}
			if ( ! filter_var( $music_video, FILTER_VALIDATE_URL ) && preg_match( '/https?:\/\/[^\s<>"\']+/', $music_video, $matches ) ) {
				$music_video = $matches[0];
			}
			if ( $music_video ) {
				// Clean YouTube URL (remove ?feature=oembed parameter)
				$music_video = $this->clean_youtube_url( $music_video );
				$videos[] = array(
					'embed_type'  => 'youtube',
					'youtube_video' => $music_video,
				);
			}
		}

		// Bandcamp - check for song_id and album_id first, then fallback to iframe
		$bandcamp_song_id = get_field( 'bandcamp_song_id', $song_id );
		$bandcamp_album_id = get_field( 'bandcamp_album_id', $song_id );
		$bandcamp_iframe = get_field( 'bandcamp_iframe', $song_id );
		
		if ( $bandcamp_song_id || $bandcamp_iframe ) {
			$videos[] = array(
				'embed_type' => 'bandcamp',
				'bandcamp_song_id' => $bandcamp_song_id ? $bandcamp_song_id : '',
				'bandcamp_album_id' => $bandcamp_album_id ? $bandcamp_album_id : '',
				'bandcamp_iframe' => $bandcamp_iframe ? $bandcamp_iframe : '',
			);
		}

		if ( empty( $videos ) ) {
			$skipped++;
			$log[] = "SKIP: '{$song_title}' (ID: {$song_id}) - No videos found";
			continue;
		}

		if ( ! $preview ) {
			// Try to get field object to get the field key (more reliable)
			$field_object = get_field_object( 'embeds', $song_id );
			$field_key = null;
			
			if ( $field_object && isset( $field_object['key'] ) ) {
				$field_key = $field_object['key'];
			}
			
			// Try saving with field key first (if we have it)
			$result = false;
			if ( $field_key ) {
				$result = update_field( $field_key, $videos, $song_id );
			}
			
			// Fallback to field name if field key didn't work or wasn't found
			if ( ! $result ) {
				$result = update_field( 'embeds', $videos, $song_id );
			}
			
			// If update_field still failed, try using acf_update_value directly (if we have field object)
			if ( ! $result && function_exists( 'acf_update_value' ) && $field_object ) {
				$result = acf_update_value( $videos, $song_id, $field_object );
			}
			
			// Verify the save worked by checking the field value
			$verify = get_field( 'embeds', $song_id );
			$saved_successfully = ! empty( $verify ) && is_array( $verify ) && count( $verify ) > 0;
			
			if ( $result || $saved_successfully ) {
				// Remove old field values after successful migration
				$this->remove_old_video_fields( $song_id );
				
				$migrated++;
				$log[] = "✓ MIGRATED: '{$song_title}' (ID: {$song_id}) - " . count( $videos ) . " video(s)";
			} else {
				$errors++;
				$log[] = "✗ ERROR: '{$song_title}' (ID: {$song_id}) - Failed to save. Field key: {$field_key}, update_field: " . var_export( $result, true ) . ", verify: " . var_export( $saved_successfully, true );
			}
		} else {
			$migrated++;
			$log[] = "PREVIEW: '{$song_title}' (ID: {$song_id}) - Would migrate " . count( $videos ) . " video(s)";
		}
		}

		// Clear status cache after migration
		$this->clear_status_cache();

		return array(
			'migrated' => $migrated,
			'skipped'  => $skipped,
			'errors'   => $errors,
			'log'      => $log,
		);
	}

	/**
	 * Migrate a single song
	 *
	 * @param int $song_id Song post ID
	 * @return array|WP_Error Result with migration details
	 */
	public function migrate_single_song( $song_id ) {
		$song_title = get_the_title( $song_id );

		// Check if already migrated
		$existing_videos = get_field( 'embeds', $song_id );
		if ( $existing_videos && is_array( $existing_videos ) && ! empty( $existing_videos ) ) {
			return array(
				'success' => false,
				'message' => "Song '{$song_title}' (ID: {$song_id}) already has new format",
				'song_id' => $song_id,
				'song_title' => $song_title,
				'videos_count' => count( $existing_videos ),
			);
		}

		// Collect videos from old fields
		$videos = array();

		// YouTube video - oembed fields might return URL or embed code
		$video = get_field( 'video', $song_id );
		if ( $video ) {
			// If it's an array (oembed format), extract the URL
			if ( is_array( $video ) && isset( $video['url'] ) ) {
				$video = $video['url'];
			}
			// If it's embed code, try to extract URL
			if ( ! filter_var( $video, FILTER_VALIDATE_URL ) && preg_match( '/https?:\/\/[^\s<>"\']+/', $video, $matches ) ) {
				$video = $matches[0];
			}
			if ( $video ) {
				// Clean YouTube URL (remove ?feature=oembed parameter)
				$video = $this->clean_youtube_url( $video );
				$videos[] = array(
					'embed_type'  => 'youtube',
					'youtube_video' => $video,
				);
			}
		}

		// TikTok video
		$tiktok = get_field( 'tiktok_video', $song_id );
		if ( $tiktok ) {
			if ( is_array( $tiktok ) && isset( $tiktok['url'] ) ) {
				$tiktok = $tiktok['url'];
			}
			if ( ! filter_var( $tiktok, FILTER_VALIDATE_URL ) && preg_match( '/https?:\/\/[^\s<>"\']+/', $tiktok, $matches ) ) {
				$tiktok = $matches[0];
			}
			if ( $tiktok ) {
			$videos[] = array(
				'embed_type'  => 'tiktok',
				'tiktok_video' => $tiktok,
			);
			}
		}

		// Instagram video
		$instagram = get_field( 'instagram_video', $song_id );
		if ( $instagram ) {
			if ( is_array( $instagram ) && isset( $instagram['url'] ) ) {
				$instagram = $instagram['url'];
			}
			if ( ! filter_var( $instagram, FILTER_VALIDATE_URL ) && preg_match( '/https?:\/\/[^\s<>"\']+/', $instagram, $matches ) ) {
				$instagram = $matches[0];
			}
			if ( $instagram ) {
			$videos[] = array(
				'embed_type'   => 'instagram',
				'instagram_video' => $instagram,
			);
			}
		}

		// Music video (YouTube) - add as separate YouTube entry
		$music_video = get_field( 'music_video', $song_id );
		if ( $music_video ) {
			if ( is_array( $music_video ) && isset( $music_video['url'] ) ) {
				$music_video = $music_video['url'];
			}
			if ( ! filter_var( $music_video, FILTER_VALIDATE_URL ) && preg_match( '/https?:\/\/[^\s<>"\']+/', $music_video, $matches ) ) {
				$music_video = $matches[0];
			}
			if ( $music_video ) {
				// Clean YouTube URL (remove ?feature=oembed parameter)
				$music_video = $this->clean_youtube_url( $music_video );
				$videos[] = array(
					'embed_type'  => 'youtube',
					'youtube_video' => $music_video,
				);
			}
		}

		// Bandcamp - check for song_id and album_id first, then fallback to iframe
		$bandcamp_song_id = get_field( 'bandcamp_song_id', $song_id );
		$bandcamp_album_id = get_field( 'bandcamp_album_id', $song_id );
		$bandcamp_iframe = get_field( 'bandcamp_iframe', $song_id );
		
		if ( $bandcamp_song_id || $bandcamp_iframe ) {
			$videos[] = array(
				'embed_type' => 'bandcamp',
				'bandcamp_song_id' => $bandcamp_song_id ? $bandcamp_song_id : '',
				'bandcamp_album_id' => $bandcamp_album_id ? $bandcamp_album_id : '',
				'bandcamp_iframe' => $bandcamp_iframe ? $bandcamp_iframe : '',
			);
		}

		if ( empty( $videos ) ) {
			return array(
				'success' => false,
				'message' => "Song '{$song_title}' (ID: {$song_id}) has no videos to migrate",
				'song_id' => $song_id,
				'song_title' => $song_title,
				'videos_count' => 0,
			);
		}

		// Try to get field object to get the field key (more reliable)
		$field_object = get_field_object( 'embeds', $song_id );
		$field_key = null;
		
		if ( $field_object && isset( $field_object['key'] ) ) {
			$field_key = $field_object['key'];
		}
		
		// Try saving with field key first (if we have it)
		$result = false;
		if ( $field_key ) {
			$result = update_field( $field_key, $videos, $song_id );
		}
		
		// Fallback to field name if field key didn't work or wasn't found
		if ( ! $result ) {
			$result = update_field( 'embeds', $videos, $song_id );
		}
		
		// If update_field still failed, try using acf_update_value directly (if we have field object)
		if ( ! $result && function_exists( 'acf_update_value' ) && $field_object ) {
			$result = acf_update_value( $videos, $song_id, $field_object );
		}
		
		// Verify the save worked by checking the field value
		$verify = get_field( 'embeds', $song_id );
		$saved_successfully = ! empty( $verify ) && is_array( $verify ) && count( $verify ) > 0;
		
		if ( $result || $saved_successfully ) {
			// Remove old field values after successful migration
			$this->remove_old_video_fields( $song_id );
			
			// Clear status cache after migration
			$this->clear_status_cache();
			
			return array(
				'success' => true,
				'message' => "Song '{$song_title}' (ID: {$song_id}) migrated successfully",
				'song_id' => $song_id,
				'song_title' => $song_title,
				'videos_count' => count( $videos ),
				'videos' => $videos,
				'field_key_used' => $field_key,
				'update_result' => $result,
				'verified' => $saved_successfully,
			);
		} else {
			return new WP_Error( 
				'migration_failed', 
				"Failed to save videos for song '{$song_title}' (ID: {$song_id}). Field key: {$field_key}, update_field result: " . var_export( $result, true ) . ", verified: " . var_export( $saved_successfully, true )
			);
		}
	}

	/**
	 * Remove old video field values after successful migration
	 *
	 * @param int $song_id Song post ID
	 */
	private function remove_old_video_fields( $song_id ) {
		// Delete old video fields
		delete_field( 'video', $song_id );
		delete_field( 'tiktok_video', $song_id );
		delete_field( 'instagram_video', $song_id );
		delete_field( 'music_video', $song_id );
		
		// Delete old bandcamp fields
		delete_field( 'bandcamp_song_id', $song_id );
		delete_field( 'bandcamp_album_id', $song_id );
		delete_field( 'bandcamp_iframe', $song_id );
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
	 * Extract URL from embed code or return as-is if already a URL
	 * (Kept for backward compatibility, but not used in new format)
	 *
	 * @param string $value Video embed code or URL
	 * @return string Extracted URL
	 */
	private function extract_url( $value ) {
		// If it's already a URL, return it
		if ( filter_var( $value, FILTER_VALIDATE_URL ) ) {
			return $value;
		}

		// Try to extract URL from embed code
		if ( preg_match( '/href=["\']([^"\']+)["\']/', $value, $matches ) ) {
			return $matches[1];
		}

		if ( preg_match( '/https?:\/\/[^\s<>"\']+/', $value, $matches ) ) {
			return $matches[0];
		}

		// Return as-is if we can't extract
		return $value;
	}
}

// Initialize
new Song_Video_Migrator();
