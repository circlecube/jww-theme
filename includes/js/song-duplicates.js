jQuery(document).ready(function($) {
	'use strict';

	// Handle checkbox selection
	$('.song-select').on('change', function() {
		var $row = $(this).closest('tr');
		var $group = $(this).closest('.duplicate-group');
		var selectedCount = $group.find('.song-select:checked').length;
		var $mergeButton = $group.find('.merge-selected');

		if (selectedCount >= 2) {
			$mergeButton.prop('disabled', false);
		} else {
			$mergeButton.prop('disabled', true);
		}
	});

	// Handle view videos link
	$(document).on('click', '.view-videos', function(e) {
		e.preventDefault();
		var songId = $(this).data('song-id');
		
		$.ajax({
			url: jwwSongDuplicates.ajaxUrl,
			type: 'POST',
			data: {
				action: 'jww_get_video_details',
				nonce: jwwSongDuplicates.nonce,
				song_id: songId
			},
			success: function(response) {
				if (response.success) {
					displayVideoDetails(response.data.videos, songId);
				} else {
					alert('Error: ' + response.data.message);
				}
			},
			error: function() {
				alert('Error loading video details');
			}
		});
	});

	// Display video details in modal
	function displayVideoDetails(videos, songId) {
		var html = '<h3>Videos for Song ID: ' + songId + '</h3>';
		
		if (videos.length === 0) {
			html += '<p>No videos found for this song.</p>';
		} else {
			html += '<ul>';
			videos.forEach(function(video) {
				html += '<li><strong>' + video.source.toUpperCase() + ':</strong> ';
				if (video.url) {
					html += '<a href="' + video.url + '" target="_blank">' + video.url + '</a>';
				} else {
					html += 'No URL';
				}
				if (video.date) {
					html += ' <em>(Date: ' + video.date + ')</em>';
				}
				html += '</li>';
			});
			html += '</ul>';
		}

		$('#video-details-body').html(html);
		$('#video-details-modal').fadeIn();
	}

	// Close modal
	$('.video-details-close, #video-details-modal').on('click', function(e) {
		if (e.target === this) {
			$('#video-details-modal').fadeOut();
		}
	});

	// Handle merge button
	$('.merge-selected').on('click', function(e) {
		e.preventDefault();
		
		var $group = $(this).closest('.duplicate-group');
		var selectedIds = [];
		$group.find('.song-select:checked').each(function() {
			selectedIds.push($(this).val());
		});

		if (selectedIds.length < 2) {
			alert('Please select at least 2 songs to merge.');
			return;
		}

		var groupTitle = $(this).data('group-title');
		var confirmMessage = 'Are you sure you want to merge ' + selectedIds.length + ' songs?\n\n';
		confirmMessage += 'This will:\n';
		confirmMessage += '- Keep the oldest song\n';
		confirmMessage += '- Combine all videos into that song\n';
		confirmMessage += '- Update all show setlist references\n';
		confirmMessage += '- Delete the duplicate songs\n\n';
		confirmMessage += 'This action cannot be undone!';

		if (!confirm(confirmMessage)) {
			return;
		}

		var $button = $(this);
		$button.prop('disabled', true).text('Merging...');

		$.ajax({
			url: jwwSongDuplicates.ajaxUrl,
			type: 'POST',
			data: {
				action: 'jww_merge_songs',
				nonce: jwwSongDuplicates.nonce,
				song_ids: selectedIds
			},
			success: function(response) {
				if (response.success) {
					alert('Songs merged successfully! The page will reload.');
					location.reload();
				} else {
					alert('Error: ' + response.data.message);
					$button.prop('disabled', false).text('Merge Selected Songs');
				}
			},
			error: function() {
				alert('Error merging songs');
				$button.prop('disabled', false).text('Merge Selected Songs');
			}
		});
	});
});
