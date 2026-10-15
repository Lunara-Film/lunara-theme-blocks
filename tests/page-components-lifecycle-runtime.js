'use strict';
// Real component scripts, real browser layout and Splide. Removed pages must
// stop responding and the same DOM must be safe to mount for a second visit.
const fs = require('node:fs');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const { chromium } = require('playwright-core');
const root = path.resolve(__dirname, '..');
const executablePath = process.env.LUNARA_BROWSER_EXECUTABLE || ['C:/Program Files/Google/Chrome/Application/chrome.exe', 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', '/usr/bin/google-chrome', '/usr/bin/chromium'].find(fs.existsSync);
const rendered = spawnSync('php', [path.join(__dirname, 'home-oscar-mobile-art-runtime.php'), '--framing-fixture'], { encoding: 'utf8' });
if (rendered.status !== 0) throw new Error(rendered.stderr || rendered.stdout);
const scripts = ['lunara-public-runtime', 'lunara-home-runtime', 'lunara-scroll-carousel', 'lunara-dynamic-rails', 'lunara-carousel', 'lunara-media-showcase', 'lunara-splide-pilot', 'lunara-hero-carousel', 'lunara-home-carousels', 'lunara-cinematic-home', 'lunara-immersive-read'];
let checks = 0;
const check = (value, message) => { checks++; if (!value) throw new Error(message); };
function fixture() {
    const items = '<div style="min-width:200px;height:60px">One</div><div style="min-width:200px;height:60px">Two</div><div style="min-width:200px;height:60px">Three</div>';
    return `<!doctype html><html><head><style>*{box-sizing:border-box}body{margin:0}#main{max-width:700px}.track{display:flex;width:210px;overflow:auto;scroll-behavior:auto}.lunara-carousel-slide{height:40px}.lunara-carousel-slide:not(.active){display:none}</style><link rel="stylesheet" href="/assets/vendor/splide/splide-core.min.css"><script src="/assets/js/lunara-page-lifecycle.js"></script><script defer src="/assets/vendor/splide/splide.min.js"></script>${scripts.map(name => `<script defer src="/assets/js/${name}.js"></script>`).join('')}</head><body><main id="main">
    <div class="aat-stat-number">1,234 nominations</div>
    <section id="scroll" data-lunara-carousel data-lunara-carousel-autoplay="0"><button data-lunara-carousel-next>Next</button><div class="track" data-lunara-carousel-track>${items}</div></section>
    <section id="dynamic" data-lunara-dynamic-rail data-lunara-dynamic-rail-autoplay="0"><button data-lunara-dynamic-rail-next>Next</button><div class="track" data-lunara-dynamic-rail-track>${items.replaceAll('<div ', '<div data-lunara-dynamic-rail-item ')}</div></section>
    <section id="generic" class="lunara-carousel" data-autoplay="0"><div class="lunara-carousel-slide active">First</div><div class="lunara-carousel-slide">Second</div><div class="lunara-carousel-slide">Third</div></section>
    <section id="media" class="lunara-media-slider" data-lunara-media-slider><button class="lunara-media-arrow" data-dir="1">Next</button><div class="track lunara-media-slider-track">${items.replaceAll('<div ', '<div class="lunara-media-slide" ')}</div></section>
    ${rendered.stdout}</main></body></html>`;
}
(async () => {
    const browser = await chromium.launch({ headless: true, executablePath });
    try {
        const page = await browser.newPage({ reducedMotion: 'reduce', viewport: { width: 1000, height: 1200 } });
        const errors = [];
        page.on('pageerror', err => errors.push(err.message));
        await page.addInitScript(() => window.addEventListener('lunara:page:error', e => { (window.lifecycleErrors ||= []).push(String(e.detail.error)); }));
        await page.route('https://components.test/**', route => {
            const pathname = new URL(route.request().url()).pathname;
            if (pathname === '/') return route.fulfill({ contentType: 'text/html', body: fixture() });
            const file = path.resolve(root, '.' + pathname);
            return file.startsWith(root + path.sep) && fs.existsSync(file) ? route.fulfill({ contentType: file.endsWith('.js') ? 'text/javascript' : 'text/css', body: fs.readFileSync(file) }) : route.fulfill({ status: 404, body: '' });
        });
        await page.goto('https://components.test/');
        await page.evaluate(() => window.LunaraApp.mount(document.querySelector('#main')));
        check(await page.locator('.aat-stat-number').textContent() === '1,234 nominations', 'Public figures render their real values immediately.');
        for (let visit = 0; visit < 2; visit++) {
            for (const name of ['scroll', 'dynamic', 'media']) {
                await page.evaluate(id => {
                    const section = document.getElementById(id), track = section.querySelector('.track');
                    track.scrollLeft = 0;
                    section.querySelector('button').click();
                }, name);
                const left = await page.locator(`#${name} .track`).evaluate(el => el.scrollLeft);
                check(left > 0 && left < 300, `${name}, visit ${visit}: a single click moves exactly one card.`);
            }
            await page.locator('#generic').dispatchEvent('keydown', { key: 'ArrowRight' });
            check(await page.locator('#generic .active').textContent() === 'Second', `Generic carousel, visit ${visit}: keyboard advances once.`);
            const facts = page.locator('[data-lunara-splide-pilot]');
            await facts.scrollIntoViewIfNeeded();
            await page.waitForFunction(() => !!document.querySelector('[data-lunara-splide-pilot]').lunaraSplidePilot);
            check(await facts.locator('.lunara-oscar-facts-splide-list').count() === 1, `Facts, visit ${visit}: one real Splide wrapper.`);
            await page.evaluate(() => window.LunaraApp.destroy('test-navigation'));
            check(await facts.locator('.lunara-oscar-facts-splide-list').count() === 0, `Facts, visit ${visit}: disposal restores the static DOM.`);
            check(await facts.locator('.splide__slide--clone').count() === 0, `Facts, visit ${visit}: no surviving clones.`);
            for (const name of ['scroll', 'dynamic', 'media']) {
                const result = await page.evaluate(id => {
                    const section = document.getElementById(id), track = section.querySelector('.track');
                    track.scrollLeft = 0;
                    section.querySelector('button').click();
                    return track.scrollLeft;
                }, name);
                check(result === 0, `${name}, visit ${visit}: outgoing controls are detached.`);
            }
            await page.locator('#generic').dispatchEvent('keydown', { key: 'ArrowRight' });
            check(await page.locator('#generic .active').textContent() === 'Second', `Generic carousel, visit ${visit}: outgoing keyboard listener is detached.`);
            await page.evaluate(async () => {
                const slides = document.querySelectorAll('#generic .lunara-carousel-slide');
                slides.forEach((el, index) => el.classList.toggle('active', index === 0));
                await window.LunaraApp.mount(document.getElementById('main'));
            });
        }
        for (const failure of ['constructor', 'mount']) {
            await page.evaluate(async kind => {
                await window.LunaraApp.destroy('test-failure');
                window.actualSplide ||= window.Splide;
                window.pilotAttempts = 0;
                window.Splide = function () {
                    window.pilotAttempts++;
                    if (kind === 'constructor') throw new Error('Simulated unavailable constructor');
                    return { on() { return this; }, mount() { throw new Error('Simulated partial mount'); }, destroy() { throw new Error('Simulated partial teardown'); } };
                };
                await window.LunaraApp.mount(document.getElementById('main'));
            }, failure);
            await page.locator('[data-lunara-splide-pilot]').scrollIntoViewIfNeeded();
            await page.waitForFunction(() => window.pilotAttempts > 0);
            check(await page.locator('.lunara-oscar-facts-splide-list').count() === 0, `${failure} failure restores static markup without nested wrappers.`);
            check(await page.locator('[data-lunara-splide-pilot]').evaluate(el => !el.classList.contains('splide') && !el.hasAttribute('data-lunara-splide-pilot-active')), `${failure} failure restores original classes and ownership.`);
            await page.evaluate(async () => {
                await window.LunaraApp.destroy('retry');
                window.Splide = window.actualSplide;
                await window.LunaraApp.mount(document.getElementById('main'));
            });
            await page.waitForFunction(() => !!document.querySelector('[data-lunara-splide-pilot]').lunaraSplidePilot);
            check(await page.locator('.lunara-oscar-facts-splide-list').count() === 1, `${failure} failure permits a clean successful retry.`);
        }
        check(errors.length === 0, `No browser errors: ${errors.join('; ')}`);
        check(await page.evaluate(() => !(window.lifecycleErrors || []).length), 'No component mount errors were swallowed.');
        await page.close();
        console.log(`Public component lifecycle: ${checks} checks passed.`);
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exit(1); });
