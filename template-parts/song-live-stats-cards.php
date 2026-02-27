<?php
/**
 * Template part: Song live stats — Overview cards (Total times played, Days since last played, First played, Last played).
 * Used on single-song only for Jesse Welles songs. Expects song_id via get_query_var( 'song_id' ) or current post when on single song.
 *
 * @package JWW_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$song_id = (int) get_query_var( 'song_id', 0 );
if ( ! $song_id && get_post_type() === 'song' ) {
	$song_id = get_the_ID();
}
if ( ! $song_id || ! function_exists( 'jww_get_song_play_count' ) ) {
	return;
}

$play_count   = jww_get_song_play_count( $song_id );
$last_played  = jww_get_song_last_played( $song_id );
$first_played = jww_get_song_first_played( $song_id );
$gap_analysis = jww_get_song_gap_analysis( $song_id );

// Build "days since" string from gap analysis
$days_since_value = '—';
if ( $gap_analysis && $last_played ) {
	$days = (int) $gap_analysis['days_since'];
	if ( $days === 0 ) {
		$days_since_value = '0';
	} else {
		$years = floor( $days / 365 );
		$days_remaining = $days % 365;
		if ( $years > 0 ) {
			$days_since_value = $years . ' year' . ( $years > 1 ? 's' : '' );
			if ( $days_remaining > 0 ) {
				$days_since_value .= ', ' . $days_remaining . ' day' . ( $days_remaining > 1 ? 's' : '' );
			}
		} else {
			$days_since_value = $days . ' day' . ( $days > 1 ? 's' : '' );
		}
	}
}

// Helper to build value HTML for first/last played (show link + optional venue image)
$build_show_card_value = function( $played_data ) {
	if ( ! $played_data ) {
		return '<strong>—</strong><span class="tour-insight-stat-note">' . esc_html__( 'This song has not been played live yet.', 'jww-theme' ) . '</span>';
	}
	$show_title = get_the_title( $played_data['show_id'] );
	$loc_id     = isset( $played_data['location_id'] ) ? $played_data['location_id'] : (int) get_field( 'show_location', $played_data['show_id'] );
	$venue_image_id = function_exists( 'jww_get_venue_image_id' ) ? jww_get_venue_image_id( $loc_id ) : 0;
	$out = '';
	if ( $venue_image_id ) {
		$out .= '<div class="song-stat-venue-image-wrap">';
		$out .= '<a href="' . esc_url( $played_data['show_link'] ) . '" class="song-stat-venue-link">';
		$out .= wp_get_attachment_image( $venue_image_id, 'medium_large', false, array( 'class' => 'song-stat-venue-image', 'loading' => 'lazy', 'decoding' => 'async' ) );
		$out .= '</a></div>';
	}
	$out .= '<span class="tour-overview-value-inner"><a href="' . esc_url( $played_data['show_link'] ) . '">' . esc_html( $show_title ) . '</a></span>';
	return $out;
};

$cards = array();

// Total times played
$cards[] = array(
	'id'    => 'song-live-stat-play-count',
	'label' => __( 'Total times played', 'jww-theme' ),
	'icon'  => 'dashicons-playlist-audio',
	'title' => __( 'Number of times this song has been played live.', 'jww-theme' ),
	'value' => (string) $play_count,
	'note'  => ( $play_count === 0 ) ? __( 'This song has not been played live yet.', 'jww-theme' ) : '',
);

// Days since last played
$cards[] = array(
	'id'    => 'song-live-stat-days-since',
	'label' => __( 'Days since last played', 'jww-theme' ),
	'icon'  => 'dashicons-clock',
	'title' => __( 'Time since the most recent live performance.', 'jww-theme' ),
	'value' => $days_since_value,
	'note'  => ( $days_since_value === '—' ) ? __( 'This song has not been played live yet.', 'jww-theme' ) : '',
);

// First played (with optional venue image + show link)
$first_value_html = $build_show_card_value( $first_played );
$cards[] = array(
	'id'         => 'song-live-stat-first-played',
	'label'      => __( 'First played', 'jww-theme' ),
	'icon'       => 'dashicons-arrow-up-alt',
	'title'      => __( 'The first show where this song was played live.', 'jww-theme' ),
	'value_html' => $first_value_html,
);

// Last played (with optional venue image + show link)
$last_value_html = $build_show_card_value( $last_played );
$cards[] = array(
	'id'         => 'song-live-stat-last-played',
	'label'      => __( 'Last played', 'jww-theme' ),
	'icon'       => 'dashicons-arrow-down-alt',
	'title'      => __( 'The most recent show where this song was played live.', 'jww-theme' ),
	'value_html' => $last_value_html,
);

?>
<div class="show-stats-cards show-stats-cards-overview-grid alignwide song-live-stats-cards" aria-label="<?php esc_attr_e( 'Live performance statistics', 'jww-theme' ); ?>">
	<?php foreach ( $cards as $card ) : ?>
		<div class="tour-overview-card tour-insight-card--compact tour-insight-card--stat show-tour-stats-card show-stats-overview-card" id="<?php echo esc_attr( $card['id'] ); ?>" aria-labelledby="<?php echo esc_attr( $card['id'] ); ?>-heading">
			<div class="show-setlist-data-card-header-wrapper">
				<h2 id="<?php echo esc_attr( $card['id'] ); ?>-heading" class="wp-block-heading show-tour-stats-heading"><?php echo esc_html( $card['label'] ); ?></h2>
				<span class="show-setlist-data-card-info" title="<?php echo esc_attr( $card['title'] ); ?>">
					<span class="dashicons <?php echo esc_attr( $card['icon'] ); ?>" aria-hidden="true"></span>
				</span>
			</div>
			<?php if ( ! empty( $card['value_html'] ) ) : ?>
				<div class="show-tour-stats-meta tour-overview-value tour-insight-stat-value tour-insight-stat-value--with-venue">
					<?php echo $card['value_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — built from escaped show link/title and wp_get_attachment_image ?>
				</div>
			<?php else : ?>
				<p class="show-tour-stats-meta tour-overview-value tour-insight-stat-value">
					<strong><?php echo esc_html( $card['value'] ); ?></strong>
					<?php if ( ! empty( $card['note'] ) ) : ?>
						<span class="tour-insight-stat-note"><?php echo esc_html( $card['note'] ); ?></span>
					<?php endif; ?>
				</p>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
</div>
