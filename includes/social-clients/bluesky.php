<?php
/**
 * Bluesky (AT Protocol) client for social auto-post. Creates a post via createSession + createRecord; optional image via uploadBlob.
 *
 * @package JWW_Theme
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post to Bluesky. Uses app password from config; creates session then creates record. Optional image when payload has image_url.
 *
 * @param array $payload Normalized payload: title, link, description, image_url (optional), type, optional status_text for post body.
 * @return true|WP_Error True on success, WP_Error on failure.
 */
function jww_social_bluesky_post( $payload ) {
	$config = function_exists( 'jww_social_get_config' ) ? jww_social_get_config( 'bluesky' ) : null;
	if ( ! $config || empty( $config['identifier'] ) || empty( $config['password'] ) ) {
		return new WP_Error( 'jww_social_bluesky_config', __( 'Bluesky not configured.', 'jww-theme' ) );
	}

	$session = jww_social_bluesky_create_session( $config );
	if ( is_wp_error( $session ) ) {
		return $session;
	}

	$access_jwt = $session['accessJwt'];
	$did        = $session['did'];

	$text = isset( $payload['status_text'] ) && is_string( $payload['status_text'] ) && trim( $payload['status_text'] ) !== ''
		? trim( $payload['status_text'] )
		: jww_social_bluesky_build_text( $payload );

	$text = jww_social_bluesky_truncate( $text, 300 );

	$uri   = isset( $payload['link'] ) ? trim( (string) $payload['link'] ) : '';
	$title = isset( $payload['title'] ) ? trim( (string) $payload['title'] ) : '';
	$desc  = isset( $payload['description'] ) ? trim( (string) $payload['description'] ) : '';

	$thumb_ref = null;
	$image_url = isset( $payload['image_url'] ) ? trim( (string) $payload['image_url'] ) : '';
	if ( function_exists( 'jww_social_debug_log' ) ) {
		$payload_type = isset( $payload['type'] ) ? $payload['type'] : '(none)';
		jww_social_debug_log( 'bluesky', 'payload type=' . $payload_type . ', image_url=' . ( $image_url !== '' ? substr( $image_url, 0, 60 ) . '...' : '(empty)' ) );
	}
	if ( $image_url !== '' ) {
		$thumb_ref = jww_social_bluesky_upload_blob( $access_jwt, $image_url );
		if ( is_wp_error( $thumb_ref ) ) {
			if ( function_exists( 'jww_social_debug_log' ) ) {
				jww_social_debug_log( 'bluesky', 'thumb upload failed: ' . $thumb_ref->get_error_message() );
			}
			$thumb_ref = null;
		} elseif ( function_exists( 'jww_social_debug_log' ) ) {
			$ref_cid = isset( $thumb_ref['ref']['$link'] ) ? $thumb_ref['ref']['$link'] : ( is_string( $thumb_ref['ref'] ?? null ) ? $thumb_ref['ref'] : '(no ref)' );
			jww_social_debug_log( 'bluesky', 'thumb upload OK, ref=' . substr( $ref_cid, 0, 20 ) . '..., adding to embed' );
		}
	}

	$record = array(
		'repo'       => $did,
		'collection' => 'app.bsky.feed.post',
		'record'     => array(
			'text'      => $text,
			'createdAt' => gmdate( 'Y-m-d\TH:i:s.v\Z' ),
		),
	);

	if ( preg_match( '/#jessewelles/i', $text, $m ) ) {
		$pos = strpos( $text, $m[0] );
		if ( $pos !== false ) {
			$record['record']['facets'] = array(
				array(
					'index' => array(
						'byteStart' => $pos,
						'byteEnd'   => $pos + strlen( $m[0] ),
					),
					'features' => array(
						array(
							'$type' => 'app.bsky.richtext.facet#tag',
							'tag'   => 'jessewelles',
						),
					),
				),
			);
		}
	}

	$embed = array(
		'$type'    => 'app.bsky.embed.external',
		'external' => array(
			'uri'         => $uri ?: ( isset( $payload['link'] ) ? $payload['link'] : '' ),
			'title'       => $title ?: 'Jesse Welles World',
			'description' => $desc ?: 'Jesse Welles World - lyrics, setlists and more.',
		),
	);
	if ( $thumb_ref !== null && is_array( $thumb_ref ) ) {
		$embed['external']['thumb'] = $thumb_ref;
	}
	$record['record']['embed'] = $embed;

	$response = wp_remote_post(
		'https://bsky.social/xrpc/com.atproto.repo.createRecord',
		array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_jwt,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $record ),
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
			'jww_social_bluesky_create',
			sprintf( __( 'Bluesky createRecord error: %d', 'jww-theme' ), $code ),
			array( 'status' => $code, 'body' => $body_raw )
		);
	}

	return true;
}

/**
 * Create Bluesky session; return accessJwt and did.
 *
 * @param array $config Config with identifier, password.
 * @return array|WP_Error Array with accessJwt, did or WP_Error.
 */
function jww_social_bluesky_create_session( $config ) {
	$body = array(
		'identifier' => $config['identifier'],
		'password'   => $config['password'],
	);

	$response = wp_remote_post(
		'https://bsky.social/xrpc/com.atproto.server.createSession',
		array(
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( $body ),
			'timeout' => 15,
		)
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body_raw = wp_remote_retrieve_body( $response );
	$data = json_decode( $body_raw, true );

	if ( $code < 200 || $code > 299 ) {
		$message = sprintf( __( 'Bluesky session error: %d', 'jww-theme' ), $code );
		if ( $code === 401 && is_array( $data ) && ! empty( $data['message'] ) ) {
			$message .= ' — ' . $data['message'];
		} elseif ( $code === 401 ) {
			$message .= ' — ' . __( 'Invalid identifier or app password. Use your full handle (e.g. user.bsky.social) and an App Password from Settings → App passwords, not your account password.', 'jww-theme' );
		} elseif ( is_array( $data ) && ! empty( $data['message'] ) ) {
			$message .= ' — ' . $data['message'];
		}
		return new WP_Error(
			'jww_social_bluesky_session',
			$message,
			array( 'status' => $code, 'body' => $body_raw )
		);
	}

	if ( empty( $data['accessJwt'] ) || empty( $data['did'] ) ) {
		return new WP_Error( 'jww_social_bluesky_session', __( 'Bluesky session missing accessJwt or did.', 'jww-theme' ) );
	}

	return array( 'accessJwt' => $data['accessJwt'], 'did' => $data['did'] );
}

/**
 * Upload image from URL to Bluesky blob; return thumb ref for embed.external.thumb.
 * Uses the exact blob object returned by uploadBlob so the thumb displays on the link card.
 * When the image URL points to this site's uploads, reads the file from disk to avoid 404s from server-to-self requests.
 *
 * @param string $access_jwt Session JWT.
 * @param string $image_url  Public image URL.
 * @return array|WP_Error Blob object (as returned by API) or WP_Error.
 */
function jww_social_bluesky_upload_blob( $access_jwt, $image_url ) {
	$body_binary = null;
	$content_type = '';

	// If the image URL path is under this site's uploads dir, read from disk (works locally or prod; no host check so normalized URLs still resolve).
	$image_path = parse_url( $image_url, PHP_URL_PATH );
	if ( $image_path !== null && $image_path !== '' && function_exists( 'wp_upload_dir' ) ) {
		$upload_dir = wp_upload_dir();
		if ( empty( $upload_dir['error'] ) && ! empty( $upload_dir['baseurl'] ) && ! empty( $upload_dir['basedir'] ) ) {
			$basedir      = untrailingslashit( $upload_dir['basedir'] );
			$baseurl_path = parse_url( $upload_dir['baseurl'], PHP_URL_PATH );
			if ( $baseurl_path !== null ) {
				$baseurl_path = untrailingslashit( $baseurl_path );
				if ( $image_path === $baseurl_path || strpos( $image_path, $baseurl_path . '/' ) === 0 ) {
					$relative   = substr( $image_path, strlen( $baseurl_path ) );
					$local_path = $basedir . str_replace( '/', DIRECTORY_SEPARATOR, $relative );
					$real_local = realpath( $local_path );
					$real_base  = realpath( $basedir );
					if ( $real_local !== false && $real_base !== false && strpos( $real_local, $real_base ) === 0 && is_file( $real_local ) && is_readable( $real_local ) ) {
						$body_binary = file_get_contents( $real_local );
						if ( $body_binary !== false ) {
							$content_type = wp_check_filetype( $local_path, null )['type'];
							if ( empty( $content_type ) || strpos( $content_type, 'image/' ) !== 0 ) {
								$content_type = 'image/jpeg';
							}
							if ( function_exists( 'jww_social_debug_log' ) ) {
								jww_social_debug_log( 'bluesky', 'read image from disk: ' . $real_local . ' (' . strlen( $body_binary ) . ' bytes, ' . $content_type . ')' );
							}
						}
					}
				}
			}
		}
	}

	// Fall back to HTTP fetch when not a local upload or local read failed.
	if ( $body_binary === null || $body_binary === false ) {
		if ( function_exists( 'jww_social_debug_log' ) ) {
			jww_social_debug_log( 'bluesky', 'fetching image: ' . substr( $image_url, 0, 70 ) . ( strlen( $image_url ) > 70 ? '...' : '' ) );
		}
		$img_response = wp_remote_get(
			$image_url,
			array(
				'timeout'   => 15,
				'user-agent' => 'Mozilla/5.0 (compatible; JWW-Theme/1.0; +https://jessewellesworld.com)',
			)
		);
		if ( is_wp_error( $img_response ) ) {
			if ( function_exists( 'jww_social_debug_log' ) ) {
				jww_social_debug_log( 'bluesky', 'fetch image error: ' . $img_response->get_error_message() );
			}
			return $img_response;
		}
		$code = wp_remote_retrieve_response_code( $img_response );
		if ( $code < 200 || $code > 299 ) {
			if ( function_exists( 'jww_social_debug_log' ) ) {
				jww_social_debug_log( 'bluesky', 'fetch image HTTP ' . $code );
			}
			return new WP_Error( 'jww_social_bluesky_fetch_image', sprintf( __( 'Failed to fetch image: %d', 'jww-theme' ), $code ) );
		}

		$content_type = wp_remote_retrieve_header( $img_response, 'content-type' );
		$content_type = $content_type ? trim( strtok( $content_type, ';' ) ) : '';
		if ( function_exists( 'jww_social_debug_log' ) ) {
			jww_social_debug_log( 'bluesky', 'fetch OK, Content-Type=' . ( $content_type ?: '(empty)' ) . ', size=' . strlen( wp_remote_retrieve_body( $img_response ) ) );
		}
		if ( strpos( $content_type, 'image/' ) !== 0 ) {
			if ( function_exists( 'jww_social_debug_log' ) ) {
				jww_social_debug_log( 'bluesky', 'rejecting: not an image Content-Type' );
			}
			return new WP_Error( 'jww_social_bluesky_fetch_image', __( 'URL did not return an image (wrong Content-Type).', 'jww-theme' ) );
		}

		$body_binary = wp_remote_retrieve_body( $img_response );
		if ( empty( $body_binary ) ) {
			if ( function_exists( 'jww_social_debug_log' ) ) {
				jww_social_debug_log( 'bluesky', 'rejecting: empty body' );
			}
			return new WP_Error( 'jww_social_bluesky_fetch_image', __( 'Image response was empty.', 'jww-theme' ) );
		}
	}

	if ( empty( $body_binary ) ) {
		if ( function_exists( 'jww_social_debug_log' ) ) {
			jww_social_debug_log( 'bluesky', 'could not read image (local or fetch)' );
		}
		return new WP_Error( 'jww_social_bluesky_fetch_image', __( 'Could not read image from URL or local path.', 'jww-theme' ) );
	}

	if ( empty( $content_type ) || strpos( $content_type, 'image/' ) !== 0 ) {
		$content_type = 'image/jpeg';
	}

	// Bluesky embed.external thumb expects the exact blob object from uploadBlob: $type, ref.$link, mimeType, size.
	if ( function_exists( 'jww_social_debug_log' ) ) {
		jww_social_debug_log( 'bluesky', 'uploading blob to Bluesky (' . strlen( $body_binary ) . ' bytes, ' . $content_type . ')' );
	}
	$response = wp_remote_post(
		'https://bsky.social/xrpc/com.atproto.repo.uploadBlob',
		array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_jwt,
				'Content-Type'  => $content_type,
			),
			'body'    => $body_binary,
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
		if ( function_exists( 'jww_social_debug_log' ) ) {
			jww_social_debug_log( 'bluesky', 'uploadBlob HTTP ' . $code . ', body=' . substr( $body_raw, 0, 120 ) );
		}
		return new WP_Error(
			'jww_social_bluesky_upload',
			sprintf( __( 'Bluesky uploadBlob error: %d', 'jww-theme' ), $code ),
			array( 'body' => $body_raw )
		);
	}

	if ( empty( $data['blob'] ) || empty( $data['blob']['ref'] ) ) {
		if ( function_exists( 'jww_social_debug_log' ) ) {
			jww_social_debug_log( 'bluesky', 'uploadBlob 200 but invalid blob response: ' . substr( $body_raw, 0, 150 ) );
		}
		return new WP_Error(
			'jww_social_bluesky_upload',
			__( 'Bluesky uploadBlob did not return a valid blob.', 'jww-theme' ),
			array( 'body' => $body_raw )
		);
	}

	// Return the blob object exactly as the API returned it (required for embed.external.thumb).
	$blob = $data['blob'];
	if ( empty( $blob['$type'] ) ) {
		$blob['$type'] = 'blob';
	}
	return $blob;
}

/**
 * Build default Bluesky post text from payload.
 *
 * @param array $payload Normalized payload with title, link.
 * @return string Post text.
 */
function jww_social_bluesky_build_text( $payload ) {
	$title = isset( $payload['title'] ) ? trim( (string) $payload['title'] ) : '';
	$link  = isset( $payload['link'] ) ? trim( (string) $payload['link'] ) : '';
	if ( $title !== '' && $link !== '' ) {
		$text = $title . ' ' . $link;
	} elseif ( $link !== '' ) {
		$text = $link;
	} else {
		$text = $title;
	}
	if ( $text !== '' && strpos( $text, '#jessewelles' ) === false ) {
		$text .= ' #jessewelles';
	}
	return $text;
}

/**
 * Truncate string to max byte length (Bluesky 300 chars).
 *
 * @param string $text       Text.
 * @param int    $max_bytes  Max bytes.
 * @return string Truncated text.
 */
function jww_social_bluesky_truncate( $text, $max_bytes = 300 ) {
	if ( strlen( $text ) <= $max_bytes ) {
		return $text;
	}
	return substr( $text, 0, $max_bytes - 3 ) . '...';
}
