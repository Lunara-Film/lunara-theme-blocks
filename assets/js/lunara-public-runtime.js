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
            var observer = null;
            var ownedImages = new Set();
            scope.cleanup(function () {
                ownedImages.forEach(function (img) {
                    // Loaded pixels stay loaded; an interrupted lazy observation can mount again.
                    if (img.dataset.lunaraHydratorState !== 'loaded') delete img.dataset.lunaraHydratorState;
                });
                ownedImages.clear();
            });
            var imageSelector = [
                '.lunara-review-grid-poster',
                '.lunara-review-feature-image',
                '.lunara-poster-card-image',
                '.lunara-journal-home-card-image',
                '.lunara-dispatch-archive-thumb',
                '.lunara-dispatch-lead-image',
                '.lunara-oscar-pick-card-image',
                '.lunara-oscar-fact-card-poster-image',
                '.aat-entity-poster',
                '.aat-filmography-poster',
                '.aat-winner-circle-photo',
                '.aat-winner-circle-media img',
                '.aat-hub-spotlight-media img',
                '.aat-related-review-image'
            ].join(',');

            function sanitizeSrcset(srcset) {
                srcset = (srcset || '').trim();
                if (!srcset) return '';

                return srcset.split(/,\s*(?=(?:https?:)?\/\/|\/)/).map(function (candidate) {
                    candidate = (candidate || '').trim();
                    if (!candidate) return '';

                    var decoded = candidate.replace(/&amp;/g, '&');
                    if (/(?:[?&;](?:resize|fit)=0(?:%2c|,)nan)/i.test(decoded) || /(?:[?&;](?:w|h)=0(?:&|$))/i.test(decoded)) {
                        return '';
                    }

                    return (/\s+\d+w$/.test(candidate) || /\s+\d+(?:\.\d+)?x$/.test(candidate)) ? candidate : '';
                }).filter(Boolean).join(', ');
            }

            function sanitizeImageSrcset(node) {
                if (!node || !node.getAttribute || !node.setAttribute) return;

                var currentSrcset = node.getAttribute('srcset') || '';
                if (!currentSrcset) return;

                var sanitizedSrcset = sanitizeSrcset(currentSrcset);
                if (sanitizedSrcset && sanitizedSrcset !== currentSrcset) {
                    node.setAttribute('srcset', sanitizedSrcset);
                } else if (!sanitizedSrcset) {
                    node.removeAttribute('srcset');
                    node.removeAttribute('sizes');
                }
            }

            function hydrateImage(img) {
                if (!img) return;
                img.dataset.lunaraHydratorState = 'hydrated';
                sanitizeImageSrcset(img);

                var dataSrcset = img.getAttribute('data-srcset') || img.getAttribute('data-lazy-srcset') || '';

                if (dataSrcset && !img.getAttribute('srcset')) {
                    var sanitizedSrcset = sanitizeSrcset(dataSrcset);
                    if (sanitizedSrcset) {
                        img.setAttribute('srcset', sanitizedSrcset);
                    } else {
                        img.removeAttribute('data-srcset');
                        img.removeAttribute('data-lazy-srcset');
                    }
                }

                if (img.complete && img.naturalWidth > 1) {
                    img.classList.add('lunara-img-loaded');
                    img.dataset.lunaraHydratorState = 'loaded';
                } else {
                    var markLoaded = function () {
                        img.classList.add('lunara-img-loaded');
                        img.dataset.lunaraHydratorState = 'loaded';
                    };
                    scope.on(img, 'load', markLoaded, { once: true });
                    scope.on(img, 'error', markLoaded, { once: true });
                    scope.timeout(markLoaded, 1800);
                }
            }

            function observeImage(img) {
                if (!img || img.dataset.lunaraHydratorState) return;
                ownedImages.add(img);

                var loading = (img.getAttribute('loading') || '').toLowerCase();
                if (loading === 'eager' || img.getAttribute('fetchpriority') === 'high') {
                    hydrateImage(img);
                    return;
                }

                if (!('IntersectionObserver' in window)) {
                    hydrateImage(img);
                    return;
                }

                if (!observer) {
                    observer = scope.observe(new IntersectionObserver(function (entries) {
                        entries.forEach(function (entry) {
                            if (!entry.isIntersecting) return;
                            hydrateImage(entry.target);
                            observer.unobserve(entry.target);
                        });
                    }, { rootMargin: '640px 0px' }));
                }

                img.dataset.lunaraHydratorState = 'observed';
                observer.observe(img);
            }

            function scanImages(root) {
                root = root || document;
                if (root.matches && root.matches(imageSelector)) {
                    observeImage(root);
                }
                if (root.querySelectorAll) {
                    root.querySelectorAll(imageSelector).forEach(observeImage);
                }
            }

            function bootImageRuntime() {
                scanImages(pageRoot);
                if (!window.MutationObserver) return;

                scope.observe(new MutationObserver(function (mutations) {
                    mutations.forEach(function (mutation) {
                        if (mutation.type === 'attributes') {
                            sanitizeImageSrcset(mutation.target);
                            if (mutation.target.matches && mutation.target.matches(imageSelector)) {
                                observeImage(mutation.target);
                            }
                            return;
                        }

                        mutation.addedNodes.forEach(function (node) {
                            if (node.nodeType === 1) {
                                scanImages(node);
                            }
                        });
                    });
                 })).observe(pageRoot, {
                    attributes: true,
                    attributeFilter: ['srcset', 'data-srcset', 'data-lazy-srcset'],
                    childList: true,
                    subtree: true
                });
            }

            bootImageRuntime();
        }());

(function(){
        if(!('startViewTransition' in document))return;
        if(window.matchMedia('(prefers-reduced-motion: reduce)').matches)return;
        var CARDS='.lunara-review-grid-card,.lunara-poster-card,.lunara-oscar-pick-card,.lunara-journal-home-card,.lunara-cinematic-hero-link';
        scope.on(document, 'click',function(e){
            if(e.defaultPrevented||e.button!==0||e.metaKey||e.ctrlKey||e.shiftKey||e.altKey)return;
            var card=e.target.closest?e.target.closest(CARDS):null;if(!card)return;
            var a=card.matches('a')?card:card.querySelector('a[href]');
            if(!a||!a.href)return;
            if(a.target&&a.target!=='_self')return;
            if(a.origin!==location.origin)return;
            var img=card.querySelector('img');if(!img)return;
            var r=img.getBoundingClientRect();
            if(r.width<1||r.bottom<0||r.top>window.innerHeight)return;
            /* Demote the current page's own hero name first — two elements
               sharing lunara-screen would abort the whole transition. */
            var hero=pageRoot.querySelector('.lunara-review-single-cinematic-hero .lunara-review-visual--hero');
            if(hero)hero.style.viewTransitionName='none';
            img.style.viewTransitionName='lunara-screen';
            img.setAttribute('data-lunara-vt','1');
        },true);
        scope.on(window, 'pageshow',function(e){
            if(!e.persisted)return;
            pageRoot.querySelectorAll('[data-lunara-vt]').forEach(function(el){el.style.viewTransitionName='';el.removeAttribute('data-lunara-vt');});
            var hero=pageRoot.querySelector('.lunara-review-single-cinematic-hero .lunara-review-visual--hero');
            if(hero)hero.style.viewTransitionName='';
        });
    })();

    }
    if (window.LunaraApp && window.LunaraApp.register) {
        window.LunaraApp.register('public-runtime', mount);
    } else if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { mount(); }, { once: true });
    } else { mount(); }
}());
