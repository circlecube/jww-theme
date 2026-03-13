<?php
/**
 * Random Lyrics Block Render Template
 *
 * Uses jww_get_random_lyrics_data() from functions-acf.php. Refresh fetches GET /wp-json/jww/v1/lyrics/random.
 */

$show_song_title = $attributes['showSongTitle'] ?? true;
$show_artist     = $attributes['showArtist'] ?? true;
$refresh_on_load = $attributes['refreshOnLoad'] ?? true;

$data = function_exists( 'jww_get_random_lyrics_data' ) ? jww_get_random_lyrics_data() : null;

if ( $data === null ) {
	echo '<div class="wp-block-jww-theme-random-lyrics">';
	echo '<p class="random-lyrics-placeholder">No songs with lyrics found.</p>';
	echo '</div>';
	return;
}

$refresh_class = $refresh_on_load ? 'refresh-on-load' : '';
$rest_url      = rest_url( 'jww/v1/lyrics/random' );

$share_url  = $data['song_link'];
$share_text = function_exists( 'jww_share_song_default_text' ) ? jww_share_song_default_text( $data['song_id'] ) : ( $data['song_title'] . ' – ' . $data['artist_name'] );
?>

<div class="wp-block-jww-theme-random-lyrics <?php echo esc_attr( $refresh_class ); ?>"
     data-show-title="<?php echo esc_attr( $show_song_title ? 'true' : 'false' ); ?>"
     data-show-artist="<?php echo esc_attr( $show_artist ? 'true' : 'false' ); ?>"
     data-rest-url="<?php echo esc_url( $rest_url ); ?>">
	<?php
	$args = array(
		'song_id'         => $data['song_id'],
		'lyrics_line'     => $data['lyrics_line'],
		'show_song_title' => $show_song_title,
		'show_artist'     => $show_artist,
	);
	echo '<div class="random-lyrics-quote-container">';
	get_template_part( 'template-parts/random-lyrics-quote', null, $args );
	echo '</div>';
	?>
	<?php if ( function_exists( 'jww_render_share_buttons' ) ) : ?>
	<?php
	$share_platforms = array( 'facebook', 'mastodon', 'bluesky', 'reddit', 'threads', 'pinterest', 'x' );
	?>
	<div class="random-lyrics-share-wrap jww-share-buttons-wrap" data-share-url="<?php echo esc_url( $share_url ); ?>" data-share-text="<?php echo esc_attr( $share_text ); ?>">
		<details class="random-lyrics-share-details">
			<summary class="random-lyrics-share-toggle" aria-label="<?php esc_attr_e( 'Share', 'jww-theme' ); ?>">
				<span class="random-lyrics-share-toggle-icon fas fa-share-alt" aria-hidden="true"></span>
				<span class="random-lyrics-share-toggle-text"><?php esc_html_e( 'Share', 'jww-theme' ); ?></span>
			</summary>
			<div class="random-lyrics-share-panel">
				<?php echo jww_render_share_buttons( $share_url, $share_text, $share_platforms, 'song', '' ); ?>
			</div>
		</details>
	</div>
	<?php endif; ?>
	<?php if ( $refresh_on_load ) : ?>
	<div class="random-lyrics-controls">
		<button type="button" class="random-lyrics-refresh-btn" aria-label="<?php esc_attr_e( 'Get new random lyrics', 'jww-theme' ); ?>">
			<span class="refresh-icon"><span class="fas fa-refresh" data-fa-animation-iteration-count="1"></span></span>
			<span class="refresh-text"><?php esc_html_e( 'New Lyrics', 'jww-theme' ); ?></span>
		</button>
	</div>
	<?php endif; ?>
</div>

<?php if ( $refresh_on_load ) : ?>
<script>
(function() {
		function buildQuoteHtml(item, showTitle, showArtist) {
		var parts = [];
		function escapeHtml(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
		function escapeAttr(s) { return String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
		parts.push('<blockquote class="random-lyrics-quote">');
		var text = (item.lyrics_line != null ? String(item.lyrics_line) : '');
		var textHtml = text.split('\n').map(function(line) { return escapeHtml(line); }).join('<br>');
		parts.push('<p class="random-lyrics-text">' + textHtml + '</p>');
		if (showTitle || showArtist) {
			parts.push('<cite class="random-lyrics-attribution">');
			if (showArtist) parts.push('<span class="random-lyrics-artist">' + escapeHtml(item.artist_name) + '</span>');
			if (showTitle) {
				parts.push(showArtist ? ' — ' : '');
				parts.push('<span class="random-lyrics-song"><a href="' + escapeAttr(item.song_link) + '">' + escapeHtml(item.song_title) + '</a></span>');
			}
			parts.push('</cite>');
		}
		parts.push('</blockquote>');
		return parts.join('');
	}

	function buildShareUrl(platform, url, text) {
		var enc = encodeURIComponent;
		url = url || '';
		text = text || '';
		var combined = text ? text + '\n' + url : url;
		switch (platform) {
			case 'x': return 'https://x.com/intent/tweet?url=' + enc(url) + (text ? '&text=' + enc(text) : '');
			case 'facebook': return 'https://www.facebook.com/sharer/sharer.php?u=' + enc(url);
			case 'mastodon': return 'https://mastodonshare.com/?url=' + enc(url) + (text ? '&text=' + enc(text) : '');
			case 'bluesky': return 'https://bsky.app/intent/compose?text=' + enc(combined);
			case 'threads': return 'https://www.threads.net/intent/post?url=' + enc(url) + (text ? '&text=' + enc(text) : '');
			case 'linkedin': return 'https://www.linkedin.com/sharing/share-offsite/?url=' + enc(url);
			case 'reddit': return 'https://www.reddit.com/submit?url=' + enc(url) + (text ? '&title=' + enc(text) : '');
			case 'pinterest': return 'https://www.pinterest.com/pin/create/button/?url=' + enc(url) + (text ? '&description=' + enc(text) : '');
			default: return url;
		}
	}

	function updateShareWrap(block, url, text) {
		var wrap = block.querySelector('.random-lyrics-share-wrap');
		if (!wrap) return;
		wrap.dataset.shareUrl = url;
		wrap.dataset.shareText = text;
		var btns = wrap.querySelectorAll('.jww-share-btn');
		btns.forEach(function(a) {
			var m = a.className.match(/jww-share-btn--(\w+)/);
			if (m) a.href = buildShareUrl(m[1], url, text);
		});
	}

	document.addEventListener('DOMContentLoaded', function() {
		// Event delegation: one listener on document so refresh works every time (button is never replaced).
		document.addEventListener('click', function(e) {
			var btn = e.target && e.target.closest && e.target.closest('.random-lyrics-refresh-btn');
			if (!btn) return;
			var block = btn.closest('.wp-block-jww-theme-random-lyrics.refresh-on-load');
			if (!block) return;
			var container = block.querySelector('.random-lyrics-quote-container');
			var restUrl = block.dataset.restUrl || block.getAttribute('data-rest-url');
			var showTitle = block.dataset.showTitle === 'true';
			var showArtist = block.dataset.showArtist === 'true';
			if (!container || !restUrl) return;
			var originalHtml = container.innerHTML;
			container.innerHTML = '<p class="random-lyrics-loading"><?php echo esc_js( __( 'Loading new lyrics...', 'jww-theme' ) ); ?></p>';
			btn.disabled = true;
			var icon = block.querySelector('.refresh-icon');
			if (icon) icon.classList.add('fa-spin');
			var url = restUrl + (restUrl.indexOf('?') >= 0 ? '&' : '?') + '_=' + Date.now();
			fetch(url, { cache: 'no-store' })
				.then(function(r) { return r.json(); })
				.then(function(item) {
					container.innerHTML = buildQuoteHtml(item, showTitle, showArtist);
					var shareText = (item.song_title || '') + (item.artist_name ? ' – ' + item.artist_name : '');
					updateShareWrap(block, item.song_link || '', shareText);
				})
				.catch(function() { container.innerHTML = originalHtml; })
				.finally(function() {
					btn.disabled = false;
					if (icon) icon.classList.remove('fa-spin');
				});
		});
	});
})();
</script>
<?php endif; ?>
