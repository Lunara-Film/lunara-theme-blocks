'use strict';
// The three-doors masthead (3.2.161) in the real public cascade (style, shell,
// home modules, guardrails), JavaScript off: geometry, order, the doors'
// shared level, faces and square corners.
const fs = require('fs'), path = require('path'), { execFileSync } = require('child_process');
const { chromium } = require('playwright-core');
const assertSquareSurfaces = require('./tools/assert-square-surfaces.cjs');
const root = path.resolve(__dirname, '..');
const executablePath = process.env.LUNARA_BROWSER_EXECUTABLE || ['C:/Program Files/Google/Chrome/Application/chrome.exe', '/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome'].find(fs.existsSync);
const masthead = execFileSync('php', [path.join(__dirname, 'home-front-desk-runtime.php'), '--fixture'], { encoding: 'utf8' });
// The Home cover (3.2.130) is the header and the masthead together: the real
// cover header (no wordmark, 64/56px) with its own printed CSS sits above the
// masthead, and the budget below is measured from the top of the page.
const header = JSON.parse(execFileSync('php', [path.join(__dirname, 'header-navigation-runtime.php'), '--fixture'], { encoding: 'utf8' }));
// This page runs with scripts off, so the header's <noscript> presentation
// (its own contract: header-navigation-browser-runtime.js) is left out here;
// the cover is measured as a scripted reader meets it.
const coverHeader = header.cover_html.replace(/<noscript>[\s\S]*?<\/noscript>/, '');
const css = ['style.css', 'assets/css/lunara-shell.css', 'assets/css/lunara-home-modules.css', 'assets/css/lunara-public-guardrails.css'].map(f => fs.readFileSync(path.join(root, f), 'utf8')).join('\n');
// The live site's Customizer runtime CSS sets the site-wide border token to
// white (lunara-runtime-customizer-css). Home's gold rules must hold against it.
const liveTokens = ':root{--lunara-border:rgba(255,255,255,0.24)}';
const still = 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="1920" height="1080"><rect width="1920" height="1080" fill="#1c2633"/></svg>');
// The width a covering door picture needs, as the renderer prints it (inc/setup.php,
// 'front-door'): the wider of the measured frame and its height at 16:9.
const doorSizes = (width) => width <= 820 ? Math.max(width - 68, 444) : width <= 1300 ? .694 * width : Math.min(.694 * width, 996);
let checks = 0;
const assert = (ok, message, detail) => { checks++; if (!ok) { console.error(JSON.stringify(detail, null, 1)); throw new Error(message); } };
(async () => {
    const browser = await chromium.launch({ headless: true, executablePath });
    try {
        for (const width of [320, 390, 768, 1100, 1440]) {
            const page = await browser.newPage({ viewport: { width, height: 1000 }, javaScriptEnabled: false });
            // The live masthead carries the 1663x490 wordmark with the inline
            // sizing the renderer emits for the Living Pulse logo settings
            // (1180x312); the doors masthead grows it to 420px, no further.
            const wordmark = 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="1663" height="490"><rect width="1663" height="490" fill="#c9a961"/></svg>');
            const withLogo = masthead.replace(/src="https:[^"]+"/g, `src="${still}"`).replace(/srcset="[^"]*"/g, '').replace(/<span class="lunara-home-masthead-logo-fallback">[^<]*<\/span>/, `<img width="1663" height="490" src="${wordmark}" class="lunara-home-masthead-logo" alt="" style="display:block;width:var(--lunara-masthead-logo-w,min(100%,1180px));height:auto;max-height:var(--lunara-masthead-logo-cap,clamp(118px,22vw,312px));object-fit:contain;">`);
            await page.setContent(`<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>*,*::before,*::after{box-sizing:border-box}body{margin:0}</style><style>${css}</style><style>${liveTokens}</style>${header.css}</head><body class="home lunara-home-cover lunara-header-takeover lunara-header-cover">${coverHeader}<div id="primary" class="site-main lunara-front-page">${withLogo}<section class="lunara-home-section lunara-home-slot-dispatch"><p class="lunara-home-section-kicker">Journal</p><h2 class="lunara-home-section-title">Fresh movement from the Journal</h2><p class="lunara-home-section-summary">A summary line.</p><h3>A card title</h3></section></div></body></html>`, { waitUntil: 'load' });
            const d = await page.evaluate(() => {
                const r = s => { const e = document.querySelector(s); if (!e) return null; const b = e.getBoundingClientRect(); return { left: b.left, right: b.right, top: b.top, bottom: b.bottom, width: b.width, height: b.height }; };
                const film = document.querySelector('.lunara-home-front-desk-title .lunara-home-front-desk-film'), take = document.querySelector('.lunara-home-front-desk-title .lunara-home-front-desk-take');
                const lead = r('.lunara-home-front-desk-lead');
                const doors = [...document.querySelectorAll('.lunara-home-door')].map(e => {
                    const label = e.querySelector('.lunara-home-masthead-route-label'), line = e.querySelector('.lunara-home-door-line'), cta = e.querySelector('.lunara-home-door-cta'), frame = e.querySelector('.lunara-home-door-frame'), media = e.querySelector('.lunara-home-door-media');
                    const b = e.getBoundingClientRect(), lb = label.getBoundingClientRect(), nb = line.getBoundingClientRect();
                    return { left: b.left, right: b.right, top: b.top, bottom: b.bottom, width: b.width, height: b.height, labelBottom: lb.bottom, labelHeight: lb.height, labelSize: parseFloat(getComputedStyle(label).fontSize), labelFont: getComputedStyle(label).fontFamily, labelLines: Math.round(lb.height / parseFloat(getComputedStyle(label).lineHeight)), lineHeight: nb.height, lineLH: parseFloat(getComputedStyle(line).lineHeight), lineSize: parseFloat(getComputedStyle(line).fontSize), ctaHeight: cta.getBoundingClientRect().height, frame: frame ? frame.getBoundingClientRect().width : null, mediaFit: media ? getComputedStyle(media).objectFit : null, word: label.textContent.trim(), radius: getComputedStyle(e).borderTopLeftRadius };
                });
                const datelineSpans = [...document.querySelectorAll('.lunara-home-program-dateline-items span')].map(e => e.getBoundingClientRect());
                const section = document.querySelector('.lunara-home-section');
                return {
                    scroll: document.documentElement.scrollWidth,
                    header: r('.lunara-header'), headerBrand: (() => { const e = document.querySelector('.lunara-header-brand'); return e ? getComputedStyle(e).visibility : null; })(), headerSearch: r('.lunara-header-search'), headerMenu: r('.lunara-header-burger'), searchControls: document.querySelectorAll('[data-lunara-search-open]').length,
                    dateline: r('.lunara-home-program-dateline'), identity: r('.lunara-home-masthead-identity'), lead, routes: r('.lunara-home-masthead-routes'), doors,
                    leadFrame: r('.lunara-home-front-desk-lead .lunara-home-front-desk-frame'), leadText: r('.lunara-home-front-desk-text'),
                    filmFont: getComputedStyle(film).fontFamily, filmSize: parseFloat(getComputedStyle(film).fontSize), takeStyle: getComputedStyle(take).fontStyle, takeFont: getComputedStyle(take).fontFamily,
                    copySize: parseFloat(getComputedStyle(document.querySelector('.lunara-home-front-desk-copy')).fontSize),
                    datelineSize: Math.min(...[...document.querySelectorAll('.lunara-home-program-dateline-items span')].map(e => parseFloat(getComputedStyle(e).fontSize))),
                    datelineLines: new Set(datelineSpans.map(b => Math.round(b.top))).size, datelineCenter: (Math.min(...datelineSpans.map(b => b.left)) + Math.max(...datelineSpans.map(b => b.right))) / 2,
                    datelineDots: [...document.querySelectorAll('.lunara-home-program-dateline-items span')].map(e => getComputedStyle(e, '::after').content),
                    searchBox: r('.lunara-home-program-search'),
                    emptyFrames: [...document.querySelectorAll('.lunara-home-front-desk-frame, .lunara-home-door-frame')].filter(f => !f.querySelector('img')).length,
                    logo: r('.lunara-home-masthead-logo'), dek: r('.lunara-home-masthead-dek'), dekSize: parseFloat(getComputedStyle(document.querySelector('.lunara-home-masthead-dek')).fontSize),
                    sectionRule: (() => { const s = section; const cs = getComputedStyle(s, '::before'); const own = getComputedStyle(s); const panel = getComputedStyle(document.querySelector('.lunara-home-masthead-panel')); const title = getComputedStyle(s.querySelector('.lunara-home-section-title')); return { bg: cs.backgroundColor, h: parseFloat(cs.height), ownBorder: parseFloat(own.borderTopWidth), panelBorder: parseFloat(panel.borderBottomWidth), padTop: parseFloat(own.paddingTop), titleFont: title.fontFamily, titleWeight: title.fontWeight, titleSize: parseFloat(title.fontSize), kickerSize: parseFloat(getComputedStyle(s.querySelector('.lunara-home-section-kicker')).fontSize), summarySize: parseFloat(getComputedStyle(s.querySelector('.lunara-home-section-summary')).fontSize), h3Size: parseFloat(getComputedStyle(s.querySelector('h3')).fontSize) }; })(),
                    mastheadToSection: section.getBoundingClientRect().top - document.querySelector('.lunara-home-masthead').getBoundingClientRect().bottom,
                };
            });
            const label = `front desk@${width}`;
            const phone = width <= 820;
            assert(d.scroll <= width + 1, `${label}: no document overflow`, d);
            assert(d.dateline.bottom <= d.identity.top + 1 && d.identity.bottom <= d.routes.top + 1 && d.routes.bottom <= d.lead.top + 1, `${label}: dateline, mark, the three doors, then Now Showing`, d);
            // The three doors: Reviews, Journal, Oscars, side by side from 821px
            // and stacked below, every word in the display face and large, and
            // all three words on one level: the lines under them reserve two
            // lines each, so the words' baselines agree within a pixel.
            assert(d.doors.length === 3 && d.doors.map(x => x.word).join(',') === 'Reviews,Journal,Oscars', `${label}: three doors read Reviews, Journal, Oscars`, d.doors);
            if (phone) assert(d.doors[1].top >= d.doors[0].bottom - 1 && d.doors[2].top >= d.doors[1].bottom - 1 && d.doors.every(x => Math.abs(x.width - d.routes.width) <= 1 && x.height >= 250), `${label}: the doors stack full width, 250px tall`, d.doors);
            else {
                assert(d.doors[1].left >= d.doors[0].right - 1 && d.doors[2].left >= d.doors[1].right - 1 && d.doors.every(x => Math.abs(x.top - d.doors[0].top) <= 1 && Math.abs(x.height - d.doors[0].height) <= 1), `${label}: the doors stand side by side, the same height`, d.doors);
                assert(d.doors.every(x => Math.abs(x.labelBottom - d.doors[0].labelBottom) <= 1), `${label}: the three words sit on the same level`, d.doors);
                assert(d.doors[0].height >= (width >= 1440 ? 540 : 390), `${label}: a door is a tall picture`, d.doors);
            }
            assert(d.doors.every(x => x.labelFont.startsWith('"Tiempos Headline"') && x.labelLines === 1 && x.labelSize >= (width >= 1440 ? 90 : 56) && x.labelSize <= 93), `${label}: each door's word is one line of Tiempos Headline, ${width >= 1440 ? 90 : 56}px or larger`, d.doors);
            assert(d.doors.every(x => Math.abs(x.lineHeight - 2 * x.lineLH) <= 1.5 && x.lineSize >= 15 && x.ctaHeight >= 12), `${label}: every door reserves exactly two lines for its line, 15px or larger, and one for its cue`, d.doors);
            assert(d.doors.every(x => x.frame === null || (Math.abs(Math.max(x.frame, x.height * 16 / 9) - doorSizes(width)) <= 8 && x.mediaFit === 'cover')), `${label}: a door's picture covers its frame, and the sizes attribute says the width a covering 16:9 picture needs (${Math.round(doorSizes(width))}px)`, { doors: d.doors, expected: doorSizes(width) });
            assert(d.doors.filter(x => x.frame !== null).length >= 2 && d.emptyFrames === 0, `${label}: the fixture's Reviews and Journal doors carry pictures (no Oscar pick in the fixture) and no frame is empty`, d.doors);
            assert(d.doors.every(x => x.radius === '0px'), `${label}: doors have square corners`, d.doors);
            // The lead below the doors: its 16:9 still, the split headline in the
            // signature face, the text beside the still from 1101px.
            assert(Math.abs(d.leadFrame.width / d.leadFrame.height - 16 / 9) < .02, `${label}: the lead still sits in a 16:9 frame`, d);
            assert(d.filmFont.startsWith('"GT Sectra Display"') && d.takeStyle === 'italic' && d.takeFont.startsWith('"Tiempos Text"') && d.filmSize >= (width >= 1440 ? 60 : 40) && d.copySize >= 17, `${label}: the film name is GT Sectra at ${width >= 1440 ? 60 : 40}px or larger, the take is reading italic, the copy 17px or larger`, d);
            if (width > 1100) assert(d.leadText.left >= d.leadFrame.right - 1 && Math.abs((d.leadText.top + d.leadText.bottom) / 2 - (d.leadFrame.top + d.leadFrame.bottom) / 2) <= 8, `${label}: the lead's text stands beside its still, centred on it`, d);
            else assert(d.leadText.top >= d.leadFrame.bottom - 1 && Math.abs(d.leadText.width - d.leadFrame.width) <= 1, `${label}: the lead's text follows its still on narrow screens`, d);
            // The cover: one wordmark on the page (the masthead's), a slim header
            // with search and the menu, one dateline line with middle dots, and the
            // doors inside a tight budget from the top of the page.
            assert(d.headerBrand !== 'visible' && d.searchBox === null && d.searchControls === 1 && d.headerSearch.height >= 44 && d.headerMenu.height >= 44, `${label}: the header shows no wordmark at the top of the cover, and the one Search control is the header's`, d);
            assert(d.header.height >= (width > 1020 ? 64 : 56) && d.header.height <= (width > 1020 ? 64 : 56) + 1 && d.dateline.top >= d.header.bottom - .6, `${label}: the cover header is a ${width > 1020 ? 64 : 56}px bar (plus its hairline) and the masthead starts under it`, d);
            assert(d.datelineSize >= (width > 820 ? 13 : 12) && (width < 360 || d.datelineLines === 1) && Math.abs(d.datelineCenter - width / 2) <= 2 && d.datelineDots.slice(0, -1).every(c => c.includes('·')) && !d.datelineDots.at(-1).includes('·'), `${label}: the dateline is one centered line with middle dots between its items`, d);
            process.stdout.write(`${label}: header ${d.header.height}px, the doors start at ${Math.round(d.routes.top)}px from the top of the page, ${Math.round(d.doors[0].height)}px tall, word ${d.doors[0].labelSize}px\n`);
            assert(d.routes.top <= (width > 820 ? 400 : 420), `${label}: the doors start within the cover budget (${width > 820 ? 400 : 420}px from the top of the page)`, d);
            assert(d.logo.height <= (phone ? 76.5 : 124.5) && d.logo.height >= (phone ? 60 : 70) && d.logo.width <= Math.min(width, 421) && Math.abs((d.logo.left + d.logo.right) / 2 - width / 2) <= 2, `${label}: the wordmark is a centered mark up to 420px wide (74px tall on a phone, 124px on desktop)`, d);
            assert(d.dek.top >= d.logo.bottom - 1 && d.dekSize >= 20, `${label}: the dek sits below the wordmark, never on it, at 20px or larger`, d);
            // The Home type scale (3.2.161) on a section below the masthead.
            assert(d.sectionRule.bg === 'rgba(201, 169, 97, 0.28)' && Math.abs(d.sectionRule.h - 1) < .6 && d.sectionRule.titleFont.startsWith('"GT Sectra Display"') && d.sectionRule.titleWeight === '400', `${label}: a Home section opens on the gold hairline with a signature-face title`, d);
            assert(d.sectionRule.titleSize >= (width >= 1440 ? 56 : 34) && d.sectionRule.kickerSize >= 15 && d.sectionRule.summarySize >= 17 && d.sectionRule.h3Size >= 21, `${label}: section titles ${width >= 1440 ? 56 : 34}px or larger, kickers 15px, summaries 17px, card titles 21px`, d.sectionRule);
            assert(d.sectionRule.ownBorder === 0 && d.sectionRule.panelBorder === 0 && d.sectionRule.padTop >= 32 && d.sectionRule.padTop <= 48 && d.mastheadToSection >= 0 && d.mastheadToSection <= 70, `${label}: one hairline only (no legacy section border, none under the masthead) and the program's rhythm`, d);
            await assertSquareSurfaces(page, label);
            await page.close();
        }
        process.stdout.write(`Home front desk browser: ${checks} checks passed.\n`);
    } finally { await browser.close(); }
})().catch(e => { console.error(e); process.exit(1); });
