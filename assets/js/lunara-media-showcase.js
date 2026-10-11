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
 * Lunara Media Showcase — slider arrow driver.
 *
 * The gallery and carousel displays need no JS: the gallery is pure CSS grid,
 * and the carousel reuses lunara-carousel.js. This tiny driver only wires the
 * optional prev/next arrows on the slider display to a scroll-snap rail. Swipe,
 * trackpad, and touch scrolling all work natively without it.
 *
 * Contract:
 *   .lunara-media-slider[data-lunara-media-slider] — outer container
 *   .lunara-media-slider-track                     — the scroll-snap rail
 *   .lunara-media-arrow[data-dir="-1|1"]           — prev / next buttons
 */
( function () {
	'use strict';

	function init( root ) {
		if ( root.dataset.lunaraMediaSliderReady === '1' ) {
			return;
		}
		root.dataset.lunaraMediaSliderReady = '1';
        scope.cleanup(function () { delete root.dataset.lunaraMediaSliderReady; });

		var track = root.querySelector( '.lunara-media-slider-track' );
		if ( ! track ) {
			return;
		}

		var arrows = Array.prototype.slice.call( root.querySelectorAll( '.lunara-media-arrow' ) );

		function step() {
			var first = track.querySelector( '.lunara-media-slide' );
			var slideWidth = first ? first.getBoundingClientRect().width : track.clientWidth * 0.8;
			var styles = window.getComputedStyle( track );
			var gap = parseFloat( styles.columnGap || styles.gap || '0' ) || 0;
			return slideWidth + gap;
		}

		function updateArrows() {
			var maxScroll = track.scrollWidth - track.clientWidth - 1;
			arrows.forEach( function ( btn ) {
				var dir = parseInt( btn.getAttribute( 'data-dir' ), 10 );
				var atStart = track.scrollLeft <= 1;
				var atEnd = track.scrollLeft >= maxScroll;
				btn.disabled = ( dir < 0 && atStart ) || ( dir > 0 && atEnd );
			} );
		}

		arrows.forEach( function ( btn ) {
			scope.on(btn,  'click', function () {
				var dir = parseInt( btn.getAttribute( 'data-dir' ), 10 ) || 1;
				track.scrollBy( { left: dir * step(), behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' } );
			} );
		} );

		scope.on(track,  'scroll', function () {
			scope.frame( updateArrows );
		}, { passive: true } );
		scope.on(window,  'resize', updateArrows );
		updateArrows();
	}

	function bootAll() {
		pageRoot.querySelectorAll( '.lunara-media-slider[data-lunara-media-slider]' ).forEach( init );
	}

	bootAll();
} )();

    }
    if (window.LunaraApp && window.LunaraApp.register) {
        window.LunaraApp.register('media-showcase', mount);
    } else if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { mount(); }, { once: true });
    } else { mount(); }
}());
