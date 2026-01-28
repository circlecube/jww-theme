<?php
/**
 * REST API Functions
 * 
 * @package JWW_Theme
 * @subpackage Includes
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add support for orderby=rand in REST API for songs
 * 
 * Allows random ordering of songs via REST API query parameter:
 * /wp-json/wp/v2/song?orderby=rand
 */
function jww_rest_song_query( $args, $request ) {
	// Check if orderby=rand is requested
	if ( isset( $request['orderby'] ) && $request['orderby'] === 'rand' ) {
		$args['orderby'] = 'rand';
		// Remove order parameter when using rand (it's not applicable)
		unset( $args['order'] );
	}
	
	return $args;
}
add_filter( 'rest_song_query', 'jww_rest_song_query', 10, 2 );

/**
 * Register orderby=rand as a valid REST API parameter for songs
 */
function jww_rest_song_collection_params( $query_params ) {
	if ( isset( $query_params['orderby'] ) && isset( $query_params['orderby']['enum'] ) ) {
		// Add 'rand' to the allowed orderby values if not already present
		if ( ! in_array( 'rand', $query_params['orderby']['enum'], true ) ) {
			$query_params['orderby']['enum'][] = 'rand';
		}
	}
	
	return $query_params;
}
add_filter( 'rest_song_collection_params', 'jww_rest_song_collection_params', 10, 1 );

/**
 * Add featured image URL to REST API response for all post types
 * 
 * Adds a 'featured_image_url' field to all REST API responses for post types
 * that support featured images
 */
function jww_register_featured_image_rest_field() {
	// Get all public post types that support thumbnails
	$post_types = get_post_types( array(
		'public'       => true,
		'show_in_rest' => true,
	), 'names' );
	
	foreach ( $post_types as $post_type ) {
		// Check if post type supports thumbnails
		if ( post_type_supports( $post_type, 'thumbnail' ) ) {
			register_rest_field(
				$post_type,
				'featured_image_url',
				array(
					'get_callback' => function( $post ) {
						$featured_image_id = get_post_thumbnail_id( $post['id'] );
						if ( $featured_image_id ) {
							return wp_get_attachment_image_url( $featured_image_id, 'full' );
						}
						return null;
					},
					'schema' => array(
						'description' => __( 'URL of the featured image (full size).' ),
						'type'        => 'string',
						'format'      => 'uri',
						'context'     => array( 'view', 'edit' ),
					),
				)
			);
		}
	}
}
add_action( 'rest_api_init', 'jww_register_featured_image_rest_field' );

/**
 * Add show-specific fields to REST API response
 * 
 * Adds setlist, location, tour, and other show fields to REST API
 * Note: Show date is available via WordPress post_date field
 */
function jww_register_show_rest_fields() {
	
	register_rest_field(
		'show',
		'setlist',
		array(
			'get_callback' => function( $post ) {
				return get_field( 'setlist', $post['id'] );
			},
			'schema' => array(
				'description' => __( 'Setlist repeater field data.' ),
				'type'        => 'array',
				'context'     => array( 'view', 'edit' ),
			),
		)
	);
	
	register_rest_field(
		'show',
		'show_location',
		array(
			'get_callback' => function( $post ) {
				$location_id = get_field( 'show_location', $post['id'] );
				if ( $location_id ) {
					$term = get_term( $location_id, 'location' );
					if ( $term && ! is_wp_error( $term ) ) {
						return array(
							'id'   => $term->term_id,
							'name' => $term->name,
							'slug' => $term->slug,
						);
					}
				}
				return null;
			},
			'schema' => array(
				'description' => __( 'Location taxonomy term.' ),
				'type'        => 'object',
				'context'     => array( 'view', 'edit' ),
			),
		)
	);
	
	register_rest_field(
		'show',
		'show_tour',
		array(
			'get_callback' => function( $post ) {
				$tour_id = get_field( 'show_tour', $post['id'] );
				if ( $tour_id ) {
					$term = get_term( $tour_id, 'tour' );
					if ( $term && ! is_wp_error( $term ) ) {
						return array(
							'id'   => $term->term_id,
							'name' => $term->name,
							'slug' => $term->slug,
						);
					}
				}
				return null;
			},
			'schema' => array(
				'description' => __( 'Tour taxonomy term.' ),
				'type'        => 'object',
				'context'     => array( 'view', 'edit' ),
			),
		)
	);
	
	register_rest_field(
		'show',
		'show_artist',
		array(
			'get_callback' => function( $post ) {
				$artist = get_field( 'show_artist', $post['id'] );
				if ( $artist ) {
					if ( is_array( $artist ) && ! empty( $artist ) ) {
						$artist = $artist[0];
					}
					if ( is_object( $artist ) && isset( $artist->ID ) ) {
						return array(
							'id'    => $artist->ID,
							'title' => get_the_title( $artist->ID ),
							'url'   => get_permalink( $artist->ID ),
						);
					}
				}
				return null;
			},
			'schema' => array(
				'description' => __( 'Artist/band performing the show.' ),
				'type'        => 'object',
				'context'     => array( 'view', 'edit' ),
			),
		)
	);
}
add_action( 'rest_api_init', 'jww_register_show_rest_fields' );
