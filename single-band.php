<?php
/**
 * Template for displaying single album posts
 */

get_header();
?>

		
<main class="wp-block-group">
	<div class="entry-content alignfull wp-block-post-content has-global-padding is-layout-constrained wp-block-post-content-is-layout-constrained" style="padding-bottom:var(--wp--preset--spacing--60)">
		<?php if (has_post_thumbnail()): ?>
			<div class="band-hero-image alignfull">
				<?php the_post_thumbnail('full'); ?>
			</div>
		<?php endif; ?>
		
		<?php the_title('<h1 class="wp-block-post-title">', '</h1>'); ?>
		<h2 class="band-info">
		<?php
			$start_date = get_field('founded');
			$end_date = get_field('disbanded');
			if ($start_date) {
				echo '<span>Formed ' . esc_html($start_date) . '</span>';
				if ($end_date) {
					echo '<span> and disbanded in ' . esc_html($end_date) . '.</span>';
				} else {
					echo '<span> and still active as of ' . esc_html(date('Y')) . '.</span>';
				}
			}
		?>
		</h2>
		<div class="wp-block-post-content alignfull is-layout-constrained ">
			<?php the_content(); ?>
		</div>
		
	</div>
</main>

<!-- Albums Section -->
<div class="wp-block-group alignwide has-global-padding is-layout-constrained wp-block-post-content-is-layout-constrained" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)">	
	<h2 class="albums-section-heading">Albums by <?php the_title(); ?></h2>
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
		),
		'meta_query' => array(
			array(
				'key'     => 'artist',
				'value'   => '"' . get_the_ID() . '"', // current band ID
				'compare' => 'LIKE'
			)
		),
	));
	
	set_query_var('albums', $albums);
	set_query_var('show_title', true);
	set_query_var('title', 'Releases');
	get_template_part('template-parts/album-covers');
	?>
</div>
<!-- Singles Section -->
<div class="wp-block-group alignwide has-global-padding is-layout-constrained wp-block-post-content-is-layout-constrained" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)">	
	<h2 class="albums-section-heading">EPs & Singles by <?php the_title(); ?></h2>
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
				'terms'    => array( 'ep', 'single' ),
				'operator' => 'IN'
			)
		),
		'meta_query' => array(
			array(
				'key'     => 'artist',
				'value'   => '"' . get_the_ID() . '"', // current band ID
				'compare' => 'LIKE'
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
