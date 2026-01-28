<?php
/**
 * Search-Related Functions and Filters
 * 
 * @package JWW_Theme
 * @subpackage Includes
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Include custom post types (bands, albums, songs, shows) in WordPress search results
 * 
 * Modifies the main search query to include band, album, song, and show post types.
 * This ensures that bands, albums, songs (including their lyrics/content), and shows
 * appear in search results.
 */
function jww_include_custom_post_types_in_search( $query ) {
	// Only modify the main search query on the frontend
	if ( ! is_admin() && $query->is_main_query() && $query->is_search() ) {
		// Get the current post types being searched
		$post_types = $query->get( 'post_type' );
		
		// If post_type is not set or is a string, convert to array
		if ( empty( $post_types ) ) {
			$post_types = array( 'post', 'page' );
		} elseif ( is_string( $post_types ) ) {
			$post_types = array( $post_types );
		}
		
		// Add our custom post types to the search
		$custom_post_types = array( 'band', 'album', 'song', 'show' );
		$post_types = array_merge( $post_types, $custom_post_types );
		
		// Remove duplicates and ensure we have valid post types
		$post_types = array_unique( $post_types );
		
		// Exclude attachments and revisions from search results
		$post_types = array_diff( $post_types, array( 'attachment', 'acf-field', 'acf-field-group', 'acf-post-type', 'revision' ) );
		
		// Set the modified post types
		$query->set( 'post_type', $post_types );
		
		// Explicitly exclude revisions by post status
		// Revisions have post_status 'inherit', so we need to exclude them
		$post_status = $query->get( 'post_status' );
		if ( empty( $post_status ) ) {
			$post_status = array( 'publish' );
		} elseif ( is_string( $post_status ) ) {
			$post_status = array( $post_status );
		}
		// Ensure 'inherit' (revisions) is not included
		if ( in_array( 'inherit', $post_status, true ) ) {
			$post_status = array_diff( $post_status, array( 'inherit' ) );
		}
		// Ensure we have at least 'publish' status
		if ( empty( $post_status ) ) {
			$post_status = array( 'publish' );
		}
		$query->set( 'post_status', $post_status );
		
		// Set posts per page for search results (default is 10)
		$posts_per_page = apply_filters( 'jww_search_posts_per_page', 50 );
		$query->set( 'posts_per_page', $posts_per_page );
	}
}
add_action( 'pre_get_posts', 'jww_include_custom_post_types_in_search' );

/**
 * Sort search results by post type priority
 * 
 * Sorts results in the order: song, album, band, show, post, page
 * 
 * @param array    $posts Array of post objects
 * @param WP_Query $query The WordPress query object
 * @return array Sorted array of post objects
 */
function jww_sort_search_results_by_post_type( $posts, $query ) {
	// Only sort search queries on the frontend
	if ( ! is_admin() && $query->is_search() && $query->is_main_query() ) {
		// Define post type priority order
		$post_type_order = array(
			'song'  => 1,
			'album' => 2,
			'band'  => 3,
			'show'  => 4,
			'post'  => 5,
			'page'  => 6,
		);
		
		// Sort posts by post type priority
		usort( $posts, function( $a, $b ) use ( $post_type_order ) {
			$a_priority = isset( $post_type_order[ $a->post_type ] ) ? $post_type_order[ $a->post_type ] : 999;
			$b_priority = isset( $post_type_order[ $b->post_type ] ) ? $post_type_order[ $b->post_type ] : 999;
			
			// If same priority, maintain original order (by date, newest first)
			if ( $a_priority === $b_priority ) {
				return strtotime( $b->post_date ) - strtotime( $a->post_date );
			}
			
			return $a_priority - $b_priority;
		} );
		
		// Re-index array
		$posts = array_values( $posts );
	}
	
	return $posts;
}
add_filter( 'posts_results', 'jww_sort_search_results_by_post_type', 10, 2 );

/**
 * Filter search results to remove unwanted post types
 * 
 * Final safety net to remove revisions and ACF post types
 * that might slip through from any source.
 * 
 * @param array    $posts Array of post objects
 * @param WP_Query $query The WordPress query object
 * @return array Filtered array of post objects
 */
function jww_filter_unwanted_post_types_from_search( $posts, $query ) {
	// Only filter search queries on the frontend
	if ( ! is_admin() && $query->is_search() && $query->is_main_query() ) {
		// Remove unwanted post types
		$excluded_types = array( 'attachment', 'acf-field', 'acf-field-group', 'acf-post-type', 'revision', 'ap_outbox' );
		$posts = array_filter( $posts, function( $post ) use ( $excluded_types ) {
			return ! in_array( $post->post_type, $excluded_types, true );
		} );
		
		// Also filter by post_status to exclude revisions
		$posts = array_filter( $posts, function( $post ) {
			return $post->post_status !== 'inherit';
		} );
		
		// Re-index array
		$posts = array_values( $posts );
	}
	return $posts;
}
add_filter( 'posts_results', 'jww_filter_unwanted_post_types_from_search', 5, 2 );

/**
 * Include ACF fields (lyrics and lyric_annotations) in WordPress search
 * 
 * Modifies the search query to search within ACF custom fields for songs.
 * This allows searching song lyrics and lyric annotations in addition to
 * the standard post title, content, and excerpt.
 */
function jww_search_acf_fields( $search, $wp_query ) {
	// Only modify the main search query on the frontend
	if ( ! is_admin() && $wp_query->is_main_query() && $wp_query->is_search() && ! empty( $wp_query->get( 's' ) ) ) {
		global $wpdb;
		
		// Get the search term
		$search_term = $wp_query->get( 's' );
		
		// Escape the search term for SQL
		$search_term = $wpdb->esc_like( $search_term );
		$search_term = '%' . $search_term . '%';
		
		// Get the post types being searched
		$post_types = $wp_query->get( 'post_type' );
		if ( empty( $post_types ) ) {
			$post_types = array( 'post', 'page' );
		} elseif ( is_string( $post_types ) ) {
			$post_types = array( $post_types );
		}
		
		// Only add ACF field search if 'song' post type is included
		if ( in_array( 'song', $post_types, true ) ) {
			// Add OR condition to search in ACF meta fields
			$search .= $wpdb->prepare(
				" OR (
					EXISTS (
						SELECT 1 FROM {$wpdb->postmeta}
						WHERE {$wpdb->postmeta}.post_id = {$wpdb->posts}.ID
						AND (
							({$wpdb->postmeta}.meta_key = 'lyrics' AND {$wpdb->postmeta}.meta_value LIKE %s)
							OR ({$wpdb->postmeta}.meta_key = 'lyric_annotations' AND {$wpdb->postmeta}.meta_value LIKE %s)
						)
					)
				)",
				$search_term,
				$search_term
			);
		}
	}
	
	return $search;
}
add_filter( 'posts_search', 'jww_search_acf_fields', 10, 2 );

/**
 * Extract snippet around search term match
 * 
 * Finds the first occurrence of any search term and extracts
 * a snippet of text around it.
 * 
 * @param string $content The full content to search
 * @param array  $search_terms Array of search terms
 * @param int    $snippet_length Characters before/after match
 * @return string|false Snippet text or false if no match
 */
function jww_extract_snippet_around_match( $content, $search_terms, $snippet_length = 150 ) {
	if ( empty( $content ) || empty( $search_terms ) ) {
		return false;
	}
	
	$content_lower = mb_strtolower( $content, 'UTF-8' );
	$best_position = -1;
	$best_term = '';
	
	// Find the first occurrence of any search term
	foreach ( $search_terms as $term ) {
		$term_trimmed = trim( $term );
		if ( strlen( $term_trimmed ) > 2 ) {
			$term_lower = mb_strtolower( $term_trimmed, 'UTF-8' );
			$position = mb_strpos( $content_lower, $term_lower );
			if ( $position !== false && ( $best_position === -1 || $position < $best_position ) ) {
				$best_position = $position;
				$best_term = $term_trimmed;
			}
		}
	}
	
	if ( $best_position === -1 ) {
		return false;
	}
	
	// Extract snippet around the match
	$start = max( 0, $best_position - $snippet_length );
	$end = min( mb_strlen( $content ), $best_position + mb_strlen( $best_term ) + $snippet_length );
	
	$snippet = mb_substr( $content, $start, $end - $start, 'UTF-8' );
	
	// Add ellipsis if not at start/end
	if ( $start > 0 ) {
		$snippet = '...' . $snippet;
	}
	if ( $end < mb_strlen( $content ) ) {
		$snippet = $snippet . '...';
	}
	
	return $snippet;
}

/**
 * Highlight search terms in text by extending to full words
 * 
 * Finds search terms in text and highlights the full word
 * containing each match. Works entirely in plain text first,
 * then applies all highlights at once to avoid position shifting.
 * 
 * @param string $text The text to highlight (should be plain text)
 * @param array  $search_terms Array of search terms
 * @return string Text with highlighted words
 */
function jww_highlight_search_terms_in_text( $text, $search_terms ) {
	if ( empty( $text ) || empty( $search_terms ) ) {
		return $text;
	}
	
	// Strip any existing HTML to work with plain text
	$plain_text = wp_strip_all_tags( $text );
	
	// Sort terms by length (longest first) to avoid partial matches within already highlighted text
	usort( $search_terms, function( $a, $b ) {
		return strlen( trim( $b ) ) - strlen( trim( $a ) );
	} );
	
	// Collect all words to highlight (with their positions) before making any replacements
	$words_to_highlight = array();
	$highlighted_ranges = array(); // Track ranges to avoid overlapping highlights
	
	foreach ( $search_terms as $term ) {
		$term_trimmed = trim( $term );
		if ( strlen( $term_trimmed ) < 3 ) {
			continue;
		}
		
		// Find all matches of this term in plain text
		$pattern = '/' . preg_quote( $term_trimmed, '/' ) . '/iu';
		$matches = array();
		preg_match_all( $pattern, $plain_text, $matches, PREG_OFFSET_CAPTURE );
		
		if ( empty( $matches[0] ) ) {
			continue;
		}
		
		foreach ( $matches[0] as $match ) {
			$match_text = $match[0];
			$match_pos = $match[1];
			
			// Check if this position is already within a highlighted range
			$already_highlighted = false;
			foreach ( $highlighted_ranges as $range ) {
				if ( $match_pos >= $range['start'] && $match_pos < $range['end'] ) {
					$already_highlighted = true;
					break;
				}
			}
			
			if ( $already_highlighted ) {
				continue;
			}
			
			// Find word boundaries in plain text
			$word_start = $match_pos;
			while ( $word_start > 0 ) {
				$char = mb_substr( $plain_text, $word_start - 1, 1, 'UTF-8' );
				if ( ! preg_match( '/[\w\']/', $char ) ) {
					break;
				}
				$word_start--;
			}
			
			$match_end = $match_pos + mb_strlen( $match_text, 'UTF-8' );
			$word_end = $match_end;
			while ( $word_end < mb_strlen( $plain_text, 'UTF-8' ) ) {
				$char = mb_substr( $plain_text, $word_end, 1, 'UTF-8' );
				if ( ! preg_match( '/[\w\']/', $char ) ) {
					break;
				}
				$word_end++;
			}
			
			// Extract the full word
			$full_word = mb_substr( $plain_text, $word_start, $word_end - $word_start, 'UTF-8' );
			
			if ( empty( $full_word ) ) {
				continue;
			}
			
			// Store this word to highlight (process from end to start)
			$words_to_highlight[] = array(
				'word' => $full_word,
				'start' => $word_start,
				'end' => $word_end
			);
			
			// Track this highlighted range
			$highlighted_ranges[] = array(
				'start' => $word_start,
				'end' => $word_end
			);
		}
	}
	
	// Sort words to highlight by position (end to start) to avoid position shifting
	usort( $words_to_highlight, function( $a, $b ) {
		return $b['start'] - $a['start'];
	} );
	
	// Apply all highlights from end to start
	foreach ( $words_to_highlight as $word_data ) {
		$before = mb_substr( $plain_text, 0, $word_data['start'], 'UTF-8' );
		$after = mb_substr( $plain_text, $word_data['end'], null, 'UTF-8' );
		$plain_text = $before . '<span class="search-term-highlight">' . $word_data['word'] . '</span>' . $after;
	}
	
	return $plain_text;
}

/**
 * Generate a search snippet with highlighted search terms
 * 
 * Creates a contextual snippet from post content or lyrics that contains
 * the search terms, with the terms highlighted.
 * 
 * @param int|null $post_id Optional. Post ID. Defaults to current post.
 * @return string HTML snippet with highlighted search terms
 */
function jww_get_search_snippet( $post_id = null ) {

	if ( ! $post_id ) {
		return '';
	}
	
	// Get the search query - try multiple methods
	$search_query = get_search_query();
	if ( empty( $search_query ) ) {
		// Fallback to GET parameter
		$search_query = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
	}
	
	if ( empty( $search_query ) ) {
		// If no search query, return regular excerpt
		return get_the_excerpt( $post_id );
	}
	
	// Get post content based on post type and where the search match is
	$content = '';
	$post_type = get_post_type( $post_id );
	
	// Get search terms for matching
	$search_terms_for_matching = explode( ' ', trim( $search_query ) );
	$search_terms_for_matching = array_filter( $search_terms_for_matching, function( $term ) {
		return strlen( trim( $term ) ) > 2;
	} );
	
	if ( 'song' === $post_type ) {
		// For songs, check multiple fields in priority order
		// Priority 1: Lyrics field
		$lyrics = get_field( 'lyrics', $post_id );
		if ( ! empty( $lyrics ) ) {
			$lyrics_text = wp_strip_all_tags( $lyrics );
			$lyrics_lower = mb_strtolower( $lyrics_text, 'UTF-8' );
			
			// Check if any search term matches in lyrics
			$found_in_lyrics = false;
			foreach ( $search_terms_for_matching as $term ) {
				if ( mb_strpos( $lyrics_lower, mb_strtolower( trim( $term ), 'UTF-8' ) ) !== false ) {
					$found_in_lyrics = true;
					break;
				}
			}
			
			if ( $found_in_lyrics ) {
				$content = $lyrics_text;
			}
		}
		
		// Priority 2: Post content (if not found in lyrics)
		if ( empty( $content ) ) {
			$post_obj = get_post( $post_id );
			if ( $post_obj && ! empty( $post_obj->post_content ) ) {
				$post_content = $post_obj->post_content;
				
				// Process blocks if this is block content
				if ( has_blocks( $post_content ) ) {
					$post_content = do_blocks( $post_content );
				}
				
				// Strip all HTML tags and normalize
				$post_content = wp_strip_all_tags( $post_content );
				$post_content = preg_replace( '/<!--.*?-->/s', '', $post_content );
				$post_content = preg_replace( '/\s+/', ' ', $post_content );
				$post_content = trim( $post_content );
				
				$post_content_lower = mb_strtolower( $post_content, 'UTF-8' );
				
				// Check if any search term matches in post content
				$found_in_content = false;
				foreach ( $search_terms_for_matching as $term ) {
					if ( mb_strpos( $post_content_lower, mb_strtolower( trim( $term ), 'UTF-8' ) ) !== false ) {
						$found_in_content = true;
						break;
					}
				}
				
				if ( $found_in_content ) {
					$content = $post_content;
				}
			}
		}
		
		// Priority 3: Lyric annotations (if not found in lyrics or content)
		if ( empty( $content ) ) {
			$lyric_annotations = get_field( 'lyric_annotations', $post_id );
			if ( ! empty( $lyric_annotations ) ) {
				$annotations_text = wp_strip_all_tags( $lyric_annotations );
				$annotations_lower = mb_strtolower( $annotations_text, 'UTF-8' );
				
				// Check if any search term matches in annotations
				$found_in_annotations = false;
				foreach ( $search_terms_for_matching as $term ) {
					if ( mb_strpos( $annotations_lower, mb_strtolower( trim( $term ), 'UTF-8' ) ) !== false ) {
						$found_in_annotations = true;
						break;
					}
				}
				
				if ( $found_in_annotations ) {
					$content = $annotations_text;
				}
			}
		}
		
		// Fallback: if no match found in any field, use lyrics if available
		if ( empty( $content ) && ! empty( $lyrics ) ) {
			$content = wp_strip_all_tags( $lyrics );
		}
	}
	
	// For non-song post types, use post content
	if ( empty( $content ) ) {
		$post_obj = get_post( $post_id );
		if ( $post_obj ) {
			// For block themes, process blocks first to get clean content
			$post_content = $post_obj->post_content;
			
			// Process blocks if this is block content
			if ( has_blocks( $post_content ) ) {
				$post_content = do_blocks( $post_content );
			}
			
			// Strip all HTML tags and normalize whitespace
			$content = wp_strip_all_tags( $post_content );
			
			// Remove any remaining HTML comments or block markup artifacts
			$content = preg_replace( '/<!--.*?-->/s', '', $content );
			
			// Normalize whitespace (multiple spaces/newlines to single space)
			$content = preg_replace( '/\s+/', ' ', $content );
			$content = trim( $content );
		}
	}
	
	if ( empty( $content ) ) {
		// If still no content, return regular excerpt
		return get_the_excerpt( $post_id );
	}
	
	// Split search query into individual terms
	$search_terms = explode( ' ', trim( $search_query ) );
	$search_terms = array_filter( $search_terms, function( $term ) {
		return strlen( trim( $term ) ) > 2; // Ignore very short terms
	} );
	
	if ( empty( $search_terms ) ) {
		return wp_trim_words( $content, 55, '...' );
	}
	
	// Extract snippet around the first match
	$snippet = jww_extract_snippet_around_match( $content, $search_terms, 150 );
	
	// If no snippet found, return trimmed content
	if ( $snippet === false ) {
		return wp_trim_words( $content, 55, '...' );
	}
	
	// Highlight search terms in the snippet
	$snippet = jww_highlight_search_terms_in_text( $snippet, $search_terms );
	
	// Return the snippet - span with style attribute should be preserved
	$allowed = wp_kses_allowed_html( 'post' );
	$allowed['span']['class'] = true;
	
	return wp_kses( $snippet, $allowed );
}

/**
 * Allow span with style attribute in shortcode output
 * 
 * Ensures span tags with style attributes are preserved when shortcodes are processed
 * 
 * @param array $tags Allowed HTML tags
 * @return array Modified allowed tags
 */
function jww_allow_span_style_in_shortcodes( $tags ) {
	if ( ! isset( $tags['span'] ) ) {
		$tags['span'] = array();
	}
	$tags['span']['style'] = true;
	return $tags;
}
add_filter( 'wp_kses_allowed_html', 'jww_allow_span_style_in_shortcodes', 10, 1 );

/**
 * Shortcode for displaying search snippet with highlighted terms
 * Usage: [search_snippet] or [search_snippet post_id="123"]
 * 
 * @param array $atts Shortcode attributes
 * @return string HTML output for search snippet
 */
function jww_search_snippet_shortcode( $atts = array() ) {
	// Parse attributes
	$atts = shortcode_atts( array(
		'post_id' => null,
	), $atts, 'search_snippet' );
	
	// Get post ID from attribute or current context
	$post_id = ! empty( $atts['post_id'] ) ? intval( $atts['post_id'] ) : null;
	
	// If no post_id provided, get it from current context
	// In block templates, get_the_ID() should work when inside post-template blocks
	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}
	
	// Get the snippet with the determined post ID
	$snippet = jww_get_search_snippet( $post_id );
	
	// Return the snippet - span with inline style should be preserved
	return $snippet;
}
add_shortcode( 'search_snippet', 'jww_search_snippet_shortcode' );

/**
 * Filter to process search_snippet shortcode with proper post context
 * 
 * This ensures the shortcode gets the correct post ID when rendered
 * in block templates by hooking into the block rendering process
 */
function jww_process_search_snippet_in_blocks( $block_content, $block ) {
	// Only process HTML blocks that contain our shortcode
	if ( isset( $block['blockName'] ) && $block['blockName'] === 'core/html' ) {
		if ( strpos( $block_content, '[search_snippet]' ) !== false ) {
			// The post context should be set by now in post-template blocks
			$post_id = get_the_ID();
			if ( $post_id ) {
				// Replace shortcode with actual snippet
				$snippet = jww_get_search_snippet( $post_id );
				$block_content = str_replace( '[search_snippet]', $snippet, $block_content );
			}
		}
	}
	return $block_content;
}
add_filter( 'render_block', 'jww_process_search_snippet_in_blocks', 10, 2 );
