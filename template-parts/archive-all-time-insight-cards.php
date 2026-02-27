<?php
/**
 * Template part: All-time concert insight cards.
 * Used on main Shows archive only. Expects query vars: archive_all_time_*.
 * Includes: stat cards, Popular Openers/Closers (top 3), Latest debut, All song stats link, One-off, Standout, Release representation.
 *
 * @package JWW_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$total_shows   = (int) get_query_var( 'archive_all_time_total_shows', 0 );
$upcoming      = (int) get_query_var( 'archive_all_time_upcoming_count', 0 );
$past          = (int) get_query_var( 'archive_all_time_past_count', 0 );
$venues_count  = (int) get_query_var( 'archive_all_time_venues_count', 0 );
$cities_count  = (int) get_query_var( 'archive_all_time_cities_count', 0 );
$shows_w_data  = (int) get_query_var( 'archive_all_time_shows_with_data_count', 0 );
$unique_songs  = (int) get_query_var( 'archive_all_time_unique_songs_count', 0 );

$card_class = 'tour-overview-card tour-insight-card--compact tour-insight-card--stat masonry-card--half wp-block-group alignwide has-global-padding show-tour-stats-card';
$card_class_full = 'tour-overview-card tour-insight-card--compact tour-insight-card--stat wp-block-group alignwide has-global-padding show-tour-stats-card';

// --- Simple stat cards (Total, Upcoming, Past, Venues, Cities, Shows with data, Unique songs, Festivals) ---
$simple_cards = array(
	array( 'id' => 'archive-all-time-total', 'label' => __( 'Total shows', 'jww-theme' ), 'value' => $total_shows, 'icon' => 'dashicons-tickets-alt', 'title' => __( 'All-time total number of shows.', 'jww-theme' ) ),
	array( 'id' => 'archive-all-time-upcoming', 'label' => __( 'Upcoming', 'jww-theme' ), 'value' => $upcoming, 'icon' => 'dashicons-clock', 'title' => __( 'Shows that haven\'t happened yet.', 'jww-theme' ), 'link_accordion' => 'archive-upcoming-shows-accordion' ),
	array( 'id' => 'archive-all-time-past', 'label' => __( 'Past shows', 'jww-theme' ), 'value' => $past, 'icon' => 'dashicons-calendar-alt', 'title' => __( 'Shows that have already taken place.', 'jww-theme' ), 'link_accordion' => 'archive-past-shows-accordion' ),
	array( 'id' => 'archive-all-time-venues', 'label' => __( 'Venues', 'jww-theme' ), 'value' => $venues_count, 'icon' => 'dashicons-location', 'title' => __( 'Unique venues played at.', 'jww-theme' ) ),
	array( 'id' => 'archive-all-time-cities', 'label' => __( 'Cities', 'jww-theme' ), 'value' => $cities_count, 'icon' => 'dashicons-location-alt', 'title' => __( 'Unique cities played in.', 'jww-theme' ) ),
	array( 'id' => 'archive-all-time-shows-with-data', 'label' => __( 'Shows with setlist data', 'jww-theme' ), 'value' => $shows_w_data, 'icon' => 'dashicons-playlist-audio', 'title' => __( 'Shows that have setlist data.', 'jww-theme' ) ),
	array( 'id' => 'archive-all-time-unique-songs', 'label' => __( 'Unique songs', 'jww-theme' ), 'value' => $unique_songs, 'icon' => 'dashicons-playlist-audio', 'title' => __( 'Distinct songs across all setlists.', 'jww-theme' ) ),
);

$festivals_count = (int) get_query_var( 'archive_all_time_festivals_count', 0 );
if ( $festivals_count > 0 ) {
	$simple_cards[] = array( 'id' => 'archive-all-time-festivals', 'label' => __( 'Festival shows', 'jww-theme' ), 'value' => $festivals_count, 'icon' => 'dashicons-flag', 'title' => __( 'Shows that were part of a festival.', 'jww-theme' ) );
}

foreach ( $simple_cards as $card ) :
	$accordion_id = isset( $card['link_accordion'] ) ? $card['link_accordion'] : '';
	?>
	<div class="<?php echo esc_attr( $card_class ); ?>" id="<?php echo esc_attr( $card['id'] ); ?>" aria-labelledby="<?php echo esc_attr( $card['id'] ); ?>-heading">
		<div class="show-setlist-data-card-header-wrapper">
			<h2 id="<?php echo esc_attr( $card['id'] ); ?>-heading" class="wp-block-heading show-tour-stats-heading"><?php echo esc_html( $card['label'] ); ?></h2>
			<span class="show-setlist-data-card-info" title="<?php echo esc_attr( $card['title'] ); ?>">
				<span class="dashicons <?php echo esc_attr( $card['icon'] ); ?>" aria-hidden="true"></span>
			</span>
		</div>
		<p class="show-tour-stats-meta tour-overview-value tour-insight-stat-value">
			<?php
			if ( $accordion_id ) {
				echo '<a href="#' . esc_attr( $accordion_id ) . '" class="archive-insight-accordion-link" data-accordion-id="' . esc_attr( $accordion_id ) . '">';
				echo '<strong>' . esc_html( (string) $card['value'] ) . '</strong></a>';
			} else {
				echo '<strong>' . esc_html( (string) $card['value'] ) . '</strong>';
			}
			?>
		</p>
	</div>
	<?php
endforeach;

// --- Popular Openers (top 3 in <ol>) ---
$openers = get_query_var( 'archive_all_time_openers', array() );
if ( ! empty( $openers ) ) :
	?>
	<div class="<?php echo esc_attr( $card_class_full ); ?>" id="archive-all-time-openers" aria-labelledby="archive-all-time-openers-heading">
		<div class="show-setlist-data-card-header-wrapper">
			<h2 id="archive-all-time-openers-heading" class="wp-block-heading show-tour-stats-heading"><?php esc_html_e( 'Popular Openers', 'jww-theme' ); ?></h2>
			<span class="show-setlist-data-card-info" title="<?php esc_attr_e( 'Top 3 songs that opened shows (all time).', 'jww-theme' ); ?>">
				<span class="dashicons dashicons-arrow-up-alt" aria-hidden="true"></span>
			</span>
		</div>
		<ol class="show-tour-stats-meta tour-overview-value tour-insight-stat-value archive-insight-ranked-list">
			<?php foreach ( array_slice( $openers, 0, 3 ) as $item ) : ?>
				<li>
					<?php if ( ! empty( $item['link'] ) ) : ?>
						<a href="<?php echo esc_url( $item['link'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a>
					<?php else : ?>
						<?php echo esc_html( $item['title'] ); ?>
					<?php endif; ?>
					<?php if ( ! empty( $item['count'] ) ) : ?>
						<span class="archive-insight-count">(<?php echo (int) $item['count']; ?>×)</span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ol>
	</div>
	<?php
endif;

// --- Popular Closers (top 3 in <ol>) ---
$closers = get_query_var( 'archive_all_time_closers', array() );
if ( ! empty( $closers ) ) :
	?>
	<div class="<?php echo esc_attr( $card_class_full ); ?>" id="archive-all-time-closers" aria-labelledby="archive-all-time-closers-heading">
		<div class="show-setlist-data-card-header-wrapper">
			<h2 id="archive-all-time-closers-heading" class="wp-block-heading show-tour-stats-heading"><?php esc_html_e( 'Popular Closers', 'jww-theme' ); ?></h2>
			<span class="show-setlist-data-card-info" title="<?php esc_attr_e( 'Top 3 songs that closed shows (all time, often the encore).', 'jww-theme' ); ?>">
				<span class="dashicons dashicons-arrow-down-alt" aria-hidden="true"></span>
			</span>
		</div>
		<ol class="show-tour-stats-meta tour-overview-value tour-insight-stat-value archive-insight-ranked-list">
			<?php foreach ( array_slice( $closers, 0, 3 ) as $item ) : ?>
				<li>
					<?php if ( ! empty( $item['link'] ) ) : ?>
						<a href="<?php echo esc_url( $item['link'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a>
					<?php else : ?>
						<?php echo esc_html( $item['title'] ); ?>
					<?php endif; ?>
					<?php if ( ! empty( $item['count'] ) ) : ?>
						<span class="archive-insight-count">(<?php echo (int) $item['count']; ?>×)</span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ol>
	</div>
	<?php
endif;

// --- Latest debut ---
$latest_debut = get_query_var( 'archive_all_time_latest_debut', null );
if ( $latest_debut && ! empty( $latest_debut['song_title'] ) ) :
	?>
	<div class="<?php echo esc_attr( $card_class ); ?>" id="archive-all-time-latest-debut" aria-labelledby="archive-all-time-latest-debut-heading">
		<div class="show-setlist-data-card-header-wrapper">
			<h2 id="archive-all-time-latest-debut-heading" class="wp-block-heading show-tour-stats-heading"><?php esc_html_e( 'Latest debut', 'jww-theme' ); ?></h2>
			<span class="show-setlist-data-card-info" title="<?php esc_attr_e( 'Most recently debuted song (first-ever live performance).', 'jww-theme' ); ?>">
				<span class="dashicons dashicons-star-filled" aria-hidden="true"></span>
			</span>
		</div>
		<p class="show-tour-stats-meta tour-overview-value tour-insight-stat-value">
			<?php if ( ! empty( $latest_debut['song_link'] ) ) : ?>
				<a href="<?php echo esc_url( $latest_debut['song_link'] ); ?>"><?php echo esc_html( $latest_debut['song_title'] ); ?></a>
			<?php else : ?>
				<?php echo esc_html( $latest_debut['song_title'] ); ?>
			<?php endif; ?>
			<?php if ( ! empty( $latest_debut['show_link'] ) && ! empty( $latest_debut['show_date'] ) ) : ?>
				<span class="tour-debut-show"> · <a href="<?php echo esc_url( $latest_debut['show_link'] ); ?>"><?php echo esc_html( $latest_debut['show_date'] ); ?></a></span>
			<?php endif; ?>
		</p>
	</div>
	<?php
endif;

// --- All song stats (link to table below) ---
?>
<div class="<?php echo esc_attr( $card_class ); ?>" id="archive-all-time-song-stats-link" aria-labelledby="archive-all-time-song-stats-link-heading">
	<div class="show-setlist-data-card-header-wrapper">
		<h2 id="archive-all-time-song-stats-link-heading" class="wp-block-heading show-tour-stats-heading"><?php esc_html_e( 'All song stats', 'jww-theme' ); ?></h2>
		<span class="show-setlist-data-card-info" title="<?php esc_attr_e( 'View play counts, first/last played for every song.', 'jww-theme' ); ?>">
			<span class="dashicons dashicons-chart-bar" aria-hidden="true"></span>
		</span>
	</div>
	<p class="show-tour-stats-meta tour-overview-value tour-insight-stat-value">
		<a href="#archive-song-live-stats-accordion" class="archive-insight-accordion-link" data-accordion-id="archive-song-live-stats-accordion"><?php esc_html_e( 'View song live stats table', 'jww-theme' ); ?></a>
	</p>
</div>

<?php
// --- One-off, Standout, Release representation (full-width list cards) ---
get_template_part( 'template-parts/archive-all-time-one-offs' );
get_template_part( 'template-parts/archive-all-time-standout' );
get_template_part( 'template-parts/archive-all-time-release-representation' );
