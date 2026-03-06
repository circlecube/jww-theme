/**
 * Admin: Import chord sheet (modal with paste | parsed, auto-parse, copy to field) and Import tab (ASCII → VexTab).
 * ChordSheetJS: try UltimateGuitarParser, then ChordsOverWordsParser, then ChordProParser; output ChordPro via ChordProFormatter.
 */
(function ($) {
	'use strict';

	var config = typeof jwwSongImportChordsTabs !== 'undefined' ? jwwSongImportChordsTabs : {};
	var chordSheetFieldKey = config.fieldChordSheet || 'field_68cad0001';
	var tabsFieldKey = config.fieldTabs || 'field_68cad0002';

	var parseDebounceMs = 350;
	var parseDebounceTimer = null;

	// Move Import chord sheet block from footer to below Chord sheet field and show it.
	function placeImportChordSheetBlock() {
		var block = document.getElementById('jww-import-chord-sheet-block');
		if (!block) return;
		var container = document.querySelector('.acf-field[data-key="' + chordSheetFieldKey + '"]') ||
			document.querySelector('[data-key="' + chordSheetFieldKey + '"]');
		if (!container) return;
		container.parentNode.insertBefore(block, container.nextSibling);
		block.style.display = '';
	}

	function placeImportTabsBlock() {
		var block = document.getElementById('jww-import-tabs-block');
		if (!block) return;
		var container = document.querySelector('.acf-field[data-key="' + tabsFieldKey + '"]') ||
			document.querySelector('[data-key="' + tabsFieldKey + '"]');
		if (!container) return;
		container.parentNode.insertBefore(block, container.nextSibling);
		block.style.display = '';
	}

	$(function () {
		placeImportChordSheetBlock();
		placeImportTabsBlock();
		setTimeout(placeImportChordSheetBlock, 600);
		setTimeout(placeImportTabsBlock, 600);
	});

	function getChordSheetTextarea() {
		return document.querySelector('[data-key="' + chordSheetFieldKey + '"] textarea') ||
			document.querySelector('textarea[name="acf[' + chordSheetFieldKey + ']"]');
	}

	function getTabsTextarea() {
		return document.querySelector('[data-key="' + tabsFieldKey + '"] textarea') ||
			document.querySelector('textarea[name="acf[' + tabsFieldKey + ']"]');
	}

	function checkChordSheetJS() {
		if (typeof window.ChordSheetJS === 'undefined') return false;
		var CS = window.ChordSheetJS;
		return typeof CS.UltimateGuitarParser === 'function' &&
			typeof CS.ChordProParser === 'function' &&
			typeof CS.ChordProFormatter === 'function' &&
			(typeof CS.ChordsOverWordsParser === 'function' || true);
	}

	/**
	 * Parse chord sheet text: try UG, then ChordsOverWords (chords above lyrics), then ChordPro.
	 * Return { song, chordPro } with ChordPro string (chords in brackets inline with lyrics).
	 */
	function parseChordSheet(text) {
		if (!text || typeof text !== 'string') return { song: null, chordPro: null };
		var ChordSheetJS = window.ChordSheetJS;
		if (!ChordSheetJS) return { song: null, chordPro: null };
		var trimmed = text.trim();
		if (!trimmed) return { song: null, chordPro: null };

		var song = null;
		var parsers = [];

		if (trimmed.indexOf('{') >= 0) {
			parsers.push({ name: 'ChordPro', P: ChordSheetJS.ChordProParser });
		}
		parsers.push({ name: 'UltimateGuitar', P: ChordSheetJS.UltimateGuitarParser });
		if (typeof ChordSheetJS.ChordsOverWordsParser === 'function') {
			parsers.push({ name: 'ChordsOverWords', P: ChordSheetJS.ChordsOverWordsParser });
		}

		for (var i = 0; i < parsers.length; i++) {
			try {
				var parser = new parsers[i].P();
				song = parser.parse(trimmed);
				if (song) break;
			} catch (e) {
				// try next
			}
		}

		if (!song) return { song: null, chordPro: null };

		var chordPro = null;
		try {
			chordPro = new ChordSheetJS.ChordProFormatter().format(song);
			if (chordPro) chordPro = stripEmptyChordBrackets(chordPro);
		} catch (e) {
			chordPro = null;
		}
		return { song: song, chordPro: chordPro };
	}

	function formatChordSheetHtml(song) {
		var ChordSheetJS = window.ChordSheetJS;
		if (!ChordSheetJS || !song) return '';
		try {
			return new ChordSheetJS.HtmlTableFormatter().format(song);
		} catch (e) {
			try {
				return new ChordSheetJS.HtmlDivFormatter().format(song);
			} catch (e2) {
				return '';
			}
		}
	}

	function escapeHtml(s) {
		var div = document.createElement('div');
		div.textContent = s;
		return div.innerHTML;
	}

	/**
	 * Remove empty chord brackets from ChordPro output.
	 * ChordSheetJS can emit [ ] or [     ] when text precedes the first chord on a line; strip these.
	 */
	function stripEmptyChordBrackets(chordPro) {
		if (!chordPro || typeof chordPro !== 'string') return chordPro;
		return chordPro.replace(/\[\s*\]/g, '');
	}

	// ——— Import chord sheet modal ———
	var $modal = $('#jww-import-chords-modal');
	var $paste = $('#jww-import-chords-paste');
	var $parsed = $('#jww-import-chords-parsed');

	function runAutoParse() {
		var raw = $paste.val();
		$parsed.removeClass('jww-parse-error');
		if (!raw || !raw.trim()) {
			$parsed.val('');
			return;
		}
		if (!checkChordSheetJS()) {
			$parsed.val('ChordSheetJS did not load. Refresh the page and try again.');
			$parsed.addClass('jww-parse-error');
			return;
		}
		var result = parseChordSheet(raw);
		if (result.chordPro) {
			$parsed.val(result.chordPro);
		} else if (result.song) {
			$parsed.val('Parsed but could not convert to ChordPro format.');
			$parsed.addClass('jww-parse-error');
		} else {
			$parsed.val('Could not parse. Try Ultimate Guitar format (chords above lyrics) or ChordPro.');
			$parsed.addClass('jww-parse-error');
		}
	}

	function scheduleAutoParse() {
		if (parseDebounceTimer) clearTimeout(parseDebounceTimer);
		parseDebounceTimer = setTimeout(runAutoParse, parseDebounceMs);
	}

	$('#jww-import-chords-open-modal').on('click', function () {
		$modal.attr('aria-hidden', 'false').addClass('jww-modal-open');
		$paste.val('');
		$parsed.val('');
		$paste.focus();
	});

	function closeModal() {
		$modal.attr('aria-hidden', 'true').removeClass('jww-modal-open');
		if (parseDebounceTimer) {
			clearTimeout(parseDebounceTimer);
			parseDebounceTimer = null;
		}
	}

	$modal.find('.jww-import-chords-modal-backdrop, .jww-import-chords-modal-close, .jww-import-chords-modal-close-btn').on('click', closeModal);

	$paste.on('input paste', function () {
		scheduleAutoParse();
	});

	$('#jww-import-chords-use').on('click', function () {
		var toCopy = $parsed.val();
		if (!toCopy || toCopy.indexOf('Could not parse') === 0 || toCopy.indexOf('ChordSheetJS did not') === 0) {
			alert('Please paste content in the left box and wait for it to parse, or fix any parse error, then click "Use this".');
			return;
		}
		var ta = getChordSheetTextarea();
		if (!ta) {
			alert('Chord sheet field not found.');
			return;
		}
		ta.value = toCopy;
		ta.dispatchEvent(new Event('input', { bubbles: true }));
		closeModal();
		// Optional: scroll to chord sheet field
		ta.scrollIntoView({ behavior: 'smooth', block: 'center' });
	});

	// ——— Import tabs modal (same pattern as chord sheet) ———
	function looksLikeVexTab(text) {
		if (!text || typeof text !== 'string') return false;
		var t = text.trim();
		return /tabstave|^notes\s/m.test(t);
	}

	function asciiTabToVexTab(ascii) {
		if (!ascii || typeof ascii !== 'string') return '';
		var lines = ascii.trim().split(/\r?\n/);
		var stringIndex = { 'e': 1, 'B': 2, 'G': 3, 'D': 4, 'A': 5, 'E': 6 };
		// Parse each tab line: "e|----|--0--|" -> string 1, segments ["----", "--0--"]
		var segmentsByString = {};
		for (var i = 0; i < lines.length; i++) {
			var line = lines[i].trim();
			var m = line.match(/^([eEBGDA])\|(.+)$/);
			if (!m) continue;
			var strName = m[1];
			var strNum = strName === 'e' ? 1 : stringIndex[strName];
			if (!strNum) continue;
			var parts = m[2].split('|');
			var segs = [];
			for (var k = 0; k < parts.length; k++) {
				var p = parts[k];
				if (p.length > 0) segs.push(p);
			}
			if (segs.length > 0) segmentsByString[strNum] = segs;
		}
		var strNums = Object.keys(segmentsByString).map(Number).sort(function (a, b) { return a - b; });
		if (strNums.length === 0) return '';

		var numSegments = 0;
		for (var s = 0; s < strNums.length; s++) {
			var n = (segmentsByString[strNums[s]] || []).length;
			if (n > numSegments) numSegments = n;
		}
		if (numSegments === 0) return '';

		var out = [];
		out.push('tabstave notation=false');
		var noteParts = [];

		for (var seg = 0; seg < numSegments; seg++) {
			if (seg > 0) noteParts.push('|');

			var maxLen = 0;
			for (var si = 0; si < strNums.length; si++) {
				var segs = segmentsByString[strNums[si]] || [];
				var segStr = segs[seg] || '';
				if (segStr.length > maxLen) maxLen = segStr.length;
			}
			for (var t = 0; t < maxLen; t++) {
				var notes = [];
				for (var si = 0; si < strNums.length; si++) {
					var strNum = strNums[si];
					var segStr = (segmentsByString[strNum] || [])[seg] || '';
					var ch = segStr.charAt(t);
					if (ch === '' || ch === '-' || ch === ' ') continue;
					if (ch === 'x' || ch === 'X') {
						notes.push({ string: strNum, fret: 'x' });
						continue;
					}
					var fret = parseInt(ch, 10);
					if (!isNaN(fret)) notes.push({ string: strNum, fret: fret });
				}
				if (notes.length === 0) continue;
				if (notes.length === 1) {
					var n = notes[0];
					noteParts.push((n.fret === 'x' ? 'x' : n.fret) + '/' + n.string);
				} else {
					noteParts.push('(' + notes.map(function (n) { return (n.fret === 'x' ? 'x' : n.fret) + '/' + n.string; }).join('.') + ')');
				}
			}
		}

		if (noteParts.length) out.push('notes ' + noteParts.join(' '));
		else out.push('notes 0/1');
		return out.join('\n');
	}

	function renderVexTabPreview(containerEl, vextabSource) {
		if (!containerEl) return;
		containerEl.innerHTML = '';
		var source = (vextabSource || '').trim();
		if (!source) return;
		try {
			if (typeof window.vextab !== 'undefined' && typeof window.vextab.Div === 'function') {
				var div = document.createElement('div');
				div.className = 'vextab-auto jww-vextab-render';
				div.setAttribute('width', '600');
				div.setAttribute('scale', '0.8');
				div.textContent = source;
				containerEl.appendChild(div);
				new window.vextab.Div(div);
				removeVexFlowAttribution(div);
			} else {
				containerEl.innerHTML = '<pre class="jww-tabs-fallback jww-monospace">' + escapeHtml(source) + '</pre>';
			}
		} catch (e) {
			containerEl.innerHTML = '<p class="notice notice-error">' + escapeHtml(e.message || 'Could not render preview.') + '</p>';
		}
	}

	function removeVexFlowAttribution(containerEl) {
		if (!containerEl || !containerEl.querySelectorAll) return;
		var texts = containerEl.querySelectorAll('text');
		for (var i = 0; i < texts.length; i++) {
			var t = texts[i];
			if (t.textContent && t.textContent.indexOf('vexflow') !== -1) {
				t.style.display = 'none';
			}
		}
	}

	var tabsDebounceTimer = null;
	var $tabsModal = $('#jww-import-tabs-modal');
	var $tabsPaste = $('#jww-import-tabs-paste');
	var $tabsVexTab = $('#jww-import-tabs-vextab');
	var tabsPreviewEl = document.getElementById('jww-import-tabs-preview');

	function runTabsConvert() {
		var raw = $tabsPaste.val();
		if (!raw || !raw.trim()) {
			$tabsVexTab.val('');
			renderVexTabPreview(tabsPreviewEl, '');
			return;
		}
		var vextab = looksLikeVexTab(raw) ? raw.trim() : asciiTabToVexTab(raw);
		if (!vextab) {
			$tabsVexTab.val('');
			renderVexTabPreview(tabsPreviewEl, '');
			return;
		}
		$tabsVexTab.val(vextab);
		renderVexTabPreview(tabsPreviewEl, vextab);
	}

	function scheduleTabsConvert() {
		if (tabsDebounceTimer) clearTimeout(tabsDebounceTimer);
		tabsDebounceTimer = setTimeout(runTabsConvert, 400);
	}

	$('#jww-import-tabs-open-modal').on('click', function () {
		$tabsModal.attr('aria-hidden', 'false').addClass('jww-modal-open');
		$tabsPaste.val('');
		$tabsVexTab.val('');
		if (tabsPreviewEl) tabsPreviewEl.innerHTML = '';
		$tabsPaste.focus();
	});

	function closeTabsModal() {
		$tabsModal.attr('aria-hidden', 'true').removeClass('jww-modal-open');
		if (tabsDebounceTimer) {
			clearTimeout(tabsDebounceTimer);
			tabsDebounceTimer = null;
		}
	}

	$tabsModal.find('.jww-import-chords-modal-backdrop, .jww-import-tabs-modal-close, .jww-import-tabs-modal-close-btn').on('click', closeTabsModal);

	$tabsPaste.on('input paste', function () {
		scheduleTabsConvert();
	});

	// When user edits the VexTab textarea, update preview only (no re-conversion from left).
	$tabsVexTab.on('input paste', function () {
		if (tabsDebounceTimer) clearTimeout(tabsDebounceTimer);
		tabsDebounceTimer = setTimeout(function () {
			var vextab = $tabsVexTab.val();
			if (tabsPreviewEl) renderVexTabPreview(tabsPreviewEl, (vextab || '').trim());
			tabsDebounceTimer = null;
		}, 350);
	});

	$('#jww-import-tabs-use').on('click', function () {
		var toCopy = $tabsVexTab.val();
		if (!toCopy || !toCopy.trim()) {
			alert('Paste ASCII tab or VexTab in the left box and wait for the conversion, then click "Use this".');
			return;
		}
		var ta = getTabsTextarea();
		if (!ta) {
			alert('Tabs field not found.');
			return;
		}
		ta.value = toCopy;
		ta.dispatchEvent(new Event('input', { bubbles: true }));
		closeTabsModal();
		ta.scrollIntoView({ behavior: 'smooth', block: 'center' });
	});
})(jQuery);
