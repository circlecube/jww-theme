<?php
/**
 * Admin page to reorganize location taxonomy: move cities under states (Country > State > City > Venue).
 *
 * @package JWW_Theme
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class JWW_Location_Reorganizer
 */
class JWW_Location_Reorganizer {

	const LOCATION_TAX = 'location';
	const NONCE_ACTION = 'jww_location_reorganizer';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ), 21 );
		add_action( 'admin_init', array( __CLASS__, 'handle_actions' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ), 10, 1 );
	}

	public static function add_menu_page() {
		add_submenu_page(
			'edit.php?post_type=show',
			__( 'Organize Locations', 'jww-theme' ),
			__( 'Organize Locations', 'jww-theme' ),
			'manage_categories',
			'jww-location-reorganizer',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Handle form submissions: create state, move city.
	 */
	public static function handle_actions() {
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( $page !== 'jww-location-reorganizer' ) {
			return;
		}

		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}

		// Create new state
		if ( isset( $_POST['jww_reorganizer_action'] ) && $_POST['jww_reorganizer_action'] === 'create_state' ) {
			if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), self::NONCE_ACTION ) ) {
				return;
			}
			$country_id = isset( $_POST['country_id'] ) ? (int) $_POST['country_id'] : 0;
			$state_name = isset( $_POST['state_name'] ) ? sanitize_text_field( wp_unslash( $_POST['state_name'] ) ) : '';
			if ( $country_id && $state_name !== '' ) {
				$term = wp_insert_term( $state_name, self::LOCATION_TAX, array( 'parent' => $country_id ) );
				if ( ! is_wp_error( $term ) ) {
					$new_term_id = $term['term_id'];
					// Set location type so it appears as a state immediately (no cities under it yet).
					update_term_meta( $new_term_id, 'location_type', 'state_province' );
					if ( function_exists( 'update_field' ) ) {
						update_field( 'location_type', 'state_province', 'location_' . $new_term_id );
					}
					self::clear_location_caches( array( $country_id, $new_term_id ) );
					wp_safe_redirect( add_query_arg( array(
						'page'        => 'jww-location-reorganizer',
						'country_id'  => $country_id,
						'created'     => 1,
					), admin_url( 'edit.php?post_type=show' ) ) );
					exit;
				}
			}
		}

		// Move city to state
		if ( isset( $_POST['jww_reorganizer_action'] ) && $_POST['jww_reorganizer_action'] === 'move_city' ) {
			if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), self::NONCE_ACTION ) ) {
				return;
			}
			$city_id    = isset( $_POST['city_id'] ) ? (int) $_POST['city_id'] : 0;
			$new_parent = isset( $_POST['new_parent_state_id'] ) ? (int) $_POST['new_parent_state_id'] : 0;
			if ( $city_id && $new_parent ) {
				$term = get_term( $city_id, self::LOCATION_TAX );
				if ( $term && ! is_wp_error( $term ) ) {
					$updated = wp_update_term( $city_id, self::LOCATION_TAX, array( 'parent' => $new_parent ) );
					if ( ! is_wp_error( $updated ) ) {
						$country_id = self::get_term_country_id( $new_parent );
						$ids = array( $city_id, $new_parent );
						if ( $country_id ) {
							$ids[] = $country_id;
						}
						self::clear_location_caches( $ids );
						wp_safe_redirect( add_query_arg( array(
							'page'       => 'jww-location-reorganizer',
							'country_id' => $country_id ?: (int) $term->parent,
							'moved'      => 1,
						), admin_url( 'edit.php?post_type=show' ) ) );
						exit;
					}
				}
			}
		}

		// Bulk move cities
		if ( isset( $_POST['jww_reorganizer_action'] ) && $_POST['jww_reorganizer_action'] === 'bulk_move_cities' ) {
			if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), self::NONCE_ACTION ) ) {
				return;
			}
			$new_parent = isset( $_POST['new_parent_state_id'] ) ? (int) $_POST['new_parent_state_id'] : 0;
			$city_ids   = isset( $_POST['city_ids'] ) && is_array( $_POST['city_ids'] ) ? array_map( 'intval', $_POST['city_ids'] ) : array();
			$country_id = isset( $_POST['country_id'] ) ? (int) $_POST['country_id'] : 0;
			if ( $new_parent && ! empty( $city_ids ) ) {
				$affected = array( $new_parent );
				foreach ( $city_ids as $city_id ) {
					if ( $city_id <= 0 ) {
						continue;
					}
					$updated = wp_update_term( $city_id, self::LOCATION_TAX, array( 'parent' => $new_parent ) );
					if ( ! is_wp_error( $updated ) ) {
						$affected[] = $city_id;
					}
				}
				if ( $country_id ) {
					$affected[] = $country_id;
				}
				self::clear_location_caches( array_unique( $affected ) );
				wp_safe_redirect( add_query_arg( array(
					'page'        => 'jww-location-reorganizer',
					'country_id'  => $country_id,
					'bulk_moved'  => count( array_filter( $city_ids ) ),
				), admin_url( 'edit.php?post_type=show' ) ) );
				exit;
			}
		}
	}

	/**
	 * Get root (country) term id for a term by walking up.
	 */
	private static function get_term_country_id( $term_id ) {
		$tid = (int) $term_id;
		while ( $tid ) {
			$term = get_term( $tid, self::LOCATION_TAX );
			if ( ! $term || is_wp_error( $term ) ) {
				return 0;
			}
			if ( ! $term->parent ) {
				return (int) $term->term_id;
			}
			$tid = (int) $term->parent;
		}
		return 0;
	}

	/**
	 * Clear location-related transients for given term IDs and their hierarchy.
	 */
	private static function clear_location_caches( $term_ids ) {
		delete_transient( 'jww_archive_locations' );
		$all_ids = array();
		foreach ( array_filter( $term_ids ) as $id ) {
			$all_ids[] = $id;
			$term = get_term( $id, self::LOCATION_TAX );
			if ( $term && ! is_wp_error( $term ) ) {
				$pid = $term->parent;
				while ( $pid ) {
					$all_ids[] = $pid;
					$p = get_term( $pid, self::LOCATION_TAX );
					$pid = $p && ! is_wp_error( $p ) ? $p->parent : 0;
				}
			}
		}
		foreach ( array_unique( $all_ids ) as $id ) {
			delete_transient( 'jww_location_hierarchy_' . $id );
		}
	}

	/**
	 * Get countries (top-level location terms).
	 *
	 * @return WP_Term[]
	 */
	public static function get_countries() {
		$terms = get_terms( array(
			'taxonomy'   => self::LOCATION_TAX,
			'parent'     => 0,
			'hide_empty' => false,
			'orderby'    => 'name',
		) );
		return is_wp_error( $terms ) ? array() : $terms;
	}

	/**
	 * Classify direct children of a country: use location_type when set; else "states" = have grandchildren, "cities" = no grandchildren.
	 *
	 * @param int $country_id Country term ID.
	 * @return array{ 'states' => WP_Term[], 'cities' => WP_Term[] }
	 */
	public static function get_states_and_cities_for_country( $country_id ) {
		$children = get_terms( array(
			'taxonomy'   => self::LOCATION_TAX,
			'parent'     => $country_id,
			'hide_empty' => false,
			'orderby'    => 'name',
		) );
		if ( is_wp_error( $children ) || empty( $children ) ) {
			return array( 'states' => array(), 'cities' => array() );
		}

		$states = array();
		$cities = array();
		$use_type = function_exists( 'jww_get_location_type' );

		foreach ( $children as $term ) {
			if ( $use_type ) {
				$type = jww_get_location_type( $term->term_id );
				if ( $type === 'state_province' ) {
					$states[] = $term;
					continue;
				}
				if ( $type === 'city' || $type === 'venue' ) {
					$cities[] = $term;
					continue;
				}
				if ( $type === 'country' ) {
					continue; // Shouldn't be under another country
				}
			}

			// Fallback: classify by grandchildren
			$grandchildren = get_terms( array(
				'taxonomy'   => self::LOCATION_TAX,
				'parent'     => $term->term_id,
				'hide_empty' => false,
				'fields'     => 'ids',
			) );
			$has_children = ! is_wp_error( $grandchildren ) && ! empty( $grandchildren );
			$has_grandchildren = false;
			if ( $has_children ) {
				foreach ( $grandchildren as $child_id ) {
					$gc = get_terms( array(
						'taxonomy' => self::LOCATION_TAX,
						'parent'   => $child_id,
						'fields'   => 'ids',
					) );
					if ( ! is_wp_error( $gc ) && ! empty( $gc ) ) {
						$has_grandchildren = true;
						break;
					}
				}
			}
			if ( $has_grandchildren ) {
				$states[] = $term;
			} else {
				$cities[] = $term;
			}
		}
		return array( 'states' => $states, 'cities' => $cities );
	}

	/**
	 * Count direct children of a term.
	 */
	public static function get_child_count( $term_id ) {
		$terms = get_terms( array(
			'taxonomy'   => self::LOCATION_TAX,
			'parent'     => $term_id,
			'hide_empty' => false,
			'fields'     => 'count',
		) );
		return is_wp_error( $terms ) ? 0 : (int) $terms;
	}

	public static function enqueue_assets( $hook ) {
		if ( $hook !== 'show_page_jww-location-reorganizer' ) {
			return;
		}
		$file = get_stylesheet_directory() . '/admin/css/location-reorganizer.css';
		if ( file_exists( $file ) ) {
			wp_enqueue_style(
				'jww-location-reorganizer',
				get_stylesheet_directory_uri() . '/admin/css/location-reorganizer.css',
				array(),
				wp_get_theme()->get( 'Version' )
			);
		}
	}

	public static function render_page() {
		$countries = self::get_countries();
		$country_id = isset( $_GET['country_id'] ) ? (int) $_GET['country_id'] : 0;
		$created   = isset( $_GET['created'] ) ? (int) $_GET['created'] : 0;
		$moved     = isset( $_GET['moved'] ) ? (int) $_GET['moved'] : 0;
		$bulk_moved = isset( $_GET['bulk_moved'] ) ? (int) $_GET['bulk_moved'] : 0;

		$country_term = $country_id ? get_term( $country_id, self::LOCATION_TAX ) : null;
		$country_name = ( $country_term && ! is_wp_error( $country_term ) ) ? $country_term->name : '';

		$states = array();
		$cities = array();
		if ( $country_id && $country_term && ! is_wp_error( $country_term ) ) {
			$classified = self::get_states_and_cities_for_country( $country_id );
			$states = $classified['states'];
			$cities = $classified['cities'];
		}
		?>
		<div class="wrap jww-location-reorganizer">
			<h1><?php esc_html_e( 'Organize Locations', 'jww-theme' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Move cities under state/province terms so the hierarchy is Country → State → City → Venue (e.g. for US, Canada, Australia). Select a country, then create states and assign cities to them.', 'jww-theme' ); ?>
			</p>

			<?php if ( $created ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'State created.', 'jww-theme' ); ?></p></div>
			<?php endif; ?>
			<?php if ( $moved ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'City moved to state.', 'jww-theme' ); ?></p></div>
			<?php endif; ?>
			<?php if ( $bulk_moved ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( sprintf( _n( '%d city moved.', '%d cities moved.', $bulk_moved, 'jww-theme' ), $bulk_moved ) ); ?></p></div>
			<?php endif; ?>

			<form method="get" action="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>" class="jww-reorganizer-select-country">
				<input type="hidden" name="post_type" value="show" />
				<input type="hidden" name="page" value="jww-location-reorganizer" />
				<label for="country_id"><?php esc_html_e( 'Country', 'jww-theme' ); ?></label>
				<select name="country_id" id="country_id">
					<option value=""><?php esc_html_e( '— Select a country —', 'jww-theme' ); ?></option>
					<?php foreach ( $countries as $c ) : ?>
						<option value="<?php echo esc_attr( $c->term_id ); ?>" <?php selected( $country_id, $c->term_id ); ?>><?php echo esc_html( $c->name ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Show locations', 'jww-theme' ); ?></button>
			</form>

			<?php if ( $country_id && $country_name ) : ?>

				<h2><?php echo esc_html( sprintf( __( 'Locations in %s', 'jww-theme' ), $country_name ) ); ?></h2>

				<!-- States -->
				<div class="jww-reorganizer-section jww-reorganizer-states">
					<h3><?php esc_html_e( 'States / Provinces', 'jww-theme' ); ?></h3>
					<p class="description"><?php esc_html_e( 'Terms that have cities (or venues) under them. Add a new state to group cities under.', 'jww-theme' ); ?></p>

					<form method="post" action="" style="margin-bottom: 1em;">
						<?php wp_nonce_field( self::NONCE_ACTION ); ?>
						<input type="hidden" name="jww_reorganizer_action" value="create_state" />
						<input type="hidden" name="country_id" value="<?php echo esc_attr( $country_id ); ?>" />
						<label for="state_name"><?php esc_html_e( 'New state/province name', 'jww-theme' ); ?></label>
						<input type="text" name="state_name" id="state_name" class="regular-text" required />
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Add state', 'jww-theme' ); ?></button>
					</form>

					<?php if ( ! empty( $states ) ) : ?>
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th><?php esc_html_e( 'State / Province', 'jww-theme' ); ?></th>
									<th><?php esc_html_e( 'Cities', 'jww-theme' ); ?></th>
									<th><?php esc_html_e( 'Edit', 'jww-theme' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $states as $state ) : ?>
									<tr>
										<td><strong><?php echo esc_html( $state->name ); ?></strong></td>
										<td><?php echo esc_html( (string) self::get_child_count( $state->term_id ) ); ?></td>
										<td>
											<a href="<?php echo esc_url( get_edit_term_link( $state->term_id, self::LOCATION_TAX ) ); ?>"><?php esc_html_e( 'Edit', 'jww-theme' ); ?></a>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'No states yet. Add one above; then you can move cities under it.', 'jww-theme' ); ?></p>
					<?php endif; ?>
				</div>

				<!-- Cities (directly under country – can be moved to a state) -->
				<div class="jww-reorganizer-section jww-reorganizer-cities">
					<h3><?php esc_html_e( 'Cities directly under country', 'jww-theme' ); ?></h3>
					<p class="description"><?php esc_html_e( 'These are currently under the country. Move them to a state using the dropdown, or leave as-is.', 'jww-theme' ); ?></p>

					<?php if ( empty( $cities ) ) : ?>
						<p><?php esc_html_e( 'No cities directly under this country (all may already be under states).', 'jww-theme' ); ?></p>
					<?php elseif ( empty( $states ) ) : ?>
						<p><?php esc_html_e( 'Create at least one state above, then you can move cities here into it.', 'jww-theme' ); ?></p>
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th><?php esc_html_e( 'City', 'jww-theme' ); ?></th>
									<th><?php esc_html_e( 'Venues', 'jww-theme' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $cities as $city ) : ?>
									<tr>
										<td><?php echo esc_html( $city->name ); ?></td>
										<td><?php echo esc_html( (string) self::get_child_count( $city->term_id ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php else : ?>
						<form method="post" action="" class="jww-bulk-move-form">
							<?php wp_nonce_field( self::NONCE_ACTION ); ?>
							<input type="hidden" name="jww_reorganizer_action" value="bulk_move_cities" />
							<input type="hidden" name="country_id" value="<?php echo esc_attr( $country_id ); ?>" />
							<table class="wp-list-table widefat fixed striped">
								<thead>
									<tr>
										<th class="check-column"><input type="checkbox" id="jww-select-all-cities" /></th>
										<th><?php esc_html_e( 'City', 'jww-theme' ); ?></th>
										<th><?php esc_html_e( 'Venues', 'jww-theme' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $cities as $city ) : ?>
										<tr>
											<th scope="row" class="check-column">
												<input type="checkbox" name="city_ids[]" value="<?php echo esc_attr( $city->term_id ); ?>" class="jww-city-check" />
											</th>
											<td><?php echo esc_html( $city->name ); ?></td>
											<td><?php echo esc_html( (string) self::get_child_count( $city->term_id ) ); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
							<p class="submit">
								<label for="jww-bulk-state-id"><?php esc_html_e( 'Move selected to state:', 'jww-theme' ); ?></label>
								<select name="new_parent_state_id" id="jww-bulk-state-id">
									<option value=""><?php esc_html_e( '— Select state —', 'jww-theme' ); ?></option>
									<?php foreach ( $states as $state ) : ?>
										<option value="<?php echo esc_attr( $state->term_id ); ?>"><?php echo esc_html( $state->name ); ?></option>
									<?php endforeach; ?>
								</select>
								<button type="submit" class="button button-primary"><?php esc_html_e( 'Move selected cities', 'jww-theme' ); ?></button>
								<span class="description"><?php esc_html_e( 'Check cities above, choose a state, then click Move.', 'jww-theme' ); ?></span>
							</p>
						</form>

						<p class="description" style="margin-top: 1em;"><?php esc_html_e( 'Or move one city at a time:', 'jww-theme' ); ?></p>
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th><?php esc_html_e( 'City', 'jww-theme' ); ?></th>
									<th><?php esc_html_e( 'Venues', 'jww-theme' ); ?></th>
									<th><?php esc_html_e( 'Move to state', 'jww-theme' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $cities as $city ) : ?>
									<tr>
										<td><?php echo esc_html( $city->name ); ?></td>
										<td><?php echo esc_html( (string) self::get_child_count( $city->term_id ) ); ?></td>
										<td>
											<form method="post" action="" style="display:inline;">
												<?php wp_nonce_field( self::NONCE_ACTION ); ?>
												<input type="hidden" name="jww_reorganizer_action" value="move_city" />
												<input type="hidden" name="city_id" value="<?php echo esc_attr( $city->term_id ); ?>" />
												<select name="new_parent_state_id" required>
													<option value=""><?php esc_html_e( '— Select state —', 'jww-theme' ); ?></option>
													<?php foreach ( $states as $state ) : ?>
														<option value="<?php echo esc_attr( $state->term_id ); ?>"><?php echo esc_html( $state->name ); ?></option>
													<?php endforeach; ?>
												</select>
												<button type="submit" class="button"><?php esc_html_e( 'Move', 'jww-theme' ); ?></button>
											</form>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>

			<?php endif; ?>
		</div>

		<script>
		(function() {
			var selectAll = document.getElementById('jww-select-all-cities');
			if (selectAll) {
				selectAll.addEventListener('change', function() {
					document.querySelectorAll('.jww-city-check').forEach(function(cb) { cb.checked = selectAll.checked; });
				});
			}
			var bulkForm = document.querySelector('.jww-bulk-move-form');
			if (bulkForm) {
				bulkForm.addEventListener('submit', function(e) {
					var checked = bulkForm.querySelectorAll('.jww-city-check:checked');
					if (checked.length === 0) {
						e.preventDefault();
						alert('<?php echo esc_js( __( 'Please select at least one city.', 'jww-theme' ) ); ?>');
						return;
					}
					var select = bulkForm.querySelector('#jww-bulk-state-id');
					if (select && !select.value) {
						e.preventDefault();
						alert('<?php echo esc_js( __( 'Please choose a state.', 'jww-theme' ) ); ?>');
					}
				});
			}
		})();
		</script>
		<?php
	}
}

JWW_Location_Reorganizer::init();
