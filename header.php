<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="profile" href="https://gmpg.org/xfn/11">
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<?php
// Load the header HTML template part directly
$header_template = get_stylesheet_directory() . '/parts/header.html';
if ( file_exists( $header_template ) ) {
    $header_content = file_get_contents( $header_template );
    // Process block markup first, then shortcodes
    $header_blocks = do_blocks( $header_content );
    // Shortcode blocks are rendered without p wrappers via render_block filter in functions-shortcodes.php.
    echo do_shortcode( $header_blocks );
}
?>
