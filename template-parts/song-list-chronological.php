<?php
/**
 * Template part for displaying a chronological song list
 * 
 * @package jww-theme
 */

// Get artist_id and show_headers from query vars (passed from block or template)
$artist_id = get_query_var('artist_id', '');
$show_headers = get_query_var('show_headers', true);
?>

<h2 class="wp-block-heading song-list-heading" id="chronological" name="chronological">Chronological</h2>
	<?php
	$query_args = array(
		'post_type'      => 'song',
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'tax_query'      => array(
			array(
				'taxonomy' => 'category',
				'field'     => 'slug',
				'terms'    => 'original'
			)
		)
	);

	// Add artist filter if artist_id is provided
	if (!empty($artist_id)) {
		$artist_id = intval($artist_id);
		// ACF relationship fields store data as serialized arrays
		// Check for the ID in serialized format: "i:ID;" or "s:2:\"ID\";"
		$query_args['meta_query'] = array(
			array(
				'key'     => 'artist',
				'value'   => '"' . $artist_id . '"',
				'compare' => 'LIKE'
			)
		);
	}

	$song_query = new WP_Query($query_args);
	
	if ($song_query->have_posts()) { 
		$current_month = '';
		?>
		
		<div class="chronological-song-list">
		<?php
		while ($song_query->have_posts()) {
			$song_query->the_post();
			$post_date = get_the_date('Y-m');
			$month_display = get_the_date('F Y');
			
			// Check if we need to add a new month header
			if ($post_date !== $current_month) {
				// Close previous month group if it exists
				if ($current_month !== '') {
					echo '</ul>';
					if ($show_headers) {
						echo '</div>';
					}
				}
				
				// Add month header (only if show_headers is true)
				if ($show_headers) {
					echo '<div class="month-section">';
					echo '<h3 class="month-heading" id="month-' . $post_date . '">' . $month_display . '</h3>';
				}
				echo '<ul class="wp-block-list song-list">';
				
				$current_month = $post_date;
			}
			?>
			<li class="wp-block-list-item">
				<h4 class="wp-block-heading">
					<a href="<?php the_permalink(); ?>">
						<?php the_title(); ?>
						<span class="song-meta song-date">(<?php echo get_the_date(); ?>)</span>
					</a>
				</h4>
			</li>
			<?php
		}
		// Close the last month group
		if ($current_month !== '') {
			echo '</ul>';
			if ($show_headers) {
				echo '</div>';
			}
		}
		?>
		</div>
		<?php
	}
	wp_reset_postdata();
	?>