/* Source entry: build with tests/tools/build-navigation.cjs. */
import Swup from 'swup';
import SwupHeadPlugin from '@swup/head-plugin';

// Put the head update inside the same fail-closed transaction as replacement.
// Swup intentionally logs (rather than rethrows) errors in ordinary before hooks.
class TransactionalHeadPlugin extends SwupHeadPlugin { mount() {} }

const MANIFEST = '#lunara-navigation-manifest';
const GLOBALS = new Set(['LUNARA_LIVE_SEARCH']);
const SHELL_LINKS = '[data-lunara-header] a[href], #header a[href], .lunara-offcanvas-nav a[href]';
const executable = node => !node.type || /^(?:text|application)\/(?:java|ecma)script$/i.test(node.type) || node.type === 'module';
const absolute = value => new URL(value, location.href).href;
const stateKey = state => `${state?.random || 'initial'}:${state?.lle?.url || state?.url || location.href}`;

function readManifest(doc) {
    const node = doc.querySelector(MANIFEST);
    if (!node) return null;
    try {
        const data = JSON.parse(node.textContent);
        return data.version === 1 && data.enabled === true && data.assets && typeof data.assets === 'object' ? data : null;
    } catch (_) { return null; }
}

function ignoredUrl(href, el) {
    let url;
    try { url = new URL(href, location.href); } catch (_) { return true; }
    if (url.origin !== location.origin || !/^https?:$/.test(url.protocol)) return true;
    if (el?.closest('[data-no-swup], [data-lle-nav], [data-lle-filters], [data-lle-search-form], [data-lunara-search-open]')) return true;
    if (el?.hasAttribute('download') || (el?.target && el.target !== '_self')) return true;
    if (url.hash && url.pathname === location.pathname && url.search === location.search) return true;
    if (/\/(?:wp-admin|wp-json|wp-login\.php)(?:\/|$)/i.test(url.pathname) || /\/(?:feed|embed)\/?$/i.test(url.pathname)) return true;
    if (/\.(?:pdf|xml|json|zip|mp[34]|webm|jpg|jpeg|png|gif|webp|avif|svg|woff2?|ttf)$/i.test(url.pathname)) return true;
    return [...url.searchParams.keys()].some(key => /^(?:_wpnonce|nonce|preview|preview_id|preview_nonce|customize_|elementor-preview|action|fragment)/i.test(key));
}

function ledgerOwnsHistory(event) {
    const root = document.querySelector('[data-lle-root]');
    if (!root || !event.state?.lle?.base || !event.state.lle.url) return false;
    try {
        return new URL(event.state.lle.base, location.href).pathname === new URL(root.dataset.lleBase, location.href).pathname
            && absolute(event.state.lle.url) === location.href;
    } catch (_) { return false; }
}

function boot() {
    const app = window.LunaraApp;
    const initial = readManifest(document);
    if (!app || !initial || document.querySelectorAll('#main').length !== 1 || window.LunaraNavigation) return;
    const allowed = initial.assets;
    const loaded = new Map();
    const loadedHandles = new Map();
    const positions = new Map();
    let activeKey = stateKey(history.state);
    let scrolling = false;

    document.querySelectorAll('script').forEach(script => {
        if (!executable(script)) return;
        if (script.src) {
            loaded.set(script.src, Promise.resolve());
            if (script.dataset.lunaraAsset) loadedHandles.set(script.dataset.lunaraAsset, script.src);
        }
    });

    const announce = document.createElement('div');
    announce.className = 'screen-reader-text';
    announce.setAttribute('role', 'status');
    announce.setAttribute('aria-live', 'polite');
    announce.setAttribute('aria-atomic', 'true');
    announce.dataset.lunaraNavigationStatus = '';
    document.body.appendChild(announce);

    function remember() { positions.set(activeKey, [window.scrollX, window.scrollY]); }
    window.addEventListener('scroll', () => {
        if (scrolling) return;
        scrolling = true;
        requestAnimationFrame(() => { scrolling = false; remember(); });
    }, { passive: true });
    window.addEventListener('popstate', remember, { capture: true });

    function audit(doc) {
        const manifest = readManifest(doc);
        if (!manifest || doc.querySelectorAll('#main').length !== 1 || doc.body.classList.contains('logged-in')) throw new Error('Destination does not support public navigation.');
        const assets = [];
        for (const script of doc.querySelectorAll('script')) {
            if (!executable(script)) continue;
            if (!script.src) {
                const code = script.textContent.trim();
                const localizedSearch = /^var\s+LUNARA_LIVE_SEARCH\s*=\s*\{[\s\S]*\}\s*;\s*$/.test(code) && manifest.config?.LUNARA_LIVE_SEARCH;
                if (code && !localizedSearch) throw new Error('Destination requires an unaudited inline initializer.');
                continue;
            }
            const src = absolute(script.getAttribute('src'));
            const handle = script.dataset.lunaraAsset;
            if (!handle || !allowed[handle] || manifest.assets[handle] !== allowed[handle]) {
                throw new Error('Destination requires an unaudited script.');
            }
            if (new URL(src).origin !== location.origin) throw new Error('Destination script is outside the audited origin.');
            if (loadedHandles.has(handle) && loadedHandles.get(handle) !== src) throw new Error('Application asset version changed; a full document load is required.');
            const component = initial.components?.[handle];
            if (allowed[handle] === 'page' && (!component || manifest.components?.[handle] !== component)) throw new Error('Destination component is not audited.');
            assets.push({ handle, src, node: script, component });
        }
        return { manifest, assets };
    }

    function loadScript(asset) {
        if (loaded.has(asset.src)) return loaded.get(asset.src);
        const promise = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = asset.src;
            script.async = false;
            script.dataset.lunaraAsset = asset.handle;
            script.dataset.lunaraScope = allowed[asset.handle];
            for (const name of ['type', 'integrity', 'crossorigin', 'referrerpolicy']) {
                if (asset.node.hasAttribute(name)) script.setAttribute(name, asset.node.getAttribute(name));
            }
            const timer = setTimeout(() => { script.remove(); reject(new Error(`Navigation asset timed out: ${asset.handle}`)); }, 5000);
            script.onload = () => { clearTimeout(timer); loadedHandles.set(asset.handle, asset.src); resolve(); };
            script.onerror = () => { clearTimeout(timer); script.remove(); reject(new Error(`Navigation asset failed: ${asset.handle}`)); };
            document.head.appendChild(script);
        });
        loaded.set(asset.src, promise);
        return promise;
    }

    function syncNavigation(doc) {
        const incoming = [...doc.querySelectorAll(SHELL_LINKS)];
        document.querySelectorAll(SHELL_LINKS).forEach(link => {
            const match = incoming.find(candidate => candidate.getAttribute('href') === link.getAttribute('href'));
            const current = match?.getAttribute('aria-current');
            if (current) link.setAttribute('aria-current', current); else link.removeAttribute('aria-current');
            const oldItem = link.closest('li'), newItem = match?.closest('li');
            if (oldItem) ['current-menu-item', 'current-menu-parent', 'current-menu-ancestor', 'current_page_item', 'current_page_parent'].forEach(name => oldItem.classList.toggle(name, !!newItem?.classList.contains(name)));
        });
    }

    function moveRouteStyles(doc) {
        // WordPress can print late route styles in wp_footer. Include those in
        // the head transaction, preserving their order after ordinary head CSS.
        doc.body.querySelectorAll('style, link[rel="stylesheet"]').forEach(node => {
            if (!node.closest('#main')) doc.head.appendChild(node);
        });
        const manifest = doc.querySelector(MANIFEST);
        if (manifest) doc.head.appendChild(manifest);
        // Parsed scripts remain inert, and only the audited loader above may
        // execute application code. Metadata JSON-LD and typed JSON stay intact.
        doc.querySelectorAll('script').forEach(node => { if (executable(node)) node.remove(); });
    }

    const head = new TransactionalHeadPlugin({
        awaitAssets: true,
        timeout: 5000,
        persistTags: node => node.tagName === 'SCRIPT' && executable(node)
    });
    const swup = new Swup({
        containers: ['#main'],
        animationSelector: false,
        animateHistoryBrowsing: false,
        native: false,
        cache: false,
        timeout: 8000,
        linkSelector: 'a[href]',
        ignoreVisit: (url, { el } = {}) => ignoredUrl(url, el),
        skipPopStateHandling: event => ledgerOwnsHistory(event) || event.state?.source !== 'swup',
        plugins: [head]
    });
    activeKey = stateKey(history.state);
    remember();
    history.scrollRestoration = 'manual';

    swup.hooks.on('visit:start', visit => {
        remember();
        visit.animation.animate = false; // No second animation system over the native document fallback.
        visit.meta.lunaraScroll = visit.history.popstate ? positions.get(stateKey(visit.trigger.event?.state)) : null;
        announce.textContent = '';
        document.querySelector('#main')?.setAttribute('aria-busy', 'true');
        window.dispatchEvent(new CustomEvent('lunara:navigation:start', { detail: { url: visit.to.url, root: document.querySelector('#main') } }));
    });

    swup.hooks.replace('fetch:request', async (_visit, { url, options }) => {
        const response = await fetch(url, options);
        if ((!response.ok && response.status !== 404) || new URL(response.url).origin !== location.origin || !/^(?:text\/html|application\/xhtml\+xml)(?:;|$)/i.test(response.headers.get('content-type') || '')) {
            throw new Error('Destination requires an ordinary document navigation.');
        }
        return response;
    });
    swup.hooks.replace('content:replace', async (visit, args, replace) => {
        const doc = visit.to.document;
        const result = audit(doc);
        visit.meta.lunaraDocument = doc;
        visit.meta.lunaraManifest = result.manifest;
        await app.destroy('navigation');
        for (const name of GLOBALS) {
            if (Object.hasOwn(result.manifest.config || {}, name)) window[name] = result.manifest.config[name];
        }
        for (const asset of result.assets) {
            await loadScript(asset);
            if (asset.component && !app.has(asset.component)) throw new Error('Destination component did not register its lifecycle.');
        }
        moveRouteStyles(doc);
        // Once moved into the incoming head, the previous footer copies must
        // not linger and override a later route's CSS or manifest.
        visit.meta.lunaraOldStyles = [...document.body.querySelectorAll('style, link[rel="stylesheet"], #lunara-navigation-manifest')].filter(node => !node.closest('#main'));
        await head.updateHead(visit, args);
        for (const link of document.head.querySelectorAll('link[rel="stylesheet"][href]')) {
            if (!link.sheet && !link.disabled) throw new Error('Destination stylesheet did not become available.');
        }
        await replace(visit, args);
        document.body.className = doc.body.className;
        for (const attribute of [...document.body.attributes]) {
            if (attribute.name.startsWith('data-')) document.body.removeAttribute(attribute.name);
        }
        for (const attribute of [...doc.body.attributes]) {
            if (attribute.name.startsWith('data-')) document.body.setAttribute(attribute.name, attribute.value);
        }
        for (const node of visit.meta.lunaraOldStyles || []) node.remove();
        syncNavigation(doc);
        if (!document.querySelector('[data-lle-root]') && history.state?.lle) {
            const next = { ...history.state }; delete next.lle; history.replaceState(next, '', location.href);
        }
        activeKey = stateKey(history.state);
        return true;
    });

    swup.hooks.replace('content:scroll', visit => {
        const saved = visit.meta.lunaraScroll;
        if (saved) window.scrollTo({ left: saved[0], top: saved[1], behavior: 'instant' });
        else if (visit.to.hash) {
            const anchor = swup.getAnchorElement(visit.to.hash);
            if (anchor) anchor.scrollIntoView({ behavior: 'instant' });
            else window.scrollTo({ left: 0, top: 0, behavior: 'instant' });
        } else window.scrollTo({ left: 0, top: 0, behavior: 'instant' });
    });

    swup.hooks.on('page:view', async visit => {
        const main = document.querySelector('#main');
        await app.mount(main, { url: location.href, title: document.title, reason: visit.history.popstate ? 'history' : 'navigation', initial: false, visitId: visit.id });
        main.removeAttribute('aria-busy');
        const target = (visit.to.hash && swup.getAnchorElement(visit.to.hash)) || main.querySelector('h1') || main;
        const temporary = !target.hasAttribute('tabindex');
        if (temporary) target.setAttribute('tabindex', '-1');
        target.focus({ preventScroll: true });
        if (temporary) target.addEventListener('blur', () => target.removeAttribute('tabindex'), { once: true });
        announce.textContent = document.title;
        remember();
    });

    swup.hooks.on('visit:abort', () => document.querySelector('#main')?.removeAttribute('aria-busy'));
    swup.hooks.before('visit:fail', visit => {
        document.querySelector('#main')?.removeAttribute('aria-busy');
        window.dispatchEvent(new CustomEvent('lunara:navigation:fallback', { detail: { url: visit.to.url } }));
        // Swup's default failure handler restores a valid history entry and
        // performs ordinary navigation. No hidden/partly mounted page remains.
    });
    window.LunaraNavigation = { swup, ignoredUrl, ledgerOwnsHistory };
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true });
else boot();
