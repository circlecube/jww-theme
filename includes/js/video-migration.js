jQuery(document).ready(function($) {
	'use strict';

	var isRunning = false;

	// Load migration status on page load
	loadMigrationStatus();
	loadNextSongInfo();

	function loadMigrationStatus() {
		$.ajax({
			url: jwwVideoMigration.ajaxUrl,
			type: 'POST',
			data: {
				action: 'jww_get_migration_status',
				nonce: jwwVideoMigration.nonce
			},
			success: function(response) {
				if (response.success) {
					var status = response.data.status;
					$('#status-total').text(status.total_songs);
					$('#status-old-fields').text(status.songs_with_old_fields);
					$('#status-migrated').text(status.already_migrated);
					$('#status-needs').html('<strong>' + status.needs_migration + '</strong>');
					
					$('#status-loading').hide();
					$('#status-table').show();

					// Show/hide actions based on needs_migration
					$('#actions-loading').hide();
					$('#actions-content').show();
					
					if (status.needs_migration > 0) {
						$('#actions-needs-migration').show();
						$('#actions-all-migrated').hide();
					} else {
						$('#actions-needs-migration').hide();
						$('#actions-all-migrated').show();
					}
				} else {
					$('#status-loading').html('<p style="color: red;">Error loading status: ' + response.data.message + '</p>');
				}
			},
			error: function() {
				$('#status-loading').html('<p style="color: red;">Error loading migration status</p>');
			}
		});
	}

	// Load info about next unmigrated song
	function loadNextSongInfo() {
		$.ajax({
			url: jwwVideoMigration.ajaxUrl,
			type: 'POST',
			data: {
				action: 'jww_get_next_unmigrated_song',
				nonce: jwwVideoMigration.nonce
			},
			success: function(response) {
				if (response.success && response.data.song) {
					var song = response.data.song;
					$('#next-song-info').html(
						'<strong>Next:</strong> "' + song.title + '" (ID: ' + song.id + ', Published: ' + song.date + ')'
					);
				} else {
					$('#next-song-info').html('<em>No unmigrated songs found.</em>');
					$('#migrate-next-song').prop('disabled', true);
				}
			},
			error: function() {
				$('#next-song-info').html('<em>Error loading next song info.</em>');
			}
		});
	}

	// Migrate next song
	$('#migrate-next-song').on('click', function() {
		if (isRunning) {
			return;
		}

		if (!confirm('Migrate the next unmigrated song? This will convert old video fields to the new repeater format.')) {
			return;
		}

		isRunning = true;
		var $button = $(this);
		$button.prop('disabled', true).text('Migrating...');

		$.ajax({
			url: jwwVideoMigration.ajaxUrl,
			type: 'POST',
			data: {
				action: 'jww_migrate_single_song',
				nonce: jwwVideoMigration.nonce
			},
			success: function(response) {
				if (response.success) {
					var result = response.data.result;
					var message = result.message;
					if (result.videos_count !== undefined) {
						message += ' (' + result.videos_count + ' video(s) migrated)';
					}
					alert(message);
					
					// Show details if available
					if (result.videos && result.videos.length > 0) {
						console.log('Migrated videos:', result.videos);
					}
					
					// Refresh status and next song info after migration
					setTimeout(function() {
						loadMigrationStatus();
						loadNextSongInfo();
						$button.prop('disabled', false).text('Migrate Next Song');
					}, 500);
				} else {
					alert('Error: ' + response.data.message);
					$button.prop('disabled', false).text('Migrate Next Song');
				}
			},
			error: function() {
				alert('Error migrating song');
				$button.prop('disabled', false).text('Migrate Next Song');
			},
			complete: function() {
				isRunning = false;
			}
		});
	});

	$('#start-migration').on('click', function() {
		if (isRunning) {
			return;
		}

		if (!confirm('Are you sure you want to migrate all videos? This will convert old video fields to the new repeater format.')) {
			return;
		}

		startMigration(false);
	});

	$('#preview-migration').on('click', function() {
		if (isRunning) {
			return;
		}

		startMigration(true);
	});

	function startMigration(preview) {
		isRunning = true;
		$('#migration-progress').show();
		$('#migration-log').html('<p>Starting migration...</p>');
		$('#progress-bar').css('width', '0%');
		$('#progress-text').text('0%');

		$.ajax({
			url: jwwVideoMigration.ajaxUrl,
			type: 'POST',
			data: {
				action: 'jww_migrate_videos',
				nonce: jwwVideoMigration.nonce,
				preview: preview ? 'true' : 'false'
			},
			success: function(response) {
				if (response.success) {
					var result = response.data.result;
					var logHtml = '<p><strong>Migration ' + (preview ? 'Preview' : '') + ' Complete!</strong></p>';
					logHtml += '<ul>';
					
					result.log.forEach(function(line) {
						logHtml += '<li>' + line + '</li>';
					});
					
					logHtml += '</ul>';
					logHtml += '<p><strong>Summary:</strong></p>';
					logHtml += '<ul>';
					logHtml += '<li>Migrated: ' + result.migrated + '</li>';
					logHtml += '<li>Skipped: ' + result.skipped + '</li>';
					if (result.errors > 0) {
						logHtml += '<li>Errors: ' + result.errors + '</li>';
					}
					logHtml += '</ul>';

					$('#migration-log').html(logHtml);
					$('#progress-bar').css('width', '100%');
					$('#progress-text').text('100%');

					if (!preview) {
						// Refresh status after migration
						setTimeout(function() {
							loadMigrationStatus();
						}, 1000);
					}
				} else {
					$('#migration-log').append('<p style="color: red;">Error: ' + response.data.message + '</p>');
				}
			},
			error: function() {
				$('#migration-log').append('<p style="color: red;">Error: Failed to communicate with server</p>');
			},
			complete: function() {
				isRunning = false;
			}
		});
	}
});
