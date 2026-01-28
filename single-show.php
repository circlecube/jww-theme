<?php
/**
 * Template for displaying single show posts
 */

get_header();

// Get show data
$location_id = get_field( 'show_location' );
$tour_id = get_field( 'show_tour' );
$setlist = get_field( 'setlist' );
$set_times = get_field( 'set_times' );
$ticket_link = get_field( 'ticket_link' );
$show_notes = get_field( 'show_notes' );
$show_artist = get_field( 'show_artist' );
$is_upcoming = ( get_the_date( 'U' ) > current_time( 'timestamp' ) );

// Get location hierarchy (reversed: Venue > City > Country)
$location_term = $location_id ? get_term( $location_id, 'location' ) : null;
$location_path = array();
if ( $location_term && ! is_wp_error( $location_term ) ) {
	$current_term = $location_term;
	while ( $current_term ) {
		array_unshift( $location_path, array(
			'name' => $current_term->name,
			'term_id' => $current_term->term_id,
			'link' => get_term_link( $current_term->term_id, 'location' )
		) );
		if ( $current_term->parent ) {
			$current_term = get_term( $current_term->parent, 'location' );
		} else {
			break;
		}
	}
	// Reverse the array to show Venue > City > Country instead of Country > City > Venue
	$location_path = array_reverse( $location_path );
}

// Get tour name and link
$tour_name = '';
$tour_link = '';
if ( $tour_id ) {
	$tour_term = get_term( $tour_id, 'tour' );
	if ( $tour_term && ! is_wp_error( $tour_term ) ) {
		$tour_name = $tour_term->name;
		$tour_link = get_term_link( $tour_term->term_id, 'tour' );
	}
}

// Get artist name (default to Jesse Welles)
$artist_name = 'Jesse Welles';
if ( $show_artist ) {
	if ( is_array( $show_artist ) && ! empty( $show_artist ) ) {
		$show_artist = $show_artist[0];
	}
	if ( is_object( $show_artist ) && isset( $show_artist->ID ) ) {
		$artist_name = get_the_title( $show_artist->ID );
	}
}
?>

<main class="wp-block-group align is-layout-flow wp-block-group-is-layout-flow">
	<div
		class="wp-block-group has-global-padding is-layout-constrained wp-block-group-is-layout-constrained" 
		style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50)"
	>
		<?php if ( has_post_thumbnail() ): ?>
			<div class="wp-block-group alignwide show-featured-image" style="margin-bottom:var(--wp--preset--spacing--40)">
				<?php the_post_thumbnail( 'large', array( 'class' => 'show-image' ) ); ?>
			</div>
		<?php endif; ?>

		<?php the_title('<h1 class="wp-block-post-title alignwide has-xxx-large-font-size">', '</h1>'); ?>
		
		<!-- Show Meta Information -->
		<div class="wp-block-group is-layout-flex is-content-justification-space-between flex-direction-row alignwide show-meta" style="margin-bottom:var(--wp--preset--spacing--40)">
			<div class="show-meta-left">
				<div class="show-date">
					<strong><?php echo get_the_date( 'F j, Y' ); ?></strong>
				</div>
				<?php if ( ! empty( $location_path ) ): ?>
					<div class="show-location">
						<?php 
						$location_links = array();
						foreach ( $location_path as $location_item ) {
							if ( is_array( $location_item ) && isset( $location_item['link'] ) ) {
								$location_links[] = '<a href="' . esc_url( $location_item['link'] ) . '">' . esc_html( $location_item['name'] ) . '</a>';
							} else {
								// Fallback for old format (just string)
								$location_links[] = esc_html( $location_item );
							}
						}
						echo implode( ' > ', $location_links );
						?>
					</div>
				<?php endif; ?>
				<?php if ( $tour_name ): ?>
					<div class="show-tour">
						<?php if ( $tour_link && ! is_wp_error( $tour_link ) ): ?>
							<em><a href="<?php echo esc_url( $tour_link ); ?>"><?php echo esc_html( $tour_name ); ?></a></em>
						<?php else: ?>
							<em><?php echo esc_html( $tour_name ); ?></em>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
			<?php if ( $is_upcoming && $ticket_link ): ?>
				<div class="show-ticket-link">
					<a href="<?php echo esc_url( $ticket_link ); ?>" class="wp-block-button__link wp-element-button" target="_blank" rel="noopener">
						Buy Tickets
					</a>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( $set_times && ( $set_times['doors_time'] || $set_times['start_time'] || $set_times['end_time'] ) ): ?>
			<div class="wp-block-group alignwide show-set-times" style="margin-bottom:var(--wp--preset--spacing--40)">
				<?php if ( $set_times['doors_time'] ): ?>
					<div><strong>Doors:</strong> <?php echo esc_html( date( 'g:i a', strtotime( $set_times['doors_time'] ) ) ); ?></div>
				<?php endif; ?>
				<?php if ( $set_times['start_time'] ): ?>
					<div><strong>Start:</strong> <?php echo esc_html( date( 'g:i a', strtotime( $set_times['start_time'] ) ) ); ?></div>
				<?php endif; ?>
				<?php if ( $set_times['end_time'] ): ?>
					<div><strong>End:</strong> <?php echo esc_html( date( 'g:i a', strtotime( $set_times['end_time'] ) ) ); ?></div>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<div class="wp-block-post-content alignwide">
			<?php the_content(); ?>
		</div>
	</div>
</main>

<!-- Setlist Section -->
<?php if ( $setlist && ! empty( $setlist ) ): ?>
	<div class="wp-block-group has-accent-6-background-color has-background is-layout-constrained has-global-padding" style="border-style:none;border-width:0px">
		<div class="wp-block-post-content">
			<h2 class="wp-block-heading">Setlist</h2>
			
			<ol class="setlist-list">
				<?php foreach ( $setlist as $index => $item ): 
					$entry_type = $item['entry_type'] ?? 'song-post';
					
					if ( $entry_type === 'note' ):
						// Note-only entry
						$note_text = $item['notes'] ?? '';
						if ( $note_text ):
				?>
					<li class="setlist-note">
						<em><?php echo esc_html( $note_text ); ?></em>
					</li>
				<?php 
						endif;
					else:
						// Song entry
						$song = null;
						$song_name = '';
						$song_link = '';
						$original_artist = '';
						
						if ( $entry_type === 'song-post' && ! empty( $item['song'] ) ) {
							$song_obj = is_array( $item['song'] ) ? $item['song'][0] : $item['song'];
							if ( is_object( $song_obj ) && isset( $song_obj->ID ) ) {
								$song = $song_obj;
								$song_name = get_the_title( $song->ID );
								$song_link = get_permalink( $song->ID );
								
								// Check if this is a cover song and get original artist
								$song_terms = wp_get_post_terms( $song->ID, 'category' );
								$is_cover = false;
								foreach ( $song_terms as $term ) {
									if ( $term->slug === 'cover' ) {
										$is_cover = true;
										break;
									}
								}
								
								if ( $is_cover ) {
									$attribution = get_field( 'attribution', $song->ID );
									if ( $attribution ) {
										$original_artist = $attribution;
									}
								}
							}
						} elseif ( $entry_type === 'song-text' && ! empty( $item['song_text'] ) ) {
							$song_name = $item['song_text'];
						}
						
						if ( $song_name ):
							$notes = $item['notes'] ?? '';
							
							// Extract cover artist from notes if not already set from song post
							if ( ! $original_artist && $notes && preg_match( '/^Cover:\s*(.+?)(?:\s*-\s*|$)/i', $notes, $matches ) ) {
								$original_artist = $matches[1];
								// Remove "Cover: Artist" from notes display
								$notes = preg_replace( '/^Cover:\s*.+?\s*-\s*/i', '', $notes );
								$notes = preg_replace( '/^Cover:\s*.+?$/i', '', $notes );
								$notes = trim( $notes );
							}
				?>
					<li class="setlist-song">
						<?php if ( $song_link ): ?>
							<a href="<?php echo esc_url( $song_link ); ?>"><?php echo esc_html( $song_name ); ?></a>
						<?php else: ?>
							<?php echo esc_html( $song_name ); ?>
						<?php endif; ?>
						<?php if ( $original_artist ): ?>
							<span class="setlist-original-artist">(<?php echo esc_html( $original_artist ); ?>)</span>
						<?php endif; ?>
						<?php if ( $notes ): ?>
							<span class="setlist-notes"><?php echo esc_html( $notes ); ?></span>
						<?php endif; ?>
					</li>
				<?php 
						endif;
					endif;
				endforeach; 
				?>
			</ol>
		</div>
	</div>
<?php endif; ?>

<?php if ( $show_notes ): ?>
	<!-- Show Notes Section -->
	<div class="wp-block-group is-style-default has-base-background-color has-background is-layout-constrained has-global-padding" style="margin-top:0;margin-bottom:0">
		<div class="wp-block-post-content">
			<h3 class="wp-block-heading">Show Notes</h3>
			<div class="show-notes-content has-medium-font-size">
				<?php echo wp_kses_post( $show_notes ); ?>
			</div>
		</div>
	</div>
<?php endif; ?>

<!-- Navigation -->
<div class="nav-link-container wp-block-group alignwide">
	<?php
	the_post_navigation(array(
		'prev_text' => '<span class="nav-subtitle">' . esc_html__('Previous:', 'jww-theme') . '</span> <span class="nav-title">%title</span>',
		'next_text' => '<span class="nav-subtitle">' . esc_html__('Next:', 'jww-theme') . '</span> <span class="nav-title">%title</span>',
	));
	?>
</div>

<?php
// If comments are open or we have at least one comment, load up the comment template.
if (comments_open() || get_comments_number()) :
	comments_template();
endif;
?>

<?php get_footer(); ?>
