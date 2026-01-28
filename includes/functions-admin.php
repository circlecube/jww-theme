<?php
/**
 * Admin Interface Customizations
 * 
 * @package JWW_Theme
 * @subpackage Includes
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add custom columns to the Songs admin table
 */
function jww_add_song_admin_columns( $columns ) {
	// Insert Artist column after title
	$new_columns = array();
	foreach ( $columns as $key => $value ) {
		$new_columns[ $key ] = $value;
		if ( $key === 'title' ) {
			$new_columns['artist'] = 'Artist';
			$new_columns['album'] = 'Album';
		}
	}
	return $new_columns;
}
add_filter( 'manage_song_posts_columns', 'jww_add_song_admin_columns' );

/**
 * Populate custom columns in the Songs admin table
 */
function jww_populate_song_admin_columns( $column, $post_id ) {
	switch ( $column ) {
		case 'artist':
			$artist = get_field( 'artist', $post_id );
			if ( $artist ) {
				// Artist field returns object(s), handle both single and array
				if ( is_array( $artist ) ) {
					$artist_names = array();
					foreach ( $artist as $artist_obj ) {
						if ( is_object( $artist_obj ) && isset( $artist_obj->ID ) ) {
							$artist_names[] = '<a href="' . esc_url( get_edit_post_link( $artist_obj->ID ) ) . '">' . esc_html( get_the_title( $artist_obj->ID ) ) . '</a>';
						} elseif ( is_numeric( $artist_obj ) ) {
							$artist_names[] = '<a href="' . esc_url( get_edit_post_link( $artist_obj ) ) . '">' . esc_html( get_the_title( $artist_obj ) ) . '</a>';
						}
					}
					echo implode( ', ', $artist_names );
				} elseif ( is_object( $artist ) && isset( $artist->ID ) ) {
					echo '<a href="' . esc_url( get_edit_post_link( $artist->ID ) ) . '">' . esc_html( get_the_title( $artist->ID ) ) . '</a>';
				} elseif ( is_numeric( $artist ) ) {
					echo '<a href="' . esc_url( get_edit_post_link( $artist ) ) . '">' . esc_html( get_the_title( $artist ) ) . '</a>';
				}
			} else {
				echo '<span style="color: #999;">—</span>';
			}
			break;

		case 'album':
			$album_id = get_field( 'album', $post_id );
			if ( $album_id ) {
				// Album field returns ID(s), handle both single and array
				if ( is_array( $album_id ) ) {
					$album_names = array();
					foreach ( $album_id as $album ) {
						$album_post_id = is_numeric( $album ) ? $album : ( is_object( $album ) && isset( $album->ID ) ? $album->ID : null );
						if ( $album_post_id ) {
							$album_names[] = '<a href="' . esc_url( get_edit_post_link( $album_post_id ) ) . '">' . esc_html( get_the_title( $album_post_id ) ) . '</a>';
						}
					}
					echo implode( ', ', $album_names );
				} else {
					$album_post_id = is_numeric( $album_id ) ? $album_id : ( is_object( $album_id ) && isset( $album_id->ID ) ? $album_id->ID : null );
					if ( $album_post_id ) {
						echo '<a href="' . esc_url( get_edit_post_link( $album_post_id ) ) . '">' . esc_html( get_the_title( $album_post_id ) ) . '</a>';
					}
				}
			} else {
				echo '<span style="color: #999;">—</span>';
			}
			break;
	}
}
add_action( 'manage_song_posts_custom_column', 'jww_populate_song_admin_columns', 10, 2 );

/**
 * Make Artist and Album columns sortable
 */
function jww_make_song_columns_sortable( $columns ) {
	$columns['artist'] = 'artist';
	$columns['album'] = 'album';
	return $columns;
}
add_filter( 'manage_edit-song_sortable_columns', 'jww_make_song_columns_sortable' );

/**
 * Handle sorting for Artist and Album columns
 */
function jww_sort_songs_by_artist_album( $query ) {
	global $pagenow, $wpdb;
	
	// Only apply on admin edit screen for song post type
	if ( ! is_admin() || $pagenow !== 'edit.php' || ! isset( $_GET['post_type'] ) || $_GET['post_type'] !== 'song' ) {
		return;
	}
	
	// Check if we're sorting by artist or album
	$orderby = isset( $_GET['orderby'] ) ? $_GET['orderby'] : '';
	
	if ( $orderby === 'artist' || $orderby === 'album' ) {
		// Use posts_clauses to add custom JOIN and ORDER BY
		add_filter( 'posts_clauses', 'jww_sort_songs_by_related_title', 10, 2 );
	}
}
add_action( 'pre_get_posts', 'jww_sort_songs_by_artist_album' );

/**
 * Custom sorting by related post title using SQL JOIN
 */
function jww_sort_songs_by_related_title( $clauses, $query ) {
	global $wpdb;
	
	$orderby = isset( $_GET['orderby'] ) ? $_GET['orderby'] : '';
	$order = isset( $_GET['order'] ) ? strtoupper( $_GET['order'] ) : 'ASC';
	
	if ( $orderby === 'artist' ) {
		// Join postmeta to get artist field, then join posts to get artist title
		// ACF stores relationship fields as serialized arrays, so we search for the ID in quotes
		$field_key = 'field_6900d8748ad9f';
		$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS artist_meta ON (
			artist_meta.post_id = {$wpdb->posts}.ID 
			AND artist_meta.meta_key = '{$field_key}'
		)";
		$clauses['join'] .= " LEFT JOIN {$wpdb->posts} AS artist_posts ON (
			artist_meta.meta_value LIKE CONCAT('\"', artist_posts.ID, '\"')
		)";
		$clauses['orderby'] = "COALESCE(artist_posts.post_title, '') " . $order;
		$clauses['groupby'] = "{$wpdb->posts}.ID";
	} elseif ( $orderby === 'album' ) {
		// Join postmeta to get album field, then join posts to get album title
		$field_key = 'field_68cace791977a';
		$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS album_meta ON (
			album_meta.post_id = {$wpdb->posts}.ID 
			AND album_meta.meta_key = '{$field_key}'
		)";
		$clauses['join'] .= " LEFT JOIN {$wpdb->posts} AS album_posts ON (
			album_meta.meta_value LIKE CONCAT('\"', album_posts.ID, '\"')
		)";
		$clauses['orderby'] = "COALESCE(album_posts.post_title, '') " . $order;
		$clauses['groupby'] = "{$wpdb->posts}.ID";
	}
	
	// Remove this filter after use to avoid affecting other queries
	remove_filter( 'posts_clauses', 'jww_sort_songs_by_related_title', 10 );
	
	return $clauses;
}

/**
 * Add Sync button to show row actions
 */
function jww_add_show_sync_action( $actions, $post ) {
	if ( $post->post_type === 'show' && current_user_can( 'edit_post', $post->ID ) ) {
		$setlist_fm_url = get_field( 'setlist_fm_url', $post->ID );
		if ( $setlist_fm_url ) {
			$actions['sync_setlist'] = sprintf(
				'<a href="#" class="jww-sync-setlist" data-show-id="%d" data-nonce="%s">%s</a>',
				esc_attr( $post->ID ),
				esc_attr( wp_create_nonce( 'sync_setlist_' . $post->ID ) ),
				esc_html__( 'Sync from setlist.fm', 'jww-theme' )
			);
		}
	}
	return $actions;
}
add_filter( 'post_row_actions', 'jww_add_show_sync_action', 10, 2 );

/**
 * AJAX handler for syncing show from setlist.fm
 */
function jww_ajax_sync_setlist() {
	$show_id = isset( $_POST['show_id'] ) ? intval( $_POST['show_id'] ) : 0;
	
	if ( ! $show_id ) {
		wp_send_json_error( array( 'message' => 'Show ID is required' ) );
	}
	
	// Verify nonce
	check_ajax_referer( 'sync_setlist_' . $show_id, 'nonce' );
	
	// Check permissions
	if ( ! current_user_can( 'edit_post', $show_id ) ) {
		wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
	}
	
	// Get setlist.fm URL
	$setlist_fm_url = get_field( 'setlist_fm_url', $show_id );
	if ( ! $setlist_fm_url ) {
		wp_send_json_error( array( 'message' => 'No setlist.fm URL found for this show' ) );
	}
	
	// Import from URL
	$importer = new Setlist_Importer();
	$result = $importer->import_from_url( $setlist_fm_url );
	
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}
	
	// Clear statistics cache when show is synced
	if ( function_exists( 'jww_clear_song_stats_caches' ) ) {
		jww_clear_song_stats_caches();
	} else {
		delete_transient( 'jww_all_time_song_stats' );
	}
	
	wp_send_json_success( array(
		'message' => $result['updated'] ? 'Show updated successfully' : 'Show synced successfully',
		'show_id' => $result['show_id'],
	) );
}
add_action( 'wp_ajax_jww_sync_setlist', 'jww_ajax_sync_setlist' );

/**
 * Enqueue admin scripts for sync functionality
 */
function jww_enqueue_show_admin_scripts( $hook ) {
	global $post_type;
	
	if ( $hook === 'edit.php' && $post_type === 'show' ) {
		wp_enqueue_script(
			'jww-show-sync',
			get_stylesheet_directory_uri() . '/includes/js/show-sync.js',
			array( 'jquery' ),
			wp_get_theme()->get( 'Version' ),
			true
		);
		
		wp_localize_script( 'jww-show-sync', 'jwwShowSync', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		) );
	}
}
add_action( 'admin_enqueue_scripts', 'jww_enqueue_show_admin_scripts' );

/**
 * Add custom Location column to show admin table
 */
function jww_add_show_location_column( $columns ) {
	// Insert Location column after title
	$new_columns = array();
	foreach ( $columns as $key => $value ) {
		$new_columns[ $key ] = $value;
		if ( $key === 'title' ) {
			$new_columns['location'] = 'Location';
		}
	}
	return $new_columns;
}
add_filter( 'manage_show_posts_columns', 'jww_add_show_location_column' );

/**
 * Populate Location column in show admin table to show full hierarchy
 * 
 * Displays the full hierarchical path (Venue > City > Country) instead of just the term name.
 */
function jww_populate_show_location_column( $column, $post_id ) {
	if ( $column !== 'location' ) {
		return;
	}

	// Get location term ID from ACF field (show_location stores the venue term ID)
	$location_id = get_field( 'show_location', $post_id );
	
	if ( ! $location_id ) {
		echo '<span style="color: #999;">—</span>';
		return;
	}

	// Build hierarchy path (reversed: Venue > City > Country)
	$hierarchy = array();
	$current_term = get_term( $location_id, 'location' );
	
	if ( ! $current_term || is_wp_error( $current_term ) ) {
		echo '<span style="color: #999;">—</span>';
		return;
	}

	// Start with the venue (current term)
	$hierarchy[] = $current_term;

	// Walk up the parent chain to get city and country
	while ( $current_term->parent ) {
		$parent_term = get_term( $current_term->parent, 'location' );
		if ( $parent_term && ! is_wp_error( $parent_term ) ) {
			$hierarchy[] = $parent_term;
			$current_term = $parent_term;
		} else {
			break;
		}
	}

	// Build links for each level in the hierarchy
	$location_links = array();
	foreach ( $hierarchy as $term ) {
		$term_link = get_edit_term_link( $term->term_id, 'location' );
		if ( $term_link ) {
			$location_links[] = '<a href="' . esc_url( $term_link ) . '">' . esc_html( $term->name ) . '</a>';
		} else {
			$location_links[] = esc_html( $term->name );
		}
	}

	// Display in reverse order (Venue > City > Country)
	// array_reverse because we built from venue up to country
	echo implode( ' <span style="color: #999;">›</span> ', array_reverse( $location_links ) );
}
// Use priority 20 to override WordPress's default taxonomy column handler (priority 10)
add_action( 'manage_show_posts_custom_column', 'jww_populate_show_location_column', 20, 2 );
