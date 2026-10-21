/**
 * LXP Certificate widget — scale-to-fit, save details, download PDF.
 *
 * The PDF is a capture of the rendered certificate (html2canvas -> jsPDF) so
 * it matches the client's design exactly. Capture works on an off-screen,
 * unscaled clone in which every <input> is swapped for a .lxp-cert-value div
 * (html2canvas draws input values unreliably) and .lxp-cert--capture applies
 * the design's print rules (no shadow, no rounded corners).
 *
 * Saving always happens before a download, so what is printed is what is
 * stored. Cookie-authenticated, hence the X-WP-Nonce header (CLAUDE.md #16).
 *
 * @see includes/widgets/lxp-certificate-widget.php
 * @see docs/certificate-context.md
 */
(function () {
	'use strict';

	var CERT_W = 1056;
	var CERT_H = 816;
	var FONT_FAMILY = '"LXP Cert Montserrat"';

	function setStatus(wrap, message, type) {
		var el = wrap.querySelector('.lxp-cert-status');
		if (!el) { return; }
		el.textContent = message || '';
		el.classList.toggle('is-error', type === 'error');
		el.classList.toggle('is-success', type === 'success');
	}

	function setBusy(wrap, busy) {
		var buttons = wrap.querySelectorAll('.lxp-cert-actions button');
		for (var i = 0; i < buttons.length; i++) { buttons[i].disabled = busy; }
	}

	// The card is a fixed 1056x816 artboard; shrink it to fit narrow containers.
	function fit(wrap) {
		var viewport = wrap.querySelector('.lxp-cert-viewport');
		var stage = wrap.querySelector('.lxp-cert-stage');
		if (!viewport || !stage) { return; }
		var scale = Math.min(1, viewport.clientWidth / CERT_W);
		stage.style.transform = scale < 1 ? 'scale(' + scale + ')' : '';
		viewport.style.height = (CERT_H * scale) + 'px';
	}

	// The course card has a fixed height sized for a short title. Step the
	// title's font-size down from the design's 30px until title + subtitle fit.
	// Set inline, so the capture clone inherits the fitted size.
	var TITLE_MAX = 30;
	var TITLE_MIN = 14;
	var CARD_GUTTER = 12; // breathing room above and below the text block

	function fitCourseTitle(wrap) {
		var card = wrap.querySelector('.cert .card');
		var text = card && card.querySelector('.card-text');
		var title = text && text.querySelector('h2');
		if (!title) { return; }

		var limit = card.clientHeight - CARD_GUTTER * 2;
		var size = TITLE_MAX;
		title.style.fontSize = size + 'px';
		while (text.offsetHeight > limit && size > TITLE_MIN) {
			size -= 1;
			title.style.fontSize = size + 'px';
		}
	}

	function field(wrap, name) {
		return wrap.querySelector('.cert input[name="' + name + '"]');
	}

	function save(wrap) {
		var nameInput = field(wrap, 'full_name');
		var emailInput = field(wrap, 'email');
		var schoolInput = field(wrap, 'school');
		var name = nameInput.value.trim();
		var email = emailInput.value.trim();

		if (!name) {
			setStatus(wrap, 'Please type your full name on the certificate.', 'error');
			nameInput.focus();
			return Promise.resolve(false);
		}
		if (email && !emailInput.checkValidity()) {
			setStatus(wrap, 'Please enter a valid email address, or leave it blank.', 'error');
			emailInput.focus();
			return Promise.resolve(false);
		}

		// Elementor editor preview — nothing to save against.
		if (wrap.getAttribute('data-course-id') === '0') {
			return Promise.resolve(true);
		}

		var body = new FormData();
		body.append('course_id', wrap.getAttribute('data-course-id'));
		body.append('name', name);
		body.append('email', email);
		body.append('school', schoolInput.value.trim());

		return fetch(wrap.getAttribute('data-save-url'), {
			method: 'POST',
			body: body,
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': wrap.getAttribute('data-nonce') }
		})
			.then(function (r) {
				return r.json()
					.catch(function () { return null; })
					.then(function (json) { return { ok: r.ok, json: json }; });
			})
			.then(function (res) {
				if (res.ok && res.json && res.json.success) { return true; }
				var json = res.json || {};
				var message = (json.data && json.data.message) || json.message ||
					'Your details could not be saved. Please try again.';
				setStatus(wrap, message, 'error');
				return false;
			})
			.catch(function () {
				setStatus(wrap, 'Network error — please check your connection and try again.', 'error');
				return false;
			});
	}

	// Off-screen, unscaled copy of the certificate with inputs flattened to text.
	function buildCaptureHost(wrap) {
		var cert = wrap.querySelector('.cert');
		var clone = cert.cloneNode(true);
		clone.classList.add('lxp-cert--capture');

		// cloneNode copies the value *attribute*, not what was typed — read the live inputs.
		var liveInputs = cert.querySelectorAll('.field input');
		var cloneInputs = clone.querySelectorAll('.field input');
		for (var i = 0; i < cloneInputs.length; i++) {
			var value = document.createElement('div');
			value.className = 'lxp-cert-value';
			value.textContent = liveInputs[i] ? liveInputs[i].value : '';
			cloneInputs[i].parentNode.replaceChild(value, cloneInputs[i]);
		}

		var host = document.createElement('div');
		host.className = 'lxp-cert-wrap lxp-cert-capture-host';
		host.setAttribute('aria-hidden', 'true');
		host.appendChild(clone);
		document.body.appendChild(host);
		return host;
	}

	function fontsReady() {
		if (!document.fonts || !document.fonts.load) { return Promise.resolve(); }
		return Promise.all([400, 500, 600, 700].map(function (weight) {
			return document.fonts.load(weight + ' 16px ' + FONT_FAMILY).catch(function () {});
		})).then(function () { return document.fonts.ready; });
	}

	function downloadPdf(wrap) {
		if (typeof window.html2canvas !== 'function' || !window.jspdf || !window.jspdf.jsPDF) {
			setStatus(wrap, 'The PDF tools did not load. Please refresh the page and try again.', 'error');
			return Promise.resolve();
		}

		var host = null;
		return fontsReady()
			.then(function () {
				host = buildCaptureHost(wrap);
				return window.html2canvas(host.firstChild, {
					scale: 3,
					useCORS: true,
					backgroundColor: '#ffffff',
					logging: false,
					width: CERT_W,
					height: CERT_H
				});
			})
			.then(function (canvas) {
				var pdf = new window.jspdf.jsPDF({ orientation: 'landscape', unit: 'in', format: [11, 8.5], compress: true });
				pdf.addImage(canvas.toDataURL('image/jpeg', 0.95), 'JPEG', 0, 0, 11, 8.5);
				pdf.save(wrap.getAttribute('data-filename') || 'certificate.pdf');
				setStatus(wrap, 'Your certificate has been downloaded.', 'success');
			})
			.catch(function () {
				setStatus(wrap, 'The certificate could not be generated. Please try again.', 'error');
			})
			.then(function () {
				if (host && host.parentNode) { host.parentNode.removeChild(host); }
			});
	}

	function init(wrap) {
		if (!wrap || wrap.getAttribute('data-lxp-cert-ready')) { return; }
		wrap.setAttribute('data-lxp-cert-ready', '1');

		fit(wrap);
		fitCourseTitle(wrap);
		// Re-measure once the certificate font is in — fallback-font metrics differ.
		fontsReady().then(function () { fitCourseTitle(wrap); });
		if (window.ResizeObserver) {
			new ResizeObserver(function () { fit(wrap); }).observe(wrap);
		} else {
			window.addEventListener('resize', function () { fit(wrap); });
		}

		var saveBtn = wrap.querySelector('[data-action="save"]');
		var downloadBtn = wrap.querySelector('[data-action="download"]');

		if (saveBtn) {
			saveBtn.addEventListener('click', function () {
				setBusy(wrap, true);
				setStatus(wrap, 'Saving…');
				save(wrap).then(function (ok) {
					if (ok) { setStatus(wrap, 'Your details have been saved.', 'success'); }
					setBusy(wrap, false);
				});
			});
		}

		if (downloadBtn) {
			downloadBtn.addEventListener('click', function () {
				setBusy(wrap, true);
				setStatus(wrap, 'Preparing your certificate…');
				save(wrap)
					.then(function (ok) { return ok ? downloadPdf(wrap) : null; })
					.then(function () { setBusy(wrap, false); });
			});
		}
	}

	function initAll(root) {
		var wraps = (root || document).querySelectorAll('.lxp-cert-wrap[data-save-url]');
		for (var i = 0; i < wraps.length; i++) { init(wraps[i]); }
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () { initAll(); });
	} else {
		initAll();
	}

	// Elementor editor re-renders widgets after load.
	function hookElementor() {
		window.elementorFrontend.hooks.addAction('frontend/element_ready/lxp-certificate.default', function ($scope) {
			initAll($scope && $scope[0] ? $scope[0] : document);
		});
	}
	if (window.elementorFrontend && window.elementorFrontend.hooks) {
		hookElementor();
	} else if (window.jQuery) {
		window.jQuery(window).on('elementor/frontend/init', hookElementor);
	}
})();
