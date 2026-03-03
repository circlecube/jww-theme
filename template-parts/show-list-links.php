<?php
/**
 * Template part: Simple list of show links.
 * Used by Latest Shows and Upcoming Shows blocks. Expects $args with:
 *   - shows: array of WP_Post (show post type)
 *   - heading: optional string
 *
 * @package JWW_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$shows   = isset( $args['shows'] ) && is_array( $args['shows'] ) ? $args['shows'] : array();
$heading = isset( $args['heading'] ) ? $args['heading'] : '';
$class   = isset( $args['class'] ) ? $args['class'] : 'show-list-links';

if ( empty( $shows ) ) {
	return;
}
?>
<div class="<?php echo esc_attr( $class ); ?>">
	<?php if ( $heading ) : ?>
		<h2 class="show-list-links-heading wp-block-heading"><?php echo esc_html( $heading ); ?></h2>
	<?php endif; ?>
	<ul class="show-list-links-list">
		<?php foreach ( $shows as $show ) : ?>
			<li class="show-list-links-item">
				<a href="<?php echo esc_url( get_permalink( $show->ID ) ); ?>"><?php echo esc_html( get_the_title( $show->ID ) ); ?></a>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
