<?php
/**
 * Venues admin page: find venues without images, search Wikimedia Commons, sideload and attach.
 *
 * @package JWW_Theme
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class JWW_Venues_Admin
 */
class JWW_Venues_Admin {

	const WIKIMEDIA_API = 'https://commons.wikimedia.org/w/api.php';
	const THUMB_WIDTH   = 400;

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ), 20 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ), 10, 1 );
		add_action( 'wp_ajax_jww_venues_search_wikimedia', array( __CLASS__, 'ajax_search_wikimedia' ) );
		add_action( 'wp_ajax_jww_venues_sideload_attach', array( __CLASS__, 'ajax_sideload_attach' ) );
	}

	/**
	 * Add Venues submenu under Shows.
	 */
	public static function add_menu_page() {
		add_submenu_page(
			'edit.php?post_type=show',
			__( 'Venues', 'jww-theme' ),
			__( 'Venues', 'jww-theme' ),
			'edit_posts',
			'jww-venues',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Get leaf location terms (venues only) that don't have a venue image.
	 *
	 * @return array Array of term objects with added breadcrumb and has_image.
	 */
	public static function get_venues_without_image() {
		$all = get_terms( array(
			'taxonomy'   => 'location',
			'hide_empty' => false,
			'parent'     => 0,
		) );
		if ( is_wp_error( $all ) || empty( $all ) ) {
			return array();
		}

		$leaf_terms = self::get_leaf_terms( $all );
		$out        = array();
		foreach ( $leaf_terms as $term ) {
			$image_id = (int) get_term_meta( $term->term_id, 'venue_image_id', true );
			if ( $image_id > 0 ) {
				continue;
			}
			$term->breadcrumb   = self::get_venue_breadcrumb( $term );
			$term->search_query = self::get_venue_search_query( $term );
			$out[]              = $term;
		}
		return $out;
	}

	/**
	 * Recursively collect leaf terms (no children).
	 *
	 * @param WP_Term[] $terms Terms to check.
	 * @return WP_Term[]
	 */
	private static function get_leaf_terms( $terms ) {
		$leaves = array();
		foreach ( $terms as $term ) {
			$children = get_terms( array(
				'taxonomy'   => 'location',
				'parent'     => $term->term_id,
				'hide_empty' => false,
			) );
			if ( is_wp_error( $children ) || empty( $children ) ) {
				$leaves[] = $term;
			} else {
				$leaves = array_merge( $leaves, self::get_leaf_terms( $children ) );
			}
		}
		return $leaves;
	}

	/**
	 * Get breadcrumb string for a venue: "Venue Name (City, Country)".
	 *
	 * @param WP_Term $term Venue term.
	 * @return string
	 */
	public static function get_venue_breadcrumb( $term ) {
		$parts = array( $term->name );
		$parent_id = $term->parent;
		$ancestors = array();
		while ( $parent_id ) {
			$parent = get_term( $parent_id, 'location' );
			if ( ! $parent || is_wp_error( $parent ) ) {
				break;
			}
			$ancestors[] = $parent->name;
			$parent_id  = $parent->parent;
		}
		if ( ! empty( $ancestors ) ) {
			$parts[] = '(' . implode( ', ', array_reverse( $ancestors ) ) . ')';
		}
		return implode( ' ', $parts );
	}

	/**
	 * Get search query for a venue: "Venue Name City" (venue + city) for better Commons results.
	 *
	 * @param WP_Term $term Venue term.
	 * @return string
	 */
	public static function get_venue_search_query( $term ) {
		$query = $term->name;
		if ( $term->parent ) {
			$parent = get_term( $term->parent, 'location' );
			if ( $parent && ! is_wp_error( $parent ) ) {
				$query = trim( $term->name . ' ' . $parent->name );
			}
		}
		return $query;
	}

	/**
	 * Render the Venues admin page.
	 */
	public static function render_page() {
		$venues = self::get_venues_without_image();
		?>
		<div class="wrap jww-venues-admin">
			<h1><?php esc_html_e( 'Venues', 'jww-theme' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Venues (leaf locations) that do not yet have an image. Search Wikimedia Commons or add an image by URL.', 'jww-theme' ); ?>
			</p>

			<?php if ( empty( $venues ) ) : ?>
				<p><?php esc_html_e( 'All venues have an image, or there are no venue-level locations.', 'jww-theme' ); ?></p>
				<?php
				return;
			endif;
			?>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Venue', 'jww-theme' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Actions', 'jww-theme' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $venues as $term ) : ?>
						<tr data-term-id="<?php echo esc_attr( $term->term_id ); ?>">
							<td>
								<strong><?php echo esc_html( $term->name ); ?></strong>
								<?php if ( ! empty( $term->breadcrumb ) && $term->breadcrumb !== $term->name ) : ?>
									<br><span class="description"><?php echo esc_html( $term->breadcrumb ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<div class="actions-container">
									<button type="button" class="button jww-venue-search" data-term-id="<?php echo esc_attr( $term->term_id ); ?>" data-venue-name="<?php echo esc_attr( $term->name ); ?>" data-search-query="<?php echo esc_attr( $term->search_query ); ?>">
										<?php esc_html_e( 'Search for image', 'jww-theme' ); ?>
									</button>
									<button type="button" class="button jww-venue-manual" data-term-id="<?php echo esc_attr( $term->term_id ); ?>">
										<?php esc_html_e( 'Manual import', 'jww-theme' ); ?>
									</button>
									<?php
									$google_images_url = 'https://www.google.com/search?' . http_build_query( array( 'tbm' => 'isch', 'q' => $term->search_query ) );
									?>
									<a href="<?php echo esc_url( $google_images_url ); ?>" class="button button-link jww-venue-google-images" target="_blank" rel="noopener noreferrer" title="<?php esc_attr_e( 'Open Google Image search in a new tab to verify images', 'jww-theme' ); ?>">
										<?php esc_html_e( 'Google Images', 'jww-theme' ); ?>
									</a>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<!-- Modal: search results -->
		<div id="jww-venue-modal" class="jww-venue-modal" role="dialog" aria-modal="true" aria-labelledby="jww-venue-modal-title" style="display: none;">
			<div class="jww-venue-modal-backdrop"></div>
			<div class="jww-venue-modal-content">
				<h2 id="jww-venue-modal-title" class="jww-venue-modal-title"><?php esc_html_e( 'Choose an image', 'jww-theme' ); ?></h2>
				<div class="jww-venue-modal-results"></div>
				<div class="jww-venue-modal-actions">
					<button type="button" class="button jww-venue-modal-reject"><?php esc_html_e( 'Reject', 'jww-theme' ); ?></button>
					<button type="button" class="button button-primary jww-venue-modal-accept" disabled><?php esc_html_e( 'Accept', 'jww-theme' ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Enqueue admin scripts and styles for the Venues page.
	 */
	public static function enqueue_assets( $hook ) {
		if ( $hook !== 'show_page_jww-venues' ) {
			return;
		}
		wp_enqueue_style(
			'jww-venues-admin',
			get_stylesheet_directory_uri() . '/admin/css/venues-admin.css',
			array(),
			wp_get_theme()->get( 'Version' )
		);
		wp_enqueue_script(
			'jww-venues-admin',
			get_stylesheet_directory_uri() . '/admin/js/venues-admin.js',
			array( 'jquery' ),
			wp_get_theme()->get( 'Version' ),
			true
		);
		wp_localize_script( 'jww-venues-admin', 'jwwVenuesAdmin', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'jww_venues_admin' ),
			'l10n'    => array(
				'searching' => __( 'Searching…', 'jww-theme' ),
				'noResults' => __( 'No images found.', 'jww-theme' ),
				'error'     => __( 'Something went wrong.', 'jww-theme' ),
				'manualPrompt' => __( 'Enter image URL:', 'jww-theme' ),
				'sideloading' => __( 'Adding to media library…', 'jww-theme' ),
				'done'      => __( 'Image added.', 'jww-theme' ),
			),
		) );
	}

	/**
	 * AJAX: Search Wikimedia Commons for images.
	 */
	public static function ajax_search_wikimedia() {
		check_ajax_referer( 'jww_venues_admin', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'jww-theme' ) ) );
		}
		$search = isset( $_REQUEST['search'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['search'] ) ) : '';
		if ( $search === '' ) {
			wp_send_json_error( array( 'message' => __( 'Search term required.', 'jww-theme' ) ) );
		}

		$url = add_query_arg( array(
			'action'       => 'query',
			'generator'   => 'search',
			'gsrsearch'   => $search,
			'gsrnamespace' => 6,
			'gsrlimit'    => 12,
			'prop'        => 'imageinfo',
			'iiprop'      => 'url|thumburl',
			'iiurlwidth'  => self::THUMB_WIDTH,
			'format'      => 'json',
			'origin'      => '*',
		), self::WIKIMEDIA_API );

		$response = wp_remote_get( $url, array(
			'timeout' => 15,
			'headers' => array( 'Accept' => 'application/json' ),
		) );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}
		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		if ( $code !== 200 || $body === '' ) {
			wp_send_json_error( array( 'message' => __( 'Wikimedia request failed.', 'jww-theme' ) ) );
		}

		$data = json_decode( $body, true );
		if ( ! isset( $data['query']['pages'] ) || ! is_array( $data['query']['pages'] ) ) {
			wp_send_json_success( array( 'images' => array() ) );
			return;
		}

		$images = array();
		foreach ( $data['query']['pages'] as $page ) {
			$info = isset( $page['imageinfo'][0] ) ? $page['imageinfo'][0] : null;
			if ( ! $info || empty( $info['thumburl'] ) ) {
				continue;
			}
			$images[] = array(
				'title'     => isset( $page['title'] ) ? $page['title'] : '',
				'thumburl'  => $info['thumburl'],
				'url'       => isset( $info['url'] ) ? $info['url'] : $info['thumburl'],
				'pageid'    => isset( $page['pageid'] ) ? $page['pageid'] : 0,
			);
		}
		wp_send_json_success( array( 'images' => $images ) );
	}

	/**
	 * AJAX: Sideload image from URL and attach to term.
	 */
	public static function ajax_sideload_attach() {
		check_ajax_referer( 'jww_venues_admin', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'jww-theme' ) ) );
		}
		$term_id = isset( $_REQUEST['term_id'] ) ? (int) $_REQUEST['term_id'] : 0;
		if ( $term_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid term.', 'jww-theme' ) ) );
		}
		$term = get_term( $term_id, 'location' );
		if ( ! $term || is_wp_error( $term ) ) {
			wp_send_json_error( array( 'message' => __( 'Term not found.', 'jww-theme' ) ) );
		}

		$image_url = '';
		if ( ! empty( $_REQUEST['image_url'] ) ) {
			$image_url = esc_url_raw( wp_unslash( $_REQUEST['image_url'] ) );
		} elseif ( ! empty( $_REQUEST['wikimedia_title'] ) ) {
			$title = sanitize_text_field( wp_unslash( $_REQUEST['wikimedia_title'] ) );
			$image_url = self::get_wikimedia_file_url( $title );
			if ( ! $image_url ) {
				wp_send_json_error( array( 'message' => __( 'Could not get image URL from Wikimedia.', 'jww-theme' ) ) );
			}
		}
		if ( $image_url === '' ) {
			wp_send_json_error( array( 'message' => __( 'Image URL or Wikimedia title required.', 'jww-theme' ) ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = download_url( $image_url );
		if ( is_wp_error( $tmp ) ) {
			wp_send_json_error( array( 'message' => $tmp->get_error_message() ) );
		}
		$file_array = array(
			'name'     => basename( wp_parse_url( $image_url, PHP_URL_PATH ) ) ?: 'venue-image.jpg',
			'tmp_name' => $tmp,
		);
		$attachment_id = media_handle_sideload( $file_array, 0, null, array( 'post_title' => $term->name . ' (venue)' ) );
		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp );
			wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
		}

		update_term_meta( $term_id, 'venue_image_id', $attachment_id );
		self::clear_location_hierarchy_cache( $term_id );

		wp_send_json_success( array(
			'attachment_id' => $attachment_id,
			'image_url'    => wp_get_attachment_image_url( $attachment_id, 'medium' ),
		) );
	}

	/**
	 * Get direct file URL for a Wikimedia Commons file title (e.g. "File:Example.jpg").
	 *
	 * @param string $title File title.
	 * @return string|false URL or false on failure.
	 */
	private static function get_wikimedia_file_url( $title ) {
		$url = add_query_arg( array(
			'action'    => 'query',
			'titles'    => $title,
			'prop'      => 'imageinfo',
			'iiprop'    => 'url',
			'format'    => 'json',
			'origin'    => '*',
		), self::WIKIMEDIA_API );
		$response = wp_remote_get( $url, array( 'timeout' => 10 ) );
		if ( is_wp_error( $response ) ) {
			return false;
		}
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		if ( ! isset( $data['query']['pages'] ) ) {
			return false;
		}
		foreach ( $data['query']['pages'] as $page ) {
			if ( isset( $page['imageinfo'][0]['url'] ) ) {
				return $page['imageinfo'][0]['url'];
			}
		}
		return false;
	}

	/**
	 * Clear location hierarchy transients that might include this term.
	 */
	private static function clear_location_hierarchy_cache( $term_id ) {
		$term = get_term( $term_id, 'location' );
		if ( ! $term || is_wp_error( $term ) ) {
			return;
		}
		$ids = array( $term_id );
		$parent_id = $term->parent;
		while ( $parent_id ) {
			$ids[] = $parent_id;
			$parent = get_term( $parent_id, 'location' );
			$parent_id = $parent && ! is_wp_error( $parent ) ? $parent->parent : 0;
		}
		foreach ( $ids as $id ) {
			delete_transient( 'jww_location_hierarchy_' . $id );
		}
	}
}

JWW_Venues_Admin::init();
