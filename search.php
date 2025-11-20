<?php
/**
 * Template for displaying search results
 */

get_header();
?>

<main class="wp-block-group is-layout-constrained wp-block-group-is-layout-constrained" style="margin-top:var(--wp--preset--spacing--60)">
	<?php if ( have_posts() ) : ?>
		
		<!-- Search Title -->
		<h1 class="wp-block-query-title">
			<?php
			// Get search query - try multiple methods
			global $wp_query;
			$search_query = get_search_query();
			
			// If empty, try the query object directly
			if ( empty( $search_query ) && isset( $wp_query->query_vars['s'] ) ) {
				$search_query = $wp_query->query_vars['s'];
			}
			
			// Fallback to GET parameter
			if ( empty( $search_query ) && isset( $_GET['s'] ) ) {
				$search_query = sanitize_text_field( $_GET['s'] );
			}
			
			if ( ! empty( $search_query ) ) {
				printf(
					/* translators: %s: Search query. */
					esc_html__( 'Search Results for: %s', 'jww-theme' ),
					'<span>' . esc_html( $search_query ) . '</span>'
				);
			} else {
				esc_html_e( 'Search Results', 'jww-theme' );
			}
			?>
		</h1>

		<!-- Search Form -->
		<?php get_search_form(); ?>

		<!-- Search Results -->
		<?php while ( have_posts() ) : the_post(); ?>
			<div class="wp-block-group search-result-item" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)">
				
				<?php if ( has_post_thumbnail() ) : ?>
					<a href="<?php the_permalink(); ?>">
						<?php the_post_thumbnail( 'large', array( 'style' => 'aspect-ratio:3/2;width:100%;height:auto;object-fit:cover;' ) ); ?>
					</a>
				<?php endif; ?>
				
				<h2 class="wp-block-post-title" style="font-size:x-large;">
					<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
				</h2>
				
				<div class="wp-block-group post-meta-container">
					<span class="post-type-label has-small-font-size">
						<?php echo esc_html( get_post_type() ); ?>
					</span>
					<span class="wp-block-post-date has-small-font-size">
						<?php echo get_the_date('F j, Y'); ?>
					</span>
				</div>
				
				<!-- Search Snippet -->
				<div class="search-snippet" style="font-size:medium;">
					<?php echo jww_get_search_snippet( get_the_ID() ); ?>
				</div>
			</div>
		<?php endwhile; ?>

		<!-- Pagination -->
		<?php
		// the_posts_pagination( array(
		// 	'mid_size'  => 2,
		// 	'prev_text' => __( '← Previous', 'jww-theme' ),
		// 	'next_text' => __( 'Next →', 'jww-theme' ),
		// ) );
		?>

	<?php else : ?>
		<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)">
			<p><?php esc_html_e( 'Sorry, but nothing was found. Please try a search with different keywords.', 'jww-theme' ); ?></p>
            <!-- Search Form -->
            <?php get_search_form(); ?>
		</div>
	<?php endif; ?>
</main>

<?php
get_footer();
?>

