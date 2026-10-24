/**
 * Lunara Header Command — off-canvas navigation (Design Spec §9).
 *
 * Opens from the header burger, closes on Esc, veil click, or the close
 * button. Focus is trapped inside the panel while open and restored to
 * the opener on close. No dependencies; does nothing unless the takeover
 * markup is present.
 */
(function () {
	'use strict';

	var root, panel, opener, lastFocus = null, openingFrame = null, closingTimer = null;

	function focusables() {
		return panel.querySelectorAll('a[href], button:not([disabled])');
	}

	function openNav() {
		if (!root) { return; }
		lastFocus = document.activeElement;
		window.clearTimeout(closingTimer);
		root.hidden = false;
		document.body.classList.add('lunara-nav-open');
		if (opener) { opener.setAttribute('aria-expanded', 'true'); }
		openingFrame = window.requestAnimationFrame(function () {
			openingFrame = null;
			if (root.hidden) return;
			root.classList.add('is-open');
			var f = focusables();
			if (f.length) { f[0].focus(); }
		});
	}

	function closeNav(immediate) {
		if (!root || root.hidden) { return; }
		window.cancelAnimationFrame(openingFrame);
		window.clearTimeout(closingTimer);
		root.classList.remove('is-open');
		document.body.classList.remove('lunara-nav-open');
		if (opener) { opener.setAttribute('aria-expanded', 'false'); }
		if (immediate === true) { root.hidden = true; }
		else { closingTimer = window.setTimeout(function () { root.hidden = true; }, 300); }
		if (immediate !== true && lastFocus && lastFocus.isConnected && lastFocus.focus) { lastFocus.focus(); }
		lastFocus = null;
	}

	function trapTab(event) {
		if (event.key !== 'Tab' || root.hidden) { return; }
		var f = focusables();
		if (!f.length) { return; }
		var first = f[0];
		var last = f[f.length - 1];
		if (event.shiftKey && document.activeElement === first) {
			event.preventDefault();
			last.focus();
		} else if (!event.shiftKey && document.activeElement === last) {
			event.preventDefault();
			first.focus();
		}
	}

	/*
	 * The Home cover (3.2.131): the masthead carries the wordmark, so the
	 * header's own brand stays hidden until that wordmark has scrolled up
	 * under the bar, then returns. Scrolling back down past it hides it again.
	 * Without IntersectionObserver the brand simply stays hidden.
	 */
	function coverReveal() {
		var header = document.querySelector('.lunara-header.is-cover');
		var mark = document.querySelector('.lunara-home-masthead-logo-frame') || document.querySelector('.lunara-home-masthead');
		if (!header || !mark || !('IntersectionObserver' in window)) { return; }
		var top = Math.max(0, Math.round(header.getBoundingClientRect().bottom));
		var observer = new IntersectionObserver(function (entries) {
			var entry = entries[entries.length - 1];
			var line = entry.rootBounds ? entry.rootBounds.top : top;
			header.classList.toggle('is-brand-revealed', !entry.isIntersecting && entry.boundingClientRect.bottom <= line + 1);
		}, { rootMargin: '-' + top + 'px 0px 0px 0px', threshold: 0 });
		observer.observe(mark);
	}

	function ready(fn) {
		if (document.readyState !== 'loading') { fn(); } else { document.addEventListener('DOMContentLoaded', fn); }
	}

	ready(function () {
		// Remove zombie Blocksy shell nodes some plugin still emits after
		// the parent-theme exit — CSS hides them; this deletes them so no
		// optimizer or cache variant can ever resurrect the floater.
		Array.prototype.forEach.call(document.querySelectorAll('.ct-drawer-canvas, #offcanvas.ct-panel'), function (el) {
			if (el.parentNode) { el.parentNode.removeChild(el); }
		});

		coverReveal();

		root   = document.getElementById('lunara-offcanvas');
		opener = document.querySelector('[data-lunara-nav-open]');
		if (!root || !opener) { return; }
		window.addEventListener('lunara:page:teardown', function () { closeNav(true); });
		panel = root.querySelector('.lunara-offcanvas-panel') || root;

		opener.addEventListener('click', openNav);

		Array.prototype.forEach.call(root.querySelectorAll('[data-lunara-nav-close]'), function (el) {
			el.addEventListener('click', closeNav);
		});

		// Following a link closes the panel (same-page anchors included).
		root.addEventListener('click', function (event) {
			if (event.target.closest && event.target.closest('a[href]')) { closeNav(); }
		});

		document.addEventListener('keydown', function (event) {
			if (root.hidden) { return; }
			if (event.key === 'Escape') { closeNav(); return; }
			trapTab(event);
		});
	});
})();
