jQuery(document).ready(function($) {
    'use strict';

    // Handle sync button click
    $(document).on('click', '.jww-sync-setlist', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var showId = $button.data('show-id');
        var nonce = $button.data('nonce');
        var originalText = $button.text();
        
        // Disable button and show loading state
        $button.prop('disabled', true).text('Syncing...');
        
        $.ajax({
            url: jwwShowSync.ajaxUrl,
            type: 'POST',
            data: {
                action: 'jww_sync_setlist',
                show_id: showId,
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    $button.text('✓ Synced').css('color', '#00a32a');
                    setTimeout(function() {
                        $button.text(originalText).css('color', '');
                        $button.prop('disabled', false);
                    }, 2000);
                } else {
                    alert('Sync failed: ' + response.data.message);
                    $button.text(originalText).prop('disabled', false);
                }
            },
            error: function() {
                alert('An error occurred while syncing. Please try again.');
                $button.text(originalText).prop('disabled', false);
            }
        });
    });
});
