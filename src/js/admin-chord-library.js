/**
 * Admin Chord Library – grid of required chords with VexChords diagrams.
 * Runs only on the Chord Library admin page (Songs → Chord Library).
 */
import { ChordBox } from 'vexchords';

(function () {
	'use strict';

	var config = typeof jwwChordLibraryAdmin !== 'undefined' ? jwwChordLibraryAdmin : {};
	var chordLibrary = config.chordLibrary || {};
	var missingChords = config.missingChords || [];

	var ENHARMONIC = {
		'C#': 'Db', 'Db': 'C#', 'D#': 'Eb', 'Eb': 'D#',
		'F#': 'Gb', 'Gb': 'F#', 'G#': 'Ab', 'Ab': 'G#',
		'A#': 'Bb', 'Bb': 'A#'
	};

	function chordSuffix(name) {
		var m = (name || '').match(/^.[#b]?(.*)$/);
		return m ? m[1] : '';
	}

	function chordRoot(name) {
		var m = (name || '').match(/^([A-Ga-g][#b]?)/);
		return m ? m[1] : '';
	}

	function lookupShape(name) {
		if (!name) return null;
		if (chordLibrary[name]) return chordLibrary[name];
		var root = chordRoot(name);
		var suffix = chordSuffix(name);
		var alt = ENHARMONIC[root];
		if (alt) {
			var key = alt + suffix;
			if (chordLibrary[key]) return chordLibrary[key];
		}
		return null;
	}

	function renderGrid() {
		var cards = document.querySelectorAll('.jww-chord-library-card-diagram[data-chord]');
		cards.forEach(function (el) {
			var name = el.getAttribute('data-chord');
			var shape = lookupShape(name);
			if (!shape || !shape.chord) return;
			try {
				var chordNoLabels = (shape.chord || []).map(function (item) {
					return item.slice(0, 2);
				});
				var struct = {
					chord: chordNoLabels,
					position: shape.position != null ? shape.position : 0,
					barres: shape.barres || []
				};
				new ChordBox(el, {
					width: 100,
					height: 120,
					defaultColor: '#444',
					fontSize: 10
				}).draw(struct);
			} catch (err) {}
		});
	}

	function initFilter() {
		var filter = document.getElementById('jww-chord-library-filter-missing');
		var grid = document.getElementById('jww-chord-library-grid');
		if (!filter || !grid) return;
		filter.addEventListener('change', function () {
			var showMissingOnly = filter.checked;
			var items = grid.querySelectorAll('.jww-chord-library-card');
			items.forEach(function (card) {
				var isMissing = card.getAttribute('data-missing') === '1';
				if (showMissingOnly) {
					card.style.display = isMissing ? '' : 'none';
				} else {
					card.style.display = '';
				}
			});
		});
	}

	function getCustomOverrideJson() {
		var textarea = document.getElementById('jww-custom-chord-library-input');
		if (!textarea) return {};
		var raw = (textarea.value || '').trim();
		if (!raw) return {};
		try {
			var data = JSON.parse(raw);
			return typeof data === 'object' && data !== null ? data : {};
		} catch (e) {
			return {};
		}
	}

	function setCustomOverrideJson(obj) {
		var textarea = document.getElementById('jww-custom-chord-library-input');
		if (!textarea) return;
		textarea.value = JSON.stringify(obj, null, 2);
	}

	function initClickToOverride() {
		var grid = document.getElementById('jww-chord-library-grid');
		var textarea = document.getElementById('jww-custom-chord-library-input');
		if (!grid || !textarea) return;
		grid.querySelectorAll('.jww-chord-library-card').forEach(function (card) {
			var chordName = card.getAttribute('data-chord');
			if (chordName && lookupShape(chordName)) {
				card.style.cursor = 'pointer';
				card.setAttribute('title', 'Add to custom overrides');
			}
		});
		grid.addEventListener('click', function (e) {
			var card = e.target.closest('.jww-chord-library-card');
			if (!card) return;
			var chordName = card.getAttribute('data-chord');
			if (!chordName) return;
			var shape = lookupShape(chordName);
			if (!shape || !shape.chord) return;
			var override = getCustomOverrideJson();
			override[chordName] = shape;
			setCustomOverrideJson(override);
		});
	}

	function init() {
		renderGrid();
		initFilter();
		initClickToOverride();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
