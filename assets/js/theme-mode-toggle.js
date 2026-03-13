/**
 * Theme mode toggle: light / dark. Preference stored in localStorage (jww_theme).
 * Toggle button in footer; apply theme on click and update label/aria.
 */
(function () {
	'use strict';

	var STORAGE_KEY = 'jww_theme';

	function getTheme() {
		try {
			return localStorage.getItem(STORAGE_KEY) || 'light';
		} catch (e) {
			return 'light';
		}
	}

	function setTheme(value) {
		try {
			localStorage.setItem(STORAGE_KEY, value);
		} catch (e) {}
		if (value === 'dark') {
			document.documentElement.setAttribute('data-theme', 'dark');
		} else {
			document.documentElement.removeAttribute('data-theme');
		}
	}

	function updateToggleButton(btn, isDark) {
		if (!btn) return;
		var label = btn.querySelector('.jww-theme-mode-toggle-label');
		var icon = btn.querySelector('.jww-theme-mode-toggle-icon');
		if (isDark) {
			btn.setAttribute('aria-label', btn.getAttribute('data-label-light') || 'Switch to light mode');
			btn.setAttribute('title', btn.getAttribute('data-label-light') || 'Switch to light mode');
			if (label) label.textContent = 'Light mode';
			if (icon) icon.setAttribute('aria-hidden', 'true');
		} else {
			btn.setAttribute('aria-label', btn.getAttribute('data-label-dark') || 'Switch to dark mode');
			btn.setAttribute('title', btn.getAttribute('data-label-dark') || 'Switch to dark mode');
			if (label) label.textContent = 'Dark mode';
			if (icon) icon.setAttribute('aria-hidden', 'true');
		}
	}

	function run() {
		var btn = document.getElementById('jww-theme-mode-toggle');
		if (!btn) return;

		var isDark = getTheme() === 'dark';
		updateToggleButton(btn, isDark);

		btn.addEventListener('click', function () {
			isDark = getTheme() === 'dark';
			isDark = !isDark;
			setTheme(isDark ? 'dark' : 'light');
			updateToggleButton(btn, isDark);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', run);
	} else {
		run();
	}
})();
