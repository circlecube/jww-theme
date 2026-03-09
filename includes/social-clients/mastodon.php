<?php
/**
 * Mastodon client for social auto-post. Posts a status via Mastodon API.
 *
 * @package JWW_Theme
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post to Mastodon. Uses Bearer token from config.
 *
 * @param array $payload Normalized payload with title, link, optional status_text, description, image_url, type.
 * @return true|WP_Error True on success, WP_Error on failure.
 */
function jww_social_mastodon_post( $payload ) {
	$config = function_exists( 'jww_social_get_config' ) ? jww_social_get_config( 'mastodon' ) : null;
	if ( ! $config || empty( $config['instance'] ) || empty( $config['access_token'] ) ) {
		return new WP_Error( 'jww_social_mastodon_config', __( 'Mastodon not configured.', 'jww-theme' ) );
	}

	$status = isset( $payload['status_text'] ) && is_string( $payload['status_text'] ) && trim( $payload['status_text'] ) !== ''
		? trim( $payload['status_text'] )
		: jww_social_mastodon_build_status( $payload );

	if ( $status === '' ) {
		return new WP_Error( 'jww_social_mastodon_empty', __( 'Mastodon status text is empty.', 'jww-theme' ) );
	}

	$url = 'https://' . $config['instance'] . '/api/v1/statuses';
	$body = array(
		'status'      => $status,
		'visibility'  => 'public',
	);

	$response = wp_remote_post(
		$url,
		array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $config['access_token'],
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $body ),
			'timeout' => 15,
		)
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = wp_remote_retrieve_response_code( $response );
	if ( $code < 200 || $code > 299 ) {
		$body_raw = wp_remote_retrieve_body( $response );
		return new WP_Error(
			'jww_social_mastodon_api',
			sprintf( __( 'Mastodon API error: %d', 'jww-theme' ), $code ),
			array( 'status' => $code, 'body' => $body_raw )
		);
	}

	return true;
}

/**
 * Build default Mastodon status from payload (title + link + hashtag).
 *
 * @param array $payload Normalized payload with title, link.
 * @return string Status text.
 */
function jww_social_mastodon_build_status( $payload ) {
	$title = isset( $payload['title'] ) ? trim( (string) $payload['title'] ) : '';
	$link  = isset( $payload['link'] ) ? trim( (string) $payload['link'] ) : '';
	$parts = array_filter( array( $title, $link ), function ( $s ) { return $s !== ''; } );
	$status = implode( ' ', $parts );
	if ( $status !== '' && strpos( $status, '#jessewelles' ) === false ) {
		$status .= ' #jessewelles';
	}
	return $status;
}
