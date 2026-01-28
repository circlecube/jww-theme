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
 * Is Home Page
 * 
 * @return bool True if current page is the home page, false otherwise
 */
function jww_is_home_page() {
	return is_front_page() || is_home();
}

/**
 * Shortcode for header site title (adapts based on page type)
 * Usage: [header_site_title]
 * 
 * @return string HTML output
 */
function jww_header_site_title_shortcode() {	
	if ( jww_is_home_page() ) {
		$site_title = '<!-- wp:site-title {"level":0,"className":"header-large-text","style":{"typography":{"fontSize":"7vw","lineHeight":"1.2"},"layout":{"selfStretch":"fill","flexSize":null},"elements":{"link":{"color":{"text":"var:preset|color|base"}}}},"textColor":"base"} /-->';
	} else {
		$site_title = '<!-- wp:site-title {"level":2,"className":"header-normal-text","fontSize":"xx-large","fontFamily":"roboto-slab"} /-->';
	}

	
	return do_blocks($site_title);
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
	
	// Build navigation block attributes
	$base_attrs = array(
		'icon' => 'menu',
		'overlayBackgroundColor' => 'base',
		'overlayTextColor' => 'contrast',
		'className' => 'header-nav-site',
		'style' => array(
			'spacing' => array(
				'blockGap' => jww_is_home_page() ? 'var:preset|spacing|30' : 'var:preset|spacing|40'
			)
		),
		'fontSize' => 'medium',
		'layout' => array(
			'type' => 'flex',
			'justifyContent' => 'right',
			'orientation' => jww_is_home_page() ? 'vertical' : 'horizontal',
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
