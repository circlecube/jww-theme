<?php
/**
 * Instagram (Graph API) client for social auto-post. Creates media container then publishes.
 * Requires an image; skips when payload has no image_url.
 *
 * @package JWW_Theme
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post to Instagram (Business/Creator account). Requires image_url; caption + link in caption.
 *
 * @param array $payload Normalized payload: title, link, description, image_url (required), type, optional status_text.
 * @return true|WP_Error True on success, WP_Error on failure.
 */
function jww_social_instagram_post( $payload ) {
	$config = function_exists( 'jww_social_get_config' ) ? jww_social_get_config( 'instagram' ) : null;
	if ( ! $config || empty( $config['ig_user_id'] ) || empty( $config['access_token'] ) ) {
		return new WP_Error( 'jww_social_instagram_config', __( 'Instagram not configured.', 'jww-theme' ) );
	}

	$image_url = isset( $payload['image_url'] ) ? trim( (string) $payload['image_url'] ) : '';
	if ( $image_url === '' ) {
		if ( function_exists( 'jww_social_debug_log' ) ) {
			jww_social_debug_log( 'instagram', 'skipped: no image_url (Instagram requires an image)' );
		}
		return new WP_Error( 'jww_social_instagram_skip', __( 'Instagram requires an image; payload has none.', 'jww-theme' ) );
	}

	$ig_user_id = $config['ig_user_id'];
	$token      = $config['access_token'];

	$caption = isset( $payload['status_text'] ) && is_string( $payload['status_text'] ) && trim( $payload['status_text'] ) !== ''
		? trim( $payload['status_text'] )
		: jww_social_instagram_build_caption( $payload );

	$caption = html_entity_decode( $caption, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$caption = jww_social_instagram_truncate_caption( $caption, 2200 );

	// When we have the attachment ID, use the original file URL for Instagram (Meta fetches it directly; format must be JPEG or video).
	// Use multisite-safe URL so subsite uploads include /sites/{blog_id}/ in the path.
	$attachment_id = isset( $payload['image_attachment_id'] ) ? (int) $payload['image_attachment_id'] : 0;
	if ( $attachment_id > 0 ) {
		$mime = get_post_mime_type( $attachment_id );
		$direct_url = function_exists( 'jww_social_attachment_url_for_share' ) ? jww_social_attachment_url_for_share( $attachment_id ) : wp_get_attachment_url( $attachment_id );
		if ( $direct_url !== '' ) {
			$image_url = $direct_url;
			// Apply same dev→production and http→https normalization so Meta can fetch the image (e.g. jww.test → jessewellesworld.com).
			if ( function_exists( 'jww_social_normalize_payload_urls' ) ) {
				$normalized = jww_social_normalize_payload_urls( array( 'image_url' => $image_url ) );
				if ( ! empty( $normalized['image_url'] ) ) {
					$image_url = $normalized['image_url'];
				}
			}
		}
		if ( function_exists( 'jww_social_debug_log' ) ) {
			$url_preview = strlen( $image_url ) > 80 ? substr( $image_url, 0, 80 ) . '...' : $image_url;
			jww_social_debug_log( 'instagram', 'image_url=' . $url_preview . ', mime=' . ( $mime ? $mime : 'unknown' ) );
			if ( $mime === 'image/webp' ) {
				jww_social_debug_log( 'instagram', 'Attachment is WebP; Instagram requires JPEG. Use filter jww_social_instagram_image_url to supply a JPEG URL, or use JPEG featured images.' );
			}
		}
	}

	// Allow swapping to a URL that returns a valid image (e.g. JPEG). Instagram requires a direct image URL; format should be JPEG.
	$image_url = apply_filters( 'jww_social_instagram_image_url', $image_url, $payload );

	// Step 1: Create media container (feed photo: image_url + caption; no media_type for simple image).
	$create_url = 'https://graph.facebook.com/v25.0/' . $ig_user_id . '/media';

	// Send as JSON to avoid form-encoding issues when image_url or caption contains & or other special characters.
	$create_body = array(
		'access_token' => $token,
		'image_url'    => $image_url,
		'caption'      => $caption,
	);

	if ( function_exists( 'jww_social_debug_log' ) ) {
		jww_social_debug_log( 'instagram', 'creating container, caption_len=' . strlen( $caption ) );
	}

	$response = wp_remote_post(
		$create_url,
		array(
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( $create_body ),
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
			jww_social_debug_log( 'instagram', 'create container error: ' . $message );
			jww_social_debug_log( 'instagram', 'image_url sent (for curl test): ' . $image_url );
		}
		return new WP_Error( 'jww_social_instagram_create', $message, array( 'status' => $code, 'body' => $body_raw ) );
	}

	if ( empty( $data['id'] ) ) {
		return new WP_Error( 'jww_social_instagram_create', __( 'Instagram API did not return a container ID.', 'jww-theme' ), array( 'body' => $body_raw ) );
	}

	$creation_id = $data['id'];

	// Poll container status until FINISHED (Meta fetches image_url asynchronously; publish fails with "Media ID is not available" until ready).
	$status_url = 'https://graph.facebook.com/v25.0/' . $creation_id . '?fields=status_code&access_token=' . rawurlencode( $token );
	$max_wait = 30;
	$interval = 3;
	$elapsed = 0;
	while ( $elapsed < $max_wait ) {
		$status_response = wp_remote_get( $status_url, array( 'timeout' => 10 ) );
		if ( is_wp_error( $status_response ) ) {
			break;
		}
		$status_body = wp_remote_retrieve_body( $status_response );
		$status_data = json_decode( $status_body, true );
		$status_code = isset( $status_data['status_code'] ) ? (string) $status_data['status_code'] : '';
		if ( $status_code === 'FINISHED' ) {
			break;
		}
		if ( $status_code === 'ERROR' || $status_code === 'EXPIRED' ) {
			return new WP_Error( 'jww_social_instagram_create', sprintf( __( 'Instagram container failed (status: %s).', 'jww-theme' ), $status_code ), array( 'body' => $status_body ) );
		}
		sleep( $interval );
		$elapsed += $interval;
	}

	// Step 2: Publish the container.
	$publish_url = 'https://graph.facebook.com/v25.0/' . $ig_user_id . '/media_publish';

	$publish_body = array(
		'access_token' => $token,
		'creation_id'  => $creation_id,
	);

	$publish_response = wp_remote_post(
		$publish_url,
		array(
			'body'    => $publish_body,
			'timeout' => 15,
		)
	);

	if ( is_wp_error( $publish_response ) ) {
		return $publish_response;
	}

	$publish_code = wp_remote_retrieve_response_code( $publish_response );
	$publish_body_raw = wp_remote_retrieve_body( $publish_response );
	$publish_data = json_decode( $publish_body_raw, true );

	if ( $publish_code < 200 || $publish_code > 299 ) {
		$message = isset( $publish_data['error']['message'] ) ? $publish_data['error']['message'] : sprintf( __( 'Publish HTTP %d', 'jww-theme' ), $publish_code );
		if ( function_exists( 'jww_social_debug_log' ) ) {
			jww_social_debug_log( 'instagram', 'publish error: ' . $message );
		}
		return new WP_Error( 'jww_social_instagram_publish', $message, array( 'status' => $publish_code, 'body' => $publish_body_raw ) );
	}

	if ( function_exists( 'jww_social_debug_log' ) ) {
		jww_social_debug_log( 'instagram', 'post published OK' );
	}

	return true;
}

/**
 * Build caption from payload (title + link).
 *
 * @param array $payload Normalized payload with title, link.
 * @return string Caption text.
 */
function jww_social_instagram_build_caption( $payload ) {
	$title = isset( $payload['title'] ) ? trim( (string) $payload['title'] ) : '';
	$link  = isset( $payload['link'] ) ? trim( (string) $payload['link'] ) : '';
	$parts = array_filter( array( $title, $link ), function ( $s ) {
		return $s !== '';
	} );
	$caption = implode( ' ', $parts );
	if ( $caption !== '' && strpos( $caption, '#jessewelles' ) === false ) {
		$caption .= ' #jessewelles';
	}
	return $caption;
}

/**
 * Truncate caption for Instagram (max 2,200 characters).
 *
 * @param string $caption  Caption text.
 * @param int    $max_len  Max length. Default 2200.
 * @return string Truncated caption.
 */
function jww_social_instagram_truncate_caption( $caption, $max_len = 2200 ) {
	if ( strlen( $caption ) <= $max_len ) {
		return $caption;
	}
	$truncated = substr( $caption, 0, $max_len - 3 );
	if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
		while ( mb_strlen( $truncated ) > $max_len - 3 && strlen( $truncated ) > 0 ) {
			$truncated = substr( $truncated, 0, -1 );
		}
	}
	return $truncated . '...';
}
