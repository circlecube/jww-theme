<?php
/**
 * Setlist repeater admin: collapsed row summary and relationship result (Option A).
 *
 * - Customizes acf/fields/relationship/result for the setlist song field.
 * - Enqueues JS that builds a compact summary per row (type + song/text/note + notes + duration),
 *   shows it when the row is collapsed, and toggles: click summary to expand, click elsewhere
 *   or another row to collapse.
 *
 * @package JWW_Theme
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Setlist_Repeater_Admin {

	/**
	 * Setlist repeater field key.
	 */
	const SETLIST_FIELD_KEY = 'field_show_setlist';

	/**
	 * Setlist song relationship field key.
	 */
	const SONG_FIELD_KEY = 'field_setlist_song';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'acf/fields/relationship/result/key=' . self::SONG_FIELD_KEY, array( $this, 'relationship_result' ), 10, 4 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10 );
	}

	/**
	 * Customize relationship field result text for setlist song (Option A).
	 *
	 * @param string $text   Current result text (post title).
	 * @param object $post   The post object.
	 * @param array  $field  ACF field array.
	 * @param int    $post_id Current post ID being edited.
	 * @return string
	 */
	public function relationship_result( $text, $post, $field, $post_id ) {
		// Optional: append attribution or other song meta for easier identification in the dropdown.
		$attribution = get_field( 'attribution', $post->ID );
		if ( ! empty( $attribution ) && is_string( $attribution ) ) {
			$text .= ' <span style="color:#646970;">(' . esc_html( $attribution ) . ')</span>';
		}
		return $text;
	}

	/**
	 * Enqueue script and style on show edit screen.
	 *
	 * @param string $hook Admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		if ( $hook !== 'post.php' && $hook !== 'post-new.php' ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || $screen->post_type !== 'show' ) {
			return;
		}

		$dir = get_stylesheet_directory();
		$uri = get_stylesheet_directory_uri();

		wp_enqueue_style(
			'jww-setlist-repeater-admin',
			$uri . '/admin/css/setlist-repeater-admin.css',
			array(),
			file_exists( $dir . '/admin/css/setlist-repeater-admin.css' ) ? (string) filemtime( $dir . '/admin/css/setlist-repeater-admin.css' ) : wp_get_theme()->get( 'Version' )
		);

		$acf_dep = array( 'jquery' );
		if ( wp_script_is( 'acf-input', 'registered' ) ) {
			$acf_dep[] = 'acf-input';
		}
		wp_enqueue_script(
			'jww-setlist-repeater-admin',
			$uri . '/admin/js/setlist-repeater-admin.js',
			$acf_dep,
			file_exists( $dir . '/admin/js/setlist-repeater-admin.js' ) ? (string) filemtime( $dir . '/admin/js/setlist-repeater-admin.js' ) : wp_get_theme()->get( 'Version' ),
			true
		);

		wp_localize_script( 'jww-setlist-repeater-admin', 'jwwSetlistRepeaterAdmin', array(
			'setlistFieldKey' => self::SETLIST_FIELD_KEY,
			'entryTypeLabels' => array(
				'song-post' => 'Song',
				'note'      => 'Note',
				'song-text' => 'Song Text',
			),
		) );
	}
}

new Setlist_Repeater_Admin();
