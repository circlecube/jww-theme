<?php
/**
 * Class Show_Importer
 *
 * Admin interface for importing and exporting show data.
 * Provides UI for setlist.fm imports and JSON file imports/exports.
 *
 * @package JWW_Theme
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Show_Importer {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'wp_ajax_jww_import_setlist', array( $this, 'ajax_import_setlist' ) );
		add_action( 'wp_ajax_jww_import_json', array( $this, 'ajax_import_json' ) );
		add_action( 'wp_ajax_jww_export_shows', array( $this, 'ajax_export_shows' ) );
		add_action( 'wp_ajax_jww_test_api', array( $this, 'ajax_test_api' ) );
	}

	/**
	 * Add admin page
	 */
	public function add_admin_page() {
		add_submenu_page(
			'edit.php?post_type=show', // Parent: Show post type menu
			'Show Importer',
			'Importer',
			'manage_options',
			'show-importer',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin scripts
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( $hook !== 'show_page_show-importer' ) {
			return;
		}

		wp_enqueue_script(
			'jww-show-importer',
			get_stylesheet_directory_uri() . '/includes/js/show-importer.js',
			array( 'jquery' ),
			wp_get_theme()->get( 'Version' ),
			true
		);

		wp_localize_script( 'jww-show-importer', 'jwwShowImporter', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'jww_show_importer' ),
		) );

		wp_enqueue_style(
			'jww-show-importer',
			get_stylesheet_directory_uri() . '/includes/css/show-importer.css',
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

		$api_key = $this->get_api_key();
		$api_status = $this->check_api_status();
		?>
		<div class="wrap">
			<h1>Show Importer</h1>
			<p>Import shows from setlist.fm or JSON files. Export shows to JSON format.</p>

			<div class="jww-importer-container">
				<!-- API Status -->
				<div class="jww-importer-section">
					<h2>API Status</h2>
					<div class="api-status">
						<?php if ( $api_key ): ?>
							<p class="api-key-status">
								<span class="status-indicator status-ok"></span>
								API Key: <code><?php echo esc_html( substr( $api_key, 0, 8 ) ); ?>...</code>
							</p>
							<?php if ( $api_status['working'] ): ?>
								<p class="api-connection status-ok">
									<span class="status-indicator status-ok"></span>
									API Connection: Working
								</p>
							<?php else: ?>
								<p class="api-connection status-error">
									<span class="status-indicator status-error"></span>
									API Connection: <?php echo esc_html( $api_status['message'] ); ?>
								</p>
							<?php endif; ?>
							<button type="button" class="button" id="test-api-btn">Test API Connection</button>
						<?php else: ?>
							<p class="api-key-status status-error">
								<span class="status-indicator status-error"></span>
								No API key set. <a href="<?php echo esc_url( admin_url( 'admin.php?page=acf-options-youtube-import-settings' ) ); ?>">Set API key in Song settings</a>
							</p>
							<p class="note">You can still import from JSON files without an API key.</p>
						<?php endif; ?>
					</div>
				</div>

				<!-- Import from setlist.fm URL -->
				<div class="jww-importer-section">
					<h2>Import from setlist.fm</h2>
					<form id="import-setlist-form" class="jww-import-form">
						<p>
							<label for="setlist-url">setlist.fm URL:</label><br>
							<input type="url" id="setlist-url" name="setlist_url" class="regular-text" 
								placeholder="https://www.setlist.fm/setlist/jesse-welles/2026/..." required>
						</p>
						<p>
							<button type="submit" class="button button-primary">Import Show</button>
							<span class="spinner"></span>
						</p>
						<div class="import-result"></div>
					</form>
				</div>

				<!-- Import from JSON File -->
				<div class="jww-importer-section">
					<h2>Import from JSON File</h2>
					<p class="description">Import multiple shows from an exported JSON file. Supports arrays of shows in setlist.fm API format.</p>
					<form id="import-json-form" class="jww-import-form" enctype="multipart/form-data">
						<p>
							<label for="json-file">JSON File:</label><br>
							<input type="file" id="json-file" name="json_file" accept=".json,application/json" required>
							<p class="description">Upload a JSON file containing one or more shows in setlist.fm API format.</p>
						</p>
						<p>
							<button type="submit" class="button button-primary">Import Shows from JSON</button>
							<span class="spinner"></span>
						</p>
						<div class="import-result"></div>
					</form>
				</div>

				<!-- Export Shows -->
				<div class="jww-importer-section">
					<h2>Export Shows</h2>
					<form id="export-shows-form" class="jww-export-form">
						<p>
							<label for="export-tour">Filter by Tour (optional):</label><br>
							<select id="export-tour" name="tour_id">
								<option value="">All Tours</option>
								<?php
								$tours = get_terms( array(
									'taxonomy'   => 'tour',
									'hide_empty' => false,
								) );
								if ( ! is_wp_error( $tours ) && ! empty( $tours ) ) {
									foreach ( $tours as $tour ) {
										echo '<option value="' . esc_attr( $tour->term_id ) . '">' . esc_html( $tour->name ) . '</option>';
									}
								}
								?>
							</select>
						</p>
						<p>
							<button type="submit" class="button button-primary">Export Shows to JSON</button>
							<span class="spinner"></span>
						</p>
						<div class="export-result"></div>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Get API key from .env file or ACF options
	 *
	 * Priority: .env file > ACF options
	 *
	 * @return string|false API key or false if not set
	 */
	private function get_api_key() {
		// First, try to get from .env file
		$env_key = $this->get_api_key_from_env();
		if ( $env_key ) {
			return $env_key;
		}

		// Fallback to ACF options
		if ( function_exists( 'get_field' ) ) {
			return get_field( 'setlist_fm_api_key', 'option' );
		}
		return get_option( 'options_setlist_fm_api_key', false );
	}

	/**
	 * Get API key from .env file
	 *
	 * @return string|false API key or false if not found
	 */
	private function get_api_key_from_env() {
		$env_file = get_stylesheet_directory() . '/.env';
		
		// Check if .env file exists
		if ( ! file_exists( $env_file ) || ! is_readable( $env_file ) ) {
			return false;
		}

		// Read .env file
		$env_content = file_get_contents( $env_file );
		if ( $env_content === false ) {
			return false;
		}

		// Parse .env file for SETLIST_FM_API_KEY
		$lines = explode( "\n", $env_content );
		foreach ( $lines as $line ) {
			$line = trim( $line );
			
			// Skip empty lines and comments
			if ( empty( $line ) || $line[0] === '#' ) {
				continue;
			}

			// Check if this line contains SETLIST_FM_API_KEY
			if ( preg_match( '/^SETLIST_FM_API_KEY\s*=\s*(.+)$/i', $line, $matches ) ) {
				$key = trim( $matches[1] );
				// Remove quotes if present
				$key = trim( $key, '"\'`' );
				return $key;
			}
		}

		return false;
	}

	/**
	 * Check API status
	 */
	private function check_api_status() {
		$api_key = $this->get_api_key();
		if ( ! $api_key ) {
			return array( 'working' => false, 'message' => 'No API key' );
		}

		// Test with a simple API call (search for artist)
		$url = 'https://api.setlist.fm/rest/1.0/search/artists?artistName=Jesse%20Welles&p=1';
		$response = wp_remote_get( $url, array(
			'headers' => array(
				'Accept'    => 'application/json',
				'x-api-key' => $api_key,
			),
			'timeout' => 10,
		) );

		if ( is_wp_error( $response ) ) {
			return array( 'working' => false, 'message' => $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code === 200 ) {
			return array( 'working' => true, 'message' => 'Connected' );
		}

		return array( 'working' => false, 'message' => 'HTTP ' . $status_code );
	}

	/**
	 * AJAX: Import from setlist.fm URL
	 */
	public function ajax_import_setlist() {
		check_ajax_referer( 'jww_show_importer', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$url = isset( $_POST['setlist_url'] ) ? esc_url_raw( $_POST['setlist_url'] ) : '';
		if ( empty( $url ) ) {
			wp_send_json_error( array( 'message' => 'URL is required' ) );
		}

		// Validate URL format
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			wp_send_json_error( array( 'message' => 'Invalid URL format' ) );
		}

		// Validate it's a setlist.fm URL
		if ( strpos( $url, 'setlist.fm' ) === false ) {
			wp_send_json_error( array( 'message' => 'URL must be from setlist.fm' ) );
		}

		$importer = new Setlist_Importer();
		$result = $importer->import_from_url( $url );
		
		// Clear statistics cache when show is imported
		if ( ! is_wp_error( $result ) ) {
			delete_transient( 'jww_all_time_song_stats' );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$message = isset( $result['updated'] ) && $result['updated'] 
			? 'Show updated successfully' 
			: 'Show imported successfully';

		wp_send_json_success( array(
			'message' => $message,
			'show_id' => $result['show_id'],
			'edit_link' => admin_url( 'post.php?post=' . $result['show_id'] . '&action=edit' ),
		) );
	}

	/**
	 * AJAX: Import from JSON (supports single show or array of shows)
	 */
	public function ajax_import_json() {
		check_ajax_referer( 'jww_show_importer', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		// Handle file upload
		$json_data = '';
		if ( ! empty( $_FILES['json_file']['tmp_name'] ) ) {
			// Check for upload errors
			if ( $_FILES['json_file']['error'] !== UPLOAD_ERR_OK ) {
				wp_send_json_error( array( 'message' => 'File upload error: ' . $this->get_upload_error_message( $_FILES['json_file']['error'] ) ) );
			}

			// Validate file type - check extension and MIME type
			$file_name = $_FILES['json_file']['name'];
			$file_type = wp_check_filetype( $file_name );
			$file_ext = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );
			$mime_type = isset( $_FILES['json_file']['type'] ) ? $_FILES['json_file']['type'] : '';
			
			// Accept .json extension or application/json MIME type
			$is_valid_json = (
				$file_ext === 'json' ||
				$file_type['ext'] === 'json' ||
				$mime_type === 'application/json' ||
				$mime_type === 'text/json' ||
				strpos( $mime_type, 'json' ) !== false
			);
			
			if ( ! $is_valid_json ) {
				wp_send_json_error( array( 'message' => 'Invalid file type. Please upload a JSON file.' ) );
			}

			// Read file contents
			$json_data = file_get_contents( $_FILES['json_file']['tmp_name'] );
			if ( $json_data === false ) {
				wp_send_json_error( array( 'message' => 'Failed to read uploaded file.' ) );
			}
			
			// Additional validation: try to parse JSON to ensure it's valid
			$test_parse = json_decode( $json_data, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				wp_send_json_error( array( 'message' => 'File does not contain valid JSON: ' . json_last_error_msg() ) );
			}
		} elseif ( isset( $_POST['json_data'] ) ) {
			// Fallback to textarea input (for backward compatibility)
			$json_data = wp_unslash( $_POST['json_data'] );
		}

		if ( empty( $json_data ) ) {
			wp_send_json_error( array( 'message' => 'JSON data is required. Please upload a file or paste JSON data.' ) );
		}

		$data = json_decode( $json_data, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			wp_send_json_error( array( 'message' => 'Invalid JSON: ' . json_last_error_msg() ) );
		}

		// Determine if this is a single show or array of shows
		$is_array = isset( $data[0] ) && is_array( $data[0] );
		
		if ( ! $is_array ) {
			// Single show - wrap in array for consistent processing
			$shows = array( $data );
		} else {
			// Array of shows
			$shows = $data;
		}

		$importer = new Setlist_Importer();
		$results = array(
			'imported' => 0,
			'updated' => 0,
			'errors' => 0,
			'messages' => array(),
		);

		// Process each show
		foreach ( $shows as $index => $show_data ) {
			// Validate required fields for this show
			if ( empty( $show_data['eventDate'] ) ) {
				$results['errors']++;
				$results['messages'][] = 'Show #' . ( $index + 1 ) . ': Missing required field: eventDate';
				continue;
			}

			if ( empty( $show_data['venue']['name'] ) ) {
				$results['errors']++;
				$results['messages'][] = 'Show #' . ( $index + 1 ) . ': Missing required field: venue.name';
				continue;
			}

			$result = $importer->import_from_json( $show_data );
			
			if ( is_wp_error( $result ) ) {
				$results['errors']++;
				$results['messages'][] = 'Show #' . ( $index + 1 ) . ' (' . ( $show_data['eventDate'] ?? 'unknown date' ) . '): ' . $result->get_error_message();
			} else {
				if ( isset( $result['updated'] ) && $result['updated'] ) {
					$results['updated']++;
					$results['messages'][] = 'Show #' . ( $index + 1 ) . ': Updated successfully (ID: ' . $result['show_id'] . ')';
				} else {
					$results['imported']++;
					$results['messages'][] = 'Show #' . ( $index + 1 ) . ': Imported successfully (ID: ' . $result['show_id'] . ')';
				}
			}
		}

		// Clear statistics cache when shows are imported
		if ( $results['imported'] > 0 || $results['updated'] > 0 ) {
			delete_transient( 'jww_all_time_song_stats' );
		}

		// Build summary message
		$total = count( $shows );
		$success_count = $results['imported'] + $results['updated'];
		$message = sprintf(
			'Processed %d show(s): %d imported, %d updated, %d errors',
			$total,
			$results['imported'],
			$results['updated'],
			$results['errors']
		);

		wp_send_json_success( array(
			'message' => $message,
			'results' => $results,
			'total' => $total,
		) );
	}

	/**
	 * Get upload error message
	 */
	private function get_upload_error_message( $error_code ) {
		$error_messages = array(
			UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize directive',
			UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE directive',
			UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded',
			UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
			UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
			UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
			UPLOAD_ERR_EXTENSION  => 'File upload stopped by extension',
		);

		return isset( $error_messages[ $error_code ] ) 
			? $error_messages[ $error_code ] 
			: 'Unknown upload error (code: ' . $error_code . ')';
	}

	/**
	 * AJAX: Export shows
	 */
	public function ajax_export_shows() {
		check_ajax_referer( 'jww_show_importer', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$tour_id = isset( $_POST['tour_id'] ) ? intval( $_POST['tour_id'] ) : 0;

		$args = array(
			'post_type'      => 'show',
			'posts_per_page' => -1,
			'post_status'    => array( 'publish', 'future' ),
			'orderby'        => 'date',
			'order'          => 'ASC',
		);

		if ( $tour_id ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'tour',
					'field'    => 'term_id',
					'terms'    => $tour_id,
				),
			);
		}

		$shows = get_posts( $args );
		$export_data = array();

		foreach ( $shows as $show ) {
			$export_data[] = $this->format_show_for_export( $show->ID );
		}

		wp_send_json_success( array(
			'data' => $export_data,
			'count' => count( $export_data ),
		) );
	}

	/**
	 * Format show for export (setlist.fm API format)
	 */
	private function format_show_for_export( $show_id ) {
		$show = get_post( $show_id );
		$location_id = get_field( 'show_location', $show_id );
		$tour_id = get_field( 'show_tour', $show_id );
		$setlist = get_field( 'setlist', $show_id );
		$show_notes = get_field( 'show_notes', $show_id );
		$setlist_fm_url = get_field( 'setlist_fm_url', $show_id );

		// Get location hierarchy
		$venue_data = array();
		if ( $location_id ) {
			$location_term = get_term( $location_id, 'location' );
			if ( $location_term && ! is_wp_error( $location_term ) ) {
				$venue_data['name'] = $location_term->name;
				$venue_data['id'] = (string) $location_term->term_id;

				// Get venue address if set
				$venue_address = get_field( 'address', 'location_' . $location_id );
				if ( $venue_address ) {
					$venue_data['address'] = $venue_address;
				}

				// Get parent (city)
				if ( $location_term->parent ) {
					$city_term = get_term( $location_term->parent, 'location' );
					if ( $city_term && ! is_wp_error( $city_term ) ) {
						$venue_data['city'] = array(
							'name' => $city_term->name,
							'id' => (string) $city_term->term_id,
						);

						// Get grandparent (country)
						if ( $city_term->parent ) {
							$country_term = get_term( $city_term->parent, 'location' );
							if ( $country_term && ! is_wp_error( $country_term ) ) {
								$venue_data['city']['country'] = array(
									'name' => $country_term->name,
									'code' => '',
								);
							}
						}
					}
				}
			}
		}

		// Format setlist
		$sets = array();
		$current_set = array( 'song' => array() );
		foreach ( $setlist as $item ) {
			if ( $item['entry_type'] === 'note' ) {
				// Start new set
				if ( ! empty( $current_set['song'] ) ) {
					$sets[] = $current_set;
				}
				$current_set = array( 'song' => array() );
				// Only add name if notes are not empty
				if ( ! empty( $item['notes'] ) ) {
					$current_set['name'] = $item['notes'];
				}
			} else {
				$song_data = array( 'name' => '' );
				$cover_artist = '';
				
				if ( $item['entry_type'] === 'song-post' && ! empty( $item['song'] ) ) {
					$song = is_array( $item['song'] ) ? $item['song'][0] : $item['song'];
					$song_id = is_object( $song ) ? $song->ID : $song;
					$song_data['name'] = get_the_title( $song_id );
					
					// Check if song has attribution (cover song)
					$attribution = get_field( 'attribution', $song_id );
					if ( ! empty( $attribution ) ) {
						$cover_artist = $attribution;
					}
				} elseif ( $item['entry_type'] === 'song-text' && ! empty( $item['song_text'] ) ) {
					$song_data['name'] = $item['song_text'];
				}

				// Check notes for cover information
				$notes = $item['notes'] ?? '';
				if ( ! empty( $notes ) ) {
					// Check if notes start with "Cover: " (from import)
					if ( preg_match( '/^Cover:\s*(.+?)(?:\s*-\s*|$)/i', $notes, $matches ) ) {
						$cover_artist = trim( $matches[1] );
						// Remove "Cover: [artist]" from notes, keep the rest
						$notes = preg_replace( '/^Cover:\s*.+?(\s*-\s*|$)/i', '', $notes );
						$notes = trim( $notes );
					}
					
					// Only add info if there's something left after removing cover info
					if ( ! empty( $notes ) ) {
						$song_data['info'] = $notes;
					}
				}

				// Add cover object if we have cover artist
				if ( ! empty( $cover_artist ) ) {
					$song_data['cover'] = array(
						'name' => $cover_artist,
					);
				}

				if ( ! empty( $song_data['name'] ) ) {
					$current_set['song'][] = $song_data;
				}
			}
		}
		if ( ! empty( $current_set['song'] ) ) {
			$sets[] = $current_set;
		}

		// Format date: DD-MM-YYYY
		$date = get_the_date( 'd-m-Y', $show_id );

		return array(
			'artist' => array(
				'name' => 'Jesse Welles',
			),
			'venue' => $venue_data,
			'tour' => $tour_id ? array(
				'name' => get_term( $tour_id, 'tour' )->name,
			) : null,
			'set' => $sets, // setlist.fm API format uses 'set' directly, not 'sets.set'
			'eventDate' => $date,
			'info' => $show_notes ?: '',
			'url' => $setlist_fm_url ?: get_permalink( $show_id ),
		);
	}

	/**
	 * AJAX: Test API connection
	 */
	public function ajax_test_api() {
		check_ajax_referer( 'jww_show_importer', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$status = $this->check_api_status();
		if ( $status['working'] ) {
			wp_send_json_success( array( 'message' => 'API connection successful' ) );
		} else {
			wp_send_json_error( array( 'message' => $status['message'] ) );
		}
	}
}

// Initialize
new Show_Importer();
