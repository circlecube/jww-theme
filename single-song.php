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
		<div class="wp-block-group is-layout-flex is-content-justification-space-between flex-direction-row alignwide song-post-meta">
			<h2 class="wp-block-heading has-large-font-size song-artist-heading">
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
			<span class="has-small-font-size song-post-date" title="First published on"><?php echo get_the_date('F j, Y'); ?></span>
		</div>

		<div class="wp-block-post-content alignwide">
			<?php the_content(); ?>
		</div>
		
		<?php
		// Video fields - check new repeater field first, fallback to old fields
		$embeds = get_field('embeds');
		
		if ( $embeds && is_array( $embeds ) && ! empty( $embeds ) ) {
			// New repeater format - loop through ALL embeds
			foreach ( $embeds as $video ) {
				$source = $video['embed_type'] ?? 'youtube';
				
				// Get the appropriate field based on source
				$embed = '';
				$url = '';
				$section_class = 'video-section alignwide';
				$container_class = 'video-container';
				
				switch ( $source ) {
					case 'youtube':
						$embed_raw = $video['youtube_video'] ?? '';
						// Handle ACF oembed field - can return HTML, array, or URL
						if ( is_array( $embed_raw ) ) {
							// If array, get HTML or URL
							$embed = $embed_raw['html'] ?? $embed_raw['url'] ?? '';
							$url = $embed_raw['url'] ?? '';
						} else {
							$embed = $embed_raw;
							// Extract URL from embed HTML if needed
							if ( $embed && preg_match( '/https?:\/\/[^\s<>"\']+/', $embed, $matches ) ) {
								$url = $matches[0];
							}
						}
						// Clean YouTube URL (remove ?feature=oembed parameter)
						if ( $url ) {
							$url = preg_replace( '/\?feature=oembed(&.*)?$/', '', $url );
							$url = preg_replace( '/\?feature=oembed&/', '?', $url );
						}
						if ( $embed && is_string( $embed ) ) {
							$embed = preg_replace( '/\?feature=oembed(&.*)?["\']/', '"', $embed );
							$embed = preg_replace( '/\?feature=oembed&/', '?', $embed );
						}
						break;
					case 'tiktok':
						$embed_raw = $video['tiktok_video'] ?? '';
						$section_class = 'tiktok-video-section alignwide';
						$container_class = 'tiktok-video-container';
						// Handle ACF oembed field
						if ( is_array( $embed_raw ) ) {
							$embed = $embed_raw['html'] ?? $embed_raw['url'] ?? '';
							$url = $embed_raw['url'] ?? '';
						} else {
							$embed = $embed_raw;
							if ( $embed && preg_match( '/https?:\/\/[^\s<>"\']+/', $embed, $matches ) ) {
								$url = $matches[0];
							}
						}
						break;
					case 'instagram':
						$embed_raw = $video['instagram_video'] ?? '';
						$section_class = 'instagram-video-section alignwide';
						$container_class = 'instagram-video-container';
						// Handle ACF oembed field
						if ( is_array( $embed_raw ) ) {
							$embed = $embed_raw['html'] ?? '';
							$url = $embed_raw['url'] ?? '';
						} else {
							$embed = $embed_raw;
							// Extract URL for Instagram embed (needed for blockquote)
							if ( $embed && preg_match( '/https?:\/\/[^\s<>"\']+/', $embed, $matches ) ) {
								$url = $matches[0];
							}
						}
						break;
					case 'bandcamp':
						$section_class = 'bandcamp-section';
						$container_class = 'bandcamp-container';
						// For bandcamp, construct iframe or use existing
						$bandcamp_song_id = $video['bandcamp_song_id'] ?? '';
						$bandcamp_album_id = $video['bandcamp_album_id'] ?? '';
						$bandcamp_iframe = $video['bandcamp_iframe'] ?? '';
						
						if ( $bandcamp_song_id && $bandcamp_album_id ) {
							$embed = '<iframe style="border: 0; width: 100%; height: 120px;" src="https://bandcamp.com/EmbeddedPlayer/size=large/bgcol=ffffff/linkcol=0687f5/tracklist=false/artwork=small/transparent=true/album=' . esc_attr( $bandcamp_album_id ) . '/track=' . esc_attr( $bandcamp_song_id ) . '" seamless></iframe>';
						} elseif ( $bandcamp_iframe ) {
							$embed = $bandcamp_iframe;
						}
						break;
				}
				
				// Skip if no embed content
				if ( empty( $embed ) ) {
					continue;
				}
				
				?>
				<div class="wp-block-group <?php echo esc_attr( $section_class ); ?>">
					<div class="<?php echo esc_attr( $container_class ); ?> has-text-align-center">
						<?php
						if ( $source === 'instagram' && $url ) {
							// Instagram embed - use blockquote format
							?>
							<blockquote
								class="instagram-media"
								data-instgrm-permalink="<?php echo esc_url( $url ); ?>"
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
							<?php
						} else {
							// YouTube, TikTok, Bandcamp - output embed HTML directly
							// ACF oembed fields return the full embed HTML (iframe, etc.)
							echo $embed;
						}
						?>
					</div>
				</div>
				<?php
			}
		} ?>
		
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
	
	<?php
		// only for Jesse Welles songs: stat cards visible, play history in accordion
		if ( $artist_name === 'Jesse Welles' ) {
			$song_id = get_the_ID();
			$nonce   = wp_create_nonce( 'jww_song_live_stats' );
			$ajax_url = admin_url( 'admin-ajax.php' );
			$stat_types = array( 'play_count', 'last_played', 'first_played', 'days_since' );
	?>
	<div class="song-stats-grid-container">
		<?php foreach ( $stat_types as $stat_type ) :
			$block_content = render_block( array(
				'blockName' => 'jww/song-live-stats',
				'attrs'     => array( 'statType' => $stat_type, 'songId' => $song_id ),
			) );
			if ( $block_content ) {
				echo $block_content;
			}
		endforeach; ?>
	</div>
	<div class="wp-block-group alignwide">
		<details class="song-live-performances-collapsible stat-card" id="song-live-performances">
			<summary class="song-live-performances-summary">
				<strong>Play history</strong>
			</summary>
			<div class="song-live-stats-lazy" data-song-id="<?php echo (int) $song_id; ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-ajax-url="<?php echo esc_url( $ajax_url ); ?>">
				<p class="song-live-stats-loading" aria-live="polite"><?php esc_html_e( 'Loading…', 'jww-theme' ); ?></p>
			</div>
		</details>
	</div>
	<script>
	(function() {
		var details = document.getElementById('song-live-performances');
		if (!details) return;
		var container = details.querySelector('.song-live-stats-lazy');
		if (!container || container.dataset.loaded === '1') return;
		function load() {
			if (container.dataset.loaded === '1') return;
			container.dataset.loaded = '1';
			var formData = new FormData();
			formData.append('action', 'jww_song_live_stats_fragment');
			formData.append('nonce', container.dataset.nonce);
			formData.append('song_id', container.dataset.songId);
			fetch(container.dataset.ajaxUrl, {
				method: 'POST',
				body: formData,
				credentials: 'same-origin'
			})
			.then(function(r) { return r.json(); })
			.then(function(data) {
				if (data.success && data.data && data.data.html) {
					container.innerHTML = data.data.html;
					if (window.jwwInitSortableTables) window.jwwInitSortableTables();
				} else {
					container.innerHTML = '<p class="song-live-stats-error"><?php echo esc_js( __( 'Could not load stats.', 'jww-theme' ) ); ?></p>';
				}
			})
			.catch(function() {
				container.innerHTML = '<p class="song-live-stats-error"><?php echo esc_js( __( 'Could not load stats.', 'jww-theme' ) ); ?></p>';
			});
		}
		details.addEventListener('toggle', function() {
			if (details.open && container.dataset.loaded !== '1') load();
		});
	})();
	</script>
	<?php
		}
	?>
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
