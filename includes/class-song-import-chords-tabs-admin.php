<?php
/**
 * Song Import Chords / Tabs – admin meta box
 *
 * Adds "Import chords" (paste from Ultimate Guitar / ChordPro) and "Import tab" (paste ASCII tab, convert to VexTab)
 * on the song edit screen. Saves into ACF chord_sheet and tabs fields.
 *
 * @package JWW_Theme
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Song_Import_Chords_Tabs_Admin
 */
class Song_Import_Chords_Tabs_Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_footer', array( $this, 'output_import_chord_sheet_block' ), 5 );
		add_action( 'wp_ajax_jww_parse_chord_sheet', array( $this, 'ajax_parse_chord_sheet' ) );
	}

	/**
	 * Add meta box to song edit screen.
	 * (Removed: Import tab meta box; tabs now use a modal below Tabs field like chord sheet.)
	 */
	public function add_meta_box() {
		// No meta box — tabs import is a button + modal below Tabs field.
	}

	/**
	 * Output the Import chord sheet button and modal in footer on song edit; JS moves it below Chord sheet field.
	 * Also output the Import tabs button and modal; JS moves it below Tabs field.
	 */
	public function output_import_chord_sheet_block() {
		$screen = get_current_screen();
		if ( ! $screen || $screen->post_type !== 'song' || $screen->base !== 'post' ) {
			return;
		}
		?>
		<div id="jww-import-chord-sheet-block" style="display: none;">
			<div class="jww-import-chord-sheet-after-field" style="padding: 0 1rem 1rem;">
				<button type="button" class="button button-secondary" id="jww-import-chords-open-modal"><?php esc_html_e( 'Import chord sheet…', 'jww-theme' ); ?></button>
				<span class="description" style="margin-left: 8px;"><?php esc_html_e( 'Paste from Ultimate Guitar; convert to ChordPro and copy into this field.', 'jww-theme' ); ?></span>
			</div>
			<div id="jww-import-chords-modal" class="jww-import-chords-modal" role="dialog" aria-labelledby="jww-import-chords-modal-title" aria-hidden="true">
				<div class="jww-import-chords-modal-backdrop"></div>
				<div class="jww-import-chords-modal-content">
					<div class="jww-import-chords-modal-header">
						<h2 id="jww-import-chords-modal-title" class="jww-import-chords-modal-title"><?php esc_html_e( 'Import chord sheet', 'jww-theme' ); ?></h2>
						<button type="button" class="jww-import-chords-modal-close" aria-label="<?php esc_attr_e( 'Close', 'jww-theme' ); ?>">&times;</button>
					</div>
					<p class="description jww-import-chords-modal-desc">
						<?php esc_html_e( 'Paste from Ultimate Guitar (or any text with chords above lyrics) in the left box. Parsed ChordPro (chords in brackets with lyrics) appears on the right. Click "Use this" to copy into the Chord sheet field.', 'jww-theme' ); ?>
					</p>
					<div class="jww-import-chords-modal-columns">
						<div class="jww-import-chords-modal-col">
							<label for="jww-import-chords-paste" class="jww-import-chords-modal-label"><?php esc_html_e( 'Paste here', 'jww-theme' ); ?></label>
							<textarea id="jww-import-chords-paste" class="jww-import-chords-modal-textarea jww-monospace" rows="18" placeholder="<?php esc_attr_e( 'Paste from Ultimate Guitar or chords-above-lyrics…', 'jww-theme' ); ?>"></textarea>
						</div>
						<div class="jww-import-chords-modal-col">
							<label for="jww-import-chords-parsed" class="jww-import-chords-modal-label"><?php esc_html_e( 'Parsed (ChordPro)', 'jww-theme' ); ?></label>
							<textarea id="jww-import-chords-parsed" class="jww-import-chords-modal-textarea jww-monospace" rows="18" placeholder="<?php esc_attr_e( 'Parsed output appears here…', 'jww-theme' ); ?>" readonly></textarea>
						</div>
					</div>
					<div class="jww-import-chords-modal-footer">
						<button type="button" class="button button-primary" id="jww-import-chords-use"><?php esc_html_e( 'Use this (copy to Chord sheet)', 'jww-theme' ); ?></button>
						<button type="button" class="button jww-import-chords-modal-close-btn"><?php esc_html_e( 'Cancel', 'jww-theme' ); ?></button>
					</div>
				</div>
			</div>
		</div>

		<div id="jww-import-tabs-block" style="display: none;">
			<div class="jww-import-chord-sheet-after-field" style="padding: 0 1rem 1rem;">
				<button type="button" class="button button-secondary" id="jww-import-tabs-open-modal"><?php esc_html_e( 'Import tabs…', 'jww-theme' ); ?></button>
				<span class="description" style="margin-left: 8px;"><?php esc_html_e( 'Paste ASCII tab or VexTab; convert and preview, then copy into the Tabs field.', 'jww-theme' ); ?></span>
			</div>
			<div id="jww-import-tabs-modal" class="jww-import-chords-modal" role="dialog" aria-labelledby="jww-import-tabs-modal-title" aria-hidden="true">
				<div class="jww-import-chords-modal-backdrop"></div>
				<div class="jww-import-chords-modal-content">
					<div class="jww-import-chords-modal-header">
						<h2 id="jww-import-tabs-modal-title" class="jww-import-chords-modal-title"><?php esc_html_e( 'Import tabs', 'jww-theme' ); ?></h2>
						<button type="button" class="jww-import-tabs-modal-close jww-import-chords-modal-close" aria-label="<?php esc_attr_e( 'Close', 'jww-theme' ); ?>">&times;</button>
					</div>
					<p class="description jww-import-chords-modal-desc">
						<?php esc_html_e( 'Paste plain ASCII tab (e.g. e|-0-2-3-|) or VexTab in the left box. Converted VexTab appears on the right with a live preview below. Click "Use this" to copy into the Tabs field.', 'jww-theme' ); ?>
					</p>
					<div class="jww-import-chords-modal-columns">
						<div class="jww-import-chords-modal-col">
							<label for="jww-import-tabs-paste" class="jww-import-chords-modal-label"><?php esc_html_e( 'Paste here', 'jww-theme' ); ?></label>
							<textarea id="jww-import-tabs-paste" class="jww-import-chords-modal-textarea jww-monospace" rows="14" placeholder="<?php esc_attr_e( 'Paste ASCII tab or VexTab…', 'jww-theme' ); ?>"></textarea>
						</div>
						<div class="jww-import-chords-modal-col">
							<label for="jww-import-tabs-vextab" class="jww-import-chords-modal-label"><?php esc_html_e( 'VexTab notation', 'jww-theme' ); ?></label>
							<textarea id="jww-import-tabs-vextab" class="jww-import-chords-modal-textarea jww-monospace" rows="8" placeholder="<?php esc_attr_e( 'Converted VexTab appears here; edit as needed…', 'jww-theme' ); ?>"></textarea>
							<div class="jww-import-tabs-preview-wrap">
								<span class="jww-import-chords-modal-label"><?php esc_html_e( 'Preview', 'jww-theme' ); ?></span>
								<div id="jww-import-tabs-preview" class="jww-import-tabs-preview"></div>
							</div>
						</div>
					</div>
					<div class="jww-import-chords-modal-footer">
						<button type="button" class="button button-primary" id="jww-import-tabs-use"><?php esc_html_e( 'Use this (copy to Tabs)', 'jww-theme' ); ?></button>
						<button type="button" class="button jww-import-tabs-modal-close-btn jww-import-chords-modal-close-btn"><?php esc_html_e( 'Cancel', 'jww-theme' ); ?></button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Former meta box content (Import tab). No longer used — tabs use modal below Tabs field.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function render_meta_box( $post ) {
		// Unused; kept for backwards compatibility if add_meta_box is re-enabled.
	}

	/**
	 * Enqueue admin scripts on song edit screen.
	 *
	 * @param string $hook Admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		if ( $hook !== 'post.php' && $hook !== 'post-new.php' ) {
			return;
		}
		global $post;
		if ( ! $post || $post->post_type !== 'song' ) {
			return;
		}

		$theme_uri = get_stylesheet_directory_uri();
		$theme_dir = get_stylesheet_directory();

		// Prefer local vendor copy (from npm run copy:chord-libs), fall back to CDN.
		$chordsheetjs_src = $theme_dir . '/assets/vendor/chordsheetjs/bundle.min.js';
		$chordsheetjs_uri = file_exists( $chordsheetjs_src )
			? $theme_uri . '/assets/vendor/chordsheetjs/bundle.min.js'
			: 'https://cdn.jsdelivr.net/npm/chordsheetjs@10/lib/bundle.min.js';

		wp_enqueue_script(
			'chordsheetjs',
			$chordsheetjs_uri,
			array(),
			'10.11.0',
			true
		);

		$vextab_src = $theme_dir . '/assets/vendor/vextab/div.prod.js';
		$vextab_uri = file_exists( $vextab_src )
			? $theme_uri . '/assets/vendor/vextab/div.prod.js'
			: 'https://cdn.jsdelivr.net/npm/vextab@4.0.5/dist/div.prod.js';
		wp_enqueue_script(
			'vextab',
			$vextab_uri,
			array(),
			'4.0.5',
			true
		);

		wp_enqueue_script(
			'jww-song-import-chords-tabs',
			$theme_uri . '/admin/js/song-import-chords-tabs.js',
			array( 'jquery', 'chordsheetjs', 'vextab' ),
			file_exists( $theme_dir . '/admin/js/song-import-chords-tabs.js' ) ? (string) filemtime( $theme_dir . '/admin/js/song-import-chords-tabs.js' ) : wp_get_theme()->get( 'Version' ),
			true
		);

		wp_localize_script( 'jww-song-import-chords-tabs', 'jwwSongImportChordsTabs', array(
			'nonce'   => wp_create_nonce( 'jww_import_chords_tabs' ),
			'fieldChordSheet' => 'field_68cad0001',
			'fieldTabs'       => 'field_68cad0002',
		) );

		wp_enqueue_style(
			'jww-song-import-chords-tabs',
			$theme_uri . '/admin/css/song-import-chords-tabs.css',
			array(),
			file_exists( $theme_dir . '/admin/css/song-import-chords-tabs.css' ) ? (string) filemtime( $theme_dir . '/admin/css/song-import-chords-tabs.css' ) : wp_get_theme()->get( 'Version' )
		);
	}

	/**
	 * AJAX: Parse chord sheet (preview only; save is done by writing to ACF field via form).
	 * Returns parsed HTML for preview.
	 */
	public function ajax_parse_chord_sheet() {
		check_ajax_referer( 'jww_import_chords_tabs', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}
		$raw = isset( $_POST['chord_sheet'] ) ? wp_unslash( $_POST['chord_sheet'] ) : '';
		$raw = is_string( $raw ) ? trim( $raw ) : '';
		if ( $raw === '' ) {
			wp_send_json_error( array( 'message' => 'Empty input' ) );
		}
		// Parsing is done client-side with ChordSheetJS; this endpoint can be used for server-side fallback or validation.
		wp_send_json_success( array( 'preview' => '' ) );
	}
}

new Song_Import_Chords_Tabs_Admin();
