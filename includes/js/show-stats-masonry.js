/**
 * Masonry layout for Setlist Data cards on single show pages.
 * Places each card in the shortest column for a true masonry effect.
 *
 * CSS-only true masonry is not reliably possible: (1) CSS multi-column was tried
 * but in this theme all content flowed into the first column. (2) grid-template-rows:
 * masonry exists in the spec but is only supported in Firefox behind a flag.
 * So we use JS and recalc on: resize, load, accordion toggle, and any card size change (ResizeObserver).
 */
(function () {
	'use strict';

	var GAP_PX = 20; // 1.25rem
	var BREAKPOINT_SMALL = 600;
	var BREAKPOINT_MEDIUM = 900;
	var CARD_SELECTOR = ':scope > .show-setlist-highlight-debuts, :scope > .show-setlist-highlight-returns, :scope > .show-setlist-highlight-standout, :scope > .show-setlist-album-stats, :scope > .show-tour-stats';

	function getColumnCount(containerWidth) {
		if (containerWidth <= BREAKPOINT_SMALL) {
			return 1;
		}
		if (containerWidth <= BREAKPOINT_MEDIUM) {
			return 2;
		}
		return 3;
	}

	function layoutMasonry() {
		var container = document.getElementById('show-stats-cards-masonry');
		if (!container || !container.classList.contains('show-stats-cards-masonry')) {
			return;
		}
		var items = Array.prototype.slice.call(container.querySelectorAll(CARD_SELECTOR));
		if (items.length === 0) {
			return;
		}

		var containerWidth = container.offsetWidth;
		var cols = getColumnCount(containerWidth);
		var gap = GAP_PX;
		var colWidth = (containerWidth - (cols - 1) * gap) / cols;
		var columnHeights = [];
		var i;
		for (i = 0; i < cols; i++) {
			columnHeights[i] = 0;
		}

		items.forEach(function (item) {
			item.style.position = 'absolute';
			item.style.setProperty('width', colWidth + 'px', 'important');
			item.style.setProperty('max-width', colWidth + 'px', 'important');
			item.style.left = '';
			item.style.right = '';
			item.style.top = '';
			item.style.marginBottom = '0';
		});

		// Force reflow so offsetHeight is correct after width change
		container.offsetHeight;

		items.forEach(function (item) {
			var minCol = 0;
			var minHeight = columnHeights[0];
			for (var c = 1; c < cols; c++) {
				if (columnHeights[c] < minHeight) {
					minHeight = columnHeights[c];
					minCol = c;
				}
			}
			var left = minCol * (colWidth + gap);
			item.style.left = left + 'px';
			item.style.top = columnHeights[minCol] + 'px';
			columnHeights[minCol] += item.offsetHeight + gap;
		});

		var maxHeight = Math.max.apply(null, columnHeights);
		container.style.height = (maxHeight > 0 ? maxHeight - gap : '') + 'px';
	}

	function debounce(fn, ms) {
		var t;
		return function () {
			clearTimeout(t);
			t = setTimeout(fn, ms);
		};
	}

	function observeMasonryCards(container) {
		var scheduled = false;
		function scheduleLayout() {
			if (scheduled) return;
			scheduled = true;
			requestAnimationFrame(function () {
				scheduled = false;
				layoutMasonry();
			});
		}
		var debouncedLayout = debounce(layoutMasonry, 80);

		// Recalc when any card’s size changes (e.g. accordion open/close in Songs on Albums)
		if (typeof ResizeObserver !== 'undefined') {
			var ro = new ResizeObserver(debouncedLayout);
			var items = container.querySelectorAll(CARD_SELECTOR);
			for (var i = 0; i < items.length; i++) {
				ro.observe(items[i]);
			}
		}

		// Recalc when a details element toggles (accordion open/close)
		container.addEventListener('toggle', function (e) {
			if (e.target.nodeName.toLowerCase() === 'details') {
				scheduleLayout();
			}
		}, true);
	}

	function init() {
		layoutMasonry();
		window.addEventListener('resize', debounce(layoutMasonry, 100));
		window.addEventListener('load', layoutMasonry);

		var container = document.getElementById('show-stats-cards-masonry');
		if (container && container.classList.contains('show-stats-cards-masonry')) {
			observeMasonryCards(container);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
