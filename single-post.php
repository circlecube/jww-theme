<?php
/**
 * Template for displaying single song posts
 */

get_header();
?>

<main class="wp-block-group align is-layout-flow wp-block-group-is-layout-flow post-single">
	<div
		class="wp-block-group has-global-padding is-layout-constrained wp-block-group-is-layout-constrained" 
		style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50)"
	>

		<?php the_title('<h1 class="wp-block-post-title has-xxx-large-font-size">', '</h1>'); ?>

		<?php if (has_post_thumbnail()): ?>
			<div class="post-featured-image is-layout-flex is-content-justification-space-between">
				<?php the_post_thumbnail('large'); ?>
			</div>
		<?php endif; ?>

		<!-- post meta: category, date -->
		<div class="wp-block-group is-layout-flex is-content-justification-space-between flex-direction-row song-post-meta">
			<span class="has-small-font-size song-post-category" title="Category">Posted in: <?php echo get_the_category_list(', '); ?></span>
			<span class="has-small-font-size song-post-date" title="First published on"><?php echo get_the_date('F j, Y'); ?></span>
		</div>

		<div class="wp-block-post-content">
			<?php the_content(); ?>
		</div>

</main>

<!-- Navigation -->
<div class="nav-link-container wp-block-group">
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
