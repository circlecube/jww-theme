<?php
/**
 * Template Name: Statistics Dashboard
 * 
 * Displays comprehensive show and song statistics including:
 * - All-time song play counts
 * - Songs never played live
 * - Longest gaps between plays
 * - Most played songs by tour
 * - Venue statistics
 * - Tour statistics
 */

get_header();

// Ensure helper functions are loaded
if ( ! function_exists( 'jww_get_all_time_song_stats' ) ) {
	echo '<p>Statistics functions not available.</p>';
	get_footer();
	return;
}

// Get all statistics
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

// Get gap analysis for all songs
$gap_analysis = array();
foreach ( $all_songs as $song ) {
	$gap = jww_get_song_gap_analysis( $song->ID );
	if ( $gap ) {
		$gap_analysis[] = array(
			'song_id'     => $song->ID,
			'song_title'  => get_the_title( $song->ID ),
			'song_link'   => get_permalink( $song->ID ),
			'days_since'  => $gap['days_since'],
			'play_count'  => $gap['play_count'],
			'last_played' => $gap['last_played'],
		);
	}
}
usort( $gap_analysis, function( $a, $b ) {
	return $b['days_since'] - $a['days_since'];
} );

// Get tour statistics
$tours = get_terms( array(
	'taxonomy'   => 'tour',
	'hide_empty' => false,
) );
$tour_stats = array();
if ( ! is_wp_error( $tours ) ) {
	foreach ( $tours as $tour ) {
		$shows = jww_get_shows_by_tour( $tour->term_id );
		$song_count = 0;
		$unique_songs = array();
		$song_plays_by_tour = array();
		
		foreach ( $shows as $show ) {
			$setlist = get_field( 'setlist', $show->ID );
			if ( $setlist && is_array( $setlist ) ) {
				foreach ( $setlist as $item ) {
					if ( isset( $item['entry_type'] ) && $item['entry_type'] === 'song-post' && ! empty( $item['song'] ) ) {
						$song = is_array( $item['song'] ) ? $item['song'][0] : $item['song'];
						$song_id = is_object( $song ) ? $song->ID : $song;
						$unique_songs[ $song_id ] = true;
						$song_count++;
						
						if ( ! isset( $song_plays_by_tour[ $song_id ] ) ) {
							$song_plays_by_tour[ $song_id ] = array(
								'song_id'    => $song_id,
								'song_title' => get_the_title( $song_id ),
								'song_link'  => get_permalink( $song_id ),
								'count'      => 0,
							);
						}
						$song_plays_by_tour[ $song_id ]['count']++;
					} elseif ( isset( $item['entry_type'] ) && $item['entry_type'] === 'song-text' && ! empty( $item['song_text'] ) ) {
						$song_count++;
					}
				}
			}
		}
		
		// Sort songs by play count for this tour
		usort( $song_plays_by_tour, function( $a, $b ) {
			return $b['count'] - $a['count'];
		} );
		
		$tour_stats[] = array(
			'tour_id'          => $tour->term_id,
			'tour_name'        => $tour->name,
			'tour_link'        => get_term_link( $tour->term_id, 'tour' ),
			'show_count'       => count( $shows ),
			'song_count'       => $song_count,
			'unique_songs'     => count( $unique_songs ),
			'most_played_songs' => array_slice( $song_plays_by_tour, 0, 10 ),
		);
	}
	
	// Sort tours by show count
	usort( $tour_stats, function( $a, $b ) {
		return $b['show_count'] - $a['show_count'];
	} );
}

// Get venue statistics
$shows = get_posts( array(
	'post_type'      => 'show',
	'posts_per_page' => -1,
	'post_status'    => array( 'publish', 'future' ),
) );
$venue_stats = array();
foreach ( $shows as $show ) {
	$location_id = get_field( 'show_location', $show->ID );
	if ( ! $location_id ) {
		continue;
	}
	
	$location_term = get_term( $location_id, 'location' );
	if ( ! $location_term || is_wp_error( $location_term ) ) {
		continue;
	}
	
	$venue_key = $location_term->term_id;
	if ( ! isset( $venue_stats[ $venue_key ] ) ) {
		$venue_stats[ $venue_key ] = array(
			'venue_id'    => $location_term->term_id,
			'venue_name'  => $location_term->name,
			'venue_link'  => get_term_link( $location_term->term_id, 'location' ),
			'show_count'  => 0,
			'song_count'  => 0,
		);
	}
	
	$venue_stats[ $venue_key ]['show_count']++;
	
	$setlist = get_field( 'setlist', $show->ID );
	if ( $setlist && is_array( $setlist ) ) {
		foreach ( $setlist as $item ) {
			if ( isset( $item['entry_type'] ) && ( $item['entry_type'] === 'song-post' || $item['entry_type'] === 'song-text' ) ) {
				$venue_stats[ $venue_key ]['song_count']++;
			}
		}
	}
}
uasort( $venue_stats, function( $a, $b ) {
	return $b['show_count'] - $a['show_count'];
} );

// Get overall stats
$total_shows = count( $shows );
$upcoming_shows = jww_get_upcoming_shows();
$past_shows = jww_get_past_shows();
$total_songs_played = count( $all_song_stats );
$total_unique_songs = count( $all_songs );
?>

<main id="main" class="site-main stats-dashboard">
	<div class="stats-container">
		<header class="stats-header">
			<h1>Show & Song Statistics</h1>
			<p class="stats-subtitle">Comprehensive statistics about live performances and songs</p>
		</header>

		<!-- Overall Statistics -->
		<section class="stats-section overall-stats">
			<h2>Overall Statistics</h2>
			<div class="stats-grid">
				<div class="stat-card">
					<div class="stat-value"><?php echo esc_html( $total_shows ); ?></div>
					<div class="stat-label">Total Shows</div>
				</div>
				<div class="stat-card">
					<div class="stat-value"><?php echo esc_html( count( $upcoming_shows ) ); ?></div>
					<div class="stat-label">Upcoming Shows</div>
				</div>
				<div class="stat-card">
					<div class="stat-value"><?php echo esc_html( count( $past_shows ) ); ?></div>
					<div class="stat-label">Past Shows</div>
				</div>
				<div class="stat-card">
					<div class="stat-value"><?php echo esc_html( $total_songs_played ); ?></div>
					<div class="stat-label">Songs Played Live</div>
				</div>
				<div class="stat-card">
					<div class="stat-value"><?php echo esc_html( $total_unique_songs ); ?></div>
					<div class="stat-label">Total Songs in Database</div>
				</div>
				<div class="stat-card">
					<div class="stat-value"><?php echo esc_html( count( $never_played ) ); ?></div>
					<div class="stat-label">Songs Never Played</div>
				</div>
			</div>
		</section>

		<!-- All-Time Song Play Counts -->
		<section class="stats-section">
			<h2>All-Time Song Play Counts</h2>
			<p class="section-description">Top songs by number of live performances</p>
			<div class="stats-list">
				<ol class="song-play-list">
					<?php foreach ( array_slice( $all_song_stats, 0, 50 ) as $index => $stat ): ?>
						<li>
							<span class="rank"><?php echo esc_html( $index + 1 ); ?>.</span>
							<a href="<?php echo esc_url( $stat['song_link'] ); ?>" class="song-title">
								<?php echo esc_html( $stat['song_title'] ); ?>
							</a>
							<span class="play-count"><?php echo esc_html( $stat['play_count'] ); ?> time<?php echo $stat['play_count'] > 1 ? 's' : ''; ?></span>
							<?php if ( $stat['last_played'] ): ?>
								<span class="last-played">
									Last: <a href="<?php echo esc_url( $stat['last_played']['show_link'] ); ?>">
										<?php echo esc_html( $stat['last_played']['show_date'] ); ?>
									</a>
								</span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ol>
			</div>
		</section>

		<!-- Songs Never Played -->
		<?php if ( ! empty( $never_played ) ): ?>
			<section class="stats-section">
				<h2>Songs Never Played Live</h2>
				<p class="section-description">Songs in the database that have never been performed</p>
				<div class="stats-list">
					<ul class="never-played-list">
						<?php foreach ( $never_played as $song ): ?>
							<li>
								<a href="<?php echo esc_url( $song['song_link'] ); ?>">
									<?php echo esc_html( $song['song_title'] ); ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			</section>
		<?php endif; ?>

		<!-- Longest Gaps -->
		<section class="stats-section">
			<h2>Longest Gaps Since Last Played</h2>
			<p class="section-description">Songs with the longest time since their last live performance</p>
			<div class="stats-list">
				<ol class="gap-analysis-list">
					<?php foreach ( array_slice( $gap_analysis, 0, 25 ) as $gap ): ?>
						<?php
						$years = floor( $gap['days_since'] / 365 );
						$days = $gap['days_since'] % 365;
						$time_string = '';
						if ( $years > 0 ) {
							$time_string = $years . ' year' . ( $years > 1 ? 's' : '' );
							if ( $days > 0 ) {
								$time_string .= ', ' . $days . ' day' . ( $days > 1 ? 's' : '' );
							}
						} else {
							$time_string = $gap['days_since'] . ' day' . ( $gap['days_since'] > 1 ? 's' : '' );
						}
						?>
						<li>
							<a href="<?php echo esc_url( $gap['song_link'] ); ?>" class="song-title">
								<?php echo esc_html( $gap['song_title'] ); ?>
							</a>
							<span class="gap-time"><?php echo esc_html( $time_string ); ?> ago</span>
							<span class="play-count">(<?php echo esc_html( $gap['play_count'] ); ?> total plays)</span>
							<?php if ( $gap['last_played'] ): ?>
								<span class="last-played">
									Last: <a href="<?php echo esc_url( $gap['last_played']['show_link'] ); ?>">
										<?php echo esc_html( $gap['last_played']['show_date'] ); ?>
									</a>
								</span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ol>
			</div>
		</section>

		<!-- Tour Statistics -->
		<?php if ( ! empty( $tour_stats ) ): ?>
			<section class="stats-section">
				<h2>Tour Statistics</h2>
				<p class="section-description">Statistics for each tour</p>
				<div class="tour-stats-grid">
					<?php foreach ( $tour_stats as $tour ): ?>
						<div class="tour-stat-card">
							<h3>
								<a href="<?php echo esc_url( $tour['tour_link'] ); ?>">
									<?php echo esc_html( $tour['tour_name'] ); ?>
								</a>
							</h3>
							<div class="tour-stats-details">
								<div class="tour-stat-item">
									<span class="stat-label">Shows:</span>
									<span class="stat-value"><?php echo esc_html( $tour['show_count'] ); ?></span>
								</div>
								<div class="tour-stat-item">
									<span class="stat-label">Unique Songs:</span>
									<span class="stat-value"><?php echo esc_html( $tour['unique_songs'] ); ?></span>
								</div>
								<div class="tour-stat-item">
									<span class="stat-label">Total Song Plays:</span>
									<span class="stat-value"><?php echo esc_html( $tour['song_count'] ); ?></span>
								</div>
							</div>
							<?php if ( ! empty( $tour['most_played_songs'] ) ): ?>
								<div class="tour-most-played">
									<h4>Most Played Songs</h4>
									<ol>
										<?php foreach ( $tour['most_played_songs'] as $song ): ?>
											<li>
												<a href="<?php echo esc_url( $song['song_link'] ); ?>">
													<?php echo esc_html( $song['song_title'] ); ?>
												</a>
												<span class="play-count">(<?php echo esc_html( $song['count'] ); ?>)</span>
											</li>
										<?php endforeach; ?>
									</ol>
								</div>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

		<!-- Venue Statistics -->
		<?php if ( ! empty( $venue_stats ) ): ?>
			<section class="stats-section">
				<h2>Venue Statistics</h2>
				<p class="section-description">Top venues by number of shows</p>
				<div class="stats-list">
					<ol class="venue-stats-list">
						<?php foreach ( array_slice( $venue_stats, 0, 25 ) as $venue ): ?>
							<li>
								<a href="<?php echo esc_url( $venue['venue_link'] ); ?>" class="venue-name">
									<?php echo esc_html( $venue['venue_name'] ); ?>
								</a>
								<span class="show-count"><?php echo esc_html( $venue['show_count'] ); ?> show<?php echo $venue['show_count'] > 1 ? 's' : ''; ?></span>
								<span class="song-count">(<?php echo esc_html( $venue['song_count'] ); ?> total songs)</span>
							</li>
						<?php endforeach; ?>
					</ol>
				</div>
			</section>
		<?php endif; ?>
	</div>
</main>

<?php
get_footer();
