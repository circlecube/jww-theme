<?php
/**
 * Template Name: Song Grid
 * Template Post Type: page
 * 
 * Template for displaying a grid of songs
 *
 * @package jww-theme
 * @subpackage jww-theme
 * @since 1.0.0
 * @version 1.0.0
 */

get_header();
?>

<main class="wp-block-group is-layout-flow wp-block-group-is-layout-flow song-grid-tempalte">
	<div class="wp-block-group">
	
	<?php
	// Get artist ID from page ACF field or URL parameter
	$artist_id = get_field('artist');
	if (!$artist_id && isset($_GET['artist'])) {
		$artist_id = intval($_GET['artist']);
	}
	
	// Handle artist field if it's an object/array (ACF relationship field)
	if (is_object($artist_id) || is_array($artist_id)) {
		// If it's an array of objects or single object, get the ID
		if (is_array($artist_id)) {
			$artist_id = !empty($artist_id) ? (is_object($artist_id[0]) ? $artist_id[0]->ID : $artist_id[0]) : null;
		} else {
			$artist_id = $artist_id->ID ?? null;
		}
	}
	
	// Build query arguments
	$song_query_args = array(
		'post_type'      => 'song',
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'DESC',
		// 'tax_query'      => array(
		// 	array(
		// 		'taxonomy' => 'category',
		// 		'field'     => 'slug',
		// 		'terms'    => 'original'
		// 	)
		// )
	);
	
	// Add artist filter if artist ID is specified
	if ($artist_id) {
		// ACF relationship fields store data as serialized arrays or comma-separated IDs
		// Use meta_query with LIKE to find the artist ID within the stored value
		$song_query_args['meta_query'] = array(
			array(
				'key'     => 'artist',
				'value'   => '"' . intval($artist_id) . '"', // Search for serialized format
				'compare' => 'LIKE'
			)
		);
	}
	
	$song_query = new WP_Query($song_query_args);
	
	if ($song_query->have_posts()) { 
		?>
		
		<div class="song-grid">
		<?php
		while ($song_query->have_posts()) {
			$song_query->the_post();

			$classes = array( 'song-grid-item' );
			if (has_post_thumbnail()) {
				$classes[] = 'has-thumbnail';
			} else {
				$classes[] = 'no-thumbnail';
			}
			?>
			<a class="<?php echo implode(' ', $classes); ?>"
				href="<?php the_permalink(); ?>"
				title="<?php the_title(); ?>"
			>
				<?php the_post_thumbnail('medium'); ?>
				<span class="song-grid-item-content">
					<span class="song-grid-item-title"><?php the_title(); ?></span>
					<span class="song-grid-item-date"><?php echo get_the_date(); ?></span>
				</span>
			</a>
			<?php
		}
		wp_reset_postdata();
		?>
		</div>
		<?php
	} ?>
	</div>
</main>

<?php
get_footer();
?>