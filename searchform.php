<?php
/**
 * Custom search form template
 * 
 * This template is used when get_search_form() is called.
 * It displays a search form with the current search query as the value.
 */
?>

<form role="search" method="get" class="wp-block-search__button-inside wp-block-search__icon-button wp-block-search" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label for="search-form-input" class="wp-block-search__label screen-reader-text"><?php esc_html_e( 'Search', 'jww-theme' ); ?></label>
	<div class="wp-block-search__inside-wrapper">
		<input 
			type="search" 
			id="search-form-input" 
			class="wp-block-search__input" 
			name="s" 
			value="<?php echo esc_attr( get_search_query() ?: ( isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '' ) ); ?>" 
			placeholder="<?php esc_attr_e( 'Type search terms here...', 'jww-theme' ); ?>" 
			required 
		/>
		<button type="submit" class="wp-block-search__button wp-element-button" aria-label="<?php esc_attr_e( 'Search', 'jww-theme' ); ?>">
			<?php esc_html_e( 'Search', 'jww-theme' ); ?>
		</button>
	</div>
</form>

