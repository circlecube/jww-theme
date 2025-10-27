<?php
/**
 * Footer template part
 */
?>
<footer class="wp-block-group is-style-section-1 has-contrast-color has-base-background-color has-text-color has-background has-link-color" style="border-top-width:2px;border-bottom-width:2px;margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--70);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--70);padding-left:var(--wp--preset--spacing--50)">
    <div class="wp-block-group alignwide is-layout-flow wp-block-group-is-layout-flow">
        <div class="wp-block-group alignfull is-content-justification-center is-layout-flex wp-block-group-is-layout-flex">
            <div class="wp-block-group is-vertical is-nowrap is-layout-flex wp-block-group-is-layout-flex" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0">
                <!-- Footer Navigation -->
                <?php
                $footer_navigation = '
                <!-- wp:navigation {"overlayMenu":"never","style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"fontSize":"large","layout":{"type":"flex","orientation":"horizontal","justifyContent":"center","flexWrap":"nowrap"},"ariaLabel":"Stories"} /-->';
                
                echo do_blocks($footer_navigation);
                ?>

                <!-- Site Logo and Title -->
                <?php
                $footer_site_info = '
                <!-- wp:site-logo {"width":300,"shouldSyncIcon":true} /-->
                
                <!-- wp:site-title {"level":2,"style":{"elements":{"link":{"color":{"text":"var:preset|color|accent-4"}}}},"fontSize":"xx-large","fontFamily":"roboto-slab"} /-->
                
                <!-- wp:site-tagline /-->';
                
                echo do_blocks($footer_site_info);
                ?>
                
            </div>
            
            
        </div>
    </div>
    
    <!-- Spacer -->
    <div style="height:var(--wp--preset--spacing--60)" aria-hidden="true" class="wp-block-spacer"></div>
    
    <!-- Social Links -->
    <?php
    $official_links = '
    <!-- wp:social-links {"openInNewTab":true,"iconColor":"base","iconColorValue":"#f4eee1","iconBackgroundColor":{"color":"#182949","name":"Accent 3","slug":"accent-3","class":"has-accent-3-icon-background-color"},"iconBackgroundColorValue":"#182949","className":"is-style-default","layout":{"type":"flex","justifyContent":"center"}} -->
    <ul class="wp-block-social-links has-icon-color has-icon-background-color is-style-default">
    <!-- wp:social-link {"url":"https://www.wellesmusic.com/","service":"chain"} /-->
        <!-- wp:social-link {"url":"https://youtube.com/@hellswelles","service":"youtube"} /-->
        <!-- wp:social-link {"url":"https://instagram.com/wellesmusic","service":"instagram"} /-->
        <!-- wp:social-link {"url":"https://www.tiktok.com/@jessewelles","service":"tiktok"} /-->
        <!-- wp:social-link {"url":"https://www.facebook.com/wellesmusic","service":"facebook"} /-->
    </ul>
    <!-- /wp:social-links -->';
    $unofficial_links = '
    <!-- wp:social-links {"openInNewTab":true,"iconColor":"base","iconColorValue":"#f4eee1","iconBackgroundColor":"accent-3","iconBackgroundColorValue":"#182949","layout":{"type":"flex","justifyContent":"center","flexWrap":"wrap"}} -->
    <ul class="wp-block-social-links has-icon-color has-icon-background-color">
        <!-- wp:social-link {"url":"https://www.reddit.com/r/JesseWelles/","service":"reddit"} /-->
        <!-- wp:social-link {"url":"https://www.facebook.com/share/g/19vTkV2P4A/","service":"facebook"} /-->
        <!-- wp:social-link {"url":"https://discord.gg/gT8dz7DgCH","service":"discord"} /-->
    </ul>
    <!-- /wp:social-links -->';
    $listening_links = '
    <!-- wp:social-links {"openInNewTab":true,"iconColor":"base","iconColorValue":"#f4eee1","iconBackgroundColor":"accent-3","layout":{"type":"flex","justifyContent":"center","flexWrap":"wrap"}} -->
    <ul class="wp-block-social-links has-icon-color has-icon-background-color social-links-listening">
        <!-- wp:social-link {"url":"https://music.amazon.com/artists/B07S2BD74W/jesse-welles?tag=circubstu-20","service":"amazon"} /-->
        <!-- wp:social-link {"url":"https://music.apple.com/us/artist/jesse-welles/1737507146","service":"chain","className":"wp-block-social-link--apple_music"} /-->
        <!-- wp:social-link {"url":"https://open.spotify.com/artist/366xgdzfRGQoiDRGidGlDJ","service":"spotify"} /-->
        <!-- wp:social-link {"url":"https://tidal.com/artist/15803343","service":"chain","className":"wp-block-social-link--tidal"} /-->
        <!-- wp:social-link {"url":"https://www.qobuz.com/us-en/interpreter/jesse-welles/4543994","service":"chain","className":"wp-block-social-link--qobuz"} /-->
        <!-- wp:social-link {"url":"https://www.deezer.com/en/artist/65777342","service":"chain","className":"wp-block-social-link--deezer"} /-->
        <!-- wp:social-link {"url":"https://jessewelles.bandcamp.com/","service":"bandcamp"} /-->
        <!-- wp:social-link {"url":"https://music.youtube.com/channel/UCWqIYTXNoYty8gH1eNE-YMw","service":"chain","className":"wp-block-social-link--youtube_music"} /-->
        <!-- wp:social-link {"url":"https://soundcloud.com/jesse-welles","service":"soundcloud"} /-->
        <!-- wp:social-link {"url":"https://www.pandora.com/artist/jesse-welles/ARcPbbrhlnrfjtK","service":"chain","className":"wp-block-social-link--pandora"} /-->
        <!-- wp:social-link {"url":"https://www.iheart.com/artist/jesse-welles-42675048/","service":"chain","className":"wp-block-social-link--iheartradio"} /-->
        <!-- wp:social-link {"url":"https://www.last.fm/music/Jesse+Welles","service":"lastfm"} /-->
    </ul>
    <!-- /wp:social-links -->';
    
    ?>
    <div class="wp-block-group is-content-justification-center is-layout-flex has-gap-large wp-block-group-is-layout-flex">
        <div class="wp-block-group is-vertical is-content-justification-center is-layout-flex wp-block-group-is-layout-flex">
            <p class="has-text-align-center"><em>Jesse’s Official Links</em></p>
            <?php echo do_blocks($official_links); ?>
        </div>
        <div class="wp-block-group is-vertical is-content-justification-center is-layout-flex wp-block-group-is-layout-flex">
            <p class="has-text-align-center"><em>Unofficial Links</em></p>
            <?php echo do_blocks($unofficial_links); ?>
        </div>
        <div class="wp-block-group is-vertical is-content-justification-center is-layout-flex wp-block-group-is-layout-flex">
            <p class="has-text-align-center"><em>Listening Links</em></p>
            <?php echo do_blocks($listening_links); ?>
        </div>
    </div>
    
    <!-- Spacer -->
    <div style="height:var(--wp--preset--spacing--60)" aria-hidden="true" class="wp-block-spacer"></div>
    
    <!-- Copyright -->
    <div class="wp-block-group alignwide is-layout-flow wp-block-group-is-layout-flow">
        <div class="wp-block-group alignfull is-content-justification-space-between is-layout-flex wp-block-group-is-layout-flex">
            <p class="has-small-font-size">© Woah!</p>
            <p class="has-small-font-size">Powered with <a href="https://wordpress.org" rel="nofollow">WordPress</a></p>
        </div>
    </div>
</footer>
