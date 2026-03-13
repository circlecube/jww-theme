/**
 * Social Sharing admin: trigger buttons and event log.
 */
(function ($) {
	'use strict';

	var $log = $('#jww-social-log');
	var $empty = $log.find('.jww-social-log-empty');

	function appendLog(lines, hasError) {
		if ($empty.length) {
			$empty.remove();
		}
		lines.forEach(function (line) {
			var $line = $('<div class="jww-social-log-line"/>').text(line);
			if (hasError && line.indexOf('Error') !== -1) {
				$line.addClass('jww-social-log-error');
			}
			$log.append($line);
		});
		$log[0].scrollTop = $log[0].scrollHeight;
	}

	function setButtonLoading($btn, loading) {
		if (loading) {
			$btn.addClass('is-loading').data('original-text', $btn.text());
			$btn.text(window.jwwSocialAdmin.i18n.triggering);
		} else {
			$btn.removeClass('is-loading').text($btn.data('original-text') || $btn.text());
		}
	}

	// Tab switching: show panel and set active tab.
	$('.jww-social-nav-tabs .nav-tab').on('click', function (e) {
		e.preventDefault();
		var tab = $(this).data('tab');
		var $target = $('#' + 'jww-social-tab-' + tab);
		if (!$target.length) {
			return;
		}
		$('.jww-social-nav-tabs .nav-tab').removeClass('nav-tab-active').attr('aria-selected', 'false');
		$(this).addClass('nav-tab-active').attr('aria-selected', 'true');
		$('.jww-social-tab-panel').attr('hidden', true);
		$target.attr('hidden', false);
		if (window.history && window.history.replaceState) {
			window.history.replaceState(null, '', '#' + 'jww-social-tab-' + tab);
		}
	});

	// Open tab from hash on load.
	(function () {
		var hash = window.location.hash;
		var $panel = hash ? $(hash) : null;
		if ($panel && $panel.hasClass('jww-social-tab-panel')) {
			var tabId = $panel.attr('id');
			var tab = tabId ? tabId.replace('jww-social-tab-', '') : '';
			if (tab) {
				$('.jww-social-nav-tabs .nav-tab').removeClass('nav-tab-active').attr('aria-selected', 'false');
				$('.jww-social-nav-tabs .nav-tab[data-tab="' + tab + '"]').addClass('nav-tab-active').attr('aria-selected', 'true');
				$('.jww-social-tab-panel').attr('hidden', true);
				$panel.attr('hidden', false);
			}
		}
	})();

	// Lyric line dropdown: when song is selected, fetch lyrics via AJAX.
	$('#jww-social-select-lyric-song').on('change', function () {
		var songId = $(this).val();
		var $lyricSelect = $('#jww-social-select-lyric-line');
		if (!songId || !window.jwwSocialAdmin || !window.jwwSocialAdmin.nonce) {
			$lyricSelect.prop('disabled', true).find('option').remove().end().append($('<option value="">').text(window.jwwSocialAdmin.i18n.selectLyric ? 'Select a song first' : 'Select a song first'));
			return;
		}
		$lyricSelect.prop('disabled', true).html('<option value="">Loading…</option>');
		$.post(window.jwwSocialAdmin.ajaxUrl, {
			action: 'jww_social_get_lyrics_for_song',
			nonce: window.jwwSocialAdmin.nonce,
			song_id: songId
		}).done(function (res) {
			var $sel = $('#jww-social-select-lyric-line');
			$sel.find('option').remove();
			$sel.append($('<option value="">').text(window.jwwSocialAdmin.i18n.selectLyric || 'Select a line…'));
			$sel.append($('<option value="random">').text(window.jwwSocialAdmin.i18n.random || 'Random'));
			if (res.success && res.data && res.data.lines && res.data.lines.length) {
				res.data.lines.forEach(function (item) {
					var label = item.label != null ? item.label : ((item.text || '').length > 60 ? (item.text || '').substring(0, 57) + '…' : (item.text || ''));
					$sel.append($('<option>').attr('value', item.index).text('Section ' + (item.index + 1) + ': ' + label));
				});
			}
			$sel.prop('disabled', false);
		}).fail(function () {
			$('#jww-social-select-lyric-line').prop('disabled', false).find('option').remove().end().append($('<option value="">').text('Error loading lyrics')).append($('<option value="random">').text(window.jwwSocialAdmin.i18n.random || 'Random'));
		});
	});

	// Share selected (specific post) buttons.
	$('.jww-social-share-specific').on('click', function () {
		var $btn = $(this);
		var type = $btn.data('type');
		var selectId = $btn.data('select-id');
		var lyricSelectId = $btn.data('lyric-select-id');
		if (!type || !selectId || !window.jwwSocialAdmin || !window.jwwSocialAdmin.nonce) {
			return;
		}
		var $postSelect = $('#' + selectId);
		var postId = $postSelect.val();
		if (!postId) {
			return;
		}
		var action = 'jww_social_share_specific_' + type;
		var data = { action: action, nonce: window.jwwSocialAdmin.nonce };
		if (type === 'lyric') {
			data.song_id = postId;
			data.line_index = lyricSelectId ? ($('#' + lyricSelectId).val() || 'random') : 'random';
		} else {
			data.post_id = postId;
		}
		setButtonLoading($btn, true);
		$.post(window.jwwSocialAdmin.ajaxUrl, data)
			.done(function (res) {
				if (res.success && res.data && res.data.log && res.data.log.length) {
					var hasError = res.data.results && Object.keys(res.data.results).some(function (ch) {
						return res.data.results[ch] && !res.data.results[ch].ok;
					});
					appendLog(res.data.log, hasError);
				}
			})
			.fail(function () {
				appendLog([new Date().toISOString().replace('T', ' ').slice(0, 19) + ' — ' + (window.jwwSocialAdmin.i18n.error || 'Request failed.')], true);
			})
			.always(function () {
				setButtonLoading($btn, false);
			});
	});

	// Cron schedule dropdown: save and reschedule.
	$('.jww-social-cron-schedule').on('change', function () {
		var $sel = $(this);
		var type = $sel.data('type');
		var hours = $sel.val();
		if (!type || !window.jwwSocialAdmin || !window.jwwSocialAdmin.settingsNonce) {
			return;
		}
		// Show/hide message template row for this cron type.
		var $row = $('.jww-social-cron-template-row[data-cron-type="' + type + '"]');
		if ($row.length) {
			var enabled = hours !== '0' && hours !== '';
			$row.toggleClass('jww-social-cron-template-row-visible', enabled);
			$row.attr('aria-hidden', enabled ? 'false' : 'true');
		}
		$sel.prop('disabled', true);
		$.post(window.jwwSocialAdmin.ajaxUrl, {
			action: 'jww_social_save_cron_schedule',
			nonce: window.jwwSocialAdmin.settingsNonce,
			type: type,
			hours: hours
		}).done(function (res) {
			if (res.success) {
				var $feedback = $sel.closest('td').find('.jww-social-cron-saved');
				if (!$feedback.length) {
					$feedback = $('<span class="jww-social-cron-saved"/>');
					$sel.closest('td').append($feedback);
				}
				$feedback.text(window.jwwSocialAdmin.i18n.cronSaved || 'Schedule saved.').addClass('is-visible');
				setTimeout(function () {
					$feedback.removeClass('is-visible');
				}, 2000);
			}
		}).always(function () {
			$sel.prop('disabled', false);
		});
	});

	// Cron hour dropdown: save and reschedule.
	$('.jww-social-cron-hour').on('change', function () {
		var $sel = $(this);
		var type = $sel.data('type');
		var hour = $sel.val();
		if (!type || hour === undefined || hour === '' || !window.jwwSocialAdmin || !window.jwwSocialAdmin.settingsNonce) {
			return;
		}
		$sel.prop('disabled', true);
		$.post(window.jwwSocialAdmin.ajaxUrl, {
			action: 'jww_social_save_cron_hour',
			nonce: window.jwwSocialAdmin.settingsNonce,
			type: type,
			hour: hour
		}).done(function (res) {
			if (res.success) {
				var $feedback = $sel.closest('td').find('.jww-social-cron-saved');
				if (!$feedback.length) {
					$feedback = $('<span class="jww-social-cron-saved"/>');
					$sel.closest('td').append($feedback);
				}
				$feedback.text(window.jwwSocialAdmin.i18n.cronSaved || 'Schedule saved.').addClass('is-visible');
				setTimeout(function () {
					$feedback.removeClass('is-visible');
				}, 2000);
			}
		}).always(function () {
			$sel.prop('disabled', false);
		});
	});

	// On-publish toggles: save when changed.
	$('.jww-social-on-publish-toggle').on('change', function () {
		var $input = $(this);
		var type = $input.data('type');
		var enabled = $input.prop('checked') ? '1' : '0';
		if (!type || !window.jwwSocialAdmin || !window.jwwSocialAdmin.settingsNonce) {
			return;
		}
		// Show/hide message template in this row when trigger is enabled.
		var $item = $input.closest('.jww-social-on-publish-item');
		var $template = $item.find('.jww-social-message-template');
		if ($template.length) {
			$template.toggleClass('jww-social-message-template-visible', $input.prop('checked'));
			$template.attr('aria-hidden', $input.prop('checked') ? 'false' : 'true');
		}
		$input.prop('disabled', true);
		$.post(window.jwwSocialAdmin.ajaxUrl, {
			action: 'jww_social_save_on_publish',
			nonce: window.jwwSocialAdmin.settingsNonce,
			type: type,
			enabled: enabled
		}).done(function (res) {
			if (res.success) {
				var $wrap = $input.closest('.jww-social-toggle-wrap');
				var $feedback = $wrap.find('.jww-social-toggle-saved');
				if (!$feedback.length) {
					$feedback = $('<span class="jww-social-toggle-saved"/>');
					$wrap.append($feedback);
				}
				$feedback.text(window.jwwSocialAdmin.i18n.saved || 'Saved.').addClass('is-visible');
				setTimeout(function () {
					$feedback.removeClass('is-visible');
				}, 1500);
			}
		}).fail(function () {
			$input.prop('checked', enabled !== '1');
		}).always(function () {
			$input.prop('disabled', false);
		});
	});

	$('.jww-social-trigger').on('click', function () {
		var $btn = $(this);
		var action = $btn.data('action');
		if (!action || !window.jwwSocialAdmin || !window.jwwSocialAdmin.nonce) {
			return;
		}
		setButtonLoading($btn, true);
		$.ajax({
			url: window.jwwSocialAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: action,
				nonce: window.jwwSocialAdmin.nonce
			},
			success: function (res) {
				if (res.success && res.data && res.data.log && res.data.log.length) {
					var hasError = res.data.results && Object.keys(res.data.results).some(function (ch) {
						return res.data.results[ch] && !res.data.results[ch].ok;
					});
					appendLog(res.data.log, hasError);
				}
			},
			error: function () {
				appendLog([new Date().toISOString().replace('T', ' ').slice(0, 19) + ' — ' + window.jwwSocialAdmin.i18n.error], true);
			},
			complete: function () {
				setButtonLoading($btn, false);
			}
		});
	});

	$('#jww-social-log-clear').on('click', function () {
		$log.empty();
		$log.append($('<p class="jww-social-log-empty"/>').text(window.jwwSocialAdmin.i18n.emptyLog || 'Trigger an event above to see results here.'));
	});

	// Status text template: save on blur.
	$('.jww-social-status-textarea').on('blur', function () {
		var $ta = $(this);
		var key = $ta.data('template-key');
		if (!key || !window.jwwSocialAdmin || !window.jwwSocialAdmin.settingsNonce) {
			return;
		}
		$.post(window.jwwSocialAdmin.ajaxUrl, {
			action: 'jww_social_save_status_text',
			nonce: window.jwwSocialAdmin.settingsNonce,
			template_key: key,
			value: $ta.val()
		}).done(function (res) {
			if (res.success) {
				var $wrap = $ta.closest('.jww-social-message-template, .jww-social-message-template-inline');
				var $feedback = $wrap.find('.jww-social-status-text-saved');
				if (!$feedback.length) {
					$feedback = $('<span class="jww-social-status-text-saved description"/>');
					$ta.after($feedback);
				}
				$feedback.text(window.jwwSocialAdmin.i18n.saved || 'Saved.').addClass('is-visible');
				setTimeout(function () {
					$feedback.removeClass('is-visible');
				}, 1500);
			}
		});
	});

	// Channel enable/disable toggles (include in posts).
	$('.jww-social-channel-toggle').on('change', function () {
		var $input = $(this);
		var channel = $input.data('channel');
		var enabled = $input.prop('checked') ? '1' : '0';
		if (!channel || !window.jwwSocialAdmin || !window.jwwSocialAdmin.toggleNonce) {
			return;
		}
		$input.prop('disabled', true);
		$.ajax({
			url: window.jwwSocialAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'jww_social_toggle_channel',
				nonce: window.jwwSocialAdmin.toggleNonce,
				channel: channel,
				enabled: enabled
			},
			success: function (res) {
				if (res.success && window.jwwSocialAdmin.enabled) {
					window.jwwSocialAdmin.enabled[channel] = res.data && res.data.enabled;
				}
				var $wrap = $input.closest('.jww-social-toggle-wrap');
				var $feedback = $wrap.find('.jww-social-toggle-saved');
				if (!$feedback.length) {
					$feedback = $('<span class="jww-social-toggle-saved"/>');
					$wrap.append($feedback);
				}
				$feedback.text(window.jwwSocialAdmin.i18n.saved || 'Saved.').addClass('is-visible');
				setTimeout(function () {
					$feedback.removeClass('is-visible');
				}, 1500);
			},
			error: function () {
				$input.prop('checked', enabled !== '1');
			},
			complete: function () {
				$input.prop('disabled', false);
			}
		});
	});
})(jQuery);
