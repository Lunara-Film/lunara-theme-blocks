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
 * Lunara Lights Down — immersive screening mode for single reviews/journal.
 *
 * Two quiet moves once the reader is past the hero and into the text:
 * 1. The chrome (header/footer) dims to 35% so the page reads like a theater
 *    going dark — hovering the header brings it back instantly.
 * 2. The hero art is sampled (24x24 canvas average) into --lunara-ambient,
 *    which tints blockquote rules to the film's own palette. Palette is
 *    kept under reduced motion (it's color, not
 *    motion); the dim transition is dropped there by CSS.
 *
 * Enqueued only on is_singular(review|journal). Everything bails safely:
 * no IntersectionObserver, no article, cross-origin canvas — the page just
 * stays exactly as it was.
 *
 * @package Lunara_Film
 */
(function () {
	'use strict';

	if (!('IntersectionObserver' in window)) {
		return;
	}

	var body = document.body;
    var ambientBefore = document.documentElement.style.getPropertyValue('--lunara-ambient');
    scope.cleanup(function () {
        body.classList.remove('is-lights-down');
        if (ambientBefore) document.documentElement.style.setProperty('--lunara-ambient', ambientBefore);
        else document.documentElement.style.removeProperty('--lunara-ambient');
    });
	var article = pageRoot.querySelector('article') || pageRoot.querySelector('.entry-content');
	if (!article) {
		return;
	}

	// --- Lights down: dim the chrome while deep in the read -------------
	var hero = pageRoot.querySelector('.lunara-review-single-cinematic-hero')
		|| pageRoot.querySelector('.lunara-review-visual--hero')
		|| pageRoot.querySelector('.entry-header');
	if (hero) {
		var io = scope.observe(new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				// Lights go down only once the hero has fully scrolled above
				// the viewport; scrolling back up brings them right back.
				var above = !entry.isIntersecting && entry.boundingClientRect.bottom < 0;
				body.classList.toggle('is-lights-down', above);
			});
		}, { threshold: 0 }));
		io.observe(hero);
	}

	// --- Ambient accent sampled from the hero art ------------------------
	var img = pageRoot.querySelector('.lunara-review-single-cinematic-hero img')
		|| pageRoot.querySelector('.lunara-review-visual--hero img')
		|| article.querySelector('img');
	if (img) {
		var ambientSampleUrl = function (image) {
			var source = image.currentSrc || image.src;
			if (!source) {
				return '';
			}

			try {
				var url = new URL(source, document.baseURI);

				if (url.hostname.toLowerCase() === 'image.tmdb.org') {
					var photon = new URL('https://i0.wp.com/' + url.hostname + url.pathname);
					photon.searchParams.set('resize', '48,48');
					photon.searchParams.set('quality', '60');
					photon.searchParams.set('ssl', '1');
					return photon.toString();
				}

				if (url.origin === window.location.origin || /(^|\.)wp\.com$/i.test(url.hostname)) {
					return url.toString();
				}
			} catch (e) {
				return '';
			}

			return '';
		};
		var sampleUrl = ambientSampleUrl(img);
		if (!sampleUrl) {
			return;
		}

		// Re-fetch through crossOrigin=anonymous so the canvas isn't tainted —
		// Photon/i0.wp.com sends ACAO:*; unsupported external hosts are skipped.
		var sample = new Image();
		sample.crossOrigin = 'anonymous';
        scope.cleanup(function () { sample.removeAttribute('src'); });
		var applyAmbient = function () {
			try {
				var c = document.createElement('canvas');
				c.width = 24;
				c.height = 24;
				var g = c.getContext('2d', { willReadFrequently: true });
				g.drawImage(sample, 0, 0, 24, 24);
				var d = g.getImageData(0, 0, 24, 24).data;
				var r = 0, gr = 0, b = 0, n = 0;
				for (var i = 0; i < d.length; i += 4) {
					if (d[i + 3] < 200) {
						continue;
					}
					r += d[i];
					gr += d[i + 1];
					b += d[i + 2];
					n++;
				}
				if (!n) {
					return;
				}
				// Lift toward legible warmth so a murky frame can't produce mud.
				var lift = function (v) {
					return Math.min(255, Math.round((v / n) * 0.55 + 110));
				};
				document.documentElement.style.setProperty(
					'--lunara-ambient',
					'rgb(' + lift(r) + ',' + lift(gr) + ',' + lift(b) + ')'
				);
			} catch (e) {
				/* Cross-origin canvas taint — keep the gold default. */
			}
		};
		scope.on(sample, 'load', applyAmbient, { once: true });
		var armSample = function () {
			sample.src = sampleUrl;
		};
		if (img.complete && img.naturalWidth) {
			armSample();
		} else {
			scope.on(img, 'load', armSample, { once: true });
		}
	}
})();

    }
    if (window.LunaraApp && window.LunaraApp.register) {
        window.LunaraApp.register('immersive-read', mount);
    } else if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { mount(); }, { once: true });
    } else { mount(); }
}());
