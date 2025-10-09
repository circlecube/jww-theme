<?php
/**
 * Template for displaying single song posts
 */

get_header();
?>

<main class="wp-block-group align is-layout-flow wp-block-group-is-layout-flow">
	<div
		class="wp-block-group has-global-padding is-layout-constrained wp-block-group-is-layout-constrained" 
		style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50)"
	>
		
		<?php the_title('<h1 class="wp-block-post-title alignwide has-text-align-center has-xxx-large-font-size">', '</h1>'); ?>
		
		<div class="wp-block-post-content">
			<?php the_content(); ?>
		</div>
		
		<?php
		// Video field
		$video_embed = get_field('video');
		if ($video_embed): ?>
			<div class="wp-block-group alignwide video-section">
				<div class="video-container has-text-align-center">
					<?php echo $video_embed; ?>
				</div>
			</div>
		<?php endif; ?>
		
		<?php
		// Get song and artist info for music service links
		$song_title = get_the_title();
		$album = get_field('album');
		$album_title = '';
		$artist_name = '';
		
		// Get album title if available
		if ($album) {
			// Handle different return formats from ACF relationship field
			if (is_array($album)) {
				// If it returns an array (multiple selections or object format)
				$album_id = is_object($album[0]) ? $album[0]->ID : $album[0];
				$album_title = get_the_title($album_id);
				
				// Get artist from album
				$artist = get_field('artist', $album_id);
				if ($artist) {
					$artist_id = is_object($artist) ? $artist->ID : $artist;
					$artist_name = get_the_title($artist_id);
				}
			} else {
				// If it returns a single ID or post object
				$album_id = is_object($album) ? $album->ID : $album;
				$album_title = get_the_title($album_id);
				
				// Get artist from album
				$artist = get_field('artist', $album_id);
				if ($artist) {
					$artist_id = is_object($artist) ? $artist->ID : $artist;
					$artist_name = get_the_title($artist_id);
				}
			}
		}
		
		// Filter out common default WordPress titles for both album and artist
		$default_titles = array('Hello world!', 'Hello World', 'Sample Page', 'Sample Post', 'Uncategorized');
		if (in_array($album_title, $default_titles)) {
			$album_title = '';
		}
		if (in_array($artist_name, $default_titles)) {
			$artist_name = '';
		}
		
		// Generate all music service links for the song
		echo get_song_music_service_links($song_title, $artist_name, $album_title);
		?>
	</div>
</main>

<!-- Lyrics Section -->
<div class="wp-block-group has-accent-6-background-color has-background is-layout-constrained" style="border-style:none;border-width:0px">
	<div style="height:4rem" aria-hidden="true" class="wp-block-spacer"></div>
	
	<div class="wp-block-post-content">
	<h2 class="wp-block-heading">Lyrics</h2>
	
	<?php
	$lyrics = get_field( 'lyrics' );
	if ( $lyrics ): ?>
		<div class="lyrics-content">
			<?php echo wp_kses_post( $lyrics ); ?>
		</div>
	<?php else: ?>
		<p>Sorry, no lyrics yet.</p>
	<?php endif; ?>
	
	<div style="height:4rem" aria-hidden="true" class="wp-block-spacer"></div>
	</div>
</div>

<!-- Annotations Section -->
<div class="wp-block-group is-style-default has-base-background-color has-background is-layout-constrained" style="margin-top:0;margin-bottom:0">
	<div style="height:4rem" aria-hidden="true" class="wp-block-spacer"></div>
	
	<div class="wp-block-post-content">
	<h3 class="wp-block-heading">Annotations and Notes on Lyrics</h3>
	
	<?php
	$annotations = get_field( 'lyric_annotations' ); // ACF field name
	if ( $annotations ): ?>
		<div class="annotations-content has-medium-font-size">
			<?php echo wp_kses_post( $annotations ); ?>
		</div>
	<?php else: ?>
		<p>Sorry, no notes yet.</p>
	<?php endif; ?>
	
	<div style="height:4rem" aria-hidden="true" class="wp-block-spacer"></div>
	</div>
</div>

<!-- Appears on Section -->
<div class="wp-block-group is-style-default has-base-background-color has-background is-layout-constrained" style="margin-top:0;margin-bottom:0">
	
	<div class="wp-block-post-content">
		<?php
			// Get the album field and pass it to the template part
			$albums = get_field('album');
			if ($albums) {
				set_query_var('albums', $albums);
				get_template_part('template-parts/album-covers');
			}
		?>
	</div>
</div>

<!-- Navigation -->
<div class="nav-link-container wp-block-group alignwide">
		<?php
		the_post_navigation(array(
			'prev_text' => '<span class="nav-subtitle">' . esc_html__('Previous:', 'jww-theme') . '</span> <span class="nav-title">%title</span>',
			'next_text' => '<span class="nav-subtitle">' . esc_html__('Next:', 'jww-theme') . '</span> <span class="nav-title">%title</span>',
		));
		?>
	</nav>
</div>

<?php
// If comments are open or we have at least one comment, load up the comment template.
if (comments_open() || get_comments_number()) :
	comments_template();
endif;
?>

<?php get_footer(); ?>
