<?php
/**
 * Register Upcoming Shows Block
 *
 * @package JWW_Theme
 * @subpackage Blocks
 */

namespace JWW_Theme\Blocks\UpcomingShows;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function register_block(): void {
	register_block_type( __DIR__ );
}
add_action( 'init', __NAMESPACE__ . '\register_block' );
