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
		
		<?php the_title('<h1 class="wp-block-post-title alignwide has-xxx-large-font-size">', '</h1>'); ?>
		
		<?php
			$attribution = get_field( 'attribution' );
			$artist_id = get_field('artist');
			$artist = get_post($artist_id[0]);
			$artist_name = get_the_title($artist);
			$artist_link = get_permalink($artist);
			
		?>
		<h2 class="wp-block-heading has-large-font-size song-artist-heading alignwide">
			<strong><em>
				<a href="<?php echo $artist_link; ?>" class="artist-link">
					<img src="<?php echo get_the_post_thumbnail_url($artist, 'thumbnail'); ?>" alt="<?php echo $artist_name; ?>" class="artist-image">
					<?php echo $artist_name; ?>
				</a>
			</em></strong>
			<?php if ( $attribution ): ?>
				<span class="wp-block-heading has-large-font-size">performing <strong><em><?php echo $attribution; ?></em></strong></span>
			<?php endif; ?>
			<!-- post date -->
		</h2>
		<span class="has-small-font-size" title="First published on"><?php echo get_the_date('F j, Y'); ?></span>

		<div class="wp-block-post-content">
			<?php the_content(); ?>
		</div>

		<?php
		// Bandcamp song and album id
		$bandcamp_song_id  = get_field('bandcamp_song_id') ?? '';
		$bandcamp_album_id = get_field('bandcamp_album_id') ?? '';
		$bandcamp_iframe   = get_field('bandcamp_iframe') ?? ''; // default to iframe field
		// override iframe with custom iframe if song and album are available
		if ( $bandcamp_song_id && $bandcamp_album_id ) {
			$bandcamp_iframe = '<iframe ' .
				'style="border: 0; width: 100%; height: 120px;" ' .
				'src="https://bandcamp.com/EmbeddedPlayer/' .
				'/size=large/bgcol=ffffff/linkcol=0687f5/tracklist=false/artwork=small/transparent=true' .
				'/album=' . $bandcamp_album_id . // album id is optional and adds track nav buttons
				'/track=' . $bandcamp_song_id . // song id is required
				'" seamless></iframe>';
		}
		?>
		<?php if ( $bandcamp_iframe ): ?>
			<div class="wp-block-group bandcamp-section">
				<div class="bandcamp-container has-text-align-center">
					<?php echo $bandcamp_iframe; ?>
				</div>
			</div>
		<?php endif; ?>
		
		<?php
		// Video fields
		$yt_video_embed        = get_field('video');
		$tiktok_video_embed    = get_field('tiktok_video');
		$instagram_video_embed = get_field('instagram_video');
		$music_video_embed     = get_field('music_video');
		
		if ( $yt_video_embed ): ?>
			<div class="wp-block-group alignwide video-section">
				<div class="video-container has-text-align-center">
					<?php echo $yt_video_embed; ?>
				</div>
			</div>
		<?php endif; ?>
		<?php if ( $tiktok_video_embed ): ?>
			<div class="wp-block-group alignwide tiktok-video-section">
				<div class="tiktok-video-container has-text-align-center">
					<?php echo $tiktok_video_embed; ?>
				</div>
			</div>
		<?php endif; ?>
		<?php if ( $instagram_video_embed ): ?>
			<div class="wp-block-group alignwide instagram-video-section">
				<div class="instagram-video-container has-text-align-center">
					<?php
					// extract url from instagram_video_embed
					$instagram_video_url = get_field( 'instagram_video', false, false );
					?>
					<blockquote
						class="instagram-media"
						data-instgrm-permalink="<?php echo $instagram_video_url; ?>"
						data-instgrm-version="14"
						style="
							background:#FFF;
							border:0;
							border-radius:3px;
							box-shadow:0 0 1px 0 rgba(0,0,0,0.5),0 1px 10px 0 rgba(0,0,0,0.15);
							margin: 1px;
							max-width:540px;
							min-width:326px;
							padding:0;
							width:calc(100% - 2px);
						"
					>
					</blockquote>
					<script async src="//www.instagram.com/embed.js"></script>
				</div>
			</div>
		<?php endif; ?>
		<?php if ( $music_video_embed ): ?>
			<div class="wp-block-group alignwide video-section music-video-section">
				<div class="video-container has-text-align-center">
					<?php echo $music_video_embed; ?>
				</div>
			</div>
		<?php endif; ?>
		
		<?php
		// Get song and artist info for music service links
		$song_title = get_the_title() ?? '';
		// Get ACF song links repeater field
		$song_links = get_field('song_links');
		// Generate all music service links for the song (ACF links will override generated ones)
		echo get_song_music_service_links($song_title, $artist_name, '', array(), $song_links);
		?>
	</div>
</main>

<!-- Lyrics Section -->
<div class="wp-block-group has-accent-6-background-color has-background is-layout-constrained has-global-padding" style="border-style:none;border-width:0px">
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
	
	</div>
</div>

<?php
	$annotations = get_field( 'lyric_annotations' ); // ACF field name
	if ( $annotations ): ?>
		<!-- Annotations Section -->
		<div class="wp-block-group is-style-default has-base-background-color has-background is-layout-constrained has-global-padding" style="margin-top:0;margin-bottom:0">
			<div class="wp-block-post-content">
			<h3 class="wp-block-heading">Annotations and Notes on Lyrics</h3>
			
			
				<div class="annotations-content has-medium-font-size">
					<?php echo wp_kses_post( $annotations ); ?>
				</div>
				
			</div>
		</div>
<?php endif; ?>

<!-- Appears on Section -->
<div class="wp-block-group is-style-default has-base-background-color has-background is-layout-constrained has-global-padding" style="margin-top:0;margin-bottom:0">
	
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
