'use strict';
// The Running Order in the real public cascade (style, shell, home modules,
// its own stylesheet, guardrails), JavaScript off, beneath the program
// masthead: columns per width, no overflow, 2:3 frames, edges aligned with
// Now Showing, faces, minimum sizes, legibility and square corners. 3.2.138
// (Dalton: "the text needs to be much more legible"): two posters across on
// phones, the film at 17px or more, the take at 16px and at least 10:1
// against the page, the stars at 15px.
const fs = require('fs'), path = require('path'), { execFileSync } = require('child_process');
const { chromium } = require('playwright-core');
const assertSquareSurfaces = require('./tools/assert-square-surfaces.cjs');
const root = path.resolve(__dirname, '..');
const executablePath = process.env.LUNARA_BROWSER_EXECUTABLE || ['C:/Program Files/Google/Chrome/Application/chrome.exe', '/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome'].find(fs.existsSync);
const fixture = name => execFileSync('php', [path.join(__dirname, name), '--fixture'], { encoding: 'utf8' });
const masthead = fixture('home-front-desk-runtime.php');
const wall = fixture('home-running-order-runtime.php');
const css = ['style.css', 'assets/css/lunara-shell.css', 'assets/css/lunara-home-modules.css', 'assets/css/lunara-home-running-order.css', 'assets/css/lunara-public-guardrails.css'].map(f => fs.readFileSync(path.join(root, f), 'utf8')).join('\n');
const svg = (w, h) => 'data:image/svg+xml,' + encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" width="${w}" height="${h}"><rect width="${w}" height="${h}" fill="#1c2633"/></svg>`);
const html = masthead.replace(/src="https:[^"]+"/g, `src="${svg(1920, 1080)}"`) + wall.replace(/ srcset="[^"]+"/g, '').replace(/src="https:[^"]+"/g, `src="${svg(342, 513)}"`);
const columnsFor = width => (width >= 1200 ? 6 : width >= 760 ? 4 : 2);
let checks = 0;
const assert = (ok, message, detail) => { checks++; if (!ok) { console.error(JSON.stringify(detail, null, 1)); throw new Error(message); } };
(async () => {
    const browser = await chromium.launch({ headless: true, executablePath });
    try {
        for (const width of [320, 390, 900, 1366, 1440]) {
            const page = await browser.newPage({ viewport: { width, height: 1000 }, javaScriptEnabled: false });
            await page.setContent(`<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>*,*::before,*::after{box-sizing:border-box}body{margin:0}</style><style>${css}</style></head><body class="home"><main class="lunara-front-page">${html}</main></body></html>`, { waitUntil: 'load' });
            const d = await page.evaluate(() => {
                const r = s => { const e = document.querySelector(s); if (!e) return null; const b = e.getBoundingClientRect(); return { left: b.left, right: b.right, top: b.top, bottom: b.bottom, width: b.width, height: b.height }; };
                const items = [...document.querySelectorAll('.lunara-home-running-order-item')];
                const rects = items.map(e => e.getBoundingClientRect());
                const frames = [...document.querySelectorAll('.lunara-home-running-order-frame')].map(e => { const b = e.getBoundingClientRect(); return { ratio: b.width / b.height, width: b.width, text: e.classList.contains('is-text'), hasImg: !!e.querySelector('img'), hasText: e.textContent.trim().length > 0 }; });
                const textFrame = document.querySelector('.lunara-home-running-order-frame.is-text');
                const textFilm = textFrame && textFrame.querySelector('.lunara-home-running-order-film');
                const cs = (s, p) => { const e = document.querySelector(s); return e ? getComputedStyle(e)[p] : null; };
                const firstTop = rects[0] ? rects[0].top : 0;
                return {
                    scroll: document.documentElement.scrollWidth,
                    section: r('.lunara-home-running-order'), inner: r('.lunara-home-running-order-inner'), masthead: r('.lunara-home-front-desk-grid'), routes: r('.lunara-home-masthead-routes'),
                    head: r('.lunara-home-running-order-head'), grid: r('.lunara-home-running-order-grid'), all: r('.lunara-home-running-order-all'),
                    items: items.length, columns: rects.filter(b => Math.abs(b.top - firstTop) < 1).length,
                    itemWidths: [...new Set(rects.map(b => Math.round(b.width)))],
                    frames,
                    linksPerItem: [...new Set(items.map(e => e.querySelectorAll('a').length))],
                    textOutsideLink: items.filter(e => e.textContent.trim() !== e.querySelector('a').textContent.trim()).length,
                    textFrameCentered: textFrame && textFilm ? Math.abs((textFilm.getBoundingClientRect().left + textFilm.getBoundingClientRect().right) / 2 - (textFrame.getBoundingClientRect().left + textFrame.getBoundingClientRect().right) / 2) : null,
                    titleFont: cs('.lunara-home-running-order-title', 'fontFamily'), filmFont: cs('.lunara-home-running-order-film', 'fontFamily'),
                    takeFont: cs('.lunara-home-running-order-take', 'fontFamily'), takeStyle: cs('.lunara-home-running-order-take', 'fontStyle'), takeSize: parseFloat(cs('.lunara-home-running-order-take', 'fontSize')),
                    kickerTransform: cs('.lunara-home-running-order-kicker', 'textTransform'), kickerSpacing: parseFloat(cs('.lunara-home-running-order-kicker', 'letterSpacing')), kickerColor: cs('.lunara-home-running-order-kicker', 'color'),
                    starSize: parseFloat(cs('.lunara-home-running-order-score .lunara-stars', 'fontSize')),
                    filmSizes: [...document.querySelectorAll('.lunara-home-running-order-card:not(:has(.is-text)) .lunara-home-running-order-film')].map(e => parseFloat(getComputedStyle(e).fontSize)),
                    takeContrast: (() => {
                        const rgb = c => (c.match(/[\d.]+/g) || []).map(Number);
                        const take = document.querySelector('.lunara-home-running-order-card:not(:has(.is-text)) .lunara-home-running-order-take');
                        let n = take.parentElement, ground = [10, 21, 32];
                        while (n) { const c = rgb(getComputedStyle(n).backgroundColor); if (c.length >= 3 && (c.length < 4 || c[3] > .5)) { ground = c.slice(0, 3); break; } n = n.parentElement; }
                        const t = rgb(getComputedStyle(take).color), a = t.length > 3 ? t[3] : 1;
                        const fg = t.slice(0, 3).map((v, i) => v * a + ground[i] * (1 - a));
                        const lum = c => { const [r, g, b] = c.map(v => { v /= 255; return v <= .03928 ? v / 12.92 : Math.pow((v + .055) / 1.055, 2.4); }); return .2126 * r + .7152 * g + .0722 * b; };
                        const L1 = lum(fg), L2 = lum(ground);
                        return (Math.max(L1, L2) + .05) / (Math.min(L1, L2) + .05);
                    })(),
                    frameBackground: cs('.lunara-home-running-order-frame.is-text', 'backgroundColor'),
                    posterFit: cs('.lunara-home-running-order-poster', 'objectFit'), posterLoading: document.querySelector('.lunara-home-running-order-poster').getAttribute('loading'),
                };
            });
            const label = `running order@${width}`;
            assert(d.scroll <= width + 1, `${label}: no document overflow`, d);
            assert(d.routes.bottom <= d.section.top + 1 && d.head.bottom <= d.grid.top, `${label}: the wall follows the masthead, head before grid`, d);
            assert(d.items === 12 && d.columns === columnsFor(width) && d.itemWidths.length <= 2 && Math.abs(d.itemWidths[0] - (d.itemWidths[1] ?? d.itemWidths[0])) <= 1, `${label}: ${columnsFor(width)} equal columns`, d);
            assert(Math.abs(d.inner.left - d.masthead.left) <= 1 && Math.abs(d.inner.right - d.masthead.right) <= 1, `${label}: the wall shares Now Showing's edges`, d);
            assert(d.frames.length === 12 && d.frames.every(f => Math.abs(f.ratio - 2 / 3) < .02) && d.frames.every(f => f.text ? f.hasText && !f.hasImg : f.hasImg), `${label}: every frame is 2:3, a poster or a text card, never empty`, d);
            assert(d.linksPerItem.length === 1 && d.linksPerItem[0] === 1 && d.textOutsideLink === 0, `${label}: each card is one link`, d);
            assert(d.textFrameCentered !== null && d.textFrameCentered <= 2 && d.frameBackground === 'rgb(15, 29, 46)', `${label}: a text card centers its title on the surface color`, d);
            assert(d.titleFont.startsWith('"GT Sectra Display"') && d.filmFont.startsWith('"GT Sectra Display"') && d.takeFont.startsWith('"Tiempos Text"') && d.takeStyle === 'italic', `${label}: GT Sectra for the title and film, Tiempos italic for the take`, d);
            assert(d.kickerTransform === 'uppercase' && d.kickerSpacing > 0 && d.kickerColor === 'rgb(201, 169, 97)', `${label}: the kicker is letterspaced gold capitals`, d);
            assert(d.takeSize >= 16 && d.starSize >= 15 && d.filmSizes.length && d.filmSizes.every(v => v >= 17) && d.all.height >= 44, `${label}: the film reads at 17px or more, the take at 16px, the stars at 15px, and the count link keeps a 44px target`, d);
            assert(d.takeContrast >= 10, `${label}: the take reads at 10:1 or better against the page`, d.takeContrast);
            assert(d.posterFit === 'cover' && d.posterLoading === 'lazy', `${label}: posters cover their frame and load lazily`, d);
            if (width <= 820) assert(d.all.left <= d.head.left + 1 && d.all.top >= d.head.top, `${label}: the count link stacks under the title on phones`, d);
            else assert(d.all.right >= d.head.right - 1 && d.all.left > d.head.left + d.head.width / 2, `${label}: the count link sits right of the title`, d);
            await assertSquareSurfaces(page, label);
            await page.close();
        }
        process.stdout.write(`Home Running Order browser: ${checks} checks passed.\n`);
    } finally { await browser.close(); }
})().catch(e => { console.error(e); process.exit(1); });
