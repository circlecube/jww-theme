<?php
/**
 * Template Name: Configurable Song List
 * Template Post Type: page
 * 
 * Template for displaying a configurable list of songs
 * Front-end users can change layout, filters, and display options
 *
 * @package jww-theme
 * @subpackage jww-theme
 * @since 1.0.0
 * @version 1.0.0
 */

get_header();

// Get initial values from URL parameters or defaults
$sort = isset($_GET['sort']) ? sanitize_text_field($_GET['sort']) : 'date';
$song_type = isset($_GET['song_type']) ? sanitize_text_field($_GET['song_type']) : 'both';
$artist_id = isset($_GET['artist_id']) ? sanitize_text_field($_GET['artist_id']) : '7';
$display = isset($_GET['display']) ? sanitize_text_field($_GET['display']) : 'grid';

// Validate inputs
$valid_sorts = array('alpha', 'date');
if (!in_array($sort, $valid_sorts)) {
	$sort = 'alpha';
}

$valid_song_types = array('originals', 'covers', 'both');
if (!in_array($song_type, $valid_song_types)) {
	$song_type = 'originals';
}

$valid_displays = array('list', 'list-headers', 'grid');
if (!in_array($display, $valid_displays)) {
	$display = 'list';
}

// Determine list_type based on display and sort (song_type is handled via tax_query in template parts)
$list_type = 'alphabetical'; // default
if ($display === 'grid') {
	$list_type = 'grid';
} elseif ($sort === 'date') {
	$list_type = 'chronological';
} else {
	$list_type = 'alphabetical';
}

// Pass song_type to template parts (they'll handle tax_query)
$song_type_for_template = $song_type; // originals, covers, or both

// Get all bands for the filter dropdown
$bands = get_posts(array(
	'post_type' => 'band',
	'posts_per_page' => 10,
	'orderby' => 'title',
	'order' => 'ASC'
));

// align classes for content
if ($display === 'grid') {
	$align_classes = 'alignfull';
} else {
	$align_classes = 'is-layout-constrained';
}
?>

<main class="wp-block-group songs-template">
	<div class="wp-block-group is-layout-flow wp-block-group-is-layout-flow is-layout-constrained">
		
		<!-- Song List Controls -->
		<div class="song-list-controls alignwide">
			<form method="get" action="" id="song-list-filters">
				<div class="song-list-filters">
					
					<!-- Band Filter -->
					<div class="filter-section">
						<label for="artist_id" class="filter-label">Band</label>
						<select name="artist_id" id="artist_id" class="filter-select auto-submit">
							<option value="all">All</option>
							<?php foreach ($bands as $band) : ?>
								<option value="<?php echo esc_attr($band->ID); ?>" <?php selected($artist_id, $band->ID); ?>>
									<?php echo esc_html($band->post_title); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					
					<!-- Display -->
					<div class="filter-section">
						<label for="display" class="filter-label">Display</label>
						<select name="display" id="display" class="filter-select auto-submit">
							<option value="list" <?php selected($display, 'list'); ?>>Simple List</option>
							<option value="list-headers" <?php selected($display, 'list-headers'); ?>>List with Headers</option>
							<option value="grid" <?php selected($display, 'grid'); ?>>Grid</option>
						</select>
					</div>

					<!-- Sorting Options -->
					<div class="filter-section">
						<label for="sort" class="filter-label">Sort By</label>
						<select name="sort" id="sort" class="filter-select auto-submit">
							<option value="alpha" <?php selected($sort, 'alpha'); ?>>Name (A-Z)</option>
							<option value="date" <?php selected($sort, 'date'); ?>>Date (Newest to Oldest)</option>
						</select>
					</div>
					
					<!-- Song Type Options -->
					<div class="filter-section">
						<label for="song_type" class="filter-label">Song Type</label>
						<select name="song_type" id="song_type" class="filter-select auto-submit">
							<option value="originals" <?php selected($song_type, 'originals'); ?>>Originals</option>
							<option value="covers" <?php selected($song_type, 'covers'); ?>>Covers</option>
							<option value="both" <?php selected($song_type, 'both'); ?>>Originals & Covers</option>
						</select>
					</div>

				</div>
			</form>
		</div>
		
		<!-- Song List Output -->
		<div class="song-list-output <?php echo $align_classes; ?>" id="song-list-output">
			<?php
				// Determine which template part to load based on list type
				$template_part_map = array(
					'alphabetical' => 'song-list-alphabetical',
					'chronological' => 'song-list-chronological',
					'grid' => 'song-list-grid'
				);
				
				$template_part = isset($template_part_map[$list_type]) ?
									$template_part_map[$list_type] :
									'song-list-alphabetical';
				
				// Pass configuration to the template part via query vars
				set_query_var('artist_id', $artist_id);
				set_query_var('show_headers', $display === 'list-headers' ? true : false);
				set_query_var('song_type', $song_type_for_template);
				set_query_var('sort', $sort);
					
				// Load the appropriate template part
				get_template_part('template-parts/' . $template_part);
			?>
		</div>
	
	</div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
	const form = document.getElementById('song-list-filters');
	const autoSubmitElements = form.querySelectorAll('.auto-submit');
	
	// Auto-submit on change for all form elements with auto-submit class
	autoSubmitElements.forEach(function(element) {
		element.addEventListener('change', function() {
			form.submit();
		});
	});
});
</script>

<?php
get_footer();
?>

