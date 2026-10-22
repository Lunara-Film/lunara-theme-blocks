'use strict';
// The program masthead in the real public cascade (style, shell, home modules,
// guardrails), JavaScript off: geometry, order, faces and square corners.
const fs = require('fs'), path = require('path'), { execFileSync } = require('child_process');
const { chromium } = require('playwright-core');
const assertSquareSurfaces = require('./tools/assert-square-surfaces.cjs');
const root = path.resolve(__dirname, '..');
const executablePath = process.env.LUNARA_BROWSER_EXECUTABLE || ['C:/Program Files/Google/Chrome/Application/chrome.exe', '/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome'].find(fs.existsSync);
const masthead = execFileSync('php', [path.join(__dirname, 'home-front-desk-runtime.php'), '--fixture'], { encoding: 'utf8' });
const css = ['style.css', 'assets/css/lunara-shell.css', 'assets/css/lunara-home-modules.css', 'assets/css/lunara-public-guardrails.css'].map(f => fs.readFileSync(path.join(root, f), 'utf8')).join('\n');
const still = 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="1920" height="1080"><rect width="1920" height="1080" fill="#1c2633"/></svg>');
let checks = 0;
const assert = (ok, message, detail) => { checks++; if (!ok) { console.error(JSON.stringify(detail, null, 1)); throw new Error(message); } };
(async () => {
    const browser = await chromium.launch({ headless: true, executablePath });
    try {
        for (const width of [320, 390, 768, 1100, 1440]) {
            const page = await browser.newPage({ viewport: { width, height: 1000 }, javaScriptEnabled: false });
            await page.setContent(`<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>*,*::before,*::after{box-sizing:border-box}body{margin:0}</style><style>${css}</style></head><body class="home"><main class="lunara-front-page">${masthead.replace(/src="https:[^"]+"/g, `src="${still}"`)}</main></body></html>`, { waitUntil: 'load' });
            const d = await page.evaluate(() => {
                const r = s => { const e = document.querySelector(s); if (!e) return null; const b = e.getBoundingClientRect(); return { left: b.left, right: b.right, top: b.top, bottom: b.bottom, width: b.width, height: b.height }; };
                const film = document.querySelector('.lunara-home-front-desk-title .lunara-home-front-desk-film'), take = document.querySelector('.lunara-home-front-desk-title .lunara-home-front-desk-take');
                const lead = r('.lunara-home-front-desk-lead'), stack = r('.lunara-home-front-desk-stack');
                const comps = [...document.querySelectorAll('.lunara-home-front-desk-companion')].map(e => e.getBoundingClientRect());
                return {
                    scroll: document.documentElement.scrollWidth,
                    dateline: r('.lunara-home-program-dateline'), identity: r('.lunara-home-masthead-identity'), lead, stack, routes: r('.lunara-home-masthead-routes'),
                    leadFrame: r('.lunara-home-front-desk-lead .lunara-home-front-desk-frame'), compFrame: r('.lunara-home-front-desk-companion.has-image .lunara-home-front-desk-frame'),
                    filmFont: getComputedStyle(film).fontFamily, filmSize: parseFloat(getComputedStyle(film).fontSize), takeStyle: getComputedStyle(take).fontStyle, takeFont: getComputedStyle(take).fontFamily,
                    datelineSize: Math.min(...[...document.querySelectorAll('.lunara-home-program-dateline-items span')].map(e => parseFloat(getComputedStyle(e).fontSize))),
                    searchBox: r('.lunara-home-program-search'), compsBelowEachOther: comps.length === 2 && comps[1].top >= comps[0].bottom - 1,
                    emptyFrames: [...document.querySelectorAll('.lunara-home-front-desk-frame')].filter(f => !f.querySelector('img')).length,
                };
            });
            const label = `front desk@${width}`;
            assert(d.scroll <= width + 1, `${label}: no document overflow`, d);
            assert(d.dateline.bottom <= d.identity.top + 1 && d.identity.bottom <= d.lead.top + 1 && d.lead.top <= d.routes.top && d.stack.bottom <= d.routes.top + 1, `${label}: dateline, mark, Now Showing, then the doors`, d);
            assert(Math.abs(d.leadFrame.width / d.leadFrame.height - 16 / 9) < .02 && Math.abs(d.compFrame.width / d.compFrame.height - 16 / 9) < .02 && d.emptyFrames === 0, `${label}: stills sit in 16:9 frames and no frame is empty`, d);
            assert(d.filmFont.startsWith('"GT Sectra Display"') && d.takeStyle === 'italic' && d.takeFont.startsWith('"Tiempos Text"') && d.filmSize >= 32, `${label}: the film name is GT Sectra and the take is reading italic`, d);
            assert(d.datelineSize >= 13 && d.searchBox.height >= 44, `${label}: dateline is at least 13px and Search keeps a 44px target`, d);
            if (width > 1100) assert(d.stack.left >= d.lead.right - 1 && Math.abs(d.stack.top - d.lead.top) <= 1 && d.compsBelowEachOther, `${label}: companions stand beside the lead on desktop`, d);
            else assert(d.stack.top >= d.lead.bottom - 1 && Math.abs(d.lead.width - d.stack.width) <= 1 && d.lead.left >= 16, `${label}: companions follow the lead on narrow screens`, d);
            await assertSquareSurfaces(page, label);
            await page.close();
        }
        process.stdout.write(`Home front desk browser: ${checks} checks passed.\n`);
    } finally { await browser.close(); }
})().catch(e => { console.error(e); process.exit(1); });
