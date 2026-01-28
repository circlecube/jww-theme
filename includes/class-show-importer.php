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
		add_action( 'wp_ajax_jww_get_setlist_sync_status', array( $this, 'ajax_get_setlist_sync_status' ) );
		add_action( 'wp_ajax_jww_save_api_key', array( $this, 'ajax_save_api_key' ) );
		add_action( 'wp_ajax_jww_run_sync_now', array( $this, 'ajax_run_sync_now' ) );
		
		// Schedule cron for automatic setlist sync
		add_action( 'wp_loaded', array( $this, 'maybe_schedule_cron' ) );
		add_action( 'sync_recent_shows_setlist', array( $this, 'sync_recent_shows_setlist' ) );
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
				<!-- API Key Management -->
				<div class="jww-importer-section">
					<h2>setlist.fm API Key</h2>
					<div class="api-key-management">
						<?php 
						// Check if key is from .env file (read-only)
						$env_key = $this->get_api_key_from_env();
						$is_env_key = (bool) $env_key;
						$display_key = $api_key ? $api_key : '';
						?>
						
						<?php if ( $is_env_key ): ?>
							<p class="note" style="background: #fff3cd; padding: 10px; border-left: 4px solid #ffc107; margin-bottom: 15px;">
								<strong>Note:</strong> API key is currently loaded from <code>.env</code> file. To manage it here, remove it from the .env file first.
							</p>
						<?php else: ?>
						
						<form id="api-key-form" class="jww-api-key-form">
							<p>
								<label for="api-key-input">API Key:</label><br>
								<input type="password" id="api-key-input" name="api_key" class="regular-text" 
									value="" 
									placeholder="<?php echo $is_env_key ? 'Set in .env file' : ( $api_key ? 'Enter new API key (leave blank to keep current)' : 'Enter setlist.fm API key' ); ?>"
									<?php echo $is_env_key ? 'disabled' : ''; ?>>
								<?php if ( $is_env_key ): ?>
									<p class="description">Key loaded from .env file (masked for security)</p>
								<?php elseif ( $api_key ): ?>
									<p class="description">Current key is set. Enter a new key to update it, or leave blank to keep current. Get your API key from <a href="https://www.setlist.fm/settings/api" target="_blank">setlist.fm API settings</a></p>
								<?php else: ?>
									<p class="description">Get your API key from <a href="https://www.setlist.fm/settings/api" target="_blank">setlist.fm API settings</a></p>
								<?php endif; ?>
							</p>
							<p>
								<button type="submit" class="button button-primary" <?php echo $is_env_key ? 'disabled' : ''; ?>>
									<?php echo $api_key ? 'Update API Key' : 'Save API Key'; ?>
								</button>
								<?php if ( $api_key && ! $is_env_key ): ?>
									<button type="button" class="button" id="clear-api-key-btn">Clear API Key</button>
								<?php endif; ?>
								<span class="spinner"></span>
							</p>
							<div class="api-key-result"></div>
						</form>
						<?php endif; ?>

						<div class="api-status" style="margin-top: 20px;">
							<?php if ( $api_key ): ?>
								<p class="api-key-status">
									<span class="status-indicator status-ok"></span>
									API Key: <code><?php echo esc_html( substr( $display_key, 0, 8 ) ); ?>...</code>
									<?php if ( $is_env_key ): ?>
										<span style="color: #646970; font-size: 0.9em;">(from .env file)</span>
									<?php endif; ?>
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
									No API key set.
								</p>
								<p class="note">You can still import from JSON files without an API key.</p>
							<?php endif; ?>
						</div>
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

				<!-- Setlist Sync Cron Status -->
				<div class="jww-importer-section">
					<h2>Automatic Setlist Sync</h2>
					<p class="description">Shows are automatically synced with setlist.fm twice daily when they are published. The cron checks for shows published since the last check and syncs them if they have a setlist.fm URL.</p>
					<div id="setlist-sync-status">
						<p>Loading status...</p>
					</div>
					<div style="margin-top: 15px; display: flex; gap: 10px;">
						<p>
							<button type="button" class="button" id="run-sync-now-btn">Run Sync Now</button>
							<span class="spinner" id="sync-now-spinner"></span>
						</p>
						<div id="sync-now-result"></div>
					</div>
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
	 * Get API key from .env file, WordPress options, or ACF options
	 *
	 * Priority: .env file > WordPress options > ACF options
	 *
	 * @return string|false API key or false if not set
	 */
	private function get_api_key() {
		// First, try to get from .env file
		$env_key = $this->get_api_key_from_env();
		if ( $env_key ) {
			return $env_key;
		}

		// Try WordPress options (new storage location)
		$option_key = get_option( 'jww_setlist_fm_api_key', false );
		if ( $option_key ) {
			return $option_key;
		}

		// Fallback to ACF options (for backward compatibility)
		if ( function_exists( 'get_field' ) ) {
			$acf_key = get_field( 'setlist_fm_api_key', 'option' );
			if ( $acf_key ) {
				// Migrate to WordPress options for consistency
				update_option( 'jww_setlist_fm_api_key', $acf_key );
				return $acf_key;
			}
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
			if ( function_exists( 'jww_clear_song_stats_caches' ) ) {
				jww_clear_song_stats_caches();
			} else {
				if ( function_exists( 'jww_clear_song_stats_caches' ) ) {
				jww_clear_song_stats_caches();
			} else {
				delete_transient( 'jww_all_time_song_stats' );
			}
			}
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
			if ( function_exists( 'jww_clear_song_stats_caches' ) ) {
				jww_clear_song_stats_caches();
			} else {
				delete_transient( 'jww_all_time_song_stats' );
			}
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
	 * Schedule cron job for automatic setlist sync
	 * Runs twice daily to check for recently published shows
	 */
	public function maybe_schedule_cron() {
		$hook = 'sync_recent_shows_setlist';
		
		// Check if already scheduled
		if ( ! wp_next_scheduled( $hook ) ) {
			// Schedule to run twice daily (every 12 hours)
			wp_schedule_event( time(), 'twicedaily', $hook );
		}
	}
	
	/**
	 * Sync recently published shows with setlist.fm
	 * Called by cron hook or manually via AJAX
	 * 
	 * @return array Results array with synced_count, error_count, checked_count
	 */
	public function sync_recent_shows_setlist() {
		// Get last sync timestamp (stored as option)
		$last_sync = get_option( 'jww_setlist_sync_last_check', 0 );
		$current_time = current_time( 'timestamp' );
		
		// If first run (last_sync is 0), only check shows from last 7 days to avoid syncing everything
		$check_since = $last_sync;
		if ( $last_sync === 0 ) {
			$check_since = $current_time - ( 7 * DAY_IN_SECONDS );
		}
		
		// Find shows published since last check
		$args = array(
			'post_type'      => 'show',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'date_query'     => array(
				array(
					'after' => date( 'Y-m-d H:i:s', $check_since ),
				),
			),
			'meta_query'     => array(
				array(
					'key'     => 'setlist_fm_url',
					'compare' => 'EXISTS',
				),
			),
		);
		
		$shows = get_posts( $args );
		$checked_count = count( $shows );
		
		if ( empty( $shows ) ) {
			// No new shows, update last check time and exit
			update_option( 'jww_setlist_sync_last_check', $current_time );
			return array(
				'synced_count' => 0,
				'error_count'  => 0,
				'checked_count' => 0,
			);
		}
		
		$importer = new Setlist_Importer();
		$synced_count = 0;
		$error_count = 0;
		
		foreach ( $shows as $show ) {
			$setlist_fm_url = get_field( 'setlist_fm_url', $show->ID );
			
			if ( empty( $setlist_fm_url ) ) {
				continue; // Skip shows without setlist.fm URL
			}
			
			// Sync this show
			$result = $importer->import_from_url( $setlist_fm_url );
			
			if ( is_wp_error( $result ) ) {
				$error_count++;
				error_log( 'Setlist sync cron error for show ' . $show->ID . ': ' . $result->get_error_message() );
			} else {
				$synced_count++;
			}
		}
		
		// Update last check time
		update_option( 'jww_setlist_sync_last_check', $current_time );
		
		// Clear song stats cache if any shows were synced
		if ( $synced_count > 0 ) {
			if ( function_exists( 'jww_clear_song_stats_caches' ) ) {
				jww_clear_song_stats_caches();
			}
		}
		
		// Log results
		if ( $synced_count > 0 || $error_count > 0 ) {
			error_log( sprintf( 
				'Setlist sync cron: %d shows synced, %d errors',
				$synced_count,
				$error_count
			) );
		}
		
		return array(
			'synced_count' => $synced_count,
			'error_count'  => $error_count,
			'checked_count' => $checked_count,
		);
	}
	
	/**
	 * AJAX: Get setlist sync cron status
	 */
	public function ajax_get_setlist_sync_status() {
		check_ajax_referer( 'jww_show_importer', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}
		
		$hook = 'sync_recent_shows_setlist';
		$next_scheduled = wp_next_scheduled( $hook );
		$current_schedule = wp_get_schedule( $hook );
		$last_sync = get_option( 'jww_setlist_sync_last_check', 0 );
		
		wp_send_json_success( array(
			'next_run' => $next_scheduled ? date( 'Y-m-d H:i:s', $next_scheduled ) : 'Not scheduled',
			'next_run_relative' => $next_scheduled ? human_time_diff( time(), $next_scheduled ) : 'N/A',
			'current_schedule' => $current_schedule ?: 'None',
			'last_sync' => $last_sync ? date( 'Y-m-d H:i:s', $last_sync ) : 'Never',
			'last_sync_relative' => $last_sync ? human_time_diff( $last_sync, time() ) . ' ago' : 'Never',
			'cron_healthy' => (bool) $next_scheduled,
		) );
	}
	
	/**
	 * AJAX: Run setlist sync now (manual trigger)
	 */
	public function ajax_run_sync_now() {
		check_ajax_referer( 'jww_show_importer', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		// Run the sync function
		$results = $this->sync_recent_shows_setlist();
		
		// Get updated status
		$last_sync = get_option( 'jww_setlist_sync_last_check', 0 );
		
		// Build message
		$message = 'Sync completed. ';
		if ( $results['checked_count'] === 0 ) {
			$message .= 'No shows found to sync.';
		} else {
			$message .= sprintf( 
				'Checked %d show(s), %d synced successfully',
				$results['checked_count'],
				$results['synced_count']
			);
			if ( $results['error_count'] > 0 ) {
				$message .= sprintf( ', %d error(s)', $results['error_count'] );
			}
		}
		
		wp_send_json_success( array(
			'message' => $message,
			'last_sync' => $last_sync ? date( 'Y-m-d H:i:s', $last_sync ) : 'Never',
			'last_sync_relative' => $last_sync ? human_time_diff( $last_sync, time() ) . ' ago' : 'Never',
			'results' => $results,
		) );
	}
	
	/**
	 * AJAX: Save API key
	 */
	public function ajax_save_api_key() {
		check_ajax_referer( 'jww_show_importer', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$api_key = isset( $_POST['api_key'] ) ? trim( $_POST['api_key'] ) : '';
		$action = isset( $_POST['action_type'] ) ? $_POST['action_type'] : 'save';

		// Handle clear action
		if ( $action === 'clear' ) {
			delete_option( 'jww_setlist_fm_api_key' );
			if ( function_exists( 'update_field' ) ) {
				update_field( 'setlist_fm_api_key', '', 'option' );
			} else {
				delete_option( 'options_setlist_fm_api_key' );
			}
			wp_send_json_success( array( 
				'message' => 'API key cleared successfully',
			) );
		}

		if ( empty( $api_key ) ) {
			wp_send_json_error( array( 'message' => 'API key cannot be empty' ) );
		}

		// Save to WordPress options
		update_option( 'jww_setlist_fm_api_key', $api_key );

		// Also update ACF option for backward compatibility
		if ( function_exists( 'update_field' ) ) {
			update_field( 'setlist_fm_api_key', $api_key, 'option' );
		} else {
			update_option( 'options_setlist_fm_api_key', $api_key );
		}

		wp_send_json_success( array( 
			'message' => 'API key saved successfully',
			'key_preview' => substr( $api_key, 0, 8 ) . '...',
		) );
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
