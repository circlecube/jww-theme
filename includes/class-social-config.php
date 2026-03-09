<?php
/**
 * Social sharing configuration – reads credentials from WordPress options or .env.
 *
 * Priority: options (saved in Settings → Social Sharing) > .env. Storing credentials in the
 * database survives theme updates; .env in the theme directory is often overwritten when
 * deploying a new theme zip.
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

	// Prefer options (DB) over .env so credentials survive theme deploy.
	$from_option = function( $key ) use ( $option_prefix ) {
		return trim( (string) get_option( $option_prefix . $key, '' ) );
	};
	$from_env = function( $env_key ) use ( $env_cache ) {
		return isset( $env_cache[ $env_key ] ) ? trim( (string) $env_cache[ $env_key ] ) : '';
	};
	$get = function( $option_key, $env_key ) use ( $from_option, $from_env ) {
		$v = $from_option( $option_key );
		return $v !== '' ? $v : $from_env( $env_key );
	};

	switch ( $service ) {
		case 'mastodon':
			$instance = $get( 'mastodon_instance', 'JWW_MASTODON_INSTANCE' );
			$token   = $get( 'mastodon_access_token', 'JWW_MASTODON_ACCESS_TOKEN' );
			if ( $instance === '' || $token === '' ) {
				return false;
			}
			return array(
				'instance'     => preg_replace( '#^https?://#i', '', rtrim( $instance, '/' ) ),
				'access_token' => $token,
			);

		case 'bluesky':
			$identifier = $get( 'bluesky_identifier', 'JWW_BLUESKY_IDENTIFIER' );
			$password  = $get( 'bluesky_app_password', 'JWW_BLUESKY_APP_PASSWORD' );
			if ( $identifier === '' || $password === '' ) {
				return false;
			}
			return array(
				'identifier' => $identifier,
				'password'   => $password,
			);

		case 'pinterest':
			$token    = $get( 'pinterest_access_token', 'JWW_PINTEREST_ACCESS_TOKEN' );
			$board_id = $get( 'pinterest_board_id', 'JWW_PINTEREST_BOARD_ID' );
			$board_song = $get( 'pinterest_board_id_song', 'JWW_PINTEREST_BOARD_ID_SONG' );
			$board_show = $get( 'pinterest_board_id_show', 'JWW_PINTEREST_BOARD_ID_SHOW' );
			$board_lyric = $get( 'pinterest_board_id_lyric', 'JWW_PINTEREST_BOARD_ID_LYRIC' );
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
			$user_id = $get( 'threads_user_id', 'JWW_THREADS_USER_ID' );
			$token   = $get( 'threads_access_token', 'JWW_THREADS_ACCESS_TOKEN' );
			if ( $user_id === '' || $token === '' ) {
				return false;
			}
			return array(
				'user_id' => $user_id,
				'access_token' => $token,
			);

		case 'facebook':
			$page_id = $get( 'facebook_page_id', 'JWW_FACEBOOK_PAGE_ID' );
			$token   = $get( 'facebook_page_access_token', 'JWW_FACEBOOK_PAGE_ACCESS_TOKEN' );
			if ( $page_id === '' || $token === '' ) {
				return false;
			}
			return array(
				'page_id'      => $page_id,
				'access_token' => $token,
			);

		case 'instagram':
			$ig_user_id = $get( 'instagram_account_id', 'JWW_INSTAGRAM_ACCOUNT_ID' );
			$token      = $get( 'instagram_access_token', 'JWW_INSTAGRAM_ACCESS_TOKEN' );
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
	$app_id     = trim( (string) get_option( $option_prefix . 'threads_app_id', '' ) );
	$app_secret = trim( (string) get_option( $option_prefix . 'threads_app_secret', '' ) );
	if ( $app_id === '' && isset( $env_cache['JWW_THREADS_APP_ID'] ) ) {
		$app_id = trim( (string) $env_cache['JWW_THREADS_APP_ID'] );
	}
	if ( $app_secret === '' && isset( $env_cache['JWW_THREADS_APP_SECRET'] ) ) {
		$app_secret = trim( (string) $env_cache['JWW_THREADS_APP_SECRET'] );
	}
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
	$app_id     = trim( (string) get_option( $option_prefix . 'facebook_app_id', '' ) );
	$app_secret = trim( (string) get_option( $option_prefix . 'facebook_app_secret', '' ) );
	if ( $app_id === '' && isset( $env_cache['JWW_FACEBOOK_APP_ID'] ) ) {
		$app_id = trim( (string) $env_cache['JWW_FACEBOOK_APP_ID'] );
	}
	if ( $app_secret === '' && isset( $env_cache['JWW_FACEBOOK_APP_SECRET'] ) ) {
		$app_secret = trim( (string) $env_cache['JWW_FACEBOOK_APP_SECRET'] );
	}
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
