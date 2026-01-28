<?php
/**
 * REST API Endpoints for Shows and Statistics
 * 
 * @package JWW_Theme
 * @subpackage Includes
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register custom REST API endpoints for shows and statistics
 */
function jww_register_show_rest_endpoints() {
	// Get shows with filters
	register_rest_route( 'jww/v1', '/shows', array(
		'methods'             => 'GET',
		'callback'            => 'jww_rest_get_shows',
		'permission_callback' => '__return_true',
		'args'                => array(
			'filter'      => array(
				'description' => 'Filter shows (all, upcoming, past)',
				'type'        => 'string',
				'enum'        => array( 'all', 'upcoming', 'past' ),
				'default'     => 'all',
			),
			'tour_id'     => array(
				'description' => 'Filter by tour term ID',
				'type'        => 'integer',
			),
			'location_id' => array(
				'description' => 'Filter by location term ID',
				'type'        => 'integer',
			),
			'per_page'    => array(
				'description' => 'Number of shows to return',
				'type'        => 'integer',
				'default'     => 10,
			),
			'page'        => array(
				'description' => 'Page number',
				'type'        => 'integer',
				'default'     => 1,
			),
		),
	) );

	// Get song statistics
	register_rest_route( 'jww/v1', '/songs/(?P<id>\d+)/stats', array(
		'methods'             => 'GET',
		'callback'            => 'jww_rest_get_song_stats',
		'permission_callback' => '__return_true',
		'args'                => array(
			'id' => array(
				'description' => 'Song post ID',
				'type'        => 'integer',
				'required'    => true,
			),
		),
	) );

	// Get setlist for a show
	register_rest_route( 'jww/v1', '/shows/(?P<id>\d+)/setlist', array(
		'methods'             => 'GET',
		'callback'            => 'jww_rest_get_show_setlist',
		'permission_callback' => '__return_true',
		'args'                => array(
			'id' => array(
				'description' => 'Show post ID',
				'type'        => 'integer',
				'required'    => true,
			),
		),
	) );

	// Get all-time statistics
	register_rest_route( 'jww/v1', '/stats/all-time', array(
		'methods'             => 'GET',
		'callback'            => 'jww_rest_get_all_time_stats',
		'permission_callback' => '__return_true',
		'args'                => array(
			'limit' => array(
				'description' => 'Limit number of results',
				'type'        => 'integer',
				'default'     => 50,
			),
		),
	) );
}
add_action( 'rest_api_init', 'jww_register_show_rest_endpoints' );

/**
 * REST API callback: Get shows with filters
 * 
 * @param WP_REST_Request $request Request object
 * @return WP_REST_Response|WP_Error
 */
function jww_rest_get_shows( $request ) {
	$filter = $request->get_param( 'filter' );
	$tour_id = $request->get_param( 'tour_id' );
	$location_id = $request->get_param( 'location_id' );
	$per_page = $request->get_param( 'per_page' );
	$page = $request->get_param( 'page' );

	$args = array(
		'post_type'      => 'show',
		'posts_per_page' => $per_page,
		'paged'          => $page,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'post_status'    => array( 'publish', 'future' ),
		'tax_query'      => array(),
	);

	// Set order based on filter
	if ( $filter === 'upcoming' ) {
		$args['order'] = 'ASC';
		$args['date_query'] = array(
			array(
				'after' => current_time( 'mysql' ),
			),
		);
	} elseif ( $filter === 'past' ) {
		$args['order'] = 'DESC';
		$args['date_query'] = array(
			array(
				'before' => current_time( 'mysql' ),
			),
		);
		$args['post_status'] = array( 'publish' );
	}

	// Filter by tour
	if ( $tour_id ) {
		$args['tax_query'][] = array(
			'taxonomy' => 'tour',
			'field'    => 'term_id',
			'terms'    => intval( $tour_id ),
		);
	}

	// Filter by location
	if ( $location_id ) {
		$args['tax_query'][] = array(
			'taxonomy' => 'location',
			'field'    => 'term_id',
			'terms'    => intval( $location_id ),
		);
	}

	$query = new WP_Query( $args );
	$shows = array();

	foreach ( $query->posts as $show ) {
		$location_id_field = get_field( 'show_location', $show->ID );
		$tour_id_field = get_field( 'show_tour', $show->ID );
		$setlist = get_field( 'setlist', $show->ID );
		$ticket_link = get_field( 'ticket_link', $show->ID );
		$is_upcoming = strtotime( $show->post_date ) > current_time( 'timestamp' );

		// Get location name
		$location_name = '';
		$location_link = '';
		if ( $location_id_field ) {
			$location_term = get_term( $location_id_field, 'location' );
			if ( $location_term && ! is_wp_error( $location_term ) ) {
				$location_name = $location_term->name;
				$location_link = get_term_link( $location_term->term_id, 'location' );
			}
		}

		// Get tour name and link
		$tour_name = '';
		$tour_link = '';
		if ( $tour_id_field ) {
			$tour_term = get_term( $tour_id_field, 'tour' );
			if ( $tour_term && ! is_wp_error( $tour_term ) ) {
				$tour_name = $tour_term->name;
				$tour_link = get_term_link( $tour_term->term_id, 'tour' );
			}
		}

		// Count songs in setlist
		$song_count = 0;
		if ( $setlist && is_array( $setlist ) ) {
			foreach ( $setlist as $item ) {
				if ( isset( $item['entry_type'] ) && ( $item['entry_type'] === 'song-post' || $item['entry_type'] === 'song-text' ) ) {
					$song_count++;
				}
			}
		}

		$shows[] = array(
			'id'            => $show->ID,
			'title'         => get_the_title( $show->ID ),
			'date'           => get_the_date( 'c', $show->ID ),
			'date_formatted' => get_the_date( 'F j, Y', $show->ID ),
			'link'          => get_permalink( $show->ID ),
			'is_upcoming'   => $is_upcoming,
			'location'      => array(
				'id'   => $location_id_field,
				'name' => $location_name,
				'link' => $location_link,
			),
			'tour'          => array(
				'id'   => $tour_id_field,
				'name' => $tour_name,
				'link' => $tour_link,
			),
			'song_count'    => $song_count,
			'ticket_link'   => $ticket_link,
		);
	}

	$response = rest_ensure_response( $shows );
	$response->header( 'X-WP-Total', $query->found_posts );
	$response->header( 'X-WP-TotalPages', $query->max_num_pages );

	return $response;
}

/**
 * REST API callback: Get song statistics
 * 
 * @param WP_REST_Request $request Request object
 * @return WP_REST_Response|WP_Error
 */
function jww_rest_get_song_stats( $request ) {
	$song_id = intval( $request->get_param( 'id' ) );

	if ( ! function_exists( 'jww_get_song_play_count' ) ) {
		return new WP_Error( 'functions_not_loaded', 'Statistics functions not available', array( 'status' => 500 ) );
	}

	$song = get_post( $song_id );
	if ( ! $song || $song->post_type !== 'song' ) {
		return new WP_Error( 'song_not_found', 'Song not found', array( 'status' => 404 ) );
	}

	$play_count = jww_get_song_play_count( $song_id );
	$last_played = jww_get_song_last_played( $song_id );
	$first_played = jww_get_song_first_played( $song_id );
	$recent_shows = jww_get_song_recent_shows( $song_id, 10 );
	$gap_analysis = jww_get_song_gap_analysis( $song_id );

	$stats = array(
		'song_id'      => $song_id,
		'song_title'   => get_the_title( $song_id ),
		'song_link'    => get_permalink( $song_id ),
		'play_count'   => $play_count,
		'last_played'  => $last_played ? array(
			'show_id'   => $last_played['show_id'],
			'show_date' => $last_played['show_date'],
			'show_link' => $last_played['show_link'],
		) : null,
		'first_played' => $first_played ? array(
			'show_id'   => $first_played['show_id'],
			'show_date' => $first_played['show_date'],
			'show_link' => $first_played['show_link'],
		) : null,
		'recent_shows' => array(),
		'gap_analysis' => $gap_analysis ? array(
			'days_since'  => $gap_analysis['days_since'],
			'play_count'  => $gap_analysis['play_count'],
			'last_played' => array(
				'show_id'   => $gap_analysis['last_played']['show_id'],
				'show_date' => $gap_analysis['last_played']['show_date'],
				'show_link' => $gap_analysis['last_played']['show_link'],
			),
		) : null,
	);

	// Format recent shows
	foreach ( $recent_shows as $show_data ) {
		$stats['recent_shows'][] = array(
			'show_id'       => $show_data['show_id'],
			'show_date'     => $show_data['show_date'],
			'show_link'     => $show_data['show_link'],
			'location_name' => $show_data['location_name'],
		);
	}

	return rest_ensure_response( $stats );
}

/**
 * REST API callback: Get setlist for a show
 * 
 * @param WP_REST_Request $request Request object
 * @return WP_REST_Response|WP_Error
 */
function jww_rest_get_show_setlist( $request ) {
	$show_id = intval( $request->get_param( 'id' ) );

	if ( ! function_exists( 'jww_get_show_setlist' ) ) {
		return new WP_Error( 'functions_not_loaded', 'Statistics functions not available', array( 'status' => 500 ) );
	}

	$show = get_post( $show_id );
	if ( ! $show || $show->post_type !== 'show' ) {
		return new WP_Error( 'show_not_found', 'Show not found', array( 'status' => 404 ) );
	}

	$setlist = jww_get_show_setlist( $show_id );

	$response_data = array(
		'show_id'   => $show_id,
		'show_title' => get_the_title( $show_id ),
		'show_date'  => get_the_date( 'F j, Y', $show_id ),
		'show_link'  => get_permalink( $show_id ),
		'setlist'    => $setlist,
		'song_count' => count( array_filter( $setlist, function( $item ) {
			return $item['entry_type'] === 'song-post' || $item['entry_type'] === 'song-text';
		} ) ),
	);

	return rest_ensure_response( $response_data );
}

/**
 * REST API callback: Get all-time statistics
 * 
 * @param WP_REST_Request $request Request object
 * @return WP_REST_Response|WP_Error
 */
function jww_rest_get_all_time_stats( $request ) {
	$limit = intval( $request->get_param( 'limit' ) );

	if ( ! function_exists( 'jww_get_all_time_song_stats' ) ) {
		return new WP_Error( 'functions_not_loaded', 'Statistics functions not available', array( 'status' => 500 ) );
	}

	$all_song_stats = jww_get_all_time_song_stats();
	$all_songs = get_posts( array(
		'post_type'      => 'song',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
	) );

	// Find songs never played
	$played_song_ids = array();
	foreach ( $all_song_stats as $stat ) {
		$played_song_ids[] = $stat['song_id'];
	}
	$never_played = array();
	foreach ( $all_songs as $song ) {
		if ( ! in_array( $song->ID, $played_song_ids, true ) ) {
			$never_played[] = array(
				'song_id'    => $song->ID,
				'song_title' => get_the_title( $song->ID ),
				'song_link'  => get_permalink( $song->ID ),
			);
		}
	}

	// Get overall stats
	$shows = get_posts( array(
		'post_type'      => 'show',
		'posts_per_page' => -1,
		'post_status'    => array( 'publish', 'future' ),
	) );
	$upcoming_shows = jww_get_upcoming_shows();
	$past_shows = jww_get_past_shows();

	$stats = array(
		'overall' => array(
			'total_shows'        => count( $shows ),
			'upcoming_shows'     => count( $upcoming_shows ),
			'past_shows'         => count( $past_shows ),
			'total_songs_played' => count( $all_song_stats ),
			'total_unique_songs' => count( $all_songs ),
			'songs_never_played' => count( $never_played ),
		),
		'top_songs' => array_slice( array_map( function( $stat ) {
			return array(
				'song_id'      => $stat['song_id'],
				'song_title'   => $stat['song_title'],
				'song_link'    => $stat['song_link'],
				'play_count'   => $stat['play_count'],
				'last_played'  => $stat['last_played'] ? array(
					'show_id'   => $stat['last_played']['show_id'],
					'show_date' => $stat['last_played']['show_date'],
					'show_link' => $stat['last_played']['show_link'],
				) : null,
			);
		}, $all_song_stats ), 0, $limit ),
		'songs_never_played' => array_slice( $never_played, 0, $limit ),
	);

	return rest_ensure_response( $stats );
}
