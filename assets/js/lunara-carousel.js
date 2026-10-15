(function () {
    'use strict';
    function mount(pageRoot, scope) {
        pageRoot = pageRoot || document;
        // Standalone documents keep their native page-unload lifecycle.
        scope = scope || {
            on: function (target, type, fn, options) { target.addEventListener(type, fn, options); },
            timeout: window.setTimeout.bind(window), interval: window.setInterval.bind(window),
            frame: window.requestAnimationFrame.bind(window), observe: function (observer) { return observer; },
            cleanup: function () {}
        };
/**
 * Lunara Carousel — dependency-free slider with mobile-correct behavior.
 *
 * Fixes vs the previous version:
 *   1. Pause-on-hover uses pointer events with pointerType check, so a mobile
 *      tap no longer kills autoplay forever.
 *   2. Swipe-left / swipe-right gestures navigate slides on touch devices.
 *   3. Initialization waits for DOMContentLoaded (or runs immediately if the
 *      DOM is already parsed) — no more race conditions.
 *   4. Respects prefers-reduced-motion: stops autoplay for users who ask for
 *      reduced motion.
 *   5. Keyboard arrow-key navigation when the carousel has focus.
 *
 * HTML/CSS contract is unchanged:
 *   .lunara-carousel              — outer container; data-autoplay="ms" optional
 *   .lunara-carousel-slide        — slide; .active class drives visibility via CSS
 *   [data-lunara-slide-prev/next/toggle] — shared navigation and playback
 */

(function () {
	'use strict';

	var SWIPE_THRESHOLD_PX = 40;     // minimum horizontal travel for a swipe
	var SWIPE_VERT_LIMIT_PX = 60;    // ignore if vertical travel exceeds this
	var motionQuery = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)');
	var prefersReducedMotion = motionQuery && motionQuery.matches;

	function init(root) {
		// Splide-pilot ownership handshake: the pilot script runs earlier in
		// the footer and tags any root it claims synchronously (pending →
		// ready). If it has claimed this root, the generic driver stays out
		// entirely — no autoplay, no gesture bindings, no .active toggling
		// racing the pilot's DOM rebuild. If the pilot never claimed it
		// (Splide missing or failed), this driver remains the working
		// static fallback exactly as before.
		if (
			root.hasAttribute('data-lunara-splide-pilot-active') ||
			root.classList.contains('lunara-splide-pending') ||
			root.classList.contains('lunara-splide-ready')
		) {
			return;
		}

		var slides = Array.prototype.slice.call(
			root.querySelectorAll('.lunara-carousel-slide')
		);
		if (!slides.length) return;

		var previous = root.querySelector('[data-lunara-slide-prev]');
        var next = root.querySelector('[data-lunara-slide-next]');
        var toggle = root.querySelector('[data-lunara-slide-toggle], .splide__toggle');
        var userPaused = false, hovered = false, focused = false;
        var originalToggle = toggle ? { html: toggle.innerHTML, hidden: toggle.hidden, label: toggle.getAttribute('aria-label') } : null;

		var current = 0;
		var autoplayMs = parseInt(root.dataset.autoplay || '5000', 10);
		var sharedPace = document.querySelector('meta[name="lunara-slider-interval"]');
		if (sharedPace && autoplayMs > 0) { autoplayMs = Math.max(8000, Math.min(30000, Number(sharedPace.content) || 12000)); }
		var timer = null;

		function splideOwnsRoot() {
			return root.classList.contains('lunara-splide-ready') ||
				root.getAttribute('data-lunara-splide-pilot-active') === 'ready';
		}

		function show(n) {
			if (splideOwnsRoot()) {
				stopAutoplay();
				return;
			}

			current = (n + slides.length) % slides.length;
			slides.forEach(function (el, k) {
				el.classList.toggle('active', k === current);
			});
            var counter = root.querySelector('.lunara-oscar-facts-current');
            if (counter) counter.textContent = String(current + 1).padStart(2, '0');
		}

		function startAutoplay() {
			if (autoplayMs > 0 && slides.length > 1 && !timer && !prefersReducedMotion && !userPaused && !hovered && !focused && !touchActive && !document.hidden) {
				timer = scope.interval(function () { show(current + 1); }, autoplayMs);
			}
		}

		function stopAutoplay() {
			if (timer) { clearInterval(timer); timer = null; }
		}

		// Make the root focusable for keyboard nav, but not in normal tab flow
		// unless an a11y review elevates this later.
		if (!root.hasAttribute('tabindex')) {
			root.setAttribute('tabindex', '-1');
		}
		if (!root.hasAttribute('aria-roledescription')) {
			root.setAttribute('aria-roledescription', 'carousel');
		}

        function move(delta) { show(current + delta); stopAutoplay(); startAutoplay(); }
        if (previous) scope.on(previous, 'click', function () { move(-1); });
        if (next) scope.on(next, 'click', function () { move(1); });
        function playbackLabel() {
            if (!toggle) return;
            toggle.hidden = autoplayMs <= 0 || slides.length < 2 || prefersReducedMotion;
            toggle.textContent = userPaused ? 'Play' : 'Pause';
            toggle.setAttribute('aria-label', userPaused ? 'Start autoplay' : 'Pause autoplay');
        }
        if (toggle) scope.on(toggle, 'click', function () {
            userPaused = !userPaused;
            if (!userPaused) { focused = false; hovered = false; }
            stopAutoplay(); startAutoplay(); playbackLabel();
        });
        playbackLabel();
        if (motionQuery && motionQuery.addEventListener) scope.on(motionQuery, 'change', function (event) {
            prefersReducedMotion = event.matches;
            stopAutoplay(); startAutoplay(); playbackLabel();
        });
        scope.on(root, 'focusin', function () { focused = true; stopAutoplay(); });
        scope.on(root, 'focusout', function (event) {
            focused = root.contains(event.relatedTarget);
            if (!focused) startAutoplay();
        });
        // Pause-on-hover — but ONLY for actual mouse pointers.
		// pointerenter/leave with pointerType === 'mouse' avoids the mobile-tap trap.
		scope.on(root, 'pointerenter', function (e) {
			if (e.pointerType === 'mouse') { hovered = true; stopAutoplay(); }
		});
		scope.on(root, 'pointerleave', function (e) {
			if (e.pointerType === 'mouse') { hovered = false; startAutoplay(); }
		});

		// Touch swipe gestures
		var touchStartX = 0, touchStartY = 0, touchActive = false;
		scope.on(root, 'touchstart', function (e) {
			if (!e.touches || !e.touches[0]) return;
			touchStartX = e.touches[0].clientX;
			touchStartY = e.touches[0].clientY;
			touchActive = true;
            stopAutoplay();
		}, { passive: true });

		scope.on(root, 'touchend', function (e) {
			if (!touchActive) return;
			touchActive = false;
            startAutoplay();
			var t = e.changedTouches && e.changedTouches[0];
			if (!t) return;
			var dx = t.clientX - touchStartX;
			var dy = t.clientY - touchStartY;
			if (Math.abs(dy) > SWIPE_VERT_LIMIT_PX) return;     // vertical scroll, not a swipe
			if (Math.abs(dx) < SWIPE_THRESHOLD_PX) return;      // too short to be a swipe
			show(current + (dx < 0 ? 1 : -1));
			stopAutoplay(); startAutoplay();
		});

        scope.on(root, 'touchcancel', function () { touchActive = false; startAutoplay(); });

		// Keyboard arrows (when carousel has focus)
		scope.on(root, 'keydown', function (e) {
			if (e.key === 'ArrowRight') { show(current + 1); stopAutoplay(); startAutoplay(); }
			else if (e.key === 'ArrowLeft') { show(current - 1); stopAutoplay(); startAutoplay(); }
		});

		// Pause autoplay when the tab is hidden — saves battery and
		// prevents a 30-slide jump-back when the user returns.
		scope.on(document, 'visibilitychange', function () {
			if (document.hidden) stopAutoplay();
			else startAutoplay();
		});

		root.setAttribute('data-lunara-carousel-ready', 'true');
        scope.cleanup(function () {
            stopAutoplay(); root.removeAttribute('data-lunara-carousel-ready');
            if (toggle && originalToggle) {
                toggle.innerHTML = originalToggle.html; toggle.hidden = originalToggle.hidden;
                if (originalToggle.label === null) toggle.removeAttribute('aria-label');
                else toggle.setAttribute('aria-label', originalToggle.label);
            }
        });
		startAutoplay();
	}

	function bootAll() {
		pageRoot.querySelectorAll('.lunara-carousel').forEach(init);
	}

	bootAll();

	// Hide any "X / Y" fraction counter overlay (preserved from original behavior).
	function hideCounters() {
		pageRoot.querySelectorAll('.lunara-carousel').forEach(function (root) {
			root.querySelectorAll(
				'.swiper-pagination-fraction,.splide__pagination__counter,' +
				'.slick-counter,.lunara-carousel-count,.lunara-slide-count,' +
				'[data-slide-count]'
			).forEach(function (el) { el.style.display = 'none'; });

			root.querySelectorAll('*').forEach(function (el) {
				if (el.children.length === 0 &&
					/^\d+\s*\/\s*\d+$/.test((el.textContent || '').trim())) {
					el.style.display = 'none';
				}
			});
		});
	}
	hideCounters();
})();

    }
    if (window.LunaraApp && window.LunaraApp.register) {
        window.LunaraApp.register('carousel', mount);
    } else if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { mount(); }, { once: true });
    } else { mount(); }
}());
