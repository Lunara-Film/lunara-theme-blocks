'use strict';
// Stills that Rhyme in the real public cascade (style, shell, home modules,
// its own stylesheet, guardrails), JavaScript off: two columns on desktop and
// tablet, one on phones, 16:9 frames, GT Sectra on the title line, Tiempos
// captions with an italic take, centered frame, no overflow, square corners.
const fs = require('fs'), path = require('path'), { execFileSync } = require('child_process');
const { chromium } = require('playwright-core');
const assertSquareSurfaces = require('./tools/assert-square-surfaces.cjs');
const root = path.resolve(__dirname, '..');
const executablePath = process.env.LUNARA_BROWSER_EXECUTABLE || ['C:/Program Files/Google/Chrome/Application/chrome.exe', '/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome'].find(fs.existsSync);
const section = execFileSync('php', [path.join(__dirname, 'home-stills-that-rhyme-runtime.php'), '--fixture'], { encoding: 'utf8' });
const css = ['style.css', 'assets/css/lunara-shell.css', 'assets/css/lunara-home-modules.css', 'assets/css/lunara-home-stills-that-rhyme.css', 'assets/css/lunara-public-guardrails.css'].map(f => fs.readFileSync(path.join(root, f), 'utf8')).join('\n');
const still = 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="1600" height="900"><rect width="1600" height="900" fill="#1c2633"/></svg>');
let checks = 0;
const assert = (ok, message, detail) => { checks++; if (!ok) { console.error(JSON.stringify(detail, null, 1)); throw new Error(message); } };
(async () => {
    const browser = await chromium.launch({ headless: true, executablePath });
    try {
        for (const width of [320, 390, 768, 1366]) {
            const page = await browser.newPage({ viewport: { width, height: 1000 }, javaScriptEnabled: false });
            await page.setContent(`<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>*,*::before,*::after{box-sizing:border-box}body{margin:0}</style><style>${css}</style></head><body class="home"><main class="lunara-front-page">${section.replace(/src="https:[^"]+"/g, `src="${still}"`)}</main></body></html>`, { waitUntil: 'load' });
            const d = await page.evaluate(() => {
                const r = s => { const e = document.querySelector(s); if (!e) return null; const b = e.getBoundingClientRect(); return { left: b.left, right: b.right, top: b.top, bottom: b.bottom, width: b.width, height: b.height }; };
                const style = (s, prop) => { const e = document.querySelector(s); return e ? getComputedStyle(e)[prop] : null; };
                const frames = [...document.querySelectorAll('.lunara-home-stills-frame')].map(f => { const b = f.getBoundingClientRect(); return { ratio: b.width / b.height, empty: !f.querySelector('img') }; });
                return {
                    scroll: document.documentElement.scrollWidth,
                    section: r('.lunara-home-stills-that-rhyme'), head: r('.lunara-home-stills-head'), grid: r('.lunara-home-stills-grid'),
                    review: r('.lunara-home-stills-item.is-review'), partner: r('.lunara-home-stills-item.is-partner'),
                    frames,
                    titleFont: style('.lunara-home-stills-title', 'fontFamily'), titleSize: parseFloat(style('.lunara-home-stills-title', 'fontSize')),
                    kickerSize: parseFloat(style('.lunara-home-stills-kicker', 'fontSize')), roleSize: parseFloat(style('.lunara-home-stills-role', 'fontSize')), roleText: document.querySelector('.lunara-home-stills-role').textContent.trim(),
                    questionStyle: style('.lunara-home-stills-question-text', 'fontStyle'), questionFont: style('.lunara-home-stills-question-text', 'fontFamily'),
                    filmFont: style('.lunara-home-stills-film', 'fontFamily'), filmSize: parseFloat(style('.lunara-home-stills-film', 'fontSize')), filmStyle: style('.lunara-home-stills-film', 'fontStyle'),
                    takeStyle: style('.lunara-home-stills-take', 'fontStyle'), takeFont: style('.lunara-home-stills-take', 'fontFamily'),
                    sectionTopBorder: parseFloat(getComputedStyle(document.querySelector('.lunara-home-stills-that-rhyme')).borderTopWidth), hairline: (() => { const b = getComputedStyle(document.querySelector('.lunara-home-stills-that-rhyme'), '::before'); return { h: parseFloat(b.height), bg: b.backgroundColor }; })(),
                };
            });
            const label = `stills that rhyme@${width}`;
            assert(d.scroll <= width + 1, `${label}: no document overflow`, d);
            assert(d.frames.length === 2 && d.frames.every(f => Math.abs(f.ratio - 16 / 9) < .02 && !f.empty), `${label}: two 16:9 frames, neither empty`, d);
            assert(d.head.bottom <= d.grid.top + 1 && Math.abs((d.grid.left + d.grid.right) / 2 - width / 2) <= 1 && Math.abs((d.head.left + d.head.right) / 2 - width / 2) <= 1, `${label}: the head sits above a centered grid`, d);
            assert(d.titleFont.startsWith('"GT Sectra Display"') && d.titleSize >= 30, `${label}: the title line is GT Sectra`, d);
            assert(d.kickerSize >= 13 && d.roleSize >= 13 && d.roleText === 'Theme Echo', `${label}: kicker and the full role name are at least 13px`, d);
            assert(d.questionStyle === 'italic' && d.questionFont.startsWith('"Tiempos Text"'), `${label}: the Method question is reading italic`, d);
            assert(d.filmFont.startsWith('"Tiempos Text"') && d.filmSize >= 16 && d.filmStyle === 'normal' && d.takeStyle === 'italic' && d.takeFont.startsWith('"Tiempos Text"'), `${label}: captions are Tiempos with the take in italic`, d);
            assert(d.sectionTopBorder === 0 && Math.abs(d.hairline.h - 1) < .6 && d.hairline.bg === 'rgba(201, 169, 97, 0.28)', `${label}: the section opens with the program's single hairline rule (no border of its own)`, d);
            if (width >= 760) assert(d.partner.left >= d.review.right - 1 && Math.abs(d.partner.top - d.review.top) <= 1 && Math.abs(d.partner.width - d.review.width) <= 1, `${label}: the two stills stand side by side`, d);
            else assert(d.partner.top >= d.review.bottom - 1 && Math.abs(d.partner.width - d.review.width) <= 1 && d.review.left >= 16 && d.review.right <= width - 16, `${label}: the stills stack with a 16px gutter on phones`, d);
            await assertSquareSurfaces(page, label);
            await page.close();
        }
        process.stdout.write(`Home stills that rhyme browser: ${checks} checks passed.\n`);
    } finally { await browser.close(); }
})().catch(e => { console.error(e); process.exit(1); });
