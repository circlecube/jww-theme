/**
 * Setlist repeater admin: compact collapsed row summary, click to expand/collapse.
 *
 * - Builds a one-line summary from entry_type + song/song_text/note + notes + duration.
 * - Replaces the default collapsed label with this summary.
 * - Click summary → expand row; click elsewhere or another row → collapse.
 */
(function ($) {
	'use strict';

	var config = window.jwwSetlistRepeaterAdmin || {};
	var setlistKey = config.setlistFieldKey || 'field_show_setlist';
	var entryTypeLabels = config.entryTypeLabels || { 'song-post': 'Song', 'note': 'Note', 'song-text': 'Song Text' };

	function getFieldInRow($row, name) {
		return $row.find('.acf-field[data-name="' + name + '"]');
	}

	function getEntryType($row) {
		var $field = getFieldInRow($row, 'entry_type');
		var val = $field.find('select').val() || 'song-post';
		return val;
	}

	function getSongTitle($row) {
		// ACF relationship: selected values are in .values-list li span.acf-rel-item (title is the span's text).
		var $field = getFieldInRow($row, 'song');
		var $item = $field.find('.values-list .acf-rel-item, .values .acf-rel-item').first();
		if ($item.length) {
			return $item.clone().children().remove().end().text().trim();
		}
		return '';
	}

	function getSongText($row) {
		var $field = getFieldInRow($row, 'song_text');
		return ($field.find('input[type="text"]').val() || '').trim();
	}

	function getNotes($row) {
		var $field = getFieldInRow($row, 'notes');
		return ($field.find('textarea').val() || '').trim();
	}

	function getDuration($row) {
		var $field = getFieldInRow($row, 'duration');
		return ($field.find('input').val() || '').trim();
	}

	function buildSummary($row) {
		var type = getEntryType($row);
		var typeLabel = entryTypeLabels[type] || type;
		// var parts = [typeLabel];
		var parts = [];

		if (type === 'song-post') {
			var title = getSongTitle($row);
			parts.push(title || 'No Song Selected');
		} else if (type === 'song-text') {
			var text = getSongText($row);
			parts.push(text || 'No Song Text Entered');
		} else {
			// note
			var noteText = getNotes($row);
			parts.push(noteText ? noteText.replace(/\s+/g, ' ').substring(0, 50) + (noteText.length > 50 ? '…' : '') : '—');
		}

		var notes = getNotes($row);
		if (notes && type !== 'note') {
			parts.push(notes.replace(/\s+/g, ' ').substring(0, 40) + (notes.length > 40 ? '…' : ''));
		}

		var duration = getDuration($row);
		if (duration) {
			parts.push(duration);
		}

		return parts.join(' · ');
	}

	function ensureSummaryNode($row) {
		// Place summary inside the row's content cell (td.acf-fields) so layout matches normal row content.
		var $fieldsCell = $row.find('td.acf-fields').first();
		if (!$fieldsCell.length) return null;
		var $existing = $fieldsCell.find('.jww-setlist-row-summary');
		if ($existing.length) return $existing;
		var $span = $('<span class="jww-setlist-row-summary" role="button" tabindex="0" title="Click to expand row"></span>');
		$fieldsCell.prepend($span);
		return $span;
	}

	function updateRowSummary($row) {
		var $summary = ensureSummaryNode($row);
		if (!$summary) return;
		$summary.text(buildSummary($row));
	}

	function getRows($repeater) {
		// ACF wraps repeater in .acf-field[data-key] > .acf-input > .acf-repeater > table.acf-table > tbody > tr.acf-row
		return $repeater.find('.acf-row');
	}

	function updateAllSummaries($repeater) {
		getRows($repeater).each(function () {
			updateRowSummary($(this));
		});
	}

	function collapseRow($row) {
		$row.addClass('-collapsed');
	}

	function expandRow($row) {
		$row.removeClass('-collapsed');
	}

	function initRepeater($repeater) {
		var $rep = $repeater;
		// Start with all rows collapsed
		getRows($rep).addClass('-collapsed');
		updateAllSummaries($rep);

		// Click on summary or handle (except order/drag icons) → expand this row, collapse others
		$rep.on('click', '.jww-setlist-row-summary, .acf-row-handle', function (e) {
			var $target = $(e.target);
			if ($target.closest('.acf-icon').length) return; // allow drag/order to work
			var $row = $target.closest('.acf-row');
			if (!$row.length) return;
			e.preventDefault();
			getRows($rep).each(function () {
				if (this === $row[0]) {
					expandRow($(this));
				} else {
					collapseRow($(this));
				}
			});
		});

		// Keyboard: Enter/Space on summary toggles (summary lives in .acf-fields, not handle)
		$rep.on('keydown', '.jww-setlist-row-summary', function (e) {
			if (e.key === 'Enter' || e.key === ' ') {
				e.preventDefault();
				$(this).trigger('click');
			}
		});

		// Click outside repeater → collapse all
		$(document).on('click', function (e) {
			if ($(e.target).closest('[data-key="' + setlistKey + '"]').length) return;
			getRows($rep).addClass('-collapsed');
		});

		// Update summary when fields change
		$rep.on('change input', 'select, input, textarea', function () {
			var $row = $(this).closest('.acf-row');
			if ($row.length) updateRowSummary($row);
		});

		// When relationship list is updated (ACF may use a custom event or DOM mutation), update summary
		$rep.on('change', '.acf-relationship select, .acf-relationship input', function () {
			var $row = $(this).closest('.acf-row');
			if ($row.length) {
				setTimeout(function () { updateRowSummary($row); }, 100);
			}
		});
	}

	function run() {
		var $repeater = $('[data-key="' + setlistKey + '"]').not('[data-jww-setlist-inited="1"]');
		if (!$repeater.length) return;
		$repeater.each(function () {
			var $el = $(this);
			$el.attr('data-jww-setlist-inited', '1');
			initRepeater($el);
		});
	}

	// Run after ACF has initialized so the repeater DOM is ready
	function runWhenReady() {
		var $repeater = $('[data-key="' + setlistKey + '"]');
		if (!$repeater.length) return;
		run();
	}

	$(function () {
		if (typeof acf !== 'undefined' && acf.addAction) {
			acf.addAction('ready', runWhenReady);
		}
		// Fallback: run after short delay in case ACF ready fires before our script or is not present
		setTimeout(runWhenReady, 600);
	});

	// Re-run when ACF adds a new row (append event)
	if (typeof acf !== 'undefined' && acf.addAction) {
		acf.addAction('append', function ($el) {
			var $rep = $el.closest('[data-key="' + setlistKey + '"]');
			if ($rep.length) {
				updateAllSummaries($rep);
				getRows($rep).addClass('-collapsed');
			}
		});
	}

})(jQuery);
