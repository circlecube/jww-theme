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

    // Load setlist sync status
    function loadSetlistSyncStatus() {
        $('#setlist-sync-status').html('Loading...');

        $.ajax({
            url: jwwShowImporter.ajaxUrl,
            type: 'POST',
            data: {
                action: 'jww_get_setlist_sync_status',
                nonce: jwwShowImporter.nonce
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    var html = '<table class="setlist-sync-status-table" style="width:100%;border-collapse:collapse;">';
                    html += '<tr><td style="padding:8px;font-weight:bold;width:40%;">Schedule:</td><td style="padding:8px;">' + data.current_schedule + '</td></tr>';
                    html += '<tr><td style="padding:8px;font-weight:bold;">Next Run:</td><td style="padding:8px;">' + data.next_run + ' (' + data.next_run_relative + ')</td></tr>';
                    html += '<tr><td style="padding:8px;font-weight:bold;">Last Check:</td><td style="padding:8px;">' + data.last_sync + ' (' + data.last_sync_relative + ')</td></tr>';
                    html += '<tr><td style="padding:8px;font-weight:bold;">Cron Health:</td><td style="padding:8px;">' +
                        (data.cron_healthy ? '<span style="color:green">✓ Healthy</span>' : '<span style="color:orange">⚠ Not scheduled</span>') +
                        '</td></tr>';
                    html += '</table>';
                    $('#setlist-sync-status').html(html);
                } else {
                    $('#setlist-sync-status').html('<p style="color: red;">Error loading status</p>');
                }
            },
            error: function() {
                $('#setlist-sync-status').html('<p style="color: red;">Error loading status</p>');
            }
        });
    }

    // Load status on page load
    loadSetlistSyncStatus();

    // Run sync now
    $('#run-sync-now-btn').on('click', function() {
        var $btn = $(this);
        var $spinner = $('#sync-now-spinner');
        var $result = $('#sync-now-result');
        var originalText = $btn.text();

        $btn.prop('disabled', true).text('Running...');
        $spinner.addClass('is-active');
        $result.html('').removeClass('error success');

        $.ajax({
            url: jwwShowImporter.ajaxUrl,
            type: 'POST',
            data: {
                action: 'jww_run_sync_now',
                nonce: jwwShowImporter.nonce
            },
            success: function(response) {
                $spinner.removeClass('is-active');
                $btn.prop('disabled', false).text(originalText);

                if (response.success) {
                    var html = '<div class="notice notice-success"><p><strong>' + response.data.message + '</strong></p>';
                    if (response.data.results) {
                        html += '<p style="margin-top: 10px; font-size: 0.9em; color: #646970;">';
                        html += 'Last check: ' + response.data.last_sync + ' (' + response.data.last_sync_relative + ')';
                        html += '</p>';
                    }
                    html += '</div>';
                    $result.html(html).addClass('success').removeClass('error');

                    // Reload status
                    loadSetlistSyncStatus();
                } else {
                    $result.html(
                        '<div class="notice notice-error"><p>' + response.data.message + '</p></div>'
                    ).addClass('error').removeClass('success');
                }
            },
            error: function() {
                $spinner.removeClass('is-active');
                $btn.prop('disabled', false).text(originalText);
                $result.html(
                    '<div class="notice notice-error"><p>An error occurred. Please try again.</p></div>'
                ).addClass('error').removeClass('success');
            }
        });
    });

    // Check for setlist updates (compare our last synced vs setlist.fm lastUpdated)
    var CHECK_DELAY_MS = 2000;
    $('#check-setlist-updates-btn').on('click', function() {
        var $btn = $(this);
        var $spinner = $('#check-updates-spinner');
        var $result = $('#setlist-updates-result');
        var originalText = $btn.text();

        $btn.prop('disabled', true).text('Fetching shows...');
        $spinner.addClass('is-active');
        $result.html('<p>Loading shows that have a setlist.fm URL...</p>').removeClass('error success');

        $.ajax({
            url: jwwShowImporter.ajaxUrl,
            type: 'POST',
            data: {
                action: 'jww_get_shows_with_setlist_url',
                nonce: jwwShowImporter.nonce
            },
            success: function(response) {
                if (!response.success || !response.data.shows || response.data.shows.length === 0) {
                    $spinner.removeClass('is-active');
                    $btn.prop('disabled', false).text(originalText);
                    $result.html(
                        '<div class="notice notice-info"><p>No shows with a setlist.fm URL found.</p></div>'
                    );
                    return;
                }
                var shows = response.data.shows;
                $result.html('<p>Checking ' + shows.length + ' show(s) with setlist.fm (one every 2 seconds to respect API limits)...</p>');
                var withChanges = [];
                var index = 0;

                function checkNext() {
                    if (index >= shows.length) {
                        $spinner.removeClass('is-active');
                        $btn.prop('disabled', false).text(originalText);
                        renderUpdatesTable(withChanges);
                        return;
                    }
                    var show = shows[index];
                    $result.html('<p>Checking ' + (index + 1) + ' / ' + shows.length + ': ' + show.title + '...</p>');
                    $.ajax({
                        url: jwwShowImporter.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'jww_check_one_show_setlist_update',
                            nonce: jwwShowImporter.nonce,
                            show_id: show.show_id
                        },
                        success: function(res) {
                            if (res.success && res.data.has_changes) {
                                withChanges.push(res.data);
                            }
                        }
                    }).always(function() {
                        index++;
                        setTimeout(checkNext, CHECK_DELAY_MS);
                    });
                }
                setTimeout(checkNext, 0);
            },
            error: function() {
                $spinner.removeClass('is-active');
                $btn.prop('disabled', false).text(originalText);
                $result.html(
                    '<div class="notice notice-error"><p>Failed to load shows. Please try again.</p></div>'
                ).addClass('error');
            }
        });
    });

    function formatLastUpdated(str) {
        if (!str) return '—';
        try {
            var d = new Date(str);
            return isNaN(d.getTime()) ? str : d.toLocaleString();
        } catch (e) {
            return str;
        }
    }

    function renderUpdatesTable(rows) {
        var $result = $('#setlist-updates-result');
        if (rows.length === 0) {
            $result.html(
                '<div class="notice notice-success"><p>No shows with setlist changes on setlist.fm. All imported setlists are up to date.</p></div>'
            ).addClass('success').removeClass('error');
            return;
        }
        var html = '<div class="notice notice-warning" style="margin-bottom:10px;"><p><strong>' + rows.length + ' show(s) have updates on setlist.fm.</strong></p></div>';
        html += '<table class="widefat striped" style="margin-top:0;"><thead><tr>';
        html += '<th>Show</th><th>Last synced (ours)</th><th>Updated on setlist.fm</th><th></th></tr></thead><tbody>';
        rows.forEach(function(r) {
            html += '<tr data-show-id="' + r.show_id + '">';
            html += '<td>' + (r.title ? r.title.replace(/</g, '&lt;') : 'Show #' + r.show_id) + '</td>';
            html += '<td>' + formatLastUpdated(r.our_last_updated) + '</td>';
            html += '<td>' + formatLastUpdated(r.api_last_updated) + '</td>';
            html += '<td><button type="button" class="button button-small jww-resync-setlist-btn" data-show-id="' + r.show_id + '" data-nonce="' + (r.resync_nonce || '') + '">Resync now</button></td>';
            html += '</tr>';
        });
        html += '</tbody></table>';
        $result.html(html).removeClass('error success');

        $result.find('.jww-resync-setlist-btn').on('click', function() {
            var $resyncBtn = $(this);
            var showId = $resyncBtn.data('show-id');
            var nonce = $resyncBtn.data('nonce');
            var $row = $resyncBtn.closest('tr');
            var originalText = $resyncBtn.text();
            $resyncBtn.prop('disabled', true).text('Syncing...');
            $.ajax({
                url: jwwShowImporter.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'jww_sync_setlist',
                    nonce: nonce,
                    show_id: showId
                },
                success: function(response) {
                    if (response.success) {
                        $row.addClass('jww-resync-done');
                        $resyncBtn.text('Synced').prop('disabled', true);
                    } else {
                        $resyncBtn.prop('disabled', false).text(originalText);
                        alert(response.data && response.data.message ? response.data.message : 'Resync failed.');
                    }
                },
                error: function(xhr) {
                    $resyncBtn.prop('disabled', false).text(originalText);
                    var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : 'Resync failed.';
                    alert(msg);
                }
            });
        });
    }

    // Save API key
    $('#api-key-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $spinner = $form.find('.spinner');
        var $result = $form.find('.api-key-result');
        var $input = $('#api-key-input');
        var apiKey = $input.val();

        if (!apiKey) {
            $result.html(
                '<div class="notice notice-error"><p>API key cannot be empty.</p></div>'
            ).addClass('error').removeClass('success');
            return;
        }

        $spinner.addClass('is-active');
        $result.html('').removeClass('error success');

        $.ajax({
            url: jwwShowImporter.ajaxUrl,
            type: 'POST',
            data: {
                action: 'jww_save_api_key',
                nonce: jwwShowImporter.nonce,
                api_key: apiKey,
                action_type: 'save'
            },
            success: function(response) {
                $spinner.removeClass('is-active');
                if (response.success) {
                    $result.html(
                        '<div class="notice notice-success"><p>' + response.data.message + '</p></div>'
                    ).addClass('success').removeClass('error');

                    // Update the display
                    if (response.data.key_preview) {
                        $('.api-key-status code').text(response.data.key_preview);
                    }

                    // Clear input
                    $input.val('');

                    // Reload page after 1 second to refresh API status
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    $result.html(
                        '<div class="notice notice-error"><p>' + response.data.message + '</p></div>'
                    ).addClass('error').removeClass('success');
                }
            },
            error: function() {
                $spinner.removeClass('is-active');
                $result.html(
                    '<div class="notice notice-error"><p>An error occurred. Please try again.</p></div>'
                ).addClass('error').removeClass('success');
            }
        });
    });

    // Clear API key
    $('#clear-api-key-btn').on('click', function() {
        if (!confirm('Are you sure you want to clear the API key?')) {
            return;
        }

        var $btn = $(this);
        var $form = $('#api-key-form');
        var $spinner = $form.find('.spinner');
        var $result = $form.find('.api-key-result');

        $spinner.addClass('is-active');
        $result.html('').removeClass('error success');

        $.ajax({
            url: jwwShowImporter.ajaxUrl,
            type: 'POST',
            data: {
                action: 'jww_save_api_key',
                nonce: jwwShowImporter.nonce,
                action_type: 'clear'
            },
            success: function(response) {
                $spinner.removeClass('is-active');
                if (response.success) {
                    $result.html(
                        '<div class="notice notice-success"><p>' + response.data.message + '</p></div>'
                    ).addClass('success').removeClass('error');

                    // Reload page after 1 second to refresh status
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    $result.html(
                        '<div class="notice notice-error"><p>' + response.data.message + '</p></div>'
                    ).addClass('error').removeClass('success');
                }
            },
            error: function() {
                $spinner.removeClass('is-active');
                $result.html(
                    '<div class="notice notice-error"><p>An error occurred. Please try again.</p></div>'
                ).addClass('error').removeClass('success');
            }
        });
    });
});
