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
 * Generated from the verified inline runtime during Phase 1C.
 * Keep behavior route-gated and free of render-blocking dependencies.
 */

(function () {
        const motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
        pageRoot.querySelectorAll('[data-lunara-carousel]').forEach(function(section) {
            let reduceMotion = motionQuery.matches;
            const track = section.querySelector('[data-lunara-carousel-track]');
            const prev = section.querySelector('[data-lunara-carousel-prev]');
            const next = section.querySelector('[data-lunara-carousel-next]');
            const dots = Array.from(section.querySelectorAll('[data-lunara-carousel-dot]'));
            const toggle = section.querySelector('[data-lunara-carousel-toggle]');
            const carouselLabel = section.getAttribute('data-lunara-carousel-label') || 'Oscar Picks';
            if (!track) return;
            if (toggle && reduceMotion) {
                toggle.disabled = true;
                toggle.setAttribute('aria-disabled', 'true');
                toggle.setAttribute('aria-label', 'Autoplay disabled for reduced motion');
            }
            function amount() {
                const card = track.children[0];
                const styles = window.getComputedStyle(track);
                const gap = parseFloat(styles.columnGap || styles.gap) || 0;
                return card ? card.offsetWidth + gap : 360;
            }
            function getIndexFromOffset(scrollLeft) {
                const step = amount();
                if (!step) {
                    return 0;
                }
                const total = track.children.length;
                return Math.min(total - 1, Math.max(0, Math.round(scrollLeft / step)));
            }
            function syncDots() {
                if (!dots.length) {
                    return;
                }
                const activeIndex = getIndexFromOffset(track.scrollLeft);
                dots.forEach(function(dot, index) {
                    const active = index === activeIndex;
                    dot.classList.toggle('active', active);
                    dot.setAttribute('aria-selected', active ? 'true' : 'false');
                });
            }
            function scrollToIndex(index) {
                const target = Number.isInteger(index) ? index : 0;
                const cards = track.children;
                if (!cards.length) {
                    return;
                }
                const clampedIndex = Math.min(cards.length - 1, Math.max(0, target));
                const card = cards[clampedIndex];
                if (!card || !card.scrollIntoView) {
                    return;
                }
                const trackRect = track.getBoundingClientRect();
                const cardRect = card.getBoundingClientRect();
                const padding = parseFloat(window.getComputedStyle(track).scrollPaddingLeft) || 0;
                const maxScroll = Math.max(0, track.scrollWidth - track.clientWidth);
                const targetLeft = track.scrollLeft + cardRect.left - trackRect.left - padding;
                track.scrollTo({
                    left: Math.min(maxScroll, Math.max(0, targetLeft)),
                    behavior: reduceMotion ? 'auto' : 'smooth'
                });
            }
            function step(direction) {
                const distance = amount() * direction;
                const maxScroll = Math.max(0, track.scrollWidth - track.clientWidth);
                const behavior = reduceMotion ? 'auto' : 'smooth';
                // Reach the final card before wrapping on the following advance.
                if (direction > 0 && track.scrollLeft >= maxScroll - 6) {
                    track.scrollTo({ left: 0, behavior: behavior });
                    return;
                }
                if (direction < 0 && track.scrollLeft <= 6) {
                    track.scrollTo({ left: maxScroll, behavior: behavior });
                    return;
                }
                track.scrollBy({ left: distance, behavior: behavior });
            }
            if (prev) {
                scope.on(prev, 'click', function () {
                    step(-1);
                });
            }
            if (next) {
                scope.on(next, 'click', function () {
                    step(1);
                });
            }
            if (dots.length) {
                dots.forEach(function(dot, index) {
                    scope.on(dot, 'click', function() {
                        scrollToIndex(index);
                    });
                    scope.on(dot, 'focus', function() {
                        const strip = dot.parentElement;
                        if (!strip || strip.scrollWidth <= strip.clientWidth) {
                            return;
                        }
                        // Native focus may leave a partly visible button clipped.
                        // Reveal its full target and ring in this strip only.
                        const stripRect = strip.getBoundingClientRect();
                        const dotRect = dot.getBoundingClientRect();
                        const padding = parseFloat(window.getComputedStyle(strip).scrollPaddingLeft) || 0;
                        const left = dotRect.left - stripRect.left - padding;
                        const right = dotRect.right - stripRect.right + padding;
                        if (left < 0 || right > 0) {
                            strip.scrollBy({ left: left < 0 ? left : right, behavior: 'auto' });
                        }
                    });
                });
            }

            scope.on(section, 'keydown', function(event) {
                if ('ArrowLeft' === event.key) {
                    event.preventDefault();
                    step(-1);
                } else if ('ArrowRight' === event.key) {
                    event.preventDefault();
                    step(1);
                }
            });

            let syncRaf = null;
            scope.on(track, 'scroll', function () {
                if (!dots.length) {
                    return;
                }
                if (syncRaf) {
                    window.cancelAnimationFrame(syncRaf);
                }
                syncRaf = scope.frame(function () {
                    syncDots();
                    syncRaf = null;
                });
            }, { passive: true });

            const autoplay = parseInt(section.getAttribute('data-lunara-carousel-autoplay') || '0', 10);
            const allowMobileAutoplay = !!toggle;
            let timer = null;
            let userPaused = false;
            let pointerHover = false;
            let focusWithin = false;
            let touchActive = false;

            function syncToggle() {
                if (!toggle) {
                    return;
                }
                const paused = userPaused || reduceMotion || autoplay <= 0;
                toggle.textContent = paused ? 'Play' : 'Pause';
                toggle.setAttribute('aria-label', reduceMotion ? 'Autoplay disabled for reduced motion' : (paused ? 'Play ' + carouselLabel + ' rotation' : 'Pause ' + carouselLabel + ' rotation'));
                toggle.setAttribute('aria-pressed', userPaused ? 'true' : 'false');
                toggle.classList.toggle('is-paused', paused);
                toggle.disabled = reduceMotion || autoplay <= 0 || track.children.length < 2;
                if (toggle.disabled) {
                    toggle.setAttribute('aria-disabled', 'true');
                    if (!reduceMotion) toggle.setAttribute('aria-label', 'Automatic rotation is turned off');
                } else {
                    toggle.removeAttribute('aria-disabled');
                }
            }

            function stop() {
                if (timer) {
                    window.clearInterval(timer);
                    timer = null;
                }
            }

            function start(force) {
                stop();
                if (document.hidden || touchActive || userPaused || reduceMotion || autoplay <= 0 || track.children.length < 2 || (!allowMobileAutoplay && window.innerWidth <= 900) || (!force && (pointerHover || focusWithin))) {
                    syncToggle();
                    return;
                }
                timer = scope.interval(function () {
                    step(1);
                }, autoplay);
                syncToggle();
            }

            function resumeWhenAvailable() {
                if (document.hidden || touchActive || pointerHover || focusWithin || userPaused) {
                    stop();
                    syncToggle();
                    return;
                }
                start();
            }

            if (toggle) {
                scope.on(toggle, 'click', function () {
                    userPaused = !userPaused;
                    if (userPaused) {
                        stop();
                    } else {
                        start(true);
                    }
                    syncToggle();
                });
            }

            // Pause on real mouse hover and while keyboard focus is inside the
            // section. Touch users can still swipe and autoplay resumes after
            // the gesture ends. A user pause always wins over these temporary
            // interaction pauses.
            scope.on(section, 'pointerenter', function (event) {
                if (event.pointerType === 'mouse') {
                    pointerHover = true;
                    stop();
                }
            });
            scope.on(section, 'pointerleave', function (event) {
                if (event.pointerType === 'mouse') {
                    pointerHover = false;
                    resumeWhenAvailable();
                }
            });
            scope.on(section, 'focusin', function () {
                focusWithin = true;
                stop();
            });
            scope.on(section, 'focusout', function () {
                scope.timeout(function () {
                    if (!section.contains(document.activeElement)) {
                        focusWithin = false;
                        resumeWhenAvailable();
                    }
                }, 0);
            });
            scope.on(track, 'touchstart', function () {
                touchActive = true;
                stop();
            }, { passive: true });
            function finishTouch() {
                touchActive = false;
                resumeWhenAvailable();
            }
            scope.on(track, 'touchend', finishTouch, { passive: true });
            scope.on(track, 'touchcancel', finishTouch, { passive: true });
            scope.on(document, 'visibilitychange', function () {
                if (document.hidden) {
                    stop();
                } else {
                    resumeWhenAvailable();
                }
            });
            scope.on(motionQuery, 'change', function (event) {
                reduceMotion = event.matches;
                stop();
                syncToggle();
                resumeWhenAvailable();
            });
            syncToggle();
            start();

            // The first dot is already marked active in server-rendered HTML.
            // Defer geometry reads until the rail actually scrolls or a reader
            // uses a control; this keeps below-fold carousels out of first paint.
        });
    }());

    }
    if (window.LunaraApp && window.LunaraApp.register) {
        window.LunaraApp.register('scroll-carousel', mount);
    } else if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { mount(); }, { once: true });
    } else { mount(); }
}());
