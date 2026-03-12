<?php
/**
 * Shortcode Functions
 * 
 * @package JWW_Theme
 * @subpackage Includes
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render core/shortcode blocks without wpautop wrapper.
 * Core wraps shortcode content in <p> via wpautop(); we strip that and run the shortcode
 * so output is never wrapped in empty or redundant p tags.
 *
 * @param string $block_content The block content.
 * @param array  $block         The full block, including name and attributes.
 * @return string Filtered block content.
 */
function jww_render_shortcode_block_without_autop( $block_content, $block ) {
	if ( isset( $block['blockName'] ) && $block['blockName'] === 'core/shortcode' ) {
		// Block content at this point is wpautop( shortcode text ), e.g. "<p>[header_site_title]</p>".
		$text = preg_replace( '#^<p[^>]*>\s*#i', '', $block_content );
		$text = preg_replace( '#\s*</p>\s*$#i', '', $text );
		return do_shortcode( $text );
	}
	return $block_content;
}
add_filter( 'render_block', 'jww_render_shortcode_block_without_autop', 10, 2 );

/**
 * Shortcode for displaying random lyrics inline (minimal format)
 * Usage: [random_lyrics_inline]
 * 
 * @return string HTML output or empty string on failure
 */
function jww_random_lyrics_inline_shortcode() {
	$random_lyrics = jww_get_random_lyrics_data();
	
	if (!$random_lyrics) {
		return '';
	}
	
	ob_start();
	get_template_part('template-parts/random-lyrics-p', null, $random_lyrics);
	return ob_get_clean();
}
add_shortcode('random_lyrics_inline', 'jww_random_lyrics_inline_shortcode');

/**
 * Shortcode for header site title (unified large title site-wide).
 * Outputs site icon (when set) to the left of the site title.
 * Usage: [header_site_title]
 *
 * @return string HTML output
 */
function jww_header_site_title_shortcode() {
	$site_icon_url = get_site_icon_url( 48 );
	$site_title_block = '<!-- wp:site-title {"level":0,"className":"header-large-text","style":{"typography":{"lineHeight":"1.2"},"layout":{"selfStretch":"fill","flexSize":null},"elements":{"link":{"color":{"text":"var:preset|color|base"}}}},"textColor":"base"} /-->';

	if ( $site_icon_url ) {
		$home_url = esc_url( home_url( '/' ) );
		$icon_img = '<img src="' . esc_url( $site_icon_url ) . '" width="40" height="40" alt="" class="header-site-icon" loading="eager" />';
		$icon_html = '<a href="' . $home_url . '" class="header-site-icon-link" aria-hidden="true">' . $icon_img . '</a>';
		$blocks = '<!-- wp:group {"className":"header-title-with-icon","layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group header-title-with-icon is-layout-flex">
	<!-- wp:html -->
	' . $icon_html . '
	<!-- /wp:html -->
	' . $site_title_block . '
</div>
<!-- /wp:group -->';
	} else {
		$blocks = $site_title_block;
	}

	return do_blocks( $blocks );
}
add_shortcode('header_site_title', 'jww_header_site_title_shortcode');

/**
 * Get a wp_navigation post ID by slug (post_name) or title.
 * Block themes use wp_navigation posts; template parts reference them by ref (post ID).
 * This resolves the correct ref by convention so header and footer each get the right menu.
 *
 * @param string $slug  Preferred: post_name/slug (e.g. 'navigation', 'footer-navigation').
 * @param string $title Fallback: post_title (e.g. 'Navigation', 'Footer Navigation').
 * @return int|null wp_navigation post ID or null if not found.
 */
function jww_get_navigation_post_id_by_slug_or_title( $slug, $title = '' ) {
	if ( ! post_type_exists( 'wp_navigation' ) ) {
		return null;
	}
	// Prefer match by post_name (slug).
	if ( $slug ) {
		$by_slug = get_posts( array(
			'post_type'      => 'wp_navigation',
			'post_status'    => 'publish',
			'name'           => $slug,
			'posts_per_page' => 1,
			'no_found_rows'  => true,
		) );
		if ( ! empty( $by_slug ) ) {
			return (int) $by_slug[0]->ID;
		}
	}
	// Fallback: match by post_title (e.g. "Navigation", "Footer Navigation").
	if ( $title ) {
		$all = get_posts( array(
			'post_type'      => 'wp_navigation',
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			'no_found_rows'  => true,
		) );
		foreach ( $all as $post ) {
			if ( isset( $post->post_title ) && $post->post_title === $title ) {
				return (int) $post->ID;
			}
		}
	}
	return null;
}

/**
 * Shortcode for header navigation (block theme: uses wp_navigation post by convention).
 * Usage: [header_navigation]
 *
 * Renders a core/navigation block with ref set to the wp_navigation post named "Navigation"
 * (post_name "navigation" or post_title "Navigation"). So the header always shows that menu;
 * the footer menu is a different wp_navigation post and never appears here.
 *
 * @return string HTML output
 */
function jww_header_navigation_shortcode() {
	$ref = jww_get_navigation_post_id_by_slug_or_title( 'navigation', 'Navigation' );
	if ( ! $ref ) {
		return '';
	}

	$base_attrs = array(
		'ref'                      => $ref,
		'icon'                     => 'menu',
		'overlayBackgroundColor'   => 'base',
		'overlayTextColor'         => 'contrast',
		'className'                => 'header-nav-site',
		'style'                    => array(
			'spacing' => array(
				'blockGap' => 'var:preset|spacing|40',
			),
		),
		'fontSize'                 => 'medium',
		'layout'                   => array(
			'type'           => 'flex',
			'justifyContent' => 'right',
			'orientation'    => 'horizontal',
			'flexWrap'        => 'nowrap',
		),
	);

	$attrs_json = wp_json_encode( $base_attrs, JSON_UNESCAPED_SLASHES );
	$navigation = '<!-- wp:navigation ' . $attrs_json . ' /-->';
	$output = do_blocks( $navigation );

	if ( ! wp_script_is( 'wp-block-navigation-view-script', 'enqueued' ) ) {
		wp_enqueue_script( 'wp-block-navigation-view-script' );
	}

	return $output;
}
add_shortcode( 'header_navigation', 'jww_header_navigation_shortcode' );

/**
 * Shortcode for footer navigation (block theme: uses wp_navigation post by convention).
 * Usage: [footer_navigation]
 *
 * Renders a core/navigation block with ref set to the wp_navigation post named
 * "Footer Navigation" (post_name "footer-navigation" or post_title "Footer Navigation").
 * Use this in parts/footer.html so the footer always shows that menu without a hardcoded ref.
 *
 * @return string HTML output
 */
function jww_footer_navigation_shortcode() {
	$ref = jww_get_navigation_post_id_by_slug_or_title( 'footer-navigation', 'Footer Navigation' );
	if ( ! $ref ) {
		return '';
	}

	$base_attrs = array(
		'ref'                    => $ref,
		'overlayMenu'            => 'never',
		'overlayBackgroundColor' => 'base',
		'overlayTextColor'       => 'contrast',
		'style'                  => array(
			'spacing' => array(
				'blockGap' => 'var:preset|spacing|30',
			),
		),
		'fontSize'               => 'large',
		'layout'                 => array(
			'type'           => 'flex',
			'orientation'    => 'horizontal',
			'justifyContent' => 'center',
			'flexWrap'       => 'wrap',
		),
	);

	$attrs_json = wp_json_encode( $base_attrs, JSON_UNESCAPED_SLASHES );
	$navigation = '<!-- wp:navigation ' . $attrs_json . ' /-->';
	return do_blocks( $navigation );
}
add_shortcode( 'footer_navigation', 'jww_footer_navigation_shortcode' );
