<?php
/**
 * Register Song Live Statistics Block
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

register_block_type( __DIR__ . '/block.json' );
