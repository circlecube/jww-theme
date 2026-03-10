/**
 * Floating share bar when user selects text in the lyrics section.
 * Share URLs are built with quoted lyrics, newline, song/artist, then link.
 */
(function () {
	'use strict';

	function escapeAttr(str) {
		var div = document.createElement('div');
		div.textContent = str;
		return div.innerHTML.replace(/"/g, '&quot;');
	}

	function getShareIcon(key) {
		// WordPress core social-link block SVG paths (same as footer / share-link-icons.php).
		var icons = {
			x: '<svg class="jww-share-btn-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M13.982 10.622 20.54 3h-1.554l-5.693 6.618L8.745 3H3.5l6.876 10.007L3.5 21h1.554l6.012-6.989L15.868 21h5.245l-7.131-10.378Zm-2.128 2.474-.697-.997-5.543-7.93H8l4.474 6.4.697.996 5.815 8.318h-2.387l-4.745-6.787Z"/></svg>',
			facebook: '<svg class="jww-share-btn-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M12 2C6.5 2 2 6.5 2 12c0 5 3.7 9.1 8.4 9.9v-7H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.5h-1.3c-1.2 0-1.6.8-1.6 1.6V12h2.8l-.4 2.9h-2.3v7C18.3 21.1 22 17 22 12c0-5.5-4.5-10-10-10z"/></svg>',
			mastodon: '<svg class="jww-share-btn-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M23.193 7.879c0-5.206-3.411-6.732-3.411-6.732C18.062.357 15.108.025 12.041 0h-.076c-3.068.025-6.02.357-7.74 1.147 0 0-3.411 1.526-3.411 6.732 0 1.192-.023 2.618.015 4.129.124 5.092.934 10.109 5.641 11.355 2.17.574 4.034.695 5.535.612 2.722-.15 4.25-.972 4.25-.972l-.09-1.975s-1.945.613-4.129.539c-2.165-.074-4.449-.233-4.799-2.891a5.499 5.499 0 0 1-.048-.745s2.125.52 4.817.643c1.646.075 3.19-.097 4.758-.283 3.007-.359 5.625-2.212 5.954-3.905.517-2.665.475-6.507.475-6.507zm-4.024 6.709h-2.497V8.469c0-1.29-.543-1.944-1.628-1.944-1.2 0-1.802.776-1.802 2.312v3.349h-2.483v-3.35c0-1.536-.602-2.312-1.802-2.312-1.085 0-1.628.655-1.628 1.944v6.119H4.832V8.284c0-1.289.328-2.313.987-3.07.68-.758 1.569-1.146 2.674-1.146 1.278 0 2.246.491 2.886 1.474L12 6.585l.622-1.043c.64-.983 1.608-1.474 2.886-1.474 1.104 0 1.994.388 2.674 1.146.658.757.986 1.781.986 3.07v6.304z"/></svg>',
			bluesky: '<svg class="jww-share-btn-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M6.3,4.2c2.3,1.7,4.8,5.3,5.7,7.2.9-1.9,3.4-5.4,5.7-7.2,1.7-1.3,4.3-2.2,4.3.9s-.4,5.2-.6,5.9c-.7,2.6-3.3,3.2-5.6,2.8,4,.7,5.1,3,2.9,5.3-5,5.2-6.7-2.8-6.7-2.8,0,0-1.7,8-6.7,2.8-2.2-2.3-1.2-4.6,2.9-5.3-2.3.4-4.9-.3-5.6-2.8-.2-.7-.6-5.3-.6-5.9,0-3.1,2.7-2.1,4.3-.9h0Z"/></svg>',
			threads: '<svg class="jww-share-btn-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M16.3 11.3c-.1 0-.2-.1-.2-.1-.1-2.6-1.5-4-3.9-4-1.4 0-2.6.6-3.3 1.7l1.3.9c.5-.8 1.4-1 2-1 .8 0 1.4.2 1.7.7.3.3.5.8.5 1.3-.7-.1-1.4-.2-2.2-.1-2.2.1-3.7 1.4-3.6 3.2 0 .9.5 1.7 1.3 2.2.7.4 1.5.6 2.4.6 1.2-.1 2.1-.5 2.7-1.3.5-.6.8-1.4.9-2.4.6.3 1 .8 1.2 1.3.4.9.4 2.4-.8 3.6-1.1 1.1-2.3 1.5-4.3 1.5-2.1 0-3.8-.7-4.8-2S5.7 14.3 5.7 12c0-2.3.5-4.1 1.5-5.4 1.1-1.3 2.7-2 4.8-2 2.2 0 3.8.7 4.9 2 .5.7.9 1.5 1.2 2.5l1.5-.4c-.3-1.2-.8-2.2-1.5-3.1-1.3-1.7-3.3-2.6-6-2.6-2.6 0-4.7.9-6 2.6C4.9 7.2 4.3 9.3 4.3 12s.6 4.8 1.9 6.4c1.4 1.7 3.4 2.6 6 2.6 2.3 0 4-.6 5.3-2 1.8-1.8 1.7-4 1.1-5.4-.4-.9-1.2-1.7-2.3-2.3zm-4 3.8c-1 .1-2-.4-2-1.3 0-.7.5-1.5 2.1-1.6h.5c.6 0 1.1.1 1.6.2-.2 2.3-1.3 2.7-2.2 2.7z"/></svg>',
			linkedin: '<svg class="jww-share-btn-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M19.7,3H4.3C3.582,3,3,3.582,3,4.3v15.4C3,20.418,3.582,21,4.3,21h15.4c0.718,0,1.3-0.582,1.3-1.3V4.3 C21,3.582,20.418,3,19.7,3z M8.339,18.338H5.667v-8.59h2.672V18.338z M7.004,8.574c-0.857,0-1.549-0.694-1.549-1.548 c0-0.855,0.691-1.548,1.549-1.548c0.854,0,1.547,0.694,1.547,1.548C8.551,7.881,7.858,8.574,7.004,8.574z M18.339,18.338h-2.669 v-4.177c0-0.996-0.017-2.278-1.387-2.278c-1.389,0-1.601,1.086-1.601,2.206v4.249h-2.667v-8.59h2.559v1.174h0.037 c0.356-0.675,1.227-1.387,2.526-1.387c2.703,0,3.203,1.779,3.203,4.092V18.338z"/></svg>',
			reddit: '<svg class="jww-share-btn-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M5.27 9.221A2.775 2.775 0 0 0 2.498 11.993a2.785 2.785 0 0 0 1.6 2.511 5.337 5.337 0 0 0 2.374 4.11 9.386 9.386 0 0 0 5.539 1.7 9.386 9.386 0 0 0 5.541-1.7 5.331 5.331 0 0 0 2.372-4.114 2.787 2.787 0 0 0 1.583-2.5 2.775 2.775 0 0 0-2.772-2.772 2.742 2.742 0 0 0-1.688.574 9.482 9.482 0 0 0-4.637-1.348v-.008a2.349 2.349 0 0 1 2.011-2.316 1.97 1.97 0 0 0 1.926 1.521 1.98 1.98 0 0 0 1.978-1.978 1.98 1.98 0 0 0-1.978-1.978 1.985 1.985 0 0 0-1.938 1.578 3.183 3.183 0 0 0-2.849 3.172v.011a9.463 9.463 0 0 0-4.59 1.35 2.741 2.741 0 0 0-1.688-.574Zm6.736 9.1a3.162 3.162 0 0 1-2.921-1.944.215.215 0 0 1 .014-.2.219.219 0 0 1 .168-.106 27.327 27.327 0 0 1 2.74-.133 27.357 27.357 0 0 1 2.74.133.219.219 0 0 1 .168.106.215.215 0 0 1 .014.2 3.158 3.158 0 0 1-2.921 1.944Zm3.743-3.157a1.265 1.265 0 0 1-1.4-1.371 1.954 1.954 0 0 1 .482-1.442 1.15 1.15 0 0 1 .842-.379 1.7 1.7 0 0 1 1.49 1.777 1.323 1.323 0 0 1-.325 1.015 1.476 1.476 0 0 1-1.089.4Zm-7.485 0a1.476 1.476 0 0 1-1.086-.4 1.323 1.323 0 0 1-.325-1.016 1.7 1.7 0 0 1 1.49-1.777 1.151 1.151 0 0 1 .843.379 1.951 1.951 0 0 1 .481 1.441 1.276 1.276 0 0 1-1.403 1.373Z"/></svg>',
			pinterest: '<svg class="jww-share-btn-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M12.289,2C6.617,2,3.606,5.648,3.606,9.622c0,1.846,1.025,4.146,2.666,4.878c0.25,0.111,0.381,0.063,0.439-0.169 c0.044-0.175,0.267-1.029,0.365-1.428c0.032-0.128,0.017-0.237-0.091-0.362C6.445,11.911,6.01,10.75,6.01,9.668 c0-2.777,2.194-5.464,5.933-5.464c3.23,0,5.49,2.108,5.49,5.122c0,3.407-1.794,5.768-4.13,5.768c-1.291,0-2.257-1.021-1.948-2.277 c0.372-1.495,1.089-3.112,1.089-4.191c0-0.967-0.542-1.775-1.663-1.775c-1.319,0-2.379,1.309-2.379,3.059 c0,1.115,0.394,1.869,0.394,1.869s-1.302,5.279-1.54,6.261c-0.405,1.666,0.053,4.368,0.094,4.604 c0.021,0.126,0.167,0.169,0.25,0.063c0.129-0.165,1.699-2.419,2.142-4.051c0.158-0.59,0.817-2.995,0.817-2.995 c0.43,0.784,1.681,1.446,3.013,1.446c3.963,0,6.822-3.494,6.822-7.833C20.394,5.112,16.849,2,12.289,2"/></svg>'
		};
		return icons[key] || '';
	}

	function buildShareUrls(shareUrl, selectedText, songArtist) {
		var quotedLyrics = '"' + selectedText.replace(/"/g, '') + '"';
		var line2 = songArtist ? (quotedLyrics + '\n\n' + songArtist + '\n' + shareUrl) : (quotedLyrics + '\n' + shareUrl);
		var encodedUrl = encodeURIComponent(shareUrl);
		var encodedLine2 = encodeURIComponent(line2);
		return {
			x: 'https://x.com/intent/tweet?text=' + encodedLine2,
			facebook: 'https://www.facebook.com/sharer/sharer.php?u=' + encodedUrl,
			mastodon: 'https://mastodonshare.com/?text=' + encodedLine2 + '&url=' + encodedUrl,
			bluesky: 'https://bsky.app/intent/compose?text=' + encodeURIComponent(line2),
			threads: 'https://www.threads.net/intent/post?text=' + encodedLine2 + '&url=' + encodedUrl,
			linkedin: 'https://www.linkedin.com/sharing/share-offsite/?url=' + encodedUrl,
			reddit: 'https://www.reddit.com/submit?url=' + encodedUrl + '&title=' + encodeURIComponent(quotedLyrics + (songArtist ? ' – ' + songArtist : '')),
			pinterest: 'https://www.pinterest.com/pin/create/button/?url=' + encodedUrl + '&description=' + encodeURIComponent(line2)
		};
	}

	function showFloat(container, bar, shareUrl, selectedText, selection) {
		var songArtist = container.getAttribute('data-share-context') || '';
		var urls = buildShareUrls(shareUrl, selectedText, songArtist);
		var titles = {
			x: 'Share these lyrics on X',
			facebook: 'Share these lyrics on Facebook',
			mastodon: 'Share these lyrics on Mastodon',
			bluesky: 'Share these lyrics on Bluesky',
			threads: 'Share these lyrics on Threads',
			linkedin: 'Share these lyrics on LinkedIn',
			reddit: 'Share these lyrics on Reddit',
			pinterest: 'Share these lyrics on Pinterest'
		};
		var html = '<span class="jww-lyrics-share-float-label">Share selected lyrics</span><div class="jww-share-buttons jww-share-buttons--lyrics-float">';
		['x', 'facebook', 'mastodon', 'bluesky', 'threads', 'linkedin', 'reddit', 'pinterest'].forEach(function (key) {
			var href = urls[key].replace(/&/g, '&amp;').replace(/"/g, '&quot;');
			var title = titles[key];
			var icon = getShareIcon(key);
			html += '<a href="' + href + '" target="_blank" rel="noopener noreferrer" class="jww-share-btn jww-share-btn--' + key + '" title="' + escapeAttr(title) + '" aria-label="' + escapeAttr(title) + '">' + icon + '</a>';
		});
		html += '</div>';
		bar.innerHTML = html;
		bar.classList.add('is-visible');

		var rect;
		if (selection && selection.rangeCount > 0) {
			rect = selection.getRangeAt(0).getBoundingClientRect();
		} else {
			rect = container.getBoundingClientRect();
		}
		var barRect = bar.getBoundingClientRect();
		var top = rect.top - barRect.height - 8;
		if (top < 8) {
			top = 8;
		}
		bar.style.top = top + 'px';
		bar.style.left = Math.max(8, rect.left) + 'px';
	}

	function hideFloat(bar) {
		bar.classList.remove('is-visible');
	}

	function isSelectionInside(container, selection) {
		if (!selection || selection.rangeCount === 0) return false;
		var range = selection.getRangeAt(0);
		return container.contains(range.commonAncestorContainer);
	}

	function init() {
		var container = document.getElementById('jww-lyrics-section');
		if (!container) return;

		var shareUrl = container.getAttribute('data-share-url') || (window.location.origin + window.location.pathname);
		var bar = document.getElementById('jww-lyrics-share-float');
		if (!bar) {
			bar = document.createElement('div');
			bar.id = 'jww-lyrics-share-float';
			bar.className = 'jww-lyrics-share-float';
			bar.setAttribute('role', 'toolbar');
			bar.setAttribute('aria-label', 'Share selected lyrics');
			document.body.appendChild(bar);
		}

		function onSelectionChange() {
			var sel = window.getSelection();
			var text = sel ? sel.toString().trim() : '';
			if (text && isSelectionInside(container, sel)) {
				showFloat(container, bar, shareUrl, text, sel);
			} else {
				hideFloat(bar);
			}
		}

		function onPointerUp() {
			setTimeout(onSelectionChange, 10);
		}

		container.addEventListener('mouseup', onPointerUp);
		container.addEventListener('touchend', onPointerUp);
		document.addEventListener('selectionchange', function () {
			if (!window.getSelection().toString().trim()) hideFloat(bar);
		});

		document.addEventListener('click', function (e) {
			if (bar.classList.contains('is-visible') && !bar.contains(e.target) && !container.contains(e.target)) {
				hideFloat(bar);
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
