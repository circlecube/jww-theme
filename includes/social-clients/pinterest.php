<?php
/**
 * Pinterest client for social auto-post. Creates a pin via Pinterest API v5.
 *
 * @package JWW_Theme
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post to Pinterest (create pin). Uses OAuth access token and board ID from config.
 * Board is chosen by payload type (song, show, lyric) when type-specific board IDs are set; otherwise the default board is used.
 *
 * @param array $payload Normalized payload: title, link, description, image_url (required for pin), type (song|show|lyric).
 * @return true|WP_Error True on success, WP_Error on failure.
 */
function jww_social_pinterest_post( $payload ) {
	$config = function_exists( 'jww_social_get_config' ) ? jww_social_get_config( 'pinterest' ) : null;
	if ( ! $config || empty( $config['access_token'] ) || empty( $config['board_id'] ) ) {
		return new WP_Error( 'jww_social_pinterest_config', __( 'Pinterest not configured.', 'jww-theme' ) );
	}

	$type = isset( $payload['type'] ) ? trim( (string) $payload['type'] ) : '';
	$board_id = $config['board_id'];
	if ( $type !== '' && ! empty( $config[ 'board_id_' . $type ] ) ) {
		$board_id = $config[ 'board_id_' . $type ];
	}

	if ( function_exists( 'jww_social_debug_log' ) ) {
		jww_social_debug_log( 'pinterest', 'posting pin: board_id=' . $board_id . ', type=' . ( $type ?: '(default)' ) );
	}

	$link        = isset( $payload['link'] ) ? trim( (string) $payload['link'] ) : '';
	$title       = isset( $payload['title'] ) ? trim( (string) $payload['title'] ) : '';
	$description = isset( $payload['description'] ) ? trim( (string) $payload['description'] ) : '';
	$image_url   = isset( $payload['image_url'] ) ? trim( (string) $payload['image_url'] ) : '';

	if ( $link === '' || $title === '' ) {
		return new WP_Error( 'jww_social_pinterest_payload', __( 'Pinterest requires link and title.', 'jww-theme' ) );
	}

	// Pinterest pins require media (image). If no image_url, skip Pinterest or use a placeholder; plan says image_url in payload.
	if ( $image_url === '' ) {
		if ( function_exists( 'jww_social_debug_log' ) ) {
			jww_social_debug_log( 'pinterest', 'skipping: no image_url in payload' );
		}
		return new WP_Error( 'jww_social_pinterest_image', __( 'Pinterest requires an image URL for the pin.', 'jww-theme' ) );
	}

	$body = array(
		'board_id'     => $board_id,
		'link'         => $link,
		'title'        => $title,
		'description'  => $description,
		'media'        => array(
			'source_type' => 'image_url',
			'url'         => $image_url,
		),
	);

	$response = wp_remote_post(
		'https://api.pinterest.com/v5/pins',
		array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $config['access_token'],
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $body ),
			'timeout' => 20,
		)
	);

	if ( is_wp_error( $response ) ) {
		if ( function_exists( 'jww_social_debug_log' ) ) {
			jww_social_debug_log( 'pinterest', 'request error: ' . $response->get_error_message() );
		}
		return $response;
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body_raw = wp_remote_retrieve_body( $response );
	if ( $code < 200 || $code > 299 ) {
		$message = sprintf( __( 'Pinterest API error: %d', 'jww-theme' ), $code );
		$data = json_decode( $body_raw, true );
		if ( is_array( $data ) ) {
			if ( ! empty( $data['message'] ) ) {
				$message .= ' — ' . $data['message'];
			}
			if ( ! empty( $data['code'] ) && ( empty( $data['message'] ) || strpos( $data['message'], (string) $data['code'] ) === false ) ) {
				$message .= ' (code: ' . $data['code'] . ')';
			}
		} else {
			$message .= ' — ' . trim( substr( $body_raw, 0, 200 ) );
		}
		if ( function_exists( 'jww_social_debug_log' ) ) {
			jww_social_debug_log( 'pinterest', 'API ' . $code . ': ' . $message );
			// If 401, check whether token works for read — helps distinguish "bad token" from "app not approved for write"
			if ( $code === 401 ) {
				$check = wp_remote_get(
					'https://api.pinterest.com/v5/user_account',
					array(
						'headers' => array( 'Authorization' => 'Bearer ' . $config['access_token'] ),
						'timeout' => 10,
					)
				);
				$check_code = is_wp_error( $check ) ? -1 : wp_remote_retrieve_response_code( $check );
				if ( $check_code === 401 ) {
					jww_social_debug_log( 'pinterest', 'Token check: GET /user_account also 401 → token is invalid or expired. Generate a new OAuth access token in developers.pinterest.com with scopes pins:write and boards:read.' );
				} elseif ( $check_code >= 200 && $check_code <= 299 ) {
					jww_social_debug_log( 'pinterest', 'Token check: GET /user_account OK → token is valid for read but create pin rejected. App may be in trial mode or need approval for pins:write in developers.pinterest.com.' );
				}
			}
		}
		return new WP_Error(
			'jww_social_pinterest_api',
			$message,
			array( 'status' => $code, 'body' => $body_raw )
		);
	}

	if ( function_exists( 'jww_social_debug_log' ) ) {
		jww_social_debug_log( 'pinterest', 'pin created OK' );
	}
	return true;
}
