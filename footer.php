<?php
// Load the footer HTML template part directly
$footer_template = get_stylesheet_directory() . '/parts/footer.html';
if ( file_exists( $footer_template ) ) {
    $footer_content = file_get_contents( $footer_template );
    // Process block markup first, then shortcodes
    $footer_blocks = do_blocks( $footer_content );
    // Process shortcodes (needed for shortcodes inside HTML blocks)
    echo do_shortcode( $footer_blocks );
}
?>

<?php wp_footer(); ?>
</body>
</html>
