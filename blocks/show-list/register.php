<?php
/**
 * Register Show List Block
 * 
 * @package JWW_Theme
 * @subpackage Blocks
 */

namespace JWW_Theme\Blocks\ShowList;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register the Show List block
 */
function register_block(): void {
    register_block_type(__DIR__);
}
add_action('init', __NAMESPACE__ . '\register_block');
