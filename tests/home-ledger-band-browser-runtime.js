'use strict';
// The Oscar Ledger band in the real public cascade (style, shell, home
// modules, the band's own sheet, guardrails), JavaScript off: the band spans
// the viewport, two columns on desktop and stacked on phones, five posters
// then three, 2:3 frames, GT Sectra on the headline, square corners and no
// document overflow at 320px.
const fs = require('fs'), path = require('path'), { execFileSync } = require('child_process');
const { chromium } = require('playwright-core');
const assertSquareSurfaces = require('./tools/assert-square-surfaces.cjs');
const root = path.resolve(__dirname, '..');
const executablePath = process.env.LUNARA_BROWSER_EXECUTABLE || ['C:/Program Files/Google/Chrome/Application/chrome.exe', '/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome'].find(fs.existsSync);
const band = execFileSync('php', [path.join(__dirname, 'home-ledger-band-runtime.php'), '--fixture'], { encoding: 'utf8' });
const css = ['style.css', 'assets/css/lunara-shell.css', 'assets/css/lunara-home-modules.css', 'assets/css/lunara-home-ledger-band.css', 'assets/css/lunara-public-guardrails.css'].map(f => fs.readFileSync(path.join(root, f), 'utf8')).join('\n');
const poster = 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="342" height="513"><rect width="342" height="513" fill="#1c2633"/></svg>');
const fixture = band.replace(/ srcset="[^"]*"/g, '').replace(/src="https:[^"]+"/g, `src="${poster}"`);
let checks = 0;
const assert = (ok, message, detail) => { checks++; if (!ok) { console.error(JSON.stringify(detail, null, 1)); throw new Error(message); } };
(async () => {
    const browser = await chromium.launch({ headless: true, executablePath });
    try {
        for (const width of [320, 390, 768, 1366]) {
            const page = await browser.newPage({ viewport: { width, height: 1000 }, javaScriptEnabled: false });
            await page.setContent(`<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>*,*::before,*::after{box-sizing:border-box}body{margin:0}</style><style>${css}</style></head><body class="home"><div id="primary" class="site-main lunara-front-page"><section class="lunara-home-section lunara-home-slot-featured"><p>Before</p></section>${fixture}<section class="lunara-home-section lunara-home-slot-dispatch"><p>After</p></section></div></body></html>`, { waitUntil: 'load' });
            const d = await page.evaluate(() => {
                const r = s => { const e = document.querySelector(s); if (!e) return null; const b = e.getBoundingClientRect(); return { left: b.left, right: b.right, top: b.top, bottom: b.bottom, width: b.width, height: b.height }; };
                const band = document.querySelector('.lunara-home-ledger-band'), title = document.querySelector('.lunara-home-ledger-band-title');
                const posters = [...document.querySelectorAll('.lunara-home-ledger-band-poster')];
                const visible = posters.filter(p => getComputedStyle(p).display !== 'none' && p.getBoundingClientRect().width > 0);
                const frames = visible.map(p => p.querySelector('.lunara-home-ledger-band-frame').getBoundingClientRect());
                const bandStyle = getComputedStyle(band);
                return {
                    scroll: document.documentElement.scrollWidth,
                    band: r('.lunara-home-ledger-band'), inner: r('.lunara-home-ledger-band-inner'), copy: r('.lunara-home-ledger-band-copy'), strip: r('.lunara-home-ledger-band-strip'),
                    before: r('.lunara-home-slot-featured'), after: r('.lunara-home-slot-dispatch'),
                    background: bandStyle.backgroundColor, borderTop: bandStyle.borderTopWidth + ' ' + bandStyle.borderTopColor, borderBottom: bandStyle.borderBottomWidth + ' ' + bandStyle.borderBottomColor,
                    posters: posters.length, visible: visible.length, hiddenLazy: posters.filter(p => getComputedStyle(p).display === 'none').every(p => p.querySelector('img').loading === 'lazy'),
                    frameRatios: frames.map(f => f.width / f.height), frameWidths: frames.map(f => f.width),
                    posterRow: frames.length > 1 && frames.every(f => Math.abs(f.top - frames[0].top) <= 1),
                    titleFont: getComputedStyle(title).fontFamily, titleSize: parseFloat(getComputedStyle(title).fontSize),
                    kickerSize: parseFloat(getComputedStyle(document.querySelector('.lunara-home-ledger-band-kicker')).fontSize),
                    recordFont: getComputedStyle(document.querySelector('.lunara-home-ledger-band-record')).fontFamily,
                    captionSize: Math.min(...visible.map(p => parseFloat(getComputedStyle(p.querySelector('.lunara-home-ledger-band-caption')).fontSize))),
                    link: r('.lunara-home-ledger-band-link'), linkRadius: getComputedStyle(document.querySelector('.lunara-home-ledger-band-link')).borderTopLeftRadius,
                };
            });
            const label = `ledger band@${width}`;
            assert(d.scroll <= width + 1, `${label}: no document overflow`, d);
            assert(d.band.left <= 0.5 && d.band.right >= width - 0.5 && d.band.width >= width - 1, `${label}: the band spans the viewport`, d);
            // style.css orders home slots with flex order, so a neighbour may land
            // above or below; the band must bleed past both without overlapping.
            const clear = n => n.left > d.band.left + 8 && n.right < d.band.right - 8 && (d.band.top >= n.bottom - 1 || d.band.bottom <= n.top + 1);
            assert(clear(d.before) && clear(d.after), `${label}: the band bleeds past its neighbours without overlapping them`, d);
            assert(d.background === 'rgb(15, 29, 46)' && d.borderTop === '1px rgb(201, 169, 97)' && d.borderBottom === '1px rgb(201, 169, 97)', `${label}: surface navy with one gold rule above and one below`, d);
            assert(d.inner.width <= 1320 + 2 * 76 + 1 && Math.abs(d.inner.left - (width - d.inner.right)) <= 1 && d.copy.left >= (width > 820 ? 40 : 20) - 0.5, `${label}: the inner content sits centered on the masthead container width with a gutter`, d);
            assert(d.posters === 5 && d.visible === (width < 760 ? 3 : 5) && d.hiddenLazy, `${label}: ${width < 760 ? 'three' : 'five'} posters show and any hidden poster stays lazy`, d);
            assert(d.frameRatios.every(ratio => Math.abs(ratio - 2 / 3) < .02) && d.posterRow && d.frameWidths.every(w => w >= 60), `${label}: posters sit in one row of 2:3 frames`, d);
            assert(d.titleFont.startsWith('"GT Sectra Display"') && d.titleSize >= 29 && d.recordFont.startsWith('"Tiempos Text"'), `${label}: the headline is GT Sectra and the record line is reading type`, d);
            assert(d.kickerSize >= 13 && d.captionSize >= 13 && d.link.height >= 44 && d.linkRadius === '0px', `${label}: kicker and captions are at least 13px and the door keeps a square 44px target`, d);
            if (width >= 1000) assert(d.strip.left >= d.copy.right - 1 && d.strip.top < d.copy.bottom && d.copy.top < d.strip.bottom, `${label}: the strip stands beside the copy`, d);
            else assert(d.strip.top >= d.copy.bottom - 1 && Math.abs(d.strip.width - d.copy.width) <= 1, `${label}: the strip follows the copy`, d);
            await assertSquareSurfaces(page, label);
            await page.close();
        }
        process.stdout.write(`Home ledger band browser: ${checks} checks passed.\n`);
    } finally { await browser.close(); }
})().catch(e => { console.error(e); process.exit(1); });
