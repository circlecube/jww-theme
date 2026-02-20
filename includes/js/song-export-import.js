jQuery(document).ready(function($) {
    'use strict';

    // Export songs to JSON (trigger download)
    $('#export-songs-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $spinner = $form.find('.spinner');
        var $result = $form.find('.export-result');

        $spinner.addClass('is-active');
        $result.html('').removeClass('error success');

        var tagId = $('#export-tag').val() || '';

        $.ajax({
            url: jwwSongExportImport.ajaxUrl,
            type: 'POST',
            data: {
                action: 'jww_export_songs',
                nonce: jwwSongExportImport.nonce,
                tag_id: tagId
            },
            success: function(response) {
                $spinner.removeClass('is-active');
                if (response.success) {
                    var jsonStr = JSON.stringify(response.data.data, null, 2);
                    var blob = new Blob([jsonStr], { type: 'application/json' });
                    var url = URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = 'songs-export-' + new Date().getTime() + '.json';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);

                    $result.html(
                        '<div class="notice notice-success"><p>Exported ' + response.data.count + ' songs. Download started.</p></div>'
                    ).addClass('success');
                } else {
                    $result.html(
                        '<div class="notice notice-error"><p>' + (response.data && response.data.message ? response.data.message : 'Export failed.') + '</p></div>'
                    ).addClass('error');
                }
            },
            error: function(xhr) {
                $spinner.removeClass('is-active');
                var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message)
                    ? xhr.responseJSON.data.message
                    : 'An error occurred. Please try again.';
                $result.html(
                    '<div class="notice notice-error"><p>' + msg + '</p></div>'
                ).addClass('error');
            }
        });
    });

    // Import songs from JSON file
    $('#import-songs-json-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $spinner = $form.find('.spinner');
        var $result = $form.find('.import-result');
        var $fileInput = $('#songs-json-file');
        var file = $fileInput[0].files[0];

        if (!file) {
            $result.html(
                '<div class="notice notice-error"><p>Please select a JSON file to upload.</p></div>'
            ).addClass('error');
            return;
        }

        $spinner.addClass('is-active');
        $result.html('').removeClass('error success');

        var formData = new FormData();
        formData.append('action', 'jww_import_songs_json');
        formData.append('nonce', jwwSongExportImport.nonce);
        formData.append('json_file', file);

        $.ajax({
            url: jwwSongExportImport.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $spinner.removeClass('is-active');
                if (response.success) {
                    var html = '<div class="notice notice-success"><p><strong>' + response.data.message + '</strong></p>';
                    if (response.data.errors && response.data.errors.length) {
                        html += '<ul style="margin: 10px 0 0 20px;">';
                        response.data.errors.forEach(function(err) {
                            html += '<li>' + err + '</li>';
                        });
                        html += '</ul>';
                    }
                    html += '</div>';
                    $result.html(html).addClass('success');
                    $fileInput.val('');
                } else {
                    $result.html(
                        '<div class="notice notice-error"><p>' + (response.data && response.data.message ? response.data.message : 'Import failed.') + '</p></div>'
                    ).addClass('error');
                }
            },
            error: function(xhr) {
                $spinner.removeClass('is-active');
                var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message)
                    ? xhr.responseJSON.data.message
                    : 'An error occurred. Please try again.';
                $result.html(
                    '<div class="notice notice-error"><p>' + msg + '</p></div>'
                ).addClass('error');
            }
        });
    });
});
