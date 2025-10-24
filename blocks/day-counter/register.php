<?php
/**
 * Register Day Counter Block
 * 
 * @package JWW_Theme
 * @subpackage Blocks
 */

namespace JWW_Theme\Blocks\DayCounter;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register the Day Counter block
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
    if (file_exists($build_path . 'index.js')) {
        wp_enqueue_script(
            'jww-day-counter-editor',
            get_stylesheet_directory_uri() . '/build/index.js',
            array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-data'),
            filemtime($build_path . 'index.js'),
            true
        );
    }
    
    // Enqueue block editor styles
    if (file_exists($build_path . 'index.css')) {
        wp_enqueue_style(
            'jww-day-counter-editor',
            get_stylesheet_directory_uri() . '/build/index.css',
            array('wp-edit-blocks'),
            filemtime($build_path . 'index.css')
        );
    }
}
add_action('enqueue_block_editor_assets', __NAMESPACE__ . '\enqueue_editor_assets');

/**
 * Enqueue block frontend assets
 */
function enqueue_frontend_assets(): void {
    $build_path = get_stylesheet_directory() . '/build/';
    
    if (file_exists($build_path . 'style-index.css')) {
        wp_enqueue_style(
            'jww-day-counter-style',
            get_stylesheet_directory_uri() . '/build/style-index.css',
            array(),
            filemtime($build_path . 'style-index.css')
        );
    }
}
add_action('enqueue_block_assets', __NAMESPACE__ . '\enqueue_frontend_assets');
