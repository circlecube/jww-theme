<?php
/**
 * Blocks Index
 * 
 * This file includes all block registration files.
 * Add new blocks by including their register.php file here.
 * 
 * @package JWW_Theme
 * @subpackage Blocks
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include block registration files
require_once __DIR__ . '/latest-song/register.php';
require_once __DIR__ . '/album-covers/register.php';
require_once __DIR__ . '/day-counter/register.php';

// Add more blocks here as needed:
// require_once __DIR__ . '/my-new-block/register.php';
