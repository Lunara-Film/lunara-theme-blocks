'use strict';
// Lane heads in the program's voice (3.2.131), under the real public cascade
// with the live site's Customizer token override. The Journal lane (rendered
// by the real renderer, from home-carousels-runtime.php --fixture) and the
// Oscar Picks head (the live markup shape) must read like the Running Order's
// head: the same kicker, headline face and link voice, one gold rule under
// the head, the gold hairline above the section, and a 44px link target.
// Measured against the Running Order itself, so no value is pinned here.
const fs = require('fs'), path = require('path'), { execFileSync } = require('child_process');
const { chromium } = require('playwright-core');
const root = path.resolve(__dirname, '..');
const executablePath = process.env.LUNARA_BROWSER_EXECUTABLE || ['C:/Program Files/Google/Chrome/Application/chrome.exe', '/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome'].find(fs.existsSync);
const css = ['style.css', 'assets/css/lunara-shell.css', 'assets/css/lunara-home-modules.css', 'assets/css/lunara-cinematic-home.css', 'assets/css/lunara-home-carousels.css', 'assets/css/lunara-home-running-order.css', 'assets/css/lunara-public-guardrails.css'].map(f => fs.readFileSync(path.join(root, f), 'utf8')).join('\n');
// lunara-runtime-customizer-css on the live site sets the site-wide border token to white.
const liveTokens = ':root{--lunara-border:rgba(255,255,255,0.24)}';
const carousels = execFileSync('php', [path.join(__dirname, 'home-carousels-runtime.php'), '--fixture'], { encoding: 'utf8' });
const journal = (carousels.match(/<section class="lunara-home-section lunara-home-slot-dispatch[\s\S]*?<\/section>/) || [''])[0].replace(/<img[^>]*>/g, '');
if (!journal.includes('lunara-home-section-kicker')) throw new Error('the fixture Journal lane must carry its kicker');
const runningOrder = '<section class="lunara-home-section lunara-home-slot-featured lunara-home-running-order"><header class="lunara-home-running-order-head"><p class="lunara-home-running-order-kicker">The Running Order</p><h2 class="lunara-home-running-order-title">Every review, newest first.</h2><a class="lunara-home-running-order-all" href="/reviews/">All reviews</a></header></section>';
const picks = '<section class="lunara-home-section lunara-home-slot-oscar-picks lunara-oscar-picks-section is-density-editorial"><div class="lunara-home-section-head is-with-summary"><div><p class="lunara-home-section-kicker">Lunara Sweep Watch</p><h2 class="lunara-home-section-title">The Odyssey across the 99th Academy Awards</h2><p class="lunara-home-section-summary">One film, fifteen live paths. The board will move as the season hardens.</p></div><a class="lunara-section-link" href="/oscars/">Open the full Oscars Ledger</a></div></section>';
let checks = 0;
const assert = (ok, message, detail) => { checks++; if (!ok) { console.error(JSON.stringify(detail, null, 1)); throw new Error(message); } };
(async () => {
    const browser = await chromium.launch({ headless: true, executablePath });
    try {
        for (const width of [360, 390, 768, 1100, 1440]) {
            const page = await browser.newPage({ viewport: { width, height: 1000 }, javaScriptEnabled: false });
            await page.setContent(`<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>*,*::before,*::after{box-sizing:border-box}body{margin:0}</style><style>${css}</style><style>${liveTokens}</style></head><body class="home"><div id="primary" class="site-main lunara-front-page">${runningOrder}${journal}${picks}</div></body></html>`, { waitUntil: 'load' });
            const d = await page.evaluate(() => {
                const st = (e, pseudo) => { const c = getComputedStyle(e, pseudo); return { color: c.color, font: c.fontFamily, size: c.fontSize, weight: c.fontWeight, spacing: c.letterSpacing, transform: c.textTransform, style: c.fontStyle, deco: c.textDecorationLine, bg: c.backgroundColor, h: c.height, bb: c.borderBottomWidth + ' ' + c.borderBottomStyle + ' ' + c.borderBottomColor }; };
                const box = e => { const b = e.getBoundingClientRect(); return { left: b.left, right: b.right, top: b.top, bottom: b.bottom, width: b.width, height: b.height }; };
                const ro = document.querySelector('.lunara-home-running-order');
                const model = { kicker: st(ro.querySelector('.lunara-home-running-order-kicker')), title: st(ro.querySelector('.lunara-home-running-order-title')), link: st(ro.querySelector('.lunara-home-running-order-all')), head: st(ro.querySelector('.lunara-home-running-order-head')) };
                const lanes = [...document.querySelectorAll('.lunara-home-slot-dispatch, .lunara-home-slot-oscar-picks')].map(s => {
                    const head = s.querySelector('.lunara-home-section-head'), k = head.querySelector('.lunara-home-section-kicker'), t = head.querySelector('.lunara-home-section-title'), a = head.querySelector(':scope > .lunara-section-link');
                    return { slot: s.className.match(/lunara-home-slot-([a-z-]+)/)[1], kicker: st(k), title: st(t), link: st(a), head: st(head), hairline: st(s, '::before'), kickerBox: box(k), titleBox: box(t), linkBox: box(a), headBox: box(head) };
                });
                return { scroll: document.documentElement.scrollWidth, model, lanes };
            });
            const label = `lane heads@${width}`;
            assert(d.scroll <= width + 1, `${label}: no document overflow`, d.scroll);
            for (const lane of d.lanes) {
                const l = `${label} ${lane.slot}`;
                for (const key of ['color', 'font', 'size', 'weight', 'spacing', 'transform']) assert(lane.kicker[key] === d.model.kicker[key], `${l}: the kicker matches the Running Order kicker (${key})`, { lane: lane.kicker, model: d.model.kicker });
                for (const key of ['font', 'weight', 'size']) assert(lane.title[key] === d.model.title[key], `${l}: the headline matches the Running Order headline (${key})`, { lane: lane.title, model: d.model.title });
                for (const key of ['color', 'font', 'style', 'deco', 'size']) assert(lane.link[key] === d.model.link[key], `${l}: the link matches the Running Order link (${key})`, { lane: lane.link, model: d.model.link });
                assert(lane.head.bb === d.model.head.bb && lane.head.bb.endsWith('rgba(201, 169, 97, 0.28)'), `${l}: one gold rule under the head, as under the Running Order, despite the white site token`, { lane: lane.head.bb, model: d.model.head.bb });
                assert(lane.hairline.bg === 'rgba(201, 169, 97, 0.28)' && lane.hairline.h === '1px', `${l}: the section opens on the gold hairline`, lane.hairline);
                assert(lane.linkBox.height >= 44 && lane.kickerBox.bottom <= lane.titleBox.top + 1 && lane.headBox.right <= width + 1, `${l}: the kicker sits above the headline and the link keeps a 44px target`, lane);
                if (width <= 820) assert(lane.linkBox.top >= lane.titleBox.bottom - 1 && Math.abs(lane.linkBox.left - lane.titleBox.left) <= 1, `${l}: on phones the link sits under the headline`, lane);
                else assert(lane.linkBox.left >= lane.titleBox.right - 1 || lane.linkBox.left >= lane.headBox.left + lane.headBox.width / 2, `${l}: on wider screens the link stands at the right of the head`, lane);
            }
            await page.close();
        }
        process.stdout.write(`Home lane heads browser: ${checks} checks passed.\n`);
    } finally { await browser.close(); }
})().catch(e => { console.error(e); process.exit(1); });
