<?php
/**
 * Social sharing admin: status per platform, manual trigger buttons for cron jobs, event log.
 *
 * @package JWW_Theme
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Social_Admin
 */
class Social_Admin {

	const PAGE_SLUG = 'jww-social';
	const NONCE_ACTION = 'jww_social_trigger';
	const NONCE_ACTION_TOGGLE = 'jww_social_toggle';
	const NONCE_ACTION_SETTINGS = 'jww_social_settings';
	const NONCE_ACTION_CREDENTIALS = 'jww_social_credentials';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ), 25 );
		add_action( 'admin_init', array( $this, 'maybe_save_credentials' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_jww_social_trigger_random_song', array( $this, 'ajax_trigger_random_song' ) );
		add_action( 'wp_ajax_jww_social_trigger_random_lyric', array( $this, 'ajax_trigger_random_lyric' ) );
		add_action( 'wp_ajax_jww_social_trigger_random_post', array( $this, 'ajax_trigger_random_post' ) );
		add_action( 'wp_ajax_jww_social_trigger_random_show', array( $this, 'ajax_trigger_random_show' ) );
		add_action( 'wp_ajax_jww_social_toggle_channel', array( $this, 'ajax_toggle_channel' ) );
		add_action( 'wp_ajax_jww_social_get_lyrics_for_song', array( $this, 'ajax_get_lyrics_for_song' ) );
		add_action( 'wp_ajax_jww_social_share_specific_song', array( $this, 'ajax_share_specific_song' ) );
		add_action( 'wp_ajax_jww_social_share_specific_lyric', array( $this, 'ajax_share_specific_lyric' ) );
		add_action( 'wp_ajax_jww_social_share_specific_show', array( $this, 'ajax_share_specific_show' ) );
		add_action( 'wp_ajax_jww_social_share_specific_post', array( $this, 'ajax_share_specific_post' ) );
		add_action( 'wp_ajax_jww_social_save_cron_schedule', array( $this, 'ajax_save_cron_schedule' ) );
		add_action( 'wp_ajax_jww_social_save_on_publish', array( $this, 'ajax_save_on_publish' ) );
		add_action( 'wp_ajax_jww_social_save_status_text', array( $this, 'ajax_save_status_text' ) );
	}

	/**
	 * Register Settings submenu page.
	 */
	public function add_menu_page() {
		add_options_page(
			__( 'Social Sharing', 'jww-theme' ),
			__( 'Social Sharing', 'jww-theme' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Handle credentials form POST on admin_init so we can redirect before any output.
	 */
	public function maybe_save_credentials() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== self::PAGE_SLUG ) {
			return;
		}
		if ( ! isset( $_POST['jww_social_save_credentials'] ) || ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), self::NONCE_ACTION_CREDENTIALS ) ) {
			return;
		}
		$prefix = 'jww_social_';
		$schema = $this->get_credentials_schema();
		foreach ( $schema as $service => $fields ) {
			foreach ( $fields as $field ) {
				$key   = $field['key'];
				$opt_key = $prefix . $key;
				$value = isset( $_POST[ 'jww_social_cred_' . $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'jww_social_cred_' . $key ] ) ) : '';
				if ( $field['secret'] && $value === '' ) {
					continue;
				}
				update_option( $opt_key, $value, false );
			}
		}
		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'credentials_saved' => '1' ), admin_url( 'options-general.php' ) ) );
		exit;
	}

	/**
	 * Enqueue scripts and styles only on our page.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_scripts( $hook_suffix ) {
		if ( $hook_suffix !== 'settings_page_' . self::PAGE_SLUG ) {
			return;
		}
		wp_enqueue_style(
			'jww-social-admin',
			get_stylesheet_directory_uri() . '/admin/css/social-admin.css',
			array(),
			filemtime( get_stylesheet_directory() . '/admin/css/social-admin.css' )
		);
		wp_enqueue_script(
			'jww-social-admin',
			get_stylesheet_directory_uri() . '/admin/js/social-admin.js',
			array( 'jquery' ),
			filemtime( get_stylesheet_directory() . '/admin/js/social-admin.js' ),
			true
		);
		wp_localize_script( 'jww-social-admin', 'jwwSocialAdmin', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
			'toggleNonce' => wp_create_nonce( self::NONCE_ACTION_TOGGLE ),
			'settingsNonce' => wp_create_nonce( self::NONCE_ACTION_SETTINGS ),
			'enabled' => array(
				'mastodon'  => jww_social_is_channel_enabled( 'mastodon' ),
				'bluesky'   => jww_social_is_channel_enabled( 'bluesky' ),
				'pinterest' => jww_social_is_channel_enabled( 'pinterest' ),
				'threads'   => jww_social_is_channel_enabled( 'threads' ),
				'facebook'  => jww_social_is_channel_enabled( 'facebook' ),
				'instagram' => jww_social_is_channel_enabled( 'instagram' ),
			),
			'cronSchedules' => array(
				'song'  => (int) get_option( 'jww_social_cron_schedule_song', 24 ),
				'lyric' => (int) get_option( 'jww_social_cron_schedule_lyric', 24 ),
				'show'  => (int) get_option( 'jww_social_cron_schedule_show', 24 ),
				'post'  => (int) get_option( 'jww_social_cron_schedule_post', 24 ),
			),
			'onPublish' => array(
				'song'  => get_option( 'jww_social_on_publish_song', '1' ) === '1',
				'show'  => get_option( 'jww_social_on_publish_show', '1' ) === '1',
				'post'  => get_option( 'jww_social_on_publish_post', '0' ) === '1',
				'anniversary_song' => get_option( 'jww_social_anniversary_song', '0' ) === '1',
				'anniversary_show' => get_option( 'jww_social_anniversary_show', '0' ) === '1',
			),
			'statusTextTemplates' => function_exists( 'jww_social_get_status_template' ) ? array(
				'song'             => jww_social_get_status_template( 'song' ),
				'song_publish'     => jww_social_get_status_template( 'song_publish' ),
				'show'             => jww_social_get_status_template( 'show' ),
				'post'             => jww_social_get_status_template( 'post' ),
				'lyric'            => jww_social_get_status_template( 'lyric' ),
				'anniversary_song' => jww_social_get_status_template( 'anniversary_song' ),
				'anniversary_show' => jww_social_get_status_template( 'anniversary_show' ),
			) : array(),
			'statusTextPlaceholders' => array(
				'song'             => '{title}, {link}',
				'song_publish'     => '{title}, {link}',
				'show'             => '{title}, {link}',
				'post'             => '{title}, {link}',
				'lyric'            => '{title}, {link}, {lyrics_line}',
				'anniversary_song' => '{title}, {link}, {years_ago}',
				'anniversary_show' => '{title}, {link}, {years_ago}',
			),
			'i18n'    => array(
				'triggering' => __( 'Running…', 'jww-theme' ),
				'success'    => __( 'Done.', 'jww-theme' ),
				'error'     => __( 'Request failed.', 'jww-theme' ),
				'emptyLog'   => __( 'Trigger an event above to see results here.', 'jww-theme' ),
				'enabledForPosts' => __( 'Include in posts', 'jww-theme' ),
				'saved'     => __( 'Saved.', 'jww-theme' ),
				'selectSong' => __( 'Select a song…', 'jww-theme' ),
				'selectShow' => __( 'Select a show…', 'jww-theme' ),
				'selectPost' => __( 'Select a post…', 'jww-theme' ),
				'selectLyric' => __( 'Select a line…', 'jww-theme' ),
				'loadLyrics' => __( 'Load lyrics', 'jww-theme' ),
				'shareSelected' => __( 'Share selected', 'jww-theme' ),
				'random' => __( 'Random', 'jww-theme' ),
				'cronSaved' => __( 'Schedule saved.', 'jww-theme' ),
				'onPublishSong' => __( 'Share when a new Song is published', 'jww-theme' ),
				'onPublishShow' => __( 'Share when a new Show is published', 'jww-theme' ),
				'onPublishPost' => __( 'Share when a new Blog post is published', 'jww-theme' ),
			),
		) );
	}

	/**
	 * Get status for each social service (no secrets).
	 *
	 * @return array [ 'mastodon' => [ 'configured' => bool, 'label' => string ], ... ]
	 */
	public function get_status() {
		$status = array();
		foreach ( array( 'mastodon', 'bluesky', 'pinterest', 'threads', 'facebook', 'instagram' ) as $service ) {
			$config = function_exists( 'jww_social_get_config' ) ? jww_social_get_config( $service ) : false;
			$configured = ! empty( $config );
			$label = '';
			if ( $configured ) {
				if ( $service === 'mastodon' && ! empty( $config['instance'] ) ) {
					$label = $config['instance'];
				} elseif ( $service === 'bluesky' && ! empty( $config['identifier'] ) ) {
					$label = $config['identifier'];
				} elseif ( $service === 'pinterest' ) {
					$parts = array();
					if ( ! empty( $config['board_id'] ) ) {
						$parts[] = _x( 'Default', 'Pinterest default board', 'jww-theme' );
					}
					if ( ! empty( $config['board_id_song'] ) ) {
						$parts[] = _x( 'Song', 'Pinterest song board', 'jww-theme' );
					}
					if ( ! empty( $config['board_id_show'] ) ) {
						$parts[] = _x( 'Show', 'Pinterest show board', 'jww-theme' );
					}
					if ( ! empty( $config['board_id_lyric'] ) ) {
						$parts[] = _x( 'Lyric', 'Pinterest lyric board', 'jww-theme' );
					}
					$label = $parts ? implode( ', ', $parts ) . ' ' . __( 'board(s)', 'jww-theme' ) : '';
				} elseif ( $service === 'threads' ) {
					$label = ! empty( $config['user_id'] ) ? __( 'Threads account', 'jww-theme' ) : '';
				} elseif ( $service === 'facebook' ) {
					$label = ! empty( $config['page_id'] ) ? __( 'Facebook Page', 'jww-theme' ) : '';
				} elseif ( $service === 'instagram' ) {
					$label = ! empty( $config['ig_user_id'] ) ? __( 'Instagram account', 'jww-theme' ) : '';
				}
			}
			$status[ $service ] = array(
				'configured' => $configured,
				'label'      => $label,
				'enabled'    => function_exists( 'jww_social_is_channel_enabled' ) && jww_social_is_channel_enabled( $service ),
			);
		}
		return $status;
	}

	/**
	 * Schema for credential fields per platform. Used for the credentials form and save handler.
	 * Keys are option suffixes (prefixed with jww_social_). secret = use password input, don't overwrite with empty.
	 *
	 * @return array [ 'mastodon' => [ [ 'key' => 'mastodon_instance', 'label' => '...', 'secret' => false, 'required' => true ], ... ], ... ]
	 */
	public function get_credentials_schema() {
		return array(
			'mastodon' => array(
				array( 'key' => 'mastodon_instance', 'label' => __( 'Instance (hostname, no https://)', 'jww-theme' ), 'secret' => false, 'required' => true ),
				array( 'key' => 'mastodon_access_token', 'label' => __( 'Access token', 'jww-theme' ), 'secret' => true, 'required' => true ),
			),
			'bluesky' => array(
				array( 'key' => 'bluesky_identifier', 'label' => __( 'Handle (e.g. user.bsky.social)', 'jww-theme' ), 'secret' => false, 'required' => true ),
				array( 'key' => 'bluesky_app_password', 'label' => __( 'App password', 'jww-theme' ), 'secret' => true, 'required' => true ),
			),
			'pinterest' => array(
				array( 'key' => 'pinterest_access_token', 'label' => __( 'OAuth access token', 'jww-theme' ), 'secret' => true, 'required' => true ),
				array( 'key' => 'pinterest_board_id', 'label' => __( 'Default board ID', 'jww-theme' ), 'secret' => false, 'required' => true ),
				array( 'key' => 'pinterest_board_id_song', 'label' => __( 'Board ID for songs (optional)', 'jww-theme' ), 'secret' => false, 'required' => false ),
				array( 'key' => 'pinterest_board_id_show', 'label' => __( 'Board ID for shows (optional)', 'jww-theme' ), 'secret' => false, 'required' => false ),
				array( 'key' => 'pinterest_board_id_lyric', 'label' => __( 'Board ID for lyrics (optional)', 'jww-theme' ), 'secret' => false, 'required' => false ),
			),
			'threads' => array(
				array( 'key' => 'threads_app_id', 'label' => __( 'Threads App ID (for Re-authorize)', 'jww-theme' ), 'secret' => false, 'required' => false ),
				array( 'key' => 'threads_app_secret', 'label' => __( 'Threads App Secret (for Re-authorize)', 'jww-theme' ), 'secret' => true, 'required' => false ),
				array( 'key' => 'threads_user_id', 'label' => __( 'Threads User ID', 'jww-theme' ), 'secret' => false, 'required' => true ),
				array( 'key' => 'threads_access_token', 'label' => __( 'Access token', 'jww-theme' ), 'secret' => true, 'required' => true ),
			),
			'facebook' => array(
				array( 'key' => 'facebook_app_id', 'label' => __( 'Meta App ID (for Re-authorize)', 'jww-theme' ), 'secret' => false, 'required' => false ),
				array( 'key' => 'facebook_app_secret', 'label' => __( 'Meta App Secret (for Re-authorize)', 'jww-theme' ), 'secret' => true, 'required' => false ),
				array( 'key' => 'facebook_page_id', 'label' => __( 'Page ID', 'jww-theme' ), 'secret' => false, 'required' => true ),
				array( 'key' => 'facebook_page_access_token', 'label' => __( 'Page access token', 'jww-theme' ), 'secret' => true, 'required' => true ),
			),
			'instagram' => array(
				array( 'key' => 'instagram_account_id', 'label' => __( 'Instagram Business Account ID', 'jww-theme' ), 'secret' => false, 'required' => true ),
				array( 'key' => 'instagram_access_token', 'label' => __( 'Access token', 'jww-theme' ), 'secret' => true, 'required' => true ),
			),
		);
	}

	/**
	 * Return a masked display string for a credential value (first 2–4 chars + bullets).
	 *
	 * @param string $value Raw value.
	 * @return string Safe to output in HTML (e.g. "ab••••••").
	 */
	protected function mask_credential_display( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( $value === '' ) {
			return '';
		}
		$len = strlen( $value );
		if ( $len <= 2 ) {
			return str_repeat( '•', min( 3, $len ) ) . '•••';
		}
		if ( $len <= 4 ) {
			return substr( $value, 0, 2 ) . '••••••';
		}
		return substr( $value, 0, 4 ) . '••••••••••';
	}

	/**
	 * Get published posts for a post type for use in dropdowns.
	 *
	 * @param string $post_type Post type (song, show, post).
	 * @param int    $limit     Max number of posts. Default 300.
	 * @return array List of [ 'id' => int, 'title' => string ].
	 */
	public function get_posts_for_dropdown( $post_type, $limit = 300 ) {
		$posts = get_posts( array(
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'fields'         => 'ids',
		) );
		$out = array();
		foreach ( $posts as $id ) {
			$out[] = array( 'id' => (int) $id, 'title' => get_the_title( $id ) );
		}
		return $out;
	}

	/**
	 * Output the message template block (textarea + description) for a trigger. Shown only when the related trigger is enabled.
	 *
	 * @param string $template_key Option key suffix (e.g. song_publish, anniversary_song).
	 * @param array  $placeholders List of placeholder names for the description (e.g. title, link, years_ago).
	 * @param bool   $visible     Whether to add the visible class (when the trigger is enabled).
	 */
	protected function render_message_template( $template_key, $placeholders, $visible = false ) {
		$placeholders_list = implode( ', ', array_map( function ( $p ) {
			return '{' . $p . '}';
		}, $placeholders ) );
		$value = function_exists( 'jww_social_get_status_template' ) ? jww_social_get_status_template( $template_key ) : '';
		$visible_class = $visible ? ' jww-social-message-template-visible' : '';
		?>
		<div class="jww-social-message-template<?php echo esc_attr( $visible_class ); ?>" data-template-key="<?php echo esc_attr( $template_key ); ?>" aria-hidden="<?php echo $visible ? 'false' : 'true'; ?>">
			<label for="jww-social-status-text-<?php echo esc_attr( $template_key ); ?>" class="jww-social-message-template-label"><?php esc_html_e( 'Message text', 'jww-theme' ); ?></label>
			<textarea id="jww-social-status-text-<?php echo esc_attr( $template_key ); ?>" class="jww-social-status-textarea large-text" name="jww_social_status_text_<?php echo esc_attr( $template_key ); ?>" data-template-key="<?php echo esc_attr( $template_key ); ?>" rows="3" placeholder="<?php echo esc_attr( $placeholders_list ); ?>"><?php echo esc_textarea( $value ); ?></textarea>
			<p class="description jww-social-message-template-help">
				<?php
				printf(
					/* translators: %s: comma-separated placeholders e.g. {title}, {link} */
					esc_html__( 'Use placeholders: %s. They are replaced when the post is shared. Example: "Check out %s" with {title} and {link}.', 'jww-theme' ),
					'<code>' . esc_html( $placeholders_list ) . '</code>',
					'"{title}"'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Output a table row for the cron message template (under a schedule row). Visible only when that schedule is not disabled.
	 *
	 * @param string $template_key    Option key (song, lyric, show, post).
	 * @param array  $placeholders    Placeholder names for description.
	 * @param int    $schedule_hours  Current schedule hours (0 = disabled); row visible when > 0.
	 */
	protected function render_cron_message_template_row( $template_key, $placeholders, $schedule_hours = 0 ) {
		$placeholders_list = implode( ', ', array_map( function ( $p ) {
			return '{' . $p . '}';
		}, $placeholders ) );
		$value = function_exists( 'jww_social_get_status_template' ) ? jww_social_get_status_template( $template_key ) : '';
		// Always show the template row so users can edit messages even when schedule is disabled.
		$visible_class = ' jww-social-cron-template-row-visible';
		?>
		<tr class="jww-social-cron-template-row<?php echo esc_attr( $visible_class ); ?>" data-cron-type="<?php echo esc_attr( $template_key ); ?>">
			<td colspan="2">
				<div class="jww-social-message-template jww-social-message-template-inline">
					<label for="jww-social-status-text-cron-<?php echo esc_attr( $template_key ); ?>" class="jww-social-message-template-label"><?php esc_html_e( 'Message text', 'jww-theme' ); ?></label>
					<textarea id="jww-social-status-text-cron-<?php echo esc_attr( $template_key ); ?>" class="jww-social-status-textarea large-text" name="jww_social_status_text_<?php echo esc_attr( $template_key ); ?>" data-template-key="<?php echo esc_attr( $template_key ); ?>" rows="3" placeholder="<?php echo esc_attr( $placeholders_list ); ?>"><?php echo esc_textarea( $value ); ?></textarea>
					<p class="description jww-social-message-template-help">
						<?php
						printf(
							/* translators: %s: placeholders list */
							esc_html__( 'Use placeholders: %s. Replaced when the random share runs.', 'jww-theme' ),
							'<code>' . esc_html( $placeholders_list ) . '</code>'
						);
						?>
					</p>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render the admin page.
	 */
	public function render_page() {
		$status = $this->get_status();
		$user_can = current_user_can( 'manage_options' );

		// Save credentials form (options take precedence over .env; survives theme deploy).
		// Handled in maybe_save_credentials() on admin_init so redirect happens before any output.

		// Show credentials-saved notice.
		if ( $user_can && isset( $_GET['credentials_saved'] ) && $_GET['credentials_saved'] === '1' ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Credentials saved. They are stored in the database and will survive theme updates.', 'jww-theme' ) . '</p></div>';
		}

		// Show Threads OAuth callback result if present.
		if ( $user_can && isset( $_GET['threads_oauth'] ) ) {
			$oauth_msg = get_transient( 'jww_threads_oauth_message' );
			if ( is_array( $oauth_msg ) && ! empty( $oauth_msg['message'] ) ) {
				delete_transient( 'jww_threads_oauth_message' );
				$type = ( isset( $oauth_msg['type'] ) && $oauth_msg['type'] === 'success' ) ? 'success' : 'error';
				echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $oauth_msg['message'] ) . '</p></div>';
			}
		}
		// Show Facebook OAuth callback result if present.
		if ( $user_can && isset( $_GET['facebook_oauth'] ) ) {
			$oauth_msg = get_transient( 'jww_facebook_oauth_message' );
			if ( is_array( $oauth_msg ) && ! empty( $oauth_msg['message'] ) ) {
				delete_transient( 'jww_facebook_oauth_message' );
				$type = ( isset( $oauth_msg['type'] ) && $oauth_msg['type'] === 'success' ) ? 'success' : 'error';
				echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $oauth_msg['message'] ) . '</p></div>';
			}
		}
		// Show Instagram OAuth callback result if present.
		if ( $user_can && isset( $_GET['instagram_oauth'] ) ) {
			$oauth_msg = get_transient( 'jww_instagram_oauth_message' );
			if ( is_array( $oauth_msg ) && ! empty( $oauth_msg['message'] ) ) {
				delete_transient( 'jww_instagram_oauth_message' );
				$type = ( isset( $oauth_msg['type'] ) && $oauth_msg['type'] === 'success' ) ? 'success' : 'error';
				echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $oauth_msg['message'] ) . '</p></div>';
			}
		}
		?>
		<div class="wrap jww-social-admin-wrap">
			<h1><?php esc_html_e( 'Social Sharing', 'jww-theme' ); ?></h1>

			<nav class="nav-tab-wrapper jww-social-nav-tabs" aria-label="<?php esc_attr_e( 'Social sharing sections', 'jww-theme' ); ?>">
				<a href="#jww-social-tab-connections" class="nav-tab nav-tab-active" data-tab="connections" role="tab" aria-selected="true" aria-controls="jww-social-tab-connections"><?php esc_html_e( 'Connections', 'jww-theme' ); ?></a>
				<a href="#jww-social-tab-triggers" class="nav-tab" data-tab="triggers" role="tab" aria-selected="false" aria-controls="jww-social-tab-triggers"><?php esc_html_e( 'Triggers & messages', 'jww-theme' ); ?></a>
				<a href="#jww-social-tab-test" class="nav-tab" data-tab="test" role="tab" aria-selected="false" aria-controls="jww-social-tab-test"><?php esc_html_e( 'Test & log', 'jww-theme' ); ?></a>
			</nav>

			<div id="jww-social-tab-connections" class="jww-social-tab-panel" role="tabpanel" aria-labelledby="jww-social-tab-connections">
			<section class="jww-social-section jww-social-status">
				<h2><?php esc_html_e( 'Connection status', 'jww-theme' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Credentials can be stored in the database below (recommended — survives theme updates) or in the theme .env file. For Threads, Facebook, and Instagram, add App ID and Secret (for Re-authorize), then use the Authorize button to create access tokens. Toggle each platform on or off to include it in event triggers and cron.', 'jww-theme' ); ?></p>
				<ul class="jww-social-status-list">
					<?php
					$labels = array(
						'mastodon' => __( 'Mastodon', 'jww-theme' ),
						'bluesky'  => __( 'Bluesky', 'jww-theme' ),
						'pinterest' => __( 'Pinterest', 'jww-theme' ),
						'threads'  => __( 'Threads', 'jww-theme' ),
						'facebook' => __( 'Facebook', 'jww-theme' ),
						'instagram' => __( 'Instagram', 'jww-theme' ),
					);
					foreach ( $status as $service => $info ) :
						$name = isset( $labels[ $service ] ) ? $labels[ $service ] : $service;
						$class = $info['configured'] ? 'configured' : 'not-configured';
						$text  = $info['configured']
							? ( $info['label'] ? sprintf( __( 'Configured (%s)', 'jww-theme' ), esc_html( $info['label'] ) ) : __( 'Configured', 'jww-theme' ) )
							: __( 'Not configured', 'jww-theme' );
						$enabled = ! empty( $info['enabled'] );
					?>
						<li class="jww-social-status-item jww-social-status-<?php echo esc_attr( $class ); ?>">
							<span class="jww-social-status-dot" aria-hidden="true"></span>
							<span class="jww-social-status-text">
								<strong><?php echo esc_html( $name ); ?>:</strong> <?php echo esc_html( $text ); ?>
							</span>
							<?php if ( $user_can ) : ?>
								<label class="jww-social-toggle-wrap" title="<?php esc_attr_e( 'Include this platform in event triggers and cron', 'jww-theme' ); ?>">
									<input type="checkbox" class="jww-social-channel-toggle" data-channel="<?php echo esc_attr( $service ); ?>" <?php checked( $enabled ); ?> />
									<span class="jww-social-toggle-label"><?php esc_html_e( 'Include in posts', 'jww-theme' ); ?></span>
								</label>
								<?php
								if ( $service === 'threads' && class_exists( 'Threads_OAuth' ) ) {
									$auth_url = Threads_OAuth::get_authorize_url();
									if ( $auth_url ) {
										$auth_label = $info['configured'] ? __( 'Re-authorize Threads', 'jww-theme' ) : __( 'Authorize Threads', 'jww-theme' );
										echo ' <a href="' . esc_url( $auth_url ) . '" class="button button-small">' . esc_html( $auth_label ) . '</a>';
									}
								}
								if ( $service === 'facebook' && class_exists( 'Facebook_OAuth' ) ) {
									$auth_url = Facebook_OAuth::get_authorize_url();
									if ( $auth_url ) {
										$auth_label = $info['configured'] ? __( 'Re-authorize Facebook', 'jww-theme' ) : __( 'Authorize Facebook', 'jww-theme' );
										echo ' <a href="' . esc_url( $auth_url ) . '" class="button button-small">' . esc_html( $auth_label ) . '</a>';
									}
								}
								if ( $service === 'instagram' && class_exists( 'Instagram_OAuth' ) ) {
									$auth_url = Instagram_OAuth::get_authorize_url();
									if ( $auth_url ) {
										$auth_label = $info['configured'] ? __( 'Re-authorize Instagram', 'jww-theme' ) : __( 'Authorize Instagram', 'jww-theme' );
										echo ' <a href="' . esc_url( $auth_url ) . '" class="button button-small">' . esc_html( $auth_label ) . '</a>';
									}
								}
								?>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>

			<?php if ( $user_can ) : ?>
			<section class="jww-social-section jww-social-credentials">
				<h2><?php esc_html_e( 'Credentials (stored in database)', 'jww-theme' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Values saved here are stored in WordPress options and persist across theme updates. Leave secret fields blank to keep the current value. Required fields are listed under each platform.', 'jww-theme' ); ?></p>
				<form method="post" action="" class="jww-social-credentials-form">
					<?php wp_nonce_field( self::NONCE_ACTION_CREDENTIALS, '_wpnonce' ); ?>
					<input type="hidden" name="jww_social_save_credentials" value="1" />
					<?php
					$schema = $this->get_credentials_schema();
					$labels = array(
						'mastodon'  => __( 'Mastodon', 'jww-theme' ),
						'bluesky'   => __( 'Bluesky', 'jww-theme' ),
						'pinterest' => __( 'Pinterest', 'jww-theme' ),
						'threads'   => __( 'Threads', 'jww-theme' ),
						'facebook'  => __( 'Facebook', 'jww-theme' ),
						'instagram' => __( 'Instagram', 'jww-theme' ),
					);
					$prefix = 'jww_social_';
					foreach ( $schema as $service => $fields ) :
						$required_names = array_filter( array_map( function ( $f ) {
							return $f['required'] ? $f['label'] : null;
						}, $fields ) );
					?>
					<div class="jww-social-credentials-platform" data-service="<?php echo esc_attr( $service ); ?>">
						<h3 class="jww-social-credentials-platform-title"><?php echo esc_html( isset( $labels[ $service ] ) ? $labels[ $service ] : $service ); ?></h3>
						<?php if ( ! empty( $required_names ) ) : ?>
						<p class="jww-social-credentials-required"><?php esc_html_e( 'Required:', 'jww-theme' ); ?> <?php echo esc_html( implode( ', ', $required_names ) ); ?></p>
						<?php endif; ?>
						<div class="jww-social-credentials-fields">
							<?php foreach ( $fields as $field ) :
								$opt_key = $prefix . $field['key'];
								$current = get_option( $opt_key, '' );
								$input_name = 'jww_social_cred_' . $field['key'];
								$input_type = $field['secret'] ? 'password' : 'text';
								$placeholder = $field['secret'] && $current !== '' ? __( 'Leave blank to keep current', 'jww-theme' ) : '';
								$value = $field['secret'] ? '' : $current; // Never prefill secrets in HTML
								$masked = $this->mask_credential_display( $current );
							?>
							<p class="jww-social-cred-field">
								<label for="<?php echo esc_attr( $input_name ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
								<input type="<?php echo esc_attr( $input_type ); ?>" id="<?php echo esc_attr( $input_name ); ?>" name="<?php echo esc_attr( $input_name ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text" placeholder="<?php echo esc_attr( $placeholder ); ?>" autocomplete="<?php echo $field['secret'] ? 'off' : 'on'; ?>" />
								<?php if ( $masked !== '' ) : ?>
								<span class="jww-social-cred-saved"><?php echo esc_html( __( 'Saved:', 'jww-theme' ) . ' ' . $masked ); ?></span>
								<?php endif; ?>
							</p>
							<?php endforeach; ?>
						</div>
					</div>
					<?php endforeach; ?>
					<p class="submit">
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Save credentials', 'jww-theme' ); ?></button>
					</p>
				</form>
			</section>
			<?php endif; ?>

			</div><!-- #jww-social-tab-connections -->

			<div id="jww-social-tab-triggers" class="jww-social-tab-panel" role="tabpanel" aria-labelledby="jww-social-tab-triggers" hidden>
			<section class="jww-social-section jww-social-on-publish">
				<h2><?php esc_html_e( 'Sharing Triggers', 'jww-theme' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Choose when to automatically share to social. New post = share that post when it’s published. Setlist synced = when a show’s setlist is updated with 10+ songs (e.g. from setlist.fm). Anniversary = share again 1 and 2 years after publish date.', 'jww-theme' ); ?></p>
				<?php if ( $user_can ) : ?>
					<ul class="jww-social-on-publish-list">
						<li class="jww-social-on-publish-item">
							<label class="jww-social-toggle-wrap">
								<input type="checkbox" class="jww-social-on-publish-toggle" data-type="song" <?php checked( get_option( 'jww_social_on_publish_song', '1' ), '1' ); ?> />
								<span class="jww-social-toggle-label"><?php esc_html_e( 'Share when a new Song is published', 'jww-theme' ); ?></span>
							</label>
							<?php $this->render_message_template( 'song_publish', array( 'title', 'link' ), get_option( 'jww_social_on_publish_song', '1' ) === '1' ); ?>
						</li>
						<li class="jww-social-on-publish-item">
							<label class="jww-social-toggle-wrap">
								<input type="checkbox" class="jww-social-on-publish-toggle" data-type="show" <?php checked( get_option( 'jww_social_on_publish_show', '1' ), '1' ); ?> />
								<span class="jww-social-toggle-label"><?php esc_html_e( 'Share when a show\'s setlist is synced with 10+ songs (e.g. from setlist.fm)', 'jww-theme' ); ?></span>
							</label>
							<?php $this->render_message_template( 'show', array( 'title', 'link' ), get_option( 'jww_social_on_publish_show', '1' ) === '1' ); ?>
						</li>
						<li class="jww-social-on-publish-item">
							<label class="jww-social-toggle-wrap">
								<input type="checkbox" class="jww-social-on-publish-toggle" data-type="post" <?php checked( get_option( 'jww_social_on_publish_post', '0' ), '1' ); ?> />
								<span class="jww-social-toggle-label"><?php esc_html_e( 'Share when a new Blog post is published', 'jww-theme' ); ?></span>
							</label>
							<?php $this->render_message_template( 'post', array( 'title', 'link' ), get_option( 'jww_social_on_publish_post', '0' ) === '1' ); ?>
						</li>
						<li class="jww-social-on-publish-item">
							<label class="jww-social-toggle-wrap">
								<input type="checkbox" class="jww-social-on-publish-toggle" data-type="anniversary_song" <?php checked( get_option( 'jww_social_anniversary_song', '0' ), '1' ); ?> />
								<span class="jww-social-toggle-label"><?php esc_html_e( 'Share on song anniversary (1 and 2 years since published)', 'jww-theme' ); ?></span>
							</label>
							<?php $this->render_message_template( 'anniversary_song', array( 'title', 'link', 'years_ago' ), get_option( 'jww_social_anniversary_song', '0' ) === '1' ); ?>
						</li>
						<li class="jww-social-on-publish-item">
							<label class="jww-social-toggle-wrap">
								<input type="checkbox" class="jww-social-on-publish-toggle" data-type="anniversary_show" <?php checked( get_option( 'jww_social_anniversary_show', '0' ), '1' ); ?> />
								<span class="jww-social-toggle-label"><?php esc_html_e( 'Share on show anniversary (1 and 2 years since published)', 'jww-theme' ); ?></span>
							</label>
							<?php $this->render_message_template( 'anniversary_show', array( 'title', 'link', 'years_ago' ), get_option( 'jww_social_anniversary_show', '0' ) === '1' ); ?>
						</li>
					</ul>
				<?php endif; ?>
			</section>

			<section class="jww-social-section jww-social-cron">
				<h2><?php esc_html_e( 'Cron scheduling', 'jww-theme' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Set how often each random share type runs. Only random shares use this schedule.', 'jww-theme' ); ?></p>
				<?php if ( $user_can ) : ?>
					<table class="form-table jww-social-cron-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Random Song', 'jww-theme' ); ?></th>
							<td>
								<select class="jww-social-cron-schedule" data-type="song" aria-label="<?php esc_attr_e( 'Schedule for Random Song', 'jww-theme' ); ?>">
									<option value="0" <?php selected( (int) get_option( 'jww_social_cron_schedule_song', 24 ), 0 ); ?>><?php esc_html_e( 'Disabled', 'jww-theme' ); ?></option>
									<option value="8" <?php selected( (int) get_option( 'jww_social_cron_schedule_song', 24 ), 8 ); ?>><?php esc_html_e( 'Every 8 hours', 'jww-theme' ); ?></option>
									<option value="12" <?php selected( (int) get_option( 'jww_social_cron_schedule_song', 24 ), 12 ); ?>><?php esc_html_e( 'Every 12 hours', 'jww-theme' ); ?></option>
									<option value="24" <?php selected( (int) get_option( 'jww_social_cron_schedule_song', 24 ), 24 ); ?>><?php esc_html_e( 'Every 24 hours', 'jww-theme' ); ?></option>
									<option value="48" <?php selected( (int) get_option( 'jww_social_cron_schedule_song', 24 ), 48 ); ?>><?php esc_html_e( 'Every 48 hours', 'jww-theme' ); ?></option>
								</select>
							</td>
						</tr>
						<?php $this->render_cron_message_template_row( 'song', array( 'title', 'link' ), (int) get_option( 'jww_social_cron_schedule_song', 24 ) ); ?>
						<tr>
							<th scope="row"><?php esc_html_e( 'Random Lyric', 'jww-theme' ); ?></th>
							<td>
								<select class="jww-social-cron-schedule" data-type="lyric" aria-label="<?php esc_attr_e( 'Schedule for Random Lyric', 'jww-theme' ); ?>">
									<option value="0" <?php selected( (int) get_option( 'jww_social_cron_schedule_lyric', 24 ), 0 ); ?>><?php esc_html_e( 'Disabled', 'jww-theme' ); ?></option>
									<option value="8" <?php selected( (int) get_option( 'jww_social_cron_schedule_lyric', 24 ), 8 ); ?>><?php esc_html_e( 'Every 8 hours', 'jww-theme' ); ?></option>
									<option value="12" <?php selected( (int) get_option( 'jww_social_cron_schedule_lyric', 24 ), 12 ); ?>><?php esc_html_e( 'Every 12 hours', 'jww-theme' ); ?></option>
									<option value="24" <?php selected( (int) get_option( 'jww_social_cron_schedule_lyric', 24 ), 24 ); ?>><?php esc_html_e( 'Every 24 hours', 'jww-theme' ); ?></option>
									<option value="48" <?php selected( (int) get_option( 'jww_social_cron_schedule_lyric', 24 ), 48 ); ?>><?php esc_html_e( 'Every 48 hours', 'jww-theme' ); ?></option>
								</select>
							</td>
						</tr>
						<?php $this->render_cron_message_template_row( 'lyric', array( 'title', 'link', 'lyrics_line' ), (int) get_option( 'jww_social_cron_schedule_lyric', 24 ) ); ?>
						<tr>
							<th scope="row"><?php esc_html_e( 'Random Show', 'jww-theme' ); ?></th>
							<td>
								<select class="jww-social-cron-schedule" data-type="show" aria-label="<?php esc_attr_e( 'Schedule for Random Show', 'jww-theme' ); ?>">
									<option value="0" <?php selected( (int) get_option( 'jww_social_cron_schedule_show', 24 ), 0 ); ?>><?php esc_html_e( 'Disabled', 'jww-theme' ); ?></option>
									<option value="8" <?php selected( (int) get_option( 'jww_social_cron_schedule_show', 24 ), 8 ); ?>><?php esc_html_e( 'Every 8 hours', 'jww-theme' ); ?></option>
									<option value="12" <?php selected( (int) get_option( 'jww_social_cron_schedule_show', 24 ), 12 ); ?>><?php esc_html_e( 'Every 12 hours', 'jww-theme' ); ?></option>
									<option value="24" <?php selected( (int) get_option( 'jww_social_cron_schedule_show', 24 ), 24 ); ?>><?php esc_html_e( 'Every 24 hours', 'jww-theme' ); ?></option>
									<option value="48" <?php selected( (int) get_option( 'jww_social_cron_schedule_show', 24 ), 48 ); ?>><?php esc_html_e( 'Every 48 hours', 'jww-theme' ); ?></option>
								</select>
							</td>
						</tr>
						<?php $this->render_cron_message_template_row( 'show', array( 'title', 'link' ), (int) get_option( 'jww_social_cron_schedule_show', 24 ) ); ?>
						<tr>
							<th scope="row"><?php esc_html_e( 'Random Blog Post', 'jww-theme' ); ?></th>
							<td>
								<select class="jww-social-cron-schedule" data-type="post" aria-label="<?php esc_attr_e( 'Schedule for Random Blog Post', 'jww-theme' ); ?>">
									<option value="0" <?php selected( (int) get_option( 'jww_social_cron_schedule_post', 24 ), 0 ); ?>><?php esc_html_e( 'Disabled', 'jww-theme' ); ?></option>
									<option value="8" <?php selected( (int) get_option( 'jww_social_cron_schedule_post', 24 ), 8 ); ?>><?php esc_html_e( 'Every 8 hours', 'jww-theme' ); ?></option>
									<option value="12" <?php selected( (int) get_option( 'jww_social_cron_schedule_post', 24 ), 12 ); ?>><?php esc_html_e( 'Every 12 hours', 'jww-theme' ); ?></option>
									<option value="24" <?php selected( (int) get_option( 'jww_social_cron_schedule_post', 24 ), 24 ); ?>><?php esc_html_e( 'Every 24 hours', 'jww-theme' ); ?></option>
									<option value="48" <?php selected( (int) get_option( 'jww_social_cron_schedule_post', 24 ), 48 ); ?>><?php esc_html_e( 'Every 48 hours', 'jww-theme' ); ?></option>
								</select>
							</td>
						</tr>
						<?php $this->render_cron_message_template_row( 'post', array( 'title', 'link' ), (int) get_option( 'jww_social_cron_schedule_post', 24 ) ); ?>
					</table>
				<?php endif; ?>
			</section>
			</div><!-- #jww-social-tab-triggers -->

			<div id="jww-social-tab-test" class="jww-social-tab-panel" role="tabpanel" aria-labelledby="jww-social-tab-test" hidden>
			<section class="jww-social-section jww-social-triggers">
				<h2><?php esc_html_e( 'Manual triggers', 'jww-theme' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Run the same jobs that cron runs, or pick a specific post to share. Results are posted to configured services and logged below.', 'jww-theme' ); ?></p>
				<?php if ( $user_can ) : ?>
					<?php
					$songs = $this->get_posts_for_dropdown( 'song' );
					$shows = $this->get_posts_for_dropdown( 'show' );
					$posts = $this->get_posts_for_dropdown( 'post' );
					?>
					<div class="jww-social-trigger-rows">
						<div class="jww-social-trigger-row">
							<div class="jww-social-trigger-random">
								<button type="button" class="button button-primary jww-social-trigger" data-action="jww_social_trigger_random_song"><?php esc_html_e( 'Random Song', 'jww-theme' ); ?></button>
							</div>
							<div class="jww-social-trigger-specific">
								<select id="jww-social-select-song" class="jww-social-select-post" data-post-type="song" aria-label="<?php esc_attr_e( 'Select a song', 'jww-theme' ); ?>">
									<option value=""><?php esc_html_e( 'Select a song…', 'jww-theme' ); ?></option>
									<?php foreach ( $songs as $item ) : ?>
										<option value="<?php echo (int) $item['id']; ?>"><?php echo esc_html( $item['title'] ); ?></option>
									<?php endforeach; ?>
								</select>
								<button type="button" class="button jww-social-share-specific" data-type="song" data-select-id="jww-social-select-song"><?php esc_html_e( 'Share selected', 'jww-theme' ); ?></button>
							</div>
						</div>
						<div class="jww-social-trigger-row">
							<div class="jww-social-trigger-random">
								<button type="button" class="button button-primary jww-social-trigger" data-action="jww_social_trigger_random_lyric"><?php esc_html_e( 'Random Lyric', 'jww-theme' ); ?></button>
							</div>
							<div class="jww-social-trigger-specific">
								<select id="jww-social-select-lyric-song" class="jww-social-select-post" data-post-type="song" aria-label="<?php esc_attr_e( 'Select a song for lyric', 'jww-theme' ); ?>">
									<option value=""><?php esc_html_e( 'Select a song…', 'jww-theme' ); ?></option>
									<?php foreach ( $songs as $item ) : ?>
										<option value="<?php echo (int) $item['id']; ?>"><?php echo esc_html( $item['title'] ); ?></option>
									<?php endforeach; ?>
								</select>
								<select id="jww-social-select-lyric-line" class="jww-social-select-lyric" aria-label="<?php esc_attr_e( 'Select lyric line', 'jww-theme' ); ?>" disabled>
									<option value=""><?php esc_html_e( 'Select a song first', 'jww-theme' ); ?></option>
								</select>
								<button type="button" class="button jww-social-share-specific" data-type="lyric" data-select-id="jww-social-select-lyric-song" data-lyric-select-id="jww-social-select-lyric-line"><?php esc_html_e( 'Share selected', 'jww-theme' ); ?></button>
							</div>
						</div>
						<div class="jww-social-trigger-row">
							<div class="jww-social-trigger-random">
								<button type="button" class="button button-primary jww-social-trigger" data-action="jww_social_trigger_random_show"><?php esc_html_e( 'Random Show', 'jww-theme' ); ?></button>
							</div>
							<div class="jww-social-trigger-specific">
								<select id="jww-social-select-show" class="jww-social-select-post" data-post-type="show" aria-label="<?php esc_attr_e( 'Select a show', 'jww-theme' ); ?>">
									<option value=""><?php esc_html_e( 'Select a show…', 'jww-theme' ); ?></option>
									<?php foreach ( $shows as $item ) : ?>
										<option value="<?php echo (int) $item['id']; ?>"><?php echo esc_html( $item['title'] ); ?></option>
									<?php endforeach; ?>
								</select>
								<button type="button" class="button jww-social-share-specific" data-type="show" data-select-id="jww-social-select-show"><?php esc_html_e( 'Share selected', 'jww-theme' ); ?></button>
							</div>
						</div>
						<div class="jww-social-trigger-row">
							<div class="jww-social-trigger-random">
								<button type="button" class="button button-primary jww-social-trigger" data-action="jww_social_trigger_random_post"><?php esc_html_e( 'Random Blog Post', 'jww-theme' ); ?></button>
							</div>
							<div class="jww-social-trigger-specific">
								<select id="jww-social-select-post" class="jww-social-select-post" data-post-type="post" aria-label="<?php esc_attr_e( 'Select a post', 'jww-theme' ); ?>">
									<option value=""><?php esc_html_e( 'Select a post…', 'jww-theme' ); ?></option>
									<?php foreach ( $posts as $item ) : ?>
										<option value="<?php echo (int) $item['id']; ?>"><?php echo esc_html( $item['title'] ); ?></option>
									<?php endforeach; ?>
								</select>
								<button type="button" class="button jww-social-share-specific" data-type="post" data-select-id="jww-social-select-post"><?php esc_html_e( 'Share selected', 'jww-theme' ); ?></button>
							</div>
						</div>
					</div>
				<?php else : ?>
					<p><?php esc_html_e( 'You do not have permission to run triggers.', 'jww-theme' ); ?></p>
				<?php endif; ?>
			</section>

			<section class="jww-social-section jww-social-log-section">
				<h2><?php esc_html_e( 'Event log', 'jww-theme' ); ?></h2>
				<div id="jww-social-log" class="jww-social-log" role="log" aria-live="polite">
					<p class="jww-social-log-empty"><?php esc_html_e( 'Trigger an event above to see results here.', 'jww-theme' ); ?></p>
				</div>
				<?php if ( $user_can ) : ?>
					<button type="button" class="button button-secondary" id="jww-social-log-clear"><?php esc_html_e( 'Clear log', 'jww-theme' ); ?></button>
				<?php endif; ?>
			</section>
			</div><!-- #jww-social-tab-test -->
		</div>
		<?php
	}

	/**
	 * AJAX: trigger Random Song job. Requires manage_options and nonce.
	 */
	public function ajax_trigger_random_song() {
		$this->ajax_trigger( 'jww_social_run_random_song', __( 'Random Song', 'jww-theme' ) );
	}

	/**
	 * AJAX: trigger Random Lyric job.
	 */
	public function ajax_trigger_random_lyric() {
		$this->ajax_trigger( 'jww_social_run_random_lyric', __( 'Random Lyric', 'jww-theme' ) );
	}

	/**
	 * AJAX: trigger Random Post job.
	 */
	public function ajax_trigger_random_post() {
		$this->ajax_trigger( 'jww_social_run_random_post', __( 'Random Post', 'jww-theme' ) );
	}

	/**
	 * AJAX: trigger Random Show job.
	 */
	public function ajax_trigger_random_show() {
		$this->ajax_trigger( 'jww_social_run_random_show', __( 'Random Show', 'jww-theme' ) );
	}

	/**
	 * AJAX: toggle channel enabled state. Saves to option jww_social_{channel}_enabled ('1' or '0').
	 */
	public function ajax_toggle_channel() {
		check_ajax_referer( self::NONCE_ACTION_TOGGLE, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'jww-theme' ) ) );
		}
		$channel = isset( $_POST['channel'] ) ? sanitize_key( $_POST['channel'] ) : '';
		$allowed = array( 'mastodon', 'bluesky', 'pinterest', 'threads', 'facebook', 'instagram' );
		if ( ! in_array( $channel, $allowed, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid channel.', 'jww-theme' ) ) );
		}
		$enabled = isset( $_POST['enabled'] ) && $_POST['enabled'] === '1';
		update_option( 'jww_social_' . $channel . '_enabled', $enabled ? '1' : '0' );
		wp_send_json_success( array( 'enabled' => $enabled ) );
	}

	/**
	 * AJAX: get lyrics lines for a song (for lyric dropdown). Returns array of { index, text }.
	 */
	public function ajax_get_lyrics_for_song() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'jww-theme' ) ) );
		}
		$song_id = isset( $_POST['song_id'] ) ? (int) $_POST['song_id'] : 0;
		if ( $song_id <= 0 ) {
			wp_send_json_success( array( 'lines' => array() ) );
		}
		$lines = function_exists( 'jww_social_get_lyrics_lines_for_song' ) ? jww_social_get_lyrics_lines_for_song( $song_id ) : array();
		$out = array();
		foreach ( $lines as $i => $text ) {
			$first_line = trim( strpos( $text, "\n" ) !== false ? substr( $text, 0, strpos( $text, "\n" ) ) : $text );
			$label = strlen( $first_line ) > 60 ? substr( $first_line, 0, 57 ) . '…' : $first_line;
			$out[] = array( 'index' => $i, 'text' => $text, 'label' => $label );
		}
		wp_send_json_success( array( 'lines' => $out ) );
	}

	/**
	 * AJAX: share a specific song. Same response shape as ajax_trigger.
	 */
	public function ajax_share_specific_song() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'jww-theme' ) ) );
		}
		$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		if ( $post_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post.', 'jww-theme' ) ) );
		}
		$out = function_exists( 'jww_social_run_specific_song' ) ? jww_social_run_specific_song( $post_id ) : array( 'ran' => false );
		$this->send_trigger_response( $out, __( 'Song', 'jww-theme' ) );
	}

	/**
	 * AJAX: share a specific lyric (song + line index). Same response shape as ajax_trigger.
	 */
	public function ajax_share_specific_lyric() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'jww-theme' ) ) );
		}
		$song_id = isset( $_POST['song_id'] ) ? (int) $_POST['song_id'] : 0;
		$line_index = isset( $_POST['line_index'] ) ? sanitize_text_field( $_POST['line_index'] ) : 'random';
		if ( $song_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid song.', 'jww-theme' ) ) );
		}
		$idx = ( $line_index === 'random' || $line_index === '' ) ? -1 : (int) $line_index;
		$out = function_exists( 'jww_social_run_specific_lyric' ) ? jww_social_run_specific_lyric( $song_id, $idx ) : array( 'ran' => false );
		$this->send_trigger_response( $out, __( 'Lyric', 'jww-theme' ) );
	}

	/**
	 * AJAX: share a specific show.
	 */
	public function ajax_share_specific_show() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'jww-theme' ) ) );
		}
		$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		if ( $post_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post.', 'jww-theme' ) ) );
		}
		$out = function_exists( 'jww_social_run_specific_show' ) ? jww_social_run_specific_show( $post_id ) : array( 'ran' => false );
		$this->send_trigger_response( $out, __( 'Show', 'jww-theme' ) );
	}

	/**
	 * AJAX: share a specific blog post.
	 */
	public function ajax_share_specific_post() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'jww-theme' ) ) );
		}
		$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		if ( $post_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post.', 'jww-theme' ) ) );
		}
		$out = function_exists( 'jww_social_run_specific_post' ) ? jww_social_run_specific_post( $post_id ) : array( 'ran' => false );
		$this->send_trigger_response( $out, __( 'Post', 'jww-theme' ) );
	}

	/**
	 * AJAX: save cron schedule for a type and reschedule.
	 */
	public function ajax_save_cron_schedule() {
		check_ajax_referer( self::NONCE_ACTION_SETTINGS, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'jww-theme' ) ) );
		}
		$type = isset( $_POST['type'] ) ? sanitize_key( $_POST['type'] ) : '';
		$hours = isset( $_POST['hours'] ) ? (int) $_POST['hours'] : -1;
		$allowed_types = array( 'song', 'lyric', 'show', 'post' );
		$allowed_hours = array( 0, 8, 12, 24, 48 );
		if ( ! in_array( $type, $allowed_types, true ) || ! in_array( $hours, $allowed_hours, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'jww-theme' ) ) );
		}
		$option_key = 'jww_social_cron_schedule_' . $type;
		update_option( $option_key, (string) $hours );
		if ( function_exists( 'jww_social_reschedule_cron' ) ) {
			jww_social_reschedule_cron( $type );
		}
		wp_send_json_success( array( 'saved' => true ) );
	}

	/**
	 * AJAX: save on-publish or anniversary trigger for a type.
	 */
	public function ajax_save_on_publish() {
		check_ajax_referer( self::NONCE_ACTION_SETTINGS, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'jww-theme' ) ) );
		}
		$type = isset( $_POST['type'] ) ? sanitize_key( $_POST['type'] ) : '';
		$enabled = isset( $_POST['enabled'] ) && $_POST['enabled'] === '1';
		$allowed = array( 'song', 'show', 'post', 'anniversary_song', 'anniversary_show' );
		if ( ! in_array( $type, $allowed, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid type.', 'jww-theme' ) ) );
		}
		if ( in_array( $type, array( 'anniversary_song', 'anniversary_show' ), true ) ) {
			update_option( 'jww_social_' . $type, $enabled ? '1' : '0' );
		} else {
			update_option( 'jww_social_on_publish_' . $type, $enabled ? '1' : '0' );
		}
		wp_send_json_success( array( 'enabled' => $enabled ) );
	}

	/**
	 * AJAX: save status text template for a key.
	 */
	public function ajax_save_status_text() {
		check_ajax_referer( self::NONCE_ACTION_SETTINGS, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'jww-theme' ) ) );
		}
		$key = isset( $_POST['template_key'] ) ? sanitize_key( $_POST['template_key'] ) : '';
		$allowed = array( 'song', 'song_publish', 'show', 'post', 'lyric', 'anniversary_song', 'anniversary_show' );
		if ( ! in_array( $key, $allowed, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid template key.', 'jww-theme' ) ) );
		}
		$value = isset( $_POST['value'] ) ? wp_unslash( $_POST['value'] ) : '';
		update_option( 'jww_social_status_text_' . $key, is_string( $value ) ? $value : '' );
		wp_send_json_success( array( 'saved' => true ) );
	}

	/**
	 * Send JSON response for a trigger (random or specific). Builds log lines and sends success.
	 *
	 * @param array  $out       Output from run_random_* or run_specific_*.
	 * @param string $job_label Label for log.
	 */
	protected function send_trigger_response( $out, $job_label ) {
		$log = array();
		$timestamp = wp_date( 'Y-m-d H:i:s' );
		if ( empty( $out['ran'] ) ) {
			$log[] = $timestamp . ' — ' . $job_label . ': ' . ( isset( $out['error'] ) ? $out['error'] : __( 'Did not run.', 'jww-theme' ) );
			wp_send_json_success( array( 'log' => $log, 'ran' => false ) );
		}
		$log[] = $timestamp . ' — ' . $job_label . ' — ' . ( isset( $out['payload']['title'] ) ? $out['payload']['title'] : ( $out['payload']['type'] ?? '' ) );
		if ( ! empty( $out['debug'] ) && is_array( $out['debug'] ) ) {
			foreach ( $out['debug'] as $line ) {
				$log[] = $line;
			}
		}
		foreach ( $out['results'] as $channel => $result ) {
			$channel_name = ucfirst( $channel );
			if ( $result === true ) {
				$log[] = '  ' . $channel_name . ': ' . __( 'OK', 'jww-theme' );
			} else {
				$msg = is_wp_error( $result ) ? $result->get_error_message() : (string) $result;
				$log[] = '  ' . $channel_name . ': ' . __( 'Error', 'jww-theme' ) . ' — ' . $msg;
			}
		}
		wp_send_json_success( array(
			'log'     => $log,
			'ran'     => true,
			'results' => $this->results_for_json( $out['results'] ),
			'payload' => isset( $out['payload'] ) ? $out['payload'] : null,
		) );
	}

	/**
	 * Run a social job and send JSON response with results and log lines.
	 *
	 * @param string $runner Function name that returns array( ran, results?, payload?, error? ).
	 * @param string $job_label Label for log (e.g. "Random Song").
	 */
	protected function ajax_trigger( $runner, $job_label ) {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'jww-theme' ) ) );
		}
		if ( ! function_exists( $runner ) ) {
			wp_send_json_error( array( 'message' => __( 'Runner not available.', 'jww-theme' ) ) );
		}
		$out = call_user_func( $runner );
		$this->send_trigger_response( $out, $job_label );
	}

	/**
	 * Convert results (true|WP_Error) to JSON-safe array.
	 *
	 * @param array $results Channel => true|WP_Error.
	 * @return array Channel => [ 'ok' => bool, 'message' => string ]
	 */
	protected function results_for_json( $results ) {
		$out = array();
		foreach ( $results as $channel => $result ) {
			$out[ $channel ] = array(
				'ok'      => $result === true,
				'message' => is_wp_error( $result ) ? $result->get_error_message() : '',
			);
		}
		return $out;
	}
}
