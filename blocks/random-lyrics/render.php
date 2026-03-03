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
		parts.push('<blockquote class="random-lyrics-quote">');
		parts.push('<p class="random-lyrics-text">' + escapeHtml(item.lyrics_line) + '</p>');
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
	function escapeHtml(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
	function escapeAttr(s) { return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

	document.addEventListener('DOMContentLoaded', function() {
		document.querySelectorAll('.wp-block-jww-theme-random-lyrics.refresh-on-load').forEach(function(block) {
			var btn = block.querySelector('.random-lyrics-refresh-btn');
			if (!btn) return;
			btn.addEventListener('click', function() {
				var container = block.querySelector('.random-lyrics-quote-container');
				var restUrl = block.dataset.restUrl || block.getAttribute('data-rest-url');
				var showTitle = block.dataset.showTitle === 'true';
				var showArtist = block.dataset.showArtist === 'true';
				var originalHtml = container.innerHTML;
				container.innerHTML = '<p class="random-lyrics-loading"><?php echo esc_js( __( 'Loading new lyrics...', 'jww-theme' ) ); ?></p>';
				btn.disabled = true;
				var icon = block.querySelector('.refresh-icon');
				if (icon) icon.classList.add('fa-spin');
				fetch(restUrl)
					.then(function(r) { return r.json(); })
					.then(function(item) {
						container.innerHTML = buildQuoteHtml(item, showTitle, showArtist);
					})
					.catch(function() { container.innerHTML = originalHtml; })
					.finally(function() {
						btn.disabled = false;
						if (icon) icon.classList.remove('fa-spin');
					});
			});
		});
	});
})();
</script>
<?php endif; ?>
