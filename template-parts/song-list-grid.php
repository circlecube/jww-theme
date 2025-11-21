<?php
/**
 * Template part for displaying a grid of songs
 * 
 * @package jww-theme
 */

// Get artist_id, song_type, and sort from query vars (passed from block or template)
$artist_id = get_query_var('artist_id', '');
$song_type = get_query_var('song_type', 'originals');
$sort = get_query_var('sort', 'date');

// Build query arguments
$query_args = array(
	'post_type'      => 'song',
	'posts_per_page' => -1
);

// Set orderby based on sort parameter
if ($sort === 'alpha') {
	$query_args['orderby'] = 'title';
	$query_args['order'] = 'ASC';
} else {
	$query_args['orderby'] = 'date';
	$query_args['order'] = 'DESC';
}

// Add tax_query based on song_type
if ($song_type === 'covers') {
	$query_args['tax_query'] = array(
		array(
			'taxonomy' => 'category',
			'field'     => 'slug',
			'terms'    => 'cover'
		)
	);
} elseif ($song_type === 'originals') {
	$query_args['tax_query'] = array(
		array(
			'taxonomy' => 'category',
			'field'     => 'slug',
			'terms'    => 'original'
		)
	);
} elseif ($song_type === 'both') {
	$query_args['tax_query'] = array(
		array(
			'taxonomy' => 'category',
			'field'     => 'slug',
			'terms'    => array('original', 'cover')
		)
	);
}

// Add artist filter if artist_id is provided
if (!empty($artist_id) && $artist_id !== 'all') {
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
	?>
	
	<div class="song-grid alignfull">
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
				<?php 
					$cats = get_the_category();
					foreach ($cats as $cat) {
						if ($cat->slug === 'cover') {
							echo '<span class="song-grid-item-attribution">(' . get_field('attribution') . ')</span>';
						}
					}
				?>
				<span class="song-grid-item-date"><?php echo get_the_date(); ?></span>
			</span>
		</a>
		<?php
	}
	wp_reset_postdata();
	?>
	</div>
	<?php
}

