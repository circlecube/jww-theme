jQuery(document).ready(function($) {
    'use strict';

    // Import from setlist.fm URL
    $('#import-setlist-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $spinner = $form.find('.spinner');
        var $result = $form.find('.import-result');
        var url = $('#setlist-url').val();

        $spinner.addClass('is-active');
        $result.html('').removeClass('error success');

        $.ajax({
            url: jwwShowImporter.ajaxUrl,
            type: 'POST',
            data: {
                action: 'jww_import_setlist',
                nonce: jwwShowImporter.nonce,
                setlist_url: url
            },
            success: function(response) {
                $spinner.removeClass('is-active');
                if (response.success) {
                    $result.html(
                        '<div class="notice notice-success"><p>' + response.data.message + 
                        ' <a href="' + response.data.edit_link + '">Edit Show</a></p></div>'
                    ).addClass('success');
                    $('#setlist-url').val('');
                } else {
                    $result.html(
                        '<div class="notice notice-error"><p>' + response.data.message + '</p></div>'
                    ).addClass('error');
                }
            },
            error: function() {
                $spinner.removeClass('is-active');
                $result.html(
                    '<div class="notice notice-error"><p>An error occurred. Please try again.</p></div>'
                ).addClass('error');
            }
        });
    });

    // Import from JSON File
    $('#import-json-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $spinner = $form.find('.spinner');
        var $result = $form.find('.import-result');
        var $fileInput = $('#json-file');
        var file = $fileInput[0].files[0];

        if (!file) {
            $result.html(
                '<div class="notice notice-error"><p>Please select a JSON file to upload.</p></div>'
            ).addClass('error');
            return;
        }

        $spinner.addClass('is-active');
        $result.html('').removeClass('error success');

        // Create FormData for file upload
        var formData = new FormData();
        formData.append('action', 'jww_import_json');
        formData.append('nonce', jwwShowImporter.nonce);
        formData.append('json_file', file);

        $.ajax({
            url: jwwShowImporter.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $spinner.removeClass('is-active');
                if (response.success) {
                    var html = '<div class="notice notice-success"><p><strong>' + response.data.message + '</strong></p>';
                    
                    // Show detailed results if available
                    if (response.data.results && response.data.results.messages) {
                        html += '<ul style="margin: 10px 0; padding-left: 20px;">';
                        response.data.results.messages.forEach(function(msg) {
                            var isError = msg.indexOf('error') !== -1 || msg.indexOf('Error') !== -1 || msg.indexOf('Missing') !== -1;
                            html += '<li style="' + (isError ? 'color: #dc3232;' : '') + '">' + msg + '</li>';
                        });
                        html += '</ul>';
                    }
                    
                    html += '</div>';
                    $result.html(html).addClass('success');
                    $fileInput.val(''); // Clear file input
                } else {
                    $result.html(
                        '<div class="notice notice-error"><p>' + response.data.message + '</p></div>'
                    ).addClass('error');
                }
            },
            error: function(xhr, status, error) {
                $spinner.removeClass('is-active');
                var errorMsg = 'An error occurred. Please try again.';
                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    errorMsg = xhr.responseJSON.data.message;
                }
                $result.html(
                    '<div class="notice notice-error"><p>' + errorMsg + '</p></div>'
                ).addClass('error');
            }
        });
    });

    // Export shows
    $('#export-shows-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $spinner = $form.find('.spinner');
        var $result = $form.find('.export-result');
        var tourId = $('#export-tour').val();

        $spinner.addClass('is-active');
        $result.html('').removeClass('error success');

        $.ajax({
            url: jwwShowImporter.ajaxUrl,
            type: 'POST',
            data: {
                action: 'jww_export_shows',
                nonce: jwwShowImporter.nonce,
                tour_id: tourId
            },
            success: function(response) {
                $spinner.removeClass('is-active');
                if (response.success) {
                    var jsonStr = JSON.stringify(response.data.data, null, 2);
                    var blob = new Blob([jsonStr], { type: 'application/json' });
                    var url = URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = 'shows-export-' + new Date().getTime() + '.json';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);

                    $result.html(
                        '<div class="notice notice-success"><p>Exported ' + response.data.count + ' shows. Download started.</p></div>'
                    ).addClass('success');
                } else {
                    $result.html(
                        '<div class="notice notice-error"><p>' + response.data.message + '</p></div>'
                    ).addClass('error');
                }
            },
            error: function() {
                $spinner.removeClass('is-active');
                $result.html(
                    '<div class="notice notice-error"><p>An error occurred. Please try again.</p></div>'
                ).addClass('error');
            }
        });
    });

    // Test API connection
    $('#test-api-btn').on('click', function() {
        var $btn = $(this);
        var originalText = $btn.text();
        $btn.prop('disabled', true).text('Testing...');

        $.ajax({
            url: jwwShowImporter.ajaxUrl,
            type: 'POST',
            data: {
                action: 'jww_test_api',
                nonce: jwwShowImporter.nonce
            },
            success: function(response) {
                $btn.prop('disabled', false).text(originalText);
                if (response.success) {
                    alert('API connection successful!');
                } else {
                    alert('API connection failed: ' + response.data.message);
                }
            },
            error: function() {
                $btn.prop('disabled', false).text(originalText);
                alert('An error occurred while testing the API.');
            }
        });
    });
});
