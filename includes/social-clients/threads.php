<?php
/**
 * Threads (Meta) client for social auto-post. Uses Threads Graph API: create media container then publish.
 *
 * @package JWW_Theme
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post to Threads. Uses user access token and user ID from config. Always text + link (no image); link preview uses Open Graph.
 *
 * @param array $payload Normalized payload: title, link, description, type, optional status_text.
 * @return true|WP_Error True on success, WP_Error on failure.
 */
function jww_social_threads_post( $payload ) {
	$config = function_exists( 'jww_social_get_config' ) ? jww_social_get_config( 'threads' ) : null;
	if ( ! $config || empty( $config['user_id'] ) || empty( $config['access_token'] ) ) {
		return new WP_Error( 'jww_social_threads_config', __( 'Threads not configured.', 'jww-theme' ) );
	}

	$user_id = $config['user_id'];
	$token   = $config['access_token'];

	$text = isset( $payload['status_text'] ) && is_string( $payload['status_text'] ) && trim( $payload['status_text'] ) !== ''
		? trim( $payload['status_text'] )
		: jww_social_threads_build_text( $payload );

	// Decode HTML entities (e.g. &#8211; → –) so Threads shows proper characters.
	$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

	$text = jww_social_threads_truncate( $text, 500 );

	$link = isset( $payload['link'] ) ? trim( (string) $payload['link'] ) : '';

	// Threads: text + link only (no image). Link preview shows Open Graph image when shared.
	// auto_publish_text=true publishes on create for text posts, avoiding a separate publish step (and "resource does not exist" errors).
	$body = array(
		'access_token'      => $token,
		'media_type'        => 'TEXT',
		'text'              => $text,
		'auto_publish_text' => 'true',
	);

	if ( $link !== '' ) {
		$body['link_attachment'] = $link;
	}

	$url = 'https://graph.threads.net/v1.0/' . $user_id . '/threads';

	if ( function_exists( 'jww_social_debug_log' ) ) {
		jww_social_debug_log( 'threads', 'creating container: media_type=TEXT, text_len=' . strlen( $text ) . ', auto_publish_text=true' );
	}

	$response = wp_remote_post(
		$url,
		array(
			'body'    => http_build_query( $body ),
			'timeout' => 20,
		)
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body_raw = wp_remote_retrieve_body( $response );
	$data = json_decode( $body_raw, true );

	if ( $code < 200 || $code > 299 ) {
		$message = isset( $data['error']['message'] ) ? $data['error']['message'] : sprintf( __( 'HTTP %d', 'jww-theme' ), $code );
		if ( function_exists( 'jww_social_debug_log' ) ) {
			jww_social_debug_log( 'threads', 'create container error: ' . $message . ' (HTTP ' . $code . ')' );
			if ( ! empty( $data['error'] ) && is_array( $data['error'] ) ) {
				jww_social_debug_log( 'threads', 'error detail: ' . wp_json_encode( $data['error'] ) );
			}
		}
		return new WP_Error( 'jww_social_threads_create', $message, array( 'status' => $code, 'body' => $body_raw ) );
	}

	// With auto_publish_text=true, the post is published on create; no separate threads_publish call needed.
	if ( function_exists( 'jww_social_debug_log' ) ) {
		jww_social_debug_log( 'threads', 'post published OK (auto_publish_text)' );
	}

	return true;
}

/**
 * Build default Threads text from payload (title + link + hashtag).
 *
 * @param array $payload Normalized payload with title, link.
 * @return string Post text.
 */
function jww_social_threads_build_text( $payload ) {
	$title = isset( $payload['title'] ) ? trim( (string) $payload['title'] ) : '';
	$link  = isset( $payload['link'] ) ? trim( (string) $payload['link'] ) : '';
	$parts = array_filter( array( $title, $link ), function ( $s ) {
		return $s !== '';
	} );
	$text = implode( ' ', $parts );
	if ( $text !== '' && strpos( $text, '#jessewelles' ) === false ) {
		$text .= ' #jessewelles';
	}
	return $text;
}

/**
 * Truncate text for Threads (500 character limit; emojis count as UTF-8 bytes).
 *
 * @param string $text     Input text.
 * @param int    $max_len  Max length. Default 500.
 * @return string Truncated string.
 */
function jww_social_threads_truncate( $text, $max_len = 500 ) {
	if ( strlen( $text ) <= $max_len ) {
		return $text;
	}
	$truncated = substr( $text, 0, $max_len - 3 );
	// Avoid cutting in the middle of a multi-byte character.
	if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
		while ( mb_strlen( $truncated ) > $max_len - 3 && strlen( $truncated ) > 0 ) {
			$truncated = substr( $truncated, 0, -1 );
		}
	}
	return $truncated . '...';
}
