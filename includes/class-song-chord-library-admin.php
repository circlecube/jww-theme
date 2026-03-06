<?php
/**
 * Chord Library Admin
 *
 * Admin subpage under Songs: view full chord library, scan all songs for chord usage,
 * and see a grid of required chords (with diagrams where available, blanks for missing).
 * Supports filtering to show only missing chords.
 *
 * @package JWW_Theme
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Song_Chord_Library_Admin
 */
class Song_Chord_Library_Admin {

	const CHROMATIC_SHARPS = array( 'C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B' );
	const CHROMATIC_FLATS  = array( 'C', 'Db', 'D', 'Eb', 'E', 'F', 'Gb', 'G', 'Ab', 'A', 'Bb', 'B' );
	const OPTION_CUSTOM_CHORDS = 'jww_chord_library_custom';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_jww_save_custom_chord_library', array( $this, 'ajax_save_custom_chord_library' ) );
	}

	/**
	 * Add submenu under Songs.
	 */
	public function add_admin_page() {
		add_submenu_page(
			'edit.php?post_type=song',
			__( 'Chord Library', 'jww-theme' ),
			__( 'Chord Library', 'jww-theme' ),
			'manage_options',
			'jww-chord-library',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue script and style only on our admin page.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		if ( $hook !== 'song_page_jww-chord-library' ) {
			return;
		}

		$theme_dir = get_stylesheet_directory();
		$theme_uri = get_stylesheet_directory_uri();

		$script_path = $theme_dir . '/build/admin-chord-library.js';
		$script_ver  = file_exists( $script_path ) ? (string) filemtime( $script_path ) : wp_get_theme()->get( 'Version' );

		wp_enqueue_script(
			'jww-admin-chord-library',
			$theme_uri . '/build/admin-chord-library.js',
			array(),
			$script_ver,
			true
		);

		$library    = $this->get_chord_library();
		$required   = $this->get_required_chords();
		$missing    = $this->get_missing_chords( $library, $required );
		$required   = array_keys( $required );
		sort( $required );

		wp_localize_script( 'jww-admin-chord-library', 'jwwChordLibraryAdmin', array(
			'chordLibrary'   => $library,
			'requiredChords' => $required,
			'missingChords'  => array_values( $missing ),
			'saveCustomChordsNonce' => wp_create_nonce( 'jww_save_custom_chord_library' ),
			'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
		) );

		wp_enqueue_style(
			'jww-admin-chord-library',
			$theme_uri . '/admin/css/chord-library.css',
			array(),
			wp_get_theme()->get( 'Version' )
		);
	}

	/**
	 * Load chord library from inc/chord-library.json (no custom overlay).
	 *
	 * @return array Associative array chord name => shape data.
	 */
	public static function get_chord_library_from_file() {
		$path = get_stylesheet_directory() . '/inc/chord-library.json';
		if ( ! file_exists( $path ) ) {
			return array();
		}
		$json = file_get_contents( $path );
		if ( $json === false ) {
			return array();
		}
		$data = json_decode( $json, true );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Load custom chord library from database (pastable JSON, no theme update needed).
	 *
	 * @return array Associative array chord name => shape data. Empty if none or invalid.
	 */
	public static function get_custom_chord_library() {
		$raw = get_option( self::OPTION_CUSTOM_CHORDS, '' );
		if ( ! is_string( $raw ) || trim( $raw ) === '' ) {
			return array();
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return array();
		}
		// Keep only entries that look like chord shapes (have 'chord' key and are array).
		$out = array();
		foreach ( $data as $key => $val ) {
			if ( ! is_array( $val ) || ! isset( $val['chord'] ) || ! is_array( $val['chord'] ) ) {
				continue;
			}
			$out[ (string) $key ] = $val;
		}
		return $out;
	}

	/**
	 * Get merged chord library: file first, then custom chords overlay (custom overrides file per key).
	 * Unique entries by chord key; database custom chords take precedence over JSON file.
	 *
	 * @return array Associative array chord name => shape data.
	 */
	public static function get_merged_chord_library() {
		$from_file = self::get_chord_library_from_file();
		$custom    = self::get_custom_chord_library();
		foreach ( $custom as $key => $shape ) {
			$from_file[ $key ] = $shape;
		}
		return $from_file;
	}

	/**
	 * Get chord library (merged file + custom). Instance wrapper for backward compatibility.
	 *
	 * @return array Associative array chord name => shape data.
	 */
	public function get_chord_library() {
		return self::get_merged_chord_library();
	}

	/**
	 * Extract chord names from chord sheet text (ChordPro [C] and common inline patterns).
	 *
	 * @param string $text Chord sheet content.
	 * @return array Unique chord names (roots + suffixes).
	 */
	public function extract_chord_names_from_text( $text ) {
		$found = array();

		if ( ! is_string( $text ) || $text === '' ) {
			return $found;
		}

		// ChordPro: [C], [Am7], [F#m], etc.
		if ( preg_match_all( '/\[([A-Ga-g][#b]?[^\]]*)\]/', $text, $m ) ) {
			foreach ( $m[1] as $chord ) {
				$chord = trim( $chord );
				if ( $chord !== '' && preg_match( '/^[A-Ga-g][#b]?/', $chord ) ) {
					$found[ $this->normalize_chord_key( $chord ) ] = true;
				}
			}
		}

		// Inline chord-like tokens (suffixes: m, min, maj, 7, m7, dim, aug, sus, etc.)
		if ( preg_match_all( '/\b([A-G][#b]?(?:m(?:aj|in)?|dim|aug|sus(?:2|4)?|add\d+|b5|#5|\d+|m7|maj7|min7|dim7|aug7|7|9|11|13)?)\b/i', $text, $m2 ) ) {
			foreach ( $m2[1] as $chord ) {
				$chord = trim( $chord );
				if ( $chord !== '' ) {
					$found[ $this->normalize_chord_key( $chord ) ] = true;
				}
			}
		}

		return array_keys( $found );
	}

	/**
	 * Normalize chord name for use as array key (consistent casing).
	 *
	 * @param string $name Chord name (e.g. am7, F#).
	 * @return string Normalized (e.g. Am7, F#).
	 */
	private function normalize_chord_key( $name ) {
		$name = trim( $name );
		if ( $name === '' ) {
			return $name;
		}
		// First character uppercase, rest as-is (so Am7 not AM7).
		$first = mb_substr( $name, 0, 1 );
		$rest  = mb_substr( $name, 1 );
		return mb_strtoupper( $first, 'UTF-8' ) . $rest;
	}

	/**
	 * Get root index 0–11 from chord name (root only).
	 *
	 * @param string $chord_name Full chord name (e.g. F#m7).
	 * @return int|null Index 0–11 or null if invalid.
	 */
	private function root_index( $chord_name ) {
		$m = array();
		if ( ! preg_match( '/^([A-Ga-g][#b]?)/', $chord_name, $m ) ) {
			return null;
		}
		$root = $m[1];
		$root = mb_strtoupper( mb_substr( $root, 0, 1 ), 'UTF-8' ) . ( mb_strlen( $root ) > 1 ? mb_substr( $root, 1 ) : '' );
		foreach ( self::CHROMATIC_SHARPS as $i => $r ) {
			if ( $r === $root ) {
				return $i;
			}
		}
		foreach ( self::CHROMATIC_FLATS as $i => $r ) {
			if ( $r === $root ) {
				return $i;
			}
		}
		return null;
	}

	/**
	 * Get suffix (everything after the root) from chord name.
	 *
	 * @param string $chord_name Full chord name.
	 * @return string Suffix (e.g. m7, 7, '').
	 */
	private function chord_suffix( $chord_name ) {
		$m = array();
		if ( preg_match( '/^[A-Ga-g][#b]?(.*)$/', $chord_name, $m ) ) {
			return $m[1];
		}
		return '';
	}

	/**
	 * Transpose a chord name by N semitones (output sharps).
	 *
	 * @param string $chord     Chord name (e.g. Am7).
	 * @param int    $semitones Semitones to add.
	 * @return string Transposed chord (e.g. Bm7).
	 */
	public function transpose_chord( $chord, $semitones ) {
		$idx = $this->root_index( $chord );
		if ( $idx === null ) {
			return $chord;
		}
		$suffix = $this->chord_suffix( $chord );
		$new_idx = ( $idx + (int) $semitones % 12 + 12 ) % 12;
		return self::CHROMATIC_SHARPS[ $new_idx ] . $suffix;
	}

	/**
	 * Build the set of all required chord names: from every song, every chord in every key.
	 *
	 * @return array Unique chord names (all keys for each chord used in any song).
	 */
	public function get_required_chords() {
		$songs = get_posts( array(
			'post_type'      => 'song',
			'posts_per_page' => -1,
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'fields'         => 'ids',
		) );

		$all_chords = array();

		foreach ( $songs as $post_id ) {
			$chord_sheet = get_field( 'chord_sheet', $post_id );
			$tabs        = get_field( 'tabs', $post_id );
			$text        = is_string( $chord_sheet ) ? $chord_sheet : '';
			if ( is_string( $tabs ) && $tabs !== '' ) {
				$text .= "\n" . $tabs;
			}
			$chords = $this->extract_chord_names_from_text( $text );
			foreach ( $chords as $c ) {
				// Add this chord and all 12 transpositions
				for ( $k = 0; $k < 12; $k++ ) {
					$transposed = $this->transpose_chord( $c, $k );
					$all_chords[ $transposed ] = true;
				}
			}
		}

		return $all_chords;
	}

	/**
	 * Check if library has a chord (exact or enharmonic equivalent).
	 *
	 * @param array  $library Library data (key => shape).
	 * @param string $name    Chord name.
	 * @return bool True if found.
	 */
	public function library_has_chord( $library, $name ) {
		if ( isset( $library[ $name ] ) ) {
			return true;
		}
		// Enharmonic equivalents (common ones)
		$equiv = array(
			'C#' => 'Db', 'Db' => 'C#',
			'D#' => 'Eb', 'Eb' => 'D#',
			'F#' => 'Gb', 'Gb' => 'F#',
			'G#' => 'Ab', 'Ab' => 'G#',
			'A#' => 'Bb', 'Bb' => 'A#',
		);
		$root = preg_replace( '/^(.[#b]?).*$/', '$1', $name );
		$suffix = $this->chord_suffix( $name );
		if ( isset( $equiv[ $root ] ) ) {
			$alt = $equiv[ $root ] . $suffix;
			if ( isset( $library[ $alt ] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get list of chord names that are required but missing from the library.
	 *
	 * @param array $library  Chord library (key => shape).
	 * @param array $required  Set of required chord names (key => true).
	 * @return array List of missing chord names.
	 */
	public function get_missing_chords( $library, $required ) {
		$missing = array();
		foreach ( array_keys( $required ) as $name ) {
			if ( ! $this->library_has_chord( $library, $name ) ) {
				$missing[] = $name;
			}
		}
		sort( $missing );
		return $missing;
	}

	/**
	 * Get library shape for a chord (exact or enharmonic). Returns null if not found.
	 *
	 * @param array  $library Library data.
	 * @param string $name    Chord name.
	 * @return array|null Shape (chord, position, barres) or null.
	 */
	public function get_library_shape( $library, $name ) {
		if ( isset( $library[ $name ] ) && is_array( $library[ $name ] ) ) {
			return $library[ $name ];
		}
		$equiv = array(
			'C#' => 'Db', 'Db' => 'C#',
			'D#' => 'Eb', 'Eb' => 'D#',
			'F#' => 'Gb', 'Gb' => 'F#',
			'G#' => 'Ab', 'Ab' => 'G#',
			'A#' => 'Bb', 'Bb' => 'A#',
		);
		$root = preg_replace( '/^(.[#b]?).*$/', '$1', $name );
		$suffix = $this->chord_suffix( $name );
		if ( isset( $equiv[ $root ] ) ) {
			$alt = $equiv[ $root ] . $suffix;
			if ( isset( $library[ $alt ] ) && is_array( $library[ $alt ] ) ) {
				return $library[ $alt ];
			}
		}
		return null;
	}

	/**
	 * AJAX: Save custom chord library (pastable JSON). Merged library uses this overlay; custom overrides file.
	 */
	public function ajax_save_custom_chord_library() {
		check_ajax_referer( 'jww_save_custom_chord_library', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'jww-theme' ) ) );
		}
		$raw = isset( $_POST['custom_chords'] ) ? wp_unslash( $_POST['custom_chords'] ) : '';
		if ( ! is_string( $raw ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid input.', 'jww-theme' ) ) );
		}
		$raw = trim( $raw );
		if ( $raw === '' ) {
			delete_option( self::OPTION_CUSTOM_CHORDS );
			wp_send_json_success( array( 'message' => __( 'Custom chords cleared. Library uses only the JSON file.', 'jww-theme' ) ) );
		}
		$data = json_decode( $raw, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			wp_send_json_error( array( 'message' => __( 'Invalid JSON: ', 'jww-theme' ) . json_last_error_msg() ) );
		}
		if ( ! is_array( $data ) ) {
			wp_send_json_error( array( 'message' => __( 'JSON must be an object of chord names to shapes (e.g. {"Am7": {"chord": [...], "position": 0, "barres": []}}).', 'jww-theme' ) ) );
		}
		update_option( self::OPTION_CUSTOM_CHORDS, $raw );
		$count = 0;
		foreach ( $data as $key => $val ) {
			if ( is_array( $val ) && isset( $val['chord'] ) && is_array( $val['chord'] ) ) {
				$count++;
			}
		}
		wp_send_json_success( array(
			'message' => sprintf(
				/* translators: %d: number of custom chord entries saved */
				__( '%d custom chord(s) saved. They override the same names in the JSON file.', 'jww-theme' ),
				$count
			),
		) );
	}

	/**
	 * Render the admin page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'jww-theme' ) );
		}

		$library = $this->get_chord_library();
		$required = $this->get_required_chords();
		$missing = $this->get_missing_chords( $library, $required );
		$required_list = array_keys( $required );
		sort( $required_list );
		$library_count = count( $library );
		$required_count = count( $required_list );
		$missing_count = count( $missing );
		$songs_with_chords = $this->count_songs_with_chord_data();
		$custom_raw = get_option( self::OPTION_CUSTOM_CHORDS, '' );
		$custom_count = count( self::get_custom_chord_library() );
		?>
		<div class="wrap jww-chord-library-wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Chord diagrams are loaded from the library (inc/chord-library.json). Below: every chord required across all songs (in every key). Add missing chords to the JSON to fill the gaps.', 'jww-theme' ); ?>
			</p>

			<div class="jww-chord-library-custom-section">
				<h2 class="jww-chord-library-custom-title"><?php esc_html_e( 'Custom chords (override library)', 'jww-theme' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Paste JSON-formatted chords here to add or override entries without editing the theme file. Same format as inc/chord-library.json (e.g. {"Am7": {"chord": [[1,0],[2,1,\"1\"],...], "position": 0, "barres": []}}). Custom chords take precedence over the file; when you migrate them into the JSON file, clear this field. Click a chord in the library below to add its JSON here (or merge it into existing overrides).', 'jww-theme' ); ?>
				</p>
				<textarea id="jww-custom-chord-library-input" class="jww-custom-chord-library-textarea large-text code" rows="8" placeholder='{"Am9": { "chord": [[1,0],[2,1,"1"],...], "position": 0, "barres": [] }}'><?php echo esc_textarea( $custom_raw ); ?></textarea>
				<p>
					<button type="button" class="button button-primary" id="jww-save-custom-chord-library"><?php esc_html_e( 'Save custom chords', 'jww-theme' ); ?></button>
					<span class="jww-custom-chord-library-message" id="jww-custom-chord-library-message" aria-live="polite"></span>
				</p>
				<?php if ( $custom_count > 0 ) : ?>
					<p class="description">
						<?php
						echo esc_html( sprintf(
							/* translators: %d: number of custom chord entries */
							__( '%d custom chord(s) in database (overriding file).', 'jww-theme' ),
							$custom_count
						) );
						?>
					</p>
				<?php endif; ?>
			</div>

			<div class="jww-chord-library-summary">
				<ul>
					<li><strong><?php echo (int) $library_count; ?></strong> <?php esc_html_e( 'chords in library', 'jww-theme' ); ?></li>
					<li><strong><?php echo (int) $required_count; ?></strong> <?php esc_html_e( 'chords required (all songs × all keys)', 'jww-theme' ); ?></li>
					<li><strong><?php echo (int) $missing_count; ?></strong> <?php esc_html_e( 'missing (no diagram)', 'jww-theme' ); ?></li>
					<li><strong><?php echo (int) $songs_with_chords; ?></strong> <?php esc_html_e( 'songs with chord/tab data', 'jww-theme' ); ?></li>
				</ul>
			</div>

			<div class="jww-chord-library-toolbar">
				<label>
					<input type="checkbox" id="jww-chord-library-filter-missing" autocomplete="off">
					<?php esc_html_e( 'Show missing chords only', 'jww-theme' ); ?>
				</label>
			</div>

			<div id="jww-chord-library-grid" class="jww-chord-library-grid" role="list">
				<?php
				foreach ( $required_list as $name ) {
					$has_diagram = $this->library_has_chord( $library, $name );
					$is_missing = ! $has_diagram;
					?>
					<div class="jww-chord-library-card <?php echo $is_missing ? 'jww-chord-missing' : ''; ?>" data-chord="<?php echo esc_attr( $name ); ?>" data-missing="<?php echo $is_missing ? '1' : '0'; ?>" role="listitem">
						<div class="jww-chord-library-card-inner">
							<div class="jww-chord-library-card-name"><?php echo esc_html( $name ); ?></div>
							<div class="jww-chord-library-card-diagram" data-chord="<?php echo esc_attr( $name ); ?>"></div>
							<?php if ( $is_missing ) : ?>
								<div class="jww-chord-library-card-placeholder"><?php esc_html_e( 'No diagram', 'jww-theme' ); ?></div>
							<?php endif; ?>
						</div>
					</div>
					<?php
				}
				?>
			</div>
		</div>
		<script>
		(function() {
			function initSaveButton() {
				var btn = document.getElementById('jww-save-custom-chord-library');
				var input = document.getElementById('jww-custom-chord-library-input');
				var messageEl = document.getElementById('jww-custom-chord-library-message');
				if (!btn || !input || !messageEl || typeof jwwChordLibraryAdmin === 'undefined') return;
				btn.addEventListener('click', function() {
					var raw = input.value.trim();
					btn.disabled = true;
					messageEl.textContent = '';
					messageEl.className = 'jww-custom-chord-library-message';
					var formData = new FormData();
					formData.append('action', 'jww_save_custom_chord_library');
					formData.append('nonce', jwwChordLibraryAdmin.saveCustomChordsNonce || '');
					formData.append('custom_chords', raw);
					fetch(jwwChordLibraryAdmin.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
						.then(function(r) { return r.json(); })
						.then(function(res) {
							btn.disabled = false;
							if (res.success) {
								messageEl.textContent = res.data.message || 'Saved.';
								messageEl.className = 'jww-custom-chord-library-message notice-success';
								setTimeout(function() { location.reload(); }, 1200);
							} else {
								messageEl.textContent = res.data && res.data.message ? res.data.message : 'Error.';
								messageEl.className = 'jww-custom-chord-library-message notice-error';
							}
						})
						.catch(function() {
							btn.disabled = false;
							messageEl.textContent = 'Request failed.';
							messageEl.className = 'jww-custom-chord-library-message notice-error';
						});
				});
			}
			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', initSaveButton);
			} else {
				initSaveButton();
			}
		})();
		</script>
		<?php
	}

	/**
	 * Count songs that have chord_sheet or tabs content.
	 *
	 * @return int
	 */
	private function count_songs_with_chord_data() {
		$songs = get_posts( array(
			'post_type'      => 'song',
			'posts_per_page' => -1,
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'fields'         => 'ids',
		) );
		$count = 0;
		foreach ( $songs as $post_id ) {
			$chord_sheet = get_field( 'chord_sheet', $post_id );
			$tabs        = get_field( 'tabs', $post_id );
			if ( is_string( $chord_sheet ) && trim( $chord_sheet ) !== '' ) {
				$count++;
				continue;
			}
			if ( is_string( $tabs ) && trim( $tabs ) !== '' ) {
				$count++;
			}
		}
		return $count;
	}
}

new Song_Chord_Library_Admin();
