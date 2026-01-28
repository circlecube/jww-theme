<?php
/**
 * Register Song History Chart Block
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

register_block_type( __DIR__ . '/block.json' );
