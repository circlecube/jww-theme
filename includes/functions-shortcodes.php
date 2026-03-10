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
 * Shortcode for header navigation (adapts based on page type)
 * Usage: [header_navigation]
 * 
 * @return string HTML output
 */
function jww_header_navigation_shortcode() {
	// Get the primary menu location or first available menu
	$menu_locations = get_nav_menu_locations();
	$menu_location_name = null;
	$menu_id = null;
	
	// Try to get primary menu location first
	if ( ! empty( $menu_locations['primary'] ) ) {
		$menu_location_name = 'primary';
		$menu_id = $menu_locations['primary'];
	} elseif ( ! empty( $menu_locations ) ) {
		// Fallback to first available menu location
		$menu_location_name = array_key_first( $menu_locations );
		$menu_id = $menu_locations[ $menu_location_name ];
	} else {
		// Fallback: get first menu from menus
		$menus = wp_get_nav_menus();
		if ( ! empty( $menus ) ) {
			$menu_id = $menus[0]->term_id;
		}
	}
	
	// Build navigation block attributes (same on all pages for consistent mobile nav)
	$base_attrs = array(
		'icon' => 'menu',
		'overlayBackgroundColor' => 'base',
		'overlayTextColor' => 'contrast',
		'className' => 'header-nav-site',
		'style' => array(
			'spacing' => array(
				'blockGap' => 'var:preset|spacing|40'
			)
		),
		'fontSize' => 'medium',
		'layout' => array(
			'type' => 'flex',
			'justifyContent' => 'right',
			'orientation' => 'horizontal',
			'flexWrap' => 'nowrap'
		)
	);
	
	// Add menu reference - use menuId for menu term ID, or menuLocation for location name
	if ( $menu_id ) {
		$base_attrs['menuId'] = $menu_id;
	}
	if ( $menu_location_name ) {
		$base_attrs['menuLocation'] = $menu_location_name;
	}
	
	// Convert to JSON for block markup (unescaped slashes for proper JSON)
	$attrs_json = wp_json_encode( $base_attrs, JSON_UNESCAPED_SLASHES );
	$navigation = '<!-- wp:navigation ' . $attrs_json . ' /-->';
	
	$output = do_blocks( $navigation );
	
	// Ensure navigation block scripts are loaded
	// This is critical for mobile menu functionality
	if ( ! wp_script_is( 'wp-block-navigation-view-script', 'enqueued' ) ) {
		wp_enqueue_script( 'wp-block-navigation-view-script' );
	}
	
	return $output;
}
add_shortcode('header_navigation', 'jww_header_navigation_shortcode');
