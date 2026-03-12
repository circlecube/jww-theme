<?php
/**
 * Comment form customization: placeholders as labels, YouTube video URL field (song/show only).
 *
 * @package JWW_Theme
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Post types that show the optional YouTube URL field on the comment form. */
define( 'JWW_COMMENT_YOUTUBE_POST_TYPES', array( 'song', 'show' ) );

/**
 * Whether the current singular post type allows the comment form YouTube URL field.
 *
 * @return bool
 */
function jww_comment_form_shows_youtube_field() {
	if ( ! is_singular() ) {
		return false;
	}
	return in_array( get_post_type(), JWW_COMMENT_YOUTUBE_POST_TYPES, true );
}

/**
 * Get YouTube field label and placeholder for the current post type.
 *
 * @return array{label: string, placeholder: string}|null Null if current post type should not show the field.
 */
function jww_comment_form_youtube_field_strings() {
	if ( ! is_singular() ) {
		return null;
	}
	$post_type = get_post_type();
	if ( $post_type === 'song' ) {
		return array(
			'label'       => __( 'Video URL (optional)', 'jww-theme' ),
			'placeholder' => __( 'Video URL(s) (e.g. YouTube link) — share video of this song', 'jww-theme' ),
		);
	}
	if ( $post_type === 'show' ) {
		return array(
			'label'       => __( 'Video URL (optional)', 'jww-theme' ),
			'placeholder' => __( 'Video URL(s) (e.g. YouTube link) — share video from this show', 'jww-theme' ),
		);
	}
	return null;
}

/**
 * Comment form defaults: placeholders instead of labels, add YouTube URL field, form class for styling.
 *
 * @param array $defaults Default comment form arguments.
 * @return array Modified defaults.
 */
function jww_comment_form_defaults( $defaults ) {
	$commenter = wp_get_current_commenter();
	$req       = get_option( 'require_name_email' );
	$html_req  = $req ? ' required="required"' : '';

	$defaults['class_form'] = 'comment-form jww-comment-form';

	// Author: placeholder only, no visible label.
	$defaults['fields']['author'] = '<p class="comment-form-author"><label for="author" class="screen-reader-text">' . __( 'Name', 'jww-theme' ) . ( $req ? ' <span class="required">*</span>' : '' ) . '</label><input id="author" name="author" type="text" value="' . esc_attr( $commenter['comment_author'] ) . '" size="30" maxlength="245" placeholder="' . esc_attr__( 'Name', 'jww-theme' ) . ( $req ? ' *' : '' ) . '"' . $html_req . ' /></p>';

	// Email: placeholder only.
	$defaults['fields']['email'] = '<p class="comment-form-email"><label for="email" class="screen-reader-text">' . __( 'Email', 'jww-theme' ) . ( $req ? ' <span class="required">*</span>' : '' ) . '</label><input id="email" name="email" type="email" value="' . esc_attr( $commenter['comment_author_email'] ) . '" size="30" maxlength="100" placeholder="' . esc_attr__( 'Email', 'jww-theme' ) . ( $req ? ' *' : '' ) . '"' . $html_req . ' /></p>';

	// URL: placeholder only.
	$defaults['fields']['url'] = '<p class="comment-form-url"><label for="url" class="screen-reader-text">' . __( 'Website', 'jww-theme' ) . '</label><input id="url" name="url" type="url" value="' . esc_attr( $commenter['comment_author_url'] ) . '" size="30" maxlength="200" placeholder="' . esc_attr__( 'Website', 'jww-theme' ) . '" /></p>';

	// Video URLs (optional, multiple): only on song and show; label/placeholder vary by type. Name as array for multiple.
	$youtube_strings = jww_comment_form_youtube_field_strings();
	if ( $youtube_strings !== null ) {
		$defaults['fields']['jww_youtube_url'] = '<div class="comment-form-youtube-urls" data-next-index="1">'
			. '<p class="comment-form-youtube-url"><label for="jww_youtube_url_0" class="screen-reader-text">' . esc_html( $youtube_strings['label'] ) . '</label><input id="jww_youtube_url_0" name="jww_youtube_url[]" type="url" value="" size="30" placeholder="' . esc_attr( $youtube_strings['placeholder'] ) . '" /></p>'
			. '</div>';
	}

	// Remove cookies from default position; we output it at the end of the form via comment_form_submit_field.
	unset( $defaults['fields']['cookies'] );

	// Comment textarea: use placeholder instead of visible label.
	$defaults['comment_field'] = '<p class="comment-form-comment"><label for="comment" class="screen-reader-text">' . _x( 'Comment', 'noun', 'jww-theme' ) . ' <span class="required">*</span></label><textarea id="comment" name="comment" cols="45" rows="6" maxlength="65525" placeholder="' . esc_attr__( 'Comment', 'jww-theme' ) . ' *" required="required"></textarea></p>';

	return $defaults;
}
add_filter( 'comment_form_defaults', 'jww_comment_form_defaults', 10, 1 );

/**
 * Put name/email/website first, then comment textarea, then YouTube at the end (when allowed).
 * Core builds comment_fields as comment first + fields; we reorder to: author, email, url, comment, jww_youtube_url.
 *
 * @param array $comment_fields Ordered array of form fields (comment, author, email, url, jww_youtube_url, ...).
 * @return array Reordered so comment is after url and jww_youtube_url is last when on song/show; otherwise YouTube removed.
 */
function jww_comment_form_fields_order( $comment_fields ) {
	$comment = isset( $comment_fields['comment'] ) ? $comment_fields['comment'] : '';
	$youtube = isset( $comment_fields['jww_youtube_url'] ) ? $comment_fields['jww_youtube_url'] : '';
	unset( $comment_fields['comment'], $comment_fields['jww_youtube_url'] );
	$comment_fields['comment'] = $comment;
	if ( jww_comment_form_shows_youtube_field() && $youtube !== '' ) {
		$comment_fields['jww_youtube_url'] = $youtube;
	}
	return $comment_fields;
}
add_filter( 'comment_form_fields', 'jww_comment_form_fields_order', 10, 1 );

/**
 * Add video URL field(s) for logged-in users (song/show only); they don't see author/email/url fields.
 * Same multi-field wrapper as guest form; JS adds more inputs when user fills the last one.
 */
function jww_comment_form_logged_in_after_youtube_field() {
	if ( ! jww_comment_form_shows_youtube_field() ) {
		return;
	}
	$youtube_strings = jww_comment_form_youtube_field_strings();
	if ( $youtube_strings === null ) {
		return;
	}
	echo '<div class="comment-form-youtube-urls" data-next-index="1">'
		. '<p class="comment-form-youtube-url"><label for="jww_youtube_url_0" class="screen-reader-text">' . esc_html( $youtube_strings['label'] ) . '</label><input id="jww_youtube_url_0" name="jww_youtube_url[]" type="url" value="" size="30" placeholder="' . esc_attr( $youtube_strings['placeholder'] ) . '" /></p>'
		. '</div>';
}
add_action( 'comment_form_logged_in_after', 'jww_comment_form_logged_in_after_youtube_field', 10, 0 );

/**
 * Print script so that when the user fills the last video URL input, another one is added. Runs in footer; no jQuery.
 */
function jww_comment_form_youtube_urls_script() {
	if ( ! is_singular() || ! comments_open() || ! jww_comment_form_shows_youtube_field() ) {
		return;
	}
	?>
	<script>
	(function() {
		var container = document.querySelector('.comment-form-youtube-urls');
		if (!container) return;
		function maybeAddMore() {
			var rows = container.querySelectorAll('.comment-form-youtube-url');
			var last = rows[rows.length - 1];
			var input = last ? last.querySelector('input[name="jww_youtube_url[]"]') : null;
			if (!input || !input.value.trim()) return;
			var nextIndex = parseInt(container.getAttribute('data-next-index') || '1', 10);
			var clone = last.cloneNode(true);
			var cloneInput = clone.querySelector('input');
			var cloneLabel = clone.querySelector('label');
			cloneInput.value = '';
			cloneInput.id = 'jww_youtube_url_' + nextIndex;
			if (cloneLabel) cloneLabel.setAttribute('for', 'jww_youtube_url_' + nextIndex);
			container.appendChild(clone);
			container.setAttribute('data-next-index', String(nextIndex + 1));
			cloneInput.focus();
		}
		container.addEventListener('input', maybeAddMore);
		container.addEventListener('change', maybeAddMore);
	})();
	</script>
	<?php
}
add_action( 'wp_footer', 'jww_comment_form_youtube_urls_script', 20 );

/**
 * Move cookies consent to end of form with shorter, smaller label.
 *
 * @param string $submit_field The submit button and hidden fields markup.
 * @param array  $args        Comment form arguments.
 * @return string Submit field plus cookies consent HTML.
 */
function jww_comment_form_submit_field_append_cookies( $submit_field, $args ) {
	if ( ! has_action( 'set_comment_cookies', 'wp_set_comment_cookies' ) || ! get_option( 'show_comments_cookies_opt_in' ) ) {
		return $submit_field;
	}
	$commenter = wp_get_current_commenter();
	$consent   = ' checked="checked"'; // Checked by default so returning visitors' details are saved.
	$label     = __( 'Save my details for future comments', 'jww-theme' );
	$cookies   = '<p class="comment-form-cookies-consent jww-comment-form-cookies-consent">'
		. '<input id="wp-comment-cookies-consent" name="wp-comment-cookies-consent" type="checkbox" value="yes"' . $consent . ' /> '
		. '<label for="wp-comment-cookies-consent">' . esc_html( $label ) . '</label>'
		. '</p>';
	return $submit_field . $cookies;
}
add_filter( 'comment_form_submit_field', 'jww_comment_form_submit_field_append_cookies', 10, 2 );

/**
 * Sanitize and validate video URL for comment embeds. Allows YouTube and Facebook video links.
 * WordPress oEmbeds YouTube by default; Facebook oEmbed was removed in core (5.5.3) so FB links show as links unless a plugin adds support.
 *
 * @param string $url Raw input.
 * @return string Sanitized URL or empty string if invalid.
 */
function jww_sanitize_comment_video_url( $url ) {
	$url = trim( $url );
	if ( $url === '' ) {
		return '';
	}
	$url = esc_url_raw( $url );
	if ( $url === '' ) {
		return '';
	}
	$host = wp_parse_url( $url, PHP_URL_HOST );
	if ( ! $host ) {
		return '';
	}
	$host   = strtolower( $host );
	$allowed = array(
		'www.youtube.com', 'youtube.com', 'm.youtube.com', 'youtu.be',
		'www.facebook.com', 'facebook.com', 'm.facebook.com', 'fb.watch', 'fb.com',
	);
	if ( ! in_array( $host, $allowed, true ) ) {
		return '';
	}
	return $url;
}

/** @deprecated Use jww_sanitize_comment_video_url. */
function jww_sanitize_comment_youtube_url( $url ) {
	return jww_sanitize_comment_video_url( $url );
}

/**
 * Extract video URLs (YouTube, Facebook) from a string (comment content). Uses wp_extract_urls and sanitizes.
 *
 * @param string $content Comment or text content.
 * @return array List of sanitized video URLs (deduped, order preserved).
 */
function jww_extract_youtube_urls_from_text( $content ) {
	if ( ! is_string( $content ) || $content === '' ) {
		return array();
	}
	$found = function_exists( 'wp_extract_urls' ) ? wp_extract_urls( $content ) : array();
	if ( empty( $found ) ) {
		return array();
	}
	$out  = array();
	$seen = array();
	foreach ( $found as $url ) {
		$u = jww_sanitize_comment_video_url( $url );
		if ( $u !== '' && ! isset( $seen[ $u ] ) ) {
			$seen[ $u ] = true;
			$out[]      = $u;
		}
	}
	return $out;
}

/**
 * Save video URL(s) as comment meta when a comment is posted.
 * Collects URLs from the video field(s) and auto-detects YouTube links in the comment text; merges and dedupes.
 *
 * @param int $comment_id Comment ID.
 */
function jww_save_comment_youtube_url( $comment_id ) {
	$out  = array();
	$seen = array();

	// From the video URL form field(s).
	if ( isset( $_POST['jww_youtube_url'] ) ) {
		$raw  = wp_unslash( $_POST['jww_youtube_url'] );
		$urls = is_array( $raw ) ? $raw : array( $raw );
		foreach ( $urls as $url ) {
			$u = jww_sanitize_comment_video_url( is_string( $url ) ? $url : '' );
			if ( $u !== '' && ! isset( $seen[ $u ] ) ) {
				$seen[ $u ] = true;
				$out[]      = $u;
			}
		}
	}

	// Auto-detect YouTube links in the comment text and add to the list.
	$comment = get_comment( $comment_id );
	if ( $comment && ! empty( $comment->comment_content ) ) {
		$from_text = jww_extract_youtube_urls_from_text( $comment->comment_content );
		foreach ( $from_text as $u ) {
			if ( ! isset( $seen[ $u ] ) ) {
				$seen[ $u ] = true;
				$out[]      = $u;
			}
		}
	}

	if ( ! empty( $out ) ) {
		update_comment_meta( $comment_id, 'jww_youtube_url', $out );
	}
}
add_action( 'comment_post', 'jww_save_comment_youtube_url', 10, 1 );

/**
 * Get oEmbed HTML for one comment video URL, with per-URL caching in comment meta.
 * Cache is stored as array in jww_youtube_embed_cache (index matches URL array index).
 *
 * @param int    $comment_id Comment ID.
 * @param string $url        Video URL.
 * @param int    $index      Index of this URL in the comment's URL list (for cache key).
 * @return string|false oEmbed HTML or false on failure.
 */
function jww_get_comment_youtube_embed_html( $comment_id, $url, $index = 0 ) {
	$cache = get_comment_meta( $comment_id, 'jww_youtube_embed_cache', true );
	if ( is_array( $cache ) && isset( $cache[ $index ] ) && is_string( $cache[ $index ] ) && $cache[ $index ] !== '' ) {
		return $cache[ $index ];
	}
	// Legacy: single URL used jww_youtube_embed_html (string).
	if ( $index === 0 ) {
		$legacy = get_comment_meta( $comment_id, 'jww_youtube_embed_html', true );
		if ( is_string( $legacy ) && $legacy !== '' ) {
			return $legacy;
		}
	}
	if ( ! function_exists( 'wp_oembed_get' ) ) {
		return false;
	}
	$embed = wp_oembed_get( $url, array( 'width' => 640 ) );
	if ( ! is_string( $embed ) || $embed === '' ) {
		return false;
	}
	if ( ! is_array( $cache ) ) {
		$cache = array();
	}
	$cache[ $index ] = $embed;
	update_comment_meta( $comment_id, 'jww_youtube_embed_cache', $cache );
	return $embed;
}

/**
 * Display video(s) as oEmbed after comment text when present. Supports single URL (legacy) or array.
 * Label varies by post type. Embeds cached in comment meta per URL.
 *
 * @param string     $comment_text Comment text.
 * @param WP_Comment $comment      Comment object.
 * @param array      $args         wp_list_comments args.
 * @return string Modified comment text.
 */
function jww_comment_text_append_youtube_link( $comment_text, $comment, $args ) {
	$comment_id = $comment->comment_ID;
	$urls       = get_comment_meta( $comment_id, 'jww_youtube_url', true );
	if ( empty( $urls ) ) {
		return $comment_text;
	}
	// Legacy: single URL stored as string.
	if ( is_string( $urls ) ) {
		$urls = array( $urls );
	}
	if ( ! is_array( $urls ) ) {
		return $comment_text;
	}

	$post = get_post( $comment->comment_post_ID );
	$pt   = $post ? $post->post_type : '';
	if ( $pt === 'show' ) {
		$label = __( 'Video from this show', 'jww-theme' );
	} elseif ( $pt === 'song' ) {
		$label = __( 'Video of this song', 'jww-theme' );
	} else {
		$label = __( 'Video', 'jww-theme' );
	}

	$blocks = array();
	foreach ( $urls as $i => $url ) {
		if ( ! is_string( $url ) || $url === '' ) {
			continue;
		}
		$embed_html = jww_get_comment_youtube_embed_html( $comment_id, $url, (int) $i );
		if ( $embed_html !== false ) {
			$blocks[] = '<div class="comment-youtube-embed" aria-label="' . esc_attr( $label ) . '">'
				. '<div class="comment-youtube-embed__wrapper">'
				. $embed_html
				. '</div></div>';
		} else {
			$blocks[] = '<p class="comment-youtube-link"><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer" class="comment-youtube-link__a">' . esc_html( $label ) . '</a></p>';
		}
	}
	if ( empty( $blocks ) ) {
		return $comment_text;
	}
	return $comment_text . implode( "\n", $blocks );
}
add_filter( 'comment_text', 'jww_comment_text_append_youtube_link', 10, 3 );

/**
 * Add target="_blank" and rel="noopener noreferrer" to links in comment text.
 * Core already adds rel="nofollow ugc"; we add open-in-new-tab and security attributes.
 *
 * @param string $comment_text Comment text (may contain HTML).
 * @return string Modified text.
 */
function jww_comment_text_links_target_blank( $comment_text ) {
	if ( ! is_string( $comment_text ) || strpos( $comment_text, '<a ' ) === false ) {
		return $comment_text;
	}
	return preg_replace_callback(
		'/<a\s([^>]*)>/i',
		function ( $m ) {
			$attrs = $m[1];
			// Add target="_blank" if not present.
			if ( ! preg_match( '/\btarget\s*=/i', $attrs ) ) {
				$attrs .= ' target="_blank"';
			}
			// Ensure rel contains noopener and noreferrer (append to existing rel).
			$rel = array();
			if ( preg_match( '/\brel\s*=\s*["\']([^"\']*)["\']/i', $attrs, $rel_m ) ) {
				$existing = array_map( 'trim', explode( ' ', $rel_m[1] ) );
				$rel      = array_merge( $rel, $existing );
			}
			$rel = array_unique( array_merge( $rel, array( 'noopener', 'noreferrer' ) ) );
			$rel = implode( ' ', array_filter( $rel ) );
			// Replace or add rel.
			if ( preg_match( '/\brel\s*=\s*["\'][^"\']*["\']/i', $attrs ) ) {
				$attrs = preg_replace( '/\brel\s*=\s*["\'][^"\']*["\']/i', ' rel="' . esc_attr( $rel ) . '"', $attrs );
			} else {
				$attrs .= ' rel="' . esc_attr( $rel ) . '"';
			}
			return '<a ' . $attrs . '>';
		},
		$comment_text
	);
}
add_filter( 'comment_text', 'jww_comment_text_links_target_blank', 5, 1 );
