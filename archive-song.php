<?php
/**
 * Archive template for Song post type
 * Uses the same configurable list as the page template, with optional Live Stats table.
 *
 * @package jww-theme
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

$song_type_for_template = $song_type;
$bands = get_posts( array(
	'post_type'      => 'band',
	'posts_per_page' => 10,
	'orderby'        => 'title',
	'order'          => 'ASC',
) );

$align_classes = ( $display === 'grid' || $display === 'live-stats' ) ? 'alignfull' : 'is-layout-constrained';
$archive_url   = get_post_type_archive_link( 'song' );
?>

<main class="wp-block-group songs-template song-archive">
	<div class="wp-block-group is-layout-flow wp-block-group-is-layout-flow is-layout-constrained">

		<div class="song-list-controls alignwide">
			<form method="get" action="<?php echo esc_url( $archive_url ?: '' ); ?>" id="song-list-filters">
				<div class="song-list-filters">

					<?php if ( $display !== 'live-stats' ) : ?>
					<div class="filter-section">
						<label for="artist_id" class="filter-label">Band</label>
						<select name="artist_id" id="artist_id" class="filter-select auto-submit">
							<option value="all">All</option>
							<?php foreach ( $bands as $band ) : ?>
								<option value="<?php echo esc_attr( $band->ID ); ?>" <?php selected( $artist_id, (string) $band->ID ); ?>>
									<?php echo esc_html( $band->post_title ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<?php endif; ?>

					<div class="filter-section">
						<label for="display" class="filter-label">Display</label>
						<select name="display" id="display" class="filter-select auto-submit">
							<option value="list" <?php selected( $display, 'list' ); ?>>Simple List</option>
							<option value="list-headers" <?php selected( $display, 'list-headers' ); ?>>List with Headers</option>
							<option value="grid" <?php selected( $display, 'grid' ); ?>>Grid</option>
							<option value="live-stats" <?php selected( $display, 'live-stats' ); ?>>Live Stats</option>
						</select>
					</div>

					<?php if ( $display !== 'live-stats' ) : ?>
					<div class="filter-section">
						<label for="sort" class="filter-label">Sort By</label>
						<select name="sort" id="sort" class="filter-select auto-submit">
							<option value="alpha" <?php selected( $sort, 'alpha' ); ?>>Name (A-Z)</option>
							<option value="date" <?php selected( $sort, 'date' ); ?>>Date (Newest to Oldest)</option>
						</select>
					</div>

					<div class="filter-section">
						<label for="song_type" class="filter-label">Song Type</label>
						<select name="song_type" id="song_type" class="filter-select auto-submit">
							<option value="originals" <?php selected( $song_type, 'originals' ); ?>>Originals</option>
							<option value="covers" <?php selected( $song_type, 'covers' ); ?>>Covers</option>
							<option value="both" <?php selected( $song_type, 'both' ); ?>>Originals & Covers</option>
						</select>
					</div>
					<?php endif; ?>

				</div>
			</form>
		</div>

		<div class="song-list-output <?php echo esc_attr( $align_classes ); ?>" id="song-list-output">
			<?php
			if ( $display === 'live-stats' ) {
				// Render the song-stats-table block (all songs, sortable live stats table)
				$block_content = render_block( array(
					'blockName' => 'jww/song-stats-table',
					'attrs'     => array(),
				) );
				if ( $block_content ) {
					echo $block_content;
				}
			} else {
				$template_part_map = array(
					'alphabetical'  => 'song-list-alphabetical',
					'chronological' => 'song-list-chronological',
					'grid'          => 'song-list-grid',
				);
				$template_part = isset( $template_part_map[ $list_type ] ) ? $template_part_map[ $list_type ] : 'song-list-alphabetical';

				set_query_var( 'artist_id', $artist_id );
				set_query_var( 'show_headers', $display === 'list-headers' );
				set_query_var( 'song_type', $song_type_for_template );
				set_query_var( 'sort', $sort );

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

<?php
get_footer();
