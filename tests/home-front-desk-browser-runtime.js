'use strict';
// The Home cover in the public cascade with JavaScript off. Check the lead
// frame, reading order, responsive door rows, and visible interactions.
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
const shellRule = (fs.readFileSync(path.join(root, 'header.php'), 'utf8').match(/body\.home \.lunara-front-page,\s*body\.home main\.lunara-front-page \{[^}]*\}/) || [''])[0];
const css = ['style.css', 'assets/css/lunara-shell.css', 'assets/css/lunara-home-modules.css', 'assets/css/lunara-public-guardrails.css'].map(f => fs.readFileSync(path.join(root, f), 'utf8')).join('\n') + '\n' + shellRule;
// The live site's Customizer runtime CSS sets the site-wide border token to
// white (lunara-runtime-customizer-css). Home's gold rules must hold against it.
const liveTokens = ':root{--lunara-border:rgba(255,255,255,0.24)}';
const still = 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="1920" height="1080"><rect width="1920" height="1080" fill="#1c2633"/></svg>');
let checks = 0;
const assert = (ok, message, detail) => { checks++; if (!ok) { console.error(JSON.stringify(detail, null, 1)); throw new Error(message); } };
(async () => {
    const browser = await chromium.launch({ headless: true, executablePath });
    try {
        for (const width of [320, 390, 640, 641, 820, 821, 1100, 1440]) {
            const page = await browser.newPage({ viewport: { width, height: 1000 }, javaScriptEnabled: false });
            // The live masthead carries the 1663x490 wordmark with the inline
            // sizing the renderer emits. CSS constrains it at each breakpoint.
            const wordmark = 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="1663" height="490"><rect width="1663" height="490" fill="#c9a961"/></svg>');
            const withLogo = masthead.replace(/src="https:[^"]+"/g, `src="${still}"`).replace(/srcset="[^"]*"/g, '').replace(/<span class="lunara-home-masthead-logo-fallback">[^<]*<\/span>/, `<img width="1663" height="490" src="${wordmark}" class="lunara-home-masthead-logo" alt="" style="display:block;width:var(--lunara-masthead-logo-w,min(100%,1180px));height:auto;max-height:var(--lunara-masthead-logo-cap,clamp(118px,22vw,312px));object-fit:contain;">`);
            await page.setContent(`<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>*,*::before,*::after{box-sizing:border-box}body{margin:0}</style><style>${css}</style><style>${liveTokens}</style>${header.css}</head><body class="home lunara-home-cover lunara-header-takeover lunara-header-cover">${coverHeader}<div id="primary" class="site-main lunara-front-page">${withLogo}<section class="lunara-home-section lunara-home-slot-dispatch"><p class="lunara-home-section-kicker">Journal</p><h2 class="lunara-home-section-title">Fresh movement from the Journal</h2><p class="lunara-home-section-summary">A summary line.</p><h3>A card title</h3></section></div></body></html>`, { waitUntil: 'load' });
            const d = await page.evaluate(() => {
                const r = s => { const e = document.querySelector(s); if (!e) return null; const b = e.getBoundingClientRect(); return { left: b.left, right: b.right, top: b.top, bottom: b.bottom, width: b.width, height: b.height }; };
                // Resolve the source-size expression with the same viewport as the frame.
                const sourceWidth = image => {
                    if (!image?.sizes) return null;
                    const entries = [];
                    let depth = 0, start = 0;
                    for (let i = 0; i < image.sizes.length; i++) {
                        const char = image.sizes[i];
                        if (char === '(') depth++;
                        if (char === ')') depth--;
                        if (char === ',' && depth === 0) {
                            entries.push(image.sizes.slice(start, i).trim());
                            start = i + 1;
                        }
                    }
                    entries.push(image.sizes.slice(start).trim());
                    for (const entry of entries) {
                        const conditional = entry.match(/^(\([^)]*\))\s+(.+)$/);
                        if (conditional && !matchMedia(conditional[1]).matches) continue;
                        const length = conditional ? conditional[2] : entry;
                        if (length === 'auto' || !CSS.supports('width', length)) continue;
                        const ruler = document.createElement('span');
                        Object.assign(ruler.style, { position: 'absolute', visibility: 'hidden', width: length });
                        document.body.append(ruler);
                        const width = ruler.getBoundingClientRect().width;
                        ruler.remove();
                        return width;
                    }
                    return null;
                };
                const film = document.querySelector('.lunara-home-front-desk-title .lunara-home-front-desk-film'), take = document.querySelector('.lunara-home-front-desk-title .lunara-home-front-desk-take');
                const lead = r('.lunara-home-front-desk-lead');
                const doors = [...document.querySelectorAll('.lunara-home-door')].map(e => {
                    const label = e.querySelector('.lunara-home-masthead-route-label'), line = e.querySelector('.lunara-home-door-line'), cta = e.querySelector('.lunara-home-door-cta'), frame = e.querySelector('.lunara-home-door-frame'), media = e.querySelector('.lunara-home-door-media');
                    const b = e.getBoundingClientRect(), lb = label.getBoundingClientRect();
                    const lineCss = getComputedStyle(line);
                    return {
                        left: b.left, right: b.right, top: b.top, bottom: b.bottom, width: b.width, height: b.height,
                        labelCenter: (lb.left + lb.right) / 2, labelSize: parseFloat(getComputedStyle(label).fontSize),
                        labelColor: getComputedStyle(label).color, lineSize: parseFloat(lineCss.fontSize),
                        lineClamp: lineCss.webkitLineClamp, lineFits: line.scrollHeight <= line.clientHeight + 1,
                        statusSize: parseFloat(getComputedStyle(e.querySelector('.lunara-home-door-status')).fontSize),
                        ctaHeight: cta.getBoundingClientRect().height, frame: frame ? frame.getBoundingClientRect().width : null,
                        mediaFit: media ? getComputedStyle(media).objectFit : null, mediaWidth: sourceWidth(media), word: label.textContent.trim(),
                        radius: getComputedStyle(e).borderTopLeftRadius
                    };
                });
                const datelineSpans = [...document.querySelectorAll('.lunara-home-program-dateline-items span')].map(e => e.getBoundingClientRect());
                const section = document.querySelector('.lunara-home-section');
                return {
                    scroll: document.documentElement.scrollWidth,
                    header: r('.lunara-header'), headerBrand: (() => { const e = document.querySelector('.lunara-header-brand'); return e ? getComputedStyle(e).visibility : null; })(), headerSearch: r('.lunara-header-search'), headerMenu: r('.lunara-header-burger'), searchControls: document.querySelectorAll('[data-lunara-search-open]').length,
                    dateline: r('.lunara-home-program-dateline'), identity: r('.lunara-home-masthead-identity'), lead, routes: r('.lunara-home-masthead-routes'), doors,
                    leadMediaWidth: sourceWidth(document.querySelector('.lunara-home-front-desk-lead-media')),
                    leadFrame: r('.lunara-home-front-desk-lead .lunara-home-front-desk-frame'), leadText: r('.lunara-home-front-desk-text'), leadCta: r('.lunara-home-front-desk-cta'),
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
            const narrow = width <= 820;
            const phone = width <= 640;
            const center = box => (box.left + box.right) / 2;
            assert(d.scroll <= width + 1, `${label}: no document overflow`, d);
            assert(d.dateline.bottom <= d.identity.top + 1 && d.identity.bottom <= d.lead.top + 1 && d.lead.bottom <= d.routes.top + 1, `${label}: dateline, mark, Now Showing, and then the three doors`, d);
            assert(d.doors.length === 3 && d.doors.map(x => x.word).join(',') === 'Reviews,Journal,Oscars', `${label}: three doors read Reviews, Journal, Oscars`, d.doors);
            const [reviews, journal, oscars] = d.doors;
            if (phone) {
                assert(journal.top >= reviews.bottom - 1 && oscars.top >= journal.bottom - 1 && d.doors.every(x => Math.abs(x.width - d.routes.width) <= 1), `${label}: three full-width doors stack`, d.doors);
            } else {
                assert(journal.left >= reviews.right - 1 && Math.abs(journal.top - reviews.top) <= 1, `${label}: Reviews and Journal share the first row`, d.doors);
                if (narrow) {
                    assert(oscars.top >= reviews.bottom - 1 && Math.abs(oscars.width - reviews.width) <= 1 && Math.abs(center(oscars) - center(d.routes)) <= 1, `${label}: the third door is centered on the second row`, d.doors);
                } else {
                    assert(oscars.left >= journal.right - 1 && Math.abs(oscars.top - reviews.top) <= 1 && d.doors.every(x => Math.abs(x.width - reviews.width) <= 1), `${label}: three equal doors share the row`, d.doors);
                }
            }
            assert(d.doors.every(x => x.height >= (phone ? 181 : 229) && x.height <= 320 && Math.abs(x.labelCenter - center(x)) <= 1 && x.labelSize >= 40 && x.labelColor === 'rgb(224, 196, 129)'), `${label}: the gold labels center on compact doors`, d.doors);
            assert(d.doors.every(x => x.lineFits && x.lineClamp === 'none' && x.lineSize >= 14 && x.statusSize >= 13 && x.ctaHeight >= 44), `${label}: every door shows its whole line, readable status and a 44px Enter cue`, d.doors);
            assert(d.doors.filter(x => x.frame !== null).length >= 2 && d.emptyFrames === 0 && d.doors.every(x => x.frame === null || x.mediaFit === 'cover'), `${label}: artwork covers each available door frame`, d.doors);
            assert(d.doors.every(x => x.frame === null || Math.abs(Math.max(x.frame, x.height * 16 / 9) - x.mediaWidth) <= 9), `${label}: door source sizes follow the painted 16:9 cover widths`, d.doors);
            assert(d.doors.every(x => x.radius === '0px'), `${label}: doors have square corners`, d.doors);
            const ratio = narrow ? 16 / 9 : 2.08;
            assert(Math.abs(d.leadFrame.width / d.leadFrame.height - ratio) < .025 && Math.abs(center(d.leadFrame) - width / 2) <= 2 && d.leadFrame.width <= 1321, `${label}: lead art is centered in its ${ratio.toFixed(2)} frame`, d);
            assert(Math.abs(d.leadFrame.width - d.leadMediaWidth) <= 2, `${label}: the lead source size matches its frame`, d);
            assert(d.filmFont.startsWith('"GT Sectra Display"') && d.takeStyle === 'italic' && d.takeFont.startsWith('"Tiempos Text"') && d.filmSize >= 40 && d.copySize >= 15 && d.leadCta.height >= 44, `${label}: legible headline, deck, and 44px review action`, d);
            if (narrow) assert(d.leadText.top >= d.leadFrame.bottom - 1 && Math.abs(d.leadText.width - d.leadFrame.width) <= 1, `${label}: the lead's text follows its still on narrow screens`, d);
            else assert(Math.abs(d.leadText.top - d.leadFrame.top) <= 1 && Math.abs(d.leadText.bottom - d.leadFrame.bottom) <= 1 && Math.abs(d.leadText.width - d.leadFrame.width) <= 1, `${label}: the lead's text overlays the desktop still`, d);
            const firstDoor = page.locator('.lunara-home-door').first();
            await firstDoor.hover();
            const hover = await firstDoor.evaluate(door => ({
                cue: getComputedStyle(door.querySelector('.lunara-home-door-cta')).textDecorationLine,
                media: getComputedStyle(door.querySelector('.lunara-home-door-media')).transform
            }));
            assert(hover.cue.includes('underline') && hover.media === 'none', `${label}: hover underlines the cue without moving the art`, hover);
            assert(d.headerBrand !== 'visible' && d.searchBox === null && d.searchControls === 1 && d.headerSearch.height >= 44 && d.headerMenu.height >= 44, `${label}: the header shows no wordmark at the top of the cover, and the one Search control is the header's`, d);
            assert(d.header.height >= (width > 1020 ? 64 : 56) && d.header.height <= (width > 1020 ? 64 : 56) + 1 && d.dateline.top >= d.header.bottom - .6, `${label}: the cover header is a ${width > 1020 ? 64 : 56}px bar (plus its hairline) and the masthead starts under it`, d);
            assert(d.datelineSize >= (width > 820 ? 13 : 12) && (width < 360 || d.datelineLines === 1) && Math.abs(d.datelineCenter - width / 2) <= 2 && d.datelineDots.slice(0, -1).every(c => c.includes('·')) && !d.datelineDots.at(-1).includes('·'), `${label}: the dateline is one centered line with middle dots between its items`, d);
            assert(d.leadFrame.top <= (narrow ? 280 : 300), `${label}: the still starts within the first ${narrow ? 280 : 300}px`, d);
            assert(d.logo.height <= (narrow ? 72.5 : 104.5) && d.logo.height >= 56 && d.logo.width <= Math.min(width, narrow ? 291 : 421) && Math.abs(center(d.logo) - width / 2) <= 2, `${label}: the responsive wordmark stays on the center axis`, d);
            assert(d.dek.top >= d.logo.bottom - 1 && d.dekSize >= 14, `${label}: the readable dek follows the wordmark`, d);
            // The Home type scale (3.2.161) on a section below the masthead.
            assert(d.sectionRule.bg === 'rgba(201, 169, 97, 0.28)' && Math.abs(d.sectionRule.h - 1) < .6 && d.sectionRule.titleFont.startsWith('"GT Sectra Display"') && d.sectionRule.titleWeight === '400', `${label}: a Home section opens on the gold hairline with a signature-face title`, d);
            assert(d.sectionRule.titleSize >= (width >= 1440 ? 56 : 34) && d.sectionRule.kickerSize >= 15 && d.sectionRule.summarySize >= 17 && d.sectionRule.h3Size >= 21, `${label}: section titles ${width >= 1440 ? 56 : 34}px or larger, kickers 15px, summaries 17px, card titles 21px`, d.sectionRule);
            assert(d.sectionRule.ownBorder === 0 && d.sectionRule.panelBorder === 0 && d.sectionRule.padTop >= 32 && d.sectionRule.padTop <= 48 && d.mastheadToSection >= 0 && d.mastheadToSection <= 70, `${label}: one hairline only (no legacy section border, none under the masthead) and the program's rhythm`, d);
            if (width === 1100) {
                const status = firstDoor.locator('.lunara-home-door-status');
                const mark = status.locator('.lunara-pillar-desk-mark');
                const animation = () => mark.evaluate(e => getComputedStyle(e).animationName);
                assert(await animation() === 'none', `${label}: an older update stays still`);
                await page.emulateMedia({ reducedMotion: 'no-preference' });
                await status.evaluate(e => e.classList.add('is-live'));
                assert(await animation() !== 'none', `${label}: today's update can pulse`);
                await page.emulateMedia({ reducedMotion: 'reduce' });
                assert(await animation() === 'none', `${label}: reduced motion leaves today's update still`);
            }
            await assertSquareSurfaces(page, label);
            await page.close();
        }
        process.stdout.write(`Home front desk browser: ${checks} checks passed.\n`);
    } finally { await browser.close(); }
})().catch(e => { console.error(e); process.exit(1); });
