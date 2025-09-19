<?php
/**
 * Header template part
 */
?>
<header 
    class="wp-block-group alignfull has-base-color has-contrast-background-color has-text-color has-background has-link-color"
    style="border-bottom-color:var(--wp--preset--color--accent-2);border-bottom-width:2px"
>
    <div class="wp-block-group is-style-section-5 has-base-color has-contrast-background-color has-text-color has-background has-link-color wp-elements-9a57da1cd98312066a1ab770f174dfee has-global-padding is-layout-constrained wp-block-group-is-layout-constrained is-style-section-5--1">
        <div class="wp-block-group alignwide is-content-justification-space-between is-nowrap is-layout-flex wp-container-core-group-is-layout-8165f36a wp-block-group-is-layout-flex" style="padding-top:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30)">
            
            <!-- Site Title -->
            <?php
            $site_title = '
            <!-- wp:site-title {"level":2,"fontSize":"xx-large","fontFamily":"roboto-slab"} /-->
            ';
            
            echo do_blocks($site_title);
            ?>
            
            <div class="wp-block-group is-content-justification-right is-nowrap is-layout-flex wp-container-core-group-is-layout-f4c28e8b wp-block-group-is-layout-flex">
            <!-- Navigation -->
            <?php
            $header_navigation = '
            <!-- wp:navigation {"overlayMenu":"never","overlayBackgroundColor":"base","overlayTextColor":"contrast","style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"fontSize":"medium","layout":{"type":"flex","justifyContent":"right","flexWrap":"wrap"}} /-->
            ';
            echo do_blocks($header_navigation);
            ?>
            </div>
        </div>
    </div>
</header>
