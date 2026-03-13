<?php
/**
 * Class Song_Export_Import
 *
 * Admin interface for exporting song data to JSON and re-importing to update existing songs.
 * Export: optional fields (title, description/main content, lyrics, embeds, categories, tags, band/artist, album/release, etc.),
 * with optional "only empty" filter and tag filter. Terms and relationships are exported as slugs for readability.
 * Import: partial updates only (only present keys are updated); slugs are resolved to term/post IDs.
 *
 * @package JWW_Theme
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Song_Export_Import {

	/**
	 * Exportable field definitions: key => [ 'label' => string, 'type' => 'post'|'acf'|'taxonomy'|'meta', 'taxonomy'|'post_type' for resolution ].
	 *
	 * @var array
	 */
	private $exportable_fields = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->exportable_fields = $this->get_exportable_fields_config();
		add_action( 'admin_menu', array( $this, 'add_admin_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'wp_ajax_jww_export_songs', array( $this, 'ajax_export_songs' ) );
		add_action( 'wp_ajax_jww_import_songs_json', array( $this, 'ajax_import_songs_json' ) );
	}

	/**
	 * Config for exportable fields (key, label, type, and taxonomy/post_type for slug resolution).
	 *
	 * @return array
	 */
	private function get_exportable_fields_config() {
		return array(
			'id'                => array( 'label' => 'ID (required for import matching)', 'type' => 'post', 'always_include' => true ),
			'post_title'        => array( 'label' => 'Title', 'type' => 'post', 'always_include' => true ),
			'post_name'         => array( 'label' => 'Slug', 'type' => 'post' ),
			'description'       => array( 'label' => 'Description (main content)', 'type' => 'post', 'post_field' => 'post_content' ),
			'post_date'         => array( 'label' => 'Date published', 'type' => 'post' ),
			'lyrics'            => array( 'label' => 'Lyrics', 'type' => 'acf' ),
			'lyric_annotations' => array( 'label' => 'Lyric annotations', 'type' => 'acf' ),
			'chord_sheet'       => array( 'label' => 'Chord sheet', 'type' => 'acf' ),
			'tabs'              => array( 'label' => 'Tabs', 'type' => 'acf' ),
			'capo'              => array( 'label' => 'Capo', 'type' => 'acf' ),
			'chords_source_url' => array( 'label' => 'Chords source URL', 'type' => 'acf' ),
			'embeds'            => array( 'label' => 'Embeds (videos)', 'type' => 'acf' ),
			'song_links'        => array( 'label' => 'Song links', 'type' => 'acf' ),
			'artist'            => array( 'label' => 'Artist / Band', 'type' => 'relationship', 'post_type' => 'band' ),
			'album'             => array( 'label' => 'Album / Release', 'type' => 'relationship', 'post_type' => 'album' ),
			'categories'        => array( 'label' => 'Categories', 'type' => 'taxonomy', 'taxonomy' => 'category' ),
			'tags'              => array( 'label' => 'Tags', 'type' => 'taxonomy', 'taxonomy' => 'post_tag' ),
		);
	}

	/**
	 * Get exportable fields config for use in templates/JS.
	 *
	 * @return array
	 */
	public function get_exportable_fields() {
		return $this->exportable_fields;
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
			get_stylesheet_directory_uri() . '/admin/js/song-export-import.js',
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
			get_stylesheet_directory_uri() . '/admin/css/song-export-import.css',
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
			<p>Export song data to JSON (choose which fields to include). Edit the file, then re-import to update existing songs. Songs are matched by post ID. Only fields present in the import file are updated.</p>

			<div class="jww-song-export-import-container">
				<!-- Export -->
				<div class="jww-importer-section">
					<h2>Export Songs to JSON</h2>
					<p class="description">Choose which fields to include. Taxonomy terms and relationships (artist/band, album) are exported as slugs so the JSON is human-readable. Filter by tag and/or band, or export only songs where the selected fields are empty (e.g. songs missing descriptions).</p>
					<form id="export-songs-form" class="jww-export-form">
						<fieldset class="jww-export-fields">
							<legend>Fields to include</legend>
							<p class="description">Select the fields to include in the export. ID and Title are always included so the file is human-readable and import can match songs.</p>
							<ul class="jww-export-field-list">
								<?php
								foreach ( $this->get_exportable_fields() as $key => $config ) {
									$always = ! empty( $config['always_include'] );
									$id_attr = 'export-field-' . sanitize_key( $key );
									?>
									<li>
										<label for="<?php echo esc_attr( $id_attr ); ?>">
											<input type="checkbox" name="export_fields[]" id="<?php echo esc_attr( $id_attr ); ?>"
												value="<?php echo esc_attr( $key ); ?>"
												<?php echo $always ? ' checked disabled' : ''; ?>>
											<?php echo esc_html( $config['label'] ); ?>
											<?php if ( $always ) : ?>
												<em>(always included)</em>
											<?php endif; ?>
										</label>
									</li>
								<?php } ?>
							</ul>
						</fieldset>
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
							<label for="export-band">Filter by band/artist (optional):</label><br>
							<select id="export-band" name="band_id">
								<option value="">All bands</option>
								<?php
								$bands = get_posts( array(
									'post_type'      => 'band',
									'posts_per_page' => -1,
									'post_status'    => 'publish',
									'orderby'        => 'title',
									'order'          => 'ASC',
								) );
								if ( ! empty( $bands ) ) {
									foreach ( $bands as $band ) {
										echo '<option value="' . esc_attr( $band->ID ) . '">' . esc_html( $band->post_title ) . '</option>';
									}
								}
								?>
							</select>
						</p>
						<p>
							<label for="export-only-empty">
								<input type="checkbox" id="export-only-empty" name="only_empty" value="1">
								Only include songs where <strong>all</strong> selected fields are empty
							</label>
						</p>
						<p>
							<label for="export-only-any-empty">
								<input type="checkbox" id="export-only-any-empty" name="only_any_empty" value="1">
								Only include songs where <strong>any</strong> of selected fields are empty
							</label>
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
					<p class="description">Upload a JSON file to update existing songs (matched by ID). Only fields present in the file are updated; omitted fields are left unchanged. Use slugs for categories, tags, artist (band), and album in the JSON; they are resolved to terms/posts on import.</p>
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

		$tag_id         = isset( $_POST['tag_id'] ) ? (int) $_POST['tag_id'] : 0;
		$band_id        = isset( $_POST['band_id'] ) ? (int) $_POST['band_id'] : 0;
		$only_empty     = ! empty( $_POST['only_empty'] );
		$only_any_empty = ! empty( $_POST['only_any_empty'] );
		$raw_fields     = isset( $_POST['fields'] ) && is_array( $_POST['fields'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['fields'] ) ) : array();
		$all_keys       = array_keys( $this->exportable_fields );
		$fields         = empty( $raw_fields ) ? $all_keys : array_intersect( $raw_fields, $all_keys );
		if ( empty( $fields ) ) {
			$fields = $all_keys;
		}
		$required = array( 'id', 'post_title' );
		foreach ( $required as $req ) {
			if ( ! in_array( $req, $fields, true ) ) {
				$fields = array_merge( array( $req ), $fields );
			}
		}

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

		// Filter by band/artist (ACF relationship): only songs linked to the selected band.
		if ( $band_id > 0 ) {
			$posts = array_filter( $posts, function ( $post ) use ( $band_id ) {
				$artist = get_field( 'artist', $post->ID );
				if ( empty( $artist ) ) {
					return false;
				}
				$ids = array();
				if ( is_array( $artist ) ) {
					foreach ( $artist as $a ) {
						$ids[] = is_object( $a ) ? (int) $a->ID : (int) $a;
					}
				} else {
					$ids[] = is_object( $artist ) ? (int) $artist->ID : (int) $artist;
				}
				return in_array( $band_id, $ids, true );
			} );
			$posts = array_values( $posts );
		}

		$data = array();
		foreach ( $posts as $post ) {
			if ( $only_empty && ! $this->song_has_all_selected_fields_empty( $post->ID, $fields ) ) {
				continue;
			}
			if ( $only_any_empty && ! $this->song_has_any_selected_fields_empty( $post->ID, $fields ) ) {
				continue;
			}
			$data[] = $this->format_song_for_export( $post->ID, $fields );
		}

		wp_send_json_success( array(
			'data'  => $data,
			'count' => count( $data ),
		) );
	}

	/**
	 * Check if a song has all of the given fields empty (for "only empty" export filter).
	 *
	 * @param int   $post_id Song post ID.
	 * @param array $fields  Field keys to check.
	 * @return bool True if every selected field is empty.
	 */
	private function song_has_all_selected_fields_empty( $post_id, $fields ) {
		$row = $this->format_song_for_export( $post_id, $fields );
		foreach ( $fields as $key ) {
			if ( ! array_key_exists( $key, $row ) ) {
				continue;
			}
			$val = $row[ $key ];
			if ( is_array( $val ) ) {
				if ( ! empty( $val ) ) {
					return false;
				}
			} elseif ( $val !== '' && $val !== null ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Check if a song has any of the given fields empty (for "only any empty" export filter).
	 *
	 * @param int   $post_id Song post ID.
	 * @param array $fields  Field keys to check.
	 * @return bool True if at least one selected field is empty.
	 */
	private function song_has_any_selected_fields_empty( $post_id, $fields ) {
		$row = $this->format_song_for_export( $post_id, $fields );
		foreach ( $fields as $key ) {
			if ( ! array_key_exists( $key, $row ) ) {
				continue;
			}
			$val = $row[ $key ];
			$is_empty = false;
			if ( is_array( $val ) ) {
				$is_empty = empty( $val );
			} else {
				$is_empty = ( $val === '' || $val === null );
			}
			if ( $is_empty ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Format a single song for export (only requested fields). Terms and relationships exported as slugs.
	 *
	 * @param int   $post_id Song post ID.
	 * @param array $fields  Keys to include (default all).
	 * @return array
	 */
	private function format_song_for_export( $post_id, $fields = array() ) {
		$config = $this->exportable_fields;
		$all    = array();
		$post   = get_post( $post_id );

		// Build full row (we'll filter by $fields after)
		$embeds = get_field( 'embeds', $post_id );
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

		$artist = get_field( 'artist', $post_id );
		$artist_slugs = array();
		if ( $artist ) {
			$artist = is_array( $artist ) ? $artist : array( $artist );
			foreach ( $artist as $a ) {
				$id = is_object( $a ) ? $a->ID : (int) $a;
				if ( $id ) {
					$slug = get_post( $id ) ? get_post( $id )->post_name : '';
					if ( $slug ) {
						$artist_slugs[] = $slug;
					}
				}
			}
		}

		$album = get_field( 'album', $post_id );
		$album_slug = '';
		if ( $album ) {
			$album_id = is_object( $album ) ? $album->ID : (int) $album;
			if ( $album_id ) {
				$album_post = get_post( $album_id );
				$album_slug = $album_post ? $album_post->post_name : '';
			}
		}

		$categories = wp_get_post_terms( $post_id, 'category' );
		$tags       = wp_get_post_terms( $post_id, 'post_tag' );
		$cat_slugs  = is_wp_error( $categories ) ? array() : wp_list_pluck( $categories, 'slug' );
		$tag_slugs  = is_wp_error( $tags ) ? array() : wp_list_pluck( $tags, 'slug' );

		$lyrics = get_field( 'lyrics', $post_id );
		$lyric_annotations = get_field( 'lyric_annotations', $post_id );
		$chord_sheet = get_field( 'chord_sheet', $post_id );
		$tabs = get_field( 'tabs', $post_id );
		$capo = get_field( 'capo', $post_id );
		$song_links = get_field( 'song_links', $post_id );
		$chords_source_url = get_field( 'chords_source_url', $post_id );

		$all['id']                 = (int) $post_id;
		$all['post_title']         = trim( (string) $post->post_title ) !== '' ? $post->post_title : '(No title)';
		$all['post_name']          = $post->post_name;
		$all['description']        = $post->post_content;
		$all['post_date']          = $post->post_date;
		$all['lyrics']             = $lyrics !== null && $lyrics !== false ? (string) $lyrics : '';
		$all['lyric_annotations']  = $lyric_annotations !== null && $lyric_annotations !== false ? (string) $lyric_annotations : '';
		$all['chord_sheet']        = $chord_sheet !== null && $chord_sheet !== false ? (string) $chord_sheet : '';
		$all['tabs']               = $tabs !== null && $tabs !== false ? (string) $tabs : '';
		$all['capo']               = is_numeric( $capo ) ? (int) $capo : '';
		$all['chords_source_url']  = $chords_source_url !== null && $chords_source_url !== false ? (string) $chords_source_url : '';
		$all['embeds']             = $embeds_export;
		$all['song_links']         = is_array( $song_links ) ? $song_links : array();
		$all['artist']             = $artist_slugs;
		$all['album']              = $album_slug;
		$all['categories']         = $cat_slugs;
		$all['tags']               = $tag_slugs;

		if ( empty( $fields ) ) {
			return $all;
		}
		return array_intersect_key( $all, array_flip( $fields ) );
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
	 * Update an existing song post from imported data. Only keys present in $data are updated.
	 * Taxonomy and relationship values can be slugs (resolved to term/post IDs) or IDs.
	 *
	 * @param int   $post_id Song post ID.
	 * @param array $data    Import row (any subset of exportable fields).
	 * @return true|WP_Error
	 */
	private function update_song_from_import_data( $post_id, $data ) {
		$post_updates = array( 'ID' => $post_id );

		if ( array_key_exists( 'post_title', $data ) ) {
			$post_updates['post_title'] = sanitize_text_field( $data['post_title'] );
		}
		if ( array_key_exists( 'description', $data ) ) {
			$post_updates['post_content'] = wp_kses_post( $data['description'] );
		}
		if ( array_key_exists( 'post_date', $data ) ) {
			$post_updates['post_date'] = sanitize_text_field( $data['post_date'] );
		}
		if ( array_key_exists( 'post_name', $data ) ) {
			$post_updates['post_name'] = sanitize_title( $data['post_name'] );
		}
		if ( count( $post_updates ) > 1 ) {
			wp_update_post( $post_updates );
		}

		if ( function_exists( 'update_field' ) ) {
			if ( array_key_exists( 'lyrics', $data ) ) {
				update_field( 'lyrics', $data['lyrics'], $post_id );
			}
			if ( array_key_exists( 'lyric_annotations', $data ) ) {
				update_field( 'lyric_annotations', $data['lyric_annotations'], $post_id );
			}
			if ( array_key_exists( 'chord_sheet', $data ) ) {
				update_field( 'chord_sheet', $data['chord_sheet'], $post_id );
			}
			if ( array_key_exists( 'tabs', $data ) ) {
				update_field( 'tabs', $data['tabs'], $post_id );
			}
			if ( array_key_exists( 'capo', $data ) ) {
				$capo = $data['capo'];
				if ( $capo === '' || ( is_numeric( $capo ) && (int) $capo >= 0 && (int) $capo <= 12 ) ) {
					update_field( 'capo', $capo === '' ? '' : (int) $capo, $post_id );
				}
			}
			if ( array_key_exists( 'chords_source_url', $data ) ) {
				update_field( 'chords_source_url', sanitize_url( $data['chords_source_url'] ), $post_id );
			}
			if ( array_key_exists( 'embeds', $data ) && is_array( $data['embeds'] ) ) {
				update_field( 'embeds', $data['embeds'], $post_id );
			}
			if ( array_key_exists( 'song_links', $data ) && is_array( $data['song_links'] ) ) {
				update_field( 'song_links', $data['song_links'], $post_id );
			}
			if ( array_key_exists( 'artist', $data ) ) {
				$artist_ids = $this->resolve_relationship_slugs_to_ids( $data['artist'], 'band' );
				update_field( 'artist', $artist_ids, $post_id );
			}
			if ( array_key_exists( 'album', $data ) ) {
				$album_id = $this->resolve_relationship_slug_or_id_to_id( $data['album'], 'album' );
				update_field( 'album', $album_id, $post_id );
			}
		}

		if ( array_key_exists( 'categories', $data ) && is_array( $data['categories'] ) ) {
			$term_ids = $this->resolve_term_slugs_to_ids( $data['categories'], 'category' );
			wp_set_post_terms( $post_id, $term_ids, 'category', false );
		}
		if ( array_key_exists( 'tags', $data ) && is_array( $data['tags'] ) ) {
			$term_ids = $this->resolve_term_slugs_to_ids( $data['tags'], 'post_tag' );
			wp_set_post_terms( $post_id, $term_ids, 'post_tag', false );
		}

		return true;
	}

	/**
	 * Resolve taxonomy slugs (or numeric IDs) to term IDs.
	 *
	 * @param array  $slugs_or_ids Array of slugs or term IDs.
	 * @param string $taxonomy     Taxonomy name.
	 * @return array Term IDs.
	 */
	private function resolve_term_slugs_to_ids( $slugs_or_ids, $taxonomy ) {
		$ids = array();
		foreach ( $slugs_or_ids as $item ) {
			if ( is_numeric( $item ) && (int) $item > 0 ) {
				$term = get_term( (int) $item, $taxonomy );
				if ( $term && ! is_wp_error( $term ) ) {
					$ids[] = $term->term_id;
				}
				continue;
			}
			$slug = is_string( $item ) ? $item : '';
			if ( $slug === '' ) {
				continue;
			}
			$term = get_term_by( 'slug', $slug, $taxonomy );
			if ( $term && ! is_wp_error( $term ) ) {
				$ids[] = $term->term_id;
			}
		}
		return $ids;
	}

	/**
	 * Resolve relationship value: array of slugs or IDs to post IDs (e.g. artist -> band).
	 *
	 * @param array|int|string $value     Array of slugs/IDs, or single slug/ID.
	 * @param string          $post_type Post type to resolve to.
	 * @return array Post IDs.
	 */
	private function resolve_relationship_slugs_to_ids( $value, $post_type ) {
		$list = is_array( $value ) ? $value : array( $value );
		$ids  = array();
		foreach ( $list as $item ) {
			if ( is_numeric( $item ) && (int) $item > 0 ) {
				$p = get_post( (int) $item );
				if ( $p && $p->post_type === $post_type ) {
					$ids[] = (int) $p->ID;
				}
				continue;
			}
			$slug = is_string( $item ) ? $item : '';
			if ( $slug === '' ) {
				continue;
			}
			$posts = get_posts( array(
				'post_type'      => $post_type,
				'name'           => $slug,
				'posts_per_page' => 1,
				'post_status'    => 'any',
				'fields'         => 'ids',
			) );
			if ( ! empty( $posts ) ) {
				$ids[] = (int) $posts[0];
			}
		}
		return $ids;
	}

	/**
	 * Resolve single relationship value (slug or ID) to post ID (e.g. album).
	 *
	 * @param int|string $value     Slug or post ID.
	 * @param string     $post_type Post type to resolve to.
	 * @return int|null Post ID or null if not found.
	 */
	private function resolve_relationship_slug_or_id_to_id( $value, $post_type ) {
		if ( empty( $value ) ) {
			return null;
		}
		if ( is_numeric( $value ) && (int) $value > 0 ) {
			$p = get_post( (int) $value );
			return ( $p && $p->post_type === $post_type ) ? (int) $p->ID : null;
		}
		$slug = is_string( $value ) ? $value : '';
		if ( $slug === '' ) {
			return null;
		}
		$posts = get_posts( array(
			'post_type'      => $post_type,
			'name'           => $slug,
			'posts_per_page' => 1,
			'post_status'    => 'any',
			'fields'         => 'ids',
		) );
		return ! empty( $posts ) ? (int) $posts[0] : null;
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
