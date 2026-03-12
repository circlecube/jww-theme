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
			'label'       => __( 'YouTube video URL (optional)', 'jww-theme' ),
			'placeholder' => __( 'Share a video of this song (optional)', 'jww-theme' ),
		);
	}
	if ( $post_type === 'show' ) {
		return array(
			'label'       => __( 'YouTube video URL (optional)', 'jww-theme' ),
			'placeholder' => __( 'Share a video from this show (optional)', 'jww-theme' ),
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

	// YouTube video URL (optional): only on song and show post types; label/placeholder vary by type.
	$youtube_strings = jww_comment_form_youtube_field_strings();
	if ( $youtube_strings !== null ) {
		$defaults['fields']['jww_youtube_url'] = '<p class="comment-form-youtube-url"><label for="jww_youtube_url" class="screen-reader-text">' . esc_html( $youtube_strings['label'] ) . '</label><input id="jww_youtube_url" name="jww_youtube_url" type="url" value="" size="30" placeholder="' . esc_attr( $youtube_strings['placeholder'] ) . '" /></p>';
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
 * Add YouTube URL field for logged-in users (song/show only); they don't see author/email/url fields.
 */
function jww_comment_form_logged_in_after_youtube_field() {
	if ( ! jww_comment_form_shows_youtube_field() ) {
		return;
	}
	$youtube_strings = jww_comment_form_youtube_field_strings();
	if ( $youtube_strings === null ) {
		return;
	}
	echo '<p class="comment-form-youtube-url"><label for="jww_youtube_url" class="screen-reader-text">' . esc_html( $youtube_strings['label'] ) . '</label><input id="jww_youtube_url" name="jww_youtube_url" type="url" value="" size="30" placeholder="' . esc_attr( $youtube_strings['placeholder'] ) . '" /></p>';
}
add_action( 'comment_form_logged_in_after', 'jww_comment_form_logged_in_after_youtube_field', 10, 0 );

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
 * Sanitize and validate YouTube URL. Allow youtube.com and youtu.be links only.
 *
 * @param string $url Raw input.
 * @return string Sanitized URL or empty string if invalid.
 */
function jww_sanitize_comment_youtube_url( $url ) {
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
	$host = strtolower( $host );
	$allowed = array( 'www.youtube.com', 'youtube.com', 'm.youtube.com', 'youtu.be' );
	if ( ! in_array( $host, $allowed, true ) ) {
		return '';
	}
	return $url;
}

/**
 * Save YouTube URL as comment meta when a comment is posted.
 *
 * @param int $comment_id Comment ID.
 */
function jww_save_comment_youtube_url( $comment_id ) {
	if ( ! isset( $_POST['jww_youtube_url'] ) ) {
		return;
	}
	$url = jww_sanitize_comment_youtube_url( wp_unslash( $_POST['jww_youtube_url'] ) );
	if ( $url !== '' ) {
		add_comment_meta( $comment_id, 'jww_youtube_url', $url, true );
	}
}
add_action( 'comment_post', 'jww_save_comment_youtube_url', 10, 1 );

/**
 * Get oEmbed HTML for a comment's YouTube URL, with caching in comment meta.
 *
 * @param int    $comment_id Comment ID.
 * @param string $url        YouTube URL.
 * @return string|false oEmbed HTML or false on failure.
 */
function jww_get_comment_youtube_embed_html( $comment_id, $url ) {
	$cached = get_comment_meta( $comment_id, 'jww_youtube_embed_html', true );
	if ( is_string( $cached ) && $cached !== '' ) {
		return $cached;
	}
	if ( ! function_exists( 'wp_oembed_get' ) ) {
		return false;
	}
	$embed = wp_oembed_get( $url, array( 'width' => 640 ) );
	if ( ! is_string( $embed ) || $embed === '' ) {
		return false;
	}
	update_comment_meta( $comment_id, 'jww_youtube_embed_html', $embed );
	return $embed;
}

/**
 * Display YouTube video as oEmbed after comment text when present.
 * Label varies by post type. Embed is cached in comment meta after first fetch.
 *
 * @param string     $comment_text Comment text.
 * @param WP_Comment $comment      Comment object.
 * @param array      $args         wp_list_comments args.
 * @return string Modified comment text.
 */
function jww_comment_text_append_youtube_link( $comment_text, $comment, $args ) {
	$comment_id = $comment->comment_ID;
	$url        = get_comment_meta( $comment_id, 'jww_youtube_url', true );
	if ( empty( $url ) || ! is_string( $url ) ) {
		return $comment_text;
	}

	$embed_html = jww_get_comment_youtube_embed_html( $comment_id, $url );
	if ( $embed_html !== false ) {
		$post = get_post( $comment->comment_post_ID );
		$pt   = $post ? $post->post_type : '';
		if ( $pt === 'show' ) {
			$label = __( 'Video from this show', 'jww-theme' );
		} elseif ( $pt === 'song' ) {
			$label = __( 'Video of this song', 'jww-theme' );
		} else {
			$label = __( 'Video', 'jww-theme' );
		}
		$block = '<div class="comment-youtube-embed" aria-label="' . esc_attr( $label ) . '">'
			. '<div class="comment-youtube-embed__wrapper">'
			. $embed_html
			. '</div></div>';
		return $comment_text . $block;
	}

	// Fallback to link if oEmbed failed (e.g. URL changed, provider down).
	$post = get_post( $comment->comment_post_ID );
	$pt   = $post ? $post->post_type : '';
	if ( $pt === 'show' ) {
		$label = __( 'Video from this show', 'jww-theme' );
	} elseif ( $pt === 'song' ) {
		$label = __( 'Video of this song', 'jww-theme' );
	} else {
		$label = __( 'Video', 'jww-theme' );
	}
	$link = '<p class="comment-youtube-link"><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer" class="comment-youtube-link__a">' . esc_html( $label ) . ' ↗</a></p>';
	return $comment_text . $link;
}
add_filter( 'comment_text', 'jww_comment_text_append_youtube_link', 10, 3 );
