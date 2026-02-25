/**
 * Venues admin: search Wikimedia, modal, sideload and attach image.
 */
(function ($) {
	'use strict';

	var modal = null;
	var resultsEl = null;
	var selectedImage = null;
	var currentTermId = null;

	function getModal() {
		if (!modal) {
			modal = document.getElementById('jww-venue-modal');
		}
		return modal;
	}

	function getResultsEl() {
		if (!resultsEl) {
			resultsEl = document.querySelector('#jww-venue-modal .jww-venue-modal-results');
		}
		return resultsEl;
	}

	function openModal() {
		var m = getModal();
		if (m) m.style.display = 'flex';
		selectedImage = null;
		var acceptBtn = document.querySelector('.jww-venue-modal-accept');
		if (acceptBtn) {
			acceptBtn.disabled = true;
			acceptBtn.textContent = 'Accept';
		}
	}

	function closeModal() {
		var m = getModal();
		if (m) m.style.display = 'none';
		selectedImage = null;
		currentTermId = null;
	}

	function setResultsLoading(loading) {
		var el = getResultsEl();
		if (el) el.classList.toggle('is-loading', !!loading);
	}

	function renderResults(images) {
		var el = getResultsEl();
		if (!el) return;
		el.innerHTML = '';
		if (!images || images.length === 0) {
			el.textContent = jwwVenuesAdmin.l10n.noResults;
			return;
		}
		images.forEach(function (img) {
			var wrap = document.createElement('div');
			wrap.className = 'jww-venue-result-item';
			wrap.dataset.title = img.title || '';
			wrap.dataset.url = img.url || '';
			var im = document.createElement('img');
			im.src = img.thumburl;
			im.alt = img.title || '';
			im.loading = 'lazy';
			wrap.appendChild(im);
			wrap.addEventListener('click', function () {
				document.querySelectorAll('.jww-venue-result-item.selected').forEach(function (n) { n.classList.remove('selected'); });
				wrap.classList.add('selected');
				selectedImage = img;
				document.querySelector('.jww-venue-modal-accept').disabled = false;
			});
			el.appendChild(wrap);
		});
		document.querySelector('.jww-venue-modal-accept').disabled = true;
	}

	function searchWikimedia(termId, venueName) {
		currentTermId = termId;
		openModal();
		setResultsLoading(true);
		renderResults([]);
		$.post(jwwVenuesAdmin.ajaxUrl, {
			action: 'jww_venues_search_wikimedia',
			nonce: jwwVenuesAdmin.nonce,
			search: venueName
		})
			.done(function (res) {
				setResultsLoading(false);
				if (res.success && res.data && res.data.images) {
					renderResults(res.data.images);
				} else {
					renderResults([]);
				}
			})
			.fail(function () {
				setResultsLoading(false);
				renderResults([]);
			});
	}

	function sideloadAndAttach(termId, payload, doneCallback) {
		$.post(jwwVenuesAdmin.ajaxUrl, $.extend({
			action: 'jww_venues_sideload_attach',
			nonce: jwwVenuesAdmin.nonce,
			term_id: termId
		}, payload))
			.done(function (res) {
				if (res.success) {
					doneCallback(null, res.data);
				} else {
					doneCallback(res.data && res.data.message ? res.data.message : jwwVenuesAdmin.l10n.error);
				}
			})
			.fail(function () {
				doneCallback(jwwVenuesAdmin.l10n.error);
			});
	}

	function removeRow(termId) {
		var row = document.querySelector('.jww-venues-admin tr[data-term-id="' + termId + '"]');
		if (row) row.classList.add('jww-venue-row-removed');
	}

	// Search for image
	$(document).on('click', '.jww-venue-search', function () {
		var termId = $(this).data('term-id');
		var searchQuery = $(this).data('search-query');
		if (!searchQuery) searchQuery = $(this).data('venue-name');
		searchWikimedia(termId, searchQuery);
	});

	// Manual import
	$(document).on('click', '.jww-venue-manual', function () {
		var termId = $(this).data('term-id');
		var url = window.prompt(jwwVenuesAdmin.l10n.manualPrompt);
		if (url === null || url === '') return;
		url = url.trim();
		if (!url) return;
		var $btn = $(this);
		$btn.prop('disabled', true).text(jwwVenuesAdmin.l10n.sideloading);
		sideloadAndAttach(termId, { image_url: url }, function (err) {
			$btn.prop('disabled', false).text('Manual import');
			if (err) {
				alert(err);
			} else {
				removeRow(termId);
			}
		});
	});

	// Modal: Reject
	$(document).on('click', '.jww-venue-modal-reject', closeModal);

	// Modal: Accept
	$(document).on('click', '.jww-venue-modal-accept', function () {
		if (!selectedImage || !currentTermId) return;
		var termIdToUpdate = currentTermId;
		var $accept = $(this);
		$accept.prop('disabled', true).text(jwwVenuesAdmin.l10n.sideloading);
		sideloadAndAttach(termIdToUpdate, { wikimedia_title: selectedImage.title }, function (err) {
			closeModal();
			if (err) {
				alert(err);
			} else {
				removeRow(termIdToUpdate);
			}
		});
	});

	// Modal: backdrop click
	$(document).on('click', '.jww-venue-modal-backdrop', closeModal);
})(jQuery);
