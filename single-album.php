<?php
/**
 * Template for displaying single album posts
 */

get_header();
?>

<main class="wp-block-group" style="margin-top:var(--wp--preset--spacing--60)">
	<div class="entry-content alignfull wp-block-post-content has-global-padding is-layout-constrained wp-block-post-content-is-layout-constrained" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)">
		
		<?php the_title('<h1 class="wp-block-post-title">', '</h1>'); ?>
		
		<div class="album-release-info">
			<span>Released </span>
			<?php
			$release_date = get_field('release_date');
			if ($release_date) {
				echo '<span>' . esc_html($release_date) . '</span>';
			}
			?>
		</div>
		
		<div class="album-content">
			<?php if (has_post_thumbnail()): ?>
				<div class="album-cover">
					<?php the_post_thumbnail('large'); ?>
				</div>
			<?php endif; ?>
			
			<div class="album-tracks">
				<p>Track:</p>
				<?php
				$tracks = get_field('tracklist'); // Replace with your actual ACF field name for tracks
				if ($tracks): ?>
					<ol>
						<?php foreach ($tracks as $track): ?>
							<li><a href="<?php echo esc_url(get_permalink($track)); ?>"><?php echo esc_html(get_the_title($track)); ?></a></li>
						<?php endforeach; ?>
					</ol>
				<?php else: ?>
					<p>No Songs Added (yet)</p>
				<?php endif; ?>
			</div>
		</div>
		
		<div class="wp-block-post-content alignfull">
			<?php the_content(); ?>
		</div>
		
	</div>
</main>

<!-- Albums Section -->
<div class="wp-block-group alignwide has-global-padding is-layout-constrained wp-block-post-content-is-layout-constrained" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)">
	<h2 class="albums-section-heading">Albums</h2>
	
	<?php
	// Get all albums and pass them to the template part
	$albums = get_posts(array(
		'order'          => 'DESC',
		'orderby'        => 'date',
		'post_type'      => 'album',
		'posts_per_page' => 100,
		'tax_query'      => array(
			array(
				'taxonomy' => 'release',
				'field'    => 'name',
				'terms'    => array( 'album' ),
				'operator' => 'IN'
			)
		)
	));
	
	set_query_var('albums', $albums);
	set_query_var('show_title', true);
	set_query_var('title', 'Releases');
	get_template_part('template-parts/album-covers');
	?>
</div>

<!-- Navigation -->
<div class="nav-link-container wp-block-group alignwide">
		<?php
		the_post_navigation(array(
			'prev_text' => '<span class="nav-subtitle">' . esc_html__('Previous:', 'jww-theme') . '</span> <span class="nav-title">%title</span>',
			'next_text' => '<span class="nav-subtitle">' . esc_html__('Next:', 'jww-theme') . '</span> <span class="nav-title">%title</span>',
		));
		?>
	</nav>
</div>


<?php
// If comments are open or we have at least one comment, load up the comment template.
if (comments_open() || get_comments_number()) :
	comments_template();
endif;
?>

<?php get_footer(); ?>
