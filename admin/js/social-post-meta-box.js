/**
 * Social Share meta box: share from post edit sidebar (song, show, post).
 */
(function ($) {
	'use strict';

	var config = window.jwwSocialShare;
	if (!config || !config.nonce || !config.channels || !config.channels.length) {
		return;
	}

	function getLineIndex($panel, shareType) {
		if (shareType !== 'lyric') {
			return null;
		}
		var $select = $panel.find('.jww-social-lyric-line-select');
		return $select.length ? $select.val() : 'random';
	}

	function share(shareType, channel, lineIndex, postId) {
		if (!postId) {
			return $.Deferred().reject();
		}
		var data = {
			action: 'jww_social_share_from_editor',
			nonce: config.nonce,
			post_id: postId,
			share_type: shareType,
			channel: channel || 'all'
		};
		if (shareType === 'lyric' && lineIndex != null) {
			data.line_index = lineIndex;
		}
		return $.post(config.ajaxUrl, data);
	}

	function setLoading($btn, loading) {
		if (loading) {
			$btn.addClass('is-loading').prop('disabled', true);
		} else {
			$btn.removeClass('is-loading').prop('disabled', false);
		}
	}

	function showFeedback($btn, success, message) {
		var text = $btn.text();
		$btn.text(message || (success ? config.i18n.success : config.i18n.error));
		setTimeout(function () {
			$btn.text(text);
		}, 2000);
	}

	$(document).on('click', '.jww-social-share-btn', function () {
		var $btn = $(this);
		var $wrap = $btn.closest('.jww-social-share-buttons');
		var shareType = $wrap.data('share-type');
		var channel = $btn.data('channel');
		var $panel = $btn.closest('.jww-social-share-panel');
		var lineIndex = getLineIndex($panel, shareType);

		setLoading($btn, true);
		share(shareType, channel, lineIndex, $panel.data('post-id'))
			.done(function (res) {
				if (res.success) {
					showFeedback($btn, true, config.i18n.success);
				} else {
					showFeedback($btn, false, (res.data && res.data.message) || config.i18n.error);
				}
			})
			.fail(function () {
				showFeedback($btn, false, config.i18n.error);
			})
			.always(function () {
				setLoading($btn, false);
			});
	});

	$(document).on('click', '.jww-social-share-all', function () {
		var $btn = $(this);
		var $wrap = $btn.closest('.jww-social-share-all-wrap');
		var shareType = $wrap.data('share-type');
		var $panel = $btn.closest('.jww-social-share-panel');
		var lineIndex = getLineIndex($panel, shareType);

		setLoading($btn, true);
		share(shareType, 'all', lineIndex, $panel.data('post-id'))
			.done(function (res) {
				if (res.success) {
					showFeedback($btn, true, config.i18n.success);
				} else {
					showFeedback($btn, false, (res.data && res.data.message) || config.i18n.error);
				}
			})
			.fail(function () {
				showFeedback($btn, false, config.i18n.error);
			})
			.always(function () {
				setLoading($btn, false);
			});
	});
})(jQuery);
