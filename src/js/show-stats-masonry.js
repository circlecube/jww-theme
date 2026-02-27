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
	var BREAKPOINT_MEDIUM = 900;
	var BREAKPOINT_SMALL = 600; // below this: 2 half-cards per row; above: 4 per row when 2 cols
	var CARD_SELECTOR = ':scope > .show-setlist-highlight-debuts, :scope > .show-setlist-highlight-returns, :scope > .show-setlist-highlight-tour-debuts, :scope > .show-setlist-highlight-standout, :scope > .show-setlist-album-stats, :scope > .show-tour-stats, :scope > .tour-overview-card, :scope > .tour-debuts-card, :scope > .tour-one-offs-card, :scope > .tour-release-representation, :scope > .tour-standout-card, :scope > .show-insight-song-count, :scope > .show-insight-tour-gap, :scope > .show-insight-venue-count, :scope > .show-insight-city-count';

	function isHalfWidthCard(item) {
		return item.classList.contains('masonry-card--half') || item.getAttribute('data-masonry-size') === 'half';
	}

	function getColumnCount(containerWidth, containerId) {
		// Use at least 2 columns so half-width (compact) cards stay half-wide on mobile.
		// Mobile: 2 cols → half cards = half width (2 per row), full-width cards = full container width.
		if (containerWidth <= BREAKPOINT_MEDIUM) {
			return 2;
		}
		if (containerId === 'tour-stats-cards-masonry' || containerId === 'location-stats-cards-masonry' || containerId === 'archive-stats-cards-masonry') {
			return 3;
		}
		return 3;
	}

	function layoutMasonry() {
		var container = document.getElementById('show-stats-cards-masonry') || document.getElementById('tour-stats-cards-masonry') || document.getElementById('location-stats-cards-masonry') || document.getElementById('archive-stats-cards-masonry');
		if (!container || !container.classList.contains('show-stats-cards-masonry')) {
			return;
		}
		var items = Array.prototype.slice.call(container.querySelectorAll(CARD_SELECTOR));
		if (items.length === 0) {
			return;
		}

		var containerWidth = container.offsetWidth;
		if (containerWidth <= 0) {
			return;
		}
		var cols = getColumnCount(containerWidth, container.id || '');
		var gap = GAP_PX;
		var colWidth = (containerWidth - (cols - 1) * gap) / cols;
		// Narrow (<=BREAKPOINT_SMALL) + 2 cols: 2 half-cards per row. Else 2 cols: 4 per row. 3 cols: 6 per row.
		var isNarrowTwoCol = cols === 2 && containerWidth <= BREAKPOINT_SMALL;
		var halfWidth = isNarrowTwoCol ? (containerWidth - gap) / 2 : (colWidth - gap) / 2;
		var slotCount = isNarrowTwoCol ? 2 : (cols === 2 ? 4 : cols * 2);
		var slotHeights = [];
		var i;
		for (i = 0; i < slotCount; i++) {
			slotHeights[i] = 0;
		}

		items.forEach(function (item) {
			var isHalf = cols > 1 && isHalfWidthCard(item);
			// Full-width cards span entire container only when 2 columns (mobile); otherwise one column
			var w = isHalf ? halfWidth : (cols === 2 ? containerWidth : colWidth);
			item.style.position = 'absolute';
			item.style.setProperty('width', w + 'px', 'important');
			item.style.setProperty('max-width', w + 'px', 'important');
			item.style.left = '';
			item.style.right = '';
			item.style.top = '';
			item.style.marginBottom = '0';
		});

		// Force reflow so offsetHeight is correct after width change
		container.offsetHeight;

		items.forEach(function (item) {
			var isHalf = cols > 1 && isHalfWidthCard(item);
			var itemHeight = item.offsetHeight;

			if (isHalf) {
				var minSlot = 0;
				var minH = slotHeights[0];
				for (var s = 1; s < slotCount; s++) {
					if (slotHeights[s] < minH) {
						minH = slotHeights[s];
						minSlot = s;
					}
				}
				var left;
				if (isNarrowTwoCol) {
					left = minSlot * (halfWidth + gap);
				} else {
					var column = Math.floor(minSlot / 2);
					var side = minSlot % 2;
					left = column * (colWidth + gap) + side * (halfWidth + gap);
				}
				item.style.left = left + 'px';
				item.style.top = slotHeights[minSlot] + 'px';
				slotHeights[minSlot] += itemHeight + gap;
			} else if (cols === 2) {
				// Full-width card on 2-column layout: span entire container, advance all slots
				var maxH = 0;
				for (var s = 0; s < slotCount; s++) {
					if (slotHeights[s] > maxH) maxH = slotHeights[s];
				}
				item.style.left = '0px';
				item.style.top = maxH + 'px';
				var newHeight = maxH + itemHeight + gap;
				for (var s = 0; s < slotCount; s++) {
					slotHeights[s] = newHeight;
				}
			} else {
				// Full-width card on 3-column layout: place in shortest column
				var minCol = 0;
				var colHeight = Math.max(slotHeights[0], slotHeights[1] || 0);
				for (var c = 1; c < cols; c++) {
					var h = Math.max(slotHeights[c * 2], slotHeights[c * 2 + 1]);
					if (h < colHeight) {
						colHeight = h;
						minCol = c;
					}
				}
				var left = minCol * (colWidth + gap);
				item.style.left = left + 'px';
				item.style.top = colHeight + 'px';
				var newHeight = colHeight + itemHeight + gap;
				slotHeights[minCol * 2] = newHeight;
				if (minCol * 2 + 1 < slotCount) {
					slotHeights[minCol * 2 + 1] = newHeight;
				}
			}
		});

		var maxHeight = Math.max.apply(null, slotHeights);
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

		// Recalc when any card's size changes (e.g. accordion open/close in Songs on Albums)
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

		var container = document.getElementById('show-stats-cards-masonry') || document.getElementById('tour-stats-cards-masonry') || document.getElementById('location-stats-cards-masonry') || document.getElementById('archive-stats-cards-masonry');
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
