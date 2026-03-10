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
		// Share buttons: directly below videos
		if ( function_exists( 'jww_render_share_buttons' ) ) {
			$share_url  = get_permalink();
			$share_text = function_exists( 'jww_share_song_default_text' ) ? jww_share_song_default_text( get_the_ID() ) : get_the_title();
			echo '<div class="jww-share-song-wrap aligncenter has-global-padding" style="margin-top:var(--wp--preset--spacing--40);margin-bottom:0">';
			echo jww_render_share_buttons( 
				$share_url,
				$share_text,
				array( 'x', 'facebook', 'mastodon', 'bluesky', 'threads', 'linkedin', 'reddit', 'pinterest' ),
				'song',
				'Share'
			);
			echo '</div>';
		}
		?>
	</div>
</main>

<!-- Lyrics Section (selectable text shows floating share) -->
<div id="jww-lyrics-section" class="wp-block-group has-accent-6-background-color has-background is-layout-constrained has-global-padding jww-lyrics-selectable-wrap" style="border-style:none;border-width:0px" data-share-url="<?php echo esc_url( get_permalink() ); ?>" data-share-context="<?php echo esc_attr( function_exists( 'jww_share_song_default_text' ) ? jww_share_song_default_text( get_the_ID() ) : get_the_title() ); ?>">
	<div class="wp-block-post-content">
	<h2 class="wp-block-heading">Lyrics</h2>

	<?php
	$lyrics = get_field( 'lyrics' );
	$chord_sheet = get_field( 'chord_sheet' );
	$tabs = get_field( 'tabs' );
	$chord_sheet = is_string( $chord_sheet ) ? trim( $chord_sheet ) : '';
	$tabs = is_string( $tabs ) ? trim( $tabs ) : '';
	$has_chord_or_tab = $chord_sheet !== '' || $tabs !== '';

	if ( $has_chord_or_tab ) :
		// Guitar toggle, Transpose, Capo – only when song has chord sheet or tabs
		$guitar_icon_url = get_stylesheet_directory_uri() . '/assets/guitar.svg';
		$capo_default = get_field( 'capo' );
		if ( ! is_numeric( $capo_default ) || (int) $capo_default < 0 || (int) $capo_default > 12 ) {
			$capo_default = 0;
		} else {
			$capo_default = (int) $capo_default;
		}
		?>
		<div id="jww-chords-controls" class="jww-chords-controls">
			<button type="button" id="jww-show-chords-toggle" class="jww-show-chords-toggle" aria-pressed="true" title="<?php esc_attr_e( 'Show or hide guitar chords and tabs', 'jww-theme' ); ?>">
				<img src="<?php echo esc_url( $guitar_icon_url ); ?>" al	t="" class="jww-guitar-icon" width="20" height="20" aria-hidden="true">
				<span class="jww-show-chords-label"><?php esc_html_e( 'Hide guitar chords', 'jww-theme' ); ?></span>
			</button>
			<div class="jww-transpose-capo">
				<div class="jww-stepper-wrap">
					<label class="jww-stepper-label"><?php esc_html_e( 'Transpose:', 'jww-theme' ); ?></label>
					<div class="jww-stepper" role="group" aria-label="<?php esc_attr_e( 'Transpose by semitones', 'jww-theme' ); ?>">
						<button type="button" class="jww-stepper-btn jww-stepper-minus" id="jww-transpose-minus" aria-label="<?php esc_attr_e( 'Decrease transpose', 'jww-theme' ); ?>">−</button>
						<span class="jww-stepper-value" id="jww-transpose-display" aria-live="polite">0</span>
						<button type="button" class="jww-stepper-btn jww-stepper-plus" id="jww-transpose-plus" aria-label="<?php esc_attr_e( 'Increase transpose', 'jww-theme' ); ?>">+</button>
					</div>
					<input type="hidden" id="jww-transpose" name="jww-transpose" value="0">
				</div>
				<div class="jww-stepper-wrap">
					<label class="jww-stepper-label"><?php esc_html_e( 'Capo:', 'jww-theme' ); ?></label>
					<div class="jww-stepper" role="group" aria-label="<?php esc_attr_e( 'Capo fret', 'jww-theme' ); ?>">
						<button type="button" class="jww-stepper-btn jww-stepper-minus" id="jww-capo-minus" aria-label="<?php esc_attr_e( 'Decrease capo', 'jww-theme' ); ?>">−</button>
						<span class="jww-stepper-value" id="jww-capo-display" aria-live="polite"><?php echo $capo_default === 0 ? esc_html__( 'None', 'jww-theme' ) : (string) $capo_default; ?></span>
						<button type="button" class="jww-stepper-btn jww-stepper-plus" id="jww-capo-plus" aria-label="<?php esc_attr_e( 'Increase capo', 'jww-theme' ); ?>">+</button>
					</div>
					<input type="hidden" id="jww-capo" name="jww-capo" value="<?php echo (int) $capo_default; ?>">
				</div>
			</div>
		</div>
		<?php if ( $lyrics ) : ?>
		<div id="jww-lyrics-plain-wrapper" class="jww-lyrics-plain-wrapper">
			<div class="lyrics-content"><?php echo wp_kses_post( $lyrics ); ?></div>
		</div>
		<?php endif; ?>
		<?php if ( $chord_sheet !== '' ) : ?>
		<div id="jww-chord-diagrams-section" class="jww-chord-diagrams-section">
			<h3 class="wp-block-heading"><?php esc_html_e( 'Chord diagrams', 'jww-theme' ); ?></h3>
			<div id="jww-chord-diagrams-container" class="jww-chord-diagrams-container"></div>
		</div>
		<div id="jww-chord-sheet-wrapper" class="jww-chord-sheet-wrapper">
			<div id="jww-chord-sheet-content" class="jww-chord-sheet-content"></div>
		</div>
		<?php endif; ?>
		<?php if ( $tabs !== '' ) : ?>
		<div id="jww-tabs-section" class="jww-tabs-section">
			<h3 class="wp-block-heading"><?php esc_html_e( 'Tabs', 'jww-theme' ); ?></h3>
			<div id="jww-tabs-container" class="jww-tabs-container"></div>
		</div>
		<?php endif; ?>
	<?php endif; ?>
	<?php if ( ! $has_chord_or_tab && $lyrics ) : ?>
		<div class="lyrics-content">
			<?php echo wp_kses_post( $lyrics ); ?>
		</div>
	<?php elseif ( ! $has_chord_or_tab ) : ?>
		<p>Sorry, no lyrics yet. Have some to suggest? Let us know in the comments!</p>
	<?php endif; ?>

	</div>
</div>

<!-- Annotations Section -->
<div class="wp-block-group is-style-default has-base-background-color has-background is-layout-constrained has-global-padding" style="margin-top:0;margin-bottom:0">
	<div class="wp-block-post-content alignwide">
		<h3 class="wp-block-heading">Annotations and Notes on Lyrics</h3>
		<?php $annotations = get_field( 'lyric_annotations' ); ?>
		<?php if ( $annotations ) { ?>
			<div class="annotations-content has-medium-font-size">
				<?php echo wp_kses_post( $annotations ); ?>
			</div>
		<?php } else { ?>
			<div class="annotations-content annotations-no-content has-medium-font-size">
				<p>Sorry, no lyrics annotations or notes yet.</p>
				<p>Have some to suggest? Let us know in the comments!</p>
			</div>
		<?php } ?>
	</div>
</div>
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
			set_query_var( 'song_id', $song_id );
			get_template_part( 'template-parts/song-live-stats-cards' );
	?>
	<div class="wp-block-group alignwide">
		<div class="song-live-stats-lazy" id="song-live-performances" data-song-id="<?php echo (int) $song_id; ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-ajax-url="<?php echo esc_url( $ajax_url ); ?>">
			<p class="song-live-stats-loading" aria-live="polite"><?php esc_html_e( 'Loading…', 'jww-theme' ); ?></p>
		</div>
	</div>
	<script>
	(function() {
		var container = document.getElementById('song-live-performances');
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
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', load);
		} else {
			load();
		}
	})();
	</script>
	<?php
		}
	?>
</div>

<!-- Music service links -->
<div class="wp-block-group alignwide has-global-padding jww-song-listen-wrap" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50)">
	<?php
	$song_title = get_the_title() ?? '';
	$song_links = get_field( 'song_links' );
	?>
	<div class="jww-song-listen-section">
		<?php echo get_song_music_service_links( $song_title, $artist_name, '', array(), $song_links, 'Listen' ); ?>
	</div>
</div>

<!-- Navigation -->
<div class="nav-link-container wp-block-group alignwide">
		<?php
		the_post_navigation(array(
			'prev_text' => '<span class="nav-subtitle">' . esc_html__('Previous Song:', 'jww-theme') . '</span> <span class="nav-title">%title</span>',
			'next_text' => '<span class="nav-subtitle">' . esc_html__('Next Song:', 'jww-theme') . '</span> <span class="nav-title">%title</span>',
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
