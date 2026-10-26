'use strict';
// Alternating panels (3.2.136) under the real public cascade, with the live
// site's white border token and the critical shell header.php prints. Real
// section fixtures (the masthead, the Debrief showcase, the Running Order,
// the Ledger band, the Journal lane, Latest Reviews) and live-shaped Oscar
// lanes are marked by inc/home-panels.php itself (home-panels-runtime.php
// --mark) in three orders: the live one, which ends on a panel above the
// footer; one with the band second, which ends on the ground; and one
// without the band. Rendered pixels decide: a panel's navy reaches both
// viewport edges, every seam carries exactly one rule, a plate never takes
// the colour of the surface under it, and nothing overflows.
const fs = require('fs'), path = require('path'), zlib = require('zlib'), { execFileSync } = require('child_process');
const { chromium } = require('playwright-core');
const assertSquareSurfaces = require('./tools/assert-square-surfaces.cjs');
const root = path.resolve(__dirname, '..');
const executablePath = process.env.LUNARA_BROWSER_EXECUTABLE || ['C:/Program Files/Google/Chrome/Application/chrome.exe', '/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome'].find(fs.existsSync);
const read = f => fs.readFileSync(path.join(root, f), 'utf8');
const php = (file, args = [], input) => execFileSync('php', [path.join(__dirname, file), ...args], { encoding: 'utf8', input });

// The container's flex column, gutters and gap live in header.php's critical
// shell, so the fixture takes that rule from header.php itself.
const shellRule = (read('header.php').match(/body\.home \.lunara-front-page,\s*body\.home main\.lunara-front-page \{[^}]*\}/) || [''])[0];
if (!shellRule.includes('display: flex')) throw new Error('header.php must print the Home container rule');
const css = ['style.css', 'assets/css/lunara-shell.css', 'assets/css/lunara-home-modules.css', 'assets/css/lunara-cinematic-home.css', 'assets/css/lunara-home-carousels.css', 'assets/css/lunara-home-running-order.css', 'assets/css/lunara-home-stills-that-rhyme.css', 'assets/css/lunara-home-ledger-band.css', 'assets/css/lunara-public-guardrails.css'].map(read).join('\n') + '\n' + shellRule;
// The page ground comes from the header command's own CSS (body.lunara-header-takeover).
const headerCss = JSON.parse(php('header-navigation-runtime.php', ['--fixture'])).css;
// The live site's palette tokens (lunara-design-tokens-css) and its white
// site-wide border token (lunara-runtime-customizer-css).
const liveTokens = ':root:root{--lunara-bg-primary:#0a1520;--lunara-bg-secondary:#0f1d2e}:root{--lunara-border:rgba(255,255,255,0.24)}';

const still = 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="600" height="900"><rect width="600" height="900" fill="#5a4630"/></svg>');
const offline = html => html.replace(/\ssrcset="[^"]*"/g, '').replace(/\ssizes="[^"]*"/g, '').replace(/src="https?:[^"]*"/g, `src="${still}"`);
const one = (html, slot) => {
    const m = html.match(new RegExp(`<section class="lunara-home-section lunara-home-slot-${slot}[\\s\\S]*?<\\/section>`));
    if (!m || (m[0].match(/<section\b/g) || []).length !== 1) throw new Error(`the ${slot} fixture must be one section`);
    return m[0];
};
const carousels = php('home-carousels-runtime.php', ['--fixture']);
const sections = {
    'pairing-desk': php('home-stills-that-rhyme-runtime.php', ['--fixture']),
    featured: php('home-running-order-runtime.php', ['--fixture']),
    ledger: php('home-ledger-band-runtime.php', ['--fixture']),
    dispatch: one(carousels, 'dispatch'),
    'latest-reviews': one(carousels, 'latest-reviews'),
    'oscar-picks': '<section class="lunara-home-section lunara-home-slot-oscar-picks lunara-oscar-picks-section is-density-editorial"><div class="lunara-home-section-head is-with-summary"><div><p class="lunara-home-section-kicker">Lunara Film Sweep Watch</p><h2 class="lunara-home-section-title">A board for the season</h2><p class="lunara-home-section-summary">Live paths across the categories.</p></div><a class="lunara-section-link" href="/oscars/">Open the full Oscars Ledger</a></div></section>',
    'oscar-facts': '<section class="lunara-home-section lunara-home-slot-oscar-facts lunara-oscar-facts-section"><div class="lunara-home-section-head"><div><h2 class="lunara-home-section-title">From the record</h2></div><a class="lunara-section-link" href="/oscars/">Open the Ledger</a></div></section>',
};
const masthead = offline(php('home-front-desk-runtime.php', ['--fixture']));
const footer = '<footer class="lunara-site-footer" role="contentinfo"><div class="lunara-footer-inner"><nav class="lunara-footer-nav-grid" aria-label="Footer"><ul class="lunara-footer-curated-list"><li><a href="/reviews/">Reviews</a></li><li><a href="/journal/">Journal</a></li><li><a href="/oscars/">Oscars</a></li></ul></nav><p class="lunara-footer-copyright">© 2026 Lunara Film</p></div></footer>';
const orders = {
    live: ['pairing-desk', 'dispatch', 'ledger', 'featured', 'oscar-picks'],
    'band second': ['dispatch', 'ledger', 'oscar-facts', 'featured', 'latest-reviews'],
    'no band': ['oscar-facts', 'featured', 'pairing-desk'],
};

// A minimal PNG reader (8-bit RGB or RGBA, not interlaced: what Chromium writes).
function decodePng(buf) {
    let pos = 8, width = 0, height = 0, bpp = 0;
    const idat = [];
    while (pos < buf.length) {
        const len = buf.readUInt32BE(pos), type = buf.toString('ascii', pos + 4, pos + 8), data = buf.subarray(pos + 8, pos + 8 + len);
        if (type === 'IHDR') {
            width = data.readUInt32BE(0); height = data.readUInt32BE(4);
            if (data[8] !== 8 || data[12] !== 0) throw new Error('8-bit, non-interlaced PNG expected');
            bpp = data[9] === 6 ? 4 : data[9] === 2 ? 3 : 0;
            if (!bpp) throw new Error('RGB or RGBA PNG expected');
        } else if (type === 'IDAT') idat.push(data);
        else if (type === 'IEND') break;
        pos += 12 + len;
    }
    const raw = zlib.inflateSync(Buffer.concat(idat)), stride = width * bpp, out = Buffer.alloc(height * stride);
    for (let y = 0; y < height; y++) {
        const filter = raw[y * (stride + 1)];
        for (let x = 0; x < stride; x++) {
            const a = x >= bpp ? out[y * stride + x - bpp] : 0, b = y ? out[(y - 1) * stride + x] : 0, c = x >= bpp && y ? out[(y - 1) * stride + x - bpp] : 0;
            let v = raw[y * (stride + 1) + 1 + x];
            if (filter === 1) v += a;
            else if (filter === 2) v += b;
            else if (filter === 3) v += (a + b) >> 1;
            else if (filter === 4) { const p = a + b - c, pa = Math.abs(p - a), pb = Math.abs(p - b), pc = Math.abs(p - c); v += pa <= pb && pa <= pc ? a : pb <= pc ? b : c; }
            out[y * stride + x] = v & 255;
        }
    }
    return (x, y) => { const i = y * stride + x * bpp; return [out[i], out[i + 1], out[i + 2]]; };
}

// The panel is the Ledger band's navy. The ground is what the page paints
// behind the sections: #0a1520, or #07111d on phones up to 520px, where
// lunara-shell.css paints the Home container itself.
const NAVY = [15, 29, 46];
const dist = (p, q) => Math.hypot(p[0] - q[0], p[1] - q[1], p[2] - q[2]);
const rgb = c => (c.match(/[\d.]+/g) || []).map(Number);

let checks = 0;
const assert = (ok, message, detail) => { checks++; if (!ok) { console.error(JSON.stringify(detail, null, 1)); throw new Error(message); } };

(async () => {
    const browser = await chromium.launch({ headless: true, executablePath });
    try {
        for (const [name, order] of Object.entries(orders)) {
            const marked = JSON.parse(php('home-panels-runtime.php', ['--mark'], JSON.stringify(order.map(slug => ({ slug, html: offline(sections[slug]) })))));
            for (const width of [320, 390, 768, 1100, 1440, 1920]) {
                const label = `panels ${name}@${width}`;
                const page = await browser.newPage({ viewport: { width, height: 900 }, javaScriptEnabled: false });
                await page.setContent(`<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>*,*::before,*::after{box-sizing:border-box}body{margin:0}</style><style>${css}</style><style>${liveTokens}</style>${headerCss}</head><body class="home lunara-home-cover lunara-header-takeover lunara-header-cover"><div id="main-container"><main id="main" class="site-main hfeed"><div id="primary" class="site-main lunara-front-page">${masthead}${marked.join('')}</div></main>${footer}</div></body></html>`, { waitUntil: 'load' });
                const d = await page.evaluate(() => {
                    const box = e => { const b = e.getBoundingClientRect(); return { top: b.top + scrollY, bottom: b.bottom + scrollY }; };
                    const list = [document.querySelector('.lunara-home-masthead'), ...document.querySelectorAll('.lunara-front-page > .lunara-home-section')].map(e => {
                        const s = getComputedStyle(e);
                        return { ...box(e), slot: (e.className.match(/lunara-home-slot-([a-z-]+)/) || [, 'masthead'])[1], panel: e.classList.contains('is-home-panel'), last: e.classList.contains('is-home-panel-last'), after: e.classList.contains('is-after-home-panel'), band: e.classList.contains('lunara-home-ledger-band'), padTop: parseFloat(s.paddingTop), padBottom: parseFloat(s.paddingBottom), hairline: getComputedStyle(e, '::before').content !== 'none' && e.classList.contains('lunara-home-section'),
                            plates: [...e.querySelectorAll('.lunara-home-stills-frame, .lunara-home-running-order-frame')].map(f => getComputedStyle(f).backgroundColor) };
                    });
                    const f = document.querySelector('.lunara-site-footer');
                    const container = getComputedStyle(document.querySelector('#primary')).backgroundColor;
                    const opaque = c => { const n = (c.match(/[\d.]+/g) || []).map(Number); return n.length < 4 || n[3] > 0; };
                    return { scroll: document.documentElement.scrollWidth, height: document.documentElement.scrollHeight, body: getComputedStyle(document.body).backgroundColor, ground: opaque(container) ? container : getComputedStyle(document.body).backgroundColor, list, footer: { top: f.getBoundingClientRect().top + scrollY } };
                });
                assert(d.scroll <= width + 1, `${label}: no document overflow`, d.scroll);
                assert(d.body === 'rgb(10, 21, 32)' && (d.ground === 'rgb(10, 21, 32)' || (width <= 520 && d.ground === 'rgb(7, 17, 29)')), `${label}: the page ground is #0a1520 (#07111d on phones)`, d);
                const GROUND = rgb(d.ground);
                // A row is a rule when its colour is far from both surfaces a seam can show.
                const isRule = p => dist(p, GROUND) > 12 && dist(p, NAVY) > 12;
                // Three full-height strips: both viewport edges and the centre.
                const centre = Math.round(width / 2);
                const strips = {};
                for (const x0 of [0, centre, width - 2]) strips[x0] = decodePng(await page.screenshot({ fullPage: true, clip: { x: x0, y: 0, width: 2, height: d.height } }));
                const px = (x, y) => {
                    const cx = Math.min(width - 1, Math.max(0, Math.round(x))), cy = Math.min(d.height - 1, Math.max(0, Math.round(y)));
                    const x0 = cx <= 1 ? 0 : cx >= width - 2 ? width - 2 : centre;
                    if (cx - x0 < 0 || cx - x0 > 1) throw new Error(`no strip holds x=${cx}`);
                    return strips[x0](cx - x0, cy);
                };
                const runs = (x, from, to) => { let n = 0, inRule = false; for (let y = Math.max(0, Math.floor(from)); y <= Math.min(d.height - 1, Math.ceil(to)); y++) { const r = isRule(px(x, y)); if (r && !inRule) n++; inRule = r; } return n; };
                const sections = d.list.slice(1);

                // The plan: panels alternate, the band is one, none touch.
                sections.forEach((s, i) => {
                    if (s.band) assert(s.panel, `${label}: the band is a panel`, s);
                    if (i) assert(!(s.panel && sections[i - 1].panel), `${label}: two panels never touch (${s.slot})`, sections);
                    assert(s.after === (!s.panel && i > 0 && sections[i - 1].panel), `${label}: ${s.slot} is marked after a panel exactly when it follows one`, s);
                });

                for (const s of sections) {
                    const mid = (s.top + s.bottom) / 2;
                    const fill = [px(0, mid), px(1, mid), px(width - 2, mid), px(width - 1, mid)];
                    if (s.panel) {
                        assert(fill.every(p => dist(p, NAVY) <= 2), `${label}: the ${s.slot} panel paints the Ledger navy to both viewport edges`, { fill, s });
                        if (!s.band) assert(s.padTop >= 40 && s.padBottom >= 40 && Math.abs(s.padTop - s.padBottom) < 1, `${label}: the ${s.slot} panel pads its top and bottom alike`, s);
                    } else {
                        assert(fill.every(p => dist(p, GROUND) <= 2), `${label}: the ${s.slot} section sits on the ground`, { fill, s });
                    }
                    // A plate keeps a colour that differs from the surface under it.
                    const surface = s.panel ? 'rgb(15, 29, 46)' : d.ground;
                    assert(s.plates.every(c => c !== surface), `${label}: no plate in ${s.slot} takes the colour of its ${s.panel ? 'panel' : 'ground'}`, s.plates);
                    if (s.after) assert(!s.hairline && s.padTop === 0, `${label}: ${s.slot} opens on the panel's rule, with no hairline or padding of its own`, s);
                }

                // One rule per seam: across the centre column every seam has
                // exactly one rule; at the viewport edge, exactly one when a
                // panel takes part (its rule is full bleed), none otherwise.
                d.list.forEach((a, i) => {
                    const b = d.list[i + 1];
                    if (!b) return;
                    const from = a.panel ? a.bottom - 8 : a.bottom + 1;
                    const to = b.panel ? b.top + 8 : b.hairline ? b.top + 4 : b.top - 1;
                    const seam = `${a.slot} → ${b.slot}`;
                    assert(b.top - a.bottom >= 39, `${label}: ${seam} keeps the program's breathing room`, { a, b });
                    assert(runs(width / 2, from, to) === 1, `${label}: ${seam} carries exactly one rule`, { a, b, from, to });
                    assert(runs(1, from, to) === (a.panel || b.panel ? 1 : 0), `${label}: ${seam} rule spans the viewport exactly when a panel takes part`, { a, b });
                });

                // The last section and the footer: a last panel runs down to the
                // footer's rule and drops its own; a last ground section keeps
                // the footer's margin. Either way, one rule.
                const last = sections[sections.length - 1];
                if (last.panel) assert(last.last && Math.abs(d.footer.top - last.bottom) <= 1, `${label}: the last panel meets the footer's rule`, { last, footer: d.footer });
                else assert(!last.last && d.footer.top - last.bottom >= 24, `${label}: a last ground section keeps the footer's margin`, { last, footer: d.footer });
                assert(runs(width / 2, last.panel ? last.bottom - 8 : last.bottom + 1, d.footer.top + 4) === 1, `${label}: the footer seam carries one rule`, { last, footer: d.footer });

                await assertSquareSurfaces(page, label);
                await page.close();
            }
        }
        process.stdout.write(`Home panels browser: ${checks} checks passed.\n`);
    } finally { await browser.close(); }
})().catch(e => { console.error(e); process.exit(1); });
