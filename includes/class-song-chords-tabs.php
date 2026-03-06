<?php
/**
 * Song Chords and Tabs
 *
 * Enqueues ChordSheetJS, VexChords, VexTab and theme script on single-song when chord_sheet or tabs exist.
 * Provides chord library and song data to the front end.
 *
 * @package JWW_Theme
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Song_Chords_Tabs
 */
class Song_Chords_Tabs {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_single_song' ), 20 );
	}

	/**
	 * Whether the current song has chord sheet or tab content (and we should show chord/tab UI).
	 *
	 * @param int|null $post_id Post ID or null for current post.
	 * @return bool
	 */
	public static function song_has_chord_or_tab_content( $post_id = null ) {
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}
		if ( ! $post_id || get_post_type( $post_id ) !== 'song' ) {
			return false;
		}
		$chord_sheet = get_field( 'chord_sheet', $post_id );
		$tabs        = get_field( 'tabs', $post_id );
		$chord_sheet = is_string( $chord_sheet ) ? trim( $chord_sheet ) : '';
		$tabs        = is_string( $tabs ) ? trim( $tabs ) : '';
		return $chord_sheet !== '' || $tabs !== '';
	}

	/**
	 * Enqueue scripts and styles on single song when chord sheet or tabs exist.
	 * ChordSheetJS and VexTab are loaded from CDN; VexChords is bundled in theme script.
	 */
	public function enqueue_single_song() {
		if ( ! is_singular( 'song' ) ) {
			return;
		}
		$post_id = get_the_ID();
		if ( ! self::song_has_chord_or_tab_content( $post_id ) ) {
			return;
		}

		$theme_uri = get_stylesheet_directory_uri();
		$theme_dir = get_stylesheet_directory();

		// Load chord/tab libs from CDN (not bundled).
		wp_register_script(
			'chordsheetjs',
			'https://cdn.jsdelivr.net/npm/chordsheetjs@10/lib/bundle.min.js',
			array(),
			'10.11.0',
			true
		);
		wp_register_script(
			'vextab',
			'https://cdn.jsdelivr.net/npm/vextab@4.0.5/dist/div.prod.js',
			array(),
			'4.0.5',
			true
		);

		$script_path = $theme_dir . '/build/theme-song-chords-tabs.js';
		$style_path  = $theme_dir . '/build/theme-song-chords-tabs.css';
		$script_ver  = file_exists( $script_path ) ? (string) filemtime( $script_path ) : wp_get_theme()->get( 'Version' );
		$style_ver   = file_exists( $style_path ) ? (string) filemtime( $style_path ) : wp_get_theme()->get( 'Version' );

		wp_enqueue_script(
			'jww-song-chords-tabs',
			$theme_uri . '/build/theme-song-chords-tabs.js',
			array( 'chordsheetjs', 'vextab' ),
			$script_ver,
			true
		);

		// Chord library (merged: inc/chord-library.json + custom chords from database; custom overrides file)
		$chord_library = Song_Chord_Library_Admin::get_merged_chord_library();

		$chord_sheet_raw = get_field( 'chord_sheet', $post_id );
		$tabs_raw        = get_field( 'tabs', $post_id );
		$capo_default    = get_field( 'capo', $post_id );
		if ( ! is_numeric( $capo_default ) || (int) $capo_default < 0 || (int) $capo_default > 12 ) {
			$capo_default = 0;
		} else {
			$capo_default = (int) $capo_default;
		}

		wp_localize_script( 'jww-song-chords-tabs', 'jwwSongChordsTabs', array(
			'chordLibrary' => $chord_library,
			'chordSheet'   => is_string( $chord_sheet_raw ) ? $chord_sheet_raw : '',
			'tabs'         => is_string( $tabs_raw ) ? $tabs_raw : '',
			'capoDefault'  => $capo_default,
			'guitarIconUrl' => $theme_uri . '/assets/guitar.svg',
			'storageKey'  => 'jww_show_chords',
		) );

		wp_enqueue_style(
			'jww-song-chords-tabs',
			$theme_uri . '/build/theme-song-chords-tabs.css',
			array(),
			$style_ver
		);
	}
}

new Song_Chords_Tabs();
