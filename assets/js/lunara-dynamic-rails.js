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
 * Lunara Dynamic Rails.
 *
 * Theme-owned rail behavior for editorial lanes that need movement without
 * importing third-party carousel plugins.
 */
(function () {
	'use strict';

	var SWIPE_THRESHOLD = 42;
	var VERTICAL_LIMIT = 64;
	var motionQuery = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)');

	function clamp(index, length) {
		if (!length) return 0;
		return (index + length) % length;
	}

	function initRail(rail) {
		var track = rail.querySelector('[data-lunara-dynamic-rail-track]');
		if (!track) return;

		var items = Array.prototype.slice.call(
			track.querySelectorAll('[data-lunara-dynamic-rail-item]')
		);
		if (items.length < 2) return;

		var previous = rail.querySelector('[data-lunara-dynamic-rail-prev]');
		var next = rail.querySelector('[data-lunara-dynamic-rail-next]');
		var toggle = rail.querySelector('[data-lunara-dynamic-rail-toggle]');
		var dots = Array.prototype.slice.call(
			rail.querySelectorAll('[data-lunara-dynamic-rail-dot]')
		);
		var autoplayMs = parseInt(rail.getAttribute('data-lunara-dynamic-rail-autoplay') || '0', 10);
		var sharedPace = document.querySelector('meta[name="lunara-slider-interval"]');
		if (sharedPace && autoplayMs > 0) { autoplayMs = Math.max(8000, Math.min(30000, Number(sharedPace.content) || 12000)); }
		var current = 0;
		var reduceMotion = !!(motionQuery && motionQuery.matches);
		var userPaused = false;
		var pointerHover = false;
		var focusWithin = false;
		var timer = null;
		var touchStartX = 0;
		var touchStartY = 0;
		var touchActive = false;

		if (!track.hasAttribute('tabindex')) {
			track.setAttribute('tabindex', '0');
		}
		if (!rail.hasAttribute('aria-roledescription')) {
			rail.setAttribute('aria-roledescription', 'carousel');
		}

		function updateState(index) {
			current = clamp(index, items.length);
			items.forEach(function (item, itemIndex) {
				item.classList.toggle('is-active', itemIndex === current);
			});
			dots.forEach(function (dot, dotIndex) {
				var active = dotIndex === current;
				dot.classList.toggle('is-active', active);
				if (active) {
					dot.setAttribute('aria-current', 'true');
				} else {
					dot.removeAttribute('aria-current');
				}
			});
		}

		function scrollToIndex(index, behavior) {
			var nextIndex = clamp(index, items.length);
			var item = items[nextIndex];
			if (!item) return;

			updateState(nextIndex);
			track.scrollTo({
				left: item.offsetLeft - track.offsetLeft,
				behavior: reduceMotion ? 'auto' : (behavior || 'smooth')
			});
		}

		function closestIndex() {
			var left = track.scrollLeft;
			var bestIndex = 0;
			var bestDistance = Number.MAX_VALUE;

			items.forEach(function (item, index) {
				var distance = Math.abs((item.offsetLeft - track.offsetLeft) - left);
				if (distance < bestDistance) {
					bestDistance = distance;
					bestIndex = index;
				}
			});

			return bestIndex;
		}

		function stopAutoplay() {
			if (timer) {
				window.clearInterval(timer);
				timer = null;
			}
		}

		function startAutoplay(force) {
			if (document.hidden || touchActive || reduceMotion || autoplayMs <= 0 || timer || userPaused || (!force && (pointerHover || focusWithin))) {
				syncToggle();
				return;
			}
			timer = scope.interval(function () {
				if (!document.hidden && !touchActive && !reduceMotion && !userPaused) {
					scrollToIndex(current + 1, 'smooth');
				}
			}, autoplayMs);
			syncToggle();
		}

		function syncToggle() {
			if (!toggle) return;

			var isPaused = userPaused || reduceMotion || autoplayMs <= 0;
			toggle.textContent = isPaused ? 'Play' : 'Pause';
			toggle.setAttribute('aria-label', reduceMotion
				? 'Autoplay disabled for reduced motion'
				: (isPaused ? 'Play companion review rotation' : 'Pause companion review rotation'));
			toggle.setAttribute('aria-pressed', userPaused ? 'true' : 'false');
			toggle.classList.toggle('is-paused', isPaused);
			if (autoplayMs <= 0 && !reduceMotion) {
				toggle.setAttribute('aria-label', 'Automatic rotation is turned off');
			}
			if (reduceMotion || autoplayMs <= 0) {
				toggle.disabled = true;
				toggle.setAttribute('aria-disabled', 'true');
			} else {
				toggle.disabled = false;
				toggle.removeAttribute('aria-disabled');
			}
		}

		function resumeAutoplay(force) {
			if (force) {
				startAutoplay(true);
				return;
			}
			if (document.hidden || touchActive || userPaused || pointerHover || focusWithin) {
				stopAutoplay();
				syncToggle();
				return;
			}
			startAutoplay();
		}

		if (previous) {
			scope.on(previous, 'click', function () {
				scrollToIndex(current - 1, 'smooth');
				stopAutoplay();
				resumeAutoplay();
			});
		}

		if (next) {
			scope.on(next, 'click', function () {
				scrollToIndex(current + 1, 'smooth');
				stopAutoplay();
				resumeAutoplay();
			});
		}

		dots.forEach(function (dot) {
			scope.on(dot, 'click', function () {
				var index = parseInt(dot.getAttribute('data-lunara-dynamic-rail-index') || '0', 10);
				scrollToIndex(index, 'smooth');
				stopAutoplay();
				resumeAutoplay();
			});
		});

		if (toggle) {
			scope.on(toggle, 'click', function () {
				userPaused = !userPaused;
				if (userPaused) {
					stopAutoplay();
				} else {
					resumeAutoplay(true);
				}
				syncToggle();
			});
		}

		scope.on(rail, 'pointerenter', function (event) {
			if (event.pointerType === 'mouse') {
				pointerHover = true;
				stopAutoplay();
				syncToggle();
			}
		});
		scope.on(rail, 'pointerleave', function (event) {
			if (event.pointerType === 'mouse') {
				pointerHover = false;
				resumeAutoplay();
			}
		});
		scope.on(rail, 'focusin', function () {
			focusWithin = true;
			stopAutoplay();
			syncToggle();
		});
		scope.on(rail, 'focusout', function () {
			scope.timeout(function () {
				if (!rail.contains(document.activeElement)) {
					focusWithin = false;
					resumeAutoplay();
				}
			}, 0);
		});

		scope.on(track, 'keydown', function (event) {
			if (event.key === 'ArrowRight') {
				event.preventDefault();
				scrollToIndex(current + 1, 'smooth');
			} else if (event.key === 'ArrowLeft') {
				event.preventDefault();
				scrollToIndex(current - 1, 'smooth');
			}
		});

		scope.on(track, 'touchstart', function (event) {
			if (!event.touches || !event.touches[0]) return;
			touchStartX = event.touches[0].clientX;
			touchStartY = event.touches[0].clientY;
			touchActive = true;
			stopAutoplay();
			syncToggle();
		}, { passive: true });

		scope.on(track, 'touchend', function (event) {
			if (!touchActive) {
				resumeAutoplay();
				return;
			}
			touchActive = false;

			var touch = event.changedTouches && event.changedTouches[0];
			if (!touch) {
				resumeAutoplay();
				return;
			}

			var dx = touch.clientX - touchStartX;
			var dy = touch.clientY - touchStartY;

			if (Math.abs(dy) > VERTICAL_LIMIT || Math.abs(dx) < SWIPE_THRESHOLD) {
				updateState(closestIndex());
				resumeAutoplay();
				return;
			}

			scrollToIndex(current + (dx < 0 ? 1 : -1), 'smooth');
			stopAutoplay();
			startAutoplay();
		});

		scope.on(track, 'touchcancel', function () {
			touchActive = false;
			resumeAutoplay();
		});

		scope.on(track, 'scroll', function () {
			scope.frame(function () {
				updateState(closestIndex());
			});
		}, { passive: true });

		scope.on(document, 'visibilitychange', function () {
			if (document.hidden) {
				stopAutoplay();
			} else {
				resumeAutoplay();
			}
		});

		if (motionQuery && motionQuery.addEventListener) {
			scope.on(motionQuery, 'change', function (event) {
				reduceMotion = event.matches;
				stopAutoplay();
				syncToggle();
				resumeAutoplay();
			});
		}

		updateState(0);
		syncToggle();
		startAutoplay();
	}

	function boot() {
		pageRoot.querySelectorAll('[data-lunara-dynamic-rail]').forEach(initRail);
	}

	boot();
})();

    }
    if (window.LunaraApp && window.LunaraApp.register) {
        window.LunaraApp.register('dynamic-rails', mount);
    } else if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { mount(); }, { once: true });
    } else { mount(); }
}());
