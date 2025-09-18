<?php 
use WP_Forge\WPUpdateHandler\ThemeUpdater;

// Updater
$theme = wp_get_theme( 'jww-theme' );
$url   = 'https://api.github.com/repos/circlecube/jww-theme/releases/latest';

// Handle plugin updates
$jwwThemeUpdater = new ThemeUpdater( $theme, $url );
$jwwThemeUpdater->setDataMap(
	array(
		'download_link' => 'assets.0.browser_download_url',
		'last_updated'  => 'published_at',
		'version'       => 'tag_name',
	)
);

$jwwThemeUpdater->setDataOverrides(
	array(
		'requires'      => '6.8',
		'requires_php'  => '8.0',
		'tested'        => '6.8',
	)
);
