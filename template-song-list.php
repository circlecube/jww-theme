<?php
/**
 * Template Name: Song List
 * Template Post Type: page
 * 
 * Template for displaying a list of songs
 *
 * @package jww-theme
 * @subpackage jww-theme
 * @since 1.0.0
 * @version 1.0.0
 */

get_header();
?>

<main class="wp-block-group is-layout-flow wp-block-group-is-layout-flow song-list-tempalte is-layout-constrained">
	<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)">
		<h1 class="wp-block-heading">Original Songs</h1>
		<h2 class="wp-block-heading">Alphabetical</h2>
	<?php
	$song_query = new WP_Query(array(
		'post_type'      => 'song',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'tax_query'      => array(
			array(
				'taxonomy' => 'category',
				'field'     => 'slug',
				'terms'    => 'original'
			)
		)
	));
	
	if ($song_query->have_posts()) { 
		$current_letter = '';
		?>
		<div class="alphabetical-song-list">
		<?php
		while ($song_query->have_posts()) {
			$song_query->the_post();
			$title = get_the_title();
			$first_letter = strtoupper(substr($title, 0, 1));
			
			// Check if we need to add a new letter header
			if ($first_letter !== $current_letter) {
				// Close previous letter group if it exists
				if ($current_letter !== '') {
					echo '</ul>';
					echo '</div>';
				}
				
				// Add letter header
				echo '<div class="alphabet-section">';
				echo '<h3 class="alphabet-letter" id="letter-' . strtolower($first_letter) . '">' . $first_letter . '</h3>';
				echo '<ul class="wp-block-list song-list">';
				
				$current_letter = $first_letter;
			}
			?>
			<li class="wp-block-list-item">
				<h4 class="wp-block-heading">
					<a href="<?php the_permalink(); ?>">
						<?php the_title(); ?>
					</a>
				</h4>
			</li>
			<?php
		}
		// Close the last letter group
		if ($current_letter !== '') {
			echo '</ul>';
			echo '</div>';
		}
		?>
		</div>
		<?php
	}
	wp_reset_postdata();
	?>
	<h2 class="wp-block-heading">Chronological</h2>
	<?php
	$song_query = new WP_Query(array(
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
	));
	
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
					echo '</div>';
				}
				
				// Add month header
				echo '<div class="month-section">';
				echo '<h3 class="month-heading" id="month-' . $post_date . '">' . $month_display . '</h3>';
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
			echo '</div>';
		}
		?>
		</div>
		<?php
	}
	wp_reset_postdata();
	?>

	<h2 class="wp-block-heading">Cover Songs</h2>
	<?php
	$song_query = new WP_Query(array(
		'post_type'      => 'song',
		'posts_per_page' => -1,
		'orderby'        => 'meta_value',
		'meta_key'       => 'attibution',
		'order'          => 'ASC',
		'tax_query'      => array(
			array(
				'taxonomy' => 'category',
				'field'     => 'slug',
				'terms'    => 'cover'
			)
		)
	));
	
	if ($song_query->have_posts()) { 
		$current_artist = '';
		?>
		<div class="cover-songs-list">
		<?php
		while ($song_query->have_posts()) {
			$song_query->the_post();
			$attribution = get_field('attibution');
			$artist_name = $attribution ? $attribution : 'Unknown Artist';
			
			// Check if we need to add a new artist header
			if ($artist_name !== $current_artist) {
				// Close previous artist group if it exists
				if ($current_artist !== '') {
					echo '</ul>';
					echo '</div>';
				}
				
				// Add artist header
				echo '<div class="artist-section">';
				echo '<h3 class="artist-heading" id="artist-' . sanitize_title($artist_name) . '">' . esc_html($artist_name) . '</h3>';
				echo '<ul class="wp-block-list song-list">';
				
				$current_artist = $artist_name;
			}
			?>
			<li class="wp-block-list-item">
                <h4 class="wp-block-heading">
                    <a href="<?php the_permalink(); ?>">
                        <?php the_title(); ?>
						<!-- <span class="song-meta song-attribution">(<?php the_field('attibution'); ?>)</span> -->
                    </a>
                </h4>
            </li>
			<?php
		}
		// Close the last artist group
		if ($current_artist !== '') {
			echo '</ul>';
			echo '</div>';
		}
		?>
		</div>
		<?php
	}
	wp_reset_postdata();
	?>
	</div>
</main>

<?php
get_footer();
?>