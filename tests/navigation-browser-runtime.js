'use strict';
const fs = require('node:fs');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const { chromium } = require('playwright-core');
const root = path.resolve(__dirname, '..');
const php = spawnSync('php', [path.join(__dirname, 'navigation-runtime.php'), '--manifest'], { encoding: 'utf8' });
if (php.status) throw Error(php.stderr || php.stdout);
const manifest = JSON.parse(php.stdout);
const executablePath = process.env.LUNARA_BROWSER_EXECUTABLE || ['C:/Program Files/Google/Chrome/Application/chrome.exe', 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', '/usr/bin/chromium', '/usr/bin/google-chrome'].find(fs.existsSync);
let checks = 0;
function assert(value, message) { checks++; if (!value) throw Error(message); }
const asset = (handle, src, defer = '') => `<script data-lunara-asset="${handle}" src="${src}" ${defer}></script>`;
function html(route) {
    const late = route === '/second/' || route === '/third/';
    const title = route.slice(1).replaceAll('/', '') || 'home';
    const bad = route.startsWith('/fallback-');
    const routeManifest = { ...manifest, config: { LUNARA_LIVE_SEARCH: { endpoint: '/api' + route } } };
    const extra = route === '/fallback-inline/' ? '<script>window.unaudited=true;</script>' : route === '/fallback-script/' ? '<script src="/unregistered.js"></script>' : route === '/fallback-missing/' ? asset('lunara-home-runtime', '/missing.js') : '';
    return `<!doctype html><html lang="${late ? 'fr' : 'en'}"><head><meta charset="utf-8"><title>${title}</title><meta name="description" content="${title}"><link rel="canonical" href="https://navigation.test${route}"><link rel="stylesheet" href="/base.css">${late ? '<link rel="stylesheet" href="/second.css">' : ''}${asset('lunara-page-lifecycle','/assets/js/lunara-page-lifecycle.js')}${asset('lunara-public-runtime','/component.js','defer')}${asset('lunara-navigation','/assets/js/lunara-navigation.bundle.js','defer')}${late ? asset('lunara-home-runtime','/late-component.js','defer') : ''}</head><body class="route-${title}" data-route="${title}"><header data-lunara-header><a id="nav-home" href="/" ${route === '/' ? 'aria-current="page"' : ''}>Home</a><a id="nav-second" href="/second/" ${late ? 'aria-current="page"' : ''}>Second</a></header><main id="main" data-page="${title}"><h1>${title}</h1><a id="second" href="/second/">Second</a><a id="third" href="/third/">Third</a><a id="hash" href="/third/#target">Hash</a><a id="slow" href="/slow/">Slow</a><a id="native" data-no-swup href="/native/">Native</a><div style="height:1500px"></div><h2 id="target">Target section</h2><a id="same-hash" href="#target">Same document anchor</a>${bad ? '<p id="native-fallback">Native fallback document</p>' : ''}</main><footer>Persistent footer</footer><style id="route-footer-style">body{--route-owner:${title}}</style>${extra}<script type="application/json" id="lunara-navigation-manifest">${JSON.stringify(routeManifest)}</script></body></html>`;
}
async function routes(page) {
    await page.route('https://navigation.test/**', async route => {
        const url = new URL(route.request().url()), pathname = url.pathname;
        if (pathname === '/slow/' && !route.request().isNavigationRequest()) await new Promise(resolve => setTimeout(resolve, 250));
        if (pathname === '/fallback-json/' && !route.request().isNavigationRequest()) return route.fulfill({ contentType: 'application/json', body: '{}' });
        if (pathname === '/fallback-error/' && !route.request().isNavigationRequest()) return route.fulfill({ status: 503, contentType: 'text/html', body: 'Unavailable' });
        if (pathname === '/missing.js') return route.fulfill({ status: 404, contentType: 'text/javascript', body: '' });
        if (pathname === '/unregistered.js') return route.fulfill({ contentType: 'text/javascript', body: 'window.unknownScript=true;' });
        if (pathname === '/component.js' || pathname === '/late-component.js') {
            const name = pathname === '/component.js' ? 'public-runtime' : 'home-runtime';
            return route.fulfill({ contentType: 'text/javascript', body: `window.assetLoads['${name}']=(window.assetLoads['${name}']||0)+1; LunaraApp.register('${name}',(root,ctx)=>{window.mounts++;ctx.on(window,'probe',()=>window.hits++);root.dataset.mounted='yes';return ()=>window.disposals++;});` });
        }
        if (pathname.endsWith('.css')) return route.fulfill({ contentType: 'text/css', body: pathname === '/second.css' ? 'h1{color:rgb(120, 20, 40)}' : 'body{margin:0}header{height:30px}.screen-reader-text{position:absolute;width:1px;height:1px;overflow:hidden}h1{margin:0}' });
        if (pathname.startsWith('/assets/js/')) return route.fulfill({ contentType: 'text/javascript', body: fs.readFileSync(path.join(root, pathname)) });
        return route.fulfill({ contentType: 'text/html', body: html(pathname) });
    });
    await page.addInitScript(() => {
        window.documentLoads = +(sessionStorage.getItem('documentLoads') || 0) + 1;
        sessionStorage.setItem('documentLoads', window.documentLoads);
        window.assetLoads = {}; window.mounts = 0; window.disposals = 0; window.hits = 0;
        window.navigationErrors = [];
        window.addEventListener('error', e => window.navigationErrors.push(e.message));
    });
}
async function ready(page, pathname) {
    await page.waitForFunction(p => window.LunaraApp?.current?.url === location.href && location.pathname === p && document.querySelector('#main')?.dataset.mounted === 'yes' && !document.querySelector('#main').hasAttribute('aria-busy'), pathname);
}
(async () => {
    const browser = await chromium.launch({ executablePath, headless: true });
    try {
        for (const reducedMotion of ['no-preference', 'reduce']) {
            const page = await browser.newPage({ viewport: { width: 390, height: 760 }, reducedMotion });
            page.on('console', msg => { if (msg.type() === 'error') console.error('browser:', msg.text()); });
            await routes(page);
            await page.goto('https://navigation.test/'); await ready(page, '/');
            await page.evaluate(() => { window.shell = document.querySelector('header'); });
            await page.locator('#second').click(); await ready(page, '/second/');
            let state = await page.evaluate(() => ({ documentLoads, sameHeader: shell === document.querySelector('header'), title: document.title, lang: document.documentElement.lang, body: document.body.dataset.route, canonical: document.querySelector('[rel=canonical]').href, description: document.querySelector('meta[name=description]').content, focus: document.activeElement.tagName, color: getComputedStyle(document.querySelector('h1')).color, search: LUNARA_LIVE_SEARCH.endpoint, current: document.querySelector('#nav-second').getAttribute('aria-current'), oldCurrent: document.querySelector('#nav-home').hasAttribute('aria-current'), mounts, disposals, assetLoads, errors: navigationErrors }));
            assert(state.documentLoads === 1 && state.sameHeader, 'Internal route preserves the existing document and shell');
            assert(state.title === 'second' && state.lang === 'fr' && state.body === 'second', 'Title, language and body state update');
            assert(state.canonical.endsWith('/second/') && state.description === 'second', 'Canonical and metadata update');
            assert(state.color === 'rgb(120, 20, 40)' && state.search === '/api/second/', 'Route CSS is ready and typed configuration updates');
            assert(state.focus === 'H1' && state.current === 'page' && !state.oldCurrent, 'New heading receives focus and persistent navigation reflects current route');
            assert(state.assetLoads['home-runtime'] === 1 && state.disposals === 1 && !state.errors.length, 'New audited component loads once and old resources dispose');
            await page.evaluate(() => scrollTo(0, 600)); await page.waitForTimeout(40);
            await page.evaluate(() => document.querySelector('#third').click()); await ready(page, '/third/');
            await page.goBack(); await ready(page, '/second/');
            state = await page.evaluate(() => ({ y: scrollY, loads: documentLoads, assetLoads, styleCount: document.querySelectorAll('#route-footer-style').length, hits: (dispatchEvent(new Event('probe')), hits), mounted: mounts - disposals }));
            assert(Math.abs(state.y - 600) <= 1 && state.loads === 1, 'Back restores route scroll without reloading');
            assert(state.assetLoads['home-runtime'] === 1 && state.styleCount === 1, 'Route assets and footer styles do not accumulate');
            assert(state.hits === 2 && state.mounted === 2, 'Only two current component listeners remain after repeated visits');
            await page.evaluate(() => document.querySelector('#hash').click()); await ready(page, '/third/');
            assert(await page.evaluate(() => location.hash === '#target' && document.activeElement.id === 'target' && scrollY > 0 && document.querySelector('#target').getBoundingClientRect().bottom <= innerHeight), 'Cross-route hash is scrolled and focused');
            await page.evaluate(() => { document.querySelector('#slow').click(); setTimeout(() => document.querySelector('#second').click(), 15); });
            await ready(page, '/second/'); await page.waitForTimeout(320);
            assert(await page.evaluate(() => location.pathname === '/second/' && LunaraApp.current.root === document.querySelector('#main')), 'Rapid navigation cannot mount the canceled route');
            const ignored = await page.evaluate(() => ['/wp-admin/', '/wp-login.php', '/wp-json/test', '/feed/', '/movie.pdf', '/?preview=true', 'https://elsewhere.test/'].map(url => LunaraNavigation.ignoredUrl(url)));
            assert(ignored.every(Boolean), 'Admin, API, feed, downloads, preview and external links retain native behavior');
            const ledger = await page.evaluate(() => {
                const root = document.createElement('div'); root.dataset.lleRoot = ''; root.dataset.lleBase = '/second/'; document.querySelector('#main').append(root);
                const own = LunaraNavigation.ledgerOwnsHistory({ state: { lle: { base: '/second/', url: location.href } } });
                const foreign = LunaraNavigation.ledgerOwnsHistory({ state: { lle: { base: '/oscars/explore/', url: location.href } } });
                root.remove();
                return { own, foreign, detached: LunaraNavigation.ledgerOwnsHistory({ state: { lle: { base: '/second/', url: location.href } } }) };
            });
            assert(ledger.own && !ledger.foreign && !ledger.detached, 'Only an attached matching Ledger route owns its filter history');
            await page.locator('#native').click(); await page.waitForURL('**/native/');
            assert(await page.evaluate(() => documentLoads === 2), 'Explicit native link performs ordinary navigation');
            await page.close();
        }
        for (const kind of ['inline', 'script', 'missing', 'json', 'error']) {
            const page = await browser.newPage(); await routes(page);
            await page.goto('https://navigation.test/'); await ready(page, '/');
            await page.evaluate(kind => LunaraNavigation.swup.navigate('/fallback-' + kind + '/'), kind);
            await page.waitForFunction(() => documentLoads === 2 && location.pathname.startsWith('/fallback-'));
            assert(await page.locator('#native-fallback').isVisible(), `Failure ${kind} ends on a readable native document`);
            await page.close();
        }
        const native = await browser.newPage({ javaScriptEnabled: false }); await routes(native);
        await native.goto('https://navigation.test/'); await native.locator('#second').click();
        assert(native.url().endsWith('/second/') && await native.locator('h1').textContent() === 'second', 'No-JavaScript route content and links remain complete');
        await native.close();
        console.log(JSON.stringify({ test: 'navigation-browser-runtime', checks, motionModes: 2, fallbackCases: 5 }));
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
