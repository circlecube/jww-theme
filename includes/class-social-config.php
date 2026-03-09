<?php
/**
 * Social sharing configuration – reads credentials from .env or WordPress options.
 *
 * Priority: .env file > WordPress options. Never commit real credentials; use .env (in .gitignore).
 *
 * @package JWW_Theme
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get social config for a service. Returns only what's needed for the client; do not log return values.
 *
 * @param string $service One of: mastodon, bluesky, pinterest, threads, facebook, instagram.
 * @return array|false Config array for the service, or false if not configured.
 */
function jww_social_get_config( $service ) {
	static $env_cache = null;

	if ( $env_cache === null ) {
		$env_cache = jww_social_parse_env();
	}

	$option_prefix = 'jww_social_';

	switch ( $service ) {
		case 'mastodon':
			$instance = isset( $env_cache['JWW_MASTODON_INSTANCE'] ) ? $env_cache['JWW_MASTODON_INSTANCE'] : get_option( $option_prefix . 'mastodon_instance', '' );
			$token   = isset( $env_cache['JWW_MASTODON_ACCESS_TOKEN'] ) ? $env_cache['JWW_MASTODON_ACCESS_TOKEN'] : get_option( $option_prefix . 'mastodon_access_token', '' );
			$instance = is_string( $instance ) ? trim( $instance ) : '';
			$token   = is_string( $token ) ? trim( $token ) : '';
			if ( $instance === '' || $token === '' ) {
				return false;
			}
			return array(
				'instance'     => preg_replace( '#^https?://#i', '', rtrim( $instance, '/' ) ),
				'access_token' => $token,
			);

		case 'bluesky':
			$identifier = isset( $env_cache['JWW_BLUESKY_IDENTIFIER'] ) ? $env_cache['JWW_BLUESKY_IDENTIFIER'] : get_option( $option_prefix . 'bluesky_identifier', '' );
			$password  = isset( $env_cache['JWW_BLUESKY_APP_PASSWORD'] ) ? $env_cache['JWW_BLUESKY_APP_PASSWORD'] : get_option( $option_prefix . 'bluesky_app_password', '' );
			$identifier = is_string( $identifier ) ? trim( $identifier ) : '';
			$password  = is_string( $password ) ? trim( $password ) : '';
			if ( $identifier === '' || $password === '' ) {
				return false;
			}
			return array(
				'identifier' => $identifier,
				'password'   => $password,
			);

		case 'pinterest':
			$token    = isset( $env_cache['JWW_PINTEREST_ACCESS_TOKEN'] ) ? $env_cache['JWW_PINTEREST_ACCESS_TOKEN'] : get_option( $option_prefix . 'pinterest_access_token', '' );
			$board_id = isset( $env_cache['JWW_PINTEREST_BOARD_ID'] ) ? $env_cache['JWW_PINTEREST_BOARD_ID'] : get_option( $option_prefix . 'pinterest_board_id', '' );
			$board_song = isset( $env_cache['JWW_PINTEREST_BOARD_ID_SONG'] ) ? $env_cache['JWW_PINTEREST_BOARD_ID_SONG'] : get_option( $option_prefix . 'pinterest_board_id_song', '' );
			$board_show = isset( $env_cache['JWW_PINTEREST_BOARD_ID_SHOW'] ) ? $env_cache['JWW_PINTEREST_BOARD_ID_SHOW'] : get_option( $option_prefix . 'pinterest_board_id_show', '' );
			$board_lyric = isset( $env_cache['JWW_PINTEREST_BOARD_ID_LYRIC'] ) ? $env_cache['JWW_PINTEREST_BOARD_ID_LYRIC'] : get_option( $option_prefix . 'pinterest_board_id_lyric', '' );
			$token    = is_string( $token ) ? trim( $token ) : '';
			$board_id = is_string( $board_id ) ? trim( $board_id ) : '';
			$board_song = is_string( $board_song ) ? trim( $board_song ) : '';
			$board_show = is_string( $board_show ) ? trim( $board_show ) : '';
			$board_lyric = is_string( $board_lyric ) ? trim( $board_lyric ) : '';
			if ( $token === '' || $board_id === '' ) {
				return false;
			}
			$out = array(
				'access_token' => $token,
				'board_id'     => $board_id,
			);
			if ( $board_song !== '' ) {
				$out['board_id_song'] = $board_song;
			}
			if ( $board_show !== '' ) {
				$out['board_id_show'] = $board_show;
			}
			if ( $board_lyric !== '' ) {
				$out['board_id_lyric'] = $board_lyric;
			}
			return $out;

		case 'threads':
			$user_id = isset( $env_cache['JWW_THREADS_USER_ID'] ) ? $env_cache['JWW_THREADS_USER_ID'] : get_option( $option_prefix . 'threads_user_id', '' );
			$token   = isset( $env_cache['JWW_THREADS_ACCESS_TOKEN'] ) ? $env_cache['JWW_THREADS_ACCESS_TOKEN'] : get_option( $option_prefix . 'threads_access_token', '' );
			$user_id = is_string( $user_id ) ? trim( $user_id ) : '';
			$token   = is_string( $token ) ? trim( $token ) : '';
			if ( $user_id === '' || $token === '' ) {
				return false;
			}
			return array(
				'user_id' => $user_id,
				'access_token' => $token,
			);

		case 'facebook':
			$page_id = isset( $env_cache['JWW_FACEBOOK_PAGE_ID'] ) ? $env_cache['JWW_FACEBOOK_PAGE_ID'] : get_option( $option_prefix . 'facebook_page_id', '' );
			$token   = isset( $env_cache['JWW_FACEBOOK_PAGE_ACCESS_TOKEN'] ) ? $env_cache['JWW_FACEBOOK_PAGE_ACCESS_TOKEN'] : get_option( $option_prefix . 'facebook_page_access_token', '' );
			$page_id = is_string( $page_id ) ? trim( $page_id ) : '';
			$token   = is_string( $token ) ? trim( $token ) : '';
			if ( $page_id === '' || $token === '' ) {
				return false;
			}
			return array(
				'page_id'      => $page_id,
				'access_token' => $token,
			);

		case 'instagram':
			$ig_user_id = isset( $env_cache['JWW_INSTAGRAM_ACCOUNT_ID'] ) ? $env_cache['JWW_INSTAGRAM_ACCOUNT_ID'] : get_option( $option_prefix . 'instagram_account_id', '' );
			$token      = isset( $env_cache['JWW_INSTAGRAM_ACCESS_TOKEN'] ) ? $env_cache['JWW_INSTAGRAM_ACCESS_TOKEN'] : get_option( $option_prefix . 'instagram_access_token', '' );
			$ig_user_id = is_string( $ig_user_id ) ? trim( $ig_user_id ) : '';
			$token      = is_string( $token ) ? trim( $token ) : '';
			if ( $ig_user_id === '' || $token === '' ) {
				return false;
			}
			return array(
				'ig_user_id'   => $ig_user_id,
				'access_token' => $token,
			);

		default:
			return false;
	}
}

/**
 * Whether a social channel is enabled for posting. Disabled channels are skipped by the dispatcher (triggers and cron).
 * Stored in WordPress options; toggled on the Social Sharing admin page.
 *
 * @param string $channel One of: mastodon, bluesky, pinterest, threads, facebook, instagram.
 * @return bool True if the channel should be included when dispatching.
 */
function jww_social_is_channel_enabled( $channel ) {
	$allowed = array( 'mastodon', 'bluesky', 'pinterest', 'threads', 'facebook', 'instagram' );
	if ( ! in_array( $channel, $allowed, true ) ) {
		return false;
	}
	$value = get_option( 'jww_social_' . $channel . '_enabled', '1' );
	return $value !== '0' && $value !== '';
}

/**
 * Get Threads App ID and App Secret for OAuth (token exchange). Used only for the "Re-authorize Threads" flow.
 * Priority: .env (JWW_THREADS_APP_ID, JWW_THREADS_APP_SECRET) then options (jww_social_threads_app_id, jww_social_threads_app_secret).
 *
 * @return array{app_id: string, app_secret: string}|false Array with app_id and app_secret, or false if either is missing.
 */
function jww_social_get_threads_app_credentials() {
	static $env_cache = null;
	if ( $env_cache === null ) {
		$env_cache = jww_social_parse_env();
	}
	$option_prefix = 'jww_social_';
	$app_id     = isset( $env_cache['JWW_THREADS_APP_ID'] ) ? $env_cache['JWW_THREADS_APP_ID'] : get_option( $option_prefix . 'threads_app_id', '' );
	$app_secret = isset( $env_cache['JWW_THREADS_APP_SECRET'] ) ? $env_cache['JWW_THREADS_APP_SECRET'] : get_option( $option_prefix . 'threads_app_secret', '' );
	$app_id     = is_string( $app_id ) ? trim( $app_id ) : '';
	$app_secret = is_string( $app_secret ) ? trim( $app_secret ) : '';
	if ( $app_id === '' || $app_secret === '' ) {
		return false;
	}
	return array(
		'app_id'     => $app_id,
		'app_secret' => $app_secret,
	);
}

/**
 * Get Facebook App ID and App Secret for OAuth (used for "Re-authorize Facebook" flow).
 * Use your main Meta app's App ID and App Secret (App settings → Basic). Same app can have Facebook Login, Pages, Threads, Instagram.
 * Priority: .env (JWW_FACEBOOK_APP_ID, JWW_FACEBOOK_APP_SECRET) then options (jww_social_facebook_app_id, jww_social_facebook_app_secret).
 *
 * @return array{app_id: string, app_secret: string}|false Array with app_id and app_secret, or false if either is missing.
 */
function jww_social_get_facebook_app_credentials() {
	static $env_cache = null;
	if ( $env_cache === null ) {
		$env_cache = jww_social_parse_env();
	}
	$option_prefix = 'jww_social_';
	$app_id     = isset( $env_cache['JWW_FACEBOOK_APP_ID'] ) ? $env_cache['JWW_FACEBOOK_APP_ID'] : get_option( $option_prefix . 'facebook_app_id', '' );
	$app_secret = isset( $env_cache['JWW_FACEBOOK_APP_SECRET'] ) ? $env_cache['JWW_FACEBOOK_APP_SECRET'] : get_option( $option_prefix . 'facebook_app_secret', '' );
	$app_id     = is_string( $app_id ) ? trim( $app_id ) : '';
	$app_secret = is_string( $app_secret ) ? trim( $app_secret ) : '';
	if ( $app_id === '' || $app_secret === '' ) {
		return false;
	}
	return array(
		'app_id'     => $app_id,
		'app_secret' => $app_secret,
	);
}

/**
 * Parse theme .env file into key => value array. Cached for the request.
 *
 * @return array Keys are env variable names (e.g. JWW_MASTODON_INSTANCE), values are trimmed (quotes removed).
 */
function jww_social_parse_env() {
	$env_file = get_stylesheet_directory() . '/.env';
	if ( ! file_exists( $env_file ) || ! is_readable( $env_file ) ) {
		return array();
	}
	$content = file_get_contents( $env_file );
	if ( $content === false ) {
		return array();
	}
	$out = array();
	$lines = explode( "\n", $content );
	foreach ( $lines as $line ) {
		$line = trim( $line );
		if ( $line === '' || $line[0] === '#' ) {
			continue;
		}
		if ( preg_match( '/^([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $line, $m ) ) {
			$key   = $m[1];
			$value = trim( $m[2] );
			$value = trim( $value, '"\'`' );
			$out[ $key ] = $value;
		}
	}
	return $out;
}
