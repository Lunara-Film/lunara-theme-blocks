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
(function () {
	'use strict';

	var selector = [
		'.lunara-journal-home-card',
		'.lunara-review-grid-card',
		'.lunara-oscar-pick-card',
		'.lunara-oscar-fact-card'
	].join(',');
	var cards = Array.prototype.slice.call(pageRoot.querySelectorAll(selector));

	cards.forEach(function (card) {
		card.classList.remove('is-cinematic-pending');
		card.classList.add('is-cinematic-visible');
	});
}());

    }
    if (window.LunaraApp && window.LunaraApp.register) {
        window.LunaraApp.register('cinematic-home', mount);
    } else if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { mount(); }, { once: true });
    } else { mount(); }
}());
