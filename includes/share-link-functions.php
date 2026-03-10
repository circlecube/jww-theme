<?php
/**
 * Social share link helpers: URL-based share intents (X, Facebook, Mastodon, Bluesky, Threads, LinkedIn).
 * Use these to build share URLs or render buttons anywhere on the site.
 *
 * @package JWW_Theme
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build X (Twitter) intent tweet URL. Uses x.com (twitter.com redirects here).
 *
 * @param string $url   Full URL to share (required).
 * @param string $text  Optional pre-filled text (will be truncated with url; total ~280 chars).
 * @return string URL to open for sharing.
 */
function jww_share_url_x( $url, $text = '' ) {
	$url = esc_url_raw( $url );
	$params = array( 'url' => $url );
	if ( $text !== '' ) {
		$params['text'] = $text;
	}
	return 'https://x.com/intent/tweet?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
}

/**
 * Build Facebook share URL.
 *
 * @param string $url Full URL to share (required). Facebook uses only u= for basic share.
 * @return string URL to open for sharing.
 */
function jww_share_url_facebook( $url ) {
	$url = esc_url_raw( $url );
	return 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode( $url );
}

/**
 * Build Mastodon share URL. Uses mastodonshare.com by default (accepts text + url); filter to use a specific instance.
 *
 * @param string $url   Full URL to share.
 * @param string $text  Optional pre-filled status text.
 * @return string URL to open for sharing.
 */
function jww_share_url_mastodon( $url, $text = '' ) {
	$url = esc_url_raw( $url );
	$base = apply_filters( 'jww_share_mastodon_base_url', 'https://mastodonshare.com' );
	$base = rtrim( $base, '/' );
	$params = array( 'url' => $url );
	if ( $text !== '' ) {
		$params['text'] = $text;
	}
	return $base . '/?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
}

/**
 * Build Bluesky compose intent URL.
 *
 * @param string $url   Full URL to share.
 * @param string $text  Optional pre-filled post text (e.g. "Song title – Artist" or selected lyric).
 * @return string URL to open for sharing.
 */
function jww_share_url_bluesky( $url, $text = '' ) {
	$url = esc_url_raw( $url );
	$combined = $text !== '' ? $text . "\n" . $url : $url;
	$params = array( 'text' => $combined );
	return 'https://bsky.app/intent/compose?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
}

/**
 * Build Threads (Meta) post intent URL.
 *
 * @param string $url   Full URL to share.
 * @param string $text  Optional pre-filled post text.
 * @return string URL to open for sharing.
 */
function jww_share_url_threads( $url, $text = '' ) {
	$url = esc_url_raw( $url );
	$base = 'https://www.threads.net/intent/post';
	$params = array();
	if ( $text !== '' ) {
		$params['text'] = $text;
	}
	$params['url'] = $url;
	return $base . '?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
}

/**
 * Build LinkedIn share URL. LinkedIn only accepts url (no custom text in URL); page og:tags supply title/description.
 *
 * @param string $url Full URL to share.
 * @return string URL to open for sharing.
 */
function jww_share_url_linkedin( $url ) {
	$url = esc_url_raw( $url );
	return 'https://www.linkedin.com/sharing/share-offsite/?url=' . rawurlencode( $url );
}

/**
 * Build Reddit submit URL. Title is the post title; url is the link.
 *
 * @param string $url   Full URL to share.
 * @param string $title Optional post title (e.g. song title – artist or quoted lyrics).
 * @return string URL to open for sharing.
 */
function jww_share_url_reddit( $url, $title = '' ) {
	$url = esc_url_raw( $url );
	$params = array( 'url' => $url );
	if ( $title !== '' ) {
		$params['title'] = $title;
	}
	return 'https://www.reddit.com/submit?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
}

/**
 * Build Pinterest pin create URL. Optional description (e.g. song title – artist).
 *
 * @param string $url         Full URL to share.
 * @param string $description Optional pin description.
 * @return string URL to open for sharing.
 */
function jww_share_url_pinterest( $url, $description = '' ) {
	$url = esc_url_raw( $url );
	$params = array( 'url' => $url );
	if ( $description !== '' ) {
		$params['description'] = $description;
	}
	return 'https://www.pinterest.com/pin/create/button/?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
}

/**
 * Get default share text for a song (title and artist). Decoded for display.
 *
 * @param int|null $post_id Optional. Song post ID; defaults to current post.
 * @return string e.g. "Song Title – Artist Name"
 */
function jww_share_song_default_text( $post_id = null ) {
	$post_id = $post_id ? (int) $post_id : get_the_ID();
	$title = $post_id ? get_the_title( $post_id ) : '';
	$artist = '';
	if ( $post_id && function_exists( 'get_field' ) ) {
		$artist_field = get_field( 'artist', $post_id );
		if ( $artist_field && ! empty( $artist_field[0] ) ) {
			$artist = get_the_title( $artist_field[0] );
		}
	}
	if ( $artist === '' ) {
		$artist = 'Jesse Welles';
	}
	return $title !== '' ? $title . ' – ' . $artist : $artist;
}

/**
 * Render social share buttons (X, Facebook, Mastodon, Bluesky, Threads, LinkedIn) as a list of links.
 * Safe to call anywhere; pass URL and optional text for pre-filled content.
 *
 * @param string $url        Full URL to share (e.g. get_permalink()).
 * @param string $text       Optional. Pre-filled quote/title for platforms that support it.
 * @param array  $platforms Optional. Which to show: 'x', 'facebook', 'mastodon', 'bluesky', 'threads', 'linkedin'. Default all six.
 * @param string $context    Optional. CSS class suffix / context (e.g. 'song', 'lyrics-float').
 * @return string HTML fragment (no wrapper; add your own wrapper if needed).
 */
function jww_render_share_buttons( $url, $text = '', $platforms = array( 'x', 'facebook', 'mastodon', 'bluesky', 'threads', 'linkedin', 'reddit', 'pinterest' ), $context = '', $label = '' ) {
	if ( empty( $platforms ) ) {
		return '';
	}
	$url = esc_url( $url );
	$context = is_string( $context ) ? $context : '';
	$class = 'jww-share-buttons';
	if ( $context !== '' ) {
		$class .= ' jww-share-buttons--' . sanitize_html_class( $context );
	}

	$links = array();
	$labels = array(
		'x'        => __( 'Share on X', 'jww-theme' ),
		'facebook' => __( 'Share on Facebook', 'jww-theme' ),
		'mastodon' => __( 'Share on Mastodon', 'jww-theme' ),
		'bluesky'  => __( 'Share on Bluesky', 'jww-theme' ),
		'threads'  => __( 'Share on Threads', 'jww-theme' ),
		'linkedin' => __( 'Share on LinkedIn', 'jww-theme' ),
		'reddit'   => __( 'Share on Reddit', 'jww-theme' ),
		'pinterest'=> __( 'Share on Pinterest', 'jww-theme' ),
	);

	foreach ( $platforms as $key ) {
		$key = strtolower( $key );
		if ( ! isset( $labels[ $key ] ) ) {
			continue;
		}
		$href = '';
		switch ( $key ) {
			case 'x':
				$href = jww_share_url_x( $url, $text );
				break;
			case 'facebook':
				$href = jww_share_url_facebook( $url );
				break;
			case 'mastodon':
				$href = jww_share_url_mastodon( $url, $text );
				break;
			case 'bluesky':
				$href = jww_share_url_bluesky( $url, $text );
				break;
			case 'threads':
				$href = jww_share_url_threads( $url, $text );
				break;
			case 'linkedin':
				$href = jww_share_url_linkedin( $url );
				break;
			case 'reddit':
				$href = jww_share_url_reddit( $url, $text );
				break;
			case 'pinterest':
				$href = jww_share_url_pinterest( $url, $text );
				break;
		}
		if ( $href === '' ) {
			continue;
		}
		$links[] = sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer" class="jww-share-btn jww-share-btn--%s" title="%s" aria-label="%s">%s</a>',
			esc_url( $href ),
			esc_attr( $key ),
			esc_attr( $labels[ $key ] ),
			esc_attr( $labels[ $key ] ),
			function_exists( 'jww_share_icon_svg' ) ? jww_share_icon_svg( $key ) : esc_html( $labels[ $key ] )
		);
	}

	if ( empty( $links ) ) {
		return '';
	}

	$label_html = '';
	if ( $label !== '' ) {
		$label_html = '<span class="jww-song-section-label">' . esc_html( $label ) . '</span>';
	}

	return '<div class="' . esc_attr( $class ) . '" role="group" aria-label="' . esc_attr__( 'Share', 'jww-theme' ) . '">' . $label_html . implode( "\n", $links ) . '</div>';
}
