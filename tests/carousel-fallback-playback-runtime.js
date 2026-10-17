'use strict';
// Actual Facts markup and dependency-free controller, with a controlled browser clock.
const fs = require('node:fs'), path = require('node:path');
const { execFileSync } = require('node:child_process');
const { chromium } = require('playwright-core');
const root = path.resolve(__dirname, '..');
const markup = execFileSync('php', [path.join(__dirname, 'home-oscar-mobile-art-runtime.php'), '--framing-fixture'], { encoding: 'utf8' });
const executablePath = process.env.LUNARA_BROWSER_EXECUTABLE || ['C:/Program Files/Google/Chrome/Application/chrome.exe', '/usr/bin/google-chrome', '/usr/bin/chromium'].find(fs.existsSync);
let checks = 0;
function check(ok, message) { checks++; if (!ok) throw Error(message); }
(async () => {
    const browser = await chromium.launch({ headless: true, executablePath });
    try {
        for (const width of [390, 1440]) {
          for (const pace of [12000, 18000]) {
            const page = await browser.newPage({ viewport: { width, height: 1000 } });
            // install() alone keeps time flowing between browser commands.
            // Pause before navigation so setup/runner latency cannot consume reading time.
            await page.clock.install({ time: new Date('2026-01-01T00:00:00Z') });
            await page.clock.pauseAt(new Date('2026-01-01T00:01:00Z'));
            await page.route('https://fallback.test/**', route => {
                const url = new URL(route.request().url());
                if (url.pathname === '/') return route.fulfill({ contentType: 'text/html', body: `<!doctype html><html><head><meta name="lunara-slider-interval" content="${pace}"><link rel="stylesheet" href="/style.css"><link rel="stylesheet" href="/assets/css/lunara-home-modules.css"></head><body class="home"><main id="main">${markup}</main><script src="/assets/js/lunara-page-lifecycle.js"></script><script src="/assets/js/lunara-carousel.js"></script></body></html>` });
                const file = path.resolve(root, '.' + url.pathname);
                return file.startsWith(root + path.sep) && fs.existsSync(file) ? route.fulfill({ contentType: file.endsWith('.js') ? 'text/javascript' : 'text/css', body: fs.readFileSync(file) }) : route.fulfill({ status: 404, body: '' });
            });
            await page.goto('https://fallback.test/');
            await page.waitForSelector('[data-lunara-carousel-ready]');
            const facts = page.locator('.lunara-oscar-facts-carousel');
            const active = () => facts.locator('.lunara-carousel-slide.active').getAttribute('data-lunara-oscar-item-id');
            const interval = pace;
            const toggle = facts.locator('.splide__toggle');
            check(await facts.locator('.lunara-carousel-dot,.splide__pagination').count() === 0, 'Fallback has no markers.');
            const initial = await active();
            await page.clock.runFor(interval - 1);
            check(await active() === initial, 'Shared pace gives the full reading interval.');
            await page.clock.runFor(1);
            check(await active() !== initial, 'Autoplay advances the actual fallback slide at the reading interval.');
            await toggle.dispatchEvent('click');
            const paused = await active();
            await facts.dispatchEvent('pointerenter', { pointerType: 'mouse' });
            await facts.dispatchEvent('pointerleave', { pointerType: 'mouse' });
            await page.clock.runFor(interval * 2);
            check(await active() === paused && await toggle.getAttribute('aria-label') === 'Start autoplay', 'Explicit pause persists through pointer events.');
            await facts.locator('[data-lunara-slide-next]').dispatchEvent('click');
            check(await active() !== paused, 'Next remains functional while paused.');
            await facts.locator('[data-lunara-slide-prev]').dispatchEvent('click');
            check(await active() === paused, 'Previous returns to the same record.');
            await facts.dispatchEvent('focusin');
            await toggle.dispatchEvent('click');
            await page.clock.runFor(interval + 50);
            check(await active() !== paused, 'Explicit Play works even while the control retains focus.');
            await page.emulateMedia({ reducedMotion: 'reduce' });
            await page.waitForFunction(() => document.querySelector('.lunara-oscar-facts-carousel .splide__toggle').hidden);
            const reduced = await active();
            await page.clock.runFor(interval * 2);
            check(await active() === reduced && !(await toggle.isVisible()), 'A live reduced-motion change stops autoplay and removes playback control.');
            await page.evaluate(() => window.LunaraApp.destroy('test-navigation'));
            await facts.locator('[data-lunara-slide-next]').dispatchEvent('click');
            check(await active() === reduced, 'Outgoing fallback controls are detached.');
            await page.close();
          }
        }
        console.log(`Fallback playback: ${checks} checks passed against real PHP markup.`);
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
