<?php
/**
 * Class Song_Export_Import
 *
 * Admin interface for exporting all song data to JSON and re-importing to update existing songs.
 * Export includes: lyrics, lyric_annotations, embeds (videos), song_links, artist, album, categories, tags.
 * Re-import updates existing songs matched by post ID.
 *
 * @package JWW_Theme
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Song_Export_Import {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'wp_ajax_jww_export_songs', array( $this, 'ajax_export_songs' ) );
		add_action( 'wp_ajax_jww_import_songs_json', array( $this, 'ajax_import_songs_json' ) );
	}

	/**
	 * Add admin page under Songs
	 */
	public function add_admin_page() {
		add_submenu_page(
			'edit.php?post_type=song',
			'Song Export / Import',
			'Export / Import',
			'manage_options',
			'song-export-import',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin scripts and styles
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( $hook !== 'song_page_song-export-import' ) {
			return;
		}

		wp_enqueue_script(
			'jww-song-export-import',
			get_stylesheet_directory_uri() . '/includes/js/song-export-import.js',
			array( 'jquery' ),
			wp_get_theme()->get( 'Version' ),
			true
		);

		wp_localize_script( 'jww-song-export-import', 'jwwSongExportImport', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'jww_song_export_import' ),
		) );

		wp_enqueue_style(
			'jww-song-export-import',
			get_stylesheet_directory_uri() . '/includes/css/song-export-import.css',
			array(),
			wp_get_theme()->get( 'Version' )
		);
	}

	/**
	 * Render admin page
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You do not have sufficient permissions to access this page.' );
		}

		?>
		<div class="wrap">
			<h1>Song Export / Import</h1>
			<p>Export all song data (lyrics, annotations, videos, taxonomy) to a JSON file. Edit the file (e.g. add annotations with AI), then re-import to update existing songs. Songs are matched by post ID.</p>

			<div class="jww-song-export-import-container">
				<!-- Export -->
				<div class="jww-importer-section">
					<h2>Export Songs to JSON</h2>
					<p class="description">Download a JSON file containing all songs with ACF fields (lyrics, lyric annotations, embeds, song links, artist, album) and taxonomy (categories, tags).</p>
					<form id="export-songs-form" class="jww-export-form">
						<p>
							<label for="export-tag">Filter by tag (optional):</label><br>
							<select id="export-tag" name="tag_id">
								<option value="">All songs</option>
								<?php
								$tags = get_terms( array(
									'taxonomy'   => 'post_tag',
									'hide_empty' => false,
									'orderby'    => 'name',
									'order'      => 'ASC',
								) );
								if ( ! is_wp_error( $tags ) && ! empty( $tags ) ) {
									foreach ( $tags as $tag ) {
										echo '<option value="' . esc_attr( $tag->term_id ) . '">' . esc_html( $tag->name ) . '</option>';
									}
								}
								?>
							</select>
						</p>
						<p>
							<button type="submit" class="button button-primary">Export Songs to JSON</button>
							<span class="spinner"></span>
						</p>
						<div class="export-result"></div>
					</form>
				</div>

				<!-- Import -->
				<div class="jww-importer-section">
					<h2>Import from JSON File</h2>
					<p class="description">Upload a previously exported JSON file to update existing songs. Only songs with matching post IDs will be updated. Add or edit lyric annotations in the JSON, then re-import.</p>
					<form id="import-songs-json-form" class="jww-import-form" enctype="multipart/form-data">
						<p>
							<label for="songs-json-file">JSON File:</label><br>
							<input type="file" id="songs-json-file" name="json_file" accept=".json,application/json" required>
						</p>
						<p>
							<button type="submit" class="button button-primary">Import JSON (Update Songs)</button>
							<span class="spinner"></span>
						</p>
						<div class="import-result"></div>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX: Export songs to JSON
	 */
	public function ajax_export_songs() {
		check_ajax_referer( 'jww_song_export_import', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$tag_id = isset( $_POST['tag_id'] ) ? (int) $_POST['tag_id'] : 0;

		$args = array(
			'post_type'      => 'song',
			'posts_per_page' => -1,
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		if ( $tag_id > 0 ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'post_tag',
					'field'    => 'term_id',
					'terms'    => $tag_id,
				),
			);
		}

		$posts = get_posts( $args );

		$data = array();
		foreach ( $posts as $post ) {
			$data[] = $this->format_song_for_export( $post->ID );
		}

		wp_send_json_success( array(
			'data'  => $data,
			'count' => count( $data ),
		) );
	}

	/**
	 * Format a single song for export (ACF fields + taxonomy).
	 *
	 * @param int $post_id Song post ID.
	 * @return array
	 */
	private function format_song_for_export( $post_id ) {
		$post = get_post( $post_id );
		$embeds = get_field( 'embeds', $post_id );
		$lyrics = get_field( 'lyrics', $post_id );
		$lyric_annotations = get_field( 'lyric_annotations', $post_id );
		$song_links = get_field( 'song_links', $post_id );
		$artist = get_field( 'artist', $post_id );
		$album = get_field( 'album', $post_id );

		// Normalize embeds to plain arrays with URL strings (no ACF oembed objects)
		$embeds_export = array();
		if ( is_array( $embeds ) ) {
			foreach ( $embeds as $row ) {
				$item = array(
					'embed_type' => isset( $row['embed_type'] ) ? $row['embed_type'] : 'youtube',
				);
				foreach ( array( 'youtube_video', 'instagram_video', 'tiktok_video', 'bandcamp_iframe', 'bandcamp_album_id', 'bandcamp_song_id' ) as $key ) {
					if ( ! empty( $row[ $key ] ) ) {
						$val = $row[ $key ];
						$item[ $key ] = is_string( $val ) ? $val : ( isset( $val['url'] ) ? $val['url'] : (string) $val );
					}
				}
				$embeds_export[] = $item;
			}
		}

		// Artist: store as array of post IDs (and titles for readability)
		$artist_ids = array();
		$artist_titles = array();
		if ( $artist ) {
			$artist = is_array( $artist ) ? $artist : array( $artist );
			foreach ( $artist as $a ) {
				$id = is_object( $a ) ? $a->ID : (int) $a;
				if ( $id ) {
					$artist_ids[] = $id;
					$artist_titles[] = get_the_title( $id );
				}
			}
		}

		$album_id = null;
		if ( $album ) {
			$album_id = is_object( $album ) ? $album->ID : (int) $album;
		}

		$categories = wp_get_post_terms( $post_id, 'category' );
		$tags       = wp_get_post_terms( $post_id, 'post_tag' );
		$cat_slugs  = is_wp_error( $categories ) ? array() : wp_list_pluck( $categories, 'slug' );
		$tag_slugs  = is_wp_error( $tags ) ? array() : wp_list_pluck( $tags, 'slug' );

		return array(
			'id'                 => (int) $post_id,
			'post_title'         => $post->post_title,
			'post_name'          => $post->post_name,
			'lyrics'             => $lyrics !== null && $lyrics !== false ? (string) $lyrics : '',
			'lyric_annotations'  => $lyric_annotations !== null && $lyric_annotations !== false ? (string) $lyric_annotations : '',
			'embeds'             => $embeds_export,
			'song_links'         => is_array( $song_links ) ? $song_links : array(),
			'artist'             => $artist_ids,
			'artist_titles'       => $artist_titles,
			'album'              => $album_id,
			'categories'         => $cat_slugs,
			'tags'               => $tag_slugs,
			'video_id'           => get_post_meta( $post_id, 'video_id', true ) ?: '',
		);
	}

	/**
	 * AJAX: Import songs from uploaded JSON (update existing only).
	 */
	public function ajax_import_songs_json() {
		check_ajax_referer( 'jww_song_export_import', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		if ( empty( $_FILES['json_file'] ) || $_FILES['json_file']['error'] !== UPLOAD_ERR_OK ) {
			$code = isset( $_FILES['json_file']['error'] ) ? (int) $_FILES['json_file']['error'] : UPLOAD_ERR_NO_FILE;
			wp_send_json_error( array( 'message' => $this->get_upload_error_message( $code ) ) );
		}

		$tmp_path = $_FILES['json_file']['tmp_name'];
		$content  = file_get_contents( $tmp_path );
		if ( $content === false ) {
			wp_send_json_error( array( 'message' => 'Could not read uploaded file.' ) );
		}

		$data = json_decode( $content, true );
		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $data ) ) {
			wp_send_json_error( array( 'message' => 'Invalid JSON. Expected an array of song objects.' ) );
		}

		// Allow wrapper object like { "data": [ ... ] } or direct array [ ... ]
		if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
			$songs = $data['data'];
		} else {
			$songs = $data;
		}

		$updated = 0;
		$skipped = 0;
		$errors  = array();

		foreach ( $songs as $index => $song ) {
			if ( empty( $song['id'] ) || get_post_type( (int) $song['id'] ) !== 'song' ) {
				$skipped++;
				$errors[] = 'Row ' . ( $index + 1 ) . ': Invalid or missing song ID, skipped.';
				continue;
			}

			$result = $this->update_song_from_import_data( (int) $song['id'], $song );
			if ( is_wp_error( $result ) ) {
				$skipped++;
				$errors[] = 'Row ' . ( $index + 1 ) . ' (ID ' . $song['id'] . '): ' . $result->get_error_message();
			} else {
				$updated++;
			}
		}

		$message = sprintf(
			'Processed %d song(s): %d updated, %d skipped.',
			count( $songs ),
			$updated,
			$skipped
		);

		wp_send_json_success( array(
			'message' => $message,
			'updated' => $updated,
			'skipped' => $skipped,
			'errors'  => $errors,
		) );
	}

	/**
	 * Update an existing song post from imported data (ACF + taxonomy).
	 *
	 * @param int   $post_id Song post ID.
	 * @param array $data    Import row (keys: post_title, lyrics, lyric_annotations, embeds, song_links, artist, album, categories, tags, video_id).
	 * @return true|WP_Error
	 */
	private function update_song_from_import_data( $post_id, $data ) {
		// Optional: update post title
		if ( ! empty( $data['post_title'] ) ) {
			wp_update_post( array(
				'ID'         => $post_id,
				'post_title' => sanitize_text_field( $data['post_title'] ),
			) );
		}

		if ( function_exists( 'update_field' ) ) {
			if ( array_key_exists( 'lyrics', $data ) ) {
				update_field( 'lyrics', $data['lyrics'], $post_id );
			}
			if ( array_key_exists( 'lyric_annotations', $data ) ) {
				update_field( 'lyric_annotations', $data['lyric_annotations'], $post_id );
			}
			if ( array_key_exists( 'embeds', $data ) && is_array( $data['embeds'] ) ) {
				update_field( 'embeds', $data['embeds'], $post_id );
			}
			if ( array_key_exists( 'song_links', $data ) && is_array( $data['song_links'] ) ) {
				update_field( 'song_links', $data['song_links'], $post_id );
			}
			if ( array_key_exists( 'artist', $data ) ) {
				$artist = is_array( $data['artist'] ) ? $data['artist'] : array( $data['artist'] );
				$artist = array_filter( array_map( 'intval', $artist ) );
				update_field( 'artist', $artist, $post_id );
			}
			if ( array_key_exists( 'album', $data ) ) {
				$album = ! empty( $data['album'] ) ? (int) $data['album'] : null;
				update_field( 'album', $album, $post_id );
			}
		}

		if ( array_key_exists( 'categories', $data ) && is_array( $data['categories'] ) ) {
			wp_set_post_terms( $post_id, $data['categories'], 'category', false );
		}
		if ( array_key_exists( 'tags', $data ) && is_array( $data['tags'] ) ) {
			wp_set_post_terms( $post_id, $data['tags'], 'post_tag', false );
		}

		if ( array_key_exists( 'video_id', $data ) ) {
			$video_id = sanitize_text_field( $data['video_id'] );
			update_post_meta( $post_id, 'video_id', $video_id );
		}

		return true;
	}

	/**
	 * Get upload error message
	 *
	 * @param int $error_code UPLOAD_ERR_* constant.
	 * @return string
	 */
	private function get_upload_error_message( $error_code ) {
		$messages = array(
			UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize.',
			UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE.',
			UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
			UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
			UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
			UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
			UPLOAD_ERR_EXTENSION  => 'File upload stopped by extension.',
		);
		return isset( $messages[ $error_code ] ) ? $messages[ $error_code ] : 'Unknown upload error (code: ' . $error_code . ')';
	}
}

new Song_Export_Import();
