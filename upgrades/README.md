# Theme Upgrade Routines

This directory contains upgrade routines that run automatically when the theme is updated. See https://github.com/wp-forge/wp-upgrade-handler for more details.

## How It Works

1. **Version Tracking**: The theme tracks its current version in the database
2. **Automatic Detection**: When the theme version changes, upgrade routines are triggered
3. **Sequential Execution**: Upgrade routines run in version order (e.g., 1.0.1.php, then 1.0.2.php)
4. **One-Time Execution**: Each upgrade routine runs only once per version

## Creating New Upgrade Routines

1. **Create a new file** named after the version (e.g., `1.0.2.php`)
2. **Add your upgrade code** inside the file
3. **Update the version** in `style.css` to match your new version
4. **Test thoroughly** before deploying

## Example Upgrade File

```php
<?php
/**
 * Upgrade routine for version 1.0.2
 * 
 * Description of what this upgrade does
 */

// Your upgrade code here
$posts = get_posts(['post_type' => 'song', 'posts_per_page' => -1]);

foreach ($posts as $post) {
    // Perform upgrade operations
    update_field('new_field', 'new_value', $post->ID);
}

error_log("Theme Upgrade 1.0.2: Completed successfully");
```

## Current Upgrade Routines

- **1.2.0.php**: Migrates 'attibution' field to 'attribution' field (fixes typo)