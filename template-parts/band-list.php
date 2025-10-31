<?php
/**
 * Template part for displaying band list
 * 
 * @package jww-theme
 */

// Get bands and title info from query vars (set by parent template or block)
$bands       = get_query_var('bands') ?? [];
$show_title  = get_query_var('show_title', true) ?? true;
$title       = get_query_var('title', '') ?? '';
$title_level = get_query_var('title_level', 3) ?? 3;
$show_dates  = get_query_var('show_dates', false) ?? false;

if (!$bands) {
    echo '<p>No bands found.</p>';
    return;
}

// Determine the title text
$title_text = !empty($title) ? $title : 'Bands';
?>

<div class="album-covers-section">
    <?php if ($show_title): ?>
        <?php printf('<h%d class="album-covers-title"><strong>%s</strong></h%d>', 
            $title_level, 
            esc_html($title_text), 
            $title_level
        ); ?>
    <?php endif; ?>
    
    <div class="album-covers">
        <?php 
        // Handle both single band and multiple bands
        $bands_array = is_array($bands) ? $bands : [$bands];
        foreach ($bands_array as $band): 
            // Handle both post objects and IDs
            $band_id    = is_object($band) ? $band->ID : $band;
            $band_title = get_the_title($band_id) ?? '';
            $band_url   = get_permalink($band_id) ?? '';
            $band_image = get_the_post_thumbnail($band_id, 'medium') ?? '';
            
            // Get date information if dates should be shown
            $date_text = '';
            if ($show_dates) {
                $founded = get_field('founded', $band_id);
                $disbanded = get_field('disbanded', $band_id);
                
                if ($founded) {
                    $date_text = '(' . esc_html($founded);
                    if ($disbanded) {
                        $date_text .= '–' . esc_html($disbanded) . ')';
                    } else {
                        $date_text .= '–' . esc_html('present') . ')';
                    }
                }
            }
            ?>
            <a
                class="album"
                href="<?php echo esc_url($band_url); ?>"
            >
                <?php if ($band_image): ?>
                    <?php echo $band_image; ?>
                <?php endif; ?>
                <h2 class="album-title"><?php echo esc_html($band_title); ?></h2>
                <?php if ($date_text): ?>
                    <span class="album-date"><?php echo $date_text; ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

