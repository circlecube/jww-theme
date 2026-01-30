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
require_once __DIR__ . '/random-lyrics/register.php';
require_once __DIR__ . '/band-list/register.php';
require_once __DIR__ . '/song-list/register.php';
require_once __DIR__ . '/show-list/register.php';
require_once __DIR__ . '/show-stats/register.php';
require_once __DIR__ . '/song-live-stats/register.php';
require_once __DIR__ . '/song-play-history/register.php';
require_once __DIR__ . '/song-stats-table/register.php';
require_once __DIR__ . '/tour-timeline/register.php';
require_once __DIR__ . '/song-history-chart/register.php';

