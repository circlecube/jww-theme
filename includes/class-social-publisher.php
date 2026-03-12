<?php
/**
 * Social publisher: dispatcher, cron registration, and hook handlers for auto-posting to Mastodon, Bluesky, Pinterest.
 *
 * @package JWW_Theme
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dispatch a normalized payload to the given channels. Logs errors; one failure does not stop others.
 * Clients may call jww_social_debug_log() to add lines for the event log (e.g. troubleshooting thumb/image).
 *
 * @param array $payload  Normalized payload: title, link, description, image_url (optional), type, optional status_text.
 * @param array $channels Optional. List of channel slugs: mastodon, bluesky, pinterest, threads, facebook, instagram. Default all six.
 * @return array Array of channel => true|WP_Error for each channel attempted.
 */
function jww_social_dispatch( $payload, $channels = array() ) {
	if ( empty( $channels ) ) {
		$channels = array( 'mastodon', 'bluesky', 'pinterest', 'threads', 'facebook', 'instagram' );
	}

	// Only dispatch to channels that are enabled (Settings → Social Sharing toggles).
	$channels = array_values( array_filter( $channels, 'jww_social_is_channel_enabled' ) );

	// Clear debug log at start of each dispatch so admin event log gets this run only.
	jww_social_debug_log_clear();

	// Replace dev URLs with production so posts from local (e.g. jww.test) don't contain broken links.
	$payload = jww_social_normalize_payload_urls( $payload );

	$results = array();
	foreach ( $channels as $channel ) {
		$func = 'jww_social_' . $channel . '_post';
		if ( ! function_exists( $func ) ) {
			$results[ $channel ] = new WP_Error( 'jww_social_unknown', sprintf( __( 'Unknown channel: %s', 'jww-theme' ), $channel ) );
			continue;
		}
		try {
			$result = call_user_func( $func, $payload );
			$results[ $channel ] = $result;
			if ( is_wp_error( $result ) ) {
				if ( function_exists( 'error_log' ) ) {
					error_log( '[JWW Social] ' . $channel . ': ' . $result->get_error_message() );
				}
			}
		} catch ( Exception $e ) {
			$results[ $channel ] = new WP_Error( 'jww_social_exception', $e->getMessage() );
			if ( function_exists( 'error_log' ) ) {
				error_log( '[JWW Social] ' . $channel . ' exception: ' . $e->getMessage() );
			}
		}
	}
	return $results;
}

/**
 * Append a line to the debug log for this request. Shown in Settings → Social Sharing event log when triggering manually.
 * Use for troubleshooting (e.g. Bluesky thumb fetch/upload steps).
 *
 * @param string $channel Channel slug (mastodon, bluesky, pinterest, threads, facebook, instagram).
 * @param string $message Log line (no newlines).
 */
function jww_social_debug_log( $channel, $message ) {
	if ( ! isset( $GLOBALS['jww_social_debug_log'] ) || ! is_array( $GLOBALS['jww_social_debug_log'] ) ) {
		$GLOBALS['jww_social_debug_log'] = array();
	}
	$GLOBALS['jww_social_debug_log'][] = '  [' . $channel . '] ' . $message;
}

/**
 * Clear the debug log (called at start of each dispatch).
 */
function jww_social_debug_log_clear() {
	$GLOBALS['jww_social_debug_log'] = array();
}

/**
 * Get and clear the debug log. Call after jww_social_dispatch() to include lines in the admin event log.
 *
 * @return array List of log lines.
 */
function jww_social_get_debug_log() {
	$log = isset( $GLOBALS['jww_social_debug_log'] ) && is_array( $GLOBALS['jww_social_debug_log'] ) ? $GLOBALS['jww_social_debug_log'] : array();
	$GLOBALS['jww_social_debug_log'] = array();
	return $log;
}

/**
 * Default status text templates (placeholders: {title}, {link}, {description}, {lyrics_line}, {years_ago}).
 * Used when no custom template is saved in options.
 *
 * @return array Map of template_key => default string.
 */
function jww_social_get_default_status_templates() {
	return array(
		'song'             => 'Check out Jesse Welles\' song, "{title}" {link} #jessewelles',
		'song_publish'     => 'New Song Alert! {title} - {link} #jessewelles',
		'show'             => 'New Show Setlist Alert! {title} - {link} #jessewelles',
		'post'             => '{title} {link} #jessewelles',
		'lyric'            => 'Random Jesse Welles lyric of the day "{lyrics_line}" from "{title}" #jessewelles {link}',
		'anniversary_song' => 'Check out Jesse Welles\' song, "{title}" — {years_ago} anniversary! {link} #jessewelles',
		'anniversary_show' => 'New Show Setlist Alert! {title} — {years_ago} anniversary! {link} #jessewelles',
	);
}

/**
 * Get the status text template string for a key (saved option or default).
 *
 * @param string $template_key One of: song, song_publish, show, post, lyric, anniversary_song, anniversary_show.
 * @return string Template string with placeholders.
 */
function jww_social_get_status_template( $template_key ) {
	$option = get_option( 'jww_social_status_text_' . $template_key, '' );
	if ( is_string( $option ) && trim( $option ) !== '' ) {
		return trim( $option );
	}
	$defaults = jww_social_get_default_status_templates();
	return isset( $defaults[ $template_key ] ) ? $defaults[ $template_key ] : '{title} {link}';
}

/**
 * Build status text from a template and payload. Replaces placeholders: {title}, {link}, {description}, {lyrics_line}, {years_ago}.
 *
 * @param string $template_key Template key (song, song_publish, show, post, lyric, anniversary_song, anniversary_show).
 * @param array  $payload      Payload with at least title, link; optional description, lyrics_line, years_ago.
 * @return string Final status text.
 */
function jww_social_build_status_text( $template_key, $payload ) {
	$tpl = jww_social_get_status_template( $template_key );
	$replace = array(
		'{title}'       => isset( $payload['title'] ) ? $payload['title'] : '',
		'{link}'        => isset( $payload['link'] ) ? $payload['link'] : '',
		'{description}' => isset( $payload['description'] ) ? $payload['description'] : '',
		'{lyrics_line}' => isset( $payload['lyrics_line'] ) ? $payload['lyrics_line'] : '',
		'{years_ago}'   => isset( $payload['years_ago'] ) ? $payload['years_ago'] : '1 year',
	);
	return str_replace( array_keys( $replace ), array_values( $replace ), $tpl );
}

/**
 * Normalize payload URLs when running on the dev site: replace dev domain with production so shared posts never contain broken jww.test links.
 * When local is single-site and production is multisite, also rewrite uploads path to include /sites/{blog_id}/ (e.g. /sites/16/).
 * Also forces https for URL fields so shared posts always use https.
 *
 * Only the dev-host replacement runs when the current site host is the dev host (e.g. jww.test). The https step always runs.
 * Replaces in: link, image_url, description, status_text.
 *
 * @param array $payload Normalized social payload.
 * @return array Payload with URLs normalized (dev host → production host, dev uploads → production multisite uploads, http → https).
 */
function jww_social_normalize_payload_urls( $payload ) {
	$home = home_url( '/', 'https' );
	$host = parse_url( $home, PHP_URL_HOST );
	if ( $host ) {
		$replace = apply_filters( 'jww_social_dev_url_replace', array(
			'from' => 'jww.test',
			'to'   => 'jessewellesworld.com',
			'uploads_site_id' => 16,
		) );
		if ( $host === $replace['from'] && ! empty( $replace['to'] ) ) {
			$keys = array( 'link', 'image_url', 'description', 'status_text' );
			$site_id = isset( $replace['uploads_site_id'] ) ? (int) $replace['uploads_site_id'] : 16;
			$dev_uploads_prefix   = '//' . $replace['from'] . '/wp-content/uploads/';
			$prod_uploads_prefix  = '//' . $replace['to'] . '/wp-content/uploads/sites/' . $site_id . '/';
			foreach ( $keys as $key ) {
				if ( ! empty( $payload[ $key ] ) && is_string( $payload[ $key ] ) ) {
					// Rewrite dev uploads URL to production multisite path (e.g. jww.test/.../uploads/ → jessewellesworld.com/.../uploads/sites/16/).
					if ( strpos( $payload[ $key ], $dev_uploads_prefix ) !== false ) {
						$payload[ $key ] = str_replace( $dev_uploads_prefix, $prod_uploads_prefix, $payload[ $key ] );
					}
					// Replace any remaining dev host with production host.
					$payload[ $key ] = str_replace( $replace['from'], $replace['to'], $payload[ $key ] );
				}
			}
		}
	}

	// Always force https for URL fields so shared posts use https.
	$url_keys = array( 'link', 'image_url', 'description', 'status_text' );
	foreach ( $url_keys as $key ) {
		if ( ! empty( $payload[ $key ] ) && is_string( $payload[ $key ] ) ) {
			$payload[ $key ] = str_replace( 'http://', 'https://', $payload[ $key ] );
		}
	}

	return $payload;
}

/**
 * Get the public URL for an attachment, multisite-safe.
 * On multisite subsites, wp_get_attachment_url() can return a path without /sites/{blog_id}/;
 * this builds the URL from the current blog's upload baseurl + _wp_attached_file so it always includes the correct path.
 *
 * @param int $attachment_id Attachment post ID.
 * @return string Attachment URL, or empty string on failure.
 */
function jww_social_attachment_url_for_share( $attachment_id ) {
	if ( ! $attachment_id ) {
		return '';
	}
	if ( is_multisite() && ! is_main_site() ) {
		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['baseurl'] ) && empty( $upload_dir['error'] ) ) {
			$file = get_post_meta( $attachment_id, '_wp_attached_file', true );
			if ( is_string( $file ) && $file !== '' ) {
				return rtrim( $upload_dir['baseurl'], '/' ) . '/' . ltrim( $file, '/' );
			}
		}
	}
	$url = wp_get_attachment_url( $attachment_id );
	return is_string( $url ) ? $url : '';
}

/**
 * Get featured image URL for a show (thumbnail or venue image fallback).
 *
 * @param int $show_id Show post ID.
 * @return string|null URL or null.
 */
function jww_social_show_featured_image_url( $show_id ) {
	$thumb_id = get_post_thumbnail_id( $show_id );
	if ( $thumb_id ) {
		$url = wp_get_attachment_image_url( $thumb_id, 'large' );
		if ( $url ) {
			return $url;
		}
	}
	$location_id = get_field( 'show_location', $show_id );
	if ( $location_id && function_exists( 'jww_get_venue_image_id' ) ) {
		$venue_image_id = jww_get_venue_image_id( $location_id );
		if ( $venue_image_id ) {
			return wp_get_attachment_image_url( $venue_image_id, 'large' );
		}
	}
	return null;
}

/**
 * Cron: Random Song. Picks a random song (tag 244), builds payload, dispatches to Mastodon, Bluesky, Pinterest.
 */
function jww_social_cron_random_song() {
	$run = jww_social_run_random_song();
	// Cron ignores return; results are for admin / testing.
}

/**
 * Run Random Song job (same as cron). Returns results for testing/admin.
 *
 * @return array{ ran: bool, results?: array, payload?: array, error?: string } Ran false if no song; else results keyed by channel (true|WP_Error), optional payload summary.
 */
function jww_social_run_random_song() {
	$args = array(
		'post_type'      => 'song',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'orderby'        => 'rand',
		'tag_id'         => 244,
		'fields'         => 'ids',
	);
	$query = new WP_Query( $args );
	if ( empty( $query->posts ) ) {
		return array( 'ran' => false, 'error' => __( 'No song found (tag 244).', 'jww-theme' ) );
	}
	$song_id = (int) $query->posts[0];
	$title   = get_the_title( $song_id );
	$link    = get_permalink( $song_id );
	$thumb_id = get_post_thumbnail_id( $song_id );
	$image_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'large' ) : null;

	$payload = array(
		'type'        => 'song',
		'title'       => $title,
		'link'        => $link,
		'description' => 'Jesse Welles World - Jesse Welles\' song, ' . $title . ' - video, lyrics and more.',
		'image_url'   => $image_url,
		'image_attachment_id' => $thumb_id ? $thumb_id : null,
	);
	$payload['status_text'] = jww_social_build_status_text( 'song', $payload );

	$channels = array( 'mastodon', 'bluesky', 'threads', 'facebook' );
	if ( ! empty( $image_url ) ) {
		$channels[] = 'pinterest';
		$channels[] = 'instagram';
	}

	$results = jww_social_dispatch( $payload, $channels );
	$debug = function_exists( 'jww_social_get_debug_log' ) ? jww_social_get_debug_log() : array();
	return array(
		'ran'     => true,
		'results' => $results,
		'payload' => array(
			'title' => $title,
			'link'  => $link,
			'type'  => 'song',
		),
		'debug'   => $debug,
	);
}

/**
 * Cron: Random Song Lyric. Gets random lyrics data, builds payload, dispatches (Bluesky always; Mastodon/Pinterest when image present).
 */
function jww_social_cron_random_lyric() {
	$run = jww_social_run_random_lyric();
	// Cron ignores return.
}

/**
 * Cron: Random Show.
 */
function jww_social_cron_random_show() {
	$run = jww_social_run_random_show();
}

/**
 * Cron: Random Blog Post.
 */
function jww_social_cron_random_post() {
	$run = jww_social_run_random_post();
}

/**
 * Run Random Lyric job (same as cron). Returns results for testing/admin.
 *
 * @return array{ ran: bool, results?: array, payload?: array, error?: string }
 */
function jww_social_run_random_lyric() {
	if ( ! function_exists( 'jww_get_random_lyrics_data' ) ) {
		return array( 'ran' => false, 'error' => __( 'jww_get_random_lyrics_data() not available.', 'jww-theme' ) );
	}
	$data = jww_get_random_lyrics_data();
	if ( ! $data || empty( $data['song_link'] ) ) {
		return array( 'ran' => false, 'error' => __( 'No random lyrics data.', 'jww-theme' ) );
	}

	$lyrics_line = isset( $data['lyrics_line'] ) ? $data['lyrics_line'] : '';
	$song_title  = isset( $data['song_title'] ) ? $data['song_title'] : '';
	$song_link   = isset( $data['song_link'] ) ? $data['song_link'] : '';
	$image_url   = isset( $data['featured_image_url'] ) ? $data['featured_image_url'] : null;

	$payload = array(
		'type'         => 'lyric',
		'title'        => $song_title,
		'link'         => $song_link,
		'description'  => $lyrics_line . ' - ' . $song_title . ' - Jesse Welles',
		'image_url'    => $image_url,
		'lyrics_line'   => $lyrics_line,
	);
	$payload['status_text'] = jww_social_build_status_text( 'lyric', $payload );

	$channels = array( 'mastodon', 'bluesky', 'threads', 'facebook' );
	if ( ! empty( $image_url ) ) {
		$channels[] = 'pinterest';
		$channels[] = 'instagram';
	}

	$results = jww_social_dispatch( $payload, $channels );
	$debug = function_exists( 'jww_social_get_debug_log' ) ? jww_social_get_debug_log() : array();
	return array(
		'ran'     => true,
		'results' => $results,
		'payload' => array(
			'title' => $song_title,
			'link'  => $song_link,
			'type'  => 'lyric',
		),
		'debug'   => $debug,
	);
}

/**
 * Build payload for a published song post and dispatch.
 *
 * @param WP_Post $post Song post (already published).
 */
function jww_social_on_publish_song( $post ) {
	if ( ! $post || $post->post_type !== 'song' || $post->post_status !== 'publish' ) {
		return;
	}

	$title     = get_the_title( $post->ID );
	$link      = get_permalink( $post->ID );
	$thumb_id  = get_post_thumbnail_id( $post->ID );
	$image_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'large' ) : null;

	$payload = array(
		'type'        => 'song',
		'title'       => $title,
		'link'        => $link,
		'description' => 'Jesse Welles World - Jesse Welles\' song ' . $title . ' - video, lyrics and more.',
		'image_url'   => $image_url,
		'image_attachment_id' => $thumb_id ? $thumb_id : null,
	);
	$payload['status_text'] = jww_social_build_status_text( 'song_publish', $payload );

	$channels = array( 'mastodon', 'bluesky', 'threads', 'facebook' );
	if ( ! empty( $image_url ) ) {
		$channels[] = 'pinterest';
		$channels[] = 'instagram';
	}

	jww_social_dispatch( $payload, $channels );
}

/**
 * Build payload for a published show (or setlist-first-added) and dispatch. Bluesky link-only (no image in blueprint for New Show).
 *
 * @param int  $show_id   Show post ID.
 * @param bool $with_image Optional. If true, include featured image for Bluesky/Pinterest. Default false for "New Show" (link-only Bluesky).
 */
function jww_social_on_publish_show( $show_id, $with_image = false ) {
	$show = get_post( $show_id );
	if ( ! $show || $show->post_type !== 'show' || $show->post_status !== 'publish' ) {
		return;
	}

	$title = get_the_title( $show_id );
	$link  = get_permalink( $show_id );

	$payload = array(
		'type'        => 'show',
		'title'       => $title,
		'link'        => $link,
		'description' => 'Jesse Welles World - Jesse Welles Show ' . $title . ' - setlist, tour stats and more.',
		'image_url'   => null,
	);
	$payload['status_text'] = jww_social_build_status_text( 'show', $payload );

	if ( $with_image ) {
		$payload['image_url'] = jww_social_show_featured_image_url( $show_id );
	}

	// Blueprint: New Show to Bluesky is link-only (no thumb). So we pass image_url only for Pinterest/Mastodon if desired.
	$channels = array( 'mastodon', 'bluesky', 'threads', 'facebook' );
	if ( ! empty( $payload['image_url'] ) ) {
		$channels[] = 'pinterest';
		$channels[] = 'instagram';
	}

	jww_social_dispatch( $payload, $channels );
}

/**
 * Build payload for sharing a song (full song). Used by post-edit share panel and run_random_song.
 *
 * @param int $post_id Song post ID (published).
 * @return array|null Payload array or null if invalid.
 */
function jww_social_build_payload_for_song( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post || $post->post_type !== 'song' || $post->post_status !== 'publish' ) {
		return null;
	}
	$title     = get_the_title( $post_id );
	$link      = get_permalink( $post_id );
	$thumb_id  = get_post_thumbnail_id( $post_id );
	$image_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'large' ) : null;
	$payload = array(
		'type'        => 'song',
		'title'       => $title,
		'link'        => $link,
		'description' => 'Jesse Welles World - Jesse Welles\' song ' . $title . ' - video, lyrics and more.',
		'image_url'   => $image_url,
		'image_attachment_id' => $thumb_id ? $thumb_id : null,
	);
	$payload['status_text'] = jww_social_build_status_text( 'song', $payload );
	return $payload;
}

/**
 * Build payload for sharing a single lyric line from a song. Used by post-edit share panel.
 *
 * @param int $song_id   Song post ID.
 * @param int $line_index 0-based index into lyrics lines, or -1 for random.
 * @return array|null Payload array or null if no lyrics.
 */
function jww_social_build_payload_for_song_lyric( $song_id, $line_index = -1 ) {
	if ( ! function_exists( 'jww_social_get_lyrics_lines_for_song' ) ) {
		return null;
	}
	$lines = jww_social_get_lyrics_lines_for_song( $song_id );
	if ( empty( $lines ) ) {
		return null;
	}
	if ( $line_index < 0 || $line_index >= count( $lines ) ) {
		$line_index = array_rand( $lines );
	}
	$lyrics_line = $lines[ $line_index ];
	$song_title  = get_the_title( $song_id );
	$song_link   = get_permalink( $song_id );
	$thumb_id    = get_post_thumbnail_id( $song_id );
	$image_url   = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'large' ) : null;
	$payload = array(
		'type'         => 'lyric',
		'title'        => $song_title,
		'link'         => $song_link,
		'description'  => $lyrics_line . ' - ' . $song_title . ' - Jesse Welles',
		'image_url'    => $image_url,
		'image_attachment_id' => $thumb_id ? $thumb_id : null,
		'lyrics_line'  => $lyrics_line,
	);
	$payload['status_text'] = jww_social_build_status_text( 'lyric', $payload );
	return $payload;
}

/**
 * Build payload for sharing a show. Used by post-edit share panel and run_random_show.
 *
 * @param int  $show_id    Show post ID.
 * @param bool $with_image Include featured/venue image. Default true for manual share.
 * @return array|null Payload array or null if invalid.
 */
function jww_social_build_payload_for_show( $show_id, $with_image = true ) {
	$show = get_post( $show_id );
	if ( ! $show || $show->post_type !== 'show' || $show->post_status !== 'publish' ) {
		return null;
	}
	$title = get_the_title( $show_id );
	$link  = get_permalink( $show_id );
	$payload = array(
		'type'        => 'show',
		'title'       => $title,
		'link'        => $link,
		'description' => 'Jesse Welles World - Jesse Welles Show ' . $title . ' - setlist, tour stats and more.',
		'image_url'   => null,
		'image_attachment_id' => null,
	);
	$payload['status_text'] = jww_social_build_status_text( 'show', $payload );
	if ( $with_image ) {
		$thumb_id = get_post_thumbnail_id( $show_id );
		if ( $thumb_id ) {
			$payload['image_url'] = jww_social_show_featured_image_url( $show_id );
			$payload['image_attachment_id'] = $thumb_id;
		} else {
			$location_id = get_field( 'show_location', $show_id );
			if ( $location_id && function_exists( 'jww_get_venue_image_id' ) ) {
				$venue_image_id = jww_get_venue_image_id( $location_id );
				if ( $venue_image_id ) {
					$payload['image_url'] = wp_get_attachment_image_url( $venue_image_id, 'large' );
					$payload['image_attachment_id'] = $venue_image_id;
				}
			}
		}
	}
	return $payload;
}

/**
 * Build payload for sharing a blog post. New share format (type=post) with featured image.
 *
 * @param int $post_id Post ID (post_type=post, published).
 * @return array|null Payload array or null if invalid.
 */
function jww_social_build_payload_for_post( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post || $post->post_type !== 'post' || $post->post_status !== 'publish' ) {
		return null;
	}
	$title       = get_the_title( $post_id );
	$link        = get_permalink( $post_id );
	$description = has_excerpt( $post_id ) ? get_the_excerpt( $post_id ) : wp_trim_words( $post->post_content, 25 );
	$thumb_id    = get_post_thumbnail_id( $post_id );
	$image_url   = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'large' ) : null;
	$payload = array(
		'type'        => 'post',
		'title'       => $title,
		'link'        => $link,
		'description' => $description ?: $title . ' - Jesse Welles World',
		'image_url'   => $image_url,
		'image_attachment_id' => $thumb_id ? $thumb_id : null,
	);
	$payload['status_text'] = jww_social_build_status_text( 'post', $payload );
	return $payload;
}

/**
 * Run random post share (pick one published post, build payload, dispatch). For admin trigger.
 *
 * @return array Same shape as jww_social_run_random_song (ran, results, payload, debug).
 */
function jww_social_run_random_post() {
	$posts = get_posts( array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'orderby'        => 'rand',
		'fields'         => 'ids',
	) );
	if ( empty( $posts ) ) {
		return array( 'ran' => false, 'error' => __( 'No published posts found.', 'jww-theme' ) );
	}
	$post_id = (int) $posts[0];
	$payload = jww_social_build_payload_for_post( $post_id );
	if ( ! $payload ) {
		return array( 'ran' => false, 'error' => __( 'Could not build payload.', 'jww-theme' ) );
	}
	$channels = array( 'mastodon', 'bluesky', 'threads', 'facebook' );
	if ( ! empty( $payload['image_url'] ) ) {
		$channels[] = 'pinterest';
		$channels[] = 'instagram';
	}
	$results = jww_social_dispatch( $payload, $channels );
	$debug   = function_exists( 'jww_social_get_debug_log' ) ? jww_social_get_debug_log() : array();
	return array(
		'ran'     => true,
		'results' => $results,
		'payload' => array( 'title' => $payload['title'], 'link' => $payload['link'], 'type' => 'post' ),
		'debug'   => $debug,
	);
}

/**
 * Run random show share (pick one published show, build payload, dispatch). For admin trigger.
 *
 * @return array Same shape as jww_social_run_random_song.
 */
function jww_social_run_random_show() {
	$shows = get_posts( array(
		'post_type'      => 'show',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'orderby'        => 'rand',
		'fields'         => 'ids',
	) );
	if ( empty( $shows ) ) {
		return array( 'ran' => false, 'error' => __( 'No published shows found.', 'jww-theme' ) );
	}
	$show_id = (int) $shows[0];
	$payload = jww_social_build_payload_for_show( $show_id, true );
	if ( ! $payload ) {
		return array( 'ran' => false, 'error' => __( 'Could not build payload.', 'jww-theme' ) );
	}
	$channels = array( 'mastodon', 'bluesky', 'threads', 'facebook' );
	if ( ! empty( $payload['image_url'] ) ) {
		$channels[] = 'pinterest';
		$channels[] = 'instagram';
	}
	$results = jww_social_dispatch( $payload, $channels );
	$debug   = function_exists( 'jww_social_get_debug_log' ) ? jww_social_get_debug_log() : array();
	return array(
		'ran'     => true,
		'results' => $results,
		'payload' => array( 'title' => $payload['title'], 'link' => $payload['link'], 'type' => 'show' ),
		'debug'   => $debug,
	);
}

/**
 * Run share for a specific song (admin "Share selected"). Same return shape as run_random_song.
 *
 * @param int $post_id Song post ID.
 * @return array
 */
function jww_social_run_specific_song( $post_id ) {
	$payload = jww_social_build_payload_for_song( $post_id );
	if ( ! $payload ) {
		return array( 'ran' => false, 'error' => __( 'Invalid or unpublished song.', 'jww-theme' ) );
	}
	$channels = array( 'mastodon', 'bluesky', 'threads', 'facebook' );
	if ( ! empty( $payload['image_url'] ) ) {
		$channels[] = 'pinterest';
		$channels[] = 'instagram';
	}
	$results = jww_social_dispatch( $payload, $channels );
	$debug   = function_exists( 'jww_social_get_debug_log' ) ? jww_social_get_debug_log() : array();
	return array(
		'ran'     => true,
		'results' => $results,
		'payload' => array( 'title' => $payload['title'], 'link' => $payload['link'], 'type' => 'song' ),
		'debug'   => $debug,
	);
}

/**
 * Run share for a specific lyric (admin "Share selected"). Same return shape as run_random_lyric.
 *
 * @param int $song_id    Song post ID.
 * @param int $line_index 0-based line index, or -1 for random.
 * @return array
 */
function jww_social_run_specific_lyric( $song_id, $line_index = -1 ) {
	$payload = jww_social_build_payload_for_song_lyric( $song_id, $line_index );
	if ( ! $payload ) {
		return array( 'ran' => false, 'error' => __( 'No lyrics available for this song.', 'jww-theme' ) );
	}
	$channels = array( 'mastodon', 'bluesky', 'threads', 'facebook' );
	if ( ! empty( $payload['image_url'] ) ) {
		$channels[] = 'pinterest';
		$channels[] = 'instagram';
	}
	$results = jww_social_dispatch( $payload, $channels );
	$debug   = function_exists( 'jww_social_get_debug_log' ) ? jww_social_get_debug_log() : array();
	return array(
		'ran'     => true,
		'results' => $results,
		'payload' => array( 'title' => $payload['title'], 'link' => $payload['link'], 'type' => 'lyric' ),
		'debug'   => $debug,
	);
}

/**
 * Run share for a specific show (admin "Share selected").
 *
 * @param int $show_id Show post ID.
 * @return array
 */
function jww_social_run_specific_show( $show_id ) {
	$payload = jww_social_build_payload_for_show( $show_id, true );
	if ( ! $payload ) {
		return array( 'ran' => false, 'error' => __( 'Invalid or unpublished show.', 'jww-theme' ) );
	}
	$channels = array( 'mastodon', 'bluesky', 'threads', 'facebook' );
	if ( ! empty( $payload['image_url'] ) ) {
		$channels[] = 'pinterest';
		$channels[] = 'instagram';
	}
	$results = jww_social_dispatch( $payload, $channels );
	$debug   = function_exists( 'jww_social_get_debug_log' ) ? jww_social_get_debug_log() : array();
	return array(
		'ran'     => true,
		'results' => $results,
		'payload' => array( 'title' => $payload['title'], 'link' => $payload['link'], 'type' => 'show' ),
		'debug'   => $debug,
	);
}

/**
 * Run share for a specific blog post (admin "Share selected").
 *
 * @param int $post_id Post ID (post_type=post).
 * @return array
 */
function jww_social_run_specific_post( $post_id ) {
	$payload = jww_social_build_payload_for_post( $post_id );
	if ( ! $payload ) {
		return array( 'ran' => false, 'error' => __( 'Invalid or unpublished post.', 'jww-theme' ) );
	}
	$channels = array( 'mastodon', 'bluesky', 'threads', 'facebook' );
	if ( ! empty( $payload['image_url'] ) ) {
		$channels[] = 'pinterest';
		$channels[] = 'instagram';
	}
	$results = jww_social_dispatch( $payload, $channels );
	$debug   = function_exists( 'jww_social_get_debug_log' ) ? jww_social_get_debug_log() : array();
	return array(
		'ran'     => true,
		'results' => $results,
		'payload' => array( 'title' => $payload['title'], 'link' => $payload['link'], 'type' => 'post' ),
		'debug'   => $debug,
	);
}

/**
 * Transition post status: trigger social post when song, show, or post is published (first time).
 * Respects options jww_social_on_publish_song, jww_social_on_publish_show, jww_social_on_publish_post.
 *
 * @param string   $new_status New post status.
 * @param string   $old_status Old post status.
 * @param WP_Post  $post       Post object.
 */
function jww_social_transition_post_status( $new_status, $old_status, $post ) {
	if ( $new_status !== 'publish' ) {
		return;
	}
	if ( $old_status === 'publish' ) {
		return;
	}

	if ( $post->post_type === 'song' ) {
		if ( get_option( 'jww_social_on_publish_song', '1' ) === '1' ) {
			jww_social_on_publish_song( $post );
		}
		return;
	}

	if ( $post->post_type === 'post' ) {
		if ( get_option( 'jww_social_on_publish_post', '0' ) === '1' ) {
			$payload = jww_social_build_payload_for_post( $post->ID );
			if ( $payload ) {
				$channels = array( 'mastodon', 'bluesky', 'threads', 'facebook' );
				if ( ! empty( $payload['image_url'] ) ) {
					$channels[] = 'pinterest';
					$channels[] = 'instagram';
				}
				jww_social_dispatch( $payload, $channels );
			}
		}
	}
}

/**
 * ACF save_post: when a show's setlist is saved with more than 10 songs (e.g. after setlist.fm sync), share once if the trigger is enabled.
 * Show is often auto-published at show time with empty setlist; sync adds the setlist later, so we share when content is meaningful.
 *
 * @param int $post_id Post ID.
 */
function jww_social_acf_save_post_show_setlist( $post_id ) {
	if ( get_post_type( $post_id ) !== 'show' ) {
		return;
	}
	if ( get_option( 'jww_social_on_publish_show', '1' ) !== '1' ) {
		return;
	}

	$setlist = get_field( 'setlist', $post_id );
	$count   = function_exists( 'jww_count_setlist_songs' ) ? jww_count_setlist_songs( $setlist ) : 0;
	if ( $count <= 10 ) {
		return;
	}

	if ( get_post_meta( $post_id, '_jww_social_show_setlist_shared', true ) === '1' ) {
		return;
	}

	jww_social_on_publish_show( $post_id, true );
	update_post_meta( $post_id, '_jww_social_show_setlist_shared', '1' );
}

/**
 * Run anniversary share for a post type and "years ago" (1 or 2). Same toggle enables both 1- and 2-year.
 * Tracks 1-year and 2-year separately via meta _jww_social_anniversary_shared and _jww_social_anniversary_2y_shared.
 *
 * @param string $post_type  'song' or 'show'.
 * @param int    $years_ago  1 or 2.
 */
function jww_social_run_anniversary_for_years_ago( $post_type, $years_ago ) {
	$years_ago = (int) $years_ago;
	if ( ! in_array( $years_ago, array( 1, 2 ), true ) ) {
		return;
	}
	$option_key = $post_type === 'song' ? 'jww_social_anniversary_song' : 'jww_social_anniversary_show';
	if ( get_option( $option_key, '0' ) !== '1' ) {
		return;
	}

	$meta_key = $years_ago === 1 ? '_jww_social_anniversary_shared' : '_jww_social_anniversary_2y_shared';
	$today = wp_date( 'Y-m-d' );
	$date_ago = gmdate( 'Y-m-d', strtotime( $today . ' -' . $years_ago . ' year' ) );
	$current_year = (int) wp_date( 'Y' );

	$args = array(
		'post_type'      => $post_type,
		'post_status'    => 'publish',
		'posts_per_page' => 50,
		'date_query'     => array(
			array(
				'after'     => $date_ago . ' 00:00:00',
				'before'    => $date_ago . ' 23:59:59',
				'inclusive' => true,
			),
		),
		'fields'         => 'ids',
	);
	$post_ids = get_posts( $args );
	foreach ( $post_ids as $post_id ) {
		if ( (int) get_post_meta( $post_id, $meta_key, true ) === $current_year ) {
			continue;
		}
		if ( $post_type === 'song' ) {
			$payload = jww_social_build_payload_for_song( $post_id );
		} else {
			$payload = jww_social_build_payload_for_show( $post_id, true );
		}
		if ( ! $payload ) {
			continue;
		}
		$payload['years_ago'] = $years_ago === 1 ? __( '1 year', 'jww-theme' ) : __( '2 years', 'jww-theme' );
		$template_key = $post_type === 'song' ? 'anniversary_song' : 'anniversary_show';
		$payload['status_text'] = jww_social_build_status_text( $template_key, $payload );

		$channels = array( 'mastodon', 'bluesky', 'threads', 'facebook' );
		if ( ! empty( $payload['image_url'] ) ) {
			$channels[] = 'pinterest';
			$channels[] = 'instagram';
		}
		jww_social_dispatch( $payload, $channels );
		update_post_meta( $post_id, $meta_key, (string) $current_year );
	}
}

/**
 * Cron: check for song and show anniversaries (1 and 2 years ago today) and share if triggers are enabled.
 */
function jww_social_cron_anniversary() {
	jww_social_run_anniversary_for_years_ago( 'song', 1 );
	jww_social_run_anniversary_for_years_ago( 'song', 2 );
	jww_social_run_anniversary_for_years_ago( 'show', 1 );
	jww_social_run_anniversary_for_years_ago( 'show', 2 );
}

/**
 * Register cron hooks and schedule events. Schedules are read from options (8, 12, 24, 48 hours).
 * Use jww_social_reschedule_cron( $type ) after changing options to apply new schedule.
 */
function jww_social_register_cron() {
	$hook_callbacks = array(
		'jww_social_random_song'  => 'jww_social_cron_random_song',
		'jww_social_random_lyric' => 'jww_social_cron_random_lyric',
		'jww_social_random_show'  => 'jww_social_cron_random_show',
		'jww_social_random_post'  => 'jww_social_cron_random_post',
	);
	$option_keys = array(
		'jww_social_random_song'  => 'jww_social_cron_schedule_song',
		'jww_social_random_lyric' => 'jww_social_cron_schedule_lyric',
		'jww_social_random_show'  => 'jww_social_cron_schedule_show',
		'jww_social_random_post'  => 'jww_social_cron_schedule_post',
	);
	$default_hours = array(
		'jww_social_random_song'  => 24,
		'jww_social_random_lyric' => 24,
		'jww_social_random_show'  => 24,
		'jww_social_random_post'  => 24,
	);

	foreach ( $hook_callbacks as $hook => $callback ) {
		add_action( $hook, $callback );
		$hours = (int) get_option( $option_keys[ $hook ], $default_hours[ $hook ] );
		if ( $hours <= 0 ) {
			wp_clear_scheduled_hook( $hook );
			continue;
		}
		$recurrence = jww_social_cron_recurrence_for_hours( $hours );
		if ( ! wp_next_scheduled( $hook ) ) {
			wp_schedule_event( time(), $recurrence, $hook );
		}
	}

	add_action( 'jww_social_anniversary', 'jww_social_cron_anniversary' );
	if ( ! wp_next_scheduled( 'jww_social_anniversary' ) ) {
		wp_schedule_event( time(), 'daily', 'jww_social_anniversary' );
	}
}

/**
 * Return WordPress cron recurrence key for given hours (8, 12, 24, 48). Default 24.
 *
 * @param int $hours 8, 12, 24, or 48.
 * @return string Recurrence key.
 */
function jww_social_cron_recurrence_for_hours( $hours ) {
	$allowed = array( 8, 12, 24, 48 );
	if ( ! in_array( (int) $hours, $allowed, true ) ) {
		return 'every_24_hours';
	}
	return 'every_' . (int) $hours . '_hours';
}

/**
 * Clear and reschedule a single cron hook with current option. Call after saving cron schedule from admin.
 *
 * @param string $type One of: song, lyric, show, post.
 */
function jww_social_reschedule_cron( $type ) {
	$hooks = array(
		'song'  => 'jww_social_random_song',
		'lyric' => 'jww_social_random_lyric',
		'show'  => 'jww_social_random_show',
		'post'  => 'jww_social_random_post',
	);
	$option_keys = array(
		'song'  => 'jww_social_cron_schedule_song',
		'lyric' => 'jww_social_cron_schedule_lyric',
		'show'  => 'jww_social_cron_schedule_show',
		'post'  => 'jww_social_cron_schedule_post',
	);
	if ( ! isset( $hooks[ $type ] ) ) {
		return;
	}
	$hook = $hooks[ $type ];
	wp_clear_scheduled_hook( $hook );
	$hours = (int) get_option( $option_keys[ $type ], 24 );
	if ( $hours <= 0 ) {
		return;
	}
	$recurrence = jww_social_cron_recurrence_for_hours( $hours );
	wp_schedule_event( time(), $recurrence, $hook );
}

/**
 * Add custom cron schedules (8, 12, 24, 48 hours).
 *
 * @param array $schedules Existing schedules.
 * @return array
 */
function jww_social_cron_schedules( $schedules ) {
	$schedules['every_8_hours'] = array(
		'interval' => 8 * HOUR_IN_SECONDS,
		'display'  => __( 'Every 8 hours', 'jww-theme' ),
	);
	$schedules['every_12_hours'] = array(
		'interval' => 12 * HOUR_IN_SECONDS,
		'display'  => __( 'Every 12 hours', 'jww-theme' ),
	);
	$schedules['every_24_hours'] = array(
		'interval' => 24 * HOUR_IN_SECONDS,
		'display'  => __( 'Every 24 hours', 'jww-theme' ),
	);
	$schedules['every_48_hours'] = array(
		'interval' => 48 * HOUR_IN_SECONDS,
		'display'  => __( 'Every 48 hours', 'jww-theme' ),
	);
	return $schedules;
}

add_filter( 'cron_schedules', 'jww_social_cron_schedules' );

/**
 * Register WordPress hooks for transition_post_status and acf/save_post.
 */
function jww_social_register_hooks() {
	add_action( 'transition_post_status', 'jww_social_transition_post_status', 10, 3 );
	add_action( 'acf/save_post', 'jww_social_acf_save_post_show_setlist', 20 );
}

add_action( 'init', 'jww_social_register_cron', 30 );
add_action( 'init', 'jww_social_register_hooks', 20 );
