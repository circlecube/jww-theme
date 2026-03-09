<?php
/**
 * Social share meta box on post edit (song, show, post). Sidebar panel, published posts only.
 *
 * @package JWW_Theme
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Social_Post_Meta_Box
 */
class Social_Post_Meta_Box {

	const META_BOX_ID = 'jww_social_share';
	const NONCE_ACTION = 'jww_social_share_from_editor';

	/**
	 * Post types that get the share panel.
	 *
	 * @var string[]
	 */
	protected $post_types = array( 'song', 'show', 'post' );

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_jww_social_share_from_editor', array( $this, 'ajax_share_from_editor' ) );
	}

	/**
	 * Get list of channel slugs that are both configured and enabled.
	 *
	 * @return string[]
	 */
	protected function get_configured_channels() {
		$out = array();
		foreach ( array( 'mastodon', 'bluesky', 'pinterest', 'threads', 'facebook', 'instagram' ) as $channel ) {
			if ( function_exists( 'jww_social_get_config' ) && jww_social_get_config( $channel )
				&& function_exists( 'jww_social_is_channel_enabled' ) && jww_social_is_channel_enabled( $channel ) ) {
				$out[] = $channel;
			}
		}
		return $out;
	}

	/**
	 * Get display label for a channel.
	 *
	 * @param string $channel Channel slug.
	 * @return string
	 */
	protected function get_channel_label( $channel ) {
		$labels = array(
			'mastodon'  => __( 'Mastodon', 'jww-theme' ),
			'bluesky'   => __( 'Bluesky', 'jww-theme' ),
			'pinterest' => __( 'Pinterest', 'jww-theme' ),
			'threads'   => __( 'Threads', 'jww-theme' ),
			'facebook'  => __( 'Facebook', 'jww-theme' ),
			'instagram' => __( 'Instagram', 'jww-theme' ),
		);
		return isset( $labels[ $channel ] ) ? $labels[ $channel ] : ucfirst( $channel );
	}

	/**
	 * Register meta boxes for song, show, post.
	 *
	 * @param string $post_type Current post type.
	 */
	public function add_meta_boxes( $post_type ) {
		if ( ! in_array( $post_type, $this->post_types, true ) ) {
			return;
		}
		add_meta_box(
			self::META_BOX_ID,
			__( 'Social Share', 'jww-theme' ),
			array( $this, 'render_meta_box' ),
			$post_type,
			'side',
			'default'
		);
	}

	/**
	 * Enqueue script and style on post edit screen for song, show, post.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_scripts( $hook_suffix ) {
		if ( $hook_suffix !== 'post.php' && $hook_suffix !== 'post-new.php' ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, $this->post_types, true ) ) {
			return;
		}
		$channels = $this->get_configured_channels();
		if ( empty( $channels ) ) {
			return;
		}
		wp_enqueue_style(
			'jww-social-post-meta-box',
			get_stylesheet_directory_uri() . '/admin/css/social-post-meta-box.css',
			array(),
			filemtime( get_stylesheet_directory() . '/admin/css/social-post-meta-box.css' )
		);
		wp_enqueue_script(
			'jww-social-post-meta-box',
			get_stylesheet_directory_uri() . '/admin/js/social-post-meta-box.js',
			array( 'jquery' ),
			filemtime( get_stylesheet_directory() . '/admin/js/social-post-meta-box.js' ),
			true
		);
		wp_localize_script( 'jww-social-post-meta-box', 'jwwSocialShare', array(
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( self::NONCE_ACTION ),
			'channels'  => $channels,
			'postType'  => $screen->post_type,
			'i18n'      => array(
				'sharing'   => __( 'Sharing…', 'jww-theme' ),
				'success'   => __( 'Shared.', 'jww-theme' ),
				'error'    => __( 'Request failed.', 'jww-theme' ),
				'shareTo'   => __( 'Share to %s', 'jww-theme' ),
				'shareToAll' => __( 'Share to all', 'jww-theme' ),
			),
		) );
	}

	/**
	 * Render meta box content. Only show share UI when post is published.
	 *
	 * @param WP_Post $post Post object.
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( self::NONCE_ACTION, 'jww_social_share_nonce' );
		if ( $post->post_status !== 'publish' ) {
			echo '<p class="jww-social-share-unpublished">' . esc_html__( 'Publish to share.', 'jww-theme' ) . '</p>';
			return;
		}
		$channels = $this->get_configured_channels();
		if ( empty( $channels ) ) {
			echo '<p class="jww-social-share-none">' . esc_html__( 'No social platforms configured. Configure and enable channels in Settings → Social Sharing.', 'jww-theme' ) . '</p>';
			return;
		}

		$post_type = $post->post_type;
		echo '<div class="jww-social-share-panel" data-post-id="' . (int) $post->ID . '" data-post-type="' . esc_attr( $post_type ) . '">';

		if ( $post_type === 'song' ) {
			$this->render_song_sections( $post, $channels );
		} elseif ( $post_type === 'show' ) {
			$this->render_show_section( $post, $channels );
		} else {
			$this->render_post_section( $post, $channels );
		}

		echo '</div>';
	}

	/**
	 * Render song: Share full song + Share a lyric line (with line selector).
	 *
	 * @param WP_Post $post    Post object.
	 * @param array   $channels Channel slugs.
	 */
	protected function render_song_sections( $post, $channels ) {
		$lines = function_exists( 'jww_social_get_lyrics_lines_for_song' ) ? jww_social_get_lyrics_lines_for_song( $post->ID ) : array();
		$has_lyrics = ! empty( $lines );

		echo '<div class="jww-social-share-section jww-social-share-full">';
		echo '<p class="jww-social-share-section-title">' . esc_html__( 'Share full song', 'jww-theme' ) . '</p>';
		$this->render_channel_buttons( $channels, 'full_song', null );
		$this->render_share_all_button( $channels, 'full_song', null );
		echo '</div>';

		echo '<div class="jww-social-share-section jww-social-share-lyric">';
		echo '<p class="jww-social-share-section-title">' . esc_html__( 'Share a lyric line', 'jww-theme' ) . '</p>';
		if ( $has_lyrics ) {
			echo '<label for="jww-social-lyric-line" class="screen-reader-text">' . esc_html__( 'Lyric line', 'jww-theme' ) . '</label>';
			echo '<select id="jww-social-lyric-line" class="jww-social-lyric-line-select">';
			echo '<option value="random">' . esc_html__( 'Random', 'jww-theme' ) . '</option>';
			foreach ( $lines as $i => $line ) {
				$preview = wp_trim_words( $line, 8 );
				echo '<option value="' . (int) $i . '">' . esc_html( sprintf( __( 'Line %d', 'jww-theme' ), $i + 1 ) ) . ': ' . esc_html( $preview ) . '</option>';
			}
			echo '</select>';
			$this->render_channel_buttons( $channels, 'lyric', 'jww-social-lyric-line' );
			$this->render_share_all_button( $channels, 'lyric', 'jww-social-lyric-line' );
		} else {
			echo '<p class="jww-social-share-no-lyrics">' . esc_html__( 'No lyrics available for this song.', 'jww-theme' ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Render show: single Share show section.
	 *
	 * @param WP_Post $post    Post object.
	 * @param array   $channels Channel slugs.
	 */
	protected function render_show_section( $post, $channels ) {
		echo '<div class="jww-social-share-section">';
		echo '<p class="jww-social-share-section-title">' . esc_html__( 'Share show', 'jww-theme' ) . '</p>';
		$this->render_channel_buttons( $channels, 'show', null );
		$this->render_share_all_button( $channels, 'show', null );
		echo '</div>';
	}

	/**
	 * Render blog post: single Share post section.
	 *
	 * @param WP_Post $post    Post object.
	 * @param array   $channels Channel slugs.
	 */
	protected function render_post_section( $post, $channels ) {
		echo '<div class="jww-social-share-section">';
		echo '<p class="jww-social-share-section-title">' . esc_html__( 'Share post', 'jww-theme' ) . '</p>';
		$this->render_channel_buttons( $channels, 'post', null );
		$this->render_share_all_button( $channels, 'post', null );
		echo '</div>';
	}

	/**
	 * Output per-channel share buttons for a share type.
	 *
	 * @param array  $channels     Channel slugs.
	 * @param string $share_type   full_song|lyric|show|post.
	 * @param string|null $line_select_id Optional. ID of the lyric line select for lyric share.
	 */
	protected function render_channel_buttons( $channels, $share_type, $line_select_id = null ) {
		echo '<div class="jww-social-share-buttons" data-share-type="' . esc_attr( $share_type ) . '"' . ( $line_select_id ? ' data-line-select-id="' . esc_attr( $line_select_id ) . '"' : '' ) . '>';
		foreach ( $channels as $ch ) {
			$label = $this->get_channel_label( $ch );
			echo '<button type="button" class="button button-small jww-social-share-btn" data-channel="' . esc_attr( $ch ) . '">' . esc_html( $label ) . '</button>';
		}
		echo '</div>';
	}

	/**
	 * Output "Share to all" button for a share type.
	 *
	 * @param array  $channels     Channel slugs.
	 * @param string $share_type   full_song|lyric|show|post.
	 * @param string|null $line_select_id Optional. ID of the lyric line select.
	 */
	protected function render_share_all_button( $channels, $share_type, $line_select_id = null ) {
		echo '<div class="jww-social-share-all-wrap" data-share-type="' . esc_attr( $share_type ) . '"' . ( $line_select_id ? ' data-line-select-id="' . esc_attr( $line_select_id ) . '"' : '' ) . '>';
		echo '<button type="button" class="button button-primary button-small jww-social-share-all">' . esc_html__( 'Share to all', 'jww-theme' ) . '</button>';
		echo '</div>';
	}

	/**
	 * AJAX: share from editor. Expects post_id, share_type (full_song|lyric|show|post), channel (slug or 'all'), line_index (optional for lyric).
	 */
	public function ajax_share_from_editor() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		if ( $post_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post.', 'jww-theme' ) ) );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'jww-theme' ) ) );
		}
		$share_type = isset( $_POST['share_type'] ) ? sanitize_key( $_POST['share_type'] ) : '';
		$allowed_types = array( 'full_song', 'lyric', 'show', 'post' );
		if ( ! in_array( $share_type, $allowed_types, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid share type.', 'jww-theme' ) ) );
		}
		$channel = isset( $_POST['channel'] ) ? sanitize_key( $_POST['channel'] ) : '';
		$line_index = isset( $_POST['line_index'] ) ? sanitize_text_field( $_POST['line_index'] ) : 'random';

		$payload = null;
		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error( array( 'message' => __( 'Post not found.', 'jww-theme' ) ) );
		}

		if ( $share_type === 'full_song' && $post->post_type === 'song' ) {
			$payload = jww_social_build_payload_for_song( $post_id );
		} elseif ( $share_type === 'lyric' && $post->post_type === 'song' ) {
			$idx = $line_index === 'random' || $line_index === '' ? -1 : (int) $line_index;
			$payload = jww_social_build_payload_for_song_lyric( $post_id, $idx );
		} elseif ( $share_type === 'show' && $post->post_type === 'show' ) {
			$payload = jww_social_build_payload_for_show( $post_id, true );
		} elseif ( $share_type === 'post' && $post->post_type === 'post' ) {
			$payload = jww_social_build_payload_for_post( $post_id );
		}

		if ( ! $payload ) {
			wp_send_json_error( array( 'message' => __( 'Could not build share content.', 'jww-theme' ) ) );
		}

		$channels = array( 'mastodon', 'bluesky', 'pinterest', 'threads', 'facebook', 'instagram' );
		if ( $channel !== 'all' && in_array( $channel, $channels, true ) ) {
			$channels = array( $channel );
		} else {
			// Use default: all that support this payload (e.g. add pinterest + instagram if image).
			$channels = array( 'mastodon', 'bluesky', 'threads', 'facebook' );
			if ( ! empty( $payload['image_url'] ) ) {
				$channels[] = 'pinterest';
				$channels[] = 'instagram';
			}
		}
		$channels = array_values( array_filter( $channels, 'jww_social_is_channel_enabled' ) );
		if ( empty( $channels ) ) {
			wp_send_json_error( array( 'message' => __( 'No channels enabled.', 'jww-theme' ) ) );
		}

		$results = jww_social_dispatch( $payload, $channels );
		$results_json = array();
		foreach ( $results as $ch => $result ) {
			$results_json[ $ch ] = array(
				'ok'      => $result === true,
				'message' => is_wp_error( $result ) ? $result->get_error_message() : '',
			);
		}
		wp_send_json_success( array( 'results' => $results_json ) );
	}
}
