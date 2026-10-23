'use strict';
// Stills that Rhyme in the real public cascade (style, shell, home modules,
// its own stylesheet, guardrails), JavaScript off, in both faces. Posters
// (the default): the reviewed film's 2:3 poster centered over three 2:3
// posters in one row at every width. Stills (the option): a 16:9 still over
// three 16:9 stills, one row from 760px and stacked below. GT Sectra on the
// title line, Tiempos captions with an italic take, centered frame, the
// program's hairline, no overflow, square corners.
const fs = require('fs'), path = require('path'), { execFileSync } = require('child_process');
const { chromium } = require('playwright-core');
const assertSquareSurfaces = require('./tools/assert-square-surfaces.cjs');
const root = path.resolve(__dirname, '..');
const executablePath = process.env.LUNARA_BROWSER_EXECUTABLE || ['C:/Program Files/Google/Chrome/Application/chrome.exe', '/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome'].find(fs.existsSync);
const fixtures = {
    posters: execFileSync('php', [path.join(__dirname, 'home-stills-that-rhyme-runtime.php'), '--fixture'], { encoding: 'utf8' }),
    stills: execFileSync('php', [path.join(__dirname, 'home-stills-that-rhyme-runtime.php'), '--fixture=stills'], { encoding: 'utf8' }),
};
const css = ['style.css', 'assets/css/lunara-shell.css', 'assets/css/lunara-home-modules.css', 'assets/css/lunara-home-stills-that-rhyme.css', 'assets/css/lunara-public-guardrails.css'].map(f => fs.readFileSync(path.join(root, f), 'utf8')).join('\n');
const still = 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="1600" height="900"><rect width="1600" height="900" fill="#1c2633"/></svg>');
let checks = 0;
const assert = (ok, message, detail) => { checks++; if (!ok) { console.error(JSON.stringify(detail, null, 1)); throw new Error(message); } };
(async () => {
    const browser = await chromium.launch({ headless: true, executablePath });
    try {
        for (const media of ['posters', 'stills']) for (const width of [320, 390, 768, 1366]) {
            const section = fixtures[media];
            const page = await browser.newPage({ viewport: { width, height: 1000 }, javaScriptEnabled: false });
            await page.setContent(`<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>*,*::before,*::after{box-sizing:border-box}body{margin:0}</style><style>${css}</style></head><body class="home"><main class="lunara-front-page">${section.replace(/src="https:[^"]+"/g, `src="${still}"`)}</main></body></html>`, { waitUntil: 'load' });
            const d = await page.evaluate(() => {
                const r = s => { const e = document.querySelector(s); if (!e) return null; const b = e.getBoundingClientRect(); return { left: b.left, right: b.right, top: b.top, bottom: b.bottom, width: b.width, height: b.height }; };
                const style = (s, prop) => { const e = document.querySelector(s); return e ? getComputedStyle(e)[prop] : null; };
                const frames = [...document.querySelectorAll('.lunara-home-stills-frame')].map(f => { const b = f.getBoundingClientRect(); return { ratio: b.width / b.height, empty: !f.querySelector('img') }; });
                return {
                    scroll: document.documentElement.scrollWidth,
                    section: r('.lunara-home-stills-that-rhyme'), head: r('.lunara-home-stills-head'), grid: r('.lunara-home-stills-grid'),
                    review: r('.lunara-home-stills-item.is-review'), three: r('.lunara-home-stills-three'),
                    pairings: [...document.querySelectorAll('.lunara-home-stills-item.is-pairing')].map(e => { const b = e.getBoundingClientRect(); return { left: b.left, right: b.right, top: b.top, bottom: b.bottom, width: b.width, role: e.querySelector('.lunara-home-stills-role').textContent.trim(), text: e.classList.contains('has-no-still') }; }),
                    textCard: (() => { const t = document.querySelector('.lunara-home-stills-frame.is-text'); if (!t) return null; const b = t.getBoundingClientRect(); const cs = getComputedStyle(t); const title = t.querySelector('.lunara-home-stills-text-title'); const tb = title.getBoundingClientRect(); return { ratio: b.width / b.height, bg: cs.backgroundColor, titleFont: getComputedStyle(title).fontFamily, titleInside: tb.left >= b.left && tb.right <= b.right && tb.top >= b.top && tb.bottom <= b.bottom }; })(),
                    frames,
                    titleFont: style('.lunara-home-stills-title', 'fontFamily'), titleSize: parseFloat(style('.lunara-home-stills-title', 'fontSize')),
                    kickerSize: parseFloat(style('.lunara-home-stills-kicker', 'fontSize')), roleSize: parseFloat(style('.lunara-home-stills-role', 'fontSize')), roleText: document.querySelector('.lunara-home-stills-role').textContent.trim(),
                    questionStyle: style('.lunara-home-stills-question-text', 'fontStyle'), questionFont: style('.lunara-home-stills-question-text', 'fontFamily'),
                    filmFont: style('.lunara-home-stills-film', 'fontFamily'), filmSize: parseFloat(style('.lunara-home-stills-film', 'fontSize')), filmStyle: style('.lunara-home-stills-film', 'fontStyle'),
                    takeStyle: style('.lunara-home-stills-take', 'fontStyle'), takeFont: style('.lunara-home-stills-take', 'fontFamily'),
                    sectionTopBorder: parseFloat(getComputedStyle(document.querySelector('.lunara-home-stills-that-rhyme')).borderTopWidth), hairline: (() => { const b = getComputedStyle(document.querySelector('.lunara-home-stills-that-rhyme'), '::before'); return { h: parseFloat(b.height), bg: b.backgroundColor }; })(),
                };
            });
            const label = `stills that rhyme (${media})@${width}`;
            assert(d.scroll <= width + 1, `${label}: no document overflow`, d);
            const ratio = media === 'stills' ? 16 / 9 : 2 / 3;
            assert(d.frames.length === 4 && d.frames.every(f => Math.abs(f.ratio - ratio) < .02), `${label}: four ${media === 'stills' ? '16:9' : '2:3'} frames`, d);
            if (media === 'posters') assert(d.frames.filter(f => f.empty).length === 1 && d.textCard && d.textCard.titleInside && d.textCard.bg === 'rgb(15, 29, 46)' && d.textCard.titleFont.startsWith('"GT Sectra Display"'), `${label}: the film without a poster is a typographic plate on the surface colour`, d);
            else assert(d.frames.every(f => !f.empty) && !d.textCard, `${label}: every frame holds a still`, d);
            assert(d.pairings.length === 3 && d.pairings.map(p => p.role).join('|') === 'Theme Echo|Counter Program|Career Context', `${label}: three pairings, full role names, Method order`, d);
            assert(d.three.top >= d.review.bottom - 1 && d.review.width <= (media === 'stills' ? 860 : 260) + 1 && Math.abs((d.review.left + d.review.right) / 2 - width / 2) <= 1, `${label}: the reviewed film leads, centered, at its face's width; the three follow beneath`, d);
            assert(d.head.bottom <= d.grid.top + 1 && Math.abs((d.grid.left + d.grid.right) / 2 - width / 2) <= 1 && Math.abs((d.head.left + d.head.right) / 2 - width / 2) <= 1, `${label}: the head sits above a centered grid`, d);
            assert(d.titleFont.startsWith('"GT Sectra Display"') && d.titleSize >= 30, `${label}: the title line is GT Sectra`, d);
            assert(d.kickerSize >= 13 && d.roleSize >= 13 && d.roleText === 'Theme Echo', `${label}: kicker and the full role name are at least 13px`, d);
            assert(d.questionStyle === 'italic' && d.questionFont.startsWith('"Tiempos Text"'), `${label}: the Method question is reading italic`, d);
            assert(d.filmFont.startsWith('"Tiempos Text"') && d.filmSize >= 16 && d.filmStyle === 'normal' && d.takeStyle === 'italic' && d.takeFont.startsWith('"Tiempos Text"'), `${label}: captions are Tiempos with the take in italic`, d);
            assert(d.sectionTopBorder === 0 && Math.abs(d.hairline.h - 1) < .6 && d.hairline.bg === 'rgba(201, 169, 97, 0.28)', `${label}: the section opens with the program's single hairline rule (no border of its own)`, d);
            const [p1, p2, p3] = d.pairings;
            if (media === 'posters' || width >= 760) assert(p2.left >= p1.right - 1 && p3.left >= p2.right - 1 && Math.abs(p1.top - p3.top) <= 1 && Math.abs(p1.width - p3.width) <= 1 && p1.left >= 16 - .5 && p3.right <= width - 16 + .5, `${label}: the three stand in one row of equal frames (posters at every width)`, d);
            else assert(p2.top >= p1.bottom - 1 && p3.top >= p2.bottom - 1 && Math.abs(p1.width - p3.width) <= 1 && p1.left >= 16 && p1.right <= width - 16, `${label}: the stills stack with a 16px gutter on phones`, d);
            await assertSquareSurfaces(page, label);
            await page.close();
        }
        process.stdout.write(`Home stills that rhyme browser: ${checks} checks passed.\n`);
    } finally { await browser.close(); }
})().catch(e => { console.error(e); process.exit(1); });
