<?php
/**
 * Class Song_Duplicate_Detector
 *
 * Detects potential duplicate songs by title similarity.
 * Provides functionality to merge duplicates into a single song with multiple videos.
 *
 * @package JWW_Theme
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Song_Duplicate_Detector {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'wp_ajax_jww_get_duplicates', array( $this, 'ajax_get_duplicates' ) );
		add_action( 'wp_ajax_jww_get_video_details', array( $this, 'ajax_get_video_details' ) );
		add_action( 'wp_ajax_jww_merge_songs', array( $this, 'ajax_merge_songs' ) );
	}

	/**
	 * Add admin page
	 */
	public function add_admin_page() {
		add_submenu_page(
			'edit.php?post_type=song',
			'Duplicate Songs',
			'Duplicate Detection',
			'manage_options',
			'song-duplicates',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin scripts
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( $hook !== 'song_page_song-duplicates' ) {
			return;
		}

		wp_enqueue_script(
			'jww-song-duplicates',
			get_stylesheet_directory_uri() . '/includes/js/song-duplicates.js',
			array( 'jquery' ),
			wp_get_theme()->get( 'Version' ),
			true
		);

		wp_localize_script( 'jww-song-duplicates', 'jwwSongDuplicates', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'jww_song_duplicates' ),
		) );

		wp_enqueue_style(
			'jww-song-duplicates',
			get_stylesheet_directory_uri() . '/includes/css/song-duplicates.css',
			array(),
			wp_get_theme()->get( 'Version' )
		);
	}

	/**
	 * Render admin page
	 */
	public function render_admin_page() {
		$duplicates = $this->find_duplicates();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p class="description">
				This page helps you identify and merge duplicate songs. Songs with similar titles are grouped together.
				You can merge duplicates to combine all videos into a single song post.
			</p>

			<?php if ( empty( $duplicates ) ): ?>
				<div class="notice notice-success">
					<p>No potential duplicates found. All songs appear to be unique.</p>
				</div>
			<?php else: ?>
				<div class="duplicates-container">
					<?php foreach ( $duplicates as $group ): ?>
						<div class="duplicate-group">
							<h2 class="duplicate-group-title">
								Potential Duplicates: "<?php echo esc_html( $group['title'] ); ?>"
								<span class="count">(<?php echo count( $group['songs'] ); ?> songs)</span>
							</h2>
							
							<table class="wp-list-table widefat fixed striped duplicate-comparison-table">
								<thead>
									<tr>
										<th class="column-select">Select</th>
										<th class="column-title">Title</th>
										<th class="column-date">Date</th>
										<th class="column-videos">Videos</th>
										<th class="column-actions">Actions</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $group['songs'] as $song ): ?>
										<tr data-song-id="<?php echo esc_attr( $song['id'] ); ?>">
											<td class="column-select">
												<input type="checkbox" class="song-select" value="<?php echo esc_attr( $song['id'] ); ?>">
											</td>
											<td class="column-title">
												<strong>
													<a href="<?php echo esc_url( get_edit_post_link( $song['id'] ) ); ?>" target="_blank">
														<?php echo esc_html( $song['title'] ); ?>
													</a>
												</strong>
												<?php if ( $song['slug'] !== sanitize_title( $song['title'] ) ): ?>
													<br><small>Slug: <?php echo esc_html( $song['slug'] ); ?></small>
												<?php endif; ?>
											</td>
											<td class="column-date">
												<?php echo esc_html( $song['date'] ); ?>
											</td>
											<td class="column-videos">
												<?php
												$video_count = $this->count_videos( $song['id'] );
												echo esc_html( $video_count );
												if ( $video_count > 0 ) {
													echo ' <a href="#" class="view-videos" data-song-id="' . esc_attr( $song['id'] ) . '">(view)</a>';
												}
												?>
											</td>
											<td class="column-actions">
												<a href="<?php echo esc_url( get_permalink( $song['id'] ) ); ?>" target="_blank" class="button button-small">
													View
												</a>
												<a href="<?php echo esc_url( get_edit_post_link( $song['id'] ) ); ?>" target="_blank" class="button button-small">
													Edit
												</a>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>

							<div class="merge-actions">
								<button type="button" class="button button-primary merge-selected" 
										data-group-title="<?php echo esc_attr( $group['title'] ); ?>"
										disabled>
									Merge Selected Songs
								</button>
								<span class="merge-help">
									Select 2 or more songs to merge. The oldest song will be kept, and videos from others will be added to it.
								</span>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>

		<!-- Video Details Modal -->
		<div id="video-details-modal" class="video-details-modal" style="display: none;">
			<div class="video-details-content">
				<span class="video-details-close">&times;</span>
				<h2>Video Details</h2>
				<div id="video-details-body"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Find potential duplicate songs
	 *
	 * @param float $similarity_threshold Minimum similarity score (0-1)
	 * @return array Array of duplicate groups
	 */
	public function find_duplicates( $similarity_threshold = 0.85 ) {
		$all_songs = get_posts( array(
			'post_type'      => 'song',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		$duplicates = array();
		$processed = array();

		foreach ( $all_songs as $song ) {
			$song_id = $song->ID;
			$song_title = get_the_title( $song_id );
			$title_normalized = $this->normalize_title( $song_title );

			// Skip if already processed
			if ( in_array( $song_id, $processed ) ) {
				continue;
			}

			$group = array(
				'title' => $song_title,
				'songs' => array( $this->get_song_data( $song_id ) ),
			);

			// Find similar songs
			foreach ( $all_songs as $other_song ) {
				$other_id = $other_song->ID;
				
				if ( $song_id === $other_id || in_array( $other_id, $processed ) ) {
					continue;
				}

				$other_title = get_the_title( $other_id );
				$other_normalized = $this->normalize_title( $other_title );

				// Check exact match first (case-insensitive)
				if ( strcasecmp( $title_normalized, $other_normalized ) === 0 ) {
					$group['songs'][] = $this->get_song_data( $other_id );
					$processed[] = $other_id;
					continue;
				}

				// Check similarity
				$similarity = $this->calculate_similarity( $title_normalized, $other_normalized );
				if ( $similarity >= $similarity_threshold ) {
					$group['songs'][] = $this->get_song_data( $other_id );
					$processed[] = $other_id;
				}
			}

			// Only add groups with 2+ songs
			if ( count( $group['songs'] ) > 1 ) {
				// Sort by date (oldest first)
				usort( $group['songs'], function( $a, $b ) {
					return strtotime( $a['date_raw'] ) - strtotime( $b['date_raw'] );
				} );
				$duplicates[] = $group;
			}

			$processed[] = $song_id;
		}

		return $duplicates;
	}

	/**
	 * Get song data for display
	 *
	 * @param int $song_id Song post ID
	 * @return array Song data
	 */
	private function get_song_data( $song_id ) {
		return array(
			'id'       => $song_id,
			'title'    => get_the_title( $song_id ),
			'slug'     => get_post_field( 'post_name', $song_id ),
			'date'     => get_the_date( 'F j, Y', $song_id ),
			'date_raw' => get_the_date( 'Y-m-d', $song_id ),
		);
	}

	/**
	 * Normalize title for comparison
	 *
	 * @param string $title Song title
	 * @return string Normalized title
	 */
	private function normalize_title( $title ) {
		// Convert to lowercase
		$title = mb_strtolower( $title, 'UTF-8' );
		
		// Remove extra whitespace
		$title = preg_replace( '/\s+/', ' ', $title );
		
		// Trim
		$title = trim( $title );
		
		// Remove common punctuation that might differ
		$title = str_replace( array( "'", '"', '`', '´' ), '', $title );
		
		return $title;
	}

	/**
	 * Calculate string similarity using Levenshtein distance
	 *
	 * @param string $str1 First string
	 * @param string $str2 Second string
	 * @return float Similarity score (0-1)
	 */
	private function calculate_similarity( $str1, $str2 ) {
		$str1 = trim( $str1 );
		$str2 = trim( $str2 );

		if ( $str1 === $str2 ) {
			return 1.0;
		}

		$max_len = max( mb_strlen( $str1, 'UTF-8' ), mb_strlen( $str2, 'UTF-8' ) );
		if ( $max_len === 0 ) {
			return 1.0;
		}

		$distance = levenshtein( $str1, $str2 );
		return 1 - ( $distance / $max_len );
	}

	/**
	 * Count videos for a song
	 *
	 * @param int $song_id Song post ID
	 * @return int Number of videos
	 */
	public function count_videos( $song_id ) {
		$count = 0;

		// Check old fields
		if ( get_field( 'video', $song_id ) ) {
			$count++;
		}
		if ( get_field( 'tiktok_video', $song_id ) ) {
			$count++;
		}
		if ( get_field( 'instagram_video', $song_id ) ) {
			$count++;
		}
		if ( get_field( 'music_video', $song_id ) ) {
			$count++;
		}

		// Check new repeater field
		$videos = get_field( 'embeds', $song_id );
		if ( $videos && is_array( $videos ) ) {
			$count += count( $videos );
		}

		return $count;
	}

	/**
	 * Get video details for a song
	 *
	 * @param int $song_id Song post ID
	 * @return array Video details
	 */
	public function get_video_details( $song_id ) {
		$videos = array();

		// Check old fields
		$video = get_field( 'video', $song_id );
		if ( $video ) {
			$videos[] = array(
				'source' => 'youtube',
				'url'    => $video,
				'embed'  => $video,
			);
		}

		$tiktok = get_field( 'tiktok_video', $song_id );
		if ( $tiktok ) {
			$videos[] = array(
				'source' => 'tiktok',
				'url'    => $tiktok,
				'embed'  => $tiktok,
			);
		}

		$instagram = get_field( 'instagram_video', $song_id );
		if ( $instagram ) {
			$videos[] = array(
				'source' => 'instagram',
				'url'    => $instagram,
				'embed'  => $instagram,
			);
		}

		$music_video = get_field( 'music_video', $song_id );
		if ( $music_video ) {
			$videos[] = array(
				'source' => 'youtube',
				'url'    => $music_video,
				'embed'  => $music_video,
			);
		}

		// Check new repeater field
		$embeds = get_field( 'embeds', $song_id );
		if ( $embeds && is_array( $embeds ) ) {
			foreach ( $embeds as $video ) {
				$source = $video['embed_type'] ?? 'youtube';
				
				// Get the appropriate field based on source
				$embed = '';
				$url = '';
				$video_data = array(
					'source' => $source,
				);
				
				switch ( $source ) {
					case 'youtube':
						$embed_raw = $video['youtube_video'] ?? '';
						// Handle ACF oembed field - can return array or string
						if ( is_array( $embed_raw ) ) {
							$embed = $embed_raw['html'] ?? $embed_raw['url'] ?? '';
							$url = $embed_raw['url'] ?? '';
						} else {
							$embed = $embed_raw;
							$url = $this->extract_url( $embed );
						}
						$video_data['embed'] = $embed;
						$video_data['url'] = $url;
						break;
					case 'tiktok':
						$embed_raw = $video['tiktok_video'] ?? '';
						// Handle ACF oembed field - can return array or string
						if ( is_array( $embed_raw ) ) {
							$embed = $embed_raw['html'] ?? $embed_raw['url'] ?? '';
							$url = $embed_raw['url'] ?? '';
						} else {
							$embed = $embed_raw;
							$url = $this->extract_url( $embed );
						}
						$video_data['embed'] = $embed;
						$video_data['url'] = $url;
						break;
					case 'instagram':
						$embed_raw = $video['instagram_video'] ?? '';
						// Handle ACF oembed field - can return array or string
						if ( is_array( $embed_raw ) ) {
							$embed = $embed_raw['html'] ?? $embed_raw['url'] ?? '';
							$url = $embed_raw['url'] ?? '';
						} else {
							$embed = $embed_raw;
							$url = $this->extract_url( $embed );
						}
						$video_data['embed'] = $embed;
						$video_data['url'] = $url;
						break;
					case 'bandcamp':
						// Preserve full bandcamp structure
						$video_data['bandcamp_song_id'] = $video['bandcamp_song_id'] ?? '';
						$video_data['bandcamp_album_id'] = $video['bandcamp_album_id'] ?? '';
						$video_data['bandcamp_iframe'] = $video['bandcamp_iframe'] ?? '';
						
						// Construct embed for display/URL extraction
						if ( ! empty( $video['bandcamp_song_id'] ) && ! empty( $video['bandcamp_album_id'] ) ) {
							$embed = '<iframe style="border: 0; width: 100%; height: 120px;" src="https://bandcamp.com/EmbeddedPlayer/size=large/bgcol=ffffff/linkcol=0687f5/tracklist=false/artwork=small/transparent=true/album=' . esc_attr( $video['bandcamp_album_id'] ) . '/track=' . esc_attr( $video['bandcamp_song_id'] ) . '" seamless></iframe>';
						} elseif ( ! empty( $video['bandcamp_iframe'] ) ) {
							$embed = $video['bandcamp_iframe'];
						}
						$video_data['embed'] = $embed;
						break;
				}
				
				$videos[] = $video_data;
			}
		}

		return $videos;
	}

	/**
	 * AJAX: Get duplicates
	 */
	public function ajax_get_duplicates() {
		check_ajax_referer( 'jww_song_duplicates', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$duplicates = $this->find_duplicates();
		wp_send_json_success( array( 'duplicates' => $duplicates ) );
	}

	/**
	 * AJAX: Get video details
	 */
	public function ajax_get_video_details() {
		check_ajax_referer( 'jww_song_duplicates', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$song_id = isset( $_POST['song_id'] ) ? intval( $_POST['song_id'] ) : 0;
		if ( ! $song_id ) {
			wp_send_json_error( array( 'message' => 'Invalid song ID' ) );
		}

		$videos = $this->get_video_details( $song_id );
		wp_send_json_success( array( 'videos' => $videos ) );
	}

	/**
	 * AJAX: Merge songs
	 */
	public function ajax_merge_songs() {
		check_ajax_referer( 'jww_song_duplicates', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$song_ids = isset( $_POST['song_ids'] ) ? array_map( 'intval', $_POST['song_ids'] ) : array();
		if ( count( $song_ids ) < 2 ) {
			wp_send_json_error( array( 'message' => 'Please select at least 2 songs to merge' ) );
		}

		$result = $this->merge_songs( $song_ids );
		
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message' => 'Songs merged successfully',
			'kept_id' => $result['kept_id'],
			'merged_ids' => $result['merged_ids'],
		) );
	}

	/**
	 * Merge multiple songs into one
	 *
	 * @param array $song_ids Array of song IDs to merge
	 * @return array|WP_Error Result with kept_id and merged_ids
	 */
	public function merge_songs( $song_ids ) {
		if ( count( $song_ids ) < 2 ) {
			return new WP_Error( 'insufficient_songs', 'At least 2 songs are required to merge' );
		}

		// Sort by date (oldest first) - keep the oldest one
		usort( $song_ids, function( $a, $b ) {
			$date_a = get_the_date( 'Y-m-d', $a );
			$date_b = get_the_date( 'Y-m-d', $b );
			return strcmp( $date_a, $date_b );
		} );

		$kept_id = $song_ids[0];
		$merged_ids = array_slice( $song_ids, 1 );

		// Get all videos from songs to merge
		$all_videos = $this->get_video_details( $kept_id );
		
		// Track URLs to avoid duplicates
		$seen_urls = array();
		foreach ( $all_videos as $video ) {
			if ( ! empty( $video['url'] ) ) {
				$seen_urls[] = $video['url'];
			}
		}

		foreach ( $merged_ids as $song_id ) {
			$videos = $this->get_video_details( $song_id );
			
			// Add videos, avoiding duplicates by URL
			foreach ( $videos as $video ) {
				$video_url = $video['url'] ?? '';
				// Skip if we've already seen this URL (avoid duplicates)
				if ( ! empty( $video_url ) && in_array( $video_url, $seen_urls ) ) {
					continue;
				}
				$all_videos[] = $video;
				if ( ! empty( $video_url ) ) {
					$seen_urls[] = $video_url;
				}
			}

			// Update references in shows (setlists)
			$this->update_show_references( $song_id, $kept_id );

			// Update references in albums
			$this->update_album_references( $song_id, $kept_id );

			// Delete the duplicate song
			wp_delete_post( $song_id, true );
		}

		// Save all videos to the kept song using new repeater format
		$this->save_videos_to_song( $kept_id, $all_videos );

		// Update post date to oldest video date if available
		$oldest_date = $this->get_oldest_video_date( $all_videos );
		if ( $oldest_date ) {
			wp_update_post( array(
				'ID'        => $kept_id,
				'post_date' => $oldest_date,
			) );
		}

		return array(
			'kept_id'    => $kept_id,
			'merged_ids' => $merged_ids,
		);
	}

	/**
	 * Update show references (setlists) from old song to new song
	 *
	 * @param int $old_song_id Old song ID
	 * @param int $new_song_id New song ID
	 */
	private function update_show_references( $old_song_id, $new_song_id ) {
		$shows = get_posts( array(
			'post_type'      => 'show',
			'posts_per_page' => -1,
			'post_status'    => array( 'publish', 'future' ),
			'meta_query'     => array(
				array(
					'key'     => 'setlist',
					'compare' => 'EXISTS',
				),
			),
		) );

		foreach ( $shows as $show ) {
			$setlist = get_field( 'setlist', $show->ID );
			if ( ! $setlist || ! is_array( $setlist ) ) {
				continue;
			}

			$updated = false;
			foreach ( $setlist as $key => $item ) {
				if ( $item['entry_type'] === 'song-post' && ! empty( $item['song'] ) ) {
					$song = is_array( $item['song'] ) ? $item['song'][0] : $item['song'];
					$item_song_id = is_object( $song ) ? $song->ID : $song;
					
					if ( $item_song_id == $old_song_id ) {
						$setlist[ $key ]['song'] = $new_song_id;
						$updated = true;
					}
				}
			}

			if ( $updated ) {
				update_field( 'setlist', $setlist, $show->ID );
			}
		}
	}

	/**
	 * Update album references from old song to new song
	 *
	 * @param int $old_song_id Old song ID
	 * @param int $new_song_id New song ID
	 */
	private function update_album_references( $old_song_id, $new_song_id ) {
		// Albums might have song relationships - update if needed
		// This depends on your album structure
		// For now, we'll leave this as a placeholder
	}

	/**
	 * Save videos to song using new repeater format
	 *
	 * @param int $song_id Song post ID
	 * @param array $videos Array of video data
	 */
	private function save_videos_to_song( $song_id, $videos ) {
		$formatted_videos = array();

		foreach ( $videos as $video ) {
			$source = $video['source'] ?? 'youtube';
			$formatted_video = array(
				'embed_type' => $source,
			);

			// Set the appropriate field based on source
			switch ( $source ) {
				case 'youtube':
				case 'youtube_video': // Support old format during migration
					$formatted_video['embed_type'] = 'youtube';
					$formatted_video['youtube_video'] = $video['embed'] ?? '';
					break;
				case 'tiktok':
				case 'tiktok_video': // Support old format during migration
					$formatted_video['embed_type'] = 'tiktok';
					$formatted_video['tiktok_video'] = $video['embed'] ?? '';
					break;
				case 'instagram':
				case 'instagram_video': // Support old format during migration
					$formatted_video['embed_type'] = 'instagram';
					$formatted_video['instagram_video'] = $video['embed'] ?? '';
					break;
				case 'bandcamp':
					$formatted_video['embed_type'] = 'bandcamp';
					
					// If we have preserved bandcamp structure from repeater, use it
					if ( isset( $video['bandcamp_song_id'] ) || isset( $video['bandcamp_album_id'] ) || isset( $video['bandcamp_iframe'] ) ) {
						if ( ! empty( $video['bandcamp_song_id'] ) ) {
							$formatted_video['bandcamp_song_id'] = $video['bandcamp_song_id'];
						}
						if ( ! empty( $video['bandcamp_album_id'] ) ) {
							$formatted_video['bandcamp_album_id'] = $video['bandcamp_album_id'];
						}
						if ( ! empty( $video['bandcamp_iframe'] ) ) {
							$formatted_video['bandcamp_iframe'] = $video['bandcamp_iframe'];
						}
					} elseif ( ! empty( $video['embed'] ) ) {
						// Fallback: try to extract song_id and album_id from iframe, or use iframe
						if ( preg_match( '/\/album=([^\/\s"\'<>]+)/', $video['embed'], $album_match ) ) {
							$formatted_video['bandcamp_album_id'] = $album_match[1];
						}
						if ( preg_match( '/\/track=([^\/\s"\'<>]+)/', $video['embed'], $track_match ) ) {
							$formatted_video['bandcamp_song_id'] = $track_match[1];
						}
						// If we couldn't extract IDs, use the iframe
						if ( empty( $formatted_video['bandcamp_song_id'] ) ) {
							$formatted_video['bandcamp_iframe'] = $video['embed'];
						}
					}
					break;
			}

			$formatted_videos[] = $formatted_video;
		}

		update_field( 'embeds', $formatted_videos, $song_id );
	}

	/**
	 * Get oldest video date from videos array
	 *
	 * @param array $videos Array of video data
	 * @return string|false Oldest date in Y-m-d format, or false
	 */
	private function get_oldest_video_date( $videos ) {
		// Since we're not storing dates in the new format, use the post date
		// This will be set to the oldest song's date during merge
		return false;
	}

	/**
	 * Extract URL from embed code or return as-is if already a URL
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
new Song_Duplicate_Detector();
