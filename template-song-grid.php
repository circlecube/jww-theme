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
	$song_query = new WP_Query(array(
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
	));
	
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