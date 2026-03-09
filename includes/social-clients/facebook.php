<?php
/**
 * Facebook Page client for social auto-post. Uses Facebook Graph API Page feed.
 *
 * @package JWW_Theme
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post to a Facebook Page feed. Message + link; no image (link preview uses Open Graph).
 * The access_token must be the Page access token from GET /me/accounts (not the user token).
 *
 * @param array $payload Normalized payload: title, link, description, type, optional status_text.
 * @return true|WP_Error True on success, WP_Error on failure.
 */
function jww_social_facebook_post( $payload ) {
	$config = function_exists( 'jww_social_get_config' ) ? jww_social_get_config( 'facebook' ) : null;
	if ( ! $config || empty( $config['page_id'] ) || empty( $config['access_token'] ) ) {
		return new WP_Error( 'jww_social_facebook_config', __( 'Facebook not configured.', 'jww-theme' ) );
	}

	$page_id = $config['page_id'];
	$token   = $config['access_token'];

	$text = isset( $payload['status_text'] ) && is_string( $payload['status_text'] ) && trim( $payload['status_text'] ) !== ''
		? trim( $payload['status_text'] )
		: jww_social_facebook_build_message( $payload );

	$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

	$link = isset( $payload['link'] ) ? trim( (string) $payload['link'] ) : '';

	$url = 'https://graph.facebook.com/v18.0/' . $page_id . '/feed';

	$body = array(
		'access_token' => $token,
		'message'      => $text,
	);

	if ( $link !== '' ) {
		$body['link'] = $link;
	}

	if ( function_exists( 'jww_social_debug_log' ) ) {
		jww_social_debug_log( 'facebook', 'posting to page feed, message_len=' . strlen( $text ) );
	}

	$response = wp_remote_post(
		$url,
		array(
			'body'    => $body,
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
			jww_social_debug_log( 'facebook', 'error: ' . $message . ' (HTTP ' . $code . ')' );
		}
		return new WP_Error( 'jww_social_facebook_post', $message, array( 'status' => $code, 'body' => $body_raw ) );
	}

	if ( function_exists( 'jww_social_debug_log' ) ) {
		jww_social_debug_log( 'facebook', 'post published OK' );
	}

	return true;
}

/**
 * Build default Facebook post message from payload (title + link).
 *
 * @param array $payload Normalized payload with title, link.
 * @return string Message text.
 */
function jww_social_facebook_build_message( $payload ) {
	$title = isset( $payload['title'] ) ? trim( (string) $payload['title'] ) : '';
	$link  = isset( $payload['link'] ) ? trim( (string) $payload['link'] ) : '';
	$parts = array_filter( array( $title, $link ), function ( $s ) {
		return $s !== '';
	} );
	return implode( ' ', $parts );
}
