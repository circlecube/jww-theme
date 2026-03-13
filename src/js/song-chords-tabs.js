/**
 * Song Chords and Tabs – front-end logic
 * Uses ChordSheetJS (global), VexTab (global) from CDN; VexChords bundled.
 * Transpose/capo, show-hide toggle (localStorage), chord diagrams, tab render.
 * Click-to-play chord sound via Tone.js + Tonal.
 */
import { ChordBox } from 'vexchords';
import * as Tone from 'tone';
import { Chord, Note } from 'tonal';

(function () {
	'use strict';

	var config = typeof jwwSongChordsTabs !== 'undefined' ? jwwSongChordsTabs : {};
	var chordLibrary = config.chordLibrary || {};
	var chordSheetRaw = config.chordSheet || '';
	var tabsRaw = config.tabs || '';
	var capoDefault = (function () {
		var v = config.capoDefault;
		if (v === null || v === undefined) return 0;
		var n = parseInt(v, 10);
		if (isNaN(n) || n < 0 || n > 12) return 0;
		return n;
	})();
	var guitarIconUrl = config.guitarIconUrl || '';
	var storageKey = config.storageKey || 'jww_show_chords';

	// Chromatic scale (root names) for transposition
	var CHROMATIC = ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B'];
	var CHROMATIC_FLATS = ['C', 'Db', 'D', 'Eb', 'E', 'F', 'Gb', 'G', 'Ab', 'A', 'Bb', 'B'];

	function indexOfRoot(name) {
		var m = (name || '').match(/^([A-Ga-g][#b]?)/);
		if (!m) return -1;
		var root = m[1].toUpperCase();
		if (root.length === 2 && root[1] === 'B') root = root[0] + 'b';
		for (var i = 0; i < CHROMATIC.length; i++) {
			if (CHROMATIC[i] === root || CHROMATIC_FLATS[i] === root) return i;
		}
		return -1;
	}

	function transposeChordString(chordStr, semitones) {
		if (!chordStr || semitones === 0) return chordStr;
		var m = chordStr.match(/^([A-Ga-g][#b]?)(.*)$/);
		if (!m) return chordStr;
		var root = m[1];
		var suffix = m[2] || '';
		var idx = indexOfRoot(root);
		if (idx < 0) return chordStr;
		var newIdx = (idx + semitones % 12 + 12) % 12;
		var newRoot = CHROMATIC[newIdx];
		return newRoot + suffix;
	}

	function getTotalOffset(transposeSemitones, capoFret) {
		var capo = parseInt(capoFret, 10);
		if (isNaN(capo) || capo < 0) capo = 0;
		return (parseInt(transposeSemitones, 10) || 0) + capo;
	}

	function parseChordSheet(text) {
		if (!text || typeof text !== 'string') return null;
		var ChordSheetJS = window.ChordSheetJS;
		if (!ChordSheetJS) return null;
		// ChordPro uses [Chord]; UG uses section headers like [Verse 1]. Prefer ChordPro when we see bracket chords.
		var looksLikeChordPro = /\[([A-Ga-g][#b]?[^\]\s]*)\]/.test(text);
		var parser = looksLikeChordPro || text.indexOf('{') >= 0
			? new ChordSheetJS.ChordProParser()
			: new ChordSheetJS.UltimateGuitarParser();
		try {
			return parser.parse(text.trim());
		} catch (e) {
			try {
				return new ChordSheetJS.UltimateGuitarParser().parse(text.trim());
			} catch (e2) {
				return null;
			}
		}
	}

	function transposeSong(song, semitones) {
		if (!song || semitones === 0) return song;
		// Transpose by modifying the raw chord sheet text and re-parsing (avoids relying on Song internals)
		return null;
	}

	function transposeChordSheetText(text, semitones) {
		if (!text || semitones === 0) return text;
		// Replace [Chord] patterns (ChordPro) and similar
		return text.replace(/\[([A-Ga-g][#b]?[^\]\s]*)\]/g, function (_, chord) {
			return '[' + transposeChordString(chord, semitones) + ']';
		});
	}

	function formatChordSheet(song) {
		return formatChordSheetAsDivs(song);
	}

	/**
	 * Format parsed song as div-based HTML (flex layout). Avoids table markup and stray characters.
	 */
	function formatChordSheetAsDivs(song) {
		if (!song) return '';
		var body = song.bodyParagraphs || song.paragraphs;
		if (!body || !body.length) return '';
		var out = [];
		for (var p = 0; p < body.length; p++) {
			var para = body[p];
			var lines = para.lines || [];
			var type = (para.type && para.type !== 'none' && para.type !== 'indeterminate') ? String(para.type) : 'paragraph';
			var paraOut = [];
			if (para.isLiteral && para.isLiteral()) {
				var label = para.label;
				var contents = (typeof para.contents === 'function' ? para.contents() : para.contents) || '';
				if (label) paraOut.push('<div class="jww-cs-label">' + escapeHtml(label) + '</div>');
				if (contents) paraOut.push('<div class="jww-cs-literal">' + escapeHtml(contents) + '</div>');
			} else {
				for (var l = 0; l < lines.length; l++) {
					var line = lines[l];
					var items = line.items || [];
					var lineLabel = null;
					var literalLine = null;
					var pairs = [];
					for (var i = 0; i < items.length; i++) {
						var item = items[i];
						if (item.chords !== undefined && item.lyrics !== undefined) {
							var chordStr = stripBrTags(item.chords || '');
							var lyricsStr = stripBrTags(item.lyrics || '');
							var chordCls = 'jww-cs-chord' + (!chordStr.trim() ? ' jww-cs-chord-empty' : '');
							pairs.push({
								chord: chordStr,
								lyrics: lyricsStr,
								chordCls: chordCls
							});
						} else if (item.label) {
							lineLabel = item.label;
						} else if (item.string !== undefined) {
							literalLine = (literalLine || '') + (item.string || '');
						}
					}
					// Add hyphen when a chord is inserted in the middle of a word (not at the start).
					// The continuation may be in the next pair (ev | C | ery) or in the same pair as the chord (ev | C+ery).
					for (var i = 0; i < pairs.length; i++) {
						var pair = pairs[i];
						if (!pair.chord || !pair.chord.trim() || i === 0) continue;
						var prevLyrics = (pairs[i - 1].lyrics != null) ? String(pairs[i - 1].lyrics) : '';
						var prevHasWord = prevLyrics.trim().length > 0 && !/\s$/.test(prevLyrics);
						if (!prevHasWord) continue;

						var nextLyrics = (i < pairs.length - 1 && pairs[i + 1].lyrics != null) ? String(pairs[i + 1].lyrics) : '';
						var currLyrics = (pair.lyrics != null) ? String(pair.lyrics) : '';
						var nextPartInNext = nextLyrics.trim().length > 0 && !/^\s/.test(nextLyrics);
						var nextPartInCurr = currLyrics.trim().length > 0 && !/^\s/.test(currLyrics);

						if (nextPartInNext) {
							pairs[i - 1].lyrics = prevLyrics.replace(/\s+$/, '') + '-';
							pairs[i + 1].lyrics = nextLyrics.replace(/^\s+/, '');
							pairs[i - 1].midWord = true;
							pair.midWord = true;
							pairs[i + 1].midWord = true;
							pairs[i - 1].lyricsHyphenated = true;
							pairs[i + 1].lyricsHyphenated = true;
						} else if (nextPartInCurr) {
							pairs[i - 1].lyrics = prevLyrics.replace(/\s+$/, '') + '-';
							pairs[i - 1].midWord = true;
							pair.midWord = true;
							pairs[i - 1].lyricsHyphenated = true;
							pair.lyricsHyphenated = true;
						}
					}
					if (lineLabel) {
						paraOut.push('<div class="jww-cs-line jww-cs-line-label"><span class="jww-cs-label">' + escapeHtml(lineLabel) + '</span></div>');
					} else if (literalLine !== null) {
						var stripped = stripBrTags(literalLine);
						if (stripped.trim().length > 0) {
							paraOut.push('<div class="jww-cs-line jww-cs-line-literal"><span class="jww-cs-lyrics">' + escapeHtml(stripped) + '</span></div>');
						}
					} else if (pairs.length) {
						var lineEmpty = true;
						for (var j = 0; j < items.length; j++) {
							var it = items[j];
							if (it && (it.chords !== undefined || it.lyrics !== undefined)) {
								if (stripBrTags(it.chords || '').trim().length > 0 || stripBrTags(it.lyrics || '').trim().length > 0) {
									lineEmpty = false;
									break;
								}
							}
						}
						var lineCls = 'jww-cs-line jww-cs-line-pairs' + (lineEmpty ? ' jww-cs-line-empty' : '');
						var pairHtml = pairs.map(function (pair) {
							var midCls = pair.midWord ? ' jww-cs-pair-mid-word' : '';
							var hyphenCls = pair.lyricsHyphenated ? ' jww-cs-lyrics--hyphenated' : '';
							return '<div class="jww-cs-pair' + midCls + '">' +
								'<span class="' + pair.chordCls + '">' + escapeHtml(pair.chord) + '</span>' +
								'<span class="jww-cs-lyrics' + hyphenCls + '">' + escapeHtml(pair.lyrics) + '</span>' +
								'</div>';
						}).join('');
						paraOut.push('<div class="' + lineCls + '">' + pairHtml + '</div>');
					}
				}
			}
			if (paraOut.length > 0) {
				out.push('<div class="jww-cs-paragraph jww-cs-' + escapeHtml(type) + '">');
				out.push(paraOut.join(''));
				out.push('</div>');
			}
		}
		return '<div class="jww-cs-sheet">' + out.join('') + '</div>';
	}

	function extractChordNamesFromSong(song) {
		var names = {};
		if (!song) return names;
		// Walk song body if available; else extract from formatted HTML or raw text
		try {
			var body = song.bodyParagraphs || song.paragraphs || (song.body && song.body.paragraphs);
			if (body && body.length) {
				for (var p = 0; p < body.length; p++) {
					var para = body[p];
					var lines = para.lines || (para.type === 'line' ? [para] : []);
					for (var l = 0; l < lines.length; l++) {
						var items = (lines[l].items || []);
						for (var i = 0; i < items.length; i++) {
							var c = items[i].chords;
							if (c && c.trim()) names[c.trim()] = true;
						}
					}
				}
				return names;
			}
		} catch (e) { }
		// Fallback: regex chord names from raw text
		var raw = chordSheetRaw;
		var matches = raw.match(/\[([A-Ga-g][#b]?[^\]\s]*)\]/g);
		if (matches) {
			matches.forEach(function (m) {
				var name = m.replace(/^\[|\]$/g, '').trim();
				if (name) names[name] = true;
			});
		}
		return names;
	}

	function getUniqueChordNames(song) {
		var keys = [];
		var seen = extractChordNamesFromSong(song);
		for (var k in seen) if (seen.hasOwnProperty(k)) keys.push(k);
		return keys;
	}

	function normalizeChordKey(name) {
		return (name || '').replace(/\s+/g, '').trim();
	}

	/** Chord sound: Tonal chord name -> note names with octave for Tone.js.
	 *  If Tonal doesn't know the chord (e.g. A(b5)), fall back to notes from the chord diagram shape (fret/string).
	 */
	function getChordNoteNamesWithOctave(chordName) {
		if (!chordName) return [];
		// Try Tonal first
		if (typeof Chord !== 'undefined') {
			var c = Chord.get(chordName);
			if (c && !c.empty && c.notes && c.notes.length) {
				return c.notes.map(function (n) { return n + '4'; });
			}
		}
		// Fallback: derive notes from chord diagram shape (same as tab: string + fret -> note)
		var shape = lookupChordDiagram(chordName);
		if (!shape || !shape.chord || !shape.chord.length) return [];
		var notes = [];
		for (var i = 0; i < shape.chord.length; i++) {
			var item = shape.chord[i];
			var stringNum = parseInt(item[0], 10);
			var fret = item[1];
			if (isNaN(stringNum) || stringNum < 1 || stringNum > 6) continue;
			if (fret === 'x' || fret === 'X') continue;
			var fretNum = parseInt(fret, 10);
			if (isNaN(fretNum) || fretNum < 0) continue;
			var n = fretStringToNoteName(stringNum, fretNum);
			if (n) notes.push(n);
		}
		return notes;
	}

	var chordSynth = null;
	var toneAudioStarted = false;
	/** Set true if not in secure context (https/localhost) or Tone.start() failed; disables audio. */
	var toneAudioUnavailable = typeof window !== 'undefined' && !window.isSecureContext;

	/** PluckSynth options; sliders in the troubleshooting panel live-update this. Next getChordSynth() uses these. */
	var pluckSynthOptions = {
		resonance: 0.96,
		dampening: 1800,
		release: 1,
		attackNoise: 4
	};

	/** Set to true to show the chord sound troubleshooting sliders above the chord diagrams. */
	var SHOW_CHORD_SOUND_CONTROLS = false;

	/**
	 * PluckSynth (Karplus–Strong) pool. Tone's PolySynth only supports Monophonic voices,
	 * so we use a pool of PluckSynth instances. Options come from pluckSynthOptions (tweakable via UI).
	 */
	function getChordSynth() {
		if (chordSynth) return chordSynth;
		if (toneAudioUnavailable || typeof Tone === 'undefined') return null;
		try {
			var opts = pluckSynthOptions;
			var poolSize = 6;
			var available = [];
			var volume = new Tone.Volume(-3).toDestination();
			for (var i = 0; i < poolSize; i++) {
				var pluck = new Tone.PluckSynth({
					resonance: opts.resonance,
					dampening: opts.dampening,
					release: opts.release,
					attackNoise: opts.attackNoise
				}).connect(volume);
				available.push(pluck);
			}
			chordSynth = {
				triggerAttackRelease: function (notes, duration, time) {
					var t = time != null ? volume.toSeconds(time) : Tone.now();
					var d = volume.toSeconds(duration);
					var list = Array.isArray(notes) ? notes : [notes];
					list.forEach(function (note) {
						var voice = available.shift();
						if (!voice) return;
						voice.triggerAttack(note, t);
						voice.triggerRelease(t + d);
						var returnIn = Math.max(0, t + d - Tone.now()) + 0.05;
						volume.context.setTimeout(function () {
							available.push(voice);
						}, returnIn);
					});
				}
			};
		} catch (e) { }
		return chordSynth;
	}

	function invalidateChordSynth() {
		chordSynth = null;
	}

	function startToneAudio() {
		if (toneAudioUnavailable || toneAudioStarted || typeof Tone === 'undefined') return;
		try {
			var p = Tone.start();
			if (p && typeof p.then === 'function') {
				p.then(function () { toneAudioStarted = true; }).catch(function () { toneAudioUnavailable = true; });
			} else {
				toneAudioStarted = true;
			}
		} catch (e) {
			toneAudioUnavailable = true;
		}
	}

	function playChordSound(chordName) {
		startToneAudio();
		var notes = getChordNoteNamesWithOctave(chordName);
		if (!notes.length) return;
		// Transpose down one octave so chord sounds more like a guitar.
		if (typeof Note !== 'undefined' && Note.midi && Note.fromMidi) {
			notes = notes.map(function (n) {
				var midi = Note.midi(n);
				if (midi == null) return n;
				return Note.fromMidi(midi - 12);
			});
		}
		var synth = getChordSynth();
		if (!synth) return;
		try {
			synth.triggerAttackRelease(notes, '0.5');
		} catch (e) { }
	}

	// Standard guitar tuning (string 1 = high E). Used for tab fret → note name.
	var STANDARD_TUNING = ['E4', 'B3', 'G3', 'D3', 'A2', 'E2'];

	function fretStringToNoteName(stringNum, fret) {
		if (stringNum < 1 || stringNum > 6 || fret < 0 || fret > 24) return null;
		var openNote = STANDARD_TUNING[stringNum - 1];
		if (typeof Note === 'undefined' || !Note.midi || !Note.fromMidi) return null;
		try {
			var midi = Note.midi(openNote);
			if (midi == null) return null;
			return Note.fromMidi(midi + fret);
		} catch (e) {
			return null;
		}
	}

	/**
	 * Parse VexTab source into segments (split by |). Each segment is an array of { fret, string }.
	 * Handles: fret/string, (fret/string.fret/string), and fret-fret-fret/string (takes first fret).
	 */
	function parseVexTabNotesIntoSegments(vextabStr) {
		if (!vextabStr || typeof vextabStr !== 'string') return [];
		var lines = vextabStr.trim().split(/\r?\n/);
		var notesLine = null;
		for (var i = 0; i < lines.length; i++) {
			var line = lines[i].trim();
			if (line.indexOf('notes ') === 0) {
				notesLine = line.replace(/^notes\s+/, '').trim();
				break;
			}
		}
		if (!notesLine) return [];
		var segmentStrs = notesLine.split(/\s*\|\s*/);
		var segments = [];
		for (var s = 0; s < segmentStrs.length; s++) {
			var tokens = segmentStrs[s].trim().split(/\s+/).filter(Boolean);
			var segment = [];
			for (var t = 0; t < tokens.length; t++) {
				var token = tokens[t];
				// Chord: (5/2.5/3.7/4)
				var chordMatch = token.match(/^\(([^)]+)\)$/);
				if (chordMatch) {
					var parts = chordMatch[1].split(/\./);
					for (var p = 0; p < parts.length; p++) {
						var pair = parts[p].trim().split(/\//);
						if (pair.length >= 2) {
							var fret = parseInt(pair[0], 10);
							var str = parseInt(pair[1], 10);
							if (!isNaN(fret) && !isNaN(str) && str >= 1 && str <= 6) segment.push({ fret: fret, string: str });
						}
					}
					continue;
				}
				// Run: 4-5-6/3 or single: 5/2
				var runMatch = token.match(/^(\d+)(-\d+)*\/(\d+)$/);
				if (runMatch) {
					var fretFirst = parseInt(runMatch[1], 10);
					var strNum = parseInt(runMatch[3], 10);
					if (strNum >= 1 && strNum <= 6) segment.push({ fret: fretFirst, string: strNum });
				}
			}
			if (segment.length > 0) segments.push(segment);
		}
		return segments;
	}

	var tabSegmentsForClick = [];

	function playTabSegmentNotes(segmentIndex) {
		if (segmentIndex < 0 || segmentIndex >= tabSegmentsForClick.length) return;
		var segment = tabSegmentsForClick[segmentIndex];
		if (!segment.length) return;
		startToneAudio();
		var notes = [];
		for (var i = 0; i < segment.length; i++) {
			var n = fretStringToNoteName(segment[i].string, segment[i].fret);
			if (n) notes.push(n);
		}
		if (!notes.length) return;
		var synth = getChordSynth();
		if (!synth) return;
		try {
			var now = Tone.now();
			var noteDuration = 0.25;
			var step = 0.12;
			for (var j = 0; j < notes.length; j++) {
				synth.triggerAttackRelease(notes[j], noteDuration, now + j * step);
			}
		} catch (e) { }
	}

	function lookupChordDiagram(name) {
		var key = normalizeChordKey(name);
		return chordLibrary[key] || chordLibrary[name] || null;
	}

	function renderChordDiagrams(container, chordNames) {
		if (!container || !chordNames.length) return;
		container.innerHTML = '';
		chordNames.forEach(function (name) {
			var shape = lookupChordDiagram(name);
			if (!shape) return;
			var wrap = document.createElement('div');
			wrap.className = 'jww-chord-diagram-wrap';
			wrap.setAttribute('title', 'Play chord sound');
			wrap.setAttribute('role', 'button');
			wrap.setAttribute('tabindex', '0');
			wrap.setAttribute('data-chord-name', name);
			var canvas = document.createElement('div');
			var label = document.createElement('span');
			label.className = 'jww-chord-diagram-name';
			label.textContent = name;
			wrap.appendChild(canvas);
			wrap.appendChild(label);
			container.appendChild(wrap);
			try {
				var chordNoLabels = (shape.chord || []).map(function (item) {
					return item.slice(0, 2);
				});
				var struct = {
					chord: chordNoLabels,
					position: shape.position,
					barres: shape.barres || []
				};
				new ChordBox(canvas, {
					width: 100,
					height: 120,
					defaultColor: '#444',
					fontSize: 10
				}).draw(struct);
			} catch (err) { }
		});
	}

	function applyFretOffsetToVexTabString(vextabStr, offset) {
		if (!offset || offset === 0) return vextabStr;
		// Only add offset to numbers that look like frets: followed by / or - or . (fret/string, fret-fret, chord)
		return vextabStr.replace(/(\d+)([\/\-\.])/g, function (_, num, after) {
			var n = parseInt(num, 10);
			if (isNaN(n)) return num + after;
			return (n + offset) + after;
		});
	}

	/** Detect if text looks like plain ASCII tab (e.g. e|-0-2-3-|) rather than VexTab. */
	function looksLikeAsciiTab(text) {
		if (!text || typeof text !== 'string') return false;
		var trimmed = text.trim();
		if (/tabstave|^notes\s/m.test(trimmed)) return false;
		return /^[eEBGDA]\|[\d\-|xX\s]+\|?\s*$/m.test(trimmed);
	}

	/** Convert plain ASCII tab (one line per string, segments between |) to VexTab. */
	function asciiTabToVexTab(ascii) {
		if (!ascii || typeof ascii !== 'string') return '';
		var lines = ascii.trim().split(/\r?\n/);
		var stringIndex = { 'e': 1, 'B': 2, 'G': 3, 'D': 4, 'A': 5, 'E': 6 };
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
			// VexTab bar line: insert | between segments (after the first)
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

	function renderTabs(container, vextabSource, fretOffset) {
		if (!container || typeof vextabSource !== 'string') return;
		var source = fretOffset ? applyFretOffsetToVexTabString(vextabSource, fretOffset) : vextabSource;
		source = source.trim();
		if (looksLikeAsciiTab(source)) {
			source = asciiTabToVexTab(source);
		}
		tabSegmentsForClick = parseVexTabNotesIntoSegments(source);
		container.innerHTML = '';
		container.setAttribute('title', tabSegmentsForClick.length ? 'Click a position to hear notes' : '');
		if (tabSegmentsForClick.length) container.classList.add('jww-tabs-clickable'); else container.classList.remove('jww-tabs-clickable');
		try {
			// VexTab div.prod.js auto-renders divs with class "vextab-auto" on load; we create our div
			// after load so we must instantiate Div ourselves. The constructor reads the element's text,
			// clears it, and renders notation into the div (no static .render method).
			var div = document.createElement('div');
			div.className = 'vextab-auto jww-vextab-render';
			div.setAttribute('width', '600');
			div.setAttribute('scale', '0.9');
			div.textContent = source;
			container.appendChild(div);
			if (typeof window.vextab !== 'undefined' && typeof window.vextab.Div === 'function') {
				new window.vextab.Div(div);
				removeVexFlowAttribution(div);
			} else {
				container.innerHTML = '<pre class="jww-tabs-fallback">' + escapeHtml(source) + '</pre>';
			}
		} catch (e) {
			container.innerHTML = '<pre class="jww-tabs-fallback">' + escapeHtml(source) + '</pre>';
		}
	}

	/** Remove VexFlow attribution text (SVG <text> with "vexflow") from container; no API to disable it. */
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

	function escapeHtml(s) {
		if (s == null) return '';
		var div = document.createElement('div');
		div.textContent = s;
		return div.innerHTML;
	}

	/** Strip <br> tags from text (ACF can save newlines as br; we display with CSS line layout). */
	function stripBrTags(s) {
		if (s == null || typeof s !== 'string') return '';
		return s.replace(/<br\s*\/?>/gi, ' ');
	}

	function getStoredShowChords() {
		try {
			var v = localStorage.getItem(storageKey);
			if (v === '0' || v === 'false') return false;
			if (v === '1' || v === 'true') return true;
		} catch (e) { }
		return false; // Hidden by default; user choice persisted in localStorage
	}

	function setStoredShowChords(show) {
		try {
			localStorage.setItem(storageKey, show ? '1' : '0');
		} catch (e) { }
	}

	function setChordsVisible(visible) {
		var guitarSection = document.getElementById('jww-guitar-section');
		if (guitarSection) guitarSection.style.display = visible ? '' : 'none';
		var toggle = document.getElementById('jww-show-chords-toggle');
		if (toggle) {
			toggle.classList.toggle('jww-chords-hidden', !visible);
			toggle.setAttribute('aria-pressed', visible ? 'true' : 'false');
			var label = toggle.querySelector('.jww-show-chords-label');
			if (label) label.textContent = visible ? 'Hide guitar chords' : 'Show guitar chords';
		}
	}

	/** Render troubleshooting sliders for PluckSynth (resonance, dampening, release, attackNoise). Live-updates pluckSynthOptions; next chord play uses new values. */
	function renderChordSoundControls(sectionEl, containerEl) {
		if (!sectionEl || !containerEl) return;
		var existing = sectionEl.querySelector('.jww-chord-sound-controls');
		if (existing) return;
		var wrap = document.createElement('div');
		wrap.className = 'jww-chord-sound-controls';
		wrap.setAttribute('aria-label', 'Chord sound troubleshooting');
		var heading = document.createElement('div');
		heading.className = 'jww-chord-sound-controls-heading';
		heading.textContent = 'Chord sound (troubleshooting)';
		wrap.appendChild(heading);
		var opts = pluckSynthOptions;
		function addSlider(labelText, key, min, max, step, formatFn) {
			var row = document.createElement('div');
			row.className = 'jww-chord-sound-control-row';
			var label = document.createElement('label');
			label.textContent = labelText;
			var input = document.createElement('input');
			input.type = 'range';
			input.min = min;
			input.max = max;
			input.step = step || (key === 'resonance' ? 0.01 : (key === 'release' ? 0.05 : 1));
			input.value = opts[key];
			var valueSpan = document.createElement('span');
			valueSpan.className = 'jww-chord-sound-control-value';
			valueSpan.textContent = formatFn ? formatFn(opts[key]) : opts[key];
			function update() {
				var val = key === 'resonance' ? parseFloat(input.value) : (key === 'release' ? parseFloat(input.value) : parseInt(input.value, 10));
				opts[key] = val;
				valueSpan.textContent = formatFn ? formatFn(val) : val;
				invalidateChordSynth();
			}
			input.addEventListener('input', update);
			input.addEventListener('change', update);
			label.appendChild(input);
			row.appendChild(label);
			row.appendChild(valueSpan);
			wrap.appendChild(row);
		}
		addSlider('Resonance ', 'resonance', 0.1, 1, 0.01, function (v) { return Math.round(v * 100) / 100; });
		addSlider('Dampening ', 'dampening', 100, 7000, 50, function (v) { return v; });
		addSlider('Release ', 'release', 0.1, 2, 0.05, function (v) { return Math.round(v * 100) / 100; });
		addSlider('Attack noise ', 'attackNoise', 0.1, 10, 0.1, function (v) { return Math.round(v * 10) / 10; });
		sectionEl.insertBefore(wrap, containerEl);
	}

	function run() {
		var chordSheetWrapper = document.getElementById('jww-chord-sheet-wrapper');
		var chordSheetContent = document.getElementById('jww-chord-sheet-content');
		var chordDiagramsSection = document.getElementById('jww-chord-diagrams-section');
		var chordDiagramsContainer = document.getElementById('jww-chord-diagrams-container');
		var tabsSection = document.getElementById('jww-tabs-section');
		var tabsContainer = document.getElementById('jww-tabs-container');
		var controlsBar = document.getElementById('jww-chords-controls');
		if (!controlsBar) return;

		var transposeInput = document.getElementById('jww-transpose');
		var capoInput = document.getElementById('jww-capo');
		var transposeDisplay = document.getElementById('jww-transpose-display');
		var capoDisplay = document.getElementById('jww-capo-display');
		var showChords = getStoredShowChords();
		setChordsVisible(showChords);

		// Chord sound troubleshooting panel (above chord diagrams)
		if (SHOW_CHORD_SOUND_CONTROLS && chordDiagramsSection && chordDiagramsContainer) {
			renderChordSoundControls(chordDiagramsSection, chordDiagramsContainer);
		}

		// Transpose: default 0. Capo: default from ACF (already in hidden input from PHP).
		if (transposeInput) transposeInput.value = '0';
		if (transposeDisplay) transposeDisplay.textContent = '0';
		if (capoInput && capoDefault >= 0 && capoDefault <= 12) {
			capoInput.value = String(capoDefault);
			if (capoDisplay) capoDisplay.textContent = capoDefault === 0 ? 'None' : String(capoDefault);
		}

		var TRANSPOSE_MIN = -6;
		var TRANSPOSE_MAX = 6;
		var CAPO_MIN = 0;
		var CAPO_MAX = 12;

		function setTransposeDisplay() {
			var v = parseInt(transposeInput && transposeInput.value, 10);
			if (isNaN(v)) v = 0;
			v = Math.max(TRANSPOSE_MIN, Math.min(TRANSPOSE_MAX, v));
			if (transposeInput) transposeInput.value = String(v);
			if (transposeDisplay) transposeDisplay.textContent = v === 0 ? '0' : (v > 0 ? '+' + v : String(v));
		}

		function setCapoDisplay() {
			var v = parseInt(capoInput && capoInput.value, 10);
			if (isNaN(v)) v = 0;
			v = Math.max(CAPO_MIN, Math.min(CAPO_MAX, v));
			if (capoInput) capoInput.value = String(v);
			if (capoDisplay) capoDisplay.textContent = v === 0 ? 'None' : String(v);
		}

		function getOffset() {
			var t = transposeInput ? parseInt(transposeInput.value, 10) : 0;
			var c = capoInput ? parseInt(capoInput.value, 10) : 0;
			t = isNaN(t) ? 0 : t;
			c = isNaN(c) ? 0 : c;
			// Saved chords are for the song's capo (capoDefault). When selected capo equals saved capo, show as-is.
			// Transpose only by the difference: (selected capo - saved capo) + manual transpose.
			var capoDelta = c - capoDefault;
			return { semitones: t, capo: c, total: t + capoDelta };
		}

		var currentSong = null;

		function updateChordSheet() {
			if (!chordSheetRaw || !chordSheetContent) return;
			var off = getOffset();
			var textToParse = transposeChordSheetText(chordSheetRaw, off.total);
			textToParse = (textToParse || '').replace(/<br\s*\/?>/gi, '\n');
			currentSong = parseChordSheet(textToParse);
			if (!currentSong) {
				chordSheetContent.innerHTML = '<pre class="jww-chord-sheet-fallback">' + escapeHtml(chordSheetRaw) + '</pre>';
				return;
			}
			chordSheetContent.innerHTML = formatChordSheet(currentSong) || escapeHtml(chordSheetRaw);
			if (chordDiagramsContainer && chordLibrary && Object.keys(chordLibrary).length) {
				var names = getUniqueChordNames(currentSong);
				renderChordDiagrams(chordDiagramsContainer, names);
			}
		}

		function updateTabs() {
			if (!tabsRaw || !tabsContainer) return;
			var off = getOffset();
			renderTabs(tabsContainer, tabsRaw, off.total);
		}

		function updateAll() {
			updateChordSheet();
			updateTabs();
		}

		updateAll();
		// Click (or Enter/Space) on a chord diagram plays the chord sound
		if (chordDiagramsContainer) {
			chordDiagramsContainer.addEventListener('click', function (e) {
				var wrap = e.target.closest('.jww-chord-diagram-wrap');
				if (!wrap) return;
				var name = wrap.getAttribute('data-chord-name') || (wrap.querySelector('.jww-chord-diagram-name') && wrap.querySelector('.jww-chord-diagram-name').textContent.trim());
				if (name) playChordSound(name);
			});
			chordDiagramsContainer.addEventListener('keydown', function (e) {
				if (e.key !== 'Enter' && e.key !== ' ') return;
				var wrap = e.target.closest('.jww-chord-diagram-wrap');
				if (!wrap) return;
				e.preventDefault();
				var name = wrap.getAttribute('data-chord-name') || (wrap.querySelector('.jww-chord-diagram-name') && wrap.querySelector('.jww-chord-diagram-name').textContent.trim());
				if (name) playChordSound(name);
			});
		}
		// Click on tab diagram: play notes for the segment at that horizontal position
		if (tabsContainer) {
			tabsContainer.addEventListener('click', function (e) {
				if (!tabSegmentsForClick.length) return;
				var rect = tabsContainer.getBoundingClientRect();
				var x = e.clientX - rect.left;
				var w = rect.width;
				if (w <= 0) return;
				var segmentIndex = Math.floor((x / w) * tabSegmentsForClick.length);
				segmentIndex = Math.max(0, Math.min(segmentIndex, tabSegmentsForClick.length - 1));
				playTabSegmentNotes(segmentIndex);
			});
		}
		document.getElementById('jww-transpose-minus') && document.getElementById('jww-transpose-minus').addEventListener('click', function () {
			var v = parseInt(transposeInput.value, 10);
			if (isNaN(v)) v = 0;
			transposeInput.value = String(Math.max(TRANSPOSE_MIN, v - 1));
			setTransposeDisplay();
			updateAll();
		});
		document.getElementById('jww-transpose-plus') && document.getElementById('jww-transpose-plus').addEventListener('click', function () {
			var v = parseInt(transposeInput.value, 10);
			if (isNaN(v)) v = 0;
			transposeInput.value = String(Math.min(TRANSPOSE_MAX, v + 1));
			setTransposeDisplay();
			updateAll();
		});
		document.getElementById('jww-capo-minus') && document.getElementById('jww-capo-minus').addEventListener('click', function () {
			var v = parseInt(capoInput.value, 10);
			if (isNaN(v)) v = 0;
			capoInput.value = String(Math.max(CAPO_MIN, v - 1));
			setCapoDisplay();
			updateAll();
		});
		document.getElementById('jww-capo-plus') && document.getElementById('jww-capo-plus').addEventListener('click', function () {
			var v = parseInt(capoInput.value, 10);
			if (isNaN(v)) v = 0;
			capoInput.value = String(Math.min(CAPO_MAX, v + 1));
			setCapoDisplay();
			updateAll();
		});

		var toggle = document.getElementById('jww-show-chords-toggle');
		if (toggle) {
			toggle.addEventListener('click', function () {
				showChords = !getStoredShowChords();
				setStoredShowChords(showChords);
				setChordsVisible(showChords);
			});
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', run);
	} else {
		run();
	}
})();
