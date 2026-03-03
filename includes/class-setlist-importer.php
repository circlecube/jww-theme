<?php
/**
 * Class Setlist_Importer
 *
 * Imports show data from setlist.fm using REST API or JSON files.
 * Supports fuzzy matching of songs to existing CPTs.
 *
 * @package JWW_Theme
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Setlist_Importer {

	/**
	 * API base URL
	 */
	const API_BASE_URL = 'https://api.setlist.fm/rest/1.0';

	/**
	 * Country codes for which the location taxonomy uses a state/province level (fallback when not set in CMS).
	 * When location terms have "Country has states" and "Country code" set, jww_get_countries_with_state_level_codes() is used instead.
	 *
	 * @var array
	 */
	const COUNTRIES_WITH_STATE_LEVEL = array( 'US', 'CA', 'AU' );

	/**
	 * Get country codes that use state/province level (from CMS when set, else fallback constant).
	 *
	 * @return array Uppercase codes, e.g. array( 'US', 'CA', 'AU' ).
	 */
	private function get_countries_with_state_level_codes() {
		if ( function_exists( 'jww_get_countries_with_state_level_codes' ) ) {
			return jww_get_countries_with_state_level_codes();
		}
		return self::COUNTRIES_WITH_STATE_LEVEL;
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		// Class is instantiated when needed
	}

	/**
	 * Get API key from .env file, WordPress options, or ACF options
	 *
	 * Priority: .env file > WordPress options > ACF options
	 *
	 * @return string|false API key or false if not set
	 */
	private function get_api_key() {
		// First, try to get from .env file
		$env_key = $this->get_api_key_from_env();
		if ( $env_key ) {
			return $env_key;
		}

		// Try WordPress options (new storage location)
		$option_key = get_option( 'jww_setlist_fm_api_key', false );
		if ( $option_key ) {
			return $option_key;
		}

		// Fallback to ACF options (for backward compatibility)
		if ( function_exists( 'get_field' ) ) {
			$acf_key = get_field( 'setlist_fm_api_key', 'option' );
			if ( $acf_key ) {
				return $acf_key;
			}
		}
		// Fallback to raw option
		return get_option( 'options_setlist_fm_api_key', false );
	}

	/**
	 * Get API key from .env file
	 *
	 * @return string|false API key or false if not found
	 */
	private function get_api_key_from_env() {
		$env_file = get_stylesheet_directory() . '/.env';
		
		// Check if .env file exists
		if ( ! file_exists( $env_file ) || ! is_readable( $env_file ) ) {
			return false;
		}

		// Read .env file
		$env_content = file_get_contents( $env_file );
		if ( $env_content === false ) {
			return false;
		}

		// Parse .env file for SETLIST_FM_API_KEY
		$lines = explode( "\n", $env_content );
		foreach ( $lines as $line ) {
			$line = trim( $line );
			
			// Skip empty lines and comments
			if ( empty( $line ) || $line[0] === '#' ) {
				continue;
			}

			// Check if this line contains SETLIST_FM_API_KEY
			if ( preg_match( '/^SETLIST_FM_API_KEY\s*=\s*(.+)$/i', $line, $matches ) ) {
				$key = trim( $matches[1] );
				// Remove quotes if present
				$key = trim( $key, '"\'`' );
				return $key;
			}
		}

		return false;
	}

	/**
	 * Import from setlist.fm API
	 *
	 * @param string $setlist_id Setlist ID from setlist.fm
	 * @param string|null $api_key Optional API key (uses stored key if not provided)
	 * @param string|null $setlist_url Optional setlist.fm URL (for storing reference)
	 * @return array|WP_Error Import result with show ID and status
	 */
	public function import_from_api( $setlist_id, $api_key = null, $setlist_url = null ) {
		// Validate setlist ID
		if ( empty( $setlist_id ) || ! is_string( $setlist_id ) ) {
			return new WP_Error( 'invalid_setlist_id', 'Invalid setlist ID provided' );
		}

		if ( ! $api_key ) {
			$api_key = $this->get_api_key();
		}

		if ( ! $api_key ) {
			return new WP_Error( 'no_api_key', 'setlist.fm API key is required' );
		}

		$url = self::API_BASE_URL . '/setlist/' . urlencode( $setlist_id );
		
		$response = wp_remote_get( $url, array(
			'headers' => array(
				'Accept'        => 'application/json',
				'x-api-key'     => $api_key,
			),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'api_request_failed', 'Failed to connect to setlist.fm API: ' . $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code !== 200 ) {
			$body = wp_remote_retrieve_body( $response );
			$error_message = 'API request failed';
			
			if ( $status_code === 404 ) {
				$error_message = 'Setlist not found. Please check the setlist ID or URL.';
			} elseif ( $status_code === 401 || $status_code === 403 ) {
				$error_message = 'API authentication failed. Please check your API key.';
			} elseif ( $status_code === 429 ) {
				$error_message = 'API rate limit exceeded. Please try again later.';
			} else {
				$error_message = 'API request failed with status ' . $status_code;
			}
			
			return new WP_Error( 'api_error', $error_message );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error( 'json_error', 'Failed to parse API response: ' . json_last_error_msg() );
		}

		// Validate required fields in response
		if ( empty( $data['eventDate'] ) ) {
			return new WP_Error( 'invalid_data', 'API response missing required field: eventDate' );
		}

		if ( empty( $data['venue']['name'] ) ) {
			return new WP_Error( 'invalid_data', 'API response missing required field: venue.name' );
		}

		// Add URL to data if provided
		if ( $setlist_url && ! isset( $data['url'] ) ) {
			$data['url'] = $setlist_url;
		}

		return $this->create_show_from_data( $data );
	}

	/**
	 * Import from setlist.fm URL
	 *
	 * @param string $url setlist.fm URL
	 * @param string|null $api_key Optional API key
	 * @return array|WP_Error Import result
	 */
	public function import_from_url( $url, $api_key = null ) {
		// Validate URL
		if ( empty( $url ) || ! is_string( $url ) ) {
			return new WP_Error( 'invalid_url', 'URL is required' );
		}

		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return new WP_Error( 'invalid_url', 'Invalid URL format' );
		}

		if ( strpos( $url, 'setlist.fm' ) === false ) {
			return new WP_Error( 'invalid_url', 'URL must be from setlist.fm' );
		}

		// Extract setlist ID from URL
		// Format: https://www.setlist.fm/setlist/jesse-welles/2026/the-factory-theatre-sydney-australia-3341fcd1.html
		// Or: https://www.setlist.fm/upcoming/jesse-welles/2026/the-eastern-atlanta-ga-1b4ca9cc.html
		// ID is the last part before .html (hex string)
		$setlist_id = null;
		if ( preg_match( '/-([a-f0-9]+)\.html$/', $url, $matches ) ) {
			$setlist_id = $matches[1];
		} elseif ( preg_match( '/(?:setlist|upcoming)\/([^\/]+)\/(\d{4})\/[^\/]+-([a-f0-9]+)\.html/', $url, $matches ) ) {
			$setlist_id = $matches[3];
		}

		if ( ! $setlist_id ) {
			return new WP_Error( 'invalid_url', 'Could not extract setlist ID from URL. Please ensure the URL is a valid setlist.fm setlist page.' );
		}

		// Import from API - pass URL so it can be stored
		$result = $this->import_from_api( $setlist_id, $api_key, $url );
		
		return $result;
	}

	/**
	 * Import from JSON file/string
	 *
	 * @param string|array $json_data JSON string or decoded array
	 * @return array|WP_Error Import result
	 */
	public function import_from_json( $json_data ) {
		// Validate input
		if ( ! is_array( $json_data ) && ! is_string( $json_data ) ) {
			return new WP_Error( 'invalid_data', 'JSON data must be an array or JSON string' );
		}

		if ( is_string( $json_data ) ) {
			$data = json_decode( $json_data, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return new WP_Error( 'invalid_json', 'Invalid JSON data: ' . json_last_error_msg() );
			}
		} else {
			$data = $json_data;
		}

		// Validate required fields
		if ( empty( $data['eventDate'] ) ) {
			return new WP_Error( 'invalid_data', 'Missing required field: eventDate' );
		}

		if ( empty( $data['venue']['name'] ) ) {
			return new WP_Error( 'invalid_data', 'Missing required field: venue.name' );
		}

		return $this->create_show_from_data( $data );
	}

	/**
	 * Create show post from setlist.fm API data
	 *
	 * @param array $api_data Parsed API response
	 * @return array|WP_Error Result with show_id and status
	 */
	public function create_show_from_data( $api_data ) {
		// Validate input
		if ( ! is_array( $api_data ) ) {
			return new WP_Error( 'invalid_data', 'Invalid data format' );
		}

		// Parse event date
		$event_date = isset( $api_data['eventDate'] ) ? $api_data['eventDate'] : '';
		if ( ! $event_date ) {
			return new WP_Error( 'missing_date', 'Event date is required' );
		}

		// Parse date format: DD-MM-YYYY
		$date_parts = explode( '-', $event_date );
		if ( count( $date_parts ) !== 3 ) {
			return new WP_Error( 'invalid_date', 'Invalid date format. Expected DD-MM-YYYY, got: ' . esc_html( $event_date ) );
		}

		// Validate date parts are numeric
		if ( ! is_numeric( $date_parts[0] ) || ! is_numeric( $date_parts[1] ) || ! is_numeric( $date_parts[2] ) ) {
			return new WP_Error( 'invalid_date', 'Date must contain numeric values. Got: ' . esc_html( $event_date ) );
		}

		// Validate date is valid
		$day = intval( $date_parts[0] );
		$month = intval( $date_parts[1] );
		$year = intval( $date_parts[2] );
		
		if ( ! checkdate( $month, $day, $year ) ) {
			return new WP_Error( 'invalid_date', 'Invalid date: ' . esc_html( $event_date ) );
		}

		$wp_date = $date_parts[2] . '-' . $date_parts[1] . '-' . $date_parts[0] . ' 20:00:00';

		// Get setlist.fm URL if provided
		// API returns the current URL (may be /setlist/ or /upcoming/ depending on show date)
		$setlist_fm_url = isset( $api_data['url'] ) ? $api_data['url'] : '';

		// Check if show already exists (by date and venue, or by setlist.fm URL/ID)
		$venue_name = isset( $api_data['venue']['name'] ) ? $api_data['venue']['name'] : '';
		$existing_show = null;
		
		// First check by setlist.fm URL/ID if we have one
		// This handles both /upcoming/ and /setlist/ URLs by matching the setlist ID
		if ( $setlist_fm_url ) {
			$existing_show = $this->find_existing_show_by_url( $setlist_fm_url );
		}
		
		// If not found by URL, check by date and venue
		if ( ! $existing_show ) {
			$existing_show = $this->find_existing_show( $wp_date, $venue_name );
		}
		
		// If show exists, update it instead of creating new
		$is_update = false;
		if ( $existing_show ) {
			$show_id = $existing_show;
			$is_update = true;
		} else {
			// Create new show post
			$post_data = array(
				'post_type'   => 'show',
				'post_title'  => '', // Will be auto-generated by save_post hook
				'post_status' => strtotime( $wp_date ) > current_time( 'timestamp' ) ? 'future' : 'publish',
				'post_date'   => $wp_date,
			);

			$show_id = wp_insert_post( $post_data );
			if ( is_wp_error( $show_id ) ) {
				return $show_id;
			}
		}

		// Create location taxonomy term
		$location_id = $this->create_location_term( $api_data['venue'] ?? array() );
		if ( is_wp_error( $location_id ) ) {
			return $location_id;
		}

		// Create tour taxonomy term if provided
		$tour_id = null;
		if ( ! empty( $api_data['tour']['name'] ) ) {
			$tour_id = $this->create_tour_term( $api_data['tour']['name'] );
			if ( is_wp_error( $tour_id ) ) {
				// Don't fail if tour creation fails, just log it
				error_log( 'Setlist Importer: Failed to create tour term: ' . $tour_id->get_error_message() );
			}
		}

		// Set ACF fields first (needed for title generation)
		update_field( 'show_location', $location_id, $show_id );
		if ( $tour_id ) {
			update_field( 'show_tour', $tour_id, $show_id );
		}
		
		// Store setlist.fm URL
		if ( $setlist_fm_url ) {
			update_field( 'setlist_fm_url', $setlist_fm_url, $show_id );
		}

		// Generate title and slug from location and date
		$title_data = $this->generate_show_title_and_slug( $location_id, $wp_date, $show_id );
		$show_title = $title_data['title'];
		$show_slug = $title_data['slug'];

		// Update post with title, slug, date, and status
		$update_data = array(
			'ID'         => $show_id,
			'post_title' => $show_title,
			'post_name'  => $show_slug,
			'post_date'  => $wp_date,
			'post_status' => strtotime( $wp_date ) > current_time( 'timestamp' ) ? 'future' : 'publish',
		);
		
		$update_result = wp_update_post( $update_data, true );
		if ( is_wp_error( $update_result ) ) {
			// Clean up the post if it was just created
			if ( ! $is_update ) {
				wp_delete_post( $show_id, true );
			}
			return new WP_Error( 'update_failed', 'Failed to update show post: ' . $update_result->get_error_message() );
		}

		// Process setlist - always update setlist on sync/import
		// Handle both 'sets' and 'set' structures
		$sets_data = array();
		if ( isset( $api_data['sets']['set'] ) ) {
			$sets_data = $api_data['sets']['set'];
		} elseif ( isset( $api_data['set'] ) ) {
			$sets_data = $api_data['set'];
		}
		
		// Ensure it's an array
		if ( ! is_array( $sets_data ) ) {
			$sets_data = array();
		}
		
		$setlist = $this->parse_setlist( $sets_data );
		// Always update setlist, even if empty (to clear old data on sync)
		update_field( 'setlist', $setlist, $show_id );
		// Keep admin list performant: store song count in post meta.
		if ( function_exists( 'jww_count_setlist_songs' ) ) {
			update_post_meta( $show_id, '_show_song_count', jww_count_setlist_songs( $setlist ) );
		}

		// Set show notes if provided
		// On update/sync, merge new info with existing if both exist and content actually differs
		if ( ! empty( $api_data['info'] ) ) {
			$existing_notes = get_field( 'show_notes', $show_id );
			$new_info       = $api_data['info'];
			$is_same        = $this->normalize_notes_for_compare( $existing_notes ) === $this->normalize_notes_for_compare( $new_info );
			if ( $is_update && ! empty( $existing_notes ) && ! $is_same ) {
				// Append new info only when it's actually different (avoids dupes from formatting/HTML)
				update_field( 'show_notes', $existing_notes . "\n\n" . $new_info, $show_id );
			} elseif ( ! $is_same ) {
				update_field( 'show_notes', $new_info, $show_id );
			}
		}

		// Set times (doors, start, end) if provided in data (setlist.fm API doesn't include these; JSON/other sources can)
		$set_times = $this->parse_set_times_from_data( $api_data );
		if ( ! empty( $set_times ) ) {
			update_field( 'set_times', $set_times, $show_id );
		}

		// Clear statistics cache when show is created/updated
		if ( function_exists( 'jww_clear_song_stats_caches' ) ) {
			jww_clear_song_stats_caches();
		} else {
			delete_transient( 'jww_all_time_song_stats' );
		}

		// Store setlist.fm lastUpdated so we can detect when the setlist changes on setlist.fm
		if ( ! empty( $api_data['lastUpdated'] ) ) {
			update_post_meta( $show_id, '_setlist_fm_api_last_updated', $api_data['lastUpdated'] );
		}

		return array(
			'success'  => true,
			'show_id'  => $show_id,
			'message'  => $is_update ? 'Show updated successfully' : 'Show imported successfully',
			'updated'  => $is_update,
		);
	}

	/**
	 * Normalize show notes text for comparison (ignore HTML and whitespace differences)
	 *
	 * ACF show_notes is WYSIWYG so it may contain HTML; API info is plain text.
	 *
	 * @param string|null $text Raw notes (may be HTML or plain text)
	 * @return string Trimmed, single-space-normalized plain text
	 */
	private function normalize_notes_for_compare( $text ) {
		if ( $text === null || $text === '' ) {
			return '';
		}
		$text = wp_strip_all_tags( (string) $text );
		$text = preg_replace( '/\s+/', ' ', $text );
		return trim( $text );
	}

	/**
	 * Parse set times from API/import data into ACF format (H:i:s)
	 *
	 * Uses (in order): (1) structured set_times/doors_time/start_time/end_time in data,
	 * (2) times parsed from the setlist.fm "info" field (e.g. "Doors: 7pm. Show: 8pm.").
	 * The setlist.fm API docs do not list doors/start/end as separate fields; editors
	 * often put times in the free-text info. If the API adds structured times later,
	 * they will be picked up automatically.
	 *
	 * @param array $data Import data (e.g. API response or JSON)
	 * @return array Keys doors_time, start_time, end_time (only non-empty, in H:i:s)
	 */
	private function parse_set_times_from_data( $data ) {
		$set_times = array();
		$keys      = array( 'doors_time', 'start_time', 'end_time' );

		// 1) Structured keys: nested set_times or top-level doors_time/start_time/end_time
		$source = isset( $data['set_times'] ) && is_array( $data['set_times'] ) ? $data['set_times'] : $data;
		foreach ( $keys as $key ) {
			$raw = isset( $source[ $key ] ) ? trim( (string) $source[ $key ] ) : '';
			if ( $raw === '' ) {
				continue;
			}
			$normalized = $this->normalize_time_to_his( $raw );
			if ( $normalized !== '' ) {
				$set_times[ $key ] = $normalized;
			}
		}

		// 2) If no structured times, try to parse from setlist.fm "info" text
		if ( empty( $set_times ) && ! empty( $data['info'] ) && is_string( $data['info'] ) ) {
			$set_times = $this->parse_set_times_from_info_text( $data['info'] );
		}

		return $set_times;
	}

	/**
	 * Parse doors/start/end times from setlist.fm "info" free text (e.g. "Doors: 7pm. Show: 8pm.")
	 *
	 * @param string $info Info field content
	 * @return array Keys doors_time, start_time, end_time (only when matched, in H:i:s)
	 */
	private function parse_set_times_from_info_text( $info ) {
		$set_times = array();
		$info      = trim( (string) $info );
		if ( $info === '' ) {
			return $set_times;
		}
		// Match patterns like "Doors: 7pm", "Doors 7:00 PM", "doors 19:00", "Start: 8pm", "Show: 8:30pm", "End: 11pm"
		$patterns = array(
			'doors_time' => '/\bdoors?\s*:?\s*(\d{1,2}(?::\d{2})?\s*(?:am|pm)?|\d{1,2}:\d{2})/i',
			'start_time' => '/\b(?:start|show|curtain)\s*:?\s*(\d{1,2}(?::\d{2})?\s*(?:am|pm)?|\d{1,2}:\d{2})/i',
			'end_time'   => '/\bend\s*:?\s*(\d{1,2}(?::\d{2})?\s*(?:am|pm)?|\d{1,2}:\d{2})/i',
		);
		foreach ( $patterns as $key => $regex ) {
			if ( preg_match( $regex, $info, $m ) ) {
				$t = $this->normalize_time_to_his( trim( $m[1] ) );
				if ( $t !== '' ) {
					$set_times[ $key ] = $t;
				}
			}
		}
		return $set_times;
	}

	/**
	 * Convert a time string to H:i:s for ACF time_picker (return_format H:i:s)
	 *
	 * @param string $time Time in various formats (e.g. "19:00", "7:00 PM", "19:00:00")
	 * @return string H:i:s or empty string if unparseable
	 */
	private function normalize_time_to_his( $time ) {
		$time = trim( (string) $time );
		if ( $time === '' ) {
			return '';
		}
		// Already H:i or H:i:s
		if ( preg_match( '/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $time, $m ) ) {
			$h = (int) $m[1];
			$i = (int) $m[2];
			$s = isset( $m[3] ) ? (int) $m[3] : 0;
			if ( $h >= 0 && $h <= 23 && $i >= 0 && $i <= 59 && $s >= 0 && $s <= 59 ) {
				return sprintf( '%02d:%02d:%02d', $h, $i, $s );
			}
		}
		// Try strtotime for "7:00 PM", "19:00", etc. (relative to a fixed date to avoid DST issues)
		$ts = strtotime( '2000-01-01 ' . $time );
		if ( $ts !== false ) {
			return date( 'H:i:s', $ts );
		}
		return '';
	}

	/**
	 * Parse setlist from API data
	 *
	 * @param array $sets Array of set objects from API
	 * @return array Formatted setlist for ACF repeater
	 */
	private function parse_setlist( $sets ) {
		$setlist = array();

		if ( ! is_array( $sets ) ) {
			return $setlist;
		}

		foreach ( $sets as $set ) {
			// Add set name as a note if it's an encore or has a name
			if ( ! empty( $set['name'] ) || ( isset( $set['encore'] ) && $set['encore'] > 0 ) ) {
				$set_name = ! empty( $set['name'] ) ? $set['name'] : 'Encore ' . $set['encore'];
				$setlist[] = array(
					'entry_type' => 'note',
					'notes'      => $set_name,
				);
			}

			// Process songs in the set
			if ( ! empty( $set['song'] ) && is_array( $set['song'] ) ) {
				foreach ( $set['song'] as $song ) {
					$entry = array(
						'entry_type' => 'song-post',
						'notes'      => '',
					);

					// Check if it's a cover
					$is_cover = ! empty( $song['cover'] ) && ! empty( $song['cover']['name'] );
					$original_artist = $is_cover ? $song['cover']['name'] : '';

					// Try to match to existing song (even if it's a cover)
					$matched_song = $this->match_song_to_database( $song['name'] );
					
					if ( $matched_song ) {
						// Found a match - use song-post relationship
						$entry['song'] = $matched_song;
						
						// If it's a cover, add original artist to notes if not already in song post
						if ( $is_cover ) {
							// Check if the song post already has attribution
							$attribution = get_field( 'attribution', $matched_song );
							if ( empty( $attribution ) ) {
								// Add cover artist to notes
								$entry['notes'] = 'Cover: ' . $original_artist;
							}
						}
					} else {
						// No match found - use song-text
						$entry['entry_type'] = 'song-text';
						$entry['song_text'] = $song['name'];
						
						// If it's a cover, add original artist to notes
						if ( $is_cover ) {
							$entry['notes'] = 'Cover: ' . $original_artist;
						}
					}

					// Add song info/notes if provided
					if ( ! empty( $song['info'] ) ) {
						$existing_notes = $entry['notes'];
						$entry['notes'] = $existing_notes ? $existing_notes . ' - ' . $song['info'] : $song['info'];
					}

					$setlist[] = $entry;
				}
			}
		}

		return $setlist;
	}

	/**
	 * Match song name to existing song CPT
	 *
	 * @param string $song_name Song name to match
	 * @return int|false Song post ID or false if no match
	 */
	public function match_song_to_database( $song_name ) {
		// Exact match first
		$exact_match = get_page_by_path( sanitize_title( $song_name ), OBJECT, 'song' );
		if ( $exact_match ) {
			return $exact_match->ID;
		}

		// Search by title
		$posts = get_posts( array(
			'post_type'      => 'song',
			'posts_per_page' => 1,
			'title'          => $song_name,
			'post_status'    => 'publish',
		) );

		if ( ! empty( $posts ) ) {
			return $posts[0]->ID;
		}

		// Fuzzy match using title search
		$fuzzy_posts = get_posts( array(
			'post_type'      => 'song',
			'posts_per_page' => 10,
			's'              => $song_name,
			'post_status'    => 'publish',
		) );

		if ( empty( $fuzzy_posts ) ) {
			return false;
		}

		// Calculate similarity for each result
		$best_match = null;
		$best_score = 0;
		$song_name_lower = mb_strtolower( $song_name, 'UTF-8' );

		foreach ( $fuzzy_posts as $post ) {
			$post_title_lower = mb_strtolower( get_the_title( $post->ID ), 'UTF-8' );
			$similarity = $this->calculate_similarity( $song_name_lower, $post_title_lower );
			
			if ( $similarity > $best_score && $similarity > 0.8 ) { // 80% similarity threshold
				$best_score = $similarity;
				$best_match = $post->ID;
			}
		}

		return $best_match ? $best_match : false;
	}

	/**
	 * Generate show title and slug from location and date
	 * 
	 * @param int $location_id Location taxonomy term ID
	 * @param string $post_date Post date in Y-m-d H:i:s format
	 * @param int $exclude_id Post ID to exclude from slug uniqueness check
	 * @return array Array with 'title' and 'slug' keys
	 */
	private function generate_show_title_and_slug( $location_id, $post_date, $exclude_id = 0 ) {
		// Get city name from location hierarchy (same logic as jww_auto_generate_show_title)
		$location_term = get_term( $location_id, 'location' );
		$city_name = '';
		
		if ( $location_term && ! is_wp_error( $location_term ) ) {
			$current_term = $location_term;
			
			// Walk up the hierarchy to find the city
			if ( $current_term->parent ) {
				$parent_term = get_term( $current_term->parent, 'location' );
				if ( $parent_term && ! is_wp_error( $parent_term ) ) {
					if ( $parent_term->parent ) {
						// Parent has a parent, so hierarchy is: grandparent (country) > parent (city) > current (venue)
						$city_name = $parent_term->name;
					} else {
						// Parent has no parent - check if parent has multiple children
						$siblings = get_terms( array(
							'taxonomy' => 'location',
							'parent'   => $parent_term->term_id,
							'hide_empty' => false,
						) );
						if ( ! empty( $siblings ) && ! is_wp_error( $siblings ) && count( $siblings ) > 1 ) {
							// Parent has multiple children, so parent is country, current is city
							$city_name = $current_term->name;
						} else {
							// Parent is likely city
							$city_name = $parent_term->name;
						}
					}
				}
			} else {
				// Current term has no parent - check if it has children
				$children = get_terms( array(
					'taxonomy' => 'location',
					'parent'   => $current_term->term_id,
					'hide_empty' => false,
				) );
				if ( ! empty( $children ) && ! is_wp_error( $children ) ) {
					// Has children - this is a country, use first child as city
					$city_name = $children[0]->name;
				} else {
					// No children - might be a city itself
					$city_name = $current_term->name;
				}
			}
		}
		
		// Fallback: use location term name if we still don't have a city
		if ( empty( $city_name ) && $location_term && ! is_wp_error( $location_term ) ) {
			$city_name = $location_term->name;
		}
		
		// Format date as "Month DD, YYYY"
		$formatted_date = date_i18n( 'F j, Y', strtotime( $post_date ) );
		
		// Generate title
		$title = $city_name . ' - ' . $formatted_date;
		
		// Generate slug from title
		$slug = sanitize_title( $title );
		
		// Ensure slug is unique
		$original_slug = $slug;
		$counter = 1;
		while ( $this->slug_exists( $slug, $exclude_id ) ) {
			$slug = $original_slug . '-' . $counter;
			$counter++;
		}
		
		return array(
			'title' => $title,
			'slug'  => $slug,
		);
	}

	/**
	 * Check if a slug already exists for show posts
	 * 
	 * @param string $slug Post slug to check
	 * @param int $exclude_id Post ID to exclude from check (for updates)
	 * @return bool True if slug exists
	 */
	private function slug_exists( $slug, $exclude_id = 0 ) {
		$args = array(
			'post_type'      => 'show',
			'name'           => $slug,
			'posts_per_page' => 1,
			'post__not_in'  => $exclude_id ? array( $exclude_id ) : array(),
		);
		
		$query = new WP_Query( $args );
		return $query->have_posts();
	}

	/**
	 * Calculate string similarity using Levenshtein distance
	 *
	 * @param string $str1 First string
	 * @param string $str2 Second string
	 * @return float Similarity score (0-1)
	 */
	private function calculate_similarity( $str1, $str2 ) {
		$str1 = trim( $str1 );
		$str2 = trim( $str2 );

		if ( $str1 === $str2 ) {
			return 1.0;
		}

		$max_len = max( mb_strlen( $str1, 'UTF-8' ), mb_strlen( $str2, 'UTF-8' ) );
		if ( $max_len === 0 ) {
			return 1.0;
		}

		$distance = levenshtein( $str1, $str2 );
		return 1 - ( $distance / $max_len );
	}

	/**
	 * Create or get location taxonomy term
	 *
	 * @param array $venue_data Venue data from API
	 * @return int|WP_Error Term ID or error
	 */
	private function create_location_term( $venue_data ) {
		if ( empty( $venue_data['name'] ) ) {
			return new WP_Error( 'missing_venue', 'Venue name is required' );
		}

		$venue_name = $venue_data['name'];
		$city_data = $venue_data['city'] ?? array();
		$city_name = $city_data['name'] ?? '';
		$country_data = $city_data['country'] ?? array();
		$country_name = $country_data['name'] ?? '';
		$country_code = $country_data['code'] ?? '';
		$state_name   = $city_data['state'] ?? '';
		$state_code   = $city_data['stateCode'] ?? '';

		// Create country term first (if provided)
		$country_id = 0;
		if ( $country_name ) {
			$country_term = term_exists( $country_name, 'location' );
			if ( ! $country_term ) {
				$country_term = wp_insert_term( $country_name, 'location' );
				if ( is_wp_error( $country_term ) ) {
					return $country_term;
				}
				$country_id = $country_term['term_id'];
			} else {
				$country_id = is_array( $country_term ) ? $country_term['term_id'] : $country_term;
			}
		}

		// For countries that use state/province level (from CMS or fallback list), use state when API provides it
		$parent_for_city = $country_id;
		$codes_with_state = $this->get_countries_with_state_level_codes();
		$use_state_level = $country_id
			&& in_array( strtoupper( (string) $country_code ), $codes_with_state, true )
			&& ( $state_name !== '' || $state_code !== '' );

		if ( $use_state_level ) {
			$state_label = $state_name !== '' ? $state_name : $state_code;
			$state_term  = term_exists( $state_label, 'location', $country_id );
			if ( ! $state_term ) {
				$state_term = wp_insert_term( $state_label, 'location', array( 'parent' => $country_id ) );
				if ( is_wp_error( $state_term ) ) {
					$parent_for_city = $country_id;
				} else {
					$parent_for_city = $state_term['term_id'];
					update_term_meta( $parent_for_city, 'location_type', 'state_province' );
					if ( function_exists( 'update_field' ) ) {
						update_field( 'location_type', 'state_province', 'location_' . $parent_for_city );
					}
				}
			} else {
				$parent_for_city = is_array( $state_term ) ? $state_term['term_id'] : $state_term;
			}
		}

		// Create city term (if provided); parent is state when available, else country
		$city_id = $parent_for_city;
		if ( $city_name && $parent_for_city ) {
			$city_term = term_exists( $city_name, 'location', $parent_for_city );
			if ( ! $city_term ) {
				$city_term = wp_insert_term( $city_name, 'location', array( 'parent' => $parent_for_city ) );
				if ( is_wp_error( $city_term ) ) {
					$city_id = $parent_for_city;
				} else {
					$city_id = $city_term['term_id'];
				}
			} else {
				$city_id = is_array( $city_term ) ? $city_term['term_id'] : $city_term;
			}
		}

		// Create venue term
		$parent_id = $city_id ? $city_id : $country_id;
		$venue_term = term_exists( $venue_name, 'location', $parent_id );
		if ( ! $venue_term ) {
			$venue_term = wp_insert_term( $venue_name, 'location', array( 'parent' => $parent_id ) );
			if ( is_wp_error( $venue_term ) ) {
				return $venue_term;
			}
			$venue_id = $venue_term['term_id'];
		} else {
			$venue_id = is_array( $venue_term ) ? $venue_term['term_id'] : $venue_term;
		}

		// Set venue address if provided
		if ( ! empty( $venue_data['address'] ) ) {
			update_field( 'address', $venue_data['address'], 'location_' . $venue_id );
		}

		return $venue_id;
	}

	/**
	 * Create or get tour taxonomy term
	 *
	 * @param string $tour_name Tour name
	 * @return int|WP_Error Term ID or error
	 */
	private function create_tour_term( $tour_name ) {
		if ( empty( $tour_name ) ) {
			return new WP_Error( 'missing_tour', 'Tour name is required' );
		}

		$tour_term = term_exists( $tour_name, 'tour' );
		if ( ! $tour_term ) {
			$tour_term = wp_insert_term( $tour_name, 'tour' );
			if ( is_wp_error( $tour_term ) ) {
				return $tour_term;
			}
			return $tour_term['term_id'];
		}

		return is_array( $tour_term ) ? $tour_term['term_id'] : $tour_term;
	}

	/**
	 * Fetch a single setlist from setlist.fm API by ID (for lastUpdated check only).
	 *
	 * @param string $setlist_id Setlist ID (hex string from URL).
	 * @return array|WP_Error Array with 'lastUpdated' and 'body' (full decoded JSON), or WP_Error on failure.
	 */
	public function fetch_setlist_from_api( $setlist_id ) {
		$api_key = $this->get_api_key();
		if ( ! $api_key ) {
			return new WP_Error( 'no_api_key', 'setlist.fm API key is required' );
		}
		$url = self::API_BASE_URL . '/setlist/' . sanitize_text_field( $setlist_id );
		$response = wp_remote_get( $url, array(
			'headers' => array(
				'Accept'        => 'application/json',
				'x-api-key'     => $api_key,
			),
			'timeout' => 15,
		) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = wp_remote_retrieve_response_code( $response );
		$body  = wp_remote_retrieve_body( $response );
		if ( $code !== 200 ) {
			return new WP_Error( 'api_error', 'setlist.fm API returned ' . $code, array( 'status' => $code ) );
		}
		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'api_parse', 'Invalid JSON from setlist.fm API' );
		}
		return array(
			'lastUpdated' => isset( $data['lastUpdated'] ) ? $data['lastUpdated'] : '',
			'body'        => $data,
		);
	}

	/**
	 * Check if a show's setlist has been updated on setlist.fm since we last synced.
	 *
	 * @param int $show_id Show post ID.
	 * @return array|WP_Error Array with keys: has_changes (bool), show_id, title, api_last_updated, our_last_updated; or WP_Error.
	 */
	public function check_show_has_setlist_changes( $show_id ) {
		$show = get_post( $show_id );
		if ( ! $show || $show->post_type !== 'show' ) {
			return new WP_Error( 'invalid_show', 'Invalid show ID' );
		}
		$url = get_field( 'setlist_fm_url', $show_id );
		if ( ! $url || strpos( $url, 'setlist.fm' ) === false ) {
			return array(
				'has_changes'       => false,
				'show_id'           => (int) $show_id,
				'title'             => $show->post_title,
				'api_last_updated'  => '',
				'our_last_updated'  => get_post_meta( $show_id, '_setlist_fm_api_last_updated', true ),
			);
		}
		$setlist_id = null;
		if ( preg_match( '/-([a-f0-9]+)\.html$/i', $url, $matches ) ) {
			$setlist_id = $matches[1];
		}
		if ( ! $setlist_id ) {
			return new WP_Error( 'invalid_url', 'Could not extract setlist ID from URL' );
		}
		$fetched = $this->fetch_setlist_from_api( $setlist_id );
		if ( is_wp_error( $fetched ) ) {
			return $fetched;
		}
		$api_last = isset( $fetched['lastUpdated'] ) ? $fetched['lastUpdated'] : '';
		$our_last = get_post_meta( $show_id, '_setlist_fm_api_last_updated', true );
		// If we never stored lastUpdated, consider it stale so we offer resync
		if ( empty( $our_last ) ) {
			$has_changes = ! empty( $api_last );
		} else {
			$has_changes = ! empty( $api_last ) && $api_last !== $our_last;
		}
		return array(
			'has_changes'       => $has_changes,
			'show_id'           => (int) $show_id,
			'title'             => $show->post_title,
			'api_last_updated'  => $api_last,
			'our_last_updated'  => $our_last,
		);
	}

	/**
	 * Find existing show by setlist.fm URL or setlist ID
	 * 
	 * Handles both /upcoming/ and /setlist/ URL formats by extracting and matching the setlist ID.
	 * This ensures sync works even if the URL changes from upcoming to setlist after the show date.
	 *
	 * @param string $url setlist.fm URL
	 * @return int|false Show post ID or false
	 */
	private function find_existing_show_by_url( $url ) {
		// First try exact URL match (for backward compatibility)
		$posts = get_posts( array(
			'post_type'      => 'show',
			'posts_per_page' => 1,
			'post_status'    => array( 'publish', 'future', 'draft' ),
			'meta_query'     => array(
				array(
					'key'   => 'setlist_fm_url',
					'value' => $url,
				),
			),
		) );

		if ( ! empty( $posts ) ) {
			return $posts[0]->ID;
		}

		// If exact match fails, extract setlist ID and match by ID
		// This handles cases where URL changed from /upcoming/ to /setlist/
		$setlist_id = null;
		if ( preg_match( '/-([a-f0-9]+)\.html$/', $url, $matches ) ) {
			$setlist_id = $matches[1];
		}

		if ( $setlist_id ) {
			// Get all shows with setlist_fm_url field
			$all_shows = get_posts( array(
				'post_type'      => 'show',
				'posts_per_page' => -1,
				'post_status'    => array( 'publish', 'future', 'draft' ),
				'meta_query'     => array(
					array(
						'key'     => 'setlist_fm_url',
						'compare' => 'EXISTS',
					),
				),
			) );

			// Check each show's URL for matching setlist ID
			foreach ( $all_shows as $show ) {
				$stored_url = get_field( 'setlist_fm_url', $show->ID );
				if ( $stored_url && preg_match( '/-([a-f0-9]+)\.html$/', $stored_url, $stored_matches ) ) {
					if ( $stored_matches[1] === $setlist_id ) {
						return $show->ID;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Find existing show by date and venue
	 *
	 * @param string $date Show date (MySQL format)
	 * @param string $venue_name Venue name
	 * @return int|false Show post ID or false
	 */
	private function find_existing_show( $date, $venue_name ) {
		$date_start = date( 'Y-m-d 00:00:00', strtotime( $date ) );
		$date_end = date( 'Y-m-d 23:59:59', strtotime( $date ) );

		$posts = get_posts( array(
			'post_type'      => 'show',
			'posts_per_page' => 1,
			'post_status'    => array( 'publish', 'future', 'draft' ),
			'date_query'     => array(
				array(
					'after'     => $date_start,
					'before'    => $date_end,
					'inclusive' => true,
				),
			),
		) );

		if ( empty( $posts ) ) {
			return false;
		}

		// Check if venue matches
		foreach ( $posts as $post ) {
			$location_id = get_field( 'show_location', $post->ID );
			if ( $location_id ) {
				$location_term = get_term( $location_id, 'location' );
				if ( $location_term && ! is_wp_error( $location_term ) ) {
					if ( mb_strtolower( $location_term->name, 'UTF-8' ) === mb_strtolower( $venue_name, 'UTF-8' ) ) {
						return $post->ID;
					}
				}
			}
		}

		return false;
	}
}
