<?php
/**
 * Register Band List Block
 * 
 * @package JWW_Theme
 * @subpackage Blocks
 */

namespace JWW_Theme\Blocks\BandList;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register the Band List block
 */
function register_block(): void {
    register_block_type(__DIR__);
}
add_action('init', __NAMESPACE__ . '\register_block');

/**
 * Enqueue block editor assets
 */
function enqueue_editor_assets(): void {
    $build_path = get_stylesheet_directory() . '/build/';
    
    // Enqueue block editor scripts
    if (file_exists($build_path . 'editor-block-styles.js')) {
        wp_enqueue_script(
            'jww-band-list-editor',
            get_stylesheet_directory_uri() . '/build/editor-block-styles.js',
            array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-data'),
            filemtime($build_path . 'editor-block-styles.js'),
            true
        );
    }
    
    // Enqueue block editor styles
    if (file_exists($build_path . 'editor-block-styles.css')) {
        wp_enqueue_style(
            'jww-band-list-editor',
            get_stylesheet_directory_uri() . '/build/editor-block-styles.css',
            array('wp-edit-blocks'),
            filemtime($build_path . 'editor-block-styles.css')
        );
    }
}
add_action('enqueue_block_editor_assets', __NAMESPACE__ . '\enqueue_editor_assets');

/**
 * Enqueue block frontend assets
 */
function enqueue_frontend_assets(): void {
    $build_path = get_stylesheet_directory() . '/build/';
    
    if (file_exists($build_path . 'block-styles.css')) {
        wp_enqueue_style(
            'jww-band-list-style',
            get_stylesheet_directory_uri() . '/build/block-styles.css',
            array(),
            filemtime($build_path . 'block-styles.css')
        );
    }
}
add_action('enqueue_block_assets', __NAMESPACE__ . '\enqueue_frontend_assets');

