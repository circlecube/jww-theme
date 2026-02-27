<?php
/**
 * Archive template for Song post type
 * Uses configurable list (alphabetical, chronological, grid) or Live Stats table.
 * Filter section uses same card style as show archive; output split into template parts.
 *
 * @package JWW_Theme
 */

get_header();

$sort      = isset( $_GET['sort'] ) ? sanitize_text_field( $_GET['sort'] ) : 'date';
$song_type = isset( $_GET['song_type'] ) ? sanitize_text_field( $_GET['song_type'] ) : 'both';
$artist_id = isset( $_GET['artist_id'] ) ? sanitize_text_field( $_GET['artist_id'] ) : '7';
$display   = isset( $_GET['display'] ) ? sanitize_text_field( $_GET['display'] ) : 'grid';

$valid_sorts      = array( 'alpha', 'date' );
$valid_song_types = array( 'originals', 'covers', 'both' );
$valid_displays   = array( 'list', 'list-headers', 'grid', 'live-stats' );

if ( ! in_array( $sort, $valid_sorts, true ) ) {
	$sort = 'alpha';
}
if ( ! in_array( $song_type, $valid_song_types, true ) ) {
	$song_type = 'originals';
}
if ( ! in_array( $display, $valid_displays, true ) ) {
	$display = 'list';
}

$list_type = 'alphabetical';
if ( $display === 'grid' ) {
	$list_type = 'grid';
} elseif ( $sort === 'date' ) {
	$list_type = 'chronological';
}

$bands = get_posts( array(
	'post_type'      => 'band',
	'posts_per_page' => 10,
	'orderby'        => 'title',
	'order'          => 'ASC',
) );

$archive_url = get_post_type_archive_link( 'song' );

// Query vars for template parts
set_query_var( 'song_archive_display', $display );
set_query_var( 'song_archive_sort', $sort );
set_query_var( 'song_archive_song_type', $song_type );
set_query_var( 'song_archive_artist_id', $artist_id );
set_query_var( 'song_archive_bands', $bands );
set_query_var( 'song_archive_url', $archive_url );
set_query_var( 'artist_id', $artist_id );
set_query_var( 'show_headers', $display === 'list-headers' );
set_query_var( 'song_type', $song_type );
set_query_var( 'sort', $sort );

$align_classes = ( $display === 'grid' || $display === 'live-stats' ) ? 'alignfull' : 'is-layout-constrained';
?>

<main class="wp-block-group songs-template song-archive">
	<div class="wp-block-group is-layout-flow wp-block-group-is-layout-flow is-layout-constrained" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);">
		<h1 class="wp-block-post-title alignwide has-xxx-large-font-size"><?php esc_html_e( 'Songs', 'jww-theme' ); ?></h1>

		<?php get_template_part( 'template-parts/song-archive-filters' ); ?>

		<div class="song-list-output <?php echo esc_attr( $align_classes ); ?>" id="song-list-output">
			<?php
			if ( $display === 'live-stats' ) {
				get_template_part( 'template-parts/song-archive-live-stats' );
			} else {
				$template_part_map = array(
					'alphabetical'  => 'song-list-alphabetical',
					'chronological' => 'song-list-chronological',
					'grid'          => 'song-list-grid',
				);
				$template_part = isset( $template_part_map[ $list_type ] ) ? $template_part_map[ $list_type ] : 'song-list-alphabetical';
				get_template_part( 'template-parts/' . $template_part );
			}
			?>
		</div>
	</div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
	var form = document.getElementById('song-list-filters');
	if (form) {
		var autoSubmitElements = form.querySelectorAll('.auto-submit');
		autoSubmitElements.forEach(function(el) {
			el.addEventListener('change', function() { form.submit(); });
		});
	}
});
</script>

<?php get_footer(); ?>
