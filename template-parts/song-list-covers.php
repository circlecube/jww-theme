<?php
/**
 * Template part for displaying a cover songs list
 * 
 * @package jww-theme
 */

// Get artist_id and show_headers from query vars (passed from block or template)
$artist_id = get_query_var('artist_id', '');
$show_headers = get_query_var('show_headers', true);
?>

<h2 class="wp-block-heading song-list-heading" id="covers" name="covers">Cover Songs</h2>
	<?php
	$query_args = array(
		'post_type'      => 'song',
		'posts_per_page' => -1,
		'orderby'        => array(
			'meta_value' => 'ASC',
			'title'      => 'ASC'
		),
		'meta_key'       => 'attribution',
		'tax_query'      => array(
			array(
				'taxonomy' => 'category',
				'field'     => 'slug',
				'terms'    => 'cover'
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
		$current_artist = '';
		?>
		<div class="cover-songs-list">
		<?php
		while ($song_query->have_posts()) {
			$song_query->the_post();
			$attribution = get_field('attribution');
			$artist_name = $attribution ? $attribution : 'Unknown Artist';
			
			// Check if we need to add a new artist header
			if ($artist_name !== $current_artist) {
				// Close previous artist group if it exists
				if ($current_artist !== '') {
					echo '</ul>';
					if ($show_headers) {
						echo '</div>';
					}
				}
				
				// Add artist header (only if show_headers is true)
				if ($show_headers) {
					echo '<div class="artist-section">';
					echo '<h3 class="artist-heading" id="artist-' . sanitize_title($artist_name) . '">' . esc_html($artist_name) . '</h3>';
				}
				echo '<ul class="wp-block-list song-list">';
				
				$current_artist = $artist_name;
			}
			?>
			<li class="wp-block-list-item">
                <h4 class="wp-block-heading">
                    <a href="<?php the_permalink(); ?>">
                        <?php the_title(); ?>
						<!-- <span class="song-meta song-attribution">(<?php the_field('attribution'); ?>)</span> -->
                    </a>
                </h4>
            </li>
			<?php
		}
		// Close the last artist group
		if ($current_artist !== '') {
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