<?php
/**
 * Upgrade routine for version 3.4.0
 *
 * Auto-set location_type on all location terms from hierarchy:
 * - Top level (parent 0) → country
 * - Leaf terms (no children) → venue
 * - Second level with only leaf children → city
 * - Second level with at least one child that has children → state_province
 * - Third level (depth 2) that are not leaves → city
 *
 * @since 3.4.0
 */

$taxonomy = 'location';
$terms = get_terms( array(
	'taxonomy'   => $taxonomy,
	'hide_empty' => false,
	'parent'     => '',
	'fields'     => 'all',
) );

if ( is_wp_error( $terms ) || empty( $terms ) ) {
	error_log( 'Theme Upgrade 3.4.0: No location terms to migrate.' );
	return;
}

// Build parent map and get all term IDs for depth/children lookups
$parent_map = array();
$term_ids = array();
foreach ( $terms as $t ) {
	$parent_map[ $t->term_id ] = (int) $t->parent;
	$term_ids[] = $t->term_id;
}

// For each term: depth (from root), is_leaf, has_grandchildren (for depth 1)
$depth = array();
$is_leaf = array();
$has_grandchildren = array();

foreach ( $terms as $t ) {
	$tid = $t->term_id;
	$d = 0;
	$pid = $parent_map[ $tid ];
	while ( $pid ) {
		$d++;
		$pid = isset( $parent_map[ $pid ] ) ? $parent_map[ $pid ] : 0;
	}
	$depth[ $tid ] = $d;

	$children = get_terms( array(
		'taxonomy' => $taxonomy,
		'parent'   => $tid,
		'fields'   => 'ids',
	) );
	$is_leaf[ $tid ] = is_wp_error( $children ) || empty( $children );

	$has_grandchildren[ $tid ] = false;
	if ( ! $is_leaf[ $tid ] ) {
		foreach ( $children as $cid ) {
			$grand = get_terms( array(
				'taxonomy' => $taxonomy,
				'parent'   => $cid,
				'fields'   => 'ids',
			) );
			if ( ! is_wp_error( $grand ) && ! empty( $grand ) ) {
				$has_grandchildren[ $tid ] = true;
				break;
			}
		}
	}
}

// Assign location_type for each term
$updated = 0;
foreach ( $terms as $t ) {
	$tid = $t->term_id;
	$d = $depth[ $tid ];
	$leaf = $is_leaf[ $tid ];
	$grand = isset( $has_grandchildren[ $tid ] ) ? $has_grandchildren[ $tid ] : false;

	if ( $d === 0 ) {
		$type = 'country';
	} elseif ( $leaf ) {
		$type = 'venue';
	} elseif ( $d === 1 && $grand ) {
		$type = 'state_province';
	} elseif ( $d === 1 && ! $grand ) {
		$type = 'city';
	} elseif ( $d >= 2 && ! $leaf ) {
		$type = 'city';
	} else {
		$type = 'venue';
	}

	update_term_meta( $tid, 'location_type', $type );
	if ( function_exists( 'update_field' ) ) {
		update_field( 'location_type', $type, 'location_' . $tid );
	}
	$updated++;
}

// Clear location caches
delete_transient( 'jww_archive_locations' );
foreach ( $term_ids as $id ) {
	delete_transient( 'jww_location_hierarchy_' . $id );
}

error_log( "Theme Upgrade 3.4.0: Set location_type on {$updated} location terms." );
