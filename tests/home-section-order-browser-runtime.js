'use strict';
// Home section order under the real public cascade (3.2.121): the order the
// PHP Section Order writes into the DOM is the order a reader sees. Until this
// release style.css carried `order` overrides on the slot classes (2026-05-10)
// that pushed Featured and the Ledger below Journal, Picks and Facts whatever
// the Studio said. Direction D puts Stills that Rhyme, The Running Order and
// the Oscar Ledger right under the masthead, so DOM order must hold.
const fs = require('fs'), path = require('path');
const { chromium } = require('playwright-core');
const root = path.resolve(__dirname, '..');
const executablePath = process.env.LUNARA_BROWSER_EXECUTABLE || ['C:/Program Files/Google/Chrome/Application/chrome.exe', '/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome'].find(fs.existsSync);
const css = ['style.css', 'assets/css/lunara-shell.css', 'assets/css/lunara-home-modules.css', 'assets/css/lunara-public-guardrails.css'].map(f => fs.readFileSync(path.join(root, f), 'utf8')).join('\n');
// The program order, as the editorial-default preset sorts it since 3.2.138
// (the Journal second), each slot with a visible body so it has height.
const slots = ['pairing-desk', 'dispatch', 'ledger', 'featured', 'latest-reviews', 'oscar-picks', 'oscar-facts'];
const sections = ['<section class="lunara-home-masthead lunara-home-front-desk lunara-home-program" data-slot="masthead"><p>Masthead</p></section>']
    .concat(slots.map(s => `<section class="lunara-home-section lunara-home-slot-${s}" data-slot="${s}"><h2>${s}</h2><p>Body of ${s}.</p></section>`)).join('');
let checks = 0;
const assert = (ok, message, detail) => { checks++; if (!ok) { console.error(JSON.stringify(detail, null, 1)); throw new Error(message); } };
(async () => {
    const browser = await chromium.launch({ headless: true, executablePath });
    try {
        for (const width of [320, 390, 768, 1366]) {
            const page = await browser.newPage({ viewport: { width, height: 1000 }, javaScriptEnabled: false });
            await page.setContent(`<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>*,*::before,*::after{box-sizing:border-box}body{margin:0}</style><style>${css}</style></head><body class="home"><div id="primary" class="site-main lunara-front-page">${sections}</div></body></html>`, { waitUntil: 'load' });
            const d = await page.evaluate(() => [...document.querySelectorAll('#primary > section')].map(e => ({ slot: e.dataset.slot, top: e.getBoundingClientRect().top, height: e.getBoundingClientRect().height, order: getComputedStyle(e).order })));
            const label = `section order@${width}`;
            assert(d.every(s => s.height > 0), `${label}: every section has height`, d);
            assert(d.every(s => s.order === '0'), `${label}: no slot carries a CSS order override`, d);
            const visual = [...d].sort((a, b) => a.top - b.top).map(s => s.slot);
            assert(JSON.stringify(visual) === JSON.stringify(['masthead'].concat(slots)), `${label}: readers see the sections in DOM order: masthead, the Debrief showcase, the Journal, the Ledger, The Running Order, then the lanes`, { visual });
            await page.close();
        }
        process.stdout.write(`Home section order browser: ${checks} checks passed.\n`);
    } finally { await browser.close(); }
})().catch(e => { console.error(e); process.exit(1); });
