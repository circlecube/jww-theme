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
 * Add lyrics field to song REST API response
 *
 * Exposes the ACF lyrics field (WYSIWYG) so API consumers can read song lyrics.
 */
function jww_register_song_lyrics_rest_field() {
	register_rest_field(
		'song',
		'lyrics',
		array(
			'get_callback' => function( $post ) {
				$lyrics = get_field( 'lyrics', $post['id'] );
				return $lyrics !== false && $lyrics !== null && $lyrics !== '' ? $lyrics : null;
			},
			'schema' => array(
				'description' => __( 'Song lyrics (may contain HTML from the editor).', 'jww-theme' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
			),
		)
	);
}
add_action( 'rest_api_init', 'jww_register_song_lyrics_rest_field' );

/**
 * Register REST route: GET random lyrics line
 * Uses jww_get_random_lyrics_data() from functions-acf.php.
 */
function jww_register_random_lyrics_rest_route() {
	register_rest_route( 'jww/v1', '/lyrics/random', array(
		'methods'             => 'GET',
		'callback'            => 'jww_rest_get_random_lyrics',
		'permission_callback' => '__return_true',
		'args'                => array(),
	) );
}
add_action( 'rest_api_init', 'jww_register_random_lyrics_rest_route' );

/**
 * Minimal test route to confirm jww/v1 and theme are loading (optional debug).
 * GET /wp-json/jww/v1/lyrics/random/ping returns {"ok":true}.
 */
function jww_register_random_lyrics_ping_route() {
	register_rest_route( 'jww/v1', '/lyrics/random/ping', array(
		'methods'             => 'GET',
		'callback'            => function () {
			return rest_ensure_response( array( 'ok' => true ) );
		},
		'permission_callback' => '__return_true',
	) );
}
add_action( 'rest_api_init', 'jww_register_random_lyrics_ping_route' );

/**
 * REST callback: return one random lyrics line (for block and API consumers).
 *
 * @param WP_REST_Request $request Request object
 * @return WP_REST_Response|WP_Error
 */
function jww_rest_get_random_lyrics( $request ) {
	try {
		$data = jww_get_random_lyrics_data();
		if ( $data === null ) {
			return new WP_Error( 'no_lyrics', __( 'No songs with lyrics found.', 'jww-theme' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $data );
	} catch ( Exception $e ) {
		return new WP_Error( 'lyrics_error', $e->getMessage(), array( 'status' => 500 ) );
	}
}

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
							return wp_get_attachment_image_url( $featured_image_id, 'large' );
						}
						// For show post type, fallback to venue image when no featured image
						if ( isset( $post['type'] ) && $post['type'] === 'show' && function_exists( 'jww_get_venue_image_id' ) ) {
							$location_id = get_field( 'show_location', $post['id'] );
							if ( $location_id ) {
								$venue_image_id = jww_get_venue_image_id( $location_id );
								if ( $venue_image_id ) {
									return wp_get_attachment_image_url( $venue_image_id, 'large' );
								}
							}
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
 * Decode HTML entities in title.rendered for REST API responses
 *
 * WordPress returns post titles with entities like &#8217; (apostrophe). Decode them
 * so API consumers get plain characters (e.g. "Don't" instead of "Don&#8217;t").
 */
function jww_rest_decode_title_entities( $response, $post, $request ) {
	if ( isset( $response->data['title']['rendered'] ) && is_string( $response->data['title']['rendered'] ) ) {
		$response->data['title']['rendered'] = html_entity_decode(
			$response->data['title']['rendered'],
			ENT_QUOTES | ENT_HTML5,
			'UTF-8'
		);
	}
	return $response;
}

function jww_rest_register_decode_title_entities() {
	$post_types = get_post_types( array( 'show_in_rest' => true ), 'names' );
	foreach ( $post_types as $post_type ) {
		add_filter( "rest_prepare_{$post_type}", 'jww_rest_decode_title_entities', 10, 3 );
	}
}
add_action( 'rest_api_init', 'jww_rest_register_decode_title_entities', 20 );

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
