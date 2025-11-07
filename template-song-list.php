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
		<?php jww_song_list_nav(); ?>
		<?php get_template_part('template-parts/song-list-alphabetical'); ?>
	
		<?php jww_song_list_nav(); ?>
		<?php get_template_part('template-parts/song-list-chronological'); ?>

		<?php jww_song_list_nav(); ?>
		<?php get_template_part('template-parts/song-list-covers'); ?>

	</div>
</main>

<?php
get_footer();
?>